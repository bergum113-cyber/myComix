/*
 * ★ (2026-11-12 21:19, 펜닐 요청 — fix131) 목록 보기 정보 줄 — 즐겨찾기·북마크·파일 목록의 '목록 보기'에서 제목 아래에
 *   1줄: 작가·그룹(서클)  2줄: 태그 · 쪽수   (서재 작품 정보 — library_info.php 가 읽기만 해서 돌려줌)
 *   - 대상: data-mcli="폴더번호|/경로" 가 붙은 항목(즐겨찾기·북마크는 PHP 가, 파일 목록 카드는 library_index_inject.php 가 붙임)
 *   - 목록 보기일 때만 받아 옴(그리드 보기는 받지도 보이지도 않음 — 그리드 모양 그대로). 보기를 바꾸면 그때 받아 옴
 *   - 서재에 정보가 없는 파일은 줄이 생기지 않음(예전 모양). 글자는 textContent 로만 넣음
 * ★ (2026-11-12 23:14, 펜닐 요청 — fix132) 파일 목록(목록 보기)은 배지 줄 아래에(제목 칸 안이라 배지 위에 끼고 2줄 자르기에 걸렸음), 글자를 조금 크게(제목보다 작게),
 *   회색으로(파일 목록 링크 글자색 규칙이 더 강해 검정·방문색으로 보였음). 쪽수는 배지(12p)에 이미 있어 파일 목록에서는 뺌. 즐겨찾기·북마크는 그대로
 * ★ (2026-11-13 00:15, 펜닐 요청 — fix133) 모양을 '작가 [칩] [칩]'처럼 종류별 줄로(작품 상세 정보 표와 같은 말·같은 칩 색 — 작가 파랑·그룹 초록·장르 빨강·태그 회색).
 *   글자는 제목보다 작게(12px). 파일 목록은 배지 줄과 같은 곳에서 시작(제목 앞 아이콘 폭만큼 들여씀 — index.php 배지 줄과 같은 --mc-ci-w).
 *   칩은 누를 수 없는 표시(카드 전체가 링크라 그 안에 링크를 둘 수 없음). 즐겨찾기·북마크는 쪽수를 마지막 줄 끝에 흐리게
 */
(function () {
    'use strict';
    if (window.mcListInfo) return;
    var T = window.mcLiT || { pages: '%d' };
    try {
        var st = document.createElement('style');
        st.textContent =
            '.mc-li{display:none}' +
            '.fav-list .mc-li,.bm-list .mc-li,html.mc-list-view .grid .mc-li{display:flex;flex-wrap:wrap;align-items:center;gap:3px 4px;margin-top:3px;text-align:left;font-weight:400;line-height:1.3;white-space:normal;min-width:0}' +
            // 글자색은 파일 목록 링크 규칙(index.php a[href*=viewer] … :not(.badge) — !important)보다 강하게(클래스를 겹쳐 씀)
            '.mc-li.mc-li.mc-li .mc-li-l.mc-li-l.mc-li-l{font-size:12px;color:#6c757d !important;margin-right:2px;white-space:nowrap;text-shadow:none}' +
            '.mc-li.mc-li.mc-li .mc-li-c.mc-li-c.mc-li-c{display:inline-block;font-size:12px;line-height:1.4;padding:0 6px;border-radius:4px;border:1px solid #d3d6db;background:#f1f2f4;color:#444 !important;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-shadow:none}' +
            '.mc-li.mc-li.mc-li .mc-li-a.mc-li-a.mc-li-a{background:#eef4ff;border-color:#bcd0f5;color:#1a56c4 !important}' +
            '.mc-li.mc-li.mc-li .mc-li-g.mc-li-g.mc-li-g{background:#eefaf1;border-color:#b5e0c2;color:#1e7a3c !important}' +
            '.mc-li.mc-li.mc-li .mc-li-n.mc-li-n.mc-li-n{background:#fdf0ee;border-color:#efc3bc;color:#b23121 !important}' +
            'html.dark-mode .mc-li.mc-li.mc-li .mc-li-l.mc-li-l.mc-li-l,[data-theme="dark"] .mc-li.mc-li.mc-li .mc-li-l.mc-li-l.mc-li-l{color:#adb5bd !important}' +
            'html.dark-mode .mc-li.mc-li.mc-li .mc-li-c.mc-li-c.mc-li-c,[data-theme="dark"] .mc-li.mc-li.mc-li .mc-li-c.mc-li-c.mc-li-c{background:#26292d;border-color:#454a50;color:#ddd !important}' +
            'html.dark-mode .mc-li.mc-li.mc-li .mc-li-a.mc-li-a.mc-li-a,[data-theme="dark"] .mc-li.mc-li.mc-li .mc-li-a.mc-li-a.mc-li-a{background:#1a2a45;border-color:#34507f;color:#9ec1ff !important}' +
            'html.dark-mode .mc-li.mc-li.mc-li .mc-li-g.mc-li-g.mc-li-g,[data-theme="dark"] .mc-li.mc-li.mc-li .mc-li-g.mc-li-g.mc-li-g{background:#17331f;border-color:#2f6b40;color:#9be3b0 !important}' +
            'html.dark-mode .mc-li.mc-li.mc-li .mc-li-n.mc-li-n.mc-li-n,[data-theme="dark"] .mc-li.mc-li.mc-li .mc-li-n.mc-li-n.mc-li-n{background:#3c1f1c;border-color:#6e3a33;color:#ffb4a8 !important}' +
            '.fav-list .mc-li.mc-li.mc-li .mc-li-l.mc-li-l.mc-li-l{color:#d9c2a3 !important;text-shadow:0 1px 2px rgba(0,0,0,.8)}' + // 즐겨찾기 책장(어두운 나무) 위
            '.mc-li-p{margin-left:2px}' +
            // 파일 목록: 카드 격자의 셋째 줄(제목·배지 아래), 시작은 배지 줄과 같게
            '.mc-li-box{display:none}html.mc-list-view .grid .card>.mc-li-box{display:block;grid-column:2;grid-row:3;min-width:0;margin-top:1px}' +
            'html.mc-list-view .grid .card:has(> .card-body > .mc-ci:first-child)>.mc-li-box{padding-left:var(--mc-ci-w,0px)}';
        (document.head || document.documentElement).appendChild(st);
    } catch (e) {}

    function listMode(el) {
        if (el.closest('.fav-list, .bm-list')) return true;
        return document.documentElement.classList.contains('mc-list-view') && !!el.closest('.grid');
    }
    function span(cls, text) { var s = document.createElement('span'); s.className = cls; s.textContent = text; return s; }
    // 한 줄 = 종류 이름 + 칩들
    function row(to, label, kind, list) {
        var d = document.createElement('div');
        d.className = 'mc-li';
        d.appendChild(span('mc-li-l', label));
        for (var i = 0; i < list.length; i++) { var c = span('mc-li-c mc-li-' + kind, String(list[i])); c.title = String(list[i]); d.appendChild(c); }
        to.appendChild(d);
        return d;
    }
    function apply(el, inf) {
        var card = !el.closest('.fav-list, .bm-list') && el.classList.contains('card'); // ★ fix132 파일 목록 카드 → 카드 바로 아래 상자(배지 줄 아래)
        var to = card ? null : (el.querySelector('.fav-text, .bm-text, .card-body') || el);
        if (card) {
            if (el.querySelector(':scope > .mc-li-box')) return;
            to = document.createElement('div'); to.className = 'mc-li-box';
        } else if (to.querySelector('.mc-li')) return;
        var last = null;
        [['a', T.a], ['g', T.g], ['n', T.n], ['t', T.t]].forEach(function (k) {
            var l = Array.isArray(inf[k[0]]) ? inf[k[0]] : [];
            if (l.length) last = row(to, String(k[1] || ''), k[0], l);
        });
        if (+inf.pages > 0 && !card) { // 즐겨찾기·북마크: 쪽수는 마지막 줄 끝에 흐리게(파일 목록은 배지에 있음)
            var p = span('mc-li-l mc-li-p', String(T.pages).replace('%d', String(+inf.pages)));
            if (last) last.appendChild(p); else { var d = document.createElement('div'); d.className = 'mc-li'; d.appendChild(p); to.appendChild(d); }
        }
        if (card && to.firstChild) el.appendChild(to);
    }
    var busy = false, again = false;
    function scan() {
        if (busy) { again = true; return; }
        var els = document.querySelectorAll('[data-mcli]:not([data-mcli-done])');
        var map = {}, want = [];
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (!listMode(el)) continue;
            var v = el.getAttribute('data-mcli') || '', k = v.indexOf('|');
            if (k < 1) continue;
            el.setAttribute('data-mcli-done', '1');
            if (!map[v]) { map[v] = []; want.push([parseInt(v.slice(0, k), 10) || 0, v.slice(k + 1)]); }
            map[v].push(el);
        }
        if (!want.length) return;
        busy = true;
        fetch('library_info.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ items: want.slice(0, 300) }) })
            .then(function (r) { return r.ok ? r.json() : {}; })
            .then(function (j) {
                for (var key in map) {
                    if (!Object.prototype.hasOwnProperty.call(map, key)) continue;
                    if (j && j[key]) map[key].forEach(function (el) { apply(el, j[key]); });
                }
            })
            .catch(function () { for (var key in map) map[key].forEach(function (el) { el.removeAttribute('data-mcli-done'); }); })
            .then(function () { busy = false; if (again || want.length > 300) { again = false; setTimeout(scan, 50); } });
    }
    var t = 0;
    function later() { clearTimeout(t); t = setTimeout(scan, 150); }
    window.mcListInfo = { scan: later };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', later); else later();
    try {
        // 보기 바꾸기(목록 ↔ 그리드: html·목록 상자의 class)와 나중에 붙는 항목에만 반응
        new MutationObserver(function (ms) {
            for (var i = 0; i < ms.length; i++) {
                var m = ms[i];
                if (m.type === 'childList' || m.target === document.documentElement || (m.target.classList && (m.target.classList.contains('fav-list') || m.target.classList.contains('bm-list') || m.target.classList.contains('fav-grid') || m.target.classList.contains('bm-grid') || m.target.classList.contains('bm-items')))) { later(); return; }
            }
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'], subtree: true, childList: true });
    } catch (e) {}
})();
