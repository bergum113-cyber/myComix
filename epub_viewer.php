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

// ✅ 다중 폴더 지원: URL 파라미터로 폴더 선택 (API 전에 필요)
$bidx = init_bidx();  // ✅ 항상 bidx 포함

// ============================================================
// EPUB locations 캐시 저장 API (파일 옆에 .locations.json)
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'save_locations' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $getfile = decode_file_param($_POST['file'] ?? '');
    $locations = $_POST['locations'] ?? '';
    
    if (empty($getfile) || empty($locations)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    // 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false || !file_exists($base_file)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file path']);
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
    header('Cache-Control: public, max-age=86400');
    
    // Range 요청 처리
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            $start = $matches[1] !== '' ? intval($matches[1]) : 0;
            $end = $matches[2] !== '' ? intval($matches[2]) : $filesize - 1;
            
            if ($start > $end || $start >= $filesize) {
                http_response_code(416);
                header("Content-Range: bytes */$filesize");
                exit;
            }
            
            $length = $end - $start + 1;
            
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$filesize");
            header("Content-Length: $length");
            
            $fp = fopen($base_file, 'rb');
            fseek($fp, $start);
            echo fread($fp, $length);
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
    // 키 정규화: 앞의 슬래시 제거
    $getfile = ltrim($getfile, '/');
    
    $cfi = $_POST['cfi'] ?? '';
    $percent = (float)($_POST['percent'] ?? 0);
    
    // ✅ bootstrap.php의 함수 사용 (파일 잠금 적용)
    $progress_file = get_epub_progress_file();
    $progress = load_json_with_lock($progress_file);
    
    // ✅ percent가 -1이면 기존 값 유지 (locations 계산 전에는 -1로 전송됨)
    $existing_percent = $progress[$getfile]['percent'] ?? 0;
    if ($percent < 0) {
        $percent = $existing_percent;
    }
    
    $progress[$getfile] = [
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
    $getfile = ltrim($getfile, '/');
    
    // ✅ bootstrap.php의 함수 사용 (파일 잠금 적용)
    $progress_file = get_epub_progress_file();
    $progress = load_json_with_lock($progress_file);
    
    $saved = $progress[$getfile] ?? null;
    
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
$fontSize = $_COOKIE['epub_fontsize'] ?? ($epub_settings['default_font_size'] ?? 100);

// 커스텀 폰트 설정
$epub_font_name = $epub_settings['font_name'] ?? '';
$epub_font_url = $epub_settings['font_url'] ?? '';
$epub_font_local = $epub_settings['font_local'] ?? '';
$epub_font_family = !empty($epub_font_name) ? "'" . h($epub_font_name) . "', 'Nanum Gothic', 'Malgun Gothic', sans-serif" : "'Nanum Gothic', 'Malgun Gothic', sans-serif";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
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
    <link rel="shortcut icon" href="./favicon.ico">
    
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>
    
    <style>
        <?php if (!empty($epub_font_local) && empty($epub_font_url)): ?>
        @font-face {
            font-family: '<?php echo h($epub_font_name ?: 'CustomEpubFont'); ?>';
            src: url('<?php echo h($epub_font_local); ?>');
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
    <a href="index.php?dir=<?php echo u($link_dir) . $bidx_param; ?>" class="btn btn-outline-secondary btn-sm">← <?php echo __h("back"); ?></a>
    <button id="btnToc" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('epub_toc'); ?>">📑</button>
    <span class="title"><?php echo h($title); ?></span>
    <?php render_lang_badge('sm-epub'); ?>
    <button id="btnSettings" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('common_settings'); ?>">⚙️</button>
</div>

<!-- 목차 사이드바 -->
<div class="toc-sidebar" id="tocSidebar">
    <h5>📚 <?php echo __h("epub_toc"); ?></h5>
    <ul class="toc-list" id="tocList"></ul>
</div>

<!-- 설정 패널 -->
<div class="settings-panel" id="settingsPanel">
    <label><?php echo __h("epub_theme"); ?></label>
    <div class="theme-buttons">
        <div class="theme-btn light <?php echo $theme === 'light' ? 'active' : ''; ?>" data-theme="light"><?php echo __h("epub_theme_light"); ?></div>
        <div class="theme-btn sepia <?php echo $theme === 'sepia' ? 'active' : ''; ?>" data-theme="sepia"><?php echo __h("epub_theme_sepia"); ?></div>
        <div class="theme-btn dark <?php echo $theme === 'dark' ? 'active' : ''; ?>" data-theme="dark"><?php echo __h("epub_theme_dark"); ?></div>
    </div>
    
    <label><?php echo __h("txt_font_size"); ?> <span id="fontSizeValue"><?php echo $fontSize; ?></span>%</label>
    <input type="range" id="fontSizeSlider" min="70" max="150" value="<?php echo $fontSize; ?>">
    
    <label style="margin-top:15px;">
        <input type="checkbox" id="chkReverseNav" <?php echo ($_COOKIE['epub_reverse_nav'] ?? '') === '1' ? 'checked' : ''; ?>>
        <?php echo __h("txt_reverse_nav"); ?>
    </label>
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
            
            rendition = book.renderTo(viewerEl, {
                width: '100%',
                height: '100%',
                spread: 'none',
                allowScriptedContent: true
            });
            
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
                await rendition.display(savedProgress.cfi);
            } else {
                await rendition.display();
            }
            
            // 목차 로드
            const toc = await book.loaded.navigation;
            renderToc(toc.toc);
            
            // ✅ 콘텐츠 먼저 표시! (로딩 완료)
            loadingEl.classList.add('hidden');
            
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
            loadingEl.innerHTML = _vi18n.epub_render_fail + ' ' + err.message;
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
    
    // 테마 변경
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const theme = btn.dataset.theme;
            
            document.querySelectorAll('.theme-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            document.body.className = 'theme-' + theme;
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
        });
    });
    
    // 글꼴 크기
    document.getElementById('fontSizeSlider').addEventListener('input', (e) => {
        const size = e.target.value;
        document.getElementById('fontSizeValue').textContent = size;
        rendition.themes.fontSize(size + '%');
        currentFontSize = size;
        document.cookie = `epub_fontsize=${size}; path=/; max-age=31536000`;
    });
    
    // 위치 변경 핸들러
    let skipFirstRelocated = true;
    
    function handleRelocated(location) {
        // 첫 페이지 로드 시에는 저장하지 않음 (기존 진행률 유지)
        if (skipFirstRelocated) {
            skipFirstRelocated = false;
            return;
        }
        
        // 위치 정보가 준비된 경우에만 퍼센트 표시
        if (locationsReady) {
            const percent = Math.round((location.start.percentage || 0) * 100);
            progressSlider.value = percent;
            progressText.textContent = percent + '%';
            
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
            let percent = -1;
            if (locationsReady && typeof loc.start.percentage === 'number') {
                percent = Math.round(loc.start.percentage * 100);
            }
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
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
            goLeft();
        }
        if (e.key === 'ArrowRight') {
            goRight();
        }
    });
    
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
        var link = e.target.closest('a[href]');
        if (!link) return;
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
<script src="<?php echo $base_path; ?>/js/auto-logout.js?v=<?php echo @filemtime(__DIR__ . '/js/auto-logout.js') ?: '1'; ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/privacy_shield.php'; ?>
</body>
</html>