<?php
/**
 * myComix 작품 탐색 — 시리즈 목록 · 필터(작가/장르/태그/상태/안 읽음/검색) · 읽는 중 · 최근 추가
 * ★ (2026-10-29) 신규
 *
 * - 현재 콘텐츠 폴더(bidx)의 시리즈만, 현재 사용자 그룹이 볼 수 있는 폴더의 권만 집계(기존 폴더 권한 규칙)
 *   ★ (2026-10-30) 콘텐츠 폴더가 2개 이상이면 '전체 폴더(통합)' 또는 폴더 하나를 골라 볼 수 있음(scope, 세션에 기억 · 기본은 통합)
 * - 정보는 라이브러리 스캔 결과 기준(관리자 "라이브러리 스캔" 버튼). 상세 화면을 열면 그 시리즈 폴더는 즉시 갱신
 */

$_lib_t0 = microtime(true);
require_once __DIR__ . "/bootstrap.php";
handle_timeout_popup();
require_once __DIR__ . "/library.php";

$bidx = init_bidx();
// ★ (2026-10-30) 보는 범위 — 'all'(모든 콘텐츠 폴더 통합) 또는 폴더 번호. 고른 값은 세션에 기억(탐색 화면 안 링크·상세에서 돌아와도 유지)
$multi_base = count($base_dirs) > 1;
$scope = 'all';
if ($multi_base) {
    $sv = (isset($_GET['scope']) && is_string($_GET['scope'])) ? $_GET['scope'] : (is_string($_SESSION['mc_lib_scope'] ?? null) ? $_SESSION['mc_lib_scope'] : 'all'); // ★ (2026-11-02 10:08) 배열이면 경고
    if ($sv !== 'all' && (!ctype_digit($sv) || !isset($base_dirs[(int)$sv]))) $sv = 'all';
    $scope = $sv;
    if (isset($_GET['scope']) && ($_SESSION['mc_lib_scope'] ?? null) !== $scope) $_SESSION['mc_lib_scope'] = $scope;
} else {
    $scope = (string)$bidx;
}
if ($scope !== 'all' && (int)$scope !== $bidx) $bidx = init_bidx((int)$scope); // 폴더 하나를 고르면 그 폴더가 현재 폴더(돌아가기·스캔 대상)
$scope_bidxs = $scope === 'all' ? array_keys($base_dirs) : [(int)$scope];
$BIN = implode(',', array_map('intval', $scope_bidxs ?: [0]));
$_branding = load_branding();
$is_admin = mc_lib_is_admin();
$db = mc_lib_db();

$f_author = (int)($_GET['author'] ?? 0);
$f_group  = (int)($_GET['group'] ?? 0); // ★ (2026-11-02 01:53) 그룹(서클)
$f_genre  = (int)($_GET['genre'] ?? 0);
$f_tag    = (int)($_GET['tag'] ?? 0);
$f_status = in_array(($_GET['status'] ?? ''), ['ongoing', 'completed', 'hiatus'], true) ? $_GET['status'] : '';
$f_unread = !empty($_GET['unread']);
$f_q      = mc_lib_clean(mc_lib_nfc(mb_substr(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 0, 100, 'UTF-8'))); // ★ (2026-11-02 09:06) 배열이면 경고
// ★ (2026-11-03 01:25) 정렬을 기기(브라우저)마다 기억 — 주소에 정렬이 없으면 쿠키(mc_lib_sort = '정렬.방향')의 값으로
if (!is_string($_GET['sort'] ?? null) || $_GET['sort'] === '') {
    $_ck = is_string($_COOKIE['mc_lib_sort'] ?? null) ? $_COOKIE['mc_lib_sort'] : '';
    if (preg_match('/^(name|recent|added|count|random)\.(asc|desc)$/', $_ck, $_cm)) { $_GET['sort'] = $_cm[1]; if ($_cm[1] !== 'random') $_GET['o'] = $_cm[2]; }
}
$f_sort   = in_array(($_GET['sort'] ?? ''), ['name', 'recent', 'added', 'count', 'random'], true) ? $_GET['sort'] : 'name';
// ★ (2026-11-03 01:25) 정렬 방향 — 이름은 가나다순, 나머지(읽은 때·추가·권수)는 큰 것부터가 기본. 같은 칩을 다시 누르면 반대로
$f_dir_def = ($f_sort === 'name') ? 'asc' : 'desc';
$f_dir = in_array(($_GET['o'] ?? ''), ['asc', 'desc'], true) ? $_GET['o'] : $f_dir_def;
// ★ (2026-11-02 16:17) 랜덤 — 섞는 순서는 seed 로 정함(쪽을 넘겨도 같은 순서, '다시 섞기'를 누르면 새 seed). 계산 결과 저장은 이름순 것을 같이 씀
$f_seed = 0;
if ($f_sort === 'random') {
    $f_seed = (is_string($_GET['seed'] ?? null) && ctype_digit($_GET['seed'])) ? (int)substr($_GET['seed'], 0, 9) : 0;
    if ($f_seed <= 0) {
        $f_seed = random_int(1, 999999999); $_GET['seed'] = (string)$f_seed; // 쪽 링크에 이어지게
        // 주소에 seed 가 없으면 붙인 주소로 다시(새로고침·뒤로가기에도 같은 순서)
        if (!isset($_GET['lib_terms']) && !isset($_GET['lib_prog']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !headers_sent()) {
            $_qs = $_GET; unset($_qs['seed']); $_qs['seed'] = (string)$f_seed;
            header('Location: browse.php?' . http_build_query($_qs), true, 302);
            exit;
        }
    }
}
$f_sort_c = $f_sort === 'random' ? 'name' : $f_sort;
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(6, min(500, (int)get_app_settings('lib_per_page', 48))); // ★ (2026-11-03 01:28) 관리자 설정(기본 48)
$lib_reading_n = max(0, min(60, (int)get_app_settings('lib_reading_count', 12))); // 읽는 중 줄(0 = 안 보임)
$lib_recent_n = max(0, min(60, (int)get_app_settings('lib_recent_count', 12)));   // 최근 추가 줄(0 = 안 보임)
$filtered = ($f_author || $f_group || $f_genre || $f_tag || $f_status !== '' || $f_unread || $f_q !== '');

// ★ (2026-11-02 07:52) 서재 첫 화면 계산 결과 저장 — 키 = 사용자·볼 수 없는 폴더 목록·범위·거르기 조건·서재 DB 파일 상태·내 이어 보기 기록 파일 상태·인식 규칙 버전
//   DB 에 무엇이든 쓰이면(스캔·읽기 기록·편집) 파일 크기·시각이 바뀌어 새로 계산 → 옛 결과를 보여 줄 일이 없음. 실패하면 그냥 계산(예전과 같음)
function lib_browse_cache_dir() { return __DIR__ . '/src/lib_cache'; }
function lib_browse_cache_key(array $bidxs, ...$cond) {
    global $db;
    try {
        clearstatcache();
        $sig = [];
        // -wal 은 DB 를 열 때마다 새로 생겨(빈 파일) 시각이 바뀌므로 내용이 있을 때만 시각을 봄
        foreach (['library.db', 'library.db-wal'] as $f) { $fp = __DIR__ . '/src/' . $f; $sz = is_file($fp) ? (int)filesize($fp) : -1; $sig[] = $sz > 0 ? ($sz . '@' . filemtime($fp)) : (string)max(0, $sz); }
        $af = function_exists('get_autosave_file') ? get_autosave_file() : '';
        $sig[] = ($af !== '' && is_file($af)) ? (string)@md5_file($af) : '-'; // ★ (2026-11-02 08:27) 내용으로(같은 초·같은 크기 저장도 구별)
        // ★ (2026-11-02 08:27) 파일 크기·시각은 같은 초 안의 쓰기(체크포인트 뒤 WAL 크기가 그대로인 경우)를 놓칠 수 있어 DB 내용 요약도 넣음
        //   (내 읽기 기록·작품·권·작가 연결 — 색인·작은 표라 몇 ms)
        if ($db) {
            $u = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_SESSION['user_id'] ?? ''));
            $q = $db->prepare('SELECT COUNT(*), MAX(updated_at), TOTAL(page), TOTAL(finished) FROM reads WHERE user=?'); $q->execute([$u]); $sig[] = implode(',', $q->fetch(PDO::FETCH_NUM));
            $sig[] = implode(',', $db->query('SELECT COUNT(*), MAX(updated_at), TOTAL(LENGTH(status)) FROM series')->fetch(PDO::FETCH_NUM));
            $sig[] = implode(',', $db->query('SELECT COUNT(*), MAX(id), TOTAL(missing), TOTAL(vol_no), TOTAL(series_id) FROM volumes')->fetch(PDO::FETCH_NUM));
            $sig[] = implode(',', $db->query('SELECT COUNT(*), MAX(rowid) FROM series_terms')->fetch(PDO::FETCH_NUM));
        }
        $deny = $db ? $db->query("SELECT bidx || '|' || root FROM temp.lib_deny2 ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN) : [];
        return md5(json_encode([(string)($_SESSION['user_id'] ?? ''), $bidxs, $cond, $sig, md5(implode("\n", $deny)), defined('MC_LIB_PARSER') ? MC_LIB_PARSER : 0, 7])); // ★ (2026-11-02 21:13) 7: 쪽수 없는 읽기 기록을 권 쪽수로 보충 // ★ (2026-11-02 20:09) 6: 0건일 때 선택지 계산이 바뀌어 예전 저장 결과는 안 씀 // ★ (2026-11-02 16:17) 5: 같은 이름 정렬 기준을 정해 예전 저장 결과는 안 씀 // ★ (2026-11-02 10:29) 4: 칩 이름 확인 방식이 바뀌어 예전 저장 결과는 쓰지 않음
    } catch (\Throwable $e) { return ''; }
}
function lib_browse_cache_get($key) {
    $f = lib_browse_cache_dir() . '/' . $key . '.ser';
    if (!is_file($f) || filemtime($f) < time() - 3600) return null;
    $s = @file_get_contents($f);
    if ($s === false || $s === '') return null;
    if (strncmp($s, 'Z1', 2) === 0) { $s = function_exists('gzinflate') ? @gzinflate(substr($s, 2)) : false; if ($s === false) return null; } // 압축해 둔 것
    $d = @unserialize($s, ['allowed_classes' => false]);
    return is_array($d) ? $d : null;
}
function lib_browse_cache_put($key, array $data) {
    try {
        $dir = lib_browse_cache_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return;
        $tmp = $dir . '/' . $key . '.' . bin2hex(random_bytes(4)) . '.tmp'; // ★ (2026-11-02 09:13) Windows Apache 는 스레드끼리 pid 가 같아 임시 이름이 겹쳤음
        $raw = serialize($data);
        if (function_exists('gzdeflate')) { $z = @gzdeflate($raw, 1); if ($z !== false) $raw = 'Z1' . $z; } // 빠른 압축(작품 2.7만이면 약 6MB → 1MB 안팎)
        if (@file_put_contents($tmp, $raw, LOCK_EX) === false) return;
        if (!@rename($tmp, $dir . '/' . $key . '.ser')) @unlink($tmp);
        // 정리: 1시간 지난 파일·남은 임시 파일, 그리고 최근 것 40개만 남김(거르기 조합이 많아도 쌓이지 않게)
        $files = [];
        foreach (array_merge((array)@glob($dir . '/*.ser'), (array)@glob($dir . '/*.tmp')) as $old) {
            if (!is_string($old) || !is_file($old)) continue;
            $mt = (int)@filemtime($old);
            if ($mt < time() - 3600) { @unlink($old); continue; }
            if (substr($old, -4) === '.ser') $files[$old] = $mt;
        }
        if (count($files) > 40) { arsort($files); foreach (array_slice(array_keys($files), 40) as $old) @unlink($old); }
    } catch (\Throwable $e) {}
}

$cards = []; $reading = []; $recent = []; $term_opts = ['author' => [], 'group' => [], 'genre' => [], 'tag' => []];
$total_vols = 0; $scan_vols = 0; $last_scan = 0; $sel_terms = []; $term_total = []; $term_all = [];
if (!defined('LIB_PICK_MIN')) define('LIB_PICK_MIN', 300); // ★ (2026-11-02 06:40) 이보다 많으면 선택 상자 대신 찾아 고르기 칸

if ($db) {
    $total_vols = (int)$db->query("SELECT COUNT(*) FROM volumes WHERE missing=0 AND bidx IN ($BIN)")->fetchColumn();
    $scan_vols = (int)$db->query('SELECT COUNT(*) FROM volumes WHERE missing=0 AND bidx=' . (int)$bidx)->fetchColumn(); // 관리자 스캔 줄(현재 폴더 기준)
    $last_scan = (int)($db->query("SELECT v FROM meta WHERE k='scan_done_" . (int)$bidx . "'")->fetchColumn() ?: 0);
    // ★ (2026-10-31) '전체 폴더(통합)' 화면이면 스캔 줄도 모든 폴더 기준(스캔 버튼도 모든 폴더를 차례로)
    $scan_all = $multi_base && $scope === 'all';
    if ($scan_all) {
        $scan_vols = $total_vols;
        $last_scan = 0;
        foreach ($scope_bidxs as $_sb) $last_scan = max($last_scan, (int)($db->query("SELECT v FROM meta WHERE k='scan_done_" . (int)$_sb . "'")->fetchColumn() ?: 0));
    }

    // 2) ★ (2026-10-29) 권한: 경로 첫 부분(root)마다 한 번만 확인 → 볼 수 없는 root 는 임시 표에 넣고 SQL 에서 제외
    //    (이전: 권을 전부 PHP 로 읽어 확인 — 권 수가 많으면 느림. 결과는 같음)
    //    ★ (2026-10-30) 폴더 여러 개를 함께 볼 수 있어 (폴더 번호, root) 단위로 — 폴더마다 같은 이름의 root 가 있어도 따로 판단
    $db->exec('CREATE TEMP TABLE IF NOT EXISTS lib_deny2 (bidx INTEGER NOT NULL, root TEXT NOT NULL, PRIMARY KEY(bidx, root))');
    $db->exec('DELETE FROM temp.lib_deny2');
    $rq = $db->query("SELECT bidx, root, MIN(path) AS path FROM volumes WHERE bidx IN ($BIN) AND missing=0 GROUP BY bidx, root");
    $dins = $db->prepare('INSERT OR IGNORE INTO temp.lib_deny2(bidx, root) VALUES(?, ?)');
    foreach ($rq->fetchAll() as $r) if (!mc_lib_can_access((int)$r['bidx'], $r['path'])) $dins->execute([(int)$r['bidx'], (string)$r['root']]);
    $VIS = ' AND NOT EXISTS (SELECT 1 FROM temp.lib_deny2 d WHERE d.bidx=volumes.bidx AND d.root=volumes.root)';


    // ★ (2026-11-02 07:52) 계산 결과 저장(서재 첫 화면이 작품 2.7만·작가 4.6천이면 매번 0.4~0.8초) — 같은 사용자·권한·조건이고 서재 DB·내 읽기 기록이
    //   바뀌지 않았으면 저장해 둔 결과를 씀(돌아가기·다시 들어오기·작가 목록 받기가 바로). DB 가 바뀌면(스캔·읽기·편집) 키가 바뀌어 새로 계산
    //   저장 위치: src/lib_cache/(웹에서 막힌 폴더) · 1시간 지난 파일은 가끔 정리
    // ★ (2026-11-02 08:27) 작가·그룹 목록 받기(lib_terms)는 화면 계산 없이 그 목록만 — 거른 화면에서 미리 받을 때 전체 계산을 한 번 더 하던 것
    if (isset($_GET['lib_terms'])) {
        $_k = is_string($_GET['lib_terms']) ? $_GET['lib_terms'] : ''; // ★ (2026-11-02 09:06) 배열이면 경고
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $_out = [];
        if (in_array($_k, ['author', 'group', 'genre', 'tag'], true)) {
            $_vis = $db->query("SELECT DISTINCT series_id FROM volumes WHERE bidx IN ($BIN) AND missing=0 AND series_id IS NOT NULL" . $VIS)->fetchAll(PDO::FETCH_COLUMN);
            if ($_vis) {
                $_vin = implode(',', array_map('intval', $_vis));
                // ★ 종류 조건을 걸면 SQLite 가 용어 쪽부터 훑을 수 있어(02:21 참고) 종류는 PHP 에서 거름
                $_tq = $db->query("SELECT t.id, t.kind, t.name, COUNT(*) c FROM series_terms st JOIN terms t ON t.id=st.term_id WHERE st.series_id IN ($_vin) GROUP BY t.id");
                foreach ($_tq->fetchAll() as $r) if ($r['kind'] === $_k) $_out[] = [(int)$r['id'], (string)$r['name'], (int)$r['c']];
            }
        }
        echo json_encode($_out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    // ★ (2026-11-02 16:17) 화면에 있는 작품들의 읽은 정도만(뒤로 왔을 때 새로고침 없이 %·✓·'읽음 N/M' 갱신) — 그 작품들만 계산해 빠름
    if (isset($_GET['lib_prog'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $_ids = [];
        foreach (explode(',', is_string($_GET['lib_prog']) ? $_GET['lib_prog'] : '') as $_x) { if (ctype_digit($_x)) $_ids[(int)$_x] = true; if (count($_ids) >= 300) break; }
        $_out = [];
        if ($_ids) {
            $_in = implode(',', array_keys($_ids));
            $_rd = mc_lib_user_reads();
            $_cnt = []; $_g = [];
            $_q = $db->query("SELECT bidx, path, series_id, dkey, pages FROM volumes WHERE bidx IN ($BIN) AND missing=0 AND series_id IN ($_in)" . $VIS);
            foreach ($_q->fetchAll() as $v) {
                $s = (int)$v['series_id'];
                $_cnt[$s][$v['dkey']] = true;
                $r = $_rd[(int)$v['bidx'] . '|' . $v['path']] ?? null;
                if (!$r) continue;
                $tot = (int)$r['total'];
                if ($tot <= 0) $tot = (int)$v['pages']; // ★ (2026-11-02 21:13) 기록에 쪽수가 없으면 권의 쪽수로(작품 화면 series.php 와 같은 기준 — 옛 기록은 끝까지 읽어도 '안 읽음'이었음)
                $isfin = !empty($r['finished']) || ($tot > 0 && (int)$r['page'] + 1 >= $tot); // 목록 계산과 같은 기준
                $_g[$s][$v['dkey']] = ($_g[$s][$v['dkey']] ?? false) || $isfin;
            }
            foreach ($_cnt as $s => $dk) {
                $c = count($dk); $fin = 0;
                foreach ($_g[$s] ?? [] as $f) if ($f) $fin++;
                $_out[$s] = [$fin, $c, __('lib_vol_count', $c) . ($fin > 0 ? ' · ' . __('lib_read_count', $fin, $c) : '')];
            }
        }
        echo json_encode($_out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $_lc = lib_browse_cache_key($scope_bidxs, $f_author, $f_group, $f_genre, $f_tag, $f_status, $f_unread, $f_q, $f_sort_c, $lib_reading_n, $lib_recent_n); // ★ 줄 개수 설정이 바뀌면 다시 계산
    $_lcv = lib_browse_cache_key($scope_bidxs); // ★ (2026-11-02 08:27) 작가·그룹 목록 버전(거르기 조건 빼고) — 거르기를 바꿔도 받아 둔 목록을 다시 받지 않게
    $_lcd = $_lc !== '' ? lib_browse_cache_get($_lc) : null;
    if (is_array($_lcd)) {
        // ★ (2026-11-02 08:27) 저장한 8개 이름만 꺼냄(파일 내용이 다른 변수를 덮지 않게)
        foreach (['reads', 'cards', 'reading', 'recent', 'term_opts', 'term_total', 'term_all', 'sel_terms'] as $_n) if (array_key_exists($_n, $_lcd) && is_array($_lcd[$_n])) $$_n = $_lcd[$_n];
    } else {
    // 1) 조건에 맞는 시리즈 후보 (용어·상태·검색은 SQL에서)
    $where = ["s.bidx IN ($BIN)"]; $args = [];
    foreach (['author' => $f_author, 'group' => $f_group, 'genre' => $f_genre, 'tag' => $f_tag] as $kind => $tid) {
        if ($tid) { $where[] = 'EXISTS (SELECT 1 FROM series_terms x WHERE x.series_id=s.id AND x.term_id=?)'; $args[] = $tid; }
    }
    if ($f_status !== '') { $where[] = 's.status = ?'; $args[] = $f_status; }
    if ($f_q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f_q) . '%';
        $where[] = "(s.name LIKE ? ESCAPE '\\' OR EXISTS (SELECT 1 FROM series_terms y JOIN terms t ON t.id=y.term_id WHERE y.series_id=s.id AND t.name LIKE ? ESCAPE '\\'))";
        $args[] = $like; $args[] = $like;
    }
    $st = $db->prepare('SELECT s.* FROM series s WHERE ' . implode(' AND ', $where));
    $st->execute($args);
    $series_rows = [];
    foreach ($st->fetchAll() as $r) $series_rows[(int)$r['id']] = $r;

    // 작품별 집계: 같은 책 사본은 1권(dkey), 최근 추가
    $agg = [];
    if ($series_rows) {
        $q = $db->prepare("SELECT series_id, COUNT(DISTINCT dkey) AS c, MAX(added_at) AS a FROM volumes WHERE bidx IN ($BIN) AND missing=0 AND series_id IS NOT NULL" . $VIS . ' GROUP BY series_id');
        $q->execute();
        foreach ($q->fetchAll() as $r) { $sid = (int)$r['series_id']; if (isset($series_rows[$sid])) $agg[$sid] = $r; }
    }

    // 3) 카드 데이터 — 읽기 기록은 기록이 있는 권만 찾아서 묶음(dkey) 단위로
    $reads = mc_lib_user_reads();
    $rinfo = []; // sid => dkey => [fin, read]
    $rpaths = []; // 폴더 번호 => 경로들
    foreach ($reads as $r) if (in_array((int)$r['bidx'], $scope_bidxs, true) && $r['path'] !== '') $rpaths[(int)$r['bidx']][] = $r['path'];
    foreach ($rpaths as $rb => $plist) foreach (array_chunk(array_values(array_unique($plist)), 400) as $chunk) {
        $q = $db->prepare('SELECT bidx, path, series_id, dkey, pages FROM volumes WHERE bidx=? AND missing=0 AND series_id IS NOT NULL' . $VIS . ' AND path IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $q->execute(array_merge([(int)$rb], $chunk));
        foreach ($q->fetchAll() as $v) {
            $sid = (int)$v['series_id'];
            if (!isset($agg[$sid])) continue;
            $r = $reads[(int)$v['bidx'] . '|' . $v['path']] ?? null;
            if (!$r) continue;
            $tot = (int)$r['total'];
            if ($tot <= 0) $tot = (int)$v['pages']; // ★ (2026-11-02 21:13) 위 lib_prog 와 같음
            $isfin = !empty($r['finished']) || ($tot > 0 && (int)$r['page'] + 1 >= $tot);
            $g = $rinfo[$sid][$v['dkey']] ?? ['fin' => false, 't' => 0];
            $g['fin'] = $g['fin'] || $isfin;
            $g['t'] = max($g['t'], (int)$r['updated_at'] ?: 1);
            $rinfo[$sid][$v['dkey']] = $g;
        }
    }
    foreach ($agg as $sid => $r) {
        $fin = 0; $last_read = 0; $reading_flag = false;
        foreach ($rinfo[$sid] ?? [] as $g) {
            if ($g['fin']) $fin++; else $reading_flag = true;
            $last_read = max($last_read, $g['t']);
        }
        $cards[$sid] = [
            'id' => $sid, 'bidx' => (int)$series_rows[$sid]['bidx'], 'name' => $series_rows[$sid]['name'], 'status' => $series_rows[$sid]['status'],
            'count' => (int)$r['c'], 'fin' => $fin, 'last_read' => $last_read, 'reading' => $reading_flag,
            'added' => (int)$r['a'], 'cover' => null,
        ];
    }
    if ($f_unread) $cards = array_filter($cards, function ($c) { return $c['fin'] < $c['count']; });

    // 대시보드(필터 없을 때): 읽는 중 · 최근 추가
    if (!$filtered) {
        $reading = array_filter($cards, function ($c) { return $c['last_read'] > 0 && ($c['reading'] || $c['fin'] < $c['count']); });
        uasort($reading, function ($a, $b) { return ($b['last_read'] <=> $a['last_read']) ?: strnatcasecmp($a['name'], $b['name']); }); // 같은 시각이면 이름순(★ 2026-10-29 순서 고정)
        $reading = array_slice($reading, 0, $lib_reading_n, true);
        $recent = $cards;
        uasort($recent, function ($a, $b) { return ($b['added'] <=> $a['added']) ?: strnatcasecmp($a['name'], $b['name']); });
        $recent = array_slice($recent, 0, $lib_recent_n, true);
    }

    // 정렬
    // ★ (2026-11-02 16:17) 이름까지 같으면 작품 번호로 — 전에는 DB 가 돌려준 순서라 스캔 뒤 같은 이름 작품끼리 자리가 바뀔 수 있었음
    uasort($cards, function ($a, $b) use ($f_sort_c) {
        if ($f_sort_c === 'recent') return ($b['last_read'] <=> $a['last_read']) ?: strnatcasecmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']);
        if ($f_sort_c === 'added') return ($b['added'] <=> $a['added']) ?: strnatcasecmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']);
        if ($f_sort_c === 'count') return ($b['count'] <=> $a['count']) ?: strnatcasecmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']);
        return strnatcasecmp($a['name'], $b['name']) ?: ($a['bidx'] <=> $b['bidx']) ?: ($a['id'] <=> $b['id']);
    });

    // 필터 선택지(볼 수 있는 시리즈 기준 개수)
    $visible_ids = array_keys($agg);
    if ($visible_ids || $filtered) { // ★ (2026-11-02 20:09) 거른 결과가 0건이어도 선택지는 전체 기준으로 — 전에는 작가·그룹·장르·태그 칸이 사라지고 걸어 둔 작가 거르기가 다음 제출 때 풀렸음
        $ids = $visible_ids ? implode(',', array_map('intval', $visible_ids)) : '0';
        // 필터가 걸려 있어도 선택지는 전체 기준(현재 bidx·권한)으로 — 다른 값으로 바꾸기 쉽게
        $all_vis = $ids;
        if ($filtered) {
            $tmp = [];
            $q = $db->prepare("SELECT DISTINCT series_id FROM volumes WHERE bidx IN ($BIN) AND missing=0 AND series_id IS NOT NULL" . $VIS);
            $q->execute();
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $tmp[(int)$x] = true;
            $all_vis = $tmp ? implode(',', array_keys($tmp)) : '0';
        }
        // ★ (2026-11-02 02:15) 종류마다 따로 상한 — 예전엔 작가·장르·태그를 합쳐 많이 쓰인 순 600개만이라, 작가가 많으면 한 번만 나온 그룹·작가가 목록에서 빠졌음
        //   (그룹 거르기에 많이 쓰인 하나만 보이던 문제)
        // ★ (2026-11-02 02:21) 종류마다 따로 묻던 것(02:15)은 SQLite 가 용어 쪽부터 훑어(작품 수 × 용어 수) 작품이 많으면 몇 분씩 걸렸음
        //   → 예전처럼 한 번에 묻고(작품 쪽부터 — 예전과 같은 계획) 종류별로 많이 쓰인 순으로 PHP 에서 자름
        // ★ (2026-11-02 02:44) 종류마다 300개 — 2,000개(작가 4천 명 서재)면 선택 상자를 열 때 멈칫했음. 나머지는 검색창(이름 검색)으로,
        //   지금 고른 이름은 300개 밖이어도 목록에 넣음(카드의 작가를 눌러 들어온 경우)
        // ★ (2026-11-02 06:40) 300개로 자르던 것(02:44)은 나머지 작가·그룹을 고를 수 없어 되돌림 — 모두 고를 수 있게:
        //   300개 이하인 종류는 예전처럼 선택 상자, 넘는 종류는 '찾아 고르기' 칸(이름을 치면 바로 걸러짐, 목록은 처음 누를 때 따로 받음 → 화면은 가볍게)
        foreach ($db->query("SELECT t.id, t.kind, t.name, COUNT(*) c FROM series_terms st JOIN terms t ON t.id=st.term_id WHERE st.series_id IN ($all_vis) GROUP BY t.id ORDER BY t.kind, c DESC, t.name") as $r) {
            if (!isset($term_opts[$r['kind']])) continue;
            $term_total[$r['kind']] = ($term_total[$r['kind']] ?? 0) + 1; // ★ (2026-11-02 02:32) 종류별 전체 개수('작가 · 전체 (N)')
            $term_all[$r['kind']][] = $r; // 많이 쓰인 순
        }
        foreach ($term_all as $_k => $_list) if (count($_list) <= LIB_PICK_MIN) $term_opts[$_k] = $_list; // 선택 상자로 보일 종류만
        foreach ($term_opts as $k => $list) usort($term_opts[$k], function ($a, $b) { return strnatcasecmp($a['name'], $b['name']); });
    }
    foreach (['author' => $f_author, 'group' => $f_group, 'genre' => $f_genre, 'tag' => $f_tag] as $kind => $tid) {
        if (!$tid) continue;
        // ★ (2026-11-02 10:08) 볼 수 있는 작품에 붙은 이름만(번호를 바꿔 가며 열어 막힌 폴더 작품의 작가·그룹 이름을 알아내던 것)
        //   (2026-11-02 10:28) 거른 결과가 0건이어도 칩이 남게 — 목록 대신 SQL 로 직접 확인
        $st = $db->prepare("SELECT t.name FROM terms t WHERE t.id=? AND t.kind=? AND EXISTS (SELECT 1 FROM series_terms st JOIN volumes ON volumes.series_id=st.series_id WHERE st.term_id=t.id AND volumes.bidx IN ($BIN) AND volumes.missing=0" . $VIS . ")");
        $st->execute([$tid, $kind]);
        $nm = $st->fetchColumn();
        if ($nm !== false) $sel_terms[$kind] = $nm;
    }

        if ($_lc !== '') lib_browse_cache_put($_lc, compact('reads', 'cards', 'reading', 'recent', 'term_opts', 'term_total', 'term_all', 'sel_terms')); // 아래에서 쓰는 것만(작품 행 전체·집계는 빼서 파일을 작게)
    }
}

// ★ (2026-11-03 01:25) 반대 방향이면 뒤집기만(저장한 계산 결과는 기본 방향 그대로 씀)
if ($f_sort !== 'random' && $f_dir !== $f_dir_def && count($cards) > 1) $cards = array_reverse($cards, true);

// ★ (2026-11-02 16:17) 랜덤: 작품 수만큼 한 번 섞기(O(n), 정렬 없음 — 작품이 100만 개여도 수십 ms). 같은 seed 면 같은 순서
$_rand_order = null;
if ($f_sort === 'random' && count($cards) > 1) {
    $_rand_order = array_keys($cards); // 번호만 섞음(카드 배열은 그대로 — 100만 개여도 약 60ms)
    if (class_exists('\\Random\\Randomizer')) { $_rand_order = (new \Random\Randomizer(new \Random\Engine\Mt19937($f_seed)))->shuffleArray($_rand_order); }
    else { mt_srand($f_seed); shuffle($_rand_order); mt_srand(); }
}

// ★ (2026-11-02 06:40) 찾아 고르기 칸의 목록 — ★ 08:27 위(권한 확인 바로 뒤)의 빠른 길로 옮김. DB 를 못 열면 빈 목록
if (isset($_GET['lib_terms'])) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo '[]'; exit; }

$total_cards = count($cards);
$pages = max(1, (int)ceil($total_cards / $per_page));
if ($page > $pages) $page = $pages;
$page_cards = array_slice($cards, ($page - 1) * $per_page, $per_page, true);
if ($_rand_order !== null) { $page_cards = []; foreach (array_slice($_rand_order, ($page - 1) * $per_page, $per_page) as $_k) $page_cards[$_k] = $cards[$_k]; } // ★ 랜덤: 섞은 번호에서 이 쪽 것만
// ★ (2026-10-29) 화면에 보이는 카드만 표지(첫 권 대표 사본)·작가 이름 읽기(이전: 모든 작품을 읽음)
if ($db && ($page_cards || $reading || $recent)) {
    $show = array_keys($page_cards + $reading + $recent);
    $ids = implode(',', array_map('intval', $show));
    $vl = [];
    $q = $db->prepare("SELECT id, bidx, series_id, path, folder, filename, vol_no, vol_label, subtitle, pages, size, mtime, added_at, corder FROM volumes WHERE bidx IN ($BIN) AND missing=0 AND series_id IN ($ids)" . $VIS);
    $q->execute();
    foreach ($q->fetchAll() as $v) $vl[(int)$v['series_id']][] = $v;
    $covers = []; $auth = [];
    foreach ($vl as $sid => $list) {
        $imp = mc_lib_implicit_first($list); // ★ (2026-10-29) 상세 화면과 같은 기준(1권으로 보는 본편이면 부제가 있어도 맨 앞)
        usort($list, function ($a, $b) use ($imp) {
            $va = mc_lib_vol_sort_no($a, $imp); $vb = mc_lib_vol_sort_no($b, $imp); // 상세 화면과 같은 순서(번호 없는 본편이 맨 앞)
            if ($va != $vb) return $va < $vb ? -1 : 1;
            return strnatcasecmp($a['filename'], $b['filename']);
        });
        $covers[$sid] = mc_lib_group_dups($list, $reads)[0]; // ★ fix64 corder(관리자가 정한 사본 순서)도 같이 — 상세 화면의 대표 사본과 같은 표지
    }
    // ★ (2026-10-29) 카드에서 바로 누를 수 있게 작가·장르·태그를 id 와 함께
    foreach ($db->query("SELECT st.series_id, t.id, t.kind, t.name FROM series_terms st JOIN terms t ON t.id=st.term_id WHERE st.series_id IN ($ids) ORDER BY t.name") as $r) $auth[(int)$r['series_id']][$r['kind']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
    foreach (['page_cards', 'reading', 'recent'] as $arr) {
        foreach ($$arr as $sid => $c) {
            ${$arr}[$sid]['cover'] = $covers[$sid] ?? ['path' => ''];
            ${$arr}[$sid]['terms'] = $auth[$sid] ?? [];
        }
    }
}
$csrf_token = generate_csrf_token();
$status_label = ['completed' => __('lib_status_completed'), 'ongoing' => __('lib_status_ongoing'), 'hiatus' => __('lib_status_hiatus')];

/** 현재 필터를 유지한 URL */
function lib_browse_url(array $over = []) {
    global $bidx;
    $p = ['author' => $_GET['author'] ?? '', 'group' => $_GET['group'] ?? '', 'genre' => $_GET['genre'] ?? '', 'tag' => $_GET['tag'] ?? '', 'status' => $_GET['status'] ?? '',
          'unread' => $_GET['unread'] ?? '', 'q' => $_GET['q'] ?? '', 'sort' => $_GET['sort'] ?? '', 'o' => (($_GET['sort'] ?? '') === 'random' || !in_array(($_GET['o'] ?? ''), ['asc', 'desc'], true)) ? '' : $_GET['o'], 'bidx' => $bidx,
          'seed' => (($_GET['sort'] ?? '') === 'random') ? ($_GET['seed'] ?? '') : '']; // ★ (2026-11-02 16:17) 랜덤이면 같은 순서로 쪽 넘김
    foreach ($over as $k => $v) $p[$k] = $v;
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null && $v !== 0 && $v !== '0'; });
    $p['bidx'] = (int)$bidx;
    global $multi_base, $scope;
    if ($multi_base) $p['scope'] = $scope; // ★ (2026-10-30) 보는 범위 유지
    return 'browse.php?' . http_build_query($p);
}

/** 시리즈 카드 출력 */
function lib_card($c, $compact = false) {
    // ★ (2026-10-29) 카드 = 표지·제목 링크(작품 상세) + 작가·장르·태그 링크(그 조건으로 탐색) — 링크 안에 링크를 둘 수 없어 나눔
    global $status_label, $scope, $multi_base, $base_dirs;
    $cb = (int)($c['bidx'] ?? 0); // ★ (2026-10-30) 작품이 속한 콘텐츠 폴더(통합 보기에서는 카드마다 다를 수 있음)
    $cv = $c['cover'];
    $pct = $c['count'] > 0 ? (int)floor($c['fin'] / $c['count'] * 100) : 0;
    $href = 'series.php?sid=' . (int)$c['id'] . '&amp;bidx=' . $cb;
    echo '<div class="sc" data-sid="' . (int)$c['id'] . '">'; // ★ (2026-11-02 16:17) 뒤로 왔을 때 읽은 정도 갱신용
    echo '<a class="sc-cover" href="' . $href . '" title="' . h($c['name']) . '"><img src="' . h(mc_lib_thumb_url($cb, $cv['path'])) . '" alt="" loading="lazy" decoding="async" onerror="this.style.visibility=\'hidden\'">';
    echo '<span class="sc-count">' . __h('lib_vol_count', $c['count']) . '</span>';
    if ($c['fin'] > 0 && $c['fin'] >= $c['count']) echo '<span class="sc-fin">✓</span>';
    if ($pct > 0 && $c['fin'] < $c['count']) echo '<div class="sc-prog"><span style="width:' . $pct . '%"></span></div>';
    echo '</a><div class="sc-text"><a class="sc-name" href="' . $href . '">' . h($c['name']);
    if ($c['status'] !== '' && isset($status_label[$c['status']])) echo ' <span class="sc-status ' . h($c['status']) . '">' . h($status_label[$c['status']]) . '</span>';
    echo '</a>';
    if ($multi_base && $scope === 'all' && isset($base_dirs[$cb])) echo '<div class="sc-base">📁 <span class="mc-noico">' . h(basename(str_replace('\\', '/', rtrim($base_dirs[$cb], '/\\')))) . '</span></div>'; // 통합 보기: 어느 폴더 작품인지
    $terms = $c['terms'] ?? [];
    $limit = ['author' => 20, 'group' => 10, 'genre' => $compact ? 0 : 6, 'tag' => $compact ? 0 : 10];
    $out = '';
    foreach (['author', 'group', 'genre', 'tag'] as $kind) { // ★ (2026-11-02 01:53) 그룹(서클)
        $list = $terms[$kind] ?? [];
        $shown = array_slice($list, 0, $limit[$kind]);
        foreach ($shown as $t) {
            $out .= '<a class="sc-t sc-t-' . $kind . '" href="browse.php?' . $kind . '=' . (int)$t['id'] . '&amp;bidx=' . $cb . ($multi_base ? '&amp;scope=' . h($scope) : '') . '">' . h($t['name']) . '</a>'; // ★ (2026-11-02 22:15) 지금 보는 범위 그대로(다른 탭·뒤로가기로 세션 범위가 바뀌어도)
        }
        if (count($list) > count($shown) && $limit[$kind] > 0) $out .= '<a class="sc-t sc-more" href="' . $href . '">+' . (count($list) - count($shown)) . '</a>';
    }
    if ($out !== '') echo '<div class="sc-terms">' . $out . '</div>';
    echo '<div class="sc-sub2">' . __h('lib_vol_count', $c['count']) . ($c['fin'] > 0 ? ' · ' . __h('lib_read_count', $c['fin'], $c['count']) : '') . '</div>';
    echo '</div></div>';
}
if (!headers_sent()) header('Server-Timing: app;desc="browse";dur=' . round((microtime(true) - $_lib_t0) * 1000, 1)); // ★ (2026-10-29) 처리 시간(개발자 도구 → 네트워크 → 타이밍)
?>
<!DOCTYPE html>
<html lang="<?php echo get_html_lang(); ?>">
<head>
<?php if (function_exists('mc_theme_early_head')) echo mc_theme_early_head(); /* ★ (2026-11-08 fix80) 다크모드 흰 바탕 깜빡임 — 첫 그림부터 어둡게 */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo __h('lib_browse'); ?> - <?php echo h($_branding['page_title'] ?? 'myComix'); ?></title>
<?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
<link rel="stylesheet" href="./css/bootstrap.min.css">
<?php if (isset($darkmode_settings) && ($darkmode_settings['enabled'] ?? false)): ?>
<link rel="stylesheet" href="./css/darkmode.css?v=<?php echo @filemtime(__DIR__ . '/css/darkmode.css') ?: '1'; ?>">
<?php endif; ?>
<style>
  body { background:#f5f3ef; }
  .lib-wrap { max-width:1200px; margin:0 auto; padding:10px 12px 60px; }
  .lib-top { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
  .lib-top h1 { font-size:1.3rem; margin:0; flex:1 1 auto; }
  .lib-filter { display:flex; flex-wrap:wrap; gap:6px; align-items:center; background:#fff; border-radius:8px; padding:10px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .lib-filter select, .lib-filter input[type=search] { height:32px; font-size:.85rem; border:1px solid #ccc; border-radius:4px; padding:0 6px; background:#fff; max-width:180px; }
  .lib-filter input[type=search] { max-width:220px; flex:1 1 160px; }
  .lib-filter label { font-size:.85rem; margin:0; white-space:nowrap; }
  /* ★ (2026-10-31) 보는 범위 선택칸: 왼쪽 선 아이콘(배경) — 다크모드의 background 묶음 규칙에 지워지지 않게 그림 속성만 !important */
  .lib-filter select[name=scope] { padding-left:28px; background-image:url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='%235f6368'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Cpath%20d='m16%206%204%2014'/%3E%3Cpath%20d='M12%206v14'/%3E%3Cpath%20d='M8%208v12'/%3E%3Cpath%20d='M4%204v16'/%3E%3C/svg%3E") !important; background-repeat:no-repeat !important; background-position:8px 50% !important; background-size:16px 16px !important; }
  .dark-mode .lib-filter select[name=scope], [data-theme="dark"] .lib-filter select[name=scope] { background-image:url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='%23bdc1c6'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Cpath%20d='m16%206%204%2014'/%3E%3Cpath%20d='M12%206v14'/%3E%3Cpath%20d='M8%208v12'/%3E%3Cpath%20d='M4%204v16'/%3E%3C/svg%3E") !important; }
  /* ★ (2026-10-31) 제목 줄 아이콘(서재)·즐겨찾기 버튼 별 */
  .lib-h1 { display:inline-flex; align-items:center; gap:6px; } .lib-h1 .mc-ico { flex:0 0 auto; }
  .lib-fav-link { display:inline-flex; align-items:center; gap:4px; } .lib-fav-link .lib-star { color:#f59f00; flex:0 0 auto; }
  .dark-mode .lib-fav-link .lib-star, [data-theme="dark"] .lib-fav-link .lib-star { color:#fbbf24; }
  /* ★ (2026-10-31) 검색 버튼(🔍) — 모양은 그대로, 초록 → 연한 회색(펜닐 요청) */
  .lib-filter .btn-success { background:#f1f3f4; border-color:#dadce0; color:#5f6368; }
  .lib-filter .btn-success:hover { background:#e8eaed; border-color:#c6c9cc; color:#3c4043; }
  .lib-filter .btn-success:not(:disabled):not(.disabled):active { background:#dadce0; border-color:#c6c9cc; color:#202124; }
  .lib-filter .btn-success:focus, .lib-filter .btn-success:not(:disabled):not(.disabled):active:focus { box-shadow:0 0 0 .2rem rgba(95,99,104,.25); }
  .dark-mode .lib-filter .btn-success, [data-theme="dark"] .lib-filter .btn-success { background:#3c4043; border-color:#5f6368; color:#e8eaed !important; }
  .dark-mode .lib-filter .btn-success:hover, [data-theme="dark"] .lib-filter .btn-success:hover { background:#4a4e52; }
  /* ★ (2026-10-31) 보는 범위 — 펼친 목록에도 아이콘(전체 = 책장, 폴더 = 폴더). 기본 선택칸(option)은 아이콘을 못 넣어 스크립트로 만든 목록을 띄움(스크립트가 없으면 기본 선택칸 그대로) */
  .lib-scope-dd { position:relative; display:inline-flex; max-width:220px; min-width:0; }
  .lib-scope-sel-hidden { display:none !important; }
  /* ★ (2026-11-02 06:40) 찾아 고르기 칸(작가·그룹 등 목록이 많을 때) — 선택 상자와 같은 모양, 목록은 범위 메뉴(lib-scope-menu) 모양 */
  .lib-tpick { position:relative; display:inline-flex; max-width:180px; min-width:120px; flex:0 1 180px; }
  .lib-tpick-in { height:32px; width:100%; min-width:0; font-size:.85rem; border:1px solid #ccc; border-radius:4px; padding:0 24px 0 6px; background:#fff url("data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2024%2024'%20fill='none'%20stroke='%235f6368'%20stroke-width='2'%20stroke-linecap='round'%20stroke-linejoin='round'%3E%3Ccircle%20cx='11'%20cy='11'%20r='8'/%3E%3Cpath%20d='m21%2021-4.3-4.3'/%3E%3C/svg%3E") no-repeat right 6px center / 14px 14px; color:#212529; }
  .lib-tpick-in:focus { outline:none; border-color:#1a73e8; box-shadow:0 0 0 2px rgba(26,115,232,.18); }
  .lib-tpick-in.has-sel { font-weight:600; color:#1a56c4; }
  .lib-tpick-menu { max-width:min(320px, calc(100vw - 24px)); }
  .lib-tpick-menu li .lsd-c { flex:0 0 auto; color:#888; font-size:.8rem; }
  .lib-tpick-menu li.lsd-more { cursor:default; color:#888; font-size:.8rem; justify-content:center; }
  .lib-tpick-menu li.lsd-more:hover { background:none; }
  .lib-tpick-menu li.lsd-act { background:#e8f0fe; }
  .dark-mode .lib-tpick-in, [data-theme="dark"] .lib-tpick-in { background-color:#2a2a2a !important; color:#eee !important; border-color:#444; } /* ★ (2026-11-02 19:05) !important — darkmode.css 의 input[type=text] 규칙이 덮어 고른 이름이 파랑이 아닌 흰색이었음 */
  .dark-mode .lib-tpick-in.has-sel, [data-theme="dark"] .lib-tpick-in.has-sel { color:#9ec1ff !important; }
  .dark-mode .lib-tpick-menu li.lsd-act, [data-theme="dark"] .lib-tpick-menu li.lsd-act { background:#3c4043; }
  .lib-scope-btn { height:32px; width:100%; min-width:0; display:inline-flex; align-items:center; gap:6px; padding:0 8px; font-size:.85rem; color:#212529; background:#fff; border:1px solid #ccc; border-radius:4px; cursor:pointer; text-align:left; }
  .lib-scope-btn .lsd-t { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .lib-scope-btn svg { flex:0 0 auto; color:#5f6368; }
  .lib-scope-btn:focus { outline:none; } .lib-scope-btn:focus-visible, .lib-scope-btn[aria-expanded="true"] { border-color:#1a73e8; box-shadow:0 0 0 2px rgba(26,115,232,.18); }
  .lib-scope-menu { position:absolute; left:0; top:calc(100% + 4px); z-index:1050; min-width:100%; max-width:min(360px, calc(100vw - 24px)); max-height:min(60vh, 420px); overflow:auto; margin:0; padding:4px; list-style:none; background:#fff; border:1px solid #dadce0; border-radius:10px; box-shadow:0 8px 24px rgba(60,64,67,.22); }
  .lib-scope-menu[hidden] { display:none; }
  .lib-scope-menu li { display:flex; align-items:center; gap:8px; min-height:36px; padding:6px 10px; border-radius:6px; font-size:.88rem; color:#202124; cursor:pointer; white-space:nowrap; }
  .lib-scope-menu li svg { flex:0 0 auto; color:#5f6368; }
  .lib-scope-menu li .lsd-t { flex:1 1 auto; overflow:hidden; text-overflow:ellipsis; }
  .lib-scope-menu li .lsd-ck { visibility:hidden; color:#1a73e8; }
  .lib-scope-menu li[aria-selected="true"] { color:#1a73e8; font-weight:600; } .lib-scope-menu li[aria-selected="true"] svg { color:#1a73e8; } .lib-scope-menu li[aria-selected="true"] .lsd-ck { visibility:visible; }
  .lib-scope-menu li:hover, .lib-scope-menu li:focus { background:#f1f3f4; outline:none; }
  .lib-scope-menu li:focus-visible { box-shadow:inset 0 0 0 2px #1a73e8; }
  .dark-mode .lib-scope-btn, [data-theme="dark"] .lib-scope-btn { background:#2a2a2a; color:#eee; border-color:#444; }
  .dark-mode .lib-scope-btn svg, [data-theme="dark"] .lib-scope-btn svg, .dark-mode .lib-scope-menu li svg, [data-theme="dark"] .lib-scope-menu li svg { color:#bdc1c6; }
  .dark-mode .lib-scope-menu, [data-theme="dark"] .lib-scope-menu { background:#2a2a2a; border-color:#444; box-shadow:0 8px 24px rgba(0,0,0,.5); }
  .dark-mode .lib-scope-menu li, [data-theme="dark"] .lib-scope-menu li { color:#e8eaed; }
  .dark-mode .lib-scope-menu li:hover, .dark-mode .lib-scope-menu li:focus, [data-theme="dark"] .lib-scope-menu li:hover, [data-theme="dark"] .lib-scope-menu li:focus { background:#3c4043; }
  .dark-mode .lib-scope-menu li[aria-selected="true"], [data-theme="dark"] .lib-scope-menu li[aria-selected="true"], .dark-mode .lib-scope-menu li[aria-selected="true"] svg, [data-theme="dark"] .lib-scope-menu li[aria-selected="true"] svg, .dark-mode .lib-scope-menu li .lsd-ck, [data-theme="dark"] .lib-scope-menu li .lsd-ck { color:#8ab4f8; }
  /* ★ (2026-10-30) 모바일 세로(576px 이하): 검색창은 한 줄 꽉, 작가·장르·태그·상태는 두 개씩 같은 폭(홀수면 마지막이 한 줄),
     '안 읽은 작품만' 한 줄, 정렬(남는 폭)·🔍·보기 전환 한 줄 / 위 제목 줄은 글자 크기만 조금 작게 — 더 넓은 화면은 그대로 */
  @media (max-width:576px) {
    .lib-top { gap:6px; }
    .lib-top h1 { font-size:1.15rem; white-space:nowrap; }
    .lib-filter { gap:6px; padding:8px; }
    .lib-filter input[type=search] { flex:1 1 100%; max-width:none; min-width:0; }
    .lib-filter select[name=scope], .lib-filter .lib-scope-dd { flex:1 1 100%; max-width:none; min-width:0; }
    .lib-filter .lib-tpick { flex:1 1 calc(50% - 3px); max-width:none; min-width:0; } /* ★ (2026-11-02 06:40) */
    .lib-filter select[name=author], .lib-filter select[name=group], .lib-filter select[name=genre], .lib-filter select[name=tag], .lib-filter select[name=status] { flex:1 1 calc(50% - 3px); max-width:none; min-width:0; }
    .lib-filter label { flex:1 1 100%; }
    .lib-filter select[name=sort] { flex:1 1 auto; max-width:none; min-width:0; }
    .lib-filter .lib-filter-sp { display:none; }
  }
  @media (max-width:360px) { .lib-top .lib-top-lbl { display:none; } } /* 아주 좁은 화면: '즐겨찾기 책장' 글자 숨김(⭐ 버튼만, 제목 줄이 한 줄로) */
  .lib-sel { display:flex; flex-wrap:wrap; gap:5px; margin:8px 0 0; }
  .lib-sel a { display:inline-block; padding:2px 10px; border-radius:14px; background:#343a40; color:#fff !important; font-size:.8rem; text-decoration:none !important; }
  .lib-sec h2 { font-size:1.05rem; margin:18px 0 8px; }
  /* ★ (2026-11-03 01:26) 정렬 칩(폴더/파일 목록 정렬 칩과 같은 모양) */
  .lib-sortbar { display:flex; flex-wrap:wrap; gap:6px; margin:0 0 12px; }
  /* ★ (2026-11-03 21:45) 정렬 칩은 '전체 작품' 제목 줄 오른쪽으로(정렬은 이 목록에만 적용 — 읽는 중·최근 추가는 그대로) / 좁으면 제목 아래 한 줄(넘치면 옆으로 밀기) */
  .lib-sec-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px 12px; margin:18px 0 10px; }
  .lib-sec-head h2 { margin:0; }
  .lib-sec-head .lib-sortbar { margin:0; flex-wrap:nowrap; overflow-x:auto; max-width:100%; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
  .lib-sec-head .lib-sortbar::-webkit-scrollbar { display:none; }
  .lib-sec-head .lib-chip { flex:0 0 auto; }
  @media (max-width:575.98px) { .lib-sec-head .lib-sortbar { width:100%; } .lib-sec-head .lib-chip { padding:0 10px; } }
  .lib-chip { height:34px; padding:0 12px; border-radius:17px; display:inline-flex; align-items:center; gap:3px; font-size:13px; white-space:nowrap; border:1px solid #d5d9de; background:#fff; color:#3c4043 !important; text-decoration:none !important; }
  .lib-chip:hover { background:#f1f3f4; }
  .lib-chip.on { background:#202124; border-color:#202124; color:#fff !important; font-weight:700; }
  .lib-chip-dir { font-size:12px; opacity:.85; }
  .lib-chip-ico { flex:0 0 auto; margin-right:2px; }
  @media (max-width:359.98px) { .lib-sortbar { gap:4px; } .lib-chip { padding:0 8px; font-size:12px; } .lib-sec-head .lib-chip { padding:0 8px; } } /* ★ (2026-11-03 23:34) 제목 줄 칩도 좁은 화면 여백(위 규칙이 더 강해 10px 로 남았음) */
  .dark-mode .lib-chip, [data-theme="dark"] .lib-chip { background:#1e1e1e; border-color:#444; color:#ddd !important; }
  .dark-mode .lib-chip.on, [data-theme="dark"] .lib-chip.on { background:#e8eaed; border-color:#e8eaed; color:#121212 !important; }
  .lib-view-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; padding:0; border:1px solid #dcdcdc; border-radius:7px; background:#fff; color:#555; cursor:pointer; box-shadow:none; outline:none; }
  .lib-view-btn:hover { background:#f3f3f3; } .lib-view-btn.active { background:#e9e9e9; border-color:#d0d0d0; color:#333; }
  .lib-view-btn:focus { outline:none; box-shadow:none; } .lib-view-btn:focus-visible { outline:2px solid #80bdff; outline-offset:1px; }
  /* 시리즈 카드 — 그리드 */
  .sc-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(130px, 1fr)); gap:16px 12px; }
  @media (max-width:576px) { .sc-grid { grid-template-columns:repeat(3, 1fr); gap:12px 8px; } }
  .sc-row { display:grid; grid-auto-flow:column; grid-auto-columns:130px; gap:12px; overflow-x:auto; padding-bottom:6px; }
  @media (max-width:576px) { .sc-row { grid-auto-columns:30%; } }
  /* ★ (2026-10-29) 카드: 표지·제목 링크 + 작가·장르·태그 링크, 제목 전체 표시(자르지 않음), 권 수는 표지 오른쪽 위 */
  .sc { display:block; min-width:0; }
  .sc a { text-decoration:none !important; }
  .sc-cover { position:relative; display:block; width:100%; aspect-ratio:3/4; border-radius:4px; overflow:hidden; background:#ddd; box-shadow:0 5px 10px -4px rgba(0,0,0,.45); }
  .sc-cover img { width:100%; height:100%; object-fit:cover; display:block; }
  .sc-count { position:absolute; right:4px; top:4px; background:rgba(0,0,0,.62); color:#fff; font-size:.72rem; line-height:1.5; border-radius:3px; padding:0 5px; }
  .sc-fin { position:absolute; top:4px; left:4px; background:#28a745; color:#fff; border-radius:50%; width:22px; height:22px; font-size:13px; line-height:22px; text-align:center; }
  .sc-prog { position:absolute; left:0; right:0; bottom:0; height:4px; background:rgba(0,0,0,.25); }
  /* ★ (2026-11-04 17:55) 표지 위 권 수·✓ 를 표지 폭에 맞춰(폰 3열은 지금 크기, 넓은 표지는 조금 크게) — 컨테이너 단위를 모르는 옛 브라우저는 위 크기 그대로 */
  .sc-cover { container-type:inline-size; }
  .sc-cover > .sc-count { font-size:clamp(11px, 8cqw, 14px); }
  .sc-cover > .sc-fin { width:clamp(20px, 15cqw, 28px); height:clamp(20px, 15cqw, 28px); line-height:clamp(20px, 15cqw, 28px); font-size:clamp(12px, 9cqw, 17px); }
  /* ★ (2026-11-05 17:55) 다 읽음 ✓ — 큰 녹색 원 → 옆 '권 수'(1권)와 같은 반투명 검정 작은 상자 + 연두색 체크(펜닐 요청: 권 표시보다 크고 촌스러움)
     크기는 권 수 표시와 같은 글자 크기·높이(표지 폭 비례 그대로) / 마크업·갱신 스크립트는 그대로(✓ 는 mc-icons.js 가 선 아이콘으로 바꿈) */
  .sc-cover > .sc-fin { width:auto; height:1.5em; min-width:0; padding:0 .3em; border-radius:3px; background:rgba(0,0,0,.62); color:#8be3a5 !important; /* 높이 = 옆 표시와 같게(글자 크기 × 줄 높이) */
    font-size:.72rem; font-size:clamp(11px, 8cqw, 14px); line-height:1.5; font-weight:700; display:inline-flex; align-items:center; justify-content:center; }
  .sc-cover > .sc-fin svg { width:1.1em !important; height:1.1em !important; vertical-align:0 !important; color:#8be3a5 !important; stroke:currentColor !important; stroke-width:3 !important; }
  .sc-prog > span { display:block; height:100%; background:#f39c12; }
  .sc-name { display:block; color:inherit !important; font-size:.85rem; margin-top:5px; line-height:1.3; word-break:keep-all; overflow-wrap:anywhere; }
  .sc-name:hover { text-decoration:none !important; } /* ★ (2026-10-30) 제목에 마우스를 올려도 밑줄 없음(사용자 요청) */
  .sc-terms { display:flex; flex-wrap:wrap; gap:3px 4px; margin-top:3px; }
  /* ★ 칩: 모서리가 둥근 사각형(테두리 + 옅은 배경) */
  .sc-t { display:inline-block; max-width:100%; font-size:.72rem; line-height:1.4; padding:1px 6px; border-radius:4px; border:1px solid transparent; overflow-wrap:anywhere; }
  .sc-t-author { background:#eef4ff; border-color:#bcd0f5; color:#1a56c4 !important; }
  .sc-t-group  { background:#eefaf1; border-color:#b5e0c2; color:#1e7a3c !important; } /* ★ (2026-11-02) 그룹 */
  .sc-t-genre  { background:#fdf0ee; border-color:#efc3bc; color:#b23121 !important; }
  .sc-t-tag    { background:#f1f2f4; border-color:#d3d6db; color:#444 !important; }
  .sc-more { background:transparent; border-color:transparent; color:#888 !important; }
  .sc-t:hover { filter:brightness(.93); }
  .sc-grid .sc-sub2, .sc-row .sc-sub2 { display:none; }
  .sc-base { font-size:.7rem; color:#888; margin-top:2px; overflow-wrap:anywhere; } /* ★ (2026-10-30) 통합 보기의 폴더 이름 */
  .sc-status { font-size:.65rem; padding:0 5px; border-radius:3px; background:#28a745; color:#fff; vertical-align:middle; white-space:nowrap; }
  .sc-status.ongoing { background:#17a2b8; } .sc-status.hiatus { background:#6c757d; }
  /* 시리즈 카드 — 목록 */
  .sc-list .sc { display:flex; align-items:flex-start; gap:10px; padding:8px 4px; border-bottom:1px solid #e2ddd5; }
  .sc-list .sc-cover { flex:0 0 60px; width:60px; height:80px; aspect-ratio:auto; }
  .sc-list .sc-count { display:none; }
  .sc-list .sc-text { min-width:0; flex:1 1 auto; padding-top:2px; }
  .sc-list .sc-name { font-size:1rem; margin-top:0; }
  .sc-list .sc-sub2 { font-size:.75rem; color:#999; margin-top:2px; }
  .lib-pager { display:flex; gap:4px; justify-content:center; flex-wrap:wrap; margin:20px 0; }
  .lib-pager a, .lib-pager span { padding:4px 10px; border-radius:4px; border:1px solid #ccc; text-decoration:none !important; color:inherit; font-size:.85rem; }
  .lib-pager span.cur { background:#343a40; color:#fff; border-color:#343a40; }
  .lib-empty { text-align:center; color:#888; padding:50px 10px; }
  .lib-auto { display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap; }
  #libScanMsg:not(:empty) { flex:1 1 100%; } /* 진행 문구는 한 줄 전체 */
  .lib-auto select { height:28px; font-size:.8rem; border:1px solid #ccc; border-radius:4px; padding:0 4px; background:#fff; }
  .dark-mode .lib-auto select, [data-theme="dark"] .lib-auto select { background:#2a2a2a; color:#eee; border-color:#444; }
  .lib-admin { font-size:.85rem; color:#666; background:#fff8e1; border:1px solid #f3e1a5; border-radius:6px; padding:8px 10px; margin-top:10px; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
  /* 다크모드 */
  .dark-mode body, [data-theme="dark"] body, body.dark-mode { background:#121212 !important; }
  .dark-mode .lib-filter, [data-theme="dark"] .lib-filter { background:#1e1e1e; box-shadow:none; }
  .dark-mode .lib-filter select, .dark-mode .lib-filter input, [data-theme="dark"] .lib-filter select, [data-theme="dark"] .lib-filter input { background:#2a2a2a; color:#eee; border-color:#444; }
  .dark-mode .sc-list .sc, [data-theme="dark"] .sc-list .sc { border-color:#333; }
  .dark-mode .sc-t-author, [data-theme="dark"] .sc-t-author { background:#1a2a45; border-color:#34507f; color:#9ec1ff !important; }
  .dark-mode .sc-t-group, [data-theme="dark"] .sc-t-group { background:#17331f; border-color:#2f6b40; color:#9be3b0 !important; }
  .dark-mode .sc-t-genre, [data-theme="dark"] .sc-t-genre { background:#3c1f1c; border-color:#6e3a33; color:#ffb4a8 !important; }
  .dark-mode .sc-t-tag, [data-theme="dark"] .sc-t-tag { background:#26292d; border-color:#454a50; color:#ddd !important; }
  .dark-mode .lib-admin, [data-theme="dark"] .lib-admin { background:#2a2416; border-color:#4a3f20; color:#ccc; }
  .dark-mode .lib-view-btn, [data-theme="dark"] .lib-view-btn { background:#1e1e1e; border-color:#444; color:#bbb; }
  .dark-mode .lib-view-btn.active, [data-theme="dark"] .lib-view-btn.active { background:#3a3a3a; color:#eee; }
  #scrollTopBtn { position:fixed; bottom:90px; right:20px; z-index:9999; font-size:18px; background-color:rgba(0,0,0,.3); color:#fff; border:none; border-radius:50%; width:40px; height:40px; padding:0; cursor:pointer; outline:none; display:flex; align-items:center; justify-content:center; }
  #scrollTopBtn:hover { background-color:rgba(0,0,0,.5); }
</style>
<?php // ★ (2026-10-31) 관리자 '라이브러리' 썸네일 크기 — 값이 있을 때만 출력(없으면 기존 화면 그대로). 정수 px만 들어감
$_mc_thumb_css = function_exists('mc_thumb_layout_css') ? mc_thumb_layout_css('lib') : ''; if ($_mc_thumb_css !== ''): ?>
<style id="mcThumbLayout"><?php echo $_mc_thumb_css; ?></style>
<?php endif; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-11-05 23:15) 아이콘 변환을 본문보다 먼저 시작 — 예전 이모지가 잠깐 보였다가 바뀌던 것(페이지 끝의 같은 스크립트는 다시 실행되지 않음) */ ?>
</head>
<body>
<div class="lib-wrap">
  <div class="lib-top">
    <a id="libBack" class="btn btn-sm btn-outline-secondary" href="index.php<?php echo $bidx_query; ?>">← <?php echo __h('fav_shelf_back'); ?></a>
    <h1 class="lib-h1"><?php // ★ (2026-10-31) '서재' — 꽂힌 책 아이콘(예전 🔎) ?><svg class="mc-ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect width="8" height="18" x="3" y="3" rx="1"/><path d="M7 3v18"/><path d="M20.4 18.9c.2.5-.1 1.1-.6 1.3l-1.9.7c-.5.2-1.1-.1-1.3-.6L11.1 5.1c-.2-.5.1-1.1.6-1.3l1.9-.7c.5-.2 1.1.1 1.3.6Z"/></svg> <?php echo __h('lib_browse'); ?></h1>
    <?php // ★ (2026-11-03 01:31) 즐겨찾기 버튼은 아래 서재·즐겨찾기·북마크 이동 줄로 옮김 ?>
  </div>
<?php echo mc_shelf_nav('lib', $bidx_query); // ★ (2026-11-03 01:31) 서재·즐겨찾기·북마크 이동 ?>

<?php if (!$db): ?>
  <div class="lib-empty"><?php echo __h('lib_no_sqlite'); ?></div>
<?php else: ?>
  <form class="lib-filter" method="get" action="browse.php" id="libFilter">
    <input type="hidden" name="bidx" value="<?php echo (int)$bidx; ?>">
    <?php if ($multi_base): // ★ (2026-10-30) 보는 범위: 전체 폴더(통합) 또는 폴더 하나 ?>
    <?php // ★ (2026-10-31) 선택칸 목록(option) 안은 아이콘(SVG)을 못 넣어 이모지(📚·📁)는 빼고, 선택칸 왼쪽에 선 아이콘(배경 그림) 하나 ?>
    <select name="scope" aria-label="<?php echo __h('lib_scope'); ?>" onchange="this.form.submit()" autocomplete="off"><?php // ★ (2026-10-31) 뒤로 가기 때 브라우저가 예전 선택값을 되살려 아래 아이콘 목록·화면 내용과 어긋나지 않게 ?>
      <option value="all"<?php echo $scope === 'all' ? ' selected' : ''; ?>><?php echo __h('lib_scope_all'); ?></option>
      <?php foreach ($base_dirs as $_bi => $_bd): ?><option value="<?php echo (int)$_bi; ?>"<?php echo $scope === (string)$_bi ? ' selected' : ''; ?>><?php echo h(basename(str_replace('\\', '/', rtrim($_bd, '/\\')))); ?></option><?php endforeach; ?>
    </select>
    <script>
    // ★ (2026-10-31) 보는 범위 — 펼친 목록에도 아이콘(전체 = 책장, 폴더 = 폴더). 값은 위 선택칸에 넣고 그대로 제출(서버 처리 같음)
    (function () {
      var sel = document.querySelector('.lib-filter select[name=scope]');
      if (!sel || !sel.form || !document.createElement('div').closest) return;
      var NS = 'http://www.w3.org/2000/svg';
      var P = {
        all: '<path d="m16 6 4 14"/><path d="M12 6v14"/><path d="M8 8v12"/><path d="M4 4v16"/>',
        dir: '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        ck: '<path d="M20 6 9 17l-5-5"/>',
        dn: '<path d="m6 9 6 6 6-6"/>'
      };
      function ico(k, cls) { var s = document.createElementNS(NS, 'svg'); s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('width', '16'); s.setAttribute('height', '16'); s.setAttribute('fill', 'none'); s.setAttribute('stroke', 'currentColor'); s.setAttribute('stroke-width', '2'); s.setAttribute('stroke-linecap', 'round'); s.setAttribute('stroke-linejoin', 'round'); s.setAttribute('aria-hidden', 'true'); if (cls) s.setAttribute('class', cls); s.innerHTML = P[k]; return s; }
      function txt(t) { var sp = document.createElement('span'); sp.className = 'lsd-t'; sp.textContent = t; return sp; }
      var dd = document.createElement('div'); dd.className = 'lib-scope-dd';
      var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'lib-scope-btn';
      btn.setAttribute('aria-haspopup', 'listbox'); btn.setAttribute('aria-expanded', 'false'); btn.setAttribute('aria-label', sel.getAttribute('aria-label') || '');
      var cur = sel.options[sel.selectedIndex] || sel.options[0];
      btn.appendChild(ico(cur.value === 'all' ? 'all' : 'dir')); btn.appendChild(txt(cur.textContent)); btn.appendChild(ico('dn'));
      var ul = document.createElement('ul'); ul.className = 'lib-scope-menu'; ul.setAttribute('role', 'listbox'); ul.hidden = true;
      Array.prototype.forEach.call(sel.options, function (op) {
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.tabIndex = -1; li.setAttribute('data-v', op.value);
        li.setAttribute('aria-selected', op.selected ? 'true' : 'false');
        li.appendChild(ico(op.value === 'all' ? 'all' : 'dir')); li.appendChild(txt(op.textContent)); li.appendChild(ico('ck', 'lsd-ck'));
        ul.appendChild(li);
      });
      dd.appendChild(btn); dd.appendChild(ul);
      sel.parentNode.insertBefore(dd, sel.nextSibling);
      sel.classList.add('lib-scope-sel-hidden');
      var items = function () { return Array.prototype.slice.call(ul.querySelectorAll('li')); };
      function open(focusSel) {
        ul.hidden = false; btn.setAttribute('aria-expanded', 'true');
        var it = ul.querySelector('li[aria-selected="true"]') || items()[0];
        if (focusSel && it) it.focus(); else if (it) it.scrollIntoView({ block: 'nearest' });
      }
      function close(back) { if (ul.hidden) return; ul.hidden = true; btn.setAttribute('aria-expanded', 'false'); if (back) btn.focus(); }
      function pick(li) {
        var v = li.getAttribute('data-v');
        close(false);
        if (v === sel.value) return;
        sel.value = v;
        sel.form.submit();
      }
      btn.addEventListener('click', function () { if (ul.hidden) open(false); else close(false); });
      btn.addEventListener('keydown', function (e) { if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(true); } });
      ul.addEventListener('click', function (e) { var li = e.target.closest('li'); if (li) pick(li); });
      ul.addEventListener('keydown', function (e) {
        var list = items(), i = list.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') { e.preventDefault(); (list[Math.min(list.length - 1, i + 1)] || list[0]).focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); (list[Math.max(0, i - 1)] || list[0]).focus(); }
        else if (e.key === 'Home') { e.preventDefault(); list[0].focus(); }
        else if (e.key === 'End') { e.preventDefault(); list[list.length - 1].focus(); }
        else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (i >= 0) pick(list[i]); }
        else if (e.key === 'Escape') { e.preventDefault(); close(true); }
        else if (e.key === 'Tab') close(false);
      });
      document.addEventListener('mousedown', function (e) { if (!dd.contains(e.target)) close(false); });
      document.addEventListener('touchstart', function (e) { if (!dd.contains(e.target)) close(false); }, { passive: true });
    })();
    </script>
    <?php endif; ?>
    <input type="search" name="q" value="<?php echo h($f_q); ?>" placeholder="<?php echo __h('lib_search_ph'); ?>" aria-label="<?php echo __h('lib_search_ph'); ?>">
    <?php foreach (['author' => ['lib_author', $f_author], 'group' => ['lib_group', $f_group], 'genre' => ['lib_genre', $f_genre], 'tag' => ['lib_tag', $f_tag]] as $kind => [$lk, $cur]):
      if (($term_total[$kind] ?? 0) > LIB_PICK_MIN || (in_array($kind, ['author', 'group'], true) && ($term_total[$kind] ?? 0) > 0)): // ★ (2026-11-02 06:40) 찾아 고르기 칸(목록이 많은 종류) — ★ (2026-11-02 16:16) 작가·그룹은 개수와 상관없이 늘 찾아 고르기(펜닐 요청: 그룹도 작가처럼) ?>
      <div class="lib-tpick" data-kind="<?php echo $kind; ?>">
        <input type="hidden" name="<?php echo $kind; ?>" value="<?php echo $cur ? (int)$cur : ''; ?>">
        <input type="text" class="lib-tpick-in" autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-label="<?php echo __h($lk); ?>"
          placeholder="<?php echo __h($lk); ?> · <?php echo __h('lib_all'); ?> (<?php echo number_format((int)$term_total[$kind]); ?>)" value="<?php echo h($sel_terms[$kind] ?? ''); ?>" data-sel="<?php echo h($sel_terms[$kind] ?? ''); ?>">
        <ul class="lib-scope-menu lib-tpick-menu" role="listbox" hidden></ul>
      </div>
      <?php continue; endif;
      if (!$term_opts[$kind] && !$cur) continue; ?>
      <select name="<?php echo $kind; ?>" aria-label="<?php echo __h($lk); ?>" onchange="this.form.submit()">
        <option value=""><?php echo __h($lk); ?> · <?php echo __h('lib_all'); ?><?php if (!empty($term_total[$kind])) echo ' (' . number_format((int)$term_total[$kind]) . ')'; /* ★ (2026-11-02 02:32) 전체 개수 */ ?></option>
        <?php foreach ($term_opts[$kind] as $t): ?>
          <option value="<?php echo (int)$t['id']; ?>"<?php echo (int)$t['id'] === $cur ? ' selected' : ''; ?>><?php echo h($t['name']); ?> (<?php echo (int)$t['c']; ?>)</option>
        <?php endforeach; ?>
      </select>
    <?php endforeach; ?>
    <select name="status" aria-label="<?php echo __h('lib_status'); ?>" onchange="this.form.submit()">
      <option value=""><?php echo __h('lib_status'); ?> · <?php echo __h('lib_all'); ?></option>
      <?php foreach ($status_label as $k => $l): ?><option value="<?php echo $k; ?>"<?php echo $f_status === $k ? ' selected' : ''; ?>><?php echo h($l); ?></option><?php endforeach; ?>
    </select>
    <label><input type="checkbox" name="unread" value="1"<?php echo $f_unread ? ' checked' : ''; ?> onchange="this.form.submit()"> <?php echo __h('lib_unread_only'); ?></label>
    <?php // ★ (2026-11-03 01:26) 정렬은 아래 칩 줄로(폴더/파일 목록과 같은 모양) — 거르기를 바꿔 다시 찾을 때도 지금 정렬 그대로 ?>
    <input type="hidden" name="sort" autocomplete="off" value="<?php echo h($f_sort); ?>">
    <input type="hidden" name="o" autocomplete="off" value="<?php echo $f_sort !== 'random' && $f_dir !== $f_dir_def ? h($f_dir) : ''; ?>"<?php echo $f_sort !== 'random' && $f_dir !== $f_dir_def ? '' : ' disabled'; ?>>
    <input type="hidden" name="seed" autocomplete="off" value="<?php echo $f_sort === 'random' ? (int)$f_seed : ''; ?>"<?php echo $f_sort === 'random' ? '' : ' disabled'; ?>>
    <button type="submit" class="btn btn-sm btn-success">🔍</button>
    <span class="lib-filter-sp" style="flex:1 1 auto"></span>
    <button type="button" class="lib-view-btn" data-view="grid" title="<?php echo __h('fav_shelf_view_grid'); ?>" aria-label="<?php echo __h('fav_shelf_view_grid'); ?>"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/></svg></button>
    <button type="button" class="lib-view-btn" data-view="list" title="<?php echo __h('fav_shelf_view_list'); ?>" aria-label="<?php echo __h('fav_shelf_view_list'); ?>"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg></button>
  </form>
  <?php // ★ 정렬 칩 — 고른 칩을 다시 누르면 방향이 바뀜(↓ 큰 것·가나다 / ↑ 반대), 랜덤은 누를 때마다 새로 섞음
  $_chips = [['name', 'lib_chip_name'], ['recent', 'lib_chip_recent'], ['added', 'lib_chip_added'], ['count', 'lib_chip_count']];
  ob_start(); // ★ (2026-11-03 21:45) 아래 '전체 작품' 제목 줄에 넣음 ?>
  <div class="lib-sortbar" role="group" aria-label="<?php echo __h('fav_shelf_sort'); ?>">
    <?php foreach ($_chips as [$_sk, $_lk]):
      $_on = ($f_sort === $_sk);
      $_def = ($_sk === 'name') ? 'asc' : 'desc';
      $_next = $_on ? ($f_dir === 'asc' ? 'desc' : 'asc') : $_def;
      $_href = lib_browse_url(['sort' => $_sk, 'o' => $_next === $_def ? '' : $_next, 'seed' => '', 'page' => '']);
      $_shown = $_on ? $f_dir : $_def; ?>
      <a class="lib-chip<?php echo $_on ? ' on' : ''; ?>" href="<?php echo h($_href); ?>" data-sort="<?php echo $_sk; ?>.<?php echo $_next; ?>"<?php echo $_on ? ' aria-current="true"' : ''; ?> title="<?php echo __h($_lk . '_tip'); ?>"><?php echo __h($_lk); ?> <span class="lib-chip-dir" aria-hidden="true"><?php echo $_shown === $_def ? '↓' : '↑'; /* ↓ = 기본 방향(이름 가나다·나머지 최근/많은 것부터) */ ?></span></a>
    <?php endforeach; ?>
    <a class="lib-chip lib-chip-rand<?php echo $f_sort === 'random' ? ' on' : ''; ?>" href="<?php echo h(lib_browse_url(['sort' => 'random', 'o' => '', 'seed' => (string)random_int(1, 999999999), 'page' => ''])); ?>" data-sort="random.asc" title="<?php echo __h($f_sort === 'random' ? 'lib_sort_reshuffle' : 'lib_sort_random'); ?>"><?php // ★ (2026-11-03 09:43) 🔀 그림 글자(파란 네모) → 다른 칩·화면 아이콘과 같은 선 아이콘(글자색 따라감 — 고르면 흰색) ?><svg class="lib-chip-ico" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m18 14 4 4-4 4"/><path d="m18 2 4 4-4 4"/><path d="M2 18h1.973a4 4 0 0 0 3.3-1.7l5.454-8.6a4 4 0 0 1 3.3-1.7H22"/><path d="M2 6h1.972a4 4 0 0 1 3.6 2.2"/><path d="M22 18h-6.041a4 4 0 0 1-3.3-1.8l-.359-.45"/></svg> <?php echo __h('lib_chip_random'); ?></a>
  </div>
  <?php $_sortbar_html = ob_get_clean(); ?>
  <?php if ($sel_terms || $f_status !== '' || $f_unread || $f_q !== ''): ?>
  <div class="lib-sel">
    <?php foreach ($sel_terms as $kind => $nm): ?><a href="<?php echo h(lib_browse_url([$kind => ''])); ?>"><?php echo h($nm); ?> ✕</a><?php endforeach; ?>
    <?php if ($f_status !== ''): ?><a href="<?php echo h(lib_browse_url(['status' => ''])); ?>"><?php echo h($status_label[$f_status]); ?> ✕</a><?php endif; ?>
    <?php if ($f_unread): ?><a href="<?php echo h(lib_browse_url(['unread' => ''])); ?>"><?php echo __h('lib_unread_only'); ?> ✕</a><?php endif; ?>
    <?php if ($f_q !== ''): ?><a href="<?php echo h(lib_browse_url(['q' => ''])); ?>">"<?php echo h($f_q); ?>" ✕</a><?php endif; ?>
    <a href="browse.php?bidx=<?php echo (int)$bidx; ?><?php echo $multi_base ? '&amp;scope=' . h($scope) : ''; ?>" style="background:#6c757d;"><?php echo __h('lib_clear_filters'); ?></a>
  </div>
  <?php endif; ?>

  <?php if ($is_admin): ?>
  <div class="lib-admin" id="libAdmin">
    <span><?php if ($multi_base && !empty($scan_all)): ?>📁 <?php echo __h('lib_scope_all'); ?> · <?php elseif ($multi_base): ?>📁 <?php echo h(basename(str_replace('\\', '/', rtrim($base_dirs[$bidx], '/\\')))); ?> · <?php endif; ?><?php echo __h('lib_index_status', number_format($scan_vols)); ?> · <?php echo $last_scan ? __h('lib_last_scan', date('Y-m-d H:i', $last_scan)) : __h('lib_never_scanned'); ?></span>
    <button type="button" class="btn btn-sm btn-warning" id="libScanBtn" title="<?php echo __h('lib_scan_quick_help'); ?>">🔄 <?php echo __h('lib_scan_quick'); ?></button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="libScanFullBtn" title="<?php echo __h('lib_scan_full_help'); ?>">♻️ <?php echo __h('lib_scan_full'); ?></button>
    <button type="button" class="btn btn-sm btn-outline-danger" id="libScanStop" style="display:none;">⏹ <?php echo __h('lib_scan_stop'); ?></button>
    <?php $_auto = mc_lib_auto_cfg($db); // ★ (2026-10-30) 자동 스캔 설정 ?>
    <span class="lib-auto" title="<?php echo __h('lib_scan_auto_help'); ?>">
      <label style="margin:0;"><input type="checkbox" id="libAutoOn"<?php echo $_auto['on'] ? ' checked' : ''; ?>> <?php echo __h('lib_scan_auto_label'); ?></label>
      <select id="libAutoMin" aria-label="<?php echo __h('lib_scan_auto_label'); ?>">
        <?php foreach ([5, 10, 30, 60, 180, 360, 1440] as $_m): ?><option value="<?php echo $_m; ?>"<?php echo $_auto['min'] === $_m ? ' selected' : ''; ?>><?php echo $_m < 60 ? __h('lib_scan_every_min', $_m) : ($_m < 1440 ? __h('lib_scan_every_hour', $_m / 60) : __h('lib_scan_every_day')); ?></option><?php endforeach; ?>
      </select>
      <span id="libAutoMsg" style="font-size:.8rem;color:#28a745;"></span>
    </span>
    <span id="libScanMsg"></span>
  </div>
  <?php endif; ?>

  <?php if (!$filtered && $reading): ?>
  <div class="lib-sec"><h2>📖 <?php echo __h('lib_reading'); ?></h2><div class="sc-row"><?php foreach ($reading as $c) lib_card($c, true); ?></div></div>
  <?php endif; ?>
  <?php if (!$filtered && $recent && $page === 1): ?>
  <div class="lib-sec"><h2>🆕 <?php echo __h('lib_recent_added'); ?></h2><div class="sc-row"><?php foreach ($recent as $c) lib_card($c, true); ?></div></div>
  <?php endif; ?>

  <div class="lib-sec">
    <div class="lib-sec-head"><h2><?php echo $filtered ? __h('lib_results', $total_cards) : __h('lib_all_series', $total_cards); ?></h2><?php echo $_sortbar_html; ?></div>
    <?php if (!$page_cards): ?>
      <div class="lib-empty"><?php echo $total_vols === 0 ? __h('lib_empty_index') : __h('lib_no_results'); ?></div>
    <?php else: ?>
      <div id="libCards" class="sc-grid"><?php foreach ($page_cards as $c) lib_card($c); ?></div>
      <?php if ($pages > 1): ?>
      <div class="lib-pager">
        <?php for ($p = 1; $p <= $pages; $p++):
          if ($p !== 1 && $p !== $pages && abs($p - $page) > 3) { if ($p === 2 || $p === $pages - 1) echo '<span>…</span>'; continue; } ?>
          <?php if ($p === $page): ?><span class="cur"><?php echo $p; ?></span><?php else: ?><a href="<?php echo h(lib_browse_url(['page' => $p])); ?>"><?php echo $p; ?></a><?php endif; ?>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>

<button id="scrollTopBtn" title="<?php echo __h('ui_scroll_top'); ?>" style="display:none;">▲</button>

<script>
(function () {
  function getPref(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }
  function setPref(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  var box = document.getElementById('libCards');
  var btns = document.querySelectorAll('.lib-view-btn');
  function applyView(v) {
    if (v !== 'list') v = 'grid';
    if (box) box.className = v === 'list' ? 'sc-list' : 'sc-grid';
    for (var i = 0; i < btns.length; i++) { var on = btns[i].getAttribute('data-view') === v; btns[i].classList.toggle('active', on); btns[i].setAttribute('aria-pressed', on ? 'true' : 'false'); }
  }
  for (var i = 0; i < btns.length; i++) btns[i].addEventListener('click', function () { var v = this.getAttribute('data-view'); applyView(v); setPref('mcLibBrowseView', v); try { this.blur(); } catch (e) {} });
  applyView(getPref('mcLibBrowseView', 'grid'));
  // ★ (2026-11-03 01:26) 고른 정렬을 이 기기에 기억(다음에 서재를 열면 그 정렬로) — 쿠키라 서버가 처음 그릴 때부터 그 순서
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('.lib-chip[data-sort]') : null;
    if (a) { try { document.cookie = 'mc_lib_sort=' + a.getAttribute('data-sort') + '; path=/; max-age=31536000; SameSite=Lax'; } catch (x) {} }
  }, true);

  var top = document.getElementById('scrollTopBtn');
  if (top) {
    window.addEventListener('scroll', function () { top.style.display = (document.documentElement.scrollTop > 200 || document.body.scrollTop > 200) ? 'block' : 'none'; }, { passive: true });
    top.onclick = function () { window.scrollTo({ top: 0, behavior: 'smooth' }); };
  }

<?php if ($is_admin && $db): ?>
  // 관리자: 라이브러리 스캔
  // ★ (2026-10-30) 서버가 끝까지 진행(scan_start — 응답을 먼저 보내고 서버에서 계속) → 화면은 진행 상황(scan_status)만 읽음
  //   창을 닫거나 사생활 보호 화면으로 바뀌어도, 자동 로그아웃이 돼도 서버에서 계속. 진행 상황 읽기가 잠깐 실패해도 다시 시도(멈추지 않음)
  //   자동 스캔(업로드·삭제 뒤·정해진 시간마다)이 진행 중이면 그 진행 상황도 여기 보임
  var scanBtn = document.getElementById('libScanBtn'), fullBtn = document.getElementById('libScanFullBtn'), stopBtn = document.getElementById('libScanStop'), scanMsg = document.getElementById('libScanMsg');
  var autoOn = document.getElementById('libAutoOn'), autoMin = document.getElementById('libAutoMin'), autoMsg = document.getElementById('libAutoMsg');
  var CSRF = <?php echo json_encode($csrf_token); ?>, BIDX = <?php echo (int)$bidx; ?>;
  var SCAN_B = <?php echo !empty($scan_all) ? "'all'" : (int)$bidx; ?>; // ★ (2026-10-31) '전체 폴더(통합)'이면 모든 폴더 스캔
  var T = <?php echo json_encode(['running' => __('lib_scan_progress'), 'done' => __('lib_scan_done2'), 'failed' => __('lib_scan_failed2'), 'busy' => __('lib_scan_busy'), 'stopped' => __('lib_scan_stopped'), 'confirm_full' => __('lib_scan_full_confirm'),
      'auto' => __('lib_scan_by_auto'), 'bg' => __('lib_scan_bg_note'), 'auto_done' => __('lib_scan_auto_done'), 'reload' => __('lib_reload'), 'resuming' => __('lib_scan_resuming'), 'conn' => __('lib_scan_conn'), 'saved' => __('lib_auto_saved'), 'save_failed' => __('lib_save_failed'), 'stopping' => __('lib_scan_stopping')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var mine = false, sawRunning = false, pollT = null, fails = 0, resumeAsked = 0, startedAt = 0, REQ = '';
  function fmt(s, a) { for (var i = 0; i < a.length; i++) s = s.split('%' + (i + 1) + '$s').join(String(a[i])); return s; }
  function setRunning(on) { scanBtn.disabled = on; fullBtn.disabled = on; stopBtn.style.display = on ? '' : 'none'; if (!on) stopBtn.disabled = false; }
  function fd(action, extra) { var f = new FormData(); f.append('action', action); f.append('csrf_token', CSRF); f.append('bidx', /^scan_/.test(action) ? SCAN_B : BIDX); if (extra) for (var k in extra) f.append(k, extra[k]); return f; }
  function post(f) { return fetch('library_api.php', { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function setMsg(text, withReload) {
    scanMsg.textContent = text;
    if (withReload) { var a = document.createElement('a'); a.href = 'javascript:void(0)'; a.textContent = ' ' + T.reload; a.style.marginLeft = '6px'; a.onclick = function () { location.reload(); }; scanMsg.appendChild(a); }
  }
  function schedule(ms) { clearTimeout(pollT); pollT = setTimeout(check, ms); }
  function show(s) {
    var st = s.state || {};
    var sec = Math.max(0, (s.now || 0) - (st.started || s.now || 0));
    // 내가 시작한 스캔인지(시작 요청 번호) — 아직 새 스캔 상태가 안 올라왔으면 이전 스캔의 '완료'를 잘못 보지 않게 기다림
    if (mine && st.req !== REQ) {
      if (Date.now() - startedAt < 20000) { if (s.live) setMsg('…'); return 1000; }
      mine = false; sawRunning = false; setRunning(false); setMsg(T.busy); return 0;
    }
    if (s.live) {
      sawRunning = true; setRunning(true);
      setMsg((st.by === 'auto' ? '[' + T.auto + '] ' : '') + fmt(T.running, [st.seen || 0, st.changed || 0, st.left || 0, sec]) + ' — ' + T.bg);
      return 1500;
    }
    if (s.pending) {
      // 끝나지 않은 스캔이 멈춤(서버 재시작·시간 제한 등) → 자동 확인 요청으로 이어서 진행(잠금이 60초 지나야 이어받을 수 있음)
      setRunning(true); setMsg(T.resuming);
      if (Date.now() - resumeAsked > 20000) { resumeAsked = Date.now();
        // ★ (2026-10-31) 모든 폴더 보기면 멈춘 폴더마다 이어받기 요청
        var pb = (SCAN_B === 'all' && s.pending_bidx && s.pending_bidx.length) ? s.pending_bidx : [BIDX];
        pb.forEach(function (b) { try { var f = new FormData(); f.append('bidx', b); fetch('library_auto.php', { method: 'POST', body: f, credentials: 'same-origin', keepalive: true }).catch(function () {}); } catch (e) {} }); }
      return 5000;
    }
    setRunning(false);
    if (sawRunning || mine) {
      if (st.status === 'done') {
        setMsg(fmt(T.done, [st.seen || 0, st.changed || 0, Math.max(0, (st.finished || st.updated || 0) - (st.started || 0))]), !mine);
        if (mine) setTimeout(function () { location.reload(); }, 1200);
      } else if (st.status === 'stopped') setMsg(T.stopped);
      else if (st.status === 'failed') setMsg(fmt(T.failed, [st.error || '']));
      mine = false; sawRunning = false;
    }
    return 0;
  }
  function check() {
    post(fd('scan_status')).then(function (s) {
      fails = 0;
      if (!s || !s.success) throw 0;
      var next = show(s);
      if (next) schedule(next);
    }).catch(function () {
      // 진행 상황 읽기 실패(통신·로그아웃 등) — 스캔은 서버에서 계속되므로 멈추지 않고 다시 시도
      fails++; setMsg(T.conn);
      schedule(Math.min(15000, 1500 * fails));
    });
  }
  function start(mode) {
    mine = true; sawRunning = false; startedAt = Date.now(); REQ = Math.random().toString(36).slice(2, 12).replace(/[^a-z0-9]/g, '') || 'r'; setRunning(true); setMsg('…');
    // 응답이 늦게 와도(서버에서 스캔이 계속되는 동안 압축 설정에 따라 응답이 붙잡힐 수 있음) 기다리지 않고 진행 상황을 읽음
    post(fd('scan_start', { mode: mode, req: REQ })).then(function (r) { if (r && r.running) mine = false; if (r && r.success === false) { mine = false; setRunning(false); setMsg(fmt(T.failed, [r.error || ''])); clearTimeout(pollT); } }).catch(function () {});
    schedule(800);
  }
  if (scanBtn) scanBtn.addEventListener('click', function () { start('quick'); });
  if (fullBtn) fullBtn.addEventListener('click', function () { if (confirm(T.confirm_full)) start('full'); });
  if (stopBtn) stopBtn.addEventListener('click', function () {
    stopBtn.disabled = true; setMsg(T.stopping);
    post(fd('scan_cancel')).then(function () { schedule(800); }).catch(function () { stopBtn.disabled = false; });
  });
  function saveAuto() {
    post(fd('auto_set', { on: autoOn.checked ? '1' : '0', min: autoMin.value })).then(function (r) { autoMsg.textContent = r && r.success ? T.saved : T.save_failed; setTimeout(function () { autoMsg.textContent = ''; }, 2000); })
      .catch(function () { autoMsg.textContent = T.save_failed; });
  }
  if (autoOn) { autoOn.addEventListener('change', saveAuto); autoMin.addEventListener('change', saveAuto); }
  check(); // 들어왔을 때 진행 중인 스캔(자동 포함)이 있으면 진행 상황 표시
<?php endif; ?>
})();
</script>
<?php render_darkmode_script(); ?>
<?php if (function_exists('mc_lib_auto_tick_script')) { foreach (($multi_base && $scope === 'all') ? $scope_bidxs : [(int)$bidx] as $_tb) mc_lib_auto_tick_script((int)$_tb); } // ★ (2026-10-30) 자동 스캔 확인(필요할 때만) — ★ (2026-10-31) '전체 폴더(통합)'면 모든 폴더 ?>
<?php render_auto_logout_script(); ?>
<?php require_once __DIR__ . '/privacy_shield.php'; ?>
<script>
// ★ (2026-11-02 06:40) 찾아 고르기 칸 — 작가·그룹처럼 목록이 많은 종류(300개 넘을 때)
// ★ (2026-11-02 07:52) 펼치면 선택 상자처럼 전체 목록(이름순·작품 수) — 한 번에 다 그리지 않고 100개씩, 아래로 내릴 때마다 이어서(위아래로 움직여도 이미 그린 건 그대로)
//   글자를 치면 그 글자가 든 이름만(대소문자·띄어쓰기·기호 무시). 목록은 화면이 뜬 뒤 쉬는 틈에 미리 받아 둠(누를 때 기다리지 않게) ·
//   서재 내용이 바뀌기 전까지는 다시 받지 않음(세션 저장소, 서재 계산 결과 키를 버전으로)
(function () {
  var T = <?php echo json_encode(['all' => __('lib_all'), 'loading' => __('lib_pick_loading'), 'none' => __('lib_pick_none')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var BASE = <?php echo json_encode('browse.php?bidx=' . (int)$bidx . ($multi_base ? '&scope=' . rawurlencode((string)$scope) : '') . '&lib_terms=', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var VER = <?php echo json_encode((string)($_lcv ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var STEP = 100;
  function norm(x) { x = String(x || '').toLowerCase(); try { x = x.normalize('NFKC'); return x.replace(new RegExp('[^\\p{L}\\p{N}]+', 'gu'), ''); } catch (e) { return x.replace(/[\s\-_.,·~!?'"()\[\]]+/g, ''); } }
  var coll = null; try { coll = new Intl.Collator('en', { numeric: true, sensitivity: 'base' }); } catch (e) {} // 숫자·영문 다음 한글·가나(장르·태그 선택 상자와 같은 순서)
  function prep(j) { var d = j.map(function (r) { return { id: r[0], n: String(r[1]), c: r[2], k: norm(r[1]) }; }); d.sort(function (a, b) { return coll ? coll.compare(a.n, b.n) : (a.n < b.n ? -1 : a.n > b.n ? 1 : 0); }); return d; }
  document.querySelectorAll('.lib-tpick').forEach(function (box) {
    var kind = box.getAttribute('data-kind'), hid = box.querySelector('input[type=hidden]'), inp = box.querySelector('.lib-tpick-in'), ul = box.querySelector('ul');
    var data = null, loading = false, waiters = [], items = [], act = -1, selName = inp.getAttribute('data-sel') || '';
    var cur = [], shown = 0, pendEnter = false;
    if (selName) inp.classList.add('has-sel');
    var CK = 'mc_lib_terms|' + BASE + kind;
    try { var _c = JSON.parse(sessionStorage.getItem(CK) || 'null'); if (_c && _c.v === VER && VER !== '' && _c.t > Date.now() - 1800000 && Array.isArray(_c.d)) data = prep(_c.d); } catch (e) {}
    function msg(t) { ul.innerHTML = ''; var li = document.createElement('li'); li.className = 'lsd-more'; li.textContent = t; ul.appendChild(li); }
    function load(cb) {
      if (data) { if (cb) cb(); return; }
      if (cb) waiters.push(cb);
      if (!ul.hidden) msg(T.loading);
      if (loading) return; loading = true;
      fetch(BASE + encodeURIComponent(kind), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        j = Array.isArray(j) ? j : [];
        data = prep(j);
        try { sessionStorage.setItem(CK, JSON.stringify({ v: VER, t: Date.now(), d: j })); } catch (e) {}
        loading = false; var w = waiters; waiters = []; w.forEach(function (f) { f(); });
        if (pendEnter) { pendEnter = false; enter(); }
      }).catch(function () { loading = false; waiters = []; pendEnter = false; if (!ul.hidden) msg(T.none); });
    }
    function pick(id) { hid.value = id ? String(id) : ''; if (hid.form) hid.form.submit(); }
    function addItem(label, count, id, isAll) {
      var li = document.createElement('li'); li.setAttribute('role', 'option');
      if (!isAll && String(id) === hid.value) li.setAttribute('aria-selected', 'true');
      var t = document.createElement('span'); t.className = 'lsd-t'; t.textContent = label; li.appendChild(t); // 글자로만(HTML 해석 없음)
      if (count !== null) { var c = document.createElement('span'); c.className = 'lsd-c'; c.textContent = count; li.appendChild(c); }
      li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(isAll ? '' : id); });
      ul.appendChild(li); items.push(li);
    }
    function more() { // 다음 100개
      var end = Math.min(cur.length, shown + STEP);
      for (var i = shown; i < end; i++) addItem(cur[i].n, cur[i].c, cur[i].id, false);
      shown = end;
    }
    function render() {
      if (ul.hidden) return;
      var q = inp.value === selName ? '' : norm(inp.value);
      cur = q ? data.filter(function (d) { return d.k.indexOf(q) >= 0; }) : data;
      ul.innerHTML = ''; items = []; act = -1; shown = 0; ul.scrollTop = 0;
      if (!q) addItem(T.all, null, '', true);
      if (!cur.length) { var n = document.createElement('li'); n.className = 'lsd-more'; n.textContent = T.none; ul.appendChild(n); return; }
      more();
      // 고른 이름이 있으면 그 줄까지 그려서 보이게
      if (!q && hid.value) { var at = -1; for (var i = 0; i < cur.length; i++) if (String(cur[i].id) === hid.value) { at = i; break; } if (at >= 0) { while (shown <= at) more(); var el = ul.querySelector('li[aria-selected="true"]'); if (el) el.scrollIntoView({ block: 'center' }); } }
    }
    ul.addEventListener('mousedown', function (e) { e.preventDefault(); }); // ★ (08:27) 목록 스크롤바·빈 곳을 눌러도 입력칸 포커스 유지(닫히지 않게)
    ul.addEventListener('scroll', function () { if (shown < cur.length && ul.scrollTop + ul.clientHeight >= ul.scrollHeight - 120) more(); }, { passive: true });
    function open() { if (!ul.hidden) return; ul.hidden = false; inp.setAttribute('aria-expanded', 'true'); load(render); }
    function enter() { if (!data) { pendEnter = true; return; } var it = act >= 0 ? items[act] : (inp.value !== selName && inp.value.trim() !== '' ? items[0] : null); if (it) it.dispatchEvent(new MouseEvent('mousedown', { cancelable: true })); }
    function close() { ul.hidden = true; inp.setAttribute('aria-expanded', 'false'); if (inp.value !== selName) inp.value = selName; }
    function move(d) {
      if (!items.length) return;
      if (d > 0 && act + 1 >= items.length && shown < cur.length) more();
      if (act >= 0 && items[act]) items[act].classList.remove('lsd-act');
      act = Math.max(0, Math.min(items.length - 1, act + d)); items[act].classList.add('lsd-act'); items[act].scrollIntoView({ block: 'nearest' });
    }
    inp.addEventListener('focus', function () { if (inp.value === selName) inp.select(); open(); });
    inp.addEventListener('click', function () { open(); });
    inp.addEventListener('input', function () { if (ul.hidden) open(); else if (data) render(); });
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (ul.hidden) open(); else move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter') { if (e.isComposing || e.keyCode === 229) return; e.preventDefault(); enter(); } // ★ (08:27) 한글 조합 중 엔터는 조합 확정만 // 엔터: 고른 줄, 없으면 처음 맞는 이름(목록을 받는 중이면 받은 뒤)
      else if (e.key === 'Escape') { close(); inp.blur(); }
    });
    inp.addEventListener('blur', function () { setTimeout(function () { if (document.activeElement !== inp) close(); }, 150); }); // 곧바로 다시 누른 경우엔 닫지 않음
    // 화면이 뜬 뒤 쉬는 틈에 미리 받아 둠
    if (!data) { var pre = function () { load(null); }; if (window.requestIdleCallback) requestIdleCallback(pre, { timeout: 2500 }); else setTimeout(pre, 800); }
  });
})();
</script>
<script>
// ★ (2026-11-02 02:44) '← 돌아가기' — 즐겨찾기 화면과 같게: 목록(index.php)에서 들어왔으면 뒤로가기(브라우저가 보관한 목록을 바로 복원 → 다시 불러오지 않아 빠르고 보던 폴더·스크롤 그대로)
//   서재 안에서 거르기·쪽을 옮긴 뒤면 들어올 때의 그 폴더 주소로(예전: 늘 목록 첫 화면으로 새로 불러옴)
(function () {
  var KEY = 'mc_lib_from_index';
  function idxRef() { try { var r = document.referrer ? new URL(document.referrer) : null; return (r && r.origin === location.origin && /\/index\.php$/.test(r.pathname)) ? r : null; } catch (e) { return null; } }
  var r0 = idxRef();
  if (r0) { try { sessionStorage.setItem(KEY, r0.pathname + r0.search); } catch (e) {} }
  var back = document.getElementById('libBack');
  if (!back) return;
  back.addEventListener('click', function (ev) {
    if (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button === 1) return; // 새 탭은 그대로
    try {
      if (idxRef() && history.length > 1) { ev.preventDefault(); history.back(); return; }
      var u = sessionStorage.getItem(KEY);
      if (u && /^\/[^\s]*index\.php(\?|$)/.test(u)) { ev.preventDefault(); location.href = u; }
    } catch (e) { /* 실패하면 링크 그대로 */ }
  });
})();
</script>
<script>
// ★ (2026-11-02 01:43) 뷰어에서 읽고 뒤로 오면 읽은 기록(%·다 읽음·읽는 중)을 바로 반영 — 예전엔 새로고침해야 바뀌었음
//   뷰어가 위치를 저장할 때 표시(mc_lib_dirty)를 남김 → 뒤로가기(bfcache·캐시)로 이 화면이 다시 보일 때만 잠깐 뒤 새로 읽음(저장이 끝날 시간)
(function () {
  function navType() { try { var e = performance.getEntriesByType('navigation'); return (e && e[0] && e[0].type) || ''; } catch (x) { return ''; } }
  window.addEventListener('pageshow', function (e) {
    var d = null; try { d = sessionStorage.getItem('mc_lib_dirty_b'); } catch (x) {} // ★ (2026-11-02 03:04) 서재 첫 화면 전용 표시(작품 화면이 먼저 지워도 여기는 갱신)
    if (!d) return;
    try { sessionStorage.removeItem('mc_lib_dirty_b'); } catch (x) {}
    if (e.persisted || navType() === 'back_forward') setTimeout(refreshProgress, 600);
  });
  // ★ (2026-11-02 16:17) 새로고침하지 않고 화면에 있는 작품 카드의 읽은 정도만 받아 바꿈(보던 쪽·스크롤·펼친 목록 그대로)
  var PROG_URL = <?php echo json_encode('browse.php?bidx=' . (int)$bidx . ($multi_base ? '&scope=' . rawurlencode((string)$scope) : '') . '&lib_prog=', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  function refreshProgress() {
    var cards = document.querySelectorAll('.sc[data-sid]'), ids = {};
    for (var i = 0; i < cards.length; i++) ids[cards[i].getAttribute('data-sid')] = 1;
    var list = Object.keys(ids).slice(0, 300);
    if (!list.length) return;
    fetch(PROG_URL + list.join(','), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j || typeof j !== 'object') return;
      for (var i = 0; i < cards.length; i++) {
        var d = j[cards[i].getAttribute('data-sid')]; if (!d) continue;
        var fin = +d[0], cnt = +d[1], cv = cards[i].querySelector('.sc-cover'); if (!cv) continue;
        var pct = cnt > 0 ? Math.floor(fin / cnt * 100) : 0;
        var ck = cv.querySelector('.sc-fin');
        if (fin > 0 && fin >= cnt) { if (!ck) { ck = document.createElement('span'); ck.className = 'sc-fin'; ck.textContent = '✓'; cv.appendChild(ck); } } else if (ck) ck.remove();
        var pg = cv.querySelector('.sc-prog');
        if (pct > 0 && fin < cnt) { if (!pg) { pg = document.createElement('div'); pg.className = 'sc-prog'; pg.appendChild(document.createElement('span')); cv.appendChild(pg); } pg.firstChild.style.width = pct + '%'; } else if (pg) pg.remove();
        var sub = cards[i].querySelector('.sc-sub2'); if (sub) sub.textContent = String(d[2]);
      }
    }).catch(function () {});
  }
})();
</script>
<?php if (function_exists('mc_lib_scroll_script')) echo mc_lib_scroll_script(); /* ★ (2026-11-02 16:21) 돌아왔을 때 보던 위치 */ ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
<?php if (function_exists('mc_perf_script')) echo mc_perf_script('browse'); // ★ (2026-11-03 14:10) 속도 기록(src/perf) ?>
<?php if (function_exists('mc_back_origin_list_js')) echo mc_back_origin_list_js(); /* ★ (2026-11-05) 뷰어 뒤로가기가 이 화면(주소·스크롤 위치)으로 돌아오게 — function.php */ ?>
</body>
</html>
