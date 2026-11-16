<?php
/**
 * ★ (2026-11-16 09:31, 펜닐 요청 — fix181) 전자책·텍스트 뷰어의 '브라우저 저장소(localStorage)에 두는 설정'을 서버(계정별)에도 저장 — src/<아이디>_vprefs.json 의 'l'
 *   js/mc-lsync.js 가 이 기기에서 바뀐 설정을 보냄(POST k·v). 읽기는 뷰어가 <head> 에 넣어 줌(function.php mc_lprefs_head)
 *   · 받는 키·값 모양은 function.php mc_lprefs_keys() 만 — 화면 크기마다 다른 설정(글자 크기·여백·한 쪽/두 쪽 등)은 기기마다 그대로라 받지 않음
 *   · 쿠키 설정(mc_prefs_sync)과 같은 파일·같은 잠금 — 쓸 때 다른 칸('v'·'p')은 그대로 둠
 *   · 로그인한 사람만, 다른 사이트에서 온 요청은 받지 않음(vres.php 와 같은 방식)
 *   · 자동 로그아웃용 활동 기록은 바꾸지 않음(설정 저장은 사람의 조작으로 치지 않음 — vres.php·blank.php 와 같음)
 */
define('MC_NO_ACTIVITY_TOUCH', true);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$uid = function_exists('mc_prefs_uid') ? mc_prefs_uid() : '';
if ($uid === '' || !function_exists('mc_lprefs_keys') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    http_response_code(403); echo '{"ok":false}'; exit;
}
// 다른 사이트에서 보낸 요청이면(Origin 이 있고 이 사이트가 아님) 거절
$_vp_origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($_vp_origin !== '' && strtolower((string)parse_url($_vp_origin, PHP_URL_HOST)) !== strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')))) {
    http_response_code(403); echo '{"ok":false}'; exit;
}
if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); // 세션은 읽기만 — 다른 요청을 붙잡지 않게

// ★ (2026-11-16 10:52, 펜닐 요청 — fix183) op=off: 만화 북모드 '짝 한 쪽 밀기(이 책만)' — b(콘텐츠 폴더 번호)·f(책 경로)·v('1' 켬 / '0' 끔). 'o' 칸에 책마다, 최대 1000권(오래된 것부터 지움)
//   끔('0')도 남김 — 다른 기기에 남은 예전 '켬'을 꺼 주려고(function.php mc_voff_get · viewer.php)
if (($_POST['op'] ?? null) === 'off') {
    $b = is_string($_POST['b'] ?? null) ? $_POST['b'] : '';
    $f = is_string($_POST['f'] ?? null) ? $_POST['f'] : '';
    $v = $_POST['v'] ?? null;
    if (!preg_match('/^\d{1,4}$/', $b) || $f === '' || strlen($f) > 1000 || preg_match('/[\x00-\x1f]/', $f) || ($v !== '1' && $v !== '0') || !function_exists('mc_voff_key')) {
        http_response_code(400); echo '{"ok":false}'; exit;
    }
    $file = __DIR__ . '/src/' . $uid . '_vprefs.json';
    $fp = @fopen($file, 'c+');
    if (!$fp) { http_response_code(500); echo '{"ok":false}'; exit; }
    $ok = false;
    try {
        if (flock($fp, LOCK_EX)) {
            $S = json_decode((string)stream_get_contents($fp), true);
            if (!is_array($S)) $S = [];
            if (!is_array($S['o'] ?? null)) $S['o'] = [];
            $S['o'][mc_voff_key($b, $f)] = ['v' => $v, 'at' => time()];
            if (count($S['o']) > 1000) {
                uasort($S['o'], function ($x, $y) { return ((int)($y['at'] ?? 0)) <=> ((int)($x['at'] ?? 0)); });
                $S['o'] = array_slice($S['o'], 0, 1000, true);
            }
            $json = json_encode($S, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_string($json)) { ftruncate($fp, 0); rewind($fp); fwrite($fp, $json); fflush($fp); $ok = true; }
        }
    } finally {
        @flock($fp, LOCK_UN); @fclose($fp);
    }
    echo $ok ? '{"ok":true}' : '{"ok":false}';
    exit;
}

// ★ (2026-11-16 11:16, 펜닐 요청 — fix184) 전자책·텍스트 '읽은 시간'을 계정에 합산 — src/<아이디>_rtime.json { b: {책 키: {기기: 초}}, d: {날: {기기: 초}}, t: {책 키: 마지막 시각} }
//   js/book-tools.js readTime 이 '이 기기가 이 책을 읽은 총 시간·오늘 읽은 시간'을 보냄(op=rt) — 늘 총합을 보내고 서버는 기기마다 큰 값만 남김
//   → 같은 값이 두 번 가거나(떠날 때 보낸 것이 늦게 닿음) 순서가 바뀌어도 두 번 더해지지 않음. 이 기기의 예전 기록도 첫 전송에 그대로 올라감
//   op=rtget: 이 책·그날의 '다른 기기들' 합 — 화면은 다른 기기 합 + 이 기기 총합을 보여 줌
//   책 1000권·날 60일·책마다 기기 10대까지(넘으면 오래된 것부터 지움)
if (($_POST['op'] ?? null) === 'rt' || ($_POST['op'] ?? null) === 'rtget') {
    $dev = is_string($_POST['dev'] ?? null) ? $_POST['dev'] : '';
    $k = is_string($_POST['k'] ?? null) ? $_POST['k'] : '';
    $day = is_string($_POST['day'] ?? null) ? $_POST['day'] : '';
    $int = function ($n, $max) { $v = $_POST[$n] ?? null; return (is_string($v) && preg_match('/^\d{1,9}$/', $v) && (int)$v <= $max) ? (int)$v : -1; };
    if (!preg_match('/^[a-z0-9]{8,16}$/', $dev) || $k === '' || strlen($k) > 300 || !preg_match('/^(epub|txt):\d{1,4}:/', $k) || preg_match('/[\x00-\x1f]/', $k) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        http_response_code(400); echo '{"ok":false}'; exit;
    }
    $file = __DIR__ . '/src/' . $uid . '_rtime.json';
    $others = function ($map) use ($dev) { $n = 0; if (is_array($map)) foreach ($map as $d => $s) { if ((string)$d !== $dev) $n += (int)$s; } return $n; };
    if ($_POST['op'] === 'rtget') {
        $R = is_file($file) ? (function_exists('load_json_with_lock') ? load_json_with_lock($file) : json_decode((string)@file_get_contents($file), true)) : [];
        if (!is_array($R)) $R = [];
        echo json_encode(['ok' => true, 'ob' => $others($R['b'][$k] ?? null), 'od' => $others($R['d'][$day] ?? null)]);
        exit;
    }
    $s = $int('s', 100000000); $ds = $int('ds', 86400);
    if ($s < 0 || $ds < 0) { http_response_code(400); echo '{"ok":false}'; exit; }
    $fp = @fopen($file, 'c+');
    if (!$fp) { http_response_code(500); echo '{"ok":false}'; exit; }
    $ok = false;
    try {
        if (flock($fp, LOCK_EX)) {
            $R = json_decode((string)stream_get_contents($fp), true);
            if (!is_array($R)) $R = [];
            foreach (['b', 'd', 't'] as $_c) if (!is_array($R[$_c] ?? null)) $R[$_c] = [];
            if (!is_array($R['b'][$k] ?? null)) $R['b'][$k] = [];
            $R['b'][$k][$dev] = max((int)($R['b'][$k][$dev] ?? 0), $s);
            if (count($R['b'][$k]) > 10) { arsort($R['b'][$k]); $R['b'][$k] = array_slice($R['b'][$k], 0, 10, true); }
            $R['t'][$k] = time();
            if (!is_array($R['d'][$day] ?? null)) $R['d'][$day] = [];
            $R['d'][$day][$dev] = max((int)($R['d'][$day][$dev] ?? 0), $ds);
            if (count($R['d'][$day]) > 10) { arsort($R['d'][$day]); $R['d'][$day] = array_slice($R['d'][$day], 0, 10, true); }
            if (count($R['b']) > 1000) { arsort($R['t']); $R['t'] = array_slice($R['t'], 0, 1000, true); $R['b'] = array_intersect_key($R['b'], $R['t']); }
            if (count($R['d']) > 60) { krsort($R['d']); $R['d'] = array_slice($R['d'], 0, 60, true); }
            $json = json_encode($R, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_string($json)) { ftruncate($fp, 0); rewind($fp); fwrite($fp, $json); fflush($fp); $ok = true; }
        }
    } finally {
        @flock($fp, LOCK_UN); @fclose($fp);
    }
    echo $ok ? '{"ok":true}' : '{"ok":false}';
    exit;
}

$keys = mc_lprefs_keys();
$k = is_string($_POST['k'] ?? null) ? $_POST['k'] : '';
$v = is_string($_POST['v'] ?? null) ? $_POST['v'] : null;
if (!isset($keys[$k]) || $v === null || strlen($v) > 20 || !preg_match($keys[$k], $v)) {
    http_response_code(400); echo '{"ok":false}'; exit;
}

$file = __DIR__ . '/src/' . $uid . '_vprefs.json';
$fp = @fopen($file, 'c+');
if (!$fp) { http_response_code(500); echo '{"ok":false}'; exit; }
$ok = false;
try {
    if (flock($fp, LOCK_EX)) {
        $S = json_decode((string)stream_get_contents($fp), true);
        if (!is_array($S)) $S = [];
        if (!is_array($S['l'] ?? null)) $S['l'] = [];
        foreach ($S['l'] as $_k => $_r) { if (!isset($keys[$_k])) unset($S['l'][$_k]); } // 지금 목록에 없는 키는 정리
        $S['l'][$k] = ['v' => $v, 'at' => time()]; // 받은 순서대로(나중에 바꾼 것이 남음) — 기기 시계는 다를 수 있어 서버 시각
        $json = json_encode($S, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json)) { ftruncate($fp, 0); rewind($fp); fwrite($fp, $json); fflush($fp); $ok = true; }
    }
} finally {
    @flock($fp, LOCK_UN); @fclose($fp);
}
echo $ok ? '{"ok":true}' : '{"ok":false}';
