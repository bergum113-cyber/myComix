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

// ★ (2026-10-30) 보안: 폴더 권한 확인(API·스트림 포함 모든 요청)
mc_file_param_guard(isset($_GET['action']) || isset($_POST['action']));
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
// ★ (2026-10-30) .xls 도 SheetJS 로 바로 보기(예전 엑셀 형식도 읽을 수 있음) — 변환 안내는 .doc·.ppt 만
$is_legacy = in_array($ext, ['doc', 'ppt']);
$is_sheet = in_array($ext, ['xlsx', 'xls']);

$link_dir = dirname($getfile);
if ($link_dir === '.' || $link_dir === '/') {
    $link_dir = '';
}

$darkmode = (is_string($_COOKIE['darkmode'] ?? null) && preg_match('/^[a-z]{1,12}$/', $_COOKIE['darkmode'])) ? $_COOKIE['darkmode'] : 'light'; // ★ (2026-11-02 19:21) 배열 쿠키(darkmode[]=)면 500 이던 것
$file_url = "office_viewer.php?action=stream&file=" . rawurlencode($getfile) . "&bidx=" . $bidx;

// 뒤로가기 URL
$back_url = "index.php?dir=" . rawurlencode($link_dir) . "&bidx=" . $bidx;
?>
<!DOCTYPE html>
<html lang="ko" class="mc-fdk" data-theme="<?php echo h($darkmode); ?>"><?php /* ★ (2026-11-08 fix82) 다크면 바탕은 어둡게 두고 내용만 나타나게(bootstrap.php mc_fade_dark_style) */ ?>
<?php if (function_exists('mc_fade_dark_style')) echo mc_fade_dark_style('#1a1a1a'); ?>
<head>
<?php if (function_exists('mc_back_origin_viewer_js')) echo mc_back_origin_viewer_js(); /* ★ (2026-11-05) 뒤로가기 = 들어온 곳(서재·즐겨찾기·북마크 등) — function.php */ ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo h($title); ?> - <?php echo h($file_info['name']); ?> <?php echo __h("viewer_label"); ?></title>
    
    <style>
        html{opacity:0;transition:opacity .15s ease-in}
        html.ready{opacity:1}
        html.leaving{opacity:0;transition:opacity .1s ease-out}
    </style>
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
    
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
        /* ★ (2026-10-30) PPT 표·그림 */
        .slide-body .slide-table { border-collapse: collapse; margin: 0.5em 0 1em; font-size: 15px; line-height: 1.5; max-width: 100%; }
        .slide-body .slide-table td { border: 1px solid var(--table-border, #ccc); padding: 4px 8px; vertical-align: top; }
        .slide-body .slide-img { margin: 0.5em 0 1em; text-align: center; }
        .slide-body .slide-img img { max-width: 100%; height: auto; }
        /* ★ (2026-10-30) 휴대폰: 최소 높이 400px × 16:9 비율 때문에 슬라이드가 711px 이상으로 넓어져 오른쪽이 잘리던 것 */
        @media (max-width: 768px) {
            .slide-content { min-height: 0; aspect-ratio: auto; padding: 24px 20px; margin: 10px auto; }
            .slide-title { font-size: 22px; }
            .slide-body { font-size: 16px; }
        }
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
        
        /* ★ (2026-11-02 20:14) 다크에서도 DOCX 쪽은 흰 종이 + 문서 자기 색(옛 HWP 와 같은 방식)
           — 글자만 밝게 바꾸면 흰 쪽 위에서 안 보이고, 쪽을 어둡게 하면 문서가 직접 지정한 검정 글자·형광펜이 안 보였음 */
        [data-theme="dark"] .docx-wrapper > section.docx { background: #fff !important; color: #000; }
        
        /* docx-preview 테이블 스타일 다크모드 대응 */
        /* ★ (2026-11-02 21:19) 다크 표 테두리 덮기 규칙 뺌 — 쪽이 흰 종이(문서 자기 색)가 된 뒤로는 빨강 테두리가 회색, 숨긴 테두리가 회색 선으로 드러났음 */
        
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
        /* ★ (2026-10-30) 열 머리(A·B·C…) */
        .excel-table th.col-head { text-align: center; color: #666; font-weight: 500; }
        [data-theme="dark"] .row-num, [data-theme="dark"] .excel-table th.col-head { color: #9aa0a6; } /* ★ (2026-11-02 20:15) 다크 바탕(#363636)에서 #666 은 거의 안 보였음 */
        .excel-table th.row-num { z-index: 2; }
        
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
            <a data-mc-back href="<?php echo h($back_url); ?>" class="back-btn" title="<?php echo __h('js_go_back'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </a>
            <span class="filename" title="<?php echo h($filename); ?>"><?php echo h($title); ?></span>
            <?php if (function_exists('mc_doc_cover_btn')) echo mc_doc_cover_btn($getfile, $bidx ?? 0, 'back-btn'); /* ★ (2026-11-08 fix80) 이 책 표지를 폴더 표지로(관리자) */ ?>
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
                <a data-mc-back href="<?php echo h($back_url); ?>" class="legacy-btn"><?php echo __h("js_go_back"); ?></a>
            </div>
        </div>
        <?php elseif ($ext === 'docx'): ?>
        <div class="viewer docx-viewer">
            <div id="docx-container"></div>
        </div>
        <?php elseif ($is_sheet): ?>
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

    <?php
    // ★ (2026-10-30) 라이브러리를 사이트 안(js/)에 두고 씀 — 외부 CDN 이 막히거나 바뀌어도 그대로 동작
    //   SheetJS 0.18.5 → 0.20.3(알려진 취약점 CVE-2023-30533·CVE-2024-22363 수정판, 공식 빌드와 해시 같음),
    //   docx-preview 0.3.7 → 0.4.1, JSZip 3.10.1 → 3.10.2
    $__jsv = function ($f) { return 'js/' . $f . '?v=' . (@filemtime(__DIR__ . '/js/' . $f) ?: '1'); };
    ?>
    <?php if ($ext === 'docx'): ?>
    <!-- docx-preview: mammoth.js보다 서식 지원 훨씬 좋음 -->
    <script src="<?php echo h($__jsv('jszip.min.js')); ?>"></script>
    <script src="<?php echo h($__jsv('docx-preview.min.js')); ?>"></script>
    
    <?php elseif ($is_sheet): ?>
    <!-- SheetJS -->
    <script src="<?php echo h($__jsv('xlsx.full.min.js')); ?>"></script>
    
    <?php elseif ($ext === 'pptx'): ?>
    <!-- PPTX: JSZip으로 텍스트·그림 추출 -->
    <script src="<?php echo h($__jsv('jszip.min.js')); ?>"></script>
    <?php endif; ?>
    
    <?php endif; ?>
    
    <script>
        document.documentElement.classList.add('ready');
        
        const fileUrl = '<?php echo $file_url; ?>';
        const backUrl = (window.mcBackUrl ? window.mcBackUrl(<?php echo js($back_url); ?>) : <?php echo js($back_url); ?>); // ★ (2026-11-05) 들어온 곳으로 // ★ (2026-11-02 09:03) h() 로 넣으면 &amp;bidx 가 되어 Esc 로 돌아갈 때 콘텐츠 폴더(bidx)가 빠졌음
        const fileExt = '<?php echo $ext; ?>';
        const isLegacy = <?php echo $is_legacy ? 'true' : 'false'; ?>;
        
        const viewerContainer = document.getElementById('viewerContainer');
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        // 모바일 감지 및 초기 줌 레벨 설정
        const isMobile = window.innerWidth <= 768;
        const isSmallMobile = window.innerWidth <= 480;
        let currentZoom = 100;
        
        // 모바일에서는 화면 너비에 맞게 줌 조정
        // ★ (2026-11-02 19:21) 줄여 그리는 것은 DOCX 뿐 — 시트·PPT 는 100% 그대로인데 표시만 50~67% 라 '+' 를 누르면 오히려 작아졌음
        if (<?php echo $ext === 'docx' ? 'true' : 'false'; ?> && isSmallMobile) {
            currentZoom = Math.floor((window.innerWidth - 20) / 900 * 100);
            currentZoom = Math.max(50, Math.min(100, currentZoom));
        } else if (<?php echo $ext === 'docx' ? 'true' : 'false'; ?> && isMobile) {
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
                    renderAltChunks: false, // ★ (2026-11-04 09:41) 문서 속 HTML 조각(altChunk)은 그리지 않음 — 켜면 샌드박스 없는 iframe(srcdoc)으로 들어가 사이트 권한으로 스크립트가 돌 수 있었음
                    debug: false
                };
                
                await docx.renderAsync(blob, container, null, options);
                // ★ (2026-10-30) 문서 안 링크가 javascript: 등이면 제거(사이트 화면에서 바로 그리므로 누르면 사이트 권한으로 실행될 수 있음)
                container.querySelectorAll('a[href]').forEach(function (a) {
                    var h = String(a.getAttribute('href') || '').replace(/[\s\u0000-\u001f]+/g, '').toLowerCase();
                    if (!(h.indexOf('http:') === 0 || h.indexOf('https:') === 0 || h.indexOf('mailto:') === 0 || h.charAt(0) === '#')) a.removeAttribute('href');
                    else if (h.charAt(0) !== '#') { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener noreferrer'); }
                });
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
        
<?php elseif ($is_sheet): ?>
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
                
                // ★ (2026-10-30) 시트 원본을 들고 있다가 그릴 때 표시값(날짜·서식)·병합·실제 행/열 번호로 그림
                sheets = workbook.SheetNames.map(name => ({
                    name: name,
                    ws: workbook.Sheets[name]
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
        
        // ★ (2026-10-30) 셀 표시값: 엑셀에 보이는 글자(날짜·통화·% 등 서식 적용) 그대로
        function sheetCellText(cell) {
            if (!cell) return '';
            try {
                const t = XLSX.utils.format_cell(cell);
                if (t !== undefined && t !== null && t !== '') return String(t);
            } catch (e) {}
            return cell.v === undefined || cell.v === null ? '' : String(cell.v);
        }
        
        function renderSheet(idx) {
            const sheet = sheets[idx];
            const ws = sheet ? sheet.ws : null;
            const noData = () => {
                viewerEl.innerHTML = '<div class="error-container"><div class="error-icon">📊</div><div class="error-message">' + _vi18n.sheet_no_data + '</div></div>';
            };
            if (!ws || !ws['!ref']) { noData(); return; }
            
            // 범위: !ref 시작점 기준(행/열 번호가 실제 엑셀과 같게), 끝은 실제 값 있는 셀·병합까지만
            //   (가끔 !ref 가 A1:XFD1048576 처럼 과하게 잡힌 파일이 있어 빈 칸 수백만 개를 그리지 않게)
            const ref = XLSX.utils.decode_range(ws['!ref']);
            let maxR = -1, maxC = -1;
            Object.keys(ws).forEach(k => {
                if (k.charAt(0) === '!') return;
                const cell = ws[k];
                if (!cell || cell.t === 'z') return;
                const a = XLSX.utils.decode_cell(k);
                if (a.r > maxR) maxR = a.r;
                if (a.c > maxC) maxC = a.c;
            });
            const merges = Array.isArray(ws['!merges']) ? ws['!merges'] : [];
            merges.forEach(m => { if (m.e.r > maxR) maxR = m.e.r; if (m.e.c > maxC) maxC = m.e.c; });
            const r0 = ref.s.r, c0 = ref.s.c;
            const r1 = Math.min(ref.e.r, maxR), c1 = Math.min(ref.e.c, maxC);
            if (r1 < r0 || c1 < c0) { noData(); return; }
            
            // 병합: 시작 셀엔 rowspan/colspan, 가려지는 셀은 건너뜀
            const mStart = {}, mCover = {};
            merges.forEach(m => {
                const sr = Math.max(m.s.r, r0), sc = Math.max(m.s.c, c0);
                const er = Math.min(m.e.r, r1), ec = Math.min(m.e.c, c1);
                if (er < sr || ec < sc) return;
                mStart[sr + ',' + sc] = { rs: er - sr + 1, cs: ec - sc + 1, r: m.s.r, c: m.s.c };
                for (let r = sr; r <= er; r++) for (let c = sc; c <= ec; c++) {
                    if (r !== sr || c !== sc) mCover[r + ',' + c] = 1;
                }
            });
            
            const parts = ['<div style="overflow-x:auto;"><table class="excel-table"><thead><tr><th class="row-num"></th>'];
            for (let c = c0; c <= c1; c++) parts.push('<th class="col-head">' + XLSX.utils.encode_col(c) + '</th>');
            parts.push('</tr></thead><tbody>');
            for (let r = r0; r <= r1; r++) {
                parts.push('<tr><td class="row-num">' + (r + 1) + '</td>');
                for (let c = c0; c <= c1; c++) {
                    const key = r + ',' + c;
                    if (mCover[key]) continue;
                    const m = mStart[key];
                    // 병합 영역 값은 원래 시작 셀(앞쪽이 잘려도 그 값)에 있음
                    const cell = ws[XLSX.utils.encode_cell(m ? { r: m.r, c: m.c } : { r: r, c: c })];
                    const span = m ? ((m.rs > 1 ? ' rowspan="' + m.rs + '"' : '') + (m.cs > 1 ? ' colspan="' + m.cs + '"' : '')) : '';
                    parts.push('<td' + span + '>' + escapeHtml(sheetCellText(cell)) + '</td>');
                }
                parts.push('</tr>');
            }
            parts.push('</tbody></table></div>');
            
            viewerEl.innerHTML = parts.join('');
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
                pptZip = await JSZip.loadAsync(arrayBuffer);
                const zip = pptZip;
                
                // ★ (2026-10-30) 슬라이드 순서는 presentation.xml 의 목록(sldIdLst) 순서 — 파일 이름 번호와 다를 수 있음
                let slideFiles = [];
                try {
                    const pres = await pptReadXml('ppt/presentation.xml');
                    const presRels = await pptReadRels('ppt/presentation.xml');
                    if (pres) {
                        pptEls(pres, 'sldId').forEach(el => {
                            const rid = el.getAttributeNS(PPT_RNS, 'id') || el.getAttribute('r:id');
                            const target = rid && presRels[rid];
                            if (target && zip.file(target) && slideFiles.indexOf(target) < 0) slideFiles.push(target);
                        });
                    }
                } catch (e) { slideFiles = []; }
                if (slideFiles.length === 0) {
                    // 목록을 못 읽으면 예전처럼 파일 이름 번호 순
                    slideFiles = Object.keys(zip.files)
                        .filter(name => /^ppt\/slides\/slide\d+\.xml$/.test(name))
                        .sort((a, b) => parseInt(a.match(/slide(\d+)\.xml$/)[1], 10) - parseInt(b.match(/slide(\d+)\.xml$/)[1], 10));
                }
                
                slides = [];
                
                for (const slideFile of slideFiles) {
                    const xmlDoc = await pptReadXml(slideFile);
                    const rels = await pptReadRels(slideFile);
                    const parsed = xmlDoc ? pptParseSlide(xmlDoc, rels) : { title: '', blocks: [] };
                    slides.push({
                        number: slides.length + 1,
                        title: parsed.title,
                        blocks: parsed.blocks
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
        
        // ★ (2026-10-30) PPTX 읽기 도우미 — 문단 단위로 글자 잇기, 제목 자리(placeholder) 인식, 표·그림 표시
        let pptZip = null;
        const pptImgCache = {};
        const PPT_RNS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        const PPT_IMG_TYPES = { png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', bmp: 'image/bmp', webp: 'image/webp' };
        
        function pptEls(node, local) {
            return Array.prototype.slice.call(node.getElementsByTagNameNS('*', local));
        }
        function pptChild(node, local) {
            if (!node) return null;
            for (let c = node.firstElementChild; c; c = c.nextElementSibling) if (c.localName === local) return c;
            return null;
        }
        async function pptReadXml(path) {
            const f = pptZip && pptZip.file(path);
            if (!f) return null;
            const doc = new DOMParser().parseFromString(await f.async('string'), 'application/xml');
            return doc.getElementsByTagName('parsererror').length ? null : doc;
        }
        // 상대 경로(../media/a.png)를 zip 안 경로로
        function pptResolve(baseFile, target) {
            if (!target) return '';
            let parts;
            if (target.charAt(0) === '/') parts = target.slice(1).split('/');
            else parts = baseFile.split('/').slice(0, -1).concat(target.split('/'));
            const out = [];
            parts.forEach(p => { if (p === '..') out.pop(); else if (p !== '.' && p !== '') out.push(p); });
            return out.join('/');
        }
        async function pptReadRels(file) {
            const dir = file.split('/').slice(0, -1).join('/');
            const name = file.split('/').pop();
            const doc = await pptReadXml((dir ? dir + '/' : '') + '_rels/' + name + '.rels');
            const map = {};
            if (!doc) return map;
            pptEls(doc, 'Relationship').forEach(r => {
                if ((r.getAttribute('TargetMode') || '') === 'External') return;
                const id = r.getAttribute('Id'), t = r.getAttribute('Target');
                if (id && t) map[id] = pptResolve(file, t);
            });
            return map;
        }
        // 문단 하나 = 그 안의 글자 조각(a:r·a:fld)을 이은 것, a:br 은 줄바꿈
        function pptParaText(p) {
            let t = '';
            for (let c = p.firstElementChild; c; c = c.nextElementSibling) {
                if (c.localName === 'r' || c.localName === 'fld') {
                    const tt = pptChild(c, 't');
                    if (tt) t += tt.textContent;
                } else if (c.localName === 'br') {
                    t += '\n';
                }
            }
            return t;
        }
        function pptParas(txBody) {
            const out = [];
            if (!txBody) return out;
            for (let p = txBody.firstElementChild; p; p = p.nextElementSibling) {
                if (p.localName !== 'p') continue;
                const text = pptParaText(p);
                if (!text.trim()) continue;
                const pPr = pptChild(p, 'pPr');
                const lvl = pPr ? (parseInt(pPr.getAttribute('lvl') || '0', 10) || 0) : 0;
                out.push({ text: text, lvl: Math.max(0, Math.min(8, lvl)) });
            }
            return out;
        }
        function pptUnderFallback(el) {
            for (let a = el.parentElement; a; a = a.parentElement) if (a.localName === 'Fallback') return true;
            return false;
        }
        function pptParseSlide(xmlDoc, rels) {
            let title = '';
            const blocks = [];
            const all = xmlDoc.getElementsByTagNameNS('*', '*');
            for (let i = 0; i < all.length; i++) {
                const el = all[i];
                const ln = el.localName;
                if (ln !== 'sp' && ln !== 'pic' && ln !== 'tbl') continue;
                if (pptUnderFallback(el)) continue;   // mc:AlternateContent 의 대체본은 중복이라 건너뜀
                if (ln === 'sp') {
                    const nv = pptChild(el, 'nvSpPr');
                    const ph = pptChild(pptChild(nv, 'nvPr'), 'ph');
                    const phType = ph ? (ph.getAttribute('type') || '') : '';
                    const paras = pptParas(pptChild(el, 'txBody'));
                    if (!paras.length) continue;
                    if (!title && (phType === 'title' || phType === 'ctrTitle')) {
                        title = paras.map(p => p.text).join('\n');
                    } else {
                        paras.forEach(p => blocks.push({ type: 'p', text: p.text, lvl: p.lvl }));
                    }
                } else if (ln === 'tbl') {
                    const rows = [];
                    pptEls(el, 'tr').forEach(tr => {
                        const cells = [];
                        for (let tc = tr.firstElementChild; tc; tc = tc.nextElementSibling) {
                            if (tc.localName !== 'tc') continue;
                            if (tc.getAttribute('hMerge') === '1' || tc.getAttribute('vMerge') === '1') continue;
                            cells.push({
                                text: pptParas(pptChild(tc, 'txBody')).map(p => p.text).join('\n'),
                                cs: Math.min(100, parseInt(tc.getAttribute('gridSpan') || '1', 10) || 1),
                                rs: Math.min(1000, parseInt(tc.getAttribute('rowSpan') || '1', 10) || 1)
                            });
                        }
                        rows.push(cells);
                    });
                    if (rows.some(r => r.some(c => c.text.trim()))) blocks.push({ type: 'table', rows: rows });
                } else if (ln === 'pic') {
                    const blip = pptEls(el, 'blip')[0];
                    const rid = blip ? (blip.getAttributeNS(PPT_RNS, 'embed') || blip.getAttribute('r:embed')) : '';
                    const path = rid ? rels[rid] : '';
                    const ext = path ? path.split('.').pop().toLowerCase() : '';
                    // 브라우저가 바로 그릴 수 있는 그림만(svg·emf·wmf 는 제외 — svg 는 스크립트가 들어갈 수 있음)
                    if (path && PPT_IMG_TYPES[ext]) blocks.push({ type: 'img', path: path, ext: ext });
                }
            }
            // 제목 자리가 없으면 예전처럼 첫 문단을 제목으로
            if (!title) {
                const k = blocks.findIndex(b => b.type === 'p');
                if (k === 0) { title = blocks[0].text; blocks.shift(); }
            }
            return { title: title, blocks: blocks };
        }
        function pptImageUrl(path, ext) {
            if (!pptImgCache[path]) {
                const f = pptZip && pptZip.file(path);
                pptImgCache[path] = f
                    ? f.async('arraybuffer').then(buf => URL.createObjectURL(new Blob([buf], { type: PPT_IMG_TYPES[ext] })))
                    : Promise.resolve('');
            }
            return pptImgCache[path];
        }
        function pptText(t) {
            return escapeHtml(t).replace(/\n/g, '<br>');
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
            if (slide.title || slide.blocks.length > 0) {
                if (slide.title) html += `<div class="slide-title">${pptText(slide.title)}</div>`;
                if (slide.blocks.length > 0) {
                    html += '<div class="slide-body">';
                    slide.blocks.forEach((b, bi) => {
                        if (b.type === 'p') {
                            html += `<p${b.lvl ? ' style="padding-left:' + (b.lvl * 1.5) + 'em"' : ''}>${pptText(b.text)}</p>`;
                        } else if (b.type === 'table') {
                            html += '<table class="slide-table">';
                            b.rows.forEach(r => {
                                html += '<tr>';
                                r.forEach(c => {
                                    html += '<td' + (c.cs > 1 ? ' colspan="' + c.cs + '"' : '') + (c.rs > 1 ? ' rowspan="' + c.rs + '"' : '') + '>' + pptText(c.text) + '</td>';
                                });
                                html += '</tr>';
                            });
                            html += '</table>';
                        } else if (b.type === 'img') {
                            html += `<div class="slide-img"><img alt="" data-bi="${bi}"></div>`;
                        }
                    });
                    html += '</div>';
                }
            } else {
                html += '<div style="text-align:center;color:#888;">' + _vi18n.no_text_content + '</div>';
            }
            html += '</div>';
            
            viewerEl.innerHTML = html;
            // 그림은 보는 슬라이드 것만 그때 꺼내서 붙임(넘기는 사이 다른 슬라이드로 바뀌면 안 붙임)
            viewerEl.querySelectorAll('img[data-bi]').forEach(img => {
                const b = slide.blocks[parseInt(img.getAttribute('data-bi'), 10)];
                if (!b) return;
                pptImageUrl(b.path, b.ext).then(u => {
                    if (u && currentSlide === idx && img.isConnected) img.src = u;
                    else if (!u) img.parentNode && img.parentNode.remove();
                }).catch(() => { img.parentNode && img.parentNode.remove(); });
            });
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
                        <div class="error-message">${escapeHtml(String(message || ''))}</div>
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
        
        // ★ (2026-11-02 19:21) 100% 넘게 키우면 기준점을 왼쪽 위로 — 가운데 기준이면 왼쪽이 화면 밖(음수)으로 밀려 스크롤로도 볼 수 없었음
        function applyZoom() {
            <?php if ($ext === 'docx'): ?>
            const docxWrapper = document.querySelector('.docx-wrapper');
            if (docxWrapper) {
                docxWrapper.style.transform = `scale(${currentZoom / 100})`;
                docxWrapper.style.transformOrigin = (currentZoom > 100 ? 'top left' : 'top center');
            }
            <?php elseif ($is_sheet): ?>
            if (viewerEl) {
                viewerEl.style.transform = `scale(${currentZoom / 100})`;
                viewerEl.style.transformOrigin = (currentZoom > 100 ? 'top left' : 'top center');
            }
            <?php elseif ($ext === 'pptx'): ?>
            const pptxContainer = document.getElementById('pptx-container');
            if (pptxContainer) {
                pptxContainer.style.transform = `scale(${currentZoom / 100})`;
                pptxContainer.style.transformOrigin = (currentZoom > 100 ? 'top left' : 'top center');
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
            try { localStorage.setItem('mycomix_theme', next); } catch (e) {} // ★ (2026-11-02 09:04) 다른 화면은 이 값을 먼저 봄 — 여기서 바꾼 테마가 목록으로 돌아가면 되돌아가던 문제
        }
        
        // 키보드 단축키
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === '+' || e.key === '=') { e.preventDefault(); zoomIn(); }
                else if (e.key === '-') { e.preventDefault(); zoomOut(); }
                else if (e.key === 'p') { e.preventDefault(); printDocument(); }
            }
            if (e.key === 'Escape') (window.mcBackGo ? window.mcBackGo(backUrl, 'push') : (window.location.href = backUrl)); // ★ (2026-11-05 22:40) 바로 앞 기록이면 뒤로
            
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
// ★ (2026-10-30) 공통 함수 사용 — 전에는 없는 세션 키(last_activity)를 읽어 남은 시간이 늘 꽉 찬 값이었고, 영어 안내 문구도 빠졌음
render_auto_logout_script();
?>

<?php require_once __DIR__ . '/privacy_shield.php'; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
</body>
</html>