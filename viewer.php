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

// ✅ 리다이렉트용 파라미터 - HTTP Header Injection 방지
// $_GET['file']은 이미 URL 인코딩된 상태이므로 그대로 전달하되,
// 개행문자(\r\n)를 제거하여 Header Injection 공격 방지
$_raw_file_param = preg_replace('/[\r\n]/', '', $_GET['file'] ?? '');

if ($_ext === 'txt' && file_exists(__DIR__ . '/txt_viewer.php')) {
    header('Location: txt_viewer.php?file=' . $_raw_file_param . '&bidx=' . $current_bidx);
    exit;
}
if ($_ext === 'epub' && file_exists(__DIR__ . '/epub_viewer.php')) {
    header('Location: epub_viewer.php?file=' . $_raw_file_param . '&bidx=' . $current_bidx);
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
function is_streaming_request() {
    global $_param_imgfile, $_param_thumb, $_param_filetype;
    
    // 이미지 파일 요청
    if (!empty($_param_imgfile)) return true;
    
    // 썸네일 요청
    if (!empty($_param_thumb)) return true;
    
    // 파일 타입별 스트리밍
    if (in_array($_param_filetype, ['pdf', 'video', 'archive'], true)) return true;
    
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
        $img = @imagecreatefromstring($img_data);
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

// ✅ 경로 검증 실패 시 에러 응답은 security_helpers.php의 simple_error_exit() 사용

set_time_limit(30);
if (ob_get_level()) ob_end_clean();

// ✅ Cache-Control 헤더 - 이미지/데이터 스트리밍 요청에만 캐시 허용
// HTML 페이지 렌더링 시에는 no-cache 적용 (HTML 출력 전에 재설정됨)
$_is_streaming_request = is_streaming_request();
if ($_is_streaming_request) {
    header("Cache-Control: public, max-age=86400");
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

    if (!file_exists($base_file)) {
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
            $start = $matches[1] !== '' ? intval($matches[1]) : 0;
            $end = $matches[2] !== '' ? intval($matches[2]) : $file_size - 1;
            
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
    header("Cache-Control: public, max-age=86400");
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
// ✅ MP4 변환 API
// ============================================================
if (($_GET['action'] ?? '') === 'convert_to_mp4') {
    header('Content-Type: application/json');
    
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
            @unlink($temp_file);
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
    $video_data = $zip->getFromName($video_name);
    if ($video_data === false) {
        $zip->close();
        simple_error_exit(404, __('viewer_video_not_found'));
    }

    $zip->close();
    
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
    
    // ZIP 내 파일은 Range 요청을 완전히 지원하기 어려우므로 전체 출력
    header("Accept-Ranges: none");
    header("Content-Type: $mime_type");
    header("Content-Length: " . strlen($video_data));
    header("Cache-Control: public, max-age=86400");
    header("Content-Disposition: inline; filename=\"" . basename($video_name) . "\"");
    
    echo $video_data;
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
                if (is_string($fname)) {
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
            if ($f->isFile() && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f->getFilename())) {
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
header('Cache-Control: public, max-age=86400');
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
                header('Cache-Control: public, max-age=86400');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
                echo $split_result['data'];
                exit;
            } else {
                // 분할 불필요 (세로 이미지 등) → 원본 반환
                $mime = detectMimeFromBytes($original_content);
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . strlen($original_content));
                header('Cache-Control: public, max-age=86400');
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
header('Cache-Control: public, max-age=86400');
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
header('Cache-Control: public, max-age=3600'); // 또는 86400 (기존 그대로)
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
header('Cache-Control: public, max-age=86400');
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
header('Cache-Control: public, max-age=3600'); // 또는 86400 (기존 그대로)
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
    if (!file_exists($cache_file)) {
        simple_error_exit(404, __('viewer_cache_not_found'));
    }

    $image_files = json_decode(file_get_contents($cache_file), true) ?? [];
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

    $filename = basename($image_files[$img_index]);
    
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
header('Cache-Control: public, max-age=86400');
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
                header('Cache-Control: public, max-age=86400');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
                echo $split_result['data'];
                exit;
            } else {
                // 분할 불필요 (세로 이미지 등) → 원본 반환
                $mime = detectMimeFromBytes($original_content);
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . strlen($original_content));
                header('Cache-Control: public, max-age=86400');
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
        $zip = new ZipArchive;
        if ($zip->open($base_file) === TRUE) {
            $img_data = $zip->getFromName($image_files[$img_index]);
            $zip->close();
            unset($zip);

            if ($img_data !== false) {
                $compressed = compressImage($img_data, null, true); // ← ✅ force=true
                
                // ✅ 원본 캐시에 저장
                $tmp_file = "{$cache_save_path}.tmp." . getmypid();
                if (@file_put_contents($tmp_file, $compressed, LOCK_EX) !== false) {
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
header('Cache-Control: public, max-age=3600'); // 또는 86400 (기존 그대로)
echo $output_data;
                unset($img_data, $compressed, $output_data);
                gc_collect_cycles();
                exit;
            } else {
                @flock($lock_fp, LOCK_UN);
                @fclose($lock_fp);
                @unlink($lock_file);
                
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
header('Cache-Control: public, max-age=86400');
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
        
        $zip = new ZipArchive;
        if ($zip->open($base_file) === TRUE) {
            $img_data = $zip->getFromName($image_files[$img_index]);
            $zip->close();
            unset($zip);

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
header('Cache-Control: public, max-age=3600'); // 또는 86400 (기존 그대로)
echo $output_data;
                
                @file_put_contents($cache_save_path, $compressed, LOCK_EX); // 원본 캐시 저장
                
                unset($img_data, $compressed, $output_data);
                gc_collect_cycles();
                exit;
            }
        }
        
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
	die(header("Location: ./"));
}

// ✅ 경로 검증
$base_file = validate_file_path($getfile, $base_dir);
if ($base_file === false) {
    log_user_activity('해킹시도', 'viewer: ' . substr($getfile, 0, 100));
    echo __("err_invalid_path");
    die(header("Location: ./"));
}

// ✅ 열람 로그 기록 (페이지 로드 시 1회만)
$view_filename = basename($getfile);
$view_folder = dirname($getfile);
log_user_activity('열람', $view_folder . '/' . $view_filename);

dir_check($getfile);

$base_title = explode("/", $base_file);
$title = $base_title[(count($base_title)-1)];
$base_folder = str_replace($title, "", $base_file);
$link_dir = str_replace("/".$title, "", $getfile);
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
} elseif (str_contains(strtolower($base_file), "zip") || str_contains(strtolower($base_file), "cbz")) {
    $type = "zip";
} elseif ($_param_filetype === "pdf" || str_contains(strtolower($getfile), ".pdf")) {
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
        $bookmark = $bookmark_arr[$getfile] ?? null;
        
        if(is_array($bookmark)){
            $bookmark = $bookmark['bookmark'];
        }
    }

    // PHP 7.x 호환: match → if-elseif
    if (str_contains(strtolower($base_file), ".zip")) {
        $json_file = substr($base_file, 0, strpos(strtolower($base_file), ".zip")) . ".json";
    } elseif (str_contains(strtolower($base_file), ".cbz")) {
        $json_file = substr($base_file, 0, strpos(strtolower($base_file), ".cbz")) . ".json";
    } elseif ($_param_filetype === "images") {
        $json_file = $base_file . ".image_files.json";
    } else {
        $json_file = null;
    }
    
    if($json_file && is_file($json_file)){
        $pageorder = json_decode(file_get_contents($json_file), true) ?? $pageorder;
        
        // ✅ page_order는 URL 파라미터로만 변경, 기본값은 항상 0 (-)
        if (isset($_GET['pageorder'])) {
            $newpageorder = get_param('pageorder', 'int', 0);
            if (in_array($newpageorder, [0, 1, 2, 3, 4], true)) {
                $pageorder['page_order'] = (string)$newpageorder;
            }
            @file_put_contents($json_file, json_encode($pageorder, JSON_UNESCAPED_UNICODE), LOCK_EX);
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
        if(str_contains(strtolower($base_file), ".zip") || str_contains(strtolower($base_file), ".cbz")){
            $zip = new ZipArchive;
            if ($zip->open($base_file) == TRUE) {
                $thumbnail_index = 0;
                for ($findthumb = 0; $findthumb < $zip->numFiles; $findthumb++) {
                    $find_img = $zip->getNameIndex($findthumb);
                    if(preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $find_img)){
                        $thumbnail_index = $findthumb;
                        break;
                    }
                }

                $img_data = $zip->getFromIndex($thumbnail_index);
                $cropimage = null;
                
                if ($img_data !== false) {
                    $size = @getimagesizefromstring($img_data);
                    if ($size !== false) {
                        $originimage = @imagecreatefromstring($img_data);
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
                    'totalpage' => $zip->numFiles,
                    'page_order' => "0",
                    'viewer' => "toon",
                    'thumbnail' => base64_encode($cropimage ?? '')
                ];
                
                @file_put_contents($json_file, json_encode($pageorder, JSON_UNESCAPED_UNICODE), LOCK_EX);
                $mode = $pageorder['viewer'];
                $zip->close();
            }
        }
    }
}

// ✅ 이전/다음 파일 찾기 - 캐시 활용
$totalfile = [];
$filelist_cache = $base_folder . '/.filelist_cache.json';

if (is_file($filelist_cache)) {
    // 캐시에서 file_list 읽기
    $list_cache = @json_decode(file_get_contents($filelist_cache), true);
    if ($list_cache && isset($list_cache['file_list']) && is_array($list_cache['file_list'])) {
        // ✅ file_list는 배열의 배열이므로 name만 추출
        $totalfile = array_column($list_cache['file_list'], 'name');
    }
}

// 캐시 없거나 비어있으면 직접 스캔
if (empty($totalfile)) {
    $files = scandir($base_folder);
    $files = n_sort($files);
    
    foreach ($files as $file) {
        if(str_contains($file, "json") || in_array($file, [".", "..", "@eaDir"])){
            continue;
        }
        
        if (preg_match('/\.(zip|cbz|rar|cbr|pdf)$/i', $file) || 
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
$page = ceil(($now+1)/$_maxview)-1;

$recent = [];
// ✅ 함수 사용으로 통일 (전역 변수 의존 제거, 파일 잠금 적용)
$_recent_file = get_recent_file();
$recent = load_json_with_lock($_recent_file);
if(isset($recent[$link_dir]) && $recent[$link_dir] != null){
    $recent_num = array_search($recent[$link_dir], $totalfile);
    if($recent_num !== false && $recent_num < $now){
        $recent[$link_dir] = $totalfile[$now] ?? $title;
    }
} else {
    $recent[$link_dir] = $totalfile[$now] ?? $title;
}

save_json_with_lock($_recent_file, $recent);

?>
<?php
flush(); ob_flush();
?>
<!DOCTYPE html>
<html lang="ko">
   <head>
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
	<link rel="shortcut icon" href="./favicon.ico">
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
	<script src="https://cdn.jsdelivr.net/npm/lg-fullscreen/dist/lg-fullscreen.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/lightgallery@2.8.3/plugins/zoom/lg-zoom.min.js"></script>

<?php
}
if($type == "pdf") {
?>
	<link rel="stylesheet" href="./css/swiper-bundle.min.css">
	<script src="./js/swiper-bundle.min.js"></script>
	<script src="./js/pdf.min.js"></script>
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
<script>document.documentElement.classList.add('ready');</script>
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
  
  // ✅ pageOrder에 따라 다르게 생성
  if (pageOrder === '3') {
    // 세로분할 좌→우: left, right 순서
    let slideNum = 0;
    const totalSlides = total * 2;
    for (let i = 0; i < total; i++) {
      // Left half
      slideNum++;
      dynamicEl.push({
        src: `viewer.php?file=${file}&bidx=${bidx}&img=${i}&split=left`,
        thumb: `viewer.php?file=${file}&bidx=${bidx}&img=${i}`,
        subHtml: `<div style="display:inline-flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center;">
          <span class="lg-counter">${slideNum}&nbsp;/&nbsp;${totalSlides}</span>
          <span style="opacity:0.5; font-size:0.85em;">(${i+1}p ${_vi18n.page_left})</span>
          <span class="lg-nav-hint" style="opacity: 0.7; font-size: 0.95em;">${initNavText}</span>
          <button type="button" class="lg-reverse-btn" style="font-size:14px; padding:4px 10px; background:${initBtnBg}; border:none; border-radius:4px; color:#fff; cursor:pointer;">${initBtnText}</button>
          <span class="lg-pageorder-btns" style="display:inline-flex; gap:2px; margin-left:5px;">
            <button type="button" class="lg-pageorder-btn" data-order="0" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">-</button>
            <button type="button" class="lg-pageorder-btn" data-order="3" style="font-size:12px; padding:2px 6px; background:rgba(0,123,255,0.5); border:none; border-radius:3px; color:#fff; cursor:pointer;">▤←</button>
            <button type="button" class="lg-pageorder-btn" data-order="4" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">→▤</button>
          </span>
        </div>`
      });
      // Right half
      slideNum++;
      dynamicEl.push({
        src: `viewer.php?file=${file}&bidx=${bidx}&img=${i}&split=right`,
        thumb: `viewer.php?file=${file}&bidx=${bidx}&img=${i}`,
        subHtml: `<div style="display:inline-flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center;">
          <span class="lg-counter">${slideNum}&nbsp;/&nbsp;${totalSlides}</span>
          <span style="opacity:0.5; font-size:0.85em;">(${i+1}p ${_vi18n.page_right})</span>
          <span class="lg-nav-hint" style="opacity: 0.7; font-size: 0.95em;">${initNavText}</span>
          <button type="button" class="lg-reverse-btn" style="font-size:14px; padding:4px 10px; background:${initBtnBg}; border:none; border-radius:4px; color:#fff; cursor:pointer;">${initBtnText}</button>
          <span class="lg-pageorder-btns" style="display:inline-flex; gap:2px; margin-left:5px;">
            <button type="button" class="lg-pageorder-btn" data-order="0" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">-</button>
            <button type="button" class="lg-pageorder-btn" data-order="3" style="font-size:12px; padding:2px 6px; background:rgba(0,123,255,0.5); border:none; border-radius:3px; color:#fff; cursor:pointer;">▤←</button>
            <button type="button" class="lg-pageorder-btn" data-order="4" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">→▤</button>
          </span>
        </div>`
      });
    }
  } else if (pageOrder === '4') {
    // 세로분할 우→좌: right, left 순서 (일본 만화 스타일)
    let slideNum = 0;
    const totalSlides = total * 2;
    for (let i = 0; i < total; i++) {
      // Right half first
      slideNum++;
      dynamicEl.push({
        src: `viewer.php?file=${file}&bidx=${bidx}&img=${i}&split=right`,
        thumb: `viewer.php?file=${file}&bidx=${bidx}&img=${i}`,
        subHtml: `<div style="display:inline-flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center;">
          <span class="lg-counter">${slideNum}&nbsp;/&nbsp;${totalSlides}</span>
          <span style="opacity:0.5; font-size:0.85em;">(${i+1}p ${_vi18n.page_right})</span>
          <span class="lg-nav-hint" style="opacity: 0.7; font-size: 0.95em;">${initNavText}</span>
          <button type="button" class="lg-reverse-btn" style="font-size:14px; padding:4px 10px; background:${initBtnBg}; border:none; border-radius:4px; color:#fff; cursor:pointer;">${initBtnText}</button>
          <span class="lg-pageorder-btns" style="display:inline-flex; gap:2px; margin-left:5px;">
            <button type="button" class="lg-pageorder-btn" data-order="0" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">-</button>
            <button type="button" class="lg-pageorder-btn" data-order="3" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">▤←</button>
            <button type="button" class="lg-pageorder-btn" data-order="4" style="font-size:12px; padding:2px 6px; background:rgba(0,123,255,0.5); border:none; border-radius:3px; color:#fff; cursor:pointer;">→▤</button>
          </span>
        </div>`
      });
      // Left half
      slideNum++;
      dynamicEl.push({
        src: `viewer.php?file=${file}&bidx=${bidx}&img=${i}&split=left`,
        thumb: `viewer.php?file=${file}&bidx=${bidx}&img=${i}`,
        subHtml: `<div style="display:inline-flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center;">
          <span class="lg-counter">${slideNum}&nbsp;/&nbsp;${totalSlides}</span>
          <span style="opacity:0.5; font-size:0.85em;">(${i+1}p ${_vi18n.page_left})</span>
          <span class="lg-nav-hint" style="opacity: 0.7; font-size: 0.95em;">${initNavText}</span>
          <button type="button" class="lg-reverse-btn" style="font-size:14px; padding:4px 10px; background:${initBtnBg}; border:none; border-radius:4px; color:#fff; cursor:pointer;">${initBtnText}</button>
          <span class="lg-pageorder-btns" style="display:inline-flex; gap:2px; margin-left:5px;">
            <button type="button" class="lg-pageorder-btn" data-order="0" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">-</button>
            <button type="button" class="lg-pageorder-btn" data-order="3" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">▤←</button>
            <button type="button" class="lg-pageorder-btn" data-order="4" style="font-size:12px; padding:2px 6px; background:rgba(0,123,255,0.5); border:none; border-radius:3px; color:#fff; cursor:pointer;">→▤</button>
          </span>
        </div>`
      });
    }
  } else {
    // 기본 (0, 1, 2): 이미지 그대로
    const activeOrder = pageOrder === '0' ? '0' : (pageOrder === '1' ? '1' : (pageOrder === '2' ? '2' : '0'));
    for (let i = 0; i < total; i++) {
      dynamicEl.push({
        src: `viewer.php?file=${file}&bidx=${bidx}&img=${i}`,
        thumb: `viewer.php?file=${file}&bidx=${bidx}&img=${i}`,
        subHtml: `<div style="display:inline-flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center;">
          <span class="lg-counter">${i+1}&nbsp;/&nbsp;${total}</span>
          <span class="lg-nav-hint" style="opacity: 0.7; font-size: 0.95em;">${initNavText}</span>
          <button type="button" class="lg-reverse-btn" style="font-size:14px; padding:4px 10px; background:${initBtnBg}; border:none; border-radius:4px; color:#fff; cursor:pointer;">${initBtnText}</button>
          <span class="lg-pageorder-btns" style="display:inline-flex; gap:2px; margin-left:5px;">
            <button type="button" class="lg-pageorder-btn" data-order="0" style="font-size:12px; padding:2px 6px; background:${activeOrder === '0' ? 'rgba(0,123,255,0.5)' : 'rgba(255,255,255,0.2)'}; border:none; border-radius:3px; color:#fff; cursor:pointer;">-</button>
            <button type="button" class="lg-pageorder-btn" data-order="3" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">▤←</button>
            <button type="button" class="lg-pageorder-btn" data-order="4" style="font-size:12px; padding:2px 6px; background:rgba(255,255,255,0.2); border:none; border-radius:3px; color:#fff; cursor:pointer;">→▤</button>
          </span>
        </div>`
      });
    }
  }

  $('#lightgallery').lightGallery({
    dynamic: true,
    dynamicEl: dynamicEl,
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
    location.replace('./viewer.php?mode=book&file=' + file + '&bidx=' + bidx + '&pageorder=' + newOrder);
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
    return !!document.querySelector('.lg-outer.lg-visible');
  }

  function isUiControl(el) {
    return !!(el && el.closest && el.closest(
      '.lg-close, .lg-toolbar, .lg-actions, .lg-prev, .lg-next, .lg-thumb-outer, .lg-sub-html'
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
  if (tag === 'input' || tag === 'textarea' || tag === 'select' || tag === 'button' || e.isComposing) return;

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

  const t = ev.changedTouches?.[0] || ev;
  const x = t?.clientX ?? 0;

  if (ev.type === 'click' || ev.type === 'touchend') {
    ev.preventDefault();
    ev.stopPropagation();
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
  location.replace("./viewer.php?mode=toon&file=" + safeFile + "&bidx=<?php echo $current_bidx; ?>");
});

window.addEventListener('beforeunload', function () {
  var safeFile = <?php echo js(encode_url($getfile)); ?>;
  var safeBookmark = <?php echo js($bookmark ?? '0'); ?>;
  const toonUrl = "./viewer.php?<?php if ($_param_filetype === 'images'){echo 'filetype=images&';} ?>mode=toon&file=" + safeFile + "&bidx=<?php echo $current_bidx; ?>#" + safeBookmark;
  history.replaceState(null, "", toonUrl);
});

$(document).one('onCloseAfter.lg', function () {
  var idx = 0;
  var inst = $('#lightgallery').data('lightGallery');
  if (inst && typeof inst.index === 'number') idx = inst.index;
  var bookmark = 'image' + idx;

  var safeFile = <?php echo js(encode_url($getfile)); ?>;
  const user_id = <?php echo js($user_id); ?>;  // ✅ 세션 닫기 전에 저장한 변수 사용

  const qs = "viewer=toon"
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

  send("bookmark.php?" + qs);
  send("bookmark.php?mode=autosave&" + qs);

  const toonUrl = "./viewer.php?mode=toon&file=" + safeFile + "&bidx=<?php echo $current_bidx; ?>#" + bookmark;
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
	$.get("bookmark.php?mode=set_cover&file=" + safeFile + "&bidx=<?php echo $current_bidx; ?>", function( data ) {
		document.getElementById("info").value = data;
	});
}
<?php
}
?>
</script>
<body>

<?php if($type != "pdf"): ?>
<nav class="navbar navbar-light fixed-top bg-white p-1 m-0">
<table class="table table-borderless mb-2 p-0" width=100%>
<tr>
<td class="m-0 p-0 align-middle">
<?php if(($_branding['logo_type'] ?? 'text') === 'image' && !empty($_branding['logo_image']) && file_exists($_branding['logo_image'])): ?>
<a href="./index.php?dir=<?php echo encode_url($link_dir); ?>&page=<?php echo intval($page); ?>&bidx=<?php echo $current_bidx; ?>" class="logo-link"><img src="<?php echo h($_branding['logo_image']); ?>" alt="<?php echo h($_branding['logo_text']); ?>" style="max-height:2em; max-width:150px;"></a><span style="font-size:10px; color:#999; margin-left:4px; position:relative; top:-15px;"><?php echo MYCOMIX_VERSION; ?></span><?php render_lang_badge('lg'); ?>
<?php else: ?>
<a href="./index.php?dir=<?php echo encode_url($link_dir); ?>&page=<?php echo intval($page); ?>&bidx=<?php echo $current_bidx; ?>" class="logo-link"><font style="font-family: 'Gugi'; font-size: 2em;"><?php echo h($_branding['logo_text'] ?? '마이코믹스'); ?></font></a><span style="font-size:10px; color:#999; margin-left:4px; position:relative; top:-15px;"><?php echo MYCOMIX_VERSION; ?></span><?php render_lang_badge('lg'); ?>
<?php endif; ?>
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
if($type != "pdf"){
?>
<tr><td align="right">
<br>
<button class="btn btn-sm btn-success mt-2 p-0" onclick="set_cover();"><?php echo __h("viewer_set_cover"); ?></button>
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
<button type="button" class="btn btn-outline-secondary btn-sm" OnClick="location.replace('./index.php?dir=<?php echo encode_url($link_dir); ?>&page=<?php echo intval($page) . $bidx_param; ?>')">
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
<button type="button" class="btn btn-outline-secondary btn-sm mr-1" OnClick="location.replace('./index.php?dir=<?php echo encode_url($link_dir); ?>&page=<?php echo intval($page); ?>&bidx=<?php echo $current_bidx; ?>')">
<svg width="3em" height="1em" viewBox="0 0 16 16" class="bi bi-list" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M2.5 11.5A.5.5 0 0 1 3 11h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4A.5.5 0 0 1 3 7h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4A.5.5 0 0 1 3 3h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
</svg>
</button>
<?php 
// ✅ 즐겨찾기 버튼
$_viewer_fav_path = $getfile;
$_viewer_is_fav = isset($_favorites_arr[$_viewer_fav_path]);
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
    xhr.send('file=' + encodeURIComponent('<?php echo addslashes($_viewer_fav_path); ?>') + '&bidx=<?php echo $current_bidx; ?>');
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
$filetype_param = ($_param_filetype === 'images') ? 'filetype=images&' : '';

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
    // 동영상 뷰어
    $is_zip_video = preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $base_file);
    $video_files = [];
    
    if ($is_zip_video) {
        // ZIP 파일 내 동영상 목록 추출
        $zip = new ZipArchive;
        if ($zip->open($base_file) === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
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
            
            <script>
            var safeFile = <?php echo js(encode_url($getfile)); ?>;
            var currentVideoItem = null;
            
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
                
                // 비디오 재생
                var player = document.getElementById('zipVideoPlayer');
                var videoUrl = 'viewer.php?filetype=zipvideo&stream=1&file=' + safeFile + '&video=' + encodeURIComponent(videoName) + '&bidx=<?php echo $current_bidx; ?>';
                
                player.src = videoUrl;
                player.load();
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
            $cmd = escape_shell_arg_safe($ffprobe_path) . ' -v quiet -print_format json -show_format -show_streams ' . escape_shell_arg_safe($base_file) . ' 2>&1';
            $json_output = @shell_exec($cmd);
            if ($json_output) {
                $video_info = @json_decode($json_output, true);
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
                $mins = floor(($dur % 3600) / 60);
                $secs = floor($dur % 60);
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
        
        <?php if ($is_playable): ?>
            <div class="video-wrapper">
                <div class="video-container">
                    <video id="videoPlayer" controls playsinline>
                        <source src="viewer.php?filetype=video&stream=1&file=<?php echo encode_url($getfile) . $bidx_param; ?>" type="<?php echo h(mime_type($base_file)); ?>">
                        <?php echo __h("viewer_browser_no_video"); ?>
                    </video>
                </div>
                
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
                fetch('viewer.php?action=convert_to_mp4&file=' + file + '&bidx=<?php echo $current_bidx; ?>')
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
                fetch('viewer.php?action=convert_to_mp4&file=' + file + '&bidx=<?php echo $current_bidx; ?>')
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
            $filename = $zip->getNameIndex($i);
            if (is_video_file($filename)) {
                $stat = $zip->statIndex($i);
                $video_list[] = [
                    'name' => basename($filename),
                    'size' => $stat['size']
                ];
            }
        }
        $zip->close();
    }
    
    // 파일 크기 포맷 함수
    $format_size = function($bytes) {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    };
?>
<div class="container-fluid m-0 p-3" style="margin-top: 50px !important;">
    <div class="video-wrapper">
        <div class="video-unsupported" style="margin-top: 30px;">
            <h4 style="font-size: 1.5em;">📦 <?php echo __h("viewer_video_archive"); ?></h4>
            <p style="color: #856404; font-size: 1.1em; line-height: 1.8;">
                <?php echo __("viewer_archive_no_stream"); ?><br>
                <?php echo __("viewer_archive_extract_hint"); ?>
            </p>
            
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
                <div class="pdf-toolbar-divider"></div>
                <button class="pdf-toolbar-btn" onclick="location.href='./index.php?dir=<?php echo encode_url($link_dir); ?>&page=<?php echo intval($page); ?>&bidx=<?php echo $current_bidx; ?>'" title="<?php echo __h('viewer_back_to_list'); ?>">
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
let currentScale = 1.5;
const isMobile = window.innerWidth <= 768;

// 모바일에서는 기본 스케일 조정
if (isMobile) {
    currentScale = 1.0;
}

const safeFile = <?php echo js($getfile); ?>;
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
    for (let i = 1; i <= totalPages; i++) {
        const canvas = document.getElementById('page_' + i);
        if (canvas) {
            renderPage(i, canvas, currentScale);
        }
    }
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

// 썸네일 렌더링
function renderThumbnail(pageNum) {
    pdfDoc.getPage(pageNum).then(function(page) {
        const thumbScale = 0.3;
        const viewport = page.getViewport({ scale: thumbScale });
        
        const item = document.createElement('div');
        item.className = 'pdf-thumbnail-item' + (pageNum === 1 ? ' active' : '');
        item.onclick = function() { goToPage(pageNum); };
        
        const canvas = document.createElement('canvas');
        canvas.className = 'pdf-thumb-canvas';
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        
        const ctx = canvas.getContext('2d');
        page.render({ canvasContext: ctx, viewport: viewport });
        
        const number = document.createElement('div');
        number.className = 'pdf-thumb-number';
        number.textContent = pageNum;
        
        item.appendChild(canvas);
        item.appendChild(number);
        document.getElementById('pdf-thumbnail-container').appendChild(item);
    });
}

// 페이지 렌더링
function renderPage(pageNum, canvas, scale) {
    pdfDoc.getPage(pageNum).then(function(page) {
        const viewport = page.getViewport({ scale: scale });
        const ctx = canvas.getContext('2d');
        
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        
        page.render({ canvasContext: ctx, viewport: viewport });
    }).catch(function(error) {
        console.error('페이지 ' + pageNum + ' 렌더링 실패:', error);
    });
}

// PDF 로드
if (typeof window['pdfjs-dist/build/pdf'] === 'undefined') {
    document.getElementById('pdf-pages-container').innerHTML = 
        '<div class="alert alert-danger m-3">' + _vi18n.pdfjs_load_fail + '</div>';
} else {
    const pdfjsLib = window['pdfjs-dist/build/pdf'];
    pdfjsLib.GlobalWorkerOptions.workerSrc = './js/pdf.worker.min.js';
    
    pdfjsLib.getDocument(pdfUrl).promise.then(function(doc) {
        pdfDoc = doc;
        totalPages = doc.numPages;
        document.getElementById('pdf-total-pages').textContent = totalPages;
        document.getElementById('pdf-page-input').max = totalPages;
        
        const container = document.getElementById('pdf-pages-container');
        
        // 각 페이지 렌더링
        for (let i = 1; i <= totalPages; i++) {
            // 썸네일 렌더링
            renderThumbnail(i);
            
            // 메인 페이지 래퍼
            const wrapper = document.createElement('div');
            wrapper.className = 'pdf-page-wrapper';
            wrapper.id = 'page_wrapper_' + i;
            
            const canvas = document.createElement('canvas');
            canvas.id = 'page_' + i;
            wrapper.appendChild(canvas);
            container.appendChild(wrapper);
            
            renderPage(i, canvas, currentScale);
        }
        
        // 스크롤 이벤트
        container.addEventListener('scroll', function() {
            requestAnimationFrame(updateCurrentPage);
        });
        
    }).catch(function(error) {
        console.error('PDF 로드 실패:', error);
        document.getElementById('pdf-pages-container').innerHTML = 
            '<div class="alert alert-danger m-3">' + _vi18n.pdf_load_fail + '<br>' + _vi18n.pdf_error + ' ' + error.message + '</div>';
    });
}

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
						$zip = new ZipArchive;
						if ($zip->open($base_file) == TRUE) {
							for ($i = 0; $i < $zip->numFiles; $i++) {
								$name = $zip->getNameIndex($i);
								if(preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $name)){
									$list[$i] = $name;
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
									if (is_string($fname)) {
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
								if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $jpgfile)) {
									$list[$counter] = $base_file."/".$jpgfile;
									$counter++;
								}
							}
						}
						$file_type = "filetype=images&";
					}

$total = count($list);
$list = n_sort($list);

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

echo "<div class=\"text-center\" id=\"lightgallery\"
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
<?php
if ($mode == "toon"){
?>
for (var i = 0; i <= <?php echo $image_counter; ?>; i++) {
	var j = <?php echo $image_counter; ?> - i;
	var scroll_image = $("#image"+j).position().top;
	if (scroll_top > scroll_image) {
		bookmark= "image" + String(j);
		break;
	}
}
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
$.get(pdfBookmarkUrl + bookmark, function( data ) {
document.getElementById("info").value = data;
});
<?php
}
?>

}

<?php
if ($mode == "book"){
?>
$("body").on('DOMSubtreeModified', "#lg-counter-current", function() {
	var new_counter = document.getElementById("lg-counter-current").innerHTML - 1;
	if (new_counter == 0 || new_counter == null){
	} else {
		img_counter = new_counter;
	}
});
<?php
}
?>

const options = { 
    rootMargin: '300px 0px',
    threshold: 0.01
};

function autosave(){	
<?php if ($mode == "toon"){ ?>
	for (var i = 0; i <= <?php echo $image_counter; ?>; i++) {
		var j = <?php echo $image_counter; ?> - i;
		var scroll_image = $("#image"+j).position().top;
		if (scroll_top > scroll_image) {
			bookmark= "image" + String(j);
			break;
		}
	}
	var autosaveUrl = <?php echo js("bookmark.php?mode=autosave&viewer=" . $mode . "&page_order=" . ($pageorder['page_order'] ?? '0') . "&file=" . encode_url($getfile) . "&bidx=" . $current_bidx . "&bookmark="); ?>;
	$.get(autosaveUrl + bookmark, function(data) {
		document.getElementById("info").value = data;
	});
<?php } elseif($mode == "book") { ?>
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
	var autosaveUrl = <?php echo js("bookmark.php?mode=autosave&viewer=" . $mode . "&page_order=" . ($pageorder['page_order'] ?? '0') . "&file=" . encode_url($getfile) . "&bidx=" . $current_bidx . "&bookmark="); ?>;
	$.get(autosaveUrl + bookmark, function(data) {
		document.getElementById("info").value = data;
	});
<?php } ?>

<?php if($type == "pdf") { ?>
	let visiblePage = 1;
	const canvases = document.querySelectorAll("canvas[id^='page_']");
	const scrollY = window.scrollY;

	for (let i = 0; i < canvases.length; i++) {
		const top = canvases[i].offsetTop;
		const height = canvases[i].offsetHeight;
		if (scrollY >= top - height / 2) {
			visiblePage = i + 1;
		}
	}

	const bookmark = "pdf_" + visiblePage;

	var pdfAutosaveUrl = <?php echo js("bookmark.php?mode=autosave&viewer=pdf&page_order=pdf&file=" . encode_url($getfile) . "&bidx=" . $current_bidx . "&bookmark="); ?>;
	$.get(pdfAutosaveUrl + bookmark, function(data) {
		document.getElementById("info").value = data;
	});
<?php } ?>
}

$(window).on("beforeunload", function () {
	autosave();
});

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

<script src="./js/crypto-js.min.js"></script>
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
  let activeLoads = 0;

  function loadLazyImage(img) {
    if (!img || !img.dataset.src) return;
    
    const imgIndex = parseInt(img.id.replace("image", ""), 10);
    
    if (imageStatus.get(imgIndex) === 'loading' || img.classList.contains('loaded')) {
      return;
    }

    if (imgIndex > 0) {
      const prevStatus = imageStatus.get(imgIndex - 1);
      if (prevStatus !== 'loaded' && prevStatus !== 'failed') {
        if (!loadQueue.some(item => item.index === imgIndex)) {
          loadQueue.push({ img, index: imgIndex });
        }
        return;
      }
    }

    if (activeLoads >= MAX_CONCURRENT) {
      if (!loadQueue.some(item => item.index === imgIndex)) {
        loadQueue.push({ img, index: imgIndex });
      }
      return;
    }

    startImageLoad(img, imgIndex);
  }

  function startImageLoad(img, imgIndex, retryCount = 0) {
    if (imageStatus.get(imgIndex) === 'loading') return;
    
    activeLoads++;
    imageStatus.set(imgIndex, 'loading');
    
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
      imageStatus.delete(imgIndex);
      
      setTimeout(() => {
        startImageLoad(img, imgIndex, retryCount + 1);
      }, 1000 * (retryCount + 1));
    } else {
      img.classList.add('load-failed');
      imageStatus.set(imgIndex, 'failed');
      console.error(`💀 image${imgIndex} 최종 실패`);
      
      processQueue();
      triggerNextImage(imgIndex);
    }
  }

  function triggerNextImage(currentImgIndex) {
    const nextIndex = currentImgIndex + 1;
    const nextImg = document.getElementById(`image${nextIndex}`);
    
    if (nextImg && !nextImg.classList.contains('loaded')) {
      setTimeout(() => {
        loadLazyImage(nextImg);
      }, 100);
    }
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

    container.appendChild(fragment);
    fragment.querySelectorAll("img.lazyload").forEach(observeLazyImage);

    loading = false;

    const lastImg = fragment.querySelector("img:last-child");
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
      
      if (!img.classList.contains('loaded') && status !== 'loading') {
        const rect = img.getBoundingClientRect();
        const isVisible = rect.top < window.innerHeight + 600 && rect.bottom > -300;
        
        if (isVisible) {
          const prevStatus = imgIndex > 0 ? imageStatus.get(imgIndex - 1) : 'loaded';
          
          if (imgIndex === 0 || prevStatus === 'loaded' || prevStatus === 'failed') {
            console.log(`🔧 복구: image${imgIndex}`);
            img.classList.remove('load-failed');
            imageStatus.delete(imgIndex);
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
<script src="<?php echo $base_path; ?>/js/auto-logout.js?v=<?php echo @filemtime(__DIR__ . '/js/auto-logout.js') ?: '1'; ?>"></script>
<?php endif; ?>

<!-- 다크모드 JS (PDF 뷰어, 북모드에서는 제외) -->
<?php if($type != "pdf" && $mode != "book") { render_darkmode_script(); } ?>

<!-- ✅ 페이지 전환 (bootstrap.php 함수 사용으로 중복 코드 제거) -->
<?php render_page_transition_script(true); ?>

<?php require_once __DIR__ . '/privacy_shield.php'; ?>
</body>
</html>