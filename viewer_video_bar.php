<?php
/**
 * myComix 동영상 화면 위 막대 — viewer.php 가 동영상(video·video_archive)일 때 <body> 바로 뒤에서 include
 * ★ (2026-11-07 fix56) 펜닐 요청: 동영상 뷰어에도 만화책 뷰어처럼 뒤로가기 줄
 *  · [←] 들어온 곳으로(만화책 뷰어 ← 와 같음 — data-mc-back: 서재·즐겨찾기·검색 결과 등 들어온 화면과 위치, 없으면 이 파일의 폴더 목록)
 *  · 제목(파일 이름, 넘치면 … · 전체는 마우스를 올리면) + 아래 작은 글씨 '동영상 · 형식'
 *  · [⚙] 예전 위 줄(로고·설정: 밝기·책갈피 등)을 막대 아래에 펼침/접음 — 예전 줄은 지우지 않고 평소엔 숨김(만화책 뷰어와 같음)
 *  · 아래 줄(이전·목록·즐겨찾기·다음)은 그대로
 * 쓰는 변수(viewer.php): $title $getfile $link_dir $page $current_bidx $type
 */
if (!isset($type, $getfile)) return;
$_mcvv_list = './index.php?dir=' . encode_url($link_dir ?? '') . '&page=' . (int)($page ?? 0) . '&bidx=' . (int)($current_bidx ?? 0);
$_mcvv_name = basename((string)($title ?? $getfile));
$_mcvv_ext = strtoupper((string)pathinfo((string)$getfile, PATHINFO_EXTENSION));
$_mcvv_cv = ($type === 'video') && !empty($_viewer_is_admin); // ★ (2026-11-11 21:48 fix118) ⚙ 메뉴는 표지 지정(관리자)만 — 예전 밝기·책갈피는 동영상에선 함수가 없어 눌러도 오류만 났음
// ★ (2026-11-11 23:06 fix119, 펜닐 요청) 아래 줄(이전·목록·즐겨찾기·다음·형식 표시)은 만화 뷰어처럼 숨기고 즐겨찾기·이전/다음 영상은 ⚙ 메뉴로 — 주소·조건은 아래 줄과 같음
$_mcvv_v = ($type === 'video');
$_mcvv_pre = $pre ?? -1; $_mcvv_nx = $next ?? -1;
$_mcvv_prev = ($_mcvv_v && !(($now ?? '0') == '0' || $_mcvv_pre < 0 || !isset($totalfile[$_mcvv_pre]))) ? './viewer.php?file=' . encode_url($link_dir . '/' . $totalfile[$_mcvv_pre]) . '&bidx=' . (int)$current_bidx : '';
$_mcvv_next = ($_mcvv_v && isset($totalfile[$_mcvv_nx]) && count($totalfile) > $_mcvv_nx) ? './viewer.php?file=' . encode_url($link_dir . '/' . $totalfile[$_mcvv_nx]) . '&bidx=' . (int)$current_bidx : '';
$_mcvv_fav = $_mcvv_v && is_array($_favorites_arr ?? null) && mc_rec_find($_favorites_arr, $current_bidx, $getfile) !== null;
$_mcvv_sub = __('viewer_video_tab') . ($_mcvv_ext !== '' ? ' · ' . $_mcvv_ext : '');
?>
<style>
:root { --mcvv-h: 48px; }
@media (max-width: 767px) { :root { --mcvv-h: 52px; } }
body.mcvv > nav.navbar.fixed-top { display: none !important; } /* 예전 위 줄(지우지 않고 숨김) — [⚙] 로 펼침 */
body.mcvv.mcvv-old > nav.navbar.fixed-top { display: block !important; top: var(--mcvv-h) !important; z-index: 1059; box-shadow: 0 2px 6px rgba(0,0,0,.08); }
body.mcvv.mcvv-old > nav.navbar.fixed-top > table:not(.collapse), body.mcvv.mcvv-old > nav.navbar.fixed-top > .text-wrap-fix { display: none !important; } /* ★ fix57 로고 줄·제목은 막대와 겹쳐서 숨기고 설정만 — 전엔 ⚙ 를 두 번 눌러야 설정이 보였음 */
.mcvv-cvrow { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; padding: 2px 4px 6px; } /* ★ (2026-11-11 20:29 fix117) ⚙ 안 '지금 장면을 표지로' 단추 줄 */
.mcvv-bar a.mcvv-ib, .mcvv-bar a.mcvv-ib:visited, .mcvv-bar a.mcvv-ib:hover { color: inherit !important; } /* ★ fix57 어두운 모드에서 ← 가 링크 색(파랑)으로 보이던 것 */
.mcvv-bar { position: fixed; left: 0; right: 0; top: 0; min-height: var(--mcvv-h); z-index: 1060; display: flex; align-items: center; gap: 4px;
  padding: 4px 6px; padding-top: max(4px, env(safe-area-inset-top, 0px)); padding-left: max(6px, env(safe-area-inset-left, 0px)); padding-right: max(6px, env(safe-area-inset-right, 0px));
  font-family: system-ui, -apple-system, "Segoe UI", "Noto Sans KR", sans-serif; background: #fff; color: #202124; border-bottom: 1px solid #e3e5e8; box-sizing: border-box; }
html.dark-mode .mcvv-bar, body.dark-mode .mcvv-bar, [data-theme="dark"] .mcvv-bar { background: #18181b; color: #e8eaed; border-bottom-color: #2c2c31; }
.mcvv-bar * { box-sizing: border-box; }
.mcvv-ib { width: 40px; height: 40px; border-radius: 50%; padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; -webkit-tap-highlight-color: transparent; text-decoration: none; }
.mcvv-ib:hover, .mcvv-ib:focus-visible { background: rgba(127,127,127,.18); outline: none; color: inherit; text-decoration: none; }
.mcvv-ib[aria-expanded="true"] { background: rgba(127,127,127,.22); }
.mcvv-ib svg { display: block; }
.mcvv-title { flex: 1 1 auto; min-width: 0; display: flex; align-items: baseline; gap: 10px; padding: 2px 4px; }
.mcvv-name { font-size: 15px; font-weight: 700; line-height: 1.3; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mcvv-sub { font-size: 13px; opacity: .78; white-space: nowrap; flex: 0 0 auto; margin-left: auto; }
@media (max-width: 767px) {
  .mcvv-title { flex-direction: column; align-items: flex-start; gap: 0; line-height: 1.2; }
  .mcvv-name { font-size: 14.5px; max-width: 100%; }
  .mcvv-sub { font-size: 11.5px; margin-left: 0; }
}
body.mcvv { padding-top: var(--mcvv-h) !important; }
body.mcvv .video-wrapper { padding-top: 4px !important; margin-top: 0 !important; } /* 예전 위 줄 자리(80px)는 막대 높이로 대신 — 겹쳐 비던 것 */
/* ★ (2026-11-11 21:48 fix118, 펜닐 요청) ⚙ 메뉴를 만화 뷰어 메뉴와 같은 모양으로 — 예전 위 줄(밝기·책갈피·초록 단추)은 동영상에서 쓸 수 없는 칸뿐이라 표지 지정만 칩으로 */
.mcvv-pop { position: fixed; z-index: 1065; top: calc(var(--mcvv-h) + 4px); right: 8px; width: min(300px, calc(100vw - 16px)); box-sizing: border-box;
  background: #fff; color: #202124; border: 1px solid #e3e5e8; border-radius: 12px; padding: 6px; box-shadow: 0 10px 28px rgba(0,0,0,.18);
  font-family: system-ui, -apple-system, "Segoe UI", "Malgun Gothic", "Apple SD Gothic Neo", sans-serif; font-size: 14px; }
.mcvv-pop[hidden] { display: none; }
html.dark-mode .mcvv-pop, body.dark-mode .mcvv-pop, [data-theme="dark"] .mcvv-pop { background: #26262b; color: #e8eaed; border-color: #38383f; box-shadow: 0 10px 28px rgba(0,0,0,.5); }
.mcvv-hd { font-size: 11.5px; opacity: .6; padding: 6px 12px 2px; }
.mcvv-chips { display: flex; flex-wrap: wrap; gap: 6px; padding: 4px 8px 6px; }
.mcvv-chips > button { flex: 1 1 auto; min-height: 34px; padding: 0 13px; border: 1px solid rgba(127,127,127,.35); border-radius: 17px; background: transparent; color: inherit; font: inherit; font-size: 13.5px; white-space: nowrap; cursor: pointer; }
.mcvv-chips > button:hover, .mcvv-chips > button:focus-visible { background: rgba(127,127,127,.14); outline: none; }
.mcvv-toast { position: fixed; left: 50%; top: calc(var(--mcvv-h) + 12px); transform: translateX(-50%); z-index: 1070; background: rgba(32,33,36,.92); color: #fff; font-size: 13px;
  padding: 7px 14px; border-radius: 16px; max-width: min(520px, calc(100vw - 32px)); width: max-content; text-align: center; line-height: 1.45; word-break: keep-all; overflow-wrap: anywhere; pointer-events: none; opacity: 0; transition: opacity .2s;
  font-family: system-ui, -apple-system, "Segoe UI", "Malgun Gothic", sans-serif; }
.mcvv-toast.mcvv-show { opacity: 1; }
body.mcvv nav.viewer-bottom-nav { display: none !important; } /* ★ (2026-11-11 23:06 fix119) 예전 아래 줄(지우지 않고 숨김) — 만화 뷰어와 같게 */
.mcvv-mi { display: flex; align-items: center; gap: 12px; width: 100%; min-height: 42px; padding: 0 12px; border: 0; background: transparent; color: inherit; font: inherit; text-align: left; border-radius: 8px; cursor: pointer; text-decoration: none !important; box-sizing: border-box; }
.mcvv-mi:hover, .mcvv-mi:focus-visible { background: rgba(127,127,127,.14); outline: none; color: inherit; }
.mcvv-mi[aria-disabled="true"] { opacity: .38; pointer-events: none; }
.mcvv-mi svg { flex: 0 0 auto; opacity: .8; }
.mcvv-mi.mcvv-fav svg { color: #f59f00; opacity: 1; }
.mcvv-mi.mcvv-fav.mcvv-on svg { fill: #f59f00; }
.mcvv-hr { height: 1px; background: rgba(127,127,127,.22); margin: 4px; }
.mcvv-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; }
.mcvv-pair > .mcvv-mi { min-width: 0; white-space: nowrap; }
</style>
<div class="mcvv-bar" id="mcvvBar" role="toolbar" aria-label="<?php echo __h('mcv2_bar'); ?>">
  <a class="mcvv-ib" id="mcvvBack" data-mc-back href="<?php echo h($_mcvv_list); ?>" title="<?php echo __h('mcv2_list'); ?>" aria-label="<?php echo __h('mcv2_list'); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg></a>
  <div class="mcvv-title">
    <span class="mcvv-name" title="<?php echo h($_mcvv_name); ?>"><?php echo h($_mcvv_name); ?></span>
    <span class="mcvv-sub"><?php echo h($_mcvv_sub); ?></span>
  </div>
  <?php if ($_mcvv_v): /* fix119 즐겨찾기·이전/다음이 있어 동영상이면 모두에게 */ ?>
  <button type="button" class="mcvv-ib" id="mcvvMore" aria-expanded="false" title="<?php echo __h('mcv2_settings'); ?>" aria-label="<?php echo __h('mcv2_settings'); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></button>
  <?php endif; ?>
</div>
<?php if ($_mcvv_v): ?>
<div class="mcvv-pop" id="mcvvPop" role="menu" hidden>
  <button type="button" class="mcvv-mi mcvv-fav<?php echo $_mcvv_fav ? ' mcvv-on' : ''; ?>" id="mcvvFav" data-on="<?php echo __h('mcv2_fav_remove'); ?>" data-off="<?php echo __h('mcv2_fav_add'); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg><span><?php echo $_mcvv_fav ? __h('mcv2_fav_remove') : __h('mcv2_fav_add'); ?></span></button>
  <?php if ($_mcvv_cv): ?>
  <div class="mcvv-hr"></div>
  <div class="mcvv-hd"><?php echo __h('mcv2_cover'); ?><span id="mcvvCvAt"></span></div>
  <div class="mcvv-chips">
    <button type="button" data-cv="file" title="<?php echo __h('viewer_cover_frame_file'); ?>"><?php echo __h('mcv2_cover_file_s'); ?></button>
    <button type="button" data-cv="folder" title="<?php echo __h('viewer_cover_frame'); ?>"><?php echo __h('mcv2_cover_folder_s'); ?></button>
    <button type="button" data-cv="auto" title="<?php echo __h('mcv2_cover_auto'); ?>"><?php echo __h('mcv2_cover_auto_s'); ?></button>
  </div>
  <?php endif; ?>
  <div class="mcvv-hr"></div>
  <div class="mcvv-pair">
    <a class="mcvv-mi" data-go href="<?php echo $_mcvv_prev !== '' ? h($_mcvv_prev) : '#'; ?>"<?php echo $_mcvv_prev === '' ? ' aria-disabled="true" tabindex="-1"' : ''; ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg><?php echo __h('at_prev'); /* fix119 예전 아래 줄처럼 같은 폴더의 앞뒤 파일(영상만이 아님) */ ?></a>
    <a class="mcvv-mi" data-go href="<?php echo $_mcvv_next !== '' ? h($_mcvv_next) : '#'; ?>"<?php echo $_mcvv_next === '' ? ' aria-disabled="true" tabindex="-1"' : ''; ?>><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/></svg><?php echo __h('at_next'); ?></a>
  </div>
</div>
<?php endif; ?>
<div class="mcvv-toast" id="mcvvToast" role="status" aria-live="polite"></div>
<script>
(function () {
  var b = document.getElementById('mcvvMore'), pop = document.getElementById('mcvvPop'); if (!b || !pop) return;
  function setOpen(on) {
    pop.hidden = !on; b.setAttribute('aria-expanded', on ? 'true' : 'false');
    if (on) { // 표지로 쓸 장면 시각(만화 메뉴의 '· N쪽' 처럼)
      var v = document.getElementById('videoPlayer'), at = document.getElementById('mcvvCvAt'), t = v && v.currentTime > 0 ? Math.floor(v.currentTime) : -1;
      if (at) at.textContent = t >= 0 ? ' · ' + Math.floor(t / 60) + ':' + ('0' + (t % 60)).slice(-2) : '';
    }
  }
  b.addEventListener('click', function (e) { e.stopPropagation(); setOpen(pop.hidden); });
  document.addEventListener('click', function (e) { if (!pop.hidden && !pop.contains(e.target)) setOpen(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !pop.hidden) setOpen(false); });
  // 결과는 예전처럼 숨은 #info 칸에 적힘 → 바뀌면 알림으로(만화 메뉴와 같은 방식, 서버가 늦어도 30초까지)
  var toastT = 0;
  function toast(msg) { var el = document.getElementById('mcvvToast'); if (!el || !msg) return; el.textContent = String(msg); el.classList.add('mcvv-show'); clearTimeout(toastT); toastT = setTimeout(function () { el.classList.remove('mcvv-show'); }, String(msg).length > 30 ? 4000 : 2200); }
  function watchInfo(run) {
    var info = document.getElementById('info'), last = info ? info.value : '', n = 0;
    try { run(); } catch (e) { return; }
    if (!info) return;
    var iv = setInterval(function () { n++; var v = info.value; if (v && v !== last) { last = v; toast(v); } if (n > 300) clearInterval(iv); }, 100);
  }
  pop.addEventListener('click', function (e) {
    // ★ fix119 이전/다음 영상 — 예전 아래 줄처럼 지금 기록을 바꿔치기(뒤로 가기가 목록으로)
    var g = e.target.closest('[data-go]');
    if (g) { e.preventDefault(); if (g.getAttribute('aria-disabled') !== 'true') location.replace(g.getAttribute('href')); return; }
    // ★ fix119 즐겨찾기 — 만화 메뉴와 같은 요청·알림
    if (e.target.closest('#mcvvFav')) {
      setOpen(false);
      var fb = document.getElementById('mcvvFav'), x = new XMLHttpRequest();
      x.open('POST', 'bookmark.php?mode=toggle_favorite', true);
      x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      x.onreadystatechange = function () {
        if (x.readyState !== 4) return;
        try { var r = JSON.parse(x.responseText);
          if (r && r.success) { var on = !!r.is_favorite; fb.classList.toggle('mcvv-on', on); fb.querySelector('span').textContent = fb.getAttribute(on ? 'data-on' : 'data-off'); toast(r.message || fb.getAttribute(on ? 'data-on' : 'data-off')); }
          else if (r && r.error) toast(r.error);
        } catch (er) {}
      };
      x.send('file=' + encodeURIComponent(<?php echo json_encode((string)$getfile, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>) + '&bidx=' + <?php echo (int)$current_bidx; ?>);
      return;
    }
    var c = e.target.closest('[data-cv]'); if (!c) return;
    setOpen(false);
    var k = c.getAttribute('data-cv'), inf = document.getElementById('info'); if (inf) inf.value = '';
    if (k === 'auto') watchInfo(function () { mcCoverAuto(function (t) { var i = document.getElementById('info'); if (i) i.value = t; }); });
    else watchInfo(function () { mcCoverFromVideo(k === 'file' ? 'file' : undefined); });
  });
})();
</script>
