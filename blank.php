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
<div class="loading" id="loadingIndicator">
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
  
  // 디코딩
  try {
    returnUrl = decodeURIComponent(returnUrl);
  } catch(e) {
    return;
  }

  var isChecking = false;  // ✅ 중복 실행 방지

  // ✅ 세션 체크 후 적절한 페이지로 이동
  function checkSessionAndRedirect() {
    if (isChecking) return;
    isChecking = true;
    
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
        location.replace(returnUrl);
      } else {
        // 세션 만료 → 로그인 페이지로
        location.replace(LOGIN_URL);
      }
    })
    .catch(function() {
      // ✅ 에러 시 blank 유지 (보안: 어디로도 이동 안함)
      isChecking = false;
      // 재시도 가능하도록 플래그만 리셋
    });
  }

  // ✅ 1. 페이지 로드 시 즉시 실행 (가장 중요!)
  // 장시간 백그라운드 후 복귀 시 이벤트 없이도 작동
  if (document.visibilityState !== 'hidden') {
    checkSessionAndRedirect();
  }

  // ✅ 2. visibilitychange - 탭 전환 복귀
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden && returnUrl) {
      checkSessionAndRedirect();
    }
  });

  // ✅ 3. pageshow - bfcache에서 복원 시
  window.addEventListener('pageshow', function(event) {
    if (event.persisted && returnUrl) {
      isChecking = false;  // bfcache 복원 시 리셋
      checkSessionAndRedirect();
    }
  });

  // ✅ 4. focus - 일부 브라우저에서 추가 트리거
  window.addEventListener('focus', function() {
    if (returnUrl && !document.hidden) {
      checkSessionAndRedirect();
    }
  });
})();
</script>
</body>
</html>