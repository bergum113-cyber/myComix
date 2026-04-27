<?php
/**
 * myComix 다국어(i18n) 지원 모듈
 * 
 * @version 1.0
 * @date 2026-02-06
 * 
 * 사용법:
 *   __('login_button')           → 현재 언어의 번역 반환
 *   __('login_error_suspended', $date)  → %s 치환 포함
 *   get_current_lang()           → 현재 언어 코드 ('ko', 'en')
 *   get_available_langs()        → 사용 가능한 언어 목록
 */

// 중복 로드 방지
if (defined('MYCOMIX_I18N_LOADED')) {
    return;
}
define('MYCOMIX_I18N_LOADED', true);

// 전역 언어 데이터
$_LANG = [];
$_CURRENT_LANG = 'ko';

/**
 * 현재 언어 코드 반환
 */
function get_current_lang() {
    global $_CURRENT_LANG;
    return $_CURRENT_LANG;
}

/**
 * 사용 가능한 언어 목록 반환
 */
function get_available_langs() {
    return [
        'ko' => '한국어',
        'en' => 'English'
    ];
}

/**
 * 언어 초기화 (bootstrap에서 호출)
 * 
 * 우선순위:
 *   1. GET 파라미터 (?lang=en) → 사용자 설정에 저장 + 세션에 저장
 *   2. 세션에 저장된 언어
 *   3. 로그인 사용자의 개별 언어 설정 (users.json → lang 필드)
 *   4. app_settings.json의 language 설정 (사이트 기본)
 *   5. 기본값 'ko'
 */
function init_language() {
    global $_LANG, $_CURRENT_LANG;
    
    $available = get_available_langs();
    $lang = 'ko'; // 기본값
    
    // 5 → 4: 사이트 기본 언어 (app_settings.json)
    $settings_file = __DIR__ . '/src/app_settings.json';
    if (file_exists($settings_file)) {
        $settings = json_decode(file_get_contents($settings_file), true);
        if (isset($settings['language']) && array_key_exists($settings['language'], $available)) {
            $lang = $settings['language'];
        }
    }
    
    // 3: 로그인 사용자의 개별 언어 설정
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
        if (function_exists('load_users')) {
            $users = load_users();
            $uid = $_SESSION['user_id'];
            if (isset($users[$uid]['lang']) && array_key_exists($users[$uid]['lang'], $available)) {
                $lang = $users[$uid]['lang'];
            }
        }
    }
    
    // 2: 세션에 저장된 언어
    if (isset($_SESSION['lang']) && array_key_exists($_SESSION['lang'], $available)) {
        $lang = $_SESSION['lang'];
    }
    
    // 1: GET 파라미터로 언어 변경 → 사용자 설정에도 저장
    if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $available)) {
        $lang = $_GET['lang'];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = $lang;
            // 로그인 사용자면 users.json에도 저장
            if (!empty($_SESSION['user_id']) && function_exists('load_users') && function_exists('save_users')) {
                save_user_language($_SESSION['user_id'], $lang);
            }
        }
    }
    
    $_CURRENT_LANG = $lang;
    
    // 언어 파일 로드
    $lang_file = __DIR__ . '/lang/' . $lang . '.php';
    if (file_exists($lang_file)) {
        $_LANG = require $lang_file;
    } else {
        // 기본 한국어 폴백
        $fallback = __DIR__ . '/lang/ko.php';
        if (file_exists($fallback)) {
            $_LANG = require $fallback;
        }
    }
    
    return $lang;
}

/**
 * 사용자 언어 설정 저장
 * 
 * @param string $user_id 사용자 ID
 * @param string $lang 언어 코드
 * @return bool
 */
function save_user_language($user_id, $lang) {
    $available = get_available_langs();
    if (!array_key_exists($lang, $available)) return false;
    
    $users = load_users();
    if (!isset($users[$user_id])) return false;
    
    $users[$user_id]['lang'] = $lang;
    return save_users($users);
}

/**
 * 번역 문자열 반환
 * 
 * ※ PHP gettext 확장이 로드되어 있으면 __() 가 이미 존재할 수 있으므로
 *    function_exists 체크 필수! 충돌 시 _t() 를 내부 함수로 사용하고
 *    gettext의 __() 를 덮어쓰지 않도록 처리.
 * 
 * @param string $key 번역 키
 * @param mixed ...$args sprintf 치환 인자
 * @return string 번역된 문자열 (키를 찾지 못하면 키 자체 반환)
 * 
 * @example
 *   __('login_button')                          → '로그인' 또는 'Login'
 *   __('login_error_suspended', '2026-03-01')   → '계정이 2026-03-01까지 정지되었습니다.'
 */

// 내부 번역 함수 (항상 정의)
function _t($key, ...$args) {
    global $_LANG;
    
    $text = $_LANG[$key] ?? $key;
    
    if (!empty($args)) {
        // sprintf 오류 방지: %s 개수와 인자 수 불일치 시 안전 처리
        try {
            $text = @sprintf($text, ...$args);
        } catch (\Throwable $e) {
            // sprintf 실패 시 원본 반환
        }
    }
    
    return $text;
}

// __() 함수 정의 (gettext 확장과 충돌 방지)
if (!function_exists('__')) {
    function __($key, ...$args) {
        return _t($key, ...$args);
    }
}

/**
 * 번역 문자열을 HTML 이스케이프하여 반환
 * 
 * @param string $key 번역 키
 * @param mixed ...$args sprintf 치환 인자
 * @return string HTML 이스케이프된 번역 문자열
 */
if (!function_exists('__h')) {
    function __h($key, ...$args) {
        return htmlspecialchars(_t($key, ...$args), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * HTML lang 속성값 반환
 */
function get_html_lang() {
    $lang = get_current_lang();
    $map = [
        'ko' => 'ko',
        'en' => 'en'
    ];
    return $map[$lang] ?? 'ko';
}

/**
 * 언어 전환 URL 생성
 * 
 * @param string $lang 전환할 언어 코드
 * @return string 현재 URL에 lang 파라미터를 추가한 URL
 */
function get_lang_switch_url($lang) {
    $url = $_SERVER['REQUEST_URI'];
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';
    $query = [];
    
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }
    
    $query['lang'] = $lang;
    
    return $path . '?' . http_build_query($query);
}

/**
 * 언어 선택 드롭다운 HTML 반환
 * 
 * @param string $style 스타일 ('dropdown', 'inline', 'minimal')
 * @return string HTML 문자열
 */
function render_lang_selector($style = 'minimal') {
    $current = get_current_lang();
    $available = get_available_langs();
    $html = '';
    
    if ($style === 'minimal') {
        $html .= '<div class="lang-selector" style="display:inline-flex;gap:6px;font-size:0.85em;">';
        foreach ($available as $code => $name) {
            if ($code === $current) {
                $html .= '<span style="font-weight:700;opacity:1;">' . htmlspecialchars($name) . '</span>';
            } else {
                $html .= '<a href="' . htmlspecialchars(get_lang_switch_url($code)) . '" style="text-decoration:none;opacity:0.7;">' . htmlspecialchars($name) . '</a>';
            }
            if ($code !== array_key_last($available)) {
                $html .= '<span style="opacity:0.4;">|</span>';
            }
        }
        $html .= '</div>';
    } elseif ($style === 'dropdown') {
        $html .= '<select onchange="location.href=this.value" class="lang-dropdown" style="padding:4px 8px;border-radius:4px;border:1px solid #ccc;font-size:0.85em;cursor:pointer;">';
        foreach ($available as $code => $name) {
            $selected = ($code === $current) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars(get_lang_switch_url($code)) . '"' . $selected . '>' . htmlspecialchars($name) . '</option>';
        }
        $html .= '</select>';
    }
    
    return $html;
}
