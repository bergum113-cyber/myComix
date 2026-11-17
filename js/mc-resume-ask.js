/*!
 * ★ (2026-11-15 16:15, 펜닐 요청 — fix169) 책 열 때 '이어서 볼까요?' 묻는 창 — 만화 뷰어(툰·북모드)·전자책(EPUB) 공용
 *   window.mcResumeAsk(o) — o: { title, name, label, page, tail, pct(0~100), startLbl, goLbl, hint, dark(true/false), onGo(), onStart() }
 *   · 글자는 모두 textContent 로 넣음(파일 이름에 < > 가 있어도 그대로 글자)
 *   · Enter = 이어보기 · Esc = 처음부터 · 창 바깥(어두운 곳)을 눌러도 닫지 않음(실수로 처음부터 되지 않게)
 *   · 사생활 가림막(privacy_shield, z-index 최대값)보다 아래(100000) — 다른 탭으로 넘어갈 때 책 이름이 가림막 위로 보이지 않게
 *   · 열려 있는 동안 키 입력은 창만 받음(뒤 화면 쪽 넘김·단축키가 같이 돌지 않게 — 창(window) 단계에서 먼저 받음)
 *   · 뒤 화면 스크롤·끌기 막음(창 위에서 휠·끌기) · 창 안 누르기도 창(window) 단계에서 먼저 받아 뒤 화면 처리로 안 넘김
 */
(function () {
  'use strict';
  if (window.mcResumeAsk) return;
  var openBox = null, onKey = null;

  // 키는 창(window) 잡기 단계에서 제일 먼저 — 이 파일은 뷰어 <head> 에서 읽혀 다른 키 처리보다 먼저 등록됨
  window.addEventListener('keydown', function (e) {
    if (!openBox || !onKey) return;
    if (e.key === 'Tab') { trapTab(e); return; }
    e.stopImmediatePropagation();
    if (e.key === 'Enter' || e.key === ' ' || e.key === 'Escape' || e.key === 'Esc') { e.preventDefault(); var _a = document.activeElement; onKey((e.key === 'Escape' || e.key === 'Esc') ? 'start' : ((_a && _a.className === 'mcrs-no' && openBox.contains(_a)) ? 'start' : 'go')); return; } // Enter·스페이스 = 초점 있는 단추(처음부터에 초점이면 처음부터), 그 밖엔 이어보기
    if (/^(Arrow|Page|Home|End)/.test(e.key)) e.preventDefault();
  }, true);

  // 누르기도 창(window) 잡기 단계에서 먼저 받아 처리하고 더 내려보내지 않음 — 북모드는 문서(document) 잡기 단계에서 빠른 두 번 누름을 막느라
  //   '누른 지 450ms 안의 클릭'을 지워 버려(viewer.php), 단추에 바로 단 처리로는 '처음부터 읽기'가 안 눌렸음(시험으로 확인)
  ['pointerdown', 'pointerup', 'mousedown', 'mouseup', 'touchstart', 'touchend', 'click'].forEach(function (t) {
    window.addEventListener(t, function (e) {
      if (!openBox || !onKey) return;
      var ov = openBox.parentNode;
      if (!ov || !e.target || !ov.contains(e.target)) return;
      e.stopImmediatePropagation();
      if (t !== 'click') return;
      var b = e.target.closest ? e.target.closest('button') : null;
      if (b && openBox.contains(b)) { e.preventDefault(); onKey(b.className === 'mcrs-no' ? 'start' : 'go'); }
    }, (t === 'touchstart' || t === 'touchend') ? { capture: true, passive: true } : true);
  });

  function trapTab(e) {
    var b = openBox.querySelectorAll('button');
    if (!b.length) return;
    var first = b[0], last = b[b.length - 1], a = document.activeElement;
    if (e.shiftKey && (a === first || !openBox.contains(a))) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && (a === last || !openBox.contains(a))) { e.preventDefault(); first.focus(); }
  }

  var CSS =
    '.mcrs-ov{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;touch-action:none;' +
      'font-family:system-ui,-apple-system,"Segoe UI","Malgun Gothic","Apple SD Gothic Neo",sans-serif}' +
    '.mcrs-ov *{box-sizing:border-box}' +
    '.mcrs-box{width:100%;max-width:340px;background:#fff;color:#202124;border-radius:14px;padding:20px 18px 16px;box-shadow:0 12px 32px rgba(0,0,0,.28);display:flex;flex-direction:column;gap:14px;text-align:left}' +
    '.mcrs-hd{display:flex;align-items:center;gap:10px}' +
    '.mcrs-ic{width:36px;height:36px;border-radius:50%;background:#e8f0fe;color:#1a73e8;display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}' +
    '.mcrs-tt{min-width:0;display:flex;flex-direction:column;gap:2px}' +
    '.mcrs-q{font-size:17px;font-weight:700;line-height:1.3}' +
    '.mcrs-nm{font-size:13px;color:#5f6368;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
    '.mcrs-row{display:flex;justify-content:space-between;gap:8px;font-size:13.5px;font-variant-numeric:tabular-nums}' +
    '.mcrs-pc{color:#5f6368}' +
    '.mcrs-bar{height:6px;border-radius:3px;background:#e8eaed;overflow:hidden;margin-top:6px}' +
    '.mcrs-bar i{display:block;height:6px;background:#1a73e8;border-radius:3px}' +
    '.mcrs-bt{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}' +
    '.mcrs-bt button{min-height:46px;border-radius:10px;font:inherit;font-size:14.5px;cursor:pointer;padding:4px 8px;line-height:1.25;word-break:keep-all}' +
    '.mcrs-no{border:1px solid #c4c7c5;background:#fff;color:#202124;font-weight:600}' +
    '.mcrs-go{border:0;background:#1a73e8;color:#fff;font-weight:700}' +
    '.mcrs-bt button:focus-visible{outline:2px solid #1a73e8;outline-offset:2px}' +
    '.mcrs-ht{font-size:12px;color:#5f6368;text-align:center}' +
    '.mcrs-dark .mcrs-box{background:#26262b;color:#e8eaed;border:1px solid #38383f;box-shadow:0 12px 32px rgba(0,0,0,.5)}' +
    '.mcrs-dark{background:rgba(0,0,0,.6)}' +
    '.mcrs-dark .mcrs-ic{background:rgba(138,180,248,.16);color:#8ab4f8}' +
    '.mcrs-dark .mcrs-nm,.mcrs-dark .mcrs-pc,.mcrs-dark .mcrs-ht{color:#b0b3b8}' +
    '.mcrs-dark .mcrs-bar{background:#3a3a40}' +
    '.mcrs-dark .mcrs-bar i{background:#8ab4f8}' +
    '.mcrs-dark .mcrs-no{border-color:#5f6368;background:transparent;color:#e8eaed}' +
    '.mcrs-dark .mcrs-go{background:#8ab4f8;color:#202124}' +
    '.mcrs-dark .mcrs-bt button:focus-visible{outline-color:#8ab4f8}';

  function el(tag, cls, txt) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (txt != null) e.textContent = String(txt);
    return e;
  }

  window.mcResumeAsk = function (o) {
    o = o || {};
    if (openBox) return;
    if (!document.getElementById('mcrsCss')) {
      var st = el('style'); st.id = 'mcrsCss'; st.textContent = CSS;
      (document.head || document.documentElement).appendChild(st);
    }
    var ov = el('div', 'mcrs-ov' + (o.dark ? ' mcrs-dark' : ''));
    var box = el('div', 'mcrs-box');
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-labelledby', 'mcrsQ');

    var hd = el('div', 'mcrs-hd'), ic = el('span', 'mcrs-ic'), tt = el('div', 'mcrs-tt');
    ic.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>'; // 고정 그림(바깥 값 안 씀)
    var q = el('span', 'mcrs-q', o.title || 'Continue?'); q.id = 'mcrsQ';
    tt.appendChild(q);
    if (o.name) { var nm = el('span', 'mcrs-nm', o.name); nm.title = String(o.name); tt.appendChild(nm); }
    hd.appendChild(ic); hd.appendChild(tt); box.appendChild(hd);

    var pct = Math.max(0, Math.min(100, Math.round(Number(o.pct) || 0)));
    var info = el('div'), row = el('div', 'mcrs-row'), lt = el('span');
    lt.appendChild(document.createTextNode((o.label || '') + ' '));
    if (o.page != null && o.page !== '') lt.appendChild(el('b', null, o.page));
    if (o.tail) lt.appendChild(document.createTextNode(' ' + o.tail));
    row.appendChild(lt);
    row.appendChild(el('span', 'mcrs-pc', pct + '%'));
    var bar = el('div', 'mcrs-bar'), bi = el('i'); bi.style.width = pct + '%'; bar.appendChild(bi);
    info.appendChild(row); info.appendChild(bar); box.appendChild(info);

    var bt = el('div', 'mcrs-bt');
    var no = el('button', 'mcrs-no', o.startLbl || 'Start over'); no.type = 'button';
    var go = el('button', 'mcrs-go', o.goLbl || 'Resume'); go.type = 'button';
    bt.appendChild(no); bt.appendChild(go); box.appendChild(bt);
    if (o.hint) box.appendChild(el('span', 'mcrs-ht', o.hint));
    ov.appendChild(box);

    // 뒤 화면이 같이 스크롤·끌리지 않게(창 위에서) — 단추 누르기는 그대로
    function stop(e) { if (e.cancelable) e.preventDefault(); }
    ov.addEventListener('wheel', stop, { passive: false });
    ov.addEventListener('touchmove', stop, { passive: false });

    var done = false;
    function close(which) {
      if (done) return; done = true;
      openBox = null; onKey = null;
      try { if (window.mcBackLog) window.mcBackLog('ask', { how: which === 'go' ? 'go' : 'start', n: pct }); } catch (x) {} // ★ (2026-11-17 13:08 fix187) 고른 답 기록만(src/perf/날짜.log — 이어서/처음부터, n = 창에 보인 %)
      try { ov.parentNode && ov.parentNode.removeChild(ov); } catch (e) {}
      try { (which === 'go' ? o.onGo : o.onStart) && (which === 'go' ? o.onGo() : o.onStart()); } catch (e) { try { console.warn('mcResumeAsk', e); } catch (x) {} }
    }
    // 단추 누르기는 위 창(window) 단계 처리가 받음 · 바깥(어두운 곳)을 눌러도 닫지 않음 · 뒤 화면으로 안 넘어감

    openBox = box; onKey = close;
    document.body.appendChild(ov);
    try { go.focus({ preventScroll: true }); } catch (e) { try { go.focus(); } catch (x) {} }
  };
})();
