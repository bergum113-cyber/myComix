/**
 * myComix 뷰어 위 막대(툰모드·북모드) — viewer_bar.php 와 짝
 * ★ (2026-11-01) 시안 S2. 기존 함수(save_bookmark·set_cover·bright_*·lightGallery)를 그대로 부르기만 함
 */
(function () {
  'use strict';
  var C = window.MCV_BAR;
  if (!C) return;
  var T = C.t || {};
  var isBook = C.mode === 'book';
  var $id = function (x) { return document.getElementById(x); };
  var bar = $id('mcvBar');
  if (!bar) return;

  function barH() { return bar.getBoundingClientRect().bottom || 48; }

  // ★ (2026-11-01) 브라우저(기기)마다 기억 — 쿠키(서버가 열 때 보기 방식·모드를 읽음), 1년, 이 사이트 전체
  function setPref(k, v) { try { document.cookie = k + '=' + encodeURIComponent(String(v)) + '; path=/; max-age=31536000; SameSite=Lax'; } catch (e) {} }
  function getPref(k) { try { var m = document.cookie.match(new RegExp('(?:^|; )' + k + '=([^;]*)')); return m ? decodeURIComponent(m[1]) : null; } catch (e) { return null; } }

  // ★ (2026-11-01) 제목이 길면 막대가 여러 줄 → 실제 높이를 --mcv-h 로(본문·북모드 그림 영역·메뉴 위치가 따라감)
  var lastH = 0;
  function syncBarH() {
    var h = Math.round(bar.getBoundingClientRect().height);
    if (!h || h === lastH) return;
    lastH = h;
    document.documentElement.style.setProperty('--mcv-h', h + 'px');
    if (isBook) { try { window.dispatchEvent(new Event('resize')); } catch (e) {} } // lightGallery 가 그림 크기를 다시 계산
  }
  // 제목이 2줄을 넘으면 두 줄 배치(제목에 폭을 다 줌) — 화면 크기·방향이 바뀌면 다시 판단
  var nameEl = bar.querySelector('.mcv-name');
  function relayout() {
    if (nameEl) {
      bar.classList.remove('mcv-2row');
      var lh = parseFloat(getComputedStyle(nameEl).lineHeight) || 19;
      if (nameEl.getBoundingClientRect().height > lh * 2.5) bar.classList.add('mcv-2row');
    }
    syncBarH();
  }
  relayout();
  try { new ResizeObserver(syncBarH).observe(bar); } catch (e) {}
  var rlT = 0;
  window.addEventListener('resize', function () { clearTimeout(rlT); rlT = setTimeout(relayout, 120); });
  window.addEventListener('orientationchange', function () { setTimeout(relayout, 300); });
  try { if (document.fonts && document.fonts.ready) document.fonts.ready.then(relayout); } catch (e) {}

  // ★ (2026-11-01) 밝기 기억 — 예전 밝기 함수는 지금 보이는 그림에만 적용(새로 불러온 그림·다음 쪽은 원래대로)이라
  //   같은 값을 규칙(style)으로도 넣어 모든 그림에 똑같이. 값은 예전 함수와 같은 계산
  var brightCss = document.createElement('style'); brightCss.id = 'mcvBrightCss'; document.head.appendChild(brightCss);
  function applyBright(n) {
    n = Math.max(-5, Math.min(5, parseInt(n, 10) || 0));
    brightCss.textContent = n ? ('.img-fluid, .lg-image { filter: brightness(' + (1 + n * 0.04) + ') contrast(' + (1 + n * 0.1) + ') !important; }') : '';
    return n;
  }
  function saveBright() {
    var n = (typeof window.bright_counter === 'number') ? window.bright_counter : 0;
    setPref('mcv_bright', applyBright(n));
  }
  (function () {
    var n = parseInt(getPref('mcv_bright'), 10);
    if (n && n >= -5 && n <= 5) { try { window.bright_counter = n; } catch (e) {} applyBright(n); }
  })();
  // 북모드 → 다른 길(예전 ✕ 등)로 툰모드가 돼도 기억이 맞게
  if (window.jQuery) window.jQuery(document).on('onCloseAfter.lg.mcvpref', function () { setPref('mcv_mode', 'toon'); });

  // ── 알림(짧게) ──
  var toastT = 0;
  function toast(msg) {
    var el = $id('mcvToast'); if (!el || !msg) return;
    el.textContent = String(msg);
    el.classList.add('mcv-show');
    clearTimeout(toastT);
    toastT = setTimeout(function () { el.classList.remove('mcv-show'); }, String(msg).length > 30 ? 4000 : 2200); // ★ (2026-11-03 01:24) 긴 안내(책갈피는 하나만 남음)는 조금 더 오래
  }
  // 기존 함수들은 결과를 숨은 #info 칸에 적음 → 바뀌면 알림으로 보여 줌
  function watchInfo(run) {
    var info = $id('info');
    var before = info ? info.value : '';
    try { run(); } catch (e) { return; }
    if (!info) return;
    var n = 0, last = before;
    var iv = setInterval(function () {
      n++;
      var v = info.value;
      if (v && v !== last) {
        last = v;
        var m = /^#(\d+)-OK$/.exec(v); // 책갈피 저장 응답 '#번호-OK' → 알아보기 쉬운 문구로
        toast(m ? String(T.saved || '').replace('%s', parseInt(m[1], 10) + 1) : v);
        // ★ (2026-11-03 00:10) 메뉴의 책갈피 상태도 바로('책갈피 · N쪽 저장됨')
        if (m) { var bl = $id('mcvBmLbl'), bb = $id('mcvBm'); if (bl && T.bm_at) bl.textContent = String(T.bm_at).replace('%s', parseInt(m[1], 10) + 1); if (bb) bb.classList.add('mcv-on'); }
      }
      if (n > 40) clearInterval(iv); // 4초
    }, 100);
  }

  // ★ (2026-11-01 07:53) 제목 누르면 전체 복사 — 끌어서 일부를 골랐으면(선택이 있으면) 그대로 둠(Ctrl+C·길게 눌러 복사)
  //   https 가 아니면 navigator.clipboard 가 없어서 예전 방식(execCommand)으로
  function copyText(s, done) {
    function legacy() {
      var ta = document.createElement('textarea');
      ta.value = s; ta.setAttribute('readonly', '');
      ta.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;';
      document.body.appendChild(ta);
      var okc = false;
      try { ta.select(); ta.setSelectionRange(0, s.length); okc = document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
      try { var sel = window.getSelection(); if (sel) sel.removeAllRanges(); } catch (e) {}
      if (okc) done();
    }
    try {
      if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(s).then(done, legacy); return; }
    } catch (e) {}
    legacy();
  }
  // ★ (2026-11-02 16:55) 제목은 한 줄 — 누르면 아래 창에 전체 제목, 복사는 그 창의 [제목 복사] 단추로(누르기·끌어 고르기와 안 겹침)
  //   창 안 제목은 끌어서 일부만 골라 복사할 수도 있음
  var nameCopy = $id('mcvName'), titlePop = $id('mcvTitlePop'), copyBtn = $id('mcvCopyTitle');
  if (nameCopy && titlePop) {
    nameCopy.addEventListener('click', function () { togglePop(nameCopy, titlePop); });
    nameCopy.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); togglePop(nameCopy, titlePop); } });
  }
  if (copyBtn) copyBtn.addEventListener('click', function () {
    var s = (typeof C.title === 'string' && C.title) ? C.title : ((nameCopy && nameCopy.textContent) || '').trim();
    if (s) copyText(s, function () { closePop(); toast(T.copied); });
  });

  // ── 펼침 메뉴 ──
  var openPop = null, openBtn = null;
  function closePop() {
    document.documentElement.classList.remove('mcv-popopen'); // ★ (2026-11-02 16:55) 메뉴가 닫히면 다크모드·맨 위로 단추 다시 보임
    if (openPop) openPop.hidden = true;
    if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
    openPop = null; openBtn = null;
  }
  function togglePop(btn, pop) {
    if (!btn || !pop) return;
    if (openPop === pop) { closePop(); return; }
    closePop();
    pop.hidden = false;
    document.documentElement.classList.add('mcv-popopen'); // ★ (2026-11-02 16:55) 열려 있는 동안 다크모드·맨 위로 단추 숨김(메뉴를 가렸음)
    if (pop.id === 'mcvMorePop') { // ★ (2026-11-02 16:57) 가로 모드 두 칸 — 보이는 항목 수의 절반씩 위에서 아래로
      pop.style.gridTemplateRows = '';
      if (pop.offsetWidth && getComputedStyle(pop).display === 'grid') {
        var n = 0; Array.prototype.forEach.call(pop.children, function (c) { if (getComputedStyle(c).display !== 'none') n++; });
        pop.style.gridTemplateRows = 'repeat(' + Math.max(1, Math.ceil(n / 2)) + ', auto)';
      }
    }
    var r = btn.getBoundingClientRect();
    if (pop.id === 'mcvTitlePop') pop.style.width = Math.min(560, window.innerWidth - 16) + 'px'; // 전체 제목 창은 제목 왼쪽에 맞춰 넓게
    var w = pop.offsetWidth;
    var left = pop.id === 'mcvTitlePop' ? Math.min(Math.max(8, r.left - 4), window.innerWidth - w - 8)
      : Math.min(Math.max(8, r.right - w), window.innerWidth - w - 8);
    pop.style.left = left + 'px';
    pop.style.top = (barH() + 4) + 'px';
    btn.setAttribute('aria-expanded', 'true');
    openPop = pop; openBtn = btn;
    var first = pop.querySelector('input, .mcv-mi.mcv-on, .mcv-mi:not([aria-disabled="true"])');
    if (first && pop.id === 'mcvGotoPop') { try { first.focus(); first.select && first.select(); } catch (e) {} }
  }
  document.addEventListener('click', function (e) {
    if (!openPop) return;
    if (openPop.contains(e.target) || (openBtn && openBtn.contains(e.target))) return;
    closePop();
  }, true);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && openPop) { var b = openBtn; closePop(); try { b && b.focus(); } catch (x) {} return; }
    // ★ (2026-11-02 17:48) 창이 열린 채 쪽 넘김 키(←→·PageUp/Down·Space·Home/End)로 쪽이 넘어가면 창도 닫음(창 안 입력칸은 그대로)
    if (openPop && /^(Arrow(Left|Right|Up|Down)|Page(Up|Down)|Home|End| )$/.test(e.key) && !(e.target && (openPop.contains(e.target) || (e.key === ' ' && openBtn && openBtn.contains(e.target))))) closePop(); // 창 안 키, 여는 단추의 Space(열고 닫기)는 그대로
  }, true);
  // ★ (2026-11-02 17:48) 휴대폰 북모드는 그림을 짧게 누르면 뷰어가 click 을 막아서(쪽 넘김) 창 바깥을 눌러도 안 닫혔음 → 누르는 순간에도 바깥이면 닫음
  document.addEventListener('pointerdown', function (e) {
    if (!openPop) return;
    if (openPop.contains(e.target) || (openBtn && openBtn.contains(e.target))) return;
    closePop();
  }, true);
  // ★ (2026-11-02 19:06) 폭이 바뀔 때만 닫음(회전 등) — 화면 키보드가 뜨면서 높이만 줄어드는 브라우저에서 쪽 번호 입력 창이 바로 닫히던 것
  var popW = window.innerWidth;
  window.addEventListener('resize', function () { if (window.innerWidth !== popW) { popW = window.innerWidth; closePop(); } });

  // ── 공통 메뉴 동작 ──
  function goReplace(url) { if (url) location.replace(url); }
  var back = $id('mcvBack');
  if (back) back.addEventListener('click', function (e) {
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return; // 새 탭은 그대로
    e.preventDefault();
    var u = back.getAttribute('href');
    if (window.mcBackGo) window.mcBackGo(u, 'replace'); else goReplace(u); // ★ (2026-11-05 22:40) 들어온 곳이 바로 앞 기록이면 브라우저 뒤로(기록이 두 번 쌓이지 않게) / 아니면 예전처럼 기록 없이 이동
  });
  var more = $id('mcvMore'), morePop = $id('mcvMorePop');
  if (more) more.addEventListener('click', function () { togglePop(more, morePop); });

  function setFav(on) {
    var it = $id('mcvFav'), lb = $id('mcvFavLbl');
    if (it) it.classList.toggle('mcv-on', !!on);
    if (lb) lb.textContent = on ? T.fav_remove : T.fav_add;
    // 숨긴 예전 단추도 맞춰 둠
    var old = $id('favBtn');
    if (old) {
      old.classList.toggle('btn-warning', !!on); old.classList.toggle('btn-outline-warning', !on);
      var sp = old.querySelector('span'); if (sp) sp.textContent = on ? '⭐' : '☆';
    }
  }
  function toggleFav() {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'bookmark.php?mode=toggle_favorite', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      try {
        var r = JSON.parse(xhr.responseText);
        if (r && r.success) { setFav(!!r.is_favorite); toast(r.message || (r.is_favorite ? T.fav_remove : T.fav_add)); }
        else if (r && r.error) toast(r.error);
      } catch (e) {}
    };
    xhr.send('file=' + encodeURIComponent(C.favPath) + '&bidx=' + encodeURIComponent(C.bidx));
  }

  function orderUrl(order) {
    if (isBook) {
      var keep = (typeof window.img_counter === 'number' && window.img_counter >= 0) ? Math.floor(window.img_counter) : 0;
      return './viewer.php?' + C.ft + 'mode=book&file=' + C.file + '&bidx=' + C.bidx + '&pageorder=' + order + '&ms=1#image' + keep;
    }
    // ★ (2026-11-01 23:37) 툰모드도 지금 모드·보던 쪽을 붙여서 다시 엶(ms=1: 모드 기억 설정이 모드를 바꾸지 않게, #imageN: 이어 보기 기록보다 지금 쪽 우선)
    var here = 0; try { here = current().idx; } catch (e) {}
    return './viewer.php?' + C.ft + 'mode=toon&file=' + C.file + '&bidx=' + C.bidx + '&pageorder=' + order + '&ms=1#image' + here;
  }

  // ★ (2026-11-05 15:40) 모드 기억 '책마다' — 막대에서 모드(툰↔북)·보기 방식을 바꾸면 이 책의 내 기록(자동 저장)을 바로 그 값으로(펜닐 제보)
  //   전에는 이 화면을 떠날 때의 자동 저장(예전 모드·보기 방식)이 뒤따라 덮어써, 다음에 열 때 예전 모드로 열리거나 다른 값으로 보였음
  //   · viewer.php 의 떠날 때 자동 저장도 mcAsViewer·mcAsOrder 를 보고 같은 값으로 저장(mcAsUrl)
  //   · 위치는 지금 보던 쪽(북모드 img_counter / 툰모드 맨 위 그림) — 직접 찍은 북마크(책갈피)는 안 건드림
  function beacon(u) { try { if (navigator.sendBeacon && navigator.sendBeacon(u)) return; } catch (e) {} try { var x = new XMLHttpRequest(); x.open('GET', u, true); x.send(); } catch (e) {} }
  function remember(viewer, order, pg) {
    if (viewer !== 'toon' && viewer !== 'book') return;
    if (!/^[0-4]$/.test(String(order))) order = '0';
    try { window.mcAsViewer = viewer; window.mcAsOrder = String(order); } catch (e) {}
    beacon('bookmark.php?mode=autosave&viewer=' + viewer + '&page_order=' + order + '&file=' + C.file + '&bookmark=' + encodeURIComponent('image' + (pg > 0 ? Math.floor(pg) : 0)) + '&bidx=' + C.bidx);
  }
  function hereIdx() {
    if (isBook) return (typeof window.img_counter === 'number' && window.img_counter >= 0) ? Math.floor(window.img_counter) : 0;
    try { return current().idx || 0; } catch (e) { return 0; }
  }

  var doFullscreen = function () {}, doToToon = function () {}; // 북모드에서 채움
  function onMenu(e) {
    var t = e.target.closest ? e.target.closest('[data-act], [data-order]') : null;
    if (!t) return;
    var act = t.getAttribute('data-act');
    var ord = t.getAttribute('data-order');
    if (ord !== null) {
      e.preventDefault(); closePop(); setPref('mcv_order', ord);
      if (ord !== C.order) {
        // ★ (2026-11-05 15:40) 이 책의 기록도 새 보기 방식으로 — 북모드는 바로 저장, 툰모드는 떠날 때 저장(쪽 안 위치 f 를 지우지 않게)만 새 값으로
        if (isBook) remember('book', ord, hereIdx()); else { try { window.mcAsOrder = String(ord); } catch (x) {} }
        goReplace(orderUrl(ord));
      }
      return;
    }
    if (act === 'vol') {
      if (t.getAttribute('aria-disabled') === 'true') { e.preventDefault(); return; }
      if (e.ctrlKey || e.metaKey || e.shiftKey) return;
      e.preventDefault(); closePop(); goReplace(t.getAttribute('href')); return;
    }
    if (act === 'bookmark') { closePop(); if (typeof window.save_bookmark === 'function') watchInfo(window.save_bookmark); return; }
    if (act === 'fav') { closePop(); toggleFav(); return; }
    if (act === 'cover') { closePop(); if (typeof window.set_cover === 'function') watchInfo(window.set_cover); return; }
    // ★ (2026-11-10 13:54, 펜닐 요청 — fix98) 지금 보는 쪽(위 막대 쪽 번호와 같은 쪽)을 파일 표지·폴더 표지로 / 자동으로 되돌리기 — viewer.php mcCoverPage
    if (act === 'cover-file' || act === 'cover-folder' || act === 'cover-auto') { closePop(); if (typeof window.mcCoverPage === 'function') { var ci = hereIdx(); watchInfo(function () { window.mcCoverPage(act.slice(6), ci); }); } return; }
    if (act === 'bright-down' && typeof window.bright_down === 'function') { watchInfo(window.bright_down); saveBright(); return; }
    if (act === 'bright-up' && typeof window.bright_up === 'function') { watchInfo(window.bright_up); saveBright(); return; }
    if (act === 'bright-zero' && typeof window.bright === 'function') { watchInfo(window.bright); saveBright(); return; }
    if (act === 'fullscreen') { closePop(); doFullscreen(); return; }
    if (act === 'to-toon') { closePop(); doToToon(); return; }
  }
  if (morePop) morePop.addEventListener('click', onMenu);

  // ★ (2026-11-01 23:37) 이어 보기(켬/끔) · 모드 기억(끔·전체·책마다) — 이 기기 쿠키, 다음에 책을 열 때부터 적용(서버가 열 때 읽음)
  var resumeIt = $id('mcvResume');
  if (resumeIt) resumeIt.addEventListener('click', function (e) {
    e.preventDefault(); e.stopPropagation();
    var on = resumeIt.getAttribute('aria-checked') !== 'true';
    resumeIt.setAttribute('aria-checked', on ? 'true' : 'false');
    setPref('mcv_resume', on ? '1' : '0');
    toast(on ? T.resume_on : T.resume_off);
  });
  var mmBox = $id('mcvModeMem');
  if (mmBox) mmBox.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-mm]') : null; if (!b) return;
    e.preventDefault(); e.stopPropagation();
    var v = b.getAttribute('data-mm');
    Array.prototype.forEach.call(mmBox.querySelectorAll('[data-mm]'), function (x) { var on = x === b; x.classList.toggle('mcv-on', on); x.setAttribute('aria-checked', on ? 'true' : 'false'); });
    setPref('mcv_modemem', v);
    toast((T.mm_tip || {})[v] || '');
  });
  // ★ (2026-11-05 15:40) 이 화면이 다시 보일 때(뒤로 가기로 보관된 화면·다른 탭에서 돌아옴) 설정 표시를 지금 쿠키 값으로 다시 맞춤
  //   다른 책에서 '모드 기억'·'이어 보기'를 바꾸고 돌아오면 이 화면엔 예전 선택이 남아 있었음(펜닐 제보 — 표시만, 저장값은 그대로)
  function syncPrefsUi() {
    try {
      if (mmBox) {
        var mm = getPref('mcv_modemem'); if (mm !== 'all' && mm !== 'book') mm = 'off';
        Array.prototype.forEach.call(mmBox.querySelectorAll('[data-mm]'), function (x) { var on = x.getAttribute('data-mm') === mm; x.classList.toggle('mcv-on', on); x.setAttribute('aria-checked', on ? 'true' : 'false'); });
      }
      if (resumeIt) resumeIt.setAttribute('aria-checked', getPref('mcv_resume') === '0' ? 'false' : 'true');
    } catch (e) {}
  }
  window.addEventListener('pageshow', function (e) { if (e.persisted) syncPrefsUi(); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') syncPrefsUi(); });

  // ============================== 북모드 ==============================
  if (isBook) {
    var view = $id('mcvView'), viewPop = $id('mcvViewPop');
    var toToonBtn = $id('mcvToToon');
    if (toToonBtn) toToonBtn.addEventListener('click', function () { closePop(); doToToon(); });
    if (view) view.addEventListener('click', function () { togglePop(view, viewPop); });
    if (viewPop) viewPop.addEventListener('click', onMenu);

    // 넘김 방향 반대로 — 예전 ◀▶ 단추와 같은 동작(쿠키·전역 상태·안내 문구)
    var rev = $id('mcvReverse');
    if (rev) rev.addEventListener('click', function () {
      var on = !(window.isReversedBook === true);
      try { window.isReversedBook = on; } catch (e) {} // viewer.php 의 전역 var
      document.cookie = 'viewer_reverse_nav=' + (on ? '1' : '0') + '; path=/; max-age=31536000';
      try { if (typeof window.updateNavLabels === 'function') window.updateNavLabels(); } catch (e) {}
      try { if (typeof window.updateSubHtmlAndButton === 'function') window.updateSubHtmlAndButton(); } catch (e) {}
      var chk = $id('chkReverseNav'); if (chk) chk.checked = on;
      rev.setAttribute('aria-checked', on ? 'true' : 'false');
    });

    // ★ (2026-11-04 18:01) 세로 화면은 한 쪽(켬/끔) — 펼침(1|2·2|1)일 때 세로 화면에서만 한 쪽씩. 그림 구성이 바뀌어서 보던 쪽 그대로 다시 엶
    var isUpright = function () { return window.innerHeight > window.innerWidth; };
    // 같은 주소(#쪽만 다르거나 같음)면 replace 로는 다시 열리지 않음 → 주소를 바꿔 두고 새로 읽기
    function reopenHere() {
      var u = orderUrl(C.order);
      // ★ (2026-11-04 18:30) 지금 장면의 첫 쪽으로(펼침 마지막 장면은 기록 쪽이 두 번째 쪽이라 한 쪽 뒤에서 열렸음)
      try {
        var inst = lg(), sp = window._mcBook && window._mcBook.slidePage;
        if (inst && sp && typeof sp[inst.index] === 'number') u = u.replace(/#image\d+$/, '#image' + sp[inst.index]);
      } catch (x) {}
      try {
        var nu = new URL(u, location.href);
        if (nu.href.split('#')[0] === location.href.split('#')[0]) { history.replaceState(history.state, '', nu.href); location.reload(); return; }
      } catch (x) {}
      goReplace(u);
    }
    var spreadOrder = (C.order === '1' || C.order === '2');
    var port1 = $id('mcvPort1');
    if (port1) port1.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      var on = port1.getAttribute('aria-checked') !== 'true';
      port1.setAttribute('aria-checked', on ? 'true' : 'false');
      setPref('mcv_port1', on ? '1' : '0');
      toast(on ? T.port1_on : T.port1_off);
      var want = on && spreadOrder && isUpright();
      var now = !!(window._mcBook && window._mcBook.portSingle);
      if (want !== now) setTimeout(reopenHere, 350);
    });
    // 화면을 돌려 세로↔가로가 바뀌면(켜져 있고 펼침일 때만) 다시 엶 — 폭이 바뀔 때만 봄(화면 키보드로 높이만 바뀌는 것 무시)
    if (spreadOrder) {
      var rotW = window.innerWidth, rotT = 0;
      var onRot = function () {
        if (window.innerWidth === rotW) return;
        rotW = window.innerWidth;
        clearTimeout(rotT);
        rotT = setTimeout(function () {
          if (getPref('mcv_port1') !== '1') return;
          if (document.fullscreenElement || document.webkitFullscreenElement) return; // ★ (2026-11-04 18:30) 전체화면 중엔 다시 열지 않음(다시 열면 전체화면이 풀림) — 지금 구성 그대로
          var want = isUpright(), now = !!(window._mcBook && window._mcBook.portSingle);
          if (want !== now) reopenHere();
        }, 450);
      };
      window.addEventListener('resize', onRot);
      window.addEventListener('orientationchange', function () { setTimeout(onRot, 300); });
    }

    // ★ (2026-11-04 22:08) 표지·가로 그림은 혼자(켬/끔, 이 기기 쿠키) · 이 책만 짝 한 쪽 밀기(이 기기에 책마다) — 그림 구성이 바뀌어 보던 쪽 그대로 다시 엶
    var soloIt = $id('mcvSolo'), shiftIt = $id('mcvShift');
    var offKey = 'mcv_off:' + String(C.favPath || '');
    if (shiftIt) { try { shiftIt.setAttribute('aria-checked', localStorage.getItem(offKey) === '1' ? 'true' : 'false'); } catch (x) {} }
    if (soloIt) soloIt.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      var on = soloIt.getAttribute('aria-checked') !== 'true';
      soloIt.setAttribute('aria-checked', on ? 'true' : 'false');
      setPref('mcv_solo', on ? '1' : '0');
      toast(on ? T.solo_on : T.solo_off);
      if (!(window._mcBook && window._mcBook.portSingle)) setTimeout(reopenHere, 350); // 세로 한 쪽으로 보는 중이면 다음에 펼칠 때 적용
    });
    if (shiftIt) shiftIt.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      var on = shiftIt.getAttribute('aria-checked') !== 'true';
      shiftIt.setAttribute('aria-checked', on ? 'true' : 'false');
      try { if (on) localStorage.setItem(offKey, '1'); else localStorage.removeItem(offKey); } catch (x) {}
      toast(on ? T.shift_on : T.shift_off);
      if (!(window._mcBook && window._mcBook.portSingle)) setTimeout(reopenHere, 350);
    });

    // ★ (2026-11-04 18:01) 화면 맞춤(화면·너비·높이) — body class 만 바꿈(다시 열지 않음). 너비·높이는 그림 칸을 밀어 봄
    var fitBox = $id('mcvFit');
    var fitCls = { screen: '', width: 'mcv-fit-w', height: 'mcv-fit-h' };
    function curWrap() { return document.querySelector('.lg-outer .lg-item.lg-current .lg-img-wrap'); }
    // ★ (2026-11-04 22:08) 높이 맞춤 시작 위치: 펼침 2|1(만화 순서)이면 오른쪽부터, 그 밖엔 왼쪽부터 — 그림이 다 불러와져야 폭이 정해져서 불러온 뒤에도 한 번 더
    var startRight = (C.order === '2');
    function resetScroll() {
      var w = curWrap(); if (!w) return;
      try { w.scrollTop = 0; w.scrollLeft = (startRight && /\bmcv-fit-h\b/.test(document.body.className)) ? w.scrollWidth : 0; } catch (x) {}
    }
    if (startRight) document.addEventListener('load', function (e) {
      var t = e.target;
      if (!t || t.tagName !== 'IMG' || !/\bmcv-fit-h\b/.test(document.body.className)) return;
      var w = curWrap(); if (w && w.contains(t)) resetScroll();
    }, true);
    function applyFit(v) {
      document.body.classList.remove('mcv-fit-w', 'mcv-fit-h');
      if (fitCls[v]) document.body.classList.add(fitCls[v]);
      resetScroll();
      try { window.dispatchEvent(new Event('resize')); } catch (x) {}
    }
    if (fitBox) fitBox.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-fit]') : null; if (!b) return;
      e.preventDefault(); e.stopPropagation();
      var v = b.getAttribute('data-fit');
      if (!(v in fitCls)) return;
      Array.prototype.forEach.call(fitBox.querySelectorAll('[data-fit]'), function (x) { var on = x === b; x.classList.toggle('mcv-on', on); x.setAttribute('aria-checked', on ? 'true' : 'false'); });
      setPref('mcv_fit', v);
      applyFit(v);
      toast((T.fit_tip || {})[v] || '');
    });
    // 쪽을 넘기면 새 쪽은 맨 위(왼쪽)부터
    if (window.jQuery) window.jQuery('#lightgallery').on('onAfterSlide.lg.mcvfit onAfterOpen.lg.mcvfit', function () { if (/\bmcv-fit-[wh]\b/.test(document.body.className)) setTimeout(resetScroll, 0); });
    // 휠: 그림 칸이 그 방향으로 더 밀릴 수 있으면 밀기만(쪽 넘김 막음), 끝까지 갔으면 예전처럼 쪽 넘김 / 높이 맞춤은 휠을 좌우 밀기로
    window.addEventListener('wheel', function (e) {
      var cls = document.body.className;
      var fw = /\bmcv-fit-w\b/.test(cls), fh = /\bmcv-fit-h\b/.test(cls);
      if (!fw && !fh) return;
      var w = curWrap(); if (!w || !w.contains(e.target)) return;
      var d = e.deltaY || 0; if (!d) return;
      if (fw) {
        var canDown = w.scrollTop + w.clientHeight < w.scrollHeight - 1, canUp = w.scrollTop > 0;
        if ((d > 0 && canDown) || (d < 0 && canUp)) e.stopPropagation(); // 브라우저가 칸을 밈
      } else {
        var canR = w.scrollLeft + w.clientWidth < w.scrollWidth - 1, canL = w.scrollLeft > 0;
        var px = d * (e.deltaMode === 1 ? 16 : (e.deltaMode === 2 ? w.clientWidth : 1)); // ★ (2026-11-04 18:30) 줄·쪽 단위 휠(Firefox 등)도 화소로
        if ((d > 0 && canR) || (d < 0 && canL)) { e.stopPropagation(); e.preventDefault(); w.scrollLeft += px; }
      }
    }, { capture: true, passive: false });

    function lg() {
      var el = window.jQuery ? window.jQuery('#lightgallery') : null;
      return el ? el.data('lightGallery') : null;
    }
    function slideCount(inst) {
      inst = inst || lg();
      if (inst && inst.s && inst.s.dynamicEl) return inst.s.dynamicEl.length;
      if (inst && inst.$items) return inst.$items.length;
      return 0;
    }
    var pos = $id('mcvPos');
    function showPos(idx) {
      var inst = lg(), n = slideCount(inst);
      if (typeof idx !== 'number') idx = (inst && typeof inst.index === 'number') ? inst.index : 0;
      if (pos && n) pos.textContent = (idx + 1) + ' / ' + n;
    }
    if (window.jQuery) {
      var $ = window.jQuery;
      $('#lightgallery').on('onAfterOpen.lg.mcv', function () { showPos(); });
      $('#lightgallery').on('onAfterSlide.lg.mcv onBeforeSlide.lg.mcv', function (ev, prev, index) { showPos(parseInt(index, 10) || 0); });
      $(function () { setTimeout(showPos, 300); setTimeout(showPos, 1200); });
    }
    // 쪽 번호 누르면 쪽 번호 입력
    var gotoPop = $id('mcvGotoPop'), gotoIn = $id('mcvGotoIn'), gotoMax = $id('mcvGotoMax'), gotoForm = $id('mcvGotoForm');
    if (pos) pos.addEventListener('click', function () {
      var inst = lg(), n = slideCount(inst);
      if (!n) return;
      if (gotoIn) { gotoIn.max = n; gotoIn.value = ((inst && inst.index) || 0) + 1; }
      if (gotoMax) gotoMax.textContent = '/ ' + n;
      togglePop(pos, gotoPop);
    });
    if (gotoForm) gotoForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var inst = lg(), n = slideCount(inst);
      var v = parseInt(gotoIn && gotoIn.value, 10);
      if (!inst || !n || !(v >= 1)) return;
      if (v > n) v = n;
      closePop();
      try { inst.slide(v - 1); } catch (x) {}
    });

    var full = $id('mcvFull');
    var canFull = !!(document.fullscreenEnabled || document.webkitFullscreenEnabled);
    if (full && !canFull) full.style.display = 'none'; // 아이폰 사파리 등 못 쓰는 곳은 숨김
    doFullscreen = function () {
      var d = document, el = d.documentElement;
      var on = d.fullscreenElement || d.webkitFullscreenElement;
      try {
        if (on) { (d.exitFullscreen || d.webkitExitFullscreen).call(d); }
        else { (el.requestFullscreen || el.webkitRequestFullscreen).call(el); }
      } catch (e) {}
    };
    doToToon = function () {
      // 예전 ✕ 와 같은 저장(자동 저장) 후 툰모드 — 보던 쪽(#imageN)을 붙여 그 자리에서 이어서
      //   (예전 ✕ 는 위치 없는 주소로 먼저 넘어가 늘 맨 위에서 시작했음)
      var pg = (typeof window.img_counter === 'number' && window.img_counter >= 0) ? Math.floor(window.img_counter) : 0;
      // ★ (2026-11-03 17:20) 직접 찍은 북마크는 안 건드림(viewer.php 북모드 닫기와 같게) — 자동 저장만
      // ★ (2026-11-05 15:40) 보기 방식(page_order)도 함께(빠져 있어 툰모드로 갈 때마다 '한 쪽'으로 초기화됐음) + 떠날 때 저장도 툰모드로(remember)
      remember('toon', C.order, pg);
      setPref('mcv_mode', 'toon');
      goReplace('./viewer.php?' + C.ft + 'mode=toon&file=' + C.file + '&bidx=' + C.bidx + '&ms=1#image' + pg); // ms=1: 막대에서 모드를 바꿈(모드 기억 설정보다 우선)
    };
    return;
  }

  // ============================== 툰모드 ==============================
  var box = $id('lightgallery');
  var total = box ? (parseInt(box.getAttribute('data-total'), 10) || 0) : 0;
  var posEl = $id('mcvPos'), prog = $id('mcvProg'), progI = prog ? prog.querySelector('i') : null, endCard = $id('mcvEnd');

  // 지금 맨 위(막대 바로 아래)에 걸친 그림 — 번호와 그 그림 안에서 얼마나 내려왔는지
  function current() {
    var top = barH() + 2;
    var imgs = box ? box.querySelectorAll('img[id^="image"]') : [];
    var idx = 0, frac = 0, rowTop = null;
    for (var k = 0; k < imgs.length; k++) {
      var r = imgs[k].getBoundingClientRect();
      if (r.height <= 0) continue;
      if (r.top <= top) {
        var m = imgs[k].id.match(/^image(\d+)/);
        if (m) {
          var ni = parseInt(m[1], 10);
          // ★ (2026-11-02 20:25) 펼침(나란히 두 쪽)은 같은 줄에서 앞 쪽 번호로 — 1|2 는 오른쪽 쪽이 문서상 뒤라 맨 위에서 "2 / N" 으로 나왔음
          if (rowTop !== null && Math.abs(r.top - rowTop) < 1 && ni > idx) continue;
          idx = ni; rowTop = r.top; frac = Math.max(0, Math.min(1, (top - r.top) / r.height));
        }
      } else break;
    }
    return { idx: idx, frac: frac };
  }
  // ★ (2026-11-10 17:22, 펜닐 요청 — fix103) 화면(위 막대 아래 ~ 화면 아래)에 보이는 쪽(0부터) — 위 막대 쪽 번호·%, 북모드로 바꿀 때 쪽, 서재 %(자동 저장 vp)·책갈피·표지의 공통 기준
  //   위에서부터 '자기 높이의 반 이상이 화면에 보이는' 첫 쪽(같은 줄 펼침이면 쪽 번호가 작은 쪽) — 없으면(쪽이 화면보다 커서 어느 쪽도 반이 안 보임) 가장 넓게 보이는 쪽
  //   ★ (2026-11-10 17:45 보강) 처음엔 '가장 넓게 보이는 쪽'만 봤는데, 휴대폰에서 짧은 가로 1쪽 밑에 긴 2쪽이 더 넓게 보여 책을 열자마자 '2 / 30' 이 됐음(회귀 시험 vres·vtt 에서 찾음)
  //   이어 보기 저장 위치(current() — 보던 줄)는 그대로
  function visIdx() {
    var top = barH(), vh = window.innerHeight || document.documentElement.clientHeight, vw = document.documentElement.clientWidth || window.innerWidth;
    var imgs = box ? box.querySelectorAll('img[id^="image"]') : [];
    var best = -1, bestA = 0, half = -1, halfTop = 0;
    for (var k = 0; k < imgs.length; k++) {
      var r = imgs[k].getBoundingClientRect();
      if (r.height <= 0 || r.width <= 0) continue;
      if (r.top >= vh) break; // 아래는 다 화면 밖(그림은 위에서 아래 순서)
      var h = Math.max(0, Math.min(r.bottom, vh) - Math.max(r.top, top)), a = h * Math.max(0, Math.min(r.right, vw) - Math.max(r.left, 0));
      if (a <= 0) continue;
      var m = imgs[k].id.match(/^image(\d+)/); if (!m) continue;
      var n = parseInt(m[1], 10);
      if (h >= r.height * 0.5 - 1) { // 반 이상 보임 — 처음 찾은 줄의 쪽(같은 줄이면 번호 작은 쪽)
        if (half < 0) { half = n; halfTop = r.top; } else if (Math.abs(r.top - halfTop) < 2 && n < half) half = n;
      }
      if (a > bestA * 1.01 || (Math.abs(a - bestA) <= bestA * 0.01 && n < best)) { best = n; bestA = a; }
    }
    if (half >= 0) return half;
    if (best < 0) { try { return current().idx; } catch (e) { return 0; } }
    return best;
  }
  try { window.mcVisibleIdx = isBook ? null : visIdx; } catch (e) {}
  function atBottom() {
    var se = document.scrollingElement || document.documentElement;
    return (window.innerHeight + (window.scrollY || se.scrollTop)) >= se.scrollHeight - 60;
  }
  // ★ (2026-11-02 20:21) 마지막 쪽(그림)이 다 불러와져 화면 안에 들어와 있나 — 펼침·짧은 그림은 끝 화면에 3쪽 넘게 보여
  //   맨 위 쪽 번호(c.idx)만으로는 끝으로 안 쳐져 '다음 권' 카드·100% 가 안 나왔음
  function lastShown() {
    var n = total - 1, ids = ['image' + n, 'image' + n + '_left', 'image' + n + '_right'], seen = false;
    for (var i = 0; i < ids.length; i++) {
      var el = $id(ids[i]); if (!el) continue;
      var r = el.getBoundingClientRect();
      if (!el.classList.contains('loaded') || r.height <= 20 || r.bottom > window.innerHeight + 60) return false;
      seen = true;
    }
    return seen;
  }
  var lastTxt = '';
  function update() {
    if (!total) return;
    var c = current(), v = visIdx();
    var end = atBottom() && (c.idx >= total - 2 || lastShown());
    // ★ (2026-11-10 17:22 fix103) 쪽 번호·% 는 화면에 가장 넓게 보이는 쪽으로 — % 는 서재(series.php: (쪽+1)/총쪽 내림)와 같은 셈. 전엔 맨 위 줄 쪽 + 쪽 안 위치라 화면·서재와 1쪽·몇 % 어긋났음
    var pct = end ? 100 : Math.max(0, Math.min(100, Math.floor((v + 1) / total * 100)));
    var page = end ? total : v + 1;
    var txt = page + ' / ' + total + ' · ' + pct + '%';
    if (posEl && txt !== lastTxt) { posEl.textContent = txt; lastTxt = txt; }
    if (progI) progI.style.width = pct + '%';
    if (endCard) endCard.classList.toggle('mcv-show', end);
  }
  var raf = 0;
  function sched() { if (!raf) raf = requestAnimationFrame(function () { raf = 0; update(); }); }
  window.addEventListener('scroll', sched, { passive: true });
  window.addEventListener('resize', sched);
  if (box) box.addEventListener('load', sched, true); // ★ (2026-11-02 20:50) 그림이 늦게 떠도 다시 계산 — 한 화면에 다 들어가 스크롤이 없으면 끝(다음 권 카드) 판정이 안 바뀌었음
  setTimeout(update, 200); setTimeout(update, 1500);

  // 진행 줄 누르면 그 위치(쪽)로
  if (prog) prog.addEventListener('click', function (e) {
    if (!total) return;
    var f = Math.max(0, Math.min(0.9999, e.clientX / Math.max(1, prog.clientWidth)));
    var idx = Math.floor(f * total);
    if (typeof window.mcvToonJump === 'function') window.mcvToonJump(idx);
    else { var el = $id('image' + idx) || $id('image' + idx + '_left'); if (el) el.scrollIntoView({ block: 'start' }); }
  });

  // 📖 북모드 — 지금 보던 쪽으로
  var toBook = $id('mcvToBook');
  if (toBook) toBook.addEventListener('click', function () {
    var c = { idx: visIdx() }; // ★ (2026-11-10 17:22 fix103) 화면에 가장 넓게 보이는 쪽으로(전엔 맨 위 줄 쪽 — 앞 쪽 끝이 조금 남아 있으면 그 앞 쪽부터 열렸음)
    remember('book', C.order, c.idx); // ★ (2026-11-05 15:40) 이 책의 기록을 바로 북모드로(떠날 때 저장도 북모드로)
    setPref('mcv_mode', 'book');
    goReplace('./viewer.php?' + C.ft + 'mode=book&file=' + C.file + '&bidx=' + C.bidx + '&ms=1#image' + c.idx);
  });
})();
