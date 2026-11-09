<?php
/**
 * 시리즈 편집 창(관리자) — series.php 에서 include. 단독 실행 차단.
 * ★ (2026-10-29) 신규
 * 필요한 변수: $db, $series, $terms, $vols, $csrf_token, $bidx
 */
if (!isset($series) || !isset($db) || !function_exists('mc_lib_is_admin') || !mc_lib_is_admin()) return;

$_names = function ($list) { return implode(', ', array_map(function ($t) { return $t['name']; }, $list)); };
// ★ (2026-10-29) 자동완성 목록(모든 작품 이름·작가·장르·태그)은 편집 창을 처음 열 때 library_api.php(suggest)로 받아옴
//    — 이전엔 상세 화면마다 전부 넣어서 작품이 많으면 관리자 화면이 수 MB 로 커져 느렸음
?>
<style>
  .lib-modal { position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10050; display:none; align-items:flex-start; justify-content:center; overflow:auto; padding:30px 10px; }
  .lib-modal.open { display:flex; }
  .lib-modal-box { background:#fff; border-radius:8px; width:100%; max-width:720px; padding:16px 18px; box-shadow:0 10px 30px rgba(0,0,0,.35); }
  .lib-modal-box h3 { font-size:1.15rem; margin:0 0 12px; }
  .lib-modal-box label { font-size:.85rem; color:#666; margin:8px 0 2px; display:block; }
  .lib-modal-box .help { font-size:.75rem; color:#999; }
  .lib-vtable { width:100%; font-size:.85rem; }
  .lib-vtable td { padding:3px 4px; vertical-align:middle; border-top:1px solid #eee; }
  .lib-vtable input[type=text] { width:70px; }
  .lib-modal-foot { display:flex; gap:6px; justify-content:flex-end; margin-top:14px; flex-wrap:wrap; }
  .lib-msg { font-size:.85rem; margin-right:auto; align-self:center; }
  .dark-mode .lib-modal-box, [data-theme="dark"] .lib-modal-box { background:#1e1e1e; color:#e0e0e0; }
  .dark-mode .lib-modal-box .form-control, [data-theme="dark"] .lib-modal-box .form-control { background:#2a2a2a; color:#eee; border-color:#444; }
  /* ★ (2026-11-06 07:20) 손으로 묶기 — 직접 묶음 표시 · 책 추가 결과 */
  #libAddBar[hidden], #libAddRes[hidden] { display:none !important; } /* d-flex(!important) 가 hidden 을 이기지 않게 */
  .lib-man { display:inline-block; font-size:.7rem; line-height:1.5; padding:0 6px; border-radius:999px; background:#fff3cd; color:#7a5b00; white-space:nowrap; vertical-align:1px; }
  .lib-addres { max-height:260px; overflow:auto; margin-top:6px; border:1px solid #e3e6ea; border-radius:6px; font-size:.85rem; }
  .lib-addres .lib-ag { padding:5px 8px; background:#f6f7f9; border-top:1px solid #e3e6ea; display:flex; align-items:center; gap:6px; font-weight:600; }
  .lib-addres .lib-ag:first-child { border-top:0; }
  .lib-addres label.lib-ai { display:flex; align-items:flex-start; gap:6px; margin:0; padding:4px 8px 4px 22px; color:inherit; font-size:.85rem; cursor:pointer; word-break:break-all; }
  .lib-addres .lib-ai small { color:#888; }
  .lib-addres .lib-anone { padding:8px; color:#888; }
  .dark-mode .lib-man, [data-theme="dark"] .lib-man { background:#4a3b0a; color:#ffe08a; }
  .dark-mode .lib-addres, [data-theme="dark"] .lib-addres { border-color:#444; }
  .dark-mode .lib-addres .lib-ag, [data-theme="dark"] .lib-addres .lib-ag { background:#262626; border-color:#444; }
  /* ★ (2026-11-08 fix60) 도서 목록 — 권 번호로 묶어 보기(중복 포함) · 바꾼 것 색 표시 · 아래 [저장]에 한 번에 */
  .lib-modal-box { max-width:860px; }
  .lib-vl-head { display:flex; align-items:baseline; flex-wrap:wrap; gap:4px 8px; margin:16px 0 6px; }
  .lib-vl-head strong { font-size:.95rem; }
  .lib-vl-tools { display:flex; align-items:center; flex-wrap:wrap; gap:6px; padding:6px 8px; background:#f6f7f9; border:1px solid #e3e6ea; border-radius:8px; }
  .lib-vl-selcnt { font-size:.85rem; font-weight:700; color:#1d2026; }
  .lib-vl-selcnt.is-none { font-weight:400; color:#5f6570; font-size:.8rem; }
  .lib-vl-sp { flex:1 1 auto; }
  .lib-vl-move { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
  .lib-vl-move[hidden], .lib-vl-addbar[hidden] { display:none !important; }
  .lib-vl-move input { flex:1 1 220px; min-width:0; }
  .lib-vl { margin-top:8px; max-height:52vh; overflow:auto; border:1px solid #e3e6ea; border-radius:10px; }
  .lib-vl-g + .lib-vl-g { border-top:1px solid #e3e6ea; }
  .lib-vl-gh { display:flex; align-items:center; gap:8px; padding:7px 10px; background:#f6f7f9; position:sticky; top:0; z-index:1; }
  .lib-vl-gh strong { font-size:.95rem; }
  .lib-vl-r { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:6px 10px; padding:8px 10px; border-top:1px solid #eef0f3; }
  .lib-vl-cb { width:18px; height:18px; margin:0; }
  .lib-vl-main { min-width:0; }
  .lib-vl-fn { font-size:.85rem; line-height:1.35; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; word-break:break-all; }
  .lib-vl-meta { display:flex; flex-wrap:wrap; align-items:center; gap:4px 6px; margin-top:3px; font-size:.75rem; color:#5f6570; }
  .lib-vl-folder { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; }
  .lib-vl-ctl { display:flex; align-items:center; gap:6px; }
  .lib-vl-place { width:auto; min-width:150px; max-width:240px; height:34px; }
  .lib-vl-undo { width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; }
  .lib-vl-custom { grid-column:2 / -1; display:flex; flex-wrap:wrap; gap:8px; }
  .lib-modal-box .lib-vl-custom label { display:flex; align-items:center; gap:6px; margin:0; font-size:.8rem; color:#5f6570; }
  .lib-vl-custom .lib-vl-no { width:90px; }
  .lib-vl-custom .lib-vl-label { width:180px; }
  .lib-vl-b { display:inline-flex; align-items:center; height:20px; padding:0 8px; border-radius:999px; font-size:.72rem; font-weight:700; white-space:nowrap; }
  .lib-vl-b.is-dup { background:#eceef2; color:#3c4049; }
  .lib-vl-b.is-man { background:#fff3cd; color:#7a5b00; }
  .lib-vl-b.is-add { background:#e6f4ea; color:#137333; }
  .lib-vl-b.is-del { background:#fdecea; color:#b3261e; }
  .lib-vl-b.is-mov { background:#e8f0fe; color:#1a56c4; }
  .lib-vl-b.is-auto { background:#f3e8fd; color:#6b2fb3; }
  .lib-vl-b.is-no { background:#fff4e5; color:#8a4b00; }
  .lib-vl-r.is-add { background:#f3faf5; }
  .lib-vl-r.is-del { background:#fdf3f2; }
  .lib-vl-r.is-del .lib-vl-fn { text-decoration:line-through; color:#7a6a68; }
  .lib-vl-r.is-mov { background:#f3f7fe; }
  .lib-vl-r.is-auto { background:#f9f4fe; }
  .lib-vl-r.is-no { background:#fffaf2; }
  .lib-vl-addq { display:flex; gap:6px; }
  .lib-vl-addq input { flex:1 1 auto; min-width:0; max-width:460px; }
  /* ★ fix62 넣는 방식은 한 줄(고른 방식의 설명만 아래 한 줄) — 찾은 목록·[도서 목록에 넣기]가 한 화면에 들어오게 */
  .lib-vl-addbar { margin-top:8px; display:flex; align-items:flex-end; gap:8px 12px; }
  .lib-vl-mode { margin:0; padding:4px 10px 6px; border:1px solid #e3e6ea; border-radius:8px; flex:1 1 auto; min-width:0; }
  .lib-vl-mode legend { width:auto; font-size:.8rem; font-weight:700; margin:0; padding:0 4px; }
  .lib-vl-mode-o { display:flex; flex-wrap:wrap; gap:2px 16px; }
  .lib-modal-box .lib-vl-mode label { display:inline-flex; align-items:center; gap:6px; margin:2px 0; color:inherit; font-size:.85rem; cursor:pointer; }
  .lib-vl-mode small { display:none; }
  .lib-vl-mode .lib-vl-pick select { width:auto; max-width:12rem; display:inline-block; height:calc(1.5em + .4rem + 2px); padding:0 1.6rem 0 .5rem; }
  .lib-vl-mode-d { color:#5f6570; font-size:.75rem; line-height:1.35; }
  .lib-vl-addgo { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex:0 0 auto; }
  .lib-addres, .lib-vl { overscroll-behavior:contain; } /* 목록 끝에서 굴려도 창 전체가 따라 내려가지 않게 */
  .lib-vl-hint { font-size:.78rem; color:#5f6570; line-height:1.4; margin:4px 2px 0; }
  .lib-vl-hint[hidden] { display:none; }
  @media (max-width: 576px) { .lib-vl-addbar { flex-direction:column; align-items:stretch; } }
  .lib-vl-sum { display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
  .lib-modal-foot { align-items:center; }
  /* 아래 [저장]·[취소] 가 늘 보이게(창 안에서 내려도) */
  .lib-modal-box { position:relative; }
  .lib-modal-foot { position:sticky; bottom:0; background:#fff; padding:10px 0 4px; margin-bottom:-4px; border-top:1px solid #eef0f3; z-index:2; }
  /* 폰(세로·가로): 창을 화면 가득 — 목록이 좁아지지 않게 */
  @media (max-width: 576px), (max-height: 480px) {
    .lib-modal { padding:0; }
    .lib-modal-box { max-width:none; min-height:100%; border-radius:0; padding:12px 12px 0; }
    .lib-vl { max-height:none; }
    .lib-vl-gh { position:static; }
    .lib-modal-foot { bottom:0; padding:10px 0 calc(10px + env(safe-area-inset-bottom, 0px)); }
  }
  @media (max-width: 576px) {
    .lib-vl-r { grid-template-columns:auto minmax(0,1fr); }
    .lib-vl-ctl { grid-column:2 / -1; }
    .lib-vl-place { flex:1 1 auto; max-width:none; min-width:0; }
    .lib-vl-custom { grid-column:2 / -1; }
    .lib-vl-custom .lib-vl-label { width:100%; }
  }
  .dark-mode .lib-vl-tools, [data-theme="dark"] .lib-vl-tools, .dark-mode .lib-vl-gh, [data-theme="dark"] .lib-vl-gh { background:#262626; border-color:#444; }
  .dark-mode .lib-vl, [data-theme="dark"] .lib-vl, .dark-mode .lib-vl-mode, [data-theme="dark"] .lib-vl-mode { border-color:#444; }
  .dark-mode .lib-vl-r, [data-theme="dark"] .lib-vl-r, .dark-mode .lib-vl-g + .lib-vl-g, [data-theme="dark"] .lib-vl-g + .lib-vl-g { border-color:#333; }
  .dark-mode .lib-vl-selcnt, [data-theme="dark"] .lib-vl-selcnt { color:#e0e0e0; }
  .dark-mode .lib-vl-mode-d, [data-theme="dark"] .lib-vl-mode-d, .dark-mode .lib-vl-hint, [data-theme="dark"] .lib-vl-hint, .dark-mode .lib-vl-meta, [data-theme="dark"] .lib-vl-meta, .dark-mode .lib-vl-mode small, [data-theme="dark"] .lib-vl-mode small, .dark-mode .lib-modal-box .lib-vl-custom label, [data-theme="dark"] .lib-modal-box .lib-vl-custom label { color:#a3a9b4; }
  .dark-mode .lib-vl-r.is-add, [data-theme="dark"] .lib-vl-r.is-add { background:#16261b; }
  .dark-mode .lib-vl-r.is-del, [data-theme="dark"] .lib-vl-r.is-del { background:#2c1a19; }
  .dark-mode .lib-vl-r.is-del .lib-vl-fn, [data-theme="dark"] .lib-vl-r.is-del .lib-vl-fn { color:#b3a3a1; }
  .dark-mode .lib-vl-r.is-mov, [data-theme="dark"] .lib-vl-r.is-mov { background:#18213a; }
  .dark-mode .lib-vl-r.is-auto, [data-theme="dark"] .lib-vl-r.is-auto { background:#241a33; }
  .dark-mode .lib-vl-r.is-no, [data-theme="dark"] .lib-vl-r.is-no { background:#2a2214; }
  .dark-mode .lib-vl-b.is-dup, [data-theme="dark"] .lib-vl-b.is-dup { background:#3a3d44; color:#e8eaed; }
  .dark-mode .lib-modal-foot, [data-theme="dark"] .lib-modal-foot { background:#1e1e1e; border-color:#333; }
  .dark-mode .lib-vl-undo, [data-theme="dark"] .lib-vl-undo { background:#3a3d44; border-color:#555; color:#e8eaed; }
  /* ★ (2026-11-08 fix64) 창 틀 — [작품 정보 | 도서 목록] 탭, 가운데(본문)만 스크롤, 위 제목·탭과 아래 [저장] 줄은 늘 보임
     · 도서 목록: 고른 수·빼기·다른 시리즈로·자동으로 줄이 본문 맨 위에 붙어 있음(책을 고르러 내려가도 단추가 보임) */
  /* ★ fix65 창 높이는 내용만큼(작품 정보는 작게, 도서 목록은 책 수만큼) — 화면(최대 960px)을 넘으면 그때부터 본문만 스크롤.
     위쪽 자리를 고정(flex-start)해 탭을 바꿔도 창이 위아래로 튀지 않음 */
  .lib-modal { align-items:flex-start; overflow:hidden; padding:24px 10px; }
  .lib-modal-box { display:flex; flex-direction:column; padding:0; height:auto; max-height:min(calc(100vh - 48px), 960px); max-height:min(calc(100dvh - 48px), 960px); overflow:hidden; }
  .lib-modal-head { flex:0 0 auto; padding:14px 18px 0; border-bottom:1px solid #e3e6ea; }
  .lib-modal-head h3 { margin:0 0 10px; }
  .lib-tabs { display:flex; gap:2px; margin-bottom:-1px; }
  .lib-tab { appearance:none; border:1px solid transparent; border-bottom:0; background:none; padding:7px 14px; font-size:.9rem; color:#5f6570; border-radius:8px 8px 0 0; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
  .lib-tab[aria-selected="true"] { background:#fff; color:#1d2026; font-weight:700; border-color:#e3e6ea; }
  #libEditForm input.form-control, #libEditForm textarea.form-control { width:100%; max-width:560px; } /* ★ fix65 작품 정보 칸을 창 폭에 맞게(좁은 칸 옆이 비어 보이던 것) */
  .lib-tab:focus { outline:none; box-shadow:none; } /* 마우스로 누를 때 굵은 검은 테두리(브라우저 기본)가 생기던 것 */
  .lib-tab:focus-visible { outline:2px solid #1a73e8; outline-offset:-2px; }
  .lib-tab-n { font-size:.75rem; font-weight:600; padding:0 6px; border-radius:999px; background:#eef0f3; color:#3c4043; }
  .lib-tab-dot { width:7px; height:7px; border-radius:50%; background:#e8710a; display:none; }
  .lib-tab.is-dirty .lib-tab-dot { display:inline-block; }
  .lib-modal-body { flex:0 1 auto; min-height:0; overflow:auto; overscroll-behavior:contain; padding:12px 18px 14px; }
  .lib-pane[hidden] { display:none !important; }
  .lib-modal-foot { flex:0 0 auto; position:static; margin:0; padding:10px 18px; background:#fff; border-top:1px solid #e3e6ea; }
  #libPaneBooks > .lib-vl-head:first-child { margin-top:0; }
  .lib-vl-stick { position:sticky; top:-12px; z-index:3; background:#fff; margin:0 -4px; padding:12px 4px 6px; } /* top = 본문 위 여백만큼 위로(붙었을 때 틈 없게) */
  .lib-vl-tl { flex:1 1 200px; min-width:0; display:flex; flex-direction:column; gap:1px; }
  .lib-vl-tools .lib-vl-hint { margin:0; font-size:.75rem; line-height:1.35; }
  .lib-vl { max-height:none; overflow:visible; }
  .lib-vl-gh { position:static; }
  .lib-vl-gh .lib-vl-sp { flex:1 1 auto; }
  .lib-vl-arr { display:inline-flex; gap:2px; }
  .lib-vl-arr button { width:28px; height:26px; padding:0; display:inline-flex; align-items:center; justify-content:center; border:1px solid #d5d9df; background:#fff; color:#3c4043; border-radius:6px; line-height:1; }
  .lib-vl-arr button:disabled { opacity:.35; cursor:default; }
  .lib-vl-arr button:not(:disabled):hover { background:#eef0f3; }
  .lib-vl-b.is-rep { background:#e6f4ea; color:#137333; }
  .lib-vl-b.is-ord { background:#fde7d1; color:#8a4b00; }
  @media (max-width: 576px), (max-height: 480px) {
    .lib-modal { padding:0; }
    .lib-modal-box { height:100vh; height:100dvh; max-height:none; max-width:none; border-radius:0; padding:0; min-height:0; } /* 폰은 지금처럼 화면 가득(아래 [저장] 줄이 화면 맨 아래) */
    .lib-modal-body { flex:1 1 auto; }
    .lib-modal-head { padding:10px 12px 0; }
    .lib-modal-head h3 { margin-bottom:6px; font-size:1.05rem; }
    .lib-modal-body { padding:10px 12px 12px; }
    .lib-vl-stick { top:-10px; padding-top:10px; }
    .lib-modal-foot { padding:8px 12px calc(8px + env(safe-area-inset-bottom, 0px)); }
    .lib-vl-arr button { width:32px; height:30px; }
  }
  @media (max-width: 576px) { .lib-vl-tl { flex-basis:100%; } } /* 폰: 안내는 한 줄 전체, 단추 셋은 그 아래 한 줄 */
  @media (max-height: 480px) { .lib-modal-head h3 { display:none; } .lib-modal-head { padding-top:6px; }
    .lib-vl-mode legend, .lib-vl-mode-d { display:none; } .lib-vl-mode { padding:4px 8px; } } /* 폰 가로: 넣는 방식은 두 단추만(설명은 단추에 올리면 보임) — 찾은 목록 자리 */ /* 폰 가로: 제목 줄을 빼서 목록 자리를 더 */
  .dark-mode .lib-modal-head, [data-theme="dark"] .lib-modal-head { border-color:#333; }
  .dark-mode .lib-tab[aria-selected="true"], [data-theme="dark"] .lib-tab[aria-selected="true"] { background:#1e1e1e; color:#e8eaed; border-color:#333; }
  .dark-mode .lib-tab, [data-theme="dark"] .lib-tab { color:#a3a9b4; }
  .dark-mode .lib-tab-n, [data-theme="dark"] .lib-tab-n { background:#3a3d44; color:#e8eaed; }
  .dark-mode .lib-vl-stick, [data-theme="dark"] .lib-vl-stick { background:#1e1e1e; }
  .dark-mode .lib-vl-arr button, [data-theme="dark"] .lib-vl-arr button { background:#2a2a2a; border-color:#555; color:#e8eaed; }
  .dark-mode .lib-vl-arr button:not(:disabled):hover, [data-theme="dark"] .lib-vl-arr button:not(:disabled):hover { background:#3a3d44; }
  .dark-mode .lib-vl-b.is-rep, [data-theme="dark"] .lib-vl-b.is-rep { background:#1f3a28; color:#81c995; }
  .dark-mode .lib-vl-b.is-ord, [data-theme="dark"] .lib-vl-b.is-ord { background:#3d2a12; color:#fdc68a; }
</style>
<datalist id="libSuggAuthor"></datalist>
<datalist id="libSuggGroup"></datalist>
<datalist id="libSuggGenre"></datalist>
<datalist id="libSuggTag"></datalist>
<datalist id="libSuggSeries"></datalist>

<div class="lib-modal" id="libEditModal" role="dialog" aria-modal="true" aria-labelledby="libEditTitle">
  <div class="lib-modal-box">
    <div class="lib-modal-head">
    <h3 id="libEditTitle">✏️ <?php echo __h('lib_edit'); ?></h3>
    <?php /* ★ fix64 탭 — 한 창에 다 있으면 위아래로 한참 내려야 했음 */ ?>
    <div class="lib-tabs" role="tablist" aria-label="<?php echo __h('lib_edit'); ?>">
      <button type="button" class="lib-tab" role="tab" id="libTabInfo" aria-controls="libPaneInfo" aria-selected="true" data-pane="info"><?php echo __h('lib_vl_tab_info'); ?><span class="lib-tab-dot" aria-hidden="true"></span></button>
      <button type="button" class="lib-tab" role="tab" id="libTabBooks" aria-controls="libPaneBooks" aria-selected="false" data-pane="books" tabindex="-1"><?php echo __h('lib_vl_tab_books'); ?> <span class="lib-tab-n"><?php echo count($vols); ?></span><span class="lib-tab-dot" aria-hidden="true"></span></button>
    </div>
    </div>
    <div class="lib-modal-body" id="libEditBody">
    <div class="lib-pane" id="libPaneInfo" role="tabpanel" aria-labelledby="libTabInfo">
    <form id="libEditForm" autocomplete="off">
      <input type="hidden" name="action" value="save_series">
      <input type="hidden" name="sid" value="<?php echo (int)$series['id']; ?>">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
      <label><?php echo __h('lib_series_name'); ?></label>
      <input type="text" class="form-control form-control-sm" name="name" value="<?php echo h($series['name']); ?>" maxlength="200" list="libSuggSeries">
      <div class="help"><?php echo __h('lib_rename_help'); ?></div>
      <label><?php echo __h('lib_status'); ?></label>
      <select class="form-control form-control-sm" name="status" style="width:auto;">
        <option value=""<?php echo $series['status'] === '' ? ' selected' : ''; ?>>—</option>
        <option value="ongoing"<?php echo $series['status'] === 'ongoing' ? ' selected' : ''; ?>><?php echo __h('lib_status_ongoing'); ?></option>
        <option value="completed"<?php echo $series['status'] === 'completed' ? ' selected' : ''; ?>><?php echo __h('lib_status_completed'); ?></option>
        <option value="hiatus"<?php echo $series['status'] === 'hiatus' ? ' selected' : ''; ?>><?php echo __h('lib_status_hiatus'); ?></option>
      </select>
      <label><?php echo __h('lib_author'); ?></label>
      <input type="text" class="form-control form-control-sm" name="authors" value="<?php echo h($_names($terms['author'])); ?>" list="libSuggAuthor">
      <label><?php echo __h('lib_group'); ?> <span class="help"><?php echo __h('lib_group_help'); ?></span></label><?php /* ★ (2026-11-02 01:53) 그룹(서클) */ ?>
      <input type="text" class="form-control form-control-sm" name="groups" value="<?php echo h($_names($terms['group'] ?? [])); ?>" list="libSuggGroup">
      <label><?php echo __h('lib_genre'); ?></label>
      <input type="text" class="form-control form-control-sm" name="genres" value="<?php echo h($_names($terms['genre'])); ?>" list="libSuggGenre">
      <label><?php echo __h('lib_tag'); ?></label>
      <input type="text" class="form-control form-control-sm" name="tags" value="<?php echo h($_names($terms['tag'])); ?>" list="libSuggTag">
      <div class="help"><?php echo __h('lib_terms_help'); ?></div>
      <label><?php echo __h('lib_description'); ?></label>
      <textarea class="form-control form-control-sm" name="description" rows="4" maxlength="5000"><?php echo h($series['description']); ?></textarea>
    </form>
    </div>
    <div class="lib-pane" id="libPaneBooks" role="tabpanel" aria-labelledby="libTabBooks" hidden>

    <?php
    // ★ (2026-11-08 fix60) 도서 목록 다시 만듦(펜닐 요청 — 시안 A+B+E)
    //   · 같은 권(중복)은 한 묶음(시리즈 화면의 '중복 보기'와 같은 기준 mc_lib_dup_key) — 1권(중복 포함)·2권·3권…
    //   · 책마다 '몇 권'만 고름(그 권의 중복으로 / 새 권으로 / 직접 입력) — 예전 '번호·표시' 빈 칸 두 개는 무엇인지 알기 어려웠음
    //   · 빼기·다른 시리즈로·자동으로·책 추가는 목록에 색으로 바로 보이고, 아래 [저장]을 눌러야 한 번에 적용(예전: 누르자마자 저장하고 창이 닫힘)
    $_vl_imp = function_exists('mc_lib_implicit_first') ? mc_lib_implicit_first($vols) : false;
    $_vl_rows = [];
    // ★ fix64 같은 권 사본은 시리즈 화면과 같은 차례(맨 앞 = 대표 사본 — 관리자가 정한 순서 → 읽던 사본·쪽수·크기·수정 시각)
    $_vl_src = [];
    if (function_exists('mc_lib_group_dups')) { foreach (mc_lib_group_dups($vols, isset($reads) && is_array($reads) ? $reads : []) as $_g) foreach ($_g['_copies'] as $_c) $_vl_src[] = $_c; }
    if (count($_vl_src) !== count($vols)) $_vl_src = $vols;
    foreach ($_vl_src as $v) {
        $_vl_rows[] = [
            'id' => (int)$v['id'], 'no' => $v['vol_no'] === null ? null : (float)$v['vol_no'], 'label' => (string)$v['vol_label'],
            'fn' => (string)$v['filename'], 'folder' => (string)$v['folder'], 'man' => (int)($v['manual'] ?? 0),
            'g' => function_exists('mc_lib_dup_key') ? mc_lib_dup_key($v) : ('id' . (int)$v['id']),
            'gt' => function_exists('mc_lib_vol_title') ? mc_lib_vol_title($v, $_vl_imp) : (string)$v['filename'],
            'so' => function_exists('mc_lib_vol_sort_no') ? min(1e15, mc_lib_vol_sort_no($v, $_vl_imp)) : 0,
            // ★ fix68 번외·외전 부제 — 서버(mc_lib_dup_key)는 같은 번호여도 'N권 · Omake'로 따로 묶음. 편집 창이 번호만 보고 같은 권으로 보이던 것(저장하면 따로 됨)
            'x' => function_exists('mc_lib_is_extra_sub') && mc_lib_is_extra_sub($v['subtitle'] ?? '') ? 1 : 0, 'sub' => (string)($v['subtitle'] ?? ''),
        ];
    }
    ?>
    <div class="lib-vl-head">
      <strong><?php echo __h('lib_volumes'); ?></strong>
      <span class="help"><?php echo __h('lib_vl_help'); ?></span>
    </div>
    <div class="lib-vl-stick">
    <div class="lib-vl-tools" id="libVlTools">
      <div class="lib-vl-tl"><span class="lib-vl-selcnt" id="libVlSel"></span><span class="lib-vl-hint" id="libVlHint" role="note" hidden></span></div>
      <button type="button" class="btn btn-sm btn-outline-primary" id="libVlMoveOpen" disabled><?php echo __h('lib_vl_move'); ?></button>
      <button type="button" class="btn btn-sm btn-outline-danger" id="libVlDel" disabled title="<?php echo __h('lib_detach_tip'); ?>"><?php echo __h('lib_vl_del'); ?></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="libVlAuto" disabled title="<?php echo __h('lib_auto_tip'); ?>"><?php echo __h('lib_vl_auto'); ?></button>
    </div>
    <div class="lib-vl-move" id="libVlMoveBox" hidden>
      <input type="text" class="form-control form-control-sm" id="libMoveName" autocomplete="off" placeholder="<?php echo __h('lib_move_ph'); ?>" list="libSuggSeries" aria-label="<?php echo __h('lib_move_ph'); ?>">
      <button type="button" class="btn btn-sm btn-primary" id="libVlMoveOk"><?php echo __h('lib_vl_move_ok'); ?></button>
      <button type="button" class="btn btn-sm btn-light" id="libVlMoveX"><?php echo __h('common_cancel'); ?></button>
    </div>
    </div>
    <div class="lib-vl" id="libVl" role="list"></div>
    <script type="application/json" id="libVlData"><?php echo json_encode($_vl_rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?></script>

    <?php /* ★ (2026-11-06 07:20) 책 추가 · ★ fix60 넣는 방식(권 번호대로 / 새 권으로 이어 붙이기) — 넣은 책은 목록에 '추가 예정'으로, [저장] 때 적용 */ ?>
    <div class="lib-vl-head mt-3" id="libAddHead">
      <strong><?php echo __h('lib_add_title'); ?></strong>
      <span class="help"><?php echo __h('lib_add_help'); ?></span>
    </div>
    <div class="lib-vl-addq">
      <input type="search" class="form-control form-control-sm" id="libAddQ" placeholder="<?php echo __h('lib_add_ph'); ?>" maxlength="100" autocomplete="off" aria-label="<?php echo __h('lib_add_ph'); ?>">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="libAddFind"><?php echo __h('lib_add_find'); ?></button>
    </div>
    <div id="libAddRes" class="lib-addres" hidden></div>
    <div id="libAddBar" class="lib-vl-addbar" hidden>
      <fieldset class="lib-vl-mode">
        <legend><?php echo __h('lib_vl_add_mode'); ?></legend>
        <div class="lib-vl-mode-o">
        <label title="<?php echo __h('lib_vl_add_num_d'); ?>"><input type="radio" name="libAddMode" value="num" checked> <span><strong><?php echo __h('lib_vl_add_num'); ?></strong> <small><?php echo __h('lib_vl_add_num_d'); ?></small></span></label>
        <label title="<?php echo __h('lib_vl_add_app_d'); ?>"><input type="radio" name="libAddMode" value="append"> <span><strong><?php echo __h('lib_vl_add_app'); ?></strong> <small><?php echo __h('lib_vl_add_app_d'); ?></small></span></label>
        <?php /* ★ fix67 고른 권의 중복으로 — 책의 번호와 상관없이 넣을 권을 직접 고름(번호대로면 '1권' 책은 늘 1권으로만 갔음) */ ?>
        <label class="lib-vl-pick" title="<?php echo __h('lib_vl_add_pick_d'); ?>"><input type="radio" name="libAddMode" value="pick"> <span><strong><?php echo __h('lib_vl_add_pick'); ?></strong> <small><?php echo __h('lib_vl_add_pick_d'); ?></small></span>
          <select class="form-control form-control-sm" id="libAddVol" aria-label="<?php echo __h('lib_vl_add_pick_sel'); ?>"></select></label>
        </div>
        <div class="lib-vl-mode-d" id="libAddModeD"><?php echo __h('lib_vl_add_num_d'); ?></div>
      </fieldset>
      <div class="lib-vl-addgo">
        <span class="help" id="libAddCnt"></span>
        <button type="button" class="btn btn-sm btn-primary" id="libAddBtn"><?php echo __h('lib_vl_add_btn'); ?></button>
      </div>
    </div>

    </div><?php /* #libPaneBooks */ ?>
    </div><?php /* .lib-modal-body */ ?>
    <div class="lib-modal-foot">
      <span class="lib-vl-sum" id="libVlSum"></span>
      <span class="lib-msg" id="libEditMsg" role="status" aria-live="polite"></span>
      <button type="button" class="btn btn-sm btn-secondary" id="libEditCancel"><?php echo __h('common_cancel'); ?></button>
      <button type="button" class="btn btn-sm btn-primary" id="libEditSave"><?php echo __h('lib_save'); ?></button>
    </div>
  </div>
</div>
<script>
(function () {
  var modal = document.getElementById('libEditModal');
  var openBtn = document.getElementById('srEditBtn');
  var msg = document.getElementById('libEditMsg');
  var CSRF = <?php echo json_encode($csrf_token); ?>;
  var BIDX = <?php echo (int)$series['bidx']; ?>;
  var SID = <?php echo (int)$series['id']; ?>;
  var T = <?php echo json_encode(['saving' => __('lib_saving'), 'failed' => __('lib_save_failed'), 'need_sel' => __('lib_vl_need_sel'), 'need_name' => __('lib_need_name'),
    'auto_none' => __('lib_auto_none'), 'dirty' => __('lib_unsaved_lost'),
    'add_none' => __('lib_add_none'), 'add_cnt' => __('lib_add_cnt'), 'add_need' => __('lib_add_need'), 'add_all' => __('lib_add_all'), 'searching' => __('lib_add_searching'),
    'vol' => __('lib_vol_label_num'), 'nonum' => __('lib_vl_nonum'), 'dup' => __('lib_vl_dup'), 'place' => __('lib_vl_place'), 'newvol' => __('lib_vl_newvol'), 'custom' => __('lib_vl_custom'),
    'no' => __('lib_vl_no'), 'label' => __('lib_vl_label'), 'sel' => __('lib_vl_sel'), 'undo' => __('lib_vl_undo'), 'man' => __('lib_manual_badge'), 'man_tip' => __('lib_manual_badge_tip'),
    'st_add' => __('lib_vl_st_add'), 'st_del' => __('lib_vl_st_del'), 'st_mov' => __('lib_vl_st_mov'), 'st_auto' => __('lib_vl_st_auto'), 'st_no' => __('lib_vl_st_no'),
    'sum_add' => __('lib_vl_sum_add'), 'sum_del' => __('lib_vl_sum_del'), 'sum_mov' => __('lib_vl_sum_mov'), 'sum_auto' => __('lib_vl_sum_auto'), 'sum_no' => __('lib_vl_sum_no'),
    'save_n' => __('lib_vl_save_n'), 'save' => __('lib_save'), 'confirm' => __('lib_vl_confirm'), 'gone' => __('lib_vl_gone'), 'added' => __('lib_vl_added'), 'partial' => __('lib_vl_partial'),
    'pick' => __('lib_vl_pick'), 'here' => __('lib_vl_here'), 'sel0' => __('lib_vl_sel0'),
    'hint_del' => __('lib_vl_hint_del'), 'hint_man' => __('lib_vl_hint_man'), 'lone_auto' => __('lib_vl_lone_auto'), 'lone_no' => __('lib_vl_lone_no'),
    'vol_up' => __('lib_vl_vol_up'), 'vol_down' => __('lib_vl_vol_down'), 'copy_up' => __('lib_vl_copy_up'), 'copy_down' => __('lib_vl_copy_down'), 'rep' => __('lib_vl_rep'), 'rep_tip' => __('lib_vl_rep_tip'),
    'st_ord' => __('lib_vl_st_ord'), 'sum_ord' => __('lib_vl_sum_ord'), 'ord_undo' => __('lib_vl_ord_undo'), 'add_pick_need' => __('lib_vl_add_pick_need')],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  if (!modal || !openBtn) return;
  var suggLoaded = false;
  function loadSugg() {
    if (suggLoaded) return; suggLoaded = true;
    var f = new FormData(); f.append('action', 'suggest'); f.append('csrf_token', CSRF); f.append('bidx', BIDX);
    fetch('library_api.php', { method: 'POST', body: f, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r || !r.success) { suggLoaded = false; return; }
        var map = { libSuggAuthor: r.author, libSuggGroup: r.group, libSuggGenre: r.genre, libSuggTag: r.tag, libSuggSeries: r.series };
        Object.keys(map).forEach(function (id) {
          var dl = document.getElementById(id); if (!dl || !map[id]) return;
          var frag = document.createDocumentFragment();
          map[id].forEach(function (n) { var o = document.createElement('option'); o.value = n; frag.appendChild(o); }); // 글자로만 넣음(HTML 해석 없음)
          dl.appendChild(frag);
        });
      }).catch(function () { suggLoaded = false; });
  }

  // ── 도서 목록 상태(★ fix60) — 바꾼 것은 여기에만 쌓이고 [저장] 때 서버에 보냄 ──
  var ROWS = [];
  // ★ fix69 처음 상태로 다시 만들 수 있게 함수로([취소]·Esc 로 닫으면 바꾼 것을 모두 버림)
  function initRows() {
    try { ROWS = JSON.parse(document.getElementById('libVlData').textContent) || []; } catch (e) { ROWS = []; }
    ROWS.forEach(function (r, i) { r.o = { no: r.no, label: r.label }; r.op = ''; r.to = ''; r.custom = false; r.oi = i; }); // oi = 처음 차례(사본 순서를 바꿨는지 볼 때)
  }
  initRows();
  var list = document.getElementById('libVl'), selBox = document.getElementById('libVlSel'), sumBox = document.getElementById('libVlSum');
  var saveBtn = document.getElementById('libEditSave');
  var checked = {};
  // ★ fix64 탭(작품 정보 | 도서 목록) — 바꾼 것이 있는 탭에는 주황 점 · ★ fix69 고른 탭은 기억하지 않음(열 때마다 작품 정보)
  var body = document.getElementById('libEditBody'), tabInfo = document.getElementById('libTabInfo'), tabBooks = document.getElementById('libTabBooks');
  var paneInfo = document.getElementById('libPaneInfo'), paneBooks = document.getElementById('libPaneBooks');
  function showTab(which, focus) {
    var books = which === 'books';
    paneInfo.hidden = books; paneBooks.hidden = !books;
    tabInfo.setAttribute('aria-selected', books ? 'false' : 'true'); tabBooks.setAttribute('aria-selected', books ? 'true' : 'false');
    tabInfo.tabIndex = books ? -1 : 0; tabBooks.tabIndex = books ? 0 : -1;
    body.scrollTop = 0;
    if (focus) (books ? tabBooks : tabInfo).focus();
  }
  [tabInfo, tabBooks].forEach(function (t) {
    t.addEventListener('click', function () { showTab(t.getAttribute('data-pane')); });
    t.addEventListener('keydown', function (e) { if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Home' || e.key === 'End') { e.preventDefault(); showTab(t === tabInfo ? 'books' : 'info', true); } });
  });
  function infoDirty() {
    var fl = modal.querySelectorAll('#libEditForm input[type=text], #libEditForm textarea');
    for (var i = 0; i < fl.length; i++) if (fl[i].value !== fl[i].defaultValue) return true;
    var sl = modal.querySelectorAll('#libEditForm select');
    for (var j = 0; j < sl.length; j++) { var o = sl[j].options[sl[j].selectedIndex]; if (o && !o.defaultSelected) return true; }
    return false;
  }
  document.getElementById('libEditForm').addEventListener('input', function () { tabInfo.classList.toggle('is-dirty', infoDirty()); });
  document.getElementById('libEditForm').addEventListener('change', function () { tabInfo.classList.toggle('is-dirty', infoDirty()); });
  function el(t, c, x) { var e = document.createElement(t); if (c) e.className = c; if (x != null) e.textContent = x; return e; }
  function fmt(n) { return (n === null || n === undefined || n === '') ? '' : String(Math.round(Number(n) * 100) / 100); }
  function f(s, a) { return String(s).replace('%s', a).replace('%d', a); }
  function numChanged(r) { return fmt(r.no) !== fmt(r.o.no) || String(r.label || '') !== String(r.o.label || ''); }
  function keyOf(r) { // 묶음 키 — 손대지 않은 책은 서버 기준(mc_lib_dup_key) 그대로, 바꾼 책은 번호(없으면 표시 이름)
    if (!numChanged(r) && r.op !== 'add') return (r.no === null && Number(r.so) === 0 && !r.label) ? 'v1|' : r.g; // 번호 없이 1권으로 보는 본편 = 1권 묶음
    if (r.no !== null && r.no !== '' && r.no !== undefined) {
      // ★ fix68 서버 mc_lib_dup_key 와 같게 — 번외 부제는 'x', 시작 번호가 같은 범위 표시('1-3')는 끝 번호까지
      var rm = /(\d{1,4})\s*-\s*(\d{1,4})/.exec(String(r.label || '')), rg = (rm && Number(rm[2]) > Number(rm[1]) && Number(rm[1]) === Number(r.no)) ? 'r' + Number(rm[2]) : '';
      return 'v' + fmt(r.no) + '|' + (r.x ? 'x' : '') + rg;
    }
    return r.label ? 'L|' + r.label : 'i|' + r.id;
  }
  function titleOf(r) {
    if (!numChanged(r) && r.op !== 'add') return r.gt;
    var xs = (r.x && r.sub) ? ' · ' + r.sub : ''; // ★ fix68 번외는 'N권 · Omake'(시리즈 화면 mc_lib_vol_title 과 같게)
    var lm = /^(?:v|vol\.?\s*)?(\d{1,5}(?:\.\d{1,2})?)(?:\s*(?:권|巻|卷|冊))?$/i.exec(String(r.label || '')); // ★ fix68 '01권'·'v3' 도 'N권'(시리즈 화면 mc_lib_label_display 와 같게)
    if (r.label) return (lm ? f(T.vol, fmt(Number(lm[1]))) : r.label) + xs; // 숫자뿐인 표시 이름은 'N권'(시리즈 화면 mc_lib_label_display 와 같게)
    if (r.no !== null && r.no !== '' && r.no !== undefined) return f(T.vol, fmt(r.no)) + xs;
    return T.nonum;
  }
  function sortOf(r) {
    if (!numChanged(r) && r.op !== 'add') return Number(r.so) || 0;
    return (r.no !== null && r.no !== '' && r.no !== undefined) ? Number(r.no) : 1e15;
  }
  function groups() { // [{k, t, so, rows, no, label}]
    var m = {}, out = [];
    ROWS.forEach(function (r) {
      var k = keyOf(r);
      if (!m[k]) { m[k] = { k: k, t: titleOf(r), so: sortOf(r), rows: [] }; out.push(m[k]); }
      m[k].rows.push(r); m[k].so = Math.min(m[k].so, sortOf(r));
    });
    out.forEach(function (g) {
      var rep = g.rows.filter(function (r) { return r.op !== 'del' && r.op !== 'mov'; })[0] || g.rows[0];
      g.no = rep.no; g.label = rep.label;
      if ((g.no === null || g.no === undefined) && g.so === 0 && !g.label) g.no = 1; // 번호 없는 본편(1권으로 보는 책) 묶음으로 옮기면 1권
    });
    out.sort(function (a, b) {
      if (a.so !== b.so) return a.so - b.so;
      var xa = /\|x/.test(a.k) ? 1 : 0, xb = /\|x/.test(b.k) ? 1 : 0; if (xa !== xb) return xa - xb; // ★ fix68 같은 번호면 본권 다음에 번외(시리즈 화면과 같게)
      return a.t < b.t ? -1 : a.t > b.t ? 1 : 0;
    });
    return out;
  }
  function maxNo() { var m = 0; ROWS.forEach(function (r) { if (r.op === 'del' || r.op === 'mov') return; var n = (r.no === null || r.no === undefined) ? (Number(r.so) === 0 ? 1 : 0) : Number(r.no); if (n > m && n < 1e9) m = n; }); return Math.floor(m); }
  function stateOf(r) {
    if (r.op === 'add') return 'add';
    if (r.op === 'del' || r.op === 'mov' || r.op === 'auto') return r.op;
    return numChanged(r) ? 'no' : '';
  }
  function badge(cls, t) { return el('span', 'lib-vl-b ' + cls, t); }
  // 번호 없이 1권으로 보던 책(본편)이 있는데 손으로 1권을 넣으면, 그 책들도 1권으로 맞춤 — 안 그러면 시리즈 화면에서 둘이 따로 보임(번호 있는 1권이 생기면 '번호 없는 본편=1권'이 풀림)
  function fixImplicit() {
    ROWS.forEach(function (r) { if (r.autoFix) { r.no = r.o.no; r.label = r.o.label; r.autoFix = false; } });
    var one = ROWS.some(function (r) { return r.op !== 'del' && r.op !== 'mov' && r.op !== 'auto' && (numChanged(r) || r.op === 'add') && r.no !== null && r.no !== undefined && Number(r.no) === 1; });
    if (!one) return;
    ROWS.forEach(function (r) { if (!r.op && r.no === null && Number(r.so) === 0 && !r.label && !r.custom) { r.no = 1; r.label = '1'; r.autoFix = true; } });
  }
  // ★ fix64 권 자리 바꾸기(↑↓) · 같은 권 사본 순서(↑↓, 맨 위 = 대표)
  var ARR_UP = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>';
  var ARR_DN = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
  function isLive(r) { return r.op !== 'del' && r.op !== 'mov' && r.op !== 'auto'; }
  // ★ fix68 다른 책을 '넣을' 수 있는 권 — 번호만으로 된 권(1권·2권…, 번호 없이 1권으로 보는 본편 포함). 번호 없는 묶음은 서버가 부제로 묶어 번호·표시 이름만으로는 같은 묶음이 안 됨
  function joinable(g) { return plainNumKey(g.k) && g.no !== null && g.no !== undefined && g.no !== '' && !isNaN(Number(g.no)); }
  function plainNumKey(k) { return /^v\d+(\.\d+)?\|$/.test(k); } // 번호만으로 된 권(범위·번외 아님) — 자리 바꾸기는 이런 권끼리만
  function origNum(r) { return (r.o.no !== null && r.o.no !== undefined) ? Number(r.o.no) : ((Number(r.so) === 0 && !r.o.label) ? 1 : null); }
  function relabel(lb, oldN, newN) { // 표시 이름 속 옛 번호를 새 번호로("01권" → "2권"), 없으면 번호 그대로, 번호가 안 든 이름("외전")은 그대로
    if (!lb) return fmt(newN);
    var done = false, out = String(lb).replace(/\d+(?:\.\d+)?/g, function (m) { if (!done && Number(m) === Number(oldN)) { done = true; return fmt(newN); } return m; });
    return done ? out : lb;
  }
  function setNo(r, newN, oldN) {
    r.custom = false; r.autoFix = false;
    if (origNum(r) === Number(newN)) { r.no = r.o.no; r.label = r.o.label; return; } // 원래 번호로 돌아오면 원래 값 그대로(바뀐 것 아님)
    r.no = Number(newN); r.label = relabel(r.label, oldN, newN);
  }
  function swapGroups(a, b) {
    var na = Number(a.no), nb = Number(b.no);
    a.rows.filter(isLive).forEach(function (r) { setNo(r, nb, na); });
    b.rows.filter(isLive).forEach(function (r) { setNo(r, na, nb); });
    render();
  }
  function ordChanged(g) {
    var ids = g.rows.filter(isLive).map(function (r) { return r; });
    for (var i = 1; i < ids.length; i++) if (ids[i - 1].oi > ids[i].oi) return true;
    return false;
  }
  function moveCopy(g, r, d) {
    var lv = g.rows.filter(isLive), i = lv.indexOf(r), o = lv[i + d];
    if (!o) return;
    var a = ROWS.indexOf(r), b = ROWS.indexOf(o);
    ROWS[a] = o; ROWS[b] = r; render();
  }
  function resetOrder(g) {
    var pos = g.rows.map(function (r) { return ROWS.indexOf(r); }).sort(function (x, y) { return x - y; });
    var rs = g.rows.slice().sort(function (x, y) { return x.oi - y.oi; });
    pos.forEach(function (p, i) { ROWS[p] = rs[i]; }); render();
  }
  function arrBtn(html, title, dis, fn) {
    var b = el('button', ''); b.type = 'button'; b.innerHTML = html; b.title = title; b.setAttribute('aria-label', title); b.disabled = !!dis;
    if (!dis) b.addEventListener('click', fn); return b;
  }
  function render() {
    fixImplicit();
    var gs = groups();
    var nums = gs.filter(function (g) { return plainNumKey(g.k) && g.rows.some(isLive) && g.no !== null && g.no !== undefined && !isNaN(Number(g.no)); });
    list.textContent = '';
    gs.forEach(function (g) {
      var live = g.rows.filter(function (r) { return r.op !== 'del' && r.op !== 'mov'; }).length;
      var box = el('div', 'lib-vl-g'); box.setAttribute('role', 'listitem');
      var hd = el('div', 'lib-vl-gh');
      hd.appendChild(el('strong', '', g.t));
      if (live > 1) hd.appendChild(badge('is-dup', f(T.dup, live)));
      var oc = ordChanged(g);
      if (oc) {
        hd.appendChild(badge('is-ord', T.st_ord));
        var ob = el('button', 'btn btn-sm btn-light lib-vl-undo'); ob.type = 'button'; ob.title = T.ord_undo; ob.setAttribute('aria-label', T.ord_undo + ' — ' + g.t);
        ob.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>';
        ob.addEventListener('click', function () { resetOrder(g); }); hd.appendChild(ob);
      }
      hd.appendChild(el('span', 'lib-vl-sp'));
      var ni = nums.indexOf(g);
      if (ni >= 0 && nums.length > 1) {
        var ga = el('span', 'lib-vl-arr');
        ga.appendChild(arrBtn(ARR_UP, T.vol_up + ' — ' + g.t, ni === 0, function () { swapGroups(g, nums[ni - 1]); }));
        ga.appendChild(arrBtn(ARR_DN, T.vol_down + ' — ' + g.t, ni === nums.length - 1, function () { swapGroups(g, nums[ni + 1]); }));
        hd.appendChild(ga);
      }
      box.appendChild(hd);
      var lvRows = g.rows.filter(isLive);
      g.rows.forEach(function (r) {
        var st = stateOf(r);
        var row = el('div', 'lib-vl-r' + (st ? ' is-' + st : ''));
        var cb = document.createElement('input'); cb.type = 'checkbox'; cb.className = 'lib-vl-cb'; cb.checked = !!checked[r.id]; cb.setAttribute('aria-label', r.fn);
        cb.disabled = (st === 'add' || st === 'del' || st === 'mov' || st === 'auto'); // 이미 할 일이 정해진 책은 ↶ 로만 되돌림
        cb.addEventListener('change', function () { if (cb.checked) checked[r.id] = 1; else delete checked[r.id]; tools(); });
        row.appendChild(cb);
        var main = el('div', 'lib-vl-main');
        var nm = el('div', 'lib-vl-fn', r.fn); nm.title = r.fn; main.appendChild(nm);
        var meta = el('div', 'lib-vl-meta');
        if (r.folder) meta.appendChild(el('span', 'lib-vl-folder', r.folder));
        if (lvRows.length > 1 && lvRows[0] === r) { var rb = badge('is-rep', T.rep); rb.title = T.rep_tip; meta.appendChild(rb); }
        if (r.man && st !== 'auto') { var mb = badge('is-man', T.man); mb.title = T.man_tip; meta.appendChild(mb); }
        if (st === 'add') meta.appendChild(badge('is-add', T.st_add));
        if (st === 'del') meta.appendChild(badge('is-del', T.st_del));
        if (st === 'mov') meta.appendChild(badge('is-mov', f(T.st_mov, r.to)));
        if (st === 'auto') meta.appendChild(badge('is-auto', T.st_auto));
        if (st === 'no' || (st === 'add' && numChanged(r))) meta.appendChild(badge('is-no', T.st_no));
        if (meta.childNodes.length) main.appendChild(meta);
        row.appendChild(main);
        var ctl = el('div', 'lib-vl-ctl');
        var ci = lvRows.indexOf(r);
        if (ci >= 0 && lvRows.length > 1) {
          var ca = el('span', 'lib-vl-arr');
          ca.appendChild(arrBtn(ARR_UP, T.copy_up + ' — ' + r.fn, ci === 0, function () { moveCopy(g, r, -1); }));
          ca.appendChild(arrBtn(ARR_DN, T.copy_down + ' — ' + r.fn, ci === lvRows.length - 1, function () { moveCopy(g, r, 1); }));
          ctl.appendChild(ca);
        }
        if (st !== 'del' && st !== 'mov' && st !== 'auto') {
          var sel = document.createElement('select'); sel.className = 'form-control form-control-sm lib-vl-place'; sel.setAttribute('aria-label', T.place + ' — ' + r.fn);
          var cur = keyOf(r), hasCur = false;
          gs.forEach(function (o) {
            if (o.k !== cur && !joinable(o)) return; // ★ fix68 번호 없는 묶음(Omake·부제)·번외·범위 권에는 '넣기'가 안 됨(넣어도 저장하면 따로 되고 1권과 섞였음) — 그런 권은 '직접 입력'
            var op = document.createElement('option'); op.value = 'g:' + o.k;
            op.textContent = o.k === cur ? f(T.here, o.t) : f(T.pick, o.t);
            if (o.k === cur) { op.selected = true; hasCur = true; }
            sel.appendChild(op);
          });
          var on = document.createElement('option'); on.value = 'new'; on.textContent = f(T.newvol, maxNo() + 1); sel.appendChild(on);
          var oc = document.createElement('option'); oc.value = 'custom'; oc.textContent = T.custom; if (r.custom) oc.selected = true; sel.appendChild(oc);
          if (r.custom) sel.value = 'custom'; else if (!hasCur) sel.value = 'custom';
          sel.addEventListener('change', function () {
            var v = sel.value;
            if (v === 'custom') { r.custom = true; render(); focusCustom(r.id); return; }
            r.custom = false;
            if (v === 'new') { r.no = maxNo() + 1; r.label = fmt(r.no); } // 표시 이름도 번호로(스캔이 붙이는 것과 같게 — 비우면 시리즈 화면에 부제가 나옴)
            else { var k = v.slice(2), tg = gs.filter(function (o) { return o.k === k; })[0]; if (tg && tg.k !== keyOf(r)) { r.no = tg.no === undefined ? null : tg.no; r.label = tg.label || fmt(r.no); } }
            render();
          });
          ctl.appendChild(sel);
        }
        if (st) {
          var ub = el('button', 'btn btn-sm btn-light lib-vl-undo'); ub.type = 'button'; ub.title = T.undo; ub.setAttribute('aria-label', T.undo + ' — ' + r.fn);
          ub.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>';
          ub.addEventListener('click', function () { undo(r); });
          ctl.appendChild(ub);
        }
        row.appendChild(ctl);
        if (r.custom && st !== 'del' && st !== 'mov' && st !== 'auto') {
          var cu = el('div', 'lib-vl-custom');
          var l1 = el('label', ''); l1.appendChild(el('span', '', T.no));
          var i1 = document.createElement('input'); i1.type = 'text'; i1.inputMode = 'decimal'; i1.className = 'form-control form-control-sm lib-vl-no'; i1.value = fmt(r.no); i1.setAttribute('data-vid', r.id);
          i1.addEventListener('change', function () { var v = i1.value.trim(); r.no = (v === '' || isNaN(Number(v))) ? null : Math.max(0, Math.min(99999, Number(v))); render(); });
          l1.appendChild(i1); cu.appendChild(l1);
          var l2 = el('label', ''); l2.appendChild(el('span', '', T.label));
          var i2 = document.createElement('input'); i2.type = 'text'; i2.maxLength = 40; i2.className = 'form-control form-control-sm lib-vl-label'; i2.value = r.label || '';
          i2.addEventListener('change', function () { r.label = i2.value.trim(); render(); });
          l2.appendChild(i2); cu.appendChild(l2);
          row.appendChild(cu);
        }
        box.appendChild(row);
      });
      list.appendChild(box);
    });
    tools(); summary();
    if (typeof fillAddVol === 'function') fillAddVol();
  }
  function focusCustom(id) { var i = list.querySelector('.lib-vl-no[data-vid="' + id + '"]'); if (i) i.focus(); }
  function undo(r) {
    if (r.op === 'add') { ROWS = ROWS.filter(function (x) { return x !== r; }); delete checked[r.id]; render(); return; }
    r.op = ''; r.to = ''; r.no = r.o.no; r.label = r.o.label; r.custom = false; r.autoFix = false; render();
  }
  function selRows() { return ROWS.filter(function (r) { return checked[r.id] && r.op !== 'add'; }); }
  var bMove = document.getElementById('libVlMoveOpen'), bDel = document.getElementById('libVlDel'), bAuto = document.getElementById('libVlAuto');
  var mvBox = document.getElementById('libVlMoveBox'), mvName = document.getElementById('libMoveName'), hint = document.getElementById('libVlHint');
  function tools() {
    var n = selRows().length;
    selBox.textContent = n ? f(T.sel, n) : T.sel0; selBox.classList.toggle('is-none', !n);
    bMove.disabled = bDel.disabled = !n;
    // ★ fix62 '자동으로'는 직접 묶음 책을 골랐을 때만 — 무엇을 하는지 고른 책에 맞춰 한 줄로 알려 줌
    var man = selRows().some(function (r) { return r.man; });
    bAuto.disabled = !man;
    hint.hidden = !n; hint.textContent = n ? (man ? T.hint_man : T.hint_del) : '';
    if (!n) mvBox.hidden = true;
  }
  function counts() {
    var c = { add: 0, del: 0, mov: 0, auto: 0, no: 0, ord: 0 };
    ROWS.forEach(function (r) { var s = stateOf(r); if (s) c[s]++; else if (r.op === 'add') c.add++; if (r.op === 'add' && numChanged(r)) c.no++; });
    groups().forEach(function (g) { if (ordChanged(g)) c.ord++; });
    return c;
  }
  function summary() {
    var c = counts(), parts = [], n = 0;
    sumBox.textContent = '';
    [['add', T.sum_add, 'is-add'], ['del', T.sum_del, 'is-del'], ['mov', T.sum_mov, 'is-mov'], ['auto', T.sum_auto, 'is-auto'], ['no', T.sum_no, 'is-no'], ['ord', T.sum_ord, 'is-ord']].forEach(function (k) {
      if (c[k[0]]) { sumBox.appendChild(badge(k[2], f(k[1], c[k[0]]))); parts.push(f(k[1], c[k[0]])); n += c[k[0]]; }
    });
    saveBtn.textContent = n ? f(T.save_n, n) : T.save;
    if (tabBooks) tabBooks.classList.toggle('is-dirty', n > 0);
    return parts;
  }
  bDel.addEventListener('click', function () {
    var s = selRows();
    // ★ fix62 책이 하나뿐인 시리즈(넣을 책도 없음)에서 그 책을 빼면 갈 곳이 없음 → 직접 묶음이면 '자동으로'(표시만 지움), 아니면 알려 주고 그대로
    var base = ROWS.filter(function (r) { return r.op !== 'add'; });
    if (base.length === 1 && s.length === 1 && !ROWS.some(function (r) { return r.op === 'add'; })) {
      var r1 = s[0];
      if (r1.man) { r1.op = 'auto'; r1.to = ''; r1.custom = false; r1.no = r1.o.no; r1.label = r1.o.label; delete checked[r1.id]; render(); msg.textContent = T.lone_auto; }
      else msg.textContent = T.lone_no;
      return;
    }
    s.forEach(function (r) { r.op = 'del'; r.to = ''; r.custom = false; r.no = r.o.no; r.label = r.o.label; delete checked[r.id]; }); msg.textContent = ''; render();
  });
  bAuto.addEventListener('click', function () {
    var s = selRows(), m = s.filter(function (r) { return r.man; });
    if (!m.length) { msg.textContent = T.auto_none; return; }
    m.forEach(function (r) { r.op = 'auto'; r.to = ''; r.custom = false; r.no = r.o.no; r.label = r.o.label; delete checked[r.id]; }); render();
  });
  bMove.addEventListener('click', function () { mvBox.hidden = false; mvName.focus(); });
  document.getElementById('libVlMoveX').addEventListener('click', function () { mvBox.hidden = true; });
  function doMove() {
    var name = mvName.value.trim();
    if (!name) { msg.textContent = T.need_name; mvName.focus(); return; }
    selRows().forEach(function (r) { r.op = 'mov'; r.to = name; r.custom = false; r.no = r.o.no; r.label = r.o.label; delete checked[r.id]; });
    mvBox.hidden = true; mvName.value = ''; msg.textContent = ''; render();
  }
  document.getElementById('libVlMoveOk').addEventListener('click', doMove);
  mvName.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); doMove(); } });

  function dirty() { return infoDirty() || summary().length > 0; }
  function open() {
    modal.classList.add('open'); document.body.style.overflow = 'hidden'; loadSugg();
    showTab('info'); // ★ fix69 늘 작품 정보부터(브라우저에 기억하지 않음)
  }
  // ★ fix69 저장하지 않고 닫으면 모두 처음 상태로 — 작품 정보 칸, 도서 목록에서 바꾼 것(권·순서·빼기·옮기기·추가 예정), 고른 책, 다른 시리즈 이름, 책 추가 검색어·결과·넣는 방식
  function resetAll() {
    var form = document.getElementById('libEditForm'); if (form) form.reset(); // 칸마다 페이지를 열 때의 값으로(defaultValue·defaultSelected)
    initRows(); checked = {};
    mvBox.hidden = true; mvName.value = '';
    seq++; // 아직 오는 중인 찾기 결과는 버림
    addQ.value = ''; addRes.textContent = ''; addRes.hidden = true; addRes.style.maxHeight = ''; addBar.hidden = true;
    var m0 = modal.querySelector('input[name="libAddMode"][value="num"]'); if (m0) { m0.checked = true; m0.dispatchEvent(new Event('change')); }
    if (addVol) addVol.value = '';
    addCount();
    tabInfo.classList.remove('is-dirty'); tabBooks.classList.remove('is-dirty');
    body.scrollTop = 0;
    render(); msg.textContent = '';
  }
  function close(force) {
    if (saving) return; // ★ fix66 저장 중에는 닫지 않음(Esc 로 '저장 안 하고 닫을까요?'가 나오고 창이 닫혀도 저장은 계속돼 헷갈렸음)
    if (!force && dirty() && !confirm(T.dirty)) return;
    modal.classList.remove('open'); document.body.style.overflow = '';
    resetAll();
  }
  openBtn.addEventListener('click', open);
  document.getElementById('libEditCancel').addEventListener('click', function () { close(false); });
  // ★ (2026-11-07 fix58) 펜닐 요청: 창 바깥(어두운 배경)을 눌러도 닫히지 않게 — 쓰던 내용이 실수로 사라지지 않게. 닫기는 [취소]·[×] 단추와 Esc
  // modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) close(false); }); // ★ fix60 바꾼 것이 있으면 묻고 닫음

  function post(fd) {
    return fetch('library_api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { success: false }; }); });
  }
  var saving = false;
  // ★ fix66 저장이 실패해 다시 풀 때 모든 칸을 그냥 켜면, 할 일이 정해진 책의 체크 칸·맨 끝 ↑↓까지 눌렸음 → 목록을 다시 그려 원래 상태로
  function busy(on) { saving = on; var b = modal.querySelectorAll('button, select, input'); for (var i = 0; i < b.length; i++) b[i].disabled = on; msg.textContent = on ? T.saving : ''; if (!on) render(); }
  function fd(action, extra) { var x = new FormData(); x.append('action', action); x.append('csrf_token', CSRF); Object.keys(extra || {}).forEach(function (k) { var v = extra[k]; if (Array.isArray(v)) v.forEach(function (y) { x.append(k + '[]', y); }); else x.append(k, v); }); return x; }

  // [저장] — 순서: 넣기 → 권 번호 → 다른 시리즈로 → 빼기 → 자동으로 → 작품 정보(이름을 바꾸면 다른 작품과 합쳐질 수 있어 맨 뒤)
  saveBtn.addEventListener('click', function () {
    var adds = [], sets = [], movs = {}, dels = [], autos = [], remain = 0;
    ROWS.forEach(function (r) {
      if (r.op === 'add') adds.push(r.id);
      if (r.op === 'del') dels.push(r.id);
      else if (r.op === 'mov') { (movs[r.to] = movs[r.to] || []).push(r.id); }
      else if (r.op === 'auto') { autos.push(r.id); remain++; }
      else { remain++; if (numChanged(r)) sets.push(r); }
    });
    var parts = summary();
    if ((dels.length || autos.length || Object.keys(movs).length) && !confirm(f(T.confirm, parts.join(' · ')) + (remain ? '' : '\n' + T.gone))) return;
    var infoFd = new FormData(document.getElementById('libEditForm')); // ★ busy() 가 입력칸을 막기 전에 읽음(막힌 칸은 FormData 에 안 들어가 CSRF 토큰까지 빠졌음)
    busy(true);
    var steps = [];
    if (adds.length) steps.push(function () { return post(fd('add_volumes', { sid: SID, vids: adds })); });
    sets.forEach(function (r) { steps.push(function () { return post(fd('set_volume', { vid: r.id, vol_no: fmt(r.no), vol_label: r.label || '' })); }); });
    var movSid = 0; // ★ fix66 남는 책 없이 한 시리즈로 다 옮기면 그 시리즈 화면으로(예전 '다른 시리즈로'처럼 — 서재 목록으로 가던 것)
    Object.keys(movs).forEach(function (name) { steps.push(function () { return post(fd('move_volumes', { bidx: BIDX, series_name: name, vids: movs[name] })).then(function (r) { if (r && r.success && r.sid) movSid = Number(r.sid) || 0; return r; }); }); });
    if (dels.length) steps.push(function () { return post(fd('detach_volumes', { sid: SID, vids: dels })); });
    if (autos.length) steps.push(function () { return post(fd('auto_volumes', { sid: SID, vids: autos })); });
    // ★ fix64 사본 순서 — 권 번호를 다 고친 뒤(번호를 고치면 서버가 그 책의 순서를 지움) 순서를 바꾼 권만
    groups().forEach(function (g) { if (ordChanged(g)) { var ids = g.rows.filter(isLive).map(function (r) { return r.id; }); if (ids.length > 1) steps.push(function () { return post(fd('order_copies', { sid: SID, vids: ids })); }); } });
    var done = 0, chain = Promise.resolve();
    steps.forEach(function (s) { chain = chain.then(function () { return s().then(function (r) { if (!r || !r.success) throw 1; done++; }); }); });
    chain.then(function () {
      if (!remain) { // 남는 책이 없으면 이 작품은 없어짐 → 한 시리즈로 다 옮겼으면 그 시리즈, 아니면 서재 목록으로
        location.href = (movSid && Object.keys(movs).length === 1 && !dels.length && !autos.length) ? 'series.php?sid=' + movSid + '&bidx=' + BIDX : 'browse.php?bidx=' + BIDX; return null;
      }
      return post(infoFd).then(function (r) {
        if (!r || !r.success) throw 1;
        location.href = 'series.php?sid=' + encodeURIComponent(r.sid) + '&bidx=' + BIDX;
      });
    }).catch(function () {
      if (done > 0) { alert(T.partial); location.reload(); return; } // 일부만 적용됨 — 지금 상태를 다시 읽어 보여 줌
      busy(false); msg.textContent = T.failed;
    });
  });

  // ── 책 추가(찾아서 '추가 예정'으로 넣기) ──
  var addQ = document.getElementById('libAddQ'), addRes = document.getElementById('libAddRes'), addBar = document.getElementById('libAddBar'), addCnt = document.getElementById('libAddCnt'), seq = 0;
  function addCount() {
    var n = addRes.querySelectorAll('.lib-aisel:checked').length;
    addCnt.textContent = n ? T.add_cnt.replace('%d', n) : '';
  }
  function find() {
    var q = addQ.value.trim();
    if (!q) { addRes.hidden = true; addBar.hidden = true; return; }
    var my = ++seq;
    addRes.hidden = false; addRes.textContent = ''; addRes.appendChild(el('div', 'lib-anone', T.searching));
    post(fd('find_volumes', { sid: SID, q: q })).then(function (r) {
      if (my !== seq) return;
      addRes.textContent = '';
      var have = {}; ROWS.forEach(function (x) { have[x.id] = 1; });
      var items = ((r && r.success && r.items) || []).filter(function (it) { return !have[it.id]; });
      if (!items.length) { addRes.appendChild(el('div', 'lib-anone', T.add_none)); addBar.hidden = true; fitAdd(true); return; }
      var g = null, box = null;
      items.forEach(function (it) {
        if (it.sid !== g) {
          g = it.sid;
          var hd = el('label', 'lib-ag'), gc = document.createElement('input'); gc.type = 'checkbox'; gc.className = 'lib-agsel'; gc.setAttribute('aria-label', T.add_all + ': ' + it.series);
          hd.appendChild(gc); hd.appendChild(el('span', '', it.series));
          box = el('div', 'lib-agbox');
          (function (b) { gc.addEventListener('change', function () { var c = b.querySelectorAll('.lib-aisel'); for (var i = 0; i < c.length; i++) c[i].checked = gc.checked; addCount(); }); })(box);
          addRes.appendChild(hd); addRes.appendChild(box);
        }
        var row = el('label', 'lib-ai'), cb = document.createElement('input'); cb.type = 'checkbox'; cb.className = 'lib-aisel'; cb.value = it.id;
        cb._it = it;
        cb.addEventListener('change', addCount);
        var tx = el('span', ''); tx.appendChild(document.createTextNode((it.vol ? '[' + it.vol + '] ' : '') + it.filename + ' '));
        if (it.folder) tx.appendChild(el('small', '', it.folder));
        row.appendChild(cb); row.appendChild(tx); box.appendChild(row);
      });
      addBar.hidden = false; addCount(); addRes.scrollTop = 0; fitAdd(true);
    }).catch(function () { if (my === seq) { addRes.textContent = ''; addRes.appendChild(el('div', 'lib-anone', T.failed)); } });
  }
  // ★ fix62 찾은 목록이 창 아래로 넘어가 창 전체를 내려야 하던 것 → 목록 높이를 남은 화면에 맞추고 '책 추가'를 창 위쪽으로
  // ★ fix64 창 틀이 바뀜 — 스크롤은 본문(#libEditBody)만, 맨 위엔 도구줄이 붙어 있음
  var addHead = document.getElementById('libAddHead'), stick = modal.querySelector('.lib-vl-stick'), head = modal.querySelector('.lib-modal-head'), foot = modal.querySelector('.lib-modal-foot');
  function fitAdd(scroll) {
    if (addRes.hidden || !modal.classList.contains('open') || paneBooks.hidden) return;
    var bcs = getComputedStyle(body), padT = parseFloat(bcs.paddingTop) || 0, padB = parseFloat(bcs.paddingBottom) || 0;
    var sh = stick ? stick.offsetHeight - padT : 0;
    var other = sh + addHead.offsetHeight + addQ.parentNode.offsetHeight + (addBar.hidden ? 0 : addBar.offsetHeight + 8) + 6 + 16 + 8;
    // ★ fix65 창이 내용만큼 커지므로 '지금 본문 높이'가 아니라 '본문이 될 수 있는 가장 큰 높이'(화면 − 위 제목·탭 − 아래 저장 줄)로 잼
    var full = window.matchMedia && window.matchMedia('(max-width: 576px), (max-height: 480px)').matches;
    var vh = window.visualViewport ? window.visualViewport.height : window.innerHeight;
    var bodyMax = (full ? vh : Math.min(vh - 48, 960)) - head.offsetHeight - foot.offsetHeight;
    var room = bodyMax - padT - padB - other;
    addRes.style.maxHeight = Math.max(bodyMax < 360 ? 90 : 120, Math.min(420, Math.floor(room))) + 'px'; // 아주 낮은 화면(폰 가로)은 90px 부터
    if (scroll) {
      // 붙은 도구줄은 본문 맨 위(top)부터 그 높이만큼 — 붙기 전 자리(더 아래)로 재면 덜 내려갔음
      var d = addHead.getBoundingClientRect().top - body.getBoundingClientRect().top - (stick ? stick.offsetHeight : padT) - 4;
      if (Math.abs(d) > 2) body.scrollTop += d;
    }
  }
  window.addEventListener('resize', function () { fitAdd(false); });
  // ★ fix67 '고른 권의 중복으로' 권 목록 — 목록이 바뀔 때마다(render) 다시 채우고 고른 권은 그대로
  var addVol = document.getElementById('libAddVol');
  function fillAddVol() {
    if (!addVol) return;
    var keep = addVol.value; addVol.textContent = '';
    groups().forEach(function (g) {
      if (!g.rows.some(isLive) || !joinable(g)) return; // ★ fix68 넣을 수 있는 권(번호만으로 된 권)만
      var o = document.createElement('option'); o.value = 'g:' + g.k; o.textContent = g.t; addVol.appendChild(o); // 글자로만(파일 이름에서 온 권 이름)
    });
    for (var i = 0; i < addVol.options.length; i++) if (addVol.options[i].value === keep) { addVol.value = keep; break; } // ★ fix68 값 비교로(선택자에 넣지 않음 — 따옴표 등이 든 이름에도 안전)
    addVol.disabled = !addVol.options.length;
  }
  addVol.addEventListener('change', function () { var r = modal.querySelector('input[name="libAddMode"][value="pick"]'); if (r && !r.checked) { r.checked = true; r.dispatchEvent(new Event('change')); } });
  modal.querySelectorAll('input[name="libAddMode"]').forEach(function (x) { x.addEventListener('change', function () {
    var d = document.getElementById('libAddModeD'), sm = x.closest('label').querySelector('small'); if (d && sm) d.textContent = sm.textContent; }); });
  document.getElementById('libAddFind').addEventListener('click', find);
  addQ.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); find(); } });
  document.getElementById('libAddBtn').addEventListener('click', function () {
    var c = addRes.querySelectorAll('.lib-aisel:checked');
    if (!c.length) { msg.textContent = T.add_need; return; }
    var mode = (modal.querySelector('input[name="libAddMode"]:checked') || {}).value || 'num';
    var next = maxNo();
    var tg = null;
    if (mode === 'pick') { var pk = addVol.value.slice(2); tg = groups().filter(function (g) { return g.k === pk; })[0] || null; if (!tg) { msg.textContent = T.add_pick_need; addVol.focus(); return; } }
    for (var i = 0; i < c.length; i++) {
      var it = c[i]._it; if (!it) continue;
      var no = (it.vno === null || it.vno === undefined) ? null : Number(it.vno), label = it.vlabel || '';
      var r = { id: Number(it.id), no: no, label: label, fn: it.filename, folder: it.folder || '', man: 1, x: it.x ? 1 : 0, sub: it.sub || '', g: 'add' + it.id, gt: it.vol || it.filename, so: no === null ? 1e15 : no, o: { no: no, label: label }, op: 'add', to: '', custom: false, oi: 1e6 + ROWS.length };
      // ★ fix63 번호 없는 책은 '권 번호대로'여도 새 권으로(다음 번호) — 예전엔 창에선 '번호 없음'으로 따로 보이다가 저장하면 번호 없는 본편(1권)의 중복으로 묶였음
      if (mode === 'pick' && tg) { r.no = (tg.no === undefined) ? null : tg.no; r.label = tg.label || fmt(r.no); } // ★ fix67 고른 권의 중복으로(그 권 줄의 '…에 넣기'와 같은 방법)
      else if (mode === 'append' || (r.no === null && !r.label)) { next += 1; r.no = next; r.label = fmt(next); }
      ROWS.push(r);
      var lb = c[i].closest('label'); if (lb) lb.remove();
    }
    addRes.querySelectorAll('.lib-agbox').forEach(function (b) { if (!b.querySelector('.lib-aisel')) { var h = b.previousElementSibling; if (h) h.remove(); b.remove(); } });
    if (!addRes.querySelector('.lib-aisel')) { addRes.hidden = true; addBar.hidden = true; }
    msg.textContent = ''; addCount(); render();
    var first = list.querySelector('.lib-vl-r.is-add'); if (first && first.scrollIntoView) first.scrollIntoView({ block: 'nearest' });
  });

  // ★ fix69 처음에도 깨끗한 상태로(브라우저가 새로 고침 때 입력값을 되살리는 경우 대비) · 뒤로/앞으로로 돌아온 페이지(bfcache)에 창이 열려 있으면 닫고 처음 상태로
  resetAll();
  window.addEventListener('pageshow', function (e) { if (e.persisted && modal.classList.contains('open')) close(true); });
})();
</script>
