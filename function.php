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
    // ★ (2026-11-10 23:54 fix108) 경로에 널 문자(%00)가 있으면 거절 — 아래 realpath() 가 예외를 던져 뷰어들이 500(치명 오류)으로 끝났음(11-09 링크 확인을 넣은 뒤부터). 그런 이름의 파일은 있을 수 없음
    if (strpos($getdir, "\0") !== false) return false;
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
//     ★ (2026-11-15 19:06 fix175) 지금은 책마다 위치 기록 src/<아이디>_vpos.json 이 먼저('이어 읽기' 목록은 개수 제한으로 밀려나 뷰어 이어 보기와 섞으면 안 됨 — 펜닐 제보), 없을 때만 위 기록
//   viewer.php(열 때)·index.php(카드 배지) 공용
// ============================================================
if (!function_exists('mc_mv_autosave_entry')) {
    function mc_mv_autosave_entry($getfile, $bidx) {
        // ★ (2026-11-15 19:01, 펜닐 제보 — fix175) 먼저 책마다 위치 기록(get_vpos_file — '이어 읽기' 목록과 따로, 개수 제한으로 밀려나지 않음)
        //   거기 없으면(나누기 전에 읽은 책) 예전처럼 '이어 읽기' 기록에서 — 전에는 이 기록만 봐서, 목록 최대 개수를 넘겨 밀려난 책은 1쪽부터 열렸음
        static $vpos = null;
        if ($vpos === null) {
            $vpos = [];
            $vf = function_exists('get_vpos_file') ? get_vpos_file() : '';
            if ($vf !== '' && is_file($vf)) { $vd = function_exists('load_json_with_lock') ? load_json_with_lock($vf) : json_decode((string)@file_get_contents($vf), true); if (is_array($vd)) $vpos = $vd; }
        }
        $__vk = (int)$bidx . '|/' . ltrim((string)$getfile, '/');
        if (isset($vpos[$__vk]) && is_array($vpos[$__vk])) { $ve = $vpos[$__vk]; $ve['bidx'] = (int)$bidx; return $ve; }
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
/**
 * ★ (2026-11-12 20:01, 펜닐 제보·속도 기록 — fix130) 뷰어 ← '찾아가기' + ← 기록(src/perf/back_날짜.log)
 *   [문제] 아이폰 크롬(WebKit)에서 탭을 나갔다 가림막(blank.php)을 거쳐 돌아오면 가끔 방문 기록 개수가 늘어(속도 기록: 18:56 복귀 → 18:57:07 작품 '새로 열기'
 *          → 18:57:08 같은 작품 '뒤로'), ← 가 '주소로 이동'을 써 같은 작품 화면이 기록에 두 번 쌓임 → 그 뒤 뒤로를 두 번 눌러야 했음. 크롬(PC 엔진)은 재현 안 됨
 *   [처리] 가림막에서 돌아온 뷰어는 들어온 곳 표시를 남겨 두고(아래 mc_back_origin_viewer_js), ← 때 기록 개수가 늘었으면 브라우저 뒤로를 이어서
 *          들어온 화면을 찾아감(mc_back_seek — 중간의 뷰어·가림막 화면만 지나감, 최대 4번·8초). 못 찾으면 그 주소로 이동(기록을 지우지 않게 '열기')
 *   [기록] 속도 기록이 켜져 있으면(src/perf 폴더) 뷰어 열림·← 누름·찾아가기·가림막 오감을 perf_log.php 로 보냄(로그인한 사람만, 정해진 값만)
 */
/** ★ (2026-11-12 21:19, 펜닐 요청 — fix131) 목록 보기 정보 줄 스크립트(js/mc-list-info.js) — 서재 DB 가 있을 때만(없으면 빈 문자열) */
if (!function_exists('mc_list_info_script')) {
    function mc_list_info_script() {
        if (!is_file(__DIR__ . '/src/library.db') || empty($_SESSION['user_id'])) return '';
        $t = json_encode(['pages' => function_exists('__') ? __('lib_pages') : '%d', // ★ (2026-11-13 00:14 fix133) 종류 이름(작가·그룹·장르·태그) — 작품 상세 정보 표와 같은 말
            'a' => __('lib_author'), 'g' => __('lib_group'), 'n' => __('lib_genre'), 't' => __('lib_tag')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return '<script>window.mcLiT=' . $t . ';</script><script src="js/mc-list-info.js?v=' . (int)@filemtime(__DIR__ . '/js/mc-list-info.js') . '"></script>';
    }
}
/**
 * ★ (2026-11-12 23:11, 펜닐 제보 — fix132) '← 돌아가기' = 지금 화면의 상위로(브라우저 뒤로처럼 지나온 순서대로가 아니라)
 *   [문제] 서재 → 작품 → '같은 작가의 다른 작품' → ← 가 앞 작품으로(브라우저 뒤로), 폴더 → 즐겨찾기 → 북마크 탭 → ← 가 폴더가 아닌 목록 첫 화면(index.php?bidx=N)으로 갔음
 *   [처리] 화면마다 '상위 화면까지 몇 칸 뒤인지'(방문 기록 칸마다 history.state.mcUp)를 적어 두고 ← 는 그만큼 뒤로(그 화면 그대로·스크롤 위치·기록 안 쌓임)
 *     · 서재·즐겨찾기·북마크(kind 'I')의 상위 = 들어오기 전 파일 목록(폴더) — 서로 탭으로 옮겨 다녀도·작품 화면을 거쳐도 그 폴더
 *     · 작품 화면(kind 'L')의 상위 = 처음 들어온 목록(서재·즐겨찾기·북마크·폴더) — 같은 작가 작품으로 옮겨 다녀도 그 목록
 *     · 칸 수를 모르는 경우(뷰어에서 주소로 돌아옴·가림막을 거쳐 옴·새 탭 등)는 그 상위 화면 주소로 이동(종전처럼 링크)
 *   파일 목록(kind '')은 적기만 함 — 바로 앞 화면이 무엇이었는지 알 수 있게(mc_up_last)
 */
if (!function_exists('mc_up_back_js')) {
    function mc_up_back_js($btn_id = '', $kind = '') {
        $cfg = json_encode(['b' => (string)$btn_id, 'k' => (string)$kind], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return '<script>window.mcUpCfg=' . $cfg . ';</script>' . <<<'JS'
<script>
(function () {
  var C = window.mcUpCfg || {}; window.mcUpCfg = null;
  var PG = /^(index|browse|favorites|bookmarks|series)\.php$/, LU = /^(index|browse|favorites|bookmarks)\.php(\?[^#\s]*)?$/;
  function pg(p) { return String(p || '').split('/').pop() || 'index.php'; }
  function g(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function s(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }
  var me = pg(location.pathname);
  function cur() { return me + location.search; }
  var hs = history.state, st = (hs && typeof hs === 'object') ? hs : {}, up = st.mcUp;
  if (!up || typeof up !== 'object') {
    up = { i: 0, l: 0, ui: '', ul: '' };
    var nt = ''; try { var en = performance.getEntriesByType('navigation'); nt = (en && en[0] && en[0].type) || ''; } catch (x) {}
    var last = null; try { last = JSON.parse(g('mc_up_last') || 'null'); } catch (e) {}
    var ref = null; try { ref = document.referrer ? new URL(document.referrer) : null; } catch (e) {}
    // 바로 앞 화면이 링크로 열어 준 경우만 믿음(앞 화면이 남긴 주소 = 이 화면의 referrer)
    if (nt === 'navigate' && last && ref && ref.origin === location.origin && PG.test(String(last.p)) && pg(ref.pathname) + ref.search === last.u) {
      if (last.p === me && last.u === cur()) { up = { i: +last.i || 0, l: +last.l || 0, ui: String(last.ui || ''), ul: String(last.ul || '') }; } // 같은 주소로 다시 열림(브라우저가 기록을 바꿔치기) = 칸 수 그대로
      else if (last.p === 'index.php') { up = { i: 1, l: 1, ui: last.u, ul: last.u }; }
      else if (last.p === 'series.php') { up = { i: last.i > 0 ? last.i + 1 : 0, l: last.l > 0 ? last.l + 1 : 0, ui: String(last.ui || ''), ul: String(last.ul || '') }; }
      else { up = { i: last.i > 0 ? last.i + 1 : 0, l: 1, ui: String(last.ui || ''), ul: last.u }; }
    }
    if (me !== 'index.php') { try { history.replaceState(Object.assign({}, st, { mcUp: up }), ''); } catch (e) {} }
  }
  if (LU.test(up.ui || '')) s('mc_up_ui', up.ui);
  if (LU.test(up.ul || '')) s('mc_up_ul', up.ul);
  function save() { s('mc_up_last', JSON.stringify({ p: me, u: cur(), i: +up.i || 0, l: +up.l || 0, ui: up.ui || '', ul: up.ul || '' })); }
  document.addEventListener('click', save, true);
  window.addEventListener('pagehide', save);
  window.addEventListener('pageshow', function (e) { if (e.persisted) save(); });
  var btn = C.b ? document.getElementById(C.b) : null;
  if (!btn || (C.k !== 'I' && C.k !== 'L')) return;
  var d = C.k === 'L' ? (+up.l || 0) : (+up.i || 0);
  var u = C.k === 'L' ? (up.ul || g('mc_up_ul')) : (up.ui || g('mc_up_ui'));
  if (C.k === 'I' && !/^index\.php/.test(u || '')) u = '';
  if (u && LU.test(u)) btn.setAttribute('href', u);
  btn.addEventListener('click', function (ev) {
    if (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button === 1) return; // 새 탭은 링크 그대로
    if (d > 0 && history.length > d) { ev.preventDefault(); history.go(-d); }
  });
})();
</script>
JS;
    }
}
if (!function_exists('mc_back_seek_js')) {
    function mc_back_seek_js() {
        $on = (function_exists('mc_perf_enabled') && mc_perf_enabled() && !empty($_SESSION['user_id'])) ? 1 : 0;
        return '<script>window.mcBackLogOn=' . $on . ';</script>' . <<<'JS'
<script>
(function () {
  if (window.mcBackSeekReady) return; window.mcBackSeekReady = 1;
  function g(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function d(k) { try { sessionStorage.removeItem(k); } catch (e) {} }
  function pg() { return location.pathname.split('/').pop() || 'index.php'; }
  window.mcBackLog = function (ev, o) {
    if (!window.mcBackLogOn) return;
    try {
      var r = { k: 'b', ev: String(ev), page: pg(), len: history.length };
      for (var x in (o || {})) r[x] = o[x];
      var b = JSON.stringify(r);
      if (navigator.sendBeacon) navigator.sendBeacon('perf_log.php', b);
      else { var q = new XMLHttpRequest(); q.open('POST', 'perf_log.php', true); q.send(b); }
    } catch (e) {}
  };
  // 찾아가기: ← 가 남긴 mc_back_seek = {u: 찾는 화면(주소), t: 시각, n: 지나간 수, fb: 못 찾으면 열 주소}
  function chk() {
    var sk = null; try { sk = JSON.parse(g('mc_back_seek') || 'null'); } catch (e) {}
    if (!sk || !sk.u) return;
    if (!(Date.now() - (+sk.t || 0) < 8000)) { d('mc_back_seek'); return; }
    var here = pg() + location.search;
    if (here === sk.u) { d('mc_back_seek'); window.mcBackLog('seek_arrive', { n: sk.n }); return; }
    if (/^(viewer|epub_viewer|txt_viewer|office_viewer|hwp_viewer|rhwp_viewer|blank)\.php$/.test(pg()) && (+sk.n || 0) < 4) {
      sk.n = (+sk.n || 0) + 1; sk.t = Date.now();
      try { sessionStorage.setItem('mc_back_seek', JSON.stringify(sk)); } catch (e) {}
      window.mcBackLog('seek_step', { n: sk.n });
      history.back();
      return;
    }
    d('mc_back_seek'); window.mcBackLog('seek_fallback', { n: sk.n });
    location.href = sk.fb;
  }
  window.addEventListener('pageshow', chk);
})();
</script>
JS;
    }
}
if (!function_exists('mc_back_origin_list_js')) {
    function mc_back_origin_list_js() {
        return (function_exists('mc_back_seek_js') ? mc_back_seek_js() : '') . <<<'JS'
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
        return (function_exists('mc_back_seek_js') ? mc_back_seek_js() : '') . <<<'JS'
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
    // ★ (2026-11-12 20:01 fix130) 가림막(blank.php)에서 방금 돌아온 경우(20초 안 — blank.php 가 'mycomix_curtain_return' 을 남김)는 기록 개수가 달라도 표시를 남기고
    //   '가림막을 거쳐 옴'(mc_back_curtain = 들어온 곳)을 적음 → ← 가 주소로 새로 열지 않고 뒤로를 이어 찾아감(mc_back_seek_js). 그 밖은 종전 그대로
    var cr = parseInt(g('mycomix_curtain_return') || '0', 10) || 0, fromCurtain = cr && Date.now() - cr >= 0 && Date.now() - cr < 20000;
    if (c && Date.now() - c >= 0 && Date.now() - c < 20000 && RE.test(o || '')) { s('mc_back_hist', bare(o) + '|' + history.length); d('mc_back_curtain'); }
    else { var h = g('mc_back_hist'); if (h && h.split('|').pop() !== String(history.length)) { if (fromCurtain) s('mc_back_curtain', h.slice(0, h.lastIndexOf('|'))); else d('mc_back_hist'); } }
  }
  d('mc_back_click');
  try { if (window.mcBackLog) window.mcBackLog('open', { nt: nt, hist: g('mc_back_hist') || '', cur: g('mc_back_curtain') ? 1 : 0 }); } catch (x) {}
  window.mcBackUrl = function (def) {
    var u = g('mc_back_origin');
    return (typeof u === 'string' && RE.test(u)) ? u : def;
  };
  // ★ (2026-11-12 13:49, 펜닐 제보 — fix125) 표지 저장(파일 표지·폴더 표지·자동)이 끝나기 전에 돌아가면 저장이 끊기거나, 저장은 됐어도 목록에 알림(mc_cover_bump)이
  //   안 남아 새로고침 전까지 예전 표지가 보였음(느린 회선에서 '설정중...' 뒤 바로 ← 로 재현). 저장 중이면 ← 는 끝날 때까지(최대 30초) 기다렸다가 가고,
  //   브라우저 뒤로·창 닫기는 떠나기 확인을 띄움(저장 중일 때만)
  var cvBusy = 0, cvWait = false;
  function cvLeave(e) { if (cvBusy > 0) { e.preventDefault(); e.returnValue = ''; return ''; } }
  function cvClear() { cvBusy = 0; window.removeEventListener('beforeunload', cvLeave); }
  window.mcCoverTrack = function (p) {
    if (!p || typeof p.then !== 'function') return p;
    if (cvBusy++ === 0) window.addEventListener('beforeunload', cvLeave);
    var f = function () { if (--cvBusy <= 0) cvClear(); };
    p.then(f, f);
    return p;
  };
  // 표지 알림 남기기 — 앞 알림을 지우지 않고 함께 둠(l: 1시간 안의 앞 알림 최대 20개). 종전엔 마지막 하나만 남아
  //   '파일 표지' 뒤 '폴더 표지'를 하고 돌아가면(뒤로 가기로 보관된 목록 화면) 파일 카드가 안 바뀌었음
  window.mcCoverBumpPut = function (n) {
    try {
      var o = null; try { o = JSON.parse(localStorage.getItem('mc_cover_bump') || 'null'); } catch (x) {}
      var l = [];
      if (o && o.t) l = (Array.isArray(o.l) ? o.l : []).concat([{ t: o.t, f: o.f, d: o.d, k: o.k }]).filter(function (x) { return x && x.t > n.t - 3600000; }).slice(-20);
      n.l = l;
      localStorage.setItem('mc_cover_bump', JSON.stringify(n));
    } catch (e) {}
  };
  // 들어온 곳으로 가기 — 바로 앞 기록이면 브라우저 뒤로(그 화면 그대로·기록 안 쌓임), 아니면 주소로(mode: 'replace' 기본 / 'push')
  window.mcBackGo = function (url, mode) {
    if (cvBusy > 0) {
      if (cvWait) return; cvWait = true;
      var n = 0, iv = setInterval(function () { if (cvBusy <= 0 || ++n > 300) { clearInterval(iv); cvClear(); cvWait = false; window.mcBackGo(url, mode); } }, 100);
      return;
    }
    var h = g('mc_back_hist');
    if (history.length > 1 && h && h === bare(url) + '|' + history.length && RE.test(String(url || ''))) { if (window.mcBackLog) window.mcBackLog('press', { how: 'back', hist: h }); d('mc_back_hist'); history.back(); return; }
    // ★ (2026-11-12 20:01 fix130) 가림막을 거쳐 와 기록 개수만 늘었으면(1~6) 뒤로를 이어 들어온 화면을 찾아감 — 주소로 새로 열면 같은 화면이 기록에 두 번 남았음
    if (history.length > 1 && h && RE.test(String(url || ''))) {
      var hk = h.lastIndexOf('|'), hu = h.slice(0, hk), gap = history.length - (parseInt(h.slice(hk + 1), 10) || 0);
      if (hu === bare(url) && gap > 0 && gap <= 6 && g('mc_back_curtain') === hu) {
        try { sessionStorage.setItem('mc_back_seek', JSON.stringify({ u: hu, t: Date.now(), n: 0, fb: url })); } catch (e) {}
        if (window.mcBackLog) window.mcBackLog('press', { how: 'seek', hist: h, gap: gap });
        d('mc_back_hist'); d('mc_back_curtain'); history.back(); return;
      }
    }
    if (window.mcBackLog) window.mcBackLog('press', { how: mode === 'push' ? 'push' : 'replace', hist: h || '', url: String(url || '').slice(0, 200) });
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

// ★ (2026-11-10 13:52, 펜닐 요청 — fix98) 파일 표지 직접 지정(관리자) — 고른 쪽을 src/covers/ 에 따로 둠. 자동 썸네일(json·영상 썸네일·같은 이름 그림)은 건드리지 않아
//   이 파일을 지우면 그대로 자동으로 돌아감. 목록 카드·thumb.php 가 이 파일이 있으면 먼저 씀(폴더 표지 [cover].jpg 와는 따로)
function mc_file_cover_path($abs) {
    $rp = @realpath((string)$abs);
    if ($rp === false) $rp = (string)$abs;
    $k = str_replace('\\', '/', rtrim($rp, '/\\'));
    if (DIRECTORY_SEPARATOR === '\\') $k = mb_strtolower($k, 'UTF-8'); // Windows 는 대소문자를 가리지 않음
    return __DIR__ . '/src/covers/' . md5($k) . '.jpg';
}
/** 카드 그림 주소에 붙일 '&cv=시각'(직접 지정한 표지가 없으면 '') — 바꾸면 주소가 바뀌어 브라우저가 옛 그림(하루 캐시)을 쓰지 않게 */
function mc_file_cover_v($abs) {
    // ★ (2026-11-10 16:08 fix101) 직접 고른 표지가 하나도 없으면(대부분) 경로 확인 없이 바로 '' — 목록·서재 카드마다 디스크를 보지 않게
    static $any = null;
    if ($any === null) { $any = false; $dh = is_dir(__DIR__ . '/src/covers') ? @opendir(__DIR__ . '/src/covers') : false; if ($dh) { while (($n = readdir($dh)) !== false) { if (substr($n, -4) === '.jpg') { $any = true; break; } } closedir($dh); } } // 하나만 찾으면 멈춤
    // ★ (2026-11-12 10:11) '&tf=1' — 썸네일을 원본 비율로 바꾸며 주소를 한 번 바꿈(브라우저가 하루 동안 저장해 둔 옛 정사각형 썸네일 대신 새로 받게). thumb.php 는 이 값을 읽지 않음
    if (!$any) return '&tf=1';
    $p = mc_file_cover_path($abs);
    return '&tf=1' . (is_file($p) ? '&cv=' . (int)@filemtime($p) : '');
}
/** ★ (2026-11-10 16:08, fix101) 콘텐츠 폴더 번호·상대 경로로 — 즐겨찾기·북마크·서재 화면의 표지 주소에 붙임(thumb.php 와 같게 없는 번호는 0번) */
function mc_file_cover_v_rel($bidx, $rel) {
    $bds = $GLOBALS['base_dirs'] ?? [];
    if (!is_array($bds)) return '';
    $bd = $bds[(int)$bidx] ?? ($bds[0] ?? '');
    $rel = str_replace('\\', '/', (string)$rel);
    if (!is_string($bd) || $bd === '' || trim($rel, '/') === '' || preg_match('#(^|/)\.\.(/|$)#', $rel)) return '';
    return mc_file_cover_v(rtrim($bd, '/\\') . '/' . ltrim($rel, '/'));
}
function mc_write_file_cover($abs, $data) {
    if (!is_string($data) || $data === '') return false;
    $img = mc_gd_from_string($data);
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    if ($w < 1 || $h < 1) { imagedestroy($img); return false; }
    $nh = min($h, 800); $nw = max(1, (int)round($w * $nh / $h));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    $dest = mc_file_cover_path($abs);
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) { imagedestroy($out); return false; }
    // ★ (2026-11-10 23:30 fix108) 보안: src/.htaccess 가 그림(jpg)은 열어 두므로, 표지 폴더는 주소로 바로 못 열게 따로 전부 막음(없으면 만듦 — src/video/.htaccess 와 같은 내용)
    //   표지는 thumb.php·index.php 가 로그인·폴더 권한을 확인하고 내보내므로 화면에는 영향 없음 (Apache 2.4 실측: 막기 전 200 → 403)
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n<FilesMatch \".*\">\nRequire all denied\n</FilesMatch>\n</IfModule>\n<IfModule !mod_authz_core.c>\n<FilesMatch \".*\">\nOrder deny,allow\nDeny from all\n</FilesMatch>\n</IfModule>\n");
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
// ============================================================
// ★ (2026-11-12 10:09, 펜닐 요청 — 시안 C 확인 후) 표지 썸네일을 정사각형으로 자르지 않고 원본 비율 그대로(긴 변 400px)
//   [종전] 압축·이미지 폴더 책의 자동 표지를 400×400 으로 잘랐음(세로는 위 정사각형, 가로는 가운데~오른쪽 정사각형) →
//   목록 칸(3:4)에서 한 번 더 잘려 일부만 보였고, 이 썸네일을 복사한 폴더 자동 표지([cover].jpg)도 잘렸음(수동 표지는 원본이라 멀쩡)
//   [지금] 원본 비율로 만들고 'thumb_fit' => 1 을 함께 적음. 표시가 없는 옛 썸네일은 thumb.php 가 그림을 보낼 때 한 번만 다시 만듦
// ============================================================
if (!function_exists('mc_thumb_fit_from_gd')) {
/** GD 그림 → 원본 비율 JPEG(긴 변 $max px, 작으면 그대로) 바이트. 실패 false */
function mc_thumb_fit_from_gd($gd, $max = 400) {
    if (!$gd) return false;
    $w = imagesx($gd); $h = imagesy($gd);
    if ($w < 1 || $h < 1) return false;
    $sc = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $sc)); $nh = max(1, (int)round($h * $sc));
    $out = imagecreatetruecolor($nw, $nh);
    if (!$out) return false;
    imagecopyresampled($out, $gd, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start();
    imagejpeg($out, null, 75);
    $d = ob_get_clean();
    imagedestroy($out);
    return (is_string($d) && $d !== '') ? $d : false;
}
}
if (!function_exists('mc_thumb_fit_from_bytes')) {
/** 그림 바이트 → 원본 비율 JPEG 바이트(너무 큰 그림·손상 그림은 false — mc_gd_from_string 과 같은 기준) */
function mc_thumb_fit_from_bytes($data, $max = 400) {
    $gd = mc_gd_from_string($data);
    if (!$gd) return false;
    $r = mc_thumb_fit_from_gd($gd, $max);
    imagedestroy($gd);
    return $r;
}
}
if (!function_exists('mc_imgbook_first_page')) {
/**
 * ★ (2026-11-12 17:25, 펜닐 로그 — fix128) 이미지 폴더 책의 첫 쪽 그림 경로('' = 없음) — 목록이 썸네일을 처음 만들 때와 같이 표지([cover].jpg)·영상 썸네일은 뺌(mc_folder_nonpage)
 *   종전 mc_thumb_fit_source 는 폴더 안 [cover].jpg 도 쪽으로 쳐서, 쪽 이름이 글자로 시작하면(rurouni_001.jpg·한글 이름 — 이름 순에서 '[' 뒤)
 *   잘린 옛 정사각형 표지를 첫 쪽으로 골라 다시 400×400 을 만들었음(실서버 기록: src 400x400 → new 400x400 성공)
 */
function mc_imgbook_first_page($src) {
    $src = rtrim((string)$src, '/\\');
    if ($src === '' || !is_dir($src)) return '';
    $imgs = [];
    foreach ((@scandir($src) ?: []) as $n) {
        if ($n !== '' && $n[0] !== '.' && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $n) && !(function_exists('mc_folder_nonpage') && mc_folder_nonpage($n)) && is_file($src . '/' . $n)) $imgs[] = $n;
    }
    if (!$imgs) return '';
    $imgs = array_values(n_sort($imgs));
    return $src . '/' . $imgs[0];
}
}
if (!function_exists('mc_thumb_fit_needs')) {
/**
 * ★ (2026-11-12 17:25 fix128) 썸네일을 원본 비율로 다시 만들어야 하는지 — 0: 그대로 · 1: 옛 방식(표시 없음) · 2: 잘못 만든 정사각형
 *   2 = 이미지 폴더 책인데 thumb_fit=1 이면서 썸네일이 400×400 이고 첫 쪽은 정사각형이 아님(위 mc_imgbook_first_page 설명의 fix128 전 잘못 — 표지를 첫 쪽으로 씀)
 *   첫 쪽이 정말 정사각형이면 0(그대로). thumb_fit=-1(전에 실패)은 0
 */
function mc_thumb_fit_needs($data, $src, $kind) {
    if (!is_array($data) || empty($data['thumbnail']) || !is_string($data['thumbnail'])) return 0;
    if (empty($data['thumb_fit'])) return 1;
    if ($kind !== 'imgfolder' || (int)$data['thumb_fit'] !== 1) return 0;
    if (mc_thumb_wh(base64_decode($data['thumbnail'], true)) !== '400x400') return 0;
    $p = mc_imgbook_first_page($src);
    $s = ($p !== '') ? @getimagesize($p) : false;
    if (!$s || empty($s[0]) || empty($s[1])) return 0;
    return (abs($s[0] / $s[1] - 1) > 0.03) ? 2 : 0;
}
}
if (!function_exists('mc_thumb_fit_source')) {
/** 표지로 쓸 첫 그림 바이트 — 목록이 처음 썸네일을 만들 때와 같은 그림(이름 자연 정렬 첫 그림). $kind: 'zip'(압축 파일) | 'imgfolder'(이미지 폴더 책) */
function mc_thumb_fit_source($src, $kind) {
    if ($kind === 'imgfolder') {
        $p = mc_imgbook_first_page($src);
        return ($p === '') ? false : @file_get_contents($p);
    }
    if (!is_file($src)) return false;
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    if ($ext === 'zip' || $ext === 'cbz') {
        if (!class_exists('ZipArchive') || !function_exists('mc_zip_names')) return false;
        $zip = new ZipArchive;
        if ($zip->open($src) !== TRUE) return false;
        $names = array_values(n_sort(mc_zip_names($zip)));
        $first = null;
        foreach ($names as $n) { if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', (string)$n)) { $first = $n; break; } }
        $d = ($first !== null) ? mc_zip_from_name($zip, $first) : false;
        $zip->close();
        return $d;
    }
    if (in_array($ext, ['rar', 'cbr', '7z', 'cb7'], true) && class_exists('ArchiveHandler')) {
        try { $h = new ArchiveHandler($src); $d = $h->extractFirstImage(); return ($d === null) ? false : $d; } catch (\Throwable $e) { return false; }
    }
    return false;
}
}
// ★ (2026-11-12 16:35, 펜닐 요청 — fix127) 썸네일·표지 기록 — src/perf/thumb_날짜.log 에 한 줄씩(JSON). 속도 기록(mc_perf_enabled)과 같은 조건:
//   src/perf 폴더가 있고 관리자 설정 '속도 기록 남기기'가 켜져 있을 때만. 하루 20MB·14일 지나면 지움. src/.htaccess 로 웹에서 막힘(.log)
//   남기는 것: 옛 썸네일 다시 만들기(upgrade) · 목록·관리자가 새로 만든 썸네일(make·regen) · 폴더 자동 표지(folder_auto) ·
//   뷰어 표지 단추 요청(cover_req) · 옛 표지·실패 썸네일을 그대로 보낸 경우(serve_old). 동작은 바꾸지 않고 기록만
if (!function_exists('mc_thumb_log')) {
function mc_thumb_log($ev, array $rec) {
    try {
        if (!function_exists('mc_perf_enabled') || !mc_perf_enabled()) return;
        $dir = __DIR__ . '/src/perf';
        if (!is_dir($dir)) return;
        $f = $dir . '/thumb_' . date('Y-m-d') . '.log';
        if (!is_file($f)) {
            foreach ((@scandir($dir) ?: []) as $n) {
                if (preg_match('/^thumb_(\d{4}-\d{2}-\d{2})\.log$/', $n, $m) && strtotime($m[1]) < time() - 14 * 86400) @unlink($dir . '/' . $n);
            }
        } elseif ((int)@filesize($f) > 20 * 1024 * 1024) return;
        $u = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_SESSION['user_id'] ?? ''));
        $rec = ['t' => date('Y-m-d H:i:s'), 'ev' => (string)$ev, 'by' => basename((string)($_SERVER['SCRIPT_NAME'] ?? '')), 'user' => $u] + $rec;
        @file_put_contents($f, json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n", FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {}
}
/** 그림 바이트 → '가로x세로'(못 읽으면 '') */
function mc_thumb_wh($bytes) {
    if (!is_string($bytes) || $bytes === '') return '';
    $s = @getimagesizefromstring($bytes);
    return $s ? ((int)$s[0] . 'x' . (int)$s[1]) : '';
}
}
if (!function_exists('mc_thumb_fit_upgrade')) {
/**
 * 옛 정사각형 썸네일을 원본 비율로 한 번만 다시 만듦 — thumb.php 가 그림을 보낼 때 부름
 *   $json_file: 썸네일이 든 json(압축 '이름.json' / 이미지 폴더 '폴더.image_files.json'), $data: 그 내용, $src: 압축 파일·폴더 경로
 *   돌려줌: 새 썸네일 바이트(성공) / false(못 만듦 — 'thumb_fit' => -1 을 적어 다시 시도하지 않음, 옛 썸네일 그대로)
 *   - ★ (2026-11-12 13:00 fix124) json·표지는 임시 파일에 쓴 뒤 이름 바꾸기(safe_json_write 와 같음) — 종전(그 자리 덮어쓰기)은 쓰는 동안 잠금 없이 읽는
 *     목록이 반쯤 쓰인 내용을 읽을 수 있었고(동시 실행으로 재현), 그러면 목록이 그 json 을 viewer 값만 남겨 다시 써 썸네일·쪽수가 사라질 수 있었음.
 *     폴더 수정 시각은 바뀌어 폴더 정보 캐시를 한 번 다시 읽음(썸네일마다 한 번뿐). 'N' 표시는 콘텐츠 파일 생성 시각 기준이라 생기지 않음
 *   - 같은 폴더의 [cover].jpg 가 옛 썸네일과 바이트까지 같으면(= 목록·'자동'이 이 썸네일을 복사해 만든 자동 표지) 새 썸네일로 바꿈. 수동 표지는 그대로
 */
function mc_thumb_fit_upgrade($json_file, $data, $src, $kind, $force = false) { // ★ (2026-11-12 17:25 fix128) $force: 잘못 만든 정사각형(mc_thumb_fit_needs 2)도 다시
    if (!is_array($data) || !is_file($json_file)) return false;
    $__t0 = microtime(true); // ★ (2026-11-12 16:35 fix127) 기록용(mc_thumb_log) — 아래 동작은 그대로
    $old = (isset($data['thumbnail']) && is_string($data['thumbnail'])) ? base64_decode($data['thumbnail'], true) : false;
    $raw = mc_thumb_fit_source($src, $kind);
    $__lg = ['file' => (string)$src, 'kind' => (string)$kind, 'old' => function_exists('mc_thumb_wh') ? mc_thumb_wh($old) : '', 'src' => (is_string($raw) && $raw !== '' && function_exists('mc_thumb_wh')) ? mc_thumb_wh($raw) : '', 'src_kb' => is_string($raw) ? (int)round(strlen($raw) / 1024) : 0];
    $new = (is_string($raw) && $raw !== '') ? mc_thumb_fit_from_bytes($raw) : false;
    if ($new === false) $__lg['why'] = (!is_string($raw) || $raw === '') ? '첫 그림 꺼내기 실패' : ($__lg['src'] === '' ? '그림 형식 못 읽음' : '그림 열기 실패(메모리 부족·손상)');
    unset($raw);
    $cur = @json_decode((string)@file_get_contents($json_file), true); // 그사이 다른 요청이 바꿨을 수 있어 다시 읽음
    if (!is_array($cur)) $cur = $data;
    if (!$force && !empty($cur['thumb_fit']) && (int)$cur['thumb_fit'] === 1 && !empty($cur['thumbnail'])) {
        $d = base64_decode((string)$cur['thumbnail'], true);
        if (function_exists('mc_thumb_log')) mc_thumb_log('upgrade', $__lg + ['res' => '이미 바뀜(다른 요청)', 'ms' => (int)round((microtime(true) - $__t0) * 1000)]);
        return ($d !== false && $d !== '') ? $d : false;
    }
    if ($new === false) {
        $cur['thumb_fit'] = -1;
        safe_json_write($json_file, $cur);
        if (function_exists('mc_thumb_log')) mc_thumb_log('upgrade', $__lg + ['res' => '실패(옛 썸네일 그대로, thumb_fit=-1)', 'mem' => (string)ini_get('memory_limit'), 'ms' => (int)round((microtime(true) - $__t0) * 1000)]);
        return false;
    }
    $cur['thumbnail'] = base64_encode($new);
    $cur['thumb_fit'] = 1;
    $__lg['new'] = function_exists('mc_thumb_wh') ? mc_thumb_wh($new) : '';
    if (!safe_json_write($json_file, $cur)) { if (function_exists('mc_thumb_log')) mc_thumb_log('upgrade', $__lg + ['res' => 'json 저장 실패(이번만 새 그림)', 'ms' => (int)round((microtime(true) - $__t0) * 1000)]); return $new; }
    if ($force) $__lg['force'] = '잘못 만든 정사각형 다시';
    // ★ (2026-11-12 17:25 fix128) 이미지 폴더 책은 책 폴더 안 [cover].jpg 가 목록 카드 그림 — 목록이 썸네일과 같은 그림으로 만든 것(옛 썸네일과 같거나, 다시 만들 때 400×400 정사각형)이면 함께 바꿈.
    //   종전엔 바깥(부모 폴더) 표지만 바꿔서 카드는 계속 잘린 정사각형이었음(실서버 기록: 책마다 '폴더 표지 400x400 그대로 · books 0')
    if ($kind === 'imgfolder') {
        $ic = rtrim((string)$src, '/\\') . '/[cover].jpg';
        $__lg['cover_in'] = '안쪽 표지 없음';
        if (is_file($ic)) {
            $icd = (string)@file_get_contents($ic);
            if ((is_string($old) && $old !== '' && $icd === $old) || ($force && mc_thumb_wh($icd) === '400x400')) {
                $tmp = $ic . '.tf' . bin2hex(random_bytes(4)) . '.tmp';
                if (@file_put_contents($tmp, $new, LOCK_EX) === strlen($new)) {
                    if (!@rename($tmp, $ic)) { @copy($tmp, $ic); @unlink($tmp); }
                    $__lg['cover_in'] = '안쪽 표지도 바꿈';
                } else { @unlink($tmp); $__lg['cover_in'] = '안쪽 표지 쓰기 실패'; }
            } else $__lg['cover_in'] = '안쪽 표지 다른 그림이라 그대로';
        }
    }
    $__lg['cover'] = '표지 없음';
    if (is_string($old) && $old !== '') {
        $cf = dirname(rtrim((string)$src, '/\\')) . '/[cover].jpg';
        if (is_file($cf)) $__lg['cover'] = '다른 표지(수동 등) 그대로';
        if (is_file($cf) && @filesize($cf) === strlen($old) && @file_get_contents($cf) === $old) {
            $tmp = $cf . '.tf' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, $new, LOCK_EX) === strlen($new)) {
                if (!@rename($tmp, $cf)) { @copy($tmp, $cf); @unlink($tmp); } // Windows: 있는 파일 위로 이름 바꾸기가 안 되면 복사(safe_json_write 와 같음)
                $__lg['cover'] = '자동 표지도 바꿈';
            } else { @unlink($tmp); $__lg['cover'] = '자동 표지 쓰기 실패'; }
        }
    }
    if (function_exists('mc_thumb_log')) mc_thumb_log('upgrade', $__lg + ['res' => '성공', 'ms' => (int)round((microtime(true) - $__t0) * 1000)]);
    return $new;
}
}

/** 폴더 표지를 자동으로 — [cover].jpg 를 지우고, 목록 화면이 처음 만들 때와 같이 첫 압축 파일(이름 순)의 썸네일로 다시 만듦(썸네일이 아직 없으면 목록을 열 때 만들어짐) */
function mc_folder_cover_auto($dir) {
    $dir = rtrim((string)$dir, '/\\');
    if ($dir === '' || !is_dir($dir)) return false;
    $cf = $dir . '/[cover].jpg';
    $__t0 = microtime(true); // ★ (2026-11-12 16:35 fix127) 기록용(mc_thumb_log)
    if (is_file($cf) && !@unlink($cf)) { if (function_exists('mc_thumb_log')) mc_thumb_log('folder_auto', ['dir' => $dir, 'res' => '옛 표지 지우기 실패']); return false; }
    $arcs = [];
    foreach ((@scandir($dir) ?: []) as $n) {
        if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $n) && is_file($dir . '/' . $n)) $arcs[] = $n;
    }
    if (!$arcs) {
        // ★ (2026-11-11 07:59 fix111, 펜닐 제보) 압축 책이 없는 폴더는 지우기만 하고 끝나 기본 폴더 표지가 다시 안 생겼음(새로고침해도) —
        //   이미지 폴더 책이면 이름 순 첫 책의 썸네일(목록이 만든 '책이름.image_files.json')로, 동영상·문서 폴더면 목록 배경과 같은 방식(mc_media_folder_cover_make)으로
        $books = [];
        foreach ((@scandir($dir) ?: []) as $n) {
            if ($n === '' || $n[0] === '.' || !is_dir($dir . '/' . $n)) continue;
            if (is_file($dir . '/' . $n . '.image_files.json')) $books[] = $n;
        }
        if ($books) {
            foreach (n_sort($books) as $n) {
                $j = @json_decode((string)@file_get_contents($dir . '/' . $n . '.image_files.json'), true);
                // ★ (2026-11-12 10:10) 옛 정사각형 썸네일이면 먼저 원본 비율로 다시 만들고 씀
                if (is_array($j) && function_exists('mc_thumb_fit_needs') && ($__nd = mc_thumb_fit_needs($j, $dir . '/' . $n, 'imgfolder')) > 0) { $__n = mc_thumb_fit_upgrade($dir . '/' . $n . '.image_files.json', $j, $dir . '/' . $n, 'imgfolder', $__nd === 2); /* ★ (2026-11-12 17:25 fix128) 잘못 만든 정사각형도 */ if ($__n !== false) $j['thumbnail'] = base64_encode($__n); }
                if (is_array($j) && !empty($j['thumbnail']) && is_string($j['thumbnail']) && strlen($j['thumbnail']) > 100) {
                    $d = base64_decode($j['thumbnail'], true);
                    if (is_string($d) && $d !== '') { @file_put_contents($cf, $d, LOCK_EX); break; }
                }
            }
        } elseif (function_exists('mc_media_folder_cover_make')) {
            try { mc_media_folder_cover_make($dir, false); } catch (\Throwable $e) {} // 영상 장면 뽑기(ffmpeg)는 하지 않음 — 응답이 늦어짐. 없으면 목록 배경이 나중에 만듦
        }
        if (function_exists('mc_thumb_log')) { clearstatcache(true, $cf); mc_thumb_log('folder_auto', ['dir' => $dir, 'from' => $books ? '이미지 폴더 책' : '동영상·문서', 'cover' => is_file($cf) ? mc_thumb_wh((string)@file_get_contents($cf)) : '없음', 'ms' => (int)round((microtime(true) - $__t0) * 1000)]); }
        return true;
    }
    $arcs = n_sort($arcs);
    $j = @json_decode((string)@file_get_contents(get_cache_json_path($dir . '/' . reset($arcs))), true);
    // ★ (2026-11-12 10:10) 옛 정사각형 썸네일이면 먼저 원본 비율로 다시 만들고 씀
    if (is_array($j) && !empty($j['thumbnail']) && empty($j['thumb_fit']) && function_exists('mc_thumb_fit_upgrade')) { $__n = mc_thumb_fit_upgrade(get_cache_json_path($dir . '/' . reset($arcs)), $j, $dir . '/' . reset($arcs), 'zip'); if ($__n !== false) $j['thumbnail'] = base64_encode($__n); }
    if (is_array($j) && !empty($j['thumbnail']) && is_string($j['thumbnail']) && strlen($j['thumbnail']) > 100) {
        $d = base64_decode($j['thumbnail'], true);
        if (is_string($d) && $d !== '') @file_put_contents($cf, $d, LOCK_EX);
    }
    if (function_exists('mc_thumb_log')) { clearstatcache(true, $cf); mc_thumb_log('folder_auto', ['dir' => $dir, 'from' => (string)reset($arcs), 'thumb_fit' => (is_array($__jj = @json_decode((string)@file_get_contents(get_cache_json_path($dir . '/' . reset($arcs))), true))) ? ($__jj['thumb_fit'] ?? '없음') : 'json 없음', 'cover' => is_file($cf) ? mc_thumb_wh((string)@file_get_contents($cf)) : '없음(썸네일 없음 — 목록을 열면 만들어짐)', 'ms' => (int)round((microtime(true) - $__t0) * 1000)]); }
    return true;
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
// ★ (2026-11-10 18:39, 펜닐 요청 — fix104) 즐겨찾기·북마크·서재의 쪽 번호 단추를 폴더 목록(index.php)과 같게 — 관리자 설정 '페이지 네비게이션 PC/모바일'(pages_per_group·pages_per_group_mobile)
//   묶음 방식: [« 처음][‹ 이전 묶음] 이 묶음의 쪽 번호 [› 다음 묶음], 화면 폭 768px 이하는 모바일 개수(목록 화면 스크립트와 같은 기준)
function mc_pager_groups() {
    return [max(1, (int)($GLOBALS['pages_per_group'] ?? 10)), max(1, (int)($GLOBALS['pages_per_group_mobile'] ?? 5))];
}
/** 서버에서 처음 그릴 때(서재) — 목록 화면과 같은 User-Agent 판정 */
function mc_pager_is_mobile() {
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false;
}
/** 묶음 범위 [start, end, 처음(0=없음), 이전 묶음 끝(0=없음), 다음 묶음 처음(0=없음)] */
function mc_pager_range($cur, $pages, $per) {
    $per = max(1, (int)$per); $pages = max(1, (int)$pages); $cur = min(max(1, (int)$cur), $pages);
    $grp = (int)ceil($cur / $per); $st = ($grp - 1) * $per + 1; $en = min($st + $per - 1, $pages);
    return [$st, $en, $grp > 1 ? 1 : 0, $grp > 1 ? $st - 1 : 0, $en < $pages ? $en + 1 : 0];
}
/** 화면 스크립트용 — window.mcPgGroup.range(지금 쪽, 전체 쪽) · onChange(다시 그리기: 폭이 768px 를 넘나들 때) */
function mc_pager_group_js() {
    [$pc, $mo] = mc_pager_groups();
    $t = json_encode(['first' => __('ui_first_page'), 'prev' => __('ui_prev_group'), 'next' => __('ui_next_group')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return '<script>if(!window.mcPgGroup){window.mcPgGroup={pc:' . $pc . ',mob:' . $mo . ',t:' . $t . ','
        . 'per:function(){return (window.innerWidth||document.documentElement.clientWidth)<=768?this.mob:this.pc;},'
        . 'range:function(cur,pages){var g=this.per(),grp=Math.ceil(cur/g),st=(grp-1)*g+1,en=Math.min(st+g-1,pages);return{st:st,en:en,first:grp>1?1:0,prev:grp>1?st-1:0,next:en<pages?en+1:0};},'
        . 'onChange:function(fn){var self=this,m=this.per();window.addEventListener("resize",function(){var n=self.per();if(n!==m){m=n;fn();}});},'
        // ★ (2026-11-10 19:18 fix105) 부트스트랩 쪽 단추(<li class="page-item">) — o.href(p) 주소, o.click(p) 누를 때 부를 식, o.attr(p) 더 붙일 속성, o.cls 링크 클래스
        . 'bs:function(cur,pages,o){o=o||{};var R=this.range(cur,pages),t=this.t,e=function(s){return String(s).replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/</g,"&lt;");},'
        . 'li=function(p,l,tip,on){return \'<li class="page-item\'+(on?\' active\':\'\')+\'"><a class="page-link\'+(o.cls?\' \'+o.cls:\'\')+\'" href="\'+e(o.href?o.href(p):"#")+\'"\'+(o.click?\' onclick="\'+e(o.click(p))+\';return false;"\':\'\')+(o.attr?\' \'+o.attr(p):\'\')+(tip?\' title="\'+e(tip)+\'" aria-label="\'+e(tip)+\'"\':\'\')+\'>\'+l+\'</a></li>\';},h=\'\';'
        . 'if(R.first)h+=li(1,"«",t.first);if(R.prev)h+=li(R.prev,"‹",t.prev);for(var p=R.st;p<=R.en;p++)h+=li(p,p,"",p===cur);if(R.next)h+=li(R.next,"›",t.next);return h;}};'
        // 서버가 그린 부트스트랩 쪽 단추(mc_pager_bs_items — ul[data-mc-pg])도 폭이 768px 를 넘나들면 다시 그림
        . 'window.mcPgGroup.onChange(function(){document.querySelectorAll("ul[data-mc-pg]").forEach(function(u){var U=u.getAttribute("data-url")||"";u.innerHTML=window.mcPgGroup.bs(+u.getAttribute("data-cur")||1,+u.getAttribute("data-pages")||1,{href:function(p){return U.replace("987654321",p);},cls:u.getAttribute("data-cls")||""});});});}</script>';
}
/** ★ (2026-11-10 19:18, 펜닐 요청 — fix105) 서버에서 그리는 부트스트랩 쪽 단추 — <ul class="pagination ..."> 의 여는 태그 속성과 <li> 들. $href(쪽) = 주소
 *   쓰는 법: <ul class="pagination ..." <?php echo $a; ?>><?php echo $li; ?></ul> 처럼 [$a, $li] = mc_pager_bs($cur, $pages, $href) */
function mc_pager_bs($cur, $pages, callable $href, $cls = '') {
    [$pc, $mo] = mc_pager_groups();
    $pages = max(1, (int)$pages); $cur = min(max(1, (int)$cur), $pages);
    [$st, $en, $f, $pv, $nx] = mc_pager_range($cur, $pages, mc_pager_is_mobile() ? $mo : $pc);
    $li = function ($p, $l, $tip, $on) use ($href, $cls) {
        return '<li class="page-item' . ($on ? ' active' : '') . '"><a class="page-link' . ($cls !== '' ? ' ' . h($cls) : '') . '" href="' . h($href($p)) . '"' . ($tip !== '' ? ' title="' . h($tip) . '" aria-label="' . h($tip) . '"' : '') . '>' . $l . '</a></li>';
    };
    $h = '';
    if ($f) $h .= $li(1, '«', __('ui_first_page'), false);
    if ($pv) $h .= $li($pv, '‹', __('ui_prev_group'), false);
    for ($p = $st; $p <= $en; $p++) $h .= $li($p, (string)$p, '', $p === $cur);
    if ($nx) $h .= $li($nx, '›', __('ui_next_group'), false);
    $a = 'data-mc-pg="1" data-cur="' . $cur . '" data-pages="' . $pages . '" data-url="' . h($href(987654321)) . '"' . ($cls !== '' ? ' data-cls="' . h($cls) . '"' : '');
    return [$a, $h];
}

if (!function_exists('mc_doc_cover_btn')) {
/** ★ (2026-11-08 fix80, 펜닐 요청) EPUB·TXT·오피스·한글 뷰어의 '이 책 표지를 폴더 표지로' 단추(관리자만) — bookmark.php?mode=set_cover(삭제 토큰)
 *   쓸 그림: 같은 이름 그림(책.jpg 등) → 파일 안 표지(EPUB 표지·HWPX 미리보기·오피스 썸네일). 없으면 '표지로 쓸 그림이 없습니다' */
function mc_doc_cover_btn($getfile, $bidx, $class = '', $page = false) {
    if (trim((string)($_SESSION['user_group'] ?? '')) !== 'admin' || !function_exists('get_delete_token')) return '';
    $url = 'bookmark.php?mode=set_cover&file=' . encode_url((string)$getfile) . '&bidx=' . (int)$bidx . '&token=' . urlencode((string)get_delete_token());
    $lbl = __h('doc_cover_btn');
    // ★ (2026-11-10 14:55, 펜닐 요청 — fix99) 단추를 누르면 작은 메뉴 — 책 표지를 폴더 표지로(예전 동작) · 그림 골라 파일 표지로 · 그림 골라 폴더 표지로 · 자동으로 되돌리기
    //   $page = true(rhwp — 쪽이 그림): 이 쪽을 파일 표지로 · 이 쪽을 폴더 표지로 · 자동으로(뷰어가 window.mcDocCoverPage() 로 지금 쪽 그림을 줌)
    //   보내기는 만화 뷰어와 같음: bookmark.php set_cover_img(target=file|폴더)·cover_auto — POST·관리자·CSRF. 끝나면 localStorage 'mc_cover_bump' 로 목록이 새로고침 없이 바뀜(index.php)
    $gf = str_replace('\\', '/', (string)$getfile);
    $dir = rtrim(str_replace('\\', '/', dirname($gf)), '/');
    $csrf = function_exists('generate_csrf_token') ? generate_csrf_token() : '';
    $it = function ($a, $k) { return '<button type="button" class="mc-dcv-mi" data-a="' . $a . '" onclick="mcDocCoverDo(this)">' . __h($k) . (strpos($a, 'page-') === 0 ? '<span class="mc-dcv-pg" style="opacity:.6"></span>' : '') . '</button>'; }; // ★ (2026-11-11 07:59 fix111) 쪽 그림 항목엔 쓸 쪽 번호 칸
    $items = $page
        ? $it('page-file', 'mcv2_cover_file') . $it('page-folder', 'mcv2_cover_folder') . $it('book', 'doc_cover_book') // ★ (2026-11-10 16:08 fix101) rhwp 도 예전 '책 표지(문서 안 미리보기 등)를 폴더 표지로'를 그대로 둠
        : $it('book', 'doc_cover_book') . $it('pick-file', 'doc_cover_pick_file') . $it('pick-folder', 'doc_cover_pick_folder');
    $items .= $it('auto', 'mcv2_cover_auto');
    return '<span class="mc-dcv" style="position:relative;display:inline-block" data-url="' . h($url) . '" data-file="' . h($gf) . '" data-dir="' . h($dir) . '" data-bidx="' . (int)$bidx . '" data-csrf="' . h($csrf) . '" data-pg="' . __h('mcv2_cover_pg') . '" data-q="' . __h('mcv2_cover_auto_q') . '" data-none="' . __h('viewer_cover_none') . '">'
        . '<button type="button" class="' . h($class) . '" title="' . $lbl . '" aria-label="' . $lbl . '" aria-haspopup="true" onclick="mcDocCoverMenu(this)">🖼️</button>'
        . '<span class="mc-dcv-menu" hidden>' . $items . '</span>'
        . '<input type="file" accept="image/*" hidden></span>'
        // 메뉴는 화면 기준(fixed)으로 단추 바로 아래에, 화면 밖으로 안 나가게(열 때 자리를 셈) — 뷰어마다 머리 막대 단추 규칙이 달라 메뉴 칸은 !important 로 고정
        . '<style>.mc-dcv .mc-dcv-menu{position:fixed;z-index:3000;display:block;background:#fff;color:#222;border:1px solid #ccc;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.25);padding:4px 0;min-width:190px;max-width:calc(100vw - 16px);text-align:left;white-space:nowrap;font-size:14px;line-height:1.3}.mc-dcv .mc-dcv-menu[hidden]{display:none!important}'
        . '.mc-dcv .mc-dcv-menu .mc-dcv-mi{display:block!important;width:100%!important;height:auto!important;min-height:0!important;background:none!important;border:0!important;border-radius:0!important;margin:0!important;color:#222!important;padding:8px 14px!important;font-size:14px!important;text-align:left!important;cursor:pointer;white-space:nowrap;box-shadow:none!important}.mc-dcv .mc-dcv-menu .mc-dcv-mi:hover{background:#eef2f7!important}</style>'
        . '<script>(function(){if(window.mcDocCoverDo)return;'
        . 'window.mcDocCoverMenu=function(b){var m=b.parentNode.querySelector(".mc-dcv-menu");m.hidden=!m.hidden;if(m.hidden)return;'
        . 'try{var pn=typeof window.mcDocCoverPageNo==="function"?window.mcDocCoverPageNo():0;m.querySelectorAll(".mc-dcv-pg").forEach(function(s){s.textContent=pn>0?" "+String(b.parentNode.getAttribute("data-pg")||"%s").replace("%s",pn):"";});}catch(e){}' // ★ (2026-11-11 07:59 fix111) 쓸 쪽 번호
        . 'var r=b.getBoundingClientRect(),w=m.offsetWidth,h=m.offsetHeight,vw=document.documentElement.clientWidth,vh=window.innerHeight;'
        . 'm.style.left=Math.max(8,Math.min(r.right-w,vw-w-8))+"px";m.style.top=((r.bottom+4+h>vh&&r.top-4-h>0)?(r.top-4-h):(r.bottom+4))+"px";};'
        . 'document.addEventListener("click",function(e){document.querySelectorAll(".mc-dcv-menu").forEach(function(m){if(!m.hidden&&!(e.target.closest&&e.target.closest(".mc-dcv")===m.parentNode))m.hidden=true;});});'
        . 'window.addEventListener("scroll",function(){document.querySelectorAll(".mc-dcv-menu").forEach(function(m){m.hidden=true;});},true);' // 화면 기준 메뉴라 스크롤하면 닫음
        . 'function bump(w,k){var n={t:Date.now(),f:w.getAttribute("data-file"),d:w.getAttribute("data-dir"),k:k};if(window.mcCoverBumpPut)return window.mcCoverBumpPut(n);try{localStorage.setItem("mc_cover_bump",JSON.stringify(n));}catch(e){}}' // ★ (2026-11-12 13:49 fix125) 앞 알림도 함께(mcCoverBumpPut)
        // ★ (2026-11-08 fix81) 원래 모양은 innerHTML 로 되돌림 — 아이콘 스크립트(mc-icons.js)가 🖼️ 를 SVG 로 바꿔 글자(textContent)가 비어, 누른 뒤 단추가 빈칸이 되던 것
        . 'function run(w,p,k){var b=w.querySelector("button"),h0=b.innerHTML,l0=b.title;b.disabled=true;b.textContent="…";'
        . 'var q=p.then(function(r){return r.text().then(function(t){if(r.ok&&k)bump(w,k);b.textContent=r.ok?"✓":"✕";b.title=String(t||"").slice(0,80);});})'
        . '.catch(function(){b.textContent="✕";});if(window.mcCoverTrack)window.mcCoverTrack(q);q.then(function(){setTimeout(function(){b.innerHTML=h0;b.title=l0;b.disabled=false;},2500);});}' // ★ (2026-11-12 13:49 fix125) 저장 중엔 ← 가 기다림(mcCoverTrack)
        . 'function post(w,mode,img,tg){var f=new FormData();f.append("mode",mode);f.append("file",w.getAttribute("data-file"));f.append("csrf_token",w.getAttribute("data-csrf"));if(img)f.append("image",img);if(tg==="file")f.append("target","file");'
        . 'return fetch("bookmark.php?bidx="+(+w.getAttribute("data-bidx")||0),{method:"POST",body:f,credentials:"same-origin"});}'
        . 'function shrink(src,cb){var im=new Image();im.onload=function(){try{var w=im.naturalWidth,h=im.naturalHeight;if(!w||!h)return cb(null);var s=Math.min(1,1000/h),c=document.createElement("canvas");c.width=Math.max(1,Math.round(w*s));c.height=Math.max(1,Math.round(h*s));var x=c.getContext("2d");x.fillStyle="#fff";x.fillRect(0,0,c.width,c.height);x.drawImage(im,0,0,c.width,c.height);cb(c.toDataURL("image/jpeg",0.88));}catch(e){cb(null);}};im.onerror=function(){cb(null);};im.src=src;}'
        . 'function fail(w){var b=w.querySelector("button"),h0=b.innerHTML,l0=b.title;b.textContent="✕";b.title=w.getAttribute("data-none");setTimeout(function(){b.innerHTML=h0;b.title=l0;},2500);}'
        . 'window.mcDocCoverDo=function(it){var w=it.closest(".mc-dcv"),a=it.getAttribute("data-a"),tg=/file$/.test(a)?"file":"folder";w.querySelector(".mc-dcv-menu").hidden=true;'
        . 'if(a==="book"){run(w,fetch(w.getAttribute("data-url"),{credentials:"same-origin"}),"folder");return;}'
        . 'if(a==="auto"){if(confirm(w.getAttribute("data-q")))run(w,post(w,"cover_auto"),"auto");return;}'
        . 'if(/^page-/.test(a)){if(typeof window.mcDocCoverPage!=="function")return fail(w);Promise.resolve(window.mcDocCoverPage()).then(function(d){if(!d)return fail(w);run(w,post(w,"set_cover_img",d,tg),tg);},function(){fail(w);});return;}'
        . 'var inp=w.querySelector("input[type=file]");inp.value="";inp.onchange=function(){var fl=inp.files&&inp.files[0];if(!fl)return;var u=URL.createObjectURL(fl);shrink(u,function(d){URL.revokeObjectURL(u);if(!d)return fail(w);run(w,post(w,"set_cover_img",d,tg),tg);});};inp.click();};'
        . '})();</script>';
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
if (!function_exists('mc_folder_cover_try')) {
/**
 * ★ (2026-11-13 11:19, 펜닐 제보 — fix142) 표지가 없는 폴더에 지금 표지를 만들어 봄 — 목록의 빈 폴더 카드가 새로고침 없이 표지를 받으려고(thumb.php type=cover&mk=1)
 *   이미 [cover].jpg 가 있으면 그대로(지우지 않음 — 직접 넣은 표지일 수 있음). 만드는 규칙은 지금 있는 것과 같음:
 *   압축 책 폴더 → 이름순으로 썸네일이 있는 첫 책의 썸네일(목록 화면이 폴더를 열 때 만드는 것과 같은 자료) · 이미지 폴더 책 → 첫 책 썸네일(mc_folder_cover_auto 와 같음)
 *   동영상·문서 폴더 → mc_media_folder_cover_make(배경 갱신과 같음 — 영상 썸네일이 없으면 ffmpeg 로 한 번)
 *   압축 책의 썸네일 자체는 여기서 만들지 않음(폴더를 열 때 목록 화면이 만듦) — 없으면 false(화면이 나중에 다시 물음)
 */
function mc_folder_cover_try($dir) {
    $dir = rtrim((string)$dir, '/\\');
    if ($dir === '' || !is_dir($dir)) return false;
    $cf = $dir . '/[cover].jpg';
    if (is_file($cf) && filesize($cf) > 0) return true;
    $arcs = []; $books = [];
    foreach ((@scandir($dir) ?: []) as $n) {
        if ($n === '' || $n[0] === '.') continue;
        if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $n) && is_file($dir . '/' . $n)) $arcs[] = $n;
        elseif (is_dir($dir . '/' . $n) && is_file($dir . '/' . $n . '.image_files.json')) $books[] = $n;
    }
    $put = function ($b64) use ($cf) {
        if (!is_string($b64) || strlen($b64) <= 100) return false;
        $d = base64_decode($b64, true);
        if (!is_string($d) || $d === '') return false;
        clearstatcache(true, $cf);
        if (is_file($cf) && filesize($cf) > 0) return true; // 그사이 다른 요청이 만들었으면 그대로
        return @file_put_contents($cf, $d, LOCK_EX) !== false;
    };
    if ($arcs) {
        foreach (array_slice(n_sort($arcs), 0, 30) as $n) { // 앞쪽 30권까지만(책이 아주 많은 폴더에서 매번 정보 파일을 다 읽지 않게)
            $j = @json_decode((string)@file_get_contents(get_cache_json_path($dir . '/' . $n)), true);
            if (is_array($j) && $put($j['thumbnail'] ?? null)) break;
        }
    } elseif ($books) {
        foreach (array_slice(n_sort($books), 0, 30) as $n) { // 앞쪽 30권까지만(책이 아주 많은 폴더에서 매번 정보 파일을 다 읽지 않게)
            $j = @json_decode((string)@file_get_contents($dir . '/' . $n . '.image_files.json'), true);
            if (is_array($j) && $put($j['thumbnail'] ?? null)) break;
        }
    } elseif (function_exists('mc_media_folder_cover_make')) {
        // 쓸 그림이 없던 폴더(깨진 영상 등)는 폴더가 바뀔 때까지 ffmpeg 를 다시 돌리지 않음 — 배경 갱신(mc_media_covers_all)과 같은 기록(src/media_cover_none.json)·같은 열쇠를 같이 씀
        $__memo_f = __DIR__ . '/src/media_cover_none.json';
        $__memo = is_file($__memo_f) ? json_decode((string)@file_get_contents($__memo_f), true) : [];
        if (!is_array($__memo)) $__memo = [];
        $__ff = (string)($GLOBALS['ffmpeg_path'] ?? '');
        $__key = (int)@filemtime($dir) . '|' . (($__ff !== '' && is_file($__ff)) ? 'f' : 'n');
        if (isset($__memo[$dir]) && (string)$__memo[$dir] === $__key) return false;
        $__ok = false;
        try { $__ok = mc_media_folder_cover_make($dir, true); } catch (\Throwable $e) {}
        clearstatcache(true, $dir);
        if (!$__ok && !is_file($cf) && mc_media_cover_pick($dir, false) === false) { // 동영상·문서는 있는데 쓸 그림이 없음 → 기록
            $__memo[$dir] = (int)@filemtime($dir) . '|' . (($__ff !== '' && is_file($__ff)) ? 'f' : 'n');
            @file_put_contents($__memo_f, json_encode($__memo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
        }
    }
    clearstatcache(true, $cf);
    return is_file($cf) && filesize($cf) > 0;
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

// ============================================================
// ★ (2026-11-15 18:28, 펜닐 요청 — fix173) 뷰어 설정·동영상 이어보기 위치를 서버(계정별)에도 저장
//   [왜] 이어 보기 켬/끔·모드 기억·보기 방식(쿠키)과 동영상 위치(브라우저 저장소)는 브라우저에만 있어, 브라우저 데이터를 지우거나 앱을 다시 깔거나
//        다른 브라우저·기기에서 열면 없어졌음 → 계정 하나에 하나(폰·PC 같은 설정, 동영상은 보던 곳부터)
//   [설정] mc_prefs_sync(): 요청 맨 처음(bootstrap)에 브라우저 쿠키와 src/<아이디>_vprefs.json 을 맞춤 — 설정을 읽는 기존 코드($_COOKIE)는 그대로
//     · 이 브라우저에서 바꿨으면(쿠키 값이 지난번 맞춘 값과 다름) → 서버에 올림(판 번호 v + 1)
//     · 서버가 더 새 판이거나(다른 기기에서 바꿈) 이 브라우저에 값이 없으면(지움·새 브라우저) → 서버 값을 쿠키로 내려줌
//     · 맞춘 표시: 쿠키 mc_psync = '계정 지문.판 번호.값 지문'(값 자체는 안 담음, 스크립트가 못 읽게 HttpOnly) — 계정 지문은 fix174
//     · 처음 한 번: 서버에 아직 없으면 이 브라우저 값을 올림 / 이미 다른 기기가 올렸으면 그 값을 받음(계정 하나에 하나)
//     · 그림·동영상 조각·API 요청(img·vapi·action)과 GET 아닌 요청은 건너뜀(많고 설정과 상관없음)
//   [동영상] mc_vres_get(): 이 영상(압축 속 영상이면 그 압축의 영상들) 위치를 뷰어에 내줌 — 저장은 vres.php(js/mc-video.js 가 보냄)
// ============================================================
if (!function_exists('mc_prefs_keys')) {
    function mc_prefs_keys() {
        return [
            'mcv_resume'  => '/^[01]$/',          // 만화 이어 보기 켬/끔
            'mcv_modemem' => '/^(off|all|book)$/', // 모드 기억
            'mcv_mode'    => '/^(toon|book)$/',    // 마지막에 쓴 모드(모드 기억 '전체')
            'mcv_order'   => '/^[0-4]$/',          // 보기 방식
            'epub_resume' => '/^[01]$/',           // 전자책 이어 보기
            'txt_resume'  => '/^[01]$/',           // 텍스트 이어 보기
            // ★ (2026-11-16 09:31, 펜닐 요청 — fix181) 화면 크기와 상관없는 설정을 더함(계정 하나에 하나). 화면 맞춤(mcv_fit)·밝기(mcv_bright)·툰 그림 크기(mcv_tz)·전자책 글자 크기(epub_fontsize)는 기기마다 그대로
            'viewer_reverse_nav' => '/^[01]$/',    // 만화 북모드 넘김 방향 반대
            'mcv_port1'   => '/^[01]$/',           // 만화 북모드 세로 화면은 한 쪽
            'mcv_solo'    => '/^[01]$/',           // 만화 북모드 표지·가로 그림은 혼자
            'epub_theme'  => '/^(light|sepia|dark)$/', // 전자책 테마
            'epub_reverse_nav' => '/^[01]$/',      // 전자책 넘김 방향 반대
            'txt_reverse_nav'  => '/^[01]$/',      // 텍스트 넘김 방향 반대
        ];
    }
    // ★ (2026-11-16 09:31 fix181) 브라우저 저장소(localStorage)에 두는 설정 중 계정에 맞출 것 — js/mc-lsync.js 가 맞추고 vprefs.php 가 받음(서버 파일은 같은 src/<아이디>_vprefs.json 의 'l')
    //   기기마다 그대로 두는 것: 전자책 여백(epub_margin), 텍스트 글자 크기·여백(txt_font_size·txt_margin),
    //   화면 켜 두기·화면 어둡게·읽어 주기 목소리(기기마다 깔린 음성이 다름)(mct_wake·mct_dim·mct_tts_voice)
    //   ★ (2026-11-16 11:16, 펜닐 요청 — fix184) 'PC 와 같이 써도 문제없으면 계정' 기준으로 다시 나눔 — 전자책 2쪽 펼침(epub_spread: 끔·넓은 화면·가로 화면 — 값 자체가 화면에 맞춰 동작)·
    //   텍스트 자동 2단(txt_layout: '자동'만 기억, 가로면 2단)을 계정으로 · 읽은 시간은 따로 계정에 합산(vprefs.php op=rt) · 북모드 '짝 한 쪽 밀기'는 fix183
    function mc_lprefs_keys() {
        return [
            'epub_lh'      => '/^(|1\.[4-9]|2\.[0-4])$/',   // 전자책 줄 간격('' = 책대로)
            'epub_font'    => '/^(|gothic|serif)$/',        // 전자책 글꼴
            'epub_para'    => '/^(|[123])$/',               // 전자책 문단 간격
            'epub_justify' => '/^[01]$/',                   // 전자책 양쪽 정렬
            'txt_theme'    => '/^(light|sepia|dark)$/',     // 텍스트 테마
            'txt_line_height' => '/^([12](\.\d)?|3(\.0)?)$/', // 텍스트 줄 간격
            'txt_font'     => '/^(|gothic|serif)$/',        // 텍스트 글꼴
            'txt_justify'  => '/^[01]$/',                   // 텍스트 양쪽 정렬
            'txt_scroll_speed' => '/^([1-9]|10)$/',         // 텍스트 자동 스크롤 속도
            'mct_tts_rate' => '/^(0\.[5-9]|1(\.\d)?|2(\.[0-5])?)$/', // 읽어 주기 빠르기
            'mcn_color'    => '/^(yellow|orange|coral|pink|purple|blue|mint|green|gray)$/', // 형광펜 마지막 색
            'epub_spread'  => '/^(0|1|land)$/',             // ★ fix184 전자책 2쪽 펼침(끔·넓은 화면·가로 화면)
            'txt_layout'   => '/^(|auto)$/',                // ★ fix184 텍스트 자동 2단('' = 자동 아님 — 이 기기 저장소에선 지운 상태)
        ];
    }
    // ★ (2026-11-16 fix184) 저장소에서 '지움'이 곧 값인 키 — 빈 값('')으로 맞춤(js/mc-lsync.js)
    function mc_lprefs_empty_keys() { return ['txt_layout']; }
    function mc_prefs_uid() {
        $u = (string)($_SESSION['user_id'] ?? '');
        $u = preg_replace('/[^a-zA-Z0-9_]/', '', $u); // get_autosave_file() 과 같은 규칙
        return $u;
    }
    // ★ (2026-11-16 fix181) 값 표시(3글자) — 맞춘 표시 쿠키에 키마다 하나씩(값 자체는 안 담음)
    function mc_prefs_sig($v) { return $v === null ? '---' : str_pad(base_convert((string)(crc32((string)$v) % 46656), 10, 36), 3, '0', STR_PAD_LEFT); }
    function mc_prefs_sync() {
        static $done = false;
        if ($done) return; $done = true;
        if (PHP_SAPI === 'cli' || headers_sent()) return;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        if (isset($_GET['img']) || isset($_GET['vapi']) || isset($_GET['action'])) return;
        // (18:32) 설정을 읽는 화면·주로 오가는 화면만 — 썸네일·저장 같은 많은 요청마다 파일을 열지 않게
        if (!in_array(basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')), ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'series.php', 'library.php', 'browse.php', 'favorites.php', 'bookmarks.php'], true)) return;
        $uid = mc_prefs_uid();
        if ($uid === '') return;
        $keys = mc_prefs_keys();
        $C = [];
        foreach ($keys as $k => $re) { $v = $_COOKIE[$k] ?? null; if (is_string($v) && preg_match($re, $v)) $C[$k] = $v; }
        $sigs = function (array $a) use ($keys) { $s = ''; foreach ($keys as $k => $re) $s .= mc_prefs_sig($a[$k] ?? null); return $s; };
        $sync = null;
        // ★ (2026-11-15 18:47 fix174) 맞춘 표시에 계정 표시(아이디 지문 6자)를 붙임 — 같은 브라우저에서 다른 계정으로 바꾸면 그 계정 값을 받게
        //   (전엔 표시가 계정과 상관없어 tuser 로 돌아와도 admin 이 마지막에 고른 값이 그대로였음 — 시험으로 확인). 다른 계정 표시·예전 모양은 '아직 안 맞춤'으로 봄
        // ★ (2026-11-16 09:31, fix181) 맞춘 표시 = '계정 지문.판 번호.키마다 값 표시(3글자씩)' — 이 브라우저에서 '바꾼 키'만 올리고 나머지는 서버 값을 받음
        //   [왜] 전엔 값 전체의 지문 하나라, 열어 둔 기기에서 설정 하나만 바꿔도 이 브라우저의 모든 값(다른 기기가 그 사이 바꾼 것의 옛 값까지)을 올려 되돌렸음
        //        (시험으로 확인: B 가 이어 보기 끔 → 열어 둔 A 가 보기 방식만 바꿈 → 이어 보기가 다시 켬)
        //   예전 모양(fix173~180, 끝이 지문 10자)·키 목록이 바뀐 뒤의 표시는 키별 표시 없음으로 봄 — 그때는 서버가 지난번 맞춘 판 그대로면 다른 값은 이 브라우저가 바꾼 것, 서버가 더 새 판이면 서버 값
        $uh = substr(md5($uid), 0, 6);
        if (is_string($_COOKIE['mc_psync'] ?? null) && preg_match('/^([0-9a-f]{6})\.(\d{1,9})\.([0-9a-z-]{10,300})$/', $_COOKIE['mc_psync'], $m) && $m[1] === $uh) {
            $sync = [(int)$m[2], strlen($m[3]) === 3 * count($keys) ? $m[3] : null];
        }
        $file = __DIR__ . '/src/' . $uid . '_vprefs.json';
        if (!is_file($file) && empty($C)) return; // 서버에도 브라우저에도 없음 — 기본값 그대로(파일을 만들지 않음)
        $fp = @fopen($file, 'c+');
        if (!$fp) return;
        $out = null;
        try {
            $rd = function () use ($fp, $keys) { // 서버 값 읽기(거른 값만) — 파일 전체(S)도 돌려줌('l' 등 다른 칸을 지우지 않게)
                rewind($fp);
                $S = json_decode((string)stream_get_contents($fp), true);
                if (!is_array($S)) $S = [];
                $sp = is_array($S['p'] ?? null) ? $S['p'] : [];
                foreach ($sp as $k => $v) { if (!isset($keys[$k]) || !is_string($v) || !preg_match($keys[$k], $v)) unset($sp[$k]); }
                return [max(0, (int)($S['v'] ?? 0)), $sp, $S];
            };
            // 키마다: 올릴 것(이 브라우저에서 바꿨거나 서버에 없음)·받을 것(서버가 다름)
            $plan = function ($sv, $sp) use ($C, $keys, $sync) {
                $up = []; $down = []; $i = -1;
                foreach ($keys as $k => $re) {
                    $i++;
                    $c = $C[$k] ?? null; $s = $sp[$k] ?? null;
                    if ($c === $s) continue;
                    if ($s === null) { $up[$k] = $c; continue; }   // 서버에 없음 → 이 브라우저 값
                    if ($c === null) { $down[$k] = $s; continue; } // 이 브라우저에 없음 → 서버 값
                    if ($sync !== null && $sync[1] !== null) $mine = substr($sync[1], $i * 3, 3) !== mc_prefs_sig($c); // 지난번 맞춘 뒤 이 브라우저에서 바뀜
                    else $mine = ($sync !== null && $sync[0] === $sv); // 키별 표시 없음: 서버가 그 뒤로 안 바뀌었으면 이 브라우저가 바꾼 것
                    if ($mine) $up[$k] = $c; else $down[$k] = $s;
                }
                return [$up, $down];
            };
            if (!flock($fp, LOCK_SH)) return; // 읽기는 같이 — 올릴 때만 혼자 잠금(아래)
            list($sv, $sp, $S) = $rd();
            list($up, $down) = $plan($sv, $sp);
            if ($up) {
                // ★ (2026-11-15 18:47 fix174) 공유 잠금을 먼저 풀고 혼자 잠금 — Windows(LockFileEx)는 내가 쥔 공유 잠금과 겹치는 혼자 잠금을 기다려 멈출 수 있음(Linux 는 바꿔 줌). 다시 읽으니 그 사이 바뀐 것도 반영
                flock($fp, LOCK_UN); if (!flock($fp, LOCK_EX)) return;
                list($sv, $sp, $S) = $rd();
                list($up, $down) = $plan($sv, $sp);
                if ($up) {
                    foreach ($up as $k => $v) $sp[$k] = $v;
                    $sv++;
                    $S['v'] = $sv; $S['p'] = $sp; $S['t'] = time();
                    $json = json_encode($S, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (is_string($json)) { ftruncate($fp, 0); rewind($fp); fwrite($fp, $json); fflush($fp); }
                }
            }
            foreach ($down as $k => $v) {
                $_COOKIE[$k] = $v; $C[$k] = $v; // 이 요청에서 읽는 기존 코드도 서버 값을 씀
                @setcookie($k, $v, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']); // 스크립트(위 막대 설정)도 읽는 쿠키 — js/viewer-bar.js setPref 와 같은 모양
            }
            if ($sv > 0) $out = $uh . '.' . $sv . '.' . $sigs($C);
        } finally {
            @flock($fp, LOCK_UN); @fclose($fp);
        }
        if ($out !== null && (string)($_COOKIE['mc_psync'] ?? '') !== $out) {
            @setcookie('mc_psync', $out, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
            $_COOKIE['mc_psync'] = $out;
        }
    }
    // ★ (2026-11-16 09:31 fix181) 브라우저 저장소 설정의 계정 값 — 전자책·텍스트 뷰어가 <head> 에서 js/mc-lsync.js 에 넘김. 로그인 안 했으면 null
    function mc_lprefs_get() {
        $uid = mc_prefs_uid();
        if ($uid === '') return null;
        $out = [];
        $f = __DIR__ . '/src/' . $uid . '_vprefs.json';
        if (is_file($f)) {
            $S = function_exists('load_json_with_lock') ? load_json_with_lock($f) : json_decode((string)@file_get_contents($f), true);
            $keys = mc_lprefs_keys();
            if (is_array($S) && is_array($S['l'] ?? null)) {
                foreach ($S['l'] as $k => $r) {
                    $v = is_array($r) ? ($r['v'] ?? null) : null;
                    if (isset($keys[$k]) && is_string($v) && preg_match($keys[$k], $v)) $out[$k] = $v;
                }
            }
        }
        return $out;
    }
    // <head> 에 넣을 것(설정을 읽는 다른 스크립트보다 먼저)
    function mc_lprefs_head() {
        $s = mc_lprefs_get();
        if ($s === null) return '';
        $cfg = ['u' => substr(md5(mc_prefs_uid()), 0, 6), 'k' => array_keys(mc_lprefs_keys()), 'z' => mc_lprefs_empty_keys(), 's' => (object)$s];
        return '<script>window.MC_LSYNC=' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>'
             . '<script src="js/mc-lsync.js?v=' . (int)@filemtime(__DIR__ . '/js/mc-lsync.js') . '"></script>';
    }
    // ★ (2026-11-16 10:52, 펜닐 요청 — fix183) 만화 북모드 '짝 한 쪽 밀기(이 책만)' — 계정에 책마다 저장(src/<아이디>_vprefs.json 의 'o': { "bidx|경로": {v:'1'|'0', at} })
    //   펼침으로 볼 때 그 책의 쪽 짝만 바꾸는 값이라 화면 크기와 상관없음(함께 쓰는 '표지·가로 그림은 혼자'도 계정 값 — fix181) → 폰·PC 같게
    //   viewer.php 가 이 값을 브라우저 저장소('mcv_off:경로', 원래 읽던 곳)에 먼저 맞춰 놓음 · 바꾸면 js/viewer-bar.js 가 vprefs.php(op=off)로 보냄
    function mc_voff_key($bidx, $path) { return (int)$bidx . '|' . ltrim(str_replace('\\', '/', (string)$path), '/'); }
    // 이 책의 계정 값 — '1'(켬)·'0'(끔)·null(서버에 없음 = 이 기기 값 그대로)
    function mc_voff_get($bidx, $path) {
        $uid = mc_prefs_uid();
        if ($uid === '') return null;
        $f = __DIR__ . '/src/' . $uid . '_vprefs.json';
        if (!is_file($f)) return null;
        $S = function_exists('load_json_with_lock') ? load_json_with_lock($f) : json_decode((string)@file_get_contents($f), true);
        $r = (is_array($S) && is_array($S['o'] ?? null)) ? ($S['o'][mc_voff_key($bidx, $path)] ?? null) : null;
        $v = is_array($r) ? ($r['v'] ?? null) : null;
        return ($v === '1' || $v === '0') ? $v : null;
    }
    // 동영상 위치 — 키는 js/mc-video.js 의 'mc_vres:' 뒤 부분(bidx|경로 또는 bidx|압축 경로|속 영상 이름)
    function mc_vres_file() { $u = mc_prefs_uid(); return $u === '' ? '' : __DIR__ . '/src/' . $u . '_vres.json'; }
    function mc_vres_get($base) {
        $f = mc_vres_file(); $base = (string)$base;
        if ($f === '' || $base === '' || !is_file($f)) return [];
        $all = function_exists('load_json_with_lock') ? load_json_with_lock($f) : json_decode((string)@file_get_contents($f), true);
        if (!is_array($all)) return [];
        $out = [];
        foreach ($all as $k => $r) {
            if (!is_string($k) || !is_array($r) || !empty($r['x'])) continue; // x = 지운 표시(vres.php)
            if ($k !== $base && strpos($k, $base . '|') !== 0) continue;
            $out[$k] = ['t' => (int)($r['t'] ?? 0), 'd' => (int)($r['d'] ?? 0), 'at' => (int)($r['at'] ?? 0)];
        }
        return $out;
    }
}

?>
