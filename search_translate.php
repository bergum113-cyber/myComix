<?php
/**
 * 한영 검색어 연결(검색 번역 사전) — 검색할 때 한글↔영어 짝 단어도 함께 찾기
 * ★ (2026-11-06 08:10) index.php 에 있던 함수를 그대로 옮김(관리 화면의 '검색 미리보기'가 같은 규칙을 쓰게) — 내용은 바꾸지 않음
 *   index.php · admin_translations.php 가 require_once
 */
$GLOBALS['translation_cache'] = null;
$GLOBALS['reverse_translation_cache'] = null; // ✅ 역방향 캐시 추가

/**
 * 검색 번역 사전 로드 (양방향 지원)
 * ✅ 함수명 변경: loadTranslations → loadSearchTranslations (admin_translations.php와 구분)
 */
function loadSearchTranslations() {
    // 이미 로드했으면 캐시 반환
    if (!empty($GLOBALS['translation_cache'])) {
        return $GLOBALS['translation_cache'];
    }
    
    $translations_file = __DIR__ . '/src/search_translations.json';
    
    if (!is_file($translations_file)) {
        // error_log("⚠️ 번역 사전 파일이 없습니다: {$translations_file}");
        $GLOBALS['translation_cache'] = [];
        $GLOBALS['reverse_translation_cache'] = [];
        return [];
    }
    
    $content = file_get_contents($translations_file);
    $translations = json_decode($content, true);
    
    if (!is_array($translations)) {
        // error_log("❌ 번역 사전 JSON 파싱 오류");
        $GLOBALS['translation_cache'] = [];
        $GLOBALS['reverse_translation_cache'] = [];
        return [];
    }
    
    // ✅ 양방향 맵 생성 (모두 배열로)
    $translation_map = [];        // 한글 → 영어[] (배열)
    $reverse_translation_map = []; // 영어 → 한글[] (배열)
    
    foreach ($translations as $item) {
        // ★ (2026-11-08 전체 점검 6차 fix78) 글자가 아니거나 빈 짝은 건너뜀 — 손으로 고친 파일에 빈 한글('')이 있으면 아래 조각 맞추기가 한 글자도 줄이지 못해 한글 검색이 끝나지 않았고(무한 반복), 배열이면 화면이 오류로 멈췄음
        if (!is_array($item) || !is_string($item['korean'] ?? null) || !is_string($item['english'] ?? null) || trim($item['korean']) === '' || trim($item['english']) === '') continue;
        if (isset($item['korean']) && isset($item['english'])) {
            $korean_lower = mb_strtolower($item['korean'], 'UTF-8');
            $english_lower = strtolower($item['english']);
            
            // 한글 → 영어 (정방향) - 여러 개 가능하므로 배열로 저장
            if (!isset($translation_map[$korean_lower])) {
                $translation_map[$korean_lower] = [];
            }
            $translation_map[$korean_lower][] = $english_lower;
            
            // 영어 → 한글 (역방향) - 여러 개 가능하므로 배열로 저장
            if (!isset($reverse_translation_map[$english_lower])) {
                $reverse_translation_map[$english_lower] = [];
            }
            $reverse_translation_map[$english_lower][] = $korean_lower;
        }
    }
    
    $GLOBALS['translation_cache'] = $translation_map;
    $GLOBALS['reverse_translation_cache'] = $reverse_translation_map;
    
    // error_log("✅ 번역 사전 로드: " . count($translation_map) . "개 (양방향)");
    
    return $translation_map;
}

/**
 * 역방향 번역 (영어 → 한글)
 * @return array 매칭된 모든 한글 단어들의 배열
 */
function reverseTranslateSearch($query) {
    // 번역 사전 로드 (캐시에서)
    loadSearchTranslations();
    $reverse_map = $GLOBALS['reverse_translation_cache'];
    
    $query = trim($query);
    if (empty($query)) return [];
    
    $query_lower = strtolower($query);
    
    // error_log("🔍 역방향 번역 함수 호출: '{$query}' (소문자: '{$query_lower}')");
    // error_log("🔍 역방향 맵 샘플 5개: " . json_encode(array_slice($reverse_map, 0, 5, true)));
    
    $all_korean_words = [];
    
    // [1] 전체 영어 단어가 맵에 있는지 확인
    if (isset($reverse_map[$query_lower])) {
        $all_korean_words = array_merge($all_korean_words, $reverse_map[$query_lower]);
        // error_log("✅ 전체 매칭 성공: '{$query_lower}' → " . json_encode($reverse_map[$query_lower]));
    }
    
    // [2] 공백으로 분리된 여러 단어 처리
    if (strpos($query, ' ') !== false) {
        $words = array_filter(explode(' ', $query));
        
        foreach ($words as $word) {
            $w_lower = strtolower($word);
            if (isset($reverse_map[$w_lower])) {
                $all_korean_words = array_merge($all_korean_words, $reverse_map[$w_lower]);
                // error_log("✅ 단어 매칭 성공: '{$w_lower}' → " . json_encode($reverse_map[$w_lower]));
            }
        }
    }
    
    // 중복 제거
    $all_korean_words = array_unique($all_korean_words);
    
    if (!empty($all_korean_words)) {
        // error_log("✅ 역방향 번역 완료: '{$query}' → " . json_encode($all_korean_words));
        return $all_korean_words;
    }
    
    // error_log("❌ 역방향 번역 실패: '{$query}'에 대한 매칭 없음");
    return [];
}

/**
 * 정방향 번역 (한글 → 영어)
 * @return array 매칭된 모든 영어 단어들의 배열
 */
function translateSearch($query) {
    $manual_translations = loadSearchTranslations();
    
    $query = trim($query);
    if (empty($query)) return [];
    
    $query_lower = mb_strtolower($query, 'UTF-8');
    
    $all_english_words = [];
    
    // [1] 전체 문구 매칭
    if (isset($manual_translations[$query_lower])) {
        $all_english_words = array_merge($all_english_words, $manual_translations[$query_lower]);
        // error_log("✅ 정방향 전체 매칭: '{$query_lower}' → " . json_encode($manual_translations[$query_lower]));
    }
    
    // [2] 공백 분리 단어별 번역
    if (strpos($query, ' ') !== false) {
        $words = array_filter(explode(' ', $query));
        
        foreach ($words as $word) {
            $w_lower = mb_strtolower($word, 'UTF-8');
            
            if (isset($manual_translations[$w_lower])) {
                $all_english_words = array_merge($all_english_words, $manual_translations[$w_lower]);
                // error_log("✅ 정방향 단어 매칭: '{$w_lower}' → " . json_encode($manual_translations[$w_lower]));
            } else {
                if (preg_match('/^[a-zA-Z]+$/', $word)) {
                    $all_english_words[] = strtolower($word);
                }
            }
        }
    }
    
    // [3] 붙여쓰기 분리
    if (empty($all_english_words)) {
        $sorted_translations = $manual_translations;
        uksort($sorted_translations, function($a, $b) {
            return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
        });
        
        $remaining = $query_lower;
        $found_parts = [];
        
        while (mb_strlen($remaining, 'UTF-8') > 0) {
            $matched = false;
            
            foreach ($sorted_translations as $korean => $english_array) {
                $korean_len = mb_strlen($korean, 'UTF-8');
                
                if (mb_substr($remaining, 0, $korean_len, 'UTF-8') === $korean) {
                    $found_parts = array_merge($found_parts, $english_array);
                    $remaining = mb_substr($remaining, $korean_len, null, 'UTF-8');
                    $matched = true;
                    break;
                }
            }
            
            if (!$matched) {
                $remaining = mb_substr($remaining, 1, null, 'UTF-8');
            }
        }
        
        if (!empty($found_parts)) {
            $all_english_words = array_merge($all_english_words, $found_parts);
        }
    }
    
    // 중복 제거
    $all_english_words = array_unique($all_english_words);
    
    if (!empty($all_english_words)) {
        // error_log("✅ 정방향 번역 완료: '{$query}' → " . json_encode($all_english_words));
        return $all_english_words;
    }
    
    // error_log("❌ 정방향 번역 실패: '{$query}'에 대한 매칭 없음");
    return [];
}
