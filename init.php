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
        // ★ (2026-10-30) 실제 캐시 폴더(viewer·warmup)와 같은 규칙(영문·숫자만) — 밑줄 있는 아이디는 다른 폴더를 지우려 해 비워지지 않았음
        $safe_id = preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['user_id']);
        $user_cache_dir = __DIR__ . '/cache/' . $safe_id;
        if ($safe_id !== '' && !in_array(strtolower($safe_id), ['vframes', 'vprobe', 'vlock'], true) && is_dir($user_cache_dir)) { // ★ (2026-11-02 14:26) 빈 이름·공용 캐시 폴더는 안 지움
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
            // ★ (2026-10-30) 자동 로그아웃 대상 화면에서 이미 시간이 지난 세션은 연장하지 않음
            //   (휴대폰에서 오래 백그라운드에 둔 탭을 다시 만지면 만료를 무시하고 되살아나던 문제)
            //   비대상 화면(관리자 등)·로그인 유지는 전처럼 연장
            if ($remaining <= 0 && ($_SESSION['on_auto_logout_page'] ?? false) === true
                && !(isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true)
                && !(($_GET['keep'] ?? '') === 'admin' && trim((string)($_SESSION['user_group'] ?? '')) === 'admin')) { // 관리자 화면 유지 요청은 전처럼 연장
                echo json_encode(['status' => 'expired', 'remaining' => 0]);
                exit;
            }
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
    $__keep_lang = $_SESSION['lang'] ?? null; // ★ (2026-10-30) 안내 문구를 사용자 언어로
    if ($__keep_lang === null && $user_id && function_exists('load_users')) { $__u = load_users(); $__keep_lang = $__u[$user_id]['lang'] ?? null; }
    session_unset();
    session_destroy();
    
    session_start();
    if (is_string($__keep_lang) && $__keep_lang !== '') $_SESSION['lang'] = $__keep_lang;
    // ★ (2026-10-30) 언어는 bootstrap 이 init.php 다음에 불러와서, 여기서 만든 안내 문구가 번역 키 그대로(init_auto_logout_msg) 나왔음 → 먼저 불러옴
    if (empty($GLOBALS['_LANG']) && function_exists('init_language')) init_language();
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
if (empty($_SESSION['user_id']) && !isset($_SESSION['timeout_message']) && isset($_COOKIE['remember_token']) && function_exists('verify_remember_token')) { // ★ (2026-11-02 14:27) init.php 를 직접 열면 함수가 없어 500 이던 것
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
                if (empty($GLOBALS['_LANG']) && function_exists('init_language')) init_language(); // ★ (2026-10-30) 번역 먼저
                $_SESSION['login_error'] = __('init_pending_approval');
                header("Location: ./login.php");
                exit;
            }
            
            // 정지 상태: 만료 여부 확인
            if ($user_status === 'suspended') {
                // ★ (2026-10-30) mc_user_suspend_state 로 통일(키 이름이 달라 만료 해제가 안 되고 날짜가 1970-01-01 로 나오던 문제)
                $__sus = mc_user_suspend_state($user);
                if ($__sus['expired']) {
                    // 정지 기간 만료 - 자동 해제
                    mc_user_clear_suspend($users[$user_id]);
                    save_users($users);
                    $user = $users[$user_id]; // 갱신된 정보 사용
                } elseif ($__sus['blocked']) { // 시작일 전(예약)이면 통과
                    // 정지 유효: 자동 로그인 불가
                    delete_remember_token($token);
                    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                    if (empty($GLOBALS['_LANG']) && function_exists('init_language')) init_language(); // 번역 먼저
                    $_SESSION['login_error'] = ($__sus['until'] !== '' ? __("init_account_suspended", $__sus['until']) : __('login_error_suspended_indef'));
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
            // ★ (2026-11-04 00:47) 비밀번호 변경·'모든 기기 로그아웃' 기준값을 로그인할 때 바로 기억 — 전엔 첫 확인 때 그 시점 값으로 채워, 그 사이 바뀐 것을 놓치고 살아남았음
            $_SESSION['mc_pw_seen'] = (string)($user['password_changed_at'] ?? '');
            $_SESSION['mc_rev_seen'] = (string)($user['sessions_revoked_at'] ?? '');
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
// ★ (2026-11-08 전체 점검) 자동 로그아웃 안내(timeout_message)가 남아 있을 때 로그인 없이 통과시키는 것은 안내 창을 띄우는 화면(handle_timeout_popup 을 부르는 곳)만 —
//   예전엔 모든 화면이 통과해, 안내를 띄우지 않는 표지(thumb.php)·미리 풀기(warmup.php) 등이 로그인 없이 응답했음(원본부터, 재현함). 그 밖의 화면은 로그인 안 한 것과 같게 로그인 화면으로(안내는 세션에 남아 다음 화면에서 뜸)
$mc_timeout_popup_pages = ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmarks.php', 'browse.php', 'favorites.php', 'hwp_viewer.php', 'office_viewer.php', 'rhwp_viewer.php', 'series.php'];
if (empty($_SESSION['user_id'])) {
    if (!isset($_SESSION['timeout_message']) || !in_array($current_script, $mc_timeout_popup_pages, true)) {
        header("Location: ./login.php");
        exit;
    }
}

// ★ (2026-10-30) 이미 로그인한 세션도 2분마다 계정 상태 확인 — 정지·삭제된 계정이 열려 있던 세션(로그인 유지 포함)으로 계속 쓰지 못하게
if (!empty($_SESSION['user_id']) && !defined('LOGIN_PAGE') && function_exists('load_users') && function_exists('mc_user_suspend_state')
    && ($now - (int)($_SESSION['mc_status_checked'] ?? 0)) > 120) {
    $_SESSION['mc_status_checked'] = $now;
    $__us = load_users();
    $__me = is_array($__us) ? ($__us[$_SESSION['user_id']] ?? null) : null;
    // ★ (2026-11-02 08:51) 관리자가 그룹을 바꾸면 열려 있던 세션에도 반영(예전엔 다시 로그인할 때까지 옛 그룹 — 관리자에서 내려도 관리자 화면을 계속 썼음)
    if (is_array($__me) && isset($__me['group']) && is_string($__me['group']) && $__me['group'] !== '' && ($_SESSION['user_group'] ?? null) !== $__me['group']) {
        $_SESSION['user_group'] = $__me['group'];
    }
    // ★ (2026-11-02 08:51) 비밀번호가 바뀌면(관리자가 바꿈 등) 그 전에 열려 있던 세션은 끝냄 — 처음 확인할 때 값을 기억해 두고 달라지면 로그아웃
    $__pw = is_array($__me) ? (string)($__me['password_changed_at'] ?? '') : '';
    if (!isset($_SESSION['mc_pw_seen'])) $_SESSION['mc_pw_seen'] = $__pw;
    $__pw_changed = is_array($__me) && $_SESSION['mc_pw_seen'] !== $__pw;
    // ★ (2026-11-02 09:58) '모든 기기에서 로그아웃'을 누른 뒤 다른 기기에 열려 있던 세션도 끝냄(전에는 로그인 유지 토큰만 지워 열린 세션은 계속 쓸 수 있었음)
    $__rv = is_array($__me) ? (string)($__me['sessions_revoked_at'] ?? '') : '';
    if (!isset($_SESSION['mc_rev_seen'])) $_SESSION['mc_rev_seen'] = $__rv;
    $__revoked = is_array($__me) && $_SESSION['mc_rev_seen'] !== $__rv;
    if ($__revoked) $__pw_changed = true;
    // ★ (2026-11-02 14:27) 관리자가 '승인 대기'로 되돌린 회원의 열린 세션도 끝냄(로그인·토큰 로그인은 이미 막고 있었음)
    $__pending = is_array($__me) && (string)($__me['status'] ?? 'active') === 'pending';
    if (is_array($__us) && $__us && ($__me === null || $__pw_changed || $__pending || mc_user_suspend_state($__me)['blocked'])) {
        if (isset($_COOKIE['remember_token']) && function_exists('delete_remember_token')) {
            delete_remember_token($_COOKIE['remember_token']);
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        $__lang = $_SESSION['lang'] ?? null;
        session_unset();
        session_destroy();
        session_start();
        if ($__lang) $_SESSION['lang'] = $__lang;
        if (empty($GLOBALS['_LANG']) && function_exists('init_language')) init_language();
        $__st = ($__me !== null) ? mc_user_suspend_state($__me) : null;
        $_SESSION['login_error'] = ($__st && !empty($__st['blocked']) && $__st['until'] !== '') ? __('login_error_suspended', $__st['until']) // ★ (2026-11-02 11:39) 지금 정지 중일 때만(기간이 끝난 기록이 남은 회원에게 '정지' 문구가 나왔음)
            : ($__me === null ? __('login_error_not_found')
            : ($__pending ? __('login_error_pending')
            : (($__st && !empty($__st['blocked'])) ? __('login_error_suspended_indef')
            : (!empty($__revoked) ? __('login_all_devices_logout') : __('login_error_pw_changed'))))); // ★ (2026-11-02 09:58) 비밀번호 변경·모든 기기 로그아웃이면 그 문구(전에는 '계정이 정지되었습니다'가 나왔음)
        header("Location: ./login.php");
        exit;
    }
}

// ✅ 마지막 활동 시간 및 페이지 타입 갱신
// ★ (2026-10-30) 사람이 하지 않은 백그라운드 요청(라이브러리 자동 스캔 확인·스캔 진행 상황 읽기)은 MC_NO_ACTIVITY_TOUCH 를 정의 →
//   활동 시각·페이지 표시를 바꾸지 않음(바꾸면 '비대상 페이지에서 옴'으로 잡혀 다음 화면에서 비활동 자동 로그아웃 검사를 건너뜀)
if ($auto_logout_enabled && !empty($_SESSION['user_id']) && !isset($_SESSION['timeout_message']) && !defined('MC_NO_ACTIVITY_TOUCH')) {
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
    $cache_ttl = 604800; // 7일 이상 된 캐시 삭제 (장시간 후 재압축해제로 위치복원이 어긋나는 빈도 감소)
    $last_cleanup = $_SESSION['last_cache_cleanup'] ?? 0;
    
    if (($now - $last_cleanup) > $cache_cleanup_interval) {
        $_SESSION['last_cache_cleanup'] = $now;
        
        $cache_dir = __DIR__ . '/cache';
        // ★ (2026-11-03 22:14) cache 는 계정별 임시 압축 풀기 전용(펜닐) — 예전에 cache 안에 두던 것 정리: 속도 기록은 src/perf 로 옮기고,
        //   도구 버전·영상 캐시(vframes·vprobe·vlock — 다시 만들 수 있음)는 지움(새 위치 src/tool_ver.json·src/video/)
        if (is_dir($cache_dir . '/perf') && !is_link($cache_dir . '/perf')) {
            $_np = __DIR__ . '/src/perf';
            // ★ (2026-11-04 07:42) src/perf 를 만들지 않음(폴더가 있을 때만 속도 기록 — 펜닐) — 있으면 옮기고, 없으면 옛 기록(cache/perf/날짜.log)은 지움(cache 는 계정별 임시 압축 풀기 전용)
            if (!is_dir($_np)) {
                foreach ((@scandir($cache_dir . '/perf') ?: []) as $_pf) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}\.log$/', $_pf)) @unlink($cache_dir . '/perf/' . $_pf);
                }
                @rmdir($cache_dir . '/perf');
            } else { // ★ (2026-11-03 23:34) 새 위치에 옮김
                foreach ((@scandir($cache_dir . '/perf') ?: []) as $_pf) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}\.log$/', $_pf)) continue;
                    $_src = $cache_dir . '/perf/' . $_pf; $_dst = $_np . '/' . $_pf;
                    if (!is_file($_dst)) { @rename($_src, $_dst); continue; }
                    // ★ (2026-11-03 23:34) 새 위치에 같은 날짜 기록이 이미 있으면(새 코드가 먼저 씀) 옛 내용을 덧붙이고 지움 — 종전엔 옛 기록을 그냥 지웠음
                    $_old = @file_get_contents($_src);
                    if (is_string($_old) && ($_old === '' || @file_put_contents($_dst, $_old, FILE_APPEND | LOCK_EX) !== false)) @unlink($_src);
                }
                @rmdir($cache_dir . '/perf');
            }
        }
        if (is_file($cache_dir . '/tool_ver.json')) @unlink($cache_dir . '/tool_ver.json');
        foreach (['vframes', 'vprobe', 'vlock'] as $_vo) {
            $_vd = $cache_dir . '/' . $_vo;
            if (!is_dir($_vd) || is_link($_vd)) continue;
            try {
                $_it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_vd, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($_it as $_vf) { $_p = $_vf->getPathname(); if ($_vf->isLink() || !$_vf->isDir()) @unlink($_p); else @rmdir($_p); }
            } catch (\Throwable $e) {}
            @rmdir($_vd);
        }
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