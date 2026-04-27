/**
 * 자동 로그아웃 타이머 - 완벽 버전
 * 버전: 3.3 (2026-01-10 디버그 로그 정리)
 * 추가: 마우스/터치 활동 감지
 */

(function() {
    'use strict';
    
    // ✅ 디버그 모드 (프로덕션에서는 false로 설정)
    const DEBUG = false;
    const log = DEBUG ? console.log.bind(console) : function() {};
    
    log('🔥 자동 로그아웃 스크립트 v3.3 시작');
    
    // 설정값
    const CONFIG = {
        TIMEOUT: window.SESSION_TIMEOUT || 600,
        WARNING_TIME: 60, // 남은 시간 60초일 때 경고 모달 표시
        CHECK_INTERVAL: 2000, // 2초마다 체크
        STORAGE_KEY: 'auto_logout_time',
        ACTIVITY_THROTTLE: 5000 // 활동 감지 5초에 한 번만
    };
    
    let timer = null;
    let checkTimer = null;
    let remainingTime = window.SESSION_REMAINING || CONFIG.TIMEOUT;
    let warningShown = false;
    let isProcessingLogout = false;
    let contextMenuBlocked = false;
    let lastActivity = 0; // 마지막 활동 시간
    
    log('⏰ 설정:', CONFIG);
    
    // 모달 HTML 생성
    function createModal() {
        if (document.getElementById('auto-logout-modal')) return;
        
        const modalHTML = `
            <div id="auto-logout-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
                 background:rgba(0,0,0,0.7); z-index:9999; justify-content:center; align-items:center;">
                <div style="background:white; padding:30px; border-radius:10px; text-align:center; max-width:400px; 
                     box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                    <h3 style="color:#dc3545; margin-bottom:20px;">${window._alI18n?.title || "⚠️ 자동 로그아웃 예정"}</h3>
                    <p style="font-size:16px; margin-bottom:20px;">
                        <strong id="logout-countdown" style="font-size:32px; color:#dc3545;">0</strong>${window._alI18n?.countdown || "초 후"}<br>
                        ${window._alI18n?.will_logout || "자동으로 로그아웃됩니다."}
                    </p>
                    <p style="color:#666; font-size:14px; margin-bottom:25px;">
                        ${window._alI18n?.continue_msg || "계속 사용하시려면 아래 버튼을 클릭하세요."}
                    </p>
                    <div style="display:flex; gap:10px; justify-content:center;">
                        <button id="btn-logout-now" class="btn btn-secondary" 
                                style="padding:10px 20px; cursor:pointer;">
                            ${window._alI18n?.logout || "로그아웃"}
                        </button>
                        <button id="btn-extend-session" class="btn btn-primary" 
                                style="padding:10px 20px; cursor:pointer;">
                            ${window._alI18n?.extend || "로그인 연장"}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // 버튼 이벤트
        document.getElementById('btn-logout-now').addEventListener('click', function(e) {
            e.stopPropagation(); // 이벤트 버블링 방지
            
            log('👤 수동 로그아웃 버튼 클릭');
            
            // ✅ 즉시 플래그 설정 (활동 감지 방지)
            isProcessingLogout = true;
            
            // ✅ 수동 로그아웃은 mode=logout
            disableRefreshBlock();
            if (timer) clearInterval(timer);
            if (checkTimer) clearInterval(checkTimer);
            
            window.location.href = 'login.php?mode=logout';
        });

        document.getElementById('btn-extend-session').addEventListener('click', function(e) {
            e.stopPropagation(); // 이벤트 버블링 방지
            log('🔄 연장 버튼 클릭');
            extendSession();
        });
    }
    
    // 모달 표시
    function showWarning() {
        if (warningShown) return;
        
        log('⚠️ 경고 모달 표시');
        const modal = document.getElementById('auto-logout-modal');
        if (modal) {
            modal.style.display = 'flex';
            warningShown = true;
            updateCountdown();
            
            // ✅ 새로고침 방지 활성화
            enableRefreshBlock();
        }
    }
    
    // 모달 숨김
    function hideWarning() {
        log('✅ 모달 숨김');
        const modal = document.getElementById('auto-logout-modal');
        if (modal) {
            modal.style.display = 'none';
            warningShown = false;
            
            // ✅ 새로고침 방지 해제
            disableRefreshBlock();
        }
    }
    
    // 카운트다운 업데이트
    function updateCountdown() {
        const countdownEl = document.getElementById('logout-countdown');
        if (countdownEl) {
            countdownEl.textContent = Math.max(0, Math.ceil(remainingTime));
        }
    }
    
    // ✅ 새로고침 방지 활성화 (F5, Ctrl+R, 우클릭만 차단)
    function enableRefreshBlock() {
        log('🚫 새로고침 차단 활성화 (F5, 우클릭만)');
        
        // F5, Ctrl+R 키 차단
        document.addEventListener('keydown', blockRefreshKeys, true);
        
        // 우클릭 메뉴 차단
        if (!contextMenuBlocked) {
            document.addEventListener('contextmenu', blockContextMenu, true);
            contextMenuBlocked = true;
            log('🚫 우클릭 메뉴 차단 활성화');
        }
    }
    
    // ✅ 새로고침 방지 해제
    function disableRefreshBlock() {
        log('✅ 새로고침 차단 해제');
        
        document.removeEventListener('keydown', blockRefreshKeys, true);
        
        if (contextMenuBlocked) {
            document.removeEventListener('contextmenu', blockContextMenu, true);
            contextMenuBlocked = false;
            log('✅ 우클릭 메뉴 차단 해제');
        }
    }
    
    // ✅ 키보드 새로고침 차단
    function blockRefreshKeys(e) {
        // F5
        if (e.key === 'F5' || e.keyCode === 116) {
            e.preventDefault();
            e.stopPropagation();
            log('🚫 F5 차단됨');
            return false;
        }
        
        // Ctrl + R 또는 Cmd + R (Mac)
        if ((e.ctrlKey || e.metaKey) && (e.key === 'r' || e.key === 'R' || e.keyCode === 82)) {
            e.preventDefault();
            e.stopPropagation();
            log('🚫 Ctrl+R 차단됨');
            return false;
        }
    }
    
    // ✅ 우클릭 메뉴 차단
    function blockContextMenu(e) {
        e.preventDefault();
        e.stopPropagation();
        log('🚫 우클릭 메뉴 차단됨');
        return false;
    }
    
    // ✅ 타이머 리셋 (사용자 활동 감지)
    function resetTimer() {
        if (isProcessingLogout) return;
        
        remainingTime = CONFIG.TIMEOUT;
        
        // 경고 모달이 떠있으면 숨김
        if (warningShown) {
            hideWarning();
        }
        
        log('🔄 활동 감지 - 타이머 리셋:', remainingTime + '초');
        
        // 로컬스토리지 업데이트
        try {
            localStorage.setItem(CONFIG.STORAGE_KEY, JSON.stringify({
                remaining: remainingTime,
                timestamp: Date.now()
            }));
        } catch(e) {}
        
        // ✅ 서버 세션도 연장 요청
        fetch('init.php?check_session=1&extend=1', {
            method: 'GET',
            cache: 'no-cache'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'active') {
                remainingTime = data.remaining;
                log('✅ 서버 세션 연장:', remainingTime + '초');
            }
        })
        .catch(err => {
            log('❌ ❌ 서버 연장 실패:', err);
        });
    }
    
    // ✅ 사용자 활동 감지 (throttled)
    function handleUserActivity(e) {
        // ✅ 모달 내부 클릭은 활동으로 간주하지 않음
        if (e && e.target) {
            const modal = document.getElementById('auto-logout-modal');
            if (modal && modal.contains(e.target)) {
                return; // 모달 내부 클릭 무시
            }
        }
        
        const now = Date.now();
        
        // throttle: 마지막 활동 후 5초 이상 경과했을 때만 처리
        if (now - lastActivity > CONFIG.ACTIVITY_THROTTLE) {
            lastActivity = now;
            resetTimer();
        }
    }
    
    // 세션 연장
    function extendSession() {
        log('🔄 세션 연장 요청...');
        
        fetch('init.php?check_session=1&extend=1', {
            method: 'GET',
            cache: 'no-cache'
        })
        .then(response => response.json())
        .then(data => {
            log('📡 연장 응답:', data);
            
            if (data.status === 'active') {
                remainingTime = data.remaining;
                hideWarning();
                log('✅ 세션 연장 성공:', remainingTime + '초');
            } else {
                log('❌ 세션 만료됨');
                logout();
            }
        })
        .catch(err => {
            log('❌ ❌ 세션 연장 실패:', err);
        });
    }
    
    // 서버와 세션 상태 동기화
    function checkServerSession() {
        if (isProcessingLogout) return;
        
        fetch('init.php?check_session=1', {
            method: 'GET',
            cache: 'no-cache'
        })
        .then(response => response.json())
        .then(data => {
            if (isProcessingLogout) return;
            
            // ✅ 자동 로그아웃 비활성화 시 타이머 중지
            if (data.status === 'disabled') {
                log('⏹️ 자동 로그아웃 비활성화됨 - 타이머 중지');
                if (timer) clearInterval(timer);
                if (checkTimer) clearInterval(checkTimer);
                hideWarning();
                return;
            }
            
            if (data.status === 'logged_out' || data.status === 'expired') {
                log('❌ 서버: 세션 만료');
                logout();
            } else if (data.status === 'active') {
                remainingTime = data.remaining;
                //log('✅ 서버 동기화:', remainingTime + '초 남음');
                
                // 경고 시점 체크
                if (remainingTime <= CONFIG.WARNING_TIME && !warningShown) {
                    showWarning();
                }
            }
        })
        .catch(err => {
            log('❌ ❌ 세션 체크 실패:', err);
        });
    }
    
    // 로그아웃 실행 (자동 타임아웃)
    function logout() {
        if (isProcessingLogout) {
            log('⏳ 이미 로그아웃 처리 중...');
            return;
        }
        
        isProcessingLogout = true;
        log('🚪 자동 타임아웃 로그아웃');
        
        disableRefreshBlock();
        
        if (timer) clearInterval(timer);
        if (checkTimer) clearInterval(checkTimer);
        
        // ✅ 자동 타임아웃은 mode=timeout
        window.location.href = 'login.php?mode=timeout';
    }
    
    // 타이머 시작
    function startTimer() {
        if (timer) clearInterval(timer);
        if (checkTimer) clearInterval(checkTimer);
        
        log('⏱️ 타이머 시작:', remainingTime + '초');
        
        // 1초마다 카운트다운
        timer = setInterval(function() {
            if (isProcessingLogout) return;
            
            remainingTime--;
            
            // ✅ 디버깅: 10초마다 로그
            if (remainingTime % 10 === 0) {
                log('⏱️ 카운트다운:', remainingTime + '초');
            }
            
            // 경고 표시
            if (remainingTime <= CONFIG.WARNING_TIME && !warningShown) {
                showWarning();
            }
            
            // 카운트다운 업데이트
            if (warningShown) {
                updateCountdown();
            }
            
            // 타임아웃
            if (remainingTime <= 0) {
                log('⏰ 타임아웃!');
                logout();
            }
            
            // 로컬스토리지에 저장
            try {
                localStorage.setItem(CONFIG.STORAGE_KEY, JSON.stringify({
                    remaining: remainingTime,
                    timestamp: Date.now()
                }));
            } catch(e) {}
            
        }, 1000);
        
        log('✅ 1초 타이머 등록됨, timer ID:', timer);
        
        // 정기적으로 서버와 동기화
        checkTimer = setInterval(checkServerSession, CONFIG.CHECK_INTERVAL);
        
        log('✅ 서버 동기화 타이머 등록됨, checkTimer ID:', checkTimer);
    }
    
    // 페이지 가시성 변경 감지 (탭 전환)
    function handleVisibilityChange() {
        if (document.visibilityState === 'visible') {
            log('👁️ 탭 복귀');
            
            // 로컬스토리지에서 읽기
            try {
                const stored = localStorage.getItem(CONFIG.STORAGE_KEY);
                if (stored) {
                    const data = JSON.parse(stored);
                    const timeSinceStore = Math.floor((Date.now() - data.timestamp) / 1000);
                    remainingTime = Math.max(0, data.remaining - timeSinceStore);
                    log('📦 저장된 시간:', data.remaining, '경과:', timeSinceStore, '남은시간:', remainingTime);
                }
            } catch(e) {}
            
            // 서버와 즉시 동기화
            checkServerSession();
        }
    }
    
    // BFCache 복구 감지 (뒤로가기)
    function handlePageShow(event) {
        if (event.persisted) {
            log('🔙 BFCache 복구');
            checkServerSession();
        }
    }
    
    // ✅ 사용자 활동 이벤트 등록
    function registerActivityEvents() {
        const ACTIVITY_EVENTS = [
            'mousedown',   // 마우스 클릭
            'scroll',      // 스크롤
            'wheel',       // 마우스 휠 (북모드용)
            'touchstart',  // 모바일 터치 시작
            'touchmove'    // 모바일 터치 이동
        ];
        
        ACTIVITY_EVENTS.forEach(function(eventName) {
            document.addEventListener(eventName, handleUserActivity, { passive: true });
        });
        
        // ✅ window 레벨에서도 감지 (북모드 등 특수 뷰어에서 stopImmediatePropagation 우회)
        window.addEventListener('scroll', handleUserActivity, { passive: true });
        window.addEventListener('wheel', handleUserActivity, { passive: true });
        window.addEventListener('touchstart', handleUserActivity, { passive: true, capture: true });
        window.addEventListener('touchmove', handleUserActivity, { passive: true, capture: true });
        
        // ✅ keydown은 window 레벨에서 감지 (stopImmediatePropagation 우회)
        window.addEventListener('keydown', handleUserActivity, { passive: true, capture: true });
        
        log('👆 사용자 활동 감지 이벤트 등록 완료');
    }
    
    // 초기화
    function init() {
        log('🚀 초기화 시작');
        
        // ✅ 먼저 서버 상태 확인 후 타이머 시작
        fetch('init.php?check_session=1', {
            method: 'GET',
            cache: 'no-cache'
        })
        .then(response => response.json())
        .then(data => {
            // 자동 로그아웃 비활성화 시 종료
            if (data.status === 'disabled') {
                log('⏹️ 자동 로그아웃 비활성화됨 - 타이머 시작 안 함');
                return;
            }
            
            // 이미 로그아웃된 경우
            if (data.status === 'logged_out' || data.status === 'expired') {
                log('❌ 이미 로그아웃됨');
                window.location.href = 'login.php?mode=timeout';
                return;
            }
            
            // 활성화된 경우에만 타이머 시작
            if (data.status === 'active') {
                remainingTime = data.remaining;
                log('✅ 서버 세션 확인:', remainingTime + '초 남음');
                
                createModal();
                startTimer();
                
                // 이벤트 리스너
                document.addEventListener('visibilitychange', handleVisibilityChange);
                window.addEventListener('pageshow', handlePageShow);
                
                // ✅ 사용자 활동 감지 등록
                registerActivityEvents();
            }
        })
        .catch(err => {
            log('❌ ❌ 초기 세션 체크 실패:', err);
            // 실패 시에도 기본 타이머 시작
            createModal();
            startTimer();
            document.addEventListener('visibilitychange', handleVisibilityChange);
            window.addEventListener('pageshow', handlePageShow);
            registerActivityEvents();
        });
    }
    
    // DOM 로드 후 실행
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();