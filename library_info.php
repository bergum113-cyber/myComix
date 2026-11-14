<?php
/**
 * ★ (2026-11-12 21:19, 펜닐 요청 — fix131) 목록 보기 정보 줄용 읽기 전용 주소 — js/mc-list-info.js 가 부름
 *   POST 본문(JSON): {"items": [[bidx, "/폴더/파일"], ...]} (300개까지) → {"bidx|/폴더/파일": {"who": [...], "tags": [...], "pages": N}, ...}
 *   - 로그인한 사람만, 읽기만(서재 DB 를 새로 만들지 않음 — 파일이 없으면 빈 결과)
 *   - 항목마다 폴더 권한 확인(mc_lib_can_access) — 볼 수 없는 파일은 빠짐
 *   - 화면이 뒤에서 부르는 요청이라 자동 로그아웃용 활동 시각은 건드리지 않음(thumb.php 와 같음)
 */
define('MC_NO_ACTIVITY_TOUCH', true);
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['user_id'])) { http_response_code(403); echo '{}'; exit; }
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (!is_file(__DIR__ . '/src/library.db')) { echo '{}'; exit; }
require_once __DIR__ . '/library.php';
$raw = (string)file_get_contents('php://input', false, null, 0, 200000);
$d = json_decode($raw, true);
$items = (is_array($d) && isset($d['items']) && is_array($d['items'])) ? $d['items'] : [];
$db = function_exists('mc_lib_db') ? mc_lib_db() : null;
if (!$db || !$items) { echo '{}'; exit; }
$ok = [];
foreach (array_slice($items, 0, 300) as $it) {
    if (!is_array($it) || count($it) < 2 || !is_numeric($it[0]) || !is_string($it[1])) continue;
    $b = (int)$it[0];
    $p = mc_lib_norm_path($it[1]);
    if ($p === '' || !mc_lib_can_access($b, $p)) continue;
    $ok[] = [$b, $p, $it[1]];
}
$info = $ok ? mc_lib_list_info($db, $ok) : [];
// 화면이 보낸 경로 그대로를 열쇠로(앞 '/'·'\\' 차이가 있어도 화면에서 찾을 수 있게)
$out = [];
foreach ($ok as $o) { $k = $o[0] . '|' . $o[1]; if (isset($info[$k])) $out[$o[0] . '|' . $o[2]] = $info[$k]; }
echo $out ? json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : '{}';
