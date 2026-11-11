<?php
/**
 * myComix EPUB 뷰어
 * epub.js를 사용한 전자책 렌더링
 * 
 * @version 2.0 - 지연 로딩 + 서버 캐시 + 진행률 유지
 * @date 2026-01-13
 * 
 * 특징:
 * - epub.js 기반 렌더링
 * - 다크모드/세피아 테마 지원
 * - 글꼴 크기 조절
 * - 목차(TOC) 지원
 * - 진행 위치 저장/복원
 * - 진행률 캐시 서버 저장 (브라우저/기기 간 공유)
 */

require_once __DIR__ . "/bootstrap.php";

// ★ (2026-11-01) 이 화면에서만 책 안의 CSS·글꼴 허용 — epub.js 는 책 안 파일을 blob: 주소로 넣는데 사이트 공통 보안 설정(CSP)이 막아서
//   책의 들여쓰기·제목·글꼴 같은 꾸밈이 하나도 안 나왔음(브라우저 콘솔: Refused to load the stylesheet 'blob:…')
//   style-src·font-src 에만 blob: 추가(blob: 주소는 이 화면 스크립트만 만들 수 있음 · 다른 규칙·다른 화면은 그대로)
if (!headers_sent() && isset($GLOBALS['CSP_RULES']) && is_array($GLOBALS['CSP_RULES'])) {
    $_ev_csp = array_map(function ($r) {
        return preg_match("/^(style-src|font-src) 'self'/", $r) && strpos($r, ' blob:') === false ? preg_replace("/^(style-src|font-src) 'self'/", "$1 'self' blob:", $r) : $r;
    }, $GLOBALS['CSP_RULES']);
    header('Content-Security-Policy: ' . implode('; ', $_ev_csp), true);
}

// ✅ 다중 폴더 지원: URL 파라미터로 폴더 선택 (API 전에 필요)
$bidx = init_bidx();  // ✅ 항상 bidx 포함

// ★ (2026-10-30) 화면 요청은 자동 로그아웃 안내를 먼저(아래 권한 확인이 로그인 정보 없는 세션을 '권한 없음'으로 보내지 않게)
if (!isset($_GET['action'])) handle_timeout_popup();
// ★ (2026-10-30) 보안: 폴더 권한 확인(API·스트림 포함 모든 요청)
mc_file_param_guard(isset($_GET['action']) || isset($_POST['action']));
// ★ (2026-10-30) 자동 로그아웃 직후(로그인 정보 없음) 세션으로는 API 를 쓰지 않음 — 화면 요청은 아래 handle_timeout_popup() 이 안내
if (isset($_GET['action']) && empty($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'login required']);
    exit;
}

// ★ (2026-10-31) 책 노트(형광펜·밑줄·메모·책갈피) API — action=notes_load / notes_save(CSRF), 계정별 src/<아이디>_notes.json
require_once __DIR__ . '/book_notes_lib.php';
mc_notes_handle_api('epub', $base_dir, $current_bidx);

// ============================================================
// EPUB locations 캐시 저장 API (파일 옆에 .locations.json)
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'save_locations' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // ★ (2026-11-02 11:40) 다른 사이트에서 보낸 요청·배열은 거절(모든 회원이 같이 쓰는 위치 캐시라 남이 바꿔 둘 수 있었음)
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site' || !is_string($_POST['file'] ?? '') || !is_string($_POST['locations'] ?? '')) { echo json_encode(['success' => false]); exit; }
    $getfile = decode_file_param($_POST['file'] ?? '');
    $locations = $_POST['locations'] ?? '';
    
    if (empty($getfile) || empty($locations)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    // 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    // ★ (2026-10-30) .epub 파일만 — 전에는 라이브러리 안 아무 파일·폴더 옆에나 <이름>.locations.json 을 만들 수 있었음
    if ($base_file === false || !is_file($base_file) || !preg_match('/\.epub$/i', $base_file)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file path']);
        exit;
    }
    if (!is_string($locations) || strlen($locations) > 5 * 1024 * 1024) { // 비정상적으로 큰 값 거부
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    // 캐시 파일 경로: 책1.epub → 책1.epub.locations.json
    $cache_file = $base_file . '.locations.json';
    
    // 캐시 데이터 (int 캐스팅으로 타입 보장)
    $cache_data = [
        'filesize' => (int)filesize($base_file),
        'mtime' => (int)filemtime($base_file),
        'locations' => $locations,
        'created' => time()
    ];
    
    if (@file_put_contents($cache_file, json_encode($cache_data), LOCK_EX)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Write failed']);
    }
    exit;
}

// ============================================================
// EPUB locations 캐시 로드 API
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'load_locations') {
    header('Content-Type: application/json');
    
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    if (empty($getfile)) {
        echo json_encode(['success' => false, 'locations' => null]);
        exit;
    }
    
    // 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false || !file_exists($base_file)) {
        echo json_encode(['success' => false, 'locations' => null]);
        exit;
    }
    
    // 캐시 파일 경로
    $cache_file = $base_file . '.locations.json';
    
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        
        // 캐시 유효성 검사: 파일 크기/수정시간이 다르면 캐시 무효화
        $current_size = filesize($base_file);
        $current_mtime = filemtime($base_file);
        
        // int 캐스팅으로 타입 불일치 방지
        $cached_size = (int)($cache_data['filesize'] ?? 0);
        $cached_mtime = (int)($cache_data['mtime'] ?? 0);
        
        if ($cache_data && 
            isset($cache_data['locations']) &&
            $cached_size === $current_size &&
            $cached_mtime === $current_mtime) {
            
            echo json_encode([
                'success' => true, 
                'locations' => $cache_data['locations']
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'locations' => null]);
    exit;
}

// ============================================================
// 나머지 페이지 렌더링 로직
// ============================================================

// ============================================================
// EPUB 파일 스트리밍 API
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'stream') {
    
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    // ✅ 경로 검증 통일 (validate_file_path 사용)
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        simple_error_exit(403, __('err_invalid_path'));
    }
    
    if (!file_exists($base_file) || !preg_match('/\.epub$/i', $base_file)) {
        simple_error_exit(404, __('err_file_not_found'));
    }
    
    $filesize = filesize($base_file);
    
    header('Content-Type: application/epub+zip');
    header('Content-Length: ' . $filesize);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=86400');
    
    // Range 요청 처리
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            // ★ (2026-10-30) 끝 위치를 파일 크기로 자르고(큰 값이면 메모리 할당 치명 오류·길이 불일치), 'bytes=-N'(끝에서 N바이트) 처리,
            //   한 번에 다 읽지 않고 64KB 씩 보냄(큰 파일 메모리)
            if ($matches[1] === '' && $matches[2] !== '') {
                $start = max(0, $filesize - intval($matches[2]));
                $end = $filesize - 1;
            } else {
                $start = $matches[1] !== '' ? intval($matches[1]) : 0;
                $end = $matches[2] !== '' ? min(intval($matches[2]), $filesize - 1) : $filesize - 1;
            }
            
            if ($start > $end || $start >= $filesize) {
                http_response_code(416);
                header("Content-Range: bytes */$filesize");
                header('Content-Length: 0'); // ★ (2026-11-09 11:38) 위에서 보낸 파일 전체 길이를 0으로 바꿈 — 본문 없이 끝나는데 길이가 남아 받는 쪽이 끝까지 기다리다 끊기던 것
                exit;
            }
            
            $length = $end - $start + 1;
            
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$filesize");
            header("Content-Length: $length");
            
            $fp = fopen($base_file, 'rb');
            fseek($fp, $start);
            $left = $length;
            while ($left > 0 && !feof($fp)) {
                $buf = fread($fp, min(65536, $left));
                if ($buf === false || $buf === '') break;
                echo $buf;
                $left -= strlen($buf);
                if (connection_aborted()) break;
            }
            fclose($fp);
            exit;
        }
    }
    
    readfile($base_file);
    exit;
}

// ============================================================
// 진행 위치 저장 API
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'save_progress' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $getfile = $_POST['file'] ?? '';
    $cfi = $_POST['cfi'] ?? '';
    // ★ (2026-11-02 09:03) 배열이 오면 500·다른 사이트 요청·빈 파일은 저장하지 않음(cfi 도 문자열만)
    if (!is_string($getfile) || !is_string($cfi) || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') { echo json_encode(['success' => false]); exit; }
    // 키 정규화: 앞의 슬래시 제거
    $getfile = ltrim($getfile, '/');
    if ($getfile === '') { echo json_encode(['success' => false]); exit; }
    
    $percent = (float)($_POST['percent'] ?? 0);
    if (!is_finite($percent)) $percent = 0; // ★ (2026-10-30) INF·NAN 이면 json_encode 실패 → 저장 거부(전에는 진행률 파일 전체가 비워질 수 있었음)
    
    // ✅ bootstrap.php의 함수 사용 (파일 잠금 적용)
    $progress_file = get_epub_progress_file();
    $progress = load_json_with_lock($progress_file);
    
    // ✅ percent가 -1이면 기존 값 유지 (locations 계산 전에는 -1로 전송됨)
    $__pk = mc_prog_key($bidx, $getfile); // ★ (2026-11-06 00:50) 콘텐츠 폴더별 키("bidx|경로") — 예전 키(경로만)는 여기서 옮기며 지움
    $__old = mc_prog_get($progress, $bidx, $getfile);
    $existing_percent = is_array($__old) ? ($__old['percent'] ?? 0) : 0;
    if ($percent < 0) {
        $percent = $existing_percent;
    }
    
    unset($progress[$getfile]);
    $progress[$__pk] = [
        'cfi' => $cfi,
        'percent' => $percent,
        'updated' => time()
    ];
    
    save_json_with_lock($progress_file, $progress);
    
    echo json_encode(['success' => true]);
    exit;
}

// ============================================================
// 진행 위치 로드 API
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'load_progress') {
    header('Content-Type: application/json');
    
    // save_progress와 동일하게 키 정규화
    $getfile = $_GET['file'] ?? '';
    $getfile = ltrim(is_string($getfile) ? $getfile : '', '/'); // ★ (2026-11-02 09:03) 배열이면 500 이던 것
    
    // ✅ bootstrap.php의 함수 사용 (파일 잠금 적용)
    $progress_file = get_epub_progress_file();
    $progress = load_json_with_lock($progress_file);
    
    $saved = mc_prog_get($progress, $bidx, $getfile); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 것(없으면 예전 키)
    
    echo json_encode(['progress' => $saved]);
    exit;
}

// ============================================================
// 페이지 렌더링 (HTML 출력)
// ============================================================

handle_timeout_popup();  // ✅ 자동 로그아웃 메시지 처리

// ✅ 브랜딩 설정 로드 (function.php의 공통 함수 사용)
$_branding = load_branding();

// 설정값
$epub_settings = $epub_viewer_settings ?? [
    'max_file_size' => 100 * 1024 * 1024,
    'use_epubjs' => true,
    'default_font_size' => 100,
    'default_theme' => 'light',
];

$getfile = decode_file_param($_GET['file'] ?? '');
if (!$getfile) {
    header("Location: ./");
    exit;
}

// ✅ 경로 검증 통일 (validate_file_path 사용)
$base_file = validate_file_path($getfile, $base_dir);
if ($base_file === false) {
    echo __("err_invalid_path");
    exit;
}

if (!file_exists($base_file) || !preg_match('/\.epub$/i', $base_file)) {
    echo __("epub_file_not_found");
    exit;
}

$title = basename($base_file, '.epub');
$filesize = filesize($base_file);

// 상위 폴더 링크
$link_dir = dirname($getfile);
if ($link_dir === '.' || $link_dir === '/') {
    $link_dir = '';
}

// 테마 설정
$theme = $_COOKIE['epub_theme'] ?? ($epub_settings['default_theme'] ?? 'light');
// ★ (2026-11-08 fix80, 펜닐 제보) EPUB 테마를 한 번도 고르지 않았으면 사이트 다크모드를 따름 — 다크모드에서 EPUB 만 흰 화면으로 열렸음(고른 뒤엔 그 테마 그대로)
if (!isset($_COOKIE['epub_theme']) && !empty($darkmode_settings['enabled'])) {
    $__site_t = (is_string($_COOKIE['darkmode'] ?? null) && in_array($_COOKIE['darkmode'], ['light', 'dark'], true)) ? $_COOKIE['darkmode'] : ((($darkmode_settings['default'] ?? 'light') === 'dark') ? 'dark' : '');
    if ($__site_t === 'dark') $theme = 'dark';
}
$fontSize = $_COOKIE['epub_fontsize'] ?? ($epub_settings['default_font_size'] ?? 100);
// ★ (2026-10-30) 쿠키 값은 그대로 HTML·JS 에 찍히므로 허용 값만(테마 3가지, 글자 크기 정수)
if (!in_array($theme, ['light', 'sepia', 'dark'], true)) $theme = 'light';
$fontSize = max(50, min(300, (int)$fontSize));

// 커스텀 폰트 설정
$epub_font_name = $epub_settings['font_name'] ?? '';
$epub_font_url = $epub_settings['font_url'] ?? '';
$epub_font_local = $epub_settings['font_local'] ?? '';
$epub_font_family = !empty($epub_font_name) ? "'" . h($epub_font_name) . "', 'Nanum Gothic', 'Malgun Gothic', sans-serif" : "'Nanum Gothic', 'Malgun Gothic', sans-serif";
?>
<!DOCTYPE html>
<html lang="ko" class="mc-fdk<?php echo $theme === 'dark' ? ' mc-fdk-dark' : ''; ?>"><?php /* ★ (2026-11-08 fix82) 다크 테마면 바탕은 어둡게 두고 내용만 나타나게(bootstrap.php mc_fade_dark_style) */ ?>
<?php if (function_exists('mc_fade_dark_style')) echo mc_fade_dark_style('#1a1a2e'); ?>
<head>
<?php if (function_exists('mc_back_origin_viewer_js')) echo mc_back_origin_viewer_js(); /* ★ (2026-11-05) 뒤로가기 = 들어온 곳(서재·즐겨찾기·북마크 등) — function.php */ ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo h($title); ?> - EPUB <?php echo __h("viewer_label"); ?></title>
    <!-- ✅ 핵심 레이아웃 + 페이지 전환 -->
    <style>
        html{opacity:0;transition:opacity .15s ease-in}
        html.ready{opacity:1}
        html.leaving{opacity:0;transition:opacity .1s ease-out}
    </style>
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <?php if (!empty($epub_font_url)): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?php echo h($epub_font_url); ?>" rel="stylesheet">
    <?php endif; ?>
    <?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
    
<?php
render_viewer_i18n([
    'epub_loading' => 'js_epub_loading',
    'epub_load_fail' => 'js_epub_load_fail',
    'epub_parsing' => 'js_epub_parsing',
    'epub_render_fail' => 'js_epub_render_fail',
    'epub_no_section' => 'js_epub_no_section',
    'epub_spine_not_found' => 'js_epub_spine_not_found',
]);
?>

    <!-- epub.js CDN -->
    <!-- ★ (2026-10-30) JSZip 은 사이트 안 js/ 의 3.10.2 를 씀(문서 뷰어와 같은 파일) -->
    <script src="js/jszip.min.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/jszip.min.js'); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>
    <script src="js/book-notes.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/book-notes.js'); ?>"></script><?php // ★ (2026-10-31) 책 노트 화면 ?>
    <script src="js/book-tools.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/book-tools.js'); ?>"></script><?php // ★ (2026-11-01) 검색·듣기·화면 꺼짐 방지·각주 창 ?>
    
    <style>
        <?php if (!empty($epub_font_local) && empty($epub_font_url)): ?>
        @font-face {
            font-family: '<?php echo h($epub_font_name ?: 'CustomEpubFont'); ?>';
            src: url('<?php echo function_exists('mc_css_str') ? mc_css_str($epub_font_local) : h($epub_font_local); /* ★ (2026-11-02 10:02) <style> 안이라 CSS 이스케이프(& 가 &amp; 로 남아 글꼴을 못 받던 것) */ ?>');
            font-display: swap;
        }
        <?php endif; ?>
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --toolbar-bg: #f8f9fa;
            --border-color: #dee2e6;
        }
        
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        
        body {
            font-family: <?php echo $epub_font_family; ?>;
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        body.theme-dark {
            --bg-color: #1a1a2e;
            --text-color: #e0e0e0;
            --toolbar-bg: #0f3460;
            --border-color: #404040;
        }
        
        body.theme-sepia {
            --bg-color: #f4ecd8;
            --text-color: #5b4636;
            --toolbar-bg: #e8dcc8;
            --border-color: #c9b99a;
        }
        
        /* 툴바 */
        .epub-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--toolbar-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 10px 15px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .epub-toolbar .title {
            flex: 1;
            font-weight: bold;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .epub-toolbar .btn {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        /* 뷰어 영역 */
        #viewer {
            position: fixed;
            top: 55px;
            bottom: 45px;
            left: 0;
            right: 0;
            background: var(--bg-color);
        }
        
        /* 세로 클릭 영역 (전체 높이) */
        .nav-zone {
            position: fixed;
            top: 55px;
            bottom: 45px;
            width: 15%;
            min-width: 50px;
            max-width: 100px;
            background: transparent;
            cursor: pointer;
            z-index: 50;
            transition: background 0.2s;
        }
        
        .nav-zone:hover {
            background: rgba(0, 0, 0, 0.05);
        }
        
        .nav-zone:active {
            background: rgba(0, 0, 0, 0.1);
        }
        
        body.theme-dark .nav-zone:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        body.theme-dark .nav-zone:active {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .nav-zone.left {
            left: 0;
        }
        
        .nav-zone.right {
            right: 0;
        }
        
        /* 세로 클릭 영역 내 아이콘 */
        .nav-zone-icon {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            color: rgba(0, 0, 0, 0.3);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .nav-zone:hover .nav-zone-icon {
            opacity: 1;
        }
        
        body.theme-dark .nav-zone-icon {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .nav-zone.left .nav-zone-icon {
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .nav-zone.right .nav-zone-icon {
            right: 50%;
            transform: translate(50%, -50%);
        }
        
        /* 하단 네비게이션 버튼 */
        .nav-bottom {
            position: fixed;
            bottom: 55px;
            padding: 12px 20px;
            background: rgba(0,0,0,0.6);
            color: white;
            border: none;
            font-size: 14px;
            cursor: pointer;
            z-index: 100;
            border-radius: 4px;
            transition: background 0.3s, left 0.3s, right 0.3s;
        }
        
        .nav-bottom:hover {
            background: rgba(0,0,0,0.8);
        }
        
        .nav-bottom.prev {
            left: 15px;
            right: auto;
        }
        
        .nav-bottom.next {
            right: 15px;
            left: auto;
        }
        
        /* 좌우 반전 모드 - 버튼 위치는 고정, 텍스트만 변경 */
        
        /* 하단 바 */
        .epub-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--toolbar-bg);
            border-top: 1px solid var(--border-color);
            padding: 8px 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .epub-footer input[type="range"] {
            flex: 1;
        }
        
        .epub-footer .progress-text {
            font-size: 12px;
            min-width: 50px;
            text-align: center;
        }
        
        /* 목차 사이드바 */
        .toc-sidebar {
            position: fixed;
            top: 55px;
            left: 0;
            bottom: 45px;
            width: 300px;
            background: var(--toolbar-bg);
            border-right: 1px solid var(--border-color);
            transform: translateX(-100%);
            transition: transform 0.3s;
            z-index: 1002;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
        
        .toc-sidebar.show {
            transform: translateX(0);
        }
        
        .toc-sidebar h5 {
            padding: 15px;
            margin: 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .toc-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .toc-list li {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
        }
        
        .toc-list li:hover {
            background: rgba(0,0,0,0.1);
        }
        
        .toc-list li.nested {
            padding-left: 30px;
            font-size: 0.9em;
        }
        
        /* 설정 패널 */
        .settings-panel {
            position: fixed;
            top: 60px;
            right: 10px;
            background: var(--toolbar-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            width: 250px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            z-index: 1001;
            display: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .settings-panel.show {
            display: block;
        }
        
        .settings-panel label {
            display: block;
            margin-bottom: 10px;
            font-size: 13px;
        }
        
        .theme-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .theme-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            font-size: 12px;
        }
        
        .theme-btn.active {
            border-color: #007bff;
        }
        
        .theme-btn.light {
            background: #ffffff;
            color: #333;
        }
        
        .theme-btn.sepia {
            background: #f4ecd8;
            color: #5b4636;
        }
        
        .theme-btn.dark {
            background: #1a1a2e;
            color: #e0e0e0;
        }
        
        /* 로딩 */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            font-size: 18px;
        }
        
        body.theme-dark .loading-overlay {
            background: rgba(26,26,46,0.9);
            color: #e0e0e0;
        }
        
        .loading-overlay.hidden {
            display: none;
        }
        
        /* ★ (2026-11-01) 좌우 여백 단추(테마 단추와 같은 모양 — 테마 단추 class 는 쓰지 않음: 테마 바꾸기 동작이 붙어 있음) */
        .mc-margin-btn { flex: 1; padding: 8px 4px; border: 2px solid var(--border-color); border-radius: 6px; cursor: pointer; text-align: center; font-size: 12px; }
        .mc-margin-btn.active { border-color: #007bff; }
        .mc-spread-btn { flex: 1; padding: 8px 4px; border: 2px solid var(--border-color); border-radius: 6px; cursor: pointer; text-align: center; font-size: 12px; } /* ★ (2026-11-04 18:04) 2쪽 펼침 3단계(여백 단추와 같은 모양) */
        .mc-spread-btn.active { border-color: #007bff; }
        .mc-fs-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 8px; }
        /* ★ (2026-11-11 11:16 fix113, 펜닐 요청) 설정 창을 스크롤 없이(휴대폰 세로·가로·PC) — 한 줄에 이름+고르는 칸, 누르는 칸 높이 30px 이상, 낮은 가로 화면은 두 칸 */
        .settings-panel.mc-setp { width: min(300px, calc(100vw - 20px)); padding: 10px 12px; left: auto; right: 10px; } /* ★ (2026-11-11 13:28 fix115, 펜닐 요청) 세로 화면·PC 는 좁고 길쭉하게(380px·휴대폰 화면 폭 가득 → 300px, 오른쪽 단추 아래) — 휴대폰 가로 두 칸은 그대로 */
        .mc-setp .mc-sr { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; min-height: 30px; }
        .mc-setp .mc-sl, .settings-panel.mc-setp label.mc-sl { flex: 0 0 72px; margin: 0; font-size: 12.5px; line-height: 1.25; display: block; }
        .mc-setp .mc-sr > input[type="range"] { flex: 1 1 auto; min-width: 0; width: auto; margin: 0; }
        .mc-setp .mc-sr > select, .mc-setp .mc-sr3 select { flex: 1 1 auto; min-width: 0; width: 100%; height: 30px; padding: 2px 6px; font-size: 12.5px; margin: 0; }
        .mc-setp .mc-sr > .btn { flex: 0 0 auto; white-space: nowrap; padding: 3px 8px; font-size: 12.5px; }
        .mc-setp .theme-buttons { flex: 1 1 auto; margin: 0; gap: 6px; min-width: 0; }
        .mc-setp .theme-btn, .mc-setp .mc-margin-btn, .mc-setp .mc-spread-btn { padding: 5px 2px; font-size: 12px; min-height: 30px; display: flex; align-items: center; justify-content: center; line-height: 1.15; }
        .mc-setp .mc-sr3 { align-items: flex-end; gap: 6px; }
        .settings-panel.mc-setp .mc-sr3 > label { flex: 1 1 0; min-width: 0; margin: 0; font-size: 11.5px; display: flex; flex-direction: column; gap: 2px; }
        .mc-setp .mc-cks { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 8px; }
        .settings-panel.mc-setp label.mc-ck { display: inline-flex; align-items: center; gap: 5px; margin: 0; padding: 4px 10px; min-height: 30px; border: 1px solid var(--border-color); border-radius: 15px; font-size: 12px; cursor: pointer; }
        .mc-setp .mc-ck input { margin: 0; }
        .settings-panel.mc-setp label.mc-ck { flex: 1 1 auto; justify-content: center; } /* ★ (2026-11-11 20:29 fix117) 체크 칩 줄 오른쪽이 비어 보였음 → 줄마다 폭을 나눠 채움(만화 메뉴 fix116 과 같게) */
        .mc-setp .mc-sr-foot { gap: 6px; font-size: 12px; min-height: 0; margin-bottom: 4px; color: #888; }
        .mc-setp .mc-sr-foot #readTime { font-size: 12px; color: inherit; }
        .mc-setp .mc-fs-row { margin-top: 4px; }
        @media (max-height: 520px) and (min-width: 560px) {
            .settings-panel.mc-setp { width: min(720px, calc(100vw - 20px)); column-count: 2; column-gap: 16px; }
            .mc-setp > * { break-inside: avoid; }
        }
        .epub-footer .ch-left { font-size: 12px; opacity: .75; white-space: nowrap; } /* ★ (2026-11-01) 이 장 남은 쪽 */
        .epub-footer .ch-left:empty { display: none; }
        /* ★ (2026-10-31) 좁은 화면: 책갈피·노트 단추가 늘어 '돌아가기'가 두 줄로 꺾이지 않게 */
        .epub-toolbar > a.btn { white-space: nowrap; }
        @media (max-width: 480px) { .epub-toolbar { gap: 6px; padding: 10px 10px; } }
        /* ★ (2026-11-01) 검색 단추가 늘어 좁은 휴대폰에서 오른쪽 끝(설정)이 잘리지 않게 — 단추 안쪽 여백만 줄임 */
        @media (max-width: 480px) { .epub-toolbar { gap: 4px; padding: 10px 8px; } .epub-toolbar .btn { padding: 5px 8px; } .epub-toolbar .title { min-width: 0; } }
        @media (max-width: 370px) { .epub-toolbar .ev-back-txt { display: none; } } /* 아주 좁은 휴대폰(360px 등): '돌아가기' 글자 숨김(화살표만) — 예전부터 설정 단추가 화면 밖으로 잘렸음 */
        /* ★ (2026-11-11 20:29 fix117) 관리자는 '폴더 표지' 단추가 하나 더 있어 375·390px(아이폰)에서 ⚙ 가 화면 밖으로 14~29px 밀렸고, 320px 은 글자를 숨겨도 24px 넘쳤음 → '돌아가기' 글자는 400px 까지 숨김(옛 아이폰 사파리는 :has 를 몰라 관리자만 고르지 않음), 340px 이하 단추 간격·안쪽 여백 줄임 */
        @media (max-width: 400px) { .epub-toolbar .ev-back-txt { display: none; } }
        @media (max-width: 340px) { .epub-toolbar { gap: 2px; padding: 10px 6px; } .epub-toolbar .btn { padding: 5px 6px; } }
        
        /* 모바일 */
        @media (max-width: 767px) {
            .nav-bottom {
                padding: 10px 15px;
                font-size: 12px;
            }
            
            .toc-sidebar {
                width: 80%;
            }
        }
    </style>
    <script>document.documentElement.classList.add('ready');</script>
</head>
<body class="theme-<?php echo h($theme); ?>">

<!-- 로딩 오버레이 -->
<div class="loading-overlay" id="loadingOverlay">
    📚 <?php echo __h("epub_loading"); ?>
</div>

<!-- 툴바 -->
<div class="epub-toolbar">
    <a data-mc-back href="index.php?dir=<?php echo u($link_dir) . $bidx_param; ?>" class="btn btn-outline-secondary btn-sm" title="<?php echo __h("back"); ?>">← <span class="ev-back-txt"><?php echo __h("back"); ?></span></a>
    <button id="btnToc" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('epub_toc'); ?>">📑</button>
    <span class="title"><?php echo h($title); ?></span>
    <?php render_lang_badge('sm-epub'); ?>
    <?php if (function_exists('mc_doc_cover_btn')) echo mc_doc_cover_btn($getfile, $current_bidx ?? 0, 'btn btn-outline-secondary btn-sm'); /* ★ (2026-11-08 fix80) 이 책 표지를 폴더 표지로(관리자) */ ?>
    <?php // ★ (2026-11-01) 본문 검색 ?>
    <button id="btnSearch" type="button" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('bt_search'); ?>" aria-label="<?php echo __h('bt_search'); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></button>
    <?php // ★ (2026-10-31) 책갈피(지금 쪽) · 노트 목록 ?>
    <button id="btnBookmark" type="button" class="btn btn-outline-secondary btn-sm mcn-tb-btn" title="<?php echo __h('bn_bm_add'); ?>" aria-label="<?php echo __h('bn_bm_add'); ?>" aria-pressed="false"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg></button>
    <button id="btnNotes" type="button" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('bn_panel'); ?>" aria-label="<?php echo __h('bn_panel'); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px"><path d="M12 20h9"/><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"/></svg></button>
    <button id="btnSettings" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('common_settings'); ?>">⚙️</button>
</div>

<!-- 목차 사이드바 -->
<div class="toc-sidebar" id="tocSidebar">
    <h5>📚 <?php echo __h("epub_toc"); ?></h5>
    <ul class="toc-list" id="tocList"></ul>
</div>

<!-- 설정 패널 -->
<?php // ★ (2026-11-11 11:16 fix113, 펜닐 요청) 설정 창을 스크롤 없이 — 한 줄에 이름+고르는 칸, 글꼴·줄·문단은 한 줄 세 칸, 체크 칸은 칩 묶음, 휴대폰 가로는 두 칸(CSS .mc-setp). 칸·ID·동작은 그대로 ?>
<div class="settings-panel mc-setp" id="settingsPanel">
    <div class="mc-sr"><span class="mc-sl"><?php echo __h("epub_theme"); ?></span>
    <div class="theme-buttons">
        <div class="theme-btn light <?php echo $theme === 'light' ? 'active' : ''; ?>" data-theme="light"><?php echo __h("epub_theme_light"); ?></div>
        <div class="theme-btn sepia <?php echo $theme === 'sepia' ? 'active' : ''; ?>" data-theme="sepia"><?php echo __h("epub_theme_sepia"); ?></div>
        <div class="theme-btn dark <?php echo $theme === 'dark' ? 'active' : ''; ?>" data-theme="dark"><?php echo __h("epub_theme_dark"); ?></div>
    </div></div>
    <div class="mc-sr"><label class="mc-sl" for="fontSizeSlider"><?php echo __h("txt_font_size"); ?> <span id="fontSizeValue"><?php echo $fontSize; ?></span>%</label>
    <input type="range" id="fontSizeSlider" min="70" max="150" value="<?php echo $fontSize; ?>"></div>
    <?php // ★ (2026-11-01) 줄 간격 · 좌우 여백 · 넓은 화면 2쪽 · 듣기 · 화면 꺼짐 방지 (이 기기에 기억) ?>
    <?php // ★ (2026-11-01 11:22) 글꼴 · 문단 간격 · 양쪽 정렬 (이 기기에 기억) ?>
    <div class="mc-sr mc-sr3">
    <label for="selFont"><?php echo __h("bt_font"); ?>
    <select id="selFont" class="form-control form-control-sm">
        <option value=""><?php echo __h("bt_book_default"); ?></option>
        <option value="gothic"><?php echo __h("bt_font_gothic"); ?></option>
        <option value="serif"><?php echo __h("bt_font_serif"); ?></option>
    </select></label>
    <label for="selLineHeight"><?php echo __h("bt_line"); ?>
    <select id="selLineHeight" class="form-control form-control-sm">
        <option value=""><?php echo __h("bt_book_default"); ?></option>
        <?php foreach (['1.4', '1.6', '1.8', '2.0', '2.2', '2.4'] as $_lh): ?><option value="<?php echo $_lh; ?>"><?php echo $_lh; ?></option><?php endforeach; ?>
    </select></label>
    <label for="selPara"><?php echo __h("bt_para"); ?>
    <select id="selPara" class="form-control form-control-sm">
        <option value=""><?php echo __h("bt_book_default"); ?></option>
        <?php foreach (['1', '2', '3'] as $_pa): ?><option value="<?php echo $_pa; ?>"><?php echo __h('bt_para_' . $_pa); ?></option><?php endforeach; ?>
    </select></label>
    </div>
    <div class="mc-sr"><span class="mc-sl"><?php echo __h("bt_margin"); ?></span>
    <div class="theme-buttons" id="marginButtons">
        <?php foreach ([0, 1, 2] as $_mg): ?><div class="mc-margin-btn" role="button" tabindex="0" data-margin="<?php echo $_mg; ?>"><?php echo __h('bt_margin_' . $_mg); ?></div><?php endforeach; ?>
    </div></div>
    <?php // ★ (2026-11-04 18:04) 2쪽 펼침: 체크 칸(켜면 폭 900 이상에서 2쪽) → 끔 · 넓은 화면(= 예전 켬, 그대로 이어짐) · 가로 화면(폰을 눕혀도 2쪽) ?>
    <div class="mc-sr" id="spreadRow"><span class="mc-sl"><?php echo __h("bt_spread_lbl"); ?></span>
    <div class="theme-buttons" id="spreadButtons" role="radiogroup" aria-label="<?php echo __h("bt_spread_lbl"); ?>">
        <?php foreach (['0', '1', 'land'] as $_sp): ?><div class="mc-spread-btn" role="radio" tabindex="0" data-spread="<?php echo $_sp; ?>" title="<?php echo __h('bt_spread_tip_' . $_sp); ?>"><?php echo __h('bt_spread_' . $_sp); ?></div><?php endforeach; ?>
    </div></div>
    <div class="mc-sr"><label class="mc-sl" for="dimSlider"><?php echo __h("bt_dim"); ?> <span id="dimValue">0%</span></label>
    <input type="range" id="dimSlider" min="0" max="70" step="5" value="0"></div>
    <div class="mc-sr"><label class="mc-sl" for="selAutoTurn"><?php echo __h("bt_autoturn"); ?></label>
    <select id="selAutoTurn" class="form-control form-control-sm">
        <option value="0"><?php echo __h("bt_off"); ?></option>
        <?php foreach ([10, 20, 30, 60] as $_at): ?><option value="<?php echo $_at; ?>"><?php echo h(sprintf(__('bt_sec'), $_at)); ?></option><?php endforeach; ?>
    </select>
    <button type="button" id="btnTts" class="btn btn-outline-secondary btn-sm">🔊 <?php echo __h("bt_tts_start"); ?></button></div>
    <small id="ttsNote" style="display:none;color:#888;margin-bottom:6px;"></small>
    <div class="mc-cks">
        <label class="mc-ck"><input type="checkbox" id="chkReverseNav" <?php echo ($_COOKIE['epub_reverse_nav'] ?? '') === '1' ? 'checked' : ''; ?>> <?php echo __h("txt_reverse_nav"); ?></label>
        <label class="mc-ck"><input type="checkbox" id="chkJustify"> <?php echo __h("bt_justify"); ?></label>
        <label class="mc-ck"><input type="checkbox" id="chkWake"> <?php echo __h("bt_wake"); ?></label>
    </div>
    <small id="wakeNote" style="display:none;color:#888;"></small>
    <div class="mc-sr mc-sr-foot"><small><?php echo __h("bt_readtime"); ?></small><div id="readTime"></div></div>
    <?php // ★ (2026-11-04 18:05) 전체화면(북모드 설정의 전체화면과 같은 동작) — 브라우저가 지원할 때만 보임(iPhone 사파리는 막혀 있어 숨김) ?>
    <div class="mc-fs-row" id="fsRow" hidden><span><?php echo __h("bt_fullscreen"); ?></span><button type="button" id="btnFullscreen" class="btn btn-outline-secondary btn-sm" aria-pressed="false"><?php echo __h("bt_fs_on"); ?></button></div>
</div>

<!-- 뷰어 -->
<div id="viewer"></div>

<!-- 세로 클릭 영역 (전체 높이) -->
<div class="nav-zone left" id="navZoneLeft">
    <span class="nav-zone-icon">◀</span>
</div>
<div class="nav-zone right" id="navZoneRight">
    <span class="nav-zone-icon">▶</span>
</div>

<!-- 하단 네비게이션 버튼 -->
<button class="nav-bottom prev" id="btnPrev">◀ <span class="nav-text"><?php echo __h("js_prev"); ?></span></button>
<button class="nav-bottom next" id="btnNext"><span class="nav-text"><?php echo __h("js_next"); ?></span> ▶</button>

<!-- 하단 바 -->
<div class="epub-footer">
    <input type="range" id="progressSlider" min="0" max="100" value="0" disabled>
    <span class="ch-left" id="chLeft"></span><?php // ★ (2026-11-01) 이 장 남은 쪽 ?>
    <span class="progress-text" id="progressText">0%</span>
</div>

<script>
(function() {
    const FILE_URL = 'epub_viewer.php?action=stream&file=<?php echo rawurlencode($getfile) . $bidx_param; ?>';
    const FILE_KEY = <?php echo js($getfile); ?>;
    const BIDX = <?php echo (int)$current_bidx; ?>;
    
    let book = null;
    let rendition = null;
    let currentTheme = '<?php echo $theme; ?>';
    let currentFontSize = <?php echo (int)$fontSize; ?>;
    let savedProgressData = null;  // ✅ 서버에서 로드한 진행률 저장
    let isReversed = <?php echo ($_COOKIE['epub_reverse_nav'] ?? '') === '1' ? 'true' : 'false'; ?>;
    
    const loadingEl = document.getElementById('loadingOverlay');
    const viewerEl = document.getElementById('viewer');
    const tocList = document.getElementById('tocList');
    const progressSlider = document.getElementById('progressSlider');
    const progressText = document.getElementById('progressText');
    
    // 테마 색상 + 커스텀 폰트
    const epubCustomFont = <?php echo json_encode($epub_font_family); ?>;
    const themes = {
        light: { body: { background: '#ffffff', color: '#333333', 'font-family': epubCustomFont } },
        sepia: { body: { background: '#f4ecd8', color: '#5b4636', 'font-family': epubCustomFont } },
        dark: { body: { background: '#1a1a2e', color: '#e0e0e0', 'font-family': epubCustomFont } }
    };
    
    // ✅ 서버에 locations 캐시 저장 (FormData 방식으로 대용량 지원)
    async function saveLocationsToServer(locationsData) {
        try {
            console.log('📚 캐시 저장 시도, 데이터 크기:', locationsData.length, 'bytes');
            
            const formData = new FormData();
            formData.append('file', FILE_KEY);
            formData.append('locations', locationsData);
            
            const res = await fetch(`epub_viewer.php?action=save_locations&bidx=<?php echo $current_bidx; ?>`, {
                method: 'POST',
                body: formData
            });
            
            const text = await res.text();
            console.log('📚 서버 응답:', text);
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    console.log('📚 서버에 위치 정보 캐시 저장 완료');
                } else {
                    console.warn('📚 서버 캐시 저장 실패:', data.error);
                }
            } catch (e) {
                console.warn('📚 서버 응답 파싱 실패:', text);
            }
        } catch (e) {
            console.warn('📚 서버 캐시 저장 중 오류:', e);
        }
    }
    
    // ============================================================
    // ★ (2026-10-31) 쪽 위치·형광펜 위치 안정화 (펜닐 제보: 쪽을 넘겼다 돌아오면 줄·형광펜이 글과 어긋남, 다시 들어가면 맞음)
    //   원인: epub.js 는 장(章)을 그리자마자 쪽 위치를 정하고 형광펜을 그린 뒤에, 그림 크기 조정·글꼴 읽기가 끝나며 글이 다시 흐름
    //   → ① 그림 크기 제한은 그리기 전에 넣고(위 spine 훅) epub.js 의 늦은 조정(adjustImages)은 뺌
    //     ② 장이 다 자리 잡으면(글꼴·그림 다 읽음) 형광펜·밑줄을 다시 그리고, 가려던 자리(앞 장의 마지막 쪽 / 저장 위치·목차·노트 위치)로 다시 맞춤
    //     ③ 그 뒤에 늦게 글이 흐르면(글꼴·그림이 늦게 옴) 다시 그리고 보던 자리로 맞춤 / 글자 크기를 바꿔도 보던 자리 유지
    //   (epub.js 0.3.93 고정 — 내부 함수 이름이 없으면 해당 부분만 건너뜀)
    // ============================================================
    let guardAnchor = null;        // 마지막으로 자리 잡은 쪽의 시작 CFI
    let guardBusy = 0;             // 맞추는 중(이때 생긴 relocated 는 '사용자가 옮김'으로 보지 않음)
    let guardActId = 0;            // 쪽 이동마다 1씩 — 맞추기 전에 사용자가 또 넘겼으면 끼어들지 않음
    let guardLastAct = null;
    let guardRaw = null;           // 감싸기 전 epub.js 함수(맞출 때 쓰면 다시 '사용자 이동'으로 잡히지 않음)
    let guardInResize = () => false; // 화면 크기 바뀌는 중인지(setupLayoutGuard 에서 채움)
    function guardRenderMarks() {
        try { const vs = rendition && rendition.views && rendition.views(); if (vs && vs.forEach) vs.forEach(v => { try { if (v && v.pane) v.pane.render(); } catch (e) {} }); } catch (e) {}
    }
    function guardShownIndex() {
        try { const l = rendition.currentLocation(); return (l && l.start && typeof l.start.index === 'number') ? l.start.index : -1; } catch (e) { return -1; }
    }
    function guardWaitStable(doc) {
        // 글꼴·그림을 다 읽을 때까지(최대 2.5초) + 화면 두 번 그림
        const waits = [];
        try { if (doc.fonts && doc.fonts.ready) waits.push(doc.fonts.ready.catch(() => {})); } catch (e) {}
        try {
            Array.prototype.forEach.call(doc.images || [], img => {
                if (!img.complete) waits.push(new Promise(r => { img.addEventListener('load', r, { once: true }); img.addEventListener('error', r, { once: true }); }));
            });
        } catch (e) {}
        const raf2 = () => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
        return Promise.race([Promise.all(waits), new Promise(r => setTimeout(r, 2500))]).then(raf2);
    }
    function guardScrollToLast() {
        const m = rendition.manager;
        if (!m || !m.isPaginated || !m.container || !m.layout || (m.settings && (m.settings.direction === 'rtl' || m.settings.axis === 'vertical'))) return false;
        const last = m.container.scrollWidth - m.layout.delta;
        if (!(last >= 0) || Math.abs(m.container.scrollLeft - last) <= 1) return false;
        m.scrollTo(last, 0, true);
        try { rendition.reportLocation(); } catch (e) {}
        return true;
    }
    function setupLayoutGuard() {
        if (!rendition) return;
        // ① epub.js 의 늦은 그림 크기 조정 빼기(같은 제한을 그리기 전에 넣었음)
        try {
            const hk = rendition.hooks && rendition.hooks.content && rendition.hooks.content.hooks;
            if (Array.isArray(hk)) for (let i = hk.length - 1; i >= 0; i--) if (hk[i] && /adjustImages/.test(hk[i].name || '')) hk.splice(i, 1);
        } catch (e) {}
        // 쪽 이동 감싸기 — 무엇을 하려던 건지 기억
        guardRaw = { next: rendition.next.bind(rendition), prev: rendition.prev.bind(rendition), display: rendition.display.bind(rendition) };
        const act = (a) => { a.id = ++guardActId; guardLastAct = a; return a; };
        rendition.next = function () { act({ t: 'next', from: guardShownIndex() }); return guardRaw.next(); };
        rendition.prev = function () { act({ t: 'prev', from: guardShownIndex() }); return guardRaw.prev(); };
        // ★ (2026-11-01) 화면 크기가 바뀌면(휴대폰 돌리기·주소창) epub.js 는 '지금 쪽의 시작'으로 다시 그림 → 돌릴 때마다 조금씩 앞으로 밀렸음
        //   → 크기 바뀌기 직전에 보던 자리를 기억해 두고, 크기 변경으로 다시 그릴 때는 그 자리로(사용자가 넘기면 다시 그 쪽 기준)
        let resizeAnchor = null, resizeUntil = 0;
        window.addEventListener('resize', () => {
            const now = Date.now();
            if (now > resizeUntil) resizeAnchor = guardAnchor;
            resizeUntil = now + 1500;
        }, true);
        guardInResize = () => Date.now() < resizeUntil;
        rendition.display = function (target) {
            try {
                // epub.js 가 크기 변경 때 부르는 display(지금 쪽 시작) 만 바꿈 — 목차·노트 등 사용자가 고른 이동은 그대로
                if (guardInResize() && resizeAnchor && rendition.location && rendition.location.start && target === rendition.location.start.cfi) target = resizeAnchor;
            } catch (e) {}
            const a = act({ t: 'display', target: target }); return (a.p = guardRaw.display(target));
        };
        rendition.on('relocated', (loc) => { if (!guardBusy && !guardInResize() && loc && loc.start && loc.start.cfi) guardAnchor = loc.start.cfi; });
        // ② 장이 다 자리 잡으면 다시 그리고 맞춤 (기다리는 동안 epub.js 표시는 그대로 진행 — 이 훅은 결과를 기다리게 하지 않음)
        rendition.hooks.content.register((contents) => {
            const doc = contents && contents.document; if (!doc) return;
            const a = guardLastAct, idx = contents.sectionIndex;
            let settled = false;
            const markSettled = () => {
                settled = true;
                if (guardInResize()) return; // 크기 바뀌는 중엔 보던 자리를 그대로 둠
                // 맞춘 뒤의 쪽을 '보던 자리'로(늦은 글 흐름 때 여기로 돌아옴)
                try { const l = rendition.currentLocation(); if (l && l.start && l.start.cfi && l.start.index === idx) guardAnchor = l.start.cfi; } catch (e) {}
            };
            guardWaitStable(doc).then(() => Promise.resolve(a && a.p).catch(() => {})).then(() => new Promise(r => setTimeout(r, 30))).then(() => {
                guardRenderMarks();
                if (!a || a.id !== guardActId) { settled = true; return; } // 그새 또 넘겼음 — 끼어들지 않음
                guardBusy++;
                const done = () => { setTimeout(() => { markSettled(); guardBusy = Math.max(0, guardBusy - 1); }, 300); };
                if (a.t === 'prev' && a.from > idx) {
                    guardScrollToLast(); // 앞 장으로 넘어왔으면 그 장의 마지막 쪽
                    done();
                } else if (a.t === 'display') {
                    return Promise.resolve(guardRaw.display(a.target)).catch(() => {}).then(() => { guardRenderMarks(); done(); });
                } else {
                    done();
                }
            }).catch(() => { settled = true; });
            // ③ 늦게 글이 흐름(글꼴·그림이 늦게 옴) → 다시 그리고 보던 자리로
            let lateT = 0;
            const late = () => {
                clearTimeout(lateT);
                lateT = setTimeout(() => {
                    guardRenderMarks();
                    if (!settled || !guardAnchor) return; // 자리 잡기 전에는 위(②)에서 맞춤
                    let cur = null; try { cur = rendition.currentLocation(); } catch (e) {}
                    if (!cur || !cur.start || cur.start.index !== idx || cur.start.cfi === guardAnchor) return;
                    // 보던 자리가 이 장의 것일 때만(다른 장으로 끌려가지 않게)
                    try { const ac = new ePub.CFI(guardAnchor); if (ac.spinePos !== idx) return; } catch (e) { return; }
                    guardBusy++;
                    Promise.resolve(guardRaw.display(guardAnchor)).catch(() => {}).then(() => { guardRenderMarks(); setTimeout(() => { guardBusy = Math.max(0, guardBusy - 1); }, 300); });
                }, 200);
            };
            try { if (doc.fonts && doc.fonts.addEventListener) doc.fonts.addEventListener('loadingdone', late); } catch (e) {}
            try { doc.addEventListener('load', (e) => { if (e.target && e.target.tagName && /^(img|image)$/i.test(e.target.tagName)) late(); }, true); } catch (e) {}
        });
    }
    // 글자 크기를 바꾼 뒤 보던 자리 유지(바꾸는 동안 여러 번 와도 처음 자리 기준)
    let fsAnchor = null, fsT = 0;
    function keepPlaceAfterFontChange() {
        if (!rendition || !guardRaw) return;
        if (!fsAnchor) fsAnchor = guardAnchor || (rendition.location && rendition.location.start && rendition.location.start.cfi) || null;
        clearTimeout(fsT);
        fsT = setTimeout(() => {
            const target = fsAnchor; fsAnchor = null;
            requestAnimationFrame(() => requestAnimationFrame(() => {
                if (!target) { guardRenderMarks(); return; }
                guardBusy++;
                Promise.resolve(guardRaw.display(target)).catch(() => {}).then(() => { guardRenderMarks(); setTimeout(() => { guardBusy = Math.max(0, guardBusy - 1); }, 300); });
            }));
        }, 250);
    }
    // 진행률(%) — 위치 정보가 준비됐을 때만. 책 마지막 쪽은 100%
    function percentOfLocation(loc) {
        if (!locationsReady || !loc || !loc.start) return -1;
        if (loc.atEnd) return 100;
        const p = loc.start.percentage;
        return (typeof p === 'number' && isFinite(p)) ? Math.max(0, Math.min(100, Math.round(p * 100))) : -1;
    }

    // EPUB 초기화
async function initBook() {
    try {
        // EPUB 파일을 ArrayBuffer로 먼저 로드
        loadingEl.textContent = _vi18n.epub_loading;
        const response = await fetch(FILE_URL);
        if (!response.ok) {
            throw new Error(_vi18n.epub_load_fail + ' ' + response.status);
        }
        const arrayBuffer = await response.arrayBuffer();
        
        // ArrayBuffer로 epub.js 초기화
        loadingEl.textContent = _vi18n.epub_parsing;
        book = ePub(arrayBuffer);
        // ★ (2026-10-30) 보안: 책 안의 스크립트·이벤트 속성·javascript: 링크를 그리기 전에 제거
        //   (iOS 에서 탭·키 이벤트를 받으려고 allowScriptedContent 를 켜 둬서, 외부 EPUB 의 스크립트가 사이트 권한으로 실행될 수 있었음)
        // ★ (2026-11-03 00:41) 아래 주석에 PHP 여는 태그처럼 보이는 글자가 있어 short_open_tag=On 서버에서 이 화면이 500 이었음 → 글자를 바꿈
        try {
            book.spine.hooks.content.register(function (doc) {
                if (!doc || !doc.getElementsByTagName) return;
                // ★ (2026-11-02 08:46) 보안: 주석·CDATA·처리 지시(물음표로 여닫는 태그) 안에 숨긴 태그는 위 요소 검사로 안 보이는데, 책 화면은 XHTML 을 글자로 바꾼 뒤
                //   HTML 로 다시 읽어서 그 태그가 살아나 실행될 수 있었음 → 주석·처리 지시는 지우고, CDATA 는 같은 글자의 보통 글로 바꿈
                try {
                    var tw = doc.createTreeWalker(doc, 0x80 | 0x40 | 0x8 /* COMMENT | PROCESSING_INSTRUCTION | CDATA_SECTION */, null, false), odd = [], nd;
                    while ((nd = tw.nextNode())) odd.push(nd);
                    odd.forEach(function (n) {
                        if (!n.parentNode) return;
                        if (n.nodeType === 4) n.parentNode.replaceChild(doc.createTextNode(n.data), n); // CDATA → 글자
                        else if (n.nodeType === 8 && String(n.parentNode.localName || n.parentNode.nodeName || '').toLowerCase() === 'style') n.parentNode.replaceChild(doc.createTextNode(n.data), n); // ★ (2026-11-02 09:36) <style><!-- CSS --></style>(옛 ePub2) 서식은 글자로 남김
                        else n.parentNode.removeChild(n);
                    });
                } catch (e0) {}
                var drop = {script:1, iframe:1, frame:1, frameset:1, object:1, embed:1, base:1, applet:1, portal:1};
                var all = Array.prototype.slice.call(doc.getElementsByTagName('*'));
                all.forEach(function (el) {
                    var tag = String(el.localName || el.nodeName || '').toLowerCase();
                    if (drop[tag]) { if (el.parentNode) el.parentNode.removeChild(el); return; }
                    if (tag === 'meta' && /refresh/i.test(el.getAttribute('http-equiv') || '')) { if (el.parentNode) el.parentNode.removeChild(el); return; }
                    if ((tag === 'set' || tag.indexOf('animate') === 0) && /href/i.test(el.getAttribute('attributeName') || '')) { if (el.parentNode) el.parentNode.removeChild(el); return; }
                    Array.prototype.slice.call(el.attributes || []).forEach(function (at) {
                        var n = String(at.localName || at.name || '').toLowerCase();
                        var v = String(at.value || '').replace(/[\s\u0000-\u001f]+/g, '').toLowerCase();
                        if (n.indexOf('on') === 0 ||
                            ((n === 'href' || n === 'src' || n === 'action' || n === 'formaction' || n === 'data' || n === 'srcdoc') &&
                             (v.indexOf('javascript:') === 0 || v.indexOf('vbscript:') === 0 || v.indexOf('data:text/html') === 0)) ||
                            n === 'srcdoc') {
                            el.removeAttributeNode(at);
                        }
                    });
                });
            });
        } catch (e) { console.warn('epub sanitize hook', e); }
        // ★ (2026-10-31) 그림 크기 제한을 '그리기 전'에 넣음 — epub.js 는 화면을 다 그리고 쪽 위치까지 정한 뒤에 그림 크기를 바꿔서(adjustImages)
        //   글이 밀려 내려감 → 쪽 위치가 어긋나고(앞 장으로 돌아가면 마지막 쪽이 아니라 그 앞 쪽), 형광펜·밑줄이 글과 따로 놂.
        //   같은 제한(칸 폭 · 화면 높이의 95%)을 문서에 미리 넣고, 나중에 바꾸는 epub.js 기본 동작은 아래에서 뺌
        try {
            book.spine.hooks.content.register(function (doc, section) {
                if (!doc || !doc.documentElement) return;
                // 고정 레이아웃(만화·그림책 EPUB)은 epub.js 도 그림 크기를 건드리지 않음 → 그대로 둠
                try {
                    var md = book.packaging && book.packaging.metadata;
                    var props = (section && section.properties) || [];
                    if ((md && md.layout === 'pre-paginated' && props.indexOf('rendition:layout-reflowable') < 0) || props.indexOf('rendition:layout-pre-paginated') >= 0) return;
                } catch (e) {}
                var ns = doc.documentElement.namespaceURI || 'http://www.w3.org/1999/xhtml';
                var head = doc.getElementsByTagName('head')[0];
                if (!head) { head = doc.createElementNS(ns, 'head'); doc.documentElement.insertBefore(head, doc.documentElement.firstChild); }
                var st = doc.createElementNS(ns, 'style');
                st.setAttribute('id', 'mc-img-fit');
                st.textContent = 'img{max-width:100% !important;max-height:calc(95vh - 38px) !important;object-fit:contain;page-break-inside:avoid;break-inside:avoid;box-sizing:border-box}'
                    + 'svg{max-width:100% !important;max-height:calc(95vh - 38px) !important;page-break-inside:avoid;break-inside:avoid}';
                head.appendChild(st);
            });
        } catch (e) { console.warn('epub image hook', e); }
        // ★ (2026-11-01) 책 CSS 가 적용되면서(위 CSP) 생길 수 있는 문제 막기 — 그리기 전에 넣음
        //   · 어두운 테마: 책이 글자·바탕 색을 정해 놓았어도 테마 색으로(검은 바탕에 검은 글 방지) · 세피아는 바탕색만 · 밝은 테마는 책 색 그대로
        //   · 관리자가 EPUB 글꼴을 정했으면 그 글꼴로(예전처럼 모든 글에), 아니면 책에 든 글꼴 사용
        //     관리자 글꼴은 이제 책 화면 안에도 넣음(예전엔 바깥 화면에만 있어 책 글자에는 안 먹었음)
        try {
            const EV_FONT = <?php echo json_encode(['name' => preg_replace('/[^\p{L}\p{N} _\-]/u', '', (string)$epub_font_name), 'url' => (string)$epub_font_url, 'local' => (string)$epub_font_local], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>; // 글꼴 이름은 글자·숫자·공백·_-만
            let fontCss = '';
            if (EV_FONT.name) {
                if (!EV_FONT.url && EV_FONT.local) {
                    let abs = ''; try { abs = new URL(EV_FONT.local, location.href).href; } catch (e) {}
                    if (/^https?:\/\//i.test(abs)) fontCss += "@font-face{font-family:'" + EV_FONT.name + "';src:url(\"" + abs.replace(/["\\]/g, '') + "\");font-display:swap}";
                }
                fontCss += "body,body *:not(code):not(pre):not(kbd):not(samp):not(tt){font-family:'" + EV_FONT.name + "','Nanum Gothic','Malgun Gothic',sans-serif !important}";
            }
            const themeCss =
                'body.dark{background:#1a1a2e !important;color:#e0e0e0 !important}' +
                'body.dark *:not(a){color:#e0e0e0 !important;background-color:transparent !important}' +
                'body.dark a,body.dark a *{color:#8ab4f8 !important}' +
                'body.sepia{background:#f4ecd8 !important;color:#5b4636 !important}'; // 세피아는 바탕만(책의 진한 글자 색은 세피아 바탕에서도 잘 보임)
            book.spine.hooks.content.register(function (doc) {
                if (!doc || !doc.documentElement) return;
                const ns = doc.documentElement.namespaceURI || 'http://www.w3.org/1999/xhtml';
                let head = doc.getElementsByTagName('head')[0];
                if (!head) { head = doc.createElementNS(ns, 'head'); doc.documentElement.insertBefore(head, doc.documentElement.firstChild); }
                if (EV_FONT.name && /^https:\/\/fonts\.googleapis\.com\//i.test(EV_FONT.url)) {
                    const ln = doc.createElementNS(ns, 'link'); ln.setAttribute('rel', 'stylesheet'); ln.setAttribute('href', EV_FONT.url); head.appendChild(ln);
                }
                const st = doc.createElementNS(ns, 'style');
                st.setAttribute('id', 'mc-theme-guard');
                st.textContent = themeCss + fontCss;
                head.appendChild(st);
            });
        } catch (e) { console.warn('epub theme hook', e); }
        // ★ (2026-11-01) 줄 간격(사용자가 고른 값 — '책 기본'이면 넣지 않음)도 그리기 전에
        try {
            book.spine.hooks.content.register(function (doc, section) {
                if (!doc || !doc.documentElement) return;
                if (isFixedSection(section)) return; // ★ (2026-11-01 17:05) 고정 레이아웃(만화·그림책)은 글꼴·간격을 바꾸면 글이 칸을 넘침 → 그대로
                const ns = doc.documentElement.namespaceURI || 'http://www.w3.org/1999/xhtml';
                let head = doc.getElementsByTagName('head')[0];
                if (!head) { head = doc.createElementNS(ns, 'head'); doc.documentElement.insertBefore(head, doc.documentElement.firstChild); }
                const st = doc.createElementNS(ns, 'style');
                st.setAttribute('id', 'mc-user-typo');
                st.textContent = typoCss();
                head.appendChild(st);
            });
        } catch (e) { console.warn('epub typo hook', e); }
        applyMargin(); // ★ (2026-11-01) 좌우 여백(책 영역을 안쪽으로)

            rendition = book.renderTo(viewerEl, {
                width: '100%',
                height: '100%',
                spread: 'none',
                allowScriptedContent: true
            });
            setupLayoutGuard(); // ★ (2026-10-31) 쪽 위치·형광펜 위치 안정화
            { const _sm = spreadMode(); if (_sm[0] !== 'none') { try { rendition.spread(_sm[0], _sm[1]); } catch (e) {} } } // ★ (2026-11-01) 넓은 화면 2쪽(고른 경우만) · ★ (2026-11-04 18:04) 가로 화면 2쪽도
            setupFootnotes(); // ★ (2026-11-01) 각주 누르면 작은 창
            try { setupLiveTypo(); setupImageZoom(); } catch (e) { console.warn('typo/img', e); } // ★ (2026-11-01) 양쪽 정렬 표시 · 그림 크게 보기

            // 테마 등록
            Object.keys(themes).forEach(name => {
                rendition.themes.register(name, themes[name]);
            });
            
            // 현재 테마 적용
            rendition.themes.select(currentTheme);
            rendition.themes.fontSize(currentFontSize + '%');
            
            // 저장된 위치 불러오기
            const savedProgress = await loadProgress();
            savedProgressData = savedProgress;
            
            if (savedProgress && savedProgress.cfi) {
                // ★ (2026-11-02 10:01) 저장 위치가 이 책에 없는 장을 가리키면(다른 판으로 바뀜 등) 처음부터 — 전에는 책이 아예 안 열렸음
                try { await rendition.display(savedProgress.cfi); } catch (eCfi) { await rendition.display(); }
            } else {
                await rendition.display();
            }
            
            // 목차 로드
            const toc = await book.loaded.navigation;
            renderToc(toc.toc);
            
            // ✅ 콘텐츠 먼저 표시! (로딩 완료)
            loadingEl.classList.add('hidden');
            try { setupNotes(toc.toc); } catch (e) { console.warn('notes', e); } // ★ (2026-10-31) 책 노트
            try { setupTools(toc.toc); } catch (e) { console.warn('tools', e); } // ★ (2026-11-01) 검색·듣기·화면 꺼짐 방지
            
            // ✅ 위치 변경 이벤트 (진행률 없이도 페이지 이동은 가능)
            rendition.on('relocated', handleRelocated);
            
            // ✅ iframe 내부 활동 감지 → 세션 연장
            let lastSessionExtend = 0;
            const SESSION_EXTEND_THROTTLE = 30000;
            
            function extendSessionFromEpub() {
                const now = Date.now();
                if (now - lastSessionExtend < SESSION_EXTEND_THROTTLE) return;
                lastSessionExtend = now;
                
                fetch('init.php?check_session=1&extend=1', {
                    method: 'GET',
                    cache: 'no-cache'
                }).then(r => r.json()).then(data => {
                    if (data.status === 'active') {
                        console.log('📖 EPUB 활동 감지 - 세션 연장:', data.remaining + '초');
                    }
                }).catch(() => {});
            }
            
            rendition.on('click', extendSessionFromEpub);
            rendition.on('keydown', extendSessionFromEpub);
            rendition.on('relocated', extendSessionFromEpub);
            
            rendition.hooks.content.register((contents) => {
                const doc = contents.document;
                if (doc) {
                    doc.addEventListener('touchstart', extendSessionFromEpub, { passive: true });
                    doc.addEventListener('scroll', extendSessionFromEpub, { passive: true });
                    
                    const body = doc.body;
                    if (body && themes[currentTheme]) {
                        body.style.background = themes[currentTheme].body.background;
                        body.style.color = themes[currentTheme].body.color;
                        body.style.fontFamily = themes[currentTheme].body['font-family'];
                    }
                }
            });
            
            // ✅ 위치 정보는 백그라운드에서 로드/생성 (UI 블로킹 없음)
            setTimeout(async () => {
                await loadLocationsInBackground();
            }, 100);
            
        } catch (err) {
            loadingEl.textContent = _vi18n.epub_render_fail + ' ' + (err && err.message ? err.message : ''); // ★ (2026-10-30) 오류 문구는 글자로
        }
    }
    
    // ✅ 위치 정보 백그라운드 로드/생성
    async function loadLocationsInBackground() {
        // 서버에서 캐시 로드 시도
        let cachedLocations = null;
        try {
            progressText.textContent = _vi18n.calculating;
            progressSlider.disabled = true;
            
            const cacheRes = await fetch(`epub_viewer.php?action=load_locations&bidx=<?php echo $current_bidx; ?>&file=${encodeURIComponent(FILE_KEY)}`);
            const cacheData = await cacheRes.json();
            if (cacheData.success && cacheData.locations) {
                cachedLocations = cacheData.locations;
                console.log('📚 서버 캐시에서 위치 정보 발견');
            }
        } catch (e) {
            console.warn('📚 서버 캐시 로드 실패:', e);
        }
        
        if (cachedLocations) {
            // 캐시된 위치 정보 사용 (즉시 로드)
            try {
                book.locations.load(cachedLocations);
                console.log('📚 캐시된 위치 정보 로드 완료, 총 위치:', book.locations.length());
                enableProgressSlider();
            } catch (e) {
                console.warn('📚 캐시 손상, 재생성:', e);
                await generateAndSaveLocations();
            }
        } else {
            // 최초 접근: 새로 계산 후 서버에 캐시 저장
            await generateAndSaveLocations();
        }
    }
    
    // ✅ 위치 정보 생성 및 저장
    async function generateAndSaveLocations() {
        progressText.textContent = _vi18n.calculating;
        console.log('📚 캐시 없음, 생성 시작...');
        await book.locations.generate(1024);
        console.log('📚 위치 정보 생성 완료, 총 위치:', book.locations.length());
        
        // 서버에 저장
        await saveLocationsToServer(book.locations.save());
        enableProgressSlider();
    }
    
    // 진행률 슬라이더 활성화
    let locationsReady = false;
    function enableProgressSlider() {
        locationsReady = true;
        progressSlider.disabled = false;
        
        // ★ (2026-10-31) 지금 보고 있는 위치로 계산(전에는 서버에 저장된 값을 먼저 보여 줘서, 다른 기기·글자 크기에서 저장한 값이면 지금 쪽과 달랐음)
        try {
            const cur = rendition && rendition.currentLocation ? rendition.currentLocation() : null;
            const pc = percentOfLocation(cur && cur.start ? cur : rendition && rendition.location);
            if (pc >= 0) { progressSlider.value = pc; progressText.textContent = pc + '%'; return; }
        } catch (e) {}
        // 서버에서 로드한 진행률이 있으면 그걸 사용
        if (savedProgressData && savedProgressData.percent >= 0) {
            const percent = savedProgressData.percent;
            progressSlider.value = percent;
            progressText.textContent = percent + '%';
            return;
        }
        
        // 서버 데이터 없으면 현재 위치로 계산
        if (rendition && rendition.location) {
            const loc = rendition.location;
            if (loc.start && typeof loc.start.percentage === 'number') {
                const percent = Math.round(loc.start.percentage * 100);
                progressSlider.value = percent;
                progressText.textContent = percent + '%';
            }
        }
    }
    
    // 목차 렌더링
    function renderToc(items, nested = false) {
        items.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item.label;
            if (nested) li.classList.add('nested');
            
            li.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('📑 목차 클릭:', item.label, item.href);
                document.getElementById('tocSidebar').classList.remove('show');
                
                // href에서 파일명과 앵커 분리
                let href = item.href;
                let anchor = '';
                if (href.includes('#')) {
                    const parts = href.split('#');
                    href = parts[0];
                    anchor = parts[1];
                }
                
                try {
                    // 방법 1: 직접 display
                    await rendition.display(item.href);
                    console.log('✅ 목차 이동 성공 (방법 1)');
                } catch (err1) {
                    console.warn('⚠️ 방법 1 실패:', err1.message);
                    
                    try {
                        // 방법 2: spine에서 섹션 찾아서 cfi로 이동
                        const section = book.spine.get(href);
                        if (section) {
                            const cfi = section.cfiFromElement ? 
                                section.cfiFromElement(section.document.body) : 
                                book.spine.cfiFromPercentage(section.index / book.spine.length);
                            await rendition.display(cfi);
                            console.log('✅ 목차 이동 성공 (방법 2)');
                        } else {
                            throw new Error(_vi18n.epub_no_section);
                        }
                    } catch (err2) {
                        console.warn('⚠️ 방법 2 실패:', err2.message);
                        
                        try {
                            // 방법 3: spine 인덱스로 시도
                            const spineItems = book.spine.items || book.spine.spineItems;
                            for (let i = 0; i < spineItems.length; i++) {
                                const spineItem = spineItems[i];
                                if (spineItem.href && (spineItem.href.includes(href) || href.includes(spineItem.href))) {
                                    await rendition.display(i);
                                    console.log('✅ 목차 이동 성공 (방법 3, 인덱스:', i + ')');
                                    return;
                                }
                            }
                            throw new Error(_vi18n.epub_spine_not_found);
                        } catch (err3) {
                            console.error('❌ 모든 방법 실패:', err3.message);
                        }
                    }
                }
            });
            
            tocList.appendChild(li);
            
            if (item.subitems && item.subitems.length > 0) {
                renderToc(item.subitems, true);
            }
        });
    }
    
    // ============================================================
    // ★ (2026-10-31) 책 노트 — 형광펜·밑줄·메모·책갈피 (공통 화면: js/book-notes.js)
    //   위치는 epub.js CFI, 표시는 rendition.annotations(형광펜 highlight · 밑줄 underline) — 글자 크기·테마가 바뀌어도 epub.js 가 다시 그림
    // ============================================================
    let notes = null;
    let notesRedraw = null;
    function setupNotes(tocItems) {
        if (!window.MCNotes || !rendition || !book) return;
        const NT = <?php echo json_encode([
            'highlight' => __('bn_highlight'), 'underline' => __('bn_underline'), 'memo' => __('bn_memo'), 'memo_edit' => __('bn_memo_edit'),
            'copy' => __('bn_copy'), 'del' => __('bn_delete'), 'save' => __('bn_save'), 'cancel' => __('bn_cancel'), 'close' => __('bn_close'),
            'memo_ph' => __('bn_memo_ph'), 'panel' => __('bn_panel'), 'tab_all' => __('bn_tab_all'), 'tab_bm' => __('bn_tab_bm'),
            'tab_mark' => __('bn_tab_mark'), 'tab_memo' => __('bn_tab_memo'), 'all_colors' => __('bn_all_colors'), 'empty' => __('bn_empty'),
            'empty_filter' => __('bn_empty_filter'), 'export' => __('bn_export'), 'copied' => __('bn_copied'), 'save_fail' => __('bn_save_fail'),
            'too_many' => __('bn_too_many'), 'confirm_del' => __('bn_confirm_del'), 'bm_add' => __('bn_bm_add'), 'bm_remove' => __('bn_bm_remove'),
            'bm_added' => __('bn_bm_added'), 'bm_removed' => __('bn_bm_removed'),
            'c_yellow' => __('bn_c_yellow'), 'c_green' => __('bn_c_green'), 'c_blue' => __('bn_c_blue'), 'c_pink' => __('bn_c_pink'), 'c_purple' => __('bn_c_purple'),
            'c_orange' => __('bn_c_orange'), 'c_gray' => __('bn_c_gray'), 'c_coral' => __('bn_c_coral'), 'c_mint' => __('bn_c_mint'), 'listen_here' => __('bn_listen_here'), /* ★ fix40 */ 'color_filter' => __('bn_color_filter'), 'all_colors_tip' => __('bn_all_colors_tip'), 'color_only' => __('bn_color_only'), 'memo_badge' => __('bn_memo_badge'), // ★ (2026-11-07 fix39) 주황·회색 · 색 거르기 설명 · 메모 표
            'dict' => __('bn_dict'), 'translate' => __('bn_translate'), // ★ (2026-11-01) 사전 찾기 · 번역
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const CFI = (window.ePub && ePub.CFI) ? new ePub.CFI() : null;
        const cmp = (a, b) => { try { return CFI ? CFI.compare(a, b) : 0; } catch (e) { return 0; } };
        // 장 이름: 목차 href 의 파일 부분으로
        const tocMap = {};
        (function walk(arr) { (arr || []).forEach(it => { const f = String(it.href || '').split('#')[0]; if (f && !tocMap[f]) tocMap[f] = String(it.label || '').trim(); walk(it.subitems); }); })(tocItems);
        const chapterOf = (href) => {
            const f = String(href || '').split('#')[0]; if (!f) return '';
            if (tocMap[f]) return tocMap[f];
            const k = Object.keys(tocMap).find(x => f.endsWith(x) || x.endsWith(f)); return k ? tocMap[k] : '';
        };
        const pctOf = (cfi) => { try { if (locationsReady) { const v = book.locations.percentageFromCfi(cfi); if (typeof v === 'number' && isFinite(v)) return Math.round(v * 1000) / 10; } } catch (e) {} return -1; };
        const typeOf = (it) => it.k === 'ul' ? 'underline' : 'highlight';
        const styleOf = (it) => {
            const dark = currentTheme === 'dark';
            // 밑줄: epub.js 가 글 둘레 상자(rect)와 아래 선(line)을 그림 — 상자는 숨기고 선만 색·굵기(css: js/book-notes.js 의 g.mcn-ul-*)
            if (it.k === 'ul') return { 'mix-blend-mode': 'normal' };
            return { fill: MCNotes.FILL[it.c] || MCNotes.FILL.yellow, 'fill-opacity': dark ? '0.38' : '0.55', 'mix-blend-mode': dark ? 'normal' : 'multiply' };
        };
        const drawn = {}; // id → {cfi, type}
        const adapter = {
            kind: 'epub',
            draw(it) {
                if (!it || it.k === 'bm' || !it.cfi) return;
                const type = typeOf(it);
                try {
                    rendition.annotations.add(type, it.cfi, { id: it.id }, (e) => {
                        try { e && e.stopPropagation && e.stopPropagation(); } catch (x) {}
                        const t = e && e.target && e.target.getBoundingClientRect ? e.target.getBoundingClientRect() : { left: innerWidth / 2, right: innerWidth / 2, top: innerHeight / 2, bottom: innerHeight / 2 };
                        notes.clicked(it, t);
                    }, it.k === 'ul' ? 'mcn-ul-' + (MCNotes.LINE[it.c] ? it.c : 'yellow') : 'mcn-highlight', styleOf(it)); // 클래스 이름은 하나만(epub.js 가 classList.add 로 넣어 띄어쓰기가 있으면 그리기 실패)
                    drawn[it.id] = { cfi: it.cfi, type };
                } catch (e) { console.warn('annotation add', e); }
            },
            undraw(it) {
                const d = it && drawn[it.id]; if (!d) return;
                try { rendition.annotations.remove(d.cfi, d.type); } catch (e) {}
                delete drawn[it.id];
            },
            go(it) { if (it && it.cfi) rendition.display(it.cfi); },
            here() {
                const loc = rendition.currentLocation && rendition.currentLocation();
                if (!loc || !loc.start || !loc.start.cfi) return null;
                let q = '';
                try { const r = rendition.getRange(loc.start.cfi); /* ★ (2026-11-02 14:32) book.getRange 는 Promise 라 미리보기 글이 늘 비어 있던 것 — TTS 처럼 rendition 것 */ if (r && r.startContainer) q = String(r.startContainer.textContent || '').slice(r.startOffset || 0).replace(/\s+/g, ' ').trim().slice(0, 80); } catch (e) {}
                return { pos: { cfi: loc.start.cfi }, q, ch: chapterOf(loc.start.href), p: pctOf(loc.start.cfi) };
            },
            onPage(bm) {
                const loc = rendition.currentLocation && rendition.currentLocation();
                if (!loc || !loc.start || !loc.end || !bm.cfi) return false;
                return cmp(bm.cfi, loc.start.cfi) >= 0 && cmp(bm.cfi, loc.end.cfi) <= 0;
            },
            compare(a, b) { return cmp(a.cfi, b.cfi); },
            // ★ (2026-11-07 fix39) 메모 표 자리 — 표시 글의 마지막 줄(지금 그려진 장에 있을 때만), 화면 좌표
            rectOf(it) {
                if (!it || !it.cfi) return null;
                let rg = null; try { rg = rendition.getRange(it.cfi); } catch (e) { rg = null; }
                if (!rg || !rg.getClientRects) return null;
                const doc = rg.startContainer && rg.startContainer.ownerDocument, fr = doc && doc.defaultView && doc.defaultView.frameElement;
                if (!fr) return null;
                const rs = Array.prototype.filter.call(rg.getClientRects(), r => r.width > 0 || r.height > 0);
                if (!rs.length) return null;
                const l = rs[rs.length - 1], f = fr.getBoundingClientRect();
                return { left: l.left + f.left, right: l.right + f.left, top: l.top + f.top, bottom: l.bottom + f.top };
            },
            area() { const r = viewerEl.getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top, bottom: r.bottom }; }
        };
        notes = MCNotes.init({
            api: 'epub_viewer.php', file: FILE_KEY, bidx: BIDX, csrf: <?php echo json_encode(generate_csrf_token()); ?>,
            t: NT, title: <?php echo json_encode($title, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, adapter,
            bmButton: document.getElementById('btnBookmark'), panelButton: document.getElementById('btnNotes'), toolbar: document.querySelector('.epub-toolbar')
        });
        notesRedraw = () => { notes.items().forEach(it => { adapter.undraw(it); adapter.draw(it); }); };
        // 글 고르기 → 메뉴(손가락으로 끄는 동안 여러 번 오므로 잠깐 기다렸다가)
        let selT = 0;
        rendition.on('selected', (cfiRange, contents) => {
            clearTimeout(selT);
            selT = setTimeout(() => {
                try {
                    const win = contents.window, sel = win.getSelection();
                    if (!sel || sel.isCollapsed || !sel.rangeCount) return;
                    const q = String(sel.toString() || '').replace(/[ \t]+\n/g, '\n').trim();
                    if (!q) return;
                    const r = sel.getRangeAt(0).getBoundingClientRect();
                    const f = win.frameElement ? win.frameElement.getBoundingClientRect() : { left: 0, top: 0 };
                    const rect = { left: r.left + f.left, right: r.right + f.left, top: r.top + f.top, bottom: r.bottom + f.top };
                    const loc = rendition.currentLocation && rendition.currentLocation();
                    notes.selected({ pos: { cfi: cfiRange }, q: q.slice(0, 1000), ch: chapterOf((() => { try { const sec = book.spine.get(contents.sectionIndex); if (sec && sec.href) return sec.href; } catch (e) {} return loc && loc.start ? loc.start.href : ''; })()), p: pctOf(cfiRange), rect, clear: () => { try { sel.removeAllRanges(); } catch (e) {} } });
                } catch (e) { console.warn('notes select', e); }
            }, 250);
        });
        // 본문을 누르면(고른 글이 없을 때) 메뉴 닫기
        rendition.on('click', (e) => {
            try { const w = e && e.view; const s = w && w.getSelection && w.getSelection(); if (!s || s.isCollapsed) notes.hidePop(); } catch (x) {}
        });
        rendition.on('relocated', () => { try { notes.relocated(); } catch (e) {} });
        rendition.on('resized', () => { try { notes.badges(); setTimeout(() => notes.badges(), 300); } catch (e) {} }); // ★ fix39 글자 크기·창 크기 바뀌면 메모 표 다시 놓음
        notes.load().then(() => {
            notes.relocated();
            // ★ (2026-11-03 00:06) 북마크 화면의 책갈피에서 열었으면(?note=아이디) 그 자리로(저장된 읽던 위치보다 우선)
            try { const nid = new URLSearchParams(location.search).get('note'); if (nid && /^[a-z0-9]{8,32}$/.test(nid)) notes.goNote(nid); } catch (e) {}
        });
    }

    // ============================================================
    // ★ (2026-11-01) 읽기 도구 — 줄 간격·좌우 여백·넓은 화면 2쪽(이 기기에 기억) · 각주 창 · 본문 검색 · 듣기(TTS) · 화면 꺼짐 방지
    //   (펜닐 요청: 전자책 앱과 비교해 빠진 기능) 공통 화면은 js/book-tools.js, 여기는 EPUB 에 맞춘 부분만
    // ============================================================
    const TT = <?php echo json_encode([
        'search' => __('bt_search'), 'search_ph' => __('bt_search_ph'), 'search_none' => __('bt_search_none'), 'search_count' => __('bt_search_count'),
        'search_more' => __('bt_search_more'), 'searching' => __('bt_searching'), 'prev' => __('bt_prev'), 'next' => __('bt_next'), 'close' => __('bt_close'),
        'tts' => __('bt_tts'), 'tts_pause' => __('bt_tts_pause'), 'tts_resume' => __('bt_tts_resume'), 'tts_stop' => __('bt_tts_stop'),
        'tts_slower' => __('bt_tts_slower'), 'tts_faster' => __('bt_tts_faster'), 'tts_none' => __('bt_tts_none'), 'tts_end' => __('bt_tts_end'), 'tts_voice' => __('bt_tts_voice'), 'tts_voice_auto' => __('bt_tts_voice_auto'), 'tts_voice_more' => __('bt_tts_voice_more'), 'tts_voice_help' => __('bt_tts_voice_help'), /* ★ fix40 목소리 더 받는 법 */ /* ★ fix39 목소리 고르기 */
        'wake_none' => __('bt_wake_none'), 'footnote' => __('bt_footnote'),
        'ch_left' => __('bt_ch_left'), 'ch_last' => __('bt_ch_last'), 'rt_line' => __('bt_rt_line'), 'rt_h' => __('bt_rt_h'), 'rt_m' => __('bt_rt_m'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const SPREAD_MIN = 900; // 이 폭(px) 이상일 때만 2쪽
    // ★ (2026-11-04 18:04) 2쪽 펼침 — '0' 끔(기본) / '1' 넓은 화면(폭 900 이상, 예전 체크 칸 켬과 같음) / 'land' 가로 화면(폭 > 높이면 폭과 상관없이 2쪽)
    function spreadPref() { const v = readPref('epub_spread'); return (v === '1' || v === 'land') ? v : '0'; }
    function isLandscape() { return window.innerWidth > window.innerHeight; }
    function spreadMode() {
        const p = spreadPref();
        if (p === '1') return ['auto', SPREAD_MIN];
        if (p === 'land') return isLandscape() ? ['auto', 1] : ['none'];
        return ['none'];
    }
    function readPref(k) { try { return localStorage.getItem(k) || ''; } catch (e) { return ''; } }
    function writePref(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    function lineHeightPref() { const v = readPref('epub_lh'); return /^(1\.[4-9]|2\.[0-4])$/.test(v) ? v : ''; }
    function marginPref() { const v = readPref('epub_margin'); return (v === '1' || v === '2') ? +v : 0; }
    // ★ (2026-11-01 11:22) 글꼴(책 기본·고딕·명조) · 문단 간격 · 양쪽 정렬 — 기기에 있는 글꼴만 씀(내려받기 없음)
    const FONT_STACK = {
        gothic: "'Noto Sans KR','Apple SD Gothic Neo','Malgun Gothic','맑은 고딕','Nanum Gothic',sans-serif",
        serif: "'Noto Serif KR','Nanum Myeongjo','AppleMyungjo','Batang','바탕',serif"
    };
    function fontPref() { const v = readPref('epub_font'); return FONT_STACK[v] ? v : ''; }
    function paraPref() { const v = readPref('epub_para'); return (v === '1' || v === '2' || v === '3') ? v : ''; }
    function justifyPref() { return readPref('epub_justify') === '1'; }
    function typoCss() {
        const lh = lineHeightPref(), f = fontPref(), pa = paraPref();
        let css = lh ? ('body,body *:not(img):not(svg):not(rt){line-height:' + lh + ' !important}') : '';
        if (f) css += 'body,body *:not(code):not(pre):not(kbd):not(samp):not(tt){font-family:' + FONT_STACK[f] + ' !important}';
        if (pa) css += 'p{margin-top:0 !important;margin-bottom:' + ({ '1': '0.3em', '2': '0.8em', '3': '1.4em' })[pa] + ' !important}';
        css += '[data-mcj]{text-align:justify !important}'; // 양쪽 정렬은 원래 왼쪽 정렬인 문단에만 표시(가운데 정렬 제목 등은 그대로)
        return css;
    }
    // 양쪽 정렬: 화면에 그린 뒤 원래 왼쪽(시작) 정렬인 문단에만 표시를 붙임(줄 나눔은 바뀌지 않음)
    function markJustify(doc) {
        if (!doc || !doc.querySelectorAll) return;
        const on = justifyPref();
        const ps = Array.prototype.slice.call(doc.querySelectorAll('p'));
        if (!on) { ps.forEach(p => { if (p.hasAttribute('data-mcj')) p.removeAttribute('data-mcj'); }); return; }
        // 먼저 다 읽고 나서 한꺼번에 표시(읽기·쓰기를 번갈아 하면 문단마다 스타일을 다시 계산함)
        const pick = ps.filter(p => {
            if (p.hasAttribute('data-mcj')) return false;
            let ta = ''; try { ta = doc.defaultView.getComputedStyle(p).textAlign; } catch (e) {}
            return ta === 'start' || ta === 'left' || ta === 'justify' || ta === '-webkit-auto';
        });
        pick.forEach(p => p.setAttribute('data-mcj', '1'));
    }
    // 고정 레이아웃(쪽마다 크기가 정해진 만화·그림책 EPUB) — 그림 크기 훅과 같은 판단
    function isFixedSection(section) {
        try {
            const md = book.packaging && book.packaging.metadata;
            const props = (section && section.properties) || [];
            return (md && md.layout === 'pre-paginated' && props.indexOf('rendition:layout-reflowable') < 0) || props.indexOf('rendition:layout-pre-paginated') >= 0;
        } catch (e) { return false; }
    }
    function setupLiveTypo() {
        rendition.hooks.content.register((contents) => { try { markJustify(contents.document); } catch (e) {} });
    }
    // 그림 크게 보기: 본문 그림(링크 안 그림·아주 작은 그림 제외)을 누르면 크게
    function setupImageZoom() {
        rendition.hooks.content.register((contents) => {
            const doc = contents && contents.document; if (!doc) return;
            try { if (isFixedSection(book.spine.get(contents.sectionIndex))) return; } catch (e) {} // 고정 레이아웃은 쪽 전체가 그림이라 누를 때마다 열리게 됨 → 예전처럼
            doc.addEventListener('click', (e) => {
                const t = e.target; if (!t || !t.closest) return;
                const im = t.closest('img, image'); if (!im || im.closest('a[href]')) return;
                try { const sel = doc.getSelection(); if (sel && !sel.isCollapsed) return; } catch (x) {}
                const r = im.getBoundingClientRect(); if (r.width < 60 || r.height < 60) return;
                let src = '';
                if ((im.localName || '').toLowerCase() === 'img') src = im.currentSrc || im.src || '';
                else { const h = im.getAttribute('href') || (im.getAttributeNS && im.getAttributeNS('http://www.w3.org/1999/xlink', 'href')) || ''; try { src = h ? new URL(h, doc.baseURI).href : ''; } catch (x) { src = ''; } }
                if (!/^(blob:|data:image\/|https?:)/i.test(src)) return;
                e.preventDefault(); e.stopPropagation();
                MCTools.image(src, im.getAttribute('alt') || '');
            });
        });
    }
    function applyMargin() {
        // 책 영역을 양옆으로 줄임(넓은 화면은 더, 휴대폰은 조금) — epub.js 는 #viewer 크기에 맞춰 쪽을 나눔
        const m = marginPref();
        const pct = m === 2 ? 12 : (m === 1 ? 6 : 0);
        const px = pct ? Math.round(Math.min(window.innerWidth * pct / 100, m === 2 ? 220 : 120)) : 0;
        viewerEl.style.left = px ? px + 'px' : '';
        viewerEl.style.right = px ? px + 'px' : '';
        return px;
    }

    // ── 각주: 각주 표시(noteref) 또는 숫자·*·† 링크를 누르면 그 내용을 작은 창으로(쪽은 그대로)
    //    일반 책 안 링크(목차 페이지·본문 안 이동)는 그 장으로 이동 — 예전엔 epub.js 링크 이동이 'No Section Found' 로 실패해 안 움직였음 ──
    function setupFootnotes() {
        const OPS = 'http://www.idpf.org/2007/ops';
        const noteTypeOf = (n) => { try { return ((n.getAttributeNS && n.getAttributeNS(OPS, 'type')) || n.getAttribute('epub:type') || '') + ' ' + (n.getAttribute('role') || ''); } catch (e) { return ''; } };
        const isBox = (n) => n && n.nodeType === 1 && /^(aside|li|p|div|section|dd|blockquote|td|footer)$/i.test(n.localName || '');
        function noteText(t) {
            if (!t) return null;
            if (/^(h[1-6]|body|html|article|nav)$/i.test(t.localName || '')) return null;
            let box = t;
            if (!isBox(box) || (box.textContent || '').trim().length < 2) { let p = t.parentNode; while (p && p.nodeType === 1 && !isBox(p)) p = p.parentNode; if (p && p.nodeType === 1 && !/^(body|html)$/i.test(p.localName)) box = p; }
            const txt = String(box.textContent || '').replace(/[ \t\r\f\u00a0]+/g, ' ').replace(/\s*\n\s*/g, '\n').replace(/\n{2,}/g, '\n').trim();
            return txt ? txt.slice(0, 3000) : null;
        }
        // ★ (2026-11-01 17:05) 각주 모음 쪽의 '[1]' 같은 되돌아가기 링크는 본문으로 이동(예전: 본문 문단을 각주 창으로 보여 줬음)
        //   · 누른 링크가 각주 상자(aside·footnote/endnote) 안에 있거나 backlink 로 표시됐으면
        //   · 가리키는 곳이 문단 한가운데의 작은 글(본문의 각주 번호 자리)이면
        function isBackLink(a, t) {
            if (/backlink/i.test(noteTypeOf(a))) return true;
            for (let n = a.parentNode; n && n.nodeType === 1; n = n.parentNode) {
                if (/^aside$/i.test(n.localName || '') || /(foot|end|rear)note/i.test(noteTypeOf(n))) return true;
            }
            if (t && /^(a|span|sup|sub|small|em|i|b|strong)$/i.test(t.localName || '')) {
                let box = t.parentNode; while (box && box.nodeType === 1 && !isBox(box)) box = box.parentNode;
                if (box && box.nodeType === 1) {
                    try { const r = t.ownerDocument.createRange(); r.setStart(box, 0); r.setEndBefore(t); if (String(r.toString()).replace(/\s+/g, '').length > 20) return true; } catch (x) {}
                }
            }
            return false;
        }
        function findIn(d, id) {
            if (!d || !id) return null;
            let t = null; try { t = d.getElementById(id); } catch (e) {}
            if (!t) { try { t = d.querySelector('[name="' + String(id).replace(/["\\]/g, '') + '"]'); } catch (e) {} }
            return t;
        }
        rendition.hooks.content.register((contents) => {
            const doc = contents && contents.document; if (!doc) return;
            doc.addEventListener('click', (e) => {
                const a = e.target && e.target.closest ? e.target.closest('a[href]') : null; if (!a) return;
                const href = a.getAttribute('href') || '';
                if (!href || /^[a-z][a-z0-9+.-]*:/i.test(href) || href.charAt(0) === '/') return; // 바깥 주소·절대 경로는 그대로
                const h = href.indexOf('#');
                const file = h < 0 ? href : href.slice(0, h);
                let id = h < 0 ? '' : href.slice(h + 1); try { id = decodeURIComponent(id); } catch (x) {}
                if (!file && !id) return;
                const isRef = /noteref/i.test(noteTypeOf(a));
                const lt = String(a.textContent || '').trim();
                const looks = /^[\[(]?\s*(\d{1,3}|[*†‡§※]+|[ivx]{1,4})\s*[\])]?$/i.test(lt) || (!!a.closest('sup') && lt.length <= 4);
                const secHref = (() => { try { const sc = book.spine.get(contents.sectionIndex); return sc ? sc.href : ''; } catch (x) { return ''; } })();
                const target = file ? (() => { try { return decodeURIComponent(new URL(file, 'http://x/' + secHref).pathname.replace(/^\//, '')); } catch (x) { return file; } })() : secHref;
                const sec = (() => { try { return book.spine.get(target); } catch (x) { return null; } })();
                if (!sec) return; // 책 안에서 못 찾으면 epub.js 에 맡김(예전 그대로)
                const stop = () => { e.preventDefault(); e.stopPropagation(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); };
                const go = () => { try { rendition.display(sec.href + (id ? '#' + id : '')); } catch (x) {} }; // 그 장(그 자리)으로 이동
                // 일반 링크(목차·본문 안 이동): 그 장으로 — epub.js 의 링크 이동은 이 뷰어에서 'No Section Found' 로 실패했음
                if ((!isRef && !looks) || !id) { stop(); go(); return; }
                stop();
                if (sec.index === contents.sectionIndex) { // 같은 파일 — 화면에 있는 문서에서 찾음
                    const el = findIn(doc, id);
                    if (!isRef && isBackLink(a, el)) { go(); return; }
                    const t = noteText(el);
                    if (!t || (!isRef && t.length > 1500)) { go(); return; } // 각주로 보기 어려우면 그 자리로 이동
                    MCTools.note(TT.footnote, t);
                    return;
                }
                // 다른 파일(책 뒤 각주 모음 등) — 그 파일을 읽어서 찾음(지금 쪽은 그대로)
                const had = !!sec.document;
                Promise.resolve(had ? sec.contents : sec.load(book.load.bind(book))).then((root) => {
                    const d2 = (root && root.ownerDocument) || sec.document; // load 가 준 문서(위 검색과 같은 이유)
                    const el = findIn(d2, id);
                    if (!isRef && isBackLink(a, el)) { if (!had) { try { sec.unload(); } catch (x) {} } go(); return; }
                    const t = noteText(el);
                    if (!had) { try { sec.unload(); } catch (x) {} }
                    if (!t || (!isRef && t.length > 1500)) { go(); return; }
                    MCTools.note(TT.footnote, t);
                }).catch(go);
            }, true);
        });
    }

    // ── 글 지도: 본문 글자를 이어 붙인 문자열 + 글자 번호 → (글 노드, 위치). 문단이 바뀌면 사이에 '\n'(노드에 없는 글자) ──
    const BLOCK_RE = /^(p|div|li|h[1-6]|blockquote|section|article|aside|td|th|dd|dt|pre|figure|figcaption|body|header|footer|tr|table|ul|ol|nav|dl)$/i;
    function blockOf(n) { let e = n.parentNode; while (e && e.nodeType === 1) { if (BLOCK_RE.test(e.localName || '')) return e; e = e.parentNode; } return null; }
    function textMap(doc) {
        const body = doc.body || doc.getElementsByTagName('body')[0] || doc.documentElement;
        const segs = []; let text = ''; let last = null;
        const tw = doc.createTreeWalker(body, NodeFilter.SHOW_TEXT, null, false);
        let n;
        while ((n = tw.nextNode())) {
            const pn = n.parentNode && n.parentNode.localName ? n.parentNode.localName.toLowerCase() : '';
            if (pn === 'script' || pn === 'style' || pn === 'title' || pn === 'rt' || pn === 'rp') continue;
            const v = n.nodeValue || ''; if (!v) continue;
            const blk = blockOf(n);
            if (last !== null && blk !== last && text && text.charAt(text.length - 1) !== '\n') text += '\n';
            last = blk;
            segs.push({ node: n, at: text.length });
            text += v;
        }
        return { text, segs };
    }
    function pointAt(map, i, isEnd) {
        const sg = map.segs; let lo = 0, hi = sg.length - 1, k = 0;
        while (lo <= hi) { const mid = (lo + hi) >> 1; if (isEnd ? sg[mid].at < i : sg[mid].at <= i) { k = mid; lo = mid + 1; } else hi = mid - 1; }
        const s = sg[k]; return { node: s.node, off: Math.max(0, Math.min(i - s.at, s.node.nodeValue.length)) };
    }
    function indexOfPoint(map, node, off) {
        if (node && node.nodeType === 3) { for (const s of map.segs) if (s.node === node) return s.at + Math.min(off, s.node.nodeValue.length); }
        // 요소가 잡힌 경우: 그 안(또는 뒤)의 첫 글
        let ref = node; try { if (node && node.nodeType === 1 && node.childNodes[off]) ref = node.childNodes[off]; } catch (e) {}
        for (const s of map.segs) {
            try { if (ref === s.node || (ref.contains && ref.contains(s.node)) || (ref.compareDocumentPosition(s.node) & Node.DOCUMENT_POSITION_FOLLOWING)) return s.at; } catch (e) {}
        }
        return 0;
    }
    function rangeOf(doc, map, a, b) { const p = pointAt(map, a, false), q = pointAt(map, b, true); const r = doc.createRange(); r.setStart(p.node, p.off); r.setEnd(q.node, q.off); return r; }

    let toolsTocMap = {};
    function chapterName(href) {
        const f = String(href || '').split('#')[0]; if (!f) return '';
        if (toolsTocMap[f]) return toolsTocMap[f];
        const k = Object.keys(toolsTocMap).find(x => f.endsWith(x) || x.endsWith(f)); return k ? toolsTocMap[k] : '';
    }
    function pctOfCfi(cfi) { try { if (locationsReady) { const v = book.locations.percentageFromCfi(cfi); if (typeof v === 'number' && isFinite(v)) return Math.round(v * 1000) / 10; } } catch (e) {} return -1; }
    const cfiCmp = (() => { const C = (window.ePub && ePub.CFI) ? new ePub.CFI() : null; return (a, b) => { try { return C ? C.compare(a, b) : 0; } catch (e) { return 0; } }; })();
    function onScreen(cfi) {
        try { const l = rendition.currentLocation(); return !!(l && l.start && l.end && cfiCmp(cfi, l.start.cfi) >= 0 && cfiCmp(cfi, l.end.cfi) <= 0); } catch (e) { return false; }
    }
    // 실제로 화면(책 영역) 안에 그 글의 첫머리가 보이는지 — 글이 다시 흐르는 중(글자 크기 바꿈 등)에도 맞게 화면 좌표로 확인
    function cfiVisible(cfi) {
        try {
            const r = rendition.getRange(cfi); if (!r) return false;
            const fr = r.startContainer.ownerDocument.defaultView.frameElement; if (!fr) return false;
            const fb = fr.getBoundingClientRect(), v = viewerEl.getBoundingClientRect();
            const rr = r.getClientRects()[0] || r.getBoundingClientRect();
            const x = fb.left + rr.left + 1;
            return x >= v.left - 2 && x < v.right - 2;
        } catch (e) { return false; }
    }
    // 잠깐 칠하기(검색 결과·읽는 문장) — 노트 형광펜과 같은 자리면 노트를 다시 그림
    function tempMark(cfi, cls, fill) {
        try { rendition.annotations.add('highlight', cfi, {}, null, cls, { fill: fill, 'fill-opacity': currentTheme === 'dark' ? '0.35' : '0.4', 'mix-blend-mode': currentTheme === 'dark' ? 'normal' : 'multiply' }); } catch (e) {}
    }
    function tempUnmark(cfi) {
        try { rendition.annotations.remove(cfi, 'highlight'); } catch (e) {}
        try { if (notes && notes.items().some(it => it.cfi === cfi) && typeof notesRedraw === 'function') notesRedraw(); } catch (e) {}
    }

    function setupTools(tocItems) {
        if (!window.MCTools) return;
        (function walk(arr) { (arr || []).forEach(it => { const f = String(it.href || '').split('#')[0]; if (f && !toolsTocMap[f]) toolsTocMap[f] = String(it.label || '').trim(); walk(it.subitems); }); })(tocItems);
        const toolbar = document.querySelector('.epub-toolbar');

        // ── 본문 검색: 장(章)마다 읽어서 찾음(지금 안 보이는 장은 읽고 나서 다시 비움) ──
        let hitCfi = null, hitT = 0;
        MCTools.search({
            t: TT, button: document.getElementById('btnSearch'), toolbar, bottom: 45,
            run: async (q, stale) => {
                const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/ /g, '\\s+');
                let re; try { re = new RegExp(esc, 'giu'); } catch (e) { re = new RegExp(esc, 'gi'); }
                const hits = [], LIMIT = 500; let capped = false;
                const secs = (book.spine.spineItems || book.spine.items || []).slice();
                for (const sec of secs) {
                    if (stale()) return null;
                    if (!sec) continue;
                    let mine = false;
                    let doc = sec.document;
                    if (!doc) { try { const root = await sec.load(book.load.bind(book)); doc = (root && root.ownerDocument) || sec.document; mine = true; } catch (e) { continue; } } // ★ (2026-11-01 17:05) load 가 준 문서를 바로 씀 — 그 사이 위치 계산(epub.js)이 같은 장을 비우면 sec.document 가 없어져 그 장 결과가 빠졌음
                    if (!doc) continue;
                    try {
                        const map = textMap(doc); re.lastIndex = 0; let m;
                        while ((m = re.exec(map.text))) {
                            if (!m[0]) { re.lastIndex++; continue; }
                            if (hits.length >= LIMIT) { capped = true; break; }
                            const i = m.index, j = i + m[0].length;
                            let cfi = ''; try { cfi = sec.cfiFromRange(rangeOf(doc, map, i, j)); } catch (e) { continue; }
                            const x = (i > 40 ? '…' : '') + map.text.slice(Math.max(0, i - 40), j + 60).replace(/\s+/g, ' ').trim() + (j + 60 < map.text.length ? '…' : '');
                            hits.push({ cfi, x, ch: chapterName(sec.href), p: pctOfCfi(cfi) });
                        }
                    } catch (e) {}
                    if (mine) { try { sec.unload(); } catch (e) {} }
                    if (capped) break;
                    await new Promise(r => setTimeout(r, 0)); // 화면이 멈추지 않게
                }
                return { hits, capped };
            },
            go: (hit) => {
                if (hitCfi) { tempUnmark(hitCfi); hitCfi = null; }
                clearTimeout(hitT);
                Promise.resolve(rendition.display(hit.cfi)).catch(() => {}).then(() => {
                    hitCfi = hit.cfi; tempMark(hit.cfi, 'mct-hit-epub', '#ff922b');
                    hitT = setTimeout(() => { if (hitCfi === hit.cfi) { tempUnmark(hit.cfi); hitCfi = null; } }, 4000);
                });
            }
        });

        // ── 듣기: 지금 쪽 첫 글부터 문장 단위, 읽는 문장이 다음 쪽이면 넘기고, 장이 끝나면 다음 장 ──
        const contentsFor = (idx) => (rendition.getContents() || []).find(c => c && c.sectionIndex === idx) || null;
        const settle = () => new Promise(r => setTimeout(() => requestAnimationFrame(() => requestAnimationFrame(r)), 350));
        function build(contents, fromRange, skipSame) {
            const map = textMap(contents.document);
            const ss = MCTools.sentences(map.text);
            let k = 0;
            if (fromRange) {
                const i0 = indexOfPoint(map, fromRange.startContainer, fromRange.startOffset);
                k = ss.findIndex(x => x[1] > i0); if (k < 0) k = ss.length;
                if (skipSame && k < ss.length && ss[k][0] <= i0) k++;
            }
            return { contents, doc: contents.document, idx: contents.sectionIndex, map, ss, k, lastCfi: null };
        }
        const ttsCtl = MCTools.tts({
            t: TT, button: document.getElementById('btnTts'), note: document.getElementById('ttsNote'),
            onStart: () => { document.getElementById('settingsPanel').classList.remove('show'); stopAutoTurn(); }, // 듣기와 자동 넘김은 함께 쓰지 않음
            bottom: () => { // 휴대폰은 아래 '이전·다음' 단추 위로
                let top = window.innerHeight - 58;
                document.querySelectorAll('.nav-bottom').forEach(bn => { const r = bn.getBoundingClientRect(); if (r.height > 0 && r.width < window.innerWidth / 2 && window.innerWidth < 768) top = Math.min(top, r.top - 8); });
                return Math.round(window.innerHeight - top);
            },
            source: {
                start: async (from) => {
                    const loc = rendition.currentLocation && rendition.currentLocation();
                    if (!loc || !loc.start) return null;
                    // ★ (2026-11-07 fix40) 고른 글부터 듣기(from.cfi) — 그 글이 든 장·문장부터. 못 찾으면 예전처럼 지금 화면 처음부터
                    let fromCfi = (from && typeof from.cfi === 'string') ? from.cfi : '', fromIdx = -1;
                    if (fromCfi) { try { const cf = new ePub.CFI(fromCfi); if (typeof cf.spinePos === 'number') fromIdx = cf.spinePos; } catch (e) { fromCfi = ''; } }
                    const c = (fromCfi && fromIdx >= 0 && contentsFor(fromIdx)) || contentsFor(loc.start.index); if (!c) return null;
                    let r = null;
                    if (fromCfi && c.sectionIndex === fromIdx) { try { r = rendition.getRange(fromCfi); } catch (e) { r = null; } }
                    if (!r && c.sectionIndex === loc.start.index) { try { r = rendition.getRange(loc.start.cfi); } catch (e) {} } // 다른 장 문서의 위치는 쓰지 않음(그 장 처음부터)
                    let L = build(c, r, false);
                    return {
                        next: async function () {
                            for (let guard = 0; guard < 2000; guard++) {
                                if (!L) return null;
                                // 화면이 다시 그려졌으면(크기·글자 크기 바꿈) 마지막 읽은 자리에서 다시 만듦
                                let live = contentsFor(L.idx);
                                if (!live || live.document !== L.doc) {
                                    if (!L.lastCfi) return null;
                                    if (!live) { await Promise.resolve(rendition.display(L.lastCfi)).catch(() => {}); await settle(); live = contentsFor(L.idx); if (!live) return null; }
                                    let rr = null; try { rr = rendition.getRange(L.lastCfi); } catch (e) {}
                                    const last = L.lastCfi; L = build(live, rr, true); L.lastCfi = last;
                                }
                                if (L.k < L.ss.length) {
                                    const [a, b] = L.ss[L.k++];
                                    let cfi = ''; try { cfi = L.contents.cfiFromRange(rangeOf(L.doc, L.map, a, b)); } catch (e) { continue; }
                                    L.lastCfi = cfi;
                                    const text = L.map.text.slice(a, b).replace(/\s+/g, ' ');
                                    return {
                                        text,
                                        show: async () => {
                                            for (let t = 0; t < 2 && !(onScreen(cfi) && cfiVisible(cfi)); t++) { await Promise.resolve(rendition.display(cfi)).catch(() => {}); await settle(); }
                                        },
                                        mark: () => tempMark(cfi, 'mct-tts-epub', '#4dabf7'),
                                        unmark: () => tempUnmark(cfi)
                                    };
                                }
                                // 장 끝 → 다음 장
                                const sec = (() => { try { const s0 = book.spine.get(L.idx); return s0 && s0.next ? s0.next() : null; } catch (e) { return null; } })();
                                if (!sec) return null;
                                await Promise.resolve(rendition.display(sec.href)).catch(() => {});
                                await settle();
                                const nc = contentsFor(sec.index); if (!nc) return null;
                                L = build(nc, null, false);
                            }
                            return null;
                        }
                    };
                }
            }
        });

        MCTools.wake({ t: TT, checkbox: document.getElementById('chkWake'), note: document.getElementById('wakeNote') });
        if (ttsCtl && window.MCNotes && MCNotes.setListen) MCNotes.setListen((pos) => { if (pos && pos.cfi) ttsCtl.startAt({ cfi: pos.cfi }); }); // ★ (2026-11-07 fix40) 글을 고르면 메뉴에 '여기부터 듣기'

        // ── 줄 간격 · 좌우 여백 · 2쪽 ──
        const selLH = document.getElementById('selLineHeight');
        if (selLH) {
            selLH.value = lineHeightPref();
            selLH.addEventListener('change', () => {
                writePref('epub_lh', selLH.value);
                keepPlaceAfterFontChange(); // 보던 자리 유지 + 형광펜 다시 그림
                const css = typoCss();
                (rendition.getContents() || []).forEach(c => { try { const st = c.document.getElementById('mc-user-typo'); if (st) st.textContent = css; } catch (e) {} });
            });
        }
        const mBtns = Array.prototype.slice.call(document.querySelectorAll('.mc-margin-btn'));
        const syncM = () => mBtns.forEach(b => b.classList.toggle('active', +b.dataset.margin === marginPref()));
        syncM();
        mBtns.forEach(b => b.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); b.click(); } }));
        mBtns.forEach(b => b.addEventListener('click', () => {
            writePref('epub_margin', b.dataset.margin); syncM();
            keepPlaceAfterFontChange();
            applyMargin();
            try { rendition.resize(); } catch (e) {}
        }));
        window.addEventListener('resize', () => { if (marginPref()) applyMargin(); }, true); // 화면 크기에 맞춰 다시(epub.js 크기 맞춤보다 먼저)
        // ★ (2026-11-04 18:04) 2쪽 펼침 3단계(예전 체크 칸 자리) — 바꾸면 보던 자리로 다시 그림(예전과 같은 방식)
        const spBtns = Array.prototype.slice.call(document.querySelectorAll('.mc-spread-btn'));
        const syncSp = () => spBtns.forEach(b => { const on = b.dataset.spread === spreadPref(); b.classList.toggle('active', on); b.setAttribute('aria-checked', on ? 'true' : 'false'); });
        syncSp();
        let spShown = spreadMode()[0];
        function redrawSpread() {
            const sm = spreadMode();
            spShown = sm[0];
            const at = guardAnchor || (rendition.location && rendition.location.start && rendition.location.start.cfi) || null;
            try { rendition.spread(sm[0], sm[1]); } catch (e) {}
            // epub.js 는 이미 그린 장의 칸 수를 바꾸지 않음 → 지우고 보던 자리로 다시 그림(형광펜은 epub.js 가 다시 붙임)
            try { rendition.clear(); } catch (e) {}
            Promise.resolve(rendition.display(at || undefined)).catch(() => {});
        }
        spBtns.forEach(b => b.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); b.click(); } }));
        spBtns.forEach(b => b.addEventListener('click', () => { writePref('epub_spread', b.dataset.spread); syncSp(); redrawSpread(); }));
        // '가로 화면'일 때 화면을 돌려 가로↔세로가 바뀌면 다시 그림(폭이 바뀔 때만 — 화면 키보드로 높이만 바뀌는 것 무시)
        let spW = window.innerWidth, spT = 0;
        window.addEventListener('resize', () => {
            if (window.innerWidth === spW) return;
            spW = window.innerWidth;
            clearTimeout(spT);
            spT = setTimeout(() => { if (spreadPref() === 'land' && spreadMode()[0] !== spShown) redrawSpread(); }, 450);
        });

        // ── ★ (2026-11-01 11:22) 글꼴 · 문단 간격 · 양쪽 정렬 (줄 간격과 같은 방식: 보던 자리 유지 + 화면에 있는 장에 바로 적용) ──
        function applyTypo() {
            keepPlaceAfterFontChange();
            const css = typoCss();
            (rendition.getContents() || []).forEach(c => { try { const st = c.document.getElementById('mc-user-typo'); if (st) st.textContent = css; markJustify(c.document); } catch (e) {} });
        }
        const selFont = document.getElementById('selFont'), selPara = document.getElementById('selPara'), chkJ = document.getElementById('chkJustify');
        if (selFont) { selFont.value = fontPref(); selFont.addEventListener('change', () => { writePref('epub_font', selFont.value); applyTypo(); }); }
        if (selPara) { selPara.value = paraPref(); selPara.addEventListener('change', () => { writePref('epub_para', selPara.value); applyTypo(); }); }
        if (chkJ) { chkJ.checked = justifyPref(); chkJ.addEventListener('change', () => { writePref('epub_justify', chkJ.checked ? '1' : '0'); applyTypo(); }); }

        // ── 자동 넘김(이번에만 — 다시 열면 꺼짐) · 손으로 넘기면 그때부터 다시 셈 · 책 끝이면 멈춤 ──
        const selAT = document.getElementById('selAutoTurn');
        if (selAT) selAT.addEventListener('change', () => {
            atSec = parseInt(selAT.value, 10) || 0;
            if (atSec && ttsCtl && ttsCtl.isOn()) ttsCtl.stop(); // ★ (2026-11-01 16:57) 듣는 중이면 듣기를 멈춤(둘이 쪽을 서로 당기지 않게)
            atReset(); if (atSec) document.getElementById('settingsPanel').classList.remove('show');
        });
        rendition.on('relocated', () => { if (atSec) atReset(); });

        // ── 이 장 남은 쪽(하단 바) ──
        const chLeft = document.getElementById('chLeft');
        const updChLeft = (loc) => {
            if (!chLeft) return;
            try {
                loc = loc || rendition.currentLocation();
                const st = loc && loc.start && loc.start.displayed, en = loc && loc.end && loc.end.displayed;
                if (!st || !(st.total > 1)) { chLeft.textContent = ''; return; }
                const left = Math.max(0, st.total - Math.max(st.page, en && en.page || 0));
                chLeft.textContent = left > 0 ? String(TT.ch_left).replace('%d', left) : TT.ch_last;
            } catch (e) { chLeft.textContent = ''; }
        };
        rendition.on('relocated', (l) => updChLeft(l));
        updChLeft();

        // ── 화면 어둡게 · 읽은 시간(이 기기) ──
        MCTools.dim({ slider: document.getElementById('dimSlider'), value: document.getElementById('dimValue') });
        MCTools.readTime({ key: 'epub:' + BIDX + ':' + FILE_KEY, t: TT, el: document.getElementById('readTime') });
        rendition.on('relocated', () => MCTools.ping());
        rendition.on('click', () => MCTools.ping());
        rendition.on('keydown', () => MCTools.ping());
        rendition.on('keydown', (e) => { if (e.shiftKey || e.ctrlKey || e.altKey || e.metaKey) return; /* ★ (2026-11-02 14:32) Shift+←→(본문 글 고르기 넓히기)는 쪽을 넘기지 않음 */ if (typeof window.__mcEpubKeyNav === 'function') window.__mcEpubKeyNav(e); }); // ★ (2026-11-02 11:36) 본문에 초점이 있어도 ←→
    }
    let atSec = 0, atT = 0;
    function atReset() {
        clearInterval(atT); atT = 0;
        if (!atSec) return;
        atT = setInterval(() => {
            if (document.visibilityState !== 'visible') return; // ★ (2026-11-01 16:57) 다른 탭·앱에 가 있는 동안은 넘기지 않음(돌아오면 엉뚱하게 앞서 있고 그 자리가 저장됐음)
            try { if (rendition.location && rendition.location.atEnd) { stopAutoTurn(); return; } } catch (e) {}
            try { rendition.next(); } catch (e) {}
        }, atSec * 1000);
    }
    function stopAutoTurn() {
        atSec = 0; clearInterval(atT); atT = 0;
        const s = document.getElementById('selAutoTurn'); if (s) s.value = '0';
    }

    // 진행 저장
    async function saveProgress(cfi, percent) {
        try {
            await fetch('epub_viewer.php?action=save_progress&bidx=<?php echo $current_bidx; ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `file=${encodeURIComponent(FILE_KEY)}&cfi=${encodeURIComponent(cfi)}&percent=${percent}`
            });
        } catch (err) {
            console.warn('진행 저장 실패:', err);
        }
    }
    
    // 진행 로드
    async function loadProgress() {
        try {
            const res = await fetch(`epub_viewer.php?action=load_progress&bidx=<?php echo $current_bidx; ?>&file=${encodeURIComponent(FILE_KEY)}`);
            const data = await res.json();
            return data.progress;
        } catch (err) {
            return null;
        }
    }
    
    // 진행 슬라이더
    progressSlider.addEventListener('change', (e) => {
        if (!locationsReady) return;  // 위치 정보 준비 안됨
        const percent = e.target.value / 100;
        const cfi = book.locations.cfiFromPercentage(percent);
        rendition.display(cfi);
    });
    
    // 목차 토글
    document.getElementById('btnToc').addEventListener('click', () => {
        document.getElementById('tocSidebar').classList.toggle('show');
    });
    
    // 설정 패널
    document.getElementById('btnSettings').addEventListener('click', () => {
        document.getElementById('settingsPanel').classList.toggle('show');
    });

    // ★ (2026-11-07 fix40) 머리줄 창은 한 번에 하나만 — 창이 열린 채 다른 단추(목차·검색·노트·설정)를 누르면 열린 창을 닫고 새 창만(예전엔 겹쳐 열림, 펜닐 제보)
    //   단추 자기 동작(열기/닫기)보다 먼저 돌도록 문서 단계(capture)에서 — 같은 단추를 다시 누르면 예전처럼 그 창만 닫힘
    document.addEventListener('click', (e) => {
        const b = e.target && e.target.closest && e.target.closest('#btnToc,#btnSearch,#btnNotes,#btnSettings');
        if (!b) return;
        const id = b.id, sp = document.getElementById('settingsPanel'), toc = document.getElementById('tocSidebar');
        if (id !== 'btnSettings' && sp) sp.classList.remove('show');
        if (id !== 'btnNotes') { try { window.MCNotes && MCNotes.closePanels && MCNotes.closePanels(); } catch (x) {} }
        if (toc && id !== 'btnToc') toc.classList.remove('show');
        // 검색·목차(옆 창 공용)는 서로 알아서 하나만 — 노트·설정·EPUB 목차를 열 때만 닫음
        if (id === 'btnNotes' || id === 'btnSettings' || (toc && id === 'btnToc')) { try { window.MCTools && MCTools.closePanels && MCTools.closePanels(); } catch (x) {} }
    }, true);
    
    // 테마 변경
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const theme = btn.dataset.theme;
            
            document.querySelectorAll('.theme-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            document.body.className = 'theme-' + theme;
            document.documentElement.classList.toggle('mc-fdk-dark', theme === 'dark'); // ★ (2026-11-08 fix82) 화면 전환 바탕도 테마에 맞춤
            rendition.themes.select(theme);
            currentTheme = theme;
            
            // ✅ iframe 내부 스타일 직접 변경 (즉시 적용)
            rendition.getContents().forEach(contents => {
                const body = contents.document.body;
                if (body) {
                    body.style.background = themes[theme].body.background;
                    body.style.color = themes[theme].body.color;
                    body.style.fontFamily = themes[theme].body['font-family'];
                }
            });
            
            document.cookie = `epub_theme=${theme}; path=/; max-age=31536000`;
            if (typeof notesRedraw === 'function') notesRedraw(); // ★ (2026-10-31) 어두운 화면은 형광펜을 다른 방식으로 그림
        });
    });
    
    // 글꼴 크기
    document.getElementById('fontSizeSlider').addEventListener('input', (e) => {
        const size = e.target.value;
        document.getElementById('fontSizeValue').textContent = size;
        keepPlaceAfterFontChange(); // ★ (2026-10-31) 글이 다시 흘러도 보던 자리 유지 + 형광펜 다시 그림(바꾸기 전 자리를 먼저 기억)
        rendition.themes.fontSize(size + '%');
        currentFontSize = size;
        document.cookie = `epub_fontsize=${size}; path=/; max-age=31536000`;
    });
    
    // 위치 변경 핸들러
    let skipFirstRelocated = true;
    
    function handleRelocated(location) {
        // ★ (2026-10-31) 진행률 표시는 첫 위치부터(저장만 건너뜀), 책 마지막 쪽은 100%
        const percent = percentOfLocation(location);
        if (percent >= 0) {
            progressSlider.value = percent;
            progressText.textContent = percent + '%';
        }
        // 첫 페이지 로드 시에는 저장하지 않음 (기존 진행률 유지)
        if (skipFirstRelocated) {
            skipFirstRelocated = false;
            return;
        }
        
        // 위치 정보가 준비된 경우에만 퍼센트 저장
        if (percent >= 0) {
            if (location.start.cfi) {
                saveProgress(location.start.cfi, percent);
            }
        } else if (location.start && location.start.cfi) {
            // 위치 정보 없으면 CFI만 저장 (퍼센트는 -1로 표시해서 기존값 유지)
            saveProgress(location.start.cfi, -1);
        }
    }
    
    // 진행률 즉시 저장 (비콘)
    function saveProgressBeacon() {
        if (rendition && rendition.location && rendition.location.start) {
            const loc = rendition.location;
            const cfi = loc.start.cfi;
            const percent = percentOfLocation(loc); // ★ (2026-10-31) 화면 표시와 같은 계산(마지막 쪽 100%)
            const data = new FormData();
            data.append('file', FILE_KEY);
            data.append('cfi', cfi);
            data.append('percent', percent);
            navigator.sendBeacon(`epub_viewer.php?action=save_progress&bidx=<?php echo $current_bidx; ?>`, data);
        }
    }
    
    // 페이지 떠날 때 진행률 저장 (여러 이벤트로 커버)
    window.addEventListener('beforeunload', saveProgressBeacon);
    window.addEventListener('pagehide', saveProgressBeacon);  // iOS Safari
    
    // 탭 숨김 시 저장 (모바일 탭 전환)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            saveProgressBeacon();
        }
    });
    
    // <?php echo __h("txt_reverse_nav"); ?>
    // isReversed is declared at top of IIFE
    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const navZoneLeft = document.getElementById('navZoneLeft');
    const navZoneRight = document.getElementById('navZoneRight');
    
    // 텍스트 및 아이콘 업데이트 함수
    function updateNavButtons() {
        if (isReversed) {
            // 반전: 텍스트만 변경 (화살표 위치 고정)
            btnPrev.querySelector('.nav-text').textContent = _vi18n.next;
            btnNext.querySelector('.nav-text').textContent = _vi18n.prev;
            document.body.classList.add('reverse-nav');
        } else {
            // 기본
            btnPrev.querySelector('.nav-text').textContent = _vi18n.prev;
            btnNext.querySelector('.nav-text').textContent = _vi18n.next;
            document.body.classList.remove('reverse-nav');
        }
    }
    
    // 초기 반전 상태 적용
    updateNavButtons();
    
    document.getElementById('chkReverseNav').addEventListener('change', (e) => {
        isReversed = e.target.checked;
        updateNavButtons();
        document.cookie = `epub_reverse_nav=${isReversed ? '1' : '0'}; path=/; max-age=31536000`;
    });
    
    // 네비게이션 동작 함수 (좌/우 클릭에 대한 동작)
    function goLeft() {
        if (isReversed) {
            rendition.next();  // 반전: 왼쪽 클릭 = 다음
        } else {
            rendition.prev();  // 기본: 왼쪽 클릭 = 이전
        }
    }
    
    function goRight() {
        if (isReversed) {
            rendition.prev();  // 반전: 오른쪽 클릭 = 이전
        } else {
            rendition.next();  // 기본: 오른쪽 클릭 = 다음
        }
    }
    
    // 하단 버튼 이벤트 (반전 적용)
    btnPrev.addEventListener('click', goLeft);
    btnNext.addEventListener('click', goRight);
    
    // 세로 클릭 영역 이벤트 (반전 적용)
    navZoneLeft.addEventListener('click', goLeft);
    navZoneRight.addEventListener('click', goRight);
    
    // 키보드 네비게이션 (반전 적용)
    // ★ (2026-11-02 11:36) 입력칸·진행 슬라이더에서는 쪽을 넘기지 않음(슬라이더가 1% 옮기면서 쪽도 넘겨 엉뚱한 곳으로 튀었음)
    //   본문(iframe)을 눌러 초점이 그 안에 있어도 ←→ 로 넘기게 rendition 쪽에도 같은 처리를 붙임(아래 initBook)
    const mcEpubKeyNav = (e) => {
        const t = e && e.target;
        if (t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName || '') || t.isContentEditable)) return;
        if (e && (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey)) return; // ★ (2026-11-02 19:21) Alt+←(브라우저 뒤로) 등 보조키는 쪽 넘김 아님 — 나가면서 저장 위치가 한 쪽 바뀌었음
        if (e.key === 'ArrowLeft') {
            goLeft();
        }
        if (e.key === 'ArrowRight') {
            goRight();
        }
    };
    window.__mcEpubKeyNav = mcEpubKeyNav;
    document.addEventListener('keydown', mcEpubKeyNav);
    
    // 터치 스와이프 (모바일, 반전 적용)
    let touchStartX = 0;
    viewerEl.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    viewerEl.addEventListener('touchend', (e) => {
        const touchEndX = e.changedTouches[0].screenX;
        const diff = touchStartX - touchEndX;
        
        if (Math.abs(diff) > 50) {
            if (diff > 0) {
                // 왼쪽으로 스와이프 = 오른쪽 클릭과 동일
                goRight();
            } else {
                // 오른쪽으로 스와이프 = 왼쪽 클릭과 동일
                goLeft();
            }
        }
    });
    
    // 세로 클릭 영역 터치 이벤트 (스와이프 방지, 탭만 인식)
    navZoneLeft.addEventListener('touchend', (e) => {
        e.preventDefault();
        goLeft();
    });
    
    navZoneRight.addEventListener('touchend', (e) => {
        e.preventDefault();
        goRight();
    });
    
    // 설정 패널 내부 터치/스크롤 이벤트 전파 차단
    const settingsPanel = document.getElementById('settingsPanel');
    // ★ (2026-11-01 17:05) 설정 창 안(슬라이더·선택 상자·단추)에서 누른 ←→·Space 는 그 칸만 움직이게(전에는 쪽도 같이 넘어갔음)
    settingsPanel.addEventListener('keydown', (e) => { if (/^(Arrow|Page|Home|End)/.test(e.key) || e.key === ' ') e.stopPropagation(); });
    settingsPanel.addEventListener('touchmove', (e) => {
        e.stopPropagation();
    }, { passive: true });
    
    settingsPanel.addEventListener('wheel', (e) => {
        e.stopPropagation();
    }, { passive: true });
    
    // 목차 사이드바 내부 터치/스크롤 이벤트 전파 차단
    const tocSidebar = document.getElementById('tocSidebar');
    tocSidebar.addEventListener('touchmove', (e) => {
        e.stopPropagation();
    }, { passive: true });
    
    tocSidebar.addEventListener('wheel', (e) => {
        e.stopPropagation();
    }, { passive: true });
    
    // 목차 사이드바 클릭/터치가 뷰어로 전파되지 않도록
    tocSidebar.addEventListener('click', (e) => {
        e.stopPropagation();
    });
    
    tocSidebar.addEventListener('touchstart', (e) => {
        e.stopPropagation();
    }, { passive: true });
    
    tocSidebar.addEventListener('touchend', (e) => {
        e.stopPropagation();
    }, { passive: true });
    
    // 초기화
    initBook();
})();
</script>

<!-- ✅ 페이지 전환 시 부드러운 효과 -->
<script>
(function(){
    document.addEventListener('click', function(e) {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return; // ★ (2026-11-04 00:29) 새 탭(Ctrl·⌘·Shift·가운데)·이미 막은 클릭은 그대로(다른 화면과 같게)
        var link = e.target.closest('a[href]');
        if (!link || link.hasAttribute('download')) return;
        var href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank') return;
        e.preventDefault();
        document.documentElement.classList.add('leaving');
        setTimeout(function() { location.href = href; }, 100);
    });
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            document.documentElement.classList.remove('leaving');
            document.documentElement.classList.add('ready');
        }
    });
})();
</script>

<!-- ✅ 자동 로그아웃 타이머 -->
<?php 
// ✅ 자동 로그아웃 설정 로드
if (!isset($auto_logout_settings)) {
    $auto_logout_settings = ['enabled' => true, 'timeout' => 600];
}
$timeout = (int)($auto_logout_settings['timeout'] ?? 600);
$remaining = isset($_SESSION['last_action']) ? max(0, $timeout - (time() - $_SESSION['last_action'])) : $timeout;

// ✅ 현재 페이지가 적용 대상인지 확인
$_current_page = basename($_SERVER['SCRIPT_FILENAME']);
$_auto_logout_pages = $auto_logout_settings['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
$_is_target = in_array($_current_page, $_auto_logout_pages);

// ✅ "로그인 유지"로 로그인한 경우 자동 로그아웃 무시
$_is_remember_me = isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true;

// ✅ 전역 $base_path 사용 (bootstrap.php에서 설정됨)
global $base_path;
if (($auto_logout_settings['enabled'] ?? true) && $_is_target && !$_is_remember_me): 
?>
<script>
window.SESSION_TIMEOUT = <?php echo $timeout; ?>;
window.SESSION_REMAINING = <?php echo $remaining; ?>;
</script>
<?php echo mc_al_i18n_script(); ?>
<script src="<?php echo $base_path; ?>/js/auto-logout.js?v=<?php echo @filemtime(__DIR__ . '/js/auto-logout.js') ?: '1'; ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/privacy_shield.php'; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
<?php if (function_exists('mc_perf_script')) echo mc_perf_script('epub'); // ★ (2026-11-03 14:10) 속도 기록(src/perf) ?>
<script>
// ★ (2026-11-04 18:05) 설정 창 '전체화면' — 지원하는 브라우저만 보임, 누를 때마다 켜기/끄기(Esc·뒤로 가기로 나가도 글자 맞춤)
(function () {
    var row = document.getElementById('fsRow'), btn = document.getElementById('btnFullscreen'), de = document.documentElement;
    var req = de.requestFullscreen || de.webkitRequestFullscreen, ex = document.exitFullscreen || document.webkitExitFullscreen;
    if (!row || !btn || !req || !ex || !(document.fullscreenEnabled || document.webkitFullscreenEnabled)) return;
    var ON = <?php echo json_encode(__('bt_fs_on'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, OFF = <?php echo json_encode(__('bt_fs_off'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    row.hidden = false;
    function isFs() { return !!(document.fullscreenElement || document.webkitFullscreenElement); }
    function sync() { var f = isFs(); btn.textContent = f ? OFF : ON; btn.setAttribute('aria-pressed', f ? 'true' : 'false'); }
    btn.addEventListener('click', function (e) {
        e.preventDefault(); e.stopPropagation();
        try { var r = isFs() ? ex.call(document) : req.call(de); if (r && r.catch) r.catch(function () {}); } catch (x) {}
    });
    document.addEventListener('fullscreenchange', sync); document.addEventListener('webkitfullscreenchange', sync);
    sync();
})();
</script>
</body>
</html>