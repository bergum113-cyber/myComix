<?php
/**
 * myComix - Tab switch privacy (MOBILE ONLY)
 *
 * @version 1.9 (base) — myComix v2.4.2에서 동작 개선 적용
 * @date 2026-01-13 (기반) / 2026-06-14 (개선)
 *
 * v2.4.2 개선 요약:
 * - visibilitychange→hidden 시 즉시 가림막(이전 100ms 지연 제거)
 * - goBlank 가드를 '최근 500ms 내 탭'(timing) 기준으로 일원화
 *   (내비게이션 없는 탭 뒤 한참 지난 백그라운드에서도 가림막이 뜨도록 — 무기한 isInternalNavigation 차단 제거)
 * - pagehide(forced) 경로 유지로 앱 백그라운드 확실 대응
 *
 * 핵심 설계:
 * - 모바일에서만 동작
 * - "내부 이동"과 "탭 이탈"을 명확히 분리
 * - 스크롤 → 탭 전환 오탐 완전 차단 (유클리드 거리)
 * - 500ms 타이밍 안전장치
 * - DEBUG 시 이벤트 타임라인 확인 가능
 */

// 설정 로드 (함수가 없으면 기본값 사용)
if (function_exists('get_app_settings')) {
    $_privacy_settings = get_app_settings('privacy_shield', [
        'enabled' => true,
        'pages'   => ['index.php', 'viewer.php'],
        'debug'   => false
    ]);
} else {
    $_privacy_settings = ['enabled' => true, 'pages' => ['index.php', 'viewer.php'], 'debug' => false];
}

if (!($_privacy_settings['enabled'] ?? true)) return;

$_current_script = basename($_SERVER['SCRIPT_FILENAME']);
$_privacy_pages  = $_privacy_settings['pages'] ?? [];

if (!in_array($_current_script, $_privacy_pages)) return;

// blank.php 경로
global $base_path;
$_blank_url = ($base_path ?? '') . '/blank.php';

$_debug = $_privacy_settings['debug'] ?? false;
?>

<?php if ($_debug): ?>
<style>
#privacy-debug {
  position: fixed;
  bottom: 10px;
  left: 10px;
  max-width: 320px;
  background: rgba(0,0,0,0.85);
  color: #0f0;
  font: 11px monospace;
  padding: 8px;
  border-radius: 5px;
  z-index: 999999;
  pointer-events: none;
}
#privacy-debug .log {
  max-height: 120px;
  overflow-y: auto;
  margin-top: 4px;
}
</style>
<div id="privacy-debug">
  <b>🛡 Privacy Shield v1.9</b><br>
  jumped: <span id="ps-jumped">false</span><br>
  internal: <span id="ps-internal">false</span><br>
  last: <span id="ps-last">-</span>
  <div class="log" id="ps-log"></div>
</div>
<?php endif; ?>

<script>
(function(){

  /* ===========================
   * 환경 판별 (모바일만)
   * =========================== */
  var ua = navigator.userAgent;
  var isDesktopOS = /Windows NT|Macintosh|Linux x86_64|Linux i686|CrOS/i.test(ua);
  var hasMobileUA = /Android|iPhone|iPad|iPod|Mobile|Tablet/i.test(ua);
  var isIPadDesktop = /Macintosh/i.test(ua) && navigator.maxTouchPoints > 1;

  var isMobile = hasMobileUA || isIPadDesktop;
  if (isDesktopOS && !isIPadDesktop) isMobile = false;

  if (!isMobile) return;

  /* ===========================
   * 상태 플래그
   * =========================== */
  var BLANK_URL = '<?php echo $_blank_url; ?>';
  var jumped = false;
  var isInternalNavigation = false;
  var pendingHide = false;   // hidden 전환이 시작됐는지(빠른 백그라운드에서 pagehide가 stale visible로 와도 보강)
  var lastClickTime = 0;  // ✅ 문서 3에서 추가
  var debug = <?php echo $_debug ? 'true' : 'false'; ?>;

  var touchStartX = 0;
  var touchStartY = 0;

  /* ===========================
   * DEBUG 헬퍼
   * =========================== */
  function $(id){ return document.getElementById(id); }

  function debugState() {
    if (!debug) return;
    var el1 = $('ps-jumped');
    var el2 = $('ps-internal');
    if (el1) el1.textContent = jumped;
    if (el2) el2.textContent = isInternalNavigation;
  }

  function debugLog(msg) {
    if (!debug) return;
    var el = $('ps-last');
    if (el) el.textContent = msg;
    var box = $('ps-log');
    if (box) {
      var line = document.createElement('div');
      line.textContent = '[' + new Date().toLocaleTimeString() + '] ' + msg;
      box.appendChild(line);
      box.scrollTop = box.scrollHeight;
    }
    debugState();
  }

  /* ===========================
   * 외부 호출용
   * =========================== */
  window.myComixMarkNavigation = function() {
    isInternalNavigation = true;
    lastClickTime = Date.now();
    debugLog('external markNavigation()');
  };

  /* ===========================
   * 터치 시작 (스크롤 판별)
   * =========================== */
  document.addEventListener('touchstart', function(e){
    if (e.touches && e.touches[0]) {
      touchStartX = e.touches[0].clientX;
      touchStartY = e.touches[0].clientY;
    }
  }, {passive:true, capture:true});

  function markIfInteractive(e) {
    // 터치 이동 거리 체크 (스크롤 방지) - 유클리드 거리
    if (e.type === 'touchend' && e.changedTouches && e.changedTouches[0]) {
      var dx = Math.abs(e.changedTouches[0].clientX - touchStartX);
      var dy = Math.abs(e.changedTouches[0].clientY - touchStartY);
      if (Math.sqrt(dx*dx + dy*dy) > 10) {
        debugLog('scroll detected');
        return;
      }
    }

    var el = e.target;
    while (el && el !== document.body) {
      var tag = el.tagName;
      if (
        tag === 'A' || tag === 'BUTTON' || tag === 'INPUT' ||
        tag === 'SELECT' || tag === 'LABEL' || tag === 'TEXTAREA' ||  // ✅ TEXTAREA 추가
        el.onclick || (el.getAttribute && el.getAttribute('onclick')) ||
        (el.getAttribute && el.getAttribute('data-href')) || 
        (el.getAttribute && el.getAttribute('role') === 'button')
      ) {
        isInternalNavigation = true;
        lastClickTime = Date.now();  // ✅ 타이밍 기록
        debugLog('internal: ' + tag);
        return;
      }
      el = el.parentElement;
    }
  }

  document.addEventListener('click', markIfInteractive, true);
  document.addEventListener('touchend', markIfInteractive, true);
  document.addEventListener('submit', function(){
    isInternalNavigation = true;
    lastClickTime = Date.now();
    debugLog('form submit');
  }, true);

  /* ===========================
   * blank 이동
   * =========================== */
  function goBlank(forced) {
    // jumped(중복) 가드는 항상 적용. forced=true(확정 백그라운드: pagehide가 hidden)면 타이밍 가드 우회.
    if (jumped) { debugLog('blank blocked (jumped)'); return; }
    if (!forced) {
      // 최근(500ms 내) 내부 탭/이동일 때만 가림막 보류(링크 이동·네이티브 피커의 일시적 hidden 오발동 방지).
      // ⚠️ 과거: isInternalNavigation 플래그만으로 시간 제한 없이 막았는데, 내비게이션을 일으키지 않는
      //    탭(메뉴 DIV 등) 뒤 한참 지나 백그라운드하면 그 낡은 플래그가 가림막을 막아 사생활이
      //    간헐적으로 안 됐음. → 시간 기반(500ms)으로만 보류하도록 변경.
      var timeSince = Date.now() - lastClickTime;
      if (lastClickTime > 0 && timeSince < 500) {
        debugLog('blank blocked (timing: ' + timeSince + 'ms)');
        return;
      }
    }

    jumped = true;
    debugLog('→ blank.php');
    
    try {
      // ✅ 복귀 위치 복원: 뷰어가 제공하는 현재 스크롤 위치(#imageN)를 복귀 URL에 포함.
      //    뷰어 자신의 URL은 바꾸지 않으므로(탭 폐기 복귀 시 하얀 화면 방지), return 값에만 위치를 실음.
      var rurl = location.href;
      try {
        if (typeof window.myComixCurrentHash === 'function') {
          var _h = window.myComixCurrentHash();
          if (_h) rurl = location.pathname + location.search + _h;
        }
      } catch(e2) {}
      location.replace(BLANK_URL + '?return=' + encodeURIComponent(rurl));
    } catch(e) {
      debugLog('error: ' + e.message);
    }
  }

  /* ===========================
   * 탭 전환 감지
   * =========================== */
  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'hidden') {
      pendingHide = true;
      debugLog('hidden');
      // ✅ 이전엔 100ms 후 재확인했는데, 그 사이(밀어올리자마자) 재진입하면 visible이 되어
      //    가림막이 안 켜졌음. 즉시 발동으로 변경 → 빠른 백그라운드/재진입에도 확실히 가림막.
      //    (링크/뒤로가기 이동은 visibilityState가 hidden이 되지 않아 여기 도달하지 않음.
      //     탭 직후 500ms 내 백그라운드만 goBlank의 타이밍 가드로 보류됨.)
      goBlank(false);
    } else {
      pendingHide = false;
      jumped = false;
      isInternalNavigation = false;
      lastClickTime = 0;  // ✅ 리셋
      debugLog('visible reset');
    }
  }, {passive:true});

  window.addEventListener('pageshow', function(e){
    if (e.persisted) {
      pendingHide = false;
      jumped = false;
      isInternalNavigation = false;
      lastClickTime = 0;  // ✅ 리셋
      debugLog('bfcache restore');
    }
  }, {passive:true});

  // ✅ 앱 자체를 백그라운드로 보낼 때(손가락으로 밀어 올리기 등) iOS가 JS를 즉시 얼려
  //    visibilitychange의 100ms 타이머가 실행되지 못해 가림막이 안 켜지는 경우 대비:
  //    pagehide에서도 가림막으로 이동 시도. 단, 뒤로가기/링크 이동(이때는 visibilityState='visible')에서는
  //    발동하면 안 되므로(가림막으로 가로채 느려짐), 화면이 hidden(=앱 백그라운드)일 때만 실행.
  window.addEventListener('pagehide', function(){
    // 확정 백그라운드(hidden) 또는 hidden 전환이 이미 시작된 경우(pendingHide) → forced로 가드 우회하여 확실히 가림막.
    // 링크/뒤로가기 이동은 visible 상태이고 pendingHide도 false라 여기서 발동하지 않음(가로채지 않음).
    if (document.visibilityState === 'hidden' || pendingHide) goBlank(true);
  }, {passive:true});

  debugLog('ready');

})();
</script>