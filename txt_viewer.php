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
// ★ (2026-11-02 09:03) 화면 요청에서만 자동 로그아웃 안내(EPUB 뷰어와 같게) — 전에는 글 조각·위치 저장 요청이 안내를 가져가 JSON 이 깨지고 안내도 사라졌음
if (!isset($_GET['action'])) handle_timeout_popup();  // ✅ 자동 로그아웃 메시지 처리

// ✅ 다중 폴더 지원: URL 파라미터로 폴더 선택
$bidx = init_bidx();  // ✅ 항상 bidx 포함

// ★ (2026-10-30) 보안: 폴더 권한 확인(API·스트림 포함 모든 요청)
mc_file_param_guard(isset($_GET['action']) || isset($_POST['action']));
// ★ (2026-11-02 09:03) 로그인 정보 없는 세션(자동 로그아웃 직후)으로는 API 를 쓰지 않음 — EPUB 뷰어와 같음
if (isset($_GET['action']) && empty($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'login required']);
    exit;
}

// ★ (2026-10-31) 책 노트(형광펜·밑줄·메모·책갈피) API — action=notes_load / notes_save(CSRF), 계정별 src/<아이디>_notes.json
require_once __DIR__ . '/book_notes_lib.php';
mc_notes_handle_api('txt', $base_dir, $current_bidx);

// ✅ 브랜딩 설정 로드 (function.php의 공통 함수 사용)
$_branding = load_branding();

// 설정값
// ★ (2026-10-30) 기본값 위에 저장된 설정을 덮음 — 설정에 일부 키만 있으면(예: enabled 만) chunk_size 가 없어 fread(0) 치명 오류였음
$txt_settings = array_merge([
    'max_file_size' => 50 * 1024 * 1024,
    'chunk_size' => 100 * 1024,
    'default_font_size' => 18,
    'default_line_height' => 1.8,
    'encoding_detect' => true,
], is_array($txt_viewer_settings ?? null) ? $txt_viewer_settings : []);
if ((int)$txt_settings['chunk_size'] < 1024) $txt_settings['chunk_size'] = 100 * 1024;

// ============================================================
// AJAX 요청 처리: 청크 데이터 반환
// ============================================================

if (isset($_GET['action']) && $_GET['action'] === 'get_chunk') {
    header('Content-Type: application/json; charset=utf-8');
    
    $getfile = decode_file_param($_GET['file'] ?? '');
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    // ★ (2026-11-03 01:21) 목차·검색이 글 전체를 읽을 때(bulk=1)는 한 번에 2MB 까지 — 50MB 파일을 100KB 씩 500번 부르느라 오래 걸렸음(보통 읽기는 설정값 그대로)
    $_txt_cap = (($_GET['bulk'] ?? '') === '1') ? max((int)$txt_settings['chunk_size'], 2 * 1024 * 1024) : (int)$txt_settings['chunk_size'];
    $length = min($_txt_cap, max(1024, (int)($_GET['length'] ?? $txt_settings['chunk_size'])));
    
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
    
    // ★ (2026-10-30) 인코딩별로 글자 경계를 맞춰 자르고, 실제로 쓴 바이트만큼만 다음 위치를 옮김
    //   (전에는 UTF-8 규칙으로 CP949 한글 첫 바이트를 지우고 다음 위치도 잘못 계산해 청크 경계마다 글자가 빠지거나 겹쳤고,
    //    UTF-16(메모장 '유니코드') 파일은 전부 깨졌음)
    list($content, $consumed) = txt_decode_chunk($raw_content, $offset, detect_file_encoding($base_file));
    $next_offset = $offset + $consumed;
    
    echo json_encode([
        'content' => $content,
        'offset' => $next_offset,
        'hasMore' => $next_offset < $filesize,
        'filesize' => $filesize,
        'progress' => round(($next_offset / $filesize) * 100, 1)
    ], JSON_INVALID_UTF8_SUBSTITUTE);
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
    // ★ (2026-11-02 09:03) 배열이 오면 ltrim 이 500 — 빈 값·다른 사이트 요청은 저장하지 않음
    if (!is_string($getfile) || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') { echo json_encode(['success' => false]); exit; }
    $getfile = ltrim($getfile, '/');
    if ($getfile === '') { echo json_encode(['success' => false]); exit; }
    
    $percent = (float)($_POST['percent'] ?? 0);
    if (!is_finite($percent)) $percent = 0; // ★ (2026-10-30) INF·NAN 이면 json_encode 실패 → 저장 거부(전에는 진행률 파일 전체가 비워질 수 있었음)
    $position = (int)($_POST['position'] ?? 0);
    // ★ (2026-11-02 01:40) 그 글자 줄이 머리줄 아래에서 몇 px 위·아래였는지(1단 스크롤) — 다시 열면 화면이 그대로(예전엔 줄 맨 위로 맞춰 반 줄쯤 움직였음)
    $_yv = $_POST['yoff'] ?? null;
    $yoff = (is_string($_yv) && preg_match('/^-?\d{1,4}$/', $_yv)) ? max(-400, min(400, (int)$_yv)) : 0;
    
    $progress_file = get_txt_progress_file();
    $progress = load_json_with_lock($progress_file);
    
    // ★ (2026-11-06 00:50) 콘텐츠 폴더별 키("bidx|경로") — 예전 키(경로만)는 여기서 옮기며 지움(같은 경로의 다른 콘텐츠 폴더 책과 위치가 섞이던 것)
    $__pk = mc_prog_key($bidx, $getfile);
    unset($progress[$getfile]);
    $progress[$__pk] = [
        'percent' => $percent,
        'position' => $position,
        'time' => date('Y-m-d H:i:s')
    ];
    if ($yoff !== 0) $progress[$__pk]['yoff'] = $yoff;
    
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
    $getfile = ltrim(is_string($getfile) ? $getfile : '', '/'); // ★ (2026-11-02 09:03) 배열이면 500 이던 것
    
    // ✅ bootstrap.php의 함수 사용 (파일 잠금 적용)
    $progress_file = get_txt_progress_file();
    $progress = load_json_with_lock($progress_file);
    
    $saved = mc_prog_get($progress, $bidx, $getfile); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 것(없으면 예전 키)
    
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
    
    // ★ (2026-11-02 22:27) 표본(4096바이트) 끝이 글자 중간에서 잘리면 검사가 실패해 EUC-KR 파일이 'UTF-8'로 표시됐음 — 끝의 잘린 바이트(최대 3)를 빼고도 검사
    $__full = strlen($sample) >= 4096;
    //   (먼저 자르지 않은 그대로 UTF-8 → EUC-KR 순으로 보고, 둘 다 안 맞을 때만 끝을 잘라 다시 — 그대로 맞는 파일 판정은 예전과 같게)
    if (mb_check_encoding($sample, 'UTF-8') && preg_match('//u', $sample)) return 'UTF-8';
    if (mb_check_encoding($sample, 'EUC-KR')) return 'EUC-KR';
    if ($__full) {
        for ($__cut = 1; $__cut <= 3; $__cut++) {
            $__s = substr($sample, 0, -$__cut);
            if (mb_check_encoding($__s, 'UTF-8') && preg_match('//u', $__s)) return 'UTF-8';
        }
        if (mb_check_encoding(substr($sample, 0, -1), 'EUC-KR')) return 'EUC-KR';
    }
    // ★ (2026-11-09 11:39) 확장 한글(똠·뷁 등 CP949에만 있는 글자)이 든 파일은 EUC-KR 검사에 걸리지 않아 정보 줄에 'UTF-8'로 나왔음 — 위가 다 안 맞을 때만 CP949로 표시(본문 읽기는 원래 조각마다 CP949로 판정해 그대로)
    if (mb_check_encoding($sample, 'CP949') || ($__full && mb_check_encoding(substr($sample, 0, -1), 'CP949'))) return 'CP949';
    
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
 * ★ (2026-10-30) 청크 하나를 UTF-8 문자열로 바꾸고, 이번에 소비한 바이트 수를 돌려줌
 * @param string $raw 파일에서 읽은 바이트(앞은 항상 글자 경계 — 이전 청크가 정확한 위치를 돌려주므로)
 * @param int $offset 이 청크의 파일 내 시작 위치(0 이면 BOM 처리)
 * @param string $file_enc detect_file_encoding() 결과(BOM 으로 UTF-16 판정)
 * @return array [UTF-8 문자열, 소비한 바이트 수(> 0)]
 */
function txt_decode_chunk($raw, $offset, $file_enc) {
    $len = strlen($raw);
    if ($len === 0) return ['', 0];
    
    // UTF-16 (BOM 으로 판정된 파일)
    if ($file_enc === 'UTF-16LE' || $file_enc === 'UTF-16BE') {
        $le = ($file_enc === 'UTF-16LE');
        $skip = ($offset === 0 && $len >= 2) ? 2 : 0; // BOM
        $use = $len - ($len % 2);
        if ($use - $skip >= 2) {
            $u = $le ? ((ord($raw[$use - 1]) << 8) | ord($raw[$use - 2])) : ((ord($raw[$use - 2]) << 8) | ord($raw[$use - 1]));
            if ($u >= 0xD800 && $u <= 0xDBFF) $use -= 2; // 서로게이트 앞쪽만 있으면 다음 청크로
        }
        if ($use <= $skip) return ['', $len]; // 끝에 남은 짝 없는 바이트
        $out = @mb_convert_encoding(substr($raw, $skip, $use - $skip), 'UTF-8', $file_enc);
        return [is_string($out) ? $out : '', $use];
    }
    
    // UTF-8 시도: 앞쪽 연속 바이트·끝쪽 잘린 글자 제외
    $k = 0;
    while ($k < $len && (ord($raw[$k]) & 0xC0) === 0x80 && $k < 3) $k++;
    $m = 0;
    for ($i = 1; $i <= 4 && $i <= $len; $i++) {
        $byte = ord($raw[$len - $i]);
        if ($byte < 0x80) break;
        if (($byte & 0xC0) === 0x80) continue;
        $need = (($byte & 0xE0) === 0xC0) ? 2 : ((($byte & 0xF0) === 0xE0) ? 3 : ((($byte & 0xF8) === 0xF0) ? 4 : 0));
        if ($need > 0 && $i < $need) $m = $i;
        break;
    }
    $cand = substr($raw, $k, max(0, $len - $k - $m));
    // ★ (2026-11-09 14:12) 한글 인코딩(EUC-KR·CP949)으로 판정된 파일은 앞 바이트를 UTF-8 이어짐으로 보고 버리지 않음 — '가'(B0 A1)처럼 두 바이트가 다 0x80–0xBF 인 글자가 조각 맨 앞이고 뒤가 영문이면 그 글자가 빠졌음
    if ($k > 0 && ($file_enc === 'EUC-KR' || $file_enc === 'CP949')) $cand = "\xFF"; // UTF-8 이 될 수 없는 값 → 아래 CP949 처리로
    if ($cand === '' || mb_check_encoding($cand, 'UTF-8')) {
        if ($offset === 0 && substr($cand, 0, 3) === "\xEF\xBB\xBF") $cand = substr($cand, 3);
        $consumed = $len - $m;
        return [$cand, $consumed > 0 ? $consumed : $len];
    }
    
    // CP949(EUC-KR 포함): 앞에서부터 2바이트 글자 단위로 걸어 마지막 잘린 첫 바이트만 남김
    $cut = 0;
    while ($cut < $len) {
        $b = ord($raw[$cut]);
        if ($b >= 0x81 && $b <= 0xFE) {
            if ($cut + 1 >= $len) break; // 짝이 다음 청크에 있음
            $cut += 2;
        } else {
            $cut++;
        }
    }
    if ($cut === 0) $cut = $len;
    $out = @mb_convert_encoding(substr($raw, 0, $cut), 'UTF-8', 'CP949');
    return [is_string($out) ? $out : '', $cut];
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
    $saved_progress = function_exists('load_json_with_lock') ? load_json_with_lock($progress_file) : (json_decode(file_get_contents($progress_file), true) ?? []); // ★ (2026-11-02 10:01) 공유 잠금(나가는 쪽 저장과 겹치면 처음부터 열렸음)
}
$progress_key = ltrim($getfile, '/');
$saved_data = mc_prog_get($saved_progress, $current_bidx, $progress_key) ?? []; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 것(없으면 예전 키)
$saved_position = is_array($saved_data) ? ($saved_data['position'] ?? 0) : 0;
$saved_percent = is_array($saved_data) ? ($saved_data['percent'] ?? 0) : 0;
$saved_yoff = is_array($saved_data) ? max(-400, min(400, (int)($saved_data['yoff'] ?? 0))) : 0; // ★ (2026-11-02 01:40)
?>
<!DOCTYPE html>
<?php
// 커스텀 폰트 설정
$txt_font_name = $txt_settings['font_name'] ?? '';
$txt_font_url = $txt_settings['font_url'] ?? '';
$txt_font_local = $txt_settings['font_local'] ?? '';
$txt_font_family = !empty($txt_font_name) ? "'" . h($txt_font_name) . "', 'Nanum Gothic', 'Malgun Gothic', sans-serif" : "'Nanum Gothic', 'Malgun Gothic', sans-serif";
?>
<html lang="ko" class="<?php echo h(trim($darkClass . ' mc-fdk')); ?>"><?php /* ★ (2026-11-08 fix82) mc-fdk: 다크 테마면 바탕은 어둡게 두고 내용만 나타나게(bootstrap.php mc_fade_dark_style) */ ?>
<?php if (function_exists('mc_fade_dark_style')) echo mc_fade_dark_style('#1a1a2e'); ?>
<head>
<?php if (function_exists('mc_back_origin_viewer_js')) echo mc_back_origin_viewer_js(); /* ★ (2026-11-05) 뒤로가기 = 들어온 곳(서재·즐겨찾기·북마크 등) — function.php */ ?>

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
    <?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
    <style>
        <?php if (!empty($txt_font_local) && empty($txt_font_url)): ?>
        @font-face {
            font-family: '<?php echo h($txt_font_name ?: 'CustomTxtFont'); ?>';
            src: url('<?php echo function_exists('mc_css_str') ? mc_css_str($txt_font_local) : h($txt_font_local); /* ★ (2026-11-02 10:02) <style> 안이라 CSS 이스케이프(& 가 &amp; 로 남아 글꼴을 못 받던 것) */ ?>');
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
        
        /* ★ (2026-11-02 21:19) theme-dark → dark-mode (이 뷰어가 실제로 붙이는 클래스 — 다크 규칙이 안 먹었음) */
        body.dark-mode .page-touch-area:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        body.dark-mode .page-touch-area:active {
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
        
        body.dark-mode .touch-area-icon {
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
        
        /* ★ (2026-11-11 11:23 fix113, 펜닐 요청) 설정 창을 스크롤 없이(휴대폰 세로·가로·PC) — 한 줄에 이름+고르는 칸, 누르는 칸 높이 30px 이상, 낮은 가로 화면은 두 칸 */
        .settings-panel.mc-setp { width: min(300px, calc(100vw - 20px)); padding: 10px 12px; left: auto; right: 10px; } /* ★ (2026-11-11 13:28 fix115, 펜닐 요청) 세로 화면·PC 는 좁고 길쭉하게(380px·휴대폰 화면 폭 가득 → 300px, 오른쪽 단추 아래) — 휴대폰 가로 두 칸은 그대로 */
        .mc-setp .mc-sr { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; min-height: 30px; }
        .mc-setp .mc-sl, .settings-panel.mc-setp label.mc-sl { flex: 0 0 72px; margin: 0; font-size: 12.5px; line-height: 1.25; display: block; }
        .mc-setp .mc-sr > input[type="range"] { flex: 1 1 auto; min-width: 0; width: auto; margin: 0; }
        .mc-setp .mc-sr > select, .mc-setp .mc-sr3 select { flex: 1 1 auto; min-width: 0; width: 100%; height: 30px; padding: 2px 6px; font-size: 12.5px; margin: 0; }
        .mc-setp .mc-sr > .btn { flex: 0 0 auto; white-space: nowrap; padding: 3px 8px; font-size: 12.5px; }
        .mc-setp .theme-buttons { flex: 1 1 auto; margin: 0; gap: 6px; min-width: 0; }
        .mc-setp .theme-btn, .mc-setp .mc-margin-btn, .mc-setp .mc-spread-btn { padding: 5px 2px; font-size: 12px; min-height: 30px; display: flex; align-items: center; justify-content: center; line-height: 1.15; }
        .mc-setp .mc-sr3 { align-items: flex-end; gap: 6px; }
        .settings-panel.mc-setp .mc-sr3 > label { flex: 1 1 0; min-width: 0; margin: 0; font-size: 11.5px; display: flex; flex-direction: column; gap: 2px; }
        .mc-setp .mc-cks { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 8px; }
        .settings-panel.mc-setp label.mc-ck { display: inline-flex; align-items: center; gap: 5px; margin: 0; padding: 4px 10px; min-height: 30px; border: 1px solid var(--border-color); border-radius: 15px; font-size: 12px; cursor: pointer; }
        .mc-setp .mc-ck input { margin: 0; }
        .mc-setp .mc-sr-foot { gap: 6px; font-size: 12px; min-height: 0; margin-bottom: 4px; color: #888; }
        .mc-setp .mc-sr-foot #readTime { font-size: 12px; color: inherit; }
        .mc-setp .mc-fs-row { margin-top: 4px; }
        @media (max-height: 520px) and (min-width: 560px) {
            .settings-panel.mc-setp { width: min(720px, calc(100vw - 20px)); column-count: 2; column-gap: 16px; }
            .mc-setp > * { break-inside: avoid; }
        }
        .mc-setp .mc-sr > .btn + .mc-sr-foot { margin: 0; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mc-setp #reverseNavRow { margin-bottom: 8px; }
        .mc-setp #autoscrollSpeedRow[style*="block"], .mc-setp #reverseNavRow[style*="block"] { display: flex !important; } /* JS 가 보일 때 display:block 으로 켬 — 이름과 칸을 한 줄로(숨길 땐 none 그대로) */
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
        
        /* ★ (2026-11-01 11:22) 양쪽 정렬 · 좌우 여백 · 이 장 남은 쪽 */
        .txt-reader.mc-justify .txt-content, .txt-reader.mc-justify .column-text { text-align: justify; }
        .txt-reader.mc-mg-1 { max-width: 680px; }
        .txt-reader.mc-mg-2 { max-width: 560px; }
        .txt-reader.layout-dual.mc-mg-1 { max-width: 100%; padding-left: 110px; padding-right: 110px; }
        .txt-reader.layout-dual.mc-mg-2 { max-width: 100%; padding-left: 170px; padding-right: 170px; }
        .txt-footer .ch-left { opacity: .75; white-space: nowrap; margin-left: auto; margin-right: 14px; } /* 오른쪽 % 옆(가운데는 쪽 모드 쪽 번호 상자에 가림) */
        
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
            /* ★ (2026-11-01) 좌우 여백(휴대폰) */
            .txt-reader.mc-mg-1, .txt-reader.layout-dual.mc-mg-1 { padding-left: 30px; padding-right: 30px; }
            .txt-reader.mc-mg-2, .txt-reader.layout-dual.mc-mg-2 { padding-left: 46px; padding-right: 46px; }
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
<script src="js/book-notes.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/book-notes.js'); ?>"></script><?php // ★ (2026-10-31) 책 노트 화면 ?>
<script src="js/book-tools.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/book-tools.js'); ?>"></script><?php // ★ (2026-11-01) 목차·검색·듣기·화면 꺼짐 방지 ?>
</head>
<body class="<?php echo h($darkClass); ?>">

<!-- 툴바 -->
<div class="txt-toolbar">
    <a data-mc-back href="index.php?dir=<?php echo u($link_dir) . $bidx_param; ?>" class="btn btn-outline-secondary btn-sm">← <?php echo __h("back"); ?></a>
    <span class="title"><?php echo h($title); ?></span>
    <?php render_lang_badge('sm'); ?>
    <?php if (function_exists('mc_doc_cover_btn')) echo mc_doc_cover_btn($getfile, $current_bidx ?? 0, 'btn btn-outline-secondary btn-sm'); /* ★ (2026-11-08 fix80) 이 책 표지를 폴더 표지로(관리자) */ ?>
    
    <div class="controls">
        <?php // ★ (2026-11-01) 목차(장 제목 자동 찾기) · 본문 검색 ?>
        <button id="btnToc" type="button" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('bt_toc'); ?>" aria-label="<?php echo __h('bt_toc'); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px"><path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/></svg></button>
        <button id="btnSearch" type="button" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('bt_search'); ?>" aria-label="<?php echo __h('bt_search'); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></button>
        <?php // ★ (2026-10-31) 책갈피(지금 위치) · 노트 목록 ?>
        <button id="btnBookmark" type="button" class="btn btn-outline-secondary btn-sm mcn-tb-btn" title="<?php echo __h('bn_bm_add'); ?>" aria-label="<?php echo __h('bn_bm_add'); ?>" aria-pressed="false"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg></button>
        <button id="btnNotes" type="button" class="btn btn-outline-secondary btn-sm" title="<?php echo __h('bn_panel'); ?>" aria-label="<?php echo __h('bn_panel'); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px"><path d="M12 20h9"/><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"/></svg></button>
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
<?php // ★ (2026-11-11 11:22 fix113, 펜닐 요청) 설정 창을 스크롤 없이 — 한 줄에 이름+고르는 칸, 테마·보기 방식과 글꼴·여백은 한 줄 두 칸, 체크 칸은 칩 묶음, 휴대폰 가로는 두 칸(CSS .mc-setp)
//   칸·ID·동작은 그대로. 부연 설명(2단 안내·좌우 반전·속도 범위)은 마우스를 올리면(title). 소제목(🎨·📖·📝·⏬·🔊)은 뺌 ?>
<div class="settings-panel mc-setp" id="settingsPanel">
    <div class="mc-sr mc-sr3">
        <label for="themeSelect"><?php echo __h("txt_display_mode"); ?>
        <select id="themeSelect">
            <option value="light">☀️ <?php echo __h("epub_theme_light"); ?></option>
            <option value="sepia">📜 <?php echo __h("epub_theme_sepia"); ?></option>
            <option value="dark">🌙 <?php echo __h("epub_theme_dark"); ?></option>
        </select></label>
        <label for="layoutSelect" title="<?php echo __h("txt_dual_note"); ?>"><?php echo __h("txt_view_mode"); ?>
        <select id="layoutSelect">
            <option value="single"><?php echo __h("txt_single"); ?></option>
            <option value="dual-ltr"><?php echo __h("txt_dual_ltr"); ?></option>
            <option value="dual-rtl"><?php echo __h("txt_dual_rtl"); ?></option>
            <option value="auto"><?php echo __h("txt_layout_auto"); ?></option><?php // ★ (2026-11-04 18:05) 자동 — 가로 화면이면 2단(1|2), 세로면 1단 · 이것만 이 기기에 기억 ?>
        </select></label>
    </div>
    <div class="mc-sr" id="reverseNavRow" style="display:none;">
        <label class="mc-ck" title="◀ <?php echo __h("js_prev"); ?>/<?php echo __h("js_next"); ?> ▶ ↔ ◀ <?php echo __h("js_next"); ?>/<?php echo __h("js_prev"); ?> ▶">
            <input type="checkbox" id="chkReverseNav" <?php echo ($_COOKIE['txt_reverse_nav'] ?? '') === '1' ? 'checked' : ''; ?>>
            <?php echo __h("txt_reverse_nav"); ?>
        </label>
    </div>
    <div class="mc-sr"><label class="mc-sl" for="fontSizeSlider"><?php echo __h("txt_font_size"); ?> <span id="fontSizeValue"><?php echo $txt_settings['default_font_size']; ?></span>px</label>
        <input type="range" id="fontSizeSlider" min="12" max="32" value="<?php echo $txt_settings['default_font_size']; ?>"></div>
    <div class="mc-sr"><label class="mc-sl" for="lineHeightSlider"><?php echo __h("txt_line_height"); ?> <span id="lineHeightValue"><?php echo $txt_settings['default_line_height']; ?></span></label>
        <input type="range" id="lineHeightSlider" min="1.0" max="3.0" step="0.1" value="<?php echo $txt_settings['default_line_height']; ?>"></div>
    <?php // ★ (2026-11-01 11:22) 글꼴 · 양쪽 정렬 · 좌우 여백 (이 기기에 기억) ?>
    <div class="mc-sr mc-sr3">
        <label for="selFont"><?php echo __h("bt_font"); ?>
        <select id="selFont">
            <option value=""><?php echo __h("bt_book_default"); ?></option>
            <option value="gothic"><?php echo __h("bt_font_gothic"); ?></option>
            <option value="serif"><?php echo __h("bt_font_serif"); ?></option>
        </select></label>
        <label for="selMargin"><?php echo __h("bt_margin"); ?>
        <select id="selMargin">
            <?php foreach ([0, 1, 2] as $_mg): ?><option value="<?php echo $_mg; ?>"><?php echo __h('bt_margin_' . $_mg); ?></option><?php endforeach; ?>
        </select></label>
    </div>
    <div class="mc-sr"><label class="mc-sl" for="dimSlider"><?php echo __h("bt_dim"); ?> <span id="dimValue">0%</span></label>
        <input type="range" id="dimSlider" min="0" max="70" step="5" value="0"></div>
    <?php // ★ (2026-11-01) 듣기 · 화면 꺼짐 방지 ?>
    <div class="mc-cks">
        <label class="mc-ck"><input type="checkbox" id="chkJustify"> <?php echo __h("bt_justify"); ?></label>
        <label class="mc-ck"><input type="checkbox" id="autoscrollCheck"> <?php echo __h("txt_autoscroll_enable"); ?></label>
        <label class="mc-ck"><input type="checkbox" id="chkWake"> <?php echo __h("bt_wake"); ?></label>
    </div>
    <small id="wakeNote" style="display:none;color:#888;"></small>
    <div class="mc-sr" id="autoscrollSpeedRow" style="display:none;"><label class="mc-sl" for="scrollSpeedSlider" title="<?php echo __h("txt_speed_range"); ?>"><?php echo __h("txt_scroll_speed"); ?> <span id="scrollSpeedValue">3</span></label>
        <input type="range" id="scrollSpeedSlider" min="1" max="10" step="1" value="3"></div>
    <div class="mc-sr"><button type="button" id="btnTts" class="btn btn-outline-secondary btn-sm">🔊 <?php echo __h("bt_tts_start"); ?></button>
        <div class="mc-sr-foot"><small><?php echo __h("bt_readtime"); ?></small> <span id="readTime"></span></div></div>
    <small id="ttsNote" style="display:none;color:#888;margin-bottom:6px;"></small>
    <?php // ★ (2026-11-04 18:05) 전체화면(북모드 설정의 전체화면과 같은 동작) — 브라우저가 지원할 때만 보임(iPhone 사파리는 막혀 있어 숨김) ?>
    <div class="settings-row mc-fs-row" id="fsRow" hidden><span><?php echo __h("bt_fullscreen"); ?></span><button type="button" id="btnFullscreen" class="btn btn-outline-secondary btn-sm" aria-pressed="false"><?php echo __h("bt_fs_on"); ?></button></div>
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
    <span id="chLeft" class="ch-left"></span><?php // ★ (2026-11-01) 이 장 남은 쪽 ?>
    <span id="readProgress">0%</span>
</div>

<script>
(function() {
    const FILE = <?php echo js($getfile); ?>;
    const CHUNK_SIZE = <?php echo $txt_settings['chunk_size']; ?>;
    const SAVED_POSITION = <?php echo (int)$saved_position; ?>;
    const SAVED_PERCENT = <?php echo (float)$saved_percent; ?>;
    const SAVED_YOFF = <?php echo (int)$saved_yoff; ?>; // ★ (2026-11-02 01:40) 저장 글자 줄의 머리줄 기준 위치(px, 1단)
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
    // ★ (2026-11-03 01:21) 1단 화면 본문(글 하나)을 필요한 곳까지만 — 큰 파일(수십 MB)은 전체를 한 번에 넣으면 브라우저가 수십 초 멈췄음(목차·검색이 글 전체를 읽은 뒤 본문에 다 넣었음)
    //   본문은 늘 전체 글(fullContent)의 앞부분이라 글자 번호는 그대로 맞음. 모자라면 뒤에 이어 붙임(앞을 다시 그리지 않음)
    function domLen() { const n = contentEl.firstChild; return (n && n.nodeType === 3 && contentEl.childNodes.length === 1) ? n.length : -1; }
    function domTo(need) {
        const want = Math.min(fullContent.length, Math.max(0, need));
        const have = domLen();
        if (have < 0) { contentEl.textContent = fullContent.substring(0, Math.max(want, Math.min(fullContent.length, 200000))); return; }
        if (have < want) contentEl.firstChild.appendData(fullContent.substring(have, want));
    }
    let chunkFailT = 0; // ★ (2026-11-03 09:08) 마지막 조각 읽기 실패 시각(세션 만료·서버 오류·신호 끊김) — 자동 스크롤이 끝에서 계속 다시 부르지 않게
    async function loadChunk() {
        if (isLoading) return;
        if (allLoaded) { if (currentLayout === 'single' && domLen() >= 0 && domLen() < fullContent.length) domTo(domLen() + CHUNK_SIZE * 2); return; } // ★ 목차·검색으로 다 읽어 왔지만 본문엔 아직 안 넣은 부분 — 스크롤하면 이어 붙임
        
        isLoading = true;
        loadingEl.classList.remove('hidden');
        
        try {
            const res = await fetch(`txt_viewer.php?action=get_chunk&file=${encodeURIComponent(FILE)}&offset=${currentOffset}&length=${CHUNK_SIZE}&bidx=${BIDX}`);
            const data = await res.json();
            
            if (data.error) {
                // ★ (2026-11-02 21:19) 이미 읽던 본문이 있으면 그대로 두고 로딩 표시만 끔 — 세션이 끝난 뒤 다음 조각을 부르면 본문 전체가 오류 한 줄로 바뀌었음
                if (contentEl.textContent.trim() === '') { const _ep = document.createElement('p'); _ep.style.color = 'red'; _ep.textContent = String(data.error); contentEl.replaceChildren(_ep); }
                loadingEl.classList.add('hidden');
                chunkFailT = Date.now();
                isLoading = false;
                return;
            }
            
            chunkFailT = 0;
            if (data.content) {
                fullContent += data.content;
                if (currentLayout === 'single') {
                    if (domLen() >= 0) domTo(fullContent.length); else contentEl.textContent = fullContent; // ★ 이어 붙임(앞을 다시 그리지 않음)
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
            chunkFailT = Date.now();
        }
        
        isLoading = false;
    }
    
    // ============================================================
    // ★ (2026-11-01) 쪽 모드(2단 · 휴대폰 1칸) 다시 만듦 — 펜닐 제보: 쪽을 넘길 때마다 글 위치가 바뀌고 진행%가 안 맞음
    //   예전: N쪽 시작 = N × 정해진 글자 수(PC 1500 · 휴대폰 500)인데 화면에는 실제로 들어가는 만큼 보여 줘서
    //         다음 쪽과 글이 겹치거나(같은 글이 또 나옴) 빠졌음(휴대폰은 쪽마다 수십 글자씩 안 보였음)
    //   지금: 쪽 = 실제로 화면에 들어간 글자 범위 [pageStart, pageEnd)
    //         다음 쪽은 정확히 pageEnd 부터, 이전 쪽은 지나온 쪽 시작을 기억해 두었다가 그대로(같은 쪽이 다시 나옴),
    //         기억이 없으면(쪽 이동·글자 크기 변경 뒤) pageStart 바로 앞까지 거꾸로 채움 → 겹침·빠짐 없음
    //         쪽 번호·전체 쪽 수는 실제 쪽 글자 수 평균으로 어림(글을 더 읽어 올수록 정확해짐)
    //   진행%: 글자 위치 ÷ 전체 글자 수(다 읽어 왔으면 정확, 아니면 읽어 온 부분의 '바이트당 글자 수'로 어림 — 예전 '÷ 2.5' 는 한글·영문 파일마다 크게 틀렸음)
    // ============================================================
    let pageStart = 0;            // 지금 쪽 첫 글자 번호
    let pageEnd = 0;              // 지금 쪽 다음 글자 번호(여기부터 다음 쪽)
    let pageHist = [];            // 앞으로 넘기며 지나온 쪽들의 시작(뒤로 갈 때 그대로 꺼냄)
    let pageSizeSum = 0, pageSizeCnt = 0; // 꽉 찬 쪽의 글자 수 평균(쪽 번호 어림용)
    let pageNo = 0;               // 지금 쪽 번호(0부터) — 넘길 때마다 ±1(번호가 건너뛰거나 겹치지 않게), 건너뛸 때만 어림
    
    // 처음 어림값(실제 쪽을 하나라도 그리면 그 평균으로 바뀜)
    function calculateCharsPerPage() {
        return window.innerWidth < 768 ? 500 : 1500;
    }
    function avgPageChars() {
        return pageSizeCnt > 0 ? Math.max(50, Math.round(pageSizeSum / pageSizeCnt)) : calculateCharsPerPage();
    }
    function resetPageStats() { pageSizeSum = 0; pageSizeCnt = 0; pageHist = []; }
    
    // 전체 글자 수 — 다 읽어 왔으면 정확한 값, 아니면 읽어 온 부분의 글자/바이트 비율로 어림
    function estTotalChars() {
        if (allLoaded) return Math.max(1, fullContent.length);
        if (currentOffset > 0 && fullContent.length > 0 && fileSize > 0) {
            return Math.max(fullContent.length, Math.round(fullContent.length * fileSize / currentOffset));
        }
        return Math.max(1, Math.ceil(fileSize / 2.5));
    }
    function percentAt(idx, atEnd) {
        if (atEnd) return 100;
        const t = estTotalChars();
        return Math.max(0, Math.min(100, Math.round(idx / t * 100)));
    }
    
    // 총 페이지 수(어림)
    function calculateTotalPages() {
        charsPerPage = avgPageChars();
        const atEnd = allLoaded && pageEnd >= fullContent.length && currentLayout !== 'single';
        const cur = pageNo;
        totalPages = atEnd ? cur + 1 : Math.max(cur + 1, cur + Math.ceil(Math.max(0, estTotalChars() - pageStart) / charsPerPage));
        document.getElementById('totalPages').textContent = totalPages;
        return totalPages;
    }
    
    // 낱말 중간에서 쪽이 끊기지 않게: 끝 근처(30자 안)의 띄어쓰기·줄바꿈 뒤에서 자름
    function snapCut(text, cap) {
        if (cap <= 0 || cap >= text.length) return cap;
        if (/\s/.test(text.charAt(cap)) || /\s/.test(text.charAt(cap - 1))) return cap;
        for (let i = cap - 1; i > cap - 30 && i > cap * 0.7; i--) {
            if (/\s/.test(text.charAt(i))) return i + 1;
        }
        return cap;
    }
    
    // 컬럼에 들어가는 문자 수 측정 (overflow 지점 찾기) — 앞에서부터 채움
    function measureColumnCapacity(columnEl, text) {
        if (!text || text.length === 0) return 0;
        
        const columnHeight = columnEl.clientHeight;
        if (columnHeight <= 0) return text.length;
        
        // 일단 전체 넣어보기(아주 긴 글은 넉넉한 만큼만)
        const probe = text.length > 20000 ? text.substring(0, 20000) : text;
        columnEl.textContent = probe;
        if (columnEl.scrollHeight <= columnHeight + 1) {
            return probe.length; // 다 들어감
        }
        
        // Binary search로 들어가는 양 찾기
        let low = 0;
        let high = probe.length;
        let result = 0;
        
        while (low <= high) {
            const mid = Math.floor((low + high) / 2);
            columnEl.textContent = probe.substring(0, mid);
            
            if (columnEl.scrollHeight > columnHeight + 1) {
                high = mid - 1;
            } else {
                result = mid;
                low = mid + 1;
            }
        }
        
        return snapCut(probe, result);
    }
    // 끝에서부터 거꾸로 채움 — text 의 '뒤쪽' 몇 글자가 들어가는지
    function measureColumnCapacityBack(columnEl, text) {
        if (!text || text.length === 0) return 0;
        const columnHeight = columnEl.clientHeight;
        if (columnHeight <= 0) return text.length;
        const probe = text.length > 20000 ? text.substring(text.length - 20000) : text;
        columnEl.textContent = probe;
        if (columnEl.scrollHeight <= columnHeight + 1) return probe.length;
        let low = 0, high = probe.length, result = 0;
        while (low <= high) {
            const mid = Math.floor((low + high) / 2);
            columnEl.textContent = probe.substring(probe.length - mid);
            if (columnEl.scrollHeight > columnHeight + 1) { high = mid - 1; } else { result = mid; low = mid + 1; }
        }
        // 시작이 낱말 중간이면 다음 띄어쓰기 뒤부터(30자 안)
        let st = probe.length - result;
        if (st > 0 && !/\s/.test(probe.charAt(st - 1)) && !/\s/.test(probe.charAt(st))) {
            for (let i = st; i < st + 30 && i < probe.length; i++) { if (/\s/.test(probe.charAt(i))) { st = i + 1; break; } }
        }
        return probe.length - st;
    }
    
    function twoColumns() { return window.innerWidth >= 768; }
    // ★ (2026-11-01) 머리줄(위)·쪽 번호/아래 막대(아래)에 가리지 않는 글 영역
    //   예전: 위 여백이 60~80px 고정 — 휴대폰은 머리줄이 두 줄(약 100px)이라 쪽마다 첫 줄 한두 개가 머리줄 밑에 가려 안 보였음
    function topSafe() {
        const tb = document.querySelector('.txt-toolbar');
        return (tb ? Math.ceil(tb.getBoundingClientRect().bottom) : 60) + 8;
    }
    function fitPageBox() {
        if (currentLayout === 'single') {
            contentWrapper.style.height = '';
            readerEl.style.paddingBottom = '';
            readerEl.style.paddingTop = '';
            const cssTop = parseFloat(getComputedStyle(readerEl).paddingTop) || 0;
            if (topSafe() > cssTop) readerEl.style.paddingTop = topSafe() + 'px'; // 1단도 첫 줄이 머리줄에 가리지 않게
            return;
        }
        const top = topSafe() + 4;
        let bottom = window.innerHeight;
        ['.page-info-bar', '.txt-footer'].forEach(sel => {
            const el = document.querySelector(sel); if (!el) return;
            const r = el.getBoundingClientRect();
            if (r.height > 0 && r.top > window.innerHeight / 2 && r.top < bottom) bottom = r.top;
        });
        readerEl.style.paddingTop = top + 'px';
        readerEl.style.paddingBottom = '0px';
        contentWrapper.style.height = Math.max(120, Math.floor(bottom - 10 - top)) + 'px';
    }
    function syncColumnStyle() {
        const fontSize = getComputedStyle(contentEl).fontSize;
        const lineHeight = getComputedStyle(contentEl).lineHeight;
        columnLeft.style.fontSize = fontSize;
        columnLeft.style.lineHeight = lineHeight;
        columnRight.style.fontSize = fontSize;
        columnRight.style.lineHeight = lineHeight;
    }
    
    // 필요한 만큼 콘텐츠 로드
    async function ensureContentLoaded(targetChars) {
        while (fullContent.length < targetChars && !allLoaded) {
            if (isLoading) { await new Promise(r => setTimeout(r, 50)); continue; } // 다른 곳에서 읽는 중이면 끝날 때까지 기다림(예전엔 여기서 멈추지 않고 계속 돌았음)
            const before = currentOffset;
            await loadChunk();
            if (currentOffset === before && !allLoaded) break; // 오류 등으로 더 못 읽음
        }
    }
    
    // start 부터 한 쪽 채우기(limit 이 있으면 거기까지만) → 실제 끝 글자 번호
    function layoutFrom(start, limit) {
        syncColumnStyle();
        const end0 = (typeof limit === 'number') ? Math.min(limit, fullContent.length) : fullContent.length;
        const text = fullContent.substring(start, end0);
        if (!text) {
            columnLeft.textContent = start >= fullContent.length && fullContent.length > 0 ? '' : _vi18n.txt_no_content;
            columnRight.textContent = '';
            return start;
        }
        const lc = measureColumnCapacity(columnLeft, text);
        columnLeft.textContent = text.substring(0, lc);
        let used = lc;
        if (twoColumns()) {
            const rest = text.substring(lc);
            const rc = measureColumnCapacity(columnRight, rest);
            columnRight.textContent = rest.substring(0, rc);
            used += rc;
        } else {
            columnRight.textContent = '';
        }
        if (used <= 0 && text.length > 0) { // 한 글자도 안 들어가는 아주 좁은 화면 — 멈추지 않게 한 글자라도
            used = 1; columnLeft.textContent = text.substring(0, 1);
        }
        return start + used;
    }
    // end 바로 앞까지 거꾸로 채우기 → 그 쪽의 시작 글자 번호
    function startBefore(end) {
        syncColumnStyle();
        const from = Math.max(0, end - 20000);
        let text = fullContent.substring(from, end);
        let back = 0;
        if (twoColumns()) {
            const rc = measureColumnCapacityBack(columnRight, text);
            back += rc; text = text.substring(0, text.length - rc);
        }
        const lc = measureColumnCapacityBack(columnLeft, text);
        back += lc;
        return Math.max(0, end - Math.max(1, back));
    }
    
    // 쪽 표시: start 부터(limit 까지만) — 모든 쪽 이동이 여기로 옴 (isPageLoading 은 위에 선언)
    async function showPageAt(start, limit, no) {
        if (isPageLoading) return;
        isPageLoading = true;
        try {
            start = Math.max(0, Math.floor(start || 0));
            await ensureContentLoaded(start + 20000);
            fitPageBox(); // 쪽 영역 크기 먼저 맞춤
            if (start >= fullContent.length && fullContent.length > 0) start = startBefore(fullContent.length); // 끝을 넘으면 마지막 쪽
            const end = layoutFrom(start, limit);
            pageStart = start; pageEnd = end;
            pageNo = (start <= 0) ? 0 : (typeof no === 'number' ? Math.max(1, no) : Math.max(1, Math.round(start / avgPageChars())));
            try { lastColSize = columnLeft.clientWidth + 'x' + columnLeft.clientHeight + 'x' + (twoColumns() ? 2 : 1); } catch (e) {}
            if (typeof limit !== 'number' && end < fullContent.length) { pageSizeSum += (end - start); pageSizeCnt++; } // 꽉 찬 쪽만 평균에
            updatePageDisplay();
        } finally {
            isPageLoading = false;
        }
    }
    
    // 예전 이름 유지(다른 곳에서 부름): 쪽 번호 → 어림 글자 위치로 이동
    async function loadPage(pageNum) {
        if (pageNum < 0) pageNum = 0;
        pageHist = [];
        await showPageAt(lineStartNear(pageNum * avgPageChars()));
    }
    // 글자 위치를 그 줄의 처음으로(300자 안에 줄바꿈이 있으면) — 쪽 번호로 건너뛸 때 줄 중간에서 시작하지 않게
    function lineStartNear(idx) {
        idx = Math.max(0, Math.min(Math.floor(idx), fullContent.length));
        const nl = fullContent.lastIndexOf('\n', idx - 1);
        return (nl >= 0 && idx - nl <= 300) ? nl + 1 : idx;
    }
    
    // 페이지 표시 업데이트
    function updatePageDisplay() {
        calculateTotalPages();
        currentPage = pageNo;
        const atEnd = allLoaded && pageEnd >= fullContent.length;
        const pageInput = document.getElementById('pageJumpInput');
        pageInput.value = currentPage + 1;
        pageInput.max = totalPages;
        
        document.getElementById('btnPrevPage').disabled = pageStart <= 0;
        document.getElementById('btnNextPage').disabled = atEnd;
        
        // 전역 currentCharIndex = 지금 쪽 첫 글자(정확한 값)
        currentCharIndex = pageStart;
        
        const percent = percentAt(pageStart, atEnd);
        readProgress.textContent = percent + '%';
        progressFill.style.width = percent + '%';
        
        // 진행률 서버 저장(쪽 모드도) — 복원 중에는 안 함
        if (!isRestoring) {
            if (saveProgressTimeout) clearTimeout(saveProgressTimeout);
            saveProgressTimeout = setTimeout(() => saveProgress(percent, pageStart), 1500);
        }
    }
    
    // 페이지 번호로 이동(쪽 번호 입력·Home·End)
    async function goToPage(page, animate = true) {
        if (page <= 0) { if (pageStart > 0) { pageHist = []; await showPageAt(0); } return; } // 첫 쪽은 언제나 0번 글자부터
        if (page >= totalPages - 1 && page > 0) { // 마지막 쪽: 끝까지 읽어 와서 끝에서 거꾸로
            await ensureContentLoaded(Infinity);
            if (page >= Math.ceil(fullContent.length / avgPageChars()) - 1) {
                pageHist = [];
                await showPageAt(startBefore(fullContent.length));
                return;
            }
        }
        if (page === currentPage) return;
        await loadPage(page);
    }
    
    // 다음/이전 페이지 (데이터 순서 기준)
    let navBusy = false; // 넘기는 중 또 누르면 무시(지나온 쪽 기억이 두 번 쌓이지 않게)
    async function nextPage() {
        if (navBusy || isPageLoading) return;
        navBusy = true;
        try {
            await ensureContentLoaded(pageEnd + 20000);
            if (pageEnd >= fullContent.length) return; // 마지막 쪽
            pageHist.push(pageStart);
            if (pageHist.length > 2000) pageHist.shift();
            await showPageAt(pageEnd, undefined, pageNo + 1);
        } finally { navBusy = false; }
    }
    
    async function prevPage() {
        if (navBusy || isPageLoading || pageStart <= 0) return;
        navBusy = true;
        try {
            const b = pageStart, no = pageNo - 1;
            let a = pageHist.length ? pageHist.pop() : -1;
            if (!(a >= 0 && a < b)) a = startBefore(b); // 기억이 없으면 거꾸로 채움
            await showPageAt(a, b, no); // 이전 쪽은 지금 쪽 바로 앞에서 끝남(겹침·빠짐 없음)
            if (pageEnd < b) { pageHist = []; await showPageAt(startBefore(b), b, no); } // 기억한 쪽이 지금 화면에서 b 까지 못 닿으면(칸 크기가 바뀐 경우) 거꾸로 채움
        } finally { navBusy = false; }
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
            
            // 1단 모드: 기존 로드된 내용 표시 — ★ 보던 곳까지만(큰 파일은 전체를 넣으면 멈춤, 모자란 뒤쪽은 스크롤하면 이어 붙음)
            contentEl.textContent = '';
            domTo(Math.max(savedCharIndex, pageEnd || 0) + CHUNK_SIZE * 4);
            fitPageBox(); // ★ (2026-11-01) 쪽 모드에서 넣은 크기 되돌림 + 머리줄 아래 여백
            
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
                            window.scrollTo(0, Math.max(0, window.pageYOffset + rect.top - topSafe())); // ★ (2026-11-01) 머리줄 바로 아래로(예전 50px 고정 — 휴대폰은 머리줄이 두 줄이라 그 글이 머리줄 밑에 가려졌음)
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
                        
                        // 진행률 표시 (★ 2026-11-01 전체 글자 수 어림 방식 통일)
                        const percent = percentAt(currentCharIndex, false);
                        progressFill.style.width = percent + '%';
                        readProgress.textContent = percent + '%';
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
            
            // 2단↔2단 전환 (방향만 변경): 지금 쪽 그대로 다시 표시
            if (prevLayout !== 'single') {
                showPageAt(pageStart);
            } else {
                // 1단→2단 전환: 1단에서 보던 첫 글자부터 쪽을 만듦(★ 2026-11-01 — 예전엔 '글자 수 ÷ 쪽당 글자 수'로 쪽을 골라 보던 곳과 어긋났음)
                resetPageStats();
                showPageAt(savedCharIndex);
            }
        }
        
        // 레이아웃은 저장하지 않음 - 항상 1단으로 시작
    }
    
    // 진행률 서버 저장
    let saveProgressTimeout;
    // ★ (2026-11-02 01:40) 1단(스크롤)에서 그 글자 줄이 머리줄 아래 몇 px 에 있는지 — 2단(쪽)은 쪽 단위라 0
    function yoffOf(position) {
        try {
            if (currentLayout !== 'single') return 0;
            const n = contentEl.firstChild;
            if (!n || n.nodeType !== Node.TEXT_NODE || position < 0 || position >= n.length) return 0;
            const r = document.createRange(); r.setStart(n, position); r.setEnd(n, position + 1);
            const v = Math.round(r.getBoundingClientRect().top - topSafe());
            return Math.abs(v) <= 400 ? v : 0;
        } catch (e) { return 0; }
    }
    function saveProgress(percent, position) {
        const data = new FormData();
        data.append('file', FILE);
        data.append('percent', percent);
        data.append('position', position);
        data.append('yoff', yoffOf(position));
        
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
        // ★ (2026-11-01) 줄의 맨 앞·머리줄 바로 아래 줄(예전: 왼쪽 20px 안쪽이라 줄 첫 1~2글자를 건너뛰었고, 휴대폰은 머리줄에 가린 줄을 읽었음
        //   → 저장 위치가 열 때마다 조금씩 뒤로 밀리고, 1단→2단 전환 때 첫 글자가 잘렸음)
        let padL = 0; try { padL = parseFloat(getComputedStyle(contentEl).paddingLeft) || 0; } catch (e) {}
        const x = rect.left + padL + 2;
        const y = Math.max(rect.top + 4, topSafe() - 4);
        
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
    
    // 2단 모드에서 실제 표시된 시작 문자 인덱스 — ★ (2026-11-01) 쪽 범위를 정확히 들고 있으므로 그대로(예전: 첫 30자를 근처에서 다시 찾음)
    function getDualModeCharIndex() {
        return pageStart;
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
        
        // 진행률 = 현재 문자 / 전체 글자 수(★ 2026-11-01 어림 방식 개선 · 맨 아래까지 다 읽으면 100%)
        const percent = percentAt(currentCharIndex, allLoaded && scrollTop + winHeight >= docHeight - 2 && (domLen() < 0 || domLen() >= fullContent.length)); // ★ (2026-11-03 01:47) 화면에 앞부분만 있을 때 그 끝을 100%로 보지 않게
        
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
        // 전역 currentCharIndex 사용 (스크롤/페이지 이동 시 이미 업데이트됨) — 화면에 보이는 %와 같은 계산(★ 2026-11-01)
        const pct = parseInt(readProgress.textContent, 10);
        const percent = isFinite(pct) ? pct : percentAt(currentCharIndex, false);
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
        data.append('yoff', yoffOf(position)); // ★ (2026-11-02 01:40)
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
    
    function setTheme(theme, fromUser) {
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
        if (fromUser && (theme === 'light' || theme === 'dark')) document.cookie = `darkmode=${theme}; path=/; max-age=31536000`; // ★ (2026-11-02 21:19) 세피아는 TXT 만의 테마(txt_theme 로 기억) — 쿠키에 쓰면 목록(다크)과 문서 뷰어(세피아 규칙 없음 → 라이트)가 어긋났음 // ★ (2026-11-02 14:30) 직접 바꿀 때만 — 열기만 해도 쿠키가 TXT 테마로 바뀌어 HWP·Office 뷰어 테마가 달라지던 것
        try { localStorage.setItem('txt_theme', theme); } catch (e) {}
        if (fromUser && (theme === 'light' || theme === 'dark')) { try { localStorage.setItem('mycomix_theme', theme); } catch (e) {} } // ★ (2026-11-02 12:02) 직접 바꿀 때만(열 때 되살리는 것은 전체 테마를 건드리지 않음) / (2026-11-02 11:40) 다른 화면은 이 값을 먼저 봄(여기서 바꾼 테마가 목록에서 되돌아가던 것 — office·hwp 와 같게)
    }
    
    // 테마 버튼 클릭 (순환)
    document.getElementById('btnTheme').addEventListener('click', () => {
        const currentIndex = themes.indexOf(currentTheme);
        const nextIndex = (currentIndex + 1) % themes.length;
        setTheme(themes[nextIndex], true);
    });
    
    // 테마 드롭다운 변경
    document.getElementById('themeSelect').addEventListener('change', (e) => {
        setTheme(e.target.value, true);
    });
    
    // 설정 패널
    // ★ (2026-11-02 19:20) 설정 창·진행 막대를 머리줄 바로 아래로 — 휴대폰은 머리줄이 두 줄(약 102px)이라 top:60px 창이 단추를 덮어 닫을 수 없었고, 진행 막대는 머리줄 밑에 가려졌음
    function mcTxtBelowBar() {
        try {
            var tb = document.querySelector('.txt-toolbar'); if (!tb) return;
            var b = Math.round(tb.getBoundingClientRect().bottom);
            var sp = document.getElementById('settingsPanel');
            if (sp) { sp.style.top = (b + 6) + 'px'; sp.style.maxHeight = 'calc(100vh - ' + (b + 16) + 'px)'; }
            var pb = document.querySelector('.progress-bar-container'); if (pb) pb.style.top = b + 'px';
        } catch (e) {}
    }
    mcTxtBelowBar();
    window.addEventListener('resize', mcTxtBelowBar);
    document.getElementById('btnSettings').addEventListener('click', () => {
        mcTxtBelowBar();
        document.getElementById('settingsPanel').classList.toggle('show');
    });

    // ★ (2026-11-07 fix40) 머리줄 창은 한 번에 하나만 — 창이 열린 채 다른 단추(목차·검색·노트·설정)를 누르면 열린 창을 닫고 새 창만(예전엔 겹쳐 열림, 펜닐 제보)
    //   단추 자기 동작(열기/닫기)보다 먼저 돌도록 문서 단계(capture)에서 — 같은 단추를 다시 누르면 예전처럼 그 창만 닫힘
    document.addEventListener('click', (e) => {
        const b = e.target && e.target.closest && e.target.closest('#btnToc,#btnSearch,#btnNotes,#btnSettings');
        if (!b) return;
        const id = b.id, sp = document.getElementById('settingsPanel'), toc = document.getElementById('tocSidebar');
        if (id !== 'btnSettings' && sp) sp.classList.remove('show');
        if (id !== 'btnNotes') { try { window.MCNotes && MCNotes.closePanels && MCNotes.closePanels(); } catch (x) {} }
        if (toc && id !== 'btnToc') toc.classList.remove('show');
        // 검색·목차(옆 창 공용)는 서로 알아서 하나만 — 노트·설정·EPUB 목차를 열 때만 닫음
        if (id === 'btnNotes' || id === 'btnSettings' || (toc && id === 'btnToc')) { try { window.MCTools && MCTools.closePanels && MCTools.closePanels(); } catch (x) {} }
    }, true);
    
    // 레이아웃 선택
    // ★ (2026-11-04 18:05) '자동'(가로 화면이면 2단 1|2, 세로면 1단) — 고르면 이 기기에 기억(다른 보기는 예전처럼 기억 안 함: 열 때 1단)
    const txtIsLand = () => window.innerWidth > window.innerHeight;
    const txtAutoLayout = () => (txtIsLand() ? 'dual-ltr' : 'single');
    document.getElementById('layoutSelect').addEventListener('change', (e) => {
        const v = e.target.value;
        if (v === 'auto') {
            try { localStorage.setItem('txt_layout', 'auto'); } catch (x) {}
            if (txtAutoLayout() !== currentLayout) setLayout(txtAutoLayout());
            return;
        }
        try { localStorage.removeItem('txt_layout'); } catch (x) {}
        setLayout(v);
    });
    // 자동일 때 화면을 돌려 가로↔세로가 바뀌면 보던 곳 그대로 바꿈(폭이 바뀔 때만 — 화면 키보드로 높이만 바뀌는 것 무시)
    let txtRotW = window.innerWidth, txtRotT = 0;
    window.addEventListener('resize', () => {
        if (window.innerWidth === txtRotW) return;
        txtRotW = window.innerWidth;
        clearTimeout(txtRotT);
        txtRotT = setTimeout(() => {
            const sel = document.getElementById('layoutSelect');
            if (!(sel && sel.value === 'auto' && window.__mcTxtInitDone && txtAutoLayout() !== currentLayout)) return;
            // ★ (2026-11-04 18:30) 2단에서 아주 먼 곳(1단 본문에 그린 곳보다 100만 자 넘게 뒤)이면 1단으로 바꾸지 않음 — 1단 본문에 수백만 자를 넣어 멈추는 것 방지(목차로 먼 곳 갈 때와 같은 기준)
            if (txtAutoLayout() === 'single') { try { if (getDualModeCharIndex() - Math.max(0, domLen()) >= TXT_FAR) return; } catch (x) {} }
            setLayout(txtAutoLayout());
        }, 450);
    });
    // 처음 열 때: 자동을 골라 두었으면 — 가로 화면이면 2단으로(1단→2단만. 아주 먼 곳으로 열려 이미 쪽 모드면 그대로)
    function txtAutoInit() {
        let pref = ''; try { pref = localStorage.getItem('txt_layout') || ''; } catch (x) {}
        if (pref !== 'auto') return;
        const sel = document.getElementById('layoutSelect');
        if (sel && currentLayout === 'single') { sel.value = 'auto'; if (txtAutoLayout() !== 'single') setLayout(txtAutoLayout()); }
    }
    
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
                // ★ (2026-11-01) 보던 첫 글자부터 다시 쪽을 만듦(쪽 크기가 바뀌므로 지나온 쪽 기억·평균은 새로)
                resetPageStats();
                showPageAt(pageStart);
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
                resetPageStats(); // ★ (2026-11-01) 보던 첫 글자부터 다시
                showPageAt(pageStart);
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
        if (e.target && (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName || '') || e.target.isContentEditable)) return; // ★ (2026-11-02 10:01) 쪽 번호 입력칸 등에서는 글자 편집(전에는 ←→ 가 쪽을 넘김)
        if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return; // ★ (2026-11-02 19:21) 보조키(Alt+← 뒤로 등)는 쪽 넘김 아님
        
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
    } else if (<?php echo (int)$txt_settings['default_font_size']; ?> !== 18) {
        contentEl.style.fontSize = '<?php echo (int)$txt_settings['default_font_size']; ?>px'; // ★ (2026-11-02 14:30) 관리자 기본 글자 크기가 화면 숫자에만 나오고 본문엔 안 들던 것(18 그대로면 전처럼 CSS 값 — 휴대폰 16px)
    }
    
    if (savedLineHeight) {
        contentEl.style.lineHeight = savedLineHeight;
        document.getElementById('lineHeightSlider').value = savedLineHeight;
        document.getElementById('lineHeightValue').textContent = savedLineHeight;
    } else if (<?php echo json_encode((float)$txt_settings['default_line_height']); ?> !== 1.8) {
        contentEl.style.lineHeight = '<?php echo (float)$txt_settings['default_line_height']; ?>'; // ★ (2026-11-02 14:30) 위와 같음
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
            
            // ★ (2026-11-03 08:30) 아래 끝 가까이면 다음 글을 직접 이어 붙임 — 50ms마다 스크롤해 스크롤 쪽 이어 붙이기(100ms 뒤 실행)가 매번 취소돼, 화면에 그린 곳 끝에서 멈췄음
            const more = !allLoaded || (domLen() >= 0 && domLen() < fullContent.length);
            const failing = chunkFailT > 0; // ★ (2026-11-03 09:08) 읽기에 실패했으면(로그인 풀림 등) 3초에 한 번만 다시 시도하고, 끝에 닿으면 예전처럼 멈춤 — 50ms 마다 요청이 나갔음
            if (more && currentLayout === 'single' && scrollTop + clientHeight >= scrollHeight - 500 && (!failing || Date.now() - chunkFailT > 3000)) loadChunk();
            if ((!more || failing) && scrollTop + clientHeight >= scrollHeight - 10) {
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
    let lastColSize = '';
    window.addEventListener('resize', () => {
        if (resizeTimeout) clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            if (currentLayout === 'single') { fitPageBox(); return; } // ★ (2026-11-01) 머리줄 높이가 바뀌어도 첫 줄이 가리지 않게
            if (currentLayout !== 'single') {
 
                // PC에서만 터치 영역 표시
                if (window.innerWidth >= 768) {
                    touchLeft.style.display = 'block';
                    touchRight.style.display = 'block';
                } else {
                    touchLeft.style.display = 'none';
                    touchRight.style.display = 'none';
                }
                
                // ★ (2026-11-01) 보던 첫 글자부터 다시 쪽을 만듦(화면 크기가 바뀌면 쪽 크기도 바뀜) — 칸 크기가 그대로면(휴대폰 주소창 등) 그냥 둠
                const sz = columnLeft.clientWidth + 'x' + columnLeft.clientHeight + 'x' + (twoColumns() ? 2 : 1);
                if (sz === lastColSize) return;
                lastColSize = sz;
                resetPageStats();
                showPageAt(pageStart);
            }
        }, 200);
    });
    
    // 초기화
    async function init() {
        try { fitPageBox(); } catch (e) {} // ★ (2026-11-01) 첫 줄이 머리줄에 가리지 않게(복원 스크롤 전에)
        const ok = await loadMeta();
        if (!ok) return;
        
        // 첫 청크 로드
        await loadChunk();
        await loadChunk();
        
        // 저장된 위치로 복원 (position 기반)
        // ★ (2026-11-03 08:30) 저장 위치가 아주 멀면(화면에 그린 곳보다 100만 자 넘게 뒤) 목차로 먼 곳에 갈 때와 같게 — 본문에 그리지 않고 다 읽은 뒤 쪽 모드로 그 자리
        //   (전엔 조각마다 1단 본문에 이어 붙여 다시 열 때 수십 초~수 분 멈췄음)
        if (SAVED_POSITION > 0 && SAVED_POSITION - Math.max(0, domLen()) >= TXT_FAR) {
            await loadAllText();
            currentCharIndex = SAVED_POSITION;
            if (await txtFarJump(SAVED_POSITION)) {
                progressFill.style.width = SAVED_PERCENT + '%';
                readProgress.textContent = Math.round(SAVED_PERCENT) + '%';
                setTimeout(() => { isRestoring = false; }, 500);
                return;
            }
        }
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
                        window.scrollTo(0, Math.max(0, window.pageYOffset + rect.top - topSafe() - (currentLayout === 'single' ? SAVED_YOFF : 0))); // ★ (2026-11-02 01:40) 저장 때 줄 위치 그대로 / ★ (2026-11-01) 머리줄 바로 아래로(예전 50px 고정 — 휴대폰은 머리줄이 두 줄이라 그 글이 머리줄 밑에 가려졌음)
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
    
    // ============================================================
    // ★ (2026-10-31) 책 노트 — 형광펜·밑줄·메모·책갈피 (공통 화면: js/book-notes.js)
    //   위치 = 전체 글(fullContent)의 글자 번호 s~e. 표시는 CSS Custom Highlight API(본문 글을 바꾸지 않음 — 지원 안 하는 옛 브라우저는 표시만 없고 목록·이동은 됨)
    //   파일이 바뀌어 글자 번호가 어긋나면 저장해 둔 고른 글로 근처를 다시 찾아 맞춤
    // ============================================================
    (function setupTxtNotes() {
        if (!window.MCNotes) return;
        const HAS_HL = !!(window.CSS && CSS.highlights && window.Highlight);
        const NT = <?php echo json_encode([
            'highlight' => __('bn_highlight'), 'underline' => __('bn_underline'), 'memo' => __('bn_memo'), 'memo_edit' => __('bn_memo_edit'),
            'copy' => __('bn_copy'), 'del' => __('bn_delete'), 'save' => __('bn_save'), 'cancel' => __('bn_cancel'), 'close' => __('bn_close'),
            'memo_ph' => __('bn_memo_ph'), 'panel' => __('bn_panel'), 'tab_all' => __('bn_tab_all'), 'tab_bm' => __('bn_tab_bm'),
            'tab_mark' => __('bn_tab_mark'), 'tab_memo' => __('bn_tab_memo'), 'all_colors' => __('bn_all_colors'), 'empty' => __('bn_empty'),
            'empty_filter' => __('bn_empty_filter'), 'export' => __('bn_export'), 'copied' => __('bn_copied'), 'save_fail' => __('bn_save_fail'),
            'too_many' => __('bn_too_many'), 'confirm_del' => __('bn_confirm_del'), 'bm_add' => __('bn_bm_add'), 'bm_remove' => __('bn_bm_remove'),
            'bm_added' => __('bn_bm_added'), 'bm_removed' => __('bn_bm_removed'),
            'c_yellow' => __('bn_c_yellow'), 'c_green' => __('bn_c_green'), 'c_blue' => __('bn_c_blue'), 'c_pink' => __('bn_c_pink'), 'c_purple' => __('bn_c_purple'),
            'c_orange' => __('bn_c_orange'), 'c_gray' => __('bn_c_gray'), 'c_coral' => __('bn_c_coral'), 'c_mint' => __('bn_c_mint'), 'listen_here' => __('bn_listen_here'), /* ★ fix40 */ 'color_filter' => __('bn_color_filter'), 'all_colors_tip' => __('bn_all_colors_tip'), 'color_only' => __('bn_color_only'), 'memo_badge' => __('bn_memo_badge'), // ★ (2026-11-07 fix39) 주황·회색 · 색 거르기 설명 · 메모 표
            'dict' => __('bn_dict'), 'translate' => __('bn_translate'), // ★ (2026-11-01) 사전 찾기 · 번역
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        // 지금 화면에 있는 글 조각 [{node, base}] (1단: 본문 하나 / 2단: 왼쪽·오른쪽 칸)
        function segs() {
            if (currentLayout === 'single') { const n = contentEl.firstChild; return (n && n.nodeType === 3) ? [{ node: n, base: 0 }] : []; }
            const out = [], lb = pageStart, ln = columnLeft.firstChild, rn = columnRight.firstChild;
            if (ln && ln.nodeType === 3) out.push({ node: ln, base: lb });
            if (rn && rn.nodeType === 3) out.push({ node: rn, base: lb + ((ln && ln.nodeType === 3) ? ln.length : 0) });
            return out;
        }
        function globalOf(node, off) {
            const ss = segs();
            for (const g of ss) if (g.node === node) return g.base + off;
            // 칸(요소) 자체가 잡힌 경우
            for (const g of ss) if (g.node.parentNode === node) return g.base + (off > 0 ? g.node.length : 0);
            return -1;
        }
        function rangesOf(s, e) {
            const out = [];
            segs().forEach(g => {
                const a = Math.max(s, g.base), b = Math.min(e, g.base + g.node.length);
                if (b > a) { try { const r = document.createRange(); r.setStart(g.node, a - g.base); r.setEnd(g.node, b - g.base); out.push(r); } catch (x) {} }
            });
            return out;
        }
        // 글자 번호가 고른 글과 맞는지 — 어긋나면 근처에서 다시 찾음(메모리에서만 고침)
        //   서버는 고른 글(q)의 줄바꿈·앞뒤 공백을 정리해 저장하므로 공백을 무시하고 비교
        const norm = (t) => String(t || '').replace(/\s+/g, ' ').trim();
        function resolve(it) {
            if (!it || typeof it.s !== 'number') return null;
            const want = norm(it.q).slice(0, 30);
            if (!want) return it;
            const span = Math.max(it.e - it.s, 120);
            if (norm(fullContent.substring(it.s, it.s + span)).startsWith(want)) return it;
            if (fullContent.length < it.s && !allLoaded) return it; // 아직 그 부분을 안 읽음
            const re = new RegExp(want.split(' ').map(w => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('\\s+'));
            const lo = Math.max(0, it.s - 20000);
            let m = re.exec(fullContent.substring(lo, it.s + 20000)), at = m ? lo + m.index : -1;
            if (at < 0 && allLoaded) { m = re.exec(fullContent); at = m ? m.index : -1; }
            if (at < 0) return null;
            const len = it.e - it.s; it.s = at; it.e = at + len; return it;
        }
        let paintT = 0, painted = [];
        function paint() {
            paintT = 0;
            if (!HAS_HL) return;
            painted.forEach(k => CSS.highlights.delete(k)); painted = [];
            const groups = {};
            notes.items().forEach(it => {
                if (it.k === 'bm') return;
                const r = resolve(it); if (!r) return;
                const key = (it.k === 'ul' ? 'mcn-ul-' : 'mcn-hl-') + (MCNotes.FILL[it.c] ? it.c : 'yellow');
                (groups[key] = groups[key] || []).push(...rangesOf(r.s, r.e));
            });
            Object.keys(groups).forEach(k => { if (groups[k].length) { CSS.highlights.set(k, new Highlight(...groups[k])); painted.push(k); } });
            try { notes.badges(); } catch (e) {} // ★ fix39 다시 칠할 때(쪽 넘김·청크 추가·1단↔2단) 메모 표도 다시 놓음
        }
        function schedule() { if (!paintT) paintT = requestAnimationFrame(paint); }
        const pctOf = (i) => Math.min(100, Math.round(i / estTotalChars() * 1000) / 10); // ★ (2026-11-01) 화면 진행%와 같은 어림
        function caretAt(x, y) {
            try {
                if (document.caretRangeFromPoint) { const r = document.caretRangeFromPoint(x, y); if (r) return globalOf(r.startContainer, r.startOffset); }
                if (document.caretPositionFromPoint) { const p = document.caretPositionFromPoint(x, y); if (p) return globalOf(p.offsetNode, p.offset); }
            } catch (e) {}
            return -1;
        }
        function visibleSpan() {
            if (currentLayout === 'single') {
                const r = contentEl.getBoundingClientRect();
                const start = getVisibleCharIndex();
                let end = caretAt(r.left + Math.min(r.width - 10, 400), Math.min(window.innerHeight - 50, r.bottom - 5));
                if (end < start) end = start + 1500;
                return [start, end];
            }
            const ss = segs(); if (!ss.length) return [0, 0];
            const last = ss[ss.length - 1];
            return [ss[0].base, last.base + last.node.length];
        }
        const adapter = {
            kind: 'txt',
            draw() { schedule(); },
            undraw() { schedule(); },
            async go(it) {
                const r = resolve(it) || it;
                await txtEnsureFor(r.e + 10);
                if (currentLayout === 'single') {
                    if (await txtFarJump(r.s)) { schedule(); setTimeout(() => notes.relocated(), 120); return; }
                    domTo(r.e + CHUNK_SIZE);
                    const rs = rangesOf(r.s, Math.max(r.e, r.s + 1));
                    if (rs.length) { const b = rs[0].getBoundingClientRect(); window.scrollTo(0, Math.max(0, window.pageYOffset + b.top - topSafe() - 30)); }
                } else {
                    // ★ (2026-11-01) 그 글이 지금 쪽에 없으면 그 줄 처음부터 쪽을 만듦
                    if (r.s < pageStart || r.s >= pageEnd) { pageHist = []; await showPageAt(lineStartNear(r.s)); }
                }
                schedule(); setTimeout(() => notes.relocated(), 120);
            },
            here() {
                const s0 = currentLayout === 'single' ? getVisibleCharIndex() : pageStart;
                return { pos: { s: s0, e: s0 }, q: fullContent.substring(s0, s0 + 80).replace(/\s+/g, ' ').trim(), ch: '', p: pctOf(s0) };
            },
            onPage(bm) { const v = visibleSpan(); return bm.s >= v[0] && bm.s <= v[1]; },
            compare(a, b) { return (a.s || 0) - (b.s || 0); },
            // ★ (2026-11-07 fix39) 메모 표 자리 — 표시 글의 마지막 줄(지금 화면에 있는 글 조각만), 화면 좌표
            rectOf(it) {
                const r = resolve(it); if (!r) return null;
                const rs = rangesOf(r.s, r.e); if (!rs.length) return null;
                const cr = Array.prototype.filter.call(rs[rs.length - 1].getClientRects(), x => x.width > 0 || x.height > 0);
                if (!cr.length) return null;
                const l = cr[cr.length - 1]; return { left: l.left, right: l.right, top: l.top, bottom: l.bottom };
            },
            area() { const r = readerEl.getBoundingClientRect(); return { left: r.left, right: r.right, top: Math.max(0, r.top), bottom: Math.min(innerHeight, r.bottom) }; }
        };
        const notes = MCNotes.init({
            api: 'txt_viewer.php', file: FILE, bidx: BIDX, csrf: <?php echo json_encode(generate_csrf_token()); ?>,
            t: NT, title: <?php echo json_encode($title, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, adapter,
            bmButton: document.getElementById('btnBookmark'), panelButton: document.getElementById('btnNotes'), toolbar: document.querySelector('.txt-toolbar')
        });
        // 본문이 바뀌면(청크 추가·쪽 넘김·1단↔2단) 다시 칠함
        try { new MutationObserver(() => { schedule(); }).observe(readerEl, { childList: true, subtree: true, characterData: true }); } catch (e) {}
        // 글 고르기 — 마우스는 놓을 때, 손가락은 고르기가 멈췄을 때
        const coarse = (() => { try { return matchMedia('(pointer: coarse)').matches; } catch (e) { return false; } })();
        function checkSel() {
            const sel = window.getSelection(); if (!sel || sel.isCollapsed || !sel.rangeCount) return;
            const rg = sel.getRangeAt(0);
            if (!readerEl.contains(rg.commonAncestorContainer)) return;
            let s = globalOf(rg.startContainer, rg.startOffset), e = globalOf(rg.endContainer, rg.endOffset);
            if (s < 0 || e < 0) return; if (e < s) { const t = s; s = e; e = t; }
            if (e - s < 1 || e - s > 200000) return;
            const q = fullContent.substring(s, e);
            if (!q.trim()) return;
            notes.selected({ pos: { s, e }, q: q.slice(0, 1000), ch: '', p: pctOf(s), rect: rg.getBoundingClientRect(), clear: () => { try { sel.removeAllRanges(); } catch (x) {} } });
        }
        let selT = 0;
        document.addEventListener('mouseup', (e) => { if (e.button === 0) setTimeout(checkSel, 10); });
        document.addEventListener('selectionchange', () => { if (!coarse) return; clearTimeout(selT); selT = setTimeout(checkSel, 500); });
        // 표시한 글 누르기 → 고치기 메뉴 (2단 칸 누르기의 쪽 넘김보다 먼저 — 고른 글이 있거나 표시를 눌렀으면 쪽을 넘기지 않음)
        readerEl.addEventListener('click', (e) => {
            const sel = window.getSelection();
            if (sel && !sel.isCollapsed) { e.stopPropagation(); return; }
            const idx = caretAt(e.clientX, e.clientY); if (idx < 0) return;
            const hit = notes.items().filter(it => it.k !== 'bm' && typeof it.s === 'number').map(resolve).filter(Boolean).find(it => idx >= it.s && idx < it.e);
            if (!hit) return;
            e.stopPropagation(); e.preventDefault();
            notes.clicked(hit, { left: e.clientX - 1, right: e.clientX + 1, top: e.clientY - 10, bottom: e.clientY + 10 });
        }, true);
        // 쪽·스크롤 위치가 바뀌면 책갈피 단추 갱신
        let relT = 0; const rel = () => { clearTimeout(relT); relT = setTimeout(() => notes.relocated(), 250); };
        window.addEventListener('scroll', rel, { passive: true });
        try { new MutationObserver(rel).observe(columnLeft, { childList: true, characterData: true, subtree: true }); } catch (e) {}
        notes.load().then(() => { schedule(); rel(); window.__mcTxtNotesReady = true; if (window.__mcTxtJump) window.__mcTxtJump(); });
        // ★ (2026-11-03 00:06) 북마크 화면의 책갈피에서 열었으면(?note=아이디) — 노트와 첫 화면(init) 둘 다 끝난 뒤 그 자리로
        window.__mcTxtJump = function () {
            if (!window.__mcTxtNotesReady || !window.__mcTxtInitDone || window.__mcTxtJumped) return;
            window.__mcTxtJumped = true;
            try { const nid = new URLSearchParams(location.search).get('note'); if (nid && /^[a-z0-9]{8,32}$/.test(nid)) notes.goNote(nid); } catch (e) {}
        };
    })();

    // ============================================================
    // ★ (2026-11-01) 읽기 도구 — 목차(장 제목 자동 찾기) · 본문 검색 · 듣기(TTS) · 화면 꺼짐 방지 (공통 화면: js/book-tools.js)
    //   (펜닐 요청: 전자책 앱과 비교해 빠진 기능) 글 위치는 노트와 같은 '전체 글 글자 번호'
    // ============================================================
    // 남은 글을 한 번에 읽어 옴 — 1단 화면은 다 읽은 뒤 한 번만 바꿈(조각마다 본문 전체를 다시 그리면 큰 파일에서 느려짐)
    let loadAllP = null;
    function loadAllText() {
        if (allLoaded) return Promise.resolve();
        if (loadAllP) return loadAllP;
        loadAllP = (async () => {
            while (isLoading) await new Promise(r => setTimeout(r, 50)); // 다른 곳에서 읽는 중이면 기다림
            isLoading = true; loadingEl.classList.remove('hidden');
            try {
                while (!allLoaded) {
                    const res = await fetch(`txt_viewer.php?action=get_chunk&file=${encodeURIComponent(FILE)}&offset=${currentOffset}&length=${2 * 1024 * 1024}&bulk=1&bidx=${BIDX}`); // ★ 한 번에 2MB 까지
                    const data = await res.json();
                    if (!data || data.error) break;
                    if (data.content) fullContent += data.content;
                    const before = currentOffset;
                    currentOffset = data.offset;
                    // ★ 목차·검색 창의 '읽어 오는 중' 에 진행률
                    try { const pr = (typeof data.progress === 'number') ? Math.floor(data.progress) : null; if (pr !== null) document.querySelectorAll('.mct-panel .mct-empty').forEach(el => { if (!el.dataset.base) el.dataset.base = el.textContent; el.textContent = el.dataset.base + ' ' + pr + '%'; }); } catch (e) {}
                    if (!data.hasMore) { allLoaded = true; loadingEl.classList.add('hidden'); }
                    else if (currentOffset === before) break; // 더 못 읽음
                }
            } catch (e) { console.error('청크 로드 오류:', e); }
            isLoading = false;
            // ★ (2026-11-03 01:21) 본문에는 넣지 않음 — 이동할 때(txtGoChar 등) 필요한 곳까지만 넣음(50MB 에서 43초 멈춤의 원인)
        })();
        return loadAllP.finally(() => { loadAllP = null; });
    }
    // 화면에 있는 글 조각 [{node, base}] → 글자 번호 s~e 의 Range 들 (노트와 같은 방식)
    function txtSegs() {
        if (currentLayout === 'single') { const n = contentEl.firstChild; return (n && n.nodeType === 3) ? [{ node: n, base: 0 }] : []; }
        const out = [], ln = columnLeft.firstChild, rn = columnRight.firstChild;
        if (ln && ln.nodeType === 3) out.push({ node: ln, base: pageStart });
        if (rn && rn.nodeType === 3) out.push({ node: rn, base: pageStart + ((ln && ln.nodeType === 3) ? ln.length : 0) });
        return out;
    }
    function txtRanges(s, e) {
        const out = [];
        txtSegs().forEach(g => {
            const a = Math.max(s, g.base), b = Math.min(e, g.base + g.node.length);
            if (b > a) { try { const r = document.createRange(); r.setStart(g.node, a - g.base); r.setEnd(g.node, b - g.base); out.push(r); } catch (x) {} }
        });
        return out;
    }
    const HAS_CSS_HL = !!(window.CSS && CSS.highlights && window.Highlight);
    function setMark(name, s, e) { if (!HAS_CSS_HL) return; const rs = txtRanges(s, e); if (rs.length) CSS.highlights.set(name, new Highlight(...rs)); else CSS.highlights.delete(name); }
    function clearMark(name) { if (HAS_CSS_HL) CSS.highlights.delete(name); }
    // 글자 번호 s 로 이동(1단: 검색은 화면 위쪽 1/3 쯤에, 목차는 머리줄 바로 아래 / 쪽 모드: 그 글이 있는 쪽)
    // ★ (2026-11-03 01:21) 1단에서 본문에 아직 안 넣은 아주 먼 곳(수백만 글자 뒤)으로 가면 그 앞을 모두 그려야 해 브라우저가 멈춤 → 그때만 쪽 모드로 바꿔 그 쪽만 그림
    const TXT_FAR = 1000000; // 이보다 멀면 쪽 모드로(1단 본문 100만 글자 그리기 ≈ 2초)
    function txtSay(msg) { // 잠깐 뜨는 알림
        try { let t = document.getElementById('txtFarToast'); if (!t) { t = document.createElement('div'); t.id = 'txtFarToast'; t.style.cssText = 'position:fixed;left:50%;bottom:70px;transform:translateX(-50%);z-index:3200;background:#202124;color:#fff;padding:8px 14px;border-radius:18px;font-size:13px;max-width:90vw;text-align:center;opacity:.95;pointer-events:none'; document.body.appendChild(t); } t.textContent = msg; t.style.display = ''; clearTimeout(t._h); t._h = setTimeout(() => { t.style.display = 'none'; }, 3500); } catch (e) {}
    }
    async function txtFarJump(s) {
        if (currentLayout !== 'single') return false;
        if (allLoaded) s = Math.min(s, fullContent.length); // ★ (2026-11-03 09:08) 저장 위치가 파일 끝보다 뒤면(파일이 짧게 바뀜) 끝 기준 — 작은 파일인데 쪽 모드로 바뀌고 '글이 매우 길어' 알림이 떴음
        const have = Math.max(0, domLen());
        if (s - have < TXT_FAR) return false;
        const sel = document.getElementById('layoutSelect');
        setLayout('dual-ltr'); if (sel) sel.value = 'dual-ltr';
        for (let k = 0; k < 100 && isPageLoading; k++) await new Promise(r => setTimeout(r, 30));
        pageHist = []; await showPageAt(lineStartNear(s));
        txtSay(<?php echo json_encode(__('txt_far_jump_page'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        return true;
    }
    // ★ (2026-11-03 01:47) 아직 안 읽어 온 먼 곳(1단 화면 끝보다 100만 자 넘게 뒤)이면 본문에 그리지 않는 전체 읽기로 — 조각 읽기는 1단 본문에 계속 이어 붙여 50MB 에서 멈췄음
    async function txtEnsureFor(t) {
        if (!allLoaded && fullContent.length < t && currentLayout === 'single' && t - Math.max(0, domLen()) >= TXT_FAR) await loadAllText();
        else await ensureContentLoaded(t);
    }
    async function txtGoChar(s, e, atTop) {
        await txtEnsureFor(e + 10);
        for (let k = 0; k < 100 && isPageLoading; k++) await new Promise(r => setTimeout(r, 30)); // ★ (2026-11-01 17:05) 쪽을 그리는 중이면 끝난 뒤에(그새 부르면 쪽 이동이 그냥 무시됐음)
        if (currentLayout === 'single') {
            if (await txtFarJump(s)) return;
            domTo(e + CHUNK_SIZE);
            const rs = txtRanges(s, Math.max(e, s + 1));
            if (rs.length) { const b = rs[0].getBoundingClientRect(); window.scrollTo(0, Math.max(0, window.pageYOffset + b.top - (atTop ? topSafe() + 2 : Math.max(topSafe() + 20, Math.round(window.innerHeight / 3))))); }
        } else if (s < pageStart || s >= pageEnd) {
            pageHist = []; await showPageAt(lineStartNear(s));
        }
    }
    // 지금 보는 첫 글자 — 1단은 글자 위치를 직접 재서 찾음(설정·검색 창이 본문 위에 떠 있어도 맞게: 화면 좌표로 짚는 방식은 그 창을 짚음)
    function txtHere() {
        if (currentLayout !== 'single') return pageStart;
        const n = contentEl.firstChild; if (!n || n.nodeType !== 3) return 0;
        const yt = topSafe() - 4, len = n.length, r = document.createRange();
        const botOf = (i) => { for (let k = i; k < Math.min(len, i + 8); k++) { r.setStart(n, k); r.setEnd(n, k + 1); const b = r.getBoundingClientRect(); if (b.height > 0) return b.bottom; } return null; };
        let lo = 0, hi = len;
        while (lo < hi) { const mid = (lo + hi) >> 1; const bt = botOf(mid); if (bt === null || bt <= yt) lo = mid + 1; else hi = mid; }
        return Math.min(lo, len);
    }

    // 목차: 장 제목처럼 생긴 줄(짧은 줄) — 제1장·第1章·1화·12장·Chapter 1·Part II·프롤로그·에필로그·외전·후기·[3화]·# 제목
    const TOC_RES = [
        /^제\s*[0-9０-９一二三四五六七八九十백百千]+\s*(장|화|편|부|권|절|막|회|話|话|章)(?=$|[\s.:：\-—–)\]】」』])/,
        /^第\s*[0-9０-９一二三四五六七八九十百千]+\s*[章話话回部卷節节]/,
        /^[0-9]{1,4}\s*(화|장|편|회)(?=$|[\s.:：\-—–)\]】」』])/,
        /^(chapter|ch\.|part|episode|ep\.|book)\s*([0-9]+|[ivxlc]+)\b/i,
        /^(prologue|epilogue|interlude|afterword|foreword|preface)\b/i,
        /^(프롤로그|에필로그|서장|종장|외전|작가\s*후기|후기|작가의\s*말|서문|머리말|들어가며|맺음말|번외편?|특별편|막간)(?=$|[\s.:：\-—–(0-9])/,
        /^[\[<【〈《「『(]\s*(제\s*)?[0-9]{1,4}\s*(화|장|편|회)/,
        /^#{1,3}\s+\S/
    ];
    let tocCache = null, tocLen = -1;
    function buildToc() {
        if (tocCache && tocLen === fullContent.length) return tocCache;
        const out = []; const txt = fullContent; let i = 0; const n = txt.length;
        while (i < n && out.length < 3000) {
            let j = txt.indexOf('\n', i); if (j < 0) j = n;
            if (j - i <= 80) {
                const raw = txt.slice(i, j); const line = raw.replace(/^[\s\u3000\ufeff]+|[\s\u3000]+$/g, '');
                if (line && line.length <= 50 && !/(다|요|죠|까)[.!?]?$/.test(line) && TOC_RES.some(re => re.test(line))) {
                    const lead = raw.length - raw.replace(/^[\s\u3000\ufeff]+/, '').length;
                    if (!out.length || out[out.length - 1].label !== line) out.push({ label: line.replace(/^#{1,3}\s+/, ''), s: i + lead });
                }
            }
            i = j + 1;
        }
        tocCache = out; tocLen = fullContent.length;
        return out;
    }
    function chapterAt(idx) { const t = buildToc(); let lb = ''; for (const it of t) { if (it.s <= idx) lb = it.label; else break; } return lb; }

    (function setupTxtTools() {
        if (!window.MCTools) return;
        const TT = <?php echo json_encode([
            'search' => __('bt_search'), 'search_ph' => __('bt_search_ph'), 'search_none' => __('bt_search_none'), 'search_count' => __('bt_search_count'),
            'search_more' => __('bt_search_more'), 'searching' => __('bt_searching'), 'prev' => __('bt_prev'), 'next' => __('bt_next'), 'close' => __('bt_close'),
            'toc_none' => __('bt_toc_none'), 'toc_loading' => __('bt_toc_loading'),
            'tts' => __('bt_tts'), 'tts_pause' => __('bt_tts_pause'), 'tts_resume' => __('bt_tts_resume'), 'tts_stop' => __('bt_tts_stop'),
            'tts_slower' => __('bt_tts_slower'), 'tts_faster' => __('bt_tts_faster'), 'tts_none' => __('bt_tts_none'), 'tts_end' => __('bt_tts_end'), 'tts_voice' => __('bt_tts_voice'), 'tts_voice_auto' => __('bt_tts_voice_auto'), 'tts_voice_more' => __('bt_tts_voice_more'), 'tts_voice_help' => __('bt_tts_voice_help'), /* ★ fix40 목소리 더 받는 법 */ /* ★ fix39 목소리 고르기 */
            'wake_none' => __('bt_wake_none'),
            'ch_left' => __('bt_ch_left'), 'ch_last' => __('bt_ch_last'), 'rt_line' => __('bt_rt_line'), 'rt_h' => __('bt_rt_h'), 'rt_m' => __('bt_rt_m'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const toolbar = document.querySelector('.txt-toolbar');
        const bottomGap = () => { const f = document.querySelector('.txt-footer'); return f ? Math.round(f.getBoundingClientRect().height) : 33; };

        // ── 목차 ──
        MCTools.list({
            t: TT, button: document.getElementById('btnToc'), toolbar, bottom: bottomGap(), side: 'r', // 오른쪽: 왼쪽에 열면 PC 에서 본문 왼쪽 끝을 가려 '보는 첫 글자'(진행 저장)를 못 잼
            title: <?php echo json_encode(__('bt_toc'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            build: async () => {
                await loadAllText();
                const here = txtHere(); const t = buildToc(); let act = -1;
                t.forEach((it, k) => { if (it.s <= here + 5) act = k; });
                return t.map((it, k) => ({ label: it.label, s: it.s, active: k === act }));
            },
            go: (it) => { txtGoChar(it.s, it.s + 1, true); }
        });

        // ── 본문 검색 ──
        let hitT = 0;
        MCTools.search({
            t: TT, button: document.getElementById('btnSearch'), toolbar, bottom: bottomGap(),
            run: async (q, stale) => {
                await loadAllText();
                if (stale()) return null;
                const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/ /g, '\\s+');
                let re; try { re = new RegExp(esc, 'giu'); } catch (e) { re = new RegExp(esc, 'gi'); }
                const hits = [], LIMIT = 500; let capped = false, m;
                buildToc();
                while ((m = re.exec(fullContent))) {
                    if (!m[0]) { re.lastIndex++; continue; }
                    if (hits.length >= LIMIT) { capped = true; break; }
                    const i = m.index, j = i + m[0].length;
                    const x = (i > 40 ? '…' : '') + fullContent.slice(Math.max(0, i - 40), j + 60).replace(/\s+/g, ' ').trim() + (j + 60 < fullContent.length ? '…' : '');
                    hits.push({ s: i, e: j, x, ch: chapterAt(i), p: percentAt(i, false) });
                }
                return { hits, capped };
            },
            go: async (hit) => {
                clearTimeout(hitT);
                await txtGoChar(hit.s, hit.e);
                requestAnimationFrame(() => setMark('mct-hit', hit.s, hit.e));
                hitT = setTimeout(() => clearMark('mct-hit'), 4000);
            }
        });

        // ── 듣기: 지금 화면 첫 글자부터 문장 단위. 읽는 문장이 화면 밖이면 스크롤·쪽 넘김 ──
        const ttsCtl = MCTools.tts({
            t: TT, button: document.getElementById('btnTts'), note: document.getElementById('ttsNote'),
            onStart: () => { document.getElementById('settingsPanel').classList.remove('show'); },
            source: {
                start: async (from) => {
                    let pos = txtHere();
                    // ★ (2026-11-07 fix40) 고른 글부터 듣기 — 그 글이 든 문장의 처음부터(낱말 중간에서 시작하지 않게)
                    if (from && typeof from.s === 'number' && from.s >= 0) {
                        pos = from.s;
                        const lo = Math.max(0, from.s - 600), w = fullContent.substring(lo, from.s + 1500), off = from.s - lo;
                        const hit = MCTools.sentences(w).find(x => x[0] <= off && off < x[1]);
                        if (hit) pos = lo + hit[0];
                    }
                    return {
                        next: async () => {
                            for (let guard = 0; guard < 200; guard++) {
                                await ensureContentLoaded(pos + 3000);
                                if (pos >= fullContent.length) return null;
                                const win = fullContent.substring(pos, pos + 1500);
                                const ss = MCTools.sentences(win);
                                // 창 끝에서 잘린 문장이면(더 읽을 글이 있으면) 다음 창에서 — 첫 문장은 1500자 안에 끝남
                                if (!ss.length) { pos += win.length; continue; }
                                const s = pos + ss[0][0], e = pos + ss[0][1];
                                pos = e;
                                return {
                                    text: fullContent.substring(s, e).replace(/\s+/g, ' '),
                                    show: async () => {
                                        if (currentLayout === 'single') {
                                            domTo(e + CHUNK_SIZE);
                                            const rs = txtRanges(s, e); if (!rs.length) return;
                                            const r = rs[0].getBoundingClientRect(); const top = topSafe();
                                            if (r.top < top || r.bottom > window.innerHeight - 60) window.scrollTo(0, Math.max(0, window.pageYOffset + r.top - top - Math.round(window.innerHeight * 0.2)));
                                        } else if (s < pageStart) {
                                            pageHist = []; await showPageAt(lineStartNear(s));
                                        } else {
                                            for (let k = 0; k < 50 && s >= pageEnd && pageEnd < fullContent.length; k++) { const pe = pageEnd; await nextPage(); if (pageEnd === pe) break; }
                                        }
                                    },
                                    mark: () => setMark('mct-tts', s, e),
                                    unmark: () => clearMark('mct-tts')
                                };
                            }
                            return null;
                        }
                    };
                }
            }
        });

        MCTools.wake({ t: TT, checkbox: document.getElementById('chkWake'), note: document.getElementById('wakeNote') });
        if (ttsCtl && window.MCNotes && MCNotes.setListen) MCNotes.setListen((pos) => { if (pos && typeof pos.s === 'number') ttsCtl.startAt({ s: pos.s }); }); // ★ (2026-11-07 fix40) 글을 고르면 메뉴에 '여기부터 듣기'

        // ── ★ (2026-11-01 11:22) 글꼴 · 양쪽 정렬 · 좌우 여백 (이 기기에 기억) — 바꾸면 보던 첫 글자부터 다시 배치 ──
        const FONT_STACK = {
            gothic: "'Noto Sans KR','Apple SD Gothic Neo','Malgun Gothic','맑은 고딕','Nanum Gothic',sans-serif",
            serif: "'Noto Serif KR','Nanum Myeongjo','AppleMyungjo','Batang','바탕',serif"
        };
        const rp = (k) => { try { return localStorage.getItem(k) || ''; } catch (e) { return ''; } };
        const wp = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };
        function applyLook() {
            const f = FONT_STACK[rp('txt_font')] || '';
            readerEl.style.fontFamily = f; contentEl.style.fontFamily = f; // 2단 칸은 readerEl 글꼴을 물려받음
            readerEl.classList.toggle('mc-justify', rp('txt_justify') === '1');
            const m = rp('txt_margin');
            readerEl.classList.toggle('mc-mg-1', m === '1'); readerEl.classList.toggle('mc-mg-2', m === '2');
        }
        function relayoutKeep(fn) {
            const keep = txtHere();
            fn(); applyLook();
            if (currentLayout === 'single') {
                requestAnimationFrame(() => { txtGoChar(keep, keep + 1, true); });
            } else {
                (async () => { for (let k = 0; k < 100 && isPageLoading; k++) await new Promise(r => setTimeout(r, 30)); resetPageStats(); showPageAt(pageStart); })(); // 쪽을 그리는 중이면 끝난 뒤에
            }
        }
        applyLook();
        const selFont = document.getElementById('selFont'), chkJ = document.getElementById('chkJustify'), selMg = document.getElementById('selMargin');
        if (selFont) { selFont.value = FONT_STACK[rp('txt_font')] ? rp('txt_font') : ''; selFont.addEventListener('change', () => relayoutKeep(() => wp('txt_font', selFont.value))); }
        if (chkJ) { chkJ.checked = rp('txt_justify') === '1'; chkJ.addEventListener('change', () => relayoutKeep(() => wp('txt_justify', chkJ.checked ? '1' : '0'))); }
        if (selMg) { selMg.value = (rp('txt_margin') === '1' || rp('txt_margin') === '2') ? rp('txt_margin') : '0'; selMg.addEventListener('change', () => relayoutKeep(() => wp('txt_margin', selMg.value))); }

        // ── 이 장 남은 쪽(하단) — 장 제목(목차)을 찾은 책만. 쪽 모드는 실제 쪽 크기, 1단은 한 화면을 한 쪽으로 셈 ──
        const chLeft = document.getElementById('chLeft');
        function screenEnd(from) { // 1단: 화면 아래 끝에 걸린 글자 번호 — 글자 위치를 직접 재서 찾음(위에 떠 있는 창에 영향 없음)
            const n = contentEl.firstChild; if (!n || n.nodeType !== 3) return -1;
            const yb = window.innerHeight - 40, len = n.length, r = document.createRange();
            const topOf = (i) => { for (let k = i; k < Math.min(len, i + 8); k++) { r.setStart(n, k); r.setEnd(n, k + 1); const b = r.getBoundingClientRect(); if (b.height > 0) return b.top; } return null; };
            let lo = Math.max(0, from || 0), hi = len;
            while (lo < hi) { const mid = (lo + hi) >> 1; const t = topOf(mid); if (t === null || t < yb) lo = mid + 1; else hi = mid; }
            return lo;
        }
        function updChLeft() {
            if (!chLeft) return;
            try {
                const t = buildToc(); if (!t.length) { chLeft.textContent = ''; return; }
                const cur = txtHere();
                // 지금 화면(쪽)의 끝 글자 — 장은 '화면 끝이 속한 장'으로 셈(쪽 안에서 새 장이 시작되면 그 장 기준)
                let shownEnd, per;
                if (currentLayout === 'single') { shownEnd = screenEnd(cur); if (shownEnd < 0) { chLeft.textContent = ''; return; } per = Math.max(50, shownEnd - cur); }
                else { shownEnd = pageEnd; per = avgPageChars(); }
                const at = Math.max(cur, shownEnd - 1);
                const nx = t.find(it => it.s > at);
                if (!nx && !allLoaded) { chLeft.textContent = ''; return; } // 다음 장 제목을 아직 안 읽어 옴
                const end = nx ? nx.s : fullContent.length;
                const left = end <= shownEnd ? 0 : Math.max(1, Math.ceil((end - shownEnd) / per));
                chLeft.textContent = left > 0 ? String(TT.ch_left).replace('%d', left) : TT.ch_last;
            } catch (e) { chLeft.textContent = ''; }
        }
        let clT = 0; const schedCh = () => { clearTimeout(clT); clT = setTimeout(updChLeft, 250); };
        window.addEventListener('scroll', schedCh, { passive: true });
        window.addEventListener('resize', schedCh);
        try { new MutationObserver(schedCh).observe(readerEl, { childList: true, subtree: true, characterData: true }); } catch (e) {}

        // ── 화면 어둡게 · 읽은 시간(이 기기) ──
        MCTools.dim({ slider: document.getElementById('dimSlider'), value: document.getElementById('dimValue') });
        MCTools.readTime({ key: 'txt:' + BIDX + ':' + FILE, t: TT, el: document.getElementById('readTime') });
        // ★ (2026-11-01 17:05) 설정 창 안(슬라이더·선택 상자)에서 누른 ←→·Space 는 그 칸만 움직이게(쪽 모드에서는 쪽이 넘어가고 슬라이더가 안 움직였음)
        const sp = document.getElementById('settingsPanel');
        if (sp) sp.addEventListener('keydown', (e) => { if (/^(Arrow|Page|Home|End)/.test(e.key) || e.key === ' ') e.stopPropagation(); });
    })();

    init().finally(() => { setTimeout(() => { try { txtAutoInit(); } catch (x) {} window.__mcTxtInitDone = true; if (window.__mcTxtJump) window.__mcTxtJump(); }, 600); }); // ★ (2026-11-03 00:07) 첫 화면·저장 위치 복원 뒤 책갈피 이동(?note=)
})();
</script>

<!-- ✅ 페이지 전환 시 부드러운 효과 -->
<script>
(function(){
    document.addEventListener('click', function(e) {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return; // ★ (2026-11-04 00:29) 새 탭(Ctrl·⌘·Shift·가운데)·이미 막은 클릭은 그대로(다른 화면과 같게)
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
<?php echo mc_al_i18n_script(); ?>
<script src="<?php echo $base_path; ?>/js/auto-logout.js?v=<?php echo @filemtime(__DIR__ . '/js/auto-logout.js') ?: '1'; ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/privacy_shield.php'; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
<?php if (function_exists('mc_perf_script')) echo mc_perf_script('txt'); // ★ (2026-11-03 14:10) 속도 기록(src/perf) ?>
<script>
// ★ (2026-11-04 18:05) 설정 창 '전체화면' — 지원하는 브라우저만 보임, 누를 때마다 켜기/끄기(Esc·뒤로 가기로 나가도 글자 맞춤)
(function () {
    var row = document.getElementById('fsRow'), btn = document.getElementById('btnFullscreen'), de = document.documentElement;
    var req = de.requestFullscreen || de.webkitRequestFullscreen, ex = document.exitFullscreen || document.webkitExitFullscreen;
    if (!row || !btn || !req || !ex || !(document.fullscreenEnabled || document.webkitFullscreenEnabled)) return;
    var ON = <?php echo json_encode(__('bt_fs_on'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, OFF = <?php echo json_encode(__('bt_fs_off'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    row.hidden = false;
    function isFs() { return !!(document.fullscreenElement || document.webkitFullscreenElement); }
    function sync() { var f = isFs(); btn.textContent = f ? OFF : ON; btn.setAttribute('aria-pressed', f ? 'true' : 'false'); }
    btn.addEventListener('click', function (e) {
        e.preventDefault(); e.stopPropagation();
        try { var r = isFs() ? ex.call(document) : req.call(de); if (r && r.catch) r.catch(function () {}); } catch (x) {}
    });
    document.addEventListener('fullscreenchange', sync); document.addEventListener('webkitfullscreenchange', sync);
    sync();
})();
</script>
</body>
</html>