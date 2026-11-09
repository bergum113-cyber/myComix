<?php
/**
 * myComix 라이브러리 엔진 — 시리즈 · 작가 · 장르 · 태그
 * ★ (2026-10-29) 신규
 *
 * 설계 원칙
 * - 원본 파일(압축파일 등)은 절대 수정하지 않음. 정보는 src/library.db(SQLite)에만 저장
 *   (src/.htaccess 가 "Deny from all" 이라 웹에서 직접 열람 불가 — db·wal·shm 모두)
 * - 기본 정보(시리즈명·작가·권수)는 파일명/폴더명에서 자동 분석. 관리자가 직접 고친 값은 다시 분석해도 덮어쓰지 않음
 * - 폴더 접근 권한은 기존 규칙(최상위 폴더의 {폴더}.json + src/folder_permissions.json)을 그대로 따름
 * - PDO SQLite 가 없으면 mc_lib_db() 가 null → 이 기능만 꺼지고 다른 화면은 그대로
 *
 * 이 파일은 bootstrap.php 이후에 require 해야 함(세션·base_dirs·encode_url 등 사용)
 */

if (!defined('MC_LIB_PARSER')) define('MC_LIB_PARSER', 15); // ★ (2026-11-06) 15: 영어 제목에 붙은 숫자('Imouto2 ｜ 여동생 2')는 다른 제목이 같은 번호일 때 권 번호로(펜닐 제보) — ★ (2026-11-05) 14: '제목: N 부제'·'제목N 부제'·'로마자 원제 한글 제목' 묶음 보강(펜닐 제보) — // ★ (2026-10-29) 파일명 인식 규칙 버전 — 규칙을 바꾸면 올릴 것(폴더 서명에 들어가 빠른 스캔도 모든 폴더를 다시 판단) / 2: 밑줄(_)을 띄어쓰기로 / 3: 'Dai X wa'·'|' 두 제목·같은 작가 같은 제목 / 4: Dai 없는 '숫자 wa' 는 뒤가 끝·구분 기호일 때만 / 5: ~부제~·전편/후편·상/하·Part·II·2nd·Zoku·번외·편집판 꼬리 단어·괄호 속 화 번호·번호 범위 / 6: 두 제목 구분 '│'(U+2502) 등 세로줄 문자·번역제 끝 by 작가·제목 끝 마침표 유지·전각 문장부호 / 7: 작가 'N/A'(모름)를 N·A 두 작가로 나누지 않음 + 다시 판단한 작품의 자동 작가 다시 계산 / 8: (2026-10-30) 이름 양끝 정리를 UTF-8 안전하게(シ·걷 등으로 끝나는 이름이 깨지던 문제) — 이미 깨져 저장된 이름을 다시 판단 / 9: ★ (2026-10-31) '제목 - 부제'·'제목 N -부제-' 근거 규칙과 폴더 다른 같은 작가 묶기(10-31 추가)를 빠른 스캔·자동 스캔에도 반영 — 8 그대로 두어 파일 목록이 안 바뀐 폴더는 다시 판단되지 않던 문제 / 10: ★ (2026-11-02 01:53) 그룹(서클) 따로('[그룹 (작가)]') · 'ㅣ'(한글 자모)를 두 제목 구분으로 · '｜ by 작가'(뒤 제목 없음) · 숫자 든 작가 이름('by Blvefo9') — 예전에 작가로 붙은 그룹은 다시 판단 때 그룹으로 옮김 / 11: ★ (2026-11-02 02:34) 'by 작가'에 숫자로 시작하는 이름('by 4why')도 작가 — 숫자뿐인 낱말·숫자+권·화만 아님, 이름 속 '화'·'권' 글자도 작가로 / 12: ★ (2026-11-02 02:40) 작가·그룹·장르·태그는 비교 키(대소문자·띄어쓰기·기호·전각 무시)로 같은 이름 하나 — 다시 판단 때 갈라진 이름이 모임 / 13: ★ (2026-11-02 11:41) 괄호 속 권·화 범위 '(1-10권)'·괄호 나눔 표시 '1巻 (上)'·총집편을 번외로·'Stand by Me' 같은 by 숙어·대소문자 다른 나눔 단어(zenpen)
if (!defined('MC_LIB_SCHEMA')) define('MC_LIB_SCHEMA', 4); // ★ (2026-10-29) 2: volumes 에 subtitle·pages·info·probe 추가(중복 묶기·쪽수·ComicInfo.xml) / 3: dkey·root·folders(빠른 스캔·탐색 화면 속도)

/** 라이브러리 대상 확장자 (이미지 압축 + 문서형 책) */
function mc_lib_exts() {
    return ['zip', 'cbz', 'rar', 'cbr', '7z', 'cb7', 'pdf', 'epub', 'txt'];
}

/** PDO SQLite 사용 가능 여부 */
function mc_lib_available() {
    return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
}

/** DB 연결(요청당 1회). 실패 시 null */
function mc_lib_db() {
    static $db = null, $tried = false;
    if ($tried) return $db;
    $tried = true;
    if (!mc_lib_available()) return null;
    try {
        $dir = __DIR__ . '/src';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $db = new PDO('sqlite:' . $dir . '/library.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
        mc_lib_migrate($db);
    } catch (\Throwable $e) {
        error_log('[library] DB open failed: ' . $e->getMessage());
        $db = null;
    }
    return $db;
}

/** 스키마 생성/업그레이드 */
function mc_lib_migrate(PDO $db) {
    $db->exec('CREATE TABLE IF NOT EXISTS meta (k TEXT PRIMARY KEY, v TEXT)');
    $ver = (int)($db->query("SELECT v FROM meta WHERE k='schema'")->fetchColumn() ?: 0);
    if ($ver >= MC_LIB_SCHEMA) return;
    // 동시에 여러 요청이 업그레이드하지 않게 쓰기 잠금 후 버전 재확인
    $db->exec('BEGIN IMMEDIATE');
    try {
    $ver = (int)($db->query("SELECT v FROM meta WHERE k='schema'")->fetchColumn() ?: 0);
    if ($ver >= MC_LIB_SCHEMA) { $db->exec('COMMIT'); return; }
    $db->exec('CREATE TABLE IF NOT EXISTS series (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bidx INTEGER NOT NULL DEFAULT 0,
        name TEXT NOT NULL,
        name_key TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'\',
        description TEXT NOT NULL DEFAULT \'\',
        manual INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL DEFAULT 0,
        updated_at INTEGER NOT NULL DEFAULT 0,
        UNIQUE(bidx, name_key)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS volumes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bidx INTEGER NOT NULL DEFAULT 0,
        path TEXT NOT NULL,
        folder TEXT NOT NULL DEFAULT \'\',
        filename TEXT NOT NULL DEFAULT \'\',
        ext TEXT NOT NULL DEFAULT \'\',
        series_id INTEGER REFERENCES series(id) ON DELETE SET NULL,
        vol_no REAL,
        vol_label TEXT NOT NULL DEFAULT \'\',
        size INTEGER NOT NULL DEFAULT 0,
        mtime INTEGER NOT NULL DEFAULT 0,
        added_at INTEGER NOT NULL DEFAULT 0,
        seen_at INTEGER NOT NULL DEFAULT 0,
        missing INTEGER NOT NULL DEFAULT 0,
        manual INTEGER NOT NULL DEFAULT 0,
        UNIQUE(bidx, path)
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS ix_vol_series ON volumes(series_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS ix_vol_folder ON volumes(bidx, folder)');
    $db->exec('CREATE TABLE IF NOT EXISTS terms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL,
        name TEXT NOT NULL,
        UNIQUE(kind, name)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS series_terms (
        series_id INTEGER NOT NULL REFERENCES series(id) ON DELETE CASCADE,
        term_id INTEGER NOT NULL REFERENCES terms(id) ON DELETE CASCADE,
        source TEXT NOT NULL DEFAULT \'auto\',
        PRIMARY KEY(series_id, term_id)
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS ix_st_term ON series_terms(term_id)');
    $db->exec('CREATE TABLE IF NOT EXISTS reads (
        user TEXT NOT NULL,
        bidx INTEGER NOT NULL DEFAULT 0,
        path TEXT NOT NULL,
        page INTEGER NOT NULL DEFAULT 0,
        total INTEGER NOT NULL DEFAULT 0,
        finished INTEGER NOT NULL DEFAULT 0,
        updated_at INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY(user, bidx, path)
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS ix_reads_user ON reads(user, updated_at)');
    // 시리즈 이름을 바꾸거나 합쳤을 때 예전 이름(키) → 현재 시리즈. 자동 스캔이 예전 파일명으로 다시 쪼개지 않게
    $db->exec('CREATE TABLE IF NOT EXISTS series_alias (
        bidx INTEGER NOT NULL DEFAULT 0,
        name_key TEXT NOT NULL,
        series_id INTEGER NOT NULL REFERENCES series(id) ON DELETE CASCADE,
        PRIMARY KEY(bidx, name_key)
    )');
    // ★ (2026-10-29) 스키마 2 — 기존 DB 는 열만 추가(데이터 보존)
    $cols = [];
    foreach ($db->query('PRAGMA table_info(volumes)')->fetchAll() as $c) $cols[$c['name']] = true;
    if (!isset($cols['subtitle'])) $db->exec("ALTER TABLE volumes ADD COLUMN subtitle TEXT NOT NULL DEFAULT ''");   // 권 번호 뒤 부제
    if (!isset($cols['pages']))    $db->exec('ALTER TABLE volumes ADD COLUMN pages INTEGER NOT NULL DEFAULT 0');      // 이미지 수(zip/cbz)
    if (!isset($cols['info']))     $db->exec("ALTER TABLE volumes ADD COLUMN info TEXT NOT NULL DEFAULT ''");       // ComicInfo.xml 요약(JSON)
    if (!isset($cols['probe']))    $db->exec("ALTER TABLE volumes ADD COLUMN probe TEXT NOT NULL DEFAULT ''");      // 마지막으로 읽은 파일 기준(크기:수정시각)
    // ★ (2026-10-29) 스키마 3 — 탐색 화면을 SQL 로 집계(같은 책 묶음 키·최상위 폴더), 바뀌지 않은 폴더 건너뛰기(폴더 서명)
    $newKey = !isset($cols['dkey']);
    if ($newKey)                   $db->exec("ALTER TABLE volumes ADD COLUMN dkey TEXT NOT NULL DEFAULT ''");       // mc_lib_dup_key()
    if (!isset($cols['root']))     $db->exec("ALTER TABLE volumes ADD COLUMN root TEXT NOT NULL DEFAULT ''");       // 경로 첫 부분(권한 확인 단위)
    // ★ (2026-11-08 fix64) 스키마 4 — 같은 권 사본의 순서를 관리자가 정함(0 = 정하지 않음 → 예전처럼 자동: 읽던 사본·쪽수·크기·수정 시각)
    if (!isset($cols['corder']))   $db->exec('ALTER TABLE volumes ADD COLUMN corder INTEGER NOT NULL DEFAULT 0');
    $db->exec('CREATE INDEX IF NOT EXISTS ix_vol_bidx ON volumes(bidx, missing, series_id)');
    $db->exec('CREATE TABLE IF NOT EXISTS folders (
        bidx INTEGER NOT NULL DEFAULT 0,
        path TEXT NOT NULL,
        sig TEXT NOT NULL DEFAULT \'\',
        pending INTEGER NOT NULL DEFAULT 0,
        rkeys TEXT NOT NULL DEFAULT \'\',
        seen_at INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY(bidx, path)
    )');
    if ($newKey) { // 기존 권 채우기(한 번만)
        $up = $db->prepare('UPDATE volumes SET dkey=?, root=? WHERE id=?');
        foreach ($db->query('SELECT id, path, vol_no, vol_label, subtitle FROM volumes')->fetchAll() as $r) {
            $up->execute([mc_lib_dup_key($r), mc_lib_root_of($r['path']), (int)$r['id']]);
        }
    }
    $db->exec("INSERT OR REPLACE INTO meta(k, v) VALUES('schema', '" . (int)MC_LIB_SCHEMA . "')");
    $db->exec('COMMIT');
    } catch (\Throwable $e) {
        try { $db->exec('ROLLBACK'); } catch (\Throwable $e2) {}
        throw $e;
    }
}

// ============================================================
// 문자열 정규화 · 파일명 분석
// ============================================================

function mc_lib_nfc($s) {
    $s = (string)$s;
    if (class_exists('Normalizer')) {
        $n = Normalizer::normalize($s, Normalizer::FORM_C);
        if ($n !== false && $n !== null) $s = $n;
    }
    return $s;
}

function mc_lib_lower($s) {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/** ★ (2026-10-29) 한자 숫자 → 정수(一~九百九十九). 실패 null */
function mc_lib_kanji_num($k) {
    $d = ['〇' => 0, '零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9];
    $n = 0; $cur = 0;
    foreach (preg_split('//u', (string)$k, -1, PREG_SPLIT_NO_EMPTY) as $c) {
        if (isset($d[$c])) { $cur = $cur * 10 + $d[$c]; continue; }
        if ($c === '十') { $n += ($cur ?: 1) * 10; $cur = 0; continue; }
        if ($c === '百') { $n += ($cur ?: 1) * 100; $cur = 0; continue; }
        return null;
    }
    $n += $cur;
    return $n > 0 ? $n : null;
}

/** ★ (2026-10-29) 일본어 로마자 숫자(ichi~kyuu, juu 조합: nana=7, juuni=12, nijuu=20, sanjuugo=35) → 정수. 실패 null */
function mc_lib_romaji_num($w) {
    $w = strtolower((string)$w);
    $u = ['ichi' => 1, 'ni' => 2, 'san' => 3, 'yon' => 4, 'yo' => 4, 'shi' => 4, 'go' => 5, 'roku' => 6, 'nana' => 7, 'shichi' => 7, 'hachi' => 8, 'kyuu' => 9, 'kyu' => 9, 'ku' => 9];
    if (!preg_match('/^(?:(ni|san|yon|yo|go|roku|nana|shichi|hachi|kyuu|kyu)?(juu|jyuu|ju))?(ichi|ni|san|yon|yo|shi|go|roku|nana|shichi|hachi|kyuu|kyu|ku)?$/', $w, $m) || $w === '') return null;
    $n = 0;
    if (!empty($m[2])) $n += (!empty($m[1]) ? $u[$m[1]] : 1) * 10;
    if (!empty($m[3])) $n += $u[$m[3]];
    return $n > 0 ? $n : null;
}

/**
 * ★ (2026-10-29) 전편·후편·상·하 표시 → [순서(1 앞·2 가운데·3 뒤), 저장 표시(前編·中編·後編 또는 上·中·下)]
 */
function mc_lib_part_info($w) {
    $w = mb_strtolower((string)$w, 'UTF-8');
    $hen = [1 => ['zenpen', 'zempen', '前編', '前篇', '前编', '전편', '상편'], 2 => ['chuuhen', 'chuhen', '中編', '中篇', '中编', '중편'], 3 => ['kouhen', 'kohen', '後編', '後篇', '后编', '后篇', '후편', '하편']];
    foreach ($hen as $n => $list) if (in_array($w, $list, true)) return [$n, ['', '前編', '中編', '後編'][$n]];
    $kan = [1 => ['joukan', 'jokan', '上巻', '上卷', '上', '상권', '상'], 2 => ['chuukan', '中巻', '中卷', '中', '중권'], 3 => ['gekan', '下巻', '下卷', '下', '하권', '하']];
    foreach ($kan as $n => $list) if (in_array($w, $list, true)) return [$n, ['', '上', '中', '下'][$n]];
    return [1, (string)$w];
}

/** 로마 숫자 I~X 정도 → 정수(부 번호용). 실패 0 */
function mc_lib_roman_num($r) {
    $map = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10];
    return $map[strtoupper((string)$r)] ?? 0;
}

/** 시리즈 묶음 키: 소문자 + 공백·기호 제거 (같은 작품이 띄어쓰기/괄호만 달라도 한 시리즈로) */
function mc_lib_name_key($name) {
    $k = mc_lib_lower(mc_lib_nfc(trim((string)$name)));
    $k = preg_replace('/[\s\-_.,:;!?·~\'"`()\[\]{}<>【】「」『』〈〉《》（）［］・]+/u', '', $k);
    return (string)$k;
}

/**
 * ★ (2026-10-30) 작가 모름 표시 "N/A"·"n/a"·"N／A"·"N / A" — 작가를 '/' 로 나눌 때 "N"·"A" 두 작가로 쪼개지지 않게 하나로 묶었다가 "N/A" 로 되돌림
 */
function mc_lib_protect_na($s) {
    $r = preg_replace('/(?<![\p{L}\p{N}])n\s*[\/／]\s*a(?![\p{L}\p{N}])/iu', "N\u{E000}A", (string)$s);
    return $r === null ? (string)$s : $r;
}
function mc_lib_restore_na($s) {
    return str_replace("N\u{E000}A", 'N/A', (string)$s);
}

/**
 * ★ (2026-10-30) UTF-8 안전 양끝 정리 — PHP trim() 은 바이트 단위라 문자 목록의 '·'(C2 B7)를 0xC2·0xB7 두 바이트로 보고
 *   마지막 바이트가 0xB7 인 글자(シ·걷·긷 등)의 끝 바이트를 잘라 이름을 깨뜨렸음(화면에 빈칸·자동완성 전체 실패)
 */
function mc_lib_utrim($s, $dot = true) {
    $cls = $dot ? '\s\x00\-_.~·' : '\s\x00\-_~·';
    $r = preg_replace('/^[' . $cls . ']+|[' . $cls . ']+$/u', '', (string)$s);
    if ($r === null) $r = trim((string)$s, $dot ? " \t\n\r\0\x0B-_.~" : " \t\n\r\0\x0B-_~"); // 잘못된 UTF-8 이면 ASCII 만
    return $r;
}

/** 표시용 공백 정리 */
function mc_lib_clean($s) {
    $s = preg_replace('/\s+/u', ' ', (string)$s);
    return mc_lib_utrim($s, true);
}

/**
 * ★ (2026-10-29) 작품 이름·부제용 정리 — mc_lib_clean 과 같되 글자 바로 뒤의 마침표는 제목 일부로 남김
 * ("Gohoubi wa Karada de.", "Mr.", "Hajimete no...") — 띄어 쓴 점·숫자 뒤 점·앞쪽 점은 지움
 * (작가·태그·입력값 정리는 그대로 mc_lib_clean — 기존 작가 이름과 갈라지지 않게)
 */
function mc_lib_clean_title($s) {
    $s = preg_replace('/\s+/u', ' ', (string)$s);
    $t = mc_lib_utrim($s, false);
    for ($g = 0; $g < 4; $g++) {
        $u = preg_replace('/^[.\s\-_~·]+|(?<![\p{L}.])[.\s\-_~·]+$/u', '', $t);
        if ($u !== null) $u = mc_lib_utrim($u, false);
        if ($u === null || $u === $t) break;
        $t = $u;
    }
    return $t;
}

/**
 * 파일명(또는 폴더명) 분석
 * 예) "[佐藤秀峰] ブラックジャックによろしく 01"        → author=佐藤秀峰, series=ブラックジャックによろしく, vol=1(tail)
 *     "원피스 101권 (완).zip"                          → series=원피스, vol=101, completed
 *     "(C97) [서클 (작가)] 제목 [Korean] (1234567).zip"  → authors=[작가, 서클], groups=[서클], series=제목 (앞 행사·끝 꼬리표/갤러리 번호 제외)
 *     ★ (2026-11-02 01:53) authors 는 지금처럼 작가+그룹(서클) 모두 — 같은 제목 작품 구분(작가 겹침)에 그대로 쓰여 기존 묶음이 바뀌지 않게.
 *       groups 는 그중 그룹(서클) 이름만 — 서재에 '작가'와 '그룹'을 따로 표시할 때 씀(mc_lib_vol_credits)
 *     "제목 v03", "제목 #12", "제목 12화", "第3話", "Ch.5", "제목 1-10권", "01.zip"(→ 폴더명 사용)
 * ★ (2026-10-29) 끝 숫자만 있는 경우(vol_kind=tail)는 확정이 아님 → mc_lib_index_folder 에서 같은 폴더의 연번 파일과 비교해 결정
 * @return array [series, authors[], vol(float|null), label, completed(bool), vol_kind, tail_title]
 */
function mc_lib_parse_name($name, $is_file = true) {
    $s = mc_lib_nfc((string)$name);
    if ($is_file) $s = preg_replace('/\.[A-Za-z0-9]{1,5}$/', '', $s);
    // 전각 숫자·괄호·공백 → 반각
    $s = strtr($s, ['０'=>'0','１'=>'1','２'=>'2','３'=>'3','４'=>'4','５'=>'5','６'=>'6','７'=>'7','８'=>'8','９'=>'9',
                    '（'=>'(','）'=>')','［'=>'[','］'=>']','＃'=>'#','＿'=>' ','_'=>' ','　'=>' ','～'=>'~','〜'=>'~',
                    // ★ (2026-10-29) 전각 문장부호·붙는 띄어쓰기도 반각으로("Title！" = "Title!")
                    '！'=>'!','？'=>'?','：'=>':','；'=>';','，'=>',','．'=>'.','＆'=>'&','＋'=>'+','／'=>'/','＠'=>'@','－'=>'-',"\u{00A0}"=>' ',"\u{2009}"=>' ',"\u{200B}"=>'']); // ★ 밑줄은 띄어쓰기로(파일명에서 공백 대신 쓰는 경우가 많음)
    // ★ (2026-10-29) 규칙 3 — 화 번호 표기 통일
    //   第三話·第十二巻 같은 한자 숫자 → 第3話 / 로마자 숫자 "Dai Nana wa"·"Dai Juuni-wa" → "Dai 7 wa" / "Dai Roku. Roku wa" → "Dai 6 wa"
    $s = preg_replace_callback('/第([一二三四五六七八九十百〇零两]{1,6})(話|话|巻|卷|章|回|冊)/u', function ($m) { $n = mc_lib_kanji_num($m[1]); return $n === null ? $m[0] : '第' . $n . $m[2]; }, $s);
    $s = preg_replace('/\bDai\s+([A-Za-z]{2,14})\.\s+\1(\s*-?\s*wa\b)/iu', 'Dai $1$2', $s);
    $s = preg_replace_callback('/\bDai\s+([A-Za-z]{2,14})(?=(?:\s*-\s*(?:[IVX]{1,4}|\d))?\s*-?\s*wa\b)/iu', function ($m) { $n = mc_lib_romaji_num($m[1]); return $n === null ? $m[0] : 'Dai ' . $n; }, $s);
    // ★ (2026-10-29) 규칙 5 — "Sono Ni"(그 2) → "Sono 2", "その二"·"其の三" → "その2"
    $s = preg_replace_callback('/\bSono\s+([A-Za-z]{2,10})\b/iu', function ($m) { $n = mc_lib_romaji_num($m[1]); return $n === null ? $m[0] : 'Sono ' . $n; }, $s);
    $s = preg_replace_callback('/(その|其の)\s*([一二三四五六七八九十]{1,3})/u', function ($m) { $n = mc_lib_kanji_num($m[2]); return $n === null ? $m[0] : $m[1] . $n; }, $s);
    $orig = $s;
    $bracketInner = []; $byFound = false; $byKeys = [];
    // ★ (2026-11-02 02:08) 같은 이름은 대소문자·띄어쓰기가 달라도 하나로("GABURI"·"Gaburi" — 처음 나온 표기를 씀)
    $hasName = function (array $list, $a) { $k = mc_lib_term_key($a); foreach ($list as $x) if (mc_lib_term_key($x) === $k) return true; return false; }; // ★ (02:40) 용어와 같은 비교 키 // ★ (2026-11-02 02:04)
    $out = ['series' => '', 'authors' => [], 'groups' => [], 'vol' => null, 'label' => '', 'completed' => false, 'vol_kind' => '', 'tail_title' => '', 'subtitle' => '', 'core' => '', 'alt' => ''];

    // 완결 표시
    if (preg_match('/[\(\[【]\s*(완결|완|完結|完)\s*[\)\]】]|(?:^|\s)완결(?:\s|$)/u', $s)) {
        $out['completed'] = true;
        $s = preg_replace('/[\(\[【]\s*(완결|완|完結|完)\s*[\)\]】]/u', ' ', $s);
        $s = preg_replace('/(?:^|\s)완결(?=\s|$)/u', ' ', $s);
    }

    // 맨 앞 (행사명) — "(C97) [서클] 제목" 같은 동인지 표기. 숫자만 든 괄호는 남김
    // ★ (2026-10-29) 뒤에 띄어쓰기나 [작가] 괄호가 올 때만 — "(500)일의 썸머", "(주)회사원" 처럼 글자에 붙은 괄호는 제목 일부
    while (preg_match('/^\s*\(([^\)]{1,40})\)(?:\s+|(?=[\[【]))(?=\S)(.*)$/u', $s, $m) && !preg_match('/^\s*\d{1,4}\s*$/u', $m[1])) $s = $m[2];

    // 맨 앞 [작가] — 화질/스캔 같은 꼬리표는 작가로 보지 않음
    if (preg_match('/^\s*[\[【]([^\]】]{1,80})[\]】]\s*(.*)$/u', $s, $m) && trim($m[2]) !== '') { // 괄호만 있는 이름("[작품명].zip")은 작가로 보지 않음
        $cand = trim($m[1]);
        // ★ (2026-10-30) raw·png 등은 단어로만(앞뒤가 영문자가 아닐 때) — 'Strawberry' 같은 서클 이름이 작가로 안 잡히던 문제
        if ($cand !== '' && !preg_match('/고화질|저화질|화질|스캔|번역|완결|^완$|(?<![a-z])(?:raw|webp|jpe?g|png)(?![a-z])|(?<![a-z\d])\d{3,4}p(?![a-z])|^hq$|^hd$|^sd$|단행본|연재|^\d+$|^korean$|^english$|^japanese$|^chinese$|한국어|한글/iu', $cand)) {
            $names = []; $grpName = ''; $innerName = '';
            // "[서클 (작가)]" → 작가, 서클 / "[작가 (출판사)]" → 작가
            if (preg_match('/^(.*?)\s*\(([^\)]{1,60})\)\s*$/u', $cand, $cm)) {
                $inner = trim($cm[2]);
                $outer = trim($cm[1]);
                $publisher = preg_match('/on\s*web|comics?|코믹스|코믹|출판|문고|미디어|media|books?|press|magazine|매거진|서울문화사|대원|학산|북박스|집영사|集英社|講談社|小学館|白泉社|秋田書店|角川|kadokawa|square\s*enix|スクウェア|一迅社|竹書房|双葉社|新潮社|ジャンプ|マガジン|サンデー/iu', $inner);
                if (!$publisher && $inner !== '') $names[] = $inner;
                if ($outer !== '') $names[] = $outer;
                if (!$publisher && $inner !== '' && $outer !== '') { $grpName = $outer; $innerName = $inner; } // ★ (2026-11-02 01:53) 괄호 앞 = 그룹(서클)
            } else {
                $names[] = $cand;
            }
            foreach ($names as $nm) {
                foreach (preg_split('/\s*[,，、&×\/]\s*|\s+[·]\s+|\s+x\s+/u', mc_lib_protect_na($nm)) as $a) {
                    $a = mc_lib_restore_na(mc_lib_clean($a));
                    if ($a !== '' && mb_strlen($a, 'UTF-8') <= 40 && !$hasName($out['authors'], $a)) $out['authors'][] = $a;
                    if ($a !== '' && mb_strlen($a, 'UTF-8') <= 40 && $nm === $grpName && !$hasName($out['groups'], $a)) $out['groups'][] = $a;
                    if ($a !== '' && $innerName !== '' && $nm === $innerName) $bracketInner[] = $a; // ★ (2026-11-02 02:04) 괄호 안 작가 — 'by 작가'가 있으면 뺌(아래)
                }
            }
        }
        $s = $m[2];
    }

    // 끝 꼬리표 떼기: [Korean], [DL版], (1234567) 갤러리 번호 등 — 숫자만 든 짧은 괄호 "(03)" 은 권 후보로 남김
    $paren_num = null;
    // ★ (2026-10-29) 전편·후편·상·하 같은 나눔 표시(영어·로마자·일본어·한국어) — 1: 앞, 2: 가운데, 3: 뒤
    $partWords = 'Zenpen|Zempen|Chuuhen|Chuhen|Kouhen|Kohen|Joukan|Jokan|Chuukan|Gekan|前編|前篇|前编|中編|中篇|中编|後編|後篇|后编|后篇|上巻|上卷|中巻|中卷|下巻|下卷|上|中|下|전편|상편|중편|후편|하편|상권|중권|하권|상|하';
    $stripTail = function ($s) use (&$paren_num, $partWords) {
        while ($paren_num === null && preg_match('/^(.*\S)\s*[\(\[【]([^\)\]】]{0,60})[\)\]】]\s*$/u', $s, $m)) {
            $inside = trim($m[2]);
            // ★ (2026-10-29) 괄호 속 화·권·나눔 표시("(Ch. 2)", "[第3話]", "(前編)")는 지우지 않고 남겨 권 번호로 읽음
            if (preg_match('/^(?:(?:ch(?:apter)?|ep(?:isode)?|vol(?:ume)?|part|pt|sono)\.?\s*\d|#\s*\d|(?:第|제)?\s*\d{1,4}(?:\.\d)?\s*(?:話|话|巻|卷|화|권)$|\d{1,4}\s*[-~]\s*\d{1,4}\s*(?:話|话|巻|卷|화|권)$|(?:' . $partWords . ')$)/iu', $inside)) break; // ★ (2026-11-02 11:41) '(1-10권)'·'[6-10화]' 범위도 남김
            if (preg_match('/^(?:vol\.?\s*|v)?(\d{1,4}(?:\.\d)?)$/iu', $inside, $pm)) $paren_num = $pm[1];
            $s = $m[1];
        }
        return $s;
    };
    $s = $stripTail($s);

    // ★ (2026-11-02 01:53) "by 작가" 의 작가로 볼지 — 예전엔 숫자가 하나라도 있으면 아님("by Blvefo9" 놓침)
    //   → 숫자로 시작하는 낱말("Stand by Me 3", "by Me 3권")이나 권·화 표시가 있을 때만 아님
    // ★ (2026-11-02 02:34) 숫자로 '시작'하는 이름도 작가("by 4why") — 낱말이 숫자뿐이거나("Me 3") 숫자+권·화("3권")·vol·ch 일 때만 아님
    //   (예전 02:00 규칙은 숫자로 시작하는 낱말을 모두 막아 "4why" 를 놓쳤고, 이름 속 '화'·'권' 글자("이화")도 막았음)
    //   ★ (2026-11-02 03:04) 숫자가 붙은 권·화 표시("Author v03", "Part2", "Ch5", "Vol2", "2nd")도 아님 — 02:34 규칙이 이걸 작가 이름으로 받아 권 번호가 사라졌음
    $byOk = function ($x) { return !preg_match('/(?<![\p{L}\p{N}])\d+(?:\.\d+)?(?:\s*(?:권|화|巻|卷|話|话|冊))?(?![\p{L}\p{N}])|\d\s*(?:권|화|巻|卷|話|话|冊)|\b(?:vol|ch)\b|(?<![\p{L}\p{N}])(?:v|vol|volume|ch|chap|chapter|ep|episode|part|pt|act|sono|no|#)\.?\d|(?<![\p{L}\p{N}])\d+(?:st|nd|rd|th)(?![\p{L}\p{N}])/iu', (string)$x); };
    // ★ (2026-11-02 11:41) 'Stand by Me'·'Side by Side'·'Step by Step' 처럼 by 가 제목 속 말인 경우는 작가로 안 봄(앞 낱말과 같은 말이거나 흔한 숙어)
    $byIdiom = function ($pre, $x) {
        $x = mb_strtolower(trim((string)$x), 'UTF-8');
        if (preg_match('/^(?:me|you|us|him|her|them|myself|yourself|side|step|day|night|one|little|hand|heart|chance|default|design|far|now|then|bit)$/u', $x)) return true;
        $w = preg_split('/\s+/u', trim((string)$pre));
        return $w && mb_strtolower((string)end($w), 'UTF-8') === $x;
    };
    $addBy = function ($x) use (&$out, &$byFound, &$byKeys, $hasName) {
        $byFound = true;
        foreach (preg_split('/\s*[,，、&×\/]\s*|\s+and\s+|\s+x\s+/iu', mc_lib_protect_na(trim((string)$x))) as $a) {
            $a = mc_lib_restore_na(mc_lib_clean($a));
            if ($a !== '') $byKeys[mc_lib_term_key($a)] = true; // ★ (03:04) 중복 판정($hasName)과 같은 키
            if ($a !== '' && mb_strlen($a, 'UTF-8') <= 40 && !$hasName($out['authors'], $a)) $out['authors'][] = $a;
        }
    };
    // ★ (2026-10-29) 두 제목 "원제 | 번역제"(e-hentai·히토미 표기) — 앞 제목(원제)으로 묶고 뒤 제목은 alt 로
    //   ★ 세로줄 모양 문자도(│ U+2502 상자선·┃·∣·¦·︱·￨ — 히토미 제목에서 실제로 쓰임). ASCII '|' 는 앞뒤 띄어쓰기가 있을 때만
    //   ★ 번역제 끝의 "by 작가" 도 작가로("원제 ~부제~ │ 번역제 by C-kyuu")
    //   ★ (2026-11-02 01:53) 한글 자모 'ㅣ'(U+3163)를 세로줄 대신 쓴 경우도 — 앞이나 뒤가 띄어 쓰였을 때만(글자 속 자모와 헷갈리지 않게)
    if (preg_match('/^(.*?\S)\s+\|\s+(.+)$/u', $s, $am) || preg_match('/^(.*?\S)\s*[｜│┃∣¦︱︳￨]\s*(\S.*)$/u', $s, $am)
        || preg_match('/^(.*?\S)(?:\s+ㅣ\s*|ㅣ\s+)(\S.*)$/u', $s, $am)) {
        $alt = $am[2];
        if (preg_match('/^(?:(.*\S)\s+)?by\s+(\S.{0,60})$/iu', $alt, $bm) && $byOk($bm[2]) && !$byIdiom($bm[1] ?? '', $bm[2])) { // ★ 뒤 제목 없이 "｜ by 작가" 만 있어도
            $addBy($bm[2]);
            $alt = (string)$bm[1];
        }
        // 뒤 제목 끝·가운데의 [KOREAN] 같은 꼬리표 정리(표시용)
        for ($g = 0; $g < 4; $g++) { $t = preg_replace('/\s*[\(\[【][^\)\]】]{0,40}[\)\]】]\s*$/u', '', $alt); if ($t === null || $t === $alt) break; $alt = $t; }
        $out['alt'] = mc_lib_clean_title($alt);
        $s = $stripTail($am[1]);
    }

    // ★ (2026-10-29) "제목 by 작가" — by 뒤를 작가로 (숫자가 들어 있으면 권 번호일 수 있어 작가로 보지 않음: "Stand by Me 3권")
    if (preg_match('/^(.*\S)\s+by\s+(\S.{0,60})$/iu', $s, $bm) && $byOk($bm[2]) && !$byIdiom($bm[1], $bm[2])) {
        $addBy($bm[2]);
        $s = $stripTail($bm[1]); // "7 Days 2 (decensored) by 작가" → "7 Days 2"
    }
    // ★ (2026-11-02 02:04) 'by 작가'가 있으면 그게 작가 — "[그룹 (작가)]" 괄호 안 이름은 뺌(같은 사람을 일본어·로마자로 두 번 쓴 경우가 많음: "[GABUGABU (ガブリ)] … by Gaburi")
    //   by 와 같은 이름이면 그대로 둠 / by 가 없으면 지금처럼 괄호 안이 작가
    if ($byFound && $bracketInner) {
        $out['authors'] = array_values(array_filter($out['authors'], function ($a) use ($bracketInner, $byKeys) { return !in_array($a, $bracketInner, true) || isset($byKeys[mc_lib_term_key($a)]); }));
    }
    // ★ (2026-10-29) 규칙 5 — 괄호 없이 끝에 붙은 판 표시(무수정·풀컬러·완전판 등)는 같은 책의 다른 판 → 지움 ("Title Decensored" = "Title")
    for ($g = 0; $g < 4; $g++) {
        $t = preg_replace('/^(.*\S)\s+(?:\+\s*(?:omake|bonus|おまけ)|decensored|uncensored|censored|digital|colori[sz]ed|full\s*colou?r|textless|kanzenban|無修正|修正版|DL版|カラー版|フルカラー|完全版|무수정|무검열|풀\s*컬러|컬러판|완전판)$/iu', '$1', $s);
        if ($t === null || $t === $s) break;
        $s = $stripTail($t);
    }
    // ★ 끝의 번외·외전·총집편 표시 → 같은 작품의 따로 된 권(부제). "Title Omake" = Title 의 Omake
    $extra = '';
    if (preg_match('/^(.*\S)\s+(Omake|おまけ|オマケ|Extra|Gaiden|外伝|Bangaihen|番外編|番外篇|외전|번외편|번외|특별편|Kanketsuhen|完結編|Soushuuhen|総集編|총집편)$/iu', $s, $xm)
        || preg_match('/^(.*\S)\s+(EX)$/u', $s, $xm)) { $s = $stripTail($xm[1]); $extra = $xm[2]; }
    // ★ e-hentai·히토미 부제 표기 "제목 ~부제~", "제목 -부제-" → 제목 / 부제 ("Title 2 ~Sub~" → Title 2권, 부제 Sub)
    //   앞이 띄어 쓰이고 부제가 숫자만이 아닐 때만("Ah~ 제목", "9-Ji Kara" 같은 이름 안 기호는 그대로)
    $tsub = '';
    if (preg_match('/^(.*?\S)\s+~\s*([^~]*?[^\s~])\s*~\s*(.*)$/u', $s, $tm) || preg_match('/^(.*?\S)\s+~\s*([^~]*[^\s~])\s*()$/u', $s, $tm)
        || preg_match('/^(.*?\S)\s+-(?=[^\s\-])([^\-]*[^\s\-])-(?=\s|$)\s*(.*)$/u', $s, $tm)) {
        // 물결표 안이 번호·나눔 표시면("~Kouhen~", "-Zenpen-", "~Ch. 2~") 부제가 아니라 권 표시 → 이름에 되돌림
        $isMark = preg_match('/^(?:' . $partWords . ')$|^(?:part|pt|sono|act|ch(?:apter)?|ep(?:isode)?|vol(?:ume)?)\.?\s*\d|^(?:第|제)?\s*\d{1,4}\s*(?:話|话|巻|卷|화|권)$|^(?:その|其の)\s*\d/iu', trim($tm[2]));
        if ($isMark) $s = $stripTail(mc_lib_clean_title($tm[1] . ' ' . trim($tm[2]) . ' ' . $tm[3]));
        elseif (!preg_match('/^[\d\s.]+$/u', $tm[2])) { $s = $stripTail(mc_lib_clean_title($tm[1] . ' ' . $tm[3])); $tsub = mc_lib_clean_title($tm[2]); }
    }
    $core = $s; // 권 표시를 찾기 전 이름(폴더 비교에서 "제목 01 부제" 판단용)

    // 권수 — 권 표시(권·vol·화·ch·#)는 처음 나온 것, 끝 숫자는 맨 끝(★ 2026-10-29: 권 표시 뒤 글자는 부제로 분리)
    $vol = null; $label = ''; $hit = ''; $kind = ''; $hitPos = -1;
    $pats = [
        ['/(\d{1,4})\s*[-~]\s*(\d{1,4})\s*(권|巻|卷)/u', 'range'],
        ['/(?:제|第)?\s*(\d{1,4}(?:\.\d)?)\s*(권|巻|卷|冊)/u', 'vol'],
        ['/(?:^|[\s_\-\(\[])(?:vol(?:ume)?|v)\.?\s*(\d{1,4}(?:\.\d)?)/iu', 'v'],
        // ★ 일본어 로마자 화 표기 "Dai 11 wa", "Dai 13-2 wa", "Dai 7 - II-wa", "5.5 wa" (wa = 話)
        ['/(?:^|[\s\-_\.~])Dai\s*(\d{1,4}(?:\.\d)?)(?:\s*-\s*([IVX]{1,4}|\d))?\s*-?\s*wa(?=$|[\s\-~:,.!?\)\]|])/iu', 'jwa'],
        // Dai 없이 "숫자 wa" 는 뒤가 끝이거나 구분 기호(- ~ : |)일 때만 — "Ore wa 2 wa Tensai" 같은 조사는 제외
        ['/(?:^|[\s\-_\.~])(\d{1,4}(?:\.\d)?)(?:\s*-\s*([IVX]{1,4}|\d))?\s*-?\s*wa(?=\s*(?:$|[\-~:|]))/iu', 'jwa'],
        // ★ (2026-10-29) "1-3화"(범위) / "13-2화"(13화 2번째) — 뒤 숫자가 크면 범위, 작으면 나눔
        ['/(?:제|第)?\s*(\d{1,4})\s*[-~]\s*(\d{1,4})\s*(화|話|话)/u', 'chr'],
        ['/(?:제|第)?\s*(\d{1,4}(?:\.\d)?)\s*(화|話|话)/u', 'ch'],
        ['/(?:^|[\s_\-\(\[])(?:ch(?:apter)?|ep(?:isode)?)\.?\s*(\d{1,4}(?:\.\d)?)(?:(?:[-~]|\s+[-~]\s*(?=(?:ch\.?\s*)?\d{1,4}\s*$))(?:ch\.?\s*)?(\d{1,4})(?![\d.]))?/iu', 'chx'], // 범위: 붙여 쓴 "1-3" 또는 띄어 쓴 대시 뒤 번호가 끝일 때만("Ch. 5 - 100 Days" 는 5화 + 부제)
        ['/#\s*(\d{1,4})/u', 'hash'],
        // ★ "Part 2", "Pt. 3", "Sono 2", "その2", "Act.2" 같은 편 표시 (Lesson·Stage·Story 처럼 제목에 흔한 말은 넣지 않음 — "Love Stage 2")
        ['/(?:^|[\s\-\(\[])(Part|Pt|Sono|Act(?=\.))\.?\s*(\d{1,3}(?:\.\d)?)(?![\d\p{L}])/iu', 'partn'],
        ['/(その|其の)\s*(\d{1,3})(?!\d)/u', 'partn'],
        // ★ 끝의 전편·후편·상·하 ("Title Kouhen", "タイトル 前編", "제목 하", "Title 3 Zenpen"(3권 전편))
        ['/(?:^|[\s\(\[・])(?:(\d{1,4})\s+)?(' . $partWords . ')[\)\]]?\s*$/iu', 'part'], // ★ (2026-11-02 11:41) 대소문자 무시(zenpen·ZENPEN)
        // ★ 속편 표시 — 끝 숫자처럼 같은 제목 권이 있을 때만 권으로 인정(tail): "7 Days II", "Title 2nd", "Zoku Title"·"Title Zoku"(続)
        ['/\s(II|III|IV|VI|VII|VIII|IX)$/u', 'roman'],
        ['/\s(\d{1,2})(?:st|nd|rd|th)$/iu', 'ord'],
        ['/^(?:Zoku|続)(?:\s+|・)|\s+Zoku$/iu', 'zoku'],
        // ★ 끝 번호 범위 "Title 1-3" (뒤가 클 때만, 연도 같은 네 자리 제외)
        ['/(?:^|[\s_\.])(\d{1,3})\s*[-~]\s*(\d{1,3})\s*$/u', 'tailr'],
        // 끝 숫자 — ★ 한글·한자·가나에 붙은 숫자("기생수2", "ぼくの彼女2")도 (시즌2·第2·제2·その2 는 제외)
        ['/(?:^|[\s_\-\.]|(?<=[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}ー])(?<!시즌|第|제|の))(\d{1,4}(?:\.\d)?)\s*$/u', 'tail'],
        ['/^\s*(\d{1,4})\s*$/u', 'only'],
    ];
    foreach ($pats as $p) {
        if (preg_match_all($p[0], $s, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            $mo = in_array($p[1], ['tail', 'only'], true) ? end($mm) : $mm[0];
            $m = array_map(function ($x) { return $x[0]; }, $mo);
            if ($p[1] === 'tailr' && (int)$m[2] <= (int)$m[1]) continue; // "Title 13-2" 는 범위가 아님 → 아래 끝 숫자 규칙으로
            $hitPos = $mo[0][1];
            $hit = $m[0];
            $kind = $p[1];
            if ($p[1] === 'range') { $vol = (float)$m[1]; $label = (int)$m[1] . '-' . (int)$m[2] . $m[3]; }
            elseif ($p[1] === 'vol' || $p[1] === 'ch') { $vol = (float)$m[1]; $label = $m[1] . $m[2]; }
            elseif ($p[1] === 'chr') {
                $a = (int)$m[1]; $b = (int)$m[2];
                $vol = $b > $a ? (float)$a : (float)$a + ($b > 0 && $b < 10 ? $b / 10 : 0);
                $label = $a . '-' . $b . $m[3]; $kind = 'ch';
            }
            elseif ($p[1] === 'chx') {
                $vol = (float)$m[1]; $label = 'ch' . $m[1];
                if (isset($m[2]) && $m[2] !== '') {
                    $b = (int)$m[2];
                    if ($b > $vol) $label .= '-' . $b;                                                         // "Ch. 1-3" 범위
                    elseif ($b > 0 && $b < 10 && floor($vol) == $vol) { $vol += $b / 10; $label .= '-' . $b; } // "Ch. 13-2" 13화 2번째
                }
            }
            elseif ($p[1] === 'partn') {
                $w = ucfirst(strtolower($m[1]));
                if ($w === 'Pt' || $w === 'Sono' || $m[1] === 'その' || $m[1] === '其の') $w = 'Part';
                $vol = (float)$m[2]; $label = $w . ' ' . ($m[2] + 0); $kind = 'part';
            }
            elseif ($p[1] === 'part') {
                $pi = mc_lib_part_info($m[2]);
                if (isset($m[1]) && $m[1] !== '') { $vol = (float)$m[1] + $pi[0] / 10; $label = ((int)$m[1]) . ' ' . $pi[1]; }
                else { $vol = $pi[0] / 10; $label = $pi[1]; } // 단독 전편·후편은 0.1~0.3 — 같은 작품의 진짜 1권·3권과 겹치지 않고, 속편 "제목 2" 보다 앞
            }
            elseif ($p[1] === 'roman') { $vol = (float)mc_lib_roman_num($m[1]); $label = (string)(int)$vol; $kind = 'tail'; }
            elseif ($p[1] === 'ord') { $vol = (float)$m[1]; $label = (string)(int)$m[1]; $kind = 'tail'; }
            elseif ($p[1] === 'zoku') { $vol = 2.0; $label = '2'; $kind = 'tail'; }
            elseif ($p[1] === 'tailr') { $vol = (float)$m[1]; $label = (int)$m[1] . '-' . (int)$m[2]; $kind = 'tail'; }
            elseif ($p[1] === 'jwa') {
                $part = isset($m[2]) && $m[2] !== '' ? (ctype_digit($m[2]) ? (int)$m[2] : mc_lib_roman_num($m[2])) : 0;
                $vol = (float)$m[1] + ($part > 0 && $part < 10 ? $part / 10 : 0);
                $label = ((float)$m[1] + 0) . ($part > 0 ? '-' . $part : '') . '話';
            }
            else { $vol = (float)$m[1]; $label = ($p[1] === 'v' ? 'v' : '') . $m[1]; }
            break;
        }
    }
    // ★ (2026-11-06 01:15) 영어 제목 끝에 붙은 숫자 "Tsuma no Imouto2 ｜ 아내의 여동생 2" (펜닐 제보: 2권만 다른 작품으로 빠졌음)
    //   로마자에 붙은 숫자는 이름의 일부일 때가 많아("Persona5"·"PS5") 원래 권으로 보지 않음 → 같은 파일 이름의 다른 제목(｜ 뒤)이
    //   같은 번호를 끝 숫자·권 표시로 적고 있을 때만 권 번호로(두 제목이 같은 번호를 말하는 경우)
    if ($vol === null && $out['alt'] !== '' && preg_match('/^(.*\p{Latin}{2})(\d{1,3})$/u', $s, $gm) && (int)$gm[2] > 0
        && preg_match('/(?:^|\s|(?<=[\p{Hangul}\p{Han}\p{Hiragana}\p{Katakana}]))(?:제|第)?0*(\d{1,3})\s*(?:권|巻|卷|화|話)?$/u', $out['alt'], $gam)
        && (int)$gam[1] === (int)$gm[2]) {
        $vol = (float)(int)$gm[2]; $label = (string)(int)$gm[2]; $kind = 'tail'; $hit = $gm[2]; $hitPos = strlen($gm[1]);
    }
    // 권 표시가 없고 끝에 "(03)" 같은 숫자 괄호만 있으면 그것을 끝 숫자로
    $before = $s;
    if ($vol === null && $paren_num !== null) { $vol = (float)$paren_num; $label = $paren_num; $kind = 'tail'; $hit = ''; } // 권으로 인정 안 되면 제목은 괄호 숫자 없이(갤러리 번호 등)
    $post = '';
    if ($hit !== '' && $hitPos >= 0) {
        $pre = substr($s, 0, $hitPos);
        $post = substr($s, $hitPos + strlen($hit));
        // 권 표시 앞에 제목이 있으면 뒤쪽은 부제("원피스 3권 부제" → 원피스 / 부제), 앞이 비면 뒤쪽이 제목("3권 제목")
        if (mc_lib_clean(preg_replace('/[\(\)\[\]【】]/u', ' ', $pre)) !== '') { $s = $pre; }
        else { $s = $post; $post = ''; }
    }
    // ★ (2026-11-02 11:41) 여는 괄호 뒤의 나눔 표시도 — '題名 1巻 (上)'·'제목 3권 (하)'·'Title v2 (Part 2)' 가 같은 권 사본으로 묶이던 것
    // ★ (2026-10-29) 번호 바로 뒤의 나눔 표시 "Title 3 Kouhen"·"Ch. 4 Part 2"·"第3話 前編" → 같은 번호 안의 순서(3.3 / 4.2) — 사본으로 겹치지 않게
    if ($post !== '' && $vol !== null && floor($vol) == $vol && in_array($kind, ['vol', 'v', 'ch', 'chx', 'hash', 'jwa'], true)) {
        if (preg_match('/^[\s\-~:\)\]】\(\[【]*(?:(?:Part|Pt|Sono)\.?\s*(\d)|(?:その|其の)\s*(\d))(?![\d\p{L}])(.*)$/iu', $post, $sm)) {
            $n = (int)($sm[1] !== '' ? $sm[1] : $sm[2]);
            if ($n > 0) { $vol += $n / 10; $label .= ' Part ' . $n; $post = $sm[3]; }
            // ★ (2026-11-02 12:04) '(Part 1 of 2)' 처럼 괄호 안에 남은 말은 부제로 남기지 않음
            if (preg_match('/^\s*(?:of\s*\d+)?\s*[\)\]】](.*)$/iu', $post, $cm)) $post = $cm[1];
        } elseif (preg_match('/^[\s\-~:\)\]】\(\[【]*(' . $partWords . ')(?![\p{L}\p{N}])(.*)$/iu', $post, $sm)) { // ★ (2026-11-02 12:04) 대소문자 무시
            $pi = mc_lib_part_info($sm[1]);
            $vol += $pi[0] / 10; $label .= ' ' . $pi[1]; $post = $sm[2];
        }
    }
    // 괄호 속 화 번호를 읽고 남은 짝 없는 괄호 정리("Title (Ch. 2)" → "Title " / ")")
    $trimBr = function ($x) { $y = preg_replace('/^[\s\)\]】]+|[\s\(\[【]+$/u', '', (string)$x); return $y === null ? (string)$x : $y; };
    $s = $trimBr($s); $post = $trimBr($post);
    $out['vol'] = $vol;
    $out['label'] = $label;
    $out['vol_kind'] = $kind;

    // 남은 괄호(화질·스캔 정보 등) 제거 → 시리즈명
    // ★ (2026-10-29) 띄어 쓴 괄호(화질·언어·원작 표시 등)만 지움 — 앞이나 뒤가 글자에 붙은 괄호("(500)일의", "(주)회사원", "A(B)C")는 제목 일부로 유지 (이름 끝 괄호는 위의 끝 꼬리표 단계에서 처리)
    $strip = function ($x) {
        $y = preg_replace('/(?<![\p{L}\p{N}])[\(\[【][^\)\]】]*[\)\]】](?![\p{L}\p{N}])/u', ' ', $x);
        return mc_lib_clean_title($y === null ? $x : $y);
    };
    $series = $strip($s);
    // 권 번호 없이 괄호만 있던 이름("[제목].zip")은 괄호 안 내용 사용 — 권 번호만 있으면('01.zip', '[작가] 3권') 비워 두고 폴더명 사용
    if ($series === '' && $vol === null) $series = mc_lib_clean_title(preg_replace('/[\(\)\[\]【】]/u', ' ', $orig));
    $out['series'] = $series;
    $out['subtitle'] = mc_lib_clean_title(implode(' ', array_filter([$post !== '' ? $strip($post) : '', $tsub !== '' ? $strip($tsub) : '', $extra], 'strlen')));
    $out['core'] = $strip($core);
    // 끝 숫자를 권으로 인정하지 않을 때 쓸 제목(숫자 포함) — 예: "건담 0079"
    if ($kind === 'tail') $out['tail_title'] = $strip($before);
    return $out;
}

// ============================================================
// 경로 · 권한
// ============================================================

/** 상대경로 정규화: "/a/b.zip" 형태, 상위 이동 차단. 이상하면 '' */
function mc_lib_norm_path($p) {
    $p = str_replace('\\', '/', (string)$p);
    $p = '/' . trim(preg_replace('#/+#', '/', $p), '/');
    if ($p === '/') return '';
    foreach (explode('/', trim($p, '/')) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') return '';
    }
    return $p;
}

function mc_lib_base($bidx) {
    global $base_dirs;
    $b = $base_dirs[(int)$bidx] ?? null;
    return $b ? rtrim(str_replace('\\', '/', $b), '/') : null;
}

/** 실제 경로가 base 안에 있는지(심볼릭 링크 포함 검사) */
function mc_lib_inside_base($bidx, $rel) {
    $base = mc_lib_base($bidx);
    if (!$base) return false;
    $rb = realpath($base);
    $rp = realpath($base . $rel);
    if ($rb === false || $rp === false) return false;
    $rb = rtrim(str_replace('\\', '/', $rb), '/') . '/';
    $rp = str_replace('\\', '/', $rp);
    return strpos($rp . '/', $rb) === 0;
}

/**
 * 현재 사용자 그룹이 이 경로를 볼 수 있는지 — 기존 규칙 그대로
 * ① 최상위 폴더의 {base}/{root}.json 이 있으면 그룹 값이 1 이어야 함(viewer/index 의 dir_check 와 동일)
 * ② src/folder_permissions.json 에 최상위 폴더가 있으면 그룹 값이 1 이어야 함(index 목록 숨김과 동일)
 */
function mc_lib_can_access($bidx, $rel) {
    static $perm = null;
    $rel = mc_lib_norm_path($rel);
    if ($rel === '') return false;
    $group = $_SESSION['user_group'] ?? '';
    $root = explode('/', trim($rel, '/'))[0];
    $base = mc_lib_base($bidx);
    if (!$base) return false;
    // ★ (2026-11-02 09:58) 서재는 DB 기록이라 디스크에서 최상위 폴더가 사라져도(NAS 끊김·이름 바꿈) 작품이 보임 — 그때 mc_path_allowed 는 '폴더 없음 = 허용'이라
    //   막힌 폴더 작품 이름·경로가 다른 그룹에 보였음 → 폴더가 없으면 저장된 폴더 권한으로 판단(기록 없으면 관리자만)
    // ★ (2026-11-02 10:28) 최상위 폴더 안의 권인지(mc_lib_norm_path 는 늘 '/' 로 시작해 전에는 최상위에 바로 놓인 파일 작품까지 '폴더 없음'으로 보고 관리자만 보였음)
    $nested = strpos(trim($rel, '/'), '/') !== false;
    static $memo = [];
    $mk = (int)$bidx . '|' . $group . '|' . $root;
    if ($nested && isset($memo[$mk])) return $memo[$mk];
    if ($nested && !is_dir(rtrim($base, '/\\') . '/' . $root)) {
        static $lib_perm = null;
        if ($lib_perm === null) {
            $lib_perm = [];
            $pf = __DIR__ . '/src/folder_permissions.json';
            $raw = (is_file($pf) && function_exists('load_permissions')) ? (load_permissions($pf) ?: []) : [];
            if (is_array($raw)) foreach ($raw as $k => $v) $lib_perm[mb_strtolower((string)$k, 'UTF-8')] = $v;
        }
        $rk = mb_strtolower(rtrim($root, '. '), 'UTF-8');
        // ★ (2026-11-04 09:40) 콘텐츠 폴더 바로 아래 옛 권한 파일({root}.json)도 봄(mc_path_allowed 와 같은 판정) — 폴더가 사라지면 이 파일로만 막힌 그룹에 작품이 보였음
        $_old_pf = rtrim($base, '/\\') . '/' . rtrim($root, '. ') . '.json';
        if (is_file($_old_pf)) {
            $_old_raw = (string)@file_get_contents($_old_pf);
            $_old_arr = json_decode($_old_raw, true);
            $_is_perm = is_array($_old_arr) && (isset($_old_arr['admin']) || isset($_old_arr['group1']) || isset($_old_arr['group2']) || isset($_old_arr['group3']) || isset($_old_arr['group4']));
            if ($_is_perm && (($_old_arr[$group] ?? null) !== 1)) return $memo[$mk] = false;
            if (!is_array($_old_arr) && trim($_old_raw) !== '') return $memo[$mk] = false; // 깨진 권한 파일은 막음
        }
        if (isset($lib_perm[$rk]) && is_array($lib_perm[$rk])) return $memo[$mk] = (($lib_perm[$rk][$group] ?? 0) === 1);
        return $memo[$mk] = ($group === 'admin');
    }
    // ★ (2026-10-30) 화면·뷰어와 같은 판단(function.php mc_path_allowed — 대소문자 무시·옛 권한 파일 판별)
    //   ★ (2026-11-02 10:08) 권한은 최상위 폴더 단위라 (콘텐츠 폴더·최상위 폴더·그룹)마다 한 번만 확인(권이 수천 개인 작품 화면에서 NAS 를 권마다 다시 읽던 것)
    if (function_exists('mc_path_allowed')) {
        if (!$nested) return mc_path_allowed('/' . $rel, $base);
        return $memo[$mk] = mc_path_allowed('/' . $rel, $base);
    }
    $modefile = $base . '/' . $root . '.json';
    if (is_file($modefile)) {
        $arr = json_decode((string)@file_get_contents($modefile), true);
        if (!is_array($arr) || (($arr[$group] ?? null) !== 1)) return false;
    }
    if ($perm === null) {
        $pf = __DIR__ . '/src/folder_permissions.json';
        $perm = (is_file($pf) && function_exists('load_permissions')) ? (load_permissions($pf) ?: []) : [];
        if (!is_array($perm)) $perm = [];
    }
    if (isset($perm[$root]) && is_array($perm[$root]) && (($perm[$root][$group] ?? 0) !== 1)) return false;
    return true;
}

// ============================================================
// 스캔(인덱싱)
// ============================================================

/** 시리즈 찾기/만들기 */
function mc_lib_series_id(PDO $db, $bidx, $name, $now) {
    $key = mc_lib_name_key($name);
    if ($key === '') return null;
    $st = $db->prepare('SELECT series_id FROM series_alias WHERE bidx=? AND name_key=?');
    $st->execute([(int)$bidx, $key]);
    $aid = $st->fetchColumn();
    if ($aid) return (int)$aid;
    $st = $db->prepare('SELECT id, name, manual FROM series WHERE bidx=? AND name_key=?');
    $st->execute([(int)$bidx, $key]);
    $row = $st->fetch();
    if ($row) {
        // ★ (2026-10-29) 인식 규칙 2(밑줄 → 띄어쓰기) 이전에 만들어진 자동 이름 정리 — 관리자가 이름을 정한 작품(manual 1)은 그대로
        if (!((int)$row['manual'] & 1) && strpos((string)$row['name'], '_') !== false && strpos((string)$name, '_') === false && $name !== $row['name']) {
            $db->prepare('UPDATE series SET name=?, updated_at=? WHERE id=?')->execute([$name, $now, (int)$row['id']]);
        }
        return (int)$row['id'];
    }
    $st = $db->prepare('INSERT INTO series(bidx, name, name_key, created_at, updated_at) VALUES(?,?,?,?,?)');
    $st->execute([(int)$bidx, $name, $key, $now, $now]);
    return (int)$db->lastInsertId();
}

/**
 * ★ (2026-10-29) 시리즈의 (다른) 파일들에서 다시 뽑은 작가 — 파일명·폴더명의 [작가]/by 작가 + ComicInfo 작가
 * @return array 작가 이름 키 => 이름
 */
function mc_lib_vol_authors(PDO $db, $sid, $excludePath = null, $limit = 300) {
    // ★ (2026-10-30) $limit=0 이면 모든 권(자동 작가 다시 계산용 — 300개 넘는 작품에서 뒤쪽 권에만 있는 작가를 잘못 떼지 않게)
    //   같은 요청 안에서 같은 이름은 한 번만 분석(스캔 중 여러 번 불려도 가볍게)
    static $memo = [];
    $st = $db->prepare('SELECT path, folder, filename, info FROM volumes WHERE series_id=? AND missing=0' . ($limit > 0 ? ' LIMIT ' . (int)$limit : ''));
    $st->execute([(int)$sid]);
    $out = [];
    $pa = function ($name, $isFile) use (&$memo) {
        $k = ($isFile ? 'f' : 'd') . $name;
        if (!isset($memo[$k])) { if (count($memo) > 50000) $memo = []; $memo[$k] = mc_lib_parse_name($name, $isFile)['authors']; }
        return $memo[$k];
    };
    foreach ($st->fetchAll() as $r) {
        if ($excludePath !== null && $r['path'] === $excludePath) continue;
        $a = $pa($r['filename'], true);
        if (!$a && $r['folder'] !== '') $a = $pa(basename($r['folder']), false);
        if ($r['info'] !== '') { $ci = json_decode($r['info'], true); if (!empty($ci['authors'])) $a = array_merge($a, (array)$ci['authors']); }
        foreach ($a as $n) { $k = mc_lib_name_key($n); if ($k !== '') $out[$k] = $n; }
    }
    return $out;
}

/**
 * ★ (2026-10-29) 같은 제목·다른 작가 구분 — 권 번호 없는 단편(동인지 등)만:
 * 이 파일의 작가가 같은 이름 작품의 다른 파일 작가와 하나도 겹치지 않으면 다른 작품("이름@작가" 키)으로.
 * 권 번호가 있는 연재물(앞 [ ] 가 스캔팀인 경우가 많음)·관리자가 작가를 정한 작품·작가 정보가 없는 쪽은 지금처럼 하나로
 */
function mc_lib_series_id_for(PDO $db, $bidx, $name, $now, array $authors, $hasVol, $path) {
    // ★ 같은 제목이 작가별로 나뉘어 있으면("키@작가") 권 번호가 있어도 작가가 겹치는 쪽으로
    if ($authors) {
        $key0 = mc_lib_name_key($name);
        $chk = $db->prepare('SELECT name FROM series WHERE bidx=? AND name_key >= ? AND name_key < ?');
        $chk->execute([(int)$bidx, $key0 . '@', $key0 . "@\u{10FFFF}"]);
        $isSplit = false;
        foreach ($chk->fetchAll(PDO::FETCH_COLUMN) as $nm) if (mc_lib_name_key($nm) === $key0) { $isSplit = true; break; } // 제목에 '@' 가 든 다른 작품은 제외
        if ($isSplit) { $hit = mc_lib_series_by_author($db, $bidx, $key0, $authors, $path); if ($hit) return $hit; }
    }
    $sid = mc_lib_series_id($db, $bidx, $name, $now);
    if (!$sid || $hasVol || !$authors) return $sid;
    $st = $db->prepare('SELECT manual FROM series WHERE id=?');
    $st->execute([(int)$sid]);
    if (((int)$st->fetchColumn()) & 2) return $sid;
    $st = $db->prepare('SELECT 1 FROM volumes WHERE series_id=? AND missing=0 AND vol_no IS NOT NULL LIMIT 1');
    $st->execute([(int)$sid]);
    if ($st->fetchColumn()) return $sid;
    $others = mc_lib_vol_authors($db, $sid, $path);
    if (!$others) return $sid;
    $mine = [];
    foreach ($authors as $a) { $k = mc_lib_name_key($a); if ($k !== '') $mine[$k] = true; }
    if (!$mine) return $sid; // ★ (2026-11-02 11:42) 작가 이름이 기호뿐('[???]')이면 키가 비어 경고·'이름@' 작품으로 갈라지던 것
    if (array_intersect_key($mine, $others)) return $sid;
    // 같은 이름으로 이미 나뉜 작품("이름@…") 중 작가가 겹치는 곳이 있으면 그쪽으로(처리 순서와 무관하게 같은 결과)
    $key = mc_lib_name_key($name);
    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key) . '@%';
    $st = $db->prepare("SELECT id, name_key FROM series WHERE bidx=? AND name_key LIKE ? ESCAPE '\\' ORDER BY id");
    $st->execute([(int)$bidx, $like]);
    $used = [];
    foreach ($st->fetchAll() as $r) {
        $used[$r['name_key']] = true;
        $oa = mc_lib_vol_authors($db, (int)$r['id'], $path);
        if ($oa) { if (array_intersect_key($mine, $oa)) return (int)$r['id']; continue; }
        // 다른 파일이 없는 작품은 이 파일이 원래 있던 곳일 때만(비어 있는 다른 작품으로 들어가지 않게)
        $own = $db->prepare('SELECT 1 FROM volumes WHERE series_id=? AND path=? AND bidx=?');
        $own->execute([(int)$r['id'], $path, (int)$bidx]);
        if ($own->fetchColumn()) return (int)$r['id'];
    }
    $ak = array_keys($mine); sort($ak, SORT_STRING);
    $key2 = $key . '@' . $ak[0];
    for ($n = 2; isset($used[$key2]); $n++) $key2 = $key . '@' . $ak[0] . '#' . $n;
    $db->prepare('INSERT INTO series(bidx, name, name_key, created_at, updated_at) VALUES(?,?,?,?,?)')->execute([(int)$bidx, $name, $key2, $now, $now]);
    return (int)$db->lastInsertId();
}

/**
 * ★ (2026-10-29) 같은 제목 키(작가별로 나뉜 "키@작가" 포함, 별칭 포함)의 작품 중 이 파일의 작가와 겹치는 작가가 있는 작품 id(없으면 0)
 * — "7 Days by A" 가 있을 때 혼자 있는 "7 Days 2 by A" 를 그 작품의 2권으로 보기 위해
 */
function mc_lib_series_by_author(PDO $db, $bidx, $key, array $authors, $excludePath = null) {
    if ($key === '' || !$authors) return 0;
    $mine = [];
    foreach ($authors as $a) { $k = mc_lib_name_key($a); if ($k !== '') $mine[$k] = true; }
    if (!$mine) return 0;
    // 같은 키 + 작가별로 나뉜 키("키@작가" — 이름이 원래 제목과 같은 것만. 제목에 '@' 가 든 다른 작품 "A@B" 는 제외) + 별칭
    $ids = [];
    $st = $db->prepare("SELECT id, name, name_key FROM series WHERE bidx=? AND (name_key=? OR (name_key >= ? AND name_key < ?))");
    $st->execute([(int)$bidx, $key, $key . '@', $key . "@\u{10FFFF}"]);
    foreach ($st->fetchAll() as $r) if ($r['name_key'] === $key || mc_lib_name_key($r['name']) === $key) $ids[] = (int)$r['id'];
    $st = $db->prepare('SELECT series_id FROM series_alias WHERE bidx=? AND name_key=?');
    $st->execute([(int)$bidx, $key]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) $ids[] = (int)$x;
    foreach (array_unique($ids) as $sid) {
        $oa = mc_lib_vol_authors($db, (int)$sid, $excludePath);
        if ($oa && array_intersect_key($mine, $oa)) return (int)$sid;
    }
    return 0;
}

/**
 * ★ (2026-10-31) 다른 폴더에 흩어진 같은 작품 찾기 — 같은 작가의 "제목 - 부제"·"제목: 부제"($kind='dash') 또는 "제목 N"($kind='num', 혼자라 권으로 인정 못 받은 것) 작품
 *   후보는 이름 키 앞부분으로 뽑고(색인 사용), 실제 판정은 원래 이름을 다시 나눠 앞부분 키가 정확히 $key 인지 + 작가가 겹치는지로
 *   ("Amarimonogatari" 처럼 키 앞부분만 같은 다른 제목은 제외)
 * @return int 찾은 작품 id(없으면 0)
 */
function mc_lib_family_by_author(PDO $db, $bidx, $key, array $authors, $kind, $excludePath = null) {
    if ($key === '' || !$authors) return 0;
    $mine = [];
    foreach ($authors as $a) { $k = mc_lib_name_key($a); if ($k !== '') $mine[$k] = true; }
    if (!$mine) return 0;
    $st = $db->prepare("SELECT id, name FROM series WHERE bidx=? AND name_key > ? AND name_key < ? LIMIT 300");
    $st->execute([(int)$bidx, $key, $key . "\u{10FFFF}"]);
    foreach ($st->fetchAll() as $r) {
        $name = (string)$r['name'];
        if ($kind === 'dash') {
            if (!preg_match('/^(.+?)(?:\s+[-–—]|:)\s+\S/u', $name, $m)) continue;
        } else {
            if (!preg_match('/^(.+?)\s+(?:제\s*)?\d{1,4}$/u', $name, $m)) continue;
        }
        if (mc_lib_name_key(mc_lib_clean($m[1])) !== $key) continue;
        $oa = mc_lib_vol_authors($db, (int)$r['id'], $excludePath);
        if ($oa && array_intersect_key($mine, $oa)) return (int)$r['id'];
    }
    return 0;
}

/** ★ (2026-10-29) 같은 제목 키(작가별로 나뉜 작품 포함)의 권 수 — 재판단이 필요한지(다른 폴더에 그 제목 권이 생겼는지) 비교용 */
function mc_lib_key_vol_count(PDO $db, $bidx, $key) {
    $st = $db->prepare("SELECT COUNT(*) FROM volumes WHERE bidx=? AND missing=0 AND series_id IN
        (SELECT id FROM series WHERE bidx=? AND (name_key=? OR (name_key >= ? AND name_key < ?)) UNION SELECT series_id FROM series_alias WHERE bidx=? AND name_key=?)");
    $st->execute([(int)$bidx, (int)$bidx, $key, $key . '@', $key . "@\u{10FFFF}", (int)$bidx, $key]);
    return (int)$st->fetchColumn();
}

/**
 * ★ (2026-11-02 01:53) 시리즈 모든 권의 파일명·폴더명·ComicInfo 에서 뽑은 작가와 그룹(서클) — 이름 키 => 이름
 *   파일마다: "[그룹 (작가)]" 의 그룹은 group, 나머지(작가·by 작가·ComicInfo)는 author
 * @return array ['author' => [...], 'group' => [...]]
 */
function mc_lib_vol_credits(PDO $db, $sid) {
    static $memo = [];
    $st = $db->prepare('SELECT folder, filename, info FROM volumes WHERE series_id=? AND missing=0');
    $st->execute([(int)$sid]);
    $out = ['author' => [], 'group' => []];
    $pa = function ($name, $isFile) use (&$memo) {
        $k = ($isFile ? 'f' : 'd') . $name;
        if (!isset($memo[$k])) { if (count($memo) > 50000) $memo = []; $p = mc_lib_parse_name($name, $isFile); $memo[$k] = [$p['authors'], $p['groups']]; }
        return $memo[$k];
    };
    foreach ($st->fetchAll() as $r) {
        [$a, $g] = $pa($r['filename'], true);
        if (!$a && $r['folder'] !== '') [$a, $g] = $pa(basename($r['folder']), false); // 스캔과 같은 규칙(파일명에 작가가 없으면 폴더명)
        $gk = [];
        foreach ($g as $n) { $k = mc_lib_name_key($n); if ($k !== '') { $gk[$k] = true; $out['group'][$k] = $n; } }
        foreach ($a as $n) { $k = mc_lib_name_key($n); if ($k !== '' && !isset($gk[$k])) $out['author'][$k] = $n; }
        if ($r['info'] !== '') { $ci = json_decode($r['info'], true); foreach ((array)($ci['authors'] ?? []) as $n) { $k = mc_lib_name_key($n); if ($k !== '') $out['author'][$k] = $n; } }
    }
    return $out;
}

/**
 * 파일이 빠져나간 작품의 자동 작가를 남은 파일 기준으로 다시 맞춤(관리자가 작가를 정한 작품은 그대로)
 * ★ (2026-11-02 01:53) 그룹(서클)도 — 예전에 작가로 붙었던 그룹 이름은 그룹으로 옮김(자동으로 붙은 것만)
 *   관리자가 작가를 직접 정한 작품(manual 2)은 작가·그룹 모두 그대로, 그룹을 직접 정한 작품(32)은 그룹만 그대로
 */
function mc_lib_refresh_auto_authors(PDO $db, $sid) {
    $st = $db->prepare('SELECT manual FROM series WHERE id=?');
    $st->execute([(int)$sid]);
    $m = $st->fetchColumn();
    if ($m === false || ((int)$m & 2)) return;
    $m = (int)$m;
    $cr = mc_lib_vol_credits($db, $sid); // ★ 모든 권 기준
    $kinds = ['author'];
    if (!($m & 32)) $kinds[] = 'group';
    // ★ (2026-11-02 02:08) 붙어 있어야 할 용어 id(대소문자만 다른 표기는 한 id) — 그 밖의 자동 용어는 뗌(예전에 "GABURI"·"Gaburi" 둘 다 붙은 것도 하나로)
    $want = [];
    foreach ($kinds as $kind) foreach ($cr[$kind] as $n) { $tid = mc_lib_term_id($db, $kind, $n); if ($tid) $want[$tid] = true; }
    $st = $db->prepare("SELECT st.term_id, t.kind FROM series_terms st JOIN terms t ON t.id=st.term_id WHERE st.series_id=? AND st.source='auto' AND t.kind IN ('author','group')");
    $st->execute([(int)$sid]);
    $del = $db->prepare('DELETE FROM series_terms WHERE series_id=? AND term_id=?');
    foreach ($st->fetchAll() as $r) {
        if (!in_array($r['kind'], $kinds, true)) continue;
        if (!isset($want[(int)$r['term_id']])) $del->execute([(int)$sid, (int)$r['term_id']]);
    }
    $ins = $db->prepare("INSERT OR IGNORE INTO series_terms(series_id, term_id, source) VALUES(?,?,'auto')");
    foreach (array_keys($want) as $tid) $ins->execute([(int)$sid, (int)$tid]);
}

/**
 * ★ (2026-11-02 02:40) 작가·그룹·장르·태그 이름 비교 키 — 대소문자·띄어쓰기·기호·전각/반각이 달라도 같은 이름
 *   ("GABURI"·"Gaburi"·"Gabu-ri"·"ＧＡＢＵＲＩ" → gaburi). Kavita 의 사람·장르 이름 정규화(글자·숫자만 남기고 소문자)와 같은 방식
 *   (작품 이름 키 mc_lib_name_key 에 NFKC(전각→반각)만 더함 — 작품 묶음 키는 그대로 둠)
 *   기호만 있는 이름("???")은 키가 비므로 소문자 원문으로
 */
function mc_lib_term_key($name) {
    $s = (string)$name;
    if (class_exists('Normalizer')) { $n = Normalizer::normalize($s, Normalizer::FORM_KC); if ($n !== false && $n !== null) $s = $n; }
    $k = preg_replace('/[^\p{L}\p{M}\p{N}+!]+/u', '', mc_lib_lower($s)); // 글자·숫자(+ ! 포함)만 — 띄어쓰기·하이픈·점·☆ 등은 무시
    if ($k === null) $k = mc_lib_name_key($s); // 잘못된 UTF-8 이면 예전 방식
    return $k !== '' ? $k : '#' . mc_lib_lower(trim($s));
}

/**
 * ★ (2026-11-02 02:40) terms 에 비교 키(nkey) 칸 — 처음 쓸 때 한 번 만들고 채움(스캔·편집의 쓰기 중에 불림). 이후 새 용어는 넣을 때 채움
 * @return bool 쓸 수 있으면 true(실패하면 예전처럼 대소문자 무시 찾기)
 */
function mc_lib_terms_key_ready(PDO $db, $prepare = false) {
    static $ok = null;
    if ($ok !== null) return $ok;
    // ★ (2026-11-02 03:04) 칸·색인 만들기는 트랜잭션 밖에서만(mc_lib_begin_write 바로 앞) — 트랜잭션 안에서 만들면 그 트랜잭션이 롤백될 때 칸도 사라지는데
    //   '준비됨' 표시만 남아 같은 요청의 다음 폴더가 모두 실패할 수 있었음. 준비 전에는 예전 방식(대소문자 무시 찾기)
    if (!$prepare || $db->inTransaction()) return false;
    $ok = false;
    try {
        $has = false;
        foreach ($db->query('PRAGMA table_info(terms)')->fetchAll() as $c) if ($c['name'] === 'nkey') { $has = true; break; }
        if (!$has) $db->exec("ALTER TABLE terms ADD COLUMN nkey TEXT NOT NULL DEFAULT ''");
        $rows = $db->query("SELECT id, name FROM terms WHERE nkey=''")->fetchAll();
        if ($rows) { $up = $db->prepare('UPDATE terms SET nkey=? WHERE id=?'); foreach ($rows as $r) $up->execute([mc_lib_term_key($r['name']), (int)$r['id']]); }
        $db->exec('CREATE INDEX IF NOT EXISTS ix_terms_nkey ON terms(kind, nkey)');
        $ok = true;
    } catch (\Throwable $e) { error_log('[library] terms nkey: ' . $e->getMessage()); }
    return $ok;
}

function mc_lib_term_id(PDO $db, $kind, $name, $rename = false) {
    $name = mc_lib_clean(mc_lib_nfc($name));
    // ★ (2026-11-02 03:05) 전각 영문·숫자·기호(ＧＡＢＵＲＩ)는 반각으로 표시 — 같은 사람이 먼저 전각으로 등록되면 그 표기로 보이던 것
    $fw = function ($x) { $y = preg_replace_callback('/[\x{FF01}-\x{FF5E}]/u', function ($m) { return mb_chr(mb_ord($m[0], 'UTF-8') - 0xFEE0, 'UTF-8'); }, (string)$x); return $y === null ? (string)$x : $y; };
    $name = $fw($name);
    if ($name === '' || !in_array($kind, ['author', 'group', 'genre', 'tag'], true)) return null; // ★ (2026-11-02 01:53) group = 그룹(서클)
    if (mb_strlen($name, 'UTF-8') > 60) $name = mb_substr($name, 0, 60, 'UTF-8');
    // ★ (2026-11-02 02:40) 같은 이름은 비교 키(mc_lib_term_key — 대소문자·띄어쓰기·기호·전각 무시)로 하나 — 가장 먼저 만든 용어를 씀
    //   (예전에 갈라져 생긴 "GABURI"·"Gaburi" 도 다시 판단 때 이쪽으로 모임. 02:08 의 영문 대소문자만 보던 방식을 넓힘)
    $key = mc_lib_term_key($name);
    if (mc_lib_terms_key_ready($db)) {
        $st = $db->prepare('SELECT id, name FROM terms WHERE kind=? AND nkey=? ORDER BY id LIMIT 1');
        $st->execute([$kind, $key]);
    } else {
        $st = $db->prepare('SELECT id, name FROM terms WHERE kind=? AND name=? COLLATE NOCASE ORDER BY id LIMIT 1');
        $st->execute([$kind, $name]);
    }
    $row = $st->fetch();
    if ($row) {
        // 예전에 전각으로 등록된 표기는 반각으로(같은 이름의 다른 행이 있으면 그대로)
        if (!$rename && $fw($row['name']) !== $row['name']) { try { $db->prepare('UPDATE terms SET name=? WHERE id=?')->execute([$fw($row['name']), (int)$row['id']]); } catch (\Throwable $e) {} }
        // 편집 창에서 표기를 바꿔 넣으면($rename) 그 표기로 바꿈(모든 작품에 같이 — 같은 사람이라서). 같은 표기의 옛 용어가 따로 남아 있으면 그대로 둠
        if ($rename && $row['name'] !== $name) { try { $db->prepare('UPDATE terms SET name=? WHERE id=?')->execute([$name, (int)$row['id']]); } catch (\Throwable $e) {} }
        return (int)$row['id'];
    }
    // ★ (03:04) 같은 표기가 이미 있으면(다른 요청이 키 없이 넣은 행 등) 그 행을 씀 — 그대로 넣으면 UNIQUE(kind,name) 충돌로 그 폴더 스캔이 실패
    $st = $db->prepare('SELECT id FROM terms WHERE kind=? AND name=?');
    $st->execute([$kind, $name]);
    $ex = $st->fetchColumn();
    if ($ex) {
        if (mc_lib_terms_key_ready($db)) { try { $db->prepare('UPDATE terms SET nkey=? WHERE id=?')->execute([$key, (int)$ex]); } catch (\Throwable $e) {} }
        return (int)$ex;
    }
    if (mc_lib_terms_key_ready($db)) $db->prepare('INSERT INTO terms(kind, name, nkey) VALUES(?,?,?)')->execute([$kind, $name, $key]);
    else $db->prepare('INSERT INTO terms(kind, name) VALUES(?,?)')->execute([$kind, $name]);
    return (int)$db->lastInsertId();
}

/**
 * 자동 작가 반영 — 관리자가 작가를 직접 넣은 시리즈는 건드리지 않음
 * ★ (2026-11-02 01:53) $groups(그룹·서클 이름)에 든 이름은 '그룹'으로 — 그룹을 직접 정한 시리즈(manual 32)면 그룹은 안 붙임
 */
function mc_lib_apply_auto_authors(PDO $db, $sid, array $authors, array $groups = []) {
    if (!$sid || !$authors) return;
    $st = $db->prepare("SELECT manual FROM series WHERE id=?");
    $st->execute([$sid]);
    $man = (int)$st->fetchColumn();
    if ($man & 2) return; // 관리자가 작가를 직접 정한 시리즈
    $gk = [];
    foreach ($groups as $g) { $k = mc_lib_name_key($g); if ($k !== '') $gk[$k] = true; }
    $ins = $db->prepare("INSERT OR IGNORE INTO series_terms(series_id, term_id, source) VALUES(?,?,'auto')");
    foreach (array_unique($authors) as $a) {
        $kind = isset($gk[mc_lib_name_key($a)]) ? 'group' : 'author';
        if ($kind === 'group' && ($man & 32)) continue;
        $tid = mc_lib_term_id($db, $kind, $a);
        if ($tid) $ins->execute([$sid, $tid]);
    }
}

/**
 * ★ (2026-10-29) zip/cbz 안을 읽기만 함(수정 없음): 이미지 수, 루트(또는 가장 얕은 위치)의 ComicInfo.xml
 * @return array|null ['pages'=>int, 'info'=>array]  — 열 수 없으면 null
 */
function mc_lib_probe_zip($full) {
    if (!class_exists('ZipArchive')) return null;
    $z = new ZipArchive();
    $flags = defined('ZipArchive::RDONLY') ? ZipArchive::RDONLY : 0;
    if (@$z->open($full, $flags) !== true) return null;
    $pages = 0; $ci = null; $ciDepth = 99;
    $n = $z->numFiles;
    for ($i = 0; $i < $n; $i++) {
        $name = $z->getNameIndex($i);
        if ($name === false || $name === '' || substr($name, -1) === '/') continue;
        $name = str_replace('\\', '/', $name);
        $b = strtolower(basename($name));
        if (strpos($name, '__MACOSX/') === 0 || ($b !== '' && $b[0] === '.')) continue;
        if (preg_match('/\.(jpe?g|png|gif|webp|bmp|avif|jxl|jfif)$/', $b)) { $pages++; continue; }
        if ($b === 'comicinfo.xml') { $d = substr_count($name, '/'); if ($d < $ciDepth) { $ci = $i; $ciDepth = $d; } }
    }
    $info = [];
    if ($ci !== null) {
        $st = $z->statIndex($ci);
        if ($st && (int)$st['size'] > 0 && (int)$st['size'] <= 262144) { // 256KB 넘는 메타데이터는 무시
            $xml = $z->getFromIndex($ci, 262144); // 선언 크기와 달라도 최대 256KB 까지만 읽음
            if (is_string($xml)) $info = mc_lib_parse_comicinfo($xml);
        }
    }
    $z->close();
    return ['pages' => $pages, 'info' => $info];
}

/**
 * ComicInfo.xml(Anansi 표준) 중 필요한 값만 — DOCTYPE(엔티티) 있는 문서는 읽지 않음(XXE·엔티티 폭탄 방지), 네트워크 접근 금지
 * @return array [series,title,number,volume,authors[],genres[],tags[],summary] (값 없으면 빈 배열)
 */
function mc_lib_parse_comicinfo($xml) {
    $xml = (string)$xml;
    if (strncmp($xml, "\xEF\xBB\xBF", 3) === 0) $xml = substr($xml, 3);
    if ($xml === '' || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) return [];
    if (!function_exists('simplexml_load_string')) return [];
    $prev = libxml_use_internal_errors(true);
    $x = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($x === false) return [];
    $get = function ($k, $max = 200) use ($x) {
        if (!isset($x->{$k})) return '';
        $v = mc_lib_clean(mc_lib_nfc(trim((string)$x->{$k})));
        if ($v === '' || !mb_check_encoding($v, 'UTF-8')) return '';
        return mb_substr($v, 0, $max, 'UTF-8');
    };
    $list = function ($k) use ($get) {
        $out = [];
        foreach (preg_split('/\s*[,;]\s*/u', $get($k, 1000)) as $t) {
            $t = mc_lib_clean($t);
            if ($t !== '' && mb_strlen($t, 'UTF-8') <= 60 && !in_array($t, $out, true)) $out[] = $t;
            if (count($out) >= 30) break;
        }
        return $out;
    };
    // 설명은 줄바꿈 유지(줄 안의 연속 공백·3줄 이상 빈 줄만 정리)
    $summary = '';
    if (isset($x->Summary)) {
        $sm = str_replace(["\r\n", "\r"], "\n", mc_lib_nfc(trim((string)$x->Summary)));
        $sm = preg_replace(['/[ \t]+/u', '/\n{3,}/u'], [' ', "\n\n"], $sm);
        if (is_string($sm) && mb_check_encoding($sm, 'UTF-8')) $summary = mb_substr(trim($sm), 0, 5000, 'UTF-8');
    }
    $num = function ($k) use ($get) { $v = $get($k, 12); return preg_match('/^\d{1,5}(?:\.\d{1,2})?$/', $v) ? $v : ''; };
    $r = [
        'series' => $get('Series'), 'title' => $get('Title'), 'number' => $num('Number'), 'volume' => $num('Volume'),
        'authors' => array_values(array_unique(array_merge($list('Writer'), $list('Penciller')))),
        'genres' => $list('Genre'), 'tags' => $list('Tags'), 'summary' => $summary,
    ];
    foreach ($r as $v) if ($v !== '' && $v !== []) return $r;
    return [];
}

/** ComicInfo 의 장르·태그(자동)·설명 반영 — 관리자가 직접 정한 항목(manual 비트 8=장르, 16=태그, 4=상태·설명)은 건드리지 않음 */
function mc_lib_apply_comicinfo(PDO $db, $sid, array $ci) {
    if (!$sid || !$ci) return;
    $st = $db->prepare('SELECT manual, description FROM series WHERE id=?');
    $st->execute([(int)$sid]);
    $row = $st->fetch();
    if (!$row) return;
    $man = (int)$row['manual'];
    $ins = $db->prepare("INSERT OR IGNORE INTO series_terms(series_id, term_id, source) VALUES(?,?,'auto')");
    foreach (['genres' => ['genre', 8], 'tags' => ['tag', 16]] as $field => [$kind, $bit]) {
        if (($man & $bit) || empty($ci[$field])) continue;
        foreach ((array)$ci[$field] as $nm) { $tid = mc_lib_term_id($db, $kind, $nm); if ($tid) $ins->execute([(int)$sid, $tid]); }
    }
    if (!($man & 4) && (string)$row['description'] === '' && !empty($ci['summary'])) {
        $db->prepare('UPDATE series SET description=? WHERE id=?')->execute([(string)$ci['summary'], (int)$sid]);
    }
}

/**
 * ★ (2026-10-29) 권 표시 이름 통일 — 파일명에 적힌 표기(v1·Vol.1·1巻·01·#3·ch12·12話)를 화면에서는 "1권"·"12화" 로
 * (저장된 vol_label 은 그대로, 화면 표시만)
 */
function mc_lib_label_display($label) {
    $l = trim((string)$label);
    // ★ (2026-10-29) 사용자 요청: 화 표기(12話·12화·ch12·Dai 12 wa)도 권 목록에서는 "12권"으로 — 저장 값·묶기·순서는 그대로, 화면 표시만
    if ($l === '') return '';
    $n = function ($x) { return (string)((float)$x + 0); };
    // ★ (2026-10-29) 나눔 표시: "前編"·"上"·"Part 2" 단독, 또는 번호 뒤("3 前編", "ch4 Part 2", "3話 後編")
    $parts = ['前編' => 'lib_part_1', '中編' => 'lib_part_2', '後編' => 'lib_part_3', '上' => 'lib_part_jou', '中' => 'lib_part_chuu', '下' => 'lib_part_ge'];
    if (isset($parts[$l])) return __($parts[$l]);
    if (preg_match('/^Part (\d{1,3}(?:\.\d)?)$/u', $l, $m)) return __('lib_part_num', $m[1]);
    if (preg_match('/^(.+?)\s+(前編|中編|後編|上|中|下|Part \d{1,3})$/u', $l, $m)) return mc_lib_label_display($m[1]) . ' ' . mc_lib_label_display($m[2]);
    if (preg_match('/^(\d{1,4})-(\d{1,4})$/u', $l, $m)) return __('lib_vol_label_num', ((int)$m[1]) . '-' . ((int)$m[2])); // 끝 번호 범위 "1-3"
    if (preg_match('/^(?:ch|ep)\.?\s*(\d{1,4})-(\d{1,4})$/iu', $l, $m)) return __('lib_vol_label_num', ((int)$m[1]) . '-' . ((int)$m[2]));
    if (preg_match('/^(?:v|vol\.?\s*)?(\d{1,5}(?:\.\d{1,2})?)$/iu', $l, $m)) return __('lib_vol_label_num', $n($m[1]));
    if (preg_match('/^(\d{1,5}(?:\.\d{1,2})?)\s*(?:권|巻|卷|冊)$/u', $l, $m)) return __('lib_vol_label_num', $n($m[1]));
    if (preg_match('/^(?:ch|ep)\.?\s*(\d{1,5}(?:\.\d{1,2})?)$/iu', $l, $m)) return __('lib_vol_label_num', $n($m[1]));
    if (preg_match('/^(\d{1,5}(?:\.\d{1,2})?)\s*(?:화|話|话)$/u', $l, $m)) return __('lib_vol_label_num', $n($m[1]));
    if (preg_match('/^(\d{1,5}(?:\.\d{1,2})?-\d{1,2})\s*(?:화|話|话)$/u', $l, $m)) return __('lib_vol_label_num', $m[1]); // "13-2話" → "13-2권"(나눠진 화)
    if (preg_match('/^(\d{1,4})\s*-\s*(\d{1,4})\s*(?:권|巻|卷|冊)$/u', $l, $m)) return __('lib_vol_label_num', ((int)$m[1]) . '-' . ((int)$m[2]));
    return $l;
}

/**
 * ★ (2026-10-29) 권 표시 제목 — 권 번호(+ 부제), 권 번호가 없으면 부제 → 파일명에서 꼬리표·괄호를 뺀 제목 → 파일명
 */
function mc_lib_vol_title(array $v, $implicitOne = false) {
    // ★ (2026-10-29) 권 목록 이름은 "1권·2권·12화·전편"처럼 번호만(부제·원래 파일명은 아래 작은 줄에) — 번외·외전 부제만 붙임("2권 · Omake")
    $lb = mc_lib_label_display($v['vol_label'] ?? '');
    $sub = trim((string)($v['subtitle'] ?? ''));
    if ($lb !== '') return mc_lib_is_extra_sub($sub) ? $lb . ' · ' . $sub : $lb;
    // 번호 없는 본편 → 1권(mc_lib_implicit_first: 혼자인 책, 또는 2권부터 번호 있는 권이 있을 때 — "7 Days" + "7 Days 2") / 화로 된 작품은 1화
    if ($implicitOne && ($v['vol_no'] ?? null) === null && !mc_lib_is_extra_sub($sub)) return __('lib_vol_label_num', '1'); // 화로 된 작품('ch')도 표시는 1권
    if ($sub !== '') return $sub;
    $p = mc_lib_parse_name((string)($v['filename'] ?? ''), true);
    // ★ (2026-10-29) 끝 숫자가 권으로 인정되지 않은 이름은 숫자까지 제목("Rocky IV", "기생수2")
    if ($p['vol_kind'] === 'tail' && $p['tail_title'] !== '') return $p['tail_title'];
    $t = $p['series'] !== '' ? $p['series'] : preg_replace('/\.[A-Za-z0-9]{1,5}$/', '', (string)($v['filename'] ?? ''));
    return $t;
}

/** ★ (2026-10-29) 번외·외전 부제인지("Omake", "외전" …) — 같은 번호 본편의 사본이 아님 / 권 이름에 부제를 붙여 구분 */
function mc_lib_is_extra_sub($sub) {
    return (bool)preg_match('/(?:^|\s)(?:Omake|おまけ|オマケ|Extra|EX|Gaiden|外伝|Bangaihen|番外編|番外篇|외전|번외편|번외|특별편|Kanketsuhen|完結編|Soushuuhen|総集編|총집편)$/iu', trim((string)$sub)); // ★ (2026-11-02 11:41) 총집편 계열도(본권 사본으로 묶여 숨던 것)
}

/**
 * ★ (2026-10-29) 번호 없는 본편을 1권으로 볼지 — 번호·부제 없는 권이 있고, 권 번호(권·v·끝 숫자, 화 제외)가 있는 권이 있으며
 * 그 가장 작은 번호가 2 이상(1권이 따로 없음)일 때. "7 Days"(번호 없음) + "7 Days 2" → 1권·2권
 */
function mc_lib_implicit_first(array $vols) {
    // 번호 없는 본편 후보: 번호·표시 없음 + 번외 부제 아님. 사본 묶음(dkey)으로 하나뿐이어야 함("~부제A~"·"~부제B~" 두 책이면 부제로 구분)
    // ★ (2026-10-29) 돌려주는 값: false / 'vol'(권으로 된 작품 → 1권) / 'ch'(화로 된 작품 → 1화, "제목" + "제목 Dai Ni wa")
    $main = []; $minV = null; $minC = null; $numbered = false;
    foreach ($vols as $v) {
        if (($v['vol_no'] ?? null) === null) {
            if (trim((string)($v['vol_label'] ?? '')) === '' && !mc_lib_is_extra_sub($v['subtitle'] ?? '')) $main[mc_lib_dup_key($v)] = true;
            continue;
        }
        $numbered = true;
        $l = trim((string)($v['vol_label'] ?? ''));
        $n = (float)$v['vol_no'];
        if (preg_match('/^(?:v|vol\.?\s*)?\d{1,5}(?:\.\d{1,2})?(?:\s*(?:권|巻|卷|冊))?$/iu', $l)) { if ($minV === null || $n < $minV) $minV = $n; }
        elseif (preg_match('/^(?:(?:ch|ep)\.?\s*\d{1,5}(?:\.\d{1,2})?|\d{1,5}(?:\.\d{1,2})?\s*(?:화|話|话))$/iu', $l)) { if ($minC === null || $n < $minC) $minC = $n; }
        // 범위·나눔·편 표시 등은 따지지 않음
    }
    if (count($main) !== 1) return false;
    if (!$numbered) return 'vol'; // 번호 있는 권이 없으면 혼자인 책(번외만 따로 있어도) → 1권
    if ($minV !== null) return ($minV >= 2 && ($minC === null || $minC >= 2)) ? 'vol' : false;
    if ($minC !== null) return $minC >= 2 ? 'ch' : false;
    return false;
}

/** 경로의 첫 부분(권한 확인 단위 — mc_lib_can_access 와 같은 기준) */
function mc_lib_root_of($path) {
    return (string)explode('/', trim((string)$path, '/'))[0];
}

/** 정렬용 권 번호: 번호 없는 본편(부제 없음, 또는 1권으로 보는 본편)은 0, 그 밖의 번호 없는 권은 맨 뒤 */
function mc_lib_vol_sort_no(array $v, $implicitOne = false) {
    if ($v['vol_no'] !== null) return (float)$v['vol_no'];
    if (mc_lib_is_extra_sub($v['subtitle'] ?? '')) return (float)PHP_INT_MAX; // 번외·외전은 맨 뒤
    // 부제 없는 본편은 맨 앞(1권 자리) / ★ (2026-10-29) 부제가 있어도 1권으로 보는 본편(mc_lib_implicit_first)이면 맨 앞, 아니면 이전처럼 맨 뒤
    return (trim((string)($v['subtitle'] ?? '')) === '' || $implicitOne) ? 0.0 : (float)PHP_INT_MAX;
}

/** 사본 판단 키: 권 번호(없으면 n + 부제 키) */
function mc_lib_dup_key(array $v) {
    // ★ (2026-10-29) 권 번호가 있으면 번호만(같은 화의 번역·표기만 다른 사본: "Dai 12 wa - Nine to Five Lover | 9시부터…" = "Dai 12 wa ~Nine to Five Lover")
    //    권 번호가 없으면 부제로 구분
    //    ★ 단, 번외·외전 부제("Title 2 Omake")는 같은 번호 본편의 사본이 아님 → 따로
    if ($v['vol_no'] !== null) {
        $x = mc_lib_is_extra_sub($v['subtitle'] ?? '') ? 'x' : '';
        // ★ 범위("1-3화", "Ch. 1-3", "1-10권")는 끝 번호까지 — 같은 시작 번호의 단권("1화")과 다른 책
        $r = (preg_match('/(\d{1,4})\s*-\s*(\d{1,4})/u', (string)($v['vol_label'] ?? ''), $rm) && (int)$rm[2] > (int)$rm[1] && (float)$rm[1] == (float)$v['vol_no']) ? 'r' . (int)$rm[2] : '';
        return 'v' . rtrim(rtrim(number_format((float)$v['vol_no'], 2, '.', ''), '0'), '.') . '|' . $x . $r;
    }
    return 'n|' . mc_lib_name_key((string)($v['subtitle'] ?? ''));
}

/**
 * ★ (2026-10-29) 같은 책 사본 묶기 — 같은 시리즈 안에서 (권 번호 + 부제)가 같으면 같은 책
 * (갤러리 번호·[Korean] 같은 꼬리표는 파일명 분석에서 이미 빠짐 → "제목 (1838488)" 과 "제목 (4959494)" 는 같은 책)
 * 대표 사본: 내가 읽던 사본 → 쪽수 많은 것 → 파일 큰 것 → 최근 수정 순
 * @param array $vols  mc_lib_series_volumes() 결과(정렬된 상태)
 * @param array $reads mc_lib_user_reads() 결과(없으면 [])
 * @return array 대표 권 목록(정렬 유지). 각 원소에 '_copies' => [사본들(대표 포함)]
 */
function mc_lib_group_dups(array $vols, array $reads = []) {
    $groups = []; $order = [];
    foreach ($vols as $v) {
        $k = mc_lib_dup_key($v);
        if (!isset($groups[$k])) { $groups[$k] = []; $order[] = $k; }
        $groups[$k][] = $v;
    }
    $out = [];
    foreach ($order as $k) {
        $g = $groups[$k];
        usort($g, function ($a, $b) use ($reads) {
            // ★ fix64 관리자가 정한 사본 순서(corder>0)가 먼저 — 정한 사본끼리는 그 순서, 정하지 않은 사본은 그 뒤에 예전 규칙대로
            $ca = (int)($a['corder'] ?? 0); $cb = (int)($b['corder'] ?? 0);
            if ($ca !== $cb && ($ca > 0 || $cb > 0)) return ($ca > 0 && $cb > 0) ? $ca - $cb : ($ca > 0 ? -1 : 1);
            $ra = isset($reads[(int)$a['bidx'] . '|' . $a['path']]) ? 1 : 0;
            $rb = isset($reads[(int)$b['bidx'] . '|' . $b['path']]) ? 1 : 0;
            if ($ra !== $rb) return $rb - $ra;
            if ((int)$a['pages'] !== (int)$b['pages']) return (int)$b['pages'] - (int)$a['pages'];
            if ((int)$a['size'] !== (int)$b['size']) return (int)$b['size'] <=> (int)$a['size'];
            return (int)$b['mtime'] <=> (int)$a['mtime'];
        });
        $rep = $g[0];
        $rep['_copies'] = $g;
        $out[] = $rep;
    }
    return $out;
}

/**
 * 한 폴더(하위 폴더 제외)의 권을 인덱싱
 * @return int 처리한 권 수 (DB 없음/폴더 없음이면 0)
 */
/**
 * ★ (2026-10-30) 바깥 감싸기 — 안쪽(트랜잭션 밖의 '확인 시각'·'없음' 쓰기 포함)에서 DB 오류가 나도 부른 화면이 죽지 않게(작품 상세가 스캔 중 500 오류로 뜨던 것)
 * 실패는 기록하고(스캔 끝 '없음' 처리 막음·다음 빠른 스캔에서 다시) 0 을 돌려줌
 */
function mc_lib_index_folder($bidx, $relFolder, $probe_sec = null, $force = false) {
    try {
        return mc_lib_index_folder_inner($bidx, $relFolder, $probe_sec, $force);
    } catch (\Throwable $e) {
        $db = mc_lib_db();
        try { if ($db && $db->inTransaction()) $db->rollBack(); } catch (\Throwable $e2) {}
        error_log('[library] index_folder failed: ' . $e->getMessage());
        mc_lib_index_stat('failed');
        return 0;
    }
}

function mc_lib_index_folder_inner($bidx, $relFolder, $probe_sec = null, $force = false) {
    // $probe_sec: zip 내부 확인 시간(전체 스캔은 폴더마다 지정, 그 밖은 요청당 합계 제한)
    // ★ (2026-10-29) $force=false 이면 폴더의 파일 이름 목록이 지난번과 같고 미뤄 둔 판단·확인이 없을 때 건너뜀(추가·삭제·이름 변경은 목록이 바뀌어 바로 반영)
    $db = mc_lib_db();
    if (!$db) return 0;
    $relFolder = ($relFolder === '' || $relFolder === '/') ? '' : mc_lib_norm_path($relFolder);
    $base = mc_lib_base($bidx);
    if (!$base) return 0;
    $abs = $base . $relFolder;
    if (!is_dir($abs)) {
        // ★ (2026-10-29) 폴더째 지워졌으면 그 폴더의 권을 '없음'으로(기록은 유지 → 폴더가 돌아오면 복구)
        if (is_dir($base)) {
            $db->prepare('UPDATE volumes SET missing=1 WHERE bidx=? AND folder=? AND +missing=0')->execute([(int)$bidx, $relFolder]);
            $db->prepare('DELETE FROM folders WHERE bidx=? AND path=?')->execute([(int)$bidx, $relFolder]);
        }
        return 0;
    }
    if ($relFolder !== '' && !mc_lib_inside_base($bidx, $relFolder)) return 0;
    $items = @scandir($abs, SCANDIR_SORT_NONE);
    if ($items === false) return 0;

    $exts = mc_lib_exts();
    $now = time();
    // 폴더 서명: 대상 확장자 파일 이름 목록(정렬) — 같으면 파일을 하나하나 확인하지 않고 건너뜀
    $names = [];
    foreach ($items as $fn) {
        if ($fn === '' || $fn[0] === '.' || $fn === '@eaDir') continue;
        if (in_array(strtolower(pathinfo($fn, PATHINFO_EXTENSION)), $exts, true)) $names[] = $fn;
    }
    sort($names, SORT_STRING);
    $fsig = md5(implode("\n", $names)) . ':' . count($names) . ':p' . MC_LIB_PARSER;
    $fst = $db->prepare('SELECT sig, pending FROM folders WHERE bidx=? AND path=?');
    $fst->execute([(int)$bidx, $relFolder]);
    $frow = $fst->fetch();
    $fst->closeCursor(); // ★ (2026-10-30) 열어 두면 아래 저장(트랜잭션)이 다른 요청의 쓰기와 부딪혀 바로 실패 — 바로 닫음
    if (!$force && $frow && $frow['sig'] === $fsig && (int)$frow['pending'] === 0) {
        // ★ (2026-11-02 08:54) 확인 시각은 전체 스캔(probe_sec 지정)에서만 씀 — 작품 화면을 열 때마다 DB 가 바뀌어 서재 목록 캐시가 버려지던 문제('없음' 판단은 스캔 중 시각만 봄)
        if ($probe_sec !== null) $db->prepare('UPDATE folders SET seen_at=? WHERE bidx=? AND path=?')->execute([$now, (int)$bidx, $relFolder]);
        mc_lib_index_stat('skipped');
        return count($names);
    }
    mc_lib_index_stat('indexed');
    $folderName = $relFolder === '' ? '' : basename($relFolder);
    $folderParse = $folderName !== '' ? mc_lib_parse_name($folderName, false) : null;
    $files = [];
    foreach ($items as $fn) {
        if ($fn === '' || $fn[0] === '.' || $fn === '@eaDir') continue;
        $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) continue;
        $full = $abs . '/' . $fn;
        if (!is_file($full)) continue;
        $files[] = [$fn, $ext, $full];
    }

    $selVol = $db->prepare('SELECT id, series_id, manual, mtime, size, vol_no, vol_label, subtitle, pages, info, probe, dkey, root, missing FROM volumes WHERE bidx=? AND path=?');
    // ★ (2026-10-29) 값이 하나도 바뀌지 않은 권은 다시 쓰지 않음(쓰기 줄이기 — 사라진 권 판단은 folders 표 기준이라 seen_at 은 쓰지 않아도 됨)
    $same = function ($row, $sid, $vol, $label, $size, $mtime, $sub, $mt, $dkey, $root) {
        $rv = $row['vol_no'] === null ? null : (float)$row['vol_no'];
        return (int)$row['missing'] === 0 && (int)$row['series_id'] === (int)$sid && $rv === ($vol === null ? null : (float)$vol)
            && (string)$row['vol_label'] === (string)$label && (int)$row['size'] === (int)$size && (int)$row['mtime'] === (int)$mtime
            && (string)$row['subtitle'] === (string)$sub && (int)$row['pages'] === (int)$mt['pages'] && (string)$row['info'] === (string)$mt['info']
            && (string)$row['probe'] === (string)$mt['probe'] && (string)$row['dkey'] === (string)$dkey && (string)$row['root'] === (string)$root;
    };
    $authorDone = []; // 같은 작품·작가 조합은 한 번만 반영
    $insVol = $db->prepare('INSERT INTO volumes(bidx, path, folder, filename, ext, series_id, vol_no, vol_label, size, mtime, added_at, seen_at, missing, manual, subtitle, pages, info, probe, dkey, root) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,0,0,?,?,?,?,?,?)');
    $updAuto = $db->prepare('UPDATE volumes SET series_id=?, vol_no=?, vol_label=?, size=?, mtime=?, seen_at=?, missing=0, subtitle=?, pages=?, info=?, probe=?, dkey=?, root=? WHERE id=?');
    $updSeen = $db->prepare('UPDATE volumes SET size=?, mtime=?, seen_at=?, missing=0, pages=?, info=?, probe=?, dkey=? WHERE id=?');

    // ★ (2026-10-29) zip/cbz 내부 확인(이미지 수 = 쪽수, ComicInfo.xml) — 새 파일이거나 크기·수정시각이 바뀐 파일만, 한 번에 최대 약 4초
    $known = [];
    $kst = $db->prepare('SELECT path, pages, info, probe FROM volumes WHERE bidx=? AND folder=?');
    $kst->execute([(int)$bidx, $relFolder]);
    foreach ($kst->fetchAll() as $r) $known[$r['path']] = $r;
    static $probeAll = null; // 화면 요청(상세·일괄 편집)은 합계 약 1.5초만 — 화면이 느려지지 않게, 남은 파일은 스캔이 이어서 확인(pending 2)
    if ($probe_sec !== null) { $probeUntil = microtime(true) + (float)$probe_sec; }
    else { if ($probeAll === null) $probeAll = microtime(true) + 1.5; $probeUntil = min(microtime(true) + 1.0, $probeAll); }
    $meta = [];
    $probeLeft = false;
    foreach ($files as $i => [$fn, $ext, $full]) {
        $rel = $relFolder . '/' . $fn;
        $sig = (int)@filesize($full) . ':' . (int)@filemtime($full);
        $k = $known[$rel] ?? null;
        $m = ['pages' => $k ? (int)$k['pages'] : 0, 'info' => $k ? (string)$k['info'] : '', 'probe' => $k ? (string)$k['probe'] : ''];
        if (in_array($ext, ['zip', 'cbz'], true) && $m['probe'] !== $sig && microtime(true) >= $probeUntil) $probeLeft = true; // 시간이 모자라 다음에 확인
        if (in_array($ext, ['zip', 'cbz'], true) && $m['probe'] !== $sig && microtime(true) < $probeUntil) {
            $pr = mc_lib_probe_zip($full);
            if ($pr !== null) {
                $m['pages'] = $pr['pages'];
                $m['info'] = $pr['info'] ? json_encode($pr['info'], JSON_UNESCAPED_UNICODE) : '';
            }
            $m['probe'] = $sig; // 열 수 없는 파일도 다시 시도하지 않게(파일이 바뀌면 다시 확인)
        }
        $meta[$i] = $m;
    }

    // ★ (2026-10-29) 끝 숫자만 있는 이름("제목 01", "건담 0079")은 같은 폴더의 다른 파일과 비교해 결정
    //   - 같은 제목에 서로 다른 번호가 2개 이상(권 표시가 있는 파일 포함) → 권 번호
    //   - 제목이 폴더 이름과 같음(폴더 "원피스" 안의 "원피스 5") 또는 제목이 비어 있음("[작가] 01") → 권 번호
    //   - 그 밖(혼자 있는 "건담 0079") → 숫자까지 제목으로 보고 권 번호 없음
    $parsed = [];
    $groupNums = [];
    foreach ($files as $i => $f) {
        $p = mc_lib_parse_name($f[0], true);
        // ComicInfo.xml 이 있으면 우선(Kavita·Komga 와 같은 방식): Series → 시리즈, Number(없으면 Volume) → 권, Title → 부제, Writer·Penciller → 작가
        $ci = $meta[$i]['info'] !== '' ? (json_decode($meta[$i]['info'], true) ?: []) : [];
        $ciNum = $ci ? ($ci['number'] !== '' ? $ci['number'] : $ci['volume']) : '';
        if (!empty($ci['series'])) {
            $p['series'] = $ci['series'];
            if ($ciNum !== '' && is_numeric($ciNum)) { $p['vol'] = max(0, min(99999, (float)$ciNum)); $p['label'] = $ciNum; $p['vol_kind'] = 'ci'; }
            if ($ci['title'] !== '' && mc_lib_name_key($ci['title']) !== mc_lib_name_key($ci['series'])) $p['subtitle'] = $ci['title'];
        } elseif ($ciNum !== '' && is_numeric($ciNum) && ($p['vol'] === null || $p['vol_kind'] === 'tail')) {
            // Series 없이 Number 만 있으면 시리즈는 파일명대로, 권 번호만 ComicInfo 값(파일명에 권 표시가 없을 때)
            $p['vol'] = max(0, min(99999, (float)$ciNum)); $p['label'] = $ciNum; $p['vol_kind'] = 'ci';
        }
        if (!empty($ci['authors'])) $p['authors'] = array_values(array_unique(array_merge($ci['authors'], $p['authors'])));
        $parsed[$i] = $p;
        if ($p['vol'] !== null && $p['series'] !== '') $groupNums[mc_lib_name_key($p['series'])][(string)$p['vol']] = true;
    }
    $folderKeys = [];
    if ($folderParse) {
        foreach ([$folderParse['series'], $folderParse['tail_title'], $folderName] as $fk) { $fk = mc_lib_name_key($fk); if ($fk !== '') $folderKeys[$fk] = true; }
    }
    //   - ★ 다른 폴더에 같은 제목의 번호 있는 권이 이미 있음(폴더별로 나눠 올린 사본·연재분) → 권 번호
    $kcache = [];
    $hasNumbered = function ($k) use ($db, $bidx, &$kcache) {
        if ($k === '') return false;
        if (isset($kcache[$k])) return $kcache[$k];
        $q = $db->prepare('SELECT 1 FROM volumes v WHERE v.bidx=? AND v.missing=0 AND v.vol_no IS NOT NULL AND v.series_id IN
            (SELECT id FROM series WHERE bidx=? AND name_key=? UNION SELECT series_id FROM series_alias WHERE bidx=? AND name_key=?) LIMIT 1');
        $q->execute([(int)$bidx, (int)$bidx, $k, (int)$bidx, $k]);
        return $kcache[$k] = (bool)$q->fetchColumn();
    };
    $rejKeys = []; // 이번에 인정 못 한 후보의 제목 키 → 나중에 다른 폴더에 같은 제목 권이 생기면 다시 판단(스캔 끝)
    // ★ (2026-10-31) "제목 - 부제"(번호 없음, 앞뒤 띄운 - – — 또는 ': ') — 같은 앞부분 제목의 번호 있는 권이 있고 작가가 겹칠 때만 그 작품의 권(부제)으로
    //   예: "Amarimono - Doutei Shojo … by Kagura Hitsuji" + "Amarimono 2 -Shojo o … - by Kagura Hitsuji" → 작품 "Amarimono"(번호 없는 본편 = 1권, 2권)
    //   근거 없이 혼자 있는 "Re:Zero - Starting Life …" 같은 제목은 그대로(나누지 않음)
    $authKeys = function (array $list) { $o = []; foreach ($list as $a) { $k = mc_lib_name_key($a); if ($k !== '') $o[$k] = true; } return $o; };
    $dash = []; $dashAuth = [];
    foreach ($parsed as $i => $p) {
        if ($p['vol'] !== null || $p['vol_kind'] === 'ci' || $p['core'] === '') continue;
        if (!preg_match('/^(.+?)(?:\s+[-–—]|:)\s+(\S.*)$/u', $p['core'], $mm)) continue;
        $pre = mc_lib_clean($mm[1]); $sub = mc_lib_clean($mm[2]);
        if ($pre === '' || $sub === '') continue;
        $k = mc_lib_name_key($pre);
        if ($k === '' || $k === mc_lib_name_key($p['series'])) continue;
        $fa = $p['authors'] ?: ($folderParse['authors'] ?? []);
        $dash[$i] = [$pre, $sub, $k, $authKeys($fa), $fa];
        $dashAuth[$k] = ($dashAuth[$k] ?? []) + $authKeys($fa);
    }
    //   - 이미 그 제목의 권으로 들어가 있던 파일은 유지(같은 폴더의 다른 권을 지워도 혼자 떨어져 나가지 않게)
    $prevKey = [];
    $pst = $db->prepare('SELECT v.path, s.name_key FROM volumes v JOIN series s ON s.id=v.series_id WHERE v.bidx=? AND v.folder=? AND v.vol_no IS NOT NULL');
    $pst->execute([(int)$bidx, $relFolder]);
    foreach ($pst->fetchAll() as $r) $prevKey[$r['path']] = $r['name_key'];
    foreach ($parsed as $i => $p) {
        if ($p['vol_kind'] !== 'tail' || $p['series'] === '') continue;
        $k = mc_lib_name_key($p['series']);
        if (count($groupNums[$k] ?? []) >= 2 || isset($folderKeys[$k])) continue;
        if (($prevKey[$relFolder . '/' . $files[$i][0]] ?? null) === $k) continue;
        if ($hasNumbered($k)) continue;
        // ★ 같은 제목 작품이 있고 작가가 겹침("7 Days by A" + 혼자 있는 "7 Days 2 by A") → 그 작품의 권
        $fa = $p['authors'] ?: ($folderParse['authors'] ?? []);
        if ($fa && mc_lib_series_by_author($db, $bidx, $k, $fa, $relFolder . '/' . $files[$i][0])) continue;
        if ($fa && isset($dashAuth[$k]) && array_intersect_key($authKeys($fa), $dashAuth[$k])) continue; // ★ (2026-10-31) 같은 폴더의 "제목 - 부제"(같은 작가)와 한 작품
        if ($fa && mc_lib_family_by_author($db, $bidx, $k, $fa, 'dash', $relFolder . '/' . $files[$i][0])) continue; // ★ (2026-10-31) 다른 폴더의 같은 작가 "제목 - 부제" 작품
        $rejKeys[$k] = true;
        $parsed[$i]['series'] = $p['tail_title'] !== '' ? $p['tail_title'] : $p['series'];
        $parsed[$i]['vol'] = null;
        $parsed[$i]['label'] = '';
        $parsed[$i]['vol_kind'] = '';
    }

    // ★ (2026-10-29) "제목 01 부제", "제목 02 다른 부제" — 권 표시 없이 가운데 번호 + 부제. 같은 앞부분에 서로 다른 번호가 2개 이상이거나
    //   앞부분이 폴더 이름과 같거나, 이미 그 제목의 권이었던 파일만 권으로 인정(혼자 있는 "2 Fast 2 Furious" 같은 제목은 그대로)
    $mid = []; $midNums = [];
    foreach ($parsed as $i => $p) {
        if ($p['vol'] !== null || $p['vol_kind'] === 'ci' || $p['core'] === '') continue;
        if (!preg_match('/^(.+?)\s+(?:제\s*)?(\d{1,4})\s+(?:[-~:–—]\s*)?(\S.*)$/u', $p['core'], $mm)) continue;
        $pre = mc_lib_clean($mm[1]);
        if ($pre === '') continue;
        $k = mc_lib_name_key($pre);
        $mid[$i] = [$pre, $mm[2], mc_lib_clean($mm[3]), $k];
        $midNums[$k][(string)(float)$mm[2]] = true;
    }
    foreach ($mid as $i => [$pre, $num, $sub, $k]) {
        // 한 자리 숫자("Love 2 Hate")는 제목 일부일 때가 많아 비슷한 이름끼리만으로는 인정하지 않음 — 01·12 처럼 0으로 시작하거나 두 자리 이상일 때만
        $strongNum = strlen($num) >= 2;
        $fa = $parsed[$i]['authors'] ?: ($folderParse['authors'] ?? []);
        $ok = ($strongNum && (count($midNums[$k]) + count($groupNums[$k] ?? [])) >= 2) || isset($folderKeys[$k]) || (($prevKey[$relFolder . '/' . $files[$i][0]] ?? null) === $k) || $hasNumbered($k)
            || ($fa && mc_lib_series_by_author($db, $bidx, $k, $fa, $relFolder . '/' . $files[$i][0]));
        if (!$ok) { $rejKeys[$k] = true; continue; }
        $parsed[$i]['series'] = $pre;
        $parsed[$i]['vol'] = (float)$num;
        $parsed[$i]['label'] = $num;
        $parsed[$i]['vol_kind'] = 'mid';
        $parsed[$i]['subtitle'] = $sub;
    }

    // ★ (2026-10-31) "제목 - 부제" 판단 — 근거: 같은 폴더에 같은 앞부분의 번호 있는 권(작가 겹침) / 앞부분이 폴더 이름 / 이미 등록된 그 제목의 번호 있는 권(작가 겹침)
    //   / 다른 폴더의 같은 작가 "제목 N" 작품(혼자라 권 인정 못 받은 것) — 그 파일은 스캔 끝 재판단에서 이 작품의 권으로 합쳐짐
    foreach ($dash as $i => [$pre, $sub, $k, $fak, $faNames]) {
        if ($parsed[$i]['vol'] !== null) continue;
        $ok = isset($folderKeys[$k]);
        if (!$ok && $fak) {
            foreach ($parsed as $j => $q) {
                if ($j === $i || $q['vol'] === null || mc_lib_name_key($q['series']) !== $k) continue;
                if (array_intersect_key($fak, $authKeys($q['authors'] ?: ($folderParse['authors'] ?? [])))) { $ok = true; break; }
            }
        }
        if (!$ok && $fak && $hasNumbered($k)) $ok = (bool)mc_lib_series_by_author($db, $bidx, $k, $faNames, $relFolder . '/' . $files[$i][0]);
        if (!$ok && $fak) $ok = (bool)mc_lib_family_by_author($db, $bidx, $k, $faNames, 'num', $relFolder . '/' . $files[$i][0]); // ★ 다른 폴더의 같은 작가 "제목 N"(혼자라 권 인정 못 받은 것)
        if (!$ok) { $rejKeys[$k] = true; continue; } // 나중에 다른 폴더에 그 제목 권이 생기면 다시 판단
        $parsed[$i]['series'] = $pre;
        $parsed[$i]['subtitle'] = $sub;
    }

    // ★ (2026-11-05) 묶음 보강 — 지금까지의 판단으로 권 번호가 없는(혼자 떨어진) 파일만 대상. 근거(같은 작가 + 같은 제목의 다른 권)가 있을 때만 묶고, 없으면 그대로 두고 재판단 후보로(rejKeys)
    //   이미 권으로 인정된 파일·ComicInfo 로 정해진 파일·관리자가 직접 옮긴 권(아래 manual)은 건드리지 않음
    $authOverlap = function ($a, $b) use ($authKeys) { return $a && $b && (bool)array_intersect_key($authKeys($a), $authKeys($b)); };
    $faOf = function ($i) use (&$parsed, $folderParse) { return $parsed[$i]['authors'] ?: ($folderParse['authors'] ?? []); };
    $wordKeys = function ($s) { $o = []; foreach (preg_split('/\s+/u', trim((string)$s)) as $w) { $k = mc_lib_name_key($w); if ($k !== '') $o[] = $k; } return $o; };
    //  ① "제목: 3 부제"(콜론 바로 뒤 번호) · "제목1 부제"(영어 낱말에 붙은 번호) — 펜닐 제보
    //     예) "Netorare Onna Bas-bu Case： 2 Ishiki Takai Kouhai ｜ …", "Saimin Idol Gakuen1 Noah ｜ …"
    //     위 '제목 01 부제' 규칙은 한 자리 번호를 제목 일부("Love 2 Hate")로 보아 넘겼음 → 콜론·붙은 번호처럼 권 표시가 분명한 모양만,
    //     같은 앞부분에 서로 다른 번호가 2개 이상 + 작가가 겹칠 때(또는 같은 작가의 번호 있는 그 작품이 이미 있을 때)만 권으로 인정
    $cand = []; $candNums = [];
    foreach ($parsed as $i => $p) {
        if ($p['vol'] !== null || $p['vol_kind'] === 'ci' || $p['core'] === '') continue;
        if (preg_match('/^(.*?\S)\s*:\s*(\d{1,3})\s+(\S.*)$/u', $p['core'], $mm)) $kind = 'colon';
        elseif (preg_match('/^(.*?\p{Latin}{3,})(\d{1,3})\s+(\S.*)$/u', $p['core'], $mm) && !preg_match('/\d$/u', $mm[1])) $kind = 'glued';
        else continue;
        if (!preg_match('/\p{L}/u', $mm[3])) continue; // 번호 뒤가 숫자·기호뿐이면 권 표시가 아님("Ranma1 2", "Title: 1 2")
        $pre = mc_lib_clean_title(preg_replace('/\s*:\s*$/u', '', $mm[1]));
        $k = mc_lib_name_key($pre);
        if ($pre === '' || $k === '') continue;
        $cand[$i] = [$pre, $mm[2], mc_lib_clean_title($mm[3]), $k];
        $candNums[$k][(string)(float)$mm[2]][] = $i;
    }
    $accepted = []; // 이번에 인정한 묶음: 키 => [앞부분 이름, 낱말 키들, 번호 => true, 작가 목록]
    foreach ($cand as $i => [$pre, $num, $sub, $k]) {
        $fa = $faOf($i);
        $ok = isset($folderKeys[$k]);
        if (!$ok && $fa) { // 같은 폴더: 같은 앞부분의 다른 번호 파일 중 작가가 겹치는 것
            foreach ($candNums[$k] as $n2 => $idx) {
                if ((string)$n2 === (string)(float)$num) continue; // 배열 키 "2" 는 정수 2 가 되므로 문자열로 비교(같은 번호 = 자기 자신·사본은 근거 아님)
                foreach ($idx as $j) if ($j !== $i && $authOverlap($fa, $faOf($j))) { $ok = true; break 2; }
            }
        }
        if (!$ok && $fa) { // 같은 폴더에 이미 권으로 인정된 같은 제목(작가 겹침)
            foreach ($parsed as $j => $q) if ($j !== $i && $q['vol'] !== null && mc_lib_name_key($q['series']) === $k && $authOverlap($fa, $faOf($j))) { $ok = true; break; }
        }
        if (!$ok && $fa && $hasNumbered($k)) $ok = (bool)mc_lib_series_by_author($db, $bidx, $k, $fa, $relFolder . '/' . $files[$i][0]); // 다른 폴더의 같은 작품
        if (!$ok) { $rejKeys[$k] = true; continue; }
        $parsed[$i]['series'] = $pre;
        $parsed[$i]['vol'] = (float)$num;
        $parsed[$i]['label'] = $num;
        $parsed[$i]['vol_kind'] = 'mid';
        $parsed[$i]['subtitle'] = $sub;
        if (!isset($accepted[$k])) $accepted[$k] = [$pre, $wordKeys($pre), [], []];
        $accepted[$k][2][(string)(float)$num] = true;
        $accepted[$k][3] = array_merge($accepted[$k][3], $fa);
    }
    //  ② 위에서 인정한 묶음과 앞 낱말·끝 낱말이 같고 가운데 낱말만 더 있는 이름(같은 폴더·같은 작가·아직 없는 번호)
    //     예) "Netorare Onna Bas-bu Saimin × Choukyou Case： 1 …" → "Netorare Onna Bas-bu Case" 의 1권
    //     묶음 쪽 이름이 3낱말 이상이고, 그 낱말들이 순서대로 다 들어 있을 때만(앞부분 키만 같은 다른 제목과 헷갈리지 않게)
    //     기준 = 이번에 인정한 묶음 + 같은 폴더에서 이미 권으로 인정된 파일(앞 규칙이 먼저 인정한 경우 — 다시 스캔해도 같은 결과가 나오게)
    foreach ($parsed as $j => $q) {
        if ($q['vol'] === null || $q['series'] === '') continue;
        $qk = mc_lib_name_key($q['series']);
        if ($qk === '') continue;
        if (!isset($accepted[$qk])) $accepted[$qk] = [$q['series'], $wordKeys($q['series']), [], []];
        $accepted[$qk][2][(string)(float)$q['vol']] = true;
        $accepted[$qk][3] = array_merge($accepted[$qk][3], $faOf($j));
    }
    foreach ($cand as $i => [$pre, $num, $sub, $k]) {
        if ($parsed[$i]['vol'] !== null || isset($accepted[$k])) continue;
        $fa = $faOf($i); if (!$fa) continue;
        $mine = $wordKeys($pre);
        foreach ($accepted as $ak => [$apre, $aw, $anums, $aauth]) {
            if (count($aw) < 3 || count($mine) <= count($aw) || isset($anums[(string)(float)$num])) continue;
            if ($mine[0] !== $aw[0] || end($mine) !== end($aw) || !$authOverlap($fa, $aauth)) continue;
            $pos = 0; foreach ($mine as $w) { if ($pos < count($aw) && $w === $aw[$pos]) $pos++; }
            if ($pos !== count($aw)) continue;
            $parsed[$i]['series'] = $apre;
            $parsed[$i]['vol'] = (float)$num;
            $parsed[$i]['label'] = $num;
            $parsed[$i]['vol_kind'] = 'mid';
            $parsed[$i]['subtitle'] = $sub;
            $accepted[$ak][2][(string)(float)$num] = true;
            unset($rejKeys[$k]);
            break;
        }
    }
    //  ③ "로마자 원제 한글 제목"(구분 기호 없이 붙여 쓴 두 제목) — 한글 제목이 같은 작가의 번호 있는 작품과 같을 때만 그 작품으로(번호가 없으면 1권으로 보임)
    //     예) "Saimin Charao Otoko netorare Setokai 최면 양아치남과 네토라레 학생회 by Sunagimo Dx" + "최면 양아치남과 네토라레 학생회 02 by Sunagimo Dx"
    //     근거가 없으면 그대로(로마자+한글 이름 그대로 한 작품) — "SPY×FAMILY 스파이 패밀리" 처럼 원래 그렇게 쓰는 제목은 바뀌지 않음
    foreach ($parsed as $i => $p) {
        if ($p['vol'] !== null || $p['vol_kind'] === 'ci' || $p['series'] === '') continue;
        if (!preg_match('/^([^\p{Hangul}]*\p{Latin}[^\p{Hangul}]*?)\s+(\p{Hangul}.*)$/u', $p['series'], $mm)) continue;
        if (count(preg_split('/\s+/u', trim($mm[1]))) < 2 || preg_match_all('/\p{Hangul}/u', $mm[2]) < 2) continue;
        $kor = mc_lib_clean_title($mm[2]); $hk = mc_lib_name_key($kor);
        $fa = $faOf($i);
        if ($hk === '' || !$fa) continue;
        $ok = false;
        foreach ($parsed as $j => $q) if ($j !== $i && $q['vol'] !== null && mc_lib_name_key($q['series']) === $hk && $authOverlap($fa, $faOf($j))) { $ok = true; break; }
        if (!$ok && $hasNumbered($hk)) $ok = (bool)mc_lib_series_by_author($db, $bidx, $hk, $fa, $relFolder . '/' . $files[$i][0]);
        if (!$ok) { $rejKeys[$hk] = true; continue; } // 나중에 다른 폴더에 그 한글 제목의 권이 생기면 다시 판단
        $parsed[$i]['series'] = $kor;
    }

    mc_lib_begin_write($db);
    try {
        $count = 0;
        $moved = false;
        $movedFrom = []; // 옛 시리즈 id → 새 시리즈 id (다시 판단되어 옮겨진 권)
        $touchedSids = []; // ★ (2026-10-30) 이 폴더의 권이 속한 시리즈 — 끝에서 자동 작가 다시 계산
        foreach ($files as $i => [$fn, $ext, $full]) {
            $rel = $relFolder . '/' . $fn;
            $p = $parsed[$i];
            $seriesName = $p['series'];
            $authors = $p['authors'];
            $groups = $p['groups'] ?? []; // ★ (2026-11-02 01:53) 그룹(서클) — 작가 목록 중 그룹인 이름
            // 파일명에 제목이 없으면(01.zip 등) 폴더명을 시리즈로
            if ($seriesName === '' && $folderParse) {
                $seriesName = $folderParse['series'] !== '' ? $folderParse['series'] : $folderName;
                if (!$authors) { $authors = $folderParse['authors']; $groups = $folderParse['groups'] ?? []; }
            }
            // 파일명에 작가가 없으면 폴더명의 [작가] 사용
            if (!$authors && $folderParse && $folderParse['authors']) { $authors = $folderParse['authors']; $groups = $folderParse['groups'] ?? []; }
            if ($seriesName === '') $seriesName = $fn;
            $size = (int)@filesize($full);
            $mtime = (int)@filemtime($full);

            $mt = $meta[$i];
            $sub = mb_substr((string)$p['subtitle'], 0, 200, 'UTF-8');
            $selVol->execute([(int)$bidx, $rel]);
            $row = $selVol->fetch();
            if ($row) {
                if ((int)$row['manual'] === 1) {
                    $sid = (int)$row['series_id'];
                    $mdk = mc_lib_dup_key(['vol_no' => $row['vol_no'], 'vol_label' => $row['vol_label'], 'subtitle' => $row['subtitle']]); // 묶음 키 규칙이 바뀌어도 맞게
                    if (!((int)$row['missing'] === 0 && (int)$row['size'] === $size && (int)$row['mtime'] === $mtime && (int)$row['pages'] === (int)$mt['pages'] && (string)$row['info'] === (string)$mt['info'] && (string)$row['probe'] === (string)$mt['probe'] && (string)$row['dkey'] === $mdk)) {
                        $updSeen->execute([$size, $mtime, $now, $mt['pages'], $mt['info'], $mt['probe'], $mdk, (int)$row['id']]);
                    }
                } else {
                    $sid = mc_lib_series_id_for($db, $bidx, $seriesName, $now, $authors, $p['vol'] !== null, $rel);
                    if ((int)$row['series_id'] !== (int)$sid) { $moved = true; if ((int)$row['series_id'] > 0 && $sid) $movedFrom[(int)$row['series_id']] = (int)$sid; }
                    $dk = mc_lib_dup_key(['vol_no' => $p['vol'], 'vol_label' => $p['label'], 'subtitle' => $sub]); $rt = mc_lib_root_of($rel);
                    if (!$same($row, $sid, $p['vol'], $p['label'], $size, $mtime, $sub, $mt, $dk, $rt)) {
                        $updAuto->execute([$sid, $p['vol'], $p['label'], $size, $mtime, $now, $sub, $mt['pages'], $mt['info'], $mt['probe'], $dk, $rt, (int)$row['id']]);
                        // ★ fix66 자동 판단으로 다른 작품·다른 권으로 가면 예전 자리에서 정한 사본 순서(corder)는 지움(새 자리에서 맨 앞으로 끼어들지 않게)
                        if ((int)$row['series_id'] !== (int)$sid || (string)$row['dkey'] !== (string)$dk) $db->prepare('UPDATE volumes SET corder=0 WHERE id=? AND corder<>0')->execute([(int)$row['id']]);
                    }
                }
            } else {
                $sid = mc_lib_series_id_for($db, $bidx, $seriesName, $now, $authors, $p['vol'] !== null, $rel);
                $insVol->execute([(int)$bidx, $rel, $relFolder, $fn, $ext, $sid, $p['vol'], $p['label'], $size, $mtime, $mtime ?: $now, $now, $sub, $mt['pages'], $mt['info'], $mt['probe'], mc_lib_dup_key(['vol_no' => $p['vol'], 'vol_label' => $p['label'], 'subtitle' => $sub]), mc_lib_root_of($rel)]);
            }
            if ($sid) {
                $ak = $sid . '|' . implode("\n", $authors);
                if ($authors && !isset($authorDone[$ak])) { $authorDone[$ak] = true; mc_lib_apply_auto_authors($db, $sid, $authors, $groups); }
                $touchedSids[(int)$sid] = true;
                if ($mt['info'] !== '') mc_lib_apply_comicinfo($db, $sid, json_decode($mt['info'], true) ?: []);
                if ($p['completed'] || ($folderParse && $folderParse['completed'])) {
                    $db->prepare("UPDATE series SET status='completed' WHERE id=? AND (manual & 4)=0 AND status=''")->execute([$sid]);
                }
            }
            $count++;
        }
        // 이 폴더에서 사라진 권은 missing 표시(기록은 유지 → 파일이 돌아오면 그대로 복구)
        // (시각 비교 대신 이번에 본 경로 목록과 비교 — 같은 1초 안에 두 번 읽어도 정확하게)
        $seenPaths = [];
        foreach ($files as $f) $seenPaths[$relFolder . '/' . $f[0]] = true;
        $ast = $db->prepare('SELECT id, path FROM volumes WHERE bidx=? AND folder=? AND +missing=0'); // +missing: 폴더 색인(ix_vol_folder)을 쓰게
        $ast->execute([(int)$bidx, $relFolder]);
        $miss = $db->prepare('UPDATE volumes SET missing=1 WHERE id=?');
        foreach ($ast->fetchAll() as $r) if (!isset($seenPaths[$r['path']])) $miss->execute([(int)$r['id']]);
        // 다시 판단되어 비게 된 옛 시리즈에 관리자가 넣은 정보(작가·장르·태그·상태·설명)가 있으면 새 시리즈로 옮김
        foreach ($movedFrom as $old => $new) { mc_lib_carry_series_info($db, $old, $new); mc_lib_refresh_auto_authors($db, $old); }
        // ★ (2026-10-30) 이 폴더의 권이 속한 시리즈도 자동 작가 다시 계산 — 파일명 해석이 바뀌어 더 이상 나오지 않는 자동 작가("N/A" 가 "N"·"A" 로 잘못 나뉘었던 것 등)를 뗌
        //   (관리자가 직접 넣은 작가·작가를 직접 정한 시리즈는 그대로 — mc_lib_refresh_auto_authors)
        foreach (array_keys($touchedSids) as $tsid) if (!isset($movedFrom[$tsid])) mc_lib_refresh_auto_authors($db, $tsid);
        if ($moved) mc_lib_cleanup_empty($db); // 다시 판단되어 시리즈가 바뀐 권이 있으면 빈 자동 시리즈 정리
        // 폴더 서명 저장 — pending 2=시간이 모자라 zip 내부 확인 남음 → 다음에 건너뛰지 않음
        // rkeys={제목 키: 그때 그 제목의 권 수} — 스캔 끝에 권 수가 달라진 제목만 다시 판단(★ 매번 다시 읽지 않게)
        $rk = [];
        foreach (array_slice(array_keys($rejKeys), 0, 50) as $k) $rk[$k] = mc_lib_key_vol_count($db, $bidx, (string)$k);
        $rkeysJson = $rk ? json_encode($rk, JSON_UNESCAPED_UNICODE) : '';
        $db->prepare('INSERT INTO folders(bidx, path, sig, pending, rkeys, seen_at) VALUES(?,?,?,?,?,?) ON CONFLICT(bidx, path) DO UPDATE SET sig=excluded.sig, pending=excluded.pending, rkeys=excluded.rkeys, seen_at=excluded.seen_at')
           ->execute([(int)$bidx, $relFolder, $fsig, $probeLeft ? 2 : 0, $rkeysJson, $now]);
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[library] index_folder failed: ' . $e->getMessage());
        mc_lib_index_stat('failed'); // ★ (2026-10-30) 스캔 끝의 '없음' 처리를 막고 다음 빠른 스캔에서 다시
        return 0;
    }
    return $count;
}

/**
 * 전체 스캔(이어서 하기 방식) — 한 번 호출에 $budget 초까지만 처리하고 남은 폴더는 meta 에 보관
 * @return array [done(bool), folders_left(int), volumes(int)]
 */
/** 이번 요청에서 다시 읽은/건너뛴 폴더 수(스캔 진행 표시용) */
function mc_lib_index_stat($what = null, $reset = false) {
    static $st = ['indexed' => 0, 'skipped' => 0, 'failed' => 0]; // ★ (2026-10-30) failed: 폴더 저장 실패(DB 잠김 등)
    if ($reset) { $out = $st; $st = ['indexed' => 0, 'skipped' => 0, 'failed' => 0]; return $out; }
    if ($what !== null && isset($st[$what])) $st[$what]++;
    return $st;
}

/**
 * 전체 스캔(이어서 하기 방식) — 한 번 호출에 $budget 초까지만 처리하고 남은 폴더는 meta 에 보관
 * ★ (2026-10-29) 개선
 *   - 빠른 스캔($force=false): 파일 이름 목록이 그대로인 폴더는 건너뜀 / 전체 다시 스캔($force=true): 모든 폴더를 다시 판단
 *   - 동시에 한 번만: 시작할 때 받은 token 으로만 이어서 진행(다른 관리자가 진행 중이면 busy). 60초 넘게 진행이 없으면 끝난 것으로 봄
 *   - 모든 폴더를 본 뒤: 권으로 인정 못 했던 후보의 제목이 이제 다른 폴더에 번호 있는 권으로 있으면 그 폴더만 다시 판단
 *     → 이번 스캔에서 못 본 폴더(지워짐 등)의 권은 '없음'(읽지 못한 폴더가 있었으면 하지 않음)
 * @return array [done, folders_left, volumes, indexed, skipped, token] 또는 [busy=>true]
 */
function mc_lib_scan_step($bidx, $budget = 8.0, $restart = false, $force = false, $token = '') {
    $db = mc_lib_db();
    if (!$db) return ['done' => true, 'folders_left' => 0, 'volumes' => 0, 'error' => 'nodb'];
    $base = mc_lib_base($bidx);
    if (!$base) return ['done' => true, 'folders_left' => 0, 'volumes' => 0, 'error' => 'nobase'];
    $b = (int)$bidx;
    $qk = 'scan_queue_' . $b; $sk = 'scan_start_' . $b; $fk = 'scan_fail_' . $b; $rk = 'scan_recheck_' . $b;
    $lk = 'scan_lock_' . $b; $mk = 'scan_force_' . $b; $pk = 'scan_phase_' . $b;
    $get = $db->prepare('SELECT v FROM meta WHERE k=?');
    $set = $db->prepare('INSERT OR REPLACE INTO meta(k, v) VALUES(?, ?)');
    // ★ (2026-10-30) 읽은 뒤 커서를 닫음 — 열어 두면 이 단계 내내 DB 를 옛 시점으로 붙잡아, 그 사이 다른 요청(뷰어 읽음 기록·편집·중지)이
    //   한 번이라도 쓰면 남은 폴더 저장이 모두 즉시 실패(database is locked)하던 문제
    $val = function ($k) use ($get) { $get->execute([$k]); $v = $get->fetchColumn(); $get->closeCursor(); return $v === false ? '' : (string)$v; };
    $now = time();
    // 잠금 "token|시각" — 한 문장(UPDATE … WHERE)으로 잡아서 동시에 눌러도 하나만 성공
    $db->prepare("INSERT OR IGNORE INTO meta(k, v) VALUES(?, '')")->execute([$lk]);
    if ($restart) {
        $token = bin2hex(random_bytes(8));
        $acq = $db->prepare("UPDATE meta SET v=? WHERE k=? AND (v='' OR CAST(substr(v, 18) AS INTEGER) < ?)");
        $acq->execute([$token . '|' . $now, $lk, $now - 60]);
        if ($acq->rowCount() !== 1) return ['done' => false, 'busy' => true, 'folders_left' => 0, 'volumes' => 0];
    } else {
        $token = preg_replace('/[^0-9a-f]/', '', (string)$token);
        if (strlen($token) !== 16) return ['done' => false, 'busy' => true, 'folders_left' => 0, 'volumes' => 0];
        $ref = $db->prepare("UPDATE meta SET v=? WHERE k=? AND substr(v, 1, 16)=?");
        $ref->execute([$token . '|' . $now, $lk, $token]);
        if ($ref->rowCount() !== 1) return ['done' => false, 'busy' => true, 'folders_left' => 0, 'volumes' => 0];
    }
    if ($restart) {
        // 시작 시각을 마지막으로 본 시각보다 뒤로(초 단위라 같은 초면 '이번 스캔에 못 본 폴더'를 가려낼 수 없음) — 최대 2초 대기
        $mx = (int)$db->query('SELECT MAX(seen_at) FROM folders WHERE bidx=' . $b)->fetchColumn();
        $wait = microtime(true) + 2.0;
        while (time() <= $mx && microtime(true) < $wait) usleep(100000);
        $set->execute([$qk, json_encode([''])]);
        $set->execute([$sk, (string)time()]);
        $set->execute([$fk, '0']);
        $set->execute([$rk, '{}']);
        $set->execute([$mk, $force ? '1' : '0']);
        $set->execute([$pk, '1']);
    }
    $queue = json_decode($val($qk), true); if (!is_array($queue)) $queue = [];
    $recheck = json_decode($val($rk), true); if (!is_array($recheck)) $recheck = [];
    $force = $val($mk) === '1';
    $phase = (int)$val($pk);
    mc_lib_index_stat(null, true);
    $t0 = microtime(true);
    $vols = 0;
    // 1단계: 폴더 차례로(하위 폴더는 뒤에 추가)
    while ($queue && (microtime(true) - $t0) < $budget) {
        $rel = (string)array_shift($queue);
        $abs = $base . $rel;
        $items = @scandir($abs, SCANDIR_SORT_NONE);
        if ($items !== false) {
            $rpCur = realpath($abs); // ★ (2026-11-08 fix85) 링크 고리 판단용
            // ★ (2026-11-09 10:40) 지금 폴더까지 거쳐 온 모든 폴더의 실제 경로(콘텐츠 폴더 맨 위 포함) — fix85 는 지금 폴더의 '실제 경로상 위'만 봐서
            //   형제끼리 서로 가리키는 링크(A/toB→B, B/toA→A)는 /A/toB/toA/toB/… 로 경로 길이 한계까지 내려갔음(같은 책이 수십 권). 링크는 폴더에 들어갈 때만 보므로 링크 없는 폴더는 비용 없음
            $rpAnc = null;
            foreach ($items as $fn) {
                if ($fn === '' || $fn[0] === '.' || $fn === '@eaDir' || $fn === 'tmp' || strpos($fn, 'rclone_') !== false) continue;
                if (is_dir($abs . '/' . $fn)) {
                    // ★ (2026-11-08 fix85) 자기 자신이나 위 폴더를 가리키는 링크(고리)는 건너뜀 — 경로 길이 한계까지 같은 폴더를 거듭 내려가
                    //   파일 하나가 수십 권으로 들어갔음(고리가 콘텐츠 폴더 맨 위를 가리키면 서재 전체가 복제). 다른 곳을 가리키는 링크 폴더는 그대로 읽음
                    if (is_link($abs . '/' . $fn)) {
                        $rpLink = realpath($abs . '/' . $fn);
                        if ($rpLink === false || ($rpCur !== false && strpos(rtrim(str_replace('\\', '/', $rpCur), '/') . '/', rtrim(str_replace('\\', '/', $rpLink), '/') . '/') === 0)) continue;
                        if ($rpAnc === null) { // 이 폴더에서 링크를 처음 만났을 때 한 번만 계산
                            $rpAnc = [];
                            $__acc = '';
                            $__r0 = realpath($base);
                            if ($__r0 !== false) $rpAnc[] = rtrim(str_replace('\\', '/', $__r0), '/') . '/';
                            foreach (explode('/', trim($rel, '/')) as $__seg) {
                                if ($__seg === '') continue;
                                $__acc .= '/' . $__seg;
                                $__r = realpath($base . $__acc);
                                if ($__r !== false) $rpAnc[] = rtrim(str_replace('\\', '/', $__r), '/') . '/';
                            }
                        }
                        $__lk = rtrim(str_replace('\\', '/', $rpLink), '/') . '/';
                        $__loop = false;
                        foreach ($rpAnc as $__a) { if (strpos($__a, $__lk) === 0) { $__loop = true; break; } } // 거쳐 온 폴더(또는 그 위)를 가리키면 고리
                        if ($__loop) continue;
                    }
                    $queue[] = $rel . '/' . $fn;
                }
            }
            $vols += mc_lib_index_folder($bidx, $rel, 4.0, $force);
        } else {
            $set->execute([$fk, '1']); // 읽지 못한 폴더가 있음(권한·네트워크 드라이브 끊김 등) → 이번 스캔은 '없음' 처리 안 함
        }
    }
    // 2단계 준비: 인정 못 했던 후보 제목이 이제 번호 있는 권으로 있는 폴더 고르기
    if (!$queue && $phase === 1) {
        // ★ 인정 못 했던 후보의 제목 권 수가 그때와 달라졌으면(다른 폴더에 그 제목 권이 생기거나 없어짐) 그 폴더만 다시 판단 — 판단은 index_folder 가
        $cnt = [];
        $fq = $db->prepare("SELECT path, rkeys FROM folders WHERE bidx=? AND rkeys<>''");
        $fq->execute([$b]);
        foreach ($fq->fetchAll() as $r) {
            $rkMap = json_decode($r['rkeys'], true); // ($rk 는 meta 키 이름이라 다른 이름 사용)
            if (!is_array($rkMap)) continue;
            if (array_keys($rkMap) === range(0, count($rkMap) - 1)) { $recheck[$r['path']] = 1; continue; } // 예전 형식(키 목록) → 한 번 다시 판단해 새 형식으로
            foreach ($rkMap as $k => $was) {
                $k = (string)$k;
                if (!isset($cnt[$k])) $cnt[$k] = mc_lib_key_vol_count($db, $b, $k);
                if ($cnt[$k] !== (int)$was) { $recheck[$r['path']] = 1; break; }
            }
        }
        $phase = 2;
        $set->execute([$pk, '2']);
    }
    // 2단계: 고른 폴더 다시 판단
    if (!$queue && $phase === 2) {
        while ($recheck && (microtime(true) - $t0) < $budget) {
            $f = (string)array_key_first($recheck);
            unset($recheck[$f]);
            $vols += mc_lib_index_folder($bidx, $f, 4.0, true);
        }
    }
    $set->execute([$qk, json_encode(array_values($queue), JSON_UNESCAPED_UNICODE)]);
    $set->execute([$rk, json_encode($recheck ? $recheck : new stdClass(), JSON_UNESCAPED_UNICODE)]);
    $done = !$queue && $phase === 2 && !$recheck;
    $st = mc_lib_index_stat(null, true);
    if ($st['failed'] > 0) $set->execute([$fk, '1']); // ★ 저장에 실패한 폴더가 있으면 이번 스캔은 '없음' 처리 안 함(멀쩡한 권이 없음으로 바뀌지 않게)
    if ($done) {
        $start = (int)$val($sk);
        $failed = $val($fk) === '1';
        if ($start > 0 && !$failed && is_dir($base)) {
            // 이번 스캔에서 한 번도 보지 못한 폴더(지워짐 등)의 권 → '없음'(기록 유지, 다시 보이면 복구). 건너뛰는 폴더(rclone_·tmp)는 제외
            // ★ (2026-10-30) GLOB(대소문자 구분) — 스캔 대기열의 건너뛰기('tmp'·'rclone_', 대소문자 구분)와 같은 기준(LIKE 는 대소문자를 안 가려 'TMP' 폴더 권이 지워져도 '없음' 처리가 안 됐음)
            $excl = "folder NOT GLOB '*rclone_*' AND '/' || folder || '/' NOT GLOB '*/tmp/*'";
            $db->prepare("UPDATE volumes SET missing=1 WHERE bidx=? AND missing=0 AND $excl AND folder NOT IN (SELECT path FROM folders WHERE bidx=? AND seen_at >= ?)")
               ->execute([$b, $b, $start]);
            $db->prepare('DELETE FROM folders WHERE bidx=? AND seen_at < ?')->execute([$b, $start]);
        }
        // ★ (2026-10-30) 아무 작품에도 붙지 않은 작가·장르·태그 정리(자동완성·목록에 남지 않게)
        $db->exec('DELETE FROM terms WHERE id NOT IN (SELECT DISTINCT term_id FROM series_terms)');
        $set->execute(['scan_done_' . $b, (string)time()]);
        $db->prepare("UPDATE meta SET v='' WHERE k=? AND substr(v, 1, 16)=?")->execute([$lk, $token]);
    }
    return ['done' => $done, 'folders_left' => count($queue) + count($recheck), 'volumes' => $vols,
            'indexed' => $st['indexed'], 'skipped' => $st['skipped'], 'failed' => $st['failed'], 'token' => $token];
}

/** 스캔 중지(잠금 해제) — 시작한 token 으로만 */
function mc_lib_scan_stop($bidx, $token) {
    $db = mc_lib_db();
    if (!$db) return false;
    $token = preg_replace('/[^0-9a-f]/', '', (string)$token);
    if (strlen($token) !== 16) return false;
    $st = $db->prepare("UPDATE meta SET v='' WHERE k=? AND substr(v, 1, 16)=?");
    $st->execute(['scan_lock_' . (int)$bidx, $token]);
    return $st->rowCount() === 1;
}

// ============================================================
// ★ (2026-10-30) 서버 쪽 스캔 실행기 / 자동 스캔
//   - 이전: 브라우저가 8초짜리 요청을 끝날 때까지 이어서 보냄 → 한 번만 끊겨도(사생활 보호 화면 전환·자동 로그아웃·모바일 절전·통신) 실패
//   - 지금: 서버가 응답을 먼저 보내고 같은 요청 안에서 끝까지 진행(ignore_user_abort). 화면은 진행 상황(scan_status)만 읽음
//   - 중간에 서버가 끊겨도(재시작·시간 제한) 대기열은 DB 에 남아 있어, 다음 접속 때 이어서 진행(resume)
//   - 자동: 업로드·삭제 직후(15초 조용해진 뒤) 또는 마지막 스캔 뒤 설정한 시간(기본 10분)이 지나면, 로그인한 사람이 화면을 열 때 뒤에서 빠른 스캔
// ============================================================

/**
 * ★ (2026-10-30) 쓰기 트랜잭션 시작 — 첫 문장을 쓰기로 해서 시작할 때 쓰기 권한을 먼저 잡음(다른 요청이 쓰는 중이면 busy_timeout 만큼 기다림).
 * 그냥 beginTransaction() 후 읽기부터 하면, 읽은 뒤 쓰려는 사이 다른 요청(스캔 등)이 먼저 쓴 경우 기다리지도 않고 실패(database is locked)
 */
function mc_lib_begin_write(PDO $db) {
    mc_lib_terms_key_ready($db, true); // ★ (2026-11-02 03:04) 용어 비교 키 칸 준비(처음 한 번, 트랜잭션 밖에서)
    $db->beginTransaction();
    try { $db->exec("UPDATE meta SET v=v WHERE k='schema'"); }
    catch (\Throwable $e) { $db->rollBack(); throw $e; }
}

/** DB 존재 여부(라이브러리를 쓰지 않는 사이트에 DB 파일을 새로 만들지 않기 위해) */
function mc_lib_db_exists() {
    return is_file(__DIR__ . '/src/library.db');
}

/** ★ (2026-11-02 08:49) 사용자 삭제·탈퇴 때 그 사용자의 서재 읽기 기록도 지움(같은 아이디로 다시 가입하면 옛 기록이 보였음) */
function mc_lib_forget_user($user) {
    $user = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$user);
    if ($user === '' || !mc_lib_db_exists()) return;
    try { $db = mc_lib_db(); if ($db) $db->prepare('DELETE FROM reads WHERE user=?')->execute([$user]); } catch (\Throwable $e) { error_log('[library] forget_user: ' . $e->getMessage()); }
}

function mc_lib_meta_get(PDO $db, $k, $def = '') {
    $st = $db->prepare('SELECT v FROM meta WHERE k=?');
    $st->execute([(string)$k]);
    $v = $st->fetchColumn();
    return $v === false ? $def : (string)$v;
}
function mc_lib_meta_set(PDO $db, $k, $v) {
    $db->prepare('INSERT OR REPLACE INTO meta(k, v) VALUES(?, ?)')->execute([(string)$k, (string)$v]);
}

/** 자동 스캔 설정 [on(bool), min(분)] — 기본 켜짐·10분 */
function mc_lib_auto_cfg(PDO $db) {
    $c = json_decode(mc_lib_meta_get($db, 'auto_scan', ''), true);
    $on = is_array($c) && array_key_exists('on', $c) ? !empty($c['on']) : true;
    $min = is_array($c) ? (int)($c['min'] ?? 10) : 10;
    if (!in_array($min, [5, 10, 30, 60, 180, 360, 1440], true)) $min = 10;
    return ['on' => $on, 'min' => $min];
}

/** 업로드·삭제 등으로 "스캔 필요" 표시 (실제 스캔은 15초 조용해진 뒤 자동으로) */
function mc_lib_mark_want($bidx) {
    if (!mc_lib_available() || !mc_lib_db_exists()) return;
    try {
        $db = mc_lib_db();
        if ($db) mc_lib_meta_set($db, 'scan_want_' . (int)$bidx, (string)time());
    } catch (\Throwable $e) { error_log('[library] mark_want: ' . $e->getMessage()); }
}

/** 진행 상황 — live: 지금 누가 진행 중(60초 안에 갱신), pending: 끝나지 않은 스캔이 있음(멈췄으면 이어서 가능) */
function mc_lib_scan_status($bidx) {
    $db = mc_lib_db();
    if (!$db) return null;
    $b = (int)$bidx;
    $lock = mc_lib_meta_get($db, 'scan_lock_' . $b);
    $lt = $lock !== '' ? (int)substr($lock, 17) : 0;
    $st = json_decode(mc_lib_meta_get($db, 'scan_state_' . $b), true);
    return [
        'live' => $lock !== '' && time() - $lt <= 60,
        'pending' => $lock !== '',
        'state' => is_array($st) ? $st : null,
        'done_at' => (int)mc_lib_meta_get($db, 'scan_done_' . $b, '0'),
        'want' => (int)mc_lib_meta_get($db, 'scan_want_' . $b, '0'),
        'auto' => mc_lib_auto_cfg($db),
        'now' => time(),
    ];
}

/** 멈춘 스캔(잠금이 60초 넘게 갱신 안 됨) 이어받기 — 성공하면 새 token */
function mc_lib_scan_claim($bidx) {
    $db = mc_lib_db();
    if (!$db) return '';
    $tok = bin2hex(random_bytes(8));
    $now = time();
    $st = $db->prepare("UPDATE meta SET v=? WHERE k=? AND v<>'' AND CAST(substr(v, 18) AS INTEGER) < ?");
    $st->execute([$tok . '|' . $now, 'scan_lock_' . (int)$bidx, $now - 60]);
    return $st->rowCount() === 1 ? $tok : '';
}

/** 지금 자동 스캔을 돌릴지 — ['run' => ''|'quick'|'resume', 'next' => 몇 초 뒤 다시 확인(0=없음)] */
function mc_lib_auto_due($bidx) {
    $s = mc_lib_scan_status($bidx);
    if (!$s || $s['live']) return ['run' => '', 'next' => 0];
    if ($s['pending']) return ['run' => 'resume', 'next' => 0];
    $now = $s['now'];
    // 실패 직후 5분은 자동으로 다시 하지 않음(같은 오류 반복 방지)
    if ($s['state'] && ($s['state']['status'] ?? '') === 'failed' && $now - (int)($s['state']['updated'] ?? 0) < 300) return ['run' => '', 'next' => 0];
    if ($s['want'] > 0) {
        $age = $now - $s['want'];
        if ($age >= 15) return ['run' => 'quick', 'next' => 0];
        return ['run' => '', 'next' => 16 - max(0, $age)];
    }
    if ($s['auto']['on'] && $s['done_at'] > 0 && $now - $s['done_at'] >= $s['auto']['min'] * 60) return ['run' => 'quick', 'next' => 0];
    return ['run' => '', 'next' => 0];
}

/** 응답(JSON)을 먼저 끝까지 보내고, 이 요청은 서버에서 계속 실행(창을 닫거나 다른 화면으로 가도 계속) */
function mc_lib_detach_json(array $out) {
    @ignore_user_abort(true);
    @set_time_limit(120);
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close(); // 세션을 잡고 있지 않게
    if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');  // mod_deflate 가 응답을 붙잡지 않게
    @ini_set('zlib.output_compression', '0');
    $body = json_encode($out, JSON_UNESCAPED_UNICODE);
    while (ob_get_level() > 0) @ob_end_clean();
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Connection: close');
        header('Content-Length: ' . strlen($body));
    }
    echo $body;
    @flush();
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
}

/**
 * 스캔을 서버에서 끝까지 진행(한 단계 8초씩, 단계마다 시간 제한을 다시 걺)
 * @param string $mode 'quick' | 'full' | 'resume'(멈춘 스캔 이어받기)
 * @param string $by   'manual' | 'auto'
 * @param string $req  화면이 붙인 시작 요청 번호(진행 상황이 내가 시작한 스캔인지 구분)
 * @return bool 진행했으면 true(다른 곳에서 진행 중이면 false)
 */
function mc_lib_scan_run($bidx, $mode = 'quick', $by = 'auto', $maxSec = 3600, $req = '') {
    $db = mc_lib_db();
    if (!$db) return false;
    $b = (int)$bidx;
    try { $db->exec('PRAGMA busy_timeout = 15000'); } catch (\Throwable $e) {}
    $t0 = time();
    $sk = 'scan_state_' . $b; $qk = 'scan_stopreq_' . $b;
    $save = function (array $st) use ($db, $sk) {
        $st['updated'] = time();
        try { mc_lib_meta_set($db, $sk, json_encode($st, JSON_UNESCAPED_UNICODE)); } catch (\Throwable $e) {}
    };
    $acc = function (array &$st, $r) {
        $st['seen'] += (int)($r['indexed'] ?? 0) + (int)($r['skipped'] ?? 0);
        $st['changed'] += (int)($r['indexed'] ?? 0);
        $st['left'] = (int)($r['folders_left'] ?? 0);
        $st['failed'] = (int)($st['failed'] ?? 0) + (int)($r['failed'] ?? 0);
    };
    $r = null;
    try {
        if ($mode === 'resume') {
            $token = mc_lib_scan_claim($b);
            if ($token === '') return false;
            $prev = json_decode(mc_lib_meta_get($db, $sk), true);
            $st = is_array($prev) ? $prev : [];
            $st += ['mode' => 'quick', 'by' => $by, 'started' => $t0, 'seen' => 0, 'changed' => 0, 'left' => 0];
            $st['status'] = 'running'; $st['error'] = ''; $st['resumed'] = (int)($st['resumed'] ?? 0) + 1;
            $save($st);
        } else {
            @set_time_limit(120);
            $r = mc_lib_scan_step($b, 0.5, true, $mode === 'full', ''); // 첫 단계는 짧게 → 진행 상황이 바로 보이게
            if (!empty($r['busy'])) return false;
            $token = (string)$r['token'];
            $st = ['status' => 'running', 'mode' => $mode === 'full' ? 'full' : 'quick', 'by' => $by === 'manual' ? 'manual' : 'auto',
                   'started' => $t0, 'seen' => 0, 'changed' => 0, 'left' => 0, 'error' => '', 'resumed' => 0, 'req' => preg_replace('/[^a-z0-9]/', '', substr((string)$req, 0, 16))];
            $acc($st, $r);
            $save($st);
        }
    } catch (\Throwable $e) {
        error_log('[library] scan_run start: ' . $e->getMessage());
        return false;
    }
    $fails = 0;
    while (empty($r['done'])) {
        if (time() - $t0 > $maxSec) return true; // 너무 길면 여기서 멈춤 → 잠금이 60초 뒤 풀리고 다음 접속 때 이어서
        // 중지 요청(시각) — 이 실행기가 시작한 뒤에 온 요청만(앞서 남은 표시로 새 스캔이 멈추지 않게)
        // ★ (2026-10-30) 여기서 DB 잠김 예외가 나도 실행기가 잡히지 않은 오류로 끝나지 않게
        try { $_stopreq = (int)mc_lib_meta_get($db, $qk, '0'); } catch (\Throwable $e) { $_stopreq = 0; }
        if ($_stopreq >= $t0) {
            try { mc_lib_scan_stop($b, $token); mc_lib_meta_set($db, $qk, ''); } catch (\Throwable $e) {}
            $st['status'] = 'stopped'; $save($st);
            return true;
        }
        @set_time_limit(120);
        try {
            $r = mc_lib_scan_step($b, 3.0, false, false, $token); // 3초씩 — 중지 요청이 빨리 걸리게
            $fails = 0;
        } catch (\Throwable $e) {
            // DB 잠김 등 일시적인 오류는 잠깐 쉬고 다시(5번 연속이면 실패로 표시하고 잠금 해제)
            error_log('[library] scan_run step: ' . $e->getMessage());
            if (++$fails >= 5) {
                try { mc_lib_scan_stop($b, $token); } catch (\Throwable $e2) {}
                $st['status'] = 'failed'; $st['error'] = mb_substr($e->getMessage(), 0, 200, 'UTF-8'); $save($st);
                return true;
            }
            sleep(2);
            $r = null;
            continue;
        }
        if (!empty($r['busy'])) return true; // 잠금을 잃음(다른 곳에서 새로 시작) — 그쪽이 이어서 함
        $acc($st, $r);
        $save($st);
    }
    $st['status'] = 'done'; $st['finished'] = time(); $save($st);
    // 스캔 중에 새로 생긴 업로드 표시는 남겨 두고(다음 자동 스캔), 시작 전 표시만 지움
    try { $db->prepare("UPDATE meta SET v='0' WHERE k=? AND CAST(v AS INTEGER) <= ?")->execute(['scan_want_' . $b, $t0]); } catch (\Throwable $e) {}
    // 저장에 실패한 폴더가 있었으면 곧 다시(자동 빠른 스캔 — 바뀐 폴더만 다시 읽으므로 실패한 폴더만 다시 함)
    // ★ (2026-10-30) 같은 폴더가 계속 실패하면 끝없이 다시 돌지 않게 — 연속 3번까지만 곧바로 다시(그 뒤는 정해진 간격의 자동 스캔 때)
    try {
        $_fk = 'scan_failrun_' . $b;
        $_fr = !empty($st['failed']) ? (int)mc_lib_meta_get($db, $_fk, '0') + 1 : 0;
        mc_lib_meta_set($db, $_fk, (string)$_fr);
        if ($_fr > 0 && $_fr <= 3) mc_lib_meta_set($db, 'scan_want_' . $b, (string)time());
    } catch (\Throwable $e) {}
    return true;
}

/**
 * ★ (2026-10-31) 모든 콘텐츠 폴더(base_dirs) 차례로 스캔 — 작품 탐색이 '전체 폴더(통합)' 일 때의 빠른/전체 다시 스캔
 *   폴더마다 기존 mc_lib_scan_run 을 그대로 씀(폴더별 잠금·이어받기·중지 규칙 그대로). 진행 표시는 scan_all_state 에 한데 모음
 */
function mc_lib_scan_bidxs() {
    global $base_dirs;
    $out = [];
    foreach ((array)$base_dirs as $b => $d) if (mc_lib_base((int)$b)) $out[] = (int)$b;
    return $out;
}
function mc_lib_scan_all_run($mode = 'quick', $req = '') {
    $db = mc_lib_db();
    if (!$db) return false;
    $list = mc_lib_scan_bidxs();
    $t0 = time();
    $req = preg_replace('/[^a-z0-9]/', '', substr((string)$req, 0, 16));
    $st = ['status' => 'running', 'mode' => $mode === 'full' ? 'full' : 'quick', 'by' => 'manual', 'req' => $req, 'started' => $t0, 'list' => $list, 'cur' => -1, 'skipped' => []];
    $save = function () use ($db, &$st) { $st['updated'] = time(); try { mc_lib_meta_set($db, 'scan_all_state', json_encode($st, JSON_UNESCAPED_UNICODE)); } catch (\Throwable $e) {} };
    $save();
    foreach ($list as $b) {
        try { $stop = (int)mc_lib_meta_get($db, 'scan_all_stopreq', '0'); } catch (\Throwable $e) { $stop = 0; }
        if ($stop >= $t0) { $st['status'] = 'stopped'; $save(); return true; }
        $st['cur'] = $b; $save();
        $s = mc_lib_scan_status($b);
        if ($s && $s['live']) { $st['skipped'][] = $b; continue; } // 그 폴더는 다른 곳(자동 등)에서 진행 중 — 그쪽이 끝까지 함
        mc_lib_scan_run($b, $mode, 'manual', 3600, $req);
        $s = mc_lib_scan_status($b);
        if (($s['state']['status'] ?? '') === 'stopped') { $st['status'] = 'stopped'; $save(); return true; }
    }
    $st['status'] = 'done'; $st['cur'] = -1; $st['finished'] = time(); $save();
    return true;
}
/** 모든 폴더 스캔 진행 상황 — 화면(browse.php)이 폴더 하나일 때와 같은 모양으로 읽게 합쳐서 돌려줌 */
function mc_lib_scan_all_status() {
    $db = mc_lib_db();
    if (!$db) return null;
    $all = json_decode(mc_lib_meta_get($db, 'scan_all_state'), true);
    $all = is_array($all) ? $all : null;
    $live = false; $pending = false; $sum = ['seen' => 0, 'changed' => 0, 'left' => 0, 'failed' => 0]; $liveState = null;
    foreach (mc_lib_scan_bidxs() as $b) {
        $s = mc_lib_scan_status($b);
        if (!$s) continue;
        if ($s['live']) { $live = true; if (!$liveState) $liveState = $s['state']; }
        if ($s['pending']) $pending = true;
        $ps = $s['state'] ?: [];
        if ($all && ($ps['req'] ?? '') !== '' && ($ps['req'] ?? '') === ($all['req'] ?? '')) foreach ($sum as $k => $v) $sum[$k] += (int)($ps[$k] ?? 0);
    }
    $runnerAlive = $all && ($all['status'] ?? '') === 'running' && time() - (int)($all['updated'] ?? 0) <= 120;
    if ($all && ($runnerAlive || !$liveState || ($liveState['req'] ?? '') === ($all['req'] ?? ''))) {
        $state = ['status' => $all['status'], 'mode' => $all['mode'] ?? 'quick', 'by' => 'manual', 'req' => $all['req'] ?? '', 'started' => (int)($all['started'] ?? 0), 'finished' => (int)($all['finished'] ?? 0), 'updated' => (int)($all['updated'] ?? 0)] + $sum;
        if (!$runnerAlive && ($all['status'] ?? '') === 'running') $state['status'] = 'stopped'; // 실행기가 끊김(서버 재시작 등) — 폴더별 멈춘 스캔은 각자 이어받음
    } else {
        $state = $liveState; // 자동 스캔 등 폴더 하나가 진행 중
    }
    return ['live' => $live || $runnerAlive, 'pending' => $pending && !$live && !$runnerAlive, 'state' => $state, 'now' => time(), 'all' => true,
            'pending_bidx' => array_values(array_filter(mc_lib_scan_bidxs(), function ($b) { $s = mc_lib_scan_status($b); return $s && $s['pending'] && !$s['live']; }))];
}
/** 모든 폴더 스캔 중지 — 실행기가 다음 폴더로 안 넘어가고, 진행 중인 폴더도 멈춤 */
function mc_lib_scan_all_request_stop() {
    $db = mc_lib_db();
    if (!$db) return false;
    mc_lib_meta_set($db, 'scan_all_stopreq', (string)time());
    foreach (mc_lib_scan_bidxs() as $b) { $s = mc_lib_scan_status($b); if ($s && $s['pending']) mc_lib_scan_request_stop($b); }
    return true;
}

/** 중지 요청 — 진행 중인 실행기가 다음 단계 전에 멈춤. 실행기가 없으면(멈춘 스캔) 바로 잠금 해제 */
function mc_lib_scan_request_stop($bidx) {
    $db = mc_lib_db();
    if (!$db) return false;
    $b = (int)$bidx;
    $s = mc_lib_scan_status($b);
    mc_lib_meta_set($db, 'scan_stopreq_' . $b, (string)time()); // 시작 직후(아직 진행 표시 전)에 눌러도 걸리게 시각으로 남김
    if (!$s['pending'] || $s['live']) return true;
    $db->prepare("UPDATE meta SET v='' WHERE k=?")->execute(['scan_lock_' . $b]);
    $st = $s['state'] ?: [];
    $st['status'] = 'stopped'; $st['updated'] = time();
    mc_lib_meta_set($db, 'scan_state_' . $b, json_encode($st, JSON_UNESCAPED_UNICODE));
    return true;
}

/** 로그인한 화면에서 넣는 자동 스캔 확인 스크립트(필요할 때만 요청 1번) — 라이브러리 DB 가 없으면 아무것도 안 함 */
function mc_lib_auto_tick_script($bidx) {
    if (empty($_SESSION['user_id']) || !mc_lib_available() || !mc_lib_db_exists() || !mc_lib_base($bidx)) return;
    try { $d = mc_lib_auto_due($bidx); } catch (\Throwable $e) { return; }
    if ($d['run'] === '' && $d['next'] <= 0) return;
    $delay = $d['run'] !== '' ? 300 : min(120, (int)$d['next']) * 1000; // 바로 필요하면 화면이 뜬 뒤 0.3초
    echo '<script>(function(){var B=' . (int)$bidx . ',n=0;function t(){if(++n>6)return;var f=new FormData();f.append("bidx",B);'
       . 'try{fetch("library_auto.php",{method:"POST",body:f,credentials:"same-origin",keepalive:true}).then(function(r){return r.json();})'
       . '.then(function(j){if(j&&j.next>0&&j.next<=120)setTimeout(t,j.next*1000);}).catch(function(){});}catch(e){}}setTimeout(t,' . (int)$delay . ');})();</script>' . "\n";
}

// ============================================================
// 읽기 기록 (bookmark.php 자동저장에서 호출)
// ============================================================

function mc_lib_total_pages($bidx, $rel) {
    $base = mc_lib_base($bidx);
    if (!$base || !function_exists('get_cache_json_path')) return 0;
    $jf = get_cache_json_path($base . $rel);
    if (!is_file($jf)) return 0;
    $j = json_decode((string)@file_get_contents($jf), true);
    return is_array($j) ? (int)($j['totalpage'] ?? 0) : 0;
}

/** "image12" / "image12o340e" → 12 (앞쪽 페이지 번호만) */
function mc_lib_page_from_mark($mark) {
    if (preg_match('/^image(\d{1,6})/', (string)$mark, $m)) return (int)$m[1];
    // ★ (2026-11-02 08:57) PDF 는 "pdf_쪽"(1부터) → 0부터로 — 전에는 늘 0이라 PDF 서재 진행률·다 읽음이 안 바뀌었음
    if (preg_match('/^pdf_(\d{1,6})$/', (string)$mark, $m)) return max(0, (int)$m[1] - 1);
    return 0;
}

function mc_lib_record_read($user, $bidx, $rel, $page, $total_hint = 0) {
    try {
        $db = mc_lib_db();
        if (!$db) return;
        $rel = mc_lib_norm_path($rel);
        $user = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$user);
        if ($rel === '' || $user === '') return;
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, mc_lib_exts(), true)) return;
        $total = mc_lib_total_pages($bidx, $rel);
        // ★ (2026-11-02 20:10) PDF 는 쪽수 캐시가 없어 total 이 늘 0 — 뷰어가 보낸 총 쪽수를 씀(PDF 만, 10만 쪽까지)
        // ★ (2026-11-02 21:17) PDF 는 뷰어 값을 먼저 — 같은 이름 ZIP 이 있으면 같은 캐시 json(이름.json)의 ZIP 쪽수가 잡혔음
        if ($ext === 'pdf' && (int)$total_hint > 0) $total = min(100000, (int)$total_hint);
        $page = max(0, (int)$page);
        $finished = ($total > 0 && $page + 1 >= $total) ? 1 : 0;
        // ★ (2026-11-02 21:17) 쪽수를 모르는 요청(total 0)은 저장된 쪽수를 지우지 않음(아래 CASE)
        $st = $db->prepare('INSERT INTO reads(user, bidx, path, page, total, finished, updated_at) VALUES(?,?,?,?,?,?,?)
            ON CONFLICT(user, bidx, path) DO UPDATE SET page=excluded.page, total=CASE WHEN excluded.total > 0 THEN excluded.total ELSE reads.total END,
            finished=MAX(reads.finished, excluded.finished), updated_at=excluded.updated_at');
        $st->execute([$user, (int)$bidx, $rel, $page, $total, $finished, time()]);
    } catch (\Throwable $e) {
        error_log('[library] record_read failed: ' . $e->getMessage());
    }
}

// ============================================================
// 편집 (관리자) — 호출 전에 권한·CSRF 확인은 library_api.php 에서
// ============================================================

/** "a, b, c" → 정리된 이름 배열(중복 제거, 최대 50개, 각 60자) */
function mc_lib_split_terms($text) {
    $out = [];
    foreach (preg_split('/\s*[,，、\n]\s*/u', (string)$text) as $t) {
        $t = mc_lib_clean(mc_lib_nfc($t));
        if ($t === '') continue;
        if (mb_strlen($t, 'UTF-8') > 60) $t = mb_substr($t, 0, 60, 'UTF-8');
        $out[mc_lib_lower($t)] = $t;
        if (count($out) >= 50) break;
    }
    return array_values($out);
}

/**
 * 시리즈 용어 설정
 * @param string $mode 'replace'(이 종류 전체 교체, 수동으로 고정) | 'append'(추가)
 */
function mc_lib_set_terms(PDO $db, $sid, $kind, array $names, $mode = 'replace') {
    if (!in_array($kind, ['author', 'group', 'genre', 'tag'], true)) return; // ★ (2026-11-02 01:53) group
    if ($mode === 'replace') {
        $db->prepare('DELETE FROM series_terms WHERE series_id=? AND term_id IN (SELECT id FROM terms WHERE kind=?)')->execute([(int)$sid, $kind]);
    }
    $ins = $db->prepare("INSERT INTO series_terms(series_id, term_id, source) VALUES(?,?,'manual') ON CONFLICT(series_id, term_id) DO UPDATE SET source='manual'");
    foreach ($names as $n) {
        $tid = mc_lib_term_id($db, $kind, $n, true); // ★ (2026-11-02 02:08) 대소문자만 바꿔 넣으면 그 표기로
        if ($tid) $ins->execute([(int)$sid, $tid]);
    }
    // 직접 정했음을 표시(비워도 자동 값이 다시 붙지 않게) — 2=작가, ★ (2026-10-29) 8=장르, 16=태그(ComicInfo 자동 반영 차단), ★ (2026-11-02) 32=그룹
    $bit = ['author' => 2, 'group' => 32, 'genre' => 8, 'tag' => 16][$kind];
    $db->prepare('UPDATE series SET manual = (manual | ?), updated_at=? WHERE id=?')->execute([$bit, time(), (int)$sid]);
}

/** 시리즈 이름 변경 — 같은 이름(키)의 시리즈가 있으면 그쪽으로 합침. 반환: 최종 시리즈 id */
function mc_lib_rename_series(PDO $db, $sid, $newName) {
    $newName = mc_lib_clean(mc_lib_nfc($newName));
    $st = $db->prepare('SELECT * FROM series WHERE id=?');
    $st->execute([(int)$sid]);
    $cur = $st->fetch();
    if (!$cur || $newName === '') return (int)$sid;
    if (mb_strlen($newName, 'UTF-8') > 200) $newName = mb_substr($newName, 0, 200, 'UTF-8');
    $bidx = (int)$cur['bidx'];
    $newKey = mc_lib_name_key($newName);
    if ($newKey === '') return (int)$sid;
    $now = time();
    $aliasIns = $db->prepare('INSERT OR REPLACE INTO series_alias(bidx, name_key, series_id) VALUES(?,?,?)');
    // ★ (2026-11-02 08:54) 작가별로 나뉜 작품("키@작가")은 이름 키가 원래 이름과 같으면 이름만 바꿈 — 편집 창에서 이름을 그대로 두고 저장해도 같은 제목의 다른 작가 작품과 합쳐지던 문제
    if ($newKey === $cur['name_key'] || $newKey === mc_lib_name_key((string)$cur['name'])) {
        $db->prepare('UPDATE series SET name=?, manual=(manual|1), updated_at=? WHERE id=?')->execute([$newName, $now, (int)$sid]);
        return (int)$sid;
    }
    // 이 시리즈의 권은 자동 재분류에서 제외(이름을 바꿔도 예전 파일명으로 다시 쪼개지지 않게)
    //   ★ (2026-11-02 11:42) 이름 키가 실제로 바뀔 때만 — 전에는 편집 창에서 태그만 바꿔 저장해도 모든 권이 고정돼 규칙이 나아져도 다시 판단되지 않았음
    $db->prepare('UPDATE volumes SET manual=1 WHERE series_id=?')->execute([(int)$sid]);
    $st = $db->prepare('SELECT id FROM series WHERE bidx=? AND name_key=? AND id<>?');
    $st->execute([$bidx, $newKey, (int)$sid]);
    $target = (int)$st->fetchColumn();
    if ($target) {
        // 합치기: 권·용어·별칭을 대상 시리즈로 옮기고 현재 시리즈 삭제
        $db->prepare('UPDATE volumes SET series_id=? WHERE series_id=?')->execute([$target, (int)$sid]);
        $db->prepare('INSERT OR IGNORE INTO series_terms(series_id, term_id, source) SELECT ?, term_id, source FROM series_terms WHERE series_id=?')->execute([$target, (int)$sid]);
        $aliasIns->execute([$bidx, $cur['name_key'], $target]);
        // ★ (2026-10-30) 상태·설명·직접 입력 표시(manual 비트)도 대상 작품으로 넘긴 뒤 삭제(별칭 이동·삭제 포함)
        //   — 전에는 편집 창에서 상태·설명을 바꾸면서 기존 작품 이름으로 바꾸면 그 수정이 조용히 사라졌음
        mc_lib_carry_series_info($db, (int)$sid, $target);
        $db->prepare('UPDATE series_alias SET series_id=? WHERE series_id=?')->execute([$target, (int)$sid]);
        $db->prepare('DELETE FROM series WHERE id=?')->execute([(int)$sid]);
        $db->prepare('UPDATE series SET updated_at=? WHERE id=?')->execute([$now, $target]);
        return $target;
    }
    $aliasIns->execute([$bidx, $cur['name_key'], (int)$sid]);
    $db->prepare('DELETE FROM series_alias WHERE bidx=? AND name_key=?')->execute([$bidx, $newKey]);
    $db->prepare('UPDATE series SET name=?, name_key=?, manual=(manual|1), updated_at=? WHERE id=?')->execute([$newName, $newKey, $now, (int)$sid]);
    return (int)$sid;
}

/** 권들을 이름으로 지정한 시리즈로 이동(없으면 생성). 반환: 대상 시리즈 id */
function mc_lib_move_volumes(PDO $db, array $vids, $seriesName, $bidx) {
    $seriesName = mc_lib_clean(mc_lib_nfc($seriesName));
    if ($seriesName === '' || !$vids) return 0;
    $target = mc_lib_series_id($db, (int)$bidx, $seriesName, time());
    if (!$target) return 0;
    $upd = $db->prepare('UPDATE volumes SET series_id=?, manual=1, corder=0 WHERE id=? AND bidx=?'); // ★ fix64 다른 작품으로 가면 사본 순서는 지움
    foreach ($vids as $vid) $upd->execute([$target, (int)$vid, (int)$bidx]);
    mc_lib_cleanup_empty($db);
    return $target;
}

/**
 * ★ (2026-11-06 07:20) 손으로 묶기 — 편집 창 '도서 목록'에서 바로(펜닐 요청: 추가·빼기를 편하게)
 * 책을 이 시리즈로 가져옴(다른 시리즈에서) — 같은 콘텐츠 폴더(bidx)의 책만, 손으로 묶은 표시(manual=1: 다시 스캔해도 그대로)
 * @return int 옮긴 책 수
 */
function mc_lib_assign_volumes(PDO $db, array $vids, $sid) {
    $st = $db->prepare('SELECT bidx FROM series WHERE id=?');
    $st->execute([(int)$sid]);
    $b = $st->fetchColumn();
    $st->closeCursor();
    if ($b === false) return 0;
    $upd = $db->prepare('UPDATE volumes SET series_id=?, manual=1, corder=0 WHERE id=? AND bidx=? AND series_id<>?'); // ★ fix64 사본 순서 지움
    $n = 0;
    foreach ($vids as $vid) { $upd->execute([(int)$sid, (int)$vid, (int)$b, (int)$sid]); $n += $upd->rowCount(); }
    if ($n) mc_lib_cleanup_empty($db);
    return $n;
}

/**
 * ★ (2026-11-06 07:20) 이 시리즈에서 책을 뺌 — 2단계(트랜잭션 밖에서 부름)
 *   ① 자동 판단에 맡기고 그 폴더를 바로 다시 읽음 → 자동이 놓는 작품으로(폴더 단위 규칙까지 그대로 — 파일 이름만 보면 '로마자+한글 제목' 같은 경우 다른 이름이 됐음)
 *   ② 그래도 이 시리즈로 돌아온 책은 파일 이름(확장자 뺀)의 작품으로 따로(이름이 별칭 등으로 이 시리즈가 되면 뒤에 (2)(3)…)
 *   ②의 경우만 손으로 정한 표시(manual=1) — 다시 스캔해도 이 시리즈로 돌아오지 않게
 *   (★ fix61: 예전엔 ①도 manual=1 이라 혼자 남은 책에 '직접 묶음'이 붙었음 — ①은 자동 판단 그대로라 표시 없음)
 * @return int 뺀 책 수
 */
function mc_lib_detach_volumes(PDO $db, array $vids, $sid) {
    $sid = (int)$sid;
    $sel = $db->prepare('SELECT id, bidx, folder, filename FROM volumes WHERE id=? AND series_id=?');
    $rows = [];
    foreach ($vids as $vid) { $sel->execute([(int)$vid, $sid]); if ($v = $sel->fetch()) $rows[(int)$v['id']] = $v; $sel->closeCursor(); }
    if (!$rows) return 0;
    mc_lib_begin_write($db);
    $up0 = $db->prepare('UPDATE volumes SET manual=0, corder=0 WHERE id=?'); // ★ fix64 빠진 책은 사본 순서도 지움
    foreach ($rows as $v) $up0->execute([(int)$v['id']]);
    $db->commit();
    $folders = [];
    foreach ($rows as $v) $folders[(int)$v['bidx'] . "\n" . (string)$v['folder']] = [(int)$v['bidx'], (string)$v['folder']];
    foreach (array_slice(array_values($folders), 0, 200) as [$fb, $fd]) mc_lib_index_folder($fb, $fd, null, true);
    mc_lib_begin_write($db);
    $cur = $db->prepare('SELECT series_id FROM volumes WHERE id=?');
    $upd = $db->prepare('UPDATE volumes SET series_id=?, manual=1, corder=0 WHERE id=?'); // ★ fix64 사본 순서 지움
    // ★ fix62 책이 하나뿐인 시리즈에서 그 책을 빼면 갈 곳이 없음(예전엔 파일 이름의 작품을 새로 만들어 옮기고 '직접 묶음'이 붙었음) → 자동 판단 그대로(위에서 manual=0) 둠
    $cnt = $db->prepare('SELECT COUNT(*) FROM volumes WHERE series_id=?');
    $cnt->execute([$sid]); $lone = (count($rows) === 1 && (int)$cnt->fetchColumn() === 1); $cnt->closeCursor();
    $now = time(); $n = 0;
    foreach ($rows as $v) {
        $cur->execute([(int)$v['id']]); $now_sid = (int)$cur->fetchColumn(); $cur->closeCursor();
        // ★ fix61 ① 자동 판단이 이미 다른 작품에 놓았으면 그대로(manual=0) — 다시 스캔해도 같은 곳이라 '직접 묶음' 표시를 붙이지 않음
        if ($now_sid && $now_sid !== $sid) { $n++; continue; }
        if ($lone && $now_sid === $sid) continue;
        $target = 0;
        {
            $stem = mc_lib_clean(mc_lib_nfc(pathinfo((string)$v['filename'], PATHINFO_FILENAME)));
            for ($k = 1; !$target && $k < 50 && $stem !== ''; $k++) {
                $t = (int)mc_lib_series_id($db, (int)$v['bidx'], $k === 1 ? $stem : $stem . ' (' . $k . ')', $now);
                if ($t && $t !== $sid) $target = $t;
            }
        }
        if (!$target) continue;
        $upd->execute([$target, (int)$v['id']]);
        $n++;
    }
    mc_lib_cleanup_empty($db);
    $db->commit();
    return $n;
}

/**
 * ★ (2026-11-06 07:20) 손으로 묶은 책을 자동 판단으로 되돌림 — manual 표시만 지움(권 번호·표시 이름을 손으로 고친 것도 자동으로)
 *   실제 다시 판단은 트랜잭션 밖에서 그 폴더를 바로 다시 읽어서(mc_lib_index_folder force) — 되돌릴 폴더 목록을 돌려줌
 * @return array [[bidx, 폴더], ...]
 */
function mc_lib_volumes_to_auto(PDO $db, array $vids, $sid) {
    $sel = $db->prepare('SELECT id, bidx, folder FROM volumes WHERE id=? AND series_id=? AND manual=1');
    $upd = $db->prepare('UPDATE volumes SET manual=0 WHERE id=?');
    $folders = [];
    foreach ($vids as $vid) {
        $sel->execute([(int)$vid, (int)$sid]);
        $v = $sel->fetch();
        $sel->closeCursor();
        if (!$v) continue;
        $upd->execute([(int)$v['id']]);
        $folders[(int)$v['bidx'] . "\n" . (string)$v['folder']] = [(int)$v['bidx'], (string)$v['folder']];
    }
    return array_values($folders);
}

/**
 * ★ (2026-11-06 07:20) 이 시리즈에 넣을 책 찾기 — 같은 콘텐츠 폴더에서 파일 이름·작품 이름에 낱말이 모두 들어간 책(이 시리즈 것은 빼고, 볼 권한 있는 것만)
 * @return array [{id, filename, folder, series, sid, vol}]
 */
function mc_lib_find_volumes(PDO $db, $bidx, $sid, $q, $limit = 80) {
    $q = mc_lib_clean(mc_lib_nfc((string)$q));
    $words = array_slice(array_values(array_filter(preg_split('/\s+/u', $q), 'strlen')), 0, 6);
    if (!$words) return [];
    $sql = "SELECT v.id, v.bidx, v.path, v.folder, v.filename, v.vol_no, v.vol_label, v.subtitle, v.series_id, s.name AS sname FROM volumes v JOIN series s ON s.id=v.series_id WHERE v.bidx=? AND v.missing=0 AND v.series_id<>?";
    $args = [(int)$bidx, (int)$sid];
    foreach ($words as $w) { $sql .= " AND (v.filename || ' ' || s.name) LIKE ? ESCAPE '\\'"; $args[] = '%' . strtr($w, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%'; }
    $sql .= ' ORDER BY s.name, v.vol_no, v.filename LIMIT ' . (int)min(500, max(1, $limit * 3));
    $st = $db->prepare($sql);
    $st->execute($args);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        if (!mc_lib_can_access((int)$r['bidx'], $r['path'])) continue;
        $vol = $r['vol_label'] !== '' ? (string)$r['vol_label'] : ($r['vol_no'] === null ? '' : rtrim(rtrim(number_format((float)$r['vol_no'], 2, '.', ''), '0'), '.'));
        $out[] = ['id' => (int)$r['id'], 'filename' => (string)$r['filename'], 'folder' => (string)$r['folder'], 'series' => (string)$r['sname'], 'sid' => (int)$r['series_id'], 'vol' => $vol,
                  'vno' => $r['vol_no'] === null ? null : (float)$r['vol_no'], 'vlabel' => (string)$r['vol_label'], // ★ fix60 편집 창이 권 번호대로 넣을 때 쓰는 숫자 번호·표시 이름
                  'x' => mc_lib_is_extra_sub($r['subtitle'] ?? '') ? 1 : 0, 'sub' => (string)($r['subtitle'] ?? '')]; // ★ fix68 번외·외전 부제(같은 번호여도 서버가 따로 묶음 — 편집 창도 같게)
        if (count($out) >= $limit) break;
    }
    return $out;
}

/**
 * ★ (2026-10-29) 자동 판단이 바뀌어 권이 모두 다른 시리즈로 옮겨진 경우, 옛 시리즈의 관리자 입력 정보를 새 시리즈로 넘기고 옛 시리즈 삭제
 * (옛 시리즈에 권이 남아 있으면 아무것도 하지 않음. 새 시리즈에 이미 직접 입력한 상태·설명이 있으면 그것을 유지)
 */
function mc_lib_carry_series_info(PDO $db, $old, $new) {
    $old = (int)$old; $new = (int)$new;
    if ($old <= 0 || $new <= 0 || $old === $new) return;
    $st = $db->prepare('SELECT COUNT(*) FROM volumes WHERE series_id=?');
    $st->execute([$old]);
    if ((int)$st->fetchColumn() > 0) return;
    $st = $db->prepare('SELECT id, status, description, manual FROM series WHERE id IN (?, ?)');
    $st->execute([$old, $new]);
    $rows = [];
    foreach ($st->fetchAll() as $r) $rows[(int)$r['id']] = $r;
    if (!isset($rows[$old], $rows[$new])) return;
    $o = $rows[$old]; $n = $rows[$new];
    $db->prepare("INSERT INTO series_terms(series_id, term_id, source) SELECT ?, term_id, 'manual' FROM series_terms WHERE series_id=? AND source='manual'
        ON CONFLICT(series_id, term_id) DO UPDATE SET source='manual'")->execute([$new, $old]);
    $man = (int)$n['manual'] | ((int)$o['manual'] & (2 | 8 | 16 | 32)); // 작가·장르·태그·그룹(★ 2026-11-02)을 직접 정했다는 표시도 함께
    $status = $n['status']; $desc = $n['description'];
    if (((int)$o['manual'] & 4) && !((int)$n['manual'] & 4)) { $status = $o['status']; $desc = $o['description']; $man |= 4; }
    elseif ($desc === '' && $o['description'] !== '') { $desc = $o['description']; }
    $db->prepare('UPDATE series SET status=?, description=?, manual=?, updated_at=? WHERE id=?')->execute([$status, $desc, $man, time(), $new]);
    $db->prepare('UPDATE series_alias SET series_id=? WHERE series_id=?')->execute([$new, $old]);
    $db->prepare('DELETE FROM series WHERE id=?')->execute([$old]);
}

/** 권이 하나도 없는 시리즈 정리(수동 정보가 없는 것만) */
function mc_lib_cleanup_empty(PDO $db) {
    $db->exec('DELETE FROM series WHERE id NOT IN (SELECT DISTINCT series_id FROM volumes WHERE series_id IS NOT NULL)
        AND manual=0 AND description=\'\' AND id NOT IN (SELECT series_id FROM series_terms WHERE source=\'manual\')');
}

// ============================================================
// 조회
// ============================================================

/** 시리즈의 용어(작가·장르·태그) */
function mc_lib_series_terms(PDO $db, $sid) {
    $st = $db->prepare('SELECT t.id, t.kind, t.name, st.source FROM series_terms st JOIN terms t ON t.id=st.term_id WHERE st.series_id=? ORDER BY t.kind, t.name');
    $st->execute([(int)$sid]);
    $out = ['author' => [], 'group' => [], 'genre' => [], 'tag' => []]; // ★ (2026-11-02 01:53) group
    foreach ($st->fetchAll() as $r) if (isset($out[$r['kind']])) $out[$r['kind']][] = $r;
    return $out;
}

/** 시리즈의 권 목록(권한 있는 것만, 정렬: 권수 → 파일명 자연정렬) */
function mc_lib_series_volumes(PDO $db, $sid, $include_missing = false) {
    $st = $db->prepare('SELECT * FROM volumes WHERE series_id=?' . ($include_missing ? '' : ' AND missing=0'));
    $st->execute([(int)$sid]);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        if (mc_lib_can_access((int)$r['bidx'], $r['path'])) $rows[] = $r;
    }
    $imp = mc_lib_implicit_first($rows);
    usort($rows, function ($a, $b) use ($imp) {
        // ★ 권 번호 없는 본편("7 Days")은 맨 앞(1권 자리), 그 밖의 번호 없는 권은 맨 뒤
        $va = mc_lib_vol_sort_no($a, $imp); $vb = mc_lib_vol_sort_no($b, $imp);
        if ($va != $vb) return $va < $vb ? -1 : 1;
        return strnatcasecmp($a['filename'], $b['filename']);
    });
    return $rows;
}

/** 현재 사용자의 읽기 정보 맵: path => [page,total,finished,updated_at] (DB 기록 + 기존 자동저장 병합) */
function mc_lib_user_reads($bidx = null) {
    $map = [];
    $user = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_SESSION['user_id'] ?? ''));
    $db = mc_lib_db();
    if ($db && $user !== '') {
        $sql = 'SELECT bidx, path, page, total, finished, updated_at FROM reads WHERE user=?';
        $st = $db->prepare($sql);
        $st->execute([$user]);
        foreach ($st->fetchAll() as $r) $map[(int)$r['bidx'] . '|' . $r['path']] = $r;
    }
    // 기존 자동저장(최근 N개)도 반영 — 라이브러리 도입 전에 읽던 것
    if (function_exists('get_autosave_file')) {
        $af = get_autosave_file();
        if (is_file($af)) {
            $as = json_decode((string)@file_get_contents($af), true);
            if (is_array($as)) {
                foreach ($as as $path => $v) {
                    if (!is_array($v)) $v = ['bookmark' => $v, 'bidx' => 0];
                    $b = (int)($v['bidx'] ?? 0);
                    if (function_exists('mc_rec_split') && preg_match('#^\d{1,4}\|/#', (string)$path)) [$b, $path] = mc_rec_split($path, $v); // ★ (2026-11-06 00:50) 같은 경로를 다른 콘텐츠 폴더에서 저장한 칸("bidx|/경로")
                    $k = $b . '|' . mc_lib_norm_path($path);
                    if (isset($map[$k])) continue;
                    $page = mc_lib_page_from_mark($v['bookmark'] ?? '');
                    $map[$k] = ['bidx' => $b, 'path' => mc_lib_norm_path($path), 'page' => $page, 'total' => 0, 'finished' => 0, 'updated_at' => 0, 'from_autosave' => 1];
                }
            }
        }
    }
    return $map;
}

/** 뷰어 URL (index.php 카드와 같은 규칙)
 *  ★ (2026-11-03 07:51) 이어 보기를 즐겨찾기·목록과 같게 — 그 책의 자동 저장(이어 읽기) 기록이 있으면 위치(#imageN)를 붙이지 않고 뷰어가 기록에서 이어 봄
 *   (쪽 안 위치·모드 기억·'이어 보기' 설정까지 그대로). 전에는 서재 기록의 쪽 번호만 붙여 그 쪽 맨 위에서 열렸고, 기록이 늦거나 달라 한두 쪽 어긋났음
 *   자동 저장 기록이 없을 때만(최대 개수에서 밀려남 등) 서재 기록의 쪽으로. $from_start = '처음부터' 단추(#image0·#pdf_1 — 뷰어가 이어 보기를 하지 않음) */
function mc_lib_view_url($bidx, $rel, $page = null, $from_start = false) {
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $enc = encode_url($rel);
    if ($ext === 'pdf') return 'viewer.php?filetype=pdf&file=' . $enc . '&bidx=' . (int)$bidx . ($from_start ? '#pdf_1' : '');
    if ($ext === 'epub') return 'epub_viewer.php?file=' . $enc . '&bidx=' . (int)$bidx;
    if ($ext === 'txt') return 'txt_viewer.php?file=' . $enc . '&bidx=' . (int)$bidx;
    $hash = '';
    if ($from_start) $hash = '#image0';
    elseif ($page !== null && $page > 0) {
        // ★ (2026-11-03 08:31) 뷰어가 실제로 이어 볼 때만 빼기('이어 보기' 설정을 끈 경우 mc_mv_resume_mark 가 '' — 그땐 예전처럼 서재 기록의 쪽. 전엔 끈 상태에서 '이어 보기' 단추가 처음부터 열렸음)
        if (!(function_exists('mc_mv_resume_mark') && mc_mv_resume_mark($rel, (int)$bidx) !== '')) $hash = '#image' . (int)$page;
    }
    return 'viewer.php?mode=toon&file=' . $enc . '&bidx=' . (int)$bidx . $hash;
}

function mc_lib_thumb_url($bidx, $rel) {
    return 'thumb.php?file=' . encode_url($rel) . '&bidx=' . (int)$bidx;
}

/** 권한 있는 시리즈인지(권 하나라도 볼 수 있으면) */
function mc_lib_series_visible(PDO $db, $sid) {
    return count(mc_lib_series_volumes($db, $sid)) > 0;
}

/** 관리자 여부 */
function mc_lib_is_admin() {
    return isset($_SESSION['user_group']) && trim($_SESSION['user_group']) === 'admin';
}

