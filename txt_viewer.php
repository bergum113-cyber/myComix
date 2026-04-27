<?php
/**
 * myComix TXT 뷰어
 * 대용량 텍스트 파일을 효율적으로 렌더링
 * 
 * @version 1.3 - 페이지 레이아웃 모드 추가 (1단, 2단 좌→우, 2단 우→좌)
 * @date 2026-01-14
 * 
 * 특징:
 * - 대용량 파일 지원 (청크 단위 로딩)
 * - 인코딩 자동 감지 (UTF-8, EUC-KR, CP949 등)
 * - 다크모드 지원
 * - 글꼴 크기, 줄간격 조절
 * - 페이지 레이아웃 (1단/2단 책 모드)
 * - 진행 위치 저장/복원
 */

require_once __DIR__ . "/bootstrap.php";
handle_timeout_popup();  // ✅ 자동 로그아웃 메시지 처리

// ✅ 다중 폴더 지원: URL 파라미터로 폴더 선택
$bidx = init_bidx();  // ✅ 항상 bidx 포함

// ✅ 브랜딩 설정 로드 (function.php의 공통 함수 사용)
$_branding = load_branding();

// 설정값
$txt_settings = $txt_viewer_settings ?? [
    'max_file_size' => 50 * 1024 * 1024,
    'chunk_size' => 100 * 1024,
    'default_font_size' => 18,
    'default_line_height' => 1.8,
    'encoding_detect' => true,
];

// ============================================================
// AJAX 요청 처리: 청크 데이터 반환
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'get_chunk') {
    header('Content-Type: application/json; charset=utf-8');
    
    $getfile = decode_file_param($_GET['file'] ?? '');
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $length = min($txt_settings['chunk_size'], max(1024, (int)($_GET['length'] ?? $txt_settings['chunk_size'])));
    
    // ✅ 경로 검증 통일 (validate_file_path 사용)
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        echo json_encode(['error' => __('err_access_denied')]);
        exit;
    }
    
    if (!file_exists($base_file) || !preg_match('/\.txt$/i', $base_file)) {
        echo json_encode(['error' => __('err_file_not_found')]);
        exit;
    }
    
    $filesize = filesize($base_file);
    
    if ($offset >= $filesize) {
        echo json_encode([
            'content' => '',
            'offset' => $filesize,
            'hasMore' => false,
            'filesize' => $filesize
        ]);
        exit;
    }
    
    // 파일에서 청크 읽기
    $fp = fopen($base_file, 'rb');
    fseek($fp, $offset);
    $raw_content = fread($fp, $length);
    fclose($fp);
    
    $raw_len = strlen($raw_content);
    
    // UTF-8 멀티바이트 문자 경계 조정 (끝부분에서 잘린 문자 제거)
    $fixed_content = fix_utf8_boundary($raw_content);
    $fixed_len = strlen($fixed_content);
    
    // 잘린 바이트 수 계산 (다음 청크에서 다시 읽어야 함)
    $trimmed_bytes = $raw_len - $fixed_len;
    
    // 인코딩 변환
    $content = convert_encoding($fixed_content);
    
    // 다음 청크 경계 조정 (잘린 바이트는 다음 청크에서 다시 읽음)
    $next_offset = $offset + $raw_len - $trimmed_bytes;
    
    echo json_encode([
        'content' => $content,
        'offset' => $next_offset,
        'hasMore' => $next_offset < $filesize,
        'filesize' => $filesize,
        'progress' => round(($next_offset / $filesize) * 100, 1)
    ]);
    exit;
}

// ============================================================
// AJAX 요청 처리: 전체 메타데이터
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'get_meta') {
    header('Content-Type: application/json; charset=utf-8');
    
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    // ✅ 경로 검증 통일 (validate_file_path 사용)
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        echo json_encode(['error' => __('err_access_denied')]);
        exit;
    }
    
    if (!file_exists($base_file) || !preg_match('/\.txt$/i', $base_file)) {
        echo json_encode(['error' => __('err_file_not_found')]);
        exit;
    }
    
    $filesize = filesize($base_file);
    $encoding = detect_file_encoding($base_file);
    
    echo json_encode([
        'filename' => basename($base_file),
        'filesize' => $filesize,
        'encoding' => $encoding,
        'humanSize' => human_filesize($filesize),
        'chunkSize' => $txt_settings['chunk_size']
    ]);
    exit;
}

// ============================================================
// AJAX 요청 처리: 진행률 저장
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'save_progress' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    $getfile = $_POST['file'] ?? '';
    $getfile = ltrim($getfile, '/');
    
    $percent = (float)($_POST['percent'] ?? 0);
    $position = (int)($_POST['position'] ?? 0);
    
    $progress_file = get_txt_progress_file();
    $progress = load_json_with_lock($progress_file);
    
    $progress[$getfile] = [
        'percent' => $percent,
        'position' => $position,
        'time' => date('Y-m-d H:i:s')
    ];
    
    save_json_with_lock($progress_file, $progress);
    
    echo json_encode(['success' => true]);
    exit;
}

// ============================================================
// AJAX 요청 처리: 진행률 로드
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'load_progress') {
    header('Content-Type: application/json; charset=utf-8');
    
    // save_progress와 동일하게 키 정규화
    $getfile = $_GET['file'] ?? '';
    $getfile = ltrim($getfile, '/');
    
    // ✅ bootstrap.php의 함수 사용 (파일 잠금 적용)
    $progress_file = get_txt_progress_file();
    $progress = load_json_with_lock($progress_file);
    
    $saved = $progress[$getfile] ?? null;
    
    echo json_encode(['progress' => $saved]);
    exit;
}

// ============================================================
// 헬퍼 함수
// ============================================================

/**
 * 파일 인코딩 감지
 */
function detect_file_encoding($file_path) {
    $sample = file_get_contents($file_path, false, null, 0, 4096);
    
    // BOM 체크
    if (substr($sample, 0, 3) === "\xEF\xBB\xBF") return 'UTF-8';
    if (substr($sample, 0, 2) === "\xFF\xFE") return 'UTF-16LE';
    if (substr($sample, 0, 2) === "\xFE\xFF") return 'UTF-16BE';
    
    // UTF-8 유효성 체크
    if (mb_check_encoding($sample, 'UTF-8') && preg_match('//u', $sample)) {
        return 'UTF-8';
    }
    
    // EUC-KR/CP949 체크 (한글 범위)
    if (mb_check_encoding($sample, 'EUC-KR')) {
        return 'EUC-KR';
    }
    
    return 'UTF-8'; // 기본값
}

/**
 * 인코딩 변환
 */
function convert_encoding($content) {
    // BOM 제거
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    
    // UTF-8이면 그대로
    if (mb_check_encoding($content, 'UTF-8') && preg_match('//u', $content)) {
        return $content;
    }
    
    // EUC-KR/CP949 변환 시도
    $converted = @mb_convert_encoding($content, 'UTF-8', 'CP949,EUC-KR');
    if ($converted !== false) {
        return $converted;
    }
    
    return $content;
}

/**
 * UTF-8 멀티바이트 문자 경계 조정
 * 청크 끝에서 잘린 멀티바이트 문자 제거
 */
function fix_utf8_boundary($str) {
    if (empty($str)) return $str;
    
    $len = strlen($str);
    
    // 시작 부분: 연속 바이트(10xxxxxx)로 시작하면 제거
    $start = 0;
    while ($start < $len && (ord($str[$start]) & 0xC0) === 0x80) {
        $start++;
    }
    if ($start > 0) {
        $str = substr($str, $start);
        $len = strlen($str);
    }
    
    if (empty($str)) return $str;
    
    // 끝에서부터 최대 4바이트 확인 (UTF-8은 최대 4바이트)
    for ($i = 1; $i <= 4 && $i <= $len; $i++) {
        $byte = ord($str[$len - $i]);
        
        // ASCII (0xxxxxxx) - 완전한 문자
        if ($byte < 0x80) {
            break;
        }
        
        // 연속 바이트 (10xxxxxx) - 계속 확인
        if (($byte & 0xC0) === 0x80) {
            continue;
        }
        
        // 시작 바이트 발견 - 필요한 바이트 수 계산
        $needed = 0;
        if (($byte & 0xE0) === 0xC0) $needed = 2;      // 110xxxxx - 2바이트 문자
        else if (($byte & 0xF0) === 0xE0) $needed = 3; // 1110xxxx - 3바이트 문자 (한글)
        else if (($byte & 0xF8) === 0xF0) $needed = 4; // 11110xxx - 4바이트 문자
        
        // 불완전한 문자면 제거
        if ($needed > 0 && $i < $needed) {
            return substr($str, 0, $len - $i);
        }
        break;
    }
    
    return $str;
}

/**
 * 파일 크기를 사람이 읽기 쉬운 형태로
 */
function human_filesize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// ============================================================
// 페이지 렌더링
// ============================================================

$getfile = decode_file_param($_GET['file'] ?? '');
if (!$getfile) {
    header("Location: ./");
    exit;
}

// ✅ 경로 검증 통일 (validate_file_path 사용)
$base_file = validate_file_path($getfile, $base_dir);
if ($base_file === false) {
    echo __("err_invalid_path");
    exit;
}

if (!file_exists($base_file) || !preg_match('/\.txt$/i', $base_file)) {
    echo __('txt_file_not_found');
    exit;
}

$title = basename($base_file, '.txt');
$filesize = filesize($base_file);
$encoding = detect_file_encoding($base_file);

// 상위 폴더 링크
$link_dir = dirname($getfile);
if ($link_dir === '.' || $link_dir === '/') {
    $link_dir = '';
}

// 다크모드/세피아 설정
$darkmode = $_COOKIE['darkmode'] ?? ($darkmode_settings['default'] ?? 'system');
$darkClass = '';
if ($darkmode === 'dark') {
    $darkClass = 'dark-mode';
} elseif ($darkmode === 'sepia') {
    $darkClass = 'sepia-mode';
}

// 저장된 진행 위치 로드
$progress_file = get_txt_progress_file();
$saved_progress = [];
if (is_file($progress_file)) {
    $saved_progress = json_decode(file_get_contents($progress_file), true) ?? [];
}
$progress_key = ltrim($getfile, '/');
$saved_data = $saved_progress[$progress_key] ?? [];
$saved_position = is_array($saved_data) ? ($saved_data['position'] ?? 0) : 0;
$saved_percent = is_array($saved_data) ? ($saved_data['percent'] ?? 0) : 0;
?>
<!DOCTYPE html>
<?php
// 커스텀 폰트 설정
$txt_font_name = $txt_settings['font_name'] ?? '';
$txt_font_url = $txt_settings['font_url'] ?? '';
$txt_font_local = $txt_settings['font_local'] ?? '';
$txt_font_family = !empty($txt_font_name) ? "'" . h($txt_font_name) . "', 'Nanum Gothic', 'Malgun Gothic', sans-serif" : "'Nanum Gothic', 'Malgun Gothic', sans-serif";
?>
<html lang="ko" class="<?php echo h($darkClass); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo h($title); ?> - TXT <?php echo __h("viewer_label"); ?></title>
    <!-- ✅ 핵심 레이아웃 + 페이지 전환 -->
    <style>
        html{opacity:0;transition:opacity .15s ease-in}
        html.ready{opacity:1}
        html.leaving{opacity:0;transition:opacity .1s ease-out}
    </style>
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <?php if (!empty($txt_font_url)): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?php echo h($txt_font_url); ?>" rel="stylesheet">
    <?php endif; ?>
    <link rel="shortcut icon" href="./favicon.ico">
    <style>
        <?php if (!empty($txt_font_local) && empty($txt_font_url)): ?>
        @font-face {
            font-family: '<?php echo h($txt_font_name ?: 'CustomTxtFont'); ?>';
            src: url('<?php echo h($txt_font_local); ?>');
            font-display: swap;
        }
        <?php endif; ?>
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --reader-bg: #f8f9fa;
            --toolbar-bg: #ffffff;
            --border-color: #dee2e6;
            --progress-bg: #e9ecef;
            --progress-fill: #007bff;
            --page-divider: #ccc;
        }
        
        /* 다크 모드 */
        .dark-mode {
            --bg-color: #1a1a2e;
            --text-color: #e0e0e0;
            --reader-bg: #16213e;
            --toolbar-bg: #0f3460;
            --border-color: #404040;
            --progress-bg: #333333;
            --progress-fill: #4da6ff;
            --page-divider: #555;
        }
        
        /* 세피아 모드 */
        .sepia-mode {
            --bg-color: #f4ecd8;
            --text-color: #5c4b37;
            --reader-bg: #f9f3e3;
            --toolbar-bg: #efe6d5;
            --border-color: #d4c4a8;
            --progress-bg: #e0d5c1;
            --progress-fill: #8b7355;
            --page-divider: #c9b896;
        }
        
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: var(--bg-color);
            color: var(--text-color);
            transition: background 0.3s, color 0.3s;
        }
        
        body {
            font-family: <?php echo $txt_font_family; ?>;
        }
        
        /* 툴바 */
        .txt-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--toolbar-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 10px 15px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .txt-toolbar .title {
            font-weight: bold;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 300px;
        }
        
        .txt-toolbar .controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }
        
        .txt-toolbar .btn {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .txt-toolbar select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        /* 진행 바 */
        .progress-bar-container {
            position: fixed;
            top: 55px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--progress-bg);
            z-index: 999;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: var(--progress-fill);
            width: 0%;
            transition: width 0.2s;
        }
        
        /* 리더 영역 - 기본 1단 */
        .txt-reader {
            padding: 80px 20px 60px 20px;
            max-width: 800px;
            margin: 0 auto;
            background: var(--reader-bg);
            min-height: 100vh;
            box-sizing: border-box;
        }
        
        .txt-content-wrapper {
            height: auto;
        }
        
        .txt-content {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 18px;
            line-height: 1.8;
            font-family: <?php echo $txt_font_family; ?>;
        }
        
        /* ============================================================
         * 페이지 레이아웃 모드 (2단 - 책 보기)
         * 두 개의 별도 div로 컬럼 구현 (CSS column 대신)
         * ============================================================ */
        
        /* 2단 모드 */
        .txt-reader.layout-dual {
            max-width: 100%;
            padding: 70px 60px 70px 60px; /* 좌우 여백 늘리고 상하 줄임 */
            height: 100vh;
            box-sizing: border-box;
            overflow: hidden;
        }
        
        .txt-reader.layout-dual .txt-content-wrapper {
            height: calc(100vh - 140px); /* 상단 70 + 하단 70 */
            overflow: hidden;
        }
        
        /* 1단 콘텐츠 숨기기 (2단 모드) */
        .txt-reader.layout-dual .txt-content {
            display: none;
        }
        
        /* 2단 컬럼 컨테이너 */
        .dual-columns {
            display: none;
            gap: 50px;
            height: 100%;
        }
        
        .txt-reader.layout-dual .dual-columns {
            display: flex !important;
        }
        
        .column-text {
            flex: 1;
            overflow: hidden;
            white-space: pre-line; /* 줄바꿈은 유지하되 연속 공백은 하나로 */
            word-wrap: break-word;
            word-break: keep-all; /* 한글 단어 중간에서 안 끊기게 */
        }
        
        /* 2단 좌→우 (1|2) */
        .txt-reader.layout-1-2 .dual-columns {
            flex-direction: row;
        }
        
        /* 2단 우→좌 (2|1) - 만화책 스타일 */
        .txt-reader.layout-2-1 .dual-columns {
            flex-direction: row-reverse;
        }
        
        /* 컬럼 구분선 */
        .txt-reader.layout-dual .column-text:first-child {
            border-right: 1px solid var(--page-divider);
            padding-right: 25px;
        }
        
        .txt-reader.layout-dual .column-text:last-child {
            padding-left: 25px;
        }
        
        /* 페이지 네비게이션 (2단 모드용) - 양쪽 화살표 */
        .page-nav {
            display: none;
        }
        
        .page-nav.show {
            display: block;
        }
        
        /* 양쪽 화살표 버튼 */
        .page-arrow {
            display: none;
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 120px;
            background: rgba(0, 0, 0, 0.3);
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            z-index: 999;
            opacity: 0.6;
            transition: opacity 0.2s, background 0.2s;
            align-items: center;
            justify-content: center;
        }
        
        .page-arrow:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.6);
        }
        
        .page-arrow:disabled {
            opacity: 0.2;
            cursor: not-allowed;
        }
        
        .page-arrow.left {
            left: 0;
            border-radius: 0 10px 10px 0;
        }
        
        .page-arrow.right {
            right: 0;
            border-radius: 10px 0 0 10px;
        }
        
        .page-nav.show .page-arrow {
            display: flex;
        }
        
        /* 페이지 정보 (하단 중앙) */
        .page-info-bar {
            display: none;
            position: fixed;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 14px;
            z-index: 999;
            gap: 8px;
            align-items: center;
        }
        
        .page-nav.show .page-info-bar {
            display: flex;
        }
        
        .page-info-bar input {
            width: 55px;
            padding: 5px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            background: rgba(255,255,255,0.15);
            color: white;
            text-align: center;
            font-size: 14px;
        }
        
        .page-info-bar input:focus {
            outline: none;
            border-color: rgba(255,255,255,0.6);
            background: rgba(255,255,255,0.25);
        }
        
        /* 터치/클릭 영역 (2단 모드) - 양쪽 세로 전체 높이 */
        .page-touch-area {
            display: none;
            position: fixed;
            top: 60px;
            bottom: 40px;
            width: 15%;
            min-width: 50px;
            max-width: 100px;
            z-index: 100;
            cursor: pointer;
            background: transparent;
            transition: background 0.2s;
        }
        
        .page-touch-area:hover {
            background: rgba(0, 0, 0, 0.05);
        }
        
        .page-touch-area:active {
            background: rgba(0, 0, 0, 0.1);
        }
        
        body.theme-dark .page-touch-area:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        body.theme-dark .page-touch-area:active {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .page-touch-area.left {
            left: 0;
        }
        
        .page-touch-area.right {
            right: 0;
        }
        
        /* 세로 클릭 영역 내 아이콘 */
        .touch-area-icon {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            color: rgba(0, 0, 0, 0.3);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .page-touch-area:hover .touch-area-icon {
            opacity: 1;
        }
        
        body.theme-dark .touch-area-icon {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .page-touch-area.left .touch-area-icon {
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .page-touch-area.right .touch-area-icon {
            right: 50%;
            transform: translate(50%, -50%);
        }
        
        /* 로딩 인디케이터 */
        .loading-indicator {
            text-align: center;
            padding: 20px;
            color: #888;
        }
        
        .loading-indicator.hidden {
            display: none;
        }
        
        /* 하단 정보 바 */
        .txt-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--toolbar-bg);
            border-top: 1px solid var(--border-color);
            padding: 8px 15px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* 설정 패널 */
        .settings-panel {
            position: fixed;
            top: 60px;
            right: 10px;
            background: var(--toolbar-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            width: 280px;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
            z-index: 1001;
            display: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .settings-panel.show {
            display: block;
        }
        
        .settings-panel label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .settings-panel input[type="range"] {
            width: 100%;
        }
        
        .settings-panel select {
            width: 100%;
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            color: var(--text-color);
            font-size: 14px;
        }
        
        .settings-row {
            margin-bottom: 15px;
        }
        
        .settings-row:last-child {
            margin-bottom: 0;
        }
        
        .settings-divider {
            border-top: 1px solid var(--border-color);
            margin: 15px 0;
        }
        
        .settings-section-title {
            font-weight: bold;
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        
        /* 모바일 */
        @media (max-width: 767px) {
            .txt-toolbar .title {
                max-width: 150px;
            }
            
            .txt-reader {
                padding: 70px 15px 50px 15px;
            }
            
            .txt-content {
                font-size: 16px;
            }
            
            .settings-panel {
                width: calc(100% - 20px);
                right: 10px;
                left: 10px;
            }
            
            /* 모바일에서 2단 모드 선택 시 1컬럼 페이지 모드로 */
            .txt-reader.layout-dual {
                height: 100vh;
                overflow: hidden;
                padding: 60px 20px 80px 20px;
            }
            
            .txt-reader.layout-dual .txt-content-wrapper {
                height: calc(100vh - 140px);
                overflow: hidden;
            }
            
            .txt-reader.layout-dual .txt-content {
                display: none !important;
            }
            
            /* 모바일에서 1컬럼만 표시 */
            .txt-reader.layout-dual .dual-columns {
                display: flex !important;
                flex-direction: column;
            }
            
            .txt-reader.layout-dual .column-text:first-child {
                display: block;
                border-right: none;
                padding-right: 0;
            }
            
            .txt-reader.layout-dual .column-text:last-child {
                display: none; /* 오른쪽 컬럼 숨김 */
                padding-left: 0;
            }
            
            /* 모바일 화살표 크기 조정 */
            .page-arrow {
                width: 40px;
                height: 80px;
                font-size: 20px;
            }
            
            .page-info-bar {
                bottom: 10px;
                padding: 8px 15px;
                font-size: 12px;
            }
            
            .page-info-bar input {
                width: 45px;
                padding: 4px;
                font-size: 12px;
            }
            
            .page-touch-area {
                display: none !important;
            }
        }
        
        /* 태블릿 이상에서만 2단 적용 */
        @media (min-width: 768px) {
            .txt-reader.layout-dual {
                max-width: 100%;
            }
        }
        
        /* 큰 화면에서 2단 최적화 */
        @media (min-width: 1200px) {
            .txt-reader.layout-dual {
                padding: 80px 60px 100px 60px;
            }
            
            .txt-reader.layout-dual .txt-content {
                column-gap: 80px;
            }
        }
    </style>
    <script>document.documentElement.classList.add('ready');</script>
<?php
render_viewer_i18n([
    'txt_no_content' => 'js_txt_no_content',
    'txt_autoscroll_single' => 'js_txt_autoscroll_single',
]);
?>
</head>
<body class="<?php echo h($darkClass); ?>">

<!-- 툴바 -->
<div class="txt-toolbar">
    <a href="index.php?dir=<?php echo u($link_dir) . $bidx_param; ?>" class="btn btn-outline-secondary btn-sm">← <?php echo __h("back"); ?></a>
    <span class="title"><?php echo h($title); ?></span>
    <?php render_lang_badge('sm'); ?>
    
    <div class="controls">
        <button id="btnTheme" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('epub_theme'); ?>">
            🌓
        </button>
        <button id="btnSettings" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('common_settings'); ?>">
            ⚙️
        </button>
    </div>
</div>

<!-- 진행 바 -->
<div class="progress-bar-container">
    <div class="progress-bar-fill" id="progressFill"></div>
</div>

<!-- 설정 패널 -->
<div class="settings-panel" id="settingsPanel">
    <!-- 테마 설정 -->
    <div class="settings-section-title">🎨 <?php echo __h("epub_theme"); ?></div>
    <div class="settings-row">
        <label for="themeSelect"><?php echo __h("txt_display_mode"); ?></label>
        <select id="themeSelect">
            <option value="light">☀️ <?php echo __h("epub_theme_light"); ?></option>
            <option value="sepia">📜 <?php echo __h("epub_theme_sepia"); ?></option>
            <option value="dark">🌙 <?php echo __h("epub_theme_dark"); ?></option>
        </select>
    </div>
    
    <div class="settings-divider"></div>
    
    <!-- 레이아웃 설정 -->
    <div class="settings-section-title">📖 <?php echo __h("txt_page_layout"); ?></div>
    <div class="settings-row">
        <label for="layoutSelect"><?php echo __h("txt_view_mode"); ?></label>
        <select id="layoutSelect">
            <option value="single"><?php echo __h("txt_single"); ?></option>
            <option value="dual-ltr"><?php echo __h("txt_dual_ltr"); ?></option>
            <option value="dual-rtl"><?php echo __h("txt_dual_rtl"); ?></option>
        </select>
        <small style="color:#888; display:block; margin-top:5px;"><?php echo __h("txt_dual_note"); ?></small>
    </div>
    <div class="settings-row" id="reverseNavRow" style="display:none;">
        <label>
            <input type="checkbox" id="chkReverseNav" <?php echo ($_COOKIE['txt_reverse_nav'] ?? '') === '1' ? 'checked' : ''; ?>>
            <?php echo __h("txt_reverse_nav"); ?>
        </label>
        <small style="color:#888; display:block; margin-top:5px;">◀ <?php echo __h("js_prev"); ?>/<?php echo __h("js_next"); ?> ▶ ↔ ◀ <?php echo __h("js_next"); ?>/<?php echo __h("js_prev"); ?> ▶</small>
    </div>
    
    <div class="settings-divider"></div>
    
    <!-- 텍스트 설정 -->
    <div class="settings-section-title">📝 <?php echo __h("txt_text_settings"); ?></div>
    <div class="settings-row">
        <label><?php echo __h("txt_font_size"); ?> <span id="fontSizeValue"><?php echo $txt_settings['default_font_size']; ?></span>px</label>
        <input type="range" id="fontSizeSlider" min="12" max="32" value="<?php echo $txt_settings['default_font_size']; ?>">
    </div>
    <div class="settings-row">
        <label><?php echo __h("txt_line_height"); ?> <span id="lineHeightValue"><?php echo $txt_settings['default_line_height']; ?></span></label>
        <input type="range" id="lineHeightSlider" min="1.0" max="3.0" step="0.1" value="<?php echo $txt_settings['default_line_height']; ?>">
    </div>
    
    <div class="settings-divider"></div>
    
    <!-- 자동 스크롤 -->
    <div class="settings-section-title">⏬ <?php echo __h("txt_auto_scroll"); ?></div>
    <div class="settings-row">
        <label>
            <input type="checkbox" id="autoscrollCheck"> <?php echo __h("txt_autoscroll_enable"); ?>
        </label>
    </div>
    <div class="settings-row" id="autoscrollSpeedRow" style="display:none;">
        <label><?php echo __h("txt_scroll_speed"); ?> <span id="scrollSpeedValue">3</span></label>
        <input type="range" id="scrollSpeedSlider" min="1" max="10" step="1" value="3">
        <small style="color:#666; display:block; margin-top:5px;"><?php echo __h("txt_speed_range"); ?></small>
    </div>
</div>

<!-- 리더 영역 -->
<div class="txt-reader" id="txtReader">
    <div class="txt-content-wrapper" id="txtContentWrapper">
        <div class="txt-content" id="txtContent"></div>
        <!-- 2단 모드용 컬럼 -->
        <div class="dual-columns" id="dualColumns" style="display:none;">
            <div class="column-text" id="columnLeft"></div>
            <div class="column-text" id="columnRight"></div>
        </div>
    </div>
    <div class="loading-indicator" id="loadingIndicator">
        📖 <?php echo __h("ui_loading"); ?>...
    </div>
</div>

<!-- 터치 영역 (2단 모드용) - 양쪽 세로 전체 높이 -->
<div class="page-touch-area left" id="touchLeft">
    <span class="touch-area-icon">◀</span>
</div>
<div class="page-touch-area right" id="touchRight">
    <span class="touch-area-icon">▶</span>
</div>

<!-- 페이지 네비게이션 (2단 모드용) - 양쪽 화살표 -->
<div class="page-nav" id="pageNav">
    <button class="page-arrow left" id="btnPrevPage" title="<?php echo __h('txt_prev_page'); ?>">◀</button>
    <button class="page-arrow right" id="btnNextPage" title="<?php echo __h('txt_next_page'); ?>">▶</button>
    <div class="page-info-bar">
        <input type="number" id="pageJumpInput" min="1" value="1" title="<?php echo __h('txt_page_jump'); ?>"> 
        <span>/ <span id="totalPages">1</span></span>
    </div>
</div>

<!-- 하단 정보 바 -->
<div class="txt-footer">
    <span id="fileInfo"><?php echo h(human_filesize($filesize)); ?> | <?php echo h($encoding); ?></span>
    <span id="readProgress">0%</span>
</div>

<script>
(function() {
    const FILE = <?php echo js($getfile); ?>;
    const CHUNK_SIZE = <?php echo $txt_settings['chunk_size']; ?>;
    const SAVED_POSITION = <?php echo (int)$saved_position; ?>;
    const SAVED_PERCENT = <?php echo (float)$saved_percent; ?>;
    const BIDX = <?php echo (int)$current_bidx; ?>;
    
    let isRestoring = true;  // 복원 중 저장 방지 플래그
    
    const contentEl = document.getElementById('txtContent');
    const contentWrapper = document.getElementById('txtContentWrapper');
    const readerEl = document.getElementById('txtReader');
    const loadingEl = document.getElementById('loadingIndicator');
    const progressFill = document.getElementById('progressFill');
    const readProgress = document.getElementById('readProgress');
    const pageNav = document.getElementById('pageNav');
    const touchLeft = document.getElementById('touchLeft');
    const touchRight = document.getElementById('touchRight');
    
    // 2단 모드용 컬럼 요소
    const dualColumns = document.getElementById('dualColumns');
    const columnLeft = document.getElementById('columnLeft');
    const columnRight = document.getElementById('columnRight');
    
    let currentOffset = 0;
    let fileSize = 0;
    let isLoading = false;
    let allLoaded = false;
    
    // 페이지 레이아웃 관련
    let currentLayout = 'single';
    let currentPage = 0;
    let totalPages = 1;
    
    // 좌우반전 기능
    let isReversed = <?php echo ($_COOKIE['txt_reverse_nav'] ?? '') === '1' ? 'true' : 'false'; ?>;
    const btnPrevPage = document.getElementById('btnPrevPage');
    const btnNextPage = document.getElementById('btnNextPage');
    const reverseNavRow = document.getElementById('reverseNavRow');
    const chkReverseNav = document.getElementById('chkReverseNav');
    
    // 1단 모드용 (스크롤 방식)
    let fullContent = '';
    let currentCharIndex = 0; // 현재 문자 인덱스 (전역)
    
    // 2단 모드용 (페이지 방식) - fullContent 기반 슬라이싱
    let charsPerPage = 3000;      // 페이지당 문자 수 (동적 조정)
    let isPageLoading = false;
    
    // 초기 메타데이터 로드
    async function loadMeta() {
        const res = await fetch(`txt_viewer.php?action=get_meta&file=${encodeURIComponent(FILE)}&bidx=${BIDX}`);
        const data = await res.json();
        if (data.error) {
            contentEl.innerHTML = `<p style="color:red">${data.error}</p>`;
            return false;
        }
        fileSize = data.filesize;
        return true;
    }
    
    // ============================================================
    // 1단 모드: 기존 청크 로딩 (스크롤 방식)
    // ============================================================
    async function loadChunk() {
        if (isLoading || allLoaded) return;
        
        isLoading = true;
        loadingEl.classList.remove('hidden');
        
        try {
            const res = await fetch(`txt_viewer.php?action=get_chunk&file=${encodeURIComponent(FILE)}&offset=${currentOffset}&length=${CHUNK_SIZE}&bidx=${BIDX}`);
            const data = await res.json();
            
            if (data.error) {
                contentEl.innerHTML = `<p style="color:red">${data.error}</p>`;
                isLoading = false;
                return;
            }
            
            if (data.content) {
                fullContent += data.content;
                if (currentLayout === 'single') {
                    contentEl.textContent = fullContent;
                }
            }
            
            currentOffset = data.offset;
            
            if (currentLayout === 'single') {
                progressFill.style.width = data.progress + '%';
            }
            
            if (!data.hasMore) {
                allLoaded = true;
                loadingEl.classList.add('hidden');
            }
        } catch (e) {
            console.error('청크 로드 오류:', e);
        }
        
        isLoading = false;
    }
    
    // ============================================================
    // 2단 모드: 고정 문자 수 + 실제 측정 표시
    // ============================================================
    
    // 페이지당 문자 수
    function calculateCharsPerPage() {
        return window.innerWidth < 768 ? 500 : 1500;
    }
    
    // 총 페이지 수 계산
    function calculateTotalPages() {
        charsPerPage = calculateCharsPerPage();
        const avgBytesPerChar = 2.5;
        const estimatedTotalChars = Math.ceil(fileSize / avgBytesPerChar);
        totalPages = Math.max(1, Math.ceil(estimatedTotalChars / charsPerPage));
        document.getElementById('totalPages').textContent = totalPages;
        return totalPages;
    }
    
    // 컬럼에 들어가는 문자 수 측정 (overflow 지점 찾기)
    function measureColumnCapacity(columnEl, text) {
        if (!text || text.length === 0) return 0;
        
        const columnHeight = columnEl.offsetHeight;
        if (columnHeight <= 0) return text.length;
        
        // 일단 전체 넣어보기
        columnEl.textContent = text;
        if (columnEl.scrollHeight <= columnHeight + 2) {
            return text.length; // 다 들어감
        }
        
        // Binary search로 들어가는 양 찾기
        let low = 0;
        let high = text.length;
        let result = 0;
        
        while (low <= high) {
            const mid = Math.floor((low + high) / 2);
            columnEl.textContent = text.substring(0, mid);
            
            if (columnEl.scrollHeight > columnHeight + 2) {
                high = mid - 1;
            } else {
                result = mid;
                low = mid + 1;
            }
        }
        
        return result;
    }
    
    // 필요한 만큼 콘텐츠 로드
    async function ensureContentLoaded(targetChars) {
        while (fullContent.length < targetChars && !allLoaded) {
            await loadChunk();
        }
    }
    
    // 페이지 로드 및 표시 (바로 점프)
    async function loadPage(pageNum) {
        if (isPageLoading) return;
        
        isPageLoading = true;
        
        if (pageNum < 0) pageNum = 0;
        
        // 해당 페이지에 필요한 콘텐츠 로드 (추정)
        const neededChars = (pageNum + 2) * charsPerPage;
        await ensureContentLoaded(neededChars);
        
        // 페이지 표시
        displayPage(pageNum);
        
        isPageLoading = false;
    }
    
    // 페이지 표시 (charsPerPage 기반 + 실제 측정)
    function displayPage(pageNum) {
        // 이 페이지의 시작 문자 인덱스 (단순 계산)
        const startChar = pageNum * charsPerPage;
        
        // 충분한 텍스트 가져오기
        const availableText = fullContent.substring(startChar);
        
        if (!availableText || availableText.length === 0) {
            columnLeft.textContent = _vi18n.txt_no_content;
            columnRight.textContent = '';
            currentPage = pageNum;
            updatePageDisplay();
            return;
        }
        
        // 스타일 동기화
        const fontSize = getComputedStyle(contentEl).fontSize;
        const lineHeight = getComputedStyle(contentEl).lineHeight;
        columnLeft.style.fontSize = fontSize;
        columnLeft.style.lineHeight = lineHeight;
        columnRight.style.fontSize = fontSize;
        columnRight.style.lineHeight = lineHeight;
        
        if (window.innerWidth < 768) {
            // 모바일: 1컬럼
            const leftCapacity = measureColumnCapacity(columnLeft, availableText);
            columnLeft.textContent = availableText.substring(0, leftCapacity);
            columnRight.textContent = '';
        } else {
            // PC: 2컬럼 - 왼쪽 먼저 채우고 나머지 오른쪽
            const leftCapacity = measureColumnCapacity(columnLeft, availableText);
            columnLeft.textContent = availableText.substring(0, leftCapacity);
            
            const remainingText = availableText.substring(leftCapacity);
            const rightCapacity = measureColumnCapacity(columnRight, remainingText);
            columnRight.textContent = remainingText.substring(0, rightCapacity);
        }
        
        currentPage = pageNum;
        updatePageDisplay();
    }
    
    // 페이지 표시 업데이트
    function updatePageDisplay() {
        const pageInput = document.getElementById('pageJumpInput');
        pageInput.value = currentPage + 1;
        pageInput.max = totalPages;
        
        document.getElementById('btnPrevPage').disabled = currentPage <= 0;
        document.getElementById('btnNextPage').disabled = currentPage >= totalPages - 1 && allLoaded;
        
        // 전역 currentCharIndex 업데이트 (실제 표시된 위치 기준)
        currentCharIndex = getDualModeCharIndex();
        
        // 진행률 = 현재 문자 / 전체 파일 예상 문자
        const estimatedTotalChars = Math.ceil(fileSize / 2.5);
        let percent = estimatedTotalChars > 0 ? Math.round((currentCharIndex / estimatedTotalChars) * 100) : 0;
        percent = Math.min(percent, 100);
        
        readProgress.textContent = percent + '%';
        progressFill.style.width = percent + '%';
    }
    
    // 페이지 이동
    function goToPage(page, animate = true) {
        if (page < 0) page = 0;
        if (page >= totalPages) page = totalPages - 1;
        if (page === currentPage) return;
        
        loadPage(page);
    }
    
    // 다음/이전 페이지 (데이터 순서 기준)
    function nextPage() {
        goToPage(currentPage + 1);
    }
    
    function prevPage() {
        goToPage(currentPage - 1);
    }
    
    // 좌/우 클릭 동작 (레이아웃 + 좌우반전 모두 적용)
    function goLeft() {
        // 기본: 왼쪽 = 이전
        // dual-rtl: 왼쪽 = 다음
        // 좌우반전: 위 결과 반대
        let action = (currentLayout === 'dual-rtl') ? 'next' : 'prev';
        if (isReversed) {
            action = (action === 'next') ? 'prev' : 'next';
        }
        if (action === 'next') {
            nextPage();
        } else {
            prevPage();
        }
    }
    
    function goRight() {
        // 기본: 오른쪽 = 다음
        // dual-rtl: 오른쪽 = 이전
        // 좌우반전: 위 결과 반대
        let action = (currentLayout === 'dual-rtl') ? 'prev' : 'next';
        if (isReversed) {
            action = (action === 'next') ? 'prev' : 'next';
        }
        if (action === 'next') {
            nextPage();
        } else {
            prevPage();
        }
    }
    
    // 좌우반전 체크박스 이벤트
    chkReverseNav.addEventListener('change', (e) => {
        isReversed = e.target.checked;
        document.cookie = `txt_reverse_nav=${isReversed ? '1' : '0'}; path=/; max-age=31536000`;
    });
    
    // 레이아웃 변경
    function setLayout(layout) {
        const prevLayout = currentLayout;
        
        // 전환 직전 현재 위치 정확히 저장
        let savedCharIndex = 0;
        let searchText = '';  // 2단→1단 전환용
        
        if (prevLayout === 'single') {
            // 1단: getVisibleCharIndex()로 정확한 위치 가져오기
            savedCharIndex = getVisibleCharIndex();
        } else {
            // 2단: 실제 표시된 텍스트 저장 (레이아웃 변경 전!)
            searchText = columnLeft.textContent.substring(0, 30);
            savedCharIndex = getDualModeCharIndex();
        }
        
        currentLayout = layout;
        
        // 클래스 초기화
        readerEl.classList.remove('layout-dual', 'layout-1-2', 'layout-2-1');
        contentEl.style.transform = '';
        contentEl.style.transition = '';
        
        // 터치 영역 숨기기
        touchLeft.style.display = 'none';
        touchRight.style.display = 'none';
        
        // 컬럼 요소 초기화
        dualColumns.style.display = 'none';
        contentEl.style.display = '';
        
        if (layout === 'single') {
            // 기본 1단
            pageNav.classList.remove('show');
            document.body.style.overflow = '';
            reverseNavRow.style.display = 'none';  // 좌우반전 옵션 숨김
            
            // 1단 모드: 기존 로드된 내용 표시
            contentEl.textContent = fullContent;
            
            // 2단에서 1단으로 전환: 텍스트 검색으로 정확한 위치 찾기
            if (prevLayout !== 'single' && searchText.length > 0) {
                requestAnimationFrame(() => {
                    // savedCharIndex 근처에서만 검색 (±5000자)
                    const searchStart = Math.max(0, savedCharIndex - 5000);
                    const searchEnd = Math.min(fullContent.length, savedCharIndex + 5000);
                    const searchArea = fullContent.substring(searchStart, searchEnd);
                    const localIndex = searchArea.indexOf(searchText);
                    
                    let targetCharIndex = savedCharIndex;  // 기본값
                    if (localIndex >= 0) {
                        targetCharIndex = searchStart + localIndex;
                    }
                    
                    const textNode = contentEl.firstChild;
                    if (textNode && textNode.nodeType === Node.TEXT_NODE && targetCharIndex > 0) {
                        const charIndex = Math.min(targetCharIndex, textNode.length - 1);
                        try {
                            const range = document.createRange();
                            range.setStart(textNode, charIndex);
                            range.setEnd(textNode, Math.min(charIndex + 1, textNode.length));
                            const rect = range.getBoundingClientRect();
                            window.scrollTo(0, Math.max(0, window.pageYOffset + rect.top - 50));
                        } catch (e) {
                            // fallback: 비율 기반
                            const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
                            const localRatio = fullContent.length > 0 ? targetCharIndex / fullContent.length : 0;
                            window.scrollTo(0, Math.max(0, scrollHeight * localRatio));
                        }
                    }
                    
                    // 스크롤 후 실제 보이는 위치로 currentCharIndex 업데이트
                    setTimeout(() => {
                        currentCharIndex = getVisibleCharIndex();
                        
                        // 진행률 표시
                        const estimatedTotalChars = Math.ceil(fileSize / 2.5);
                        const percent = estimatedTotalChars > 0 ? Math.round((currentCharIndex / estimatedTotalChars) * 100) : 0;
                        progressFill.style.width = Math.min(percent, 100) + '%';
                        readProgress.textContent = Math.min(percent, 100) + '%';
                    }, 50);
                });
            }
            
        } else {
            // 2단 모드 (PC) 또는 1컬럼 페이지 모드 (모바일)
            readerEl.classList.add('layout-dual');
            reverseNavRow.style.display = 'block';  // 좌우반전 옵션 표시
            
            if (layout === 'dual-ltr') {
                readerEl.classList.add('layout-1-2');
            } else if (layout === 'dual-rtl') {
                readerEl.classList.add('layout-2-1');
            }
            
            // 페이지 네비게이션 표시 (PC/모바일 모두)
            pageNav.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // 2단 컬럼 표시, 1단 콘텐츠 숨기기
            dualColumns.style.display = 'flex';
            contentEl.style.display = 'none';
            
            // PC에서만 터치 영역 표시
            if (window.innerWidth >= 768) {
                touchLeft.style.display = 'block';
                touchRight.style.display = 'block';
            }
            
            // 2단↔2단 전환 (방향만 변경): 현재 페이지 유지
            if (prevLayout !== 'single') {
                // 페이지 재계산 없이 현재 페이지 다시 표시
                displayPage(currentPage);
            } else {
                // 1단→2단 전환: 페이지 계산 필요
                calculateTotalPages();
                
                // savedCharIndex 위치의 텍스트 조각 저장 (보정용)
                const targetText = fullContent.substring(savedCharIndex, savedCharIndex + 30);
                
                const targetPage = charsPerPage > 0 ? Math.floor(savedCharIndex / charsPerPage) : 0;
                loadPage(targetPage);
                
                // 페이지 로드 후 보정
                setTimeout(() => {
                    const leftText = columnLeft.textContent;
                    // 대상 텍스트가 현재 페이지에 있는지 확인
                    if (targetText && !leftText.includes(targetText.substring(0, 15))) {
                        // 정확한 위치 다시 찾기
                        const actualCharIndex = getDualModeCharIndex();
                        if (Math.abs(actualCharIndex - savedCharIndex) > charsPerPage / 2) {
                            // 차이가 크면 페이지 재조정
                            const correctPage = charsPerPage > 0 ? Math.floor(savedCharIndex / charsPerPage) : 0;
                            if (correctPage !== currentPage) {
                                loadPage(Math.max(0, correctPage - 1));
                            }
                        }
                    }
                    // currentCharIndex 업데이트
                    currentCharIndex = getDualModeCharIndex();
                }, 100);
            }
        }
        
        // 레이아웃은 저장하지 않음 - 항상 1단으로 시작
    }
    
    // 진행률 서버 저장
    let saveProgressTimeout;
    function saveProgress(percent, position) {
        const data = new FormData();
        data.append('file', FILE);
        data.append('percent', percent);
        data.append('position', position);
        
        fetch(`txt_viewer.php?action=save_progress&bidx=${BIDX}`, {
            method: 'POST',
            body: data
        }).catch(() => {});
    }
    
    // 터치 영역 클릭 이벤트
    touchLeft.addEventListener('click', () => {
        if (currentLayout !== 'single') goLeft();
    });
    
    touchRight.addEventListener('click', () => {
        if (currentLayout !== 'single') goRight();
    });
    
    // 컬럼 클릭 이벤트 (2단 모드에서 페이지 넘기기)
    columnLeft.addEventListener('click', (e) => {
        if (currentLayout === 'single') return;
        // 클릭 위치가 왼쪽 절반이면 이전, 오른쪽 절반이면 다음
        const rect = columnLeft.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        if (clickX < rect.width / 2) {
            goLeft();
        } else {
            goRight();
        }
    });
    
    columnRight.addEventListener('click', (e) => {
        if (currentLayout === 'single') return;
        // 클릭 위치가 왼쪽 절반이면 이전, 오른쪽 절반이면 다음
        const rect = columnRight.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        if (clickX < rect.width / 2) {
            goLeft();
        } else {
            goRight();
        }
    });
    
    // 화면에 보이는 첫 번째 문자 인덱스 찾기 (1단 모드)
    function getVisibleCharIndex() {
        const textNode = contentEl.firstChild;
        if (!textNode || textNode.nodeType !== Node.TEXT_NODE) return 0;
        
        const rect = contentEl.getBoundingClientRect();
        // 화면에 보이는 콘텐츠 영역의 상단 좌표
        const x = rect.left + 20;  // 왼쪽 여백
        const y = Math.max(rect.top + 10, 60);  // 상단 또는 헤더 아래
        
        try {
            // caretRangeFromPoint: 특정 좌표에 있는 텍스트 위치 반환
            if (document.caretRangeFromPoint) {
                const range = document.caretRangeFromPoint(x, y);
                if (range && range.startContainer === textNode) {
                    return range.startOffset;
                }
            }
            // Firefox 대응
            if (document.caretPositionFromPoint) {
                const pos = document.caretPositionFromPoint(x, y);
                if (pos && pos.offsetNode === textNode) {
                    return pos.offset;
                }
            }
        } catch (e) {}
        
        // fallback: 스크롤 비율 기반
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (scrollHeight > 0 && fullContent.length > 0) {
            return Math.floor((scrollTop / scrollHeight) * fullContent.length);
        }
        return 0;
    }
    
    // 2단 모드에서 실제 표시된 시작 문자 인덱스 찾기
    function getDualModeCharIndex() {
        const leftText = columnLeft.textContent;
        if (!leftText || leftText.length === 0) return currentPage * charsPerPage;
        
        // 왼쪽 컬럼의 첫 30자로 검색
        const searchText = leftText.substring(0, Math.min(30, leftText.length));
        
        // 현재 페이지 근처에서만 검색 (오차 범위 ±2000자)
        const estimatedPos = currentPage * charsPerPage;
        const searchStart = Math.max(0, estimatedPos - 2000);
        const searchEnd = Math.min(fullContent.length, estimatedPos + 2000);
        const searchArea = fullContent.substring(searchStart, searchEnd);
        
        const localIndex = searchArea.indexOf(searchText);
        if (localIndex >= 0) {
            return searchStart + localIndex;
        }
        
        // 범위 내에서 못 찾으면 전체에서 검색
        const foundIndex = fullContent.indexOf(searchText);
        if (foundIndex >= 0) {
            return foundIndex;
        }
        
        // fallback: 페이지 기반 계산
        return estimatedPos;
    }
    
    // 스크롤 이벤트 (1단 모드)
    let scrollTimeout;
    window.addEventListener('scroll', () => {
        if (currentLayout !== 'single') return;
        
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const docHeight = document.documentElement.scrollHeight;
        const winHeight = window.innerHeight;
        const scrollHeight = docHeight - winHeight;
        
        // 현재 문자 위치 계산 (caretRangeFromPoint 사용)
        currentCharIndex = getVisibleCharIndex();
        
        // 진행률 = 현재 문자 / 전체 파일 예상 문자
        const estimatedTotalChars = Math.ceil(fileSize / 2.5);
        let percent = estimatedTotalChars > 0 ? Math.round((currentCharIndex / estimatedTotalChars) * 100) : 0;
        percent = Math.min(percent, 100);
        
        progressFill.style.width = percent + '%';
        readProgress.textContent = percent + '%';
        
        // 추가 로드 (하단 근처)
        if (scrollTimeout) clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            if (scrollTop + winHeight >= docHeight - 500) {
                loadChunk();
            }
        }, 100);
        
        // 진행률 서버 저장 - 복원 중에는 저장 안 함
        if (saveProgressTimeout) clearTimeout(saveProgressTimeout);
        if (!isRestoring) {
            saveProgressTimeout = setTimeout(() => {
                saveProgress(percent, currentCharIndex);
            }, 2000);
        }
    });
    
    // 현재 진행률 계산 함수
    function getCurrentProgress() {
        const estimatedTotalChars = Math.ceil(fileSize / 2.5);
        // 전역 currentCharIndex 사용 (스크롤/페이지 이동 시 이미 업데이트됨)
        const percent = estimatedTotalChars > 0 ? Math.round((currentCharIndex / estimatedTotalChars) * 100) : 0;
        return { percent: Math.min(percent, 100), position: currentCharIndex };
    }
    
    // 진행률 즉시 저장 (비콘) - 복원 중에는 저장 안 함
    function saveProgressBeacon() {
        if (isRestoring) return;
        const { percent, position } = getCurrentProgress();
        const data = new FormData();
        data.append('file', FILE);
        data.append('percent', percent);
        data.append('position', position);
        navigator.sendBeacon(`txt_viewer.php?action=save_progress&bidx=${BIDX}`, data);
    }
    
    // 페이지 떠날 때 진행률 저장 (여러 이벤트로 커버)
    window.addEventListener('beforeunload', saveProgressBeacon);
    window.addEventListener('pagehide', saveProgressBeacon);  // iOS Safari
    
    // 탭 숨김 시 저장 (모바일 탭 전환)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            saveProgressBeacon();
        }
    });
    
    // 테마 관리
    const themes = ['light', 'sepia', 'dark'];
    const themeIcons = { light: '☀️', sepia: '📜', dark: '🌙' };
    let currentTheme = 'light';
    
    function setTheme(theme) {
        const html = document.documentElement;
        const body = document.body;
        
        // 모든 테마 클래스 제거
        html.classList.remove('dark-mode', 'sepia-mode');
        body.classList.remove('dark-mode', 'sepia-mode');
        
        // 새 테마 적용
        if (theme === 'dark') {
            html.classList.add('dark-mode');
            body.classList.add('dark-mode');
        } else if (theme === 'sepia') {
            html.classList.add('sepia-mode');
            body.classList.add('sepia-mode');
        }
        
        currentTheme = theme;
        document.getElementById('themeSelect').value = theme;
        document.getElementById('btnTheme').textContent = themeIcons[theme];
        document.cookie = `darkmode=${theme}; path=/; max-age=31536000`;
        localStorage.setItem('txt_theme', theme);
    }
    
    // 테마 버튼 클릭 (순환)
    document.getElementById('btnTheme').addEventListener('click', () => {
        const currentIndex = themes.indexOf(currentTheme);
        const nextIndex = (currentIndex + 1) % themes.length;
        setTheme(themes[nextIndex]);
    });
    
    // 테마 드롭다운 변경
    document.getElementById('themeSelect').addEventListener('change', (e) => {
        setTheme(e.target.value);
    });
    
    // 설정 패널
    document.getElementById('btnSettings').addEventListener('click', () => {
        document.getElementById('settingsPanel').classList.toggle('show');
    });
    
    // 레이아웃 선택
    document.getElementById('layoutSelect').addEventListener('change', (e) => {
        setLayout(e.target.value);
    });
    
    // 글꼴 크기
    let fontChangeTimeout;
    document.getElementById('fontSizeSlider').addEventListener('input', (e) => {
        const size = e.target.value;
        contentEl.style.fontSize = size + 'px';
        document.getElementById('fontSizeValue').textContent = size;
        localStorage.setItem('txt_font_size', size);
        
        // 2단 모드면 페이지 재계산
        if (currentLayout !== 'single') {
            if (fontChangeTimeout) clearTimeout(fontChangeTimeout);
            fontChangeTimeout = setTimeout(() => {
                const currentCharIndex = currentPage * charsPerPage;
                calculateTotalPages();
                const targetPage = charsPerPage > 0 ? Math.floor(currentCharIndex / charsPerPage) : 0;
                loadPage(targetPage);
            }, 300);
        }
    });
    
    // 줄 간격
    let lineHeightChangeTimeout;
    document.getElementById('lineHeightSlider').addEventListener('input', (e) => {
        const height = e.target.value;
        contentEl.style.lineHeight = height;
        document.getElementById('lineHeightValue').textContent = height;
        localStorage.setItem('txt_line_height', height);
        
        // 2단 모드면 페이지 재계산
        if (currentLayout !== 'single') {
            if (lineHeightChangeTimeout) clearTimeout(lineHeightChangeTimeout);
            lineHeightChangeTimeout = setTimeout(() => {
                const currentCharIndex = currentPage * charsPerPage;
                calculateTotalPages();
                const targetPage = charsPerPage > 0 ? Math.floor(currentCharIndex / charsPerPage) : 0;
                loadPage(targetPage);
            }, 300);
        }
    });
    
    // 페이지 네비게이션 버튼
    btnPrevPage.addEventListener('click', goLeft);
    btnNextPage.addEventListener('click', goRight);
    
    // 페이지 점프 (입력 필드)
    const pageJumpInput = document.getElementById('pageJumpInput');
    pageJumpInput.addEventListener('change', (e) => {
        const page = parseInt(e.target.value) - 1;
        if (!isNaN(page)) {
            goToPage(page);
        }
    });
    pageJumpInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const page = parseInt(e.target.value) - 1;
            if (!isNaN(page)) {
                goToPage(page);
            }
            e.target.blur();
        }
    });
    
    // 키보드 네비게이션 (2단 모드)
    document.addEventListener('keydown', (e) => {
        if (currentLayout === 'single') return;
        if (window.innerWidth < 768) return;
        
        if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
            e.preventDefault();
            goLeft();
        } else if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') {
            e.preventDefault();
            goRight();
        } else if (e.key === 'Home') {
            e.preventDefault();
            goToPage(0);
        } else if (e.key === 'End') {
            e.preventDefault();
            goToPage(totalPages - 1);
        }
    });
    
    // 저장된 설정 복원
    const savedFontSize = localStorage.getItem('txt_font_size');
    const savedLineHeight = localStorage.getItem('txt_line_height');
    const savedTheme = localStorage.getItem('txt_theme') || '<?php echo ($darkmode === "dark" ? "dark" : ($darkmode === "sepia" ? "sepia" : "light")); ?>';
    
    // 테마 복원
    if (savedTheme) {
        setTheme(savedTheme);
    }
    
    if (savedFontSize) {
        contentEl.style.fontSize = savedFontSize + 'px';
        document.getElementById('fontSizeSlider').value = savedFontSize;
        document.getElementById('fontSizeValue').textContent = savedFontSize;
    }
    
    if (savedLineHeight) {
        contentEl.style.lineHeight = savedLineHeight;
        document.getElementById('lineHeightSlider').value = savedLineHeight;
        document.getElementById('lineHeightValue').textContent = savedLineHeight;
    }
    
    // 레이아웃은 항상 기본값(1단)으로 시작 - 드롭다운도 1단 유지
    // savedLayout은 사용하지 않음
    
    // ============================================================
    // 자동 스크롤 기능
    // ============================================================
    let autoScrollInterval = null;
    let autoScrollSpeed = 3; // 기본 속도 (1~10)
    
    const autoscrollCheck = document.getElementById('autoscrollCheck');
    const scrollSpeedSlider = document.getElementById('scrollSpeedSlider');
    const scrollSpeedValue = document.getElementById('scrollSpeedValue');
    const autoscrollSpeedRow = document.getElementById('autoscrollSpeedRow');
    
    // 저장된 속도 불러오기
    const savedScrollSpeed = localStorage.getItem('txt_scroll_speed');
    if (savedScrollSpeed) {
        autoScrollSpeed = parseInt(savedScrollSpeed);
        scrollSpeedSlider.value = autoScrollSpeed;
        scrollSpeedValue.textContent = autoScrollSpeed;
    }
    
    // 자동 스크롤 시작/정지
    function startAutoScroll() {
        if (autoScrollInterval || currentLayout !== 'single') return;
        
        // 속도에 따른 스크롤 양과 간격 계산
        // 속도 1: 50ms마다 1px, 속도 10: 50ms마다 5px
        const pixelPerTick = Math.max(1, Math.round(autoScrollSpeed / 2));
        
        autoScrollInterval = setInterval(() => {
            window.scrollBy(0, pixelPerTick);
            
            // 끝에 도달하면 정지
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight;
            const clientHeight = document.documentElement.clientHeight;
            
            if (scrollTop + clientHeight >= scrollHeight - 10) {
                stopAutoScroll();
                autoscrollCheck.checked = false;
            }
        }, 50);
    }
    
    function stopAutoScroll() {
        if (autoScrollInterval) {
            clearInterval(autoScrollInterval);
            autoScrollInterval = null;
        }
    }
    
    // 체크박스 이벤트
    autoscrollCheck.addEventListener('change', (e) => {
        if (e.target.checked) {
            if (currentLayout !== 'single') {
                alert(_vi18n.txt_autoscroll_single);
                e.target.checked = false;
                return;
            }
            autoscrollSpeedRow.style.display = 'block';
            startAutoScroll();
        } else {
            autoscrollSpeedRow.style.display = 'none';
            stopAutoScroll();
        }
    });
    
    // 속도 조절 이벤트
    scrollSpeedSlider.addEventListener('input', (e) => {
        autoScrollSpeed = parseInt(e.target.value);
        scrollSpeedValue.textContent = autoScrollSpeed;
        localStorage.setItem('txt_scroll_speed', autoScrollSpeed);
        
        // 자동 스크롤 중이면 속도 변경 적용
        if (autoScrollInterval) {
            stopAutoScroll();
            startAutoScroll();
        }
    });
    
    // 수동 스크롤 시 자동 스크롤 정지
    window.addEventListener('wheel', () => {
        if (autoScrollInterval) {
            stopAutoScroll();
            autoscrollCheck.checked = false;
            autoscrollSpeedRow.style.display = 'none';
        }
    });
    
    window.addEventListener('touchmove', () => {
        if (autoScrollInterval) {
            stopAutoScroll();
            autoscrollCheck.checked = false;
            autoscrollSpeedRow.style.display = 'none';
        }
    });
    
    // 윈도우 리사이즈 시 페이지 재계산
    let resizeTimeout;
    window.addEventListener('resize', () => {
        if (resizeTimeout) clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            if (currentLayout !== 'single') {
                // 현재 문자 위치 저장
                const currentCharIndex = currentPage * charsPerPage;
                
                // 페이지 재계산
                calculateTotalPages();
                
                // PC에서만 터치 영역 표시
                if (window.innerWidth >= 768) {
                    touchLeft.style.display = 'block';
                    touchRight.style.display = 'block';
                } else {
                    touchLeft.style.display = 'none';
                    touchRight.style.display = 'none';
                }
                
                // 문자 인덱스로 페이지 계산
                const targetPage = charsPerPage > 0 ? Math.floor(currentCharIndex / charsPerPage) : 0;
                loadPage(targetPage);
            }
        }, 200);
    });
    
    // 초기화
    async function init() {
        const ok = await loadMeta();
        if (!ok) return;
        
        // 첫 청크 로드
        await loadChunk();
        await loadChunk();
        
        // 저장된 위치로 복원 (position 기반)
        if (SAVED_POSITION > 0) {
            // 저장된 위치까지 콘텐츠 로드
            const neededChars = SAVED_POSITION + 5000;
            await ensureContentLoaded(neededChars);
            contentEl.textContent = fullContent;
            
            // 전역 currentCharIndex 설정
            currentCharIndex = SAVED_POSITION;
            
            setTimeout(() => {
                // Range API로 해당 문자 위치로 스크롤
                const textNode = contentEl.firstChild;
                if (textNode && textNode.nodeType === Node.TEXT_NODE) {
                    const charIndex = Math.min(SAVED_POSITION, textNode.length - 1);
                    try {
                        const range = document.createRange();
                        range.setStart(textNode, charIndex);
                        range.setEnd(textNode, Math.min(charIndex + 1, textNode.length));
                        const rect = range.getBoundingClientRect();
                        window.scrollTo(0, Math.max(0, window.pageYOffset + rect.top - 50));
                    } catch (e) {
                        // fallback: 비율 기반
                        const charRatio = fullContent.length > 0 ? SAVED_POSITION / fullContent.length : 0;
                        const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
                        window.scrollTo(0, Math.max(0, scrollHeight * charRatio));
                    }
                }
                
                progressFill.style.width = SAVED_PERCENT + '%';
                readProgress.textContent = Math.round(SAVED_PERCENT) + '%';
            }, 100);
        } else {
            progressFill.style.width = '0%';
            readProgress.textContent = '0%';
        }
        
        // 복원 완료 후 저장 허용
        setTimeout(() => {
            isRestoring = false;
        }, 500);
    }
    
    init();
})();
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
// ✅ 자동 로그아웃 설정 로드
if (!isset($auto_logout_settings)) {
    $auto_logout_settings = ['enabled' => true, 'timeout' => 600];
}
$timeout = (int)($auto_logout_settings['timeout'] ?? 600);
$remaining = isset($_SESSION['last_action']) ? max(0, $timeout - (time() - $_SESSION['last_action'])) : $timeout;

// ✅ 현재 페이지가 적용 대상인지 확인
$_current_page = basename($_SERVER['SCRIPT_FILENAME']);
$_auto_logout_pages = $auto_logout_settings['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
$_is_target = in_array($_current_page, $_auto_logout_pages);

// ✅ "로그인 유지"로 로그인한 경우 자동 로그아웃 무시
$_is_remember_me = isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true;

// ✅ 전역 $base_path 사용 (bootstrap.php에서 설정됨)
global $base_path;
if (($auto_logout_settings['enabled'] ?? true) && $_is_target && !$_is_remember_me): 
?>
<script>
window.SESSION_TIMEOUT = <?php echo $timeout; ?>;
window.SESSION_REMAINING = <?php echo $remaining; ?>;
</script>
<script src="<?php echo $base_path; ?>/js/auto-logout.js?v=<?php echo @filemtime(__DIR__ . '/js/auto-logout.js') ?: '1'; ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/privacy_shield.php'; ?>
</body>
</html>