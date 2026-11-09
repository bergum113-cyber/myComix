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

/**
 * ★ (2026-10-30) 폴더 권한 판단(출력·종료 없이 true/false) — dir_check 와 스트리밍·썸네일 요청이 같은 규칙을 씀
 *   ① 최상위 폴더의 {폴더}.json(옛 방식)이 있으면 그룹 값이 1 이어야 함
 *   ② src/folder_permissions.json 에 최상위 폴더가 있으면 그룹 값이 1 이어야 함(관리자 화면이 저장하는 곳 — 전에는 dir_check 가 이걸 안 봐서 주소로 바로 들어가면 막히지 않았음)
 *   경로 앞 '/' 유무와 상관없이 첫 조각을 최상위 폴더로 봄(전에는 '/' 없이 넣으면 검사를 건너뜀)
 */
function mc_path_allowed($getdir, $base_dir_override = null) {
    global $base_dir, $base_dirs;
    static $perm = null;
    $use_base_dir = $base_dir_override ?? $base_dir;
    $getdir = (string)$getdir;
    if (function_exists('extract_basedir_index')) {
        list($idx, $clean_path) = extract_basedir_index($getdir);
        if ($idx > 0 && isset($base_dirs[$idx])) {
            $use_base_dir = $base_dirs[$idx];
            $getdir = $clean_path;
        }
    }
    $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $getdir)), function ($p) { return $p !== '' && $p !== '.'; }));
    if (!$parts) return true; // 최상위(목록 화면) — 폴더별 숨김은 목록에서 처리
    $root = $parts[0];
    $group = (string)($_SESSION['user_group'] ?? '');
    // 권한은 최상위 '폴더' 단위 — 최상위에 바로 놓인 파일(abc.rar 등)은 대상 아님
    //   (abc.rar 의 메타 캐시가 abc.rar.json 이라 옛 권한 파일로 잘못 읽혀 막히던 것 방지)
    if (!is_dir(rtrim((string)$use_base_dir, '/\\') . '/' . $root)) return true;
    // Windows 는 이름 끝 점·공백을 버리고(Secret. → Secret) 대소문자를 가리지 않음 — 입력한 이름(끝 점·공백 제거)과 실제 이름(realpath)
    //   두 가지로 모두 확인해 하나라도 막혀 있으면 막음(정션·링크면 realpath 가 대상 폴더 이름이라 링크 이름도 함께 봐야 함)
    $__bd = rtrim((string)$use_base_dir, '/\\');
    $__names = [rtrim($root, '. ')];
    $__rp = realpath($__bd . '/' . $root);
    if ($__rp !== false) $__names[] = basename(str_replace('\\', '/', $__rp));
    // ★ (2026-11-09 11:41) 경로 중간의 링크·정션도 봄 — 허용 폴더 안(X/lnk → 막힌 폴더)에 둔 링크를 거치면 막힌 최상위 폴더가 목록·썸네일·뷰어로 그대로 열렸음
    //   경로 전체의 실제 위치가 이 콘텐츠 폴더 안이면 그 실제 위치의 첫 조각(최상위 폴더)도 같이 확인. 경로가 없거나 콘텐츠 폴더 밖이면 예전과 같음
    if (count($parts) > 1) {
        $__bdr = realpath($__bd);
        $__fr = realpath($__bd . '/' . implode('/', $parts));
        // ★ (2026-11-09 14:09) 경로 끝이 없는 이름이면(A/lnk/그림이름.txt — thumb.php 가 같은 이름 그림을 내줌) 가장 가까운 '있는' 위 폴더의 실제 위치로 판정 — 전에는 false 라 링크 확인을 건너뜀
        if ($__fr === false) { $__pp = $parts; while (count($__pp) > 1 && $__fr === false) { array_pop($__pp); $__fr = realpath($__bd . '/' . implode('/', $__pp)); } }
        if ($__bdr !== false && $__fr !== false) {
            $__bdn = rtrim(str_replace('\\', '/', $__bdr), '/') . '/';
            $__frn = str_replace('\\', '/', $__fr);
            $__cmp = (DIRECTORY_SEPARATOR === '\\') ? (stripos($__frn, $__bdn) === 0) : (strpos($__frn, $__bdn) === 0); // Windows 는 대소문자 무시
            if ($__cmp) {
                $__seg = explode('/', substr($__frn, strlen($__bdn)))[0];
                if ($__seg !== '') $__names[] = $__seg;
            }
        }
    }
    $__names = array_values(array_unique(array_filter($__names, 'strlen')));
    if ($perm === null) {
        $pf = __DIR__ . '/src/folder_permissions.json';
        $raw = (is_file($pf) && function_exists('load_permissions')) ? (load_permissions($pf) ?: []) : [];
        $perm = [];
        // Windows 는 폴더 이름 대소문자를 구분하지 않으므로(/secret 으로도 열림) 소문자 키로 찾음
        if (is_array($raw)) foreach ($raw as $k => $v) $perm[mb_strtolower((string)$k, 'UTF-8')] = $v;
    }
    foreach ($__names as $__n) {
        $modefile = $__bd . '/' . $__n . '.json';
        if (is_file($modefile)) {
            $arr = json_decode((string)@file_get_contents($modefile), true);
            // 옛 권한 파일인지(그룹 키가 있는지) 확인 — 같은 이름 zip 의 메타 캐시(Foo.zip → Foo.json)를 권한 파일로 잘못 읽지 않게
            $is_perm = is_array($arr) && (isset($arr['admin']) || isset($arr['group1']) || isset($arr['group2']) || isset($arr['group3']) || isset($arr['group4']));
            if ($is_perm && (($arr[$group] ?? null) !== 1)) return false;
            if (!is_array($arr) && trim((string)@file_get_contents($modefile)) !== '') return false; // 깨진 권한 파일은 전처럼 막음
        }
        $rk = mb_strtolower((string)$__n, 'UTF-8');
        if (isset($perm[$rk]) && is_array($perm[$rk]) && (($perm[$rk][$group] ?? 0) !== 1)) return false;
    }
    return true;
}

function dir_check($getdir, $base_dir_override = null) {
    if (!mc_path_allowed($getdir, $base_dir_override)) {
        echo __('err_permission_redirect') . "<br>";
        echo("<meta http-equiv=\"refresh\" content=\"3; url=index.php\">"); 
        exit();
    }
    return "n";
}

/**
 * ★ (2026-10-30) 요청의 file 파라미터 폴더 권한 확인 — 권한이 없으면 403 으로 끝냄
 *   txt/epub/office/hwp 뷰어·thumb.php·warmup.php 의 모든 요청(API 포함) 앞에서 호출
 * @param bool $json true 면 JSON 오류, false 면 dir_check 와 같은 안내 화면
 */
function mc_file_param_guard($json = false) {
    $path = '';
    foreach ([$_GET['file'] ?? null, $_POST['file'] ?? null] as $raw) { // GET·POST 둘 다(API 가 POST 값을 쓰는 경우)
        if (!is_string($raw) || $raw === '') continue;
        $p = decode_file_param($raw);
        if ($p !== '' && !mc_path_allowed($p)) { $path = $p; break; }
    }
    if ($path === '') return;
    if (!$json) dir_check($path); // 안내 후 종료
    http_response_code(403);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => __('err_access_denied')]);
    exit;
}

################################################################################
# 긴 제목을 줄여줌
# ✅ 리팩토링: 가독성 개선 및 로직 단순화 (2026-01-11)
################################################################################

/**
 * 북마크 드롭다운(자동저장·북마크·즐겨찾기) 전용 제목 표시.
 *
 * cut_title()은 파일 그리드용으로 현재 폴더명/구분자/괄호를 잘라내는데,
 * 드롭다운은 여러 폴더 항목이 섞여 있어 "어느 만화인지 식별"이 목적이므로
 * 잘라내지 않고 실제 파일명(확장자만 제거)을 그대로 보여준다.
 * 출력은 h()로 이스케이프하여 파일명 기반 XSS를 차단한다.
 *
 * @param string $filename 파일명(경로의 마지막 조각)
 * @return string HTML 이스케이프된 표시용 제목
 */
function mark_display_title($filename) {
    // 압축/문서 확장자만 제거 (config.php의 압축 확장자 + 문서 확장자)
    $archive_ext = get_archive_extensions_string();
    $name = preg_replace('/\.(' . $archive_ext . '|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', '', (string)$filename);
    return h($name);
}

function cut_title($title, $skip_dir_strip = false) {
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
    
    // ✅ 5단계: 현재 디렉토리명 기준 정리 (북마크 드롭다운처럼 여러 폴더 항목이 섞인 경우엔 건너뜀)
    if (!$skip_dir_strip && !empty($nowdir)) {
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
 * ★ (2026-10-30) 파일 하나에 딸린 캐시(메타 json·이미지 목록·썸네일·사용자별 압축 해제 캐시) 정리
 *   같은 이름으로 덮어써 올릴 때 옛 썸네일·쪽수·페이지 그림이 새 파일에 붙어 보이던 문제 방지
 */
function mc_clear_file_caches($path) {
    if (!is_string($path) || $path === '') return;
    $list = [
        $path . '.video_thumb.jpg',
        function_exists('get_cache_json_path') ? get_cache_json_path($path) : null,
        $path . '.image_files.json',
        $path . '.video_files.json',
        $path . '.thumb.jpg',
        $path . '.thumb.webp',
        $path . '.pdf_thumb.jpg',
        $path . '.epub_thumb.jpg',
    ];
    foreach ($list as $f) {
        if ($f && is_file($f)) @unlink($f);
    }
    if (function_exists('cacheKeyFromPath') && is_file($path)) {
        $key = cacheKeyFromPath($path);
        foreach ((glob(__DIR__ . '/cache/*/' . $key, GLOB_ONLYDIR) ?: []) as $d) {
            foreach ((@scandir($d) ?: []) as $n) {
                if ($n === '.' || $n === '..') continue;
                if (is_file($d . '/' . $n)) @unlink($d . '/' . $n);
            }
            @rmdir($d);
        }
    }
}

/**
 * 압축/문서 파일의 .json 캐시 경로를 생성한다.
 *
 * ✅ [충돌 방지] rar/cbr/7z/cb7 은 같은 이름의 다른 압축(예: 만화.zip)과
 *    .json 캐시가 겹치지 않도록 "확장자를 유지한 채" .json 을 덧붙인다.
 *    예) 만화.rar  → 만화.rar.json
 *        만화.7z   → 만화.7z.json
 *    zip/cbz/pdf/문서 등 기존 동작은 그대로(확장자 제거 후 .json)로 유지하여
 *    기존 ZIP 캐시 호환성을 깨지 않는다.
 *        예) 만화.zip → 만화.json  (기존과 동일)
 *
 * @param string $filepath 원본 파일 경로
 * @return string .json 캐시 경로
 */
/** ★ (2026-11-02 10:01) <style> 안 작은따옴표 문자열(url('…')·font-family:'…')용 — HTML 이스케이프(h)는 <style> 안에서 안 풀려 & 가 &amp; 로 남았음 */
if (!function_exists('mc_css_str')) {
function mc_css_str($u) {
    return (string)preg_replace('/[\x00-\x1F\x7F]/', '', str_replace(['\\', "'", '"', '<', '>'], ['\\\\', "\\'", '\\22 ', '\\3c ', '\\3e '], (string)$u));
}
}

/**
 * ★ (2026-11-02 09:56) GD 로 그림 열기 — 남은 메모리로 못 풀 만큼 큰 그림이면 열지 않고 false(실패와 같게)
 *   (아주 큰 첫 그림 하나로 목록·캐시 만들기 요청이 메모리 한도를 넘어 500 으로 죽던 것. 크기를 모르면 전처럼 열어 봄)
 */
if (!function_exists('mc_gd_from_string')) {
function mc_gd_from_string($data) {
    if (!is_string($data) || $data === '') return false;
    $info = @getimagesizefromstring($data);
    if ($info && !empty($info[0]) && !empty($info[1])) {
        $ml = trim((string)ini_get('memory_limit'));
        if ($ml !== '' && $ml !== '-1') {
            $mb = (int)$ml; $u = strtolower(substr($ml, -1));
            if ($u === 'g') $mb *= 1073741824; elseif ($u === 'm') $mb *= 1048576; elseif ($u === 'k') $mb *= 1024;
            if ($mb > 0 && memory_get_usage(true) + (int)$info[0] * (int)$info[1] * 5 + strlen($data) > $mb) return false;
        }
    }
    return @imagecreatefromstring($data);
}
}

/** ★ (2026-11-02 11:38) 검색 색인에서 뺄 이름 — 숨김·캐시 파일(.으로 시작·.json)·폴더 표지·영상 썸네일 */
if (!function_exists('mc_index_skip_name')) {
function mc_index_skip_name($name) {
    $name = (string)$name;
    // ★ (2026-11-02 12:04) 점으로 시작해도 진짜 파일('.hack 1권.zip')은 남김 — 숨김 메타(.json·.DS_Store·Thumbs.db)만
    return $name === '' || preg_match('/\.json$/i', $name) === 1 || in_array(strtolower($name), ['.ds_store', 'thumbs.db', 'desktop.ini'], true) || strcasecmp($name, '[cover].jpg') === 0 || preg_match('/\.video_thumb\.jpg$/i', $name) === 1;
}
}

/** ★ (2026-11-02 08:58) 이미지 폴더 안의 쪽이 아닌 그림 — 목록 화면이 만든 표지([cover].jpg)·영상 썸네일(*.video_thumb.jpg) */
if (!function_exists('mc_folder_nonpage')) {
function mc_folder_nonpage($name) {
    $name = (string)$name;
    return strcasecmp($name, '[cover].jpg') === 0 || preg_match('/\.video_thumb\.jpg$/i', $name) === 1;
}
}

// ★ (2026-11-02 22:02) 폴더 분류용 — 책·문서·영상 파일 이름(확장자 뺀 것, 소문자) 모음과 '표지 그림' 판정.
//   같은 이름 표지(책.pdf 옆 책.jpg)·[cover].* 는 쪽 그림으로 세지 않음 — 표지가 6장 넘는 책 폴더가 '이미지 폴더'로 분류돼 책을 열 수 없었음
function mc_folder_book_stems($names): array {
    $s = [];
    if (!is_array($names)) return $s;
    foreach ($names as $n) {
        if (!is_string($n)) continue;
        if (preg_match('/^(.+)\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?|mp4|mkv|avi|mov|webm|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $n, $m)) {
            $s[mb_strtolower($m[1], 'UTF-8')] = true;
            $s[mb_strtolower($n, 'UTF-8')] = true; // ★ (2026-11-02 22:14) '소설.txt.jpg'·'책.epub.png' 처럼 확장자까지 붙인 표지 이름도(카드·thumb.php 가 쓰는 두 번째 형식)
        }
    }
    return $s;
}
// ★ (2026-11-02 22:14) 폴더 안 '쪽 그림' 수(표지·영상 썸네일 뺌) — 카드의 'N장' 칩용. 이미 받은 이름 목록으로 셈(끝까지)
function mc_folder_count_page_imgs($names, array $stems): int {
    $c = 0;
    if (!is_array($names)) return 0;
    foreach ($names as $n) {
        if (!is_string($n) || $n === '' || $n[0] === '.') continue;
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $n) && !preg_match('/\.video_thumb\.jpg$/i', $n) && !mc_folder_is_cover_img($n, $stems)) $c++;
    }
    return $c;
}
function mc_folder_is_cover_img($name, array $stems): bool {
    $name = (string)$name;
    if (preg_match('/^\[cover\]\./i', $name)) return true;
    return preg_match('/^(.+)\.(jpg|jpeg|png|gif|webp)$/i', $name, $m) === 1 && isset($stems[mb_strtolower($m[1], 'UTF-8')]);
}

function get_cache_json_path($filepath) {
    // rar/7z 계열만 확장자 유지(중복 회피)
    if (preg_match('/\.(rar|cbr|7z|cb7)$/i', $filepath)) {
        return $filepath . '.json';
    }
    // 그 외(zip/cbz/pdf/txt/epub/office 등)는 기존 방식: 확장자 제거 후 .json
    return preg_replace('/\.(zip|cbz|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', '.json', $filepath);
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
        // ★ (2026-11-03 22:02) 관리자 '로고 타입' 고르기를 없앰 — 로고 이미지가 있으면 이미지, 없으면 글자(예전에 '글자'로 골라 둔 채 이미지만 올려 둔 경우도 이미지로)
        $cache['logo_type'] = (is_string($cache['logo_image'] ?? null) && $cache['logo_image'] !== '') ? 'image' : 'text';
        
        return array_merge($cache, $extra_defaults);
    }
}

/**
 * ★ (2026-11-03 22:32) 머리 로고 — FileStation 처럼 [로고 아이콘][로고 문구] (펜닐 요청). 올린 로고 이미지가 있으면 그것, 없으면 기본 로고(서재 아이콘 — ★ 2026-11-03 22:46 색 없이 글자색, 선 굵게)
 *   크기는 감싸는 글자 크기(em)를 따라감(화면별 기존 글자 크기·좁은 화면 줄이기 규칙 그대로). 버전은 부르는 쪽이 옆에 붙임
 */
if (!function_exists('mc_brand_logo')) {
    function mc_brand_logo($branding, $href, $extra_style = '') {
        $img = (string)($branding['logo_image'] ?? '');
        $txt = (string)($branding['logo_text'] ?? 'myComix');
        $ico = ($img !== '' && file_exists($img))
            ? '<img src="' . h($img) . '" alt="" style="height:.92em;width:auto;max-width:2.4em;object-fit:contain;flex:0 0 auto;">'
            : '<svg class="mc-brand-def" width="0.92em" height="0.92em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:0 0 auto;"><path d="m16 6 4 14"/><path d="M12 6v14"/><path d="M8 8v12"/><path d="M4 4v16"/></svg>';
        return '<a href="' . h($href) . '" class="logo-link mc-brand" style="display:inline-flex;align-items:center;gap:.2em;text-decoration:none;white-space:nowrap;' . $extra_style . '">' . $ico . '<span class="mc-brand-txt">' . h($txt) . '</span></a>';
    }
}

/**
 * ★ (2026-11-03 23:50) 브라우저 탭 아이콘(파비콘) = 머리 로고와 같은 것(펜닐) — 올린 로고 이미지가 있으면 그것, 없으면 기본 로고(서재 아이콘)
 *   기본: SVG(다크 탭이면 밝은 선) + PNG 32(SVG 를 못 쓰는 사파리) + 홈 화면용 PNG 180(img/logo-default-*.png)
 *   종전엔 모든 화면이 ./favicon.ico 를 가리켰는데 배포본에 그 파일이 없어 탭 아이콘이 비어 있었음
 */
if (!function_exists('mc_favicon_tags')) {
    function mc_favicon_tags() {
        $b = function_exists('load_branding') ? load_branding() : [];
        $img = (string)($b['logo_image'] ?? '');
        if ($img !== '' && file_exists($img)) {
            $u = h($img);
            return '<link rel="icon" href="' . $u . '"><link rel="apple-touch-icon" href="' . $u . '">';
        }
        return '<link rel="icon" type="image/png" sizes="32x32" href="img/logo-default-32.png">'
             . '<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,' . rawurlencode(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 26 26" fill="none" stroke="#202124" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><style>@media (prefers-color-scheme:dark){*{stroke:#e8eaed}}</style><path d="m16 6 4 14"/><path d="M12 6v14"/><path d="M8 8v12"/><path d="M4 4v16"/></svg>
SVG
) . '">'
             . '<link rel="apple-touch-icon" href="img/logo-default-180.png">';
    }
}

/**
 * ★ (2026-11-04 00:27) 라이브러리 전체 훑기용 폴더 반복자 — 목록 화면에서 숨기는 폴더(@eaDir·tmp·점으로 시작)는 들어가지 않음
 *   종전엔 배경 갱신·검색 색인·일괄 캐시가 시놀로지 @eaDir 안까지 훑어 검색 결과에 @eaDir 안 썸네일 경로가 나오고 그 안에 캐시 파일도 썼음
 */
if (!function_exists('mc_rdi')) {
    function mc_rdi($base_dir) {
        $it = new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS);
        return new RecursiveCallbackFilterIterator($it, function ($cur) {
            if (!$cur->isDir()) return true;
            $n = $cur->getFilename();
            return !($n === '' || $n[0] === '.' || $n === '@eaDir' || $n === 'tmp');
        });
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
            if (is_array($data)) $GLOBALS['__mc_users_snap'] = $data; // ★ (2026-11-04 09:35) 읽은 내용 기억 — save_users 가 '바꾼 회원만' 최신 파일에 얹음
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
        // ★ (2026-10-30) 만들기 실패면 사용자 파일을 비우지 않음(전체 계정이 사라지는 것 방지)
        $json = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) { fclose($fp); return false; }
        if (flock($fp, LOCK_EX)) {
            // ★ (2026-11-04 09:35) 읽을 때(load_users) 받은 내용과 비교해 이 요청이 바꾼·더한·지운 회원만, 잠금 안에서 다시 읽은 최신 파일에 얹어 저장
            //   전엔 통째로 덮어써, 그사이 다른 요청이 바꾼 회원(동시 가입·비밀번호 찾기·언어 저장 등)이 사라졌음. 같은 회원을 둘이 바꾸면 나중 것
            $snap = $GLOBALS['__mc_users_snap'] ?? null; // ★ (2026-11-04 12:41) 주의: 스냅숏은 '마지막 load_users() 결과' — load 와 save 사이에 load_users() 를 다시 부르는 코드를 넣으면 병합이 틀림(옛 배열을 저장하면 남의 변경이 되돌아감). 그럴 땐 mc_users_modify 를 쓸 것
            if (is_array($snap)) {
                $cur_raw = stream_get_contents($fp);
                $cur = (is_string($cur_raw) && trim($cur_raw) !== '') ? json_decode($cur_raw, true) : null;
                if (is_array($cur)) {
                    // ★ (2026-11-04 10:06) 재검토: ① 그사이 남이 지운 회원은 되살리지 않음 ② 같은 회원이면 바꾼 칸만 얹음(남이 바꾼 다른 칸 보존)
                    $merged = $cur;
                    foreach (array_keys($snap + $users) as $k) {
                        $in_s = array_key_exists($k, $snap); $in_u = array_key_exists($k, $users); $in_c = array_key_exists($k, $cur);
                        if (!$in_u) { unset($merged[$k]); continue; }                 // 이 요청이 지움
                        if ($in_s && $users[$k] === $snap[$k]) continue;                // 안 바꿈 — 최신 그대로
                        if ($in_s && !$in_c) continue;                                  // 그사이 남이 지움 — 되살리지 않음
                        if ($in_s && $in_c && is_array($snap[$k]) && is_array($users[$k]) && is_array($cur[$k])) {
                            $m = $cur[$k];
                            foreach (array_keys($snap[$k] + $users[$k]) as $f) {
                                if (!array_key_exists($f, $users[$k])) unset($m[$f]);
                                elseif (!array_key_exists($f, $snap[$k]) || $users[$k][$f] !== $snap[$k][$f]) $m[$f] = $users[$k][$f];
                            }
                            $merged[$k] = $m;
                        } else {
                            $merged[$k] = $users[$k];                                   // 새 회원 등
                        }
                    }
                    $j2 = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
                    if ($j2 !== false) $json = $j2;
                }
            }
            ftruncate($fp, 0);
            rewind($fp);
            $result = fwrite($fp, $json) !== false;
            fflush($fp);
            flock($fp, LOCK_UN);
            if ($result) $GLOBALS['__mc_users_snap'] = $users; // ★ (2026-11-04 10:06) 호출한 쪽 배열을 기억(병합 결과를 기억하면 같은 배열로 두 번 저장할 때 남의 새 회원을 지움)
        }
        fclose($fp);
        return $result;
    }
}

/**
 * ★ (2026-11-04 09:33) 사용자 파일을 잠금(LOCK_EX) 하나 안에서 읽고 $fn(&$users) 로 고친 뒤 저장 — $fn 이 false 를 돌려주면 저장 안 함
 *   load_users()+save_users() 는 읽기·쓰기 잠금이 따로라, 동시에 온 요청(가입 여러 명·비밀번호 찾기 등)이 서로 덮어썼음(검토자 실측: 동시 가입 6명 중 3명만 남음)
 *   파일이 있는데 해석 실패면 덮어쓰지 않고 null. 파일이 없으면 null(첫 설치는 기존 save_users 경로)
 * @return mixed $fn 의 반환값(실패 시 null)
 */
if (!function_exists('mc_users_modify')) {
    function mc_users_modify(callable $fn) {
        $file = get_users_file_path();
        if (!is_file($file)) return null;
        $fp = @fopen($file, 'c+');
        if (!$fp) return null;
        if (!@flock($fp, LOCK_EX)) { fclose($fp); return null; }
        $raw = stream_get_contents($fp);
        $users = (is_string($raw) && trim($raw) !== '') ? json_decode($raw, true) : null;
        if (!is_array($users)) { @flock($fp, LOCK_UN); fclose($fp); error_log('[mycomix] users.json 을 해석하지 못해 고치지 않음'); return null; }
        $res = $fn($users);
        if ($res !== false) {
            $json = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) {
                $res = null;
            } else {
                ftruncate($fp, 0);
                rewind($fp);
                $w = fwrite($fp, $json);
                if ($w === false || $w < strlen($json)) { // 쓰기 실패면 원래 내용으로 되돌림
                    ftruncate($fp, 0); rewind($fp); @fwrite($fp, $raw);
                    $res = null;
                }
                fflush($fp);
            }
        }
        @flock($fp, LOCK_UN);
        fclose($fp);
        return $res;
    }
}

/**
 * ★ (2026-10-30) 사용자 정지 상태 판단 — 관리자 화면(admin.php)이 저장하는 키(suspended_until)를 기준으로 통일
 *   (전에는 login.php 는 suspend_end, init.php 는 suspension_end 를 읽어 정지가 로그인을 막지 못했음)
 * @return array ['blocked' => 아직 정지 중인지, 'expired' => 기간이 끝나 풀어야 하는지, 'until' => 표시용 종료일('' 이면 무기한)]
 */
if (!function_exists('mc_user_suspend_state')) {
    function mc_user_suspend_state($user) {
        if (!is_array($user) || ($user['status'] ?? 'active') !== 'suspended') return ['blocked' => false, 'expired' => false, 'until' => ''];
        $from = trim((string)($user['suspended_from'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && $from > date('Y-m-d')) return ['blocked' => false, 'expired' => false, 'until' => '']; // 시작일 전(예약)
        $until = (string)($user['suspended_until'] ?? $user['suspend_end'] ?? $user['suspension_end'] ?? '');
        $until = trim($until);
        if ($until === '') return ['blocked' => true, 'expired' => false, 'until' => '']; // 무기한
        $ts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) ? strtotime($until . ' 23:59:59') : strtotime($until); // 날짜만 있으면 그날 끝까지 정지
        if ($ts === false) return ['blocked' => true, 'expired' => false, 'until' => $until];
        if ($ts > time()) return ['blocked' => true, 'expired' => false, 'until' => date('Y-m-d', $ts)];
        return ['blocked' => false, 'expired' => true, 'until' => date('Y-m-d', $ts)];
    }
}
if (!function_exists('mc_user_clear_suspend')) {
    function mc_user_clear_suspend(&$user) {
        $user['status'] = 'active';
        foreach (['suspended_reason', 'suspended_from', 'suspended_until', 'suspend_reason', 'suspend_start', 'suspend_end', 'suspension_reason', 'suspension_start', 'suspension_end'] as $k) unset($user[$k]);
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
        // ★ (2026-10-30) 먼저 만들어 보고, 실패하면(잘못된 UTF-8·INF 등) 파일을 비우지 않음 — 전에는 빈 파일로 덮어써 북마크·즐겨찾기 등이 통째로 사라질 수 있었음
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) { fclose($fp); return false; }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            $result = fwrite($fp, $json) !== false;
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $result;
    }
}

/**
 * ★ (2026-11-09 11:43) 계정별 기록(북마크·자동 저장·즐겨찾기·그림 크기) 읽고-고치고-쓰기를 한 줄로 세움
 *   load_json_with_lock·save_json_with_lock 은 읽기와 쓰기를 따로 잠가, 같은 계정의 요청이 겹치면(폰·PC 동시, 탭 여러 개 닫기)
 *   서로 읽은 옛 내용으로 덮어써 기록이 사라졌음 — 이 잠금을 잡은 요청끼리는 차례로 처리됨(잡은 요청이 끝나면 저절로 풀림)
 *   잠금 파일을 못 열거나 못 잠그면 예전처럼 그냥 진행(저장이 막히지 않게)
 */
if (!function_exists('mc_rec_lock')) {
    function mc_rec_lock($release = false) {
        static $fp = null;
        if ($release) { if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); $fp = null; } return true; }
        if ($fp) return true;
        if (!function_exists('get_autosave_file')) return false;
        $lf = dirname(get_autosave_file()) . '/' . preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_SESSION['user_id'] ?? 'guest')) . '_rec.lock';
        $h = @fopen($lf, 'c');
        if (!$h) return false;
        if (!@flock($h, LOCK_EX)) { @fclose($h); return false; }
        $fp = $h;
        return true;
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
                $logs = json_decode($content, true);
                // ★ (2026-11-03 09:09) 깨진 로그 파일이면 덮어쓰지 않음(빈 파일만 새로) — 끝이 잘린 파일에 한 번 쓰면 기록이 1개만 남고, 숫자 하나뿐이면 count() 오류로 목록 화면 전체가 500 이었음(탈퇴·삭제 쪽과 같은 방식)
                $__lw = is_array($logs) || trim((string)$content) === '';
                if (!is_array($logs)) $logs = [];
                
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
                
                $__lj = json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE); // ★ (2026-10-30) 실패 시 비우지 않음
                if ($__lj !== false && $__lw) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, $__lj);
                }
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
        $temp_file = $filepath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4)); // ★ (2026-10-30) Windows mod_php 는 스레드끼리 PID 가 같아 동시 저장 시 임시 파일이 겹쳤음
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

// ★ (2026-11-02 14:26) 새 아이디 겹침 검사 — Windows(NTFS)는 파일 이름 대소문자를 안 가려서 'Admin' 이 'admin' 의
//   src/<아이디>_bookmark·autosave·favorites… 를 같이 쓰던 것. 그래서 대소문자만 다른 아이디는 새로 못 만듦.
//   캐시 폴더 이름(영문·숫자만)이 비거나 공용 캐시 폴더(vframes·vprobe·vlock)와 같으면 로그인 때 공용 캐시를 지우므로 그것도 거절
function mc_user_id_unavailable(array $users, string $id): bool {
    $safe = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $id));
    if ($safe === '' || in_array($safe, ['vframes', 'vprobe', 'vlock'], true)) return true;
    foreach (array_keys($users) as $k) {
        if (strcasecmp((string)$k, $id) === 0) return true;
        if (strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$k)) === $safe) return true; // ★ (2026-11-02 19:10) 'a_b'·'ab' 처럼 캐시 폴더 이름(영문·숫자만)이 같아지는 아이디도 — 한쪽이 로그인할 때 다른 쪽 캐시를 지웠음
    }
    return false;
}

// ★ (2026-11-02 16:16) 관리자가 정한 '가입 금지 아이디'(설정 → 회원) — 대소문자 무시, * 는 아무 글자. 회원가입에만 적용
function mc_signup_id_blocked(string $id): bool {
    $list = function_exists('get_app_settings') ? (get_app_settings('registration', [])['blocked_ids'] ?? []) : [];
    if (!is_array($list) || !$list) return false;
    foreach ($list as $pat) {
        if (is_int($pat)) $pat = (string)$pat; // ★ (2026-11-02 21:09) 숫자만 된 항목은 JSON 에 정수로 저장돼 그동안 건너뛰었음(막히지 않았음)
        if (!is_string($pat) || $pat === '') continue;
        $re = '/^' . str_replace('\\*', '.*', preg_quote($pat, '/')) . '$/i';
        if (preg_match($re, $id) !== 0) return true; // 판정 실패(false)도 금지로
    }
    return false;
}

/** 설정 화면 글을 금지 아이디 목록으로(한 줄·쉼표·공백으로 나눔, 영문·숫자·밑줄·* 만, 최대 500개) */
function mc_parse_blocked_ids($text): array {
    $out = [];
    foreach ((preg_split('/[\s,\x{00A0}\x{3000}]+/u', is_string($text) ? $text : '') ?: preg_split('/[\s,]+/', is_string($text) ? $text : '')) as $p) { // ★ (2026-11-02 17:37) 붙여 넣은 특수 공백(NBSP·전각)도 나눔 — 그 항목이 통째로 버려졌음
        $p = strtolower(trim($p));
        $p = preg_replace('/\*+/', '*', $p); // 연속 * 는 하나로
        if ($p === '' || !preg_match('/^[a-z0-9_*]{1,40}$/', $p) || trim($p, '*') === '' || substr_count($p, '*') > 4) continue; // * 는 4개까지
        $out[$p] = true;
        if (count($out) >= 500) break;
    }
    return array_map('strval', array_keys($out)); // ★ (2026-11-02 21:09) "12345" 같은 키가 정수로 바뀌지 않게
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
    if (!is_array($deleted_data)) return false; // ★ (2026-11-04 09:39) 배열이 아니면 foreach 경고
    foreach ($deleted_data as $entry) {
        if (isset($entry['user_id']) && strcasecmp((string)$entry['user_id'], $user_id) === 0) { // ★ (2026-11-02 14:26) 대소문자 무시(위 mc_user_id_unavailable 와 같은 이유)
            return true;
        }
    }
    return false;
}


// ============================================================
// ★ (2026-10-29) 썸네일 크기 커스텀 (관리자 설정 → app_settings.json 'thumb_layout')
//   - 기준 폭(breakpoint) 이상 화면 = PC 값, 미만 = 모바일 값 (기기 종류가 아니라 화면 폭 기준 → 큰 태블릿/가로 모드는 PC 값)
//   - 값 0(또는 빈칸) = 기존 기본 CSS 그대로(아무것도 출력 안 함) → 설정 전과 화면 100% 동일
//   - 값은 정수 px로만 저장/출력(범위 밖은 가까운 한계값으로) → CSS에 사용자 문자열이 들어가지 않음
// ============================================================
if (!function_exists('mc_thumb_layout_fields')) {
    /** 필드 정의: 키 => [최소, 최대] (단위 px) */
    function mc_thumb_layout_fields() {
        $grid = [60, 600];
        $list = [30, 300];
        return [
            'breakpoint'     => [360, 2560],
            // 폴더 카드 (그리드=커버 이미지 높이, 목록=썸네일 폭)
            'folder_grid_pc' => [40, 600], 'folder_grid_mo' => [40, 600],
            'folder_list_pc' => $list,     'folder_list_mo' => $list,
            // 파일 카드 (그리드=카드 폭, 목록=썸네일 폭)
            'file_grid_pc'   => $grid,     'file_grid_mo'   => $grid,
            'file_list_pc'   => $list,     'file_list_mo'   => $list,
            // 즐겨찾기 책장 (그리드=표지 폭, 목록=썸네일 폭)
            'shelf_grid_pc'  => $grid,     'shelf_grid_mo'  => $grid,
            'shelf_list_pc'  => $list,     'shelf_list_mo'  => $list,
            // ★ (2026-10-31) 라이브러리(작품 탐색·작품 상세) (그리드=표지 폭, 목록=썸네일 폭)
            'lib_grid_pc'    => $grid,     'lib_grid_mo'    => $grid,
            'lib_list_pc'    => $list,     'lib_list_mo'    => $list,
            // ★ (2026-10-31) 북마크 화면(bookmarks.php) (그리드=표지 폭, 목록=썸네일 폭)
            'bm_grid_pc'     => $grid,     'bm_grid_mo'     => $grid,
            'bm_list_pc'     => $list,     'bm_list_mo'     => $list,
        ];
    }
}

if (!function_exists('mc_thumb_layout_sanitize')) {
    /** 입력(POST/저장값) → 정수 배열. 비었거나 0 이하면 0(기본값), 범위 밖은 한계값으로 */
    function mc_thumb_layout_sanitize($in) {
        $out = [];
        $in = is_array($in) ? $in : [];
        foreach (mc_thumb_layout_fields() as $k => $range) {
            $v = isset($in[$k]) ? trim((string)$in[$k]) : '';
            $n = ($v === '' || !preg_match('/^\d{1,5}$/', $v)) ? 0 : (int)$v;
            if ($k === 'breakpoint') {
                $n = ($n <= 0) ? 768 : max($range[0], min($range[1], $n));
            } elseif ($n > 0) {
                $n = max($range[0], min($range[1], $n));
            }
            $out[$k] = $n;
        }
        return $out;
    }
}

if (!function_exists('mc_thumb_layout_css')) {
    /**
     * 설정값으로 CSS 생성. $scope: 'index'(폴더/파일 목록) | 'shelf'(즐겨찾기 책장)
     * 설정이 없거나 모든 값이 0이면 빈 문자열.
     */
    function mc_thumb_layout_css($scope) {
        $raw = function_exists('get_app_settings') ? get_app_settings('thumb_layout', []) : [];
        $t = mc_thumb_layout_sanitize($raw);
        $bp = (int)$t['breakpoint'];
        $media = [
            'pc' => '@media (min-width: ' . $bp . 'px)',
            'mo' => '@media (max-width: ' . ($bp - 1) . 'px)',
        ];
        $h43 = function ($w) { return (int)round($w * 4 / 3); };
        $css = '';
        foreach (['pc', 'mo'] as $env) {
            $r = '';
            if ($scope === 'index') {
                // ★ (2026-10-31) 폴더/파일 구분: data-folder-path(관리자 화면에만 붙음) → mc-folder-card(모든 사용자) — 전에는 관리자 아닌 사용자에게
                //   폴더 크기 설정이 안 먹고, 파일 그리드 폭이 폴더 카드에도 걸렸음
                // 파일 카드 — 그리드: 카드 폭 (썸네일은 카드 폭 × 3:4)
                if ($w = $t['file_grid_' . $env]) {
                    // 이미지 폴더 카드(mc-folder-card 이지만 파일 카드처럼 그림이 카드 바로 안, 파일과 같은 줄)도 파일 크기를 따름
                    $r .= 'html:not(.mc-list-view) .grid .row > a > .col.mb-3:has(> .card:not(.mc-folder-card)),'
                        . 'html:not(.mc-list-view) .grid .row > .col.mb-3:has(> .card:not(.mc-folder-card)),'
                        . 'html:not(.mc-list-view) .grid .row > a > .col.mb-3:has(> .card.mc-folder-card > img.card-img-top),'
                        . 'html:not(.mc-list-view) .grid .row > .col.mb-3:has(> .card.mc-folder-card > img.card-img-top)'
                        // 화면보다 큰 값이면 화면 폭까지만(min) → 모바일에서 가로 넘침/축소 방지
                        . '{flex:0 0 min(' . $w . 'px, 100%) !important;width:min(' . $w . 'px, 100%) !important;max-width:min(' . $w . 'px, 100%) !important;}'
                        . 'html:not(.mc-list-view) .grid .card:not(.mc-folder-card) > .card-img,html:not(.mc-list-view) .grid .card.mc-folder-card > img.card-img{max-height:none !important;}';
                }
                // 파일 카드 — 목록: 썸네일 폭 (높이 = 폭 × 4/3)
                //   ★ (2026-10-31) 목록 카드의 썸네일 칸(--mc-lt-w, index.php 목록 CSS)도 같은 폭으로 — 전에는 칸이 54px 고정이라 그림이 54px 폭에 길쭉하게 눌렸음
                if ($w = $t['file_list_' . $env]) {
                    // :has 규칙은 따로(옛 브라우저가 :has 를 모르면 그 규칙만 무시 — 아래 그림 크기 규칙은 그대로 적용)
                    $r .= 'html.mc-list-view body .grid .card:not(.mc-folder-card){--mc-lt-w:' . $w . 'px;--mc-lt-h:' . $h43($w) . 'px;}'
                        . 'html.mc-list-view body .grid .card.mc-folder-card:has(> img.card-img-top){--mc-lt-w:' . $w . 'px;--mc-lt-h:' . $h43($w) . 'px;}'
                        . 'html.mc-list-view body .grid .card:not(.mc-folder-card) > .card-img,'
                        . 'html.mc-list-view body .grid .card:not(.mc-folder-card) > .card-img-placeholder,'
                        . 'html.mc-list-view body .grid .card:not(.mc-folder-card) > img.card-img-top,'
                        . 'html.mc-list-view body .grid .card.mc-folder-card > img.card-img-top'
                        . '{flex:0 0 ' . $w . 'px !important;width:' . $w . 'px !important;max-width:none !important;height:' . $h43($w) . 'px !important;}';
                }
                // 폴더 카드 — 그리드: 커버 이미지 높이
                if ($hgt = $t['folder_grid_' . $env]) {
                    $r .= 'html:not(.mc-list-view) .grid .card.mc-folder-card > .card-body > img'
                        . '{height:' . $hgt . 'px !important;}';
                }
                // 폴더 카드 — 목록: 썸네일 폭
                if ($w = $t['folder_list_' . $env]) {
                    $r .= 'html.mc-list-view body .grid .card.mc-folder-card:not(:has(> img.card-img-top)){--mc-lt-w:' . $w . 'px;--mc-lt-h:' . $h43($w) . 'px;}'
                        . 'html.mc-list-view body .grid .card.mc-folder-card > .card-body > img'
                        . '{flex:0 0 ' . $w . 'px !important;width:' . $w . 'px !important;min-width:' . $w . 'px !important;max-width:' . $w . 'px !important;height:' . $h43($w) . 'px !important;}';
                }
            } elseif ($scope === 'shelf') {
                // 책장 — 그리드: 표지 폭 (칸 폭 고정)
                if ($w = $t['shelf_grid_' . $env]) {
                    $r .= 'body .fav-grid{grid-template-columns:repeat(auto-fill, min(' . $w . 'px, 100%)) !important;}'; // 화면보다 크면 화면 폭까지만
                }
                // 책장 — 목록: 썸네일 폭
                if ($w = $t['shelf_list_' . $env]) {
                    $r .= 'body .fav-list .fav-cover{flex:0 0 ' . $w . 'px !important;width:' . $w . 'px !important;height:' . $h43($w) . 'px !important;}';
                }
            } elseif ($scope === 'lib') {
                // ★ (2026-10-31) 작품 탐색(browse.php) — 그리드: 표지 폭(한 줄을 꽉 채우도록 폭 이상으로 늘어남), 위쪽 '읽는 중·최근 추가' 가로 줄도 같은 폭
                if ($w = $t['lib_grid_' . $env]) {
                    $r .= 'body .sc-grid{grid-template-columns:repeat(auto-fill, minmax(min(' . $w . 'px, 100%), 1fr)) !important;}'
                        . 'body .sc-row{grid-auto-columns:min(' . $w . 'px, 80%) !important;}';
                }
                // 목록: 썸네일 폭
                if ($w = $t['lib_list_' . $env]) {
                    $r .= 'body .sc-list .sc-cover{flex:0 0 ' . $w . 'px !important;width:' . $w . 'px !important;height:' . $h43($w) . 'px !important;}';
                }
            } elseif ($scope === 'bm') {
                // ★ (2026-10-31) 북마크 화면(bookmarks.php) — 그리드: 표지 폭(서재처럼 한 줄을 꽉 채우도록 폭 이상으로 늘어남, 화면보다 크면 화면 폭까지만)
                if ($w = $t['bm_grid_' . $env]) {
                    $r .= 'body .bm-grid{grid-template-columns:repeat(auto-fill, minmax(min(' . $w . 'px, 100%), 1fr)) !important;}';
                }
                // 목록: 썸네일 폭
                if ($w = $t['bm_list_' . $env]) {
                    $r .= 'body .bm-list .bm-cover{flex:0 0 ' . $w . 'px !important;width:' . $w . 'px !important;height:' . $h43($w) . 'px !important;}';
                }
            } elseif ($scope === 'series') {
                // ★ (2026-10-31) 작품 상세(series.php) 권 목록 — 라이브러리 값이 있으면 그 값, 없으면 예전처럼 책장 값(설정해 둔 화면이 바뀌지 않게)
                if ($w = ($t['lib_grid_' . $env] ?: $t['shelf_grid_' . $env])) {
                    $r .= 'body .vols-grid{grid-template-columns:repeat(auto-fill, min(' . $w . 'px, 100%)) !important;}';
                }
                if ($w = ($t['lib_list_' . $env] ?: $t['shelf_list_' . $env])) {
                    $r .= 'body .vols-list .vol-cover{flex:0 0 ' . $w . 'px !important;width:' . $w . 'px !important;height:' . $h43($w) . 'px !important;}';
                }
            }
            if ($r !== '') $css .= $media[$env] . '{' . $r . '}' . "\n";
        }
        return $css;
    }
}


// ============================================================
// ★ (2026-11-05) 목록 카드 제목 앞 아이콘 — 폴더 아이콘(mc-icons.js 가 그리는 선 아이콘)과 같은 모양·굵기·글자색 (펜닐 요청: 노란 압축 아이콘 → 선 아이콘)
//   모양: Lucide (ISC License, https://lucide.dev) / 파일 카드 제목은 mc-icons.js 가 건너뛰는 곳(.card.text-black > .card-body)이라 서버에서 바로 그림
//   제목과 붙여서(사이 띄어쓰기 없이) 출력 — 간격은 CSS(.mc-ci)가 정함 → 목록 보기에서 배지 줄을 제목 첫 글자 위치에 정확히 맞출 수 있음
// ============================================================
if (!function_exists('mc_card_icon')) {
    function mc_card_icon($name) {
        static $P = [
            'archive' => '<path d="M10 12v-1"/><path d="M10 18v-2"/><path d="M10 7V6"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M15.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v16a2 2 0 0 0 .274 1.01"/><circle cx="10" cy="20" r="2"/>',
            'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
            'images' => '<path d="M18 22H4a2 2 0 0 1-2-2V6"/><path d="m22 13-1.296-1.296a2.41 2.41 0 0 0-3.408 0L11 18"/><circle cx="12" cy="8" r="2"/><rect width="16" height="16" x="6" y="2" rx="2"/>',
        ];
        $n = isset($P[$name]) ? $name : 'file-text';
        return '<span class="emoji-icon mc-ci"><svg class="mc-ico mc-ei mc-ico-' . $n . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $P[$n] . '</svg></span>';
    }
}

// ============================================================
// ★ (2026-11-01 23:37) 만화 뷰어 '모드 기억'·'이어 보기' — 뷰어 위 막대 설정(톱니)에서 고르고 이 기기 쿠키에 기억 (펜닐 요청)
//   · 모드 기억(mcv_modemem): off = 늘 주소의 모드(목록은 툰모드, 기본) / all = 마지막에 쓴 모드(mcv_mode) / book = 책마다 내 기록의 모드
//     (book 인데 처음 보는 책이면 기본(툰모드)) — ★ (2026-11-02 01:01) 기본값 all→off, 책마다의 처음 보는 책 마지막 모드→툰모드 (펜닐 요청)
//   · 설정은 쿠키라 브라우저(기기)마다 따로
//   · 이어 보기(mcv_resume, 기본 켬): 주소에 위치(#imageN)가 없으면 내 기록(계정별 자동 저장)의 보던 쪽에서
//   · 내 기록 = src/<아이디>_autosave.json (툰·북모드가 나갈 때 저장하는 그 기록, 책마다·계정마다) — 다른 사용자·책 정보 파일에는 안 씀
//   viewer.php(열 때)·index.php(카드 배지) 공용
// ============================================================
if (!function_exists('mc_mv_autosave_entry')) {
    function mc_mv_autosave_entry($getfile, $bidx) {
        static $arr = null;
        if ($arr === null) {
            $arr = [];
            $f = function_exists('get_autosave_file') ? get_autosave_file() : '';
            if ($f !== '' && is_file($f)) { $d = function_exists('load_json_with_lock') ? load_json_with_lock($f) : json_decode((string)@file_get_contents($f), true); if (is_array($d)) $arr = $d; } // ★ (2026-11-02 09:57) 공유 잠금 — 앞 권 자동 저장(비우고 쓰기)과 겹치면 빈 내용이라 이어 보기를 놓쳤음 / 쿠키는 문자열만(배열이면 경고)
        }
        $__k = function_exists('mc_rec_find') ? mc_rec_find($arr, $bidx, (string)$getfile) : (string)$getfile; // ★ (2026-11-06 00:50) 같은 경로를 다른 콘텐츠 폴더가 쓰면 따로 저장한 칸("bidx|/경로")도
        $e = $__k !== null ? ($arr[$__k] ?? null) : null;
        if (!is_array($e) || (int)($e['bidx'] ?? 0) !== (int)$bidx) return null; // 옛 문자열 형식·다른 폴더(bidx)는 쓰지 않음
        return $e;
    }
    function mc_mv_modemem() {
        $v = (is_string($_COOKIE['mcv_modemem'] ?? null) ? $_COOKIE['mcv_modemem'] : 'off');
        return in_array($v, ['off', 'all', 'book'], true) ? $v : 'off';
    }
    // 이 책을 열 때 쓸 모드 — $fallback: 주소·책 정보의 모드(설정이 '끔'이면 그대로)
    function mc_mv_mode_for($getfile, $bidx, $fallback) {
        if ($fallback !== 'toon' && $fallback !== 'book') return $fallback; // ★ (2026-11-02) 툰/북 화면만(동영상 등 다른 값은 그대로)
        $mm = mc_mv_modemem();
        if ($mm === 'off') return $fallback;
        if ($mm === 'book') {
            $e = mc_mv_autosave_entry($getfile, $bidx);
            $v = is_array($e) ? (string)($e['viewer'] ?? '') : '';
            return ($v === 'toon' || $v === 'book') ? $v : $fallback; // 처음 보는 책은 기본(툰모드)
        }
        $last = (is_string($_COOKIE['mcv_mode'] ?? null) ? $_COOKIE['mcv_mode'] : '');
        return ($last === 'toon' || $last === 'book') ? $last : $fallback;
    }
    // ★ (2026-11-05) 이 책을 열 때 쓸 보기 방식(쪽 순서: 0 한 쪽·1 1|2·2|1·3·4 세로분할) — 모드 기억과 같은 설정을 따름
    //   · 책마다(book): 내 기록(자동 저장)에 남은 이 책의 보기 방식 → 없으면(처음 보는 책) 기본값($fallback, 한 쪽) — 모드(처음 보는 책은 툰모드)와 같게
    //     ★ (2026-11-05 15:40) 처음 보는 책에 '마지막에 고른 값'을 쓰던 것을 기본값으로 — 한 책에서 2|1 로 바꾸면 안 본 책 전부가 같이 2|1 이 됐음(펜닐 제보)
    //   · 끔·전체: 이 기기에서 마지막에 고른 보기 방식(mcv_order 쿠키 — 예전부터 뷰어가 쓰던 값 그대로) → 없으면 $fallback
    //   viewer.php(열 때)·index.php(카드 배지)가 같은 값을 쓰게 — 전에는 카드 배지가 책 정보 파일(.json)의 값을 보여 줘서
    //   뷰어가 기억해 여는 보기 방식과 달리 늘 [ - ] 로 보였음
    function mc_mv_order_for($getfile, $bidx, $fallback = '0') {
        $ok = ['0', '1', '2', '3', '4'];
        $fallback = (string)$fallback;
        if (!in_array($fallback, $ok, true)) $fallback = '0';
        if (mc_mv_modemem() === 'book') {
            $e = mc_mv_autosave_entry($getfile, $bidx);
            $v = is_array($e) ? (string)($e['page_order'] ?? '') : '';
            return in_array($v, $ok, true) ? $v : $fallback;
        }
        $c = (is_string($_COOKIE['mcv_order'] ?? null) ? $_COOKIE['mcv_order'] : '');
        return in_array($c, $ok, true) ? $c : $fallback;
    }
    // 카드 배지 글자 — 뷰어 위 막대의 짧은 이름과 같은 기호(0 은 예전처럼 [ - ])
    function mc_mv_order_label($order) {
        $m = ['0' => '[ - ]', '1' => '[1|2]', '2' => '[2|1]', '3' => '[▤←]', '4' => '[→▤]'];
        return $m[(string)$order] ?? '[ - ]';
    }
    // 이어 볼 위치('imageN' / PDF 'pdf_N') — 끔이거나 기록이 없으면 ''
    // ★ (2026-11-02 01:01) $kind: 'toon'·'book'·'pdf' — 툰모드·PDF 는 쪽 안 위치(천분율 f)를 붙임('image12f345') → 나갔던 그 줄에서 다시 열림
    //   (북모드는 쪽 단위라 안 붙임 / 만화 기록은 만화에만, PDF 기록은 PDF 에만)
    function mc_mv_resume_mark($getfile, $bidx, $kind = 'toon') {
        if ((is_string($_COOKIE['mcv_resume'] ?? null) ? $_COOKIE['mcv_resume'] : '1') === '0') return '';
        $e = mc_mv_autosave_entry($getfile, $bidx);
        $b = is_array($e) ? (string)($e['bookmark'] ?? '') : '';
        $ok = ($kind === 'pdf') ? preg_match('/^pdf_\d{1,6}$/', $b) : preg_match('/^image\d+$/', $b);
        if (!$ok) return '';
        if ($kind !== 'book') {
            $f = $e['f'] ?? null;
            if (is_int($f) && $f >= 0 && $f <= 999) $b .= 'f' . $f;
        }
        return $b;
    }
}

/**
 * ★ (2026-11-05) 뷰어 '뒤로가기'(위 막대 ←·목록 단추)를 들어온 곳으로(펜닐 요청)
 *   서재·작품·즐겨찾기·북마크·폴더 목록에서 책을 열면 뒤로가기가 늘 그 파일의 폴더 목록으로 가던 것 → 책을 연 그 화면(주소·스크롤 위치)으로
 *   · 목록 화면(mc_back_origin_list_js): 떠날 때(링크 누름·페이지 숨김) 지금 주소 + 스크롤 위치(#ly픽셀)를 이 탭에 기억(sessionStorage)
 *   · 뷰어(mc_back_origin_viewer_js): window.mcBackUrl(기본 주소) — 기억한 곳이 있으면 그 주소, 없으면 기본(지금처럼 폴더 목록)
 *     주소를 직접 열었거나 새 탭에서 처음 연 경우 등 기억이 없으면 예전 그대로 / 정해진 목록 화면(같은 사이트 안 상대 주소)만 받음
 *   · 뷰어 안에서 이전·다음 권으로 옮겨도 그대로(뷰어는 기억을 바꾸지 않음)
 */
if (!function_exists('mc_back_origin_list_js')) {
    function mc_back_origin_list_js() {
        return <<<'JS'
<script>
(function () {
  function save() {
    try {
      var y = Math.round(window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0);
      var page = location.pathname.split('/').pop() || 'index.php';
      sessionStorage.setItem('mc_back_origin', page + location.search + (y > 0 ? '#ly' + y : ''));
    } catch (e) {}
  }
  // ★ (2026-11-05 22:40) 누른 시각 — 바로 이어 열린 뷰어가 '이 화면이 바로 앞 방문 기록'임을 알 수 있게(뷰어 ← 가 브라우저 뒤로로 돌아와 기록이 두 번 쌓이지 않게)
  document.addEventListener('click', function () { save(); try { sessionStorage.setItem('mc_back_click', String(Date.now())); } catch (e) {} }, true);
  window.addEventListener('pagehide', save);
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') save(); });
})();
</script>
JS;
    }
}
if (!function_exists('mc_back_origin_viewer_js')) {
    function mc_back_origin_viewer_js() {
        return <<<'JS'
<script>
(function () {
  var RE = /^(?:index|browse|series|favorites|bookmarks)\.php(?:\?[^#\s]*)?(?:#ly\d{1,7})?$/;
  function g(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function s(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }
  function d(k) { try { sessionStorage.removeItem(k); } catch (e) {} }
  function bare(u) { return String(u || '').replace(/#.*$/, ''); }
  // ★ (2026-11-05 22:40) 바로 앞 방문 기록이 들어온 곳인지(mc_back_hist = '주소|기록 개수') — 펜닐 제보: 서재 → 작품 화면 → 권 → ← 뒤 작품 화면에서 '돌아가기'를 두 번 눌러야 했음
  //   (← 가 작품 화면을 새로 열어 방문 기록에 작품 화면이 두 번 쌓였음)
  //   · 목록에서 방금(20초 안) 눌러 열린 뷰어 → 그 목록이 바로 앞 기록 / 이전·다음 권(replace)은 기록 개수가 그대로라 유지
  //   · 기록 개수가 바뀌면(뷰어 안 링크로 쌓인 경우) 버림 → 예전처럼 주소로 이동
  var nt = ''; try { var en = performance.getEntriesByType('navigation'); nt = (en && en[0] && en[0].type) || ''; } catch (x) {}
  if (nt !== 'back_forward' && nt !== 'reload') {
    var o = g('mc_back_origin'), c = parseInt(g('mc_back_click') || '0', 10) || 0;
    if (c && Date.now() - c >= 0 && Date.now() - c < 20000 && RE.test(o || '')) s('mc_back_hist', bare(o) + '|' + history.length);
    else { var h = g('mc_back_hist'); if (h && h.split('|').pop() !== String(history.length)) d('mc_back_hist'); }
  }
  d('mc_back_click');
  window.mcBackUrl = function (def) {
    var u = g('mc_back_origin');
    return (typeof u === 'string' && RE.test(u)) ? u : def;
  };
  // 들어온 곳으로 가기 — 바로 앞 기록이면 브라우저 뒤로(그 화면 그대로·기록 안 쌓임), 아니면 주소로(mode: 'replace' 기본 / 'push')
  window.mcBackGo = function (url, mode) {
    var h = g('mc_back_hist');
    if (history.length > 1 && h && h === bare(url) + '|' + history.length && RE.test(String(url || ''))) { d('mc_back_hist'); history.back(); return; }
    if (mode === 'push') location.href = url; else location.replace(url);
  };
  document.addEventListener('DOMContentLoaded', function () {
    try {
      document.querySelectorAll('a[data-mc-back]').forEach(function (a) {
        a.setAttribute('href', window.mcBackUrl(a.getAttribute('href')));
        if (a.id === 'mcvBack') return; // 위 막대 ← 는 js/viewer-bar.js 가 처리
        a.addEventListener('click', function (e) {
          if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return; // 새 탭은 그대로
          e.preventDefault(); window.mcBackGo(a.getAttribute('href'), 'push'); // 예전처럼 링크 이동(기록 남김) — 바로 앞 기록이면 뒤로
        });
      });
    } catch (e) {}
  });
})();
</script>
JS;
    }
}

/**
 * ★ (2026-11-02 16:21) 서재·작품·즐겨찾기 화면 스크롤 위치 기억(★ 2026-11-02 17:17 function.php 로 옮김 — 즐겨찾기도 씀) — 뒤로가기·새로고침·다른 탭/앱에 갔다 와서(사생활 보호 화면을 거쳐) 다시 열려도 보던 자리로
 *   쪽 번호는 주소(page=)에 있어 그대로. 위치는 이 탭의 sessionStorage(주소별) + 사생활 보호 복귀 주소의 #ly<픽셀>(목록 화면과 같은 방식)
 *   새로 들어온 경우(링크로 이동)는 맨 위 그대로. 사용자가 스크롤하면 맞추기를 멈춤
 */
function mc_lib_scroll_script() {
    return <<<'JS'
<script>
(function () {
  function KEY() { return 'mc_lib_y|' + location.pathname + location.search; } // 주소가 바뀌는 화면(즐겨찾기 쪽 번호)도 맞게 그때그때
  function cur() { return window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0; }
  function save() { try { sessionStorage.setItem(KEY(), String(Math.round(cur()))); } catch (e) {} }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') save(); });
  window.addEventListener('pagehide', save);
  window.myComixCurrentHash = function () { var y = Math.round(cur()); return y > 0 ? ('#ly' + y) : ''; };
  function navType() { try { var e = performance.getEntriesByType('navigation'); return (e && e[0] && e[0].type) || ''; } catch (x) { return ''; } }
  var m = (location.hash || '').match(/^#ly(\d+)$/), target = -1;
  if (m) { target = parseInt(m[1], 10) || 0; try { history.replaceState(history.state, '', location.pathname + location.search); } catch (e) {} }
  else if (/^(back_forward|reload)$/.test(navType())) { try { var v = sessionStorage.getItem(KEY()); if (v !== null) target = parseInt(v, 10) || 0; } catch (e) {} }
  if (!(target > 0)) return;
  var stop = false, n = 0, setY = -1;
  function onUser() { stop = true; }
  window.addEventListener('wheel', onUser, { passive: true });
  window.addEventListener('touchmove', onUser, { passive: true });
  window.addEventListener('keydown', onUser, true);
  // ★ (2026-11-02 23:08) ▲ 단추·아이폰 상태 표시줄 탭(맨 위로)도 알아챔 — 터치 이동·휠이 없어 복원이 제자리로 계속 끌어당겼음
  window.addEventListener('pointerdown', onUser, { passive: true });
  window.addEventListener('touchstart', onUser, { passive: true });
  function onScr() { if (setY >= 0 && Math.abs(cur() - setY) > 8) stop = true; }
  window.addEventListener('scroll', onScr, { passive: true });
  var iv = setInterval(function () {
    if (stop || ++n > 60) { clearInterval(iv); window.removeEventListener('scroll', onScr); return; }
    if (Math.abs(cur() - target) > 3) { window.scrollTo(0, target); setY = cur(); } else if (n > 8) { clearInterval(iv); window.removeEventListener('scroll', onScr); }
  }, 50);
})();
</script>
JS;
}


/**
 * ★ (2026-11-03 01:31) 서재·즐겨찾기·북마크 화면 사이 이동 버튼 줄 — $cur = 'lib' | 'fav' | 'bm' (지금 화면은 눌린 모양, 링크 아님)
 *  서재는 PDO SQLite 가 있을 때만(목록 화면 위 서재 아이콘과 같은 조건)
 */
function mc_shelf_nav($cur, $bidx_query = '') {
    $q = h((string)$bidx_query);
    $star = '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>';
    $tabs = [];
    if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $tabs[] = ['lib', 'browse.php', __('lib_browse'), '<rect width="8" height="18" x="3" y="3" rx="1"/><path d="M7 3v18"/><path d="M20.4 18.9c.2.5-.1 1.1-.6 1.3l-1.9.7c-.5.2-1.1-.1-1.3-.6L11.1 5.1c-.2-.5.1-1.1.6-1.3l1.9-.7c.5-.2 1.1.1 1.3.6Z"/>'];
    }
    $tabs[] = ['fav', 'favorites.php', __('fav_shelf_title'), $star];
    $tabs[] = ['bm', 'bookmarks.php', __('index_bookmarks'), '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>'];
    $out = '<style>.mc-shelf-nav{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 12px}'
         . '.mc-shelf-nav a{display:inline-flex;align-items:center;gap:5px;height:32px;padding:0 12px;border:1px solid #dcdcdc;border-radius:16px;background:#fff;color:#444 !important;font-size:.85rem;text-decoration:none !important;white-space:nowrap}'
         . '.mc-shelf-nav a:hover{background:#f3f3f3}'
         . '.mc-shelf-nav a.on{background:#1a73e8;border-color:#1a73e8;color:#fff !important;font-weight:600;pointer-events:none}'
         . '.mc-shelf-nav svg{flex:0 0 auto}.mc-shelf-nav a.t-fav:not(.on) svg{color:#f59f00}'
         . '.dark-mode .mc-shelf-nav a,[data-theme="dark"] .mc-shelf-nav a{background:#1e1e1e;border-color:#444;color:#ccc !important}'
         . '.dark-mode .mc-shelf-nav a.on,[data-theme="dark"] .mc-shelf-nav a.on{background:#1a73e8;border-color:#1a73e8;color:#fff !important}'
         . '.dark-mode .mc-shelf-nav a.t-fav:not(.on) svg,[data-theme="dark"] .mc-shelf-nav a.t-fav:not(.on) svg{color:#fbbf24}</style>';
    $out .= '<nav class="mc-shelf-nav">';
    foreach ($tabs as $t) {
        $on = ($t[0] === $cur);
        $out .= '<a class="t-' . $t[0] . ($on ? ' on' : '') . '" href="' . $t[1] . $q . '"' . ($on ? ' aria-current="page"' : '') . '>'
              . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $t[3] . '</svg>'
              . h($t[2]) . '</a>';
    }
    return $out . '</nav>';
}


// ============================================================
// ★ (2026-11-03 14:10) 속도 기록 — src/perf/날짜.log 에 한 줄씩(JSON) (★ 2026-11-03 22:14 cache/perf → src/perf: cache 는 계정별 임시 압축 풀기 전용). 관리자 설정 '속도 기록 남기기'(기본 켬)
//   s(서버): 페이지·폴더·캐시 상태·단계별 시간(목록 만들기·카드 그리기)·전체 생성 시간·기기
//   c(브라우저): 같은 요청 번호(rid)로, 받기 시작까지(TTFB)·화면 그림 완료·전체 로드·받은 크기·연결 종류(안드로이드 크롬만 — 아이폰 사파리는 연결 정보를 주지 않음)
//   src/perf 는 src/.htaccess 로 웹에서 막혀 있고(.log), 하루 파일 20MB·14일 지나면 지움
// ============================================================
function mc_perf_enabled() {
    // ★ (2026-11-04 07:42) src/perf 폴더가 있을 때만 기록(펜닐) — 폴더를 만들면 시작, 지우면 멈춤. 폴더를 자동으로 만들지 않음
    static $dir_ok = null;
    if ($dir_ok === null) $dir_ok = is_dir(__DIR__ . '/src/perf');
    if (!$dir_ok) return false;
    return function_exists('get_app_settings') ? ((string)get_app_settings('perf_log', '1') !== '0') : true;
}
function mc_perf_rid() {
    static $rid = null;
    if ($rid === null) { try { $rid = bin2hex(random_bytes(6)); } catch (\Throwable $e) { $rid = substr(md5(uniqid('', true)), 0, 12); } }
    return $rid;
}
function mc_perf_device($ua) {
    $ua = (string)$ua; $l = strtolower($ua);
    $dev = (strpos($l, 'ipad') !== false || (strpos($l, 'android') !== false && strpos($l, 'mobile') === false)) ? 'tablet'
         : ((strpos($l, 'mobile') !== false || strpos($l, 'iphone') !== false || strpos($l, 'android') !== false) ? 'mobile' : 'pc');
    $os = strpos($l, 'iphone') !== false || strpos($l, 'ipad') !== false ? 'ios' : (strpos($l, 'android') !== false ? 'android' : (strpos($l, 'windows') !== false ? 'windows' : (strpos($l, 'mac os') !== false ? 'mac' : 'other')));
    $br = strpos($l, 'edg/') !== false ? 'edge' : (strpos($l, 'samsungbrowser') !== false ? 'samsung' : (strpos($l, 'crios') !== false || strpos($l, 'chrome') !== false ? 'chrome' : (strpos($l, 'firefox') !== false || strpos($l, 'fxios') !== false ? 'firefox' : (strpos($l, 'safari') !== false ? 'safari' : 'other'))));
    return [$dev, $os, $br];
}
function mc_perf_write(array $rec) {
    if (!mc_perf_enabled()) return;
    $dir = __DIR__ . '/src/perf'; // ★ (2026-11-03 22:14) cache 밖으로
    if (!is_dir($dir)) return; // ★ (2026-11-04 07:42) 폴더가 없으면 남기지 않음(만들지도 않음)
    $f = $dir . '/' . date('Y-m-d') . '.log';
    if (!is_file($f)) { // 새 날 — 14일 지난 파일 정리
        foreach ((@scandir($dir) ?: []) as $n) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})\.log$/', $n, $m) && strtotime($m[1]) < time() - 14 * 86400) @unlink($dir . '/' . $n);
        }
    } elseif ((int)@filesize($f) > 20 * 1024 * 1024) return; // 하루 20MB 넘으면 그날은 그만
    $rec = ['t' => date('Y-m-d H:i:s')] + $rec;
    @file_put_contents($f, json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n", FILE_APPEND | LOCK_EX);
}
/** 페이지 끝에서 부름: 서버 기록(요청이 끝날 때 전체 시간 포함)을 남기고, 브라우저 쪽 측정을 보내는 짧은 스크립트를 돌려줌 */
function mc_perf_script($page, array $extra = []) {
    if (!mc_perf_enabled() || empty($_SESSION['user_id'])) return '';
    $rid = mc_perf_rid();
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    list($dev, $os, $br) = mc_perf_device($ua);
    $base = ['k' => 's', 'rid' => $rid, 'page' => (string)$page, 'user' => preg_replace('/[^a-zA-Z0-9_]/', '', (string)$_SESSION['user_id']),
             'dev' => $dev, 'os' => $os, 'br' => $br, 'uri' => substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 300)] + $extra;
    if (!empty($_SERVER['HTTP_X_MC_REFRESH'])) $base['xr'] = 1; // ★ (2026-11-03 20:58) 뒤로가기 뒤 화면이 몰래 다시 받은 요청(브라우저 측정 짝 없음) — 통계에서 거를 수 있게
    $t0 = (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    register_shutdown_function(function () use ($base, $t0, $ua) {
        $base['ms'] = round((microtime(true) - $t0) * 1000, 1); // 서버 전체(출력 끝날 때까지)
        $base['mem_mb'] = round(memory_get_peak_usage(true) / 1048576, 1);
        $base['ua'] = substr($ua, 0, 200);
        mc_perf_write($base);
    });
    return '<script>(function(){var R=' . json_encode($rid) . ',P=' . json_encode((string)$page) . ';function send(){try{var n=(performance.getEntriesByType&&performance.getEntriesByType("navigation")[0])||null;if(!n)return;var c=navigator.connection||navigator.mozConnection||navigator.webkitConnection||{};var d={rid:R,page:P,nav:n.type||"",ttfb:Math.round(n.responseStart),dl:Math.round(n.responseEnd-n.responseStart),dom:Math.round(n.domInteractive),dcl:Math.round(n.domContentLoadedEventEnd),load:Math.round(n.loadEventEnd),kb:Math.round((n.transferSize||0)/1024),body_kb:Math.round((n.encodedBodySize||0)/1024),net:c.type||"",eff:c.effectiveType||"",down:c.downlink||0,rtt:c.rtt||0,sw:screen.width||0,img:document.images?document.images.length:0};var b=JSON.stringify(d);if(!(navigator.sendBeacon&&navigator.sendBeacon("perf_log.php",new Blob([b],{type:"text/plain"})))){var x=new XMLHttpRequest();x.open("POST","perf_log.php",true);x.send(b);}}catch(e){}}if(document.readyState==="complete")setTimeout(send,300);else window.addEventListener("load",function(){setTimeout(send,300);});})();</script>';
}


/* ============================================================
 * ★ (2026-11-08 fix80, 펜닐 요청) 동영상·PDF·디지털도서 폴더 대표 이미지([cover].jpg)
 *   압축 책 폴더만 표지가 생겼음(첫 책의 첫 그림) — 동영상·PDF·EPUB·TXT·한글·오피스만 있는 폴더는 표지가 없었음
 *   · 자동: 압축 책이 없는 폴더는 이름순 첫 파일부터 쓸 그림을 찾음
 *       동영상 → 그 영상 썸네일(*.video_thumb.jpg, 없으면 ffmpeg 로 만듦) → 같은 이름 그림
 *       문서·책 → 같은 이름 그림(책.jpg·책.pdf.jpg 등 — 목록 카드와 같은 규칙) → 파일 안 표지(EPUB 표지·HWPX 미리보기·오피스 썸네일)
 *   · 이미 [cover].jpg 가 있으면(직접 넣은 표지 포함) 절대 덮어쓰지 않음 — 바꾸려면 뷰어의 관리자 단추로 직접 지정
 * ============================================================ */
if (!function_exists('mc_book_sidecar_img')) {
/** 책·영상 옆 같은 이름 그림(카드·thumb.php 와 같은 순서) — 없으면 '' */
function mc_book_sidecar_img($path) {
    $path = (string)$path;
    $base = preg_replace('/\.[^.\/\\\\]+$/', '', $path);
    foreach (['.jpg', '.jpeg', '.png', '.webp'] as $e) { if ($base !== $path && is_file($base . $e)) return $base . $e; }
    foreach (['.jpg', '.jpeg', '.png', '.webp'] as $e) { if (is_file($path . $e)) return $path . $e; }
    return '';
}
}
if (!function_exists('mc_doc_embedded_cover')) {
/** 파일 안에 든 표지 그림(EPUB 표지·HWPX 미리보기·DOCX/XLSX/PPTX 썸네일) — 자료(바이트) 또는 false. XML 은 정규식으로만 읽음(외부 개체 처리 없음), 15MB 넘는 그림은 안 읽음 */
function mc_doc_embedded_cover($path) {
    if (!class_exists('ZipArchive') || !is_file($path)) return false;
    $ext = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['epub', 'hwpx', 'docx', 'xlsx', 'pptx'], true)) return false;
    $z = new ZipArchive;
    if ($z->open($path) !== true) return false;
    $read = function ($name) use ($z) {
        $st = @$z->statName($name);
        if (!$st || (int)$st['size'] <= 0 || (int)$st['size'] > 15 * 1048576) return false;
        $d = $z->getFromName($name);
        return (is_string($d) && $d !== '') ? $d : false;
    };
    $out = false;
    if ($ext === 'hwpx') {
        $out = $read('Preview/PrvImage.png');
    } elseif ($ext !== 'epub') {
        $out = $read('docProps/thumbnail.jpeg');
        if ($out === false) $out = $read('docProps/thumbnail.png');
    } else {
        $opf = '';
        $c = $read('META-INF/container.xml');
        if ($c !== false && preg_match('/full-path\s*=\s*["\']([^"\']+)["\']/i', $c, $m)) $opf = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        $x = ($opf !== '') ? $read($opf) : false;
        if (is_string($x)) {
            $dir = (strpos($opf, '/') !== false) ? substr($opf, 0, strrpos($opf, '/') + 1) : '';
            $byId = []; $href = '';
            if (preg_match_all('/<(?:[\w-]+:)?item\b[^>]*>/i', $x, $items)) {
                foreach ($items[0] as $it) {
                    $a = [];
                    if (preg_match_all('/([\w:.-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/', $it, $mm, PREG_SET_ORDER)) {
                        foreach ($mm as $q) $a[strtolower($q[1])] = html_entity_decode(($q[2] !== '') ? $q[2] : ($q[3] ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                    if (!isset($a['href'])) continue;
                    if (isset($a['id'])) $byId[$a['id']] = $a;
                    if ($href === '' && isset($a['properties']) && preg_match('/(^|\s)cover-image(\s|$)/', $a['properties'])) $href = $a['href'];
                }
            }
            if ($href === '' && preg_match_all('/<(?:[\w-]+:)?meta\b[^>]*>/i', $x, $metas)) {
                foreach ($metas[0] as $mt) {
                    if (preg_match('/\bname\s*=\s*["\']cover["\']/i', $mt) && preg_match('/\bcontent\s*=\s*["\']([^"\']+)["\']/i', $mt, $cm) && isset($byId[$cm[1]])) { $href = $byId[$cm[1]]['href']; break; }
                }
            }
            if ($href === '') {
                foreach ($byId as $id => $a) {
                    if (preg_match('/^image\//i', (string)($a['media-type'] ?? '')) && preg_match('/cover/i', $id . ' ' . $a['href'])) { $href = $a['href']; break; }
                }
            }
            if ($href !== '' && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
                $parts = [];
                foreach (explode('/', $dir . rawurldecode(preg_replace('/#.*$/', '', $href))) as $seg) {
                    if ($seg === '' || $seg === '.') continue;
                    if ($seg === '..') { array_pop($parts); continue; }
                    $parts[] = $seg;
                }
                if ($parts) $out = $read(implode('/', $parts));
            }
        }
    }
    $z->close();
    return $out;
}
}
if (!function_exists('mc_write_folder_cover')) {
/** 그림 자료를 폴더의 [cover].jpg 로(JPEG·높이 최대 800px·투명은 흰 바탕) — 임시 파일에 쓴 뒤 바꿔 넣음. 성공 true */
function mc_write_folder_cover($dir, $data) {
    $dir = rtrim((string)$dir, '/\\');
    if ($dir === '' || !is_dir($dir) || !is_string($data) || $data === '') return false;
    $img = mc_gd_from_string($data);
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    if ($w < 1 || $h < 1) { imagedestroy($img); return false; }
    $nh = min($h, 800); $nw = max(1, (int)round($w * $nh / $h));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    $dest = $dir . '/[cover].jpg';
    $tmp = $dir . '/.mc_cover_' . bin2hex(random_bytes(4)) . '.tmp';
    $ok = @imagejpeg($out, $tmp, 85);
    imagedestroy($out);
    if (!$ok || !is_file($tmp) || filesize($tmp) < 100) { @unlink($tmp); return false; }
    if (!@rename($tmp, $dest)) { // Windows: 있는 파일 위로는 이름 바꾸기가 안 됨
        if (!@copy($tmp, $dest)) { @unlink($tmp); return false; }
        @unlink($tmp);
    }
    @chmod($dest, 0644);
    return true;
}
}
if (!function_exists('mc_media_cover_pick')) {
/**
 * 압축 책이 없는 폴더에서 표지로 쓸 그림 찾기
 *   null  = 해당 없음(압축 책이 있음 — 예전 압축 표지 방식이 맡음 / 동영상·문서가 없음)
 *   false = 동영상·문서는 있는데 쓸 그림이 없음
 *   ['file' => 그림 경로] 또는 ['data' => 그림 자료]
 */
function mc_media_cover_pick($dir, $allow_ffmpeg = true) {
    $dir = rtrim((string)$dir, '/\\');
    $names = @scandir($dir);
    if (!$names) return null;
    $media = [];
    foreach ($names as $n) {
        if ($n === '.' || $n === '..' || $n === '' || $n[0] === '.') continue;
        if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $n)) return null;
        if (is_video_file($n) || preg_match('/\.(pdf|epub|txt|hwpx?|docx?|xlsx?|pptx?)$/i', $n)) $media[] = $n;
    }
    if (!$media) return null;
    usort($media, function ($a, $b) { return strnatcasecmp($a, $b); });
    $ff_try = $allow_ffmpeg; // ffmpeg 로 썸네일 만들기는 한 폴더에 한 번만(깨진 영상이 많아도 영상마다 돌리지 않게)
    foreach ($media as $n) {
        $p = $dir . '/' . $n;
        if (!is_file($p)) continue;
        if (is_video_file($n)) {
            $t = $p . '.video_thumb.jpg';
            if (!is_file($t) && $ff_try) {
                $ff_try = false;
                $ff = (string)($GLOBALS['ffmpeg_path'] ?? '');
                if ($ff !== '' && is_file($ff) && function_exists('generate_video_thumbnail')) generate_video_thumbnail($p, $ff, 5);
            }
            if (is_file($t) && filesize($t) > 0) return ['file' => $t];
            $sc = mc_book_sidecar_img($p);
            if ($sc !== '') return ['file' => $sc];
        } else {
            $sc = mc_book_sidecar_img($p);
            if ($sc !== '') return ['file' => $sc];
            $d = mc_doc_embedded_cover($p);
            if ($d !== false) return ['data' => $d];
        }
    }
    return false;
}
}
if (!function_exists('mc_media_file_cover_data')) {
/** ★ (2026-11-08 fix80) 동영상·PDF·디지털도서 한 파일에서 표지로 쓸 그림 자료(뷰어의 '대표 이미지로' — 압축 책의 첫 그림에 해당). 없으면 false
 *   동영상 → 영상 썸네일(없으면 ffmpeg 로 만듦) → 같은 이름 그림 / 문서·책 → 같은 이름 그림 → 파일 안 표지 */
function mc_media_file_cover_data($path) {
    $path = (string)$path;
    if (!is_file($path)) return false;
    $n = basename($path);
    $file = '';
    if (is_video_file($n)) {
        $t = $path . '.video_thumb.jpg';
        if (!is_file($t)) {
            $ff = (string)($GLOBALS['ffmpeg_path'] ?? '');
            if ($ff !== '' && is_file($ff) && function_exists('generate_video_thumbnail')) generate_video_thumbnail($path, $ff, 5);
        }
        if (is_file($t) && filesize($t) > 0) $file = $t;
        if ($file === '') $file = mc_book_sidecar_img($path);
    } elseif (preg_match('/\.(pdf|epub|txt|hwpx?|docx?|xlsx?|pptx?)$/i', $n)) {
        $file = mc_book_sidecar_img($path);
        if ($file === '') return mc_doc_embedded_cover($path);
    } else {
        return false;
    }
    if ($file === '') return false;
    $d = @file_get_contents($file);
    return (is_string($d) && $d !== '') ? $d : false;
}
}
if (!function_exists('mc_doc_cover_btn')) {
/** ★ (2026-11-08 fix80, 펜닐 요청) EPUB·TXT·오피스·한글 뷰어의 '이 책 표지를 폴더 표지로' 단추(관리자만) — bookmark.php?mode=set_cover(삭제 토큰)
 *   쓸 그림: 같은 이름 그림(책.jpg 등) → 파일 안 표지(EPUB 표지·HWPX 미리보기·오피스 썸네일). 없으면 '표지로 쓸 그림이 없습니다' */
function mc_doc_cover_btn($getfile, $bidx, $class = '') {
    if (trim((string)($_SESSION['user_group'] ?? '')) !== 'admin' || !function_exists('get_delete_token')) return '';
    $url = 'bookmark.php?mode=set_cover&file=' . encode_url((string)$getfile) . '&bidx=' . (int)$bidx . '&token=' . urlencode((string)get_delete_token());
    $lbl = __h('doc_cover_btn');
    return '<button type="button" class="' . h($class) . '" title="' . $lbl . '" aria-label="' . $lbl . '" data-url="' . h($url) . '" onclick="mcDocCover(this)">🖼️</button>'
        // ★ (2026-11-08 fix81) 원래 모양은 innerHTML 로 되돌림 — 아이콘 스크립트(mc-icons.js)가 🖼️ 를 SVG 로 바꿔 글자(textContent)가 비어, 누른 뒤 단추가 빈칸이 되던 것
        . '<script>window.mcDocCover=window.mcDocCover||function(b){if(b.disabled)return;var h0=b.innerHTML,l0=b.title;b.disabled=true;b.textContent="…";'
        . 'fetch(b.getAttribute("data-url"),{credentials:"same-origin"}).then(function(r){return r.text().then(function(t){b.textContent=r.ok?"✓":"✕";b.title=String(t||"").slice(0,80);});})'
        . '.catch(function(){b.textContent="✕";}).then(function(){setTimeout(function(){b.innerHTML=h0;b.title=l0;b.disabled=false;},2500);});};</script>';
}
}
if (!function_exists('mc_media_folder_cover_make')) {
/** 동영상·문서 폴더 표지를 만듦 — 이미 [cover].jpg 가 있으면 그대로. 만들었으면 true */
function mc_media_folder_cover_make($dir, $allow_ffmpeg = true) {
    $dir = rtrim((string)$dir, '/\\');
    if ($dir === '' || is_file($dir . '/[cover].jpg')) return false;
    $pick = mc_media_cover_pick($dir, $allow_ffmpeg);
    if (!is_array($pick)) return false;
    $data = isset($pick['data']) ? $pick['data'] : @file_get_contents($pick['file']);
    return is_string($data) && mc_write_folder_cover($dir, $data);
}
}
if (!function_exists('mc_media_covers_all')) {
/** 콘텐츠 폴더 아래 표지 없는 동영상·문서 폴더에 표지를 만듦(배경 갱신용 — 한 번에 $limit 곳까지). 만든 수 */
function mc_media_covers_all($base, $limit = 20, $allow_ffmpeg = true) {
    $base = rtrim((string)$base, '/\\');
    if ($base === '' || !is_dir($base)) return 0;
    $dirs = [];
    try {
        $it = new RecursiveIteratorIterator(mc_rdi($base), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $n = $f->getFilename();
            if (is_video_file($n) || preg_match('/\.(pdf|epub|txt|hwpx?|docx?|xlsx?|pptx?)$/i', $n)) $dirs[dirname($f->getPathname())] = true;
        }
    } catch (\Throwable $e) {}
    // 쓸 그림이 없던 폴더는 그 폴더가 바뀔 때(파일 추가 등 — 폴더 시각)까지 다시 열어 보지 않음(src/media_cover_none.json)
    $memo_f = __DIR__ . '/src/media_cover_none.json';
    $memo = is_file($memo_f) ? json_decode((string)@file_get_contents($memo_f), true) : [];
    if (!is_array($memo)) $memo = [];
    $memo0 = $memo;
    $__ff = (string)($GLOBALS['ffmpeg_path'] ?? '');
    $ffk = ($allow_ffmpeg && $__ff !== '' && is_file($__ff)) ? 'f' : 'n'; // ffmpeg 를 나중에 설정하면 동영상 폴더를 다시 봄
    $made = 0; $tries = 0;
    foreach (array_keys($dirs) as $d) {
        if ($made >= $limit || $tries >= $limit * 2) break; // 쓸 그림이 없는 폴더를 매번 끝없이 다시 보지 않게 시도 수도 제한
        if (rtrim($d, '/\\') === $base) continue; // 콘텐츠 폴더 맨 위는 카드가 없음
        if (is_file($d . '/[cover].jpg')) { unset($memo[$d]); continue; }
        if (isset($memo[$d]) && (string)$memo[$d] === (int)@filemtime($d) . '|' . $ffk) continue;
        $tries++;
        if (mc_media_folder_cover_make($d, $allow_ffmpeg)) { $made++; unset($memo[$d]); }
        else { clearstatcache(true, $d); $memo[$d] = (int)@filemtime($d) . '|' . $ffk; }
    }
    foreach (array_keys($memo) as $k) { if (strpos((string)$k, $base) === 0 && !isset($dirs[$k])) unset($memo[$k]); } // 없어진 폴더 정리
    if ($memo !== $memo0) @file_put_contents($memo_f, json_encode($memo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
    return $made;
}
}

?>
