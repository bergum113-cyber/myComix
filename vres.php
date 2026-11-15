<?php
/**
 * ★ (2026-11-15 18:28, 펜닐 요청 — fix173) 동영상 이어보기 위치를 서버(계정별)에 저장 — src/<아이디>_vres.json
 *   js/mc-video.js 가 브라우저 저장소('mc_vres:키')에 쓸 때 같이 보냄(sendBeacon, POST). 읽기는 viewer.php 가 페이지에 넣어 줌(function.php mc_vres_get)
 *   · op=put : 키·t(초)·d(길이 초)·at(저장 시각, 초) / op=del : 키·at — 다 본 영상·'처음부터'
 *     지울 때는 '지운 시각'만 남김(x) — 그보다 먼저 저장된 값이 늦게 도착해도(떠날 때 보낸 것 등) 다시 살아나지 않게(18:35, 시험으로 확인: 다 본 영상이 다시 32초로 살아났음)
 *   · 로그인한 사람만, 다른 사이트에서 온 요청은 받지 않음, 정해진 모양의 값만, 최대 1000개(오래된 것부터 지움)
 *   · 자동 로그아웃용 활동 기록은 바꾸지 않음(재생 중 몇 초마다 오는 저장이 사람의 조작으로 잡히지 않게 — blank.php 와 같은 방식)
 */
define('MC_NO_ACTIVITY_TOUCH', true);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$uid = function_exists('mc_prefs_uid') ? mc_prefs_uid() : '';
if ($uid === '' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    http_response_code(403); echo '{"ok":false}'; exit;
}
// 다른 사이트에서 보낸 요청이면(Origin 이 있고 이 사이트가 아님) 거절
$_vr_origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($_vr_origin !== '' && strtolower((string)parse_url($_vr_origin, PHP_URL_HOST)) !== strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')))) {
    http_response_code(403); echo '{"ok":false}'; exit;
}
if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); // 세션은 읽기만 — 다른 요청을 붙잡지 않게

$op = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
$k  = is_string($_POST['k'] ?? null) ? $_POST['k'] : '';
// 키 모양: 'bidx|경로' 또는 'bidx|압축 경로|속 영상 이름' (viewer.php 가 만드는 것과 같음)
if (!in_array($op, ['put', 'del'], true) || $k === '' || strlen($k) > 2000 || !preg_match('/^\d{1,4}\|/', $k) || preg_match('/[\x00-\x1f]/', $k)) {
    http_response_code(400); echo '{"ok":false}'; exit;
}
$num = function ($n, $max) { $v = $_POST[$n] ?? null; return (is_string($v) && preg_match('/^\d{1,10}$/', $v) && (int)$v <= $max) ? (int)$v : -1; };
$t = $num('t', 10000000); $d = $num('d', 10000000); $at = $num('at', 4102444800);
if ($op === 'put' && ($t < 0 || $d <= 0 || $at <= 0 || $t > $d)) { http_response_code(400); echo '{"ok":false}'; exit; }
if ($at <= 0) $at = time(); // 지우기에 시각이 없으면 서버 시각

$file = __DIR__ . '/src/' . $uid . '_vres.json';
$fp = @fopen($file, 'c+');
if (!$fp) { http_response_code(500); echo '{"ok":false}'; exit; }
$ok = false;
try {
    if (flock($fp, LOCK_EX)) {
        $all = json_decode((string)stream_get_contents($fp), true);
        if (!is_array($all)) $all = [];
        if ($op === 'put') {
            // 다른 기기가 더 나중에 저장했으면 덮지 않음
            if (!isset($all[$k]['at']) || (int)$all[$k]['at'] <= $at) $all[$k] = ['t' => $t, 'd' => $d, 'at' => $at];
            if (count($all) > 1000) {
                uasort($all, function ($a, $b) { return ((int)($b['at'] ?? 0)) <=> ((int)($a['at'] ?? 0)); });
                $all = array_slice($all, 0, 1000, true);
            }
        } else {
            if (!isset($all[$k]['at']) || (int)$all[$k]['at'] <= $at) $all[$k] = ['x' => 1, 'at' => $at]; // 지운 표시(읽을 땐 없음으로 — function.php mc_vres_get)
        }
        $json = json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (is_string($json)) { ftruncate($fp, 0); rewind($fp); fwrite($fp, $json); fflush($fp); $ok = true; }
    }
} finally {
    @flock($fp, LOCK_UN); @fclose($fp);
}
echo $ok ? '{"ok":true}' : '{"ok":false}';
