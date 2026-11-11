<?php
/**
 * myComix rhwp(WASM) HWP/HWPX 뷰어 — ★ (2026-10-31) 신규, 보기·인쇄·내려받기만(편집 기능 없음)
 *
 * - 엔진: rhwp @rhwp/core 0.9.0 (Rust + WebAssembly, MIT) — https://github.com/edwardkim/rhwp  ★ (2026-11-09 08:44) 0.8.7 → 0.9.0(펜닐 요청, 읽기 뷰어에 필요한 rhwp.js·rhwp_bg.wasm 만 교체 — 쓰는 함수 HwpDocument·pageCount·renderPageSvg·version 그대로)
 *   FileStation v5.8.12 의 rhwp_viewer.php 를 바탕으로 myComix 에 맞춤(파일 확인·전송은 hwp_viewer.php 와 같은 규칙)
 * - 파일 받기: hwp_viewer.php?action=stream (이미 폴더 권한·경로 확인·크기 제한·Range 처리를 함) — 전송 코드를 따로 두지 않음
 * - 자산: js/rhwp/rhwp.js · rhwp_bg.wasm(npm @rhwp/core 0.9.0 원본) · fonts/(오픈 라이선스 글꼴 10개 — FONTS.md)
 * @rhwp_version 0.9.0
 */

require_once __DIR__ . "/bootstrap.php";
handle_timeout_popup();

$bidx = init_bidx();

// 보안: 폴더 권한 확인(hwp_viewer.php 와 같음)
mc_file_param_guard(false);
$_branding = load_branding();

$hwp_settings = $hwp_viewer_settings ?? [
    'max_file_size' => 50 * 1024 * 1024,
    'default_zoom' => 100,
];

$getfile = decode_file_param($_GET['file'] ?? '');
if (!$getfile) {
    header("Location: ./");
    exit;
}
$base_file = validate_file_path($getfile, $base_dir);
if ($base_file === false) {
    echo __h('err_invalid_path');
    exit;
}
if (!file_exists($base_file) || !preg_match('/\.hwpx?$/i', $base_file)) {
    echo __h('hwp_file_not_found');
    exit;
}
if (filesize($base_file) > $hwp_settings['max_file_size']) {
    echo __h('err_file_too_large_max', round($hwp_settings['max_file_size'] / 1024 / 1024));
    exit;
}

$filename = basename($base_file);
$is_hwpx = (bool)preg_match('/\.hwpx$/i', $base_file);
$link_dir = dirname($getfile);
if ($link_dir === '.' || $link_dir === '/') $link_dir = '';

$darkmode = (($_COOKIE['darkmode'] ?? 'light') === 'dark') ? 'dark' : 'light';
$file_url = "hwp_viewer.php?action=stream&file=" . rawurlencode($getfile) . "&bidx=" . $bidx;      // 파일 받기(기존 HWP 뷰어의 전송)
$basic_url = "hwp_viewer.php?file=" . rawurlencode($getfile) . "&bidx=" . $bidx;                  // 기본 뷰어로
$back_url = "index.php?dir=" . rawurlencode($link_dir) . "&bidx=" . $bidx;
$rhwp_v = function ($f) { return (string)(@filemtime(__DIR__ . '/js/rhwp/' . $f) ?: '1'); };
?>
<!DOCTYPE html>
<html lang="<?php echo get_html_lang(); ?>" data-theme="<?php echo h($darkmode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo h(preg_replace('/\.hwpx?$/i', '', $filename)); ?> - rhwp <?php echo __h("viewer_label"); ?></title>
    <?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
<?php if (function_exists('mc_back_origin_viewer_js')) echo mc_back_origin_viewer_js(); /* ★ (2026-11-08 fix84) 뒤로가기 = 들어온 곳(hwp 뷰어와 같은 방식) */ ?>
    <style>
        :root { --hd-bg:#fff; --hd-bd:#ddd; --hd-tx:#333; --btn-bg:#fff; --btn-bd:#ccc; --btn-hv:#f0f0f0; --body-bg:#e8e8e8; }
        [data-theme="dark"] { --hd-bg:#2d2d2d; --hd-bd:#404040; --hd-tx:#e0e0e0; --btn-bg:#3d3d3d; --btn-bd:#555; --btn-hv:#4d4d4d; --body-bg:#1e1e1e; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Malgun Gothic", sans-serif; background:var(--body-bg); color:var(--hd-tx); height:100vh; display:flex; flex-direction:column; overflow:hidden; }
        .viewer-header { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 12px; background:var(--hd-bg); border-bottom:1px solid var(--hd-bd); min-height:48px; flex-shrink:0; }
        .viewer-header .title { font-size:14px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; flex:1 1 auto; }
        .viewer-header .controls { display:flex; align-items:center; gap:6px; flex-shrink:0; }
        .viewer-header button, .viewer-header .switch-btn, .viewer-header select {
            height:32px; padding:0 10px; border:1px solid var(--btn-bd); border-radius:6px; background:var(--btn-bg); color:var(--hd-tx);
            cursor:pointer; font-size:13px; display:inline-flex; align-items:center; gap:4px; text-decoration:none; white-space:nowrap; }
        .viewer-header button:hover, .viewer-header .switch-btn:hover { background:var(--btn-hv); }
        .viewer-header button:disabled { opacity:.4; cursor:default; }
        .viewer-header .back-btn { padding:0 8px; flex-shrink:0; } /* ★ (2026-11-08 fix84) 왼쪽 ← 뒤로가기(hwp 뷰어와 같게) */
        .viewer-header .page-info { font-size:13px; opacity:.75; min-width:56px; text-align:center; }
        .viewer-header .ver { font-size:10px; opacity:.55; }
        .viewer-body { flex:1; overflow:auto; padding:20px; display:flex; flex-direction:column; align-items:center; gap:16px; -webkit-overflow-scrolling:touch; }
        .page-container { background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.15); max-width:100%; }
        .page-container svg { display:block; height:auto; }
        .loading-overlay { position:fixed; inset:0; background:rgba(255,255,255,.92); display:flex; flex-direction:column; align-items:center; justify-content:center; z-index:1000; }
        [data-theme="dark"] .loading-overlay { background:rgba(20,20,20,.92); }
        .loading-overlay.hidden { display:none; }
        .spinner { width:40px; height:40px; border:4px solid #ddd; border-top-color:#4a90d9; border-radius:50%; animation:spin .8s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        .loading-text { margin-top:12px; font-size:14px; opacity:.75; }
        .error-msg { padding:40px 20px; text-align:center; color:#d32f2f; font-size:15px; line-height:1.6; }
        .error-msg a { color:#1a73e8; }
        @media (max-width:768px) {
            .viewer-header { padding:4px 8px; min-height:40px; gap:4px; }
            .viewer-header .title { font-size:12px; }
            .viewer-header button, .viewer-header .switch-btn, .viewer-header select { height:28px; padding:0 6px; font-size:11px; }
            /* 모바일: 쪽 이동·버전·확대 선택은 숨김(손가락 스크롤·두 손가락 확대) */
            .viewer-header #btn-prev, .viewer-header #btn-next, .viewer-header .page-info, .viewer-header .ver, .viewer-header #zoom-select { display:none !important; }
            .viewer-body { padding:0; gap:2px; background:#fff; }
            .page-container { box-shadow:none; border-bottom:1px solid #e0e0e0; width:100%; }
            .page-container svg { width:100% !important; height:auto !important; }
        }
        @media print {
            .viewer-header, .loading-overlay { display:none !important; }
            html, body { background:#fff; overflow:visible; height:auto; }
            .viewer-body { padding:0; gap:0; background:#fff; overflow:visible; height:auto; }
            .page-container { box-shadow:none; page-break-after:always; break-after:page; }
            .page-container:last-child { page-break-after:auto; break-after:auto; }
            .page-container svg { width:100% !important; height:auto !important; max-width:100%; }
            @page { margin:10mm; }
        }
    </style>
    <script>
    // rhwp(WASM)가 부르는 글자 폭 측정 함수
    (function () {
        var ctx = null, lastFont = '';
        globalThis.measureTextWidth = function (font, text) {
            if (!ctx) ctx = document.createElement('canvas').getContext('2d');
            if (font !== lastFont) { ctx.font = font; lastFont = font; }
            return ctx.measureText(text).width;
        };
    })();
    </script>
</head>
<body>
    <div class="viewer-header">
        <a class="switch-btn back-btn" href="<?php echo h($back_url); ?>" data-mc-back title="<?php echo __h('js_go_back'); ?>" aria-label="<?php echo __h('js_go_back'); ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></a><?php /* ★ (2026-11-08 fix84) 뒤로가기 — 들어온 곳(폴더·즐겨찾기·북마크 등)으로, 바로 앞 기록이면 브라우저 뒤로(function.php mc_back_origin_viewer_js) */ ?>
        <span class="title" title="<?php echo h($filename); ?>"><?php echo h($filename); ?></span>
        <div class="controls">
            <?php if (function_exists('mc_doc_cover_btn')) echo mc_doc_cover_btn($getfile, $bidx ?? 0, '', true); /* ★ (2026-11-08 fix80) 이 책 표지를 폴더 표지로(관리자) / ★ (2026-11-10 14:55 fix99) 쪽이 그림이라 '이 쪽을 파일·폴더 표지로' */ ?>
            <button type="button" id="btn-prev" disabled title="<?php echo __h('rhwp_prev'); ?>" aria-label="<?php echo __h('rhwp_prev'); ?>">◀</button>
            <span class="page-info" id="page-info">- / -</span>
            <button type="button" id="btn-next" disabled title="<?php echo __h('rhwp_next'); ?>" aria-label="<?php echo __h('rhwp_next'); ?>">▶</button>
            <span class="ver" id="rhwp-version"></span>
            <select id="zoom-select" aria-label="<?php echo __h('rhwp_zoom'); ?>">
                <option value="fit"><?php echo __h('rhwp_fit'); ?></option>
                <option value="0.5">50%</option>
                <option value="0.75">75%</option>
                <option value="1">100%</option>
                <option value="1.25">125%</option>
                <option value="1.5">150%</option>
                <option value="2">200%</option>
            </select>
            <a class="switch-btn" href="<?php echo h($basic_url); ?>" title="<?php echo __h('rhwp_basic_btn'); ?>">🔄 <?php echo __h('rhwp_basic_btn'); ?></a>
            <a class="switch-btn" href="<?php echo h($file_url); ?>" download="<?php echo h($filename); ?>" title="<?php echo __h('hwp_download'); ?>" aria-label="<?php echo __h('hwp_download'); ?>">📥</a>
            <button type="button" id="btn-print" title="<?php echo __h('hwp_print'); ?> (Ctrl+P)" aria-label="<?php echo __h('hwp_print'); ?>">🖨️</button>
            <button type="button" id="btn-close" title="<?php echo __h('hwp_back'); ?>" aria-label="<?php echo __h('hwp_back'); ?>">✕</button>
        </div>
    </div>

    <div class="viewer-body" id="viewer-body"></div>

    <div class="loading-overlay" id="loading">
        <div class="spinner"></div>
        <div class="loading-text" id="loading-text"><?php echo __h('rhwp_loading_engine'); ?></div>
    </div>

    <script>
    // 한글 문서에 자주 쓰는 글꼴 → 비슷한 오픈 라이선스 글꼴(FileStation 과 같은 대응표). 함초롬은 공개 웹 글꼴(jsdelivr) 사용
    (function () {
        var HB = 'https://cdn.jsdelivr.net/gh/projectnoonnu/noonfonts_2104@1.0/HANBatang.woff';
        var HD = 'https://cdn.jsdelivr.net/gh/projectnoonnu/noonfonts_four@1.0/HCRDotum.woff';
        var base = 'js/rhwp/fonts/';
        var fonts = [
            ['함초롬돋움', HD, 'woff'], ['함초롬바탕', HB, 'woff'], ['함초롱돋움', HD, 'woff'], ['함초롱바탕', HB, 'woff'],
            ['한컴돋움', HD, 'woff'], ['한컴바탕', HB, 'woff'], ['새돋움', HD, 'woff'], ['새바탕', HB, 'woff'],
            ['HY헤드라인M', base + 'NotoSansKR-Bold.woff2'], ['HY견고딕', base + 'NotoSansKR-Bold.woff2'], ['HY그래픽', base + 'NotoSansKR-Regular.woff2'],
            ['HY견명조', base + 'NotoSerifKR-Bold.woff2'], ['HY신명조', base + 'NotoSerifKR-Regular.woff2'], ['HY중고딕', base + 'NotoSansKR-Regular.woff2'],
            ['Malgun Gothic', base + 'Pretendard-Regular.woff2'], ['맑은 고딕', base + 'Pretendard-Regular.woff2'],
            ['돋움', base + 'NotoSansKR-Regular.woff2'], ['돋움체', base + 'NotoSansKR-Regular.woff2'], ['굴림', base + 'NotoSansKR-Regular.woff2'],
            ['굴림체', base + 'D2Coding-Regular.woff2'], ['바탕', base + 'NotoSerifKR-Regular.woff2'], ['바탕체', base + 'D2Coding-Regular.woff2'],
            ['궁서', base + 'GowunBatang-Regular.woff2'], ['궁서체', base + 'GowunBatang-Regular.woff2'],
            ['나눔고딕', base + 'NanumGothic-Regular.woff2'], ['나눔명조', base + 'NanumMyeongjo-Regular.woff2'], ['나눔고딕코딩', base + 'NanumGothicCoding-Regular.woff2']
        ];
        if (!window.FontFace || !document.fonts) return;
        fonts.forEach(function (f) {
            try { new FontFace(f[0], "url('" + f[1] + "') format('" + (f[2] || 'woff2') + "')").load().then(function (l) { document.fonts.add(l); }).catch(function () {}); } catch (e) {}
        });
    })();
    // rhwp 내부 진행 로그('[' 로 시작) 숨김
    (function () {
        var L = console.log, W = console.warn;
        console.log = function () { if (typeof arguments[0] === 'string' && arguments[0].charAt(0) === '[') return; L.apply(console, arguments); };
        console.warn = function () { if (typeof arguments[0] === 'string' && arguments[0].charAt(0) === '[') return; W.apply(console, arguments); };
    })();
    window.RHWP_CFG = <?php echo json_encode([
        'fileUrl' => $file_url,
        'basicUrl' => $basic_url,
        'backUrl' => $back_url,
        'js' => './js/rhwp/rhwp.js?v=' . $rhwp_v('rhwp.js'),          // import() 는 './' 로 시작해야 상대 경로(아니면 모듈 이름으로 봄)
        'wasm' => './js/rhwp/rhwp_bg.wasm?v=' . $rhwp_v('rhwp_bg.wasm'),
        't' => [
            'loading_file' => __('rhwp_loading_file'), 'parsing' => __('rhwp_parsing'), 'open_fail' => __('rhwp_open_fail'),
            'no_pages' => __('rhwp_no_pages'), 'page_fail' => __('rhwp_page_fail'), 'pages' => __('rhwp_pages'),
            'basic' => __('rhwp_basic_btn'), 'bad_format' => __('rhwp_bad_format'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>

    <script type="module">
    const C = window.RHWP_CFG, T = C.t;
    const body = document.getElementById('viewer-body');
    const loading = document.getElementById('loading');
    const loadingText = document.getElementById('loading-text');
    const pageInfo = document.getElementById('page-info');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const zoomSelect = document.getElementById('zoom-select');
    const isMobile = window.innerWidth <= 768;
    let doc = null, currentPage = 0, totalPages = 0, currentZoom = isMobile ? 'fit' : 1;
    zoomSelect.value = isMobile ? 'fit' : '1';

    const fmt = (s, n) => String(s).replace('%d', String(n));
    function esc(s) { const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
    // rhwp(WASM)는 Error 가 아닌 문자열·값을 던지기도 함 → 'undefined' 대신 알맞은 문구
    function errText(e) {
        if (e && typeof e.message === 'string' && e.message) return e.message;
        if (typeof e === 'string' && e) return e;
        if (e != null) { const s = String(e); if (s && s !== '[object Object]' && s !== 'undefined') return s; }
        return T.bad_format;
    }
    function sizeSvg(svgEl) {
        if (!svgEl) return;
        if (currentZoom === 'fit') {
            svgEl.style.width = '100%'; svgEl.style.height = 'auto';
            svgEl.removeAttribute('width'); svgEl.removeAttribute('height');
        } else {
            const vb = svgEl.getAttribute('viewBox');
            if (vb) { svgEl.style.width = (parseFloat(vb.split(/\s+/)[2]) * currentZoom) + 'px'; svgEl.style.height = 'auto'; }
        }
    }
    function renderAllPages() {
        body.innerHTML = '';
        for (let i = 0; i < totalPages; i++) {
            const box = document.createElement('div');
            box.className = 'page-container';
            box.dataset.page = String(i);
            try {
                box.innerHTML = doc.renderPageSvg(i);   // rhwp 가 문서 내용을 그린 SVG(스크립트 없는 그림 — 엔진이 글자를 이스케이프함)
                box.querySelectorAll('script, foreignObject').forEach(n => n.remove()); // 혹시 모를 실행 요소 제거(그림만 남김)
                // ★ (2026-11-04 09:41) 링크·속성 정리 — javascript: 같은 주소와 on* 속성 제거(문서 하이퍼링크를 누르면 사이트 권한으로 실행될 수 있었음, office 의 DOCX 링크 처리와 같게)
                box.querySelectorAll('*').forEach(function (el) {
                    for (const at of Array.from(el.attributes)) {
                        const nm = at.name.toLowerCase();
                        if (nm.startsWith('on')) { el.removeAttribute(at.name); continue; }
                        if (el.localName === 'a' && (nm === 'href' || nm.endsWith(':href')) && !/^\s*(https?:|mailto:|#)/i.test(at.value)) el.removeAttribute(at.name); // 링크(a)만 — 그림(image)의 주소는 그대로
                    }
                });
                sizeSvg(box.querySelector('svg'));
            } catch (e) {
                box.innerHTML = '<div class="error-msg">' + esc(fmt(T.page_fail, i + 1)) + ': ' + esc(errText(e)) + '</div>';
            }
            body.appendChild(box);
        }
        pageInfo.textContent = fmt(T.pages, totalPages);
    }
    function updateNav() { btnPrev.disabled = currentPage <= 0; btnNext.disabled = currentPage >= totalPages - 1; }
    function goPage(p) {
        if (p < 0 || p >= totalPages) return;
        currentPage = p;
        const t = body.querySelector('.page-container[data-page="' + p + '"]');
        if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
        updateNav();
    }

    async function start() {
        try {
            const mod = await import(C.js);
            await mod.default({ module_or_path: C.wasm });
            try { document.getElementById('rhwp-version').textContent = 'rhwp v' + mod.version(); } catch (e) {}
            loadingText.textContent = T.loading_file;
            const resp = await fetch(C.fileUrl, { credentials: 'same-origin' });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = new Uint8Array(await resp.arrayBuffer());
            loadingText.textContent = T.parsing;
            doc = new mod.HwpDocument(data);
            totalPages = doc.pageCount();
            if (!totalPages) throw new Error(T.no_pages);
            renderAllPages();
            updateNav();
            loading.classList.add('hidden');
        } catch (e) {
            loading.classList.add('hidden');
            body.innerHTML = '<div class="error-msg">❌ ' + esc(T.open_fail) + '<br><br>' + esc(errText(e)) +
                '<br><br><a href="' + esc(C.basicUrl) + '">🔄 ' + esc(T.basic) + '</a></div>';
        }
    }

    // ★ (2026-11-10 14:55, 펜닐 요청 — fix99) 지금 보는 쪽(위 쪽 번호)의 SVG 를 높이 1000px 이하 JPEG 로 — 표지 메뉴(function.php mc_doc_cover_btn)가 씀
    window.mcDocCoverPageNo = function () { return currentPage + 1; }; // ★ (2026-11-11 07:59 fix111) 표지 메뉴에 보일 쪽 번호 — 아래 mcDocCoverPage 가 쓰는 쪽(currentPage)
    window.mcDocCoverPage = function () {
        return new Promise(function (res) {
            const svg = body.querySelector('.page-container[data-page="' + currentPage + '"] svg');
            if (!svg) return res(null);
            const vb = (svg.getAttribute('viewBox') || '').split(/[\s,]+/).map(Number);
            const w0 = vb[2] || svg.getBoundingClientRect().width, h0 = vb[3] || svg.getBoundingClientRect().height;
            if (!w0 || !h0) return res(null);
            const cl = svg.cloneNode(true); cl.setAttribute('width', w0); cl.setAttribute('height', h0); cl.removeAttribute('style');
            if (!cl.getAttribute('xmlns')) cl.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            const u = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(cl)], { type: 'image/svg+xml' }));
            const im = new Image();
            im.onload = function () {
                try {
                    const s = Math.min(3, 1000 / h0), c = document.createElement('canvas');
                    c.width = Math.max(1, Math.round(w0 * s)); c.height = Math.max(1, Math.round(h0 * s));
                    const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(im, 0, 0, c.width, c.height);
                    URL.revokeObjectURL(u); res(c.toDataURL('image/jpeg', 0.88));
                } catch (e) { URL.revokeObjectURL(u); res(null); }
            };
            im.onerror = function () { URL.revokeObjectURL(u); res(null); };
            im.src = u;
        });
    };
    btnPrev.addEventListener('click', () => goPage(currentPage - 1));
    btnNext.addEventListener('click', () => goPage(currentPage + 1));
    // 스크롤 위치로 지금 쪽 표시
    let _st = 0;
    body.addEventListener('scroll', () => {
        if (_st) return;
        _st = requestAnimationFrame(() => {
            _st = 0;
            const pages = body.querySelectorAll('.page-container'), top = body.getBoundingClientRect().top;
            for (let i = 0; i < pages.length; i++) {
                const r = pages[i].getBoundingClientRect();
                if (r.top >= top - r.height / 2) { currentPage = i; pageInfo.textContent = (i + 1) + ' / ' + totalPages; updateNav(); break; }
            }
        });
    }, { passive: true });
    zoomSelect.addEventListener('change', () => {
        currentZoom = zoomSelect.value === 'fit' ? 'fit' : parseFloat(zoomSelect.value);
        body.querySelectorAll('.page-container svg').forEach(sizeSvg);
    });
    document.getElementById('btn-print').addEventListener('click', () => window.print());
    function goBack() { if (history.length > 1) history.back(); else location.href = C.backUrl; }
    document.getElementById('btn-close').addEventListener('click', goBack);
    document.addEventListener('keydown', (e) => {
        if (e.target && /^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName)) return;
        if (e.key === 'Escape') goBack();
        else if (e.key === 'PageDown') { e.preventDefault(); goPage(currentPage + 1); }
        else if (e.key === 'PageUp') { e.preventDefault(); goPage(currentPage - 1); }
    });
    start();
    </script>
<?php render_auto_logout_script(); ?>
<?php require_once __DIR__ . '/privacy_shield.php'; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-31) 이모지 → 선 아이콘 */ ?>
</body>
</html>
