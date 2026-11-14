/* ★ (2026-11-14 16:45 fix158, 펜닐 요청) 서재·즐겨찾기·북마크 — 폴더/파일 목록처럼 표시
   - 마우스를 올리면 제목이 청록(#39c5bb), 눌러 본 항목은 제목이 회색(뒤로 돌아와도 남음)
   - 기록은 폴더 목록(index.php applyVisitedLinkStyle)과 같은 localStorage 'visited_' + 주소 — 목록에서 본 것도 같은 주소면 같이 회색
   - 클래스 이름은 visited-link 대신 mc-vis — darkmode.css 의 visited-link 규칙이 쪽 배지 등 다른 글자까지 바꾸지 않게
   - :not(#mcVisX) 는 우선순위 올리기용(darkmode.css 의 !important 링크 색보다 앞서게) */
(function () {
  // ★ (2026-11-14 18:48 fix161) + 서재 카드 '+N'(작품 화면으로 감) · 작품 화면(series.php) 권(a.vol)·같은 작가 다른 작품(a.sr-same-it)
  var SEL = 'a.sc-cover, a.sc-name, a.sc-more, a.fav-link, a.bm-link, a.vol, a.sr-same-it';
  var css =
    'a.sc-name:not(#mcVisX).mc-vis{color:#888!important}' +
    'a.bm-link:not(#mcVisX).mc-vis .bm-title{color:#888!important}' +
    'a.fav-link:not(#mcVisX).mc-vis .fav-title{color:#9a9a9a!important}' +
    '.dark-mode a.sc-name:not(#mcVisX).mc-vis,[data-theme="dark"] a.sc-name:not(#mcVisX).mc-vis,' +
    '.dark-mode a.bm-link:not(#mcVisX).mc-vis .bm-title,[data-theme="dark"] a.bm-link:not(#mcVisX).mc-vis .bm-title{color:#666!important}' +
    'a.vol:not(#mcVisX).mc-vis .vol-name,a.sr-same-it:not(#mcVisX).mc-vis .sr-same-nm{color:#888!important}' +
    '.dark-mode a.vol:not(#mcVisX).mc-vis .vol-name,[data-theme="dark"] a.vol:not(#mcVisX).mc-vis .vol-name,' +
    '.dark-mode a.sr-same-it:not(#mcVisX).mc-vis .sr-same-nm,[data-theme="dark"] a.sr-same-it:not(#mcVisX).mc-vis .sr-same-nm{color:#666!important}' +
    'a.sc-name:not(#mcVisX):hover,a.bm-link:not(#mcVisX):hover .bm-title,a.fav-link:not(#mcVisX):hover .fav-title,' +
    '.dark-mode a.sc-name:not(#mcVisX):hover,[data-theme="dark"] a.sc-name:not(#mcVisX):hover,' +
    '.dark-mode a.bm-link:not(#mcVisX):hover .bm-title,[data-theme="dark"] a.bm-link:not(#mcVisX):hover .bm-title{color:#39c5bb!important}' +
    // ★ (2026-11-14 19:08 fix162) 작품 화면 권·다른 작품 청록은 마우스가 있을 때만 — 폰은 권을 톡 눌러 고르면(이동 안 함) 누른 자리가 '올림' 상태로 남아 제목이 계속 청록이었음
    '@media (hover:hover) and (pointer:fine){' +
    'a.vol:not(#mcVisX):hover .vol-name,a.sr-same-it:not(#mcVisX):hover .sr-same-nm,' +
    '.dark-mode a.vol:not(#mcVisX):hover .vol-name,[data-theme="dark"] a.vol:not(#mcVisX):hover .vol-name,' +
    '.dark-mode a.sr-same-it:not(#mcVisX):hover .sr-same-nm,[data-theme="dark"] a.sr-same-it:not(#mcVisX):hover .sr-same-nm{color:#39c5bb!important}}' +
    // fix161: :has() 는 따로 — 같은 묶음에 두면 :has 를 모르는 옛 브라우저(파이어폭스 121·사파리 15.4 이전)에서 위 청록 규칙까지 통째로 무시됨
    '.sc:has(> a.sc-cover:hover) a.sc-name:not(#mcVisX){color:#39c5bb!important}';
  /* ★ (2026-11-14 17:32 fix159, 펜닐 지시) 폴더 목록(index.php '본 항목 표시')과 같은 주황 테두리
     - PC 마우스 올림: 옅은 주황 테두리 / 눌러 들어갔다 돌아오면: 그 항목에 진한 주황 테두리(#f39c12)
     - 기억 = 폴더 목록과 같은 localStorage 'mcViewedMap'(목록 주소마다 마지막 하나, 최대 12개), 돌아온 뒤 관리자 '복귀 표시 유지 시간'(viewed_mark_seconds, 0=안 사라짐)만큼 보이고 지움
     - 테두리는 항목 칸(서재 .sc · 즐겨찾기 .fav-item · 북마크 .bm-item) 전체 */
  var BOX = '.sc, .fav-item, .bm-item, .vol, .sr-same-it'; // fix161: 권·다른 작품은 링크 자체가 칸
  var me = document.currentScript;
  var HOLD = me && me.getAttribute('data-hold') !== null ? Math.max(0, parseFloat(me.getAttribute('data-hold')) || 0) * 1000 : 2000;
  css +=
    '@media (hover:hover) and (pointer:fine){.fav-item:hover,.bm-item:hover{outline:2px solid rgba(243,156,18,.45);outline-offset:2px;border-radius:4px}' +
    '.sc:hover::after{content:"";position:absolute;inset:0;border:2px solid rgba(243,156,18,.45);border-radius:4px;pointer-events:none;z-index:3}}' +
    '.fav-item.mc-viewed,.bm-item.mc-viewed{outline:2px solid #f39c12!important;outline-offset:2px;border-radius:4px}' +
    // 서재 칸: '읽는 중'·'최근' 가로 줄은 바깥이 잘리고 표지가 칸 테두리를 덮음 → 칸 안쪽에 표지 위로 덧그림
    '.sc{position:relative}.sc.mc-viewed::after{content:"";position:absolute;inset:0;border:2px solid #f39c12;border-radius:4px;pointer-events:none;z-index:3}' +
    // fix161 작품 화면: 권(.vol — 이미 position:relative)·다른 작품(.sr-same-it — 폰에선 가로 줄이라 바깥이 잘림)도 서재 칸처럼 안쪽 덧그림. 초록 '고른 권'(.is-pick, 표지 테두리)과는 따로
    '.sr-same-it{position:relative}' +
    '@media (hover:hover) and (pointer:fine){.vol:hover::after,.sr-same-it:hover::after{content:"";position:absolute;inset:-3px;border:2px solid rgba(243,156,18,.45);border-radius:5px;pointer-events:none;z-index:3}}' +
    '.vol.mc-viewed::after,.sr-same-it.mc-viewed::after{content:"";position:absolute;inset:-3px;border:2px solid #f39c12;border-radius:5px;pointer-events:none;z-index:3}' +
    '.sr-same-row .sr-same-it:hover::after,.sr-same-row .sr-same-it.mc-viewed::after{inset:0}';
  var MAP = 'mcViewedMap';
  function listId() { try { return location.pathname + location.search; } catch (e) { return ''; } }
  function vRead() { try { var v = JSON.parse(localStorage.getItem(MAP) || '{}'); return (v && typeof v === 'object') ? v : {}; } catch (e) { return {}; } }
  function vWrite(m) {
    try {
      var ks = Object.keys(m);
      if (ks.length > 12) { ks.sort(function (a, b) { return (m[a].t || 0) - (m[b].t || 0); }); for (var i = 0; i < ks.length - 12; i++) delete m[ks[i]]; }
      localStorage.setItem(MAP, JSON.stringify(m));
    } catch (e) {}
  }
  function vSet(h) { var m = vRead(); m[listId()] = { h: h, t: Date.now() }; vWrite(m); }
  function vClear() { var m = vRead(); delete m[listId()]; vWrite(m); }
  function mark(box) {
    var old = document.querySelectorAll('.mc-viewed');
    for (var i = 0; i < old.length; i++) if (old[i].matches && old[i].matches(BOX)) old[i].classList.remove('mc-viewed');
    if (box) box.classList.add('mc-viewed');
  }
  function restore() { // 새로 읽은 화면: 기억해 둔 항목에 표시(bfcache 복귀는 누를 때 붙인 표시가 그대로 남아 있음)
    var h = null; try { var e = vRead()[listId()]; h = e ? e.h : null; } catch (e2) {}
    if (!h || document.querySelector('.sc.mc-viewed, .fav-item.mc-viewed, .bm-item.mc-viewed, .vol.mc-viewed, .sr-same-it.mc-viewed')) return;
    var list = document.querySelectorAll(SEL);
    for (var i = 0; i < list.length; i++) if (hrefOf(list[i]) === h) { var b = list[i].closest(BOX); if (b) { b.classList.add('mc-viewed'); break; } }
  }
  function consume() { // 폴더 목록과 같게: 보이고 나면 기억은 지우고, 유지 시간이 지나면 테두리도 지움
    var mv = Array.prototype.slice.call(document.querySelectorAll('.sc.mc-viewed, .fav-item.mc-viewed, .bm-item.mc-viewed, .vol.mc-viewed, .sr-same-it.mc-viewed'));
    if (!mv.length) return;
    try { vClear(); } catch (e) {}
    if (HOLD > 0) setTimeout(function () { for (var i = 0; i < mv.length; i++) mv[i].classList.remove('mc-viewed'); }, HOLD);
  }

  try { var st = document.createElement('style'); st.id = 'mcVisCss'; st.textContent = css; document.head.appendChild(st); } catch (e) {}

  function hrefOf(a) { // 기억 열쇠 — 보통은 주소. 북마크 '이어 읽기'는 읽으면 저장 위치(#image…)가 바뀌어 주소가 달라지므로 파일(data-mcli = 폴더 번호|경로)로(fix159)
    var it = a.closest ? a.closest('.bm-sec[data-type="autosave"] .bm-item[data-mcli]') : null;
    if (it) return 'bm-as|' + it.getAttribute('data-mcli');
    // fix161 작품 화면 권: 돌아오면 이어 볼 쪽으로 주소가 바뀜(series.php refreshProgress) → 파일(file=·bidx=)로
    if (a.classList && a.classList.contains('vol')) {
      try { var u = new URL(a.getAttribute('href') || '', location.href), f = u.searchParams.get('file'); if (f) return 'vol|' + (u.searchParams.get('bidx') || '') + '|' + f; } catch (x) {}
    }
    var h = a.getAttribute('href') || '';
    return (h === '' || /^javascript:/i.test(h) || /^https?:/i.test(h)) ? '' : h;
  }
  function apply() {
    var list = document.querySelectorAll(SEL);
    for (var i = 0; i < list.length; i++) {
      var h = hrefOf(list[i]); if (!h) continue;
      var on = false; try { on = !!localStorage.getItem('visited_' + h); } catch (e) {}
      if (on) list[i].classList.add('mc-vis');
    }
  }
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest(SEL) : null; if (!a) return;
    // fix161: 작품 화면 권은 여럿이면 첫 누름이 '권 고르기'(이동 안 함, preventDefault) → 다른 누르기 처리가 끝난 뒤 막혔는지 보고 표시(나머지는 종전처럼 바로)
    if (a.classList && a.classList.contains('vol')) { setTimeout(function () { if (!e.defaultPrevented) seen(a); }, 0); return; }
    seen(a);
  }, true);
  function seen(a) {
    var h = hrefOf(a); if (!h) return;
    try { localStorage.setItem('visited_' + h, '1'); } catch (e2) {}
    var list = document.querySelectorAll(SEL); // 표지·제목처럼 같은 주소인 링크는 함께
    for (var i = 0; i < list.length; i++) if (hrefOf(list[i]) === h) list[i].classList.add('mc-vis');
    try { mark(a.closest(BOX)); vSet(h); } catch (e3) {} // fix159 주황 테두리
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { apply(); restore(); }); else { apply(); restore(); }
  window.addEventListener('pageshow', function (e) {
    // ★ (2026-11-14 18:06 fix160) 북마크는 보관 화면으로 돌아올 때 뷰어가 위치를 저장했으면(mycomix_marks_dirty) 스스로 새로 읽음(bookmarks.php)
    //   → 여기서 기억을 지우면 새로 읽힌 화면에 주황 테두리가 없음. 새로 읽을 화면이면 지우지 않고 넘김(새로 읽힌 뒤 표시하고 지움)
    try { if (e && e.persisted && document.querySelector('.bm-item') && sessionStorage.getItem('mycomix_marks_dirty')) return; } catch (x) {}
    apply(); restore(); consume();
  }); // 뒤로가기 복귀(bfcache)·새로 읽음 모두
})();
