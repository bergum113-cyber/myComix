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
    if (!is_string($val)) return 'image0'; // ★ (2026-11-02) 배열(bookmark[]=…)이 오면 preg_match 가 PHP 8 에서 멈췄음
    if (preg_match('/^image\d+$/', $val)) {
        return $val;
    }
    // ★ (2026-11-02 00:59) PDF 는 "pdf_쪽"(1부터) — 예전엔 여기서 'image0' 으로 바뀌어 PDF 책갈피·자동 저장 위치가 늘 처음이었음
    if (preg_match('/^pdf_\d{1,6}$/', $val)) {
        return $val;
    }
    return 'image0';
}

// ★ (2026-11-02 00:59) 이어 보기 정밀 위치: 그 쪽(그림·PDF 쪽) 안에서 얼마나 내려왔는지(천분율 0~999) — 숫자가 아니면 없음
function sanitize_frac($val) {
    if (!is_string($val) || !preg_match('/^\d{1,3}$/', $val)) return null;
    return min(999, (int)$val);
}

function sanitize_viewer($val) {
    // viewer는 toon, book만 허용
    return in_array($val, ['toon', 'book'], true) ? $val : 'toon';
}

function sanitize_page_order($val) {
    // page_order는 뷰어 정식 값 0~4만 허용(0:기본,1/2:묶음,3/4:세로분할 좌→우/우→좌). 그 외(pdf 등)는 '0'.
    return in_array($val, ['0', '1', '2', '3', '4'], true) ? $val : '0';
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
if (!is_string($mode)) $mode = ''; // ★ (2026-11-02 11:35) 배열이면 기본(북마크 저장)으로 빈 키가 저장되던 것
// ★ (2026-11-02 11:35) 저장하는 요청은 빈 경로·'..' 경로·볼 수 없는 폴더를 받지 않음 — 저장해 두고 북마크·즐겨찾기 화면의 '파일 없음' 표시로 서버 파일·숨긴 폴더 파일이 있는지 알아낼 수 있었음
if (!in_array($mode, ['delete_bookmark', 'delete_autosave', 'delete_epub_progress', 'delete_txt_progress', 'delete_favorite', 'check_favorite', 'list_favorites', 'set_cover', 'set_cover_img'], true)) {
    $__gf = str_replace('\\', '/', (string)$getfile);
    if (trim($__gf, '/') === '' || preg_match('#(^|/)\.\.(/|$)#', $__gf) || strpos($__gf, "\0") !== false
        || (function_exists('mc_path_allowed') && !mc_path_allowed($__gf))) {
        simple_error_exit(400, 'Bad Request');
    }
}

// ✅ bootstrap.php의 함수 사용 (전역 변수 $bookmark_file 대신)
$bookmark_file = get_bookmark_file();
$autosave_file = get_autosave_file();
$favorites_file = get_favorites_file();
// ★ (2026-11-09 11:43) 기록을 고치는 요청은 계정별로 차례로(function.php mc_rec_lock) — 동시에 온 자동 저장·북마크·즐겨찾기가 서로의 기록을 지우던 것. 표지 지정·읽기만 하는 요청은 제외
if (!in_array($mode, ['set_cover', 'set_cover_img', 'check_favorite', 'list_favorites'], true) && function_exists('mc_rec_lock')) mc_rec_lock();

// ✅ 설정값 - get_app_settings() 함수 사용 (global 대신 권장)
$max_bookmark = (int)get_app_settings('max_bookmark', 10);
$max_autosave = (int)get_app_settings('max_autosave', 10);

/** ★ (2026-11-02 08:56) 개수가 꽉 찼을 때 마지막 저장 시각('t')이 가장 오래된 항목 하나를 뺌 — 't' 없는 옛 항목은 가장 오래된 것으로(그중 앞의 것) */
function mc_bm_evict_oldest(array &$arr) {
    $old_key = null; $old_t = PHP_INT_MAX;
    foreach ($arr as $k => $v) {
        $t = (is_array($v) && isset($v['t'])) ? (int)$v['t'] : 0;
        if ($t < $old_t) { $old_t = $t; $old_key = $k; }
    }
    if ($old_key !== null) unset($arr[$old_key]);
}
$max_favorites = (int)get_app_settings('max_favorites', 50);
// ★ (2026-11-06 00:50) 기록 키는 콘텐츠 폴더(bidx)별로(bootstrap.php mc_rec_*) — 지우기는 요청에 적힌 번호 그대로(설정에서 빠진 콘텐츠 폴더 기록도 지울 수 있게)
$rec_bidx = mc_rec_req_bidx($bidx);

// ============================================================
// 삭제 작업 목록 (토큰 필수)
// ============================================================
$delete_modes = ['delete_bookmark', 'delete_autosave', 'delete_epub_progress', 'delete_txt_progress', 'set_cover', 'set_cover_img', 'delete_favorite']; // ★ (2026-11-08 fix80) set_cover_img(POST — CSRF 토큰)

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
    $__k = mc_rec_find($bookmark_arr, $rec_bidx, $getfile); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 것만(예전: 같은 경로면 다른 콘텐츠 폴더 것도)
    if ($__k !== null) unset($bookmark_arr[$__k]);
    save_json_with_lock($bookmark_file, $bookmark_arr);
    safe_redirect($_SERVER["HTTP_REFERER"] ?? 'index.php');

} elseif ($mode === "delete_autosave") {
    $autosave_arr = load_json_with_lock($autosave_file);
    $__k = mc_rec_find($autosave_arr, $rec_bidx, $getfile); // ★ (2026-11-06 00:50) 위와 같음
    if ($__k !== null) unset($autosave_arr[$__k]);
    save_json_with_lock($autosave_file, $autosave_arr);
    safe_redirect($_SERVER["HTTP_REFERER"] ?? 'index.php');

} elseif ($mode === "autosave") {
    // ✅ autosave는 세션 사용자만 허용 (토큰 불필요)
    if (!verify_state_change_request(false)) {
        // ✅ 에러 응답 통일: simple_error_exit() 사용
        simple_error_exit(403, 'Forbidden');
    }
    
    $autosave_arr = load_json_with_lock($autosave_file);
    $__ak = mc_rec_key_for_write($autosave_arr, $bidx, $getfile); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 칸(같은 경로를 다른 콘텐츠 폴더가 쓰고 있으면 따로) — 아래 $autosave_arr[$__ak] 는 예전 $autosave_arr[$getfile] 자리
    // ✅ max_autosave 개수 초과 시 가장 오래된 항목 제거 (현재 파일이 '새 항목'일 때만 — 기존 재저장 시 개수 유지)
    if (!isset($autosave_arr[$__ak]) && count($autosave_arr) >= (int)$max_autosave) {
        mc_bm_evict_oldest($autosave_arr); // ★ (2026-11-02 08:56) 가장 오래 안 본 것부터(전에는 처음 넣은 것 — 매일 보는 책이 먼저 밀려났음)
    }
    if (!is_array($autosave_arr[$__ak] ?? null)) $autosave_arr[$__ak] = []; // ★ (2026-11-02 09:56) 옛 문자열 형식 기록("image5")이면 아래 줄이 500 — 배열로 새로
    $autosave_arr[$__ak]['bookmark'] = sanitize_bookmark($_GET['bookmark'] ?? 'image0');
    $autosave_arr[$__ak]['viewer'] = sanitize_viewer($_GET['viewer'] ?? 'toon');
    $autosave_arr[$__ak]['page_order'] = sanitize_page_order($_GET['page_order'] ?? '0');
    $autosave_arr[$__ak]['bidx'] = $bidx;
    $autosave_arr[$__ak]['t'] = time(); // ★ (2026-11-02 08:56) 마지막 저장 시각(밀어낼 순서용 — 표시 순서는 그대로)
    // ★ (2026-11-02 00:59) 쪽 안 위치(천분율) — 툰모드·PDF 가 보냄(북모드는 쪽 단위라 안 보냄 → 지움)
    $_f = sanitize_frac($_GET['f'] ?? null);
    if ($_f !== null) $autosave_arr[$__ak]['f'] = $_f; else unset($autosave_arr[$__ak]['f']);
    save_json_with_lock($autosave_file, $autosave_arr);

    // ★ (2026-10-29) 라이브러리 읽기 기록(시리즈 이어보기·다 읽음 표시용). 실패해도 기존 저장에는 영향 없음
    try {
        if (is_file(__DIR__ . '/library.php')) {
            require_once __DIR__ . '/library.php';
            if (function_exists('mc_lib_record_read')) {
                mc_lib_record_read($_SESSION['user_id'] ?? '', $bidx, $getfile, mc_lib_page_from_mark(sanitize_bookmark($_GET['bookmark'] ?? '')) /* ★ (2026-11-02) 걸러진 값으로 — 배열이 오면 경고 났음 */, (is_string($_GET['total'] ?? null) && ctype_digit($_GET['total'])) ? (int)$_GET['total'] : 0);
            }
        }
    } catch (\Throwable $e) { /* 무시 */ }

} elseif ($mode === "save_dims") {
    // ✅ [B단계1] 뷰어 이미지 크기(가로x세로) 저장 — 세션 사용자만(토큰 불필요, autosave 동일 권한).
    //    콜드 리로드 시 높이 예약(리플로우 제거)용. best-effort: 실패해도 뷰잉/기존 동작에 영향 없음.
    if (!verify_state_change_request(false)) {
        simple_error_exit(403, 'Forbidden');
    }
    try {
        $raw = file_get_contents('php://input');
        $incoming = json_decode($raw, true);
        if (is_array($incoming) && $getfile !== '') {
            $dims_file = get_dims_file();
            $dims_all = load_json_with_lock($dims_file);
            if (!is_array($dims_all)) $dims_all = [];
            // ★ (2026-11-06 01:05) 콘텐츠 폴더별 칸("bidx|/경로") — 같은 경로의 다른 콘텐츠 폴더 책 크기로 높이를 잡던 것
            //   예전 칸(경로만)은 그대로 둠(뷰어는 이번에 잰 그림만 보내서 지우면 아직 안 본 쪽 크기가 사라짐 — 읽을 땐 새 칸이 먼저, 500개 상한으로 저절로 밀려남)
            $__dk = (int)$bidx . '|/' . ltrim((string)$getfile, '/');
            if (!isset($dims_all[$__dk]) && count($dims_all) >= 500) {
                array_shift($dims_all); // 파일 수 상한(무한증가 방지)
            }
            if (!isset($dims_all[$__dk]) || !is_array($dims_all[$__dk])) {
                $dims_all[$__dk] = [];
                // ★ (2026-11-09 10:46) 새 칸을 처음 만들 때 예전 칸(경로만)에 있던 쪽 크기를 먼저 옮겨 담음
                //   — 뷰어는 새 칸이 있으면 예전 칸을 안 보므로, 이번에 잰 쪽만 담기면 나머지 쪽 높이 예약이 사라졌음(같은 범위 검사)
                $__old = $dims_all[(string)$getfile] ?? null;
                if (is_array($__old)) {
                    foreach ($__old as $idx => $wh) {
                        if (is_array($wh) && isset($wh[0], $wh[1])) {
                            $w = (int)$wh[0]; $h = (int)$wh[1];
                            if ($w > 0 && $h > 0 && $w <= 20000 && $h <= 40000) $dims_all[$__dk][(string)(int)$idx] = [$w, $h];
                        }
                    }
                }
            }
            foreach ($incoming as $idx => $wh) {
                if (is_array($wh) && isset($wh[0], $wh[1])) {
                    $w = (int)$wh[0]; $h = (int)$wh[1];
                    if ($w > 0 && $h > 0 && $w <= 20000 && $h <= 40000) {
                        $dims_all[$__dk][(string)(int)$idx] = [$w, $h];
                    }
                }
            }
            save_json_with_lock($dims_file, $dims_all);
        }
    } catch (\Throwable $e) { /* best-effort: 실패 무시 (뷰잉 무영향) */ }

} elseif ($mode === "delete_epub_progress") {
    // ✅ EPUB 읽기 진행 삭제
    $progress_file = get_epub_progress_file();
    $progress = load_json_with_lock($progress_file);
    unset($progress[$getfile], $progress[mc_prog_key($rec_bidx, $getfile)], $progress[ltrim((string)$getfile, '/')]); // ★ (2026-11-06 00:50) 콘텐츠 폴더별 새 키와 예전 키(뷰어 키는 앞 '/' 없음 — 예전엔 '/' 붙은 경로로 지우면 안 지워졌음)
    save_json_with_lock($progress_file, $progress);
    safe_redirect($_SERVER["HTTP_REFERER"] ?? 'index.php');

} elseif ($mode === "delete_txt_progress") {
    // ✅ TXT 읽기 진행 삭제
    $progress_file = get_txt_progress_file();
    $progress = load_json_with_lock($progress_file);
    unset($progress[$getfile], $progress[mc_prog_key($rec_bidx, $getfile)], $progress[ltrim((string)$getfile, '/')]); // ★ (2026-11-06 00:50) 위와 같음
    save_json_with_lock($progress_file, $progress);
    safe_redirect($_SERVER["HTTP_REFERER"] ?? 'index.php');

} elseif ($mode === "set_cover") {
    // ★ (2026-11-04 07:44) 표지 지정은 관리자만(펜닐) — 일반 회원이 공용 폴더 표지([cover].jpg)를 바꿀 수 있었음
    if (trim((string)($_SESSION['user_group'] ?? '')) !== 'admin') simple_error_exit(403, __('err_access_denied'));
    // ★ (2026-11-02 20:07) 빈 경로('', '/', '.')면 거절 — 콘텐츠 폴더 맨 위가 대상이 되어 그 '부모' 폴더(콘텐츠 폴더 밖)에 [cover].jpg 를 쓰던 것
    if (trim(str_replace('\\', '/', (string)$getfile), '/.') === '') simple_error_exit(400, __('err_invalid_path'));
    // ✅ 경로 검증 추가
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false || !mc_path_allowed($getfile)) { // ★ (2026-10-30) 폴더 권한도 확인
        // ✅ 에러 응답 통일: simple_error_exit() 사용
        simple_error_exit(403, __('err_invalid_path'));
    }
    
    // ✅ dirname() 사용으로 경로 조합 단순화 및 안전성 확보
    // 기존: str_replace($title, "", $base_file) → 경로 내 동일 문자열 모두 제거되는 버그
    // 개선: dirname()으로 부모 폴더만 정확히 추출 + 경로 구분자 정규화
    $base_folder = rtrim(dirname($base_file), '/\\') . '/';
    $cover_file = $base_folder . "[cover].jpg";
    $__cover_written = false;
    
    if (preg_match('/\.(zip|cbz)$/i', $base_file)) {
        $list = [];
        $zip = new ZipArchive;
        if ($zip->open($base_file) === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = mc_zip_name($zip, $i); // ★ (2026-10-31) 옛 ZIP 한글·일본어 이름
                // ✅ is_image_file() 함수 사용 (2026-01-11)
                if (is_image_file($name)) {
                    $list[$i] = $name;
                }
            }
            $list = n_sort($list);
            if (!empty($list)) {
                $image_data = mc_zip_from_name($zip, reset($list));
                if ($image_data !== false) {
                    $cover_output = function_exists('mc_gd_from_string') ? mc_gd_from_string($image_data) : @imagecreatefromstring($image_data); // ★ (2026-11-02 11:36) 너무 큰 그림은 건너뜀
                    if ($cover_output !== false) {
                        if (imagejpeg($cover_output, $cover_file)) $__cover_written = true; // ★ (2026-11-04 09:43) 실제로 썼는지
                        imagedestroy($cover_output);
                    }
                }
            }
            $zip->close();
        }
    } elseif (preg_match('/\.(rar|cbr|7z|cb7)$/i', $base_file)) {
        // ✅ [RAR/7Z 지원] ArchiveHandler로 cover 생성 (ZIP 분기와 동일 동작)
        if (class_exists('ArchiveHandler')) {
            try {
                $_h = new ArchiveHandler($base_file);
                $image_data = $_h->extractFirstImage();
                if ($image_data !== false && $image_data !== null) {
                    $cover_output = function_exists('mc_gd_from_string') ? mc_gd_from_string($image_data) : @imagecreatefromstring($image_data); // ★ (2026-11-02 11:36) 너무 큰 그림은 건너뜀
                    if ($cover_output !== false) {
                        if (imagejpeg($cover_output, $cover_file)) $__cover_written = true; // ★ (2026-11-04 09:43) 실제로 썼는지
                        imagedestroy($cover_output);
                    }
                }
            } catch (Exception $e) {
                // cover 생성 실패는 치명적이지 않음 (북마크는 계속 진행)
            }
        }
    } elseif (function_exists('mc_media_file_cover_data') && is_file($base_file) && (is_video_file($base_file) || preg_match('/\.(pdf|epub|txt|hwpx?|docx?|xlsx?|pptx?)$/i', $base_file))) {
        // ★ (2026-11-08 fix80, 펜닐 요청) 동영상·PDF·디지털도서 — 영상 썸네일·같은 이름 그림·파일 안 표지(EPUB 표지 등)를 폴더 표지로
        $__d = mc_media_file_cover_data($base_file);
        if ($__d !== false && function_exists('mc_write_folder_cover') && mc_write_folder_cover($base_folder, $__d)) $__cover_written = true;
    } else {
        $list = [];
        if (is_dir($base_file)) {
            $iterator = new DirectoryIterator($base_file);
            foreach ($iterator as $jpgfile) {
                if ($jpgfile->isDot()) continue;
                // ✅ is_image_file() 함수 사용 (2026-01-11)
                if (is_image_file($jpgfile->getFilename()) && !(function_exists('mc_folder_nonpage') && mc_folder_nonpage($jpgfile->getFilename()))) { // ★ (2026-11-02 09:56) 표지·영상 썸네일 말고 첫 쪽(뷰어와 같은 기준)
                    $list[] = $base_file . "/" . $jpgfile->getFilename();
                }
            }
            $list = n_sort($list);
            if (!empty($list)) {
                $image_data = @file_get_contents(reset($list));
                if ($image_data !== false) {
                    $cover_output = function_exists('mc_gd_from_string') ? mc_gd_from_string($image_data) : @imagecreatefromstring($image_data); // ★ (2026-11-02 11:36) 너무 큰 그림은 건너뜀
                    if ($cover_output !== false) {
                        if (imagejpeg($cover_output, $cover_file)) $__cover_written = true; // ★ (2026-11-04 09:43) 실제로 썼는지
                        imagedestroy($cover_output);
                    }
                }
            }
        }
    }
    // ★ (2026-11-04 09:43) 아무것도 못 썼으면(동영상·그림 없음·GD 실패) '설정됨' 대신 실패 — 전엔 늘 '설정됨'
    if (!$__cover_written) { http_response_code(422); echo __('viewer_cover_none'); exit; }
    echo __('bookmark_set');

} elseif ($mode === "set_cover_img") {
    // ★ (2026-11-08 fix80, 펜닐 요청) 보고 있는 동영상 장면·PDF 쪽을 그 폴더의 표지([cover].jpg)로 — 화면 그림(JPEG/PNG)을 POST 로 받음
    //   set_cover 와 같은 조건: 관리자만 · CSRF 토큰(위 delete_modes) · 빈 경로 거절 · 경로·폴더 권한 · 그림은 GD 로 다시 그려 저장(그림이 아니면 거절)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') simple_error_exit(405, 'Method Not Allowed');
    if (trim((string)($_SESSION['user_group'] ?? '')) !== 'admin') simple_error_exit(403, __('err_access_denied'));
    if (trim(str_replace('\\', '/', (string)$getfile), '/.') === '') simple_error_exit(400, __('err_invalid_path'));
    $base_file = validate_file_path($getfile, $base_dir);
    if ($base_file === false || !is_file($base_file) || !mc_path_allowed($getfile)) simple_error_exit(403, __('err_invalid_path'));
    $__img = $_POST['image'] ?? '';
    $__d = false;
    if (is_string($__img) && strlen($__img) < 12 * 1048576 && preg_match('#^data:image/(?:jpeg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#', $__img, $__m)) $__d = base64_decode(preg_replace('/\s+/', '', $__m[1]), true);
    if (!is_string($__d) || $__d === '' || !function_exists('mc_write_folder_cover') || !mc_write_folder_cover(rtrim(dirname($base_file), '/\\'), $__d)) { http_response_code(422); echo __('viewer_cover_none'); exit; }
    echo __('bookmark_set');

} elseif ($mode === "add_favorite") {
    // ✅ 즐겨찾기 추가 (토큰 불필요, 세션만 확인)
    if (!verify_state_change_request(false)) {
        json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
    
    $favorites_arr = load_json_with_lock($favorites_file);
    
    // 이미 존재하면 추가하지 않음
    $__fk = mc_rec_key_for_write($favorites_arr, $bidx, $getfile); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 칸
    if (isset($favorites_arr[$__fk])) {
        json_response(['success' => true, 'action' => 'exists', 'message' => __('bookmark_already_exists')]);
    }
    
    // 최대 개수 초과 시 추가 차단
    if (count($favorites_arr) >= (int)$max_favorites) {
        json_response(['success' => false, 'error' => __('favorite_max_reached', $max_favorites)]);
    }
    
    $favorites_arr[$__fk] = [
        'added_at' => time(),
        'bidx' => $bidx
    ];
    save_json_with_lock($favorites_file, $favorites_arr);
    json_response(['success' => true, 'action' => 'added', 'message' => __('bookmark_added')]);

} elseif ($mode === "delete_favorite") {
    // ✅ 즐겨찾기 삭제 (토큰 필수 - delete_modes에 포함됨)
    $favorites_arr = load_json_with_lock($favorites_file);
    $__fk = mc_rec_find($favorites_arr, $rec_bidx, $getfile); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 것만
    if ($__fk !== null) unset($favorites_arr[$__fk]);
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
    
    $__fk = mc_rec_find($favorites_arr, $bidx, $getfile); // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 것만 켜고 끔(예전: 다른 콘텐츠 폴더의 같은 경로 즐겨찾기를 지웠음)
    if ($__fk !== null) {
        // 이미 있으면 삭제
        unset($favorites_arr[$__fk]);
        save_json_with_lock($favorites_file, $favorites_arr);
        json_response(['success' => true, 'action' => 'deleted', 'is_favorite' => false, 'message' => __('bookmark_deleted'), 'filename' => $filename, 'viewer' => $viewer]);
    } else {
        // 없으면 추가
        if (count($favorites_arr) >= (int)$max_favorites) {
            json_response(['success' => false, 'error' => __('favorite_max_reached', $max_favorites)]);
        }
        $favorites_arr[mc_rec_key_for_write($favorites_arr, $bidx, $getfile)] = [
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
    $is_favorite = mc_rec_find($favorites_arr, $bidx, $getfile) !== null; // ★ (2026-11-06 00:50) 이 콘텐츠 폴더의 것
    json_response(['success' => true, 'is_favorite' => $is_favorite]);

} elseif ($mode === "list_favorites") {
    // ✅ 즐겨찾기 목록 반환 (bfcache 복원 시 동기화용)
    if (!verify_state_change_request(false)) {
        json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
    $favorites_arr = load_json_with_lock($favorites_file);
    // ★ (2026-11-06 00:50) 'favorites' 는 경로 목록 그대로(키 앞 "bidx|" 는 뗌) — 요청에 bidx 가 있으면 그 콘텐츠 폴더 것만(목록 화면 별 맞추기·즐겨찾기 화면 지운 뒤 확인)
    $__want = (isset($_GET['bidx']) || isset($_POST['bidx'])) ? $rec_bidx : null;
    $__paths = [];
    foreach ($favorites_arr as $__k => $__v) { [$__b, $__p] = mc_rec_split($__k, $__v); if ($__want === null || $__b === $__want) $__paths[$__p] = true; }
    json_response(['success' => true, 'favorites' => array_map('strval', array_keys($__paths))]);

} else {
    // 기본: 북마크 저장 (세션 사용자만 허용, 토큰 불필요)
    if (!verify_state_change_request(false)) {
        // ✅ 에러 응답 통일: simple_error_exit() 사용
        simple_error_exit(403, 'Forbidden');
    }
    
    $bookmark_arr = load_json_with_lock($bookmark_file);
    $__bk = mc_rec_key_for_write($bookmark_arr, $bidx, $getfile); // ★ (2026-11-06 00:50) 자동 저장과 같음 — 아래 $bookmark_arr[$__bk] 는 예전 $bookmark_arr[$getfile] 자리
    // ✅ max_bookmark 개수 초과 시 가장 오래된 항목 제거 (현재 파일이 '새 항목'일 때만 — 기존 재저장 시 개수 유지)
    if (!isset($bookmark_arr[$__bk]) && count($bookmark_arr) >= (int)$max_bookmark) {
        mc_bm_evict_oldest($bookmark_arr); // ★ (2026-11-02 08:56) 가장 오래 안 본 것부터(전에는 처음 넣은 것 — 매일 보는 책이 먼저 밀려났음)
    }
    if (!is_array($bookmark_arr[$__bk] ?? null)) $bookmark_arr[$__bk] = []; // ★ (2026-11-02 09:56) 옛 문자열 형식 기록("image5")이면 아래 줄이 500 — 배열로 새로
    $bookmark_arr[$__bk]['bookmark'] = sanitize_bookmark($_GET['bookmark'] ?? 'image0');
    $bookmark_arr[$__bk]['viewer'] = sanitize_viewer($_GET['viewer'] ?? 'toon');
    $bookmark_arr[$__bk]['page_order'] = sanitize_page_order($_GET['page_order'] ?? '0');
    $bookmark_arr[$__bk]['bidx'] = $bidx;
    $bookmark_arr[$__bk]['t'] = time(); // ★ (2026-11-02 08:56) 마지막 저장 시각(밀어낼 순서용 — 표시 순서는 그대로)
    save_json_with_lock($bookmark_file, $bookmark_arr);

    // ★ (2026-10-29) 라이브러리 읽기 기록(시리즈 이어보기·다 읽음 표시용). 실패해도 기존 저장에는 영향 없음
    try {
        if (is_file(__DIR__ . '/library.php')) {
            require_once __DIR__ . '/library.php';
            if (function_exists('mc_lib_record_read')) {
                mc_lib_record_read($_SESSION['user_id'] ?? '', $bidx, $getfile, mc_lib_page_from_mark(sanitize_bookmark($_GET['bookmark'] ?? '')) /* ★ (2026-11-02) 걸러진 값으로 — 배열이 오면 경고 났음 */, (is_string($_GET['total'] ?? null) && ctype_digit($_GET['total'])) ? (int)$_GET['total'] : 0);
            }
        }
    } catch (\Throwable $e) { /* 무시 */ }
    
    // ✅ XSS 방어: 출력 이스케이프
    $bookmark_num = str_replace("image", "", sanitize_bookmark($_GET['bookmark'] ?? 'image0'));
    echo "#" . h($bookmark_num) . "-OK";
}
?>