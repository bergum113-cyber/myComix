<?php
/**
 * myComix - Tab switch privacy (MOBILE ONLY)
 *
 * @version 1.9 (STABLE) - 문서 4 기반 + 문서 3 장점 병합
 * @date 2026-01-13
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
  function goBlank() {
    // ✅ 500ms 타이밍 안전장치 (문서 3에서 추가)
    var timeSince = Date.now() - lastClickTime;
    
    if (isInternalNavigation || jumped) {
      debugLog('blank blocked (flag)');
      return;
    }
    
    if (timeSince < 500 && lastClickTime > 0) {
      debugLog('blank blocked (timing: ' + timeSince + 'ms)');
      return;
    }
    
    jumped = true;
    debugLog('→ blank.php');
    
    try {
      location.replace(BLANK_URL + '?return=' + encodeURIComponent(location.href));
    } catch(e) {
      debugLog('error: ' + e.message);
    }
  }

  /* ===========================
   * 탭 전환 감지
   * =========================== */
  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'hidden') {
      debugLog('hidden');
      setTimeout(function(){
        if (document.visibilityState === 'hidden') goBlank();
      }, 100);
    } else {
      jumped = false;
      isInternalNavigation = false;
      lastClickTime = 0;  // ✅ 리셋
      debugLog('visible reset');
    }
  }, {passive:true});

  window.addEventListener('pageshow', function(e){
    if (e.persisted) {
      jumped = false;
      isInternalNavigation = false;
      lastClickTime = 0;  // ✅ 리셋
      debugLog('bfcache restore');
    }
  }, {passive:true});

  debugLog('ready');

})();
</script>