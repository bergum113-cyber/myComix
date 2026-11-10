<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/i18n.php";
// ★ (2026-11-02 10:05) 입력은 문자열만(배열이면 trim 에서 500)
foreach ([&$_POST, &$_GET] as &$__in) { foreach ($__in as $__k => $__v) { if (is_array($__v)) $__in[$__k] = ''; } }
unset($__in, $__k, $__v);

// 관리자만 접근 가능
if (!isset($_SESSION['user_id']) || $_SESSION['user_group'] != "admin") {
    header("Location: index.php");
    exit;
}

// ✅ 브랜딩 설정 로드 (function.php의 공통 함수 사용)
$_branding = load_branding();

$translations_file = __DIR__ . '/src/search_translations.json';
require_once __DIR__ . '/search_translate.php'; // ★ (2026-11-06 08:10) 검색 미리보기 — 실제 검색과 같은 함수
// ★ (2026-11-06 fix34) 주소창으로 바로 열면(질문 없는 첫 화면) 관리자 화면의 '한영 검색어 연결' 탭으로 — 메뉴를 오갈 때 새로고침되지 않게
//   브라우저가 '맨 위 문서'라고 알려 줄 때만(안쪽 칸·옛 브라우저·?embed=1·검색/쪽 넘김 등은 그대로)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && empty($_GET) && ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'document') { header('Location: admin.php#translations'); exit; }

// ★ (2026-11-06 08:10) 한글은 조합형(NFC)으로(맥에서 입력한 자모 분리형도 검색어와 맞게) · 같은 짝 판단 키(영어는 대소문자 무시)
function at_nfc($s) {
    $s = trim((string)$s);
    if ($s !== '' && class_exists('Normalizer')) { $n = Normalizer::normalize($s, Normalizer::FORM_C); if (is_string($n)) $s = $n; }
    return preg_replace('/\s+/u', ' ', $s);
}
function at_pair_key($k, $e) { return mb_strtolower(at_nfc($k), 'UTF-8') . "\n" . strtolower(at_nfc($e)); }
function at_has_pair(array $list, $k, $e, $skipK = null, $skipE = null) {
    $want = at_pair_key($k, $e); $skipped = false;
    foreach ($list as $it) {
        if (!is_array($it) || !isset($it['korean'], $it['english'])) continue;
        if (!$skipped && $skipK !== null && $it['korean'] === $skipK && $it['english'] === $skipE) { $skipped = true; continue; } // 고치는 그 짝은 빼고
        if (at_pair_key($it['korean'], $it['english']) === $want) return true;
    }
    return false;
}

// ★ (2026-11-06 08:10) 검색 미리보기 — 이 검색어로 찾으면 함께 찾는 말(index.php 와 같은 판단: 한글이 있으면 한→영, 영어·띄어쓰기만이면 영→한)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => __('at_csrf_fail')]); exit; }
    $pq = mb_substr(at_nfc($_POST['q'] ?? ''), 0, 100, 'UTF-8');
    $dir = 'none'; $words = [];
    if ($pq !== '') {
        if (preg_match('/[ㄱ-ㅎㅏ-ㅣ가-힣]/u', $pq)) { $dir = 'ko'; $words = translateSearch($pq); }
        elseif (preg_match('/^[a-zA-Z\s]+$/', $pq)) { $dir = 'en'; $words = reverseTranslateSearch($pq); }
        else $dir = 'other';
    }
    echo json_encode(['success' => true, 'dir' => $dir, 'words' => array_values(array_unique(array_map('strval', (array)$words)))], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ function.php의 공통 함수 사용 (파일 잠금 적용)
// - load_json_with_lock(): 파일 읽기 (공유 잠금)
// - save_json_with_lock(): 파일 쓰기 (배타 잠금)

// AJAX 개별 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => __('at_csrf_fail')]);
        exit;
    }
    $korean = $_POST['korean'] ?? '';
    $english = $_POST['english'] ?? '';
    
    $translations = load_json_with_lock($translations_file);
    $new_translations = [];
    $deleted = false;
    
    foreach ($translations as $item) {
        if ($item['korean'] === $korean && $item['english'] === $english && !$deleted) {
            $deleted = true;
            continue;
        }
        $new_translations[] = $item;
    }
    
    if ($deleted) {
        save_json_with_lock($translations_file, $new_translations);
        echo json_encode(['success' => true, 'message' => __('at_deleted')]);
    } else {
        echo json_encode(['success' => false, 'message' => __('at_item_not_found')]);
    }
    exit;
}

// AJAX 일괄 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_multiple') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => __('at_csrf_fail')]);
        exit;
    }
    $items = json_decode(is_string($_POST['items'] ?? null) ? $_POST['items'] : '[]', true);
    
    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => false, 'message' => __('at_no_items_to_delete')]);
        exit;
    }
    
    $translations = load_json_with_lock($translations_file);
    $new_translations = [];
    $deleted_count = 0;
    
    foreach ($translations as $item) {
        $should_delete = false;
        foreach ($items as $delete_item) {
            if (!is_array($delete_item) || !isset($delete_item['korean'], $delete_item['english']) || !is_array($item)) continue; // ★ (2026-10-30) 형식이 틀린 항목은 건너뜀(TypeError 방지)
            if (($item['korean'] ?? null) === $delete_item['korean'] && ($item['english'] ?? null) === $delete_item['english']) {
                $should_delete = true;
                $deleted_count++;
                break;
            }
        }
        if (!$should_delete) {
            $new_translations[] = $item;
        }
    }
    
    save_json_with_lock($translations_file, $new_translations);
    echo json_encode(['success' => true, 'message' => __("at_items_deleted", $deleted_count)]);
    exit;
}

// AJAX 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => __('at_csrf_fail')]);
        exit;
    }
    $korean = at_nfc($_POST['korean'] ?? ''); // ★ (2026-11-06 08:10) 조합형·띄어쓰기 정리
    $english = at_nfc($_POST['english'] ?? '');
    
    if ($korean !== '' && $english !== '') {
        $translations = load_json_with_lock($translations_file);
        if (at_has_pair($translations, $korean, $english)) { echo json_encode(['success' => false, 'message' => __('at_dup_exists')]); exit; } // ★ 같은 짝은 한 번만(예전: 똑같은 짝이 여러 번 쌓였음)
        $translations[] = ['korean' => $korean, 'english' => $english];
        save_json_with_lock($translations_file, $translations);
        echo json_encode(['success' => true, 'message' => __('at_added')]);
    } else {
        echo json_encode(['success' => false, 'message' => __('at_both_required')]);
    }
    exit;
}

// AJAX 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => __('at_csrf_fail')]);
        exit;
    }
    $old_korean = $_POST['old_korean'] ?? '';
    $old_english = $_POST['old_english'] ?? '';
    $new_korean = at_nfc($_POST['new_korean'] ?? ''); // ★ (2026-11-06 08:10) 조합형·띄어쓰기 정리
    $new_english = at_nfc($_POST['new_english'] ?? '');
    
    if ($new_korean !== '' && $new_english !== '') {
        $translations = load_json_with_lock($translations_file);
        if (at_has_pair($translations, $new_korean, $new_english, $old_korean, $old_english)) { echo json_encode(['success' => false, 'message' => __('at_dup_exists')]); exit; } // ★ 다른 짝과 똑같아지면 막음
        $updated = false;
        foreach ($translations as &$item) {
            if ($item['korean'] === $old_korean && $item['english'] === $old_english && !$updated) {
                $item['korean'] = $new_korean;
                $item['english'] = $new_english;
                $updated = true;
            }
        }
        if ($updated) {
            save_json_with_lock($translations_file, $translations);
            echo json_encode(['success' => true, 'message' => __('at_edited')]);
        } else {
            echo json_encode(['success' => false, 'message' => __('at_item_not_found')]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => __('at_both_required')]);
    }
    exit;
}

// CSV 내보내기
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="translations_' . date('Ymd') . '.csv"');
    $translations = load_json_with_lock($translations_file);
    echo "\xEF\xBB\xBF";
    echo __("at_csv_header") . "\n";
    foreach ($translations as $item) {
        echo '"' . str_replace('"', '""', $item['korean']) . '","' . str_replace('"', '""', $item['english']) . '"' . "\n";
    }
    exit;
}

// CSV 가져오기
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = "❌ " . __("at_csrf_fail");
    } else {
        $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($file['tmp_name']);
        $content = str_replace("\xEF\xBB\xBF", '', $content);
        // ★ (2026-11-02 10:05) 한국어 Windows Excel 이 저장한 CSV(CP949)면 UTF-8 로 바꿔 읽음 — 전에는 한글이 모두 깨진 채 사전이 통째로 바뀌었음
        if (!mb_check_encoding($content, 'UTF-8')) {
            $__conv = @mb_convert_encoding($content, 'UTF-8', 'UHC'); // UHC = CP949(mbstring 이름)
            if (is_string($__conv) && mb_check_encoding($__conv, 'UTF-8')) $content = $__conv;
        }
        $lines = explode("\n", $content);
        $new_translations = [];
        foreach ($lines as $i => $line) {
            if ($i === 0 || trim($line) === '') continue;
            $parts = str_getcsv($line, ',', '"', ''); // ★ (2026-11-02 14:27) 역슬래시 escape 끔 — 값이 \ 로 끝나면 두 칸이 합쳐져 그 줄이 사라지던 것(내보내기는 "" 만 씀)
            if (count($parts) >= 2) {
                $korean = at_nfc($parts[0]);
                $english = at_nfc($parts[1]);
                if ($korean !== '' && $english !== '') {
                    $new_translations[] = ['korean' => $korean, 'english' => $english];
                }
            }
        }
        // ★ (2026-11-06 08:10) 가져오기 방식 — 합치기(기본: 지금 짝은 그대로 두고 새 짝만 더함) / 모두 바꾸기(예전 방식) · 같은 짝은 한 번만
        $csv_mode = (($_POST['csv_mode'] ?? '') === 'replace') ? 'replace' : 'merge';
        $__seen = []; $__base = ($csv_mode === 'merge') ? load_json_with_lock($translations_file) : [];
        if (!is_array($__base)) $__base = [];
        foreach ($__base as $__it) if (is_array($__it) && isset($__it['korean'], $__it['english'])) $__seen[at_pair_key($__it['korean'], $__it['english'])] = true;
        $__read = count($new_translations); $__add = [];
        foreach ($new_translations as $__it) { $__k = at_pair_key($__it['korean'], $__it['english']); if (isset($__seen[$__k])) continue; $__seen[$__k] = true; $__add[] = $__it; }
        $__skip = $__read - count($__add);
        $new_translations = ($csv_mode === 'merge') ? array_merge($__base, $__add) : $__add;
        if ($__read > 0) {
            save_json_with_lock($translations_file, $new_translations);
            $message = "✅ " . __($csv_mode === 'merge' ? 'at_csv_merged' : 'at_csv_imported', count($__add)) . ($__skip > 0 ? ' ' . __('at_csv_skipped', $__skip) : '');
        } else {
            $message = "❌ " . __("at_csv_empty"); // ★ (2026-11-02 14:27) 읽은 줄이 0개면 아무 알림도 없던 것(사전은 그대로 둠) — ★ (2026-11-09 10:41) 전용 문구로: at_csv_imported 가 '모두 바꿨습니다'로 바뀌어 사전이 지워진 것처럼 보였음
        }
    } else {
        $message = "❌ " . __("at_js_error"); // ★ (2026-11-02 14:27) 업로드 오류도 알림
    }
    }
}

// 번역 사전 로드
$translations = load_json_with_lock($translations_file);

// 검색 필터
// ✅ sanitize_input()으로 입력 검증 (XSS 방지 강화)
// ★ (2026-10-30) 원문으로 받음(출력할 때 이스케이프) — 미리 HTML 이스케이프하면 & < " 가 든 검색이 안 맞고 페이지를 넘길 때마다 &amp;amp; 로 불어남, 배열이면 500
$search_query = (isset($_GET['search']) && is_string($_GET['search'])) ? trim($_GET['search']) : '';
$filtered_translations = $translations;

if (($search_query !== '')) {
    $search_lower = mb_strtolower($search_query, 'UTF-8');
    $filtered_translations = array_filter($translations, function($item) use ($search_lower) {
        $korean_lower = mb_strtolower($item['korean'], 'UTF-8');
        $english_lower = mb_strtolower($item['english'], 'UTF-8');
        return mb_strpos($korean_lower, $search_lower, 0, 'UTF-8') !== false 
            || mb_strpos($english_lower, $search_lower, 0, 'UTF-8') !== false;
    });
}

// ★ (2026-11-06 08:10) 정렬 고르기 — ko(한글 가나다, 기본) · en(영어 ABC) · new(최근 추가 순) · multi(짝이 여러 개인 말 먼저 = 예전 정렬)
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && in_array($_GET['sort'], ['ko', 'en', 'new', 'multi'], true)) ? $_GET['sort'] : 'ko';
if (!empty($filtered_translations) && $sort !== 'multi') {
    $filtered_translations = array_values($filtered_translations);
    if ($sort === 'new') $filtered_translations = array_reverse($filtered_translations);
    elseif ($sort === 'en') usort($filtered_translations, function ($a, $b) { return strnatcasecmp($a['english'], $b['english']) ?: strcmp($a['korean'], $b['korean']); });
    else usort($filtered_translations, function ($a, $b) { return strcmp($a['korean'], $b['korean']) ?: strnatcasecmp($a['english'], $b['english']); });
}
// 정렬: 중복 많은 순 → 같은 단어끼리 그룹핑 → 가나다순 (★ 2026-11-06 '짝 여러 개 먼저' 를 고를 때만)
if (!empty($filtered_translations) && $sort === 'multi') {
    // 대소문자 구분 없이 중복 카운트
    $korean_counts = array_count_values(array_column($filtered_translations, 'korean'));
    $english_lower = array_map('strtolower', array_column($filtered_translations, 'english'));
    $english_counts_lower = array_count_values($english_lower);
    
    // 원본 영어 → 소문자 카운트 매핑
    $english_counts = [];
    foreach ($filtered_translations as $item) {
        $english_counts[$item['english']] = $english_counts_lower[strtolower($item['english'])];
    }
    
    usort($filtered_translations, function($a, $b) use ($korean_counts, $english_counts) {
        $a_kor_dup = $korean_counts[$a['korean']];
        $b_kor_dup = $korean_counts[$b['korean']];
        $a_eng_dup = $english_counts[$a['english']];
        $b_eng_dup = $english_counts[$b['english']];
        
        // 각 항목의 최대 중복 개수
        $a_max_dup = max($a_kor_dup, $a_eng_dup);
        $b_max_dup = max($b_kor_dup, $b_eng_dup);
        
        // 1. 중복 개수가 많은 것부터
        if ($a_max_dup !== $b_max_dup) return $b_max_dup - $a_max_dup;
        
        // 2. 같은 영어면 그룹으로 묶기 (대소문자 무시)
        if (strcasecmp($a['english'], $b['english']) === 0) {
            return strcmp($a['korean'], $b['korean']);
        }
        
        // 3. 같은 한글이면 그룹으로 묶기
        if ($a['korean'] === $b['korean']) {
            return strcasecmp($a['english'], $b['english']);
        }
        
        // 4. 영어 중복이 있으면 영어로 정렬 (대소문자 무시)
        if ($a_eng_dup > 1 || $b_eng_dup > 1) {
            return strcasecmp($a['english'], $b['english']);
        }
        
        // 5. 한글 중복이 있으면 한글로 정렬
        if ($a_kor_dup > 1 || $b_kor_dup > 1) {
            return strcmp($a['korean'], $b['korean']);
        }
        
        // 6. 중복 없으면 한글 가나다순
        return strcmp($a['korean'], $b['korean']);
    });
}

// 페이징 (30개씩)
$per_page = 50; // ★ (2026-11-06 08:10) 30 → 50
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_count = count($filtered_translations);
$total_pages = max(1, (int)ceil($total_count / $per_page)); // ★ (2026-11-02 11:40) 정수로(범위 밖 쪽에서 현재 쪽 표시가 사라졌음) · 검색어 '0' 도 검색
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$page_translations = array_slice($filtered_translations, $offset, $per_page);

// 통계
$stats = [
    'total' => count($translations),
    'unique_korean' => count(array_unique(array_column($translations, 'korean'))),
    'unique_english' => count(array_unique(array_column($translations, 'english'))),
];
?>
<!DOCTYPE html>
<html lang="<?php echo h(function_exists('get_html_lang') ? get_html_lang() : 'ko'); ?>">
<head>
<title><?php echo __h("at_page_title"); ?> - <?php echo htmlspecialchars($_branding['admin_title'] ?? __('at_admin_title_default')); ?></title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- ✅ 핵심 레이아웃 + 페이지 전환 -->
<style>
html{opacity:0;transition:opacity .15s ease-in}
html.ready{opacity:1}
html.leaving{opacity:0;transition:opacity .1s ease-out}
</style>
<link rel="stylesheet" href="./css/bootstrap.min.css">
<link rel="stylesheet" href="./css/css2.css?family=Nanum+Gothic:wght@400;700&display=swap">
<?php /* ★ (2026-11-06 08:10) 화면 새로 — 이름 '한영 검색어 연결' · 설명과 예 · 검색 미리보기 · 정렬 고르기 · 한 줄 '한글 ⇄ 영어' 목록 · CSV 합치기 */ ?>
<style>
body{padding:15px;font-family:'Nanum Gothic',sans-serif;background:#f3f4f6;color:#1f2328}
.at-wrap{max-width:1040px;margin:0 auto}
.at-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.at-head h1{font-size:1.45rem;margin:0;font-weight:700;display:flex;align-items:center;gap:8px;word-break:keep-all}
.at-head .at-back{margin-left:auto}
.at-card{background:#fff;border:1px solid #e3e6ea;border-radius:12px;padding:16px 18px;margin-bottom:14px}
.at-card h2{font-size:1.02rem;font-weight:700;margin:0 0 10px;display:flex;align-items:center;gap:6px}
.at-intro p{margin:0 0 8px;color:#454b54;line-height:1.65}
.at-ex{display:flex;flex-wrap:wrap;gap:8px;margin:6px 0 4px}
.at-ex span{display:inline-flex;align-items:center;gap:6px;background:#f1f6ff;border:1px solid #d6e4ff;color:#1d4ed8;border-radius:999px;padding:3px 12px;font-size:.88rem}
.at-note{font-size:.82rem;color:#6b7280;margin:6px 0 0}
.at-stats{display:flex;flex-wrap:wrap;gap:18px;font-size:.9rem;color:#454b54;margin-top:10px;padding-top:10px;border-top:1px solid #eef0f3}
.at-stats b{color:#1f2328;font-size:1.05rem;margin-right:3px}
.at-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.at-grid .at-card{margin-bottom:0}
.at-card .form-control{width:100%;max-width:none}
.at-tool select.form-control{width:auto}
.at-pair{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);gap:8px;align-items:center}
.at-arrow{color:#9aa1ab;font-size:1.1rem;text-align:center}
.at-label{font-size:.78rem;color:#6b7280;margin:0 0 3px}
.at-hint{font-size:.8rem;color:#6b7280;margin-top:8px}
.at-pv-out{margin-top:10px;font-size:.9rem;min-height:1.5em;line-height:1.7}
.at-pv-out .at-w{display:inline-block;background:#ecfdf3;border:1px solid #b7ebc6;color:#166534;border-radius:999px;padding:1px 10px;margin:2px 3px 2px 0}
.at-tool{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px}
.at-tool form{display:flex;gap:8px;flex:1 1 320px;min-width:0}
.at-tool input[type=search]{flex:1;min-width:0}
.at-count{font-size:.85rem;color:#6b7280;margin:0 0 8px}
.at-list{border-top:1px solid #eef0f3}
.translation-row{display:grid;grid-template-columns:28px minmax(0,1fr) 22px minmax(0,1fr) auto;gap:8px;align-items:center;padding:7px 4px;border-bottom:1px solid #eef0f3}
.translation-row:hover{background:#fafbfc}
.translation-row.selected{background:#eef4ff}
.translation-row.editing{background:#fffbeb}
.translation-row .form-control{border-color:transparent;background:transparent;padding:4px 8px;height:auto;font-size:.95rem}
.translation-row .form-control[readonly]{cursor:default;box-shadow:none}
.translation-row.editing .form-control{border-color:#f0c36d;background:#fff}
.translation-row .at-multi{display:inline-block;font-size:.72rem;color:#92400e;background:#fef3c7;border-radius:999px;padding:0 7px;margin-left:8px;white-space:nowrap}
.translation-row .at-cell{display:flex;align-items:center;min-width:0}
.translation-row .at-cell .form-control{flex:1;min-width:0}
.translation-row .at-full{flex:1;min-width:0;padding:4px 8px;font-size:.95rem;line-height:1.4;overflow-wrap:anywhere;word-break:keep-all;white-space:normal}
.translation-row:not(.editing) .at-cell .form-control{display:none}
.translation-row.editing .at-full{display:none}
.translation-row .at-btns{display:flex;gap:4px}
.translation-row .btn{padding:3px 9px;font-size:.82rem}
.item-checkbox{width:18px;height:18px;cursor:pointer}
.bulk-actions{position:sticky;top:8px;z-index:5;background:#1f2937;color:#fff;padding:10px 14px;border-radius:10px;margin-bottom:10px;display:none;align-items:center;gap:10px;flex-wrap:wrap}
.bulk-actions.show{display:flex}
.at-empty{padding:24px;text-align:center;color:#6b7280}
#csvImport{display:none}
.at-csv-mode label{display:flex;gap:8px;align-items:flex-start;font-size:.9rem;margin:4px 0;cursor:pointer}
.toast-message{position:fixed;top:20px;right:20px;padding:12px 18px;background:#16a34a;color:#fff;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.2);z-index:9999;animation:slideIn .3s}
.toast-message.error{background:#dc2626}
@keyframes slideIn{from{transform:translateX(400px);opacity:0}to{transform:translateX(0);opacity:1}}
@media(max-width:768px){
  body{padding:10px}
  .at-grid{grid-template-columns:1fr}
  .at-card{padding:14px}
  .at-head h1{font-size:1.2rem}
  .translation-row{grid-template-columns:26px minmax(0,1fr) auto;grid-template-areas:"c k b" "c e b";row-gap:2px}
  .translation-row > .at-cbx{grid-area:c} .translation-row > .at-k{grid-area:k} .translation-row > .at-e{grid-area:e} .translation-row > .at-btns{grid-area:b;flex-direction:column}
  .translation-row > .at-arrow{display:none}
  .at-pair{grid-template-columns:1fr}
  .at-pair .at-arrow{display:none}
}
</style>
<script>document.documentElement.classList.add('ready');</script>
<script>if (window.top !== window.self) document.documentElement.classList.add('mc-embed');</script><?php // ★ fix34 관리자 화면 탭 안에 불려 왔을 때(위 막대·메뉴는 바깥 것을 씀) ?>
<?php require_once __DIR__ . '/admin_shell.php'; mc_admin_shell_css(); /* ★ (2026-11-06 fix27) 관리자 공통 틀 — admin.php 와 같은 위 막대·메뉴 */ ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-11-07 fix36) 아이콘 변환을 본문보다 먼저 시작 — 새로고침 때 예전 이모지가 잠깐 보였다가 바뀌던 것(펜닐 제보) / 페이지 끝의 같은 스크립트는 다시 실행되지 않음 */ ?>
</head>
<body class="mc-adm at-page">
<?php echo function_exists('mc_pager_group_js') ? mc_pager_group_js() : ''; /* ★ (2026-11-10 19:19 fix105) 쪽 번호 단추 묶음 */ ?>
<?php mc_admin_topbar(function_exists('load_branding') ? load_branding() : []); mc_admin_menu('links', 'translations'); mc_admin_shell_js(); ?>
<!-- CSRF 토큰 -->
<input type="hidden" id="csrf_token" value="<?php echo h(generate_csrf_token()); ?>">

<div class="at-wrap">
<div class="at-head">
  <h1><?php echo __("at_heading"); ?></h1>
  <a href="admin.php" class="btn btn-outline-secondary btn-sm at-back"><?php echo __("at_btn_back_admin"); ?></a>
</div>

<?php if (isset($message)): ?>
<div class="alert <?php echo (strpos($message, '❌') === 0) ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show">
<?php echo h($message); ?>
<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<div class="at-card at-intro">
  <p><?php echo __h('at_intro'); ?></p>
  <div class="at-ex"><span><?php echo __h('at_ex1'); ?></span><span><?php echo __h('at_ex2'); ?></span><span><?php echo __h('at_ex3'); ?></span></div>
  <p class="at-note"><?php echo __h('at_intro_note'); ?></p>
  <div class="at-stats">
    <span><b id="totalCount"><?php echo number_format($stats['total']); ?></b><?php echo __h('at_stat_pairs'); ?></span>
    <span><b><?php echo number_format($stats['unique_korean']); ?></b><?php echo __h('at_stat_ko_words'); ?></span>
    <span><b><?php echo number_format($stats['unique_english']); ?></b><?php echo __h('at_stat_en_words'); ?></span>
  </div>
</div>

<div class="at-grid">
  <div class="at-card">
    <h2><?php echo __("at_add_new"); ?></h2>
    <div class="at-pair">
      <div><div class="at-label"><?php echo __h('at_col_ko'); ?></div><input type="text" id="newKorean" class="form-control" placeholder="<?php echo __h('at_placeholder_ko'); ?>" maxlength="200" autocomplete="off"></div>
      <div class="at-arrow" aria-hidden="true">⇄</div>
      <div><div class="at-label"><?php echo __h('at_col_en'); ?></div><input type="text" id="newEnglish" class="form-control" placeholder="<?php echo __h('at_placeholder_en'); ?>" maxlength="200" autocomplete="off"></div>
    </div>
    <div class="d-flex align-items-center mt-2" style="gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-success" onclick="addTranslation()"><?php echo __h("at_btn_add"); ?></button>
      <span class="at-hint" style="margin:0"><?php echo __h('at_add_hint'); ?></span>
    </div>
  </div>
  <div class="at-card">
    <h2><?php echo __h('at_pv_title'); ?></h2>
    <div class="at-label"><?php echo __h('at_pv_label'); ?></div>
    <input type="search" id="pvQ" class="form-control" placeholder="<?php echo __h('at_pv_ph'); ?>" maxlength="100" autocomplete="off">
    <div class="at-pv-out" id="pvOut" aria-live="polite"></div>
  </div>
</div>

<div class="at-card" style="margin-top:14px">
  <div class="at-tool">
    <form method="get" role="search">
      <input type="search" name="search" class="form-control" placeholder="<?php echo __h('at_search_placeholder'); ?>" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES); ?>" aria-label="<?php echo __h('at_search_placeholder'); ?>">
      <input type="hidden" name="sort" value="<?php echo h($sort); ?>">
      <button type="submit" class="btn btn-primary" style="white-space:nowrap"><?php echo __("at_btn_search"); ?></button>
      <?php if ($search_query !== ''): ?><a href="?sort=<?php echo h($sort); ?>" class="btn btn-outline-secondary" style="white-space:nowrap"><?php echo __("at_btn_reset"); ?></a><?php endif; ?>
    </form>
    <select class="form-control" aria-label="<?php echo __h('at_sort_label'); ?>" onchange="var u=new URL(location.href);u.searchParams.set('sort',this.value);u.searchParams.delete('page');location.href=u.toString();">
      <?php foreach (['ko' => 'at_sort_ko', 'en' => 'at_sort_en', 'new' => 'at_sort_new', 'multi' => 'at_sort_multi'] as $__sv => $__sl): ?>
      <option value="<?php echo $__sv; ?>"<?php echo $sort === $__sv ? ' selected' : ''; ?>><?php echo __h($__sl); ?></option>
      <?php endforeach; ?>
    </select>
    <a href="?export=csv" class="btn btn-outline-success" download><?php echo __("at_btn_csv_export"); ?></a>
    <button type="button" class="btn btn-outline-info" onclick="var c=document.getElementById('csvImport');c.style.display=c.style.display==='block'?'none':'block'"><?php echo __('at_btn_csv_import'); ?></button>
  </div>

  <div id="csvImport" class="alert alert-light border">
    <h5 style="font-size:1rem;font-weight:700"><?php echo __("at_csv_import_title"); ?></h5>
    <p class="text-muted mb-2" style="font-size:.88rem"><?php echo __("at_csv_format_desc"); ?></p>
    <form method="post" enctype="multipart/form-data" onsubmit="var r=this.querySelector('input[name=csv_mode]:checked');return !(r&&r.value==='replace')||confirm(<?php echo h(js(__('at_csv_replace_confirm'))); ?>);">
      <?php echo csrf_field(); ?>
      <div class="at-csv-mode mb-2">
        <label><input type="radio" name="csv_mode" value="merge" checked> <span><?php echo __h('at_csv_mode_merge'); ?></span></label>
        <label><input type="radio" name="csv_mode" value="replace"> <span><?php echo __h('at_csv_mode_replace'); ?></span></label>
      </div>
      <div class="d-flex flex-wrap align-items-center" style="gap:8px">
        <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" style="width:auto;max-width:100%" required>
        <button type="submit" class="btn btn-primary"><?php echo __h("at_btn_upload"); ?></button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('csvImport').style.display='none'"><?php echo __h("at_btn_cancel"); ?></button>
      </div>
    </form>
  </div>

  <div class="bulk-actions" id="bulkActions">
    <strong><span id="selectedCount">0</span><?php echo __h("at_selected"); ?></strong>
    <button type="button" class="btn btn-danger btn-sm" onclick="deleteSelected()"><?php echo __("at_btn_delete_selected"); ?></button>
    <button type="button" class="btn btn-light btn-sm" onclick="clearSelection()"><?php echo __("at_btn_clear_selection"); ?></button>
  </div>

  <div class="at-count d-flex align-items-center" style="gap:10px;flex-wrap:wrap">
    <label class="mb-0 d-flex align-items-center" style="gap:6px;cursor:pointer"><input type="checkbox" class="item-checkbox" id="selAll" onchange="toggleSelectAll(this.checked)" aria-label="<?php echo __h('at_js_select_all'); ?>"> <span class="select-all-label"><?php echo __h('at_select_page'); ?></span></label>
    <span><?php
      if ($search_query !== '') echo __('at_search_results', $total_count, $stats['total']) . ($total_count ? ' · ' : '');
      if ($total_count) echo ($offset + 1) . '–' . min($offset + $per_page, $total_count) . ' / ' . number_format($total_count);
    ?></span>
  </div>

<div id="translationList" class="at-list">
<?php
$korean_counts = []; $english_counts = [];
foreach ($translations as $__it) { if (!is_array($__it)) continue; $__k = (string)($__it['korean'] ?? ''); $__e = strtolower((string)($__it['english'] ?? '')); $korean_counts[$__k] = ($korean_counts[$__k] ?? 0) + 1; $english_counts[$__e] = ($english_counts[$__e] ?? 0) + 1; }
if (!$page_translations): ?>
<div class="at-empty"><?php echo __h($search_query !== '' ? 'at_empty_search' : 'at_empty'); ?></div>
<?php endif;
foreach ($page_translations as $index => $item):
    $kc = $korean_counts[$item['korean']] ?? 1; $ec = $english_counts[strtolower($item['english'])] ?? 1;
    $unique_id = 'row_' . $index;
?>
<div class="translation-row" id="<?php echo $unique_id; ?>" data-korean="<?php echo htmlspecialchars($item['korean'], ENT_QUOTES); ?>" data-english="<?php echo htmlspecialchars($item['english'], ENT_QUOTES); ?>">
  <div class="at-cbx"><input type="checkbox" class="item-checkbox row-cb" data-korean="<?php echo htmlspecialchars($item['korean'], ENT_QUOTES); ?>" data-english="<?php echo htmlspecialchars($item['english'], ENT_QUOTES); ?>" onchange="updateBulkActions()" aria-label="<?php echo h($item['korean'] . ' ⇄ ' . $item['english']); ?>"></div>
  <div class="at-cell at-k"><input type="text" class="form-control korean-input" value="<?php echo htmlspecialchars($item['korean'], ENT_QUOTES); ?>" readonly aria-label="<?php echo __h('at_col_ko'); ?>"><span class="at-full"><?php echo htmlspecialchars($item['korean'], ENT_QUOTES); ?></span><?php if ($kc > 1): ?><span class="at-multi" title="<?php echo __h('at_multi_tip'); ?>"><?php echo __h('at_multi_n', $kc); ?></span><?php endif; ?></div>
  <div class="at-arrow" aria-hidden="true">⇄</div>
  <div class="at-cell at-e"><input type="text" class="form-control english-input" value="<?php echo htmlspecialchars($item['english'], ENT_QUOTES); ?>" readonly aria-label="<?php echo __h('at_col_en'); ?>"><span class="at-full"><?php echo htmlspecialchars($item['english'], ENT_QUOTES); ?></span><?php if ($ec > 1): ?><span class="at-multi" title="<?php echo __h('at_multi_tip'); ?>"><?php echo __h('at_multi_n', $ec); ?></span><?php endif; ?></div>
  <div class="at-btns">
    <button type="button" class="btn btn-outline-secondary btn-sm edit-btn" onclick="editRow('<?php echo $unique_id; ?>')" title="<?php echo __h('at_edit'); ?>">✏️</button>
    <button type="button" class="btn btn-success btn-sm save-btn" style="display:none" onclick="saveRow('<?php echo $unique_id; ?>')" title="<?php echo __h('at_save'); ?>">💾</button>
    <button type="button" class="btn btn-secondary btn-sm cancel-btn" style="display:none" onclick="cancelEdit('<?php echo $unique_id; ?>')" title="<?php echo __h('at_btn_cancel'); ?>">✕</button>
    <button type="button" class="btn btn-outline-danger btn-sm delete-btn" onclick="deleteTranslation('<?php echo $unique_id; ?>')" title="<?php echo __h('at_delete'); ?>">🗑️</button>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($total_pages > 1):
  $__qs = function ($pg) use ($search_query, $sort) { return '?page=' . (int)$pg . ($search_query !== '' ? '&search=' . urlencode($search_query) : '') . '&sort=' . urlencode($sort); };
?>
<?php if (function_exists('mc_pager_bs')): [$__pga, $__pgl] = mc_pager_bs($page, $total_pages, $__qs); /* ★ (2026-11-10 19:19, 펜닐 요청 — fix105) 폴더 목록과 같은 묶음 방식 */ ?>
<nav><ul class="pagination" style="margin-top:16px;justify-content:center;flex-wrap:wrap" <?php echo $__pga; ?>><?php echo $__pgl; ?></ul></nav>
<?php else: ?>
<nav><ul class="pagination" style="margin-top:16px;justify-content:center;flex-wrap:wrap">
<?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="<?php echo h($__qs($page - 1)); ?>"><?php echo __("at_prev"); ?></a></li><?php endif; ?>
<?php $start_page = max(1, $page - 2); $end_page = min($total_pages, $page + 2);
if ($start_page > 1): ?><li class="page-item"><a class="page-link" href="<?php echo h($__qs(1)); ?>">1</a></li><?php if ($start_page > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; endif; ?>
<?php for ($i = $start_page; $i <= $end_page; $i++): ?><li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo h($__qs($i)); ?>"><?php echo $i; ?></a></li><?php endfor; ?>
<?php if ($end_page < $total_pages): if ($end_page < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?php echo h($__qs($total_pages)); ?>"><?php echo $total_pages; ?></a></li><?php endif; ?>
<?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link" href="<?php echo h($__qs($page + 1)); ?>"><?php echo __("at_next"); ?></a></li><?php endif; ?>
</ul></nav>
<?php endif; ?>
<?php endif; ?>
</div>
</div>

<script src="./js/jquery-3.5.1.min.js"></script>
<script src="./js/popper.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<script>
var T = <?php echo json_encode([
  'err' => __('at_js_error'), 'both' => __('at_both_required'), 'sel' => __('at_js_select_to_delete'),
  'delOne' => __('at_js_confirm_delete_one'), 'delPre' => __('at_js_confirm_delete_prefix'), 'delSuf' => __('at_js_confirm_delete_suffix'),
  'pvKo' => __('at_pv_ko'), 'pvEn' => __('at_pv_en'), 'pvNone' => __('at_pv_none'), 'pvOther' => __('at_pv_other'), 'pvHint' => __('at_pv_hint')
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function csrf(){ return document.getElementById('csrf_token').value; }
function post(body){
  var f = new URLSearchParams(body); f.append('csrf_token', csrf());
  return fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:f.toString(), credentials:'same-origin'}).then(function(r){ return r.json(); });
}
function showToast(msg, err){
  if (document.documentElement.classList.contains('mc-embed')) { try { window.parent.postMessage({ mcToast: { m: String(msg), e: !!err } }, location.origin); return; } catch (x) {} } // ★ fix34 탭 안이면 바깥 화면 위에
  var t = document.createElement('div'); t.className = 'toast-message' + (err ? ' error' : ''); t.textContent = msg; document.body.appendChild(t);
  setTimeout(function(){ t.style.animation = 'slideIn 0.3s reverse'; setTimeout(function(){ t.remove(); }, 300); }, 2000);
}
function rowCbs(){ return document.querySelectorAll('.row-cb'); }
function updateBulkActions(){
  var all = rowCbs(), n = 0;
  all.forEach(function(cb){ cb.closest('.translation-row').classList.toggle('selected', cb.checked); if (cb.checked) n++; });
  document.getElementById('selectedCount').textContent = n;
  document.getElementById('bulkActions').classList.toggle('show', n > 0);
  var sa = document.getElementById('selAll'); sa.checked = n > 0 && n === all.length; sa.indeterminate = n > 0 && n < all.length;
}
function toggleSelectAll(on){ rowCbs().forEach(function(cb){ cb.checked = !!on; }); updateBulkActions(); }
function clearSelection(){ toggleSelectAll(false); }
function deleteSelected(){
  var cbs = document.querySelectorAll('.row-cb:checked');
  if (!cbs.length) { showToast(T.sel, true); return; }
  if (!confirm(T.delPre + cbs.length + T.delSuf)) return;
  var items = []; cbs.forEach(function(cb){ items.push({korean: cb.dataset.korean, english: cb.dataset.english}); });
  post({action:'delete_multiple', items: JSON.stringify(items)}).then(function(d){
    if (d.success) { showToast(d.message); setTimeout(function(){ location.reload(); }, 700); } else showToast(d.message, true);
  }).catch(function(){ showToast(T.err, true); });
}
function addTranslation(){
  var k = document.getElementById('newKorean').value.trim(), e = document.getElementById('newEnglish').value.trim();
  if (!k || !e) { showToast(T.both, true); (k ? document.getElementById('newEnglish') : document.getElementById('newKorean')).focus(); return; }
  post({action:'add', korean:k, english:e}).then(function(d){
    if (d.success) { showToast(d.message); try { sessionStorage.setItem('at_added', '1'); } catch (x) {} setTimeout(function(){ location.reload(); }, 600); }
    else showToast(d.message, true);
  }).catch(function(){ showToast(T.err, true); });
}
document.getElementById('newKorean').addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); document.getElementById('newEnglish').focus(); } });
document.getElementById('newEnglish').addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); addTranslation(); } });
try { if (sessionStorage.getItem('at_added')) { sessionStorage.removeItem('at_added'); document.getElementById('newKorean').focus(); } } catch (x) {} // 이어서 하나 더 넣기 쉽게
function setEditing(r, on){
  r.classList.toggle('editing', on);
  // ★ (2026-11-06 fix30) 읽을 때는 줄바꿈되는 글자(.at-full)로 — 칸(input)은 한 줄이라 긴 말이 잘려 안 보였음 · 고치기를 마치면(저장·취소) 글자를 칸 값으로 맞춤
  if (!on) r.querySelectorAll('.at-cell').forEach(function(c){ var i = c.querySelector('.form-control'), f = c.querySelector('.at-full'); if (!i || !f) return; var v = c.classList.contains('at-k') ? r.dataset.korean : r.dataset.english; f.textContent = (v != null) ? v : i.value; }); // ★ fix31 저장된 값(앞뒤 빈칸 뺀 것)으로 — 화면 읽기 프로그램도 이 글자를 읽음(칸은 숨어 있으므로 두 번 읽지 않음)
  r.querySelectorAll('.korean-input,.english-input').forEach(function(i){ if (on) i.removeAttribute('readonly'); else i.setAttribute('readonly', ''); });
  r.querySelector('.edit-btn').style.display = on ? 'none' : '';
  r.querySelector('.delete-btn').style.display = on ? 'none' : '';
  r.querySelector('.save-btn').style.display = on ? '' : 'none';
  r.querySelector('.cancel-btn').style.display = on ? '' : 'none';
}
function editRow(id){ var r = document.getElementById(id); setEditing(r, true); r.querySelector('.korean-input').focus(); }
function cancelEdit(id){ var r = document.getElementById(id); r.querySelector('.korean-input').value = r.dataset.korean; r.querySelector('.english-input').value = r.dataset.english; setEditing(r, false); }
function saveRow(id){
  var r = document.getElementById(id), nk = r.querySelector('.korean-input').value.trim(), ne = r.querySelector('.english-input').value.trim();
  if (!nk || !ne) { showToast(T.both, true); return; }
  post({action:'edit', old_korean:r.dataset.korean, old_english:r.dataset.english, new_korean:nk, new_english:ne}).then(function(d){
    if (!d.success) { showToast(d.message, true); return; }
    showToast(d.message); r.dataset.korean = nk; r.dataset.english = ne;
    var cb = r.querySelector('.row-cb'); if (cb) { cb.dataset.korean = nk; cb.dataset.english = ne; }
    setEditing(r, false);
  }).catch(function(){ showToast(T.err, true); });
}
document.getElementById('translationList').addEventListener('keydown', function(e){
  var r = e.target.closest && e.target.closest('.translation-row.editing'); if (!r) return;
  if (e.key === 'Enter') { e.preventDefault(); saveRow(r.id); } else if (e.key === 'Escape') { e.preventDefault(); cancelEdit(r.id); }
});
function deleteTranslation(id){
  if (!confirm(T.delOne)) return;
  var r = document.getElementById(id);
  post({action:'delete', korean:r.dataset.korean, english:r.dataset.english}).then(function(d){
    if (!d.success) { showToast(d.message, true); return; }
    showToast(d.message); r.style.transition = 'opacity .25s'; r.style.opacity = '0';
    setTimeout(function(){ r.remove(); var tc = document.getElementById('totalCount'); tc.textContent = Math.max(0, (parseInt(tc.textContent.replace(/[^0-9]/g, ''), 10) || 1) - 1).toLocaleString(); updateBulkActions(); }, 260);
  }).catch(function(){ showToast(T.err, true); });
}
// 검색 미리보기 — 실제 검색과 같은 규칙으로 함께 찾는 말
(function(){
  var q = document.getElementById('pvQ'), out = document.getElementById('pvOut'), tm = 0, seq = 0;
  function el(t, c, x){ var e = document.createElement(t); if (c) e.className = c; if (x != null) e.textContent = x; return e; }
  function show(){ out.textContent = ''; out.appendChild(el('span', 'text-muted', T.pvHint)); }
  show();
  q.addEventListener('input', function(){
    clearTimeout(tm);
    var v = q.value.trim(); if (!v) { seq++; show(); return; }
    tm = setTimeout(function(){
      var my = ++seq;
      post({action:'preview', q:v}).then(function(d){
        if (my !== seq) return; out.textContent = '';
        if (!d || !d.success) { out.appendChild(el('span', 'text-danger', T.err)); return; }
        if (d.dir === 'other') { out.appendChild(el('span', 'text-muted', T.pvOther)); return; }
        if (!d.words.length) { out.appendChild(el('span', 'text-muted', T.pvNone)); return; }
        out.appendChild(el('div', '', (d.dir === 'ko' ? T.pvKo : T.pvEn).replace('%s', v)));
        var box = el('div', ''); d.words.forEach(function(w){ box.appendChild(el('span', 'at-w', w)); }); out.appendChild(box);
      }).catch(function(){ if (my === seq) { out.textContent = ''; out.appendChild(el('span', 'text-danger', T.err)); } });
    }, 250);
  });
})();
</script>
<!-- ✅ 페이지 전환 시 부드러운 효과 -->
<script>
(function(){
    document.addEventListener('click', function(e) {
        // ★ (2026-11-02 11:35) 이미 막은 클릭·새 탭(Ctrl·⌘·Shift·가운데)·내려받기 링크는 건드리지 않음(bootstrap 과 같게)
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var link = e.target.closest('a[href]');
        if (!link || link.hasAttribute('download')) return;
        var href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank') return;
        e.preventDefault();
        document.documentElement.classList.add('leaving');
        setTimeout(function() { location.href = href; }, 100);
    });
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            document.documentElement.classList.remove('leaving');
            document.documentElement.classList.add('ready');
        }
    });
})();
</script>

<!-- ✅ 자동 로그아웃 타이머 -->
<?php 
$remaining = isset($_SESSION['last_action']) ? max(0, $timeout - (time() - $_SESSION['last_action'])) : $timeout;

// ✅ 현재 페이지가 적용 대상인지 확인
$_current_page = basename($_SERVER['SCRIPT_FILENAME']);
$_auto_logout_pages = $auto_logout_settings['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
$_is_target = in_array($_current_page, $_auto_logout_pages);

if (($auto_logout_settings['enabled'] ?? true) && $_is_target && !(isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true)): // ★ (2026-11-04 00:29) 로그인 유지면 화면 타이머도 안 붙임(관리자 화면과 같게 — 서버는 만료시키지 않는데 화면이 로그아웃시켰음) 
?>
<script>
window.SESSION_TIMEOUT = <?php echo $timeout; ?>;
window.SESSION_REMAINING = <?php echo $remaining; ?>;
</script>
<?php echo mc_al_i18n_script(); ?>
<script src="./js/auto-logout.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<!-- ✅ 세션 유지 스크립트 (관리자 페이지 장시간 사용 시 CSRF 토큰 만료 방지) -->
<script>
(function(){
    var KEEPALIVE_INTERVAL = 5 * 60 * 1000; // 5분마다
    var keepaliveTimer = null;
    
    function keepSessionAlive() {
        fetch('init.php?check_session=1&extend=1&keep=admin', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function(r){ return r.json(); }).then(function(data){
            if (data.status === 'logged_out' || data.status === 'expired') {
                clearInterval(keepaliveTimer);
                alert('<?php echo __h("at_js_session_expired"); ?>');
                (window.top || window).location.href = 'login.php'; // ★ fix34 탭 안이면 바깥 화면 전체를 로그인으로
            }
        }).catch(function(){});
    }
    
    // ★ (2026-11-03 09:15) 이 화면이 '자동 로그아웃 대상'이면 5분마다 연장하지 않음(admin.php 와 같음 — 체크해도 열어 둔 동안 로그아웃되지 않았음)
    if (<?php echo (!empty($auto_logout_settings['enabled'] ?? true) && !empty($_is_target) && !(isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true)) ? 'true' : 'false'; ?>) return;
    // 5분마다 세션 갱신
    keepaliveTimer = setInterval(keepSessionAlive, KEEPALIVE_INTERVAL);
    
    // 페이지 떠날 때 정리
    window.addEventListener('beforeunload', function() {
        if (keepaliveTimer) clearInterval(keepaliveTimer);
    });
})();
</script>

<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
<script>
// ★ (2026-11-06 fix34) 관리자 화면 탭 안에 불려 왔을 때 — 높이를 바깥에 알려 칸이 내용만큼(안쪽 스크롤 없게) · 이 화면 밖으로 가는 링크는 바깥 화면에서 열기
(function () {
  var H = document.documentElement; if (!H.classList.contains('mc-embed')) return;
  var last = 0;
  function post() { var h = document.body ? Math.ceil(document.body.getBoundingClientRect().height) : 0; /* 내용 높이(칸 높이가 아니라) — 줄면 칸도 줄게 */ if (h && Math.abs(h - last) > 1) { last = h; try { window.parent.postMessage({ mcEmbedH: h }, location.origin); } catch (x) {} } }
  if (window.ResizeObserver) { new ResizeObserver(post).observe(document.body); } else { setInterval(post, 800); }
  window.addEventListener('load', post); post();
  // ★ fix35 선택 막대 — 바깥이 알려 주는 '보이는 맨 위'에 맞춰 내려옴(sticky 대신) · 목록 끝을 넘어가지는 않음
  var vt = 0;
  function stick() {
    var b = document.querySelector('.bulk-actions'); if (!b) return;
    b.style.transform = ''; if (!b.classList.contains('show')) return;
    var nat = b.getBoundingClientRect().top + window.scrollY, list = document.getElementById('translationList') || b.parentElement;
    var maxD = Math.max(0, (list.getBoundingClientRect().bottom + window.scrollY) - nat - b.offsetHeight);
    var d = Math.min(maxD, Math.max(0, vt + 8 - nat));
    if (d > 0) { b.style.transform = 'translateY(' + d + 'px)'; b.style.position = 'relative'; b.style.zIndex = '20'; }
  }
  window.addEventListener('message', function (e) { if (e.origin === location.origin && e.source === window.parent && e.data && typeof e.data.mcVTop === 'number') { vt = e.data.mcVTop; stick(); } });
  var bb = document.querySelector('.bulk-actions'); if (bb && window.MutationObserver) new MutationObserver(function () { stick(); post(); }).observe(bb, { attributes: true, attributeFilter: ['class'] });
  window.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest ? e.target.closest('a[href]') : null; if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
    var href = a.getAttribute('href') || ''; if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) return;
    var u; try { u = new URL(a.href, location.href); } catch (x) { return; }
    if (u.origin === location.origin && /\/admin_translations\.php$/.test(u.pathname)) return; // 이 화면 안(검색·쪽 넘김)은 그대로
    e.preventDefault(); e.stopImmediatePropagation(); window.top.location.href = u.href;
  }, true);
})();
</script>
</body>
</html>