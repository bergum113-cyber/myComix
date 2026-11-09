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
$_mcvv_sub = __('viewer_video_tab') . ($_mcvv_ext !== '' ? ' · ' . $_mcvv_ext : '');
?>
<style>
:root { --mcvv-h: 48px; }
@media (max-width: 767px) { :root { --mcvv-h: 52px; } }
body.mcvv > nav.navbar.fixed-top { display: none !important; } /* 예전 위 줄(지우지 않고 숨김) — [⚙] 로 펼침 */
body.mcvv.mcvv-old > nav.navbar.fixed-top { display: block !important; top: var(--mcvv-h) !important; z-index: 1059; box-shadow: 0 2px 6px rgba(0,0,0,.08); }
body.mcvv.mcvv-old > nav.navbar.fixed-top > table:not(.collapse), body.mcvv.mcvv-old > nav.navbar.fixed-top > .text-wrap-fix { display: none !important; } /* ★ fix57 로고 줄·제목은 막대와 겹쳐서 숨기고 설정만 — 전엔 ⚙ 를 두 번 눌러야 설정이 보였음 */
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
</style>
<div class="mcvv-bar" id="mcvvBar" role="toolbar" aria-label="<?php echo __h('mcv2_bar'); ?>">
  <a class="mcvv-ib" id="mcvvBack" data-mc-back href="<?php echo h($_mcvv_list); ?>" title="<?php echo __h('mcv2_list'); ?>" aria-label="<?php echo __h('mcv2_list'); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg></a>
  <div class="mcvv-title">
    <span class="mcvv-name" title="<?php echo h($_mcvv_name); ?>"><?php echo h($_mcvv_name); ?></span>
    <span class="mcvv-sub"><?php echo h($_mcvv_sub); ?></span>
  </div>
  <button type="button" class="mcvv-ib" id="mcvvMore" aria-expanded="false" title="<?php echo __h('mcv2_settings'); ?>" aria-label="<?php echo __h('mcv2_settings'); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></button>
</div>
<script>
(function () {
  var b = document.getElementById('mcvvMore'); if (!b) return;
  b.addEventListener('click', function () {
    var on = !document.body.classList.contains('mcvv-old');
    document.body.classList.toggle('mcvv-old', on);
    b.setAttribute('aria-expanded', on ? 'true' : 'false');
    // ★ fix57 설정(예전 위 줄의 .collapse — 밝기·책갈피)을 바로 펼침/접음(예전 ⚙ 의 sub_toggle 과 같은 동작, 열림 상태는 맞춰서)
    try {
      var cs = document.querySelectorAll('nav.navbar.fixed-top .collapse');
      if (window.jQuery) { var $c = window.jQuery(cs); if (on) $c.stop(true, true).fadeIn(); else $c.stop(true, true).hide(); }
      else cs.forEach(function (c) { c.style.display = on ? 'table' : 'none'; });
      var inf = document.getElementById('info'); if (inf) inf.value = '';
    } catch (e) {}
  });
})();
</script>
