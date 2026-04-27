/**
 * myComix 다크모드 관리 스크립트
 * @version 1.0
 * @date 2026-01-08
 */

(function() {
    'use strict';
    
    // 상수
    const STORAGE_KEY = 'mycomix_theme';
    const THEMES = ['light', 'dark'];  // 시스템 설정 제거
    
    // 현재 테마
    let currentTheme = 'light';
    
    /**
     * 시스템 테마 감지
     */
    function getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }
    
    /**
     * 테마 적용
     */
    function applyTheme(theme) {
        const html = document.documentElement;
        const body = document.body;
        
        // data-theme 속성 설정
        html.setAttribute('data-theme', theme);
        
        // 클래스 제거
        html.classList.remove('dark-mode');
        body.classList.remove('dark-mode');
        
        // 다크 모드일 때만 클래스 추가 (라이트 모드는 기존 스타일 유지)
        if (theme === 'dark') {
            html.classList.add('dark-mode');
            body.classList.add('dark-mode');
        }
        
        // 메타 테마 색상 업데이트 (모바일 브라우저 상단바)
        updateMetaThemeColor(theme);
        
        // 토글 버튼 아이콘 업데이트
        updateToggleIcon(theme);
        
        currentTheme = theme;
    }
    
    /**
     * 메타 테마 색상 업데이트
     */
    function updateMetaThemeColor(theme) {
        let metaTheme = document.querySelector('meta[name="theme-color"]');
        
        if (!metaTheme) {
            metaTheme = document.createElement('meta');
            metaTheme.name = 'theme-color';
            document.head.appendChild(metaTheme);
        }
        
        metaTheme.content = theme === 'dark' ? '#121212' : '#ffffff';
    }
    
    /**
     * 토글 버튼 아이콘 업데이트
     */
    function updateToggleIcon(theme) {
        const toggleBtn = document.getElementById('darkmode-toggle');
        if (toggleBtn) {
            toggleBtn.innerHTML = theme === 'dark' ? '☀️' : '🌙';
            toggleBtn.title = theme === 'dark' ? (window._dmI18n?.toLightMode || '라이트 모드로 전환') : (window._dmI18n?.toDarkMode || '다크 모드로 전환');
            // 배경색 업데이트 (투명도 포함)
            toggleBtn.style.backgroundColor = theme === 'dark' ? 'rgba(64, 64, 64, 0.7)' : 'rgba(108, 117, 125, 0.7)';
            toggleBtn.style.color = theme === 'dark' ? '#e0e0e0' : '#ffffff';
        }
    }
    
    /**
     * 테마 저장
     */
    function saveTheme(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
            // 쿠키에도 저장 (PHP에서 접근용)
            document.cookie = `darkmode=${theme}; path=/; max-age=31536000`;
        } catch (e) {
            console.warn('테마 저장 실패:', e);
        }
    }
    
    /**
     * 테마 불러오기
     */
    function loadTheme() {
        try {
            // localStorage 우선
            let theme = localStorage.getItem(STORAGE_KEY);
            
            // 쿠키에서 불러오기 (fallback)
            if (!theme) {
                const match = document.cookie.match(/darkmode=([^;]+)/);
                if (match) {
                    theme = match[1];
                }
            }
            
            // 유효성 검사
            if (THEMES.includes(theme)) {
                return theme;
            }
        } catch (e) {
            console.warn('테마 불러오기 실패:', e);
        }
        
        return 'light'; // 기본값
    }
    
    /**
     * 테마 순환 (light → dark → system → light...)
     */
    function cycleTheme() {
        const currentIndex = THEMES.indexOf(currentTheme);
        const nextIndex = (currentIndex + 1) % THEMES.length;
        const nextTheme = THEMES[nextIndex];
        
        applyTheme(nextTheme);
        saveTheme(nextTheme);
        
        // 토스트 알림 (선택적)
        showThemeToast(nextTheme);
    }
    
    /**
     * 토스트 알림 표시
     */
    function showThemeToast(theme) {
        // 기존 토스트 제거
        const existing = document.getElementById('theme-toast');
        if (existing) existing.remove();
        
        const themeNames = {
            'light': (window._dmI18n?.lightMode || '☀️ 라이트 모드'),
            'dark': (window._dmI18n?.darkMode || '🌙 다크 모드')
        };
        
        const isDark = theme === 'dark';
        
        const toast = document.createElement('div');
        toast.id = 'theme-toast';
        toast.style.cssText = `
            position: fixed;
            bottom: 140px;
            right: 20px;
            background: ${isDark ? 'rgba(45, 45, 45, 0.9)' : 'rgba(233, 236, 239, 0.9)'};
            color: ${isDark ? '#e0e0e0' : '#212529'};
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            z-index: 100000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: fadeInUp 0.3s ease;
        `;
        toast.textContent = themeNames[theme] || theme;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }
    
    /**
     * 토글 버튼 생성
     */
    function createToggleButton() {
        // 이미 있으면 생성하지 않음
        if (document.getElementById('darkmode-toggle')) return;
        
        const btn = document.createElement('button');
        btn.id = 'darkmode-toggle';
        btn.className = 'darkmode-toggle';
        btn.setAttribute('aria-label', (window._dmI18n?.changeTheme || '테마 변경'));
        btn.setAttribute('type', 'button');
        
        btn.innerHTML = currentTheme === 'dark' ? '☀️' : '🌙';
        btn.title = currentTheme === 'dark' ? (window._dmI18n?.toLightMode || '라이트 모드로 전환') : (window._dmI18n?.toDarkMode || '다크 모드로 전환');
        
        // 인라인 스타일 - 스크롤탑과 같은 크기, 투명도, 아래 위치
        btn.style.cssText = `
            position: fixed !important;
            bottom: 45px !important;
            right: 20px !important;
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            background-color: rgba(108, 117, 125, 0.7) !important;
            color: #ffffff !important;
            border: none !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 18px !important;
            z-index: 9998 !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
            opacity: 0.8 !important;
        `;
        
        btn.addEventListener('click', cycleTheme);
        
        // hover 효과
        btn.addEventListener('mouseenter', function() {
            this.style.opacity = '1';
            this.style.transform = 'scale(1.1)';
        });
        btn.addEventListener('mouseleave', function() {
            this.style.opacity = '0.8';
            this.style.transform = 'scale(1)';
        });
        
        document.body.appendChild(btn);
        console.log('다크모드 토글 버튼 생성됨');
    }
    
    /**
     * 시스템 테마 변경 감지
     */
    function watchSystemTheme() {
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (currentTheme === 'system') {
                    applyTheme('system');
                }
            });
        }
    }
    
    /**
     * CSS 애니메이션 스타일 추가
     */
    function addAnimationStyles() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }
    
    /**
     * 초기화
     */
    function init() {
        // 애니메이션 스타일 추가
        addAnimationStyles();
        
        // 저장된 테마 불러오기
        const savedTheme = loadTheme();
        
        // 테마 적용 (깜빡임 방지를 위해 빠르게 적용)
        applyTheme(savedTheme);
        
        // DOM 로드 후 토글 버튼 생성
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', createToggleButton);
        } else {
            createToggleButton();
        }
        
        // 페이지 완전 로드 후 버튼 확인 (fallback)
        window.addEventListener('load', function() {
            if (!document.getElementById('darkmode-toggle')) {
                createToggleButton();
            }
        });
        
        // 시스템 테마 변경 감지
        watchSystemTheme();
    }
    
    // 전역 API 노출
    window.DarkMode = {
        get: () => currentTheme,
        set: (theme) => {
            if (THEMES.includes(theme)) {
                applyTheme(theme);
                saveTheme(theme);
            }
        },
        toggle: cycleTheme,
        getEffective: () => currentTheme
    };
    
    // 초기화 실행
    init();
})();