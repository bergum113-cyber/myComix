<?php
/**
 * myComix 북마크 관리
 * @version 4.1 - is_image_file() 헬퍼 함수 사용으로 패턴 통일
 * @date 2026-01-11
 * 
 * 변경사항:
 * - verify_state_change_request() 함수를 security_helpers.php로 이동
 * - bootstrap.php의 get_*_file() 함수 사용으로 전역 변수 의존 제거
 * - decode_url() → decode_file_param() 변경 (이중 인코딩 자동 처리)
 * - 에러 응답 통일: http_response_code() 직접 사용 → simple_error_exit(), json_response()
 */
require_once __DIR__ . '/bootstrap.php';
$bidx = init_bidx();

// ✅ verify_state_change_request()는 security_helpers.php에서 제공됨

// ============================================================
// 입력값 검증 함수
// ============================================================

function sanitize_bookmark($val) {
    // bookmark는 "image숫자" 형식만 허용
    if (preg_match('/^image\d+$/', $val)) {
        return $val;
    }
    return 'image0';
}

function sanitize_viewer($val) {
    // viewer는 toon, book만 허용
    return in_array($val, ['toon', 'book'], true) ? $val : 'toon';
}

function sanitize_page_order($val) {
    // page_order는 0, 1, 2만 허용
    return in_array($val, ['0', '1', '2'], true) ? $val : '0';
}

// ============================================================
// 파일 잠금 기반 JSON 읽기/쓰기 함수
// ✅ function.php에서 통합 제공 - 중복 제거 (2026-01-11)
// load_json_with_lock(), save_json_with_lock() 사용
// ============================================================

// ============================================================
// 파라미터 로드
// ============================================================


// ✅ decode_file_param() 사용 - 이중 인코딩 자동 처리 (2026-01-11)
$getfile = decode_file_param($_GET['file'] ?? $_POST['file'] ?? '');
$mode = $_GET['mode'] ?? $_POST['mode'] ?? '';

// ✅ bootstrap.php의 함수 사용 (전역 변수 $bookmark_file 대신)
$bookmark_file = get_bookmark_file();
$autosave_file = get_autosave_file();
$favorites_file = get_favorites_file();

// ✅ 설정값 - get_app_settings() 함수 사용 (global 대신 권장)
$max_bookmark = (int)get_app_settings('max_bookmark', 10);
$max_autosave = (int)get_app_settings('max_autosave', 10);
$max_favorites = (int)get_app_settings('max_favorites', 50);

// ============================================================
// 삭제 작업 목록 (토큰 필수)
// ============================================================
$delete_modes = ['delete_bookmark', 'delete_autosave', 'delete_epub_progress', 'delete_txt_progress', 'set_cover', 'delete_favorite'];

// ✅ 삭제 작업은 토큰 필수 - verify_state_change_request(true) 사용
if (in_array($mode, $delete_modes)) {
    if (!verify_state_change_request(true)) {
        // ✅ 에러 응답 통일: json_response() 사용
        json_response(['error' => __('err_permission_denied')], 403);
    }
}

// ============================================================
// 모드별 처리
// ============================================================

if ($mode === "delete_bookmark") {
    $bookmark_arr = load_json_with_lock($bookmark_file);
    unset($bookmark_arr[$getfile]);
    save_json_with_lock($bookmark_file, $bookmark_arr);
    safe_redirect($_SERVER["HTTP_REFERER"] ?? 'index.php');

} elseif ($mode === "delete_autosave") {
    $autosave_arr = load_json_with_lock($autosave_file);
    unset($autosave_arr[$getfile]);
    save_json_with_lock($autosave_file, $autosave_arr);
    safe_redirect($_SERVER["HTTP_REFERER"] ?? 'index.php');

} elseif ($mode === "autosave") {
    // ✅ autosave는 세션 사용자만 허용 (토큰 불필요)
    if (!verify_state_change_request(false)) {
        // ✅ 에러 응답 통일: simple_error_exit() 사용
        simple_error_exit(403, 'Forbidden');
    }
    
    $autosave_arr = load_json_with_lock($autosave_file);
    // ✅ max_autosave 개수 초과 시 가장 오래된 항목 제거 (>= 로 정확히 제한)
    if (count($autosave_arr) >= (int)$max_autosave) {
        array_shift($autosave_arr);
    }
    $autosave_arr[$getfile]['bookmark'] = sanitize_bookmark($_GET['bookmark'] ?? 'image0');
    $autosave_arr[$getfile]['viewer'] = sanitize_viewer($_GET['viewer'] ?? 'toon');
    $autosave_arr[$getfile]['page_order'] = sanitize_page_order($_GET['page_order'] ?? '0');
    $autosave_arr[$getfile]['bidx'] = $bidx;
    save_json_with_lock($autosave_file, $autosave_arr);

} elseif ($mode === "delete_epub_progress") {
    // ✅ EPUB 읽기 진행 삭제
    $progress_file = get_epub_progress_file();
    $progress = load_json_with_lock($progress_file);
    unset($progress[$getfile]);
    save_json_with_lock($progress_file, $progress);
    safe_redirect($_SERVER["HTTP_REFERER"] ?? 'index.php');

} elseif ($mode === "delete_txt_progress") {
    // ✅ TXT 읽기 진행 삭제
    $progress_file = get_txt_progress_file();
    $progress = load_json_with_lock($progress_file);
    unset($progress[$getfile]);
    save_json_with_lock($progress_file, $progress);
    safe_redirect($_SERVER["HTTP_REFERER"] ?? 'index.php');

} elseif ($mode === "set_cover") {
    // ✅ 경로 검증 추가
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false) {
        // ✅ 에러 응답 통일: simple_error_exit() 사용
        simple_error_exit(403, __('err_invalid_path'));
    }
    
    // ✅ dirname() 사용으로 경로 조합 단순화 및 안전성 확보
    // 기존: str_replace($title, "", $base_file) → 경로 내 동일 문자열 모두 제거되는 버그
    // 개선: dirname()으로 부모 폴더만 정확히 추출 + 경로 구분자 정규화
    $base_folder = rtrim(dirname($base_file), '/\\') . '/';
    $cover_file = $base_folder . "[cover].jpg";
    
    if (strpos(strtolower($base_file), ".zip") !== false || strpos(strtolower($base_file), ".cbz") !== false) {
        $list = [];
        $zip = new ZipArchive;
        if ($zip->open($base_file) === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                // ✅ is_image_file() 함수 사용 (2026-01-11)
                if (is_image_file($name)) {
                    $list[$i] = $name;
                }
            }
            $list = n_sort($list);
            if (!empty($list)) {
                $image_data = $zip->getFromName(reset($list));
                if ($image_data !== false) {
                    $cover_output = @imagecreatefromstring($image_data);
                    if ($cover_output !== false) {
                        imagejpeg($cover_output, $cover_file);
                        imagedestroy($cover_output);
                    }
                }
            }
            $zip->close();
        }
    } else {
        $list = [];
        if (is_dir($base_file)) {
            $iterator = new DirectoryIterator($base_file);
            foreach ($iterator as $jpgfile) {
                if ($jpgfile->isDot()) continue;
                // ✅ is_image_file() 함수 사용 (2026-01-11)
                if (is_image_file($jpgfile->getFilename())) {
                    $list[] = $base_file . "/" . $jpgfile->getFilename();
                }
            }
            $list = n_sort($list);
            if (!empty($list)) {
                $image_data = @file_get_contents(reset($list));
                if ($image_data !== false) {
                    $cover_output = @imagecreatefromstring($image_data);
                    if ($cover_output !== false) {
                        imagejpeg($cover_output, $cover_file);
                        imagedestroy($cover_output);
                    }
                }
            }
        }
    }
    echo __('bookmark_set');

} elseif ($mode === "add_favorite") {
    // ✅ 즐겨찾기 추가 (토큰 불필요, 세션만 확인)
    if (!verify_state_change_request(false)) {
        json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
    
    $favorites_arr = load_json_with_lock($favorites_file);
    
    // 이미 존재하면 추가하지 않음
    if (isset($favorites_arr[$getfile])) {
        json_response(['success' => true, 'action' => 'exists', 'message' => __('bookmark_already_exists')]);
    }
    
    // 최대 개수 초과 시 추가 차단
    if (count($favorites_arr) >= (int)$max_favorites) {
        json_response(['success' => false, 'error' => __('favorite_max_reached', $max_favorites)]);
    }
    
    $favorites_arr[$getfile] = [
        'added_at' => time(),
        'bidx' => $bidx
    ];
    save_json_with_lock($favorites_file, $favorites_arr);
    json_response(['success' => true, 'action' => 'added', 'message' => __('bookmark_added')]);

} elseif ($mode === "delete_favorite") {
    // ✅ 즐겨찾기 삭제 (토큰 필수 - delete_modes에 포함됨)
    $favorites_arr = load_json_with_lock($favorites_file);
    unset($favorites_arr[$getfile]);
    save_json_with_lock($favorites_file, $favorites_arr);
    
    // AJAX 요청인 경우 JSON 응답
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        json_response(['success' => true, 'action' => 'deleted', 'message' => __('bookmark_deleted')]);
    }
    safe_redirect($_SERVER["HTTP_REFERER"] ?? 'index.php');

} elseif ($mode === "toggle_favorite") {
    // ✅ 즐겨찾기 토글 (AJAX용, 토큰 불필요)
    if (!verify_state_change_request(false)) {
        json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
    
    $favorites_arr = load_json_with_lock($favorites_file);
    $filename = basename($getfile);
    $ext = strtolower(pathinfo($getfile, PATHINFO_EXTENSION));
    $viewer = 'folder';
    if (in_array($ext, ['zip', 'cbz', 'rar', 'cbr', '7z', 'cb7'])) {
        $viewer = 'archive';
    } elseif ($ext === 'epub') {
        $viewer = 'epub';
    } elseif ($ext === 'txt') {
        $viewer = 'txt';
    }
    
    if (isset($favorites_arr[$getfile])) {
        // 이미 있으면 삭제
        unset($favorites_arr[$getfile]);
        save_json_with_lock($favorites_file, $favorites_arr);
        json_response(['success' => true, 'action' => 'deleted', 'is_favorite' => false, 'message' => __('bookmark_deleted'), 'filename' => $filename, 'viewer' => $viewer]);
    } else {
        // 없으면 추가
        if (count($favorites_arr) >= (int)$max_favorites) {
            json_response(['success' => false, 'error' => __('favorite_max_reached', $max_favorites)]);
        }
        $favorites_arr[$getfile] = [
            'added_at' => time(),
            'bidx' => $bidx
        ];
        save_json_with_lock($favorites_file, $favorites_arr);
        json_response(['success' => true, 'action' => 'added', 'is_favorite' => true, 'message' => __('bookmark_added'), 'filename' => $filename, 'viewer' => $viewer]);
    }

} elseif ($mode === "check_favorite") {
    // ✅ 즐겨찾기 상태 확인 (AJAX용)
    if (!verify_state_change_request(false)) {
        json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
    
    $favorites_arr = load_json_with_lock($favorites_file);
    $is_favorite = isset($favorites_arr[$getfile]);
    json_response(['success' => true, 'is_favorite' => $is_favorite]);

} elseif ($mode === "list_favorites") {
    // ✅ 즐겨찾기 목록 반환 (bfcache 복원 시 동기화용)
    if (!verify_state_change_request(false)) {
        json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
    $favorites_arr = load_json_with_lock($favorites_file);
    json_response(['success' => true, 'favorites' => array_keys($favorites_arr)]);

} else {
    // 기본: 북마크 저장 (세션 사용자만 허용, 토큰 불필요)
    if (!verify_state_change_request(false)) {
        // ✅ 에러 응답 통일: simple_error_exit() 사용
        simple_error_exit(403, 'Forbidden');
    }
    
    $bookmark_arr = load_json_with_lock($bookmark_file);
    // ✅ max_bookmark 개수 초과 시 가장 오래된 항목 제거 (>= 로 정확히 제한)
    if (count($bookmark_arr) >= (int)$max_bookmark) {
        array_shift($bookmark_arr);
    }
    $bookmark_arr[$getfile]['bookmark'] = sanitize_bookmark($_GET['bookmark'] ?? 'image0');
    $bookmark_arr[$getfile]['viewer'] = sanitize_viewer($_GET['viewer'] ?? 'toon');
    $bookmark_arr[$getfile]['page_order'] = sanitize_page_order($_GET['page_order'] ?? '0');
    $bookmark_arr[$getfile]['bidx'] = $bidx;
    save_json_with_lock($bookmark_file, $bookmark_arr);
    
    // ✅ XSS 방어: 출력 이스케이프
    $bookmark_num = str_replace("image", "", sanitize_bookmark($_GET['bookmark'] ?? 'image0'));
    echo "#" . h($bookmark_num) . "-OK";
}
?>