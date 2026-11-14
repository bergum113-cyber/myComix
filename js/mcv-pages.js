/**
 * ★ (2026-11-13 00:07, 펜닐 요청 — fix133) 뷰어 쪽 목록 · 진행 줄 끌기 미리보기 — viewer_bar.php 가 viewer-bar.js 다음에 부름
 *  · 쪽 목록: 모든 쪽을 작은 그림(viewer.php …&img=N&tw=1 — 폭 240px)으로 격자에 보여 주고, 누르면 그 쪽부터 봄
 *      툰모드 = 위 막대 쪽 번호(3 / 33 · 9%)를 누름 / 북모드 = 쪽 번호 창의 [쪽 목록]
 *      보이는 칸 근처만 그림을 받음(IntersectionObserver). 지금 보던 쪽은 테두리 + 그 칸으로 스크롤
 *  · 툰모드 진행 줄(위 막대 아래 가는 줄): 마우스를 올리거나(PC) 누른 채 끌면(PC·휴대폰) 그 위치의 쪽 번호와 작은 그림을 보여 주고,
 *      놓으면 그 쪽으로. 그냥 누르기는 예전처럼(viewer-bar.js) — 위치 계산은 같은 식(누른 곳 비율 × 전체 쪽)
 *  · 쪽 이동은 기존 함수 그대로: 툰 = window.mcvToonJump, 북 = lightGallery slide(쪽 → 슬라이드 window._mcBook.pageSlide)
 *  ★ (2026-11-13 00:42 점검) 쪽 목록 z-index 9000 — 북모드 그림 화면(lightGallery 1050~1082)보다 위, 자동 로그아웃 경고(99999)·가림막보다 아래.
 *    처음엔 가장 위(2147483000)로 둬서 쪽 목록이 열린 채 자동 로그아웃 경고가 뜨면 그 뒤에 가려졌음(점검 중 재현)
 */
(function () {
  'use strict';
  var C = window.MCV_BAR;
  if (!C) return;
  var T = C.t || {};
  var box = document.getElementById('lightgallery');
  if (!box) return;
  var total = parseInt(box.getAttribute('data-total'), 10) || 0;
  if (total < 1) return;
  var isBook = C.mode === 'book';

  function thumb(i) { return 'viewer.php?file=' + C.file + '&bidx=' + (parseInt(C.bidx, 10) || 0) + '&img=' + i + '&tw=1'; }
  function barH() { var b = document.getElementById('mcvBar'); return b ? b.getBoundingClientRect().bottom : 0; }
  function lg() { try { return window.jQuery ? window.jQuery('#lightgallery').data('lightGallery') : null; } catch (e) { return null; } }
  function curPage() {
    try {
      if (isBook) { var inst = lg(), m = window._mcBook; return (inst && m && m.slidePage) ? (m.slidePage[inst.index || 0] || 0) : 0; }
      return typeof window.mcVisibleIdx === 'function' ? (window.mcVisibleIdx() || 0) : 0;
    } catch (e) { return 0; }
  }
  function go(i) {
    i = Math.max(0, Math.min(total - 1, parseInt(i, 10) || 0));
    if (isBook) {
      var inst = lg(), m = window._mcBook;
      var s = (m && m.pageSlide && m.pageSlide[i] !== undefined) ? m.pageSlide[i] : i;
      try { if (inst) inst.slide(s); } catch (e) {}
      return;
    }
    if (typeof window.mcvToonJump === 'function') window.mcvToonJump(i);
  }

  try {
    // fix164 쪽 번호 칸 ▦·▾ 그림(글자색 따라감 — CSS mask). 쪽 번호 글자는 viewer-bar.js 가 textContent 로 바꾸므로 그림은 칸 앞뒤 가짜 요소로
    var ICO_GRID = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect width='7' height='7' x='3' y='3' rx='1'/%3E%3Crect width='7' height='7' x='14' y='3' rx='1'/%3E%3Crect width='7' height='7' x='14' y='14' rx='1'/%3E%3Crect width='7' height='7' x='3' y='14' rx='1'/%3E%3C/svg%3E";
    var ICO_DOWN = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E";
    var st = document.createElement('style');
    st.textContent =
      '.mcv-pg-ov{position:fixed;top:0;left:0;right:0;bottom:0;z-index:9000;background:#121214;color:#eee;display:flex;flex-direction:column}' +
      '.mcv-pg-ov[hidden]{display:none}' +
      '.mcv-pg-hd{display:flex;align-items:center;gap:8px;padding:8px 10px 8px 14px;border-bottom:1px solid rgba(255,255,255,.12);font-size:15px;font-weight:600;flex:0 0 auto}' +
      '.mcv-pg-hd span{flex:1 1 auto;min-width:0}' +
      '.mcv-pg-x{width:44px;height:40px;border:0;border-radius:8px;background:transparent;color:inherit;font-size:22px;line-height:1;cursor:pointer}' +
      '.mcv-pg-x:hover{background:rgba(255,255,255,.12)}' +
      '.mcv-pg-body{flex:1 1 auto;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:10px;overscroll-behavior:contain}' +
      '.mcv-pg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:12px 10px}' +
      '@media (max-width:576px){.mcv-pg-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:10px 8px}}' +
      '.mcv-pg-it{display:block;border:0;padding:0;background:transparent;color:inherit;cursor:pointer;text-align:center;min-width:0;outline:none !important;box-shadow:none !important}' +
      '.mcv-pg-im{display:block;width:100%;aspect-ratio:3/4;border-radius:4px;overflow:hidden;background:#2a2a2e;outline:2px solid transparent;outline-offset:2px}' +
      '.mcv-pg-im img{width:100%;height:100%;object-fit:contain;display:block}' +
      '.mcv-pg-it.on .mcv-pg-im{outline-color:#4dabf7}' +
      '.mcv-pg-it:hover .mcv-pg-im,.mcv-pg-it:focus-visible .mcv-pg-im{outline-color:rgba(255,255,255,.5)}' +
      '.mcv-pg-n{display:block;font-size:12.5px;margin-top:4px;opacity:.8;font-variant-numeric:tabular-nums}' +
      '.mcv-pg-it.on .mcv-pg-n{color:#74c0fc;opacity:1;font-weight:700}' +
      // ★ (2026-11-14 02:25, 펜닐 요청 — fix154) 쪽 고르기 판(최종안 — 가운데 한 장 크게) · fix153 E 판 CSS 는 판을 바꾸며 지움
      '.mcv-rp{position:fixed;left:0;right:0;bottom:0;z-index:1070;display:flex;flex-direction:column;background:rgba(8,8,10,.92);color:#fff;user-select:none;-webkit-user-select:none;touch-action:none;font-variant-numeric:tabular-nums}' +
      '.mcv-rp[hidden]{display:none}' +
      'body.mcv-rp-on #scrollTopBtn,body.mcv-rp-on #darkmode-toggle{visibility:hidden!important}' + // 판이 열린 동안 툰모드의 ▲·밤낮 단추(z 9999)가 판 위를 덮어 옆 쪽·이동 줄 누르기를 가로챘음(시험 재현)
      '.mcv-rp-hd{position:relative;flex:0 0 auto;height:52px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700}' +
      '.mcv-rp-back{position:absolute;left:12px;top:10px;height:32px;padding:0 12px;border:1px solid #3a3b42;border-radius:16px;background:#1b1c1f;color:#e6e8eb;font-size:13px;font-weight:400;cursor:pointer}' +
      '.mcv-rp-back[hidden]{display:none}' +
      '.mcv-rp-x{position:absolute;right:6px;top:4px;width:44px;height:44px;border:0;border-radius:8px;background:transparent;color:#c9cdd4;font-size:28px;line-height:1;cursor:pointer}' +
      '.mcv-rp-x:hover{background:rgba(255,255,255,.12)}' +
      '.mcv-rp-st{position:relative;flex:1 1 auto;min-height:0;overflow:hidden;cursor:grab}' +
      '.mcv-rp-it{position:absolute;left:0;top:0;border-radius:5px;overflow:hidden;background:#2a2a2e;box-shadow:0 10px 30px rgba(0,0,0,.6);will-change:transform}' +
      '.mcv-rp-it[hidden]{display:none}' +
      '.mcv-rp-it.c{outline:3px solid #4dabf7;outline-offset:3px}' +
      '.mcv-rp-it img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;pointer-events:none}' +
      '.mcv-rp-it b{position:absolute;left:0;right:0;top:50%;transform:translateY(-50%);text-align:center;font-weight:700;opacity:.45}' +
      // ★ (2026-11-14 21:21, 펜닐 요청 — fix164) 처음엔 안내(A-3 손가락·B-2·C-2 말풍선), 확인한 뒤엔 모양 표시(A-1 'N쪽 열기' 단추·B-1 진행 줄 손잡이·C-1 쪽 번호 ▦ ▾) — fix163 의 ▶ 는 뺌
      '.mcv-rp-ob{position:absolute;left:50%;transform:translateX(-50%);height:44px;padding:0 22px;border:0;border-radius:22px;background:#4dabf7;color:#0b1a26;font-size:15px;font-weight:700;font-family:inherit;white-space:nowrap;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.45);z-index:200}' + // 그림(z 최대 100)보다 위 — 폰 세로 툰처럼 그림이 판을 거의 채우면 그림 아래쪽 위에 얹힘(시험 재현: 그림이 단추를 덮어 안 눌렸음)
      '.mcv-rp-ob[hidden],.mcv-rp-hint[hidden]{display:none}' +
      '.mcv-rp-ob:focus-visible{outline:2px solid #fff;outline-offset:2px}' +
      '.mcv-rp-hint{position:absolute;left:0;right:0;top:0;height:0;pointer-events:none;z-index:200}' +
      '.mcv-rp-hand{position:absolute;left:50%;width:84px;height:84px;margin-left:-42px;border-radius:50%;border:3px solid rgba(255,255,255,.75);box-sizing:border-box;display:flex;align-items:center;justify-content:center;animation:mcvTipPulse 1.6s ease-in-out infinite}' +
      '.mcv-rp-hand svg{width:46px;height:46px;margin:22px 0 0 18px}' +
      '.mcv-rp-ht{position:absolute;left:50%;transform:translateX(-50%);padding:9px 16px;border-radius:14px;background:#fff3bf;color:#3b2f00;font-size:14px;font-weight:500;white-space:nowrap;box-shadow:0 4px 14px rgba(0,0,0,.45)}' +
      '@keyframes mcvTipPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(.88);opacity:.75}}' +
      '@media (prefers-reduced-motion:reduce){.mcv-rp-hand{animation:none}}' +
      '.mcv-prog.mcv-kn{z-index:1061}.mcv-prog.mcv-kn::before{height:4px}.mcv-prog.mcv-kn i{height:4px}' +
      '.mcv-prog.mcv-kn i::after{content:"";position:absolute;right:-7px;top:-5px;width:14px;height:14px;border-radius:50%;background:#fff;border:3px solid #1a73e8;box-sizing:border-box;box-shadow:0 1px 3px rgba(0,0,0,.35)}' +
      '#mcvPos.mcv-ic::before,#mcvPos.mcv-ic::after{content:"";display:inline-block;width:.95em;height:.95em;vertical-align:-.12em;background:currentColor}' +
      '#mcvPos.mcv-ic::before{margin-right:4px;-webkit-mask:url("' + ICO_GRID + '") center/contain no-repeat;mask:url("' + ICO_GRID + '") center/contain no-repeat}' +
      '#mcvPos.mcv-ic::after{margin-left:3px;-webkit-mask:url("' + ICO_DOWN + '") center/contain no-repeat;mask:url("' + ICO_DOWN + '") center/contain no-repeat}' +
      '.mcv-tip{position:fixed;z-index:1066;width:max-content;max-width:min(300px,calc(100vw - 24px));padding:12px 14px 10px;border-radius:12px;background:#1f2937;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.35);font-family:system-ui,-apple-system,"Segoe UI","Malgun Gothic",sans-serif;font-size:14px;line-height:1.45;word-break:keep-all;overflow-wrap:anywhere;box-sizing:border-box}' +
      '.mcv-tip::before{content:"";position:absolute;top:-6px;left:var(--ax,20px);width:12px;height:12px;background:#1f2937;transform:rotate(45deg)}' +
      '.mcv-tip-b{display:block;margin:8px 0 0 auto;height:34px;padding:0 14px;border:0;border-radius:8px;background:#ffd43b;color:#2b2100;font-size:14px;font-weight:700;font-family:inherit;cursor:pointer}' +
      'body.mcv-rp-on .mcv-tip,html.mcv-popopen .mcv-tip{display:none}' +
      '.mcv-tip-on{outline:2px solid #ffd43b !important;outline-offset:2px}' +
      '.mcv-rp-ft{flex:0 0 auto;height:60px;display:flex;align-items:center;gap:12px;padding:0 max(16px,4vw)}' +
      '.mcv-rp-ft span{font-size:12px;color:#8d939c}' +
      '.mcv-rp-bar{position:relative;flex:1 1 auto;height:44px;cursor:pointer;touch-action:none}' +
      '.mcv-rp-tr{position:absolute;left:0;right:0;top:19px;height:6px;border-radius:3px;background:#2a2b30}' +
      '.mcv-rp-fl{position:absolute;left:0;top:19px;height:6px;border-radius:3px;background:#4dabf7}' +
      '.mcv-rp-og{position:absolute;top:15px;width:2px;height:14px;margin-left:-1px;background:#8d939c}' +
      '.mcv-rp-kn{position:absolute;top:10px;width:24px;height:24px;margin-left:-12px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.5)}' +
      '@media (max-height:480px){.mcv-rp-hd{height:42px;font-size:18px}.mcv-rp-back{top:6px;height:30px}.mcv-rp-x{top:0;height:42px}.mcv-rp-ft{height:48px}}' +
      '.mcv-prog{touch-action:none}' +
      '@media (pointer:coarse){html .mcv-prog{height:24px}}'; // 손가락으로 잡기 쉽게(위 막대 CSS 12px 보다 강하게)
    (document.head || document.documentElement).appendChild(st);
  } catch (e) {}

  // ============================== 쪽 목록 ==============================
  var ov = null, body = null, items = [], io = null;
  function load(it) { var im = it.querySelector('img'); if (im && !im.getAttribute('src')) im.setAttribute('src', im.getAttribute('data-src')); }
  function build() {
    if (ov) return;
    ov = document.createElement('div');
    ov.className = 'mcv-pg-ov'; ov.hidden = true;
    ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-label', String(T.pages || ''));
    var hd = document.createElement('div'); hd.className = 'mcv-pg-hd';
    var ttl = document.createElement('span'); ttl.textContent = String(T.pages || '') + ' · ' + total;
    var x = document.createElement('button'); x.type = 'button'; x.className = 'mcv-pg-x'; x.textContent = '×';
    x.title = String(T.pages_close || ''); x.setAttribute('aria-label', String(T.pages_close || ''));
    x.addEventListener('click', close);
    hd.appendChild(ttl); hd.appendChild(x);
    body = document.createElement('div'); body.className = 'mcv-pg-body';
    var grid = document.createElement('div'); grid.className = 'mcv-pg-grid';
    for (var i = 0; i < total; i++) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'mcv-pg-it'; b.setAttribute('data-i', String(i));
      var w = document.createElement('span'); w.className = 'mcv-pg-im';
      var im = document.createElement('img'); im.alt = ''; im.decoding = 'async'; im.setAttribute('data-src', thumb(i));
      w.appendChild(im);
      var n = document.createElement('span'); n.className = 'mcv-pg-n'; n.textContent = String(i + 1);
      b.appendChild(w); b.appendChild(n);
      grid.appendChild(b); items.push(b);
    }
    grid.addEventListener('click', function (e) {
      var b2 = e.target && e.target.closest ? e.target.closest('.mcv-pg-it') : null;
      if (!b2) return;
      var k = parseInt(b2.getAttribute('data-i'), 10) || 0;
      close(); go(k);
    });
    body.appendChild(grid);
    ov.appendChild(hd); ov.appendChild(body);
    document.body.appendChild(ov);
    if ('IntersectionObserver' in window) {
      io = new IntersectionObserver(function (es) {
        es.forEach(function (en) { if (en.isIntersecting) { load(en.target); io.unobserve(en.target); } });
      }, { root: body, rootMargin: '400px 0px' });
      items.forEach(function (it) { io.observe(it); });
    } else items.forEach(load);
  }
  function onKey(e) {
    if (!ov || ov.hidden) return;
    if (e.key === 'Escape') { e.preventDefault(); close(); }
    e.stopPropagation(); // 열려 있는 동안 뒤쪽 뷰어(쪽 넘김 키·북모드 Esc 닫기)가 받지 않게
  }
  function open() {
    build();
    try { if (typeof window.mcvClosePop === 'function') window.mcvClosePop(); } catch (e) {}
    var c = curPage();
    items.forEach(function (it, k) { it.classList.toggle('on', k === c); });
    ov.hidden = false;
    document.documentElement.classList.add('mcv-popopen');
    var t = items[c];
    if (t) { body.scrollTop = Math.max(0, t.offsetTop - body.clientHeight / 2 + t.offsetHeight / 2); try { t.focus({ preventScroll: true }); } catch (e) { try { t.focus(); } catch (x) {} } }
    window.addEventListener('keydown', onKey, true);
  }
  function close() {
    if (!ov || ov.hidden) return;
    ov.hidden = true;
    document.documentElement.classList.remove('mcv-popopen');
    window.removeEventListener('keydown', onKey, true);
    // 단추에 포커스를 남기지 않음 — 툰모드 키(↑↓·PageUp/Down)는 포커스가 단추에 있으면 무시돼(viewer.php) 닫은 뒤 키로 안 넘어갔음
    try { var a = document.activeElement; if (a && a !== document.body && a.blur) a.blur(); } catch (e) {}
  }
  window.mcvPagesOpen = open;
  var pos = document.getElementById('mcvPos');
  if (!isBook && pos) pos.addEventListener('click', open);
  var pb = document.getElementById('mcvPagesBtn');
  if (pb) pb.addEventListener('click', function (e) { e.preventDefault(); open(); });

  // ============================== 진행 줄 → 쪽 고르기 판(툰모드·북모드) ==============================
  // ★ (2026-11-14 02:25, 펜닐 요청 — fix154) 최종안(디자인 캔버스 '최종 후보' — 시안 E6·E9 다듬음): 가운데 한 장 크게 + 양옆 쪽 작게, 한 번 밀면 한 쪽
  //   여는 법: 위 진행 줄을 누르거나 끌면(북모드에도 진행 줄을 새로 둠 — viewer_bar.php) 화면을 어둡게 덮는 판이 열림. 끄는 동안 판이 따라가고 놓으면 그 쪽으로 이동
  //   판: 위 쪽 번호 · '↩ 원래 쪽 N'(판을 열기 전 보던 쪽 — 그 쪽이 아닐 때만) · × / 가운데 큰 그림(그림 비율대로 — 펼침 스캔은 넓게) · 양옆 쪽 / 아래 이동 줄(1·끝 쪽, 원래 쪽 표시선)
  //   가운데 그림 = 큰 미리보기(viewer.php &tw=2 — 폭 720px), 받기 전엔 작은 그림(&tw=1)을 먼저 보여 줌 · 양옆 = 작은 그림(종전 미리 받기: 동시 4개, 못 받은 쪽은 번호 칸)
  //   그림 줄: 좌우로 끌기(한 번 밀면 한 쪽, 길게 끌면 여러 쪽)·휠·옆 쪽 누르기·←→ → 가운데에 맞추고 그 쪽으로 이동 / 이동 줄: 끌거나 눌러 책 전체
  //   닫힘: × · Esc · 화면 폭이 바뀜(fix157: 판 바깥 누르기 닫힘은 뺌). 종전(fix137~153)의 세 장 미리보기·그림 줄·E 판은 이것으로 바꿈
  // ★ (2026-11-14 08:44, 펜닐 요청 — fix156) ComicGlass 방식으로 바꿈: 진행 줄·밀기·옆 그림·이동 줄·←→·휠·'원래 쪽'은 판 안에서 고르기만(뒤 화면은 그대로), 가운데 그림을 누르거나 Enter 일 때만 그 쪽을 열고 판을 닫음(fix155 는 멈출 때마다 열고 닫았음)
  var prog = document.getElementById('mcvProg');
  if (!prog) return;
  prog.removeAttribute('title');
  // ---- 미리 받기(fix137 그대로 — 그림 비율을 함께 기억) ----
  var st = {}, pre = {}, act = 0, MAXA = 4, focus = 0, reach = 0, asp = {}, big = {};
  function pick() {
    for (var d = 0; d <= reach; d++) {
      var a = focus + d, b = focus - d;
      if (a < total && !st[a]) return a;
      if (d && b >= 0 && !st[b]) return b;
      if (a >= total && b < 0) break;
    }
    return -1;
  }
  function pump() {
    while (act < MAXA) {
      var j = pick();
      if (j < 0) return;
      st[j] = 1; act++;
      (function (j) {
        var im = new Image();
        pre[j] = im;
        im.onload = function () { st[j] = 2; act--; delete pre[j]; if (im.naturalWidth && im.naturalHeight && !asp[j]) asp[j] = im.naturalWidth / im.naturalHeight; fill(j); pump(); };
        im.onerror = function () { st[j] = 3; act--; delete pre[j]; pump(); };
        im.src = thumb(j);
      })(j);
    }
  }
  function want(i, wide) {
    focus = i;
    reach = Math.max(reach, wide ? (total <= 600 ? total : 40) : 5);
    pump();
  }
  function thumbBig(i) { return thumb(i).replace(/&tw=1$/, '&tw=2'); }
  function wantBig(i) {
    if (i < 0 || i >= total || big[i]) return;
    big[i] = 1;
    var im = new Image();
    im.onload = function () { big[i] = 2; if (im.naturalWidth && im.naturalHeight) asp[i] = im.naturalWidth / im.naturalHeight; fill(i); };
    im.onerror = function () { big[i] = 3; };
    im.src = thumbBig(i);
  }
  function idxAt(x) {
    var r = prog.getBoundingClientRect();
    var f = Math.max(0, Math.min(0.9999, (x - r.left) / Math.max(1, r.width)));
    return Math.floor(f * total);
  }
  // ---- 판 ----
  function mk(tag, cls, par) { var e = document.createElement(tag); if (cls) e.className = cls; if (par) par.appendChild(e); return e; }
  var rp = mk('div', 'mcv-rp'); rp.hidden = true; rp.setAttribute('role', 'dialog'); rp.setAttribute('aria-label', String(T.pages || ''));
  var rHd = mk('div', 'mcv-rp-hd', rp);
  var rBack = mk('button', 'mcv-rp-back', rHd); rBack.type = 'button'; rBack.hidden = true;
  var rLab = mk('span', '', rHd);
  var rX = mk('button', 'mcv-rp-x', rHd); rX.type = 'button'; rX.textContent = '×'; rX.setAttribute('aria-label', String(T.pages_close || ''));
  var rSt = mk('div', 'mcv-rp-st', rp);
  var rPool = [], RN = 7;
  for (var rk = 0; rk < RN; rk++) {
    var re = mk('div', 'mcv-rp-it', rSt); re.hidden = true;
    var rb = mk('b', '', re), rim = mk('img', '', re); rim.alt = ''; rim.hidden = true; rim.draggable = false;
    rPool.push({ el: re, num: rb, im: rim, i: -2, src: '' });
  }
  // ★ (2026-11-14 21:21 fix164) 가운데 그림 아래 — 안내 확인 전: 손가락 + '가운데 그림을 누르면 열려요'(A-3) / 확인 뒤: 'N쪽 열기' 단추(A-1). 그림 줄(끌기 받는 곳) 밖에 둬서 누르기가 끌기와 섞이지 않음
  var rOb = mk('button', 'mcv-rp-ob', rp); rOb.type = 'button'; rOb.hidden = true;
  var rHint = mk('div', 'mcv-rp-hint', rp); rHint.hidden = true; rHint.setAttribute('aria-hidden', 'true');
  var rHand = mk('div', 'mcv-rp-hand', rHint);
  rHand.innerHTML = '<svg viewBox="0 0 24 24" fill="rgba(0,0,0,.35)" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 14a8 8 0 0 1-8 8"/><path d="M18 11v-1a2 2 0 0 0-2-2a2 2 0 0 0-2 2"/><path d="M14 10V9a2 2 0 0 0-2-2a2 2 0 0 0-2 2v1"/><path d="M10 9.5V4a2 2 0 0 0-2-2a2 2 0 0 0-2 2v10"/><path d="M18 11a2 2 0 1 1 4 0v3a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg>';
  var rHt = mk('div', 'mcv-rp-ht', rHint); rHt.textContent = String(T.tip_rp || '');
  // 안내 확인 기록(이 브라우저에 — 저장이 안 되는 곳이면 매번 안내)
  function tipDone(k) { try { return localStorage.getItem('mcv_tip_' + k) === '1'; } catch (e) { return false; } }
  function tipSet(k) { try { localStorage.setItem('mcv_tip_' + k, '1'); } catch (e) {} if (window.mcvTipApply) window.mcvTipApply(); }
  var rFt = mk('div', 'mcv-rp-ft', rp);
  mk('span', '', rFt).textContent = '1';
  var rBar = mk('div', 'mcv-rp-bar', rFt);
  mk('div', 'mcv-rp-tr', rBar);
  var rFl = mk('div', 'mcv-rp-fl', rBar), rOg = mk('div', 'mcv-rp-og', rBar), rKn = mk('div', 'mcv-rp-kn', rBar);
  mk('span', '', rFt).textContent = String(total);
  document.body.appendChild(rp);
  var pos = 0, origin = 0, raf = 0, wheelT = 0, sd = null, bd = false, busy = false, drag = false, moved = false, sx = 0, eat = false;
  function clamp(v) { return Math.max(0, Math.min(total - 1, v)); }
  function ar(i) { return asp[i] || 0.7; }
  function geo() { // 가운데 그림 높이·한 칸 끌기 거리·사이
    var W = rSt.clientWidth || window.innerWidth, H = rSt.clientHeight || 400;
    return { W: W, H: H, HC: Math.max(80, Math.min(H - 24, 620)), gap: W < 600 ? 14 : 28 };
  }
  function size(i, k, g) { var h = g.HC * k, w = h * ar(i), mw = g.W * 0.86 * k; if (w > mw) { w = mw; h = w / ar(i); } return { w: w, h: h }; }
  function row(g) {
    var c = Math.floor(pos), f = pos - c, R = 3, xs = {}, sz = {};
    var S = function (i) { if (!sz[i]) sz[i] = size(i, 1 - 0.3 * Math.min(1, Math.abs(i - pos)), g); return sz[i]; };
    xs[c] = 0;
    for (var i = c + 1; i <= c + R + 1; i++) xs[i] = xs[i - 1] + (S(i - 1).w + S(i).w) / 2 + g.gap;
    for (var j = c - 1; j >= c - R; j--) xs[j] = xs[j + 1] - (S(j + 1).w + S(j).w) / 2 - g.gap;
    var shift = f * (xs[c + 1] - xs[c]), out = [];
    for (var q = c - R; q <= c + R + 1; q++) { if (q < 0 || q >= total) continue; out.push({ i: q, x: xs[q] - shift, w: S(q).w, h: S(q).h, d: q - pos }); }
    return out;
  }
  function render() {
    var g = geo(), list = row(g), c = Math.round(pos);
    for (var k = 0; k < RN; k++) {
      var n = rPool[k], o = list[k];
      if (!o) { n.el.hidden = true; n.i = -2; continue; }
      var ad = Math.abs(o.d);
      n.el.hidden = false;
      n.el.style.width = o.w.toFixed(1) + 'px'; n.el.style.height = o.h.toFixed(1) + 'px';
      n.el.style.transform = 'translate(' + (g.W / 2 + o.x - o.w / 2).toFixed(1) + 'px,' + ((g.H - o.h) / 2).toFixed(1) + 'px)';
      n.el.style.zIndex = String(100 - Math.round(ad * 10));
      n.el.style.opacity = String(Math.max(0.35, 1 - Math.min(1, ad) * 0.5));
      n.el.classList.toggle('c', ad < 0.5);
      n.num.style.fontSize = Math.max(14, Math.round(o.h / 9)) + 'px';
      var src = (ad < 0.5 && big[o.i] === 2) ? thumbBig(o.i) : (st[o.i] === 2 || big[o.i] === 2 ? (st[o.i] === 2 ? thumb(o.i) : thumbBig(o.i)) : '');
      if (n.i !== o.i || n.src !== src) {
        n.i = o.i; n.num.textContent = String(o.i + 1); n.src = src;
        if (src) { n.im.src = src; n.im.hidden = false; } else { n.im.hidden = true; n.im.removeAttribute('src'); }
      }
    }
    // fix164 가운데 그림 아래 단추·안내 자리(그림 줄 높이가 모자라면 그림 아래쪽 안으로)
    var cen = null; for (var ci = 0; ci < list.length; ci++) if (Math.abs(list[ci].d) < 0.5) { cen = list[ci]; break; }
    var dn = tipDone('rp');
    rOb.hidden = !dn || !cen; rHint.hidden = dn || !cen;
    if (cen) {
      var top0 = rSt.offsetTop, cb = (g.H + cen.h) / 2, by = Math.max(g.H / 2, Math.min(cb + 12, g.H - 44 - 6));
      rOb.style.top = (top0 + by).toFixed(0) + 'px'; rOb.textContent = String(T.rp_open || '%d').replace('%d', String(c + 1));
      rHt.style.top = (top0 + by).toFixed(0) + 'px';
      rHand.style.top = (top0 + g.H / 2 - 42).toFixed(0) + 'px';
    }
    rLab.textContent = (c + 1) + ' / ' + total;
    rBack.hidden = (c === origin);
    rBack.textContent = '↩ ' + String(T.rp_back || '') + ' ' + (origin + 1);
    var pc = (total > 1 ? pos / (total - 1) * 100 : 0).toFixed(2) + '%';
    rFl.style.width = pc; rKn.style.left = pc;
    rOg.style.left = (total > 1 ? origin / (total - 1) * 100 : 0).toFixed(2) + '%';
  }
  function fill(j) { if (!rp.hidden && Math.abs(j - pos) <= 4) render(); }
  function place() { rp.style.top = (barH() + (prog.offsetHeight || 12)) + 'px'; }
  function rOpen(i) {
    if (rp.hidden) { origin = curPage(); place(); rp.hidden = false; document.body.classList.add('mcv-rp-on'); rPool.forEach(function (n) { n.i = -2; n.src = ''; }); window.addEventListener('keydown', rKey, true); }
    pos = clamp(i); render(); want(Math.round(pos), false); wantBig(Math.round(pos));
  }
  function rClose() {
    if (rp.hidden) return;
    cancelAnimationFrame(raf); clearTimeout(wheelT); sd = null; bd = false; busy = false;
    rp.hidden = true; document.body.classList.remove('mcv-rp-on'); window.removeEventListener('keydown', rKey, true);
    tipPlace(); // fix164 판에 가려 있던 말풍선 자리 다시
  }
  function settle(target, commit) {
    cancelAnimationFrame(raf); busy = true;
    var from = pos, t0 = performance.now(), dur = 240;
    var step = function (t) {
      var q = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - q, 3);
      pos = from + (target - from) * e; render();
      if (q < 1) { raf = requestAnimationFrame(step); return; }
      pos = target; render(); busy = false;
      // ★ (2026-11-14 08:21, 펜닐 요청 — fix155) 그림에 멈추면 그 쪽이 열려야 함 — 종전엔 뒤에서 넘어가기만 하고 판이 어둡게 덮은 채 남아 안 보였음. 이동하고 판을 닫음
      if (commit) { tipSet('rp'); go(target); rClose(); return; } // fix164: 가운데 그림으로 열어 봤으면 안내 확인으로 셈
      want(target, false); wantBig(target); wantBig(target + 1); wantBig(target - 1);
    };
    raf = requestAnimationFrame(step);
  }
  function rKey(e) { // 판이 열려 있는 동안 ←→ 는 판에서(뒤쪽 북모드 쪽 넘김 키로 안 감), Esc 는 판만 닫음
    if (rp.hidden) return;
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); rClose(); return; }
    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); e.stopPropagation(); settle(clamp(Math.round(pos) + (e.key === 'ArrowRight' ? 1 : -1)), false); }
    if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); settle(clamp(Math.round(pos)), true); } // fix156: Enter = 가운데 그림 열기(키보드용)
  }
  // ---- 그림 줄 끌기 ----
  rSt.addEventListener('pointerdown', function (e) {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    cancelAnimationFrame(raf); busy = false;
    try { rSt.setPointerCapture(e.pointerId); } catch (x) {}
    var g = geo(), cw = size(Math.round(pos), 1, g).w;
    sd = { x: e.clientX, p: pos, t: performance.now(), px: Math.max(120, cw * 0.8 + g.gap), m: false };
    e.preventDefault();
  });
  rSt.addEventListener('pointermove', function (e) {
    if (!sd) return;
    if (Math.abs(e.clientX - sd.x) > 4) sd.m = true;
    pos = clamp(sd.p - (e.clientX - sd.x) / sd.px); render();
    focus = Math.round(pos); pump();
  });
  function stUp(e, cancel) {
    if (!sd) return;
    var d0 = sd; sd = null;
    try { rSt.releasePointerCapture(e.pointerId); } catch (x) {}
    if (cancel) { settle(Math.round(d0.p), false); return; }
    if (!d0.m) { // 누르기: 누른 쪽으로(가운데를 누르면 그대로)
      var g = geo(), r = rSt.getBoundingClientRect(), lx = e.clientX - r.left - g.W / 2, best = -1, bdist = 1e9;
      var ly = e.clientY - r.top; // fix156: 위아래 빈 곳 누르기는 그림 누르기로 안 셈(폰 세로에서 그림 위 빈 곳을 눌러도 열렸음 — 시험 재현)
      row(g).forEach(function (o) { var dd = Math.abs(o.x - lx); if (dd < bdist && Math.abs(o.x - lx) < o.w / 2 + g.gap && Math.abs(ly - g.H / 2) <= o.h / 2 + 8) { bdist = dd; best = o.i; } });
      // ★ (2026-11-14 08:44, 펜닐 요청 — fix156) ComicGlass 처럼 가운데 그림을 누를 때만 그 쪽을 열고 판을 닫음 — 옆 그림 누르기는 가운데로 가져오기만
      if (best < 0) { settle(Math.round(d0.p), false); return; } // 그림 없는 빈 곳 누르기는 아무것도 안 함
      settle(best, best === Math.round(d0.p)); return;
    }
    var dx = e.clientX - d0.x, fast = Math.abs(dx) / Math.max(1, performance.now() - d0.t) > 0.4;
    var k = Math.round(-dx / d0.px); if (k === 0 && (fast || Math.abs(dx) > 50)) k = dx < 0 ? 1 : -1;
    settle(clamp(Math.round(d0.p) + k), false); // fix156: 고르기만 — 열기는 가운데 그림 누르기
  }
  rSt.addEventListener('pointerup', function (e) { stUp(e, false); });
  rSt.addEventListener('pointercancel', function (e) { stUp(e, true); });
  rp.addEventListener('wheel', function (e) {
    e.preventDefault(); e.stopPropagation(); // 뒤쪽 화면이 같이 움직이지 않게
    cancelAnimationFrame(raf);
    var dl = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
    if (e.deltaMode === 1) dl *= 30;
    pos = clamp(pos + dl / 120); render(); focus = Math.round(pos); pump();
    clearTimeout(wheelT); wheelT = setTimeout(function () { settle(Math.round(pos), false); }, 160); // fix156: 고르기만 — 열기는 가운데 그림 누르기
  }, { passive: false });
  rp.addEventListener('touchmove', function (e) { e.preventDefault(); }, { passive: false }); // 판 위에서 뒤 화면 스크롤 막음
  // ---- 이동 줄(책 전체) ----
  function barAt(e) { var r = rBar.getBoundingClientRect(); return Math.max(0, Math.min(1, (e.clientX - r.left) / Math.max(1, r.width))) * (total - 1); }
  rBar.addEventListener('pointerdown', function (e) {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    cancelAnimationFrame(raf); busy = false;
    try { rBar.setPointerCapture(e.pointerId); } catch (x) {}
    bd = true; pos = barAt(e); render(); want(Math.round(pos), true);
    e.preventDefault();
  });
  rBar.addEventListener('pointermove', function (e) { if (!bd) return; pos = barAt(e); render(); focus = Math.round(pos); pump(); });
  function barUp(e, cancel) { if (!bd) return; bd = false; try { rBar.releasePointerCapture(e.pointerId); } catch (x) {} settle(Math.round(pos), false); } // fix156: 고르기만 — 열기는 가운데 그림 누르기
  rBar.addEventListener('pointerup', function (e) { barUp(e, false); });
  rBar.addEventListener('pointercancel', function (e) { barUp(e, true); });
  rBack.addEventListener('click', function () { settle(origin, false); }); // fix156: 고르기만 — 열기는 가운데 그림 누르기
  rX.addEventListener('click', rClose);
  rOb.addEventListener('click', function () { settle(clamp(Math.round(pos)), true); }); // fix164 A-1 'N쪽 열기' = 가운데 그림 누르기와 같음
  // ---- 위 진행 줄 ----
  prog.addEventListener('pointerdown', function (e) {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    drag = true; moved = false; sx = e.clientX;
    if (!tipDone('prog')) tipSet('prog'); // fix164: 진행 줄을 써 봤으면 안내 확인으로 셈
    try { prog.setPointerCapture(e.pointerId); } catch (x) {}
    cancelAnimationFrame(raf); busy = false;
    rOpen(idxAt(e.clientX)); want(Math.round(pos), true);
  });
  prog.addEventListener('pointermove', function (e) {
    if (!drag) return;
    if (Math.abs(e.clientX - sx) > 4) moved = true;
    pos = idxAt(e.clientX); render(); want(Math.round(pos), true);
  });
  function end(e, cancel) {
    if (!drag) return;
    drag = false;
    try { prog.releasePointerCapture(e.pointerId); } catch (x) {}
    if (cancel) return;
    // 뒤따르는 click 은 먹음 — viewer-bar.js 의 누르기 이동이 따로 돌지 않게(fix154: click 의 x 로 따로 셈해 쪽 경계에서 판 번호와 한 쪽 어긋났음)
    eat = true; setTimeout(function () { eat = false; }, 400);
    var gi = idxAt(e.clientX); pos = gi; render(); wantBig(gi);
    // fix156: 진행 줄은 판을 그 자리에 여는 것만(누르기·끌기 모두) — 쪽 이동은 판에서 가운데 그림을 누를 때
  }
  prog.addEventListener('pointerup', function (e) { end(e, false); });
  prog.addEventListener('pointercancel', function (e) { end(e, true); });
  // 놓은 뒤 따라오는 click 은 먹음(viewer-bar.js 의 누르기 이동이 한 번 더 부르지 않게)
  prog.addEventListener('click', function (e) { if (eat) { eat = false; e.stopImmediatePropagation(); e.preventDefault(); } }, true);
  // ---- 닫기 ----
  // ★ (2026-11-14 08:55, 펜닐 요청 — fix157) 판 바깥(위 막대) 누르기로 닫던 것 뺌 — 닫기는 × · Esc · 가운데 그림 누르기(열기) · 화면 폭이 바뀜
  var rW = window.innerWidth;
  window.addEventListener('resize', function () { if (window.innerWidth !== rW) { rW = window.innerWidth; rClose(); } else if (!rp.hidden) { place(); render(); } });

  // ============================== 처음 안내 → 확인 뒤 모양 표시 ==============================
  // ★ (2026-11-14 21:21, 펜닐 요청 — fix164) 진행 줄(B)·쪽 번호 칸(C): 확인 전엔 말풍선 하나씩(진행 줄 먼저, [알겠어요] 누르면 쪽 번호), 확인 뒤엔 진행 줄 손잡이(B-1)·쪽 번호 ▦ ▾(C-1)
  //   확인 = [알겠어요] 또는 그 기능을 직접 써 봄(진행 줄 누르기·쪽 번호 누르기). 말풍선은 판·메뉴·쪽 목록이 열린 동안 숨김(CSS)
  var posEl = document.getElementById('mcvPos'), tip = null, tipK = '', tipTgt = null;
  function tipHide() { if (tip) { tip.remove(); tip = null; } if (tipTgt) tipTgt.classList.remove('mcv-tip-on'); tipTgt = null; tipK = ''; }
  function tipPlace() {
    if (!tip || !tipTgt) return;
    var r = tipTgt.getBoundingClientRect(), w = tip.offsetWidth, vw = document.documentElement.clientWidth || window.innerWidth;
    if (!w) { clearTimeout(tipPlace.t); tipPlace.t = setTimeout(tipPlace, 300); return; } // 판·메뉴에 가려 크기가 없을 때는 나중에
    var ax = tipK === 'prog' ? Math.max(r.left + 12, Math.min(r.right - 12, r.left + (prog.querySelector('i') ? prog.querySelector('i').getBoundingClientRect().width : r.width / 2))) : r.left + r.width / 2;
    var left = Math.max(12, Math.min(vw - w - 12, ax - w / 2));
    var pb = prog.getBoundingClientRect().bottom; // 늘 진행 줄 아래 — PC 는 쪽 번호가 진행 줄 바로 위라 말풍선이 진행 줄을 덮어 못 눌렀음(시험 재현)
    tip.style.left = left.toFixed(0) + 'px'; tip.style.top = (Math.max(r.bottom, pb) + 10).toFixed(0) + 'px';
    tip.style.setProperty('--ax', Math.max(14, Math.min(w - 26, ax - left - 6)).toFixed(0) + 'px');
  }
  function tipShow(k, tgt, text) {
    tipHide(); if (!tgt || !text) return;
    tipK = k; tipTgt = tgt; tgt.classList.add('mcv-tip-on');
    tip = document.createElement('div'); tip.className = 'mcv-tip'; tip.setAttribute('role', 'status');
    var tx = document.createElement('div'); tx.textContent = String(text); tip.appendChild(tx);
    var b = document.createElement('button'); b.type = 'button'; b.className = 'mcv-tip-b'; b.textContent = String(T.tip_ok || 'OK'); tip.appendChild(b);
    b.addEventListener('click', function (e) { e.stopPropagation(); var kk = tipK; tipHide(); tipSet(kk); });
    document.body.appendChild(tip); tipPlace();
  }
  function tipNext() {
    if (tip) return;
    if (!tipDone('prog')) tipShow('prog', prog, T.tip_prog);
    else if (posEl && !tipDone('pos')) tipShow('pos', posEl, T.tip_pos);
  }
  window.mcvTipApply = function () {
    prog.classList.toggle('mcv-kn', tipDone('prog'));
    if (posEl) posEl.classList.toggle('mcv-ic', tipDone('pos'));
    if (tip && tipDone(tipK)) tipHide();
    tipNext();
    if (!rp.hidden) render();
  };
  if (posEl) posEl.addEventListener('click', function () { if (!tipDone('pos')) tipSet('pos'); }); // 쪽 번호를 눌러 봤으면 확인으로 셈
  window.addEventListener('resize', tipPlace);
  prog.classList.toggle('mcv-kn', tipDone('prog'));
  if (posEl) posEl.classList.toggle('mcv-ic', tipDone('pos'));
  setTimeout(tipNext, 900); // 위 막대 자리(두 줄 제목 등)가 잡힌 뒤
})();
