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
    'nav' => $str('nav', '/^(navigate|reload|back_forward|prerender)\z/'),
    'ttfb' => $num('ttfb'), 'dl' => $num('dl'), 'dom' => $num('dom'), 'dcl' => $num('dcl'), 'load' => $num('load'),
    'kb' => $num('kb', 10000000), 'body_kb' => $num('body_kb', 10000000), 'img' => $num('img', 100000),
    'net' => $str('net', '/^[a-z]{1,12}\z/'), 'eff' => $str('eff', '/^(slow-2g|2g|3g|4g)\z/'),
    'down' => $num('down', 100000), 'rtt' => $num('rtt', 100000), 'sw' => $num('sw', 10000),
]);
http_response_code(204);
