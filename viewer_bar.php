<?php
/**
 * myComix 뷰어 위 막대(툰모드·북모드 공통) — viewer.php 가 <body> 바로 뒤에서 include
 * ★ (2026-11-01) 펜닐 선택 시안 S2 '보기 버튼 노출형'
 *
 *  · 위 막대 하나 · 항상 보임:  ← 목록 │ 제목 + 위치 │ [자주 쓰는 단추] │ 설정(톱니)
 *      북모드 = [보기 ▾](한 쪽·1|2·2|1·▤←·→▤ + 넘김 방향 반대로) + [툰모드] / 툰모드 = [📖 북모드]
 *      설정 = 책갈피 · 즐겨찾기 · 밝기 · (북)전체화면 · (툰)보기 방식 · 표지로 지정 · 이전/다음 권
 *  ★ (2026-11-01 07:35) 펜닐 요청: ⋯ → 설정(톱니) 아이콘 · 북모드에도 [툰모드] 단추를 막대에 바로 · 제목(파일명)은 자르지 않고 다 보임(넘치면 줄바꿈, 막대 높이 자동)
 *  ★ (2026-11-01 07:53) 펜닐 요청: 제목을 끌어서 고르기(복사) 가능 + 누르면 제목 전체 복사(알림), 따로 아이콘 없이
 *      · 그림마다 오른쪽 아래 쪽 번호 원복 · 보기 방식·툰/북 모드·밝기·넘김 방향을 브라우저(기기)마다 기억(쿠키 — 서버가 열 때 읽음, 책마다 X)
 *  · 예전 위 줄(사이트 이름·톱니)·아래 줄(이전/목록/즐겨찾기/다음·보기 단추)은 지우지 않고 숨김 — 그 안의 함수(밝기·책갈피·표지 지정 등)를 그대로 부름
 *  · 새로 넣은 것 셋: 툰모드 진행 줄(누르면 그 위치) · 툰모드 권 끝 '다음 권' · 북모드 쪽 번호 누르면 쪽 번호 입력
 *  · 압축 파일(zip·cbz·rar·7z…)과 이미지 폴더에서만 — PDF·동영상 화면은 그대로
 *
 * 쓰는 변수(viewer.php 에서 이미 계산): $mode $type $getfile $title $link_dir $page $current_bidx $totalfile $now $pre $next
 *                                       $_favorites_arr $_param_filetype $pageorder
 */
if (!isset($mode, $type)) return;

$_mcv_ft = ($_param_filetype === 'images' || $type === 'images') ? 'filetype=images&' : '';
$_mcv_enc = encode_url($getfile);
$_mcv_list = './index.php?dir=' . encode_url($link_dir) . '&page=' . (int)$page . '&bidx=' . (int)$current_bidx;
$_mcv_has_prev = !($now == '0' || $pre < 0 || !isset($totalfile[$pre]));
$_mcv_has_next = isset($totalfile[$next]) && count($totalfile) > $next;
$_mcv_prev = $_mcv_has_prev ? './viewer.php?file=' . encode_url($link_dir . '/' . $totalfile[$pre]) . '&bidx=' . (int)$current_bidx : '';
$_mcv_next = $_mcv_has_next ? './viewer.php?file=' . encode_url($link_dir . '/' . $totalfile[$next]) . '&bidx=' . (int)$current_bidx : '';
$_mcv_strip = function ($n) { return preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7|pdf)$/i', '', (string)$n); };
$_mcv_title = $_mcv_strip($title);
$_mcv_next_title = $_mcv_has_next ? $_mcv_strip($totalfile[$next]) : '';
$_mcv_order = (string)($pageorder['page_order'] ?? '0');
if (!in_array($_mcv_order, ['0', '1', '2', '3', '4'], true)) $_mcv_order = '0';
$_mcv_is_fav = is_array($_favorites_arr ?? null) && mc_rec_find($_favorites_arr, $current_bidx, $getfile) !== null; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 즐겨찾기만
// ★ (2026-11-03 00:10) 이 책에 저장한 책갈피 쪽(없으면 0) — 메뉴에 '책갈피 · N쪽 저장됨'으로(즐겨찾기처럼 상태가 보이게)
$_mcv_bm = (isset($bookmark_arr) && is_array($bookmark_arr)) ? (($__mcv_bk = mc_rec_find($bookmark_arr, $current_bidx, $getfile)) !== null ? $bookmark_arr[$__mcv_bk] : null) : null; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 책갈피만
$_mcv_bm_anchor = is_array($_mcv_bm) ? (string)($_mcv_bm['bookmark'] ?? '') : (is_string($_mcv_bm) ? $_mcv_bm : '');
$_mcv_bm_page = preg_match('/^image(\d+)$/', $_mcv_bm_anchor, $_mcv_bmm) ? (int)$_mcv_bmm[1] + 1 : 0;
$_mcv_book = ($mode === 'book');

$_mcv_cfg = [
    'mode'      => $_mcv_book ? 'book' : 'toon',
    'order'     => $_mcv_order,
    'file'      => $_mcv_enc,                // encode_url() 결과(주소에 그대로 씀)
    'favPath'   => (string)$getfile,          // 즐겨찾기 API 에 보내는 원래 경로
    'bidx'      => (int)$current_bidx,
    'ft'        => $_mcv_ft,
    'list'      => $_mcv_list,
    'prev'      => $_mcv_prev,
    'next'      => $_mcv_next,
    'nextTitle' => $_mcv_next_title,
    'title'     => (string)$_mcv_title,       // 제목 복사용(공백 그대로)
    'isFav'     => $_mcv_is_fav,
    't' => [
        'fav_add' => __('mcv2_fav_add'), 'fav_remove' => __('mcv2_fav_remove'),
        'goto' => __('mcv2_goto'), 'next_label' => __('mcv2_next_label'), 'saved' => __('mcv2_done'), 'bm_at' => __('mcv2_bm_at'), 'copied' => __('mcv2_copied'),
        'resume_on' => __('mcv2_resume_on'), 'resume_off' => __('mcv2_resume_off'),
        'port1_on' => __('mcv2_port1_on'), 'port1_off' => __('mcv2_port1_off'), // ★ (2026-11-04 18:00) 세로 화면은 한 쪽 · 화면 맞춤
        'solo_on' => __('mcv2_solo_on'), 'solo_off' => __('mcv2_solo_off'), 'shift_on' => __('mcv2_shift_on'), 'shift_off' => __('mcv2_shift_off'),
        'fit_tip' => ['screen' => __('mcv2_fit_tip_screen'), 'width' => __('mcv2_fit_tip_width'), 'height' => __('mcv2_fit_tip_height')],
        'mm_tip' => ['off' => __('mcv2_mm_tip_off'), 'all' => __('mcv2_mm_tip_all'), 'book' => __('mcv2_mm_tip_book')],
        'v' => [__('mcv2_v0'), __('mcv2_v1'), __('mcv2_v2'), __('mcv2_v3'), __('mcv2_v4')],
        'short' => [__('mcv2_s0'), '1|2', '2|1', '▤←', '→▤'],
    ],
];
$_mcv_ico = function ($d, $size = 20) {
    return '<svg class="mcv-svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
};
$_mcv_i = [
    'back'  => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
    'more'  => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
    'book'  => '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
    'mark'  => '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>',
    'star'  => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
    'sun'   => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
    'full'  => '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
    'prev'  => '<path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/>',
    'next'  => '<path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/>',
    'scroll'=> '<rect width="14" height="20" x="5" y="2" rx="2"/><path d="M9 6h6"/><path d="M9 10h6"/><path d="M9 14h6"/>',
    'image' => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
    'swap'  => '<path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/>',
    'port'  => '<rect width="12" height="20" x="6" y="2" rx="2"/><path d="M11 18h2"/>', // ★ (2026-11-04 18:00) 세로 화면은 한 쪽
    'solo'  => '<rect width="8" height="14" x="3" y="5" rx="1"/><rect width="10" height="14" x="12" y="5" rx="1" stroke-dasharray="2 2"/>', // ★ (2026-11-04 22:07) 표지·가로 그림은 혼자
    'shift' => '<path d="M4 12h14"/><path d="m14 8 4 4-4 4"/><rect width="4" height="10" x="2" y="7" rx="1"/>', // ★ (2026-11-04 22:07) 짝 한 쪽 밀기
    'fit'   => '<path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/>', // ★ (2026-11-04 18:00) 화면 맞춤
    'down'  => '<path d="m6 9 6 6 6-6"/>',
    'resume'=> '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
    'mode'  => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
    'copy'  => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>', // ★ (2026-11-02 16:55) 제목 복사
    'gear'  => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
];
// ★ (2026-11-01 23:37) 다시 열 때: 이어 보기 · 모드 기억(이 기기 쿠키 — function.php mc_mv_*)
$_mcv_resume_on = ((is_string($_COOKIE['mcv_resume'] ?? null) ? $_COOKIE['mcv_resume'] : '1')) !== '0';
$_mcv_mm = function_exists('mc_mv_modemem') ? mc_mv_modemem() : 'off'; // ★ (2026-11-02 01:01) 기본 끔
$_mcv_vlabel = [__('mcv2_v0'), __('mcv2_v1'), __('mcv2_v2'), __('mcv2_v3'), __('mcv2_v4')];
// ★ (2026-11-04 18:00) 북모드 보기 메뉴에 추가 — 세로 화면은 한 쪽(mcv_port1, 기본 끔) · 화면 맞춤(mcv_fit, 기본 화면) — 이 기기 쿠키, 기본값은 지금 동작 그대로
$_mcv_port1 = (($_COOKIE['mcv_port1'] ?? '') === '1');
$_mcv_fit = in_array(($_COOKIE['mcv_fit'] ?? ''), ['width', 'height'], true) ? $_COOKIE['mcv_fit'] : 'screen';
// ★ (2026-11-04 22:07) 펼침(1|2·2|1)일 때만: 표지·가로 그림은 혼자(mcv_solo, 기본 켬 — '0'이면 끔) · 이 책만 짝 한 쪽 밀기(localStorage, 책마다)
$_mcv_spread = in_array($_mcv_order, ['1', '2'], true);
$_mcv_solo = (($_COOKIE['mcv_solo'] ?? '') !== '0');
?>
<style>
/* ★ (2026-11-01) 뷰어 위 막대(시안 S2) */
:root { --mcv-h: 48px; } /* 막대 실제 높이(긴 제목이면 여러 줄 → js/viewer-bar.js 가 맞춤) */
@media (max-width: 767px) { :root { --mcv-h: 52px; } }
body.mcv2 > nav.navbar.fixed-top, body.mcv2 nav.viewer-bottom-nav { display: none !important; } /* 예전 위·아래 줄(지우지 않고 숨김) */
body.mcv2-toon { padding-top: var(--mcv-h); }
body.mcv2-book .lg-outer { top: var(--mcv-h) !important; height: calc(100% - var(--mcv-h)) !important; }
body.mcv2-book .lg-outer .lg-toolbar, body.mcv2-book .lg-outer .lg-sub-html { display: none !important; } /* 1초 뒤 숨던 위 막대·아래 줄 → 위 막대 하나로 */
.mcv-bar { position: fixed; left: 0; right: 0; top: 0; min-height: 48px; z-index: 1060; display: flex; align-items: center; gap: 4px;
  padding: 4px 6px; padding-top: max(4px, env(safe-area-inset-top, 0px)); padding-left: max(6px, env(safe-area-inset-left, 0px)); padding-right: max(6px, env(safe-area-inset-right, 0px)); font-family: system-ui, -apple-system, "Segoe UI", "Malgun Gothic", "Apple SD Gothic Neo", sans-serif; box-sizing: border-box;
  background: #fff; color: #202124; border-bottom: 1px solid #e3e5e8; -webkit-user-select: none; user-select: none; }
.mcv-bar.mcv-dark, html.dark-mode .mcv-bar, body.dark-mode .mcv-bar, [data-theme="dark"] .mcv-bar { background: #18181b; color: #e8eaed; border-bottom-color: #2c2c31; }
.mcv-bar * { box-sizing: border-box; }
.mcv-ib, .mcv-tb { border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; -webkit-tap-highlight-color: transparent; }
.mcv-ib { width: 40px; height: 40px; border-radius: 50%; padding: 0; }
.mcv-tb { height: 34px; padding: 0 12px; border-radius: 17px; gap: 4px; font-size: 13.5px; font-weight: 600; background: rgba(0,0,0,.06); white-space: nowrap; }
.mcv-dark .mcv-tb, html.dark-mode .mcv-tb, body.dark-mode .mcv-tb, [data-theme="dark"] .mcv-tb { background: rgba(255,255,255,.1); }
.mcv-ib:hover, .mcv-ib:focus-visible { background: rgba(127,127,127,.18); outline: none; }
.mcv-tb:hover, .mcv-tb:focus-visible { background: rgba(127,127,127,.22); outline: none; }
.mcv-ib[aria-expanded="true"], .mcv-tb[aria-expanded="true"] { background: rgba(26,115,232,.18); }
.mcv-svg { display: block; flex: 0 0 auto; }
.mcv-title { flex: 1 1 auto; min-width: 0; display: flex; align-items: center; gap: 10px; padding: 2px 4px; }
/* ★ (2026-11-02 16:55) 제목은 한 줄(넘치면 …) — 여러 줄로 막대가 두꺼워지던 것. 누르면 아래 창에 전체 제목 + [제목 복사] 단추
   (전에는 누르면 바로 복사라 끌어 고르기·터치와 겹쳤음) */
.mcv-name { font-size: 15px; font-weight: 700; line-height: 1.3; min-width: 0; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; border-radius: 6px; }
.mcv-name:hover { background: rgba(127,127,127,.12); }
.mcv-name[aria-expanded="true"] { background: rgba(26,115,232,.18); }
.mcv-ttl-pop { padding: 10px; }
.mcv-ttl { font-size: 15px; font-weight: 700; line-height: 1.4; padding: 2px 6px 8px; word-break: keep-all; overflow-wrap: anywhere; white-space: normal;
  -webkit-user-select: text; user-select: text; -webkit-touch-callout: default; cursor: text; }
.mcv-pos { font-size: 13px; opacity: .78; font-variant-numeric: tabular-nums; white-space: nowrap; flex: 0 0 auto; border: 0; background: transparent; color: inherit; padding: 2px 6px; border-radius: 6px; font-family: inherit; }
button.mcv-pos { cursor: pointer; }
button.mcv-pos:hover { background: rgba(127,127,127,.18); }
.mcv-pos-sep { margin-left: auto; }
@media (max-width: 767px) {
  .mcv-title { flex-direction: column; align-items: flex-start; gap: 0; line-height: 1.2; }
  .mcv-name { font-size: 14.5px; max-width: 100%; }
  .mcv-ttl { font-size: 14.5px; }
  .mcv-bar { min-height: 52px; }
  .mcv-pos { font-size: 11.5px; padding: 0; }
  .mcv-pos-sep { margin-left: 0; } /* 휴대폰: 제목 아래 왼쪽 */
  .mcv-tb { padding: 0 10px; }
  .mcv-tb .mcv-tb-lbl { display: none; }
}
/* 제목이 2줄을 넘으면(좁은 화면 + 긴 파일명) 두 줄 배치: 1줄 = ← + 제목(폭 전부), 2줄 = 위치 + 단추 — js 가 판단해 .mcv-2row 를 붙임 */
.mcv-bar.mcv-2row { flex-wrap: wrap; row-gap: 0; }
.mcv-bar.mcv-2row .mcv-title { display: contents; }
.mcv-bar.mcv-2row .mcv-name { order: 1; flex: 1 1 calc(100% - 48px); padding: 6px 4px 2px; }
.mcv-bar.mcv-2row .mcv-pos { order: 2; margin: 0 auto 0 44px; }
.mcv-bar.mcv-2row > .mcv-tb, .mcv-bar.mcv-2row > button.mcv-ib { order: 3; }
.mcv-prog { position: fixed; left: 0; right: 0; top: var(--mcv-h); height: 12px; z-index: 1059; cursor: pointer; }
.mcv-prog::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 3px; background: rgba(0,0,0,.08); }
html.dark-mode .mcv-prog::before, body.dark-mode .mcv-prog::before, [data-theme="dark"] .mcv-prog::before { background: rgba(255,255,255,.1); }
.mcv-prog i { position: absolute; left: 0; top: 0; height: 3px; width: 0; background: #1a73e8; transition: width .15s linear; }
.mcv-pop { position: fixed; z-index: 1065; min-width: 210px; max-width: calc(100vw - 16px); max-height: calc(100vh - var(--mcv-h) - 16px); overflow-y: auto;
  background: #fff; color: #202124; border: 1px solid #e3e5e8; border-radius: 12px; padding: 6px; box-shadow: 0 10px 28px rgba(0,0,0,.18);
  font-family: system-ui, -apple-system, "Segoe UI", "Malgun Gothic", "Apple SD Gothic Neo", sans-serif; font-size: 14px; }
.mcv-pop[hidden] { display: none; }
.mcv-pop.mcv-dark, html.dark-mode .mcv-pop, body.dark-mode .mcv-pop, [data-theme="dark"] .mcv-pop { background: #26262b; color: #e8eaed; border-color: #38383f; box-shadow: 0 10px 28px rgba(0,0,0,.5); }
.mcv-mi { display: flex; align-items: center; gap: 12px; width: 100%; min-height: 42px; padding: 0 12px; border: 0; background: transparent; color: inherit; font: inherit; text-align: left; border-radius: 8px; cursor: pointer; text-decoration: none !important; }
.mcv-mi:hover, .mcv-mi:focus-visible { background: rgba(127,127,127,.14); outline: none; color: inherit; }
.mcv-mi[aria-disabled="true"] { opacity: .38; pointer-events: none; }
.mcv-mi .mcv-svg { opacity: .8; }
.mcv-mi .mcv-r { margin-left: auto; display: inline-flex; align-items: center; gap: 4px; }
.mcv-mi.mcv-on { color: #1a73e8; font-weight: 600; }
.mcv-dark .mcv-mi.mcv-on, html.dark-mode .mcv-mi.mcv-on, body.dark-mode .mcv-mi.mcv-on, [data-theme="dark"] .mcv-mi.mcv-on { color: #8ab4f8; }
.mcv-mi.mcv-fav .mcv-svg { color: #f59f00; opacity: 1; }
.mcv-mi.mcv-fav.mcv-on .mcv-svg { fill: #f59f00; }
.mcv-sw { width: 34px; height: 20px; border-radius: 10px; background: #bdc1c6; position: relative; flex: 0 0 auto; transition: background .15s; }
.mcv-sw::after { content: ""; position: absolute; left: 2px; top: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: left .15s; }
.mcv-mi[aria-checked="true"] .mcv-sw { background: #1a73e8; }
.mcv-mi[aria-checked="true"] .mcv-sw::after { left: 16px; }
.mcv-hd { font-size: 11.5px; opacity: .6; padding: 6px 12px 2px; }
.mcv-hr { height: 1px; background: rgba(127,127,127,.22); margin: 4px 4px; }
.mcv-step { width: 32px; height: 32px; border-radius: 50%; border: 1px solid rgba(127,127,127,.35); background: transparent; color: inherit; font-size: 17px; line-height: 1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
.mcv-step.mcv-zero { font-size: 12px; width: auto; padding: 0 9px; border-radius: 16px; }
.mcv-step.mcv-seg { height: 30px; } /* ★ (2026-11-01) 모드 기억: 끔·전체·책마다 */
.mcv-step.mcv-seg.mcv-on { background: #1a73e8; border-color: #1a73e8; color: #fff; }
.mcv-goto { display: flex; gap: 6px; padding: 6px; align-items: center; }
.mcv-goto input { width: 90px; height: 38px; border: 1px solid #c4c7c5; border-radius: 8px; padding: 0 10px; font-size: 15px; background: transparent; color: inherit; }
.mcv-goto button { height: 38px; padding: 0 14px; border: 0; border-radius: 8px; background: #1a73e8; color: #fff; font-weight: 600; cursor: pointer; }
.mcv-goto span { font-size: 13px; opacity: .7; white-space: nowrap; }
.mcv-toast { position: fixed; left: 50%; top: calc(var(--mcv-h) + 12px); transform: translateX(-50%); z-index: 1070; background: rgba(32,33,36,.92); color: #fff; font-size: 13px;
  padding: 7px 14px; border-radius: 16px; max-width: min(520px, calc(100vw - 32px)); white-space: normal; word-break: keep-all; overflow-wrap: anywhere; width: max-content; text-align: center; line-height: 1.45; overflow: hidden; pointer-events: none; opacity: 0; transition: opacity .2s; /* ★ (2026-11-03 01:24) 긴 안내는 줄바꿈(전엔 휴대폰에서 '…'로 잘렸음) */
  font-family: system-ui, -apple-system, "Segoe UI", "Malgun Gothic", sans-serif; }
.mcv-toast.mcv-show { opacity: 1; }
.mcv-end { position: fixed; left: 50%; bottom: calc(16px + env(safe-area-inset-bottom, 0px)); transform: translateX(-50%) translateY(20px); z-index: 1058; display: flex; align-items: center; gap: 10px;
  max-width: calc(100vw - 32px); background: #fff; color: #202124; border: 1px solid #e3e5e8; border-radius: 26px; padding: 6px 6px 6px 16px; box-shadow: 0 6px 20px rgba(0,0,0,.18);
  font-family: system-ui, -apple-system, "Segoe UI", "Malgun Gothic", sans-serif; font-size: 14px; opacity: 0; pointer-events: none; transition: opacity .2s, transform .2s; }
.mcv-end { width: max-content; } /* ★ (2026-11-02 19:06) left:50% 라 휴대폰 세로에서 폭이 화면 절반으로 줄어 다음 권 제목이 잘렸음(최대 폭은 그대로) */
.mcv-end.mcv-show { opacity: 1; pointer-events: auto; transform: translateX(-50%) translateY(0); }
html.dark-mode .mcv-end, body.dark-mode .mcv-end, [data-theme="dark"] .mcv-end { background: #26262b; color: #e8eaed; border-color: #38383f; }
.mcv-end-t { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.mcv-end a { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 4px; height: 36px; padding: 0 14px; border-radius: 18px; background: #1a73e8; color: #fff !important; font-weight: 600; text-decoration: none !important; }
/* ★ (2026-11-02 16:55) 메뉴가 휴대폰 세로·가로 화면에 맞게 — 보이는 높이(dvh: 주소창 제외) 안에서 스크롤, 낮은 화면은 줄 간격을 줄이고
   가로 모드(낮고 넓음)는 설정 메뉴를 두 칸으로. 메뉴가 열려 있는 동안 오른쪽 아래 다크모드·맨 위로 단추는 숨김(메뉴를 가렸음) */
@supports (height: 100dvh) { .mcv-pop { max-height: calc(100dvh - var(--mcv-h) - 16px - env(safe-area-inset-bottom, 0px)); } }
.mcv-pop { overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
html.mcv-popopen #darkmode-toggle, html.mcv-popopen .darkmode-toggle, html.mcv-popopen #scrollTopBtn { visibility: hidden !important; pointer-events: none !important; }
@media (max-height: 700px) { .mcv-mi { min-height: 38px; } .mcv-hd { padding: 4px 12px 1px; } .mcv-hr { margin: 3px 4px; } }
@media (max-height: 520px) { .mcv-mi { min-height: 34px; gap: 10px; } .mcv-pop:not(.mcv-ttl-pop) { padding: 4px; } .mcv-step { width: 30px; height: 30px; } .mcv-step.mcv-seg { height: 28px; } }
@media (max-height: 520px) and (min-width: 540px) {
  /* 위에서 아래로 채우고 다음 칸(줄 수는 js 가 항목 수의 절반으로) — 높이가 모자라면 세로 스크롤 */
  #mcvMorePop:not([hidden]) { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); column-gap: 6px; align-content: start; width: min(620px, calc(100vw - 16px)); }
  #mcvMorePop > .mcv-hr { display: none; }
}
</style>
<div class="mcv-bar<?php echo $_mcv_book ? ' mcv-dark' : ''; ?>" id="mcvBar" role="toolbar" aria-label="<?php echo __h('mcv2_bar'); ?>">
  <a class="mcv-ib" id="mcvBack" data-mc-back href="<?php echo h($_mcv_list); ?>" title="<?php echo __h('mcv2_list'); ?>" aria-label="<?php echo __h('mcv2_list'); ?>"><?php echo $_mcv_ico($_mcv_i['back'], 22); ?></a>
  <div class="mcv-title">
    <span class="mcv-name" id="mcvName" role="button" tabindex="0" aria-haspopup="dialog" aria-expanded="false" title="<?php echo __h('mcv2_copy_hint'); ?>"><?php echo h($_mcv_title); ?></span>
    <?php if ($_mcv_book): ?>
    <button type="button" class="mcv-pos mcv-pos-sep" id="mcvPos" title="<?php echo __h('mcv2_goto'); ?>" aria-label="<?php echo __h('mcv2_goto'); ?>" aria-haspopup="dialog" aria-expanded="false">&nbsp;</button>
    <?php else: ?>
    <span class="mcv-pos mcv-pos-sep" id="mcvPos">&nbsp;</span>
    <?php endif; ?>
  </div>
  <?php if ($_mcv_book): ?>
  <button type="button" class="mcv-tb" id="mcvView" aria-haspopup="menu" aria-expanded="false" title="<?php echo __h('mcv2_view'); ?>"><span id="mcvViewLbl"><?php echo h($_mcv_cfg['t']['short'][(int)$_mcv_order]); ?></span><?php echo $_mcv_ico($_mcv_i['down'], 16); ?></button>
  <button type="button" class="mcv-tb" id="mcvToToon" title="<?php echo __h('mcv2_to_toon_btn'); ?>" aria-label="<?php echo __h('mcv2_to_toon_btn'); ?>"><?php echo $_mcv_ico($_mcv_i['scroll'], 18); ?><span class="mcv-tb-lbl"><?php echo __h('mcv2_to_toon_btn'); ?></span></button>
  <?php else: ?>
  <button type="button" class="mcv-tb" id="mcvToBook" title="<?php echo __h('mcv2_to_book'); ?>" aria-label="<?php echo __h('mcv2_to_book'); ?>"><?php echo $_mcv_ico($_mcv_i['book'], 18); ?><span class="mcv-tb-lbl"><?php echo __h('mcv2_to_book'); ?></span></button>
  <?php endif; ?>
  <button type="button" class="mcv-ib" id="mcvMore" aria-haspopup="menu" aria-expanded="false" title="<?php echo __h('mcv2_settings'); ?>" aria-label="<?php echo __h('mcv2_settings'); ?>"><?php echo $_mcv_ico($_mcv_i['gear'], 22); ?></button>
</div>
<?php if (!$_mcv_book): ?>
<div class="mcv-prog" id="mcvProg" title="<?php echo __h('mcv2_prog'); ?>"><i></i></div>
<?php endif; ?>

<?php // 보기 방식 메뉴(북모드는 [보기 ▾], 툰모드는 ⋯ 안에) ?>
<?php ob_start(); ?>
  <div class="mcv-hd"><?php echo __h('mcv2_view'); ?></div>
  <?php foreach ($_mcv_vlabel as $_k => $_lbl): ?>
  <button type="button" class="mcv-mi<?php echo (string)$_k === $_mcv_order ? ' mcv-on' : ''; ?>" role="menuitemradio" aria-checked="<?php echo (string)$_k === $_mcv_order ? 'true' : 'false'; ?>" data-order="<?php echo $_k; ?>"><?php echo h($_lbl); ?><?php if ((string)$_k === $_mcv_order): ?><span class="mcv-r">✓</span><?php endif; ?></button>
  <?php endforeach; ?>
<?php $_mcv_view_items = ob_get_clean(); ?>

<?php if ($_mcv_book): ?>
<div class="mcv-pop mcv-dark" id="mcvViewPop" role="menu" hidden>
  <?php echo $_mcv_view_items; ?>
  <div class="mcv-hr"></div>
  <button type="button" class="mcv-mi" role="menuitemcheckbox" id="mcvReverse" aria-checked="<?php echo (($_COOKIE['viewer_reverse_nav'] ?? '') === '1') ? 'true' : 'false'; ?>"><?php echo $_mcv_ico($_mcv_i['swap']); ?><?php echo __h('mcv2_reverse'); ?><span class="mcv-r"><span class="mcv-sw"></span></span></button>
  <div class="mcv-hr"></div>
  <button type="button" class="mcv-mi" role="menuitemcheckbox" id="mcvPort1" aria-checked="<?php echo $_mcv_port1 ? 'true' : 'false'; ?>" title="<?php echo __h('mcv2_port1_tip'); ?>"><?php echo $_mcv_ico($_mcv_i['port']); ?><?php echo __h('mcv2_port1'); ?><span class="mcv-r"><span class="mcv-sw"></span></span></button>
  <?php if ($_mcv_spread): ?>
  <button type="button" class="mcv-mi" role="menuitemcheckbox" id="mcvSolo" aria-checked="<?php echo $_mcv_solo ? 'true' : 'false'; ?>" title="<?php echo __h('mcv2_solo_tip'); ?>"><?php echo $_mcv_ico($_mcv_i['solo']); ?><?php echo __h('mcv2_solo'); ?><span class="mcv-r"><span class="mcv-sw"></span></span></button>
  <button type="button" class="mcv-mi" role="menuitemcheckbox" id="mcvShift" aria-checked="false" title="<?php echo __h('mcv2_shift_tip'); ?>"><?php echo $_mcv_ico($_mcv_i['shift']); ?><?php echo __h('mcv2_shift'); ?><span class="mcv-r"><span class="mcv-sw"></span></span></button>
  <?php endif; ?>
  <div class="mcv-mi" role="radiogroup" id="mcvFit" aria-label="<?php echo __h('mcv2_fit'); ?>" style="cursor:default"><?php echo $_mcv_ico($_mcv_i['fit']); ?><?php echo __h('mcv2_fit'); ?><span class="mcv-r"><?php foreach (['screen', 'width', 'height'] as $_fv): ?><button type="button" class="mcv-step mcv-zero mcv-seg<?php echo $_mcv_fit === $_fv ? ' mcv-on' : ''; ?>" role="radio" aria-checked="<?php echo $_mcv_fit === $_fv ? 'true' : 'false'; ?>" data-fit="<?php echo $_fv; ?>" title="<?php echo __h('mcv2_fit_tip_' . $_fv); ?>"><?php echo __h('mcv2_fit_' . $_fv); ?></button><?php endforeach; ?></span></div>
</div>
<div class="mcv-pop mcv-dark" id="mcvGotoPop" role="dialog" aria-label="<?php echo __h('mcv2_goto'); ?>" hidden>
  <form class="mcv-goto" id="mcvGotoForm" novalidate><input type="number" id="mcvGotoIn" min="1" step="1" inputmode="numeric" aria-label="<?php echo __h('mcv2_goto'); ?>"><span id="mcvGotoMax"></span><button type="submit"><?php echo __h('mcv2_go'); ?></button></form>
</div>
<?php endif; ?>

<div class="mcv-pop<?php echo $_mcv_book ? ' mcv-dark' : ''; ?>" id="mcvMorePop" role="menu" hidden>
  <button type="button" class="mcv-mi<?php echo $_mcv_bm_page > 0 ? ' mcv-on' : ''; ?>" role="menuitem" data-act="bookmark" id="mcvBm"><?php echo $_mcv_ico($_mcv_i['mark']); ?><span id="mcvBmLbl"><?php echo $_mcv_bm_page > 0 ? h(__('mcv2_bm_at', $_mcv_bm_page)) : __h('mcv2_bookmark'); ?></span></button>
  <button type="button" class="mcv-mi mcv-fav<?php echo $_mcv_is_fav ? ' mcv-on' : ''; ?>" role="menuitem" data-act="fav" id="mcvFav"><?php echo $_mcv_ico($_mcv_i['star']); ?><span id="mcvFavLbl"><?php echo $_mcv_is_fav ? __h('mcv2_fav_remove') : __h('mcv2_fav_add'); ?></span></button>
  <div class="mcv-mi" role="group" aria-label="<?php echo __h('mcv2_bright'); ?>" style="cursor:default"><?php echo $_mcv_ico($_mcv_i['sun']); ?><?php echo __h('mcv2_bright'); ?><span class="mcv-r"><button type="button" class="mcv-step" data-act="bright-down" aria-label="<?php echo __h('mcv2_bright_down'); ?>">−</button><button type="button" class="mcv-step mcv-zero" data-act="bright-zero" aria-label="<?php echo __h('mcv2_bright_zero'); ?>">0</button><button type="button" class="mcv-step" data-act="bright-up" aria-label="<?php echo __h('mcv2_bright_up'); ?>">+</button></span></div>
  <?php if ($_mcv_book): ?>
  <button type="button" class="mcv-mi" role="menuitem" data-act="fullscreen" id="mcvFull"><?php echo $_mcv_ico($_mcv_i['full']); ?><?php echo __h('mcv2_fullscreen'); ?></button>
  <?php else: ?>
  <div class="mcv-hr"></div>
  <?php echo $_mcv_view_items; ?>
  <?php if (!empty($_viewer_is_admin)): // ★ (2026-11-04 07:44) 표지 지정은 관리자만 ?>
  <div class="mcv-hr"></div>
  <button type="button" class="mcv-mi" role="menuitem" data-act="cover"><?php echo $_mcv_ico($_mcv_i['image']); ?><?php echo __h('mcv2_cover'); ?></button>
  <?php endif; ?>
  <?php endif; ?>
  <div class="mcv-hr"></div>
  <a class="mcv-mi" role="menuitem" href="<?php echo $_mcv_prev !== '' ? h($_mcv_prev) : '#'; ?>" data-act="vol"<?php echo $_mcv_prev === '' ? ' aria-disabled="true" tabindex="-1"' : ''; ?>><?php echo $_mcv_ico($_mcv_i['prev']); ?><?php echo __h('mcv2_prev_vol'); ?></a>
  <a class="mcv-mi" role="menuitem" href="<?php echo $_mcv_next !== '' ? h($_mcv_next) : '#'; ?>" data-act="vol"<?php echo $_mcv_next === '' ? ' aria-disabled="true" tabindex="-1"' : ''; ?>><?php echo $_mcv_ico($_mcv_i['next']); ?><?php echo __h('mcv2_next_vol'); ?></a>
  <div class="mcv-hr"></div>
  <div class="mcv-hd"><?php echo __h('mcv2_reopen'); ?></div>
  <button type="button" class="mcv-mi" role="menuitemcheckbox" id="mcvResume" aria-checked="<?php echo $_mcv_resume_on ? 'true' : 'false'; ?>" title="<?php echo __h('mcv2_resume_tip'); ?>"><?php echo $_mcv_ico($_mcv_i['resume']); ?><?php echo __h('mcv2_resume'); ?><span class="mcv-r"><span class="mcv-sw"></span></span></button>
  <div class="mcv-mi" role="radiogroup" id="mcvModeMem" aria-label="<?php echo __h('mcv2_modemem'); ?>" style="cursor:default"><?php echo $_mcv_ico($_mcv_i['mode']); ?><?php echo __h('mcv2_modemem'); ?><span class="mcv-r"><?php foreach (['off', 'all', 'book'] as $_mmv): ?><button type="button" class="mcv-step mcv-zero mcv-seg<?php echo $_mcv_mm === $_mmv ? ' mcv-on' : ''; ?>" role="radio" aria-checked="<?php echo $_mcv_mm === $_mmv ? 'true' : 'false'; ?>" data-mm="<?php echo $_mmv; ?>" title="<?php echo __h('mcv2_mm_tip_' . $_mmv); ?>"><?php echo __h('mcv2_mm_' . $_mmv); ?></button><?php endforeach; ?></span></div>
</div>
<?php if (!$_mcv_book && $_mcv_next !== ''): ?>
<div class="mcv-end" id="mcvEnd" aria-live="polite"><span class="mcv-end-t"><?php echo h(sprintf(__('mcv2_next_label'), $_mcv_next_title)); ?></span><a href="<?php echo h($_mcv_next); ?>"><?php echo __h('mcv2_next_go'); ?><?php echo $_mcv_ico($_mcv_i['next'], 16); ?></a></div>
<?php endif; ?>
<?php // ★ (2026-11-02 16:55) 전체 제목 창(제목을 누르면) — 끌어서 일부만 고를 수도 있고, [제목 복사]는 전체 ?>
<div class="mcv-pop mcv-ttl-pop<?php echo $_mcv_book ? ' mcv-dark' : ''; ?>" id="mcvTitlePop" role="dialog" aria-label="<?php echo __h('mcv2_copy_hint'); ?>" hidden>
  <div class="mcv-ttl" id="mcvTitleFull"><?php echo h($_mcv_title); ?></div>
  <button type="button" class="mcv-mi" id="mcvCopyTitle"><?php echo $_mcv_ico($_mcv_i['copy']); ?><?php echo __h('mcv2_copy_title'); ?></button>
</div>
<div class="mcv-toast" id="mcvToast" role="status" aria-live="polite"></div>
<script>window.MCV_BAR = <?php echo json_encode($_mcv_cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null'; /* ★ (2026-11-02 08:59) 깨진 글자 파일명이어도 막대 스크립트가 멈추지 않게 */ ?>;</script>
<script src="js/viewer-bar.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/viewer-bar.js'); ?>" defer></script>
