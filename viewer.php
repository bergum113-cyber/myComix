<?php
/**
 * myComix 이미지 뷰어
 * @version 3.14 - 세로 분할 모드 추가 (pageorder 3, 4), VIPS 지원
 * @date 2026-01-14
 */
require_once __DIR__ . '/bootstrap.php';
handle_timeout_popup();

// ✅ log_user_activity()는 function.php에서 로드됨 (2026-01-20 통합)

// ✅ cache_util.php는 bootstrap.php에서 자동 로드됨

$bidx = init_bidx();

// ============================================================
// ✅ 공통 파라미터 정의 (get_param 사용으로 일관성 확보)
// ============================================================
$_param_filetype = get_param('filetype', 'string', '');
$_param_download = get_param('download', 'string', '');
$_param_stream = get_param('stream', 'string', '');
$_param_action = get_param('action', 'string', '');
$_param_imgfile = get_param('imgfile', 'string', '');
$_param_thumb = get_param('thumb', 'string', '');
$_param_img = get_param('img', 'int', -1);

// ✅ TXT/EPUB 파일은 전용 뷰어로 리다이렉트
$_getfile = decode_file_param($_GET['file'] ?? '');
$_ext = strtolower(pathinfo($_getfile, PATHINFO_EXTENSION));

// ★ (2026-10-30) 보안: 폴더 권한을 모든 요청(이미지·다운로드·스트림·PDF·변환 포함)보다 먼저 확인
//   (전에는 화면(HTML)을 그릴 때만 dir_check 해서, 주소만 알면 권한 없는 폴더의 이미지·파일을 받을 수 있었음)
if ($_getfile !== '') {
    dir_check($_getfile);
}

// ✅ 리다이렉트용 파라미터 - HTTP Header Injection 방지
// $_GET['file']은 이미 URL 인코딩된 상태이므로 그대로 전달하되,
// 개행문자(\r\n)를 제거하여 Header Injection 공격 방지
$_raw_file_param = preg_replace('/[\r\n]/', '', $_GET['file'] ?? '');

// ★ (2026-10-30) $_GET['file'] 은 PHP 가 이미 한 번 디코딩한 값 — 이름에 & # % 가 있으면 잘려서 다시 인코딩해 넘김
if ($_ext === 'txt' && file_exists(__DIR__ . '/txt_viewer.php')) {
    header('Location: txt_viewer.php?file=' . encode_url($_getfile) . '&bidx=' . $current_bidx);
    exit;
}
if ($_ext === 'epub' && file_exists(__DIR__ . '/epub_viewer.php')) {
    header('Location: epub_viewer.php?file=' . encode_url($_getfile) . '&bidx=' . $current_bidx);
    exit;
}

$_branding = load_branding();

// ✅ 즐겨찾기 로드
$_favorites_file = get_favorites_file();
$_favorites_arr = [];
if (is_file($_favorites_file)) {
    $_favorites_arr = json_decode(file_get_contents($_favorites_file), true) ?? [];
}

// ============================================================
// ✅ 스트리밍 요청 판별 함수 (캐시 헤더 결정용)
// ============================================================

/**
 * 현재 요청이 이미지/파일 스트리밍인지 판별
 * 
 * 스트리밍 요청 유형:
 * - imgfile: ZIP 내 이미지 추출
 * - thumb: 썸네일 요청
 * - filetype=pdf: PDF 파일 스트리밍
 * - filetype=video: 동영상 스트리밍
 * - filetype=archive&download=1: 압축파일 다운로드
 * 
 * @return bool 스트리밍 요청 여부
 */
/**
 * 압축 파일에서 특정 이미지 데이터를 추출 (ZIP/RAR/7Z 자동 분기)
 *
 * - ZIP/CBZ: PHP 내장 ZipArchive (빠름, 기존 동작 유지)
 * - RAR/CBR/7Z/CB7: archive_handler(외부 도구 unrar/7z) 사용
 *
 * @param string $base_file  압축 파일 경로
 * @param string $entry_name 압축 내 이미지 파일명
 * @return string|false      이미지 바이너리 또는 실패 시 false
 */
function archive_get_image_data($base_file, $entry_name, $img_index = null) {
    $ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));

    // RAR/7Z 계열: archive_handler 사용
    if (in_array($ext, ['rar', 'cbr', '7z', 'cb7'])) {
        if (!class_exists('ArchiveHandler')) {
            return false;
        }
        try {
            $handler = new ArchiveHandler($base_file);
            $data = $handler->extractFile($entry_name);
            if ($data !== false && $data !== null && strlen($data) > 0) return $data;
            // ★ (2026-10-31) 예전 버전이 만든 목록 캐시(리눅스 C 로캘에서 '?' 로 깨진 이름 등)라 이름이 지금 목록에 없으면
            //   목록 캐시를 지금 이름으로 다시 쓰고 같은 순번의 쪽을 꺼냄 — 업데이트 뒤 예전 캐시 때문에 쪽이 안 열리지 않게
            if ($img_index !== null && is_string($entry_name) && !in_array($entry_name, $handler->listFiles(), true)) {
                $fresh = [];
                foreach ($handler->getImageFiles() as $n) { if (is_string($n) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $n)) $fresh[] = $n; }
                natsort($fresh);
                $fresh = array_values($fresh);
                if (!empty($fresh)) {
                    $cf = $base_file . '.image_files.json';
                    $tmp = $cf . '.tmp' . bin2hex(random_bytes(4));
                    if (@file_put_contents($tmp, json_encode($fresh, JSON_UNESCAPED_UNICODE)) !== false) {
                        if (!@rename($tmp, $cf)) @unlink($tmp);
                    }
                    if (isset($fresh[(int)$img_index])) {
                        $data = $handler->extractFile($fresh[(int)$img_index]);
                        if ($data !== false && $data !== null && strlen($data) > 0) return $data;
                    }
                }
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    // ZIP/CBZ 계열: 기존 ZipArchive 방식 (동작 동일)
    if (!class_exists('ZipArchive')) return false;
    $zip = new ZipArchive;
    if ($zip->open($base_file) === TRUE) {
        $data = mc_zip_from_name($zip, $entry_name); // ★ (2026-10-31) 한글 등으로 바꾼 이름도 찾기
        $zip->close();
        return $data;
    }
    return false;
}

/**
 * ★ (2026-10-31) 암호가 걸린 압축인지(항목 암호 또는 헤더 암호) — 암호 압축을 열면 빈 화면·깨진 쪽만 보이던 문제 안내용
 *   ZIP 은 항목 정보만 훑고(빠름), RAR/7Z 는 ArchiveHandler 의 목록 결과를 씀
 */
function archive_is_encrypted($base_file) {
    static $memo = [];
    if (isset($memo[$base_file])) return $memo[$base_file];
    $enc = false;
    $ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
    if (in_array($ext, ['zip', 'cbz'], true)) {
        if (class_exists('ZipArchive')) {
            $z = new ZipArchive;
            if ($z->open($base_file) === TRUE) {
                for ($i = 0; $i < $z->numFiles; $i++) {
                    $st = @$z->statIndex($i);
                    if (is_array($st) && !empty($st['encryption_method'])) { $enc = true; break; }
                }
                $z->close();
            }
        }
    } elseif (in_array($ext, ['rar', 'cbr', '7z', 'cb7'], true) && class_exists('ArchiveHandler')) {
        try { $h = new ArchiveHandler($base_file); $enc = $h->isEncrypted(); } catch (\Throwable $e) { $enc = false; }
    }
    return $memo[$base_file] = $enc;
}

/** ★ (2026-10-31) 암호 압축의 쪽 자리에 보낼 안내 그림(SVG) — 캐시하지 않음 */
function archive_locked_image_exit() {
    $t = htmlspecialchars(__('viewer_archive_locked_short'), ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800" viewBox="0 0 600 800">'
         . '<rect width="600" height="800" fill="#eceff1"/>'
         . '<g transform="translate(300 330)" fill="none" stroke="#607d8b" stroke-width="14" stroke-linecap="round" stroke-linejoin="round">'
         . '<rect x="-70" y="-10" width="140" height="110" rx="14"/><path d="M-42 -10v-34a42 42 0 0 1 84 0v34"/></g>'
         . '<text x="300" y="520" text-anchor="middle" font-family="sans-serif" font-size="30" fill="#455a64">' . $t . '</text></svg>';
    http_response_code(200);
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Content-Length: ' . strlen($svg));
    header('Cache-Control: no-store');
    echo $svg;
    exit;
}

function is_streaming_request() {
    global $_param_imgfile, $_param_thumb, $_param_filetype, $_param_stream, $_param_download;
    
    // 이미지 파일 요청 (PDF 본문도 imgfile=pdf)
    if (!empty($_param_imgfile)) return true;
    
    // 썸네일 요청
    if (!empty($_param_thumb)) return true;
    
    // 파일 타입별 스트리밍 — ★ (2026-10-30) 실제 전송(stream=1 / download=1)일 때만.
    //   전에는 filetype=pdf|video 화면(HTML)까지 24시간 공개 캐시로 나가, 남은 로그인 시간 등이 오래된 화면으로 보였음
    if (in_array($_param_filetype, ['video', 'archive', 'zipvideo'], true) && ($_param_stream === '1' || $_param_download === '1')) return true;
    
    return false;
}

/**
 * 이미지를 좌우로 분할 (세로 분할 모드용)
 * 가로가 세로의 1.3배 이상일 때만 분할
 * VIPS 우선 사용, 실패 시 GD 폴백 (index.php와 동일 패턴)
 * 
 * @param string $img_data 원본 이미지 바이너리
 * @param string $side 'left' 또는 'right'
 * @return array ['data' => 분할된 이미지 바이너리, 'split' => true] 또는 ['data' => 원본, 'split' => false]
 */
function splitImage($img_data, $side) {
    global $vips_path;
    
    // 이미지 크기 확인 (GD 없이 가능)
    $size_info = @getimagesizefromstring($img_data);
    if ($size_info === false) {
        return ['data' => $img_data, 'split' => false];
    }
    
    $width = $size_info[0];
    $height = $size_info[1];
    
    // 가로가 세로의 1.3배 이상일 때만 분할
    if ($width < $height * 1.3) {
        return ['data' => $img_data, 'split' => false]; // 분할 불필요 - 원본 반환
    }
    
    $half_width = (int)($width / 2);
    $result_data = null;
    
    // ✅ VIPS 우선 사용 (index.php와 동일 패턴)
    if (!empty($vips_path) && file_exists($vips_path)) {
        // 원본 확장자 감지
        $ext = 'jpg';
        if ($size_info[2] === IMAGETYPE_PNG) $ext = 'png';
        elseif ($size_info[2] === IMAGETYPE_WEBP) $ext = 'webp';
        elseif ($size_info[2] === IMAGETYPE_GIF) $ext = 'gif';
        
        $temp_in = sys_get_temp_dir() . '/split_in_' . uniqid() . '.' . $ext;
        $temp_out = sys_get_temp_dir() . '/split_out_' . uniqid() . '.jpg';
        
        @file_put_contents($temp_in, $img_data, LOCK_EX);
        
        // VIPS crop 명령: vips crop input output left top width height
        if ($side === 'left') {
            $crop_x = 0;
            $crop_width = $half_width;
        } else {
            $crop_x = $half_width;
            $crop_width = $width - $half_width;
        }
        
        $cmd = escape_shell_arg_safe($vips_path) . ' crop ' . escape_shell_arg_safe($temp_in) . ' ' . escape_shell_arg_safe($temp_out) . ' ' . intval($crop_x) . ' 0 ' . intval($crop_width) . ' ' . intval($height) . ' 2>&1';
        exec($cmd, $output, $return_code);
        
        if ($return_code === 0 && file_exists($temp_out)) {
            $result_data = file_get_contents($temp_out);
        }
        
        @unlink($temp_in);
        @unlink($temp_out);
    }
    
    // ✅ VIPS 미설정 또는 실패 시 GD 폴백
    if ($result_data === null) {
        $img = function_exists('mc_gd_from_string') ? mc_gd_from_string($img_data) : @imagecreatefromstring($img_data); // ★ (2026-11-02 09:58) 너무 큰 그림은 나누지 않고 원본
        if (!$img) {
            return ['data' => $img_data, 'split' => false];
        }
        
        if ($side === 'left') {
            $crop = @imagecrop($img, ['x' => 0, 'y' => 0, 'width' => $half_width, 'height' => $height]);
        } else {
            $crop = @imagecrop($img, ['x' => $half_width, 'y' => 0, 'width' => $width - $half_width, 'height' => $height]);
        }
        
        imagedestroy($img);
        
        if (!$crop) {
            return ['data' => $img_data, 'split' => false];
        }
        
        // 원본 포맷 감지
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = @finfo_buffer($finfo, $img_data);
        @finfo_close($finfo);
        
        ob_start();
        if ($mime === 'image/png') {
            imagepng($crop, null, 6);
        } elseif ($mime === 'image/webp') {
            imagewebp($crop, null, 85);
        } else {
            imagejpeg($crop, null, 85);
        }
        $result_data = ob_get_clean();
        imagedestroy($crop);
    }
    
    return ['data' => $result_data, 'split' => true];
}

/**
 * ★ (2026-10-29) 세로분할(3/4)용(북모드·툰모드 공통): 페이지별 "분할 대상 여부" 맵
 * - splitImage()와 같은 기준(가로 >= 세로*1.3)을 이미지 헤더만 읽어 판정 → 세로 페이지가 좌/우로 2번 나오던 중복 방지
 * - 서버 이미지 서빙과 같은 .image_files.json 순서(img 인덱스)를 사용
 * - 1 = 분할, 0 = 분할 안 함(1장), null = 판정 불가(기존 동작 그대로 좌/우 2장)
 * - ZIP/CBZ·이미지 폴더만 판정. RAR/7Z는 헤더만 읽기 어려워 null(기존 동작 유지)
 */
function mc_book_split_map($base_file, $type, $cache_dir = null) {
    // 같은 요청 안에서 북모드(헤더 JS)·본문 목록이 함께 호출해도 1번만 계산
    static $memo = [];
    $memo_key = $type . '|' . $base_file;
    if (isset($memo[$memo_key])) return $memo[$memo_key];
    $map = [];
    try {
        $judge = function ($info) {
            if (!is_array($info) || empty($info[0]) || empty($info[1])) return null;
            return ((int)$info[0] >= (int)$info[1] * 1.3) ? 1 : 0;
        };
        $list_file = $base_file . '.image_files.json';

        if ($type === 'zip' && preg_match('/\.(zip|cbz)$/i', $base_file)) {
            // ZIP 서빙과 같은 목록: .image_files.json 배열의 img 인덱스 그대로
            if (!is_file($list_file)) return $map;
            $names = @json_decode(@file_get_contents($list_file), true);
            if (!is_array($names) || empty($names)) return $map;
            $count = min(count($names), 5000); // 비정상 대용량 목록 상한
            $map = array_fill(0, $count, null);

            // ★ (2026-10-29) 판정 결과 캐시(ZIP만): 사용자·압축파일별 이미지 캐시 폴더에 저장
            //   → 기존 캐시 정리(7일 TTL·로그아웃 정리)와 함께 지워짐. 파일 크기·수정시각·목록 수정시각이 바뀌면 다시 판정
            $ck = @filesize($base_file) . '|' . @filemtime($base_file) . '|' . @filemtime($list_file) . '|' . $count;
            $cf = $cache_dir ? rtrim($cache_dir, '/\\') . '/_splitmap.json' : null;
            if ($cf && is_file($cf)) {
                $c = @json_decode(@file_get_contents($cf), true);
                if (is_array($c) && ($c['k'] ?? null) === $ck && isset($c['m']) && is_array($c['m']) && count($c['m']) === $count) {
                    $ok = true;
                    foreach ($c['m'] as $v) { if (!($v === 0 || $v === 1 || $v === null)) { $ok = false; break; } }
                    if ($ok) { $memo[$memo_key] = array_values($c['m']); return $memo[$memo_key]; }
                }
            }

            $z = new ZipArchive;
            if ($z->open($base_file) !== TRUE) { $memo[$memo_key] = $map; return $map; }
            for ($i = 0; $i < $count; $i++) {
                $name = $names[$i] ?? null;
                if (!is_string($name)) continue;
                $info = false;
                foreach ([131072, 2097152] as $len) { // 헤더만 부분 읽기(128KB → 실패 시 2MB)
                    $part = mc_zip_from_name($z, $name, $len);
                    if ($part === false || $part === '') break;
                    $info = @getimagesizefromstring($part);
                    if ($info !== false || strlen($part) < $len) break;
                }
                $map[$i] = $judge($info);
            }
            $z->close();
            if ($cf) {
                if (!is_dir(dirname($cf))) @mkdir(dirname($cf), 0755, true);
                if (is_dir(dirname($cf))) @file_put_contents($cf, json_encode(['k' => $ck, 'm' => $map]), LOCK_EX);
            }
        } elseif ($type === 'images' && is_dir($base_file)) {
            // 폴더 서빙(이미지 요청 처리부)과 완전히 같은 방식으로 목록/순서 구성 → 인덱스 일치
            $files = [];
            if (is_file($list_file)) {
                $cached = @json_decode(@file_get_contents($list_file), true);
                if (is_array($cached) && !empty($cached)) {
                    foreach ($cached as $fname) {
                        if (is_string($fname) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fname) && !preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $fname)) { /* ★ (2026-11-09 14:14) '..' 조각이 든 이름은 건너뜀 — 손으로 만든 목록 파일로 폴더 밖·막힌 폴더 그림이 나왔음(앱은 폴더 맨 위 이름만 씀) */ /* ★ (2026-11-02 20:27) 그림 이름만 — 이 파일이 목록 대신 메타(totalpage·page_order·thumbnail…)로 덮여 있는 경우가 있어, 'toon'·'3' 같은 이름의 하위 폴더·파일이 쪽으로 잡힐 수 있었음 */
                            $fp = realpath($base_file . DIRECTORY_SEPARATOR . $fname);
                            if ($fp !== false) $files[] = $fp;
                        }
                    }
                }
            }
            if (empty($files)) {
                foreach (new DirectoryIterator($base_file) as $f) {
                    if ($f->isFile() && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f->getFilename()) && !mc_folder_nonpage($f->getFilename())) { // ★ (2026-11-02 08:58) 폴더 안 표지([cover].jpg — 목록 화면이 만듦)·영상 썸네일은 쪽이 아님(전에는 마지막·첫 쪽으로 끼어 들어갔음) — 세 곳 같은 기준
                        $files[] = $f->getPathname();
                    }
                }
                $files = n_sort($files);
            }
            $files = array_values($files);
            $count = min(count($files), 5000);
            $map = array_fill(0, $count, null);
            for ($i = 0; $i < $count; $i++) {
                if (is_string($files[$i]) && is_file($files[$i])) {
                    $map[$i] = $judge(@getimagesize($files[$i]));
                }
            }
        }
        // RAR/7Z 등: 판정 안 함(빈 배열 → 기존처럼 좌/우 2장)
    } catch (\Throwable $e) {
        // 판정 실패 → 기존 동작 그대로
    }
    $memo[$memo_key] = $map;
    return $map;
}

/** 현재 사용자·압축파일의 이미지 캐시 폴더 경로(이미지 서빙부와 같은 규칙) — 분할 판정 캐시 저장 위치 */
function mc_split_cache_dir($base_file, $user_id) {
    $uid = preg_replace('/[^a-zA-Z0-9]/', '', (string)$user_id);
    if ($uid === '' || !function_exists('cacheKeyFromPath')) return null;
    return __DIR__ . "/cache/{$uid}/" . cacheKeyFromPath($base_file);
}

// ✅ 경로 검증 실패 시 에러 응답은 security_helpers.php의 simple_error_exit() 사용

set_time_limit(30);
// ★ (2026-10-30) Windows 는 실행 시간 제한을 실제 경과 시간으로 잼 — 파일 내려받기·동영상·PDF 전송은 느린 회선에서 30초에 끊기지 않게
if ($_param_download === '1' || $_param_stream === '1' || ($_param_filetype === 'pdf' && $_param_imgfile === 'pdf')) {
    set_time_limit(0);
}
if (ob_get_level()) ob_end_clean();

// ✅ Cache-Control 헤더 - 이미지/데이터 스트리밍 요청에만 캐시 허용
// HTML 페이지 렌더링 시에는 no-cache 적용 (HTML 출력 전에 재설정됨)
$_is_streaming_request = is_streaming_request();
if ($_is_streaming_request) {
    header("Cache-Control: private, max-age=86400");
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
}

// ============================================================
// ✅ 세션 데이터 접근 (session_write_close 전에 모두 처리)
// ⚠️ 중요: 아래 코드는 반드시 session_write_close() 호출 전에 실행되어야 함
// session_write_close() 이후에는 $_SESSION 접근 시 빈 값 반환
// 이 순서가 바뀌면 'guest' 폴더가 생성되는 버그 발생
// ============================================================

// 1. 먼저 세션에서 필요한 데이터를 모두 추출
$_session_user_id = $_SESSION['user_id'] ?? '';
$_session_last_action = $_SESSION['last_action'] ?? time();
$_session_remember_me = isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true;
$_viewer_delete_token = get_delete_token(); // ★ (2026-10-30) 세션을 닫기 전에 만들어 둠(커버 설정·MP4 변환 요청용)
$_viewer_is_admin = isset($_SESSION['user_group']) && trim((string)$_SESSION['user_group']) === 'admin'; // ★ (2026-10-30) MP4 변환(원본 삭제)은 관리자만
$_viewer_csrf = ($_viewer_is_admin && function_exists('generate_csrf_token')) ? generate_csrf_token() : ''; // ★ (2026-11-08 fix80) '지금 장면·이 쪽을 폴더 표지로'(POST) 용 — 세션을 닫기 전에

// 2. 추출한 데이터로 변수 설정
$user_id = !empty($_session_user_id) 
    ? preg_replace('/[^a-zA-Z0-9]/', '', $_session_user_id) 
    : 'guest';
$timeout = (int)($auto_logout_settings['timeout'] ?? 600);
$last_action = $_session_last_action;
$elapsed = time() - $last_action;
$remaining = max(0, $timeout - $elapsed);

// 3. 세션 닫기 (이미지 스트리밍 시 세션 락 방지)
// ⚠️ 이 이후로는 $_SESSION 접근 금지
session_write_close();

// PDF 데이터 직접 출력용
if ($_param_filetype === 'pdf' && $_param_imgfile === 'pdf') {
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    // ✅ 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        log_user_activity('해킹시도', 'pdf: ' . substr($getfile, 0, 100));
        simple_error_exit(403, __('err_invalid_path'));
    }

    if (!file_exists($base_file) || !preg_match('/\.pdf$/i', $base_file)) {
        simple_error_exit(404, __('err_file_not_found'));
    }

    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/pdf");
    header("Content-Length: " . filesize($base_file));
    readfile($base_file);
    exit;
}

// ============================================================
// ✅ 압축파일 다운로드용
// ============================================================
if ($_param_filetype === 'archive' && $_param_download === '1') {
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    // ✅ 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        log_user_activity('해킹시도', 'archive download: ' . substr($getfile, 0, 100));
        simple_error_exit(403, __('err_invalid_path'));
    }

    if (!is_file($base_file)) { // ★ (2026-11-02 11:38) 폴더 경로면 filesize·readfile 경고 — 파일만
        simple_error_exit(404, __('err_file_not_found'));
    }

    $file_size = filesize($base_file);
    $filename = basename($base_file);
    
    // ✅ 다운로드 로그 기록
    log_user_activity('다운로드', '압축파일: ' . $filename);
    
    // 출력 버퍼 정리
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header("Content-Type: application/octet-stream");
    header("Content-Length: $file_size");
    header("Content-Disposition: attachment; filename=\"" . rawurlencode($filename) . "\"");
    header("Content-Transfer-Encoding: binary");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    
    readfile($base_file);
    exit;
}

// ============================================================
// ✅ 동영상 다운로드용 (download=1)
// ============================================================
if ($_param_filetype === 'video' && $_param_download === '1') {
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    // ✅ 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        log_user_activity('해킹시도', 'video download: ' . substr($getfile, 0, 100));
        simple_error_exit(403, __('err_invalid_path'));
    }

    if (!file_exists($base_file) || !is_video_file($base_file)) {
        simple_error_exit(404, __('viewer_video_not_found'));
    }

    $file_size = filesize($base_file);
    $filename = basename($base_file);
    
    // ✅ 다운로드 로그 기록
    log_user_activity('다운로드', '동영상: ' . $filename);
    
    // 출력 버퍼 정리
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header("Content-Type: application/octet-stream");
    header("Content-Length: $file_size");
    header("Content-Disposition: attachment; filename=\"" . rawurlencode($filename) . "\"");
    header("Content-Transfer-Encoding: binary");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    
    // 대용량 파일 스트리밍
    $fp = fopen($base_file, 'rb');
    if ($fp) {
        while (!feof($fp) && connection_status() === 0) {
            echo fread($fp, 8192);
            flush();
        }
        fclose($fp);
    }
    exit;
}

// ============================================================
// ✅ 동영상 스트리밍 출력용
// ============================================================
if ($_param_filetype === 'video' && $_param_stream === '1') {
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    // ✅ 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        log_user_activity('해킹시도', 'video stream: ' . substr($getfile, 0, 100));
        simple_error_exit(403, __('err_invalid_path'));
    }

    if (!file_exists($base_file) || !is_video_file($base_file)) {
        simple_error_exit(404, __('viewer_video_not_found'));
    }

    $file_size = filesize($base_file);
    $mime_type = mime_type($base_file);
    
    // Range 요청 처리 (동영상 시크 지원)
    $start = 0;
    $end = $file_size - 1;
    
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            // ★ (2026-10-30) 'bytes=-N'(끝에서 N바이트) 처리, 끝 위치는 파일 크기로 자름(길이 불일치로 플레이어가 기다리던 문제)
            if ($matches[1] === '' && $matches[2] !== '') {
                $start = max(0, $file_size - intval($matches[2]));
                $end = $file_size - 1;
            } else {
                $start = $matches[1] !== '' ? intval($matches[1]) : 0;
                $end = $matches[2] !== '' ? min(intval($matches[2]), $file_size - 1) : $file_size - 1;
            }
            
            if ($start > $end || $start >= $file_size) {
                http_response_code(416);
                header("Content-Range: bytes */$file_size");
                exit;
            }
            
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$file_size");
        }
    }
    
    $length = $end - $start + 1;
    
    header("Accept-Ranges: bytes");
    header("Content-Type: $mime_type");
    header("Content-Length: $length");
    header("Cache-Control: private, max-age=86400");
    header("Content-Disposition: inline; filename=\"" . basename($base_file) . "\"");
    
    // 파일 스트리밍
    $fp = fopen($base_file, 'rb');
    if ($fp) {
        fseek($fp, $start);
        $buffer_size = 8192;
        $bytes_sent = 0;
        
        while (!feof($fp) && $bytes_sent < $length && connection_status() === 0) {
            $read_size = min($buffer_size, $length - $bytes_sent);
            echo fread($fp, $read_size);
            $bytes_sent += $read_size;
            flush();
        }
        
        fclose($fp);
    }
    exit;
}

// ============================================================
// ★ (2026-10-31) FileStation 동영상 플레이어 기능 — 재생바 장면 미리보기(vapi=frame) · 실시간 변환 재생(vapi=tc) · 압축 속 자막(vapi=zsubs)
//   파일 확인은 위 스트림과 같음(맨 위 dir_check 폴더 권한 + validate_file_path). ffmpeg 는 관리자 설정 경로만, 동시 실행 자리 제한(video_lib.php)
// ============================================================
if (isset($_GET['vapi']) && in_array($_param_filetype, ['video', 'zipvideo'], true)) {
    require_once __DIR__ . '/video_lib.php';
    $getfile = decode_file_param($_GET['file'] ?? '');
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        log_user_activity('해킹시도', 'vapi: ' . substr($getfile, 0, 100));
        simple_error_exit(403, __('err_invalid_path'));
    }
    $_vapi = is_string($_GET['vapi']) ? $_GET['vapi'] : ''; // ★ (2026-11-02 09:58) 배열이면 경고
    if ($_param_filetype === 'zipvideo') {
        if ($_vapi !== 'zsubs' || !is_file($base_file) || !preg_match('/\.(zip|cbz)$/i', $base_file)) simple_error_exit(404, __('viewer_archive_not_found'));
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(mcv_zip_subs($base_file, is_string($_GET['video'] ?? null) ? $_GET['video'] : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if (!is_file($base_file) || !is_video_file($base_file)) simple_error_exit(404, __('viewer_video_not_found'));
    if ($_vapi === 'frame') mcv_frame_send($base_file, (float)($_GET['t'] ?? 0));
    if ($_vapi === 'tc') mcv_transcode_send($base_file, (float)($_GET['ss'] ?? 0), (int)($_GET['a'] ?? 0), (int)($_GET['q'] ?? 0));
    simple_error_exit(400, 'bad request');
}

// ============================================================
// ✅ MP4 변환 API
// ============================================================
if (($_GET['action'] ?? '') === 'convert_to_mp4') {
    header('Content-Type: application/json');
    // ★ (2026-10-30) 보안: 원본을 지우는 동작이라 관리자만 + 토큰 필수(외부 페이지가 링크·이미지로 변환을 시키는 CSRF 방지)
    if (!$_viewer_is_admin || !verify_delete_token($_GET['token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => __('err_permission_denied')]);
        exit;
    }
    
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    // 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        log_user_activity('해킹시도', 'mp4 convert: ' . substr($getfile, 0, 100));
        echo json_encode(['success' => false, 'error' => __('err_invalid_path')]);
        exit;
    }
    
    if (!file_exists($base_file) || !is_video_file($base_file)) {
        echo json_encode(['success' => false, 'error' => __('viewer_video_not_found')]);
        exit;
    }
    
    // ✅ ffmpeg 경로 확인 - 관리자 설정 경로만 사용 (자동 탐색 제거)
    global $ffmpeg_path;
    
    if (empty($ffmpeg_path)) {
        echo json_encode(['success' => false, 'error' => __('video_ffmpeg_not_set')]);
        exit;
    }
    
    if (!file_exists($ffmpeg_path)) {
        echo json_encode(['success' => false, 'error' => __('video_ffmpeg_not_exist', $ffmpeg_path)]);
        exit;
    }
    
    $ffmpeg_cmd = $ffmpeg_path;
    
    // 출력 파일 경로
    $output_file = preg_replace('/\.[^.]+$/', '.mp4', $base_file);
    
    // 이미 MP4인 경우 (재인코딩) - 임시 파일명 사용
    $is_same_file = ($output_file === $base_file);
    if ($is_same_file) {
        $temp_file = preg_replace('/\.mp4$/i', '_h264.mp4.converting', $base_file);
        $final_file = $base_file; // 최종적으로 원본 파일명으로 덮어쓰기
    } else {
        $temp_file = $output_file . '.converting';
        $final_file = $output_file;
    }
    
    // 이미 변환된 파일이 있는 경우 (다른 확장자에서 변환한 경우만)
    if (!$is_same_file && file_exists($output_file)) {
        echo json_encode(['success' => false, 'error' => __('video_mp4_exists')]);
        exit;
    }
    
    // Windows 한글 경로 처리
    $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $input_escaped = escape_shell_arg_safe($base_file);
    $output_escaped = escape_shell_arg_safe($temp_file);
    
    // 변환 실행
    // -y: 덮어쓰기, -i: 입력, -f mp4: 출력 포맷 명시
    // -c:v libx264: H.264 코덱, -c:a aac: AAC 오디오
    // -preset fast: 빠른 인코딩, -crf 23: 품질 (낮을수록 좋음, 18-28 권장)
    $cmd = sprintf(
        '%s -y -i %s -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k -movflags +faststart -f mp4 %s 2>&1',
        escape_shell_arg_safe($ffmpeg_cmd),
        $input_escaped,
        $output_escaped
    );
    
    // 실행 시간 제한 해제
    set_time_limit(0);
    
    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    
    if ($return_var !== 0 || !file_exists($temp_file)) {
        @unlink($temp_file);
        echo json_encode([
            'success' => false, 
            'error' => __('js_video_fail') . ': ' . implode("\n", array_slice($output, -5))
        ]);
        exit;
    }
    
    // 임시 파일을 최종 파일로 이동
    if ($is_same_file) {
        // 같은 MP4 파일 재인코딩: 원본 삭제 후 임시파일을 원본 이름으로 변경
        @unlink($base_file);
        
        // 썸네일 캐시도 삭제
        $thumb_file = $base_file . '.video_thumb.jpg';
        if (file_exists($thumb_file)) {
            @unlink($thumb_file);
        }
        
        if (!@rename($temp_file, $final_file)) {
            // ★ (2026-11-02 09:59) 원본은 이미 지웠으므로 변환본(임시 파일)은 남김 — 전에는 이것까지 지워 영상이 둘 다 사라졌음
            echo json_encode(['success' => false, 'error' => __('video_move_fail')]);
            exit;
        }
        $output_file = $final_file;
    } else {
        // 다른 확장자에서 변환
        if (!@rename($temp_file, $output_file)) {
            @unlink($temp_file);
            echo json_encode(['success' => false, 'error' => __('video_move_fail')]);
            exit;
        }
        
        // 원본 파일 삭제
        @unlink($base_file);
        
        // 썸네일 캐시도 삭제
        $thumb_file = $base_file . '.video_thumb.jpg';
        if (file_exists($thumb_file)) {
            @unlink($thumb_file);
        }
    }
    
    // 새 파일 경로 반환 (상대 경로)
    $new_path = str_replace($base_dir . '/', '', $output_file);
    
    echo json_encode([
        'success' => true, 
        'message' => __('video_convert_done'),
        'new_file' => $new_path,
        'new_url' => 'viewer.php?filetype=video&file=' . encode_url($new_path) . '&bidx=' . $current_bidx
    ]);
    exit;
}

// ============================================================
// ✅ MP4 변환 진행률 확인 API
// ============================================================
if (($_GET['action'] ?? '') === 'convert_progress') {
    header('Content-Type: application/json');
    
    $getfile = decode_file_param($_GET['file'] ?? '');
    $base_file = validate_file_path($getfile, $base_dir);
    
    if ($base_file === false) {
        log_user_activity('해킹시도', 'convert progress: ' . substr($getfile, 0, 100));
        echo json_encode(['progress' => -1, 'error' => __('err_invalid_path')]);
        exit;
    }
    
    $output_file = preg_replace('/\.[^.]+$/', '.mp4', $base_file);
    $is_same_file = ($output_file === $base_file); // MP4 재인코딩인 경우
    
    // 재인코딩: _h264.mp4.converting, 일반 변환: .mp4.converting
    if ($is_same_file) {
        $temp_file = preg_replace('/\.mp4$/i', '_h264.mp4.converting', $base_file);
    } else {
        $temp_file = $output_file . '.converting';
    }
    
    if (file_exists($temp_file)) {
        // 변환 중
        clearstatcache(true, $temp_file);
        $temp_size = filesize($temp_file);
        $original_size = filesize($base_file);
        // 대략적인 진행률 (실제로는 정확하지 않음)
        $progress = min(95, round(($temp_size / max(1, $original_size)) * 100));
        echo json_encode(['progress' => $progress, 'status' => 'converting']);
    } elseif (!$is_same_file && file_exists($output_file)) {
        // 완료 (다른 확장자에서 변환한 경우만)
        echo json_encode(['progress' => 100, 'status' => 'complete']);
    } else {
        // 대기 중 또는 실패 또는 재인코딩 대기
        echo json_encode(['progress' => 0, 'status' => 'pending']);
    }
    exit;
}

// ============================================================
// ✅ ZIP 내 동영상 스트리밍 출력용
// ============================================================
if ($_param_filetype === 'zipvideo' && $_param_stream === '1') {
    $getfile = decode_file_param($_GET['file'] ?? '');
    $video_name = $_GET['video'] ?? '';
    
    // ✅ 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        log_user_activity('해킹시도', 'zipvideo: ' . substr($getfile, 0, 100));
        simple_error_exit(403, __('err_invalid_path'));
    }

    if (!file_exists($base_file) || !preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $base_file)) {
        simple_error_exit(404, __('viewer_archive_not_found'));
    }

    $zip = new ZipArchive;
    if ($zip->open($base_file) !== TRUE) {
        simple_error_exit(500, __('viewer_archive_open_fail'));
    }

    // 동영상 파일 찾기
    // ★ (2026-10-30) 통째로 메모리에 올리지 않고 스트림으로 보냄(수백 MB 동영상이면 memory_limit 초과로 치명 오류)
    // ★ (2026-10-31) 목록에 보인 이름(옛 ZIP 의 한글 등을 바꾼 이름)을 ZipArchive 가 아는 이름으로
    $video_zname = (is_string($video_name) && $video_name !== '') ? mc_zip_native_name($zip, $video_name) : '';
    $video_stat = $video_zname !== '' ? $zip->statName($video_zname) : false;
    $video_stream = ($video_stat !== false) ? $zip->getStream($video_zname) : false;
    if ($video_stream === false) {
        $zip->close();
        simple_error_exit(404, __('viewer_video_not_found'));
    }
    set_time_limit(0);
    
    // MIME 타입 결정
    $ext = strtolower(pathinfo($video_name, PATHINFO_EXTENSION));
    $mime_types = [
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'm2t' => 'video/mp2t',
        'ts' => 'video/mp2t',
        'mts' => 'video/mp2t',
        'm2ts' => 'video/mp2t',
    ];
    $mime_type = $mime_types[$ext] ?? 'video/mp4';
    
    // ★ (2026-10-31) Range 요청 지원(구간 보내기) — 전에는 'Accept-Ranges: none' 으로 통째로만 보내 압축 파일 속 동영상은
    //   앞뒤로 옮기기(±5초·진행바 끌기·구간 반복·이어보기)가 안 됐음. 압축 속 파일은 바로 그 위치로 갈 수 없어(fseek 미지원)
    //   앞부분을 읽어 버린 뒤 그 위치부터 보냄 — 저장(무압축) 파일은 빠르고, 압축된 파일은 앞부분을 푸는 시간만큼 걸림
    $vsize = (int)$video_stat['size'];
    $vstart = 0; $vend = $vsize - 1; $vpartial = false;
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', trim((string)$_SERVER['HTTP_RANGE']), $rm) && ($rm[1] !== '' || $rm[2] !== '')) {
        if ($rm[1] === '') { $vstart = max(0, $vsize - (int)$rm[2]); $vend = $vsize - 1; }   // 'bytes=-N' (끝에서 N바이트)
        else { $vstart = (int)$rm[1]; $vend = ($rm[2] !== '') ? min((int)$rm[2], $vsize - 1) : $vsize - 1; }
        if ($vsize <= 0 || $vstart > $vend || $vstart >= $vsize) {
            fclose($video_stream); $zip->close();
            http_response_code(416);
            header("Content-Range: bytes */$vsize");
            exit;
        }
        $vpartial = true;
    }
    $vlen = $vend - $vstart + 1;
    header("Accept-Ranges: bytes");
    header("Content-Type: $mime_type");
    if ($vpartial) {
        http_response_code(206);
        header("Content-Range: bytes $vstart-$vend/$vsize");
    }
    header("Content-Length: " . $vlen);
    header("Cache-Control: private, max-age=86400");
    header("Content-Disposition: inline; filename=\"" . basename($video_name) . "\"");
    
    if ($vstart > 0) {
        // 이 PHP·libzip 이 압축 속 파일 이동을 지원하면 바로, 아니면 앞부분을 읽어 버림(1MB 씩)
        $sought = (@fseek($video_stream, $vstart) === 0 && @ftell($video_stream) === $vstart);
        if (!$sought) {
            $skip = $vstart;
            while ($skip > 0 && !feof($video_stream)) {
                $b = fread($video_stream, min(1048576, $skip));
                if ($b === false || $b === '') break;
                $skip -= strlen($b);
                if (connection_aborted()) { fclose($video_stream); $zip->close(); exit; }
            }
        }
    }
    $vleft = $vlen;
    while ($vleft > 0 && !feof($video_stream)) {
        $buf = fread($video_stream, min(65536, $vleft));
        if ($buf === false || $buf === '') break;
        echo $buf;
        $vleft -= strlen($buf);
        if (connection_aborted()) break;
    }
    fclose($video_stream);
    $zip->close();
    exit;
}

// ✅ warmupZipImageCache 함수는 cache_util.php에서 제공됨 (중복 제거)
// 이 함수는 viewer.php에서 직접 호출되지 않고, warmup.php에서 사용됨

if (isset($_GET['img'])) {
    $getfile = decode_file_param($_GET['file'] ?? '');
    
    // ✅ 경로 검증
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        log_user_activity('해킹시도', 'img: ' . substr($getfile, 0, 100));
        simple_error_exit(403, __('err_invalid_path'));
    }
    
    $img_index = intval($_GET['img']);
    
    // ✅ 세로 분할 모드 (pageorder 3, 4) 처리
    $split_side = '';
    if (isset($_GET['split']) && in_array($_GET['split'], ['left', 'right'], true)) {
        $split_side = $_GET['split'];
    }

    // ✅ 폴더 기반 처리 (ZIP과 동일한 압축 로직) - 캐시 활용
if (is_dir($base_file)) {
    $image_files = [];
    $img_cache_file = $base_file . '.image_files.json';
    
    // ✅ 캐시 파일이 있으면 사용
    if (is_file($img_cache_file)) {
        $cached_files = @json_decode(file_get_contents($img_cache_file), true);
        if (is_array($cached_files) && !empty($cached_files)) {
            foreach ($cached_files as $fname) {
                if (is_string($fname) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fname) && !preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $fname)) { /* ★ (2026-11-09 14:14) '..' 조각이 든 이름은 건너뜀 — 손으로 만든 목록 파일로 폴더 밖·막힌 폴더 그림이 나왔음(앱은 폴더 맨 위 이름만 씀) */ /* ★ (2026-11-02 20:27) 그림 이름만 — 이 파일이 목록 대신 메타(totalpage·page_order·thumbnail…)로 덮여 있는 경우가 있어, 'toon'·'3' 같은 이름의 하위 폴더·파일이 쪽으로 잡힐 수 있었음 */
                    // ✅ realpath로 경로 정규화 + 파일 존재 확인
                    $full_path = realpath($base_file . DIRECTORY_SEPARATOR . $fname);
                    if ($full_path !== false) {
                        $image_files[] = $full_path;
                    }
                }
            }
        }
    }
    
    // 캐시 없거나 비어있으면 직접 스캔
    if (empty($image_files)) {
        foreach (new DirectoryIterator($base_file) as $f) {
            if ($f->isFile() && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f->getFilename()) && !mc_folder_nonpage($f->getFilename())) {
                $image_files[] = $f->getPathname();
            }
        }
        $image_files = n_sort($image_files);
    }
    if (!isset($image_files[$img_index])) {
        simple_error_exit(404, __('viewer_img_not_found'));
    }

    // ✅ $user_id는 38행에서 session_write_close() 전에 이미 설정됨
    // 중복 할당 제거 (세션 닫힌 후 $_SESSION 접근 시 guest 반환 문제)
    $zip_hash = cacheKeyFromPath($base_file);
    $cache_dir = __DIR__ . "/cache/{$user_id}/{$zip_hash}";
    
    clearstatcache();
    if (file_exists($cache_dir) && !is_dir($cache_dir)) {
        unlink($cache_dir);
    }
    
    if (!is_dir($cache_dir) && !@mkdir($cache_dir, 0755, true)) {
        if (!is_dir($cache_dir)) {
            simple_error_exit(500, __('viewer_server_error'));
        }
    }

    $src_path = $image_files[$img_index];
    $filename = basename($src_path);
    
    // ✅ 분할 모드: 캐시 파일명에 접미사 추가
    $split_suffix = $split_side ? "_{$split_side}" : '';
    $filename_parts = pathinfo($filename);
    $cache_filename = $filename_parts['filename'] . $split_suffix . '.' . ($filename_parts['extension'] ?? 'jpg');
    $cache_img = "{$cache_dir}/{$cache_filename}";
    $cache_img_original = "{$cache_dir}/{$filename}"; // 원본 캐시 경로
    $min_size = 1000;

    // ===== 캐시 읽기 로직 =====
    if (file_exists($cache_img)) {
        clearstatcache(true, $cache_img);
        $filesize = @filesize($cache_img);
        
        if ($filesize >= $min_size) {
            $content = @file_get_contents($cache_img);
            
            if ($content !== false && strlen($content) >= $min_size) {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = detectMimeFromBytes($content);
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($content));
header('Cache-Control: private, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
echo $content;
                exit;
            }
        }
        
        // ✅ 캐시 파일이 있지만 유효하지 않으면 삭제
        @unlink($cache_img);
    }
    
    // ✅ 분할 모드: 원본 캐시에서 분할 생성
    if ($split_side && file_exists($cache_img_original)) {
        $original_content = @file_get_contents($cache_img_original);
        if ($original_content !== false && strlen($original_content) >= $min_size) {
            $split_result = splitImage($original_content, $split_side);
            
            if ($split_result['split']) {
                // 분할 성공 → 분할 캐시 저장 후 반환
                @file_put_contents($cache_img, $split_result['data'], LOCK_EX);
                $mime = detectMimeFromBytes($split_result['data']);
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . strlen($split_result['data']));
                header('Cache-Control: private, max-age=86400');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
                echo $split_result['data'];
                exit;
            } else {
                // 분할 불필요 (세로 이미지 등) → 원본 반환
                $mime = detectMimeFromBytes($original_content);
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . strlen($original_content));
                header('Cache-Control: private, max-age=86400');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
                header('X-Split-Skipped: true'); // 분할 안 됨 표시
                echo $original_content;
                exit;
            }
        }
    }

    // ===== 캐시 생성 로직 (개선) =====
    // ✅ 분할 모드일 때는 원본 캐시에 저장
    $cache_save_path = $split_side ? $cache_img_original : $cache_img;
    $lock_file = "{$cache_dir}/.lock_" . md5($filename);
    $lock_fp = @fopen($lock_file, 'c');
    
    if ($lock_fp && @flock($lock_fp, LOCK_EX | LOCK_NB)) {
        // ✅ 락 획득 성공 → 파일 압축 생성
        
        // 이중 체크: 다른 프로세스가 이미 만들었을 수도
        clearstatcache(true, $cache_save_path);
        if (file_exists($cache_save_path) && filesize($cache_save_path) >= $min_size) {
            @flock($lock_fp, LOCK_UN);
            @fclose($lock_fp);
            @unlink($lock_file);
            
            // 재귀 방지: 다시 읽기
            $content = @file_get_contents($cache_save_path);
            if ($content !== false && strlen($content) >= $min_size) {
                // ✅ 분할 모드: 분할 처리 후 출력
                if ($split_side) {
                    $split_result = splitImage($content, $split_side);
                    if ($split_result['split']) {
                        @file_put_contents($cache_img, $split_result['data'], LOCK_EX);
                        $content = $split_result['data'];
                    }
                }
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = detectMimeFromBytes($content);
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($content));
header('Cache-Control: private, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
echo $content;
                exit;
            }
        }
        
        // 원본 파일 읽기
        $img_data = @file_get_contents($src_path);
        
        if ($img_data === false) {
            @flock($lock_fp, LOCK_UN);
            @fclose($lock_fp);
            @unlink($lock_file);
            
            http_response_code(500);
            echo __("viewer_img_read_fail");
            exit;
        }
        
$original_size = strlen($img_data);
// error_log("📂 [폴더] 압축 시작: {$filename} (" . number_format($original_size) . " bytes)");

// ✅ 강제 압축 모드 적용 (크기/해상도 체크 우회)
$compressed = compressImage($img_data, null, true);  // ← force=true
$compressed_size = strlen($compressed);

// error_log("📂 [폴더] 압축 완료: {$filename} (" . number_format($compressed_size) . " bytes, " . round(($compressed_size/$original_size)*100, 1) . "%)");

// ✅ GIF는 원본 유지되므로 재압축 시도 건너뜀
// 압축 효과가 없으면 (원본과 동일) 품질 낮춰서 재시도 - 단, GIF 제외
$is_gif = (strlen($img_data) >= 6 && (substr($img_data, 0, 6) === 'GIF87a' || substr($img_data, 0, 6) === 'GIF89a'));
if (!$is_gif && $compressed_size === $original_size) {
    // error_log("⚠️ [폴더] 압축 효과 없음, 품질 75로 재시도: {$filename}");
    $compressed = compressImage($img_data, 75, true);  // ← force=true
    $compressed_size = strlen($compressed);
    // error_log("📂 [폴더] 재압축 완료 (품질 75): " . number_format($compressed_size) . " bytes");
}
        
        // Atomic write
        $tmp_file = "{$cache_save_path}.tmp." . getmypid();
        $write_result = @file_put_contents($tmp_file, $compressed, LOCK_EX);
        
        if ($write_result === false) {
            @flock($lock_fp, LOCK_UN);
            @fclose($lock_fp);
            @unlink($lock_file);
            @unlink($tmp_file);
            
            // 캐시 실패해도 이미지는 반환
            header('Content-Type: image/jpeg');
            echo $compressed;
            exit;
        }
        
        // ✅ 파일 크기 검증
        clearstatcache(true, $tmp_file);
        $tmp_size = @filesize($tmp_file);
        
        if ($tmp_size < $min_size) {
            // error_log("❌ [폴더] 임시 파일 크기 이상: {$tmp_size} bytes (기대: {$compressed_size})");
            @unlink($tmp_file);
            @flock($lock_fp, LOCK_UN);
            @fclose($lock_fp);
            @unlink($lock_file);
            
            // 캐시 실패해도 이미지는 반환
            header('Content-Type: image/jpeg');
            echo $compressed;
            exit;
        }
        
        // rename 시도
        $rename_success = @rename($tmp_file, $cache_save_path);
        
        if (!$rename_success) {
            // rename 실패 시 copy 대체
            if (@copy($tmp_file, $cache_save_path)) {
                @unlink($tmp_file);
            } else {
                // error_log("❌ [폴더] 파일 이동 실패: {$tmp_file} → {$cache_save_path}");
                @unlink($tmp_file);
                @flock($lock_fp, LOCK_UN);
                @fclose($lock_fp);
                @unlink($lock_file);
                
                // 캐시 실패해도 이미지는 반환
                header('Content-Type: image/jpeg');
                echo $compressed;
                exit;
            }
        }
        
        @chmod($cache_save_path, 0644);
        
        // ✅ 최종 검증
        clearstatcache(true, $cache_save_path);
        $final_size = @filesize($cache_save_path);
        
        if ($final_size < $min_size) {
            // error_log("❌ [폴더] 최종 파일 크기 이상: {$final_size} bytes");
            @unlink($cache_save_path);
        } else {
            // error_log("✅ [폴더] 캐시 저장 성공: {$filename} ({$final_size} bytes)");
        }
        
        @flock($lock_fp, LOCK_UN);
        @fclose($lock_fp);
        @unlink($lock_file);

        // ✅ 분할 모드: 분할 처리 후 출력
        $output_data = $compressed;
        if ($split_side) {
            $split_result = splitImage($compressed, $split_side);
            if ($split_result['split']) {
                @file_put_contents($cache_img, $split_result['data'], LOCK_EX); // 분할 캐시 저장
                $output_data = $split_result['data'];
            }
        }

        // 즉시 출력
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = detectMimeFromBytes($output_data);
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($output_data));
header('Cache-Control: private, max-age=3600'); // 또는 86400 (기존 그대로)
echo $output_data;
        unset($img_data, $compressed, $output_data);
        gc_collect_cycles();
        exit;
        
    } else {
        // ✅ 락 획득 실패 → 대기
        if ($lock_fp) @fclose($lock_fp);
        
        $max_wait = 20; // 15초 → 20초로 증가
        $waited = 0;
        $check_interval = 0.2; // 0.1초 → 0.2초로 증가 (CPU 부하 감소)
        
        // error_log("⏳ [폴더] 대기 시작: {$filename}");
        
        while ($waited < $max_wait) {
            usleep($check_interval * 1000000);
            $waited += $check_interval;
            
            clearstatcache(true, $cache_save_path);
            clearstatcache(true, $lock_file);
            
            // 캐시 파일이 생성되었는지 체크
            if (file_exists($cache_save_path)) {
                $filesize = @filesize($cache_save_path);
                
                if ($filesize >= $min_size) {
                    $content = @file_get_contents($cache_save_path);
                    
                    if ($content !== false && strlen($content) >= $min_size) {
                        // error_log("✅ [폴더] 대기 후 캐시 획득: {$filename} ({$filesize} bytes, {$waited}초 대기)");
                        
                        // ✅ 분할 모드: 분할 처리 후 출력
                        if ($split_side) {
                            $split_result = splitImage($content, $split_side);
                            if ($split_result['split']) {
                                @file_put_contents($cache_img, $split_result['data'], LOCK_EX);
                                $content = $split_result['data'];
                            }
                        }
                        
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = detectMimeFromBytes($content);
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($content));
header('Cache-Control: private, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
echo $content;
                        exit;
                    }
                }
            }
            
            // 락 파일이 사라졌는지 체크 (생성 완료 또는 실패)
            if (!file_exists($lock_file)) {
                // 락은 없는데 캐시도 없으면 → 생성 실패
                if (!file_exists($cache_save_path)) {
                    // error_log("⚠️ [폴더] 락 해제됐지만 캐시 없음: {$filename}");
                    break;
                }
            }
        }
        
        // ✅ 대기 시간 초과 → 직접 생성 (절대 원본 그대로 저장 금지)
        // error_log("⚠️ [폴더] 대기 시간 초과 ({$waited}초), 직접 생성: {$filename}");
        
        $img_data = @file_get_contents($src_path);
        
        if ($img_data !== false) {
            // ✅ GIF 감지 - 애니메이션 보존을 위해 원본 유지
            $is_gif_fallback = (strlen($img_data) >= 6 && (substr($img_data, 0, 6) === 'GIF87a' || substr($img_data, 0, 6) === 'GIF89a'));
            
            if ($is_gif_fallback) {
                // GIF는 압축 없이 원본 사용
                $compressed = $img_data;
            } else {
                // ✅ 반드시 압축 (품질 70으로 강제)
                $compressed = compressImage($img_data, 70, true); // ← force=true
                
                // ✅ 압축이 정말 안 되면 품질 낮춰서 재시도
                if (strlen($compressed) === strlen($img_data)) {
                    $compressed = compressImage($img_data, 65, true);
                    // error_log("❌ [폴더] 압축 실패: {$filename}");
                    
                    // 여전히 압축 안 되면 원본 사용 (에러 대신)
                    if (strlen($compressed) === strlen($img_data)) {
                        $compressed = $img_data;
                    }
                }
            }
            
            // ✅ 분할 모드: 분할 처리 후 출력
            $output_data = $compressed;
            if ($split_side) {
                $split_result = splitImage($compressed, $split_side);
                if ($split_result['split']) {
                    @file_put_contents($cache_img, $split_result['data'], LOCK_EX); // 분할 캐시 저장
                    $output_data = $split_result['data'];
                }
            }
            
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = detectMimeFromBytes($output_data);
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($output_data));
header('Cache-Control: private, max-age=3600'); // 또는 86400 (기존 그대로)
echo $output_data;
            
            // 백그라운드 저장 시도 (원본 캐시)
            @file_put_contents($cache_save_path, $compressed, LOCK_EX);
            
            // error_log("✅ [폴더] 직접 생성 완료: {$filename} (" . strlen($compressed) . " bytes)");
            
            unset($img_data, $compressed, $output_data);
            gc_collect_cycles();
            exit;
        }
        
        http_response_code(500);
        echo __("viewer_img_timeout");
        exit;
    }
} // ← ✅ 폴더 처리 if 닫기

    // ===== ZIP 파일 처리 =====
    if (!file_exists($base_file) || !preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $base_file)) {
        simple_error_exit(404, __('viewer_archive_invalid'));
    }

    $cache_file = $base_file . '.image_files.json';
    $_gen_ready = false;
    if (!file_exists($cache_file)) {
        // ★ (2026-10-30) 목록 캐시가 아직 없으면(폴더 화면을 거치지 않고 작품 탐색·즐겨찾기에서 바로 연 경우) 여기서 만듦
        //   — index.php 와 같은 형식(이미지 이름 natsort 배열). 전에는 이 경우 모든 이미지가 404 였음
        $_gen = [];
        $_gext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
        if (in_array($_gext, ['zip', 'cbz'], true)) {
            $_gz = new ZipArchive;
            if ($_gz->open($base_file) === TRUE) {
                for ($_gi = 0; $_gi < $_gz->numFiles; $_gi++) {
                    $_gn = mc_zip_name($_gz, $_gi);
                    if (is_string($_gn) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $_gn)) $_gen[] = $_gn;
                }
                $_gz->close();
            }
        } elseif (class_exists('ArchiveHandler')) {
            try {
                $_gh = new ArchiveHandler($base_file);
                foreach ($_gh->getImageFiles() as $_gn) { if (is_string($_gn) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $_gn)) $_gen[] = $_gn; } // index.php 와 같은 확장자
            } catch (\Throwable $e) {}
        }
        if (empty($_gen)) {
            simple_error_exit(404, __('viewer_cache_not_found'));
        }
        natsort($_gen);
        $_gen = array_values($_gen);
        // 임시 파일에 쓴 뒤 이름 바꾸기 — 동시에 온 다른 이미지 요청이 쓰는 도중의 빈 파일을 읽지 않게
        $_gtmp = $cache_file . '.tmp' . bin2hex(random_bytes(4));
        if (@file_put_contents($_gtmp, json_encode($_gen, JSON_UNESCAPED_UNICODE)) !== false) {
            if (!@rename($_gtmp, $cache_file)) @unlink($_gtmp);
        }
        $image_files = $_gen;
        $_gen_ready = true;
    }

    if (!$_gen_ready) {
        $image_files = json_decode((string)@file_get_contents($cache_file), true);
        if (!is_array($image_files) || !$image_files) { // 다른 요청이 쓰는 중이었으면 한 번 더
            usleep(200000);
            $image_files = json_decode((string)@file_get_contents($cache_file), true) ?? [];
        }
    }
    if (!isset($image_files[$img_index])) {
        simple_error_exit(404, __('viewer_img_index_none'));
    }

    $user_id = preg_replace('/[^a-zA-Z0-9]/', '', $user_id);
    $zip_hash = cacheKeyFromPath($base_file);
    $cache_dir = __DIR__ . "/cache/{$user_id}/{$zip_hash}";

    clearstatcache();
    if (file_exists($cache_dir) && !is_dir($cache_dir)) {
        unlink($cache_dir);
    }

    if (!is_dir($cache_dir) && !@mkdir($cache_dir, 0755, true)) {
        if (!is_dir($cache_dir)) {
            simple_error_exit(500, __('viewer_cache_dir_fail'));
        }
    }

    $filename = mc_zip_cache_name($image_files[$img_index]); // ★ (2026-10-30) 하위 폴더가 다른 같은 이름 충돌 방지(cache_util.php 와 같은 규칙)
    
    // ✅ 분할 모드: 캐시 파일명에 접미사 추가
    $split_suffix = $split_side ? "_{$split_side}" : '';
    $filename_parts = pathinfo($filename);
    $cache_filename = $filename_parts['filename'] . $split_suffix . '.' . ($filename_parts['extension'] ?? 'jpg');
    $cache_img = "{$cache_dir}/{$cache_filename}";
    $cache_img_original = "{$cache_dir}/{$filename}"; // 원본 캐시 경로
    $cache_save_path = $split_side ? $cache_img_original : $cache_img;
    $min_size = 1000;

    if (file_exists($cache_img)) {
        clearstatcache(true, $cache_img);
        $filesize = @filesize($cache_img);
        
        if ($filesize >= $min_size) {
            $content = @file_get_contents($cache_img);
            
            if ($content !== false && strlen($content) >= $min_size) {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = detectMimeFromBytes($content);
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($content));
header('Cache-Control: private, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
echo $content;
                exit;
            }
        }
        
        @unlink($cache_img);
    }
    
    // ✅ 분할 모드: 원본 캐시에서 분할 생성
    if ($split_side && file_exists($cache_img_original)) {
        $original_content = @file_get_contents($cache_img_original);
        if ($original_content !== false && strlen($original_content) >= $min_size) {
            $split_result = splitImage($original_content, $split_side);
            
            if ($split_result['split']) {
                // 분할 성공 → 분할 캐시 저장 후 반환
                @file_put_contents($cache_img, $split_result['data'], LOCK_EX);
                $mime = detectMimeFromBytes($split_result['data']);
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . strlen($split_result['data']));
                header('Cache-Control: private, max-age=86400');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
                echo $split_result['data'];
                exit;
            } else {
                // 분할 불필요 (세로 이미지 등) → 원본 반환
                $mime = detectMimeFromBytes($original_content);
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . strlen($original_content));
                header('Cache-Control: private, max-age=86400');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
                header('X-Split-Skipped: true'); // 분할 안 됨 표시
                echo $original_content;
                exit;
            }
        }
    }

    $lock_file = "{$cache_dir}/.lock_" . md5($filename);
    $lock_fp = @fopen($lock_file, 'c');
    
    if ($lock_fp && @flock($lock_fp, LOCK_EX | LOCK_NB)) {
        // ✅ [RAR/7Z 지원] ZIP은 ZipArchive, RAR/7Z는 archive_handler로 분기 추출
        $img_data = archive_get_image_data($base_file, $image_files[$img_index], $img_index);
        if (true) {
            if ($img_data !== false) {
                $compressed = compressImage($img_data, null, true); // ← ✅ force=true
                
                // ✅ 원본 캐시에 저장
                $tmp_file = "{$cache_save_path}.tmp." . getmypid();
                $_wr = @file_put_contents($tmp_file, $compressed, LOCK_EX);
                if ($_wr !== false) {
                    if (@rename($tmp_file, $cache_save_path)) {
                        @chmod($cache_save_path, 0644);
                    } else {
                        @copy($tmp_file, $cache_save_path);
                        @unlink($tmp_file);
                    }
                }

                @flock($lock_fp, LOCK_UN);
                @fclose($lock_fp);
                @unlink($lock_file);

                // ✅ 분할 모드: 분할 처리 후 출력
                $output_data = $compressed;
                if ($split_side) {
                    $split_result = splitImage($compressed, $split_side);
                    if ($split_result['split']) {
                        @file_put_contents($cache_img, $split_result['data'], LOCK_EX); // 분할 캐시 저장
                        $output_data = $split_result['data'];
                    }
                }

                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = detectMimeFromBytes($output_data);
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($output_data));
header('Cache-Control: private, max-age=3600'); // 또는 86400 (기존 그대로)
echo $output_data;
                unset($img_data, $compressed, $output_data);
                gc_collect_cycles();
                exit;
            } else {
                @flock($lock_fp, LOCK_UN);
                @fclose($lock_fp);
                @unlink($lock_file);
                
                if (archive_is_encrypted($base_file)) archive_locked_image_exit(); // ★ (2026-10-31) 암호 압축 안내 그림
                http_response_code(500);
                echo __("viewer_extract_fail");
                exit;
            }
        } else {
            @flock($lock_fp, LOCK_UN);
            @fclose($lock_fp);
            @unlink($lock_file);
            
            http_response_code(500);
            echo __("viewer_open_fail");
            exit;
        }
    } else {
        if ($lock_fp) @fclose($lock_fp);
        
        $max_wait = 15;
        $waited = 0;
        
        while ($waited < $max_wait) {
            usleep(100000);
            $waited += 0.1;
            
            clearstatcache(true, $cache_save_path);
            
            if (file_exists($cache_save_path)) {
                $filesize = @filesize($cache_save_path);
                
                if ($filesize >= $min_size) {
                    $content = @file_get_contents($cache_save_path);
                    
                    if ($content !== false && strlen($content) >= $min_size) {
                        // ✅ 분할 모드: 분할 처리 후 출력
                        if ($split_side) {
                            $split_result = splitImage($content, $split_side);
                            if ($split_result['split']) {
                                @file_put_contents($cache_img, $split_result['data'], LOCK_EX);
                                $content = $split_result['data'];
                            }
                        }
                        
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = detectMimeFromBytes($content);
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($content));
header('Cache-Control: private, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
echo $content;
                        exit;
                    }
                }
            }
            
            if (!file_exists($lock_file)) {
                if (!file_exists($cache_save_path)) {
                    break;
                }
            }
        }
        
        // error_log("⚠️ 캐시 대기 시간 초과, 직접 생성 시도");
        
        // ✅ [RAR/7Z 지원] ZIP은 ZipArchive, RAR/7Z는 archive_handler로 분기 추출
        $img_data = archive_get_image_data($base_file, $image_files[$img_index], $img_index);
        if (true) {
            if ($img_data !== false) {
                $compressed = compressImage($img_data, null, true); // ← ✅ force=true
                
                // ✅ 분할 모드: 분할 처리 후 출력
                $output_data = $compressed;
                if ($split_side) {
                    $split_result = splitImage($compressed, $split_side);
                    if ($split_result['split']) {
                        @file_put_contents($cache_img, $split_result['data'], LOCK_EX); // 분할 캐시 저장
                        $output_data = $split_result['data'];
                    }
                }
                
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = detectMimeFromBytes($output_data);
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($output_data));
header('Cache-Control: private, max-age=3600'); // 또는 86400 (기존 그대로)
echo $output_data;
                
                @file_put_contents($cache_save_path, $compressed, LOCK_EX); // 원본 캐시 저장
                
                unset($img_data, $compressed, $output_data);
                gc_collect_cycles();
                exit;
            }
        }
        
        if (archive_is_encrypted($base_file)) archive_locked_image_exit(); // ★ (2026-10-31) 암호 압축 안내 그림
        http_response_code(500);
        echo __("viewer_load_fail");
        exit;
    }
} // ← ✅ 최상위 if (isset($_GET['img'])) 닫기

?>
<?php
$getfile = decode_file_param($_GET['file'] ?? '');
if(!$getfile){
	echo __("viewer_no_info");
	// ★ (2026-10-30) 이미 출력이 나가 header(Location) 이 안 먹음 — 화면 새로고침으로 이동
	die('<meta http-equiv="refresh" content="2; url=./">');
}

// ✅ 경로 검증
$base_file = validate_file_path($getfile, $base_dir);
if ($base_file === false) {
    log_user_activity('해킹시도', 'viewer: ' . substr($getfile, 0, 100));
    echo __("err_invalid_path");
    die('<meta http-equiv="refresh" content="2; url=./">');
}

// ✅ 열람 로그 기록 (페이지 로드 시 1회만)
$view_filename = basename($getfile);
$view_folder = dirname($getfile);
log_user_activity('열람', $view_folder . '/' . $view_filename);

dir_check($getfile);

$base_title = explode("/", $base_file);
$title = $base_title[(count($base_title)-1)];
// ★ (2026-10-30) 끝의 파일(폴더) 이름만 떼어냄 — str_replace 는 경로 안의 같은 글자를 전부 지워
//   폴더 이름이 '1' 같은 경우 /comics/Vol1/1 → /comics/Vol/ 처럼 부모 경로가 깨졌음
$base_folder = (substr($base_file, -strlen($title)) === $title) ? substr($base_file, 0, strlen($base_file) - strlen($title)) : str_replace($title, "", $base_file);
$link_dir = (substr($getfile, -strlen("/".$title)) === "/".$title) ? substr($getfile, 0, strlen($getfile) - strlen("/".$title)) : str_replace("/".$title, "", $getfile);
// $link_dir이 /로 시작하지 않으면 추가
if (!empty($link_dir) && $link_dir[0] !== '/') {
    $link_dir = '/' . $link_dir;
}

// PHP 7.x 호환: match → switch
$type = null;
if ($_param_filetype === "video_archive") {
    $type = "video_archive";
} elseif ($_param_filetype === "video" || is_video_file($base_file)) {
    $type = "video";
} elseif (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $base_file)) {
    $type = "zip";
} elseif ($_param_filetype === "pdf" || preg_match('/\.pdf$/i', $getfile)) {
    $type = "pdf";
} elseif (is_file($base_file.".image_files.json")) {
    $type = "images";
}

$mode = $_GET['mode'] ?? 'toon';
$json_file = null;
$pageorder = ['page_order' => '0', 'viewer' => 'toon'];

if($type != "pdf"){
    $bookmark_arr = [];
    $bookmark = 0;
    
    // ✅ 함수 사용으로 통일 (전역 변수 의존 제거)
    if(is_file(get_bookmark_file())){
        $bookmark_arr = json_decode(file_get_contents(get_bookmark_file()), true) ?? [];
        $__bmk = mc_rec_find($bookmark_arr, $current_bidx, $getfile); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 책갈피만(예전: 같은 경로면 다른 콘텐츠 폴더 책의 쪽으로 열렸음)
        $bookmark = $__bmk !== null ? $bookmark_arr[$__bmk] : null;
        
        if(is_array($bookmark)){
            $bookmark = $bookmark['bookmark'];
        }
    }

    // PHP 7.x 호환: match → if-elseif
    // ✅ [RAR/7Z 지원] 확장자 정규식으로 .json 경로 결정 (ZIP/RAR/7z 공통)
    if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $base_file)) {
        $json_file = get_cache_json_path($base_file);
    } elseif ($_param_filetype === "images" || $type === "images") { // ★ (2026-10-30) 북마크·자동저장 링크처럼 filetype 없이 연 이미지 폴더도
        $json_file = $base_file . ".image_files.json";
    } else {
        $json_file = null;
    }
    
    if($json_file && is_file($json_file)){
        $__po_read = @json_decode((string)@file_get_contents($json_file), true); // ★ (2026-11-03 23:34) 못 읽었으면(다른 요청이 쓰는 중 등) 아래에서 기본값으로 덮어쓰지 않음 — 쪽수·표지가 사라졌음
        $pageorder = is_array($__po_read) ? $__po_read : $pageorder;
        
        // ✅ page_order는 URL 파라미터로만 변경, 기본값은 항상 0 (-)
        if (isset($_GET['pageorder'])) {
            $newpageorder = get_param('pageorder', 'int', 0);
            if (in_array($newpageorder, [0, 1, 2, 3, 4], true)) {
                $pageorder['page_order'] = (string)$newpageorder;
            }
            if (is_array($__po_read)) @file_put_contents($json_file, json_encode($pageorder, JSON_UNESCAPED_UNICODE), LOCK_EX);
        } else {
            // URL 파라미터 없으면 기본값 0 (-) 으로 시작
            $pageorder['page_order'] = '0';
        }

        // PHP 7.x 호환: match → if-elseif
        $get_mode = $_GET['mode'] ?? null;
        if ($get_mode === 'toon') {
            if (($pageorder['viewer'] ?? null) !== 'toon') {
                $pageorder['viewer'] = 'toon';
                @file_put_contents($json_file, json_encode($pageorder, JSON_UNESCAPED_UNICODE), LOCK_EX);
            }
            $mode = 'toon';
        } elseif ($get_mode === 'book') {
            $mode = 'book';
        } elseif ($get_mode === null) {
            $mode = $pageorder['viewer'] ?? 'toon';
        } else {
            $mode = in_array(get_param('mode', 'string'), ['toon', 'book'], true) ? get_param('mode', 'string') : 'toon';
        }
        
    } elseif ($json_file && !is_file($json_file)) {    
        if(preg_match('/\.(zip|cbz)$/i', $base_file)){
            $zip = new ZipArchive;
            // ★ (2026-10-21) `== TRUE` → `=== TRUE`: 열기 실패 코드(정수)를 성공으로 보고 getFromIndex()에서 PHP 8 ValueError(Fatal)로
            //   뷰어가 깨지던 문제. 실패 시 메타 생성을 건너뛰고(json 미작성 → 다음 열람 때 재시도) $mode 는 위에서 정한 기본값 사용.
            if ($zip->open($base_file) === TRUE) {
                $thumbnail_index = 0;
                $_found_thumb = false;
                $_img_total = 0; // ★ (2026-10-30) 쪽수는 이미지 개수(전에는 폴더·txt 항목까지 센 numFiles)
                for ($findthumb = 0; $findthumb < $zip->numFiles; $findthumb++) {
                    $find_img = $zip->getNameIndex($findthumb);
                    if(is_string($find_img) && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $find_img)) $_img_total++; // 목록·image_files.json 과 같은 확장자
                    if(!$_found_thumb && preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', (string)$find_img)){
                        $thumbnail_index = $findthumb;
                        $_found_thumb = true;
                    }
                }

                $img_data = $zip->getFromIndex($thumbnail_index);
                $cropimage = null;
                
                if ($img_data !== false) {
                    $size = @getimagesizefromstring($img_data);
                    if ($size !== false) {
                        $originimage = function_exists('mc_gd_from_string') ? mc_gd_from_string($img_data) : @imagecreatefromstring($img_data); // ★ (2026-11-02 09:58) 너무 큰 그림은 썸네일 없이
                        if ($originimage !== false) {
                            [$width, $height] = $size;
                            
                            if ($width > $height) {
                                $x_point = max(0, ($width / 2) - $height);
                                $cropimage = imagecrop($originimage, [
                                    'x' => (int)$x_point, 
                                    'y' => 0, 
                                    'width' => $height, 
                                    'height' => $height
                                ]);
                            } else {
                                $y_point = ($height - $width) / 2;
                                $cropimage = imagecrop($originimage, [
                                    'x' => 0, 
                                    'y' => (int)$y_point, 
                                    'width' => $width, 
                                    'height' => $width
                                ]);
                            }
                            
                            if($cropimage){
                                $resized = imagecreatetruecolor(400, 400);
                                $crop_size = $width > $height ? $height : $width;
                                imagecopyresampled($resized, $cropimage, 0, 0, 0, 0, 400, 400, $crop_size, $crop_size);
                                imagedestroy($cropimage);
                                
                                ob_start();
                                imagejpeg($resized, null, 75);
                                imagedestroy($resized);
                                $cropimage = ob_get_clean();
                            }
                            
                            imagedestroy($originimage);
                        }
                    }
                }

                $pageorder = [
                    'totalpage' => $_img_total,
                    'page_order' => "0",
                    'viewer' => "toon",
                    'thumbnail' => base64_encode($cropimage ?? '')
                ];
                
                @file_put_contents($json_file, json_encode($pageorder, JSON_UNESCAPED_UNICODE), LOCK_EX);
                $mode = $pageorder['viewer'];
                $zip->close();
            }
        } elseif (preg_match('/\.(rar|cbr|7z|cb7)$/i', $base_file) && class_exists('ArchiveHandler')) {
            // ✅ [RAR/7Z 지원] ZIP 분기와 동일 동작 (ArchiveHandler로 썸네일+메타 생성)
            try {
                $_h = new ArchiveHandler($base_file);
                $_imgs = $_h->getImageFiles();
                $cropimage = null;
                if (!empty($_imgs)) {
                    $img_data = $_h->extractFile($_imgs[0]);
                    if ($img_data !== false && $img_data !== null) {
                        $size = @getimagesizefromstring($img_data);
                        if ($size !== false) {
                            $originimage = function_exists('mc_gd_from_string') ? mc_gd_from_string($img_data) : @imagecreatefromstring($img_data); // ★ (2026-11-02 09:58) 너무 큰 그림은 썸네일 없이
                            if ($originimage !== false) {
                                [$width, $height] = $size;
                                if ($width > $height) {
                                    $x_point = max(0, ($width / 2) - $height);
                                    $cropimage = imagecrop($originimage, ['x' => (int)$x_point, 'y' => 0, 'width' => $height, 'height' => $height]);
                                } else {
                                    $y_point = ($height - $width) / 2;
                                    $cropimage = imagecrop($originimage, ['x' => 0, 'y' => (int)$y_point, 'width' => $width, 'height' => $width]);
                                }
                                if ($cropimage) {
                                    $resized = imagecreatetruecolor(400, 400);
                                    $crop_size = $width > $height ? $height : $width;
                                    imagecopyresampled($resized, $cropimage, 0, 0, 0, 0, 400, 400, $crop_size, $crop_size);
                                    imagedestroy($cropimage);
                                    ob_start();
                                    imagejpeg($resized, null, 75);
                                    imagedestroy($resized);
                                    $cropimage = ob_get_clean();
                                }
                                imagedestroy($originimage);
                            }
                        }
                    }
                }
                $pageorder = [
                    'totalpage' => count($_imgs),
                    'page_order' => "0",
                    'viewer' => "toon",
                    'thumbnail' => base64_encode($cropimage ?? '')
                ];
                @file_put_contents($json_file, json_encode($pageorder, JSON_UNESCAPED_UNICODE), LOCK_EX);
                $mode = $pageorder['viewer'];
            } catch (Exception $e) {
                // 폴백 실패 시 기본값 유지 (크래시 방지)
            }
        }
    }
}

// ★ (2026-11-01) 보기 방식·툰/북 모드를 브라우저(기기)마다 기억 — 위 막대(js/viewer-bar.js)가 고를 때 쿠키에 적고, 여기서 열 때 읽음(책마다 X)
//   · 보기 방식: 주소에 pageorder 가 없을 때만(있으면 그 값 — 막대도 같은 값을 쿠키에 먼저 적음) / 예전엔 열 때마다 '한 쪽'(0)
//   · 모드: 목록·이어 읽기 링크는 늘 mode=toon 이라 쿠키를 우선(막대에서 바꿀 때 쿠키를 먼저 바꾸고 이동)
//   · 압축 파일·이미지 폴더만(PDF·동영상은 그대로), 정해진 값만 받음
if ($type === 'zip' || $type === 'images') {
    if (!isset($_GET['pageorder'])) {
        // ★ (2026-11-05) 모드 기억 설정을 따라(책마다면 이 책의 내 기록 → 없으면 마지막에 고른 값) — function.php mc_mv_order_for(), 목록 카드 배지와 같은 값
        if (function_exists('mc_mv_order_for')) {
            if (is_array($pageorder)) $pageorder['page_order'] = mc_mv_order_for($getfile, $current_bidx, (string)($pageorder['page_order'] ?? '0'));
        } else {
            $_mcv_c_order = (is_string($_COOKIE['mcv_order'] ?? null) ? $_COOKIE['mcv_order'] : '');
            if (in_array($_mcv_c_order, ['0', '1', '2', '3', '4'], true) && is_array($pageorder)) $pageorder['page_order'] = $_mcv_c_order;
        }
    }
    // ★ (2026-11-01 23:37) 모드 기억을 설정(끔·전체·책마다)으로 — function.php mc_mv_mode_for() / 위 막대에서 모드를 바꿔 들어오면(ms=1) 주소의 모드 그대로
    if (!isset($_GET['ms']) && function_exists('mc_mv_mode_for')) $mode = mc_mv_mode_for($getfile, $current_bidx, $mode);
    // ★ (2026-11-01 23:37) 이어 보기 — 주소에 위치가 없으면 내 기록의 보던 쪽에서(아래 <head> 맨 앞 스크립트가 #imageN 을 붙임)
    $_mcv_resume = (($mode === 'toon' || $mode === 'book') && function_exists('mc_mv_resume_mark')) ? mc_mv_resume_mark($getfile, $current_bidx, $mode) : '';
}
// ★ (2026-11-02 01:01) PDF 도 이어 보기 — 내 기록의 보던 쪽·쪽 안 위치(#pdf_NfM)에서 (설정 '이어 보기' 끔이면 처음부터)
if ($type === 'pdf') {
    $_mcv_resume = function_exists('mc_mv_resume_mark') ? mc_mv_resume_mark($getfile, $current_bidx, 'pdf') : '';
}
// ★ (2026-11-08 전체 점검 7차 fix79) 보기 방식(page_order)은 0~4 만 — 주소의 pageorder 가 0~4 가 아니면(예: 9) 책 옆 정보 파일(.json)의 값을 거르지 않고 그대로 써서,
//   손으로 만든 .json(받은 만화 묶음에 같이 든 파일 등)의 값이 아래 JS 글자(pageOrder)로 그대로 들어가 스크립트가 실행됐음
if (!is_array($pageorder)) $pageorder = ['page_order' => '0', 'viewer' => 'toon'];
if (!in_array((string)($pageorder['page_order'] ?? '0'), ['0', '1', '2', '3', '4'], true)) $pageorder['page_order'] = '0';

// ✅ 이전/다음 파일 찾기 - 캐시 활용
$totalfile = [];
$filelist_cache = $base_folder . '/.filelist_cache.json';

if (is_file($filelist_cache)) {
    // 캐시에서 file_list 읽기
    $list_cache = @json_decode(file_get_contents($filelist_cache), true);
    if ($list_cache && isset($list_cache['file_list']) && is_array($list_cache['file_list'])) {
        // ✅ file_list는 배열의 배열이므로 name만 추출
        $totalfile = array_column($list_cache['file_list'], 'name');
        // ★ (2026-11-03 09:06) 아래 직접 훑기와 같은 대상·순서로 — 캐시 목록은 폴더를 읽은 순서 그대로(정렬 안 됨)이고 문서(docx·hwp·epub 등)까지 들어 있어
        //   이전/다음 권이 'vol 2 → vol 10' 처럼 건너뛰거나 문서 파일을 만화 뷰어로 열어 빈 화면이었음
        $totalfile = array_map(function ($f) { return (is_string($f) && substr($f, -10) === '_imgfolder') ? substr($f, 0, -10) : $f; }, $totalfile); // ★ (2026-11-03 11:33) 목록 캐시의 이미지 폴더 이름(꼬리 _imgfolder)을 실제 폴더 이름으로 — 이전/다음 권에서 이미지 폴더 책이 빠졌음
        $totalfile = array_values(array_filter($totalfile, function ($f) use ($base_folder) {
            if (!is_string($f) || $f === '' || preg_match('/\.json$/i', $f)) return false; // ★ (2026-11-04 09:44) 확장자로 — 이름에 'json' 이 든 책(예: 'Jsonny 3권.zip')이 이전/다음 권에서 빠졌음
            return preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf)$/i', $f) || is_video_file($f) || is_file($base_folder . '/' . $f . '.image_files.json');
        }));
        if ($totalfile) $totalfile = array_values(n_sort($totalfile));
    }
}

// 캐시 없거나 비어있으면 직접 스캔 — ★ (2026-11-02 09:59) 캐시에 지금 책이 없으면(새로 넣은 권 등 캐시가 오래됨) 다시 훑음(전에는 이전 권 없음·다음 권이 엉뚱했음)
if (empty($totalfile) || array_search($title, $totalfile) === false) { // (이미지 폴더 책도 여기서 다시 훑어야 이전·다음 권이 맞음)
    $totalfile = [];
    $files = scandir($base_folder);
    $files = n_sort($files);
    
    foreach ($files as $file) {
        if(preg_match('/\.json$/i', $file) || in_array($file, [".", "..", "@eaDir"])){ // ★ (2026-11-04 09:44) 위와 같음
            continue;
        }
        
        if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf)$/i', $file) || // ★ (2026-10-30) 7z/cb7 도 이전·다음 대상
            is_file($base_folder."/".$file.".image_files.json") ||
            is_video_file($file)) {
            $totalfile[] = $file;
        }
    }
}

$now = array_search($title, $totalfile);
if ($now === false) {
    $now = 0;
}
$next = $now + 1;
$pre = $now - 1;
$_maxview = $maxview_file ?? $maxview ?? 100;  // 호환성 유지
// ★ (2026-11-04 00:31) 휴대폰이면 목록(index.php)과 같은 쪽당 개수 — 전엔 PC 값으로 계산돼 '← 목록'이 엉뚱한 쪽을 열었음
if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT'])) {
    $_maxview = get_app_settings('maxview_file_mobile', 30);
}
$_maxview = max(1, (int)$_maxview);
$page = ceil(($now+1)/$_maxview)-1;

$recent = [];
// ✅ 함수 사용으로 통일 (전역 변수 의존 제거, 파일 잠금 적용)
$_recent_file = get_recent_file();
$recent = load_json_with_lock($_recent_file);
// ★ (2026-11-03 15:00) 폴더 화면 '이어 읽기' = 그 폴더에서 마지막으로 연 책(펜닐 선택) — 전엔 목록 순서로 가장 뒤 책이라 앞 권을 다시 열어도 안 바뀌었음
//   (★ 14:11 예전 '_imgfolder' 이름 기록도 이것으로 바로 바뀜) 목록에서 못 찾으면 지금 연 이름 그대로
// ★ (2026-11-03 20:58) 동영상 압축(filetype=video_archive)은 남기지 않음 — '이어 읽기' 단추 주소엔 filetype 이 없어 만화 뷰어로 열려 빈 화면이었음
if ($type !== 'video_archive') {
// ★ (2026-11-05 23:30) 콘텐츠 폴더(bidx)마다 따로 — 열쇠 '번호|폴더 경로'(펜닐 제보: 테스트1/새폴더 에서 읽은 책이 테스트2·3 의 같은 경로 /새폴더 에도 '이어 읽기'로 떠서 눌러도 그림이 안 나왔음)
//   예전 형식(폴더 경로만) 기록은 이 폴더 것이면 새 형식으로 옮기고 지움 — 다른 콘텐츠 폴더의 같은 경로 기록은 목록 화면이 파일이 실제로 있을 때만 씀(index.php)
$recent[(int)$current_bidx . '|' . $link_dir] = (array_search($title, $totalfile) !== false) ? $totalfile[$now] : $title;
unset($recent[$link_dir]);
save_json_with_lock($_recent_file, $recent);
}

?>
<?php
flush(); ob_flush();
?>
<!DOCTYPE html>
<html lang="ko">
   <head>
<?php if ($type != "pdf" && $mode != "book" && function_exists('mc_theme_early_head')) echo mc_theme_early_head(true); /* ★ (2026-11-08 fix82) true = 투명했다 나타나는 화면(다크면 바탕은 어둡게 두고 내용만) / ★ (2026-11-08 fix80) 다크모드 흰 바탕 깜빡임 — 아래 render_darkmode_script 와 같은 조건 */ ?>
<?php if (($type == "pdf" || $mode == "book") && function_exists('mc_fade_dark_style')): /* ★ (2026-11-08 fix82) PDF(회색 #525659)·북모드(검정)는 테마와 상관없이 늘 어두운 화면 — 들어올 때·누르고 나갈 때 투명해진 화면 뒤 흰 바탕이 보이던 것 */ echo mc_fade_dark_style($type == "pdf" ? '#525659' : '#000000'); ?><script>document.documentElement.classList.add('mc-fdk', 'mc-fdk-dark');</script>
<?php endif; ?>
<?php if ($type != "pdf" && $mode == "book"): /* ★ (2026-11-08 fix80, 펜닐 제보) 북모드는 늘 검은 바탕(lightGallery) — 그 창이 뜨기 전 흰 바탕이 잠깐 보이던 것(테마와 상관없이 처음부터 검게) */ ?><style>html, html body { background-color: #000; }</style>
<?php endif; ?>
<?php if (function_exists('mc_back_origin_viewer_js')) echo mc_back_origin_viewer_js(); /* ★ (2026-11-05) 뒤로가기 = 들어온 곳(서재·즐겨찾기·북마크 등) — function.php */ ?>
<?php if (!empty($_mcv_resume) && preg_match('/^(image\d+|pdf_\d{1,6})(f\d{1,3})?$/', $_mcv_resume)): // ★ (2026-11-02 01:01) PDF·쪽 안 위치(f)도 / ★ (2026-11-01 23:37) 이어 보기: 위치(#…) 없이 열면 보던 쪽을 붙임 — 툰모드 복원·북모드 시작 쪽이 이 값을 씀(다른 스크립트보다 먼저) ?>
	<script>(function(){try{if(!location.hash)history.replaceState(history.state,'',location.pathname+location.search+'#<?php echo $_mcv_resume; ?>');}catch(e){}})();</script>
<?php endif; ?>
      <title><?php echo h($_branding['page_title'] ?? 'myComix'); ?> - <?php echo htmlspecialchars($title); ?></title>
	<meta charset="UTF-8">
	<!-- ✅ 핵심 레이아웃 + 페이지 전환 -->
	<style>
		html{opacity:0;transition:opacity .15s ease-in}
		html.ready{opacity:1}
		html.leaving{opacity:0;transition:opacity .1s ease-out}
		*{box-sizing:border-box}
		body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
		img{max-width:100%;height:auto}
	</style>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
	<meta name="format-detection" content="telephone=no">
	<link rel="stylesheet" href="./css/bootstrap.min.css">
	<link rel="stylesheet" href="./css/css2.css?family=Gugi&family=Nanum+Gothic:wght@400;700&display=swap">
	<?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
	<!-- 다크모드 CSS -->
	<?php if (isset($darkmode_settings) && ($darkmode_settings['enabled'] ?? false)): ?>
	<link rel="stylesheet" href="./css/darkmode.css?v=<?php echo @filemtime(__DIR__ . '/css/darkmode.css') ?: '1'; ?>">
	<?php endif; ?>
	<script src="./js/jquery-3.5.1.min.js"></script>
	<script src="./js/popper.min.js"></script>
	<script src="./js/bootstrap.min.js"></script>

<?php
if($mode == "book") {
?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/1.8.2/css/lightgallery.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/1.8.2/js/lightgallery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-mousewheel/3.1.13/jquery.mousewheel.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/lg-fullscreen@1.2.1/dist/lg-fullscreen.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/lightgallery@2.8.3/plugins/zoom/lg-zoom.min.js"></script>

<?php
}
if($type == "pdf") {
?>
	<?php /* ★ (2026-11-08 전체 점검 fix71) Swiper(11.2.8 — CVE-2026-27212 프로토타입 오염 해당 버전)는 불러오기만 하고 어디서도 쓰지 않아(new Swiper 0건, 원본부터) 불러오지 않음 — 파일(js/swiper-bundle.min.js·css)은 그대로 둠 */ ?>
	<?php /* ★ (2026-10-31) PDF.js 2.6.347 → 6.4.299(legacy 빌드, FileStation 과 같은 파일·npm 원본 대조) — 6.x 는 모듈이라 아래 스크립트에서 import() 로 읽음 */ ?>
<?php
}
?>
	<style type="text/css">
		body {
			font-family: 'Nanum Gothic', sans-serif;
			font-size: smaller;
		}
		a:link {text-decoration: none;}
		a:visited {text-decoration: none;}
		a:active {text-decoration: none;}
		a:hover {text-decoration: none;}
		
		/* ✅ 로고 링크 색상 */
		.logo-link, .logo-link:visited, .logo-link:hover, .logo-link:active {
			color: #000000 !important;
			text-decoration: none !important;
		}

<?php if($type == "video"): ?>
/* 동영상 플레이어 스타일 */
.video-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
    padding-top: 80px;
}

.video-container {
    position: relative;
    width: 100%;
    background: #000;
    border-radius: 8px;
    overflow: hidden;
}

.video-container video {
    width: 100%;
    max-height: 65vh;
    display: block;
}

@media (max-width: 767px) {
    .video-wrapper {
        margin-top: 10px;
    }
    
    .video-container video {
        max-height: 35vh !important;
        object-fit: contain !important;
    }
}

.video-controls {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    padding: 10px;
    opacity: 0;
    transition: opacity 0.3s;
}

.video-container:hover .video-controls {
    opacity: 1;
}

.video-info {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-top: 10px;
}

.video-title {
    font-size: 1.2em;
    font-weight: bold;
    margin-bottom: 10px;
    word-break: break-all;
}

.video-meta {
    color: #666;
    font-size: 0.9em;
}

.video-unsupported {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    margin: 80px 20px 20px 20px;
}

.video-unsupported h4 {
    color: #856404;
    margin-bottom: 10px;
}

.video-unsupported p {
    color: #856404;
    margin-bottom: 15px;
}

.zip-video-list {
    margin-top: 20px;
}

.zip-video-item {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: background 0.2s;
}

.zip-video-item:hover {
    background: #e9ecef;
}

.zip-video-item.active {
    background: #007bff;
    color: white;
}

.zip-video-icon {
    margin-right: 10px;
    font-size: 1.5em;
}

.zip-video-name {
    flex: 1;
    word-break: break-all;
}

.zip-video-size {
    font-size: 0.85em;
    color: #666;
}

.zip-video-item.active .zip-video-size {
    color: rgba(255,255,255,0.8);
}

/* ★ (2026-10-31) 다크 모드 — 동영상 정보 칸·압축 동영상 목록이 밝은 바탕(#f8f9fa)에 밝은 글자(다크 모드 본문색 #e0e0e0)라 거의 안 보이던 것.
   라이트 모드는 그대로, 다크 모드일 때만 어두운 바탕(동영상 플레이어 아래 버튼 줄·목록과 같은 색) */
.dark-mode .video-info, [data-theme="dark"] .video-info { background: #1e1e1e; border: 1px solid #333; }
.dark-mode .video-meta, [data-theme="dark"] .video-meta { color: #a8adb3; }
.dark-mode .zip-video-item, [data-theme="dark"] .zip-video-item { background: #1e1e1e; border: 1px solid #333; }
.dark-mode .zip-video-item:hover, [data-theme="dark"] .zip-video-item:hover { background: #2a2a2a; }
.dark-mode .zip-video-item.text-muted, [data-theme="dark"] .zip-video-item.text-muted { color: #8a8f96 !important; }
.dark-mode .zip-video-size, [data-theme="dark"] .zip-video-size { color: #a8adb3; }
.dark-mode .zip-video-item.active, [data-theme="dark"] .zip-video-item.active { background: #1f3352; border-color: #3b5f99; color: #e8f0fe; }
.dark-mode .zip-video-item.active .zip-video-size, [data-theme="dark"] .zip-video-item.active .zip-video-size { color: #bcd0f0; }
<?php endif; ?>

		img.lg-image {
			margin: 0;
			padding: 0;
			min-height:100%;
			min-width:100%;
			object-fit:contain;
		}
		.lg-outer .lg-img-wrap {
			position: absolute;
			padding: 0 0px;
			padding-top: 0px;
			padding-right: 0px;
			padding-bottom: 0px;
			padding-left: 0px;
			left: 0;
			right: 0;
			top: 0;
			bottom: 0;
		}

.image-wrapper {
  position: relative;
  display: inline-block;
  width: fit-content;
  text-align: center;
}

.image-wrapper img {
  display: inline-block;
  max-width: 100%;
  width: auto;
  height: auto;
}

.img-overlay-page {
  position: absolute;
  bottom: 8px;
  right: 12px;
  background: rgba(0, 0, 0, 0.3);
  color: #ffffff;
  padding: 2px 8px;
  border-radius: 8px;
  font-size: 0.85em;
  pointer-events: none;
}
/* ★ (2026-10-21) 이미지 로딩 전에는 페이지 번호 숨김 — 로딩 전 자리(placeholder)에 번호가 먼저 떠서
   가운데에 1~끝 번호가 줄줄이 보이다가, 이미지가 뜨면 오른쪽 하단으로 이동하던 문제.
   이미지가 loaded 되는 순간(lazyload 제거와 동시에) 함께 나타남 — 이미지도 그 순간 즉시 표시되므로 별도 transition 없음. 최종 실패(load-failed)는 어느 페이지인지 알 수 있게 번호 표시 유지. */
img.lazyload:not(.loaded):not(.load-failed) + .img-overlay-page {
  opacity: 0;
}

#scrollTopBtn {
  position: fixed;
  bottom: 90px;
  right: 20px;
  z-index: 9999;
  font-size: 18px;
  background-color: rgba(0, 0, 0, 0.3);
  color: white;
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  padding: 0;
  cursor: pointer;
  outline: none;
  transition: opacity 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}
#scrollTopBtn:hover {
  background-color: rgba(0, 0, 0, 0.4);
}

img.lazyload {
  opacity: 0;
  transition: opacity 0.3s ease-in;
  display: block;
  width: 100%;
  min-height: 250px;
}

img.loaded {
  opacity: 1;
}

#swiper {
  display: block !important;
  flex-direction: column !important;
  white-space: normal !important;
  overflow: visible !important;
}

.lg-outer .lg-sub-html {
  font-family: 'Nanum Gothic', 'Pretendard', 'Noto Sans KR', sans-serif;
  font-size: 14px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.82);
  line-height: 1.4;
  text-shadow: none;
  background: rgba(0, 0, 0, 0.3);
  padding: 6px 10px;
  border-radius: 10px;
  backdrop-filter: blur(2px);
  text-align: left;
  width: fit-content;
  margin-left: 10px;
  bottom: 5px !important;
  position: absolute !important;
  white-space: nowrap;
  word-break: keep-all;
  overflow-wrap: normal;
}

.lg-outer .lg-sub-html .lg-counter {
  white-space: nowrap;
}

.lg-outer .lg-toolbar .lg-icon,
.lg-outer .lg-actions .lg-icon {
  color: rgba(255, 255, 255, 0.85) !important;
}
.lg-outer .lg-toolbar {
  background: linear-gradient(to bottom, rgba(0,0,0,0.35), rgba(0,0,0,0));
}
.lg-outer .lg-actions {
  background: linear-gradient(to top, rgba(0,0,0,0.25), rgba(0,0,0,0));
}

@media (max-width: 767px) {
  .lg-outer .lg-sub-html {
    font-size: 12.5px;
    padding: 5px 8px;
    margin-left: 8px;
	bottom: 5px !important;
  }
}

.lg-outer,
.lg-outer .lg {
  height: 100dvh !important;
}

.lg-outer .lg-inner,
.lg-outer .lg-img-wrap {
  padding: 0 !important;
}

.lg-outer .lg-image {
  width: auto !important;
  height: auto !important;
  max-width: 100% !important;
  max-height: 100% !important;
  object-fit: contain !important;
}

/* ★ (2026-10-29) 북모드 펼침(1|2, 2|1): 한 슬라이드에 두 장을 가운데 맞대어 표시 */
.lg-outer .lg-img-wrap.mc-spread {
  display: flex !important;
  align-items: center;
  justify-content: center;
}
.lg-outer .lg-img-wrap.mc-spread:before {
  display: none !important;
}
.lg-outer .lg-img-wrap.mc-spread > img {
  flex: 0 1 auto;
  max-width: 50% !important;
  max-height: 100% !important;
  min-width: 0 !important;
  min-height: 0 !important;
  width: auto !important;
  height: auto !important;
  margin: 0 !important;
  object-fit: contain !important;
}

/* ★ (2026-11-04 18:00) 북모드 화면 맞춤 — 보기 메뉴 '화면 맞춤'(기본 '화면' = 위 규칙 그대로)
   · 너비(mcv-fit-w): 폭에 꽉 — 쪽이 화면보다 길면 위아래로 밀어 봄(쪽을 넘기면 맨 위부터) · 짧으면 가운데
   · 높이(mcv-fit-h): 높이에 꽉 — 화면보다 넓으면 좌우로 밀어 봄 · 좁으면 가운데
   · 펼침(두 장)은 두 장을 합친 크기로 같은 규칙 · 쪽 넘김은 그대로(좌우 끝 누르기·키·휠 — 휠은 끝까지 내린 다음에 넘김) */
body.mcv-fit-w .lg-outer .lg-img-wrap,
body.mcv-fit-h .lg-outer .lg-img-wrap {
  display: flex !important;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}
body.mcv-fit-w .lg-outer .lg-img-wrap:before,
body.mcv-fit-h .lg-outer .lg-img-wrap:before { display: none !important; }
body.mcv-fit-w .lg-outer .lg-img-wrap { flex-direction: row; align-items: flex-start !important; justify-content: center !important; overflow-x: hidden !important; overflow-y: auto !important; touch-action: pan-y pinch-zoom; }
body.mcv-fit-w .lg-outer .lg-img-wrap > img.lg-image,
body.mcv-fit-w .lg-outer .lg-img-wrap > img {
  flex: 0 0 100%;
  width: 100% !important; max-width: 100% !important;
  height: auto !important; max-height: none !important;
  margin: auto 0 !important; /* 쪽이 짧으면 가운데, 길면 맨 위부터(잘리지 않게) */
}
body.mcv-fit-w .lg-outer .lg-img-wrap.mc-spread > img { flex: 0 0 50%; width: 50% !important; max-width: 50% !important; }
body.mcv-fit-h .lg-outer .lg-img-wrap { flex-direction: row; align-items: stretch !important; justify-content: flex-start !important; overflow-x: auto !important; overflow-y: hidden !important; touch-action: pan-x pinch-zoom; }
body.mcv-fit-h .lg-outer .lg-img-wrap > img.lg-image,
body.mcv-fit-h .lg-outer .lg-img-wrap > img {
  flex: 0 0 auto;
  height: 100% !important; max-height: 100% !important;
  width: auto !important; max-width: none !important;
  margin: 0 !important;
}
body.mcv-fit-h .lg-outer .lg-img-wrap > img:first-child { margin-left: auto !important; }
body.mcv-fit-h .lg-outer .lg-img-wrap > img:last-child { margin-right: auto !important; }

.lg-outer,
.lg-outer * {
  -webkit-touch-callout: none !important;
  -webkit-user-select: none !important;
  user-select: none !important;
  -webkit-user-drag: none !important;
  user-drag: none !important;
}

.lg-prev, .lg-next {
  display: none !important;
}

.lg-zoom-in, .lg-zoom-out {
  visibility: visible;
}

/* 자동 로그아웃 모달이 lightgallery 위에 표시되도록 */
#auto-logout-modal {
  z-index: 99999 !important;
}

#auto-logout-modal * {
  pointer-events: auto !important;
}

	</style>
<!-- ✅ CSS 로드 완료 후 화면 표시 -->
<script>
(function(){
  // ✅ 브라우저 자동 스크롤 복원 비활성화(콜드 리오픈 시 우리 복원을 덮지 않도록). 복원은 우리 JS가 전담.
  if ('scrollRestoration' in history) { try { history.scrollRestoration = 'manual'; } catch(e){} }
  function _show(){ var h=document.documentElement; h.classList.remove('leaving'); h.classList.add('ready'); }
  _show();
  // 브라우저 완전 종료 후 재실행, freeze/discard, bfcache 등 어떤 복원 경로에서도 흰 화면이 남지 않도록 보장.
  window.addEventListener('pageshow', _show);
  document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'visible') _show(); }, {passive:true});
  setTimeout(_show, 1200); // 최후 보루(정상 로드에선 이미 ready라 무해)
})();
</script>
   </head>
<?php
render_viewer_i18n([
    // Favorites
    'fav_remove' => 'js_fav_remove',
    'fav_add' => 'js_fav_add',
    // Navigation
    'nav_prev_next' => 'js_nav_prev_next',
    'nav_next_prev' => 'js_nav_next_prev',
    'page_left' => 'js_page_left',
    'page_right' => 'js_page_right',
    // Video
    'video_unsupported_format' => 'js_video_unsupported_format',
    'video_reencode_confirm' => 'js_video_reencode_confirm',
    'video_convert_confirm' => 'js_video_convert_confirm',
    'video_converting' => 'js_video_converting',
    'video_progress' => 'js_video_progress',
    'video_progress_pct' => 'js_video_progress_pct',
    'video_parse_fail' => 'js_video_parse_fail',
    'video_done' => 'js_video_done',
    'video_elapsed' => 'js_video_elapsed',
    'video_view_converted' => 'js_video_view_converted',
    'video_fail' => 'js_video_fail',
    'video_unknown_error' => 'js_video_unknown_error',
    'video_reencode_btn' => 'js_video_reencode_btn',
    'video_convert_btn' => 'js_video_convert_btn',
    'video_error' => 'js_video_error',
    'video_done_msg' => 'js_video_done_msg',
    'video_select' => 'js_video_select',
    // PDF
    'pdfjs_load_fail' => 'js_pdfjs_load_fail',
    'pdf_load_fail' => 'js_pdf_load_fail',
    'pdf_error' => 'js_pdf_error',
    // Auto logout
    'auto_logout_title' => 'js_auto_logout_title',
    'auto_logout_countdown' => 'js_auto_logout_countdown',
    'continue_msg' => 'js_continue_msg',
    'logout' => 'js_logout',
    'extend_login' => 'js_extend_login',
    'time_hours' => 'js_time_hours',
    'time_minutes' => 'js_time_minutes',
    'time_seconds' => 'js_time_seconds',
]);
?>
<script type="text/javascript">
var scroll_top = 0;
var bright_counter = 0;
<?php
if($mode == "toon"){
?>
					$(document).ready(function(){
						if ($('.navbar').length > 0) {
							 var last_scroll_top = 0;
							$(window).on('scroll', function() {
								scroll_top = $(this).scrollTop();
								if(scroll_top < last_scroll_top) {
									$('.navbar').fadeIn();
								}
								else {
									$('.navbar').fadeOut();
									$('.collapse').fadeOut();
									document.getElementById("info").value = "";
								}
								last_scroll_top = scroll_top;
							});
						}
					});
					function hidenav() {
						$('.navbar').fadeToggle();
						$('.collapse').fadeOut();
					};

					// ✅ 툰모드 키보드 단축키 (방향키 위/아래, PageUp/Down, Home/End)
					document.addEventListener('keydown', function(e) {
						var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
						if (tag === 'input' || tag === 'textarea' || tag === 'select' || tag === 'button' || e.isComposing) return;

						var code = e.key || e.code || '';
						var kc = e.keyCode || 0;
						var scrollAmount = window.innerHeight * 0.85;

						if (code === 'ArrowDown' || kc === 40) {
							e.preventDefault();
							window.scrollBy({ top: 200, behavior: 'smooth' });
						} else if (code === 'ArrowUp' || kc === 38) {
							e.preventDefault();
							window.scrollBy({ top: -200, behavior: 'smooth' });
						} else if (code === 'PageDown' || kc === 34) {
							e.preventDefault();
							window.scrollBy({ top: scrollAmount, behavior: 'smooth' });
						} else if (code === 'PageUp' || kc === 33) {
							e.preventDefault();
							window.scrollBy({ top: -scrollAmount, behavior: 'smooth' });
						} else if (code === 'Home' || kc === 36) {
							e.preventDefault();
							window.scrollTo({ top: 0, behavior: 'smooth' });
						} else if (code === 'End' || kc === 35) {
							e.preventDefault();
							window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
						}
					});

<?php
} elseif($mode == "book"){
?>

// ✅ 북모드 전용: 완전 강제 모달
document.addEventListener('DOMContentLoaded', function() {
  console.log('📚 북모드 - 모달 보호 활성화');
  
  let customModalActive = false;
  
  const observer = new MutationObserver(function(mutations) {
    const originalModal = document.getElementById('auto-logout-modal');
    const customModal = document.getElementById('book-mode-logout-modal');
    const lgOuter = document.querySelector('.lg-outer');
    
    if (customModalActive && customModal) return;
    
    if (originalModal && originalModal.style.display === 'flex' && !customModal) {
      console.log('🔒 원본 모달 감지 - 커스텀 모달 생성');
      
      customModalActive = true;
      originalModal.style.display = 'none';
      
      if (lgOuter) {
        lgOuter.style.display = 'none';
        lgOuter.style.pointerEvents = 'none';
      }
      
      // ✅ 모든 lightgallery 관련 요소 차단
      document.querySelectorAll('.lg-outer, .lg-backdrop, .lg-item').forEach(function(el) {
        el.style.pointerEvents = 'none';
        el.style.display = 'none';
      });
      
      const newModal = document.createElement('div');
      newModal.id = 'book-mode-logout-modal';
      newModal.setAttribute('data-modal', 'true');
      newModal.style.cssText = `
        display: flex !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0,0,0,0.9) !important;
        z-index: 2147483647 !important;
        justify-content: center !important;
        align-items: center !important;
        pointer-events: auto !important;
        cursor: default !important;
      `;
      
newModal.innerHTML = `
  <div style="background:white; padding:50px; border-radius:20px; text-align:center; max-width:500px; 
       box-shadow: 0 20px 60px rgba(0,0,0,0.7); pointer-events:auto; position:relative; z-index:2147483647;">
    <h3 style="color:#dc3545; margin-bottom:30px; font-size:28px; font-weight:bold;">${_vi18n.auto_logout_title}</h3>
    <p style="font-size:20px; margin-bottom:30px;">
      <strong id="book-countdown" style="font-size:64px; color:#dc3545; display:block; margin:30px 0; font-weight:bold;">0</strong>
      <span style="font-size:24px; font-weight:500;">${_vi18n.auto_logout_countdown}</span>
    </p>
    <p style="color:#666; font-size:18px; margin-bottom:40px;">
      ${_vi18n.continue_msg}
    </p>
    <div style="display:flex; gap:20px; justify-content:center;">
      <button id="book-btn-logout" 
              style="padding:20px 40px; font-size:18px; cursor:pointer; pointer-events:auto;
                     background:#6c757d; color:white; border:none; border-radius:10px; font-weight:bold;
                     box-shadow: 0 4px 12px rgba(0,0,0,0.2); position:relative; z-index:2147483647;">
        ${_vi18n.logout}
      </button>
      <button id="book-btn-extend" 
              style="padding:20px 40px; font-size:18px; cursor:pointer; pointer-events:auto;
                     background:#007bff; color:white; border:none; border-radius:10px; font-weight:bold;
                     box-shadow: 0 4px 12px rgba(0,0,0,0.2); position:relative; z-index:2147483647;">
        ${_vi18n.extend_login}
      </button>
    </div>
  </div>
`;
      
      document.body.appendChild(newModal);
      document.body.style.overflow = 'hidden'; // 스크롤 방지
      
      console.log('✅ 커스텀 모달 생성 완료');
      
      let lastCountdown = 0;
      let modalClosed = false;
      
      const countdownInterval = setInterval(function() {
        if (modalClosed) {
          clearInterval(countdownInterval);
          return;
        }
        
        const originalCountdown = document.getElementById('logout-countdown');
        const bookCountdown = document.getElementById('book-countdown');
        const bookModal = document.getElementById('book-mode-logout-modal');
        
        if (!bookModal) {
          clearInterval(countdownInterval);
          return;
        }
        
        if (originalCountdown && bookCountdown) {
          const currentCount = parseInt(originalCountdown.textContent);
          bookCountdown.textContent = currentCount;
          
          if (lastCountdown > 0 && (currentCount - lastCountdown) > 10) {
            console.log('🔓 모달 닫힘 (연장: ' + lastCountdown + ' → ' + currentCount + ')');
            modalClosed = true;
            bookModal.remove();
            document.body.style.overflow = '';
            
            if (lgOuter) {
              lgOuter.style.display = '';
              lgOuter.style.pointerEvents = '';
            }
            
            document.querySelectorAll('.lg-outer, .lg-backdrop, .lg-item').forEach(function(el) {
              el.style.pointerEvents = '';
              el.style.display = '';
            });
            
            customModalActive = false;
            clearInterval(countdownInterval);
          }
          
          lastCountdown = currentCount;
        }
      }, 200);
      
// ✅ 로그아웃은 <a> 태그의 href로 자동 처리됨
console.log('🔧 로그아웃 링크: href로 처리');

// ✅ 연장 버튼은 직접 클릭 감지
setTimeout(function() {
  const logoutBtn = document.getElementById('book-btn-logout');
  const extendBtn = document.getElementById('book-btn-extend');
  
// ✅ 로그아웃 버튼 (수동 로그아웃)
if (logoutBtn) {
    ['click', 'touchend', 'mouseup', 'pointerup'].forEach(function(eventType) {
      logoutBtn.addEventListener(eventType, function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        console.log('✅✅✅ 수동 로그아웃 버튼 클릭됨');
        
        if (logoutBtn.dataset.processing === 'true') return;
        logoutBtn.dataset.processing = 'true';
        
        // ✅ 수동 로그아웃은 mode=logout
        window.location.href = 'login.php?mode=logout';
        
      }, true);
    });
    console.log('🔧 로그아웃 버튼 등록 완료');
}
  
  // ✅ 연장 버튼
  if (extendBtn) {
    ['click', 'touchend', 'mouseup', 'pointerup'].forEach(function(eventType) {
      extendBtn.addEventListener(eventType, function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        console.log('✅✅✅ 연장 버튼 클릭됨 (이벤트:', eventType, ')');
        
        if (extendBtn.dataset.processing === 'true') {
          console.log('⏳ 이미 처리 중...');
          return;
        }
        extendBtn.dataset.processing = 'true';
        
        fetch('init.php?check_session=1&extend=1', {
          method: 'GET',
          cache: 'no-cache'
        })
        .then(response => response.json())
        .then(data => {
          console.log('📡 연장 응답:', data);
          extendBtn.dataset.processing = 'false';
          
          if (data.status === 'active') {
            const origModal = document.getElementById('auto-logout-modal');
            if (origModal) origModal.style.display = 'none';
            console.log('✅ 세션 연장 성공');
            
            // 페이지 새로고침으로 동기화
            setTimeout(function() {
              location.reload();
            }, 300);
          } else {
            window.location.href = 'login.php?mode=timeout';
          }
        })
        .catch(err => {
          console.error('❌ 연장 실패:', err);
          extendBtn.dataset.processing = 'false';
        });
        
      }, true);
    });
    console.log('🔧 연장 버튼 등록 완료');
  }
}, 100);
    }
  });
  
  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['style']
  });
});

document.addEventListener('DOMContentLoaded', function () {
  run_gallery();
  auto_open_gallery();
});

function run_gallery() {
  if ($('#lightgallery').data('lightGallery')) return;

  const $container = $('#lightgallery');
  const total = parseInt($container.data('total'), 10) || 0;
  const file  = $container.data('file');
  const bidx = <?php echo $current_bidx; ?>;
  const pageOrder = '<?php echo $pageorder['page_order'] ?? '0'; ?>';
  
  // ✅ 초기 반전 상태 확인
  const initReversed = document.cookie.indexOf('viewer_reverse_nav=1') !== -1;
  const initNavText = initReversed ? _vi18n.nav_prev_next : _vi18n.nav_next_prev;
  const initBtnText = initReversed ? '🔄' : '◀▶';
  const initBtnBg = initReversed ? 'rgba(0,123,255,0.5)' : 'rgba(255,255,255,0.2)';

  const dynamicEl = [];

  // ★ (2026-10-29) 북모드 페이지 구성 통합
  //   - 0: 1장씩 / 1: 1|2 펼침 / 2: 2|1 펼침(툰모드와 같은 짝: 1-2, 3-4 …, 홀수 마지막 장은 1장) / 3: ▤← / 4: →▤
  //   - 세로분할(3/4): 서버가 분할하지 않는 세로 페이지(가로 < 세로*1.3)는 1장만 → 같은 그림이 좌/우로 2번 나오던 중복 수정
  //     (판정 불가 null이면 기존처럼 좌/우 2장 — 무회귀)
  //   - 슬라이드 ↔ 페이지 매핑(window._mcBook): 위치 저장/복원(#imageN)은 항상 "페이지 번호" 기준(툰모드와 동일)
  // ★ (2026-11-04 22:07) 펼침(1|2·2|1)에서 '표지·가로 그림은 혼자'(쿠키 mcv_solo, 기본 켬)면 같은 크기 정보로 가로 그림을 알아냄(ZIP 은 판정 캐시 그대로 씀)
  <?php $_mcv_solo_on = ($mode == "book" && in_array((string)($pageorder['page_order'] ?? '0'), ['1', '2'], true) && (($_COOKIE['mcv_solo'] ?? '') !== '0')); ?>
  const splitMap = <?php echo ($mode == "book" && (in_array((string)($pageorder['page_order'] ?? '0'), ['3', '4'], true) || $_mcv_solo_on)) ? json_encode(mc_book_split_map($base_file, $type, mc_split_cache_dir($base_file, $user_id ?? ''))) : '[]'; ?>;
  const mcSolo = <?php echo $_mcv_solo_on ? 'true' : 'false'; ?>;
  // 이 책만 '짝 한 쪽 밀기'(이 기기에 책마다 기억 — localStorage 'mcv_off:' + 파일 경로)
  const mcOff = (function () { try { return localStorage.getItem('mcv_off:' + <?php echo json_encode((string)$getfile, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>) === '1'; } catch (e) { return false; } })();
  const activeOrder = ['0', '1', '2', '3', '4'].indexOf(pageOrder) !== -1 ? pageOrder : '0';
  function orderBtns(active) {
    const list = [['0', '-'], ['1', '1|2'], ['2', '2|1'], ['3', '▤←'], ['4', '→▤']];
    return '<span class="lg-pageorder-btns" style="display:inline-flex; gap:2px; margin-left:5px;">' +
      list.map(function (o) {
        return '<button type="button" class="lg-pageorder-btn" data-order="' + o[0] + '" style="font-size:12px; padding:2px 6px; background:' +
          (o[0] === active ? 'rgba(0,123,255,0.5)' : 'rgba(255,255,255,0.2)') +
          '; border:none; border-radius:3px; color:#fff; cursor:pointer;">' + o[1] + '</button>';
      }).join('') + '</span>';
  }

  const imgBase = `viewer.php?file=${file}&bidx=${bidx}&img=`;
  // ★ (2026-11-04 18:00) '세로 화면은 한 쪽'(보기 메뉴, 이 기기 쿠키 mcv_port1=1)을 켰고 펼침(1|2·2|1)이면 세로 화면에서만 한 쪽씩 — 고른 보기 방식은 그대로 두고 그리는 것만
  //   (화면을 돌려 세로↔가로가 바뀌면 js/viewer-bar.js 가 보던 쪽 그대로 다시 엶) / 끄면(기본) 지금과 똑같음
  const mcPortSingle = (pageOrder === '1' || pageOrder === '2') && /(?:^|;\s*)mcv_port1=1(?:;|$)/.test(document.cookie) && window.innerHeight > window.innerWidth;
  const views = []; // { src, second, thumb, pages:[페이지 인덱스], detail }
  if (pageOrder === '3' || pageOrder === '4') {
    const firstSide = (pageOrder === '3') ? 'left' : 'right';
    const secondSide = (pageOrder === '3') ? 'right' : 'left';
    const sideLabel = function (s) { return s === 'left' ? _vi18n.page_left : _vi18n.page_right; };
    for (let i = 0; i < total; i++) {
      if (splitMap[i] === 0) {
        // 분할 대상 아님(세로 페이지) → 원본 1장
        views.push({ src: imgBase + i, thumb: imgBase + i, pages: [i], detail: `(${i+1}p)` });
        continue;
      }
      views.push({ src: imgBase + i + '&split=' + firstSide, thumb: imgBase + i, pages: [i], detail: `(${i+1}p ${sideLabel(firstSide)})` });
      views.push({ src: imgBase + i + '&split=' + secondSide, thumb: imgBase + i, pages: [i], detail: `(${i+1}p ${sideLabel(secondSide)})` });
    }
  } else if ((pageOrder === '1' || pageOrder === '2') && !mcPortSingle) {
    // ★ (2026-11-04 22:07) 펜닐 결정(3-2차 A+B): 표지(첫 쪽)는 혼자 · 가로로 넓은 그림(원래 두 쪽짜리)은 혼자 · 이 책만 짝 한 쪽 밀기
    //   둘 다 끄면(mcv_solo=0, 밀기 없음) 예전과 똑같이 1-2, 3-4 …
    const one = function (i) { views.push({ src: imgBase + i, thumb: imgBase + i, pages: [i], detail: `(${i+1}p)` }); };
    const wide = function (i) { return mcSolo && splitMap[i] === 1; };
    let i = 0;
    if (mcSolo && total > 0) { one(0); i = 1; }
    if (mcOff && i < total) { one(i); i++; }
    while (i < total) {
      if (wide(i) || i + 1 >= total || wide(i + 1)) { one(i); i++; continue; }
      const L = (pageOrder === '1') ? i : i + 1;
      const R = (pageOrder === '1') ? i + 1 : i;
      views.push({ src: imgBase + L, second: imgBase + R, thumb: imgBase + i, pages: [i, i + 1], detail: `(${L+1}|${R+1}p)` });
      i += 2;
    }
  } else {
    for (let i = 0; i < total; i++) {
      views.push({ src: imgBase + i, thumb: imgBase + i, pages: [i], detail: '' });
    }
  }

  const nViews = views.length;
  const slidePage = [];
  const pageSlide = [];
  views.forEach(function (v, k) {
    slidePage.push(v.pages[0]);
    v.pages.forEach(function (p) { if (pageSlide[p] === undefined) pageSlide[p] = k; });
    const el = {
      src: v.src,
      thumb: v.thumb,
      subHtml: `<div style="display:inline-flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center;">
          <span class="lg-counter">${k+1}&nbsp;/&nbsp;${nViews}</span>${v.detail ? `
          <span style="opacity:0.5; font-size:0.85em;">${v.detail}</span>` : ''}
          <span class="lg-nav-hint" style="opacity: 0.7; font-size: 0.95em;">${initNavText}</span>
          <button type="button" class="lg-reverse-btn" style="font-size:14px; padding:4px 10px; background:${initBtnBg}; border:none; border-radius:4px; color:#fff; cursor:pointer;">${initBtnText}</button>
          ${orderBtns(activeOrder)}
        </div>`
    };
    if (v.second) el.mcSecond = v.second; // 펼침(1|2, 2|1)의 두 번째 이미지 (lightGallery는 무시하는 사용자 속성)
    dynamicEl.push(el);
  });
  // ★ (2026-11-02 21:22) 마지막 슬라이드의 마지막 쪽 — 펼침(1|2·2|1)은 슬라이드 첫 쪽만 저장돼 짝수 쪽 책을 끝까지 넘겨도 '다 읽음'이 안 됐음
  const lastViewPage = nViews ? Math.max.apply(null, views[nViews - 1].pages) : 0;
  window._mcBook = { slidePage: slidePage, pageSlide: pageSlide, order: pageOrder, lastPage: lastViewPage, portSingle: mcPortSingle };

  // ★ (2026-10-29) 시작 위치: #imageN(페이지 번호) → 현재 구성의 슬라이드 번호
  //   (동적 갤러리는 초기화 즉시 열려 auto_open_gallery()의 이동이 실행되지 않던 문제 → index 옵션으로 직접 지정)
  let startIndex = 0;
  const _hm = (window.location.hash || '').match(/image(\d+)/i);
  if (_hm && pageSlide.length) {
    const _pg = parseInt(_hm[1], 10) || 0;
    if (pageSlide[_pg] !== undefined) startIndex = pageSlide[_pg];
    else if (_pg >= pageSlide.length) startIndex = pageSlide[pageSlide.length - 1];
  }
  if (!(startIndex >= 0 && startIndex < dynamicEl.length)) startIndex = 0;

  // ★ (2026-10-29) 펼침(1|2, 2|1): 슬라이드 생성 직후 두 번째 이미지를 같은 슬라이드에 나란히 추가
  //   (첫 이미지는 lightGallery 기본 .lg-object 그대로 → 로드/프리로드/카운터 등 기존 흐름 유지)
  $('#lightgallery').off('onAferAppendSlide.lg.mcspread').on('onAferAppendSlide.lg.mcspread', function (e, index) {
    const it = dynamicEl[index];
    if (!it || !it.mcSecond) return;
    const inst = $('#lightgallery').data('lightGallery');
    const $slide = (inst && inst.$slide) ? inst.$slide.eq(index) : $('.lg-outer .lg-item').eq(index);
    const $wrap = $slide.find('.lg-img-wrap').first();
    if (!$wrap.length || $wrap.hasClass('mc-spread')) return;
    $wrap.addClass('mc-spread');
    const img2 = document.createElement('img');
    img2.className = 'lg-image mc-spread-2nd';
    img2.alt = '';
    img2.src = it.mcSecond;
    $wrap.append(img2); // 밝기(change_bright)는 .lg-image 클래스로 첫 이미지와 동일하게 적용됨
  });

  $('#lightgallery').lightGallery({
    dynamic: true,
    dynamicEl: dynamicEl,
    index: startIndex,
    loop: false,
    hideBarsDelay: 1000,
    controls: true,
    preload: 5,
    download: false,
    useLeft: true,
    keyPress: false,
  closable: false,
  escKey: false,
  enableSwipe: false,
  enableDrag: false,
  speed: 0,
  cssEasing: 'linear',
  selector: '.item',
  zoom: true,
  plugins: ['lgZoom']
  });
}

function auto_open_gallery() {
  var hash = window.location.hash || '';
  var m = hash.match(/image(\d+)/i);
  var targetIndex = m ? (parseInt(m[1], 10) || 0) : 0;
  // ★ (2026-10-29) #imageN은 "페이지 번호" → 현재 구성(분할/펼침)의 슬라이드 번호로 변환
  //   (0 기본 모드는 페이지 = 슬라이드라 값 그대로)
  if (window._mcBook && window._mcBook.pageSlide && window._mcBook.pageSlide.length) {
    var _ps = window._mcBook.pageSlide;
    if (_ps[targetIndex] !== undefined) targetIndex = _ps[targetIndex];
    else if (targetIndex >= _ps.length) targetIndex = _ps[_ps.length - 1];
  }

  setTimeout(function () {
    if (document.querySelector('.lg-outer.lg-visible')) return;

    var $el = $('#lightgallery');
    var inst = $el.data('lightGallery');
    if (!inst) return;

    $el.trigger('click');

    $(document).one('onAfterOpen.lg', function () {
      var again = $el.data('lightGallery');
      if (again && typeof again.slide === 'function') {
        var total = (again.$items && again.$items.length) ? again.$items.length : 0;
        if (targetIndex < 0) targetIndex = 0;
        if (total && targetIndex >= total) targetIndex = total - 1;

        again.slide(targetIndex);
      }
    });
  }, 80);
}

// ✅ 좌우반전 기능 (쿠키 기반) - 전역 변수
var isReversedBook = document.cookie.indexOf('viewer_reverse_nav=1') !== -1;

// aria-label 업데이트 함수 - 전역 함수
function updateNavLabels() {
  if (isReversedBook) {
    $('.lg-prev').attr({'aria-label': _vi18n.prev, 'title': _vi18n.prev});
    $('.lg-next').attr({'aria-label': _vi18n.next, 'title': _vi18n.next});
  } else {
    $('.lg-prev').attr({'aria-label': _vi18n.next, 'title': _vi18n.next});
    $('.lg-next').attr({'aria-label': _vi18n.prev, 'title': _vi18n.prev});
  }
}

// ✅ 하단 안내 텍스트 및 버튼 상태 업데이트 함수 - 전역 함수
// ✅ 업데이트 중 플래그 (무한 루프 방지)
var isUpdatingSubHtml = false;

function updateSubHtmlAndButton() {
  if (isUpdatingSubHtml) return;
  isUpdatingSubHtml = true;
  
  // 안내 텍스트 업데이트 (document 전체에서 검색)
  var hints = document.querySelectorAll('.lg-nav-hint');
  var newHtml = isReversedBook ? _vi18n.nav_prev_next : _vi18n.nav_next_prev;
  hints.forEach(function(span) {
    span.innerHTML = newHtml;
  });
  
  // 버튼 상태 업데이트
  var btns = document.querySelectorAll('.lg-reverse-btn');
  btns.forEach(function(btn) {
    btn.textContent = isReversedBook ? '🔄' : '◀▶';
    btn.style.background = isReversedBook ? 'rgba(0,123,255,0.5)' : 'rgba(255,255,255,0.2)';
  });
  
  setTimeout(function() { isUpdatingSubHtml = false; }, 100);
}

$(document).on('onAfterOpen.lg', function () {
  var outer = document.querySelector('.lg-outer');
  if (!outer) return;

  // 초기 상태 업데이트
  setTimeout(updateSubHtmlAndButton, 100);
  
  // ✅ MutationObserver로 subHtml 영역 변경 감지
  var subHtmlArea = outer.querySelector('.lg-sub-html');
  if (subHtmlArea && !subHtmlArea.__reverseObserver) {
    var observer = new MutationObserver(function(mutations) {
      // subHtml이 변경되면 잠시 후 업데이트
      setTimeout(updateSubHtmlAndButton, 10);
    });
    observer.observe(subHtmlArea, { childList: true, subtree: true, characterData: true });
    subHtmlArea.__reverseObserver = observer;
  }
  
  // 버튼 클릭 이벤트 위임 (subHtml 내 버튼)
  $(document).off('click.lgReverse').on('click.lgReverse', '.lg-reverse-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    isReversedBook = !isReversedBook;
    document.cookie = 'viewer_reverse_nav=' + (isReversedBook ? '1' : '0') + '; path=/; max-age=31536000';
    updateNavLabels();
    updateSubHtmlAndButton();
    
    // ✅ lightGallery dynamicEl 업데이트 (슬라이드 변경 시에도 올바른 값 표시)
    var inst = $('#lightgallery').data('lightGallery');
    if (inst && inst.s && inst.s.dynamicEl) {
      var newNavText = isReversedBook ? _vi18n.nav_prev_next : _vi18n.nav_next_prev;
      var newBtnText = isReversedBook ? '🔄' : '◀▶';
      var newBtnBg = isReversedBook ? 'rgba(0,123,255,0.5)' : 'rgba(255,255,255,0.2)';
      var slideCount = inst.s.dynamicEl.length;
      
      for (var i = 0; i < slideCount; i++) {
        var oldHtml = inst.s.dynamicEl[i].subHtml;
        // nav-hint와 버튼만 업데이트 (기존 구조 유지)
        var newHtml = oldHtml
          .replace(/<span class="lg-nav-hint"[^>]*>.*?<\/span>/, 
            '<span class="lg-nav-hint" style="opacity: 0.7; font-size: 0.95em;">' + newNavText + '</span>')
          .replace(/<button[^>]*class="lg-reverse-btn"[^>]*>.*?<\/button>/, 
            '<button type="button" class="lg-reverse-btn" style="font-size:14px; padding:4px 10px; background:' + newBtnBg + '; border:none; border-radius:4px; color:#fff; cursor:pointer;">' + newBtnText + '</button>');
        inst.s.dynamicEl[i].subHtml = newHtml;
      }
    }
    
    // 체크박스도 동기화
    var chk = document.getElementById('chkReverseNav');
    if (chk) chk.checked = isReversedBook;
  });
  
  // ✅ 페이지오더 버튼 클릭 이벤트
  $(document).off('click.lgPageorder').on('click.lgPageorder', '.lg-pageorder-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    var newOrder = $(this).data('order');
    var file = $('#lightgallery').data('file');
    var bidx = <?php echo $current_bidx; ?>;
    // 페이지오더 변경하여 페이지 새로고침
    // ★ (2026-10-29) 지금 보던 페이지 유지: img_counter(페이지 번호)를 #imageN으로 넘김 → 새 구성에서 해당 페이지 슬라이드로 열림
    var _keepPage = (typeof img_counter === 'number' && img_counter >= 0) ? Math.floor(img_counter) : 0;
    location.replace('./viewer.php?<?php echo ($_param_filetype === 'images' || $type === 'images') ? 'filetype=images&' : ''; ?>mode=book&file=' + file + '&bidx=' + bidx + '&pageorder=' + newOrder + '#image' + _keepPage); // ★ (2026-10-30) 이미지 폴더는 filetype=images 를 유지해야 순서 버튼이 먹음
  });

  if (outer.__lgTapBound) return;
  outer.__lgTapBound = true;

  function getLgInstance() {
    var inst = $('#lightgallery').data('lightGallery');
    if (inst) return inst;
    var el = document.getElementById('lightgallery');
    if (window.lgData && el && el.getAttribute('lg-uid')) {
      return window.lgData[el.getAttribute('lg-uid')];
    }
    return null;
  }

  function goNext(){
    var inst = getLgInstance();
    if (inst && typeof inst.goToNextSlide === 'function') inst.goToNextSlide();
    else if (inst && typeof inst.slide === 'function')     inst.slide((inst.index||0) + 1);
    else $('.lg-next').trigger('click');
  }
  function goPrev(){
    var inst = getLgInstance();
    if (inst && typeof inst.goToPrevSlide === 'function') inst.goToPrevSlide();
    else if (inst && typeof inst.slide === 'function')     inst.slide((inst.index||0) - 1);
    else $('.lg-prev').trigger('click');
  }

  // 좌우반전 체크박스 이벤트
  var chkReverseNavBook = document.getElementById('chkReverseNav');
  if (chkReverseNavBook) {
    chkReverseNavBook.addEventListener('change', function(e) {
      isReversedBook = e.target.checked;
      document.cookie = 'viewer_reverse_nav=' + (isReversedBook ? '1' : '0') + '; path=/; max-age=31536000';
      updateNavLabels();  // ✅ aria-label 즉시 업데이트
    });
  }
  
  // 좌/우 클릭 동작 함수 (좌우반전 적용)
  function goLeft() {
    // 기본: 왼쪽 = 다음 (만화 스타일)
    // 반전: 왼쪽 = 이전 (일반 스타일)
    if (isReversedBook) {
      goPrev();
    } else {
      goNext();
    }
  }
  
  function goRight() {
    // 기본: 오른쪽 = 이전 (만화 스타일)
    // 반전: 오른쪽 = 다음 (일반 스타일)
    if (isReversedBook) {
      goNext();
    } else {
      goPrev();
    }
  }

(function () {
  var lastDownAt = 0, lastX = 0, lastY = 0, armed = false;

  function inGallery() {
    // ★ (2026-11-04 18:00) 화면 맞춤 너비·높이(밀어서 보는 상태)에서는 쪽 넘김을 '짧게 누르고 뗐을 때(click)'만 → 여기서 막지 않음(아래 tapHandler 가 click 으로 처리)
    if (document.body && /\bmcv-fit-[wh]\b/.test(document.body.className)) return false;
    return !!document.querySelector('.lg-outer.lg-visible');
  }

  function isUiControl(el) {
    return !!(el && el.closest && el.closest(
      '.lg-close, .lg-toolbar, .lg-actions, .lg-prev, .lg-next, .lg-thumb-outer, .lg-sub-html, .mcv-bar, .mcv-pop' // ★ (2026-11-01) 위 막대·메뉴
    ));
  }

  document.addEventListener('pointerdown', function (e) {
    if (!inGallery()) return;
    if (isUiControl(e.target)) { armed = false; return; }

    lastDownAt = Date.now();
    lastX = e.clientX || 0;
    lastY = e.clientY || 0;
    armed = true;
  }, true);

  document.addEventListener('touchstart', function (e) {
    if (!inGallery()) return;
    if (isUiControl(e.target)) { armed = false; return; }

    var t = e.touches && e.touches[0];
    lastDownAt = Date.now();
    lastX = t ? t.clientX : 0;
    lastY = t ? t.clientY : 0;
    armed = true;
  }, true);

document.addEventListener('touchend', function (e) {
    if (!inGallery()) return;

    if (isUiControl(e.target)) { armed = false; return; }

    var t = e.changedTouches && e.changedTouches[0];
    if (!t) return;

    var dt = Date.now() - lastDownAt;
    var dx = (t.clientX || 0) - lastX;
    var dy = (t.clientY || 0) - lastY;
    var dist = Math.sqrt(dx * dx + dy * dy);

    if (dt < 450 && dist < 25) {
      e.stopImmediatePropagation();
      e.stopPropagation();
      e.preventDefault();
      armed = false;
      return;
    }
    armed = false;
  }, true);

  document.addEventListener('click', function (e) {
    if (!inGallery()) return;

    if (isUiControl(e.target)) { armed = false; return; }

    if (!armed) return;

    var dt = Date.now() - lastDownAt;
    var dx = (e.clientX || 0) - lastX;
    var dy = (e.clientY || 0) - lastY;
    var dist = Math.sqrt(dx*dx + dy*dy);

    if (dt < 450 && dist < 25) {
      e.stopImmediatePropagation();
      e.stopPropagation();
      e.preventDefault();
      armed = false;
      return;
    }
    armed = false;
  }, true);

  $(document).one('onBeforeClose.lg onCloseAfter.lg', function () {
    armed = false;
  });
})();

  function isUiControl(el) {
    return !!(el.closest && el.closest('.lg-toolbar, .lg-actions, .lg-close, .lg-prev, .lg-next, .lg-thumb-outer, .lg-sub-html'));
  }

function arrowCaptureHandler(ev) {
  var target = ev.target;
  if (!target || !target.closest) return;

  if (target.closest('.lg-prev')) {
    ev.preventDefault();
    ev.stopPropagation();
    goLeft();  // ✅ 좌우반전 적용
    return;
  }

  if (target.closest('.lg-next')) {
    ev.preventDefault();
    ev.stopPropagation();
    goRight();  // ✅ 좌우반전 적용
    return;
  }
}

document.addEventListener('click', arrowCaptureHandler, true);
document.addEventListener('pointerdown', arrowCaptureHandler, true);
document.addEventListener('touchstart', arrowCaptureHandler, { passive: false, capture: true });

$(document).one('onBeforeClose.lg onCloseAfter.lg', function () {
  document.removeEventListener('click', arrowCaptureHandler, true);
  document.removeEventListener('pointerdown', arrowCaptureHandler, true);
  document.removeEventListener('touchstart', arrowCaptureHandler, true);
});

  // ✅ 좌우반전 상태에 따라 aria-label 설정
  updateNavLabels();

function keyCaptureHandler(e) {
  var outer = document.querySelector('.lg-outer.lg-visible');
  if (!outer) return;
  var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
  // ★ (2026-11-02 19:06) 위 막대 단추(보기·톱니·쪽 번호)는 제외하지 않음 — 누르거나 Esc 로 메뉴를 닫으면 초점이 단추에 남아 ←→·PageUp/Down 이 안 먹었음
  //   (여기서 처리하는 키는 방향·Page·Home/End 뿐이라 단추 동작(Space·Enter)과 안 겹침)
  if (tag === 'input' || tag === 'textarea' || tag === 'select' || (tag === 'button' && !(e.target.closest && e.target.closest('.mcv-bar'))) || e.isComposing) return;

  var code = e.key || e.code || '';
  var kc = e.keyCode || 0;

  if (code === 'ArrowLeft' || kc === 37) {
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    goLeft();  // ✅ 좌우반전 적용
  } else if (code === 'ArrowRight' || kc === 39) {
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    goRight();  // ✅ 좌우반전 적용
  } else if (code === 'PageDown' || kc === 34) {
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    goNext();  // ✅ PageDown = 항상 다음 (좌우반전 무관)
  } else if (code === 'PageUp' || kc === 33) {
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    goPrev();  // ✅ PageUp = 항상 이전 (좌우반전 무관)
  } else if (code === 'Home' || kc === 36) {
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    var inst = getLgInstance();
    if (inst && typeof inst.slide === 'function') inst.slide(0);
  } else if (code === 'End' || kc === 35) {
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    var inst = getLgInstance();
    if (inst && typeof inst.slide === 'function' && inst.s && inst.s.dynamicEl) {
      inst.slide(inst.s.dynamicEl.length - 1);
    }
  }
}
document.addEventListener('keydown', keyCaptureHandler, true);

$(document).one('onBeforeClose.lg onCloseAfter.lg', function () {
  document.removeEventListener('keydown', keyCaptureHandler, true);
});

  function isUiControl(el) {
    return !!(el.closest &&
      el.closest('.lg-toolbar, .lg-actions, .lg-close, .lg-prev, .lg-next, .lg-thumb-outer, .lg-sub-html'));
  }

function getActiveRect() {
  const slide = document.querySelector('.lg-item.lg-current') || document.querySelector('.lg-current');
  if (!slide) return null;

  const el =
    slide.querySelector('img.lg-object') ||
    slide.querySelector('.lg-img-wrap img') ||
    slide.querySelector('video.lg-video-object, video') ||
    slide.querySelector('.lg-img-wrap') ||
    slide.querySelector('.lg-content') ||
    slide;

  const rect = el?.getBoundingClientRect?.();
  if (!rect || rect.width === 0 || rect.height === 0) return null;
  return rect;
}

function getContainerRect() {
  const cont =
    document.querySelector('.lg-outer.lg-visible .lg-inner') ||
    document.querySelector('.lg-outer.lg-visible .lg-content') ||
    document.querySelector('.lg-outer.lg-visible') ||
    document.body;
  const r = cont.getBoundingClientRect?.();
  return r && r.width > 0 && r.height > 0
    ? r
    : { left: 0, top: 0, width: window.innerWidth, height: window.innerHeight, right: window.innerWidth, bottom: window.innerHeight };
}

function getActiveImage() {
  const slide = document.querySelector('.lg-item.lg-current') || document.querySelector('.lg-current');
  if (!slide) return null;
  const img =
    slide.querySelector('img.lg-object, .lg-img-wrap img, img') ||
    null;
  return img || null;
}

function computeDisplayedBox(containerRect, imgNaturalW, imgNaturalH) {
  const cw = containerRect.width;
  const ch = containerRect.height;
  const arImg = imgNaturalW / Math.max(1, imgNaturalH);
  const arCon = cw / Math.max(1, ch);

  let dispW, dispH;
  if (arImg > arCon) {
    dispW = cw;
    dispH = cw / arImg;
  } else {
    dispH = ch;
    dispW = ch * arImg;
  }

  const leftMargin  = (cw - dispW) / 2;
  const rightMargin = leftMargin;
  const topMargin   = (ch - dispH) / 2;
  const bottomMargin= topMargin;

  return { dispW, dispH, leftMargin, rightMargin, topMargin, bottomMargin };
}

function tapHandler(ev) {
  if (ev.target?.closest?.('.lg-toolbar, .lg-actions, .lg-close, .lg-prev, .lg-next, .lg-thumb-outer, .lg-sub-html')) return;

  if (ev.changedTouches && ev.changedTouches.length > 1) return;

  // ★ (2026-11-04 18:00) 화면 맞춤 너비·높이: 그림을 밀어(스크롤) 보므로 누르는 순간(touchstart·pointerdown)에 넘기지 않고,
  //   밀지 않고 짧게 눌렀을 때 오는 click 에서만 판단 · 좌우 끝 20% 는 그림이 아니라 화면 폭 기준(그림이 화면보다 커서)
  const fitScroll = !!(document.body && /\bmcv-fit-[wh]\b/.test(document.body.className));
  if (fitScroll && ev.type !== 'click') return;

  const t = ev.changedTouches?.[0] || ev;
  const x = t?.clientX ?? 0;

  if (ev.type === 'click' || ev.type === 'touchend') {
    ev.preventDefault();
    ev.stopPropagation();
  }

  if (fitScroll) {
    const cr = getContainerRect();
    if (x < cr.left + cr.width * 0.20) return goLeft();
    if (x > cr.left + cr.width * 0.80) return goRight();
    return showToolbar();
  }

  // ★ (2026-10-29) 펼침(두 장) 슬라이드: 두 이미지 전체 영역 기준으로 좌/우 20% 탭 판정
  const spreadWrap = document.querySelector('.lg-item.lg-current .lg-img-wrap.mc-spread');
  if (spreadWrap) {
    let sl = Infinity, sr = -Infinity;
    spreadWrap.querySelectorAll('img').forEach(function (m) {
      const b = m.getBoundingClientRect();
      if (b.width > 0 && b.height > 0) { sl = Math.min(sl, b.left); sr = Math.max(sr, b.right); }
    });
    if (sr > sl) {
      const sw2 = sr - sl;
      if (x < sl + sw2 * 0.20) return goLeft();  // ✅ 좌우반전 적용
      if (x > sr - sw2 * 0.20) return goRight(); // ✅ 좌우반전 적용
      return showToolbar();
    }
  }

  const contRect = getContainerRect();
  const imgEl = getActiveImage();

  const nW = imgEl?.naturalWidth;
  const nH = imgEl?.naturalHeight;

  if (!nW || !nH) {
    const EDGE = 0.20, sw = window.innerWidth;
    if (x < sw * EDGE) return goLeft();  // ✅ 좌우반전 적용
    if (x > sw * (1 - EDGE)) return goRight();  // ✅ 좌우반전 적용
    return showToolbar();
  }

  const box = computeDisplayedBox(contRect, nW, nH);

  const imgLeft  = contRect.left + box.leftMargin;
  const imgRight = contRect.left + contRect.width - box.rightMargin;

  const leftEdge  = imgLeft  + box.dispW * 0.20;
  const rightEdge = imgRight - box.dispW * 0.20;

  if (x < leftEdge)  return goLeft();  // ✅ 좌우반전 적용
  if (x > rightEdge) return goRight();  // ✅ 좌우반전 적용
  return showToolbar();

  function showToolbar() {
    const outer = document.querySelector('.lg-outer.lg-visible');
    if (!outer) return;
    outer.classList.remove('lg-hide-items');
    outer.classList.add('lg-force-show');
    clearTimeout(window.__lgHideTimer);
    window.__lgHideTimer = setTimeout(() => {
      if (document.querySelector('.lg-outer.lg-visible')) {
        outer.classList.add('lg-hide-items');
        outer.classList.remove('lg-force-show');
      }
    }, 2500);
  }
}

  outer.addEventListener('click', tapHandler, true);
  outer.addEventListener('pointerdown', tapHandler, true);
  outer.addEventListener('touchstart', tapHandler, {passive:false, capture:true});

  $(document).one('onBeforeClose.lg onCloseAfter.lg', function(){
    outer.removeEventListener('click', tapHandler, true);
    outer.removeEventListener('pointerdown', tapHandler, true);
    outer.removeEventListener('touchstart', tapHandler, true);
    outer.__lgTapBound = false;
    $('.lg-prev').off('.rev');
    $('.lg-next').off('.rev');
    $(document).off('click.lgReverse');  // ✅ 버튼 클릭 이벤트 제거
    // ✅ MutationObserver 해제
    var subHtmlArea = outer.querySelector('.lg-sub-html');
    if (subHtmlArea && subHtmlArea.__reverseObserver) {
      subHtmlArea.__reverseObserver.disconnect();
      subHtmlArea.__reverseObserver = null;
    }
  });
});

$(document).on('onCloseAfter.lg', function () {
  var $el = $('#lightgallery');

  var inst = $el.data('lightGallery');
  // ★ (2026-10-30) 아래(위치 저장) 핸들러가 이 다음에 실행되는데 인스턴스를 먼저 지워 늘 image0 이 저장되던 문제 — 지우기 전에 위치 보관
  if (inst && typeof inst.index === 'number') window.__mcLgCloseIdx = inst.index;
  if (inst && typeof inst.destroy === 'function') {
    inst.destroy(true);
  }
  $el.removeData('lightGallery');
  $('.lg-outer, .lg-backdrop').remove();

  var $input = $('#rungallery');
  var $label = $input.closest('label');
  $input.prop('checked', false).prop('disabled', false);
  $label.removeClass('active btn-secondary').addClass('btn-outline-secondary');
  $label.attr('aria-pressed', 'false');

  setTimeout(function () {
    $label.removeClass('disabled');
  }, 0);

  var safeFile = <?php echo js(encode_url($getfile)); ?>;
  location.replace("./viewer.php?<?php echo ($_param_filetype === 'images' || $type === 'images') ? 'filetype=images&' : ''; ?>mode=toon&file=" + safeFile + "&bidx=<?php echo $current_bidx; ?>");
});

// 보던 위치(toon URL#bookmark)를 히스토리에 반영.
// ℹ️ (제거됨) 백그라운드 전환(visibilitychange→hidden) 시 URL을 toonUrl로 replaceState 하던 코드 제거.
//    iOS가 한참 뒤 탭을 폐기(discard)하고 복귀할 때 그 바뀐 URL(#bookmark)을 다시 로드하면서
//    하얀 화면 + 목록으로 가려면 뒤로가기 2번이 필요한 부작용이 있었음.
//    (v2.4의 beforeunload 방식은 폐기 시 잘 실행되지 않아 원래 화면이 그대로 보였음.)
//    복귀 위치 복원은 자동저장(sendBeacon) + restoreToonPosition이 담당하므로 이 URL 바꿔치기는 불필요.

$(document).one('onCloseAfter.lg', function () {
  var idx = 0;
  var inst = $('#lightgallery').data('lightGallery');
  if (inst && typeof inst.index === 'number') idx = inst.index;
  else if (typeof window.__mcLgCloseIdx === 'number') idx = window.__mcLgCloseIdx;
  var _sp = window._mcBook && window._mcBook.slidePage; // 세로 분할이면 슬라이드 번호 → 페이지 번호
  var _last = _sp && idx === _sp.length - 1 && typeof window._mcBook.lastPage === 'number'; // ★ (2026-11-02 21:22) 마지막 슬라이드면 그 마지막 쪽(위 onAfterSlide 와 같게)
  if (_sp && _sp[idx] !== undefined) idx = _sp[idx];
  if (_last) idx = window._mcBook.lastPage;
  var bookmark = 'image' + idx;

  var safeFile = <?php echo js(encode_url($getfile)); ?>;
  const user_id = <?php echo js($user_id); ?>;  // ✅ 세션 닫기 전에 저장한 변수 사용

  const qs = "viewer=toon"
    + "&page_order=<?php echo (int)($pageorder['page_order'] ?? 0); ?>" // ★ (2026-11-05 15:40) 이 책의 보기 방식도 그대로 기록(빠져 있어 닫을 때마다 '한 쪽'으로 초기화됐음 — 모드 기억 '책마다')
    + "&file=" + safeFile
    + "&bookmark=" + encodeURIComponent(bookmark)
    + "&user_id=" + encodeURIComponent(user_id)
    + "&bidx=<?php echo $current_bidx; ?>";

  function send(url) {
    if (navigator.sendBeacon) {
      try { navigator.sendBeacon(url); return; } catch(e){}
    }
    $.get(url);
  }

  // ★ (2026-11-03 17:20) 직접 찍은 북마크(책갈피)는 건드리지 않음 — 종전엔 북모드를 닫을 때마다 북마크도 보던 쪽으로 덮어써 따로 기억해 둔 쪽이 사라졌고,
  //   안 찍은 책도 북마크 목록에 저절로 생겼음(펜닐 제보). 이어 읽기는 자동 저장으로(툰모드와 같게)
  try { window.mcAsViewer = 'toon'; } catch(e){} // ★ (2026-11-05 15:40) 이어서 이 화면이 떠날 때의 자동 저장도 툰모드로(예전엔 '북'으로 다시 덮어씀)
  send("bookmark.php?mode=autosave&" + qs);

  const toonUrl = "./viewer.php?<?php echo ($_param_filetype === 'images' || $type === 'images') ? 'filetype=images&' : ''; ?>mode=toon&file=" + safeFile + "&bidx=<?php echo $current_bidx; ?>#" + bookmark;
  setTimeout(function(){ location.replace(toonUrl); }, 80);
});

<?php
}
?>

					function sub_toggle() {
						$('.collapse').fadeToggle();
						document.getElementById("info").value = "";
					};
<?php
if($type != "pdf") {
?>
function set_cover() {
	document.getElementById("info").value = _vi18n.setting;
	var safeFile = <?php echo js(encode_url($getfile)); ?>;
	// ★ (2026-10-30) bookmark.php 가 set_cover 에 삭제 토큰을 요구하는데 안 붙여서 늘 403 이던 문제
	$.get("bookmark.php?mode=set_cover&file=" + safeFile + "&bidx=<?php echo $current_bidx; ?>&token=<?php echo urlencode($_viewer_delete_token); ?>", function( data ) {
		document.getElementById("info").value = data;
	}).fail(function (x) { document.getElementById("info").value = (x && x.responseText) ? String(x.responseText).slice(0, 80) : '✕'; }); // ★ (2026-11-04 09:43) 실패(쓸 그림 없음·권한)도 알림
}
<?php if ($_viewer_is_admin): ?>
/* ★ (2026-11-08 fix80, 펜닐 요청) 보고 있는 장면(동영상)·쪽(PDF)을 이 폴더의 표지로 — 화면 그림을 JPEG 로 보내 bookmark.php?mode=set_cover_img 가 [cover].jpg 로 저장(관리자·CSRF) */
function mcCoverPost(dataUrl, done) {
	try {
		var fd = new FormData();
		fd.append('mode', 'set_cover_img'); fd.append('file', <?php echo js((string)$getfile); ?>);
		fd.append('csrf_token', <?php echo js((string)$_viewer_csrf); ?>); fd.append('image', dataUrl);
		fetch('bookmark.php?bidx=<?php echo (int)$current_bidx; ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.text().then(function (t) { done(r.ok ? t : String(t || '✕').slice(0, 80), r.ok); }); })
			.catch(function () { done('✕', false); });
	} catch (e) { done('✕', false); }
}
function mcCoverFromVideo() {
	var inf = document.getElementById('info'), v = document.getElementById('videoPlayer');
	if (inf) inf.value = _vi18n.setting;
	// 장면이 아직 없으면(재생 전·변환 중) 예전 방식 — 서버가 영상 썸네일·같은 이름 그림으로
	if (!v || v.readyState < 2 || !v.videoWidth) { if (typeof set_cover === 'function') set_cover(); return; }
	try {
		var sc = Math.min(1, 1280 / v.videoWidth), c = document.createElement('canvas');
		c.width = Math.max(1, Math.round(v.videoWidth * sc)); c.height = Math.max(1, Math.round(v.videoHeight * sc));
		c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
		mcCoverPost(c.toDataURL('image/jpeg', 0.88), function (t) { if (inf) inf.value = t; });
	} catch (e) { if (typeof set_cover === 'function') set_cover(); }
}
<?php endif; ?>
<?php
}
?>
</script>
<?php
// ★ (2026-11-01) 위 막대(시안 S2) — 압축 파일·이미지 폴더의 툰모드·북모드만(PDF·동영상은 그대로). 막대 파일이 없으면 예전 화면 그대로
$_mcv_on = ($type === 'zip' || $type === 'images') && ($mode === 'toon' || $mode === 'book') && is_file(__DIR__ . '/viewer_bar.php');
?>
<?php
// ★ (2026-11-04 18:00) 북모드 화면 맞춤(이 기기 쿠키 mcv_fit: 없음=화면(지금 동작) / width=너비 / height=높이) — 그리기 전에 class 로 넣어 깜빡임 없게
$_mcv_fitc = ($_mcv_on && $mode === 'book') ? (($_COOKIE['mcv_fit'] ?? '') === 'width' ? ' mcv-fit-w' : ((($_COOKIE['mcv_fit'] ?? '') === 'height') ? ' mcv-fit-h' : '')) : '';
?>
<?php
// ★ (2026-11-07 fix56) 동영상 화면에도 위 막대(← · 제목 · ⚙) — 막대 파일이 없으면 예전 화면 그대로
$_mcvv_on = !$_mcv_on && ($type === 'video' || $type === 'video_archive') && is_file(__DIR__ . '/viewer_video_bar.php');
?>
<body<?php echo $_mcv_on ? ' class="mcv2 mcv2-' . ($mode === 'book' ? 'book' : 'toon') . $_mcv_fitc . '"' : ($_mcvv_on ? ' class="mcvv"' : ''); ?>>
<?php if ($_mcv_on) include __DIR__ . '/viewer_bar.php'; ?>
<?php if ($_mcvv_on) include __DIR__ . '/viewer_video_bar.php'; ?>

<?php if($type != "pdf"): ?>
<nav class="navbar navbar-light fixed-top bg-white p-1 m-0">
<table class="table table-borderless mb-2 p-0" width=100%>
<tr>
<td class="m-0 p-0 align-middle">
<?php // ★ (2026-11-03 22:32) [로고][로고 문구][버전] 한 줄(FileStation 처럼) ?>
<span style="font-family: 'Gugi'; font-size: 2em; display:inline-flex; align-items:flex-start; vertical-align:middle;"><?php echo mc_brand_logo($_branding, './index.php?dir=' . encode_url($link_dir) . '&page=' . intval($page) . '&bidx=' . $current_bidx, 'white-space:normal;min-width:0;'); /* ★ (2026-11-03 23:34) 긴 문구는 줄바꿈(예전처럼) */ ?><span style="font-size:10px; color:#999; margin:3px 0 0 4px; font-family:system-ui,sans-serif; line-height:1;"><?php echo MYCOMIX_VERSION; ?></span></span><?php render_lang_badge('lg'); ?>
</td>
<td class="m-0 p-0 align-middle" align="right">
<button class="btn btn-sm" onclick="sub_toggle();">
<svg width="2em" height="2em" viewBox="0 0 16 16" class="bi bi-gear" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M8.837 1.626c-.246-.835-1.428-.835-1.674 0l-.094.319A1.873 1.873 0 0 1 4.377 3.06l-.292-.16c-.764-.415-1.6.42-1.184 1.185l.159.292a1.873 1.873 0 0 1-1.115 2.692l-.319.094c-.835.246-.835 1.428 0 1.674l.319.094a1.873 1.873 0 0 1 1.115 2.693l-.16.291c-.415.764.42 1.6 1.185 1.184l.292-.159a1.873 1.873 0 0 1 2.692 1.116l.094.318c.246.835 1.428.835 1.674 0l.094-.319a1.873 1.873 0 0 1 2.693-1.115l.291.16c.764.415 1.6-.42 1.184-1.185l-.159-.291a1.873 1.873 0 0 1 1.116-2.693l.318-.094c.835-.246.835-1.428 0-1.674l-.319-.094a1.873 1.873 0 0 1-1.115-2.692l.16-.292c.415-.764-.42-1.6-1.185-1.184l-.291.159A1.873 1.873 0 0 1 8.93 1.945l-.094-.319zm-2.633-.283c.527-1.79 3.065-1.79 3.592 0l.094.319a.873.873 0 0 0 1.255.52l.292-.16c1.64-.892 3.434.901 2.54 2.541l-.159.292a.873.873 0 0 0 .52 1.255l.319.094c1.79.527 1.79 3.065 0 3.592l-.319.094a.873.873 0 0 0-.52 1.255l.16.292c.893 1.64-.902 3.434-2.541 2.54l-.292-.159a.873.873 0 0 0-1.255.52l-.094.319c-.527 1.79-3.065 1.79-3.592 0l-.094-.319a.873.873 0 0 0-1.255-.52l-.292.16c-1.64.893-3.433-.902-2.54-2.541l.159-.292a.873.873 0 0 0-.52-1.255l-.319-.094c-1.79-.527-1.79-3.065 0-3.592l.319-.094a.873.873 0 0 0 .52-1.255l-.16-.292c-.892-1.64.902-3.433 2.541-2.54l.292.159a.873.873 0 0 0 1.255-.52l.094-.319z"/>
  <path fill-rule="evenodd" d="M8 5.754a2.246 2.246 0 1 0 0 4.492 2.246 2.246 0 0 0 0-4.492zM4.754 8a3.246 3.246 0 1 1 6.492 0 3.246 3.246 0 0 1-6.492 0z"/>
</svg>
</button>
</td></tr>
</table>
<table class="collapse" width="100%">
<tr><td align="right">
	<div class="justify-content-end btn-toolbar" role="toolbar">
		<div>
			<input class="form-control bg-white" align="right" type="text" style="text-align:right;border:none;border-right:0px; border-top:0px; boder-left:0px; boder-bottom:0px;" readonly id="info" value="" size="10"></input>
		</div>
		<div class="btn-group mr-3" role="group">
			<button id="bright-down" class="btn btn-sm text-danger" onclick="bright_down();">
			<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-brightness-low-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
			  <path d="M12 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM8.5 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm0 11a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm5-5a.5.5 0 1 1 0-1 .5.5 0 0 1 0 1zm-11 0a.5.5 0 1 1 0-1 .5.5 0 0 1 0 1zm9.743-4.036a.5.5 0 1 1-.707-.707.5.5 0 0 1 .707.707zm-7.779 7.779a.5.5 0 1 1-.707-.707.5.5 0 0 1 .707.707zm7.072 0a.5.5 0 1 1 .707-.707.5.5 0 0 1-.707.707zM3.757 4.464a.5.5 0 1 1 .707-.707.5.5 0 0 1-.707.707z"/>
			</svg>
			</button>
			<button id="bright" class="btn btn-sm text-danger" onclick="bright();">
			<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-brightness-low" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
			  <path fill-rule="evenodd" d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>
			  <path d="M8.5 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm0 11a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm5-5a.5.5 0 1 1 0-1 .5.5 0 0 1 0 1zm-11 0a.5.5 0 1 1 0-1 .5.5 0 0 1 0 1zm9.743-4.036a.5.5 0 1 1-.707-.707.5.5 0 0 1 .707.707zm-7.779 7.779a.5.5 0 1 1-.707-.707.5.5 0 0 1 .707.707zm7.072 0a.5.5 0 1 1 .707-.707.5.5 0 0 1-.707.707zM3.757 4.464a.5.5 0 1 1 .707-.707.5.5 0 0 1-.707.707z"/>
			</svg>
			</button>
			<button id="bright-up" class="btn btn-sm text-danger" onclick="bright_up();">
			<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-brightness-high" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
			  <path fill-rule="evenodd" d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>
			</svg>
			</button>
		</div>
		<div class="btn-group" role="group">
			<button class="btn btn-sm" onclick="location.replace('#<?php echo h($bookmark ?? '0'); ?>');" id="load" value="<?php echo __h('viewer_save_pos'); ?>">
			<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-bookmark-check-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
			  <path fill-rule="evenodd" d="M4 0a2 2 0 0 0-2 2v13.5a.5.5 0 0 0 .74.439L8 13.069l5.26 2.87A.5.5 0 0 0 14 15.5V2a2 2 0 0 0-2-2H4zm6.854 5.854a.5.5 0 0 0-.708-.708L7.5 7.793 6.354 6.646a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0l3-3z"/>
			</svg>
			</button>
			<button class="btn btn-sm" onclick="save_bookmark();" id="save" value="<?php echo __h('viewer_save_pos'); ?>">
			<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-bookmark-plus" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
			  <path fill-rule="evenodd" d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5V2zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1H4z"/>
			  <path fill-rule="evenodd" d="M8 4a.5.5 0 0 1 .5.5V6H10a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V7H6a.5.5 0 0 1 0-1h1.5V4.5A.5.5 0 0 1 8 4z"/>
			</svg>
			</button>
		</div>
		<?php if($type == "pdf"): ?>
		<div class="btn-group ml-2" role="group">
			<button id="pdf-outline-btn" class="btn btn-sm btn-outline-primary" onclick="toggleOutline();" title="<?php echo __h('epub_toc'); ?>">
				📑 <?php echo __h('epub_toc'); ?>
			</button>
		</div>
		<?php endif; ?>
	</div>
</td></tr>
<?php
if(($type === 'zip' || $type === 'images' || $type === 'video') && $_viewer_is_admin){ // ★ (2026-11-08 fix80) 동영상도(지금 장면 → 폴더 표지)
    // ★ (2026-11-04 07:44) 표지 지정은 관리자만 / ★ (2026-11-04 09:43) 압축·이미지 폴더만(동영상엔 쓸 그림이 없어 '설정됨'만 떴음)
?>
<tr><td align="right">
<br>
<?php if ($type === 'video'): ?>
<button class="btn btn-sm btn-success mt-2 p-0" onclick="mcCoverFromVideo();"><?php echo __h("viewer_cover_frame"); ?></button>
<?php else: ?>
<button class="btn btn-sm btn-success mt-2 p-0" onclick="set_cover();"><?php echo __h("viewer_set_cover"); ?></button>
<?php endif; ?>
</td></tr>
<?php
}
?>
</table>
<?php
function cleanHiddenUnicode(string $text): string {
    // 유니코드 제어 문자 제거
    $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
    // 다시 한번 HTML 인코딩 (이중 인코딩 방지)
    return $text;
}

$title_trimmed = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '', $title);

$title_safe = htmlspecialchars($title_trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$title_safe = preg_replace_callback('/\((\d{6,11})\)/', function($m) {
    $num = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
    $half = intdiv(strlen($num), 2);
    return '(' . substr($num, 0, $half) . '&#8203;' . substr($num, $half) . ')';
}, $title_safe);
?>

<span class="text-wrap-fix">
  <?= cleanHiddenUnicode($title_safe); ?>
</span>

</nav>
<?php endif; // if($type != "pdf") ?>
<div>
<?php if ($type == "video_archive"): ?>
<!-- video_archive용 하단 네비게이션 - 목록 버튼만 -->
<!-- ✅ 모바일 하단 메뉴 아이콘 크기 조정 -->
<style>
@media (max-width: 576px) {
    .viewer-bottom-nav .btn { padding: 0.15rem 0.3rem !important; }
    .viewer-bottom-nav .btn-group { gap: 2px; }
    .viewer-bottom-nav #favBtn span { font-size: 1em !important; }
}
</style>
<nav class="navbar navbar-light fixed-bottom bg-white m-0 p-1 viewer-bottom-nav">
<table width="100%">
<tr><td align="left">
<div class="btn-group justify-content-center" style="font-family: 'Gugi';">
<button type="button" class="btn btn-outline-secondary btn-sm" OnClick="var u=(window.mcBackUrl ? window.mcBackUrl(this.getAttribute('data-def')) : this.getAttribute('data-def')); if (window.mcBackGo) window.mcBackGo(u, 'replace'); else location.replace(u);" data-def="./index.php?dir=<?php echo h(encode_url($link_dir)); ?>&amp;page=<?php echo intval($page) . h($bidx_param); ?>">
<svg width="3em" height="1em" viewBox="0 0 16 16" class="bi bi-list" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M2.5 11.5A.5.5 0 0 1 3 11h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4A.5.5 0 0 1 3 7h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4A.5.5 0 0 1 3 3h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
</svg>
</button>
</div>
</td></tr></table>
</nav>
<?php elseif ($type != "pdf"): ?>
<nav class="navbar navbar-light fixed-bottom bg-white m-0 p-1 viewer-bottom-nav">
<table width="100%">
<tr><td align="left">
<div class="btn-group justify-content-center" style="font-family: 'Gugi';">
<?php
         if ($now == '0' || $pre < 0 || !isset($totalfile[$pre])) {
			 ?>
<button type="button" class="btn btn-outline-light btn-sm mr-1">
<svg width="3em" height="1em" viewBox="0 0 16 16" class="bi bi-skip-backward-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M.5 3.5A.5.5 0 0 0 0 4v8a.5.5 0 0 0 1 0V4a.5.5 0 0 0-.5-.5z"/>
  <path d="M.904 8.697l6.363 3.692c.54.313 1.233-.066 1.233-.697V4.308c0-.63-.692-1.01-1.233-.696L.904 7.304a.802.802 0 0 0 0 1.393z"/>
  <path d="M8.404 8.697l6.363 3.692c.54.313 1.233-.066 1.233-.697V4.308c0-.63-.693-1.01-1.233-.696L8.404 7.304a.802.802 0 0 0 0 1.393z"/>
</svg></button>
			 <?php
         } else {
			 ?>
<button type="button" class="btn btn-outline-secondary btn-sm mr-1" OnClick="location.replace('./viewer.php?file=<?php echo encode_url($link_dir."/".$totalfile[$pre]); ?>&bidx=<?php echo $current_bidx; ?>')">
<svg width="3em" height="1em" viewBox="0 0 16 16" class="bi bi-skip-backward-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M.5 3.5A.5.5 0 0 0 0 4v8a.5.5 0 0 0 1 0V4a.5.5 0 0 0-.5-.5z"/>
  <path d="M.904 8.697l6.363 3.692c.54.313 1.233-.066 1.233-.697V4.308c0-.63-.692-1.01-1.233-.696L.904 7.304a.802.802 0 0 0 0 1.393z"/>
  <path d="M8.404 8.697l6.363 3.692c.54.313 1.233-.066 1.233-.697V4.308c0-.63-.693-1.01-1.233-.696L8.404 7.304a.802.802 0 0 0 0 1.393z"/>
</svg></button>
			 <?php
         }
?>
<button type="button" class="btn btn-outline-secondary btn-sm mr-1" OnClick="var u=(window.mcBackUrl ? window.mcBackUrl(this.getAttribute('data-def')) : this.getAttribute('data-def')); if (window.mcBackGo) window.mcBackGo(u, 'replace'); else location.replace(u);" data-def="./index.php?dir=<?php echo h(encode_url($link_dir)); ?>&amp;page=<?php echo intval($page); ?>&amp;bidx=<?php echo (int)$current_bidx; ?>">
<svg width="3em" height="1em" viewBox="0 0 16 16" class="bi bi-list" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M2.5 11.5A.5.5 0 0 1 3 11h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4A.5.5 0 0 1 3 7h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4A.5.5 0 0 1 3 3h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
</svg>
</button>
<?php 
// ✅ 즐겨찾기 버튼
$_viewer_fav_path = $getfile;
$_viewer_is_fav = mc_rec_find($_favorites_arr, $current_bidx, $_viewer_fav_path) !== null; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 즐겨찾기
?>
<button type="button" id="favBtn" class="btn btn-sm mr-1 <?php echo $_viewer_is_fav ? 'btn-warning' : 'btn-outline-warning'; ?>" 
        onclick="toggleViewerFavorite(this)" title="<?php echo $_viewer_is_fav ? __('viewer_fav_remove') : __('viewer_fav_add'); ?>">
    <span style="font-size:1.5em;"><?php echo $_viewer_is_fav ? '⭐' : '☆'; ?></span>
</button>
<script>
function toggleViewerFavorite(btn) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'bookmark.php?mode=toggle_favorite', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    if (resp.is_favorite) {
                        btn.classList.remove('btn-outline-warning');
                        btn.classList.add('btn-warning');
                        btn.querySelector('span').textContent = '⭐';
                        btn.title = _vi18n.fav_remove;
                    } else {
                        btn.classList.remove('btn-warning');
                        btn.classList.add('btn-outline-warning');
                        btn.querySelector('span').textContent = '☆';
                        btn.title = _vi18n.fav_add;
                    }
                }
            } catch(e) { console.error(e); }
        }
    };
    xhr.send('file=' + encodeURIComponent(<?php echo js($_viewer_fav_path); ?>) + '&bidx=<?php echo $current_bidx; ?>');
}
</script>
<?php
         if (!isset($totalfile[$next]) || count($totalfile) <= $next) {
			 ?>
<button type="button" class="btn btn-outline-light btn-sm">
<svg width="3em" height="1em" viewBox="0 0 16 16" class="bi bi-skip-forward-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M15.5 3.5a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5z"/>
  <path d="M7.596 8.697l-6.363 3.692C.693 12.702 0 12.322 0 11.692V4.308c0-.63.693-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393z"/>
  <path d="M15.096 8.697l-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.693-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393z"/>
</svg></button>
			 <?php
         } else {
			 ?>
<button type="button" class="btn btn-outline-secondary btn-sm" OnClick="location.replace('./viewer.php?file=<?php echo encode_url($link_dir."/".$totalfile[$next]); ?>&bidx=<?php echo $current_bidx; ?>')"> 
<svg width="3em" height="1em" viewBox="0 0 16 16" class="bi bi-skip-forward-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M15.5 3.5a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5z"/>
  <path d="M7.596 8.697l-6.363 3.692C.693 12.702 0 12.322 0 11.692V4.308c0-.63.693-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393z"/>
  <path d="M15.096 8.697l-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.693-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393z"/>
</svg></button>
			 <?php
		 }
         ?>		 
		 </div>
</td><td align="right" style="padding-right:15px;">
<div style="display:inline-flex; align-items:center; gap:5px;">
<?php
if($type != "pdf" && $type != "video"){
$page_order = $pageorder['page_order'] ?? '0';
$filetype_param = ($_param_filetype === 'images' || $type === 'images') ? 'filetype=images&' : '';

if($mode == "toon"){
?>
<div class="btn-group btn-group-toggle" data-toggle="buttons">
<label class="btn btn-outline-secondary btn-sm">
<input type="radio" name="options" id="rungallery"
  OnClick="location.replace('./viewer.php?<?php echo h($filetype_param); ?>mode=book&file=<?php echo encode_url($getfile); ?>&bidx=<?php echo $current_bidx; ?>#<?php echo h($bookmark ?? '0'); ?>')">
<?php  
} elseif($mode == "book") {
?>
<div class="btn-group btn-group-toggle" data-toggle="buttons">
<label class="btn btn-secondary btn-sm">
<input type="radio" name="options" id="rungallery"
  OnClick="location.replace('./viewer.php?<?php echo h($filetype_param); ?>mode=toon&file=<?php echo encode_url($getfile); ?>&bidx=<?php echo $current_bidx; ?>#<?php echo h($bookmark ?? '0'); ?>')">
<?php
}
?>
<svg width="2em" height="1em" viewBox="0 0 16 16" class="bi bi-book" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M1 2.828v9.923c.918-.35 2.107-.692 3.287-.81 1.094-.111 2.278-.039 3.213.492V2.687c-.654-.689-1.782-.886-3.112-.752-1.234.124-2.503.523-3.388.893zm7.5-.141v9.746c.935-.53 2.12-.603 3.213-.493 1.18.12 2.37.461 3.287.811V2.828c-.885-.37-2.154-.769-3.388-.893-1.33-.134-2.458.063-3.112.752zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
</svg>
</label>
</div>
<div class="btn-group btn-group-toggle" data-toggle="buttons" id="pageorder-buttons">
  <label class="btn btn<?= $page_order == "0" ? "" : "-outline" ?>-secondary btn-sm">
    <input type="radio" name="pageorder" id="option1" OnClick="location.replace('./viewer.php?<?php echo h($filetype_param); ?>file=<?php echo encode_url($getfile); ?>&bidx=<?php echo $current_bidx; ?>&pageorder=0')"> - 
  </label>
  <label class="btn btn<?= $page_order == "1" ? "" : "-outline" ?>-secondary btn-sm">
    <input type="radio" name="pageorder" id="option2" OnClick="location.replace('./viewer.php?<?php echo h($filetype_param); ?>file=<?php echo encode_url($getfile); ?>&bidx=<?php echo $current_bidx; ?>&pageorder=1')">1|2
  </label>
  <label class="btn btn<?= $page_order == "2" ? "" : "-outline" ?>-secondary btn-sm">
    <input type="radio" name="pageorder" id="option3" OnClick="location.replace('./viewer.php?<?php echo h($filetype_param); ?>file=<?php echo encode_url($getfile); ?>&bidx=<?php echo $current_bidx; ?>&pageorder=2')">2|1
  </label>
  <label class="btn btn<?= $page_order == "3" ? "" : "-outline" ?>-secondary btn-sm" title="<?php echo __h('viewer_split_lr'); ?>">
    <input type="radio" name="pageorder" id="option4" OnClick="location.replace('./viewer.php?<?php echo h($filetype_param); ?>file=<?php echo encode_url($getfile); ?>&bidx=<?php echo $current_bidx; ?>&pageorder=3')">▤←
  </label>
  <label class="btn btn<?= $page_order == "4" ? "" : "-outline" ?>-secondary btn-sm" title="<?php echo __h('viewer_split_rl'); ?>">
    <input type="radio" name="pageorder" id="option5" OnClick="location.replace('./viewer.php?<?php echo h($filetype_param); ?>file=<?php echo encode_url($getfile); ?>&bidx=<?php echo $current_bidx; ?>&pageorder=4')">→▤
  </label>
</div>
<?php if($mode == "book"): ?>
<label class="btn btn-outline-info btn-sm ml-2" title="<?php echo __h('viewer_reverse'); ?>" style="cursor:pointer;">
  <input type="checkbox" id="chkReverseNav" <?php echo ($_COOKIE['viewer_reverse_nav'] ?? '') === '1' ? 'checked' : ''; ?> style="margin-right:3px;">
  ◀▶
</label>
<?php endif; ?>

<?php
} elseif ($type == "video") {
    // 동영상일 때는 간단한 정보만 표시
    $video_ext = strtoupper(pathinfo($base_file, PATHINFO_EXTENSION));
?>
<span class="badge badge-primary mr-2"><?php echo h($video_ext); ?></span>
<span class="badge badge-info">🎬 <?php echo __h("viewer_video_tab"); ?></span>
<?php
}
?>
</div>  
</td></tr></table>  
</nav>
<!-- ✅ 모바일 하단 메뉴 아이콘 크기 축소 -->
<script>
(function() {
    if (window.innerWidth <= 768) {
        var navSvgs = document.querySelectorAll('.viewer-bottom-nav svg');
        for (var i = 0; i < navSvgs.length; i++) {
            navSvgs[i].setAttribute('width', '2.3em');
            navSvgs[i].setAttribute('height', '0.85em');
        }
        var favBtn = document.getElementById('favBtn');
        if (favBtn) {
            var span = favBtn.querySelector('span');
            if (span) span.style.fontSize = '1.15em';
        }
        // 버튼 패딩 및 여백 조정
        var btns = document.querySelectorAll('.viewer-bottom-nav .btn');
        for (var j = 0; j < btns.length; j++) {
            btns[j].style.padding = '0.18rem 0.35rem';
            btns[j].style.marginRight = '2px';
        }
    }
})();
</script>
<?php endif; ?>
</div>
<?php
if($type == "video"){
    // ★ (2026-10-31) 동영상 플레이어 보강(js/mc-video.js) 번역
    echo '<script>window.MC_VIDEO_T = ' . json_encode([
        'back5' => __('mcv_back5'), 'fwd5' => __('mcv_fwd5'), 'back5_help' => __('mcv_back5_help'), 'fwd5_help' => __('mcv_fwd5_help'),
        'speed' => __('mcv_speed'), 'loop' => __('mcv_loop'), 'loop_on' => __('mcv_loop_on'), 'loop_off' => __('mcv_loop_off'),
        'ab_set_a' => __('mcv_ab_set_a'), 'ab_set_b' => __('mcv_ab_set_b'), 'ab_clear' => __('mcv_ab_clear'), 'ab_off' => __('mcv_ab_off'),
        'autonext' => __('mcv_autonext'), 'autonext_on' => __('mcv_autonext_on'), 'autonext_off' => __('mcv_autonext_off'),
        'list' => __('mcv_list'), 'list_title' => __('mcv_list_title'),
        'resume_q' => __('mcv_resume_q'), 'resume' => __('mcv_resume'), 'resume_no' => __('mcv_resume_no'),
        'center_play' => __('mcfs_center_play'),
        // ★ (2026-10-31) 자동 재생·변환 재생·배지·자막·정보 창
        'resume_from' => __('mcv_resume_from'), 'autoplay_muted' => __('mcv_autoplay_muted'), 'unmute' => __('mcv_unmute'),
        'tc_fallback' => __('mcv_tc_fallback'), 'tc_error' => __('mcv_tc_error'), 'mode_native' => __('mcv_mode_native'), 'mode_tc' => __('mcv_mode_tc'),
        'q_orig' => __('mcv_q_orig'), 'badge_native' => __('mcv_badge_native'), 'badge_tc' => __('mcv_badge_tc'),
        'sub_file' => __('mcv_sub_file'), 'sub_on' => __('mcv_sub_on'), 'sub_off' => __('mcv_sub_off'),
        'info_title' => __('mcv_info_title'), 'info_mode' => __('mcv_info_mode'), 'close' => __('mcv_close'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' . "\n";
    // ★ (2026-10-31) FileStation 동영상 플레이어(js/fs-video-skin.js·fs-eq.js) 화면 글자 — 그 파일들은 window.LANG_STRINGS 에서 읽음
    $_mcfs_keys = ['audio_track', 'close', 'eq_custom', 'eq_note_music', 'eq_note_video', 'eq_off', 'eq_on', 'eq_p_classical', 'eq_p_club', 'eq_p_dance', 'eq_p_flat', 'eq_p_fullbass', 'eq_p_fullbasstreble', 'eq_p_fulltreble', 'eq_p_headphones', 'eq_p_largehall', 'eq_p_live', 'eq_p_party', 'eq_p_pop', 'eq_p_reggae', 'eq_p_rock', 'eq_p_ska', 'eq_p_soft', 'eq_p_softrock', 'eq_p_techno', 'eq_preset', 'eq_reset', 'eq_title', 'fsvs_ab_clear', 'fsvs_ab_set_a', 'fsvs_ab_set_b', 'fsvs_back', 'fsvs_default', 'fsvs_muted', 'fsvs_normal', 'fsvs_osd_ab_a', 'fsvs_osd_ab_off', 'fsvs_sub_file', 'fsvs_sub_pos', 'fsvs_sub_settings', 'fsvs_sub_size', 'fsvs_sub_sync', 'fsvs_sync_reset', 'fullscreen', 'mute', 'next_video', 'pause', 'pip', 'play', 'playback_mode', 'playback_speed', 'prev_video', 'quality', 'sec', 'seek', 'settings', 'sub_toggle_off', 'sub_toggle_on', 'vi_dlg_title', 'video_ab', 'video_ab_clear', 'video_ab_set_a', 'video_ab_set_b', 'video_loop_one', 'video_loop_one_off', 'video_loop_one_on', 'video_loop_one_on_s', 'volume'];
    $_mcfs_t = [];
    foreach ($_mcfs_keys as $_k) $_mcfs_t[$_k] = __('mcfs_' . $_k);
    echo '<script>window.LANG_STRINGS = Object.assign(window.LANG_STRINGS || {}, ' . json_encode($_mcfs_t, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>' . "\n";
    $_mcfs_v = function ($f) { return (int)@filemtime(__DIR__ . '/' . $f); };
    echo '<link rel="stylesheet" href="css/fs-video-skin.css?v=' . $_mcfs_v('css/fs-video-skin.css') . '">' . '<link rel="stylesheet" href="css/fs-eq.css?v=' . $_mcfs_v('css/fs-eq.css') . '">' . "\n";
    echo '<script src="js/fs-eq.js?v=' . $_mcfs_v('js/fs-eq.js') . '"></script><script src="js/fs-video-skin.js?v=' . $_mcfs_v('js/fs-video-skin.js') . '"></script>' . "\n";
    // 동영상 뷰어
    $is_zip_video = preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $base_file);
    $video_files = [];
    
    if ($is_zip_video) {
        // ZIP 파일 내 동영상 목록 추출
        $zip = new ZipArchive;
        if ($zip->open($base_file) === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = mc_zip_name($zip, $i);
                if (is_video_file($name)) {
                    $stat = $zip->statIndex($i);
                    $video_files[] = [
                        'name' => $name,
                        'size' => $stat['size']
                    ];
                }
            }
            $zip->close();
        }
        // 자연 정렬
        usort($video_files, function($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });
    }
    
    // 파일 크기 포맷팅 함수
    $format_size = function($size) {
        if ($size >= 1073741824) {
            return number_format($size / 1073741824, 2) . ' GB';
        } elseif ($size >= 1048576) {
            return number_format($size / 1048576, 2) . ' MB';
        } elseif ($size >= 1024) {
            return number_format($size / 1024, 2) . ' KB';
        }
        return $size . ' B';
    };
?>
<div class="container-fluid m-0 p-3">
    <?php if ($is_zip_video): ?>
        <!-- ZIP 내 동영상 목록 -->
        <?php if (empty($video_files)): ?>
            <div class="alert alert-warning text-center">
                <?php echo __h("viewer_no_video_in_archive"); ?>
            </div>
        <?php else: ?>
            <div class="video-container mb-3">
                <video id="zipVideoPlayer" controls playsinline>
                    <?php echo __h("viewer_browser_no_video"); ?>
                </video>
            </div>
            
            <div class="video-info" id="currentVideoInfo">
                <div class="video-title" id="currentVideoTitle"><?php echo __h("js_video_select"); ?></div>
                <div class="video-meta" id="currentVideoMeta"></div>
            </div>
            
            <div class="zip-video-list">
                <h5 class="mb-3">📁 <?php echo __("viewer_video_list", count($video_files)); ?></h5>
                <?php foreach ($video_files as $idx => $vf): ?>
                    <?php 
                    $is_playable = is_browser_playable_video($vf['name']);
                    ?>
                    <div class="zip-video-item <?php echo $is_playable ? '' : 'text-muted'; ?>" 
                         data-video="<?php echo h($vf['name']); ?>"
                         data-size="<?php echo $format_size($vf['size']); ?>"
                         data-playable="<?php echo $is_playable ? '1' : '0'; ?>"
                         onclick="playZipVideo(this)">
                        <span class="zip-video-icon"><?php echo $is_playable ? '🎬' : '⚠️'; ?></span>
                        <span class="zip-video-name"><?php echo h(basename($vf['name'])); ?></span>
                        <span class="zip-video-size"><?php echo $format_size($vf['size']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <script src="js/mc-video.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/mc-video.js'); ?>"></script>
            <script>
            var safeFile = <?php echo js(encode_url($getfile)); ?>;
            var currentVideoItem = null;
            // ★ (2026-10-31) 플레이어 보강 — 목록은 아래 기존 목록을 그대로 씀(showList:false), 끝났을 때 다음 영상은 기존 처리(manageEnded:false)
            var mcZipV = (window.MCVideo && document.getElementById('zipVideoPlayer')) ? MCVideo.init(document.getElementById('zipVideoPlayer'), {
                list: Array.prototype.map.call(document.querySelectorAll('.zip-video-item'), function (it) { return { title: it.getAttribute('data-video'), playable: it.getAttribute('data-playable') === '1' }; }),
                showList: false, manageEnded: false, t: window.MC_VIDEO_T,
                // ★ (2026-10-31) FileStation 플레이어의 이전·다음 버튼 → 아래 기존 목록의 그 항목 재생
                onPick: function (i) { var its = document.querySelectorAll('.zip-video-item'); if (its[i]) playZipVideo(its[i]); }
            }) : null;
            
            function playZipVideo(item) {
                var videoName = item.getAttribute('data-video');
                var videoSize = item.getAttribute('data-size');
                var isPlayable = item.getAttribute('data-playable') === '1';
                
                if (!isPlayable) {
                    alert(_vi18n.video_unsupported_format);
                    return;
                }
                
                // 활성 상태 업데이트
                document.querySelectorAll('.zip-video-item').forEach(function(el) {
                    el.classList.remove('active');
                });
                item.classList.add('active');
                currentVideoItem = item;
                if (mcZipV) { var _ix = Array.prototype.indexOf.call(document.querySelectorAll('.zip-video-item'), item); if (_ix >= 0) mcZipV.setCurrent(_ix); } // ★ (2026-10-31) 이전·다음 버튼 기준
                
                // 비디오 재생
                var player = document.getElementById('zipVideoPlayer');
                var videoUrl = 'viewer.php?filetype=zipvideo&stream=1&file=' + safeFile + '&video=' + encodeURIComponent(videoName) + '&bidx=<?php echo $current_bidx; ?>';
                
                player.src = videoUrl;
                player.load();
                if (mcZipV) mcZipV.setKey(<?php echo js($current_bidx . '|' . $getfile . '|'); ?> + videoName); // ★ 이어보기: 압축 파일 속 영상마다
                // ★ (2026-10-31) 자막: 압축 파일 속 같은 폴더의 srt·smi·vtt·ass(영상마다 새로 읽음)
                if (mcZipV && mcZipV.setSubs) {
                    mcZipV.setSubs([]);
                    fetch('viewer.php?filetype=zipvideo&vapi=zsubs&file=' + safeFile + '&video=' + encodeURIComponent(videoName) + '&bidx=<?php echo $current_bidx; ?>', { credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : []; })
                        .then(function (a) { if (currentVideoItem === item && Array.isArray(a) && a.length) mcZipV.setSubs(a); })
                        .catch(function () {});
                }
                player.play().catch(function(e) {
                    console.log('자동 재생 실패:', e);
                });
                
                // 정보 업데이트
                document.getElementById('currentVideoTitle').textContent = videoName.split('/').pop();
                document.getElementById('currentVideoMeta').textContent = _vi18n.size + ' ' + videoSize;
                
                // 스크롤 위치 조정
                player.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // 첫 번째 재생 가능한 동영상 자동 선택
            document.addEventListener('DOMContentLoaded', function() {
                var firstPlayable = document.querySelector('.zip-video-item[data-playable="1"]');
                if (firstPlayable) {
                    playZipVideo(firstPlayable);
                }
            });
            
            // 동영상 종료 시 다음 동영상 재생
            document.getElementById('zipVideoPlayer').addEventListener('ended', function() {
                if (mcZipV && !mcZipV.shouldAutoNext()) return; // ★ (2026-10-31) 자동 다음 꺼짐·반복·A-B 중이면 넘어가지 않음
                if (currentVideoItem) {
                    var next = currentVideoItem.nextElementSibling;
                    while (next && next.getAttribute('data-playable') !== '1') {
                        next = next.nextElementSibling;
                    }
                    if (next) {
                        playZipVideo(next);
                    }
                }
            });
            </script>
        <?php endif; ?>
    <?php else: ?>
        <!-- 단일 동영상 파일 -->
        <?php 
        $is_playable = is_browser_playable_video($base_file);
        $file_size = @filesize($base_file);
        $video_ext = strtoupper(pathinfo($base_file, PATHINFO_EXTENSION));
        
        // FFprobe로 상세 정보 가져오기
        $video_info = null;
        $ffprobe_path = $ffprobe_path ?? '';
        if (!empty($ffprobe_path) && is_file($base_file)) {
            // ★ (2026-11-04 00:31) ffprobe 결과를 src/video/vprobe 에 저장해 다시 열 때 재사용(파일 크기·수정시각이 바뀌면 새로) — 전엔 열 때마다 실행
            require_once __DIR__ . '/video_lib.php';
            $_ffc = (function_exists('mcv_file_key') && function_exists('mcv_cache_dir') && ($_ffd = mcv_cache_dir('vprobe'))) ? $_ffd . '/' . mcv_file_key($base_file) . '.ff.json' : '';
            if ($_ffc !== '' && is_file($_ffc)) {
                $video_info = @json_decode((string)@file_get_contents($_ffc), true);
                if (!is_array($video_info) || !isset($video_info['streams'])) $video_info = null;
            }
            if ($video_info === null) {
                $cmd = escape_shell_arg_safe($ffprobe_path) . ' -v quiet -print_format json -show_format -show_streams ' . escape_shell_arg_safe($base_file) . ' 2>&1';
                $json_output = @shell_exec($cmd);
                if ($json_output) {
                    $video_info = @json_decode($json_output, true);
                    if ($_ffc !== '' && is_array($video_info) && isset($video_info['streams'])) @file_put_contents($_ffc, json_encode($video_info), LOCK_EX);
                }
            }
        }
        
        // 정보 파싱
        $duration_str = '';
        $video_codec = '';
        $video_resolution = '';
        $video_fps = '';
        $video_bitrate = '';
        $audio_codec = '';
        $audio_channels = '';
        
        if ($video_info) {
            // 재생 시간
            if (isset($video_info['format']['duration'])) {
                $dur = (float)$video_info['format']['duration'];
                $hours = floor($dur / 3600);
                $_di = (int)$dur; // ★ (2026-10-30) 실수에 % 하면 PHP 8.1+ 경고
                $mins = floor(($_di % 3600) / 60);
                $secs = $_di % 60;
                if ($hours > 0) {
                    $duration_str = sprintf(__('viewer_time_hm'), $hours, $mins);
                } else {
                    $duration_str = sprintf(__('viewer_time_ms'), $mins, $secs);
                }
            }
            
            // 스트림 정보
            if (isset($video_info['streams'])) {
                foreach ($video_info['streams'] as $stream) {
                    if ($stream['codec_type'] === 'video' && empty($video_codec)) {
                        $codec_name = strtoupper($stream['codec_name'] ?? '');
                        // PHP 7.x 호환: 코덱 이름 변환
                        $codec_map = [
                            'H264' => 'H.264/AVC',
                            'AVC' => 'H.264/AVC',
                            'HEVC' => 'H.265/HEVC',
                            'H265' => 'H.265/HEVC',
                            'VP9' => 'VP9',
                            'VP8' => 'VP8',
                            'AV1' => 'AV1',
                            'MPEG4' => 'MPEG-4'
                        ];
                        $codec_display = $codec_map[$codec_name] ?? $codec_name;
                        $video_codec = $codec_display;
                        
                        // 해상도
                        if (isset($stream['width']) && isset($stream['height'])) {
                            $video_resolution = $stream['width'] . 'x' . $stream['height'];
                        }
                        
                        // FPS
                        if (isset($stream['r_frame_rate'])) {
                            $fps_parts = explode('/', $stream['r_frame_rate']);
                            if (count($fps_parts) == 2 && $fps_parts[1] > 0) {
                                $fps = round($fps_parts[0] / $fps_parts[1], 2);
                                $video_fps = $fps . 'fps';
                            }
                        }
                        
                        // 비트레이트
                        if (isset($stream['bit_rate'])) {
                            $br = (int)$stream['bit_rate'];
                            if ($br > 1000000) {
                                $video_bitrate = round($br / 1000000, 1) . ' Mbps';
                            } else {
                                $video_bitrate = round($br / 1000) . ' kbps';
                            }
                        }
                    }
                    
                    if ($stream['codec_type'] === 'audio' && empty($audio_codec)) {
                        $audio_codec = strtoupper($stream['codec_name'] ?? '');
                        $channels = $stream['channels'] ?? 0;
                        // PHP 7.x 호환: 채널 수 변환
                        $channel_map = [
                            1 => __('viewer_mono'),
                            2 => __('viewer_stereo'),
                            6 => __('viewer_5_1ch'),
                            8 => __('viewer_7_1ch')
                        ];
                        $audio_channels = $channel_map[$channels] ?? $channels . __('viewer_ch');
                    }
                }
            }
        }
        ?>
        
        <?php
        // ★ (2026-10-31) 실시간 변환 재생(FFmpeg 설정 시) — 브라우저가 못 여는 형식도 재생, 음성 트랙·화질 고르기(video_lib.php)
        require_once __DIR__ . '/video_lib.php';
        $mcv_ff = (mcv_ffmpeg() !== '');
        $mcv_tc_auto = (!$is_playable && $mcv_ff);
        // 같은 폴더의 동영상 목록(재생 목록·자동 다음 재생용) — 같은 폴더라 권한은 지금 파일과 같음, 많아도 500개까지
        $mc_vlist = [];
        $_vdir_real = rtrim(dirname($base_file), '/\\');
        $_vnames = @scandir($_vdir_real) ?: [];
        $_vnames = array_values(array_filter($_vnames, function ($n) use ($_vdir_real) {
            return $n !== '' && $n[0] !== '.' && is_video_file($n) && @is_file($_vdir_real . '/' . $n);
        }));
        natcasesort($_vnames);
        $_vnames = array_slice(array_values($_vnames), 0, 500);
        foreach ($_vnames as $_vn) {
            $_vrel = rtrim($link_dir, '/') . '/' . $_vn;
            $mc_vlist[] = [
                'title' => $_vn,
                'url' => 'viewer.php?filetype=video&file=' . encode_url($_vrel) . $bidx_param,
                'playable' => (bool)is_browser_playable_video($_vn) || $mcv_ff,   // ★ FFmpeg 가 있으면 변환 재생으로 모두 재생
                'current' => ($_vn === basename($base_file)),
            ];
        }
        ?>
        <?php
        // ★ (2026-10-31) 플레이어에 넘길 것 — 재생 방식 배지·동영상 정보 창·자막·변환 재생·장면 미리보기·자동 재생
        $mcv_opts = [];
        if ($is_playable || $mcv_tc_auto) {
            $_mp = mcv_probe($base_file, is_array($video_info) ? $video_info : null);
            $_src = trim(strtoupper((string)$_mp['vcodec']) . (($_mp['w'] && $_mp['h']) ? ' ' . $_mp['w'] . 'x' . $_mp['h'] : ''));
            if ($_src === '') $_src = $video_ext;
            $_alabels = [];
            foreach ($_mp['audio'] as $_ai => $_a) {
                $_parts = array_filter([$_a['title'] !== '' ? $_a['title'] : '', $_a['lang'] !== '' && $_a['lang'] !== 'und' ? $_a['lang'] : '', $_a['codec'], $_a['ch'] ? $_a['ch'] . 'ch' : '']);
                $_alabels[] = ['label' => ($_ai + 1) . '. ' . (implode(' · ', $_parts) ?: ('Track ' . ($_ai + 1))), 'def' => (bool)$_a['def']];
            }
            $_info = [
                [__('mcv_info_name'), basename($base_file)],
                [__('viewer_format'), $video_ext],
                [__('viewer_size'), $format_size($file_size)],
                [__('viewer_duration'), $duration_str ?: ''],
                [__('viewer_video'), trim($video_codec . ' ' . $video_resolution . ' ' . $video_fps . ' ' . $video_bitrate) ?: $_src],
                [__('viewer_audio'), trim($audio_codec . ' ' . $audio_channels)],
            ];
            if (count($_alabels) > 1) $_info[] = [__('mcv_info_audio_tracks'), implode("\n", array_column($_alabels, 'label'))];
            $_subs = [];
            foreach (mcv_sidecar_subs($base_file) as $_s) $_subs[] = ['name' => $_s['name'], 'ext' => $_s['ext'], 'content' => $_s['content']];
            $_vurl = 'viewer.php?filetype=video&file=' . encode_url($getfile) . $bidx_param;
            $mcv_opts = [
                'native' => $is_playable ? 'viewer.php?filetype=video&stream=1&file=' . encode_url($getfile) . $bidx_param : '',
                'badge' => ['src' => $_src],
                'info' => $_info,
                'subs' => $_subs,
                'autoplay' => !empty($_GET['autoplay']),
                'nextParam' => '&autoplay=1',
            ];
            if ($mcv_ff) {
                $mcv_opts['tc'] = ['url' => $_vurl . '&vapi=tc', 'dur' => (float)$_mp['dur'], 'h' => (int)$_mp['h'], 'audio' => $_alabels];
                $mcv_opts['frameUrl'] = $_vurl . '&vapi=frame';
            }
        }
        ?>
        <?php if ($is_playable || $mcv_tc_auto): ?>
            <div class="video-wrapper">
                <div class="video-container">
                    <video id="videoPlayer" controls playsinline<?php echo $mcv_tc_auto ? ' preload="none"' : ''; ?>>
                        <?php if ($is_playable): ?>
                        <source src="viewer.php?filetype=video&stream=1&file=<?php echo encode_url($getfile) . $bidx_param; ?>" type="<?php echo h(mime_type($base_file)); ?>">
                        <?php endif; ?>
                        <?php echo __h("viewer_browser_no_video"); ?>
                    </video>
                </div>
                <script src="js/mc-video.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/mc-video.js'); ?>"></script>
                <script>
                // ★ (2026-10-31) 동영상 플레이어 보강(±5초·속도·반복·A-B·자동 다음·재생 목록·이어보기·단축키)
                (function () {
                    var v = document.getElementById('videoPlayer');
                    if (!v || !window.MCVideo) return;
                    var o = <?php echo json_encode($mcv_opts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?> || {};
                    o.key = <?php echo js($current_bidx . '|' . $getfile); ?>;
                    o.list = <?php echo json_encode($mc_vlist, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;   // ★ (2026-10-31) UTF-8 아닌 파일 이름(옛 리눅스·시놀로지)이 있어도 스크립트가 깨지지 않게
                    o.t = window.MC_VIDEO_T;
                    // ★ (2026-10-31) 자동 다음 재생으로 열린 페이지 — 주소의 autoplay 는 지움(새로 고침·즐겨찾기 때 다시 자동 재생하지 않게)
                    if (o.autoplay) { try { history.replaceState(history.state, '', location.href.replace(/([?&])autoplay=1(&|$)/, function (m, a, b) { return b ? a : ''; })); } catch (e) {} }
                    MCVideo.init(v, o);
                })();
                </script>
                
                <div class="video-info">
                    <div class="video-title">🎬 <?php echo h($title); ?></div>
                    <div class="video-meta">
                        <?php echo __h("viewer_format"); ?>: <?php echo h($video_ext); ?> | 
                        <?php echo __h("viewer_size"); ?>: <?php echo $format_size($file_size); ?>
                        <?php if ($duration_str): ?> | <?php echo __h("viewer_duration"); ?>: <?php echo h($duration_str); ?><?php endif; ?>
                    </div>
                    <?php if ($video_codec): ?>
                    <div class="video-meta">
                        📹 <?php echo __h("viewer_video"); ?>: <?php echo h($video_codec); ?>
                        <?php if ($video_resolution): ?> <?php echo h($video_resolution); ?><?php endif; ?>
                        <?php if ($video_fps): ?> <?php echo h($video_fps); ?><?php endif; ?>
                        <?php if ($video_bitrate): ?> <?php echo h($video_bitrate); ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($audio_codec): ?>
                    <div class="video-meta">
                        🔊 <?php echo __h("viewer_audio"); ?>: <?php echo h($audio_codec); ?>
                        <?php if ($audio_channels): ?> <?php echo h($audio_channels); ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" onclick="downloadFile('viewer.php?filetype=video&download=1&file=<?php echo encode_url($getfile) . $bidx_param; ?>')">
                        <?php echo __("viewer_download_btn"); ?>
                    </button>
                    
                    <?php 
                    $ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
                    $ffmpeg_configured = !empty($ffmpeg_path) && file_exists($ffmpeg_path);
                    ?>
                    <?php if ($_viewer_is_admin): // ★ (2026-10-30) 변환 버튼은 관리자에게만 ?>
                    <button type="button" class="btn btn-<?php echo $ffmpeg_configured ? 'success' : 'secondary'; ?>" 
                            id="convertBtn" 
                            <?php if ($ffmpeg_configured): ?>
                                onclick="convertToMp4()"
                            <?php else: ?>
                                disabled 
                                title="<?php echo __h('video_ffmpeg_not_set'); ?>"
                                style="cursor: not-allowed;"
                            <?php endif; ?>>
                        <?php 
                        if (!$ffmpeg_configured) {
                            echo __('video_ffmpeg_not_set_short');
                        } else {
                            echo ($ext === 'mp4') ? __('video_reencode_h264') : __('video_convert_mp4');
                        }
                        ?>
                    </button>
                    <?php if (!$ffmpeg_configured): ?>
                    <small class="d-block text-warning mt-1"><?php echo __h("viewer_ffmpeg_notice"); ?></small>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- 변환 진행률 표시 -->
                <div id="convertProgress" class="mt-3" style="display: none;">
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                             style="width: 0%;" id="progressBar">0%</div>
                    </div>
                    <p class="mt-2 text-muted" id="convertStatus"><?php echo __h("viewer_convert_preparing"); ?></p>
                </div>
            </div>
            
            <script>
            // ✅ 페이지 이동 없이 파일 다운로드
            function downloadFile(url) {
                var iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = url;
                document.body.appendChild(iframe);
                setTimeout(function() {
                    document.body.removeChild(iframe);
                }, 10000);
            }
            
            function convertToMp4() {
                <?php 
                $ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
                $confirm_msg = ($ext === 'mp4') 
                    ? __('js_video_reencode_confirm')
                    : __('js_video_convert_confirm');
                ?>
                if (!confirm('<?php echo $confirm_msg; ?>')) {
                    return;
                }
                
                var btn = document.getElementById('convertBtn');
                var progressDiv = document.getElementById('convertProgress');
                var progressBar = document.getElementById('progressBar');
                var statusText = document.getElementById('convertStatus');
                var startTime = Date.now();
                
                // 시간 포맷 함수
                function formatElapsedTime(seconds) {
                    var hrs = Math.floor(seconds / 3600);
                    var mins = Math.floor((seconds % 3600) / 60);
                    var secs = Math.floor(seconds % 60);
                    
                    if (hrs > 0) {
                        return hrs + _vi18n.time_hours + ' ' + mins + _vi18n.time_minutes + ' ' + secs + _vi18n.time_seconds;
                    } else if (mins > 0) {
                        return mins + _vi18n.time_minutes + ' ' + secs + _vi18n.time_seconds;
                    } else {
                        return secs + _vi18n.time_seconds;
                    }
                }
                
                btn.disabled = true;
                btn.innerHTML = _vi18n.video_converting;
                progressDiv.style.display = 'block';
                
                var file = <?php echo js(encode_url($getfile)); ?>;
                
                // 진행률 폴링 시작
                var progressInterval = setInterval(function() {
                    var elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
                    
                    fetch('viewer.php?action=convert_progress&file=' + file + '&bidx=<?php echo $current_bidx; ?>')
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            if (data.progress >= 0) {
                                progressBar.style.width = data.progress + '%';
                                progressBar.innerText = data.progress + '%';
                                
                                if (data.status === 'converting') {
                                    statusText.innerText = _vi18n.video_progress + ' (' + data.progress + _vi18n.video_progress_pct + ' ' + elapsed;
                                }
                                // complete 상태는 여기서 처리하지 않음 (변환 fetch 응답에서 처리)
                            }
                        })
                        .catch(function() {});
                }, 1000);
                
                // 변환 요청
                fetch('viewer.php?action=convert_to_mp4&file=' + file + '&bidx=<?php echo $current_bidx; ?>&token=<?php echo urlencode($_viewer_delete_token); ?>')
                    .then(function(res) { 
                        return res.text().then(function(text) {
                            try {
                                return JSON.parse(text);
                            } catch(e) {
                                console.error('JSON 파싱 에러:', text.substring(0, 500));
                                throw new Error(_vi18n.video_parse_fail);
                            }
                        });
                    })
                    .then(function(data) {
                        clearInterval(progressInterval);
                        console.log('변환 응답:', data);
                        
                        var totalElapsed = formatElapsedTime((Date.now() - startTime) / 1000);
                        
                        if (data.success) {
                            progressBar.style.width = '100%';
                            progressBar.innerText = '100%';
                            var newUrl = data.new_url || '';
                            statusText.innerHTML = _vi18n.video_done + ' (' + _vi18n.video_elapsed + ' ' + totalElapsed + ')<br><a href="' + newUrl + '" class="btn btn-primary mt-2">' + _vi18n.video_view_converted + '</a>';
                        } else {
                            statusText.innerText = _vi18n.video_fail + ' ' + (data.error || _vi18n.video_unknown_error);
                            btn.disabled = false;
                            <?php 
                            $ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
                            $btn_text = ($ext === 'mp4') ? __('js_video_reencode_btn') : __('js_video_convert_btn');
                            ?>
                            btn.innerHTML = '<?php echo $btn_text; ?>';
                        }
                    })
                    .catch(function(err) {
                        clearInterval(progressInterval);
                        console.error('변환 에러:', err);
                        statusText.innerText = _vi18n.video_error + ' ' + (err.message || err);
                        btn.disabled = false;
                        <?php 
                        $ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
                        $btn_text = ($ext === 'mp4') ? __('js_video_reencode_btn') : __('js_video_convert_btn');
                        ?>
                        btn.innerHTML = '<?php echo $btn_text; ?>';
                    });
            }
            </script>
        <?php else: ?>
            <div class="video-unsupported">
                <h4>⚠️ <?php echo __h("viewer_video_unsupported"); ?></h4>
                <p>
                    <?php if ($video_codec && strpos($video_codec, 'HEVC') !== false): ?>
                        <?php echo __("viewer_hevc_message"); ?><br>
                        <?php echo __h("viewer_download_local_player"); ?>
                    <?php else: ?>
                        <strong><?php echo h($video_ext); ?></strong> <?php echo __("viewer_format_unsupported"); ?><br>
                        <?php echo __h("viewer_supported_formats"); ?>
                    <?php endif; ?>
                </p>
                <div class="video-info mt-3">
                    <div class="video-title">🎬 <?php echo h($title); ?></div>
                    <div class="video-meta">
                        <?php echo __h("viewer_format"); ?>: <?php echo h($video_ext); ?> | 
                        <?php echo __h("viewer_size"); ?>: <?php echo $format_size($file_size); ?>
                        <?php if ($duration_str): ?> | <?php echo __h("viewer_duration"); ?>: <?php echo h($duration_str); ?><?php endif; ?>
                    </div>
                    <?php if ($video_codec): ?>
                    <div class="video-meta">
                        📹 <?php echo __h("viewer_video"); ?>: <?php echo h($video_codec); ?>
                        <?php if ($video_resolution): ?> <?php echo h($video_resolution); ?><?php endif; ?>
                        <?php if ($video_fps): ?> <?php echo h($video_fps); ?><?php endif; ?>
                        <?php if ($video_bitrate): ?> <?php echo h($video_bitrate); ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($audio_codec): ?>
                    <div class="video-meta">
                        🔊 <?php echo __h("viewer_audio"); ?>: <?php echo h($audio_codec); ?>
                        <?php if ($audio_channels): ?> <?php echo h($audio_channels); ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" onclick="downloadFile('viewer.php?filetype=video&download=1&file=<?php echo encode_url($getfile) . $bidx_param; ?>')">
                        <?php echo __("viewer_download_btn"); ?>
                    </button>
                    
                    <?php 
                    $ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
                    $ffmpeg_configured = !empty($ffmpeg_path) && file_exists($ffmpeg_path);
                    ?>
                    <?php if ($_viewer_is_admin): // ★ (2026-10-30) 변환 버튼은 관리자에게만 ?>
                    <button type="button" class="btn btn-<?php echo $ffmpeg_configured ? 'success' : 'secondary'; ?>" 
                            id="convertBtn" 
                            <?php if ($ffmpeg_configured): ?>
                                onclick="convertToMp4()"
                            <?php else: ?>
                                disabled 
                                title="<?php echo __h('video_ffmpeg_not_set'); ?>"
                                style="cursor: not-allowed;"
                            <?php endif; ?>>
                        <?php 
                        if (!$ffmpeg_configured) {
                            echo __('video_ffmpeg_not_set_short');
                        } else {
                            echo ($ext === 'mp4') ? __('video_reencode_h264') : __('video_convert_mp4');
                        }
                        ?>
                    </button>
                    <?php if (!$ffmpeg_configured): ?>
                    <small class="d-block text-warning mt-1"><?php echo __h("viewer_ffmpeg_notice"); ?></small>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- 변환 진행률 표시 -->
                <div id="convertProgress" class="mt-3" style="display: none;">
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                             style="width: 0%;" id="progressBar">0%</div>
                    </div>
                    <p class="mt-2 text-muted" id="convertStatus"><?php echo __h("viewer_convert_preparing"); ?></p>
                </div>
            </div>
            
            <script>
            // ✅ 페이지 이동 없이 파일 다운로드
            function downloadFile(url) {
                var iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = url;
                document.body.appendChild(iframe);
                setTimeout(function() {
                    document.body.removeChild(iframe);
                }, 10000);
            }
            
            function convertToMp4() {
                <?php 
                $ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
                $confirm_msg = ($ext === 'mp4') 
                    ? __('js_video_reencode_confirm')
                    : __('js_video_convert_confirm');
                ?>
                if (!confirm('<?php echo $confirm_msg; ?>')) {
                    return;
                }
                
                var btn = document.getElementById('convertBtn');
                var progressDiv = document.getElementById('convertProgress');
                var progressBar = document.getElementById('progressBar');
                var statusText = document.getElementById('convertStatus');
                
                btn.disabled = true;
                btn.innerHTML = _vi18n.video_converting;
                progressDiv.style.display = 'block';
                
                var file = <?php echo js(encode_url($getfile)); ?>;
                
                // 진행률 폴링 시작
                var progressInterval = setInterval(function() {
                    fetch('viewer.php?action=convert_progress&file=' + file + '&bidx=<?php echo $current_bidx; ?>')
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            if (data.progress >= 0) {
                                progressBar.style.width = data.progress + '%';
                                progressBar.innerText = data.progress + '%';
                                
                                if (data.status === 'converting') {
                                    statusText.innerText = _vi18n.video_progress + ' (' + data.progress + '%)';
                                }
                                // complete 상태는 여기서 처리하지 않음 (변환 fetch 응답에서 처리)
                            }
                        })
                        .catch(function() {});
                }, 2000);
                
                // 변환 요청
                fetch('viewer.php?action=convert_to_mp4&file=' + file + '&bidx=<?php echo $current_bidx; ?>&token=<?php echo urlencode($_viewer_delete_token); ?>')
                    .then(function(res) { 
                        return res.text().then(function(text) {
                            try {
                                return JSON.parse(text);
                            } catch(e) {
                                console.error('JSON 파싱 에러:', text.substring(0, 500));
                                throw new Error(_vi18n.video_parse_fail);
                            }
                        });
                    })
                    .then(function(data) {
                        clearInterval(progressInterval);
                        console.log('변환 응답:', data);
                        
                        if (data.success) {
                            progressBar.style.width = '100%';
                            progressBar.innerText = '100%';
                            progressBar.classList.remove('progress-bar-animated');
                            progressBar.classList.add('bg-success');
                            var newUrl = data.new_url || '';
                            statusText.innerHTML = _vi18n.video_done_msg + '<br><a href="' + newUrl + '" class="btn btn-primary mt-2">' + _vi18n.video_view_converted + '</a>';
                        } else {
                            progressBar.classList.remove('progress-bar-animated');
                            progressBar.classList.add('bg-danger');
                            statusText.innerText = _vi18n.video_fail + ' ' + (data.error || _vi18n.video_unknown_error);
                            btn.disabled = false;
                            btn.innerHTML = _vi18n.video_convert_btn;
                        }
                    })
                    .catch(function(err) {
                        clearInterval(progressInterval);
                        console.error('변환 에러:', err);
                        progressBar.classList.add('bg-danger');
                        statusText.innerText = _vi18n.video_error + ' ' + (err.message || err);
                        btn.disabled = false;
                        btn.innerHTML = _vi18n.video_convert_btn;
                    });
            }
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
} elseif($type == "video_archive"){
    // 동영상 압축파일 - 재생 불가 안내
    $zip = new ZipArchive();
    $video_list = [];
    
    if ($zip->open($base_file) === TRUE) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = mc_zip_name($zip, $i);
            if (is_video_file($filename)) {
                $stat = $zip->statIndex($i);
                $video_list[] = [
                    'name' => basename($filename),
                    'size' => $stat['size']
                ];
            }
        }
        $zip->close();
    } elseif (preg_match('/\.(rar|cbr|7z|cb7)$/i', $base_file) && class_exists('ArchiveHandler')) {
        // ✅ [RAR/7Z 지원] 동영상 압축 목록 (ZIP 분기와 동일 동작, 크기는 미제공)
        try {
            $_h = new ArchiveHandler($base_file);
            $_vsizes = $_h->getFileSizes();
            foreach ($_h->getVideoFiles() as $vf) {
                $video_list[] = ['name' => basename($vf), 'size' => $_vsizes[$vf] ?? 0];
            }
        } catch (Exception $e) {}
    }
    
    // 파일 크기 포맷 함수
    $format_size = function($bytes) {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    };
?>
<div class="container-fluid m-0 p-3" style="margin-top: <?php echo !empty($_mcvv_on) ? '0' : '50px'; /* ★ fix56 위 막대가 있으면 그 높이로 대신 */ ?> !important;">
    <div class="video-wrapper">
        <div class="video-unsupported" style="margin-top: 30px;">
            <h4 style="font-size: 1.5em;">📦 <?php echo __h("viewer_video_archive"); ?></h4>
            <p style="color: #856404; font-size: 1.1em; line-height: 1.8;">
                <?php echo __("viewer_archive_no_stream"); ?><br>
                <?php echo __("viewer_archive_extract_hint"); ?>
            </p>
            
            <?php if (empty($video_list) && archive_is_encrypted($base_file)): // ★ (2026-10-31) 헤더까지 암호인 압축은 목록이 비어 이 화면으로 옴 ?>
            <div class="alert alert-warning mt-3 mc-noico" role="alert" style="text-align:left"><?php echo __h('viewer_archive_locked'); ?></div>
            <?php endif; ?>
            <?php if (!empty($video_list)): ?>
            <div class="video-info mt-3">
                <div class="video-title">📁 <?php echo h($title); ?></div>
                <div class="video-meta mb-3">
                    <?php echo __("viewer_included_videos", count($video_list)); ?>
                </div>
                
                <div style="text-align: left; max-height: 350px; overflow-y: auto;">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php echo __h("viewer_filename"); ?></th>
                                <th><?php echo __h("viewer_filesize"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($video_list as $idx => $video): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td style="word-break: break-all;"><?php echo h($video['name']); ?></td>
                                <td><?php echo $format_size($video['size']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-3">
                <button type="button" class="btn btn-primary" onclick="downloadFile('viewer.php?filetype=archive&download=1&file=<?php echo encode_url($getfile) . $bidx_param; ?>')">
                    <?php echo __("viewer_download_archive"); ?>
                </button>
            </div>
        </div>
    </div>
</div>
<script>
// ✅ 페이지 이동 없이 파일 다운로드
function downloadFile(url) {
    var iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);
    setTimeout(function() {
        document.body.removeChild(iframe);
    }, 10000);
}
</script>
<?php
} elseif($type == "pdf") { 
?>
<style>
/* PDF 뷰어 전체 레이아웃 */
.pdf-viewer-container {
    display: flex;
    height: 100vh;
    overflow: hidden;
}

/* 왼쪽 썸네일 사이드바 */
#pdf-sidebar {
    width: 200px;
    min-width: 200px;
    background: #2a2a2a;
    display: flex;
    flex-direction: column;
    transition: margin-left 0.3s ease;
}
#pdf-sidebar.hidden {
    margin-left: -200px;
}
#pdf-sidebar-header {
    padding: 10px;
    background: #1a1a1a;
    border-bottom: 1px solid #444;
    display: flex;
    align-items: center;
    gap: 10px;
}
#pdf-sidebar-header .sidebar-title {
    color: #fff;
    font-size: 14px;
    flex: 1;
}
#pdf-thumbnail-container {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}
.pdf-thumbnail-item {
    margin-bottom: 15px;
    cursor: pointer;
    text-align: center;
    transition: transform 0.2s;
}
.pdf-thumbnail-item:hover {
    transform: scale(1.02);
}
.pdf-thumbnail-item.active .pdf-thumb-canvas {
    border: 3px solid #64b5f6;
}
.pdf-thumb-canvas {
    max-width: 100%;
    border: 2px solid #444;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.pdf-thumb-number {
    color: #fff;
    font-size: 12px;
    margin-top: 5px;
}
.pdf-thumbnail-item.active .pdf-thumb-number {
    color: #64b5f6;
    font-weight: bold;
}

/* 메인 뷰어 영역 */
#pdf-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #525659;
    overflow: hidden;
}

/* 상단 툴바 */
#pdf-toolbar {
    height: 40px;
    background: #323639;
    display: flex;
    align-items: center;
    padding: 0 10px;
    gap: 10px;
    border-bottom: 1px solid #1a1a1a;
}
.pdf-toolbar-btn {
    background: transparent;
    border: none;
    color: #fff;
    padding: 5px 10px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.pdf-toolbar-btn:hover {
    background: rgba(255,255,255,0.1);
}
.pdf-toolbar-divider {
    width: 1px;
    height: 24px;
    background: #555;
}
.pdf-page-indicator {
    color: #fff;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.pdf-page-input {
    width: 50px;
    background: #1a1a1a;
    border: 1px solid #555;
    color: #fff;
    text-align: center;
    padding: 3px;
    border-radius: 4px;
}
.pdf-zoom-controls {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-left: auto;
}
.pdf-zoom-btn {
    background: transparent;
    border: none;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 4px;
}
.pdf-zoom-btn:hover {
    background: rgba(255,255,255,0.1);
}
.pdf-zoom-level {
    color: #fff;
    font-size: 14px;
    min-width: 50px;
    text-align: center;
}

/* PDF 페이지 컨테이너 */
#pdf-pages-container {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}
.pdf-page-wrapper {
    background: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
}
.pdf-page-wrapper canvas {
    display: block;
}

/* 반응형 - 태블릿 */
@media (max-width: 1024px) {
    #pdf-sidebar {
        width: 180px;
        min-width: 180px;
    }
    #pdf-sidebar.hidden {
        margin-left: -180px;
    }
}

/* 반응형 - 모바일 */
@media (max-width: 768px) {
    #pdf-sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 70vw;
        max-width: 280px;
        min-width: auto;
        z-index: 1050;
        height: 100vh;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        margin-left: 0 !important;
    }
    #pdf-sidebar.mobile-open {
        transform: translateX(0);
    }
    #pdf-sidebar.hidden {
        transform: translateX(-100%);
    }
    
    /* 사이드바 오버레이 */
    #pdf-sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0,0,0,0.5);
        z-index: 1040;
    }
    #pdf-sidebar-overlay.active {
        display: block;
    }
    
    /* 툴바 모바일 최적화 */
    #pdf-toolbar {
        height: auto;
        min-height: 44px;
        padding: 5px 8px;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .pdf-toolbar-btn {
        padding: 8px 10px;
        font-size: 13px;
        min-height: 36px;
    }
    
    .pdf-toolbar-divider {
        display: none;
    }
    
    .pdf-filename {
        font-size: 11px !important;
        max-width: 120px !important;
    }
    
    .pdf-page-indicator {
        font-size: 13px;
    }
    
    .pdf-page-input {
        width: 45px;
        padding: 5px;
        font-size: 14px;
    }
    
    .pdf-zoom-controls {
        gap: 3px;
        margin-left: auto;
    }
    
    .pdf-zoom-btn {
        padding: 8px;
        font-size: 16px;
        min-width: 36px;
        min-height: 36px;
    }
    
    .pdf-zoom-level {
        font-size: 12px;
        min-width: 40px;
    }
    
    /* 인쇄/목록 버튼 텍스트 숨기고 아이콘만 */
    .btn-text {
        display: none;
    }
    
    /* 페이지 컨테이너 */
    #pdf-pages-container {
        padding: 10px 5px;
        gap: 10px;
    }
    
    .pdf-page-wrapper {
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        max-width: 100%;
    }
    
    .pdf-page-wrapper canvas {
        max-width: 100%;
        height: auto !important;
    }
}

/* 반응형 - 소형 모바일 */
@media (max-width: 480px) {
    #pdf-toolbar {
        padding: 5px;
    }
    
    .pdf-toolbar-btn {
        padding: 6px 8px;
        font-size: 12px;
    }
    
    .pdf-filename {
        font-size: 10px !important;
        max-width: 80px !important;
    }
    
    .pdf-zoom-btn {
        padding: 6px;
        min-width: 32px;
        min-height: 32px;
    }
    
    .pdf-zoom-level {
        font-size: 11px;
        min-width: 35px;
    }
    
    .pdf-page-input {
        width: 40px;
        font-size: 13px;
    }
}
</style>

<!-- 모바일 사이드바 오버레이 -->
<div id="pdf-sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="pdf-viewer-container" id="pdf-viewer">
    <!-- 왼쪽 썸네일 사이드바 -->
    <div id="pdf-sidebar">
        <div id="pdf-sidebar-header">
            <button class="pdf-toolbar-btn" onclick="toggleSidebar()" title="<?php echo __h('js_close'); ?>">✕</button>
            <span class="sidebar-title"><?php echo __h("epub_toc"); ?></span>
        </div>
        <div id="pdf-thumbnail-container"></div>
    </div>
    
    <!-- 메인 뷰어 -->
    <div id="pdf-main">
        <!-- 상단 툴바 -->
        <div id="pdf-toolbar">
            <button class="pdf-toolbar-btn" onclick="toggleSidebar()" title="<?php echo __h('epub_toc'); ?>">
                <span style="font-size:18px;">☰</span><span class="btn-text"> <?php echo __h("epub_toc"); ?></span>
            </button>
            <div class="pdf-toolbar-divider"></div>
            <span class="pdf-filename" style="color:#fff; font-size:13px; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo h($title); ?>">
                <?php echo h(mb_strlen($title) > 35 ? mb_substr($title, 0, 35) . '...' : $title); ?>
            </span>
            <div class="pdf-toolbar-divider"></div>
            <div class="pdf-page-indicator">
                <input type="number" id="pdf-page-input" class="pdf-page-input" value="1" min="1">
                <span>/ <span id="pdf-total-pages">0</span></span>
            </div>
            <div class="pdf-zoom-controls">
                <button class="pdf-zoom-btn" onclick="zoomOut()" title="<?php echo __h('hwp_zoom_out'); ?>">−</button>
                <span class="pdf-zoom-level" id="pdf-zoom-level">150%</span>
                <button class="pdf-zoom-btn" onclick="zoomIn()" title="<?php echo __h('hwp_zoom_in'); ?>">+</button>
                <div class="pdf-toolbar-divider"></div>
                <button class="pdf-toolbar-btn" onclick="printPdf()" title="<?php echo __h('hwp_print'); ?>">
                    🖨️<span class="btn-text"> <?php echo __h("hwp_print"); ?></span>
                </button>
                <?php if ($_viewer_is_admin): /* ★ (2026-11-08 fix80, 펜닐 요청) 보고 있는 쪽을 이 폴더의 표지로(관리자) */ ?>
                <div class="pdf-toolbar-divider"></div>
                <button class="pdf-toolbar-btn" id="mcPdfCoverBtn" onclick="mcPdfCover(this)" title="<?php echo __h('pdf_cover_page'); ?>">
                    🖼️<span class="btn-text"> <?php echo __h("pdf_cover_page_short"); ?></span>
                </button>
                <?php endif; ?>
                <div class="pdf-toolbar-divider"></div>
                <button class="pdf-toolbar-btn" onclick="var u=(window.mcBackUrl ? window.mcBackUrl(this.getAttribute('data-def')) : this.getAttribute('data-def')); if (window.mcBackGo) window.mcBackGo(u, 'push'); else location.href=u;" data-def="./index.php?dir=<?php echo h(encode_url($link_dir)); ?>&amp;page=<?php echo intval($page); ?>&amp;bidx=<?php echo (int)$current_bidx; ?>" title="<?php echo __h('viewer_back_to_list'); ?>">
                    📋<span class="btn-text"> <?php echo __h("back"); ?></span>
                </button>
            </div>
        </div>
        
        <!-- PDF 페이지 표시 영역 -->
        <div id="pdf-pages-container"></div>
    </div>
</div>

<script>
let pdfDoc = null;
let totalPages = 0;
let current_page = 1;
<?php if ($_viewer_is_admin): ?>
// ★ (2026-11-08 fix80, 펜닐 요청) 지금 보고 있는 쪽을 높이 약 1000px JPEG 로 다시 그려 폴더 표지로(bookmark.php?mode=set_cover_img — 관리자·CSRF)
function mcPdfCover(btn) {
    if (!pdfDoc || !btn || btn.disabled) return;
    // ★ (2026-11-08 fix81) 결과를 ✓/✕ 로도 보임 — 휴대폰은 글자(.btn-text)가 숨겨져 '설정됨'이 안 보였음. 끝나면 원래 모양(innerHTML) 그대로
    var h0 = btn.innerHTML, t0 = btn.title;
    var mark = function (m, t) { btn.textContent = m; if (t) { var sp = document.createElement('span'); sp.className = 'btn-text'; sp.textContent = ' ' + t; btn.appendChild(sp); } };
    var show = function (t, ok) { mark(ok ? '✓' : '✕', t); btn.title = t; setTimeout(function () { btn.innerHTML = h0; btn.title = t0; btn.disabled = false; }, 2500); };
    btn.disabled = true; mark('…', '');
    pdfDoc.getPage(Math.max(1, Math.min(totalPages || 1, current_page || 1))).then(function (page) {
        var v1 = page.getViewport({ scale: 1 }), sc = Math.max(0.2, Math.min(3, 1000 / (v1.height || 1000))), vp = page.getViewport({ scale: sc });
        var c = document.createElement('canvas'); c.width = Math.round(vp.width); c.height = Math.round(vp.height);
        var ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
        return page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
            var fd = new FormData();
            fd.append('mode', 'set_cover_img'); fd.append('file', <?php echo js((string)$getfile); ?>);
            fd.append('csrf_token', <?php echo js((string)$_viewer_csrf); ?>); fd.append('image', c.toDataURL('image/jpeg', 0.88));
            return fetch('bookmark.php?bidx=<?php echo (int)$current_bidx; ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.text().then(function (t) { show(r.ok ? t : String(t || '✕').slice(0, 40), r.ok); }); });
        });
    }).catch(function () { show('✕', false); });
}
<?php endif; ?>
let currentScale = 1.5;
const isMobile = window.innerWidth <= 768;

// 모바일에서는 기본 스케일 조정
if (isMobile) {
    currentScale = 1.0;
}

const safeFile = <?php echo js(encode_url($getfile)); ?>; // ★ (2026-10-30) 인코딩 — 이름에 & # % 가 있으면 파일 주소가 잘려 PDF 가 안 열렸음
const pdfUrl = "viewer.php?filetype=pdf&file=" + safeFile + "&imgfile=pdf&bidx=<?php echo $current_bidx; ?>";

// 사이드바 토글 (모바일 지원)
function toggleSidebar() {
    const sidebar = document.getElementById('pdf-sidebar');
    const overlay = document.getElementById('pdf-sidebar-overlay');
    const isMobileNow = window.innerWidth <= 768;
    
    if (isMobileNow) {
        sidebar.classList.toggle('mobile-open');
        sidebar.classList.remove('hidden');
        overlay.classList.toggle('active');
    } else {
        sidebar.classList.toggle('hidden');
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    }
}

// 모바일 초기화 - 사이드바 숨김
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('pdf-sidebar');
        sidebar.classList.add('hidden');
        document.getElementById('pdf-zoom-level').textContent = '100%';
    }
});

// 인쇄 기능 (현재 창에서 iframe 사용)
function printPdf() {
    // 숨겨진 iframe 생성
    let printFrame = document.getElementById('pdf-print-frame');
    if (!printFrame) {
        printFrame = document.createElement('iframe');
        printFrame.id = 'pdf-print-frame';
        printFrame.style.position = 'fixed';
        printFrame.style.right = '0';
        printFrame.style.bottom = '0';
        printFrame.style.width = '0';
        printFrame.style.height = '0';
        printFrame.style.border = 'none';
        document.body.appendChild(printFrame);
    }
    
    printFrame.src = pdfUrl;
    printFrame.onload = function() {
        setTimeout(function() {
            printFrame.contentWindow.print();
        }, 500);
    };
}

// 줌 인/아웃
function zoomIn() {
    if (currentScale < 3) {
        currentScale += 0.25;
        updateZoom();
    }
}

function zoomOut() {
    if (currentScale > 0.5) {
        currentScale -= 0.25;
        updateZoom();
    }
}

function updateZoom() {
    document.getElementById('pdf-zoom-level').textContent = Math.round(currentScale * 100) + '%';
    // ★ (2026-11-04 07:45) 화면 근처 쪽만 다시 그리고, 나머지는 새 크기의 빈 그림판으로(전엔 전 쪽을 다시 그림)
    for (let i = 1; i <= totalPages; i++) {
        const canvas = document.getElementById('page_' + i);
        if (!canvas) continue;
        if (_mcPdfNear.has(i)) renderPage(i, canvas, currentScale);
        else mcPdfBlank(i);
    }
}

// ★ (2026-11-04 07:45) PDF 화면 근처 쪽만 그림(펜닐) — 전엔 열자마자 모든 쪽 본문·썸네일을 그려 200쪽 휴대폰에서 그림판 400개·약 416MB
//   · 쪽 크기는 먼저 모두 정해 둠(빈 그림판 — 스크롤 길이·쪽 위치·이어 보기 복원은 그대로)
//   · 보이는 곳 위아래 1.5화면 안의 쪽만 그리고, 멀어지면 빈 그림판으로 바꿔 메모리를 놓음(다시 오면 다시 그림)
//   · 썸네일도 썸네일 칸에서 보이는 근처만
var _mcPdfBase = {};       // 쪽 번호 → 배율 1 크기 {w, h}
var _mcPdfNear = new Set(); // 지금 화면 근처 쪽
var _mcThumbNear = new Set();
function mcPdfSize(i, scale) { var b = _mcPdfBase[i]; return b ? { w: Math.floor(b.w * scale), h: Math.floor(b.h * scale) } : null; }
function mcPdfSwap(old, w, h) { // 그린 적 없는 새 그림판으로 바꿈(옛 그림판 메모리는 놓아짐)
    if (old._mcTask) { try { old._mcTask.cancel(); } catch (e) {} old._mcTask = null; }
    var c = document.createElement('canvas');
    c.id = old.id; c.className = old.className;
    if (w && h) { c.width = w; c.height = h; c.dataset.sz = '1'; }
    if (old.parentNode) old.parentNode.replaceChild(c, old);
    return c;
}
function mcPdfBlank(i) {
    var old = document.getElementById('page_' + i);
    if (!old) return null;
    var s = mcPdfSize(i, currentScale);
    if (!old.dataset.drawn && s && old.width === s.w && old.height === s.h) return old; // 이미 빈 그림판이고 크기도 맞음
    return mcPdfSwap(old, s ? s.w : 0, s ? s.h : 0);
}

// 페이지 이동
function goToPage(pageNum) {
    if (pageNum < 1 || pageNum > totalPages) return;
    
    const pageWrapper = document.getElementById('page_wrapper_' + pageNum);
    if (pageWrapper) {
        pageWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// 현재 페이지 업데이트
function updateCurrentPage() {
    const container = document.getElementById('pdf-pages-container');
    const containerHeight = container.clientHeight;
    
    let visiblePage = 1;
    for (let i = 1; i <= totalPages; i++) {
        const wrapper = document.getElementById('page_wrapper_' + i);
        if (wrapper) {
            const rect = wrapper.getBoundingClientRect();
            const containerRect = container.getBoundingClientRect();
            if (rect.top - containerRect.top < containerHeight / 2) {
                visiblePage = i;
            }
        }
    }
    
    if (current_page !== visiblePage) {
        current_page = visiblePage;
        document.getElementById('pdf-page-input').value = current_page;
        
        // 썸네일 활성화 업데이트
        document.querySelectorAll('.pdf-thumbnail-item').forEach((item, idx) => {
            if (idx + 1 === current_page) {
                item.classList.add('active');
                item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    }
}

// 썸네일 렌더링 — ★ (2026-11-04 07:45) 칸(item)은 순서대로 바로 만들고(전엔 그리기가 끝나는 순서로 붙어 순서가 뒤섞일 수 있었음), 그림은 보일 때만
function renderThumbnail(pageNum) {
    const item = document.createElement('div');
    item.className = 'pdf-thumbnail-item' + (pageNum === 1 ? ' active' : '');
    item.dataset.pg = pageNum;
    item.onclick = function() { goToPage(pageNum); };
    
    const canvas = document.createElement('canvas');
    canvas.className = 'pdf-thumb-canvas';
    canvas.id = 'thumb_' + pageNum;
    canvas.width = 120; canvas.height = 160; // 크기를 알기 전 자리
    
    const number = document.createElement('div');
    number.className = 'pdf-thumb-number';
    number.textContent = pageNum;
    
    item.appendChild(canvas);
    item.appendChild(number);
    document.getElementById('pdf-thumbnail-container').appendChild(item);
    return item;
}
function mcThumbDraw(pageNum) {
    var cv = document.getElementById('thumb_' + pageNum);
    if (!cv || cv.dataset.drawn) return;
    cv.dataset.drawn = '1';
    pdfDoc.getPage(pageNum).then(function (page) {
        if (!cv.isConnected || !_mcThumbNear.has(pageNum)) { delete cv.dataset.drawn; return; }
        const viewport = page.getViewport({ scale: 0.3 });
        cv.width = viewport.width;
        cv.height = viewport.height;
        const task = page.render({ canvasContext: cv.getContext('2d'), viewport: viewport });
        cv._mcTask = task;
        if (task && task.promise) task.promise.then(function () { if (cv._mcTask === task) cv._mcTask = null; }, function () { if (cv._mcTask === task) cv._mcTask = null; });
    }).catch(function () { delete cv.dataset.drawn; });
}
function mcThumbBlank(pageNum) {
    var cv = document.getElementById('thumb_' + pageNum);
    if (!cv || !cv.dataset.drawn) return;
    var s = mcPdfSize(pageNum, 0.3);
    mcPdfSwap(cv, s ? s.w : cv.width, s ? s.h : cv.height);
}

// 페이지 렌더링
function renderPage(pageNum, canvas, scale) {
    canvas.dataset.drawn = String(scale); // ★ (2026-11-04 07:45) 그림 — 화면 근처를 벗어나면 mcPdfBlank 가 비움
    pdfDoc.getPage(pageNum).then(function(page) {
        if (!canvas.isConnected) return; // 그사이 빈 그림판으로 바뀜(멀어짐)
        const viewport = page.getViewport({ scale: scale });
        const ctx = canvas.getContext('2d');
        // ★ (2026-11-02 08:59) 확대·축소를 빨리 누르면 같은 그림판에 그리기가 겹쳐 오류·깨진 그림 — 앞의 그리기를 멈추고 새로
        if (canvas._mcTask) { try { canvas._mcTask.cancel(); } catch (e) {} canvas._mcTask = null; }
        
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        canvas.dataset.sz = '1'; // ★ (2026-11-02 01:01) 크기 정해짐 — 이어 보기 복원이 앞쪽들 크기가 다 정해진 뒤 맞추게
        
        const task = page.render({ canvasContext: ctx, viewport: viewport });
        canvas._mcTask = task;
        if (task && task.promise) task.promise.then(function () { if (canvas._mcTask === task) canvas._mcTask = null; }, function (e) { if (canvas._mcTask === task) canvas._mcTask = null; if (!e || e.name !== 'RenderingCancelledException') console.error('페이지 ' + pageNum + ' 렌더링 실패:', e); });
    }).catch(function(error) {
        console.error('페이지 ' + pageNum + ' 렌더링 실패:', error);
    });
}

// ★ (2026-11-02 01:01) PDF 지금 위치 — 보기 칸 맨 위 줄이 걸친 쪽(1부터)과 그 쪽 안 위치(천분율 0~999). 아직 쪽이 없으면 null
//   (자동 저장·사생활 보호 복귀가 씀 / 쪽 사이 틈이면 다음 쪽 맨 위)
window.mcPdfHere = function () {
    try {
        var c = document.getElementById('pdf-pages-container');
        if (!c || !totalPages) return null;
        var cTop = c.getBoundingClientRect().top;
        for (var i = 1; i <= totalPages; i++) {
            var w = document.getElementById('page_wrapper_' + i);
            if (!w) break;
            var r = w.getBoundingClientRect();
            if (r.bottom > cTop + 1) {
                var f = (r.height > 20 && r.top < cTop) ? Math.max(0, Math.min(999, Math.round((cTop - r.top) / r.height * 1000))) : 0;
                return { page: i, f: f, n: totalPages }; // ★ (2026-11-02 20:10) n = 총 쪽수(서재 진행률용)
            }
        }
        return { page: totalPages, f: 0, n: totalPages }; // ★ (2026-11-02 20:22) 여기도 총 쪽수
    } catch (e) { return null; }
};

// ★ (2026-11-02 01:01) PDF 이어 보기 — 주소의 #pdf_N(fM) 쪽·줄로. 쪽 크기는 비동기로 정해져서(앞쪽들이 커지면 밀림)
//   앞쪽들 크기가 다 정해질 때까지 기다렸다가 맞추고, 안정될 때까지 잠깐 다시 맞춤. 사용자가 스크롤하면 바로 멈춤(최대 15초)
function mcPdfRestore() {
    var m = (location.hash || '').match(/^#pdf_(\d{1,6})(?:f(\d{1,3}))?$/);
    if (!m) return;
    var pg = Math.max(1, Math.min(parseInt(m[1], 10) || 1, totalPages || 1));
    var fr = m[2] ? Math.min(999, parseInt(m[2], 10)) : 0;
    if (pg <= 1 && fr <= 0) return;
    var c = document.getElementById('pdf-pages-container');
    if (!c) return;
    var t0 = Date.now(), stable = 0, stop = false;
    function onUser() { if (Date.now() - t0 > 600) stop = true; }
    c.addEventListener('wheel', onUser, { passive: true });
    c.addEventListener('touchmove', onUser, { passive: true });
    window.addEventListener('keydown', onUser, true);
    function done() { clearInterval(tm); c.removeEventListener('wheel', onUser); c.removeEventListener('touchmove', onUser); window.removeEventListener('keydown', onUser, true); }
    var tm = setInterval(function () {
        if (stop || Date.now() - t0 > 15000) { done(); return; }
        for (var i = 1; i <= pg; i++) { var cv = document.getElementById('page_' + i); if (!cv || cv.dataset.sz !== '1') return; } // 앞쪽들 크기 대기
        var w = document.getElementById('page_wrapper_' + pg);
        if (!w) { done(); return; }
        var r = w.getBoundingClientRect(), d = Math.round(r.top + r.height * fr / 1000 - c.getBoundingClientRect().top);
        if (Math.abs(d) > 1) { var b0 = c.scrollTop; c.scrollTop += d; if (c.scrollTop === b0) { if (++stable >= 3) done(); } else stable = 0; } // 끝이라 더 못 내려가면(막힘) 그대로 끝
        else if (++stable >= 3) done();
    }, 100);
}

// PDF 로드
// ★ (2026-10-31) PDF.js 6.4.299 — 모듈(ESM)이라 import() 로 읽음(파일 이름은 .js 그대로: .mjs 는 Windows Apache 에 MIME 이 없을 수 있음)
//   종전 2.6.347 은 악성 PDF 로 스크립트가 실행되는 취약점(CVE-2024-4367, 4.2.67 에서 고침)이 있었음
//   한중일 글꼴 문자표(cmaps)·기본 글꼴·JPEG2000/JBIG2 그림 풀기(wasm)·색 프로필(iccs) 경로를 함께 넘김 — 글꼴을 안 품은 한글 PDF 도 글자가 보이게
(function () {
    var PDFJS_BASE = new URL('js/pdfjs/', document.baseURI).href, PDFJS_V = '6.4.299';
    function showErr(html) { document.getElementById('pdf-pages-container').innerHTML = '<div class="alert alert-danger m-3">' + html + '</div>'; }
    var load;
    try { load = import(PDFJS_BASE + 'pdf.min.js?v=' + PDFJS_V); } catch (e) { load = Promise.reject(e); } // import() 를 모르는 아주 옛 브라우저
    load.then(function (mod) {
        var pdfjsLib = (mod && mod.getDocument) ? mod : (globalThis.pdfjsLib || mod);
        if (!pdfjsLib || !pdfjsLib.getDocument) throw new Error('pdfjsLib not found');
        pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_BASE + 'pdf.worker.min.js?v=' + PDFJS_V;
        return pdfjsLib.getDocument({
            url: new URL(pdfUrl, document.baseURI).href,
            cMapUrl: PDFJS_BASE + 'cmaps/', cMapPacked: true,
            standardFontDataUrl: PDFJS_BASE + 'standard_fonts/',
            wasmUrl: PDFJS_BASE + 'wasm/',
            iccUrl: PDFJS_BASE + 'iccs/',
            enableXfa: false
        }).promise.then(function (doc) {
                pdfDoc = doc;
                totalPages = doc.numPages;
                document.getElementById('pdf-total-pages').textContent = totalPages;
                document.getElementById('pdf-page-input').max = totalPages;
        
                const container = document.getElementById('pdf-pages-container');
                const thumbBox = document.getElementById('pdf-thumbnail-container');
        
                // ★ (2026-11-04 07:45) 화면 근처만 그리기 — 위아래 1.5화면 안에 들어오면 그리고, 벗어나면 비움
                const pageObs = ('IntersectionObserver' in window) ? new IntersectionObserver(function (ents) {
                    ents.forEach(function (en) {
                        const i = parseInt(en.target.dataset.pg, 10);
                        const cv = document.getElementById('page_' + i);
                        if (!cv) return;
                        if (en.isIntersecting) {
                            _mcPdfNear.add(i);
                            if (cv.dataset.drawn !== String(currentScale)) renderPage(i, cv, currentScale);
                        } else {
                            _mcPdfNear.delete(i);
                            if (cv.dataset.drawn) mcPdfBlank(i);
                        }
                    });
                }, { root: container, rootMargin: '150% 0px' }) : null;
                const thumbObs = ('IntersectionObserver' in window) ? new IntersectionObserver(function (ents) {
                    ents.forEach(function (en) {
                        const i = parseInt(en.target.dataset.pg, 10);
                        if (en.isIntersecting) { _mcThumbNear.add(i); mcThumbDraw(i); }
                        else { _mcThumbNear.delete(i); mcThumbBlank(i); }
                    });
                }, { root: thumbBox, rootMargin: '200% 0px' }) : null;
        
                // 각 페이지 자리 — 크기는 먼저 1쪽 크기로 두고, 쪽마다 실제 크기를 알면 바로 맞춤(그리지는 않음)
                for (let i = 1; i <= totalPages; i++) {
                    // 썸네일 칸
                    const titem = renderThumbnail(i);
            
                    // 메인 페이지 래퍼
                    const wrapper = document.createElement('div');
                    wrapper.className = 'pdf-page-wrapper';
                    wrapper.id = 'page_wrapper_' + i;
                    wrapper.dataset.pg = i;
            
                    const canvas = document.createElement('canvas');
                    canvas.id = 'page_' + i;
                    wrapper.appendChild(canvas);
                    container.appendChild(wrapper);
            
                    if (!pageObs) { _mcPdfNear.add(i); renderPage(i, canvas, currentScale); } // 아주 옛 브라우저는 예전처럼 전부
                    if (!thumbObs) { _mcThumbNear.add(i); }
                    pdfDoc.getPage(i).then(function (page) {
                        const vp = page.getViewport({ scale: 1 });
                        _mcPdfBase[i] = { w: vp.width, h: vp.height };
                        const cv = document.getElementById('page_' + i);
                        if (cv && !cv.dataset.drawn) { const s = mcPdfSize(i, currentScale); cv.width = s.w; cv.height = s.h; cv.dataset.sz = '1'; }
                        const tv = document.getElementById('thumb_' + i);
                        if (tv && !tv.dataset.drawn) { const s = mcPdfSize(i, 0.3); tv.width = s.w; tv.height = s.h; }
                        if (pageObs) pageObs.observe(wrapper);
                        if (thumbObs) thumbObs.observe(titem); else mcThumbDraw(i);
                    }).catch(function () {
                        if (pageObs) pageObs.observe(wrapper);
                        if (thumbObs) thumbObs.observe(titem);
                    });
                }
        
                // 스크롤 이벤트
                container.addEventListener('scroll', function() {
                    requestAnimationFrame(updateCurrentPage);
                });
                mcPdfRestore(); // ★ (2026-11-02 01:01) 이어 보기·사생활 보호 복귀·책갈피 위치
        
        }, function (error) {
            console.error('PDF 로드 실패:', error);
            showErr(_vi18n.pdf_load_fail + '<br>' + _vi18n.pdf_error + ' ' + String((error && error.message) || error).replace(/[<>&"]/g, ''));
        });
    }).catch(function (e) {
        console.error('PDF.js 로드 실패:', e);
        showErr(_vi18n.pdfjs_load_fail);
    });
})();

// 페이지 입력 처리
document.getElementById('pdf-page-input').addEventListener('change', function() {
    const page = parseInt(this.value);
    if (page >= 1 && page <= totalPages) {
        goToPage(page);
    } else {
        this.value = current_page;
    }
});

document.getElementById('pdf-page-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.blur();
    }
});

// 키보드 단축키
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT') return;
    
    switch(e.key) {
        case 'ArrowLeft':
        case 'PageUp':
            if (current_page > 1) goToPage(current_page - 1);
            break;
        case 'ArrowRight':
        case 'PageDown':
            if (current_page < totalPages) goToPage(current_page + 1);
            break;
        case 'Home':
            goToPage(1);
            break;
        case 'End':
            goToPage(totalPages);
            break;
        case '+':
        case '=':
            zoomIn();
            break;
        case '-':
            zoomOut();
            break;
        case 'p':
            if (e.ctrlKey) {
                e.preventDefault();
                printPdf();
            }
            break;
    }
});
</script>

<?php
} elseif($mode == "toon"){
?>
<div class="container-fluid m-0 p-0" onclick="hidenav();">
<?php
} elseif($mode == "book") {
?>
<div class="container-fluid m-0 p-0">
<?php
} // end of if-elseif chain (video/toon/book/pdf)

if($type != "pdf" && $type != "video"){
?>
            <p class="m-0 p-0" align='center'>
              <?php
			  $loaded = 0;
			  $image_counter = 0;
			  
			  $list = [];
			  $file_type = "";
			  
					if ($type == "zip") {
						$_arc_ext = strtolower(pathinfo($base_file, PATHINFO_EXTENSION));
						if (in_array($_arc_ext, ['rar','cbr','7z','cb7'])) {
							// ✅ [RAR/7Z] .image_files.json 캐시 우선 (없으면 ArchiveHandler)
							$img_cache_file = $base_file . '.image_files.json';
							if (is_file($img_cache_file)) {
								$cached_files = @json_decode(file_get_contents($img_cache_file), true);
								if (is_array($cached_files) && !empty($cached_files)) {
									$counter = 0;
									foreach ($cached_files as $fname) {
										if (is_string($fname)) { $list[$counter] = $fname; $counter++; }
									}
								}
							}
							if (empty($list) && class_exists('ArchiveHandler')) {
								try {
									$_h = new ArchiveHandler($base_file);
									$_i = 0;
									foreach ($_h->getImageFiles() as $name) { if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $name)) $list[$_i++] = $name; } // ★ (2026-10-30) 이미지 요청 쪽 목록과 같은 확장자
								} catch (Exception $e) {}
							}
						} else {
							// ZIP/CBZ: 원본 동작 그대로 (ZipArchive 직접)
							$zip = new ZipArchive;
							if ($zip->open($base_file) == TRUE) {
								for ($i = 0; $i < $zip->numFiles; $i++) {
									$name = mc_zip_name($zip, $i);
									if(preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $name)){
										$list[$i] = $name;
									}
								}
							}
						}
 					} elseif($type == "images") {
						// ✅ 캐시 활용 (경로 정규화)
						$img_cache_file = $base_file . '.image_files.json';
						if (is_file($img_cache_file)) {
							$cached_files = @json_decode(file_get_contents($img_cache_file), true);
							if (is_array($cached_files) && !empty($cached_files)) {
								$counter = 0;
								foreach ($cached_files as $fname) {
									if (is_string($fname) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fname) && !preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $fname)) { /* ★ (2026-11-09 14:14) '..' 조각이 든 이름은 건너뜀 — 손으로 만든 목록 파일로 폴더 밖·막힌 폴더 그림이 나왔음(앱은 폴더 맨 위 이름만 씀) */ /* ★ (2026-11-02 20:27) 그림 이름만 — 이 파일이 목록 대신 메타(totalpage·page_order·thumbnail…)로 덮여 있는 경우가 있어, 'toon'·'3' 같은 이름의 하위 폴더·파일이 쪽으로 잡힐 수 있었음 */
										$full_path = realpath($base_file . DIRECTORY_SEPARATOR . $fname);
										if ($full_path !== false) {
											$list[$counter] = $full_path;
											$counter++;
										}
									}
								}
							}
						}
						// 캐시 없거나 비어있으면 직접 스캔
						if (empty($list)) {
							$counter = 0;
							$iterator = new DirectoryIterator($base_file);
							foreach ($iterator as $jpgfile) {
								if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $jpgfile) && !mc_folder_nonpage((string)$jpgfile)) {
									$list[$counter] = $base_file."/".$jpgfile;
									$counter++;
								}
							}
						}
						$file_type = "filetype=images&";
					}

$total = count($list);
$list = n_sort($list);
// ★ (2026-10-31) 암호 걸린 압축이면 안내 — 전에는 빈 화면(헤더 암호)이나 안 보이는 쪽만 나왔음
if ($type == "zip" && archive_is_encrypted($base_file)) {
    echo "<div class='alert alert-warning mx-auto my-3 mc-noico' role='alert' style='max-width:640px;text-align:left;margin-top:90px !important'>" . __h('viewer_archive_locked') . "</div>";
}

$page_order = $pageorder['page_order'] ?? '0';

$initial = min(10, $total);
if ($page_order === "1" || $page_order === "2") {
    $initial = $initial - ($initial % 2);
}

// ✅ $base_file은 이미 994줄에서 validate_file_path()로 검증됨 - 재계산 불필요
// $base_file = $base_dir . decode_file_param($_GET['file'] ?? '');  // 제거됨
$hash = md5($base_file);

// HTML 속성용 안전한 인코딩
$safe_file = encode_url($_GET['file'] ?? '');
$safe_type = h($type);
$safe_user_id = h($user_id);
$safe_hash = h($hash);

// ★ (2026-10-29) 세로분할(3/4): 분할 대상이 아닌 세로 페이지는 1장만 표시(같은 그림 좌/우 2번 중복 방지)
//   판정 불가(null)·RAR/7Z는 기존처럼 좌/우 2장. JS 추가 로딩(appendImages)도 data-split-map으로 같은 판정 사용
$_mc_split_map = [];
if ($page_order === "3" || $page_order === "4") {
    $_mc_split_map = mc_book_split_map($base_file, $type, mc_split_cache_dir($base_file, $user_id ?? ''));
}
$safe_split_map = h(json_encode(array_values($_mc_split_map)) ?: '[]');

echo "<div class=\"text-center\" id=\"lightgallery\"
       data-split-map=\"{$safe_split_map}\"
       data-total=\"{$total}\"
       data-file=\"{$safe_file}\"
       data-page-order=\"{$page_order}\"
       data-filetype=\"{$safe_type}\"
       data-userid=\"{$safe_user_id}\"
       data-hash=\"{$safe_hash}\">";

if ($page_order === "0") {
    for ($i = 0; $i < $initial; $i++) {
        $img_src = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img={$i}");

        echo "<div class='image-row d-flex justify-content-center'>";
        echo "<div class='image-wrapper position-relative d-inline-block'>";
        echo "<img class='lazyload img-fluid lg-item' id=\"image{$i}\" data-src=\"{$img_src}\" ";
        echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" ";
        echo "onerror=\"this.onerror=null; console.error('Image load failed:', this.dataset.src);\" />";
        echo "<div class='img-overlay-page text-white small'>" . ($i + 1) . " / {$total}</div>";
        echo "</div>";
        echo "</div>";
    }
} elseif ($page_order === "1") {
    for ($i = 0; $i < $initial; $i += 2) {
        echo "<div class='image-row d-flex justify-content-center'>";

        $img_srcL = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img={$i}&order=left");
        echo "<div class='image-wrapper position-relative d-inline-block'>";
        echo "<img class='lazyload img-fluid lg-item' id=\"image{$i}\" data-src=\"{$img_srcL}\" ";
        echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
        echo "<div class='img-overlay-page text-white small'>" . ($i + 1) . " / {$total}</div>";
        echo "</div>";

        if ($i + 1 < $total) {
            $img_srcR = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img=" . ($i + 1) . "&order=right");
            echo "<div class='image-wrapper position-relative d-inline-block'>";
            echo "<img class='lazyload img-fluid lg-item' id=\"image" . ($i + 1) . "\" data-src=\"{$img_srcR}\" ";
            echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
            echo "<div class='img-overlay-page text-white small'>" . ($i + 2) . " / {$total}</div>";
            echo "</div>";
        }

        echo "</div>";
    }
} elseif ($page_order === "2") {
    for ($i = 0; $i < $initial; $i += 2) {
        echo "<div class='image-row d-flex justify-content-center'>";

        if ($i + 1 < $total) {
            $img_srcR = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img=" . ($i + 1) . "&order=right");
            echo "<div class='image-wrapper position-relative d-inline-block'>";
            echo "<img class='lazyload img-fluid lg-item' id=\"image" . ($i + 1) . "\" data-src=\"{$img_srcR}\" ";
            echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
            echo "<div class='img-overlay-page text-white small'>" . ($i + 2) . " / {$total}</div>";
            echo "</div>";
        }

        $img_srcL = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img={$i}&order=left");
        echo "<div class='image-wrapper position-relative d-inline-block'>";
        echo "<img class='lazyload img-fluid lg-item' id=\"image{$i}\" data-src=\"{$img_srcL}\" ";
        echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
        echo "<div class='img-overlay-page text-white small'>" . ($i + 1) . " / {$total}</div>";
        echo "</div>";

        echo "</div>";
    }
} elseif ($page_order === "3") {
    // ✅ 세로 분할 (좌→우): 각 이미지를 left, right 순서로 표시
    for ($i = 0; $i < $initial; $i++) {
        if (($_mc_split_map[$i] ?? null) === 0) {
            // 분할 대상 아님(세로 페이지) → 원본 1장 (기본 모드와 같은 마크업·id)
            $img_src = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img={$i}");
            echo "<div class='image-row d-flex justify-content-center'>";
            echo "<div class='image-wrapper position-relative d-inline-block'>";
            echo "<img class='lazyload img-fluid lg-item' id=\"image{$i}\" data-src=\"{$img_src}\" ";
            echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
            echo "<div class='img-overlay-page text-white small'>" . ($i + 1) . " / {$total}</div>";
            echo "</div>";
            echo "</div>";
            continue;
        }
        // Left half
        $img_srcL = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img={$i}&split=left");
        echo "<div class='image-row d-flex justify-content-center'>";
        echo "<div class='image-wrapper position-relative d-inline-block'>";
        echo "<img class='lazyload img-fluid lg-item' id=\"image{$i}_left\" data-src=\"{$img_srcL}\" ";
        echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
        echo "<div class='img-overlay-page text-white small'>" . ($i + 1) . "L / {$total}</div>";
        echo "</div>";
        echo "</div>";
        
        // Right half
        $img_srcR = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img={$i}&split=right");
        echo "<div class='image-row d-flex justify-content-center'>";
        echo "<div class='image-wrapper position-relative d-inline-block'>";
        echo "<img class='lazyload img-fluid lg-item' id=\"image{$i}_right\" data-src=\"{$img_srcR}\" ";
        echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
        echo "<div class='img-overlay-page text-white small'>" . ($i + 1) . "R / {$total}</div>";
        echo "</div>";
        echo "</div>";
    }
} elseif ($page_order === "4") {
    // ✅ 세로 분할 (우→좌): 각 이미지를 right, left 순서로 표시 (일본 만화 스타일)
    for ($i = 0; $i < $initial; $i++) {
        if (($_mc_split_map[$i] ?? null) === 0) {
            // 분할 대상 아님(세로 페이지) → 원본 1장 (기본 모드와 같은 마크업·id)
            $img_src = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img={$i}");
            echo "<div class='image-row d-flex justify-content-center'>";
            echo "<div class='image-wrapper position-relative d-inline-block'>";
            echo "<img class='lazyload img-fluid lg-item' id=\"image{$i}\" data-src=\"{$img_src}\" ";
            echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
            echo "<div class='img-overlay-page text-white small'>" . ($i + 1) . " / {$total}</div>";
            echo "</div>";
            echo "</div>";
            continue;
        }
        // Right half first
        $img_srcR = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img={$i}&split=right");
        echo "<div class='image-row d-flex justify-content-center'>";
        echo "<div class='image-wrapper position-relative d-inline-block'>";
        echo "<img class='lazyload img-fluid lg-item' id=\"image{$i}_right\" data-src=\"{$img_srcR}\" ";
        echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
        echo "<div class='img-overlay-page text-white small'>" . ($i + 1) . "R / {$total}</div>";
        echo "</div>";
        echo "</div>";
        
        // Left half
        $img_srcL = h("viewer.php?file=" . encode_url($_GET['file'] ?? '') . "&bidx=" . $current_bidx . "&img={$i}&split=left");
        echo "<div class='image-row d-flex justify-content-center'>";
        echo "<div class='image-wrapper position-relative d-inline-block'>";
        echo "<img class='lazyload img-fluid lg-item' id=\"image{$i}_left\" data-src=\"{$img_srcL}\" ";
        echo "src=\"data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==\" height=\"250px\" />";
        echo "<div class='img-overlay-page text-white small'>" . ($i + 1) . "L / {$total}</div>";
        echo "</div>";
        echo "</div>";
    }
}

echo "</div>";

if (isset($zip) && $zip instanceof ZipArchive) {
    try {
        $zip->close();
    } catch (Throwable $e) {
        // 이미 닫혔거나 초기화되지 않은 경우 무시
    }
    unset($zip);
    gc_collect_cycles();
}

						$image_counter=$image_counter-1;
               ?>
            </p>
<?php
}
?>
</div>
<?php if($type != "video"): ?>
<script type="text/javascript">
var bookmark = "image0";
var bright_value = 1;
var contrast_value = 1;
var img_counter = 0;
var scroll_counter = 0;
<?php
if($type == "pdf"){
	$image_counter = 400;
}
?>
function bright_up() {
	if(bright_counter < 5){
	bright_counter = bright_counter + 1;
	change_bright();
	}
}
function bright_down() {
	if(bright_counter > -5){
	bright_counter = bright_counter - 1;
	change_bright();
	}
}
function bright() {
	bright_counter = 0;
	change_bright();
}
function change_bright(){
	bright_value = "brightness(" + (1 + (bright_counter * 0.04)) + ")";
	contrast_value = "contrast(" + (1 + (bright_counter * 0.1)) + ")";
	$(".img-fluid").css('-webkit-filter', bright_value);
	$(".img-fluid").css('-webkit-filter', contrast_value);
	$(".img-fluid").css('filter', bright_value);
	$(".img-fluid").css('filter', contrast_value);
	$(".lg-image").css('-webkit-filter', bright_value);
	$(".lg-image").css('-webkit-filter', contrast_value);
	$(".lg-image").css('filter', bright_value);
	$(".lg-image").css('filter', contrast_value);
  	document.getElementById("info").value = _vi18n.brightness + " " + bright_counter;
}
$('#lightgallery').on('onAfterOpen.lg',function(event){
    change_bright();
});
$('#lightgallery').on('onAfterSlide.lg',function(event){
    change_bright();
});

function save_bookmark() {
  	document.getElementById("info").value = _vi18n.saving;
  	try{ sessionStorage.setItem('mycomix_marks_dirty','1'); }catch(e){} try{ sessionStorage.setItem('mc_lib_dirty','1'); sessionStorage.setItem('mc_lib_dirty_b','1'); }catch(e){} /* ★ (2026-11-02 01:43) 서재 화면 읽은 기록 바로 반영(series.php = mc_lib_dirty · ★ 03:04 browse.php = mc_lib_dirty_b — 따로 써서 한쪽이 먼저 지워도 다른 쪽이 갱신) */
<?php
if ($mode == "toon"){
?>
// ✅ 점진 로딩(지연/무한스크롤) 대응: 고정 범위 루프 대신 현재 DOM에 존재하는 이미지만
//    스캔해 뷰포트 상단에 걸친 마지막 이미지를 현재 위치로 기록(없는 요소 .position() 크래시 방지)
bookmark = (function(){
	var imgs = document.querySelectorAll("#lightgallery img[id^='image']");
	var cur = "image0";
	// ★ (2026-11-02 09:00) 위 막대 아래를 기준으로(자동 저장과 같은 기준) — 막대에 가려진 쪽 경계에서 한 쪽 앞을 저장하던 문제
	var _bt = 0; try { var _bb = document.getElementById('mcvBar'); if (_bb) _bt = Math.max(0, _bb.getBoundingClientRect().bottom); } catch(e){}
	var _rowT = null, _curN = -1; // ★ (2026-11-09 11:40) 펼침(1|2)은 같은 줄에서 앞 쪽 번호로 — 위 막대 쪽 표시(js/viewer-bar.js current())와 같게. 전엔 뒤 쪽이 저장돼 북모드 이어 보기에서 한 쪽 건너뜀
	for (var k = 0; k < imgs.length; k++){
		var r = imgs[k].getBoundingClientRect();
		if (r.top <= _bt + 5) {
			var mm = imgs[k].id.match(/image(\d+)/i);
			if (mm && _rowT !== null && Math.abs(r.top - _rowT) < 1 && parseInt(mm[1], 10) > _curN) continue;
			if (mm) { cur = "image" + mm[1]; _rowT = r.top; _curN = parseInt(mm[1], 10); }
		} else {
			break;
		}
	}
	return cur;
})();
var bookmarkBaseUrl = <?php echo js("bookmark.php?viewer=" . $mode . "&page_order=" . ($pageorder['page_order'] ?? '0') . "&file=" . encode_url($getfile) . "&bidx=" . $current_bidx . "&bookmark="); ?>;
$.get(bookmarkBaseUrl + bookmark, function( data ) {
  	document.getElementById("info").value = data;
});
<?php
} elseif ($mode == "book") {
?>
for (var i = 0; i <= <?php echo $image_counter; ?>; i++) {
	var j = <?php echo $image_counter; ?> - i;
	var scroll_top = $(this).scrollTop();
	var scroll_image = $("#image"+j).position().top;
	if (scroll_top > scroll_image) {
		scroll_counter = j;
		break;
	}
}
	if(scroll_counter > img_counter){
		bookmark = "image" + scroll_counter;
	} else {
		bookmark = "image" + img_counter;
		location.replace('#' + bookmark);
	}
var bookmarkBaseUrl = <?php echo js("bookmark.php?viewer=" . $mode . "&page_order=" . ($pageorder['page_order'] ?? '0') . "&file=" . encode_url($getfile) . "&bidx=" . $current_bidx . "&bookmark="); ?>;
$.get(bookmarkBaseUrl + bookmark, function( data ) {
  	document.getElementById("info").value = data;
});
<?php
}
if($type == "pdf"){
?>
bookmark = "pdf_" + current_page;

var pdfBookmarkUrl = <?php echo js("bookmark.php?viewer=pdf&page_order=pdf&file=" . encode_url($getfile) . "&bidx=" . $current_bidx . "&bookmark="); ?>;
$.get(pdfBookmarkUrl + bookmark + (typeof totalPages === 'number' && totalPages > 0 ? "&total=" + totalPages : ""), function( data ) { // ★ (2026-11-02 20:50) 총 쪽수도 — 없으면 서재 진행률이 다음 자동 저장 전까지 '모름'으로 덮였음
document.getElementById("info").value = data;
});
<?php
}
?>

}

<?php
if ($mode == "book"){
?>
// ★ (2026-10-29) 현재 위치 추적: DOMSubtreeModified(최신 Chrome에서 더 이상 발생 안 함 → 항상 image0 저장되던 문제)
//   대신 lightGallery의 onAfterSlide 이벤트 사용. 슬라이드 번호가 아니라 "페이지 번호"로 기록
//   (세로분할은 슬라이드가 페이지의 2배라 그대로 저장하면 이어보기 위치가 2배로 어긋났음)
$('#lightgallery').on('onAfterSlide.lg', function(event, prevIndex, index) {
	var idx = parseInt(index, 10);
	if (isNaN(idx) || idx < 0) return;
	var sp = window._mcBook && window._mcBook.slidePage;
	img_counter = (sp && sp[idx] !== undefined) ? sp[idx] : idx;
	if (sp && idx === sp.length - 1 && typeof window._mcBook.lastPage === 'number') img_counter = window._mcBook.lastPage; // ★ (2026-11-02 21:22) 마지막 슬라이드면 그 마지막 쪽
});
<?php
}
?>

const options = { 
    rootMargin: '300px 0px',
    threshold: 0.01
};

// ✅ [B단계1] 캡처한 이미지 크기를 서버에 저장(sendBeacon). 비어있으면 아무것도 안 함. 실패 무시(무회귀).
function sendDims(){
	try {
		if (!window._mcDims) return;
		if (!Object.keys(window._mcDims).length) return;
		var _durl = <?php echo js("bookmark.php?mode=save_dims&file=" . encode_url($getfile) . "&bidx=" . $current_bidx); ?>;
		var _body = JSON.stringify(window._mcDims);
		if (navigator && navigator.sendBeacon) { navigator.sendBeacon(_durl, new Blob([_body], {type:'application/json'})); }
	} catch(e){}
}
// ★ (2026-11-05 15:40) 위 막대에서 모드(툰↔북)·보기 방식을 바꿔 이 화면을 떠나는 중이면, 떠날 때 자동 저장도 '바꾼 값'으로(펜닐 제보 — 모드 기억 '책마다')
//   전에는 바꾼 값을 저장한 직후 이 화면의 자동 저장(예전 모드·보기 방식)이 뒤따라 덮어써 다음에 열 때 예전 모드로 열렸음
//   window.mcAsViewer('toon'·'book')·window.mcAsOrder('0'~'4')는 js/viewer-bar.js·아래 북모드 닫기가 이동 직전에만 정함(정해진 값만 씀)
function mcAsUrl(u){
	try{
		var v = window.mcAsViewer, o = window.mcAsOrder;
		if (v === 'toon' || v === 'book') u = u.replace(/([?&]viewer=)[^&]*/, '$1' + v);
		if (typeof o === 'string' && /^[0-4]$/.test(o)) u = u.replace(/([?&]page_order=)[^&]*/, '$1' + o);
	}catch(e){}
	return u;
}
function sendAutosave(u){
	// ✅ 페이지 이탈/숨김 시점 호출: sendBeacon으로 확실히 전송(비동기 $.get가 이탈로 취소되는 race 방지).
	//    sendBeacon은 같은 출처라 세션 쿠키를 함께 보내고, bookmark.php의 autosave는 토큰 없이 세션만으로 허용됨.
	//    미지원 브라우저 또는 큐잉 실패(false) 시에만 기존 $.get로 폴백 → 무회귀(최악의 경우 현재 동작과 동일).
	try{
		if (navigator && navigator.sendBeacon && navigator.sendBeacon(u)) { return; }
	}catch(e){}
	$.get(u, function(data){ try{ document.getElementById("info").value = data; }catch(e){} });
}
function autosave(){	
	try{ sessionStorage.setItem('mycomix_marks_dirty','1'); }catch(e){} try{ sessionStorage.setItem('mc_lib_dirty','1'); sessionStorage.setItem('mc_lib_dirty_b','1'); }catch(e){} /* ★ (2026-11-02 01:43) 서재 화면 읽은 기록 바로 반영(series.php = mc_lib_dirty · ★ 03:04 browse.php = mc_lib_dirty_b — 따로 써서 한쪽이 먼저 지워도 다른 쪽이 갱신) */
<?php if ($type != "pdf" && $mode == "toon"){ // ★ (2026-10-30) PDF 는 아래 PDF 분기만 — 같은 함수 안 const bookmark 때문에 ReferenceError 로 자동저장·크기 저장이 늘 실패했음 ?>
	// ✅ 점진 로딩(지연/무한스크롤) 대응: 고정 범위 루프 대신 현재 DOM에 존재하는 이미지만
	//    스캔해 뷰포트 상단에 걸친 마지막 이미지를 현재 위치로 기록(없는 요소 .position() 크래시 방지)
	var _frac = -1; // ★ (2026-11-02 01:01) 그 그림 안에서 얼마나 내려왔는지(천분율) — 이어 보기가 그림 맨 위가 아니라 보던 줄에서 열리게
	bookmark = (function(){
		var imgs = document.querySelectorAll("#lightgallery img[id^='image']");
		var cur = "image0";
		// ★ (2026-11-01 23:42) 위 막대(viewer_bar)가 있으면 그 아래를 화면 맨 위로 봄 — 막대 바로 아래 그림을 보고 있어도 앞 그림으로 저장돼 이어 보기가 한 쪽 앞에서 열렸음(막대 없으면 예전과 같음)
		var _top0 = 0; try { var _bar = document.getElementById('mcvBar'); if (_bar) _top0 = Math.max(0, _bar.getBoundingClientRect().bottom); } catch(e){}
		var _rowT = null, _curN = -1; // ★ (2026-11-09 11:40) 펼침(1|2)은 같은 줄에서 앞 쪽 번호로(위 책갈피 저장과 같은 이유)
		for (var k = 0; k < imgs.length; k++){
			var r = imgs[k].getBoundingClientRect();
			if (r.top <= _top0 + 5) {
				var mm = imgs[k].id.match(/image(\d+)/i);
				if (mm && _rowT !== null && Math.abs(r.top - _rowT) < 1 && parseInt(mm[1], 10) > _curN) continue;
				if (mm) {
					cur = "image" + mm[1]; _rowT = r.top; _curN = parseInt(mm[1], 10);
					// 다 불러온 그림만(자리표시 높이로 재면 엉뚱한 값) — 막대 아래 줄이 그림 안 어디인지
					_frac = (imgs[k].classList.contains('loaded') && r.height > 20) ? Math.max(0, Math.min(999, Math.round((_top0 - r.top) / r.height * 1000))) : -1; // -1 = 모름(안 보냄)
					if (/_(left|right)$/i.test(imgs[k].id)) _frac = -1; // ★ (2026-11-02 02:48) 세로 분할(좌·우 반쪽)은 어느 반쪽 기준인지 복원과 어긋날 수 있어 예전처럼 쪽 맨 위로
				}
			} else {
				break;
			}
		}
		// ★ (2026-11-02 20:21) 맨 아래까지 내려 마지막 쪽이 다 보이면 마지막 쪽으로 — 짧은 그림·펼침은 끝 화면에 여러 쪽이 보여
		//   맨 위 쪽만 저장하면 끝까지 읽어도 서재에서 '다 읽음'이 안 됐음(js/viewer-bar.js 끝 판정과 같은 조건)
		try {
			var _se = document.scrollingElement || document.documentElement, _lb = document.getElementById('lightgallery');
			var _tot = _lb ? (parseInt(_lb.getAttribute('data-total'), 10) || 0) : 0;
			if (_tot > 0 && (window.innerHeight + (window.scrollY || _se.scrollTop)) >= _se.scrollHeight - 60) {
				var _n = _tot - 1, _ids = ['image' + _n, 'image' + _n + '_left', 'image' + _n + '_right'], _seen = false, _ok = true;
				for (var q = 0; q < _ids.length; q++) {
					var _el = document.getElementById(_ids[q]); if (!_el) continue;
					var _r = _el.getBoundingClientRect();
					if (!_el.classList.contains('loaded') || _r.height <= 20 || _r.bottom > window.innerHeight + 60) { _ok = false; break; }
					_seen = true;
				}
				if (_seen && _ok && cur !== 'image' + _n) { cur = 'image' + _n; _frac = -1; }
			}
		} catch(e){}
		return cur;
	})();
	var autosaveUrl = mcAsUrl(<?php echo js("bookmark.php?mode=autosave&viewer=" . $mode . "&page_order=" . ($pageorder['page_order'] ?? '0') . "&file=" . encode_url($getfile) . "&bidx=" . $current_bidx . "&bookmark="); ?>); // ★ (2026-11-05 15:40) mcAsUrl — 위 설명
	sendAutosave(autosaveUrl + bookmark + (_frac >= 0 ? "&f=" + _frac : ""));
<?php } elseif($type != "pdf" && $mode == "book") { ?>
	for (var i = 0; i <= <?php echo $image_counter; ?>; i++) {
		var j = <?php echo $image_counter; ?> - i;
		var scroll_top = $(this).scrollTop();
		var scroll_image = $("#image"+j).position().top;
		if (scroll_top > scroll_image) {
			scroll_counter = j;
			break;
		}
	}
	if(scroll_counter > img_counter){
		bookmark = "image" + scroll_counter;
	} else {
		bookmark = "image" + img_counter;
		location.replace('#' + bookmark);
	}
	var autosaveUrl = mcAsUrl(<?php echo js("bookmark.php?mode=autosave&viewer=" . $mode . "&page_order=" . ($pageorder['page_order'] ?? '0') . "&file=" . encode_url($getfile) . "&bidx=" . $current_bidx . "&bookmark="); ?>); // ★ (2026-11-05 15:40) mcAsUrl — 위 설명
	sendAutosave(autosaveUrl + bookmark);
<?php } ?>

<?php if($type == "pdf") { ?>
	// ★ (2026-11-02 01:01) PDF 는 창이 아니라 #pdf-pages-container 안에서 스크롤 — 예전엔 window.scrollY(늘 0)로 재서 늘 1쪽,
	//   게다가 bookmark.php 가 'pdf_N' 을 'image0' 으로 바꿔 저장해 PDF 이어 보기가 늘 처음이었음.
	//   → 보기 칸 맨 위 줄이 걸친 쪽 + 그 쪽 안 위치(천분율)를 저장(window.mcPdfHere — 아래 PDF 스크립트)
	if (typeof window.mcPdfHere !== 'function') return;
	const _ph = window.mcPdfHere();
	if (!_ph) return; // 아직 PDF 를 못 읽었으면 저장 안 함(예전 기록을 1쪽으로 덮지 않게)
	var pdfAutosaveUrl = <?php echo js("bookmark.php?mode=autosave&viewer=pdf&page_order=pdf&file=" . encode_url($getfile) . "&bidx=" . $current_bidx . "&bookmark="); ?>;
	// ★ (2026-11-02 20:22) 보기 칸 맨 아래까지 내렸으면 마지막 쪽으로 — 휴대폰 세로처럼 끝 화면에 2쪽 넘게 보이면 맨 위 쪽만 저장돼 '다 읽음'이 안 됐음
	//   (쪽 안 위치는 마지막 쪽 기준으로 다시 재서, 다시 열어도 같은 자리)
	try {
		var _pc = document.getElementById('pdf-pages-container'), _lw = _ph.n ? document.getElementById('page_wrapper_' + _ph.n) : null;
		if (_pc && _lw && _ph.page < _ph.n && _pc.scrollTop + _pc.clientHeight >= _pc.scrollHeight - 2) {
			var _lr = _lw.getBoundingClientRect(), _ct = _pc.getBoundingClientRect().top;
			if (_lr.height > 20 && _lr.bottom <= _pc.getBoundingClientRect().bottom + 2) {
				_ph.page = _ph.n;
				_ph.f = (_lr.top < _ct) ? Math.max(0, Math.min(999, Math.round((_ct - _lr.top) / _lr.height * 1000))) : 0;
			}
		}
	} catch(e){}
	sendAutosave(pdfAutosaveUrl + "pdf_" + _ph.page + "&f=" + _ph.f + (_ph.n ? "&total=" + _ph.n : "")); // ★ (2026-11-02 20:10) 총 쪽수도 — PDF 는 쪽수 캐시가 없어 서재 진행률이 늘 1%·다 읽음이 안 됐음
<?php } ?>
}

// ✅ 사생활 보호 복귀용: 현재 toon 스크롤 위치를 "#imageN" 형태로 반환(autosave와 동일한 상단 이미지 판별).
//    뷰어 URL 자체는 바꾸지 않고, privacy_shield가 blank 복귀 URL에 이 위치만 실어 보내도록 제공.
window.myComixCurrentHash = function(){
	try {
<?php if ($type != "pdf" && $mode == "toon"){ // ★ (2026-11-02 01:01) PDF 는 아래 분기(PDF 도 주소가 mode=toon 이라 예전엔 여기로 와서 늘 빈 값) ?>
		var imgs = document.querySelectorAll("#lightgallery img[id^='image']");
		var cur = ""; var off = 0;
		for (var k = 0; k < imgs.length; k++){
			var r = imgs[k].getBoundingClientRect();
			if (r.top <= 5){ var mm = imgs[k].id.match(/image(\d+)/i); if (mm){ cur = "image" + mm[1]; off = Math.max(0, Math.round(-r.top)); } }
			else break;
		}
		// 문서 맨 아래(끝페이지)인지: 끝이면 e 플래그를 붙여 복원 시 '바닥 고정'으로 정확히 복귀
		var _sy = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
		var _docH = Math.max(document.documentElement.scrollHeight || 0, document.body ? document.body.scrollHeight : 0);
		var atBottom = (window.innerHeight + _sy) >= (_docH - 4);
		return cur ? ("#" + cur + (off > 0 ? ("o" + off) : "") + (atBottom ? "e" : "")) : "";
<?php } elseif ($type == "pdf") { // ★ (2026-11-02 01:01) PDF 도 사생활 보호 복귀 때 보던 쪽·줄로(예전엔 위치 없이 돌아와 1쪽) ?>
		var _ph = (typeof window.mcPdfHere === 'function') ? window.mcPdfHere() : null;
		return _ph ? ("#pdf_" + _ph.page + (_ph.f > 0 ? ("f" + _ph.f) : "")) : "";
<?php } elseif ($mode == "book") { // ★ (2026-11-02 09:58) 북모드도 지금 보는 쪽으로 복귀(전에는 처음 연 주소의 쪽으로 돌아갔음) ?>
		return (typeof img_counter === 'number' && img_counter >= 0) ? ("#image" + img_counter) : ""; // 1쪽(0)도 그 쪽으로
<?php } else { ?>
		return "";
<?php } ?>
	} catch(e){ return ""; }
};

$(window).on("beforeunload", function () {
	autosave();
	try{ sendDims(); }catch(e){}
});
// ✅ [B단계1] 모바일 대비 크기 전송 훅(백그라운드/이탈). 실패 무시(무회귀).
document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'hidden'){ try{ sendDims(); }catch(e){} } });
window.addEventListener('pagehide', function(){ try{ sendDims(); }catch(e){} });

window.addEventListener("visibilitychange", function(e) {
    if (document.visibilityState == 'hidden') {
		autosave();
    }
});
</script>
<?php endif; // if($type != "video") ?>

<button id="scrollTopBtn" title="<?php echo __h('viewer_to_top'); ?>" style="display:none;">
  ▲
</button>

<script>
  const scrollTopBtn = document.getElementById("scrollTopBtn");

  window.onscroll = function () {
    if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
      scrollTopBtn.style.display = "block";
    } else {
      scrollTopBtn.style.display = "none";
    }
  };

  scrollTopBtn.onclick = function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
</script>

<?php /* ★ (2026-11-08 전체 점검 fix71) crypto-js(4.2.0 미만 — CVE-2023-46233 해당 버전)는 불러오기만 하고 CryptoJS 를 어디서도 쓰지 않아(원본부터) 불러오지 않음 — 파일(js/crypto-js.min.js)은 그대로 둠 */ ?>
<?php if ($type !== "pdf" && $type !== "video") { ?>
<script>

document.addEventListener("DOMContentLoaded", function () {
  const container = document.getElementById("lightgallery");
  if (!container) {
    console.error("❌ container 없음!");
    return;
  }

  const total = parseInt(container.dataset.total);
  const file = container.dataset.file;
  const pageOrder = container.dataset.pageOrder || "0";
  // ★ (2026-10-29) 세로분할 페이지별 판정(1=분할, 0=1장, null=판정불가→좌/우 2장). 파싱 실패 시 빈 배열(기존 동작)
  let splitMap = [];
  try { splitMap = JSON.parse(container.dataset.splitMap || "[]"); if (!Array.isArray(splitMap)) splitMap = []; } catch (e) { splitMap = []; }
  const bidx = <?php echo $current_bidx; ?>;

  let currentIndex = 0;
  const imgs = container.querySelectorAll("img[id^='image']");
  if (imgs.length > 0) {
    const last = imgs[imgs.length - 1].id;
    currentIndex = parseInt(last.replace("image", ""), 10) + 1;
  }

  let loading = false;
  let lazyImageObserver = null;
  let scrollTriggerObserver = null;

  const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  const isSlowConnection = connection && ['slow-2g', '2g', '3g'].includes(connection.effectiveType);
  
  const MAX_CONCURRENT = isSlowConnection ? 2 : 4;
  const IMAGE_TIMEOUT = isSlowConnection ? 15000 : 10000;

  console.log(`📶 네트워크: ${connection?.effectiveType || 'unknown'}, 동시: ${MAX_CONCURRENT}`);

  const loadQueue = [];
  const loadingImages = new Map();
  const imageStatus = new Map();
  // ★ (2026-11-02 09:59) 세로 분할(image3_left·image3_right)은 반쪽이 같은 쪽 번호라 '로딩 중'·대기열을 쪽 번호로만 보면 두 번째 반쪽이 버려져 3초 복구 루프로만 떴음
  //   → 로딩 중·대기열 중복은 그림 요소(id)로 보고, 앞 쪽 순서 확인은 전처럼 쪽 번호로
  const idStatus = new Map();
  let activeLoads = 0;

  function loadLazyImage(img, force) {
    if (!img || !img.dataset.src) return;
    
    const imgIndex = parseInt(img.id.replace("image", ""), 10);
    
    if (idStatus.get(img.id) === 'loading' || img.classList.contains('loaded')) {
      return;
    }

    if (!force && imgIndex > 0) {
      const prevStatus = imageStatus.get(imgIndex - 1);
      if (prevStatus !== 'loaded' && prevStatus !== 'failed') {
        if (!loadQueue.some(item => item.img === img)) {
          loadQueue.push({ img, index: imgIndex });
        }
        return;
      }
    }

    if (activeLoads >= MAX_CONCURRENT) {
      if (!loadQueue.some(item => item.img === img)) {
        loadQueue.push({ img, index: imgIndex });
      }
      return;
    }

    startImageLoad(img, imgIndex);
  }

  function startImageLoad(img, imgIndex, retryCount = 0) {
    if (idStatus.get(img.id) === 'loading') return;
    
    activeLoads++;
    idStatus.set(img.id, 'loading');
    if (imageStatus.get(imgIndex) !== 'loaded') imageStatus.set(imgIndex, 'loading'); // 다른 반쪽이 이미 떴으면 그대로(다음 쪽을 막지 않게)
    
    console.log(`🖼️ image${imgIndex} 로딩 (재시도:${retryCount})`);

    const timeoutId = setTimeout(() => {
      if (!img.classList.contains('loaded')) {
        handleImageError(img, imgIndex, retryCount);
      }
    }, IMAGE_TIMEOUT);

    loadingImages.set(img.id, { img, timeoutId, startTime: Date.now() });

    const tempImg = new Image();
    
    tempImg.onload = function() {
      clearTimeout(timeoutId);
      
      img.src = tempImg.src;
      img.classList.add('loaded');
      img.classList.remove('lazyload');
      imageStatus.set(imgIndex, 'loaded');
      idStatus.set(img.id, 'loaded');
      // ✅ [B단계1] 로드된 실제 이미지 크기 캡처(높이 예약용). 추가비용 거의 0, 실패 무시.
      //    단일모드(pageOrder 0)의 깨끗한 인덱스(^image\d+$)만 저장 → 세로분할 반쪽크롭이 캐시를 오염시키는 것 방지.
      try {
        if ((typeof pageOrder === 'undefined' || pageOrder === '0') && /^image\d+$/.test(img.id)
            && tempImg.naturalWidth > 0 && tempImg.naturalHeight > 0) {
          (window._mcDims = window._mcDims || {})[imgIndex] = [tempImg.naturalWidth, tempImg.naturalHeight];
        }
      } catch(e){}
      loadingImages.delete(img.id);
      activeLoads--;
      
      console.log(`✅ image${imgIndex} 완료`);
      
      processQueue();
      triggerNextImage(imgIndex);
    };

    tempImg.onerror = function() {
      clearTimeout(timeoutId);
      handleImageError(img, imgIndex, retryCount);
    };

    const cacheBuster = retryCount > 0 ? `&t=${Date.now()}` : '';
    tempImg.src = img.dataset.src + cacheBuster;
  }

  function handleImageError(img, imgIndex, retryCount) {
    loadingImages.delete(img.id);
    activeLoads--;

    if (retryCount < 3) {
      console.log(`🔄 image${imgIndex} 재시도 ${retryCount + 1}/3`);
      if (imageStatus.get(imgIndex) !== 'loaded') imageStatus.delete(imgIndex);
      idStatus.delete(img.id);
      
      setTimeout(() => {
        startImageLoad(img, imgIndex, retryCount + 1);
      }, 1000 * (retryCount + 1));
    } else {
      img.classList.add('load-failed');
      if (imageStatus.get(imgIndex) !== 'loaded') imageStatus.set(imgIndex, 'failed');
      idStatus.set(img.id, 'failed');
      console.error(`💀 image${imgIndex} 최종 실패`);
      
      processQueue();
      triggerNextImage(imgIndex);
    }
  }

  function triggerNextImage(currentImgIndex) {
    const nextIndex = currentImgIndex + 1;
    // ★ (2026-11-02 09:59) 세로 분할이면 같은 쪽의 다른 반쪽·다음 쪽 반쪽들도(전에는 image{n+1} 만 찾아 분할 모드에서는 아무것도 안 했음)
    [`image${nextIndex}`, `image${currentImgIndex}_left`, `image${currentImgIndex}_right`, `image${nextIndex}_left`, `image${nextIndex}_right`].forEach(function (id) {
      const nextImg = document.getElementById(id);
      if (nextImg && !nextImg.classList.contains('loaded')) {
        setTimeout(() => {
          loadLazyImage(nextImg);
        }, 100);
      }
    });
  }

  function processQueue() {
    if (loadQueue.length === 0) return;
    
    loadQueue.sort((a, b) => a.index - b.index);
    
    while (loadQueue.length > 0 && activeLoads < MAX_CONCURRENT) {
      const item = loadQueue[0];
      const imgIndex = item.index;
      
      const prevStatus = imgIndex > 0 ? imageStatus.get(imgIndex - 1) : 'loaded';
      if (prevStatus === 'loaded' || prevStatus === 'failed') {
        loadQueue.shift();
        loadLazyImage(item.img);
      } else {
        break;
      }
    }
  }

  if ('IntersectionObserver' in window) {
    const rootMargin = isSlowConnection ? "600px 0px" : "1000px 0px";
    
    lazyImageObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          const img = entry.target;
          if (!img.classList.contains('loaded') && img.dataset.src) {
            loadLazyImage(img);
          }
        }
      });
    }, { rootMargin: rootMargin, threshold: 0.01 });

    scrollTriggerObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          appendImages();
          scrollTriggerObserver.unobserve(entry.target);
        }
      });
    }, { rootMargin: "1000px 0px", threshold: 0.1 });
  }

  function observeLazyImage(img) {
    if (lazyImageObserver) {
      lazyImageObserver.observe(img);
    } else {
      loadLazyImage(img);
    }
  }

  function createImageElement(i, order = "", split = "") {
    const wrapper = document.createElement("div");
    wrapper.className = "image-wrapper position-relative d-inline-block";

    const img = document.createElement("img");
    img.className = "lazyload img-fluid lg-item";
    // ✅ 분할 모드에서는 고유 ID 생성 (같은 이미지의 left/right 구분)
    img.id = split ? `image${i}_${split}` : `image${i}`;

    const file = container.dataset.file;
    let src = `viewer.php?file=${file}&bidx=${bidx}&img=${i}`;
    if (order) src += `&order=${order}`;
    if (split) src += `&split=${split}`; // ✅ 분할 파라미터 추가

    img.dataset.src = src;
    img.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==";
    img.height = 250;

    const overlay = document.createElement("div");
    overlay.className = "img-overlay-page text-white small";
    // ✅ 분할 모드에서는 페이지 표시 조정
    overlay.innerText = split ? `${i + 1}${split === 'left' ? 'L' : 'R'} / ${total}` : `${i + 1} / ${total}`;

    wrapper.appendChild(img);
    wrapper.appendChild(overlay);

    return wrapper;
  }

  function appendImages() {
    if (loading || currentIndex >= total) return;
    loading = true;

    const fragment = document.createDocumentFragment();
    const batchSize = isSlowConnection ? 3 : 5;

    if (pageOrder === "0") {
      for (let i = 0; i < batchSize && currentIndex < total; i++) {
        const row = document.createElement("div");
        row.className = "image-row d-flex justify-content-center";
        row.appendChild(createImageElement(currentIndex));
        fragment.appendChild(row);
        currentIndex++;
      }
    } else if (pageOrder === "1") {
      for (let i = 0; i < batchSize && currentIndex < total; i += 2) {
        const row = document.createElement("div");
        row.className = "image-row d-flex justify-content-center";
        if (currentIndex < total) row.appendChild(createImageElement(currentIndex, "left"));
        if (currentIndex + 1 < total) row.appendChild(createImageElement(currentIndex + 1, "right"));
        currentIndex += 2;
        fragment.appendChild(row);
      }
    } else if (pageOrder === "2") {
      if (currentIndex % 2 !== 0) currentIndex++;
      for (let pair = 0; pair < Math.ceil(batchSize/2) && currentIndex < total; pair++) {
        const row = document.createElement("div");
        row.className = "image-row d-flex justify-content-center";
        const left = currentIndex;
        const right = currentIndex + 1;
        if (right < total) row.appendChild(createImageElement(right, "right"));
        if (left < total) row.appendChild(createImageElement(left, "left"));
        currentIndex += 2;
        fragment.appendChild(row);
      }
    } else if (pageOrder === "3") {
      // ✅ 세로 분할 (좌→우): 각 이미지를 left, right 순서로 표시
      for (let i = 0; i < batchSize && currentIndex < total; i++) {
        if (splitMap[currentIndex] === 0) {
          // ★ (2026-10-29) 분할 대상 아님(세로 페이지) → 원본 1장
          const rowOne = document.createElement("div");
          rowOne.className = "image-row d-flex justify-content-center";
          rowOne.appendChild(createImageElement(currentIndex));
          fragment.appendChild(rowOne);
          currentIndex++;
          continue;
        }
        // Left half
        const rowLeft = document.createElement("div");
        rowLeft.className = "image-row d-flex justify-content-center";
        rowLeft.appendChild(createImageElement(currentIndex, "", "left"));
        fragment.appendChild(rowLeft);
        
        // Right half
        const rowRight = document.createElement("div");
        rowRight.className = "image-row d-flex justify-content-center";
        rowRight.appendChild(createImageElement(currentIndex, "", "right"));
        fragment.appendChild(rowRight);
        
        currentIndex++;
      }
    } else if (pageOrder === "4") {
      // ✅ 세로 분할 (우→좌): 각 이미지를 right, left 순서로 표시 (일본 만화 스타일)
      for (let i = 0; i < batchSize && currentIndex < total; i++) {
        if (splitMap[currentIndex] === 0) {
          // ★ (2026-10-29) 분할 대상 아님(세로 페이지) → 원본 1장
          const rowOne = document.createElement("div");
          rowOne.className = "image-row d-flex justify-content-center";
          rowOne.appendChild(createImageElement(currentIndex));
          fragment.appendChild(rowOne);
          currentIndex++;
          continue;
        }
        // Right half first
        const rowRight = document.createElement("div");
        rowRight.className = "image-row d-flex justify-content-center";
        rowRight.appendChild(createImageElement(currentIndex, "", "right"));
        fragment.appendChild(rowRight);
        
        // Left half
        const rowLeft = document.createElement("div");
        rowLeft.className = "image-row d-flex justify-content-center";
        rowLeft.appendChild(createImageElement(currentIndex, "", "left"));
        fragment.appendChild(rowLeft);
        
        currentIndex++;
      }
    }

    // ★ (2026-11-02 14:27) 붙이기 전에 잡아 둠 — 붙인 뒤 fragment 는 비어서 새 그림이 지연 로딩·무한 스크롤 감시에 안 걸렸음
    const newImgs = fragment.querySelectorAll("img.lazyload");
    const lastImg = newImgs.length ? newImgs[newImgs.length - 1] : null;
    container.appendChild(fragment);
    newImgs.forEach(observeLazyImage);

    loading = false;

    if (lastImg && scrollTriggerObserver) {
      scrollTriggerObserver.observe(lastImg);
    }
  }

  container.querySelectorAll("img.lazyload").forEach(observeLazyImage);

  let scrollTimeout = null;
  window.addEventListener("scroll", function () {
    if (scrollTimeout) return;
    
    scrollTimeout = setTimeout(() => {
      scrollTimeout = null;
      
      document.querySelectorAll("img.lazyload:not(.loaded)").forEach((img) => {
        const rect = img.getBoundingClientRect();
        if (rect.top < window.innerHeight + 300 && rect.bottom > -100) {
          loadLazyImage(img);
        }
      });

      if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 600)) {
        appendImages();
      }
    }, 100);
  });

  if (connection) {
    connection.addEventListener('change', function() {
      console.log(`📶 변경: ${connection.effectiveType}`);
    });
  }

  setInterval(() => {
    const pending = document.querySelectorAll('img.lazyload:not(.loaded):not(.load-failed)').length;
    const failed = document.querySelectorAll('img.load-failed').length;
    
    if (activeLoads > 0 || pending > 0 || failed > 0 || loadQueue.length > 0) {
      console.log(`📊 활성:${activeLoads} 큐:${loadQueue.length} 대기:${pending} 실패:${failed}`);
    }

    const allImages = Array.from(document.querySelectorAll('img[id^="image"]'));
    for (let i = 0; i < allImages.length; i++) {
      const img = allImages[i];
      const imgIndex = parseInt(img.id.replace("image", ""), 10);
      const status = imageStatus.get(imgIndex);
      
      if (!img.classList.contains('loaded') && idStatus.get(img.id) !== 'loading' && (status !== 'loading' || !/_(left|right)$/.test(img.id))) { // ★ (2026-11-02 09:59) 반쪽은 요소별로
        const rect = img.getBoundingClientRect();
        const isVisible = rect.top < window.innerHeight + 600 && rect.bottom > -300;
        
        if (isVisible) {
          const prevStatus = imgIndex > 0 ? imageStatus.get(imgIndex - 1) : 'loaded';
          
          if (imgIndex === 0 || prevStatus === 'loaded' || prevStatus === 'failed') {
            console.log(`🔧 복구: image${imgIndex}`);
            img.classList.remove('load-failed');
            if (imageStatus.get(imgIndex) !== 'loaded') imageStatus.delete(imgIndex);
            idStatus.delete(img.id);
            loadLazyImage(img);
          }
        }
      }
    }

    processQueue();
  }, 3000);

  setTimeout(() => {
    if (currentIndex < total) {
      appendImages();
      
      const firstImg = document.getElementById('image0');
      if (firstImg && !firstImg.classList.contains('loaded')) {
        console.log('🚀 첫 이미지 로딩');
        loadLazyImage(firstImg);
      }
    }
  }, 100);

  if (/iP(hone|ad|od)/i.test(navigator.userAgent)) {
    setTimeout(() => {
      document.querySelectorAll("img.lazyload:not(.loaded)").forEach((img) => {
        const rect = img.getBoundingClientRect();
        if (rect.top < window.innerHeight + 200) {
          loadLazyImage(img);
        }
      });
    }, 1000);
  }

if (file) {
    setTimeout(() => {
        // ✅ warmup.php는 세션에서 user_id를 가져오므로 URL 파라미터 불필요
        // ✅ file은 이미 encode_url()로 인코딩된 상태이므로 encodeURIComponent 제거
        // ✅ credentials: 'same-origin'으로 세션 쿠키 전송 보장
        fetch("warmup.php?file=" + file + "&bidx=" + bidx, { credentials: 'same-origin' })
            .then(res => res.text())
            .catch(err => console.warn("[warmup] failed", err));
    }, 100);
    
    // ✅ 백그라운드 프리페치 - warmup 이후 나머지 이미지 미리 캐싱
    (function backgroundPrefetch() {
        const totalImages = parseInt(container?.dataset?.total) || 0;
        if (totalImages <= 10) return; // 10개 이하면 warmup으로 충분
        
        let prefetchIndex = 10; // warmup이 처리한 다음부터
        const prefetchedSet = new Set();
        
        function prefetchNext() {
            // 이미 로딩된 이미지는 건너뛰기
            while (prefetchIndex < totalImages && 
                   (prefetchedSet.has(prefetchIndex) || imageStatus.get(prefetchIndex) === 'loaded')) {
                prefetchIndex++;
            }
            
            if (prefetchIndex >= totalImages) {
                console.log('📦 백그라운드 프리페치 완료');
                return;
            }
            
            // 현재 활성 로딩이 많으면 대기
            if (activeLoads >= MAX_CONCURRENT) {
                setTimeout(prefetchNext, 500);
                return;
            }
            
            prefetchedSet.add(prefetchIndex);
            
            const img = new Image();
            img.onload = img.onerror = function() {
                // 다음 이미지 프리페치 (간격 두고)
                setTimeout(prefetchNext, 300);
            };
            img.src = `viewer.php?file=${file}&bidx=${bidx}&img=${prefetchIndex}`;
            
            prefetchIndex++;
        }
        
        // 2초 후 백그라운드 프리페치 시작 (초기 로딩 방해 안 함)
        setTimeout(prefetchNext, 2000);
    })();
}

  // ✅ [위치 복원] 인라인 툰: 진입 시 URL의 #imageN 위치로 스크롤.
  //    점진 로딩(요소가 스크롤 시 추가됨)이라 대상 요소가 생길 때까지 appendImages()로 확장 후 스크롤.
  //    레이아웃 변동(상단 이미지 로드로 높이 증가)에 대비해 잠시 재정렬하고, 대상 로드 완료/사용자 조작/
  //    타임아웃 시 종료. 실패해도 최상단에 머무를 뿐이라 무해(fail-safe).
  // (정리됨) 뷰어 위치 localStorage 백업(_vpSave/_vpRead)은 이어보기 자동점프 비활성 이후 아무도 읽지 않아 제거함.
  //          위치 복원은 URL 해시(#imageN)만 사용: 목록 '눌러서 이어보기'·사생활 복귀·저장위치 버튼.

  (function restoreToonPosition(){
    try {
      var m = (window.location.hash || '').match(/image(\d+)(?:o(\d+))?(?:f(\d{1,3}))?(e)?/i); // ★ (2026-11-02 01:01) f = 그림 안 위치(천분율, 이어 보기)
      // ✅ 이어보기 자동점프 비활성: URL 해시(#imageN)가 있을 때만 위치 복원한다(목록의 "눌러서 이어보기"·
      //    사생활 복귀·저장위치 버튼). 파일목록에서 그냥 열 때(해시 없음)는 localStorage 백업으로 자동
      //    점프하지 않고 처음부터 본다. 자동 저장(서버)과 자동저장 목록은 그대로 유지된다.
      if (!m) return;
      var targetIndex = parseInt(m[1], 10);
      var targetOffset = m[2] ? parseInt(m[2], 10) : 0;            // 이미지 내부 픽셀 오프셋(정밀 복원)
      var hasFrac = m[3] !== undefined && m[3] !== '';             // f0 = 그림 맨 위가 막대 바로 아래
      var targetFrac = hasFrac ? Math.min(999, parseInt(m[3], 10)) : 0; // 그림 높이의 천분율 — 위 막대 아래 줄에 맞춤(자동 저장과 같은 기준)
      var atBottom = !!m[4];                                       // 저장 시 문서 맨 아래였음 → 바닥 고정 복원
      if (targetIndex <= 0 && targetOffset <= 0 && targetFrac <= 0 && !atBottom) return;  // 최상단이면 복원 불필요
      if (targetIndex < 0) targetIndex = 0;
      if (typeof total === 'number' && targetIndex >= total) targetIndex = total - 1;

      function findTarget(){
        return document.getElementById('image' + targetIndex)
            || document.getElementById('image' + targetIndex + '_left')
            || document.getElementById('image' + targetIndex + '_right');
      }

      var aborted = false;
      var startTime = Date.now();
      // 복귀 직후 탭 모멘텀/잔여 터치 등 의도치 않은 스크롤로 복원이 즉시 중단되는 것을 막기 위해
      // 처음 600ms 동안의 스크롤은 무시(그 후의 실제 사용자 스크롤이면 중단).
      function onUserScroll(){ if (Date.now() - startTime > 600) aborted = true; }
      window.addEventListener('wheel',     onUserScroll, { passive: true, capture: true });
      window.addEventListener('touchmove', onUserScroll, { passive: true, capture: true });
      window.addEventListener('keydown',   onUserScroll, true);
      function cleanup(){
        window.removeEventListener('wheel',     onUserScroll, true);
        window.removeEventListener('touchmove', onUserScroll, true);
        window.removeEventListener('keydown',   onUserScroll, true);
      }

      var tries = 0;
      var MAX_TRIES = 240;                                          // 240 × 250ms ≈ 최대 60초 가드 (콜드 리로드에서 깊은 위치까지 재정렬할 시간 확보; 짧은시간 케이스는 2틱에 완료돼 무영향)
      var lastHeight = -1;                                          // 바닥 고정 시 문서 높이 안정 판정용
      var stableTicks = 0;                                          // 일반 복원 시 위치 안정(드리프트 종료) 판정용
      var timer = setInterval(function(){
        // 사용자가 직접 스크롤했거나 전체화면 갤러리가 열렸으면 중단(충돌 방지)
        if (aborted || document.querySelector('.lg-outer.lg-visible')) { clearInterval(timer); cleanup(); return; }
        if (++tries > MAX_TRIES) { clearInterval(timer); cleanup(); return; }

        var el = findTarget();
        if (!el) {
          // ✅ 대상이 아직 DOM에 없으면 채운다. 한 틱에 5장씩(appendImages 기본)만 늘리면
          //    image90 같은 깊은 타겟까지 수 초가 걸려 복원이 미완료됨. appendImages는 동기 함수로
          //    loading을 스스로 즉시 해제하므로, 이번 틱에서 타겟이 나타날 때까지 연속 호출해 빠르게 채운다.
          //    (일반 스크롤 로딩 동작은 그대로 — 복원 경로에서만 빠르게 채움. 상한 가드로 무한루프 방지)
          if (typeof appendImages === 'function' && typeof currentIndex === 'number'
              && typeof total === 'number' && currentIndex < total) {
            var _g = 0;
            while (!findTarget() && currentIndex < total && _g < 2000) {
              var _before = currentIndex;
              appendImages();
              if (currentIndex === _before) break;                  // 더 안 늘면 중단(안전)
              _g++;
            }
          }
          el = findTarget();
          if (!el) { clearInterval(timer); cleanup(); return; }     // 끝까지 채워도 없으면 종료
          // 타겟 확보 → 이번 틱에서 아래 정렬/바닥고정 로직으로 계속 진행
        }

        // ✅ 깊은 위치로 점프 시, 순차 로딩 게이트 때문에 대상 이미지가 로드되지 않아
        //    흰 화면이 되는 것을 방지: 대상과 주변 몇 장을 게이트 무시하고 강제 로드.
        //    (loadLazyImage는 loading/loaded면 즉시 반환하므로 매 틱 재호출돼도 무해)
        if (typeof loadLazyImage === 'function') {
          for (var _d = -1; _d <= 3; _d++) {
            var _nb = document.getElementById('image' + (targetIndex + _d))
                   || document.getElementById('image' + (targetIndex + _d) + '_left')
                   || document.getElementById('image' + (targetIndex + _d) + '_right');
            if (_nb) loadLazyImage(_nb, true);
          }
        }

        // ✅ 끝페이지(저장 시 맨 아래) 복원: 꼬리 이미지들을 강제 로드하며 문서 바닥에 고정.
        //    위쪽 이미지가 뒤늦게 로드돼 대상이 밀려 올라가는 것을 막기 위해, 높이가 안정될 때까지
        //    매 틱 바닥으로 스크롤한다.
        if (atBottom) {
          // 끝(바닥) 복원은 문서 끝까지 DOM이 있어야 정확히 바닥에 닿음 → 끝까지 빠르게 채움
          if (typeof appendImages === 'function' && typeof currentIndex === 'number'
              && typeof total === 'number' && currentIndex < total) {
            var _gb = 0;
            while (currentIndex < total && _gb < 2000) {
              var _bb = currentIndex;
              appendImages();
              if (currentIndex === _bb) break;
              _gb++;
            }
          }
          if (typeof loadLazyImage === 'function' && typeof total === 'number') {
            for (var _t = targetIndex; _t < total; _t++) {
              var _te = document.getElementById('image' + _t)
                     || document.getElementById('image' + _t + '_left')
                     || document.getElementById('image' + _t + '_right');
              if (_te) loadLazyImage(_te, true);
            }
          }
          var _dh = Math.max(document.documentElement.scrollHeight || 0, document.body ? document.body.scrollHeight : 0);
          window.scrollTo(0, _dh);                                  // 바닥으로(클램프됨)
          if (el.classList.contains('loaded') && _dh === lastHeight) { clearInterval(timer); cleanup(); }
          lastHeight = _dh;
          return;                                                   // 끝 케이스는 일반 정렬 건너뜀
        }

        // 의도 위치로 재정렬하되, 재정렬에 필요한 이동량(moved)을 측정한다.
        // 위쪽 이미지가 늦게 로드돼 대상이 밀리면 moved가 커지므로 재정렬이 계속된다.
        var _y0 = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
        el.scrollIntoView({ block: 'start' });                      // 대상 위치로 정렬(변동 대비 반복)
        if (targetOffset > 0) window.scrollBy(0, targetOffset);     // 이미지 내부 정밀 위치
        else if (hasFrac) {                                         // 이어 보기: 그림 안 그 줄을 막대 아래에
          var _bb0 = 0; try { var _bar0 = document.getElementById('mcvBar'); if (_bar0) _bb0 = Math.max(0, _bar0.getBoundingClientRect().bottom); } catch (e0) {}
          var _eh = el.getBoundingClientRect().height;
          if (el.classList.contains('loaded') && _eh > 20) window.scrollBy(0, Math.round(_eh * targetFrac / 1000) - _bb0);
        }
        var _y1 = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;

        // 대상 로드 + 재정렬 이동이 거의 없음(±2px)이 2틱 연속 = 주변 레이아웃 안정 → 완료.
        // (대상만 로드됐다고 끝내면, 이후 위쪽 이미지가 로드되며 대상이 밀려 위치가 어긋났음)
        if (el.classList.contains('loaded') && Math.abs(_y1 - _y0) <= 2) {
          if (++stableTicks >= 2) { clearInterval(timer); cleanup(); }
        } else {
          stableTicks = 0;
        }
      }, 250);
    } catch (e) { /* 복원 실패는 무해: 최상단 유지 */ }
  })();

  // ★ (2026-11-01) 위 막대 진행 줄을 누르면 그 쪽으로(js/viewer-bar.js) — 위 복원과 같은 방식: 아직 없는 쪽은 채우고, 주변 그림은 먼저 불러온 뒤,
  //   위쪽 그림이 늦게 불러와져 밀려도 잠깐 다시 맞춤. 사용자가 스크롤하면 바로 멈춤
  window.mcvToonJump = function (idx) {
    try {
      idx = Math.max(0, Math.min(parseInt(idx, 10) || 0, (typeof total === 'number' ? total : 1) - 1));
      var find = function () { return document.getElementById('image' + idx) || document.getElementById('image' + idx + '_left') || document.getElementById('image' + idx + '_right'); };
      var g = 0;
      while (!find() && typeof appendImages === 'function' && currentIndex < total && g < 2000) { var b = currentIndex; appendImages(); if (currentIndex === b) break; g++; }
      var el = find(); if (!el) return;
      for (var d = -1; d <= 3; d++) {
        var nb = document.getElementById('image' + (idx + d)) || document.getElementById('image' + (idx + d) + '_left');
        if (nb && typeof loadLazyImage === 'function') loadLazyImage(nb, true);
      }
      var barEl = document.getElementById('mcvBar'), off = barEl ? barEl.getBoundingClientRect().bottom : 0;
      var put = function () { var e2 = find(); if (!e2) return; window.scrollTo(0, (window.scrollY || window.pageYOffset || 0) + e2.getBoundingClientRect().top - off); };
      put();
      var stop = false, n = 0;
      var halt = function () { stop = true; };
      window.addEventListener('wheel', halt, { passive: true, once: true });
      window.addEventListener('touchmove', halt, { passive: true, once: true });
      // ★ (2026-11-02 20:25) 키·스크롤바(누름)로 움직여도 멈춤, 앞 타이머는 지움 — 3초 안에 Home·스크롤바·다시 누르기가 되돌려지거나 두 타이머가 서로 당겼음
      window.addEventListener('keydown', halt, { once: true });
      window.addEventListener('pointerdown', halt, { passive: true, once: true });
      if (window._mcvJumpIv) clearInterval(window._mcvJumpIv);
      var iv = window._mcvJumpIv = setInterval(function () { if (stop || ++n > 12) { clearInterval(iv); if (window._mcvJumpIv === iv) window._mcvJumpIv = 0; return; } put(); }, 250); // 3초 동안 다시 맞춤
    } catch (e) {}
  };
});

</script>
<?php } ?>

<!-- 자동 로그아웃 타이머 -->
<?php 
// ✅ 현재 페이지가 적용 대상인지 확인
$_current_page = basename($_SERVER['SCRIPT_FILENAME']);
$_auto_logout_pages = $auto_logout_settings['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
$_is_target = in_array($_current_page, $_auto_logout_pages);

// ✅ "로그인 유지"로 로그인한 경우 자동 로그아웃 무시
// ⚠️ session_write_close() 이후이므로 미리 추출한 값 사용
$_is_remember_me = $_session_remember_me;

// ✅ viewer.php는 session_write_close() 이후이므로 render_auto_logout_script() 대신 직접 출력
// $timeout, $remaining은 session_write_close() 전에 미리 계산됨 (59-62행)
global $base_path;
if (($auto_logout_settings['enabled'] ?? true) && $_is_target && !$_is_remember_me): ?>
<script>
window.SESSION_TIMEOUT = <?php echo $timeout; ?>;
window.SESSION_REMAINING = <?php echo $remaining; ?>;
</script>
<?php echo mc_al_i18n_script(); ?>
<script src="<?php echo $base_path; ?>/js/auto-logout.js?v=<?php echo @filemtime(__DIR__ . '/js/auto-logout.js') ?: '1'; ?>"></script>
<?php endif; ?>

<!-- 다크모드 JS (PDF 뷰어, 북모드에서는 제외) -->
<?php if($type != "pdf" && $mode != "book") { render_darkmode_script(); } ?>

<!-- ✅ 페이지 전환 (bootstrap.php 함수 사용으로 중복 코드 제거) -->
<?php render_page_transition_script(true); ?>

<?php
// ✅ [B단계2] 이 파일의 캐시된 이미지 크기를 불러와, 툰 단일모드에서 img에 실제 width/height를 부여(높이 예약).
//    → 콜드 리로드 시 위쪽 이미지 로드를 기다리지 않고도 높이가 확정돼 #imageN 위치가 정확해짐.
//    크기를 모르는 이미지는 기존 동작(height=250px) 그대로 → 데이터 없으면 무동작(무회귀).
$_mc_dims = new stdClass();
try {
    if ($mode == "toon" && function_exists('get_dims_file')) {
        $_dims_raw = @file_get_contents(get_dims_file());
        if ($_dims_raw !== false) {
            $_dims_all = @json_decode($_dims_raw, true);
            $__dk = (int)$current_bidx . '|/' . ltrim((string)$getfile, '/'); // ★ (2026-11-06 01:05) 이 콘텐츠 폴더의 칸 먼저, 없으면 예전 칸(경로만)
            if (is_array($_dims_all) && !isset($_dims_all[$__dk]) && isset($_dims_all[$getfile])) $__dk = $getfile;
            if (is_array($_dims_all) && isset($_dims_all[$__dk]) && is_array($_dims_all[$__dk]) && count($_dims_all[$__dk]) > 0) {
                $_mc_dims = $_dims_all[$__dk];
            }
        }
    }
} catch (\Throwable $e) { $_mc_dims = new stdClass(); }
?>
<?php if ($mode == "toon"){ ?>
<script>
// [B단계2] 캐시된 크기로 높이 예약(단일모드). 실패/데이터없음 시 완전 무동작.
window._mcDimsCache = <?php echo json_encode($_mc_dims); ?>;
(function(){
  try {
    if (typeof pageOrder !== 'undefined' && pageOrder !== '0') return; // 단일모드만
    var D = window._mcDimsCache;
    if (!D || typeof D !== 'object') return;
    if (!Object.keys(D).length) return; // 데이터 없으면 무동작(무회귀)
    function apply(img){
      try {
        if (!img || (img.dataset && img.dataset._arSet)) return;
        var m = (img.id || '').match(/^image(\d+)$/); // 단일 인덱스만(_left/_right 제외)
        if (!m) return;
        var d = D[m[1]];
        if (d && d[0] > 0 && d[1] > 0) {
          img.setAttribute('width', d[0]);
          img.setAttribute('height', d[1]); // 실제 비율로 높이 예약(height=250px 대체) → img-fluid가 비례 스케일
          if (img.dataset) img.dataset._arSet = '1';
        }
      } catch(e){}
    }
    function scan(root){ try { (root || document).querySelectorAll("img[id^='image']").forEach(apply); } catch(e){} }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ scan(document); });
    else scan(document);
    try {
      var mo = new MutationObserver(function(muts){
        for (var i = 0; i < muts.length; i++){
          var a = muts[i].addedNodes;
          for (var j = 0; j < a.length; j++){
            var n = a[j];
            if (n && n.nodeType === 1){
              if (n.matches && n.matches("img[id^='image']")) apply(n);
              if (n.querySelectorAll) scan(n);
            }
          }
        }
      });
      mo.observe(document.documentElement || document.body, { childList:true, subtree:true });
    } catch(e){}
  } catch(e){}
})();
</script>
<?php } ?>
<?php require_once __DIR__ . '/privacy_shield.php'; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
<?php if (function_exists('mc_perf_script')) echo mc_perf_script('viewer'); // ★ (2026-11-03 14:10) 속도 기록(src/perf) ?>
</body>
</html>