<?php
/**
 * myComix 함수 모음
 * @version 2.9 - is_image_file(), is_viewable_file() 헬퍼 함수 추가
 * @date 2026-01-11
 * 
 * 변경사항:
 * - get_archive_extensions_string() 함수 추가
 * - n_sort(), cut_title(), is_archive_file()에서 동적 확장자 패턴 사용
 * - 전역 변수($bookmark_file 등) 정의 제거 → bootstrap.php의 get_*_file() 함수 사용
 * - decode_url 함수 내 중복 확장자 처리 제거
 * - deprecated 함수 제거: checknew_value(), is_zip_file(), is_rar_file(), is_7z_file(), format_bytes()
 *   → is_archive_file() 사용 권장
 */

// ============================================================
// PHP 7.x 호환성 Polyfill 함수들
// ============================================================

/**
 * str_ends_with - PHP 8.0 이상에서 도입된 함수
 * 문자열이 특정 문자열로 끝나는지 확인
 */
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') {
            return true;
        }
        $needle_len = strlen($needle);
        return substr($haystack, -$needle_len) === $needle;
    }
}

/**
 * str_starts_with - PHP 8.0 이상에서 도입된 함수
 * 문자열이 특정 문자열로 시작하는지 확인
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

/**
 * str_contains - PHP 8.0 이상에서 도입된 함수
 * 문자열에 특정 문자열이 포함되어 있는지 확인
 */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/**
 * array_is_list - PHP 8.1 이상에서 도입된 함수
 * 배열이 리스트(연속적인 정수 키)인지 확인
 */
if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool {
        if ($array === []) {
            return true;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }
}

// ============================================================
// XSS 방어 함수 - security_helpers.php에서 제공
// h(), hv(), js(), u() 함수 사용
// ============================================================

################################################################################
# 파일리스트 소트
# ✅ config.php의 $archive_extensions와 동기화 (2026-01-11)
################################################################################

function n_sort($file_list) {
    if (empty($file_list)) {
        return [];
    }
    
    $filelist_sort = [];
    
    // ✅ 지원하는 압축 확장자 동적 로드 (config.php와 동기화)
    $archive_ext_pattern = function_exists('get_archive_extensions_string') 
        ? get_archive_extensions_string() 
        : 'zip|cbz|rar|cbr|7z|cb7';
    
    foreach ($file_list as $sort_file) {
        $n_sort = strtolower($sort_file);
        
        // 1단계: "화.확장자" 또는 "권.확장자" 패턴 제거 (예: "01화.zip" → "01")
        $n_sort = preg_replace('/[화권]\.(' . $archive_ext_pattern . ')$/i', '', $n_sort);
        
        // 2단계: 일반 확장자 제거 (예: "file.zip" → "file")
        $n_sort = preg_replace('/\.(' . $archive_ext_pattern . ')$/i', '', $n_sort);
        
        // 3단계: 공백을 밑줄로 변환, _imgfolder 마커 제거
        $n_sort = str_replace([' ', '_imgfolder'], ['_', ''], $n_sort);
        
        $filelist_sort[$sort_file] = $n_sort;
    }
    
    asort($filelist_sort, SORT_NATURAL);
    return array_keys($filelist_sort);
}

################################################################################
# 접근권한이 있는지 확인 및 리모트폴더 여부 반환
# @param $getdir - 상대 경로
# @param $base_dir_override - 특정 base_dir 사용 (다중 폴더용)
################################################################################

function dir_check($getdir, $base_dir_override = null) {
    global $base_dir, $base_dirs;
    
    // base_dir 결정
    $use_base_dir = $base_dir_override ?? $base_dir;
    
    // 다중 폴더 환경에서 인덱스 추출
    if (function_exists('extract_basedir_index')) {
        list($idx, $clean_path) = extract_basedir_index($getdir);
        if ($idx > 0 && isset($base_dirs[$idx])) {
            $use_base_dir = $base_dirs[$idx];
            $getdir = $clean_path;
        }
    }
    
    $rootdir = explode("/", $getdir);
    $getmodefile = $use_base_dir . '/' . (isset($rootdir[1]) ? $rootdir[1] : (isset($rootdir[0]) ? $rootdir[0] : 'default')) . '.json';
    
    if(is_file($getmodefile) == true) {
        $dirmode_arr = json_decode(file_get_contents($getmodefile), true) ?? [];
        if(($dirmode_arr[$_SESSION['user_group']] ?? null) !== 1) {
            echo __('err_permission_redirect') . "<br>";
            echo("<meta http-equiv=\"refresh\" content=\"3; url=index.php\">"); 
            exit();
        }
    }
    
    // 권한 파일 없으면 접근 허용
    return "n";
}

################################################################################
# 긴 제목을 줄여줌
# ✅ 리팩토링: 가독성 개선 및 로직 단순화 (2026-01-11)
################################################################################

function cut_title($title) {
    // ✅ 1단계: 현재 디렉토리명 추출 및 정규화
    $nowdir = '';
    // ✅ decode_file_param() 사용으로 이중 인코딩 대응 통일
    $dir_param = decode_file_param($_GET['dir'] ?? '');
    if (!empty($dir_param)) {
        $nowdir_arr = explode("/", $dir_param);
        $nowdir = strtolower(end($nowdir_arr) ?: '');
        // 디렉토리명에서 괄호/대괄호 내용 제거
        $nowdir = preg_replace('/[\(\[][^\)\]]*[\)\]]/', '', $nowdir);
        $nowdir = trim($nowdir);
    }
    
    // ✅ 2단계: 제목 기본 정규화
    $title = strtolower($title);
    
    // ✅ 압축 파일 확장자 제거 (config.php와 동기화)
    $archive_ext = get_archive_extensions_string();
    $title = preg_replace('/\.(' . $archive_ext . ')$/i', '', $title);
    
    // URL 인코딩 복원 및 특수 표현 치환
    $title = str_replace(['%3f', '(decensored)'], ['?', '-무수정'], $title);
    
    // ✅ 3단계: 구분자 이후 텍스트만 추출 (앞부분 제거)
    // 일반적으로 "작가명 | 제목" 형식에서 제목만 추출
    static $dividers = ['|', '｜', ' l ', '│', '%7c'];
    foreach ($dividers as $div) {
        $pos = strpos($title, $div);
        if ($pos !== false) {
            $title = substr($title, $pos + strlen($div));
            break;  // 첫 번째 구분자에서만 처리
        }
    }
    
    // ✅ 4단계: 대괄호 내용 제거 ([작가], [번역] 등)
    $title = preg_replace('/\[[^\]]*\]/', '', $title);
    
    // ✅ 5단계: 현재 디렉토리명 기준 정리
    if (!empty($nowdir)) {
        // 디렉토리명 이후 부분만 추출
        $pos = strpos($title, $nowdir);
        if ($pos !== false) {
            $title = substr($title, $pos + strlen($nowdir));
        }
    }
    
    // ✅ 6단계: 긴 제목에서 소괄호 내용 제거
    if (mb_strlen($title, 'UTF-8') > 10) {
        $title = preg_replace('/\([^)]*\)/', '', $title);
    }
    
    return trim($title);
}

################################################################################
# Return encode/decode URL for Special character
# ✅ 중복 처리 정리 (2026-01-10)
################################################################################

function encode_url($filename) {
    $filename = str_replace("+", "{pl}", $filename);
    $filename = str_replace(".", "{dt}", $filename);
    $filename = str_replace("%", "{pc}", $filename);
    $filename = urlencode($filename);
    return $filename;
}

function decode_url($str) {
    $decoded = urldecode($str);
    
    // ✅ 플레이스홀더를 원래 문자로 복원 (일괄 처리)
    $decoded = str_replace(
        ["{pl}", "{dt}", "{pc}"],
        ["+",    ".",    "%"],
        $decoded
    );

    return $decoded;
}

################################################################################
# Return MIME Content type
################################################################################

function mime_type($filename) {

    $mime_types = array(

        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',

        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'webp' => 'image/webp',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',

        // archives
        'zip' => 'application/zip',
        'cbz' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'cbr' => 'application/x-rar-compressed',
        '7z'  => 'application/x-7z-compressed',
        'cb7' => 'application/x-7z-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',

        // audio/video
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',

        // adobe
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',

        // ms office
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',

        // open office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    );

    // ✅ PHP 8 호환: array_pop()은 변수만 참조로 받음
    // ✅ 확장자만 전달된 경우도 처리 (예: mime_type("jpg"))
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // 확장자가 없으면 파일명 자체를 확장자로 간주 (예: "jpg" → "jpg")
    if (empty($ext)) {
        $ext = strtolower($filename);
    }
    
    if (array_key_exists($ext, $mime_types)) {
        return $mime_types[$ext];
    }
    elseif (function_exists('finfo_open') && is_file($filename)) {
        // ✅ 실제 파일인 경우에만 finfo_file() 호출
        $finfo = finfo_open(FILEINFO_MIME);
        $mimetype = @finfo_file($finfo, $filename);
        finfo_close($finfo);
        return $mimetype ?: 'application/octet-stream';
    }
    else {
        return 'application/octet-stream';
    }
}

################################################################################
# 동영상 관련 함수
# ✅ VIDEO_EXT_PATTERN 상수 사용으로 config.php와 동기화 (2026-01-11)
################################################################################

/**
 * 동영상 파일인지 확인
 * ✅ config.php의 VIDEO_EXT_PATTERN 상수 사용
 */
function is_video_file($path) {
    // VIDEO_EXT_PATTERN이 정의되어 있으면 사용, 아니면 기본 패턴
    $pattern = defined('VIDEO_EXT_PATTERN') ? VIDEO_EXT_PATTERN : 'mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv';
    return preg_match('/\.(' . $pattern . ')$/i', $path);
}

/**
 * 브라우저에서 직접 재생 가능한 동영상인지 확인
 * mp4, webm, m4v, mov(일부) 등이 대부분의 브라우저에서 지원됨
 * 
 * @param string $path 파일 경로 또는 파일명
 * @return bool 브라우저 재생 가능 여부
 */
function is_browser_playable_video($path) {
    // 브라우저 재생 가능한 형식은 제한적이므로 하드코딩 유지
    return preg_match('/\.(mp4|webm|m4v|mov|ogg|ogv)$/i', $path);
}

/**
 * 동영상 파일 확장자 패턴 반환
 * ✅ config.php의 VIDEO_EXT_PATTERN 상수 사용
 */
function get_video_extensions_pattern() {
    $pattern = defined('VIDEO_EXT_PATTERN') ? VIDEO_EXT_PATTERN : 'mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv';
    return '/\.(' . $pattern . ')$/i';
}

/**
 * 동영상 썸네일 생성
 */
function generate_video_thumbnail($video_path, $ffmpeg_path = '', $time_offset = 5) {
    if (empty($ffmpeg_path) || !is_file($video_path)) {
        return false;
    }
    
    $thumb_path = $video_path . '.video_thumb.jpg';
    
    if (file_exists($thumb_path)) {
        return $thumb_path;
    }
    
    $cmd = sprintf(
        '%s -i %s -ss %d -vframes 1 -q:v 2 -y %s 2>&1',
        escape_shell_arg_safe($ffmpeg_path),
        escape_shell_arg_safe($video_path),
        $time_offset,
        escape_shell_arg_safe($thumb_path)
    );
    
    @exec($cmd, $output, $return_code);
    
    if ($return_code === 0 && file_exists($thumb_path)) {
        return $thumb_path;
    }
    
    if ($time_offset > 1) {
        $cmd = sprintf(
            '%s -i %s -ss 1 -vframes 1 -q:v 2 -y %s 2>&1',
            escape_shell_arg_safe($ffmpeg_path),
            escape_shell_arg_safe($video_path),
            escape_shell_arg_safe($thumb_path)
        );
        
        @exec($cmd, $output, $return_code);
        
        if ($return_code === 0 && file_exists($thumb_path)) {
            return $thumb_path;
        }
    }
    
    return false;
}

/**
 * 동영상 썸네일 경로 반환
 */
function get_video_thumbnail($video_path) {
    $thumb_path = $video_path . '.video_thumb.jpg';
    return file_exists($thumb_path) ? $thumb_path : false;
}

################################################################################
# 압축 파일 확장자 체크 (ZIP/RAR/7Z)
################################################################################

/**
 * 압축 파일인지 확인 (이미지 뷰어용)
 * ✅ config.php의 $archive_extensions와 동기화
 */
function is_archive_file($filename) {
    $pattern = get_archive_extensions_pattern();
    return preg_match($pattern, $filename);
}

/**
 * 문서 파일인지 확인
 * ✅ DOCUMENT_EXT_PATTERN 상수 사용 (2026-01-11)
 */
function is_document_file($filename) {
    $pattern = defined('DOCUMENT_EXT_PATTERN') ? DOCUMENT_EXT_PATTERN : 'pdf|txt|epub';
    return preg_match('/\.(' . $pattern . ')$/i', $filename);
}

/**
 * 이미지 파일인지 확인
 * ✅ IMAGE_EXT_PATTERN 상수 사용 (2026-01-11 추가)
 * 
 * @param string $filename 파일명 또는 경로
 * @return bool 이미지 파일 여부
 */
function is_image_file($filename) {
    $pattern = defined('IMAGE_EXT_PATTERN') ? IMAGE_EXT_PATTERN : 'jpg|jpeg|png|gif|webp|bmp';
    return preg_match('/\.(' . $pattern . ')$/i', $filename);
}

/**
 * 이미지 파일 확장자 패턴 반환
 * ✅ IMAGE_EXT_PATTERN 상수 사용 (2026-01-11 추가)
 * 
 * @return string 정규식 패턴
 */
function get_image_extensions_pattern() {
    $pattern = defined('IMAGE_EXT_PATTERN') ? IMAGE_EXT_PATTERN : 'jpg|jpeg|png|gif|webp|bmp';
    return '/\.(' . $pattern . ')$/i';
}

/**
 * 뷰어 지원 파일인지 확인 (압축 + 문서)
 * ✅ VIEWABLE_EXT_PATTERN 상수 사용 (2026-01-11 추가)
 * 
 * @param string $filename 파일명 또는 경로
 * @return bool 뷰어 지원 파일 여부
 */
function is_viewable_file($filename) {
    $pattern = defined('VIEWABLE_EXT_PATTERN') ? VIEWABLE_EXT_PATTERN : 'zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub';
    return preg_match('/\.(' . $pattern . ')$/i', $filename);
}

/**
 * 지원되는 압축 파일 확장자 문자열 반환 (정규식 내부용)
 * config.php의 $archive_extensions와 동기화됨
 * 
 * @return string 확장자 문자열 (예: "zip|cbz|rar|cbr|7z|cb7")
 */
function get_archive_extensions_string() {
    global $archive_extensions;
    
    if (isset($archive_extensions) && is_array($archive_extensions)) {
        $exts = [];
        foreach ($archive_extensions as $ext => $supported) {
            if ($supported) $exts[] = $ext;
        }
        if (!empty($exts)) {
            return implode('|', $exts);
        }
    }
    
    // 기본: ZIP/CBZ만
    return 'zip|cbz';
}

/**
 * 지원되는 압축 파일 확장자 패턴
 */
function get_archive_extensions_pattern() {
    return '/\.(' . get_archive_extensions_string() . ')$/i';
}

################################################################################
# 공통 브랜딩 로드 함수 (통합)
################################################################################

/**
 * 브랜딩 설정 로드
 * 모든 페이지에서 공통으로 사용
 * 
 * @param array $extra_defaults 추가 기본값 (페이지별 필요시)
 * @return array 브랜딩 설정
 */
if (!function_exists('load_branding')) {
    function load_branding($extra_defaults = []) {
        static $cache = null;
        
        // 캐시된 값이 있으면 반환
        if ($cache !== null) {
            return array_merge($cache, $extra_defaults);
        }
        
        $file = __DIR__ . '/src/branding.json';
        $base_defaults = [
            'logo_type' => 'text',
            'logo_text' => '마이코믹스',
            'logo_image' => '',
            'page_title' => 'myComix',
            'subtitle' => '나만의 만화 서재',
            'login_button' => '로그인',
            'copyright' => 'myComix © 2021',
            'admin_title' => 'myComix - 관리자'
        ];
        
        if (!file_exists($file)) {
            $cache = $base_defaults;
            return array_merge($cache, $extra_defaults);
        }
        
        $data = json_decode(file_get_contents($file), true);
        $cache = array_merge($base_defaults, $data ?: []);
        
        return array_merge($cache, $extra_defaults);
    }
}

// ============================================================
// 사용자 관리 함수 (users.json)
// ============================================================

/**
 * 사용자 파일 경로 반환
 * @return string
 */
if (!function_exists('get_users_file_path')) {
    function get_users_file_path() {
        return __DIR__ . '/src/users.json';
    }
}

/**
 * 레거시 user.php 경로 반환
 * @return string
 */
if (!function_exists('get_legacy_users_file_path')) {
    function get_legacy_users_file_path() {
        return __DIR__ . '/src/user.php';
    }
}

/**
 * 사용자 목록 로드 (레거시 user.php에서 자동 마이그레이션)
 * @return array
 */
if (!function_exists('load_users')) {
    function load_users() {
        $new_file = get_users_file_path();
        $legacy_file = get_legacy_users_file_path();
        
        // 1. 새 파일(users.json)이 있으면 그걸 사용 (파일 잠금)
        if (is_file($new_file)) {
            $fp = fopen($new_file, 'r');
            if (!$fp) return [];
            
            $data = [];
            if (flock($fp, LOCK_SH)) {
                $content = stream_get_contents($fp);
                $data = json_decode($content, true);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            return is_array($data) ? $data : [];
        }
        
        // 2. 레거시 파일(user.php)이 있으면 마이그레이션
        if (is_file($legacy_file)) {
            $content = file_get_contents($legacy_file);
            // PHP 태그 제거 (chr 사용으로 파서 혼동 방지)
            $open_tag = chr(60) . chr(63) . 'php ';  // 여는 태그
            $close_tag = ' ' . chr(63) . chr(62);    // 닫는 태그
            $json_str = trim(str_replace([$open_tag, $close_tag], '', $content));
            $data = json_decode($json_str, true);
            
            if (is_array($data) && !empty($data)) {
                // 새 형식으로 저장
                save_users($data);
                // 레거시 파일 백업 후 삭제
                @rename($legacy_file, $legacy_file . '.bak');
                return $data;
            }
        }
        
        return [];
    }
}

/**
 * 사용자 목록 저장 (순수 JSON, 파일 잠금)
 * @param array $users
 * @return bool
 */
if (!function_exists('save_users')) {
    function save_users($users) {
        $file = get_users_file_path();
        $dir = dirname($file);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 파일 잠금으로 동시 접근 보호
        $fp = fopen($file, 'c+');
        if (!$fp) return false;
        
        $result = false;
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            $json = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $result = fwrite($fp, $json) !== false;
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $result;
    }
}

/**
 * 사용자 파일 존재 여부 확인 (레거시 포함)
 * @return bool
 */
if (!function_exists('users_file_exists')) {
    function users_file_exists() {
        return is_file(get_users_file_path()) || is_file(get_legacy_users_file_path());
    }
}

/**
 * JSON 파일 읽기 (파일 잠금)
 * @param string $file
 * @return array
 */
if (!function_exists('load_json_with_lock')) {
    function load_json_with_lock($file) {
        if (!is_file($file)) return [];
        
        $fp = fopen($file, 'r');
        if (!$fp) return [];
        
        $data = [];
        if (flock($fp, LOCK_SH)) {
            $content = stream_get_contents($fp);
            $data = json_decode($content, true);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        
        return is_array($data) ? $data : [];
    }
}

/**
 * JSON 파일 저장 (파일 잠금)
 * @param string $file
 * @param array $data
 * @return bool
 */
if (!function_exists('save_json_with_lock')) {
    function save_json_with_lock($file, $data) {
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        $fp = fopen($file, 'c+');
        if (!$fp) return false;
        
        $result = false;
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            $result = fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false;
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $result;
    }
}

// ============================================================
// 폴더 권한 관리 함수 (admin.php, index.php 통합)
// ============================================================

/**
 * 폴더 권한 파일 로드 (파일 잠금)
 * @param string $file 권한 파일 경로
 * @return array 권한 데이터
 */
if (!function_exists('load_permissions')) {
    function load_permissions($file) {
        if (!file_exists($file)) return [];
        return load_json_with_lock($file);
    }
}

/**
 * 폴더 권한 파일 저장 (파일 잠금)
 * @param string $file 권한 파일 경로
 * @param array $data 권한 데이터
 * @return bool 저장 성공 여부
 */
if (!function_exists('save_permissions')) {
    function save_permissions($file, $data) {
        return save_json_with_lock($file, $data);
    }
}

// ============================================================
// 📌 확장자 패턴 마이그레이션 가이드 (2026-01-11)
// ============================================================
// 
// 하드코딩된 패턴을 헬퍼 함수로 교체하면 유지보수성이 향상됩니다.
// 
// ▶ 이미지 파일 체크:
//   변경 전: preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)
//   변경 후: is_image_file($filename)
// 
// ▶ 동영상 파일 체크:
//   변경 전: preg_match('/\.(mp4|webm|mkv|avi|mov|...)$/i', $filename)
//   변경 후: is_video_file($filename)
// 
// ▶ 뷰어 지원 파일 체크 (압축+문서):
//   변경 전: preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub)$/i', $filename)
//   변경 후: is_viewable_file($filename)
// 
// ▶ 압축 파일만 체크:
//   변경 전: preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $filename)
//   변경 후: is_archive_file($filename)
// 
// ▶ 문서 파일만 체크:
//   변경 전: preg_match('/\.(pdf|txt|epub)$/i', $filename)
//   변경 후: is_document_file($filename)
// 
// ▶ 정규식 패턴이 필요한 경우:
//   get_image_extensions_pattern()    → '/\.(jpg|jpeg|png|...)$/i'
//   get_video_extensions_pattern()    → '/\.(mp4|webm|...)$/i'
//   get_archive_extensions_pattern()  → '/\.(zip|cbz|...)$/i'
// 
// 참고: 상수는 config.php에 정의됨
//   IMAGE_EXT_PATTERN, VIDEO_EXT_PATTERN, ARCHIVE_EXT_PATTERN,
//   DOCUMENT_EXT_PATTERN, VIEWABLE_EXT_PATTERN
// ============================================================

// ============================================================
// ✅ 활동 로그 기록 함수 (2026-01-20 통합)
// - index.php, viewer.php에서 중복 정의되어 있던 것을 통합
// ============================================================
if (!function_exists('log_user_activity')) {
    /**
     * 사용자 활동 로그 기록
     * 
     * @param string $action 활동 유형 (예: '로그인', '다운로드', '해킹시도')
     * @param string $detail 상세 내용 (최대 500자)
     * @param string|null $user_id 사용자 ID (null이면 세션에서 가져옴)
     */
    function log_user_activity($action, $detail = '', $user_id = null) {
        if ($user_id === null) {
            $user_id = $_SESSION['user_id'] ?? 'guest';
        }
        
        // guest는 기록하지 않음 (로그인한 사용자만)
        if ($user_id === 'guest') return;
        
        $log_file = __DIR__ . '/src/activity_log.json';
        
        $fp = @fopen($log_file, 'c+');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                $content = stream_get_contents($fp);
                $logs = json_decode($content, true) ?? [];
                
                // 최대 5000개 유지
                if (count($logs) >= 5000) {
                    $logs = array_slice($logs, -4000);
                }
                
                // 한국 시간 사용
                $kst = new DateTime('now', new DateTimeZone('Asia/Seoul'));
                
                $logs[] = [
                    'datetime' => $kst->format('Y-m-d H:i:s'),
                    'user_id' => preg_replace('/[^a-zA-Z0-9_]/', '', $user_id),
                    'action' => $action,
                    'detail' => mb_substr($detail, 0, 500),
                    'ip' => filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown'
                ];
                
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
}

// ============================================================
// JSON 파일 안전 파싱 함수
// ============================================================

/**
 * 손상된 JSON 파일 복구 파싱
 * 
 * 중복 저장으로 인해 }{가 연결된 경우 첫 번째 유효한 JSON만 추출
 * 
 * @param string $json_content JSON 문자열
 * @param bool $assoc true면 연관배열, false면 객체 반환
 * @return mixed 파싱된 데이터 또는 null
 * @version 1.0
 * @date 2026-01-29
 */
function safe_json_decode($json_content, $assoc = true) {
    if (empty($json_content)) {
        return null;
    }
    
    // 먼저 일반적인 파싱 시도
    $data = @json_decode($json_content, $assoc);
    
    // 파싱 성공하면 바로 반환
    if ($data !== null || json_last_error() === JSON_ERROR_NONE) {
        return $data;
    }
    
    // JSON 파싱 실패 시 복구 시도
    // 파일이 중복 저장되어 }{가 연결된 경우 첫 번째 JSON만 추출
    $brace_count = 0;
    $json_end = -1;
    $len = strlen($json_content);
    
    for ($i = 0; $i < $len; $i++) {
        $char = $json_content[$i];
        if ($char === '{') {
            $brace_count++;
        } elseif ($char === '}') {
            $brace_count--;
            if ($brace_count === 0) {
                $json_end = $i;
                break;
            }
        }
    }
    
    if ($json_end > 0) {
        $first_json = substr($json_content, 0, $json_end + 1);
        return @json_decode($first_json, $assoc);
    }
    
    return null;
}

/**
 * JSON 파일 안전 읽기 (손상된 파일 자동 복구)
 * 
 * @param string $filepath JSON 파일 경로
 * @param bool $auto_repair true면 손상된 파일 자동 수정
 * @return mixed 파싱된 데이터 또는 null
 * @version 1.0
 * @date 2026-01-29
 */
function safe_json_read($filepath, $auto_repair = false) {
    if (!is_file($filepath)) {
        return null;
    }
    
    $json_content = @file_get_contents($filepath);
    if ($json_content === false) {
        return null;
    }
    
    // 일반적인 파싱 시도
    $data = @json_decode($json_content, true);
    
    // 파싱 성공하면 바로 반환
    if ($data !== null) {
        return $data;
    }
    
    // 복구 시도
    $data = safe_json_decode($json_content, true);
    
    // 복구 성공 시 파일 수정 (선택적)
    if ($data !== null && $auto_repair) {
        $repaired_json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($repaired_json !== false) {
            @file_put_contents($filepath, $repaired_json, LOCK_EX);
        }
    }
    
    return $data;
}

/**
 * JSON 파일 안전 저장 (저장 후 검증)
 * 
 * 파일 저장 후 다시 읽어서 JSON이 유효한지 검증
 * 손상 방지를 위한 안전장치
 * 
 * @param string $filepath 저장할 파일 경로
 * @param array $data 저장할 데이터
 * @param int $max_retry 재시도 횟수 (기본 2)
 * @return bool 성공 여부
 * @version 1.0
 * @date 2026-01-29
 */
function safe_json_write($filepath, $data, $max_retry = 2) {
    if (empty($filepath) || !is_array($data)) {
        return false;
    }
    
    $json_content = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json_content === false) {
        return false;
    }
    
    for ($retry = 0; $retry <= $max_retry; $retry++) {
        // 임시 파일에 먼저 저장
        $temp_file = $filepath . '.tmp.' . getmypid();
        $result = @file_put_contents($temp_file, $json_content, LOCK_EX);
        
        if ($result === false) {
            @unlink($temp_file);
            continue;
        }
        
        // 저장된 내용 검증
        $verify = @file_get_contents($temp_file);
        if ($verify === false || $verify !== $json_content) {
            @unlink($temp_file);
            continue;
        }
        
        // JSON 파싱 검증
        $verify_data = @json_decode($verify, true);
        if ($verify_data === null) {
            @unlink($temp_file);
            continue;
        }
        
        // 검증 통과 - 원본 파일로 이동
        if (@rename($temp_file, $filepath)) {
            return true;
        }
        
        // rename 실패 시 직접 복사 후 삭제
        if (@copy($temp_file, $filepath)) {
            @unlink($temp_file);
            return true;
        }
        
        @unlink($temp_file);
    }
    
    return false;
}

/**
 * 삭제/탈퇴된 사용자 아이디인지 확인
 * @param string $user_id 확인할 사용자 아이디
 * @return bool 삭제/탈퇴 목록에 있으면 true
 */
function is_deleted_user_id(string $user_id): bool {
    $deleted_file = __DIR__ . '/src/deleted_users.json';
    if (!file_exists($deleted_file)) {
        return false;
    }
    $deleted_data = json_decode(file_get_contents($deleted_file), true) ?? [];
    foreach ($deleted_data as $entry) {
        if (isset($entry['user_id']) && $entry['user_id'] === $user_id) {
            return true;
        }
    }
    return false;
}

?>