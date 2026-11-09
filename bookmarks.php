<?php
/**
 * myComix 북마크 화면
 * ★ (2026-10-31) 상단 🔖 버튼 — 예전엔 목록 위로 표가 펼쳐졌음 → 서재·즐겨찾기처럼 따로 화면으로 이동 (펜닐 요청)
 *
 * - 데이터: 사용자별 자동 저장(get_autosave_file()) · 북마크(get_bookmark_file()) — 읽기만 함(형식 변경 없음)
 * - 여는 주소: index.php 의 예전 펼침 표와 똑같은 주소(저장 위치 #imageN · 쪽 순서 · 항목 bidx)
 * - 지우기: 기존 bookmark.php?mode=delete_autosave / delete_bookmark (POST + CSRF 토큰)
 *   지운 뒤 이 화면의 ?mode=keys 로 서버 목록을 다시 받아 정말 지워졌는지 확인(이름의 '%' 등으로 경로가 어긋나면 화면만 지워지는 것 방지)
 * - 보기 방식(그리드/목록)은 브라우저(localStorage)에만 저장, 실패해도 기본값으로 동작
 */

require_once __DIR__ . "/bootstrap.php";
handle_timeout_popup();  // ✅ 자동 로그아웃 메시지 처리 (다른 페이지와 동일)

$bidx = init_bidx();
$_branding = load_branding();

function bm_load_json_file($f) {
    if (!is_file($f)) return [];
    if (function_exists('load_json_with_lock')) return load_json_with_lock($f); // ★ (2026-11-02 09:14) 저장(비우고 쓰기) 중에 읽어 목록이 빈 화면으로 보이던 것
    $a = json_decode((string)@file_get_contents($f), true);
    return is_array($a) ? $a : [];
}
$autosave_arr = bm_load_json_file(get_autosave_file());
$bookmark_arr = bm_load_json_file(get_bookmark_file());
/** ★ (2026-11-06 00:50) 지운 뒤 확인용 목록 — "콘텐츠 폴더 번호|경로"(같은 경로라도 콘텐츠 폴더가 다르면 다른 항목 · 아래 화면 스크립트가 같은 형식으로 비교) */
function bm_keys_list(array $arr) {
    $out = [];
    foreach ($arr as $k => $v) {
        [$b, $p] = mc_rec_split($k, $v);
        if (!is_array($v)) $b = -1; // 옛 문자열 형식(콘텐츠 폴더 없음) — 화면 쪽 bidx 와 상관없이 같은 경로면 남은 것으로
        $out[] = $b . '|' . (string)$p;
    }
    return $out;
}

// 지운 뒤 확인용 — 지금 서버에 남아 있는 경로 목록만(JSON)
if (($_GET['mode'] ?? '') === 'keys') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => true,
        'autosave' => bm_keys_list($autosave_arr),
        'bookmark' => bm_keys_list($bookmark_arr)],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$_autosave_max = (int)get_app_settings('max_autosave', 10);
$_bookmark_max = (int)get_app_settings('max_bookmark', 10);

// 실제 파일 존재 확인 (index.php mc_entry_exists 와 같은 규칙: base 를 모르면 존재로 간주 → 오탐 방지)
function bm_entry_exists($rel_path, $entry_bidx) {
    global $base_dirs;
    $rel = trim(str_replace('\\', '/', (string)$rel_path), '/');
    if ($rel === '') return true;
    $base = $base_dirs[(int)$entry_bidx] ?? null;
    if (!$base) return true;
    return @file_exists(rtrim($base, '/\\') . '/' . $rel);
}

/** 항목 하나 → 화면용 배열 (주소는 index.php 예전 표와 똑같이) */
function bm_item($type, $no, $path, $mark) {
    global $current_bidx, $bidx_param;
    if ($type === 'autosave' && !is_array($mark)) { // 옛 문자열 형식(index.php 와 같은 정규화)
        $mark = ['bookmark' => $mark, 'viewer' => 'toon', 'page_order' => '0', 'bidx' => 0];
    }
    if (is_array($mark)) {
        $ib = (int)($mark['bidx'] ?? 0);
        $anchor = (string)($mark['bookmark'] ?? '');
        $url = "viewer.php?mode=toon&page_order=" . ($mark['page_order'] ?? '0') . "&file=" . encode_url($path) . "&bidx=" . $ib . "#" . $anchor;
        $tb = ($type === 'bookmark') ? (int)($mark['bidx'] ?? $current_bidx) : $ib; // 썸네일 bidx 도 예전 표와 같게
    } else { // 옛 북마크 문자열 형식: 지금 폴더 bidx
        $ib = (int)$current_bidx;
        $anchor = (string)$mark;
        $url = "viewer.php?file=" . encode_url($path) . $bidx_param . "#" . $anchor;
        $tb = $ib;
    }
    $page = preg_match('/^image(\d+)$/', $anchor, $m) ? (int)$m[1] + 1 : 0; // ★ (2026-11-02 19:10) imageN 은 0부터(뷰어 그림 id·책갈피 알림과 같게 +1) — 10번째 쪽이 '9쪽'으로 보였음
    // ★ (2026-11-02 01:01) PDF 위치('pdf_쪽', 1부터)도 쪽 표시 / 자동 저장(이어 보기)은 쪽 안 위치(f)까지 붙여 보던 줄로 열리게
    if (!$page && preg_match('/^pdf_(\d{1,6})$/', $anchor, $m)) $page = (int)$m[1];
    if ($type === 'autosave' && is_array($mark) && preg_match('/^(image|pdf_)\d+$/', $anchor)) {
        $_bf = $mark['f'] ?? null;
        if (is_int($_bf) && $_bf >= 0 && $_bf <= 999) $url .= 'f' . $_bf;
    }
    $parts = explode('/', trim(str_replace('\\', '/', $path), '/'));
    $name = end($parts);
    return [
        'no'      => $no,
        'path'    => $path,
        'enc'     => encode_url($path),
        'bidx'    => $ib,
        'url'     => $url,
        'thumb'   => "thumb.php?file=" . encode_url($path) . "&bidx=" . $tb,
        'title'   => mark_display_title($name),   // 이미 HTML 이스케이프됨
        'parent'  => count($parts) > 1 ? implode('/', array_slice($parts, 0, -1)) : '',
        'page'    => $page,
        'time'    => is_array($mark) ? (int)($mark['t'] ?? 0) : 0, // ★ (2026-11-03 00:08) 저장한 때(옛 항목엔 없음)
        'missing' => !bm_entry_exists($path, $ib),
    ];
}

/** ★ (2026-11-03 00:08) 저장한 때 → '오늘 14:05' / '어제 14:05' / '10-31 14:05' / '2025-12-31' (서버 시간대) */
function bm_when($t) {
    $t = (int)$t;
    if ($t <= 0) return '';
    $d = date('Y-m-d', $t);
    if ($d === date('Y-m-d')) return __('bm_today') . ' ' . date('H:i', $t);
    if ($d === date('Y-m-d', strtotime('-1 day'))) return __('bm_yesterday') . ' ' . date('H:i', $t);
    if (date('Y', $t) === date('Y')) return date('m-d H:i', $t);
    return $d;
}

$sections = [];
foreach ([['autosave', $autosave_arr, $_autosave_max, 'index_continue_reading'], ['bookmark', $bookmark_arr, $_bookmark_max, 'index_bookmarks']] as $sec) {
    list($type, $arr, $max, $label) = $sec;
    $list = [];
    $no = 0;
    foreach ($arr as $path => $mark) {
        if (!is_string($path) || $path === '') continue;
        [$__kb, $path] = mc_rec_split($path, $mark); // ★ (2026-11-06 00:50) "bidx|/경로" 키는 경로만(값의 bidx 가 없으면 키의 번호)
        if (is_array($mark) && !isset($mark['bidx'])) $mark['bidx'] = $__kb;
        $list[] = bm_item($type, ++$no, $path, $mark);
    }
    $sections[] = ['type' => $type, 'items' => $list, 'max' => $max, 'label' => $label];
}
// ★ (2026-11-03 00:08) 전자책(EPUB·TXT) 책갈피 — 계정 노트 파일(src/<아이디>_notes.json)의 책갈피(k=bm)를 최근 것부터. 관리자 설정 개수(0 = 안 보임)
$_ebook_max = max(0, min(500, (int)get_app_settings('max_ebook_bookmark', 30)));
$ebook_items = [];
if ($_ebook_max > 0) {
    require_once __DIR__ . '/book_notes_lib.php';
    $_nf = function_exists('mc_notes_file') ? mc_notes_file() : '';
    $_nd = ($_nf !== '' && is_file($_nf)) ? bm_load_json_file($_nf) : [];
    foreach ($_nd as $_key => $_book) {
        if (!is_string($_key) || !is_array($_book) || !is_array($_book['items'] ?? null)) continue;
        if (!preg_match('/^(\d+)\|(.+)$/s', $_key, $_m)) continue;
        $_kb = (int)$_m[1];
        $_rel = '/' . ltrim($_m[2], '/');
        $_ext = strtolower(pathinfo($_rel, PATHINFO_EXTENSION));
        if ($_ext !== 'epub' && $_ext !== 'txt') continue;
        $_parts = explode('/', trim($_rel, '/'));
        $_name = end($_parts);
        foreach ($_book['items'] as $_it) {
            if (!is_array($_it) || ($_it['k'] ?? '') !== 'bm') continue;
            $_id = is_string($_it['id'] ?? null) ? $_it['id'] : '';
            if (!preg_match('/^[a-z0-9]{8,32}$/', $_id)) continue;
            $_p = (is_numeric($_it['p'] ?? null) && (float)$_it['p'] >= 0) ? (float)$_it['p'] : -1;
            $ebook_items[] = [
                'id'      => $_id,
                'kind'    => $_ext,
                'path'    => $_rel,
                'enc'     => encode_url($_rel),
                'bidx'    => $_kb,
                'url'     => ($_ext === 'epub' ? 'epub_viewer.php' : 'txt_viewer.php') . '?file=' . encode_url($_rel) . '&bidx=' . $_kb . '&note=' . $_id,
                'thumb'   => 'thumb.php?file=' . encode_url($_rel) . '&bidx=' . $_kb,
                'title'   => mark_display_title($_name),
                'parent'  => count($_parts) > 1 ? implode('/', array_slice($_parts, 0, -1)) : '',
                'ch'      => is_string($_it['ch'] ?? null) ? $_it['ch'] : '',
                'q'       => is_string($_it['q'] ?? null) ? $_it['q'] : '',
                'pct'     => $_p,
                'time'    => (int)($_it['t'] ?? 0),
                'missing' => !bm_entry_exists($_rel, $_kb),
            ];
        }
    }
    usort($ebook_items, function ($a, $b) { return $b['time'] <=> $a['time']; });
    $ebook_items = array_slice($ebook_items, 0, $_ebook_max);
}
// ★ (2026-11-03 01:30) 칸별 한 페이지 개수(관리자 설정, 0 = 나누지 않음) — 페이지 넘기기는 화면에서(칸마다 따로)
function bm_per_page($k) { return max(0, min(500, (int)get_app_settings($k, 12))); }
$_total = count($sections[0]['items']) + count($sections[1]['items']) + count($ebook_items);

$csrf_token = generate_csrf_token();
$_jsf = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="<?php echo get_html_lang(); ?>">
<head>
<?php if (function_exists('mc_theme_early_head')) echo mc_theme_early_head(); /* ★ (2026-11-08 fix80) 다크모드 흰 바탕 깜빡임 — 첫 그림부터 어둡게 */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo __h('index_bookmarks'); ?> - <?php echo h($_branding['page_title'] ?? 'myComix'); ?></title>
<?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
<link rel="stylesheet" href="./css/bootstrap.min.css">
<?php if (isset($darkmode_settings) && ($darkmode_settings['enabled'] ?? false)): ?>
<link rel="stylesheet" href="./css/darkmode.css?v=<?php echo @filemtime(__DIR__ . '/css/darkmode.css') ?: '1'; ?>">
<?php endif; ?>
<style>
  body { background:#f5f6f8; }
  .bm-wrap { max-width:1200px; margin:0 auto; padding:10px 12px 40px; }
  .bm-head { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:10px; }
  .bm-head h1 { font-size:1.3rem; margin:0; flex:1 1 auto; white-space:nowrap; }
  .bm-h1-ico { color:#3c4043; vertical-align:-3px; margin-right:2px; }
  .bm-tools { display:flex; align-items:center; gap:6px; }
  @media (max-width:576px) { .bm-head h1 { font-size:1.15rem; flex:1 1 0; min-width:0; } }
  @media (max-width:360px) { .bm-head .bm-back-lbl { display:none; } }
  /* 보기 전환 버튼 — 즐겨찾기 화면과 같은 모양 */
  .bm-view-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; padding:0;
                 border:1px solid #dcdcdc; border-radius:7px; background:#fff; color:#555; cursor:pointer; line-height:1; box-shadow:none; outline:none; }
  .bm-view-btn:hover { background:#f3f3f3; }
  .bm-view-btn.active { background:#e9e9e9; border-color:#d0d0d0; color:#333; }
  .bm-view-btn:focus { outline:none; box-shadow:none; }
  .bm-view-btn:focus-visible { outline:2px solid #80bdff; outline-offset:1px; }

  .bm-sec { margin:14px 0 22px; }
  .bm-sec-head { display:flex; align-items:baseline; gap:8px; margin:0 2px 10px; padding-bottom:6px; border-bottom:1px solid #e3e5e8; }
  .bm-sec-head h2 { font-size:1.02rem; font-weight:700; margin:0; color:#202124; }
  .bm-sec-count { font-size:.8rem; color:#888; }
  .bm-sec-empty { color:#999; font-size:.88rem; padding:6px 2px 4px; }
  /* ★ 칸 설명 · 저장한 때 · 전자책 책갈피(장·고른 글·종류) */
  .bm-sec-hint { font-size:.78rem; color:#80868b; margin:-4px 2px 10px; }
  .bm-when { font-size:.72rem; color:#80868b; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .bm-ch { font-size:.75rem; color:#5f6368; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .bm-q { display:none; font-size:.78rem; color:#5f6368; margin-top:3px; border-left:3px solid #8ab4f8; padding-left:6px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
  .bm-grid .bm-q { display:none; }
  .bm-kind { position:absolute; right:5px; bottom:5px; font-size:.65rem; line-height:1.5; padding:0 5px; border-radius:3px; background:#1a73e8; color:#fff; font-weight:600; }
  .bm-list .bm-cover .bm-kind { right:3px; bottom:3px; }

  /* 그리드 */
  .bm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:16px 12px; }
  @media (max-width:576px) { .bm-grid { grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px 8px; } }
  .bm-item { position:relative; min-width:0; }
  .bm-link { display:block; color:inherit !important; text-decoration:none !important; }
  .bm-cover { position:relative; width:100%; aspect-ratio:3/4; background:#e4e6ea; border-radius:6px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.18); }
  .bm-cover img { width:100%; height:100%; object-fit:cover; display:block; }
  .bm-page { position:absolute; left:5px; bottom:5px; font-size:.72rem; line-height:1.5; padding:0 6px; border-radius:10px; background:rgba(0,0,0,.62); color:#fff; }
  .bm-text { min-width:0; }
  .bm-title { font-size:.82rem; line-height:1.3; margin-top:6px; color:#202124; word-break:keep-all; overflow-wrap:anywhere; }
  .bm-sub { display:none; font-size:.75rem; color:#80868b; margin-top:2px; overflow-wrap:anywhere; }
  .bm-del { position:absolute; top:4px; right:4px; z-index:2; border:none; background:rgba(0,0,0,.55); color:#fff; width:24px; height:24px;
            border-radius:50%; font-size:13px; line-height:24px; padding:0; text-align:center; cursor:pointer; }
  .bm-del:disabled { opacity:.4; }

  /* 목록 */
  .bm-list { display:flex; flex-direction:column; gap:8px; }
  .bm-list .bm-item { display:flex; align-items:flex-start; gap:10px; background:#fff; border:1px solid #e6e8eb; border-radius:8px; padding:8px; }
  .bm-list .bm-link { display:flex; align-items:flex-start; gap:12px; flex:1 1 auto; min-width:0; }
  .bm-list .bm-cover { flex:0 0 60px; width:60px; height:80px; aspect-ratio:auto; border-radius:4px; }
  .bm-list .bm-page { position:static; display:inline-block; margin-top:4px; background:#eef1f4; color:#5f6368; }
  .bm-list .bm-cover .bm-page { display:none; }
  .bm-list .bm-text .bm-page { display:inline-block; }
  .bm-grid .bm-text .bm-page { display:none; }
  .bm-list .bm-text { flex:1 1 auto; padding-top:2px; }
  .bm-list .bm-title { font-size:1rem; margin-top:0; }
  .bm-list .bm-sub { display:block; }
  .bm-list .bm-del { position:static; flex:0 0 auto; background:transparent; color:#c62828; font-size:15px; }
  .bm-list .bm-del:hover { background:#fdecea; }

  .bm-item.is-missing .bm-cover img { filter:grayscale(1); opacity:.45; }
  .bm-item.is-missing .bm-link { cursor:default; }
  .bm-miss-badge { display:inline-block; font-size:.7rem; background:#e74c3c; color:#fff; border-radius:3px; padding:0 4px; margin-left:4px; vertical-align:middle; }
  .bm-empty { text-align:center; color:#999; padding:60px 10px; }
  /* ★ 칸별 페이지 넘기기 */
  .bm-pager { display:flex; flex-wrap:wrap; justify-content:center; align-items:center; gap:4px; margin:12px 0 0; }
  .bm-pager[hidden] { display:none; }
  .bm-pg { min-width:32px; height:32px; padding:0 8px; border:1px solid #dcdcdc; border-radius:7px; background:#fff; color:#444; font-size:.85rem; cursor:pointer; line-height:30px; }
  .bm-pg:hover { background:#f3f3f3; }
  .bm-pg.on { background:#1a73e8; border-color:#1a73e8; color:#fff; font-weight:600; cursor:default; }
  .bm-pg:disabled { opacity:.4; cursor:default; }
  .bm-pg-gap { color:#999; padding:0 2px; }
  .bm-item.bm-pg-off { display:none !important; }

  /* 다크모드 (darkmode.js 가 html/body 에 .dark-mode / data-theme="dark" 설정) */
  .dark-mode body, [data-theme="dark"] body, body.dark-mode { background:#121212 !important; }
  .dark-mode .bm-h1-ico, [data-theme="dark"] .bm-h1-ico { color:#e8eaed; }
  .dark-mode .bm-view-btn, [data-theme="dark"] .bm-view-btn { background:#1e1e1e; border-color:#444; color:#bbb; }
  .dark-mode .bm-view-btn.active, [data-theme="dark"] .bm-view-btn.active { background:#3a3a3a; border-color:#555; color:#eee; }
  .dark-mode .bm-sec-head, [data-theme="dark"] .bm-sec-head { border-color:#333; }
  .dark-mode .bm-sec-head h2, [data-theme="dark"] .bm-sec-head h2,
  .dark-mode .bm-title, [data-theme="dark"] .bm-title { color:#e8eaed; }
  .dark-mode .bm-cover, [data-theme="dark"] .bm-cover { background:#2a2a2a; }
  .dark-mode .bm-list .bm-item, [data-theme="dark"] .bm-list .bm-item { background:#1e1e1e; border-color:#333; }
  .dark-mode .bm-list .bm-page, [data-theme="dark"] .bm-list .bm-page { background:#2c2c2c; color:#bbb; }
  .dark-mode .bm-list .bm-del, [data-theme="dark"] .bm-list .bm-del { color:#ff8a80; }
  .dark-mode .bm-list .bm-del:hover, [data-theme="dark"] .bm-list .bm-del:hover { background:#3a2323; }
  .dark-mode .bm-sec-hint, [data-theme="dark"] .bm-sec-hint, .dark-mode .bm-when, [data-theme="dark"] .bm-when { color:#9aa0a6; }
  .dark-mode .bm-pg, [data-theme="dark"] .bm-pg { background:#1e1e1e; border-color:#444; color:#ccc; }
  .dark-mode .bm-pg.on, [data-theme="dark"] .bm-pg.on { background:#1a73e8; border-color:#1a73e8; color:#fff; }
  .dark-mode .bm-ch, [data-theme="dark"] .bm-ch, .dark-mode .bm-q, [data-theme="dark"] .bm-q { color:#bdc1c6; }
</style>
<?php // ★ (2026-10-31) 관리자 설정 '썸네일 크기' → 북마크 줄 — 값이 있을 때만 출력(없으면 기본 화면 그대로). 정수 px만 들어감
$_mc_thumb_css = function_exists('mc_thumb_layout_css') ? mc_thumb_layout_css('bm') : '';
if ($_mc_thumb_css !== ''): ?>
<style id="mcThumbLayout">
<?php echo $_mc_thumb_css; ?>
</style>
<?php endif; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-11-05 23:15) 아이콘 변환을 본문보다 먼저 시작 — 예전 이모지가 잠깐 보였다가 바뀌던 것(페이지 끝의 같은 스크립트는 다시 실행되지 않음) */ ?>
</head>
<body>
<div class="bm-wrap">
  <div class="bm-head">
    <a id="bmBack" class="btn btn-sm btn-outline-secondary" href="index.php<?php echo $bidx_query; ?>" title="<?php echo __h('fav_shelf_back'); ?>" aria-label="<?php echo __h('fav_shelf_back'); ?>">← <span class="bm-back-lbl"><?php echo __h('fav_shelf_back'); ?></span></a>
    <h1><svg class="bm-h1-ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg> <?php echo __h('index_bookmarks'); ?></h1>
    <div class="bm-tools">
      <button type="button" class="bm-view-btn" data-view="grid" title="<?php echo __h('fav_shelf_view_grid'); ?>" aria-label="<?php echo __h('fav_shelf_view_grid'); ?>"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/></svg></button>
      <button type="button" class="bm-view-btn" data-view="list" title="<?php echo __h('fav_shelf_view_list'); ?>" aria-label="<?php echo __h('fav_shelf_view_list'); ?>"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg></button>
    </div>
  </div>
<?php echo mc_shelf_nav('bm', $bidx_query); // ★ (2026-11-03 01:31) 서재·즐겨찾기·북마크 이동 ?>

<?php if ($_total === 0): ?>
  <div class="bm-empty"><?php echo __h('bm_empty'); ?></div>
<?php else: ?>
<?php foreach ($sections as $sec): ?>
  <section class="bm-sec" data-type="<?php echo $sec['type']; ?>" data-per="<?php echo bm_per_page('bm_page_' . $sec['type']); ?>">
    <div class="bm-sec-head">
      <h2><?php echo __h($sec['label']); ?></h2>
      <span class="bm-sec-count"><span class="bm-cnt"><?php echo count($sec['items']); ?></span>/<?php echo (int)$sec['max']; ?></span>
    </div>
    <div class="bm-sec-hint"><?php echo __h($sec['type'] === 'autosave' ? 'bm_hint_autosave' : 'bm_hint_bookmark'); ?></div>
    <div class="bm-sec-empty"<?php echo $sec['items'] ? ' style="display:none;"' : ''; ?>><?php echo __h('bm_sec_empty'); ?></div>
    <div class="bm-items bm-grid">
<?php foreach ($sec['items'] as $it): ?>
      <div class="bm-item<?php echo $it['missing'] ? ' is-missing' : ''; ?>" data-name="<?php echo $it['title']; ?>">
        <a class="bm-link" <?php echo $it['missing'] ? 'href="javascript:void(0)" aria-disabled="true"' : 'href="' . h($it['url']) . '"'; ?> title="<?php echo $it['title']; ?>">
          <div class="bm-cover">
            <img src="<?php echo h($it['thumb']); ?>" alt="" loading="lazy" decoding="async" onerror="this.onerror=null;this.style.visibility='hidden';">
            <?php if ($it['page'] > 0): ?><span class="bm-page"><?php echo h(__('bm_page', $it['page'])); ?></span><?php endif; ?>
          </div>
          <div class="bm-text">
            <div class="bm-title"><?php echo $it['title']; ?><?php if ($it['missing']): ?><span class="bm-miss-badge"><?php echo __h('fav_shelf_missing'); ?></span><?php endif; ?></div>
            <div class="bm-sub"><?php echo h($it['parent'] !== '' ? '/' . $it['parent'] : '/'); ?></div>
            <?php if ($it['page'] > 0): ?><span class="bm-page"><?php echo h(__('bm_page', $it['page'])); ?></span><?php endif; ?>
            <?php if ($it['time'] > 0): ?><div class="bm-when"><?php echo h(bm_when($it['time'])); ?></div><?php endif; ?>
          </div>
        </a>
        <button type="button" class="bm-del" title="<?php echo __h('bm_remove'); ?>" aria-label="<?php echo __h('bm_remove'); ?>"
                data-type="<?php echo $sec['type']; ?>" data-file="<?php echo h($it['enc']); ?>" data-path="<?php echo h($it['path']); ?>" data-bidx="<?php echo (int)$it['bidx']; ?>">✕</button>
      </div>
<?php endforeach; ?>
    </div>
    <nav class="bm-pager" hidden></nav>
  </section>
<?php endforeach; ?>
<?php if ($_ebook_max > 0): // ★ 전자책(EPUB·TXT) 책갈피 ?>
  <section class="bm-sec" data-type="ebook" data-per="<?php echo bm_per_page('bm_page_ebook'); ?>">
    <div class="bm-sec-head">
      <h2><?php echo __h('bm_sec_ebook'); ?></h2>
      <span class="bm-sec-count"><span class="bm-cnt"><?php echo count($ebook_items); ?></span>/<?php echo (int)$_ebook_max; ?></span>
    </div>
    <div class="bm-sec-hint"><?php echo __h('bm_hint_ebook'); ?></div>
    <div class="bm-sec-empty"<?php echo $ebook_items ? ' style="display:none;"' : ''; ?>><?php echo __h('bm_sec_empty'); ?></div>
    <div class="bm-items bm-grid">
<?php foreach ($ebook_items as $it): ?>
      <div class="bm-item<?php echo $it['missing'] ? ' is-missing' : ''; ?>" data-name="<?php echo $it['title']; ?>">
        <a class="bm-link" <?php echo $it['missing'] ? 'href="javascript:void(0)" aria-disabled="true"' : 'href="' . h($it['url']) . '"'; ?> title="<?php echo $it['title']; ?>">
          <div class="bm-cover">
            <img src="<?php echo h($it['thumb']); ?>" alt="" loading="lazy" decoding="async" onerror="this.onerror=null;this.style.visibility='hidden';">
            <span class="bm-kind"><?php echo $it['kind'] === 'epub' ? 'EPUB' : 'TXT'; ?></span>
            <?php if ($it['pct'] >= 0): ?><span class="bm-page"><?php echo h(rtrim(rtrim(number_format($it['pct'], 1, '.', ''), '0'), '.')); ?>%</span><?php endif; ?>
          </div>
          <div class="bm-text">
            <div class="bm-title"><?php echo $it['title']; ?><?php if ($it['missing']): ?><span class="bm-miss-badge"><?php echo __h('fav_shelf_missing'); ?></span><?php endif; ?></div>
            <?php if ($it['ch'] !== ''): ?><div class="bm-ch"><?php echo h($it['ch']); ?></div><?php endif; ?>
            <?php if ($it['q'] !== ''): ?><div class="bm-q"><?php echo h($it['q']); ?></div><?php endif; ?>
            <div class="bm-sub"><?php echo h($it['parent'] !== '' ? '/' . $it['parent'] : '/'); ?></div>
            <?php if ($it['pct'] >= 0): ?><span class="bm-page"><?php echo h(rtrim(rtrim(number_format($it['pct'], 1, '.', ''), '0'), '.')); ?>%</span><?php endif; ?>
            <?php if ($it['time'] > 0): ?><div class="bm-when"><?php echo h(bm_when($it['time'])); ?></div><?php endif; ?>
          </div>
        </a>
        <button type="button" class="bm-del" title="<?php echo __h('bm_remove'); ?>" aria-label="<?php echo __h('bm_remove'); ?>"
                data-type="ebook" data-kind="<?php echo $it['kind']; ?>" data-note="<?php echo h($it['id']); ?>" data-file="<?php echo h($it['enc']); ?>" data-path="<?php echo h($it['path']); ?>" data-bidx="<?php echo (int)$it['bidx']; ?>">✕</button>
      </div>
<?php endforeach; ?>
    </div>
    <nav class="bm-pager" hidden></nav>
  </section>
<?php endif; ?>
<?php endif; ?>
</div>

<script>
(function () {
  var CSRF = <?php echo json_encode($csrf_token); ?>;
  var MSG_CONFIRM = <?php echo json_encode(__('bm_confirm_remove'), $_jsf); ?>;
  var MSG_FAIL = <?php echo json_encode(__('bm_remove_fail'), $_jsf); ?>;
  var MSG_STILL = <?php echo json_encode(__('fav_shelf_remove_still'), $_jsf); ?>;
  var lists = document.querySelectorAll('.bm-items');
  var viewBtns = document.querySelectorAll('.bm-view-btn');

  function getPref(k, def) { try { var v = localStorage.getItem(k); return v === null ? def : v; } catch (e) { return def; } }
  function setPref(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  // 보기 방식 (그리드/목록)
  function applyView(v) {
    if (v !== 'list') v = 'grid';
    for (var i = 0; i < lists.length; i++) lists[i].className = 'bm-items ' + (v === 'list' ? 'bm-list' : 'bm-grid');
    for (var j = 0; j < viewBtns.length; j++) {
      var on = viewBtns[j].getAttribute('data-view') === v;
      viewBtns[j].classList.toggle('active', on);
      viewBtns[j].setAttribute('aria-pressed', on ? 'true' : 'false');
    }
  }
  for (var i = 0; i < viewBtns.length; i++) {
    viewBtns[i].addEventListener('click', function () {
      var v = this.getAttribute('data-view');
      applyView(v); setPref('mcBmView', v);
      try { this.blur(); } catch (e) {}
    });
  }
  applyView(getPref('mcBmView', 'grid'));

  // ★ (2026-11-03 01:30) 칸별 페이지 넘기기 — 칸마다 따로. 보던 페이지는 이 탭 동안 기억(뷰어 갔다 돌아와도 그대로)
  var T_PREV = <?php echo json_encode(__('bm_pg_prev'), $_jsf); ?>, T_NEXT = <?php echo json_encode(__('bm_pg_next'), $_jsf); ?>;
  function pgKey(sec) { return 'mcBmPg_' + (sec.getAttribute('data-type') || ''); }
  function pgGet(sec) { try { return parseInt(sessionStorage.getItem(pgKey(sec)) || '1', 10) || 1; } catch (e) { return 1; } }
  function pgSet(sec, p) { try { sessionStorage.setItem(pgKey(sec), String(p)); } catch (e) {} }
  function paginate(sec, page) {
    var per = parseInt(sec.getAttribute('data-per') || '0', 10) || 0;
    var items = sec.querySelectorAll('.bm-item');
    var nav = sec.querySelector('.bm-pager');
    var n = items.length;
    var pages = per > 0 ? Math.max(1, Math.ceil(n / per)) : 1;
    if (page == null) page = pgGet(sec);
    page = Math.min(Math.max(1, page | 0), pages);
    sec.setAttribute('data-page', String(page));
    for (var i = 0; i < n; i++) {
      var off = per > 0 && (i < (page - 1) * per || i >= page * per);
      items[i].classList.toggle('bm-pg-off', off);
    }
    if (!nav) return;
    if (pages <= 1) { nav.hidden = true; nav.innerHTML = ''; return; }
    nav.hidden = false;
    var html = '<button type="button" class="bm-pg" data-pg="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' aria-label="' + T_PREV + '" title="' + T_PREV + '">‹</button>';
    var last = 0;
    for (var p = 1; p <= pages; p++) {
      if (p !== 1 && p !== pages && Math.abs(p - page) > 2) continue; // 처음·끝·지금 앞뒤 2쪽만
      if (last && p - last > 1) html += '<span class="bm-pg-gap">…</span>';
      html += '<button type="button" class="bm-pg' + (p === page ? ' on' : '') + '" data-pg="' + p + '"' + (p === page ? ' aria-current="page"' : '') + '>' + p + '</button>';
      last = p;
    }
    html += '<button type="button" class="bm-pg" data-pg="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + ' aria-label="' + T_NEXT + '" title="' + T_NEXT + '">›</button>';
    nav.innerHTML = html;
  }
  var secs = document.querySelectorAll('.bm-sec');
  for (var s = 0; s < secs.length; s++) paginate(secs[s]);
  document.addEventListener('click', function (ev) {
    var b = ev.target && ev.target.closest ? ev.target.closest('.bm-pg') : null;
    if (!b || b.disabled || b.classList.contains('on')) return;
    var sec = b.closest('.bm-sec'); if (!sec) return;
    var p = parseInt(b.getAttribute('data-pg') || '1', 10) || 1;
    paginate(sec, p); pgSet(sec, parseInt(sec.getAttribute('data-page') || '1', 10));
    var head = sec.querySelector('.bm-sec-head');
    if (head && head.getBoundingClientRect().top < 0) { try { head.scrollIntoView({ block: 'start' }); } catch (e) {} } // 칸 머리가 화면 위로 나가 있으면 칸 처음으로
  });

  // 지우기 — 기존 bookmark.php 삭제 모드(POST + CSRF). 성공하면 서버가 이 화면으로 되돌려 보내므로(리다이렉트) 따라가지 않고 받기만 함
  document.addEventListener('click', function (ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('.bm-del') : null;
    if (!btn) return;
    ev.preventDefault(); ev.stopPropagation();
    if (btn.disabled) return;
    var item = btn.closest('.bm-item');
    var sec = btn.closest('.bm-sec');
    var dt = btn.getAttribute('data-type');
    var type = dt === 'autosave' ? 'autosave' : (dt === 'ebook' ? 'ebook' : 'bookmark');
    var path = btn.getAttribute('data-path') || '';
    if (!window.confirm(MSG_CONFIRM.replace('%s', function () { return item ? (item.getAttribute('data-name') || '') : ''; }) /* ★ (2026-11-02 11:36) 이름의 $& 등을 특수 기호로 읽지 않게 */)) return;
    btn.disabled = true;

    function removeItem() {
      if (item && item.parentNode) item.parentNode.removeChild(item);
      if (sec) {
        var n = sec.querySelectorAll('.bm-item').length;
        var c = sec.querySelector('.bm-cnt'); if (c) c.textContent = String(n);
        var em = sec.querySelector('.bm-sec-empty'); if (em) em.style.display = n ? 'none' : '';
        paginate(sec, parseInt(sec.getAttribute('data-page') || '1', 10)); // ★ 지운 뒤 다음 항목이 당겨져 보이게(마지막 페이지가 비면 앞 페이지로)
      }
      try { sessionStorage.setItem('mycomix_marks_dirty', '1'); } catch (e) {} // 목록 화면으로 돌아가면 이어 읽기 등 다시 읽게
    }
    function fail(msg) { btn.disabled = false; alert(msg); }
    function verifyRemoved() {
      var vx = new XMLHttpRequest();
      vx.open('GET', 'bookmarks.php?mode=keys&_=' + Date.now(), true);
      vx.onreadystatechange = function () {
        if (vx.readyState !== 4) return;
        var list = null;
        if (vx.status === 200) { try { var r = JSON.parse(vx.responseText); if (r && r.success && Array.isArray(r[type])) list = r[type]; } catch (e) {} }
        if (list === null) { fail(MSG_FAIL); return; } // ★ (2026-11-02 19:10) 확인 실패면 지우지 않음 — 로그인이 풀린 뒤엔 삭제도 확인도 로그인 화면으로 가서, 안 지워졌는데 화면에서만 사라졌음
        var nf = function (x) { try { return String(x).normalize('NFC'); } catch (e) { return String(x); } };
        var np = nf(path), nb = String(parseInt(btn.getAttribute('data-bidx') || '0', 10) || 0); // ★ (2026-11-06 00:50) 서버 목록은 '콘텐츠 폴더 번호|경로'(옛 문자열 기록은 -1)
        for (var q = 0; q < list.length; q++) { var it = nf(list[q]), bar = it.indexOf('|'), ib = bar > 0 ? it.slice(0, bar) : '', ip = bar > 0 ? it.slice(bar + 1) : it; if (ip === np && (ib === nb || ib === '-1')) { fail(MSG_STILL); return; } }
        removeItem();
      };
      vx.send();
    }
    var body = 'file=' + btn.getAttribute('data-file') + '&bidx=' + encodeURIComponent(btn.getAttribute('data-bidx') || '0') + '&csrf_token=' + encodeURIComponent(CSRF);
    // ★ 전자책 책갈피 — 그 뷰어의 노트 지우기(notes_save op=del, CSRF). 결과가 JSON 이라 바로 확인
    if (type === 'ebook') {
      var api = (btn.getAttribute('data-kind') === 'epub' ? 'epub_viewer.php' : 'txt_viewer.php') + '?action=notes_save&bidx=' + encodeURIComponent(btn.getAttribute('data-bidx') || '0');
      var eb = body + '&op=del&id=' + encodeURIComponent(btn.getAttribute('data-note') || '');
      fetch(api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: eb })
        .then(function (r) { return r.json().catch(function () { return null; }); })
        .then(function (d) { if (d && d.success) removeItem(); else fail(MSG_FAIL); }, function () { fail(MSG_FAIL); });
      return;
    }
    var req;
    try {
      req = fetch('bookmark.php?mode=delete_' + type, { method: 'POST', credentials: 'same-origin', redirect: 'manual',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body });
    } catch (e) { fail(MSG_FAIL); return; }
    req.then(function (r) {
      // 성공 = 리다이렉트(되돌려 보냄). 권한·토큰 실패는 403 JSON
      if (r.type === 'opaqueredirect' || (r.status >= 200 && r.status < 400)) verifyRemoved(); else fail(MSG_FAIL);
    }, function () { fail(MSG_FAIL); });
  });

  // 돌아가기: 목록(index.php)에서 들어왔으면 뒤로가기(보던 폴더·스크롤 그대로), 아니면 목록 첫 화면으로
  var back = document.getElementById('bmBack');
  if (back) {
    back.addEventListener('click', function (ev) {
      try {
        var ref = document.referrer ? new URL(document.referrer) : null;
        if (ref && ref.origin === location.origin && /\/index\.php$/.test(ref.pathname) && history.length > 1) { ev.preventDefault(); history.back(); }
      } catch (e) {}
    });
  }
  // 뷰어에서 읽다가 뒤로 돌아왔을 때(브라우저가 보관한 화면): 이어 읽기 위치가 바뀌었을 수 있으니 새로 읽음
  //  (표시는 지우지 않음 — 목록 화면도 같은 표시로 다시 읽어야 함)
  window.addEventListener('pageshow', function (e) {
    try { if (e.persisted && sessionStorage.getItem('mycomix_marks_dirty')) location.reload(); } catch (x) {}
  });
})();
</script>
<?php render_darkmode_script(); ?>
<?php render_auto_logout_script(); // 관리자 설정 '자동 로그아웃 대상'에 bookmarks.php 를 체크한 경우에만 동작 ?>
<?php require_once __DIR__ . '/privacy_shield.php'; // 관리자 설정 '모바일 화면 가림 대상'에 체크한 경우에만 동작 ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); ?>
<?php if (function_exists('mc_perf_script')) echo mc_perf_script('bookmarks'); // ★ (2026-11-03 14:10) 속도 기록(src/perf) ?>
<?php if (function_exists('mc_back_origin_list_js')) echo mc_back_origin_list_js(); /* ★ (2026-11-05) 뷰어 뒤로가기가 이 화면(주소·스크롤 위치)으로 돌아오게 — function.php */ ?>
</body>
</html>
