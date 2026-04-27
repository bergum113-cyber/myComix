<?php
/**
 * myComix 초기화 모듈
 * 
 * @version 2.6 - 탭 열기 시 캐시 플래그 초기화 (sessionStorage 연동)
 * @date 2026-01-18
 */

// ✅ config.php 로드 (이미 로드된 경우 스킵됨 - require_once)
// bootstrap.php에서 먼저 로드되지만, init.php 단독 사용 시에도 동작하도록 유지
require_once __DIR__ . '/config.php';

// ✅ 통합된 보안 헤더 설정 - 레거시 분기 제거
// config.php의 set_security_headers_unified() 사용
if (function_exists('set_security_headers_unified')) {
    set_security_headers_unified();
}

// 리소스 설정
ini_set('memory_limit', '512M');

// ✅ SESSION_NO_START 플래그 체크 - 세션 불필요한 경우
if (defined('SESSION_NO_START') && SESSION_NO_START === true) {
    // 세션 시작하지 않고 종료
    return;
}

// ✅ LOGIN_PAGE 플래그 체크 - login.php에서 사용
if (defined('LOGIN_PAGE') && LOGIN_PAGE === true) {
    // 로그인 페이지는 세션 체크 건너뜀 (무한 리다이렉트 방지)
    // 세션은 시작하되 로그인 체크는 하지 않음
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return;
}

// ✅ 자동 로그아웃 설정 - config.php에서 로드됨
// bootstrap.php → config.php 로드 순서가 보장되므로 $auto_logout_settings는 항상 설정됨
$auto_logout_enabled = $auto_logout_settings['enabled'] ?? true;
$timeout = (int)($auto_logout_settings['timeout'] ?? 600);

// 자동 로그아웃 비활성화 시 세션 유지 시간을 24시간으로 설정
if (!$auto_logout_enabled) {
    $timeout = 86400; // 24시간
}

// ✅ 세션 GC 설정 (세션 시작 전에 설정해야 함)
ini_set('session.gc_maxlifetime', max($timeout, 86400));

// 출력 버퍼 시작
if (!headers_sent()) {
    ob_start();
} else {
    while (ob_get_level()) ob_end_clean();
    ob_start();
}

// 세션 안전 시작
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$now = time();

// ✅ 탭 열기 시 캐시 플래그 초기화 (sessionStorage 연동)
if (isset($_GET['reset_cache']) && $_GET['reset_cache'] === '1') {
    unset($_SESSION['index_refreshed']);
    unset($_SESSION['zip_cache_generated']);
    unset($_SESSION['filelist_cache_generated']);
    
    // ✅ cache 폴더도 비움 (로그인 유지 상태에서 새 탭 열 때)
    if (!empty($_SESSION['user_id'])) {
        $safe_id = preg_replace('/[^a-zA-Z0-9_]/', '', $_SESSION['user_id']);
        $user_cache_dir = __DIR__ . '/cache/' . $safe_id;
        if (is_dir($user_cache_dir)) {
            foreach (glob("$user_cache_dir/*") as $zip_dir) {
                if (is_dir($zip_dir)) {
                    foreach (glob("$zip_dir/*") as $file) { @unlink($file); }
                    @rmdir($zip_dir);
                } else { @unlink($zip_dir); }
            }
            @rmdir($user_cache_dir);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}

// ✅ 현재 스크립트 이름 확인
$current_script = basename($_SERVER['SCRIPT_FILENAME']);

// ✅ 자동 로그아웃 대상 페이지 (설정에서 로드, 없으면 기본값 사용)
// 관리자 페이지에서 체크박스로 선택 가능
$default_auto_logout_pages = ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
$auto_logout_pages = $auto_logout_settings['pages'] ?? $default_auto_logout_pages;
$is_auto_logout_target = in_array($current_script, $auto_logout_pages);

// AJAX 요청으로 세션 체크하는 경우
if (isset($_GET['check_session']) && $_GET['check_session'] === '1') {
    header('Content-Type: application/json');
    
    // 자동 로그아웃 비활성화 시
    if (!$auto_logout_enabled) {
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['status' => 'logged_out']);
        } else {
            echo json_encode(['status' => 'disabled', 'remaining' => -1]);
        }
        exit;
    }
    
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['status' => 'logged_out']);
        exit;
    }
    
    if (isset($_SESSION['last_action'])) {
        $elapsed = $now - $_SESSION['last_action'];
        $remaining = $timeout - $elapsed;
        
        // ✅ 연장 요청인 경우 만료 여부 상관없이 세션 갱신 (admin.php 등 비대상 페이지 지원)
        if (isset($_GET['extend']) && $_GET['extend'] === '1') {
            $_SESSION['last_action'] = $now;
            echo json_encode(['status' => 'active', 'remaining' => $timeout]);
            exit;
        }
        
        if ($remaining <= 0) {
            echo json_encode(['status' => 'expired', 'remaining' => 0]);
            exit;
        }
        
        echo json_encode(['status' => 'active', 'remaining' => $remaining]);
        exit;
    }
    
    $_SESSION['last_action'] = $now;
    echo json_encode(['status' => 'active', 'remaining' => $timeout]);
    exit;
}

// ✅ 비대상 페이지에서 대상 페이지로 이동했는지 확인
$came_from_non_target = isset($_SESSION['on_auto_logout_page']) && $_SESSION['on_auto_logout_page'] === false;

// ✅ "로그인 유지"로 로그인한 경우 비활동 자동 로그아웃 무시
$is_remember_me = isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true;

// ✅ 세션 만료 체크 (자동 로그아웃 활성화 + 대상 페이지 + 비대상에서 온 게 아닐 때 + 로그인 유지 아닐 때)
// 비대상→대상 이동 시에는 카운터 리셋하므로 만료 체크 건너뛰기
// "로그인 유지"로 로그인한 경우 비활동 자동 로그아웃 적용 안 함
if ($auto_logout_enabled && $is_auto_logout_target && !$came_from_non_target && !$is_remember_me && isset($_SESSION['last_action']) && ($now - $_SESSION['last_action']) > $timeout) {
    $user_id = $_SESSION['user_id'] ?? null;
    session_unset();
    session_destroy();
    
    session_start();
    $timeout_minutes = floor($timeout / 60);
    $timeout_seconds = $timeout % 60;
    $timeout_str = $timeout_minutes > 0 ? "{$timeout_minutes}" . __("time_min") : "";
    $timeout_str .= $timeout_seconds > 0 ? " {$timeout_seconds}" . __("time_sec") : "";
    $_SESSION['timeout_message'] = __('init_auto_logout_msg', trim($timeout_str));
}

// ✅ 로그인 유지 토큰 함수들은 security_helpers.php에서 통합 제공
// load_remember_tokens(), save_remember_tokens(), get_device_hash(),
// create_remember_token(), verify_remember_token() 등

// ✅ load_users()는 function.php에서 제공 (bootstrap.php 로드 순서상 항상 먼저 로드됨)

// ✅ 로그인 유지 쿠키로 자동 로그인 시도
if (empty($_SESSION['user_id']) && !isset($_SESSION['timeout_message']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $user_id = verify_remember_token($token);
    
    if ($user_id) {
        // 사용자 정보 확인
        $users = load_users();
        if (isset($users[$user_id])) {
            $user = $users[$user_id];
            
            // ✅ 사용자 상태 체크
            $user_status = $user['status'] ?? 'active';
            
            // 승인 대기 상태: 자동 로그인 불가
            if ($user_status === 'pending') {
                // 토큰 삭제
                delete_remember_token($token);
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                $_SESSION['login_error'] = __('init_pending_approval');
                header("Location: ./login.php");
                exit;
            }
            
            // 정지 상태: 만료 여부 확인
            if ($user_status === 'suspended') {
                $suspension_end = $user['suspension_end'] ?? null;
                
                if ($suspension_end && strtotime($suspension_end) <= time()) {
                    // 정지 기간 만료 - 자동 해제
                    $users[$user_id]['status'] = 'active';
                    $users[$user_id]['suspension_reason'] = null;
                    $users[$user_id]['suspension_start'] = null;
                    $users[$user_id]['suspension_end'] = null;
                    save_users($users);
                    $user = $users[$user_id]; // 갱신된 정보 사용
                } else {
                    // 정지 유효: 자동 로그인 불가
                    delete_remember_token($token);
                    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                    $end_date = date('Y-m-d', strtotime($suspension_end));
                    $_SESSION['login_error'] = __("init_account_suspended", $end_date);
                    header("Location: ./login.php");
                    exit;
                }
            }
            
            // ✅ 세션 고정 공격 방지 (자동 로그인 시)
            session_regenerate_id(true);
            
            // 자동 로그인 성공
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_pass'] = ''; // 토큰 로그인은 비밀번호 해시 없음
            $_SESSION['user_group'] = $user['group'] ?? 'group2';
            $_SESSION['last_action'] = time();
            $_SESSION['auto_login'] = true; // 자동 로그인 표시
            $_SESSION['remember_me'] = true; // 비활동 자동 로그아웃 무시 플래그
            
            // ✅ 캐시 플래그 초기화 - 새 만화 추가 시 캐시 갱신 보장
            unset($_SESSION['index_refreshed']);
            unset($_SESSION['zip_cache_generated']);
            unset($_SESSION['filelist_cache_generated']);
        }
    }
}

// 로그인 여부 확인
if (empty($_SESSION['user_id'])) {
    if (!isset($_SESSION['timeout_message'])) {
        header("Location: ./login.php");
        exit;
    }
}

// ✅ 마지막 활동 시간 및 페이지 타입 갱신
if ($auto_logout_enabled && !empty($_SESSION['user_id']) && !isset($_SESSION['timeout_message'])) {
    if ($is_auto_logout_target) {
        // 대상 페이지: 활동 갱신 (비대상에서 왔으면 카운터 새로 시작)
        $_SESSION['last_action'] = $now;
        $_SESSION['on_auto_logout_page'] = true;
    } else {
        // 비대상 페이지: 갱신 안함, 플래그만 설정
        $_SESSION['on_auto_logout_page'] = false;
    }
}

// ✅ TTL 기반 캐시 자동 정리 (1시간마다 실행, 24시간 이상 된 캐시 삭제)
// 로그인 유지 사용 시에도 캐시가 무한히 쌓이지 않도록 함
if (!empty($_SESSION['user_id'])) {
    $cache_cleanup_interval = 3600; // 1시간마다 정리 실행
    $cache_ttl = 86400; // 24시간 이상 된 캐시 삭제
    $last_cleanup = $_SESSION['last_cache_cleanup'] ?? 0;
    
    if (($now - $last_cleanup) > $cache_cleanup_interval) {
        $_SESSION['last_cache_cleanup'] = $now;
        
        $cache_dir = __DIR__ . '/cache';
        if (is_dir($cache_dir)) {
            // 모든 사용자 캐시 폴더 순회
            foreach (glob($cache_dir . '/*', GLOB_ONLYDIR) as $user_cache_dir) {
                // 압축파일별 캐시 폴더 순회
                foreach (glob($user_cache_dir . '/*', GLOB_ONLYDIR) as $archive_cache_dir) {
                    $dir_mtime = @filemtime($archive_cache_dir);
                    // TTL 초과된 캐시 폴더 삭제
                    if ($dir_mtime && ($now - $dir_mtime) > $cache_ttl) {
                        foreach (glob($archive_cache_dir . '/*') as $file) {
                            @unlink($file);
                        }
                        @rmdir($archive_cache_dir);
                    }
                }
                // 빈 사용자 폴더 삭제
                if (count(glob($user_cache_dir . '/*')) === 0) {
                    @rmdir($user_cache_dir);
                }
            }
        }
    }
}
?>