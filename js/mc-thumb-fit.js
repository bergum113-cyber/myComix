/*
 * ★ (2026-11-12 11:15, 펜닐 요청) 즐겨찾기·북마크·서재(작품 탐색·작품 상세) 표지 — 목록 화면(index.php mcThumbFit)과 같은 모양
 *   표지 칸 안 그림을 잘리지 않게(contain) 보이고, 남는 칸에 같은 그림을 흐리게 깔아 채움(맨 위 배너·목록 카드와 같은 방식)
 *   - 표지 칸(.fav-cover·.bm-cover·.sc-cover·.sr-cover·.vol-cover)의 바로 아래 그림만. 칸 크기·모양(3:4·목록 보기 작은 칸)은 그대로
 *   - 그림이 다 받아진 뒤에만 붙임(load 를 문서 단계에서 잡음 — 늦게 받아지는 그림·나중에 생긴 카드도 같이). 아주 작은 그림(1×1 빈 그림 등)은 건드리지 않음
 *   - 흐린 그림은 칸 맨 앞에 넣고(위치 지정), 원래 그림은 position:relative 로 그 위에 — 칸의 다른 표시(종류·쪽수·완결 등)는 문서 순서대로 그대로 위에 보임
 *   - 스크립트가 없거나 실패하면 예전 모양(cover) 그대로
 * ★ (2026-11-13 15:01, 펜닐 제보 — fix144) 서재(작품 탐색 .sc-cover·작품 상세 .sr-cover·.vol-cover)는 흐린 그림 대신 옅은 회색 바탕(다크 모드는 어두운 회색)
 *   서재에서 스크롤하면 카드의 흐린 배경이 움직여 보였음(아이폰 — 파일 목록·즐겨찾기 방식은 안 움직임). 시험 브라우저로는 재현이 안 돼 원인을 고칠 수 없어
 *   펜닐이 정한 대로 무색 바탕으로. 그림은 그대로 잘리지 않게(contain) — 남는 칸만 회색. 즐겨찾기·북마크는 예전 그대로(흐린 배경)
 */
(function () {
    'use strict';
    var SEL = '.fav-cover > img, .bm-cover > img, .sc-cover > img, .sr-cover > img, .vol-cover > img';
    var PLAIN = '.sc-cover > img, .sr-cover > img, .vol-cover > img';   // ★ fix144 서재 — 흐린 그림 없이 회색 바탕
    try {
        var st = document.createElement('style');
        st.textContent =
            'img.mc-tf{position:relative;object-fit:contain !important;object-position:center !important;background:transparent !important}' +
            '.mc-tf-bg{position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;border-radius:inherit}' +
            '.mc-tf-bg>img{position:absolute;left:-15%;top:-15%;width:130% !important;height:130% !important;max-width:none !important;object-fit:cover !important;filter:blur(14px) brightness(.85);visibility:visible !important}' +
            '.mc-tf-plain.mc-tf-plain{background:#eceef1 !important}' +
            'html.dark-mode .mc-tf-plain.mc-tf-plain,[data-theme="dark"] .mc-tf-plain.mc-tf-plain{background:#2b2e33 !important}';
        (document.head || document.documentElement).appendChild(st);
    } catch (e) {}

    function fit(im) {
        if (!im || im.tagName !== 'IMG' || im.classList.contains('mc-tf-b')) return;
        try { if (!im.matches(SEL)) return; } catch (e) { return; }
        var src = im.currentSrc || im.src || '';
        if (!src || !(im.naturalWidth >= 64 && im.naturalHeight >= 64)) return;
        var box = im.parentElement; if (!box) return;
        var plain = false; try { plain = im.matches(PLAIN); } catch (e) {}
        if (plain) { box.classList.add('mc-tf-plain'); im.classList.add('mc-tf'); return; }   // ★ fix144 서재 — 회색 바탕만
        var bg = box.querySelector(':scope > .mc-tf-bg');
        if (!bg) {
            if (getComputedStyle(box).position === 'static') box.style.position = 'relative';
            bg = document.createElement('div'); bg.className = 'mc-tf-bg'; bg.setAttribute('aria-hidden', 'true');
            var b = document.createElement('img'); b.className = 'mc-tf-b'; b.alt = ''; b.decoding = 'async';
            bg.appendChild(b);
            box.insertBefore(bg, box.firstChild);
            im.classList.add('mc-tf');
        }
        var bi = bg.firstChild;
        if (bi.getAttribute('src') !== src) bi.setAttribute('src', src);
    }
    document.addEventListener('load', function (e) { fit(e.target); }, true);
    function scan() {
        var l = document.querySelectorAll(SEL);
        for (var i = 0; i < l.length; i++) if (l[i].complete) fit(l[i]);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
    window.addEventListener('load', scan);
})();
