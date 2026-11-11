/*!
 * book-notes.js — ★ (2026-10-31) 책 노트: 형광펜·밑줄·메모·책갈피 (EPUB·TXT 뷰어 공용 화면)
 *
 * 뷰어마다 다른 부분(글 위치 표시·이동·지금 쪽)은 adapter 가 맡고, 이 파일은 공통 화면만:
 *   - 글을 고르면 뜨는 작은 메뉴(색 5가지 형광펜 · 밑줄 · 메모 · 복사 · 사전 · 번역 — ★ 2026-11-01 사전(짧게 고른 말만)·번역(파파고) 추가)
 *   - 표시한 글을 누르면 뜨는 고치기 메뉴(색·형광펜↔밑줄·메모·복사·지우기)
 *   - 메모 창, 노트 목록 창(전체·책갈피·밑줄/형광펜·메모, 색 거르기, 누르면 그 위치로), 마크다운으로 내보내기
 *   - 책갈피 단추(지금 쪽에 있으면 채워진 모양)
 *   - 서버 저장(뷰어 ?action=notes_load / notes_save, CSRF) — 실패하면 화면을 되돌리고 알림
 * 글자는 모두 textContent 로만 넣음(메모·고른 글이 HTML 로 해석되지 않게)
 *
 * MCNotes.init({ api, file, bidx, csrf, t, title, adapter, mount }) → core
 *   adapter = { kind:'epub'|'txt', draw(item), undraw(item), go(item), here() → {위치…, q, ch, p} | null,
 *               onPage(item) → bool, compare(a, b) → -1·0·1(책 안 순서) }
 *   core.selected({ pos:{cfi}|{s,e}, q, ch, p, rect, clear() })  — 뷰어가 글 고르기를 알려 줌
 *   core.clicked(item, rect)                                      — 뷰어가 표시한 글 누름을 알려 줌
 *   core.relocated()                                              — 쪽이 바뀌면(책갈피 단추 갱신)
 */
(function () {
    'use strict';
    if (window.MCNotes) return;

    // ★ (2026-11-07 fix39) 색 5가지 → 7가지 / ★ (2026-11-07 fix40) 9가지 — 공부할 때 많이 쓰는 시중 형광펜 색(형광 기본색 + 눈이 편한 은은한 '마일드' 계열)으로 다시 고름(펜닐 요청)
    //   노랑·주황·코랄(빨강 계열)·분홍·보라·파랑·민트(청록)·초록(연두)·회색. 칠하는 색(FILL)은 채도를 낮춰 오래 봐도 눈이 덜 부시게, 밑줄·테두리 색(LINE)은 같은 계열 진한 색
    //   예전 저장 색 이름(yellow·orange·green·blue·pink·purple·gray)은 그대로 써서 전에 칠한 것도 같은 계열로 보임 / 서버(book_notes_lib.php)도 같은 9가지만 받음
    var COLORS = ['yellow', 'orange', 'coral', 'pink', 'purple', 'blue', 'mint', 'green', 'gray'];
    var FILL = { yellow: '#fde26a', orange: '#ffc285', coral: '#ffaa9c', pink: '#f9b3cf', purple: '#d3c0f4', blue: '#a5cdfb', mint: '#99e3d8', green: '#bfe89a', gray: '#c9ced5' };
    var LINE = { yellow: '#d4a000', orange: '#e8590c', coral: '#e03131', pink: '#d6336c', purple: '#7048e8', blue: '#1c7ed6', mint: '#0c8599', green: '#2f9e44', gray: '#495057' };

    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function newId() {
        var a = new Uint8Array(8);
        try { crypto.getRandomValues(a); } catch (e) { for (var i = 0; i < 8; i++) a[i] = Math.floor(Math.random() * 256); }
        return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }
    function fmtDate(sec) {
        try { var d = new Date(sec * 1000); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); } catch (e) { return ''; }
    }
    var ICON = {
        bm: '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>',
        notes: '<path d="M12 20h9"/><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"/>',
        hl: '<path d="m9 11-6 6v3h9l3-3"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/>',
        ul: '<path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" x2="20" y1="20" y2="20"/>',
        memo: '<path d="M15.5 3H5a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3Z"/><path d="M15 3v6h6"/>',
        copy: '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
        del: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
        x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        play: '<path d="M2 10v3"/><path d="M6 6v11"/><path d="M10 3v18"/><path d="M14 8v7"/><path d="M18 5v13"/><path d="M22 10v3"/>', // ★ fix40 여기부터 듣기(소리 물결)
        dl: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        tr: '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>',
        dict: '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>'
    };
    // ★ (2026-11-01 08:18) 사전 찾기 — 고른 말의 글자 종류로 네이버 사전(국어·영어·일본어·한자)을 새 창으로
    function dictUrl(q) {
        var w = encodeURIComponent(q);
        if (/[\uac00-\ud7a3]/.test(q)) return 'https://ko.dict.naver.com/#/search?query=' + w;
        if (/[\u3040-\u30ff]/.test(q)) return 'https://ja.dict.naver.com/#/search?query=' + w;
        if (/[\u4e00-\u9fff]/.test(q)) return 'https://hanja.dict.naver.com/#/search?query=' + w;
        if (/[A-Za-z]/.test(q)) return 'https://en.dict.naver.com/#/search?query=' + w;
        return 'https://dict.naver.com/search.dict?dicQuery=' + w;
    }
    // ★ (2026-11-01 11:22) 번역 — 고른 글을 파파고 새 창으로(한글이면 영어로, 그 밖은 한국어로)
    function transUrl(q) {
        var tk = /[\uac00-\ud7a3]/.test(q) ? 'en' : 'ko';
        return 'https://papago.naver.com/?sk=auto&tk=' + tk + '&st=' + encodeURIComponent(q);
    }
    function svg(name, size) {
        var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('width', size || 18); s.setAttribute('height', size || 18);
        s.setAttribute('fill', 'none'); s.setAttribute('stroke', 'currentColor'); s.setAttribute('stroke-width', '2');
        s.setAttribute('stroke-linecap', 'round'); s.setAttribute('stroke-linejoin', 'round'); s.setAttribute('aria-hidden', 'true');
        s.innerHTML = ICON[name]; // 고정 그림만
        return s;
    }

    function css() {
        if (document.getElementById('mcn-css')) return;
        var s = document.createElement('style'); s.id = 'mcn-css';
        s.textContent =
            '.mcn-pop{position:fixed;z-index:3000;display:flex;align-items:center;gap:2px;padding:4px;background:#202124;color:#fff;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.3);font:13px/1.2 system-ui,-apple-system,"Segoe UI","Malgun Gothic",sans-serif;user-select:none;-webkit-user-select:none}' +
            '.mcn-pop[hidden]{display:none}' +
            '.mcn-pop{max-width:calc(100vw - 16px);flex-wrap:wrap;justify-content:center}' + // ★ (2026-11-01) 단추가 늘어 아주 좁은 화면에선 두 줄로
            '.mcn-pop button{border:0;background:transparent;color:inherit;height:32px;min-width:32px;padding:0 7px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;gap:4px;cursor:pointer;font:inherit;white-space:nowrap}' +
            '.mcn-pop button:hover,.mcn-pop button:focus-visible{background:rgba(255,255,255,.14);outline:none}' +
            '.mcn-pop button.on{background:rgba(255,255,255,.22)}' +
            '.mcn-pop .mcn-dot{width:18px;height:18px;border-radius:50%;border:2px solid rgba(255,255,255,.7);display:inline-block}' +
            '.mcn-pop .mcn-dot.on{box-shadow:0 0 0 2px #202124,0 0 0 4px #fff}' +
            '.mcn-pop .mcn-sep{width:1px;height:20px;background:rgba(255,255,255,.25);margin:0 3px}' +
            '.mcn-mask{position:fixed;inset:0;z-index:3100;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:16px}' +
            '.mcn-dlg{background:#fff;color:#202124;border-radius:12px;width:100%;max-width:480px;box-shadow:0 12px 40px rgba(0,0,0,.35);font:14px/1.5 system-ui,-apple-system,"Segoe UI","Malgun Gothic",sans-serif;overflow:hidden}' +
            '.mcn-dlg-h{display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid #e8eaed;font-weight:700}' +
            '.mcn-dlg-h span{flex:1}' +
            '.mcn-dlg-b{padding:12px 14px}' +
            '.mcn-quote{border-left:4px solid #ffe066;padding:4px 10px;margin:0 0 10px;color:#5f6368;max-height:120px;overflow:auto;white-space:pre-wrap;word-break:break-word}' +
            '.mcn-dlg textarea{width:100%;min-height:120px;resize:vertical;border:1px solid #dadce0;border-radius:8px;padding:8px 10px;font:inherit;color:inherit;background:#fff}' +
            '.mcn-dlg textarea:focus{outline:none;border-color:#1a73e8;box-shadow:0 0 0 3px rgba(26,115,232,.15)}' +
            '.mcn-dlg-f{display:flex;justify-content:flex-end;gap:8px;padding:10px 14px;border-top:1px solid #e8eaed}' +
            // ★ (2026-11-11 11:33 fix113, 펜닐 요청) 메모 창이 낮은 화면(휴대폰 가로)에서 화면보다 커져 위 제목줄이 잘리고 저장 단추가 화면 끝에 붙었음 —
            //   창 높이를 화면 안으로, 넘치면 가운데(인용·글 칸)만 스크롤(제목줄·저장/취소는 늘 보임). 낮은 화면은 인용·글 칸을 조금 작게
            '.mcn-dlg{max-height:calc(100vh - 32px);max-height:calc(100dvh - 32px);display:flex;flex-direction:column}.mcn-dlg-b{overflow:auto;min-height:0;flex:1 1 auto}' +
            '@media (max-height:520px){.mcn-mask{padding:8px}.mcn-dlg{max-height:calc(100vh - 16px);max-height:calc(100dvh - 16px)}.mcn-quote{max-height:64px}.mcn-dlg textarea{min-height:72px}}' +
            '.mcn-btn{height:34px;padding:0 14px;border-radius:17px;border:1px solid #dadce0;background:#fff;color:#202124;font:inherit;cursor:pointer}' +
            '.mcn-btn.pri{background:#1a73e8;border-color:#1a73e8;color:#fff}' +
            '.mcn-x{border:0;background:transparent;color:inherit;width:32px;height:32px;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}' +
            '.mcn-x:hover{background:rgba(0,0,0,.06)}' +
            '.mcn-panel{position:fixed;top:55px;right:0;bottom:45px;width:min(380px,92vw);z-index:1003;background:#fff;color:#202124;border-left:1px solid #dadce0;box-shadow:-6px 0 20px rgba(0,0,0,.12);display:flex;flex-direction:column;transform:translateX(105%);transition:transform .25s;font:14px/1.45 system-ui,-apple-system,"Segoe UI","Malgun Gothic",sans-serif}' +
            '.mcn-panel.show{transform:none}' +
            '.mcn-panel-h{display:flex;align-items:center;gap:6px;padding:10px 12px;border-bottom:1px solid #e8eaed;font-weight:700}' +
            '.mcn-panel-h .mcn-ttl{flex:1}' +
            '.mcn-tabs{display:flex;gap:4px;padding:8px 10px 0;flex-wrap:wrap}' +
            '.mcn-tab{height:28px;padding:0 10px;border-radius:14px;border:1px solid #dadce0;background:#fff;color:#3c4043;font:inherit;font-size:12px;cursor:pointer}' +
            '.mcn-tab.on{background:#202124;border-color:#202124;color:#fff}' +
            '.mcn-cf{display:flex;gap:6px;padding:8px 12px;align-items:center}' +
            '.mcn-cf button{width:20px;height:20px;border-radius:50%;border:2px solid transparent;cursor:pointer;padding:0}' +
            '.mcn-cf button.on{border-color:#202124}' +
            '.mcn-cf .mcn-cf-all{width:auto;height:22px;border-radius:11px;padding:0 8px;font:inherit;font-size:12px;border:1px solid #dadce0;background:#fff;color:#3c4043}' +
            '.mcn-cf .mcn-cf-all.on{background:#e8f0fe;border-color:#1a73e8;color:#1a56c4}' +
            '.mcn-cf{flex-wrap:wrap}.mcn-cf .mcn-cf-lb{font-size:12px;color:#5f6368;margin-right:2px}' + // ★ fix39 '모든 색'이 무엇인지 — 앞에 '색으로 거르기' 글
            /* ★ (2026-11-07 fix39) 메모가 있는 표시 글 끝에 작은 메모 표(누르면 메모 창) — 색만으로는 메모가 있는지 알 수 없었음(펜닐 제보) */
            '.mcn-mbadge{position:fixed;z-index:998;width:18px;height:18px;margin:0;padding:0;border-radius:6px;border:1.5px solid #f08c00;background:#fff8e1;color:#5f4500;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.25)}' +
            '.mcn-mbadge[hidden]{display:none}' +
            '.mcn-mbadge svg{width:13px;height:13px;pointer-events:none}' +
            '.mcn-mbadge:focus-visible{outline:2px solid #1a73e8;outline-offset:1px}' +
            '.mcn-mbadge svg{width:12px;height:12px}@media (pointer:coarse){.mcn-mbadge{width:22px;height:22px}.mcn-mbadge svg{width:14px;height:14px}}' +
            'body.theme-dark .mcn-mbadge{background:#2d2d2d;color:#fdd663}' +
            '.mcn-item-h .mcn-mk{display:inline-flex;align-items:center;gap:2px;color:#b06000;font-size:11px}' + // 목록 카드 머리의 '메모' 표
            '.mcn-list{flex:1;overflow:auto;padding:4px 10px 12px;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}' +
            '.mcn-item{border:1px solid #e8eaed;border-radius:10px;padding:8px 10px;margin-top:8px;cursor:pointer;background:#fff}' +
            '.mcn-item:hover{border-color:#c6c9cc}' +
            '.mcn-item-h{display:flex;align-items:center;gap:6px;font-size:12px;color:#5f6368}' +
            '.mcn-item-h .mcn-ch{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
            '.mcn-item-q{margin-top:4px;padding-left:8px;border-left:4px solid #ffe066;white-space:pre-wrap;word-break:break-word;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:4;overflow:hidden}' +
            '.mcn-item-q.ul{border-left-style:dashed}' +
            '.mcn-item-n{margin-top:6px;padding:6px 8px;border-radius:6px;background:#fff8e1;color:#5f4500;white-space:pre-wrap;word-break:break-word;font-size:13px}' +
            '.mcn-item .mcn-x{width:26px;height:26px;color:#5f6368}' +
            '.mcn-empty{color:#5f6368;font-size:13px;padding:16px 4px;text-align:center}' +
            '.mcn-toast{position:fixed;left:50%;bottom:70px;transform:translateX(-50%);z-index:3200;background:#202124;color:#fff;padding:8px 14px;border-radius:18px;font:13px system-ui,-apple-system,"Malgun Gothic",sans-serif;opacity:0;transition:opacity .2s;pointer-events:none;max-width:90vw;text-align:center}' +
            '.mcn-toast.show{opacity:.95}' +
            /* ★ (2026-11-03 00:11) 지금 쪽에 책갈피가 있으면 본문 오른쪽 위에 파란 책갈피 띠(표시만, 눌리지 않음) */
            '.mcn-ribbon{position:fixed;right:14px;top:56px;z-index:1500;width:22px;height:30px;pointer-events:none;opacity:0;transform:translateY(-6px);transition:opacity .2s,transform .2s;filter:drop-shadow(0 1px 2px rgba(0,0,0,.35))}' +
            '.mcn-ribbon.on{opacity:1;transform:none}' +
            '.mcn-ribbon svg{width:22px;height:30px;display:block}' +
            '.mcn-tb-btn.on svg{fill:currentColor}' +
            // EPUB 밑줄(epub.js marks-pane): 둘레 상자는 안 보이게(누를 수는 있게 투명 채움), 아래 선만 색·굵기
            'g[class^="mcn-ul-"] rect{stroke:none!important;fill:rgba(0,0,0,0)!important}' +
            'g[class^="mcn-ul-"] line{stroke-width:2px!important;stroke-opacity:1!important}' +
            COLORS.map(function (c) { return 'g.mcn-ul-' + c + ' line{stroke:' + LINE[c] + '!important}'; }).join('') +
            'body.theme-dark .mcn-panel,body.theme-dark .mcn-dlg{background:#1e1e1e;color:#e8eaed;border-color:#3c4043}' +
            'body.theme-dark .mcn-panel-h,body.theme-dark .mcn-dlg-h,body.theme-dark .mcn-dlg-f{border-color:#3c4043}' +
            'body.theme-dark .mcn-tab,body.theme-dark .mcn-btn,body.theme-dark .mcn-cf .mcn-cf-all{background:#2d2d2d;border-color:#3c4043;color:#e8eaed}' +
            'body.theme-dark .mcn-tab.on{background:#e8eaed;color:#202124}' +
            'body.theme-dark .mcn-btn.pri{background:#8ab4f8;border-color:#8ab4f8;color:#202124}' +
            'body.theme-dark .mcn-item{background:#262626;border-color:#3c4043}' +
            'body.theme-dark .mcn-item-h,body.theme-dark .mcn-quote,body.theme-dark .mcn-empty{color:#9aa0a6}' +
            'body.theme-dark .mcn-item-n{background:#3a3020;color:#fdd663}' +
            'body.theme-dark .mcn-dlg textarea{background:#2d2d2d;border-color:#3c4043;color:#e8eaed}' +
            'body.theme-dark .mcn-x:hover{background:rgba(255,255,255,.08)}' +
            'body.theme-sepia .mcn-panel,body.theme-sepia .mcn-dlg{background:#fbf6ea;color:#3d2f22}' +
            'body.theme-sepia .mcn-item{background:#fffdf7}' +
            // TXT 뷰어 표시(CSS Custom Highlight API — 본문 글을 바꾸지 않고 칠함)
            COLORS.map(function (c) {
                var f = FILL[c];
                return '::highlight(mcn-hl-' + c + '){background-color:' + f + 'B3;color:inherit}' +
                    'body.dark-mode ::highlight(mcn-hl-' + c + '),body.theme-dark ::highlight(mcn-hl-' + c + '){background-color:' + f + '59}' +
                    '::highlight(mcn-ul-' + c + '){text-decoration:underline;text-decoration-color:' + LINE[c] + ';text-decoration-thickness:2px;text-underline-offset:3px}';
            }).join('');
        // TXT 뷰어는 테마가 body.dark-mode·sepia-mode — EPUB(body.theme-*) 규칙을 그대로 한 벌 더
        var extra = (s.textContent.match(/body\.theme-(dark|sepia)[^{}]*\{[^}]*\}/g) || []).join('')
            .replace(/body\.theme-dark/g, 'body.dark-mode').replace(/body\.theme-sepia/g, 'body.sepia-mode');
        s.textContent += extra;
        (document.head || document.documentElement).appendChild(s);
    }

    var listenFn = null, cores = []; // ★ fix40 고른 곳부터 듣기(뷰어가 MCNotes.setListen 으로 알려 줌) · 노트 창 모두 닫기용
    function init(o) {
        css();
        var T = o.t || {};
        var A = o.adapter;
        var items = [];
        var core = { items: function () { return items; }, FILL: FILL, LINE: LINE };
        var lastColor = 'yellow';
        try { var lc = localStorage.getItem('mcn_color'); if (COLORS.indexOf(lc) >= 0) lastColor = lc; } catch (e) {}

        // ── 알림
        var toast = el('div', 'mcn-toast'); document.body.appendChild(toast); var toastT = 0;
        function say(msg, ms) { toast.textContent = msg; toast.classList.add('show'); clearTimeout(toastT); toastT = setTimeout(function () { toast.classList.remove('show'); }, ms || 1800); }
        core.say = say;

        // ── 서버
        function post(params) {
            var fd = new FormData();
            fd.append('file', o.file); fd.append('csrf_token', o.csrf);
            Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
            return fetch(o.api + '?action=notes_save&bidx=' + o.bidx, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json().catch(function () { return { success: false, error: 'http ' + r.status }; }); });
        }
        function failMsg(err) { return err === 'too_many' ? (T.too_many || 'Too many notes') : (T.save_fail || 'Could not save'); }
        function find(id) { for (var i = 0; i < items.length; i++) if (items[i].id === id) return i; return -1; }
        function put(it, prev) {
            var i = find(it.id);
            if (i >= 0) { A.undraw(items[i]); items[i] = it; } else items.push(it);
            A.draw(it); refresh();
            return post({ op: 'put', item: JSON.stringify(it) }).then(function (r) {
                if (r && r.success) { if (r.item) { var j = find(it.id); if (j >= 0) items[j] = Object.assign(items[j], { t: r.item.t, m: r.item.m }); } return true; }
                // 되돌림
                var k = find(it.id);
                if (k >= 0) { A.undraw(items[k]); if (prev) { items[k] = prev; A.draw(prev); } else items.splice(k, 1); }
                refresh(); say(failMsg(r && r.error), 3000); return false;
            }).catch(function () {
                var k = find(it.id);
                if (k >= 0) { A.undraw(items[k]); if (prev) { items[k] = prev; A.draw(prev); } else items.splice(k, 1); }
                refresh(); say(T.save_fail || 'Could not save', 3000); return false;
            });
        }
        function del(it) {
            var i = find(it.id); if (i < 0) return Promise.resolve(true);
            A.undraw(it); items.splice(i, 1); refresh();
            return post({ op: 'del', id: it.id }).then(function (r) {
                if (r && r.success) return true;
                items.splice(Math.min(i, items.length), 0, it); A.draw(it); refresh(); say(T.save_fail || 'Could not save', 3000); return false;
            }).catch(function () { items.splice(Math.min(i, items.length), 0, it); A.draw(it); refresh(); say(T.save_fail || 'Could not save', 3000); return false; });
        }
        // ★ (2026-11-03 00:06) 북마크 화면의 '전자책 책갈피'에서 열면(?note=아이디) 그 자리로 — 찾으면 true
        core.goNote = function (id) {
            var i = find(String(id || '')); if (i < 0) return false;
            try { A.go(items[i]); } catch (e) { return false; }
            return true;
        };
        core.load = function () {
            return fetch(o.api + '?action=notes_load&bidx=' + o.bidx + '&file=' + encodeURIComponent(o.file), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    items = (d && d.success && Array.isArray(d.items)) ? d.items.filter(function (x) { return x && x.id && x.k; }) : [];
                    items.forEach(function (it) { try { A.draw(it); } catch (e) {} });
                    refresh();
                }).catch(function () { items = []; refresh(); });
        };

        function copyText(text) {
            var done = function () { say(T.copied || 'Copied'); };
            try { if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, function () { fallback(); }); return; } } catch (e) {}
            fallback();
            function fallback() { var ta = el('textarea'); ta.value = text; ta.style.cssText = 'position:fixed;left:-9999px;top:0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) {} ta.remove(); }
        }

        // ── 떠 있는 메뉴(고를 때·누를 때 같은 상자)
        var pop = el('div', 'mcn-pop'); pop.hidden = true; pop.setAttribute('role', 'toolbar');
        document.body.appendChild(pop);
        var popCtx = null;
        function hidePop() { pop.hidden = true; popCtx = null; }
        core.hidePop = hidePop;
        function place(rect) {
            pop.hidden = false;
            var w = pop.offsetWidth, h = pop.offsetHeight, vw = window.innerWidth, vh = window.innerHeight;
            // 손가락 기기는 휴대폰 자체 메뉴(복사 등)가 고른 글 위에 떠서 겹치므로 아래에, 마우스는 위에
            var coarse = false; try { coarse = window.matchMedia('(pointer: coarse)').matches; } catch (e) {}
            var x = Math.round((rect.left + rect.right) / 2 - w / 2), y = coarse ? Math.round(rect.bottom + 14) : Math.round(rect.top - h - 10);
            if (!coarse && y < 60) y = Math.round(rect.bottom + 10);
            if (coarse && y + h > vh - 50) y = Math.round(rect.top - h - 14);
            if (y + h > vh - 50) y = Math.max(60, vh - 50 - h);
            x = Math.max(8, Math.min(vw - w - 8, x));
            pop.style.left = x + 'px'; pop.style.top = y + 'px';
        }
        function pbtn(icon, label, fn, on) {
            var b = el('button'); b.type = 'button'; b.title = label; b.setAttribute('aria-label', label);
            if (icon) b.appendChild(svg(icon, 18));
            if (on) b.classList.add('on');
            b.addEventListener('mousedown', function (e) { e.preventDefault(); }); // 고른 글이 풀리지 않게
            b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); fn(); });
            pop.appendChild(b); return b;
        }
        function pdots(cur, fn) {
            COLORS.forEach(function (c) {
                var b = el('button'); b.type = 'button'; var nm = (T['c_' + c] || c); b.title = nm; b.setAttribute('aria-label', nm);
                var d = el('span', 'mcn-dot' + (cur === c ? ' on' : '')); d.style.background = FILL[c]; b.appendChild(d);
                b.addEventListener('mousedown', function (e) { e.preventDefault(); });
                b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); fn(c); });
                pop.appendChild(b);
            });
        }
        function sep() { pop.appendChild(el('span', 'mcn-sep')); }

        function makeItem(sel, k, c) {
            var it = { id: newId(), k: k, c: c, q: sel.q || '', n: '', ch: sel.ch || '', p: (typeof sel.p === 'number') ? sel.p : -1, t: Math.floor(Date.now() / 1000) };
            Object.keys(sel.pos || {}).forEach(function (key) { it[key] = sel.pos[key]; });
            return it;
        }
        // 글을 골랐을 때
        core.selected = function (sel) {
            if (!sel || !sel.q || !sel.rect) { hidePop(); return; }
            pop.innerHTML = ''; popCtx = sel;
            pdots(null, function (c) { lastColor = c; try { localStorage.setItem('mcn_color', c); } catch (e) {} var it = makeItem(sel, 'hl', c); try { sel.clear(); } catch (e) {} hidePop(); put(it); });
            sep();
            pbtn('ul', T.underline || 'Underline', function () { var it = makeItem(sel, 'ul', lastColor); try { sel.clear(); } catch (e) {} hidePop(); put(it); });
            pbtn('memo', T.memo || 'Note', function () { var it = makeItem(sel, 'hl', lastColor); try { sel.clear(); } catch (e) {} hidePop(); memoDlg(it, null, true); });
            pbtn('copy', T.copy || 'Copy', function () { copyText(sel.q); try { sel.clear(); } catch (e) {} hidePop(); });
            if (listenFn && T.listen_here) pbtn('play', T.listen_here, function () { var ps = sel.pos; try { sel.clear(); } catch (e) {} hidePop(); try { listenFn(ps); } catch (e) {} }); // ★ fix40 고른 곳부터 듣기
            var dq = String(sel.q || '').replace(/\s+/g, ' ').trim();
            if (T.dict && dq && dq.length <= 40) pbtn('dict', T.dict, function () { // 짧게 고른 말만(문장 통째로는 사전에 안 맞음)
                hidePop(); try { var wnd = window.open(dictUrl(dq), '_blank', 'noopener'); if (wnd) wnd.opener = null; } catch (e) {}
            });
            var tq = String(sel.q || '').replace(/[ \t]+/g, ' ').trim();
            if (T.translate && tq && tq.length <= 1000) pbtn('tr', T.translate, function () { // 길면 주소가 너무 길어져 1000자까지
                hidePop(); try { var wnd = window.open(transUrl(tq), '_blank', 'noopener'); if (wnd) wnd.opener = null; } catch (e) {}
            });
            place(sel.rect);
        };
        // 표시한 글을 눌렀을 때
        core.clicked = function (it, rect) {
            var i = find(it.id); if (i < 0) return;
            it = items[i];
            pop.innerHTML = ''; popCtx = it;
            pdots(it.c, function (c) { hidePop(); if (c === it.c) return; var prev = Object.assign({}, it); var nx = Object.assign({}, it, { c: c }); lastColor = c; try { localStorage.setItem('mcn_color', c); } catch (e) {} put(nx, prev); });
            sep();
            pbtn('hl', T.highlight || 'Highlight', function () { hidePop(); if (it.k === 'hl') return; var prev = Object.assign({}, it); put(Object.assign({}, it, { k: 'hl' }), prev); }, it.k === 'hl');
            pbtn('ul', T.underline || 'Underline', function () { hidePop(); if (it.k === 'ul') return; var prev = Object.assign({}, it); put(Object.assign({}, it, { k: 'ul' }), prev); }, it.k === 'ul');
            pbtn('memo', it.n ? (T.memo_edit || 'Edit note') : (T.memo || 'Note'), function () { hidePop(); memoDlg(Object.assign({}, it), it, false); }, !!it.n);
            pbtn('copy', T.copy || 'Copy', function () { hidePop(); copyText(it.q + (it.n ? '\n\n' + it.n : '')); });
            pbtn('del', T.del || 'Delete', function () { hidePop(); del(it); });
            place(rect);
        };
        document.addEventListener('mousedown', function (e) { if (!pop.hidden && !pop.contains(e.target)) hidePop(); }, true);
        document.addEventListener('touchstart', function (e) { if (!pop.hidden && !pop.contains(e.target)) hidePop(); }, { capture: true, passive: true });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !pop.hidden) { hidePop(); e.stopPropagation(); } }, true);
        window.addEventListener('resize', hidePop);

        // ── 메모 창
        function memoDlg(it, prev, isNew) {
            var mask = el('div', 'mcn-mask');
            var dlg = el('div', 'mcn-dlg'); dlg.setAttribute('role', 'dialog'); dlg.setAttribute('aria-modal', 'true');
            var h = el('div', 'mcn-dlg-h'); h.appendChild(svg('memo', 18)); h.appendChild(el('span', null, T.memo || 'Note'));
            var x = el('button', 'mcn-x'); x.type = 'button'; x.setAttribute('aria-label', T.cancel || 'Cancel'); x.appendChild(svg('x', 18)); h.appendChild(x);
            var b = el('div', 'mcn-dlg-b');
            var q = el('div', 'mcn-quote', it.q); q.style.borderLeftColor = FILL[it.c] || FILL.yellow; b.appendChild(q);
            var ta = el('textarea'); ta.value = it.n || ''; ta.maxLength = 4000; ta.placeholder = T.memo_ph || ''; b.appendChild(ta);
            var f = el('div', 'mcn-dlg-f');
            var cancel = el('button', 'mcn-btn', T.cancel || 'Cancel'); cancel.type = 'button';
            var save = el('button', 'mcn-btn pri', T.save || 'Save'); save.type = 'button';
            f.appendChild(cancel); f.appendChild(save);
            dlg.appendChild(h); dlg.appendChild(b); dlg.appendChild(f); mask.appendChild(dlg);
            document.body.appendChild(mask);
            setTimeout(function () { try { ta.focus(); } catch (e) {} }, 30);
            function close() { mask.remove(); document.removeEventListener('keydown', onKey, true); }
            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
                else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); doSave(); }
                else e.stopPropagation(); // 뷰어 쪽 넘기기 단축키(←→)가 글쓰기를 방해하지 않게
            }
            function doSave() { var nx = Object.assign({}, it, { n: ta.value.slice(0, 4000) }); close(); put(nx, prev); }
            document.addEventListener('keydown', onKey, true);
            x.addEventListener('click', close); cancel.addEventListener('click', close); save.addEventListener('click', doSave);
            mask.addEventListener('click', function (e) { if (e.target === mask) close(); });
            void isNew;
        }

        // ── 책갈피
        var bmBtn = o.bmButton || null;
        function bmHere() { for (var i = 0; i < items.length; i++) if (items[i].k === 'bm' && A.onPage(items[i])) return items[i]; return null; }
        // ★ (2026-11-03 00:11) 본문 위 책갈피 띠 — 단추 모양만으로는 '이 쪽에 책갈피가 있는지' 알기 어려웠음
        var ribbon = el('div', 'mcn-ribbon'); ribbon.setAttribute('aria-hidden', 'true');
        ribbon.innerHTML = '<svg viewBox="0 0 22 30"><path d="M0 0h22v30l-11-7-11 7z" fill="#1a73e8"/></svg>';
        document.body.appendChild(ribbon);
        function bmSync() {
            if (!bmBtn) return;
            var on = !!bmHere();
            if (on && o.toolbar) { try { var tb2 = o.toolbar.getBoundingClientRect(); ribbon.style.top = Math.max(0, Math.round(tb2.bottom)) + 'px'; } catch (e) {} }
            ribbon.classList.toggle('on', on);
            bmBtn.classList.toggle('on', on);
            var lb = on ? (T.bm_remove || 'Remove bookmark') : (T.bm_add || 'Add bookmark');
            bmBtn.title = lb; bmBtn.setAttribute('aria-label', lb); bmBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
        core.relocated = function () { hidePop(); bmSync(); badgeSoon(); };
        if (bmBtn) bmBtn.addEventListener('click', function () {
            var cur = bmHere();
            if (cur) { del(cur).then(function (ok) { if (ok) say(T.bm_removed || 'Bookmark removed'); }); return; }
            var h = A.here(); if (!h) return;
            var it = { id: newId(), k: 'bm', c: 'blue', q: h.q || '', n: '', ch: h.ch || '', p: (typeof h.p === 'number') ? h.p : -1, t: Math.floor(Date.now() / 1000) };
            Object.keys(h.pos || {}).forEach(function (key) { it[key] = h.pos[key]; });
            put(it).then(function (ok) { if (ok) say(T.bm_added || 'Bookmarked'); });
        });

        // ── ★ (2026-11-07 fix39) 메모 표 — 메모가 있는 형광펜·밑줄 글 끝(마지막 줄 오른쪽 위)에 작은 메모 단추
        //   위치는 뷰어가 알려 줌(adapter.rectOf(item) → 화면 좌표 {left,right,top,bottom} 또는 null) — 없으면 표를 안 그림(예전과 같음)
        //   쪽 넘김·스크롤·창 크기·노트 바뀜 때 다시 놓음 / 본문 칸(adapter.area()) 밖이면 숨김 / 누르면 그 메모 창
        var badges = {}, badgeT = 0;
        function badgeSoon() { if (!A.rectOf || badgeT) return; badgeT = requestAnimationFrame(function () { badgeT = 0; layoutBadges(); }); }
        core.badges = badgeSoon;
        function layoutBadges() {
            var want = {};
            var area = null; try { area = A.area ? A.area() : null; } catch (e) { area = null; }
            var top = 0, bottom = window.innerHeight;
            if (o.toolbar) { try { var tb = o.toolbar.getBoundingClientRect(); if (tb.bottom > 0 && tb.bottom < bottom / 2) top = tb.bottom; } catch (e) {} }
            if (area) { top = Math.max(top, area.top); bottom = Math.min(bottom, area.bottom); }
            items.forEach(function (it) {
                if (it.k === 'bm' || !it.n) return;
                var r = null; try { r = A.rectOf(it); } catch (e) { r = null; }
                if (!r) return;
                var x = Math.round(r.right - 7), y = Math.round(r.top - 13); // 글 끝 오른쪽 위 모서리(글자를 덜 가리게)
                if (r.bottom < top + 4 || r.top > bottom - 4) return;
                if (area && (r.right < area.left || r.left > area.right)) return;
                want[it.id] = 1;
                var b = badges[it.id];
                if (!b) {
                    b = el('button', 'mcn-mbadge'); b.type = 'button'; b.appendChild(svg('memo', 13));
                    b.addEventListener('mousedown', function (e) { e.preventDefault(); });
                    b.addEventListener('click', function (e) {
                        e.preventDefault(); e.stopPropagation();
                        var k = find(b.dataset.id); if (k < 0) return; hidePop(); memoDlg(Object.assign({}, items[k]), items[k], false);
                    });
                    ['touchstart', 'touchend'].forEach(function (ev) { b.addEventListener(ev, function (e) { e.stopPropagation(); }, { passive: true }); });
                    document.body.appendChild(b); badges[it.id] = b;
                }
                b.dataset.id = it.id;
                var tip = (T.memo_badge || T.memo || 'Note') + ': ' + String(it.n).replace(/\s+/g, ' ').slice(0, 80);
                b.title = tip; b.setAttribute('aria-label', tip);
                b.style.borderColor = LINE[it.c] || LINE.yellow;
                var bw = b.offsetWidth || 20;
                x = Math.max(2, Math.min(window.innerWidth - bw - 2, x)); y = Math.max(top + 1, y);
                b.style.left = x + 'px'; b.style.top = y + 'px'; b.hidden = false;
            });
            Object.keys(badges).forEach(function (id) { if (!want[id]) { badges[id].remove(); delete badges[id]; } });
        }
        window.addEventListener('resize', badgeSoon);
        document.addEventListener('scroll', badgeSoon, { capture: true, passive: true });

        // ── 노트 목록 창
        var panel = el('div', 'mcn-panel'); panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', T.panel || 'Notes');
        var ph = el('div', 'mcn-panel-h'); ph.appendChild(svg('notes', 18)); var pttl = el('span', 'mcn-ttl', T.panel || 'Notes'); ph.appendChild(pttl);
        var exp = el('button', 'mcn-x'); exp.type = 'button'; exp.title = T.export || 'Export'; exp.setAttribute('aria-label', exp.title); exp.appendChild(svg('dl', 18)); ph.appendChild(exp);
        var pclose = el('button', 'mcn-x'); pclose.type = 'button'; pclose.setAttribute('aria-label', T.close || 'Close'); pclose.appendChild(svg('x', 18)); ph.appendChild(pclose);
        var tabs = el('div', 'mcn-tabs'); var cf = el('div', 'mcn-cf'); var list = el('div', 'mcn-list');
        panel.appendChild(ph); panel.appendChild(tabs); panel.appendChild(cf); panel.appendChild(list);
        document.body.appendChild(panel);
        ['touchstart', 'touchmove', 'touchend', 'wheel', 'click'].forEach(function (ev) { panel.addEventListener(ev, function (e) { e.stopPropagation(); }, { passive: true }); });
        var tab = 'all', colorF = '';
        [['all', T.tab_all || 'All'], ['bm', T.tab_bm || 'Bookmarks'], ['mark', T.tab_mark || 'Highlights'], ['memo', T.tab_memo || 'Notes']].forEach(function (x) {
            var b = el('button', 'mcn-tab' + (x[0] === tab ? ' on' : ''), x[1]); b.type = 'button'; b.dataset.tab = x[0];
            b.addEventListener('click', function () { tab = x[0]; Array.prototype.forEach.call(tabs.children, function (y) { y.classList.toggle('on', y === b); }); render(); });
            tabs.appendChild(b);
        });
        if (T.color_filter) cf.appendChild(el('span', 'mcn-cf-lb', T.color_filter)); // ★ fix39 아래 색 단추는 '목록 거르기'(새 색 고르기 아님)
        var allB = el('button', 'mcn-cf-all on', T.all_colors || 'All'); allB.type = 'button'; allB.title = T.all_colors_tip || allB.textContent;
        allB.addEventListener('click', function () { colorF = ''; syncCf(); render(); }); cf.appendChild(allB);
        COLORS.forEach(function (c) {
            var b = el('button'); b.type = 'button'; b.style.background = FILL[c]; b.dataset.c = c; var nm = T['c_' + c] || c; b.title = (T.color_only || '%s').replace('%s', nm); b.setAttribute('aria-label', b.title);
            b.addEventListener('click', function () { colorF = (colorF === c ? '' : c); syncCf(); render(); }); cf.appendChild(b);
        });
        function syncCf() { allB.classList.toggle('on', !colorF); Array.prototype.forEach.call(cf.querySelectorAll('button[data-c]'), function (b) { b.classList.toggle('on', b.dataset.c === colorF); }); }
        function sorted() { return items.slice().sort(function (a, b) { var r = 0; try { r = A.compare(a, b); } catch (e) { r = 0; } return r || ((a.t || 0) - (b.t || 0)); }); }
        function render() {
            list.innerHTML = '';
            var arr = sorted().filter(function (it) {
                if (tab === 'bm' && it.k !== 'bm') return false;
                if (tab === 'mark' && it.k === 'bm') return false;
                if (tab === 'memo' && !it.n) return false;
                if (colorF && (it.k === 'bm' || it.c !== colorF)) return false;
                return true;
            });
            pttl.textContent = (T.panel || 'Notes') + ' (' + items.length + ')';
            if (!arr.length) { list.appendChild(el('div', 'mcn-empty', items.length ? (T.empty_filter || '—') : (T.empty || ''))); return; }
            arr.forEach(function (it) {
                var card = el('div', 'mcn-item'); card.tabIndex = 0;
                var hh = el('div', 'mcn-item-h');
                hh.appendChild(svg(it.k === 'bm' ? 'bm' : (it.k === 'ul' ? 'ul' : 'hl'), 14));
                hh.lastChild.style.color = it.k === 'bm' ? '#1a73e8' : (LINE[it.c] || LINE.yellow);
                hh.appendChild(el('span', 'mcn-ch', [it.ch, (it.p >= 0 ? it.p + '%' : ''), fmtDate(it.t)].filter(Boolean).join(' · ')));
                if (it.n && it.k !== 'bm') { var mk = el('span', 'mcn-mk'); mk.appendChild(svg('memo', 12)); mk.appendChild(document.createTextNode(T.memo || 'Note')); hh.insertBefore(mk, hh.lastChild); }
                var xb = el('button', 'mcn-x'); xb.type = 'button'; xb.setAttribute('aria-label', T.del || 'Delete'); xb.title = T.del || 'Delete'; xb.appendChild(svg('del', 15));
                xb.addEventListener('click', function (e) { e.stopPropagation(); if (!confirm(T.confirm_del || 'Delete?')) return; del(it); });
                hh.appendChild(xb);
                card.appendChild(hh);
                if (it.q) { var qq = el('div', 'mcn-item-q' + (it.k === 'ul' ? ' ul' : ''), it.q); qq.style.borderLeftColor = it.k === 'bm' ? '#8ab4f8' : (FILL[it.c] || FILL.yellow); card.appendChild(qq); }
                if (it.n) card.appendChild(el('div', 'mcn-item-n', it.n));
                var go = function () { if (window.innerWidth < 700) panel.classList.remove('show'); A.go(it); };
                card.addEventListener('click', go);
                card.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });
                list.appendChild(card);
            });
        }
        function refresh() { if (panel.classList.contains('show')) render(); else pttl.textContent = (T.panel || 'Notes') + ' (' + items.length + ')'; bmSync(); badgeSoon(); }
        core.toggle = function (force) {
            var on = (typeof force === 'boolean') ? force : !panel.classList.contains('show');
            if (on && o.toolbar) { try { var tb = o.toolbar.getBoundingClientRect(); panel.style.top = Math.max(0, Math.round(tb.bottom)) + 'px'; } catch (e) {} } // 머리줄이 두 줄이 되는 좁은 화면에서도 단추를 가리지 않게
            panel.classList.toggle('show', on); if (on) render();
        };
        core.isOpen = function () { return panel.classList.contains('show'); };
        cores.push(core);
        pclose.addEventListener('click', function () { core.toggle(false); });
        if (o.panelButton) o.panelButton.addEventListener('click', function () { core.toggle(); });

        // 내보내기(마크다운)
        exp.addEventListener('click', function () {
            var lines = ['# ' + (o.title || '') + ' — ' + (T.panel || 'Notes'), ''];
            sorted().forEach(function (it) {
                var head = [it.k === 'bm' ? (T.tab_bm || 'Bookmark') : (it.k === 'ul' ? (T.underline || 'Underline') : (T.highlight || 'Highlight')), it.ch, (it.p >= 0 ? it.p + '%' : '')].filter(Boolean).join(' · ');
                lines.push('## ' + head);
                if (it.q) lines.push('> ' + String(it.q).replace(/\n/g, '\n> '));
                if (it.n) { lines.push(''); lines.push(String(it.n)); }
                lines.push('');
            });
            var blob = new Blob([lines.join('\n')], { type: 'text/markdown;charset=utf-8' });
            var a = el('a'); a.href = URL.createObjectURL(blob); a.target = '_blank'; a.rel = 'noopener'; // 페이지 전환 스크립트가 이 링크를 가로채지 않게(target=_blank 는 건너뜀)
            a.download = String(o.title || 'notes').replace(/[\\\/:*?"<>|]+/g, '_').slice(0, 80) + '_notes.md';
            document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
        });

        return core;
    }

    window.MCNotes = { init: init, FILL: FILL, LINE: LINE, COLORS: COLORS,
        setListen: function (fn) { listenFn = (typeof fn === 'function') ? fn : null; },
        closePanels: function () { cores.forEach(function (c) { try { if (c.isOpen()) c.toggle(false); } catch (e) {} }); } };
})();
