<?php
/**
 * myComix HWP/HWPX 통합 뷰어
 * 
 * @version 3.0
 * @date 2026-01-12
 * 
 * 지원 형식:
 * - HWP (한글 5.0, ohah/hwpjs legacy)
 * - HWPX (한글 2014+, JSZip + XML 파싱)
 * 
 * 사용 라이브러리:
 * - HWP: cfb.js, pako.js, hwpjs.js (ohah/hwpjs legacy)
 * - HWPX: JSZip
 */

require_once __DIR__ . "/bootstrap.php";
handle_timeout_popup();

$bidx = init_bidx();
$_branding = load_branding();

$hwp_settings = $hwp_viewer_settings ?? [
    'max_file_size' => 50 * 1024 * 1024,
    'default_zoom' => 100,
];

// ============================================================
// 파일 스트리밍 API (HWP/HWPX 공통)
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'stream') {
    
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        http_response_code(403);
        echo __('err_invalid_path');
        exit;
    }
    
    // HWP 또는 HWPX 파일 확인
    if (!file_exists($base_file) || !preg_match('/\.hwpx?$/i', $base_file)) {
        http_response_code(404);
        echo __('err_file_not_found');
        exit;
    }
    
    $filesize = filesize($base_file);
    
    if ($filesize > $hwp_settings['max_file_size']) {
        http_response_code(413);
        echo __('err_file_too_large');
        exit;
    }
    
    header('Content-Type: application/octet-stream');
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

// HWP 또는 HWPX 파일 확인
if (!file_exists($base_file) || !preg_match('/\.hwpx?$/i', $base_file)) {
    echo __('hwp_file_not_found');
    exit;
}

$filename = basename($base_file);
$is_hwpx = preg_match('/\.hwpx$/i', $base_file);
$file_ext = $is_hwpx ? 'hwpx' : 'hwp';
$title = preg_replace('/\.hwpx?$/i', '', $filename);
$filesize = filesize($base_file);

if ($filesize > $hwp_settings['max_file_size']) {
    echo __('err_file_too_large_max', round($hwp_settings['max_file_size'] / 1024 / 1024));
    exit;
}

$link_dir = dirname($getfile);
if ($link_dir === '.' || $link_dir === '/') {
    $link_dir = '';
}

$darkmode = $_COOKIE['darkmode'] ?? 'light';
$file_url = "hwp_viewer.php?action=stream&file=" . rawurlencode($getfile) . "&bidx=" . $bidx;

// 뒤로가기 URL - rawurlencode 사용
$back_url = "index.php?dir=" . rawurlencode($link_dir) . "&bidx=" . $bidx;
?>
<!DOCTYPE html>
<html lang="ko" data-theme="<?php echo h($darkmode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo h($title); ?> - <?php echo $is_hwpx ? 'HWPX' : 'HWP'; ?> <?php echo __h("viewer_label"); ?></title>
    
    <style>
        html{opacity:0;transition:opacity .15s ease-in}
        html.ready{opacity:1}
        html.leaving{opacity:0;transition:opacity .1s ease-out}
    </style>
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="shortcut icon" href="./favicon.ico">
    
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --header-bg: #f8f9fa;
            --border-color: #dee2e6;
            --btn-bg: #e9ecef;
            --btn-hover: #dee2e6;
            --viewer-bg: #f5f5f5;
        }
        
        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --text-color: #e0e0e0;
            --header-bg: #2d2d2d;
            --border-color: #404040;
            --btn-bg: #3d3d3d;
            --btn-hover: #4d4d4d;
            --viewer-bg: #252525;
        }
        
        * { margin: 0; padding: 0; }
        
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
            display: inline-block;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            background: <?php echo $is_hwpx ? '#28a745' : '#007bff'; ?>;
            color: white;
            margin-left: 8px;
            vertical-align: middle;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 5px;
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
            color: var(--text-color);
            border-radius: 4px;
            font-size: 16px;
        }
        
        .zoom-controls button:hover { background: var(--btn-hover); }
        .zoom-value { font-size: 12px; min-width: 40px; text-align: center; }
        
        .viewer-container {
            flex: 1;
            overflow: auto;
            background: var(--viewer-bg);
            padding: 20px;
        }
        
        #hwp-viewer {
            background: var(--bg-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20mm;
            margin: 0 auto;
            max-width: 250mm;
            min-height: 297mm;
            transform-origin: top center;
            line-height: 1.8;
            word-break: keep-all;
            overflow-wrap: break-word;
        }
        
        [data-theme="dark"] #hwp-viewer {
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        /* HWP/HWPX 컨텐츠 스타일 */
        #hwp-viewer p { margin: 0.5em 0; }
        #hwp-viewer table { border-collapse: collapse; width: 100%; margin: 1em 0; }
        #hwp-viewer td, #hwp-viewer th { border: 1px solid var(--border-color); padding: 8px; vertical-align: top; }
        #hwp-viewer img { max-width: 100%; height: auto; display: inline-block; margin: 0.5em 0; }
        #hwp-viewer .hwpx-section { margin-bottom: 2em; }
        #hwp-viewer .hwpx-para { margin: 0.3em 0; min-height: 1em; }
        #hwp-viewer .hwpx-table { margin: 1em 0; }
        
        /* hwpjs 스타일 오버라이드 */
        #hwp-viewer .hwpjs { line-height: 1.8; }
        #hwp-viewer .hwpjs p { margin: 0.3em 0; }
        
        /* hwpjs 이미지 강제 스타일 */
        #hwp-viewer img {
            max-width: 100% !important;
            width: auto !important;
            height: auto !important;
            display: block !important;
            margin: 10px auto !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 10 !important;
            min-width: 50px !important;
            min-height: 50px !important;
        }
        
        /* hwpjs shape 컨테이너 */
        #hwp-viewer [class*="shape"], 
        #hwp-viewer [class*="image"],
        #hwp-viewer [class*="pic"],
        #hwp-viewer [class*="container"],
        #hwp-viewer div[style*="position: absolute"],
        #hwp-viewer div[style*="position:absolute"] {
            position: relative !important;
            width: auto !important;
            height: auto !important;
            max-width: 100% !important;
            overflow: visible !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            transform: none !important;
            left: auto !important;
            top: auto !important;
        }
        
        #hwp-viewer *[style*="width:"][style*="height:"] {
            width: auto !important;
            height: auto !important;
            max-width: 100% !important;
            overflow: visible !important;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .loading-text { color: #fff; margin-top: 15px; font-size: 14px; }
        
        .error-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 20px;
            text-align: center;
        }
        
        .error-icon { font-size: 48px; margin-bottom: 15px; opacity: 0.5; }
        .error-message { font-size: 16px; margin-bottom: 20px; opacity: 0.8; max-width: 400px; }
        .error-btn {
            padding: 10px 20px;
            background: var(--btn-bg);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
        }
        .error-btn:hover { background: var(--btn-hover); }
        
        .info-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        [data-theme="dark"] .info-notice {
            background: #3d3d00;
            border-color: #666600;
            color: #ffcc00;
        }
        
        @media (max-width: 768px) {
            .header { padding: 8px 10px; flex-wrap: wrap; }
            .filename { font-size: 12px; }
            .file-badge { display: none; }
            .zoom-controls { display: none; }
            .viewer-container { padding: 5px; }
            #hwp-viewer { 
                box-sizing: border-box; 
                padding: 4mm; 
                max-width: 100%; 
                min-height: auto; 
                overflow-x: hidden;
                word-break: break-word;
                overflow-wrap: break-word;
            }
            #hwp-viewer * { max-width: 100% !important; }
            #hwp-viewer table { display: block; overflow-x: auto; }
            #hwp-viewer img { height: auto !important; }
        }
        
        @media print {
            .header, .info-notice { display: none !important; }
            .viewer-container { padding: 0; overflow: visible; }
            #hwp-viewer { box-shadow: none; transform: none !important; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <a href="javascript:void(0)" class="back-btn" title="<?php echo __h('js_go_back'); ?>" id="backBtn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </a>
            <span class="filename" title="<?php echo h($filename); ?>">
                <?php echo h($filename); ?>
                <span class="file-badge"><?php echo strtoupper($file_ext); ?></span>
                <?php render_lang_badge('sm-hwp'); ?>
            </span>
        </div>
        
        <div class="header-right">
            <div class="zoom-controls">
                <button onclick="zoomOut()" title="<?php echo __h('hwp_zoom_out'); ?>">−</button>
                <span class="zoom-value" id="zoomValue">100%</span>
                <button onclick="zoomIn()" title="<?php echo __h('hwp_zoom_in'); ?>">+</button>
            </div>
            
            <button class="toolbar-btn" onclick="printDocument()" title="<?php echo __h('hwp_print'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
            </button>
            
            <button class="toolbar-btn" onclick="toggleDarkMode()" title="<?php echo __h('hwp_toggle_dark'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
            </button>
            
            <a href="<?php echo h($file_url); ?>" download="<?php echo h($filename); ?>" class="toolbar-btn" title="<?php echo __h('js_download'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
            </a>
        </div>
    </header>
    
    <div class="viewer-container" id="viewerContainer">
        <div id="hwp-viewer"></div>
    </div>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text"><?php echo ($is_hwpx ? 'HWPX' : 'HWP') . ' ' . __('js_loading'); ?></div>
    </div>
    
<?php
// 뷰어 JS용 번역 문자열 출력
render_viewer_i18n([
    'hwp_unsupported' => 'js_hwp_unsupported',
    'hwp_save_hwpx' => 'js_hwp_save_hwpx',
    'hwp_load_fail' => 'js_hwp_load_fail',
    'hwpx_load_fail' => 'js_hwpx_load_fail',
    'hwp_partial_parse' => 'js_hwp_partial_parse',
]);
?>

<?php if ($is_hwpx): ?>
    <!-- HWPX용: JSZip -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<?php else: ?>
    <!-- HWP용: ohah/hwpjs legacy - v2 console suppressed -->
    <script>
        // hwpjs 라이브러리 로그 억제
        const _originalConsole = {
            log: console.log,
            warn: console.warn,
            error: console.error
        };
        console.log = console.warn = function(){};
    </script>
    <script src="https://cdn.jsdelivr.net/gh/ohah/hwpjs@main/legacy/cfb.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/ohah/hwpjs@main/legacy/pako.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/ohah/hwpjs@main/legacy/hwpjs.js"></script>
    <script>
        // console 복원하지 않음 - hwpjs 사용 후에도 로그 억제 유지
    </script>
<?php endif; ?>
    
    <script>
        document.documentElement.classList.add('ready');
        
        // PHP에서 안전하게 JSON으로 전달
        const fileUrl = <?php echo json_encode($file_url); ?>;
        const backUrl = <?php echo json_encode($back_url); ?>;
        const fileName = <?php echo json_encode($filename); ?>;
        const isHwpx = <?php echo $is_hwpx ? 'true' : 'false'; ?>;
        const viewerEl = document.getElementById('hwp-viewer');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const viewerContainer = document.getElementById('viewerContainer');
        
        let currentZoom = <?php echo $hwp_settings['default_zoom']; ?>;
        
        // 뒤로가기 버튼 - JavaScript로 처리
        document.getElementById('backBtn').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = backUrl;
        });
        
<?php if ($is_hwpx): ?>
        // ============================================================
        // HWPX 파일 로드 (ZIP + XML)
        // ============================================================
        async function loadDocument() {
            try {
                const response = await fetch(fileUrl, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error(_vi18n.http_error + ' ' + response.status + ')');
                }
                
                const arrayBuffer = await response.arrayBuffer();
                const zip = await JSZip.loadAsync(arrayBuffer);
                
                // 이미지 추출 (BinData 폴더)
                const images = {};
                const imageFiles = [];
                
                zip.forEach((relativePath, file) => {
                    if (!file.dir && relativePath.toLowerCase().startsWith('bindata/')) {
                        imageFiles.push({ path: relativePath, file: file });
                    }
                });
                
                for (const item of imageFiles) {
                    try {
                        const data = await item.file.async('base64');
                        const filename = item.path.split('/').pop();
                        const ext = filename.split('.').pop().toLowerCase();
                        let mimeType = 'image/png';
                        if (ext === 'jpg' || ext === 'jpeg') mimeType = 'image/jpeg';
                        else if (ext === 'gif') mimeType = 'image/gif';
                        else if (ext === 'bmp') mimeType = 'image/bmp';
                        else if (ext === 'emf' || ext === 'wmf') mimeType = 'image/' + ext;
                        
                        // 여러 방식으로 매핑
                        images[filename] = `data:${mimeType};base64,${data}`;
                        images[filename.toLowerCase()] = `data:${mimeType};base64,${data}`;
                        // 확장자 제거한 이름으로도 매핑
                        const baseName = filename.replace(/\.[^.]+$/, '');
                        images[baseName] = `data:${mimeType};base64,${data}`;
                    } catch (e) {
                        // 이미지 로드 실패 무시
                    }
                }
                
                // section XML 파일들 찾기
                const sections = [];
                zip.forEach((relativePath, file) => {
                    if (relativePath.match(/Contents\/section\d+\.xml$/i)) {
                        sections.push({ path: relativePath, file: file });
                    }
                });
                
                // section 순서대로 정렬
                sections.sort((a, b) => {
                    const numA = parseInt(a.path.match(/section(\d+)/i)[1]);
                    const numB = parseInt(b.path.match(/section(\d+)/i)[1]);
                    return numA - numB;
                });
                
                let html = '';
                
                if (sections.length === 0) {
                    // Contents 폴더가 없는 경우 다른 구조 시도
                    zip.forEach((relativePath, file) => {
                        if (relativePath.match(/section\d*\.xml$/i)) {
                            sections.push({ path: relativePath, file: file });
                        }
                    });
                    sections.sort((a, b) => {
                        const numA = parseInt((a.path.match(/(\d+)/) || [0,0])[1]);
                        const numB = parseInt((b.path.match(/(\d+)/) || [0,0])[1]);
                        return numA - numB;
                    });
                }
                
                for (const section of sections) {
                    const xmlContent = await section.file.async('text');
                    html += parseHwpxSection(xmlContent, images);
                }
                
                if (!html.trim()) {
                    // XML 파싱 실패 시 텍스트 추출 시도
                    html = '<div class="info-notice">' + _vi18n.hwp_partial_parse + '</div>';
                    for (const section of sections) {
                        const xmlContent = await section.file.async('text');
                        const textOnly = xmlContent.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                        if (textOnly) {
                            html += '<p>' + escapeHtml(textOnly) + '</p>';
                        }
                    }
                }
                
                viewerEl.innerHTML = html;
                loadingOverlay.style.display = 'none';
                
            } catch (error) {
                // "Cannot read properties of undefined" 오류 감지
                const errMsg = error.message || '';
                if (errMsg.includes('Cannot read properties of undefined') || 
                    errMsg.includes('Cannot read property') ||
                    errMsg.includes('undefined') && errMsg.includes('reading')) {
                    showError('', true);
                } else {
                    showError(errMsg || _vi18n.hwpx_load_fail);
                }
            }
        }
        
        // HWPX XML 파싱
        function parseHwpxSection(xmlContent, images) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(xmlContent, 'text/xml');
            
            // 파싱 에러 체크
            const parseError = doc.querySelector('parsererror');
            
            let html = '<div class="hwpx-section">';
            
            // 네임스페이스 무시하고 모든 p 요소 찾기
            const allElements = doc.getElementsByTagName('*');
            const paragraphs = [];
            
            for (let i = 0; i < allElements.length; i++) {
                const el = allElements[i];
                const localName = el.localName || el.nodeName.split(':').pop();
                if (localName === 'p' || localName === 'P') {
                    paragraphs.push(el);
                }
            }
            
            paragraphs.forEach(para => {
                let paraText = '';
                let paraImages = [];
                
                // 재귀적으로 텍스트와 이미지 수집
                function extractContent(node) {
                    if (node.nodeType === Node.TEXT_NODE) {
                        paraText += node.textContent;
                    } else if (node.nodeType === Node.ELEMENT_NODE) {
                        const localName = node.localName || node.nodeName.split(':').pop();
                        
                        // 이미지 처리
                        if (localName === 'img' || localName === 'pic' || localName === 'picture') {
                            // binId 또는 binaryItemIDRef 속성 찾기
                            const binId = node.getAttribute('binaryItemIDRef') || 
                                         node.getAttribute('binId') ||
                                         node.getAttribute('hp:binaryItemIDRef');
                            if (binId && images[binId]) {
                                paraImages.push(images[binId]);
                            }
                            // 하위 요소에서도 찾기
                            const binItem = node.querySelector('[binaryItemIDRef], [binId]');
                            if (binItem) {
                                const ref = binItem.getAttribute('binaryItemIDRef') || binItem.getAttribute('binId');
                                if (ref && images[ref]) {
                                    paraImages.push(images[ref]);
                                }
                            }
                        }
                        
                        // 자식 노드 탐색
                        for (let i = 0; i < node.childNodes.length; i++) {
                            extractContent(node.childNodes[i]);
                        }
                    }
                }
                
                extractContent(para);
                
                // HTML 생성
                let paraHtml = '<div class="hwpx-para">';
                if (paraText.trim()) {
                    paraHtml += escapeHtml(paraText);
                }
                paraImages.forEach(imgSrc => {
                    paraHtml += `<img src="${imgSrc}" alt="" data-i18n-alt="image">`;
                });
                if (!paraText.trim() && paraImages.length === 0) {
                    paraHtml += '&nbsp;'; // 빈 문단
                }
                paraHtml += '</div>';
                html += paraHtml;
            });
            
            // 테이블 처리
            for (let i = 0; i < allElements.length; i++) {
                const el = allElements[i];
                const localName = el.localName || el.nodeName.split(':').pop();
                if (localName === 'tbl' || localName === 'table') {
                    html += parseTable(el);
                }
            }
            
            html += '</div>';
            return html;
        }
        
        function parseTable(tableEl) {
            let html = '<table class="hwpx-table"><tbody>';
            const allElements = tableEl.getElementsByTagName('*');
            let currentRow = null;
            
            for (let i = 0; i < allElements.length; i++) {
                const el = allElements[i];
                const localName = el.localName || el.nodeName.split(':').pop();
                
                if (localName === 'tr') {
                    if (currentRow) html += '</tr>';
                    html += '<tr>';
                    currentRow = el;
                } else if (localName === 'tc' || localName === 'td' || localName === 'cell') {
                    html += '<td>' + escapeHtml(el.textContent || '') + '</td>';
                }
            }
            if (currentRow) html += '</tr>';
            
            html += '</tbody></table>';
            return html;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
<?php else: ?>
        // ============================================================
        // HWP 파일 로드 (ohah/hwpjs + CFB 직접 이미지 추출)
        // ============================================================
        
        // CFB에서 BinData 이미지 직접 추출
        function extractImagesFromCFB(arrayBuffer) {
            const images = {};
            try {
                const cfb = CFB.read(arrayBuffer, { type: 'array' });
                
                // 모든 파일 탐색
                cfb.FileIndex.forEach((entry, idx) => {
                    const fullPath = cfb.FullPaths[idx] || '';
                    
                    // BinData 폴더 내 파일 찾기
                    if (fullPath.toLowerCase().includes('bindata') && entry.content && entry.content.length > 0) {
                        let content = new Uint8Array(entry.content);
                        let mimeType = null;
                        
                        // 1. 먼저 원본 데이터의 시그니처 확인
                        mimeType = detectImageType(content);
                        
                        // 2. 이미지가 아니면 다양한 압축 해제 시도
                        if (!mimeType) {
                            // zlib 압축 (78 xx)
                            if (content[0] === 0x78) {
                                try {
                                    const decompressed = pako.inflate(content);
                                    mimeType = detectImageType(decompressed);
                                    if (mimeType) content = decompressed;
                                } catch (e) {}
                            }
                            
                            // deflate raw 압축 (헤더 없음) - HWP에서 주로 사용
                            if (!mimeType) {
                                try {
                                    const decompressed = pako.inflateRaw(content);
                                    mimeType = detectImageType(decompressed);
                                    if (mimeType) content = decompressed;
                                } catch (e) {}
                            }
                            
                            // 오프셋 시도 (앞부분에 헤더가 있을 수 있음)
                            if (!mimeType) {
                                for (let offset = 1; offset <= 4; offset++) {
                                    try {
                                        const sliced = content.slice(offset);
                                        const decompressed = pako.inflateRaw(sliced);
                                        mimeType = detectImageType(decompressed);
                                        if (mimeType) {
                                            content = decompressed;
                                            break;
                                        }
                                    } catch (e) {}
                                }
                            }
                        }
                        
                        if (mimeType) {
                            const base64 = uint8ArrayToBase64(content);
                            const binMatch = fullPath.match(/BIN(\d+)/i);
                            const binId = binMatch ? binMatch[1] : (idx + 1).toString();
                            images[binId] = `data:${mimeType};base64,${base64}`;
                        }
                    }
                });
            } catch (e) {}
            return images;
        }
        
        // 이미지 타입 감지
        function detectImageType(data) {
            if (!data || data.length < 4) return null;
            
            // PNG: 89 50 4E 47
            if (data[0] === 0x89 && data[1] === 0x50 && data[2] === 0x4E && data[3] === 0x47) {
                return 'image/png';
            }
            // JPEG: FF D8 FF
            if (data[0] === 0xFF && data[1] === 0xD8 && data[2] === 0xFF) {
                return 'image/jpeg';
            }
            // GIF: 47 49 46 38
            if (data[0] === 0x47 && data[1] === 0x49 && data[2] === 0x46) {
                return 'image/gif';
            }
            // BMP: 42 4D
            if (data[0] === 0x42 && data[1] === 0x4D) {
                return 'image/bmp';
            }
            // WEBP: 52 49 46 46 ... 57 45 42 50
            if (data[0] === 0x52 && data[1] === 0x49 && data[2] === 0x46 && data[3] === 0x46) {
                return 'image/webp';
            }
            return null;
        }
        
        // Uint8Array를 Base64로 변환 (대용량 지원)
        function uint8ArrayToBase64(uint8Array) {
            let binary = '';
            const chunkSize = 32768;
            for (let i = 0; i < uint8Array.length; i += chunkSize) {
                const chunk = uint8Array.subarray(i, Math.min(i + chunkSize, uint8Array.length));
                binary += String.fromCharCode.apply(null, chunk);
            }
            return btoa(binary);
        }
        
        // 이미지 플레이스홀더를 실제 이미지로 교체
        function replaceImagePlaceholders(html, images) {
            // hwpjs가 생성하는 이미지 참조 패턴 처리
            
            const binIds = Object.keys(images).sort((a, b) => parseInt(a) - parseInt(b));
            if (binIds.length === 0) return html;
            
            // 1. blob: URL을 가진 img 태그를 찾아서 순서대로 교체
            let imgIndex = 0;
            html = html.replace(/<img([^>]*?)src=["']blob:[^"']+["']([^>]*?)>/gi, (match, before, after) => {
                if (imgIndex < binIds.length) {
                    const binId = binIds[imgIndex];
                    imgIndex++;
                    return `<img${before}src="${images[binId]}"${after}>`;
                }
                return match;
            });
            
            // 2. BIN0001 형식의 참조도 교체
            binIds.forEach(binId => {
                const patterns = [
                    new RegExp(`BIN${binId.padStart(4, '0')}`, 'gi'),
                    new RegExp(`bindata/${binId}`, 'gi'),
                    new RegExp(`data-binid=["']${binId}["']`, 'gi'),
                ];
                patterns.forEach(pattern => {
                    if (html.match(pattern)) {
                        html = html.replace(pattern, `src="${images[binId]}"`);
                    }
                });
            });
            
            return html;
        }
        
        async function loadDocument() {
            try {
                const response = await fetch(fileUrl, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error(_vi18n.http_error + ' ' + response.status + ')');
                }
                
                const arrayBuffer = await response.arrayBuffer();
                
                // 1. CFB에서 이미지 먼저 추출
                let extractedImages = {};
                try {
                    extractedImages = extractImagesFromCFB(new Uint8Array(arrayBuffer));
                } catch (e) {
                    console.warn('이미지 추출 실패:', e);
                }
                
                // 2. hwpjs로 HTML 변환
                let hwp, html;
                try {
                    hwp = new hwpjs(arrayBuffer);
                    html = hwp.getHtml();
                } catch (hwpError) {
                    // hwpjs 파싱 오류 - 지원되지 않는 형식
                    console.error('hwpjs 오류:', hwpError);
                    showError('', true);
                    return;
                }
                
                // HTML이 비어있거나 유효하지 않은 경우
                if (!html || html.trim() === '' || html === '<div></div>') {
                    showError('', true);
                    return;
                }
                
                // 3. 이미지 플레이스홀더 교체
                html = replaceImagePlaceholders(html, extractedImages);
                
                // 4. 이미지 처리
                const extractedCount = Object.keys(extractedImages).length;
                
                viewerEl.innerHTML = html;
                
                // 이미지 스타일 강제 적용
                if (extractedCount > 0) {
                    viewerEl.querySelectorAll('img').forEach((img) => {
                        img.style.cssText = 'max-width: 100% !important; width: auto !important; height: auto !important; display: block !important; margin: 10px auto !important; visibility: visible !important; opacity: 1 !important; position: relative !important; min-width: 100px !important; min-height: 100px !important;';
                        img.removeAttribute('width');
                        img.removeAttribute('height');
                        
                        // 부모 요소들의 position: absolute 제거
                        let parent = img.parentElement;
                        for (let i = 0; i < 15 && parent && parent !== viewerEl; i++) {
                            const computedStyle = window.getComputedStyle(parent);
                            if (computedStyle.position === 'absolute') {
                                parent.style.position = 'relative';
                                parent.style.left = 'auto';
                                parent.style.top = 'auto';
                            }
                            parent.style.width = 'auto';
                            parent.style.height = 'auto';
                            parent.style.maxWidth = '100%';
                            parent.style.overflow = 'visible';
                            parent.style.visibility = 'visible';
                            parent.style.opacity = '1';
                            parent.style.display = 'block';
                            parent = parent.parentElement;
                        }
                    });
                }
                
                loadingOverlay.style.display = 'none';
                
            } catch (error) {
                // "Cannot read properties of undefined" 오류 감지
                const errMsg = error.message || '';
                if (errMsg.includes('Cannot read properties of undefined') || 
                    errMsg.includes('Cannot read property') ||
                    errMsg.includes('undefined') && errMsg.includes('reading')) {
                    showError('', true);
                } else {
                    showError(errMsg || _vi18n.hwp_load_fail);
                }
            }
        }
<?php endif; ?>
        
        function showError(message, isUnsupportedFormat = false) {
            loadingOverlay.style.display = 'none';
            
            if (isUnsupportedFormat) {
                viewerContainer.innerHTML = `
                    <div class="error-container">
                        <div class="error-icon">📄</div>
                        <div class="error-message">${_vi18n.hwp_unsupported}</div>
                        <div style="color: #888; font-size: 13px; margin-top: 8px;">${_vi18n.hwp_save_hwpx}</div>
                        <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: center; flex-wrap: wrap;">
                            <a href="${fileUrl}" download="${fileName}" class="error-btn" style="background: #28a745;">${_vi18n.download}</a>
                            <a href="javascript:void(0)" class="error-btn" onclick="window.location.href=backUrl">${_vi18n.close}</a>
                        </div>
                    </div>
                `;
            } else {
                viewerContainer.innerHTML = `
                    <div class="error-container">
                        <div class="error-icon">📄</div>
                        <div class="error-message">${message}</div>
                        <a href="javascript:void(0)" class="error-btn" onclick="window.location.href=backUrl">${_vi18n.go_back}</a>
                    </div>
                `;
            }
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
            viewerEl.style.transform = `scale(${currentZoom / 100})`;
            document.getElementById('zoomValue').textContent = currentZoom + '%';
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
        
        // 시작
        loadDocument();
    </script>

<?php
$timeout = $auto_logout_settings['timeout'] ?? 1800;
$remaining = $timeout - (time() - ($_SESSION['last_activity'] ?? time()));
if ($remaining < 0) $remaining = 0;

$_current_page = basename($_SERVER['SCRIPT_FILENAME']);
$_auto_logout_pages = $auto_logout_settings['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'hwp_viewer.php', 'bookmark.php'];
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