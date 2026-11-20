<?php
/**
 * ★ (2026-11-03 14:10) 속도 기록 — 브라우저가 보내는 페이지 로딩 측정(function.php mc_perf_script)을 src/perf/날짜.log 에 한 줄 남김
 *   로그인한 사람만, 숫자·정해진 글자만 받음(본문 4KB 까지). 자동 로그아웃용 활동 시각은 건드리지 않음(사람이 한 동작이 아님)
 */
define('MC_NO_ACTIVITY_TOUCH', true);
require_once __DIR__ . '/bootstrap.php';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['user_id']) || !function_exists('mc_perf_enabled') || !mc_perf_enabled()) { http_response_code(204); exit; }
$raw = (string)file_get_contents('php://input', false, null, 0, 4096);
$d = json_decode($raw, true);
if (!is_array($d)) { http_response_code(204); exit; }
// ★ (2026-11-12 20:02, 펜닐 요청 — fix130) 뷰어 ←·가림막 기록(function.php mc_back_seek_js) → src/perf/back_날짜.log. 정해진 값만(글자 길이 제한), 하루 5MB·14일 지나면 지움
if (($d['k'] ?? '') === 'b') {
    $ev = (string)($d['ev'] ?? '');
    if (!in_array($ev, ['open', 'press', 'seek_step', 'seek_arrive', 'seek_fallback', 'blank_go', 'blank_ret', 'up_back', 'ask', 'lp_use'], true)) { http_response_code(204); exit; }
    $sv = function ($k, $len = 200) use ($d) { $v = $d[$k] ?? ''; if (!is_string($v)) return ''; $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v); return mb_substr((string)$v, 0, $len); };
    $iv = function ($k) use ($d) { $v = $d[$k] ?? 0; return (is_int($v) || is_float($v)) ? (int)max(-1, min(100000, $v)) : 0; };
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    list($dev, $os, $br) = mc_perf_device($ua);
    $rec = ['t' => date('Y-m-d H:i:s'), 'ev' => $ev, 'user' => preg_replace('/[^a-zA-Z0-9_]/', '', (string)$_SESSION['user_id']), 'dev' => $dev, 'os' => $os, 'br' => $br,
            'page' => preg_replace('/[^a-z_.]/', '', $sv('page', 30)), 'len' => $iv('len'), 'nt' => preg_replace('/[^a-z_]/', '', $sv('nt', 20)), 'how' => preg_replace('/[^a-z]/', '', $sv('how', 10)),
            'hist' => $sv('hist'), 'url' => $sv('url'), 'gap' => $iv('gap'), 'n' => $iv('n'), 'cur' => $iv('cur'), 'forced' => $iv('forced')];
    $rec = array_filter($rec, function ($v) { return $v !== '' && $v !== 0; }) + ['len' => $rec['len']];
    $dir = __DIR__ . '/src/perf';
    if (is_dir($dir)) {
        $f = $dir . '/back_' . date('Y-m-d') . '.log';
        if (!is_file($f)) {
            foreach ((@scandir($dir) ?: []) as $n) { if (preg_match('/^back_(\d{4}-\d{2}-\d{2})\.log$/', $n, $m) && strtotime($m[1]) < time() - 14 * 86400) @unlink($dir . '/' . $n); }
        }
        if (!is_file($f) || (int)@filesize($f) < 5 * 1024 * 1024) @file_put_contents($f, json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n", FILE_APPEND | LOCK_EX);
    }
    // ★ (2026-11-17 13:08, 펜닐 요청 — fix187) 같은 줄을 날짜.log 에도(화면 열기·저장 기록과 한 파일에서 순서대로 보이게) — up_back: 서재·작품 화면 등 '← 돌아가기' / ask: '이어서 볼까요?' 답(how: go·start) / lp_use: 서버보다 이 기기 기록(방금 보낸 위치)으로 열었음(fix188)
    $__r2 = $rec; unset($__r2['t']);
    mc_perf_write(['k' => 'b'] + $__r2);
    http_response_code(204); exit;
}
// ★ (2026-11-19 11:17, 펜닐 요청 — fix243) 증상 기록(js/mc-ev.js) → 날짜.log 에 k:"u". 정해진 이름·정해진 글자만(길이 제한), 숫자는 범위 안으로
if (($d['k'] ?? '') === 'u') {
    $ev = (string)($d['ev'] ?? '');
    if (!in_array($ev, ['tap2', 'tap_slow', 'zoom', 'req_slow', 'req_fail', 'back2', 'hist_add', 'freeze', 'jserr', 'lv_load', 'lv_set'], true)) { http_response_code(204); exit; } // ★ (2026-11-20 22:34, 펜닐 요청 — fix264) lv_load·lv_set: 폴더 목록 화면 그리드/목록(화면 종류별) 확인용
    $pat = function ($k, $re, $len) use ($d) { $v = $d[$k] ?? ''; if (!is_string($v)) return ''; $v = preg_replace($re, '', $v); return substr($v, 0, $len); };
    $iv = function ($k) use ($d) { $v = $d[$k] ?? 0; return (is_int($v) || is_float($v)) ? (int)max(-1, min(600000, $v)) : 0; };
    $msg = $d['msg'] ?? ''; $msg = is_string($msg) ? mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $msg), 0, 150) : '';
    $sc = $d['sc'] ?? 0; $sc = (is_int($sc) || is_float($sc)) ? round(max(0, min(20, (float)$sc)), 2) : 0;
    list($dev, $os, $br) = mc_perf_device((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $rec = ['k' => 'u', 'ev' => $ev, 'user' => preg_replace('/[^a-zA-Z0-9_]/', '', (string)$_SESSION['user_id']), 'dev' => $dev, 'os' => $os, 'br' => $br,
            'page' => $pat('page', '/[^a-z_]/', 20), 'from' => $pat('from', '/[^a-z_]/', 20), 'el' => $pat('el', '/[^A-Za-z0-9_#.\-]/', 40),
            'path' => $pat('path', '/[^A-Za-z0-9_.\/=?&\-]/', 80), 'h' => $pat('h', '/[^A-Za-z0-9_#.\-]/', 30), 'src' => $pat('src', '/[^A-Za-z0-9_.\-]/', 60), 'msg' => $msg,
            'ms' => $iv('ms'), 'gap' => $iv('gap'), 'n' => $iv('n'), 'st' => $iv('st'), 'line' => $iv('line'), 'hl' => $iv('hl'), 'sc' => $sc];
    $rec = array_filter($rec, function ($v) { return $v !== '' && $v !== 0 && $v !== 0.0; });
    if ($ev === 'req_fail') $rec['st'] = $iv('st'); // 0 = 연결 끊김(빈 값으로 지우지 않음)
    if ($ev === 'lv_load' || $ev === 'lv_set') { // ★ (2026-11-20 22:34 fix264) 화면 종류(folder|file)·보기(g 그리드|l 목록)·저장값(폴더 화면 sf·파일 화면 sl, - = 없음)·이 쪽 폴더 카드 수(fo)·파일 카드 수(fi)·폴더 깊이(dp) — 폴더 이름은 남기지 않음
        foreach (['kind' => '/^(folder|file)\z/', 'v' => '/^[gl]\z/', 'sf' => '/^[gl\-]\z/', 'sl' => '/^[gl\-]\z/'] as $_k => $_re) { $_x = $d[$_k] ?? ''; if (is_string($_x) && preg_match($_re, $_x)) $rec[$_k] = $_x; }
        foreach (['fo', 'fi', 'dp'] as $_k) { $_x = $d[$_k] ?? null; if (is_int($_x) || is_float($_x)) $rec[$_k] = (int)max(0, min(100000, $_x)); }
    }
    mc_perf_write($rec);
    http_response_code(204); exit;
}
$num = function ($k, $max = 600000) use ($d) { $v = $d[$k] ?? 0; return (is_int($v) || is_float($v)) ? max(-1, min($max, round((float)$v, 2))) : 0; };
$str = function ($k, $re, $len = 24) use ($d) { $v = $d[$k] ?? ''; return (is_string($v) && preg_match($re, $v)) ? substr($v, 0, $len) : ''; };
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
list($dev, $os, $br) = mc_perf_device($ua);
mc_perf_write([
    'k' => 'c',
    'rid' => $str('rid', '/^[a-f0-9]{8,32}\z/', 32),
    'page' => $str('page', '/^[a-z_]{1,20}\z/'),
    'user' => preg_replace('/[^a-zA-Z0-9_]/', '', (string)$_SESSION['user_id']),
    'dev' => $dev, 'os' => $os, 'br' => $br,
    'nav' => $str('nav', '/^(navigate|reload|back_forward|prerender|bfcache)\z/'), // ★ (2026-11-17 fix187) bfcache = 브라우저가 보관한 화면을 되살림(뒤로·앞으로)
    'h0' => $str('h0', '/^#[A-Za-z0-9_.\-]{0,39}\z/', 40), 'h1' => $str('h1', '/^#[A-Za-z0-9_.\-]{0,39}\z/', 40), 'hl' => $num('hl', 1000), // ★ (2026-11-17 fix187) 주소 뒤 위치(열 때·보낼 때)·방문 기록 개수
    'ttfb' => $num('ttfb'), 'dl' => $num('dl'), 'dom' => $num('dom'), 'dcl' => $num('dcl'), 'load' => $num('load'),
    'kb' => $num('kb', 10000000), 'body_kb' => $num('body_kb', 10000000), 'img' => $num('img', 100000),
    'net' => $str('net', '/^[a-z]{1,12}\z/'), 'eff' => $str('eff', '/^(slow-2g|2g|3g|4g)\z/'),
    'down' => $num('down', 100000), 'rtt' => $num('rtt', 100000), 'sw' => $num('sw', 10000),
]);
http_response_code(204);
