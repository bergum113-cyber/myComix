<?php
/**
 * myComix - 탭 전환 보호용 빈 페이지
 * 
 * @version 1.2 - 동적 경로 생성으로 서브디렉토리 지원
 * @date 2026-01-10
 */

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
  <div style="font:14px sans-serif;color:#9aa0a6;">화면을 탭하면 계속 봅니다</div>
</div>
<div class="loading" id="loadingIndicator" style="display:none;">
  <div class="spinner"></div>
  <div>로딩 중...</div>
</div>
<script>
(function(){
  // ✅ 서버에서 생성한 동적 경로 사용
  var SESSION_CHECK_URL = '<?php echo $_session_check_url; ?>';
  var LOGIN_URL = '<?php echo $_login_url; ?>';
  
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
    
    fetch(SESSION_CHECK_URL, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store'
    })
    .then(function(response) {
      return response.json();
    })
    .then(function(data) {
      if (data.valid) {
        // 세션 유효 → 원래 페이지로
        // ✅ 복귀 직후 목록(index)에서 가림막 탭의 고스트클릭/빠른 2번째 탭이 항목을 눌러 엉뚱하게
        //    진입하는 것을 막기 위한 플래그. index가 로드 시 이 플래그를 읽어 짧게 첫 클릭을 흡수함.
        try { sessionStorage.setItem('mycomix_curtain_return', String(Date.now())); } catch(e){}
        location.replace(returnUrl);
      } else {
        // 세션 만료 → 로그인 페이지로 (보던 화면 URL을 함께 전달하여,
        // 로그인 유지 자동로그인 시 홈이 아닌 보던 화면으로 복귀하도록 함)
        location.replace(LOGIN_URL + '?return=' + encodeURIComponent(returnUrl));
      }
    })
    .catch(function() {
      // ✅ 에러 시 커튼 유지 (보안: 어디로도 이동 안함), 재탭 가능하도록 복구
      isChecking = false;
      var _cover = document.getElementById('cover');
      var _load = document.getElementById('loadingIndicator');
      if (_load) _load.style.display = 'none';
      if (_cover) _cover.style.display = 'flex';
    });
  }

  // ✅ 진짜 커튼: 자동 복귀하지 않음. 사용자가 화면을 탭(클릭/터치)해야만 세션 확인 후 내용으로 이동.
  //    → 백그라운드 복귀 시 내용이 자동 노출되지 않음(사생활 보호).
  document.addEventListener('click', checkSessionAndRedirect, { passive: true });
  document.addEventListener('touchend', checkSessionAndRedirect, { passive: true });
})();
</script>
</body>
</html>