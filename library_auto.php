<?php
/**
 * 작품 정보(라이브러리) 자동 스캔 확인 — ★ (2026-10-30) 신규
 *
 * 로그인한 사람이 홈·작품 탐색·작품 상세 화면을 열면(mc_lib_auto_tick_script 가 필요할 때만 넣음) POST 로 한 번 호출
 *   - 업로드·삭제 뒤 15초 조용해졌거나, 마지막 스캔 뒤 설정한 시간이 지났으면 → 응답을 먼저 보내고 서버에서 빠른 스캔
 *   - 멈춘 스캔(서버 재시작·시간 제한 등으로 끊김)이 있으면 → 이어서 진행
 *   - 아직이면 {next: 몇 초 뒤 다시 확인} 만 돌려줌
 * 입력은 bidx 하나뿐이고, 할 일은 서버가 정함(정해진 조건일 때만, 동시에 하나만) — 다른 효과 없음
 */
define('MC_NO_ACTIVITY_TOUCH', true); // ★ 백그라운드 요청 — 자동 로그아웃용 활동 기록을 건드리지 않음(init.php)
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/library.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['run' => '', 'next' => 0]);
    exit;
}
$bidx = (int)($_POST['bidx'] ?? 0);
if (!mc_lib_available() || !mc_lib_db_exists() || !mc_lib_base($bidx) || !mc_lib_db()) {
    echo json_encode(['run' => '', 'next' => 0]);
    exit;
}
try {
    $d = mc_lib_auto_due($bidx);
} catch (\Throwable $e) {
    error_log('[library_auto] ' . $e->getMessage());
    echo json_encode(['run' => '', 'next' => 0]);
    exit;
}
if ($d['run'] === '') {
    echo json_encode($d);
    exit;
}
mc_lib_detach_json(['run' => $d['run'], 'next' => 0]);
mc_lib_scan_run($bidx, $d['run'], 'auto');
exit;
