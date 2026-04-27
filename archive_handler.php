<?php
/**
 * myComix 압축 파일 핸들러
 * ZIP, RAR, 7Z 등 다양한 압축 형식 처리
 * 
 * @version 1.2 - escapeArg() 함수 추가로 명령 인젝션 방지 강화
 * @date 2026-01-20
 */

class ArchiveHandler {
    private $file_path;
    private $type;
    private $temp_dir;
    
    // 외부 툴 경로 (config.php에서 로드)
    private static $unrar_path = '';
    private static $sevenzip_path = '';
    
    /**
     * 플랫폼 독립적인 셸 인자 이스케이프
     * Windows에서는 따옴표로 감싸고, Linux/Mac에서는 escapeshellarg 사용
     * 
     * @param string $arg 이스케이프할 인자
     * @return string 이스케이프된 인자
     */
    private static function escapeArg($arg) {
        // 공용 함수가 있으면 사용, 없으면 자체 구현
        if (function_exists('escape_shell_arg_safe')) {
            return escape_shell_arg_safe($arg);
        }

        $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
        
        if ($is_windows) {
            // Windows: 따옴표 이스케이프 후 전체를 따옴표로 감싸기
            // 내부 따옴표와 백슬래시 처리
            $arg = str_replace('"', '""', $arg);
            return '"' . $arg . '"';
        } else {
            // Linux/Mac: escapeshellarg 사용
            return escapeshellarg($arg);
        }
    }
    
    /**
     * 외부 툴 경로 설정
     */
    public static function configure($unrar_path = '', $sevenzip_path = '') {
        self::$unrar_path = $unrar_path;
        self::$sevenzip_path = $sevenzip_path;
    }
    
    /**
     * 특정 압축 형식 지원 여부 확인
     */
    public static function isSupported($extension) {
        $ext = strtolower($extension);
        
        switch ($ext) {
            case 'zip':
            case 'cbz':
                return true; // PHP 기본 지원
                
            case 'rar':
            case 'cbr':
                return !empty(self::$unrar_path) && (is_executable(self::$unrar_path) || file_exists(self::$unrar_path));
                
            case '7z':
            case 'cb7':
                return !empty(self::$sevenzip_path) && (is_executable(self::$sevenzip_path) || file_exists(self::$sevenzip_path));
                
            default:
                return false;
        }
    }
    
    /**
     * 지원되는 모든 압축 확장자 반환
     */
    public static function getSupportedExtensions() {
        $exts = ['zip', 'cbz'];
        
        if (self::isSupported('rar')) {
            $exts[] = 'rar';
            $exts[] = 'cbr';
        }
        
        if (self::isSupported('7z')) {
            $exts[] = '7z';
            $exts[] = 'cb7';
        }
        
        return $exts;
    }
    
    /**
     * 파일 확장자 패턴 생성
     */
    public static function getExtensionPattern() {
        $exts = self::getSupportedExtensions();
        return '/\.(' . implode('|', $exts) . ')$/i';
    }
    
    /**
     * 생성자
     */
    public function __construct($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception(__("err_file_not_found") . ": {$file_path}");
        }
        
        $this->file_path = $file_path;
        $this->type = $this->detectType();
        
        if (!self::isSupported($this->type)) {
            throw new Exception(__("archive_unsupported_format") . ": {$this->type}");
        }
    }
    
    /**
     * 파일 타입 감지
     */
    private function detectType() {
        $ext = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));
        
        // 확장자 기반 판별
        switch ($ext) {
            case 'zip':
            case 'cbz':
                return 'zip';
            case 'rar':
            case 'cbr':
                return 'rar';
            case '7z':
            case 'cb7':
                return '7z';
        }
        
        // 매직 바이트 확인
        $fp = fopen($this->file_path, 'rb');
        if ($fp) {
            $magic = fread($fp, 8);
            fclose($fp);
            
            if (substr($magic, 0, 4) === "PK\x03\x04") return 'zip';
            if (substr($magic, 0, 4) === "Rar!") return 'rar';
            if (substr($magic, 0, 6) === "7z\xBC\xAF\x27\x1C") return '7z';
        }
        
        return $ext;
    }
    
    /**
     * 압축 파일 내 파일 목록 가져오기
     * 
     * @param string $filter 정규식 필터 (예: '/\.(jpg|png)$/i')
     * @return array 파일 목록
     */
    public function listFiles($filter = null) {
        switch ($this->type) {
            case 'zip':
                return $this->listZipFiles($filter);
            case 'rar':
                return $this->listRarFiles($filter);
            case '7z':
                return $this->list7zFiles($filter);
            default:
                return [];
        }
    }
    
    /**
     * ZIP 파일 목록
     */
    private function listZipFiles($filter) {
        $files = [];
        $zip = new ZipArchive();
        
        if ($zip->open($this->file_path) === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                
                // 디렉토리 제외
                if (substr($name, -1) === '/') continue;
                
                // 필터 적용
                if ($filter && !preg_match($filter, $name)) continue;
                
                $files[] = $name;
            }
            $zip->close();
        }
        
        return $files;
    }
    
    /**
     * RAR 파일 목록 (unrar 사용)
     */
    private function listRarFiles($filter) {
        $files = [];
        
        if (empty(self::$unrar_path)) return $files;
        
        // unrar lb: 파일 목록만 출력 (베어 포맷)
        // ✅ escapeArg()로 경로 이스케이프 (보안 강화)
        $cmd = sprintf('%s lb %s 2>&1', 
            self::escapeArg(self::$unrar_path), 
            self::escapeArg($this->file_path));
        
        exec($cmd, $output, $return_code);
        
        if ($return_code === 0) {
            foreach ($output as $line) {
                $name = trim($line);
                if (empty($name)) continue;
                
                // 디렉토리 제외 (끝이 /인 경우)
                if (substr($name, -1) === '/' || substr($name, -1) === '\\') continue;
                
                // 필터 적용
                if ($filter && !preg_match($filter, $name)) continue;
                
                $files[] = str_replace('\\', '/', $name);
            }
        }
        
        return $files;
    }
    
    /**
     * 7Z 파일 목록 (7z 사용)
     */
    private function list7zFiles($filter) {
        $files = [];
        
        if (empty(self::$sevenzip_path)) return $files;
        
        // 7z l: 파일 목록 출력
        // ✅ escapeArg()로 경로 이스케이프 (보안 강화)
        $cmd = sprintf('%s l -slt %s 2>&1', 
            self::escapeArg(self::$sevenzip_path), 
            self::escapeArg($this->file_path));
        
        exec($cmd, $output, $return_code);
        
        if ($return_code === 0) {
            $current_file = null;
            $is_dir = false;
            
            foreach ($output as $line) {
                if (strpos($line, 'Path = ') === 0) {
                    // 이전 파일 저장
                    if ($current_file !== null && !$is_dir) {
                        if (!$filter || preg_match($filter, $current_file)) {
                            $files[] = str_replace('\\', '/', $current_file);
                        }
                    }
                    
                    $current_file = substr($line, 7);
                    $is_dir = false;
                }
                
                if (strpos($line, 'Attributes = D') === 0) {
                    $is_dir = true;
                }
            }
            
            // 마지막 파일 처리
            if ($current_file !== null && !$is_dir) {
                if (!$filter || preg_match($filter, $current_file)) {
                    $files[] = str_replace('\\', '/', $current_file);
                }
            }
        }
        
        return $files;
    }
    
    /**
     * 특정 파일 추출
     * 
     * @param string $entry_name 압축 내 파일 경로
     * @return string|false 파일 데이터 또는 false
     */
    public function extractFile($entry_name) {
        switch ($this->type) {
            case 'zip':
                return $this->extractZipFile($entry_name);
            case 'rar':
                return $this->extractRarFile($entry_name);
            case '7z':
                return $this->extract7zFile($entry_name);
            default:
                return false;
        }
    }
    
    /**
     * ZIP에서 파일 추출
     */
    private function extractZipFile($entry_name) {
        $zip = new ZipArchive();
        
        if ($zip->open($this->file_path) === TRUE) {
            $data = $zip->getFromName($entry_name);
            $zip->close();
            return $data;
        }
        
        return false;
    }
    
    /**
     * RAR에서 파일 추출 (임시 디렉토리 사용)
     */
    private function extractRarFile($entry_name) {
        if (empty(self::$unrar_path)) return false;
        
        // 임시 디렉토리 생성
        $temp_dir = sys_get_temp_dir() . '/mycomix_' . md5($this->file_path . $entry_name . time());
        
        if (!mkdir($temp_dir, 0755, true)) {
            return false;
        }
        
        try {
            // ✅ OS에 따른 경로 구분자 처리 (Windows: 백슬래시, Linux/Mac: 슬래시)
            $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
            $rar_entry_name = $is_windows ? str_replace('/', '\\', $entry_name) : $entry_name;
            
            // ✅ OS에 따른 stderr 리다이렉션
            $stderr_redirect = $is_windows ? '2>NUL' : '2>/dev/null';
            
            // unrar x: 특정 파일 추출
            // ✅ escapeArg()로 경로 이스케이프 (보안 강화)
            $cmd = sprintf(
                '%s x -y -o+ %s %s %s %s',
                self::escapeArg(self::$unrar_path),
                self::escapeArg($this->file_path),
                self::escapeArg($rar_entry_name),
                self::escapeArg($temp_dir),
                $stderr_redirect
            );
            
            exec($cmd, $output, $return_code);
            
            if ($return_code === 0) {
                $extracted_file = $temp_dir . '/' . $entry_name;
                
                if (file_exists($extracted_file)) {
                    $data = file_get_contents($extracted_file);
                    $this->deleteDirectory($temp_dir);
                    return $data;
                }
            }
            
            $this->deleteDirectory($temp_dir);
            return false;
            
        } catch (Exception $e) {
            $this->deleteDirectory($temp_dir);
            return false;
        }
    }
    
    /**
     * 7Z에서 파일 추출 (stdout으로 직접 출력)
     */
    private function extract7zFile($entry_name) {
        if (empty(self::$sevenzip_path)) return false;
        
        // ✅ OS에 따른 stderr 리다이렉션
        $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
        $stderr_redirect = $is_windows ? '2>NUL' : '2>/dev/null';
        
        // 7z e -so: stdout으로 출력
        // ✅ escapeArg()로 경로 이스케이프 (보안 강화)
        $cmd = sprintf(
            '%s e -so %s %s %s',
            self::escapeArg(self::$sevenzip_path),
            self::escapeArg($this->file_path),
            self::escapeArg($entry_name),
            $stderr_redirect
        );
        
        $data = @shell_exec($cmd);
        
        return $data !== null ? $data : false;
    }
    
    /**
     * 이미지 파일 목록 가져오기 (정렬됨)
     * ✅ get_image_extensions_pattern() 헬퍼 함수 사용으로 패턴 통일 (2026-01-11)
     */
    public function getImageFiles() {
        $pattern = function_exists('get_image_extensions_pattern') 
            ? get_image_extensions_pattern() 
            : '/\.(jpg|jpeg|png|gif|webp|bmp)$/i';
        $files = $this->listFiles($pattern);
        
        // 자연 정렬
        usort($files, function($a, $b) {
            return strnatcasecmp(basename($a), basename($b));
        });
        
        return $files;
    }
    
    /**
     * 동영상 파일 목록 가져오기
     * ✅ get_video_extensions_pattern() 헬퍼 함수 사용으로 패턴 통일 (2026-01-11)
     */
    public function getVideoFiles() {
        $pattern = function_exists('get_video_extensions_pattern') 
            ? get_video_extensions_pattern() 
            : '/\.(mp4|webm|mkv|avi|mov|m4v|m2t|ts|mts|m2ts|wmv|flv)$/i';
        return $this->listFiles($pattern);
    }
    
    /**
     * 첫 번째 이미지 추출 (썸네일용)
     */
    public function extractFirstImage() {
        $images = $this->getImageFiles();
        
        if (empty($images)) {
            return false;
        }
        
        return $this->extractFile($images[0]);
    }
    
    /**
     * 압축 타입 반환
     */
    public function getType() {
        return $this->type;
    }
    
    /**
     * 파일 경로 반환
     */
    public function getFilePath() {
        return $this->file_path;
    }
    
    /**
     * 파일 개수 반환
     */
    public function getFileCount($filter = null) {
        return count($this->listFiles($filter));
    }
    
    /**
     * 이미지 개수 반환
     */
    public function getImageCount() {
        return count($this->getImageFiles());
    }
    
    /**
     * 디렉토리 삭제 (재귀)
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        
        @rmdir($dir);
    }
    
    /**
     * 정적 메서드: 압축 파일인지 확인
     */
    public static function isArchive($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, self::getSupportedExtensions());
    }
    
    /**
     * 정적 메서드: 이미지 목록 캐시 생성
     */
    public static function generateImageCache($archive_path, $cache_path = null) {
        try {
            $handler = new self($archive_path);
            $images = $handler->getImageFiles();
            
            if (empty($images)) {
                return false;
            }
            
            // 캐시 경로 결정
            if ($cache_path === null) {
                $cache_path = $archive_path . '.image_files.json';
            }
            
            // 캐시 데이터 생성
            $cache_data = [
                'type' => $handler->getType(),
                'count' => count($images),
                'files' => $images,
                'created' => time()
            ];
            
            // 썸네일 생성 시도
            $thumb_data = $handler->extractFirstImage();
            if ($thumb_data !== false) {
                // 썸네일 리사이즈 및 저장
                $thumb = @imagecreatefromstring($thumb_data);
                if ($thumb !== false) {
                    $width = imagesx($thumb);
                    $height = imagesy($thumb);
                    
                    // 정사각형 크롭
                    $size = min($width, $height);
                    $x = ($width - $size) / 2;
                    $y = ($height - $size) / 2;
                    
                    $cropped = imagecrop($thumb, ['x' => $x, 'y' => $y, 'width' => $size, 'height' => $size]);
                    if ($cropped !== false) {
                        $resized = imagecreatetruecolor(400, 400);
                        imagecopyresampled($resized, $cropped, 0, 0, 0, 0, 400, 400, $size, $size);
                        
                        ob_start();
                        imagejpeg($resized, null, 75);
                        $cache_data['thumbnail'] = base64_encode(ob_get_clean());
                        
                        imagedestroy($resized);
                        imagedestroy($cropped);
                    }
                    imagedestroy($thumb);
                }
            }
            
            // 캐시 저장
            @file_put_contents($cache_path, json_encode($cache_data, JSON_UNESCAPED_UNICODE), LOCK_EX);
            
            return true;
            
        } catch (Exception $e) {
            error_log("[ArchiveHandler] 캐시 생성 실패: " . $e->getMessage());
            return false;
        }
    }
}

// ✅ ArchiveHandler::configure()는 config.php에서 호출됨
// 이 파일이 단독으로 로드되는 경우는 없으므로 여기서 configure 불필요
?>