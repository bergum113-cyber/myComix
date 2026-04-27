<?php
/**
 * myComix 설정 파일
 * @version 3.2 - IMAGE_EXT_PATTERN 상수 추가
 * @date 2026-01-11
 * 
 * ============================================================
 * 경로 검증 함수 사용 가이드
 * ============================================================
 * 
 * 모든 경로 검증 함수는 security_helpers.php에 통합되었습니다.
 * bootstrap.php를 통해 로드하면 자동으로 사용 가능합니다.
 * 
 * 1. validate_file_path($path, $base_dir)
 *    - 단일 base_dir에서 파일 경로 검증
 *    - 사용처: thumb.php, warmup.php, viewer.php 등
 * 
 * 2. resolve_path_from_basedirs($path, $base_dirs_array)
 *    - 다중 base_dir 환경에서 파일 경로 검색 및 검증
 *    - 사용처: index.php 등 다중 폴더 탐색이 필요한 경우
 * 
 * 3. extract_basedir_index($path)
 *    - 경로에서 [N]/ 형식의 인덱스 추출
 * 
 * 4. prepend_basedir_index($index, $path)
 *    - 경로에 인덱스 접두사 추가
 */

// ============================================================
// 앱 버전
// ============================================================
define('MYCOMIX_VERSION', 'v2.2');

// ============================================================
// 설정 파일 경로
// ============================================================
$settings_file = __DIR__ . '/src/app_settings.json';

// ============================================================
// CSP (Content Security Policy) 규칙 - 전역 통합
// ============================================================
if (!defined('CSP_RULES_DEFINED')) {
    define('CSP_RULES_DEFINED', true);
    
    $GLOBALS['CSP_RULES'] = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: https://www.googletagmanager.com https://www.google-analytics.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
        "img-src 'self' data: blob: https: https://www.google-analytics.com https://www.googletagmanager.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
		"connect-src 'self' blob: https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "object-src 'none'",
        "base-uri 'self'"
    ];
}

/**
 * CSP 헤더 문자열 반환
 * @return string CSP 헤더 값
 */
if (!function_exists('get_csp_header')) {
    function get_csp_header() {
        return implode("; ", $GLOBALS['CSP_RULES']);
    }
}

/**
 * 보안 헤더 설정 (통합)
 * init.php, login.php 등에서 호출
 */
if (!function_exists('set_security_headers_unified')) {
    function set_security_headers_unified() {
        if (headers_sent()) return;
        
        header("X-XSS-Protection: 1; mode=block");
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("Content-Security-Policy: " . get_csp_header());
    }
}

// ============================================================
// 설정 로드 함수
// ============================================================
if (!function_exists('load_app_settings')) {
/**
 * app_settings.json에서 설정 로드
 * @param string $file 설정 파일 경로
 * @return array 설정 배열 (없으면 빈 배열)
 */
function load_app_settings($file) {
    if (!file_exists($file)) {
        return [];
    }
    
    $json = @file_get_contents($file);
    if ($json === false) {
        return [];
    }
    
    $settings = @json_decode($json, true);
    if (!is_array($settings)) {
        return [];
    }
    
    return $settings;
}
}

// ============================================================
// 설정 저장 함수
// ============================================================
if (!function_exists('save_app_settings')) {
function save_app_settings($file, $settings) {
    // src 폴더 생성
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    return @file_put_contents(
        $file, 
        json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}
}

// ============================================================
// 설정 로드
// ============================================================
$app_settings = load_app_settings($settings_file);

// ============================================================
// 설정 접근 함수 (global 대신 권장)
// ============================================================

if (!function_exists('get_app_settings')) {
/**
 * 앱 설정 반환 (global $app_settings 대신 권장)
 * 
 * @param string|null $key 특정 키만 가져올 경우 (null이면 전체)
 * @param mixed $default 키가 없을 경우 기본값
 * @return mixed 설정값 또는 전체 설정 배열
 * 
 * @example
 * // 전체 설정
 * $settings = get_app_settings();
 * 
 * // 특정 키
 * $timeout = get_app_settings('auto_logout.timeout', 600);
 */
function get_app_settings($key = null, $default = null) {
    global $app_settings;
    
    if ($key === null) {
        return $app_settings;
    }
    
    // 점(.) 표기법 지원: 'auto_logout.timeout' → $app_settings['auto_logout']['timeout']
    $keys = explode('.', $key);
    $value = $app_settings;
    
    foreach ($keys as $k) {
        if (!is_array($value) || !isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}
}

if (!function_exists('set_app_settings')) {
/**
 * 앱 설정 저장 (특정 키)
 * 
 * @param string $key 저장할 키
 * @param mixed $value 저장할 값
 * @return bool 저장 성공 여부
 * 
 * @example
 * set_app_settings('smtp', ['host' => 'smtp.gmail.com', ...]);
 */
function set_app_settings($key, $value) {
    global $app_settings, $settings_file;
    
    // 전역 변수 업데이트
    $app_settings[$key] = $value;
    
    // 파일에 저장
    $dir = dirname($settings_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    return @file_put_contents(
        $settings_file, 
        json_encode($app_settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    ) !== false;
}
}

// ============================================================
// 변수 추출 (하위 호환성)
// ============================================================

// 다중 폴더 설정 - app_settings.json에서 로드
$base_dirs = $app_settings['base_dirs'] ?? [];

// base_dirs가 배열이 아니면 빈 배열로
if (!is_array($base_dirs)) {
    $base_dirs = [];
}

// 빈 문자열 경로 제거 및 유효한 경로만 필터링
$base_dirs = array_values(array_filter($base_dirs, function($dir) {
    return !empty(trim($dir));
}));

// ============================================================
// AJAX/API 요청 감지 함수
// ============================================================
// 
// 📌 이 함수는 요청 유형에 따라 다른 응답 형식을 사용할 때 유용합니다.
// 
// 사용 예시:
//   if (is_ajax_or_api_request()) {
//       // JSON 응답
//       json_response(['error' => '권한 없음'], 403);
//   } else {
//       // HTML 에러 페이지
//       die_with_error('권한 없음', 403);
//   }
// 
// 감지 기준:
//   - CLI 모드 실행
//   - X-Requested-With: XMLHttpRequest 헤더
//   - Accept: application/json 헤더
//   - Content-Type: application/json 헤더
//   - URL 파라미터: action, mode, ajax, api, imgfile, thumb
// ============================================================
if (!function_exists('is_ajax_or_api_request')) {
/**
 * AJAX 또는 API 요청인지 감지
 * 
 * @return bool AJAX/API 요청이면 true
 * 
 * @note 내부적으로 세션 시작 여부 결정에 사용됨
 *       외부에서는 응답 형식 선택 시 활용 가능
 */
function is_ajax_or_api_request() {
    // CLI 모드
    if (php_sapi_name() === 'cli') {
        return true;
    }
    
    // XMLHttpRequest 헤더
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    
    // Accept 헤더에 application/json
    if (!empty($_SERVER['HTTP_ACCEPT']) && 
        strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        return true;
    }
    
    // Content-Type이 JSON
    if (!empty($_SERVER['CONTENT_TYPE']) && 
        strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        return true;
    }
    
    // API 파라미터 존재
    if (isset($_GET['action']) || isset($_GET['mode']) || 
        isset($_GET['ajax']) || isset($_GET['api']) ||
        isset($_GET['imgfile']) || isset($_GET['thumb'])) {
        return true;
    }
    
    return false;
}
}

// ✅ base_dirs가 비어있으면 경고 및 사용자 안내
if (empty($base_dirs)) {
    $current_script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    
    // 설정 없이도 접근 가능한 페이지
    $allowed_empty_pages = [
        'admin.php',           // 관리자 페이지
        'login.php',           // 로그인 페이지
        'session_check.php',   // 세션 체크 API
        'thumb.php',           // 썸네일 API
        'warmup.php',          // 캐시 워밍업 API
        'blank.php',           // 프라이버시 실드
    ];
    
    // 리다이렉트 필요 여부 판단
    $should_redirect = !in_array($current_script, $allowed_empty_pages)
                    && !headers_sent()
                    && !defined('SUPPRESS_BASEDIR_WARNING')
                    && !is_ajax_or_api_request();
    
    if ($should_redirect) {
        error_log('[myComix] base_dirs가 설정되지 않았습니다. admin.php에서 설정해주세요.');
        header('Location: admin.php?setup_required=1');
        exit;
    }
}

// 하위 호환성: 첫 번째 폴더를 기본 base_dir로 설정
$base_dir = $base_dirs[0] ?? '';

// 뷰어 설정
$maxview_folder = $app_settings['maxview_folder'] ?? '30';
$maxview_file = $app_settings['maxview_file'] ?? '99999999';
$maxview_folder_mobile = $app_settings['maxview_folder_mobile'] ?? '30';
$maxview_file_mobile = $app_settings['maxview_file_mobile'] ?? '30';
$pages_per_group = $app_settings['pages_per_group'] ?? '10';
$pages_per_group_mobile = $app_settings['pages_per_group_mobile'] ?? '5';
$max_autosave = $app_settings['max_autosave'] ?? '10';
$max_bookmark = $app_settings['max_bookmark'] ?? '10';
$max_favorites = $app_settings['max_favorites'] ?? '50';
$new_badge_hours = $app_settings['new_badge_hours'] ?? '24';
$use_cover = $app_settings['use_cover'] ?? 'y';
$use_listcover = $app_settings['use_listcover'] ?? 'y';

// 외부 도구 경로
$ffmpeg_path = $app_settings['ffmpeg_path'] ?? '';
$ffprobe_path = $app_settings['ffprobe_path'] ?? '';
$vips_path = $app_settings['vips_path'] ?? '';
$unrar_path = $app_settings['unrar_path'] ?? '';
$sevenzip_path = $app_settings['sevenzip_path'] ?? '';

// 폴더 표시 설정
$imgfolder_threshold = $app_settings['imgfolder_threshold'] ?? 5;
$video_folder_as_dir = $app_settings['video_folder_as_dir'] ?? true;

// 다크모드 설정
$darkmode_settings = $app_settings['darkmode'] ?? ['enabled' => true, 'default' => 'light'];

// TXT 뷰어 설정
$txt_viewer_settings = $app_settings['txt_viewer'] ?? ['enabled' => true];

// EPUB 뷰어 설정
$epub_viewer_settings = $app_settings['epub_viewer'] ?? ['enabled' => true];

// ✅ 자동 로그아웃 설정
$auto_logout_settings = $app_settings['auto_logout'] ?? ['enabled' => true, 'timeout' => 600];

// ✅ 모든 기기에서 로그아웃 설정
$logout_all_devices_settings = $app_settings['logout_all_devices'] ?? ['enabled' => false];

// ✅ 목록 폰트 설정 (index.php 폴더/파일 제목)
$list_font_settings = $app_settings['list_font'] ?? [];

// ✅ 프라이버시 실드 설정 (모바일 탭 전환 시 빈 화면)
$privacy_shield_settings = $app_settings['privacy_shield'] ?? [
    'enabled' => true, 
    'pages' => ['index.php', 'viewer.php'],
    'debug' => false
];

// ✅ 캐시 설정 (이미지 압축 품질 등)
$cache_settings = $app_settings['cache_settings'] ?? [];

// ============================================================
// 지원 파일 형식 정의
// ============================================================

// 압축 형식 지원 여부
$archive_extensions = [
    'zip' => true,
    'cbz' => true,
    'rar' => !empty($unrar_path),
    'cbr' => !empty($unrar_path),
    '7z'  => !empty($sevenzip_path),
    'cb7' => !empty($sevenzip_path),
];

// 문서 형식 지원 여부
$document_extensions = [
    'pdf'  => true,
    'txt'  => $txt_viewer_settings['enabled'] ?? true,
    'epub' => $epub_viewer_settings['enabled'] ?? true,
];

// ============================================================
// 파일 확장자 패턴 상수 (정규식용)
// ============================================================
// 
// 📌 새 코드에서는 아래 상수 또는 function.php의 함수를 사용하세요.
//    하드코딩된 패턴 대신 이 상수를 사용하면 확장자 추가 시 일괄 적용됩니다.
// 
// 상수:
//   - ARCHIVE_EXT_PATTERN: 압축 파일 (zip|cbz|rar|cbr|7z|cb7)
//   - DOCUMENT_EXT_PATTERN: 문서 파일 (pdf|txt|epub)
//   - VIEWABLE_EXT_PATTERN: 뷰어 지원 전체 (압축 + 문서)
//   - VIDEO_EXT_PATTERN: 동영상 파일
// 
// 함수 (function.php):
//   - get_archive_extensions_string(): 동적으로 지원 확장자 반환
//   - get_archive_extensions_pattern(): 정규식 패턴 반환 (/\.(...)$/i)
//   - is_archive_file($filename): 압축 파일 여부 확인
//   - is_document_file($filename): 문서 파일 여부 확인
// 
// 사용 예시:
//   if (preg_match(ARCHIVE_EXT_PATTERN, $filename)) { ... }
//   if (preg_match('/\.(' . VIEWABLE_EXT_PATTERN . ')$/i', $filename)) { ... }
// ============================================================

// 압축 파일 확장자 (정규식 문자열)
if (!defined('ARCHIVE_EXT_PATTERN')) {
    define('ARCHIVE_EXT_PATTERN', 'zip|cbz|rar|cbr|7z|cb7');
}

// 문서 파일 확장자 (정규식 문자열)
if (!defined('DOCUMENT_EXT_PATTERN')) {
    define('DOCUMENT_EXT_PATTERN', 'pdf|txt|epub');
}

// 뷰어 지원 전체 확장자 (압축 + 문서)
if (!defined('VIEWABLE_EXT_PATTERN')) {
    define('VIEWABLE_EXT_PATTERN', ARCHIVE_EXT_PATTERN . '|' . DOCUMENT_EXT_PATTERN);
}

// 동영상 파일 확장자
if (!defined('VIDEO_EXT_PATTERN')) {
    define('VIDEO_EXT_PATTERN', 'mp4|webm|mkv|avi|mov|m4v|wmv|flv|m2t|ts|mts|m2ts');
}

// ✅ 이미지 파일 확장자 (2026-01-11 추가)
if (!defined('IMAGE_EXT_PATTERN')) {
    define('IMAGE_EXT_PATTERN', 'jpg|jpeg|png|gif|webp|bmp');
}

// ============================================================
// 확장/플러그인용 유틸리티 함수
// ============================================================
// 
// 📌 이 함수들은 myComix 코어에서 직접 사용되지 않습니다.
//    커스텀 테마, 플러그인, 외부 스크립트에서 활용하기 위해 제공됩니다.
// 
// 사용 가능한 함수:
//   - is_archive_supported($ext)    : 압축 형식 지원 여부 (RAR/7Z 설정 반영)
//   - is_document_supported($ext)   : 문서 형식 지원 여부 (TXT/EPUB 설정 반영)
//   - get_viewer_type($filename)    : 파일에 맞는 뷰어 타입 반환
//   - get_file_icon($filename)      : 파일 확장자별 이모지 아이콘
//   - get_viewable_extensions_pattern() : 지원 파일 정규식 패턴
// 
// 예시 - 커스텀 파일 목록 필터링:
//   $pattern = get_viewable_extensions_pattern();
//   $viewable_files = array_filter($files, fn($f) => preg_match($pattern, $f));
// ============================================================

if (!function_exists('is_archive_supported')) {
/**
 * 압축 형식 지원 여부 확인
 * 
 * @param string $extension 확장자 (예: 'zip', 'rar')
 * @return bool 지원 여부
 * 
 * @note 확장/플러그인용 - 코어에서 미사용
 * 
 * 사용 예시:
 *   if (is_archive_supported('rar')) { ... }
 */
function is_archive_supported($extension) {
    global $archive_extensions;
    $ext = strtolower($extension);
    return isset($archive_extensions[$ext]) && $archive_extensions[$ext];
}
}

if (!function_exists('is_document_supported')) {
/**
 * 문서 형식 지원 여부 확인
 * 
 * @param string $extension 확장자 (예: 'pdf', 'epub')
 * @return bool 지원 여부
 * 
 * 사용 예시:
 *   if (is_document_supported('epub')) { ... }
 */
function is_document_supported($extension) {
    global $document_extensions;
    $ext = strtolower($extension);
    return isset($document_extensions[$ext]) && $document_extensions[$ext];
}
}

// ============================================================
// 경로 검증 함수 안내
// ============================================================
// 
// 다음 함수들은 security_helpers.php에서 제공됩니다:
// - extract_basedir_index($path)
// - prepend_basedir_index($index, $path)  
// - resolve_path_from_basedirs($relative_path, $base_dirs_array)
// - validate_file_path($path, $base_dir)
//
// bootstrap.php를 통해 로드하면 자동으로 사용 가능합니다.
// 폴백 함수 제거됨 (2026-01-10) - bootstrap.php 로드 순서 보장
// ============================================================

if (!function_exists('get_viewer_type')) {
/**
 * 뷰어 타입 결정
 * 
 * @param string $filename 파일명
 * @return string 뷰어 타입 (zip, rar, 7z, pdf, txt, epub, video, image, unknown)
 * 
 * 사용 예시:
 *   $type = get_viewer_type('manga.cbz'); // returns 'zip'
 */
function get_viewer_type($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (in_array($ext, ['zip', 'cbz'])) return 'zip';
    if (in_array($ext, ['rar', 'cbr'])) return 'rar';
    if (in_array($ext, ['7z', 'cb7'])) return '7z';
    if ($ext === 'pdf') return 'pdf';
    if ($ext === 'txt') return 'txt';
    if ($ext === 'epub') return 'epub';
    if (preg_match('/^(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/', $ext)) return 'video';
    if (preg_match('/^(jpg|jpeg|png|gif|webp|bmp)$/', $ext)) return 'image';
    
    return 'unknown';
}
}

if (!function_exists('get_file_icon')) {
/**
 * 파일 아이콘 반환
 * 
 * @param string $filename 파일명
 * @return string 이모지 아이콘
 * 
 * 사용 예시:
 *   echo get_file_icon('manga.cbz'); // outputs '📚'
 */
function get_file_icon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'zip' => '📦', 'cbz' => '📚',
        'rar' => '📦', 'cbr' => '📚',
        '7z'  => '📦', 'cb7' => '📚',
        'pdf' => '📕', 'txt' => '📝', 'epub' => '📖',
        'mp4' => '🎬', 'webm' => '🎬', 'mkv' => '🎬',
    ];
    
    return $icons[$ext] ?? '📄';
}
}

if (!function_exists('get_viewable_extensions_pattern')) {
/**
 * 지원되는 뷰어 파일 패턴
 * 
 * @return string 정규식 패턴
 * 
 * @note 확장/플러그인용 - 코어에서 미사용
 *       코어에서는 각 파일에서 직접 패턴 정의
 * 
 * 사용 예시:
 *   // 커스텀 파일 필터링
 *   $pattern = get_viewable_extensions_pattern();
 *   $viewable = array_filter($files, fn($f) => preg_match($pattern, $f));
 *   
 *   // 파일 확장자 검증
 *   if (preg_match(get_viewable_extensions_pattern(), $filename)) {
 *       // 뷰어로 열 수 있는 파일
 *   }
 */
function get_viewable_extensions_pattern() {
    global $archive_extensions, $document_extensions;
    
    $exts = [];
    
    foreach ($archive_extensions as $ext => $supported) {
        if ($supported) $exts[] = $ext;
    }
    foreach ($document_extensions as $ext => $supported) {
        if ($supported) $exts[] = $ext;
    }
    
    return '/\.(' . implode('|', $exts) . ')$/i';
}
}

// ============================================================
// ArchiveHandler 로드 (RAR/7Z 지원)
// ============================================================
if (file_exists(__DIR__ . '/archive_handler.php')) {
    require_once __DIR__ . '/archive_handler.php';
    
    if (class_exists('ArchiveHandler')) {
        ArchiveHandler::configure(
            $unrar_path ?? '',
            $sevenzip_path ?? ''
        );
    }
}
?>