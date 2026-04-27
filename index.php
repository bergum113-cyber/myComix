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
        if ($validated) $target_base = $validated;
    }
    
    $uploadTmpDir = __DIR__ . '/src/upload_tmp';
    if (!is_dir($uploadTmpDir)) @mkdir($uploadTmpDir, 0755, true);
    
    $action = $_POST['admin_action'];
    
    if ($action === 'chunk_init') {
        $uploadId = bin2hex(random_bytes(16));
        $fileName = $_POST['file_name'] ?? 'unknown';
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
        $uploadId = $_POST['upload_id'] ?? '';
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
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'chunk_finish') {
        $uploadId = $_POST['upload_id'] ?? '';
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
        
        $dest = $session['target_path'] . '/' . $safeName;
        
        // 기존 파일 있으면 덮어쓰기 (삭제 후 이동)
        if (file_exists($dest)) {
            @unlink($dest);
        }
        
        if (rename($tmpFile, $dest)) {
            @unlink($sessionFile);
            $cache = dirname($dest) . '/.folder_cache.json';
            if (file_exists($cache)) @unlink($cache);
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
    $current_dir = $_POST['current_dir'] ?? '';
    
    // 경로 검증
    $target_base = $base_dir;
    if (!empty($current_dir)) {
        $validated_dir = validate_file_path($current_dir, $base_dir);
        if ($validated_dir === false) {
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
            $folder_name = preg_replace('/[<>:"\/\\|?*\x00-\x1f]/', '', $folder_name);
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
                    preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '.json', $validated_path),
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
                log_user_activity('폴더 삭제', $validated_path);
                echo json_encode(['success' => true, 'message' => __('api_folder_deleted', $folder_name)]);
            } else {
                echo json_encode(['success' => false, 'error' => __('api_folder_delete_failed')]);
            }
            break;
            
        case 'delete_multiple':
            $files = json_decode($_POST['files'] ?? '[]', true);
            $folders = json_decode($_POST['folders'] ?? '[]', true);
            
            if (empty($files) && empty($folders)) {
                echo json_encode(['success' => false, 'error' => __('api_nothing_to_delete')]);
                break;
            }
            
            $deleted_files = 0;
            $deleted_folders = 0;
            $failed = 0;
            
            // 재귀적 폴더 삭제 함수
            if (!function_exists('deleteDirectoryRecursive')) {
                function deleteDirectoryRecursive($dir) {
                    if (!is_dir($dir)) return false;
                    $items = array_diff(scandir($dir), ['.', '..']);
                    foreach ($items as $item) {
                        $path = $dir . '/' . $item;
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
            foreach ($files as $file_path) {
                $validated_path = validate_file_path($file_path, $base_dir);
                if ($validated_path === false || !is_file($validated_path)) {
                    $failed++;
                    continue;
                }
                
                if (@unlink($validated_path)) {
                    // 관련 캐시/메타 파일도 삭제
                    $related_files = [
                        $validated_path . '.video_thumb.jpg',
                        preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '.json', $validated_path),
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
                } else {
                    $failed++;
                }
            }
            
            // 폴더 삭제
            foreach ($folders as $folder_path) {
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
                } else {
                    $failed++;
                }
            }
            
            $total_deleted = $deleted_files + $deleted_folders;
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
    
    if (!is_file($video_file) || !is_video_file($video_file)) {
        http_response_code(404);
        exit;
    }
    
    $thumb_path = $video_file . '.video_thumb.jpg';
    
    if (file_exists($thumb_path)) {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($thumb_path));
        header('Cache-Control: public, max-age=86400');
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
            
            if (!verify_totp($settings['secret'] ?? '', $otp_code) && !verify_user_backup_code($user_id, $otp_code)) {
                echo json_encode(['success' => false, 'error' => __('api_2fa_invalid_code')]);
                break;
            }
            
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
            
            delete_user_totp($user_id);
            echo json_encode(['success' => true, 'message' => __('api_2fa_deleted')]);
            break;
            
        case 'regenerate_backup':
            // 백업 코드 재생성
            $settings = load_user_totp($user_id);
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
            if (!password_verify($hashed_current, $ua[$user_id]['pass'])) {
                echo json_encode(['success' => false, 'error' => __('api_current_password_wrong')]);
                break;
            }
            
            // 새 비밀번호 저장
            $ua[$user_id]['pass'] = password_hash(hash("sha256", $new_pass), PASSWORD_DEFAULT);
            $ua[$user_id]['password_changed_at'] = date('Y-m-d H:i:s');
            unset($ua[$user_id]['must_change_password']); // 비밀번호 변경 필요 플래그 제거
            save_users($ua);
            
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
            
            // 이메일 중복 체크
            if (!empty($new_email)) {
                foreach ($ua as $uid => $udata) {
                    if ($uid !== $user_id && isset($udata['email']) && strtolower($udata['email']) === strtolower($new_email)) {
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
                $all_logs = json_decode(file_get_contents($log_file), true) ?? [];
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
            if (!password_verify($hashed_pass, $ua[$user_id]['pass'])) {
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
            if (file_exists($deleted_file)) {
                $deleted_users = json_decode(file_get_contents($deleted_file), true) ?? [];
            }
            $deleted_users[] = [
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => 'self',
                'user_id' => $user_id,
                'user_info' => $user_info,
                'login_logs' => $login_logs,
                'activity_logs' => $activity_logs
            ];
            file_put_contents($deleted_file, json_encode($deleted_users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
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
            ];
            foreach ($user_files as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
            
            // ✅ 해당 사용자의 로그인 기록 삭제
            if (file_exists($log_file)) {
                $fp = fopen($log_file, 'c+');
                if ($fp && flock($fp, LOCK_EX)) {
                    $content = stream_get_contents($fp);
                    $logs = json_decode($content, true) ?? [];
                    
                    // 해당 사용자의 로그만 제거
                    $logs = array_filter($logs, function($log) use ($user_id) {
                        return ($log['user_id'] ?? '') !== $user_id;
                    });
                    $logs = array_values($logs); // 인덱스 재정렬
                    
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fflush($fp);
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
                    $logs = json_decode($content, true) ?? [];
                    
                    $logs = array_filter($logs, function($log) use ($user_id) {
                        return ($log['user_id'] ?? '') !== $user_id;
                    });
                    $logs = array_values($logs);
                    
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fflush($fp);
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
?>
<html lang="<?php echo get_html_lang(); ?>">
<head>
	<script>
	// ✅ 탭 열기 시 캐시 플래그 초기화 (새 탭/브라우저 재시작 시 실행)
	if (!sessionStorage.getItem('tab_init')) {
		sessionStorage.setItem('tab_init', '1'); // 먼저 설정하여 무한 루프 방지
		fetch('init.php?reset_cache=1').then(function(){
			location.reload(); // 캐시 초기화 후 리로드
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
    <link rel="shortcut icon" href="./favicon.ico">
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
			src: url('<?php echo h($list_font_local); ?>');
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
		/* 뱃지 안 이모지 정렬 */
		.badge .emoji-icon {
			vertical-align: baseline;
			width: auto;
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
<script>document.documentElement.classList.add('ready');</script>
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

<?php if (isset($_SESSION['user_id']) && empty($_SESSION['index_refreshed'])): ?>
<script>
// 모든 bidx 순차 갱신
(function() {
    const allBidxs = <?php echo json_encode(array_keys($base_dirs)); ?>;
    let idx = 0;
    function refreshNext() {
        if (idx >= allBidxs.length) return;
        fetch("index.php?make_index=1&bidx=" + allBidxs[idx])
        .then(res => res.text())
        .then(() => { idx++; setTimeout(refreshNext, 500); });
    }
    setTimeout(refreshNext, 1000);
})();
</script>
<?php endif; ?>

<?php if (isset($_SESSION['user_id']) && empty($_SESSION['zip_cache_generated'])): ?>
<script>
// 모든 bidx 순차 갱신
(function() {
    const allBidxs = <?php echo json_encode(array_keys($base_dirs)); ?>;
    let idx = 0;
    function refreshNext() {
        if (idx >= allBidxs.length) return;
        fetch("index.php?make_zip_cache=1&bidx=" + allBidxs[idx])
        .then(res => res.text())
        .then(() => { idx++; setTimeout(refreshNext, 500); });
    }
    setTimeout(refreshNext, 1500);
})();
</script>
<?php endif; ?>

<?php if (isset($_SESSION['user_id']) && empty($_SESSION['filelist_cache_generated'])): ?>
<script>
// 모든 bidx 순차 갱신
(function() {
    const allBidxs = <?php echo json_encode(array_keys($base_dirs)); ?>;
    let idx = 0;
    function refreshNext() {
        if (idx >= allBidxs.length) return;
        fetch("index.php?make_filelist_cache=1&bidx=" + allBidxs[idx])
        .then(res => res.text())
        .then(() => { idx++; setTimeout(refreshNext, 500); });
    }
    setTimeout(refreshNext, 2000);
})();
</script>
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
$q = get_param('q', 'search', '');
$dir_param = get_param('dir', 'path', '');
$sort_param = validate_sort_mode(get_param('sort', 'string', 'nameasc'));
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
    
    // ✅ 검색 로그 기록
    log_user_activity('검색', $q);
    
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
    $dir = $base_dir . $getdir;
    dir_check($getdir);  // 권한 체크만 수행
    
    // ✅ 폴더 접근 로그 기록 (AJAX 요청 제외)
    if (!isset($_GET['get_folders']) && !isset($_GET['make_thumbnail_cache']) && 
        !isset($_GET['make_cover_cache']) && !isset($_GET['make_filelist_cache']) &&
        !isset($_GET['make_folder_cache']) && !isset($_GET['make_image_files_cache']) &&
        !isset($_GET['make_video_files_cache']) && !isset($_GET['make_index']) &&
        !isset($_GET['make_search_index']) && !isset($_GET['thumb'])) {
        log_user_activity('폴더 접근', $getdir);
    }
} else {
    $dir = $base_dir;
}


// 검색어 포함된 전체 폴더 검색 //

// 로그인 직후에만 전체 인덱스 생성 - bidx별 분리
if (get_param('make_index', 'string') === '1' && isset($_SESSION['user_id'])) {
    $index_file = __DIR__ . '/src/search_index_' . $bidx . '.json';
    $total_file = __DIR__ . '/src/zip_total_' . $bidx . '.json';
    $index_output = [];
    $zip_total = 0;
    $folder_total = 0;
    $counted_dirs = [];  // 폴더 중복 카운트 방지

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
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

    $_SESSION['index_refreshed'] = true;
    echo "ok";
    exit;
}


// ✅ 폴더 목록 조회 (admin 폴더 브라우저용)
if (get_param('get_folders', 'string') === '1' && isset($_SESSION['user_id'])) {
    $path = get_param('path', 'string');
    
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
        echo json_encode(['folders' => [], 'error' => 'Directory not found', 'path' => $path, 'target' => $target_dir]);
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
    $folder = get_param('folder', 'string');
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
        $result = generate_thumbnail_cache($base_dir, $force);
        echo json_encode(['status' => 'ok', 'created' => $result['created'], 'skipped' => $result['skipped'], 'total' => $result['total']]);
    }
    exit;
}

// ✅ 커버 이미지 생성 (스트리밍 - [cover].jpg만)
if (get_param('make_cover_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    $force = (get_param('force', 'string') === '1');
    $stream = (get_param('stream', 'string') === '1');
    $folder = get_param('folder', 'string');
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
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));
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
        $json_file = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '.json', $zipfile);
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
        
        $zip = new ZipArchive;
        if ($zip->open($zipfile) === TRUE) {
            // 캐시가 없으면 ZIP 스캔
            if (!$use_cached_list) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zname = $zip->getNameIndex($i);
                    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                        $image_files[] = $zname;
                    }
                    if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                        $video_files[] = $zname;
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
                $zip->close();
                $created++;
                continue;
            }
            
            // 이미지가 있는 ZIP
            if (count($image_files) > 0) {
                natsort($image_files);
                $image_files = array_values($image_files);
                $thumbnail_filename = $image_files[0];
                
                $img_data = $zip->getFromName($thumbnail_filename);
                if ($img_data !== false) {
                    $thumbnail_data = null;
                    global $vips_path;
                    
                    // vips 경로가 설정되어 있으면 vips 사용
                    if (!empty($vips_path) && file_exists($vips_path)) {
                        $temp_in = sys_get_temp_dir() . '/thumb_in_' . uniqid() . '.' . pathinfo($thumbnail_filename, PATHINFO_EXTENSION);
                        $temp_out = sys_get_temp_dir() . '/thumb_out_' . uniqid() . '.jpg';
                        
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
                        $img = @imagecreatefromstring($img_data);
                        if ($img) {
                            $w = imagesx($img);
                            $h = imagesy($img);
                            $new_h = 400;
                            $new_w = intval($w * ($new_h / $h));
                            
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
            
            $zip->close();
            unset($zip);
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
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));
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
            $json_file = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '.json', $zipfile);
            
            // json에서 썸네일 가져오기
            if (file_exists($json_file)) {
                $json_data = @json_decode(file_get_contents($json_file), true);
                if ($json_data && !empty($json_data['thumbnail']) && strlen($json_data['thumbnail']) > 100) {
                    @file_put_contents($cover_file, base64_decode($json_data['thumbnail']), LOCK_EX);
                    $created++;
                    break;
                }
            }
            
            // json이 없으면 ZIP에서 직접 추출
            $zip = new ZipArchive;
            if ($zip->open($zipfile) === TRUE) {
                $image_files = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zname = $zip->getNameIndex($i);
                    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                        $image_files[] = $zname;
                    }
                }
                
                if (count($image_files) > 0) {
                    natsort($image_files);
                    $image_files = array_values($image_files);
                    $img_data = $zip->getFromName($image_files[0]);
                    
                    if ($img_data !== false) {
                        $img = @imagecreatefromstring($img_data);
                        if ($img) {
                            $w = imagesx($img);
                            $h = imagesy($img);
                            $new_h = 400;
                            $new_w = intval($w * ($new_h / $h));
                            
                            $cropimage = imagecreatetruecolor($new_w, $new_h);
                            imagecopyresampled($cropimage, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
                            imagedestroy($img);
                            
                            ob_start();
                            imagejpeg($cropimage, null, 75);
                            $thumbnail_data = ob_get_contents();
                            ob_end_clean();
                            imagedestroy($cropimage);
                            
                            @file_put_contents($cover_file, $thumbnail_data, LOCK_EX);
                            $zip->close();
                            $created++;
                            break;
                        }
                    }
                }
                $zip->close();
            }
        }
        
        // 커버 생성 실패 시 스킵 카운트
        if (!file_exists($cover_file)) {
            $skipped++;
        }
        
        gc_collect_cycles();
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
        $file_count = 0;
        $current = 0;
        
        // 먼저 전체 개수 파악
        $total = 0;
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if ($file->isFile()) $total++;
        }
        
        // 실제 처리
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if ($file->isFile()) {
                $current++;
                $file_count++;
                $relative_path = str_replace($base_dir, '', $file->getPathname());
                $relative_path = trim(str_replace("\\", "/", $relative_path), '/');
                
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
            new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($rii as $item) {
            $total++;
        }
        
        // 실제 처리
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
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
    $folder = get_param('folder', 'string');
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
    $_SESSION['filelist_cache_generated'] = true;
    exit;
}

// ✅ 폴더 캐시 생성 (스트리밍)
if (get_param('make_folder_cache', 'string') === '1' && isset($_SESSION['user_id'])) {
    $force = (get_param('force', 'string') === '1');
    $stream = (get_param('stream', 'string') === '1');
    $folder = get_param('folder', 'string');
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
    $folder = get_param('folder', 'string');
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
    $folder = get_param('folder', 'string');
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
function generate_filelist_cache_streaming($base_dir, $force = false, $recursive = true) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    $current = 0;
    $CACHE_VERSION = 3;
    
    // 폴더 목록 수집
    $folders = [$base_dir];
    if ($recursive) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
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
        
        // 강제가 아니고 캐시가 이미 있으면 스킵
        if (!$force && file_exists($cache_file)) {
            $cache_mtime = @filemtime($cache_file);
            $dir_mtime = @filemtime($dir_path);
            
            // ✅ 실제 파일 개수 (Windows mtime 버그 대응)
            $actual_items = @scandir($dir_path);
            $actual_count = $actual_items ? count($actual_items) - 2 : 0;
            $existing = @json_decode(file_get_contents($cache_file), true);
            $cached_count = $existing['total_items'] ?? -1;
            
            if ($cache_mtime >= $dir_mtime && ($cached_count === -1 || $cached_count === $actual_count)) {
                // 버전 체크
                if (isset($existing['version']) && $existing['version'] >= $CACHE_VERSION) {
                    $skipped++;
                    continue;
                }
            }
        }
        
        // 캐시 생성
        $file_list = [];
        $dir_list = [];
        $jpg_list = [];
        $title_list = [];
        $dirinfo = [];
        
        $files = @scandir($dir_path);
        if ($files === false) {
            $skipped++;
            continue;
        }
        
        foreach ($files as $filename) {
            if ($filename === '.' || $filename === '..' || $filename === '@eaDir' || $filename === 'tmp' || $filename === 'robots.txt') continue;
            if (strpos($filename, '.') === 0) continue; // 숨김 파일
            
            $filepath = $dir_path . '/' . $filename;
            $is_dir = is_dir($filepath);
            
            if ($is_dir) {
                // 폴더 처리
                if (strpos($filename, "rclone_") !== false) {
                    $dir_list[] = $filename;
                    $dirinfo[$filename] = "remote";
                    continue;
                }
                
                // 하위 폴더 내용 확인
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
                
                // .folder_cache.json 확인
                $sub_cache_file = $filepath . '/.folder_cache.json';
                $sub_mtime = @filemtime($filepath);
                $use_sub_cache = false;
                
                // ✅ 실제 파일 개수 (Windows mtime 버그 대응)
                $actual_items = @scandir($filepath);
                $actual_count = $actual_items ? count($actual_items) - 2 : 0; // . 과 .. 제외
                
                if (is_file($sub_cache_file)) {
                    $sub_cache_mtime = @filemtime($sub_cache_file);
                    $sub_cache = @json_decode(file_get_contents($sub_cache_file), true);
                    
                    // ✅ mtime 비교 + 파일 개수 비교 (둘 다 일치해야 캐시 사용)
                    $cached_count = $sub_cache['total_items'] ?? -1;
                    if ($sub_cache_mtime >= $sub_mtime && ($cached_count === -1 || $cached_count === $actual_count)) {
                        if (isset($sub_cache['jpg_count'], $sub_cache['zip_count'], $sub_cache['has_subdir'], $sub_cache['subdir_count'])) {
                            $jpg_count = $sub_cache['jpg_count'];
                            $zip_count = $sub_cache['zip_count'];
                            $video_count = $sub_cache['video_count'] ?? 0;
                            $has_subdir = $sub_cache['has_subdir'];
                            $subdir_count = $sub_cache['subdir_count'] ?? ($has_subdir ? 1 : 0);
                            $newest_mtime = $sub_cache['newest_mtime'] ?? 0;
                            $use_sub_cache = true;
                        }
                    }
                }
                
                if (!$use_sub_cache) {
                    if ($dh = @opendir($filepath)) {
                        while (($sf = readdir($dh)) !== false) {
                            if ($sf[0] === '.' || $sf === '@eaDir' || $sf === 'tmp' || $sf === 'robots.txt') continue;
                            $sf_full = $filepath . '/' . $sf;
                            
                            // ✅ 콘텐츠 파일만 newest_mtime 계산 (캐시/메타 파일 제외)
                            if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|epub|txt|mp4|mkv|avi|mov|webm|m4v|ts|mts|m2ts|wmv|flv)$/i', $sf)) {
                                $sf_ctime = get_file_created_time($sf_full);
                                if ($sf_ctime > $newest_mtime) $newest_mtime = $sf_ctime;
                            }
                            
                            if (is_dir($sf_full)) {
                                $has_subdir = true;
                                $subdir_count++;
                            } else {
                                if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $sf)) $jpg_count++;
                                if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $sf)) {
                                    $zip_count++;
                                    if (preg_match('/\.pdf$/i', $sf)) $has_pdf = true;
                                    if (preg_match('/\.epub$/i', $sf)) $has_epub = true;
                                    if (preg_match('/\.txt$/i', $sf)) $has_txt = true;
                                }
                                if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $sf)) $video_count++;
                            }
                            if ($jpg_count > 10) break;
                        }
                        closedir($dh);
                    }
                    
                    // .folder_cache.json 저장
                    $sub_cache_data = [
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
                        'total_items' => $actual_count,  // ✅ 파일 개수 저장 (Windows mtime 버그 대응)
                        'mtime' => time()
                    ];
                    @file_put_contents($sub_cache_file, json_encode($sub_cache_data), LOCK_EX);
                }
                
                // 폴더 분류
                $imgfolder_threshold = $GLOBALS['imgfolder_threshold'] ?? 5;
                $video_folder_as_dir = $GLOBALS['video_folder_as_dir'] ?? true;
                
                if ($has_subdir) {
                    $dir_list[] = $filename;
                } elseif ($video_folder_as_dir && $video_count > 0) {
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
            } else {
                // 파일 처리
                if ($filename === '[cover].jpg') continue;
                if (preg_match('/\.json$/i', $filename)) continue;
                if (preg_match('/\.image_files\.json$/i', $filename)) continue;
                
if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $filename)) {
    $json_file = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', '.json', $filepath);
    $badge_info = ['totalpage' => 0, 'page_order' => '0', 'viewer' => 'toon', 'is_video_archive' => false];
    
    // ✅ 1순위: 파일명.json 확인
    if (is_file($json_file)) {
        $json_data = @json_decode(file_get_contents($json_file), true);
        if ($json_data) {
            $badge_info['totalpage'] = $json_data['totalpage'] ?? 0;
            $badge_info['page_order'] = $json_data['page_order'] ?? '0';
            $badge_info['viewer'] = $json_data['viewer'] ?? 'toon';
            $badge_info['is_video_archive'] = $json_data['is_video_archive'] ?? false;
        }
    }
    
    // ✅ 2순위: totalpage가 0이고 .image_files.json이 있으면 거기서 개수 가져오기
    if ($badge_info['totalpage'] == 0) {
        $image_cache = $filepath . '.image_files.json';
        if (is_file($image_cache)) {
            $image_files = @json_decode(file_get_contents($image_cache), true);
            if (is_array($image_files) && !empty($image_files)) {
                $badge_info['totalpage'] = count($image_files);
            }
        }
    }
                    $file_list[] = [
                        'name' => $filename,
                        'time' => @filemtime($filepath),
                        'size' => @filesize($filepath),
                        'totalpage' => $badge_info['totalpage'],
                        'page_order' => $badge_info['page_order'],
                        'viewer' => $badge_info['viewer'],
                        'is_video_archive' => $badge_info['is_video_archive']
                    ];
                } elseif (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $filename)) {
                    $file_list[] = [
                        'name' => $filename,
                        'time' => @filemtime($filepath),
                        'size' => @filesize($filepath),
                        'type' => 'video'
                    ];
                } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
                    if (!preg_match('/\.video_thumb\.jpg$/i', $filename)) {
                        $jpg_list[] = $filename;
                    }
                }
            }
        }
        
        // ✅ [추가] 캐시 저장 전 검증: totalpage가 0인데 image_files.json이 있으면 수정
        foreach ($file_list as &$file_item) {
            if (!is_array($file_item)) continue;
            
            $fname = $file_item['name'] ?? '';
            if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', $fname)) {
                // totalpage가 0이면 .image_files.json 확인
                if (($file_item['totalpage'] ?? 0) == 0) {
                    $fpath = $dir_path . '/' . $fname;
                    $img_cache = $fpath . '.image_files.json';
                    
                    if (is_file($img_cache)) {
                        $img_list = @json_decode(file_get_contents($img_cache), true);
                        if (is_array($img_list) && !empty($img_list)) {
                            $file_item['totalpage'] = count($img_list);
                        }
                    }
                }
            }
        }
        unset($file_item); // 참조 해제

        // 캐시 저장
        $cache_data = [
            'version' => $CACHE_VERSION,
            'file_list' => $file_list,
            'dir_list' => $dir_list,
            'jpg_list' => $jpg_list,
            'title_list' => $title_list,
            'dirinfo' => $dirinfo,
            'total_items' => $files ? count($files) - 2 : 0,  // ✅ 파일 개수 저장 (Windows mtime 버그 대응)
            'mtime' => time()
        ];
        
        @file_put_contents($cache_file, json_encode($cache_data, JSON_UNESCAPED_UNICODE), LOCK_EX);
        $created++;
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
            new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
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
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));
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
        
        $zip = new ZipArchive;
        if ($zip->open($zipfile) === TRUE) {
            $image_files = [];
            $has_video = false;
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zname = $zip->getNameIndex($i);
                if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $zname)) {
                    $image_files[] = $zname;
                }
                if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                    $has_video = true;
                }
            }
            
            $zip->close();
            
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
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));
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
        
        $zip = new ZipArchive;
        if ($zip->open($zipfile) === TRUE) {
            $video_files = [];
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zname = $zip->getNameIndex($i);
                if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $zname)) {
                    $video_files[] = $zname;
                }
            }
            
            $zip->close();
            
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
    $created = 0;
    $skipped = 0;
    $total = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            $total++;
            $dir_path = $item->getPathname();
            $cache_file = $dir_path . '/.filelist_cache.json';
            $dir_mtime = @filemtime($dir_path);
            
            // 캐시가 이미 있고 최신이면 스킵 (강제 갱신이 아닐 때만)
            if (!$force && is_file($cache_file)) {
                $cache_mtime = @filemtime($cache_file);
                
                // ✅ 실제 파일 개수 (Windows mtime 버그 대응)
                $check_items = @scandir($dir_path);
                $check_count = $check_items ? count($check_items) - 2 : 0;
                $existing = @json_decode(file_get_contents($cache_file), true);
                $cached_count = $existing['total_items'] ?? -1;
                
                if ($cache_mtime >= $dir_mtime && ($cached_count === -1 || $cached_count === $check_count)) {
                    $skipped++;
                    continue;
                }
            }
            
            // 파일 목록 생성
            $file_list = [];
            $dir_list = [];
            $jpg_list = [];
            $title_list = [];
            $dirinfo = [];
            
            $files = @scandir($dir_path, SCANDIR_SORT_NONE);
            if ($files === false) continue;
            
            foreach ($files as $filename) {
                if ($filename[0] === '.' || $filename === '@eaDir' || $filename === 'tmp' || $filename === 'robots.txt') continue;
                $filepath = $dir_path . '/' . $filename;
                
                if (is_dir($filepath)) {
                    // 하위 폴더 처리 - .folder_cache.json 생성
                    $sub_cache = $filepath . '/.folder_cache.json';
                    $sub_mtime = @filemtime($filepath);
                    $need_folder_cache = true;
                    
                    // ✅ 실제 파일 개수 (Windows mtime 버그 대응)
                    $actual_items = @scandir($filepath);
                    $actual_count = $actual_items ? count($actual_items) - 2 : 0;
                    
                    // 기존 캐시 체크
                    if (is_file($sub_cache)) {
                        $cache_mtime = @filemtime($sub_cache);
                        $cache = @json_decode(file_get_contents($sub_cache), true);
                        
                        // ✅ mtime 비교 + 파일 개수 비교 (둘 다 일치해야 캐시 사용)
                        $cached_count = $cache['total_items'] ?? -1;
                        if ($cache_mtime >= $sub_mtime && ($cached_count === -1 || $cached_count === $actual_count)) {
                            if ($cache && isset($cache['jpg_count'], $cache['zip_count'], $cache['has_subdir'], $cache['video_count'], $cache['subdir_count'])) {
                                $need_folder_cache = false;
                                $has_subdir = $cache['has_subdir'] ?? false;
                                $jpg_count = $cache['jpg_count'] ?? 0;
                                $video_count = $cache['video_count'] ?? 0;
                            }
                        }
                    }
                    
                    // .folder_cache.json 생성 필요하면 생성
                    if ($need_folder_cache) {
                        $jpg_count = 0;
                        $zip_count = 0;
                        $video_count = 0;
                        $pure_image_count = 0;  // 순수 이미지 개수 (썸네일 제외)
                        $has_subdir = false;
                        $subdir_count = 0;
                        $has_pdf = false;
                        $has_epub = false;
                        $has_txt = false;
                        $newest_mtime = 0;  // ✅ 폴더 내 가장 최신 파일의 mtime
                        
                        if ($dh = @opendir($filepath)) {
                            $all_files = [];
                            $media_basenames = [];
                            
                            // 1차 스캔: 모든 파일 수집 및 미디어/문서 파일명 추출
                            while (($sf = readdir($dh)) !== false) {
                                if ($sf[0] === '.' || $sf === '@eaDir' || $sf === 'tmp' || $sf === 'robots.txt') continue;
                                $all_files[] = $sf;
                                
                                // ✅ 콘텐츠 파일만 newest_mtime 계산 (캐시/메타 파일 제외)
                                if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|epub|txt|mp4|mkv|avi|mov|webm|m4v|ts|mts|m2ts|wmv|flv)$/i', $sf)) {
                                    $sf_ctime = get_file_created_time($filepath . '/' . $sf);
                                    if ($sf_ctime > $newest_mtime) $newest_mtime = $sf_ctime;
                                }
                                
                                // 미디어/문서 파일의 basename 수집 (이미지 썸네일 판별용)
                                if (preg_match('/\.(mp4|mkv|avi|mov|webm|m4v|m2t|ts|mts|m2ts|wmv|flv|txt|epub|hwpx?|pdf|zip|cbz|rar|cbr|7z|cb7)$/i', $sf)) {
                                    $basename = preg_replace('/\.(mp4|mkv|avi|mov|webm|m4v|m2t|ts|mts|m2ts|wmv|flv|txt|epub|hwpx?|pdf|zip|cbz|rar|cbr|7z|cb7)$/i', '', $sf);
                                    $media_basenames[$basename] = true;
                                }
                            }
                            closedir($dh);
                            
                            // 2차 처리: 파일 분류
                            foreach ($all_files as $sf) {
                                $sf_full = $filepath . '/' . $sf;
                                
                                if (is_dir($sf_full)) {
                                    $has_subdir = true;
                                    $subdir_count++;
                                } else {
                                    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $sf) && substr($sf, -16) !== '.video_thumb.jpg') {
                                        $jpg_count++;
                                        // 순수 이미지 판별 (썸네일 파일 제외)
                                        $is_thumbnail = false;
                                        if (preg_match('/^\[cover\]/i', $sf)) $is_thumbnail = true;
                                        if (preg_match('/\.video_thumb\.jpg$/i', $sf)) $is_thumbnail = true;
                                        // 동일 파일명의 미디어/문서 파일이 있으면 썸네일 (movie.jpg ↔ movie.mp4)
                                        $img_basename = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '', $sf);
                                        if (isset($media_basenames[$img_basename])) $is_thumbnail = true;
                                        if (!$is_thumbnail) $pure_image_count++;
                                    }
                                    if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $sf)) {
                                        $zip_count++;
                                    }
                                    if (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $sf)) {
                                        $video_count++;
                                    }
                                    // 파일 타입별 플래그 설정
                                    if (preg_match('/\.pdf$/i', $sf)) $has_pdf = true;
                                    if (preg_match('/\.epub$/i', $sf)) $has_epub = true;
                                    if (preg_match('/\.txt$/i', $sf)) $has_txt = true;
                                }
                                if ($jpg_count > 10) break;
                            }
                        }
                        
                        $folder_cache = [
                            'jpg_count' => $jpg_count,
                            'zip_count' => $zip_count,
                            'video_count' => $video_count,
                            'pure_image_count' => $pure_image_count,
                            'has_subdir' => $has_subdir,
                            'subdir_count' => $subdir_count,
                            'has_pdf' => $has_pdf,
                            'has_epub' => $has_epub,
                            'has_txt' => $has_txt,
                            'newest_mtime' => $newest_mtime,
                            'total_items' => $actual_count,  // ✅ 파일 개수 저장 (Windows mtime 버그 대응)
                            'mtime' => time()
                        ];
                        @file_put_contents($sub_cache, json_encode($folder_cache), LOCK_EX);
                    }
                    
                    // filelist에 반영
                    if ($has_subdir || $video_count > 0) {
                        $dir_list[] = $filename;
                    } elseif ($jpg_count > 5) {
                        $file_list[] = [
                            'name' => $filename . '_imgfolder',
                            'time' => @filemtime($filepath),
                            'size' => 0
                        ];
                    } else {
                        $title_list[] = $filename;
                    }
                } else {
                    // 파일 처리
                    if ($filename === '[cover].jpg') continue;
                    if (preg_match('/\.json$/i', $filename)) continue;
                    
                    if (preg_match('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', $filename)) {
                        // ✅ 배지 정보도 함께 저장
                        $json_file = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', '.json', $filepath);
                        $badge_info = ['totalpage' => 0, 'page_order' => '0', 'viewer' => 'toon', 'is_video_archive' => false];
                        if (is_file($json_file)) {
                            $json_data = @json_decode(file_get_contents($json_file), true);
                            if ($json_data) {
                                $badge_info['totalpage'] = $json_data['totalpage'] ?? 0;
                                $badge_info['page_order'] = $json_data['page_order'] ?? '0';
                                $badge_info['viewer'] = $json_data['viewer'] ?? 'toon';
                                $badge_info['is_video_archive'] = $json_data['is_video_archive'] ?? false;
                            }
                        }
                        $file_list[] = [
                            'name' => $filename,
                            'time' => @filemtime($filepath),
                            'size' => @filesize($filepath),
                            'totalpage' => $badge_info['totalpage'],
                            'page_order' => $badge_info['page_order'],
                            'viewer' => $badge_info['viewer'],
                            'is_video_archive' => $badge_info['is_video_archive']
                        ];
                    } elseif (preg_match('/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i', $filename)) {
                        $file_list[] = [
                            'name' => $filename,
                            'time' => @filemtime($filepath),
                            'size' => @filesize($filepath),
                            'type' => 'video'
                        ];
                    } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename) && substr($filename, -16) !== '.video_thumb.jpg') {
                        $jpg_list[] = $filename;
                    }
                }
            }
            
            // 캐시 저장
            $cache_data = [
                'version' => 3,  // ✅ 버전 추가
                'file_list' => $file_list,
                'dir_list' => $dir_list,
                'jpg_list' => $jpg_list,
                'title_list' => $title_list,
                'dirinfo' => $dirinfo,
                'total_items' => $files ? count($files) - 2 : 0,  // ✅ 파일 개수 저장 (Windows mtime 버그 대응)
                'mtime' => time()
            ];
            @file_put_contents($cache_file, json_encode($cache_data, JSON_UNESCAPED_UNICODE), LOCK_EX);
            $created++;
        }
    }
    return ['created' => $created, 'skipped' => $skipped, 'total' => $total];
}


// 함수 선언
function generate_all_zip_image_caches($base_dir, $force = false) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));
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
                        $zname = $zip->getNameIndex($i);
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

// ✅ 파일명.json + [cover].jpg 일괄 생성 함수
// [cover].jpg는 폴더 내 natsort ASC 순서로 첫 번째 ZIP에서 생성
function generate_all_zip_metadata($base_dir, $force = false) {
    $created = 0;
    $skipped = 0;
    $total = 0;
    
    // 1단계: 폴더별로 ZIP 파일 그룹화
    $folders = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));
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
            $json_file = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '.json', $zipfile);
            
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
                    $zname = $zip->getNameIndex($i);
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
                    
                    $img_data = $zip->getFromName($thumbnail_filename);
                    if ($img_data !== false) {
                        $img = @imagecreatefromstring($img_data);
                        if ($img) {
                            $w = imagesx($img);
                            $h = imagesy($img);
                            $new_h = 400;
                            $new_w = intval($w * ($new_h / $h));
                            
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

// 검색어 포함된 전체 폴더 검색 (메인페이지 + 하위폴더 모두)
if (!empty($q) && trim($q) !== '') {
    
    // 번역은 이미 249-265 라인에서 처리됨
    
    // ✅ 번역 안내 메시지 표시 (검색 결과 목록 위) - 하위폴더에서만
    if (!empty(get_param('dir', 'path'))) {
        if (!empty($translated_q)) {
            $display_translated = generateTranslationCombinations($q, $translated_q);
            echo "<div style='margin: 10px 8px; padding: 12px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;'>";
            echo "💡 " . __('search_also_searching', h($q), h($display_translated));
            echo "</div>";
        }
        elseif (!empty($reverse_translated_q)) {
            echo "<div style='margin: 10px 8px; padding: 12px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;'>";
            $display_reverse = is_array($reverse_translated_q) ? implode(', ', $reverse_translated_q) : $reverse_translated_q;
            echo "💡 " . __('search_also_searching', h($q), h($display_reverse));
            echo "</div>";
        }
    }
    
    // 검색 결과 표시
    show_search_results($q, $translated_q, $reverse_translated_q);
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
    
    // HTML 이스케이프 먼저 (ENT_COMPAT: 아포스트로피는 유지)
    $highlighted = htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
    
    // ✅ 각 검색어를 길이 순으로 정렬 (긴 것부터 먼저 매칭)
    $sorted_terms = $search_terms;
    usort($sorted_terms, function($a, $b) {
        return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
    });
    
    // 각 검색어에 대해 하이라이트 적용
    foreach ($sorted_terms as $term) {
        if (empty($term)) continue;
        
        // 검색어도 NFC 정규화
        $term = normalize_korean($term);
        
        // 대소문자 구분 없이 매칭 (u: UTF-8, i: case-insensitive)
        $pattern = '/(' . preg_quote($term, '/') . ')/ui';
        $highlighted = preg_replace(
            $pattern, 
            '<mark style="background-color: yellow; font-weight: bold;">$1</mark>', 
            $highlighted
        );
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

function show_search_results($q, $translated_q = [], $reverse_translated_q = []) {
    global $getdir, $bidx_param, $current_bidx, $base_dirs;
    
    // 모든 bidx의 search_index 파일 로드
    $index = [];
    foreach (array_keys($base_dirs) as $idx) {
        $index_path = __DIR__ . '/src/search_index_' . $idx . '.json';
        if (is_file($index_path)) {
            $idx_data = json_decode(file_get_contents($index_path), true);
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
    if (empty($index) && is_file($old_index_path)) {
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
                        'match_type' => $dir_matched ? 'dir' : 'file'
                    ];
                } elseif ($dir_matched && $matched_dirs[$dir_key]['match_type'] === 'file') {
                    // 폴더명 매칭이 발견되면 업데이트
                    $matched_dirs[$dir_key]['match_type'] = 'dir';
                }
                break;
            }
        }
    });
    
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
    
    echo "<div class='p-2'><h5>" . __('ui_search_folder_results', '<strong>' . htmlspecialchars(implode(' / ', $display_parts)) . '</strong>') . "</h5>";
    
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
            
            $encoded = urlencode("/" . $match_dir);
            
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
                $bidx_label = ' <span class="badge badge-secondary" style="vertical-align:middle; position:relative; top:-2px;">' . basename($base_dirs[$match_bidx]) . '</span>';
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
    $CACHE_VERSION = 3;
    
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
            if ($cache_version >= $CACHE_VERSION && $list_cache && isset($list_cache['file_list'], $list_cache['dir_list'], $list_cache['jpg_list'], $list_cache['title_list'], $list_cache['dirinfo'])) {
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
    // scandir 한 번!
    $all_files = scandir($dir, SCANDIR_SORT_NONE);
    foreach ($all_files as $filename) {
        if ($filename[0] === '.' || $filename === '@eaDir' || $filename === 'tmp' || $filename === 'robots.txt') continue;
        $filepath = $dir . '/' . $filename;

        $is_dir = is_dir($filepath);
        $is_file = !$is_dir;

        // 루트라면 권한 캐싱 (통합 JSON 사용)
        if ($is_root && $is_dir) {
            // 통합 권한 파일에서 권한 확인
            if (isset($all_permissions[$filename])) {
                $dirmode_arr = $all_permissions[$filename];
                if (($dirmode_arr[$_SESSION['user_group']] ?? 0) !== 1) continue;
            }
        }

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
    $cache = json_decode(file_get_contents($cache_file), true);
    $cached_count = $cache['total_items'] ?? -1;
    
    // ✅ mtime 비교 + 파일 개수 비교 (둘 다 일치해야 캐시 사용)
    if ($cache_mtime >= $folder_mtime && ($cached_count === -1 || $cached_count === $actual_count)) {
        // video_count가 없는 구버전 캐시도 갱신 대상
        if (isset($cache['jpg_count'], $cache['zip_count'], $cache['has_subdir'], $cache['video_count'], $cache['subdir_count'])) {
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
    if ($dh = @opendir($filepath)) {
        while (($sf = readdir($dh)) !== false) {
            if ($sf[0] === '.' || $sf === '@eaDir' || $sf === 'tmp' || $sf === 'robots.txt') continue;
            $sf_full = $filepath . '/' . $sf;

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

            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $sf) && !str_ends_with($sf, '.video_thumb.jpg')) {
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

            if ($jpg_count > 10) break; // 5에서 10으로 늘려서 더 정확한 카운트
        }
        closedir($dh);
    }

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
        'total_items' => $actual_count,  // ✅ 파일 개수 저장 (Windows mtime 버그 대응)
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
    $json_file = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7|pdf|txt|epub|hwpx?|docx?|xlsx?|pptx?)$/i', '.json', $filepath);
    $badge_info = ['totalpage' => 0, 'page_order' => '0', 'viewer' => 'toon', 'is_video_archive' => false];
    if (is_file($json_file)) {
        $json_data = @json_decode(file_get_contents($json_file), true);
        if ($json_data) {
            $badge_info['totalpage'] = $json_data['totalpage'] ?? 0;
            $badge_info['page_order'] = $json_data['page_order'] ?? '0';
            $badge_info['viewer'] = $json_data['viewer'] ?? 'toon';
            $badge_info['is_video_archive'] = $json_data['is_video_archive'] ?? false;
        }
    }
    $file_list[] = [
        'name' => $filename,
        'time' => @filemtime($filepath),
        'size' => @filesize($filepath),
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
        'total_items' => $all_files ? count($all_files) - 2 : 0,  // ✅ 파일 개수 저장 (Windows mtime 버그 대응)
        'mtime' => time()
    ];
    @file_put_contents($list_cache_file, json_encode($list_cache_data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    } // ✅ if (!$use_list_cache) 닫기
    
    $_t2 = microtime(true);

    // 정렬 - 기본 순서: 숫자 → 영문 → 한글 (ASC)
    natsort($dir_list);
    natsort($title_list);
    natsort($jpg_list);
    
    $jpg_list = array_values($jpg_list);
    $dir_list = array_values($dir_list);
    $title_list = array_values($title_list);

    // 파일 리스트 정렬(구조 유지)
    $sort_mode = validate_sort_mode(get_param('sort', 'string', 'nameasc'));
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
    $startview = $paging * $current_maxview;
    $endview = min($startview + $current_maxview, $maxlist);
    $updir = "";
} else {
	echo __("ui_dir_not_readable") . "<br>"; 
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
    
    $basename = pathinfo($name, PATHINFO_FILENAME);
    
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
    
    // 중복 제거
    if (isset($basename_map[$basename])) continue;
    $filtered_file_list[] = is_array($file) ? $file : ['name' => $file];
    $basename_map[$basename] = true;
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

	<div>
	<br>
	<table class="table table-borderless m-0 p-0" width="100%">
	<tr>
	<td class="m-0 p-0 align-middle" align="left">
<?php if(($_branding['logo_type'] ?? 'text') === 'image' && !empty($_branding['logo_image']) && file_exists($_branding['logo_image'])): ?>
		<div><a href="index.php<?php echo $bidx_query; ?>" class="logo-link" style="display:inline-block;"><img src="<?php echo h($_branding['logo_image']); ?>" alt="<?php echo h($_branding['logo_text']); ?>" style="max-height:2.5em; max-width:200px;"></a><span style="font-size:10px; color:#999; margin-left:4px; position:relative; top:-23px;"><?php echo MYCOMIX_VERSION; ?></span><?php render_lang_badge('xl'); ?></div>
<?php else: ?>
		<div style="font-family: 'Gugi'; font-size:2.5em;"><a href="index.php<?php echo $bidx_query; ?>" class="logo-link"><?php echo h($_branding['logo_text'] ?? '마이코믹스'); ?></a><span style="font-size:10px; color:#999; margin-left:4px; position:relative; top:-23px;"><?php echo MYCOMIX_VERSION; ?></span><?php render_lang_badge('xl'); ?></div>
<?php endif; ?>

<?php // 다중 폴더 선택 - 아래 [전체파일] 옆에 드롭다운으로 표시
// 여기서는 제거하고 아래로 이동
?>
	</td>
	<td class="m-0 p-0 align-middle" align="right">	
<?php
$bookmark_arr = array();
$bookmark_title = array();
$bookmark_mark = array();
$autosave_arr = array();
$autosave_title = array();
$autosave_mark = array();
$favorites_arr = array();
$favorites_title = array();
$favorites_mark = array();

// ✅ 함수 사용으로 통일 (전역 변수 의존 제거)
// EPUB/TXT 진행 정보 로드
$epub_progress_file = get_epub_progress_file();
$txt_progress_file = get_txt_progress_file();
$epub_progress = [];
$txt_progress = [];
if (is_file($epub_progress_file)) {
    $epub_progress = json_decode(file_get_contents($epub_progress_file), true) ?? [];
}
if (is_file($txt_progress_file)) {
    $txt_progress = json_decode(file_get_contents($txt_progress_file), true) ?? [];
}

// ✅ 함수 사용으로 통일 (전역 변수 의존 제거)
$_bookmark_file = get_bookmark_file();
$_autosave_file = get_autosave_file();
$_favorites_file = get_favorites_file();
if(is_file($_bookmark_file) === true){
	$bookmark_arr = json_decode(file_get_contents($_bookmark_file), true) ?? [];
	$bookmark_title = array_keys($bookmark_arr);
	$bookmark_mark = array_values($bookmark_arr);
}
if(is_file($_autosave_file) === true){
	$autosave_arr = json_decode(file_get_contents($_autosave_file), true) ?? [];
	$autosave_title = array_keys($autosave_arr);
	$autosave_mark = array_values($autosave_arr);
}
if(is_file($_favorites_file) === true){
	$favorites_arr = json_decode(file_get_contents($_favorites_file), true) ?? [];
	$favorites_title = array_keys($favorites_arr);
	$favorites_mark = array_values($favorites_arr);
}
	if(count($bookmark_arr) > 0 || count($autosave_arr) > 0 || count($epub_progress) > 0 || count($txt_progress) > 0 || count($favorites_arr) > 0){
?>
		<button class="dropdown-toggle btn btn-sm m-0 p-0" onclick="bookmark_toggle();">
		<svg width="2em" height="2em" viewBox="0 0 16 16" class="bi bi-bookmark-check-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
		<path fill-rule="evenodd" d="M4 0a2 2 0 0 0-2 2v13.5a.5.5 0 0 0 .74.439L8 13.069l5.26 2.87A.5.5 0 0 0 14 15.5V2a2 2 0 0 0-2-2H4zm6.854 5.854a.5.5 0 0 0-.708-.708L7.5 7.793 6.354 6.646a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0l3-3z"/>
		</svg>
		</button>
<?php
	}
?>
</td></tr></table>

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
if(count($autosave_arr)>0){
for($count=0;$count < count($autosave_arr); $count++){
	$title_temp = explode("/", $autosave_title[$count]);
?>
	<tr class="border-bottom border-success"><td align=right style="white-space:nowrap;"><button class="btn btn-sm m-0 p-0"><?php echo $_svg_warn; ?></button><button class="btn btn-sm m-0 p-0" onclick="location.replace('bookmark.php?mode=delete_autosave&file=<?php echo encode_url($autosave_title[$count]) . $bidx_param; ?>&token=<?php echo get_delete_token(); ?>');"><?php echo $_svg_x; ?></button></td><td><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./viewer.php?mode=toon&page_order=<?php echo $autosave_mark[$count]['page_order']; ?>&file=<?php echo encode_url($autosave_title[$count]); ?>&bidx=<?php echo $autosave_mark[$count]['bidx'] ?? 0; ?>#<?php echo $autosave_mark[$count]['bookmark']; ?>'"><?php echo cut_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
	}
	}

if(count($bookmark_arr)>0){
for($count=0;$count < count($bookmark_arr); $count++){
	$title_temp = explode("/", $bookmark_title[$count]);
?>
	<tr class="border-bottom border-light"><td align=right style="white-space:nowrap;"><button class="btn btn-sm m-0 p-0" onclick="location.replace('bookmark.php?mode=delete_bookmark&file=<?php echo encode_url($bookmark_title[$count]) . $bidx_param; ?>&token=<?php echo get_delete_token(); ?>');"><?php echo $_svg_x; ?></button></td><td><?php
	if(!is_array($bookmark_mark[$count])){
?><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./viewer.php?file=<?php echo encode_url($bookmark_title[$count]) . $bidx_param; ?>#<?php echo $bookmark_mark[$count]; ?>'"><?php echo cut_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
	} else {
?><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./viewer.php?mode=toon&page_order=<?php echo $bookmark_mark[$count]['page_order']; ?>&file=<?php echo encode_url($bookmark_title[$count]); ?>&bidx=<?php echo $bookmark_mark[$count]['bidx'] ?? 0; ?>#<?php echo $bookmark_mark[$count]['bookmark']; ?>'"><?php echo cut_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
	}
	}
}

// EPUB 진행 목록
if(count($epub_progress) > 0){
    foreach($epub_progress as $file_path => $progress_data){
        $title_temp = explode("/", $file_path);
        $percent = (int)($progress_data['percent'] ?? 0);
?>
	<tr class="border-bottom" style="border-color:#9b59b6 !important;"><td align=right style="white-space:nowrap;"><span class="badge badge-info" style="font-size:0.7em;"><?php echo $percent; ?>%</span><button class="btn btn-sm m-0 p-0" onclick="location.replace('bookmark.php?mode=delete_epub_progress&file=<?php echo encode_url($file_path) . $bidx_param; ?>&token=<?php echo get_delete_token(); ?>');"><?php echo $_svg_x; ?></button></td><td><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./epub_viewer.php?file=<?php echo encode_url($file_path) . $bidx_param; ?>'">📖 <?php echo cut_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
    }
}

// TXT 진행 목록
if(count($txt_progress) > 0){
    foreach($txt_progress as $file_path => $progress_data){
        $title_temp = explode("/", $file_path);
        $percent = (int)($progress_data['percent'] ?? 0);
?>
	<tr class="border-bottom" style="border-color:#27ae60 !important;"><td align=right style="white-space:nowrap;"><span class="badge badge-success" style="font-size:0.7em;"><?php echo $percent; ?>%</span><button class="btn btn-sm m-0 p-0" onclick="location.replace('bookmark.php?mode=delete_txt_progress&file=<?php echo encode_url($file_path) . $bidx_param; ?>&token=<?php echo get_delete_token(); ?>');"><?php echo $_svg_x; ?></button></td><td><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./txt_viewer.php?file=<?php echo encode_url($file_path) . $bidx_param; ?>'">📝 <?php echo cut_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
    }
}

// ✅ 즐겨찾기 목록
$_fav_max = (int)get_app_settings('max_favorites', 50);
?>
<tr id="favCountRow"><td colspan="2"><small class="text-muted">⭐ <span id="favCount"><?php echo count($favorites_arr); ?></span>/<?php echo $_fav_max; ?></small></td></tr>
<?php
if(count($favorites_arr) > 0){
    foreach($favorites_arr as $file_path => $fav_data){
        $title_temp = explode("/", $file_path);
        $fav_bidx = $fav_data['bidx'] ?? 0;
?>
	<tr class="border-bottom" style="border-color:#f39c12 !important;" data-fav-path="<?php echo h($file_path); ?>"><td align=right style="white-space:nowrap;"><span class="badge badge-warning" style="font-size:0.7em;">⭐</span><button class="btn btn-sm m-0 p-0" onclick="removeFavoriteClick(this, '<?php echo addslashes($file_path); ?>', <?php echo $fav_bidx; ?>);"><?php echo $_svg_x; ?></button></td><td><button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="window.myComixMarkNavigation&&myComixMarkNavigation();location.href='./<?php 
        // 파일 타입에 따라 다른 뷰어로 이동
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (in_array($ext, ['zip', 'cbz', 'rar', 'cbr', '7z', 'cb7'])) {
            echo "viewer.php?mode=toon&file=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        } elseif ($ext === 'epub') {
            echo "epub_viewer.php?file=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        } elseif ($ext === 'txt') {
            echo "txt_viewer.php?file=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        } else {
            // 폴더인 경우
            echo "index.php?dir=" . encode_url($file_path) . "&bidx=" . $fav_bidx;
        }
    ?>'">⭐ <?php echo cut_title($title_temp[count($title_temp) - 1]); ?></button></td></tr>
<?php
    }
}
?>
</table>
</div>

<table class="table table-borderless m-0 p-0" width="100%">
<tr>
  <td class="m-0 p-0" align="left">
    <span class="badge badge-light badge-sm" style="font-family: 'Nanum Gothic', sans-serif;">
      <?php echo __('index_logged_in_as', $_SESSION['user_id']); ?>
    </span>
    <?php if ($_SESSION['user_group'] === "admin") {
      echo '<a class="badge badge-primary badge-sm" href="admin.php" style="color:#fff;">' . __h('index_admin_page') . '</a> ';
      
      // ✅ 승인 대기 사용자 알림
      $pending_users = [];
      $all_users = load_users();
      foreach ($all_users as $uid => $udata) {
          if (($udata['status'] ?? 'active') === 'pending') {
              $pending_users[] = $uid;
          }
      }
      if (count($pending_users) > 0) {
          echo '<a class="badge badge-warning badge-sm pending-alert" href="admin.php#group" title="' . __h('users_approve') . '" style="animation:pulse 1.5s infinite;">' . __('index_pending_approval', count($pending_users)) . '</a> ';
      }
    } ?>
    <a class="badge badge-danger badge-sm" href="login.php?mode=logout"><?php echo __h('index_logout'); ?></a>
    <?php if ($logout_all_devices_settings['enabled'] ?? false): ?>
    <a class="badge badge-warning badge-sm" href="login.php?mode=logout_all" onclick="return confirm('<?php echo __('index_logout_all_confirm'); ?>');"><?php echo __h('index_logout_all'); ?></a>
    <?php endif; ?>
    <a class="badge badge-info badge-sm" href="#" onclick="openProfileModal(); return false;" title="<?php echo __h('index_my_profile'); ?>" style="color:#fff !important;"><?php echo __('index_my_profile'); ?></a>
    <a class="badge badge-success badge-sm" href="#" onclick="openSettingsModal(); return false;" title="<?php echo __h('index_2fa_settings'); ?>" style="color:#fff;"><?php echo __('index_2fa_settings'); ?></a>

    <br><br>
  </td>
</tr>

<?php
if ($use_cover == "y" && !empty($getdir)){
	if(is_file($dir."/[cover].jpg") == true) {
		echo "<tr><td class=\"m-0 p-0\"><img class=\"folder-cover border border-white rounded-lg mt-2 mb-2 p-0\" src=\"thumb.php?file=".encode_url($getdir)."&type=cover".$bidx_param."\" loading=\"lazy\"></td></tr>";
	} else {
		echo "<tr><td class=\"m-0 p-0\"><img class=\"folder-cover border border-white rounded-lg mt-2 mb-2 p-0\" src=\"data:image/gif;base64,".$null_image."\"></td></tr>";
	}
}
?>
<!--// 해당 $dir 없을때 표시, 해당 $dir 없을때 표시 -->
<?php if (empty(get_param('dir', 'path'))): ?>
<tr><td class="m-0" style="padding: 2px 0;">
    <div style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">
        <span style="background:#dc3545; color:#fff; padding:5px 10px; border-radius:4px; font-size:14px; font-family:'Nanum Gothic',sans-serif;"><?php echo __h('index_all_files'); ?></span>
        <?php
        // 모든 bidx의 zip_total 합산
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
        ?>
        <span style="background:#ffc107; color:#212529; padding:5px 10px; border-radius:4px; font-size:14px; line-height:1; display:inline-flex; align-items:center;">📦 <?php echo number_format($zip_total); ?></span>
        <?php // 베이스 폴더 드롭다운 (2개 이상일 때만)
        if (count($base_dirs) > 1): ?>
        <select style="background:#6c757d; color:#fff; border:none; padding:2px 10px 8px 10px; border-radius:4px; font-size:14px; cursor:pointer; height:28px; line-height:1;" onchange="location.href='index.php?bidx=' + this.value">
        <?php foreach ($base_dirs as $idx => $bdir): ?>
            <option value="<?php echo $idx; ?>" <?php echo ($idx === $current_bidx) ? 'selected' : ''; ?>>📁 <?php echo h(basename($bdir)); ?></option>
        <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>
</td></tr>
<?php endif; ?>
<!--// 해당 $dir 없을때 표시, 해당 $dir 없을때 표시 -->
<!--// 해당 $dir 있을때 표시 -->
<?php if (!empty(get_param('dir', 'path'))): ?>
<tr><td class="m-0" style="padding: 2px 0;">
    <div style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">
        <span style="background:#dc3545; color:#fff; padding:5px 10px; border-radius:4px; font-size:14px; font-family:'Nanum Gothic',sans-serif;">[<?php echo h($getdir);?>]</span>
        <span style="background:#ffc107; color:#212529; padding:5px 10px; border-radius:4px; font-size:14px; line-height:1; display:inline-flex; align-items:center;">📦 <?php echo count($file_list); ?></span>
        <?php // 베이스 폴더 드롭다운 (2개 이상일 때만)
        if (count($base_dirs) > 1): ?>
        <select style="background:#6c757d; color:#fff; border:none; padding:2px 10px 8px 10px; border-radius:4px; font-size:14px; cursor:pointer; height:28px; line-height:1;" onchange="location.href='index.php?bidx=' + this.value">
        <?php foreach ($base_dirs as $idx => $bdir): ?>
            <option value="<?php echo $idx; ?>" <?php echo ($idx === $current_bidx) ? 'selected' : ''; ?>>📁 <?php echo h(basename($bdir)); ?></option>
        <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>
</td></tr>
<?php endif; ?>
<!--// 해당 $dir 있을때 표시 -->
<?php
		if ($getdir !== '' && isset($recent[$getdir]) && $recent[$getdir] !== null) {
?>
<tr><td class="m-0" style="padding: 2px 0;">
                 <button class="btn btn-warning btn-sm" style="font-family: 'Nanum Gothic', sans-serif;" onclick="location.href='./viewer.php?file=<?php echo encode_url($getdir."/".$recent[$getdir]) . $bidx_param; ?>'"><?php echo __('index_read_upto', $recent[$getdir]); ?></button>
</td></tr>
<?php
			} else {
			}
?>
</table>
	<br>
	</div>
<div class="grid">

<!-- 루트 폴더용 wrapper (모바일에서 순서 변경) -->
<div class="root-folder-wrapper">
<!--// 해당 $dir 없을때 검색기능 -->
<?php if (empty(get_param('dir', 'path'))): ?>
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
                <button type="submit" class="btn btn-success" style="border-radius: 0 4px 4px 0; height: 40px;">
                    🔍 <?php echo __h("common_search"); ?>
                </button>
            </div>
        </div>
    </form>
    
    <?php 
    // ✅ 번역 안내 메시지 (검색창 아래)
    // 정방향: 한글 → 영어
    if (!empty(get_param('q', 'search')) && !empty($translated_q)): 
        $display_translated = generateTranslationCombinations($q, $translated_q);
    ?>
        <div style='margin: 0 auto 15px; padding: 10px 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; max-width: 500px; text-align: left;'>
            💡 <?php echo __('search_also_searching', h($q), htmlspecialchars($display_translated, ENT_QUOTES)); ?>
        </div>
    <?php 
    // 역방향: 영어 → 한글
    elseif (!empty(get_param('q', 'search')) && !empty($reverse_translated_q)): 
        $display_reverse = is_array($reverse_translated_q) ? implode(', ', $reverse_translated_q) : $reverse_translated_q;
    ?>
        <div style='margin: 0 auto 15px; padding: 10px 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; max-width: 500px; text-align: left;'>
            💡 <?php echo __('search_also_searching', h($q), htmlspecialchars($display_reverse, ENT_QUOTES)); ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<!--// 해당 $dir 없을때 검색기능 -->


<?php if($has_folders || $has_files || ($is_admin && !(isset($is_root) && $is_root == true))): ?>
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
<table width=100%><tr><td align=left style="padding-left:12px;">
<?php if (!(isset($is_root) && $is_root == true)): ?>
<button class="btn btn-primary m-1" onclick="location.replace('index.php?dir=<?php echo encode_url($updir_for_sort); ?>&page=<?php echo validate_page(get_param('uppage', 'int', 0)) . $bidx_param; ?>')">
<svg width="1.5em" height="1.5em" viewBox="0 0 16 16" class="bi bi-arrow-90deg-up" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
<path fill-rule="evenodd" d="M4.854 1.146a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L4 2.707V12.5A2.5 2.5 0 0 0 6.5 15h8a.5.5 0 0 0 0-1h-8A1.5 1.5 0 0 1 5 12.5V2.707l3.146 3.147a.5.5 0 1 0 .708-.708l-4-4z"/>
</svg> <?php echo __h("ui_parent_folder"); ?>
</button>
<?php endif; ?>
			</td><td align=right class="sort-buttons">
<?php
$sort = validate_sort_mode(get_param('sort', 'string', 'nameasc'));
$page = validate_page(get_param('page', 'int', 0));

// URL 파라미터 구성
$base_params = array_filter([
    'dir' => get_param('dir', 'path', ''),
    'q' => $q,
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

<?php if ($is_admin): ?>
<!-- 관리자 전용 버튼 (모바일/태블릿 숨김) -->
<button type="button" class="btn btn-sm btn-outline-success ml-2 p-1 d-none d-lg-inline-block" onclick="openCreateFolderModal()" title="<?php echo __h('ui_new_folder'); ?>">
    📁+
</button>
<?php if (!(isset($is_root) && $is_root == true)): ?>
<!-- 파일 업로드는 하위 폴더에서만 -->
<button type="button" class="btn btn-sm btn-outline-primary ml-1 p-1 d-none d-lg-inline-block" onclick="openUploadModal()" title="<?php echo __h('ui_upload_file'); ?>">
    📤
</button>
<?php endif; ?>
<!-- 삭제 모드 토글 -->
<button type="button" id="deleteModeBtn" class="btn btn-sm btn-outline-danger ml-1 p-1" onclick="toggleDeleteMode()" title="<?php echo __h('ui_delete_mode'); ?>">
    🗑️
</button>
<?php endif; ?>

</td></tr>
</table>
</div>
<?php endif; ?>
</div><!-- // root-folder-wrapper -->

<!--// 해당 $dir에서 검색기능 (상위폴더+정렬버튼 아래) -->
<?php if (!empty(get_param('dir', 'path'))): ?>
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
                >
                    🔍 <?php echo __h("common_search"); ?>
                </button>
            </div>
        </div>
    </form>

    <?php 
    // ✅ 번역 안내 메시지 (검색창 아래)
    // 정방향: 한글 → 영어
    if (!empty(get_param('q', 'search')) && !empty($translated_q)): 
        $display_translated2 = generateTranslationCombinations($q, $translated_q);
    ?>
        <div style='margin: 0 auto 15px; padding: 10px 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; max-width: 500px; text-align: left;'>
            💡 <?php echo __('search_also_searching', h($q), htmlspecialchars($display_translated2, ENT_QUOTES)); ?>
        </div>
    <?php 
    // 역방향: 영어 → 한글
    elseif (!empty(get_param('q', 'search')) && !empty($reverse_translated_q)): 
        $display_reverse2 = is_array($reverse_translated_q) ? implode(', ', $reverse_translated_q) : $reverse_translated_q;
    ?>
        <div style='margin: 0 auto 15px; padding: 10px 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; max-width: 500px; text-align: left;'>
            💡 <?php echo __('search_also_searching', h($q), htmlspecialchars($display_reverse2, ENT_QUOTES)); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty(get_param('q', 'search')) && empty($filtered_file_list)): ?>
        <div style="text-align: center; padding: 40px 20px;">
            <div style="color: #dc3545; font-size: 1.3em; font-weight: bold; margin-bottom: 10px;">
                😕 <?php echo __h('ui_no_search_results'); ?>
            </div>
            <div style="color: #6c757d;">
                <?php echo __('ui_file_search_not_found', h($q)); ?>
            </div>
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

if (!empty($title_list)) {
    if ($sort == 'namedesc') {
        rsort($title_list, SORT_NATURAL | SORT_FLAG_CASE);
    } elseif ($sort == 'timeasc') {
        // 폴더 생성시간으로 정렬 (오름차순)
        $title_with_time = array();
        foreach ($title_list as $title) {
            $folder_path = $dir . "/" . str_replace("00000000.", "", $title);
            $title_with_time[] = array(
                'name' => $title,
                'time' => is_dir($folder_path) ? filemtime($folder_path) : 0
            );
        }
        usort($title_with_time, function($a, $b) {
            return $a['time'] - $b['time'];
        });
        $title_list = array_column($title_with_time, 'name');
    } elseif ($sort == 'timedesc') {
        // 폴더 생성시간으로 정렬 (내림차순)
        $title_with_time = array();
        foreach ($title_list as $title) {
            $folder_path = $dir . "/" . str_replace("00000000.", "", $title);
            $title_with_time[] = array(
                'name' => $title,
                'time' => is_dir($folder_path) ? filemtime($folder_path) : 0
            );
        }
        usort($title_with_time, function($a, $b) {
            return $b['time'] - $a['time'];
        });
        $title_list = array_column($title_with_time, 'name');
    } elseif ($sort == 'countasc') {
        // 폴더 내 파일 개수로 정렬 (오름차순) - ✅ 캐시 활용
        $title_with_count = array();
        foreach ($title_list as $title) {
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
        $title_list = array_column($title_with_count, 'name');
    } elseif ($sort == 'countdesc') {
        // 폴더 내 파일 개수로 정렬 (내림차순) - ✅ 캐시 활용
        $title_with_count = array();
        foreach ($title_list as $title) {
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
        $title_list = array_column($title_with_count, 'name');
    } else {
        // 기본 정렬 (이름 오름차순)
        sort($title_list, SORT_NATURAL | SORT_FLAG_CASE);
    }
}
// title_list 정렬 처리
?>



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
<div style="width: 100%; text-align: center; padding: 80px 20px;">
    <div style="color: #dc3545; font-size: 1.5em; font-weight: bold; margin-bottom: 15px;">
        😕 <?php echo __h('ui_no_search_results'); ?>
    </div>
    <div style="color: #6c757d; font-size: 1.1em;">
        <?php echo __('ui_folder_search_not_found', '<strong>' . h($q) . '</strong>'); ?>
    </div>
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
    <div class="card border-secondary m-1 p-0" <?php if ($is_admin): ?>data-folder-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
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
		echo "<img class=\"rounded-lg mb-2\" src=\"thumb.php?file=".encode_url($getdir."/".$fileinfo)."&type=cover".$bidx_param."\" style=\"height:120px;max-width:100%;min-width:100%;object-fit:contain;\" loading=\"lazy\"><br>";
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
$actual_items = @scandir($folder_path);
$actual_count = $actual_items ? count($actual_items) - 2 : 0;

if (is_file($cache_file)) {
    $cache = json_decode(file_get_contents($cache_file), true);
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
                if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $sf) && !preg_match('/\[cover\]/i', $sf)) $pure_image_count++;
            }
        }
        closedir($dh);
        
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
            'total_items' => $actual_count,  // ✅ 파일 개수 저장 (Windows mtime 버그 대응)
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
					<span class="emoji-icon"><?php echo $folder_icon; ?></span>
					</svg> <?php 
$all_terms = isset($GLOBALS["all_highlight_terms"]) && is_array($GLOBALS["all_highlight_terms"]) ? $GLOBALS["all_highlight_terms"] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo highlight_search($fileinfo, $all_terms);
?>
    <?php if ($zip_count > 0): ?>
        <span class="badge badge-warning"><span class="emoji-icon"><?php echo $badge_icon; ?></span><?php echo $zip_count; ?></span>
    <?php endif; ?>
    <?php if ($video_count > 0): ?>
        <span class="badge badge-info"><span class="emoji-icon">🎬</span><?php echo $video_count; ?></span>
    <?php endif; ?>
    <?php if (isset($subdir_count) && $subdir_count > 0): ?>
        <span class="badge badge-warning"><span class="emoji-icon">📁</span><?php echo $subdir_count; ?></span>
    <?php endif; ?>
    <?php if ($pure_image_count > 0 && $zip_count == 0 && $video_count == 0 && (!isset($subdir_count) || $subdir_count == 0)): ?>
        <span class="badge badge-warning badge-imagecount"><span class="emoji-icon">🖼️</span><?php echo $pure_image_count; ?></span>
    <?php endif; ?>
<?php
// ✅ NEW 딱지 표시 (폴더 내 가장 최신 파일 기준)
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0 && $newest_mtime > 0) {
    if ((time() - $newest_mtime) < ($_new_badge_hours * 3600)) {
        echo '<span class="badge badge-danger">N</span>';
    }
}
?>
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
	    <div class="card bg-secondary text-white text-left m-1 p-0" <?php if ($is_admin): ?>data-folder-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
				<div class="card-body m-1 p-1 d-inline-block text-truncate text-nowrap">
<?php

if($use_listcover == "y"){
	if(is_file($dir."/".$fileinfo."/[cover].jpg")){
		echo "<img class=\"rounded-lg mb-2\" src=\"thumb.php?file=".encode_url($getdir."/".$fileinfo)."&type=cover".$bidx_param."\" style=\"height:120px;max-width:100%;min-width:100%;object-fit:contain;\" loading=\"lazy\"><br>";
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
$actual_items = @scandir($folder_path);
$actual_count = $actual_items ? count($actual_items) - 2 : 0;

if (is_file($cache_file)) {
    $cache = json_decode(file_get_contents($cache_file), true);
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
                if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $sf) && !preg_match('/\[cover\]/i', $sf)) $pure_image_count++;
            }
        }
        closedir($dh);
        
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
            'total_items' => $actual_count,  // ✅ 파일 개수 저장 (Windows mtime 버그 대응)
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
					<span class="emoji-icon"><?php echo $folder_icon; ?></span>
<?php
?>
</svg> <?php 
$all_terms = isset($GLOBALS["all_highlight_terms"]) && is_array($GLOBALS["all_highlight_terms"]) ? $GLOBALS["all_highlight_terms"] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo highlight_search($fileinfo, $all_terms);
?> 
    <?php if ($zip_count > 0): ?>
        <span class="badge badge-warning"><span class="emoji-icon"><?php echo $badge_icon; ?></span><?php echo $zip_count; ?></span>
    <?php endif; ?>
    <?php if ($video_count > 0): ?>
        <span class="badge badge-info"><span class="emoji-icon">🎬</span><?php echo $video_count; ?></span>
    <?php endif; ?>
    <?php if (isset($subdir_count) && $subdir_count > 0): ?>
        <span class="badge badge-warning"><span class="emoji-icon">📁</span><?php echo $subdir_count; ?></span>
    <?php endif; ?>
    <?php if ($pure_image_count > 0 && $zip_count == 0 && $video_count == 0 && (!isset($subdir_count) || $subdir_count == 0)): ?>
        <span class="badge badge-warning badge-imagecount"><span class="emoji-icon">🖼️</span><?php echo $pure_image_count; ?></span>
    <?php endif; ?>
<?php
// ✅ NEW 딱지 표시 (폴더 내 가장 최신 파일 기준)
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0 && $newest_mtime > 0) {
    if ((time() - $newest_mtime) < ($_new_badge_hours * 3600)) {
        echo '<span class="badge badge-danger">N</span>';
    }
}
?>

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
    $json_file = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '.json', $zip_file);
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
    $has_video_in_imgfolder = false;
    if (is_dir($zip_file)) {
        $video_check_cache = $zip_file . '/.folder_cache.json';
        if (is_file($video_check_cache)) {
            $vc = @json_decode(file_get_contents($video_check_cache), true);
            if ($vc && isset($vc['video_count']) && $vc['video_count'] > 0) {
                $has_video_in_imgfolder = true;
            }
        } else {
            // 캐시 없으면 직접 체크
            foreach (scandir($zip_file) as $f) {
                if (is_video_file($f)) {
                    $has_video_in_imgfolder = true;
                    break;
                }
            }
        }
    }
    
    // 동영상이 있으면 이 블록 건너뛰기 (동영상 폴더로 처리해야 함)
    if ($has_video_in_imgfolder) {
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
            if(preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename) && !str_ends_with($filename, '.video_thumb.jpg')) {
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
                
                if($size && $size[0] > $size[1]) {
                    $x_point = ($size[0]/2) - $size[1];
                    $originimage = imagecreatefromstring($jpg_cover);
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
                    $originimage = imagecreatefromstring($jpg_cover);
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
                    @file_put_contents($zip_file."/[cover].jpg", $cropimage, LOCK_EX);
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
        $json_data = json_decode(file_get_contents($configfile), true) ?? [];
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
    <a href='viewer.php?filetype=images&mode=<?php echo $viewer; ?>&file=<?php echo encode_url($getdir."/".str_replace("_imgfolder","", $fileinfo)) . $bidx_param; ?>'>
      <div class="col mb-3">
        <div class="card text-black m-0 p-1" style="position:relative;" <?php if ($is_admin): ?>data-folder-path="<?php echo h($getdir."/".str_replace("_imgfolder","", $fileinfo)); ?>"<?php endif; ?>>
            <img src="thumb.php?file=<?php echo encode_url($getdir."/".str_replace("_imgfolder","", $fileinfo)) . $bidx_param; ?>" class="rounded card-img-top card-img" alt="thumbnail" loading="lazy">
<?php 
// ✅ 즐겨찾기 버튼
$_fav_file_path = $getdir."/".str_replace("_imgfolder","", $fileinfo);
$_is_favorite = isset($favorites_arr[$_fav_file_path]);
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        onclick="toggleFavorite(this, '<?php echo addslashes($_fav_file_path); ?>', <?php echo $bidx; ?>, event)"
        title="<?php echo $_is_favorite ? __h('ui_unfavorite') : __h('ui_add_favorite'); ?>">
    <?php echo $_is_favorite ? '⭐' : '⭐'; ?>
</button>
                        <div class="card-img-overlay m-1 p-0">
						<span class="badge badge-pill badge-success"><?php echo h($totalpage); ?>p</span>
						<span class="badge badge-pill badge-success"><?php echo h($pageorder); ?></span>
						<span class="badge badge-pill badge-success"><?php echo h($viewer); ?></span>
<?php 
// ✅ NEW 딱지 표시 (이미지 폴더 - 폴더 내 가장 최신 파일 기준)
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0) {
    $_imgfolder_path = $dir . "/" . str_replace("_imgfolder", "", $fileinfo);
    $_imgfolder_newest_mtime = 0;
    
    // 캐시에서 newest_mtime 확인
    $_imgfolder_cache_file = $_imgfolder_path . '/.folder_cache.json';
    if (is_file($_imgfolder_cache_file)) {
        $_imgfolder_cache = @json_decode(file_get_contents($_imgfolder_cache_file), true);
        $_imgfolder_newest_mtime = (int)($_imgfolder_cache['newest_mtime'] ?? 0);
    }
    
    // 캐시에 없으면 폴더 내 파일 중 최신 mtime 확인
    if ($_imgfolder_newest_mtime == 0 && is_dir($_imgfolder_path)) {
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
                <span class="emoji-icon">🖼️</span> <?php 
$img_folder_name = preg_replace("/_imgfolder$/", "", $fileinfo);
$all_terms = isset($GLOBALS["all_highlight_terms"]) && is_array($GLOBALS["all_highlight_terms"]) ? $GLOBALS["all_highlight_terms"] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo highlight_search($img_folder_name, $all_terms);
?>
            </div>
        </div>
      </div>
    </a>
    <?php
} elseif(strpos(strtolower($zip_file), ".pdf") == true){
		// PDF 썸네일 확인 (파일명.jpg)
		$pdf_thumb_file = '';
		$pdf_base_name = preg_replace('/\.pdf$/i', '', $zip_file);
		$pdf_possible_thumbs = [
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
$_is_favorite = isset($favorites_arr[$_fav_file_path]);
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        onclick="toggleFavorite(this, '<?php echo addslashes($_fav_file_path); ?>', <?php echo $bidx; ?>, event)"
        title="<?php echo $_is_favorite ? __h('ui_unfavorite') : __h('ui_add_favorite'); ?>">
    <?php echo $_is_favorite ? '⭐' : '⭐'; ?>
</button>
<?php if (!empty($pdf_thumb_file)): ?>
<?php $pdf_thumb_data = base64_encode(file_get_contents($pdf_thumb_file)); $pdf_thumb_mime = mime_content_type($pdf_thumb_file); ?>
<img src="data:<?php echo $pdf_thumb_mime; ?>;base64,<?php echo $pdf_thumb_data; ?>" class="rounded card-img-top card-img loaded" style="height:200px; object-fit:cover;" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
<div class="rounded card-img-top card-img-placeholder align-items-center justify-content-center" style="height:200px; background:#f8f9fa; display:none; opacity:1;">
<svg viewBox="0 0 56 56" style="width:80px;height:80px;" xmlns="http://www.w3.org/2000/svg"><g><path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/><polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/><path style="fill:#CC4B4C;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/><text x="28" y="51" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="bold" fill="#FFFFFF" text-anchor="middle">PDF</text><path style="fill:#CC4B4C;" d="M15,18h26v2H15V18z M15,24h26v2H15V24z M15,30h20v2H15V30z"/></g></svg>
</div>
<?php else: ?>
<div class="rounded card-img-top card-img-placeholder d-flex align-items-center justify-content-center" style="height:200px; background:#f8f9fa; opacity:1;">
<svg viewBox="0 0 56 56" style="width:80px;height:80px;" xmlns="http://www.w3.org/2000/svg"><g><path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/><polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/><path style="fill:#CC4B4C;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/><text x="28" y="51" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="bold" fill="#FFFFFF" text-anchor="middle">PDF</text><path style="fill:#CC4B4C;" d="M15,18h26v2H15V18z M15,24h26v2H15V24z M15,30h20v2H15V30z"/></g></svg>
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
							<span class="emoji-icon">📄</span> <?php 
$pdf_display_name = preg_replace("/\.pdf$/i", "", $fileinfo);
$all_terms = isset($GLOBALS["all_highlight_terms"]) && is_array($GLOBALS["all_highlight_terms"]) ? $GLOBALS["all_highlight_terms"] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo highlight_search($pdf_display_name, $all_terms);
?>
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
$_is_favorite = isset($favorites_arr[$_fav_file_path]);
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        onclick="toggleFavorite(this, '<?php echo addslashes($_fav_file_path); ?>', <?php echo $bidx; ?>, event)"
        title="<?php echo $_is_favorite ? __h('ui_unfavorite') : __h('ui_add_favorite'); ?>">
    <?php echo $_is_favorite ? '⭐' : '⭐'; ?>
</button>
<?php if ($video_thumb && file_exists($video_thumb)): ?>
<!-- 동영상 썸네일 이미지 - 이미지 ZIP과 동일한 크기 -->
<img src="index.php?thumb=video&file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>" class="rounded card-img-top card-img" alt="video thumbnail">
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
					<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-play-circle" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
  <path fill-rule="evenodd" d="M6.271 5.055a.5.5 0 0 1 .52.038l3.5 2.5a.5.5 0 0 1 0 .814l-3.5 2.5A.5.5 0 0 1 6 10.5v-5a.5.5 0 0 1 .271-.445z"/>
</svg> <?php 
// 동영상 파일명 하이라이트
$video_all_terms = isset($GLOBALS['all_highlight_terms']) && is_array($GLOBALS['all_highlight_terms']) 
    ? $GLOBALS['all_highlight_terms'] 
    : [];
if (empty($video_all_terms) && !empty($q)) {
    $video_all_terms = [$q];
}
echo highlight_search($fileinfo, $video_all_terms); 
?>
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
							if ($file_ext === 'epub' && isset($epub_progress[$file_progress_key])) {
							    $file_progress_percent = (int)($epub_progress[$file_progress_key]['percent'] ?? 0);
							} elseif ($file_ext === 'txt' && isset($txt_progress[$file_progress_key])) {
							    $file_progress_percent = (int)($txt_progress[$file_progress_key]['percent'] ?? 0);
							}
							
							// 썸네일 확인 (파일명.jpg 또는 파일명.txt.jpg 등)
							$thumb_file = '';
							$base_name = preg_replace('/\.(txt|epub|hwpx?)$/i', '', $zip_file); // 확장자 제거
							$possible_thumbs = [
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
$_is_favorite = isset($favorites_arr[$_fav_file_path]);
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        onclick="toggleFavorite(this, '<?php echo addslashes($_fav_file_path); ?>', <?php echo $bidx; ?>, event)"
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
										<!-- HWP 기본 SVG 아이콘 -->
										<svg viewBox="0 0 56 56" style="width:80px;height:80px;" xmlns="http://www.w3.org/2000/svg">
											<g>
												<path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/>
												<polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/>
												<path style="fill:#4B96E6;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/>
												<text x="28" y="51" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="bold" fill="#FFFFFF" text-anchor="middle">HWP</text>
												<path style="fill:#4B96E6;" d="M15,18h26v2H15V18z M15,24h26v2H15V24z M15,30h20v2H15V30z"/>
											</g>
										</svg>
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
										<!-- HWP 기본 SVG 아이콘 -->
										<svg viewBox="0 0 56 56" style="width:80px;height:80px;" xmlns="http://www.w3.org/2000/svg">
											<g>
												<path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/>
												<polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/>
												<path style="fill:#4B96E6;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/>
												<text x="28" y="51" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="bold" fill="#FFFFFF" text-anchor="middle">HWP</text>
												<path style="fill:#4B96E6;" d="M15,18h26v2H15V18z M15,24h26v2H15V24z M15,30h20v2H15V30z"/>
											</g>
										</svg>
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
										<span class="badge badge-pill badge-info badge-filetype"><?php echo strtoupper($file_ext); ?></span><?php if ($file_progress_percent > 0): ?><span class="badge badge-pill badge-progress <?php echo $file_progress_percent >= 100 ? 'badge-success' : 'badge-warning'; ?>"><?php echo $file_progress_percent; ?>%</span><?php endif; ?>
									</div>
									<div class="card-body m-0 p-0 text-center" style="text-overflow: ellipsis; overflow: hidden; white-space: normal; line-height: 1.05;">
										<span class="emoji-icon"><?php echo $file_icon; ?></span>
										<?php 
										// 확장자 제거한 파일명
										$display_name = preg_replace('/\.(txt|epub)$/i', '', $fileinfo);
										$all_terms = isset($GLOBALS['all_highlight_terms']) && is_array($GLOBALS['all_highlight_terms']) 
										    ? $GLOBALS['all_highlight_terms'] : [];
										if (empty($all_terms) && !empty($q)) $all_terms = [$q];
										echo highlight_search($display_name, $all_terms);
										?>
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
										<!-- Office SVG 아이콘 -->
										<svg viewBox="0 0 56 56" style="width:80px;height:80px;" xmlns="http://www.w3.org/2000/svg">
											<g>
												<path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/>
												<polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/>
												<path style="fill:<?php echo $office_color; ?>;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/>
												<text x="28" y="51" font-family="Arial, Helvetica, sans-serif" font-size="9" font-weight="bold" fill="#FFFFFF" text-anchor="middle"><?php echo strtoupper($file_ext); ?></text>
												<path style="fill:<?php echo $office_color; ?>;" d="M15,18h26v2H15V18z M15,24h26v2H15V24z M15,30h20v2H15V30z"/>
											</g>
										</svg>
									</div>
									<div class="card-img-overlay m-1 p-0">
										<span class="badge badge-pill badge-info badge-filetype" style="background:<?php echo $office_color; ?>;"><?php echo strtoupper($file_ext); ?></span>
									</div>
									<div class="card-body m-0 p-0 text-center" style="text-overflow: ellipsis; overflow: hidden; white-space: normal; line-height: 1.05;">
										<?php echo $file_icon_svg; ?>
										<?php 
										$display_name = preg_replace('/\.(docx?|xlsx?|pptx?)$/i', '', $fileinfo);
										$all_terms = isset($GLOBALS['all_highlight_terms']) && is_array($GLOBALS['all_highlight_terms']) 
										    ? $GLOBALS['all_highlight_terms'] : [];
										if (empty($all_terms) && !empty($q)) $all_terms = [$q];
										echo highlight_search($display_name, $all_terms);
										?>
									</div>
								</div>
							  </div>
							</a>
							<?php
							continue; // ✅ Office 파일 처리 후 다음 파일로 (중복 렌더링 방지)
						} else {
						// configfile 경로 설정 (압축 파일만)
						$configfile = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7|pdf)$/i', '.json', $zip_file);
						
						// ✅ .image_files.json 경로
						$image_files_cache = $zip_file . '.image_files.json';
						
						// ✅ 캐시된 배지 정보가 있고 .image_files.json도 있으면 바로 사용
						if ($cached_totalpage !== null && $cached_viewer !== null && $cached_pageorder !== null && is_file($image_files_cache)) {
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
					$zip = new ZipArchive;
					if ($zip->open($zip_file) == TRUE) {
						$thumbnail_index = 0;
						$find_img = array();
						$zip_numfile = $zip->numFiles;

// ★★★ .image_files.json 생성 추가 ★★★
        $image_files = array();
        $video_files = array();
        
        for ($findthumb = 0; $findthumb < $zip_numfile; $findthumb++) {
            $find_img[$findthumb] = $zip->getNameIndex($findthumb);
            
            // 이미지 파일만 따로 수집
            if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $find_img[$findthumb])) {
                $image_files[] = $find_img[$findthumb];
            }
            // 동영상 파일 수집
            if (is_video_file($find_img[$findthumb])) {
                $video_files[] = $find_img[$findthumb];
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
            
            $zip->close();
            unset($zip);
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

						for ($findthumb = 0; $findthumb < $zip_numfile; $findthumb++) {
							$find_img[$findthumb] = $zip->getNameIndex($findthumb);
						}
						$find_img = n_sort($find_img);
						$thumbnail_index = null; // 명시적 초기화
						$thumbnail_filename = null; // 실제 파일명 저장
						for ($findthumb = 0; $findthumb < $zip_numfile; $findthumb++) {
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
							$zip->close();
							unset($zip);
							gc_collect_cycles();
						} else {
						
						// 이미지 데이터 가져오기
						$img_data = $zip->getFromName($thumbnail_filename);
						if ($img_data === false) {
							// 실패시 빈 썸네일로 저장
							$cache_data = array();
							$cache_data['totalpage'] = count($image_files);  // ✅ 폴더 제외, 이미지 파일만
							$cache_data['page_order'] = "0";
							$cache_data['viewer'] = "toon";
							$cache_data['thumbnail'] = "";
							safe_json_write($configfile, $cache_data);
							$zip->close();
							unset($zip);
							gc_collect_cycles();
						} else {

						$cropimage = null;  // ✅ 버그 수정: 이전 파일의 썸네일 잔존 방지
						$size = getimagesizefromstring($img_data);
						if($size && $size[0] > $size[1]) {
							$x_point = ($size[0]/2) - $size[1];
							$originimage = imagecreatefromstring($img_data);
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
							$originimage = imagecreatefromstring($img_data);
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
					$zip->close();
                    unset($zip); // ✨ ZipArchive 해제
                    gc_collect_cycles(); // ✨ PHP GC 유도
					safe_json_write($configfile, $cache_data);

					$_SESSION['zip_cache_generated'] = true;
				} // end of else (getFromName 성공)
				} // end of else (이미지가 있는 경우)
				}

			$json_data = json_decode(file_get_contents($configfile), true) ?? [];
			$img_output = $json_data['thumbnail'];
			
			// ✅ 캐시된 배지 정보가 있으면 사용, 없으면 JSON에서 읽기
			if ($cached_totalpage !== null) {
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
				$totalpage = $json_data['totalpage'];
				$pageorder = $json_data['page_order'];
				if((int)$json_data['page_order'] == 0) {
					$pageorder = "[ - ]";
				} elseif((int)$json_data['page_order'] == 1) {
					$pageorder = "[1|2]";
				} elseif((int)$json_data['page_order'] == 2) {
					$pageorder = "[2|1]";
				}
				if($json_data['viewer'] !== null){
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
			$cover_file = $dir."/[cover].jpg";
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
					<div class="card text-black m-0 p-1">
						<!-- 동영상 아이콘 - thumb.php 사용 -->
						<img src="thumb.php?file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>" class="rounded card-img-top card-img" alt="video" loading="lazy" style="min-height: 120px; object-fit: contain; background: #e9e9e9;">
						<div class="card-img-overlay m-1 p-0">
							<span class="badge badge-pill badge-primary">ZIP</span>
							<span class="badge badge-pill badge-info badge-filecount"><?php echo h($totalpage); ?> files</span>
<?php 
// ✅ NEW 딱지 표시 (동영상 ZIP)
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0) {
    $_file_full_path = $dir . "/" . $fileinfo;
    $_file_ctime = get_file_created_time($_file_full_path);
    if ($_file_ctime && (time() - $_file_ctime) < ($_new_badge_hours * 3600)) {
        echo '<span class="badge badge-pill badge-danger">N</span>';
    }
}
?>
						</div>
						<div class="card-body m-0 p-0 text-center text-nowrap" style="text-overflow: ellipsis; overflow: hidden;">
							<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-play-circle" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
								<path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
								<path fill-rule="evenodd" d="M6.271 5.055a.5.5 0 0 1 .52.038l3.5 2.5a.5.5 0 0 1 0 .814l-3.5 2.5A.5.5 0 0 1 6 10.5v-5a.5.5 0 0 1 .271-.445z"/>
							</svg> <?php 
$zip_name = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '', $fileinfo);
$all_terms = isset($GLOBALS['all_highlight_terms']) && is_array($GLOBALS['all_highlight_terms']) ? $GLOBALS['all_highlight_terms'] : [];
if (empty($all_terms) && !empty($q)) $all_terms = [$q];
echo highlight_search($zip_name, $all_terms);
?>
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
<!--  <a href='viewer.php?mode=<?php echo $viewer; ?>&file=<?php echo encode_url($getdir."/".$fileinfo);?>' rel="noopener noreferrer">  -->
				<a href='viewer.php?mode=toon&file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>' rel="noopener noreferrer">
				  <div class="col mb-3">
					<div class="card text-black m-0 p-1" style="position:relative;" <?php if ($is_admin): ?>data-file-path="<?php echo h($getdir."/".$fileinfo); ?>"<?php endif; ?>>
<?php 
// ✅ 즐겨찾기 버튼
$_fav_file_path = $getdir."/".$fileinfo;
$_is_favorite = isset($favorites_arr[$_fav_file_path]);
?>
<button class="fav-btn <?php echo $_is_favorite ? 'is-fav' : ''; ?>" 
        onclick="toggleFavorite(this, '<?php echo addslashes($_fav_file_path); ?>', <?php echo $bidx; ?>, event)"
        title="<?php echo $_is_favorite ? __h('ui_unfavorite') : __h('ui_add_favorite'); ?>">
    <?php echo $_is_favorite ? '⭐' : '⭐'; ?>
</button>
						<img src="thumb.php?file=<?php echo encode_url($getdir."/".$fileinfo) . $bidx_param; ?>" class="rounded card-img-top card-img" alt="thumbnail" loading="lazy">
									<div class="card-img-overlay m-1 p-0">
									<span class="badge badge-pill badge-success"><?php echo h($totalpage); ?>p</span>
									<span class="badge badge-pill badge-success"><?php echo h($pageorder); ?></span>
									<span class="badge badge-pill badge-success"><?php echo h($viewer); ?></span>
<?php 
// ✅ NEW 딱지 표시
$_new_badge_hours = (int)get_app_settings('new_badge_hours', 24);
if ($_new_badge_hours > 0) {
    $_file_full_path = $dir . "/" . $fileinfo;
    $_file_ctime = get_file_created_time($_file_full_path);
    if ($_file_ctime && (time() - $_file_ctime) < ($_new_badge_hours * 3600)) {
        echo '<span class="badge badge-pill badge-danger">N</span>';
    }
}
?>
									</div>
						<div class="card-body m-0 p-0 text-center" style="text-overflow: ellipsis; overflow: hidden; white-space: normal; line-height: 1.05;">
							<svg width="0.6em" height="0.6em" viewBox="0 0 16 16" class="bi bi-file-earmark-zip-fill" fill="#f0c419" xmlns="http://www.w3.org/2000/svg">
				  <path fill-rule="evenodd" d="M2 2a2 2 0 0 1 2-2h5.293A1 1 0 0 1 10 .293L13.707 4a1 1 0 0 1 .293.707V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm7 2l.5-2.5 3 3L10 5a1 1 0 0 1-1-1zM5.5 3V2h-1V1H6v1h1v1H6v1h1v1H6v1h1v1H5.5V6h-1V5h1V4h-1V3h1zm0 4.5a1 1 0 0 0-1 1v.938l-.4 1.599a1 1 0 0 0 .416 1.074l.93.62a1 1 0 0 0 1.109 0l.93-.62a1 1 0 0 0 .415-1.074l-.4-1.599V8.5a1 1 0 0 0-1-1h-1zm0 1.938V8.5h1v.938a1 1 0 0 0 .03.243l.4 1.598-.93.62-.93-.62.4-1.598a1 1 0 0 0 .03-.243z"/>
				</svg> 
<?php 
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
?>

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
if (is_dir($dir)) {
    $dir_video_cache = $dir . '/.folder_cache.json';
    if (is_file($dir_video_cache)) {
        $dvc = @json_decode(file_get_contents($dir_video_cache), true);
        if ($dvc && isset($dvc['video_count']) && $dvc['video_count'] > 0) {
            $has_video_in_dir = true;
        }
    } else {
        // 캐시 없으면 직접 체크
        foreach (new DirectoryIterator($dir) as $f) {
            if ($f->isFile() && is_video_file($f->getFilename())) {
                $has_video_in_dir = true;
                break;
            }
        }
    }
}

// 이미지만 있고 동영상이 없을 때만 .image_files.json 생성
	if(count($jpg_list) > 0 && !$has_video_in_dir && (count($dir_list) + count($title_list) + count($file_list)) == 0){
					$configfile = $dir.".image_files.json";
					if(is_File($configfile) === false || empty($_SESSION['index_refreshed'])) {
						$jpg_cover = file_get_contents($dir."/".$jpg_list[0]);
						$size = getimagesizefromstring($jpg_cover);
						$cropimage = null;  // ✅ 버그 수정: 초기화
						
						if($size && $size[0] > $size[1]) {
							$x_point = ($size[0]/2) - $size[1];
							$originimage = imagecreatefromstring($jpg_cover);
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
							$originimage = imagecreatefromstring($jpg_cover);
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
					$img_output = $json_data['thumbnail'];
					$totalpage = $json_data['totalpage'];
					$pageorder = $json_data['page_order'];
					if((int)$json_data['page_order'] == 0) {
						$pageorder = "[ - ]";
					} elseif((int)$json_data['page_order'] == 1) {
						$pageorder = "[1|2]";
					} elseif((int)$json_data['page_order'] == 2) {
						$pageorder = "[2|1]";
					}
					if($json_data['viewer'] !== null){
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
$total_pages = max(1, ceil($maxlist / $current_maxview));
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
if (!empty($sort_param)) $nav_params[] = 'sort=' . urlencode($sort_param);
if (!empty($getdir)) $nav_params[] = 'dir=' . encode_url($getdir);
if (!empty($q)) $nav_params[] = 'q=' . urlencode($q);
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

<div class="pagination-wrapper" data-pc="<?php echo $pages_per_group; ?>" data-mobile="<?php echo $pages_per_group_mobile; ?>" data-current="<?php echo $current_page; ?>" data-total="<?php echo $total_pages; ?>" data-param-prefix="<?php echo htmlspecialchars($param_prefix); ?>">
    <!-- 페이지 버튼들 -->
    <div class="pagination-nav">
        <!-- 상위폴더 버튼 -->
        <?php if($dir != $base_dir): ?>
        <a href="index.php?dir=<?php echo encode_url($updir);?>&page=<?php echo validate_page(get_param('uppage', 'int', 0)) . $bidx_param; ?>" class="page-btn" title="<?php echo __h('ui_parent_folder'); ?>">
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

			if (localStorage.getItem(key)) {
				link.classList.add("visited-link");
			}

			link.addEventListener("click", () => {
				localStorage.setItem(key, "1");
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
    document.addEventListener('submit', function() {
        document.documentElement.classList.add('leaving');
    });
    
    // 뒤로가기(bfcache) 복원
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            document.documentElement.classList.remove('leaving');
            document.documentElement.classList.add('ready');
            // ✅ viewer에서 변경된 즐겨찾기 상태 동기화
            syncFavorites();
        }
    });
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
</style>
<script>
// ✅ 뒤로가기 시 즐겨찾기 상태 동기화 (viewer에서 변경된 경우)
function syncFavorites() {
    var btns = document.querySelectorAll('.fav-btn');
    if (!btns.length) return;
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'bookmark.php?mode=list_favorites', true);
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
                    var path = btn.getAttribute('onclick');
                    var m = path && path.match(/toggleFavorite\(this,\s*'([^']+)'/);
                    if (!m) return;
                    var filePath = m[1].replace(/\\'/g, "'");
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
    var table = document.getElementById('bookmarkTable');
    if (!table) return;
    
    // 이미 있는지 확인
    if (document.querySelector('tr[data-fav-path="' + CSS.escape(filePath) + '"]')) return;
    
    var tr = document.createElement('tr');
    tr.className = 'border-bottom';
    tr.style.borderColor = '#f39c12';
    tr.setAttribute('data-fav-path', filePath);
    
    var viewerUrl = '';
    var ext = filePath.split('.').pop().toLowerCase();
    if (['zip','cbz','rar','cbr','7z','cb7'].indexOf(ext) >= 0) {
        viewerUrl = 'viewer.php?mode=toon&file=' + encodeURIComponent(filePath) + '&bidx=' + bidx;
    } else if (ext === 'epub') {
        viewerUrl = 'epub_viewer.php?file=' + encodeURIComponent(filePath) + '&bidx=' + bidx;
    } else if (ext === 'txt') {
        viewerUrl = 'txt_viewer.php?file=' + encodeURIComponent(filePath) + '&bidx=' + bidx;
    } else {
        viewerUrl = 'index.php?dir=' + encodeURIComponent(filePath) + '&bidx=' + bidx;
    }
    
    tr.innerHTML = '<td align="right" style="white-space:nowrap;">' +
        '<span class="badge badge-warning" style="font-size:0.7em;">⭐</span>' +
        '<button class="btn btn-sm m-0 p-0" onclick="removeFavoriteClick(this, \'' + filePath.replace(/'/g, "\\'") + '\', ' + bidx + ')">' +
        '<svg width="1em" height="1em" viewBox="0 0 18 18" fill="currentColor"><path fill-rule="evenodd" d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/></svg>' +
        '</button></td><td>' +
        '<button class="btn btn-sm mr-1 p-0 d-inline-block text-truncate text-nowrap" onclick="location.href=\'./' + viewerUrl + '\'">⭐ ' + escapeHtml(filename) + '</button></td>';
    
    // 테이블 맨 아래에 추가
    table.appendChild(tr);
    
    // 북마크 버튼 표시 (숨겨져 있으면)
    showBookmarkButton();
    updateFavCount(1);
}

function removeFavoriteFromBookmark(filePath) {
    var row = document.querySelector('tr[data-fav-path="' + CSS.escape(filePath) + '"]');
    if (row) {
        row.remove();
        updateFavCount(-1);
    }
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
        var onclick = btn.getAttribute('onclick') || '';
        if (onclick.indexOf(filePath.replace(/'/g, "\\'")) >= 0 || onclick.indexOf(filePath) >= 0) {
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

<!-- 페이지 생성 시간: <?php echo round((microtime(true) - $_start_time) * 1000, 2); ?>ms | 캐시: <?php echo $_cache_debug ?? 'N/A'; ?> | 캐시읽기: <?php echo $_time_cache_read ?? 0; ?>ms | 정렬: <?php echo $_time_after_cache ?? 0; ?>ms | maxview: <?php echo $current_maxview ?? 'N/A'; ?> (folder:<?php echo $maxview_folder ?? 'N/A'; ?>/file:<?php echo $maxview_file ?? 'N/A'; ?>) | files:<?php echo $real_file_count ?? 'N/A'; ?> folders:<?php echo (count($dir_list ?? []) + count($title_list ?? [])); ?> | mobile:<?php echo ($is_mobile ?? false) ? 'Y' : 'N'; ?> -->

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
            info += '<p><strong>📧 ' + i18n.email_label + ':</strong> ' + (data.email || '<span class="text-warning">' + i18n.unregistered + '</span>') + '</p>';
            info += '<p><strong>📅 ' + i18n.joined_label + ':</strong> ' + (data.created_at || '<span class="text-muted">' + i18n.no_info + '</span>') + '</p>';
            info += '<p><strong>🌐 ' + i18n.ip_label + ':</strong> <code>' + (data.current_ip || '-') + '</code></p>';
            info += '<p><strong>🌍 ' + i18n.country_label + ':</strong> <span class="badge badge-info">' + (data.current_country || '-') + '</span></p>';
            info += '</div>';
            document.getElementById('profileInfoDetail').innerHTML = info;
            document.getElementById('currentEmailDisplay').innerHTML = data.email || '<span class="text-warning">' + i18n.no_email + '</span>';
            document.getElementById('newEmail').value = data.email || '';
        }
    })
    .catch(function(e) {
        document.getElementById('profileInfoDetail').innerHTML = '<span class="text-danger">' + i18n.load_failed + '</span>';
    });
}

function loadLoginLogs(page) {
    var container = document.getElementById('loginLogContent');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm" role="status"></div> <?php echo __("ui_loading"); ?></div>';
    
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
                    html += '<td style="white-space:nowrap;">' + (log.datetime || '-') + '</td>';
                    html += '<td><code style="font-size:11px;">' + (log.ip || '-') + '</code></td>';
                    html += '<td><span class="badge badge-secondary">' + country + '</span></td>';
                    html += '<td class="d-none d-sm-table-cell">' + deviceIcon + '</td>';
                    html += '</tr>';
                }
                
                html += '</tbody></table></div>';
                
                // 페이지네이션
                if (data.total_pages > 1) {
                    html += '<nav class="mt-2"><ul class="pagination pagination-sm justify-content-center mb-0">';
                    
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
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + i18n.processing;
    
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'profile_action=change_email&csrf_token=' + encodeURIComponent(profileCsrfToken) +
              '&new_email=' + encodeURIComponent(newEmail)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = i18n.change_email;
        
        if (data.success) {
            msgEl.innerHTML = '<div class="alert alert-success py-2">' + data.message + '</div>';
            document.getElementById('currentEmailDisplay').innerHTML = data.email || '<span class="text-warning">' + i18n.no_email + '</span>';
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
    body.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2"><?php echo __("ui_loading"); ?></div></div>';
    
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
        generateTotp();
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
    // QR 코드 API URL (qrserver.com 무료 API 사용)
    var qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(qrUrl);
    
    var html = '';
    
    // 상단 안내
    html += '<div class="alert alert-warning py-2 mb-3 text-center">';
    html += '<strong>⏳ ' + i18n.twofa_register_msg + '</strong>';
    html += '</div>';
    
    // QR 코드 영역
    html += '<div class="text-center mb-4">';
    html += '<div class="d-inline-block p-3 bg-white border rounded shadow-sm">';
    html += '<img src="' + qrImageUrl + '" alt="QR Code" width="180" height="180" onerror="this.outerHTML=\'<div class=text-danger style=width:180px;height:180px;display:flex;align-items:center;justify-content:center;>' + (window.i18n ? i18n.twofa_qr_fail : 'QR Load Failed') + '<br>' + (window.i18n ? i18n.twofa_enter_key : 'Enter secret key manually') + '</div>\'">';
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
    
    if (!confirm(i18n.twofa_confirm_delete)) return;
    
    var formData = new FormData();
    formData.append('totp_action', 'reset');
    formData.append('confirm', 'RESET');
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
    
    var formData = new FormData();
    formData.append('totp_action', 'regenerate_backup');
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
var currentDir = '<?php echo addslashes(get_param('dir', 'path', '')); ?>';

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
        html += '<small class="text-muted">' + i18n.current_location + ': <?php echo h(get_param('dir', 'path', '') ?: '(/)'); ?></small>';
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
        html += '<div id="dropZone" style="border:2px dashed #ccc; border-radius:10px; padding:40px; text-align:center; background:#fafafa; cursor:pointer; margin-bottom:15px; transition:all 0.3s;">';
        html += '<div style="font-size:48px; margin-bottom:10px;">📁</div>';
        html += '<div style="font-size:16px; color:#666;">' + i18n.upload_drag + '</div>';
        html += '<div style="font-size:12px; color:#999; margin-top:5px;">' + i18n.upload_info + '</div>';
        html += '</div>';
        html += '<input type="file" id="uploadFiles" multiple style="display:none;">';
        
        // 파일 목록 테이블
        html += '<div id="fileListContainer" style="display:none; max-height:250px; overflow-y:auto; margin-bottom:15px;">';
        html += '<table id="fileListTable" style="width:100%; border-collapse:collapse; font-size:13px;">';
        html += '<thead><tr style="background:#f5f5f5; border-bottom:2px solid #ddd;">';
        html += '<th style="padding:8px; text-align:left;">' + i18n.upload_filename + '</th>';
        html += '<th style="padding:8px; width:80px; text-align:right;">' + i18n.upload_size + '</th>';
        html += '<th style="padding:8px; width:80px; text-align:center;">' + i18n.upload_status + '</th>';
        html += '<th style="padding:8px; width:30px;"></th>';
        html += '</tr></thead>';
        html += '<tbody id="fileListBody"></tbody>';
        html += '</table>';
        html += '</div>';
        
        // 전체 진행률
        html += '<div id="totalProgressArea" style="display:none; background:#f0f7ff; border-radius:8px; padding:12px; margin-bottom:15px;">';
        html += '<div style="display:flex; justify-content:space-between; margin-bottom:5px;">';
        html += '<span id="uploadStatusText">' + i18n.upload_count_status.replace('%d', '0').replace('%d', '0') + '</span>';
        html += '<span id="uploadPercentText">0%</span>';
        html += '</div>';
        html += '<div class="progress" style="height:8px;"><div id="totalProgressBar" class="progress-bar" style="width:0%; transition:width 0.1s;"></div></div>';
        html += '<div id="currentFileName" style="font-size:12px; color:#007bff; margin-top:8px;"></div>';
        html += '</div>';
        
        html += '<div style="font-size:12px; color:#888;">';
        html += '<strong>' + i18n.upload_current_loc + ':</strong> <?php echo h(get_param('dir', 'path', '') ?: '/'); ?><br>';
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
            rowBg = 'background:#fff8e1;';
        } else if (item.status === 'done') {
            statusHtml = '<span style="color:#28a745;">✅' + i18n.upload_done + '</span>';
            rowBg = 'background:#e8f5e9;';
        } else if (item.status === 'error') {
            statusHtml = '<span style="color:#dc3545;">❌' + i18n.upload_failed + '</span>';
            rowBg = 'background:#ffebee;';
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
    html += '<tr style="background:#f5f5f5; font-weight:bold;">';
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
                            '📁 ' + item.name + '<br>' +
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
        btn.innerHTML = '🗑️ ON';
        
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
        btn.innerHTML = '🗑️';
        
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

function deleteFile(filePath) {
    var fileName = filePath.split('/').pop();
    if (!confirm(i18n.confirm_file_delete.replace('%s', fileName))) {
        return;
    }
    
    // 이중 확인
    if (!confirm(i18n.confirm_file_delete2.replace('%s', fileName))) {
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
    if (!confirm(i18n.confirm_folder_delete.replace('%s', folderName))) {
        return;
    }
    
    // 이중 확인
    if (!confirm(i18n.confirm_folder_delete2.replace('%s', folderName))) {
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

<?php require_once __DIR__ . '/privacy_shield.php'; ?>

<div style="text-align:center; padding:15px 0 10px; color:#999; font-size:12px;">
© <?php echo h($_branding['copyright'] ?? 'myComix'); ?>
</div>
</body>
</html>