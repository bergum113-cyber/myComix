/*!
 * book-tools.js — ★ (2026-11-01 08:18) EPUB·TXT 뷰어 공용 도구 (펜닐 요청: 전자책 앱과 비교해 빠진 기능)
 *   - 본문 검색 창(오른쪽): 찾은 줄 목록 · 누르면 그 위치 · ↑↓로 앞뒤 결과
 *   - 목록 창(왼쪽, TXT 목차용)
 *   - 듣기(TTS, 브라우저 음성): 지금 쪽부터 문장 단위로 읽고 읽는 문장을 칠함, 읽는 쪽을 따라 넘김 · 일시정지·멈춤·속도
 *   - 화면 꺼짐 방지(Wake Lock) · 작은 글 창(각주)
 * 뷰어마다 다른 부분(글 찾기·이동·문장 꺼내기)은 뷰어가 함수로 넘김. 글자는 모두 textContent 로만 넣음(책 글이 HTML 로 해석되지 않게)
 *
 * MCTools.search({ t, button, toolbar, bottom, run(q, isStale) → Promise<{hits:[{x, ch, p}], capped}>, go(hit, q) })
 * MCTools.list({ t, button, toolbar, bottom, side('l'|'r'), title, build() → Promise<[{label, level, active}]>, go(item) })
 * MCTools.tts({ t, button, note, bottom(px 또는 함수), onStart, source:{ start() → Promise<iter> } })   iter.next() → Promise<{text, show(), mark(), unmark()} | null>
 * MCTools.wake({ t, checkbox, note })
 * MCTools.note(title, text)
 * MCTools.sentences(text) → [[시작, 끝], …]
 * ★ (2026-11-01 11:22) 더 추가: MCTools.dim({ slider, value })  화면 어둡게(이 기기에 기억)
 *                              MCTools.readTime({ key, t, el })  읽은 시간(이 책·오늘, 이 기기) · MCTools.ping() 읽는 중 알림
 *                              MCTools.image(src, alt)            그림 크게 보기(누르면 원래 크기 ↔ 화면 맞춤)
 */
(function () {
    'use strict';
    if (window.MCTools) return;

    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    var ICON = {
        search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        up: '<path d="m18 15-6-6-6 6"/>',
        down: '<path d="m6 9 6 6 6-6"/>',
        list: '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
        play: '<polygon points="6 3 20 12 6 21 6 3"/>',
        pause: '<rect x="14" y="4" width="4" height="16" rx="1"/><rect x="6" y="4" width="4" height="16" rx="1"/>',
        stop: '<rect x="5" y="5" width="14" height="14" rx="2"/>',
        minus: '<path d="M5 12h14"/>',
        plus: '<path d="M5 12h14"/><path d="M12 5v14"/>'
    };
    function svg(name, size) {
        var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('width', size || 18); s.setAttribute('height', size || 18);
        s.setAttribute('fill', 'none'); s.setAttribute('stroke', 'currentColor'); s.setAttribute('stroke-width', '2');
        s.setAttribute('stroke-linecap', 'round'); s.setAttribute('stroke-linejoin', 'round'); s.setAttribute('aria-hidden', 'true');
        s.innerHTML = ICON[name]; // 고정 그림만
        return s;
    }
    function xbtn(icon, label, cls) {
        var b = el('button', cls || 'mct-x'); b.type = 'button'; b.title = label; b.setAttribute('aria-label', label); b.appendChild(svg(icon, 18)); return b;
    }

    function css() {
        if (document.getElementById('mct-css')) return;
        var s = document.createElement('style'); s.id = 'mct-css';
        var F = 'font:14px/1.45 system-ui,-apple-system,"Segoe UI","Malgun Gothic",sans-serif;';
        s.textContent =
            '.mct-panel{position:fixed;top:55px;bottom:45px;width:min(380px,92vw);z-index:1004;background:#fff;color:#202124;box-shadow:0 0 20px rgba(0,0,0,.14);display:flex;flex-direction:column;transition:transform .25s;' + F + '}' +
            '.mct-panel.r{right:0;border-left:1px solid #dadce0;transform:translateX(105%)}' +
            '.mct-panel.l{left:0;border-right:1px solid #dadce0;transform:translateX(-105%)}' +
            '.mct-panel.show{transform:none}' +
            '.mct-h{display:flex;align-items:center;gap:6px;padding:8px 10px;border-bottom:1px solid #e8eaed;font-weight:700}' +
            '.mct-h .mct-ttl{flex:1;min-width:0}' +
            '.mct-in{flex:1;min-width:0;height:36px;border:1px solid #dadce0;border-radius:18px;padding:0 12px;font:inherit;font-weight:400;color:inherit;background:#fff}' +
            '.mct-in:focus{outline:none;border-color:#1a73e8;box-shadow:0 0 0 3px rgba(26,115,232,.15)}' +
            '.mct-st{display:flex;align-items:center;gap:4px;padding:6px 12px;font-size:12px;color:#5f6368;min-height:34px}' +
            '.mct-st span{flex:1}' +
            '.mct-x{border:0;background:transparent;color:inherit;width:32px;height:32px;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;flex:0 0 auto;padding:0}' +
            '.mct-x:hover{background:rgba(0,0,0,.06)}.mct-x:disabled{opacity:.35;cursor:default}' +
            '.mct-list{flex:1;overflow:auto;padding:2px 8px 12px;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}' +
            '.mct-hit{display:block;width:100%;text-align:left;border:0;border-bottom:1px solid #f1f3f4;background:transparent;color:inherit;padding:8px 6px;cursor:pointer;font:inherit;font-size:13.5px;border-radius:6px}' +
            '.mct-hit:hover,.mct-hit:focus-visible{background:rgba(26,115,232,.08);outline:none}' +
            '.mct-hit.on{background:rgba(26,115,232,.14)}' +
            '.mct-hit small{display:block;color:#5f6368;font-size:11.5px;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
            '.mct-hit mark{background:#ffe066;color:inherit;padding:0 1px;border-radius:2px}' +
            '.mct-hit.lv1{padding-left:22px;font-size:13px}.mct-hit.lv2{padding-left:36px;font-size:12.5px}' +
            '.mct-empty{color:#5f6368;font-size:13px;padding:16px 4px;text-align:center}' +
            '.mct-player{position:fixed;left:50%;bottom:58px;transform:translateX(-50%);z-index:1500;display:none;align-items:center;gap:2px;padding:4px 6px;background:#202124;color:#fff;border-radius:24px;box-shadow:0 6px 20px rgba(0,0,0,.3);' + F + 'font-size:13px;user-select:none;-webkit-user-select:none}' +
            '.mct-player.show{display:flex}' +
            '.mct-player .mct-x{width:36px;height:36px;border-radius:18px}' +
            '.mct-player .mct-x:hover{background:rgba(255,255,255,.14)}' +
            '.mct-player .mct-rate{min-width:42px;text-align:center;font-variant-numeric:tabular-nums}' +
            /* ★ (2026-11-07 fix39) 목소리 고르기 — 기기·브라우저에 깔린 음성 중에서(없으면 숨김) */
            '.mct-player .mct-voice{max-width:150px;height:30px;margin:0 2px 0 4px;padding:0 6px;border-radius:15px;border:1px solid rgba(255,255,255,.3);background:#2d2e30;color:#fff;font:inherit;font-size:12px;cursor:pointer}' +
            '.mct-player .mct-voice:focus-visible{outline:2px solid #8ab4f8;outline-offset:1px}' +
            '@media (max-width:420px){.mct-player .mct-voice{max-width:96px}}' +
            '.mct-player .mct-sep{width:1px;height:20px;background:rgba(255,255,255,.25);margin:0 4px}' +
            '.mct-mask{position:fixed;inset:0;z-index:3100;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;padding:16px}' +
            '.mct-note{background:#fff;color:#202124;border-radius:12px;width:100%;max-width:520px;max-height:70vh;display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,.35);' + F + '}' +
            '.mct-note-b{padding:12px 16px 16px;overflow:auto;white-space:pre-wrap;word-break:break-word;line-height:1.65}' +
            '.mct-toast{position:fixed;left:50%;bottom:70px;transform:translateX(-50%);z-index:3200;background:#202124;color:#fff;padding:8px 14px;border-radius:18px;font:13px system-ui,-apple-system,"Malgun Gothic",sans-serif;opacity:0;transition:opacity .2s;pointer-events:none;max-width:90vw;text-align:center}' +
            '.mct-toast.show{opacity:.95}' +
            // 화면 어둡게(누르기는 그대로 통과) · 그림 크게 보기
            '.mct-dim{position:fixed;inset:0;background:#000;pointer-events:none;z-index:2900;display:none}' +
            '.mct-img{position:fixed;inset:0;z-index:3150;background:rgba(0,0,0,.92);display:flex;align-items:center;justify-content:center;overflow:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}' +
            '.mct-img img{max-width:96vw;max-height:92vh;object-fit:contain;cursor:zoom-in;background:#fff;user-select:none;-webkit-user-select:none}' +
            '.mct-img.big{display:block;text-align:center}.mct-img.big img{max-width:none;max-height:none;cursor:zoom-out;margin:40px auto}' +
            '.mct-img .mct-img-x{position:fixed;top:10px;right:10px;width:40px;height:40px;border-radius:20px;background:rgba(255,255,255,.18);color:#fff}' +
            // TXT: 찾은 글·읽는 문장(CSS Custom Highlight API)
            '::highlight(mct-hit){background-color:#ff922b99;color:inherit}' +
            '::highlight(mct-tts){background-color:#74c0fc80;color:inherit}' +
            // 어두운·세피아 테마(EPUB body.theme-*, TXT body.dark-mode·sepia-mode)
            'body.theme-dark .mct-panel,body.dark-mode .mct-panel,body.theme-dark .mct-note,body.dark-mode .mct-note{background:#1e1e1e;color:#e8eaed;border-color:#3c4043}' +
            'body.theme-dark .mct-h,body.dark-mode .mct-h{border-color:#3c4043}' +
            'body.theme-dark .mct-in,body.dark-mode .mct-in{background:#2d2d2d;border-color:#3c4043;color:#e8eaed}' +
            'body.theme-dark .mct-st,body.dark-mode .mct-st,body.theme-dark .mct-hit small,body.dark-mode .mct-hit small,body.theme-dark .mct-empty,body.dark-mode .mct-empty{color:#9aa0a6}' +
            'body.theme-dark .mct-hit,body.dark-mode .mct-hit{border-color:#2d2d2d}' +
            'body.theme-dark .mct-hit mark,body.dark-mode .mct-hit mark{background:#8a6d00;color:#fff}' +
            'body.theme-dark .mct-x:hover,body.dark-mode .mct-x:hover{background:rgba(255,255,255,.08)}' +
            'body.theme-sepia .mct-panel,body.sepia-mode .mct-panel,body.theme-sepia .mct-note,body.sepia-mode .mct-note{background:#fbf6ea;color:#3d2f22}' +
            'body.theme-sepia .mct-in,body.sepia-mode .mct-in{background:#fffdf7}';
        (document.head || document.documentElement).appendChild(s);
    }

    var toastEl = null, toastT = 0;
    function say(msg, ms) {
        css();
        if (!toastEl) { toastEl = el('div', 'mct-toast'); document.body.appendChild(toastEl); }
        toastEl.textContent = msg; toastEl.classList.add('show'); clearTimeout(toastT);
        toastT = setTimeout(function () { toastEl.classList.remove('show'); }, ms || 1800);
    }

    // ── 옆 창(검색·목차 공용) ──
    var panels = [];
    function panel(side, o) {
        css();
        var p = el('div', 'mct-panel ' + side); p.setAttribute('role', 'dialog');
        var h = el('div', 'mct-h'); var body = el('div', 'mct-list');
        p.appendChild(h); p.appendChild(body); document.body.appendChild(p);
        ['touchstart', 'touchmove', 'touchend', 'wheel', 'click', 'keydown'].forEach(function (ev) { p.addEventListener(ev, function (e) { e.stopPropagation(); }, { passive: true }); });
        var api = {
            root: p, head: h, body: body,
            isOpen: function () { return p.classList.contains('show'); },
            show: function (on) {
                if (on) {
                    panels.forEach(function (x) { if (x !== api && x.isOpen()) x.show(false); }); // 한 번에 하나만
                    try { if (o.toolbar) p.style.top = Math.max(0, Math.round(o.toolbar.getBoundingClientRect().bottom)) + 'px'; } catch (e) {}
                    if (typeof o.bottom === 'number') p.style.bottom = o.bottom + 'px';
                }
                p.classList.toggle('show', !!on);
            }
        };
        panels.push(api);
        return api;
    }

    // ── 본문 검색 ──
    function search(o) {
        var T = o.t || {};
        var P = panel('r', o); P.root.setAttribute('aria-label', T.search || 'Search'); P.root.classList.add('mct-search');
        var form = el('form'); form.style.cssText = 'display:flex;flex:1;min-width:0;gap:6px;margin:0';
        var input = el('input', 'mct-in'); input.type = 'search'; input.placeholder = T.search_ph || ''; input.setAttribute('aria-label', T.search || 'Search'); input.maxLength = 100;
        input.setAttribute('enterkeyhint', 'search');
        form.appendChild(input);
        var close = xbtn('x', T.close || 'Close');
        P.head.appendChild(svg('search', 18)); P.head.appendChild(form); P.head.appendChild(close);
        var st = el('div', 'mct-st'); var stTxt = el('span'); var upB = xbtn('up', T.prev || 'Previous'), dnB = xbtn('down', T.next || 'Next');
        st.appendChild(stTxt); st.appendChild(upB); st.appendChild(dnB);
        P.root.insertBefore(st, P.body);
        var hits = [], cur = -1, runId = 0, lastQ = '';
        function setNav() { upB.disabled = !(hits.length && cur > 0); dnB.disabled = !(hits.length && cur < hits.length - 1); }
        function mark(box, text, q) {
            // 찾은 말만 <mark>(글자는 textContent 로)
            var low = text.toLowerCase(), ql = q.toLowerCase(), i = 0, k;
            if (low.length !== text.length) { box.appendChild(document.createTextNode(text)); return; }
            while ((k = low.indexOf(ql, i)) >= 0 && ql) {
                if (k > i) box.appendChild(document.createTextNode(text.slice(i, k)));
                box.appendChild(el('mark', null, text.slice(k, k + q.length)));
                i = k + q.length;
            }
            if (i < text.length) box.appendChild(document.createTextNode(text.slice(i)));
        }
        function render(q, capped) {
            P.body.innerHTML = '';
            if (!hits.length) { P.body.appendChild(el('div', 'mct-empty', T.search_none || 'No results')); stTxt.textContent = ''; setNav(); return; }
            stTxt.textContent = String(T.search_count || '%d').replace('%d', hits.length) + (capped ? ' · ' + String(T.search_more || '').replace('%d', hits.length) : '');
            hits.forEach(function (hh, i) {
                var b = el('button', 'mct-hit'); b.type = 'button';
                var meta = [hh.ch || '', (typeof hh.p === 'number' && hh.p >= 0) ? hh.p + '%' : ''].filter(Boolean).join(' · ');
                if (meta) b.appendChild(el('small', null, meta));
                var sp = el('span'); mark(sp, hh.x || '', q); b.appendChild(sp);
                b.addEventListener('click', function () { try { b.blur(); } catch (e) {} goTo(i, true); });
                P.body.appendChild(b);
            });
            setNav();
        }
        function goTo(i, fromList) {
            if (i < 0 || i >= hits.length) return;
            cur = i;
            Array.prototype.forEach.call(P.body.children, function (c, k) { c.classList.toggle('on', k === i); });
            var c = P.body.children[i]; if (c && !fromList) { try { c.scrollIntoView({ block: 'nearest' }); } catch (e) {} }
            setNav();
            if (fromList && window.innerWidth < 700) P.show(false); // 휴대폰은 창을 닫고 본문을 보여 줌(↑↓는 다시 열어서)
            try { o.go(hits[i], lastQ); } catch (e) {}
        }
        upB.addEventListener('click', function () { goTo(cur - 1, false); });
        dnB.addEventListener('click', function () { goTo(cur + 1, false); });
        function run() {
            var q = input.value.replace(/\s+/g, ' ').trim();
            if (!q) { hits = []; cur = -1; P.body.innerHTML = ''; stTxt.textContent = ''; setNav(); return; }
            if (q === lastQ && hits.length) return;
            var my = ++runId; lastQ = q; hits = []; cur = -1;
            P.body.innerHTML = ''; stTxt.textContent = T.searching || '…'; setNav();
            Promise.resolve().then(function () { return o.run(q, function () { return my !== runId; }); }).then(function (r) {
                if (my !== runId) return;
                hits = (r && r.hits) || []; render(q, r && r.capped);
            }).catch(function () { if (my === runId) { hits = []; render(q, false); } });
        }
        form.addEventListener('submit', function (e) { e.preventDefault(); lastQ = ''; run(); try { input.blur(); } catch (x) {} });
        var deb = 0;
        input.addEventListener('input', function () { clearTimeout(deb); deb = setTimeout(run, 500); });
        input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); P.show(false); } });
        close.addEventListener('click', function () { P.show(false); });
        if (o.button) o.button.addEventListener('click', function () {
            var on = !P.isOpen(); P.show(on);
            if (on) setTimeout(function () { try { input.focus(); input.select(); } catch (e) {} }, 60);
        });
        return P;
    }

    // ── 목록 창(TXT 목차) ──
    function list(o) {
        var T = o.t || {};
        var P = panel(o.side === 'r' ? 'r' : 'l', o); P.root.setAttribute('aria-label', o.title || ''); P.root.classList.add('mct-toc'); // side:'r' 이면 오른쪽(TXT — 왼쪽 창이 본문 첫 글자 위치 재기를 가리지 않게)
        P.head.appendChild(svg('list', 18)); P.head.appendChild(el('span', 'mct-ttl', o.title || ''));
        var close = xbtn('x', T.close || 'Close'); P.head.appendChild(close);
        close.addEventListener('click', function () { P.show(false); });
        var busy = false;
        function open() {
            P.show(true);
            if (busy) return; busy = true;
            P.body.innerHTML = ''; P.body.appendChild(el('div', 'mct-empty', T.toc_loading || '…'));
            Promise.resolve().then(o.build).then(function (items) {
                P.body.innerHTML = '';
                if (!items || !items.length) { P.body.appendChild(el('div', 'mct-empty', T.toc_none || '')); return; }
                var act = null;
                items.forEach(function (it) {
                    var b = el('button', 'mct-hit' + (it.level ? ' lv' + Math.min(2, it.level) : '') + (it.active ? ' on' : ''), it.label); b.type = 'button';
                    b.addEventListener('click', function () { try { b.blur(); } catch (e) {} if (window.innerWidth < 700) P.show(false); try { o.go(it); } catch (e) {} }); // ★ (2026-11-04 00:29) 초점을 빼야 ←→ 키로 쪽이 넘어감
                    P.body.appendChild(b); if (it.active) act = b;
                });
                if (act) setTimeout(function () { try { act.scrollIntoView({ block: 'center' }); } catch (e) {} }, 30);
            }).catch(function () { P.body.innerHTML = ''; P.body.appendChild(el('div', 'mct-empty', T.toc_none || '')); })
              .then(function () { busy = false; });
        }
        if (o.button) o.button.addEventListener('click', function () { if (P.isOpen()) P.show(false); else open(); });
        return P;
    }

    // ── 문장 나누기: 마침표·물음표·느낌표·말줄임(뒤 따옴표·괄호 포함) 뒤, 줄바꿈 — 너무 긴 문장은 쉼표·띄어쓰기에서 나눔(긴 말은 브라우저가 끊기도 함) ──
    function sentences(text) {
        var out = [], n = text.length, i = 0;
        var END = /[.!?。！？…]/, CLOSE = /["'”’」』)\]〉》】]/;
        while (i < n) {
            while (i < n && /\s/.test(text.charAt(i))) i++;
            if (i >= n) break;
            var j = i, cut = -1;
            while (j < n) {
                var ch = text.charAt(j);
                if (ch === '\n') { cut = j; break; }
                if (END.test(ch)) {
                    var k = j + 1;
                    while (k < n && (END.test(text.charAt(k)) || CLOSE.test(text.charAt(k)))) k++;
                    if (k >= n || /\s/.test(text.charAt(k))) { cut = k; break; }
                    j = k; continue;
                }
                if (j - i >= 220) { // 너무 김 — 가까운 쉼표·띄어쓰기에서
                    var back = j; while (back > i + 120 && !/[,，、\s]/.test(text.charAt(back))) back--;
                    cut = back > i + 120 ? back + 1 : j; break;
                }
                j++;
            }
            if (cut < 0) cut = n;
            var a = i, b = cut; while (b > a && /\s/.test(text.charAt(b - 1))) b--;
            if (b > a && /[\p{L}\p{N}]/u.test(text.slice(a, b))) out.push([a, b]);
            i = cut;
        }
        return out;
    }

    // ── 화면 꺼짐 방지 ──
    var wakeLock = null, wakeWant = { user: false, tts: false }, wakeAsking = false;
    function wakeSupported() { return !!(navigator.wakeLock && window.isSecureContext); }
    function wakeSync() {
        var want = wakeWant.user || wakeWant.tts;
        if (want && !wakeLock && !wakeAsking && wakeSupported() && document.visibilityState === 'visible') {
            wakeAsking = true; // 요청 중 또 부르면 잠금이 두 개 생겨 하나가 안 풀리던 것 방지
            navigator.wakeLock.request('screen').then(function (l) {
                wakeAsking = false;
                wakeLock = l; l.addEventListener('release', function () { if (wakeLock === l) wakeLock = null; });
                if (!(wakeWant.user || wakeWant.tts)) { try { l.release(); } catch (e) {} wakeLock = null; }
            }).catch(function () { wakeAsking = false; });
        } else if (!want && wakeLock) { try { wakeLock.release(); } catch (e) {} wakeLock = null; }
    }
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') wakeSync(); }); // 다른 앱 갔다 오면 다시 잡음
    function wake(o) {
        var cb = o.checkbox; if (!cb) return;
        if (!wakeSupported()) {
            cb.checked = false; cb.disabled = true;
            if (o.note) { o.note.textContent = (o.t && o.t.wake_none) || ''; o.note.style.display = ''; }
            return;
        }
        var on = false; try { on = localStorage.getItem('mct_wake') === '1'; } catch (e) {}
        cb.checked = on; wakeWant.user = on; wakeSync();
        cb.addEventListener('change', function () {
            wakeWant.user = !!cb.checked; try { localStorage.setItem('mct_wake', cb.checked ? '1' : '0'); } catch (e) {}
            wakeSync();
        });
    }

    // ── 듣기(TTS) ──
    function ttsSupported() { return !!(window.speechSynthesis && window.SpeechSynthesisUtterance); }
    function tts(o) {
        var T = o.t || {};
        var btn = o.button;
        if (!ttsSupported()) {
            if (btn) { btn.disabled = true; btn.title = T.tts_none || ''; }
            if (o.note) { o.note.textContent = T.tts_none || ''; o.note.style.display = ''; }
            return null;
        }
        css();
        var synth = window.speechSynthesis;
        var bar = el('div', 'mct-player'); bar.setAttribute('role', 'toolbar'); bar.setAttribute('aria-label', T.tts || 'Listen');
        var bPlay = xbtn('pause', T.tts_pause || 'Pause'), bStop = xbtn('stop', T.tts_stop || 'Stop');
        var bSlow = xbtn('minus', T.tts_slower || 'Slower'), bFast = xbtn('plus', T.tts_faster || 'Faster'), rateEl = el('span', 'mct-rate');
        bar.appendChild(bPlay); bar.appendChild(bStop); bar.appendChild(el('span', 'mct-sep')); bar.appendChild(bSlow); bar.appendChild(rateEl); bar.appendChild(bFast);
        var vSel = el('select', 'mct-voice'); vSel.title = T.tts_voice || 'Voice'; vSel.setAttribute('aria-label', vSel.title); vSel.hidden = true; // ★ fix39 목소리
        bar.appendChild(vSel);
        document.body.appendChild(bar);
        ['touchstart', 'touchend', 'click', 'mousedown'].forEach(function (ev) { bar.addEventListener(ev, function (e) { e.stopPropagation(); }, { passive: true }); });
        bar.addEventListener('keydown', function (e) { if (/^(Arrow|Page|Home|End)/.test(e.key) || e.key === ' ') e.stopPropagation(); }); // 막대 단추에서 누른 키가 쪽을 넘기지 않게
        var rate = 1; try { var rr = parseFloat(localStorage.getItem('mct_tts_rate')); if (rr >= 0.5 && rr <= 2.5) rate = rr; } catch (e) {}
        function showRate() { rateEl.textContent = rate.toFixed(1) + '×'; }
        showRate();
        var state = 'off', it = null, cur = null, gen = 0, keepT = 0;
        var speaking = false, errRun = 0; // ★ (2026-11-01 17:05) 말하는 중인지(문장 사이엔 false) · 연달아 난 오류 수
        function setPlayIcon(playing) {
            bPlay.innerHTML = ''; bPlay.appendChild(svg(playing ? 'pause' : 'play', 18));
            var lb = playing ? (T.tts_pause || 'Pause') : (T.tts_resume || 'Resume'); bPlay.title = lb; bPlay.setAttribute('aria-label', lb);
        }
        var voices = [];
        // ★ (2026-11-07 fix39) 고른 목소리(이 기기에 기억 — 기기마다 깔린 음성이 달라 서버에 두지 않음). 빈 값 = 자동(글 언어에 맞는 것)
        var voicePick = ''; try { voicePick = localStorage.getItem('mct_tts_voice') || ''; } catch (e) {}
        function voiceKey(v) { return (v.voiceURI || v.name || '') + ''; }
        function fillVoices() {
            var keep = voicePick; vSel.innerHTML = '';
            var o0 = el('option', null, T.tts_voice_auto || 'Auto'); o0.value = ''; vSel.appendChild(o0);
            var list = voices.filter(function (v) { return v && v.lang; }).slice();
            var pri = function (v) { var l = v.lang.toLowerCase(); return /^ko/.test(l) ? 0 : /^en/.test(l) ? 1 : /^ja/.test(l) ? 2 : /^zh/.test(l) ? 3 : 4; };
            list.sort(function (a, b) { return (pri(a) - pri(b)) || a.lang.localeCompare(b.lang) || a.name.localeCompare(b.name); });
            var groups = {};
            list.forEach(function (v) {
                var g = v.lang.replace('_', '-');
                if (!groups[g]) { groups[g] = el('optgroup'); groups[g].label = g; vSel.appendChild(groups[g]); }
                var op = el('option', null, v.name + (v.localService ? '' : ' ☁')); op.value = voiceKey(v); groups[g].appendChild(op);
            });
            if (T.tts_voice_more) { var oh = el('option', null, T.tts_voice_more); oh.value = '__more'; vSel.appendChild(oh); } // ★ fix40 목소리 더 받는 법(안내 창)
            vSel.value = keep; if (vSel.value !== keep) vSel.value = ''; // 그 목소리가 이 기기에 없으면 자동
            vSel.hidden = !list.length;
        }
        function loadVoices() { try { voices = synth.getVoices() || []; } catch (e) { voices = []; } fillVoices(); }
        loadVoices(); try { synth.addEventListener('voiceschanged', loadVoices); } catch (e) {}
        vSel.addEventListener('change', function () {
            if (vSel.value === '__more') { vSel.value = voicePick; if (vSel.value !== voicePick) vSel.value = ''; note(T.tts_voice_more || '', T.tts_voice_help || ''); return; }
            voicePick = vSel.value; try { if (voicePick) localStorage.setItem('mct_tts_voice', voicePick); else localStorage.removeItem('mct_tts_voice'); } catch (e) {}
            // 말하는 중이면 그 문장을 새 목소리로 다시(속도 바꿀 때와 같은 방식)
            if (state === 'play' && cur && speaking) { gen++; speaking = false; try { synth.cancel(); } catch (e) {} try { cur.unmark && cur.unmark(); } catch (e) {} speak(cur); }
        });
        vSel.addEventListener('keydown', function (e) { e.stopPropagation(); }); // 고르는 중 ←→ 가 쪽을 넘기지 않게
        var lastLang = ''; // ★ fix46 숫자·기호만 있는 문장("(2)", "1.", "***")은 앞 문장 언어 그대로 — 예전엔 영어로 보고 영어 목소리가 읽었음
        function langOf(t) {
            var r;
            if (/[가-힣]/.test(t)) r = 'ko-KR';
            else if (/[぀-ヿ]/.test(t)) r = 'ja-JP';
            else if (/[一-鿿]/.test(t)) r = 'zh-CN';
            else if (!/[A-Za-z\u00C0-\u024F\u0370-\u04FF]/.test(t)) r = lastLang || ((document.documentElement.lang && !/^ko/i.test(document.documentElement.lang)) ? document.documentElement.lang : 'ko-KR');
            else r = (document.documentElement.lang && !/^ko/i.test(document.documentElement.lang)) ? document.documentElement.lang : 'en-US';
            lastLang = r; return r;
        }
        function voiceFor(lang) {
            var p = lang.slice(0, 2).toLowerCase(), best = null;
            if (voicePick) { // 고른 목소리가 이 글 언어와 같으면 그것(다른 언어 글은 자동 — 한국어 목소리로 영어 문장을 읽지 않게)
                for (var j = 0; j < voices.length; j++) { var w = voices[j]; if (w && voiceKey(w) === voicePick && w.lang && w.lang.replace('_', '-').toLowerCase().indexOf(p) === 0) return w; }
            }
            for (var i = 0; i < voices.length; i++) {
                var v = voices[i]; if (!v || !v.lang) continue;
                if (v.lang.replace('_', '-').toLowerCase().indexOf(p) === 0) { if (!best || (v.localService && !best.localService)) best = v; }
            }
            return best;
        }
        function keepAlive() {
            // 듣는 동안은 손을 안 대도 자동 로그아웃되지 않게(1분마다 '활동'으로 알림 — 자동 로그아웃 스크립트가 서버 세션도 늘림)
            clearInterval(keepT);
            keepT = setInterval(function () { try { window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Unidentified' })); } catch (e) {} }, 60000);
        }
        function speak(s) {
            var my = ++gen;
            cur = s;
            Promise.resolve().then(function () { return s.show && s.show(); }).catch(function () {}).then(function () {
                if (my !== gen || state !== 'play') return;
                try { s.mark && s.mark(); } catch (e) {}
                var u = new SpeechSynthesisUtterance(s.text);
                var lg = langOf(s.text); u.lang = lg; var v = voiceFor(lg); if (v) u.voice = v;
                u.rate = rate;
                var done = false;
                var fin = function (ok) {
                    if (done) return; done = true;
                    if (my !== gen) return; // 멈춤·속도 바꿈으로 끊긴 것
                    speaking = false;
                    try { s.unmark && s.unmark(); } catch (e) {}
                    // 음성 엔진 오류가 연달아 나면(음성 없음·허용 안 됨 등) 멈춤 — 그대로 두면 문장마다 쪽만 넘어가 저장 위치가 책 뒤로 밀림
                    errRun = ok ? 0 : errRun + 1;
                    if (errRun >= 3) { stop(); say(T.tts_none || ''); return; }
                    if (state === 'play') step(); else if (!ok) stop();
                };
                u.onend = function () { fin(true); };
                u.onerror = function (e) { if (e && (e.error === 'interrupted' || e.error === 'canceled')) return; fin(false); };
                speaking = true;
                try { synth.speak(u); } catch (e) { fin(false); }
            });
        }
        function step() {
            var my = gen;
            it.next().then(function (s) {
                if (state === 'pause' && s) { cur = s; return; } // 다음 문장을 받는 사이 일시정지 → 이어 듣기 때 이 문장부터(건너뛰지 않게)
                if (state !== 'play' || my !== gen) return;
                if (!s) { stop(); say(T.tts_end || ''); return; }
                ping(); // 듣는 중도 읽은 시간에 셈
                speak(s);
            }).catch(function () { stop(); });
        }
        function start(from) { // ★ (2026-11-07 fix40) from = 듣기 시작 자리(뷰어가 정함 — 고른 글 등). 없으면 예전처럼 지금 화면 처음부터
            stop();
            try { var bt = (typeof o.bottom === 'function') ? o.bottom() : o.bottom; bar.style.bottom = (typeof bt === 'number') ? bt + 'px' : ''; } catch (e) {} // 뷰어 아래 단추를 가리지 않게
            // 아이폰 사파리 등은 '누른 그 순간'에 말하기를 한 번 시작해야 그 뒤 말하기를 허용함 → 소리 없는 짧은 말로 먼저 열어 둠
            try { var w0 = new SpeechSynthesisUtterance(' '); w0.volume = 0; synth.speak(w0); } catch (e) {}
            state = 'play'; bar.classList.add('show'); setPlayIcon(true);
            wakeWant.tts = true; wakeSync(); keepAlive();
            Promise.resolve().then(function () { return o.source.start(from); }).then(function (iter) {
                if (state === 'off') return;
                if (!iter) { stop(); return; }
                it = iter;
                if (state === 'play') step(); // 준비되기 전에 일시정지했으면 이어 듣기 때 시작
            }).catch(function () { stop(); });
        }
        function pause() {
            if (state !== 'play') return;
            state = 'pause'; gen++; speaking = false; try { synth.cancel(); } catch (e) {} setPlayIcon(false);
            clearInterval(keepT); wakeWant.tts = false; wakeSync(); // 일시정지 중엔 '읽는 중' 알림·화면 유지 멈춤(자리를 비우면 자동 로그아웃되게)
        }
        function resume() {
            if (state !== 'pause') return;
            state = 'play'; setPlayIcon(true); errRun = 0;
            wakeWant.tts = true; wakeSync(); keepAlive();
            if (cur) { try { cur.unmark && cur.unmark(); } catch (e) {} speak(cur); } else if (it) step(); // 끊긴 문장을 처음부터 다시
        }
        function stop() {
            gen++; state = 'off'; speaking = false; errRun = 0;
            try { synth.cancel(); } catch (e) {}
            try { cur && cur.unmark && cur.unmark(); } catch (e) {}
            cur = null; it = null; bar.classList.remove('show');
            clearInterval(keepT); wakeWant.tts = false; wakeSync();
        }
        function setRate(r) {
            rate = Math.round(Math.max(0.5, Math.min(2.5, r)) * 10) / 10; showRate();
            try { localStorage.setItem('mct_tts_rate', String(rate)); } catch (e) {}
            // 말하는 중일 때만 그 문장을 새 속도로 다시(문장 사이면 다음 문장부터 새 속도 — 앞 문장 반복·다음 문장 건너뜀 방지)
            if (state === 'play' && cur && speaking) { gen++; speaking = false; try { synth.cancel(); } catch (e) {} try { cur.unmark && cur.unmark(); } catch (e) {} speak(cur); }
        }
        bPlay.addEventListener('click', function () { if (state === 'play') pause(); else resume(); });
        bStop.addEventListener('click', stop);
        bSlow.addEventListener('click', function () { setRate(rate - 0.1); });
        bFast.addEventListener('click', function () { setRate(rate + 0.1); });
        if (btn) btn.addEventListener('click', function () { start(); if (o.onStart) try { o.onStart(); } catch (e) {} });
        function startAt(from) { start(from); if (o.onStart) try { o.onStart(); } catch (e) {} }
        window.addEventListener('pagehide', function () { try { synth.cancel(); } catch (e) {} });
        return { start: start, startAt: startAt, stop: stop, pause: pause, isOn: function () { return state !== 'off'; } };
    }

    // ── 작은 글 창(각주) ──
    function note(title, text) {
        css();
        var mask = el('div', 'mct-mask');
        var box = el('div', 'mct-note'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
        var h = el('div', 'mct-h'); h.appendChild(el('span', 'mct-ttl', title || ''));
        var x = xbtn('x', (window._vi18n && window._vi18n.close) || 'Close'); h.appendChild(x);
        var b = el('div', 'mct-note-b', text || '');
        box.appendChild(h); box.appendChild(b); mask.appendChild(box); document.body.appendChild(mask);
        function close() { mask.remove(); document.removeEventListener('keydown', onKey, true); }
        function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); } else if (/^Arrow|^Page/.test(e.key)) e.stopPropagation(); }
        document.addEventListener('keydown', onKey, true);
        x.addEventListener('click', close);
        mask.addEventListener('click', function (e) { if (e.target === mask) close(); });
        ['touchstart', 'touchend', 'wheel'].forEach(function (ev) { mask.addEventListener(ev, function (e) { e.stopPropagation(); }, { passive: true }); });
        setTimeout(function () { try { x.focus(); } catch (e) {} }, 30);
        return close;
    }


    // ── 화면 어둡게(밤에 읽을 때 — 기기 밝기와 별도로 화면 위에 반투명 검은 막) ──
    var dimEl = null;
    function dimApply(v) {
        v = Math.max(0, Math.min(70, parseInt(v, 10) || 0));
        if (!dimEl) { css(); dimEl = el('div', 'mct-dim'); dimEl.setAttribute('aria-hidden', 'true'); document.body.appendChild(dimEl); }
        dimEl.style.opacity = String(v / 100); dimEl.style.display = v ? 'block' : 'none';
        return v;
    }
    function dim(o) {
        var v = 0; try { v = parseInt(localStorage.getItem('mct_dim'), 10) || 0; } catch (e) {}
        v = dimApply(v);
        var sl = o && o.slider; if (!sl) return;
        sl.value = v; if (o.value) o.value.textContent = v + '%';
        sl.addEventListener('input', function () {
            var n = dimApply(sl.value); if (o.value) o.value.textContent = n + '%';
            try { localStorage.setItem('mct_dim', String(n)); } catch (e) {}
        });
    }

    // ── 읽은 시간(이 기기·이 브라우저에만 기록) — 화면이 보이고 최근 3분 안에 움직임(넘김·스크롤·누름)이 있을 때만 셈 ──
    var lastAct = Date.now();
    function ping() { lastAct = Date.now(); }
    ['keydown', 'pointerdown', 'wheel', 'touchstart', 'scroll'].forEach(function (ev) { window.addEventListener(ev, ping, { passive: true, capture: true }); });
    function fmtDur(sec, T) {
        var m = Math.floor(sec / 60), h = Math.floor(m / 60); m = m % 60;
        if (h > 0) return String((T && T.rt_h) || '%d h %d m').replace('%d', h).replace('%d', m);
        return String((T && T.rt_m) || '%d m').replace('%d', Math.max(sec > 0 && m === 0 ? 1 : m, 0));
    }
    function dayKey() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
    function rtLoad() { try { var o = JSON.parse(localStorage.getItem('mct_rt') || '{}'); if (o && typeof o === 'object') return { b: o.b || {}, d: o.d || {} }; } catch (e) {} return { b: {}, d: {} }; }
    function rtSave(o) {
        // 오래된 것 정리(책 300권·60일까지)
        var bk = Object.keys(o.b); if (bk.length > 300) { bk.sort(function (x, y) { return (o.b[x].t || 0) - (o.b[y].t || 0); }); bk.slice(0, bk.length - 300).forEach(function (k) { delete o.b[k]; }); }
        var dk = Object.keys(o.d).sort(); if (dk.length > 60) dk.slice(0, dk.length - 60).forEach(function (k) { delete o.d[k]; });
        try { localStorage.setItem('mct_rt', JSON.stringify(o)); } catch (e) {}
    }
    function readTime(o) {
        var key = String(o.key || '').slice(0, 300), T = o.t || {}, out = o.el;
        var STEP = 15;
        function show() {
            if (!out) return;
            var d = rtLoad(), b = d.b[key] ? d.b[key].s || 0 : 0, t = d.d[dayKey()] || 0;
            out.textContent = String(T.rt_line || '%s · %s').replace('%s', fmtDur(b, T)).replace('%s', fmtDur(t, T));
        }
        show();
        setInterval(function () {
            if (document.visibilityState !== 'visible' || Date.now() - lastAct > 180000) return;
            var d = rtLoad(), k = dayKey();
            var e = d.b[key] || { s: 0 }; e.s = (e.s || 0) + STEP; e.t = Math.floor(Date.now() / 1000); d.b[key] = e;
            d.d[k] = (d.d[k] || 0) + STEP;
            rtSave(d); show();
        }, STEP * 1000);
        return { show: show };
    }

    // ── 그림 크게 보기 ──
    function image(src, alt) {
        if (!src) return;
        css();
        var mask = el('div', 'mct-img'); mask.setAttribute('role', 'dialog'); mask.setAttribute('aria-modal', 'true');
        var im = el('img'); im.src = src; im.alt = alt || ''; im.draggable = false;
        var x = xbtn('x', (window._vi18n && window._vi18n.close) || 'Close', 'mct-x mct-img-x');
        mask.appendChild(im); mask.appendChild(x); document.body.appendChild(mask);
        var big = false;
        im.addEventListener('click', function (e) { e.stopPropagation(); big = !big; mask.classList.toggle('big', big); }); // 누르면 원래 크기 ↔ 화면 맞춤
        function close() { mask.remove(); document.removeEventListener('keydown', onKey, true); }
        function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); } else if (/^Arrow|^Page| /.test(e.key)) e.stopPropagation(); }
        document.addEventListener('keydown', onKey, true);
        x.addEventListener('click', function (e) { e.stopPropagation(); close(); });
        mask.addEventListener('click', function (e) { if (e.target === mask) close(); });
        ['touchstart', 'touchend', 'touchmove', 'wheel'].forEach(function (ev) { mask.addEventListener(ev, function (e) { e.stopPropagation(); }, { passive: true }); });
        // ★ (2026-11-02 14:30) 초점을 창으로 — EPUB 본문(iframe)에 초점이 남아 ←→ 가 뒤의 쪽을 넘기고 Esc 도 안 먹던 것(note() 와 같은 방식)
        setTimeout(function () { try { x.focus(); } catch (e) {} }, 30);
        return close;
    }
    // ★ (2026-11-07 fix40) 옆 창(검색·목차) 모두 닫기 — 뷰어가 다른 창(설정·노트)을 열 때 씀
    function closePanels() { panels.forEach(function (x) { if (x.isOpen()) x.show(false); }); }
    window.MCTools = { search: search, list: list, tts: tts, ttsSupported: ttsSupported, wake: wake, wakeSupported: wakeSupported, note: note, sentences: sentences, say: say,
        dim: dim, readTime: readTime, ping: ping, fmtDur: fmtDur, image: image, closePanels: closePanels };
})();
