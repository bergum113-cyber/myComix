<?php
/**
 * myComix 공통 초기화 모듈 (Bootstrap)
 * 
 * 모든 페이지에서 일관된 초기화를 보장하는 단일 진입점
 * 
 * @version 1.7 - 레거시 전역 변수 @deprecated 표시 추가
 * @date 2026-01-11
 * 
 * 사용법:
 *   require_once __DIR__ . '/bootstrap.php';
 *   $bidx = init_bidx();  // bidx 초기화 (필요한 경우)
 * 
 * 로드 순서:
 *   1. config.php (설정)
 *   2. security_helpers.php (보안 함수, 토큰 함수)
 *   3. function.php (유틸리티 함수)
 *   4. init.php (세션, 보안 헤더, 자동 로그인)
 *   5. cache_util.php (이미지 압축, 캐시 관리)
 * 
 * 제공되는 전역 변수 (init_bidx 호출 후):
 *   - $base_path: URL 기본 경로 (예: "/mycomix" 또는 "")
 *   - $base_dir: 현재 선택된 기본 디렉토리
 *   - $current_bidx: 현재 bidx 값
 *   - $bidx_param: URL 파라미터용 문자열 ("&bidx=N")
 *   - $bidx_query: 쿼리 시작용 문자열 ("?bidx=N")
 *   - $bookmark_file: 북마크 파일 경로 (하위 호환)
 *   - $autosave_file: 자동저장 파일 경로 (하위 호환)
 *   - $recent_file: 최근 파일 경로 (하위 호환)
 * 
 * 브랜딩 로드:
 *   load_branding() 함수 사용 (function.php에서 제공)
 */

// ============================================================
// 이미 로드되었는지 확인 (중복 로드 방지)
// ============================================================
if (defined('MYCOMIX_BOOTSTRAP_LOADED')) {
    return;
}
define('MYCOMIX_BOOTSTRAP_LOADED', true);

// ============================================================
// IP/국가 차단 체크 (가장 먼저 실행)
// ============================================================
if (file_exists(__DIR__ . '/ip_block.php')) {
    require_once __DIR__ . '/ip_block.php';
    check_ip_block();
}

// ============================================================
// 필수 파일 로드 (정해진 순서대로)
// ============================================================

// 1. 설정 로드 (다른 모듈에서 필요)
require_once __DIR__ . '/config.php';

// 2. 보안 헬퍼 (토큰 함수 등 - init.php에서 사용)
require_once __DIR__ . '/security_helpers.php';

// 2.5. 다국어(i18n) 지원
require_once __DIR__ . '/i18n.php';

// 3. 유틸리티 함수
require_once __DIR__ . '/function.php';

// 4. 초기화 (세션, 보안 헤더, 자동 로그인)
// ※ config.php, security_helpers.php가 이미 로드된 상태
require_once __DIR__ . '/init.php';

// 5. 캐시 유틸리티 (이미지 압축, 캐시 관리)
// ※ 개별 페이지에서 별도 로드 불필요
require_once __DIR__ . '/cache_util.php';

// 6. 언어 초기화 (세션 시작 후 실행)
init_language();

// ============================================================
// 전역 $base_path 설정 (URL 기본 경로)
// ============================================================
// 모든 페이지에서 공통으로 사용하는 URL 기본 경로
// 예: /mycomix 또는 빈 문자열 (루트)
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// ============================================================
// 공통 bidx (Base Directory Index) 처리 함수
// ============================================================

if (!function_exists('init_bidx')) {
/**
 * bidx 파라미터 초기화 및 전역 변수 설정
 * 
 * @param int|null $override_bidx 강제로 사용할 bidx 값 (null이면 GET 파라미터 사용)
 * @return int 초기화된 bidx 값
 * 
 * 설정되는 전역 변수:
 *   - $base_dir: 현재 선택된 기본 디렉토리
 *   - $current_bidx: 현재 bidx 값
 *   - $bidx_param: URL 파라미터용 문자열 ("&bidx=N")
 *   - $bidx_query: 쿼리 시작용 문자열 ("?bidx=N")
 */
function init_bidx($override_bidx = null) {
    global $base_dirs, $base_dir, $current_bidx, $bidx_param, $bidx_query;
    
    // bidx 값 결정 (override > GET > POST > 기본값 0)
    if ($override_bidx !== null) {
        $bidx = (int)$override_bidx;
    } else {
        $bidx = isset($_GET['bidx']) ? (int)$_GET['bidx'] : 
               (isset($_POST['bidx']) ? (int)$_POST['bidx'] : 0);
    }
    
    // 유효성 검증
    if ($bidx < 0 || !isset($base_dirs[$bidx])) {
        $bidx = 0;
    }
    
    // 전역 변수 설정
    $base_dir = $base_dirs[$bidx] ?? '';
    $current_bidx = $bidx;
    $bidx_param = "&bidx={$bidx}";
    $bidx_query = "?bidx={$bidx}";
    
    return $bidx;
}
}

if (!function_exists('add_bidx_to_url')) {
/**
 * URL에 bidx 파라미터 추가
 * 
 * 다중 폴더 환경에서 링크 생성 시 bidx 유지를 위해 사용
 * 
 * @param string $url 원본 URL
 * @param int|null $bidx bidx 값 (null이면 현재 bidx 사용)
 * @return string bidx가 추가된 URL
 * 
 * @example
 * // 현재 bidx 유지하며 링크 생성
 * $link = add_bidx_to_url('viewer.php?file=test.zip');
 * // 결과: viewer.php?file=test.zip&bidx=1
 * 
 * // 특정 bidx로 링크 생성
 * $link = add_bidx_to_url('index.php', 2);
 * // 결과: index.php?bidx=2
 * 
 * @note 커스텀 확장용 유틸리티 함수. 기본 코드에서는 직접 $bidx_param 사용
 */
function add_bidx_to_url($url, $bidx = null) {
    global $current_bidx;
    
    $bidx = $bidx ?? $current_bidx ?? 0;
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    
    return $url . $separator . 'bidx=' . $bidx;
}
}

// ============================================================
// 자동 로그아웃 팝업 처리 함수
// ============================================================

if (!function_exists('handle_timeout_popup')) {
/**
 * 자동 로그아웃 메시지 팝업 처리
 * init.php에서 설정된 timeout_message를 확인하고 팝업 표시
 * 
 * @return bool 팝업이 표시되었으면 true (스크립트 종료됨)
 */
function handle_timeout_popup() {
    if (!isset($_SESSION['timeout_message'])) {
        return false;
    }
    
    // 메시지를 변수에 저장하고 세션에서 즉시 삭제
    $logout_message = $_SESSION['timeout_message'];
    unset($_SESSION['timeout_message']);
    
    // JavaScript 팝업 출력
    echo '<script>';
    echo "var logoutMessage = " . json_encode($logout_message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";\n";
    echo "if (typeof showCustomPopup === 'function') {\n";
    echo "    showCustomPopup(logoutMessage, function() { location.href = 'login.php'; });\n";
    echo "} else {\n";
    echo "    alert(logoutMessage);\n";
    echo "    location.href = 'login.php';\n";
    echo "}\n";
    echo '</script>';
    
    exit;
}
}

// ============================================================
// 사용자 파일 경로 함수 (권장 방식)
// ※ 새 코드에서는 이 함수들을 사용하세요
// ============================================================

if (!function_exists('get_bookmark_file')) {
/**
 * 현재 사용자의 북마크 파일 경로 반환
 * 
 * @return string 북마크 파일 경로
 */
function get_bookmark_file() {
    $user_id = $_SESSION['user_id'] ?? 'guest';
    return __DIR__ . '/src/' . preg_replace('/[^a-zA-Z0-9_]/', '', $user_id) . '_bookmark.json';
}

// ✅ [B단계1] 뷰어 이미지 크기 캐시 파일(계정별). 콜드 리로드 높이예약용. get_bookmark_file 패턴 동일.
function get_dims_file() {
    $user_id = $_SESSION['user_id'] ?? 'guest';
    return __DIR__ . '/src/' . preg_replace('/[^a-zA-Z0-9_]/', '', $user_id) . '_dims.json';
}
}

if (!function_exists('get_autosave_file')) {
/**
 * 현재 사용자의 자동저장 파일 경로 반환
 * 
 * @return string 자동저장 파일 경로
 */
function get_autosave_file() {
    $user_id = $_SESSION['user_id'] ?? 'guest';
    return __DIR__ . '/src/' . preg_replace('/[^a-zA-Z0-9_]/', '', $user_id) . '_autosave.json';
}
}

if (!function_exists('get_favorites_file')) {
/**
 * 현재 사용자의 즐겨찾기 파일 경로 반환
 * 
 * @return string 즐겨찾기 파일 경로
 */
function get_favorites_file() {
    $user_id = $_SESSION['user_id'] ?? 'guest';
    return __DIR__ . '/src/' . preg_replace('/[^a-zA-Z0-9_]/', '', $user_id) . '_favorites.json';
}
}

if (!function_exists('get_file_created_time')) {
/**
 * 파일의 생성 시간 반환 (NEW 딱지용)
 * Windows에서 filectime()이 가끔 미래 시간을 반환하는 버그 대응
 * 
 * @param string $filepath 파일 경로
 * @return int 유효한 생성 시간 (Unix timestamp), 실패시 0
 */
function get_file_created_time($filepath) {
    $ctime = @filectime($filepath);
    if ($ctime === false) return 0;
    
    $now = time();
    // 미래 시간이면 무효 (버그)
    if ($ctime > $now) {
        return 0;
    }
    return $ctime;
}
}

if (!function_exists('get_recent_file')) {
/**
 * 현재 사용자의 최근 파일 경로 반환
 * 
 * @return string 최근 파일 경로
 */
function get_recent_file() {
    $user_id = $_SESSION['user_id'] ?? 'guest';
    return __DIR__ . '/src/' . preg_replace('/[^a-zA-Z0-9_]/', '', $user_id) . '_recent.json';
}
}

if (!function_exists('get_epub_progress_file')) {
/**
 * 현재 사용자의 EPUB 진행 파일 경로 반환
 * 
 * @return string EPUB 진행 파일 경로
 */
function get_epub_progress_file() {
    $user_id = $_SESSION['user_id'] ?? 'guest';
    return __DIR__ . '/src/' . preg_replace('/[^a-zA-Z0-9_]/', '', $user_id) . '_epub_progress.json';
}
}

if (!function_exists('get_txt_progress_file')) {
/**
 * 현재 사용자의 TXT 진행 파일 경로 반환
 * 
 * @return string TXT 진행 파일 경로
 */
function get_txt_progress_file() {
    $user_id = $_SESSION['user_id'] ?? 'guest';
    return __DIR__ . '/src/' . preg_replace('/[^a-zA-Z0-9_]/', '', $user_id) . '_txt_progress.json';
}
}

// ============================================================
// ★ (2026-11-06 00:50) 콘텐츠 폴더(bidx)가 달라도 위치·이름이 같은 책의 기록이 섞이지 않게 — 기록 이름(키) 규칙
//   · 자동 저장·북마크·즐겨찾기(값에 bidx 가 있음): 키는 예전 그대로 경로("/폴더/책.zip").
//     같은 경로를 다른 콘텐츠 폴더에서 저장할 때만 "bidx|/경로" 키를 따로 씀(예전 기록·파일 형식은 그대로라 되돌려도 읽힘)
//     예전엔 같은 경로면 한 칸을 같이 써서, 테스트2 에서 저장하면 테스트1 기록이 덮이고(이어 보기·책갈피 사라짐)
//     즐겨찾기 별·책갈피 쪽은 다른 콘텐츠 폴더의 것이 그대로 보였음(별을 끄면 다른 곳의 즐겨찾기가 지워짐)
//   · TXT·EPUB 읽던 위치(값에 bidx 없음): 새로 저장할 때 "bidx|경로" 키로 옮김(노트 파일과 같은 형식). 예전 키는 읽기만(옮기기 전까지 예전처럼)
// ============================================================
if (!function_exists('mc_rec_split')) {
/** 기록 키 → [bidx, 경로] — "bidx|/경로" 형식이면 앞 숫자, 아니면 값의 bidx(없으면 0) */
function mc_rec_split($key, $val = null) {
    $key = (string)$key;
    if (preg_match('#^(\d{1,4})\|(/.*)$#s', $key, $m)) return [(int)$m[1], $m[2]];
    return [is_array($val) ? (int)($val['bidx'] ?? 0) : 0, $key];
}
/** 이 콘텐츠 폴더·경로의 기록 키(없으면 null) — 따로 쓴 키가 먼저, 다음 예전 키(값의 bidx 가 같거나 bidx 가 없는 옛 형식일 때만) */
function mc_rec_find($arr, $bidx, $path) {
    if (!is_array($arr)) return null;
    $path = (string)$path;
    $k2 = (int)$bidx . '|/' . ltrim($path, '/');
    if (array_key_exists($k2, $arr)) return $k2;
    if ($path !== '' && array_key_exists($path, $arr)) {
        $v = $arr[$path];
        if (!is_array($v) || !isset($v['bidx']) || (int)$v['bidx'] === (int)$bidx) return $path;
    }
    return null;
}
/** 저장할 키 — 이미 있으면 그 키, 없으면 예전 키(경로)가 비어 있을 때 경로 · 다른 콘텐츠 폴더가 쓰고 있으면 "bidx|/경로" */
function mc_rec_key_for_write($arr, $bidx, $path) {
    $k = mc_rec_find($arr, $bidx, $path);
    if ($k !== null) return $k;
    $path = (string)$path;
    return (is_array($arr) && array_key_exists($path, $arr)) ? ((int)$bidx . '|/' . ltrim($path, '/')) : $path;
}
/** 요청에 적힌 bidx 그대로(없으면 $fallback) — 지우기는 설정에서 빠진 콘텐츠 폴더의 기록도 지울 수 있어야 해서(init_bidx 는 없는 번호를 0 으로 바꿈) */
function mc_rec_req_bidx($fallback) {
    foreach ([$_GET['bidx'] ?? null, $_POST['bidx'] ?? null] as $v) {
        if (is_string($v) && preg_match('/^\d{1,4}$/', $v)) return (int)$v;
    }
    return (int)$fallback;
}
/**
 * ★ (2026-11-06 01:05) 지운 파일·폴더의 내 기록(북마크·자동 저장·즐겨찾기) 정리 — 폴더 삭제·여러 개 삭제용(파일 하나 지우기는 index.php 에 원래 있던 정리 그대로)
 *   $files·$dirs: 콘텐츠 폴더 안 상대 경로(앞뒤 '/' 상관없음) / 폴더는 그 폴더 자체와 그 아래 전부("/A" 는 "/AB" 를 건드리지 않음)
 *   그 콘텐츠 폴더(bidx)의 기록만 — 콘텐츠 폴더 번호가 없는 옛 기록은 예전 파일 정리처럼 경로만 보고. 실패해도 삭제 자체에는 영향 없음
 */
function mc_rec_cleanup_deleted($bidx, array $files, array $dirs) {
    $norm = function ($p) { return trim(str_replace('\\', '/', (string)$p), '/'); };
    $fset = []; foreach ($files as $f) { if (is_string($f) && ($f = $norm($f)) !== '') $fset[$f] = true; }
    $dl = []; foreach ($dirs as $d) { if (is_string($d) && ($d = $norm($d)) !== '') $dl[] = $d; }
    if (!$fset && !$dl) return;
    $targets = [];
    foreach (['get_bookmark_file', 'get_autosave_file', 'get_favorites_file'] as $fn) if (function_exists($fn)) $targets[] = $fn();
    $__lk = function_exists('mc_rec_lock') && mc_rec_lock(); // ★ (2026-11-09 11:43) 기록 고치기 차례 잠금(bookmark.php 와 같이) — 끝나면 바로 풂
    foreach ($targets as $cf) {
        try {
            if (!$cf || !is_file($cf) || !function_exists('load_json_with_lock')) continue;
            $arr = load_json_with_lock($cf);
            if (!is_array($arr) || !$arr) continue;
            $changed = false;
            foreach ($arr as $k => $v) {
                [$kb, $kp] = mc_rec_split($k, $v);
                $any = !preg_match('#^\d{1,4}\|/#', (string)$k) && (!is_array($v) || !isset($v['bidx']));
                if (!$any && $kb !== (int)$bidx) continue;
                $rel = $norm($kp);
                $hit = isset($fset[$rel]);
                if (!$hit) foreach ($dl as $d) { if ($rel === $d || strpos($rel, $d . '/') === 0) { $hit = true; break; } }
                if ($hit) { unset($arr[$k]); $changed = true; }
            }
            if ($changed) save_json_with_lock($cf, $arr);
        } catch (\Throwable $e) { /* best-effort */ }
    }
    if ($__lk) mc_rec_lock(true);
}
/** TXT·EPUB 읽던 위치 키("bidx|상대 경로", 앞 '/' 없음 — 예전 키와 노트 키 형식에 맞춤) */
function mc_prog_key($bidx, $rel) {
    return (int)$bidx . '|' . ltrim(str_replace('\\', '/', (string)$rel), '/');
}
/** TXT·EPUB 읽던 위치 찾기 — 새 키 먼저, 없으면 예전 키(콘텐츠 폴더 구분 없던 것, 옮기기 전까지 예전처럼) */
function mc_prog_get($arr, $bidx, $rel) {
    if (!is_array($arr)) return null;
    $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
    $k = mc_prog_key($bidx, $rel);
    if (isset($arr[$k])) return $arr[$k];
    return $arr[$rel] ?? null;
}
}

// ============================================================
// 하위 호환성: 레거시 전역 변수 설정
// ============================================================
// 
// @deprecated 향후 버전에서 제거 예정
// 
// ⚠️ 새 코드에서는 아래 전역 변수 대신 함수를 사용하세요:
//   - $bookmark_file → get_bookmark_file()
//   - $autosave_file → get_autosave_file()
//   - $recent_file   → get_recent_file()
// 
// 이유:
// 1. 전역 변수는 세션 시작 시점에 고정되어 사용자 변경 시 갱신 안 됨
// 2. 함수 호출은 항상 현재 세션의 user_id 기준으로 경로 반환
// 3. 테스트/디버깅 시 함수 모킹이 더 용이함
// 
// 마이그레이션 가이드:
//   변경 전: global $bookmark_file; $data = file_get_contents($bookmark_file);
//   변경 후: $data = file_get_contents(get_bookmark_file());
// ============================================================

/** @deprecated get_bookmark_file() 사용 권장 */
$bookmark_file = get_bookmark_file();

/** @deprecated get_autosave_file() 사용 권장 */
$autosave_file = get_autosave_file();

/** @deprecated get_recent_file() 사용 권장 */
$recent_file = get_recent_file();

// ============================================================
// 자동 로그아웃 스크립트 렌더링 함수 (중복 코드 통합)
// ============================================================

if (!function_exists('render_auto_logout_script')) {
/**
 * 자동 로그아웃 JavaScript 코드를 출력
 * 여러 페이지에서 반복되는 코드를 통합
 * 
 * @return void (직접 출력)
 * 
 * 사용법:
 *   <?php render_auto_logout_script(); ?>
 */
function render_auto_logout_script() {
    global $auto_logout_settings;
    
    // 설정 로드 (없으면 기본값)
    if (!isset($auto_logout_settings)) {
        $auto_logout_settings = ['enabled' => true, 'timeout' => 600, 'pages' => ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php']];
    }
    
    // 비활성화면 종료
    if (!($auto_logout_settings['enabled'] ?? true)) {
        return;
    }
    
    // ✅ "로그인 유지"로 로그인한 경우 자동 로그아웃 무시
    if (isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true) {
        return;
    }
    
    // 현재 페이지가 적용 대상인지 확인
    $current_page = basename($_SERVER['SCRIPT_FILENAME']);
    $auto_logout_pages = $auto_logout_settings['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
    
    if (!in_array($current_page, $auto_logout_pages)) {
        return;
    }
    
    // 타임아웃 계산
    $timeout = (int)($auto_logout_settings['timeout'] ?? 600);
    $last_action = $_SESSION['last_action'] ?? time();
    $elapsed = time() - $last_action;
    $remaining = max(0, $timeout - $elapsed);
    
    // ✅ 전역 $base_path 사용 (bootstrap.php에서 설정됨)
    global $base_path;
    $js_url = $base_path . '/js/auto-logout.js';
    
    // ✅ filemtime 기반 캐시 버스팅 (성능 개선)
    $js_file = __DIR__ . '/js/auto-logout.js';
    $js_version = @filemtime($js_file) ?: '1';
    
    // JavaScript 출력
    echo '<script>' . "\n";
    echo 'window.SESSION_TIMEOUT = ' . $timeout . ';' . "\n";
    echo 'window.SESSION_REMAINING = ' . $remaining . ';' . "\n";
    // 자동 로그아웃 모달 번역
    $al_i18n = json_encode([
        'title' => __('js_auto_logout_title'),
        'countdown' => __('al_countdown'),
        'will_logout' => __('al_will_logout'),
        'continue_msg' => __('js_continue_msg'),
        'logout' => __('js_logout'),
        'extend' => __('js_extend_login'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    echo 'window._alI18n = ' . $al_i18n . ';' . "\n";
    echo '</script>' . "\n";
    echo '<script src="' . htmlspecialchars($js_url) . '?v=' . $js_version . '"></script>' . "\n";
}
}

if (!function_exists('mc_al_i18n_script')) {
/**
 * ★ (2026-10-30) 자동 로그아웃 경고 창 번역(window._alI18n) — auto-logout.js 를 직접 넣는 화면(관리자·뷰어 등)에서도 같이 출력
 *   (전에는 render_auto_logout_script() 를 쓰는 화면에만 있어, 영어 사용자에게도 한국어 기본 문구가 나왔음)
 */
function mc_al_i18n_script() {
    return '<script>window._alI18n = ' . json_encode([
        'title' => __('js_auto_logout_title'),
        'countdown' => __('al_countdown'),
        'will_logout' => __('al_will_logout'),
        'continue_msg' => __('js_continue_msg'),
        'logout' => __('js_logout'),
        'extend' => __('js_extend_login'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>' . "\n";
}
}

if (!function_exists('render_darkmode_script')) {
/**
 * 다크모드 JavaScript 코드를 출력
 * 
 * @return void (직접 출력)
 */
function render_darkmode_script() {
    global $darkmode_settings;
    
    if (!isset($darkmode_settings) || !($darkmode_settings['enabled'] ?? false)) {
        return;
    }
    
    // ✅ 전역 $base_path 사용 (bootstrap.php에서 설정됨)
    global $base_path;
    $js_url = $base_path . '/js/darkmode.js';
    
    // ✅ filemtime 기반 캐시 버스팅
    $js_file = __DIR__ . '/js/darkmode.js';
    $js_version = @filemtime($js_file) ?: '1';
    
    // 다크모드 번역 문자열
    $dm_i18n = json_encode([
        'toLightMode' => __('dm_to_light'),
        'toDarkMode' => __('dm_to_dark'),
        'lightMode' => __('dm_light_mode'),
        'darkMode' => __('dm_dark_mode'),
        'changeTheme' => __('dm_change_theme'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    echo '<script>window._dmI18n = ' . $dm_i18n . ';</script>' . "\n";
    // ★ (2026-10-30) 관리자 설정의 기본 테마(저장된 선택이 없는 처음 방문·새 브라우저에 적용) — 전에는 JS 가 늘 'light' 로 시작해 설정이 무시됐음
    $dm_default = (($darkmode_settings['default'] ?? 'light') === 'dark') ? 'dark' : 'light';
    echo '<script>window._dmDefault = ' . json_encode($dm_default) . ';</script>' . "\n";
    echo '<script src="' . htmlspecialchars($js_url) . '?v=' . $js_version . '"></script>' . "\n";
}
}

if (!function_exists('mc_fade_dark_style')) {
/**
 * ★ (2026-11-08 fix82, 펜닐 제보) 다크모드에서 누르거나 새 화면이 늦게 뜰 때 흰 화면이 보이던 것
 *   목록·뷰어 등은 화면 전체(html)를 투명하게 했다가(처음 html{opacity:0} → .ready 에서 1, 누르면 .leaving 에서 다시 0) 나타나게 하는데,
 *   html 이 투명하면 그 뒤의 브라우저 기본 바탕(흰색)이 보였음 — 자료가 늦게 오면 그만큼 오래
 *   → <html class="mc-fdk"> 이고 어두운 테마일 때(.dark-mode · data-theme="dark" · .mc-fdk-dark)만: html 은 늘 보이게 두고 어두운 바탕을 칠하고, 투명→보임은 body 가 맡음
 *     (밝은 테마는 예전 그대로 — 흰 바탕 위라 차이 없음)
 * @param string $bg 그 화면의 어두운 바탕색(#rrggbb — 코드 안에서 정한 값만)
 */
function mc_fade_dark_style($bg = '#121212') {
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string)$bg)) $bg = '#121212';
    $on = 'html.mc-fdk:is(.dark-mode,[data-theme="dark"],.mc-fdk-dark)';
    return '<style>' . $on . '{opacity:1!important;transition:none!important;background-color:' . $bg . '!important}'
        . $on . '>body{opacity:0;transition:opacity .15s ease-in}'
        . $on . '.ready>body{opacity:1}'
        . $on . '.leaving>body{opacity:0;transition:opacity .1s ease-out}</style>' . "\n";
}
}

if (!function_exists('mc_theme_early_head')) {
/**
 * ★ (2026-11-08 fix80, 펜닐 제보) 다크모드에서 화면을 옮길 때 흰 바탕이 잠깐 보이던 것
 *   다크모드 스크립트(render_darkmode_script)는 본문 끝에서 불려, 그 위의 화면이 먼저 밝게 그려진 뒤 어두워졌음(넘어가는 순간·자료가 늦게 오면 오래 보임)
 *   → <head> 맨 앞에서 저장된 테마(localStorage → darkmode 쿠키 → 관리자 기본값, darkmode.js 와 같은 순서)를 보고 <html> 에 dark-mode 를 먼저 붙임
 *     (darkmode.css 의 html·body 어두운 바탕이 첫 그림부터 적용. 클래스만 붙여 라이트로 바꾸는 단추·darkmode.js 동작은 그대로)
 *   다크모드 기능이 꺼져 있으면 아무것도 내지 않음
 */
function mc_theme_early_head($fade = false) {
    global $darkmode_settings;
    if (!isset($darkmode_settings) || !($darkmode_settings['enabled'] ?? false)) return '';
    $def = (($darkmode_settings['default'] ?? 'light') === 'dark') ? 'dark' : 'light';
    // ★ (2026-11-08 fix82) $fade=true: 화면이 투명했다 나타나는(html opacity) 목록·뷰어 — 다크면 바탕은 어둡게 두고 내용만 나타나게(mc_fade_dark_style)
    return ($fade && function_exists('mc_fade_dark_style') ? mc_fade_dark_style('#121212') : '')
        . '<script>(function(){try{' . ($fade ? 'document.documentElement.classList.add("mc-fdk");' : '') . 'var t=null;try{t=localStorage.getItem("mycomix_theme");}catch(e){}'
        . 'if(t!=="light"&&t!=="dark"){var m=document.cookie.match(/(?:^|;\\s*)darkmode=(light|dark)(?:;|$)/);t=m?m[1]:' . json_encode($def) . ';}'
        . 'if(t==="dark"){var d=document.documentElement;d.classList.add("dark-mode");d.setAttribute("data-theme","dark");}}catch(e){}})();</script>' . "\n";
}
}

if (!function_exists('mc_icons_script')) {
/**
 * ★ (2026-10-30) 화면 이모지 아이콘 → 선 아이콘(js/mc-icons.js) 출력 — 각 페이지 </body> 바로 앞에서 echo
 */
function mc_icons_script() {
    global $base_path;
    $f = __DIR__ . '/js/mc-icons.js';
    if (!is_file($f)) return '';
    return '<script src="' . htmlspecialchars(($base_path ?? '') . '/js/mc-icons.js', ENT_QUOTES, 'UTF-8') . '?v=' . (@filemtime($f) ?: '1') . '"></script>' . "\n";
}
}

if (!function_exists('render_i18n_script')) {
/**
 * 다국어(i18n) JavaScript 스크립트 출력
 * 
 * js/i18n.js를 로드하여 클라이언트 측 번역을 활성화합니다.
 * 뷰어 페이지 등 독립적인 HTML을 가진 페이지에서 호출해야 합니다.
 * 
 * @return void (직접 출력)
 */
function render_i18n_script() {
    global $base_path;
    $js_url = ($base_path ?? '') . '/js/i18n.js';
    
    // filemtime 기반 캐시 버스팅
    $js_file = __DIR__ . '/js/i18n.js';
    $js_version = @filemtime($js_file) ?: '1';
    
    echo '<script src="' . htmlspecialchars($js_url) . '?v=' . $js_version . '"></script>' . "\n";
}
}

if (!function_exists('render_viewer_i18n')) {
/**
 * 뷰어용 JS 번역 객체 출력
 * 
 * PHP __() 함수로 번역된 문자열을 JS 변수로 내보냅니다.
 * JS 코드에서 하드코딩된 한국어 대신 _vi18n.key를 참조합니다.
 * 
 * @param array $extra_keys 뷰어별 추가 키 (선택)
 * @return void (직접 출력)
 */
function render_viewer_i18n($extra_keys = []) {
    // 공통 키
    $keys = [
        'loading' => __('js_loading'),
        'close' => __('js_close'),
        'go_back' => __('js_go_back'),
        'download' => __('js_download'),
        'prev' => __('js_prev'),
        'next' => __('js_next'),
        'calculating' => __('js_calculating'),
        'setting' => __('js_setting'),
        'saving' => __('js_saving'),
        'image' => __('js_image'),
        'size' => __('js_size'),
        'brightness' => __('js_brightness'),
        'no_content' => __('js_no_content'),
        'http_error' => __('js_http_error'),
        'auto_logout_title' => __('js_auto_logout_title'),
        'auto_logout_countdown' => __('js_auto_logout_countdown'),
        'time_hours' => __('js_time_hours'),
        'time_minutes' => __('js_time_minutes'),
        'time_seconds' => __('js_time_seconds'),
    ];
    
    // 뷰어별 추가 키 병합
    foreach ($extra_keys as $js_key => $php_key) {
        $keys[$js_key] = __($php_key);
    }
    
    // ★ (2026-10-30) 언어 파일의 작은따옴표 문자열 속 '\n'(글자 두 개)을 실제 줄바꿈으로 — index·admin 과 같게(안 하면 알림에 \n 이 그대로 보임)
    $keys = array_map(function ($v) { return is_string($v) ? str_replace('\n', "\n", $v) : $v; }, $keys);
    echo '<script>' . "\n";
    echo 'var _vi18n = ' . json_encode($keys, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';' . "\n";
    echo '</script>' . "\n";
}
}


if (!function_exists('render_lang_badge')) {
/**
 * 언어 전환 배지 출력 (인라인)
 * 
 * @param string $size 'sm' (뷰어 툴바용), 'md' (일반), 'lg' (큰 로고 옆)
 * @return void
 */
function render_lang_badge($size = 'md') {
    $available = get_available_langs();
    if (count($available) <= 1) return;
    
    $current = get_current_lang();
    foreach ($available as $lcode => $lname) {
        if ($lcode === $current) continue;
        $url = get_lang_switch_url($lcode);
        
        // sm: 뷰어 툴바 (flex container 안, 버튼들과 나란히)
        // md: 관리자 페이지 (일반 텍스트 흐름)
        // lg: 메인/뷰어 로고 옆 (큰 폰트 옆 인라인)
        $styles = match($size) {
            'sm' => 'font-size:10px; padding:2px 6px; margin-left:4px; position:relative; top:-7px;',
            'sm-epub' => 'font-size:10px; padding:2px 6px; margin-left:4px; position:relative; top:8px;',
            'sm-office' => 'font-size:10px; padding:2px 6px; margin-left:4px; position:relative; top:4px;',
            'sm-hwp' => 'font-size:10px; padding:2px 6px; margin-left:4px; position:relative; top:3px;',
            'md' => 'font-size:11px; padding:3px 7px; margin-left:8px;',
            'lg' => 'font-size:11px; padding:3px 7px; margin-left:10px; vertical-align:baseline; position:relative; top:-7px;',
            'xl' => 'font-size:11px; padding:3px 7px; margin-left:10px; vertical-align:baseline; position:relative; top:-7px;',
            default => 'font-size:11px; padding:3px 7px; margin-left:8px;',
        };
        
        echo ' <a class="badge badge-secondary lang-badge" href="' . htmlspecialchars($url) . '" '
           . 'title="' . htmlspecialchars($lname) . '" '
           . 'style="color:#fff !important; text-decoration:none; white-space:nowrap; '
           . 'display:inline-flex; align-items:center; justify-content:center; gap:2px; '
           . 'vertical-align:middle; border-radius:4px; ' . $styles . '">'
           . '🌐 ' . htmlspecialchars($lname)
           . '</a>';
        break;
    }
}
}

if (!function_exists('render_page_transition_script')) {
/**
 * 페이지 전환 스크립트 출력
 * 
 * @param bool $immediate true면 즉시 이동 (setTimeout 없음)
 * @return void (직접 출력)
 */
function render_page_transition_script($immediate = true) {
    $delay = $immediate ? '' : 'setTimeout(function() { location.href = href; }, 100);';
    $navigate = $immediate ? 'location.href = href;' : $delay;
    
    echo <<<SCRIPT
<script>
(function(){
    document.addEventListener('click', function(e) {
        // ★ (2026-11-02 09:12) 이미 막은 클릭(확인 창 '취소' 등)·새 탭(Ctrl·⌘·Shift·가운데 버튼)·내려받기는 건드리지 않음 — 전에는 같은 탭으로 이동시켰음
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var link = e.target.closest ? e.target.closest('a[href]') : null;
        if (!link || link.hasAttribute('download')) return;
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || link.target === '_blank') return;
        if (href.indexOf('mode=logout') !== -1) return;
        
        // privacy_shield 내부 이동 플래그
        if (typeof window.myComixMarkNavigation === 'function') {
            window.myComixMarkNavigation();
        }
        
        e.preventDefault();
        document.documentElement.classList.add('leaving');
        {$navigate}
    });
    document.addEventListener('submit', function(e) {
        if (e.defaultPrevented) return; // ★ (2026-11-02 11:35) 확인 창 '취소'(return false)면 화면을 흐리지 않음 — 전에는 투명한 채로 남았음
        document.documentElement.classList.add('leaving');
    });
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            document.documentElement.classList.remove('leaving');
            document.documentElement.classList.add('ready');
        }
    });
})();
</script>

SCRIPT;
}
}