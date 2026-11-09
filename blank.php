<?php
/**
 * myComix - 탭 전환 보호용 빈 페이지
 * 
 * @version 1.2 - 동적 경로 생성으로 서브디렉토리 지원
 * @date 2026-01-10
 */

// ★ (2026-10-30) 탭 가림막은 사람이 연 화면이 아님 — 자동 로그아웃용 활동 기록·페이지 표시를 바꾸지 않음
//   (바꾸면 '비대상 페이지에서 옴'으로 잡혀, 가림막을 거쳐 돌아올 때 비활동 자동 로그아웃 검사를 건너뜀)
define('MC_NO_ACTIVITY_TOUCH', true);
// ✅ bootstrap.php 사용으로 통일
require_once __DIR__ . '/bootstrap.php';

// 브랜딩 로드 (공통 함수 사용)
$_branding = load_branding();
$_page_title = $_branding['page_title'] ?? 'myComix';

// ✅ 동적 경로 생성 (서브디렉토리 지원)
// bootstrap.php에서 설정된 전역 $base_path 사용
global $base_path;
$_session_check_url = $base_path . '/session_check.php';
$_login_url = $base_path . '/login.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo h($_page_title); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    html,body{margin:0;height:100%;background:#fff}
    /* ✅ 로딩 표시 */
    .loading{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);font-size:14px;color:#666;font-family:sans-serif}
    .spinner{width:24px;height:24px;border:3px solid #eee;border-top-color:#666;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 10px}
    @keyframes spin{to{transform:rotate(360deg)}}
  </style>
</head>
<body>
<div id="cover" style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:#fff;cursor:pointer;-webkit-tap-highlight-color:transparent;user-select:none;">
  <div style="font:15px/1.5 sans-serif;color:#555;text-align:center;padding:14px 24px;border:1px solid #e2e4e8;border-radius:12px;background:#fafbfc;box-shadow:0 1px 3px rgba(0,0,0,0.06);"><?php echo __h("blank_tap_continue"); /* ★ (2026-11-02 10:01) 언어 키로 */ ?></div>
</div>
<div class="loading" id="loadingIndicator" style="display:none;">
  <div class="spinner"></div>
  <div><?php echo __h("loading"); ?></div>
</div>
<script>
(function(){
  // ✅ 서버에서 생성한 동적 경로 사용
  var SESSION_CHECK_URL = <?php echo js((string)$_session_check_url); /* ★ (2026-11-02 11:39) 설치 폴더 이름에 ' 가 있어도 깨지지 않게 */ ?>;
  var LOGIN_URL = <?php echo js((string)$_login_url); ?>;
  
  var params = new URLSearchParams(location.search);
  var returnUrl = params.get('return');
  
  if (!returnUrl) return;
  // ⚠️ URLSearchParams.get()이 이미 1회 디코딩함. 여기서 decodeURIComponent를 다시 호출하면
  //    파일명 속 %23(#) 등이 생짜 문자가 되어 file 파라미터가 잘려나감(제목에 #가 든 파일이
  //    가림막 복귀 후 흰 화면이 되던 원인). 추가 디코딩하지 않고 그대로 사용한다.

  // ✅ 오픈 리다이렉트 방지(login.php와 동일 정책): 같은 출처의 절대경로(/로 시작)만 허용.
  //    외부/비정상 URL은 안전한 기본 홈(index.php)으로. 같은 출처면 경로+쿼리+위치(#)만 정규화하여 보존.
  (function(){
    try {
      if (returnUrl.indexOf('\\') !== -1) { returnUrl = 'index.php'; return; }   // 백슬래시 우회 차단
      var r = new URL(returnUrl, location.origin);
      if (r.origin !== location.origin || r.pathname.charAt(0) !== '/') { returnUrl = 'index.php'; return; }
      returnUrl = r.pathname + r.search + r.hash;   // 같은 출처 경로만(호스트 제거), 스크롤 위치(#) 보존
      // ★ (2026-11-08 전체 점검 2차 fix74) '/..//다른곳' · '/.//다른곳' 은 같은 출처로 읽히지만 정규화 뒤 경로가 '//다른곳' 이 되어
      //   location.replace 가 그 주소를 '//호스트'(다른 사이트)로 보냈음 → 경로가 // 로 시작하면 처음 화면으로
      if (returnUrl.charAt(1) === '/') { returnUrl = 'index.php'; }
    } catch(e) { returnUrl = 'index.php'; }
  })();

  var isChecking = false;  // ✅ 중복 실행 방지

  // ✅ 세션 체크 후 적절한 페이지로 이동
  function checkSessionAndRedirect() {
    if (isChecking) return;
    isChecking = true;
    var _cover = document.getElementById('cover');
    var _load = document.getElementById('loadingIndicator');
    if (_cover) _cover.style.display = 'none';
    if (_load) _load.style.display = 'block';

    // 세션 확인. 5G↔LTE 등 네트워크 전환 순간엔 이 통신이 일시적으로 실패하거나 멈출 수 있어,
    // 실패 시 짧게 자동 재시도하고, 멈춤(hang) 방지를 위해 각 시도에 타임아웃을 둔다.
    // 성공 시 동작은 기존과 동일(유효→원위치 복원 / 만료→로그인 이동). 재시도 모두 실패 시에만 커튼 유지.
    var MAX_TRIES = 3;        // 총 시도 횟수
    var RETRY_DELAY = 700;    // 재시도 간격(ms)
    var FETCH_TIMEOUT = 4000; // 한 시도가 응답 없이 멈춰있을 때 끊는 시간(ms)

    function restoreCurtain() {
      // 재시도 소진 → 커튼 유지(보안: 어디로도 이동 안 함), 사용자가 다시 탭 가능
      isChecking = false;
      var c = document.getElementById('cover');
      var l = document.getElementById('loadingIndicator');
      if (l) l.style.display = 'none';
      if (c) c.style.display = 'flex';
    }

    function attempt(n) {
      var ctrl = null, to = null;
      try { if (typeof AbortController !== 'undefined') ctrl = new AbortController(); } catch(e){}
      var opts = { method: 'GET', credentials: 'same-origin', cache: 'no-store' };
      if (ctrl) { opts.signal = ctrl.signal; to = setTimeout(function(){ try { ctrl.abort(); } catch(e){} }, FETCH_TIMEOUT); }

      fetch(SESSION_CHECK_URL, opts)
        .then(function(response) {
          if (to) { clearTimeout(to); to = null; }
          if (!response.ok) throw new Error('status ' + response.status);
          return response.json();
        })
        .then(function(data) {
          if (data.valid) {
            // 세션 유효 → 원래 페이지로 (스크롤 위치 #는 returnUrl에 포함됨)
            // ✅ 복귀 직후 목록(index)에서 가림막 탭의 고스트클릭/빠른 2번째 탭이 항목을 눌러 엉뚱하게
            //    진입하는 것을 막기 위한 플래그. index가 로드 시 이 플래그를 읽어 짧게 첫 클릭을 흡수함.
            try { sessionStorage.setItem('mycomix_curtain_return', String(Date.now())); } catch(e){}
            location.replace(returnUrl);
          } else {
            // 세션 만료 → 로그인 페이지로 (보던 화면 URL을 함께 전달)
            location.replace(LOGIN_URL + '?return=' + encodeURIComponent(returnUrl));
          }
        })
        .catch(function() {
          if (to) { clearTimeout(to); to = null; }
          // 네트워크 전환(5G↔LTE 등)으로 실패/타임아웃 → 짧게 자동 재시도
          if (n < MAX_TRIES) {
            setTimeout(function(){ attempt(n + 1); }, RETRY_DELAY);
          } else {
            restoreCurtain();
          }
        });
    }
    attempt(1);
  }

  // ✅ 진짜 커튼: 자동 복귀하지 않음. 사용자가 화면을 탭(클릭/터치)해야만 세션 확인 후 내용으로 이동.
  //    → 백그라운드 복귀 시 내용이 자동 노출되지 않음(사생활 보호).
  document.addEventListener('click', checkSessionAndRedirect, { passive: true });
  document.addEventListener('touchend', checkSessionAndRedirect, { passive: true });

  // ✅ iOS Safari가 오래된 탭을 freeze/discard(또는 bfcache) 했다가 되살릴 때,
  //    직전 탭으로 cover가 숨겨졌거나 세션확인이 멈춘 채 되살아나 "흰 화면"으로 보이는 문제 방지.
  //    되살아나면 커튼을 깨끗한 탭 가능 상태로 되돌린다(자동 복귀는 하지 않음 → 사생활 보호 유지).
  function resetCurtainState() {
    isChecking = false;
    var c = document.getElementById('cover');
    var l = document.getElementById('loadingIndicator');
    if (l) l.style.display = 'none';
    if (c) c.style.display = 'flex';
  }
  window.addEventListener('pageshow', function(){ resetCurtainState(); });
  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'visible') resetCurtainState();
  });
  // ✅ 최후 보루: 브라우저 완전 종료 후 재실행 등으로 cover가 숨겨진 채(이전 세션확인 진행 중 상태로)
  //    복원되고 pageshow/visibilitychange가 오지 않아 흰 화면이 남는 경우 대비.
  //    세션확인 진행 중(isChecking)이 아니면 커튼을 다시 보이게 한다(자동 복귀는 하지 않음 → 사생활 보호 유지).
  setTimeout(function(){ if (!isChecking) resetCurtainState(); }, 800);
})();
</script>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
</body>
</html>