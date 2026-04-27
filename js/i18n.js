/**
 * myComix i18n v2 - 다국어 지원 시스템
 * @version 2.0
 * @date 2026-02-04
 * 
 * 2-pass 접근: exact match → phrase match
 * - exact: 텍스트 노드 전체가 키와 일치할 때만 치환 (짧은 키 안전)
 * - phrase: 부분 문자열 치환 (긴 고유 문장만 등록)
 */

(function() {
    'use strict';

    var STORAGE_KEY = 'mycomix_lang';
    var DEFAULT_LANG = 'ko';
    var SUPPORTED_LANGS = ['ko', 'en'];
    var LANG_LABELS = { ko: '한국어', en: 'English' };

    var exactMap = {};      // 정확히 일치할 때만 (trim 후)
    var phraseMap = {};     // 부분 문자열 치환 (안전한 긴 문장)
    var phraseSorted = [];  // 긴 것 먼저 정렬된 키
    var currentLang = DEFAULT_LANG;
    var observer = null;
    var isTranslating = false;

    // ========================================
    // 초기화
    // ========================================
    function init() {
        currentLang = localStorage.getItem(STORAGE_KEY) || DEFAULT_LANG;
        if (SUPPORTED_LANGS.indexOf(currentLang) < 0) currentLang = DEFAULT_LANG;

        if (currentLang === 'ko') {
            return;
        }

        loadTranslations(function() {
            translatePage();
            setupMutationObserver();
        });
    }

    // ========================================
    // 번역 데이터 로드
    // ========================================
    function loadTranslations(callback) {
        var basePath = getBasePath();
        var xhr = new XMLHttpRequest();
        xhr.open('GET', basePath + 'lang/en.json?v=' + Date.now(), true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    // exact 카테고리
                    if (data.exact) {
                        for (var k in data.exact) {
                            if (k && data.exact[k]) exactMap[k] = data.exact[k];
                        }
                    }
                    // phrase 카테고리
                    if (data.phrase) {
                        for (var k in data.phrase) {
                            if (k && data.phrase[k]) phraseMap[k] = data.phrase[k];
                        }
                    }
                    // phrase 키를 긴 것 먼저 정렬
                    phraseSorted = Object.keys(phraseMap).sort(function(a, b) {
                        return b.length - a.length;
                    });
                } catch(e) {
                    console.warn('[i18n] JSON parse error:', e);
                }
            }
            callback();
        };
        xhr.onerror = function() {
            console.warn('[i18n] Failed to load translations');
            callback();
        };
        xhr.send();
    }

    function getBasePath() {
        var path = window.location.pathname;
        return path.substring(0, path.lastIndexOf('/') + 1);
    }

    // ========================================
    // 페이지 전체 번역
    // ========================================
    function translatePage() {
        if (currentLang === 'ko') return;
        isTranslating = true;
        
        // title
        var titleEl = document.querySelector('title');
        if (titleEl) translateTextNode_direct(titleEl);
        
        // body
        translateNode(document.body);
        translateAttributes(document.body);
        
        isTranslating = false;
    }

    // ========================================
    // 노드 번역 (재귀)
    // ========================================
    var SKIP_TAGS = {SCRIPT:1, STYLE:1, TEXTAREA:1, CODE:1, PRE:1, SVG:1, NOSCRIPT:1};

    function translateNode(node) {
        if (!node) return;
        if (SKIP_TAGS[node.nodeName]) return;
        if (node.id === 'mycomix-lang-selector' || (node.classList && node.classList.contains('i18n-skip'))) return;

        var children = node.childNodes;
        for (var i = 0; i < children.length; i++) {
            var child = children[i];
            if (child.nodeType === 3) { // TEXT_NODE
                translateTextNode(child);
            } else if (child.nodeType === 1) { // ELEMENT_NODE
                translateNode(child);
            }
        }
    }

    // ========================================
    // 텍스트 노드 번역 (2-pass)
    // ========================================
    function translateTextNode(textNode) {
        var original = textNode.textContent;
        if (!original) return;
        
        var trimmed = original.trim();
        if (!trimmed) return;

        // Pass 1: Exact match (trim 후 전체 일치)
        if (exactMap[trimmed] !== undefined) {
            // 앞뒤 공백 유지하면서 치환
            textNode.textContent = original.replace(trimmed, exactMap[trimmed]);
            return;
        }

        // Pass 2: Phrase match (부분 문자열 - 안전한 긴 문장만)
        var result = original;
        var changed = false;
        for (var i = 0; i < phraseSorted.length; i++) {
            var ko = phraseSorted[i];
            if (result.indexOf(ko) >= 0) {
                result = result.split(ko).join(phraseMap[ko]);
                changed = true;
            }
        }
        if (changed) {
            textNode.textContent = result;
        }
    }

    // title 등 단일 요소 직접 번역
    function translateTextNode_direct(el) {
        if (!el) return;
        var text = el.textContent;
        if (!text) return;
        var trimmed = text.trim();
        if (exactMap[trimmed]) {
            el.textContent = text.replace(trimmed, exactMap[trimmed]);
        } else {
            var result = text;
            for (var i = 0; i < phraseSorted.length; i++) {
                var ko = phraseSorted[i];
                if (result.indexOf(ko) >= 0) {
                    result = result.split(ko).join(phraseMap[ko]);
                }
            }
            if (result !== text) el.textContent = result;
        }
    }

    // ========================================
    // 속성 번역 (placeholder, title)
    // ========================================
    function translateAttributes(root) {
        if (!root) return;
        
        var attrs = ['placeholder', 'title', 'aria-label'];
        for (var a = 0; a < attrs.length; a++) {
            var attrName = attrs[a];
            var elems = root.querySelectorAll('[' + attrName + ']');
            for (var i = 0; i < elems.length; i++) {
                if (elems[i].id === 'mycomix-lang-selector') continue;
                var val = elems[i].getAttribute(attrName);
                if (!val) continue;
                
                var trimmed = val.trim();
                // exact match first
                if (exactMap[trimmed]) {
                    elems[i].setAttribute(attrName, val.replace(trimmed, exactMap[trimmed]));
                    continue;
                }
                // phrase match
                var result = val;
                for (var j = 0; j < phraseSorted.length; j++) {
                    var ko = phraseSorted[j];
                    if (result.indexOf(ko) >= 0) {
                        result = result.split(ko).join(phraseMap[ko]);
                    }
                }
                if (result !== val) elems[i].setAttribute(attrName, result);
            }
        }
    }

    // ========================================
    // 전체 텍스트 번역 (alert/confirm용)
    // ========================================
    function translateText(text) {
        if (!text || currentLang === 'ko') return text;
        
        var trimmed = text.trim();
        // exact
        if (exactMap[trimmed]) {
            return text.replace(trimmed, exactMap[trimmed]);
        }
        // phrase
        var result = text;
        for (var i = 0; i < phraseSorted.length; i++) {
            var ko = phraseSorted[i];
            if (result.indexOf(ko) >= 0) {
                result = result.split(ko).join(phraseMap[ko]);
            }
        }
        return result;
    }

    // ========================================
    // MutationObserver
    // ========================================
    function setupMutationObserver() {
        if (observer) observer.disconnect();
        
        observer = new MutationObserver(function(mutations) {
            if (isTranslating) return;
            isTranslating = true;
            
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                if (m.addedNodes) {
                    for (var j = 0; j < m.addedNodes.length; j++) {
                        var node = m.addedNodes[j];
                        if (node.id === 'mycomix-lang-selector') continue;
                        if (node.nodeType === 1) {
                            translateNode(node);
                            translateAttributes(node);
                        } else if (node.nodeType === 3) {
                            translateTextNode(node);
                        }
                    }
                }
                if (m.type === 'characterData' && m.target.nodeType === 3) {
                    translateTextNode(m.target);
                }
            }
            
            isTranslating = false;
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    // ========================================
    // alert/confirm 오버라이드
    // ========================================
    if (typeof window._origAlert === 'undefined') {
        window._origAlert = window.alert;
        window.alert = function(msg) {
            return window._origAlert(typeof msg === 'string' ? translateText(msg) : msg);
        };
    }
    if (typeof window._origConfirm === 'undefined') {
        window._origConfirm = window.confirm;
        window.confirm = function(msg) {
            return window._origConfirm(typeof msg === 'string' ? translateText(msg) : msg);
        };
    }

    

    // ========================================
    // 전역 API
    // ========================================
    window.myComixI18n = {
        getLang: function() { return currentLang; },
        t: function(text) {
            if (currentLang === 'ko') return text;
            var trimmed = (text || '').trim();
            return exactMap[trimmed] || phraseMap[trimmed] || translateText(text);
        },
        setLang: function(lang) {
            if (SUPPORTED_LANGS.indexOf(lang) >= 0) {
                localStorage.setItem(STORAGE_KEY, lang);
                window.location.reload();
            }
        },
        translateElement: function(el) {
            if (currentLang !== 'ko' && el) {
                translateNode(el);
                translateAttributes(el);
            }
        },
        translateText: translateText
    };

    window._t = window.myComixI18n.t;

    // ========================================
    // DOM Ready
    // ========================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
