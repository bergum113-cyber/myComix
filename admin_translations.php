<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/i18n.php";

// 관리자만 접근 가능
if (!isset($_SESSION['user_id']) || $_SESSION['user_group'] != "admin") {
    header("Location: index.php");
    exit;
}

// ✅ 브랜딩 설정 로드 (function.php의 공통 함수 사용)
$_branding = load_branding();

$translations_file = __DIR__ . '/src/search_translations.json';

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
    $items = json_decode($_POST['items'] ?? '[]', true);
    
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => __('at_no_items_to_delete')]);
        exit;
    }
    
    $translations = load_json_with_lock($translations_file);
    $new_translations = [];
    $deleted_count = 0;
    
    foreach ($translations as $item) {
        $should_delete = false;
        foreach ($items as $delete_item) {
            if ($item['korean'] === $delete_item['korean'] && $item['english'] === $delete_item['english']) {
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
    $korean = trim($_POST['korean'] ?? '');
    $english = trim($_POST['english'] ?? '');
    
    if ($korean !== '' && $english !== '') {
        $translations = load_json_with_lock($translations_file);
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
    $new_korean = trim($_POST['new_korean'] ?? '');
    $new_english = trim($_POST['new_english'] ?? '');
    
    if ($new_korean !== '' && $new_english !== '') {
        $translations = load_json_with_lock($translations_file);
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
        $lines = explode("\n", $content);
        $new_translations = [];
        foreach ($lines as $i => $line) {
            if ($i === 0 || trim($line) === '') continue;
            $parts = str_getcsv($line);
            if (count($parts) >= 2) {
                $korean = trim($parts[0]);
                $english = trim($parts[1]);
                if ($korean !== '' && $english !== '') {
                    $new_translations[] = ['korean' => $korean, 'english' => $english];
                }
            }
        }
        if (!empty($new_translations)) {
            save_json_with_lock($translations_file, $new_translations);
            $message = "✅ " . __("at_csv_imported", count($new_translations));
        }
    }
    }
}

// 번역 사전 로드
$translations = load_json_with_lock($translations_file);

// 검색 필터
// ✅ sanitize_input()으로 입력 검증 (XSS 방지 강화)
$search_query = sanitize_input($_GET['search'] ?? '', 'search');
$filtered_translations = $translations;

if (!empty($search_query)) {
    $search_lower = mb_strtolower($search_query, 'UTF-8');
    $filtered_translations = array_filter($translations, function($item) use ($search_lower) {
        $korean_lower = mb_strtolower($item['korean'], 'UTF-8');
        $english_lower = mb_strtolower($item['english'], 'UTF-8');
        return mb_strpos($korean_lower, $search_lower, 0, 'UTF-8') !== false 
            || mb_strpos($english_lower, $search_lower, 0, 'UTF-8') !== false;
    });
}

// 정렬: 중복 많은 순 → 같은 단어끼리 그룹핑 → 가나다순
if (!empty($filtered_translations)) {
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
$per_page = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_count = count($filtered_translations);
$total_pages = max(1, ceil($total_count / $per_page));
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
<html>
<head>
<title><?php echo __("at_page_title"); ?> - <?php echo htmlspecialchars($_branding['admin_title'] ?? __('at_admin_title_default')); ?></title>
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
<style>
body{padding:15px;font-family:'Nanum Gothic',sans-serif;background:#f5f5f5}
.container{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);max-width:1200px}
h2{font-size:1.5em;word-break:keep-all}
.translation-row{margin-bottom:8px;padding:12px;background:#f8f9fa;border-radius:4px;transition:all 0.2s}
.translation-row:hover{background:#e9ecef;box-shadow:0 2px 4px rgba(0,0,0,0.1)}
.translation-row.editing{background:#fff3cd;border:2px solid #ffc107}
.translation-row.selected{background:#cfe2ff;border-left:4px solid #0d6efd}
.btn-remove{background:#dc3545;color:white;border:none}
.btn-remove:hover{background:#c82333}
.btn-edit{background:#17a2b8;color:white;border:none}
.btn-edit:hover{background:#138496}
.stats-box{background:#e3f2fd;padding:12px;border-radius:6px;margin-bottom:15px;font-size:0.9em;line-height:1.8}
.stats-box .stat-item{display:inline-block;margin-right:15px;white-space:nowrap}
.duplicate-highlight{background:#fff3cd !important;border-left:3px solid #ffc107}
.add-form{background:#d4edda;padding:15px;border-radius:6px;margin-bottom:15px;border:2px solid #28a745}
.add-form h5{font-size:1.1em;margin-bottom:10px}
.toast-message{position:fixed;top:20px;right:20px;padding:15px 20px;background:#28a745;color:white;border-radius:4px;box-shadow:0 4px 6px rgba(0,0,0,0.2);z-index:9999;animation:slideIn 0.3s}
.toast-message.error{background:#dc3545}
@keyframes slideIn{from{transform:translateX(400px);opacity:0}to{transform:translateX(0);opacity:1}}
.checkbox-col{display:flex;align-items:center;justify-content:center}
.checkbox-col input[type="checkbox"]{width:20px;height:20px;cursor:pointer}
.bulk-actions{background:#fff3cd;padding:12px;border-radius:6px;margin-bottom:15px;display:none;align-items:center;gap:10px;flex-wrap:wrap}
.bulk-actions.show{display:flex}
.select-all-btn{cursor:pointer;user-select:none}
.action-buttons{margin-bottom:15px;display:flex;gap:8px;flex-wrap:wrap}
.action-buttons .btn{font-size:0.85em;padding:6px 10px}
.search-form{margin-bottom:15px}
.search-form input{width:100%}
.translation-row .btn{padding:4px 8px;font-size:0.8em}
.translation-row .form-control-sm{font-size:0.9em}

/* 모바일 반응형 */
@media(max-width:768px){
    body{padding:10px}
    .container{padding:15px}
    h2{font-size:1.3em}
    .stats-box{padding:10px;font-size:0.8em;line-height:2}
    .stats-box .stat-item{display:block;margin-right:0}
    .add-form{padding:12px}
    .add-form h5{font-size:1em}
    .action-buttons .btn{font-size:0.75em;padding:5px 8px}
    .translation-row{padding:10px}
    .translation-row .col-md-5{margin-bottom:8px}
    .translation-row .btn-group-mobile{display:flex;gap:5px;margin-top:8px;justify-content:flex-end}
    .search-form input{font-size:0.9em;margin-bottom:8px}
    .alert{font-size:0.85em;padding:10px}
    .pagination{font-size:0.85em}
    .pagination .page-link{padding:6px 10px}
}
</style>
<script>document.documentElement.classList.add('ready');</script>
</head>
<body>
<!-- CSRF 토큰 -->
<input type="hidden" id="csrf_token" value="<?php echo h(generate_csrf_token()); ?>">

<div class="container">
<h2><?php echo __("at_heading"); ?></h2>

<div class="stats-box">
<div class="stat-item"><strong><?php echo __("at_stat_total"); ?></strong> <span id="totalCount"><?php echo $stats['total']; ?></span><?php echo __('at_count_suffix'); ?></div>
<div class="stat-item"><strong><?php echo __("at_stat_unique_ko"); ?></strong> <?php echo $stats['unique_korean']; ?><?php echo __('at_count_suffix'); ?></div>
<div class="stat-item"><strong><?php echo __("at_stat_unique_en"); ?></strong> <?php echo $stats['unique_english']; ?><?php echo __('at_count_suffix'); ?></div>
<div class="stat-item"><strong><?php echo __("at_stat_duplicates"); ?></strong> <?php echo $stats['total'] - $stats['unique_korean']; ?><?php echo __('at_count_suffix'); ?></div>
<div class="stat-item"><strong><?php echo __("at_stat_sort"); ?></strong> <?php echo __("at_stat_sort_desc"); ?></div>
</div>

<?php if (isset($message)): ?>
<div class="alert alert-success alert-dismissible fade show">
<?php echo $message; ?>
<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<div class="add-form">
<h5><?php echo __("at_add_new"); ?></h5>
<div class="row g-2">
<div class="col-12 col-md-5">
<input type="text" id="newKorean" class="form-control" placeholder="<?php echo __h('at_placeholder_ko'); ?>">
</div>
<div class="col-12 col-md-5">
<input type="text" id="newEnglish" class="form-control" placeholder="<?php echo __h('at_placeholder_en'); ?>">
</div>
<div class="col-12 col-md-2">
<button type="button" class="btn btn-success btn-block w-100" onclick="addTranslation()"><?php echo __h("at_btn_add"); ?></button>
</div>
</div>
</div>

<div class="bulk-actions" id="bulkActions">
<strong><span id="selectedCount">0</span><?php echo __h("at_selected"); ?></strong>
<button type="button" class="btn btn-danger btn-sm" onclick="deleteSelected()"><?php echo __("at_btn_delete_selected"); ?></button>
<button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()"><?php echo __("at_btn_clear_selection"); ?></button>
</div>

<div class="action-buttons" style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap">
<button type="button" class="btn btn-primary select-all-btn" onclick="toggleSelectAll()">
<?php echo __('at_js_select_all'); ?>
</button>
<a href="?export=csv" class="btn btn-success"><?php echo __("at_btn_csv_export"); ?></a>
<button type="button" class="btn btn-info" onclick="document.getElementById('csvImport').style.display='block'"><?php echo __('at_btn_csv_import'); ?></button>
<a href="admin.php" class="btn btn-outline-secondary"><?php echo __("at_btn_back_admin"); ?></a>
</div>

<div id="csvImport" style="display:none;margin-bottom:20px;padding:15px;background:#f8f9fa;border-radius:4px">
<h5><?php echo __("at_csv_import_title"); ?></h5>
<p class="text-muted"><?php echo __("at_csv_format_desc"); ?> <strong><?php echo __("at_csv_warning"); ?></strong></p>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="file" name="csv_file" accept=".csv" class="form-control" style="display:inline-block;width:auto">
<button type="submit" class="btn btn-primary"><?php echo __h("at_btn_upload"); ?></button>
<button type="button" class="btn btn-secondary" onclick="document.getElementById('csvImport').style.display='none'"><?php echo __h("at_btn_cancel"); ?></button>
</form>
</div>

<div class="search-form">
<form method="get" class="d-flex flex-column flex-md-row gap-2 align-items-stretch align-items-md-center">
<input type="text" name="search" class="form-control" style="flex:1;" placeholder="<?php echo __h('at_search_placeholder'); ?>" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES); ?>">
<div class="d-flex gap-2 flex-shrink-0 align-items-center">
<button type="submit" class="btn btn-primary" style="white-space:nowrap;"><?php echo __("at_btn_search"); ?></button>
<?php if (!empty($search_query)): ?>
<a href="?" class="btn btn-outline-secondary" style="white-space:nowrap;"><?php echo __("at_btn_reset"); ?></a>
<?php endif; ?>
</div>
</form>
</div>

<?php if (!empty($search_query)): ?>
<div class="alert alert-info"><?php echo __('at_search_results', $total_count, $stats['total']); ?></div>
<?php endif; ?>

<div style="text-align:center;color:#666;margin:10px 0">
<?php echo ($offset + 1) . ' - ' . min($offset + $per_page, $total_count) . ' / ' . $total_count . ' ' . __('at_items'); ?>
</div>

<div id="translationList">
<?php 
// 화면 표시용 중복 카운트 (대소문자 구분 없음)
$korean_counts = array_count_values(array_column($translations, 'korean'));
$english_lower_all = array_map('strtolower', array_column($translations, 'english'));
$english_counts_lower_all = array_count_values($english_lower_all);

// 원본 영어 → 소문자 카운트 매핑
$english_counts = [];
foreach ($translations as $item) {
    $english_counts[$item['english']] = $english_counts_lower_all[strtolower($item['english'])];
}

foreach ($page_translations as $index => $item): 
    $is_duplicate = ($korean_counts[$item['korean']] > 1) || ($english_counts[$item['english']] > 1);
    $row_class = $is_duplicate ? 'translation-row duplicate-highlight' : 'translation-row';
    $unique_id = 'row_' . $index;
?>
<div class="<?php echo $row_class; ?>" id="<?php echo $unique_id; ?>" 
     data-korean="<?php echo htmlspecialchars($item['korean'], ENT_QUOTES); ?>"
     data-english="<?php echo htmlspecialchars($item['english'], ENT_QUOTES); ?>">
<div class="row g-2 align-items-center">
<div class="col-12 col-md-5">
<input type="text" class="form-control form-control-sm korean-input" value="<?php echo htmlspecialchars($item['korean'], ENT_QUOTES); ?>" readonly>
<?php if ($korean_counts[$item['korean']] > 1): ?>
<small class="text-warning">⚠️ <?php echo __('at_duplicate_count', $korean_counts[$item['korean']]); ?></small>
<?php endif; ?>
</div>
<div class="col-12 col-md-5">
<input type="text" class="form-control form-control-sm english-input" value="<?php echo htmlspecialchars($item['english'], ENT_QUOTES); ?>" readonly>
<?php if ($english_counts[$item['english']] > 1): ?>
<small class="text-info">ℹ️ <?php echo __('at_duplicate_count', $english_counts[$item['english']]); ?></small>
<?php endif; ?>
</div>
<div class="col-12 col-md-2">
<div class="d-flex gap-1 justify-content-end align-items-center">
<button type="button" class="btn btn-edit btn-sm edit-btn" onclick="editRow('<?php echo $unique_id; ?>')">✏️</button>
<button type="button" class="btn btn-success btn-sm save-btn" style="display:none" onclick="saveRow('<?php echo $unique_id; ?>')">💾</button>
<button type="button" class="btn btn-secondary btn-sm cancel-btn" style="display:none" onclick="cancelEdit('<?php echo $unique_id; ?>')">✕</button>
<button type="button" class="btn btn-remove btn-sm delete-btn" onclick="deleteTranslation('<?php echo $unique_id; ?>')">🗑️</button>
<input type="checkbox" class="item-checkbox" style="width:18px;height:18px;margin-left:5px"
       data-korean="<?php echo htmlspecialchars($item['korean'], ENT_QUOTES); ?>"
       data-english="<?php echo htmlspecialchars($item['english'], ENT_QUOTES); ?>"
       onchange="updateBulkActions()">
</div>
</div>
</div>
</div>
<?php endforeach; ?>
</div>

<?php if ($total_pages > 1): ?>
<nav><ul class="pagination" style="margin-top:20px;justify-content:center">
<?php if ($page > 1): ?>
<li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"><?php echo __("at_prev"); ?></a></li>
<?php endif; ?>

<?php
$start_page = max(1, $page - 2);
$end_page = min($total_pages, $page + 2);
if ($start_page > 1): ?>
<li class="page-item"><a class="page-link" href="?page=1<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>">1</a></li>
<?php if ($start_page > 2): ?>
<li class="page-item disabled"><span class="page-link">...</span></li>
<?php endif; endif; ?>

<?php for ($i = $start_page; $i <= $end_page; $i++): ?>
<li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
<a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"><?php echo $i; ?></a>
</li>
<?php endfor; ?>

<?php if ($end_page < $total_pages): ?>
<?php if ($end_page < $total_pages - 1): ?>
<li class="page-item disabled"><span class="page-link">...</span></li>
<?php endif; ?>
<li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"><?php echo $total_pages; ?></a></li>
<?php endif; ?>

<?php if ($page < $total_pages): ?>
<li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"><?php echo __("at_next"); ?></a></li>
<?php endif; ?>
</ul></nav>
<?php endif; ?>

<div class="alert alert-light mt-4">
<strong><?php echo __("at_tips_title"); ?></strong>
<ul class="mb-0">
<li><strong><?php echo __("at_tip_sort_label"); ?></strong> <?php echo __("at_tip_sort_desc"); ?></li>
<li><strong><?php echo __("at_tip_checkbox_label"); ?></strong> <?php echo __("at_tip_checkbox_desc"); ?></li>
<li><strong><?php echo __("at_tip_selectall_label"); ?></strong> <?php echo __("at_tip_selectall_desc"); ?></li>
<li><strong><?php echo __("at_tip_instant_label"); ?></strong> <?php echo __("at_tip_instant_desc"); ?></li>
<li><?php echo __("at_tip_yellow"); ?></li>
</ul>
</div>
</div>

<script src="./js/jquery-3.5.1.min.js"></script>
<script src="./js/popper.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<script>
var allSelected = false;

function showToast(msg,err){
const t=document.createElement('div');
t.className='toast-message'+(err?' error':'');
t.textContent=msg;
document.body.appendChild(t);
setTimeout(()=>{t.style.animation='slideIn 0.3s reverse';setTimeout(()=>t.remove(),300)},2000);
}

function updateBulkActions(){
const cbs=document.querySelectorAll('.item-checkbox:checked');
const cnt=cbs.length;
const total=document.querySelectorAll('.item-checkbox').length;
document.getElementById('selectedCount').textContent=cnt;
document.getElementById('bulkActions').classList.toggle('show',cnt>0);
document.querySelectorAll('.translation-row').forEach(r=>{
const cb=r.querySelector('.item-checkbox');
r.classList.toggle('selected',cb&&cb.checked);
});
allSelected = (cnt === total && total > 0);
updateSelectAllButton();
}

function updateSelectAllButton(){
const btn=document.querySelector('.select-all-btn');
if(allSelected){
btn.innerHTML='<?php echo __h("at_js_deselect_all"); ?>';
btn.classList.remove('btn-primary');
btn.classList.add('btn-secondary');
}else{
btn.innerHTML='<?php echo __h("at_js_select_all"); ?>';
btn.classList.remove('btn-secondary');
btn.classList.add('btn-primary');
}
}

function toggleSelectAll(){
allSelected = !allSelected;
document.querySelectorAll('.item-checkbox').forEach(cb=>cb.checked=allSelected);
updateBulkActions();
}

function clearSelection(){
allSelected = false;
document.querySelectorAll('.item-checkbox').forEach(cb=>cb.checked=false);
updateBulkActions();
}

function deleteSelected(){
const cbs=document.querySelectorAll('.item-checkbox:checked');
if(cbs.length===0){showToast('<?php echo __h("at_js_select_to_delete"); ?>',true);return}
if(!confirm('<?php echo __h("at_js_confirm_delete_prefix"); ?>' + cbs.length + '<?php echo __h("at_js_confirm_delete_suffix"); ?>'))return;
const items=[];
const csrfToken=document.getElementById('csrf_token').value;
cbs.forEach(cb=>items.push({korean:cb.dataset.korean,english:cb.dataset.english}));
fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:`action=delete_multiple&csrf_token=${encodeURIComponent(csrfToken)}&items=${encodeURIComponent(JSON.stringify(items))}`})
.then(r=>r.json()).then(d=>{
if(d.success){showToast(d.message);setTimeout(()=>location.reload(),1000)}
else showToast(d.message,true);
}).catch(e=>{showToast('<?php echo __h("at_js_error"); ?>',true);console.error(e)});
}

function addTranslation(){
const k=document.getElementById('newKorean').value.trim();
const e=document.getElementById('newEnglish').value.trim();
const csrfToken=document.getElementById('csrf_token').value;
if(!k||!e){showToast(__('at_both_required'),true);return}
fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:`action=add&csrf_token=${encodeURIComponent(csrfToken)}&korean=${encodeURIComponent(k)}&english=${encodeURIComponent(e)}`})
.then(r=>r.json()).then(d=>{
if(d.success){showToast(d.message);document.getElementById('newKorean').value='';
document.getElementById('newEnglish').value='';setTimeout(()=>location.reload(),1000)}
else showToast(d.message,true);
}).catch(e=>{showToast('<?php echo __h("at_js_error"); ?>',true);console.error(e)});
}

document.getElementById('newKorean').addEventListener('keypress',e=>{if(e.key==='Enter')document.getElementById('newEnglish').focus()});
document.getElementById('newEnglish').addEventListener('keypress',e=>{if(e.key==='Enter')addTranslation()});

function editRow(id){
const r=document.getElementById(id);
r.classList.add('editing');
r.querySelector('.korean-input').removeAttribute('readonly');
r.querySelector('.english-input').removeAttribute('readonly');
r.querySelector('.edit-btn').style.display='none';
r.querySelector('.save-btn').style.display='inline-block';
r.querySelector('.cancel-btn').style.display='inline-block';
r.querySelector('.delete-btn').style.display='none';
r.querySelector('.korean-input').focus();
}

function cancelEdit(id){
const r=document.getElementById(id);
r.querySelector('.korean-input').value=r.dataset.korean;
r.querySelector('.english-input').value=r.dataset.english;
r.classList.remove('editing');
r.querySelector('.korean-input').setAttribute('readonly',true);
r.querySelector('.english-input').setAttribute('readonly',true);
r.querySelector('.edit-btn').style.display='inline-block';
r.querySelector('.save-btn').style.display='none';
r.querySelector('.cancel-btn').style.display='none';
r.querySelector('.delete-btn').style.display='inline-block';
}

function saveRow(id){
const r=document.getElementById(id);
const ok=r.dataset.korean,oe=r.dataset.english;
const nk=r.querySelector('.korean-input').value.trim();
const ne=r.querySelector('.english-input').value.trim();
const csrfToken=document.getElementById('csrf_token').value;
if(!nk||!ne){showToast(__('at_both_required'),true);return}
fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:`action=edit&csrf_token=${encodeURIComponent(csrfToken)}&old_korean=${encodeURIComponent(ok)}&old_english=${encodeURIComponent(oe)}&new_korean=${encodeURIComponent(nk)}&new_english=${encodeURIComponent(ne)}`})
.then(res=>res.json()).then(d=>{
if(d.success){showToast(d.message);r.dataset.korean=nk;r.dataset.english=ne;
r.classList.remove('editing');
r.querySelector('.korean-input').setAttribute('readonly',true);
r.querySelector('.english-input').setAttribute('readonly',true);
r.querySelector('.edit-btn').style.display='inline-block';
r.querySelector('.save-btn').style.display='none';
r.querySelector('.cancel-btn').style.display='none';
r.querySelector('.delete-btn').style.display='inline-block';
}else showToast(d.message,true);
}).catch(e=>{showToast('<?php echo __h("at_js_error"); ?>',true);console.error(e)});
}

function deleteTranslation(id){
if(!confirm('<?php echo __h("at_js_confirm_delete_one"); ?>'))return;
const r=document.getElementById(id);
const k=r.dataset.korean,e=r.dataset.english;
const csrfToken=document.getElementById('csrf_token').value;
fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:`action=delete&csrf_token=${encodeURIComponent(csrfToken)}&korean=${encodeURIComponent(k)}&english=${encodeURIComponent(e)}`})
.then(res=>res.json()).then(d=>{
if(d.success){showToast(d.message);r.style.transition='all 0.3s';r.style.opacity='0';
r.style.transform='translateX(-100%)';
setTimeout(()=>{r.remove();
document.getElementById('totalCount').textContent=parseInt(document.getElementById('totalCount').textContent)-1;
},300)}else showToast(d.message,true);
}).catch(e=>{showToast('<?php echo __h("at_js_error"); ?>',true);console.error(e)});
}

document.addEventListener('keydown',e=>{if(e.ctrlKey&&e.key==='n'){e.preventDefault();document.getElementById('newKorean').focus()}});
</script>
<!-- ✅ 페이지 전환 시 부드러운 효과 -->
<script>
(function(){
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href]');
        if (!link) return;
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

if (($auto_logout_settings['enabled'] ?? true) && $_is_target): 
?>
<script>
window.SESSION_TIMEOUT = <?php echo $timeout; ?>;
window.SESSION_REMAINING = <?php echo $remaining; ?>;
</script>
<script src="./js/auto-logout.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<!-- ✅ 세션 유지 스크립트 (관리자 페이지 장시간 사용 시 CSRF 토큰 만료 방지) -->
<script>
(function(){
    var KEEPALIVE_INTERVAL = 5 * 60 * 1000; // 5분마다
    var keepaliveTimer = null;
    
    function keepSessionAlive() {
        fetch('init.php?check_session=1&extend=1', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function(r){ return r.json(); }).then(function(data){
            if (data.status === 'logged_out' || data.status === 'expired') {
                clearInterval(keepaliveTimer);
                alert('<?php echo __h("at_js_session_expired"); ?>');
                location.href = 'login.php';
            }
        }).catch(function(){});
    }
    
    // 5분마다 세션 갱신
    keepaliveTimer = setInterval(keepSessionAlive, KEEPALIVE_INTERVAL);
    
    // 페이지 떠날 때 정리
    window.addEventListener('beforeunload', function() {
        if (keepaliveTimer) clearInterval(keepaliveTimer);
    });
})();
</script>

</body>
</html>