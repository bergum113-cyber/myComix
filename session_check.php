<?php
/**
 * 세션 상태 체크 API
 * blank.php에서 탭 복귀 시 세션 유효 여부 확인용
 * 
 * @version 1.2 - CSP 헤더 추가, 보안 헤더 통일
 * @date 2026-01-10
 */

// ✅ config.php 로드 (CSP 규칙 포함)
require_once __DIR__ . '/config.php';

// ✅ 통합 보안 헤더 설정 (CSP 포함)
if (function_exists('set_security_headers_unified')) {
    set_security_headers_unified();
}

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// JSON 응답 헤더
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// 세션에 user_id가 있으면 유효
$valid = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

echo json_encode(['valid' => $valid]);