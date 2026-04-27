<?php
/**
 * myComix Office 문서 뷰어 (개선판)
 * 
 * @version 2.0
 * @date 2026-01-12
 * 
 * 지원 형식:
 * - DOC/DOCX (Microsoft Word)
 * - XLS/XLSX (Microsoft Excel)
 * - PPT/PPTX (Microsoft PowerPoint)
 * 
 * 사용 라이브러리:
 * - DOCX: docx-preview (mammoth.js 대비 서식 지원 대폭 향상)
 * - XLSX: SheetJS (xlsx.full.min.js)
 * - PPTX: PPTXjs (도형, 차트, 테마, 이미지 지원)
 * - DOC/XLS/PPT: 레거시 형식 안내
 * 
 * 개선사항 (v2.0):
 * - DOCX: 폰트 크기, 색상, 정렬, 테이블, 이미지 완벽 지원
 * - PPTX: 슬라이드 배경, 도형, 차트, SmartArt 지원
 */

require_once __DIR__ . "/bootstrap.php";
handle_timeout_popup();

$bidx = init_bidx();
$_branding = load_branding();

$office_settings = $office_viewer_settings ?? [
    'max_file_size' => 50 * 1024 * 1024,
    'default_zoom' => 100,
];

// ============================================================
// 파일 스트리밍 API
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'stream') {
    
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        http_response_code(403);
        echo __('err_invalid_path');
        exit;
    }
    
    // Office 파일 확인
    if (!file_exists($base_file) || !preg_match('/\.(docx?|xlsx?|pptx?)$/i', $base_file)) {
        http_response_code(404);
        echo __('err_file_not_found');
        exit;
    }
    
    $filesize = filesize($base_file);
    
    if ($filesize > $office_settings['max_file_size']) {
        http_response_code(413);
        echo __('err_file_too_large');
        exit;
    }
    
    // MIME 타입 결정
    $ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
    $mime_types = [
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    $mime = $mime_types[$ext] ?? 'application/octet-stream';
    
    header('Content-Type: ' . $mime);
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
// 메인 페이지
// ============================================================

$getfile = decode_file_param($_GET['file'] ?? '');
if (!$getfile) {
    header("Location: ./");
    exit;
}

$base_file = validate_file_path($getfile, $base_dir);
if ($base_file === false) {
    echo __('err_invalid_path');
    exit;
}

// Office 파일 확인
if (!file_exists($base_file) || !preg_match('/\.(docx?|xlsx?|pptx?)$/i', $base_file)) {
    echo __('office_file_not_found');
    exit;
}

$filename = basename($base_file);
$ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
$title = preg_replace('/\.(docx?|xlsx?|pptx?)$/i', '', $filename);
$filesize = filesize($base_file);

if ($filesize > $office_settings['max_file_size']) {
    echo __('err_file_too_large_max', round($office_settings['max_file_size'] / 1024 / 1024));
    exit;
}

// 파일 타입 정보
$office_svgs = [
    'doc' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#2b579a"/><path d="M2.5 4h2l1.5 6 2-6h2l2 6 1.5-6h2l-2.5 8h-2L9 6 7 12H5L2.5 4z" fill="#fff"/></svg>',
    'docx' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#2b579a"/><path d="M2.5 4h2l1.5 6 2-6h2l2 6 1.5-6h2l-2.5 8h-2L9 6 7 12H5L2.5 4z" fill="#fff"/></svg>',
    'xls' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#217346"/><path d="M4 4l3 4-3 4h2.5l2-2.7 2 2.7H13l-3-4 3-4h-2.5l-2 2.7-2-2.7H4z" fill="#fff"/></svg>',
    'xlsx' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#217346"/><path d="M4 4l3 4-3 4h2.5l2-2.7 2 2.7H13l-3-4 3-4h-2.5l-2 2.7-2-2.7H4z" fill="#fff"/></svg>',
    'ppt' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#d24726"/><path d="M5 4h4a3 3 0 010 6H7v2H5V4zm2 4h1.5a1 1 0 000-2H7v2z" fill="#fff"/></svg>',
    'pptx' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#d24726"/><path d="M5 4h4a3 3 0 010 6H7v2H5V4zm2 4h1.5a1 1 0 000-2H7v2z" fill="#fff"/></svg>',
];
$file_types = [
    'doc' => ['name' => __('office_word_legacy'), 'icon' => $office_svgs['doc'], 'color' => '#2b579a'],
    'docx' => ['name' => 'Word', 'icon' => $office_svgs['docx'], 'color' => '#2b579a'],
    'xls' => ['name' => __('office_excel_legacy'), 'icon' => $office_svgs['xls'], 'color' => '#217346'],
    'xlsx' => ['name' => 'Excel', 'icon' => $office_svgs['xlsx'], 'color' => '#217346'],
    'ppt' => ['name' => __('office_pptx_legacy'), 'icon' => $office_svgs['ppt'], 'color' => '#d24726'],
    'pptx' => ['name' => 'PowerPoint', 'icon' => $office_svgs['pptx'], 'color' => '#d24726'],
];
$file_info = $file_types[$ext] ?? ['name' => 'Office', 'icon' => $office_svgs['docx'], 'color' => '#666'];
$is_legacy = in_array($ext, ['doc', 'xls', 'ppt']);

$link_dir = dirname($getfile);
if ($link_dir === '.' || $link_dir === '/') {
    $link_dir = '';
}

$darkmode = $_COOKIE['darkmode'] ?? 'light';
$file_url = "office_viewer.php?action=stream&file=" . rawurlencode($getfile) . "&bidx=" . $bidx;

// 뒤로가기 URL
$back_url = "index.php?dir=" . rawurlencode($link_dir) . "&bidx=" . $bidx;
?>
<!DOCTYPE html>
<html lang="ko" data-theme="<?php echo h($darkmode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo h($title); ?> - <?php echo h($file_info['name']); ?> <?php echo __h("viewer_label"); ?></title>
    
    <style>
        html{opacity:0;transition:opacity .15s ease-in}
        html.ready{opacity:1}
        html.leaving{opacity:0;transition:opacity .1s ease-out}
    </style>
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="shortcut icon" href="./favicon.ico">
    
    <?php if ($ext === 'pptx'): ?>
    <!-- PPTX 슬라이드 스타일 -->
    <style>
        .slide-content {
            max-width: 900px;
            margin: 30px auto;
            padding: 40px;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            min-height: 400px;
            aspect-ratio: 16/9;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .slide-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 20px;
            color: <?php echo $file_info['color']; ?>;
        }
        .slide-body {
            font-size: 18px;
            line-height: 1.8;
        }
        .slide-body p { margin-bottom: 0.5em; }
    </style>
    <?php endif; ?>
    
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --header-bg: #f8f9fa;
            --border-color: #dee2e6;
            --btn-bg: #e9ecef;
            --btn-hover: #dee2e6;
            --viewer-bg: #f5f5f5;
            --card-bg: #ffffff;
            --table-border: #dee2e6;
            --table-header-bg: #f1f3f4;
        }
        
        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --text-color: #e0e0e0;
            --header-bg: #2d2d2d;
            --border-color: #404040;
            --btn-bg: #3d3d3d;
            --btn-hover: #4d4d4d;
            --viewer-bg: #252525;
            --card-bg: #2d2d2d;
            --table-border: #404040;
            --table-header-bg: #363636;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Malgun Gothic", sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            min-height: 50px;
            flex-shrink: 0;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }
        
        .back-btn, .toolbar-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: var(--btn-bg);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
            flex-shrink: 0;
        }
        
        .back-btn:hover, .toolbar-btn:hover { background: var(--btn-hover); }
        
        .filename {
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .file-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            background: <?php echo $file_info['color']; ?>;
            flex-shrink: 0;
        }
        
        .toolbar {
            display: flex;
            gap: 8px;
        }
        
        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--btn-bg);
            border-radius: 8px;
            padding: 4px;
        }
        
        .zoom-controls button {
            width: 28px;
            height: 28px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 4px;
            color: var(--text-color);
            font-size: 16px;
        }
        
        .zoom-controls button:hover { background: var(--btn-hover); }
        .zoom-controls span { font-size: 12px; min-width: 40px; text-align: center; }
        
        .viewer-container {
            flex: 1;
            overflow: auto;
            background: var(--viewer-bg);
            position: relative;
        }
        
        .viewer {
            max-width: 900px;
            margin: 20px auto;
            padding: 40px;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transform-origin: top center;
            transition: transform 0.2s ease;
        }
        
        /* ======================================
           DOCX 스타일 (docx-preview)
           ====================================== */
        .viewer.docx-viewer {
            padding: 0;
            max-width: 100%;
            background: transparent;
            box-shadow: none;
        }
        
        /* docx-preview 기본 컨테이너 스타일 */
        #docx-container {
            background: var(--card-bg);
        }
        
        /* docx-preview 래퍼 (페이지) 스타일 */
        .docx-wrapper {
            background: var(--card-bg) !important;
            padding: 40px 60px !important;
            margin: 20px auto !important;
            max-width: 900px !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
            border-radius: 8px !important;
        }
        
        [data-theme="dark"] .docx-wrapper {
            background: var(--card-bg) !important;
        }
        
        [data-theme="dark"] .docx-wrapper * {
            color: var(--text-color);
        }
        
        /* docx-preview 테이블 스타일 다크모드 대응 */
        [data-theme="dark"] .docx-wrapper table {
            border-color: var(--table-border) !important;
        }
        
        [data-theme="dark"] .docx-wrapper td,
        [data-theme="dark"] .docx-wrapper th {
            border-color: var(--table-border) !important;
        }
        
        /* ======================================
           XLSX 스타일
           ====================================== */
        .viewer.xlsx-viewer {
            padding: 20px;
            max-width: 100%;
        }
        .sheet-tabs {
            display: flex;
            gap: 4px;
            padding: 10px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
            flex-shrink: 0;
        }
        .sheet-tab {
            padding: 8px 16px;
            border: none;
            background: var(--btn-bg);
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            white-space: nowrap;
            color: var(--text-color);
        }
        .sheet-tab:hover { background: var(--btn-hover); }
        .sheet-tab.active {
            background: <?php echo $file_info['color']; ?>;
            color: #fff;
        }
        .excel-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .excel-table th,
        .excel-table td {
            border: 1px solid var(--table-border);
            padding: 6px 10px;
            text-align: left;
            min-width: 80px;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .excel-table th {
            background: var(--table-header-bg);
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .excel-table tr:first-child td {
            background: var(--table-header-bg);
            font-weight: 600;
        }
        .row-num {
            background: var(--table-header-bg);
            text-align: center;
            color: #666;
            width: 40px;
            min-width: 40px;
        }
        
        /* ======================================
           PPTX 스타일 (PPTXjs)
           ====================================== */
        .viewer.pptx-viewer {
            padding: 0;
            background: transparent;
            box-shadow: none;
            max-width: 100%;
        }
        
        /* PPTXjs 슬라이드 컨테이너 */
        #pptx-container {
            padding: 20px;
        }
        
        #pptx-container .slide {
            margin: 20px auto !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
            border-radius: 8px !important;
            overflow: hidden;
        }
        
        /* 슬라이드 네비게이션 */
        .slide-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            padding: 15px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
        }
        .slide-nav button {
            padding: 10px 20px;
            border: none;
            background: <?php echo $file_info['color']; ?>;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .slide-nav button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .slide-nav button:hover:not(:disabled) {
            filter: brightness(1.1);
        }
        .slide-counter {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* PPTXjs 슬라이드 쇼 모드 */
        .slide-thumbnails-pptx {
            display: flex;
            gap: 8px;
            padding: 15px;
            background: var(--header-bg);
            border-top: 1px solid var(--border-color);
            overflow-x: auto;
            flex-shrink: 0;
        }
        .slide-thumb-pptx {
            min-width: 80px;
            height: 60px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
            background: var(--card-bg);
            flex-shrink: 0;
            overflow: hidden;
        }
        .slide-thumb-pptx:hover { border-color: <?php echo $file_info['color']; ?>; }
        .slide-thumb-pptx.active {
            border-color: <?php echo $file_info['color']; ?>;
            border-width: 3px;
        }
        .slide-thumb-pptx img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        /* 로딩 오버레이 */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--viewer-bg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top-color: <?php echo $file_info['color']; ?>;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text {
            margin-top: 15px;
            font-size: 14px;
            color: var(--text-color);
        }
        
        /* 에러/레거시 컨테이너 */
        .error-container, .legacy-container {
            text-align: center;
            padding: 60px 20px;
        }
        .error-icon, .legacy-icon { font-size: 64px; margin-bottom: 20px; }
        .error-message, .legacy-message {
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .error-btn, .legacy-btn {
            display: inline-block;
            padding: 12px 24px;
            background: <?php echo $file_info['color']; ?>;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
        }
        .error-btn:hover, .legacy-btn:hover {
            filter: brightness(1.1);
            color: #fff;
        }
        
        /* 태블릿 대응 */
        @media (max-width: 1024px) {
            .viewer { max-width: 100%; margin: 15px; }
            .docx-wrapper { 
                max-width: 100% !important;
                padding: 30px 40px !important;
            }
        }
        
        /* 모바일 대응 */
        @media (max-width: 768px) {
            .header { 
                padding: 8px 10px;
                min-height: 44px;
                flex-wrap: wrap;
                gap: 8px;
            }
            .header-left {
                flex: 1;
                min-width: 0;
            }
            .filename { 
                font-size: 12px;
                max-width: 150px;
            }
            .file-badge {
                font-size: 10px;
                padding: 3px 8px;
            }
            .toolbar { 
                gap: 4px;
            }
            .toolbar-btn { 
                width: 36px; 
                height: 36px;
            }
            .zoom-controls {
                padding: 2px;
            }
            .zoom-controls button {
                width: 32px;
                height: 32px;
                font-size: 18px;
            }
            .zoom-controls span {
                font-size: 11px;
                min-width: 35px;
            }
            
            /* 뷰어 컨테이너 */
            .viewer-container {
                -webkit-overflow-scrolling: touch;
            }
            .viewer { 
                margin: 10px 5px; 
                padding: 15px;
                border-radius: 4px;
            }
            
            /* DOCX */
            .docx-wrapper { 
                padding: 15px !important;
                margin: 10px 5px !important;
                border-radius: 4px !important;
            }
            
            /* XLSX */
            .sheet-tabs {
                padding: 8px;
                gap: 4px;
            }
            .sheet-tab {
                padding: 6px 12px;
                font-size: 12px;
            }
            .excel-table {
                font-size: 11px;
            }
            .excel-table th,
            .excel-table td {
                padding: 4px 6px;
                min-width: 60px;
            }
            .viewer.xlsx-viewer {
                padding: 10px;
            }
            
            /* PPTX */
            #pptx-container { 
                padding: 10px 5px;
            }
            #pptx-container .slide {
                margin: 10px auto !important;
            }
            .slide-nav {
                padding: 10px;
                gap: 10px;
                flex-wrap: wrap;
            }
            .slide-nav button {
                padding: 8px 12px;
                font-size: 12px;
            }
            .slide-counter {
                font-size: 12px;
            }
            .slide-thumbnails-pptx {
                max-height: 80px;
            }
            .slide-thumb {
                width: 60px !important;
                height: auto !important;
            }
        }
        
        /* 소형 모바일 */
        @media (max-width: 480px) {
            .header {
                padding: 6px 8px;
            }
            .back-btn {
                width: 32px;
                height: 32px;
            }
            .filename {
                font-size: 11px;
                max-width: 100px;
            }
            .file-badge {
                display: none;
            }
            .toolbar-btn {
                width: 32px;
                height: 32px;
            }
            .zoom-controls button {
                width: 28px;
                height: 28px;
            }
            .zoom-controls span {
                font-size: 10px;
                min-width: 30px;
            }
            
            .viewer {
                margin: 5px;
                padding: 10px;
            }
            .docx-wrapper {
                padding: 10px !important;
                margin: 5px !important;
            }
            
            /* 엑셀 테이블 스크롤 가능하게 */
            .viewer.xlsx-viewer {
                padding: 5px;
                overflow-x: auto;
            }
            .excel-table {
                font-size: 10px;
            }
            .excel-table th,
            .excel-table td {
                padding: 3px 4px;
                min-width: 50px;
            }
            
            /* PPT 네비게이션 간소화 */
            .slide-nav button {
                padding: 6px 10px;
                font-size: 11px;
            }
        }
        
        /* 인쇄 스타일 */
        @media print {
            .header, .loading-overlay, .sheet-tabs, .slide-nav, .slide-thumbnails-pptx { display: none !important; }
            .viewer-container { overflow: visible !important; }
            .viewer { box-shadow: none !important; margin: 0 !important; max-width: none !important; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <a href="<?php echo h($back_url); ?>" class="back-btn" title="<?php echo __h('js_go_back'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </a>
            <span class="filename" title="<?php echo h($filename); ?>"><?php echo h($title); ?></span>
            <span class="file-badge">
                <span><?php echo $file_info['icon']; ?></span>
                <span><?php echo h($file_info['name']); ?></span>
            </span>
            <?php render_lang_badge('sm-office'); ?>
        </div>
        <div class="toolbar">
            <?php if (!$is_legacy): ?>
            <div class="zoom-controls">
                <button onclick="zoomOut()" title="<?php echo __h('hwp_zoom_out'); ?>">−</button>
                <span id="zoomValue">100%</span>
                <button onclick="zoomIn()" title="<?php echo __h('hwp_zoom_in'); ?>">+</button>
            </div>
            <?php endif; ?>
            <button class="toolbar-btn" onclick="printDocument()" title="<?php echo __h('hwp_print'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
            </button>
            <button class="toolbar-btn" onclick="toggleDarkMode()" title="<?php echo __h('hwp_toggle_dark'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/>
                    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                </svg>
            </button>
        </div>
    </div>
    
    <?php if ($ext === 'xlsx' || $ext === 'xls'): ?>
    <div class="sheet-tabs" id="sheetTabs"></div>
    <?php endif; ?>
    
    <?php if ($ext === 'pptx'): ?>
    <div class="slide-nav" id="slideNav" style="display:none;">
        <button onclick="prevSlide()">◀ <?php echo __h("js_prev"); ?></button>
        <span class="slide-counter"><?php echo __h("office_slide"); ?> <span id="currentSlide">1</span> / <span id="totalSlides">1</span></span>
        <button onclick="nextSlide()"><?php echo __h("js_next"); ?> ▶</button>
    </div>
    <?php endif; ?>
    
    <div class="viewer-container" id="viewerContainer">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
            <div class="loading-text"><?php echo __h("js_loading"); ?></div>
        </div>
        
        <?php if ($is_legacy): ?>
        <div class="viewer">
            <div class="legacy-container">
                <div class="legacy-icon">⚠️</div>
                <div class="legacy-message">
                    <strong><?php echo h($file_info['name']); ?></strong> <?php echo __h("office_legacy_format"); ?><br>
                    <?php echo __('office_legacy_convert', strtoupper(str_replace(['doc', 'xls', 'ppt'], ['docx', 'xlsx', 'pptx'], $ext))); ?>
                </div>
                <a href="<?php echo h($back_url); ?>" class="legacy-btn"><?php echo __h("js_go_back"); ?></a>
            </div>
        </div>
        <?php elseif ($ext === 'docx'): ?>
        <div class="viewer docx-viewer">
            <div id="docx-container"></div>
        </div>
        <?php elseif ($ext === 'xlsx'): ?>
        <div class="viewer xlsx-viewer" id="viewer"></div>
        <?php elseif ($ext === 'pptx'): ?>
        <div class="viewer pptx-viewer">
            <div id="pptx-container"></div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($ext === 'pptx'): ?>
    <div class="slide-thumbnails-pptx" id="slideThumbnails"></div>
    <?php endif; ?>
    
    <!-- ======================================
         라이브러리 로드
         ====================================== -->
    <?php if (!$is_legacy): ?>
    
<?php
render_viewer_i18n([
    'docx_load_fail' => 'js_docx_load_fail',
    'xlsx_load_fail' => 'js_xlsx_load_fail',
    'pptx_load_fail' => 'js_pptx_load_fail',
    'sheet_no_data' => 'js_sheet_no_data',
    'slide_not_found' => 'js_slide_not_found',
    'no_text_content' => 'js_no_text_content',
]);
?>

    <?php if ($ext === 'docx'): ?>
    <!-- docx-preview: mammoth.js보다 서식 지원 훨씬 좋음 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3.7/dist/docx-preview.min.js"></script>
    
    <?php elseif ($ext === 'xlsx'): ?>
    <!-- SheetJS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <?php elseif ($ext === 'pptx'): ?>
    <!-- PPTX: JSZip으로 텍스트 추출 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <?php endif; ?>
    
    <?php endif; ?>
    
    <script>
        document.documentElement.classList.add('ready');
        
        const fileUrl = '<?php echo $file_url; ?>';
        const backUrl = '<?php echo h($back_url); ?>';
        const fileExt = '<?php echo $ext; ?>';
        const isLegacy = <?php echo $is_legacy ? 'true' : 'false'; ?>;
        
        const viewerContainer = document.getElementById('viewerContainer');
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        // 모바일 감지 및 초기 줌 레벨 설정
        const isMobile = window.innerWidth <= 768;
        const isSmallMobile = window.innerWidth <= 480;
        let currentZoom = 100;
        
        // 모바일에서는 화면 너비에 맞게 줌 조정
        if (isSmallMobile) {
            currentZoom = Math.floor((window.innerWidth - 20) / 900 * 100);
            currentZoom = Math.max(50, Math.min(100, currentZoom));
        } else if (isMobile) {
            currentZoom = Math.floor((window.innerWidth - 30) / 900 * 100);
            currentZoom = Math.max(50, Math.min(100, currentZoom));
        }
        
        let currentSlide = 0;
        let totalSlides = 0;
        let sheets = [];
        let currentSheet = 0;
        
        if (isLegacy) {
            loadingOverlay.style.display = 'none';
        }
        
<?php if ($ext === 'docx'): ?>
        // ======================================
        // DOCX 로드 (docx-preview 사용)
        // ======================================
        async function loadDocument() {
            try {
                const response = await fetch(fileUrl, { credentials: 'same-origin' });
                if (!response.ok) throw new Error(_vi18n.http_error + ' ' + response.status + ')');
                
                const blob = await response.blob();
                const container = document.getElementById('docx-container');
                
                // docx-preview 옵션
                const options = {
                    className: 'docx',
                    inWrapper: true,
                    ignoreWidth: false,
                    ignoreHeight: false,
                    ignoreFonts: false,
                    breakPages: true,
                    ignoreLastRenderedPageBreak: false,
                    experimental: true,
                    trimXmlDeclaration: true,
                    useBase64URL: true,
                    renderHeaders: true,
                    renderFooters: true,
                    renderFootnotes: true,
                    renderEndnotes: true,
                    debug: false
                };
                
                await docx.renderAsync(blob, container, null, options);
                loadingOverlay.style.display = 'none';
                
                // 모바일에서 초기 줌 적용
                if (isMobile) {
                    setTimeout(applyZoom, 100);
                }
                
                console.log('DOCX 렌더링 완료 (docx-preview)');
            } catch (error) {
                console.error('DOCX 로드 오류:', error);
                showError(error.message || _vi18n.docx_load_fail);
            }
        }
        
<?php elseif ($ext === 'xlsx'): ?>
        // ======================================
        // XLSX 로드 (SheetJS 사용)
        // ======================================
        const viewerEl = document.getElementById('viewer');
        
        async function loadDocument() {
            try {
                const response = await fetch(fileUrl, { credentials: 'same-origin' });
                if (!response.ok) throw new Error(_vi18n.http_error + ' ' + response.status + ')');
                
                const arrayBuffer = await response.arrayBuffer();
                const workbook = XLSX.read(arrayBuffer, { type: 'array' });
                
                sheets = workbook.SheetNames.map(name => ({
                    name: name,
                    data: XLSX.utils.sheet_to_json(workbook.Sheets[name], { header: 1 })
                }));
                
                // 시트 탭 렌더링
                const tabsEl = document.getElementById('sheetTabs');
                if (sheets.length > 1) {
                    tabsEl.innerHTML = sheets.map((sheet, idx) => 
                        `<button class="sheet-tab ${idx === 0 ? 'active' : ''}" onclick="switchSheet(${idx})">${escapeHtml(sheet.name)}</button>`
                    ).join('');
                } else {
                    tabsEl.style.display = 'none';
                }
                
                renderSheet(0);
                loadingOverlay.style.display = 'none';
            } catch (error) {
                showError(error.message || _vi18n.xlsx_load_fail);
            }
        }
        
        function switchSheet(idx) {
            currentSheet = idx;
            document.querySelectorAll('.sheet-tab').forEach((tab, i) => {
                tab.classList.toggle('active', i === idx);
            });
            renderSheet(idx);
        }
        
        function renderSheet(idx) {
            const sheet = sheets[idx];
            if (!sheet || !sheet.data || sheet.data.length === 0) {
                viewerEl.innerHTML = '<div class="error-container"><div class="error-icon">📊</div><div class="error-message">' + _vi18n.sheet_no_data + '</div></div>';
                return;
            }
            
            const maxCols = Math.max(...sheet.data.map(row => row ? row.length : 0));
            
            let html = '<div style="overflow-x:auto;"><table class="excel-table"><tbody>';
            sheet.data.forEach((row, rowIdx) => {
                html += '<tr>';
                html += `<td class="row-num">${rowIdx + 1}</td>`;
                for (let col = 0; col < maxCols; col++) {
                    const cell = row && row[col] !== undefined ? row[col] : '';
                    html += `<td>${escapeHtml(String(cell))}</td>`;
                }
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            
            viewerEl.innerHTML = html;
        }
        
<?php elseif ($ext === 'pptx'): ?>
        // ======================================
        // PPTX 로드 (JSZip 기반 텍스트 추출)
        // ======================================
        const viewerEl = document.getElementById('pptx-container');
        let slides = [];
        
        async function loadDocument() {
            try {
                const response = await fetch(fileUrl, { credentials: 'same-origin' });
                if (!response.ok) throw new Error(_vi18n.http_error + ' ' + response.status + ')');
                
                const arrayBuffer = await response.arrayBuffer();
                const zip = await JSZip.loadAsync(arrayBuffer);
                
                // 슬라이드 파일 찾기
                const slideFiles = Object.keys(zip.files)
                    .filter(name => name.match(/ppt\/slides\/slide\d+\.xml$/))
                    .sort((a, b) => {
                        const numA = parseInt(a.match(/slide(\d+)/)[1]);
                        const numB = parseInt(b.match(/slide(\d+)/)[1]);
                        return numA - numB;
                    });
                
                slides = [];
                
                for (const slideFile of slideFiles) {
                    const content = await zip.file(slideFile).async('string');
                    const parser = new DOMParser();
                    const xmlDoc = parser.parseFromString(content, 'text/xml');
                    
                    // 텍스트 추출
                    const texts = [];
                    const textElements = xmlDoc.getElementsByTagName('a:t');
                    for (let i = 0; i < textElements.length; i++) {
                        const text = textElements[i].textContent;
                        if (text && text.trim()) {
                            texts.push(text);
                        }
                    }
                    
                    slides.push({
                        number: slides.length + 1,
                        texts: texts
                    });
                }
                
                if (slides.length === 0) {
                    throw new Error(_vi18n.slide_not_found);
                }
                
                totalSlides = slides.length;
                
                // UI 업데이트
                document.getElementById('slideNav').style.display = 'flex';
                document.getElementById('totalSlides').textContent = totalSlides;
                
                // 썸네일 렌더링
                renderThumbnails();
                renderSlide(0);
                
                loadingOverlay.style.display = 'none';
                console.log('PPTX 로드 완료 - ' + totalSlides + '개 슬라이드');
                
            } catch (error) {
                console.error('PPTX 로드 오류:', error);
                showError(error.message || _vi18n.pptx_load_fail);
            }
        }
        
        function renderThumbnails() {
            const thumbsEl = document.getElementById('slideThumbnails');
            thumbsEl.innerHTML = slides.map((s, idx) => 
                `<div class="slide-thumb-pptx ${idx === 0 ? 'active' : ''}" onclick="goToSlide(${idx})">${idx + 1}</div>`
            ).join('');
        }
        
        function renderSlide(idx) {
            currentSlide = idx;
            const slide = slides[idx];
            
            document.getElementById('currentSlide').textContent = idx + 1;
            document.querySelectorAll('.slide-thumb-pptx').forEach((thumb, i) => {
                thumb.classList.toggle('active', i === idx);
            });
            
            // 네비게이션 버튼 상태
            updateNavButtons();
            
            // 슬라이드 내용
            let html = '<div class="slide-content">';
            if (slide.texts.length > 0) {
                html += `<div class="slide-title">${escapeHtml(slide.texts[0])}</div>`;
                if (slide.texts.length > 1) {
                    html += '<div class="slide-body">';
                    slide.texts.slice(1).forEach(text => {
                        html += `<p>${escapeHtml(text)}</p>`;
                    });
                    html += '</div>';
                }
            } else {
                html += '<div style="text-align:center;color:#888;">' + _vi18n.no_text_content + '</div>';
            }
            html += '</div>';
            
            viewerEl.innerHTML = html;
        }
        
        function goToSlide(idx) {
            if (idx >= 0 && idx < slides.length) {
                renderSlide(idx);
            }
        }
        
        function prevSlide() {
            if (currentSlide > 0) {
                goToSlide(currentSlide - 1);
            }
        }
        
        function nextSlide() {
            if (currentSlide < totalSlides - 1) {
                goToSlide(currentSlide + 1);
            }
        }
        
        function updateNavButtons() {
            const nav = document.getElementById('slideNav');
            const buttons = nav.querySelectorAll('button');
            buttons[0].disabled = currentSlide === 0;
            buttons[1].disabled = currentSlide === totalSlides - 1;
        }
<?php endif; ?>
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showError(message) {
            loadingOverlay.style.display = 'none';
            viewerContainer.innerHTML = `
                <div class="viewer">
                    <div class="error-container">
                        <div class="error-icon">📄</div>
                        <div class="error-message">${message}</div>
                        <a href="${backUrl}" class="error-btn">${_vi18n.go_back}</a>
                    </div>
                </div>
            `;
        }
        
        function zoomIn() {
            if (currentZoom < 200) {
                currentZoom += 10;
                applyZoom();
            }
        }
        
        function zoomOut() {
            if (currentZoom > 50) {
                currentZoom -= 10;
                applyZoom();
            }
        }
        
        function applyZoom() {
            <?php if ($ext === 'docx'): ?>
            const docxWrapper = document.querySelector('.docx-wrapper');
            if (docxWrapper) {
                docxWrapper.style.transform = `scale(${currentZoom / 100})`;
                docxWrapper.style.transformOrigin = 'top center';
            }
            <?php elseif ($ext === 'xlsx'): ?>
            if (viewerEl) {
                viewerEl.style.transform = `scale(${currentZoom / 100})`;
                viewerEl.style.transformOrigin = 'top center';
            }
            <?php elseif ($ext === 'pptx'): ?>
            const pptxContainer = document.getElementById('pptx-container');
            if (pptxContainer) {
                pptxContainer.style.transform = `scale(${currentZoom / 100})`;
                pptxContainer.style.transformOrigin = 'top center';
            }
            <?php endif; ?>
            
            const zoomVal = document.getElementById('zoomValue');
            if (zoomVal) {
                zoomVal.textContent = currentZoom + '%';
            }
        }
        
        function printDocument() {
            window.print();
        }
        
        function toggleDarkMode() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            document.cookie = `darkmode=${next};path=/;max-age=31536000`;
        }
        
        // 키보드 단축키
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === '+' || e.key === '=') { e.preventDefault(); zoomIn(); }
                else if (e.key === '-') { e.preventDefault(); zoomOut(); }
                else if (e.key === 'p') { e.preventDefault(); printDocument(); }
            }
            if (e.key === 'Escape') window.location.href = backUrl;
            
            <?php if ($ext === 'pptx'): ?>
            // PPTX 슬라이드 네비게이션
            if (totalSlides > 0) {
                if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    prevSlide();
                } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === ' ') {
                    e.preventDefault();
                    nextSlide();
                }
            }
            <?php endif; ?>
        });
        
        // 터치 제스처 (핀치 줌)
        let initialDistance = 0, initialZoom = 100;
        
        viewerContainer.addEventListener('touchstart', (e) => {
            if (e.touches.length === 2) {
                initialDistance = Math.hypot(
                    e.touches[0].pageX - e.touches[1].pageX,
                    e.touches[0].pageY - e.touches[1].pageY
                );
                initialZoom = currentZoom;
            }
        }, { passive: true });
        
        viewerContainer.addEventListener('touchmove', (e) => {
            if (e.touches.length === 2) {
                const distance = Math.hypot(
                    e.touches[0].pageX - e.touches[1].pageX,
                    e.touches[0].pageY - e.touches[1].pageY
                );
                currentZoom = Math.min(200, Math.max(50, Math.round(initialZoom * (distance / initialDistance))));
                applyZoom();
            }
        }, { passive: true });
        
        <?php if ($ext === 'pptx'): ?>
        // 스와이프 (PPTX)
        let touchStartX = 0;
        viewerContainer.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                touchStartX = e.touches[0].clientX;
            }
        }, { passive: true });
        
        viewerContainer.addEventListener('touchend', (e) => {
            if (e.changedTouches.length === 1 && totalSlides > 0) {
                const diff = e.changedTouches[0].clientX - touchStartX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0) prevSlide();
                    else nextSlide();
                }
            }
        }, { passive: true });
        <?php endif; ?>
        
        // 시작
        if (!isLegacy) {
            loadDocument();
            
            // 초기 줌 값 표시
            const zoomVal = document.getElementById('zoomValue');
            if (zoomVal) {
                zoomVal.textContent = currentZoom + '%';
            }
        }
    </script>

<?php
// 자동 로그아웃 스크립트
$timeout = $auto_logout_settings['timeout'] ?? 1800;
$remaining = $timeout - (time() - ($_SESSION['last_activity'] ?? time()));
if ($remaining < 0) $remaining = 0;

$_current_page = basename($_SERVER['SCRIPT_FILENAME']);
$_auto_logout_pages = $auto_logout_settings['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'hwp_viewer.php', 'office_viewer.php', 'bookmark.php'];
$_is_target = in_array($_current_page, $_auto_logout_pages);
$_is_remember_me = isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true;

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