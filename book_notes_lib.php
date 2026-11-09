<?php
/**
 * myComix 책 노트(형광펜·밑줄·메모·책갈피) — EPUB·TXT 뷰어 공용
 * ★ (2026-10-31) 펜닐 요청 — 전자책처럼 공부 노트
 *
 *  · 계정별 파일 하나: src/<아이디>_notes.json  { "<bidx>|<책 경로>": { "u": 마지막 수정, "items": [ … ] } }
 *  · 항목: id · k(hl 형광펜 | ul 밑줄 | bm 책갈피) · c(색) · 위치(EPUB: cfi / TXT: s·e 글자 위치) · q(고른 글) · n(메모) · ch(장 이름) · p(진행률) · t(만든 시각) · m(고친 시각)
 *  · 읽기·고치기 모두 파일 잠금 한 번 안에서(두 탭에서 동시에 고쳐도 서로 지우지 않게)
 *  · 함수만 있음(직접 열어도 아무 일도 안 함) — 화면(뷰어)이 경로·권한·CSRF 를 먼저 확인한 뒤 부름
 */

if (!function_exists('mc_notes_file')) {

define('MC_NOTES_MAX_ITEMS', 3000);           // 책 한 권에 노트 최대 개수
define('MC_NOTES_MAX_FILE', 8 * 1024 * 1024); // 계정 노트 파일 최대 크기
define('MC_NOTES_MAX_QUOTE', 1000);           // 고른 글 최대 글자
define('MC_NOTES_MAX_NOTE', 4000);            // 메모 최대 글자

function mc_notes_file() {
    $uid = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_SESSION['user_id'] ?? ''));
    if ($uid === '') return '';
    return __DIR__ . '/src/' . $uid . '_notes.json';
}

/** 책 열쇠 — 폴더 번호 + 앞 슬래시 뺀 경로 */
function mc_notes_key($bidx, $path) {
    return (int)$bidx . '|' . ltrim(str_replace('\\', '/', (string)$path), '/');
}

/** 글자 정리: UTF-8 만, 제어 문자 제거(줄바꿈·탭은 메모에만), 길이 자름 */
function mc_notes_clean_text($s, $max, $multiline) {
    if (!is_string($s)) return '';
    if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = $multiline ? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) : preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
    if (!is_string($s)) return '';
    return mb_substr(trim($s), 0, $max, 'UTF-8');
}

/**
 * 들어온 항목 하나를 검사·정리 — 이상하면 null
 * @param string $kind 'epub' | 'txt'
 */
function mc_notes_sanitize_item($in, $kind) { // ★ (2026-11-02 10:02) 입력 값은 문자열만(배열이면 경고)
    if (!is_array($in)) return null;
    $id = (is_string($in['id'] ?? null) ? $in['id'] : '');
    if (!preg_match('/^[a-z0-9]{8,32}$/', $id)) return null;
    $k = (is_string($in['k'] ?? null) ? $in['k'] : '');
    if (!in_array($k, ['hl', 'ul', 'bm'], true)) return null;
    $c = (is_string($in['c'] ?? null) ? $in['c'] : 'yellow');
    if (!in_array($c, ['yellow', 'orange', 'coral', 'pink', 'purple', 'blue', 'mint', 'green', 'gray'], true)) $c = 'yellow'; // ★ (2026-11-07 fix39) 주황·회색 / ★ fix40 코랄·민트 더함(js/book-notes.js 의 COLORS 와 같게)
    $it = ['id' => $id, 'k' => $k, 'c' => $c];
    if ($kind === 'epub') {
        $cfi = (is_string($in['cfi'] ?? null) ? $in['cfi'] : '');
        if (strlen($cfi) > 2000 || !preg_match('/^epubcfi\([^\x00-\x1F<>"\']+\)$/', $cfi)) return null;
        $it['cfi'] = $cfi;
    } else {
        $s = $in['s'] ?? null; $e = $in['e'] ?? null;
        if (!is_numeric($s) || !is_numeric($e)) return null;
        $s = (int)$s; $e = (int)$e;
        if ($s < 0 || $e < $s || $e - $s > 200000 || $e > 200000000) return null;
        $it['s'] = $s; $it['e'] = $e;
    }
    $it['q'] = mc_notes_clean_text($in['q'] ?? '', MC_NOTES_MAX_QUOTE, false);
    $it['n'] = mc_notes_clean_text($in['n'] ?? '', MC_NOTES_MAX_NOTE, true);
    $it['ch'] = mc_notes_clean_text($in['ch'] ?? '', 200, false);
    $p = $in['p'] ?? -1;
    $it['p'] = (is_numeric($p) && is_finite((float)$p)) ? max(-1, min(100, round((float)$p, 1))) : -1;
    $t = (int)($in['t'] ?? 0);
    $it['t'] = ($t > 1000000000 && $t < 4102444800) ? $t : time();
    $it['m'] = time();
    return $it;
}

/** 이 책의 노트 목록 */
function mc_notes_load($bidx, $path) {
    $f = mc_notes_file();
    if ($f === '' || !is_file($f)) return [];
    $data = function_exists('load_json_with_lock') ? load_json_with_lock($f) : (json_decode((string)@file_get_contents($f), true) ?: []);
    $book = $data[mc_notes_key($bidx, $path)] ?? null;
    return (is_array($book) && isset($book['items']) && is_array($book['items'])) ? array_values($book['items']) : [];
}

/**
 * 노트 고치기 — 잠금 한 번 안에서 읽고·고치고·씀
 * @param string $op 'put'(추가·바꿈) | 'del'(지움)
 * @return array ['ok' => bool, 'error' => 문자열]
 */
function mc_notes_apply($bidx, $path, $op, $item_or_id) {
    $f = mc_notes_file();
    if ($f === '') return ['ok' => false, 'error' => 'login'];
    $dir = dirname($f);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fp = @fopen($f, 'c+');
    if (!$fp) return ['ok' => false, 'error' => 'open'];
    if (!flock($fp, LOCK_EX)) { fclose($fp); return ['ok' => false, 'error' => 'lock']; }
    $raw = stream_get_contents($fp);
    $data = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : [];
    if (!is_array($data)) {
        // 깨진 파일은 덮어쓰지 않고 그대로 둠(백업 후 새로 시작하도록 알림만)
        if ($raw !== '' && $raw !== false) { flock($fp, LOCK_UN); fclose($fp); return ['ok' => false, 'error' => 'corrupt']; }
        $data = [];
    }
    $key = mc_notes_key($bidx, $path);
    $book = (isset($data[$key]) && is_array($data[$key])) ? $data[$key] : ['u' => 0, 'items' => []];
    $items = is_array($book['items'] ?? null) ? array_values($book['items']) : [];
    if ($op === 'del') {
        $id = (string)$item_or_id;
        $items = array_values(array_filter($items, function ($x) use ($id) { return is_array($x) && ($x['id'] ?? '') !== $id; }));
    } else {
        $it = $item_or_id;
        $found = false;
        foreach ($items as $i => $x) {
            if (is_array($x) && ($x['id'] ?? '') === $it['id']) { $it['t'] = (int)($x['t'] ?? $it['t']); $items[$i] = $it; $found = true; break; }
        }
        if (!$found) {
            if (count($items) >= MC_NOTES_MAX_ITEMS) { flock($fp, LOCK_UN); fclose($fp); return ['ok' => false, 'error' => 'too_many']; }
            $items[] = $it;
        }
    }
    if ($items) { $data[$key] = ['u' => time(), 'items' => $items]; }
    else { unset($data[$key]); }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) { flock($fp, LOCK_UN); fclose($fp); return ['ok' => false, 'error' => 'encode']; }
    if (strlen($json) > MC_NOTES_MAX_FILE) { flock($fp, LOCK_UN); fclose($fp); return ['ok' => false, 'error' => 'too_big']; }
    ftruncate($fp, 0);
    rewind($fp);
    $ok = fwrite($fp, $json) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return ['ok' => $ok, 'error' => $ok ? '' : 'write'];
}

/**
 * 뷰어 API 처리 — action=notes_load(GET) / notes_save(POST: op, item(JSON) 또는 id, csrf_token)
 * 경로·확장자 확인은 여기서도 다시 함. 처리했으면 출력하고 exit
 * @param string $kind 'epub' | 'txt'
 */
function mc_notes_handle_api($kind, $base_dir, $bidx) {
    $act = is_string($_GET['action'] ?? null) ? $_GET['action'] : ''; // ★ (2026-11-02 09:06) 배열이면 경고
    if ($act !== 'notes_load' && $act !== 'notes_save') return;
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $out = function ($a, $code = 200) { http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; };
    if (empty($_SESSION['user_id'])) $out(['success' => false, 'error' => 'login'], 403);
    $raw = ($act === 'notes_save') ? ($_POST['file'] ?? '') : ($_GET['file'] ?? '');
    $path = decode_file_param(is_string($raw) ? $raw : '');
    $real = ($path !== '') ? validate_file_path($path, $base_dir) : false;
    $extRe = ($kind === 'epub') ? '/\.epub$/i' : '/\.txt$/i';
    // ★ (2026-11-03 08:31) 지우기는 파일이 없어도 됨(경로 검사·확장자는 그대로) — 북마크 화면에서 '파일 없음' 전자책 책갈피를 지울 수 없었음(내 노트 기록에서 그 항목만 빠짐)
    $_del_only = ($act === 'notes_save' && ($_POST['op'] ?? null) === 'del');
    if ($real === false || (!$_del_only && !is_file($real)) || !preg_match($extRe, $real)) $out(['success' => false, 'error' => 'path'], 400);
    if ($act === 'notes_load') $out(['success' => true, 'items' => mc_notes_load($bidx, $path)]);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $out(['success' => false, 'error' => 'method'], 405);
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'] ?? '')) $out(['success' => false, 'error' => 'csrf'], 403);
    $op = (is_string($_POST['op'] ?? null) ? $_POST['op'] : '');
    if ($op === 'del') {
        $id = (is_string($_POST['id'] ?? null) ? $_POST['id'] : '');
        if (!preg_match('/^[a-z0-9]{8,32}$/', $id)) $out(['success' => false, 'error' => 'id'], 400);
        $r = mc_notes_apply($bidx, $path, 'del', $id);
        $out(['success' => $r['ok'], 'error' => $r['error']], $r['ok'] ? 200 : 500);
    }
    if ($op === 'put') {
        $in = json_decode((is_string($_POST['item'] ?? null) ? $_POST['item'] : ''), true);
        $it = mc_notes_sanitize_item($in, $kind);
        if ($it === null) $out(['success' => false, 'error' => 'item'], 400);
        $r = mc_notes_apply($bidx, $path, 'put', $it);
        $out(['success' => $r['ok'], 'error' => $r['error'], 'item' => $r['ok'] ? $it : null], $r['ok'] ? 200 : ($r['error'] === 'too_many' || $r['error'] === 'too_big' ? 413 : 500));
    }
    $out(['success' => false, 'error' => 'op'], 400);
}

}
