/* ★ (2026-11-18 08:16, 펜닐 요청 — 시안 C · fix192) 가로로 미는 줄: 스크롤 바 대신 아래 점 표시
 *   - <script src="js/mc-hdots.js" data-sel="CSS 선택자"> — 선택자에 맞는 줄마다 아래에 점(화면 수만큼, 지금 위치는 길게)
 *   - 넘치지 않는 줄(PC 에서 여러 줄로 감기는 등)은 점을 숨기고 스크롤 바 숨김도 하지 않음 → 예전 그대로
 *   - 점을 누르면 그 화면으로 이동. 스크립트가 안 돌면 스크롤 바가 그대로 보임
 *   - fix223: PC 는 마우스로 줄을 끌어 좌우 이동 */
(function () {
  var me = document.currentScript, sel = me && me.getAttribute('data-sel');
  if (!sel) return;
  var st = document.createElement('style');
  st.textContent = '.mc-hd-on{scrollbar-width:none}.mc-hd-on::-webkit-scrollbar{display:none}'
    + '.mc-hd{display:flex;gap:6px;justify-content:center;align-items:center;margin:6px 0 0;min-height:10px}'
    + '.mc-hd[hidden]{display:none}'
    + '.mc-hd i{width:6px;height:6px;border-radius:3px;background:rgba(127,127,127,.35);cursor:pointer;transition:width .2s,background .2s}'
    + '.mc-hd i.on{width:16px;background:rgba(127,127,127,.85)}'
    + '.mc-hd-drag,.mc-hd-drag *{cursor:grabbing!important;user-select:none!important}';
  document.head.appendChild(st);
  function setup(row) {
    if (row.__mcHd) return; row.__mcHd = 1;
    var bar = document.createElement('div'); bar.className = 'mc-hd'; bar.hidden = true;
    row.parentNode.insertBefore(bar, row.nextSibling);
    var n = 0;
    function upd() {
      var cw = row.clientWidth, max = row.scrollWidth - cw;
      if (cw < 1 || max <= 2) { bar.hidden = true; row.classList.remove('mc-hd-on'); return; }
      row.classList.add('mc-hd-on'); bar.hidden = false;
      var k = Math.ceil(row.scrollWidth / cw - 0.05);
      if (k < 2) k = 2;
      if (k !== n) { n = k; bar.innerHTML = new Array(n + 1).join('<i></i>'); }
      var c = Math.round(row.scrollLeft / max * (n - 1));
      for (var j = 0; j < n; j++) bar.children[j].className = j === c ? 'on' : '';
    }
    bar.addEventListener('click', function (e) {
      var j = Array.prototype.indexOf.call(bar.children, e.target); if (j < 0) return;
      var max = row.scrollWidth - row.clientWidth;
      try { row.scrollTo({ left: n > 1 ? max * j / (n - 1) : 0, behavior: 'smooth' }); } catch (x) { row.scrollLeft = n > 1 ? max * j / (n - 1) : 0; }
    });
    var t = 0;
    row.addEventListener('scroll', function () { if (!t) t = requestAnimationFrame(function () { t = 0; upd(); }); }, { passive: true });
    window.addEventListener('resize', upd);
    window.addEventListener('load', upd);
    upd();
    // ★ (2026-11-18 23:32, 펜닐 요청 — fix223) PC 마우스로 끌어 좌우 이동 — 스크롤 바를 숨겨(fix192) 마우스로는 옆으로 갈 수 없었음.
    //   마우스만(손가락·펜은 원래대로 밀기) · 5px 넘게 끌면 이동하고 그때 놓은 클릭은 열지 않음 · 그냥 누르면 예전처럼 열림
    var dn = null, moved = false;
    row.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse' || e.button !== 0 || !row.classList.contains('mc-hd-on')) return;
      dn = { x: e.clientX, s: row.scrollLeft }; moved = false;
    });
    window.addEventListener('pointermove', function (e) {
      if (!dn) return;
      var dx = e.clientX - dn.x;
      if (!moved && Math.abs(dx) > 5) { moved = true; row.classList.add('mc-hd-drag'); }
      if (moved) { row.scrollLeft = dn.s - dx; e.preventDefault(); }
    });
    function up() { if (!dn) return; dn = null; if (moved) setTimeout(function () { moved = false; row.classList.remove('mc-hd-drag'); }, 0); }
    window.addEventListener('pointerup', up);
    window.addEventListener('pointercancel', up);
    window.addEventListener('click', function (e) { if (moved && row.contains(e.target)) { e.preventDefault(); e.stopPropagation(); } }, true);
    row.addEventListener('dragstart', function (e) { if (row.classList.contains('mc-hd-on')) e.preventDefault(); }); // 표지 그림·링크를 끌면 브라우저 끌어 놓기가 시작돼 옆 이동이 멈춤
  }
  function init() { var l = document.querySelectorAll(sel); for (var i = 0; i < l.length; i++) setup(l[i]); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
