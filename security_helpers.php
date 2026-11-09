<?php
/**
 * myComix 보안 패치 헬퍼 함수
 * XSS 취약점 방어를 위한 입력 검증 및 출력 인코딩 함수
 * 
 * @version 2.8 - 토큰 관련 함수 통합, safe_redirect 동적 경로 지원
 * @date 2026-01-11
 * 
 * ============================================================
 * 경로 검증 함수 사용 가이드
 * ============================================================
 * 
 * 1. validate_file_path($path, $base_dir)
 *    - 단일 base_dir에서 파일 경로 검증
 *    - 경로 순회 공격 방지 (.., NULL 바이트, 절대 경로)
 *    - 검증 성공 시 전체 경로 반환, 실패 시 false
 *    - 사용처: thumb.php, warmup.php, viewer.php 등 단일 폴더 작업
 * 
 * 2. resolve_path_from_basedirs($path, $base_dirs_array)
 *    - 다중 base_dir 환경에서 파일 경로 검색 및 검증
 *    - [N]/path 형식의 인덱스 접두사 지원
 *    - 검증 성공 시 상세 정보 배열 반환, 실패 시 false
 *    - 사용처: index.php 등 다중 폴더 탐색이 필요한 경우
 * 
 * 3. extract_basedir_index($path)
 *    - 경로에서 [N]/ 형식의 인덱스 추출
 *    - 예: "[1]/folder/file.zip" → [1, "folder/file.zip"]
 * 
 * 4. prepend_basedir_index($index, $path)
 *    - 경로에 인덱스 접두사 추가
 *    - 예: prepend_basedir_index(1, "file.zip") → "[1]/file.zip"
 * 
 * 선택 기준:
 * - 단일 폴더 작업 → validate_file_path()
 * - 다중 폴더 탐색 → resolve_path_from_basedirs()
 * - init_bidx() 호출 후라면 $base_dir이 이미 설정되므로 validate_file_path() 권장
 */

if (!function_exists('mc_path_within')) {
/** ★ (2026-11-04 00:47) 실제 경로가 기준 폴더 자신이거나 그 안인지 — 앞부분만 비교하면 'D:\comics' 기준에 'D:\comics_old\…' 도 통과했음 */
function mc_path_within($real_path, $real_base) {
    if (!is_string($real_path) || !is_string($real_base) || $real_base === '') return false;
    if ($real_path === $real_base) return true;
    $b = rtrim($real_base, '/\\');
    return strpos($real_path, $b . DIRECTORY_SEPARATOR) === 0 || (DIRECTORY_SEPARATOR === '\\' && strpos($real_path, $b . '/') === 0);
}
}

/**
 * 사용자 입력 검증 및 정제
 * 
 * @param mixed $input 입력값
 * @param string $type 입력 타입 (string, int, email, url, array)
 * @param mixed $default 기본값
 * @return mixed 정제된 값
 */
function sanitize_input($input, $type = 'string', $default = '') {
    if ($input === null || $input === '') {
        return $default;
    }
    // ★ (2026-11-02 08:49) 배열(?x[]=…)이 오면 기본값 — 아래 문자열 함수가 PHP 8 에서 TypeError(500)로 멈췄음
    if (is_array($input) || is_object($input)) {
        return $default;
    }

    switch ($type) {
        case 'int':
            $value = filter_var($input, FILTER_VALIDATE_INT);
            return ($value !== false) ? $value : $default;
            
        case 'float':
            $value = filter_var($input, FILTER_VALIDATE_FLOAT);
            return ($value !== false) ? $value : $default;
            
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
            
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
            
        case 'boolean':
            return filter_var($input, FILTER_VALIDATE_BOOLEAN);
            
        case 'array':
            if (!is_array($input)) {
                return $default;
            }
            return array_map(function($item) {
                return sanitize_input($item, 'string');
            }, $input);
            
        case 'path':
            // 경로에서 위험한 문자 제거
            // ★ (2026-11-02 09:03) 작은따옴표(')는 지우지 않음 — "Girl's Love" 같은 폴더가 "Girls Love" 로 바뀌어 열리지 않던 문제
            //   (Windows 이름에 못 쓰는 < > " 와 '..' 는 그대로 지움 / 출력은 모두 h()·encode_url()·http_build_query 로 이스케이프)
            $input = str_replace(['..', '<', '>', '"', '\\'], '', $input);
            return trim($input);
            
        case 'filename':
            // 파일명에서 위험한 문자 제거
            $input = preg_replace('/[^a-zA-Z0-9가-힣._\-\s]/', '', $input);
            return trim($input);
            
        case 'alphanum':
            // 영문자와 숫자만 허용
            return preg_replace('/[^a-zA-Z0-9]/', '', $input);
        
        case 'search':
            // 검색어용: 아포스트로피 유지, 큰따옴표/HTML태그만 이스케이프
            return htmlspecialchars(trim($input), ENT_COMPAT, 'UTF-8');
            
        case 'string':
        default:
            // 기본적인 HTML 인코딩
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * HTML 출력용 인코딩 + NFC 정규화
 * 
 * @param string $text 출력할 텍스트
 * @return string 인코딩된 텍스트
 */
function h($text) {
    $text = $text ?? '';
    // NFD → NFC 정규화 (자모분리 → 조합형)
    if (class_exists('Normalizer')) {
        $text = Normalizer::normalize($text, Normalizer::FORM_C);
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * HTML input value 속성용 인코딩 (아포스트로피 유지)
 * value="..." 형태로 큰따옴표로 감싸진 속성에서 사용
 * 아포스트로피(')를 &#039;로 변환하지 않음
 * 
 * @param string $text 출력할 텍스트
 * @return string 인코딩된 텍스트
 */
function hv($text) {
    $text = $text ?? '';
    // NFD → NFC 정규화 (자모분리 → 조합형)
    if (class_exists('Normalizer')) {
        $text = Normalizer::normalize($text, Normalizer::FORM_C);
    }
    // ENT_COMPAT: 큰따옴표만 이스케이프, 아포스트로피는 그대로 유지
    return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
}

/**
 * JavaScript 문자열용 인코딩
 * 
 * @param mixed $value JavaScript에서 사용할 값
 * @return string JSON 인코딩된 문자열
 */
function js($value) {
    // ★ (2026-11-02 11:39) 깨진 글자(UTF-8 아님) 이름이어도 false 가 되지 않게 — false 면 'const X = ;' 로 그 화면 스크립트가 통째로 멈췄음
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

/**
 * URL 파라미터용 인코딩
 * 
 * @param string $text URL에 포함될 텍스트
 * @return string URL 인코딩된 텍스트
 */
function u($text) {
    return urlencode($text ?? '');
}

/**
 * 파일 경로 파라미터 디코딩 (이중 인코딩 대응)
 * 
 * 브라우저/클라이언트에서 이중 인코딩된 URL을 안전하게 디코딩
 * 
 * ✅ 사용 권장 위치:
 * - thumb.php, warmup.php 등 JavaScript에서 호출되는 API
 * - viewer.php에서 imgfile 파라미터 처리 시
 * 
 * ✅ decode_url()과의 차이점:
 * - decode_url(): 단순 플레이스홀더 복원 ({pl}, {dt}, {pc})
 * - decode_file_param(): 이중 인코딩 자동 감지 및 처리 추가
 * 
 * @param string $param $_GET['file'] 등 URL 파라미터
 * @return string 디코딩된 파일 경로
 * 
 * @example
 * // JavaScript에서 encodeURIComponent() 2회 호출된 경우
 * // 원본: "파일.zip" → 1차 인코딩 → 2차 인코딩
 * // decode_file_param()으로 원본 복원
 * $file = decode_file_param($_GET['file']);
 */
function decode_file_param($param) {
    if (!is_string($param)) return is_scalar($param) ? (string)$param : ''; // ★ (2026-11-02 08:49) 배열이면 빈 값(500 오류 방지)
    // 1. + 기호 보존 (URL에서 공백으로 해석되는 것 방지)
    $value = str_replace('+', '%2B', $param);
    
    // 2. 기본 디코딩 (encode_url/decode_url 체계)
    $value = decode_url($value);
    
    // 3. 이중 인코딩 처리 (예: %257Bdt%257D → %7Bdt%7D → {dt})
    // 일부 클라이언트에서 encodeURIComponent() 2회 호출 시 발생
    if (strpos($value, '%') !== false) {
        $value = urldecode($value);
        $value = str_replace('{dt}', '.', $value);
    }
    
    return $value;
}

/**
 * 허용된 값 목록에서 검증
 * 
 * @param mixed $value 검증할 값
 * @param array $allowed 허용된 값 목록
 * @param mixed $default 기본값
 * @return mixed 검증된 값 또는 기본값
 */
function validate_whitelist($value, array $allowed, $default) {
    return in_array($value, $allowed, true) ? $value : $default;
}

/**
 * $_GET 파라미터 안전하게 가져오기
 * 
 * @param string $key 파라미터 키
 * @param string $type 데이터 타입
 * @param mixed $default 기본값
 * @return mixed 정제된 값
 */
function get_param($key, $type = 'string', $default = '') {
    return sanitize_input($_GET[$key] ?? $default, $type, $default);
}

/**
 * $_POST 파라미터 안전하게 가져오기
 * 
 * @param string $key 파라미터 키
 * @param string $type 데이터 타입
 * @param mixed $default 기본값
 * @return mixed 정제된 값
 */
function post_param($key, $type = 'string', $default = '') {
    return sanitize_input($_POST[$key] ?? $default, $type, $default);
}

/**
 * CSRF 토큰 생성
 * 
 * @return string CSRF 토큰
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF 토큰 검증
 * 
 * @param string $token 검증할 토큰
 * @return bool 검증 결과
 */
function verify_csrf_token($token) {
    // ★ (2026-10-30) 배열 등 문자열이 아닌 값이 오면 hash_equals TypeError(500) 대신 실패 처리
    return isset($_SESSION['csrf_token']) && is_string($token) && is_string($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF 토큰 HTML 폼 필드 생성
 * 
 * @return string HTML input 태그
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

/**
 * 정렬 모드 검증
 * 
 * @param string $sort 정렬 모드
 * @return string 검증된 정렬 모드
 */
function validate_sort_mode($sort) {
    $allowed_sorts = ['nameasc', 'namedesc', 'timeasc', 'timedesc', 'sizeasc', 'sizedesc', 'countasc', 'countdesc'];
    return validate_whitelist($sort, $allowed_sorts, 'nameasc');
}

/**
 * 모드 파라미터 검증
 * 
 * @param string $mode 모드 값
 * @param array $allowed_modes 허용된 모드 목록
 * @return string|null 검증된 모드 또는 null
 */
function validate_mode($mode, array $allowed_modes) {
    return in_array($mode, $allowed_modes, true) ? $mode : null;
}

// ============================================================
// 경로 검증 함수 (단일 base_dir용)
// ============================================================

if (!function_exists('validate_file_path')) {
/**
 * 파일 경로 보안 검증 (단일 base_dir용)
 * 
 * 검증 항목:
 * - 경로 순회 공격 방지 (.., NULL 바이트)
 * - 절대 경로 차단 (Windows 드라이브, UNC)
 * - base_dir 외부 접근 차단
 * 
 * @param string $path 검증할 상대 경로
 * @param string $base_dir 기준 디렉토리
 * @return string|false 검증된 전체 경로 또는 false
 * 
 * @example
 * $full_path = validate_file_path($_GET['file'], $base_dir);
 * if ($full_path === false) {
 *     http_response_code(403);
 *     exit;
 * }
 */
function validate_file_path($path, $base_dir) {
    if (!is_string($path) || !is_string($base_dir)) return false; // ★ (2026-11-02 09:58) 배열(path[]=…)이면 preg_match 에서 500 이던 것
    // 1단계: 위험한 패턴 차단
    // - 실제 경로 순회 패턴 (..)
    // - NULL 바이트 (파일시스템 공격)
    // - Windows 절대 경로 (C:\, D:/)
    // - UNC 경로 (\\server\share)
    if (preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $path) ||
        strpos($path, "\0") !== false ||
        preg_match('#^[a-z]:[/\\\\]#i', $path) ||
        preg_match('#^\\\\\\\\#', $path)) {
        return false;
    }
    
    // 2단계: 경로 정규화
    $path = trim($path, '/\\');
    $full_path = rtrim($base_dir, '/\\') . '/' . $path;
    
    // 3단계: base_dir의 실제 경로 확인
    $real_base = realpath($base_dir);
    if ($real_base === false) {
        return false;
    }
    
    // 4단계: 대상 경로 검증
    if (file_exists($full_path)) {
        $real_path = realpath($full_path);
        if ($real_path === false || !mc_path_within($real_path, $real_base)) {
            return false;
        }
        return $full_path;
    }
    
    // 5단계: 파일이 없는 경우 부모 디렉토리 검증
    $parent_dir = dirname($full_path);
    if (is_dir($parent_dir)) {
        $real_parent = realpath($parent_dir);
        if ($real_parent === false || !mc_path_within($real_parent, $real_base)) {
            return false;
        }
    }
    
    return $full_path;
}
}

// ✅ validate_path 별칭 제거됨 (2026-01-10)
// 모든 코드에서 validate_file_path() 직접 사용

/**
 * ✅ 안전한 리다이렉트 (보안 강화 버전)
 * - 외부 URL 차단
 * - 허용된 내부 경로만 리다이렉트
 * - Open Redirect 공격 방지
 * 
 * @param string $url 리다이렉트 URL
 * @param array $params URL 파라미터 (선택)
 * @param string $fallback 실패 시 기본 URL
 * 
 * @version 2.9 - 패턴 정의 통합, 정규식 제거로 성능 개선
 * @date 2026-01-11
 */
if (!function_exists('safe_redirect')) {
    function safe_redirect($url, array $params = [], $fallback = 'index.php') {
        // ✅ 빈 URL이면 fallback 사용
        if (empty($url)) {
            header('Location: ' . $fallback);
            exit;
        }
        // ★ (2026-11-08 전체 점검 2차 fix72) 제어 문자·공백·역슬래시가 든 주소, http(s):// 가 아닌 스킴으로 시작하는 주소(https:/\… · javascript: 등)는 처음 화면으로
        //   — 브라우저는 주소의 탭·줄바꿈을 지우고 역슬래시를 / 로 읽어 '/<탭>/다른곳/index.php'·'https:/\다른곳/index.php' 가 다른 사이트로 갔음(이 함수만으로 재현. 지금 부르는 곳은 브라우저가 정리한 Referer 뿐이라 실제로는 닿지 않음)
        if (!is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) && !preg_match('#^https?://#i', $url))) {
            header('Location: ' . $fallback);
            exit;
        }

        // ✅ 외부 URL 방지 (http://, https://, // 등)
        if (preg_match('/^(https?:)?\/\//i', $url)) {
            $parsed = parse_url($url);
            $current_host = $_SERVER['HTTP_HOST'] ?? '';
            
            // ★ (2026-11-02 11:39) 포트까지 같이 비교(host:8099 같은 주소에서 늘 첫 화면으로 가던 것) · 대소문자 무시
            $__ph = isset($parsed['host']) ? $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '') : null;
            if ($__ph !== null && strcasecmp($__ph, $current_host) !== 0 && strcasecmp($parsed['host'], $current_host) !== 0) {
                header('Location: ' . $fallback);
                exit;
            }
            
            // 같은 호스트라면 경로만 추출
            $url = $parsed['path'] ?? '/';
            if (isset($parsed['query'])) {
                $url .= '?' . $parsed['query'];
            }
            if (isset($parsed['fragment'])) {
                $url .= '#' . $parsed['fragment'];
            }
        }
        
        // ✅ 허용 파일 목록 (통합 정의 - static으로 1회만 생성)
        static $allowed_files = null;
        static $blocked_files = [
            'config.php', 'init.php', 'function.php', 
            'security_helpers.php', 'cache_util.php', 'archive_handler.php', 'bootstrap.php'
        ];
        
        if ($allowed_files === null) {
            $allowed_files = [];
            $php_files = @glob(__DIR__ . '/*.php');
            
            if ($php_files && count($php_files) > 0) {
                // 동적 스캔 성공: 차단 목록 제외한 모든 PHP 파일 허용
                foreach ($php_files as $file) {
                    $basename = basename($file);
                    if (!in_array($basename, $blocked_files, true)) {
                        $allowed_files[] = $basename;
                    }
                }
            }
            
            // 스캔 실패 시 핵심 파일 정적 추가
            if (empty($allowed_files)) {
                $allowed_files = [
                    'index.php', 'viewer.php', 'admin.php', 'login.php',
                    'bookmark.php', 'txt_viewer.php', 'epub_viewer.php',
                    'admin_translations.php', 'thumb.php', 'warmup.php',
                    'session_check.php', 'blank.php', 'privacy_shield.php'
                ];
            }
        }
        
        // ✅ 경로 검증 (정규식 대신 문자열 비교로 성능 개선)
        $path_only = parse_url($url, PHP_URL_PATH) ?? $url;
        $path_only = ltrim($path_only, './');  // 앞의 ./ 또는 / 제거
        if (preg_match('#^\s*(//|\\\\|/\\\\)#', $url)) { header('Location: ' . $fallback); exit; } // ★ (2026-11-02 12:04) '//다른곳' 은 외부로 가므로 막음
        if (strpos($path_only, '/') !== false && strpos($path_only, '..') === false) $path_only = basename($path_only); // ★ (2026-11-02 11:39) 하위 폴더 설치(/mycomix/index.php)도 파일 이름으로 확인
        
        // 루트 또는 허용된 파일인지 확인
        $is_allowed = empty($path_only) || in_array($path_only, $allowed_files, true);
        
        if (!$is_allowed) {
            header('Location: ' . $fallback);
            exit;
        }
        
        // ✅ 추가 파라미터가 있으면 병합
        if (!empty($params)) {
            $query = http_build_query($params);
            $url .= (strpos($url, '?') === false ? '?' : '&') . $query;
        }
        
        header('Location: ' . $url);
        exit;
    }
}

// ============================================================
// 에러 응답 함수 (용도별 분리)
// ============================================================
// 
// 선택 가이드:
// - simple_error_exit(): 이미지/파일 스트리밍 API → 텍스트만 반환
// - json_response(): AJAX 요청 → JSON 반환
// - die_with_error(): 일반 페이지 → HTML 에러 페이지 반환
// ============================================================

/**
 * 간단한 에러 메시지 출력 후 종료 (API/스트리밍용)
 * 
 * 사용처: viewer.php 이미지 스트리밍, 파일 다운로드 등
 * HTML 페이지 없이 단순 텍스트로 에러 응답
 * 
 * @param int $code HTTP 상태 코드
 * @param string $message 에러 메시지 (경로 정보 포함 금지)
 */
if (!function_exists('simple_error_exit')) {
    function simple_error_exit($code = 403, $message = null) {
        if ($message === null) {
            $message = function_exists('__') ? __('err_access_denied') : '접근 권한이 없습니다.';
        }
        if (!headers_sent()) {
            http_response_code($code);
        }
        echo $message;
        exit;
    }
}

/**
 * JSON 응답 출력 (AJAX용)
 * 
 * 사용처: AJAX 요청 응답, API 엔드포인트
 * 
 * @param mixed $data 응답 데이터 (배열 또는 객체)
 * @param int $status HTTP 상태 코드
 */
if (!function_exists('json_response')) {
    function json_response($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }
}

/**
 * HTML 에러 페이지 표시 및 종료
 * 
 * 사용처: 일반 웹페이지 접근 시 에러
 * 브랜딩이 적용된 에러 페이지 렌더링
 * 
 * @param string $message 에러 메시지
 * @param int $code HTTP 상태 코드
 */
if (!function_exists('die_with_error')) {
    function die_with_error($message, $code = 400) {
        http_response_code($code);
        $_branding_file = __DIR__ . '/src/branding.json';
        $_page_title = 'myComix';
        if (file_exists($_branding_file)) {
            $_branding_data = json_decode(file_get_contents($_branding_file), true);
            $_page_title = $_branding_data['page_title'] ?? 'myComix';
        }
        echo '<!DOCTYPE html>
<html lang="' . get_html_lang() . '">
<head>
    <meta charset="UTF-8">
    <title>' . __h('err_occurred') . ' - ' . htmlspecialchars($_page_title) . '</title>
    <style>
        body { font-family: "Malgun Gothic", sans-serif; text-align: center; padding: 50px; }
        .error { color: #d9534f; font-size: 18px; }
    </style>
</head>
<body>
    <h1>' . __h('err_occurred') . '</h1>
    <p class="error">' . h($message) . '</p>
    <p><a href="javascript:history.back()">' . __h('back') . '</a></p>
</body>
</html>';
        exit;
    }
}

/**
 * 설정값 안전하게 저장 (config.php용)
 * 
 * @param array $config 설정 배열
 * @param string $file_path 저장할 파일 경로
 * @return bool 저장 성공 여부
 */
function save_config_safe(array $config, $file_path = 'config.json') {
    // JSON 형식으로 저장 (더 안전함)
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($json === false) {
        return false;
    }
    
    // 원자적 쓰기 (임시 파일 사용)
    $temp_file = $file_path . '.tmp';
    if (@file_put_contents($temp_file, $json, LOCK_EX) === false) {
        return false;
    }
    
    return rename($temp_file, $file_path);
}

/**
 * 설정값 안전하게 불러오기
 * 
 * @param string $file_path 설정 파일 경로
 * @return array|false 설정 배열 또는 false
 */
function load_config_safe($file_path = 'config.json') {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $json = file_get_contents($file_path);
    if ($json === false) {
        return false;
    }
    
    $config = json_decode($json, true);
    return is_array($config) ? $config : false;
}

/**
 * 페이지네이션 파라미터 검증
 * 
 * @param int $page 페이지 번호
 * @param int $max_page 최대 페이지
 * @return int 검증된 페이지 번호
 */
function validate_page($page, $max_page = 1000) {
    $page = (int)$page;
    if ($page < 0) return 0;
    if ($page > $max_page) return $max_page;
    return $page;
}

/**
 * 파일 확장자 검증
 * 
 * @param string $filename 파일명
 * @param array $allowed_extensions 허용된 확장자
 * @return bool 검증 결과
 */
function validate_file_extension($filename, array $allowed_extensions) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed_extensions, true);
}

/**
 * ✅ 정수형 안전 출력 (JavaScript 컨텍스트용)
 * 
 * @param mixed $value 출력할 값
 * @return int 정수로 변환된 값
 */
function safe_int($value) {
    return (int)$value;
}

// ============================================================
// 삭제 작업용 토큰 함수 (CSRF 대안)
// ============================================================

if (!function_exists('get_delete_token')) {
/**
 * 삭제 작업용 세션 토큰 생성/반환
 * GET 방식 삭제 요청에서 CSRF 방어용으로 사용
 * 
 * @return string 삭제 토큰
 */
function get_delete_token() {
    if (empty($_SESSION['delete_token'])) {
        $_SESSION['delete_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['delete_token'];
}
}

if (!function_exists('verify_delete_token')) {
/**
 * 삭제 토큰 검증
 * 
 * @param string $token 검증할 토큰
 * @return bool 유효 여부
 */
function verify_delete_token($token) {
    if (empty($token) || empty($_SESSION['delete_token']) || !is_string($token) || !is_string($_SESSION['delete_token'])) { // ★ (2026-10-30) 배열 입력 TypeError 방지
        return false;
    }
    return hash_equals($_SESSION['delete_token'], $token);
}
}

// ============================================================
// 상태 변경 요청 검증 (통합)
// ============================================================

if (!function_exists('verify_state_change_request')) {
/**
 * 상태 변경 요청 검증 (통일된 방식)
 * 
 * 세션 확인 + 토큰 검증을 통합한 보안 함수
 * bookmark.php, 기타 상태 변경 API에서 공통 사용
 * 
 * @param bool $require_token true면 토큰 필수 (삭제 등 위험 작업)
 * @return bool 검증 통과 여부
 * 
 * @example
 * // 삭제 작업 (토큰 필수)
 * if (!verify_state_change_request(true)) {
 *     http_response_code(403);
 *     exit;
 * }
 * 
 * // 자동저장 (세션만 확인)
 * if (!verify_state_change_request(false)) {
 *     http_response_code(403);
 *     exit;
 * }
 */
function verify_state_change_request($require_token = false) {
    // 1. 세션 확인 (필수)
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    // ★ (2026-11-02 08:56) 다른 사이트에서 보낸 요청은 거절(브라우저가 붙이는 Sec-Fetch-Site — 없으면 옛 브라우저라 전처럼)
    //   — 토큰 없이 받는 저장(자동 저장·북마크·즐겨찾기)을 남의 페이지 링크로 바꾸지 못하게
    $__sfs = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($__sfs === 'cross-site' || $__sfs === 'same-site') { // ★ (2026-11-02 21:17) same-site(같은 상위 도메인의 다른 하위 도메인 — 예: 남이 만든 다른 *.mooo.com 페이지)도 거절. 이 앱의 화면에서 보내면 same-origin
        return false;
    }
    
    // 2. POST 요청이면 CSRF 토큰 검증
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if ($require_token || !empty($token)) {
            return verify_csrf_token($token);
        }
        return true; // POST지만 토큰 불필요한 경우
    }
    
    // 3. GET 요청 처리
    $token = $_GET['token'] ?? '';
    
    // 토큰 필수인 경우 (삭제 작업 등)
    if ($require_token) {
        return !empty($token) && verify_delete_token($token);
    }
    
    // 토큰 있으면 검증, 없으면 세션만으로 허용 (autosave 등)
    if (!empty($token)) {
        return verify_delete_token($token);
    }
    
    return true;
}
}

// ============================================================
// 다중 base_dir 경로 처리 함수 (config.php에서 이동)
// ============================================================

if (!function_exists('extract_basedir_index')) {
/**
 * 경로에서 base_dir 인덱스 추출
 * 예: "[1]/folder/file.zip" → [1, "folder/file.zip"]
 * 
 * @param string $path 경로 문자열
 * @return array [인덱스, 정리된 경로]
 */
function extract_basedir_index($path) {
    if (preg_match('/^\[(\d+)\]\//', $path, $matches)) {
        return [(int)$matches[1], preg_replace('/^\[\d+\]\//', '', $path)];
    }
    return [0, $path];
}
}

if (!function_exists('prepend_basedir_index')) {
/**
 * base_dir 인덱스를 경로에 추가
 * 
 * @param int $index base_dir 인덱스
 * @param string $path 경로
 * @return string 인덱스가 붙은 경로
 */
function prepend_basedir_index($index, $path) {
    if ($index > 0) {
        return "[{$index}]/" . ltrim($path, '/');
    }
    return $path;
}
}

if (!function_exists('resolve_path_from_basedirs')) {
/**
 * 다중 base_dir에서 파일 경로 검증 및 실제 경로 반환
 * 
 * 단일 폴더 작업에는 validate_file_path() 사용 권장
 * 이 함수는 다중 폴더 탐색이 필요한 경우에 사용
 * 
 * @param string $relative_path 상대 경로 (또는 [N]/path 형식)
 * @param array|null $base_dirs_array base_dir 배열 (null이면 전역 $base_dirs 사용)
 * @return array|false 성공 시 ['index', 'base_dir', 'full_path', 'relative_path'], 실패 시 false
 * 
 * @example
 * $result = resolve_path_from_basedirs('[1]/manga/book.zip');
 * if ($result !== false) {
 *     $full_path = $result['full_path'];
 *     $bidx = $result['index'];
 * }
 */
function resolve_path_from_basedirs($relative_path, $base_dirs_array = null) {
    global $base_dirs;
    
    if ($base_dirs_array === null) {
        $base_dirs_array = $base_dirs;
    }
    
    if (empty($base_dirs_array)) {
        return false;
    }
    
    // 경로 순회 공격 방지
    if (preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $relative_path) ||
        strpos($relative_path, "\0") !== false) {
        return false;
    }
    
    // 경로에서 인덱스 추출
    list($explicit_index, $clean_path) = extract_basedir_index($relative_path);
    $clean_path = trim($clean_path, '/\\');
    
    // 명시적 인덱스가 있으면 해당 base_dir만 확인
    if ($explicit_index > 0 && isset($base_dirs_array[$explicit_index])) {
        $full_path = rtrim($base_dirs_array[$explicit_index], '/\\') . '/' . $clean_path;
        
        if (file_exists($full_path)) {
            $real_base = realpath($base_dirs_array[$explicit_index]);
            $real_path = realpath($full_path);
            
            if ($real_base !== false && $real_path !== false && 
                mc_path_within($real_path, $real_base)) {
                return [
                    'index' => $explicit_index,
                    'base_dir' => $base_dirs_array[$explicit_index],
                    'full_path' => $full_path,
                    'relative_path' => $clean_path
                ];
            }
        }
        return false;
    }
    
    // 모든 base_dir에서 검색
    foreach ($base_dirs_array as $index => $base_dir) {
        $full_path = rtrim($base_dir, '/\\') . '/' . $clean_path;
        
        if (file_exists($full_path)) {
            $real_base = realpath($base_dir);
            $real_path = realpath($full_path);
            
            if ($real_base !== false && $real_path !== false && 
                mc_path_within($real_path, $real_base)) {
                return [
                    'index' => $index,
                    'base_dir' => $base_dir,
                    'full_path' => $full_path,
                    'relative_path' => $clean_path
                ];
            }
        }
    }
    
    return false;
}
}

// ============================================================
// 로그인 유지 토큰 관리 함수 (통합)
// login.php, init.php에서 중복 제거되어 여기로 통합됨
// @date 2026-01-11
// ============================================================

if (!function_exists('get_remember_tokens_file')) {
/**
 * 로그인 유지 토큰 파일 경로 반환
 * @return string 토큰 파일 경로
 */
function get_remember_tokens_file() {
    return __DIR__ . '/src/remember_tokens.json';
}
}

if (!function_exists('load_remember_tokens')) {
/**
 * 로그인 유지 토큰 로드 (파일 잠금)
 * @return array 토큰 배열
 */
function load_remember_tokens() {
    $file = get_remember_tokens_file();
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

if (!function_exists('save_remember_tokens')) {
/**
 * 로그인 유지 토큰 저장 (파일 잠금)
 * @param array $tokens 토큰 배열
 * @return bool 성공 여부
 */
function save_remember_tokens($tokens) {
    $file = get_remember_tokens_file();
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    // 보안: src 폴더 웹 접근 차단
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n", LOCK_EX);
    }
    
    $fp = fopen($file, 'c+');
    if (!$fp) return false;
    
    $__tj = json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE); // ★ (2026-10-30) 실패 시 비우지 않음
    if ($__tj !== false && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $__tj);
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return true;
}
}

if (!function_exists('get_device_hash')) {
/**
 * User-Agent 해시 생성 (기기 식별용)
 * @return string 16자리 해시
 */
function get_device_hash() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return substr(md5($ua), 0, 16);
}
}

if (!function_exists('generate_remember_token')) {
/**
 * 랜덤 토큰 생성
 * @return string 64자리 hex 토큰
 */
function generate_remember_token() {
    return bin2hex(random_bytes(32));
}
}

if (!function_exists('mc_remember_tokens_modify')) {
/**
 * ★ (2026-11-07 fix53) 로그인 유지 토큰 파일을 '읽기 → 고치기 → 저장'까지 한 번의 잠금(LOCK_EX) 안에서
 *   예전: 읽기(load)와 저장(save)이 따로 잠겨, 동시에 온 요청(브라우저 업데이트 날 탭 여러 개가 한꺼번에 열림 등)이
 *         서로의 새 토큰을 덮어써 지웠음 → 브라우저가 지워진 토큰을 쥐고 있으면 그날은 세션으로 버티다 다음 날 로그인이 풀림
 *         (시험: 동시 12개 중 1~6개 사라짐 — 원본 소스도 같음)
 * @param callable $fn function(array &$tokens): bool  — 바꿨으면 true(그때만 저장)
 * @return mixed $fn 이 돌려준 값(파일을 못 열거나 잠그지 못하면 null)
 */
function mc_remember_tokens_modify(callable $fn) {
    $file = get_remember_tokens_file();
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $htaccess = $dir . '/.htaccess'; // 보안: src 폴더 웹 접근 차단(save_remember_tokens 와 같음)
    if (!file_exists($htaccess)) @file_put_contents($htaccess, "Deny from all\n", LOCK_EX);
    $fp = @fopen($file, 'c+');
    if (!$fp) return null;
    $ret = null;
    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $tokens = ($raw === '' || $raw === false) ? [] : json_decode($raw, true);
        if (!is_array($tokens)) { // 깨진 파일: 예전처럼 빈 목록에서 다시 시작(깨진 토큰은 어차피 확인할 수 없음) — 내용은 .broken 으로 한 부 남김
            @file_put_contents($file . '.broken', (string)$raw, LOCK_EX);
            $tokens = [];
        }
        {
            $changed = false;
            $ret = $fn($tokens, $changed);
            if ($changed) {
                $j = json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($j !== false) { ftruncate($fp, 0); rewind($fp); fwrite($fp, $j); fflush($fp); }
            }
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $ret;
}
}

if (!function_exists('mc_remember_token_entry')) {
/** 새 토큰 한 칸(예전 create_remember_token 과 같은 내용) */
function mc_remember_token_entry($user_id, $days) {
    return [
        'user_id' => $user_id,
        'expires' => time() + ($days * 24 * 60 * 60),
        'created' => time(),
        'ip' => filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown',
        'device_hash' => get_device_hash()
    ];
}
}

if (!function_exists('create_remember_token')) {
/**
 * 로그인 유지 토큰 생성
 * @param string $user_id 사용자 ID
 * @param int $days 유효 기간 (일)
 * @return string 생성된 토큰
 */
function create_remember_token($user_id, $days = 3650) {
    $token = generate_remember_token();
    // ★ fix53 한 번의 잠금 안에서 만료 정리 + 새 토큰 추가(동시에 만든 다른 토큰을 덮어쓰지 않게)
    mc_remember_tokens_modify(function (&$tokens, &$changed) use ($token, $user_id, $days) {
        $now = time();
        $tokens = array_filter($tokens, function($t) use ($now) {
            return (is_array($t) ? ($t['expires'] ?? 0) : 0) > $now; // 만료된 토큰 정리
        });
        $tokens[$token] = mc_remember_token_entry($user_id, $days); // 새 토큰 추가
        $changed = true;
        return true;
    });
    return $token;
}
}

if (!function_exists('verify_remember_token')) {
/**
 * 로그인 유지 토큰 검증
 * @param string $token 토큰
 * @param bool $auto_refresh 다른 기기면 새 토큰 발급
 * @return string|false 사용자 ID 또는 false
 */
function verify_remember_token($token, $auto_refresh = true) {
    if (empty($token) || !is_string($token)) return false; // ★ (2026-11-02 08:50) 쿠키가 배열(remember_token[]=…)이면 TypeError(모든 화면 500)
    
    $tokens = load_remember_tokens();
    if (!isset($tokens[$token]) || !is_array($tokens[$token])) return false;
    
    $data = $tokens[$token];
    if (($data['expires'] ?? 0) < time()) {
        // 만료된 토큰 삭제(★ fix53 잠근 채로)
        mc_remember_tokens_modify(function (&$t, &$changed) use ($token) {
            if (isset($t[$token]) && (is_array($t[$token]) ? ($t[$token]['expires'] ?? 0) : 0) < time()) { unset($t[$token]); $changed = true; }
            return true;
        });
        return false;
    }
    
    // 기기 해시 확인 - 다른 기기면 새 토큰 발급
    $current_device = get_device_hash();
    $token_device = $data['device_hash'] ?? '';
    
    if ($auto_refresh && !empty($token_device) && $token_device !== $current_device) {
        $user_id = $data['user_id'];
        // ★ fix53 새 토큰 발급과 옛 토큰 정리를 한 번의 잠금 안에서 —
        //   · 같은 옛 토큰으로 동시에 온 요청(탭 여러 개)은 먼저 만든 새 토큰을 같이 받음(전엔 요청마다 새 토큰을 만들고 서로 덮어써 일부가 사라졌음)
        //   · ★ (2026-11-02 08:50) 옛 토큰은 지움 — 남겨 두면 기기(브라우저)가 바뀔 때마다 10년짜리 토큰이 쌓이고, 훔친 토큰을 다른 기기에서 써도 원래 것이 계속 살아 있음
        //   · ★ (2026-11-02 09:35) 바로 지우지 않고 10분 뒤 만료로 — 브라우저 업데이트 뒤 탭 여러 개가 한꺼번에 열릴 때 늦은 요청이 로그인 화면으로 가지 않게
        $new_token = mc_remember_tokens_modify(function (&$t, &$changed) use ($token, $user_id, $current_device) {
            $now = time();
            if (!isset($t[$token]) || !is_array($t[$token]) || ($t[$token]['expires'] ?? 0) < $now) return null; // 그 사이 지워짐(로그아웃 등)
            $nx = (string)($t[$token]['next'] ?? '');
            if ($nx !== '' && isset($t[$nx]) && is_array($t[$nx]) && ($t[$nx]['device_hash'] ?? '') === $current_device
                && (string)($t[$nx]['user_id'] ?? '') === (string)$user_id && ($t[$nx]['expires'] ?? 0) > $now) return $nx; // 방금 다른 탭이 받은 새 토큰
            $t = array_filter($t, function($x) use ($now) { return (is_array($x) ? ($x['expires'] ?? 0) : 0) > $now; }); // 만료된 토큰 정리(예전 create_remember_token 과 같음)
            $nt = generate_remember_token();
            $t[$nt] = mc_remember_token_entry($user_id, 3650);
            if ((int)($t[$token]['expires'] ?? 0) > $now + 600) $t[$token]['expires'] = $now + 600;
            $t[$token]['next'] = $nt;
            $changed = true;
            return $nt;
        });
        if (!is_string($new_token) || $new_token === '') {
            // 파일을 못 열었거나 그 사이 토큰이 지워졌으면: 지워진 경우는 로그인하지 않음, 파일 문제면 예전처럼 이번만 통과(쿠키는 그대로)
            $chk = load_remember_tokens();
            return (isset($chk[$token]) && is_array($chk[$token]) && ($chk[$token]['expires'] ?? 0) >= time()) ? $user_id : false;
        }
        
        // 새 토큰으로 쿠키 갱신
        $cookie_options = [
            'expires' => time() + (3650 * 24 * 60 * 60),
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        setcookie('remember_token', $new_token, $cookie_options);
        
        return $user_id;
    }
    
    return $data['user_id'];
}
}

if (!function_exists('delete_remember_token')) {
/**
 * 특정 토큰 삭제
 * @param string $token 삭제할 토큰
 */
function delete_remember_token($token) {
    if (empty($token) || !is_string($token)) return; // ★ (2026-11-02 08:51) 배열 쿠키
    
    // ★ fix53 잠근 채로 지움(그 사이 다른 요청이 만든 토큰을 덮어쓰지 않게) · 이 토큰에서 갈아탄 새 토큰(next)도 같이 지움(로그아웃했는데 다른 탭이 받은 새 토큰으로 다시 로그인되지 않게)
    mc_remember_tokens_modify(function (&$tokens, &$changed) use ($token) {
        if (isset($tokens[$token])) {
            $nx = is_array($tokens[$token]) ? (string)($tokens[$token]['next'] ?? '') : '';
            unset($tokens[$token]); $changed = true;
            if ($nx !== '' && isset($tokens[$nx])) unset($tokens[$nx]);
        }
        // ★ fix54 반대로 이 토큰으로 갈아타기 전 옛 토큰(10분 유예 중)도 지움 — 새 토큰으로 로그아웃해도 옛 토큰으로 다시 로그인·새 토큰 발급이 됐음
        foreach ($tokens as $k => $v) {
            if (is_array($v) && (string)($v['next'] ?? '') === $token) { unset($tokens[$k]); $changed = true; }
        }
        return true;
    });
}
}

if (!function_exists('delete_user_remember_tokens')) {
/**
 * 사용자의 모든 토큰 삭제 (모든 기기 로그아웃)
 * @param string $user_id 사용자 ID
 */
function delete_user_remember_tokens($user_id) {
    // ★ fix53 잠근 채로(그 사이 다른 사용자의 새 토큰을 덮어쓰지 않게)
    mc_remember_tokens_modify(function (&$tokens, &$changed) use ($user_id) {
        $n = count($tokens);
        $tokens = array_filter($tokens, function($t) use ($user_id) {
            return (string)(is_array($t) ? ($t['user_id'] ?? '') : '') !== (string)$user_id; // ★ (2026-11-02 11:33) 숫자만 된 아이디는 회원 목록 키가 정수라 하나도 안 지워졌음
        });
        $changed = count($tokens) !== $n;
        return true;
    });
}
}

// ============================================================
// 2FA / TOTP (Time-based One-Time Password) 함수
// Google Authenticator 호환
// ============================================================

if (!function_exists('totp_base32_decode')) {
/**
 * Base32 디코딩 (RFC 4648)
 * @param string $input Base32 인코딩된 문자열
 * @return string 디코딩된 바이너리 데이터
 */
function totp_base32_decode($input) {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(str_replace(['=', ' ', '-'], '', $input));
    
    $buffer = 0;
    $bitsLeft = 0;
    $result = '';
    
    for ($i = 0; $i < strlen($input); $i++) {
        $val = strpos($map, $input[$i]);
        if ($val === false) continue;
        
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $result .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    
    return $result;
}
}

if (!function_exists('totp_base32_encode')) {
/**
 * Base32 인코딩 (RFC 4648)
 * @param string $input 바이너리 데이터
 * @return string Base32 인코딩된 문자열
 */
function totp_base32_encode($input) {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $result = '';
    $buffer = 0;
    $bitsLeft = 0;
    
    for ($i = 0; $i < strlen($input); $i++) {
        $buffer = ($buffer << 8) | ord($input[$i]);
        $bitsLeft += 8;
        
        while ($bitsLeft >= 5) {
            $bitsLeft -= 5;
            $result .= $map[($buffer >> $bitsLeft) & 0x1F];
        }
    }
    
    if ($bitsLeft > 0) {
        $result .= $map[($buffer << (5 - $bitsLeft)) & 0x1F];
    }
    
    return $result;
}
}

if (!function_exists('generate_totp_secret')) {
/**
 * TOTP 비밀키 생성 (160비트, 20바이트)
 * @return string Base32 인코딩된 비밀키
 */
function generate_totp_secret() {
    $bytes = random_bytes(20);
    return totp_base32_encode($bytes);
}
}

if (!function_exists('get_totp_code')) {
/**
 * 현재 TOTP 코드 계산 (RFC 6238)
 * @param string $secret Base32 인코딩된 비밀키
 * @param int|null $timestamp Unix 타임스탬프 (기본: 현재 시간)
 * @param int $digits 코드 자릿수 (기본: 6)
 * @param int $period 유효 기간 초 (기본: 30)
 * @return string 6자리 OTP 코드
 */
function get_totp_code($secret, $timestamp = null, $digits = 6, $period = 30) {
    if ($timestamp === null) {
        $timestamp = time();
    }
    
    $counter = floor($timestamp / $period);
    $key = totp_base32_decode($secret);
    
    // counter를 8바이트 big-endian으로 변환
    $data = pack('N*', 0, $counter);
    
    // HMAC-SHA1
    $hash = hash_hmac('sha1', $data, $key, true);
    
    // Dynamic Truncation
    $offset = ord($hash[19]) & 0x0F;
    $binary = ((ord($hash[$offset]) & 0x7F) << 24) |
              ((ord($hash[$offset + 1]) & 0xFF) << 16) |
              ((ord($hash[$offset + 2]) & 0xFF) << 8) |
              (ord($hash[$offset + 3]) & 0xFF);
    
    $otp = $binary % pow(10, $digits);
    return str_pad($otp, $digits, '0', STR_PAD_LEFT);
}
}

if (!function_exists('verify_totp')) {
/**
 * TOTP 코드 검증 (시간 오차 허용)
 * @param string $secret Base32 인코딩된 비밀키
 * @param string $code 사용자 입력 코드
 * @param int $window 허용 시간 윈도우 (기본: 1 = ±30초)
 * @return bool 유효 여부
 */
function verify_totp($secret, $code, $window = 1) {
    return mc_totp_match_step($secret, $code, $window) !== false;
}
}

if (!function_exists('mc_reauth_locked')) {
/**
 * ★ (2026-11-08 전체 점검 4차 fix77) 로그인한 뒤 현재 비밀번호·2단계 코드를 다시 확인하는 곳(내 정보의 비밀번호·이메일 바꾸기·탈퇴, 2단계 끄기·지우기·백업 코드 새로)의 틀린 횟수
 *   전엔 횟수 제한이 없어 로그인된 세션만 있으면 현재 비밀번호를 끝없이 맞혀 볼 수 있었음 → 로그인 시도 제한과 같은 기록·같은 설정(기본 5분에 5번, 15분 잠금, 화이트리스트 IP 제외)
 */
function mc_reauth_locked() {
    if (!class_exists('IPBlocker')) return false;
    try { $r = (new IPBlocker())->checkBruteforce(); return !empty($r['blocked']); } catch (\Throwable $e) { return false; }
}
function mc_reauth_fail($user_id) {
    if (!class_exists('IPBlocker')) return;
    try { (new IPBlocker())->recordLoginFailure(null, is_string($user_id) ? $user_id : ''); } catch (\Throwable $e) {}
}
}

if (!function_exists('mc_totp_match_step')) {
/**
 * ★ (2026-11-08 전체 점검 fix73) 맞는 코드면 그 코드의 시간 칸 번호(30초 단위), 아니면 false — verify_totp 와 같은 판단(같은 칸 범위·같은 비교)
 *   · 문자열이 아니면 false(부르는 곳 login.php·index.php 가 이미 배열을 빈 값으로 바꾸므로 실제로는 오지 않음 — 함수 혼자 쓰일 때 대비)
 */
function mc_totp_match_step($secret, $code, $window = 1) {
    if (!is_string($code) || !is_string($secret)) return false;
    $code = preg_replace('/\s+/', '', $code); // 공백 제거
    
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    
    $timestamp = time();
    $period = 30;
    
    // 시간 오차 허용 (window * 30초 전후)
    for ($i = -$window; $i <= $window; $i++) {
        $checkTime = $timestamp + ($i * $period);
        $expected = get_totp_code($secret, $checkTime);
        
        if (hash_equals($expected, $code)) {
            return (int)floor($checkTime / $period);
        }
    }
    
    return false;
}
}

if (!function_exists('get_totp_qr_url')) {
/**
 * Google Authenticator용 QR 코드 URL 생성
 * @param string $secret Base32 비밀키
 * @param string $label 계정 라벨 (예: user@example.com)
 * @param string $issuer 발급자 이름 (예: myComix)
 * @return string otpauth:// URI
 */
function get_totp_qr_url($secret, $label, $issuer = 'myComix') {
    $label = rawurlencode($label);
    $issuer = rawurlencode($issuer);
    $secret = str_replace(' ', '', strtoupper($secret));
    
    return "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
}
}

if (!function_exists('get_totp_settings_file')) {
/**
 * TOTP 설정 파일 경로 반환
 * @return string 파일 경로
 */
function get_totp_settings_file() {
    return __DIR__ . '/src/totp_settings.json';
}
}

if (!function_exists('load_totp_settings')) {
/**
 * TOTP 설정 로드 (전역 - 레거시 호환)
 * @return array 설정 배열
 */
function load_totp_settings() {
    $file = get_totp_settings_file();
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
    return [
        'enabled' => false,
        'secret' => '',
        'created_at' => null,
        'backup_codes' => []
    ];
}
}

if (!function_exists('save_totp_settings')) {
/**
 * TOTP 설정 저장 (전역 - 레거시 호환)
 * @param array $settings 설정 배열
 * @return bool 성공 여부
 */
function save_totp_settings($settings) {
    $file = get_totp_settings_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT)) !== false;
}
}

// ============================================================
// 사용자별 2FA 설정 함수
// ============================================================

if (!function_exists('get_user_totp_file')) {
/**
 * 사용자별 TOTP 설정 파일 경로
 * @return string 파일 경로
 */
function get_user_totp_file() {
    return __DIR__ . '/src/totp_users.json';
}
}

if (!function_exists('load_all_user_totp')) {
/**
 * 모든 사용자 TOTP 설정 로드
 * @return array 사용자별 설정
 */
// ★ (2026-11-04 00:46) 읽기는 LOCK_SH, 쓰기는 LOCK_EX 안에서 읽고-고치고-쓰기 — 전엔 잠금 없이 써서(파일을 비운 순간) 다른 요청이 빈 내용을 읽으면
//   2단계가 '꺼짐'으로 판정돼 OTP 없이 로그인됐고, 그 빈 내용으로 저장하면 다른 회원 설정이 지워질 수 있었음
//   $ok: 파일이 있는데 못 읽거나 해석 실패면 false(is_user_2fa_enabled 는 이때 '켜짐'으로 보고 로그인 거절)
function load_all_user_totp(&$ok = null) {
    $ok = true;
    $file = get_user_totp_file();
    if (!file_exists($file)) {
        return [];
    }
    for ($try = 0; $try < 3; $try++) {
        $fp = @fopen($file, 'rb');
        if ($fp) {
            @flock($fp, LOCK_SH);
            $raw = stream_get_contents($fp);
            @flock($fp, LOCK_UN);
            fclose($fp);
            // ★ (2026-11-04 01:16) 빈 파일(0바이트)은 '설정 없음'으로 — 잠금으로 쓰는 중인 빈 내용은 이제 안 보이고, 남는 빈 파일은 옛 코드가 쓰다 끊긴 것이라 거절하면 모든 회원이 로그인 못 했음
            if ($raw === '' || (is_string($raw) && trim($raw) === '')) return [];
            if (is_string($raw)) {
                $data = json_decode($raw, true);
                if (is_array($data)) return $data;
            }
        }
        if (!file_exists($file)) return [];
        usleep(50000);
    }
    $ok = false;
    error_log('[mycomix] totp_users.json 을 읽지 못함(깨짐?) — 2단계 확인을 거절 쪽으로 처리');
    return [];
}
}

if (!function_exists('mc_totp_modify')) {
/**
 * 잠금(LOCK_EX) 안에서 전체 TOTP 설정을 읽고 $fn(&$all) 로 고친 뒤 저장 — $fn 이 false 를 돌려주면 저장 안 함
 * 파일이 있는데 해석 실패면 덮어쓰지 않고 null
 * @return mixed $fn 의 반환값(실패 시 null)
 */
function mc_totp_modify(callable $fn) {
    $file = get_user_totp_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $fp = @fopen($file, 'c+b');
    if (!$fp) return null;
    if (!@flock($fp, LOCK_EX)) { fclose($fp); return null; }
    $raw = stream_get_contents($fp);
    $all = [];
    if (is_string($raw) && trim($raw) !== '') {
        $all = json_decode($raw, true);
        if (!is_array($all)) {
            @flock($fp, LOCK_UN); fclose($fp);
            error_log('[mycomix] totp_users.json 해석 실패 — 덮어쓰지 않음');
            return null;
        }
    }
    $res = $fn($all);
    if ($res !== false) {
        $json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $res = null;
        } else {
            ftruncate($fp, 0);
            rewind($fp);
            $__w = fwrite($fp, $json);
            if ($__w === false || $__w < strlen($json)) {
                // ★ (2026-11-04 01:50) 쓰기 실패(디스크 가득 등)면 원래 내용으로 되돌림 — 빈 파일로 남으면 모든 회원 2단계가 꺼진 것으로 보였음
                $res = null;
                ftruncate($fp, 0);
                rewind($fp);
                if (is_string($raw) && $raw !== '') @fwrite($fp, $raw);
                error_log('[mycomix] totp_users.json 저장 실패 — 원래 내용으로 되돌림');
            }
            fflush($fp);
        }
    }
    @flock($fp, LOCK_UN);
    fclose($fp);
    return $res;
}
}

if (!function_exists('save_all_user_totp')) {
/**
 * 모든 사용자 TOTP 설정 저장
 * @param array $data 사용자별 설정
 * @return bool 성공 여부
 */
function save_all_user_totp($data) {
    if (!is_array($data)) return false;
    return mc_totp_modify(function (&$all) use ($data) { $all = $data; return true; }) === true; // ★ (2026-11-04 00:46) 잠금 안에서 저장
}
}

if (!function_exists('load_user_totp')) {
/**
 * 특정 사용자 TOTP 설정 로드
 * @param string $user_id 사용자 ID
 * @return array 설정 배열
 */
function load_user_totp($user_id) {
    $all = load_all_user_totp();
    return $all[$user_id] ?? [
        'enabled' => false,
        'secret' => '',
        'backup_codes' => [],
        'created_at' => null,
        'enabled_at' => null
    ];
}
}

if (!function_exists('save_user_totp')) {
/**
 * 특정 사용자 TOTP 설정 저장
 * @param string $user_id 사용자 ID
 * @param array $settings 설정 배열
 * @return bool 성공 여부
 */
function save_user_totp($user_id, $settings) {
    // ★ (2026-11-04 00:46) 잠금 안에서 최신 내용을 읽고 이 회원 것만 바꿈 — 전엔 따로 읽은 뒤 통째로 써서 동시 저장 때 다른 회원 설정이 사라질 수 있었음
    return mc_totp_modify(function (&$all) use ($user_id, $settings) { $all[$user_id] = $settings; return true; }) === true;
}
}

if (!function_exists('delete_user_totp')) {
/**
 * 특정 사용자 TOTP 설정 삭제
 * @param string $user_id 사용자 ID
 * @return bool 성공 여부
 */
function delete_user_totp($user_id) {
    $all = load_all_user_totp();
    if (isset($all[$user_id])) {
        return mc_totp_modify(function (&$all) use ($user_id) { // ★ (2026-11-04 00:46) 잠금 안에서
            if (!isset($all[$user_id])) return false;
            unset($all[$user_id]);
            return true;
        }) !== null;
    }
    return true;
}
}

if (!function_exists('is_user_2fa_enabled')) {
/**
 * 특정 사용자 2FA 활성화 여부
 * @param string $user_id 사용자 ID
 * @return bool 활성화 여부
 */
function is_user_2fa_enabled($user_id) {
    $all = load_all_user_totp($ok);
    if (!$ok) return true; // ★ (2026-11-04 00:46) 설정 파일을 못 읽으면 '켜짐'으로 — 꺼짐으로 보면 OTP 없이 통과됐음
    $settings = $all[$user_id] ?? [];
    return !empty($settings['enabled']) && !empty($settings['secret']);
}
}

if (!function_exists('verify_user_totp')) {
/**
 * 사용자 TOTP 코드 검증
 * @param string $user_id 사용자 ID
 * @param string $code OTP 코드
 * @return bool 유효 여부
 */
function verify_user_totp($user_id, $code) {
    $settings = load_user_totp($user_id);
    if (empty($settings['secret'])) {
        return false;
    }
    $step = mc_totp_match_step($settings['secret'], $code);
    if ($step === false) return false;
    // ★ (2026-11-08 전체 점검 fix73) 한 번 쓴 코드(그 시간 칸)와 그보다 앞선 칸의 코드는 다시 받지 않음 — 예전엔 같은 6자리를 최대 90초 안에 몇 번이고 다시 써서 로그인할 수 있었음(재현함)
    //   잠금 안에서 확인·기록(동시 요청 두 개가 같은 코드로 둘 다 통과하지 못하게). 기록할 파일을 못 열면(디스크 문제) 예전처럼 통과 — 그 때문에 로그인이 막히지 않게
    $ok = false; // 콜백 안에서 정함 — 콜백은 고쳤을 때만 true(그때만 저장)
    $res = mc_totp_modify(function (&$all) use ($user_id, $step, $settings, &$ok) {
        if (!isset($all[$user_id]) || !is_array($all[$user_id]) || (string)($all[$user_id]['secret'] ?? '') !== (string)$settings['secret']) return false; // 그 사이 2단계가 꺼지거나 바뀜
        $last = $all[$user_id]['last_step'] ?? null;
        if (is_int($last) && $step <= $last) return false; // 이미 쓴 코드
        $all[$user_id]['last_step'] = $step;
        $ok = true;
        return true;
    });
    if ($res === null && !$ok) return true; // 파일을 열거나 잠그지 못함(예전처럼 통과)
    return $ok;
}
}

if (!function_exists('verify_user_backup_code')) {
/**
 * 사용자 백업 코드 검증 및 사용 처리
 * @param string $user_id 사용자 ID
 * @param string $code 백업 코드
 * @return bool 유효 여부
 */
function verify_user_backup_code($user_id, $code) {
    $settings = load_user_totp($user_id);
    $backup_codes = $settings['backup_codes'] ?? [];
    
    $code = strtoupper(preg_replace('/[\s-]/', '', $code));
    
    foreach ($backup_codes as $idx => $stored) {
        if (hash_equals(strtoupper($stored), $code)) {
            // 사용된 코드 제거 — ★ (2026-11-04 00:46) 잠금 안에서 그 코드가 아직 있을 때만 지우고 통과(동시 요청으로 같은 코드를 두 번 쓰지 못하게)
            return mc_totp_modify(function (&$all) use ($user_id, $code) {
                $codes = $all[$user_id]['backup_codes'] ?? [];
                if (!is_array($codes)) return false;
                foreach ($codes as $i => $s) {
                    if (hash_equals(strtoupper((string)$s), $code)) {
                        unset($codes[$i]);
                        $all[$user_id]['backup_codes'] = array_values($codes);
                        return true;
                    }
                }
                return false;
            }) === true;
        }
    }
    
    return false;
}
}

if (!function_exists('generate_backup_codes')) {
/**
 * 백업 코드 생성 (2FA 비상용)
 * @param int $count 생성할 코드 개수 (기본: 10)
 * @return array 백업 코드 배열
 */
function generate_backup_codes($count = 10) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        // 8자리 숫자+영문 조합
        $codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return $codes;
}
}

if (!function_exists('verify_backup_code')) {
/**
 * 백업 코드 검증 및 사용 처리
 * @param string $code 입력된 백업 코드
 * @return bool 유효 여부
 */
function verify_backup_code($code) {
    $settings = load_totp_settings();
    $backup_codes = $settings['backup_codes'] ?? [];
    
    $code = strtoupper(preg_replace('/[\s-]/', '', $code));
    
    foreach ($backup_codes as $idx => $stored) {
        if (hash_equals(strtoupper($stored), $code)) {
            // 사용된 코드 제거
            unset($backup_codes[$idx]);
            $settings['backup_codes'] = array_values($backup_codes);
            save_totp_settings($settings);
            return true;
        }
    }
    
    return false;
}
}

if (!function_exists('is_2fa_enabled')) {
/**
 * 2FA 활성화 여부 확인
 * @return bool 활성화 여부
 */
function is_2fa_enabled() {
    $settings = load_totp_settings();
    return !empty($settings['enabled']) && !empty($settings['secret']);
}
}

/**
 * SMTP를 통한 이메일 발송
 * 
 * @param string $to 수신자 이메일
 * @param string $subject 제목
 * @param string $body HTML 본문
 * @param array $smtp SMTP 설정 배열
 * @return bool|string 성공 시 true, 실패 시 오류 메시지
 */
function send_smtp_email($to, $subject, $body, $smtp = null) {
    if ($smtp === null) {
        $smtp = get_app_settings('smtp', []);
    }
    
    // 설정 검증
    if (empty($smtp['host']) || empty($smtp['username'])) {
        return __('smtp_no_config');
    }
    
    $host = $smtp['host'];
    $port = (int)($smtp['port'] ?? 587);
    $encryption = $smtp['encryption'] ?? 'tls';
    $username = $smtp['username'];
    $password = $smtp['password'] ?? '';
    $from_email = !empty($smtp['from_email']) ? $smtp['from_email'] : $username;
    $from_name = $smtp['from_name'] ?? 'myComix';
    // ★ (2026-11-08 전체 점검 fix73) 받는·보내는 주소에 줄바꿈·꺾쇠·제어 문자가 있으면 보내지 않음 — 그대로 SMTP 명령(MAIL FROM·RCPT TO·머리글)에 들어가
    //   '보내는 주소'(관리자 설정, 서버에서 형식 검사 없음)나 그 대신 쓰는 SMTP 아이디에 줄바꿈이 있으면 받는 사람을 몰래 더할 수 있었음(가짜 SMTP 서버로 재현 — 비밀번호 찾기 코드 메일이 다른 주소로도 감)
    foreach ([$to, $from_email] as $__addr) {
        if (!is_string($__addr) || $__addr === '' || preg_match('/[\x00-\x1f\x7f<>]/', $__addr)) {
            return __('smtp_bad_address');
        }
    }
    
    // PHP 내장 fsockopen을 사용한 간단한 SMTP 구현
    $errno = 0;
    $errstr = '';
    
    // SSL/TLS 연결
    $prefix = '';
    if ($encryption === 'ssl') {
        $prefix = 'ssl://';
    }
    
    $timeout = 30;
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);
    
    if (!$socket) {
        return __("smtp_connect_fail") . ": $errstr ($errno)";
    }
    
    stream_set_timeout($socket, $timeout);
    
    // 응답 읽기 함수
    $read_response = function() use ($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    };
    
    // 명령 전송 함수
    $send_command = function($cmd, $expected_code = '250') use ($socket, $read_response) {
        fputs($socket, $cmd . "\r\n");
        $response = $read_response();
        $code = substr($response, 0, 3);
        if (strpos($expected_code, $code) === false && $expected_code !== $code) {
            return ['success' => false, 'response' => $response, 'code' => $code];
        }
        return ['success' => true, 'response' => $response, 'code' => $code];
    };
    
    try {
        // 서버 인사말
        $response = $read_response();
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return __("smtp_server_error") . ": $response";
        }
        
        // EHLO
        $result = $send_command("EHLO " . gethostname(), '250');
        if (!$result['success']) {
            fclose($socket);
            return __("smtp_ehlo_fail") . ": " . $result['response'];
        }
        
        // STARTTLS (TLS 암호화 시)
        if ($encryption === 'tls') {
            $result = $send_command("STARTTLS", '220');
            if (!$result['success']) {
                fclose($socket);
                return __("smtp_starttls_fail") . ": " . $result['response'];
            }
            
            // TLS 핸드셰이크
            $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto_method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            
            if (!stream_socket_enable_crypto($socket, true, $crypto_method)) {
                fclose($socket);
                return __("smtp_tls_fail");
            }
            
            // TLS 후 다시 EHLO
            $result = $send_command("EHLO " . gethostname(), '250');
            if (!$result['success']) {
                fclose($socket);
                return __("smtp_tls_ehlo_fail") . ": " . $result['response'];
            }
        }
        
        // 인증
        $result = $send_command("AUTH LOGIN", '334');
        if (!$result['success']) {
            fclose($socket);
            return __("smtp_auth_fail") . ": " . $result['response'];
        }
        
        $result = $send_command(base64_encode($username), '334');
        if (!$result['success']) {
            fclose($socket);
            return __("smtp_user_auth_fail");
        }
        
        $result = $send_command(base64_encode($password), '235');
        if (!$result['success']) {
            fclose($socket);
            return __("smtp_pass_auth_fail") . " - " . __("smtp_check_app_password");
        }
        
        // MAIL FROM
        $result = $send_command("MAIL FROM:<{$from_email}>", '250');
        if (!$result['success']) {
            fclose($socket);
            return __("smtp_mail_from_fail") . ": " . $result['response'];
        }
        
        // RCPT TO
        $result = $send_command("RCPT TO:<{$to}>", '250');
        if (!$result['success']) {
            fclose($socket);
            return __("smtp_rcpt_to_fail") . ": " . $result['response'];
        }
        
        // DATA
        $result = $send_command("DATA", '354');
        if (!$result['success']) {
            fclose($socket);
            return __("smtp_data_fail") . ": " . $result['response'];
        }
        
        // 이메일 헤더 및 본문
        $headers = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_email}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "\r\n";
        
        // 본문에서 단일 점(.)은 두 개로 치환 (SMTP 프로토콜)
        $body = str_replace("\r\n.\r\n", "\r\n..\r\n", $body);
        
        fputs($socket, $headers . $body . "\r\n.\r\n");
        $response = $read_response();
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return __("smtp_send_fail") . ": $response";
        }
        
        // QUIT
        $send_command("QUIT", '221');
        fclose($socket);
        
        return true;
        
    } catch (Exception $e) {
        if ($socket) fclose($socket);
        return __("error_prefix") . ": " . $e->getMessage();
    }
}

/**
 * 비밀번호 재설정 이메일 발송
 * 
 * @param string $to 수신자 이메일
 * @param string $username 사용자명
 * @param string $temp_password 임시 비밀번호
 * @return bool|string 성공 시 true, 실패 시 오류 메시지
 */
function send_password_reset_email($to, $username, $temp_password) {
    $smtp = get_app_settings('smtp', []);
    
    if (empty($smtp['enabled']) || empty($smtp['host'])) {
        return false; // SMTP 미설정 - 화면에 표시
    }
    
    $branding = load_branding();
    $site_name = $branding['logo_text'] ?? 'myComix';
    
    $subject = "[{$site_name}] " . __('security_temp_pw_title');
    
    $_pw_reset_msg = __('security_pw_reset', $username);
    $_pw_change_notice = __('security_pw_change_notice');
    $_temp_pw_label = __('security_temp_pw_label');
    
    $body = "
    <div style='font-family: -apple-system, BlinkMacSystemFont, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: #f8f9fa; border-radius: 10px; padding: 30px; text-align: center;'>
            <h2 style='color: #333; margin-bottom: 20px;'>" . __h('security_temp_pw_title') . "</h2>
            <p style='color: #666; margin-bottom: 20px;'>{$_pw_reset_msg}</p>
            <div style='background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                <p style='margin: 0 0 10px; color: #856404;'><strong>{$_temp_pw_label}</strong></p>
                <p style='font-size: 24px; font-family: monospace; letter-spacing: 3px; margin: 0; color: #333;'>{$temp_password}</p>
            </div>
            <p style='color: #dc3545; font-size: 14px;'>{$_pw_change_notice}</p>
            <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
            <p style='color: #999; font-size: 12px;'>{$site_name}</p>
        </div>
    </div>
    ";
    
    return send_smtp_email($to, $subject, $body, $smtp);
}

/**
 * ★ (2026-11-04 07:49) 비밀번호 찾기 확인 코드 메일 — 이 코드를 비밀번호 찾기 화면에 넣어야 임시 비밀번호로 바뀜(그 전엔 비밀번호 그대로)
 */
function send_password_reset_code_email($to, $username, $code) {
    $smtp = get_app_settings('smtp', []);
    if (empty($smtp['enabled']) || empty($smtp['host'])) {
        return false;
    }
    $branding = load_branding();
    $site_name = h($branding['logo_text'] ?? 'myComix');
    $subject = '[' . ($branding['logo_text'] ?? 'myComix') . '] ' . __('security_pw_code_title');
    $body = "
    <div style='font-family: -apple-system, BlinkMacSystemFont, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: #f8f9fa; border-radius: 10px; padding: 30px; text-align: center;'>
            <h2 style='color: #333; margin-bottom: 20px;'>" . __h('security_pw_code_title') . "</h2>
            <p style='color: #666; margin-bottom: 20px;'>" . __h('security_pw_code_msg', $username) . "</p>
            <div style='background: #e8f0fe; border: 1px solid #8ab4f8; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                <p style='margin: 0 0 10px; color: #1a56c4;'><strong>" . __h('security_pw_code_label') . "</strong></p>
                <p style='font-size: 26px; font-family: monospace; letter-spacing: 4px; margin: 0; color: #333;'>" . h($code) . "</p>
            </div>
            <p style='color: #666; font-size: 14px;'>" . __h('security_pw_code_ignore') . "</p>
            <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
            <p style='color: #999; font-size: 12px;'>{$site_name}</p>
        </div>
    </div>
    ";
    return send_smtp_email($to, $subject, $body, $smtp);
}

// ─── 크로스 플랫폼 셸 인수 이스케이프 ───
if (!function_exists('escape_shell_arg_safe')) {
    /**
     * 크로스 플랫폼 셸 인수 이스케이프
     * Windows: 내부 따옴표를 ""로 이스케이프 후 전체를 따옴표로 감싸기
     * Linux/Mac: escapeshellarg() 사용
     *
     * @param string $arg 이스케이프할 인수
     * @return string 이스케이프된 인수
     */
    function escape_shell_arg_safe($arg) {
        $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);

        if ($is_windows) {
            $arg = str_replace('"', '""', $arg);
            return '"' . $arg . '"';
        } else {
            return escapeshellarg($arg);
        }
    }
}

?>