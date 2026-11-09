<?php
/**
 * ★ (2026-11-06 fix27) 관리자 화면 공통 틀 — 위 막대 + 메뉴(PC 왼쪽 고정 / 모바일 밀어 나오는 메뉴)
 *   admin.php 와 admin_translations.php 가 같이 씀(모양·메뉴가 같게)
 *   - 메뉴 링크 이름·순서·data-toggle·onclick 은 예전 admin.php 것 그대로('tabs')
 *   - 다른 화면('links')에서는 같은 메뉴를 admin.php#탭 링크로(admin.php 가 # 을 보고 그 탭을 엶)
 *   - 바깥 칸 이름 nav-tabs-wrapper · admin-menu 는 탭 복원·저장 막대 스크립트가 찾으므로 그대로
 */
if (!defined('MC_ADMIN_SHELL')) {
    define('MC_ADMIN_SHELL', 1);

    /** ★ (2026-11-06 fix28) 관리자 화면 색 6가지 — [키, 이름 키, 견본 색 2개] · 기본 sky */
    function mc_admin_themes() {
        return [
            'sky'      => ['adm_ui_th_sky',      '#3858E9', '#EEF2FF'],
            'mint'     => ['adm_ui_th_mint',     '#10B981', '#ECFDF5'],
            'lavender' => ['adm_ui_th_lavender', '#7C5CFC', '#F3F0FF'],
            'ocean'    => ['adm_ui_th_ocean',    '#0F766E', '#5EC9C3'],
            'coral'    => ['adm_ui_th_coral',    '#F2674E', '#FFF1EE'],
            'dark'     => ['adm_ui_th_dark',     '#111827', '#3B82F6'],
        ];
    }
    /** 고른 색(이 브라우저 쿠키 mc_adm_theme) — 목록에 있는 이름만, 아니면 기본 */
    function mc_admin_theme() {
        $t = isset($_COOKIE['mc_adm_theme']) ? (string)$_COOKIE['mc_adm_theme'] : '';
        return array_key_exists($t, mc_admin_themes()) ? $t : 'sky';
    }

    /** 공통 CSS 링크(머리 <head> 안에서) + 고른 색을 그리기 전에 붙임(깜빡임 없게) */
    function mc_admin_shell_css() {
        $f = __DIR__ . '/css/admin-shell.css';
        $v = is_file($f) ? filemtime($f) : 1;
        echo '<script>document.documentElement.setAttribute("data-adm-theme","' . mc_admin_theme() . '");</script>' . "\n";
        echo '<link rel="stylesheet" href="./css/admin-shell.css?v=' . (int)$v . '">' . "\n";
    }

    /** 위 막대: [메뉴 단추(모바일)] 로고 · 관리자 ……… 언어 · 메인 · 로그아웃 */
    function mc_admin_topbar($branding) {
        $logo = function_exists('mc_brand_logo') ? mc_brand_logo($branding, 'admin.php', 'color:#fff;min-width:0;') : '<a href="admin.php" class="mc-brand" style="color:#fff">myComix</a>';
        echo '<header class="mc-adm-top">'
           . '<button type="button" class="mc-adm-burger" id="mcAdmBurger" aria-label="' . __h('adm_menu_open') . '" aria-expanded="false" aria-controls="mcAdmNav">'
           . '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>'
           . '<span class="mc-adm-brand">' . $logo . '</span>'
           . '<span class="mc-adm-role">' . __h('admin_return_admin') . ' <small>' . (defined('MYCOMIX_VERSION') ? h(MYCOMIX_VERSION) : '') . '</small></span>'
           . '<span class="mc-adm-top-r">';
        // ★ (2026-11-06 fix28) 화면 색 고르기
        $cur = mc_admin_theme();
        echo '<span class="mc-th"><button type="button" class="mc-th-btn" id="mcThBtn" aria-haspopup="true" aria-expanded="false" aria-controls="mcThPop" title="' . __h('adm_ui_theme') . '" aria-label="' . __h('adm_ui_theme') . '">'
           . '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22a10 10 0 1 1 10-10c0 2.8-2.2 4-4 4h-2.5a2 2 0 0 0-1.5 3.3A1.6 1.6 0 0 1 12 22z"/><circle cx="7.5" cy="10.5" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16.5" cy="10.5" r="1"/></svg><span class="mc-th-cur" aria-hidden="true"></span></button>'
           . '<div class="mc-th-pop" id="mcThPop" role="menu" aria-label="' . __h('adm_ui_theme') . '" hidden><div class="mc-th-ttl">' . __h('adm_ui_theme') . '</div>';
        foreach (mc_admin_themes() as $k => $t) {
            echo '<button type="button" class="mc-th-opt" role="menuitemradio" data-th="' . $k . '" aria-checked="' . ($k === $cur ? 'true' : 'false') . '">'
               . '<span class="mc-th-sw" aria-hidden="true"><i style="background:' . $t[1] . '"></i><i style="background:' . $t[2] . '"></i></span>' . __h($t[0])
               . '<svg class="mc-th-ck" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg></button>';
        }
        echo '<div class="mc-th-note">' . __h('adm_ui_theme_note') . '</div></div></span>';
        if (function_exists('render_lang_badge')) render_lang_badge('md');
        // ★ (2026-11-07 fix37) 메인·로그아웃 — 글자 대신 아이콘(펜닐 요청) / 마우스 올리면 이름(title), 읽기 프로그램은 aria-label·숨은 글자로 읽음
        $ico = function ($d) { return '<svg class="mc-adm-tico" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>'; };
        echo '<a href="index.php?bidx=0" class="mc-adm-toplink mc-adm-icolink" title="' . __h('admin_return_index') . '" aria-label="' . __h('admin_return_index') . '">'
           . $ico('<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>')
           . '<span class="sr-only">' . __h('admin_return_index') . '</span></a>'
           . '<a href="login.php?mode=logout" class="mc-adm-toplink mc-adm-icolink" title="' . __h('admin_logout') . '" aria-label="' . __h('admin_logout') . '">'
           . $ico('<path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>')
           . '<span class="sr-only">' . __h('admin_logout') . '</span></a>'
           . '</span></header>'
           . '<div class="mc-adm-backdrop" id="mcAdmBackdrop" hidden></div>';
    }

    /**
     * 메뉴. $mode = 'tabs'(admin.php 안 탭) | 'links'(다른 화면 — admin.php#탭 으로 감)
     * $current = 'links' 일 때 선택 표시할 항목(예: 'translations')
     */
    function mc_admin_menu($mode = 'tabs', $current = '') {
        $tabs = ($mode === 'tabs');
        $groups = [
            ['am-overview', 'admin_menu_grp_overview', [['dashboard', 'admin_tab_dashboard'], ['logs', 'admin_tab_logs'], ['system', 'admin_tab_system']]],
            ['am-site', 'admin_menu_grp_site', [['config', 'admin_tab_config'], ['theme', 'admin_tab_theme'], ['branding', 'admin_tab_branding'], ['language', 'admin_tab_language']]],
            ['am-content', 'admin_menu_grp_content', [['folder', 'admin_tab_folder'], ['cache', 'admin_tab_cache'], ['duplicates', 'admin_tab_duplicates'], ['notice', 'admin_tab_notice'], ['@translations', 'admin_tab_translations']]],
            ['am-member', 'admin_menu_grp_member', [['group', 'admin_tab_users'], ['deleted_users', 'admin_tab_deleted_users'], ['terms', 'admin_tab_terms']]],
            ['am-security', 'admin_menu_grp_security', [['security', 'admin_tab_security'], ['smtp', 'admin_tab_smtp']]],
        ];
        echo '<nav class="nav-tabs-wrapper admin-menu" id="mcAdmNav" aria-label="' . __h('admin_return_admin') . '">'
           . '<div class="mc-nav-head"><span>' . __h('adm_menu_open') . '</span><button type="button" class="mc-nav-close" id="mcAdmNavClose" aria-label="' . __h('adm_menu_close') . '">&times;</button></div>';
        foreach ($groups as $g) {
            echo '<div class="am-group ' . $g[0] . '"><div class="am-title">' . __h($g[1]) . '</div>' . "\n" . '<ul class="nav am-list" role="tablist">' . "\n";
            foreach ($g[2] as $it) {
                $label = __($it[1]); // 예전처럼 __() 그대로(이모지 → 선 아이콘은 mc-icons.js)
                if ($it[0] === '@translations') { // ★ (2026-11-06 fix34) admin.php 안에서는 탭(#translations — 본문에 그 화면을 불러 옴, 새로고침 없이 오감) / 다른 화면에서는 예전처럼 링크
                    if ($tabs) { echo '<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#translations">' . $label . '</a></li>' . "\n"; continue; }
                    $on = (!$tabs && $current === 'translations');
                    echo '<li class="nav-item"><a class="nav-link mc-nav-page' . ($on ? ' active' : '') . '" href="admin_translations.php"' . ($on ? ' aria-current="page"' : '') . '>' . $label . '</a></li>' . "\n";
                    continue;
                }
                if ($tabs) {
                    $extra = ($it[0] === 'logs') ? ' onclick="resetLogPage()"' : '';
                    $act = ($it[0] === 'dashboard') ? ' active' : '';
                    echo '<li class="nav-item"><a class="nav-link' . $act . '" data-toggle="tab" href="#' . $it[0] . '"' . $extra . '>' . $label . '</a></li>' . "\n";
                } else {
                    echo '<li class="nav-item"><a class="nav-link" href="admin.php#' . $it[0] . '">' . $label . '</a></li>' . "\n";
                }
            }
            echo '</ul></div>' . "\n";
        }
        echo '</nav>';
    }

    /** 모바일 메뉴 열고 닫기 + (탭 화면이면) 위 제목 = 고른 메뉴 이름 */
    function mc_admin_shell_js() {
        ?>
<script>
(function () {
  var b = document.body, btn = document.getElementById('mcAdmBurger'), nav = document.getElementById('mcAdmNav'), bd = document.getElementById('mcAdmBackdrop'), cl = document.getElementById('mcAdmNavClose');
  if (!btn || !nav) return;
  function setOpen(on) {
    b.classList.toggle('mc-nav-open', on); btn.setAttribute('aria-expanded', on ? 'true' : 'false');
    if (bd) bd.hidden = !on;
    if (on) { var a = nav.querySelector('.nav-link.active') || nav.querySelector('.nav-link'); if (a) try { a.focus({ preventScroll: true }); } catch (e) {} }
  }
  btn.addEventListener('click', function () { setOpen(!b.classList.contains('mc-nav-open')); });
  if (bd) bd.addEventListener('click', function () { setOpen(false); });
  if (cl) cl.addEventListener('click', function () { setOpen(false); btn.focus(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && b.classList.contains('mc-nav-open')) { setOpen(false); btn.focus(); } });
  nav.addEventListener('click', function (e) {
    var l = e.target.closest ? e.target.closest('.nav-link') : null; if (!l) return;
    if (b.classList.contains('mc-nav-open')) { b.classList.add('mc-nav-noanim'); setTimeout(function () { b.classList.remove('mc-nav-noanim'); }, 60); } // ★ fix29 고르면 미끄러지지 않고 바로 닫힘(화면이 바뀌며 깜빡이던 것)
    setOpen(false); // 메뉴를 고르면 닫힘(PC 에선 아무 일 없음)
    if (l.getAttribute('data-toggle') === 'tab' && window.scrollY > 0) { try { window.scrollTo(0, 0); } catch (x) {} } // 아래로 내려 둔 채 다른 메뉴를 고르면 새 화면 맨 위부터(손으로 고를 때만 — 저장 뒤 위치 되살리기는 그대로)
  });
  window.addEventListener('resize', function () { if (window.innerWidth >= 992 && b.classList.contains('mc-nav-open')) setOpen(false); });
  // ★ (2026-11-06 fix28) 화면 색 고르기 — 바로 바뀌고 이 브라우저에 1년 기억(쿠키 — 서버는 목록에 있는 이름만 받음)
  var tb = document.getElementById('mcThBtn'), tp = document.getElementById('mcThPop');
  if (tb && tp) {
    var opts = [].slice.call(tp.querySelectorAll('.mc-th-opt'));
    function pop(on) { tp.hidden = !on; tb.setAttribute('aria-expanded', on ? 'true' : 'false'); if (on) { var c = tp.querySelector('[aria-checked="true"]') || opts[0]; if (c) c.focus(); } }
    tb.addEventListener('click', function (e) { e.stopPropagation(); pop(tp.hidden); });
    opts.forEach(function (o, i) {
      o.addEventListener('click', function () {
        var k = o.getAttribute('data-th');
        document.documentElement.setAttribute('data-adm-theme', k);
        [].forEach.call(document.querySelectorAll('iframe.mc-embed-frame'), function (f) { try { f.contentDocument.documentElement.setAttribute('data-adm-theme', k); } catch (x) {} }); // 불러 온 한영 화면도 같은 색
        opts.forEach(function (x) { x.setAttribute('aria-checked', x === o ? 'true' : 'false'); });
        try { document.cookie = 'mc_adm_theme=' + k + ';path=/;max-age=31536000;SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : ''); } catch (x) {}
        pop(false); tb.focus();
      });
      o.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); var n = opts[(i + (e.key === 'ArrowDown' ? 1 : opts.length - 1)) % opts.length]; n.focus(); }
      });
    });
    document.addEventListener('click', function (e) { if (!tp.hidden && !tp.contains(e.target) && e.target !== tb) pop(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !tp.hidden) { pop(false); tb.focus(); } });
  }
  // ★ (2026-11-06 fix30) 폰 세로에서 옆으로 넘치는 표(머리 칸 4개 이상) → 줄마다 카드(각 값 앞에 머리 이름) — 옆으로 밀어야 보이던 IP·활동·상세·크기 칸
  //   머리 이름은 표의 thead 마지막 줄에서 읽음(colspan 셈) · 대시보드 시간표·설정 큰 표는 제외 · 화면 모양만(칸·값 그대로)
  function stackTables(root) {
    (root || document).querySelectorAll('.mc-adm-main table, .at-wrap table').forEach(function (tb) {
      if (tb.classList.contains('config-table') || tb.closest('#dashboard') || tb.hasAttribute('data-no-stack')) return;
      var hr = tb.tHead && tb.tHead.rows.length ? tb.tHead.rows[tb.tHead.rows.length - 1] : null;
      if (!hr || hr.cells.length < 4) return;
      var names = [];
      [].forEach.call(hr.cells, function (c) { var n = (c.textContent || '').replace(/\s+/g, ' ').trim(); for (var k = 0; k < (c.colSpan || 1); k++) names.push(n); });
      tb.classList.add('mc-stk');
      [].forEach.call(tb.tBodies, function (bd) {
        [].forEach.call(bd.rows, function (tr) {
          var ix = 0;
          [].forEach.call(tr.cells, function (td) { if (!td.hasAttribute('data-label') || td.getAttribute('data-mc-l') === '1') { td.setAttribute('data-label', (td.colSpan > 1) ? '' : (names[ix] || '')); td.setAttribute('data-mc-l', '1'); } ix += td.colSpan || 1; });
        });
      });
    });
  }
  function stackInit() { // 이 글은 본문보다 먼저 나오므로 — 본문이 다 읽힌 뒤에
    stackTables();
    var stkT = 0, mainEl = document.querySelector('.mc-adm-main, .at-wrap');
    if (mainEl && window.MutationObserver) new MutationObserver(function (ms) { // 로그 쪽 넘김 등으로 줄이 새로 들어올 때
      for (var i = 0; i < ms.length; i++) { var t0 = ms[i].target; if (t0.closest && t0.closest('table')) { clearTimeout(stkT); stkT = setTimeout(stackTables, 80); return; } }
    }).observe(mainEl, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', stackInit); else stackInit();
  // ★ (2026-11-06 fix34) 한영 검색어 연결 탭 — 처음 열 때 본문에 그 화면을 불러 옴(따로 페이지로 가지 않아 메뉴·본문이 새로고침되지 않음)
  //   높이는 안쪽 화면이 알려 줌(같은 사이트에서 온 알림만) · 저장/삭제 알림은 바깥 화면 위에 띄움(안쪽 위쪽은 스크롤에 따라 안 보일 수 있어서)
  function embLoad() { var f = document.querySelector('#translations iframe.mc-embed-frame'); if (f && !f.getAttribute('src')) f.setAttribute('src', f.getAttribute('data-src')); }
  if (window.jQuery) jQuery(document).on('shown.bs.tab', 'a[data-toggle="tab"][href="#translations"]', embLoad);
  document.addEventListener('DOMContentLoaded', function () { var p0 = document.getElementById('translations'); if (p0 && p0.classList.contains('active')) embLoad(); });
  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || typeof e.data !== 'object') return;
    var f = document.querySelector('iframe.mc-embed-frame'); if (!f || e.source !== f.contentWindow) return;
    if (typeof e.data.mcEmbedH === 'number' && e.data.mcEmbedH > 0) f.style.height = Math.ceil(Math.min(e.data.mcEmbedH, 200000)) + 'px';
    if (e.data.mcToast && typeof e.data.mcToast.m === 'string') {
      var d = document.createElement('div'); d.className = 'mc-toast' + (e.data.mcToast.e ? ' mc-toast-err' : ''); d.setAttribute('role', 'status'); d.textContent = e.data.mcToast.m.slice(0, 300);
      document.body.appendChild(d); setTimeout(function () { d.classList.add('mc-toast-out'); setTimeout(function () { d.remove(); }, 300); }, 2200);
    }
  });
  // ★ (2026-11-06 fix35) 한영 탭 안의 '선택 막대'(여러 개 고르면 나오는 막대)가 스크롤해도 보이게 — 칸 안은 스스로 스크롤하지 않아 sticky 가 듣지 않으므로, 보이는 맨 위 위치를 알려 줌
  var vtR = 0;
  function sendVTop() {
    vtR = 0; var f = document.querySelector('iframe.mc-embed-frame'); if (!f || !f.contentWindow || !f.getAttribute('src')) return;
    var top = document.querySelector('.mc-adm-top'), th = top ? top.getBoundingClientRect().bottom : 0;
    try { f.contentWindow.postMessage({ mcVTop: Math.max(0, Math.round(th - f.getBoundingClientRect().top)) }, location.origin); } catch (x) {}
  }
  function qVTop() { if (!vtR) vtR = (window.requestAnimationFrame || setTimeout)(sendVTop); }
  window.addEventListener('scroll', qVTop, { passive: true }); window.addEventListener('resize', qVTop);
  document.addEventListener('DOMContentLoaded', function () { var f = document.querySelector('iframe.mc-embed-frame'); if (f) f.addEventListener('load', sendVTop); });
  window.addEventListener('message', function (e) { if (e.origin === location.origin && e.data && e.data.mcEmbedH) qVTop(); });
  var t = document.getElementById('mcAdmTitle');
  function title() { if (!t) return; var a = nav.querySelector('a[data-toggle="tab"].active'); if (a) t.innerHTML = a.innerHTML; } // 아이콘(svg)까지 같은 모양으로
  title();
  if (window.jQuery) jQuery(document).on('shown.bs.tab', '.admin-menu a[data-toggle="tab"]', title);
  document.addEventListener('DOMContentLoaded', title);
  window.addEventListener('load', title);
})();
</script>
<?php
    }
}
