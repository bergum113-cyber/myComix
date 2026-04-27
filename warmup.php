<?php
/**
 * myComix - Cache Warmup Script
 * 
 * 이미지 캐시 사전 생성 스크립트
 * ZIP 파일 또는 이미지 폴더의 캐시를 미리 생성하여 뷰어 로딩 속도 향상
 * 
 * @version 2.5 - 주석 개선, 버전 정보 업데이트
 * @date 2026-01-11
 * 
 * 주요 보안 개선사항:
 * - XSS 취약점 제거: 에러 메시지에서 경로 정보 완전 제거
 * - 경로 순회 공격 방지: validate_file_path() 통합 사용
 * - 입력 검증 강화: security_helpers.php 통합
 * - user_id 보안: 세션에서만 가져옴
 */

// 출력 버퍼링 시작
ob_start();

// ✅ bootstrap.php 사용으로 통일 (정상 세션 시작)
// ✅ cache_util.php는 bootstrap.php에서 자동 로드됨
require_once __DIR__ . '/bootstrap.php';

// ✅ init_bidx() 사용으로 다중 폴더 지원
$bidx = init_bidx();

// 버퍼 정리
ob_end_clean();

// 타임아웃 제한 해제 (대용량 파일 처리)
set_time_limit(0);

// 응답 헤더 설정
header("Content-Type: text/plain; charset=utf-8");
header("X-Content-Type-Options: nosniff");

// ============================================================
// 사용자 ID - 세션에서 가져옴 (보안 강화)
// ============================================================

$user_id = preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['user_id'] ?? 'guest');
if (empty($user_id) || strlen($user_id) > 50) {
    $user_id = 'guest';
}

// ============================================================
// 이미지 폴더 캐시 생성 함수
// ============================================================

/**
 * 이미지 폴더의 캐시 파일 생성
 * 
 * @param string $src_dir 원본 이미지 폴더 경로
 * @param string $user_id 사용자 ID (캐시 폴더 구분용)
 * @param int $limit 처리할 최대 이미지 수
 * @return string|false 캐시 디렉토리 경로 또는 false (실패 시)
 */
function warmupImageFolderCache($src_dir, $user_id, $limit = 10) {
    if (!is_dir($src_dir)) {
        error_log("[warmup] 디렉토리 없음: {$src_dir}");
        return false;
    }
    
    // 캐시 키 생성 (경로 기반 해시)
    $zip_hash  = cacheKeyFromPath($src_dir);
    $cache_dir = __DIR__ . "/cache/{$user_id}/{$zip_hash}";
    
    // 캐시 디렉토리 생성
    if (!is_dir($cache_dir)) {
        if (!mkdir($cache_dir, 0755, true)) {
            error_log("[warmup] 캐시 디렉토리 생성 실패: {$cache_dir}");
            return false;
        }
    }

    $count = 0;
    $success = 0;
    
    try {
        foreach (new DirectoryIterator($src_dir) as $fi) {
            if (!$fi->isFile()) continue;
            
            // ✅ is_image_file() 헬퍼 함수 사용으로 패턴 통일 (2026-01-11)
            if (!is_image_file($fi->getFilename())) {
                continue;
            }

            $src  = $fi->getPathname();
            $dest = $cache_dir . '/' . $fi->getFilename();

            if (!file_exists($dest)) {
                $raw = @file_get_contents($src);
                
                if ($raw !== false) {
                    $compressed = compressImage($raw, null, true);
                    $tmp = $dest . '.tmp.' . getmypid();
                    
                    if (@file_put_contents($tmp, $compressed, LOCK_EX) !== false) {
                        if (@rename($tmp, $dest)) {
                            @chmod($dest, 0644);
                            $success++;
                        } else {
                            if (@copy($tmp, $dest)) {
                                @chmod($dest, 0644);
                                $success++;
                            }
                            @unlink($tmp);
                        }
                    }
                }
            } else {
                $success++;
            }

            if (++$count >= $limit) break;
        }
    } catch (Exception $e) {
        error_log("[warmup] 폴더 캐시 생성 오류: " . $e->getMessage());
        return false;
    }
    
    // error_log("[warmup] 폴더 캐시 완료: {$success}/{$count} 파일");
    
    return $cache_dir;
}

// ============================================================
// 메인 로직: 파라미터 검증 및 캐시 생성
// ============================================================

// 필수 파라미터 확인
if (!isset($_GET['file'])) {
    if (!headers_sent()) http_response_code(400);
    exit(__("warmup_missing_params"));
}

// ✅ 파일 경로 디코딩 (이중 인코딩 대응)
// 브라우저/클라이언트에 따라 URL이 이중 인코딩될 수 있음
// 예: viewer.js에서 encodeURIComponent() 2회 호출 시
$getfile = decode_file_param($_GET['file'] ?? '');

// 특수 접미사 제거 (_imgfolder는 폴더 표시용 마커)
$getfile = preg_replace('/_imgfolder$/', '', $getfile);

// ✅ validate_file_path() 사용으로 경로 검증 통합
$base_file = validate_file_path($getfile, $base_dir);
if ($base_file === false) {
    if (!headers_sent()) http_response_code(403);
    exit(__("warmup_invalid_path"));
}

// ============================================================
// 케이스 1: 이미지 폴더 처리
// ============================================================

if (is_dir($base_file)) {
    $result = warmupImageFolderCache($base_file, $user_id, 10);
    
    if ($result === false) {
        if (!headers_sent()) http_response_code(500);
        exit(__("warmup_cache_error"));
    }
    
    if (!headers_sent()) http_response_code(200);
    exit(__("warmup_folder_cache_done"));
}

// ============================================================
// 케이스 2: ZIP/CBZ 파일 처리
// ============================================================

$cache_file = $base_file . '.image_files.json';

// 파일 존재 확인
if (!file_exists($base_file)) {
    if (!headers_sent()) http_response_code(404);
    exit(__("err_file_not_found"));
}

// ✅ 캐시 파일이 없는 경우 (동영상 ZIP 등) - 정상 응답
if (!file_exists($cache_file)) {
    if (!headers_sent()) http_response_code(200);
    exit(__("warmup_cache_unnecessary"));
}

// 이미지 목록 로드
$image_files = @json_decode(file_get_contents($cache_file), true);

if (!is_array($image_files)) {
    if (!headers_sent()) http_response_code(500);
    exit(__("warmup_image_list_fail"));
}

// ✅ warmupZipImageCache는 cache_util.php에서 제공
try {
    warmupZipImageCache($base_file, $image_files, 10, $user_id);
    
    if (!headers_sent()) http_response_code(200);
    exit(__("warmup_zip_cache_done"));
    
} catch (Exception $e) {
    error_log("[warmup] ZIP 캐시 오류: " . $e->getMessage());
    
    if (!headers_sent()) http_response_code(500);
    exit(__("warmup_cache_error"));
}