<?php
/**
 * myComix 즐겨찾기 책장
 * ★ (2026-10-29) 즐겨찾기 항목을 썸네일 그리드(책장) / 목록(썸네일 + 제목)으로 한눈에 보고 바로 이동
 *
 * - 데이터: 사용자별 즐겨찾기 파일(get_favorites_file()) — 읽기만 함(형식 변경 없음)
 * - 이동 규칙: index.php 즐겨찾기 목록과 동일(압축=뷰어, epub/txt=전용 뷰어, 그 외=폴더 목록)
 * - 썸네일: 기존 thumb.php (목록 카드와 같은 규칙, 경로 검증은 thumb.php가 수행)
 * - 삭제: 기존 bookmark.php?mode=delete_favorite (CSRF 토큰 필수, 삭제 전용이라 토글처럼 다시 추가될 위험 없음)
 * - 보기 방식/정렬은 브라우저(localStorage)에만 저장, 실패해도 기본값으로 동작
 */

require_once __DIR__ . "/bootstrap.php";
handle_timeout_popup();  // ✅ 자동 로그아웃 메시지 처리 (다른 페이지와 동일)

$bidx = init_bidx();
$_branding = load_branding();

// 즐겨찾기 로드 (index.php와 같은 방식: 파일 순서 = 추가 순서)
$favorites_arr = [];
$_favorites_file = get_favorites_file();
if (is_file($_favorites_file)) {
    $favorites_arr = function_exists('load_json_with_lock') ? load_json_with_lock($_favorites_file) : json_decode((string)@file_get_contents($_favorites_file), true); // ★ (2026-11-02 09:14) 공유 잠금으로
    if (!is_array($favorites_arr)) $favorites_arr = [];
}
$_fav_max = (int)get_app_settings('max_favorites', 50);
// ★ (2026-10-31) 책장 한 페이지 개수(관리자 설정, 0 = 나누지 않음) — 정렬·삭제가 화면(JS)에서 이뤄지므로 페이지도 화면에서 나눔(전체 항목은 그대로 받음)
$_fav_per = max(0, min(500, (int)get_app_settings('fav_per_page', 30)));

// 실제 파일 존재 확인 (index.php mc_entry_exists와 같은 규칙: base를 모르면 존재로 간주 → 오탐 방지)
function fav_entry_exists($rel_path, $entry_bidx) {
    global $base_dirs;
    $rel = trim(str_replace('\\', '/', (string)$rel_path), '/');
    if ($rel === '') return true;
    $base = $base_dirs[(int)$entry_bidx] ?? null;
    if (!$base) return true;
    return @file_exists(rtrim($base, '/\\') . '/' . $rel);
}

$_archive_exts = ['zip', 'cbz', 'rar', 'cbr', '7z', 'cb7'];
$items = [];
$_no = 0;
foreach ($favorites_arr as $file_path => $fav_data) {
    if (!is_string($file_path) || $file_path === '') continue;
    $_no++;
    $fav_bidx = (int)(is_array($fav_data) ? ($fav_data['bidx'] ?? 0) : 0);
    // ★ (2026-11-06 00:50) 같은 경로를 다른 콘텐츠 폴더에서 넣은 즐겨찾기는 키가 "bidx|/경로" — 경로만 씀(bootstrap.php mc_rec_split)
    [$__kb, $file_path] = mc_rec_split($file_path, $fav_data);
    if (!is_array($fav_data) || !isset($fav_data['bidx'])) $fav_bidx = $__kb;
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    if (in_array($ext, $_archive_exts, true)) {
        $url = "viewer.php?mode=toon&file=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        $kind = 'archive';
    } elseif ($ext === 'epub') {
        $url = "epub_viewer.php?file=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        $kind = 'epub';
    } elseif ($ext === 'txt') {
        $url = "txt_viewer.php?file=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        $kind = 'txt';
    } elseif ($ext === 'hwp' || $ext === 'hwpx') {
        // ★ (2026-11-02 14:33) HWP·HWPX 카드에도 별이 있는데 폴더로 보고 index.php?dir=파일 로 가서 빈 화면이던 것
        $url = "hwp_viewer.php?file=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        $kind = 'txt';
    } elseif ($ext === 'pdf' || (function_exists('is_video_file') && is_video_file($file_path))) {
        // ★ (2026-11-02 09:57) PDF·동영상은 뷰어로(목록 화면 카드의 별로 넣을 수 있음) — 전에는 index.php?dir=파일 로 가서 빈 화면
        $url = "viewer.php?" . ($ext === 'pdf' ? "filetype=pdf&" : "filetype=video&") . "file=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        $kind = 'archive';
    } else {
        $url = "index.php?dir=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        $kind = 'folder';
    }
    $parts = explode('/', trim(str_replace('\\', '/', $file_path), '/'));
    $name = end($parts);
    $parent = count($parts) > 1 ? implode('/', array_slice($parts, 0, -1)) : '';
    $items[] = [
        'no'      => $_no,
        'path'    => $file_path,
        'enc'     => encode_url($file_path),
        'bidx'    => $fav_bidx,
        'url'     => $url,
        'thumb'   => "thumb.php?file=" . encode_url($file_path) . "&bidx=" . $fav_bidx,
        'title'   => mark_display_title($name),   // 이미 HTML 이스케이프됨
        'parent'  => $parent,
        'kind'    => $kind,
        'added'   => (int)(is_array($fav_data) ? ($fav_data['added_at'] ?? 0) : 0),
        'missing' => !fav_entry_exists($file_path, $fav_bidx),
    ];
}

$csrf_token = generate_csrf_token();
$_kind_label = ['archive' => '📦', 'epub' => '📖', 'txt' => '📝', 'folder' => '📁'];
?>
<!DOCTYPE html>
<html lang="<?php echo get_html_lang(); ?>">
<head>
<?php if (function_exists('mc_theme_early_head')) echo mc_theme_early_head(); /* ★ (2026-11-08 fix80) 다크모드 흰 바탕 깜빡임 — 첫 그림부터 어둡게 */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo __h('fav_shelf_title'); ?> - <?php echo h($_branding['page_title'] ?? 'myComix'); ?></title>
<?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
<link rel="stylesheet" href="./css/bootstrap.min.css">
<?php if (isset($darkmode_settings) && ($darkmode_settings['enabled'] ?? false)): ?>
<link rel="stylesheet" href="./css/darkmode.css?v=<?php echo @filemtime(__DIR__ . '/css/darkmode.css') ?: '1'; ?>">
<?php endif; ?>
<style>
/* ★ (2026-10-31) 제목 별 */
.fav-h1 .fav-h1-star { color:#f59f00; vertical-align:-3px; margin-right:2px; }
.dark-mode .fav-h1 .fav-h1-star, [data-theme="dark"] .fav-h1 .fav-h1-star { color:#fbbf24; }
  body { background:#f5f3ef; }
  .fav-wrap { max-width:1200px; margin:0 auto; padding:10px 12px 40px; }
  .fav-head { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:12px; }
  .fav-head h1 { font-size:1.3rem; margin:0; flex:1 1 auto; white-space:nowrap; }
  .fav-head .fav-count { font-size:.85rem; color:#888; margin-left:6px; font-weight:normal; }
  .fav-tools { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
  .fav-tools select { height:32px; font-size:.85rem; padding:0 6px; border-radius:4px; border:1px solid #ccc; background:#fff; }
  /* ★ (2026-10-30) 모바일 세로(576px 이하): 1줄 돌아가기 + 제목(+개수), 2줄 정렬(남는 폭 꽉) + 보기 전환(오른쪽) — 더 넓은 화면은 그대로 */
  @media (max-width:576px) {
    .fav-head { gap:6px; }
    .fav-head h1 { font-size:1.15rem; flex:1 1 0; min-width:0; white-space:normal; }
    .fav-head .fav-count { white-space:nowrap; }
    .fav-tools { flex:1 1 100%; flex-wrap:nowrap; }
    .fav-tools select { flex:1 1 auto; min-width:0; }
  }
  @media (max-width:360px) { .fav-head .fav-back-lbl { display:none; } .fav-head h1 { font-size:1.05rem; } } /* 아주 좁은 화면: 돌아가기는 ← 만(제목·개수가 한 줄로) */
  /* 보기 전환 버튼 — 목록 화면(index.php)과 같은 모양: 현재 보기만 회색 배경, 클릭 후 포커스 그림자 없음 */
  .fav-view-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; padding:0;
                  border:1px solid #dcdcdc; border-radius:7px; background:#fff; color:#555; cursor:pointer; line-height:1;
                  box-shadow:none; outline:none; transition:background-color .12s ease, border-color .12s ease; }
  .fav-view-btn:hover { background:#f3f3f3; }
  .fav-view-btn.active { background:#e9e9e9; border-color:#d0d0d0; color:#333; }
  .fav-view-btn:focus { outline:none; box-shadow:none; }
  .fav-view-btn:focus-visible { outline:2px solid #80bdff; outline-offset:1px; }
  .dark-mode .fav-view-btn, [data-theme="dark"] .fav-view-btn { background:#1e1e1e; border-color:#444; color:#bbb; }
  .dark-mode .fav-view-btn.active, [data-theme="dark"] .fav-view-btn.active { background:#3a3a3a; border-color:#555; color:#eee; }

  /* ★ (2026-10-29) 나무 책장 — 뒤판·테두리·선반(그리드는 한 줄 전체에 선반, 목록은 한 줄마다 선반). 이미지 파일 없이 CSS 그라데이션 */
  #favShelf { --fav-gx:14px; position:relative; padding:18px 16px 10px; border-radius:8px; border:10px solid #4a2810;
              background-color:#5e3618;
              background-image:
                linear-gradient(90deg, rgba(0,0,0,.28), rgba(0,0,0,0) 5%, rgba(0,0,0,0) 95%, rgba(0,0,0,.28)),
                repeating-linear-gradient(90deg, rgba(255,255,255,.035) 0 2px, rgba(0,0,0,.05) 2px 5px, rgba(255,255,255,.02) 5px 11px, rgba(0,0,0,.06) 11px 13px, rgba(255,255,255,.03) 13px 21px),
                linear-gradient(#7a4a22, #5a3316);
              box-shadow:inset 0 0 22px rgba(0,0,0,.6), 0 4px 14px rgba(0,0,0,.35); }
  .fav-shelf-plank { height:14px; border-radius:2px; background:linear-gradient(#cf9459 0, #ad6e3c 35%, #7a4722 100%);
                     box-shadow:0 7px 9px -3px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.35); }

  /* 그리드(책장) */
  .fav-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:22px var(--fav-gx); }
  #favShelf.fav-grid { padding-left:calc(var(--fav-gx) / 2); padding-right:calc(var(--fav-gx) / 2); } /* 선반이 좌우 테두리까지 닿게 */
  @media (max-width:576px) { #favShelf { --fav-gx:10px; padding:14px 10px 8px; border-width:7px; } .fav-grid { grid-template-columns:repeat(3, 1fr); gap:18px var(--fav-gx); } }
  .fav-grid .fav-item { position:relative; display:flex; flex-direction:column; min-width:0; }
  .fav-grid .fav-link { display:block; color:inherit; text-decoration:none; }
  .fav-grid .fav-cover { position:relative; width:100%; aspect-ratio:3/4; background:#3b2412; border-radius:2px 3px 3px 2px;
                         overflow:hidden; box-shadow:4px 0 6px -2px rgba(0,0,0,.55), 0 2px 3px rgba(0,0,0,.4); }
  .fav-grid .fav-cover::after { content:''; position:absolute; inset:0; pointer-events:none;
                                background:linear-gradient(90deg, rgba(0,0,0,.35) 0, rgba(255,255,255,.18) 3%, rgba(255,255,255,0) 7%, rgba(0,0,0,0) 92%, rgba(0,0,0,.18) 100%); }
  .fav-grid .fav-cover img { width:100%; height:100%; object-fit:cover; display:block; }
  /* 선반: 칸 사이 간격까지 이어서 한 줄 전체가 하나의 선반처럼 */
  .fav-grid .fav-shelf { height:14px; margin:0 calc(var(--fav-gx) / -2 - 1px); border-radius:0; background:linear-gradient(#cf9459 0, #ad6e3c 35%, #7a4722 100%);
                         box-shadow:0 7px 9px -3px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.35); }
  .fav-grid .fav-title { font-size:.8rem; line-height:1.3; margin-top:8px; color:#f6e7d1; text-shadow:0 1px 2px rgba(0,0,0,.8);
                         word-break:keep-all; overflow-wrap:anywhere; } /* 제목 전체 표시 */
  .fav-grid .fav-del { position:absolute; top:4px; right:4px; z-index:2; }
  .fav-grid .fav-kind { position:absolute; left:4px; bottom:4px; z-index:1; font-size:.8rem; background:rgba(0,0,0,.55);
                        color:#fff; border-radius:3px; padding:0 4px; }
  .fav-grid .fav-kind { display:none; } /* ★ (2026-10-30) 표지 위 종류 배지(압축·폴더 등) 숨김 — 펜닐 요청 */
  .fav-grid .fav-filler { display:flex; flex-direction:column; pointer-events:none; }
  .fav-grid .fav-filler .fav-cover { visibility:hidden; box-shadow:none; }
  .fav-list .fav-filler { display:none; }

  /* 목록 — 한 줄마다 선반 위에 책 */
  .fav-list .fav-item { position:relative; display:flex; align-items:flex-start; gap:10px; padding:10px 6px 22px; }
  .fav-list .fav-item::after { content:''; position:absolute; left:-16px; right:-16px; bottom:4px; height:14px;
                               background:linear-gradient(#cf9459 0, #ad6e3c 35%, #7a4722 100%);
                               box-shadow:0 7px 9px -3px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.35); }
  @media (max-width:576px) { .fav-list .fav-item::after { left:-10px; right:-10px; } }
  .fav-list .fav-link { display:flex; align-items:flex-start; gap:12px; flex:1 1 auto; min-width:0; color:inherit; text-decoration:none; } /* ★ 제목은 썸네일 맨 위 기준 */
  .fav-list .fav-cover { position:relative; z-index:1; flex:0 0 72px; width:72px; height:96px; background:#3b2412; border-radius:2px 3px 3px 2px; overflow:hidden;
                         box-shadow:4px 0 6px -2px rgba(0,0,0,.55), 0 2px 3px rgba(0,0,0,.4); }
  .fav-list .fav-cover::after { content:''; position:absolute; inset:0; pointer-events:none;
                                background:linear-gradient(90deg, rgba(0,0,0,.35) 0, rgba(255,255,255,.18) 4%, rgba(255,255,255,0) 9%); }
  @media (max-width:576px) { .fav-list .fav-cover { flex-basis:60px; width:60px; height:80px; } }
  .fav-list .fav-cover img { width:100%; height:100%; object-fit:cover; display:block; }
  .fav-list .fav-shelf { display:none; }
  .fav-list .fav-text { min-width:0; flex:1 1 auto; padding-top:2px; }
  .fav-list .fav-title { font-size:1.05rem; line-height:1.35; color:#f6e7d1; text-shadow:0 1px 2px rgba(0,0,0,.8); word-break:keep-all; overflow-wrap:anywhere; }
  .fav-list .fav-sub { font-size:.75rem; color:#d9c2a3; margin-top:2px; overflow-wrap:anywhere; }
  .fav-grid .fav-sub { display:none; }
  .fav-list .fav-kind { display:none; }
  .fav-list .fav-del { position:relative; z-index:1; flex:0 0 auto; align-self:flex-start; margin-top:2px; }

  .fav-del { border:none; background:rgba(0,0,0,.55); color:#fff; width:24px; height:24px; border-radius:50%;
             font-size:13px; line-height:24px; padding:0; text-align:center; cursor:pointer; }
  .fav-list .fav-del { background:rgba(0,0,0,.35); color:#ffb3a7; font-size:14px; }
  .fav-del:disabled { opacity:.4; }

  .fav-item.is-missing .fav-cover img { filter:grayscale(1); opacity:.45; }
  .fav-item.is-missing .fav-link { cursor:default; }
  .fav-miss-badge { display:inline-block; font-size:.7rem; background:#e74c3c; color:#fff; border-radius:3px; padding:0 4px; margin-left:4px; vertical-align:middle; }
  .fav-empty { text-align:center; color:#999; padding:60px 10px; }
  /* ★ (2026-10-31) 페이지 나누기 — 지금 페이지가 아닌 항목은 숨김, 아래 페이지 번호(라이브러리 목록과 같은 모양) */
  #favShelf .fav-item.fav-off { display:none !important; }
  .fav-pager { display:flex; gap:4px; justify-content:center; flex-wrap:wrap; margin:18px 0 6px; }
  .fav-pager a, .fav-pager span { min-width:34px; padding:5px 10px; border-radius:4px; border:1px solid #ccc; text-align:center;
                                  text-decoration:none !important; color:#333 !important; background:#fff; font-size:.85rem; line-height:1.3; }
  .fav-pager a:hover { background:#f0f0f0; }
  .fav-pager span.cur { background:#343a40; color:#fff !important; border-color:#343a40; }
  .fav-pager span.gap { border-color:transparent; background:transparent; min-width:0; padding:5px 2px; }
  .dark-mode .fav-pager a, [data-theme="dark"] .fav-pager a { background:#1e1e1e; border-color:#444; color:#ddd !important; }
  .dark-mode .fav-pager a:hover, [data-theme="dark"] .fav-pager a:hover { background:#2a2a2a; }
  .dark-mode .fav-pager span.cur, [data-theme="dark"] .fav-pager span.cur { background:#e0e0e0; color:#111 !important; border-color:#e0e0e0; }

  /* 다크모드 (darkmode.js가 html/body에 .dark-mode / data-theme="dark" 설정) */
  .dark-mode body, [data-theme="dark"] body, body.dark-mode { background:#121212 !important; }
  .dark-mode #favShelf, [data-theme="dark"] #favShelf { background-color:#2e1b0c; border-color:#24140a;
      background-image:linear-gradient(90deg, rgba(0,0,0,.35), rgba(0,0,0,0) 5%, rgba(0,0,0,0) 95%, rgba(0,0,0,.35)),
        repeating-linear-gradient(90deg, rgba(255,255,255,.025) 0 2px, rgba(0,0,0,.06) 2px 5px, rgba(255,255,255,.015) 5px 11px, rgba(0,0,0,.07) 11px 13px),
        linear-gradient(#3d2410, #2a180a); }
  .dark-mode .fav-grid .fav-shelf, [data-theme="dark"] .fav-grid .fav-shelf,
  .dark-mode .fav-list .fav-item::after, [data-theme="dark"] .fav-list .fav-item::after { background:linear-gradient(#8a5a33 0, #6b4022 35%, #45280f 100%); }
  .dark-mode .fav-tools select, [data-theme="dark"] .fav-tools select { background:#1e1e1e; color:#e0e0e0; border-color:#444; }
</style>
<?php // ★ (2026-10-29) 관리자 설정 '썸네일 크기' — 값이 있을 때만 출력(없으면 기존 화면 그대로). 정수 px만 들어감
$_mc_thumb_css = function_exists('mc_thumb_layout_css') ? mc_thumb_layout_css('shelf') : '';
if ($_mc_thumb_css !== ''): ?>
<style id="mcThumbLayout">
<?php echo $_mc_thumb_css; ?>
</style>
<?php endif; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-11-05 23:15) 아이콘 변환을 본문보다 먼저 시작 — 예전 이모지가 잠깐 보였다가 바뀌던 것(페이지 끝의 같은 스크립트는 다시 실행되지 않음) */ ?>
</head>
<body>
<div class="fav-wrap">
  <div class="fav-head">
    <a id="favBack" class="btn btn-sm btn-outline-secondary" href="index.php<?php echo $bidx_query; ?>" title="<?php echo __h('fav_shelf_back'); ?>" aria-label="<?php echo __h('fav_shelf_back'); ?>">← <span class="fav-back-lbl"><?php echo __h('fav_shelf_back'); ?></span></a>
    <h1 class="fav-h1"><?php // ★ (2026-10-31) '즐겨찾기' — 노란 선 별(예전 📚 책장 — '서재'와 헷갈림) ?><svg class="mc-ico fav-h1-star" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg> <?php echo __h('fav_shelf_title'); ?><span class="fav-count">⭐ <span id="favShelfCount"><?php echo count($items); ?></span>/<?php echo $_fav_max; ?></span></h1>
    <div class="fav-tools">
      <select id="favSort" aria-label="<?php echo __h('fav_shelf_sort'); ?>">
        <option value="added"><?php echo __h('fav_shelf_sort_added'); ?></option>
        <option value="recent"><?php echo __h('fav_shelf_sort_recent'); ?></option>
        <option value="name"><?php echo __h('fav_shelf_sort_name'); ?></option>
      </select>
      <button type="button" class="fav-view-btn" data-view="grid" title="<?php echo __h('fav_shelf_view_grid'); ?>" aria-label="<?php echo __h('fav_shelf_view_grid'); ?>"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/></svg></button>
      <button type="button" class="fav-view-btn" data-view="list" title="<?php echo __h('fav_shelf_view_list'); ?>" aria-label="<?php echo __h('fav_shelf_view_list'); ?>"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg></button>
    </div>
  </div>
<?php echo mc_shelf_nav('fav', $bidx_query); // ★ (2026-11-03 01:31) 서재·즐겨찾기·북마크 이동 ?>

<?php if (empty($items)): ?>
  <div class="fav-empty"><?php echo __h('fav_shelf_empty'); ?></div>
<?php else: ?>
  <div id="favShelf" class="fav-grid">
<?php foreach ($items as $it): ?>
    <div class="fav-item<?php echo $it['missing'] ? ' is-missing' : ''; ?>"
         data-no="<?php echo (int)$it['no']; ?>"
         data-added="<?php echo (int)$it['added']; ?>"
         data-name="<?php echo $it['title']; ?>">
      <a class="fav-link" <?php echo $it['missing'] ? 'href="javascript:void(0)" aria-disabled="true"' : 'href="' . h($it['url']) . '"'; ?> title="<?php echo $it['title']; ?>">
        <div class="fav-cover">
          <img src="<?php echo h($it['thumb']); ?>" alt="" loading="lazy" decoding="async"
               onerror="this.onerror=null;this.style.visibility='hidden';">
          <span class="fav-kind"><?php echo $_kind_label[$it['kind']] ?? ''; ?></span>
        </div>
        <div class="fav-shelf"></div>
        <div class="fav-text">
          <div class="fav-title"><?php echo $it['title']; ?><?php if ($it['missing']): ?><span class="fav-miss-badge"><?php echo __h('fav_shelf_missing'); ?></span><?php endif; ?></div>
          <div class="fav-sub"><?php echo h($it['parent'] !== '' ? '/' . $it['parent'] : '/'); /* ★ (2026-10-31) 종류 아이콘 빼고 폴더 경로만 — 펜닐 요청 */ ?></div>
        </div>
      </a>
      <button type="button" class="fav-del" title="<?php echo __h('ui_unfavorite'); ?>"
              data-file="<?php echo h($it['enc']); ?>" data-path="<?php echo h($it['path']); ?>" data-bidx="<?php echo (int)$it['bidx']; ?>">✕</button>
    </div>
<?php endforeach; ?>
  </div>
  <nav id="favPager" class="fav-pager" aria-label="<?php echo __h('fav_shelf_pages'); ?>" style="display:none;"></nav>
  <div id="favEmptyAfter" class="fav-empty" style="display:none;"><?php echo __h('fav_shelf_empty'); ?></div>
<?php endif; ?>
</div>

<script>
(function () {
  var shelf = document.getElementById('favShelf');
  var sortSel = document.getElementById('favSort');
  var viewBtns = document.querySelectorAll('.fav-view-btn');
  var CSRF = <?php echo json_encode($csrf_token); ?>;
  <?php // ★ (2026-10-29) 관리자 '책장 그리드 표지 폭'(없으면 0) — 한 줄을 꽉 채울 칸 수 계산용
  $_fav_fit = ['bp' => 768, 'pc' => 0, 'mo' => 0];
  if (function_exists('mc_thumb_layout_sanitize') && function_exists('get_app_settings')) {
      $_tl = mc_thumb_layout_sanitize(get_app_settings('thumb_layout', []));
      $_fav_fit = ['bp' => (int)$_tl['breakpoint'], 'pc' => (int)$_tl['shelf_grid_pc'], 'mo' => (int)$_tl['shelf_grid_mo']];
  } ?>
  var FIT = <?php echo json_encode($_fav_fit); ?>;
  var PER = <?php echo (int)$_fav_per; ?>; // ★ (2026-10-31) 한 페이지 개수(0 = 나누지 않음)
  var MSG_PREV = <?php echo json_encode(__('fav_shelf_prev'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var MSG_NEXT = <?php echo json_encode(__('fav_shelf_next'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var MSG_CONFIRM = <?php echo json_encode(__('fav_shelf_confirm_remove'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var MSG_FAIL = <?php echo json_encode(__('fav_shelf_remove_fail'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var MSG_STILL = <?php echo json_encode(__('fav_shelf_remove_still'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  function getPref(k, def) { try { var v = localStorage.getItem(k); return v === null ? def : v; } catch (e) { return def; } }
  function setPref(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  // 보기 방식 (그리드/목록)
  function applyView(v) {
    if (v !== 'list') v = 'grid';
    if (shelf) shelf.className = (v === 'list') ? 'fav-list' : 'fav-grid';
    for (var i = 0; i < viewBtns.length; i++) {
      var on = viewBtns[i].getAttribute('data-view') === v;
      viewBtns[i].classList.toggle('active', on);
      viewBtns[i].setAttribute('aria-pressed', on ? 'true' : 'false');
    }
  }
  for (var i = 0; i < viewBtns.length; i++) {
    viewBtns[i].addEventListener('click', function () {
      var v = this.getAttribute('data-view');
      applyView(v); setPref('mcFavShelfView', v); fillShelf();
      try { this.blur(); } catch (e) {} // 클릭 후 포커스 표시가 남지 않게
    });
  }
  applyView(getPref('mcFavShelfView', 'grid'));

  // ★ (2026-10-29) 그리드 마지막 줄이 덜 차도 선반이 끝까지 이어지게 빈 칸(.fav-filler) 채움 — .fav-item 이 아니라 정렬·개수·삭제 계산에 들어가지 않음
  // ★ (2026-10-29) 관리자가 표지 폭을 정했을 때: 칸 폭 고정이면 남는 폭이 오른쪽 여백으로 남음 → 설정 폭에 가장 가까운 칸 수로 한 줄을 꽉 채움
  //   (표지는 설정 폭 ± 반 칸 정도. 설정이 없으면 기본 CSS 가 이미 꽉 채우므로 그대로)
  function fitCols() {
    if (!shelf) return;
    shelf.style.removeProperty('grid-template-columns');
    if (shelf.className !== 'fav-grid') return;
    var w = 0;
    try { w = window.matchMedia('(min-width: ' + FIT.bp + 'px)').matches ? FIT.pc : FIT.mo; } catch (e) { w = 0; }
    if (!(w > 0)) return;
    var cs = getComputedStyle(shelf);
    var avail = shelf.clientWidth - (parseFloat(cs.paddingLeft) || 0) - (parseFloat(cs.paddingRight) || 0);
    var gap = parseFloat(cs.columnGap) || 0;
    if (!(avail > 0)) return;
    var n = Math.max(1, Math.round((avail + gap) / (w + gap)));
    shelf.style.setProperty('grid-template-columns', 'repeat(' + n + ', minmax(0, 1fr))', 'important');
  }
  function fillShelf() {
    if (!shelf) return;
    fitCols();
    var old = shelf.querySelectorAll('.fav-filler');
    for (var i = 0; i < old.length; i++) old[i].parentNode.removeChild(old[i]);
    if (shelf.className !== 'fav-grid') return;
    var n = shelf.querySelectorAll('.fav-item:not(.fav-off)').length; // ★ (2026-10-31) 지금 페이지에 보이는 항목만
    if (!n) return;
    var cols = 0;
    try { cols = getComputedStyle(shelf).gridTemplateColumns.split(' ').filter(function (x) { return x && x !== '0px'; }).length; } catch (e) { cols = 0; }
    if (cols < 2) return;
    var rem = n % cols;
    if (!rem) return;
    for (var k = 0; k < cols - rem; k++) {
      var f = document.createElement('div');
      f.className = 'fav-filler'; f.setAttribute('aria-hidden', 'true');
      f.innerHTML = '<div class="fav-cover"></div><div class="fav-shelf"></div>';
      shelf.appendChild(f);
    }
  }
  var _fillT = null;
  window.addEventListener('resize', function () { clearTimeout(_fillT); _fillT = setTimeout(fillShelf, 150); }, { passive: true });

  // ★ (2026-10-31) 페이지 나누기 — 정렬된 순서대로 PER 개씩, 주소의 ?page=N 을 기억(뒤로가기로 돌아와도 같은 페이지)
  var pager = document.getElementById('favPager');
  var curPage = 1;
  try { curPage = Math.max(1, parseInt(new URLSearchParams(location.search).get('page'), 10) || 1); } catch (e) { curPage = 1; }
  function pageUrl(p) {
    try { var u = new URL(location.href); if (p > 1) u.searchParams.set('page', String(p)); else u.searchParams.delete('page'); return u.pathname + u.search; }
    catch (e) { return '?page=' + p; }
  }
  function paginate() {
    if (!shelf) return;
    var items = shelf.querySelectorAll('.fav-item');
    var n = items.length, per = PER > 0 ? PER : n;
    var pages = Math.max(1, Math.ceil(n / Math.max(1, per)));
    if (curPage > pages) curPage = pages;
    var from = (curPage - 1) * per, to = from + per;
    for (var i = 0; i < n; i++) items[i].classList.toggle('fav-off', PER > 0 && (i < from || i >= to));
    try { history.replaceState(history.state, '', pageUrl(curPage) + (/^#ly\d+$/.test(location.hash) ? location.hash : '')); } catch (e) {} // ★ (2026-11-02 17:17) 사생활 보호 복귀 위치(#ly)는 남겨 둠(아래 위치 기억이 읽고 지움)
    if (!pager) return;
    pager.innerHTML = '';
    if (PER <= 0 || pages <= 1) { pager.style.display = 'none'; return; }
    pager.style.display = '';
    function add(p, label, aria) {
      var el;
      if (p === curPage && !aria) { el = document.createElement('span'); el.className = 'cur'; el.setAttribute('aria-current', 'page'); }
      else { el = document.createElement('a'); el.href = pageUrl(p); el.setAttribute('data-p', String(p)); if (aria) el.setAttribute('aria-label', aria); }
      el.textContent = label; pager.appendChild(el);
    }
    if (curPage > 1) add(curPage - 1, '‹', MSG_PREV);
    for (var p = 1; p <= pages; p++) {
      if (p !== 1 && p !== pages && Math.abs(p - curPage) > 3) {
        if (p === 2 || p === pages - 1) { var g = document.createElement('span'); g.className = 'gap'; g.textContent = '…'; pager.appendChild(g); }
        continue;
      }
      add(p, String(p));
    }
    if (curPage < pages) add(curPage + 1, '›', MSG_NEXT);
  }
  if (pager) {
    pager.addEventListener('click', function (ev) {
      var a = ev.target && ev.target.closest ? ev.target.closest('a[data-p]') : null;
      if (!a || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button === 1) return; // 새 탭 열기는 그대로
      ev.preventDefault();
      curPage = parseInt(a.getAttribute('data-p'), 10) || 1;
      paginate(); fillShelf();
      try { var top = document.querySelector('.fav-head'); if (top) top.scrollIntoView({ block: 'start' }); } catch (e) {}
    });
  }

  // 정렬 (추가순 / 최근 추가순 / 이름순)
  function applySort(mode) {
    if (!shelf) return;
    var items = Array.prototype.slice.call(shelf.querySelectorAll('.fav-item'));
    items.sort(function (a, b) {
      var na = parseInt(a.getAttribute('data-no'), 10) || 0, nb = parseInt(b.getAttribute('data-no'), 10) || 0;
      if (mode === 'recent') {
        var ta = parseInt(a.getAttribute('data-added'), 10) || 0, tb = parseInt(b.getAttribute('data-added'), 10) || 0;
        return (tb - ta) || (nb - na);
      }
      if (mode === 'name') {
        var c = (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '', undefined, { numeric: true, sensitivity: 'base' });
        return c || (na - nb);
      }
      return na - nb;
    });
    for (var k = 0; k < items.length; k++) shelf.appendChild(items[k]);
    paginate();
    fillShelf();
  }
  if (sortSel) {
    var savedSort = getPref('mcFavShelfSort', 'added');
    if (['added', 'recent', 'name'].indexOf(savedSort) === -1) savedSort = 'added';
    sortSel.value = savedSort;
    applySort(savedSort);
    sortSel.addEventListener('change', function () { curPage = 1; applySort(this.value); setPref('mcFavShelfSort', this.value); }); // 정렬을 바꾸면 1페이지부터
  } else { paginate(); }
  fillShelf();

  // 즐겨찾기 해제 (삭제 전용 API, CSRF 토큰 필수)
  if (shelf) {
    shelf.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('.fav-del') : null;
      if (!btn) return;
      ev.preventDefault(); ev.stopPropagation();
      if (btn.disabled) return;
      var item = btn.closest('.fav-item');
      var title = item ? (item.getAttribute('data-name') || '') : ''; // getAttribute는 이미 원문으로 복원된 값
      var path = btn.getAttribute('data-path') || '';
      if (!window.confirm(MSG_CONFIRM.replace('%s', function () { return title; }) /* ★ (2026-11-02 11:36) 이름의 $& 등을 특수 기호로 읽지 않게 */)) return;
      btn.disabled = true;

      function removeItem() {
        if (item && item.parentNode) item.parentNode.removeChild(item);
        paginate(); // ★ (2026-10-31) 다음 페이지 항목이 앞으로 당겨짐, 마지막 페이지가 비면 그 앞 페이지로
        fillShelf();
        var cnt = document.getElementById('favShelfCount');
        if (cnt) cnt.textContent = String(shelf.querySelectorAll('.fav-item').length);
        if (!shelf.querySelector('.fav-item')) {
          var em = document.getElementById('favEmptyAfter'); if (em) em.style.display = '';
        }
        try { sessionStorage.setItem('mycomix_marks_dirty', '1'); } catch (e) {}
      }
      function fail(msg) { btn.disabled = false; alert(msg); }

      // 삭제 후 서버의 실제 목록으로 확인 — delete_favorite은 키가 없어도 success를 돌려주므로,
      // 경로 해석이 어긋난 경우(이름에 '%' 와 그 뒤 코드처럼 보이는 문자가 함께 있는 등) 화면만 지워지는 것을 방지
      function verifyRemoved() {
        var vx = new XMLHttpRequest();
        vx.open('GET', 'bookmark.php?mode=list_favorites&bidx=' + encodeURIComponent(btn.getAttribute('data-bidx') || '0') + '&_=' + Date.now(), true); // ★ (2026-11-06 00:50) 지운 항목의 콘텐츠 폴더 것만(같은 경로의 다른 콘텐츠 폴더 즐겨찾기가 있으면 '안 지워짐'으로 보였음)
        vx.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        vx.onreadystatechange = function () {
          if (vx.readyState !== 4) return;
          var list = null;
          if (vx.status === 200) { try { var r = JSON.parse(vx.responseText); if (r && r.success && Array.isArray(r.favorites)) list = r.favorites; } catch (e) {} }
          if (list === null) { removeItem(); return; }          // 확인 실패 시: 삭제 요청은 성공했으므로 기존처럼 제거(새로고침하면 실제 상태 표시)
          // h()가 NFC로 정규화하므로 비교도 양쪽 NFC로(맥에서 올린 NFD 한글 이름 대비)
          var nf = function (x) { try { return String(x).normalize('NFC'); } catch (e) { return String(x); } };
          var np = nf(path), still = false;
          for (var q = 0; q < list.length; q++) { if (nf(list[q]) === np) { still = true; break; } }
          if (path && still) { fail(MSG_STILL); return; }
          removeItem();
        };
        vx.send();
      }

      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'bookmark.php?mode=delete_favorite', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        var ok = false;
        if (xhr.status === 200) { try { ok = !!JSON.parse(xhr.responseText).success; } catch (e) {} }
        if (ok) verifyRemoved(); else fail(MSG_FAIL);
      };
      // file 값은 encode_url() 결과 그대로 전송(PHP가 1회 디코드 → decode_file_param이 원래 경로로 복원)
      xhr.send('file=' + btn.getAttribute('data-file') + '&bidx=' + encodeURIComponent(btn.getAttribute('data-bidx') || '0') +
               '&csrf_token=' + encodeURIComponent(CSRF));
    });
  }
})();
</script>
<!-- 맨 위로 버튼: index.php와 같은 모양·위치·동작 -->
<style>
#scrollTopBtn {
  position: fixed;
  bottom: 90px;
  right: 20px;
  z-index: 9999;
  font-size: 18px;
  background-color: rgba(0, 0, 0, 0.3);
  color: white;
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  padding: 0;
  cursor: pointer;
  outline: none;
  transition: opacity 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}
#scrollTopBtn:hover { background-color: rgba(0, 0, 0, 0.5); }
</style>
<button id="scrollTopBtn" title="<?php echo __h('ui_scroll_top'); ?>" style="display:none;">
  ▲
</button>
<script>
(function () {
  var btn = document.getElementById('scrollTopBtn');
  if (btn) {
    window.addEventListener('scroll', function () {
      btn.style.display = (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) ? 'block' : 'none';
    }, { passive: true });
    btn.onclick = function () { window.scrollTo({ top: 0, behavior: 'smooth' }); };
  }
  // 돌아가기: 목록(index.php)에서 들어왔으면 뒤로가기(브라우저가 보관한 페이지를 바로 복원 → 다시 불러오지 않아 빠르고 보던 폴더·스크롤 그대로).
  // 직접 열었거나 다른 곳에서 왔으면 기존처럼 목록 첫 화면으로 이동.
  var back = document.getElementById('favBack');
  if (back) {
    back.addEventListener('click', function (ev) {
      try {
        var ref = document.referrer ? new URL(document.referrer) : null;
        if (ref && ref.origin === location.origin && /\/index\.php$/.test(ref.pathname) && history.length > 1) {
          ev.preventDefault();
          history.back();
        }
      } catch (e) { /* 실패 시 링크 그대로 이동 */ }
    });
  }
})();
</script>
<?php if (function_exists('mc_lib_scroll_script')) echo mc_lib_scroll_script(); /* ★ (2026-11-02 17:17) 뒤로가기·새로고침·다른 탭/앱 갔다 와서도 보던 위치(쪽 번호는 원래 주소에) */ ?>
<?php render_darkmode_script(); ?>
<?php render_auto_logout_script(); // 관리자 설정 '자동 로그아웃 대상'에 favorites.php를 체크한 경우에만 동작 ?>
<?php require_once __DIR__ . '/privacy_shield.php'; // 관리자 설정 '모바일 화면 가림 대상'에 체크한 경우에만 동작 ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
<?php if (function_exists('mc_perf_script')) echo mc_perf_script('favorites'); // ★ (2026-11-03 14:10) 속도 기록(src/perf) ?>
<?php if (function_exists('mc_back_origin_list_js')) echo mc_back_origin_list_js(); /* ★ (2026-11-05) 뷰어 뒤로가기가 이 화면(주소·스크롤 위치)으로 돌아오게 — function.php */ ?>
</body>
</html>
