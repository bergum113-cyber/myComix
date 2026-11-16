/*!
 * ★ (2026-11-16 09:31, 펜닐 요청 — fix181) 전자책·텍스트 뷰어 설정 중 '브라우저 저장소(localStorage)에 두는 것'을 계정에 맞춤
 *   뷰어 <head> 에서 다른 스크립트보다 먼저 읽힘 — window.MC_LSYNC = { u: 계정 지문, k: [맞출 키], s: {서버 값} } (function.php mc_lprefs_head)
 *   · 열 때: 키마다 — 이 기기에서 바꾼 값(지난번 맞춘 값과 다름)이면 서버에 올림, 아니면 서버 값을 이 기기에 넣음(다른 기기에서 바꾼 것)
 *            서버에 없는 키는 이 기기 값을 올림(처음 한 번 — 먼저 연 기기의 값이 계정 값이 됨)
 *   · 보는 동안: 1.5초마다·화면을 떠날 때 바뀐 키만 보냄(vprefs.php) — 뷰어의 설정 코드는 그대로(이 파일이 저장소 값을 살펴봄)
 *   · 지난번 맞춘 값은 저장소 'mc_lsync' 에 { u, b:{키:값} } — 다른 계정이면 처음부터(서버 값을 받음)
 *   · 보내기가 성공해야 '맞춤'으로 적음 — 실패하면 다음에 열 때 다시 보냄
 *   · 화면 크기마다 다른 설정(글자 크기·여백·한 쪽/두 쪽)은 목록에 없어 기기마다 그대로
 */
(function () {
  'use strict';
  var C = window.MC_LSYNC;
  if (!C || !C.k || !C.k.length) return;
  var LS;
  try { LS = window.localStorage; LS.getItem('mc_lsync'); } catch (e) { return; } // 저장소를 못 쓰면(사생활 보호 창 등) 아무것도 안 함
  var META = 'mc_lsync', has = Object.prototype.hasOwnProperty;
  var m = null;
  try { m = JSON.parse(LS.getItem(META) || 'null'); } catch (e) { m = null; }
  if (!m || typeof m !== 'object' || m.u !== C.u || !m.b || typeof m.b !== 'object') m = { u: C.u, b: {} };
  var S = (C.s && typeof C.s === 'object') ? C.s : {};
  var pend = {};
  function saveMeta() { try { LS.setItem(META, JSON.stringify(m)); } catch (e) {} }
  // ★ (2026-11-16 11:16 fix184) '지움'이 곧 값인 키(C.z — 텍스트 자동 2단) — 저장소에 없으면 빈 값('')으로 보고, 빈 값을 받으면 지움
  var Z = {}; (C.z || []).forEach(function (k) { Z[k] = 1; });
  function get(k) { try { var v = LS.getItem(k); return (v === null && Z[k]) ? '' : v; } catch (e) { return null; } }
  function put(k, v) { if (Z[k] && v === '') LS.removeItem(k); else LS.setItem(k, v); }
  function empty(k, v) { return v === null || (Z[k] && v === ''); }
  function send(k, v) {
    if (pend[k] === v) return;
    pend[k] = v;
    var fd = new FormData(); fd.append('k', k); fd.append('v', v);
    try {
      fetch('vprefs.php', { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (d && d.ok) { m.b[k] = v; saveMeta(); if (pend[k] === v) delete pend[k]; }
        })
        .catch(function () {}); // 실패 — pend 에 남겨 이 화면에선 다시 안 보냄(다음에 열 때 다시)
    } catch (e) {}
  }

  // ★ (2026-11-16 10:35 fix181 재검토) 열 때 이 기기·서버 둘 다 없던 키 — 처음 3초 안에 생긴 값은 화면이 넣은 기본값(사람이 고른 게 아님)이라 올리지 않고 이 기기 기준으로만 적음
  //   [왜] 텍스트 뷰어는 열 때마다 테마를 저장소에 씀 — 텍스트를 처음 연 폰의 기본 'light' 가 계정 값이 되어 PC 에서 직접 고른 'dark' 를 덮었음(시험으로 확인)
  var t0 = Date.now(), quiet = {};
  // 열 때 맞추기
  C.k.forEach(function (k) {
    var cur = get(k);
    var base = has.call(m.b, k) ? m.b[k] : undefined;
    if (!has.call(S, k)) { // 서버에 없음 → 이 기기 값(지난번에 기본값으로 적어 둔 그대로면 올리지 않음)
      if (empty(k, cur)) quiet[k] = 1;
      else if (base === undefined || cur !== base) send(k, cur);
      return;
    }
    var sv = String(S[k]);
    if (cur === sv) { m.b[k] = sv; return; }
    if (cur !== null && base !== undefined && cur !== base) { send(k, cur); return; } // 지난번 맞춘 뒤 이 기기에서 바꿈
    try { put(k, sv); m.b[k] = sv; } catch (e) {} // 다른 기기에서 바꿈(또는 이 기기 첫 맞춤) → 서버 값
  });
  saveMeta();

  // 보는 동안 바뀐 것
  function scan() {
    C.k.forEach(function (k) {
      var cur = get(k);
      if (quiet[k]) {
        if (empty(k, cur)) { if (Date.now() - t0 >= 3000) { delete quiet[k]; if (cur !== null) { m.b[k] = cur; saveMeta(); } } return; } // (fix184) 빈 값 그대로면 기준으로만 적음
        delete quiet[k];
        if (Date.now() - t0 < 3000) { m.b[k] = cur; saveMeta(); return; } // 화면이 넣은 기본값 — 적기만
      }
      if (cur !== null && cur !== (has.call(m.b, k) ? m.b[k] : null)) send(k, cur);
    });
  }
  setInterval(scan, 1500);
  window.addEventListener('pagehide', scan);
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') scan(); });
})();
