<?php
/**
 * myComix 시리즈 상세 화면
 * ★ (2026-10-29) 신규 — 표지 · 작가 · 장르 · 태그 · 상태 · 설명 · 권 목록(그리드/목록, 진행률) · 이어보기
 *
 * 진입: series.php?sid=N  또는  series.php?file={encode_url(경로)}&bidx=N (목록 카드의 ⓘ 버튼)
 * - file 로 들어오면 그 파일이 있는 폴더를 즉시 인덱싱한 뒤 해당 권의 시리즈를 보여줌
 * - 권 목록은 현재 사용자 그룹이 볼 수 있는 폴더의 권만(기존 폴더 권한 규칙 그대로)
 * - 편집은 관리자만(library_api.php, CSRF 토큰 필수)
 */

$_lib_t0 = microtime(true);
require_once __DIR__ . "/bootstrap.php";
handle_timeout_popup();
require_once __DIR__ . "/library.php";

$bidx = init_bidx();
$_branding = load_branding();
$is_admin = mc_lib_is_admin();
$db = mc_lib_db();

$sid = (int)($_GET['sid'] ?? 0);
$prog_only = isset($_GET['prog']) && $sid > 0; // ★ (2026-11-02 16:18) 뒤로 왔을 때 읽은 정도만(새로고침 없이) — 폴더 다시 훑기는 건너뜀
$from_path = '';
if (!$sid && isset($_GET['file'])) {
    $from_path = mc_lib_norm_path(decode_file_param($_GET['file']));
    if ($db && $from_path !== '' && mc_lib_can_access($bidx, $from_path)) {
        mc_lib_index_folder($bidx, dirname($from_path) === '/' ? '' : dirname($from_path));
        $st = $db->prepare('SELECT series_id FROM volumes WHERE bidx=? AND path=?');
        $st->execute([$bidx, $from_path]);
        $sid = (int)$st->fetchColumn();
    }
}

$series = null; $vols = []; $terms = ['author' => [], 'group' => [], 'genre' => [], 'tag' => []]; // ★ (2026-11-02 01:53) 그룹
if ($db && $sid) {
    $st = $db->prepare('SELECT * FROM series WHERE id=?');
    $st->execute([$sid]);
    $series = $st->fetch() ?: null;
    $st->closeCursor(); // ★ (2026-10-30) 열어 두면 아래 폴더 다시 훑기의 쓰기가 스캔 중 실패(페이지 500)하던 것 — 바로 닫음
    if ($series && !$prog_only) {
        // 권 폴더들을 다시 훑어 최신 상태 반영(새로 추가/삭제된 권)
        $fs = $db->prepare('SELECT bidx, folder, MIN(path) AS path FROM volumes WHERE series_id=? GROUP BY bidx, folder');
        $fs->execute([$sid]);
        $probe_path = null;
        foreach ($fs->fetchAll() as $f) {
            // 볼 수 있는 폴더만 다시 훑음(권한 확인은 실제 권 경로로)
            if (mc_lib_can_access((int)$f['bidx'], $f['path'])) { mc_lib_index_folder((int)$f['bidx'], $f['folder']); if ($probe_path === null) $probe_path = [(int)$f['bidx'], $f['path']]; }
        }
        // ★ (2026-10-29) 다시 읽는 중 이 작품의 권이 다른 작품으로 합쳐졌으면(예전 링크) 옮겨간 작품으로
        if ($probe_path) {
            $st = $db->prepare('SELECT series_id FROM volumes WHERE bidx=? AND path=?');
            $st->execute($probe_path);
            $now_sid = (int)$st->fetchColumn();
            $st = $db->prepare('SELECT COUNT(*) FROM volumes WHERE series_id=? AND missing=0');
            $st->execute([$sid]);
            if ($now_sid && $now_sid !== $sid && (int)$st->fetchColumn() === 0) {
                header('Location: series.php?sid=' . $now_sid . '&bidx=' . (int)$bidx, true, 302);
                exit;
            }
        }
    }
    if ($series) {
        $vols = mc_lib_series_volumes($db, $sid);
        $terms = mc_lib_series_terms($db, $sid);
        if (!$vols) $series = null; // 볼 수 있는 권이 없으면 존재하지 않는 것처럼
    }
}

// 읽기 정보
$reads = $series ? mc_lib_user_reads() : [];
// ★ (2026-10-29) 같은 책 사본(폴더별 중복 업로드)은 하나로 묶어 권 수·읽음·이어보기를 계산. 편집 창에는 전체 파일($all_vols)
$all_vols = $vols;
$vols = mc_lib_group_dups($all_vols, $reads);
$dup_extra = count($all_vols) - count($vols);
$implicit_one = mc_lib_implicit_first($all_vols); // ★ (2026-10-29) "7 Days" + "7 Days 2" → 본편을 1권으로 표시
$read_count = 0; $resume = null; $resume_time = -1; $resume_vol = -1;
foreach ($vols as $i => $v) {
    $pct = 0; $fin = false; $page = 0; $hasRead = false; $cand = null; $candT = -1;
    foreach ($v['_copies'] as $c) {
        $r = $reads[(int)$c['bidx'] . '|' . $c['path']] ?? null;
        if (!$r) continue;
        $hasRead = true;
        $total = (int)$r['total'];
        if ($total <= 0) $total = (int)$c['pages'] ?: mc_lib_total_pages((int)$c['bidx'], $c['path']);
        $cfin = !empty($r['finished']) || ($total > 0 && (int)$r['page'] + 1 >= $total);
        $cpct = $cfin ? 100 : ($total > 0 ? (int)floor(((int)$r['page'] + 1) / $total * 100) : 1);
        if ($cfin) $fin = true;
        if ($cpct > $pct) $pct = $cpct;
        if ($c['path'] === $v['path'] && (int)$c['bidx'] === (int)$v['bidx']) $page = (int)$r['page'];
        $t = (int)$r['updated_at'];
        if (!$cfin && $t > $candT) { $cand = [$c, (int)$r['page']]; $candT = $t; }
    }
    if ($fin) { $read_count++; $pct = 100; }
    // 이어보기: 다 읽지 않은 권 중 가장 최근에 읽은 사본(기록 시각) → 같으면 뒤쪽 권
    if (!$fin && $cand && ($candT > $resume_time || ($candT === $resume_time && $i > $resume_vol))) { $resume = $cand; $resume_time = $candT; $resume_vol = $i; }
    $vols[$i]['_pct'] = $hasRead ? $pct : 0;
    $vols[$i]['_fin'] = $fin;
    $vols[$i]['_page'] = $page;
}
// 진행 중인 권이 없으면: 다 읽은 권 다음 권
if (!$resume && $vols) {
    $last_fin = -1;
    foreach ($vols as $i => $v) if ($v['_fin']) $last_fin = $i;
    if ($last_fin >= 0 && isset($vols[$last_fin + 1])) $resume = [$vols[$last_fin + 1], 0];
}
// ★ (2026-11-02 16:18) 읽은 정도만 JSON 으로(뒤로 왔을 때 새로고침 대신)
if ($prog_only) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $_pv = [];
    foreach ($vols as $i => $v) $_pv[] = [(int)$i, (int)$v['_pct'], $v['_fin'] ? 1 : 0, mc_lib_view_url((int)$v['bidx'], $v['path'], (!$v['_fin'] && $v['_page'] > 0) ? $v['_page'] : null)];
    echo json_encode(['ok' => $series ? 1 : 0, 'vols' => $_pv,
        'meta' => __('lib_vol_count', count($vols)) . ' · ' . __('lib_read_count', $read_count, count($vols)),
        'resume' => $resume ? [mc_lib_view_url((int)$resume[0]['bidx'], $resume[0]['path'], $resume[1]), '▶ ' . __('lib_resume') . ' · ' . mc_lib_vol_title($resume[0], $implicit_one)] : null],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
$first = $vols[0] ?? null;
$cover = $first ? mc_lib_thumb_url((int)$first['bidx'], $first['path']) : '';
$status_label = ['completed' => __('lib_status_completed'), 'ongoing' => __('lib_status_ongoing'), 'hiatus' => __('lib_status_hiatus')];
$csrf_token = generate_csrf_token();
if (!headers_sent()) header('Server-Timing: app;desc="series";dur=' . round((microtime(true) - $_lib_t0) * 1000, 1)); // ★ (2026-10-29) 처리 시간(개발자 도구 → 네트워크 → 타이밍)
?>
<!DOCTYPE html>
<html lang="<?php echo get_html_lang(); ?>">
<head>
<?php if (function_exists('mc_theme_early_head')) echo mc_theme_early_head(); /* ★ (2026-11-08 fix80) 다크모드 흰 바탕 깜빡임 — 첫 그림부터 어둡게 */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo $series ? h($series['name']) : __h('lib_series'); ?> - <?php echo h($_branding['page_title'] ?? 'myComix'); ?></title>
<?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
<link rel="stylesheet" href="./css/bootstrap.min.css">
<?php if (isset($darkmode_settings) && ($darkmode_settings['enabled'] ?? false)): ?>
<link rel="stylesheet" href="./css/darkmode.css?v=<?php echo @filemtime(__DIR__ . '/css/darkmode.css') ?: '1'; ?>">
<?php endif; ?>
<style>
  body { background:#f5f3ef; }
  .lib-wrap { max-width:1200px; margin:0 auto; padding:10px 12px 60px; }
  .lib-top { display:flex; align-items:center; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
  .lib-top .spacer { flex:1 1 auto; }
  .sr-head { display:flex; gap:20px; align-items:flex-start; background:#fff; border-radius:8px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .sr-cover { flex:0 0 170px; width:170px; aspect-ratio:3/4; border-radius:4px; overflow:hidden; background:#ddd; box-shadow:0 6px 12px -4px rgba(0,0,0,.45); }
  .sr-cover img { width:100%; height:100%; object-fit:cover; display:block; }
  .sr-info { flex:1 1 auto; min-width:0; }
  .sr-info h1 { font-size:1.5rem; margin:0 0 6px; word-break:keep-all; overflow-wrap:anywhere; }
  .sr-meta { color:#777; font-size:.9rem; margin-bottom:8px; }
  .chips { display:flex; flex-wrap:wrap; gap:5px; margin:4px 0; }
  .chip { display:inline-block; padding:2px 9px; border-radius:4px; font-size:.82rem; text-decoration:none !important; border:1px solid transparent; } /* ★ 둥근 사각형 */
  .chip-author { background:#eef4ff; border-color:#bcd0f5; color:#1a56c4 !important; }
  .chip-group  { background:#eefaf1; border-color:#b5e0c2; color:#1e7a3c !important; } /* ★ (2026-11-02) 그룹 */
  .chip-genre  { background:#fdf0ee; border-color:#efc3bc; color:#b23121 !important; }
  .chip-tag    { background:#f1f2f4; border-color:#d3d6db; color:#444 !important; }
  .chip:hover { filter:brightness(.95); }
  .chip-label { font-size:.75rem; color:#999; margin-right:4px; align-self:center; }
  .sr-status { display:inline-block; font-size:.75rem; padding:1px 8px; border-radius:4px; margin-left:6px; vertical-align:middle; background:#28a745; color:#fff; }
  .sr-status.ongoing { background:#17a2b8; } .sr-status.hiatus { background:#6c757d; }
  .sr-desc { white-space:pre-wrap; color:#444; font-size:.92rem; margin:8px 0 10px; max-height:9.5em; overflow:auto; }
  .sr-actions { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
  .vol-bar { display:flex; align-items:center; gap:8px; margin:18px 0 10px; }
  .vol-bar h2 { font-size:1.1rem; margin:0; flex:1 1 auto; }
  .lib-view-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; padding:0; border:1px solid #dcdcdc; border-radius:7px; background:#fff; color:#555; cursor:pointer; box-shadow:none; outline:none; }
  .lib-view-btn:hover { background:#f3f3f3; } .lib-view-btn.active { background:#e9e9e9; border-color:#d0d0d0; color:#333; }
  .lib-view-btn:focus { outline:none; box-shadow:none; } .lib-view-btn:focus-visible { outline:2px solid #80bdff; outline-offset:1px; }
  /* 권 목록 — 그리드 */
  .vols-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:16px 12px; }
  @media (max-width:576px) { .vols-grid { grid-template-columns:repeat(3, 1fr); gap:12px 8px; } }
  .vol { position:relative; color:inherit; text-decoration:none !important; display:block; }
  .vols-grid .vol-cover { position:relative; width:100%; aspect-ratio:3/4; border-radius:3px; overflow:hidden; background:#ddd; box-shadow:0 4px 8px -3px rgba(0,0,0,.4); }
  .vol-cover img { width:100%; height:100%; object-fit:cover; display:block; }
  .vols-grid .vol-name { font-size:.85rem; margin-top:5px; line-height:1.3; word-break:keep-all; overflow-wrap:anywhere; } /* ★ 제목 전체 표시(자르지 않음), 낱말 중간에서 끊지 않음 */
  .vols-grid .vol-file { display:none; }
  .vol-prog { position:absolute; left:0; right:0; bottom:0; height:4px; background:rgba(0,0,0,.25); }
  .vol-prog > span { display:block; height:100%; background:#f39c12; }
  .vol-fin { position:absolute; top:4px; right:4px; background:#28a745; color:#fff; border-radius:50%; width:22px; height:22px; font-size:13px; line-height:22px; text-align:center; }
  /* ★ (2026-11-04 17:55) 표지 위 ✓·×2·사본 표시를 표지 폭에 맞춰 — 목록 보기의 작은 표지(60px)에서 그림을 덜 가리고, 넓은 표지에선 조금 크게(옛 브라우저는 위 크기 그대로) */
  .vol-cover { container-type:inline-size; }
  .vol-chips { display:none; }
  .vols-list .vol-chips:not(:empty) { display:flex; flex-wrap:wrap; gap:4px; margin-top:5px; }
  .vols-list .vol-cover > .vol-fin, .vols-list .vol-cover > .vol-dupn { display:none; }
  .vol-cf, .vol-cd { font-size:.75rem; line-height:1.2; font-weight:600; border-radius:999px; padding:2px 8px; }
  .vol-cf { background:#28a745; color:#fff; }
  .vol-cd { background:#6c757d; color:#fff; }
  .vol-cover > .vol-fin { width:clamp(16px, 15cqw, 28px); height:clamp(16px, 15cqw, 28px); line-height:clamp(16px, 15cqw, 28px); font-size:clamp(10px, 9cqw, 17px); top:clamp(2px, 3cqw, 5px); right:clamp(2px, 3cqw, 5px); }
  /* ★ (2026-11-05 17:55) 다 읽음 ✓ — 서재(browse.php)와 같은 모양: 큰 녹색 원 → 반투명 검정 작은 상자 + 연두색 체크(옆 ×2·사본 표시와 같은 크기) */
  .vol-cover > .vol-fin { width:auto; height:1.6em; min-width:0; padding:0 .3em; border-radius:3px; background:rgba(0,0,0,.62); color:#8be3a5 !important; /* 높이 = 옆 표시와 같게(글자 크기 × 줄 높이) */
    font-size:.72rem; font-size:clamp(10px, 8cqw, 14px); line-height:1.6; font-weight:700; display:inline-flex; align-items:center; justify-content:center; }
  .vol-cover > .vol-fin svg { width:1.1em !important; height:1.1em !important; vertical-align:0 !important; color:#8be3a5 !important; stroke:currentColor !important; stroke-width:3 !important; }
  .vol-cover > .vol-dupn, .vol-cover > .vol-copytag { font-size:clamp(10px, 8cqw, 14px); line-height:1.6; top:clamp(2px, 3cqw, 5px); left:clamp(2px, 3cqw, 5px); }
  /* 권 목록 — 목록 */
  .vols-list .vol { display:flex; align-items:flex-start; gap:10px; padding:8px 4px; border-bottom:1px solid #e2ddd5; }
  .vols-list .vol-cover { position:relative; flex:0 0 60px; width:60px; height:80px; border-radius:3px; overflow:hidden; background:#ddd; }
  .vols-list .vol-text { min-width:0; flex:1 1 auto; padding-top:2px; }
  .vols-list .vol-name { font-size:1rem; line-height:1.35; word-break:keep-all; overflow-wrap:anywhere; }
  .vols-list .vol-file { font-size:.75rem; color:#999; word-break:break-all; margin-top:2px; }
  .vol-pct { font-size:.75rem; color:#f39c12; margin-left:4px; }
  /* ★ (2026-10-29) 같은 책 사본 */
  .vol-copy { display:none !important; }
  #vols.show-dups .vol-copy { display:block !important; }
  #vols.vols-list.show-dups .vol-copy { display:flex !important; }
  .vol-copy .vol-cover { opacity:.75; }
  .vol-dupn { position:absolute; top:4px; left:4px; background:rgba(0,0,0,.6); color:#fff; border-radius:9px; padding:0 6px; font-size:11px; line-height:18px; }
  .vol-copytag { position:absolute; top:4px; left:4px; background:#6c757d; color:#fff; border-radius:3px; padding:0 5px; font-size:11px; line-height:17px; }
  .vol-duptoggle { font-size:.85rem; color:#666; white-space:nowrap; cursor:pointer; }
  .vol-bar h2 { white-space:nowrap; }
  @media (max-width:576px) { .vol-bar { flex-wrap:wrap; } .vol-duptoggle { order:9; flex-basis:100%; } }
  .dark-mode .vol-duptoggle, [data-theme="dark"] .vol-duptoggle { color:#bbb; }
  .lib-empty { text-align:center; color:#888; padding:60px 10px; }
  @media (max-width:576px) {
    .sr-head { gap:12px; padding:12px; }
    .sr-cover { flex-basis:110px; width:110px; }
    .sr-info h1 { font-size:1.2rem; }
  }
  /* 다크모드 */
  .dark-mode body, [data-theme="dark"] body, body.dark-mode { background:#121212 !important; }
  .dark-mode .sr-head, [data-theme="dark"] .sr-head { background:#1e1e1e; box-shadow:none; }
  .dark-mode .sr-desc, [data-theme="dark"] .sr-desc { color:#ccc; }
  .dark-mode .chip-author, [data-theme="dark"] .chip-author { background:#1a2a45; border-color:#34507f; color:#9ec1ff !important; }
  .dark-mode .chip-group, [data-theme="dark"] .chip-group { background:#17331f; border-color:#2f6b40; color:#9be3b0 !important; }
  .dark-mode .chip-genre, [data-theme="dark"] .chip-genre { background:#3c1f1c; border-color:#6e3a33; color:#ffb4a8 !important; }
  .dark-mode .chip-tag, [data-theme="dark"] .chip-tag { background:#26292d; border-color:#454a50; color:#ddd !important; }
  .dark-mode .vols-list .vol, [data-theme="dark"] .vols-list .vol { border-color:#333; }
  .dark-mode .lib-view-btn, [data-theme="dark"] .lib-view-btn { background:#1e1e1e; border-color:#444; color:#bbb; }
  .dark-mode .lib-view-btn.active, [data-theme="dark"] .lib-view-btn.active { background:#3a3a3a; color:#eee; }
  #scrollTopBtn { position:fixed; bottom:90px; right:20px; z-index:9999; font-size:18px; background-color:rgba(0,0,0,.3); color:#fff; border:none; border-radius:50%; width:40px; height:40px; padding:0; cursor:pointer; outline:none; display:flex; align-items:center; justify-content:center; }
  #scrollTopBtn:hover { background-color:rgba(0,0,0,.5); }
</style>
<?php // ★ (2026-10-31) 관리자 '라이브러리' 썸네일 크기(없으면 예전처럼 '즐겨찾기 책장' 값) — 정수 px만 들어감
$_mc_thumb_css = function_exists('mc_thumb_layout_css') ? mc_thumb_layout_css('series') : ''; if ($_mc_thumb_css !== ''): ?>
<style id="mcThumbLayout"><?php echo $_mc_thumb_css; ?></style>
<?php endif; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-11-05 23:15) 아이콘 변환을 본문보다 먼저 시작 — 예전 이모지가 잠깐 보였다가 바뀌던 것(페이지 끝의 같은 스크립트는 다시 실행되지 않음) */ ?>
</head>
<body>
<div class="lib-wrap">
  <div class="lib-top">
    <a id="libBack" class="btn btn-sm btn-outline-secondary" href="index.php<?php echo $bidx_query; ?>">← <?php echo __h('fav_shelf_back'); ?></a>
    <a class="btn btn-sm btn-outline-secondary" href="browse.php<?php echo $bidx_query; ?>"><svg class="mc-ico" width="15" height="15" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect width="8" height="18" x="3" y="3" rx="1"/><path d="M7 3v18"/><path d="M20.4 18.9c.2.5-.1 1.1-.6 1.3l-1.9.7c-.5.2-1.1-.1-1.3-.6L11.1 5.1c-.2-.5.1-1.1.6-1.3l1.9-.7c.5-.2 1.1.1 1.3.6Z"/></svg> <?php echo __h('lib_browse'); ?></a><?php // ★ (2026-11-04 00:29) 서재 아이콘(다른 화면과 같게, 예전 🔎) / ★ (2026-11-04 09:40) 📚 이모지는 다른 그림(세로줄 넷)으로 바뀌어 browse 서재 제목과 같은 '꽂힌 책' 선 그림으로 ?>
    <span class="spacer"></span>
  </div>

<?php if (!$db): ?>
  <div class="lib-empty"><?php echo __h('lib_no_sqlite'); ?></div>
<?php elseif (!$series): ?>
  <div class="lib-empty"><?php echo __h('lib_series_not_found'); ?>
    <?php if ($from_path !== '' && mc_lib_can_access($bidx, $from_path)): ?>
      <div class="mt-3"><a class="btn btn-primary btn-sm" href="<?php echo h(mc_lib_view_url($bidx, $from_path)); ?>"><?php echo __h('lib_open_viewer'); ?></a></div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="sr-head">
    <div class="sr-cover"><img src="<?php echo h($cover); ?>" alt="" onerror="this.style.visibility='hidden'"></div>
    <div class="sr-info">
      <h1 id="srName"><?php echo h($series['name']); ?><?php if ($series['status'] !== '' && isset($status_label[$series['status']])): ?><span class="sr-status <?php echo h($series['status']); ?>"><?php echo h($status_label[$series['status']]); ?></span><?php endif; ?></h1>
      <div class="sr-meta"><?php echo __h('lib_vol_count', count($vols)); ?> · <?php echo __h('lib_read_count', $read_count, count($vols)); ?></div>
      <?php
        // ★ (2026-11-02 21:48) 서재에서 다른 폴더 하나를 골라 둔 채면 '전체'로 — 그 폴더 범위로 찾아 0건·칩 없음이 됐음.
        //   전체·이 폴더를 보던 중이면 범위를 건드리지 않음(scope 는 세션에 기억되므로 늘 붙이면 보던 범위가 바뀜)
        $__ss = $_SESSION['mc_lib_scope'] ?? 'all';
        $__chip_scope = (is_string($__ss) && $__ss !== 'all' && $__ss !== (string)(int)$bidx) ? '&amp;scope=all' : '';
      ?>
      <?php foreach (['author' => 'lib_author', 'group' => 'lib_group', 'genre' => 'lib_genre', 'tag' => 'lib_tag'] as $kind => $lk): if (empty($terms[$kind])) continue; ?>
      <div class="chips"><span class="chip-label"><?php echo __h($lk); ?></span>
        <?php foreach ($terms[$kind] as $t): ?>
          <a class="chip chip-<?php echo $kind; ?>" href="browse.php?<?php echo $kind; ?>=<?php echo (int)$t['id']; ?>&amp;bidx=<?php echo (int)$bidx; ?><?php echo $__chip_scope; ?>"><?php echo h($t['name']); ?></a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <?php if (trim($series['description']) !== ''): ?><div class="sr-desc"><?php echo h($series['description']); ?></div><?php endif; ?>
      <div class="sr-actions">
        <?php if ($resume): ?>
          <a id="srResume" class="btn btn-warning btn-sm" href="<?php echo h(mc_lib_view_url((int)$resume[0]['bidx'], $resume[0]['path'], $resume[1])); ?>">▶ <?php echo __h('lib_resume'); ?> · <?php echo h(mc_lib_vol_title($resume[0], $implicit_one)); ?></a>
        <?php endif; ?>
        <?php if ($first): ?>
          <a class="btn btn-primary btn-sm" href="<?php echo h(mc_lib_view_url((int)$first['bidx'], $first['path'], null, true)); /* ★ (2026-11-03 07:51) 처음부터는 정말 처음부터(이어 보기 기록이 있으면 뷰어가 그 자리로 열었음) */ ?>">⏮ <?php echo __h('lib_from_start'); ?></a>
          <a class="btn btn-outline-secondary btn-sm" href="index.php?dir=<?php echo encode_url($first['folder']); ?>&amp;bidx=<?php echo (int)$first['bidx']; ?>">📁 <?php echo __h('lib_open_folder'); ?></a>
        <?php endif; ?>
        <?php if ($is_admin): ?>
          <button type="button" class="btn btn-outline-dark btn-sm" id="srEditBtn">✏️ <?php echo __h('lib_edit'); ?></button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="vol-bar">
    <h2><?php echo __h('lib_volumes'); ?></h2>
    <?php if ($dup_extra > 0): ?>
    <label class="vol-duptoggle mb-0" title="<?php echo __h('lib_dup_help'); ?>"><input type="checkbox" id="volShowDups"> <?php echo __h('lib_dup_show', $dup_extra); ?></label>
    <?php endif; ?>
    <select id="volSort" class="form-control form-control-sm" style="width:auto;" aria-label="<?php echo __h('fav_shelf_sort'); ?>">
      <option value="asc"><?php echo __h('lib_sort_asc'); ?></option>
      <option value="desc"><?php echo __h('lib_sort_desc'); ?></option>
    </select>
    <button type="button" class="lib-view-btn" data-view="grid" title="<?php echo __h('fav_shelf_view_grid'); ?>" aria-label="<?php echo __h('fav_shelf_view_grid'); ?>"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/></svg></button>
    <button type="button" class="lib-view-btn" data-view="list" title="<?php echo __h('fav_shelf_view_list'); ?>" aria-label="<?php echo __h('fav_shelf_view_list'); ?>"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zm-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg></button>
  </div>

  <div id="vols" class="vols-grid">
  <?php
  // 표시 이름 ★ (2026-10-29): 권 표기 통일(v1·1巻·01 → 1권, ch12·12話 → 12화) — 부제는 붙이지 않음(번외·외전만), 번호 없는 본편은 1권, 그 밖은 부제·제목(원본 파일명·폴더는 목록 보기 아래 줄)
  $vol_name = function ($v) use ($implicit_one) { return mc_lib_vol_title($v, $implicit_one); };
  // ★ (2026-11-04 01:28) HTML 로 돌려줌 — 파일명·폴더 이름만 mc-noico 로 감싸고 일부러 넣은 📁 는 아이콘으로(통째로 감쌌더니 📁 도 컬러 이모지로 남았음)
  $vol_file = function ($v, $is_copy) {
      $t = '<span class="mc-noico">' . h($v['filename']) . '</span>';
      if ((int)($v['pages'] ?? 0) > 0) $t .= ' · ' . h(__('lib_pages', (int)$v['pages']));
      $t .= ' · 📁 <span class="mc-noico">' . h($v['folder'] === '' ? '/' : $v['folder']) . '</span>'; // ★ (2026-10-29) 목록 보기 아래 줄: 실제 파일명 · 쪽수 · 폴더 경로(사본이 아니어도)
      return $t;
  };
  foreach ($vols as $i => $v):
      $name = $vol_name($v);
      $url = mc_lib_view_url((int)$v['bidx'], $v['path'], (!$v['_fin'] && $v['_page'] > 0) ? $v['_page'] : null);
      $ncopy = count($v['_copies']);
  ?>
    <a class="vol" data-i="<?php echo (int)$i; ?>" data-j="0" href="<?php echo h($url); ?>" title="<?php echo h($v['filename']); ?>">
      <div class="vol-cover">
        <img src="<?php echo h(mc_lib_thumb_url((int)$v['bidx'], $v['path'])); ?>" alt="" loading="lazy" decoding="async" onerror="this.style.visibility='hidden'">
        <?php if ($v['_fin']): ?><span class="vol-fin" title="<?php echo __h('lib_finished'); ?>">✓</span><?php endif; ?>
        <?php if ($ncopy > 1): ?><span class="vol-dupn" title="<?php echo __h('lib_dup_badge', $ncopy); ?>">×<?php echo (int)$ncopy; ?></span><?php endif; ?>
        <?php if ($v['_pct'] > 0 && !$v['_fin']): ?><div class="vol-prog"><span style="width:<?php echo (int)$v['_pct']; ?>%"></span></div><?php endif; ?>
      </div>
      <div class="vol-text">
        <div class="vol-name"><?php echo h($name); ?><?php if ($v['_pct'] > 0 && !$v['_fin']): ?><span class="vol-pct"><?php echo (int)$v['_pct']; ?>%</span><?php endif; ?></div>
        <div class="vol-file"><?php echo $vol_file($v, $ncopy > 1); ?></div><?php // ★ (2026-11-04 00:29) 파일 이름 속 이모지는 그대로(위 $vol_file 이 h() 처리) ?>
        <div class="vol-chips"><?php // ★ (2026-11-04 17:56) 목록 보기: 표지 위 ✓·×2 대신 제목 아래 한 줄(그리드 보기는 표지 위 그대로) ?><?php if ($v['_fin']): ?><span class="vol-cf badge">✓ <?php echo __h('lib_finished'); ?></span><?php endif; ?><?php if ($ncopy > 1): ?><span class="vol-cd badge" title="<?php echo __h('lib_dup_badge', $ncopy); ?>">×<?php echo (int)$ncopy; ?></span><?php endif; ?></div>
      </div>
    </a>
    <?php foreach (array_slice($v['_copies'], 1) as $j => $c): // 같은 책의 다른 사본 — '사본도 보기'를 켰을 때만 보임 ?>
    <a class="vol vol-copy" data-i="<?php echo (int)$i; ?>" data-j="<?php echo (int)$j + 1; ?>" href="<?php echo h(mc_lib_view_url((int)$c['bidx'], $c['path'])); ?>" title="<?php echo h($c['filename']); ?>">
      <div class="vol-cover">
        <img src="<?php echo h(mc_lib_thumb_url((int)$c['bidx'], $c['path'])); ?>" alt="" loading="lazy" decoding="async" onerror="this.style.visibility='hidden'">
        <span class="vol-copytag"><?php echo __h('lib_dup_copy'); ?></span>
      </div>
      <div class="vol-text">
        <div class="vol-name"><?php echo h($vol_name($c)); ?></div>
        <div class="vol-file"><?php echo $vol_file($c, true); ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<button id="scrollTopBtn" title="<?php echo __h('ui_scroll_top'); ?>" style="display:none;">▲</button>

<?php if ($series && $is_admin) { $vols = $all_vols; require __DIR__ . '/library_edit_form.php'; } // 편집 창은 사본 포함 전체 파일 ?>

<script>
(function () {
  function getPref(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }
  function setPref(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  var box = document.getElementById('vols');
  var btns = document.querySelectorAll('.lib-view-btn');
  function applyView(v) {
    if (v !== 'list') v = 'grid';
    if (box) { box.classList.remove('vols-list', 'vols-grid'); box.classList.add(v === 'list' ? 'vols-list' : 'vols-grid'); } // show-dups 유지
    for (var i = 0; i < btns.length; i++) { var on = btns[i].getAttribute('data-view') === v; btns[i].classList.toggle('active', on); btns[i].setAttribute('aria-pressed', on ? 'true' : 'false'); }
  }
  for (var i = 0; i < btns.length; i++) btns[i].addEventListener('click', function () { var v = this.getAttribute('data-view'); applyView(v); setPref('mcLibVolView', v); try { this.blur(); } catch (e) {} });
  applyView(getPref('mcLibVolView', 'grid'));
  var sortSel = document.getElementById('volSort');
  function applySort(m) {
    if (!box) return;
    var items = Array.prototype.slice.call(box.querySelectorAll('.vol'));
    items.sort(function (a, b) {
      var x = +a.getAttribute('data-i'), y = +b.getAttribute('data-i');
      if (x !== y) return m === 'desc' ? y - x : x - y;
      return (+a.getAttribute('data-j') || 0) - (+b.getAttribute('data-j') || 0); // 사본은 항상 대표 바로 뒤
    });
    for (var k = 0; k < items.length; k++) box.appendChild(items[k]);
  }
  if (sortSel) { var s = getPref('mcLibVolSort', 'asc'); if (s !== 'desc') s = 'asc'; sortSel.value = s; applySort(s); sortSel.addEventListener('change', function () { applySort(this.value); setPref('mcLibVolSort', this.value); }); }
  var dupChk = document.getElementById('volShowDups');
  if (dupChk && box) {
    dupChk.checked = getPref('mcLibShowDups', '0') === '1';
    box.classList.toggle('show-dups', dupChk.checked);
    dupChk.addEventListener('change', function () { box.classList.toggle('show-dups', this.checked); setPref('mcLibShowDups', this.checked ? '1' : '0'); });
  }
  var top = document.getElementById('scrollTopBtn');
  if (top) {
    window.addEventListener('scroll', function () { top.style.display = (document.documentElement.scrollTop > 200 || document.body.scrollTop > 200) ? 'block' : 'none'; }, { passive: true });
    top.onclick = function () { window.scrollTo({ top: 0, behavior: 'smooth' }); };
  }
  var back = document.getElementById('libBack');
  if (back) back.addEventListener('click', function (ev) {
    try { var ref = document.referrer ? new URL(document.referrer) : null;
      if (ref && ref.origin === location.origin && history.length > 1) { ev.preventDefault(); history.back(); } } catch (e) {}
  });
})();
</script>
<?php render_darkmode_script(); ?>
<?php if (function_exists('mc_lib_auto_tick_script')) mc_lib_auto_tick_script((int)$bidx); // ★ (2026-10-30) 자동 스캔 확인(필요할 때만) ?>
<?php render_auto_logout_script(); ?>
<?php require_once __DIR__ . '/privacy_shield.php'; ?>
<script>
// ★ (2026-11-02 01:43) 뷰어에서 읽고 뒤로 오면 읽은 기록(%·다 읽음·읽는 중)을 바로 반영 — 예전엔 새로고침해야 바뀌었음
//   뷰어가 위치를 저장할 때 표시(mc_lib_dirty)를 남김 → 뒤로가기(bfcache·캐시)로 이 화면이 다시 보일 때만 잠깐 뒤 새로 읽음(저장이 끝날 시간)
(function () {
  function navType() { try { var e = performance.getEntriesByType('navigation'); return (e && e[0] && e[0].type) || ''; } catch (x) { return ''; } }
  window.addEventListener('pageshow', function (e) {
    var d = null; try { d = sessionStorage.getItem('mc_lib_dirty'); } catch (x) {}
    if (!d) return;
    try { sessionStorage.removeItem('mc_lib_dirty'); } catch (x) {}
    if (e.persisted || navType() === 'back_forward') setTimeout(refreshProgress, 600);
  });
  // ★ (2026-11-02 16:18) 새로고침하지 않고 권마다 %·✓·이어 볼 쪽 주소, '읽음 N/M', 이어보기 단추만 바꿈(스크롤·보기 방식 그대로)
  var PROG_URL = <?php echo json_encode('series.php?sid=' . (int)$sid . '&bidx=' . (int)$bidx . '&prog=1', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  function refreshProgress() {
    fetch(PROG_URL, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j || !j.ok || !Array.isArray(j.vols)) return;
      j.vols.forEach(function (d) {
        var a = document.querySelector('#vols .vol[data-i="' + d[0] + '"][data-j="0"]'); if (!a) return;
        var pct = +d[1], fin = !!d[2], cv = a.querySelector('.vol-cover'), nm = a.querySelector('.vol-name');
        if (d[3]) a.setAttribute('href', d[3]);
        if (cv) {
          var ck = cv.querySelector('.vol-fin');
          if (fin) { if (!ck) { ck = document.createElement('span'); ck.className = 'vol-fin'; ck.textContent = '✓'; cv.insertBefore(ck, cv.children[1] || null); } } else if (ck) ck.remove();
          var pg = cv.querySelector('.vol-prog');
          if (pct > 0 && !fin) { if (!pg) { pg = document.createElement('div'); pg.className = 'vol-prog'; pg.appendChild(document.createElement('span')); cv.appendChild(pg); } pg.firstChild.style.width = pct + '%'; } else if (pg) pg.remove();
        }
        var cs = a.querySelector('.vol-chips'); // ★ (2026-11-04 17:56) 목록 보기 제목 아래 '✓ 다 읽음'도 같이
        if (cs) { var cf = cs.querySelector('.vol-cf'); if (fin) { if (!cf) { cf = document.createElement('span'); cf.className = 'vol-cf badge'; cf.textContent = '✓ ' + <?php echo json_encode(__('lib_finished'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>; cs.insertBefore(cf, cs.firstChild); } } else if (cf) cf.remove(); }
        if (nm) {
          var sp = nm.querySelector('.vol-pct');
          if (pct > 0 && !fin) { if (!sp) { sp = document.createElement('span'); sp.className = 'vol-pct'; nm.appendChild(sp); } sp.textContent = pct + '%'; } else if (sp) sp.remove();
        }
      });
      var meta = document.querySelector('.sr-meta'); if (meta && j.meta) meta.textContent = String(j.meta);
      var rs = document.getElementById('srResume');
      if (j.resume) {
        if (!rs) { var acts = document.querySelector('.sr-actions'); if (acts) { rs = document.createElement('a'); rs.id = 'srResume'; rs.className = 'btn btn-warning btn-sm'; acts.insertBefore(rs, acts.firstChild); } }
        if (rs) { rs.setAttribute('href', j.resume[0]); rs.textContent = String(j.resume[1]); }
      } else if (rs) rs.remove();
    }).catch(function () {});
  }
})();
</script>
<?php if (function_exists('mc_lib_scroll_script')) echo mc_lib_scroll_script(); /* ★ (2026-11-02 16:21) 돌아왔을 때 보던 위치 */ ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
<?php if (function_exists('mc_perf_script')) echo mc_perf_script('series'); // ★ (2026-11-03 14:10) 속도 기록(src/perf) ?>
<?php if (function_exists('mc_back_origin_list_js')) echo mc_back_origin_list_js(); /* ★ (2026-11-05) 뷰어 뒤로가기가 이 화면(주소·스크롤 위치)으로 돌아오게 — function.php */ ?>
</body>
</html>
