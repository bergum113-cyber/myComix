<?php
/**
 * myComix 캐시 유틸리티
 * 이미지 압축 및 캐시 관리 함수
 * 
 * @version 2.0 - compressImage() VIPS 지원 추가 (index.php와 동일 패턴)
 * @date 2026-01-14
 * 
 * 의존성:
 * - bootstrap.php를 통해 로드되어야 함 (config.php → $app_settings 필요)
 * - 독립 실행 시에는 기본 설정 사용
 * - $vips_path 설정 시 VIPS 우선 사용, 없으면 GD 폴백
 */

// ============================================================
// 안전한 설정 병합 함수
// ============================================================

if (!function_exists('safe_array_merge_recursive')) {
    /**
     * 안전한 재귀적 배열 병합 (깊이 제한)
     * 
     * @param array $base 기본 배열
     * @param array $override 덮어쓸 배열
     * @param int $max_depth 최대 허용 깊이 (기본: 3)
     * @param int $current_depth 현재 깊이 (내부용)
     * @return array 병합된 배열
     */
    function safe_array_merge_recursive(array $base, array $override, int $max_depth = 3, int $current_depth = 0): array {
        // 깊이 초과 시 override 값으로 대체 (재귀 중단)
        if ($current_depth >= $max_depth) {
            return $override;
        }
        
        foreach ($override as $key => $value) {
            // 키가 없거나 값 타입이 다르면 그대로 대체
            if (!isset($base[$key]) || !is_array($base[$key]) || !is_array($value)) {
                $base[$key] = $value;
            } else {
                // 둘 다 배열이면 재귀 병합
                $base[$key] = safe_array_merge_recursive($base[$key], $value, $max_depth, $current_depth + 1);
            }
        }
        
        return $base;
    }
}

// ============================================================
// 캐시 설정 로드
// ============================================================

if (!defined('CACHE_SETTINGS_LOADED')) {
    define('CACHE_SETTINGS_LOADED', true);
    
    // 기본 설정값
    $GLOBALS['CACHE_SETTINGS'] = [
        'version' => 'w1100-q80-v3',
        'max_width' => 1100,
        'quality' => [
            'high' => 83,
            'medium' => 80,
            'low' => 75,
            'mobile_high' => 80,
            'mobile_medium' => 78,
            'mobile_low' => 75
        ],
        'size_thresholds' => [
            'large' => 3000000,    // 3MB 이상 → low 품질
            'medium' => 1500000,   // 1.5MB 이상 → medium 품질
            'small' => 800000      // 800KB 이상 → high 품질
        ],
        'min_size_to_compress' => 400000,  // 400KB 이하는 압축 안함
        'min_dimensions' => [
            'width' => 1000,
            'height' => 1400
        ]
    ];
    
    // ✅ get_app_settings() 함수 사용 (global 대신 권장)
    $cache_settings_override = get_app_settings('cache_settings');
    if (!empty($cache_settings_override) && is_array($cache_settings_override)) {
        $GLOBALS['CACHE_SETTINGS'] = safe_array_merge_recursive(
            $GLOBALS['CACHE_SETTINGS'],
            $cache_settings_override,
            3  // 최대 깊이 3으로 제한
        );
    }
}

// ✅ 캐시 버전 상수 정의 (설정에서 로드)
if (!defined('CACHE_VERSION')) {
    define('CACHE_VERSION', $GLOBALS['CACHE_SETTINGS']['version'] ?? 'w1100-q80-v3');
}

// ============================================================
// 캐시 키 생성 함수
// ============================================================

if (!function_exists('cacheKeyFromPath')) {
    /**
     * 경로에서 캐시 키 생성
     * 
     * @param string $path 파일/폴더 경로
     * @return string MD5 해시 키
     */
    function cacheKeyFromPath(string $path): string {
        $p = realpath($path) ?: $path;
        $p = str_replace('\\', '/', $p);
        if (preg_match('#^[A-Z]:/#', $p)) {
            $p = strtolower(substr($p, 0, 1)) . substr($p, 1);
        }
        $p = rtrim($p, '/');
        return md5($p . '|' . CACHE_VERSION);
    }
}

// ============================================================
// 이미지 압축 함수
// ============================================================

if (!function_exists('compressImage')) {
    /**
     * 이미지 압축 (품질 최적화)
     * VIPS 우선 사용, 실패 시 GD 폴백 (index.php와 동일 패턴)
     * 
     * @param string $img_data 원본 이미지 바이너리 데이터
     * @param int|null $quality JPEG 품질 (null이면 자동 결정)
     * @param bool $force true면 크기/품질 무관하게 압축 실행
     * @return string 압축된 이미지 데이터 (또는 원본)
     * 
     * ✅ GIF 애니메이션 지원: GIF는 압축하지 않고 원본 반환
     * ✅ VIPS 지원: 설정 시 2~3배 빠른 처리
     */
    function compressImage(string $img_data, ?int $quality = null, bool $force = false): string {
        global $vips_path;
        $settings = $GLOBALS['CACHE_SETTINGS'];
        $filesize = strlen($img_data);
        
        // ✅ GIF 파일 감지 - 애니메이션 보존을 위해 원본 반환
        // GIF87a 또는 GIF89a 매직 바이트 확인
        if (strlen($img_data) >= 6) {
            $magic = substr($img_data, 0, 6);
            if ($magic === 'GIF87a' || $magic === 'GIF89a') {
                return $img_data;  // GIF는 그대로 반환
            }
        }
        
        // 압축 필요 여부 판단 (force가 아니고 작은 파일이면 패스)
        if (!$force && $filesize < $settings['min_size_to_compress']) {
            return $img_data;
        }

        // 이미지 크기 확인 (GD 없이)
        $size_info = @getimagesizefromstring($img_data);
        if ($size_info === false) {
            return $img_data;
        }
        
        $width = $size_info[0];
        $height = $size_info[1];

        // 크기가 작으면 압축 불필요 (force가 아닌 경우)
        if (!$force && 
            $width <= $settings['min_dimensions']['width'] && 
            $height <= $settings['min_dimensions']['height']) {
            return $img_data;
        }

        // 모바일 감지 (Save-Data 헤더 체크)
        $is_mobile = isset($_SERVER['HTTP_SAVE_DATA']) && $_SERVER['HTTP_SAVE_DATA'] === 'on';

        // 품질 자동 결정 (설정 기반)
        if ($quality === null) {
            $thresholds = $settings['size_thresholds'];
            $qualities = $settings['quality'];
            
            if ($filesize > $thresholds['large']) {
                $quality = $is_mobile ? $qualities['mobile_low'] : $qualities['low'];
            } elseif ($filesize > $thresholds['medium']) {
                $quality = $is_mobile ? $qualities['mobile_medium'] : $qualities['medium'];
            } elseif ($filesize > $thresholds['small']) {
                $quality = $is_mobile ? $qualities['mobile_high'] : $qualities['high'];
            } else {
                $quality = $qualities['high'];
            }
        }

        $maxWidth = $settings['max_width'];
        $compressed = null;
        
        // ✅ VIPS 우선 사용 (index.php와 동일 패턴)
        if (!empty($vips_path) && file_exists($vips_path)) {
            // 원본 확장자 감지
            $ext = 'jpg';
            if ($size_info[2] === IMAGETYPE_PNG) $ext = 'png';
            elseif ($size_info[2] === IMAGETYPE_WEBP) $ext = 'webp';
            
            $temp_in = sys_get_temp_dir() . '/compress_in_' . uniqid() . '.' . $ext;
            $temp_out = sys_get_temp_dir() . '/compress_out_' . uniqid() . '.jpg';
            
            @file_put_contents($temp_in, $img_data, LOCK_EX);
            
            // 리사이즈 필요 여부에 따라 명령 선택
            if ($width > $maxWidth) {
                // vips thumbnail: 리사이즈 + 품질 조절
                $cmd = '"' . $vips_path . '" thumbnail "' . $temp_in . '" "' . $temp_out . '" ' . $maxWidth . ' --size down -Q ' . $quality . ' 2>&1';
            } else {
                // vips jpegsave: 품질 조절만
                $cmd = '"' . $vips_path . '" jpegsave "' . $temp_in . '" "' . $temp_out . '" --Q ' . $quality . ' 2>&1';
            }
            
            exec($cmd, $output, $return_code);
            
            if ($return_code === 0 && file_exists($temp_out)) {
                $compressed = file_get_contents($temp_out);
            }
            
            @unlink($temp_in);
            @unlink($temp_out);
        }
        
        // ✅ VIPS 미설정 또는 실패 시 GD 폴백
        if ($compressed === null) {
            $img = @imagecreatefromstring($img_data);
            if ($img === false) {
                return $img_data;
            }

            // 리사이즈 (설정된 최대 너비 초과 시)
            if ($width > $maxWidth) {
                $newW = $maxWidth;
                $newH = (int)($height * ($maxWidth / $width));
                $resized = imagecreatetruecolor($newW, $newH);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $width, $height);
                imagedestroy($img);
                $img = $resized;
            }

            // JPEG로 압축
            ob_start();
            imagejpeg($img, null, $quality);
            $compressed = ob_get_clean();
            imagedestroy($img);
        }

        // 압축 효과 없으면 원본 반환 (5% 이상 감소하지 않으면)
        if (!$force && strlen($compressed) >= $filesize * 0.95) {
            return $img_data;
        }
        
        return $compressed;
    }
}

// ============================================================
// MIME 타입 감지 함수
// ============================================================

if (!function_exists('detectMimeFromBytes')) {
    /**
     * 바이너리 데이터에서 MIME 타입 감지
     * 
     * @param string $bytes 이미지 바이너리 데이터
     * @return string MIME 타입
     */
    function detectMimeFromBytes(string $bytes): string {
        if (isset($bytes[0], $bytes[1]) && $bytes[0] === "\xFF" && $bytes[1] === "\xD8") {
            return 'image/jpeg';
        }
        if (substr($bytes, 0, 8) === "\x89PNG\x0D\x0A\x1A\x0A") {
            return 'image/png';
        }
        if (substr($bytes, 0, 6) === "GIF87a" || substr($bytes, 0, 6) === "GIF89a") {
            return 'image/gif';
        }
        if (substr($bytes, 0, 4) === "RIFF" && substr($bytes, 8, 4) === "WEBP") {
            return 'image/webp';
        }
        return 'application/octet-stream';
    }
}

// ============================================================
// ZIP 이미지 캐시 사전 생성 함수
// ============================================================

if (!function_exists('warmupZipImageCache')) {
    /**
     * ZIP 이미지 캐시 사전 생성 (viewer.php, warmup.php 공용)
     * 
     * @param string $base_file ZIP 파일 경로
     * @param array $image_files 이미지 파일 목록
     * @param int $count 처리할 이미지 수 (기본: 20)
     * @param string|null $user_id 사용자 ID (null이면 세션에서 가져옴)
     * @return string|null 캐시 디렉토리 경로 또는 null
     */
    function warmupZipImageCache(string $base_file, array $image_files, int $count = 20, ?string $user_id = null): ?string {
        // 사용자 ID 결정 (파라미터 > 세션 > 기본값)
        if ($user_id === null) {
            $user_id = $_SESSION['user_id'] ?? 'guest';
        }
        $user_id = preg_replace('/[^a-zA-Z0-9]/', '', $user_id);
        
        $zip_hash = cacheKeyFromPath($base_file);
        $cache_dir = __DIR__ . "/cache/{$user_id}/{$zip_hash}";

        // 캐시 디렉토리 준비
        clearstatcache();
        if (file_exists($cache_dir) && !is_dir($cache_dir)) {
            @unlink($cache_dir);
        }

        if (!is_dir($cache_dir)) {
            if (!@mkdir($cache_dir, 0755, true) && !is_dir($cache_dir)) {
                return null;
            }
        }

        // ZIP 파일 열기
        $zip = new ZipArchive;
        if ($zip->open($base_file) !== TRUE) {
            return null;
        }
        
        $processed = 0;
        $total = min($count, count($image_files));
        
        for ($i = 0; $i < $total; $i++) {
            $filename = basename($image_files[$i]);
            $cache_img = "{$cache_dir}/{$filename}";
            $tmp_cache_img = "{$cache_img}.tmp";

            // 이미 캐시 있으면 스킵
            if (!file_exists($cache_img)) {
                $img_data = $zip->getFromName($image_files[$i]);
                if ($img_data !== false) {
                    $compressed = compressImage($img_data, null, false);
                    
                    // 원자적 쓰기 (파일 락 + 임시 파일)
                    $fp = @fopen($tmp_cache_img, 'c+b');
                    if ($fp) {
                        $locked = false;
                        for ($retry = 0; $retry < 3; $retry++) {
                            if (flock($fp, LOCK_EX)) {
                                $locked = true;
                                break;
                            }
                            usleep(100000);
                        }

                        if ($locked) {
                            ftruncate($fp, 0);
                            fwrite($fp, $compressed);
                            fflush($fp);
                            flock($fp, LOCK_UN);
                            fclose($fp);

                            // 기존 파일 삭제 후 이동
                            if (file_exists($cache_img)) {
                                @unlink($cache_img);
                                usleep(100000);
                            }

                            if (!@rename($tmp_cache_img, $cache_img)) {
                                if (@copy($tmp_cache_img, $cache_img)) {
                                    @unlink($tmp_cache_img);
                                }
                            }
                            @touch($cache_img);
                            $processed++;
                        } else {
                            fclose($fp);
                            @unlink($tmp_cache_img);
                        }
                    }
                    unset($img_data, $compressed);
                }
            } else {
                $processed++;
            }
            
            // 메모리 정리 (5개마다)
            if (($i + 1) % 5 === 0) {
                gc_collect_cycles();
            }
        }
        
        $zip->close();
        unset($zip);
        gc_collect_cycles();
        
        return $cache_dir;
    }
}

// ============================================================
// 폴더 캐시 재생성 함수 (index.php에서 이동)
// ============================================================

if (!function_exists('rebuild_folder_caches')) {
    /**
     * 폴더 캐시 파일 삭제 (재생성 트리거)
     * 
     * base_dir 하위의 모든 .folder_cache.json, .filelist_cache.json 삭제
     * 다음 접근 시 캐시가 자동 재생성됨
     * 
     * @param string $base_dir 기준 디렉토리
     * @return int 삭제된 캐시 파일 수
     * 
     * 사용 예시 (index.php AJAX):
     *   if (get_param('rebuild_cache', 'string') === '1') {
     *       $deleted = rebuild_folder_caches($base_dir);
     *       echo json_encode(['success' => true, 'deleted_count' => $deleted]);
     *   }
     */
    function rebuild_folder_caches(string $base_dir): int {
        $count = 0;
        
        if (empty($base_dir) || !is_dir($base_dir)) {
            return $count;
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    // .folder_cache.json 삭제
                    $cache_file = $item->getPathname() . '/.folder_cache.json';
                    if (file_exists($cache_file)) {
                        if (@unlink($cache_file)) {
                            $count++;
                        }
                    }
                    // .filelist_cache.json 삭제
                    $list_cache = $item->getPathname() . '/.filelist_cache.json';
                    if (file_exists($list_cache)) {
                        if (@unlink($list_cache)) {
                            $count++;
                        }
                    }
                }
            }
            
            // 루트 폴더의 .filelist_cache.json도 삭제
            $root_cache = $base_dir . '/.filelist_cache.json';
            if (file_exists($root_cache)) {
                if (@unlink($root_cache)) {
                    $count++;
                }
            }
            
        } catch (Exception $e) {
            error_log("[cache_util] 캐시 재생성 중 오류: " . $e->getMessage());
        }
        
        return $count;
    }
}

// ============================================================
// 확장/디버깅용 캐시 설정 조회 함수
// ============================================================
// 
// 📌 이 함수들은 myComix 코어에서 직접 사용되지 않습니다.
//    관리자 도구, 디버깅, 커스텀 캐시 로직에서 활용하기 위해 제공됩니다.
// 
// 사용 가능한 함수:
//   - getCacheSettings()              : 전체 캐시 설정 배열 반환
//   - getCacheQuality($size, $mobile) : 파일 크기 기반 압축 품질 계산
// 
// 예시 - 관리자 페이지에서 현재 설정 표시:
//   $settings = getCacheSettings();
//   echo "최대 너비: " . $settings['max_width'] . "px";
//   echo "기본 품질: " . $settings['quality']['high'];
// ============================================================

if (!function_exists('getCacheSettings')) {
    /**
     * 현재 캐시 설정 반환
     * 
     * @return array 캐시 설정 배열
     * 
     * @note 확장/디버깅용 - 코어에서는 직접 $GLOBALS['CACHE_SETTINGS'] 참조
     * 
     * 반환 배열 구조:
     *   - version: 캐시 버전 문자열
     *   - max_width: 이미지 최대 너비
     *   - quality: 품질 설정 (high, medium, low, mobile_*)
     *   - size_thresholds: 크기별 품질 임계값
     *   - min_size_to_compress: 압축 최소 크기
     *   - min_dimensions: 최소 이미지 크기
     */
    function getCacheSettings(): array {
        return $GLOBALS['CACHE_SETTINGS'];
    }
}

if (!function_exists('getCacheQuality')) {
    /**
     * 파일 크기에 따른 압축 품질 반환
     * 
     * @param int $filesize 파일 크기 (바이트)
     * @param bool $is_mobile 모바일 여부
     * @return int JPEG 품질 (0-100)
     * 
     * @note 확장/디버깅용 - 코어에서는 compressImage() 내부에서 직접 계산
     * 
     * 사용 예시:
     *   $quality = getCacheQuality(filesize($image), $is_mobile);
     *   echo "적용될 압축 품질: {$quality}%";
     */
    function getCacheQuality(int $filesize, bool $is_mobile = false): int {
        $settings = $GLOBALS['CACHE_SETTINGS'];
        $thresholds = $settings['size_thresholds'];
        $qualities = $settings['quality'];
        
        if ($filesize > $thresholds['large']) {
            return $is_mobile ? $qualities['mobile_low'] : $qualities['low'];
        } elseif ($filesize > $thresholds['medium']) {
            return $is_mobile ? $qualities['mobile_medium'] : $qualities['medium'];
        } elseif ($filesize > $thresholds['small']) {
            return $is_mobile ? $qualities['mobile_high'] : $qualities['high'];
        }
        
        return $qualities['high'];
    }
}