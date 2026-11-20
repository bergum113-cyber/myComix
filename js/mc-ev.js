/* ★ (2026-11-19 11:17, 펜닐 요청 — fix243) 증상 기록 — 폰·PC 에서 "느리다·안 먹는다" 할 때 원인을 찾을 수 있게 src/perf/날짜.log 에 k:"u" 한 줄씩
 *   속도 기록과 같은 조건(src/perf 폴더 있음·관리자 설정 켬·로그인)일 때만 function.php mc_perf_script 가 이 파일을 붙임. 기록만 하고 화면 동작은 바꾸지 않음
 *   tap2     : 같은 것을 1.5초 안에 다시 누름(반응이 안 보여 또 누름 — 즐겨찾기 여러 번 등). 작품 화면 권(고르기→열기)의 정상 두 번은 뺌
 *   tap_slow : 누른 뒤 다음 화면 요청이 나가기까지 0.6초 넘게 걸림(누른 게 늦게 먹음·두 번 탭 판정 대기) — 5초 넘게 지난 누름·새로고침·뒤로가기는 셈에서 뺌(누름과 상관없는 이동)
 *   zoom     : 화면이 확대됨(두 번 탭 확대 등) — 직전 누름부터 걸린 시간 같이
 *   req_slow : 화면 안 요청(즐겨찾기·저장 등)이 2초 넘게 걸림 / req_fail : 실패(연결 끊김·서버 오류)
 *   back2    : 뒤로가기가 4초 안에 연달아 두 번(뒤로가기를 두 번 눌러야 했음)
 *   hist_add : 주소 뒤(#)만 바뀌며 방문 기록이 늘어남(뒤로가기가 한 번에 안 가는 원인)
 *   freeze   : 화면이 0.3초 넘게 멈춤(크롬·안드로이드만 — 아이폰은 알려주지 않음)
 *   jserr    : 화면 스크립트 오류
 *   한 화면에 최대 40줄. 주소는 파일 이름·mode/action 값만(검색어·쪽 번호 등은 안 남김) */
(function () {
  if (window.__mcEv) return; window.__mcEv = 1;
  var me = document.currentScript, P = (me && me.getAttribute('data-page')) || '';
  var left = 40, lastTap = 0;
  function send(ev, d) {
    if (left <= 0) return; left--;
    try {
      d = d || {}; d.k = 'u'; d.ev = ev; d.page = P; d.hl = history.length;
      var b = JSON.stringify(d);
      if (navigator.sendBeacon) navigator.sendBeacon('perf_log.php', b);
      else { var q = new XMLHttpRequest(); q.open('POST', 'perf_log.php', true); q.send(b); }
    } catch (e) {}
  }
  function lab(t) {
    var e = t && t.closest ? t.closest('a,button,[id],[data-act],label,input,select') : null;
    if (!e) return '';
    var s = e.tagName.toLowerCase();
    if (e.id) s += '#' + e.id;
    else if (e.getAttribute('data-act')) s += '.' + e.getAttribute('data-act');
    else if (e.classList && e.classList.length) s += '.' + e.classList[0];
    return s.replace(/[^A-Za-z0-9_#.\-]/g, '').slice(0, 40);
  }
  function path(u) {
    try {
      var x = new URL(String(u), location.href);
      if (x.origin !== location.origin) return '';
      var q = x.searchParams.get('mode') || x.searchParams.get('action') || '';
      return (x.pathname.split('/').pop() + (q ? '?' + q : '')).slice(0, 80);
    } catch (e) { return ''; }
  }
  var SS = null; try { SS = window.sessionStorage; } catch (e) {}
  window.mcEvLog = function (ev, d) { send(ev, d); }; // ★ (2026-11-20 22:34, fix264) 화면 스크립트가 같은 기록(한 화면 40줄 한도)에 남김 — perf_log.php 가 허용한 ev 만 저장

  // 누름: 같은 것 다시 누름 / 다음 화면까지 걸린 시간 재기용 시각
  var tk = '', tn = 0, tt = 0;
  document.addEventListener('click', function (e) {
    var now = Date.now();
    lastTap = now;
    // ★ (2026-11-19 11:28, 점검 — fix244) 단추·링크만 셈 — 뷰어 그림을 눌러 책장 넘기기·메뉴 열기(정상 연타)가 tap2 로 한 화면 40줄을 다 채웠음(div#lightgallery). 그림 누름은 '누른 시각'(확대 기록용)만
    var act = e.target && e.target.closest ? e.target.closest('a[href],button,[data-act],[role="button"],input,select,label') : null;
    if (!act) return;
    if (act.hasAttribute && act.hasAttribute('data-view')) return; // ★ (2026-11-20 23:09, 펜닐 요청 — fix265) 그리드/목록 단추(data-view — 폴더 목록·서재·작품 상세·즐겨찾기·북마크)는 빠르게 바꿔 보는 게 정상이라 셈에서 뺌(실제 로그 tap2 124건 중 120건이 이것). 다음 화면까지 걸린 시간(tap_slow)용 기록도 안 남김(화면을 넘기지 않는 단추)
    var el = lab(act), a = act.closest('a[href]');
    var key = el + '|' + (a ? a.getAttribute('href') : '');
    if (key === tk && now - tt < 1500) tn++; else tn = 1;
    var isVol = !!(a && a.closest && a.closest('#vols') && a.classList.contains('vol'));
    if (tn >= 2 && !(isVol && tn === 2)) send('tap2', { el: el, n: tn, gap: now - tt });
    tk = key; tt = now;
    try { if (SS) SS.setItem('mc_ev_tap', JSON.stringify({ t: now, p: P, el: el })); } catch (x) {}
  }, true);

  // 이 화면이 열리기 전 누름 → 요청이 나가기까지
  try {
    var tp = SS && SS.getItem('mc_ev_tap');
    if (tp) {
      SS.removeItem('mc_ev_tap');
      var o = JSON.parse(tp), t0 = performance.timeOrigin || (performance.timing && performance.timing.navigationStart) || 0;
      var nv = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || null;
      var ms = Math.round(t0 - o.t);
      if (o && o.t && o.el && t0 && ms >= 600 && ms < 5000 && !(nv && (nv.type === 'back_forward' || nv.type === 'reload'))) send('tap_slow', { from: String(o.p || ''), el: String(o.el || ''), ms: ms });
    }
  } catch (e) {}

  // 확대
  var vv = window.visualViewport, zoomed = false;
  if (vv) vv.addEventListener('resize', function () {
    var sc = vv.scale || 1;
    if (!zoomed && sc > 1.05) { zoomed = true; send('zoom', { sc: Math.round(sc * 100) / 100, gap: lastTap ? Date.now() - lastTap : -1 }); }
    else if (zoomed && sc <= 1.01) zoomed = false;
  });

  // 화면 안 요청(XHR·fetch)이 느림·실패
  function req(u, ms, st) {
    var p = path(u);
    if (!p || p.indexOf('perf_log.php') === 0) return;
    if (String(u).indexOf('mc_bg=1') >= 0) return; // ★ (2026-11-20 23:09 fix265) 서버 배경 갱신(index.php?mc_bg=1 — 화면을 연 지 3초 뒤 조용히 한 번, 라이브러리 목록·색인 다시 만들기)은 원래 오래 걸리고 다음 화면으로 넘어가면 끊겨도 서버가 끝까지 함(ignore_user_abort) → 느림·실패 기록에서 뺌(실제 로그 느림 3건·실패 4건이 이것)
    if (st === 0 || st >= 500) send('req_fail', { path: p, ms: ms, st: st });
    else if (ms >= 2000) send('req_slow', { path: p, ms: ms, st: st });
  }
  try {
    var XO = XMLHttpRequest.prototype.open, XS = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (m, u) { this.__mcU = u; return XO.apply(this, arguments); };
    XMLHttpRequest.prototype.send = function () {
      var x = this, t = Date.now();
      try { x.addEventListener('loadend', function () { req(x.__mcU, Date.now() - t, x.status); }); } catch (e) {}
      return XS.apply(this, arguments);
    };
  } catch (e) {}
  if (window.fetch) {
    var F = window.fetch;
    window.fetch = function (u) {
      var t = Date.now(), url = (u && u.url) ? u.url : u;
      return F.apply(this, arguments).then(function (r) { req(url, Date.now() - t, r.status); return r; },
        function (er) { if (!(er && er.name === 'AbortError')) req(url, Date.now() - t, 0); throw er; });
    };
  }

  // 뒤로가기 연달아 두 번
  window.addEventListener('pageshow', function (e) {
    var nv = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || null;
    if (!(e.persisted || (nv && nv.type === 'back_forward'))) return;
    try {
      var now = Date.now(), pv = SS && JSON.parse(SS.getItem('mc_ev_back') || 'null');
      if (pv && pv.t && now - pv.t < 4000) send('back2', { from: String(pv.p || ''), gap: now - pv.t });
      if (SS) SS.setItem('mc_ev_back', JSON.stringify({ t: now, p: P }));
    } catch (x) {}
  });

  // 주소 뒤(#)만 바뀌며 방문 기록이 늘어남
  var hl0 = history.length;
  window.addEventListener('hashchange', function () {
    if (history.length > hl0) send('hist_add', { n: history.length - hl0, h: String(location.hash || '').replace(/[^A-Za-z0-9_#.\-]/g, '').slice(0, 30) });
    hl0 = history.length;
  });

  // 화면 멈춤(크롬 계열만)
  try {
    if (window.PerformanceObserver && PerformanceObserver.supportedEntryTypes && PerformanceObserver.supportedEntryTypes.indexOf('longtask') >= 0) {
      var fz = 0;
      new PerformanceObserver(function (l) {
        l.getEntries().forEach(function (en) { if (en.duration >= 300 && fz < 5) { fz++; send('freeze', { ms: Math.round(en.duration) }); } });
      }).observe({ entryTypes: ['longtask'] });
    }
  } catch (e) {}

  // 스크립트 오류
  var je = 0;
  window.addEventListener('error', function (e) {
    if (je++ >= 5 || !e || !e.message) return;
    send('jserr', { msg: String(e.message).slice(0, 150), src: String(e.filename || '').split('?')[0].split('/').pop().slice(0, 60), line: e.lineno || 0 });
  });
  window.addEventListener('unhandledrejection', function (e) {
    if (je++ >= 5) return;
    var r = e && e.reason; send('jserr', { msg: String((r && r.message) || r || '').slice(0, 150), src: 'promise', line: 0 });
  });
})();
