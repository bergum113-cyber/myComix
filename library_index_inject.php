<?php
/**
 * index.php 확장 — 작품 정보(라이브러리) 연결
 * ★ (2026-10-29) 신규. index.php 끝에서 include (기존 카드 PHP는 수정하지 않고 스크립트로만 덧붙임)
 *
 * - 압축/PDF/EPUB/TXT 파일 카드에 ⓘ(작품 정보) 버튼 → series.php
 * - 상단 책장 아이콘 왼쪽에 🔎 작품 탐색 아이콘 → browse.php
 * - 관리자: 🏷️ 정보 일괄 편집 모드(카드 선택 → 시리즈/작가/장르/태그 넣기, library_api.php bulk_assign)
 * - 관리자 설정 '카드 클릭 동작 = 상세 화면'이면 카드 클릭 시 상세 화면, ⓘ 자리는 ▶(바로 보기)
 * - PDO SQLite 가 없으면 아무것도 출력하지 않음(기존 화면 그대로)
 */
if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) return;
if (!function_exists('__')) return; // ★ (2026-11-02 11:42) 주소로 직접 열면(index.php 밖) 500 이던 것 — library_edit_form.php 와 같게

// ★ (2026-10-30) 자동 스캔 확인 — 로그인했고 라이브러리 DB 가 있을 때만(할 일이 있을 때만 스크립트 1줄 출력)
if (!empty($_SESSION['user_id']) && is_file(__DIR__ . '/src/library.db')) {
    require_once __DIR__ . '/library.php';
    if (function_exists('mc_lib_auto_tick_script')) mc_lib_auto_tick_script((int)($current_bidx ?? 0));
}

$_lib_click = function_exists('get_app_settings') ? (string)get_app_settings('library.card_click', 'viewer') : 'viewer';
if (!in_array($_lib_click, ['viewer', 'detail'], true)) $_lib_click = 'viewer';
$_lib_admin = isset($_SESSION['user_group']) && trim($_SESSION['user_group']) === 'admin';
$_lib_cfg = [
    'bidx'  => (int)($current_bidx ?? 0),
    'click' => $_lib_click,
    'admin' => $_lib_admin,
    'csrf'  => $_lib_admin ? generate_csrf_token() : '',
    't'     => [
        'info' => __('lib_info_btn'), 'view' => __('lib_open_viewer'), 'browse' => __('lib_browse'),
        'bulk' => __('lib_bulk_mode'), 'title' => __('lib_bulk_title'), 'help' => __('lib_bulk_help'),
        'sel' => __('lib_bulk_selected'), 'apply' => __('lib_bulk_apply'), 'cancel' => __('common_cancel'),
        'done' => __('lib_bulk_done'), 'failed' => __('lib_save_failed'), 'none' => __('lib_bulk_none'),
        'series' => __('lib_series_name'), 'author' => __('lib_author'), 'group' => __('lib_group'), 'genre' => __('lib_genre'), 'tag' => __('lib_tag'),
        'saving' => __('lib_saving'),
    ],
];
?>
<style>
  .mc-lib-info { position:absolute; top:6px; right:26px; z-index:10; width:20px; height:20px; padding:0; border:none; border-radius:50%;
                 background:rgba(0,0,0,.45); color:#fff; font-size:12px; line-height:20px; text-align:center; cursor:pointer; opacity:.85; }
  .mc-lib-info:hover { opacity:1; background:rgba(0,0,0,.7); }
  html.mc-list-view .grid .card > .mc-lib-info { top:8px; }
  html.mc-list-view .grid .card > .card-img-overlay { margin-right:50px !important; } /* ⭐ + ⓘ 자리 */
  .mc-lib-chk { position:absolute; top:5px; left:5px; z-index:101; width:22px; height:22px; cursor:pointer; }
  .mc-lib-pick { outline:3px solid #f39c12; outline-offset:-3px; }
  #mcLibBar { position:fixed; left:0; right:0; bottom:0; z-index:1060; background:#343a40; color:#fff; padding:10px 16px; display:none; align-items:center; gap:10px; flex-wrap:wrap; box-shadow:0 -2px 10px rgba(0,0,0,.3); }
  #mcLibModal { position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10060; display:none; align-items:flex-start; justify-content:center; padding:40px 10px; overflow:auto; }
  #mcLibModal .box { background:#fff; color:#222; border-radius:8px; width:100%; max-width:520px; padding:16px 18px; }
  #mcLibModal label { font-size:.85rem; color:#666; margin:8px 0 2px; display:block; }
  .dark-mode #mcLibModal .box, [data-theme="dark"] #mcLibModal .box { background:#1e1e1e; color:#e0e0e0; }
  .mc-lib-browse-link { color:#2a7ae2; }
</style>
<script>
(function () {
  var CFG = <?php echo json_encode($_lib_cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  // 카드 링크(href)에서 파일 경로를 얻음 — data-file-path 는 관리자에게만 출력되므로 사용하지 않음
  // 대상: 압축(viewer.php?mode=toon), PDF(viewer.php?filetype=pdf), EPUB/TXT(epub_/txt_viewer.php) — 동영상·오피스·폴더 제외
  var RX = /^(?:\.\/)?(?:viewer\.php\?(?:mode=toon|filetype=pdf)&|epub_viewer\.php\?|txt_viewer\.php\?)file=([^&#]*)/;
  function info(a) {
    var href = a.getAttribute('data-viewer-href') || a.getAttribute('href') || ''; // 상세 우선 모드에서 바꾼 링크는 원래 뷰어 주소로 판별
    var m = RX.exec(href);
    if (!m || !m[1]) return null;
    var bm = /[?&]bidx=(\d+)/.exec(href);
    var plain = '';
    try { plain = decodeURIComponent(m[1].replace(/\+/g, ' ')); } catch (e) { return null; }
    return { raw: m[1], plain: plain, bidx: bm ? parseInt(bm[1], 10) : CFG.bidx, href: href };
  }
  function libCards() {
    var out = [];
    var as = document.querySelectorAll('a[href]');
    for (var i = 0; i < as.length; i++) {
      var card = as[i].querySelector('.card');
      if (!card) continue;
      var d = info(as[i]);
      if (d) { d.a = as[i]; d.card = card; out.push(d); }
    }
    return out;
  }
  function seriesUrl(d) { return 'series.php?file=' + d.raw + '&bidx=' + d.bidx; } // raw = encode_url() 결과 그대로

  // 1) 카드에 ⓘ / ▶ 버튼, 상세 우선 모드면 카드 링크를 상세 화면으로
  libCards().forEach(function (d) {
    var card = d.card, a = d.a;
    if (card.querySelector('.mc-lib-info')) return;
    if (getComputedStyle(card).position === 'static') card.style.position = 'relative';
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'mc-lib-info';
    if (CFG.click === 'detail') {
      a.setAttribute('data-viewer-href', d.href);
      a.setAttribute('href', seriesUrl(d));
      b.textContent = '▶'; b.title = CFG.t.view; b.setAttribute('aria-label', CFG.t.view);
      b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); location.href = d.href; });
    } else {
      b.textContent = 'i'; b.title = CFG.t.info; b.setAttribute('aria-label', CFG.t.info);
      b.style.fontStyle = 'italic'; b.style.fontWeight = 'bold'; b.style.fontFamily = 'Georgia, serif';
      b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); location.href = seriesUrl(d); });
    }
    card.appendChild(b);
  });

  // 2) 상단: 작품 탐색 아이콘(책장 아이콘 왼쪽)
  var shelf = document.querySelector('.mc-fav-shelf-link');
  if (shelf && !document.querySelector('.mc-lib-browse-link')) {
    var l = document.createElement('a');
    l.className = 'btn btn-sm m-0 p-0 mr-2 align-middle mc-lib-browse-link';
    l.href = 'browse.php?bidx=' + CFG.bidx; l.title = CFG.t.browse; l.setAttribute('aria-label', CFG.t.browse);
    l.style.lineHeight = '0';
    // ★ (2026-10-31) '서재' — 꽂힌 책 아이콘(예전 문서+돋보기는 파일 검색처럼 보였음), 색은 옆 북마크와 같은 회색(index.php CSS)
    l.innerHTML = '<svg class="mc-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect width="8" height="18" x="3" y="3" rx="1"/><path d="M7 3v18"/><path d="M20.4 18.9c.2.5-.1 1.1-.6 1.3l-1.9.7c-.5.2-1.1-.1-1.3-.6L11.1 5.1c-.2-.5.1-1.1.6-1.3l1.9-.7c.5-.2 1.1.1 1.3.6Z"/></svg>';
    shelf.parentNode.insertBefore(l, shelf);
  }

  if (!CFG.admin) return;

  // 3) 관리자: 정보 일괄 편집 모드
  var delBtn = document.getElementById('deleteModeBtn');
  if (!delBtn || !libCards().length) return;
  var mode = false;
  var btn = document.createElement('button');
  btn.type = 'button'; btn.id = 'mcLibBulkBtn';
  btn.className = 'btn btn-sm btn-outline-warning ml-1 p-1';
  btn.title = CFG.t.bulk; btn.setAttribute('aria-label', CFG.t.bulk);
  if (window.MC_ICO_TAG) btn.innerHTML = window.MC_ICO_TAG; else btn.textContent = '🏷️'; // ★ (2026-10-30) 선 아이콘(index.php 가 넣어 줌)
  delBtn.parentNode.insertBefore(btn, delBtn.nextSibling);

  var bar = document.createElement('div');
  bar.id = 'mcLibBar';
  bar.innerHTML = '<span id="mcLibCnt"></span><span style="flex:1"></span>' +
    '<button type="button" class="btn btn-sm btn-warning" id="mcLibApply"></button>' +
    '<button type="button" class="btn btn-sm btn-outline-light" id="mcLibCancel"></button>';
  document.body.appendChild(bar);
  document.getElementById('mcLibApply').textContent = CFG.t.apply;
  document.getElementById('mcLibCancel').textContent = CFG.t.cancel;

  var modal = document.createElement('div');
  modal.id = 'mcLibModal';
  modal.innerHTML = '<div class="box"><h5 id="mcLibTitle" style="margin:0 0 6px;"></h5><div id="mcLibHelp" style="font-size:.8rem;color:#999;"></div>' +
    '<label id="mcLibL1"></label><input class="form-control form-control-sm" id="mcLibSeries" maxlength="200">' +
    '<label id="mcLibL2"></label><input class="form-control form-control-sm" id="mcLibAuthors">' +
    '<label id="mcLibL2g"></label><input class="form-control form-control-sm" id="mcLibGroups">' + /* ★ (2026-11-02 01:53) 그룹 */
    '<label id="mcLibL3"></label><input class="form-control form-control-sm" id="mcLibGenres">' +
    '<label id="mcLibL4"></label><input class="form-control form-control-sm" id="mcLibTags">' +
    '<div style="display:flex;gap:6px;justify-content:flex-end;margin-top:14px;align-items:center;"><span id="mcLibMsg" style="margin-right:auto;font-size:.85rem;"></span>' +
    '<button type="button" class="btn btn-sm btn-secondary" id="mcLibMCancel"></button><button type="button" class="btn btn-sm btn-primary" id="mcLibMOk"></button></div></div>';
  document.body.appendChild(modal);
  document.getElementById('mcLibHelp').textContent = CFG.t.help;
  document.getElementById('mcLibL1').textContent = CFG.t.series;
  document.getElementById('mcLibL2').textContent = CFG.t.author;
  document.getElementById('mcLibL2g').textContent = CFG.t.group;
  document.getElementById('mcLibL3').textContent = CFG.t.genre;
  document.getElementById('mcLibL4').textContent = CFG.t.tag;
  document.getElementById('mcLibMCancel').textContent = CFG.t.cancel;
  document.getElementById('mcLibMOk').textContent = CFG.t.apply;

  function picked() { return Array.prototype.slice.call(document.querySelectorAll('.mc-lib-chk:checked')); }
  function updCount() { document.getElementById('mcLibCnt').textContent = CFG.t.sel.replace('%d', picked().length); }
  function setMode(on) {
    mode = on;
    btn.classList.toggle('btn-warning', on); btn.classList.toggle('btn-outline-warning', !on);
    bar.style.display = on ? 'flex' : 'none';
    libCards().forEach(function (d) {
      var card = d.card;
      if (d.bidx !== CFG.bidx) return; // 다른 저장소(검색 결과) 카드는 제외
      var c = card.querySelector('.mc-lib-chk');
      if (on && !c) {
        c = document.createElement('input'); c.type = 'checkbox'; c.className = 'mc-lib-chk';
        c.setAttribute('data-plain', d.plain);
        c.addEventListener('click', function (e) { e.stopPropagation(); card.classList.toggle('mc-lib-pick', c.checked); updCount(); });
        card.appendChild(c);
      } else if (!on && c) { c.remove(); card.classList.remove('mc-lib-pick'); }
    });
    updCount();
  }
  btn.addEventListener('click', function () {
    if (!mode && typeof deleteMode !== 'undefined' && deleteMode && typeof toggleDeleteMode === 'function') toggleDeleteMode(); // 삭제 모드와 동시 사용 방지
    setMode(!mode); try { btn.blur(); } catch (e) {}
  });
  delBtn.addEventListener('click', function () { if (mode) setMode(false); });
  document.getElementById('mcLibCancel').addEventListener('click', function () { setMode(false); });
  // 일괄 편집 중에는 카드 클릭 = 선택(뷰어로 이동하지 않음)
  document.addEventListener('click', function (e) {
    if (!mode) return;
    var card = e.target.closest && e.target.closest('.card');
    if (!card || e.target.classList.contains('mc-lib-chk')) return;
    var c = card.querySelector('.mc-lib-chk'); if (!c) return;
    e.preventDefault(); e.stopPropagation();
    c.checked = !c.checked; card.classList.toggle('mc-lib-pick', c.checked); updCount();
  }, true);

  var msg = document.getElementById('mcLibMsg');
  document.getElementById('mcLibApply').addEventListener('click', function () {
    var n = picked().length;
    if (!n) { alert(CFG.t.none); return; }
    document.getElementById('mcLibTitle').textContent = CFG.t.title.replace('%d', n);
    msg.textContent = '';
    modal.style.display = 'flex';
    document.getElementById('mcLibSeries').focus();
  });
  document.getElementById('mcLibMCancel').addEventListener('click', function () { modal.style.display = 'none'; });
  modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
  document.getElementById('mcLibMOk').addEventListener('click', function () {
    var ok = this; ok.disabled = true; msg.textContent = CFG.t.saving;
    var f = new FormData();
    f.append('action', 'bulk_assign'); f.append('csrf_token', CFG.csrf); f.append('bidx', CFG.bidx);
    f.append('series_name', document.getElementById('mcLibSeries').value);
    f.append('authors', document.getElementById('mcLibAuthors').value);
    f.append('groups', document.getElementById('mcLibGroups').value);
    f.append('genres', document.getElementById('mcLibGenres').value);
    f.append('tags', document.getElementById('mcLibTags').value);
    picked().forEach(function (c) { f.append('files[]', c.getAttribute('data-plain')); }); // encode_url 형식(플레이스홀더) — 서버에서 decode_file_param
    fetch('library_api.php', { method: 'POST', body: f, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        ok.disabled = false;
        if (!r || !r.success) { msg.textContent = (r && r.error) || CFG.t.failed; return; }
        msg.textContent = CFG.t.done.replace('%1$d', r.volumes).replace('%2$d', r.series);
        setTimeout(function () { modal.style.display = 'none'; setMode(false); ['mcLibSeries', 'mcLibAuthors', 'mcLibGroups', 'mcLibGenres', 'mcLibTags'].forEach(function (id) { document.getElementById(id).value = ''; }); }, 900);
      })
      .catch(function () { ok.disabled = false; msg.textContent = CFG.t.failed; });
  });
})();
</script>
