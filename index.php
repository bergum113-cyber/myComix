<?php
/**
 * myComix 메인 인덱스 페이지
 * @version 4.5 - 북마크/자동저장 목록 버튼에 privacy_shield 호환성 추가
 * @date 2026-01-12
 */

// 실행 시간 측정 시작
$_start_time = microtime(true);

// 동영상 썸네일 생성 등 시간이 오래 걸리는 작업을 위해 실행 시간 제한 해제
@set_time_limit(0);
@ini_set('max_execution_time', 0);

require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/search_translate.php"; // ★ (2026-11-06 08:10) 한영 검색어 연결 함수(관리 화면 미리보기와 같은 것)

// ===== upload_tmp 오래된 임시파일 자동 정리 (1시간 초과) =====
$_uploadTmpDir = __DIR__ . '/src/upload_tmp';
if (is_dir($_uploadTmpDir)) {
    foreach (glob($_uploadTmpDir . '/*') as $_oldFile) {
        if (is_file($_oldFile) && (time() - filemtime($_oldFile)) > 3600) {
            @unlink($_oldFile);
        }
    }
}
unset($_uploadTmpDir, $_oldFile);

// ===== 청크 업로드 빠른 처리 (다른 출력 전에 처리) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action']) && in_array($_POST['admin_action'], ['chunk_init', 'chunk_data', 'chunk_finish'])) {
    // 출력 버퍼 정리
    while (ob_get_level()) ob_end_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    
    // 관리자 확인
    if (!isset($_SESSION['user_group']) || $_SESSION['user_group'] !== 'admin') {
        echo json_encode(['success' => false, 'error' => __('api_admin_required')]);
        exit;
    }
    
    // CSRF 확인
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => __('api_csrf_error')]);
        exit;
    }
    
    // 경로 설정
    $bidx = init_bidx();
    $current_dir = $_POST['current_dir'] ?? '';
    $target_base = $base_dir;
    if (!empty($current_dir)) {
        $validated = validate_file_path($current_dir, $base_dir);
        // ★ (2026-10-30) 경로가 틀리면 조용히 루트에 올리지 않고 오류(다른 업로드 경로와 같게)
        if ($validated === false || !is_dir($validated)) {
            echo json_encode(['success' => false, 'error' => __('api_invalid_path')]);
            exit;
        }
        $target_base = $validated;
    }
    // ★ (2026-10-30) 서버에서 실행·설정될 수 있는 파일은 청크 업로드로도 받지 않음(일반 업로드는 허용 목록 검사가 있었음)
    $_mc_upload_blocked = function ($name) {
        $name = rtrim(strtolower(trim((string)$name)), ". "); // Windows 는 이름 끝 점·공백을 버림(shell.php. → shell.php)
        if (strpos($name, '.ht') === 0 || $name === '.user.ini') return true; // .htaccess·.htpasswd·.user.ini
        return (bool)preg_match('/\.(php\d*|phtml|pht|phar|phps|cgi|pl|py|asp|aspx|jsp|shtml|htaccess|htpasswd)$/i', $name);
    };
    
    $uploadTmpDir = __DIR__ . '/src/upload_tmp';
    if (!is_dir($uploadTmpDir)) @mkdir($uploadTmpDir, 0755, true);
    
    $action = $_POST['admin_action'];
    // ★ (2026-10-30) 업로드·삭제 뒤 작품 정보(라이브러리) 자동 스캔 예약 — 표시만 남기고(가벼움), 15초 조용해진 뒤 화면을 열 때 서버에서 빠른 스캔
    //   (여러 파일을 연달아 올려도 마지막 뒤 한 번만). 라이브러리를 쓰지 않으면(DB 없음) 아무것도 안 함
    if (in_array($action, ['chunk_finish', 'upload_file', 'upload_file_single', 'delete_file', 'delete_folder', 'delete_multiple'], true)) {
        $_mc_lib_want_bidx = (int)($bidx ?? 0);
        register_shutdown_function(function () use ($_mc_lib_want_bidx) {
            if (!is_file(__DIR__ . '/src/library.db')) return;
            try { require_once __DIR__ . '/library.php'; if (function_exists('mc_lib_mark_want')) mc_lib_mark_want($_mc_lib_want_bidx); } catch (\Throwable $e) {}
        });
    }
    
    if ($action === 'chunk_init') {
        $uploadId = bin2hex(random_bytes(16));
        $fileName = $_POST['file_name'] ?? 'unknown';
        if (!is_string($fileName) || $_mc_upload_blocked(basename(str_replace('\\', '/', $fileName)))) {
            echo json_encode(['success' => false, 'error' => __('api_disallowed_ext', strtolower(pathinfo(rtrim((string)$fileName, ". \t"), PATHINFO_EXTENSION)))]); // ★ (2026-11-04 00:28) 'x.php. ' 같은 이름도 확장자가 보이게
            exit;
        }
        $fileSize = (int)($_POST['file_size'] ?? 0);
        
        file_put_contents($uploadTmpDir . '/' . $uploadId . '.json', json_encode([
            'upload_id' => $uploadId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'target_path' => $target_base,
            'created_at' => time()
        ]));
        touch($uploadTmpDir . '/' . $uploadId . '.tmp');
        
        echo json_encode(['success' => true, 'upload_id' => $uploadId]);
        exit;
    }
    
    if ($action === 'chunk_data') {
        $uploadId = is_string($_POST['upload_id'] ?? null) ? $_POST['upload_id'] : ''; // ★ (2026-11-02 19:05) 배열이면 TypeError(500)
        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            echo json_encode(['success' => false, 'error' => __('api_invalid_id')]);
            exit;
        }
        
        $sessionFile = $uploadTmpDir . '/' . $uploadId . '.json';
        $tmpFile = $uploadTmpDir . '/' . $uploadId . '.tmp';
        
        if (!file_exists($sessionFile)) {
            echo json_encode(['success' => false, 'error' => __('api_no_session')]);
            exit;
        }
        
        if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => __('api_chunk_error', $_FILES['chunk']['error'] ?? 'no file')]);
            exit;
        }
        
        file_put_contents($tmpFile, file_get_contents($_FILES['chunk']['tmp_name']), FILE_APPEND);
        @touch($sessionFile); // ★ (2026-10-30) 1시간 넘는 업로드 중에 세션 파일이 '오래된 임시 파일'로 지워지지 않게
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'chunk_finish') {
        $uploadId = is_string($_POST['upload_id'] ?? null) ? $_POST['upload_id'] : ''; // ★ (2026-11-02 19:05) 배열이면 TypeError(500)
        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            echo json_encode(['success' => false, 'error' => __('api_invalid_id')]);
            exit;
        }
        
        $sessionFile = $uploadTmpDir . '/' . $uploadId . '.json';
        $tmpFile = $uploadTmpDir . '/' . $uploadId . '.tmp';
        
        if (!file_exists($sessionFile) || !file_exists($tmpFile)) {
            echo json_encode(['success' => false, 'error' => __('api_file_not_found')]);
            exit;
        }
        
        $session = json_decode(file_get_contents($sessionFile), true);
        $safeName = preg_replace('/[<>:"\/\\\\|?*\x00-\x1f]/', '', $session['file_name']);
        if (trim($safeName) === '') $safeName = 'file_' . time();
        if ($_mc_upload_blocked($safeName)) { // ★ (2026-10-30)
            @unlink($tmpFile); @unlink($sessionFile);
            echo json_encode(['success' => false, 'error' => __('api_disallowed_ext', strtolower(pathinfo($safeName, PATHINFO_EXTENSION)))]);
            exit;
        }
        
        $dest = $session['target_path'] . '/' . $safeName;
        
        // 기존 파일 있으면 덮어쓰기 (삭제 후 이동)
        if (file_exists($dest)) {
            if (is_file($dest)) mc_clear_file_caches($dest); // ★ (2026-10-30) 옛 캐시도 정리
            @unlink($dest);
        }
        
        if (rename($tmpFile, $dest)) {
            @unlink($sessionFile);
            $cache = dirname($dest) . '/.folder_cache.json';
            if (file_exists($cache)) @unlink($cache);
            if (function_exists('log_user_activity')) @log_user_activity('파일 업로드', basename($dest)); // ★ (2026-11-04 00:28) 업로드도 활동 기록에(화면이 쓰는 이 경로엔 없었음)
            echo json_encode(['success' => true, 'file' => basename($dest)]);
        } else {
            echo json_encode(['success' => false, 'error' => __('api_move_failed')]);
        }
        exit;
    }
}
// ===== 청크 업로드 끝 =====

// ✅ log_user_activity()는 function.php에서 로드됨 (2026-01-20 통합)

// ✅ cache_util.php는 bootstrap.php에서 자동 로드됨
// rebuild_folder_caches() 함수 등 사용 가능

// ✅ 1x1 투명 GIF (썸네일 없을 때 placeholder)
$null_image = "R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";

// ✅ 보안 헤더는 init.php에서 이미 설정됨 (CSP 통일)
// set_security_headers();

handle_timeout_popup();

// ✅ 다중 폴더 지원: URL 파라미터로 폴더 선택
$bidx = init_bidx();  // $bidx_query, $bidx_param 등 전역 변수도 함께 설정됨

// ✅ 관리자 여부 확인
$is_admin = isset($_SESSION['user_group']) && trim($_SESSION['user_group']) === 'admin';

// ✅ 관리자 전용: 폴더 생성 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action']) && $is_admin) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => __('api_csrf_invalid')]);
        exit;
    }
    
    $action = $_POST['admin_action'];
    // ★ (2026-10-30) 업로드·삭제 뒤 작품 정보(라이브러리) 자동 스캔 예약 — 표시만 남기고(가벼움), 15초 조용해진 뒤 화면을 열 때 서버에서 빠른 스캔
    //   (여러 파일을 연달아 올려도 마지막 뒤 한 번만). 라이브러리를 쓰지 않으면(DB 없음) 아무것도 안 함
    if (in_array($action, ['chunk_finish', 'upload_file', 'upload_file_single', 'delete_file', 'delete_folder', 'delete_multiple'], true)) {
        $_mc_lib_want_bidx = (int)($bidx ?? 0);
        register_shutdown_function(function () use ($_mc_lib_want_bidx) {
            if (!is_file(__DIR__ . '/src/library.db')) return;
            try { require_once __DIR__ . '/library.php'; if (function_exists('mc_lib_mark_want')) mc_lib_mark_want($_mc_lib_want_bidx); } catch (\Throwable $e) {}
        });
    }
    $current_dir = $_POST['current_dir'] ?? '';
    
    // 경로 검증
    $target_base = $base_dir;
    if (!empty($current_dir)) {
        $validated_dir = validate_file_path($current_dir, $base_dir);
        if ($validated_dir === false || !is_dir($validated_dir)) { // ★ (2026-10-30) 없는 폴더면 거부(새 폴더가 엉뚱한 경로에 함께 만들어지지 않게)
            echo json_encode(['success' => false, 'error' => __('api_invalid_path')]);
            exit;
        }
        $target_base = $validated_dir;
    }
    
    switch ($action) {
        case 'create_folder':
            $folder_name = trim($_POST['folder_name'] ?? '');
            
            // 폴더명 유효성 검사
            if (empty($folder_name)) {
                echo json_encode(['success' => false, 'error' => __('api_enter_folder_name')]);
                break;
            }
            
            // 위험한 문자 제거
            // ★ (2026-10-30) 역슬래시도 제거('\\|' 는 정규식에서 '|' 만 뜻해 '\' 가 빠져, a\..\..\x 로 상위 폴더에 만들 수 있었음)
            $folder_name = preg_replace('/[<>:"\/\\\\|?*\x00-\x1f]/', '', $folder_name);
            $folder_name = trim($folder_name, '. ');
            
            if (empty($folder_name) || $folder_name === '.' || $folder_name === '..') {
                echo json_encode(['success' => false, 'error' => __('api_invalid_folder_name')]);
                break;
            }
            
            $new_folder_path = $target_base . '/' . $folder_name;
            
            if (file_exists($new_folder_path)) {
                echo json_encode(['success' => false, 'error' => __('api_folder_exists')]);
                break;
            }
            
            if (@mkdir($new_folder_path, 0755, true)) {
                log_user_activity('폴더 생성', $new_folder_path);
                echo json_encode(['success' => true, 'message' => __('api_folder_created', $folder_name)]);
            } else {
                echo json_encode(['success' => false, 'error' => __('api_folder_create_failed')]);
            }
            break;
        
        // ===== 청크 업로드 핸들러 (대용량 파일용) =====
        case 'chunk_init':
            $uploadTmpDir = __DIR__ . '/src/upload_tmp';
            if (!is_dir($uploadTmpDir)) @mkdir($uploadTmpDir, 0755, true);
            
            // 1시간 이상 된 임시파일 자동 정리
            foreach (glob($uploadTmpDir . '/*') as $oldFile) {
                if (is_file($oldFile) && (time() - filemtime($oldFile)) > 3600) {
                    @unlink($oldFile);
                }
            }
            
            $uploadId = bin2hex(random_bytes(16));
            $fileName = $_POST['file_name'] ?? 'unknown';
            $fileSize = (int)($_POST['file_size'] ?? 0);
            
            $sessionFile = $uploadTmpDir . '/' . $uploadId . '.json';
            file_put_contents($sessionFile, json_encode([
                'upload_id' => $uploadId,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'target_path' => $target_base,
                'created_at' => time()
            ]));
            
            $tmpFile = $uploadTmpDir . '/' . $uploadId . '.tmp';
            @touch($tmpFile);
            
            echo json_encode(['success' => true, 'upload_id' => $uploadId]);
            break;
            
        case 'chunk_data':
            $uploadTmpDir = __DIR__ . '/src/upload_tmp';
            $uploadId = $_POST['upload_id'] ?? '';
            
            if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
                echo json_encode(['success' => false, 'error' => __('api_invalid_upload_id')]);
                break;
            }
            
            $sessionFile = $uploadTmpDir . '/' . $uploadId . '.json';
            $tmpFile = $uploadTmpDir . '/' . $uploadId . '.tmp';
            
            if (!file_exists($sessionFile)) {
                echo json_encode(['success' => false, 'error' => __('api_no_upload_session')]);
                break;
            }
            
            if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
                $err = isset($_FILES['chunk']) ? $_FILES['chunk']['error'] : 'no file';
                echo json_encode(['success' => false, 'error' => __('api_chunk_error', $err)]);
                break;
            }
            
            $chunkData = file_get_contents($_FILES['chunk']['tmp_name']);
            file_put_contents($tmpFile, $chunkData, FILE_APPEND);
            
            echo json_encode(['success' => true, 'received' => strlen($chunkData)]);
            break;
            
        case 'chunk_finish':
            $uploadTmpDir = __DIR__ . '/src/upload_tmp';
            $uploadId = $_POST['upload_id'] ?? '';
            
            if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
                echo json_encode(['success' => false, 'error' => __('api_invalid_upload_id')]);
                break;
            }
            
            $sessionFile = $uploadTmpDir . '/' . $uploadId . '.json';
            $tmpFile = $uploadTmpDir . '/' . $uploadId . '.tmp';
            
            if (!file_exists($sessionFile) || !file_exists($tmpFile)) {
                echo json_encode(['success' => false, 'error' => __('api_file_not_found')]);
                break;
            }
            
            $session = json_decode(file_get_contents($sessionFile), true);
            $fileName = $session['file_name'];
            $targetPath = $session['target_path'];
            
            // 파일명 정리 (특수문자 유지, 위험 문자만 제거)
            $safeName = preg_replace('/[<>:"\/\\\\|?*\x00-\x1f]/', '', $fileName);
            if (trim($safeName) === '') $safeName = 'file_' . time();
            
            $destination = $targetPath . '/' . $safeName;
            
            // 중복 처리
            if (file_exists($destination)) {
                $info = pathinfo($destination);
                $base = $info['filename'];
                $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
                $dir = $info['dirname'];
                $c = 1;
                while (file_exists($destination)) {
                    $destination = $dir . '/' . $base . '_' . $c . $ext;
                    $c++;
                }
            }
            
            if (rename($tmpFile, $destination)) {
                @unlink($sessionFile);
                $cacheFile = dirname($destination) . '/.folder_cache.json';
                if (file_exists($cacheFile)) @unlink($cacheFile);
                
                log_user_activity('파일 업로드(청크)', basename($destination));
                echo json_encode(['success' => true, 'message' => __('api_upload_complete'), 'file' => basename($destination)]);
            } else {
                // 이동 실패 시 임시파일 정리
                @unlink($tmpFile);
                @unlink($sessionFile);
                echo json_encode(['success' => false, 'error' => __('api_file_move_failed')]);
            }
            break;
            
        case 'upload_file':
            if (!isset($_FILES['upload_files']) || empty($_FILES['upload_files']['name'][0])) {
                echo json_encode(['success' => false, 'error' => __('api_select_file')]);
                break;
            }
            
            $uploaded = 0;
            $failed = 0;
            $errors = [];
            
            // 허용 확장자 (myComix 지원 형식만)
            $allowed_ext = [
                // 압축 파일 (만화)
                'zip', 'cbz', 'rar', 'cbr', '7z', 'cb7',
                // 이미지 (이미지 폴더용)
                'jpg', 'jpeg', 'png', 'gif', 'webp',
                // 동영상
                'mp4', 'webm', 'mkv', 'avi', 'mov', 'm4v', 'm2t', 'ts', 'mts', 'm2ts', 'wmv', 'flv',
                // 문서
                'pdf', 'epub', 'txt', 'hwp', 'hwpx',
                // 오피스
                'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'
            ];
            
            $files = $_FILES['upload_files'];
            $file_count = count($files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $failed++;
                    continue;
                }
                
                $original_name = $files['name'][$i];
                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed_ext)) {
                    $errors[] = "{$original_name}: " . __("api_disallowed_type");
                    $failed++;
                    continue;
                }
                
                // 파일명 정리
                $safe_name = preg_replace('/[<>:"\\|?*\x00-\x1f]/', '', $original_name);
                $destination = $target_base . '/' . $safe_name;
                
                // 중복 파일 처리
                if (file_exists($destination)) {
                    $name_part = pathinfo($safe_name, PATHINFO_FILENAME);
                    $counter = 1;
                    while (file_exists($destination)) {
                        $destination = $target_base . '/' . $name_part . '_' . $counter . '.' . $ext;
                        $counter++;
                    }
                }
                
                if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    $uploaded++;
                } else {
                    $errors[] = "{$original_name}: " . __("api_upload_fail");
                    $failed++;
                }
            }
            
            if ($uploaded > 0) {
                log_user_activity('파일 업로드', "{$uploaded}개 파일 업로드 to {$target_base}");
            }
            
            $message = __('api_upload_count', $uploaded);
            if ($failed > 0) {
                $message .= ", " . __("api_upload_failed_count", $failed);
            }
            
            echo json_encode([
                'success' => $uploaded > 0,
                'message' => $message,
                'uploaded' => $uploaded,
                'failed' => $failed,
                'errors' => $errors
            ]);
            break;
        
        // ===== 단일 파일 업로드 (파일별 순차 업로드용) =====
        case 'upload_file_single':
            if (!isset($_FILES['upload_file']) || $_FILES['upload_file']['error'] !== UPLOAD_ERR_OK) {
                $err = isset($_FILES['upload_file']) ? $_FILES['upload_file']['error'] : 'no file';
                echo json_encode(['success' => false, 'error' => __('api_file_error', $err)]);
                break;
            }
            
            $original_name = $_FILES['upload_file']['name'];
            $tmp_name = $_FILES['upload_file']['tmp_name'];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            
            // 허용 확장자
            $allowed_ext = [
                'zip', 'cbz', 'rar', 'cbr', '7z', 'cb7',
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'avif',
                'mp4', 'webm', 'mkv', 'avi', 'mov', 'm4v', 'm2t', 'ts', 'mts', 'm2ts', 'wmv', 'flv',
                'pdf', 'epub', 'txt', 'hwp', 'hwpx',
                'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'
            ];
            
            if (!in_array($ext, $allowed_ext)) {
                echo json_encode(['success' => false, 'error' => __('api_disallowed_ext', $ext)]);
                break;
            }
            
            // 파일명 정리 (특수문자 유지, 위험 문자만 제거)
            $safe_name = preg_replace('/[<>:"\/\\\\|?*\x00-\x1f]/', '', $original_name);
            if (trim($safe_name) === '') $safe_name = 'file_' . time() . '.' . $ext;
            
            $destination = $target_base . '/' . $safe_name;
            
            // 기존 파일 있으면 덮어쓰기
            if (file_exists($destination)) {
                if (is_file($destination)) mc_clear_file_caches($destination); // ★ (2026-10-30) 옛 캐시도 정리
                @unlink($destination);
            }
            
            if (move_uploaded_file($tmp_name, $destination)) {
                // 캐시 무효화
                $cacheFile = dirname($destination) . '/.folder_cache.json';
                if (file_exists($cacheFile)) @unlink($cacheFile);
                
                log_user_activity('파일 업로드', basename($destination));
                echo json_encode(['success' => true, 'message' => __('api_upload_complete'), 'file' => basename($destination)]);
            } else {
                echo json_encode(['success' => false, 'error' => __('api_file_save_failed')]);
            }
            break;
            
        case 'delete_file':
            $file_path = $_POST['file_path'] ?? '';
            
            if (empty($file_path)) {
                echo json_encode(['success' => false, 'error' => __('api_no_file_path')]);
                break;
            }
            
            // 경로 검증
            $validated_path = validate_file_path($file_path, $base_dir);
            if ($validated_path === false) {
                echo json_encode(['success' => false, 'error' => __('api_invalid_path')]);
                break;
            }
            
            if (!is_file($validated_path)) {
                echo json_encode(['success' => false, 'error' => __('api_file_not_exists')]);
                break;
            }
            
            $file_name = basename($validated_path);
            
            if (@unlink($validated_path)) {
                // 관련 캐시/메타 파일도 삭제
                $related_files = [
                    // 동영상 썸네일
                    $validated_path . '.video_thumb.jpg',
                    // ZIP/압축파일 관련 JSON
                    get_cache_json_path($validated_path),
                    $validated_path . '.image_files.json',
                    $validated_path . '.video_files.json',
                    // 썸네일 이미지
                    $validated_path . '.thumb.jpg',
                    $validated_path . '.thumb.webp',
                    $validated_path . '.pdf_thumb.jpg',
                    $validated_path . '.epub_thumb.jpg',
                ];
                
                foreach ($related_files as $related) {
                    if ($related && is_file($related)) {
                        @unlink($related);
                    }
                }
                
                // ✅ [B] 삭제된 파일에 연결된 북마크/자동저장/즐겨찾기 항목도 함께 정리(고아 항목 방지).
                //    키는 상대경로($file_path)로 저장되므로 원본 입력 경로로 매칭. best-effort(실패해도 삭제 자체는 성공).
                try {
                    $_clean_key = trim(str_replace('\\', '/', $file_path), '/');
                    $_clean_targets = [];
                    if (function_exists('get_bookmark_file'))  $_clean_targets[] = get_bookmark_file();
                    if (function_exists('get_autosave_file'))  $_clean_targets[] = get_autosave_file();
                    if (function_exists('get_favorites_file')) $_clean_targets[] = get_favorites_file();
                    $_clean_lk = function_exists('mc_rec_lock') && mc_rec_lock(); // ★ (2026-11-09 11:43) 기록 고치기 차례 잠금(bookmark.php 와 같이)
                    foreach ($_clean_targets as $_cf) {
                        if (!$_cf || !is_file($_cf)) continue;
                        $_arr = load_json_with_lock($_cf);
                        if (!is_array($_arr)) continue;
                        $_changed = false;
                        foreach ($_arr as $_k => $_v) {
                            // ★ (2026-11-06 00:50) 지운 파일이 있던 콘텐츠 폴더의 기록만(예전: 다른 콘텐츠 폴더의 같은 경로 기록까지 지웠음) — "bidx|/경로" 키도
                            [$_kb, $_kp] = mc_rec_split($_k, $_v);
                            $_any = !preg_match('#^\d{1,4}\|/#', (string)$_k) && (!is_array($_v) || !isset($_v['bidx'])); // 콘텐츠 폴더 번호가 없는 옛 기록은 예전처럼
                            if (($_any || $_kb === (int)$bidx) && trim(str_replace('\\', '/', (string)$_kp), '/') === $_clean_key) { unset($_arr[$_k]); $_changed = true; }
                        }
                        if ($_changed) save_json_with_lock($_cf, $_arr);
                    }
                } catch (\Throwable $_e) { /* best-effort */ }
                if (!empty($_clean_lk)) mc_rec_lock(true);

                log_user_activity('파일 삭제', $validated_path);
                echo json_encode(['success' => true, 'message' => __('api_file_deleted', $file_name)]);
            } else {
                echo json_encode(['success' => false, 'error' => __('api_file_delete_failed')]);
            }
            break;
            
        case 'delete_folder':
            $folder_path = $_POST['folder_path'] ?? '';
            
            if (empty($folder_path)) {
                echo json_encode(['success' => false, 'error' => __('api_no_folder_path')]);
                break;
            }
            
            // 경로 검증
            $validated_path = validate_file_path($folder_path, $base_dir);
            if ($validated_path === false) {
                echo json_encode(['success' => false, 'error' => __('api_invalid_path')]);
                break;
            }
            
            // base_dir 자체는 삭제 불가
            if (realpath($validated_path) === realpath($base_dir)) {
                echo json_encode(['success' => false, 'error' => __('api_root_no_delete')]);
                break;
            }
            
            if (!is_dir($validated_path)) {
                echo json_encode(['success' => false, 'error' => __('api_folder_not_exists')]);
                break;
            }
            
            $folder_name = basename($validated_path);
            
            // 재귀적 폴더 삭제 함수
            if (!function_exists('deleteDirectory')) {
                function deleteDirectory($dir) {
                    if (!is_dir($dir)) return false;
                    $items = array_diff(scandir($dir), ['.', '..']);
                    foreach ($items as $item) {
                        $path = $dir . '/' . $item;
                        // ★ (2026-11-02 11:37) 링크·정션이면 안으로 들어가지 않고 링크만 지움 — 전에는 따라 들어가 콘텐츠 폴더 밖 원본까지 지웠음
                        $__rp = @realpath($path); $__rd = @realpath($dir);
                        if (is_link($path) || ($__rp !== false && $__rd !== false && strpos(str_replace('\\', '/', $__rp) . '/', rtrim(str_replace('\\', '/', $__rd), '/') . '/') !== 0)) {
                            if (!@unlink($path)) @rmdir($path);
                            continue;
                        }
                        if (is_dir($path)) {
                            deleteDirectory($path);
                        } else {
                            @unlink($path);
                        }
                    }
                    return @rmdir($dir);
                }
            }
            
            if (deleteDirectory($validated_path)) {
                if (function_exists('mc_rec_cleanup_deleted')) mc_rec_cleanup_deleted($bidx, [], [$folder_path]); // ★ (2026-11-06 01:05) 그 폴더와 안의 책 기록(북마크·자동 저장·즐겨찾기) 정리 — 예전엔 남아 '파일 없음'으로 보였음(파일 하나 지우기와 같게)
                log_user_activity('폴더 삭제', $validated_path);
                echo json_encode(['success' => true, 'message' => __('api_folder_deleted', $folder_name)]);
            } else {
                echo json_encode(['success' => false, 'error' => __('api_folder_delete_failed')]);
            }
            break;
            
        case 'delete_multiple':
            $files = json_decode(is_string($_POST['files'] ?? null) ? $_POST['files'] : '[]', true); // ★ (2026-11-02 19:53) 배열로 오면 json_decode TypeError(500)
            $folders = json_decode(is_string($_POST['folders'] ?? null) ? $_POST['folders'] : '[]', true);
            
            if (empty($files) && empty($folders)) {
                echo json_encode(['success' => false, 'error' => __('api_nothing_to_delete')]);
                break;
            }
            
            $deleted_files = 0;
            $deleted_folders = 0;
            $failed = 0;
            $__rc_files = []; $__rc_dirs = []; // ★ (2026-11-06 01:05) 지운 것들의 기록 정리용(아래 끝에서 한 번)
            
            // 재귀적 폴더 삭제 함수
            if (!function_exists('deleteDirectoryRecursive')) {
                function deleteDirectoryRecursive($dir) {
                    if (!is_dir($dir)) return false;
                    $items = array_diff(scandir($dir), ['.', '..']);
                    foreach ($items as $item) {
                        $path = $dir . '/' . $item;
                        // ★ (2026-11-02 11:37) 링크·정션이면 안으로 들어가지 않고 링크만 지움 — 전에는 따라 들어가 콘텐츠 폴더 밖 원본까지 지웠음
                        $__rp = @realpath($path); $__rd = @realpath($dir);
                        if (is_link($path) || ($__rp !== false && $__rd !== false && strpos(str_replace('\\', '/', $__rp) . '/', rtrim(str_replace('\\', '/', $__rd), '/') . '/') !== 0)) {
                            if (!@unlink($path)) @rmdir($path);
                            continue;
                        }
                        if (is_dir($path)) {
                            deleteDirectoryRecursive($path);
                        } else {
                            @unlink($path);
                        }
                    }
                    return @rmdir($dir);
                }
            }
            
            // 파일 삭제
            foreach ((is_array($files) ? $files : []) as $file_path) {
                if (!is_string($file_path)) continue; // ★ (2026-11-02 19:05) 배열 원소면 TypeError(500)
                $validated_path = validate_file_path($file_path, $base_dir);
                if ($validated_path === false || !is_file($validated_path)) {
                    $failed++;
                    continue;
                }
                
                if (@unlink($validated_path)) {
                    // 관련 캐시/메타 파일도 삭제
                    $related_files = [
                        $validated_path . '.video_thumb.jpg',
                        get_cache_json_path($validated_path),
                        $validated_path . '.image_files.json',
                        $validated_path . '.video_files.json',
                        $validated_path . '.thumb.jpg',
                        $validated_path . '.thumb.webp',
                        $validated_path . '.pdf_thumb.jpg',
                        $validated_path . '.epub_thumb.jpg',
                    ];
                    foreach ($related_files as $related) {
                        if ($related && is_file($related)) {
                            @unlink($related);
                        }
                    }
                    $deleted_files++;
                    $__rc_files[] = $file_path;
                } else {
                    $failed++;
                }
            }
            
            // 폴더 삭제
            foreach ((is_array($folders) ? $folders : []) as $folder_path) {
                if (!is_string($folder_path)) continue; // ★ (2026-11-02 19:05) 배열 원소면 TypeError(500)
                $validated_path = validate_file_path($folder_path, $base_dir);
                if ($validated_path === false || !is_dir($validated_path)) {
                    $failed++;
                    continue;
                }
                
                // base_dir 자체는 삭제 불가
                if (realpath($validated_path) === realpath($base_dir)) {
                    $failed++;
                    continue;
                }
                
                if (deleteDirectoryRecursive($validated_path)) {
                    $deleted_folders++;
                    $__rc_dirs[] = $folder_path;
                } else {
                    $failed++;
                }
            }
            
            $total_deleted = $deleted_files + $deleted_folders;
            if (($__rc_files || $__rc_dirs) && function_exists('mc_rec_cleanup_deleted')) mc_rec_cleanup_deleted($bidx, $__rc_files, $__rc_dirs); // ★ (2026-11-06 01:05) 파일 하나 지우기처럼 기록도 정리
            if ($total_deleted > 0) {
                log_user_activity('다중 삭제', "파일 {$deleted_files}개, 폴더 {$deleted_folders}개 삭제");
            }
            
            $message = '';
            if ($deleted_files > 0) $message .= __("api_deleted_files_count", $deleted_files) . " ";
            if ($deleted_folders > 0) $message .= __("api_deleted_folders_count", $deleted_folders) . " ";
            $message .= __("api_delete_complete");
            if ($failed > 0) $message .= " (" . __("api_failed_count", $failed) . ")";
            
            echo json_encode([
                'success' => $total_deleted > 0,
                'message' => $message,
                'deleted_files' => $deleted_files,
                'deleted_folders' => $deleted_folders,
                'failed' => $failed
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => __('api_unknown_action')]);
    }
    exit;
}

// ✅ 브랜딩 설정 로드 (function.php의 공통 함수 사용)
$_branding = load_branding();

// 동영상 썸네일 요청 처리
if (isset($_GET['thumb']) && $_GET['thumb'] === 'video' && isset($_GET['file'])) {
    
    $getfile = decode_file_param($_GET['file']);
    
    // ✅ 경로 검증 통일 (validate_file_path 사용)
    $video_file = validate_file_path($getfile, $base_dir);
    if ($video_file === false) {
        log_user_activity('해킹시도', 'video thumb: ' . substr($getfile, 0, 100));
        http_response_code(403);
        exit;
    }
    
    if (!mc_path_allowed($getfile)) { // ★ (2026-10-30) 폴더 권한 — ★ (2026-11-02 09:55) 파일 있는지 보기 전에(권한 없는 폴더의 파일 존재가 403/404 로 드러나던 것)
        http_response_code(403);
        exit;
    }
    if (!is_file($video_file) || !is_video_file($video_file)) {
        http_response_code(404);
        exit;
    }
    
    $thumb_path = $video_file . '.video_thumb.jpg';
    if (function_exists('mc_file_cover_path') && is_file(mc_file_cover_path($video_file))) $thumb_path = mc_file_cover_path($video_file); // ★ (2026-11-10 13:53, 펜닐 요청 — fix98) 관리자가 직접 고른 파일 표지 먼저
    
    if (file_exists($thumb_path)) {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($thumb_path));
        // ★ (2026-11-11 07:59 fix111) 직접 고른 표지를 cv 가 맞지 않는 주소로 줄 땐 저장 안 함(thumb.php 와 같음 — 자동으로 되돌린 뒤 지운 표지가 남아 보이던 것)
        header((strpos($thumb_path, '.video_thumb.jpg') === false && (string)($_GET['cv'] ?? '') !== (string)(int)@filemtime($thumb_path)) ? 'Cache-Control: no-store' : 'Cache-Control: private, max-age=86400');
        readfile($thumb_path);
        exit;
    }
    
    // 썸네일이 없으면 404
    http_response_code(404);
    exit;
}

//error_log("[index.php] " . date('Y-m-d H:i:s') . " 요청 시작: " . $_SERVER['REQUEST_URI']);
?>
<?php
// ===== 2FA 설정 AJAX 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_action']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => __('api_csrf_invalid')]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    // ★ (2026-11-02 10:03) 글자 칸은 문자열만(배열이면 strlen·trim·hash 에서 500)
    foreach ($_POST as $__pk => $__pv) { if (is_array($__pv)) $_POST[$__pk] = ''; }
    $action = $_POST['totp_action'];
    
    switch ($action) {
        case 'get_status':
            // 현재 2FA 상태 조회
            $settings = load_user_totp($user_id);
            $enabled = !empty($settings['enabled']) && !empty($settings['secret']);
            $has_secret = !empty($settings['secret']);
            $backup_count = count($settings['backup_codes'] ?? []);
            
            echo json_encode([
                'success' => true,
                'enabled' => $enabled,
                'has_secret' => $has_secret,
                'backup_count' => $backup_count,
                'enabled_at' => $settings['enabled_at'] ?? null
            ]);
            break;
            
        case 'generate':
            // 비밀키 생성
            // ★ (2026-10-30) 이미 켜진 2단계 인증을 OTP 확인 없이 새 키로 바꿔 끄던 문제 — 켜져 있으면 먼저 '끄기'(OTP 확인)를 거쳐야 함
            $__cur = load_user_totp($user_id);
            if (!empty($__cur['enabled']) && !empty($__cur['secret'])) {
                echo json_encode(['success' => false, 'error' => __('api_2fa_already_on')]);
                break;
            }
            $secret = generate_totp_secret();
            $backup_codes = generate_backup_codes(10);
            
            $settings = load_user_totp($user_id);
            $settings['secret'] = $secret;
            $settings['backup_codes'] = $backup_codes;
            $settings['created_at'] = date('Y-m-d H:i:s');
            $settings['enabled'] = false;
            save_user_totp($user_id, $settings);
            
            $qr_url = get_totp_qr_url($secret, $user_id, 'myComix');
            
            echo json_encode([
                'success' => true,
                'secret' => $secret,
                'qr_url' => $qr_url,
                'backup_codes' => $backup_codes
            ]);
            break;
            
        case 'enable':
            // 2FA 활성화 (OTP 검증 후)
            $otp_code = $_POST['otp_code'] ?? '';
            $settings = load_user_totp($user_id);
            
            if (empty($settings['secret'])) {
                echo json_encode(['success' => false, 'error' => __('api_2fa_generate_first')]);
                break;
            }
            
            if (!verify_totp($settings['secret'], $otp_code)) {
                echo json_encode(['success' => false, 'error' => __('api_2fa_invalid_otp')]);
                break;
            }
            
            $settings['enabled'] = true;
            $settings['enabled_at'] = date('Y-m-d H:i:s');
            save_user_totp($user_id, $settings);
            
            echo json_encode(['success' => true, 'message' => __('api_2fa_enabled')]);
            break;
            
        case 'disable':
            // 2FA 비활성화
            $otp_code = $_POST['otp_code'] ?? '';
            $settings = load_user_totp($user_id);
            
            if (mc_reauth_locked()) { echo json_encode(['success' => false, 'error' => __('api_reauth_locked')]); break; } // ★ (2026-11-08 전체 점검 4차 fix77) 틀린 횟수 초과면 확인하지 않음
            if (!verify_totp($settings['secret'] ?? '', $otp_code) && !verify_user_backup_code($user_id, $otp_code)) {
                mc_reauth_fail($user_id);
                echo json_encode(['success' => false, 'error' => __('api_2fa_invalid_code')]);
                break;
            }
            
            $settings = load_user_totp($user_id); // ★ (2026-11-02 08:48) 백업 코드로 껐으면 그 코드가 빠진 최신 설정으로 — 먼저 읽어 둔 값으로 덮어써 쓴 코드가 되살아났음
            $settings['enabled'] = false;
            save_user_totp($user_id, $settings);
            
            echo json_encode(['success' => true, 'message' => __('api_2fa_disabled')]);
            break;
            
        case 'reset':
            // 2FA 완전 삭제
            $confirm = $_POST['confirm'] ?? '';
            if ($confirm !== 'RESET') {
                echo json_encode(['success' => false, 'error' => __('api_2fa_confirm_mismatch')]);
                break;
            }
            // ★ (2026-10-30) 현재 비밀번호 확인 — 세션만 가로챈 사람이 2단계 인증을 지우지 못하게
            //   (OTP 대신 비밀번호: 인증 앱을 잃어버린 경우에도 로그인한 본인은 지울 수 있게)
            $__rp = $_POST['current_password'] ?? '';
            $__ua = load_users();
            if (mc_reauth_locked()) { echo json_encode(['success' => false, 'error' => __('api_reauth_locked')]); break; } // ★ (2026-11-08 전체 점검 4차 fix77) 틀린 횟수 초과면 확인하지 않음
            if (!is_string($__rp) || $__rp === '' || !isset($__ua[$user_id]['pass']) || !password_verify(hash('sha256', $__rp), $__ua[$user_id]['pass'])) {
                mc_reauth_fail($user_id);
                echo json_encode(['success' => false, 'error' => __('api_current_password_wrong')]);
                break;
            }
            
            delete_user_totp($user_id);
            echo json_encode(['success' => true, 'message' => __('api_2fa_deleted')]);
            break;
            
        case 'regenerate_backup':
            // 백업 코드 재생성
            $settings = load_user_totp($user_id);
            // ★ (2026-10-30) 켜져 있으면 OTP(또는 기존 백업 코드) 확인 — 세션만 가로챈 사람이 새 코드를 받아 2단계 인증을 끄지 못하게
            if (!empty($settings['enabled']) && !empty($settings['secret'])) {
                $__oc = is_string($_POST['otp_code'] ?? null) ? trim($_POST['otp_code']) : '';
                if (mc_reauth_locked()) { echo json_encode(['success' => false, 'error' => __('api_reauth_locked')]); break; } // ★ (2026-11-08 전체 점검 4차 fix77) 틀린 횟수 초과면 확인하지 않음
                if ($__oc === '' || (!verify_totp($settings['secret'], $__oc) && !verify_user_backup_code($user_id, $__oc))) {
                    mc_reauth_fail($user_id);
                    echo json_encode(['success' => false, 'error' => __('api_2fa_invalid_code')]);
                    break;
                }
                $settings = load_user_totp($user_id); // 백업 코드를 썼으면 그 코드가 빠진 최신 상태로
            }
            $settings['backup_codes'] = generate_backup_codes(10);
            save_user_totp($user_id, $settings);
            
            echo json_encode([
                'success' => true,
                'backup_codes' => $settings['backup_codes'],
                'message' => __('api_2fa_backup_regenerated')
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => __('api_unknown_request')]);
    }
    exit;
}
?>
<?php
// ===== 프로필 설정 AJAX 처리 (비밀번호, 이메일 변경) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_action']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => __('api_csrf_invalid')]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    // ★ (2026-11-02 10:03) 글자 칸은 문자열만(배열이면 strlen·trim·hash 에서 500)
    foreach ($_POST as $__pk => $__pv) { if (is_array($__pv)) $_POST[$__pk] = ''; }
    $action = $_POST['profile_action'];
    
    switch ($action) {
        case 'get_info':
            // 현재 사용자 정보 조회
            $ua = load_users();
            $user = $ua[$user_id] ?? [];
            
            // 현재 접속 IP 및 국가 정보
            $current_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ?: 'unknown';
            $current_country = 'UNKNOWN';
            if (file_exists(__DIR__ . '/ip_block.php')) {
                require_once __DIR__ . '/ip_block.php';
                $ipBlockerProfile = new IPBlocker(__DIR__ . '/src/ip_block_settings.json');
                $current_country = $ipBlockerProfile->getCountryByIP($current_ip);
            }
            
            echo json_encode([
                'success' => true,
                'email' => $user['email'] ?? '',
                'created_at' => $user['created_at'] ?? '',
                'group' => $user['group'] ?? 'group2',
                'current_ip' => $current_ip,
                'current_country' => $current_country
            ]);
            break;
            
        case 'change_password':
            // 비밀번호 변경
            $current_pass = $_POST['current_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';
            
            if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
                echo json_encode(['success' => false, 'error' => __('api_fill_all_fields')]);
                break;
            }
            
            if (strlen($new_pass) < 8) {
                echo json_encode(['success' => false, 'error' => __('api_password_min_length')]);
                break;
            }
            
            if ($new_pass !== $confirm_pass) {
                echo json_encode(['success' => false, 'error' => __('api_password_mismatch')]);
                break;
            }
            
            $ua = load_users();
            if (!isset($ua[$user_id])) {
                echo json_encode(['success' => false, 'error' => __('api_user_not_found')]);
                break;
            }
            
            // 현재 비밀번호 확인
            $hashed_current = hash("sha256", $current_pass);
            if (mc_reauth_locked()) { echo json_encode(['success' => false, 'error' => __('api_reauth_locked')]); break; } // ★ (2026-11-08 전체 점검 4차 fix77) 틀린 횟수 초과면 확인하지 않음
            if (!password_verify($hashed_current, $ua[$user_id]['pass'])) {
                mc_reauth_fail($user_id);
                echo json_encode(['success' => false, 'error' => __('api_current_password_wrong')]);
                break;
            }
            
            // 새 비밀번호 저장
            $ua[$user_id]['pass'] = password_hash(hash("sha256", $new_pass), PASSWORD_DEFAULT);
            $ua[$user_id]['password_changed_at'] = date('Y-m-d H:i:s');
            unset($ua[$user_id]['must_change_password']); // 비밀번호 변경 필요 플래그 제거
            save_users($ua);
            $_SESSION['mc_pw_seen'] = $ua[$user_id]['password_changed_at']; // ★ (2026-11-02 08:51) 내가 바꾼 이 세션은 그대로(다른 세션만 끝남 — init.php)
            // ★ (2026-11-02 14:29) 다른 기기의 '로그인 유지' 토큰도 지움(지금 이 기기 것만 남김) — 세션이 만료된 기기는 토큰으로 계속 자동 로그인됐음
            if (function_exists('load_remember_tokens') && function_exists('save_remember_tokens')) {
                $__keep = (isset($_COOKIE['remember_token']) && is_string($_COOKIE['remember_token'])) ? $_COOKIE['remember_token'] : '';
                $__flt = function($t, $k) use ($user_id, $__keep) {
                    return (string)(is_array($t) ? ($t['user_id'] ?? '') : '') !== (string)$user_id || ($__keep !== '' && (string)$k === $__keep);
                };
                if (function_exists('mc_remember_tokens_modify')) { // ★ fix54 읽기·저장을 한 번의 잠금 안에서(security_helpers.php fix53 과 같게 — 그 사이 다른 기기가 받은 새 토큰을 덮어쓰지 않게)
                    mc_remember_tokens_modify(function (&$__tk, &$__ch) use ($__flt) { $__n = count($__tk); $__tk = array_filter($__tk, $__flt, ARRAY_FILTER_USE_BOTH); $__ch = count($__tk) !== $__n; return true; });
                } else {
                    $__tk = load_remember_tokens();
                    $__tk2 = array_filter($__tk, $__flt, ARRAY_FILTER_USE_BOTH);
                    if (count($__tk2) !== count($__tk)) save_remember_tokens($__tk2);
                }
            }
            
            echo json_encode(['success' => true, 'message' => __('api_password_changed')]);
            break;
            
        case 'change_email':
            // 이메일 변경
            $new_email = trim($_POST['new_email'] ?? '');
            
            if (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => __('api_invalid_email')]);
                break;
            }
            
            $ua = load_users();
            if (!isset($ua[$user_id])) {
                echo json_encode(['success' => false, 'error' => __('api_user_not_found')]);
                break;
            }
            // ★ (2026-11-02 14:29) 현재 비밀번호 확인 — 세션만 가진 사람이 이메일을 바꾼 뒤 '비밀번호 찾기'로 계정을 가져갈 수 있었음(2단계 인증 삭제와 같은 방식)
            $__ep = $_POST['current_password'] ?? '';
            if (mc_reauth_locked()) { echo json_encode(['success' => false, 'error' => __('api_reauth_locked')]); break; } // ★ (2026-11-08 전체 점검 4차 fix77) 틀린 횟수 초과면 확인하지 않음
            if (!is_string($__ep) || $__ep === '' || !isset($ua[$user_id]['pass']) || !password_verify(hash('sha256', $__ep), $ua[$user_id]['pass'])) {
                mc_reauth_fail($user_id);
                echo json_encode(['success' => false, 'error' => __('api_current_password_wrong')]);
                break;
            }
            
            // 이메일 중복 체크
            if (!empty($new_email)) {
                foreach ($ua as $uid => $udata) {
                    if ((string)$uid !== (string)$user_id && isset($udata['email']) /* ★ (2026-11-02 09:55) 숫자만 된 아이디는 키가 정수라 자기 자신도 '사용 중'이던 것 */ && strtolower($udata['email']) === strtolower($new_email)) {
                        echo json_encode(['success' => false, 'error' => __('api_email_in_use')]);
                        break 2;
                    }
                }
            }
            
            // 이메일 저장
            $ua[$user_id]['email'] = $new_email;
            save_users($ua);
            
            $msg = empty($new_email) ? __('api_email_removed') : __('api_email_changed');
            echo json_encode(['success' => true, 'message' => $msg, 'email' => $new_email]);
            break;
            
        case 'get_login_logs':
            // 해당 사용자의 로그인 기록 조회
            $page = max(1, intval($_POST['page'] ?? 1));
            $per_page = 10;
            
            $log_file = __DIR__ . '/src/login_log.json';
            $all_logs = [];
            if (file_exists($log_file)) {
                $all_logs = function_exists('load_json_with_lock') ? load_json_with_lock($log_file) : (json_decode(file_get_contents($log_file), true) ?? []); // ★ (2026-11-02 11:38) 공유 잠금
            }
            
            // 현재 사용자의 로그만 필터링
            $user_logs = array_filter($all_logs, function($log) use ($user_id) {
                return ($log['user_id'] ?? '') === $user_id;
            });
            
            // 최신순 정렬
            $user_logs = array_reverse(array_values($user_logs));
            
            $total = count($user_logs);
            $total_pages = max(1, ceil($total / $per_page));
            $page = min($page, $total_pages);
            $offset = ($page - 1) * $per_page;
            
            $paged_logs = array_slice($user_logs, $offset, $per_page);
            
            echo json_encode([
                'success' => true,
                'logs' => $paged_logs,
                'total' => $total,
                'page' => $page,
                'total_pages' => $total_pages,
                'per_page' => $per_page
            ]);
            break;
        
        case 'withdraw':
            // 회원 탈퇴 처리
            $password = $_POST['password'] ?? '';
            
            if (empty($password)) {
                echo json_encode(['success' => false, 'error' => __('api_enter_password')]);
                break;
            }
            
            $ua = load_users();
            if (!isset($ua[$user_id])) {
                echo json_encode(['success' => false, 'error' => __('api_user_not_found')]);
                break;
            }
            
            // 관리자는 탈퇴 불가
            if (($ua[$user_id]['group'] ?? '') === 'admin') {
                echo json_encode(['success' => false, 'error' => __('api_admin_no_withdraw')]);
                break;
            }
            
            // 비밀번호 확인
            $hashed_pass = hash("sha256", $password);
            if (mc_reauth_locked()) { echo json_encode(['success' => false, 'error' => __('api_reauth_locked')]); break; } // ★ (2026-11-08 전체 점검 4차 fix77) 틀린 횟수 초과면 확인하지 않음
            if (!password_verify($hashed_pass, $ua[$user_id]['pass'])) {
                mc_reauth_fail($user_id);
                echo json_encode(['success' => false, 'error' => __('api_password_wrong')]);
                break;
            }
            
            // ✅ 삭제 전 사용자 정보 백업
            $user_info = $ua[$user_id];
            unset($user_info['pass']); // 비밀번호는 제외
            
            // 로그인 기록 수집
            $login_logs = [];
            $log_file = __DIR__ . '/src/login_log.json';
            if (file_exists($log_file)) {
                $all_logs = json_decode(file_get_contents($log_file), true) ?? [];
                $login_logs = array_values(array_filter($all_logs, function($log) use ($user_id) {
                    return ($log['user_id'] ?? '') === $user_id;
                }));
            }
            
            // 활동 로그 수집
            $activity_logs = [];
            $activity_file = __DIR__ . '/src/activity_log.json';
            if (file_exists($activity_file)) {
                $all_activities = json_decode(file_get_contents($activity_file), true) ?? [];
                $activity_logs = array_values(array_filter($all_activities, function($log) use ($user_id) {
                    return ($log['user_id'] ?? '') === $user_id;
                }));
            }
            
            // deleted_users.json에 저장
            $deleted_file = __DIR__ . '/src/deleted_users.json';
            $deleted_users = [];
            // ★ (2026-10-30) 읽기~쓰기를 한 잠금 안에서(동시에 탈퇴·삭제가 겹치면 기록이 사라지던 문제)
            $__dfp = @fopen($deleted_file, 'c+');
            $__dbroken = false;
            if ($__dfp) { flock($__dfp, LOCK_EX); $__raw = stream_get_contents($__dfp); $deleted_users = trim((string)$__raw) === '' ? [] : json_decode((string)$__raw, true); if (!is_array($deleted_users)) { $deleted_users = []; $__dbroken = true; } } // ★ (2026-11-04 12:41) 깨진 파일이면 덮어쓰지 않음(관리자 삭제와 같게 — 전엔 [] 로 바꿔 기존 기록이 사라졌음)
            $deleted_users[] = [
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => 'self',
                'user_id' => $user_id,
                'user_info' => $user_info,
                'login_logs' => $login_logs,
                'activity_logs' => $activity_logs
            ];
            $__djson = json_encode($deleted_users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($__dfp) {
                if ($__dbroken) error_log('[mycomix] deleted_users.json 이 깨져 탈퇴 기록을 더하지 않음');
                elseif ($__djson !== false) { ftruncate($__dfp, 0); rewind($__dfp); fwrite($__dfp, $__djson); fflush($__dfp); }
                flock($__dfp, LOCK_UN); fclose($__dfp);
            } elseif ($__djson !== false) {
                file_put_contents($deleted_file, $__djson, LOCK_EX);
            }
            
            // 사용자 삭제
            unset($ua[$user_id]);
            save_users($ua);
            
            // ✅ 해당 사용자의 모든 관련 파일 삭제
            $safe_id = preg_replace('/[^a-zA-Z0-9_]/', '', $user_id);
            $user_files = [
                __DIR__ . '/src/' . $safe_id . '_bookmark.json',
                __DIR__ . '/src/' . $safe_id . '_autosave.json',
                __DIR__ . '/src/' . $safe_id . '_favorites.json',
                __DIR__ . '/src/' . $safe_id . '_recent.json',
                __DIR__ . '/src/' . $safe_id . '_epub_progress.json',
                __DIR__ . '/src/' . $safe_id . '_txt_progress.json',
                __DIR__ . '/src/' . $safe_id . '_notes.json', // ★ (2026-10-31) 책 노트
                __DIR__ . '/src/' . $safe_id . '_dims.json', // ★ (2026-10-30) 뷰어 이미지 크기 캐시도 함께
            ];
            foreach ($user_files as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
            // ★ (2026-11-02 08:49) 서재 읽기 기록(library.db)도
            if (is_file(__DIR__ . '/library.php')) { require_once __DIR__ . '/library.php'; if (function_exists('mc_lib_forget_user')) mc_lib_forget_user($safe_id); }
            
            // ✅ 해당 사용자의 로그인 기록 삭제
            if (file_exists($log_file)) {
                $fp = fopen($log_file, 'c+');
                if ($fp && flock($fp, LOCK_EX)) {
                    $content = stream_get_contents($fp);
                    $logs = json_decode($content, true);
                    $__log_ok = is_array($logs) || trim((string)$content) === ''; // ★ (2026-11-02 20:05) 깨진 파일이면 빈 배열로 덮어써 전체 기록이 지워지던 것 — 그때는 쓰지 않음
                    if (!is_array($logs)) $logs = [];
                    
                    // 해당 사용자의 로그만 제거
                    $logs = array_filter($logs, function($log) use ($user_id) {
                        return ($log['user_id'] ?? '') !== $user_id;
                    });
                    $logs = array_values($logs); // 인덱스 재정렬
                    
                    if ($__log_ok) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fflush($fp);
                    }
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }
            }
            
            // ✅ 해당 사용자의 활동 로그 삭제
            $activity_file = __DIR__ . '/src/activity_log.json';
            if (file_exists($activity_file)) {
                $fp = fopen($activity_file, 'c+');
                if ($fp && flock($fp, LOCK_EX)) {
                    $content = stream_get_contents($fp);
                    $logs = json_decode($content, true);
                    $__log_ok = is_array($logs) || trim((string)$content) === ''; // ★ (2026-11-02 20:05) 깨진 파일이면 빈 배열로 덮어써 전체 기록이 지워지던 것 — 그때는 쓰지 않음
                    if (!is_array($logs)) $logs = [];
                    
                    $logs = array_filter($logs, function($log) use ($user_id) {
                        return ($log['user_id'] ?? '') !== $user_id;
                    });
                    $logs = array_values($logs);
                    
                    if ($__log_ok) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fflush($fp);
                    }
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }
            }
            
            // ✅ 자동 로그인 토큰 삭제 (모든 기기)
            if (function_exists('delete_user_remember_tokens')) {
                delete_user_remember_tokens($user_id);
            }
            
            // ✅ 2FA/TOTP 설정 삭제
            if (function_exists('delete_user_totp')) {
                delete_user_totp($user_id);
            }
            
            // ✅ remember_token 쿠키 삭제
            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
            
            // 세션 종료
            session_unset();
            session_destroy();
            
            echo json_encode(['success' => true, 'message' => __('api_account_deleted')]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => __('api_unknown_request')]);
    }
    exit;
}
?>
<?php
// ===== 캐시 재생성 처리 (HTML 출력 전에 위치) =====
// ✅ rebuild_folder_caches() 함수는 cache_util.php에서 제공
// ✅ POST 방식 + CSRF 토큰 검증으로 보안 강화
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_param('rebuild_cache', 'string') === '1' && isset($_SESSION['user_id']) && $_SESSION['user_group'] === "admin") {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => __('api_csrf_expired')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $deleted_count = rebuild_folder_caches($base_dir);
    
    // 헤더 설정을 더 명확하게
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // JSON 응답 (한글 처리 개선)
    $response = array(
        'success' => true,
        'message' => __('api_cache_deleted', $deleted_count),
        'deleted_count' => $deleted_count
    );
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ★ (2026-11-03 10:50) 배경 갱신 요청 — 세션을 바로 닫고(다른 화면을 막지 않게) 서버 쪽에서 할 때인지·이미 도는지 판단(mc_bg_refresh_run)
if (get_param('mc_bg', 'string') === '1' && isset($_SESSION['user_id'])) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $__bg = mc_bg_refresh_run(is_array($base_dirs ?? null) ? $base_dirs : []);
    if (!(isset($_SESSION['user_group']) && trim((string)$_SESSION['user_group']) === 'admin')) $__bg = ['status' => $__bg['status'] ?? '']; // ★ (2026-11-03 11:10) 회원에겐 결과(전체 폴더 수 등) 없이 상태만
    echo json_encode($__bg, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<html lang="<?php echo get_html_lang(); ?>">
<head>
<?php if (function_exists('mc_theme_early_head')) echo mc_theme_early_head(true); /* ★ (2026-11-08 fix82) true = 투명했다 나타나는 화면(다크면 바탕은 어둡게 두고 내용만) / ★ (2026-11-08 fix80) 다크모드 흰 바탕 깜빡임 — 첫 그림부터 어둡게 */ ?>
	<script>
	// ★ fix50 아래 막대 ✕(또는 검색창 비우고 검색)로 닫은 검색에 뒤로 가기·새로고침으로 오면 검색 결과를 그리지 않음 — 그 사이 결과가 번쩍 보이지 않게 가림, 4초 뒤엔 무조건 다시 보임
	// ★ fix57 (펜닐 제보) 닫은 뒤 뒤로 가기를 누르면 검색으로 지나온 화면(S3·S2·S1)이 하나씩 '검색어만 뺀 같은 화면'으로 바뀌어 새로고침처럼 여러 번 보였음
	//   → 뒤로 가기·앞으로 가기로 닫은 검색 화면에 오면 그 방향으로 계속 건너뜀(한 번에 검색 전 화면으로). 방향은 목록 화면마다 방문 기록에 붙인 번호(history.state.mcIdx)로 앎
	//   · 더 갈 곳이 없거나 방향을 모르면(새로고침 등) 예전처럼 검색어만 뺀 주소로(fix50·fix52) · 검색 전 화면 검색칸에 브라우저가 닫은 검색어를 되살려 넣으면 지움
	try { (function () {
		var S = sessionStorage, D = document.documentElement, tm = 0;
		var n = function (x) { x = String(x || '').trim(); try { x = x.normalize('NFC'); } catch (e) {} return x; };
		function stamp() { // 방문 기록 칸 번호 — 뒤로(작아짐)·앞으로(커짐)
			var hs = history.state, idx = (hs && typeof hs === 'object') ? (+hs.mcIdx || 0) : 0;
			if (!idx) { idx = (+S.getItem('mc_hseq') || 0) + 1; S.setItem('mc_hseq', String(idx)); try { history.replaceState(Object.assign({}, (hs && typeof hs === 'object') ? hs : {}, { mcIdx: idx }), ''); } catch (e) {} }
			var last = +S.getItem('mc_hlast') || 0; S.setItem('mc_hlast', String(idx));
			return (last && idx < last) ? 'back' : ((last && idx > last) ? 'fwd' : '');
		}
		function closedQ() { var st = JSON.parse(S.getItem('mc_sf_seen') || 'null'); /* fix51 탭마다 따로 */ return (st && st.closed) ? n(st.q) : null; }
		function stripUrl() { var v = new URL(location.href); v.searchParams.delete('q'); v.searchParams.delete('sq'); v.hash = ''; return v.toString(); }
		function run(t, dir) {
			var u = new URLSearchParams(location.search), q = n(u.get('q') || u.get('sq') || ''), cq = closedQ();
			if (!q) { // 검색 화면이 아님 — 뒤로 가기로 왔을 때 검색칸에 닫은 검색어가 되살아나 있으면 지움
				if (cq && t === 'back_forward') { var clr = function () { try { document.querySelectorAll('form input[name="q"]').forEach(function (i) { if (n(i.value) === cq) i.value = ''; }); } catch (e) {} };
					clr(); document.addEventListener('DOMContentLoaded', clr); window.addEventListener('load', function () { setTimeout(clr, 0); }); }
				return;
			}
			if ((t !== 'back_forward' && t !== 'reload') || cq === null || cq !== q) return;
			D.style.visibility = 'hidden'; setTimeout(function () { D.style.visibility = ''; }, 4000);
			if (t === 'back_forward' && (dir === 'back' || dir === 'fwd')) {
				window.__mcSfSkip = true; // 페이지 끝 mc_sf_js·아래 DOMContentLoaded 의 '검색어만 뺀 주소로 옮기기' 대신 건너뜀
				var here = location.href;
				if (dir === 'back') history.back(); else history.forward();
				tm = setTimeout(function () { if (location.href === here) { window.__mcSfSkip = false; try { location.replace(stripUrl()); } catch (e) { D.style.visibility = ''; } } }, 1500); // 더 갈 기록이 없으면 예전처럼
				return;
			}
			document.addEventListener('DOMContentLoaded', function () { if (window.__mcSfSkip || document.getElementById('mcSfBar')) return; try { location.replace(stripUrl()); } catch (e) { D.style.visibility = ''; } }); // ★ fix52 막대 없는 같은 검색 화면
		}
		var ne = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
		run(ne && ne.type, stamp());
		window.addEventListener('pagehide', function () { if (tm) { clearTimeout(tm); tm = 0; } }); // 건너뛰는 중 저장된 화면(bfcache)에서 늦게 깨어 옮기지 않게
		window.addEventListener('pageshow', function (e) { if (e.persisted) { window.__mcSfSkip = false; run('back_forward', stamp()); } }); // 저장된 화면으로 돌아옴
	})(); } catch (e) {}
	</script>
	<script>
	// ★ (2026-10-29) 목록 보기(☰) 상태를 그리기 전에 적용(깜빡임 방지). 저장소 실패 시 기본(썸네일 그리드)
	try { if (localStorage.getItem('mcListView') === '1') document.documentElement.classList.add('mc-list-view'); } catch (e) {}
	</script>
	<script>
	// ✅ 탭 열기 시 캐시 플래그 초기화 (새 탭/브라우저 재시작 시 실행)
	if (!sessionStorage.getItem('tab_init')) {
		sessionStorage.setItem('tab_init', '1'); // 먼저 설정하여 무한 루프 방지
		// ✅ 뒤로/앞으로 복귀(back_forward)에서는 리로드하지 않음:
		//    iOS가 몇 시간 뒤 탭을 폐기하면 sessionStorage가 비워져 'tab_init'이 사라지는데,
		//    그 상태로 뒤로가기하면 새 탭으로 오인해 location.reload()가 돌아 흰 화면이 깜빡였음.
		//    캐시 초기화 fetch는 그대로 수행하되(서버 정리), 복귀 내비게이션일 때는 리로드를 생략.
		var _navType = '';
		try { var _ne = performance.getEntriesByType('navigation')[0]; if (_ne) _navType = _ne.type; } catch(e){}
		// ★ (2026-11-04 00:26) 정리 뒤 다시 불러오기(location.reload) 없앰 — 다시 읽던 세션 표시(index_refreshed 등)를 이제 쓰는 곳이 없어(배경 갱신으로 바뀜) 첫 화면을 두 번 받고 활동·속도 기록도 두 줄 남았음
		fetch('init.php?reset_cache=1').then(function(){
		}).catch(function(){
			// 실패해도 계속 진행
		});
	}
	</script>
	<title><?php echo h($_branding['page_title'] ?? 'myComix'); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<!-- ✅ 핵심 레이아웃 + 페이지 전환 (Bootstrap 로드 전) -->
	<style>
		html{opacity:0;transition:opacity .15s ease-in}
		html.ready{opacity:1}
		html.leaving{opacity:0;transition:opacity .1s ease-out}
		/* 핵심 레이아웃 - Bootstrap 로드 전 미리 적용 */
		*{box-sizing:border-box}
		body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
		.container{width:100%;max-width:1200px;margin:0 auto;padding:0 15px}
		.row{display:flex;flex-wrap:wrap;margin:0 -4px}
		.col{flex:1 0 0%;padding:0 4px}
		img{max-width:100%;height:auto}
		/* 카드/뱃지 기본 크기 확보 - 레이아웃 시프트 방지 */
		.card{min-height:100px;border-radius:4px}
		.badge{display:inline-block;padding:2px 6px;font-size:12px;line-height:1;border-radius:10px;vertical-align:middle}
		.emoji-icon{display:inline-block;width:14px;font-size:14px;text-align:center}
		/* 승인 대기 알림 애니메이션 */
		@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.7;transform:scale(1.05)}}
		.pending-alert{cursor:pointer}
	</style>
	<link rel="stylesheet" href="./css/bootstrap.min.css">
	<link rel="stylesheet" href="./css/css2.css?family=Gugi&family=Nanum+Gothic:wght@400;700&display=swap">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php 
	// 목록 폰트 설정
	$list_font_name = $list_font_settings['font_name'] ?? '';
	$list_font_url = $list_font_settings['font_url'] ?? '';
	$list_font_local = $list_font_settings['font_local'] ?? '';
	$list_font_size = $list_font_settings['font_size'] ?? 22;
	$list_font_family = !empty($list_font_name) ? "'" . h($list_font_name) . "', sans-serif" : "'Dongle', sans-serif";
	
	// 커스텀 폰트 URL이 없으면 기본 Dongle 폰트 로드
	if (empty($list_font_url) && empty($list_font_local)): ?>
	<link rel="preload" href="https://fonts.googleapis.com/css2?family=Dongle&display=block" as="style">
	<link href="https://fonts.googleapis.com/css2?family=Dongle&display=block" rel="stylesheet">
	<?php else: ?>
	<?php if (!empty($list_font_url)): ?>
	<link href="<?php echo h($list_font_url); ?>" rel="stylesheet">
	<?php endif; ?>
	<?php endif; ?>
    <?php echo function_exists('mc_favicon_tags') ? mc_favicon_tags() : '<link rel="shortcut icon" href="./favicon.ico">'; // ★ (2026-11-03 23:50) 탭 아이콘 = 로고 ?>
	<!-- 다크모드 CSS -->
	<?php if (isset($darkmode_settings) && ($darkmode_settings['enabled'] ?? false)): 
		$_darkmode_css_file = __DIR__ . '/css/darkmode.css';
		$_darkmode_version = @filemtime($_darkmode_css_file) ?: '1';
	?>
	<link rel="stylesheet" href="./css/darkmode.css?v=<?php echo $_darkmode_version; ?>">
	<?php endif; ?>
	<script src="./js/jquery-3.5.1.min.js"></script>
	<script src="./js/popper.min.js"></script>
	<script src="./js/bootstrap.min.js"></script>
	<style type="text/css">
		<?php if (!empty($list_font_local) && empty($list_font_url)): ?>
		@font-face {
			font-family: '<?php echo h($list_font_name ?: 'CustomListFont'); ?>';
			src: url('<?php echo function_exists('mc_css_str') ? mc_css_str($list_font_local) : h($list_font_local); /* ★ (2026-11-02 10:02) <style> 안이라 CSS 이스케이프(& 가 &amp; 로 남아 글꼴을 못 받던 것) */ ?>');
			font-display: swap;
		}
		<?php endif; ?>
		html {
			overscroll-behavior-x: none;
		}
		body {
			padding-bottom: 3em;
			font-family: 'Nanum Gothic', sans-serif;
			font-size: smaller;
			overflow-x: hidden;
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
		
		/* ✅ 폴더/파일 카드 링크 색상 - 기본 검정 (.page-btn 제외) */
		a[href*="dir="]:not(.visited-link):not(.page-btn),
		a[href*="dir="]:not(.visited-link):not(.page-btn) *:not(.badge):not(.badge *),
		a[href*="dir="]:not(.visited-link):not(.page-btn) [class*="text-"]:not(.badge),
		a[href*="viewer.php"]:not(.visited-link):not(.page-btn),
		a[href*="viewer.php"]:not(.visited-link):not(.page-btn) *:not(.badge):not(.badge *),
		a[href*="viewer.php"]:not(.visited-link):not(.page-btn) [class*="text-"]:not(.badge),
		a[href*="_viewer.php"]:not(.visited-link):not(.page-btn),
		a[href*="_viewer.php"]:not(.visited-link):not(.page-btn) *:not(.badge):not(.badge *),
		a[href*="_viewer.php"]:not(.visited-link):not(.page-btn) [class*="text-"]:not(.badge) {
			color: #000000 !important;
		}
		
		/* ✅ 폴더/파일 카드 링크 색상 - 방문한 링크 회색 (.page-btn 제외) */
		a[href*="dir="].visited-link:not(.page-btn),
		a[href*="dir="].visited-link:not(.page-btn) *:not(.badge):not(.badge *),
		a[href*="dir="].visited-link:not(.page-btn) [class*="text-"]:not(.badge),
		a[href*="viewer.php"].visited-link:not(.page-btn),
		a[href*="viewer.php"].visited-link:not(.page-btn) *:not(.badge):not(.badge *),
		a[href*="viewer.php"].visited-link:not(.page-btn) [class*="text-"]:not(.badge),
		a[href*="_viewer.php"].visited-link:not(.page-btn),
		a[href*="_viewer.php"].visited-link:not(.page-btn) *:not(.badge):not(.badge *),
		a[href*="_viewer.php"].visited-link:not(.page-btn) [class*="text-"]:not(.badge) {
			color: #888888 !important;
		}
		
		/* ✅ 카드 내 배지 텍스트는 흰색 유지 (badge-light, badge-warning, badge-info 제외) */
		a[href*="dir="] .badge:not(.badge-light):not(.badge-warning):not(.badge-info),
		a[href*="viewer.php"] .badge:not(.badge-light):not(.badge-warning):not(.badge-info),
		a[href*="_viewer.php"] .badge:not(.badge-light):not(.badge-warning):not(.badge-info) {
			color: #ffffff !important;
		}
		
		/* ✅ badge-light (로그인 정보 등)는 검정 텍스트 */
		.badge-light, .badge-light * {
			color: #000000 !important;
		}
		
		/* ✅ badge-warning, badge-info 기본/hover 모두 검정 텍스트 (badge-filesize, badge-filecount, badge-filetype, badge-progress 제외) */
		.badge-warning:not(.badge-progress), .badge-warning:not(.badge-progress) *,
		.badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype), .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype) *,
		a:hover .badge-warning:not(.badge-progress), a:hover .badge-warning:not(.badge-progress) *,
		a:hover .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype), a:hover .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype) *,
		a[href*="dir="] .badge-warning:not(.badge-progress), a[href*="dir="] .badge-warning:not(.badge-progress) *,
		a[href*="dir="] .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype), a[href*="dir="] .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype) *,
		a[href*="dir="]:hover .badge-warning:not(.badge-progress), a[href*="dir="]:hover .badge-warning:not(.badge-progress) *,
		a[href*="dir="]:hover .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype), a[href*="dir="]:hover .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype) *,
		a[href*="_viewer.php"] .badge-warning:not(.badge-progress), a[href*="_viewer.php"] .badge-warning:not(.badge-progress) *,
		a[href*="_viewer.php"] .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype), a[href*="_viewer.php"] .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype) *,
		a[href*="_viewer.php"]:hover .badge-warning:not(.badge-progress), a[href*="_viewer.php"]:hover .badge-warning:not(.badge-progress) *,
		a[href*="_viewer.php"]:hover .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype), a[href*="_viewer.php"]:hover .badge-info:not(.badge-filesize):not(.badge-filecount):not(.badge-filetype) * {
			color: #000000 !important;
		}
		
		/* ✅ badge-filesize, badge-filecount (동영상 용량/파일수)는 흰색 유지 */
		.badge-filesize, .badge-filesize *,
		.badge-filecount, .badge-filecount *,
		a:hover .badge-filesize, a:hover .badge-filesize *,
		a:hover .badge-filecount, a:hover .badge-filecount *,
		/* ✅ badge-filetype (TXT/EPUB), badge-progress (진행률) 흰색 텍스트 - 특이성 최대화 */
		span.badge.badge-pill.badge-info.badge-filetype,
		span.badge.badge-pill.badge-warning.badge-progress,
		span.badge.badge-pill.badge-success.badge-progress,
		a span.badge.badge-pill.badge-info.badge-filetype,
		a span.badge.badge-pill.badge-warning.badge-progress,
		a span.badge.badge-pill.badge-success.badge-progress,
		a:hover span.badge.badge-pill.badge-info.badge-filetype,
		a:hover span.badge.badge-pill.badge-warning.badge-progress,
		a:hover span.badge.badge-pill.badge-success.badge-progress {
			color: #ffffff !important;
		}
		/* ✅ badge-progress 좌측 여백 (TXT/EPUB 뱃지와 간격) */
		.badge-progress {
			margin-left: 4px;
		}
		/* ✅ badge-imagecount (이미지 파일 개수) - 검정 텍스트, 녹색 배경, 컴팩트 여백 */
		.badge-imagecount {
			padding: 0.2em 0.3em 0.1em 0.2em !important;
			font-size: 0.75em !important;
			color: #000000 !important;
			background-color: #28a745 !important;
		}
		.badge-imagecount * {
			color: #000000 !important;
		}
		.badge-imagecount .emoji-icon {
			margin-right: 1px;
		}
		.dropdown-menu{
			max-height: 300px;
			overflow-y: auto;
		}
		/* 카드 row 왼쪽 정렬 + 간격 조절 */
		.row.row-cols-2, .row.row-cols-1 {
			justify-content: flex-start;
			gap: 8px;
			margin: 0;
		}
		/* 카드 링크 크기 맞춤 */
		.row.row-cols-2 > a, .row.row-cols-1 > a {
			display: contents;
		}
		/* ✅ 카드 컨테이너 - 레이아웃 시프트 방지 */
		.col.mb-3 {
			max-width: 300px;
			min-height: 200px;
			flex: 0 0 auto;
			padding: 0;
			margin-bottom: 8px !important;
			contain: layout style;
		}
		/* 모바일에서 2개씩 */
		@media (max-width: 767px) {
			.col.mb-3 {
				max-width: calc(50% - 4px);
				width: calc(50% - 4px);
				min-height: 160px;
			}
		}
		/* 폴더 커버 이미지 */
		.folder-cover {
			max-height: 300px;
			max-width: 90vw;
			object-fit: contain;
		}
		@media (max-width: 767px) {
			.folder-cover {
				max-height: 200px;
			}
			.sort-buttons {
				padding-right: 10px;
			}
			#search-section {
				padding: 5px 15px !important;
			}
			/* 루트 폴더에서 검색창/정렬버튼 순서 변경 */
			.root-folder-wrapper {
				display: flex;
				flex-direction: column;
			}
			.root-search-section {
				order: 2;
				margin-top: 5px;
				margin-bottom: 10px;
			}
			.root-sort-section {
				order: 1;
				margin-bottom: 10px;
			}
		}
		/* 폴더에서 검색창/정렬버튼 순서 변경 (PC+모바일 공통) */
		.root-folder-wrapper {
			display: flex;
			flex-direction: column;
		}
		.root-search-section {
			order: 2;
		}
		.root-sort-section {
			order: 1;
		}
		/* 정렬 버튼 기본 스타일 */
		.sort-buttons {
			white-space: nowrap;
		}
		/* PC용 정렬 버튼 여백 */
		@media (min-width: 768px) {
			.sort-buttons {
				padding-right: 20px;
			}
		}
		/* 카드 목록 - 클릭 영역을 내용만큼만 */
		.grid .row {
			align-items: flex-start !important;
		}
		/* ✅ 카드 컨테이너 - 레이아웃 시프트 방지 */
		.card {
			min-height: 180px;
			contain: layout;
		}
		/* ✅ 썸네일 이미지 - 고정 크기로 레이아웃 시프트 방지 */
		.card-img {
			width: 100%;
			aspect-ratio: 3/4;
			min-height: 120px;
			max-height: 400px;
			object-fit: cover;
			background: linear-gradient(135deg, #e8e8e8 0%, #f5f5f5 100%);
			opacity: 0;
			transition: opacity 0.2s ease-in;
		}
		.card-img.loaded, .card-img[src^="data:"] {
			opacity: 1;
		}
		/* ★ (2026-10-29) 동영상 썸네일이 없을 때의 아이콘 칸(div.card-img)은 이미지가 아니라 load 이벤트가 없어
		   opacity:0 으로 계속 숨겨져 있던 문제 → div 는 처음부터 표시(이미지 썸네일의 페이드인은 그대로) */
		div.card-img {
			opacity: 1;
		}
		/* ✅ TXT/EPUB/PDF 기본 아이콘용 placeholder - card-img 스타일 충돌 방지 */
		.card-img-placeholder {
			width: 100%;
			min-height: 120px;
			max-height: 400px;
		}
		/* 카드 내 파일/폴더 이름에 폰트 적용 */
		.card-body {
			font-family: <?php echo $list_font_family; ?>;
			font-size: <?php echo (int)$list_font_size; ?>px;
			min-height: 24px;
			line-height: 1.2;
		}
		/* ✅ 폴더 카드 레이아웃 안정화 */
		.card.bg-secondary,
		.card.bg-dark {
			min-height: 60px;
		}
		.card.bg-secondary .card-body,
		.card.bg-dark .card-body {
			min-height: 40px;
		}
		/* 폴더 뱃지 - 별도 줄로 표시 */
		.card.bg-secondary .badge,
		.card.bg-dark .badge {
			display: inline-flex;
			align-items: center;
			min-height: 18px;
			margin-left: 2px;
			vertical-align: middle;
		}
		/* 이모지 크기를 텍스트와 맞춤 */
		.emoji-icon {
			font-size: 14px;
			display: inline-block;
			width: 16px;
			text-align: center;
		}
		/* 뱃지 패딩 조절 */
		.card-body .badge {
			padding: 0.2em 0.3em 0.1em 0.2em;
			vertical-align: baseline;
		}
		/* ★ (2026-10-29) 보기 전환 버튼(그리드/목록) — 현재 보기만 회색 배경, 클릭 후 포커스 그림자 없음 */
		.mc-view-switch { display: inline-flex; gap: 6px; vertical-align: middle; }
		/* ★ (2026-10-30) 시안 E 로 정렬 줄을 새로 짜면서(표 → 한 줄 묶음) 예전 표 칸 풀기·순서 규칙은 지움 — 검색창 규칙만 그대로 */
		@media (max-width: 576px) {
			#search-section { padding: 0 !important; }
			#search-section > form { justify-content: flex-start !important; }
			#search-section .input-group { max-width: none !important; }
			.root-sort-section { margin-bottom: 10px; }
			.root-search-section { margin-top: 0; margin-bottom: 8px; }
		}
		@media (min-width: 768px) {
			.root-search-section { margin-top: 10px; margin-bottom: 6px; }
			#search-section { padding-bottom: 0 !important; }
		}
		@media (min-width: 577px) and (max-width: 1024px) and (orientation: landscape) and (hover: none) and (pointer: coarse) {
			#search-section { padding-left: 0 !important; padding-right: 0 !important; }
			.root-search-section > form, #search-section > form { justify-content: flex-start !important; }
			.root-search-section .input-group, #search-section .input-group { max-width: none !important; }
			.root-search-section { margin-top: 6px; }
		}
		/* ★ (2026-10-30) 폭 360px 미만(320px 기기 등): 글자 로고 "마이코믹스"가 두 줄로 꺾이던 것 → 조금 작게, 한 줄로 (이미지 로고는 해당 없음) */
		@media (max-width: 359.98px) {
			a.logo-link { font-size: .84em; white-space: nowrap; }
		}

		/* ======================================================================
		   ★ (2026-10-30) 시안 E — 위쪽 영역(머리 줄·계정 메뉴·콘텐츠 폴더 탭·표지 배너/큰 제목·이어 읽기·정렬 칩·⋯ 도구)
		   높이: 버튼·칩 34~42px, 줄 사이 10~12px 로 통일
		   ====================================================================== */
		.mc-e-head { padding-top: 12px; text-align: left; }
		.mc-topbar { display: flex; align-items: center; gap: 8px; padding-bottom: 8px; }
		.mc-topbar-logo { flex: 1 1 auto; min-width: 0; display: flex; align-items: flex-end; gap: 4px; line-height: 1; }
		.mc-topbar-logo > div { white-space: nowrap; display: flex; align-items: flex-start; } /* ★ (2026-11-03 22:54) 버전은 위(예전 자리 — 펜닐) */
		.mc-topbar-logo > div.mc-logo-clip { overflow: hidden; } /* ★ (2026-11-05) 아래 로고 자동 맞춤 — 가장 작게 해도 넘칠 때만 문구 끝을 … 로 */
		.mc-topbar-logo > div.mc-logo-clip > a.logo-link { flex: 0 1 auto; min-width: 0; overflow: hidden; }
		.mc-topbar-logo > div.mc-logo-clip .mc-brand-txt { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.mc-topbar-logo > div.mc-logo-clip > .mc-ver { flex: 0 0 auto; }
		.mc-ver { font-size: 10px; line-height: 1; color: #999; margin: 2px 0 0 4px; font-family: system-ui, sans-serif; }
		.mc-topbar-tools { flex: 0 0 auto; display: flex; align-items: center; gap: 4px; }
		@media (max-width: 420px) { .mc-topbar-logo a.logo-link { font-size: .58em; } .mc-topbar { gap: 4px; } } /* 좁은 휴대폰: 글자 로고가 아이콘에 닿지 않게 (★ 11-03 22:3x 로고 아이콘이 붙어 .66 → .58) */
		@media (max-width: 359.98px) { .mc-topbar-logo a.logo-link { font-size: .44em; } .mc-topbar-logo img { max-height: 1.8em !important; } .mc-ver { margin-left: 2px; } }
		.mc-acct { position: relative; }
		.mc-acct-btn { position: relative; width: 40px; height: 40px; border: 0; background: transparent; padding: 0; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 20px; }
		.mc-acct-btn:focus { outline: none; } .mc-acct-btn:focus-visible { outline: 2px solid #80bdff; outline-offset: 1px; }
		.mc-acct-initial { width: 32px; height: 32px; border-radius: 16px; background: #1a73e8; color: #fff; font: 700 14px/32px system-ui, sans-serif; text-align: center; }
		.mc-acct-dot { position: absolute; top: 3px; right: 3px; width: 11px; height: 11px; border-radius: 6px; background: #dc3545; border: 2px solid #fff; }
		.mc-acct-menu { position: absolute; right: 0; top: calc(100% + 6px); z-index: 1060; width: 240px; max-width: calc(100vw - 24px); background: #fff; border: 1px solid #d5d9de; border-radius: 12px; box-shadow: 0 10px 28px rgba(0,0,0,.18); padding: 6px; text-align: left; }
		.mc-acct-menu[hidden] { display: none; }
		.mc-acct-who { padding: 8px 10px 9px; font-size: 13px; color: #5f6368; border-bottom: 1px solid #eceff1; margin-bottom: 4px; overflow-wrap: anywhere; }
		.mc-acct-item { display: flex; align-items: center; gap: 6px; min-height: 40px; padding: 0 10px; border-radius: 8px; font-size: 14px; color: #202124 !important; text-decoration: none !important; }
		.mc-acct-item:hover { background: #f1f3f4; color: #202124 !important; }
		.mc-acct-warn { color: #8a5300 !important; font-weight: 700; }
		.mc-acct-danger, .mc-acct-danger:hover { color: #c62828 !important; }
		.mc-acct-sep { height: 1px; background: #eceff1; margin: 4px 0; }
		.mc-tabs { display: flex; gap: 2px; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; border-bottom: 1px solid #e3e6ea; margin: 0 0 10px; }
		.mc-tabs::-webkit-scrollbar { display: none; }
		.mc-tab { flex: 0 0 auto; display: inline-flex; align-items: center; height: 42px; padding: 0 12px; border-bottom: 3px solid transparent; color: #5f6368 !important; font-size: 14px; white-space: nowrap; text-decoration: none !important; box-sizing: border-box; }
		.mc-tab.active { border-bottom-color: #1a73e8; color: #0b57d0 !important; font-weight: 700; }
		.mc-up-btn { flex: 0 0 40px; width: 40px; height: 40px; border: 0; border-radius: 20px; background: #f1f3f4; color: #202124; display: inline-flex; align-items: center; justify-content: center; padding: 0; cursor: pointer; }
		.mc-up-btn:focus { outline: none; } .mc-up-btn:focus-visible { outline: 2px solid #80bdff; outline-offset: 1px; }
		.mc-titlebar { display: flex; align-items: center; gap: 10px; margin: 4px 0 10px; }
		.mc-title-wrap { flex: 1 1 auto; min-width: 0; }
		.mc-parent { font-size: 12px; color: #5f6368; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.mc-title-line { display: flex; align-items: baseline; gap: 10px; min-width: 0; }
		.mc-title { font-size: 24px; font-weight: 700; line-height: 1.25; color: #202124; min-width: 0; overflow-wrap: anywhere; }
		.mc-count { flex: 0 0 auto; font-size: 13px; color: #5f6368; white-space: nowrap; }
		.mc-banner { position: relative; height: 220px; margin: 2px 0 10px; border-radius: 12px; overflow: hidden; background: #c9ced6; }
		.mc-banner-bg { position: absolute; inset: -20px; width: calc(100% + 40px); height: calc(100% + 40px); object-fit: cover; filter: blur(18px) brightness(.75); }
		.mc-banner-img { position: absolute; left: 0; right: 0; top: 0; margin: 0 auto; height: 100%; max-width: 100%; object-fit: contain; display: block; }
		.mc-banner-img { border-radius: .3rem; } /* ★ (2026-11-05 15:40) 가운데 표지 그림도 살짝 둥글게(폴더·파일 썸네일과 같게 — 배너 상자만 둥글고 그림 모서리는 각져 보였음, 펜닐 제보) */
		.mc-banner-band { position: absolute; left: 0; right: 0; bottom: 0; display: flex; align-items: center; gap: 10px; padding: 10px 12px;
			/* ★ (2026-10-31) 진한 검정 띠(.72) → 아래쪽만 살짝 어두운 옅은 그라데이션 + 글자 그림자(밝은 표지 위에서도 흰 글자가 또렷하게) — 펜닐 요청 */
			background: linear-gradient(to top, rgba(12,14,18,.56) 0%, rgba(12,14,18,.36) 65%, rgba(12,14,18,.14) 100%); }
		.mc-banner-band .mc-up-btn { background: rgba(255,255,255,.22); color: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.25); }
		.mc-banner-band .mc-title { color: #fff; font-size: 20px; text-shadow: 0 1px 2px rgba(0,0,0,.75), 0 0 10px rgba(0,0,0,.35); }
		.mc-banner-band .mc-count, .mc-banner-band .mc-parent { color: #f1f3f4; text-shadow: 0 1px 2px rgba(0,0,0,.8), 0 0 6px rgba(0,0,0,.35); }
		.mc-banner-band .mc-count svg, .mc-banner-band .mc-parent svg { filter: drop-shadow(0 1px 1px rgba(0,0,0,.7)); }
		.mc-continue { display: flex; align-items: center; gap: 8px; width: 100%; height: 44px; margin: 0 0 10px; padding: 0 16px; border: 0; border-radius: 22px; background: #ffc107; color: #2e2300; font-size: 14px; text-align: left; cursor: pointer; }
		.mc-continue b { flex: 0 0 auto; }
		.mc-continue-name { flex: 1 1 auto; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.mc-continue:focus { outline: none; } .mc-continue:focus-visible { outline: 2px solid #80bdff; outline-offset: 1px; }
		@media (min-width: 768px) {
			.mc-banner { height: 280px; }
			.mc-banner-band { padding: 12px 18px; }
			.mc-banner-band .mc-title { font-size: 24px; }
			.mc-title { font-size: 28px; }
			.mc-continue { width: auto; max-width: 560px; }
		}
		.mc-sortbar { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; width: 100%; padding: 0 15px; position: relative; }
		.mc-sortbar > .btn[class*="info"] { height: 34px; margin: 0 !important; padding: 0 12px !important; border-radius: 17px !important; display: inline-flex; align-items: center; gap: 2px; font-size: 13px; white-space: nowrap; border: 1px solid #d5d9de !important; background: #fff !important; color: #3c4043 !important; box-shadow: none !important; }
		.mc-sortbar > .btn.btn-info { background: #202124 !important; border-color: #202124 !important; color: #fff !important; font-weight: 700; }
		.mc-sortbar > .btn[class*="info"] > svg { width: 1.15em; height: 1.15em; }
		.mc-sp { flex: 1 1 auto; }
		.mc-sortbar .mc-view-switch { margin: 0 !important; }
		.mc-tools { display: inline-flex; align-items: center; gap: 4px; }
		.mc-tools > .btn { margin: 0 !important; height: 34px; min-width: 34px; border-radius: 17px !important; display: inline-flex; align-items: center; justify-content: center; }
		.mc-tools > .btn.d-none { display: none !important; }
		@media (min-width: 992px) { .mc-tools > .btn.d-lg-inline-block { display: inline-flex !important; } }
		.mc-more-btn { display: none; width: 36px; height: 36px; border: 0; border-radius: 18px; background: #f1f3f4; color: #3c4043; align-items: center; justify-content: center; padding: 0; cursor: pointer; }
		.mc-more-btn:focus { outline: none; } .mc-more-btn:focus-visible { outline: 2px solid #80bdff; outline-offset: 1px; }
		.root-search-section .input-group > .form-control, #search-section .input-group > .form-control { height: 42px !important; border-radius: 21px 0 0 21px !important; }
		.root-search-section .input-group-append > .btn, #search-section .input-group-append > .btn { height: 42px !important; border-radius: 0 21px 21px 0 !important; padding: 0 16px; }
		/* ★ (2026-10-31) 파일 검색창 — 한 덩어리 둥근 칸 + 칸 안 오른쪽 연한 초록 원형 돋보기 버튼(글자 없음), 입력 중이면 테두리 초록 */
		.root-search-section .input-group, #search-section .input-group { flex-wrap: nowrap; align-items: center; background: #fff; border: 1px solid #dadce0; border-radius: 24px; padding: 3px 3px 3px 6px; box-shadow: 0 1px 3px rgba(60,64,67,.10); transition: border-color .15s, box-shadow .15s; }
		.root-search-section .input-group:hover, #search-section .input-group:hover { box-shadow: 0 1px 6px rgba(60,64,67,.18); }
		.root-search-section .input-group:focus-within, #search-section .input-group:focus-within { border-color: #34a853; box-shadow: 0 0 0 3px rgba(52,168,83,.16); }
		.root-search-section .input-group > .form-control, #search-section .input-group > .form-control { height: 38px !important; border: 0 !important; border-radius: 19px !important; box-shadow: none !important; background-color: transparent; padding-left: 12px; font-size: 15px; }
		.root-search-section .input-group-append, #search-section .input-group-append { margin-left: 4px; }
		.root-search-section .input-group-append > .btn, #search-section .input-group-append > .btn { width: 36px; height: 36px !important; padding: 0; border-radius: 50% !important; border: 0 !important; background: #e6f4ea !important; color: #1e8e3e !important; display: inline-flex; align-items: center; justify-content: center; box-shadow: none !important; transition: background-color .15s, color .15s; }
		.root-search-section .input-group-append > .btn:hover, #search-section .input-group-append > .btn:hover { background: #ceead6 !important; color: #137333 !important; }
		.root-search-section .input-group-append > .btn:active, #search-section .input-group-append > .btn:active { background: #b7dfc3 !important; }
		.root-search-section .input-group-append > .btn:focus, #search-section .input-group-append > .btn:focus { outline: none; }
		.root-search-section .input-group-append > .btn:focus-visible, #search-section .input-group-append > .btn:focus-visible { outline: 2px solid #34a853; outline-offset: 2px; }
		.dark-mode .root-search-section .input-group, .dark-mode #search-section .input-group, [data-theme="dark"] .root-search-section .input-group, [data-theme="dark"] #search-section .input-group { background: #2d2d2d; border-color: #404040; box-shadow: none; }
		.dark-mode .root-search-section .input-group:focus-within, .dark-mode #search-section .input-group:focus-within, [data-theme="dark"] .root-search-section .input-group:focus-within, [data-theme="dark"] #search-section .input-group:focus-within { border-color: #81c995; box-shadow: 0 0 0 3px rgba(129,201,149,.18); }
		.dark-mode .root-search-section .input-group-append > .btn, .dark-mode #search-section .input-group-append > .btn, [data-theme="dark"] .root-search-section .input-group-append > .btn, [data-theme="dark"] #search-section .input-group-append > .btn { background: rgba(129,201,149,.18) !important; color: #81c995 !important; }
		.dark-mode .root-search-section .input-group-append > .btn:hover, .dark-mode #search-section .input-group-append > .btn:hover, [data-theme="dark"] .root-search-section .input-group-append > .btn:hover, [data-theme="dark"] #search-section .input-group-append > .btn:hover { background: rgba(129,201,149,.30) !important; }
		/* ★ (2026-10-31) 폴더 카드 개수 칩(시안 A): 책 = 호박색, 동영상 = 보라, 하위 폴더 = 파랑, 이미지 = 초록 — 링크 색 규칙에 안 바뀌게 !important */
		.mc-fchips { display: inline-flex; flex-wrap: wrap; justify-content: center; gap: 4px; min-height: 22px; margin-top: 3px; vertical-align: middle; white-space: nowrap; }
		.mc-fchip { display: inline-flex; align-items: center; gap: 3px; height: 20px; padding: 0 8px; border-radius: 10px; font-size: 12px; font-weight: 600; line-height: 20px; white-space: nowrap; letter-spacing: normal;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Malgun Gothic", "맑은 고딕", "Apple SD Gothic Neo", "Noto Sans KR", sans-serif !important; } /* ★ (2026-10-31 14:29) 관리자 '목록 글꼴'(꾸밈 글꼴)이 칩까지 따라와 숫자·글자가 안 보이던 것 → 칩만 기본 글꼴(이름은 설정 글꼴 그대로) */
		.mc-fchip svg { width: 13px; height: 13px; flex: 0 0 auto; }
		/* ★ (2026-11-04 00:00) 폴더 카드 N(새 파일) 딱지 — 옆 개수 칩과 같은 높이·둥근 모양으로 나란히(펜닐) */
		.mc-fchips > .badge.mc-fnew { display: inline-flex; align-items: center; justify-content: center; height: 20px; min-width: 26px; padding: 0 8px; border-radius: 10px; font-size: 12px; font-weight: 700; line-height: 20px; letter-spacing: normal; vertical-align: middle;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Malgun Gothic", "맑은 고딕", "Apple SD Gothic Neo", "Noto Sans KR", sans-serif !important; } /* ★ (2026-11-04 07:23) 파일 카드 N 처럼 — 관리자 '목록 글꼴'(Dongle 등 꾸밈 글꼴)이 따라와 N 이 작은 동그라미처럼 보였음(펜닐) → 칩과 같은 기본 글꼴, 가로로 조금 긴 알약 모양 */
		/*   폴더 링크 글자를 검정으로 고정하는 규칙(a[href*="dir="] … :not(.badge) — 배지만 빼 줌)보다 앞서게 :not(#_)(없는 id — 우선순위만 올림) */
		.mc-fchips > .mc-fchip.mc-fc-book:not(#_) { background: #fef3c7 !important; color: #92400e !important; }
		.mc-fchips > .mc-fchip.mc-fc-vid:not(#_) { background: #f3e8ff !important; color: #6b21a8 !important; }
		.mc-fchips > .mc-fchip.mc-fc-dir:not(#_) { background: #e8f0fe !important; color: #1a56c4 !important; }
		.mc-fchips > .mc-fchip.mc-fc-img:not(#_) { background: #e6f4ea !important; color: #137333 !important; }
		.dark-mode .mc-fchips > .mc-fchip.mc-fc-book:not(#_), [data-theme="dark"] .mc-fchips > .mc-fchip.mc-fc-book:not(#_) { background: rgba(251,191,36,.16) !important; color: #fcd34d !important; }
		.dark-mode .mc-fchips > .mc-fchip.mc-fc-vid:not(#_), [data-theme="dark"] .mc-fchips > .mc-fchip.mc-fc-vid:not(#_) { background: rgba(196,148,255,.16) !important; color: #d0a8ff !important; }
		.dark-mode .mc-fchips > .mc-fchip.mc-fc-dir:not(#_), [data-theme="dark"] .mc-fchips > .mc-fchip.mc-fc-dir:not(#_) { background: rgba(138,180,248,.16) !important; color: #8ab4f8 !important; }
		.dark-mode .mc-fchips > .mc-fchip.mc-fc-img:not(#_), [data-theme="dark"] .mc-fchips > .mc-fchip.mc-fc-img:not(#_) { background: rgba(129,201,149,.16) !important; color: #81c995 !important; }
		/* ★ (2026-10-31 14:41) 실제 화면 보정(펜닐 제보 — 시안과 달리 칩 아이콘이 이름 색(청록)으로, 글자 굵기·크기가 사이트 규칙에 바뀜)
		   폴더 링크 안 모든 요소에 글자색을 거는 규칙·목록 글꼴 규칙보다 앞서게 아이콘·글자 모양도 :not(#_) 로 고정 */
		.mc-fchips > .mc-fchip:not(#_) { font-size: 12px !important; font-weight: 600 !important; line-height: 20px !important; letter-spacing: normal !important; text-decoration: none !important; }
		.mc-fchips > .mc-fchip:not(#_) > svg { color: inherit !important; stroke: currentColor !important; fill: none !important; width: 13px !important; height: 13px !important; margin: 0 !important; vertical-align: middle; }
		.mc-fchips > .mc-fchip:not(#_) > svg * { color: inherit !important; stroke: currentColor !important; } /* 선(path·rect·circle)마다 걸리는 글자색 규칙까지 — currentColor 는 각 선 자신의 색을 따름 */
		/* 목록 보기(시안 A): 한 줄에 [아이콘 이름(최대 3줄) ……… 칩] — 칩은 오른쪽 끝, 이름과 세로 가운데. 휴대폰 세로(576px 이하)는 폭이 좁아 칩을 이름 아래 줄로 */
		html.mc-list-view .mc-fchips { justify-content: flex-start; min-height: 0; }
		@media (min-width: 577px) {
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body:has(> .mc-fchips) { display: flex !important; align-items: flex-start; gap: 6px; overflow: visible; -webkit-line-clamp: unset; line-clamp: none; } /* ★ (14:49 펜닐 요청) 썸네일 윗줄에 맞춤(파일 줄과 같게) — 이름·칩 모두 위쪽 */
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body:has(> .mc-fchips) > .emoji-icon { flex: 0 0 auto; }
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body:has(> .mc-fchips) > .mc-fname { flex: 1 1 auto; min-width: 0; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; line-clamp: 3; overflow: hidden; overflow-wrap: anywhere; }
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body:has(> .mc-fchips) > .mc-fbr { display: none; }
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body:has(> .mc-fchips) > .mc-fchips { flex: 0 0 auto; flex-wrap: nowrap; margin: 0 0 0 auto; justify-content: flex-end; min-height: 0; }
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body:has(> .mc-fchips) > .badge { flex: 0 0 auto; }
		}
		@media (max-width: 576px) {
			.mc-title { font-size: 22px; }
			.mc-banner { height: 210px; }
			.mc-view-btn.active { display: none; } /* 휴대폰 세로: 보기 전환은 '바꿀 보기' 버튼 하나만(누르면 바뀜) */
			.mc-more-btn { display: inline-flex; }
			.mc-tools { display: none; position: absolute; right: 15px; top: calc(100% + 6px); z-index: 1050; flex-direction: column; align-items: stretch; gap: 2px; min-width: 190px; padding: 6px; background: #fff; border: 1px solid #d5d9de; border-radius: 12px; box-shadow: 0 10px 28px rgba(0,0,0,.18); }
			.mc-tools.open { display: flex; }
			.mc-tools > .btn { justify-content: flex-start; gap: 8px; height: 40px; border: 0 !important; border-radius: 8px !important; background: transparent !important; color: #202124 !important; font-size: 15px; padding: 0 10px !important; text-align: left; }
			.mc-tools > .btn::after { content: attr(title); font-size: 14px; }
			.mc-tools > .btn:hover { background: #f1f3f4 !important; }
		}
		@media (max-width: 359.98px) { .mc-sortbar { gap: 4px; } .mc-sortbar > .btn[class*="info"] { padding: 0 8px !important; font-size: 12px; } }
		/* 휴대폰 가로(화면 높이 500px 이하): 배너가 화면을 다 덮지 않게 낮춤 */
		@media (max-height: 500px) and (orientation: landscape) { .mc-banner { height: 150px; } .mc-banner-band { padding: 6px 12px; } }
		/* ★ (2026-10-30) 선 아이콘 — 색 있는 것(탐색 파랑·책장 주황·도구 버튼 색·로그아웃 빨강)과 색 없는 것(북마크·보기·⋯·메뉴 항목 회색)
		   id 로 묶은 규칙: 아래쪽 공통 링크 규칙(a:hover 청록·방문한 링크 회색 !important)보다 우선하게 */
		.mc-ico { display: inline-block; vertical-align: middle; flex: 0 0 auto; }
		#mcTopTools > span { display: inline-flex; align-items: center; gap: 2px; }
		#mcTopTools a.mc-lib-browse-link, #mcTopTools a.mc-fav-shelf-link, #mcTopTools .mc-bm-toggle {
			height: 40px; min-width: 40px; margin: 0 !important; padding: 0 !important; display: inline-flex !important; align-items: center; justify-content: center;
			border-radius: 20px; line-height: 1 !important; box-shadow: none !important; }
		/* ★ (2026-10-31) 서재 = 옆 북마크와 같은 회색(예전 파랑), 즐겨찾기 = 노란 별(예전 주황 책장) — 펜닐 선택 시안 A */
		#mcTopTools a.mc-lib-browse-link, #mcTopTools a.mc-lib-browse-link:hover { color: #3c4043 !important; }
		#mcTopTools a.mc-fav-shelf-link, #mcTopTools a.mc-fav-shelf-link:hover { color: #f59f00 !important; }
		#mcTopTools .mc-bm-toggle, #mcTopTools .mc-bm-toggle:hover { color: #3c4043 !important; gap: 2px; padding: 0 6px !important; }
		#mcTopTools a.mc-lib-browse-link:hover, #mcTopTools a.mc-fav-shelf-link:hover, #mcTopTools .mc-bm-toggle:hover, .mc-acct-btn:hover { background: #f1f3f4; }
		#mcTopTools a *, #mcTopTools a:hover * { color: inherit !important; }
		@media (max-width: 359.98px) { /* 320 폭: 버전 글자가 첫 아이콘에 닿지 않게 버튼 폭만 살짝 줄임 */
			#mcTopTools > span { gap: 0; }
			#mcTopTools a.mc-lib-browse-link, #mcTopTools a.mc-fav-shelf-link { min-width: 34px; }
			#mcTopTools .mc-bm-toggle, #mcTopTools .mc-bm-toggle:hover { padding: 0 3px !important; } }
		.mc-acct-menu { width: 250px; padding: 6px; }
		/* ★ (2026-11-03 21:46) 머리 부분이 허전해 보이던 것 — 연한 바탕 카드 + 조금 큰 이름표 + 권한을 작은 배지로(펜닐 제보) */
		#mcAcctMenu .mc-acct-head { display: flex; align-items: center; gap: 12px; padding: 12px; background: #eef3fd; border-radius: 10px; margin-bottom: 6px; }
		#mcAcctMenu .mc-acct-avatar { flex: 0 0 44px; width: 44px; height: 44px; border-radius: 22px; background: #1a73e8; color: #fff; font: 700 18px/44px system-ui, sans-serif; text-align: center; box-shadow: 0 0 0 3px #fff; }
		#mcAcctMenu .mc-acct-id { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
		#mcAcctMenu .mc-acct-name { font-size: 16px; font-weight: 700; color: #202124; overflow-wrap: anywhere; }
		#mcAcctMenu .mc-acct-hi { display: block; font-size: 14px; color: #3c4043; overflow-wrap: anywhere; line-height: 1.35; } /* ★ (2026-10-31) 환영 문구 */
		.dark-mode #mcAcctMenu .mc-acct-hi, [data-theme="dark"] #mcAcctMenu .mc-acct-hi { color: #cfd2d6; }
		#mcAcctMenu .mc-acct-role { align-self: flex-start; margin-top: 5px; padding: 1px 9px; border-radius: 10px; background: #fff; border: 1px solid #dadce0; font-size: 12px; line-height: 18px; color: #5f6368; }
		#mcAcctMenu .mc-acct-role.is-admin { background: #d3e3fd; border-color: #d3e3fd; color: #0b57d0; font-weight: 600; }
		#mcAcctMenu a.mc-acct-item { gap: 12px; min-height: 40px; padding: 0 10px; color: #202124 !important; font-size: 14px; }
		#mcAcctMenu a.mc-acct-item .mc-ico { color: #5f6368 !important; }
		#mcAcctMenu a.mc-acct-item:hover { background: #f1f3f4; color: #202124 !important; }
		#mcAcctMenu a.mc-acct-item:hover span { color: #202124 !important; }
		#mcAcctMenu a.mc-acct-item:hover .mc-ico { color: #202124 !important; }
		#mcAcctMenu a.mc-acct-warn, #mcAcctMenu a.mc-acct-warn span, #mcAcctMenu a.mc-acct-warn .mc-ico { color: #8a5300 !important; font-weight: 700; }
		#mcAcctMenu a.mc-acct-danger, #mcAcctMenu a.mc-acct-danger span, #mcAcctMenu a.mc-acct-danger .mc-ico,
		#mcAcctMenu a.mc-acct-danger:hover span, #mcAcctMenu a.mc-acct-danger:hover .mc-ico { color: #c62828 !important; }
		#mcAcctMenu a.mc-acct-danger:hover { background: #fdecea; }
		#mcTabs a.mc-tab { gap: 6px; color: #5f6368 !important; }
		#mcTabs a.mc-tab * { color: inherit !important; }
		#mcTabs a.mc-tab.active { color: #0b57d0 !important; }
		.mc-count { display: inline-flex; align-items: center; gap: 3px; }
		.mc-tools > .btn .mc-ico { pointer-events: none; }
		.root-search-section .input-group-append > .btn, #search-section .input-group-append > .btn { display: inline-flex; align-items: center; gap: 6px; }
		.dark-mode #mcTopTools .mc-bm-toggle, [data-theme="dark"] #mcTopTools .mc-bm-toggle { color: #e8eaed !important; }
		.dark-mode #mcTopTools a.mc-lib-browse-link, [data-theme="dark"] #mcTopTools a.mc-lib-browse-link { color: #e8eaed !important; } /* ★ (2026-10-31) 북마크와 같은 색 */
		.dark-mode #mcTopTools a.mc-fav-shelf-link, [data-theme="dark"] #mcTopTools a.mc-fav-shelf-link { color: #fbbf24 !important; }
		.dark-mode #mcTopTools a:hover, .dark-mode #mcTopTools .mc-bm-toggle:hover, .dark-mode .mc-acct-btn:hover,
		[data-theme="dark"] #mcTopTools a:hover, [data-theme="dark"] #mcTopTools .mc-bm-toggle:hover, [data-theme="dark"] .mc-acct-btn:hover { background: #2c2c2c; }
		.dark-mode #mcAcctMenu .mc-acct-head, [data-theme="dark"] #mcAcctMenu .mc-acct-head { background: #262b33; }
		.dark-mode #mcAcctMenu .mc-acct-avatar, [data-theme="dark"] #mcAcctMenu .mc-acct-avatar { box-shadow: 0 0 0 3px #262b33; }
		.dark-mode #mcAcctMenu .mc-acct-name, [data-theme="dark"] #mcAcctMenu .mc-acct-name { color: #e8eaed; }
		.dark-mode #mcAcctMenu .mc-acct-role, [data-theme="dark"] #mcAcctMenu .mc-acct-role { color: #bbb; background: #1e1e1e; border-color: #444; }
		.dark-mode #mcAcctMenu .mc-acct-role.is-admin, [data-theme="dark"] #mcAcctMenu .mc-acct-role.is-admin { color: #8ab4f8; background: #1f3350; border-color: #1f3350; }
		.dark-mode #mcAcctMenu a.mc-acct-item, .dark-mode #mcAcctMenu a.mc-acct-item:hover, .dark-mode #mcAcctMenu a.mc-acct-item:hover span,
		[data-theme="dark"] #mcAcctMenu a.mc-acct-item, [data-theme="dark"] #mcAcctMenu a.mc-acct-item:hover, [data-theme="dark"] #mcAcctMenu a.mc-acct-item:hover span { color: #e8eaed !important; }
		.dark-mode #mcAcctMenu a.mc-acct-item .mc-ico, [data-theme="dark"] #mcAcctMenu a.mc-acct-item .mc-ico { color: #aaa !important; }
		.dark-mode #mcAcctMenu a.mc-acct-item:hover, [data-theme="dark"] #mcAcctMenu a.mc-acct-item:hover { background: #2c2c2c; }
		.dark-mode #mcAcctMenu a.mc-acct-danger, .dark-mode #mcAcctMenu a.mc-acct-danger span, .dark-mode #mcAcctMenu a.mc-acct-danger .mc-ico,
		[data-theme="dark"] #mcAcctMenu a.mc-acct-danger, [data-theme="dark"] #mcAcctMenu a.mc-acct-danger span, [data-theme="dark"] #mcAcctMenu a.mc-acct-danger .mc-ico { color: #ff8a80 !important; }
		.dark-mode #mcAcctMenu a.mc-acct-danger:hover, [data-theme="dark"] #mcAcctMenu a.mc-acct-danger:hover { background: #3a2422; }
		.dark-mode #mcAcctMenu a.mc-acct-warn, .dark-mode #mcAcctMenu a.mc-acct-warn span, .dark-mode #mcAcctMenu a.mc-acct-warn .mc-ico,
		[data-theme="dark"] #mcAcctMenu a.mc-acct-warn, [data-theme="dark"] #mcAcctMenu a.mc-acct-warn span, [data-theme="dark"] #mcAcctMenu a.mc-acct-warn .mc-ico { color: #ffcc66 !important; }
		.dark-mode #mcTabs a.mc-tab, [data-theme="dark"] #mcTabs a.mc-tab { color: #aaa !important; }
		.dark-mode #mcTabs a.mc-tab.active, [data-theme="dark"] #mcTabs a.mc-tab.active { color: #8ab4f8 !important; }
		/* 어두운 화면 */
		.dark-mode .mc-acct-menu, [data-theme="dark"] .mc-acct-menu,
		.dark-mode .mc-tools.open, [data-theme="dark"] .mc-tools.open { background: #1e1e1e; border-color: #3a3a3a; }
		.dark-mode .mc-acct-who, [data-theme="dark"] .mc-acct-who { color: #aaa; border-color: #333; }
		.dark-mode .mc-acct-item, [data-theme="dark"] .mc-acct-item,
		.dark-mode .mc-acct-item:hover, [data-theme="dark"] .mc-acct-item:hover { color: #e8eaed !important; }
		.dark-mode .mc-acct-item:hover, [data-theme="dark"] .mc-acct-item:hover { background: #2c2c2c; }
		.dark-mode .mc-acct-warn, [data-theme="dark"] .mc-acct-warn { color: #ffcc66 !important; }
		.dark-mode .mc-acct-danger, [data-theme="dark"] .mc-acct-danger { color: #ff8a80 !important; }
		.dark-mode .mc-acct-sep, [data-theme="dark"] .mc-acct-sep { background: #333; }
		.dark-mode .mc-acct-dot, [data-theme="dark"] .mc-acct-dot { border-color: #121212; }
		.dark-mode .mc-tabs, [data-theme="dark"] .mc-tabs { border-color: #333; }
		.dark-mode .mc-tab, [data-theme="dark"] .mc-tab { color: #aaa !important; }
		.dark-mode .mc-tab.active, [data-theme="dark"] .mc-tab.active { color: #8ab4f8 !important; border-bottom-color: #8ab4f8; }
		.dark-mode .mc-up-btn, [data-theme="dark"] .mc-up-btn,
		.dark-mode .mc-more-btn, [data-theme="dark"] .mc-more-btn { background: #2c2c2c; color: #e8eaed; }
		.dark-mode .mc-title, [data-theme="dark"] .mc-title { color: #e8eaed; }
		.dark-mode .mc-count, .dark-mode .mc-parent, [data-theme="dark"] .mc-count, [data-theme="dark"] .mc-parent { color: #aaa; }
		.dark-mode .mc-banner-band .mc-title, [data-theme="dark"] .mc-banner-band .mc-title { color: #fff; }
		.dark-mode .mc-sortbar > .btn[class*="info"], [data-theme="dark"] .mc-sortbar > .btn[class*="info"] { background: #1e1e1e !important; border-color: #444 !important; color: #ddd !important; }
		.dark-mode .mc-sortbar > .btn.btn-info, [data-theme="dark"] .mc-sortbar > .btn.btn-info { background: #e8eaed !important; border-color: #e8eaed !important; color: #121212 !important; }
		.dark-mode .mc-sortbar > .btn.btn-info svg, [data-theme="dark"] .mc-sortbar > .btn.btn-info svg { fill: currentColor !important; } /* ★ (2026-11-02 19:04) 고른 정렬 칩 화살표 — 다크모드 공용 .btn svg 색(밝은 회색)이 밝은 칩 위에 덮여 안 보였음 */
		@media (max-width: 576px) {
			.dark-mode .mc-tools > .btn, [data-theme="dark"] .mc-tools > .btn { color: #e8eaed !important; }
			.dark-mode .mc-tools > .btn:hover, [data-theme="dark"] .mc-tools > .btn:hover { background: #2c2c2c !important; }
		}
		.mc-view-btn {
			display: inline-flex; align-items: center; justify-content: center;
			width: 32px; height: 32px; padding: 0;
			border: 1px solid #dcdcdc; border-radius: 7px;
			background: #fff; color: #555; cursor: pointer; line-height: 1;
			box-shadow: none; outline: none;
			transition: background-color .12s ease, border-color .12s ease;
		}
		.mc-view-btn:hover { background: #f3f3f3; }
		.mc-view-btn.active { background: #e9e9e9; border-color: #d0d0d0; color: #333; }
		.mc-view-btn:focus { outline: none; box-shadow: none; }
		.mc-view-btn:focus-visible { outline: 2px solid #80bdff; outline-offset: 1px; }
		.dark-mode .mc-view-btn, [data-theme="dark"] .mc-view-btn { background: #1e1e1e; border-color: #444; color: #bbb; }
		.dark-mode .mc-view-btn:hover, [data-theme="dark"] .mc-view-btn:hover { background: #2a2a2a; }
		.dark-mode .mc-view-btn.active, [data-theme="dark"] .mc-view-btn.active { background: #3a3a3a; border-color: #555; color: #eee; }
		/* ★ (2026-10-29) 목록 보기(☰): 카드 마크업은 그대로, 배치만 한 줄 리스트(작은 썸네일 + 제목 + 뱃지)로 */
		html.mc-list-view .grid .row > a > .col.mb-3,
		html.mc-list-view .grid .row > .col.mb-3 {
			flex: 0 0 100% !important;
			max-width: 100% !important;
			width: 100% !important;
			min-height: 0 !important; /* 그리드용 레이아웃 시프트 방지 min-height:200px 해제 */
			margin-bottom: 0 !important;
			padding-left: 0; padding-right: 0;
		}
		html.mc-list-view .grid .row.row-cols-2,
		html.mc-list-view .grid .row.row-cols-1 { gap: 4px; }
		html.mc-list-view .grid .row > a > .col.mb-3 > .card,
		html.mc-list-view .grid .row > .col.mb-3 > .card {
			display: flex;
			flex-direction: row;
			align-items: flex-start; /* ★ 제목·뱃지를 썸네일 맨 위에 맞춤(가운데 → 위) */
			min-height: 0;
			padding: 4px 6px !important;
			margin: 0 !important;
		}
		html.mc-list-view .grid .card > .card-img,
		html.mc-list-view .grid .card > .card-img-placeholder,
		html.mc-list-view .grid .card > img.card-img-top {
			flex: 0 0 54px;
			width: 54px !important;
			height: 72px !important;
			min-height: 0 !important;
			max-height: none !important;
			aspect-ratio: auto;
			object-fit: cover;
			margin: 0 10px 0 0;
			overflow: hidden;
			font-size: 1.4rem;
		}
		html.mc-list-view .grid .card > .card-img svg,
		html.mc-list-view .grid .card > .card-img-placeholder svg,
		html.mc-list-view .grid .card > .card-img-placeholder img { max-width: 100% !important; max-height: 100% !important; width: auto !important; height: auto !important; }
		html.mc-list-view .grid .card > .card-body {
			flex: 1 1 auto;
			min-width: 0;
			order: 2;
			text-align: left !important;
			white-space: nowrap !important;
			overflow: hidden;
			text-overflow: ellipsis;
			margin: 0 !important;
			padding-top: 2px !important;
		}
		html.mc-list-view .grid .card > .card-img-overlay {
			position: static;
			order: 3;
			flex: 0 1 auto;
			max-width: 45%;
			display: flex;
			flex-wrap: wrap;
			justify-content: flex-end;
			gap: 2px;
			margin: 4px 26px 0 8px !important;
			padding: 0 !important;
		}
		html.mc-list-view .grid .card > .fav-btn { top: 8px; margin-top: 0; } /* 제목 줄과 같은 높이(위쪽) */
		/* 폴더 커버 카드(이미지가 card-body 안에 있는 구조) — ★ (2026-10-30) 관리자 아닌 사용자도 적용되게 mc-folder-card 클래스도 같이(data-folder-path 는 관리자만 붙음) */
		html.mc-list-view .grid .card[data-folder-path] > .card-body,
		html.mc-list-view .grid .card.mc-folder-card > .card-body {
			display: flex !important;
			align-items: flex-start; /* ★ 위쪽 정렬 */
			text-align: left !important;
		}
		html.mc-list-view .grid .card[data-folder-path] > .card-body > img,
		html.mc-list-view .grid .card.mc-folder-card > .card-body > img {
			flex: 0 0 54px;
			width: 54px !important;
			min-width: 54px !important;
			max-width: 54px !important;
			height: 72px !important;
			object-fit: cover !important;
			margin: 0 10px 0 0 !important;
		}
		html.mc-list-view .grid .card[data-folder-path] > .card-body > br,
		html.mc-list-view .grid .card.mc-folder-card > .card-body > br { display: none; }
		html.mc-list-view .grid .card[data-folder-path] > .card-body > .card-body,
		html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body {
			flex: 1 1 auto;
			min-width: 0;
			padding-top: 2px !important;
			text-align: left !important;
		}
		@media (max-width: 576px) {
			html.mc-list-view .grid .card > .card-img-overlay { max-width: 38%; }
			html.mc-list-view .grid .card > .card-img-overlay .badge { font-size: 10px; }
		}
		/* ★ (2026-10-30) 목록 보기 다듬기(그리드 보기는 그대로)
		   - 파일 카드: [썸네일 | 제목(최대 2줄, 잘리지 않게 줄바꿈) / 그 아래 배지 한 줄] — 예전엔 제목 한 줄(…)에 배지가 오른쪽에 세로로 쌓여 제목 칸이 좁았음
		     오른쪽 ⓘ·★ 자리는 비워 둠. 배지 줄이 있는 카드만(:has) — :has 를 모르는 옛 브라우저는 위의 예전 배치 그대로
		   - 폴더 카드: 표지 없는 폴더도 썸네일 칸에 폴더 그림, 줄 높이를 파일 줄과 같게, 이름은 줄바꿈(최대 3줄)
		   - 휴대폰(576px 이하)은 목록 제목 글자를 최대 17px 로(설정 글자 크기가 더 작으면 그대로) */
		html.mc-list-view .grid .row > a > .col.mb-3 > .card:has(> .card-img-overlay),
		html.mc-list-view .grid .row > .col.mb-3 > .card:has(> .card-img-overlay) {
			display: grid;
			grid-template-columns: var(--mc-lt-w, 54px) minmax(0, 1fr); /* ★ (2026-10-31) 썸네일 칸 = 관리자 '목록' 썸네일 폭(설정 없으면 54px) */
			grid-template-rows: auto auto minmax(0, 1fr); /* 1fr 은 썸네일 크기만큼 늘어나 아래가 비었음 */
			column-gap: 10px;
			row-gap: 4px;
			align-items: start;
			padding: 4px 52px 4px 6px !important; /* 오른쪽 52px: ⓘ·★ 자리 */
			min-height: calc(var(--mc-lt-h, 72px) + 10px);
		}
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-img,
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-img-placeholder,
		html.mc-list-view .grid .card:has(> .card-img-overlay) > img.card-img-top {
			grid-column: 1;
			grid-row: 1 / span 3;
			margin: 0;
		}
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-body {
			grid-column: 2;
			grid-row: 1;
			display: -webkit-box;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
			line-clamp: 2;
			white-space: normal !important;
			overflow: hidden;
			overflow-wrap: anywhere;
			word-break: normal;
			line-height: 1.25 !important;
			min-height: 0;
			padding-top: 1px !important;
		}
		/* ★ (2026-10-31) 배지를 썸네일 위(왼쪽 위)에 두었던 것을 ★ (2026-11-04 17:55) 다시 제목 아래 한 줄로 — 펜닐 결정(시안: 목록은 제목 밑에 전부 정렬)
		   썸네일이 작아(54px 안팎) 배지 3개가 세로로 쌓여 그림을 가렸음. 배지는 하나도 빼지 않고 같은 순서·같은 색 그대로 */
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-img-overlay {
			grid-column: 2;
			grid-row: 2;
			align-self: start;
			justify-self: stretch;
			z-index: 2;
			max-width: none;
			min-width: 0;
			justify-content: flex-start;
			align-content: flex-start;
			align-items: center;
			gap: 4px;
			margin: 0 !important;
			padding: 0 !important;
		}
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-img-overlay .badge {
			font-size: 11.5px; line-height: 1.2; padding: 3px 7px; margin: 0; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
		}
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-img-overlay .badge-progress { margin-left: 0; }
		/* 폴더 카드(표지·이름이 card-body 안) */
		html.mc-list-view .grid .card.mc-folder-card:not(:has(> .card-img-overlay)) {
			padding: 4px 6px !important; /* 줄 높이는 썸네일(72px)+여백으로 정해짐 — 목록 표지를 끈 설정이면 이름 한 줄 높이 */
		}
		html.mc-list-view .grid .card.mc-folder-card:not(:has(> .card-img-overlay)) > .card-body {
			margin: 0 !important;
			padding: 0 !important;
			min-height: 0;
			white-space: normal !important;
		}
		html.mc-list-view .grid .card.mc-folder-card > .card-body > img[src^="data:"] {
			/* 표지 없는 폴더: 투명 1×1 그림 대신 폴더 그림(밝은·어두운 화면 모두 보이는 회색) */
			background: rgba(128,128,128,.16) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23909399'%3E%3Cpath d='M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8l-2-2z'/%3E%3C/svg%3E") center / 26px no-repeat;
			border-radius: 6px;
		}
		html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body {
			display: -webkit-box;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 3;
			line-clamp: 3;
			white-space: normal !important;
			overflow: hidden;
			overflow-wrap: anywhere;
			line-height: 1.3 !important;
			min-height: 0;
		}
		/* 넓은 화면: 한 줄이 화면 끝까지 길게 비지 않게 2열(768px~)·3열(1400px~) */
		@media (min-width: 768px) {
			html.mc-list-view .grid .row > a > .col.mb-3,
			html.mc-list-view .grid .row > .col.mb-3 {
				flex: 0 0 calc(50% - 2px) !important;
				max-width: calc(50% - 2px) !important;
				width: calc(50% - 2px) !important;
			}
		}
		@media (min-width: 1400px) {
			html.mc-list-view .grid .row > a > .col.mb-3,
			html.mc-list-view .grid .row > .col.mb-3 {
				flex: 0 0 calc(33.333% - 2.7px) !important;
				max-width: calc(33.333% - 2.7px) !important;
				width: calc(33.333% - 2.7px) !important;
			}
		}
		@media (max-width: 576px) {
			html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-body,
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body {
				font-size: min(<?php echo (int)$list_font_size; ?>px, 17px);
			}
		}
		/* ★ (2026-11-04 17:55) 그리드 보기 카드 배지 — 펜닐 결정(시안 A+B)
		   · 모서리(A): N = 썸네일 왼쪽 위, ★ 즐겨찾기·ⓘ 정보 = 오른쪽 위(원래 자리)
		   · 아래 띠(B): 쪽수·쪽 순서·모드·형식·크기·파일 수·진행 % 를 썸네일 아래쪽 반투명 띠에 ' · '로 한 줄(넘치면 두 줄) — 세로로 쌓여 그림을 가리지 않게
		   · 크기: 기기 종류가 아니라 실제 카드 폭에 비례(폭 × 약 7%, 10~14px) — 폰 세로·가로·태블릿·PC 어디서든 자동
		   · 배지는 하나도 빼지 않음(글자 그대로) · :has·컨테이너 단위를 모르는 옛 브라우저는 예전 배치 그대로 · 목록 보기는 위 규칙(제목 아래 한 줄) */
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) {
			display: grid;
			grid-template-columns: minmax(0, 1fr);
			grid-template-rows: auto auto;
			align-content: start;
			container-type: inline-size;
		}
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img,
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-placeholder,
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > img.card-img-top { grid-column: 1; grid-row: 1; }
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-body { grid-column: 1; grid-row: 2; }
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay {
			grid-column: 1; grid-row: 1; align-self: end;
			position: static !important; inset: auto;
			margin: 0 !important; padding: .34em .55em !important;
			display: flex; flex-wrap: wrap; align-items: center; gap: .1em .38em;
			font-size: 11px; font-size: clamp(10px, 7cqw, 14px); line-height: 1.25;
			background: rgba(20, 22, 28, .66) !important; color: #fff;
			border-radius: 0 0 .25rem .25rem; pointer-events: none; z-index: 2; min-width: 0;
		}
		/* 띠에 들어갈 배지가 없으면(N 만 있으면) 띠 없음 */
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay:not(:has(> .badge:not(.badge-danger))) { background: none !important; padding: 0 !important; }
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay > .badge:not(.badge-danger) {
			background: none !important; color: #fff !important; padding: 0 !important; margin: 0 !important; border-radius: 0;
			font-size: 1em; font-weight: 700; line-height: inherit; white-space: nowrap; max-width: 100%; overflow: hidden; text-overflow: ellipsis;
		}
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay > .badge:not(.badge-danger) * { color: inherit !important; }
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay > .badge:not(.badge-danger) ~ .badge:not(.badge-danger)::before { content: '·'; margin-right: .38em; opacity: .75; }
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay > .badge-progress.badge-warning { color: #ffd666 !important; }
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay > .badge-progress.badge-success { color: #8be3a5 !important; }
		/* N: 썸네일 왼쪽 위(카드 기준 — 띠는 position:static 이라 카드가 기준) */
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay > .badge.badge-danger {
			position: absolute; top: calc(.25rem + 4px); left: calc(.25rem + 4px); z-index: 3; margin: 0 !important;
			font-size: 11px; font-size: clamp(10px, 7cqw, 14px); line-height: 1; padding: .3em .55em; border-radius: 999px;
		}
		/* 관리자 선택 상자(왼쪽 위)가 있으면 N 을 옆으로 */
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay):has(> .admin-delete-checkbox) > .card-img-overlay > .badge.badge-danger,
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay):has(> .mc-lib-chk) > .card-img-overlay > .badge.badge-danger { left: calc(.25rem + 32px); }
		/* ★·ⓘ 도 카드 폭에 맞춰(작은 화면은 지금 크기, 넓은 카드는 조금 크게) */
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .fav-btn { font-size: 14px; font-size: clamp(14px, 8.5cqw, 20px); top: calc(.25rem + 4px); right: calc(.25rem + 3px); }
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .mc-lib-info {
			width: clamp(20px, 11.5cqw, 26px); height: clamp(20px, 11.5cqw, 26px); line-height: clamp(20px, 11.5cqw, 26px); font-size: clamp(12px, 7cqw, 15px);
			top: calc(.25rem + 3px); right: calc(.25rem + 9px + clamp(16px, 9.8cqw, 23px));
		}

		/* ★ (2026-11-05) 카드 아이콘·배지 정리 (펜닐 요청)
		   ① 제목 앞 아이콘(.mc-ci) — 서버가 아이콘과 제목을 붙여서 내보내고(사이 띄어쓰기 없음) 간격은 여기서: 글꼴(목록 글꼴 Dongle 등)마다 띄어쓰기 폭이 달라 배지 줄을 맞출 수 없었음
		   ② 목록 보기 — 파일: 배지 줄·제목 둘째 줄을 제목 첫 글자 위치에 맞춤(아이콘은 왼쪽에 걸침) / 폴더: 아이콘·이름·칩을 글자 기준선에 맞춤(목록 글꼴이 아래로 앉아 아이콘·칩만 위로 떠 보였음)
		   ③ 크기 — 배지·N·★·ⓘ 를 썸네일(카드) 크기에 맞춰 자동으로: 그리드는 카드 폭(cqw), 목록은 관리자 '목록 썸네일 폭'(--mc-lt-w). PC 넓은 카드에서 너무 커 보이던 상한을 낮춤
		   ④ N — 옆 배지와 같은 높이·글자 크기, 좌우 여백을 줄여 작은 알약 모양으로(그리드·목록·폴더 칩 모두 같은 규칙) */
		.card > .card-body .mc-ci { display: inline-block; margin-right: 4px; }
		.card > .card-body .mc-ci > svg { vertical-align: -.15em; }
		.card > .card-body .mc-ci:not(.emoji-icon) > svg { vertical-align: -.1em; }
		/* ② 목록 보기 · 파일 카드: [아이콘][제목 …] / [배지 줄] — 배지 줄 시작 = 제목 첫 글자 */
		html.mc-list-view .grid .card:has(> .card-body > .mc-ci:first-child) { --mc-ci-box: 16px; --mc-ci-gap: 5px; --mc-ci-w: calc(var(--mc-ci-box) + var(--mc-ci-gap)); }
		html.mc-list-view .grid .card:has(> .card-body > .mc-ci:first-child) > .card-body { padding-left: var(--mc-ci-w) !important; }
		html.mc-list-view .grid .card > .card-body > .mc-ci:first-child { width: var(--mc-ci-box); margin-left: calc(-1 * var(--mc-ci-w)); margin-right: var(--mc-ci-gap); text-align: center; }
		html.mc-list-view .grid .card:has(> .card-body > .mc-ci:first-child) > .card-img-overlay { padding-left: var(--mc-ci-w) !important; }
		/* ② 목록 보기 · 폴더 카드(넓은 화면 한 줄 배치): 기준선 맞춤 / 휴대폰 세로(이름 아래 칩 줄): 칩 줄을 이름 첫 글자 위치에 */
		@media (min-width: 577px) {
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body:has(> .mc-fchips) { align-items: baseline; gap: 0 8px; }
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body:has(> .mc-fchips) > .mc-ci { margin-right: -3px; } /* 아이콘~이름 간격은 다른 줄과 같게(위 gap 8px - 3px = 5px) */
			/* 칩의 기준선 = 칩 글자(전에는 칩 안 아이콘 아래 끝이 기준이라 칩 글자가 이름보다 위로 떠 보였음) */
			html.mc-list-view .grid .card.mc-folder-card .mc-fchips > .mc-fchip:not(#_) { display: inline-block; }
			html.mc-list-view .grid .card.mc-folder-card .mc-fchips > .mc-fchip:not(#_) > svg { display: inline-block; vertical-align: -.16em; margin: 0 .25em 0 0 !important; }
		}
		@media (max-width: 576px) {
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body:has(> .mc-ci:first-child) { padding-left: 21px !important; }
			html.mc-list-view .grid .card.mc-folder-card > .card-body > .card-body > .mc-ci:first-child { width: 16px; margin-left: -21px; margin-right: 5px; text-align: center; }
		}
		/* ③ 목록 보기 배지 — 목록 썸네일 폭에 비례(기본 54px → 약 11.5px, 넓게 설정하면 최대 12.5px) */
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-img-overlay { --mc-lbfs: clamp(10.5px, calc(7.5px + var(--mc-lt-w, 54px) * .075), 12.5px); }
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-img-overlay .badge {
			font-size: clamp(10.5px, calc(7.5px + var(--mc-lt-w, 54px) * .075), 12.5px); font-size: var(--mc-lbfs); line-height: 1.2; padding: .26em .6em;
		}
		/* ★ (2026-11-05 22:50) N — 옆 배지(31p 등)와 같은 높이·같은 알약 모양, 글자만 살짝 작게(펜닐: 동그라미처럼 보이고 글자가 큼) — 높이 = 옆 배지(글자 × 1.2 + 위아래 .26em × 2) */
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .card-img-overlay .badge.badge-danger {
			display: inline-flex; align-items: center; justify-content: center; min-width: 0;
			font-size: calc(var(--mc-lbfs) * .88); line-height: 1; height: calc(var(--mc-lbfs) * 1.72); padding: 0 calc(var(--mc-lbfs) * .78);
		}
		/* 목록 보기: 동영상 재생 아이콘은 원래대로 제목 글자 크기(1em) — 아이콘 칸을 그 크기로(줄이지 않음) */
		html.mc-list-view .grid .card:has(> .card-body > .mc-ci:first-child > svg.bi-play-circle) { --mc-ci-box: <?php echo (int)$list_font_size; ?>px; }
		@media (max-width: 576px) { html.mc-list-view .grid .card:has(> .card-body > .mc-ci:first-child > svg.bi-play-circle) { --mc-ci-box: min(<?php echo (int)$list_font_size; ?>px, 17px); } } /* 휴대폰 목록 제목 글자 상한(17px)과 같게 */
		/* ③ 그리드 보기 — 카드 폭 비례(폰 2열 ≈170px → 지금과 거의 같게, PC 넓은 카드는 상한을 낮춤)
		   카드 폭 단위(cqw)를 아는 브라우저에서만(@supports) — 모르는 브라우저(사파리 15.4~15.6 등)는 변수 값이 무효가 되어 대체값도 안 먹고 글자가 커졌을 것. 그런 브라우저는 위 예전 규칙 그대로 */
		@supports (width: 1cqw) {
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) {
			--mc-bfs: clamp(10px, calc(9.4px + 1.25cqw), 12.5px); /* 배지 띠·N 글자 */
			--mc-fav: clamp(13px, calc(10px + 2.4cqw), 16px);     /* ★ 크기 */
			--mc-inf: calc(var(--mc-fav) * 1.22);                /* ⓘ 지름 — ★ (2026-11-05 17:55) ★ 보다 커 보였음 → 1.15 / ★ (22:50) 그리드에선 살짝 작아 보임 → 1.22(펜닐) */
		}
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay { font-size: 11px; font-size: var(--mc-bfs); padding: .3em .55em !important; }
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .card-img-overlay > .badge.badge-danger {
			font-size: 11px; font-size: calc(var(--mc-bfs) * .88); font-weight: 700; line-height: 1.2; padding: .22em .78em; border-radius: 999px; /* ★ (2026-11-05 22:50) 동그라미 → 배지처럼 가로로 긴 알약, 글자 살짝 작게 */
		}
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .fav-btn { font-size: 14px; font-size: var(--mc-fav); }
		html:not(.mc-list-view) .grid .card:has(> .card-img-overlay) > .mc-lib-info {
			width: var(--mc-inf); height: var(--mc-inf); line-height: var(--mc-inf); font-size: calc(var(--mc-inf) * .6);
			top: calc(.25rem + 3px + (var(--mc-fav) * 1.15 - var(--mc-inf)) / 2); /* ★ 와 세로 가운데를 맞춤 */
			right: calc(.25rem + 3px + var(--mc-fav) * 1.15 + 4px);
		}
		/* ③④ 폴더 카드 칩·N — 카드 폭 비례(넓은 PC 카드 12.5px, 폰 2열 11px 안팎), N 은 칩과 같은 높이의 작은 알약 */
		.grid .card.mc-folder-card { container-type: inline-size; }
		.grid .card.mc-folder-card .mc-fchips { --mc-fc: clamp(11px, calc(9.5px + .9cqw), 12.5px); --mc-fch: calc(var(--mc-fc) * 1.64); min-height: var(--mc-fch); gap: 4px; }
		html.mc-list-view .grid .card.mc-folder-card .mc-fchips { --mc-fc: clamp(10.5px, calc(7.5px + var(--mc-lt-w, 54px) * .075), 12.5px); } /* 목록 보기: 파일 줄 배지와 같은 크기(목록 썸네일 폭 비례) */
		.grid .card.mc-folder-card .mc-fchips > .mc-fchip:not(#_) { height: var(--mc-fch); padding: 0 .62em; border-radius: 999px; font-size: var(--mc-fc) !important; line-height: var(--mc-fch) !important; gap: .25em; }
		.grid .card.mc-folder-card .mc-fchips > .mc-fchip:not(#_) > svg { width: 1.08em !important; height: 1.08em !important; }
		.grid .card.mc-folder-card .mc-fchips > .badge.mc-fnew:not(#_) { height: var(--mc-fch); min-width: 0; padding: 0 calc(var(--mc-fc) * .85); border-radius: 999px; font-size: calc(var(--mc-fc) * .88) !important; line-height: 1 !important; font-weight: 700; } /* ★ (2026-11-05 22:50) 옆 칩과 같은 높이·알약 모양(동그라미 아님), 글자 살짝 작게(펜닐) */
		html:not(.mc-list-view) .grid .card.mc-folder-card .mc-fchips > .badge.mc-fnew:not(#_) { margin-left: 0; } /* ★ (2026-11-05 23:10) 그리드: 칩 → N 간격을 칩끼리와 같게(4px) — 폴더 배지 기본 왼쪽 여백 2px 때문에 N 만 떨어져 보이고 가운데 정렬이 한쪽으로 치우쳤음(펜닐). 목록 보기는 그대로 */
		} /* @supports (width: 1cqw) */
		/* ★ (2026-11-05 17:55) ⓘ(작품 정보) 단추 — 펜닐 제보: ★ 보다 크고, 마우스를 올리거나 휴대폰에서 누르면 'i' 가 미쿠색(#39c5bb)으로 바뀜
		   원인: 카드 전체가 링크라 '링크에 마우스를 올리면 안의 글자 전부 미쿠색' 규칙(a:hover … *)에 ⓘ 도 걸림(휴대폰은 누른 뒤에도 hover 가 남음)
		   → 'i' 는 늘 흰색, 마우스가 있는 기기에서만 ⓘ 자체에 올렸을 때 조금 진하게, 누를 때 파란 테두리·회색 번쩍임 없음(키보드 이동 때는 테두리 보임) */
		.grid .card > .mc-lib-info:not(#_), .grid a:hover .card > .mc-lib-info:not(#_) { color: #fff !important; text-shadow: none; box-shadow: none; -webkit-tap-highlight-color: transparent; }
		.grid .card > .mc-lib-info:focus:not(:focus-visible) { outline: none; }
		@media (hover: none) { .grid .card > .mc-lib-info:hover { background: rgba(0,0,0,.45); opacity: .85; } } /* 휴대폰: 누른 뒤 남는 hover 로 진해지지 않게(원래 색 그대로) */
		/* 목록 보기 — ★·ⓘ 도 목록 썸네일 폭에 비례(기본 54px → ★ 약 14px), ⓘ 는 ★ 칸과 같은 크기·같은 높이 */
		html.mc-list-view .grid .card:has(> .card-img-overlay) { --mc-fav: clamp(13px, calc(8px + var(--mc-lt-w, 54px) * .11), 16px); }
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .fav-btn { font-size: 14px; font-size: var(--mc-fav); }
		html.mc-list-view .grid .card:has(> .card-img-overlay) > .mc-lib-info {
			width: calc(var(--mc-fav) * 1.15); height: calc(var(--mc-fav) * 1.15); line-height: calc(var(--mc-fav) * 1.15); font-size: calc(var(--mc-fav) * .7);
			right: calc(5px + var(--mc-fav) * 1.15 + 4px);
		}

		/* ★ (2026-11-05 15:40) 그리드 보기 폴더 표지 — 그림 자체가 둥글게(펜닐 제보: 파일 썸네일은 둥근데 폴더 표지는 각져 보임)
		   표지는 칸(폭 100%·높이 120px) 안에 비율대로 넣는 방식(contain)이라 둥근 모서리가 빈 칸에만 걸렸음 → 그림 칸 폭을 그림 폭에 맞춤(가운데 정렬·높이·비율·자르지 않는 방식은 그대로)
		   그림이 카드보다 넓으면 예전처럼 카드 폭까지만(그 경우만 위아래 여백) · 표지 없는 폴더(투명 그림)는 그대로 · 목록 보기는 별도 규칙(칸을 채워 원래 둥긂) */
		html:not(.mc-list-view) .grid .card.mc-folder-card > .card-body > img:not([src^="data:"]) { width: auto !important; min-width: 1px !important; max-width: 100% !important; } /* min-width 1px: 그림을 받기 전(늦게 불러오기) 폭이 0이 되지 않게 */
		html:not(.mc-list-view) .grid .card.mc-folder-card > .card-body:has(> img:not([src^="data:"])) { text-align: center; } /* 그림 줄만 가운데(이름 줄은 원래 가운데) — 예전엔 그림 칸이 폭 100%라 표가 안 났음 */

		/* 뱃지 안 이모지 정렬 */
		.badge .emoji-icon {
			vertical-align: baseline;
			width: auto;
		}

/* ★ (2026-11-02 21:18) 창(내 정보·2단계 등)이 열려 있으면 삭제 모드 아래 막대 숨김 — 같은 z-index 라 휴대폰 가로에서 창의 '닫기'를 덮었음 */
body.modal-open #bulkDeleteBar { visibility: hidden; pointer-events: none; }
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
  outline: none; /* ← 추가: 포커스 테두리 제거 */
/*  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);  */
  transition: opacity 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}
#scrollTopBtn:hover {
  background-color: rgba(0, 0, 0, 0.5);
}

a.badge-danger {
  display: inline-block !important;
  background-color: #dc3545 !important;
  color: #fff !important;
  padding: 0.3em 0.6em;
  border-radius: 0.25rem;
  font-size: 0.75em;
}

/* 검색용 css */
.search-results-list {
    list-style-type: none;
    padding-left: 0;
}

.search-results-list li {
    margin-bottom: 8px;
    padding: 8px 12px;
    border-left: 3px solid #007bff;
    background-color: #f8f9fa;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.search-result-link {
    color: #007bff !important;
    font-weight: 500;
    text-decoration: none !important;
    display: block;
    transition: color 0.3s ease;
}

.search-result-link:hover {
    color: #0056b3 !important;
}

/* 현재 방문 중인 폴더 스타일 (핑크색) */
.current-folder-item {
    border-left: 3px solid #e91e63 !important;
    background-color: #fce4ec !important;
    box-shadow: 0 2px 4px rgba(233, 30, 99, 0.2);
}

.search-result-link.current-folder {
    color: #e91e63 !important;
    font-weight: bold !important;
}

.search-result-link.current-folder:hover {
    color: #c2185b !important;
}
/* ★ (2026-11-02 19:05) 다크모드 검색 결과 줄 — 밝은 바탕(#f8f9fa)에 다크모드 밝은 글자가 올라가 거의 안 보였음 */
.dark-mode .search-results-list li, [data-theme="dark"] .search-results-list li { background-color: #1e1e1e; }
.dark-mode .current-folder-item, [data-theme="dark"] .current-folder-item { background-color: #3a1f2a !important; }
/* ★ (2026-11-04 17:50) 현재 위치에 검색 결과가 없을 때 — 다른 폴더 목록(누르면 그 폴더에서 같은 검색어로) */
.mc-else { max-width: 520px; margin: 22px auto 0; text-align: left; }
.mc-else-hd { font-size: 15px; font-weight: 700; color: #1d2026; margin: 0 2px 8px; }
.mc-else-list { list-style: none; margin: 0; padding: 0; border: 1px solid #e3e6eb; border-radius: 14px; overflow: hidden; background: #fff; }
.mc-else-list li + li { border-top: 1px solid #eef0f3; }
.mc-else-list a { display: flex; align-items: center; gap: 12px; padding: 13px 16px; color: inherit !important; text-decoration: none !important; min-height: 44px; }
.mc-else-list a:hover { background: #f6f8fb; }
.mc-else-ico { flex: 0 0 auto; color: #2f5fd0; display: inline-flex; }
.mc-else-t { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.mc-else-n { font-size: 15px; font-weight: 600; color: #1d2026; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mc-else-p { font-size: 12px; color: #6c7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mc-else-c { flex: 0 0 auto; font-size: 13px; font-weight: 700; color: #2f5fd0; background: #e8efff; border-radius: 10px; padding: 3px 9px; white-space: nowrap; }
.mc-else-ch { flex: 0 0 auto; color: #9aa0ab; display: inline-flex; }
.mc-else-more { font-size: 12px; color: #6c7280; margin: 8px 4px 0; }
/* ★ (2026-11-04 20:51) 접힌 한 줄(여기에도 결과가 있을 때) · 더 보기 */
.mc-else-mini { margin: 4px auto 12px; }
/* ★ (2026-11-04 21:27) 지금 폴더 줄(예전 목록의 '← 현재 위치') */
.mc-else-list li.mc-else-cur { background: #fdeef4; }
.mc-else-row { display: flex; align-items: center; gap: 12px; padding: 13px 16px; min-height: 44px; }
.mc-else-here { flex: 0 0 auto; font-size: 12px; font-weight: 700; color: #c2185b; white-space: nowrap; }
.dark-mode .mc-else-list li.mc-else-cur, [data-theme="dark"] .mc-else-list li.mc-else-cur { background: #3a1f2a; }
.dark-mode .mc-else-here, [data-theme="dark"] .mc-else-here { color: #f48fb1; }
/* ★ (2026-11-04 21:27) 폴더 칩 한 줄(시안 E) — 가로로 밀기 · ★ (2026-11-04 22:04) 펜닐 요청: 아래 줄에 콘텐츠 폴더 이름, 지금 폴더는 진한 파랑 대신 사이트 초록(검색 단추와 같은 계열), 열어 본 폴더는 회색 대신 ✓ */
.mc-chips-wrap { max-width: 520px; margin: 2px auto 10px; text-align: left; }
.mc-chips-hd { font-size: 12px; color: #6c7280; margin: 0 2px 6px; }
.mc-chips { display: flex; gap: 6px; overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; scrollbar-width: thin; padding-bottom: 4px; }
.mc-chips::-webkit-scrollbar { height: 4px; } .mc-chips::-webkit-scrollbar-thumb { background: rgba(127,127,127,.35); border-radius: 2px; }
.mc-chip { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 4px 14px; box-sizing: border-box; border-radius: 14px; border: 1px solid #d5d9e0; background: #fff; color: #1d2026 !important; font-size: 14px; font-weight: 500; white-space: nowrap; text-decoration: none !important; max-width: 240px; cursor: pointer; font-family: inherit; text-align: left; }
.mc-chip:hover { background: #f6f8fb; }
.mc-chip-t { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
.mc-chip-nm { display: flex; align-items: center; gap: 5px; overflow: hidden; text-overflow: ellipsis; }
.mc-chip-sub { font-size: 11px; color: #6c7280; font-weight: 400; overflow: hidden; text-overflow: ellipsis; max-width: 210px; }
.mc-chip-n { font-size: 12px; color: #6c7280; font-weight: 600; }
.mc-chip-dot { width: 8px; height: 8px; border-radius: 50%; background: #34a853; flex: 0 0 auto; }
.mc-chip-cur, .mc-chip-cur:hover { background: #e6f4ea; border: 1.5px solid #34a853; color: #1e6b31 !important; font-weight: 700; cursor: default; }
.mc-chip-cur .mc-chip-n { color: #2e7d32; }
.mc-chip-cur .mc-chip-sub { color: #3d7a4a; }
.mc-chip-ck { display: none; font-size: 13px; font-weight: 700; color: #34a853; }
.mc-chip-all { color: #1e7e34 !important; border-style: dashed; }
/* 열어 본 폴더: 회색(공통 규칙) 대신 글자는 그대로 + ✓ */
.mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn), .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) *:not(.badge) { color: #1d2026 !important; }
.mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-n, .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-sub { color: #6c7280 !important; }
.mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-ck { display: inline; color: #34a853 !important; }
.mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn), .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) *:not(.badge) { color: #1d2026 !important; }
.mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-p { color: #6c7280 !important; }
.mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-ico { color: #2f5fd0 !important; }
.mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-c { color: #2f5fd0 !important; }
.mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-n::after { content: ' ✓'; color: #34a853; font-weight: 700; }
.dark-mode .mc-chip, [data-theme="dark"] .mc-chip { background: #1e1e1e; border-color: #3a3e48; color: #e9ebef !important; }
.dark-mode .mc-chip:hover, [data-theme="dark"] .mc-chip:hover { background: #262a31; }
.dark-mode .mc-chip-n, [data-theme="dark"] .mc-chip-n, .dark-mode .mc-chips-hd, [data-theme="dark"] .mc-chips-hd, .dark-mode .mc-chip-sub, [data-theme="dark"] .mc-chip-sub { color: #a3a9b4; }
.dark-mode .mc-chip-cur, [data-theme="dark"] .mc-chip-cur { background: #1f3a28; border-color: #4caf50; color: #c8e6c9 !important; }
.dark-mode .mc-chip-cur .mc-chip-n, [data-theme="dark"] .mc-chip-cur .mc-chip-n, .dark-mode .mc-chip-cur .mc-chip-sub, [data-theme="dark"] .mc-chip-cur .mc-chip-sub { color: #a5d6a7; }
.dark-mode .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn), .dark-mode .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) *:not(.badge), [data-theme="dark"] .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn), [data-theme="dark"] .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) *:not(.badge) { color: #e9ebef !important; }
.dark-mode .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-n, .dark-mode .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-sub, [data-theme="dark"] .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-n, [data-theme="dark"] .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-sub { color: #a3a9b4 !important; }
.dark-mode .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-ck, [data-theme="dark"] .mc-chips a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-ck { color: #81c995 !important; }
.dark-mode .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn), .dark-mode .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) *:not(.badge), [data-theme="dark"] .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn), [data-theme="dark"] .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) *:not(.badge) { color: #e9ebef !important; }
.dark-mode .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-p, [data-theme="dark"] .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-p { color: #a3a9b4 !important; }
.dark-mode .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-ico, .dark-mode .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-c, [data-theme="dark"] .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-ico, [data-theme="dark"] .mc-else-list li > a.visited-link[href*="dir="]:not(.page-btn) .mc-else-c { color: #9cbcff !important; }
.dark-mode .mc-chip-all, [data-theme="dark"] .mc-chip-all { color: #81c995 !important; }
/* ★ (2026-11-06 01:30) 펜닐 결정(C+D 합침) — 지금 폴더 맨 앞 고정 · 다른 폴더 줄 · [모두 보기] 판(PC 격자 / 휴대폰 한 칸 목록) */
.mc-chips-row { display: flex; align-items: center; gap: 6px; min-width: 0; }
.mc-chips-row > .mc-chips { flex: 1 1 auto; min-width: 0; }
.mc-chips-row > .mc-chip-cur, .mc-chips-row > .mc-chip-all { flex: 0 0 auto; margin-bottom: 4px; }
.mc-chips-sep { flex: 0 0 1px; align-self: stretch; margin: 8px 2px 12px; background: #d5d9e0; }
.mc-chips-arr { display: none; flex: 0 0 30px; width: 30px; height: 30px; margin-bottom: 4px; padding: 0; border-radius: 50%; border: 1px solid #d5d9e0; background: #fff; color: #4a5060; align-items: center; justify-content: center; cursor: pointer; }
.mc-chips-arr:hover { background: #f6f8fb; color: #1d2026; }
@media (hover: hover) and (pointer: fine) {
  .mc-chips-row.mc-ovf > .mc-chips-arr { display: inline-flex; }
  .mc-chips-row.mc-ovf.mc-at-s > .mc-chips-arr:first-of-type, .mc-chips-row.mc-ovf.mc-at-e > .mc-chips-arr:last-of-type { opacity: .35; }
}
.mc-chip-all { gap: 6px; cursor: pointer; font-family: inherit; }
.mc-chip-all.mc-on { border-style: solid; background: #e6f4ea; }
.mc-chip-all-s { display: none; }
@media (max-width: 600px) { .mc-chip-all-l { display: none; } .mc-chip-all-s { display: inline; } .mc-chip-all { padding: 4px 10px; } }
@media (min-width: 992px) { .mc-chips-wrap { max-width: 760px; } }
.mc-cp { margin-top: 6px; padding: 10px; border: 1px solid #d5d9e0; border-radius: 14px; background: #fff; }
.mc-cp[hidden] { display: none; }
.mc-cp-q { height: 38px !important; border-radius: 10px !important; font-size: 14px; }
.mc-cp-gh { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; color: #6c7280; margin: 10px 2px 6px; }
.mc-cp-gh > span:first-child { font-weight: 600; color: #3c4150; }
.mc-cp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 6px; }
.mc-cp-grid > .mc-chip { max-width: none; width: 100%; min-width: 0; }
.mc-cp-grid .mc-chip-t { flex: 1 1 auto; }
.mc-cp-grid .mc-chip-nm, .mc-cp-grid .mc-chip-sub { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: none; display: block; }
.mc-cp-grid .mc-chip-nm .mc-chip-dot { display: inline-block; margin-right: 5px; vertical-align: 1px; }
.mc-cp-grid .mc-chip-nm .mc-chip-n { margin-left: 5px; }
.mc-cp-more { width: 100%; margin-top: 8px; min-height: 40px; border: 1px dashed #b9c2cf; border-radius: 10px; background: transparent; color: #1e7e34; font-weight: 600; cursor: pointer; }
.mc-cp-more[hidden], .mc-cp-cap[hidden] { display: none; }
.mc-cp-cap, .mc-cp-none { font-size: 12px; color: #6c7280; margin: 8px 2px 0; }
@media (max-width: 600px) {
  .mc-cp { padding: 8px; }
  .mc-cp-grid { grid-template-columns: 1fr; gap: 0; }
  .mc-cp-grid > .mc-chip { border: 0; border-bottom: 1px solid #eceef2; border-radius: 0; min-height: 46px; padding: 6px 8px; background: transparent; }
  .mc-cp-grid > .mc-chip:last-child { border-bottom: 0; }
  .mc-cp-grid > .mc-chip-cur { background: #e6f4ea; border-radius: 10px; border: 0; }
}
.mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn), .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) *:not(.badge) { color: #1d2026 !important; }
.mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-n, .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-sub { color: #6c7280 !important; }
.mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-ck { display: inline; color: #34a853 !important; }
.dark-mode .mc-chips-sep, [data-theme="dark"] .mc-chips-sep { background: #3a3e48; }
.dark-mode .mc-chips-arr, [data-theme="dark"] .mc-chips-arr { background: #1e1e1e; border-color: #3a3e48; color: #c3c8d1; }
.dark-mode .mc-chips-arr:hover, [data-theme="dark"] .mc-chips-arr:hover { background: #262a31; color: #fff; }
.dark-mode .mc-chip-all.mc-on, [data-theme="dark"] .mc-chip-all.mc-on { background: #1f3a28; }
.dark-mode .mc-cp, [data-theme="dark"] .mc-cp { background: #181818; border-color: #3a3e48; }
.dark-mode .mc-cp-gh, [data-theme="dark"] .mc-cp-gh, .dark-mode .mc-cp-cap, [data-theme="dark"] .mc-cp-cap, .dark-mode .mc-cp-none, [data-theme="dark"] .mc-cp-none { color: #a3a9b4; }
.dark-mode .mc-cp-gh > span:first-child, [data-theme="dark"] .mc-cp-gh > span:first-child { color: #d6dae1; }
.dark-mode .mc-cp-more, [data-theme="dark"] .mc-cp-more { border-color: #4a505c; color: #81c995; }
@media (max-width: 600px) { .dark-mode .mc-cp-grid > .mc-chip, [data-theme="dark"] .mc-cp-grid > .mc-chip { border-bottom-color: #2c3038; } .dark-mode .mc-cp-grid > .mc-chip-cur, [data-theme="dark"] .mc-cp-grid > .mc-chip-cur { background: #1f3a28; } }
.dark-mode .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn), .dark-mode .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) *:not(.badge), [data-theme="dark"] .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn), [data-theme="dark"] .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) *:not(.badge) { color: #e9ebef !important; }
.dark-mode .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-n, .dark-mode .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-sub, [data-theme="dark"] .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-n, [data-theme="dark"] .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-sub { color: #a3a9b4 !important; }
.dark-mode .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-ck, [data-theme="dark"] .mc-cp a.mc-chip.visited-link[href*="dir="]:not(.page-btn) .mc-chip-ck { color: #81c995 !important; }
/* 내려도 검색창·칩 줄이 위에 붙어 있게(③A) — 원래 자리가 화면 위로 지나가면 같은 모양의 막대가 맨 위에 붙음 */
#mcStickySearch { position: fixed; left: 0; right: 0; top: 0; z-index: 1030; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.12); padding: 8px 12px 6px; padding-top: max(8px, env(safe-area-inset-top, 0px)); padding-left: max(12px, env(safe-area-inset-left, 0px)); padding-right: max(12px, env(safe-area-inset-right, 0px)); transform: translateY(-110%); transition: transform .18s ease; }
#mcStickySearch.mc-on { transform: none; }
#mcStickySearch > .mc-ss-in, #mcStickySearch > .mc-ss-in.root-search-section { max-width: 520px; margin: 0 auto !important; padding: 0 !important; display: flex; flex-direction: column; gap: 6px; } /* 검색창 모양은 .root-search-section 규칙 그대로 씀 */
#mcStickySearch form { margin: 0 !important; padding: 0 !important; justify-content: flex-start !important; }
#mcStickySearch .input-group { max-width: none !important; width: 100% !important; }
#mcStickySearch .mc-chips-wrap { margin: 0; max-width: none; }
#mcStickySearch .mc-chips-hd { display: none; }
.dark-mode #mcStickySearch, [data-theme="dark"] #mcStickySearch { background: #121212; box-shadow: 0 2px 10px rgba(0,0,0,.5); }
.mc-else-mini > summary.mc-else-hd, .mc-else-moreb > summary { cursor: pointer; list-style: none; display: flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 14px; margin: 0; border: 1px solid #cfdcff; background: #f3f7ff; border-radius: 12px; color: #1d2026; font-size: 14px; font-weight: 600; }
.mc-else-mini > summary::-webkit-details-marker, .mc-else-moreb > summary::-webkit-details-marker { display: none; }
.mc-else-mini > summary::after, .mc-else-moreb > summary::after { content: '▾'; margin-left: auto; color: #2f5fd0; }
.mc-else-mini[open] > summary::after, .mc-else-moreb[open] > summary::after { content: '▴'; }
.mc-else-mini[open] > summary { margin-bottom: 8px; }
.mc-else-moreb { margin-top: 8px; }
.mc-else-moreb > summary { background: transparent; border-color: #e3e6eb; font-weight: 500; color: #3b404b; }
.mc-else-moreb[open] > summary { margin-bottom: 8px; }
.dark-mode .mc-else-mini > summary, [data-theme="dark"] .mc-else-mini > summary { background: #1b2436; border-color: #2c3a57; color: #e9ebef; }
.dark-mode .mc-else-moreb > summary, [data-theme="dark"] .mc-else-moreb > summary { border-color: #3a3e48; color: #c4c8d0; }
.mc-else-tip { font-size: 13px; color: #6c7280; margin: 10px 4px 0; line-height: 1.5; }
.dark-mode .mc-else-hd, [data-theme="dark"] .mc-else-hd, .dark-mode .mc-else-n, [data-theme="dark"] .mc-else-n { color: #e9ebef; }
.dark-mode .mc-else-list, [data-theme="dark"] .mc-else-list { background: #1e1e1e; border-color: #3a3e48; }
.dark-mode .mc-else-list li + li, [data-theme="dark"] .mc-else-list li + li { border-color: #2c2f36; }
.dark-mode .mc-else-list a:hover, [data-theme="dark"] .mc-else-list a:hover { background: #262a31; }
.dark-mode .mc-else-p, [data-theme="dark"] .mc-else-p, .dark-mode .mc-else-tip, [data-theme="dark"] .mc-else-tip, .dark-mode .mc-else-more, [data-theme="dark"] .mc-else-more { color: #a3a9b4; }
.dark-mode .mc-else-c, [data-theme="dark"] .mc-else-c { background: #23324f; color: #9cbcff; }
.dark-mode .mc-else-ico, [data-theme="dark"] .mc-else-ico { color: #9cbcff; }

.current-indicator {
    color: #e91e63;
    font-size: 0.9em;
    font-weight: normal;
    margin-left: 8px;
}
/* 검색용 css */

	</style>
	<script type="text/javascript">
function bookmark_toggle() {
    $('.collapse').fadeToggle();
    
    // 안전하게 처리
    const infoElement = document.getElementById("info");
    if (infoElement) {
        infoElement.value = "";
    }
}
</script>
<!-- ✅ CSS 로드 완료 후 화면 표시 -->
<script>document.documentElement.classList.add('ready');
// ✅ 브라우저 자동 스크롤 복원 비활성화: 콜드 리오픈 시 브라우저가 우리 #ly 복원을 0으로 덮던 문제 방지.
if ('scrollRestoration' in history) { try { history.scrollRestoration = 'manual'; } catch(e){} }</script>
<?php // ★ (2026-10-29) 관리자 설정 '썸네일 크기' — 값이 있을 때만 출력(없으면 기존 화면 그대로). 정수 px만 들어감
$_mc_thumb_css = function_exists('mc_thumb_layout_css') ? mc_thumb_layout_css('index') : '';
if ($_mc_thumb_css !== ''): ?>
<style id="mcThumbLayout">
<?php echo $_mc_thumb_css; ?>
</style>
<?php endif; ?>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-11-05 23:15) 아이콘 변환을 본문보다 먼저 시작 — 예전 이모지가 잠깐 보였다가 바뀌던 것(페이지 끝의 같은 스크립트는 다시 실행되지 않음) */ ?>
</head>
<body>

<?php
// ✅ 상단 배너 설정 로드
$banner_settings = get_app_settings('banner', [
    'enabled' => false,
    'content' => '',
    'bg_color' => '#fff3cd',
    'text_color' => '#856404',
    'link' => '',
    'start_date' => '',
    'end_date' => ''
]);

// ✅ 다중 팝업 설정 로드
$popups = get_app_settings('popups', []);
$today = date('Y-m-d');

// 배너 표시 여부 확인 (기간 체크)
$show_banner = false;
if ($banner_settings['enabled'] && !empty($banner_settings['content'])) {
    $start_ok = empty($banner_settings['start_date']) || $banner_settings['start_date'] <= $today;
    $end_ok = empty($banner_settings['end_date']) || $banner_settings['end_date'] >= $today;
    $show_banner = $start_ok && $end_ok;
}

// 활성화되고 기간 내인 팝업만 필터링
$active_popups = [];
foreach ($popups as $idx => $popup) {
    if (!($popup['enabled'] ?? false)) continue;
    
    // 기간 체크
    $start_ok = empty($popup['start_date']) || $popup['start_date'] <= $today;
    $end_ok = empty($popup['end_date']) || $popup['end_date'] >= $today;
    if (!$start_ok || !$end_ok) continue;
    
    // 이미지 경로 결정
    $image_src = '';
    if (!empty($popup['image_file']) && file_exists(__DIR__ . '/src/' . $popup['image_file'])) {
        $image_src = 'src/' . $popup['image_file'];
    } elseif (!empty($popup['image_url'])) {
        $image_src = $popup['image_url'];
    }
    
    // show_mode에 따른 콘텐츠 체크
    $show_mode = $popup['show_mode'] ?? 'both';
    $has_image = !empty($image_src);
    $has_text = !empty($popup['content']);
    
    // 실제 표시될 콘텐츠가 있는지 확인
    $will_show_image = in_array($show_mode, ['both', 'image']) && $has_image;
    $will_show_text = in_array($show_mode, ['both', 'text']) && $has_text;
    
    if (!$will_show_image && !$will_show_text) continue;
    
    $popup['_image_src'] = $image_src;
    $popup['_idx'] = $idx;
    $active_popups[] = $popup;
}

// 순서대로 정렬
usort($active_popups, function($a, $b) {
    return ($a['order'] ?? 0) - ($b['order'] ?? 0);
});
?>

<!-- 상단 배너 -->
<?php if ($show_banner): ?>
<div id="topBanner" style="background:<?php echo h($banner_settings['bg_color']); ?>;color:<?php echo h($banner_settings['text_color']); ?>;padding:8px 15px;text-align:left;position:relative;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <div style="flex:1;">
        📢 <?php if (!empty($banner_settings['link'])): ?>
        <a href="<?php echo h($banner_settings['link']); ?>" target="_blank" style="color:inherit;text-decoration:none;">
            <?php echo h($banner_settings['content']); ?>
        </a>
        <?php else: ?>
        <?php echo h($banner_settings['content']); ?>
        <?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;gap:10px;font-size:12px;">
        <label style="margin:0;cursor:pointer;display:flex;align-items:center;gap:4px;opacity:0.8;">
            <input type="checkbox" id="bannerDontShowToday"> <?php echo __h("ui_dont_show_today"); ?>
        </label>
        <button type="button" onclick="closeBanner()" style="background:none;border:none;font-size:20px;cursor:pointer;color:inherit;opacity:0.7;line-height:1;padding:0 5px;">&times;</button>
    </div>
</div>
<script>
(function() {
    var bannerKey = 'banner_closed_<?php echo date('Y-m-d'); ?>';
    var banner = document.getElementById('topBanner');
    
    // 오늘 이미 "하루 보지 않기"로 닫았으면 숨김
    if (localStorage.getItem(bannerKey) === '1') {
        banner.style.display = 'none';
    }
    
    window.closeBanner = function() {
        // 체크박스 체크되어 있으면 오늘 하루 숨김
        if (document.getElementById('bannerDontShowToday').checked) {
            localStorage.setItem(bannerKey, '1');
        }
        banner.style.display = 'none';
    };
})();
</script>
<?php endif; ?>

<!-- 다중 팝업 컨테이너 -->
<?php if (!empty($active_popups)): 
    $popup_layout = get_app_settings('popup_layout', 'horizontal');
    $popup_gap = get_app_settings('popup_gap', 20);
    $popup_default_width = get_app_settings('popup_default_width', 350);
    $popup_default_height = get_app_settings('popup_default_height', 250);
?>
<style>
#popupContainer {
    position: fixed;
    top: <?php echo $show_banner ? '50px' : '10px'; ?>;
    left: 10px;
    z-index: 1050;
    gap: <?php echo (int)$popup_gap; ?>px;
    max-width: calc(100vw - 20px);
    max-height: calc(100vh - 60px);
    pointer-events: none;
    overflow: auto;
    <?php if ($popup_layout === 'vertical'): ?>
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    <?php elseif ($popup_layout === 'grid'): ?>
    display: grid;
    grid-template-columns: repeat(2, auto);
    <?php else: /* horizontal - 3열 */ ?>
    display: grid;
    grid-template-columns: repeat(3, auto);
    <?php endif; ?>
}
.notice-popup {
    background: #fff;
    color: #212529; /* ★ (2026-11-02 20:05) 다크모드에서 흰 팝업 위에 밝은 글자(body 색)가 올라가 안 보였음 */
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    overflow: hidden;
    pointer-events: auto;
    display: flex;
    flex-direction: column;
}
.notice-popup-header {
    padding: 8px 12px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    font-weight: bold;
}
.notice-popup-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #666;
    line-height: 1;
    padding: 0 5px;
}
.notice-popup-close:hover { color: #000; }
.notice-popup-body {
    flex: 1;
    overflow: auto;
}
.notice-popup-body img {
    display: block;
    max-width: 100%;
    height: auto;
}
.notice-popup-text {
    padding: 12px;
    white-space: pre-line;
    line-height: 1.6;
    font-size: 14px;
}
.notice-popup-footer {
    padding: 8px 12px;
    background: #f8f9fa;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
}
.notice-popup-footer label {
    margin: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}
@media (max-width: 768px) {
    #popupContainer {
        top: <?php echo $show_banner ? '45px' : '5px'; ?>;
        left: 5px;
        gap: 5px;
    }
    .notice-popup {
        max-width: calc(100vw - 10px) !important;
    }
}
</style>

<div id="popupContainer">
<?php foreach ($active_popups as $popup): 
    $popup_id = 'popup_' . $popup['_idx'];
    $image_src = $popup['_image_src'] ?? '';
    $show_mode = $popup['show_mode'] ?? 'both';
    $show_image = in_array($show_mode, ['both', 'image']) && !empty($image_src);
    $show_text = in_array($show_mode, ['both', 'text']) && !empty($popup['content']);
    
    // 크기 결정 (개별 설정 > 기본 설정)
    $width = $popup['width'] ?? '';
    $height = $popup['height'] ?? '';
    $style_width = $width ? $width . 'px' : $popup_default_width . 'px';
    $style_height = $height ? 'height:' . $height . 'px;' : 'height:' . $popup_default_height . 'px;';
    
    // 이미지만 있고 크기가 지정 안된 경우 auto로
    if ($show_image && !$show_text && !$width) {
        $style_width = 'auto';
        $style_height = '';
    }
?>
<div class="notice-popup" id="<?php echo $popup_id; ?>" style="width:<?php echo $style_width; ?>;<?php echo $style_height; ?>">
    <div class="notice-popup-header" style="background:<?php echo h($popup['bg_color'] ?? '#f8f9fa'); ?>;">
        <span><?php echo h($popup['title'] ?: __('ui_notice')); ?></span>
        <button type="button" class="notice-popup-close" onclick="closePopup('<?php echo $popup_id; ?>')">&times;</button>
    </div>
    <div class="notice-popup-body">
        <?php if ($show_image): ?>
        <img src="<?php echo h($image_src); ?>" alt="<?php echo h($popup['title'] ?? __('ui_notice')); ?>" style="<?php echo $width ? 'width:100%;' : 'max-width:' . ($width ?: '400') . 'px;'; ?>">
        <?php endif; ?>
        <?php if ($show_text): ?>
        <div class="notice-popup-text"><?php echo h($popup['content']); ?></div>
        <?php endif; ?>
    </div>
    <div class="notice-popup-footer">
        <label>
            <input type="checkbox" id="<?php echo $popup_id; ?>_dontshow"> <?php echo __h('ui_dont_show_today'); ?>
        </label>
        <button type="button" class="btn btn-sm btn-secondary" onclick="closePopup('<?php echo $popup_id; ?>')"><?php echo __h('common_close'); ?></button>
    </div>
</div>
<?php endforeach; ?>
</div>

<script>
(function() {
    var today = '<?php echo date('Y-m-d'); ?>';
    
    // 각 팝업 표시 여부 체크
    <?php foreach ($active_popups as $popup): 
        $popup_id = 'popup_' . $popup['_idx'];
    ?>
    (function() {
        var key = '<?php echo $popup_id; ?>_' + today;
        var popup = document.getElementById('<?php echo $popup_id; ?>');
        if (localStorage.getItem(key) === '1') {
            if (popup) popup.style.display = 'none';
        }
    })();
    <?php endforeach; ?>
    
    // 컨테이너에 표시할 팝업이 없으면 숨김
    setTimeout(function() {
        var container = document.getElementById('popupContainer');
        if (container) {
            var visiblePopups = container.querySelectorAll('.notice-popup:not([style*="display: none"])');
            if (visiblePopups.length === 0) {
                container.style.display = 'none';
            }
        }
    }, 100);
})();

function closePopup(popupId) {
    var popup = document.getElementById(popupId);
    var checkbox = document.getElementById(popupId + '_dontshow');
    
    if (checkbox && checkbox.checked) {
        var today = '<?php echo date('Y-m-d'); ?>';
        localStorage.setItem(popupId + '_' + today, '1');
    }
    
    if (popup) {
        popup.style.display = 'none';
    }
    
    // 모든 팝업이 닫혔는지 체크
    var container = document.getElementById('popupContainer');
    if (container) {
        var visiblePopups = container.querySelectorAll('.notice-popup:not([style*="display: none"])');
        if (visiblePopups.length === 0) {
            container.style.display = 'none';
        }
    }
}
</script>
<?php endif; ?>

<script>
// i18n strings for JavaScript
var i18n = <?php
$_i18n_data = [
    // Common
    'loading' => __('ui_loading'),
    'processing' => __('js_processing'),
    'error_occurred' => __('js_error_occurred'),
    'load_failed' => __('js_load_failed'),
    'log_load_failed' => __('js_log_load_failed'),
    'close' => __('common_close'),
    'cancel' => __('common_cancel'),
    'confirm_text' => __('common_confirm'),
    'copied' => __('js_copied'),
    
    // Profile
    'no_email' => __('profile_no_email'),
    'no_info' => __('profile_no_info'),
    'enter_current_password' => __('profile_enter_current_pw'),
    'password_min_8' => __('profile_pw_min_8'),
    'password_mismatch' => __('profile_pw_mismatch'),
    'change_password' => __('profile_change_password_btn'),
    'change_email' => __('profile_change_email_btn'),
    'enter_password' => __('profile_enter_password'),
    'withdraw_confirm' => __('profile_withdraw_confirm'),
    'withdraw_complete' => __('profile_withdraw_complete'),
    'login_log_empty' => __('profile_login_log_empty'),
    'login_log_total' => __('profile_login_log_total'),
    'login_log_date' => __('profile_log_date'),
    'login_log_ip' => __('profile_log_ip'),
    'login_log_country' => __('profile_log_country'),
    'login_log_device' => __('profile_log_device'),
    'email_label' => __('profile_email_label'),
    'joined_label' => __('profile_joined_label'),
    'ip_label' => __('profile_ip_label'),
    'country_label' => __('profile_country_label'),
    'unregistered' => __('profile_unregistered'),
    
    // 2FA
    'twofa_not_set' => __('js_2fa_not_set'),
    'twofa_desc' => __('js_2fa_desc'),
    'twofa_setup' => __('js_2fa_setup'),
    'twofa_supported_apps' => __('js_2fa_supported_apps'),
    'twofa_apps_list' => __('js_2fa_apps_list'),
    'twofa_enabled' => __('js_2fa_enabled'),
    'twofa_since' => __('js_2fa_since'),
    'twofa_disable_title' => __('js_2fa_disable_title'),
    'twofa_disable_placeholder' => __('js_2fa_disable_ph'),
    'twofa_disable_btn' => __('js_2fa_disable_btn'),
    'twofa_backup_title' => __('js_2fa_backup_title'),
    'twofa_backup_remaining' => __('js_2fa_backup_remaining'),
    'twofa_backup_desc' => __('js_2fa_backup_desc'),
    'twofa_backup_regen' => __('js_2fa_backup_regen'),
    'twofa_delete_title' => __('js_2fa_delete_title'),
    'twofa_delete_desc' => __('js_2fa_delete_desc'),
    'twofa_delete_ph' => __('js_2fa_delete_ph'),
    'twofa_delete_btn' => __('js_2fa_delete_btn'),
    'twofa_generating' => __('js_2fa_generating'),
    'twofa_gen_fail' => __('js_2fa_gen_fail'),
    'twofa_register_msg' => __('js_2fa_register_msg'),
    'twofa_scan_msg' => __('js_2fa_scan_msg'),
    'twofa_manual_key' => __('js_2fa_manual_key'),
    'twofa_enter_otp' => __('js_2fa_enter_otp'),
    'twofa_activate' => __('js_2fa_activate'),
    'twofa_backup_preview' => __('js_2fa_backup_preview'),
    'twofa_enter_6digit' => __('js_2fa_enter_6digit'),
    'twofa_activate_fail' => __('js_2fa_activate_fail'),
    'twofa_enter_code' => __('js_2fa_enter_code'),
    'twofa_cur_pw_ph' => __('profile_current_password_ph'),
    'twofa_confirm_disable' => __('js_2fa_confirm_disable'),
    'twofa_disable_fail' => __('js_2fa_disable_fail'),
    'twofa_enter_reset' => __('js_2fa_enter_reset'),
    'twofa_confirm_delete' => __('js_2fa_confirm_delete'),
    'twofa_delete_fail' => __('js_2fa_delete_fail'),
    'twofa_regen_confirm' => __('js_2fa_regen_confirm'),
    'twofa_regen_result' => __('js_2fa_regen_result'),
    'twofa_regen_fail' => __('js_2fa_regen_fail'),
    
    // Folder/File operations
    'new_folder_title' => __('js_new_folder_title'),
    'folder_name_label' => __('js_folder_name_label'),
    'folder_name_ph' => __('js_folder_name_ph'),
    'current_location' => __('js_current_location'),
    'root' => __('js_root'),
    'enter_folder_name' => __('js_enter_folder_name'),
    'create_fail' => __('js_create_fail'),
    
    // Upload
    'upload_title' => __('js_upload_title'),
    'upload_drag' => __('js_upload_drag'),
    'upload_info' => __('js_upload_info'),
    'upload_filename' => __('js_upload_filename'),
    'upload_size' => __('js_upload_size'),
    'upload_status' => __('js_upload_status'),
    'upload_progress' => __('js_upload_progress'),
    'upload_current_loc' => __('js_upload_current_loc'),
    'upload_supported' => __('js_upload_supported'),
    'upload_vpn_warning' => __('js_upload_vpn_warning'),
    'upload_cancel_btn' => __('js_upload_cancel_btn'),
    'upload_start' => __('js_upload_start'),
    'upload_waiting' => __('js_upload_waiting'),
    'upload_done' => __('js_upload_done'),
    'upload_failed' => __('js_upload_fail_status'),
    'upload_count_status' => __('js_upload_count_status'),
    'upload_complete' => __('js_upload_complete_msg'),
    'upload_cancel_confirm' => __('js_upload_cancel_confirm'),
    'upload_in_progress' => __('js_upload_in_progress'),
    'upload_total_count' => __('js_upload_total_count'),
    'upload_init_fail' => __('js_upload_init_fail'),
    'upload_cancelled' => __('js_upload_cancelled'),
    'upload_remaining' => __('js_upload_remaining'),
    'upload_chunk_info' => __('js_upload_chunk_info'),
    'upload_chunk_fail' => __('js_upload_chunk_fail'),
    'upload_parse_error' => __('js_upload_parse_error'),
    'upload_network_error' => __('js_upload_network_error'),
    'upload_finish_fail' => __('js_upload_finish_fail'),
    'upload_eta_done' => __('js_upload_eta_done'),
    'upload_eta_sec' => __('js_upload_eta_sec'),
    'upload_eta_min' => __('js_upload_eta_min'),
    'upload_eta_hour' => __('js_upload_eta_hour'),

    // Delete
    'select_all' => __('js_select_all'),
    'deselect_all' => __('js_deselect_all'),
    'selected_count' => __('js_selected_count'),
    'bulk_delete' => __('js_bulk_delete'),
    'delete_btn' => __('js_delete_btn'),
    'delete_file_label' => __('js_delete_file_label'),
    'delete_folder_label' => __('js_delete_folder_label'),
    'select_items_delete' => __('js_select_items_delete'),
    'confirm_delete_items' => __('js_confirm_delete_items'),
    'confirm_file_items' => __('js_confirm_file_items'),
    'confirm_folder_items' => __('js_confirm_folder_items'),
    'confirm_irreversible' => __('js_confirm_irreversible'),
    'confirm_folder_final' => __('js_confirm_folder_final'),
    'confirm_file_final' => __('js_confirm_file_final'),
    'deleting' => __('js_deleting'),
    'delete_fail' => __('js_delete_fail'),
    'confirm_file_delete' => __('js_confirm_file_delete'),
    'confirm_file_delete2' => __('js_confirm_file_delete2'),
    'confirm_folder_delete' => __('js_confirm_folder_delete'),
    'confirm_folder_delete2' => __('js_confirm_folder_delete2'),

    // Favorites
    'unfavorite' => __('ui_unfavorite'),
    'add_favorite' => __('ui_add_favorite'),
    
    // Cache
    'cache_rebuild_confirm' => __('js_cache_rebuild_confirm'),
    // 2FA QR
    'twofa_qr_fail' => __('twofa_qr_fail'),
    'twofa_enter_key' => __('twofa_enter_key'),
];
// ko.php 단일 따옴표 문자열의 리터럴 \n을 실제 줄바꿈으로 변환
$_i18n_data = array_map(function($v) {
    return is_string($v) ? str_replace('\n', "\n", $v) : $v;
}, $_i18n_data);
echo json_encode($_i18n_data, JSON_UNESCAPED_UNICODE);
?>;
</script>

<?php // ★ (2026-11-03 10:50) 로그인·자동 로그인·새 탭마다 사용자별로 라이브러리 전체를 세 번(목록 캐시·압축 정보·검색 색인) 훑던 것 → 사이트 전체에 하나만 도는 배경 갱신
//   갱신할 때가 됐을 때만(관리자 설정 간격, 기본 10분) 이 화면이 조용히 한 번 부름. 서버가 잠금으로 동시에 하나만 돌리고, 바뀐 폴더만 다시 만듦(mc_bg_refresh_run)
if (isset($_SESSION['user_id']) && mc_bg_due()): ?>
<script>setTimeout(function () { try { fetch('index.php?mc_bg=1', { credentials: 'same-origin', cache: 'no-store' }).catch(function () {}); } catch (e) {} }, 3000);</script>
<?php endif; ?>

<div class="container-fluid" >

<?php

// ===== 통합 권한 파일 로드 =====
$permissions_file = "./src/folder_permissions.json";

// ✅ load_permissions()는 function.php에서 통합 제공 (파일 잠금 포함)

// 통합 권한 데이터 로드
$all_permissions = load_permissions($permissions_file);
// ===== 통합 권한 파일 로드 끝 =====

// ===== 보안 강화: GET 파라미터 안전하게 처리 =====
// 모든 GET 파라미터를 미리 검증하고 정제
// ★ (2026-10-30) 검색어는 원문으로 받음(출력할 때마다 h()/hv() 로 이스케이프함) — 전에는 미리 HTML 이스케이프돼
//   & < > " 가 든 검색어(예: Tom & Jerry)가 원래 이름과 비교되지 않아 결과가 0건이고, 화면엔 &amp;amp; 로 보였음
$q = (isset($_GET['q']) && is_string($_GET['q'])) ? trim(str_replace("\0", '', $_GET['q'])) : '';
// ★ (2026-11-07 fix41) sq = 찾은 폴더 사이 이동용 검색어(목록을 거르지 않음) — 폴더 이름이 맞아 들어간 폴더는 q 없이 열려(그 안 파일로 거르면 안 되니까) 아래 '찾은 폴더 이동 막대'를 이어 가려고 따로 받음
$mc_sq = (isset($_GET['sq']) && is_string($_GET['sq'])) ? trim(str_replace("\0", '', $_GET['sq'])) : '';
if (mb_strlen($mc_sq, 'UTF-8') > 200) $mc_sq = trim(mb_substr($mc_sq, 0, 200, 'UTF-8'));
if (mb_strlen($q, 'UTF-8') > 200) $q = trim(mb_substr($q, 0, 200, 'UTF-8')); // ★ (2026-11-02 19:05) 아주 긴 검색어는 200자까지 — 붙여쓰기 분리가 글자 수 × 사전 크기만큼 돌아 요청 하나가 몇 초씩 걸릴 수 있었음
$dir_param = get_param('dir', 'path', '');
// ★ (2026-11-03 01:26) 정렬을 기기(브라우저)마다 기억 — 주소에 정렬이 없으면 쿠키 mc_sort(정렬 칩을 고르면 아래 스크립트가 적음)
$__mc_sort_def = (is_string($_COOKIE['mc_sort'] ?? null) && in_array($_COOKIE['mc_sort'], ['nameasc', 'namedesc', 'timeasc', 'timedesc', 'sizeasc', 'sizedesc', 'countasc', 'countdesc'], true)) ? $_COOKIE['mc_sort'] : 'nameasc';
$sort_param = validate_sort_mode(get_param('sort', 'string', $__mc_sort_def));
$page_param = validate_page(get_param('page', 'int', 0));
$uppage_param = validate_page(get_param('uppage', 'int', 0));
// ===== GET 파라미터 처리 끝 =====

// NFD → NFC 정규화 함수 (자모분리 → 조합형 통일)
function normalize_korean($str) {
    if (class_exists('Normalizer')) {
        return Normalizer::normalize($str, Normalizer::FORM_C);
    }
    return $str;
}

$translated_q = []; // 정방향 번역 (한글→영어) - 배열
$reverse_translated_q = []; // 역방향 번역 (영어→한글) - 배열

// 검색어가 있으면 미리 번역 처리
if (!empty($q)) {
    $q = trim($q);
    // 검색어 NFC 정규화 (NFD 자모분리 → NFC 조합형)
    $q = normalize_korean($q);
    
    // ✅ 검색 로그 기록 (★ (2026-11-02 21:18) 뷰어에서 돌아와 목록을 몰래 다시 받는 요청은 빼기 — 돌아올 때마다 '검색'이 한 번 더 쌓였음)
    //   (헤더만으로 빠지지 않게 — 이 세션이 바로 전에 같은 검색어를 기록했을 때만 건너뜀)
    if (!(($_SERVER['HTTP_X_MC_REFRESH'] ?? '') === '1' && ($_SESSION['mc_last_logged_q'] ?? null) === $q)) {
        log_user_activity('검색', $q);
        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['mc_last_logged_q'] = $q;
    }
    
    // 한글이 포함되어 있으면 한글 → 영어 번역 (배열 반환)
    if (preg_match('/[ㄱ-ㅎㅏ-ㅣ가-힣]/u', $q)) {
        $translated_q = translateSearch($q);
    }
    // 영어만 있으면 영어 → 한글 번역 (배열 반환)
    elseif (preg_match('/^[a-zA-Z\s]+$/', $q)) {
        $reverse_translated_q = reverseTranslateSearch($q);
    }
}

$getdir = ''; // 기본 초기화

if (!empty($dir_param)) {
    
    // ✅ decode_file_param() 사용으로 이중 인코딩 대응 통일
    $getdir = decode_file_param($dir_param);
    
    // 경로 보안 검증
    $getdir = str_replace("/..", "", $getdir);
    // ★ (2026-10-30) 보안: {dt}·{pc}5C 등 인코딩을 풀면 '..'·'\' 가 다시 생겨 위의 한 번 치환을 우회할 수 있었음
    //   → 상위 폴더 조각이 남았거나 실제 경로가 콘텐츠 폴더 밖이면 최상위로
    $getdir = str_replace('\\', '/', $getdir);
    if (preg_match('#(^|/)\.\.(/|$)#', $getdir) || strpos($getdir, "\0") !== false) {
        $getdir = '';
    }
    // ★ (2026-11-02 08:47) '/.'·'//' 조각 정리 — 'dir=/.' 이면 실제로는 최상위인데 최상위 판별을 비껴가 권한 없는 최상위 폴더 이름까지 보였음
    $getdir = preg_replace('#/+#', '/', preg_replace('#(^|/)\.(?=/|$)#', '$1', $getdir));
    $getdir = ($getdir === '/' || $getdir === '') ? '' : rtrim($getdir, '/');
    $dir = $base_dir . $getdir;
    // ★ (2026-11-09 14:17) 폴더가 없을 때만: 디스크 이름이 자모 분리형(NFD — 맥에서 옮긴 폴더 등)이고 주소가 조합형(NFC — 검색 색인이 만든 링크)이면 한 단계씩 실제 이름으로 바꿈
    //   검색이 일부러 남겨 둔 이런 폴더(mc_search_dir_exists_norm)가 검색 결과에서 눌러도 '폴더를 찾을 수 없음'이었음. 있는 폴더는 예전 그대로
    if ($getdir !== '' && !is_dir($dir) && class_exists('Normalizer')) {
        $__nc = rtrim((string)$base_dir, '/\\'); $__nr = []; $__nok = true;
        foreach (explode('/', trim($getdir, '/')) as $__ns) {
            if ($__ns === '') continue;
            if (is_dir($__nc . '/' . $__ns)) { $__nc .= '/' . $__ns; $__nr[] = $__ns; continue; }
            $__nh = null; $__nn = normalize_korean($__ns);
            foreach ((@scandir($__nc) ?: []) as $__n) { if ($__n !== '.' && $__n !== '..' && normalize_korean($__n) === $__nn && is_dir($__nc . '/' . $__n)) { $__nh = $__n; break; } }
            if ($__nh === null) { $__nok = false; break; }
            $__nc .= '/' . $__nh; $__nr[] = $__nh;
        }
        if ($__nok && $__nr) { $getdir = (strncmp($getdir, '/', 1) === 0 ? '/' : '') . implode('/', $__nr); $dir = $base_dir . $getdir; }
        unset($__nc, $__nr, $__nok, $__ns, $__nh, $__nn, $__n);
    }
    if ($getdir !== '' && is_dir($dir)) {
        $__rb = realpath($base_dir); $__rd = realpath($dir);
        if ($__rb === false || $__rd === false || ($__rd !== $__rb && strpos($__rd, rtrim($__rb, '\\/') . DIRECTORY_SEPARATOR) !== 0)) {
            $getdir = '';
            $dir = $base_dir;
        }
    }
    dir_check($getdir);  // 권한 체크만 수행
    
    // ✅ 폴더 접근 로그 기록 (AJAX 요청 제외)
    if (!isset($_GET['get_folders']) && !isset($_GET['make_thumbnail_cache']) && 
        !isset($_GET['make_cover_cache']) && !isset($_GET['make_filelist_cache']) &&
        !isset($_GET['make_folder_cache']) && !isset($_GET['make_image_files_cache']) &&
        !isset($_GET['make_video_files_cache']) && !isset($_GET['make_index']) &&
        !isset($_GET['make_search_index']) && !isset($_GET['thumb'])) {
        // ★ (2026-11-02 22:17) 뷰어에서 돌아와 목록을 몰래 다시 받는 요청은 같은 폴더를 이미 기록했으면 빼기('검색'과 같은 방식)
        if (!(($_SERVER['HTTP_X_MC_REFRESH'] ?? '') === '1' && ($_SESSION['mc_last_logged_dir'] ?? null) === $getdir)) {
            log_user_activity('폴더 접근', $getdir);
            if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['mc_last_logged_dir'] = $getdir;
        }
    }
} else {
    $dir = $base_dir;
}


// 검색어 포함된 전체 폴더 검색 //

// 로그인 직후에만 전체 인덱스 생성 - bidx별 분리
// ★ (2026-10-30) 캐시 만들기 요청 권한 — 로그인 직후 자동으로 부르는 3가지(make_index·make_zip_cache·make_filelist_cache, 강제·폴더 지정 없이)만
//   일반 사용자 허용, 나머지(관리자 화면의 캐시 작업)와 강제 재생성(force=1)·폴더 지정은 관리자만
//   (전에는 로그인만 하면 GET 한 번으로 전체 썸네일·목록 캐시를 지우고 다시 만들게 할 수 있었음)
// ★ (2026-11-04 09:39) 다른 사이트에서 온 캐시 작업 요청은 관리자라도 거절(CSRF) — 링크 하나로 관리자가 고른 표지가 덮이고 책마다 저장한 보기 방식이 초기화됐음
//   (브라우저가 알려 주는 Sec-Fetch-Site — 관리자 화면의 캐시 작업은 같은 사이트에서 부르므로 영향 없음, security_helpers 의 다른 요청 검사와 같은 방식)
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    foreach ($_GET as $__mk_k => $__mk_v) {
        if (is_string($__mk_k) && strncmp($__mk_k, 'make_', 5) === 0) {
            if (!headers_sent()) { http_response_code(403); header('Content-Type: application/json'); }
            echo json_encode(['error' => __('err_access_denied')]);
            exit;
        }
    }
    unset($__mk_k, $__mk_v);
}
if (isset($_SESSION['user_id']) && !$is_admin) {
    $__mk_keys = ['make_index', 'make_thumbnail_cache', 'make_cover_cache', 'make_zip_cache', 'make_search_index', 'make_zip_total',
                  'make_filelist_cache', 'make_folder_cache', 'make_image_files_cache', 'make_video_files_cache', 'make_zip_image_cache'];
    $__mk_auto = []; // ★ (2026-11-03 11:10) 로그인 때 자동으로 부르던 3가지는 사이트 전체 배경 갱신(mc_bg=1)으로 바뀌어 회원 허용 없앰 — 회원이 간격·잠금을 건너뛰고 전체를 훑게 할 수 있었음
    foreach ($__mk_keys as $__mk) {
        if (get_param($__mk, 'string') !== '1') continue; // 처리부와 같은 방식(앞뒤 공백 제거)으로 읽음
        if (!in_array($__mk, $__mk_auto, true) || get_param('force', 'string') === '1' || !empty($_GET['folder']) || !empty($_GET['path'])
            || !empty($_GET['stream'])) { // ★ (2026-11-02 11:36) 진행 상황 보내기(stream)는 관리자만 — 막힌 폴더 아래 폴더 이름까지 그대로 흘러나갔음(자동 호출은 stream 없이 보냄)
            if (!headers_sent()) { http_response_code(403); header('Content-Type: application/json'); }
            echo json_encode(['error' => __('api_admin_required')]);
            exit;
        }
    }
    unset($__mk_keys, $__mk_auto, $__mk);
}

// ★ (2026-11-02 14:28) 무거운 make_* 요청은 시작할 때 세션을 닫음 — 끝날 때 처음 읽은 세션을 다시 써서, 그 사이의 로그아웃·언어 변경이
//   되돌아가던 것(Redis 세션은 잠금이 기본으로 꺼져 있음). 끝에 세우던 '만들었음' 표시는 여기서 먼저 세움.
if (isset($_SESSION['user_id']) && session_status() === PHP_SESSION_ACTIVE) {
    $__mk_flags = ['make_index' => 'index_refreshed', 'make_search_index' => 'index_refreshed', 'make_zip_cache' => 'zip_cache_generated',
                   'make_filelist_cache' => 'filelist_cache_generated', 'make_thumbnail_cache' => null, 'make_cover_cache' => null,
                   'make_zip_total' => null, 'make_folder_cache' => null, 'make_image_files_cache' => null,
                   'make_video_files_cache' => null, 'make_zip_image_cache' => null];
    foreach ($__mk_flags as $__mk => $__flag) {
        if (get_param($__mk, 'string') !== '1') continue;
        if ($__flag !== null) $_SESSION[$__flag] = true;
        session_write_close();
        break;
    }
    unset($__mk_flags, $__mk, $__flag);
}

if (get_param('make_index', 'string') === '1' && isset($_SESSION['user_id'])) {
    mc_build_search_index($bidx, $base_dir); // ★ (2026-11-03 10:49) 함수로 옮김(내용 같음)
    $_SESSION['index_refreshed'] = true;
    echo "ok";
    exit;
}


// ✅ 폴더 목록 조회 (admin 폴더 브라우저용)
// ★ (2026-10-30) 관리자 폴더 탐색용 — 권한과 상관없이 폴더 이름을 돌려주므로 관리자만
if (get_param('get_folders', 'string') === '1' && isset($_SESSION['user_id']) && $is_admin) {
    $path = (isset($_GET['path']) && is_string($_GET['path'])) ? $_GET['path'] : ''; // ★ (2026-10-30) 원문(HTML 이스케이프하면 ' & 가 든 폴더를 못 찾음) — 아래 validate_file_path 가 검증
    
    // 빈 경로면 base_dir 사용
    if (empty($path) || $path === '/') {
        $target_dir = $base_dir;
    } else {
        // ✅ 경로 검증 통일 (validate_file_path 사용)
        $target_dir = validate_file_path($path, $base_dir);
        if ($target_dir === false) {
            log_user_activity('해킹시도', 'folder list: ' . substr($path, 0, 100));
            header('Content-Type: application/json');
            echo json_encode(['folders' => [], 'error' => 'Access denied']);
            exit;
        }
    }
    
    // 디렉토리 존재 확인
    if (!is_dir($target_dir)) {
        header('Content-Type: application/json');
        echo json_encode(['folders' => [], 'error' => 'Directory not found', 'path' => $path]); // ★ 서버 전체 경로는 내보내지 않음
        exit;
    }
    
    $folders = [];
    $items = @scandir($target_dir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '@eaDir') continue;
            if (strpos($item, '.') === 0) continue; // 숨김 폴더 제외
            $item_path = $target_dir . '/' . $item;
            if (is_dir($item_path)) {
                $folders[] = $item;
            }
        }
        natsort($folders);
        $folders = array_values($folders);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['folders' => $folders, 'current_path' => $path]);
    exit;
}

// ✅ 썸네일 캐시 생성 (스트리밍 - 파일명.json만)
if (get_param('make_thumbnail_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    $force = (get_param('force', 'string') === '1');
    $stream = (get_param('stream', 'string') === '1');
    $folder = (isset($_GET['folder']) && is_string($_GET['folder'])) ? $_GET['folder'] : ''; // ★ (2026-10-30) 원문 — validate_file_path 가 검증
    $recursive = get_param('recursive', 'string') !== '0';
    
    // 폴더 지정 시 해당 폴더만 처리
    $target_dir = $base_dir;
    if ($folder) {
        // ✅ 경로 검증 통일 (validate_file_path 사용)
        $target_dir = validate_file_path($folder, $base_dir);
        if ($target_dir === false || !is_dir($target_dir)) {
            log_user_activity('해킹시도', 'thumb cache: ' . substr($folder, 0, 100));
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid folder']);
            exit;
        }
    }
    
    if ($stream) {
        // SSE 스트리밍 모드
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        // 출력 버퍼링 끄기
        if (ob_get_level()) ob_end_clean();
        
        $result = generate_thumbnail_cache_streaming($target_dir, $force, $recursive);
        
        echo "data: " . json_encode(['type' => 'complete', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]) . "\n\n";
        flush();
    } else {
        // 일반 JSON 응답
        header('Content-Type: application/json');
        $result = generate_thumbnail_cache_streaming($target_dir, $force, $recursive); // ★ (2026-10-30) 지정 폴더·하위 포함 여부 반영(전에는 늘 전체)
        echo json_encode(['status' => 'ok', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
    }
    exit;
}

// ✅ 커버 이미지 생성 (스트리밍 - [cover].jpg만)
if (get_param('make_cover_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    $force = (get_param('force', 'string') === '1');
    $stream = (get_param('stream', 'string') === '1');
    $folder = (isset($_GET['folder']) && is_string($_GET['folder'])) ? $_GET['folder'] : ''; // ★ (2026-10-30) 원문 — validate_file_path 가 검증
    $recursive = get_param('recursive', 'string') !== '0';
    
    // 폴더 지정 시 해당 폴더만 처리
    $target_dir = $base_dir;
    if ($folder) {
        // ✅ 경로 검증 통일 (validate_file_path 사용)
        $target_dir = validate_file_path($folder, $base_dir);
        if ($target_dir === false || !is_dir($target_dir)) {
            log_user_activity('해킹시도', 'cover cache: ' . substr($folder, 0, 100));
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid folder']);
            exit;
        }
    }
    
    if ($stream) {
        // SSE 스트리밍 모드
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        if (ob_get_level()) ob_end_clean();
        
        $result = generate_cover_cache_streaming($target_dir, $force, $recursive);
        
        echo "data: " . json_encode(['type' => 'complete', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]) . "\n\n";
        flush();
    } else {
        header('Content-Type: application/json');
        $result = generate_cover_cache($target_dir, $force);
        echo json_encode(['status' => 'ok', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
    }
    exit;
}

// ✅ 썸네일 캐시 생성 함수 (스트리밍)
function generate_thumbnail_cache_streaming($base_dir, $force = false, $recursive = true) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    $current = 0;
    
    // 먼저 전체 ZIP 개수 파악
    $zip_files = [];
    if ($recursive) {
        $rii = new RecursiveIteratorIterator(mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($rii as $file) {
            if ($file->isDir()) continue;
            if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $file->getFilename())) {
                $zip_files[] = $file->getPathname();
            }
        }
    } else {
        $items = @scandir($base_dir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $filepath = $base_dir . '/' . $item;
                if (is_file($filepath) && preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $item)) {
                    $zip_files[] = $filepath;
                }
            }
        }
    }
    $total = count($zip_files);
    
    // natsort 정렬
    usort($zip_files, function($a, $b) {
        return strnatcasecmp($a, $b);
    });
    
    foreach ($zip_files as $zipfile) {
        $current++;
        $json_file = get_cache_json_path($zipfile);
        $relative_path = str_replace($base_dir, '', $zipfile);
        
        // 진행 상황 전송
        echo "data: " . json_encode([
            'type' => 'progress',
            'current' => $current,
            'total' => $total,
            'created' => $created,
            'skipped' => $skipped,
            'file' => $relative_path
        ]) . "\n\n";
        flush();
        
        // 빠른 생성: json이 이미 있으면 스킵
        if (!$force && file_exists($json_file)) {
            $skipped++;
            continue;
        }
        
        // 강제 재생성: 기존 파일 삭제
        if ($force && file_exists($json_file)) {
            @unlink($json_file);
        }
        
        // [5번] image_files.json 캐시 확인 - 있으면 ZIP 스캔 생략
        $image_cache_file = $zipfile . '.image_files.json';
        $video_cache_file = $zipfile . '.video_files.json';
        $image_files = [];
        $video_files = [];
        $use_cached_list = false;
        
        if (file_exists($image_cache_file)) {
            $cached_images = @json_decode(file_get_contents($image_cache_file), true);
            if (is_array($cached_images) && !empty($cached_images)) {
                $image_files = $cached_images;
                $use_cached_list = true;
            }
        }
        if (file_exists($video_cache_file)) {
            $cached_videos = @json_decode(file_get_contents($video_cache_file), true);
            if (is_array($cached_videos)) {
                $video_files = $cached_videos;
            }
        }
        
        // ✅ [RAR/7Z 지원] 압축 종류 판별 (ZIP=내장, RAR/7Z=archive_handler)
        $_arc_ext = strtolower(pathinfo($zipfile, PATHINFO_EXTENSION));
        $_is_rar7z = in_array($_arc_ext, ['rar', 'cbr', '7z', 'cb7']);
        $_arc = null;

        $zip = new ZipArchive;
        $_opened = $_is_rar7z ? (($_arc = archive_open_handler($zipfile)) !== false) : ($zip->open($zipfile) === TRUE);
        if ($_opened) {
            // 캐시가 없으면 스캔
            if (!$use_cached_list) {
                if ($_is_rar7z) {
                    // RAR/7Z: 헬퍼로 전체 목록 가져와 분류
                    foreach (archive_list_all($_arc) as $zname) {
                        if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                            $image_files[] = $zname;
                        }
                        if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                            $video_files[] = $zname;
                        }
                    }
                } else {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zname = mc_zip_name($zip, $i);
                    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                        $image_files[] = $zname;
                    }
                    if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                        $video_files[] = $zname;
                    }
                }
                }
            }
            
            // 동영상만 있는 ZIP
            if (count($image_files) == 0 && count($video_files) > 0) {
                $cache_data = [
                    'totalpage' => count($video_files),
                    'page_order' => '0',
                    'viewer' => 'video',
                    'thumbnail' => '',
                    'is_video_archive' => true
                ];
                @file_put_contents($json_file, json_encode($cache_data, JSON_UNESCAPED_UNICODE), LOCK_EX);
                // ✅ [버그수정] video_files.json도 함께 저장 (메인 루프 캐시 판정과 일관성)
                natsort($video_files);
                $video_files = array_values($video_files);
                @file_put_contents($zipfile . '.video_files.json', json_encode($video_files, JSON_UNESCAPED_UNICODE), LOCK_EX);
                if (!$_is_rar7z) { $zip->close(); }
                $created++;
                continue;
            }
            
            // 이미지가 있는 ZIP
            if (count($image_files) > 0) {
                natsort($image_files);
                $image_files = array_values($image_files);
                $thumbnail_filename = $image_files[0];
                
                // ✅ [버그수정] 이 함수는 그동안 .json(썸네일/페이지수)만 만들고
                //    .image_files.json은 저장하지 않아, RAR/7z에서 image_files.json 누락 발생.
                //    image_files를 이미 갖고 있으므로 여기서 함께 저장한다. (ZIP/RAR/7Z 공통)
                @file_put_contents($zipfile . '.image_files.json', json_encode($image_files, JSON_UNESCAPED_UNICODE), LOCK_EX);
                
                $img_data = $_is_rar7z ? archive_extract_one($_arc, $thumbnail_filename) : mc_zip_from_name($zip, $thumbnail_filename);
                if ($img_data !== false) {
                    $thumbnail_data = null;
                    global $vips_path;
                    
                    // vips 경로가 설정되어 있으면 vips 사용
                    if (!empty($vips_path) && file_exists($vips_path)) {
                        $__rnd = bin2hex(random_bytes(8)); // ★ (2026-11-02 19:05) uniqid 는 Windows Apache 스레드끼리 겹칠 수 있음(cache_util.php 와 같게)
                        $temp_in = sys_get_temp_dir() . '/thumb_in_' . $__rnd . '.' . pathinfo($thumbnail_filename, PATHINFO_EXTENSION);
                        $temp_out = sys_get_temp_dir() . '/thumb_out_' . $__rnd . '.jpg';
                        
                        @file_put_contents($temp_in, $img_data, LOCK_EX);
                        
                        // vips thumbnail 실행 (높이 400px 기준)
                        $cmd = '"' . $vips_path . '" thumbnail "' . $temp_in . '" "' . $temp_out . '" 400 --size down 2>&1';
                        exec($cmd, $output, $return_code);
                        
                        if ($return_code === 0 && file_exists($temp_out)) {
                            $thumbnail_data = file_get_contents($temp_out);
                        }
                        
                        @unlink($temp_in);
                        @unlink($temp_out);
                    }
                    
                    // vips 미설정 또는 실패 시 GD 사용
                    if ($thumbnail_data === null) {
                        $img = mc_gd_from_string($img_data); // ★ (2026-11-02 09:56) 너무 큰 그림은 건너뜀(메모리 넘침)
                        if ($img) {
                            $w = imagesx($img);
                            $h = imagesy($img);
                            $new_h = 400;
                            $new_w = max(1, intval($w * ($new_h / $h))); // ★ (2026-11-02 14:34) 아주 가는 그림이면 0 이 되어 ValueError 로 작업이 멈추던 것
                            
                            $cropimage = imagecreatetruecolor($new_w, $new_h);
                            imagecopyresampled($cropimage, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
                            imagedestroy($img);
                            
                            ob_start();
                            imagejpeg($cropimage, null, 75);
                            imagedestroy($cropimage);
                            $thumbnail_data = ob_get_contents();
                            ob_end_clean();
                        }
                    }
                    
                    if ($thumbnail_data) {
                        $cache_data = [
                            'totalpage' => count($image_files),
                            'page_order' => '0',
                            'viewer' => 'toon',
                            'thumbnail' => base64_encode($thumbnail_data)
                        ];
                        @file_put_contents($json_file, json_encode($cache_data, JSON_UNESCAPED_UNICODE), LOCK_EX);
                        $created++;
                    }
                }
            }
            
            if (!$_is_rar7z) { $zip->close(); unset($zip); }
            gc_collect_cycles();
        }
    }
    
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
}

// ✅ 썸네일 캐시 생성 함수 (일반)
function generate_thumbnail_cache($base_dir, $force = false) {
    return generate_thumbnail_cache_streaming($base_dir, $force);
}

// ✅ 커버 이미지 생성 함수 (스트리밍)
function generate_cover_cache_streaming($base_dir, $force = false, $recursive = true) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    $current = 0;
    
    // 폴더별로 ZIP 파일 그룹화
    $folders = [];
    if ($recursive) {
        $rii = new RecursiveIteratorIterator(mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($rii as $file) {
            if ($file->isDir()) continue;
            if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $file->getFilename())) {
                $dir_path = dirname($file->getPathname());
                if (!isset($folders[$dir_path])) {
                    $folders[$dir_path] = [];
                }
                $folders[$dir_path][] = $file->getPathname();
            }
        }
    } else {
        // 현재 폴더만
        $items = @scandir($base_dir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $filepath = $base_dir . '/' . $item;
                if (is_file($filepath) && preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $item)) {
                    if (!isset($folders[$base_dir])) {
                        $folders[$base_dir] = [];
                    }
                    $folders[$base_dir][] = $filepath;
                }
            }
        }
    }
    $total = count($folders);
    
    foreach ($folders as $dir_path => $zip_files) {
        $current++;
        $relative_path = str_replace($base_dir, '', $dir_path);
        $cover_file = $dir_path . '/[cover].jpg';
        
        // 진행 상황 전송
        echo "data: " . json_encode([
            'type' => 'progress',
            'current' => $current,
            'total' => $total,
            'created' => $created,
            'skipped' => $skipped,
            'folder' => $relative_path ?: '/'
        ]) . "\n\n";
        flush();
        
        // 빠른 생성: cover가 이미 있으면 스킵
        if (!$force && file_exists($cover_file)) {
            $skipped++;
            continue;
        }
        
        // 강제 재생성: 기존 커버 삭제
        if ($force && file_exists($cover_file)) {
            @unlink($cover_file);
        }
        
        // natsort로 정렬
        usort($zip_files, function($a, $b) {
            return strnatcasecmp(basename($a), basename($b));
        });
        
        // 첫 번째 유효한 ZIP에서 커버 추출
        foreach ($zip_files as $zipfile) {
            $json_file = get_cache_json_path($zipfile);
            
            // json에서 썸네일 가져오기
            if (file_exists($json_file)) {
                $json_data = @json_decode(file_get_contents($json_file), true);
                if ($json_data && !empty($json_data['thumbnail']) && strlen($json_data['thumbnail']) > 100) {
                    @file_put_contents($cover_file, base64_decode($json_data['thumbnail']), LOCK_EX);
                    $created++;
                    break;
                }
            }
            
            // json이 없으면 압축에서 직접 추출
            // ✅ [RAR/7Z 지원] ZIP=내장, RAR/7Z=archive_handler
            $_arc_ext = strtolower(pathinfo($zipfile, PATHINFO_EXTENSION));
            $_is_rar7z = in_array($_arc_ext, ['rar', 'cbr', '7z', 'cb7']);
            $_arc = null;
            $zip = new ZipArchive;
            $_opened = $_is_rar7z ? (($_arc = archive_open_handler($zipfile)) !== false) : ($zip->open($zipfile) === TRUE);
            if ($_opened) {
                $image_files = [];
                if ($_is_rar7z) {
                    foreach (archive_list_all($_arc) as $zname) {
                        if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                            $image_files[] = $zname;
                        }
                    }
                } else {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zname = mc_zip_name($zip, $i);
                    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                        $image_files[] = $zname;
                    }
                }
                }
                
                if (count($image_files) > 0) {
                    natsort($image_files);
                    $image_files = array_values($image_files);
                    $img_data = $_is_rar7z ? archive_extract_one($_arc, $image_files[0]) : mc_zip_from_name($zip, $image_files[0]);
                    
                    if ($img_data !== false) {
                        $img = mc_gd_from_string($img_data); // ★ (2026-11-02 09:56) 너무 큰 그림은 건너뜀(메모리 넘침)
                        if ($img) {
                            $w = imagesx($img);
                            $h = imagesy($img);
                            $new_h = 400;
                            $new_w = max(1, intval($w * ($new_h / $h))); // ★ (2026-11-02 14:34) 아주 가는 그림이면 0 이 되어 ValueError 로 작업이 멈추던 것
                            
                            $cropimage = imagecreatetruecolor($new_w, $new_h);
                            imagecopyresampled($cropimage, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
                            imagedestroy($img);
                            
                            ob_start();
                            imagejpeg($cropimage, null, 75);
                            $thumbnail_data = ob_get_contents();
                            ob_end_clean();
                            imagedestroy($cropimage);
                            
                            @file_put_contents($cover_file, $thumbnail_data, LOCK_EX);
                            if (!$_is_rar7z) { $zip->close(); }
                            $created++;
                            break;
                        }
                    }
                }
                if (!$_is_rar7z) { $zip->close(); }
            }
        }
        
        // 커버 생성 실패 시 스킵 카운트
        if (!file_exists($cover_file)) {
            $skipped++;
        }
        
        gc_collect_cycles();
    }
    
    // ★ (2026-11-08 fix80, 펜닐 요청) 압축 책이 없는 동영상·PDF·디지털도서 폴더도 표지를 만듦(mc_media_folder_cover_make — function.php)
    //   강제 다시 만들기여도 이미 있는 표지는 지우지 않음(직접 넣은 표지일 수 있음 — 바꾸려면 뷰어의 '대표 이미지로')
    if (function_exists('mc_media_folder_cover_make')) {
        $__roots = array_map(function ($r) { return rtrim(str_replace('\\', '/', (string)$r), '/'); }, array_values((array)($GLOBALS['base_dirs'] ?? [])));
        $__mdirs = [];
        if ($recursive) {
            try {
                $__rii = new RecursiveIteratorIterator(mc_rdi($base_dir), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
                foreach ($__rii as $__f) {
                    if (!$__f->isFile()) continue;
                    $__n = $__f->getFilename();
                    if (is_video_file($__n) || preg_match('/\.(pdf|epub|txt|hwpx?|docx?|xlsx?|pptx?)$/i', $__n)) $__mdirs[dirname($__f->getPathname())] = true;
                }
            } catch (\Throwable $e) {}
        } else {
            $__mdirs[$base_dir] = true;
        }
        foreach (array_keys($__mdirs) as $__md) {
            if (isset($folders[$__md])) continue; // 압축 책 폴더는 위에서 맡음
            if (in_array(rtrim(str_replace('\\', '/', $__md), '/'), $__roots, true)) continue; // 콘텐츠 폴더 맨 위는 카드가 없음
            if (is_file($__md . '/[cover].jpg')) { $skipped++; continue; }
            $total++; $current++;
            echo "data: " . json_encode(['type' => 'progress', 'current' => $current, 'total' => $total, 'created' => $created, 'skipped' => $skipped, 'folder' => (str_replace($base_dir, '', $__md) ?: '/')]) . "\n\n";
            flush();
            if (mc_media_folder_cover_make($__md, true)) $created++; else $skipped++;
        }
    }
    
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
}

// ✅ 커버 이미지 생성 함수 (일반)
function generate_cover_cache($base_dir, $force = false) {
    return generate_cover_cache_streaming($base_dir, $force);
}

if (get_param('make_zip_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    // 파일명.json + [cover].jpg 일괄 생성
    $force = (get_param('force', 'string') === '1');
    $result = generate_all_zip_metadata($base_dir, $force);
    $_SESSION['zip_cache_generated'] = true;
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
    exit;
}

// ✅ 검색 인덱스 생성 (스트리밍) - search_index_{bidx}.json 생성
if (get_param('make_search_index', 'string') === '1' && isset($_SESSION['user_id'])) {
    $stream = (get_param('stream', 'string') === '1');
    
    if ($stream) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (ob_get_level()) ob_end_clean();
        
        $index_file = __DIR__ . '/src/search_index_' . $bidx . '.json';
        $index_output = [];
        $__idx_dirs = [];
        $file_count = 0;
        $current = 0;
        
        // 먼저 전체 개수 파악
        $total = 0;
        $rii = new RecursiveIteratorIterator(mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($rii as $file) {
            if ($file->isFile()) $total++;
        }
        
        // 실제 처리
        $rii = new RecursiveIteratorIterator(mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($rii as $file) {
            if ($file->isFile()) {
                $current++;
                $file_count++;
                $relative_path = str_replace($base_dir, '', $file->getPathname());
                $relative_path = trim(str_replace("\\", "/", $relative_path), '/');
                // ★ (2026-11-02 11:38) 캐시·메타 파일은 검색 색인에서 뺌 / ★ (2026-11-02 12:04) 그 폴더 이름은 남김(하위 폴더만 있는 폴더가 폴더 이름 검색에서 빠지던 것) — 파일 이름 없이 폴더당 한 번
                if (mc_index_skip_name(basename($relative_path))) {
                    $__sd = function_exists('normalize_korean') ? normalize_korean(dirname($relative_path)) : dirname($relative_path);
                    if (!isset($__idx_dirs[$__sd])) { $__idx_dirs[$__sd] = true; $index_output[] = ['dir' => $__sd, 'file' => '', 'bidx' => $bidx]; }
                    continue;
                }
                
                $dir_name = function_exists('normalize_korean') ? normalize_korean(dirname($relative_path)) : dirname($relative_path);
                $file_name = function_exists('normalize_korean') ? normalize_korean(basename($relative_path)) : basename($relative_path);
                
                $index_output[] = [
                    'dir'  => $dir_name,
                    'file' => $file_name,
                    'bidx' => $bidx
                ];
                
                // 진행 상황 전송
                echo "data: " . json_encode([
                    'type' => 'progress',
                    'current' => $current,
                    'total' => $total,
                    'files' => $file_count,
                    'path' => $relative_path
                ]) . "\n\n";
                flush();
            }
        }
        
        @file_put_contents($index_file, json_encode($index_output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        
        $_SESSION['index_refreshed'] = true;
        
        echo "data: " . json_encode(['type' => 'complete', 'files' => $file_count]) . "\n\n";
        flush();
    } else {
        // 기존 방식
        header('Content-Type: text/plain');
        echo "ok";
    }
    exit;
}

// ✅ ZIP 통계 생성 (스트리밍)
if (get_param('make_zip_total', 'string') === '1' && isset($_SESSION['user_id'])) {
    $stream = (get_param('stream', 'string') === '1');
    
    if ($stream) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (ob_get_level()) ob_end_clean();
        
        $total_file = __DIR__ . '/src/zip_total_' . $bidx . '.json';
        $zip_total = 0;
        $folder_total = 0;
        $current = 0;
        
        // 먼저 전체 개수 파악 (파일 + 폴더)
        $total = 0;
        $rii = new RecursiveIteratorIterator(
            mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */,
            RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($rii as $item) {
            $total++;
        }
        
        // 실제 처리
        $rii = new RecursiveIteratorIterator(
            mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */,
            RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($rii as $item) {
            $current++;
            
            if ($item->isDir()) {
                $dirname = $item->getFilename();
                if ($dirname !== '@eaDir' && $dirname !== 'tmp') {
                    $folder_total++;
                }
            } elseif ($item->isFile()) {
                if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $item->getFilename())) {
                    $zip_total++;
                }
            }
            
            // 진행 상황 전송
            echo "data: " . json_encode([
                'type' => 'progress',
                'current' => $current,
                'total' => $total,
                'zips' => $zip_total,
                'folders' => $folder_total
            ]) . "\n\n";
            flush();
        }
        
        @file_put_contents($total_file, json_encode([
            'zip_total' => $zip_total,
            'folder_total' => $folder_total,
            'generated_at' => date('c')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        
        echo "data: " . json_encode(['type' => 'complete', 'zip_total' => $zip_total, 'folder_total' => $folder_total]) . "\n\n";
        flush();
    } else {
        // 기존 방식
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }
    exit;
}

// ✅ 파일 목록 캐시 생성 (스트리밍)
if (get_param('make_filelist_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    $force = (get_param('force', 'string') === '1');
    $stream = (get_param('stream', 'string') === '1');
    $folder = (isset($_GET['folder']) && is_string($_GET['folder'])) ? $_GET['folder'] : ''; // ★ (2026-10-30) 원문 — validate_file_path 가 검증
    $recursive = get_param('recursive', 'string') !== '0';
    
    $target_dir = $base_dir;
    if ($folder) {
        // ✅ 경로 검증 통일 (validate_file_path 사용)
        $target_dir = validate_file_path($folder, $base_dir);
        if ($target_dir === false || !is_dir($target_dir)) {
            log_user_activity('해킹시도', 'filelist cache: ' . substr($folder, 0, 100));
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid folder']);
            exit;
        }
    }
    
    if ($stream) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (ob_get_level()) ob_end_clean();
        
        $result = generate_filelist_cache_streaming($target_dir, $force, $recursive);
        
        echo "data: " . json_encode(['type' => 'complete', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]) . "\n\n";
        flush();
    } else {
        header('Content-Type: application/json');
        $result = generate_all_filelist_caches($target_dir, $force);
        echo json_encode(['status' => 'ok', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
    }
    // ★ (2026-11-03 20:58) 관리자 일괄 '파일 목록 캐시'로 새로 만든 폴더가 있으면 다음 배경 갱신이 검색 색인도 새로 — 종전엔 캐시가 다 최신이라 색인을 건너뛰어 새 책이 검색에 안 나왔음
    if ((int)($result['created'] ?? 0) > 0) @touch(__DIR__ . '/src/bg_dirty');
    $_SESSION['filelist_cache_generated'] = true;
    exit;
}

// ✅ 폴더 캐시 생성 (스트리밍)
if (get_param('make_folder_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    $force = (get_param('force', 'string') === '1');
    $stream = (get_param('stream', 'string') === '1');
    $folder = (isset($_GET['folder']) && is_string($_GET['folder'])) ? $_GET['folder'] : ''; // ★ (2026-10-30) 원문 — validate_file_path 가 검증
    $recursive = get_param('recursive', 'string') !== '0';
    
    $target_dir = $base_dir;
    if ($folder) {
        // ✅ 경로 검증 통일 (validate_file_path 사용)
        $target_dir = validate_file_path($folder, $base_dir);
        if ($target_dir === false || !is_dir($target_dir)) {
            log_user_activity('해킹시도', 'folder cache: ' . substr($folder, 0, 100));
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid folder']);
            exit;
        }
    }
    
    if ($stream) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (ob_get_level()) ob_end_clean();
        
        $result = generate_folder_cache_streaming($target_dir, $force, $recursive);
        
        echo "data: " . json_encode(['type' => 'complete', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]) . "\n\n";
        flush();
    } else {
        header('Content-Type: application/json');
        $result = generate_folder_cache_streaming($target_dir, $force, $recursive);
        echo json_encode(['status' => 'ok', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
    }
    exit;
}

// ✅ ZIP 이미지 목록 캐시 생성 (스트리밍)
if (get_param('make_image_files_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    $force = (get_param('force', 'string') === '1');
    $stream = (get_param('stream', 'string') === '1');
    $folder = (isset($_GET['folder']) && is_string($_GET['folder'])) ? $_GET['folder'] : ''; // ★ (2026-10-30) 원문 — validate_file_path 가 검증
    $recursive = get_param('recursive', 'string') !== '0';
    
    $target_dir = $base_dir;
    if ($folder) {
        // ✅ 경로 검증 통일 (validate_file_path 사용)
        $target_dir = validate_file_path($folder, $base_dir);
        if ($target_dir === false || !is_dir($target_dir)) {
            log_user_activity('해킹시도', 'image files cache: ' . substr($folder, 0, 100));
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid folder']);
            exit;
        }
    }
    
    if ($stream) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (ob_get_level()) ob_end_clean();
        
        $result = generate_image_files_cache_streaming($target_dir, $force, $recursive);
        
        echo "data: " . json_encode(['type' => 'complete', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]) . "\n\n";
        flush();
    } else {
        header('Content-Type: application/json');
        $result = generate_all_zip_image_caches($target_dir, $force);
        echo json_encode(['status' => 'ok', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
    }
    exit;
}

// ✅ ZIP 동영상 목록 캐시 생성 (스트리밍)
if (get_param('make_video_files_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    $force = (get_param('force', 'string') === '1');
    $stream = (get_param('stream', 'string') === '1');
    $folder = (isset($_GET['folder']) && is_string($_GET['folder'])) ? $_GET['folder'] : ''; // ★ (2026-10-30) 원문 — validate_file_path 가 검증
    $recursive = get_param('recursive', 'string') !== '0';
    
    $target_dir = $base_dir;
    if ($folder) {
        // ✅ 경로 검증 통일 (validate_file_path 사용)
        $target_dir = validate_file_path($folder, $base_dir);
        if ($target_dir === false || !is_dir($target_dir)) {
            log_user_activity('해킹시도', 'video files cache: ' . substr($folder, 0, 100));
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid folder']);
            exit;
        }
    }
    
    if ($stream) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (ob_get_level()) ob_end_clean();
        
        $result = generate_video_files_cache_streaming($target_dir, $force, $recursive);
        
        echo "data: " . json_encode(['type' => 'complete', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]) . "\n\n";
        flush();
    } else {
        header('Content-Type: application/json');
        $result = generate_video_files_cache_streaming($target_dir, $force, $recursive);
        echo json_encode(['status' => 'ok', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
    }
    exit;
}

// ✅ 파일 목록 캐시 생성 함수 (스트리밍) - index.php와 동일한 형식
/**
 * ★ (2026-11-03 10:50) 사이트 전체 배경 갱신 — 로그인마다 사용자별로 전체를 훑던 것을 대신함(작업 스케줄러 없이 앱 안에서)
 *   상태: src/bg_refresh.json { last_start, last_end, running, stats }  잠금: src/bg_refresh.lock(동시에 하나만)
 *   간격: 관리자 설정 bg_refresh_minutes(기본 10, 0 = 끔)
 */
function mc_bg_state_file() { return __DIR__ . '/src/bg_refresh.json'; }
function mc_bg_state() {
    $f = mc_bg_state_file();
    $d = is_file($f) ? @json_decode((string)@file_get_contents($f), true) : null;
    return is_array($d) ? $d : [];
}
function mc_bg_interval() {
    $m = (int)(function_exists('get_app_settings') ? get_app_settings('bg_refresh_minutes', 10) : 10);
    return max(0, min(1440, $m)) * 60;
}
/** 갱신할 때인지 — 꺼져 있거나, 도는 중이거나(30분 넘게 끝나지 않았으면 멈춘 것으로 봄), 마지막으로 끝난 뒤 간격이 안 지났으면 false */
function mc_bg_due($ignore_running = false) {
    $iv = mc_bg_interval();
    if ($iv <= 0) return false;
    $st = mc_bg_state();
    $now = time();
    if (!$ignore_running && !empty($st['running']) && $now - (int)($st['last_start'] ?? 0) < 1800) return false;
    return $now - (int)($st['last_end'] ?? 0) >= $iv;
}
function mc_bg_save_state(array $st) {
    @file_put_contents(mc_bg_state_file(), json_encode($st, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
/**
 * 배경 갱신 한 번: 폴더마다 목록 캐시가 맞는지 보고(수정 시각·항목 수) 바뀐 폴더만 다시 만듦.
 * 바뀐 게 있었거나(새 파일·지운 파일) 목록 화면이 그사이 다시 만든 폴더가 있으면(src/bg_dirty) 압축 정보·검색 색인도 새로.
 */
function mc_bg_refresh_run(array $base_dirs) {
    $lock = @fopen(__DIR__ . '/src/bg_refresh.lock', 'c');
    if (!$lock) return ['status' => 'error'];
    if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return ['status' => 'busy']; }
    if (!mc_bg_due(true)) { flock($lock, LOCK_UN); fclose($lock); return ['status' => 'not_due']; }
    @ignore_user_abort(true);
    @set_time_limit(0);
    $t0 = microtime(true);
    $st = mc_bg_state();
    $st['running'] = true; $st['last_start'] = time();
    mc_bg_save_state($st);
    $dirty_t = (int)@filemtime(__DIR__ . '/src/bg_dirty');
    $idx_start = is_array($st['idx_start'] ?? null) ? $st['idx_start'] : []; // ★ (2026-11-03 20:58) bidx 별 지난 색인 '시작' 시각
    $stats = [];
    foreach ($base_dirs as $bidx => $base) {
        if (!is_string($base) || $base === '' || !is_dir($base)) continue;
        $one = ['created' => 0, 'checked' => 0, 'index' => false];
        try {
            $pass = function () use ($base, &$one) {
                $c = 0; $ex = null;
                if (!mc_list_cache_fresh($base, true, $ex)) { mc_build_list_cache($base, $ex); if (!empty($GLOBALS['mc_list_cache_changed'])) $c++; } // ★ (2026-11-04 00:26) 이름이 바뀐 것만
                $r = generate_all_filelist_caches($base, false);
                $one['checked'] = (int)($r['total'] ?? 0) + 1;
                return $c + (int)($r['changed'] ?? 0);
            };
            // ★ (2026-11-08 fix80, 펜닐 요청) 표지 없는 동영상·PDF·디지털도서 폴더에 표지 만들기(한 번에 20곳까지, 쓸 그림이 없던 폴더는 바뀔 때까지 다시 안 봄)
            if (function_exists('mc_media_covers_all')) { try { $one['covers'] = mc_media_covers_all($base, 20, true); } catch (\Throwable $e) {} }
            $one['created'] = $pass();
            $idx_file = __DIR__ . '/src/search_index_' . $bidx . '.json';
            // ★ (2026-11-03 20:58) bg_dirty 는 색인 '시작' 시각과 비교 — 종전엔 색인 파일 저장 시각과 비교해, 색인을 만드는 동안 목록 화면이 찍은 변경(이미 지나간 폴더의 새 파일)을 놓쳤음
            $_ref = (int)($idx_start[$bidx] ?? 0); if ($_ref <= 0) $_ref = (int)@filemtime($idx_file);
            if ($one['created'] > 0 || !is_file($idx_file) || $dirty_t >= $_ref) {
                $_is_t = time(); // ★ (2026-11-03 23:34) 시작 시각을 잡아 두고, 색인까지 끝난 뒤에 기록(중간에 실패하면 다음 갱신이 다시 시도)
                generate_all_zip_metadata($base, false);          // 새 압축 파일의 쪽수·표지(예전 로그인 때 하던 것)
                $one['created'] += $pass();                        // 위에서 생긴 책 정보 파일 때문에 바뀐 폴더 다시
                mc_build_search_index($bidx, $base);              // 검색 색인
                $idx_start[$bidx] = $_is_t;
                $one['index'] = true;
            }
        } catch (\Throwable $e) { $one['error'] = 1; }
        $stats[$bidx] = $one;
    }
    $st = mc_bg_state();
    $st['running'] = false; $st['last_end'] = time(); $st['seconds'] = round(microtime(true) - $t0, 2); $st['stats'] = $stats; $st['idx_start'] = $idx_start;
    mc_bg_save_state($st);
    flock($lock, LOCK_UN); fclose($lock);
    return ['status' => 'done', 'seconds' => $st['seconds'], 'stats' => $stats];
}

/** ★ (2026-11-03 10:49) 검색 색인(src/search_index_{bidx}.json)·합계(zip_total_{bidx}.json) 만들기 — 예전 make_index 요청 안의 코드 그대로(배경 갱신도 씀) */
function mc_build_search_index($bidx, $base_dir) {
    $index_file = __DIR__ . '/src/search_index_' . $bidx . '.json';
    $total_file = __DIR__ . '/src/zip_total_' . $bidx . '.json';
    $index_output = [];
    $__idx_dirs = [];
    $zip_total = 0;
    $folder_total = 0;
    $counted_dirs = [];  // 폴더 중복 카운트 방지

    $iterator = new RecursiveIteratorIterator(
        mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */,
        RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
    );

    foreach ($iterator as $item) {
        // 폴더 카운트
        if ($item->isDir()) {
            $dirname = $item->getFilename();
            if ($dirname !== '@eaDir' && $dirname !== 'tmp') {
                $folder_total++;
            }
            continue;
        }
        
        // 파일 처리
        if ($item->isFile()) {
            $relative_path = str_replace($base_dir, '', $item->getPathname());
            $relative_path = trim(str_replace("\\", "/", $relative_path), '/');
            // ★ (2026-11-02 11:38) 캐시·메타 파일은 검색 색인에서 뺌 / ★ (2026-11-02 12:04) 그 폴더 이름은 남김(하위 폴더만 있는 폴더가 폴더 이름 검색에서 빠지던 것) — 파일 이름 없이 폴더당 한 번
            if (mc_index_skip_name(basename($relative_path))) {
                $__sd = function_exists('normalize_korean') ? normalize_korean(dirname($relative_path)) : dirname($relative_path);
                if (!isset($__idx_dirs[$__sd])) { $__idx_dirs[$__sd] = true; $index_output[] = ['dir' => $__sd, 'file' => '', 'bidx' => $bidx]; }
                continue;
            }
            
            // NFD → NFC 정규화 적용 (자모분리 → 조합형)
            $dir_name = normalize_korean(dirname($relative_path));
            $file_name = normalize_korean(basename($relative_path));
            
            $index_output[] = [
                'dir'  => $dir_name,
                'file' => $file_name,
                'bidx' => $bidx
            ];

            // ★ zip/cbz/pdf면 합계 카운트
            $lower = strtolower($relative_path);
            if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $lower)) {
                $zip_total++;
            }
        }
    }

    @file_put_contents($index_file, json_encode($index_output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    // ★ 합계 저장 (폴더 수 추가)
    @file_put_contents($total_file, json_encode([
        'zip_total' => $zip_total,
        'folder_total' => $folder_total,
        'generated_at' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

}

/**
 * ★ (2026-11-03 10:48) 목록 캐시가 지금 폴더와 맞는지(목록 화면이 캐시를 그대로 쓰는 조건과 똑같이) — 맞으면 true
 *   폴더 수정 시각 ≤ 캐시 시각 · 항목 수 같음(Windows 수정 시각이 안 바뀌는 경우 대비) · 버전 4 이상 · 필요한 칸 있음 · 최상위는 권한 거르기 전 전체 목록
 *   $existing 에 읽은 캐시 내용을 돌려줌(다시 만들 때 바뀌지 않은 책 정보를 재사용) */
function mc_list_cache_fresh($dir_path, $is_root, &$existing = null) {
    $existing = null;
    $cache_file = $dir_path . '/.filelist_cache.json';
    if (!is_file($cache_file)) return false;
    $cache_mtime = @filemtime($cache_file);
    $dir_mtime = @filemtime($dir_path);
    $items = @scandir($dir_path);
    $count = $items ? count($items) - 2 : 0;
    $existing = @json_decode((string)@file_get_contents($cache_file), true);
    if (!is_array($existing)) { $existing = null; return false; }
    $cached_count = $existing['total_items'] ?? -1;
    if (!($cache_mtime >= $dir_mtime && ($cached_count === -1 || $cached_count === $count))) return false;
    if (($existing['version'] ?? 1) < 4 || !isset($existing['file_list'], $existing['dir_list'], $existing['jpg_list'], $existing['title_list'], $existing['dirinfo'])) return false;
    if ($is_root && empty($existing['perm_full'])) return false;
    return true;
}

/**
 * ★ (2026-11-03 10:46) 폴더 하나의 목록 캐시(.filelist_cache.json)를 만들어 저장하고 [file_list, dir_list, jpg_list, title_list, dirinfo] 를 돌려줌
 *   목록 화면(index.php)·로그인/배경 갱신·관리자 일괄 생성이 모두 이 함수를 씀(예전엔 화면 안에 있던 코드 그대로 옮김)
 *   $list_cache = 예전 캐시 내용(있으면 바뀌지 않은 책의 정보를 다시 읽지 않음) */
function mc_build_list_cache($dir, $list_cache = null) {
    $CACHE_VERSION = 4; // 목록 화면과 같은 값(위 두 곳과 함께 바꿀 것)
    $list_cache_file = $dir . '/.filelist_cache.json';
    $file_list = $jpg_list = $dir_list = $title_list = [];
    $dirinfo = [];
    // scandir 한 번!
    $all_files = @scandir($dir, SCANDIR_SORT_NONE) ?: []; // ★ (2026-11-02 20:05) 읽을 수 없는 폴더면 경고 없이 빈 목록
    // ★ (2026-11-03 09:40) 다시 만들 때 디스크 확인 줄이기(느린 디스크·NAS 에서 파일 수백 개 폴더가 수 초 걸리던 것) — 결과는 같음
    //   ① 예전 목록 캐시의 책 정보(쪽수·보기 방식)를 이름으로 찾아 둠: 책 파일의 시각·크기가 같고 책 정보 파일(.json)이 그 캐시보다 새로 바뀌지 않았으면 .json 을 다시 열지 않음
    //   ② .json 이름(책 정보·그림 목록 — 책마다 1~2개)은 폴더인지 확인하지 않고 건너뜀(아래 파일 쪽에서도 원래 건너뜀)
    $__old_items = []; $__old_mtime = 0;
    $__stems = []; // ★ (2026-11-03 11:33) 책 이름(확장자 뺀 것, rar·7z 는 확장자 포함) — 위 .json 건너뛰기용
    foreach ($all_files as $__n) { if (!is_string($__n) || $__n === '' || $__n[0] === '.' || preg_match('/\.json$/i', $__n)) continue; $__l = strtolower($__n); $__stems[$__l] = true; $__p = strrpos($__l, '.'); if ($__p) $__stems[substr($__l, 0, $__p)] = true; }
    if (isset($list_cache) && is_array($list_cache) && is_array($list_cache['file_list'] ?? null)) {
        $__old_mtime = (int)@filemtime($list_cache_file); // ★ (2026-11-03 11:33) 예전 캐시 파일의 디스크 시각(같은 파일 시스템 시계 — NAS 시계가 달라도 비교가 맞게, 전엔 웹서버 시각)
        foreach ($list_cache['file_list'] as $__oi) { if (is_array($__oi) && isset($__oi['name']) && is_string($__oi['name'])) $__old_items[$__oi['name']] = $__oi; }
    }
    foreach ($all_files as $filename) {
        if ($filename[0] === '.' || $filename === '@eaDir' || $filename === 'tmp' || $filename === 'robots.txt') continue;
        if (preg_match('/\.json$/i', $filename) && (preg_match('/\.(image|video)_files\.json$/i', $filename) || isset($__stems[strtolower(substr($filename, 0, -5))]))) continue; // ★ (2026-11-03 09:40) ② 위 / ★ (2026-11-03 11:33) 책 정보 파일(같은 이름 책이 있는 것)만 — 이름이 .json 으로 끝나는 폴더가 목록에서 사라졌음
        $filepath = $dir . '/' . $filename;

        $is_dir = is_dir($filepath);
        $is_file = !$is_dir;

        // ★ (2026-10-30) 루트 목록 캐시는 모든 사용자가 같이 쓰므로 권한 숨김은 캐시에 넣지 않고 아래(캐시 읽은 뒤)에서 매번 적용
        //   (전에는 처음 만든 사람의 그룹 기준으로 캐시돼, 관리자가 먼저 열면 숨김 폴더가 모두에게 보였음)

 if ($is_dir) {
    // 특수 폴더 체크 (rclone_ 폴더명)
    if (strpos($filename, "rclone_") !== false) {
        $dir_list[] = $filename;
        $dirinfo[$filename] = "remote";
        continue;
    }

$jpg_count = 0;
$zip_count = 0;
$video_count = 0;
$has_subdir = false;
$subdir_count = 0;
$has_pdf = false;
$has_epub = false;
$has_txt = false;
$pure_image_count = 0;
$newest_mtime = 0;  // ✅ 폴더 내 가장 최신 파일의 mtime

$cache_file = $filepath . '/.folder_cache.json';
$folder_mtime = @filemtime($filepath);
$cache_needs_update = true;

// ✅ 실제 파일 개수 (Windows mtime 버그 대응)
$actual_items = @scandir($filepath);
$actual_count = $actual_items ? count($actual_items) - 2 : 0;

if (is_file($cache_file)) {
    $cache_mtime = @filemtime($cache_file);
    $cache = json_decode((string)@file_get_contents($cache_file), true); // ★ 2026-11-04 00:28 쓰는 중이면(Windows 잠금) 경고 없이
    $cached_count = $cache['total_items'] ?? -1;
    
    // ✅ mtime 비교 + 파일 개수 비교 (둘 다 일치해야 캐시 사용)
    if ($cache_mtime >= $folder_mtime && ($cached_count === -1 || $cached_count === $actual_count)) {
        // video_count가 없는 구버전 캐시도 갱신 대상
        if (isset($cache['jpg_count'], $cache['zip_count'], $cache['has_subdir'], $cache['video_count'], $cache['subdir_count']) && ((int)($cache['cv'] ?? 0) >= 3 || ((int)($cache['cv'] ?? 0) === 2 && (int)$cache['jpg_count'] < 11))) { // ★ (2026-11-04 09:39) cv 3: 그림 11장에서 멈추던 옛 셈(00:26 고침)으로 만든 캐시는 그림 11장 이상이면 한 번 다시 셈(하위 폴더를 못 봐 이미지 폴더로 잘못 분류됐을 수 있음) / ★ (2026-11-02 22:03) cv 2: 같은 이름 표지·[cover] 는 쪽 그림으로 안 셈(function.php mc_folder_is_cover_img) — 예전 캐시는 한 번 다시 셈
            $jpg_count = $cache['jpg_count'];
            $zip_count = $cache['zip_count'];
            $video_count = $cache['video_count'];
            $has_subdir = $cache['has_subdir'];
            $subdir_count = $cache['subdir_count'] ?? ($has_subdir ? 1 : 0);
            $newest_mtime = $cache['newest_mtime'] ?? 0;
            $cache_needs_update = false;
        }
    }
}

if ($cache_needs_update) {
    $__mc_stems = mc_folder_book_stems($actual_items ?: []);
    if ($dh = @opendir($filepath)) {
        while (($sf = readdir($dh)) !== false) {
            if ($sf[0] === '.' || $sf === '@eaDir' || $sf === 'tmp' || $sf === 'robots.txt') continue;
            $sf_full = $filepath . '/' . $sf;
            // ★ (2026-11-04 00:26) 그림은 폴더인지 확인하지 않고 세고 다음으로 — 종전엔 그림 11장에서 읽기를 멈춰(break) 그 뒤 하위 폴더·동영상·책을 못 봄(이미지 폴더로 잘못 분류돼 하위 폴더에 못 들어감), 임계값 11 이상이면 이미지 폴더가 안 생겼음
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $sf)) {
                if (!str_ends_with($sf, '.video_thumb.jpg') && !mc_folder_is_cover_img($sf, $__mc_stems)) $jpg_count++;
                continue;
            }

            // ✅ 콘텐츠 파일만 newest_mtime 계산 (캐시/메타 파일 제외)
            if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|epub|txt|mp4|mkv|avi|mov|webm|m4v|ts|mts|m2ts|wmv|flv)$/i', $sf)) {
                $sf_ctime = get_file_created_time($sf_full);
                if ($sf_ctime > $newest_mtime) $newest_mtime = $sf_ctime;
            }

            if (is_dir($sf_full)) {
                $has_subdir = true;
                $subdir_count++;
//                break;   // break를 제거하고 계속 진행
           } else {

            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $sf) && !str_ends_with($sf, '.video_thumb.jpg') && !mc_folder_is_cover_img($sf, $__mc_stems)) {
                $jpg_count++;
            }

if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $sf)) {
                $zip_count++;
                // 파일 타입 플래그 설정
                if (preg_match('/\.pdf$/i', $sf)) $has_pdf = true;
                if (preg_match('/\.epub$/i', $sf)) $has_epub = true;
                if (preg_match('/\.txt$/i', $sf)) $has_txt = true;
            }
            
            if (is_video_file($sf)) {
                $video_count++;
            }

		   }

        }
        closedir($dh);
    }
    $pure_image_count = mc_folder_count_page_imgs($actual_items ?: [], $__mc_stems); // ★ (2026-11-02 22:14) 카드 'N장' 칩용 — 전에는 0 으로 저장돼 목록 캐시를 다시 만들면 칩이 사라졌음

    $cache = [
        'jpg_count' => $jpg_count,
        'zip_count' => $zip_count,
        'video_count' => $video_count,
        'has_subdir' => $has_subdir,
        'subdir_count' => $subdir_count,
        'has_pdf' => $has_pdf,
        'has_epub' => $has_epub,
        'has_txt' => $has_txt,
        'pure_image_count' => $pure_image_count,
        'newest_mtime' => $newest_mtime,
        'total_items' => $actual_count + (in_array('.folder_cache.json', $actual_items ?: [], true) ? 0 : 1),  // ✅ 파일 개수 저장 (Windows mtime 버그 대응) ★ (2026-11-03 07:51) 처음 만들 때는 이 캐시 파일 자신도 셈 — 빼고 세면 다음에 열 때 개수가 달라 폴더를 한 번 더 훑었음
        'cv' => 3, // ★ (2026-11-04 09:39) 3: 그림을 끝까지 셈
        'mtime' => time()
    ];
    @file_put_contents($cache_file, json_encode($cache), LOCK_EX);
}

    // 판단 결과 반영
    // config.php에서 설정 가져오기 (기본값 설정)
    $imgfolder_threshold = $GLOBALS['imgfolder_threshold'] ?? 5;  // 이미지 폴더 임계값
    $video_folder_as_dir = $GLOBALS['video_folder_as_dir'] ?? true;  // 동영상 있으면 폴더로 표시
    
    if ($has_subdir) {
        $dir_list[] = $filename;
    } elseif ($video_folder_as_dir && $video_count > 0) {
        // 동영상이 있으면 폴더 아이콘으로 표시 (설정에 따라)
        $dir_list[] = $filename;
    } elseif ($jpg_count > $imgfolder_threshold) {
        $file_list[] = [
            'name' => $filename . '_imgfolder',
            'time' => @filemtime($filepath),
            'size' => 0
        ];
    } else {
        $title_list[] = $filename;
    }
}

else {
    // 파일
    if ($filename === '[cover].jpg') continue;

    // .image_files.json 제외
    if (str_ends_with($filename, '.image_files.json')) continue;  //php8.x 용
//	if (substr($filename, -18) === '.image_files.json') continue;  //php7.x 용

    // 일반 .json 파일도 무시
    if (preg_match('/\.json$/i', $filename)) continue;

if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $filename)) {
    // ✅ 배지 정보도 함께 저장
    $__ft = @filemtime($filepath); $__fs = @filesize($filepath); // ★ (2026-11-03 09:40) 위 폴더 확인과 같은 정보라 디스크를 다시 안 봄(전엔 .json 을 본 뒤라 한 번 더 확인)
    $json_file = get_cache_json_path($filepath);
    $badge_info = ['totalpage' => 0, 'page_order' => '0', 'viewer' => 'toon', 'is_video_archive' => false];
    $__jm = @filemtime($json_file); // 없으면 false
    $__oi = $__old_items[$filename] ?? null;
    if ($__jm !== false && is_array($__oi) && $__old_mtime > 0 && $__jm <= $__old_mtime
        && ($__oi['time'] ?? null) === $__ft && ($__oi['size'] ?? null) === $__fs && (int)($__oi['totalpage'] ?? 0) > 0) {
        // ★ (2026-11-03 09:40) ① 위 — 바뀐 게 없으면 예전 캐시 값 그대로
        $badge_info['totalpage'] = $__oi['totalpage'];
        $badge_info['page_order'] = $__oi['page_order'] ?? '0';
        $badge_info['viewer'] = $__oi['viewer'] ?? 'toon';
        $badge_info['is_video_archive'] = $__oi['is_video_archive'] ?? false;
    } elseif ($__jm !== false) {
        $json_data = @json_decode((string)@file_get_contents($json_file), true);
        if ($json_data) {
            $badge_info['totalpage'] = $json_data['totalpage'] ?? 0;
            $badge_info['page_order'] = $json_data['page_order'] ?? '0';
            $badge_info['viewer'] = $json_data['viewer'] ?? 'toon';
            $badge_info['is_video_archive'] = $json_data['is_video_archive'] ?? false;
        }
    }
    $file_list[] = [
        'name' => $filename,
        'time' => $__ft,
        'size' => $__fs,
        'ctime' => get_file_created_time($filepath), // ★ (2026-11-04 11:18) 올린 시각(Windows 만든 시각) — 관리자 'N 기준: 서재에 올린 시각'일 때 카드 N 이 씀(위 filemtime 과 같은 stat 캐시라 디스크를 더 안 봄)
        'totalpage' => $badge_info['totalpage'],
        'page_order' => $badge_info['page_order'],
        'viewer' => $badge_info['viewer'],
        'is_video_archive' => $badge_info['is_video_archive']
    ];

} elseif (is_video_file($filename)) {
    // 동영상 파일 추가
    $file_list[] = [
        'name' => $filename,
        'time' => @filemtime($filepath),
        'size' => @filesize($filepath),
        'type' => 'video'
    ];
} elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename) && !str_ends_with($filename, '.video_thumb.jpg')) {
    $jpg_list[] = $filename;
}
}
    }
    
    // ✅ 파일 목록 캐시 저장 (루트는 src 폴더에, 일반은 해당 폴더에)
    $list_cache_data = [
        'version' => $CACHE_VERSION,  // ✅ 버전 추가
        'file_list' => $file_list,
        'dir_list' => $dir_list,
        'jpg_list' => $jpg_list,
        'title_list' => $title_list,
        'dirinfo' => $dirinfo,
        'total_items' => ($all_files ? count($all_files) - 2 : 0) + (in_array('.filelist_cache.json', $all_files ?: [], true) ? 0 : 1),  // ✅ 파일 개수 저장 (Windows mtime 버그 대응) ★ (2026-11-03 07:51) 처음 만들 때는 이 캐시 파일 자신도 셈 — 빼고 세면 다음에 열 때 '오래됨'으로 목록·하위 폴더를 한 번 더 만들었음
        'mtime' => time(),
        'perm_full' => true // ★ (2026-10-30) 권한으로 거르지 않은 전체 목록
    ];
    $GLOBALS['mc_list_cache_written'] = (@file_put_contents($list_cache_file, json_encode($list_cache_data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false);
    // ★ (2026-11-04 00:26) 책·폴더 이름이 예전 캐시와 달라졌는지(목록 화면의 bg_dirty 판정과 같은 기준) — 배경 갱신이 앱이 쓴 정보 파일(.image_files.json·[cover].jpg 등) 때문에 다시 만든 폴더까지 '바뀜'으로 세어 갱신마다 전체 검색 색인을 다시 만들었음
    $__nm = function ($c) { if (!is_array($c)) return null; $n = []; foreach (['file_list', 'dir_list', 'title_list', 'jpg_list'] as $__k) foreach ((is_array($c[$__k] ?? null) ? $c[$__k] : []) as $__e) $n[] = $__k . ':' . (is_array($__e) ? (string)($__e['name'] ?? '') . (($__k === 'file_list' && substr((string)($__e['name'] ?? ''), -10) !== '_imgfolder') ? '|' . (int)($__e['time'] ?? 0) . '|' . (int)($__e['size'] ?? 0) : '') : (string)$__e); sort($n); return $n; }; // ★ (2026-11-04 01:50) 책 파일은 수정 시각·크기도 비교(같은 이름으로 내용만 바꿔 넣어도 검색 색인을 다시 만듦 — 쪽수·표지 정보 파일은 이미 있으면 그대로) — 이미지 폴더 시각은 앱이 쓰는 파일로도 바뀌어 뺌. 아래 목록 화면($__names)과 같은 기준
    $GLOBALS['mc_list_cache_changed'] = !empty($GLOBALS['mc_list_cache_written']) && $__nm($list_cache) !== $__nm($list_cache_data); // ★ (2026-11-03 11:10) 저장됐는지(쓰기 막힌 폴더는 '다시 만듦'으로 세지 않게 — 갱신 때마다 전체 색인을 다시 만들었음)
    return [$file_list, $dir_list, $jpg_list, $title_list, $dirinfo];
}

function generate_filelist_cache_streaming($base_dir, $force = false, $recursive = true) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    $current = 0;
    $CACHE_VERSION = 4; // ★ (2026-11-02 22:03) 4: 폴더 분류(이미지 폴더 판정)에서 같은 이름 표지를 빼 예전 목록 캐시는 한 번 다시 만듦
    
    // 폴더 목록 수집
    $folders = [$base_dir];
    if ($recursive) {
        $iterator = new RecursiveIteratorIterator(
            mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */,
            RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $folders[] = $item->getPathname();
            }
        }
    }
    $total = count($folders);
    
    foreach ($folders as $dir_path) {
        $current++;
        $cache_file = $dir_path . '/.filelist_cache.json';
        $relative_path = str_replace($base_dir, '', $dir_path);
        
        echo "data: " . json_encode([
            'type' => 'progress',
            'current' => $current,
            'total' => $total,
            'created' => $created,
            'skipped' => $skipped,
            'folder' => $relative_path ?: '/'
        ]) . "\n\n";
        flush();
        
        // ★ (2026-11-03 10:48) 새로 만들지 판단·만들기 모두 목록 화면과 같은 함수(mc_list_cache_fresh·mc_build_list_cache) — 예전엔 따로 만든 캐시를 화면이 다시 만들었음
        $existing = null;
        if (!$force && mc_list_cache_fresh($dir_path, $dir_path === $base_dir, $existing)) { $skipped++; continue; }
        if (!is_dir($dir_path) || @scandir($dir_path) === false) { $skipped++; continue; }
        mc_build_list_cache($dir_path, $existing);
        if (!empty($GLOBALS['mc_list_cache_written'])) $created++; else $skipped++;
    }
    
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
}

// ✅ 폴더 캐시 생성 함수 (스트리밍)
function generate_folder_cache_streaming($base_dir, $force = false, $recursive = true) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    $current = 0;
    
    // 폴더 목록 수집
    $folders = [$base_dir];
    if ($recursive) {
        $iterator = new RecursiveIteratorIterator(
            mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */,
            RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $folders[] = $item->getPathname();
            }
        }
    }
    $total = count($folders);
    
    foreach ($folders as $dir_path) {
        $current++;
        $cache_file = $dir_path . '/.folder_cache.json';
        $relative_path = str_replace($base_dir, '', $dir_path);
        
        echo "data: " . json_encode([
            'type' => 'progress',
            'current' => $current,
            'total' => $total,
            'created' => $created,
            'skipped' => $skipped,
            'folder' => $relative_path ?: '/'
        ]) . "\n\n";
        flush();
        
        // 빠른 생성: 캐시가 이미 있으면 스킵
        if (!$force && file_exists($cache_file)) {
            $skipped++;
            continue;
        }
        
        // 강제 재생성: 기존 파일 삭제
        if ($force && file_exists($cache_file)) {
            @unlink($cache_file);
        }
        
        // 캐시 생성
        $zip_count = 0;
        $video_count = 0;
        $files = @scandir($dir_path);
        $total_items = $files ? count($files) - 2 : 0;  // ✅ 파일 개수 (Windows mtime 버그 대응)
        if ($files !== false) {
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $filepath = $dir_path . '/' . $f;
                if (is_file($filepath)) {
                    if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $f)) {
                        $zip_count++;
                    }
                    if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $f)) {
                        $video_count++;
                    }
                }
            }
        }
        
        $cache_data = [
            'zip_count' => $zip_count,
            'video_count' => $video_count,
            'total_items' => $total_items  // ✅ 파일 개수 저장
        ];
        
        @file_put_contents($cache_file, json_encode($cache_data), LOCK_EX);
        $created++;
    }
    
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
}

// ✅ ZIP 이미지 목록 캐시 생성 함수 (스트리밍)
function generate_image_files_cache_streaming($base_dir, $force = false, $recursive = true) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    $current = 0;
    
    // ZIP 파일 목록 수집
    $zip_files = [];
    if ($recursive) {
        $rii = new RecursiveIteratorIterator(mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($rii as $file) {
            if ($file->isFile() && preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $file->getFilename())) {
                $zip_files[] = $file->getPathname();
            }
        }
    } else {
        $items = @scandir($base_dir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $filepath = $base_dir . '/' . $item;
                if (is_file($filepath) && preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $item)) {
                    $zip_files[] = $filepath;
                }
            }
        }
    }
    $total = count($zip_files);
    
    foreach ($zip_files as $zipfile) {
        $current++;
        $cache_file = $zipfile . '.image_files.json';
        $relative_path = str_replace($base_dir, '', $zipfile);
        
        echo "data: " . json_encode([
            'type' => 'progress',
            'current' => $current,
            'total' => $total,
            'created' => $created,
            'skipped' => $skipped,
            'file' => $relative_path
        ]) . "\n\n";
        flush();
        
        // 빠른 생성: 캐시가 이미 있으면 스킵
        if (!$force && file_exists($cache_file)) {
            $skipped++;
            continue;
        }
        
        // 강제 재생성: 기존 파일 삭제
        if ($force && file_exists($cache_file)) {
            @unlink($cache_file);
        }
        
        // ✅ [RAR/7Z 지원] ZIP=내장, RAR/7Z=archive_handler
        $_arc_ext = strtolower(pathinfo($zipfile, PATHINFO_EXTENSION));
        $_is_rar7z = in_array($_arc_ext, ['rar', 'cbr', '7z', 'cb7']);
        $_arc = null;
        $zip = new ZipArchive;
        $_opened = $_is_rar7z ? (($_arc = archive_open_handler($zipfile)) !== false) : ($zip->open($zipfile) === TRUE);
        if ($_opened) {
            $image_files = [];
            $has_video = false;
            
            if ($_is_rar7z) {
                foreach (archive_list_all($_arc) as $zname) {
                    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                        $image_files[] = $zname;
                    }
                    if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                        $has_video = true;
                    }
                }
            } else {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zname = mc_zip_name($zip, $i);
                if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                    $image_files[] = $zname;
                }
                if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                    $has_video = true;
                }
            }
            }
            
            if (!$_is_rar7z) { $zip->close(); }
            
            // 이미지가 있고 동영상이 없을 때만 생성
            if (count($image_files) > 0 && !$has_video) {
                natsort($image_files);
                $image_files = array_values($image_files);
                @file_put_contents($cache_file, json_encode($image_files, JSON_UNESCAPED_UNICODE), LOCK_EX);
                $created++;
            } else {
                $skipped++;
            }
        } else {
            $skipped++;
        }
    }
    
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
}

// ✅ ZIP 동영상 목록 캐시 생성 함수 (스트리밍)
function generate_video_files_cache_streaming($base_dir, $force = false, $recursive = true) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    $current = 0;
    
    // ZIP 파일 목록 수집
    $zip_files = [];
    if ($recursive) {
        $rii = new RecursiveIteratorIterator(mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($rii as $file) {
            if ($file->isFile() && preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $file->getFilename())) {
                $zip_files[] = $file->getPathname();
            }
        }
    } else {
        $items = @scandir($base_dir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $filepath = $base_dir . '/' . $item;
                if (is_file($filepath) && preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $item)) {
                    $zip_files[] = $filepath;
                }
            }
        }
    }
    $total = count($zip_files);
    
    foreach ($zip_files as $zipfile) {
        $current++;
        $cache_file = $zipfile . '.video_files.json';
        $relative_path = str_replace($base_dir, '', $zipfile);
        
        echo "data: " . json_encode([
            'type' => 'progress',
            'current' => $current,
            'total' => $total,
            'created' => $created,
            'skipped' => $skipped,
            'file' => $relative_path
        ]) . "\n\n";
        flush();
        
        // 빠른 생성: 캐시가 이미 있으면 스킵
        if (!$force && file_exists($cache_file)) {
            $skipped++;
            continue;
        }
        
        // 강제 재생성: 기존 파일 삭제
        if ($force && file_exists($cache_file)) {
            @unlink($cache_file);
        }
        
        // ✅ [RAR/7Z 지원] ZIP=내장, RAR/7Z=archive_handler
        $_arc_ext = strtolower(pathinfo($zipfile, PATHINFO_EXTENSION));
        $_is_rar7z = in_array($_arc_ext, ['rar', 'cbr', '7z', 'cb7']);
        $_arc = null;
        $zip = new ZipArchive;
        $_opened = $_is_rar7z ? (($_arc = archive_open_handler($zipfile)) !== false) : ($zip->open($zipfile) === TRUE);
        if ($_opened) {
            $video_files = [];
            
            if ($_is_rar7z) {
                foreach (archive_list_all($_arc) as $zname) {
                    if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                        $video_files[] = $zname;
                    }
                }
            } else {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zname = mc_zip_name($zip, $i);
                if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                    $video_files[] = $zname;
                }
            }
            }
            
            if (!$_is_rar7z) { $zip->close(); }
            
            // 동영상이 있을 때만 생성
            if (count($video_files) > 0) {
                natsort($video_files);
                $video_files = array_values($video_files);
                @file_put_contents($cache_file, json_encode($video_files, JSON_UNESCAPED_UNICODE), LOCK_EX);
                $created++;
            } else {
                $skipped++;
            }
        } else {
            $skipped++;
        }
    }
    
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
}

// ✅ ZIP 이미지 목록 캐시 생성 (백그라운드)
if (get_param('make_zip_image_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    $force = (get_param('force', 'string') === '1');
    $result = generate_all_zip_image_caches($base_dir, $force);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
    exit;
}

// ✅ 파일 목록 캐시 생성 함수
function generate_all_filelist_caches($base_dir, $force = false) {
    $changed = 0;
    $created = 0;
    $skipped = 0;
    $total = 0;
    $iterator = new RecursiveIteratorIterator(
        mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */,
        RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
    );
    
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            $total++;
            $dir_path = $item->getPathname();
            // ★ (2026-11-03 10:48) 목록 화면과 같은 판단·같은 함수로 만듦(위 스트리밍과 같음)
            $existing = null;
            if (!$force && mc_list_cache_fresh($dir_path, false, $existing)) { $skipped++; continue; }
            if (@scandir($dir_path) === false) continue;
            mc_build_list_cache($dir_path, $existing);
            if (!empty($GLOBALS['mc_list_cache_written'])) $created++; else $skipped++;
            if (!empty($GLOBALS['mc_list_cache_changed'])) $changed++; // ★ (2026-11-04 00:26) 이름이 바뀐 폴더 수(배경 갱신 판단용)
        }
    }
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total, 'changed' => $changed];
}


// 함수 선언
function generate_all_zip_image_caches($base_dir, $force = false) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    $rii = new RecursiveIteratorIterator(mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $filename = $file->getFilename();
        if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $filename)) {
            $total++;
            $zipfile = $file->getPathname();
            $cache_file = $zipfile . '.image_files.json';
            if ($force || !file_exists($cache_file)) {
                $zip = new ZipArchive;
                if ($zip->open($zipfile) === TRUE) {
                    $image_files = [];
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $zname = mc_zip_name($zip, $i);
                        if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                            $image_files[] = $zname;
                        }
                    }
                    natsort($image_files);
                    $image_files = array_values($image_files);
                    @file_put_contents($cache_file, json_encode($image_files, JSON_UNESCAPED_UNICODE), LOCK_EX);
                    $zip->close();
                    unset($zip);
                    gc_collect_cycles();
                    $created++;
                }
            } else {
                $skipped++;
            }
        }
    }
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
}

// ============================================================
// ✅ RAR/7Z 어댑터 헬퍼 (검증된 ZIP 흐름을 그대로 재사용하기 위함)
// ------------------------------------------------------------
// ZIP은 PHP 내장 ZipArchive를 쓰고, RAR/7Z는 아래 3개 헬퍼로
// "목록 가져오기 / 개별 추출"만 대체한다. 썸네일 생성·캐시 저장·
// 표시 등 그 이후 로직은 ZIP과 100% 동일하게 흐른다.
// ============================================================

// 압축 핸들러 열기 (RAR/7Z 전용). 성공 시 ArchiveHandler, 실패 시 false
function archive_open_handler($archive_path) {
    if (!class_exists('ArchiveHandler')) return false;
    try {
        return new ArchiveHandler($archive_path);
    } catch (Exception $e) {
        error_log("[archive_open_handler] " . $e->getMessage());
        return false;
    }
}

// 압축 내 전체 파일 목록 (배열). ZipArchive의 getNameIndex 루프에 대응
function archive_list_all($handler) {
    if (!$handler) return array();
    try {
        return $handler->listFiles(); // 필터 없이 전체
    } catch (Exception $e) {
        return array();
    }
}

// 압축 내 특정 파일 1개의 바이트 추출. ZipArchive의 getFromName에 대응
function archive_extract_one($handler, $entry_name) {
    if (!$handler) return false;
    try {
        $data = $handler->extractFile($entry_name);
        return ($data !== false && $data !== null && strlen($data) > 0) ? $data : false;
    } catch (Exception $e) {
        return false;
    }
}

// ✅ 파일명.json + [cover].jpg 일괄 생성 함수
// [cover].jpg는 폴더 내 natsort ASC 순서로 첫 번째 ZIP에서 생성
function generate_all_zip_metadata($base_dir, $force = false) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    
    // 1단계: 폴더별로 ZIP 파일 그룹화
    $folders = [];
    $rii = new RecursiveIteratorIterator(mc_rdi($base_dir) /* ★ 11-04 @eaDir·tmp·숨김 폴더 제외 */, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $filename = $file->getFilename();
        if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $filename)) {
            $dir_path = dirname($file->getPathname());
            if (!isset($folders[$dir_path])) {
                $folders[$dir_path] = [];
            }
            $folders[$dir_path][] = $file->getPathname();
        }
    }
    
    // 2단계: 각 폴더별로 처리
    foreach ($folders as $dir_path => $zip_files) {
        // natsort로 정렬 (이름 오름차순)
        usort($zip_files, function($a, $b) {
            return strnatcasecmp(basename($a), basename($b));
        });
        
        $cover_file = $dir_path . '/[cover].jpg';
        $cover_created = false; // 이 폴더에서 커버가 생성되었는지
        
        // force 모드일 때 기존 커버 삭제하여 첫 번째 ZIP에서 새로 생성되도록 함
        if ($force && file_exists($cover_file)) {
            @unlink($cover_file);
        }
        
        foreach ($zip_files as $zipfile) {
            $total++;
            $json_file = get_cache_json_path($zipfile);
            
            // force가 아니고 이미 json이 있으면 스킵
            if (!$force && file_exists($json_file)) {
                $skipped++;
                continue;
            }
            
            $zip = new ZipArchive;
            if ($zip->open($zipfile) === TRUE) {
                $image_files = [];
                $video_files = [];
                
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zname = mc_zip_name($zip, $i);
                    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                        $image_files[] = $zname;
                    }
                    if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                        $video_files[] = $zname;
                    }
                }
                
                // 동영상만 있는 ZIP
                if (count($image_files) == 0 && count($video_files) > 0) {
                    $cache_data = [
                        'totalpage' => count($video_files),
                        'page_order' => '0',
                        'viewer' => 'video',
                        'thumbnail' => '',
                        'is_video_archive' => true
                    ];
                    @file_put_contents($json_file, json_encode($cache_data, JSON_UNESCAPED_UNICODE), LOCK_EX);
                    $zip->close();
                    unset($zip);
                    gc_collect_cycles();
                    $created++;
                    continue;
                }
                
                // 이미지가 있는 ZIP - 썸네일 생성
                if (count($image_files) > 0) {
                    natsort($image_files);
                    $image_files = array_values($image_files);
                    $thumbnail_filename = $image_files[0];
                    
                    $img_data = mc_zip_from_name($zip, $thumbnail_filename);
                    if ($img_data !== false) {
                        $img = mc_gd_from_string($img_data); // ★ (2026-11-02 09:56) 너무 큰 그림은 건너뜀(메모리 넘침)
                        if ($img) {
                            $w = imagesx($img);
                            $h = imagesy($img);
                            $new_h = 400;
                            $new_w = max(1, intval($w * ($new_h / $h))); // ★ (2026-11-02 14:34) 아주 가는 그림이면 0 이 되어 ValueError 로 작업이 멈추던 것
                            
                            $cropimage = imagecreatetruecolor($new_w, $new_h);
                            imagecopyresampled($cropimage, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
                            imagedestroy($img);
                            
                            ob_start();
                            imagejpeg($cropimage, null, 75);
                            imagedestroy($cropimage);
                            $thumbnail_data = ob_get_contents();
                            ob_end_clean();
                            
                            $cache_data = [
                                'totalpage' => count($image_files),
                                'page_order' => '0',
                                'viewer' => 'toon',
                                'thumbnail' => base64_encode($thumbnail_data)
                            ];
                            @file_put_contents($json_file, json_encode($cache_data, JSON_UNESCAPED_UNICODE), LOCK_EX);
                            
                            // [cover].jpg 생성 - 폴더 내 첫 번째 유효한 ZIP에서만 생성
                            if (!$cover_created && !file_exists($cover_file)) {
                                @file_put_contents($cover_file, $thumbnail_data, LOCK_EX);
                                $cover_created = true;
                            }
                            $created++;
                        }
                    }
                }
                
                $zip->close();
                unset($zip);
                gc_collect_cycles();
            }
        }
    }
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
}


// 번역 사전 캐싱을 위한 전역 변수
// ★ (2026-11-06 08:10) 검색 번역 사전 함수(loadSearchTranslations·reverseTranslateSearch·translateSearch)는 search_translate.php 로 옮김(맨 위에서 require_once)

// 검색어 포함된 전체 폴더 검색 (메인페이지 + 하위폴더 모두)
if (!empty($q) && trim($q) !== '') {
    
    // 번역은 이미 249-265 라인에서 처리됨
    
    // ★ (2026-11-04 20:51) 펜닐 요청: 맨 위(사이트 머리 위)에 나오던 안내·폴더 목록은 없앰 — 같은 번역 안내는 검색창 아래에 있고, 폴더 목록은 검색창 아래 '다른 폴더' 목록(mc_search_elsewhere_html)으로
    //   (show_search_results 는 그 목록 자료를 만들려고 그대로 부르되 출력은 버림)
    // 검색 결과 자료(다른 폴더 목록용) — 화면에는 내지 않음
    ob_start();
    try { show_search_results($q, $translated_q, $reverse_translated_q); } finally { ob_end_clean(); }
    $GLOBALS['mc_sf_q'] = $q; // ★ fix41 찾은 폴더 이동 막대·목록 창이 쓰는 검색어
} elseif ($mc_sq !== '') {
    // ★ (2026-11-07 fix41) 폴더 이름이 맞아 들어간 폴더(q 없음, sq 만) — 목록은 거르지 않고, 찾은 폴더 이동 막대만 이어 가려고 같은 자료를 만듦(번역도 q 와 같게)
    $mc_sq = normalize_korean($mc_sq);
    $__sq_tr = []; $__sq_rtr = [];
    if (preg_match('/[ㄱ-ㅎㅏ-ㅣ가-힣]/u', $mc_sq)) $__sq_tr = translateSearch($mc_sq);
    elseif (preg_match('/^[a-zA-Z\s]+$/', $mc_sq)) $__sq_rtr = reverseTranslateSearch($mc_sq);
    ob_start();
    try { show_search_results($mc_sq, $__sq_tr, $__sq_rtr); } finally { ob_end_clean(); }
    $GLOBALS['mc_sf_q'] = $mc_sq;
}

// 검색어 하이라이트 함수 (배열 지원 추가)
function highlight_search($text, $search_terms) {
    // 문자열이면 배열로 변환
    if (is_string($search_terms)) {
        $search_terms = [$search_terms];
    }
    
    // NFD → NFC 정규화 적용
    $text = normalize_korean($text);
    
    if (empty($search_terms)) {
        return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
    }
    
    // ★ (2026-11-02 08:47) 원문에서 모든 검색어를 한 번에 찾아 조각마다 이스케이프해 이어 붙임 — 예전엔 먼저 이스케이프한 뒤 검색어마다 바꿔서
    //   '&amp;' 같은 엔티티나 앞에서 넣은 <mark style=…> 안까지 맞춰 이름이 깨졌음('Tom & Jerry' 를 a 로 찾으면 &<mark>a</mark>mp;)
    $sorted_terms = [];
    foreach ($search_terms as $term) { $term = normalize_korean((string)$term); if ($term !== '') $sorted_terms[] = $term; }
    if (!$sorted_terms) return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
    // 긴 검색어부터(같은 위치면 긴 것이 먼저 맞게)
    usort($sorted_terms, function($a, $b) { return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8'); });
    $pattern = '/(' . implode('|', array_map(function ($t) { return preg_quote($t, '/'); }, array_unique($sorted_terms))) . ')/ui';
    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
    $highlighted = '';
    foreach ($parts as $k => $part) {
        $e = htmlspecialchars($part, ENT_COMPAT, 'UTF-8');
        $highlighted .= ($k % 2 === 1) ? '<mark style="background-color: yellow; color: #000 !important; font-weight: bold;">' . $e . '</mark>' : $e; // 홀수 조각 = 맞은 검색어 ★ (2026-11-03 09:09) 글자색 검정 — 다크모드에선 링크 글자색(밝은 회색)이 노랑 위에 와 거의 안 보였음
    }
    return $highlighted;
}


/**
 * 번역어 조합 생성 (표시용)
 * "섹스 프렌드" → "sex friend, cex friend"
 */
function generateTranslationCombinations($original_query, $translated_q) {
    if (empty($translated_q)) {
        return '';
    }
    
    // 원본에 띄어쓰기가 없으면 그냥 합치기
    if (strpos($original_query, ' ') === false) {
        if (is_array($translated_q)) {
            return implode(', ', array_map('strtolower', $translated_q));
        }
        return strtolower($translated_q);
    }
    
    // 띄어쓰기가 있는 경우: 각 단어별로 번역 분리
    $words = array_filter(explode(' ', $original_query));
    $manual_translations = loadSearchTranslations();
    
    $word_translations = [];
    foreach ($words as $word) {
        $w_lower = mb_strtolower($word, 'UTF-8');
        if (isset($manual_translations[$w_lower])) {
            $word_translations[] = array_map('strtolower', $manual_translations[$w_lower]);
        } else {
            // 번역 없으면 원본 사용
            $word_translations[] = [$w_lower];
        }
    }
    
    // 데카르트 곱 생성
    $combinations = cartesianProduct($word_translations);
    
    // 각 조합을 공백으로 연결
    $result = [];
    foreach ($combinations as $combo) {
        $result[] = implode(' ', $combo);
    }
    
    return implode(', ', $result);
}

/**
 * 데카르트 곱 계산
 */
function cartesianProduct($arrays) {
    $result = [[]];
    
    foreach ($arrays as $key => $values) {
        $append = [];
        // ★ (2026-11-02 09:55) 조합이 64개를 넘으면 그 뒤 낱말은 첫 번역만 — 번역이 여러 개인 낱말을 수십 번 이어 쓴 검색어 하나로 메모리가 바닥나(2^N) 500 이던 것
        $values = array_values((array)$values);
        if (count($result) * count($values) > 64) $values = array_slice($values, 0, 1);
        foreach ($result as $product) {
            foreach ($values as $item) {
                $product_copy = $product;
                $product_copy[] = $item;
                $append[] = $product_copy;
            }
        }
        $result = $append;
    }
    
    return $result;
}


/**
 * 검색용 조합 생성 (검색어 배열용)
 * "게이밍 하렘" + ["gaming", "harem"] → ["gaming harem"]
 */
function generateSearchCombinations($original_query, $translated_array) {
    if (empty($translated_array)) {
        return [];
    }
    
    // 띄어쓰기가 없으면 빈 배열 반환 (이 함수는 복합어용)
    if (strpos($original_query, ' ') === false) {
        return [];
    }
    
    // 각 단어별로 번역 분리
    $words = array_filter(explode(' ', $original_query));
    $manual_translations = loadSearchTranslations();
    
    $word_translations = [];
    foreach ($words as $word) {
        $w_lower = mb_strtolower($word, 'UTF-8');
        if (isset($manual_translations[$w_lower])) {
            $word_translations[] = array_map('strtolower', $manual_translations[$w_lower]);
        } else {
            // 번역 없으면 원본 사용
            $word_translations[] = [$w_lower];
        }
    }
    
    // 데카르트 곱 생성
    $combinations = cartesianProduct($word_translations);
    
    // 각 조합을 공백으로 연결
    $result = [];
    foreach ($combinations as $combo) {
        $result[] = implode(' ', $combo);
    }
    
    return $result;
}

/** ★ (2026-11-07 fix41) 펜닐 결정(검색 시안 추천 ①②③④): 검색 결과 '다른 폴더' 안내를 새로 — 예전 칩 줄(좌우 화살표·스크롤 막대)이 거슬린다는 요청
 *   ① 이 폴더에도 결과가 있을 때: 검색창 아래 한 줄만 — "'친구' 이 폴더에서 2개 [다른 폴더 153곳 ▾]" (mc_search_elsewhere_html($q, 'chips', 개수))
 *   ② [다른 폴더 N곳]·이동 막대의 [목록]: 찾은 폴더 목록 창 — PC 는 단추 아래 펼침, 폰(좁은 화면)은 아래에서 올라오는 창. 이름 거르기 · 콘텐츠 폴더별 묶음 · 60곳씩
 *   ③ 이 폴더에 없을 때: 분명한 안내 + 다른 폴더 짧은 목록(5곳) + [N곳 더 보기] / 어디에도 없으면 서재 전체에서 못 찾았다는 안내 + 고쳐 볼 방법 + 검색어 지우기 (mc_search_none_html)
 *   ④ 화면 아래 '찾은 폴더 이동 막대': ‹ 이전 · 3 / 153 · 다음 › | 목록 | ✕ — 위로 스크롤하지 않고 찾은 폴더 사이를 오감(mc_search_footer_html, 본문 끝에서 한 번)
 *   자료는 예전과 같은 $GLOBALS['mc_search_dirs'](show_search_results — 권한 없는 폴더는 이미 빠짐), 순서도 예전과 같음(콘텐츠 폴더 순 → 경로 자연 정렬)
 *   폴더 이름이 맞은 곳은 q 없이 열되(그 안 파일로 거르지 않게) sq 로 검색어를 넘겨 이동 막대가 이어짐
 *   예전: 칩 줄 + 좌우 화살표 + [모두 보기] 판(2026-11-04 ~ 11-06) / 못 찾았을 때 큰 목록 30곳 + 더 보기 300곳 */
function mc_josa($w, $a, $b) {
    $s = normalize_korean(trim((string)$w));
    if ($s === '') return $a . '(' . $b . ')';
    $ch = mb_substr($s, -1, 1, 'UTF-8');
    $o = function_exists('mb_ord') ? mb_ord($ch, 'UTF-8') : 0;
    if ($o >= 0xAC00 && $o <= 0xD7A3) return (($o - 0xAC00) % 28) ? $a : $b;
    if (preg_match('/^[0-9]$/', $ch)) return in_array($ch, ['0', '1', '3', '6', '7', '8'], true) ? $a : $b; // 영·일·삼·육·칠·팔
    return $a . '(' . $b . ')';
}
/** 찾은 폴더 자료(한 번만 만듦): rows(순서대로) · cur(지금 폴더 번호, 없으면 -1) · others(지금 폴더 뺀 수) · q */
function mc_sf_data() {
    static $D = null;
    if ($D !== null) return $D;
    global $getdir, $current_bidx, $base_dirs;
    $q = (string)($GLOBALS['mc_sf_q'] ?? '');
    $D = ['rows' => [], 'cur' => -1, 'others' => 0, 'q' => $q];
    if ($q === '') return $D;
    $cur = trim((string)$getdir, '/');
    $rows = [];
    foreach (($GLOBALS['mc_search_dirs'] ?? []) as $k => $mi) {
        $kp = explode('|', (string)$k, 2); $b = (int)$kp[0]; $d = trim((string)($kp[1] ?? ''), '/');
        if ($d === '.') $d = '';
        $rows[] = ['b' => $b, 'd' => $d, 'type' => $mi['match_type'] ?? 'file', 'cnt' => (int)($mi['cnt'] ?? 0), 'cur' => ($b === (int)$current_bidx && ($d === $cur || normalize_korean($d) === normalize_korean($cur)))]; // ★ (2026-11-09 14:17) NFD 이름 폴더를 열었을 때도 '이 폴더'로
    }
    usort($rows, function ($x, $y) { return ($x['b'] <=> $y['b']) ?: strnatcasecmp(normalize_korean($x['d']), normalize_korean($y['d'])); });
    foreach ($rows as $i => &$r) {
        $enc = encode_url('/' . $r['d']);
        $r['url'] = ($r['type'] === 'dir') ? "index.php?dir={$enc}&bidx=" . $r['b'] . '&sq=' . urlencode($q) : "index.php?dir={$enc}&q=" . urlencode($q) . '&bidx=' . $r['b'] . '#search-section';
        $nm = normalize_korean($r['d']);
        $r['name'] = ($nm === '') ? (isset($base_dirs[$r['b']]) ? basename($base_dirs[$r['b']]) : __('js_root')) : basename($nm);
        $r['path'] = ($nm === '' || strpos($nm, '/') === false) ? '' : str_replace('/', ' › ', dirname($nm));
        $r['group'] = isset($base_dirs[$r['b']]) ? basename($base_dirs[$r['b']]) : '';
        if ($r['cur']) $D['cur'] = $i; else $D['others']++;
    }
    unset($r);
    $D['rows'] = $rows;
    return $D;
}
/** 지금 위치 이름(안내 글에 씀) — 맨 위면 콘텐츠 폴더 이름 */
function mc_sf_where() {
    global $getdir, $current_bidx, $base_dirs;
    $d = normalize_korean(trim((string)$getdir, '/'));
    if ($d !== '' && $d !== '.') return basename($d);
    return isset($base_dirs[$current_bidx]) ? basename($base_dirs[$current_bidx]) : __('js_root');
}
function mc_sf_ico($n, $sz = 18) {
    $P = [
        'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>', 'prev' => '<path d="m15 18-6-6 6-6"/>', 'next' => '<path d="m9 18 6-6-6-6"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'searchx' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/><path d="m8.5 8.5 5 5"/><path d="m13.5 8.5-5 5"/>',
    ];
    return '<svg class="mc-sf-i" width="' . (int)$sz . '" height="' . (int)$sz . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($P[$n] ?? '') . '</svg>';
}
/** 폴더 한 줄(③ 짧은 목록) */
function mc_sf_row_html($r) {
    $cnt = $r['cnt'] > 0 ? '<span class="mc-sf-c">' . h(sprintf(__('ui_search_else_cnt'), $r['cnt'])) . '</span>' : '';
    $sub = [];
    if (count($GLOBALS['base_dirs'] ?? []) > 1 && $r['group'] !== '') $sub[] = $r['group'];
    if ($r['path'] !== '') $sub[] = $r['path'];
    $sub = implode(' › ', $sub);
    if ($r['type'] === 'dir') $sub = ($sub !== '' ? $sub . ' · ' : '') . __('ui_search_else_dirname');
    return '<li><a class="mc-sf-row" href="' . h($r['url']) . '" data-sfk="' . h($r['b'] . '|' . $r['d']) . '">' . mc_sf_ico('folder', 18)
        . '<span class="mc-sf-rt"><span class="mc-sf-rn">' . h($r['name']) . '</span>' . ($sub !== '' ? '<span class="mc-sf-rp">' . h($sub) . '</span>' : '') . '</span>'
        . $cnt . mc_sf_ico('next', 16) . '</a></li>';
}
/** ① 이 폴더에도 결과가 있을 때 — 검색창 아래 한 줄 (다른 폴더가 없으면 아무것도 안 냄) */
function mc_search_elsewhere_html($q, $mode = 'chips', $here_n = -1) {
    $D = mc_sf_data();
    if (!$D['others'] || $mode !== 'chips') return '';
    $hc = ($here_n >= 0) ? $here_n : (($D['cur'] >= 0) ? $D['rows'][$D['cur']]['cnt'] : 0);
    $GLOBALS['mc_sf_here'] = max(1, (int)$hc); // 이동 막대: 지금 폴더가 찾은 목록에 없어도(맞는 것이 하위 폴더 이름뿐) '이 폴더엔 없음'이라 하지 않게
    // ★ (2026-11-07 fix42) 펜닐 결정: 추천 ④(아래 이동 막대)만 — 검색창 아래 한 줄 안내([다른 폴더 N곳])는 안 냄(다른 폴더는 막대의 [목록]으로)
    return '';
    $here = $hc > 0 ? sprintf(__('sf_here_n'), $hc) : __('sf_here_found');
    // .mc-chips-wrap: 붙어 다니는 검색 막대(mc_search_sticky_js)가 이 줄도 함께 본뜸
    return mc_sf_css_once() . '<div class="mc-chips-wrap mc-sf-sum"><span class="mc-sf-sumt"><b>' . h("'" . $q . "'") . '</b> ' . h($here) . '</span>'
        . '<button type="button" class="mc-sf-open" aria-haspopup="dialog" aria-expanded="false" onclick="mcSfOpen(this)">' . mc_sf_ico('folder', 15) . '<span>' . h(sprintf(__('sf_others_btn'), $D['others'])) . '</span>' . mc_sf_ico('down', 14) . '</button></div>';
}
/** ③ 이 폴더에 없을 때 — 안내 + (다른 폴더 짧은 목록 | 어디에도 없을 때 고쳐 볼 방법) */
function mc_search_none_html($q) {
    global $getdir, $current_bidx;
    $D = mc_sf_data();
    $where = mc_sf_where();
    if ($D['others'] > 0) {
        $h = mc_sf_css_once() . '<div class="mc-sf-none"><div class="mc-sf-nh"><span class="mc-sf-nico">' . mc_sf_ico('searchx', 22) . '</span><div class="mc-sf-ntx">'
            . '<div class="mc-sf-nt">' . h(sprintf(__('sf_none_title'), $q, mc_josa($q, '이', '가'))) . '</div>'
            . '<div class="mc-sf-nd">' . h(sprintf(__('sf_none_desc'), $where)) . '</div></div></div>';
        $h .= '<div class="mc-sf-oh">' . h(sprintf(__('sf_none_others'), $D['others'])) . ' <span>· ' . __h('sf_none_others_hint') . '</span></div><ul class="mc-sf-list">';
        $k = 0; $show = 5;
        foreach ($D['rows'] as $r) { if ($r['cur']) continue; if ($k++ >= $show) break; $h .= mc_sf_row_html($r); }
        $h .= '</ul>';
        if ($D['others'] > $show) $h .= '<button type="button" class="mc-sf-more" aria-haspopup="dialog" onclick="mcSfOpen(this)">' . mc_sf_ico('grid', 15) . '<span>' . h(sprintf(__('sf_more'), $D['others'] - $show)) . '</span></button>';
        return $h . '</div>';
    }
    $clear = 'index.php?' . ((trim((string)$getdir, '/') !== '') ? 'dir=' . encode_url('/' . trim((string)$getdir, '/')) . '&' : '') . 'bidx=' . (int)$current_bidx;
    return mc_sf_css_once() . '<div class="mc-sf-none mc-sf-nowhere"><span class="mc-sf-nico mc-sf-big">' . mc_sf_ico('searchx', 28) . '</span>'
        . '<div class="mc-sf-nt">' . h(sprintf(__('sf_nowhere_title'), $q, mc_josa($q, '을', '를'))) . '</div>'
        . '<div class="mc-sf-nd">' . __h('sf_nowhere_desc') . '</div>'
        . '<ul class="mc-sf-tips"><li>' . __h('sf_tip1') . '</li><li>' . __h('sf_tip2') . '</li><li>' . __h('sf_tip3') . '</li></ul>'
        . '<a class="mc-sf-clear" href="' . h($clear) . '">' . __h('sf_clear') . '</a></div>';
}
/** ②④ 목록 창 + 아래 이동 막대 — 본문 끝에서 한 번(검색어가 있고 다른 폴더가 있을 때만) */
function mc_search_footer_html() {
    global $getdir, $current_bidx;
    $D = mc_sf_data();
    if (!$D['others']) return '';
    $q = $D['q']; $rows = $D['rows']; $n = count($rows); $ci = $D['cur'];
    // 목록 창 자료(이름·경로는 화면에서 textContent 로만 넣음)
    $data = []; $groups = [];
    foreach (array_slice($rows, 0, 3000) as $r) {
        $data[] = ['u' => $r['cur'] ? '' : $r['url'], 'n' => $r['name'], 'p' => $r['path'], 'c' => $r['cnt'], 'k' => $r['cur'] ? 1 : 0, 'g' => $r['b'], 'i' => $r['b'] . '|' . $r['d']]; // ★ fix47 i: 이번 검색에서 본 폴더 기억용 키
        $groups[$r['b']] = $r['group'];
    }
    $txt = ['filter' => __('ui_search_chips_filter'), 'more' => __('ui_search_chips_more'), 'none' => __('ui_search_chips_none'), 'here' => __('ui_search_chips_here'),
            'cnt' => __('ui_search_chips_gcount'), 'cap' => $n > 3000 ? sprintf(__('ui_search_else_more'), $n - 3000) : '', 'multi' => count($groups) > 1 ? 1 : 0];
    $h = mc_sf_css_once() . '<div class="mc-sfp-back" id="mcSfBack" hidden></div>'
        . '<div class="mc-sfp" id="mcSfPanel" role="dialog" aria-modal="false" aria-labelledby="mcSfPt" hidden>'
        . '<div class="mc-sfp-grip" aria-hidden="true"></div>'
        . '<div class="mc-sfp-h"><span class="mc-sfp-t" id="mcSfPt">' . h(sprintf(__('sf_panel_title'), $q, $n)) . '</span>'
        . '<button type="button" class="mc-sfp-x" aria-label="' . __h('sf_close') . '" onclick="mcSfClose()">' . mc_sf_ico('x', 18) . '</button></div>'
        . '<div class="mc-sfp-f"><input type="search" class="mc-sfp-q" placeholder="' . h($txt['filter']) . '" aria-label="' . h($txt['filter']) . '" autocomplete="off"></div>'
        . '<div class="mc-sfp-b"></div><button type="button" class="mc-sfp-more" hidden></button><div class="mc-sfp-cap" hidden></div>'
        . '<script type="application/json" class="mc-sfp-data">' . json_encode(['rows' => $data, 'groups' => (object)$groups, 'txt' => $txt, 'q' => (string)$q], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . '</script></div>';
    // 이동 막대
    $btn = function ($url, $ico, $label, $aria, $cls = '') {
        if ($url === null) return '<span class="mc-sfb-b mc-off ' . $cls . '" aria-disabled="true" title="' . h($aria) . '">' . $ico . '<span class="mc-sfb-l">' . h($label) . '</span></span>';
        return '<a class="mc-sfb-b ' . $cls . '" href="' . h($url) . '" title="' . h($aria) . '" aria-label="' . h($aria) . '">' . $ico . '<span class="mc-sfb-l">' . h($label) . '</span></a>';
    };
    if ($ci >= 0) {
        $top = sprintf(__('sf_bar_found'), $q); $big = ($ci + 1) . ' / ' . $n;
        if ($ci === $n - 1) { $top = __('sf_bar_last'); }
        $prev = $btn($ci > 0 ? $rows[$ci - 1]['url'] : null, mc_sf_ico('prev', 18), __('sf_prev'), __('sf_prev_aria'), 'mc-sfb-p'); // ★ (2026-11-09 10:41) 'mc-sfb-p' = ‹이전 알아보는 이름(아래 클릭 거르기) — 모양 규칙 없음
        $next = ($ci < $n - 1) ? $btn($rows[$ci + 1]['url'], mc_sf_ico('next', 18), __('sf_next'), __('sf_next_aria'), 'mc-sfb-r')
                               : $btn($rows[0]['url'], mc_sf_ico('next', 18), __('sf_restart'), __('sf_restart_aria'), 'mc-sfb-r');
    } else {
        $hn = (int)($GLOBALS['mc_sf_here'] ?? 0); // 이 폴더에도 결과가 있음(맞는 하위 폴더 등) — 목록에는 없는 자리
        $top = $hn > 0 ? sprintf(__('sf_bar_here_n'), $q, $hn) : __('sf_bar_none'); $big = sprintf(__('sf_bar_others'), $D['others']);
        $first = null; foreach ($rows as $r) { if (!$r['cur']) { $first = $r['url']; break; } }
        $prev = $btn(null, mc_sf_ico('prev', 18), __('sf_prev'), __('sf_prev_aria'), 'mc-sfb-p');
        $next = $btn($first, mc_sf_ico('next', 18), __('sf_first'), __('sf_first_aria'), 'mc-sfb-r');
    }
    $end = 'index.php?' . ((trim((string)$getdir, '/') !== '') ? 'dir=' . encode_url('/' . trim((string)$getdir, '/')) . '&' : '') . 'bidx=' . (int)$current_bidx;
    $h .= '<nav class="mc-sfbar" id="mcSfBar" aria-label="' . h(sprintf(__('sf_bar_found'), $q)) . '">' . $prev
        . '<span class="mc-sfb-m"><span class="mc-sfb-top">' . h($top) . '</span><span class="mc-sfb-big">' . h($big) . '</span></span>' . $next
        . '<span class="mc-sfb-sep" aria-hidden="true"></span>'
        . '<button type="button" class="mc-sfb-b mc-sfb-list" aria-haspopup="dialog" aria-expanded="false" title="' . __h('sf_list_aria') . '" aria-label="' . __h('sf_list_aria') . '" onclick="mcSfOpen(this)">' . mc_sf_ico('grid', 17) . '<span class="mc-sfb-l">' . __h('sf_list') . '</span></button>'
        . '<span class="mc-sfb-sep" aria-hidden="true"></span>'
        . '<a class="mc-sfb-b mc-sfb-end" href="' . h($end) . '" title="' . __h('sf_end') . '" aria-label="' . __h('sf_end') . '">' . mc_sf_ico('x', 16) . '</a></nav>';
    $h .= '<script>' . mc_sf_js() . '</script>';
    return $h;
}
function mc_sf_js() {
    return <<<'JS'
(function(){
if(window.mcSfOpen)return;
document.body.classList.add('mc-sf-on');
var P=document.getElementById('mcSfPanel'),B=document.getElementById('mcSfBack'),opener=null,inited=false;
function narrow(){return window.matchMedia&&matchMedia('(max-width: 576px), (max-height: 480px) and (pointer: coarse)').matches;} /* 폰 세로·폰 가로 = 아래에서 올라오는 창 */
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e;}
/* ★ fix47·fix48 '이번 검색에서 본 폴더' ✓ — 사이트 전체 방문 기록(visited_…, 지워지지 않음) 대신 '열린 검색' 하나만 기억(fix51: 탭마다 따로 sessionStorage mc_sf_seen = {q, s:{폴더 키:1}, closed})
   유지: 닫지 않는 동안(목록 이동·이전/다음·정렬·쪽 넘기기·새로고침·뒤로 가기·같은 말 다시 검색)
   끝: 아래 막대 ✕ · '검색어 지우기' · 검색창을 비우고 검색 → ✓ 지움 + 닫힘 표시(뒤로 가기로 돌아와도 막대·목록 창 안 나옴) · 다른 말 검색 → 새로 시작 */
var SEEN={},SQ=null,ENDED=false,SKEY='mc_sf_seen';
function stGet(){try{var o=JSON.parse(sessionStorage.getItem(SKEY)||'null');return (o&&typeof o==='object'&&typeof o.q==='string'&&o.s&&typeof o.s==='object'&&!Array.isArray(o.s))?o:null;}catch(e){return null;}}
function stPut(o){try{sessionStorage.setItem(SKEY,JSON.stringify(o));}catch(e){}}
try{localStorage.removeItem(SKEY);}catch(e){} /* ★ fix51 ✓ 기록은 탭마다 따로(sessionStorage — 탭을 닫으면 사라짐, 새로고침·뒤로 가기는 남음). fix47~50 에 브라우저 전체(localStorage)에 남긴 것은 지움 */
function decide(nt){ENDED=false;var st=stGet();
 if(st&&st.q===SQ&&st.closed){if(nt==='back_forward'||nt==='reload'){ENDED=true;SEEN={};return;}st=null;} /* 닫은 검색: 뒤로 가기·그 화면 새로고침(fix49)이면 막대 없이, 링크로 다시 들어오면 새로 */
 var fr=false;try{fr=sessionStorage.getItem('mc_sf_new')==='1';sessionStorage.removeItem('mc_sf_new');}catch(e){}
 if(!st||st.q!==SQ||(fr&&nt==='navigate'))st={q:SQ,s:{}}; /* fix49 막대가 없는 화면(카드로 들어간 폴더 등)에서 검색창으로 검색 = 새 검색 */
 delete st.closed;(D0rows||[]).forEach(function(r){if(r.k&&r.i)st.s[r.i]=1;});st.t=Date.now();stPut(st);SEEN=st.s;}
var D0rows=null;
(function(){if(!P)return;var D0;try{D0=JSON.parse(P.querySelector('.mc-sfp-data').textContent);}catch(e){return;}SQ=String(D0.q||'');D0rows=D0.rows||[];
 var nt='navigate';try{var ne=performance.getEntriesByType('navigation')[0];if(ne&&ne.type)nt=ne.type;}catch(e){}decide(nt);})();
window.mcSfEnd=function(){if(SQ===null)return;stPut({q:SQ,s:{},closed:1,t:Date.now()});SEEN={};};
function leave(){if(window.__mcSfSkip)return true; /* fix57 head 가 닫은 검색을 건너뛰는 중 */var e=document.querySelector('#mcSfBar .mc-sfb-end');if(e&&e.href){document.documentElement.style.visibility='hidden';location.replace(e.href);return true;}return false;} /* fix50 닫은 검색 = 검색 전 화면(✕ 와 같은 주소) */
function endedView(){if(ENDED&&leave())return;document.documentElement.style.visibility='';var on=ENDED;document.body.classList.toggle('mc-sf-ended',on);document.body.classList.toggle('mc-sf-on',!on);if(on&&window.mcSfClose)try{mcSfClose();}catch(e){}if(typeof lift==='function')lift();}
function seenMark(){document.querySelectorAll('a.mc-sf-row[data-sfk]').forEach(function(a){a.classList.toggle('mc-seen',!!SEEN[a.getAttribute('data-sfk')]);});}
seenMark();document.addEventListener('DOMContentLoaded',seenMark);
document.addEventListener('click',function(e){var a=e.target&&e.target.closest&&e.target.closest('.mc-sfb-end,.mc-sf-clear');if(a)mcSfEnd();},true);
/* ★ (2026-11-09 10:41) ‹이전 은 a 에 붙은 이름이 없어(mc-sfb-l 은 안쪽 글자) 맨 위 맞추기가 안 됐음 → a 에 mc-sfb-p 를 붙이고 그걸로 고름
 * ★ fix59 (펜닐 요청) 아래 막대·찾은 폴더 목록으로 폴더를 옮기면 화면 맨 위가 그 폴더의 목록(카드)이 되게 — 전엔 페이지 맨 위(로고·탭·폴더 배너·정렬·검색칸)에 머물러
   폰 가로에선 카드가 거의 안 보였고, 검색어 주소(#search-section)는 검색칸을 화면 가운데로 옮겨 위치가 들쭉날쭉했음
   · 막대 ‹이전·다음›·목록 줄·'다른 곳에서 찾음' 줄을 눌러 온 때만(검색창 검색·뒤로 가기·새로고침·뷰어에서 복귀는 예전대로)
   · 목록 위치 복원(scrollToAnchor)과 같은 방식: 그림이 늦게 그려져 밀리면 다시 맞추고, 사용자가 스크롤·터치하면 곧바로 멈춤(1.2초 그대로면·6초 지나면 끝) */
document.addEventListener('click',function(e){var a=e.target&&e.target.closest&&e.target.closest('#mcSfPanel a.mc-sfp-it[href],#mcSfBar a.mc-sfb-p[href],#mcSfBar a.mc-sfb-r[href],a.mc-sf-row[href]');if(a&&!e.ctrlKey&&!e.metaKey&&!e.shiftKey&&e.button===0)try{sessionStorage.setItem('mc_sf_jump','1');}catch(x){}},true);
(function(){var fl=false,nt='navigate';try{fl=sessionStorage.getItem('mc_sf_jump')==='1';sessionStorage.removeItem('mc_sf_jump');}catch(x){}
 try{var ne=performance.getEntriesByType('navigation')[0];if(ne&&ne.type)nt=ne.type;}catch(x){}
 if(!fl||nt!=='navigate'||!document.getElementById('mcSfBar'))return;
 window.__mcSfJump=true; /* scrollToSearchSection(검색칸 가운데로)이 덮어쓰지 않게 */
 try{if('scrollRestoration' in history)history.scrollRestoration='manual';}catch(x){}
 function target(){ /* ★ fix60 (펜닐) 폴더 제목 줄(← 폴더 이름 · 배너 아래 띠)을 맨 위로 — 목록이 길어도 카드까지 쭉 내려가지 않음, 목록이 짧으면 더 못 내려가 위쪽이 더 보임
     제목 줄이 없는 화면(맨 처음 폴더 등)만 예전처럼: 검색어 결과 화면(q)은 검색칸 아래 결과 목록, 폴더 화면은 첫 카드 목록 */
  var bb=document.querySelector('.mc-banner-band');if(bb&&bb.offsetParent!==null&&bb.getBoundingClientRect().height>10)return bb;
  var tb=document.querySelector('.mc-titlebar');if(tb&&tb.offsetParent!==null&&tb.getBoundingClientRect().height>10)return tb; /* 배너 없는 화면(콘텐츠 폴더 첫 화면 '전체파일' 등)의 제목 줄 */
  var u=new URLSearchParams(location.search),ss=document.getElementById('search-section');
  if((u.get('q')||'').trim()!==''&&ss&&ss.offsetParent!==null){var k=ss.children;for(var j=0;j<k.length;j++){if(k[j].tagName!=='FORM'&&k[j].getBoundingClientRect().height>10)return k[j];}}
  var g=document.querySelectorAll('.grid');for(var i=0;i<g.length;i++){var c=g[i].querySelector('.card');if(c&&c.getBoundingClientRect().height>10&&g[i].offsetParent!==null)return g[i];}return null;}
 var t0=Date.now(),last=Date.now(),stop=false,iv=0;
 function end(){stop=true;clearInterval(iv);['wheel','touchstart','keydown','mousedown'].forEach(function(k){window.removeEventListener(k,user,true);});}
 function user(){end();}
 ['wheel','touchstart','keydown','mousedown'].forEach(function(k){window.addEventListener(k,user,{capture:true,passive:true});});
 function place(){if(stop)return;if(Date.now()-t0>6000){end();return;}var el=target();if(!el)return;
  var mx=Math.max(0,(document.documentElement.scrollHeight||0)-window.innerHeight);var gap=(el.classList.contains('mc-banner-band')||el.classList.contains('mc-titlebar'))?6:8;var y=Math.min(mx,Math.max(0,Math.round(el.getBoundingClientRect().top+(window.scrollY||document.documentElement.scrollTop||0)-gap))); /* 목록이 짧아 더 못 내려가면 갈 수 있는 끝까지(그 자리에서 끝남) */
  if(Math.abs((window.scrollY||0)-y)>2){window.scrollTo(0,y);last=Date.now();}else if(Date.now()-last>1200)end();}
 place();document.addEventListener('DOMContentLoaded',place);window.addEventListener('load',function(){place();setTimeout(place,50);});iv=setInterval(place,100);
})();
window.addEventListener('pageshow',function(e){if(!e.persisted||SQ===null)return;decide('back_forward');endedView();seenMark();if(P&&P.__redraw)P.__redraw();});
function init(){if(inited||!P)return;inited=true;var D;try{D=JSON.parse(P.querySelector('.mc-sfp-data').textContent);}catch(e){return;}
 var T=D.txt,body=P.querySelector('.mc-sfp-b'),more=P.querySelector('.mc-sfp-more'),cap=P.querySelector('.mc-sfp-cap'),q=P.querySelector('.mc-sfp-q'),lim=60,STEP=60,CI=-1;D.rows.forEach(function(r,i){if(r.k&&CI<0)CI=i;});function baseLim(){return Math.max(STEP,Math.ceil((CI+1+20)/STEP)*STEP);}lim=baseLim(); /* ★ fix43 지금 폴더가 뒤쪽이어도 처음부터 그려지게 */
 var nf=function(x){x=String(x||'');try{x=x.normalize('NFC');}catch(e){}return x.toLowerCase();};D.rows.forEach(function(r){r._k=nf(r.n+' '+r.p);});
 function item(r){var a=el(r.u?'a':'div','mc-sfp-it'+(r.k?' mc-cur':'')+(!r.k&&r.i&&SEEN[r.i]?' mc-seen':''));if(r.u)a.setAttribute('href',r.u);else a.setAttribute('aria-current','page');
  var t=el('span','mc-sfp-tx');t.appendChild(el('span','mc-sfp-n',r.n));if(r.p)t.appendChild(el('span','mc-sfp-p',r.p));a.appendChild(t);
  if(r.k)a.appendChild(el('span','mc-sfp-here',T.here));if(r.c>0)a.appendChild(el('span','mc-sfp-c',String(r.c)));a.title=r.n+(r.p?' — '+r.p:'');return a;}
 function draw(){var f=nf(q.value.trim()),list=f?D.rows.filter(function(r){return r._k.indexOf(f)>=0;}):D.rows,cnt={};list.forEach(function(r){cnt[r.g]=(cnt[r.g]||0)+1;});
  body.textContent='';var g=null,box=null;list.slice(0,lim).forEach(function(r){if(r.g!==g||!box){g=r.g;if(D.txt.multi&&!(cnt[g]===1&&r.n===(D.groups[g]||''))){ /* 콘텐츠 폴더 맨 위 하나뿐이면 묶음 제목이 이름과 겹쳐 생략 */var hd=el('div','mc-sfp-gh');hd.appendChild(el('span','',D.groups[g]||''));hd.appendChild(el('span','',T.cnt.replace('%d',cnt[g])));body.appendChild(hd);}box=el('div','mc-sfp-box');body.appendChild(box);}box.appendChild(item(r));});
  if(!list.length)body.appendChild(el('div','mc-sfp-none',T.none));var rest=list.length-Math.min(lim,list.length);if(rest>0){more.hidden=false;more.textContent=T.more.replace('%d',rest);}else more.hidden=true;
  cap.hidden=!(T.cap&&!f);cap.textContent=T.cap;if(window.applyVisitedLinkStyle)try{applyVisitedLinkStyle();}catch(e){}}
 var tm=0;q.addEventListener('input',function(){clearTimeout(tm);tm=setTimeout(function(){lim=q.value.trim()?STEP:baseLim();draw();},120);});more.addEventListener('click',function(){lim+=STEP;draw();});
 q.addEventListener('keydown',function(e){if(e.key==='Escape'){e.preventDefault();mcSfClose(true);}e.stopPropagation();});P.__redraw=function(){draw();};P.__reset=function(){if(q.value){q.value='';lim=baseLim();draw();}};P.__toCur=function(){var c=body.querySelector('.mc-sfp-it.mc-cur');if(!c)return;var top=c.offsetTop-body.offsetTop;body.scrollTop=Math.max(0,top-(body.clientHeight-c.offsetHeight)/2);}; /* ★ fix43 열면 '지금 여기'가 창 가운데에 보이게(펜닐 요청) */draw();} /* fix42 다시 열면 거르기 글은 비움 */
function place(btn){
 P.classList.toggle('mc-sheet',narrow());
 if(narrow()){P.style.left='';P.style.top='';P.style.bottom='';P.style.width='';P.style.maxHeight='';return;}
 var vw=window.innerWidth,vh=window.innerHeight,w=Math.min(380,vw-24),bar=btn&&btn.closest?btn.closest('.mc-sfbar'):null;
 /* ★ (2026-11-07 fix42) 막대의 [목록]이면 막대 바로 위 가운데에 작은 창(예전: 단추 왼쪽 끝에 맞춘 440px·화면 높이 거의 전부 — 한쪽에 너무 크다는 펜닐 제보) */
 if(bar){var b=bar.getBoundingClientRect();P.style.width=w+'px';P.style.left=Math.max(12,Math.min(vw-w-12,Math.round(b.left+b.width/2-w/2)))+'px';P.style.top='';P.style.bottom=Math.round(vh-b.top+10)+'px';P.style.maxHeight=Math.max(200,Math.min(440,Math.round(b.top-24)))+'px';return;}
 var r=btn.getBoundingClientRect();
 P.style.width=w+'px';var left=Math.max(12,Math.min(vw-w-12,Math.round(r.left+r.width/2-w/2)));P.style.left=left+'px';
 var below=vh-r.bottom-16,above=r.top-16;
 if(below>=280||below>=above){P.style.top=(r.bottom+6)+'px';P.style.bottom='';P.style.maxHeight=Math.max(200,Math.min(440,below))+'px';}
 else{P.style.top='';P.style.bottom=(vh-r.top+6)+'px';P.style.maxHeight=Math.max(200,Math.min(440,above))+'px';}
}
function setExp(v){document.querySelectorAll('.mc-sf-open,.mc-sfbar [aria-haspopup]').forEach(function(x){x.setAttribute('aria-expanded',v?'true':'false');});}
window.mcSfOpen=function(btn){if(!P)return;if(!P.hidden&&opener===btn){mcSfClose();return;}init();if(P.__reset)P.__reset();opener=btn||null;place(btn||document.body);P.hidden=false;B.hidden=!narrow();setExp(true);if(P.__toCur)P.__toCur();
 var q=P.querySelector('.mc-sfp-q');if(q&&window.matchMedia&&matchMedia('(hover:hover) and (pointer:fine)').matches)try{q.focus({preventScroll:true});}catch(e){}};
window.mcSfClose=function(kbd){if(!P||P.hidden)return;P.hidden=true;B.hidden=true;setExp(false);var o=opener;opener=null;if(kbd===true&&o&&o.focus)try{o.focus({preventScroll:true});}catch(e){}}; /* fix42 초점은 Esc 로 닫을 때만 되돌림 */
B.addEventListener('click',mcSfClose);
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&P&&!P.hidden){e.preventDefault();mcSfClose(true);}});
document.addEventListener('mousedown',function(e){if(!P||P.hidden||narrow())return;if(P.contains(e.target)||(opener&&opener.contains&&opener.contains(e.target)))return;mcSfClose();},true);
window.addEventListener('resize',function(){if(P&&!P.hidden&&opener)place(opener);});
window.addEventListener('scroll',function(){if(P&&!P.hidden&&!narrow()&&opener)place(opener);},{passive:true});
['touchstart','touchmove','wheel'].forEach(function(ev){P&&P.addEventListener(ev,function(e){e.stopPropagation();},{passive:true});});
/* 좁은 화면: 오른쪽 아래 떠 있는 단추(어두운 화면 전환 — 인라인 !important 라 CSS 로 못 옮김)가 막대와 겹치지 않게 위로 */
function lift(){var d=document.getElementById('darkmode-toggle');if(!d)return;var on=window.matchMedia&&matchMedia('(max-width: 600px)').matches&&document.getElementById('mcSfBar')&&!document.body.classList.contains('mc-sf-ended');d.style.setProperty('bottom',on?'84px':'45px','important');}
lift();endedView();document.addEventListener('DOMContentLoaded',lift);window.addEventListener('load',function(){lift();setTimeout(lift,400);});window.addEventListener('resize',lift);
})();
JS;
}

function mc_sf_css_once() { static $done = false; if ($done) return ''; $done = true; return mc_sf_css(); }
/** 목록 창·이동 막대·안내 모양 */
function mc_sf_css() {
    return <<<'CSS'
<style id="mc-sf-css">
.mc-sf-i{flex:0 0 auto;display:inline-block}
.mc-sf-sum{display:flex;align-items:center;gap:10px;flex-wrap:wrap;max-width:520px;margin:2px auto 10px;text-align:left;font-size:13px;color:#5f6570}
.mc-sf-sumt b{color:#1d2026}
.mc-sf-open{display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:0 12px;border-radius:999px;border:1px solid #a8dab5;background:#fff;color:#137333;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer}
.mc-sf-open:hover,.mc-sf-open[aria-expanded="true"]{background:#e6f4ea}
.mc-sf-open:focus-visible,.mc-sfb-b:focus-visible,.mc-sf-more:focus-visible,.mc-sf-row:focus-visible,.mc-sfp-it:focus-visible{outline:2px solid #1e8e3e;outline-offset:2px}
.mc-sf-none{max-width:560px;margin:8px auto 0;text-align:left;display:flex;flex-direction:column;gap:12px}
.mc-sf-nh{display:flex;align-items:flex-start;gap:12px}
.mc-sf-nico{width:40px;height:40px;flex:0 0 auto;border-radius:999px;background:#eef0f3;color:#5f6570;display:inline-flex;align-items:center;justify-content:center}
.mc-sf-ntx{display:flex;flex-direction:column;gap:3px;min-width:0}
.mc-sf-nt{font-size:17px;font-weight:700;color:#1d2026;line-height:1.4;word-break:keep-all;overflow-wrap:anywhere}
.mc-sf-nd{font-size:13px;color:#5f6570;line-height:1.55;word-break:keep-all;overflow-wrap:anywhere}
.mc-sf-oh{font-size:14px;font-weight:700;color:#1d2026;margin-top:4px}
.mc-sf-oh span{font-weight:400;font-size:12px;color:#5f6570}
.mc-sf-list{list-style:none;margin:0;padding:0;border:1px solid #d5d9e0;border-radius:14px;overflow:hidden;background:#fff}
.mc-sf-list li+li{border-top:1px solid #eceef2}
.mc-sf-row{display:flex;align-items:center;gap:10px;min-height:48px;padding:6px 14px;color:#1d2026!important;text-decoration:none!important}
.mc-sf-row:hover{background:#f6f8fb}
.mc-sf-row>.mc-sf-i:first-child{color:#137333}
.mc-sf-row>.mc-sf-i:last-child{color:#9aa0ab}
.mc-sf-rt{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;line-height:1.3}
.mc-sf-rn{font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mc-sf-rp{font-size:11px;color:#5f6570;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mc-sf-c{flex:0 0 auto;font-size:12px;font-weight:700;color:#137333;background:#e6f4ea;border-radius:8px;padding:2px 8px}
.mc-sf-more{display:flex;align-items:center;justify-content:center;gap:6px;min-height:44px;border:1px dashed #a8dab5;border-radius:12px;background:#fff;color:#137333;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer}
.mc-sf-more:hover{background:#e6f4ea}
.mc-sf-nowhere{align-items:center;text-align:center;gap:8px;padding:6px 0}
.mc-sf-nico.mc-sf-big{width:56px;height:56px}
.mc-sf-tips{margin:6px 0 0;padding:10px 16px 6px 30px;text-align:left;font-size:12.5px;color:#5f6570;background:#f6f7f9;border-radius:12px;line-height:1.55}
.mc-sf-tips li{margin:0 0 4px}
.mc-sf-clear{display:inline-flex;align-items:center;min-height:44px;padding:0 18px;margin-top:4px;border-radius:999px;border:1px solid #a8dab5;background:#fff;color:#137333!important;font-size:13px;font-weight:700;text-decoration:none!important}
.mc-sf-clear:hover{background:#e6f4ea}
/* ② 목록 창 */
.mc-sfp-back{position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.38)}
.mc-sfp-back[hidden],.mc-sfp[hidden]{display:none}
.mc-sfp{position:fixed;z-index:10001;display:flex;flex-direction:column;background:#fff;color:#1d2026;border:1px solid #d5d9e0;border-radius:14px;box-shadow:0 12px 32px rgba(15,23,42,.18);overflow:hidden;text-align:left;font-size:14px}
.mc-sfp-grip{display:none}
.mc-sfp.mc-sheet{left:0!important;right:0;bottom:0!important;top:auto!important;width:auto!important;max-height:min(78vh,640px)!important;border-radius:18px 18px 0 0;border:0;box-shadow:0 -8px 24px rgba(15,23,42,.2);padding-bottom:env(safe-area-inset-bottom,0px)}
.mc-sfp.mc-sheet .mc-sfp-grip{display:block;width:40px;height:4px;border-radius:2px;background:#cfd4dc;margin:8px auto 0}
.mc-sfp-h{display:flex;align-items:center;gap:8px;padding:10px 10px 6px 14px}
.mc-sfp-t{flex:1 1 auto;min-width:0;font-size:14px;font-weight:700;overflow-wrap:anywhere}
.mc-sfp-x{width:40px;height:40px;flex:0 0 auto;border:0;border-radius:999px;background:#f2f4f7;color:#5f6570;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.mc-sfp-x:hover{background:#e7eaef}
.mc-sfp-f{padding:0 12px 8px}
.mc-sfp-q{width:100%;height:40px;box-sizing:border-box;padding:0 12px;border:1px solid #d5d9e0;border-radius:10px;background:#f6f7f9;font-size:14px;font-family:inherit;color:#1d2026}
.mc-sfp-q:focus{outline:none;border-color:#1e8e3e;background:#fff;box-shadow:0 0 0 3px rgba(30,142,62,.18)}
.mc-sfp-b{flex:1 1 auto;min-height:0;overflow:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;padding:0 6px 6px}
.mc-sfp-gh{display:flex;justify-content:space-between;gap:8px;font-size:12px;color:#5f6570;margin:8px 8px 4px}
.mc-sfp-gh>span:first-child{font-weight:700;color:#3c4150}
.mc-sfp-it{display:flex;align-items:center;gap:8px;min-height:46px;padding:4px 10px;border-radius:10px;color:#1d2026!important;text-decoration:none!important}
a.mc-sfp-it:hover{background:#f6f8fb}
.mc-sfp-it.mc-cur{background:#f3faf5}
.mc-sfp-tx{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;line-height:1.3}
.mc-sfp-n{font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mc-sfp-p{font-size:11px;color:#5f6570;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mc-sfp-here{flex:0 0 auto;font-size:11px;font-weight:700;color:#137333;background:#e6f4ea;border-radius:6px;padding:2px 6px}
.mc-sfp-c{flex:0 0 auto;font-size:12px;font-weight:700;color:#5f6570}
.mc-sfp-it.mc-cur .mc-sfp-c{color:#137333}
.mc-sfp-more{margin:6px 12px 10px;min-height:40px;border:1px dashed #b9c2cf;border-radius:10px;background:transparent;color:#137333;font-weight:700;font-family:inherit;cursor:pointer}
.mc-sfp-more[hidden],.mc-sfp-cap[hidden]{display:none}
.mc-sfp-cap,.mc-sfp-none{font-size:12px;color:#5f6570;margin:8px 14px 10px}
.mc-sfp a.mc-sfp-it.visited-link,.mc-sfp a.mc-sfp-it.visited-link *{color:#1d2026!important}
.mc-sfp a.mc-sfp-it.visited-link .mc-sfp-p,.mc-sfp a.mc-sfp-it.visited-link .mc-sfp-c{color:#5f6570!important}
.mc-sfp a.mc-sfp-it.visited-link .mc-sfp-n::after{content:' \2713';color:#1e8e3e;font-weight:700}
/* ④ 아래 이동 막대 */
.mc-sfbar{position:fixed;left:50%;bottom:calc(16px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);z-index:1040;display:flex;align-items:center;gap:2px;max-width:calc(100vw - 16px);box-sizing:border-box;padding:4px 6px;border-radius:999px;background:#1f3d2a;color:#fff;box-shadow:0 8px 24px rgba(15,23,42,.28);white-space:nowrap;font-size:13px}
.mc-sfb-b{display:inline-flex;align-items:center;justify-content:center;gap:4px;min-width:44px;height:44px;padding:0 10px;box-sizing:border-box;border:0;border-radius:999px;background:transparent;color:#fff!important;font-size:13px;font-weight:600;font-family:inherit;text-decoration:none!important;cursor:pointer}
a.mc-sfb-b:hover,button.mc-sfb-b:hover{background:rgba(255,255,255,.14)}
.mc-sfb-b.mc-off{color:rgba(255,255,255,.35)!important;cursor:default}
.mc-sfb-r{flex-direction:row-reverse}
.mc-sfb-end{padding:0}
.mc-sfb-m{display:flex;flex-direction:column;align-items:center;line-height:1.2;padding:0 6px;min-width:0}
.mc-sfb-top{font-size:11px;color:#b7d9c0;max-width:180px;overflow:hidden;text-overflow:ellipsis}
.mc-sfb-big{font-size:14px;font-weight:700}
.mc-sfb-sep{width:1px;height:22px;background:rgba(255,255,255,.25);margin:0 2px}
/* ★ (2026-11-07 fix42) [목록] 단추가 잘 안 보이던 것(어두운 막대 위 흰 글자 하나) — 밝은 초록 바탕 알약으로, 열려 있으면 흰 바탕 */
.mc-sfbar .mc-sfb-list{background:#d8efdf;color:#173322!important;font-weight:700;padding:0 14px;margin:0 2px}
.mc-sfbar .mc-sfb-list:hover{background:#ffffff}
.mc-sfbar .mc-sfb-list[aria-expanded="true"]{background:#ffffff;box-shadow:0 0 0 2px #81c995}
.mc-sfbar .mc-sfb-b:focus{outline:none}
.mc-sfbar .mc-sfb-b:focus-visible{outline:2px solid #1e8e3e;outline-offset:2px}
/* ★ (2026-11-07 fix43) 막대 색 다시 — 진한 녹색이 어둡고 무겁다는 펜닐 의견: 밝은 흰 막대(얇은 테두리·부드러운 그림자) + 사이트 머리줄과 같은 파란 강조 */
.mc-sfbar{background:rgba(255,255,255,.97);color:#1d2026;border:1px solid #d5d9e0;box-shadow:0 6px 20px rgba(15,23,42,.14),0 1px 3px rgba(15,23,42,.08);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)}
.mc-sfbar .mc-sfb-b{color:#2f343c!important}
.mc-sfbar a.mc-sfb-b:hover,.mc-sfbar button.mc-sfb-b:hover{background:#f1f3f6}
.mc-sfbar .mc-sfb-b.mc-off{color:#b9bec7!important;background:transparent}
.mc-sfbar .mc-sfb-top{color:#5f6570}
.mc-sfbar .mc-sfb-big{color:#137333}
.mc-sfbar .mc-sfb-sep{background:#e3e6ea}
.mc-sfbar .mc-sfb-list{background:#e6f4ea;color:#137333!important}
.mc-sfbar .mc-sfb-list:hover{background:#ceead6}
.mc-sfbar .mc-sfb-list[aria-expanded="true"]{background:#1e8e3e;color:#ffffff!important;box-shadow:none}
.mc-sfbar .mc-sfb-end{color:#5f6570!important}
.dark-mode .mc-sfbar,[data-theme="dark"] .mc-sfbar{background:rgba(38,40,45,.97);color:#e9ebef;border-color:#3a3e48;box-shadow:0 6px 20px rgba(0,0,0,.45)}
.dark-mode .mc-sfbar .mc-sfb-b,[data-theme="dark"] .mc-sfbar .mc-sfb-b{color:#e3e6eb!important}
.dark-mode .mc-sfbar a.mc-sfb-b:hover,[data-theme="dark"] .mc-sfbar a.mc-sfb-b:hover,.dark-mode .mc-sfbar button.mc-sfb-b:hover,[data-theme="dark"] .mc-sfbar button.mc-sfb-b:hover{background:#33363d}
.dark-mode .mc-sfbar .mc-sfb-b.mc-off,[data-theme="dark"] .mc-sfbar .mc-sfb-b.mc-off{color:#5d626c!important}
.dark-mode .mc-sfbar .mc-sfb-top,[data-theme="dark"] .mc-sfbar .mc-sfb-top{color:#a3a9b4}
.dark-mode .mc-sfbar .mc-sfb-big,[data-theme="dark"] .mc-sfbar .mc-sfb-big{color:#81c995}
.dark-mode .mc-sfbar .mc-sfb-sep,[data-theme="dark"] .mc-sfbar .mc-sfb-sep{background:#3a3e48}
.dark-mode .mc-sfbar .mc-sfb-list,[data-theme="dark"] .mc-sfbar .mc-sfb-list{background:#1f3a28;color:#81c995!important}
.dark-mode .mc-sfbar .mc-sfb-list[aria-expanded="true"],[data-theme="dark"] .mc-sfbar .mc-sfb-list[aria-expanded="true"]{background:#81c995;color:#0d2b17!important}
body.mc-sf-on{padding-bottom:84px}
@media (max-width:600px){body.mc-sf-on #scrollTopBtn{bottom:136px!important}}
body:has(#bulkDeleteBar) .mc-sfbar,body.modal-open .mc-sfbar{display:none}
body.mc-sf-ended .mc-sfbar,body.mc-sf-ended .mc-sf-open,body.mc-sf-ended .mc-sfp,body.mc-sf-ended .mc-sfp-back{display:none!important} /* fix48 닫은 검색에 뒤로 가기로 돌아옴 */
@media (max-width:576px){
 .mc-sfb-l{display:none}
 .mc-sfb-b{padding:0}
 .mc-sfb-top{max-width:130px}
 .mc-sf-nt{font-size:15px}
}
/* 어두운 화면 */
.dark-mode .mc-sf-sum,[data-theme="dark"] .mc-sf-sum{color:#a3a9b4}
.dark-mode .mc-sf-sumt b,[data-theme="dark"] .mc-sf-sumt b,.dark-mode .mc-sf-nt,[data-theme="dark"] .mc-sf-nt,.dark-mode .mc-sf-oh,[data-theme="dark"] .mc-sf-oh{color:#e9ebef}
.dark-mode .mc-sf-open,[data-theme="dark"] .mc-sf-open,.dark-mode .mc-sf-more,[data-theme="dark"] .mc-sf-more,.dark-mode .mc-sf-clear,[data-theme="dark"] .mc-sf-clear{background:#1e1e1e;border-color:#3d6b4a;color:#81c995!important}
.dark-mode .mc-sf-open:hover,[data-theme="dark"] .mc-sf-open:hover,.dark-mode .mc-sf-open[aria-expanded="true"],[data-theme="dark"] .mc-sf-open[aria-expanded="true"]{background:#1f3a28}
.dark-mode .mc-sf-nd,[data-theme="dark"] .mc-sf-nd,.dark-mode .mc-sf-oh span,[data-theme="dark"] .mc-sf-oh span,.dark-mode .mc-sf-rp,[data-theme="dark"] .mc-sf-rp,.dark-mode .mc-sf-tips,[data-theme="dark"] .mc-sf-tips{color:#a3a9b4}
.dark-mode .mc-sf-nico,[data-theme="dark"] .mc-sf-nico{background:#2a2d33;color:#a3a9b4}
.dark-mode .mc-sf-list,[data-theme="dark"] .mc-sf-list{background:#1e1e1e;border-color:#3a3e48}
.dark-mode .mc-sf-list li+li,[data-theme="dark"] .mc-sf-list li+li{border-color:#2c2f36}
.dark-mode .mc-sf-row,[data-theme="dark"] .mc-sf-row{color:#e9ebef!important}
.dark-mode .mc-sf-row:hover,[data-theme="dark"] .mc-sf-row:hover{background:#262a31}
.dark-mode .mc-sf-c,[data-theme="dark"] .mc-sf-c{background:#1f3a28;color:#81c995}
.dark-mode .mc-sf-tips,[data-theme="dark"] .mc-sf-tips{background:#1b1d21}
.dark-mode .mc-sfp,[data-theme="dark"] .mc-sfp{background:#181818;border-color:#3a3e48;color:#e9ebef}
.dark-mode .mc-sfp-x,[data-theme="dark"] .mc-sfp-x{background:#2a2d33;color:#c3c8d1}
.dark-mode .mc-sfp-q,[data-theme="dark"] .mc-sfp-q{background:#222428;border-color:#3a3e48;color:#e9ebef}
.dark-mode .mc-sfp-it,[data-theme="dark"] .mc-sfp-it,.dark-mode .mc-sfp a.mc-sfp-it.visited-link,[data-theme="dark"] .mc-sfp a.mc-sfp-it.visited-link,.dark-mode .mc-sfp a.mc-sfp-it.visited-link *,[data-theme="dark"] .mc-sfp a.mc-sfp-it.visited-link *{color:#e9ebef!important}
.dark-mode a.mc-sfp-it:hover,[data-theme="dark"] a.mc-sfp-it:hover{background:#262a31}
.dark-mode .mc-sfp-it.mc-cur,[data-theme="dark"] .mc-sfp-it.mc-cur{background:#1f3a28}
.dark-mode .mc-sfp-p,[data-theme="dark"] .mc-sfp-p,.dark-mode .mc-sfp-c,[data-theme="dark"] .mc-sfp-c,.dark-mode .mc-sfp-gh,[data-theme="dark"] .mc-sfp-gh,.dark-mode .mc-sfp-cap,[data-theme="dark"] .mc-sfp-cap,.dark-mode .mc-sfp-none,[data-theme="dark"] .mc-sfp-none{color:#a3a9b4!important}
.dark-mode .mc-sfp-gh>span:first-child,[data-theme="dark"] .mc-sfp-gh>span:first-child{color:#d6dae1}
.dark-mode .mc-sfp-here,[data-theme="dark"] .mc-sfp-here{background:#1f3a28;color:#81c995}
.dark-mode .mc-sfp-more,[data-theme="dark"] .mc-sfp-more{border-color:#4a505c;color:#81c995}
/* 목록 창의 '지금 여기'도 같은 파란 강조로 */
.mc-sfp-it.mc-cur{background:#e6f4ea;box-shadow:inset 3px 0 0 #1e8e3e}
.mc-sfp-here{background:#1e8e3e;color:#ffffff}
.mc-sfp-it.mc-cur .mc-sfp-c{color:#137333}
.dark-mode .mc-sfp-it.mc-cur,[data-theme="dark"] .mc-sfp-it.mc-cur{background:#1f3a28;box-shadow:inset 3px 0 0 #81c995}
.dark-mode .mc-sfp-here,[data-theme="dark"] .mc-sfp-here{background:#81c995;color:#0d2b17}
.dark-mode .mc-sfp-it.mc-cur .mc-sfp-c,[data-theme="dark"] .mc-sfp-it.mc-cur .mc-sfp-c{color:#81c995!important}
/* 열린 [목록]에 마우스가 올라가 있어도 진한 초록 유지(흰 글자가 연한 바탕에 묻히던 것) */
.mc-sfbar .mc-sfb-list[aria-expanded="true"]:hover{background:#188038}
.dark-mode .mc-sfbar .mc-sfb-list[aria-expanded="true"]:hover,[data-theme="dark"] .mc-sfbar .mc-sfb-list[aria-expanded="true"]:hover{background:#a8dab5}
/* (fix45) 목록 줄에 마우스·손가락이 올라가면 사이트 공통 링크 효과(청록) 대신 같은 초록 — 이 목록 안에서만 */
body #mcSfPanel a.mc-sfp-it:hover .mc-sfp-n,body a.mc-sf-row:hover:not(.badge):not(.page-btn):not(.logo-link):not(.mc-x) .mc-sf-rn{color:#137333!important}
body #mcSfPanel a.mc-sfp-it:hover .mc-sfp-p,body #mcSfPanel a.mc-sfp-it:hover .mc-sfp-c,body a.mc-sf-row:hover:not(.badge):not(.page-btn):not(.logo-link):not(.mc-x) .mc-sf-rp{color:#5f6570!important}
body #mcSfPanel a.mc-sfp-it:hover,body a.mc-sf-row:hover:not(.badge):not(.page-btn):not(.logo-link):not(.mc-x){color:#137333!important}
.dark-mode #mcSfPanel a.mc-sfp-it:hover .mc-sfp-n,[data-theme="dark"] #mcSfPanel a.mc-sfp-it:hover .mc-sfp-n,.dark-mode a.mc-sf-row:hover:not(.badge):not(.page-btn):not(.logo-link):not(.mc-x) .mc-sf-rn,[data-theme="dark"] a.mc-sf-row:hover:not(.badge):not(.page-btn):not(.logo-link):not(.mc-x) .mc-sf-rn{color:#81c995!important}
.dark-mode #mcSfPanel a.mc-sfp-it:hover .mc-sfp-p,[data-theme="dark"] #mcSfPanel a.mc-sfp-it:hover .mc-sfp-p,.dark-mode #mcSfPanel a.mc-sfp-it:hover .mc-sfp-c,[data-theme="dark"] #mcSfPanel a.mc-sfp-it:hover .mc-sfp-c,.dark-mode a.mc-sf-row:hover:not(.badge):not(.page-btn):not(.logo-link):not(.mc-x) .mc-sf-rp,[data-theme="dark"] a.mc-sf-row:hover:not(.badge):not(.page-btn):not(.logo-link):not(.mc-x) .mc-sf-rp{color:#a3a9b4!important}
/* ★ fix47 ✓·회색 = '이 검색에서 들어가 본 폴더'(.mc-seen, JS mc_sf_seen)만 — 사이트 전체 방문 기록(.visited-link)은 이 목록에서 쓰지 않음 */
#mcSfPanel a.mc-sfp-it.visited-link,#mcSfPanel a.mc-sfp-it.visited-link .mc-sfp-n{color:#1d2026!important}
#mcSfPanel a.mc-sfp-it.visited-link .mc-sfp-p,#mcSfPanel a.mc-sfp-it.visited-link .mc-sfp-c{color:#5f6570!important}
#mcSfPanel a.mc-sfp-it .mc-sfp-n::after{content:none}
#mcSfPanel a.mc-sfp-it.mc-seen .mc-sfp-n,#mcSfPanel a.mc-sfp-it.mc-seen.visited-link .mc-sfp-n{color:#6b7280!important}
#mcSfPanel a.mc-sfp-it.mc-seen .mc-sfp-n::after{content:' \2713';color:#1e8e3e;font-weight:700}
html body .mc-sf-list > li > a.mc-sf-row.visited-link:not(.mc-seen) .mc-sf-rn{color:#1d2026!important}
html body .mc-sf-list > li > a.mc-sf-row.visited-link .mc-sf-rp{color:#5f6570!important}
html body .mc-sf-list > li > a.mc-sf-row.visited-link > .mc-sf-i:first-child,html body .mc-sf-list > li > a.mc-sf-row.visited-link .mc-sf-c{color:#137333!important}
html body .mc-sf-list > li > a.mc-sf-row.visited-link > .mc-sf-i:last-child{color:#9aa0ab!important}
html body .mc-sf-list > li > a.mc-sf-row.mc-seen:not(.mc-x) .mc-sf-rn{color:#6b7280!important}
html body .mc-sf-list > li > a.mc-sf-row.mc-seen .mc-sf-rn::after{content:' \2713';color:#1e8e3e;font-weight:700}
.dark-mode #mcSfPanel a.mc-sfp-it.visited-link,[data-theme="dark"] #mcSfPanel a.mc-sfp-it.visited-link,.dark-mode #mcSfPanel a.mc-sfp-it.visited-link .mc-sfp-n,[data-theme="dark"] #mcSfPanel a.mc-sfp-it.visited-link .mc-sfp-n{color:#e9ebef!important}
.dark-mode #mcSfPanel a.mc-sfp-it.visited-link .mc-sfp-p,[data-theme="dark"] #mcSfPanel a.mc-sfp-it.visited-link .mc-sfp-p,.dark-mode #mcSfPanel a.mc-sfp-it.visited-link .mc-sfp-c,[data-theme="dark"] #mcSfPanel a.mc-sfp-it.visited-link .mc-sfp-c{color:#a3a9b4!important}
.dark-mode #mcSfPanel a.mc-sfp-it.mc-seen .mc-sfp-n,[data-theme="dark"] #mcSfPanel a.mc-sfp-it.mc-seen .mc-sfp-n{color:#9aa0ab!important}
.dark-mode #mcSfPanel a.mc-sfp-it.mc-seen .mc-sfp-n::after,[data-theme="dark"] #mcSfPanel a.mc-sfp-it.mc-seen .mc-sfp-n::after{color:#81c995}
.dark-mode .mc-sf-list > li > a.mc-sf-row.visited-link:not(.mc-seen) .mc-sf-rn{color:#e9ebef!important}
.dark-mode .mc-sf-list > li > a.mc-sf-row.visited-link .mc-sf-rp{color:#a3a9b4!important}
.dark-mode .mc-sf-list > li > a.mc-sf-row.visited-link > .mc-sf-i:first-child,.dark-mode .mc-sf-list > li > a.mc-sf-row.visited-link .mc-sf-c{color:#81c995!important}
.dark-mode .mc-sf-list > li > a.mc-sf-row.mc-seen:not(.mc-x) .mc-sf-rn{color:#9aa0ab!important}
.dark-mode .mc-sf-list > li > a.mc-sf-row.mc-seen .mc-sf-rn::after{color:#81c995}
html body #mcSfPanel a.mc-sfp-it:hover:not(.mc-x):not(.mc-y):not(.mc-z) .mc-sfp-n{color:#137333!important} /* 본 폴더·방문한 링크도 마우스를 올리면 같은 초록(fix45) */
html.dark-mode body #mcSfPanel a.mc-sfp-it:hover:not(.mc-x):not(.mc-y):not(.mc-z) .mc-sfp-n,html .dark-mode #mcSfPanel a.mc-sfp-it:hover:not(.mc-x):not(.mc-y):not(.mc-z) .mc-sfp-n,html[data-theme="dark"] body #mcSfPanel a.mc-sfp-it:hover:not(.mc-x):not(.mc-y):not(.mc-z) .mc-sfp-n{color:#81c995!important}
</style>
CSS;
}

/** ★ (2026-11-04 22:05) 펜닐 결정(검색 칩 다듬기 ③A): 내려도 검색창·칩 줄이 위에 붙어 있게
 *  검색 영역(#search-section / .root-search-section)의 검색창·칩 줄을 그대로 본뜬 막대를 맨 위에 두고, 원래 자리가 화면 위로 지나가면 내려 보임
 *  (원래 영역은 그대로 — 위치를 바꾸지 않아 다른 화면 배치에 영향 없음 / 본뜬 칩도 링크·열어 본 ✓ 그대로) */
function mc_search_sticky_js() {
    return '<script>(function(){if(window.__mcSS)return;window.__mcSS=1;var me=document.currentScript;var sec=me&&me.closest?me.closest("#search-section,.root-search-section"):null;if(!sec||!window.IntersectionObserver)return;'
        . 'var form=sec.querySelector("form");if(!form)return;var chips=sec.querySelector(".mc-chips-wrap");'
        . 'var bar=document.createElement("div");bar.id="mcStickySearch";bar.setAttribute("aria-hidden","true");var inn=document.createElement("div");inn.className="mc-ss-in root-search-section";bar.appendChild(inn);'
        . 'function cl(n){var c=n.cloneNode(true);c.removeAttribute("id");c.querySelectorAll("[id]").forEach(function(x){x.removeAttribute("id");});c.querySelectorAll("script").forEach(function(x){x.remove();});return c;}'
        . 'inn.appendChild(cl(form));var cc=null;if(chips){var cw=cl(chips);cw.querySelectorAll("details,.mc-cp").forEach(function(x){x.remove();});inn.appendChild(cw);cc=cw.querySelector(".mc-chips");}'
        . 'document.body.appendChild(bar);var last=chips||form;var on=false;'
        . 'function set(v){if(v===on)return;on=v;bar.classList.toggle("mc-on",v);bar.setAttribute("aria-hidden",v?"false":"true");if(v&&cc){var oc=sec.querySelector(".mc-chips");if(oc)cc.scrollLeft=oc.scrollLeft;}if(v&&window.mcChipsOvf)window.mcChipsOvf();}'
        . 'if(cc)cc.addEventListener("scroll",function(){if(window.mcChipsOvf)window.mcChipsOvf();},{passive:true});bar.querySelectorAll("a,button,input").forEach(function(x){x.addEventListener("focus",function(){set(true);});});'
        . 'new IntersectionObserver(function(es){es.forEach(function(e){set(!e.isIntersecting&&e.boundingClientRect.top<0);});}).observe(last);'
        . 'if(cc)cc.addEventListener("wheel",function(e){if(cc.scrollWidth<=cc.clientWidth+1||Math.abs(e.deltaX)>Math.abs(e.deltaY))return;var d=e.deltaY*(e.deltaMode===1?16:1);var m=cc.scrollWidth-cc.clientWidth;if((d>0&&cc.scrollLeft<m-1)||(d<0&&cc.scrollLeft>0)){e.preventDefault();cc.scrollLeft+=d;}},{passive:false});'
        . '})();</script>';
}

/** ★ (2026-11-06 00:30) 검색 색인의 폴더(조합형 NFC 이름)가 디스크에 있는지 — 단계마다 그대로 있으면 그 이름, 없으면 그 단계 폴더 안에서 같은 글자(NFC 로 맞춘 이름)를 찾음
 *  (맥에서 만든 폴더 안에 윈도에서 만든 폴더처럼 자모 분리형·조합형이 섞인 경로도 맞게) */
function mc_search_dir_exists_norm($base, $rel) {
    $cur = rtrim((string)$base, '/\\');
    foreach (explode('/', (string)$rel) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') return false;
        if (is_dir($cur . '/' . $seg)) { $cur .= '/' . $seg; continue; }
        $hit = null;
        foreach ((@scandir($cur) ?: []) as $n) {
            if ($n === '.' || $n === '..') continue;
            if (normalize_korean($n) === $seg && is_dir($cur . '/' . $n)) { $hit = $n; break; }
        }
        if ($hit === null) return false;
        $cur .= '/' . $hit;
    }
    return true;
}

function show_search_results($q, $translated_q = [], $reverse_translated_q = []) {
    global $getdir, $bidx_param, $current_bidx, $base_dirs;
    
    // 모든 bidx의 search_index 파일 로드
    $index = [];
    foreach (array_keys($base_dirs) as $idx) {
        // ★ (2026-11-06 00:10) 콘텐츠 폴더 경로가 지금 없거나 못 읽으면(폴더를 지움·이름 바꿈·NAS 끊김) 그 색인은 안 씀 — 예전 색인이 그대로 남아 열리지 않는 폴더가 검색에 떴음(배경 갱신은 없는 경로를 건너뛰어 색인이 영영 안 바뀜)
        //   색인 파일은 지우지 않음(NAS 가 다시 붙으면 바로 다시 씀)
        if (!is_string($base_dirs[$idx]) || $base_dirs[$idx] === '' || !is_dir($base_dirs[$idx])) continue;
        $index_path = __DIR__ . '/src/search_index_' . $idx . '.json';
        if (is_file($index_path)) {
            $idx_data = function_exists('load_json_with_lock') ? load_json_with_lock($index_path) : json_decode(file_get_contents($index_path), true); // ★ (2026-11-02 10:03) 공유 잠금(다시 만드는 중에 읽으면 그 폴더 결과가 빠졌음)
            if (is_array($idx_data)) {
                // bidx가 없는 기존 데이터 호환
                foreach ($idx_data as &$entry) {
                    if (!isset($entry['bidx'])) {
                        $entry['bidx'] = $idx;
                    }
                }
                $index = array_merge($index, $idx_data);
            }
        }
    }
    
    // 기존 단일 파일도 체크 (하위 호환성)
    $old_index_path = __DIR__ . '/src/search_index.json';
    if (empty($index) && is_file($old_index_path) && isset($base_dirs[0]) && is_string($base_dirs[0]) && is_dir($base_dirs[0])) { // ★ (2026-11-06 00:10) 위와 같음(옛 색인은 첫 콘텐츠 폴더 것)
        $old_data = json_decode(file_get_contents($old_index_path), true);
        if (is_array($old_data)) {
            foreach ($old_data as &$entry) {
                if (!isset($entry['bidx'])) {
                    $entry['bidx'] = 0;
                }
            }
            $index = $old_data;
        }
    }
    
    if (empty($index)) {
        echo "<div class='p-2 text-danger'>⚠ " . __h('ui_search_index_missing') . "</div>";
        return;
    }
    
    // ✅ 검색어 배열 생성 (복합어는 구문만, 단일어는 개별 추가)
    $search_terms = [];
    
    // 원본 검색어 추가
    if (!empty($q)) {
        $search_terms[] = $q;
    }
    
    // 띄어쓰기 확인
    $has_space = (strpos($q, ' ') !== false);
    
    // ✅ 정방향 번역어 처리
    if (!empty($translated_q)) {
        if ($has_space) {
            // 복합어: 조합된 구문만 추가
            $combinations = generateSearchCombinations($q, $translated_q);
            foreach ($combinations as $combo) {
                $search_terms[] = $combo;
            }
        } else {
            // 단일어: 각 번역어를 개별 추가
            if (is_array($translated_q)) {
                foreach ($translated_q as $word) {
                    if (!empty($word)) {
                        $search_terms[] = strtolower(trim($word));
                    }
                }
            } else {
                $search_terms[] = strtolower($translated_q);
            }
        }
    }
    
    // ✅ 역방향 번역어 처리
    if (!empty($reverse_translated_q)) {
        if ($has_space) {
            // 복합어: 조합된 구문만 추가
            $combinations = generateSearchCombinations($q, $reverse_translated_q);
            foreach ($combinations as $combo) {
                $search_terms[] = $combo;
            }
        } else {
            // 단일어: 각 번역어를 개별 추가
            if (is_array($reverse_translated_q)) {
                foreach ($reverse_translated_q as $word) {
                    if (!empty($word)) {
                        $search_terms[] = trim($word);
                    }
                }
            } else {
                $search_terms[] = $reverse_translated_q;
            }
        }
    }
    
    // 중복 제거
    $search_terms = array_unique($search_terms);
    
    // error_log("🔍 최종 검색어 목록: " . implode(', ', $search_terms));
    
    $matched_dirs = [];  // ['dir_path' => ['bidx' => X, 'match_type' => 'dir'|'file'], ...]
    
    // 각 파일에 대해 검색어 매칭 (파일명 + 폴더명 모두 검색)
    array_walk($index, function ($entry) use (&$matched_dirs, $search_terms) {
        $filename = $entry['file'];
        $dirname = $entry['dir'];
        
        // NFD → NFC 정규화 적용
        $filename_normalized = normalize_korean($filename);
        $filename_lower = mb_strtolower($filename_normalized, 'UTF-8');
        
        $dirname_normalized = normalize_korean($dirname);
        $dirname_lower = mb_strtolower($dirname_normalized, 'UTF-8');
        
        foreach ($search_terms as $term) {
            if (empty($term)) continue;
            
            $term_normalized = normalize_korean($term);
            $term_lower = mb_strtolower($term_normalized, 'UTF-8');
            
            // ✅ 폴더명 매칭 확인 (우선)
            $dir_matched = mb_strpos($dirname_lower, $term_lower, 0, 'UTF-8') !== false;
            // ✅ 파일명 매칭 확인
            $file_matched = mb_strpos($filename_lower, $term_lower, 0, 'UTF-8') !== false;
            
            if ($dir_matched || $file_matched) {
                $bidx = $entry['bidx'] ?? 0;
                $dir_key = $bidx . '|' . $entry['dir'];  // bidx 포함하여 중복 방지
                
                // 이미 등록된 경우, 폴더명 매칭이 우선 (폴더명 매칭이면 q 제거)
                if (!isset($matched_dirs[$dir_key])) {
                    $matched_dirs[$dir_key] = [
                        'bidx' => $bidx,
                        'match_type' => $dir_matched ? 'dir' : 'file',
                        'cnt' => 0
                    ];
                }
                // ★ (2026-11-04 17:49) 폴더마다 맞는 파일 수(결과 없음 화면의 '다른 폴더' 목록에 표시)
                //   ★ (2026-11-04 18:30) 첫 검색어는 폴더 이름에만, 다른 검색어(번역어)가 파일 이름에 맞는 경우도 셈(이 반복은 처음 맞은 검색어에서 멈춤)
                if ($filename !== '') {
                    $__fm = $file_matched;
                    if (!$__fm) foreach ($search_terms as $__t) { if (!empty($__t) && mb_strpos($filename_lower, mb_strtolower(normalize_korean($__t), 'UTF-8'), 0, 'UTF-8') !== false) { $__fm = true; break; } }
                    if ($__fm) $matched_dirs[$dir_key]['cnt']++;
                }
                if ($dir_matched && $matched_dirs[$dir_key]['match_type'] === 'file') {
                    // 폴더명 매칭이 발견되면 업데이트
                    $matched_dirs[$dir_key]['match_type'] = 'dir';
                }
                break;
            }
        }
    });
    
    // ★ (2026-10-30) 그룹 권한이 없는 최상위 폴더의 결과는 뺌(검색 색인은 권한 구분 없이 전체를 담고 있음)
    foreach (array_keys($matched_dirs) as $__k) {
        $__p = explode('|', $__k, 2);
        $__bd = $base_dirs[(int)$__p[0]] ?? null;
        if ($__bd === null || !mc_path_allowed('/' . ltrim($__p[1] ?? '', '/'), $__bd)) unset($matched_dirs[$__k]);
    }
    // ★ (2026-11-06 00:10) 서버에서 지운 폴더는 뺌(펜닐 제보: 손으로 지운 폴더가 검색 칩에 계속 떴음 — 색인은 배경 갱신 때 새로 만들어짐)
    //   화면에 나오는 만큼(칩 50·목록 330)을 넉넉히 덮도록 앞에서부터 400곳까지만 확인(검색어가 짧아 맞는 폴더가 아주 많을 때 느려지지 않게)
    //   없는 폴더를 찾으면 bg_dirty 를 찍어 다음 배경 갱신 때 색인을 다시 만들게 함(목록 화면이 바뀐 폴더를 찍는 것과 같은 방식)
    $__chk = 0; $__stale = false;
    foreach (array_keys($matched_dirs) as $__k) {
        if (++$__chk > 400) break;
        $__p = explode('|', $__k, 2);
        $__bd = $base_dirs[(int)$__p[0]] ?? null;
        $__d = trim(str_replace('\\', '/', (string)($__p[1] ?? '')), '/');
        if ($__d === '.') $__d = '';
        if (!is_string($__bd)) { unset($matched_dirs[$__k]); continue; }
        if (is_dir($__bd . ($__d !== '' ? '/' . $__d : ''))) continue;
        // 색인 이름은 조합형(NFC)으로 바꿔 둔 것 — 맥에서 옮긴 폴더처럼 디스크 이름이 자모 분리형(NFD)이거나 섞여 있으면 한 단계씩 디스크 이름과 맞춰 봄(있는 폴더를 빼지 않게 · 바로 위 확인이 실패한 때만)
        if ($__d !== '' && mc_search_dir_exists_norm($__bd, $__d)) continue;
        unset($matched_dirs[$__k]); $__stale = true;
    }
    if ($__stale) @touch(__DIR__ . '/src/bg_dirty');
    unset($__chk, $__stale, $__d);
    
    // 번역어가 있으면 표시
    $display_parts = [$q];
    if (!empty($translated_q)) {
        $translated_display = generateTranslationCombinations($q, $translated_q);
        $display_parts[] = $translated_display;
    }
    if (!empty($reverse_translated_q)) {
        $reverse_display = is_array($reverse_translated_q) ? implode(', ', $reverse_translated_q) : $reverse_translated_q;
        $display_parts[] = $reverse_display;
    }
    
    $GLOBALS['mc_search_dirs'] = $matched_dirs; // ★ (2026-11-04 17:49) 현재 위치에 결과가 없을 때 그 자리에 '다른 폴더' 목록을 그리려고 기억(권한 없는 폴더는 위에서 이미 뺌)
    echo "<div class='p-2' id='mcSearchTop'><h5>" . __('ui_search_folder_results', '<strong>' . htmlspecialchars(implode(' / ', $display_parts)) . '</strong>') . "</h5>";
    
    if (empty($matched_dirs)) {
        echo '<div style="padding: 20px 0;">';
        echo '<div style="color: #dc3545; font-size: 1.2em; font-weight: bold; margin-bottom: 8px;">😕 ' . __h('ui_no_search_results') . '</div>';
        echo '<div style="color: #6c757d;">' . __('ui_search_not_found', h($q)) . '</div>';
        echo '</div>';
    } else {
        echo '<ul class="search-results-list">';
        foreach ($matched_dirs as $dir_key => $match_info) {
            // bidx|dir 형식에서 분리
            $key_parts = explode('|', $dir_key, 2);
            $match_bidx = (int)$key_parts[0];
            $match_dir = $key_parts[1] ?? '';
            $match_type = $match_info['match_type'];
            
            $encoded = encode_url("/" . $match_dir); // ★ (2026-11-02 11:37) 일반 목록 링크와 같은 방식 — urlencode 면 '..' 든 폴더(A...B)가 dir 정리에서 깨져 안 열렸음
            
            // ✅ 폴더명 매칭: q 제거 (폴더 내용 보기)
            // ✅ 파일명 매칭: q 유지 (해당 폴더에서 파일 검색)
            if ($match_type === 'dir') {
                $link_url = "index.php?dir={$encoded}&bidx=" . $match_bidx;
            } else {
                $link_url = "index.php?dir={$encoded}&q=" . urlencode($q) . "&bidx=" . $match_bidx . "#search-section";
            }
            
            $current_dir = trim($getdir, '/');
            $search_dir = trim($match_dir, '/');
            $is_current = ($current_dir === $search_dir && $current_bidx == $match_bidx);
            
            $class = $is_current ? 'search-result-link current-folder' : 'search-result-link';
            
            // 표시용 디렉토리명은 NFC 정규화
            $display_dir = normalize_korean($match_dir);
            if ($display_dir === '.' || $display_dir === '') $display_dir = __('js_root'); // ★ (2026-11-04 00:28) 최상위 파일이면 '.' 대신 '루트'
            
            // ✅ 매칭 타입 배지 (폴더명/파일명 구분)
            $match_badge = '';
            if ($match_type === 'dir') {
                $match_badge = '<span class="badge badge-info" style="font-size: 11px; margin-right: 5px;">📁 ' . __h('ui_folder_name') . '</span>';
            } else {
                $match_badge = '<span class="badge badge-warning" style="font-size: 11px; margin-right: 5px;">📄 ' . __h('ui_file_name') . '</span>';
            }
            
            // bidx 표시 (여러 폴더가 있는 경우)
            $bidx_label = '';
            if (count($base_dirs) > 1) {
                $bidx_label = ' <span class="badge badge-secondary" style="vertical-align:middle; position:relative; top:-2px;">' . h(basename($base_dirs[$match_bidx])) . '</span>'; // ★ (2026-11-02 19:05) 이스케이프
            }
            
            echo "<li class='" . ($is_current ? 'current-folder-item' : '') . "'>";
            echo "<a href='{$link_url}' class='{$class}'>";
            echo $match_badge . highlight_search($display_dir, $search_terms) . $bidx_label;
            
            if ($is_current) {
                echo ' <span class="current-indicator">← ' . __h('ui_current_location') . '</span>';
            }
            echo "</a></li>";
        }
        echo '</ul>';
    }
    echo "</div>";
}
// 검색어 포함된 전체 폴더 검색 //

// ★ (2026-10-30) 폴더를 못 읽는 경우에도 아래 출력부가 정의 안 된 변수 경고 없이 빈 목록으로 그려지게
$file_list = $jpg_list = $dir_list = $title_list = [];
$dirinfo = [];
$is_root = false;
$mc_dir_err = ''; $mc_dir_err_gone = false; // ★ (2026-11-05 23:45) 폴더를 열 수 없을 때 안내 종류(아래)
if (is_dir($dir)) {
    // 변수 선언
    $file_list = $jpg_list = $dir_list = $title_list = [];
	$desc_cache = [];  // ✅ 여기 추가
    $dirinfo = [];
    $recent = is_file($recent_file) ? (json_decode(file_get_contents($recent_file), true) ?: []) : [];
    $is_root = (str_replace("/", "", $base_dir) === str_replace("/", "", $dir));

    // ✅ 파일 목록 캐시 체크
    $list_cache_file = $dir . '/.filelist_cache.json';
    $dir_mtime = @filemtime($dir);
    $use_list_cache = false;
    $_cache_debug = 'no_cache_file'; // 디버깅용
    $_time_cache_read = 0;
    $_time_after_cache = 0;
    
    // ✅ 캐시 버전 (배지 정보 추가 등 구조 변경 시 올리기)
    $CACHE_VERSION = 4; // ★ (2026-11-02 22:03) 4: 위와 같음
    
    $_t1 = microtime(true);
    
    if (is_file($list_cache_file)) {
        $cache_mtime = @filemtime($list_cache_file);
        
        // ✅ 실제 파일 개수 (Windows mtime 버그 대응)
        $actual_items = @scandir($dir);
        $actual_count = $actual_items ? count($actual_items) - 2 : 0;
        $list_cache = @json_decode(file_get_contents($list_cache_file), true);
        $cached_count = $list_cache['total_items'] ?? -1;
        
        if ($cache_mtime >= $dir_mtime && ($cached_count === -1 || $cached_count === $actual_count)) {
            // ✅ 버전 체크 추가
            $cache_version = $list_cache['version'] ?? 1;
            if ($cache_version >= $CACHE_VERSION && $list_cache && isset($list_cache['file_list'], $list_cache['dir_list'], $list_cache['jpg_list'], $list_cache['title_list'], $list_cache['dirinfo'])
                && (!$is_root || !empty($list_cache['perm_full'])) // ★ (2026-10-30) 루트는 권한 거르기 전 전체 목록으로 만든 캐시만
                // ★ (2026-11-04 12:41) 'N 기준: 올린 시각'인데 예전 캐시(책 항목에 ctime 없음)면 한 번 다시 만듦 — 안 그러면 열 때마다 카드마다 파일 시각을 다시 물었음(기본 '파일 날짜'면 그대로 씀)
                && !(get_app_settings('new_badge_basis', 'file') === 'added' && (function ($fl) { foreach ((array)$fl as $e) { if (is_array($e) && isset($e['totalpage'])) return !array_key_exists('ctime', $e); } return false; })($list_cache['file_list']))) {
                $file_list = $list_cache['file_list'];
                $dir_list = $list_cache['dir_list'];
                $jpg_list = $list_cache['jpg_list'];
                $title_list = $list_cache['title_list'];
                $dirinfo = $list_cache['dirinfo'];
                $use_list_cache = true;
                $_cache_debug = 'cache_used'; // 캐시 사용됨
            } else {
                $_cache_debug = 'cache_invalid'; // 캐시 형식 오류
            }
        } else {
            $_cache_debug = 'cache_old'; // 캐시가 오래됨
        }
    }
    
    $_time_cache_read = round((microtime(true) - $_t1) * 1000, 2);
    
    
    // ✅ [I/O 최적화] 캐시 유효하면 하위 폴더 캐시 체크 완전 스킵!
    // - 기존: 캐시 사용해도 모든 하위 폴더 .folder_cache.json 체크 (폴더 100개 = 300+ I/O)
    // - 개선: 캐시 유효하면 추가 I/O 0회
    // - 하위 폴더 캐시는 해당 폴더 직접 방문 시 또는 "폴더 캐시 재생성" 시 갱신
    
    // ✅ 캐시가 없거나 오래됐으면 새로 생성
    if (!$use_list_cache) {
    // ★ (2026-11-03 10:46) 목록 만들기를 함수 하나로(mc_build_list_cache) — 배경 갱신·일괄 생성도 같은 함수를 써서 결과가 똑같게(전엔 따로 만든 캐시를 화면이 다시 만들었음)
    $_t_build = microtime(true);
    list($file_list, $dir_list, $jpg_list, $title_list, $dirinfo) = mc_build_list_cache($dir, $list_cache ?? null);
    $_time_build = round((microtime(true) - $_t_build) * 1000, 2); // ★ (2026-11-03 10:51) 목록 만들기 시간(주석에 표시)
    // ★ (2026-11-03 10:50) 배경 갱신에 '바뀐 폴더 있음' 알림(검색 색인·압축 정보를 다음 갱신 때 새로)
    // ★ (2026-11-03 11:33) 저장됐고 책·폴더 이름이 예전 캐시와 다를 때만 — 앱이 만든 정보 파일 때문에 다시 만든 경우까지 알려 10분마다 전체 색인을 다시 만들었음
    $__names = function ($c) { if (!is_array($c)) return null; $n = []; foreach (['file_list', 'dir_list', 'title_list', 'jpg_list'] as $__k) foreach ((is_array($c[$__k] ?? null) ? $c[$__k] : []) as $__e) $n[] = $__k . ':' . (is_array($__e) ? (string)($__e['name'] ?? '') . (($__k === 'file_list' && substr((string)($__e['name'] ?? ''), -10) !== '_imgfolder') ? '|' . (int)($__e['time'] ?? 0) . '|' . (int)($__e['size'] ?? 0) : '') : (string)$__e); sort($n); return $n; }; // ★ (2026-11-04 01:50) 위 mc_build_list_cache 의 $__nm 과 같은 기준(책 파일 시각·크기 포함)
    if (!empty($GLOBALS['mc_list_cache_written']) && $__names($list_cache ?? null) !== $__names(['file_list' => $file_list, 'dir_list' => $dir_list, 'title_list' => $title_list, 'jpg_list' => $jpg_list])) @touch(__DIR__ . '/src/bg_dirty');
    } // ✅ if (!$use_list_cache) 닫기
    
    // ★ (2026-10-30) 루트: 그룹 권한이 없는 최상위 폴더는 목록에서 뺌(캐시 사용 여부와 상관없이 매번)
    if ($is_root) {
        $__mc_allow = function ($n) { return mc_path_allowed('/' . (string)$n); };
        $dir_list = array_values(array_filter($dir_list, $__mc_allow));
        $title_list = array_values(array_filter($title_list, $__mc_allow));
        $file_list = array_values(array_filter($file_list, function ($f) use ($__mc_allow) {
            $n = is_array($f) ? (string)($f['name'] ?? '') : (string)$f;
            if (substr($n, -10) === '_imgfolder') $n = substr($n, 0, -10);
            return $__mc_allow($n);
        }));
    }
    
    $_t2 = microtime(true);

    // 정렬 - 기본 순서: 숫자 → 영문 → 한글 (ASC)
    natsort($dir_list);
    natsort($title_list);
    natsort($jpg_list);
    
    $jpg_list = array_values($jpg_list);
    $dir_list = array_values($dir_list);
    $title_list = array_values($title_list);

    // ★ (2026-11-03 09:09) 기억한 정렬(쿠키)이 이 화면에 없는 칩이면 기본(이름)으로 — 파일 화면엔 '파일수', 폴더만 있는 화면엔 '크기' 칩이 없음.
    //   폴더만 있는 곳에서 '파일수'를 고른 뒤 파일 폴더를 열면 파일이 정렬되지 않은 채(읽은 순서) 나오고 켜진 칩도 없었음. 주소에 sort 가 있으면 그대로
    if (!isset($_GET['sort']) && isset($__mc_sort_def)) {
        if ($file_list && strncmp($__mc_sort_def, 'count', 5) === 0) $__mc_sort_def = 'nameasc';
        elseif (!$file_list && strncmp($__mc_sort_def, 'size', 4) === 0) $__mc_sort_def = 'nameasc';
    }
    // 파일 리스트 정렬(구조 유지)
    $sort_mode = validate_sort_mode(get_param('sort', 'string', $__mc_sort_def ?? 'nameasc'));
    if ($sort_mode === 'nameasc') {
        usort($file_list, function($a, $b) { return strnatcasecmp($a['name'], $b['name']); });
    } elseif ($sort_mode === 'namedesc') {
        usort($file_list, function($a, $b) { return strnatcasecmp($b['name'], $a['name']); });
    } elseif ($sort_mode === 'timeasc') {
        usort($file_list, function($a, $b) { return ($a['time'] <=> $b['time']); });
    } elseif ($sort_mode === 'timedesc') {
        usort($file_list, function($a, $b) { return ($b['time'] <=> $a['time']); });
    } elseif ($sort_mode === 'sizeasc') {
        usort($file_list, function($a, $b) { return ($a['size'] <=> $b['size']); });
    } elseif ($sort_mode === 'sizedesc') {
        usort($file_list, function($a, $b) { return ($b['size'] <=> $a['size']); });
    }
    
    $_time_after_cache = round((microtime(true) - $_t2) * 1000, 2);  

    // ✅ $search_no_results 초기화 (실제 필터링은 3375행 부근에서 처리)
    $search_no_results = false;

    // 페이징 - 폴더/파일에 따라 다른 maxview 사용
    // 모바일 체크
    $is_mobile = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT']);
    
    if ($is_mobile) {
        // 모바일용 설정 (항상 모바일 값으로 덮어쓰기)
        $maxview_folder = get_app_settings('maxview_folder_mobile', 30);
        $maxview_file = get_app_settings('maxview_file_mobile', 30);
    }
    // PC는 config.php에서 이미 로드됨
    
    // ✅ 파일이 하나라도 있으면 maxview_file 사용, 폴더만 있을 때만 maxview_folder 사용
    // robots.txt는 실제 파일이 아니므로 제외, _imgfolder는 표시되는 콘텐츠이므로 포함
    $real_file_count = 0;
    foreach ($file_list as $f) {
        $fname = is_array($f) ? $f['name'] : $f;
        if ($fname !== 'robots.txt') {
            $real_file_count++;
        }
    }
    $has_files = $real_file_count > 0;
    $has_folders = (count($dir_list) + count($title_list)) > 0;
    
    if ($has_files) {
        // 파일이 있으면 (파일만 또는 폴더+파일 혼합) → maxview_file 사용
        $current_maxview = (int)$maxview_file;
    } else {
        // 폴더만 있을 때 → maxview_folder 사용
        $current_maxview = (int)$maxview_folder;
    }
    
    $maxlist = count($file_list) + count($title_list) + count($dir_list);
    $paging = validate_page(get_param('page', 'int', 0));
    $paging = min($paging, max(0, (int)ceil($maxlist / max(1, (int)$current_maxview)) - 1)); // ★ (2026-11-02 11:34) 범위 밖 쪽(항목이 줄어든 뒤 옛 링크 등)이면 마지막 쪽으로 — 전에는 빈 목록
    $startview = $paging * $current_maxview;
    $endview = min($startview + $current_maxview, $maxlist);
    $updir = "";
} else {
	// ★ (2026-11-05 23:45) 예전엔 여기서 문구를 바로 찍어 화면 맨 위(로고보다 위)에 떴음 → 상태만 정하고 목록 자리에 안내 상자로(아래 $mc_dir_err)
	//   base = 콘텐츠 폴더 경로 자체를 못 읽음(설정 경로가 없거나 권한 없음) / sub = 그 안의 폴더가 없음(지워짐·옮겨짐·예전 링크)
	$mc_dir_err = (is_dir($base_dir) && is_readable($base_dir)) ? 'sub' : 'base';
	$mc_dir_err_gone = ($mc_dir_err === 'base' && !is_dir($base_dir)); // ★ (2026-11-06 00:20) 폴더 자체가 없음(지움·이름 바꿈·NAS 끊김) / 아니면 있는데 못 읽음(권한) — 관리자 안내를 나눔
	// ★ (2026-11-02 08:47) 없는(옮겨지거나 지운) 폴더를 옛 링크로 열면 아래에서 쓰는 값이 비어 0 으로 나누기(500 오류)였음 → 빈 목록으로
	$file_list = []; $title_list = []; $dir_list = []; $jpg_list = []; $recent = $recent ?? [];
	$maxlist = 0; $current_maxview = max(1, (int)($maxview_folder ?? 20)); $paging = 0; $startview = 0; $endview = 0;
	$updir = ''; $has_files = false; $has_folders = false; $is_root = false; $search_no_results = false;
}

?>

<?php

// 검색어 리스트 쿼리 (복합어는 구문만, 단일어는 개별 추가)
$search_terms = [];

// ✅ 원본 검색어를 그대로 추가
if (!empty($q)) {
    $search_terms[] = $q;
}

// 띄어쓰기 확인
$has_space = (strpos($q, ' ') !== false);

// ✅ 정방향 번역어 처리
if (!empty($translated_q)) {
    if ($has_space) {
        // 복합어: 조합된 구문만 추가
        $combinations = generateSearchCombinations($q, $translated_q);
        foreach ($combinations as $combo) {
            $search_terms[] = $combo;
        }
    } else {
        // 단일어: 각 번역어를 개별 추가
        if (is_array($translated_q)) {
            foreach ($translated_q as $word) {
                if (!empty($word)) {
                    $search_terms[] = strtolower(trim($word));
                }
            }
        } else {
            $search_terms[] = strtolower($translated_q);
        }
    }
}

// ✅ 역방향 번역어 처리
if (!empty($reverse_translated_q)) {
    if ($has_space) {
        // 복합어: 조합된 구문만 추가
        $combinations = generateSearchCombinations($q, $reverse_translated_q);
        foreach ($combinations as $combo) {
            $search_terms[] = $combo;
        }
    } else {
        // 단일어: 각 번역어를 개별 추가
        if (is_array($reverse_translated_q)) {
            foreach ($reverse_translated_q as $word) {
                if (!empty($word)) {
                    $search_terms[] = trim($word);
                }
            }
        } else {
            $search_terms[] = $reverse_translated_q;
        }
    }
}

// 중복 제거
$search_terms = array_unique($search_terms);

// ✅ 전역 변수에 모든 검색어 저장 (한글 + 영어) - 하이라이트용
$GLOBALS['all_highlight_terms'] = $search_terms;

// ✅ 디버그 로그
if (!empty($search_terms)) {
    // error_log("🔍 검색어 목록: " . implode(', ', $search_terms));
}

$filtered_file_list = [];
$basename_map = [];

foreach ($file_list as $file) {
    $name = is_array($file) ? $file['name'] : $file;
    
    // ✅ robots.txt 제외
    if ($name === 'robots.txt') continue;
    
    // ★ (2026-11-02 10:03) 이미지 폴더('이름_imgfolder')는 접미사만 떼기 — 확장자로 보고 자르면 '작품 Vol.2' 가 '작품 Vol' 이 되어 검색에 안 걸렸음
    $basename = (substr($name, -10) === '_imgfolder') ? substr($name, 0, -10) : pathinfo($name, PATHINFO_FILENAME);
    
    // ✅ 검색어가 있으면 필터링
    if (!empty($search_terms)) {
        $match_found = false;
        
        // 파일명도 NFC 정규화
        $basename_normalized = normalize_korean($basename);
        
        // 각 검색어 중 하나라도 파일명에 있으면 매치
        foreach ($search_terms as $term) {
            if (empty($term)) continue;
            
            // 검색어도 NFC 정규화
            $term_normalized = normalize_korean($term);
            
            // 대소문자 구분 없이 검색
            if (mb_stripos($basename_normalized, $term_normalized, 0, 'UTF-8') !== false) {
                $match_found = true;
                break; // 하나라도 매칭되면 OK
            }
        }
        
        // 어떤 검색어도 매칭 안 되면 제외
        if (!$match_found) {
            continue;
        }
    }
    
    // 중복 제거 (전체 파일명 기준 - 같은 이름 다른 확장자 zip/rar/7z는 각각 유지)
    if (isset($basename_map[$name])) continue;
    $filtered_file_list[] = is_array($file) ? $file : ['name' => $file];
    $basename_map[$name] = true;
}

$file_list = $filtered_file_list;

// ✅ 검색어 필터링 후 페이지네이션 재계산
if (!empty($search_terms)) {
    if ($is_root) {
        // ✅ 메인페이지: 폴더도 검색어로 필터링
        $dir_list = array_values(array_filter($dir_list, function($folder) use ($search_terms) {
            $folder_normalized = mb_strtolower(normalize_korean($folder), 'UTF-8');
            foreach ($search_terms as $term) {
                if (empty($term)) continue;
                $term_normalized = mb_strtolower(normalize_korean($term), 'UTF-8');
                if (mb_strpos($folder_normalized, $term_normalized, 0, 'UTF-8') !== false) {
                    return true;
                }
            }
            return false;
        }));
        
        $title_list = array_values(array_filter($title_list, function($folder) use ($search_terms) {
            $folder_normalized = mb_strtolower(normalize_korean($folder), 'UTF-8');
            foreach ($search_terms as $term) {
                if (empty($term)) continue;
                $term_normalized = mb_strtolower(normalize_korean($term), 'UTF-8');
                if (mb_strpos($folder_normalized, $term_normalized, 0, 'UTF-8') !== false) {
                    return true;
                }
            }
            return false;
        }));
        
        // 검색 결과 없음 플래그
        $search_no_results = (count($dir_list) + count($title_list) + count($file_list)) === 0;
        
        $maxlist = count($file_list) + count($title_list) + count($dir_list);
    } else {
        // ✅ 하위 폴더: 기존처럼 파일만 표시
        $title_list = [];
        $dir_list = [];
        $maxlist = count($file_list);
    }
    
    $endview = min($startview + $current_maxview, $maxlist);
    
    // startview가 범위를 벗어나면 첫 페이지로
    if ($startview >= $maxlist && $maxlist > 0) {
        $paging = 0;
        $startview = 0;
        $endview = min($current_maxview, $maxlist);
    }
}

// ✅ 디버그 로그
// error_log("✅ 검색 결과: " . count($file_list) . "개 파일");

?>

<?php
// ★ (2026-10-30) 선 아이콘(Lucide 모양, ISC 라이선스) — 색은 글자색(currentColor)을 따름: 버튼마다 색 있는/없는 아이콘을 CSS 색으로 정함
if (!function_exists('mc_ui_icon')) {
function mc_ui_icon($name, $size = 20, $sw = 1.9) {
    static $P = [
        'file-search' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h4"/><path d="M14 2v5h5"/><path d="M14 2l5 5v3"/><circle cx="16.5" cy="16.5" r="3"/><path d="m21 21-2.4-2.4"/>',
        'library' => '<path d="m16 6 4 14"/><path d="M12 6v14"/><path d="M8 8v12"/><path d="M4 4v16"/>',
        'bookmark' => '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/>',
        'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'monitor-off' => '<path d="M17 17H4a2 2 0 0 1-2-2V5c0-1.5 1-2 1-2"/><path d="M22 15V5a2 2 0 0 0-2-2H9"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="m2 2 20 20"/>',
        'layout-grid' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
        'list' => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
        'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'folder-plus' => '<path d="M12 10v6"/><path d="M9 13h6"/><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'tag' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1"/>',
        'ellipsis' => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'package' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'play' => '<path d="M6 3l14 9-14 9z" fill="currentColor"/>',
        'star' => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',   // ★ (2026-10-31) 즐겨찾기
        'library-big' => '<rect width="8" height="18" x="3" y="3" rx="1"/><path d="M7 3v18"/><path d="M20.4 18.9c.2.5-.1 1.1-.6 1.3l-1.9.7c-.5.2-1.1-.1-1.3-.6L11.1 5.1c-.2-.5.1-1.1.6-1.3l1.9-.7c.5-.2 1.1.1 1.3.6Z"/>'   // ★ (2026-10-31) 서재
    ];
    if (!isset($P[$name])) return '';
    $z = (int)$size;
    return '<svg class="mc-ico mc-ico-' . $name . '" width="' . $z . '" height="' . $z . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . (float)$sw . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $P[$name] . '</svg>';
}
}
/**
 * ★ (2026-10-31) 폴더 카드 개수 칩(시안 A — 펜닐 선택): 숫자 + 단위(권·폴더·편·장), 종류별 연한 색
 *   예전 노란 배지(📦480 📁4)는 무엇의 개수인지 안 보이고, 하위 폴더 아이콘이 이름 앞 폴더 아이콘과 같아 헷갈렸음
 *   이름 아래 줄(그리드·목록 모두)에 놓고, 마우스를 올리면 풀어 쓴 설명. 보이는 조건은 예전 배지와 같음
 *   $kind: 'zip'(압축) · 'pdf' · 'epub' · 'txt'
 */
if (!function_exists('mc_folder_chips')) {
function mc_folder_chips($kind, $books, $videos, $dirs, $images, $tail = '') {
    static $I = [
        'book' => '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
        'file' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'folders' => '<path d="M20 17a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3.9a2 2 0 0 1-1.69-.9l-.81-1.2a2 2 0 0 0-1.67-.9H8a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2Z"/><path d="M2 8v11a2 2 0 0 0 2 2h14"/>',
        'film' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18"/><path d="M3 7.5h4"/><path d="M3 12h18"/><path d="M3 16.5h4"/><path d="M17 3v18"/><path d="M17 7.5h4"/><path d="M17 16.5h4"/>',
        'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
    ];
    $chip = function ($cls, $ico, $label, $title) use ($I) {
        return '<span class="mc-fchip ' . $cls . '" title="' . h($title) . '"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $I[$ico] . '</svg>' . h($label) . '</span>';
    };
    // 1개면 단수형 문구(영어 '1 vol' — 예전엔 '1 vols'), 그 밖은 복수형
    $t = function ($key, $count, $n) { return __(((int)$count === 1) ? $key . '_one' : $key, $n); };
    $out = '';
    if ($books > 0) {
        $n = number_format((int)$books);
        $tk = ['pdf' => 'mcf_t_pdf', 'epub' => 'mcf_t_epub', 'txt' => 'mcf_t_txt'][$kind] ?? 'mcf_t_zip';
        $out .= $chip('mc-fc-book', ($kind === 'pdf' || $kind === 'txt') ? 'file' : 'book', $t('mcf_books', $books, $n), $t($tk, $books, $n));
    }
    if ($videos > 0) { $n = number_format((int)$videos); $out .= $chip('mc-fc-vid', 'film', $t('mcf_videos', $videos, $n), $t('mcf_t_videos', $videos, $n)); }
    if ($dirs > 0) { $n = number_format((int)$dirs); $out .= $chip('mc-fc-dir', 'folders', $t('mcf_dirs', $dirs, $n), $t('mcf_t_dirs', $dirs, $n)); }
    if ($images > 0 && $books == 0 && $videos == 0 && $dirs == 0) { $n = number_format((int)$images); $out .= $chip('mc-fc-img', 'image', $t('mcf_images', $images, $n), $t('mcf_t_images', $images, $n)); }
    // 줄바꿈 + 칩 줄(칩이 없어도 줄 높이는 유지 — 같은 줄 카드 높이가 들쭉날쭉하지 않게)
    return '<br class="mc-fbr"><span class="mc-fchips">' . $out . $tail . '</span>'; // ★ (2026-11-04 00:01) $tail = N(새 파일) 딱지 — 칩 줄 안에서 같은 높이로 나란히
}
}
?>
	<?php // ★ (2026-10-30) 시안 E — 머리 줄: [로고][탐색·책장·북마크][계정] (로그인 관련 버튼·언어는 계정 메뉴 안) ?>
	<div class="mc-e-head">
	<div class="mc-topbar">
	<div class="mc-topbar-logo">
<?php // ★ (2026-11-03 22:32) [로고][로고 문구][버전] 한 줄(FileStation 처럼) — 올린 이미지가 있으면 그 이미지, 없으면 기본 로고 ?>
		<div style="font-family: 'Gugi'; font-size:2.5em;"><?php echo mc_brand_logo($_branding, 'index.php' . $bidx_query); ?><span class="mc-ver"><?php echo MYCOMIX_VERSION; ?></span></div>

<?php // 다중 폴더 선택 - 아래 [전체파일] 옆에 드롭다운으로 표시
// 여기서는 제거하고 아래로 이동
?>
	</div>
	<div class="mc-topbar-tools" id="mcTopTools">
<?php
$bookmark_arr = array();
$bookmark_title = array();
$bookmark_mark = array();
$autosave_arr = array();
$autosave_title = array();
$autosave_mark = array();
$favorites_arr = array();
// ✅ [A] 북마크/자동저장/즐겨찾기 항목의 실제 파일 존재 확인(고아 항목 표시용).
//    상대경로 키 + 항목별 bidx로 base_dir을 골라 확인. 같은 키 반복 조회는 정적 캐시로 1회만 stat.
if (!function_exists('mc_entry_exists')) {
    function mc_entry_exists($rel_path, $entry_bidx = null) {
        global $base_dirs, $current_bidx;
        static $_cache = [];
        $rel = trim(str_replace('\\', '/', (string)$rel_path), '/');
        if ($rel === '') return true; // 알 수 없으면 존재로 간주(오탐 방지)
        $b = ($entry_bidx === null) ? (int)$current_bidx : (int)$entry_bidx;
        $ck = $b . '|' . $rel;
        if (isset($_cache[$ck])) return $_cache[$ck];
        $base = $base_dirs[$b] ?? null;
        if (!$base) return $_cache[$ck] = true; // base를 못 찾으면 존재로 간주(드라이브 미연결 등 오탐 방지)
        $full = rtrim($base, '/\\') . '/' . $rel;
        return $_cache[$ck] = (@file_exists($full));
    }
}
$favorites_title = array();
$favorites_mark = array();
/** ★ (2026-11-06 00:50) 기록 목록의 키 → 경로("bidx|/경로" 형식이면 앞 번호를 떼고, 값에 bidx 가 없으면 그 번호를 넣음 — bootstrap.php mc_rec_split) */
if (!function_exists('mc_rec_titles_split')) {
    function mc_rec_titles_split(array &$titles, array &$marks) {
        foreach ($titles as $__i => $__k) {
            [$__b, $__p] = mc_rec_split($__k, $marks[$__i] ?? null);
            if ($__p === (string)$__k) continue;
            $titles[$__i] = $__p;
            if (is_array($marks[$__i] ?? null) && !isset($marks[$__i]['bidx'])) $marks[$__i]['bidx'] = $__b;
        }
    }
}

// ✅ 함수 사용으로 통일 (전역 변수 의존 제거)
// EPUB/TXT 진행 정보 로드
$epub_progress_file = get_epub_progress_file();
$txt_progress_file = get_txt_progress_file();
$epub_progress = [];
$txt_progress = [];
if (is_file($epub_progress_file)) {
    $epub_progress = (function_exists('load_json_with_lock') ? load_json_with_lock($epub_progress_file) : (json_decode(file_get_contents($epub_progress_file), true) ?? [])); // ★ (2026-11-02 11:34) 공유 잠금
}
if (is_file($txt_progress_file)) {
    $txt_progress = (function_exists('load_json_with_lock') ? load_json_with_lock($txt_progress_file) : (json_decode(file_get_contents($txt_progress_file), true) ?? [])); // ★ (2026-11-02 11:34) 공유 잠금
}

// ✅ 함수 사용으로 통일 (전역 변수 의존 제거)
$_bookmark_file = get_bookmark_file();
$_autosave_file = get_autosave_file();
$_favorites_file = get_favorites_file();
if(is_file($_bookmark_file) === true){
	$bookmark_arr = (function_exists('load_json_with_lock') ? load_json_with_lock($_bookmark_file) : (json_decode(file_get_contents($_bookmark_file), true) ?? [])); // ★ (2026-11-02 11:34) 공유 잠금
	$bookmark_title = array_keys($bookmark_arr);
	$bookmark_mark = array_values($bookmark_arr);
	mc_rec_titles_split($bookmark_title, $bookmark_mark); // ★ (2026-11-06 00:50) "bidx|/경로" 키는 경로만(값의 bidx 는 그대로)
}
if(is_file($_autosave_file) === true){
	$autosave_arr = (function_exists('load_json_with_lock') ? load_json_with_lock($_autosave_file) : (json_decode(file_get_contents($_autosave_file), true) ?? [])); // ★ (2026-11-02 11:34) 공유 잠금
	// ✅ 옛 문자열 형식 호환: 문자열 값이면 배열 형식으로 정규화(행 렌더가 배열을 가정하므로)
	foreach($autosave_arr as $_ak => $_av){ if(!is_array($_av)){ $autosave_arr[$_ak] = ['bookmark'=>$_av, 'viewer'=>'toon', 'page_order'=>'0', 'bidx'=>0]; } }
	$autosave_title = array_keys($autosave_arr);
	$autosave_mark = array_values($autosave_arr);
	mc_rec_titles_split($autosave_title, $autosave_mark); // ★ (2026-11-06 00:50) 위와 같음
}
if(is_file($_favorites_file) === true){
	$favorites_arr = (function_exists('load_json_with_lock') ? load_json_with_lock($_favorites_file) : (json_decode(file_get_contents($_favorites_file), true) ?? [])); // ★ (2026-11-02 11:34) 공유 잠금
	$favorites_title = array_keys($favorites_arr);
	$favorites_mark = array_values($favorites_arr);
	mc_rec_titles_split($favorites_title, $favorites_mark); // ★ (2026-11-06 00:50) 위와 같음
}
?>
		<?php // ★ (2026-10-29) 즐겨찾기 책장(favorites.php) 바로가기 — 북마크 아이콘 왼쪽, 항상 표시(0개여도 진입 가능) ?>
		<span style="white-space:nowrap;"><?php // 책장·북마크 아이콘을 한 줄로(모바일 줄바꿈 방지) ?>
<?php // ★ (2026-11-05 23:15) '서재'(작품 탐색) 아이콘을 처음부터 서버가 그림 — 전에는 페이지 끝 스크립트(library_index_inject.php)가 나중에 끼워 넣어 늦게 나타나고 옆 ★·북마크가 밀렸음(펜닐 제보)
      //   같은 조건(그 파일이 있고 SQLite 사용 가능)·같은 모양·주소 — 끼워 넣는 스크립트는 이미 있으면 건너뜀(.mc-lib-browse-link)
if (is_file(__DIR__ . '/library_index_inject.php') && class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)): ?>
		<a class="btn btn-sm m-0 p-0 mr-2 align-middle mc-lib-browse-link" href="browse.php?bidx=<?php echo (int)($current_bidx ?? 0); ?>" title="<?php echo __h('lib_browse'); ?>" aria-label="<?php echo __h('lib_browse'); ?>" style="line-height:0;"><svg class="mc-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect width="8" height="18" x="3" y="3" rx="1"/><path d="M7 3v18"/><path d="M20.4 18.9c.2.5-.1 1.1-.6 1.3l-1.9.7c-.5.2-1.1-.1-1.3-.6L11.1 5.1c-.2-.5.1-1.1.6-1.3l1.9-.7c.5-.2 1.1.1 1.3.6Z"/></svg></a>
<?php endif; ?>
		<a class="btn btn-sm m-0 p-0 mr-2 align-middle mc-fav-shelf-link" href="favorites.php<?php echo $bidx_query; ?>" title="<?php echo __h('fav_shelf_title'); ?>" aria-label="<?php echo __h('fav_shelf_title'); ?>" style="line-height:0;">
		<?php echo mc_ui_icon('star', 24, 2); // ★ (2026-10-31) '즐겨찾기' — 별(서재 화면 버튼·즐겨찾기 화면 제목과 같은 별), 예전 책장 아이콘은 '서재'와 헷갈렸음 ?>
		</a>
<?php
	// ★ (2026-10-31) 🔖 북마크 — 예전엔 누르면 목록 위로 표(#bookmarkCollapse)가 펼쳐졌음 → 서재·즐겨찾기처럼 북마크 화면(bookmarks.php)으로 이동, 항상 표시(0개여도 진입 가능)
	//   펼침 표(#bookmarkCollapse)·bookmark_toggle() 은 지우지 않고 그대로 둠(뷰어에서 돌아올 때 갱신 코드가 참조) — 여는 버튼만 없어짐
?>
		<a class="btn btn-sm m-0 p-0 mc-bm-toggle" href="bookmarks.php<?php echo $bidx_query; ?>" title="<?php echo __h('index_bookmarks'); ?>" aria-label="<?php echo __h('index_bookmarks'); ?>">
		<?php echo mc_ui_icon('bookmark', 24, 2); // ★ (2026-10-30) 선 아이콘 ?>
		</a>
		</span>
<?php
// ★ (2026-10-30) 계정 메뉴 — 예전 로그인 줄의 [로그인 표시·관리자페이지·승인 대기·로그아웃·모든 기기 로그아웃·내 정보·2단계 인증]과 로고 옆 언어 배지를 한곳에(링크·동작 그대로)
$_mc_uid = (string)($_SESSION['user_id'] ?? '');
$_mc_initial = $_mc_uid !== '' ? mb_strtoupper(mb_substr($_mc_uid, 0, 1, 'UTF-8'), 'UTF-8') : '?';
$pending_users = [];
if (($_SESSION['user_group'] ?? '') === "admin") {
    foreach (load_users() as $uid => $udata) {
        if (($udata['status'] ?? 'active') === 'pending') $pending_users[] = $uid;
    }
}
?>
		<div class="mc-acct" id="mcAcct">
		<button type="button" class="mc-acct-btn" id="mcAcctBtn" aria-haspopup="true" aria-expanded="false" aria-controls="mcAcctMenu" title="<?php echo h(__('index_logged_in_as', $_mc_uid)); ?>" aria-label="<?php echo __h('index_account_menu'); ?>"><span class="mc-acct-initial"><?php echo h($_mc_initial); ?></span><?php if (count($pending_users) > 0): ?><span class="mc-acct-dot" aria-hidden="true"></span><?php endif; ?></button>
		<div class="mc-acct-menu" id="mcAcctMenu" hidden>
		<?php
		// 메뉴 글자 앞 이모지는 빼고(아이콘으로 대신) 글자만
		$_mc_lbl = function ($t) { $t = preg_replace('/^[^\p{L}\p{N}\[\(]+/u', '', (string)$t); return trim((string)$t); };
		$_mc_is_admin_u = (($_SESSION['user_group'] ?? '') === "admin");
		?>
		  <div class="mc-acct-head" title="<?php echo h(__('index_logged_in_as', $_mc_uid)); ?>">
		    <span class="mc-acct-avatar" aria-hidden="true"><?php echo h($_mc_initial); ?></span>
		    <?php // ★ (2026-10-31) '아이디님 환영합니다.' — 문구 안 %s 자리에 이름(굵게). 이름·문구 모두 이스케이프
		    $_mc_hi = explode("\x01", h(__('index_welcome_user', "\x01")), 2); ?>
		    <span class="mc-acct-id"><span class="mc-acct-hi"><?php echo $_mc_hi[0]; ?><span class="mc-acct-name"><?php echo h($_mc_uid); ?></span><?php echo $_mc_hi[1] ?? ''; ?></span><span class="mc-acct-role<?php echo $_mc_is_admin_u ? ' is-admin' : ''; ?>"><?php echo __h($_mc_is_admin_u ? 'index_role_admin' : 'index_role_user'); ?></span></span>
		  </div>
		  <?php if ($_mc_is_admin_u): ?>
		  <a class="mc-acct-item" href="admin.php"><?php echo mc_ui_icon('settings', 18); ?><span><?php echo h($_mc_lbl(__('index_admin_page'))); ?></span></a>
		  <?php if (count($pending_users) > 0): ?>
		  <a class="mc-acct-item mc-acct-warn pending-alert" href="admin.php#group" title="<?php echo __h('users_approve'); ?>"><?php echo mc_ui_icon('user-plus', 18); ?><span><?php echo h($_mc_lbl(__('index_pending_approval', count($pending_users)))); ?></span></a>
		  <?php endif; ?>
		  <?php endif; ?>
		  <a class="mc-acct-item" href="#" onclick="mcAcctClose(); openProfileModal(); return false;"><?php echo mc_ui_icon('user', 18); ?><span><?php echo h($_mc_lbl(__('index_my_profile'))); ?></span></a>
		  <a class="mc-acct-item" href="#" onclick="mcAcctClose(); openSettingsModal(); return false;"><?php echo mc_ui_icon('shield-check', 18); ?><span><?php echo h($_mc_lbl(__('index_2fa_settings'))); ?></span></a>
		  <?php foreach ((function_exists('get_available_langs') ? get_available_langs() : []) as $_lc => $_ln): if ($_lc === get_current_lang()) continue; ?>
		  <a class="mc-acct-item" href="<?php echo h(get_lang_switch_url($_lc)); ?>"><?php echo mc_ui_icon('globe', 18); ?><span><?php echo h($_ln); ?></span></a>
		  <?php endforeach; ?>
		  <div class="mc-acct-sep"></div>
		  <a class="mc-acct-item mc-acct-danger" href="login.php?mode=logout"><?php echo mc_ui_icon('log-out', 18); ?><span><?php echo h($_mc_lbl(__('index_logout'))); ?></span></a>
		  <?php if ($logout_all_devices_settings['enabled'] ?? false): ?>
		  <a class="mc-acct-item mc-acct-danger" href="login.php?mode=logout_all" onclick="return confirm(<?php echo h(js(str_replace('\\n', "\n", __('index_logout_all_confirm')))); /* ★ (2026-11-02 19:53) 문구 속 '\\n'(글자)을 줄바꿈으로 — js() 로 바꾸면서 \\n 이 글자 그대로 보였음 */ /* ★ (2026-11-02 19:04) 문구에 ' 가 들어가도 안 깨지게 */ ?>);"><?php echo mc_ui_icon('monitor-off', 18); ?><span><?php echo h($_mc_lbl(__('index_logout_all'))); ?></span></a>
		  <?php endif; ?>
		</div>
		</div>
<script>
// ★ (2026-10-30) 계정 메뉴 열고 닫기(바깥 누르기·Esc 로 닫힘)
(function () {
  var box = document.getElementById('mcAcct'), btn = document.getElementById('mcAcctBtn'), menu = document.getElementById('mcAcctMenu');
  if (!box || !btn || !menu) return;
  function set(open) { menu.hidden = !open; box.classList.toggle('open', open); btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  window.mcAcctClose = function () { set(false); };
  btn.addEventListener('click', function (e) { e.stopPropagation(); set(menu.hidden); });
  document.addEventListener('click', function (e) { if (!menu.hidden && !box.contains(e.target)) set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { set(false); try { btn.focus(); } catch (x) {} } });
})();
</script>
	</div>
	</div>
<script>
// ★ (2026-11-05) 머리 줄 로고 자동 맞춤(펜닐 요청) — 휴대폰 세로에서 버전(v2.5.0)과 오른쪽 아이콘 사이가 너무 붙어 보였음
//   실제 폭을 재서 [로고+버전] 오른쪽에 최소 간격(MIN)이 남지 않을 때만 로고 글자를 그만큼 줄임(넓은 화면은 그대로)
//   · 로고 글꼴(Gugi)이 늦게 받아져 폭이 바뀌어도·화면을 돌려도·관리자가 로고 문구를 길게 바꿔도 다시 맞춤
//   · 너무 작아지지 않게 하한(로고 글자 12px) — 스크립트가 안 돌면 예전 CSS 구간(420px·360px 이하) 그대로
(function () {
  var box = document.querySelector('.mc-e-head .mc-topbar-logo'); // 오른쪽 아이콘까지 그려진 뒤(머리 줄 끝)에서 잼
  var el = box && box.firstElementChild; if (!el) return;
  var BASE = el.style.fontSize || '2.5em', MIN = 32, MINTXT = 12, raf = 0; // MIN: 버전~아이콘 최소 간격(px), MINTXT: 로고 글자 최소 크기(px)
  function fit() {
    raf = 0;
    el.style.fontSize = BASE;
    el.classList.remove('mc-logo-clip'); el.style.maxWidth = '';
    var lk = el.querySelector('.logo-link'), e0 = parseFloat(getComputedStyle(el).fontSize) || 40;
    var ratio = lk ? (parseFloat(getComputedStyle(lk).fontSize) / e0) || 1 : 1; // 좁은 화면 CSS 가 로고 글자를 상자보다 작게(.58em 등) 둠
    var MINFS = Math.min(e0, MINTXT / ratio);
    for (var i = 0; i < 6; i++) {
      var avail = box.clientWidth - MIN, need = el.scrollWidth;
      if (avail <= 0 || need <= avail) break;
      var cur = parseFloat(getComputedStyle(el).fontSize) || 40;
      var next = Math.max(MINFS, Math.floor(cur * (avail / need) * 10) / 10);
      if (next >= cur) break;
      el.style.fontSize = next + 'px';
      if (next === MINFS) break;
    }
    // 가장 작게 해도 넘치면(관리자가 로고 문구를 아주 길게 바꾼 경우) 문구 끝을 '…'로 — 버전·아이콘과 겹치지 않게
    var room = box.clientWidth - MIN;
    if (room > 0 && el.scrollWidth > room) { el.style.maxWidth = room + 'px'; el.classList.add('mc-logo-clip'); }
  }
  function later() { if (!raf) raf = requestAnimationFrame(fit); }
  window.mcFitLogo = fit;
  fit();
  window.addEventListener('resize', later, { passive: true });
  window.addEventListener('orientationchange', later);
  window.addEventListener('load', later);
  try { if (document.fonts) { if (document.fonts.ready) document.fonts.ready.then(later); document.fonts.addEventListener && document.fonts.addEventListener('loadingdone', later); } } catch (e) {}
})();
</script>

<div class="collapse" id="bookmarkCollapse" width="100%" align="right" style="max-height: 50vh; overflow-y: auto;">
<style>
@media (max-width: 768px) {
    #bookmarkCollapse { text-align:left !important; }
    #bookmarkTable { width:100% !important; }
    #bookmarkTable td:first-child { white-space:nowrap; }
    #bookmarkTable td:last-child { text-align:left !important; width:100%; }
    #bookmarkTable td:last-child .btn { display:block !important; white-space:nowrap !important; overflow:hidden !important; text-overflow:ellipsis !important; max-width:100% !important; }
}
</style>
<table class="mb-2 mt-2" id="bookmarkTable">
<?php
$_svg_warn = '<svg width="1em" height="1em" viewBox="0 0 18 18" class="bi bi-exclamation-circle-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>';
$_svg_x = '<svg width="1em" height="1em" viewBox="0 0 18 18" class="bi bi-x-circle-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/></svg>';
$_autosave_max = (int)get_app_settings('max_autosave', 10);
?>
<tr id="autosaveCountRow"><td colspan="2"><small class="text-muted"><?php echo $_svg_warn; ?> <span id="autosaveCount"><?php echo count($autosave_arr); ?></span>/<?php echo $_autosave_max; ?></small></td></tr>
<?php
if(count($autosave_arr)>0){
for($count=0;$count < count($autosave_arr); $count++){
	$title_temp = explode("/", $autosave_title[$count]);
?>
<?php $_mc_miss = !mc_entry_exists($autosave_title[$count], $autosave_mark[$count]['bidx'] ?? null); ?>
	<tr class="border-bottom border-success<?php echo $_mc_miss ? ' mc-missing' : ''; ?>"<?php echo $_mc_miss ? ' title="파일 없음"' : ''; ?> data-fav-thumb="<?php echo h("thumb.php?file=" . encode_url($autosave_title[$count]) . "&bidx=" . ($autosave_mark[$count]['bidx'] ?? 0) . mc_file_cover_v_rel((int)($autosave_mark[$count]['bidx'] ?? 0), $autosave_title[$count])); /* ★ (2026-11-10 16:08 fix101) */ ?>" data-fav-title="<?php echo h(mark_display_title($title_temp[count($title_temp) - 1])); ?>" data-fav-url="<?php echo h("viewer.php?mode=toon&page_order=" . ($autosave_mark[$count]['page_order'] ?? '0') . "&file=" . encode_url($autosave_title[$count]) . "&bidx=" . ($autosave_mark[$count]['bidx'] ?? 0) . "#" . $autosave_mark[$count]['bookmark']); ?>"><td align=right style="white-space:nowrap;"><button class="btn btn-sm m-0 p-0"><?php echo $_svg_warn; ?></button><button class="btn btn-sm m-0 p-0" onclick="location.replace('bookmark.php?mode=delete_autosave&file=<?php echo encode_url($autosave_title[$count]) . '&bidx=' . (int)($autosave_mark[$count]['bidx'] ?? 0); /* ★ (2026-11-06 00:50) 그 기록의 콘텐츠 폴더(지우기가 콘텐츠 폴더별이라) */ ?>&token=<?php echo get_delete_token(); ?>');"><?php echo $_svg_x; ?></button></td><td><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.mcFavMarkAndSave&&mcFavMarkAndSave(this);window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./viewer.php?mode=toon&page_order=<?php echo $autosave_mark[$count]['page_order'] ?? '0'; /* ★ (2026-11-05) 보기 방식이 없는 아주 옛 기록이면 경고(Undefined array key) — 기본값 '0' */ ?>&file=<?php echo encode_url($autosave_title[$count]); ?>&bidx=<?php echo $autosave_mark[$count]['bidx'] ?? 0; ?>#<?php echo $autosave_mark[$count]['bookmark']; ?>'"><?php echo mark_display_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
	}
	}

$_bookmark_max = (int)get_app_settings('max_bookmark', 10);
?>
<tr id="bookmarkCountRow"><td colspan="2"><small class="text-muted">🔖 <span id="bookmarkCount"><?php echo count($bookmark_arr); ?></span>/<?php echo $_bookmark_max; ?></small></td></tr>
<?php
if(count($bookmark_arr)>0){
for($count=0;$count < count($bookmark_arr); $count++){
	$title_temp = explode("/", $bookmark_title[$count]);
?>
<?php $_mc_miss = !mc_entry_exists($bookmark_title[$count], is_array($bookmark_mark[$count]) ? ($bookmark_mark[$count]['bidx'] ?? null) : null); ?>
	<tr class="border-bottom border-light<?php echo $_mc_miss ? ' mc-missing' : ''; ?>"<?php echo $_mc_miss ? ' title="파일 없음"' : ''; ?> data-fav-thumb="<?php echo h("thumb.php?file=" . encode_url($bookmark_title[$count]) . "&bidx=" . (is_array($bookmark_mark[$count]) ? ($bookmark_mark[$count]['bidx'] ?? $current_bidx) : $current_bidx) . mc_file_cover_v_rel((int)(is_array($bookmark_mark[$count]) ? ($bookmark_mark[$count]['bidx'] ?? $current_bidx) : $current_bidx), $bookmark_title[$count])); /* ★ (2026-11-10 16:08 fix101) */ ?>" data-fav-title="<?php echo h(mark_display_title($title_temp[count($title_temp) - 1])); ?>" data-fav-url="<?php /* ★ (2026-10-30) 모바일 '열기'도 제목 버튼과 같은 주소(저장 위치·쪽 순서·항목 bidx) — 전에는 배열 형식이면 처음 쪽으로 열렸음 */ echo h(is_array($bookmark_mark[$count]) ? ("viewer.php?mode=toon&page_order=" . ($bookmark_mark[$count]['page_order'] ?? '0') . "&file=" . encode_url($bookmark_title[$count]) . "&bidx=" . ($bookmark_mark[$count]['bidx'] ?? 0) . "#" . ($bookmark_mark[$count]['bookmark'] ?? '')) : ("viewer.php?file=" . encode_url($bookmark_title[$count]) . $bidx_param . "#" . $bookmark_mark[$count])); ?>"><td align=right style="white-space:nowrap;"><button class="btn btn-sm m-0 p-0" onclick="location.replace('bookmark.php?mode=delete_bookmark&file=<?php echo encode_url($bookmark_title[$count]) . '&bidx=' . (is_array($bookmark_mark[$count]) ? (int)($bookmark_mark[$count]['bidx'] ?? 0) : (int)$current_bidx); /* ★ (2026-11-06 00:50) 위와 같음 */ ?>&token=<?php echo get_delete_token(); ?>');"><?php echo $_svg_x; ?></button></td><td><?php
	if(!is_array($bookmark_mark[$count])){
?><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.mcFavMarkAndSave&&mcFavMarkAndSave(this);window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./viewer.php?file=<?php echo encode_url($bookmark_title[$count]) . $bidx_param; ?>#<?php echo $bookmark_mark[$count]; ?>'"><?php echo mark_display_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
	} else {
?><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.mcFavMarkAndSave&&mcFavMarkAndSave(this);window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./viewer.php?mode=toon&page_order=<?php echo $bookmark_mark[$count]['page_order'] ?? '0'; /* ★ (2026-11-05) 옛 기록 경고 방지 */ ?>&file=<?php echo encode_url($bookmark_title[$count]); ?>&bidx=<?php echo $bookmark_mark[$count]['bidx'] ?? 0; ?>#<?php echo $bookmark_mark[$count]['bookmark']; ?>'"><?php echo mark_display_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
	}
	}
}

// EPUB 진행 목록  ※ 북마크 목록에서 전체 숨김(EPUB 뷰어 자체가 진행률/이어보기 제공). 필요시 'false && ' 제거
if(false && count($epub_progress) > 0){
?>
<?php /* 북마크 목록에선 EPUB 개수 카운트가 불필요하여 비활성화(필요시 주석 해제)
<tr id="epubCountRow"><td colspan="2"><small class="text-muted">📖 <span id="epubCount"><?php echo count($epub_progress); ?></span></small></td></tr>
*/ ?>
<?php
    foreach($epub_progress as $file_path => $progress_data){
        $title_temp = explode("/", $file_path);
        $percent = (int)($progress_data['percent'] ?? 0);
?>
	<tr class="border-bottom" style="border-color:#9b59b6 !important;"><td align=right style="white-space:nowrap;"><span class="badge badge-info" style="font-size:0.7em;"><?php echo $percent; ?>%</span><button class="btn btn-sm m-0 p-0" onclick="location.replace('bookmark.php?mode=delete_epub_progress&file=<?php echo encode_url($file_path) . $bidx_param; ?>&token=<?php echo get_delete_token(); ?>');"><?php echo $_svg_x; ?></button></td><td><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.mcFavMarkAndSave&&mcFavMarkAndSave(this);window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./epub_viewer.php?file=<?php echo encode_url($file_path) . $bidx_param; ?>'">📖 <?php echo mark_display_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
    }
}

// TXT 진행 목록  ※ 북마크 목록에서 전체 숨김(TXT 뷰어 자체가 진행률/이어보기 제공). 필요시 'false && ' 제거
if(false && count($txt_progress) > 0){
?>
<?php /* 북마크 목록에선 TXT 개수 카운트가 불필요하여 비활성화(필요시 주석 해제)
<tr id="txtCountRow"><td colspan="2"><small class="text-muted">📝 <span id="txtCount"><?php echo count($txt_progress); ?></span></small></td></tr>
*/ ?>
<?php
    foreach($txt_progress as $file_path => $progress_data){
        $title_temp = explode("/", $file_path);
        $percent = (int)($progress_data['percent'] ?? 0);
?>
	<tr class="border-bottom" style="border-color:#27ae60 !important;"><td align=right style="white-space:nowrap;"><span class="badge badge-success" style="font-size:0.7em;"><?php echo $percent; ?>%</span><button class="btn btn-sm m-0 p-0" onclick="location.replace('bookmark.php?mode=delete_txt_progress&file=<?php echo encode_url($file_path) . $bidx_param; ?>&token=<?php echo get_delete_token(); ?>');"><?php echo $_svg_x; ?></button></td><td><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.mcFavMarkAndSave&&mcFavMarkAndSave(this);window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./txt_viewer.php?file=<?php echo encode_url($file_path) . $bidx_param; ?>'">📝 <?php echo mark_display_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
    }
}

// ✅ 즐겨찾기 목록
$_fav_max = (int)get_app_settings('max_favorites', 50);
?>
<?php // ★ (2026-10-29) 즐겨찾기 개수·책장 링크 줄 제거 — 즐겨찾기는 상단 책장 아이콘(favorites.php)으로 이동 ?>
<?php // ★ (2026-10-29) 즐겨찾기 개별 행은 '즐겨찾기 책장(favorites.php)'으로 대체 — 여기서는 개수 + 책장 링크만 표시 ?>
</table>
</div>

<?php
// ★ (2026-10-30) 시안 E — 콘텐츠 폴더 탭 / 표지 배너(표지 없으면 큰 제목) / 이어 읽기
//   예전: 로그인 줄 · 표지 그림 · [경로][📦 개수][폴더 선택] · [~까지 읽음] 이 각각 다른 높이·간격으로 쌓였음
$_mc_root_view = empty(get_param('dir', 'path'));
if ($_mc_root_view) {
    // 모든 bidx의 zip_total 합산(예전과 같음)
    $zip_total = 0;
    foreach (array_keys($base_dirs) as $idx) {
        $total_file = __DIR__ . '/src/zip_total_' . $idx . '.json';
        if (is_file($total_file)) {
            $data = json_decode(@file_get_contents($total_file), true);
            if (isset($data['zip_total'])) {
                $zip_total += (int)$data['zip_total'];
            }
        }
    }
    // 하위 호환성: 기존 단일 파일도 체크
    if ($zip_total === 0) {
        $old_total_file = __DIR__ . '/src/zip_total.json';
        if (is_file($old_total_file)) {
            $data = json_decode(@file_get_contents($old_total_file), true);
            if (isset($data['zip_total'])) {
                $zip_total = (int)$data['zip_total'];
            }
        }
    }
    $_mc_count = $zip_total;
    $_mc_title = preg_replace('/^\[(.*)\]$/su', '$1', (string)__('index_all_files'));
    $_mc_parent_label = '';
} else {
    $_mc_count = count($file_list);
    $_mc_gd = rtrim(str_replace('\\', '/', (string)$getdir), '/');
    $_mc_pos = strrpos($_mc_gd, '/');
    $_mc_title = $_mc_pos === false ? $_mc_gd : substr($_mc_gd, $_mc_pos + 1);
    if ($_mc_title === '') $_mc_title = $_mc_gd;
    if ($_mc_title === '') $_mc_title = preg_replace('/^\[(.*)\]$/su', '$1', (string)__('index_all_files')); // ★ (2026-11-02 20:11) dir=/ (최상위 파일 검색 결과 링크)일 때 제목 빈칸 방지
    $_mc_parent_label = ($_mc_pos !== false && $_mc_pos > 0) ? substr($_mc_gd, 0, $_mc_pos) : ''; // 두 단계 이상일 때 위 경로
}
// 상위폴더 주소(예전 '상위폴더' 버튼과 같은 계산·같은 주소)
$_mc_up_url = '';
if (!$_mc_root_view && !(isset($is_root) && $is_root == true)) {
    $_mc_up = '';
    $_mc_arr = explode("/", $getdir);
    for ($i = 1; $i < count($_mc_arr) - 1; $i++) $_mc_up .= "/" . $_mc_arr[$i];
    $_mc_up_url = 'index.php?dir=' . encode_url($_mc_up) . '&page=' . validate_page(get_param('uppage', 'int', 0)) . $bidx_param;
}
$_mc_cover_shown = ($use_cover == "y" && !empty($getdir) && is_file($dir . "/[cover].jpg"));
$_mc_cover_url = $_mc_cover_shown ? 'thumb.php?file=' . encode_url($getdir) . '&type=cover&v=' . (int)@filemtime($dir . "/[cover].jpg") . $bidx_param : ''; // ★ (2026-11-04 09:44) 표지를 바꾸면 주소가 바뀌어 브라우저가 옛 그림(하루 캐시)을 쓰지 않게
$_mc_up_key = ($_mc_up_url === '') ? '' : ((int)$current_bidx . '|' . trim((string)$_mc_up, '/')); // ★ (2026-11-08 fix80) 위 폴더의 위치 기억 열쇠(콘텐츠 폴더 번호|폴더)
$_mc_up_btn = $_mc_up_url === '' ? '' : '<button type="button" class="mc-up-btn" data-mc-up="' . h($_mc_up_key) . '" data-up-url="' . h($_mc_up_url) . '" onclick="if(window.mcDirUp){mcDirUp(this);}else{location.replace(' . h(json_encode($_mc_up_url)) . ');}" title="' . __h('ui_parent_folder') . '" aria-label="' . __h('ui_parent_folder') . '">' . mc_ui_icon('chevron-left', 20, 2.4) . '</button>';
?>
<?php if (count($base_dirs) > 1): // 콘텐츠 폴더 탭(2개 이상일 때만, 넘치면 가로로 밀어서 보기) ?>
<nav class="mc-tabs" id="mcTabs" aria-label="<?php echo __h('index_content_folders'); ?>">
<?php foreach ($base_dirs as $idx => $bdir): $_on = ($idx === $current_bidx); ?>
  <a class="mc-tab<?php echo $_on ? ' active' : ''; ?>" href="index.php?bidx=<?php echo (int)$idx; ?>"<?php echo $_on ? ' aria-current="page"' : ''; ?>><?php echo mc_ui_icon('folder', 16); ?><?php echo h(basename($bdir)); ?></a>
<?php endforeach; ?>
</nav>
<script>
// ★ (2026-11-05) 콘텐츠 폴더 탭이 많아 가로로 넘칠 때 — 들어온 폴더의 탭이 보이게 탭 줄만 가로로 밀어 둠(펜닐 요청)
//   전에는 늘 맨 왼쪽에서 시작해 뒤쪽 폴더로 들어가면 선택된 탭이 화면 밖에 있었음
//   · 이 탭에서 마지막으로 본 탭 줄 위치를 먼저 되살림(탭을 옮길 때마다 줄이 왔다 갔다 하지 않게) → 그래도 안 보이면 보일 만큼만 밂
//   · 페이지 세로 스크롤은 건드리지 않음(scrollIntoView 안 씀) · 글꼴이 늦게 와 탭 폭이 바뀌면 한 번 더 맞춤(사용자가 탭 줄을 만지면 그만)
(function () {
  var bar = document.getElementById('mcTabs'); if (!bar) return;
  var KEY = 'mc_tabs_x', touched = false;
  function fit(useSaved) {
    if (touched) return;
    var a = bar.querySelector('.mc-tab.active'); if (!a) return;
    var max = bar.scrollWidth - bar.clientWidth; if (max <= 0) return;
    if (useSaved) { try { var v = parseInt(sessionStorage.getItem(KEY), 10); if (v > 0) bar.scrollLeft = Math.min(v, max); } catch (e) {} }
    var x = bar.scrollLeft, pad = 24, br = bar.getBoundingClientRect(), ar = a.getBoundingClientRect();
    var l = ar.left - br.left - bar.clientLeft + x, r = l + ar.width; // 탭 줄 안에서의 위치(지금 밀린 만큼 더함)
    if (r > x + bar.clientWidth - pad) x = r - bar.clientWidth + pad;
    if (l < x + pad) x = l - pad;
    bar.scrollLeft = Math.max(0, Math.min(x, max));
  }
  fit(true);
  ['pointerdown', 'touchstart', 'wheel'].forEach(function (t) { bar.addEventListener(t, function () { touched = true; }, { passive: true }); });
  var save = function () { try { sessionStorage.setItem(KEY, String(Math.round(bar.scrollLeft))); } catch (e) {} };
  bar.addEventListener('scroll', save, { passive: true });
  bar.addEventListener('click', save, true);
  window.addEventListener('load', function () { fit(false); });
  try { if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { fit(false); }); } catch (e) {}
})();
</script>
<?php endif; ?>

<?php if ($_mc_cover_shown): ?>
<div class="mc-banner">
  <img class="mc-banner-bg" src="<?php echo h($_mc_cover_url); ?>" alt="" aria-hidden="true" loading="lazy">
  <img class="mc-banner-img" src="<?php echo h($_mc_cover_url); ?>" alt="" loading="lazy">
  <div class="mc-banner-band">
    <?php echo $_mc_up_btn; ?>
    <div class="mc-title-wrap">
      <?php if ($_mc_parent_label !== ''): ?><div class="mc-parent"><?php echo h($_mc_parent_label); ?></div><?php endif; ?>
      <div class="mc-title-line"><span class="mc-title"><?php echo h($_mc_title); ?></span><span class="mc-count"><?php echo mc_ui_icon('package', 14); ?><?php echo number_format((int)$_mc_count); ?></span></div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="mc-titlebar">
  <?php echo $_mc_up_btn; ?>
  <div class="mc-title-wrap">
    <?php if ($_mc_parent_label !== ''): ?><div class="mc-parent"><?php echo h($_mc_parent_label); ?></div><?php endif; ?>
    <div class="mc-title-line"><span class="mc-title"><?php echo h($_mc_title); ?></span><span class="mc-count"><?php echo mc_ui_icon('package', 14); ?><?php echo number_format((int)$_mc_count); ?></span></div>
  </div>
</div>
<?php endif; ?>
<?php
// ★ (2026-11-05 23:30) '이어 읽기'는 이 콘텐츠 폴더(bidx)의 기록만 — 열쇠 '번호|폴더 경로'(viewer.php 가 저장)
//   예전 형식(폴더 경로만) 기록은 이 폴더에 그 파일이 실제로 있을 때만(다른 콘텐츠 폴더의 같은 경로 /새폴더 에서 읽은 책이 여기에도 떠서 열면 그림이 안 나왔음 — 펜닐 제보)
$__rc = '';
if ($getdir !== '' && isset($recent) && is_array($recent)) {
    $__rv = $recent[(int)$current_bidx . '|' . $getdir] ?? null;
    if (is_string($__rv) && $__rv !== '') $__rc = $__rv;
    else {
        $__rv = $recent[$getdir] ?? null;
        if (is_string($__rv) && $__rv !== '' && strpos($__rv, '/') === false && strpos($__rv, '\\') === false) {
            $__rn = (substr($__rv, -10) === '_imgfolder') ? substr($__rv, 0, -10) : $__rv;
            if (file_exists($dir . '/' . $__rn)) $__rc = $__rv;
        }
    }
}
if ($__rc !== ''):
    // ★ (2026-11-03 14:11) 이미지 폴더 책은 예전 기록에 꼬리 '_imgfolder' 가 붙어 있어 없는 파일로 열려(옛 뷰어 빈 화면) — 실제 폴더 이름으로. 이름 표시도 같게
    if (substr($__rc, -10) === '_imgfolder') $__rc = substr($__rc, 0, -10); ?>
<button type="button" id="mcContinue" class="mc-continue" onclick="location.href='./viewer.php?file=<?php echo encode_url($getdir."/".$__rc) . $bidx_param; ?>'" title="<?php echo h(__('index_read_upto', $__rc)); ?>"><?php echo mc_ui_icon('play', 14, 1.5); ?><b><?php echo __h('index_continue_reading'); ?></b><span class="mc-continue-name"><?php echo h($__rc); ?></span></button>
<?php elseif ($getdir !== ''): ?><span id="mcContinue" hidden></span><?php // ★ (2026-11-03 14:38) 자리표시 — 뷰어에서 돌아오면 새로고침 없이 '이어 읽기'를 새로 바꿔 넣는 자리 ?>
<?php endif; ?>
	</div>
<?php $_t_cards = microtime(true); // ★ (2026-11-03 10:51) 카드 그리기 시간 재기(아래 '페이지 생성 시간' 주석) ?>
<div class="grid">

<!-- 루트 폴더용 wrapper (모바일에서 순서 변경) -->
<div class="root-folder-wrapper">
<!--// 해당 $dir 없을때 검색기능 -->
<?php if (empty(get_param('dir', 'path')) && $mc_dir_err === ''): /* ★ (2026-11-06 00:10) 콘텐츠 폴더를 열 수 없으면 검색창 없음(펜닐 제보: 안내 아래에서 검색이 되고 다른 곳 결과 칩이 떠 헷갈렸음) */ ?>
<div class="text-center root-search-section">
    <form method="get" action="index.php" class="form-inline justify-content-center mb-2">
        <input type="hidden" name="bidx" value="<?php echo $current_bidx; ?>">
        <?php if (!empty(get_param('dir', 'path'))): ?>
            <input type="hidden" name="dir" value="<?php echo htmlspecialchars(get_param('dir', 'path', ''), ENT_QUOTES); ?>">
        <?php endif; ?>
        <div class="input-group" style="max-width: 500px; width: 100%;">
            <input 
                type="text" 
                name="q" 
                class="form-control" 
                placeholder="<?php echo __h('ui_search_placeholder'); ?>" 
                value="<?php echo hv($q); ?>"
                style="border-radius: 4px 0 0 4px; height: 40px;"
            >
            <div class="input-group-append">
                <button type="submit" class="btn btn-success" style="border-radius: 0 4px 4px 0; height: 40px;" title="<?php echo __h('common_search'); ?>" aria-label="<?php echo __h('common_search'); ?>">
                    <?php echo mc_ui_icon('search', 18, 2.4); // ★ (2026-10-31) '검색' 글자 빼고 돋보기만(연한 초록 원형 — 아래 스타일) ?>
                </button>
            </div>
        </div>
    </form>
    
    <?php 
    // ✅ 번역 안내 메시지 (검색창 아래)
    // 정방향: 한글 → 영어
    if (!empty($q) && !empty($translated_q)): 
        $display_translated = generateTranslationCombinations($q, $translated_q);
    ?>
        <div class='mc-noico' style='margin: 0 auto 15px; padding: 10px 15px; background: #d4edda; color: #155724; border-left: 4px solid #28a745; border-radius: 4px; max-width: 500px; text-align: left;'>
            💡 <?php echo __('search_also_searching', h($q), htmlspecialchars($display_translated, ENT_QUOTES)); ?>
        </div>
    <?php 
    // 역방향: 영어 → 한글
    elseif (!empty($q) && !empty($reverse_translated_q)): 
        $display_reverse = is_array($reverse_translated_q) ? implode(', ', $reverse_translated_q) : $reverse_translated_q;
    ?>
        <div class='mc-noico' style='margin: 0 auto 15px; padding: 10px 15px; background: #d4edda; color: #155724; border-left: 4px solid #28a745; border-radius: 4px; max-width: 500px; text-align: left;'>
            💡 <?php echo __('search_also_searching', h($q), htmlspecialchars($display_reverse, ENT_QUOTES)); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($q) && empty($search_no_results)) echo mc_search_elsewhere_html($q, 'chips', count($dir_list ?? []) + count($title_list ?? []) + count($file_list ?? [])) . mc_search_sticky_js(); // ★ (2026-11-04 20:51) 여기에도 결과가 있을 때: 다른 폴더 목록 — ★ (2026-11-07 fix41) 칩 줄 → 한 줄 안내 + [다른 폴더 N곳] ?>
</div>
<?php endif; ?>
<!--// 해당 $dir 없을때 검색기능 -->


<?php if($has_folders || $has_files || ($is_admin && $mc_dir_err === '')): /* ★ (23:45) 폴더를 못 열면 도구 없음(만들·올릴 곳이 없어 눌러도 오류) / ★ (2026-11-05 23:05) 관리자는 빈 콘텐츠 폴더 최상위에서도(펜닐 제보: 새로 추가한 콘텐츠 폴더가 비어 있으면 '새 폴더'·삭제 도구가 안 보여 폴더를 만들 수 없었음 — 폴더가 하나라도 있으면 원래 보였음). 정렬 단추는 아래 조건 그대로(내용이 있을 때만), 파일 올리기는 그대로 하위 폴더에서만 */ ?>
<?php
// 상위폴더 경로 계산
$updir_for_sort = "";
if (!(isset($is_root) && $is_root == true)) {
    $nowdirarr_sort = explode("/", $getdir);
    $temp_sort = count($nowdirarr_sort);
    for($i = 1; $i < $temp_sort - 1; $i++) {
        $updir_for_sort .= "/" . $nowdirarr_sort[$i];
    }
}
?>
<div class="row root-sort-section">
<?php // ★ (2026-10-30) 시안 E — 정렬은 둥근 칩, 오른쪽에 보기 전환·도구. '상위폴더'는 제목 앞 ← 로 옮김(같은 주소) ?>
<div class="mc-sortbar">
<?php
$sort = validate_sort_mode(get_param('sort', 'string', $__mc_sort_def ?? 'nameasc'));
$page = validate_page(get_param('page', 'int', 0));

// URL 파라미터 구성
$base_params = array_filter([
    'dir' => get_param('dir', 'path', ''),
    'q' => $q,
    'sq' => ($q === '' && isset($mc_sq)) ? $mc_sq : '', // ★ (2026-11-07 fix44) 정렬을 바꿔도 찾은 폴더 이동 막대가 이어지게(빈 값은 아래에서 빠짐)
    'page' => $page,
    'bidx' => $current_bidx
], function($v) { return $v !== '' && $v !== null; });
$param_string = http_build_query($base_params);
?>

<?php if($has_folders || $has_files): ?>
<!-- 기본(이름) 정렬 버튼 -->
<button onclick="location.replace('index.php?<?php 
    if($sort === '' || $sort === 'nameasc') { 
        echo 'sort=namedesc&'; 
    } else { 
        echo 'sort=nameasc&'; 
    } 
    echo $param_string; 
?>')" class="btn btn-sm btn-<?php 
    if($sort === '' || $sort === 'nameasc' || $sort === 'namedesc') { 
        echo ''; 
    } else { 
        echo 'outline-'; 
    } 
?>info m-0 p-1"><?php echo __h("sort_default"); ?>

<?php if ($sort === "namedesc") { ?>
<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-sort-alpha-up" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M4 14a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-1 0v11a.5.5 0 0 0 .5.5z"/>
  <path fill-rule="evenodd" d="M6.354 4.854a.5.5 0 0 0 0-.708l-2-2a.5.5 0 0 0-.708 0l-2 2a.5.5 0 1 0 .708.708L4 3.207l1.646 1.647a.5.5 0 0 0 .708 0z"/>
  <path d="M9.664 7l.418-1.371h1.781L12.281 7h1.121l-1.78-5.332h-1.235L8.597 7h1.067zM11 2.687l.652 2.157h-1.351l.652-2.157H11zM9.027 14h3.934v-.867h-2.645v-.055l2.567-3.719v-.691H9.098v.867h2.507v.055l-2.578 3.719V14z"/>
</svg>			
<?php } else { ?>
<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-sort-alpha-down" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M4 2a.5.5 0 0 1 .5.5v11a.5.5 0 0 1-1 0v-11A.5.5 0 0 1 4 2z"/>
  <path fill-rule="evenodd" d="M6.354 11.146a.5.5 0 0 1 0 .708l-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 0 1 .708-.708L4 12.793l1.646-1.647a.5.5 0 0 1 .708 0z"/>
  <path d="M9.664 7l.418-1.371h1.781L12.281 7h1.121l-1.78-5.332h-1.235L8.597 7h1.067zM11 2.687l.652 2.157h-1.351l.652-2.157H11zM9.027 14h3.934v-.867h-2.645v-.055l2.567-3.719v-.691H9.098v.867h2.507v.055l-2.578 3.719V14z"/>
</svg>			
<?php } ?>
</button>

<!-- 시간 정렬 버튼 -->
<button onclick="var url='index.php?sort=<?php 
    echo ($sort === 'timeasc') ? 'timedesc' : 'timeasc'; 
?><?php echo !empty($param_string) ? '&' . $param_string : ''; ?>'; location.replace(url);" class="btn btn-sm btn-<?php 
    if($sort === 'timeasc' || $sort === 'timedesc') { 
        echo ''; 
    } else { 
        echo 'outline-'; 
    } 
?>info ml-1 p-1"><?php echo __h("sort_time"); ?>

<?php if ($sort === "timedesc") { ?>
<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-sort-numeric-up" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M4 14a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-1 0v11a.5.5 0 0 0 .5.5z"/>
  <path fill-rule="evenodd" d="M6.354 4.854a.5.5 0 0 0 0-.708l-2-2a.5.5 0 0 0-.708 0l-2 2a.5.5 0 1 0 .708.708L4 3.207l1.646 1.647a.5.5 0 0 0 .708 0z"/>
  <path d="M12.438 7V1.668H11.39l-1.262.906v.969l1.21-.86h.052V7h1.046zm-2.84 5.82c.054.621.625 1.278 1.761 1.278 1.422 0 2.145-.98 2.145-2.848 0-2.05-.973-2.688-2.063-2.688-1.125 0-1.972.688-1.972 1.836 0 1.145.808 1.758 1.719 1.758.69 0 1.113-.351 1.261-.742h.059c.031 1.027-.309 1.856-1.133 1.856-.43 0-.715-.227-.773-.45H9.598zm2.757-2.43c0 .637-.43.973-.933.973-.516 0-.934-.34-.934-.98 0-.625.407-1 .926-1 .543 0 .941.375.941 1.008z"/>
</svg>
<?php } else { ?>
<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-sort-numeric-down" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M4 2a.5.5 0 0 1 .5.5v11a.5.5 0 0 1-1 0v-11A.5.5 0 0 1 4 2z"/>
  <path fill-rule="evenodd" d="M6.354 11.146a.5.5 0 0 1 0 .708l-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 0 1 .708-.708L4 12.793l1.646-1.647a.5.5 0 0 1 .708 0z"/>
  <path d="M12.438 7V1.668H11.39l-1.262.906v.969l1.21-.86h.052V7h1.046zm-2.84 5.82c.054.621.625 1.278 1.761 1.278 1.422 0 2.145-.98 2.145-2.848 0-2.05-.973-2.688-2.063-2.688-1.125 0-1.972.688-1.972 1.836 0 1.145.808 1.758 1.719 1.758.69 0 1.113-.351 1.261-.742h.059c.031 1.027-.309 1.856-1.133 1.856-.43 0-.715-.227-.773-.45H9.598zm2.757-2.43c0 .637-.43.973-.933.973-.516 0-.934-.34-.934-.98 0-.625.407-1 .926-1 .543 0 .941.375.941 1.008z"/>
</svg>
<?php } ?>
</button>

<?php if ($has_files): ?>
<!-- 파일 크기 정렬 버튼 (파일 있을 때) -->
<button onclick="var url='index.php?sort=<?php 
    echo ($sort === 'sizeasc') ? 'sizedesc' : 'sizeasc'; 
?><?php echo !empty($param_string) ? '&' . $param_string : ''; ?>'; location.replace(url);" class="btn btn-sm btn-<?php 
    if($sort === 'sizeasc' || $sort === 'sizedesc') { 
        echo ''; 
    } else { 
        echo 'outline-'; 
    } 
?>info ml-1 mr-1 p-1"><?php echo __h("sort_size"); ?>

<?php if ($sort === "sizedesc") { ?>
<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-sort-up-alt" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M3 14a.5.5 0 0 0 .5-.5v-10a.5.5 0 0 0-1 0v10a.5.5 0 0 0 .5.5z"/>
  <path fill-rule="evenodd" d="M5.354 5.854a.5.5 0 0 0 0-.708l-2-2a.5.5 0 0 0-.708 0l-2 2a.5.5 0 1 0 .708.708L3 4.207l1.646 1.647a.5.5 0 0 0 .708 0zM7 6.5a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 0-1h-3a.5.5 0 0 0-.5.5zm0 3a.5.5 0 0 0 .5.5h5a.5.5 0 0 0 0-1h-5a.5.5 0 0 0-.5.5zm0 3a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 0-1h-7a.5.5 0 0 0-.5.5zm0-9a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0-.5.5z"/>
</svg>
<?php } else { ?>
<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-sort-down-alt" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M3 3a.5.5 0 0 1 .5.5v10a.5.5 0 0 1-1 0v-10A.5.5 0 0 1 3 3z"/>
  <path fill-rule="evenodd" d="M5.354 11.146a.5.5 0 0 1 0 .708l-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 0 1 .708-.708L3 12.793l1.646-1.647a.5.5 0 0 1 .708 0zM7 6.5a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 0-1h-3a.5.5 0 0 0-.5.5zm0 3a.5.5 0 0 0 .5.5h5a.5.5 0 0 0 0-1h-5a.5.5 0 0 0-.5.5zm0 3a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 0-1h-7a.5.5 0 0 0-.5.5zm0-9a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0-.5.5z"/>
</svg>
<?php } ?>
</button>
<?php else: ?>
<!-- 폴더 내 파일 개수 정렬 버튼 (폴더만 있을 때) -->
<button onclick="var url='index.php?sort=<?php 
    echo ($sort === 'countasc') ? 'countdesc' : 'countasc'; 
?><?php echo !empty($param_string) ? '&' . $param_string : ''; ?>'; location.replace(url);" class="btn btn-sm btn-<?php 
    if($sort === 'countasc' || $sort === 'countdesc') { 
        echo ''; 
    } else { 
        echo 'outline-'; 
    } 
?>info ml-1 mr-1 p-1"><?php echo __h("sort_filecount"); ?>

<?php if ($sort === "countdesc") { ?>
<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-sort-numeric-up" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M4 14a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-1 0v11a.5.5 0 0 0 .5.5z"/>
  <path fill-rule="evenodd" d="M6.354 4.854a.5.5 0 0 0 0-.708l-2-2a.5.5 0 0 0-.708 0l-2 2a.5.5 0 1 0 .708.708L4 3.207l1.646 1.647a.5.5 0 0 0 .708 0z"/>
  <path d="M12.438 7V1.668H11.39l-1.262.906v.969l1.21-.86h.052V7h1.046zm-2.84 5.82c.054.621.625 1.278 1.761 1.278 1.422 0 2.145-.98 2.145-2.848 0-2.05-.973-2.688-2.063-2.688-1.125 0-1.972.688-1.972 1.836 0 1.145.808 1.758 1.719 1.758.69 0 1.113-.351 1.261-.742h.059c.031 1.027-.309 1.856-1.133 1.856-.43 0-.715-.227-.773-.45H9.598zm2.757-2.43c0 .637-.43.973-.933.973-.516 0-.934-.34-.934-.98 0-.625.407-1 .926-1 .543 0 .941.375.941 1.008z"/>
</svg>
<?php } else { ?>
<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-sort-numeric-down" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M4 2a.5.5 0 0 1 .5.5v11a.5.5 0 0 1-1 0v-11A.5.5 0 0 1 4 2z"/>
  <path fill-rule="evenodd" d="M6.354 11.146a.5.5 0 0 1 0 .708l-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 0 1 .708-.708L4 12.793l1.646-1.647a.5.5 0 0 1 .708 0z"/>
  <path d="M12.438 7V1.668H11.39l-1.262.906v.969l1.21-.86h.052V7h1.046zm-2.84 5.82c.054.621.625 1.278 1.761 1.278 1.422 0 2.145-.98 2.145-2.848 0-2.05-.973-2.688-2.063-2.688-1.125 0-1.972.688-1.972 1.836 0 1.145.808 1.758 1.719 1.758.69 0 1.113-.351 1.261-.742h.059c.031 1.027-.309 1.856-1.133 1.856-.43 0-.715-.227-.773-.45H9.598zm2.757-2.43c0 .637-.43.973-.933.973-.516 0-.934-.34-.934-.98 0-.625.407-1 .926-1 .543 0 .941.375.941 1.008z"/>
</svg>
<?php } ?>
</button>
<?php endif; ?>
<?php endif; ?><!-- // 정렬 버튼 조건 끝 -->
<!-- ★ (2026-10-29) 보기 전환: [그리드][목록] 두 버튼, 현재 보기 강조. 브라우저에만 저장(localStorage) -->
<span class="mc-sp"></span><span class="mc-view-switch ml-1" role="group" aria-label="<?php echo __h('index_view_grid'); ?> / <?php echo __h('index_view_list'); ?>">
<button type="button" class="mc-view-btn" data-view="grid" onclick="mcSetListView(false, this)" title="<?php echo __h('index_view_grid'); ?>" aria-label="<?php echo __h('index_view_grid'); ?>">
<?php echo mc_ui_icon('layout-grid', 17); ?>
</button><button type="button" class="mc-view-btn" data-view="list" onclick="mcSetListView(true, this)" title="<?php echo __h('index_view_list'); ?>" aria-label="<?php echo __h('index_view_list'); ?>">
<?php echo mc_ui_icon('list', 17); ?>
</button>
</span>
<script>
function mcSyncViewToggle() {
    var isList = document.documentElement.classList.contains('mc-list-view');
    var btns = document.querySelectorAll('.mc-view-btn');
    for (var i = 0; i < btns.length; i++) {
        var on = (btns[i].getAttribute('data-view') === 'list') === isList;
        btns[i].classList.toggle('active', on);
        btns[i].setAttribute('aria-pressed', on ? 'true' : 'false');
    }
}
function mcSetListView(on, btn) {
    document.documentElement.classList.toggle('mc-list-view', !!on);
    try { localStorage.setItem('mcListView', on ? '1' : '0'); } catch (e) {}
    mcSyncViewToggle();
    // 클릭 후 포커스 그림자가 남지 않도록 포커스 해제(키보드 사용 시에는 :focus-visible 테두리만 표시)
    try { if (btn && btn.blur) btn.blur(); } catch (e) {}
}
mcSyncViewToggle();
</script>

<?php if ($is_admin): ?>
<?php // ★ (2026-10-30) 관리자 도구 묶음 — 휴대폰 세로(576px 이하)에서는 ⋯ 메뉴 안(🏷️ 버튼도 library_index_inject 가 🗑️ 바로 뒤, 같은 묶음에 넣음) ?>
<span class="mc-tools" id="mcTools">
<!-- 관리자 전용 버튼 (모바일/태블릿 숨김) -->
<button type="button" class="btn btn-sm btn-outline-success ml-2 p-1 d-none d-lg-inline-block" onclick="openCreateFolderModal()" title="<?php echo __h('ui_new_folder'); ?>" aria-label="<?php echo __h('ui_new_folder'); ?>"><?php echo mc_ui_icon('folder-plus', 18); ?></button>
<?php if (!(isset($is_root) && $is_root == true)): ?>
<!-- 파일 업로드는 하위 폴더에서만 -->
<button type="button" class="btn btn-sm btn-outline-primary ml-1 p-1 d-none d-lg-inline-block" onclick="openUploadModal()" title="<?php echo __h('ui_upload_file'); ?>" aria-label="<?php echo __h('ui_upload_file'); ?>"><?php echo mc_ui_icon('upload', 18); ?></button>
<?php endif; ?>
<!-- 삭제 모드 토글 -->
<button type="button" id="deleteModeBtn" class="btn btn-sm btn-outline-danger ml-1 p-1" onclick="toggleDeleteMode()" title="<?php echo __h('ui_delete_mode'); ?>" aria-label="<?php echo __h('ui_delete_mode'); ?>"><?php echo mc_ui_icon('trash', 18); ?></button>
</span>
<script>
// ★ (2026-10-30) 삭제 모드 켜고 끌 때(toggleDeleteMode)·🏷️ 버튼(library_index_inject)이 쓰는 같은 선 아이콘
window.MC_ICO_TRASH = <?php echo json_encode(mc_ui_icon('trash', 18), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.MC_ICO_TAG = <?php echo json_encode(mc_ui_icon('tag', 18), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<button type="button" class="mc-more-btn" id="mcMoreBtn" aria-haspopup="true" aria-expanded="false" aria-controls="mcTools" title="<?php echo __h('index_more_tools'); ?>" aria-label="<?php echo __h('index_more_tools'); ?>"><?php echo mc_ui_icon('ellipsis', 20, 2.6); ?></button>
<script>
// ★ (2026-10-30) ⋯ 도구 메뉴(휴대폰 세로) — 안의 버튼을 누르면 닫힘, 바깥 누르기·Esc 로 닫힘
(function () {
  var btn = document.getElementById('mcMoreBtn'), tools = document.getElementById('mcTools');
  if (!btn || !tools) return;
  function set(open) { tools.classList.toggle('open', open); btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  btn.addEventListener('click', function (e) { e.stopPropagation(); set(!tools.classList.contains('open')); });
  tools.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('button')) setTimeout(function () { set(false); }, 0); });
  document.addEventListener('click', function (e) { if (tools.classList.contains('open') && !tools.contains(e.target) && e.target !== btn) set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && tools.classList.contains('open')) set(false); });
})();
</script>
<?php endif; ?>

</div>
</div>
<?php endif; ?>
</div><!-- // root-folder-wrapper -->

<!--// 해당 $dir에서 검색기능 (상위폴더+정렬버튼 아래) -->
<?php if (!empty(get_param('dir', 'path')) && $mc_dir_err === ''): /* ★ (2026-11-06 00:10) 하위 폴더가 없으면 검색창 없음(위와 같음) */ ?>
<div id="search-section" class="p-3">
    
    <form method="get" action="index.php" class="form-inline justify-content-center">
        <input type="hidden" name="bidx" value="<?php echo $current_bidx; ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars(get_param('dir', 'path', '') ?? '', ENT_QUOTES); ?>">
        <div class="input-group" style="max-width: 500px; width: 100%;">
            <input 
                type="text" 
                name="q" 
                class="form-control" 
                placeholder="<?php echo __h('ui_search_placeholder'); ?>" 
                value="<?php echo hv($q); ?>"
                style="border-radius: 4px 0 0 4px; height: 40px;"
            >
            <div class="input-group-append">
                <button 
                    type="submit" 
                    class="btn btn-success" 
                    style="border-radius: 0 4px 4px 0; height: 40px;"
                    title="<?php echo __h('common_search'); ?>" aria-label="<?php echo __h('common_search'); ?>"
                >
                    <?php echo mc_ui_icon('search', 18, 2.4); // ★ (2026-10-31) '검색' 글자 빼고 돋보기만(연한 초록 원형 — 아래 스타일) ?>
                </button>
            </div>
        </div>
    </form>

    <?php 
    // ✅ 번역 안내 메시지 (검색창 아래)
    // 정방향: 한글 → 영어
    if (!empty($q) && !empty($translated_q)): 
        $display_translated2 = generateTranslationCombinations($q, $translated_q);
    ?>
        <div class='mc-noico' style='margin: 0 auto 15px; padding: 10px 15px; background: #d4edda; color: #155724; border-left: 4px solid #28a745; border-radius: 4px; max-width: 500px; text-align: left;'>
            💡 <?php echo __('search_also_searching', h($q), htmlspecialchars($display_translated2, ENT_QUOTES)); ?>
        </div>
    <?php 
    // 역방향: 영어 → 한글
    elseif (!empty($q) && !empty($reverse_translated_q)): 
        $display_reverse2 = is_array($reverse_translated_q) ? implode(', ', $reverse_translated_q) : $reverse_translated_q;
    ?>
        <div class='mc-noico' style='margin: 0 auto 15px; padding: 10px 15px; background: #d4edda; color: #155724; border-left: 4px solid #28a745; border-radius: 4px; max-width: 500px; text-align: left;'>
            💡 <?php echo __('search_also_searching', h($q), htmlspecialchars($display_reverse2, ENT_QUOTES)); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($q) && !empty($filtered_file_list)) echo mc_search_elsewhere_html($q, 'chips', count($filtered_file_list)) . mc_search_sticky_js(); // ★ (2026-11-04 20:51) 다른 폴더에서 찾아 들어온 뒤 등 여기에도 결과가 있을 때 — ★ (2026-11-07 fix41) 칩 줄 → 한 줄 안내 ?>

    <?php if (!empty($q) && empty($filtered_file_list)): ?>
        <div style="padding: 24px 12px 8px;">
            <?php echo mc_search_none_html($q); // ★ (2026-11-07 fix41) '검색 결과가 없습니다'(무엇을 어디서 못 찾았는지 알 수 없던 글) → 이 폴더에 없다는 안내 + 다른 폴더 짧은 목록 / 어디에도 없으면 고쳐 볼 방법 ?>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<!-- 검색 영역이 없어도 ID는 유지 (숨김 처리) -->
<div id="search-section" style="display: none;"></div>
<?php endif; ?>
<!--// 해당 $dir에서 검색기능 -->

<?php
// title_list 정렬 처리
// error_log("=== 정렬 디버그 ===");
// error_log("sort 값: " . ($sort ?? 'NULL'));
// error_log("title_list 개수: " . count($title_list));

// ★ (2026-11-02 10:03) 정렬(이름 역순·시간·개수)을 하위 폴더 목록($dir_list)에도 — 전에는 $title_list 만 정렬돼 루트처럼 폴더가 많은 곳은 정렬 버튼을 눌러도 순서가 그대로였음
if (!isset($sort)) $sort = validate_sort_mode(get_param('sort', 'string', $__mc_sort_def ?? 'nameasc')); // ★ (2026-11-02 11:37) 폴더를 못 연 경우 등 $sort 가 아직 없을 때(경고)
if (!isset($dir)) $dir = '';
$__mc_sort_folders = function (array $__l) use ($sort, $dir) {
    if ($sort == 'namedesc') {
        rsort($__l, SORT_NATURAL | SORT_FLAG_CASE);
    } elseif ($sort == 'timeasc') {
        // 폴더 생성시간으로 정렬 (오름차순)
        $title_with_time = array();
        foreach ($__l as $title) {
            $folder_path = $dir . "/" . str_replace("00000000.", "", $title);
            $title_with_time[] = array(
                'name' => $title,
                'time' => is_dir($folder_path) ? filemtime($folder_path) : 0
            );
        }
        usort($title_with_time, function($a, $b) {
            return $a['time'] - $b['time'];
        });
        $__l = array_column($title_with_time, 'name');
    } elseif ($sort == 'timedesc') {
        // 폴더 생성시간으로 정렬 (내림차순)
        $title_with_time = array();
        foreach ($__l as $title) {
            $folder_path = $dir . "/" . str_replace("00000000.", "", $title);
            $title_with_time[] = array(
                'name' => $title,
                'time' => is_dir($folder_path) ? filemtime($folder_path) : 0
            );
        }
        usort($title_with_time, function($a, $b) {
            return $b['time'] - $a['time'];
        });
        $__l = array_column($title_with_time, 'name');
    } elseif ($sort == 'countasc') {
        // 폴더 내 파일 개수로 정렬 (오름차순) - ✅ 캐시 활용
        $title_with_count = array();
        foreach ($__l as $title) {
            $folder_path = $dir . "/" . str_replace("00000000.", "", $title);
            $file_count = 0;
            if (is_dir($folder_path)) {
                // ✅ .folder_cache.json에서 개수 읽기 (scandir 대신)
                $sort_cache_file = $folder_path . '/.folder_cache.json';
                if (is_file($sort_cache_file)) {
                    $sort_cache = @json_decode(file_get_contents($sort_cache_file), true);
                    if ($sort_cache) {
                        $file_count = (int)($sort_cache['zip_count'] ?? 0) 
                                    + (int)($sort_cache['video_count'] ?? 0)
                                    + (int)($sort_cache['pure_image_count'] ?? 0)
                                    + (int)($sort_cache['subdir_count'] ?? 0);
                    }
                }
            }
            $title_with_count[] = array(
                'name' => $title,
                'count' => $file_count
            );
        }
        usort($title_with_count, function($a, $b) {
            return $a['count'] - $b['count'];
        });
        $__l = array_column($title_with_count, 'name');
    } elseif ($sort == 'countdesc') {
        // 폴더 내 파일 개수로 정렬 (내림차순) - ✅ 캐시 활용
        $title_with_count = array();
        foreach ($__l as $title) {
            $folder_path = $dir . "/" . str_replace("00000000.", "", $title);
            $file_count = 0;
            if (is_dir($folder_path)) {
                // ✅ .folder_cache.json에서 개수 읽기 (scandir 대신)
                $sort_cache_file = $folder_path . '/.folder_cache.json';
                if (is_file($sort_cache_file)) {
                    $sort_cache = @json_decode(file_get_contents($sort_cache_file), true);
                    if ($sort_cache) {
                        $file_count = (int)($sort_cache['zip_count'] ?? 0) 
                                    + (int)($sort_cache['video_count'] ?? 0)
                                    + (int)($sort_cache['pure_image_count'] ?? 0)
                                    + (int)($sort_cache['subdir_count'] ?? 0);
                    }
                }
            }
            $title_with_count[] = array(
                'name' => $title,
                'count' => $file_count
            );
        }
        usort($title_with_count, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        $__l = array_column($title_with_count, 'name');
    } else {
        // 기본 정렬 (이름 오름차순)
        sort($__l, SORT_NATURAL | SORT_FLAG_CASE);
    }
    return $__l;
};
if (!empty($title_list)) $title_list = $__mc_sort_folders($title_list);
if (!empty($dir_list)) $dir_list = $__mc_sort_folders($dir_list);
// title_list 정렬 처리
?>
<?php if ($mc_dir_err !== ''): // ★ (2026-11-05 23:45) 폴더를 열 수 없을 때 안내 — 목록이 나올 자리에(예전: 화면 맨 위 한 줄 "설정된 경로가 없거나 읽을 수 없습니다. 다시 설정하세요.") ?>
<div class="mc-dir-err" role="alert">
  <div class="mc-dir-err-t"><?php echo mc_ui_icon('folder', 20); ?><?php echo __h($mc_dir_err === 'sub' ? 'dir_err_sub_title' : ($is_admin && !empty($mc_dir_err_gone) ? 'dir_err_gone_title' : 'dir_err_base_title')); ?></div>
<?php if ($mc_dir_err === 'base'): ?>
<?php if ($is_admin): ?>
  <p><?php echo __h(!empty($mc_dir_err_gone) ? 'dir_err_gone_admin' : 'dir_err_base_admin'); ?></p>
  <p class="mc-dir-err-path"><?php echo __h('dir_err_path'); ?>: <code><?php echo h((string)$base_dir); ?></code></p>
  <a class="btn btn-sm btn-outline-primary" href="admin.php?tab=config"><?php echo __h('dir_err_open_settings'); ?></a>
<?php else: ?>
  <p><?php echo __h('dir_err_base_user'); ?></p>
<?php endif; ?>
<?php else: ?>
  <p><?php echo __h('dir_err_sub_body'); ?></p>
  <a class="btn btn-sm btn-outline-primary" href="index.php?bidx=<?php echo (int)$current_bidx; ?>"><?php echo __h('dir_err_to_top'); ?></a>
<?php endif; ?>
</div>
<style>
  .mc-dir-err { max-width: 640px; margin: 24px auto 8px; padding: 18px 20px; border: 1px solid #f1d5a8; background: #fff8ec; border-radius: 12px; color: #5f4b2b; text-align: left; }
  .mc-dir-err-t { display: flex; align-items: center; gap: 8px; font-size: 1.05rem; font-weight: 700; color: #8a5a00; margin-bottom: 8px; }
  .mc-dir-err p { margin: 0 0 10px; font-size: .92rem; line-height: 1.55; }
  .mc-dir-err-path code { color: #6b3f00; background: rgba(0,0,0,.05); padding: 1px 6px; border-radius: 4px; word-break: break-all; }
  .dark-mode .mc-dir-err, [data-theme="dark"] .mc-dir-err { background: #2a2418; border-color: #5a4a2a; color: #e6d8bf; }
  .dark-mode .mc-dir-err-t, [data-theme="dark"] .mc-dir-err-t { color: #f0c36d; }
  .dark-mode .mc-dir-err-path code, [data-theme="dark"] .mc-dir-err-path code { color: #f0c36d; background: rgba(255,255,255,.08); }
</style>
<?php endif; ?>



	<?php
if (isset($is_root) && $is_root == true) {
	} else {
		$nowdirarr = explode("/", $getdir);
		$temp = count($nowdirarr);
			for($i = 1;$i<$temp-1;$i++) {
				$updir = $updir."/".$nowdirarr[$i];
			}
	}
if($use_listcover == "y"){
?>	
<div class="row row-cols-2 row-cols-md-4">
<?php
} else {
?>	
<div class="row row-cols-1 row-cols-md-2">
	<?php
}

// ✅ 메인페이지 검색 결과 없음 메시지
if (isset($search_no_results) && $search_no_results === true): ?>
</div><!-- row 닫기 -->
<div style="width: 100%; padding: 32px 12px 20px;">
    <?php echo mc_search_none_html($q); // ★ (2026-11-07 fix41) 큰 '검색 결과가 없습니다' → 이 폴더에 없다는 안내 + 다른 폴더 짧은 목록 / 어디에도 없으면 고쳐 볼 방법 ?>
</div>
<div class="row"><!-- 빈 row 다시 열기 (endif용) -->
<?php else: ?>

<?php
	$dir_start = $startview;
	if(count($dir_list) > 0){
		for($i=$dir_start;$i<$endview;$i++) {
			$startview = $i;
			$fileinfo = $dir_list[$i] ?? '';
			$dirs = str_replace($base_dir."/", "", $dir);
			if($i >= count($dir_list)){
				break;
			}
	?>	
			 
<a href='index.php?uppage=<?php echo isset($page_param) ? $page_param : 0; ?>&dir=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>'>
  <div class="col mb-3">
    <div class="card mc-folder-card border-secondary m-1 p-0" <?php if ($is_admin): ?>data-folder-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
				<div class="card-body text-secondary m-1 p-1 d-inline-block text-truncate text-nowrap">
				
<?php
if (isset($dirinfo[$fileinfo]) && $dirinfo[$fileinfo] === "remote") {
?>	
					<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-hdd-network-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
					  <path fill-rule="evenodd" d="M2 2a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2h5.5v3A1.5 1.5 0 0 0 6 11.5H.5a.5.5 0 0 0 0 1H6A1.5 1.5 0 0 0 7.5 14h1a1.5 1.5 0 0 0 1.5-1.5h5.5a.5.5 0 0 0 0-1H10A1.5 1.5 0 0 0 8.5 10V7H14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H2zm.5 3a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1zm2 0a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
					</svg> <?php 
$rclone_name = str_replace("rclone_", "", $fileinfo);
$all_terms = isset($GLOBALS["all_highlight_terms"]) && is_array($GLOBALS["all_highlight_terms"]) ? $GLOBALS["all_highlight_terms"] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo highlight_search($rclone_name, $all_terms);
?>
<?php
} else {
if($use_listcover == "y"){
	if(is_file($dir."/".$fileinfo."/[cover].jpg")){
		echo "<img class=\"rounded-lg mb-2\" src=\"thumb.php?file=".encode_url($getdir."/".$fileinfo)."&type=cover&v=".(int)@filemtime($dir."/".$fileinfo."/[cover].jpg").$bidx_param."\" style=\"height:120px;max-width:100%;min-width:100%;object-fit:contain;\" loading=\"lazy\"><br>";
	} else {
		echo "<img class=\"rounded-lg mb-2\" src=\"data:".mime_type("jpg").";base64,".$null_image."\" style=\"height:120px;max-width:100%;min-width:100%;object-fit:fill;\"><br>";
	}
}

?>
<div class="card-body m-0 p-0 text-center text-nowrap" style="text-overflow: ellipsis; overflow: hidden;">
<?php
$folder_path = $dir . "/" . $fileinfo;
$zip_count = 0;
$video_count = 0;
$pure_image_count = 0;
$subdir_count = 0;
$folder_icon = '📁';
$has_pdf = false;
$has_epub = false;
$has_txt = false;
$newest_mtime = 0;  // ✅ 폴더 내 가장 최근 파일의 mtime

$cache_file = $folder_path . '/.folder_cache.json';
$cache_valid = false;

// ✅ 실제 파일 개수 (Windows mtime 버그 대응)
// [I/O 최적화] scandir(배열 생성+정렬) 대신 FilesystemIterator로 개수만 카운트
//  - 폴더 리스트를 그릴 때 폴더마다 호출되므로, 폴더가 많을수록 효과 큼
//  - 결과값은 scandir 방식과 동일 (SKIP_DOTS가 . / .. 자동 제외)
$actual_count = 0;
try {
    $_fi = new FilesystemIterator($folder_path, FilesystemIterator::SKIP_DOTS);
    foreach ($_fi as $_unused) { $actual_count++; }
    unset($_fi);
} catch (Exception $e) {
    $actual_count = 0;
}

if (is_file($cache_file)) {
    $cache = json_decode((string)@file_get_contents($cache_file), true); // ★ 2026-11-04 00:28 쓰는 중이면(Windows 잠금) 경고 없이
    $cached_count = $cache['total_items'] ?? -1;
    
    // ✅ 파일 개수 비교 추가
    if (isset($cache['zip_count'], $cache['subdir_count']) && ($cached_count === -1 || $cached_count === $actual_count)) {
        $zip_count = (int)$cache['zip_count'];
        $video_count = (int)($cache['video_count'] ?? 0);
        $pure_image_count = (int)($cache['pure_image_count'] ?? 0);
        $subdir_count = (int)($cache['subdir_count'] ?? 0);
        $has_pdf = $cache['has_pdf'] ?? false;
        $has_epub = $cache['has_epub'] ?? false;
        $has_txt = $cache['has_txt'] ?? false;
        $newest_mtime = (int)($cache['newest_mtime'] ?? 0);
        $cache_valid = true;
    }
}

// ✅ 캐시가 없으면 즉시 생성 (lazy loading)
if (!$cache_valid && is_dir($folder_path)) {
    $__nm = [];
    if ($dh = @opendir($folder_path)) {
        while (($sf = readdir($dh)) !== false) {
            if ($sf[0] === '.' || $sf === '@eaDir') continue;
            $sf_path = $folder_path . '/' . $sf;
            
            // ✅ 콘텐츠 파일만 newest_mtime 계산 (캐시/메타 파일 제외)
            if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|epub|txt|mp4|mkv|avi|mov|webm|m4v|ts|mts|m2ts|wmv|flv)$/i', $sf)) {
                $sf_ctime = get_file_created_time($sf_path);
                if ($sf_ctime > $newest_mtime) $newest_mtime = $sf_ctime;
            }
            
            if (is_dir($sf_path)) {
                $subdir_count++;
            } else {
                if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $sf)) {
                    $zip_count++;
                    if (preg_match('/\.pdf$/i', $sf)) $has_pdf = true;
                    if (preg_match('/\.epub$/i', $sf)) $has_epub = true;
                    if (preg_match('/\.txt$/i', $sf)) $has_txt = true;
                }
                if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $sf)) $video_count++;
                $__nm[] = $sf; // ★ (2026-11-02 22:17) 그림 수는 아래에서 분류와 같은 기준(같은 이름 표지·영상 썸네일 뺌)으로
            }
        }
        closedir($dh);
        $pure_image_count = mc_folder_count_page_imgs($__nm, mc_folder_book_stems($__nm));
        
        // 캐시 저장
        @file_put_contents($cache_file, json_encode([
            'zip_count' => $zip_count,
            'video_count' => $video_count,
            'pure_image_count' => $pure_image_count,
            'subdir_count' => $subdir_count,
            'has_pdf' => $has_pdf,
            'has_epub' => $has_epub,
            'has_txt' => $has_txt,
            'has_subdir' => $subdir_count > 0,
            'newest_mtime' => $newest_mtime,
            'total_items' => $actual_count + (is_file($cache_file) ? 0 : 1),  // ✅ 파일 개수 저장 (Windows mtime 버그 대응) ★ (2026-11-03 07:52) 처음 만들 때는 이 캐시 파일 자신도 셈(다음에 열 때 한 번 더 훑지 않게)
            'mtime' => time()
        ]), LOCK_EX);
    }
}

// ✅ [I/O 최적화] glob 호출 제거 - 캐시에 파일 타입 정보가 없어도 스킵
// 캐시 재생성 또는 lazy loading 시 has_pdf/has_epub/has_txt가 저장되므로, 여기서 glob 불필요
if (false && !$has_pdf && !$has_epub && !$has_txt && $pure_image_count == 0) {
    if (!empty(glob($folder_path . '/*.pdf')) || !empty(glob($folder_path . '/*.PDF'))) {
        $has_pdf = true;
        if ($zip_count == 0) $zip_count = count(glob($folder_path . '/*.{pdf,PDF}', GLOB_BRACE) ?: []);
    } elseif (!empty(glob($folder_path . '/*.epub'))) {
        $has_epub = true;
        if ($zip_count == 0) $zip_count = count(glob($folder_path . '/*.epub') ?: []);
    } elseif (!empty(glob($folder_path . '/*.txt'))) {
        $has_txt = true;
        if ($zip_count == 0) $zip_count = count(glob($folder_path . '/*.txt') ?: []);
    } else {
        // 순수 이미지만 있는지 확인하여 개수 세기
        $has_media = !empty(glob($folder_path . '/*.{zip,cbz,rar,cbr,7z,cb7,pdf,txt,epub,mp4,mkv,avi,mov,webm,m4v,ts,mts,m2ts,wmv,flv}', GLOB_BRACE));
        $image_files = glob($folder_path . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if ($image_files === false) $image_files = [];
        // [cover].jpg 제외
        $image_files = array_filter($image_files, function($f) {
            return !preg_match('/\[cover\]/i', basename($f));
        });
        if (!empty($image_files) && !$has_media) {
            $pure_image_count = count($image_files);
        }
    }
}

// 뱃지 아이콘 결정
$badge_icon = '📦';
if ($has_pdf) $badge_icon = '📄';
elseif ($has_epub) $badge_icon = '📖';
elseif ($has_txt) $badge_icon = '📝';
?>
					<span class="emoji-icon mc-ci"><?php echo $folder_icon; /* ★ (2026-11-05) 이름과 붙여서(간격은 CSS .mc-ci — 목록 보기에서 칩 줄을 이름 위치에 맞춤) */ ?></span><?php 
$all_terms = isset($GLOBALS["all_highlight_terms"]) && is_array($GLOBALS["all_highlight_terms"]) ? $GLOBALS["all_highlight_terms"] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo '<span class="mc-fname">' . highlight_search($fileinfo, $all_terms) . '</span>'; // ★ (2026-10-31) 이름을 한 덩어리로(목록 보기에서 이름 왼쪽·칩 오른쪽 배치용 — 그리드는 모양 같음)
?>
    <?php // ★ (2026-10-31) 노란 배지(📦·🎬·📁·🖼️ + 숫자) → 단위 글자 칩(이름 아래 줄, mc_folder_chips) — 보이는 조건은 같음
    // ★ (2026-11-04 00:01) NEW 딱지(폴더 내 가장 최신 파일 기준)를 칩 줄 안에 — 옆 칩과 같은 높이·간격(펜닐)
    $_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
    $_fc_new = ($_new_badge_hours > 0 && $newest_mtime > 0 && (time() - $newest_mtime) < ($_new_badge_hours * 3600)) ? '<span class="badge badge-danger mc-fnew">N</span>' : '';
    echo mc_folder_chips($has_pdf ? 'pdf' : ($has_epub ? 'epub' : ($has_txt ? 'txt' : 'zip')), (int)$zip_count, (int)$video_count, (int)($subdir_count ?? 0), (int)$pure_image_count, $_fc_new); ?>
</div>
<?php
}
?>
				</div>
			</div>
  </div>
				</a>
	<?php
		}
	}

	$title_start = $startview;
	if(count($title_list) > 0){
		for($i=$title_start;$i<$endview;$i++) {
			$startview = $i;
        $index = $i - count($dir_list);
        if (!isset($title_list[$index])) {
            break; // 인덱스가 없으면 반복 종료
        }

        $fileinfo = str_replace("00000000.", "", $title_list[$index]);
			$dirs = str_replace($base_dir."/", "", $dir);
			if($i >= (count($title_list)+count($dir_list))){
				break;
			}
	?>	
	<a href='index.php?uppage=<?php echo h(validate_page(get_param('page', 'int', 0))) ?? 0; ?>&dir=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>'>
	  <div class="col mb-3">
	    <div class="card mc-folder-card bg-secondary text-white text-left m-1 p-0" <?php if ($is_admin): ?>data-folder-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
				<div class="card-body m-1 p-1 d-inline-block text-truncate text-nowrap">
<?php

if($use_listcover == "y"){
	if(is_file($dir."/".$fileinfo."/[cover].jpg")){
		echo "<img class=\"rounded-lg mb-2\" src=\"thumb.php?file=".encode_url($getdir."/".$fileinfo)."&type=cover&v=".(int)@filemtime($dir."/".$fileinfo."/[cover].jpg").$bidx_param."\" style=\"height:120px;max-width:100%;min-width:100%;object-fit:contain;\" loading=\"lazy\"><br>";
	} else {
		echo "<img class=\"rounded-lg mb-2\" src=\"data:".mime_type("jpg").";base64,".$null_image."\" style=\"height:120px;max-width:100%;min-width:100%;object-fit:fill;\"><br>";
	}
}

?>
<div class="card-body m-0 p-0 text-center text-nowrap" style="text-overflow: ellipsis; overflow: hidden;">
<?php
$folder_path = $dir . "/" . $fileinfo;
$zip_count = 0;
$video_count = 0;
$pure_image_count = 0;
$subdir_count = 0;
$has_pdf = false;
$has_epub = false;
$has_txt = false;
$newest_mtime = 0;  // ✅ 폴더 내 가장 최근 파일의 mtime

// ✅ 폴더 아이콘은 항상 📚, 뱃지에서 파일 타입 표시
$folder_icon = '📚';
$cache_file = $folder_path . '/.folder_cache.json';
$cache_valid = false;

// ✅ 실제 파일 개수 (Windows mtime 버그 대응)
// [I/O 최적화] scandir(배열 생성+정렬) 대신 FilesystemIterator로 개수만 카운트
//  - 폴더 리스트를 그릴 때 폴더마다 호출되므로, 폴더가 많을수록 효과 큼
//  - 결과값은 scandir 방식과 동일 (SKIP_DOTS가 . / .. 자동 제외)
$actual_count = 0;
try {
    $_fi = new FilesystemIterator($folder_path, FilesystemIterator::SKIP_DOTS);
    foreach ($_fi as $_unused) { $actual_count++; }
    unset($_fi);
} catch (Exception $e) {
    $actual_count = 0;
}

if (is_file($cache_file)) {
    $cache = json_decode((string)@file_get_contents($cache_file), true); // ★ 2026-11-04 00:28 쓰는 중이면(Windows 잠금) 경고 없이
    $cached_count = $cache['total_items'] ?? -1;
    
    // ✅ 파일 개수 비교 추가
    if (isset($cache['zip_count'], $cache['subdir_count']) && ($cached_count === -1 || $cached_count === $actual_count)) {
        $zip_count = (int)$cache['zip_count'];
        $video_count = (int)($cache['video_count'] ?? 0);
        $pure_image_count = (int)($cache['pure_image_count'] ?? 0);
        $subdir_count = (int)($cache['subdir_count'] ?? 0);
        $has_pdf = $cache['has_pdf'] ?? false;
        $has_epub = $cache['has_epub'] ?? false;
        $has_txt = $cache['has_txt'] ?? false;
        $newest_mtime = (int)($cache['newest_mtime'] ?? 0);
        $cache_valid = true;
    }
}

// ✅ 캐시가 없으면 즉시 생성 (lazy loading)
if (!$cache_valid && is_dir($folder_path)) {
    $__nm = [];
    if ($dh = @opendir($folder_path)) {
        while (($sf = readdir($dh)) !== false) {
            if ($sf[0] === '.' || $sf === '@eaDir') continue;
            $sf_path = $folder_path . '/' . $sf;
            
            // ✅ 콘텐츠 파일만 newest_mtime 계산 (캐시/메타 파일 제외)
            if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|epub|txt|mp4|mkv|avi|mov|webm|m4v|ts|mts|m2ts|wmv|flv)$/i', $sf)) {
                $sf_ctime = get_file_created_time($sf_path);
                if ($sf_ctime > $newest_mtime) $newest_mtime = $sf_ctime;
            }
            
            if (is_dir($sf_path)) {
                $subdir_count++;
            } else {
                if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $sf)) {
                    $zip_count++;
                    if (preg_match('/\.pdf$/i', $sf)) $has_pdf = true;
                    if (preg_match('/\.epub$/i', $sf)) $has_epub = true;
                    if (preg_match('/\.txt$/i', $sf)) $has_txt = true;
                }
                if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $sf)) $video_count++;
                $__nm[] = $sf; // ★ (2026-11-02 22:17) 그림 수는 아래에서 분류와 같은 기준(같은 이름 표지·영상 썸네일 뺌)으로
            }
        }
        closedir($dh);
        $pure_image_count = mc_folder_count_page_imgs($__nm, mc_folder_book_stems($__nm));
        
        // 캐시 저장
        @file_put_contents($cache_file, json_encode([
            'zip_count' => $zip_count,
            'video_count' => $video_count,
            'pure_image_count' => $pure_image_count,
            'subdir_count' => $subdir_count,
            'has_pdf' => $has_pdf,
            'has_epub' => $has_epub,
            'has_txt' => $has_txt,
            'has_subdir' => $subdir_count > 0,
            'newest_mtime' => $newest_mtime,
            'total_items' => $actual_count + (is_file($cache_file) ? 0 : 1),  // ✅ 파일 개수 저장 (Windows mtime 버그 대응) ★ (2026-11-03 07:52) 처음 만들 때는 이 캐시 파일 자신도 셈(다음에 열 때 한 번 더 훑지 않게)
            'mtime' => time()
        ]), LOCK_EX);
    }
}

// ✅ [I/O 최적화] glob 호출 제거 - 캐시에 파일 타입 정보가 없어도 스킵
// 캐시 재생성 시 has_pdf/has_epub/has_txt가 저장되므로, 여기서 glob 불필요
if (false && !$has_pdf && !$has_epub && !$has_txt && $pure_image_count == 0) {
    if (!empty(glob($folder_path . '/*.pdf')) || !empty(glob($folder_path . '/*.PDF'))) {
        $has_pdf = true;
        if ($zip_count == 0) $zip_count = count(glob($folder_path . '/*.{pdf,PDF}', GLOB_BRACE) ?: []);
    } elseif (!empty(glob($folder_path . '/*.epub'))) {
        $has_epub = true;
        if ($zip_count == 0) $zip_count = count(glob($folder_path . '/*.epub') ?: []);
    } elseif (!empty(glob($folder_path . '/*.txt'))) {
        $has_txt = true;
        if ($zip_count == 0) $zip_count = count(glob($folder_path . '/*.txt') ?: []);
    } else {
        // 순수 이미지만 있는지 확인하여 개수 세기
        $has_media = !empty(glob($folder_path . '/*.{zip,cbz,rar,cbr,7z,cb7,pdf,txt,epub,mp4,mkv,avi,mov,webm,m4v,ts,mts,m2ts,wmv,flv}', GLOB_BRACE));
        $image_files = glob($folder_path . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if ($image_files === false) $image_files = [];
        // [cover].jpg 제외
        $image_files = array_filter($image_files, function($f) {
            return !preg_match('/\[cover\]/i', basename($f));
        });
        if (!empty($image_files) && !$has_media) {
            $pure_image_count = count($image_files);
        }
    }
}

// 뱃지 아이콘 결정
$badge_icon = '📦';
if ($has_pdf) $badge_icon = '📄';
elseif ($has_epub) $badge_icon = '📖';
elseif ($has_txt) $badge_icon = '📝';
?>
					<span class="emoji-icon mc-ci"><?php echo $folder_icon; /* ★ (2026-11-05) 이름과 붙여서(간격은 CSS .mc-ci) */ ?></span><?php 
$all_terms = isset($GLOBALS["all_highlight_terms"]) && is_array($GLOBALS["all_highlight_terms"]) ? $GLOBALS["all_highlight_terms"] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo '<span class="mc-fname">' . highlight_search($fileinfo, $all_terms) . '</span>'; // ★ (2026-10-31) 이름을 한 덩어리로(목록 보기에서 이름 왼쪽·칩 오른쪽 배치용 — 그리드는 모양 같음)
?> 
    <?php // ★ (2026-10-31) 노란 배지(📦·🎬·📁·🖼️ + 숫자) → 단위 글자 칩(이름 아래 줄, mc_folder_chips) — 보이는 조건은 같음
    // ★ (2026-11-04 00:01) NEW 딱지(폴더 내 가장 최신 파일 기준)를 칩 줄 안에 — 옆 칩과 같은 높이·간격(펜닐)
    $_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
    $_fc_new = ($_new_badge_hours > 0 && $newest_mtime > 0 && (time() - $newest_mtime) < ($_new_badge_hours * 3600)) ? '<span class="badge badge-danger mc-fnew">N</span>' : '';
    echo mc_folder_chips($has_pdf ? 'pdf' : ($has_epub ? 'epub' : ($has_txt ? 'txt' : 'zip')), (int)$zip_count, (int)$video_count, (int)($subdir_count ?? 0), (int)$pure_image_count, $_fc_new); ?>

</div>
				</div>
			</div>
	  </div>
				</a>
	<?php
		}
	}

	?>	
	</div>
	</div>
	<br>

<div class="grid">
<div class="row row-cols-2 row-cols-md-4">
	<?php
	if(count($file_list) > 0 && $endview > $startview){
		$_cover_ok = false; // ✅ [I/O 최적화] cover 중복 stat 방지 플래그 (폴더당 1회만 확인)
		for($i=$startview;$i<$endview;$i++) {
			if($i>$endview) {
				break;
			}
					$fileinfo_data = $file_list[$i - count($dir_list) - count($title_list)] ?? null;

// ✅ 캐시에서 배지 정보 가져오기
$cached_totalpage = null;
$cached_pageorder = null;
$cached_viewer = null;

if (is_array($fileinfo_data)) {
    $fileinfo = $fileinfo_data['name'] ?? 'unknown';
    $cached_totalpage = $fileinfo_data['totalpage'] ?? null;
    $cached_pageorder = $fileinfo_data['page_order'] ?? null;
    $cached_viewer = $fileinfo_data['viewer'] ?? null;
} else {
    $fileinfo = $fileinfo_data;
}

$zip_file = $dir . '/' . $fileinfo;

// ✅✅✅ [I/O 최적화] 캐시에 값이 없을 때만 .json 파일 읽기
// - 캐시에 totalpage가 있으면 스킵 (대부분의 경우)
// - 캐시에 없을 때만 fallback으로 .json 확인
if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $zip_file) && ($cached_totalpage === null || $cached_totalpage == 0)) {
    // 1순위: 파일명.json 확인
    $json_file = get_cache_json_path($zip_file);
    if (file_exists($json_file)) {
        $json_data = @json_decode(file_get_contents($json_file), true);
        if ($json_data && isset($json_data['totalpage']) && $json_data['totalpage'] > 0) {
            $cached_totalpage = $json_data['totalpage'];
            $cached_pageorder = $json_data['page_order'] ?? $cached_pageorder;
            $cached_viewer = $json_data['viewer'] ?? $cached_viewer;
        }
    }
    
    // 2순위: totalpage가 여전히 0이면 .image_files.json 확인
    if (($cached_totalpage ?? 0) == 0) {
        $image_cache = $zip_file . '.image_files.json';
        if (file_exists($image_cache)) {
            $image_files = @json_decode(file_get_contents($image_cache), true);
            if (is_array($image_files) && !empty($image_files)) {
                $cached_totalpage = count($image_files);
            }
        }
    }
}

if(strpos($zip_file, "_imgfolder") == true){
    $zip_file = str_replace("_imgfolder","", $zip_file);
    
    // 동영상 폴더인지 먼저 체크 - ✅ 캐시 활용
    // ★ (2026-11-03 22:54) 이미지 폴더 카드가 열 때마다 폴더 안 그림을 전부 훑었음(동영상 확인 scandir + NEW 배지용 그림마다 만든 시각) — 그림 수백 장 폴더 30개면 1~2초(운영 속도 기록 '바람의 검심')
    //   → 폴더 수정 시각이 같으면 옆 정보 파일(이름.image_files.json)에 적어 둔 값(has_video·newest_mtime)을 씀. 폴더에 그림이 들고 나면 수정 시각이 바뀌어 한 번 다시 셈
    $has_video_in_imgfolder = false;
    $_imgf_meta_file = $zip_file . '.image_files.json';
    $_imgf_meta = is_file($_imgf_meta_file) ? @json_decode((string)@file_get_contents($_imgf_meta_file), true) : null;
    $_imgf_meta = (is_array($_imgf_meta) && isset($_imgf_meta['totalpage'])) ? $_imgf_meta : null; // 그림 목록(배열)만 있는 옛 형식이면 쓰지 않음
    $_imgf_mtime = (int)@filemtime($zip_file);
    $_imgf_cnt = -1; // ★ (2026-11-03 23:34) 항목 수도 같이 봄(수정 시각이 안 바뀌는 환경 대비 — 다른 캐시와 같은 방식). 그림마다 시각을 묻는 것보다 훨씬 쌈
    $_imgf_newest_known = null;
    if (is_dir($zip_file)) {
        $video_check_cache = $zip_file . '/.folder_cache.json';
        $_imgf_vc_ok = false;
        if (is_file($video_check_cache)) {
            $vc = @json_decode(file_get_contents($video_check_cache), true);
            if ($vc && isset($vc['video_count']) && $vc['video_count'] > 0) {
                $has_video_in_imgfolder = true;
            }
            $_imgf_vc_ok = is_array($vc);
            if (is_array($vc) && (int)($vc['newest_mtime'] ?? 0) > 0) $_imgf_newest_known = (int)$vc['newest_mtime']; // 폴더 캐시에 있으면 그것(그림 폴더는 보통 0 — 그림은 안 셈)
        }
        if ($_imgf_newest_known !== null) {
            // 위 폴더 캐시로 끝
        } elseif ($_imgf_meta !== null && (int)($_imgf_meta['vchk'] ?? -1) === $_imgf_mtime && $_imgf_mtime > 0
                  && (int)($_imgf_meta['vcnt'] ?? -2) === ($_imgf_cnt = count(@scandir($zip_file) ?: []))) {
            if (!$_imgf_vc_ok) $has_video_in_imgfolder = !empty($_imgf_meta['has_video']);
            $_imgf_newest_known = (int)($_imgf_meta['newest_mtime'] ?? 0);
        } else {
            // 캐시 없으면 직접 체크 — 한 번 훑을 때 NEW 배지용 '가장 새 그림 시각'도 같이 셈
            $_imgf_newest = 0; $_imgf_pages = 0;
            $_imgf_list = @scandir($zip_file) ?: [];
            $_imgf_cnt = count($_imgf_list);
            foreach ($_imgf_list as $f) {
                if (is_video_file($f)) { if (!$_imgf_vc_ok) $has_video_in_imgfolder = true; }
                elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)) {
                    if (!str_ends_with($f, '.video_thumb.jpg') && $f !== '[cover].jpg') $_imgf_pages++; // ★ (2026-11-04 01:29) 쪽수는 정보 파일을 처음 만들 때와 같은 기준으로
                    if (!preg_match('/\[cover\]/i', $f) && !preg_match('/\.video_thumb\.jpg$/i', $f)) {
                        $_ct = get_file_created_time($zip_file . '/' . $f);
                        if ($_ct > $_imgf_newest) $_imgf_newest = $_ct;
                    }
                }
            }
            $_imgf_newest_known = $_imgf_newest;
            if ($_imgf_meta !== null && $_imgf_mtime > 0) { // 그 자리에 덮어씀(이름 바꾸기 없이 — 부모 폴더 수정 시각이 안 바뀌어 목록 캐시가 그대로)
                $_imgf_meta['vchk'] = $_imgf_mtime; $_imgf_meta['vcnt'] = $_imgf_cnt; $_imgf_meta['has_video'] = $has_video_in_imgfolder ? 1 : 0; $_imgf_meta['newest_mtime'] = $_imgf_newest;
                if ($_imgf_pages > 0) $_imgf_meta['totalpage'] = $_imgf_pages; // ★ (2026-11-04 00:28) 그림 수(쪽수)도 맞춤 — 그림을 넣어도 카드 'Np' 가 그대로였음(그 폴더에 직접 들어가야 바뀌었음)
                $_imgf_js = json_encode($_imgf_meta, JSON_UNESCAPED_UNICODE);
                if (is_string($_imgf_js)) @file_put_contents($_imgf_meta_file, $_imgf_js, LOCK_EX);
            }
        }
    }
    
    // 동영상이 있으면 이 블록 건너뛰기 (동영상 폴더로 처리해야 함)
    // ★ (2026-11-09 14:45) '동영상 있는 폴더는 폴더로'가 꺼져 있으면 건너뛰지 않음 — 그때는 그림 수로 그림 폴더가 된 것(설정 도움말대로)인데 여기서 건너뛰어 목록에서 아예 사라졌음(쪽 정보 개수에는 들어감). 켜져 있으면 예전 그대로
    if ($has_video_in_imgfolder && ($GLOBALS['video_folder_as_dir'] ?? true)) {
        continue;
    }
    
    $configfile = $zip_file.".image_files.json";
    
    // ✅ 캐시 파일이 없으면 생성
    if(is_file($configfile) === false) {
        // 이미지 파일 목록 생성 (.video_thumb.jpg 제외)
        $image_files = [];
        $has_video = false;
        $files = scandir($zip_file);
        foreach($files as $filename) {
            if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename) && !str_ends_with($filename, '.video_thumb.jpg') && $filename !== '[cover].jpg') { // ★ (2026-11-02 08:58) 표지는 쪽 수에서 빼기(뷰어와 같은 기준)
                $image_files[] = $filename;
            }
            // 동영상 파일 존재 여부 체크
            if(is_video_file($filename)) {
                $has_video = true;
            }
        }
        natsort($image_files);
        $image_files = array_values($image_files);
        
        // 이미지가 있고, 동영상이 없을 때만 .image_files.json 생성
        if(count($image_files) > 0 && !$has_video) {
            @file_put_contents($zip_file . '.image_files.json', json_encode($image_files, JSON_UNESCAPED_UNICODE), LOCK_EX);
            $first_image_path = $zip_file . '/' . $image_files[0];
            if(file_exists($first_image_path)) {
                $jpg_cover = file_get_contents($first_image_path);
                $size = getimagesizefromstring($jpg_cover);
                $cropimage = null;  // ✅ 버그 수정: 초기화
                
                // ★ (2026-10-30) 손상된 이미지면 imagecreatefromstring 이 false → imagecrop(false) 치명 오류로 목록이 중간에 잘림 — 한 번만 만들고 실패하면 썸네일 없이 진행
                $__gd = $size ? mc_gd_from_string($jpg_cover) : false; // ★ (2026-11-02 09:56) 너무 큰 그림은 건너뜀
                if ($__gd === false) $size = false;
                if($size && $size[0] > $size[1]) {
                    $x_point = ($size[0]/2) - $size[1];
                    $originimage = $__gd;
                    if($x_point > 0){
                        $cropimage = imagecrop($originimage, ['x' => $x_point, 'y' => 0, 'width' => $size[1], 'height' => $size[1]]);
                    } else {
                        $cropimage = imagecrop($originimage, ['x' => 0, 'y' => 0, 'width' => $size[1], 'height' => $size[1]]);
                    }
                    $originimage = $cropimage;
                    $cropimage = imagecreatetruecolor(400, 400);
                    imagecopyresampled($cropimage, $originimage, 0, 0, 0, 0, 400, 400, $size[1], $size[1]);
                    imagedestroy($originimage);
                    ob_start();
                    imagejpeg($cropimage, null, 75 );
                    imagedestroy($cropimage);
                    $cropimage = ob_get_contents();
                    ob_end_clean();
                } else if ($size) {
                    $originimage = $__gd;
                    $y_point = ($size[1] - $size[0])/2;
                    $cropimage = imagecrop($originimage, ['x' => 0, 'y' => 0, 'width' => $size[0], 'height' => $size[0]]);
                    $originimage = $cropimage;
                    $cropimage = imagecreatetruecolor(400, 400);
                    imagecopyresampled($cropimage, $originimage, 0, 0, 0, 0, 400, 400, $size[0], $size[0]);
                    imagedestroy($originimage);
                    ob_start();
                    imagejpeg($cropimage, null, 75 );
                    imagedestroy($cropimage);
                    $cropimage = ob_get_contents();
                    ob_end_clean();
                }
                
                $cache_data = array();
                $cache_data['totalpage'] = count($image_files);
                $cache_data['page_order'] = "0";
                $cache_data['viewer'] = "toon";
                $cache_data['thumbnail'] = ($cropimage) ? base64_encode($cropimage) : "";
                safe_json_write($configfile, $cache_data);
                
                // [cover].jpg 생성 - 이미지 폴더 안에
                if(!is_file($zip_file."/[cover].jpg")) {
                    if (!empty($cropimage)) @file_put_contents($zip_file."/[cover].jpg", $cropimage, LOCK_EX); // ★ (2026-10-30) 손상 이미지면 빈 표지 파일을 만들지 않음
                }
            }
        }
    }

    // ✅ 안전하게 JSON 읽기
    $json_data = [];
    $img_output = '';
    $totalpage = 0;
    $pageorder = "[ - ]";
    $viewer = "toon";
    
    if(is_file($configfile)) {
        // ★ (2026-11-03 23:34) 위에서 읽어 둔(필요하면 고친) 값을 그대로 — 다시 읽다가 다른 요청이 덮어쓰는 순간이면 빈 값(0p)이었음
        $json_data = ($_imgf_meta !== null) ? $_imgf_meta : (@json_decode((string)@file_get_contents($configfile), true) ?? []);
        $img_output = $json_data['thumbnail'] ?? '';
        $totalpage = $json_data['totalpage'] ?? 0;
        $pageorder = $json_data['page_order'] ?? '0';
        
        if((int)$pageorder == 0) {
            $pageorder = "[ - ]";
        } elseif((int)$pageorder == 1) {
            $pageorder = "[1|2]";
        } elseif((int)$pageorder == 2) {
            $pageorder = "[2|1]";
        }
        
        $viewer = $json_data['viewer'] ?? "toon";
    }
        
    // [cover].jpg 생성 - 이미지 폴더 안에 첫 번째 이미지 기반으로 생성
    if(!is_file($zip_file."/[cover].jpg")) {
        $cover_created = false;
        
        // 1순위: 캐시된 썸네일에서 생성
        if(!empty($img_output) && strlen($img_output) > 100) {
            $result = @file_put_contents($zip_file."/[cover].jpg", base64_decode($img_output), LOCK_EX);
            if($result !== false) $cover_created = true;
        }
        
        // 2순위: 폴더에서 첫 번째 이미지를 직접 찾아서 복사
        if(!$cover_created) {
            $folder_files = @scandir($zip_file);
            if($folder_files) {
                $img_files = [];
                foreach($folder_files as $f) {
                    if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f) && !str_ends_with($f, '.video_thumb.jpg') && $f !== '[cover].jpg') {
                        $img_files[] = $f;
                    }
                }
                if(count($img_files) > 0) {
                    natsort($img_files);
                    $img_files = array_values($img_files);
                    @copy($zip_file . '/' . $img_files[0], $zip_file."/[cover].jpg");
                }
            }
        }
    }
    ?>
    <a href='viewer.php?filetype=images&mode=<?php echo urlencode((string)$viewer); /* ★ (2026-11-09 14:15) 폴더 옆 정보 파일의 값이라 주소용으로 감쌈(따옴표로 속성을 빠져나갔음) */ ?>&file=<?php echo encode_url($getdir."/".str_replace("_imgfolder","", $fileinfo)) . $bidx_param; ?>'>
      <div class="col mb-3">
        <div class="card mc-folder-card text-black m-0 p-1" style="position:relative;" <?php if ($is_admin): ?>data-folder-path="<?php echo h($getdir."/".str_replace("_imgfolder","", $fileinfo)); ?>"<?php endif; ?>>
            <img src="thumb.php?file=<?php echo encode_url($getdir."/".str_replace("_imgfolder","", $fileinfo)) . $bidx_param . mc_file_cover_v($zip_file); /* ★ (2026-11-10 13:53 fix98) 직접 고른 표지면 주소가 바뀜 */ ?>" class="rounded card-img-top card-img" width="300" height="400" alt="thumbnail" loading="lazy">
<?php 
// ✅ 즐겨찾기 버튼
$_fav_file_path = $getdir."/".str_replace("_imgfolder","", $fileinfo);
$_is_favorite = mc_rec_find($favorites_arr, $current_bidx, $_fav_file_path) !== null; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 즐겨찾기만
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        data-fav-path="<?php echo htmlspecialchars((string)$_fav_file_path, ENT_QUOTES, 'UTF-8'); ?>"
        onclick="toggleFavorite(this, this.getAttribute('data-fav-path'), <?php echo (int)$bidx; ?>, event)"
        title="<?php echo $_is_favorite ? __h('ui_unfavorite') : __h('ui_add_favorite'); ?>">
    <?php echo $_is_favorite ? '⭐' : '⭐'; ?>
</button>
                        <div class="card-img-overlay m-1 p-0">
						<span class="badge badge-pill badge-success"><?php echo h($totalpage); ?>p</span>
						<span class="badge badge-pill badge-success" data-mcorder="<?php echo h($getdir."/".str_replace("_imgfolder","", $fileinfo)); ?>"><?php /* ★ (2026-11-05) 열릴 보기 방식(뷰어 '모드 기억' 설정 반영 — function.php mc_mv_order_for) / data-mcorder = 뷰어에서 돌아오면 새로고침 없이 바꿈 */ echo h(function_exists('mc_mv_order_for') ? mc_mv_order_label(mc_mv_order_for($getdir."/".str_replace("_imgfolder","", $fileinfo), $current_bidx)) : $pageorder); ?></span>
						<span class="badge badge-pill badge-success" data-mcmode="<?php echo h($getdir."/".str_replace("_imgfolder","", $fileinfo)); ?>"><?php /* ★ (2026-11-01 23:37) 열릴 모드(뷰어 설정 '모드 기억' 반영) / ★ (2026-11-02 01:43) data-mcmode = 뷰어에서 돌아오면 새로고침 없이 바꿈 */ echo h(function_exists('mc_mv_mode_for') ? mc_mv_mode_for($getdir."/".str_replace("_imgfolder","", $fileinfo), $current_bidx, $viewer) : $viewer); ?></span>
<?php 
// ✅ NEW 딱지 표시 (이미지 폴더 - 폴더 내 가장 최신 파일 기준)
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0) {
    $_imgfolder_path = $dir . "/" . str_replace("_imgfolder", "", $fileinfo);
    $_imgfolder_newest_mtime = 0;
    
    // 캐시에서 newest_mtime 확인
    $_imgfolder_cache_file = $_imgfolder_path . '/.folder_cache.json';
    if (!empty($_imgf_vc_ok) && isset($vc, $video_check_cache) && is_array($vc) && $video_check_cache === $_imgfolder_cache_file) {
        $_imgfolder_newest_mtime = (int)($vc['newest_mtime'] ?? 0); // ★ (2026-11-04 09:39) 위에서 이 카드를 위해 읽은 같은 파일 — 다시 읽지 않음
    } elseif (is_file($_imgfolder_cache_file)) {
        $_imgfolder_cache = @json_decode(file_get_contents($_imgfolder_cache_file), true);
        $_imgfolder_newest_mtime = (int)($_imgfolder_cache['newest_mtime'] ?? 0);
    }
    
    if ($_imgfolder_newest_mtime == 0 && isset($_imgf_newest_known) && $_imgf_newest_known !== null) $_imgfolder_newest_mtime = $_imgf_newest_known; // ★ (2026-11-03 22:54) 위에서 기억·계산한 값
    // 캐시에 없으면 폴더 내 파일 중 최신 mtime 확인
    if ($_imgfolder_newest_mtime == 0 && $_imgf_newest_known === null && is_dir($_imgfolder_path)) {
        if ($_imgfolder_dh = @opendir($_imgfolder_path)) {
            while (($_imgfolder_sf = readdir($_imgfolder_dh)) !== false) {
                if ($_imgfolder_sf[0] === '.') continue;
                // ✅ 이미지 파일만 newest_mtime 계산 (커버/썸네일 제외)
                if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $_imgfolder_sf) 
                    && !preg_match('/\[cover\]/i', $_imgfolder_sf)
                    && !preg_match('/\.video_thumb\.jpg$/i', $_imgfolder_sf)) {
                    $_sf_ctime = get_file_created_time($_imgfolder_path . '/' . $_imgfolder_sf);
                    if ($_sf_ctime > $_imgfolder_newest_mtime) $_imgfolder_newest_mtime = $_sf_ctime;
                }
            }
            closedir($_imgfolder_dh);
        }
    }
    
    if ($_imgfolder_newest_mtime > 0 && (time() - $_imgfolder_newest_mtime) < ($_new_badge_hours * 3600)) {
        echo '<span class="badge badge-pill badge-danger">N</span>';
    }
}
?>
                        </div>
            <div class="card-body m-0 p-0 text-center text-nowrap" style="text-overflow: ellipsis; overflow: hidden;">
                <?php // ★ (2026-11-03 15:00) 이미지 폴더 책 — 겹친 그림 아이콘(펜닐 선택) / ★ (2026-11-05) 공용 함수로·제목과 붙여서(목록 보기 배지 줄 맞춤) ?><?php echo mc_card_icon('images'); ?><span class="mc-ct"><?php 
$img_folder_name = preg_replace("/_imgfolder$/", "", $fileinfo);
$all_terms = isset($GLOBALS["all_highlight_terms"]) && is_array($GLOBALS["all_highlight_terms"]) ? $GLOBALS["all_highlight_terms"] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo highlight_search($img_folder_name, $all_terms);
?></span>
            </div>
        </div>
      </div>
    </a>
    <?php
} elseif(preg_match('/\.pdf$/i', $zip_file)){
		// PDF 썸네일 확인 (파일명.jpg)
		$pdf_thumb_file = '';
		$pdf_base_name = preg_replace('/\.pdf$/i', '', $zip_file);
		$pdf_possible_thumbs = [
		    mc_file_cover_path($zip_file), // ★ (2026-11-10 13:53, 펜닐 요청 — fix98) 관리자가 직접 고른 파일 표지 먼저
		    $pdf_base_name . '.jpg',
		    $pdf_base_name . '.jpeg', 
		    $pdf_base_name . '.png',
		    $pdf_base_name . '.webp',
		];
		foreach ($pdf_possible_thumbs as $pt) {
		    if (is_file($pt)) {
		        $pdf_thumb_file = $pt;
		        break;
		    }
		}
		?>
				<a href='viewer.php?filetype=pdf&file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>'>
				  <div class="col mb-3">
					<div class="card text-black m-0 p-1" style="position:relative;" <?php if ($is_admin): ?>data-file-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
<?php 
// ✅ 즐겨찾기 버튼
$_fav_file_path = $getdir."/".$fileinfo;
$_is_favorite = mc_rec_find($favorites_arr, $current_bidx, $_fav_file_path) !== null; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 즐겨찾기만
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        data-fav-path="<?php echo htmlspecialchars((string)$_fav_file_path, ENT_QUOTES, 'UTF-8'); ?>"
        onclick="toggleFavorite(this, this.getAttribute('data-fav-path'), <?php echo (int)$bidx; ?>, event)"
        title="<?php echo $_is_favorite ? __h('ui_unfavorite') : __h('ui_add_favorite'); ?>">
    <?php echo $_is_favorite ? '⭐' : '⭐'; ?>
</button>
<?php if (!empty($pdf_thumb_file)): ?>
<?php $pdf_thumb_data = base64_encode(file_get_contents($pdf_thumb_file)); $pdf_thumb_mime = mime_content_type($pdf_thumb_file); ?>
<img src="data:<?php echo $pdf_thumb_mime; ?>;base64,<?php echo $pdf_thumb_data; ?>" class="rounded card-img-top card-img loaded" style="height:200px; object-fit:cover;" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
<div class="rounded card-img-top card-img-placeholder align-items-center justify-content-center" style="height:200px; background:#f8f9fa; display:none; opacity:1;">
<?php // ★ (2026-10-31) FileStation 확장자 아이콘(img/file-icons) ?><img src="img/file-icons/ext-pdf.svg" alt="" width="96" height="96" style="width:96px;height:96px;" loading="lazy" draggable="false">
</div>
<?php else: ?>
<div class="rounded card-img-top card-img-placeholder d-flex align-items-center justify-content-center" style="height:200px; background:#f8f9fa; opacity:1;">
<?php // ★ (2026-10-31) FileStation 확장자 아이콘(img/file-icons) ?><img src="img/file-icons/ext-pdf.svg" alt="" width="96" height="96" style="width:96px;height:96px;" loading="lazy" draggable="false">
</div>
<?php endif; ?>
									<div class="card-img-overlay m-1 p-0">
									<span class="badge badge-pill badge-success">PDF FILE</span>
<?php 
// ✅ NEW 딱지 표시 (PDF)
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0) {
    $_pdf_ctime = get_file_created_time($zip_file);
    if ($_pdf_ctime && (time() - $_pdf_ctime) < ($_new_badge_hours * 3600)) {
        echo '<span class="badge badge-pill badge-danger">N</span>';
    }
}
?>
									</div>
						<div class="card-body m-0 p-0 text-center text-nowrap" style="text-overflow: ellipsis; overflow: hidden;">
							<span class="emoji-icon mc-ci">📄</span><?php /* ★ (2026-11-05) 아이콘은 그대로, 제목과 붙여서(간격은 CSS .mc-ci — 목록 보기 배지 줄 맞춤) */ ?><span class="mc-ct"><?php 
$pdf_display_name = preg_replace("/\.pdf$/i", "", $fileinfo);
$all_terms = isset($GLOBALS["all_highlight_terms"]) && is_array($GLOBALS["all_highlight_terms"]) ? $GLOBALS["all_highlight_terms"] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo highlight_search($pdf_display_name, $all_terms);
?></span>
						</div>
					</div>
				  </div>
				</a>

						<?php
	} elseif(is_video_file($zip_file)){
		// 동영상 파일 표시
		$file_size = @filesize($zip_file);
		$file_size_display = '';
		if ($file_size !== false) {
			if ($file_size >= 1073741824) {
				$file_size_display = number_format($file_size / 1073741824, 1) . ' GB';
			} elseif ($file_size >= 1048576) {
				$file_size_display = number_format($file_size / 1048576, 1) . ' MB';
			} else {
				$file_size_display = number_format($file_size / 1024, 1) . ' KB';
			}
		}
		$video_ext = strtoupper(pathinfo($fileinfo, PATHINFO_EXTENSION));
		
		// 썸네일 확인/생성
		$video_thumb = get_video_thumbnail($zip_file);
		if (!$video_thumb && !empty($ffmpeg_path)) {
			$video_thumb = generate_video_thumbnail($zip_file, $ffmpeg_path, 5);
		}
		?>
		<a href='viewer.php?filetype=video&file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>'>
		  <div class="col mb-3">
			<div class="card text-black m-0 p-1" style="position:relative;" <?php if ($is_admin): ?>data-file-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
<?php 
// ✅ 즐겨찾기 버튼
$_fav_file_path = $getdir."/".$fileinfo;
$_is_favorite = mc_rec_find($favorites_arr, $current_bidx, $_fav_file_path) !== null; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 즐겨찾기만
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        data-fav-path="<?php echo htmlspecialchars((string)$_fav_file_path, ENT_QUOTES, 'UTF-8'); ?>"
        onclick="toggleFavorite(this, this.getAttribute('data-fav-path'), <?php echo (int)$bidx; ?>, event)"
        title="<?php echo $_is_favorite ? __h('ui_unfavorite') : __h('ui_add_favorite'); ?>">
    <?php echo $_is_favorite ? '⭐' : '⭐'; ?>
</button>
<?php $__mc_fcv = mc_file_cover_v($zip_file); /* ★ (2026-11-10 13:53, 펜닐 요청 — fix98) 직접 고른 파일 표지 */ if (($video_thumb && file_exists($video_thumb)) || $__mc_fcv !== ''): ?>
<!-- 동영상 썸네일 이미지 - 이미지 ZIP과 동일한 크기 -->
<img src="index.php?thumb=video&file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param . $__mc_fcv; ?>" class="rounded card-img-top card-img" width="300" height="400" alt="video thumbnail">
<?php else: ?>
<!-- 동영상 아이콘 SVG - 이미지 ZIP과 동일한 크기 -->
<div class="rounded card-img-top card-img" style="display: flex; align-items: center; justify-content: center; background: #e9e9e9; min-height: 200px;">
	<svg viewBox="0 0 56 56" style="width: 100px; height: 100px;" xmlns="http://www.w3.org/2000/svg">
	<g>
		<path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074
			c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/>
		<polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/>
		<path style="fill:#556080;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/>
		<g>
			<path style="fill:#FFFFFF;" d="M22.5,48.5l8-5l-8-5V48.5z"/>
			<path style="fill:#FFFFFF;" d="M28,42c-4.411,0-8,3.589-8,8s3.589,8,8,8s8-3.589,8-8S32.411,42,28,42z M28,56
				c-3.309,0-6-2.691-6-6s2.691-6,6-6s6,2.691,6,6S31.309,56,28,56z"/>
		</g>
		<path style="fill:#556080;" d="M24.5,28.5c0,1.657,1.343,3,3,3s3-1.343,3-3v-10c0-1.657-1.343-3-3-3s-3,1.343-3,3V28.5z"/>
		<path style="fill:#556080;" d="M38.5,23.5h-3v5h3c1.381,0,2.5-1.119,2.5-2.5S39.881,23.5,38.5,23.5z"/>
		<path style="fill:#556080;" d="M17.5,23.5c-1.381,0-2.5,1.119-2.5,2.5s1.119,2.5,2.5,2.5h3v-5H17.5z"/>
	</g>
	</svg>
</div>
<?php endif; ?>
							<div class="card-img-overlay m-1 p-0">
							<span class="badge badge-pill badge-primary"><?php echo h($video_ext); ?></span>
							<?php if ($file_size_display): ?>
							<span class="badge badge-pill badge-info badge-filesize"><?php echo h($file_size_display); ?></span>
							<?php endif; ?>
<?php 
// ✅ NEW 딱지 표시 (동영상)
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0) {
    $_video_ctime = get_file_created_time($zip_file);
    if ($_video_ctime && (time() - $_video_ctime) < ($_new_badge_hours * 3600)) {
        echo '<span class="badge badge-pill badge-danger">N</span>';
    }
}
?>
							</div>
				<div class="card-body m-0 p-0 text-center text-nowrap" style="text-overflow: ellipsis; overflow: hidden;">
					<span class="mc-ci"><svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-play-circle" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path fill-rule="evenodd" d="M6.271 5.055a.5.5 0 0 1 .52.038l3.5 2.5a.5.5 0 0 1 0 .814l-3.5 2.5A.5.5 0 0 1 6 10.5v-5a.5.5 0 0 1 .271-.445z"/></svg></span><?php /* ★ (2026-11-05) 아이콘은 그대로, 제목과 붙여서(간격은 CSS .mc-ci) */ ?><span class="mc-ct"><?php 
// 동영상 파일명 하이라이트
$video_all_terms = isset($GLOBALS['all_highlight_terms']) && is_array($GLOBALS['all_highlight_terms']) 
    ? $GLOBALS['all_highlight_terms'] 
    : [];
if (empty($video_all_terms) && !empty($q)) {
    $video_all_terms = [$q];
}
echo highlight_search($fileinfo, $video_all_terms); 
?></span>
				</div>
			</div>
		  </div>
		</a>

					<?php
	} elseif(is_File($zip_file) == true){
						// 각 ZIP 파일마다 변수 초기화
						$img_output = "";
						$totalpage = 0;
						$pageorder = "[ - ]";
						$viewer = "toon";
						$is_video_archive = false;
						$json_data = [];
						$configfile = '';  // 초기화
						
						// 파일 확장자 확인
						$file_ext = strtolower(pathinfo($zip_file, PATHINFO_EXTENSION));
						
						// ✅ TXT/EPUB 파일은 별도 처리 (ZipArchive 불필요)
						if ($file_ext === 'txt' || $file_ext === 'epub' || $file_ext === 'hwp' || $file_ext === 'hwpx') {
							$totalpage = 1;
							$viewer = ($file_ext === 'hwpx') ? 'hwp' : $file_ext;  // hwpx도 hwp_viewer.php 사용
							$pageorder = "[ - ]";
							// TXT/EPUB 파일 아이콘
							$file_icon = ($file_ext === 'txt') ? '📝' : (($file_ext === 'epub') ? '📖' : '📄');
							
							// 진행률 확인
							$file_progress_key = ltrim($getdir . "/" . $fileinfo, '/');
							$file_progress_percent = 0;
							$__fpe = ($file_ext === 'epub') ? mc_prog_get($epub_progress, $current_bidx, $file_progress_key) : (($file_ext === 'txt') ? mc_prog_get($txt_progress, $current_bidx, $file_progress_key) : null); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 읽던 위치(없으면 예전 키)
							if ($file_ext === 'epub' && is_array($__fpe)) {
							    $file_progress_percent = (int)($__fpe['percent'] ?? 0);
							} elseif ($file_ext === 'txt' && is_array($__fpe)) {
							    $file_progress_percent = (int)($__fpe['percent'] ?? 0);
							}
							
							// 썸네일 확인 (파일명.jpg 또는 파일명.txt.jpg 등)
							$thumb_file = '';
							$base_name = preg_replace('/\.(txt|epub|hwpx?)$/i', '', $zip_file); // 확장자 제거
							$possible_thumbs = [
							    mc_file_cover_path($zip_file), // ★ (2026-11-10 14:55, 펜닐 요청 — fix99) 관리자가 직접 고른 파일 표지 먼저
							    $base_name . '.jpg',
							    $base_name . '.jpeg', 
							    $base_name . '.png',
							    $base_name . '.webp',
							    $zip_file . '.jpg',  // 소설.txt.jpg
							    $zip_file . '.jpeg',
							    $zip_file . '.png',
							    $zip_file . '.webp',
							];
							foreach ($possible_thumbs as $pt) {
							    if (is_file($pt)) {
							        $thumb_file = $pt;
							        break;
							    }
							}
							?>
							<!-- TXT/EPUB 파일 표시 -->
							<a href='<?php echo $viewer; ?>_viewer.php?file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>' rel="noopener noreferrer">
							  <div class="col mb-3">
								<div class="card text-black m-0 p-1" style="position:relative;" <?php if ($is_admin): ?>data-file-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
<?php 
// ✅ 즐겨찾기 버튼
$_fav_file_path = $getdir."/".$fileinfo;
$_is_favorite = mc_rec_find($favorites_arr, $current_bidx, $_fav_file_path) !== null; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 즐겨찾기만
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        data-fav-path="<?php echo htmlspecialchars((string)$_fav_file_path, ENT_QUOTES, 'UTF-8'); ?>"
        onclick="toggleFavorite(this, this.getAttribute('data-fav-path'), <?php echo (int)$bidx; ?>, event)"
        title="<?php echo $_is_favorite ? __h('ui_unfavorite') : __h('ui_add_favorite'); ?>">
    <?php echo $_is_favorite ? '⭐' : '⭐'; ?>
</button>
									<?php if (!empty($thumb_file)): ?>
								<?php $thumb_data = base64_encode(file_get_contents($thumb_file)); $thumb_mime = mime_content_type($thumb_file); ?>
									<img src="data:<?php echo $thumb_mime; ?>;base64,<?php echo $thumb_data; ?>" class="rounded card-img-top card-img loaded" style="height:200px; object-fit:cover;" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
									<div class="rounded card-img-top card-img-placeholder align-items-center justify-content-center" style="height:200px; background:#f8f9fa; display:none; opacity:1;">
										<?php if ($file_ext === 'epub'): ?>
										<!-- EPUB 기본 SVG 아이콘 -->
										<svg viewBox="0 0 56 56" style="width:80px;height:80px;" xmlns="http://www.w3.org/2000/svg">
											<g>
												<path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/>
												<polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/>
												<path style="fill:#8697CB;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/>
												<text x="28" y="51" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="bold" fill="#FFFFFF" text-anchor="middle">EPUB</text>
												<path style="fill:#8697CB;" d="M15,18h26v2H15V18z M15,24h26v2H15V24z M15,30h20v2H15V30z"/>
											</g>
											</svg>
										<?php elseif ($file_ext === 'hwp' || $file_ext === 'hwpx'): ?>
										<!-- HWP 아이콘 — ★ (2026-10-31) FileStation 확장자 아이콘(한글 hwp·hwpx) -->
										<img src="img/file-icons/ext-<?php echo $file_ext === 'hwpx' ? 'hwpx' : 'hwp'; ?>.svg" alt="" width="96" height="96" style="width:96px;height:96px;" loading="lazy" draggable="false">
										<?php else: ?>
										<!-- TXT 기본 SVG 아이콘 -->
											<svg viewBox="0 0 56 56" style="width:80px;height:80px;" xmlns="http://www.w3.org/2000/svg">
											<g>
												<path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/>
												<polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/>
												<path style="fill:#95A5A5;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/>
												<text x="28" y="51" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="bold" fill="#FFFFFF" text-anchor="middle">TXT</text>
												<path style="fill:#95A5A5;" d="M15,18h26v2H15V18z M15,24h26v2H15V24z M15,30h20v2H15V30z"/>
											</g>
											</svg>
										<?php endif; ?>
									</div>
									<?php else: ?>
									<div class="rounded card-img-top card-img-placeholder d-flex align-items-center justify-content-center" style="height:200px; background:#f8f9fa; opacity:1;">
										<?php if ($file_ext === 'epub'): ?>
										<!-- EPUB 기본 SVG 아이콘 -->
										<svg viewBox="0 0 56 56" style="width:80px;height:80px;" xmlns="http://www.w3.org/2000/svg">
											<g>
												<path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/>
												<polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/>
												<path style="fill:#8697CB;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/>
												<text x="28" y="51" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="bold" fill="#FFFFFF" text-anchor="middle">EPUB</text>
												<path style="fill:#8697CB;" d="M15,18h26v2H15V18z M15,24h26v2H15V24z M15,30h20v2H15V30z"/>
											</g>
											</svg>
										<?php elseif ($file_ext === 'hwp' || $file_ext === 'hwpx'): ?>
										<!-- HWP 아이콘 — ★ (2026-10-31) FileStation 확장자 아이콘(한글 hwp·hwpx) -->
										<img src="img/file-icons/ext-<?php echo $file_ext === 'hwpx' ? 'hwpx' : 'hwp'; ?>.svg" alt="" width="96" height="96" style="width:96px;height:96px;" loading="lazy" draggable="false">
										<?php else: ?>
										<!-- TXT 기본 SVG 아이콘 -->
											<svg viewBox="0 0 56 56" style="width:80px;height:80px;" xmlns="http://www.w3.org/2000/svg">
											<g>
												<path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/>
												<polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/>
												<path style="fill:#95A5A5;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/>
												<text x="28" y="51" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="bold" fill="#FFFFFF" text-anchor="middle">TXT</text>
												<path style="fill:#95A5A5;" d="M15,18h26v2H15V18z M15,24h26v2H15V24z M15,30h20v2H15V30z"/>
											</g>
											</svg>
										<?php endif; ?>
									</div>
									<?php endif; ?>
									<div class="card-img-overlay m-1 p-0">
										<span class="badge badge-pill badge-info badge-filetype"><?php echo strtoupper($file_ext); ?></span><?php if ($file_progress_percent > 0): ?><span class="badge badge-pill badge-progress <?php echo $file_progress_percent >= 100 ? 'badge-success' : 'badge-warning'; ?>"><?php echo $file_progress_percent; ?>%</span><?php endif; ?><?php // ★ (2026-11-04 10:51) 문서 카드도 N — 폴더 카드는 epub·txt 도 새 파일로 세는데 안에서는 어느 것이 새것인지 표시가 없었음 / ★ (2026-11-04 11:14) 압축 카드와 같은 기준(파일 자체 날짜 = 목록 캐시 time) — 펜닐: 폴더 N 은 올린 시각, 파일 N 은 받은 날짜가 맞음
$__doc_nb = (int)get_app_settings('new_badge_hours', 24); $__doc_add = (get_app_settings('new_badge_basis', 'file') === 'added'); $__doc_ct = (int)(is_array($fileinfo_data ?? null) ? ($fileinfo_data[$__doc_add ? 'ctime' : 'time'] ?? 0) : 0); if ($__doc_nb > 0 && !$__doc_ct) $__doc_ct = (int)($__doc_add ? get_file_created_time($dir . '/' . $fileinfo) : @filemtime($dir . '/' . $fileinfo)); /* ★ (2026-11-04 11:18) 관리자 N 기준 따름 */
if ($__doc_nb > 0 && $__doc_ct && (time() - $__doc_ct) < ($__doc_nb * 3600)) echo '<span class="badge badge-pill badge-danger">N</span>'; ?>
									</div>
									<div class="card-body m-0 p-0 text-center" style="text-overflow: ellipsis; overflow: hidden; white-space: normal; line-height: 1.05;">
										<span class="emoji-icon mc-ci"><?php echo $file_icon; ?></span><?php /* ★ (2026-11-05) 아이콘은 그대로, 제목과 붙여서(간격은 CSS .mc-ci) */ ?><span class="mc-ct"><?php 
										// 확장자 제거한 파일명
										$display_name = preg_replace('/\.(txt|epub)$/i', '', $fileinfo);
										$all_terms = isset($GLOBALS['all_highlight_terms']) && is_array($GLOBALS['all_highlight_terms']) 
										    ? $GLOBALS['all_highlight_terms'] : [];
										if (empty($all_terms) && !empty($q)) $all_terms = [$q];
										echo highlight_search($display_name, $all_terms);
										?></span>
									</div>
								</div>
							  </div>
							</a>
							<?php
							continue; // ✅ HWP/TXT/EPUB 처리 후 다음 파일로 (중복 렌더링 방지)
						// ✅ Office 파일 처리 (doc/docx/xls/xlsx/ppt/pptx)
						} elseif ($file_ext === 'doc' || $file_ext === 'docx' || $file_ext === 'xls' || $file_ext === 'xlsx' || $file_ext === 'ppt' || $file_ext === 'pptx') {
							$totalpage = 1;
							$viewer = 'office';
							$pageorder = "[ - ]";
							
							// Office 파일 색상
							$office_colors = [
								'doc' => '#2b579a', 'docx' => '#2b579a',
								'xls' => '#217346', 'xlsx' => '#217346',
								'ppt' => '#d24726', 'pptx' => '#d24726',
							];
							$office_color = $office_colors[$file_ext] ?? '#666';
							
							// 실제 Office 스타일 SVG 아이콘
							$office_svgs = [
								'doc' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#2b579a"/><path d="M2.5 4h2l1.5 6 2-6h2l2 6 1.5-6h2l-2.5 8h-2L9 6 7 12H5L2.5 4z" fill="#fff"/></svg>',
								'docx' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#2b579a"/><path d="M2.5 4h2l1.5 6 2-6h2l2 6 1.5-6h2l-2.5 8h-2L9 6 7 12H5L2.5 4z" fill="#fff"/></svg>',
								'xls' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#217346"/><path d="M4 4l3 4-3 4h2.5l2-2.7 2 2.7H13l-3-4 3-4h-2.5l-2 2.7-2-2.7H4z" fill="#fff"/></svg>',
								'xlsx' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#217346"/><path d="M4 4l3 4-3 4h2.5l2-2.7 2 2.7H13l-3-4 3-4h-2.5l-2 2.7-2-2.7H4z" fill="#fff"/></svg>',
								'ppt' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#d24726"/><path d="M5 4h4a3 3 0 010 6H7v2H5V4zm2 4h1.5a1 1 0 000-2H7v2z" fill="#fff"/></svg>',
								'pptx' => '<svg viewBox="0 0 16 16" style="width:14px;height:14px;vertical-align:middle;"><rect width="16" height="16" rx="2" fill="#d24726"/><path d="M5 4h4a3 3 0 010 6H7v2H5V4zm2 4h1.5a1 1 0 000-2H7v2z" fill="#fff"/></svg>',
							];
							$file_icon_svg = $office_svgs[$file_ext] ?? '';
							
							// 썸네일 확인
							$thumb_file = '';
							$base_name = preg_replace('/\.(docx?|xlsx?|pptx?)$/i', '', $zip_file);
							$possible_thumbs = [
								mc_file_cover_path($zip_file), // ★ (2026-11-10 14:55, 펜닐 요청 — fix99) 관리자가 직접 고른 파일 표지 먼저
								$base_name . '.jpg',
								$base_name . '.jpeg', 
								$base_name . '.png',
								$base_name . '.webp',
								$zip_file . '.jpg',
								$zip_file . '.jpeg',
								$zip_file . '.png',
								$zip_file . '.webp',
							];
							foreach ($possible_thumbs as $pt) {
								if (is_file($pt)) {
									$thumb_file = $pt;
									break;
								}
							}
							?>
							<!-- Office 파일 표시 -->
							<a href='office_viewer.php?file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>' rel="noopener noreferrer">
							  <div class="col mb-3">
								<div class="card text-black m-0 p-1" <?php if ($is_admin): ?>data-file-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
									<?php if (!empty($thumb_file)): ?>
								<?php $thumb_data = base64_encode(file_get_contents($thumb_file)); $thumb_mime = mime_content_type($thumb_file); ?>
									<img src="data:<?php echo $thumb_mime; ?>;base64,<?php echo $thumb_data; ?>" class="rounded card-img-top card-img loaded" style="height:200px; object-fit:cover;" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
									<div class="rounded card-img-top card-img-placeholder align-items-center justify-content-center" style="height:200px; background:#f8f9fa; display:none; opacity:1;">
									<?php else: ?>
									<div class="rounded card-img-top card-img-placeholder d-flex align-items-center justify-content-center" style="height:200px; background:#f8f9fa; opacity:1;">
									<?php endif; ?>
										<!-- Office 아이콘 — ★ (2026-10-31) FileStation 확장자 아이콘(워드·엑셀·파워포인트) -->
										<img src="img/file-icons/ext-<?php echo h($file_ext); ?>.svg" alt="" width="96" height="96" style="width:96px;height:96px;" loading="lazy" draggable="false">
									</div>
									<div class="card-img-overlay m-1 p-0">
										<span class="badge badge-pill badge-info badge-filetype" style="background:<?php echo $office_color; ?>;"><?php echo strtoupper($file_ext); ?></span><?php // ★ (2026-11-04 10:51) 문서 카드도 N — 폴더 카드는 epub·txt 도 새 파일로 세는데 안에서는 어느 것이 새것인지 표시가 없었음 / ★ (2026-11-04 11:14) 압축 카드와 같은 기준(파일 자체 날짜 = 목록 캐시 time) — 펜닐: 폴더 N 은 올린 시각, 파일 N 은 받은 날짜가 맞음
$__doc_nb = (int)get_app_settings('new_badge_hours', 24); $__doc_add = (get_app_settings('new_badge_basis', 'file') === 'added'); $__doc_ct = (int)(is_array($fileinfo_data ?? null) ? ($fileinfo_data[$__doc_add ? 'ctime' : 'time'] ?? 0) : 0); if ($__doc_nb > 0 && !$__doc_ct) $__doc_ct = (int)($__doc_add ? get_file_created_time($dir . '/' . $fileinfo) : @filemtime($dir . '/' . $fileinfo)); /* ★ (2026-11-04 11:18) 관리자 N 기준 따름 */
if ($__doc_nb > 0 && $__doc_ct && (time() - $__doc_ct) < ($__doc_nb * 3600)) echo '<span class="badge badge-pill badge-danger">N</span>'; ?>
									</div>
									<div class="card-body m-0 p-0 text-center" style="text-overflow: ellipsis; overflow: hidden; white-space: normal; line-height: 1.05;">
										<span class="mc-ci"><?php echo $file_icon_svg; /* ★ (2026-11-05) 제목과 붙여서(간격은 CSS .mc-ci) — 오피스 색 아이콘은 그대로 */ ?></span><span class="mc-ct"><?php 
										$display_name = preg_replace('/\.(docx?|xlsx?|pptx?)$/i', '', $fileinfo);
										$all_terms = isset($GLOBALS['all_highlight_terms']) && is_array($GLOBALS['all_highlight_terms']) 
										    ? $GLOBALS['all_highlight_terms'] : [];
										if (empty($all_terms) && !empty($q)) $all_terms = [$q];
										echo highlight_search($display_name, $all_terms);
										?></span>
									</div>
								</div>
							  </div>
							</a>
							<?php
							continue; // ✅ Office 파일 처리 후 다음 파일로 (중복 렌더링 방지)
						} else {
						// configfile 경로 설정 (압축 파일만)
						$configfile = get_cache_json_path($zip_file);
						
						// ✅ .image_files.json 경로
						$image_files_cache = $zip_file . '.image_files.json';

						// ✅ [수정] 동영상 ZIP은 .image_files.json이 아니라 .video_files.json을 가짐
						//  - 기존: 동영상 ZIP은 is_file(.image_files.json)=false라 캐시 통과 실패
						//    → 매번 ZipArchive를 열고 .json/.video_files.json을 재기록
						//    → 폴더 mtime 갱신 → 다음 진입 시 cache_old → 매번 재스캔(느림)
						//  - 수정: 캐시 viewer가 video이거나 is_video_archive면 .video_files.json 존재로 판정
						$_is_cached_video = ($cached_viewer === 'video' || ($fileinfo_data['is_video_archive'] ?? false));
						$_cache_meta_exists = $_is_cached_video
							? is_file($zip_file . '.video_files.json')
							: is_file($image_files_cache);

						// ✅ 캐시된 배지 정보가 있고 메타 캐시(.image_files.json 또는 .video_files.json)도 있으면 바로 사용
						if ($cached_totalpage !== null && $cached_viewer !== null && $cached_pageorder !== null && $_cache_meta_exists) {
							$totalpage = $cached_totalpage;
							$viewer = $cached_viewer;
							$pageorder = $cached_pageorder;
							
							// ✅ 동영상 ZIP 체크
							if ($viewer === 'video' || ($fileinfo_data['is_video_archive'] ?? false)) {
								$is_video_archive = true;
							}
							
							if((int)$pageorder == 0) {
								$pageorder = "[ - ]";
							} elseif((int)$pageorder == 1) {
								$pageorder = "[1|2]";
							} elseif((int)$pageorder == 2) {
								$pageorder = "[2|1]";
							}
							
							goto display_zip_item;
						}
						

			// ✅ [I/O 최적화] 캐시 파일이 둘 다 있으면 ZIP 열지 않음
			// - 기존: 세션이 비어있으면 무조건 ZIP 열기 → 첫 접속 매우 느림
			// - 개선: .json과 .image_files.json 둘 다 있으면 스킵
			$image_files_cache_check = $zip_file . '.image_files.json';
			if(empty($configfile) || is_File($configfile) === false || !is_file($image_files_cache_check)) {
					// ✅ [RAR/7Z 지원] 압축 종류 판별 (ZIP=내장, RAR/7Z=archive_handler)
					$_arc_ext = strtolower(pathinfo($zip_file, PATHINFO_EXTENSION));
					$_is_rar7z = in_array($_arc_ext, ['rar', 'cbr', '7z', 'cb7']);
					$_arc = null; // RAR/7Z용 핸들러

					// ── [RAR/7z 처리] ──
					if ($_is_rar7z) {
					}

					$zip = new ZipArchive;
					$_arc_opened = $_is_rar7z ? (($_arc = archive_open_handler($zip_file)) !== false) : ($zip->open($zip_file) === TRUE);

					// ★ (2026-10-21) 압축 열기 실패 처리. 기존 `== TRUE` 느슨한 비교는 ZipArchive::open()의 실패 코드(정수, 예: 19)를
					//   참으로 판정해, 열지 못한 zip을 "이미지 0개"로 보고 json에 viewer=video·totalpage=0 을 저장(캐시 오염)한 뒤
					//   close()에서 PHP 8 ValueError(Fatal)로 그 아래 목록이 잘렸음(손상 파일·업로드 직후 백신 잠금 등).
					//   → 엄격 비교로 판정하고, 실패 시 아무 파일도 쓰지 않은 채 이 카드만 기본값으로 표시(다음 방문 때 다시 시도).
					//   동영상 zip 조기 종료와 같은 방식으로 표시용 변수를 직접 지정 — 이전 카드의 $json_data/$img_output 이 섞이지 않게 초기화.
					if (!$_arc_opened) {
						unset($zip);
						$img_output = "";
						$json_data = [];
						$totalpage = (int)($cached_totalpage ?? 0);
						$pageorder = "[ - ]";
						$viewer = "toon";
						$is_video_archive = false;
						goto display_zip_item;
					}

					if ($_is_rar7z) {
					}

					if ($_arc_opened) {
						$thumbnail_index = 0;
						$find_img = array();
						$zip_numfile = $_is_rar7z ? 0 : $zip->numFiles;

// ★★★ .image_files.json 생성 추가 ★★★
        $image_files = array();
        $video_files = array();
        
        if ($_is_rar7z) {
            // ✅ RAR/7Z: 핸들러로 전체 파일 목록을 한 번에 가져옴
            $find_img = archive_list_all($_arc);
            foreach ($find_img as $_nm) {
                if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $_nm)) {
                    $image_files[] = $_nm;
                }
                if (is_video_file($_nm)) {
                    $video_files[] = $_nm;
                }
            }
        } else {
        for ($findthumb = 0; $findthumb < $zip_numfile; $findthumb++) {
            $find_img[$findthumb] = mc_zip_name($zip, $findthumb);
            
            // 이미지 파일만 따로 수집
            if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $find_img[$findthumb])) {
                $image_files[] = $find_img[$findthumb];
            }
            // 동영상 파일 수집
            if (is_video_file($find_img[$findthumb])) {
                $video_files[] = $find_img[$findthumb];
            }
        }
        }
        
        // ★★★ 동영상만 있는 ZIP인 경우 조기 종료 ★★★
        if (count($image_files) == 0 && count($video_files) > 0) {
            // 동영상 ZIP으로 표시
            $cache_data = array();
            $cache_data['totalpage'] = count($video_files);
            $cache_data['page_order'] = "0";
            $cache_data['viewer'] = "video";
            $cache_data['thumbnail'] = "";
            $cache_data['is_video_archive'] = true;
            safe_json_write($configfile, $cache_data);
            
            // video_files.json 저장
            natsort($video_files);
            $video_files = array_values($video_files);
            @file_put_contents($zip_file . '.video_files.json', json_encode($video_files, JSON_UNESCAPED_UNICODE), LOCK_EX);
            
            if (!$_is_rar7z) { $zip->close(); unset($zip); }
            gc_collect_cycles();
            $_SESSION['zip_cache_generated'] = true;
            
            // 표시용 변수 설정
            $img_output = "";
            $totalpage = count($video_files);
            $pageorder = "[ - ]";
            $viewer = "video";
            $is_video_archive = true;
            goto display_zip_item;
        }
        
        $find_img = n_sort($find_img);
        
        // ★★★ .image_files.json 파일 저장 ★★★
        natsort($image_files);
        $image_files = array_values($image_files);
        $image_files_cache = $zip_file . '.image_files.json';
        @file_put_contents($image_files_cache, json_encode($image_files, JSON_UNESCAPED_UNICODE), LOCK_EX);
        
        // ★★★ .video_files.json 파일 저장 ★★★
        if (!empty($video_files)) {
            natsort($video_files);
            $video_files = array_values($video_files);
            $video_files_cache = $zip_file . '.video_files.json';
            @file_put_contents($video_files_cache, json_encode($video_files, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
// ★★★ 여기까지 추가 ★★★

						if (!$_is_rar7z) {
							for ($findthumb = 0; $findthumb < $zip_numfile; $findthumb++) {
								$find_img[$findthumb] = mc_zip_name($zip, $findthumb);
							}
						}
						$find_img = n_sort($find_img);
						$thumbnail_index = null; // 명시적 초기화
						$thumbnail_filename = null; // 실제 파일명 저장
						// ✅ [RAR/7Z 지원] $zip_numfile은 RAR/7Z에서 0이므로 $find_img 개수로 순회
						//    (ZIP/RAR/7Z 공통: $find_img는 위에서 이미 채워짐)
						$_scan_count = count($find_img);
						for ($findthumb = 0; $findthumb < $_scan_count; $findthumb++) {
							$current_file = $find_img[$findthumb];
							$lower_file = strtolower($current_file);
							// 이미지 파일 확장자 체크
							if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $lower_file)) {
								$thumbnail_filename = $current_file;
								break;
							}
						}						

						// 이미지가 없는 ZIP (동영상만 있는 경우) - 기본 썸네일 사용
						if ($thumbnail_filename === null) {
							$cache_data = array();
							$cache_data['totalpage'] = count($video_files);  // ✅ 폴더 제외, 동영상 파일만
							$cache_data['page_order'] = "0";
							$cache_data['viewer'] = "video";
							$cache_data['thumbnail'] = "";
							$cache_data['is_video_archive'] = true;
							safe_json_write($configfile, $cache_data);
							if (!$_is_rar7z) { $zip->close(); unset($zip); }
							gc_collect_cycles();
						} else {
						
						// 이미지 데이터 가져오기
						$img_data = $_is_rar7z ? archive_extract_one($_arc, $thumbnail_filename) : mc_zip_from_name($zip, $thumbnail_filename);
						if ($img_data === false) {
							// 실패시 빈 썸네일로 저장
							// ★ (2026-10-31) 아래 공통 저장부가 앞 카드의 $cropimage 를 이 압축의 표지로 저장하던 문제(암호 압축에 남의 표지가 붙음) — 비워 둠
							$cropimage = null;
							$cache_data = array();
							$cache_data['totalpage'] = count($image_files);  // ✅ 폴더 제외, 이미지 파일만
							$cache_data['page_order'] = "0";
							$cache_data['viewer'] = "toon";
							$cache_data['thumbnail'] = "";
							safe_json_write($configfile, $cache_data);
							if (!$_is_rar7z) { $zip->close(); unset($zip); }
							gc_collect_cycles();
						} else {

						$cropimage = null;  // ✅ 버그 수정: 이전 파일의 썸네일 잔존 방지
						$size = getimagesizefromstring($img_data);
						// ★ (2026-10-30) 손상된 이미지면 imagecreatefromstring 이 false → imagecrop(false) 치명 오류로 목록이 중간에 잘림 — 한 번만 만들고 실패하면 썸네일 없이 진행
						$__gd = $size ? mc_gd_from_string($img_data) : false; // ★ (2026-11-02 09:56) 너무 큰 그림은 건너뜀
						if ($__gd === false) $size = false;
						if($size && $size[0] > $size[1]) {
							$x_point = ($size[0]/2) - $size[1];
							$originimage = $__gd;
								if($x_point > 0){
									$cropimage = imagecrop($originimage, ['x' => $x_point, 'y' => 0, 'width' => $size[1], 'height' => $size[1]]);
								} else {
									$cropimage = imagecrop($originimage, ['x' => 0, 'y' => 0, 'width' => $size[1], 'height' => $size[1]]);
								}
							$originimage = $cropimage;
							$cropimage = imagecreatetruecolor(400, 400);
							imagecopyresampled($cropimage, $originimage, 0, 0, 0, 0, 400, 400, $size[1], $size[1]);
							imagedestroy($originimage);
							ob_start();
							imagejpeg($cropimage, null, 75 );
							imagedestroy($cropimage);
							$cropimage = ob_get_contents();
							ob_end_clean();

						} else if ($size) {
							$originimage = $__gd;
							$y_point = ($size[1] - $size[0])/2;
							$cropimage = imagecrop($originimage, ['x' => 0, 'y' => 0, 'width' => $size[0], 'height' => $size[0]]);
							$originimage = $cropimage;
							$cropimage = imagecreatetruecolor(400, 400);
							imagecopyresampled($cropimage, $originimage, 0, 0, 0, 0, 400, 400, $size[0], $size[0]);
							imagedestroy($originimage);
							ob_start();
							imagejpeg($cropimage, null, 75 );
							imagedestroy($cropimage);
							$cropimage = ob_get_contents();
							ob_end_clean();
						}
					}
					$cache_data = array();
					$cache_data['totalpage'] = count($image_files);  // ✅ 폴더 제외, 이미지 파일만
					$cache_data['page_order'] = "0";
					$cache_data['viewer'] = "toon";
					$cache_data['thumbnail'] = isset($cropimage) && $cropimage ? base64_encode($cropimage) : "";
//$cache_data['mtime'] = filemtime($zip_file);
//$cache_data['size'] = filesize($zip_file);
					// ★ (2026-10-31) 표지 이미지를 못 꺼낸 경우(암호 걸린 ZIP 등)엔 위에서 이미 닫고 지워서 여기서 null->close() 치명 오류로 폴더 목록이 잘렸음
					if (!$_is_rar7z && isset($zip) && $zip instanceof ZipArchive) { $zip->close(); unset($zip); }
                    gc_collect_cycles(); // ✨ PHP GC 유도
					safe_json_write($configfile, $cache_data);

					$_SESSION['zip_cache_generated'] = true;
				} // end of else (getFromName 성공)
				} // end of else (이미지가 있는 경우)
				}

			$json_data = json_decode(file_get_contents($configfile), true) ?? [];
			$img_output = $json_data['thumbnail'] ?? ''; // ★ (2026-10-30) 깨진 json 이면 키가 없어 경고
			
			// ✅ 캐시된 배지 정보가 있으면 사용, 없으면 JSON에서 읽기
			// ★ (2026-09-30) 목록 캐시 totalpage=0 인데 방금 생성/보유한 json 에 실제 페이지수가 있으면 json 사용.
			//   웹 업로드 직후 새로고침 시 목록 캐시가 json 생성 전에 만들어져 0 이 저장되고, 그 0 을 그대로 써서 0p 로 보이던 문제.
			//   json 에도 값이 없으면(이미지 없는 zip 등) 기존대로 캐시 값 사용 → 기존 동작·경고 발생 여부 변화 없음.
			if ($cached_totalpage !== null && ((int)$cached_totalpage > 0 || empty($json_data['totalpage']))) {
				$totalpage = $cached_totalpage;
				$pageorder = $cached_pageorder;
				$viewer = $cached_viewer ?? 'toon';
				
				if((int)$pageorder == 0) {
					$pageorder = "[ - ]";
				} elseif((int)$pageorder == 1) {
					$pageorder = "[1|2]";
				} elseif((int)$pageorder == 2) {
					$pageorder = "[2|1]";
				}
			} else {
				$totalpage = $json_data['totalpage'] ?? 0;
				$pageorder = $json_data['page_order'] ?? '0';
				if((int)($json_data['page_order'] ?? 0) == 0) {
					$pageorder = "[ - ]";
				} elseif((int)($json_data['page_order'] ?? 0) == 1) {
					$pageorder = "[1|2]";
				} elseif((int)($json_data['page_order'] ?? 0) == 2) {
					$pageorder = "[2|1]";
				}
				if(($json_data['viewer'] ?? null) !== null){
					$viewer = $json_data['viewer'];
				} else {
					$json_data['viewer'] = "toon";
					$json_output = json_encode($json_data, JSON_UNESCAPED_UNICODE);
					@file_put_contents($configfile, $json_output, LOCK_EX);
					$viewer = $json_data['viewer'];
				}
			}
	}

			display_zip_item:
			// [cover].jpg 생성 - 유효한 썸네일이 있고 cover가 없거나 너무 작을 때
			// ✅ [I/O 최적화] cover는 폴더당 하나($dir/[cover].jpg)인데, 기존엔 표시되는
			//    파일마다(수백~수천 회) is_file+filesize로 같은 파일을 반복 stat 했음.
			//    한 번 "존재함"이 확인되면 그 요청 동안 사라지지 않으므로, 플래그로
			//    이후 반복의 중복 stat을 건너뛴다. (결과 동일, 디스크 I/O만 감소)
			//    $_cover_ok는 이 표시 블록 진입 전(루프 바깥)에서 false로 초기화됨.
			$cover_file = $dir."/[cover].jpg";
			if (empty($_cover_ok)) {
				$cover_exists = is_file($cover_file) && filesize($cover_file) > 1000;
				if(!$cover_exists) {
					$cover_created = false;
					
					// 1순위: $img_output에서 생성
					if(!empty($img_output) && strlen($img_output) > 100) {
						$result = @file_put_contents($cover_file, base64_decode($img_output), LOCK_EX);
						if($result !== false) $cover_created = true;
					}
					
					// 2순위: 파일명.json에서 thumbnail 읽어서 생성
					if(!$cover_created && !empty($configfile) && is_file($configfile)) {
						$json_for_cover = @json_decode(@file_get_contents($configfile), true);
						if(!empty($json_for_cover['thumbnail']) && strlen($json_for_cover['thumbnail']) > 100) {
							$result = @file_put_contents($cover_file, base64_decode($json_for_cover['thumbnail']), LOCK_EX);
							if($result !== false) $cover_created = true;
						}
					}
					// 생성에 성공했으면 이후 반복에서 재확인 불필요
					if ($cover_created) $_cover_ok = true;
				} else {
					// 이미 유효한 cover가 존재 → 이후 반복에서 stat 생략
					$_cover_ok = true;
				}
			}
			
			// 동영상 ZIP 파일인 경우 별도 처리 - 캐시에서 확인
			if (!$is_video_archive && isset($json_data['is_video_archive'])) {
				$is_video_archive = $json_data['is_video_archive'];
			}
			
				if ($is_video_archive) {
				// 동영상 ZIP 파일 표시
				$title_s = cut_title($fileinfo);
				?>
				<a href='viewer.php?filetype=video_archive&file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>' rel="noopener noreferrer">
				  <div class="col mb-3">
					<?php /* ★ (2026-10-30) 다른 카드처럼 관리자 삭제 모드에서 고를 수 있게 data-file-path */ ?>
					<div class="card text-black m-0 p-1" <?php if ($is_admin): ?>data-file-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
						<!-- 동영상 아이콘 - thumb.php 사용 -->
						<img src="thumb.php?file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param . mc_file_cover_v($zip_file); /* ★ (2026-11-10 13:53 fix98) */ ?>" class="rounded card-img-top card-img" width="300" height="400" alt="video" loading="lazy" style="min-height: 120px; object-fit: contain; background: #e9e9e9;">
						<div class="card-img-overlay m-1 p-0">
							<span class="badge badge-pill badge-primary">ZIP</span>
							<span class="badge badge-pill badge-info badge-filecount"><?php echo h($totalpage); ?> files</span>
<?php 
// ✅ NEW 딱지 표시 (동영상 ZIP)
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0) {
    // ✅ [I/O 최적화] 캐시(file_list)의 time(filemtime) 사용 → 디스크 stat 회피
    //  - 기존: 파일마다 filectime() 호출 → 480회 디스크 접근 → cold일 때 큰 부담
    //  - 개선: .filelist_cache.json에 저장된 time 값을 우선 사용 (디스크 접근 0)
    //  - 폴백: 캐시에 time이 없는 예외 케이스만 기존 filectime() 호출
    //  - 의미 차이: filectime(메타변경시각) → filemtime(내용수정시각)
    //    만화 ZIP은 생성 후 거의 수정되지 않으므로 실용적 차이 미미
    $_file_ctime = $fileinfo_data['time'] ?? null;
    if (get_app_settings('new_badge_basis', 'file') === 'added') $_file_ctime = (int)($fileinfo_data['ctime'] ?? 0) ?: get_file_created_time($dir . "/" . $fileinfo); // ★ (2026-11-04 11:18) 관리자 'N 기준: 올린 시각'이면 올린 시각(목록 캐시에 없으면 그 파일만 한 번 확인). 기본은 위 파일 날짜(받은 날짜) 그대로
    if (!$_file_ctime) {
        $_file_full_path = $dir . "/" . $fileinfo;
        $_file_ctime = get_file_created_time($_file_full_path);
    }
    if ($_file_ctime && (time() - $_file_ctime) < ($_new_badge_hours * 3600)) {
        echo '<span class="badge badge-pill badge-danger">N</span>';
    }
}
?>
						</div>
						<div class="card-body m-0 p-0 text-center text-nowrap" style="text-overflow: ellipsis; overflow: hidden;">
							<span class="mc-ci"><svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-play-circle" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path fill-rule="evenodd" d="M6.271 5.055a.5.5 0 0 1 .52.038l3.5 2.5a.5.5 0 0 1 0 .814l-3.5 2.5A.5.5 0 0 1 6 10.5v-5a.5.5 0 0 1 .271-.445z"/></svg></span><?php /* ★ (2026-11-05) 아이콘은 그대로, 제목과 붙여서(간격은 CSS .mc-ci) */ ?><span class="mc-ct"><?php 
$zip_name = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '', $fileinfo);
$all_terms = isset($GLOBALS['all_highlight_terms']) && is_array($GLOBALS['all_highlight_terms']) ? $GLOBALS['all_highlight_terms'] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo highlight_search($zip_name, $all_terms);
?></span>
						</div>
					</div>
				  </div>
				</a>
				<?php
			} else {
			// 일반 만화 ZIP 파일 표시

			if(isset($nowdirarr) && is_array($nowdirarr) && strpos($nowdirarr[count($nowdirarr)-1],"] ") !== false){
				$dir_s = preg_replace("/\[[^]]*\]/","",$nowdirarr[count($nowdirarr)-1]);
				$t = str_replace($dir_s,"", $nowdirarr[count($nowdirarr)-1]);
				$dir_s = str_replace($t." ","", $nowdirarr[count($nowdirarr)-1]);
			} elseif(isset($nowdirarr) && is_array($nowdirarr)) {
				$dir_s = preg_replace("/\[[^]]*\]/","",$nowdirarr[count($nowdirarr)-1]);
			} else {
				$dir_s = "";
			}
			$title_s = cut_title($fileinfo);
		?>
<!-- 해당 방식은 리스트 이미지가 toon 이면, toon으로 book 이면 book 으로 우선 접속 -->
<!--  <a href='viewer.php?mode=<?php echo urlencode((string)$viewer); /* ★ (2026-11-09 14:15) 주석 안이어도 찍히므로 감쌈('-->' 로 주석을 빠져나갔음) */ ?>&file=<?php echo encode_url($getdir."/".$fileinfo);?>' rel="noopener noreferrer">  -->
				<a href='viewer.php?mode=toon&file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>' rel="noopener noreferrer">
				  <div class="col mb-3">
					<div class="card text-black m-0 p-1" style="position:relative;" <?php if ($is_admin): ?>data-file-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
<?php 
// ✅ 즐겨찾기 버튼
$_fav_file_path = $getdir."/".$fileinfo;
$_is_favorite = mc_rec_find($favorites_arr, $current_bidx, $_fav_file_path) !== null; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 즐겨찾기만
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        data-fav-path="<?php echo htmlspecialchars((string)$_fav_file_path, ENT_QUOTES, 'UTF-8'); ?>"
        onclick="toggleFavorite(this, this.getAttribute('data-fav-path'), <?php echo (int)$bidx; ?>, event)"
        title="<?php echo $_is_favorite ? __h('ui_unfavorite') : __h('ui_add_favorite'); ?>">
    <?php echo $_is_favorite ? '⭐' : '⭐'; ?>
</button>
						<img src="thumb.php?file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param . mc_file_cover_v($zip_file); /* ★ (2026-11-10 13:53 fix98) 직접 고른 표지면 주소가 바뀜 */ ?>" class="rounded card-img-top card-img" width="300" height="400" alt="thumbnail" loading="lazy">
									<div class="card-img-overlay m-1 p-0">
									<span class="badge badge-pill badge-success"><?php echo h($totalpage); ?>p</span>
									<span class="badge badge-pill badge-success" data-mcorder="<?php echo h($getdir."/".$fileinfo); ?>"><?php /* ★ (2026-11-05) 열릴 보기 방식(뷰어 '모드 기억' 설정 반영 — 책마다면 내 기록) / data-mcorder */ echo h(function_exists('mc_mv_order_for') ? mc_mv_order_label(mc_mv_order_for($getdir."/".$fileinfo, $current_bidx)) : $pageorder); ?></span>
									<span class="badge badge-pill badge-success" data-mcmode="<?php echo h($getdir."/".$fileinfo); ?>"><?php /* ★ (2026-11-01 23:37) 열릴 모드(뷰어 설정 '모드 기억' 반영 — 책마다면 내 기록) / ★ (2026-11-02 01:43) data-mcmode */ echo h(function_exists('mc_mv_mode_for') ? mc_mv_mode_for($getdir."/".$fileinfo, $current_bidx, $viewer) : $viewer); ?></span>
<?php 
// ✅ NEW 딱지 표시
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0) {
    // ✅ [I/O 최적화] 캐시(file_list)의 time(filemtime) 사용 → 디스크 stat 회피
    //  - 기존: 파일마다 filectime() 호출 → 수백 회 디스크 접근 → cold일 때 큰 부담
    //  - 개선: .filelist_cache.json에 저장된 time 값을 우선 사용 (디스크 접근 0)
    //  - 폴백: 캐시에 time이 없는 예외 케이스만 기존 filectime() 호출
    //  - 의미 차이: filectime(메타변경시각) → filemtime(내용수정시각)
    //    만화 ZIP은 생성 후 거의 수정되지 않으므로 실용적 차이 미미
    $_file_ctime = $fileinfo_data['time'] ?? null;
    if (get_app_settings('new_badge_basis', 'file') === 'added') $_file_ctime = (int)($fileinfo_data['ctime'] ?? 0) ?: get_file_created_time($dir . "/" . $fileinfo); // ★ (2026-11-04 11:18) 관리자 'N 기준: 올린 시각'이면 올린 시각(목록 캐시에 없으면 그 파일만 한 번 확인). 기본은 위 파일 날짜(받은 날짜) 그대로
    if (!$_file_ctime) {
        $_file_full_path = $dir . "/" . $fileinfo;
        $_file_ctime = get_file_created_time($_file_full_path);
    }
    if ($_file_ctime && (time() - $_file_ctime) < ($_new_badge_hours * 3600)) {
        echo '<span class="badge badge-pill badge-danger">N</span>';
    }
}
?>
									</div>
						<div class="card-body m-0 p-0 text-center" style="text-overflow: ellipsis; overflow: hidden; white-space: normal; line-height: 1.05;">
							<?php echo mc_card_icon('archive'); /* ★ (2026-11-05) 노란 압축 아이콘 → 폴더 아이콘과 같은 선 아이콘(펜닐 요청), 제목과 붙여서(목록 보기 배지 줄을 제목 위치에 맞춤) */ ?><span class="mc-ct"><?php 
// 파일 목록을 뿌려주는 (출력 제목)
// 확장자를 제거한 파일명
$display_name = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '', $fileinfo); 

// ✅ 전역 변수에서 모든 검색어 가져오기
$all_terms = isset($GLOBALS['all_highlight_terms']) && is_array($GLOBALS['all_highlight_terms']) 
    ? $GLOBALS['all_highlight_terms'] 
    : [];

// 검색어가 없으면 원본 $q 사용
if (empty($all_terms) && !empty($q)) {
    $all_terms = [$q];
}

// ✅ 하이라이트 적용 (모든 검색어 - 한글 + 영어)
echo highlight_search($display_name, $all_terms);
?></span>
						</div>
					</div>
				  </div>
				</a>
						<?php
			} // end of txt/epub else block
		}
	}
}

// 폴더 내 동영상 존재 여부 체크 - ✅ 캐시 활용
$has_video_in_dir = false;
// ★ (2026-11-03 09:40) 아래 '이미지 폴더 보기' 조건(검색 아님·그림 있음·폴더/파일 목록 비었음)일 때만 확인 — 그 밖엔 결과를 안 쓰는데 폴더 안 파일을 하나씩 확인했음(.folder_cache.json 없는 폴더는 열 때마다)
if (empty($q) && count($jpg_list) > 0 && (count($dir_list) + count($title_list) + count($file_list)) == 0 && is_dir($dir)) {
    $dir_video_cache = $dir . '/.folder_cache.json';
    if (is_file($dir_video_cache)) {
        $dvc = @json_decode(file_get_contents($dir_video_cache), true);
        if ($dvc && isset($dvc['video_count']) && $dvc['video_count'] > 0) {
            $has_video_in_dir = true;
        }
    } else {
        // 캐시 없으면 직접 체크
        try { // ★ (2026-11-02 14:32) 읽기 권한 없는 폴더(System Volume Information·ACL 막힌 NAS 폴더)를 열면 예외로 화면이 아래쪽에서 잘리던 것
        foreach (new DirectoryIterator($dir) as $f) {
            if ($f->isFile() && is_video_file($f->getFilename())) {
                $has_video_in_dir = true;
                break;
            }
        }
        } catch (\Throwable $e) {}
    }
}

// 이미지만 있고 동영상이 없을 때만 .image_files.json 생성
	// ★ (2026-10-30) 검색 중이 아닐 때만 — 하위 폴더 검색은 폴더 목록을 비워 두므로 결과 0건이면 이미지 뷰어로 끌려가던 문제
	if(empty($q) && count($jpg_list) > 0 && !$has_video_in_dir && (count($dir_list) + count($title_list) + count($file_list)) == 0){
					$configfile = $dir.".image_files.json";
					// ★ (2026-11-03 11:33) 다시 만들 때 = 없음·그림 수가 다름·폴더가 정보 파일보다 새로 바뀜 — 예전엔 로그인 직후 자동 색인이 세우던 세션 표시(index_refreshed)를 봤는데,
					//   그 자동 요청이 배경 갱신으로 바뀌어 탭·세션마다 다시 만들었고(보기 방식이 초기화되고 상위 목록 캐시까지 다시 만듦)
					$__if_old = is_file($configfile) ? @json_decode((string)@file_get_contents($configfile), true) : null;
					if(!is_array($__if_old) || (int)($__if_old['totalpage'] ?? -1) !== count($jpg_list) || (int)@filemtime($dir) > (int)@filemtime($configfile)) {
						$jpg_cover = file_get_contents($dir."/".$jpg_list[0]);
						$size = getimagesizefromstring($jpg_cover);
						$cropimage = null;  // ✅ 버그 수정: 초기화
						
						// ★ (2026-10-30) 손상된 이미지면 imagecreatefromstring 이 false → imagecrop(false) 치명 오류로 목록이 중간에 잘림 — 한 번만 만들고 실패하면 썸네일 없이 진행
						$__gd = $size ? mc_gd_from_string($jpg_cover) : false; // ★ (2026-11-02 09:56) 너무 큰 그림은 건너뜀
						if ($__gd === false) $size = false;
						if($size && $size[0] > $size[1]) {
							$x_point = ($size[0]/2) - $size[1];
							$originimage = $__gd;
								if($x_point > 0){
									$cropimage = imagecrop($originimage, ['x' => $x_point, 'y' => 0, 'width' => $size[1], 'height' => $size[1]]);
								} else {
									$cropimage = imagecrop($originimage, ['x' => 0, 'y' => 0, 'width' => $size[1], 'height' => $size[1]]);
								}
							$originimage = $cropimage;
							$cropimage = imagecreatetruecolor(400, 400);
							imagecopyresampled($cropimage, $originimage, 0, 0, 0, 0, 400, 400, $size[1], $size[1]);
							imagedestroy($originimage);
							ob_start();
							imagejpeg($cropimage, null, 75 );
							imagedestroy($cropimage);
							$cropimage = ob_get_contents();
							ob_end_clean();
						} else if ($size) {
							$originimage = $__gd;
							$y_point = ($size[1] - $size[0])/2;
							$cropimage = imagecrop($originimage, ['x' => 0, 'y' => 0, 'width' => $size[0], 'height' => $size[0]]);
							$originimage = $cropimage;
							$cropimage = imagecreatetruecolor(400, 400);
							imagecopyresampled($cropimage, $originimage, 0, 0, 0, 0, 400, 400, $size[0], $size[0]);
							imagedestroy($originimage);
							ob_start();
							imagejpeg($cropimage, null, 75 );
							imagedestroy($cropimage);
							$cropimage = ob_get_contents();
							ob_end_clean();
						}
						$cache_data = array();
						$cache_data['totalpage'] = count($jpg_list);
						$cache_data['page_order'] = "0";
						$cache_data['viewer'] = "toon";
						$cache_data['thumbnail'] = ($cropimage) ? base64_encode($cropimage) : "";
						safe_json_write($configfile, $cache_data);

						$_SESSION['index_refreshed'] = true;
					}
					$json_data = json_decode(file_get_contents($configfile), true) ?? [];
					$img_output = $json_data['thumbnail'] ?? ''; // ★ (2026-10-30) 깨진 json 이면 키가 없어 경고
					$totalpage = $json_data['totalpage'] ?? 0;
					$pageorder = $json_data['page_order'] ?? '0';
					if((int)($json_data['page_order'] ?? 0) == 0) {
						$pageorder = "[ - ]";
					} elseif((int)($json_data['page_order'] ?? 0) == 1) {
						$pageorder = "[1|2]";
					} elseif((int)($json_data['page_order'] ?? 0) == 2) {
						$pageorder = "[2|1]";
					}
					if(($json_data['viewer'] ?? null) !== null){
						$viewer = $json_data['viewer'];
					} else {
						$json_data['viewer'] = "toon";
						$json_output = json_encode($json_data, JSON_UNESCAPED_UNICODE);
						@file_put_contents($configfile, $json_output, LOCK_EX);
						$viewer = $json_data['viewer'];
					}
		// ✅ 이미지만 있는 폴더: 바로 viewer로 리다이렉트
		$redirect_url = 'viewer.php?filetype=images&mode=' . urlencode($viewer) . '&file=' . encode_url($getdir) . $bidx_param;
		?>
<script>location.replace('<?php echo $redirect_url; ?>');</script>
<?php
}
?>
<?php endif; // search_no_results ?>
	</div>
</div>
<br>
<nav class="navbar  navbar-light bg-white m-0 p-2 " aria-label="Page navigation">
<table width="100%">
<tr><td align="center">

<!--// 해당 $dir 없을때 검색어 포함된 전체 폴더 검색 -->
<!--// <?php if (empty(get_param('dir', 'path'))): ?>
<div class="text-center">
    <form method="get" action="index.php" class="form-inline justify-content-center mb-2">
        <input type="text" name="q" class="form-control mr-2" style="width: 300px;" placeholder="<?php echo __h('ui_search_placeholder'); ?>" value="<?php echo hv($q); ?>">
        <button type="submit" class="btn btn-sm btn-success"><?php echo __h('common_search'); ?></button>
    </form>
</div>
<?php endif; ?> -->
<!--// 해당 $dir 없을때 검색어 포함된 전체 폴더 검색 -->

</td></tr><tr>
<td width="100%" class="p-2" align="center">

<?php
// 페이지네이션 변수 계산
$total_pages = max(1, ceil($maxlist / max(1, (int)$current_maxview)));
$current_page = $paging + 1;  // 1-based for display

// config.php에서 $pages_per_group 값을 가져옴 (없으면 기본값)
if (!isset($pages_per_group)) $pages_per_group = 5;
if (!isset($pages_per_group_mobile)) $pages_per_group_mobile = 3;

// 모바일 감지 (User-Agent 기반)
$is_mobile = false;
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
    $is_mobile = (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || 
                  strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false);
}

// 현재 기기에 맞는 페이지 수 선택
$current_pages_per_group = $is_mobile ? $pages_per_group_mobile : $pages_per_group;

// 그룹형 페이지네이션 계산
$current_group = ceil($current_page / $current_pages_per_group);
$start_page = ($current_group - 1) * $current_pages_per_group + 1;
$end_page = min($start_page + $current_pages_per_group - 1, $total_pages);

// 이전/다음 그룹 페이지
$prev_group_page = $start_page - 1;
$next_group_page = $end_page + 1;

// 쿼리 파라미터 구성 (인코딩 문제 해결)
$nav_params = [];
// ★ (2026-11-09 14:15) 쪽 링크에는 이 화면에서 실제로 쓴 정렬($sort — 기억한 정렬이 이 화면에 없는 칩이면 이름순으로 바꾼 값)을 붙임 — 전에는 쿠키 값(파일수)이 그대로 붙어 2쪽부터 정렬 안 된 순서로 책이 겹치거나 빠졌음
$__nav_sort = isset($sort) ? $sort : $sort_param;
if (!empty($__nav_sort)) $nav_params[] = 'sort=' . urlencode($__nav_sort);
if (!empty($getdir)) $nav_params[] = 'dir=' . encode_url($getdir);
if (!empty($q)) $nav_params[] = 'q=' . urlencode($q);
elseif (isset($mc_sq) && $mc_sq !== '') $nav_params[] = 'sq=' . urlencode($mc_sq); // ★ (2026-11-07 fix44) 폴더 이름이 맞아 들어간 폴더에서 쪽을 넘겨도 찾은 폴더 이동 막대가 이어지게(fix41 에서 빠뜨림)
$nav_params[] = 'bidx=' . $current_bidx;  // ✅ 항상 bidx 포함
$param_string = implode('&', $nav_params);
$param_prefix = !empty($param_string) ? $param_string . '&' : '';
?>

<style>
.pagination-wrapper { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.pagination-nav { display: flex; align-items: center; justify-content: center; gap: 4px; }
.pagination-nav .page-btn { 
    min-width: 36px; height: 36px; 
    display: flex; align-items: center; justify-content: center;
    border: 1px solid #dee2e6; border-radius: 6px;
    background: #fff !important; color: #495057 !important; font-size: 14px;
    cursor: pointer; transition: all 0.2s;
    text-decoration: none !important; padding: 0 8px;
}
.pagination-nav .page-btn:hover:not(.disabled):not(.active) { 
    background: #e9ecef !important; border-color: #adb5bd !important; color: #495057 !important;
}
.pagination-nav .page-btn.active { 
    background: #28a745 !important; border-color: #28a745 !important; color: #fff !important; font-weight: bold; 
}
.pagination-nav .page-btn.disabled { 
    opacity: 0.5; cursor: not-allowed; pointer-events: none; 
}
.pagination-info { 
    color: #6c757d; font-size: 13px; text-align: center;
}
</style>

<?php echo function_exists('mc_pager_group_js') ? mc_pager_group_js() : ''; /* ★ (2026-11-10 19:19 fix105) 내 정보 접속 기록 등 다른 쪽 단추도 같은 묶음 / ★ (2026-11-10 21:53 fix106) 폴더를 못 여는 화면에서도 나오게 조건 밖으로 */ ?>
<?php if ($mc_dir_err === ''): // ★ (2026-11-06 00:10) 폴더를 열 수 없으면 쪽 번호·'1 / 1 (0개)' 없음(안내 상자의 단추로 이동) ?>
<div class="pagination-wrapper" data-pc="<?php echo $pages_per_group; ?>" data-mobile="<?php echo $pages_per_group_mobile; ?>" data-current="<?php echo $current_page; ?>" data-total="<?php echo $total_pages; ?>" data-param-prefix="<?php echo htmlspecialchars($param_prefix); ?>">
    <!-- 페이지 버튼들 -->
    <div class="pagination-nav">
        <!-- 상위폴더 버튼 -->
        <?php if($dir != $base_dir): ?>
        <a href="index.php?dir=<?php echo encode_url($updir);?>&page=<?php echo validate_page(get_param('uppage', 'int', 0)) . $bidx_param; ?>"<?php if (!empty($_mc_up_key)): /* ★ (2026-11-08 fix80) 위 '<' 와 같게 보던 자리로 */ ?> data-mc-up="<?php echo h($_mc_up_key); ?>" onclick="if(window.mcDirUp&&!(event.ctrlKey||event.metaKey||event.shiftKey)){mcDirUp(this);return false;}"<?php endif; ?> class="page-btn" title="<?php echo __h('ui_parent_folder'); ?>">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.854 1.146a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L4 2.707V12.5A2.5 2.5 0 0 0 6.5 15h8a.5.5 0 0 0 0-1h-8A1.5 1.5 0 0 1 5 12.5V2.707l3.146 3.147a.5.5 0 1 0 .708-.708l-4-4z"/></svg>
        </a>
        <?php endif; ?>
        
        <!-- 처음 페이지 (첫 그룹이 아닐 때만) -->
        <?php if($current_group > 1): ?>
        <a href="index.php?<?php echo $param_prefix; ?>page=0" 
           class="page-btn" title="<?php echo __h('ui_first_page'); ?>">
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.354 1.646a.5.5 0 0 1 0 .708L2.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/><path fill-rule="evenodd" d="M12.354 1.646a.5.5 0 0 1 0 .708L6.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
        </a>
        <?php endif; ?>
        
        <!-- 이전 그룹 (첫 그룹이 아닐 때만) -->
        <?php if($prev_group_page >= 1): ?>
        <a href="index.php?<?php echo $param_prefix; ?>page=<?php echo $prev_group_page - 1; ?>" 
           class="page-btn" title="<?php echo __h('ui_prev_group'); ?>">
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
        </a>
        <?php endif; ?>
        
        <!-- 현재 그룹의 페이지 번호들 -->
        <?php for($p = $start_page; $p <= $end_page; $p++): ?>
            <a href="index.php?<?php echo $param_prefix; ?>page=<?php echo $p - 1; ?>" 
               class="page-btn <?php if($p == $current_page) echo 'active'; ?>">
                <?php echo $p; ?>
            </a>
        <?php endfor; ?>
        
        <!-- 다음 그룹 (다음 그룹이 있을 때만) -->
        <?php if($next_group_page <= $total_pages): ?>
        <a href="index.php?<?php echo $param_prefix; ?>page=<?php echo $next_group_page - 1; ?>" 
           class="page-btn" title="<?php echo __h('ui_next_group'); ?>">
            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M3.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L9.293 8 3.646 2.354a.5.5 0 0 1 0-.708z"/></svg>
        </a>
        <?php endif; ?>
    </div>
    
    <!-- 페이지 정보 -->
    <div class="pagination-info"><?php echo number_format($current_page); ?> / <?php echo number_format($total_pages); ?> (<?php echo number_format($maxlist); ?><?php echo __('index_items_count'); ?>)</div>
</div>

<script>
(function() {
    var wrapper = document.querySelector('.pagination-wrapper');
    if (!wrapper) return;
    
    var pcPages = parseInt(wrapper.getAttribute('data-pc')) || 5;
    var mobilePages = parseInt(wrapper.getAttribute('data-mobile')) || 3;
    var currentPage = parseInt(wrapper.getAttribute('data-current')) || 1;
    var totalPages = parseInt(wrapper.getAttribute('data-total')) || 1;
    var paramPrefix = wrapper.getAttribute('data-param-prefix') || '';
    
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    function updatePagination() {
        var pagesPerGroup = isMobile() ? mobilePages : pcPages;
        var currentGroup = Math.ceil(currentPage / pagesPerGroup);
        var startPage = (currentGroup - 1) * pagesPerGroup + 1;
        var endPage = Math.min(startPage + pagesPerGroup - 1, totalPages);
        var prevGroupPage = startPage - 1;
        var nextGroupPage = endPage + 1;
        
        var nav = wrapper.querySelector('.pagination-nav');
        if (!nav) return;
        
        // 상위폴더 버튼 보존
        var upFolderBtn = nav.querySelector('a[title="<?php echo __h('ui_parent_folder'); ?>"], a.up-folder-link');
        var upFolderHtml = upFolderBtn ? upFolderBtn.outerHTML : '';
        
        var html = upFolderHtml;
        
        // 처음 페이지
        if (currentGroup > 1) {
            html += '<a href="index.php?' + paramPrefix + 'page=0" class="page-btn" title="<?php echo __h('ui_first_page'); ?>">' +
                    '<svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.354 1.646a.5.5 0 0 1 0 .708L2.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/><path fill-rule="evenodd" d="M12.354 1.646a.5.5 0 0 1 0 .708L6.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg></a>';
        }
        
        // 이전 그룹
        if (prevGroupPage >= 1) {
            html += '<a href="index.php?' + paramPrefix + 'page=' + (prevGroupPage - 1) + '" class="page-btn" title="<?php echo __h('ui_prev_group'); ?>">' +
                    '<svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg></a>';
        }
        
        // 페이지 번호들
        for (var p = startPage; p <= endPage; p++) {
            var activeClass = (p === currentPage) ? ' active' : '';
            html += '<a href="index.php?' + paramPrefix + 'page=' + (p - 1) + '" class="page-btn' + activeClass + '">' + p + '</a>';
        }
        
        // 다음 그룹
        if (nextGroupPage <= totalPages) {
            html += '<a href="index.php?' + paramPrefix + 'page=' + (nextGroupPage - 1) + '" class="page-btn" title="<?php echo __h('ui_next_group'); ?>">' +
                    '<svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M3.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L9.293 8 3.646 2.354a.5.5 0 0 1 0-.708z"/></svg></a>';
        }
        
        nav.innerHTML = html;
    }
    
    var wasMobile = isMobile();
    
    function onResize() {
        var nowMobile = isMobile();
        if (wasMobile !== nowMobile) {
            wasMobile = nowMobile;
            updatePagination();
        }
    }
    
    window.addEventListener('resize', onResize);
    window.addEventListener('orientationchange', function() {
        setTimeout(onResize, 100);
    });
})();
</script>
<?php endif; ?>
</td></tr>
</table>
</nav>

<!-- Scroll to Top Button -->
<button id="scrollTopBtn" title="<?php echo __h('ui_scroll_top'); ?>" style="display:none;">
  ▲
</button>

<style>
/* 기본 링크 스타일 (.page-btn은 별도 스타일 적용) */
a:not(.page-btn) {
	text-decoration: none !important;
	color: #0095fd;
}

/* 방문한 링크 스타일 (단, .badge, .page-btn, .logo-link 클래스는 제외) */
a.visited-link:not(.badge):not(.page-btn):not(.logo-link) {
	color: #888888 !important;
	text-decoration: none !important;
	background-color: transparent;
}

/* hover 시 강조 (단, .badge, .page-btn, .logo-link 클래스는 제외) */
a:hover:not(.badge):not(.page-btn):not(.logo-link) {
	color: #39c5bb !important;
}
/* hover 시 자식 요소 색상 변경 (Bootstrap text-white 등 오버라이드, 배지/page-btn 제외) */
a:hover:not(.badge):not(.page-btn):not(.logo-link) *:not(.badge):not(.badge *),
a:hover:not(.badge):not(.page-btn):not(.logo-link) .text-white:not(.badge),
a:hover:not(.badge):not(.page-btn):not(.logo-link) [class*="text-"]:not(.badge) {
	color: #39c5bb !important;
}

/* ✅ 카드 내 배지 텍스트는 흰색 유지 (최우선) - badge-light, badge-warning, badge-info 제외 (단 badge-filesize, badge-filecount는 포함) */
a[href*="dir="] .badge:not(.badge-light):not(.badge-warning):not(.badge-info),
a[href*="dir="] .badge:not(.badge-light):not(.badge-warning):not(.badge-info) *,
a[href*="viewer.php"] .badge:not(.badge-light):not(.badge-warning):not(.badge-info),
a[href*="viewer.php"] .badge:not(.badge-light):not(.badge-warning):not(.badge-info) *,
a[href*="_viewer.php"] .badge:not(.badge-light):not(.badge-warning):not(.badge-info),
a[href*="_viewer.php"] .badge:not(.badge-light):not(.badge-warning):not(.badge-info) *,
.badge-filesize, .badge-filesize *,
.badge-filecount, .badge-filecount * {
	color: #ffffff !important;
}

/* ✅ badge-light (로그인 정보 등)는 검정 텍스트 (최우선) */
.badge-light, .badge-light *, a:hover .badge-light {
	color: #000000 !important;
}

/* ✅ badge-warning, badge-info 기본/hover 모두 검정 텍스트 (최우선) - badge-filesize, badge-filecount 제외 */
.badge-warning, .badge-warning *,
.badge-info:not(.badge-filesize):not(.badge-filecount), .badge-info:not(.badge-filesize):not(.badge-filecount) *,
a:hover .badge-warning, a:hover .badge-warning *,
a:hover .badge-info:not(.badge-filesize):not(.badge-filecount), a:hover .badge-info:not(.badge-filesize):not(.badge-filecount) *,
a[href*="dir="] .badge-warning, a[href*="dir="] .badge-warning *,
a[href*="dir="] .badge-info:not(.badge-filesize):not(.badge-filecount), a[href*="dir="] .badge-info:not(.badge-filesize):not(.badge-filecount) *,
a[href*="dir="]:hover .badge-warning, a[href*="dir="]:hover .badge-warning *,
a[href*="dir="]:hover .badge-info:not(.badge-filesize):not(.badge-filecount), a[href*="dir="]:hover .badge-info:not(.badge-filesize):not(.badge-filecount) * {
	color: #000000 !important;
}

/* ✅ badge-filesize, badge-filecount (동영상 용량/파일수)는 항상 흰색 (최최우선) */
.badge-filesize, .badge-filesize *,
.badge-filecount, .badge-filecount *,
a:hover .badge-filesize, a:hover .badge-filesize *,
a:hover .badge-filecount, a:hover .badge-filecount * {
	color: #ffffff !important;
}
</style>

<script>
function applyVisitedLinkStyle() {
	const anchors = document.querySelectorAll("a[href]");
	anchors.forEach(link => {
		const href = link.getAttribute("href");

		if (!href.startsWith("http") || href.startsWith(location.origin)) {
			const key = "visited_" + href;

			try { if (localStorage.getItem(key)) link.classList.add("visited-link"); } catch (e) {}

			// ★ (2026-11-02 10:03) 클릭 기록은 링크마다 한 번만 붙임(전에는 처음 3번·뒤로 올 때마다 더 붙음) · 저장 공간이 차도 오류 없이
			if (link.dataset.mcVisited) return;
			link.dataset.mcVisited = "1";
			link.addEventListener("click", () => {
				try { localStorage.setItem(key, "1"); } catch (e) {}
				link.classList.add("visited-link");
			});
		}
	});
}

// 초기 로딩 시 적용
document.addEventListener("DOMContentLoaded", applyVisitedLinkStyle);

// 뒤로가기 복귀 시 적용 (bfcache 대응)
window.addEventListener("pageshow", applyVisitedLinkStyle);
</script>

<script>
  // ★ (2026-11-03 01:27) 정렬 칩으로 고른 정렬을 이 기기에 기억(주소에 정렬이 있으면 그 값을 쿠키로 — 다음에 정렬 없이 열어도 그 정렬)
  try { var _ms = location.search.match(/[?&]sort=(nameasc|namedesc|timeasc|timedesc|sizeasc|sizedesc|countasc|countdesc)(?:&|$)/); if (_ms) document.cookie = 'mc_sort=' + _ms[1] + '; path=/; max-age=31536000; SameSite=Lax'; } catch (e) {}
  const scrollTopBtn = document.getElementById("scrollTopBtn");

  // 스크롤 이벤트
  window.onscroll = function () {
    if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
      scrollTopBtn.style.display = "block";
    } else {
      scrollTopBtn.style.display = "none";
    }
  };

  // 버튼 클릭 시 맨 위로
  scrollTopBtn.onclick = function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
</script>

<!-- 캐시 재생성 JavaScript 함수 추가 -->
<script>
function rebuildCache() {
    if (confirm(i18n.cache_rebuild_confirm)) {
        // 로딩 표시
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = i18n.processing;
        btn.style.pointerEvents = 'none';
        
        // ✅ POST 방식 + CSRF 토큰으로 변경
        const formData = new FormData();
        formData.append('rebuild_cache', '1');
        formData.append('bidx', '<?php echo $current_bidx; ?>');
        formData.append('csrf_token', '<?php echo h(generate_csrf_token()); ?>');
        
        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())  // JSON 대신 text()로 변경
        .then(data => {
            alert(data);  // 직접 텍스트 표시
            btn.textContent = originalText;
            btn.style.pointerEvents = 'auto';
            // 페이지 새로고침으로 새 캐시 적용
            location.reload();
        })
        .catch(error => {
            alert(i18n.error_occurred + ': ' + error);
            btn.textContent = originalText;
            btn.style.pointerEvents = 'auto';
        });
    }
}
</script>

<script>
// 스크롤 문제 해결을 위한 코드만 (추가 부분)

// 전역 플래그
let hasScrolledToSearch = false;
let isScrolling = false;
let userHasScrolled = false;
let scrollTimer = null;

// 사용자 스크롤 감지
function detectUserScroll() {
    if (!userHasScrolled) {
        userHasScrolled = true;
        console.log('사용자 스크롤 감지됨 - 자동 스크롤 취소');
        
        if (scrollTimer) {
            clearTimeout(scrollTimer);
            scrollTimer = null;
            console.log('예약된 스크롤 타이머 취소됨');
        }
        
        hasScrolledToSearch = true;
    }
}

// 스크롤 함수 (수정된 버전)
function scrollToSearchSection() {
    try {
        if (window.__mcSfJump) return; // ★ fix59 아래 막대로 옮겨 온 화면은 폴더 목록을 맨 위로(mc_sf_js)
        if (window.__mcListRestore) return; // ★ fix55 돌아온 검색 결과는 보던 자리로 복원(restoreScroll) — 검색칸으로 끌어올리지 않음
        if (hasScrolledToSearch || isScrolling || userHasScrolled) {
            console.log('스크롤 차단됨');
            return;
        }
        
        const searchSection = document.getElementById('search-section');
        if (!searchSection) return;
        
        // ✅ 요소가 숨겨져 있으면 스크롤 안 함
        if (searchSection.offsetParent === null || searchSection.style.display === 'none') {
            console.log('search-section이 숨겨져 있음 - 스크롤 취소');
            return;
        }
        
        console.log('스크롤 시작');
        isScrolling = true;
        hasScrolledToSearch = true;
        
        const rect = searchSection.getBoundingClientRect();
        
        // ✅ rect가 유효한지 확인
        if (rect.height === 0) {
            console.log('search-section 높이가 0 - 스크롤 취소');
            isScrolling = false;
            return;
        }
        
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const targetPosition = scrollTop + rect.top - (window.innerHeight / 2) + (rect.height / 2);
        
        if (userHasScrolled) {
            console.log('스크롤 실행 직전 사용자 액션 감지 - 취소');
            isScrolling = false;
            return;
        }
        
        window.scrollTo({
            top: Math.max(0, targetPosition),
            behavior: 'smooth'
        });
        
        setTimeout(() => {
            const searchInput = searchSection.querySelector('input[name="q"]');
            if (searchInput && !userHasScrolled) {
                searchInput.focus();
            }
            isScrolling = false;
            console.log('스크롤 완료');
        }, 1000);
    } catch (e) {
        console.error('scrollToSearchSection 에러:', e);
        isScrolling = false;
    }
}

// 플래그 초기화
function resetScrollFlags() {
    hasScrolledToSearch = false;
    isScrolling = false;
    userHasScrolled = false;
    
    if (scrollTimer) {
        clearTimeout(scrollTimer);
        scrollTimer = null;
    }
}

// 기존 DOMContentLoaded 이벤트에 추가할 부분
document.addEventListener('DOMContentLoaded', function() {
    try {
        console.log('DOMContentLoaded 실행');
        
        // 플래그 초기화
        resetScrollFlags();
        
        // 사용자 스크롤 이벤트 등록
        window.addEventListener('scroll', detectUserScroll);
        window.addEventListener('wheel', detectUserScroll);
        window.addEventListener('touchmove', detectUserScroll);
        window.addEventListener('keydown', function(e) {
            if ([32, 33, 34, 35, 36, 37, 38, 39, 40].includes(e.keyCode)) {
                detectUserScroll();
            }
        });
        
        // 기존 applyVisitedLinkStyle() 실행
        if (typeof applyVisitedLinkStyle === 'function') {
            applyVisitedLinkStyle();
        }
        
        // URL 해시 체크 및 스크롤 예약
        if (window.location.hash === '#search-section' && !hasScrolledToSearch) {
            console.log('해시 발견, 스크롤 예약');
            
            // ✅ 요소가 존재하고 보이는지 먼저 확인
            const searchSection = document.getElementById('search-section');
            if (!searchSection || searchSection.style.display === 'none' || searchSection.offsetParent === null) {
                console.log('search-section이 없거나 숨겨져 있음 - 스크롤 예약 취소');
                return;
            }
            
            if (document.readyState === 'complete') {
                scrollTimer = setTimeout(() => {
                    if (!hasScrolledToSearch && !userHasScrolled) {
                        scrollToSearchSection();
                    }
                }, 500);
            } else {
                window.addEventListener('load', () => {
                    scrollTimer = setTimeout(() => {
                        if (!hasScrolledToSearch && !userHasScrolled) {
                            scrollToSearchSection();
                        }
                    }, 500);
                });
            }
        }
    } catch (e) {
        console.error('DOMContentLoaded 에러:', e);
    }
});
</script>


<script>
    // 1. 필요한 상수 설정
    const STORAGE_KEY = 'infiniteScrollState';
    const LIST_LINK_SELECTOR = '.board-list a'; // 게시글 목록의 링크를 가리키는 정확한 CSS 선택자로 변경하세요.
                                                // 예: 'tr.list-item a' 또는 'ul.post-list li a' 등

    // 2. 게시글 상세 페이지로 이동하기 전 상태 저장
    function saveScrollState() {
        // 현재 스크롤 위치 (Y축)
        const scrollPosition = window.scrollY;
        
        // 현재 로드된 게시글 목록의 HTML (DOM) 상태 저장
        // 무한 스크롤 콘텐츠를 담고 있는 컨테이너의 ID나 클래스를 사용하세요.
        // 예: <div id="post-container">...</div>
        const listContainer = document.getElementById('post-container'); // ⭐ 이 ID를 실제 게시판 목록 컨테이너 ID로 변경하세요 ⭐
        
        if (listContainer) {
            const state = {
                scroll: scrollPosition,
                html: listContainer.innerHTML,
                timestamp: Date.now()
            };
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            // console.log('스크롤 상태 저장 완료:', scrollPosition);
        }
    }

    // 3. 목록 페이지 로드 시 상태 복원
    function restoreScrollState() {
        const savedState = sessionStorage.getItem(STORAGE_KEY);
        
        if (savedState) {
            const state = JSON.parse(savedState);
            const listContainer = document.getElementById('post-container'); // ⭐ 위와 동일한 ID 사용 ⭐
            
            if (listContainer) {
                // 저장된 HTML 콘텐츠로 목록 컨테이너 복원
                listContainer.innerHTML = state.html;

                // 저장된 스크롤 위치로 이동
                // setTimeout을 사용하여 DOM 로드 및 렌더링이 완료된 후 스크롤을 실행
                setTimeout(() => {
                    window.scrollTo(0, state.scroll);
                    // console.log('스크롤 상태 복원 완료:', state.scroll);
                    
                    // 복원 후에는 저장된 상태 삭제 (다시 처음부터 스크롤 시작할 경우 대비)
                    sessionStorage.removeItem(STORAGE_KEY);
                    
                    // 검색 결과 해시(#search-section)와 충돌하지 않도록 기존 로직 제거
                    if (window.location.hash === '#search-section') {
                        window.location.hash = ''; 
                    }
                    
                }, 50); // 짧은 지연 시간(50ms)을 주어 안정적으로 스크롤 위치 복원
            }
        }
    }

    // 4. 모든 게시글 링크에 이벤트 리스너 등록 (상태 저장)
    document.addEventListener('DOMContentLoaded', () => {
        // 모든 목록 링크에 클릭 이벤트 등록
        document.querySelectorAll(LIST_LINK_SELECTOR).forEach(link => {
            link.addEventListener('click', saveScrollState);
        });

        // 페이지 로드 시 스크롤 상태 복원 시도
        restoreScrollState();
        
        // 브라우저의 전방/후방 캐시(bfcache) 사용 시에도 상태를 저장하도록 pagehide 이벤트 추가
        window.addEventListener('pagehide', saveScrollState);
    });

    // 5. 무한 스크롤 함수가 새로운 목록을 로드할 때마다
    //    새로 로드된 게시글에도 saveScrollState 이벤트 리스너를 다시 등록해야 합니다.
    //    ⭐ 기존 무한 스크롤 로직(예: loadMorePosts 함수) 내부에도 아래 코드를 추가해야 합니다.
    /* function loadMorePosts(page) {
        // ... (새 게시글 로드 로직) ...

        // 새 게시글이 DOM에 추가된 후:
        document.querySelectorAll(LIST_LINK_SELECTOR).forEach(link => {
            // 이벤트가 중복 등록되는 것을 막기 위해 remove 후 add 하는 것이 안전
            link.removeEventListener('click', saveScrollState); 
            link.addEventListener('click', saveScrollState);
        });
    }
    */
</script>


<!-- 자동 로그아웃 타이머 -->
<?php render_auto_logout_script(); ?>

<!-- 다크모드 JS -->
<?php render_darkmode_script(); ?>

<?php // ✅ 통합 스크립트: bidx 파라미터 + 페이지 전환 (즉시 이동) ?>
<script>
(function() {
    'use strict';
    
    var bidx = <?php echo $current_bidx; ?>;
    
    // bidx 파라미터가 필요한 페이지 패턴
    var BIDX_PAGES = ['index.php', 'viewer.php', 'txt_viewer.php', 'epub_viewer.php', 'bookmark.php'];
    
    // 링크에 bidx 추가
    function addBidxToHref(href) {
        if (!href || href.indexOf('bidx=') !== -1) return href;
        
        var needsBidx = BIDX_PAGES.some(function(page) { 
            return href.indexOf(page) !== -1; 
        });
        if (!needsBidx) return href;
        
        var separator = href.indexOf('?') !== -1 ? '&' : '?';
        return href + separator + 'bidx=' + bidx;
    }
    
    // ✅ 링크 클릭 - 즉시 이동 (지연 없음)
    document.addEventListener('click', function(e) {
        var link = e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) {
            var el = e.target;
            while (el && el.tagName !== 'A') el = el.parentElement;
            link = el;
        }
        if (!link) return;
        // ★ (2026-10-30) Ctrl/⌘/Shift/Alt+클릭·가운데 버튼(새 탭 열기)과 이미 처리된 클릭은 브라우저 기본 동작대로
        if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || (e.button !== undefined && e.button !== 0)) return;
        
        var href = link.getAttribute('href');
        
        // 무시할 링크
        if (!href || 
            href.charAt(0) === '#' || 
            href.indexOf('javascript:') === 0 || 
            href.indexOf('http') === 0 ||
            link.target === '_blank') {
            return;
        }
        
        // 로그아웃은 즉시 이동
        if (href.indexOf('mode=logout') !== -1) {
            return;
        }
        
        // bidx 파라미터 추가
        href = addBidxToHref(href);
        
        // ✅ privacy_shield 내부 이동 플래그 설정
        if (typeof window.myComixMarkNavigation === 'function') {
            window.myComixMarkNavigation();
        }
        
        // ✅ 즉시 이동 (fade 효과는 비동기로 시작만)
        e.preventDefault();
        document.documentElement.classList.add('leaving');
        
        // 즉시 이동 (setTimeout 제거)
        location.href = href;
        
    }, false);
    
    // 폼에 bidx hidden input 추가
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
        if (!forms[i].querySelector('input[name="bidx"]')) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'bidx';
            input.value = bidx;
            forms[i].appendChild(input);
        }
    }
    
    // 폼 제출 시 fade out
    document.addEventListener('submit', function(e) {
        if (e.defaultPrevented) return; // ★ (2026-11-02 11:35) 확인 창 '취소'(return false)면 화면을 흐리지 않음 — 전에는 투명한 채로 남았음
        document.documentElement.classList.add('leaving');
    });
    
    // ✅ 페이지가 보일 때는 항상 leaving 해제(=html opacity:1)하여 흰 화면 방지.
    //    과거엔 pageshow의 persisted일 때만 해제해, iOS가 오래된 목록을 freeze/discard 후
    //    persisted=false로 되살리면 leaving(opacity:0)이 남아 목록이 흰 화면이 됐음(뷰어는 leaving을
    //    추가하지 않아 무관). → pageshow(무조건) + visibilitychange(visible) 양쪽에서 해제. 이탈 시 fade-out은 그대로.
    function _showPage(){
        document.documentElement.classList.remove('leaving');
        document.documentElement.classList.add('ready');
    }
    document.addEventListener('visibilitychange', function(){
        if (document.visibilityState === 'visible') _showPage();
    }, {passive:true});
    // ✅ 최후 보루: 브라우저 완전 종료 후 재실행 등 어떤 복원 경로에서도 흰 화면이 남지 않도록
    //    짧은 지연 후 한 번 더 보이게 강제(정상 로드에선 이미 ready라 무해한 no-op).
    setTimeout(_showPage, 1200);
    // 뒤로가기(bfcache)·freeze 복원
    window.addEventListener('pageshow', function(e) {
        _showPage();
        // ✅ viewer에서 자동저장/북마크 변경 시, 새로고침 없이 목록 갱신 (bfcache·일반 로드 모두).
        //    자동저장 저장($.get)이 비동기라 이탈 직후엔 아직 안 끝났을 수 있어, 약간의 지연 후 갱신해
        //    옛 데이터를 받아오는 race를 완화(실패 시 refreshBookmarkTable 내부에서 reload 폴백).
        try {
            if (sessionStorage.getItem('mycomix_marks_dirty')) {
                sessionStorage.removeItem('mycomix_marks_dirty');
                setTimeout(refreshBookmarkTable, 700);
            }
        } catch(err){}
        // ✅ viewer에서 변경된 즐겨찾기 상태 동기화 (bfcache 복원 시)
        if (e.persisted) syncFavorites();
    });
})();
</script>
<script>
// ★ (2026-11-10 13:53, 펜닐 요청 — fix98) 뷰어에서 표지를 바꾸면 목록으로 돌아왔을 때 새로고침 없이 바로 바뀜(즐겨찾기 표시처럼)
//   뷰어가 localStorage 'mc_cover_bump' = {t: 시각, f: 파일 경로, d: 폴더 경로, k: file|folder|auto} 를 남김 → 이 화면이 그 뒤에 바뀐 것만 그림 주소에 cv=시각을 붙여 다시 받음
//   새로 연 화면은 서버가 이미 새 주소(v=·cv=)를 주므로 건드리지 않음(이 화면을 그린 뒤에 바뀐 것만)
(function () {
    var seen = Date.now();
    function np(s) { s = String(s || '').replace(/\{pl\}/g, '+').replace(/\{dt\}/g, '.').replace(/\{pc\}/g, '%').replace(/\\/g, '/').replace(/\/+/g, '/').replace(/\/$/, ''); return s.charAt(0) === '/' ? s : '/' + s; }
    function bust(u, t) { try { var x = new URL(u, location.href); x.searchParams.set('cv', String(t)); return x.pathname.replace(/^.*\//, '') + x.search; } catch (e) { return u; } }
    function apply() {
        var b = null;
        try { b = JSON.parse(localStorage.getItem('mc_cover_bump') || 'null'); } catch (e) {}
        if (!b || !b.t || b.t <= seen) return;
        seen = b.t;
        var f = np(b.f), d = np(b.d), file = (b.k === 'file' || b.k === 'auto'), folder = (b.k === 'folder' || b.k === 'auto');
        document.querySelectorAll('img').forEach(function (im) {
            var s = im.getAttribute('src') || '';
            if (!/(^|\/)thumb\.php\?|[?&]thumb=video(&|$)/.test(s)) return;
            var q; try { q = new URL(s, location.href).searchParams; } catch (e) { return; }
            var p = np(q.get('file')), isCover = (q.get('type') === 'cover');
            if ((file && !isCover && p === f) || (folder && isCover && p === d)) im.setAttribute('src', bust(s, b.t));
        });
        // PDF 카드는 그림을 화면에 바로 넣어 둠 — 관리자 화면에만 있는 카드 경로(data-file-path)로 찾아 thumb.php 주소로 바꿈
        if (file) document.querySelectorAll('.card[data-file-path]').forEach(function (c) {
            if (np(c.getAttribute('data-file-path')) !== f) return;
            var im = c.querySelector('img.card-img');
            // ★ (2026-11-10 14:55 fix99) 표지 그림이 없던 카드(빈 칸만 있음 — 문서·PDF)는 그림 칸을 만들어 넣음
            var ph = c.querySelector('.card-img-placeholder');
            if (!im && ph) { im = document.createElement('img'); im.className = 'rounded card-img-top card-img loaded'; im.style.cssText = 'height:200px; object-fit:cover;'; im.setAttribute('src', 'data:,'); ph.parentNode.insertBefore(im, ph); }
            if (im && /^data:/.test(im.getAttribute('src') || '')) im.onload = function () { // 새 표지가 있으면 그림, 없으면(자동으로 되돌려 표지 없음) 빈 칸 표시
                var p2 = this.nextElementSibling, isPh = p2 && p2.classList && p2.classList.contains('card-img-placeholder');
                if (this.naturalWidth > 1) { this.style.display = ''; if (isPh) p2.style.display = 'none'; }
                else { this.style.display = 'none'; if (isPh) p2.style.display = 'flex'; }
            };
            if (im && /^data:/.test(im.getAttribute('src') || '')) im.setAttribute('src', 'thumb.php?file=' + encodeURIComponent(f.replace(/\+/g, '{pl}').replace(/\./g, '{dt}').replace(/%/g, '{pc}')) + '&bidx=<?php echo (int)$current_bidx; ?>&cv=' + b.t);
        });
        // ★ (2026-11-11 01:28 fix110) 표지가 없던 폴더(PDF·동영상·문서만 있어 자동 표지가 없음)는 그림 칸이 처음부터 없어서, 폴더 표지를 지정해도 새로고침 전엔 안 보였음
        //   ① 이 폴더 목록 위: 제목줄을 서버가 그리는 것과 같은 표지 칸으로 바꿈 ② 위 목록의 폴더 카드: 빈 그림(data:)을 표지 주소로
        //   자동으로 되돌려 쓸 그림이 없으면(thumb.php 가 1×1 그림) 다시 제목줄로 — 새로 열었을 때와 같은 모양
        if (folder) {
            var csrc = 'thumb.php?file=' + encodeURIComponent(d.replace(/\+/g, '{pl}').replace(/\./g, '{dt}').replace(/%/g, '{pc}')) + '&type=cover&bidx=<?php echo (int)$current_bidx; ?>&cv=' + b.t;
            var toBar = function (bn) { var band = bn.querySelector('.mc-banner-band'); if (!band) return; var tb = document.createElement('div'); tb.className = 'mc-titlebar'; while (band.firstChild) tb.appendChild(band.firstChild); bn.parentNode.replaceChild(tb, bn); };
            var watch = function (bn) { var mi = bn.querySelector('.mc-banner-img'); if (mi) mi.onload = function () { if (this.naturalWidth <= 1 && bn.parentNode) toBar(bn); }; };
            if (<?php echo (isset($use_cover) && $use_cover == "y") ? 'true' : 'false'; ?> && d === np(<?php echo json_encode((string)$getdir, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>) && d !== '/') {
                var bn = document.querySelector('.mc-banner'), tb = document.querySelector('.mc-titlebar');
                if (bn) watch(bn); // 위에서 주소를 이미 바꿈
                else if (tb) {
                    bn = document.createElement('div'); bn.className = 'mc-banner';
                    var bg = document.createElement('img'); bg.className = 'mc-banner-bg'; bg.alt = ''; bg.setAttribute('aria-hidden', 'true');
                    var mi = document.createElement('img'); mi.className = 'mc-banner-img'; mi.alt = '';
                    var band = document.createElement('div'); band.className = 'mc-banner-band';
                    while (tb.firstChild) band.appendChild(tb.firstChild);
                    bn.appendChild(bg); bn.appendChild(mi); bn.appendChild(band); tb.parentNode.replaceChild(bn, tb);
                    watch(bn); bg.setAttribute('src', csrc); mi.setAttribute('src', csrc);
                }
            }
            if (<?php echo (isset($use_listcover) && $use_listcover == "y") ? 'true' : 'false'; ?>) document.querySelectorAll('.card[data-folder-path]').forEach(function (c) {
                if (np(c.getAttribute('data-folder-path')) !== d) return;
                var im = c.querySelector('img.rounded-lg');
                if (im && /^data:/.test(im.getAttribute('src') || '')) { im.style.objectFit = 'contain'; im.setAttribute('src', csrc); }
            });
        }
    }
    window.addEventListener('pageshow', apply);
    window.addEventListener('storage', function (e) { if (e.key === 'mc_cover_bump') apply(); });
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') apply(); });
})();
</script>

<!-- ✅ 즐겨찾기 토글 기능 -->
<style>
.fav-btn {
    position: absolute;
    top: 8px;
    right: 5px;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    z-index: 10;
    font-size: 14px;
    line-height: 1;
    transition: transform 0.2s;
    filter: grayscale(100%);
}
.fav-btn:hover {
    transform: scale(1.2);
}
.fav-btn.is-fav {
    filter: none;
}
/* ★ (2026-11-05 22:50) 목록·그리드 카드의 ★ — 마우스를 올려도 커지지 않고 ⓘ 처럼 조금 진해지기만(펜닐 요청). 휴대폰(hover 없음)은 누른 뒤 남는 상태 없게 그대로 */
.grid .card > .fav-btn { transition: filter .15s; }
.grid .card > .fav-btn:hover { transform: none; }
@media (hover: hover) {
    .grid .card > .fav-btn:hover { filter: grayscale(100%) brightness(.72); }
    .grid .card > .fav-btn.is-fav:hover { filter: brightness(.88); }
}
</style>
<script>
// ✅ 뒤로가기 시 즐겨찾기 상태 동기화 (viewer에서 변경된 경우)
// ✅ 새로고침 없이 북마크/자동저장 목록 갱신: 현재 index를 다시 받아 #bookmarkTable 내용만 교체
//    (서버 렌더링을 그대로 재사용 → 토큰/인코딩/링크 동일. 실패 시 location.reload()로 안전 폴백)
function refreshBookmarkTable() {
    try {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', location.href, true);
        try { xhr.setRequestHeader('X-MC-Refresh', '1'); } catch (_h) {} // ★ (2026-11-02 21:18) 서버가 검색 기록을 다시 쓰지 않게
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            if (xhr.status !== 200) { location.reload(); return; }
            try {
                var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
                // ★ (2026-11-02 01:43) 카드의 툰/북 배지도 최신으로(뷰어에서 모드를 바꾸고 돌아오면 새로고침해야 바뀌었음) — 같은 경로의 배지 글자만 바꿈
                try {
                    // ★ (2026-11-05) 보기 방식 배지(data-mcorder)도 같은 방식으로
                    ['data-mcmode', 'data-mcorder'].forEach(function (_at) {
                        var _mm = {};
                        doc.querySelectorAll('[' + _at + ']').forEach(function (el) { _mm[el.getAttribute(_at)] = el.textContent; });
                        document.querySelectorAll('[' + _at + ']').forEach(function (el) {
                            var k = el.getAttribute(_at);
                            if (Object.prototype.hasOwnProperty.call(_mm, k) && el.textContent !== _mm[k]) el.textContent = _mm[k];
                        });
                    });
                } catch (_e2) {}
                // ★ (2026-11-03 14:38) 폴더 화면 '이어 읽기'(노란 단추)도 최신으로 — 읽고 뒤로 오면 새로고침해야 바뀌었음(배지와 같은 방식)
                try {
                    var _cf = doc.getElementById('mcContinue'), _cc = document.getElementById('mcContinue');
                    if (_cf && _cc && _cf.outerHTML !== _cc.outerHTML) _cc.outerHTML = _cf.outerHTML;
                } catch (_e3) {}
                var fresh = doc.getElementById('bookmarkTable');
                var cur = document.getElementById('bookmarkTable');
                if (fresh && cur) {
                    cur.innerHTML = fresh.innerHTML;   // 카운트행+모든 행을 최신으로 교체(인라인 onclick 유지)
                    try { if (window.mcFavReapplySel) window.mcFavReapplySel(); } catch(_e){} // ✅ 교체로 사라진 '최근 본 항목' 강조 복구
                } else {
                    location.reload();                 // 구조 변동 시 안전하게 전체 새로고침
                }
            } catch (e) { location.reload(); }
        };
        xhr.onerror = function(){ location.reload(); };
        xhr.send();
    } catch (e) { location.reload(); }
}

function syncFavorites() {
    var btns = document.querySelectorAll('.fav-btn');
    if (!btns.length) return;
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'bookmark.php?mode=list_favorites&bidx=<?php echo (int)$current_bidx; ?>', true); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 즐겨찾기만(같은 경로의 다른 콘텐츠 폴더 별이 켜지던 것)
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (!resp.success) return;
                var favSet = {};
                for (var i = 0; i < resp.favorites.length; i++) {
                    favSet[resp.favorites[i]] = true;
                }
                btns.forEach(function(btn) {
                    // ★ (2026-10-29) 경로는 data-fav-path(HTML 이스케이프된 원본)에서 읽음 — onclick에 경로를 넣지 않음(따옴표 포함 이름 안전)
                    var filePath = btn.getAttribute('data-fav-path');
                    if (filePath === null) {
                        var path = btn.getAttribute('onclick');
                        var m = path && path.match(/toggleFavorite\(this,\s*'([^']+)'/);
                        if (!m) return;
                        filePath = m[1].replace(/\\'/g, "'");
                    }
                    if (favSet[filePath]) {
                        btn.classList.add('is-fav');
                        btn.title = i18n.unfavorite;
                    } else {
                        btn.classList.remove('is-fav');
                        btn.title = i18n.add_favorite;
                    }
                });
            } catch(e) { console.error('syncFavorites error:', e); }
        }
    };
    xhr.send();
}

function toggleFavorite(btn, filePath, bidx, e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    // ★ (2026-10-29) 경로가 비면 요청하지 않음(data-fav-path가 비는 경우 빈 키 '' 가 즐겨찾기에 추가되는 것 방지)
    if (!filePath) return;
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'bookmark.php?mode=toggle_favorite', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        if (resp.is_favorite) {
                            btn.classList.add('is-fav');
                            btn.innerHTML = '⭐';
                            btn.title = i18n.unfavorite;
                            // 북마크 테이블에 즐겨찾기 항목 추가
                            addFavoriteToBookmark(filePath, bidx, resp.filename, resp.viewer);
                        } else {
                            btn.classList.remove('is-fav');
                            btn.innerHTML = '⭐';
                            btn.title = i18n.add_favorite;
                            // 북마크 테이블에서 즐겨찾기 항목 제거
                            removeFavoriteFromBookmark(filePath);
                        }
                    } else {
                        alert(resp.error || resp.message || i18n.error_occurred);
                    }
                } catch(e) {
                    console.error('JSON parse error:', e);
                }
            }
        }
    };
    xhr.send('file=' + encodeURIComponent(filePath) + '&bidx=' + bidx);
}

function addFavoriteToBookmark(filePath, bidx, filename, viewer) {
    // ★ (2026-10-29) 🔖 목록에는 즐겨찾기 개별 행을 두지 않음(즐겨찾기 책장으로 대체) → 🔖 버튼 표시 + 개수만 갱신
    showBookmarkButton();
    updateFavCount(1);
}

function removeFavoriteFromBookmark(filePath) {
    // ★ (2026-10-29) 개별 행이 없으므로(즐겨찾기 책장으로 대체) 개수만 갱신. 예전 행이 남아 있으면 함께 제거
    var row = document.querySelector('tr[data-fav-path="' + CSS.escape(filePath) + '"]');
    if (row) row.remove();
    updateFavCount(-1);
}

function updateFavCount(delta) {
    var el = document.getElementById('favCount');
    if (el) {
        var count = parseInt(el.textContent || '0', 10) + delta;
        if (count < 0) count = 0;
        el.textContent = count;
    }
}

function removeFavoriteClick(btn, filePath, bidx) {
    if (!filePath) return; // ★ (2026-10-29) 빈 경로 요청 방지
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'bookmark.php?mode=toggle_favorite', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success && !resp.is_favorite) {
                    removeFavoriteFromBookmark(filePath);
                    // 파일 카드의 별도 업데이트
                    updateFileCardStar(filePath, false);
                }
            } catch(e) {}
        }
    };
    xhr.send('file=' + encodeURIComponent(filePath) + '&bidx=' + bidx);
}

function updateFileCardStar(filePath, isFavorite) {
    // 파일 카드의 즐겨찾기 버튼 찾기
    var btns = document.querySelectorAll('.fav-btn');
    btns.forEach(function(btn) {
        // ★ (2026-10-29) data-fav-path 정확 일치 우선(부분 문자열 오매칭 방지), 없으면 기존 방식
        var dp = btn.getAttribute('data-fav-path');
        var onclick = btn.getAttribute('onclick') || '';
        if (dp !== null ? (dp === filePath) : (onclick.indexOf(filePath.replace(/'/g, "\\'")) >= 0 || onclick.indexOf(filePath) >= 0)) {
            if (isFavorite) {
                btn.classList.add('is-fav');
                btn.innerHTML = '⭐';
                btn.title = i18n.unfavorite;
            } else {
                btn.classList.remove('is-fav');
                btn.innerHTML = '⭐';
                btn.title = i18n.add_favorite;
            }
        }
    });
}

function showBookmarkButton() {
    var bmBtn = document.querySelector('button[onclick="bookmark_toggle();"]');
    if (bmBtn) bmBtn.style.display = '';
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

<!-- ✅ 이미지 로드 완료 시 fade-in -->
<script>
(function() {
    var imgs = document.querySelectorAll('.card-img');
    for (var i = 0; i < imgs.length; i++) {
        if (imgs[i].complete) {
            imgs[i].classList.add('loaded');
        } else {
            imgs[i].onload = function() { this.classList.add('loaded'); };
            imgs[i].onerror = function() { this.classList.add('loaded'); };
        }
    }
})();
</script>

<!-- 페이지 생성 시간: <?php echo round((microtime(true) - $_start_time) * 1000, 2); ?>ms | 캐시: <?php echo $_cache_debug ?? 'N/A'; ?> | 캐시읽기: <?php echo $_time_cache_read ?? 0; ?>ms | 정렬: <?php echo $_time_after_cache ?? 0; ?>ms | 목록만들기: <?php echo $_time_build ?? 0; ?>ms | 카드그리기: <?php echo isset($_t_cards) ? round((microtime(true) - $_t_cards) * 1000, 2) : 0; ?>ms | maxview: <?php echo $current_maxview ?? 'N/A'; ?> (folder:<?php echo $maxview_folder ?? 'N/A'; ?>/file:<?php echo $maxview_file ?? 'N/A'; ?>) | files:<?php echo $real_file_count ?? 'N/A'; ?> folders:<?php echo (count($dir_list ?? []) + count($title_list ?? [])); ?> | mobile:<?php echo ($is_mobile ?? false) ? 'Y' : 'N'; ?> -->
<?php // ★ (2026-11-03 14:10) 속도 기록(src/perf/날짜.log) — 위 주석과 같은 값 + 폴더·파일 수. 페이지 소스를 보지 않아도 서버에서 확인
if (function_exists('mc_perf_script')) echo mc_perf_script('index', ['bidx' => (int)($current_bidx ?? 0), 'dir' => mb_substr((string)($getdir ?? ''), 0, 200), 'q' => (isset($q) && $q !== '') ? 1 : 0,
    'cache' => (string)($_cache_debug ?? ''), 'read_ms' => (float)($_time_cache_read ?? 0), 'build_ms' => (float)($_time_build ?? 0),
    'cards_ms' => isset($_t_cards) ? round((microtime(true) - $_t_cards) * 1000, 1) : 0, 'gen_ms' => isset($_start_time) ? round((microtime(true) - $_start_time) * 1000, 1) : 0,
    'folders' => count($dir_list ?? []) + count($title_list ?? []), 'files' => (int)($real_file_count ?? 0), 'mobile' => !empty($is_mobile) ? 1 : 0]); ?>

<!-- ✅ 내 정보 모달 -->
<style>
#profileModal .modal-content {
    border-radius: 12px;
    overflow: hidden;
}
#profileModal .modal-header {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
}
#profileModal .nav-tabs .nav-link {
    color: #666;
    border: none;
    padding: 10px 20px;
}
#profileModal .nav-tabs .nav-link.active {
    color: #17a2b8;
    border-bottom: 2px solid #17a2b8;
    background: transparent;
}
#profileModal .form-group label {
    font-weight: 600;
    font-size: 14px;
    color: #555;
}
#profileModal .form-control {
    border-radius: 8px;
}
#profileModal .btn-primary {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
    border: none;
    border-radius: 8px;
}
</style>
<div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
<div class="modal-content">
    <div class="modal-header text-white py-2">
        <h5 class="modal-title" id="profileModalLabel"><?php echo __("index_my_profile"); ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <div class="modal-body p-0">
        <!-- 탭 메뉴 -->
        <ul class="nav nav-tabs" id="profileTabs" role="tablist" style="border-bottom: 1px solid #dee2e6;">
            <li class="nav-item">
                <a class="nav-link active" id="tab-info" data-toggle="tab" href="#profile-info" role="tab"><?php echo __("profile_tab_info"); ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-loginlog" data-toggle="tab" href="#profile-loginlog" role="tab" onclick="loadLoginLogs(1)"><?php echo __("profile_tab_loginlog"); ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-password" data-toggle="tab" href="#profile-password" role="tab"><?php echo __("profile_tab_password"); ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-email" data-toggle="tab" href="#profile-email" role="tab"><?php echo __("profile_tab_email"); ?></a>
            </li>
            <?php if (($_SESSION['user_group'] ?? '') !== 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link text-danger" id="tab-withdraw" data-toggle="tab" href="#profile-withdraw" role="tab"><?php echo __("profile_tab_withdraw"); ?></a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="tab-content p-3">
            <!-- 기본 정보 탭 -->
            <div class="tab-pane fade show active" id="profile-info" role="tabpanel">
                <div class="text-center py-3">
                    <div style="font-size:48px;margin-bottom:10px;">👤</div>
                    <h5 class="mb-3"><?php echo h($_SESSION['user_id'] ?? ''); ?></h5>
                    <div class="text-muted mb-2">
                        <span class="badge badge-secondary"><?php echo h($_SESSION['user_group'] ?? 'group2'); ?></span>
                    </div>
                    <div id="profileInfoDetail" class="mt-3" style="font-size:14px;color:#666;">
                        <div class="spinner-border spinner-border-sm" role="status"></div> <?php echo __("ui_loading_info"); ?>
                    </div>
                </div>
            </div>
            
            <!-- 로그인 기록 탭 -->
            <div class="tab-pane fade" id="profile-loginlog" role="tabpanel">
                <div id="loginLogContent">
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm" role="status"></div> <?php echo __("ui_loading"); ?>
                    </div>
                </div>
            </div>
            
            <!-- 비밀번호 변경 탭 -->
            <div class="tab-pane fade" id="profile-password" role="tabpanel">
                <div id="passwordChangeForm">
                    <div class="form-group">
                        <label><?php echo __h("profile_current_password"); ?></label>
                        <input type="password" class="form-control" id="currentPassword" placeholder="<?php echo __h('profile_current_password_ph'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo __h("profile_new_password"); ?></label>
                        <input type="password" class="form-control" id="newPassword" placeholder="<?php echo __h('profile_new_password_ph'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo __h("profile_confirm_password"); ?></label>
                        <input type="password" class="form-control" id="confirmPassword" placeholder="<?php echo __h('profile_confirm_password_ph'); ?>">
                    </div>
                    <div id="passwordMessage" class="mb-3" style="display:none;"></div>
                    <button type="button" class="btn btn-primary btn-block" id="btnChangePassword" onclick="changePassword()">
                        <?php echo __h("profile_change_password_btn"); ?>
                    </button>
                </div>
            </div>
            
            <!-- 이메일 변경 탭 -->
            <div class="tab-pane fade" id="profile-email" role="tabpanel">
                <div id="emailChangeForm">
                    <div class="form-group">
                        <label><?php echo __h("profile_current_email"); ?></label>
                        <div id="currentEmailDisplay" class="form-control-plaintext text-muted">
                            <span class="spinner-border spinner-border-sm" role="status"></span> <?php echo __("ui_loading"); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php echo __h("profile_new_email"); ?></label>
                        <input type="email" class="form-control" id="newEmail" placeholder="example@email.com">
                        <small class="text-muted"><?php echo __h("profile_email_empty_note"); ?></small>
                    </div>
                    <div class="form-group"><!-- ★ (2026-11-02 14:29) 이메일 바꿀 때도 현재 비밀번호 확인 -->
                        <label><?php echo __h("profile_current_password"); ?></label>
                        <input type="password" class="form-control" id="emailCurrentPassword" placeholder="<?php echo __h('profile_current_password_ph'); ?>">
                    </div>
                    <div id="emailMessage" class="mb-3" style="display:none;"></div>
                    <button type="button" class="btn btn-primary btn-block" id="btnChangeEmail" onclick="changeEmail()">
                        <?php echo __h("profile_change_email_btn"); ?>
                    </button>
                </div>
            </div>
            
            <?php if (($_SESSION['user_group'] ?? '') !== 'admin'): ?>
            <!-- 회원 탈퇴 탭 -->
            <div class="tab-pane fade" id="profile-withdraw" role="tabpanel">
                <div class="text-center py-3">
                    <div style="font-size:48px;margin-bottom:10px;">⚠️</div>
                    <h5 class="text-danger mb-3"><?php echo __h("profile_withdraw_title"); ?></h5>
                    <p class="text-muted small mb-4">
                        <?php echo __("profile_withdraw_warning"); ?>
                    </p>
                </div>
                <div class="form-group">
                    <label><?php echo __h("profile_password_verify"); ?></label>
                    <input type="password" class="form-control" id="withdrawPassword" placeholder="<?php echo __h('profile_current_password_ph'); ?>">
                </div>
                <div id="withdrawMessage" class="mb-3" style="display:none;"></div>
                <button type="button" class="btn btn-danger btn-block" onclick="withdrawAccount()">
                    <?php echo __("profile_withdraw_btn"); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><?php echo __h('common_close'); ?></button>
    </div>
</div>
</div>
</div>

<script>
var profileCsrfToken = '<?php echo generate_csrf_token(); ?>';

function openProfileModal() {
    $('#profileModal').modal({backdrop: 'static', keyboard: true});
    loadProfileInfo();
}

function loadProfileInfo() {
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'profile_action=get_info&csrf_token=' + encodeURIComponent(profileCsrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var info = '<div style="text-align:left;">';
            info += '<p><strong>📧 ' + i18n.email_label + ':</strong> ' + (data.email ? escapeHtml(String(data.email)) : '<span class="text-warning">' + i18n.unregistered + '</span>') + '</p>'; // ★ (2026-11-02 11:34) 이메일·IP·국가는 글자로(따옴표 이메일 "<svg…>"@… 가 형식 검사를 통과해 스크립트로 실행됐음)
            info += '<p><strong>📅 ' + i18n.joined_label + ':</strong> ' + (data.created_at ? escapeHtml(String(data.created_at)) : '<span class="text-muted">' + i18n.no_info + '</span>') + '</p>';
            info += '<p><strong>🌐 ' + i18n.ip_label + ':</strong> <code>' + escapeHtml(String(data.current_ip || '-')) + '</code></p>';
            info += '<p><strong>🌍 ' + i18n.country_label + ':</strong> <span class="badge badge-info">' + escapeHtml(String(data.current_country || '-')) + '</span></p>';
            info += '</div>';
            document.getElementById('profileInfoDetail').innerHTML = info;
            document.getElementById('currentEmailDisplay').innerHTML = data.email ? escapeHtml(String(data.email)) : '<span class="text-warning">' + i18n.no_email + '</span>';
            document.getElementById('newEmail').value = data.email || '';
        }
    })
    .catch(function(e) {
        document.getElementById('profileInfoDetail').innerHTML = '<span class="text-danger">' + i18n.load_failed + '</span>';
    });
}

function loadLoginLogs(page) {
    var container = document.getElementById('loginLogContent');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm" role="status"></div> ' + <?php echo js(h(__("ui_loading"))); /* ★ (2026-11-02 19:04) 문구 이스케이프 */ ?> + '</div>';
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'profile_action=get_login_logs&page=' + page + '&csrf_token=' + encodeURIComponent(profileCsrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var html = '';
            
            if (data.logs.length === 0) {
                html = '<div class="text-center py-4 text-muted">📭 ' + i18n.login_log_empty + '</div>';
            } else {
                html += '<div class="small text-muted mb-2">' + i18n.login_log_total.replace('%s', data.total) + '</div>';
                html += '<div class="table-responsive"><table class="table table-sm table-hover" style="font-size:12px;">';
                html += '<thead class="thead-light"><tr><th>' + i18n.login_log_date + '</th><th>' + i18n.login_log_ip + '</th><th>' + i18n.login_log_country + '</th><th class="d-none d-sm-table-cell">' + i18n.login_log_device + '</th></tr></thead><tbody>';
                
                for (var i = 0; i < data.logs.length; i++) {
                    var log = data.logs[i];
                    var ua = log.user_agent || '';
                    var isMobile = /iPhone|iPad|iPod|Android|Mobile/i.test(ua);
                    var deviceIcon = isMobile ? '📱' : '💻';
                    var country = log.country || '-';
                    
                    html += '<tr>';
                    html += '<td style="white-space:nowrap;">' + escapeHtml(String(log.datetime || '-')) + '</td>'; // ★ (2026-11-02 11:34) 글자로
                    html += '<td><code style="font-size:11px;">' + escapeHtml(String(log.ip || '-')) + '</code></td>';
                    html += '<td><span class="badge badge-secondary">' + escapeHtml(String(country)) + '</span></td>';
                    html += '<td class="d-none d-sm-table-cell">' + deviceIcon + '</td>';
                    html += '</tr>';
                }
                
                html += '</tbody></table></div>';
                
                // 페이지네이션
                if (data.total_pages > 1) {
                    html += '<nav class="mt-2"><ul class="pagination pagination-sm justify-content-center mb-0">';
                    if (window.mcPgGroup) html += window.mcPgGroup.bs(data.page, data.total_pages, { click: function (p) { return 'loadLoginLogs(' + p + ')'; } }); // ★ (2026-11-10 19:19, 펜닐 요청 — fix105) 폴더 목록과 같은 묶음 방식
                    else {
                    if (data.page > 1) {
                        html += '<li class="page-item"><a class="page-link" href="#" onclick="loadLoginLogs(1);return false;">«</a></li>';
                        html += '<li class="page-item"><a class="page-link" href="#" onclick="loadLoginLogs(' + (data.page - 1) + ');return false;">‹</a></li>';
                    }
                    
                    var startPage = Math.max(1, data.page - 2);
                    var endPage = Math.min(data.total_pages, data.page + 2);
                    
                    for (var p = startPage; p <= endPage; p++) {
                        var activeClass = (p === data.page) ? ' active' : '';
                        html += '<li class="page-item' + activeClass + '"><a class="page-link" href="#" onclick="loadLoginLogs(' + p + ');return false;">' + p + '</a></li>';
                    }
                    
                    if (data.page < data.total_pages) {
                        html += '<li class="page-item"><a class="page-link" href="#" onclick="loadLoginLogs(' + (data.page + 1) + ');return false;">›</a></li>';
                        html += '<li class="page-item"><a class="page-link" href="#" onclick="loadLoginLogs(' + data.total_pages + ');return false;">»</a></li>';
                    }
                    }
                    html += '</ul></nav>';
                }
            }
            
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="text-center py-3 text-danger">' + i18n.log_load_failed + '</div>';
        }
    })
    .catch(function(e) {
        container.innerHTML = '<div class="text-center py-3 text-danger">' + i18n.log_load_failed + '</div>';
    });
}

function changePassword() {
    var current = document.getElementById('currentPassword').value;
    var newPass = document.getElementById('newPassword').value;
    var confirmPass = document.getElementById('confirmPassword').value;
    var msgEl = document.getElementById('passwordMessage');
    var btn = document.getElementById('btnChangePassword');
    
    // 유효성 검사
    if (!current) {
        msgEl.innerHTML = '<div class="alert alert-danger py-2">' + i18n.enter_current_password + '</div>';
        msgEl.style.display = 'block';
        return;
    }
    
    if (!newPass || newPass.length < 8) {
        msgEl.innerHTML = '<div class="alert alert-danger py-2">' + i18n.password_min_8 + '</div>';
        msgEl.style.display = 'block';
        return;
    }
    
    if (newPass !== confirmPass) {
        msgEl.innerHTML = '<div class="alert alert-danger py-2">' + i18n.password_mismatch + '</div>';
        msgEl.style.display = 'block';
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + i18n.processing;
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'profile_action=change_password&csrf_token=' + encodeURIComponent(profileCsrfToken) +
              '&current_password=' + encodeURIComponent(current) +
              '&new_password=' + encodeURIComponent(newPass) +
              '&confirm_password=' + encodeURIComponent(confirmPass)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = i18n.change_password;
        
        if (data.success) {
            msgEl.innerHTML = '<div class="alert alert-success py-2">' + data.message + '</div>';
            // 입력 필드 초기화
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        } else {
            msgEl.innerHTML = '<div class="alert alert-danger py-2">' + data.error + '</div>';
        }
        msgEl.style.display = 'block';
    })
    .catch(function(e) {
        btn.disabled = false;
        btn.innerHTML = i18n.change_password;
        msgEl.innerHTML = '<div class="alert alert-danger py-2">' + i18n.error_occurred + '</div>';
        msgEl.style.display = 'block';
    });
}

function changeEmail() {
    var newEmail = document.getElementById('newEmail').value.trim();
    var msgEl = document.getElementById('emailMessage');
    var btn = document.getElementById('btnChangeEmail');
    var curPwEl = document.getElementById('emailCurrentPassword');
    var curPw = curPwEl ? curPwEl.value : '';
    if (!curPw) { // ★ (2026-11-02 14:29) 현재 비밀번호 필요
        msgEl.innerHTML = '<div class="alert alert-danger py-2">' + i18n.enter_current_password + '</div>';
        msgEl.style.display = 'block';
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + i18n.processing;
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'profile_action=change_email&csrf_token=' + encodeURIComponent(profileCsrfToken) +
              '&new_email=' + encodeURIComponent(newEmail) + '&current_password=' + encodeURIComponent(curPw)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = i18n.change_email;
        
        if (data.success) {
            msgEl.innerHTML = '<div class="alert alert-success py-2">' + data.message + '</div>';
            if (curPwEl) curPwEl.value = '';
            document.getElementById('currentEmailDisplay').innerHTML = data.email ? escapeHtml(String(data.email)) : '<span class="text-warning">' + i18n.no_email + '</span>';
            loadProfileInfo(); // 기본 정보 탭도 갱신
        } else {
            msgEl.innerHTML = '<div class="alert alert-danger py-2">' + data.error + '</div>';
        }
        msgEl.style.display = 'block';
    })
    .catch(function(e) {
        btn.disabled = false;
        btn.innerHTML = i18n.change_email;
        msgEl.innerHTML = '<div class="alert alert-danger py-2">' + i18n.error_occurred + '</div>';
        msgEl.style.display = 'block';
    });
}

function withdrawAccount() {
    var password = document.getElementById('withdrawPassword').value;
    var msgEl = document.getElementById('withdrawMessage');
    
    if (!password) {
        msgEl.innerHTML = '<div class="alert alert-danger py-2">' + i18n.enter_password + '</div>';
        msgEl.style.display = 'block';
        return;
    }
    
    if (!confirm(i18n.withdraw_confirm)) {
        return;
    }
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'profile_action=withdraw&csrf_token=' + encodeURIComponent(profileCsrfToken) +
              '&password=' + encodeURIComponent(password)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert(i18n.withdraw_complete);
            window.location.href = 'login.php';
        } else {
            msgEl.innerHTML = '<div class="alert alert-danger py-2">' + data.error + '</div>';
            msgEl.style.display = 'block';
        }
    })
    .catch(function(e) {
        msgEl.innerHTML = '<div class="alert alert-danger py-2">' + i18n.error_occurred + '</div>';
        msgEl.style.display = 'block';
    });
}

// 모달 닫힐 때 폼 초기화
$('#profileModal').on('hidden.bs.modal', function() {
    // 비밀번호 필드 초기화
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('passwordMessage').style.display = 'none';
    document.getElementById('emailMessage').style.display = 'none';
    var emailCurPw = document.getElementById('emailCurrentPassword'); if (emailCurPw) emailCurPw.value = ''; // ★ (2026-11-02 14:33) 이메일 변경 칸의 비밀번호도 비움
    // 탈퇴 필드 초기화
    var withdrawPass = document.getElementById('withdrawPassword');
    var withdrawMsg = document.getElementById('withdrawMessage');
    if (withdrawPass) withdrawPass.value = '';
    if (withdrawMsg) withdrawMsg.style.display = 'none';
    // 탭을 기본 정보로 리셋
    $('#profileTabs a[href="#profile-info"]').tab('show');
});
</script>

<!-- ✅ 개인 설정 모달 -->
<style>
.badge-success.settings-2fa-btn:hover {
    background-color: #1e7e34 !important;
}
#settingsModal .modal-content {
    border-radius: 12px;
    overflow: hidden;
}
#settingsModal .modal-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}
</style>
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
<div class="modal-content">
    <div class="modal-header bg-secondary text-white py-2">
        <h5 class="modal-title" id="settingsModalLabel"><?php echo __("index_2fa_settings"); ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <div class="modal-body">
        <!-- 2FA 설정 -->
        <div id="totp-settings-body">
            <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2"><?php echo __("ui_loading"); ?></div>
            </div>
        </div>
    </div>
    <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><?php echo __h('common_close'); ?></button>
    </div>
</div>
</div>
</div>

<script>
var totpCsrfToken = '<?php echo generate_csrf_token(); ?>';

function openSettingsModal() {
    $('#settingsModal').modal({backdrop: 'static', keyboard: true});
    loadTotpStatus();
}

function loadTotpStatus() {
    var body = document.getElementById('totp-settings-body');
    body.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">' + <?php echo js(h(__("ui_loading"))); /* ★ (2026-11-02 19:04) 문구 이스케이프 */ ?> + '</div></div>';
    
    var formData = new FormData();
    formData.append('totp_action', 'get_status');
    formData.append('csrf_token', totpCsrfToken);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.enabled) {
            renderTotpEnabled(data);
        } else if (data.has_secret) {
            renderTotpPending();
        } else {
            renderTotpDisabled();
        }
    })
    .catch(function(err) {
        body.innerHTML = '<div class="alert alert-danger">' + i18n.load_failed + ': ' + err.message + '</div>';
    });
}

function renderTotpDisabled() {
    var html = '';
    
    html += '<div class="text-center py-4">';
    html += '<div class="mb-3"><span style="font-size:3em;">🔓</span></div>';
    html += '<h5 class="mb-2">' + i18n.twofa_not_set + '</h5>';
    html += '<p class="text-muted small mb-4">' + i18n.twofa_desc + '</p>';
    html += '<button type="button" class="btn btn-primary btn-lg px-4" onclick="generateTotp()">🔑 ' + i18n.twofa_setup + '</button>';
    html += '</div>';
    
    html += '<div class="bg-light rounded p-3 mt-4">';
    html += '<div class="small text-muted">';
    html += '<strong>📱 ' + i18n.twofa_supported_apps + '</strong><br>';
    html += i18n.twofa_apps_list;
    html += '</div>';
    html += '</div>';
    
    document.getElementById('totp-settings-body').innerHTML = html;
}

function renderTotpPending() {
    // 이미 생성된 비밀키가 있으면 캐시에서 사용하거나 다시 생성
    var userTotp = window._pendingTotp || null;
    if (userTotp) {
        showQrSetup(userTotp.secret, userTotp.qr_url, userTotp.backup_codes);
    } else {
        renderTotpDisabled(); // ★ (2026-11-02 14:32) 바로 새 키를 만들지 않음 — 끈 뒤 '꺼짐' 화면 대신 QR 화면이 뜨고, 창을 열 때마다 새 비밀키가 저장되던 것('설정' 단추로 시작)
    }
}

function renderTotpEnabled(data) {
    var html = '';
    
    // 상태 표시
    html += '<div class="text-center py-3 mb-3">';
    html += '<div class="mb-2"><span style="font-size:2.5em;">🔒</span></div>';
    html += '<h5 class="text-success mb-1">' + i18n.twofa_enabled + '</h5>';
    if (data.enabled_at) {
        html += '<small class="text-muted">' + data.enabled_at + ' ' + i18n.twofa_since + '</small>';
    }
    html += '</div>';
    
    // 비활성화
    html += '<div class="border rounded p-3 mb-3">';
    html += '<div class="font-weight-bold mb-2">🔓 ' + i18n.twofa_disable_title + '</div>';
    html += '<input type="text" id="disable-otp" class="form-control mb-2" placeholder="' + i18n.twofa_disable_placeholder + '" autocomplete="off">';
    html += '<button type="button" class="btn btn-warning btn-block" onclick="disableTotp()">' + i18n.twofa_disable_btn + '</button>';
    html += '</div>';
    
    // 백업 코드
    html += '<div class="border rounded p-3 mb-3">';
    html += '<div class="d-flex justify-content-between align-items-center mb-2">';
    html += '<span class="font-weight-bold">📋 ' + i18n.twofa_backup_title + '</span>';
    html += '<span class="badge badge-' + (data.backup_count > 3 ? 'info' : 'warning') + '">' + data.backup_count + ' ' + i18n.twofa_backup_remaining + '</span>';
    html += '</div>';
    html += '<p class="small text-muted mb-2">' + i18n.twofa_backup_desc + '</p>';
    html += '<button type="button" class="btn btn-outline-secondary btn-block btn-sm" onclick="regenerateBackup()">🔄 ' + i18n.twofa_backup_regen + '</button>';
    html += '</div>';
    
    // 완전 삭제
    html += '<details class="mt-3">';
    html += '<summary class="text-danger small" style="cursor:pointer;">⚠️ ' + i18n.twofa_delete_title + '</summary>';
    html += '<div class="mt-2 p-3 border border-danger rounded">';
    html += '<p class="small text-danger mb-2">' + i18n.twofa_delete_desc + '</p>';
    html += '<input type="text" id="reset-confirm" class="form-control form-control-sm mb-2" placeholder="' + i18n.twofa_delete_ph + '">';
    html += '<input type="password" id="reset-password" class="form-control form-control-sm mb-2" autocomplete="current-password" placeholder="' + i18n.twofa_cur_pw_ph + '">'; // ★ (2026-10-30) 현재 비밀번호 확인
    html += '<button type="button" class="btn btn-danger btn-sm btn-block" onclick="resetTotp()">🗑️ ' + i18n.twofa_delete_btn + '</button>';
    html += '</div>';
    html += '</details>';
    
    document.getElementById('totp-settings-body').innerHTML = html;
}

function generateTotp() {
    var body = document.getElementById('totp-settings-body');
    body.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-success" role="status"></div><div class="mt-2">' + i18n.twofa_generating + '</div></div>';
    
    var formData = new FormData();
    formData.append('totp_action', 'generate');
    formData.append('csrf_token', totpCsrfToken);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            window._pendingTotp = data;
            showQrSetup(data.secret, data.qr_url, data.backup_codes);
        } else {
            body.innerHTML = '<div class="alert alert-danger">' + (data.error || i18n.twofa_gen_fail) + '</div>';
        }
    })
    .catch(function(err) {
        body.innerHTML = '<div class="alert alert-danger">' + i18n.error_occurred + ': ' + err.message + '</div>';
    });
}

function showQrSetup(secret, qrUrl, backupCodes) {
    // ★ (2026-11-02 09:01) QR 은 이 브라우저에서 직접 그림(js/qrcode-generator.js, MIT) — 전에는 비밀키가 든 주소를 외부 사이트(api.qrserver.com)로 보내 그림을 받았음
    var qrFailHtml = '<div class=text-danger style=width:180px;height:180px;display:flex;align-items:center;justify-content:center;>' + (window.i18n ? i18n.twofa_qr_fail : 'QR Load Failed') + '<br>' + (window.i18n ? i18n.twofa_enter_key : 'Enter secret key manually') + '</div>';
    
    var html = '';
    
    // 상단 안내
    html += '<div class="alert alert-warning py-2 mb-3 text-center">';
    html += '<strong>⏳ ' + i18n.twofa_register_msg + '</strong>';
    html += '</div>';
    
    // QR 코드 영역
    html += '<div class="text-center mb-4">';
    html += '<div class="d-inline-block p-3 bg-white border rounded shadow-sm">';
    html += '<div id="mcTotpQr" style="width:180px;height:180px;display:flex;align-items:center;justify-content:center;"></div>';
    html += '</div>';
    html += '<div class="small text-muted mt-2">' + i18n.twofa_scan_msg + '</div>';
    html += '</div>';
    
    // 비밀키 (수동 입력용)
    html += '<div class="mb-3" style="font-size:14px;font-weight:600;">📝 ' + i18n.twofa_manual_key + '</div>';
    html += '<div class="bg-light rounded p-3 mb-4">';
    html += '<div class="d-flex align-items-center">';
    html += '<code class="flex-grow-1 text-center" style="font-size:14px;word-break:break-all;">' + secret + '</code>';
    html += '<button class="btn btn-sm btn-outline-secondary ml-2" type="button" onclick="copyToClipboard(\'' + secret + '\')">📋</button>';
    html += '</div>';
    html += '</div>';
    
    // OTP 입력
    html += '<div class="mb-3" style="font-size:14px;font-weight:600;">🔢 ' + i18n.twofa_enter_otp + '</div>';
    html += '<input type="text" id="enable-otp" class="form-control text-center mb-3" placeholder="000000" maxlength="6" style="width:100%;font-size:2em;letter-spacing:0.3em;font-family:monospace;font-weight:bold;padding:20px 15px;" inputmode="numeric" autocomplete="off" oninput="this.value=this.value.replace(/[^0-9]/g,\'\')">';
    html += '<button type="button" class="btn btn-success btn-lg btn-block" onclick="enableTotp()">✅ ' + i18n.twofa_activate + '</button>';
    
    // 백업 코드
    html += '<details class="mt-4">';
    html += '<summary style="cursor:pointer;color:#666;font-size:14px;font-weight:600;">📋 ' + i18n.twofa_backup_preview + '</summary>';
    html += '<div class="p-2 rounded mt-2 text-center" style="background:#e9ecef;">';
    for (var i = 0; i < backupCodes.length; i++) {
        html += '<span class="badge m-1" style="background:#495057;color:#fff;font-size:12px;">' + backupCodes[i] + '</span>';
    }
    html += '</div>';
    html += '</details>';
    
    document.getElementById('totp-settings-body').innerHTML = html;
    mcDrawTotpQr(qrUrl, qrFailHtml);
}

// ★ (2026-11-02 09:01) QR 그리기 — 라이브러리는 처음 쓸 때만 불러옴(실패하면 비밀키 직접 입력 안내)
function mcDrawTotpQr(text, failHtml) {
    var box = document.getElementById('mcTotpQr');
    if (!box) return;
    function draw() {
        try {
            var qr = qrcode(0, 'M');
            qr.addData(String(text));
            qr.make();
            var n = qr.getModuleCount();
            var cell = Math.max(2, Math.floor(180 / (n + 8)));
            var img = new Image();
            img.alt = 'QR Code'; img.width = 180; img.height = 180;
            img.style.imageRendering = 'pixelated';
            img.src = qr.createDataURL(cell, cell * 4);
            box.innerHTML = '';
            box.appendChild(img);
        } catch (e) { var b = document.getElementById('mcTotpQr'); if (b) b.outerHTML = failHtml; }
    }
    if (typeof qrcode === 'function') { draw(); return; }
    var sc = document.createElement('script');
    sc.src = 'js/qrcode-generator.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/qrcode-generator.js'); ?>';
    sc.onload = draw;
    sc.onerror = function () { var b = document.getElementById('mcTotpQr'); if (b) b.outerHTML = failHtml; };
    document.head.appendChild(sc);
}

function enableTotp() {
    var otp = document.getElementById('enable-otp').value.trim();
    if (!otp || otp.length !== 6) {
        alert(i18n.twofa_enter_6digit);
        return;
    }
    
    var formData = new FormData();
    formData.append('totp_action', 'enable');
    formData.append('otp_code', otp);
    formData.append('csrf_token', totpCsrfToken);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('✅ ' + data.message);
            window._pendingTotp = null;
            loadTotpStatus();
        } else {
            alert('❌ ' + (data.error || i18n.twofa_activate_fail));
        }
    });
}

function disableTotp() {
    var otp = document.getElementById('disable-otp').value.trim();
    if (!otp) {
        alert(i18n.twofa_enter_code);
        return;
    }
    
    if (!confirm(i18n.twofa_confirm_disable)) return;
    
    var formData = new FormData();
    formData.append('totp_action', 'disable');
    formData.append('otp_code', otp);
    formData.append('csrf_token', totpCsrfToken);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('✅ ' + data.message);
            loadTotpStatus();
        } else {
            alert('❌ ' + (data.error || i18n.twofa_disable_fail));
        }
    });
}

function resetTotp() {
    var confirm_text = document.getElementById('reset-confirm').value.trim();
    if (confirm_text !== 'RESET') {
        alert(i18n.twofa_enter_reset);
        return;
    }
    
    var cur_pw = (document.getElementById('reset-password') || {}).value || '';
    if (!cur_pw) { alert(i18n.twofa_cur_pw_ph); return; }
    if (!confirm(i18n.twofa_confirm_delete)) return;
    
    var formData = new FormData();
    formData.append('totp_action', 'reset');
    formData.append('confirm', 'RESET');
    formData.append('current_password', cur_pw);
    formData.append('csrf_token', totpCsrfToken);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('✅ ' + data.message);
            loadTotpStatus();
        } else {
            alert('❌ ' + (data.error || i18n.twofa_delete_fail));
        }
    });
}

function regenerateBackup() {
    if (!confirm(i18n.twofa_regen_confirm)) return;
    // ★ (2026-10-30) OTP 코드(또는 백업 코드) 확인
    var code = prompt(i18n.twofa_enter_code);
    if (code === null) return;
    code = String(code).trim();
    if (!code) { alert(i18n.twofa_enter_code); return; }
    
    var formData = new FormData();
    formData.append('totp_action', 'regenerate_backup');
    formData.append('otp_code', code);
    formData.append('csrf_token', totpCsrfToken);
    
    fetch('index.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var codes = data.backup_codes.join('\n');
            alert('✅ ' + data.message + '\n\n' + codes);
            loadTotpStatus();
        } else {
            alert('❌ ' + (data.error || i18n.twofa_regen_fail));
        }
    });
}

function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            alert(i18n.copied);
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert(i18n.copied);
    }
}

<?php if ($is_admin): ?>
// ===== 관리자 전용: 폴더 생성 / 파일 업로드 =====
var adminCsrfToken = '<?php echo generate_csrf_token(); ?>';
// ★ (2026-10-30) 디코딩된 실제 경로를 보냄 — 전에는 주소의 인코딩 형태(Vol{dt}1)를 그대로 보내 이름에 . + % 가 있는 폴더에서
//   업로드가 루트로 가거나 실패하고, 새 폴더가 엉뚱한 이름으로 생겼음
var currentDir = <?php echo js((string)$getdir); ?>;

function openCreateFolderModal() {
    var modal = document.getElementById('createFolderModal');
    if (!modal) {
        // 모달 동적 생성
        var html = '<div id="createFolderModal" class="modal fade" tabindex="-1">';
        html += '<div class="modal-dialog modal-dialog-centered">';
        html += '<div class="modal-content">';
        html += '<div class="modal-header bg-success text-white py-2">';
        html += '<h5 class="modal-title">📁 ' + i18n.new_folder_title + '</h5>';
        html += '<button type="button" class="close text-white" data-dismiss="modal">&times;</button>';
        html += '</div>';
        html += '<div class="modal-body">';
        html += '<div class="form-group">';
        html += '<label>' + i18n.folder_name_label + '</label>';
        html += '<input type="text" id="newFolderName" class="form-control" placeholder="' + i18n.folder_name_ph + '" autofocus>';
        html += '</div>';
        html += '<small class="text-muted">' + i18n.current_location + ': <?php echo h((string)$getdir ?: '(/)'); ?></small>';
        html += '</div>';
        html += '<div class="modal-footer py-2">';
        html += '<button type="button" class="btn btn-secondary" data-dismiss="modal">' + i18n.cancel + '</button>';
        html += '<button type="button" class="btn btn-success" onclick="createFolder()">' + i18n.confirm_text + '</button>';
        html += '</div></div></div></div>';
        document.body.insertAdjacentHTML('beforeend', html);
        modal = document.getElementById('createFolderModal');
    }
    $('#createFolderModal').modal({backdrop: 'static', keyboard: true});
    setTimeout(function() { document.getElementById('newFolderName').focus(); }, 300);
}

function createFolder() {
    var name = document.getElementById('newFolderName').value.trim();
    if (!name) {
        alert(i18n.enter_folder_name);
        return;
    }
    
    var formData = new FormData();
    formData.append('admin_action', 'create_folder');
    formData.append('folder_name', name);
    formData.append('current_dir', currentDir);
    formData.append('csrf_token', adminCsrfToken);
    
    fetch('index.php?bidx=<?php echo $current_bidx; ?>', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('✅ ' + data.message);
            $('#createFolderModal').modal('hide');
            location.reload();
        } else {
            alert('❌ ' + (data.error || i18n.create_fail));
        }
    })
    .catch(function(e) {
        alert('❌ ' + i18n.error_occurred + ': ' + e.message);
    });
}

<?php if (!(isset($is_root) && $is_root == true)): ?>
// 파일 업로드 (하위 폴더에서만) - 드래그앤드롭 UI + 파일별 순차 업로드
var uploadQueue = [];
var isUploading = false;

function openUploadModal() {
    var modal = document.getElementById('uploadModal');
    if (!modal) {
        var html = '<div id="uploadModal" class="modal fade" tabindex="-1">';
        html += '<div class="modal-dialog modal-dialog-centered modal-lg">';
        html += '<div class="modal-content">';
        html += '<div class="modal-header bg-primary text-white py-2">';
        html += '<h5 class="modal-title">📤 ' + i18n.upload_title + '</h5>';
        html += '<button type="button" class="close text-white" data-dismiss="modal">&times;</button>';
        html += '</div>';
        html += '<div class="modal-body">';
        
        // 드래그앤드롭 영역
        html += '<div id="dropZone" style="border:2px dashed #ccc; border-radius:10px; padding:40px; text-align:center; background:#fafafa; color:#212529; cursor:pointer; margin-bottom:15px; transition:all 0.3s;">'; // ★ (2026-11-02 22:17) 글자색 — 다크에서 큰 📁 아이콘이 밝은 바탕 위 밝은 색이라 안 보였음
        html += '<div style="font-size:48px; margin-bottom:10px;">📁</div>';
        html += '<div style="font-size:16px; color:#666;">' + i18n.upload_drag + '</div>';
        html += '<div style="font-size:12px; color:#999; margin-top:5px;">' + i18n.upload_info + '</div>';
        html += '</div>';
        html += '<input type="file" id="uploadFiles" multiple style="display:none;">';
        
        // 파일 목록 테이블
        html += '<div id="fileListContainer" style="display:none; max-height:250px; overflow-y:auto; margin-bottom:15px;">';
        html += '<table id="fileListTable" style="width:100%; border-collapse:collapse; font-size:13px;">';
        html += '<thead><tr style="background:#f5f5f5; color:#212529; border-bottom:2px solid #ddd;">'; // ★ (2026-11-02 21:17) 밝은 바탕 칸은 글자색도 — 다크에서 밝은 바탕 위 밝은 글자(머리·합계·진행·업로드 중 줄)라 안 보였음
        html += '<th style="padding:8px; text-align:left;">' + i18n.upload_filename + '</th>';
        html += '<th style="padding:8px; width:80px; text-align:right;">' + i18n.upload_size + '</th>';
        html += '<th style="padding:8px; width:80px; text-align:center;">' + i18n.upload_status + '</th>';
        html += '<th style="padding:8px; width:30px;"></th>';
        html += '</tr></thead>';
        html += '<tbody id="fileListBody"></tbody>';
        html += '</table>';
        html += '</div>';
        
        // 전체 진행률
        html += '<div id="totalProgressArea" style="display:none; background:#f0f7ff; color:#212529; border-radius:8px; padding:12px; margin-bottom:15px;">';
        html += '<div style="display:flex; justify-content:space-between; margin-bottom:5px;">';
        html += '<span id="uploadStatusText">' + i18n.upload_count_status.replace('%d', '0').replace('%d', '0') + '</span>';
        html += '<span id="uploadPercentText">0%</span>';
        html += '</div>';
        html += '<div class="progress" style="height:8px;"><div id="totalProgressBar" class="progress-bar" style="width:0%; transition:width 0.1s;"></div></div>';
        html += '<div id="currentFileName" style="font-size:12px; color:#007bff; margin-top:8px;"></div>';
        html += '</div>';
        
        html += '<div style="font-size:12px; color:#888;">';
        html += '<strong>' + i18n.upload_current_loc + ':</strong> <?php echo h((string)$getdir ?: '/'); ?><br>';
        html += i18n.upload_supported;
        html += '</div>';
        html += '<div style="font-size:11px; color:#dc3545; margin-top:8px; padding:6px 10px; background:#fff5f5; border-radius:4px; border-left:3px solid #dc3545;">';
        html += '⚠️ ' + i18n.upload_vpn_warning;
        html += '</div>';
        html += '</div>';
        html += '<div class="modal-footer py-2">';
        html += '<button type="button" class="btn btn-outline-danger" id="cancelUploadBtn" onclick="cancelUpload()" style="display:none;">' + i18n.upload_cancel_btn + '</button>';
        html += '<button type="button" class="btn btn-secondary" data-dismiss="modal">' + i18n.close + '</button>';
        html += '<button type="button" class="btn btn-primary" onclick="startUpload()" id="uploadBtn" disabled>' + i18n.upload_start + '</button>';
        html += '</div></div></div></div>';
        document.body.insertAdjacentHTML('beforeend', html);
        
        // 이벤트 바인딩
        var dropZone = document.getElementById('dropZone');
        var fileInput = document.getElementById('uploadFiles');
        
        dropZone.onclick = function() { fileInput.click(); };
        dropZone.ondragover = function(e) { 
            e.preventDefault(); 
            this.style.borderColor = '#007bff'; 
            this.style.background = '#e8f4ff'; 
        };
        dropZone.ondragleave = function() { 
            this.style.borderColor = '#ccc'; 
            this.style.background = '#fafafa'; 
        };
        dropZone.ondrop = function(e) { 
            e.preventDefault(); 
            this.style.borderColor = '#ccc'; 
            this.style.background = '#fafafa';
            addFilesToQueue(e.dataTransfer.files);
        };
        fileInput.onchange = function() { addFilesToQueue(this.files); };
    }
    
    // 초기화
    uploadQueue = [];
    isUploading = false;
    document.getElementById('uploadFiles').value = '';
    document.getElementById('fileListContainer').style.display = 'none';
    document.getElementById('totalProgressArea').style.display = 'none';
    document.getElementById('cancelUploadBtn').style.display = 'none';
    document.getElementById('uploadBtn').disabled = true;
    renderFileList();
    $('#uploadModal').modal({backdrop: 'static', keyboard: true});
}

function addFilesToQueue(files) {
    for (var i = 0; i < files.length; i++) {
        uploadQueue.push({
            file: files[i],
            name: files[i].name,
            size: files[i].size,
            status: 'pending', // pending, uploading, done, error
            progress: 0
        });
    }
    renderFileList();
    document.getElementById('uploadBtn').disabled = uploadQueue.length === 0;
}

function removeFromQueue(idx) {
    if (!isUploading && uploadQueue[idx] && uploadQueue[idx].status === 'pending') {
        uploadQueue.splice(idx, 1);
        renderFileList();
        document.getElementById('uploadBtn').disabled = uploadQueue.length === 0;
    }
}

function renderFileList() {
    var container = document.getElementById('fileListContainer');
    var tbody = document.getElementById('fileListBody');
    
    if (uploadQueue.length === 0) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    var html = '';
    var totalSize = 0;
    
    for (var i = 0; i < uploadQueue.length; i++) {
        var item = uploadQueue[i];
        totalSize += item.size;
        var sizeStr = item.size < 1048576 ? (item.size/1024).toFixed(1)+' KB' : (item.size/1048576).toFixed(1)+' MB';
        
        var statusHtml = '';
        var rowBg = '';
        if (item.status === 'pending') {
            statusHtml = '<span style="color:#888;">' + i18n.upload_waiting + '</span>';
        } else if (item.status === 'uploading') {
            statusHtml = '<span style="color:#007bff;">' + item.progress + '%</span>';
            rowBg = 'background:#fff8e1; color:#212529;';
        } else if (item.status === 'done') {
            statusHtml = '<span style="color:#28a745;">✅' + i18n.upload_done + '</span>';
            rowBg = 'background:#e8f5e9; color:#212529;';
        } else if (item.status === 'error') {
            statusHtml = '<span style="color:#dc3545;">❌' + i18n.upload_failed + '</span>';
            rowBg = 'background:#ffebee; color:#212529;';
        }
        
        var removeBtn = (item.status === 'pending' && !isUploading) ? 
            '<button onclick="removeFromQueue('+i+')" style="border:none; background:none; color:#dc3545; cursor:pointer; font-size:16px;">×</button>' : '';
        
        html += '<tr style="border-bottom:1px solid #eee;'+rowBg+'">';
        html += '<td style="padding:8px; word-break:break-all;">'+escapeHtml(item.name)+'</td>';
        html += '<td style="padding:8px; text-align:right;">'+sizeStr+'</td>';
        html += '<td style="padding:8px; text-align:center;">'+statusHtml+'</td>';
        html += '<td style="padding:8px; text-align:center;">'+removeBtn+'</td>';
        html += '</tr>';
    }
    
    // 합계
    var totalStr = totalSize < 1048576 ? (totalSize/1024).toFixed(1)+' KB' : (totalSize/1048576).toFixed(1)+' MB';
    html += '<tr style="background:#f5f5f5; color:#212529; font-weight:bold;">';
    html += '<td style="padding:8px;">' + i18n.upload_total_count.replace('%d', uploadQueue.length) + '</td>';
    html += '<td style="padding:8px; text-align:right; color:#28a745;">'+totalStr+'</td>';
    html += '<td colspan="2"></td>';
    html += '</tr>';
    
    tbody.innerHTML = html;
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 업로드 중 페이지 이탈 방지
function preventPageLeave(e) {
    if (isUploading) {
        e.preventDefault();
        e.returnValue = i18n.upload_in_progress;
        return e.returnValue;
    }
}

async function startUpload() {
    if (uploadQueue.length === 0 || isUploading) return;
    
    isUploading = true;
    document.getElementById('uploadBtn').disabled = true;
    document.getElementById('cancelUploadBtn').style.display = 'inline-block';
    document.getElementById('totalProgressArea').style.display = 'block';
    
    // 페이지 이탈 방지 활성화
    window.addEventListener('beforeunload', preventPageLeave);
    
    var total = uploadQueue.length;
    var completed = 0, failed = 0;
    
    for (var i = 0; i < uploadQueue.length; i++) {
        if (!isUploading) break; // 취소됨
        
        var item = uploadQueue[i];
        if (item.status !== 'pending') continue;
        
        item.status = 'uploading';
        item.progress = 0;
        renderFileList();
        
        document.getElementById('uploadStatusText').textContent = i18n.upload_count_status.replace('%d', completed + 1).replace('%d', total);
        document.getElementById('currentFileName').textContent = '📁 ' + item.name;
        
        try {
            await uploadSingleFile(item, i, total, completed);
            item.status = 'done';
            item.progress = 100;
            completed++;
        } catch (err) {
            item.status = 'error';
            failed++;
            console.error('업로드 실패:', item.name, err);
        }
        
        renderFileList();
        
        // 전체 진행률 업데이트
        var pct = Math.round((completed + failed) / total * 100);
        document.getElementById('totalProgressBar').style.width = pct + '%';
        document.getElementById('uploadPercentText').textContent = pct + '%';
    }
    
    isUploading = false;
    
    // 페이지 이탈 방지 해제
    window.removeEventListener('beforeunload', preventPageLeave);
    
    document.getElementById('cancelUploadBtn').style.display = 'none';
    document.getElementById('uploadBtn').disabled = false;
    document.getElementById('uploadStatusText').textContent = i18n.upload_complete;
    
    var msg = i18n.upload_complete;
    if (failed > 0) msg += ' (' + failed + ' failed)';
    alert('✅ ' + msg);
    
    if (completed > 0) {
        $('#uploadModal').modal('hide');
        location.reload();
    }
}

// 파일 하나 업로드 (청크 방식 - php.ini 제한 무시)
async function uploadSingleFile(item, idx, total, completedBefore) {
    var chunkSize = 50 * 1024 * 1024; // 50MB 청크
    var file = item.file;
    var totalChunks = Math.ceil(file.size / chunkSize);
    
    var startTime = Date.now();
    var lastLoaded = 0;
    var lastTime = startTime;
    var totalLoaded = 0;
    
    // 1. 초기화
    var initData = new FormData();
    initData.append('admin_action', 'chunk_init');
    initData.append('current_dir', currentDir);
    initData.append('csrf_token', adminCsrfToken);
    initData.append('file_name', file.name);
    initData.append('file_size', file.size);
    
    var initRes = await fetch('index.php?bidx=<?php echo $current_bidx; ?>', { method: 'POST', body: initData });
    var initJson = await initRes.json();
    
    if (!initJson.success) {
        throw new Error(initJson.error || i18n.upload_init_fail);
    }
    
    var uploadId = initJson.upload_id;
    
    // 2. 청크 전송
    for (var c = 0; c < totalChunks; c++) {
        if (!isUploading) throw new Error(i18n.upload_cancelled);
        
        var start = c * chunkSize;
        var end = Math.min(start + chunkSize, file.size);
        var chunk = file.slice(start, end);
        
        // XHR로 청크 전송 (진행률 표시)
        await new Promise(function(resolve, reject) {
            var chunkData = new FormData();
            chunkData.append('admin_action', 'chunk_data');
            chunkData.append('csrf_token', adminCsrfToken);
            chunkData.append('upload_id', uploadId);
            chunkData.append('chunk', chunk);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'index.php?bidx=<?php echo $current_bidx; ?>', true);
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    var chunkLoaded = start + e.loaded;
                    var pct = Math.round(chunkLoaded / file.size * 100);
                    item.progress = pct;
                    renderFileList();
                    
                    // 전체 진행률
                    var overallPct = Math.round((completedBefore + pct/100) / total * 100);
                    document.getElementById('totalProgressBar').style.width = overallPct + '%';
                    document.getElementById('uploadPercentText').textContent = overallPct + '%';
                    
                    // 속도 계산
                    var now = Date.now();
                    var timeDiff = (now - lastTime) / 1000;
                    if (timeDiff >= 0.3) {
                        var bytesDiff = chunkLoaded - lastLoaded;
                        var speed = bytesDiff / timeDiff;
                        var speedStr = formatSpeed(speed);
                        
                        var remaining = file.size - chunkLoaded;
                        var eta = speed > 0 ? remaining / speed : 0;
                        var etaStr = formatTime(eta);
                        
                        document.getElementById('currentFileName').innerHTML = 
                            '📁 ' + escapeHtml(item.name) + '<br>' +
                            '<span style="color:#28a745; font-weight:bold;">' + speedStr + '</span>' +
                            ' · ' + i18n.upload_remaining + ': ' + etaStr +
                            ' · ' + i18n.upload_chunk_info + ' ' + (c+1) + '/' + totalChunks;
                        
                        lastLoaded = chunkLoaded;
                        lastTime = now;
                    }
                }
            };
            
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) resolve();
                    else reject(new Error(res.error || i18n.upload_chunk_fail));
                } catch(e) {
                    reject(new Error(i18n.upload_parse_error));
                }
            };
            
            xhr.onerror = function() { reject(new Error(i18n.upload_network_error)); };
            xhr.send(chunkData);
        });
    }
    
    // 3. 완료
    var finishData = new FormData();
    finishData.append('admin_action', 'chunk_finish');
    finishData.append('csrf_token', adminCsrfToken);
    finishData.append('upload_id', uploadId);
    
    var finishRes = await fetch('index.php?bidx=<?php echo $current_bidx; ?>', { method: 'POST', body: finishData });
    var finishJson = await finishRes.json();
    
    if (!finishJson.success) {
        throw new Error(finishJson.error || i18n.upload_finish_fail);
    }
    
    return finishJson;
}

function formatSpeed(bytesPerSec) {
    if (bytesPerSec >= 1048576) {
        return (bytesPerSec / 1048576).toFixed(1) + ' MB/s';
    } else if (bytesPerSec >= 1024) {
        return (bytesPerSec / 1024).toFixed(0) + ' KB/s';
    } else {
        return bytesPerSec.toFixed(0) + ' B/s';
    }
}

function formatTime(seconds) {
    if (seconds < 1) return i18n.upload_eta_done;
    if (seconds < 60) return Math.ceil(seconds) + i18n.upload_eta_sec;
    if (seconds < 3600) return Math.ceil(seconds / 60) + i18n.upload_eta_min;
    return Math.floor(seconds / 3600) + i18n.upload_eta_hour + ' ' + Math.ceil((seconds % 3600) / 60) + i18n.upload_eta_min;
}

function cancelUpload() {
    if (confirm(i18n.upload_cancel_confirm)) {
        isUploading = false;
    }
}
<?php endif; ?>

// Enter 키로 폴더 생성
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && document.getElementById('newFolderName') === document.activeElement) {
        createFolder();
    }
});

// ===== 삭제 모드 =====
var deleteMode = false;
var selectedItems = { files: [], folders: [] };

function toggleDeleteMode() {
    deleteMode = !deleteMode;
    var btn = document.getElementById('deleteModeBtn');
    var deleteButtons = document.querySelectorAll('.admin-delete-btn');
    var checkboxes = document.querySelectorAll('.admin-delete-checkbox');
    var bulkBar = document.getElementById('bulkDeleteBar');
    
    // 선택 초기화
    selectedItems = { files: [], folders: [] };
    
    if (deleteMode) {
        btn.classList.remove('btn-outline-danger');
        btn.classList.add('btn-danger');
        btn.innerHTML = (window.MC_ICO_TRASH || '🗑️') + ' ON'; // ★ (2026-10-30) 선 아이콘
        
        // 삭제 버튼, 체크박스 표시
        deleteButtons.forEach(function(b) { b.style.display = 'block'; });
        checkboxes.forEach(function(c) { c.style.display = 'block'; c.checked = false; });
        
        // 카드에 삭제 버튼/체크박스 없으면 추가
        addDeleteButtonsToCards();
        
        // 일괄 삭제 바 표시
        showBulkDeleteBar();
    } else {
        btn.classList.remove('btn-danger');
        btn.classList.add('btn-outline-danger');
        btn.innerHTML = window.MC_ICO_TRASH || '🗑️';
        
        // 삭제 버튼, 체크박스 숨기기
        deleteButtons.forEach(function(b) { b.style.display = 'none'; });
        checkboxes.forEach(function(c) { c.style.display = 'none'; c.checked = false; });
        
        // 일괄 삭제 바 숨기기
        if (bulkBar) bulkBar.style.display = 'none';
    }
}

function showBulkDeleteBar() {
    var bar = document.getElementById('bulkDeleteBar');
    if (!bar) {
        var html = '<div id="bulkDeleteBar" style="position:fixed; bottom:0; left:0; right:0; background:#343a40; padding:10px 20px; z-index:1050; display:flex; justify-content:space-between; align-items:center; box-shadow:0 -2px 10px rgba(0,0,0,0.3);">';
        html += '<div style="color:#fff;">';
        html += '<button type="button" class="btn btn-sm btn-outline-light mr-2" onclick="selectAllItems()">' + i18n.select_all + '</button>';
        html += '<button type="button" class="btn btn-sm btn-outline-light mr-3" onclick="deselectAllItems()">' + i18n.deselect_all + '</button>';
        html += '<span id="selectedCount" style="font-size:0.95em;">' + i18n.selected_count.replace('%d', '0') + '</span>';
        html += '</div>';
        html += '<button type="button" class="btn btn-danger" onclick="deleteSelectedItems()" id="bulkDeleteBtn" disabled>🗑️ ' + i18n.bulk_delete + '</button>';
        html += '</div>';
        document.body.insertAdjacentHTML('beforeend', html);
    } else {
        bar.style.display = 'flex';
    }
    updateSelectedCount();
}

function addDeleteButtonsToCards() {
    // 폴더 카드에 삭제 버튼/체크박스 추가
    document.querySelectorAll('.card[data-folder-path]').forEach(function(card) {
        var folderPath = card.getAttribute('data-folder-path');
        
        // 체크박스 추가
        if (!card.querySelector('.admin-delete-checkbox')) {
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'admin-delete-checkbox';
            checkbox.style.cssText = 'position:absolute; top:5px; left:5px; z-index:100; width:20px; height:20px; cursor:pointer;';
            checkbox.setAttribute('data-type', 'folder');
            checkbox.setAttribute('data-path', folderPath);
            checkbox.onclick = function(e) { e.stopPropagation(); updateSelection(this); };
            card.style.position = 'relative';
            card.appendChild(checkbox);
        }
        
        // 삭제 버튼 추가
        if (!card.querySelector('.admin-delete-btn')) {
            var btn = document.createElement('button');
            btn.className = 'admin-delete-btn';
            btn.style.cssText = 'position:absolute; top:5px; right:5px; z-index:100; padding:2px 8px; font-size:0.8em; background:#dc3545; border:1px solid #dc3545; color:#fff !important; border-radius:4px; cursor:pointer;';
            btn.innerHTML = '✕ ' + i18n.delete_btn;
            btn.onmouseover = function() { this.style.background='#c82333'; };
            btn.onmouseout = function() { this.style.background='#dc3545'; };
            btn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                deleteFolder(folderPath);
            };
            card.appendChild(btn);
        }
    });
    
    // 파일 카드에 삭제 버튼/체크박스 추가
    document.querySelectorAll('.card[data-file-path]').forEach(function(card) {
        var filePath = card.getAttribute('data-file-path');
        
        // 체크박스 추가
        if (!card.querySelector('.admin-delete-checkbox')) {
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'admin-delete-checkbox';
            checkbox.style.cssText = 'position:absolute; top:5px; left:5px; z-index:100; width:20px; height:20px; cursor:pointer;';
            checkbox.setAttribute('data-type', 'file');
            checkbox.setAttribute('data-path', filePath);
            checkbox.onclick = function(e) { e.stopPropagation(); updateSelection(this); };
            card.style.position = 'relative';
            card.appendChild(checkbox);
        }
        
        // 삭제 버튼 추가
        if (!card.querySelector('.admin-delete-btn')) {
            var btn = document.createElement('button');
            btn.className = 'admin-delete-btn';
            btn.style.cssText = 'position:absolute; top:5px; right:5px; z-index:100; padding:2px 8px; font-size:0.8em; background:#dc3545; border:1px solid #dc3545; color:#fff !important; border-radius:4px; cursor:pointer;';
            btn.innerHTML = '✕ ' + i18n.delete_btn;
            btn.onmouseover = function() { this.style.background='#c82333'; };
            btn.onmouseout = function() { this.style.background='#dc3545'; };
            btn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                deleteFile(filePath);
            };
            card.appendChild(btn);
        }
    });
}

function updateSelection(checkbox) {
    var type = checkbox.getAttribute('data-type');
    var path = checkbox.getAttribute('data-path');
    
    if (checkbox.checked) {
        if (type === 'file' && selectedItems.files.indexOf(path) === -1) {
            selectedItems.files.push(path);
        } else if (type === 'folder' && selectedItems.folders.indexOf(path) === -1) {
            selectedItems.folders.push(path);
        }
    } else {
        if (type === 'file') {
            selectedItems.files = selectedItems.files.filter(function(p) { return p !== path; });
        } else {
            selectedItems.folders = selectedItems.folders.filter(function(p) { return p !== path; });
        }
    }
    updateSelectedCount();
}

function updateSelectedCount() {
    var total = selectedItems.files.length + selectedItems.folders.length;
    var countEl = document.getElementById('selectedCount');
    var btn = document.getElementById('bulkDeleteBtn');
    
    if (countEl) {
        var text = i18n.selected_count.replace('%d', total);
        if (selectedItems.files.length > 0) text += ' (' + i18n.delete_file_label + ' ' + selectedItems.files.length + ')';
        if (selectedItems.folders.length > 0) text += ' (' + i18n.delete_folder_label + ' ' + selectedItems.folders.length + ')';
        countEl.textContent = text;
    }
    if (btn) {
        btn.disabled = total === 0;
        btn.textContent = total > 0 ? '🗑️ ' + total + ' ' + i18n.delete_btn : '🗑️ ' + i18n.bulk_delete;
    }
}

function selectAllItems() {
    document.querySelectorAll('.admin-delete-checkbox').forEach(function(checkbox) {
        checkbox.checked = true;
        updateSelection(checkbox);
    });
}

function deselectAllItems() {
    document.querySelectorAll('.admin-delete-checkbox').forEach(function(checkbox) {
        checkbox.checked = false;
    });
    selectedItems = { files: [], folders: [] };
    updateSelectedCount();
}

function deleteSelectedItems() {
    var total = selectedItems.files.length + selectedItems.folders.length;
    if (total === 0) {
        alert(i18n.select_items_delete);
        return;
    }
    
    var msg = i18n.confirm_delete_items.replace('%d', total) + '\n';
    if (selectedItems.files.length > 0) msg += '- ' + i18n.delete_file_label + ' ' + selectedItems.files.length + '\n';
    if (selectedItems.folders.length > 0) msg += '- ' + i18n.delete_folder_label + ' ' + selectedItems.folders.length + '\n';
    msg += '\n' + i18n.confirm_irreversible;
    
    if (!confirm(msg)) return;
    
    // 2번째 확인 (폴더 또는 파일)
    if (selectedItems.folders.length > 0) {
        if (!confirm(i18n.confirm_folder_final)) {
            return;
        }
    } else {
        if (!confirm(i18n.confirm_file_final.replace('%d', selectedItems.files.length))) {
            return;
        }
    }
    
    var formData = new FormData();
    formData.append('admin_action', 'delete_multiple');
    formData.append('files', JSON.stringify(selectedItems.files));
    formData.append('folders', JSON.stringify(selectedItems.folders));
    formData.append('csrf_token', adminCsrfToken);
    
    // 버튼 비활성화
    var btn = document.getElementById('bulkDeleteBtn');
    btn.disabled = true;
    btn.textContent = i18n.deleting;
    
    fetch('index.php?bidx=<?php echo $current_bidx; ?>', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + (data.error || i18n.twofa_delete_fail));
            btn.disabled = false;
            updateSelectedCount();
        }
    })
    .catch(function(e) {
        alert('❌ ' + i18n.error_occurred + ': ' + e.message);
        btn.disabled = false;
        updateSelectedCount();
    });
}

// ★ (2026-11-02 14:35) 확인 문구 치환을 함수로 — 이름에 '$&' 같은 글자가 있으면 문구가 바뀌던 것(즐겨찾기·북마크와 같게)
function deleteFile(filePath) {
    var fileName = filePath.split('/').pop();
    if (!confirm(i18n.confirm_file_delete.replace('%s', function(){ return fileName; }))) {
        return;
    }
    
    // 이중 확인
    if (!confirm(i18n.confirm_file_delete2.replace('%s', function(){ return fileName; }))) {
        return;
    }
    
    var formData = new FormData();
    formData.append('admin_action', 'delete_file');
    formData.append('file_path', filePath);
    formData.append('csrf_token', adminCsrfToken);
    
    fetch('index.php?bidx=<?php echo $current_bidx; ?>', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + (data.error || i18n.twofa_delete_fail));
        }
    })
    .catch(function(e) {
        alert('❌ ' + i18n.error_occurred + ': ' + e.message);
    });
}

function deleteFolder(folderPath) {
    var folderName = folderPath.split('/').pop();
    if (!confirm(i18n.confirm_folder_delete.replace('%s', function(){ return folderName; }))) {
        return;
    }
    
    // 이중 확인
    if (!confirm(i18n.confirm_folder_delete2.replace('%s', function(){ return folderName; }))) {
        return;
    }
    
    var formData = new FormData();
    formData.append('admin_action', 'delete_folder');
    formData.append('folder_path', folderPath);
    formData.append('csrf_token', adminCsrfToken);
    
    fetch('index.php?bidx=<?php echo $current_bidx; ?>', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + (data.error || i18n.twofa_delete_fail));
        }
    })
    .catch(function(e) {
        alert('❌ ' + i18n.error_occurred + ': ' + e.message);
    });
}
<?php endif; ?>
</script>

<script>
/* ✅ 목록 스크롤 위치 저장/복원: 사생활 보호 복귀 등으로 목록이 새로 로드될 때 읽던 위치로 되돌림.
   - 백그라운드 전환(hidden)/이탈(pagehide) 시 현재 스크롤을 URL별 키로 sessionStorage에 저장
   - 새 로드 시 저장값이 있으면 복원(썸네일 로드로 높이가 변하는 동안 잠시 재적용, 사용자가 스크롤하면 중단)
   - bfcache 복원은 브라우저가 스크롤을 유지하므로 영향 없음(DOMContentLoaded에서만 동작) */
(function(){
  var KEY = 'mycomix_list_scroll:' + location.pathname + location.search;
  // ✅ localStorage 단기 백업 키 — sessionStorage는 iOS 탭 폐기 시 비워지므로, 장시간 후
  //    뷰어→뒤로가기→목록 복귀에도 위치를 살리기 위한 보조 저장. 만료 없음(무제한) — 장시간 후에도 마지막 목록 위치를 복원.
  var LKEY = 'mycomix_list_scroll_p:' + location.pathname + location.search;
  var _userScrolled = false;
  var _anchorStop = null; // 진행 중인 앵커 정착 세션 종료 함수(중복 방지) // 사용자가 실제 스크롤했는지(콘텐츠 밀림과 구분) — 앵커 갱신 판단용
  try { ['wheel','touchmove','keydown','pointerdown','touchstart'].forEach(function(evn){ window.addEventListener(evn, function(){ _userScrolled = true; }, { passive:true, capture:true }); }); } catch(e){}
  function curY(){ return window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0; }
  // ★ (2026-11-02 23:08) 복원 중에 스크롤이 '우리가 맞춘 자리'에서 크게 벗어나면 사용자가 움직인 것으로 봄 — 아이폰 상태 표시줄 탭(맨 위로)·▲ 단추는
  //   터치 이동·휠 이벤트가 없어 못 알아채고, 복원이 최대 10초 동안 제자리로 계속 끌어당겨 화면이 깜빡이고 맨 위로 못 갔음
  var _mcSetY = -1;
  window.addEventListener('scroll', function(){ if (_mcSetY >= 0 && Math.abs(curY() - _mcSetY) > 8) { _userScrolled = true; _mcSetY = -1; } }, { passive:true });
  // ✅ 항목 앵커: 목록 내용(이어보기 등)이 바뀌어 픽셀이 밀려도 '같은 항목'으로 정확히 복원하기 위한 헬퍼.
  function _cardText(c){ try { return (c.textContent||'').replace(/\s+/g,' ').trim().slice(0,40); } catch(e){ return ''; } }
  function topAnchor(){
    try {
      var cards = document.querySelectorAll('.card');
      var best=null, bestTop=-1e9, bi=-1;
      for (var i=0;i<cards.length;i++){ var r=cards[i].getBoundingClientRect(); if (r.height<=0) continue; if (r.top<=5 && r.top>bestTop){ bestTop=r.top; best=cards[i]; bi=i; } }
      if (best) return { txt:_cardText(best), n:bi, off:Math.round(-bestTop) }; // 실제 오프셋: 첫 목록의 스크롤 위치(반쯤 걸친 것 등) 그대로 보존. 썸네일 높이 예약으로 레이아웃 안정 → 드리프트 없음.
    } catch(e){}
    return null;
  }
  function findAnchorEl(a){
    try {
      if (!a || !a.txt) return null;
      var cards = document.querySelectorAll('.card');
      if (a.n>=0 && a.n<cards.length && _cardText(cards[a.n])===a.txt) return cards[a.n];
      var best=null, bestD=1e9;
      for (var i=0;i<cards.length;i++){ if (_cardText(cards[i])===a.txt){ var d=Math.abs(i-(a.n||0)); if (d<bestD){ bestD=d; best=cards[i]; } } }
      return best;
    } catch(e){}
    return null;
  }
  function scrollToAnchor(a){
    // 항목을 상단에 재정착: 콘텐츠(이미지) 로드로 레이아웃이 바뀔 때마다 다시 맞춘다(느린 로드로 정착이
    // 뒤늦게 깨지는 것 방지). 사용자가 스크롤하면 즉시 중단(사용자 의도 우선). 정착(1.5초 안정) 또는 최대 10초 후 자동 종료.
    try {
      if (!findAnchorEl(a)) return false;
      try { if (_anchorStop) _anchorStop(); } catch(e){} // 이전 정착 세션 종료(중복/누수 방지)
      _userScrolled = false;
      var _off = (a && a.off) || 0;
      var _t0 = Date.now(), _lastMove = Date.now(), _done = false, _iv = null, _ro = null;
      function _finish(){ if (_done) return; _done = true; _mcSetY = -1; try { clearInterval(_iv); } catch(e){} try { if (_ro) _ro.disconnect(); } catch(e){} if (_anchorStop === _finish) _anchorStop = null; }
      _anchorStop = _finish;
      _mcSetY = curY();
      function _place(){
        if (_done) return;
        if (_userScrolled || (Date.now() - _t0) > 10000) { _finish(); return; } // 사용자 스크롤/최대10초 시 종료
        var el = findAnchorEl(a); if (!el) { _finish(); return; }
        var rect = el.getBoundingClientRect();
        var cur = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
        var h = rect.height || 0;
        var useOff = Math.max(0, Math.min(_off, h > 40 ? (h - 40) : 0));
        var target = Math.max(0, Math.round(rect.top + cur + useOff));
        if (Math.abs(cur - target) > 2) { window.scrollTo(0, target); _mcSetY = curY(); _lastMove = Date.now(); } // 실제 이동 (_mcSetY = 실제로 간 자리 — 아직 덜 그려져 끝에 걸린 경우도)
        else if ((Date.now() - _lastMove) > 1500) { _finish(); } // 1.5초간 조정 불필요 → 정착 완료 종료(늦은 움직임 방지)
      }
      _place();
      _iv = setInterval(_place, 100); // 주기적 재정착(느린 로드 대비, 사용자 스크롤/정착/10초 시 종료)
      try { if (window.ResizeObserver) { _ro = new ResizeObserver(_place); _ro.observe(document.body); } } catch(e){} // 레이아웃 변화(이미지 로드)마다 재정착
      return true;
    } catch(e){}
    return false;
  }
  function saveScroll(){
    try {
      var y = curY();
      sessionStorage.setItem(KEY, String(y));
      try {
        var _sa = null;
        if (!_userScrolled) { try { var _pv = JSON.parse(localStorage.getItem(LKEY) || 'null'); _sa = _pv && _pv.a; } catch(e3){} } // 사용자 스크롤 없었으면(콘텐츠 밀림뿐) 기존 앵커 유지
        if (!_sa) _sa = topAnchor();
        localStorage.setItem(LKEY, JSON.stringify({ y: y, t: Date.now(), a: _sa }));
      } catch(e2){}
    } catch(e){}
  }
  document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'hidden') saveScroll(); });
  window.addEventListener('pagehide', saveScroll);

  // ✅ 목록 위치를 복귀 URL에도 실어, iOS 탭 폐기/장시간/망(5G↔LTE) 전환으로 sessionStorage가
  //    비워져도 복원되게 한다. privacy_shield가 백그라운드 전환 시 이 함수를 호출해 반환값을
  //    복귀 URL에 포함한다. 목록은 #ly<픽셀> 형식(뷰어의 #imageN과 구분 — 서로 다른 페이지라 충돌 없음).
  window.myComixCurrentHash = function(){
    try { var y = curY(); return y > 0 ? ('#ly' + Math.round(y)) : ''; } catch(e){ return ''; }
  };

  // 목표 위치로 복원(썸네일 로드로 높이가 변하는 동안 잠시 재적용, 사용자가 스크롤하면 중단)
  function scrollToY(y){
    if (!(y > 0)) return;
    var tries = 0, stop = false;
    function onUser(){ stop = true; }
    window.addEventListener('wheel', onUser, { passive:true });
    window.addEventListener('touchmove', onUser, { passive:true });
    window.addEventListener('keydown', onUser, true);
    window.addEventListener('pointerdown', onUser, { passive:true }); // ★ (2026-11-02 23:08) ▲ 단추 등
    window.addEventListener('touchstart', onUser, { passive:true });
    var _setY = -1;
    function onScr(){ if (_setY >= 0 && Math.abs(curY() - _setY) > 8) stop = true; } // ★ (2026-11-02 23:08) 맞춘 자리에서 크게 벗어나면(상태 표시줄 탭 등) 멈춤
    window.addEventListener('scroll', onScr, { passive:true });
    function done(){ clearInterval(iv); window.removeEventListener('scroll', onScr); // ★ (2026-11-04 00:28) 사용자 동작 감지 리스너도 떼기(뒤로가기 복귀마다 5개씩 쌓였음)
      window.removeEventListener('wheel', onUser); window.removeEventListener('touchmove', onUser); window.removeEventListener('keydown', onUser, true);
      window.removeEventListener('pointerdown', onUser); window.removeEventListener('touchstart', onUser); }
    var iv = setInterval(function(){
      if (stop) { done(); return; }
      window.scrollTo(0, y); _setY = curY(); // 실제로 간 자리(덜 그려져 끝에 걸린 경우도) 기준
      if (++tries >= 50 || Math.abs(curY() - y) < 4) { done(); }
    }, 60);
  }

  // ✅ 진입 방식 판별: 새 진입(navigate)이면 false, 뒤로/앞으로가기·새로고침(복귀)이면 true.
  //    폴더목록→파일목록처럼 "새로 들어온" 목록은 맨 위가 정상이고, 뒤로가기 복귀일 때만 저장 위치를 복원한다.
  function isReturnNav(){
    try {
      var e = (performance.getEntriesByType && performance.getEntriesByType('navigation')) || [];
      if (e.length && e[0] && e[0].type) return (e[0].type === 'back_forward' || e[0].type === 'reload');
      if (performance.navigation) { var t = performance.navigation.type; return (t === 1 || t === 2); } // 1=reload,2=back_forward
    } catch(_e){}
    return false;
  }
  function restoreScroll(force){
    try {
      var ly = (location.hash || '').match(/^#ly(\d+)$/);
      // ✅ 새 진입(navigate)에서는 저장 위치를 복원하지 않는다(맨 위). 뒤로가기/새로고침(복귀) 또는
      //    bfcache 복원(pageshow persisted=force)일 때만 복원. 단 URL #ly(사생활 복귀로 위치가 명시된
      //    경우)는 진입 방식과 무관하게 복원한다.
      // ✅ 근본해결: 목록→뷰 진입 시 기록한 플래그(mycomix_from_viewer)가 이 목록과 일치하면, 진입 방식과
      //    무관하게 복원(앱 완전종료·장시간 후 뒤로가기=full reload=navigate 케이스 대응). 복원 후 플래그 제거.
      var fromViewer = false;
      try {
        var _thisUrl = location.pathname + location.search;
        if (localStorage.getItem('mycomix_from_viewer') === _thisUrl) { fromViewer = true; localStorage.removeItem('mycomix_from_viewer'); }
      } catch(_e){}
      var allow = !!force || isReturnNav() || fromViewer;
      // #search-section 등 #ly가 아닌 해시가 있으면 스크롤 복원을 건드리지 않음(기존 동작)
      // ★ fix55 단 #search-section(검색 결과 — 찾은 폴더 목록·이전/다음·예전 검색 링크)은 돌아올 때(뒤로 가기·새로고침·뷰어에서 복귀) 저장 위치로 복원
      //   — 전엔 복원을 건너뛰고 검색칸 쪽으로만 가서, 검색 결과에서 책을 보고 돌아오면 보던 자리를 잃었음(원본도 같음). 처음 들어올 때는 예전처럼 검색칸으로
      if (location.hash && !ly) {
        if (!(location.hash === '#search-section' && allow)) return;
        window.__mcListRestore = true; // scrollToSearchSection 이 뒤늦게 검색칸으로 끌어올리지 않게
      }
      // ✅ 항목 앵커 우선: 복귀(allow/fromViewer)일 때, 저장된 '그 항목'을 찾아 복원한다. 목록 내용(이어보기
      //    갱신 등)이 바뀌어 픽셀이 밀려도 같은 항목으로 정확히 감. 앵커 못 찾으면 아래 픽셀 방식으로 폴백.
      // #ly(사생활 복귀)일 때도 앵커 우선 — 목록에서 바로 사생활→닫기→복귀 시 내용 밀림 대응. 못 찾으면 #ly 픽셀 폴백.
      if (allow || fromViewer || ly) {
        try { var _la = localStorage.getItem(LKEY); if (_la) { var _oa = JSON.parse(_la); if (_oa && _oa.a && scrollToAnchor(_oa.a)) return; } } catch(_ea){}
      }
      // sessionStorage가 가장 신선한 위치(직전 pagehide 저장) — 복귀일 때만 사용.
      if (allow) {
        var v = sessionStorage.getItem(KEY);
        var vy = (v !== null) ? (parseInt(v, 10) || 0) : -1;
        if (vy > 0) { scrollToY(vy); return; }
      }
      // URL #ly 폴백(사생활 복귀로 위치 명시) — 진입 방식과 무관하게 복원.
      if (ly) { scrollToY(parseInt(ly[1], 10) || 0); return; }
      // 그래도 없으면 localStorage 백업(만료 없음) — 복귀일 때만 사용.
      if (allow) {
        try {
          var lv = localStorage.getItem(LKEY);
          if (lv) {
            var o = JSON.parse(lv);
            if (o) scrollToY(parseInt(o.y, 10) || 0);
          }
        } catch(e2){}
      }
    } catch(e){}
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ restoreScroll(false); });
  else restoreScroll(false);
  // ✅ 뒤로가기(bfcache 복원)는 DOMContentLoaded가 뜨지 않고 pageshow(persisted)만 뜬다. 게다가
  //    콜드 리오픈용 scrollRestoration='manual' 때문에 브라우저도 스크롤을 복원하지 않아 맨 위로 간다.
  //    따라서 여기서 저장해 둔 목록 위치(#ly/sessionStorage/localStorage)로 직접 복원한다
  //    → 뷰어에서 뒤로가기 시 목록 스크롤 위치 유지. (persisted=false 일반 로드는 위에서 이미 복원함)
  window.addEventListener('pageshow', function(e){ if (e && e.persisted) restoreScroll(true); });
  // ✅ 근본해결: 목록에서 뷰(viewer/epub/txt)로 이동하는 클릭을 잡아 "이 목록에서 뷰로 들어감"을 기록.
  //    localStorage라 앱 종료·장시간 후에도 유지되며, 위치는 saveScroll이 함께 저장 → 그 목록 복귀 시 복원.
  // ✅ [본 항목 표시] 목록 URL별로 '방금 본 항목'을 따로 보관.
  //    단일 키를 쓰면 폴더 표시가 그 안에서 연 파일 표시에 덮여 사라짐(폴더→파일→뷰→뒤로→뒤로 시 폴더 강조 실패).
  var MC_VIEWED_MAP = 'mcViewedMap';
  function mcListId(){ try { return location.pathname + location.search; } catch(e){ return ''; } }
  function mcViewedRead(){ try { var v = JSON.parse(localStorage.getItem(MC_VIEWED_MAP) || '{}'); return (v && typeof v === 'object') ? v : {}; } catch(e){ return {}; } }
  function mcViewedWrite(m){
    try {
      var ks = Object.keys(m);
      if (ks.length > 12) { // 오래된 항목부터 정리(무한 증가 방지)
        ks.sort(function(a,b){ return (m[a].t||0) - (m[b].t||0); });
        for (var i = 0; i < ks.length - 12; i++) delete m[ks[i]];
      }
      localStorage.setItem(MC_VIEWED_MAP, JSON.stringify(m));
    } catch(e){}
  }
  function mcViewedSet(href){ try { var m = mcViewedRead(); m[mcListId()] = { h: href, t: Date.now() }; mcViewedWrite(m); } catch(e){} }
  function mcViewedGet(){ try { var e = mcViewedRead()[mcListId()]; return e ? e.h : null; } catch(e){ return null; } }
  function mcViewedClear(){ try { var m = mcViewedRead(); delete m[mcListId()]; mcViewedWrite(m); } catch(e){} }
  document.addEventListener('click', function(ev){
    try {
      var n = ev.target;
      // ★ (2026-11-02 19:04) 카드 안 즐겨찾기 별·삭제 모드 단추는 뷰어로 가지 않음 — 전에는 '방금 본 항목' 표시·복귀 위치가 남았음
      if (n && n.closest && n.closest('.fav-btn,.admin-delete-btn,.admin-delete-checkbox')) return;
      for (var _i = 0; n && n !== document && _i < 8; _i++) {
        var _href = (n.tagName === 'A' && n.getAttribute && n.getAttribute('href')) || '';
        var _oc = (n.getAttribute && n.getAttribute('onclick')) || '';
        if (/(viewer|epub_viewer|txt_viewer)\.php/.test(_href) || /(viewer|epub_viewer|txt_viewer)\.php/.test(_oc)) {
          try { localStorage.setItem('mycomix_from_viewer', location.pathname + location.search); } catch(_e1){}
          try { saveScroll(); } catch(_e2){}
          // ✅ [본 항목 표시] 뷰로 들어간 카드를 기억(복귀 시 강조). 카드 밖(즐겨찾기 등) 클릭은 해당 없음.
          try {
            // 카드 찾기: 이 목록은 <a> 안에 .card가 있는 구조라 안쪽(querySelector) 우선, 혹시 반대 구조면 위쪽(closest) 보조
            var _card = null;
            try { _card = (n.querySelector ? n.querySelector('.card') : null) || (n.closest ? n.closest('.card') : null); } catch(_e4){}
            if (_card) {
              var _old = document.querySelectorAll('.card.mc-viewed');
              for (var _k = 0; _k < _old.length; _k++) _old[_k].classList.remove('mc-viewed');
              _card.classList.add('mc-viewed');
              mcViewedSet(_href || _oc);
            }
          } catch(_e3){}
          break;
        }
        // ✅ [본 항목 표시] 폴더 진입도 카드 기억 (from_viewer 플래그는 뷰 전용이므로 설정하지 않음)
        if (_href && /index\.php\?/.test(_href) && /[?&]dir=/.test(_href)) {
          try {
            var _card2 = (n.querySelector ? n.querySelector('.card') : null) || (n.closest ? n.closest('.card') : null);
            if (_card2) {
              var _old2 = document.querySelectorAll('.card.mc-viewed');
              for (var _k2 = 0; _k2 < _old2.length; _k2++) _old2[_k2].classList.remove('mc-viewed');
              _card2.classList.add('mc-viewed');
              mcViewedSet(_href);
            }
          } catch(_e5){}
          break;
        }
        n = n.parentNode;
      }
    } catch(e){}
  }, true);
  // ✅ [본 항목 표시] 콜드 로드 복원: 저장된 뷰어 링크와 일치하는 카드를 강조 (bfcache 복귀는 클래스가 보존되어 불필요)
  try {
    var _vk = mcViewedGet();
    if (_vk && !document.querySelector('.card.mc-viewed')) {
      // <a href>가 .card의 부모인 구조: 링크를 순회하며 그 링크의 안쪽(자식) 카드 또는 바깥(부모) 카드를 매칭
      var _links = document.querySelectorAll('a[href]');
      for (var _li = 0; _li < _links.length; _li++) {
        var _h = _links[_li].getAttribute('href') || '';
        if (_h === _vk) {
          var _c = _links[_li].querySelector('.card') || (_links[_li].closest ? _links[_li].closest('.card') : null);
          if (_c) { _c.classList.add('mc-viewed'); break; }
        }
      }
    }
  } catch(e){}
  // ✅ [본 항목 표시] 2초 유지 후 자동 해제 + 키 소비. pageshow는 콜드/bfcache 복귀 모두 발화하므로 한 곳에서 처리.
  window.addEventListener('pageshow', function(){
    try {
      var _mv = Array.prototype.slice.call(document.querySelectorAll('.card.mc-viewed'));
      if (_mv.length) {
        try { mcViewedClear(); } catch(_e){} // 현재 목록의 표시만 소비(다른 목록의 표시는 유지)
        var _holdMs = <?php echo (int)round(max(0, (float)get_app_settings('viewed_mark_seconds', 2)) * 1000); ?>; // 관리자 설정: 복귀 표시 유지 시간 (0=자동 해제 안 함)
        if (_holdMs > 0) setTimeout(function(){ for (var _m = 0; _m < _mv.length; _m++) { try { _mv[_m].classList.remove('mc-viewed'); } catch(_e){} } }, _holdMs);
      }
    } catch(_e){}
  });
})();
</script>

<script>
/* ✅ 가림막(blank.php) 복귀 직후 목록 오터치 방지:
 *    가림막을 탭해 복귀하면 그 탭의 고스트클릭 또는 빠르게 누른 2번째 탭이 갓 로드된 목록 항목을
 *    눌러 엉뚱한 항목으로 진입하던 문제. blank.php가 복귀 직전 남긴 플래그가 최근이면
 *    짧은 시간(450ms) 동안 첫 click들을 흡수(취소)한다. click만 가로채므로 스크롤/touch 로직엔 영향 없음. */
(function(){
  try {
    var v = sessionStorage.getItem('mycomix_curtain_return');
    if (!v) return;
    sessionStorage.removeItem('mycomix_curtain_return');
    var age = Date.now() - parseInt(v, 10);
    if (isNaN(age) || age > 1500) return;                 // 오래된 플래그면 정상 로드로 간주(흡수 안 함)
    var until = Date.now() + 450;
    function swallow(e){
      if (Date.now() > until) { remove(); return; }
      e.preventDefault(); e.stopPropagation();
    }
    function remove(){ document.removeEventListener('click', swallow, true); }
    document.addEventListener('click', swallow, true);
    setTimeout(remove, 480);
  } catch(e){}
})();
</script>

<!-- ✅ [즐겨찾기 미리보기] PC=hover, 모바일=탭. 오버레이(fixed)라 목록 레이아웃을 밀지 않음(스크롤 복원 무영향). -->
<style>
/* ✅ [게시판식 행 하이라이트] PC: hover한 행 표시(즐겨찾기/북마크/자동저장 공통) */
@media (hover: hover) and (pointer: fine){
  tr[data-fav-thumb]:hover>td{background:rgba(243,156,18,.13)}
}
/* 모바일: 미리보기가 열린(선택된) 행 표시 */
tr.mcfp-sel>td{background:rgba(243,156,18,.18)}
/* ✅ [게시판식] 메인 목록 카드: PC hover 시 강조 */
@media (hover: hover) and (pointer: fine){
  .card:hover{outline:2px solid rgba(243,156,18,.45);outline-offset:-1px}
}
/* 뷰로 들어갔다 돌아온 '방금 본' 카드 표시 */
.card.mc-viewed{outline:2px solid #f39c12;outline-offset:-1px}
/* ✅ [A] 파일이 없는(고아) 항목 표시 — 흐리게 + 취소선, 뒤에 (파일 없음) 라벨. 데이터는 그대로 두고 표시만 구분. */
tr.mc-missing{opacity:.5}
tr.mc-missing td button{text-decoration:line-through}
tr.mc-missing td:last-child::after{content:" (파일 없음)";font-size:11px;color:#c0392b;white-space:nowrap}
#mcFavPreview{position:fixed;z-index:2000;display:none;background:#fff;border:1px solid #f39c12;border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.28);padding:6px;max-width:220px}
#mcFavPreview img{display:block;width:200px;height:266px;object-fit:contain;background:#f4f4f4;border-radius:4px}
#mcFavPreview .mcfp-t{font-size:12px;line-height:1.3;margin-top:4px;max-width:200px;word-break:break-all;color:#333}
#mcFavPreview .mcfp-open{display:block;width:100%;margin-top:5px;padding:6px 0;font-size:13px;border:0;border-radius:4px;background:#f39c12;color:#fff;cursor:pointer}
#mcFavPreview.mcfp-mobile{max-width:240px}
@media (prefers-color-scheme: dark){#mcFavPreview{background:#2b2b2b;border-color:#f39c12}#mcFavPreview .mcfp-t{color:#eee}}
</style>
<div id="mcFavPreview" role="dialog" aria-label="즐겨찾기 미리보기"></div>
<script>
(function(){
  try {
    var box = document.getElementById('mcFavPreview');
    if (!box) return;
    var canHover = false;
    try { canHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches; } catch(e){}
    var showTimer = null, curRow = null;

    function hide(){
      try {
        if (showTimer) { clearTimeout(showTimer); showTimer = null; }
        box.style.display = 'none';
        box.innerHTML = ''; // 이미지 해제(메모리/요청 정리)
        curRow = null; // 선택(mcfp-sel)은 유지: 다른 행 선택/이동 시 clearSel()이 정리
      } catch(e){}
    }
    function build(row){
      var thumb = row.getAttribute('data-fav-thumb') || '';
      var title = row.getAttribute('data-fav-title') || '';
      var url   = row.getAttribute('data-fav-url') || '';
      box.innerHTML = '';
      var img = document.createElement('img');
      img.alt = 'thumbnail'; img.loading = 'lazy';
      img.onerror = function(){ try { img.style.display='none'; var p=document.createElement('div'); p.className='mcfp-t'; p.textContent='(미리보기 없음)'; box.insertBefore(p, box.firstChild); } catch(e){} };
      img.src = thumb; // 열 때만 로드(온디맨드)
      box.appendChild(img);
      var t = document.createElement('div'); t.className = 'mcfp-t'; t.textContent = title; box.appendChild(t);
      if (!canHover) { // 모바일: 명시적 열기 버튼(썸네일 탭도 동일 동작)
        var b = document.createElement('button'); b.className = 'mcfp-open'; b.type='button'; b.textContent = '열기';
        b.addEventListener('click', function(ev){ ev.stopPropagation(); go(url, row); });
        box.appendChild(b);
        img.style.cursor = 'pointer';
        img.addEventListener('click', function(ev){ ev.stopPropagation(); go(url, row); });
      }
      return url;
    }
    function go(url, row){
      if (!url) return;
      try { if (window.myComixMarkNavigation) window.myComixMarkNavigation(); } catch(e){}
      hide();
      if (row) { markSel(row); saveReturn(row); } // 복귀 시 이 행을 선택 상태로
      location.href = './' + url;
    }
    function place(row){
      try {
        var r = row.getBoundingClientRect();
        box.style.display = 'block';
        var bw = box.offsetWidth || 220, bh = box.offsetHeight || 300;
        var left;
        if (canHover) {
          // 행 오른쪽에 공간이 있으면 오른쪽, 없으면 행 왼쪽에 배치(화면 끝에 붙어 잘리는 것 방지)
          left = r.right + 12;
          if (left + bw > window.innerWidth - 8) left = r.left - bw - 12;
          if (left < 8) left = Math.max(8, Math.min(r.left, window.innerWidth - bw - 8));
        } else {
          left = 15; // 모바일: 화면 왼쪽 15px 고정
        }
        if (left + bw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - bw - 8);
        var top = canHover ? r.top : (r.bottom + 8);
        if (top + bh > window.innerHeight - 8) top = Math.max(8, window.innerHeight - bh - 8);
        box.style.left = Math.round(left) + 'px';
        box.style.top  = Math.round(top) + 'px';
      } catch(e){}
    }
    function open(row){
      if (!row) return;
      if (!canHover) { markSel(row); } // 모바일: 선택 행 하이라이트
      curRow = row;
      box.className = canHover ? '' : 'mcfp-mobile';
      build(row);
      box.style.display = 'block'; box.style.left='-9999px'; box.style.top='0px'; // 크기 측정 후 배치
      place(row);
    }
    var _selUrl = null; // 현재 선택된 행의 식별자(테이블이 통째로 교체돼도 다시 찾기 위해 보관)
    function clearSel(){ try { _selUrl = null; var els=document.querySelectorAll('tr.mcfp-sel'); for (var i=0;i<els.length;i++) els[i].classList.remove('mcfp-sel'); } catch(e){} }
    function markSel(row){ try { clearSel(); row.classList.add('mcfp-sel'); _selUrl = row.getAttribute('data-fav-url') || null; } catch(e){} }
    // 목록 테이블이 교체된 뒤(refreshBookmarkTable) 선택 표시를 복구. 해제 타이머가 이미 돌았으면 _selUrl이 없어 no-op.
    window.mcFavReapplySel = function(){
      try {
        if (!_selUrl) return;
        var rows = document.querySelectorAll('tr[data-fav-url]');
        for (var i = 0; i < rows.length; i++) {
          if (rows[i].getAttribute('data-fav-url') === _selUrl) { rows[i].classList.add('mcfp-sel'); break; }
        }
      } catch(e){}
    };
    var _selTimer = null;
    function revealSel(){
      // 선택 행이 패널(자체 스크롤 컨테이너) 밖에 있으면 보이도록 스크롤. 이미 보이면 건드리지 않음.
      try {
        var row = document.querySelector('tr.mcfp-sel'); if (!row) return;
        var col = document.getElementById('bookmarkCollapse'); if (!col) return;
        var cr = col.getBoundingClientRect(), rr = row.getBoundingClientRect();
        if (rr.top < cr.top || rr.bottom > cr.bottom) {
          col.scrollTop = col.scrollTop + (rr.top - cr.top) - Math.max(0, (cr.height - rr.height) / 2);
        }
      } catch(e){}
    }
    function scheduleSelClear(){
      // 관리자 설정(복귀 표시 유지 시간)만큼 뒤에 선택 표시 해제. 0이면 자동 해제하지 않음.
      try {
        var ms = <?php echo (int)round(max(0, (float)get_app_settings('viewed_mark_seconds', 2)) * 1000); ?>;
        if (_selTimer) { clearTimeout(_selTimer); _selTimer = null; }
        if (ms > 0) _selTimer = setTimeout(function(){ clearSel(); _selTimer = null; }, ms);
      } catch(e){}
    }
    function saveReturn(row){
      // 뷰로 이동 직전: 선택 행 + 패널 내부 스크롤 저장(복귀 시 복원)
      try {
        var col = document.getElementById('bookmarkCollapse');
        sessionStorage.setItem('mcFavReturn', JSON.stringify({ u: row.getAttribute('data-fav-url') || '', s: col ? col.scrollTop : 0 }));
      } catch(e){}
    }
    // 인라인 onclick(location.href 즉시 이동)에서도 저장되도록 전역 노출 — 캡처 리스너는 이동 전에 실행될 틈이 없음
    window.mcFavMarkAndSave = function(el){
      try {
        var row = el; var i = 0;
        while (row && row.nodeType === 1 && !(row.hasAttribute && row.hasAttribute('data-fav-url')) && i < 8) { row = row.parentNode; i++; }
        if (row && row.nodeType === 1 && row.hasAttribute && row.hasAttribute('data-fav-url')) {
          markSel(row); saveReturn(row);
        }
      } catch(e){}
    };
    function rowOf(el){
      try { for (var n=el, i=0; n && n!==document && i<6; n=n.parentNode, i++){ if (n.nodeType===1 && n.hasAttribute && n.hasAttribute('data-fav-thumb')) return n; } } catch(e){}
      return null;
    }

    if (canHover) {
      // PC: hover 시 지연 표시(스치는 이동에 깜빡임 방지), 벗어나면 닫힘. 클릭은 기존 동작 그대로.
      document.addEventListener('mouseover', function(ev){
        var row = rowOf(ev.target); if (!row || row === curRow) return;
        if (showTimer) clearTimeout(showTimer);
        showTimer = setTimeout(function(){ open(row); }, 180);
      });
      document.addEventListener('mouseout', function(ev){
        var row = rowOf(ev.target); if (!row) return;
        var to = ev.relatedTarget;
        if (to && (rowOf(to) === row || box.contains(to))) return;
        hide();
      });
    } else {
      // 모바일: 제목 탭 → 미리보기. 바깥 탭/스크롤 → 닫힘. 썸네일/열기 → 이동.
      document.addEventListener('click', function(ev){
        try {
          if (box.contains(ev.target)) return;
          var row = rowOf(ev.target);
          if (row) {
            if (ev.target.closest && ev.target.closest('button') && !ev.target.closest('td:last-child')) { /* 삭제 등 다른 버튼은 기존 동작 */ }
            var tgtBtn = ev.target.closest ? ev.target.closest('button') : null;
            // 제목 버튼 판별: 행의 마지막 td(제목 칸) 안의 버튼 = 이동 버튼. 삭제/경고 아이콘 버튼(첫 td)은 제외.
            var isTitle = false;
            try {
              if (tgtBtn) {
                var td = tgtBtn.closest('td');
                var tds = row.getElementsByTagName('td');
                isTitle = !!(td && tds.length && td === tds[tds.length - 1]);
              }
            } catch(e2){}
            if (isTitle) { ev.preventDefault(); ev.stopPropagation(); if (curRow === row) { hide(); } else { open(row); } return; }
          }
          hide();
        } catch(e){}
      }, true);
      window.addEventListener('scroll', function(){ if (curRow) hide(); }, { passive:true });
    }
    // ✅ [복귀 선택 유지] 제목(마지막 td) 버튼으로 이동하는 모든 클릭에서 선택 표시 + 상태 저장 (PC 직클릭 포함)
    document.addEventListener('click', function(ev){
      try {
        var row = rowOf(ev.target); if (!row) return;
        var btn = ev.target.closest ? ev.target.closest('button') : null; if (!btn) return;
        var td = btn.closest('td'); var tds = row.getElementsByTagName('td');
        if (!(td && tds.length && td === tds[tds.length - 1])) return; // 제목 칸 버튼만
        if (!canHover) return; // 모바일은 미리보기(go)에서 처리
        markSel(row); saveReturn(row);
      } catch(e){}
    }, true);
    // ✅ [복귀 복원] 뒤로가기 콜드 로드에서만: 패널 열기 + 내부 스크롤 + 선택 행 표시.
    //    - back/forward 판정: bfcache 복귀는 스크립트가 재실행되지 않아 키가 남는데, 그 키가 이후 일반 이동(콜드)에서
    //      발동해 패널이 저절로 열리던 버그 수정 → 일반 이동이면 키만 폐기하고 적용하지 않음.
    try {
      var _ret = sessionStorage.getItem('mcFavReturn');
      if (_ret) {
        var _isBack = false;
        try { var _ne = performance.getEntriesByType('navigation'); _isBack = !!(_ne && _ne[0] && _ne[0].type === 'back_forward'); }
        catch(_eb){ try { _isBack = !!(performance.navigation && performance.navigation.type === 2); } catch(_eb2){} }
        sessionStorage.removeItem('mcFavReturn'); // 항상 1회 소비(오발동 방지)
        if (_isBack) {
          var _r = JSON.parse(_ret);
          if (_r && _r.u) {
            var _rows = document.querySelectorAll('tr[data-fav-url]');
            for (var _i = 0; _i < _rows.length; _i++) {
              if (_rows[_i].getAttribute('data-fav-url') === _r.u) {
                var _col = document.getElementById('bookmarkCollapse');
                if (_col) { _col.style.display = 'block'; _col.scrollTop = _r.s || 0; } // fadeToggle과 같은 display 제어
                markSel(_rows[_i]);
                revealSel(); scheduleSelClear(); // 선택 행 보이게 + 해제 타이머 시작
                break;
              }
            }
          }
        }
      }
    } catch(e){}
    // ✅ [복귀 선택 2초 해제] 복귀 시 표시된 선택(mcfp-sel)은 2초 후 자동 해제. bfcache 복귀는 키도 폐기.
    window.addEventListener('pageshow', function(e){
      try {
        if (e && e.persisted) { try { sessionStorage.removeItem('mcFavReturn'); } catch(_e){} }
        if (document.querySelector('tr.mcfp-sel')) { revealSel(); scheduleSelClear(); } // bfcache 복귀: 보이게 + 해제 타이머 재시작
      } catch(_e){}
    });
    window.addEventListener('resize', hide, { passive:true });
    document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape') hide(); });
  } catch(e){}
})();
</script>
<?php if (is_file(__DIR__ . '/library_index_inject.php')) { include __DIR__ . '/library_index_inject.php'; } // ★ (2026-10-29) 작품 정보(라이브러리) 연결 ?>
<?php require_once __DIR__ . '/privacy_shield.php'; ?>

<div style="text-align:center; padding:15px 0 10px; color:#999; font-size:12px;">
© <?php echo h($_branding['copyright'] ?? 'myComix'); ?>
</div>
<?php if (function_exists('mc_icons_script')) echo mc_icons_script(); /* ★ (2026-10-30) 이모지 → 선 아이콘 */ ?>
<script>
/* ★ (2026-11-08 fix80, 펜닐 요청) 폴더 화면 위 '<'(위 폴더로)·아래 '상위폴더' 단추 — 뷰어 뒤로가기처럼 보던 자리(쪽 번호·스크롤 위치)로
   · 폴더 화면을 떠날 때(누름·페이지 숨김) 이 폴더의 주소+스크롤(#ly픽셀)을 이 탭에 폴더별로 기억(sessionStorage mc_dir_pos, 최근 40곳)
   · '<' 는 위 폴더의 기억을 찾아 그 주소로(없으면 예전 주소) / 위 폴더에서 바로 눌러 들어왔으면 브라우저 뒤로(그 화면 그대로·기록 안 쌓임) */
(function () {
  var ME = <?php echo js((int)$current_bidx . '|' . trim((string)$getdir, '/')); ?>, MAP = 'mc_dir_pos';
  var RE = /^index\.php(?:\?[^#\s]*)?(?:#ly\d{1,7})?$/;
  function g(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
  function s(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }
  function d(k) { try { sessionStorage.removeItem(k); } catch (e) {} }
  function rd() { try { var m = JSON.parse(g(MAP) || '{}'); return (m && typeof m === 'object') ? m : {}; } catch (e) { return {}; } }
  function save() {
    try {
      var y = Math.round(window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0);
      var m = rd(); m[ME] = { u: 'index.php' + location.search + (y > 0 ? '#ly' + y : ''), t: Date.now() };
      var ks = Object.keys(m);
      if (ks.length > 40) { ks.sort(function (a, b) { return (m[a].t || 0) - (m[b].t || 0); }); for (var i = 0; i < ks.length - 40; i++) delete m[ks[i]]; }
      s(MAP, JSON.stringify(m));
    } catch (e) {}
  }
  document.addEventListener('click', function (e) {
    save();
    try { var a = e.target && e.target.closest ? e.target.closest('a[href]') : null; if (a && /^index\.php\?/.test(a.getAttribute('href') || '') && /[?&]dir=/.test(a.getAttribute('href'))) s('mc_dir_click', ME + '\n' + Date.now()); } catch (x) {}
  }, true);
  window.addEventListener('pagehide', save);
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') save(); });
  // 바로 위 폴더에서 눌러 들어왔는지(그 화면이 바로 앞 방문 기록인지) — 뷰어 뒤로가기와 같은 방식
  var nt = ''; try { var en = performance.getEntriesByType('navigation'); nt = (en && en[0] && en[0].type) || ''; } catch (x) {}
  var UP = <?php echo js((string)($_mc_up_key ?? '')); ?>;
  if (nt === 'navigate') {
    var c = String(g('mc_dir_click') || '').split('\n');
    if (UP && c.length === 2 && c[0] === UP && Date.now() - (parseInt(c[1], 10) || 0) < 20000) s('mc_dir_hist', UP + '\n' + history.length);
    else d('mc_dir_hist');
  } else if (nt !== 'back_forward' && nt !== 'reload') d('mc_dir_hist');
  d('mc_dir_click');
  window.mcDirUp = function (btn) {
    var k = btn.getAttribute('data-mc-up') || '', def = btn.getAttribute('data-up-url') || btn.getAttribute('href') || 'index.php';
    if (k && history.length > 1 && g('mc_dir_hist') === k + '\n' + history.length) { d('mc_dir_hist'); history.back(); return; }
    var e = rd()[k], u = (e && typeof e.u === 'string' && RE.test(e.u)) ? e.u : def;
    location.replace(u);
  };
})();
</script>
<?php if (function_exists('mc_back_origin_list_js')) echo mc_back_origin_list_js(); /* ★ (2026-11-05) 뷰어 뒤로가기가 이 화면(주소·스크롤 위치)으로 돌아오게 — function.php */ ?>
<script>/* ★ fix48·fix49 검색창으로 검색을 보낼 때 — 비우고 보내면 검색 끝(mcSfEnd: ✓ 지움) · 아래 막대가 없는 화면에서 보내면 새 검색(✓ 새로, mc_sf_js) · 막대가 떠 있는 화면에서 같은 말이면 ✓ 유지 */document.addEventListener('submit',function(e){var f=e.target,i=f&&f.querySelector&&f.querySelector('input[name="q"]');if(!i)return;if(String(i.value||'').trim()===''){if(window.mcSfEnd)try{mcSfEnd();}catch(x){}return;}var bar=document.getElementById('mcSfBar');if(!bar||document.body.classList.contains('mc-sf-ended'))try{sessionStorage.setItem('mc_sf_new','1');}catch(x){}},true);</script>
<?php if (function_exists('mc_search_footer_html')) echo mc_search_footer_html(); /* ★ (2026-11-07 fix41) 찾은 폴더 목록 창 + 아래 이동 막대(검색어가 있고 다른 폴더가 있을 때만) */ ?>
</body>
</html>