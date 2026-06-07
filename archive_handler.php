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
        // ✅ [한글/유니코드] -scu: 목록 출력을 유니코드로 (Windows UnRAR.exe는 UTF-16BE 출력)
        $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
        $stdin_block = $is_windows ? '< NUL' : '< /dev/null';
        $cmd = sprintf('%s lb -scu -p- -y %s 2>&1 %s', 
            self::escapeArg(self::$unrar_path), 
            self::escapeArg($this->file_path),
            $stdin_block);

        $output = [];
        $return_code = -1;
        exec($cmd, $output, $return_code);
        
        if ($return_code === 0) {
            foreach ($output as $line) {
                // ✅ [인코딩 정규화] UTF-16(Windows -scu)/CP949 → UTF-8.
                //    trim 전에 normalize: UTF-16은 끝에 \0가 있어 trim이 부정확할 수 있음
                $name = self::normalizeEntryName($line);
                $name = trim($name);
                if (empty($name)) continue;
                
                // 디렉토리 제외 (끝이 /인 경우)
                if (substr($name, -1) === '/' || substr($name, -1) === '\\') continue;
                
                // 필터 적용 (백슬래시→슬래시 변환 후 매칭)
                $name = str_replace('\\', '/', $name);
                if ($filter && !preg_match($filter, $name)) continue;
                
                $files[] = $name;
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
        // ✅ [hang 방지] stdin 차단 — 암호 걸린 7z에서 입력 대기로 멈추는 것 방지
        // ✅ [한글/유니코드] -scsUTF-8: 콘솔 출력을 UTF-8로 (Windows 한국어판 깨짐 방지)
        $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
        $stdin_block = $is_windows ? '< NUL' : '< /dev/null';
        $cmd = sprintf('%s l -slt -scsUTF-8 %s 2>&1 %s', 
            self::escapeArg(self::$sevenzip_path), 
            self::escapeArg($this->file_path),
            $stdin_block);

        $output = [];
        $return_code = -1;
        exec($cmd, $output, $return_code);

        
        if ($return_code === 0) {
            $current_file = null;
            $is_dir = false;
            $in_file_section = false; // ✅ 파일 목록 섹션 진입 여부

            foreach ($output as $line) {
                // ✅ '----------' 구분선 이후가 실제 파일 목록 (그 전 'Path ='는 아카이브 자신)
                if (strpos($line, '----------') === 0) {
                    $in_file_section = true;
                    continue;
                }
                if (!$in_file_section) continue;

                if (strpos($line, 'Path = ') === 0) {
                    // 이전 파일 저장
                    if ($current_file !== null && !$is_dir) {
                        if (!$filter || preg_match($filter, $current_file)) {
                            $files[] = str_replace('\\', '/', $current_file);
                        }
                    }
                    
                    $current_file = substr($line, 7);
                    // ✅ [한글 안전장치] -scsUTF-8 미적용 환경 대비 CP949→UTF-8 폴백
                    $current_file = self::normalizeEntryName($current_file);
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
        if (empty(self::$unrar_path)) {
            return false;
        }
        
        // 임시 디렉토리 생성
        // ✅ [동시 추출 충돌 방지] time()(초)는 동시 요청 시 겹칠 수 있어
        //    uniqid(more_entropy=true) + 랜덤으로 프로세스마다 고유한 폴더명 보장
        $temp_dir = sys_get_temp_dir() . '/mycomix_' . md5($this->file_path . $entry_name . uniqid('', true) . getmypid() . random_int(0, PHP_INT_MAX));
        
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
            // ✅ [한글/유니코드] -scu: 목록(lb)과 동일하게 UTF-8 처리로 파일명 매칭 일관성 확보
            $stdin_block = $is_windows ? '< NUL' : '< /dev/null';
            $cmd = sprintf(
                '%s x -scu -y -o+ -p- %s %s %s %s %s',
                self::escapeArg(self::$unrar_path),
                self::escapeArg($this->file_path),
                self::escapeArg($rar_entry_name),
                self::escapeArg($temp_dir),
                $stderr_redirect,
                $stdin_block
            );
            
            $output = [];
            $return_code = -1;
            exec($cmd, $output, $return_code);
            
            if ($return_code === 0) {
                $extracted_file = $temp_dir . '/' . $entry_name;
                
                if (file_exists($extracted_file)) {
                    $data = file_get_contents($extracted_file);
                    $this->deleteDirectory($temp_dir);
                    return $data;
                }
                
                // ✅ [한글 안전장치] 이름 직접 매칭 실패 시(인코딩 불일치 등)
                //    temp 폴더에서 실제 추출된 파일을 재귀 스캔하여 찾기
                //    (특정 1개 파일만 추출하므로 떨어진 이미지 파일은 그것 하나뿐)
                $found = $this->findFirstFileRecursive($temp_dir);
                if ($found !== null) {
                    $data = file_get_contents($found);
                    $this->deleteDirectory($temp_dir);
                    return $data;
                }
            }
            
            $this->deleteDirectory($temp_dir);
            
            // ✅ [한글 최종 폴백] 개별 추출(x)이 한글 파일명 매칭 실패로 아무것도
            //    못 꺼낸 경우: 전체를 평면 추출(-ep)한 뒤, 목록에서의 순번으로 찾는다.
            //    (이름 매칭을 전혀 하지 않으므로 인코딩 문제와 무관)
            return $this->extractRarByIndex($entry_name);
            
        } catch (Exception $e) {
            $this->deleteDirectory($temp_dir);
            return false;
        }
    }
    
    /**
     * [한글 최종 폴백] RAR 전체를 평면 추출(-ep) 후 목록 순번으로 파일 찾기.
     * unrar에 한글 파일명을 인자로 넘기는 것이 인코딩 문제로 실패할 때 사용.
     * 이름을 전혀 매칭하지 않고 "이미지 목록의 N번째 = 추출 파일 중 N번째"로 찾음.
     * mbstring 의존 없음. 사용자 제보 아이디어를 안전하게 구현.
     */
    private function extractRarByIndex($entry_name) {
        if (empty(self::$unrar_path)) return false;
        
        $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
        $stderr_redirect = $is_windows ? '2>NUL' : '2>/dev/null';
        $stdin_block = $is_windows ? '< NUL' : '< /dev/null';
        
        $temp_dir = sys_get_temp_dir() . '/mycomixidx_' . md5($this->file_path . uniqid('', true) . getmypid() . random_int(0, PHP_INT_MAX));
        if (!mkdir($temp_dir, 0755, true)) return false;
        
        try {
            // ✅ x: 경로 구조를 유지하며 전체 추출 (특정 파일명을 인자로 안 주므로 인코딩 무관).
            //    -ep(평면)는 하위폴더의 같은 이름 파일이 충돌(덮어쓰기)하므로 사용하지 않음.
            $cmd = sprintf(
                '%s x -scu -y -o+ -p- %s %s %s %s',
                self::escapeArg(self::$unrar_path),
                self::escapeArg($this->file_path),
                self::escapeArg($temp_dir),
                $stderr_redirect,
                $stdin_block
            );
            $output = [];
            $return_code = -1;
            exec($cmd, $output, $return_code);
            
            // 추출된 파일들 수집 (이미지 확장자만, 하위폴더 포함 재귀 + 상대경로 보존)
            $img_pattern = function_exists('get_image_extensions_pattern')
                ? get_image_extensions_pattern()
                : '/\.(jpg|jpeg|png|gif|webp|bmp)$/i';
            $extracted = $this->collectFilesRecursive($temp_dir, $temp_dir, $img_pattern);
            if (empty($extracted)) {
                $this->deleteDirectory($temp_dir);
                return false;
            }
            // 추출 파일 자연 정렬 (getImageFiles와 동일: basename 기준)
            usort($extracted, function($a, $b) {
                return strnatcasecmp(basename($a), basename($b));
            });
            
            // 목록에서 entry_name의 순번 찾기 (getImageFiles도 basename 정렬이라 순서 일치)
            $all_images = $this->getImageFiles();
            $target_index = array_search($entry_name, $all_images, true);
            
            $data = false;
            if ($target_index !== false && isset($extracted[$target_index]) && count($extracted) === count($all_images)) {
                // 순번 일치 + 추출 개수 = 목록 개수일 때만 순번 신뢰 (정확한 1:1 매핑)
                $data = file_get_contents($extracted[$target_index]);
            } elseif (count($extracted) === 1) {
                // 파일이 하나뿐이면 그것
                $data = file_get_contents($extracted[0]);
            } else {
                // 순번을 못 구하거나 개수 불일치: 전체경로 정규화 비교로 매칭
                $target_norm = self::normalizeEntryName($entry_name);
                $target_base = self::normalizeEntryName(basename($entry_name));
                foreach ($extracted as $full) {
                    $rel = ltrim(str_replace('\\', '/', substr($full, strlen($temp_dir))), '/');
                    if (self::normalizeEntryName($rel) === $target_norm
                        || self::normalizeEntryName(basename($full)) === $target_base
                        || $rel === $entry_name) {
                        $data = file_get_contents($full);
                        break;
                    }
                }
            }
            
            $this->deleteDirectory($temp_dir);
            return $data;
            
        } catch (Exception $e) {
            $this->deleteDirectory($temp_dir);
            return false;
        }
    }
    
    /**
     * 디렉토리에서 패턴에 맞는 파일들의 전체 경로를 재귀 수집.
     */
    private function collectFilesRecursive($dir, $base, $pattern) {
        $result = [];
        if (!is_dir($dir)) return $result;
        foreach (array_diff(scandir($dir), ['.', '..']) as $e) {
            $p = $dir . '/' . $e;
            if (is_file($p)) {
                if (!$pattern || preg_match($pattern, $e)) {
                    $result[] = $p;
                }
            } elseif (is_dir($p)) {
                $result = array_merge($result, $this->collectFilesRecursive($p, $base, $pattern));
            }
        }
        return $result;
    }
    
    /**
     * 7Z에서 파일 추출 (임시폴더 추출 방식)
     * ✅ Windows 7z.exe의 -so(stdout) 출력이 shell_exec에서 47바이트로 잘리는
     *    문제 때문에, RAR과 동일하게 임시폴더 추출 후 읽는 방식으로 변경.
     */
    private function extract7zFile($entry_name) {
        if (empty(self::$sevenzip_path)) {
            return false;
        }
        
        $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
        $stderr_redirect = $is_windows ? '2>NUL' : '2>/dev/null';
        $stdin_block = $is_windows ? '< NUL' : '< /dev/null';
        
        // 임시 디렉토리 생성 (RAR과 동일 방식)
        // ✅ [동시 추출 충돌 방지] time()(초)는 동시 요청 시 겹칠 수 있어
        //    uniqid(more_entropy=true) + 랜덤으로 프로세스마다 고유한 폴더명 보장
        $temp_dir = sys_get_temp_dir() . '/mycomix7z_' . md5($this->file_path . $entry_name . uniqid('', true) . getmypid() . random_int(0, PHP_INT_MAX));
        
        if (!mkdir($temp_dir, 0755, true)) {
            return false;
        }
        
        try {
            // 7z e -o{출력폴더}: 지정 파일을 임시폴더로 추출 (-so 대신)
            //  e = 경로 없이 추출 → 파일명만으로 떨어짐
            //  -o{dir}는 붙여써야 함 (7z 문법). escapeArg로 폴더 경로 감쌈
            //  ✅ [한글] -scsUTF-8: 입력 파일명을 UTF-8로 해석 (목록과 일관성)
            $cmd = sprintf(
                '%s e -y -bso0 -bsp0 -scsUTF-8 %s %s -o%s %s %s',
                self::escapeArg(self::$sevenzip_path),
                self::escapeArg($this->file_path),
                self::escapeArg($entry_name),
                self::escapeArg($temp_dir),
                $stderr_redirect,
                $stdin_block
            );
            
            $output = [];
            $return_code = -1;
            exec($cmd, $output, $return_code);
            
            if ($return_code === 0) {
                // e 옵션은 경로를 제거하므로 파일명(basename)만으로 떨어짐
                $extracted_file = $temp_dir . '/' . basename($entry_name);
                
                if (file_exists($extracted_file)) {
                    $data = file_get_contents($extracted_file);
                    $this->deleteDirectory($temp_dir);
                    return $data;
                }
                
                // ✅ [한글 안전장치] 이름 매칭 실패 시 temp 폴더 스캔으로 폴백
                $found = $this->findFirstFileRecursive($temp_dir);
                if ($found !== null) {
                    $data = file_get_contents($found);
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
     * 압축 내 파일들의 크기 맵 반환 ['파일명' => 바이트수, ...]
     * ZIP의 statIndex()['size']와 동등한 정보를 RAR/7Z에서 제공.
     * 크기를 못 구한 파일은 맵에 없거나 0.
     */
    public function getFileSizes() {
        $sizes = [];
        if ($this->type === '7z') {
            if (empty(self::$sevenzip_path)) return $sizes;
            $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
            $stdin_block = $is_windows ? '< NUL' : '< /dev/null';
            $cmd = sprintf('%s l -slt -scsUTF-8 %s 2>&1 %s',
                self::escapeArg(self::$sevenzip_path),
                self::escapeArg($this->file_path),
                $stdin_block);
            $output = [];
            $return_code = -1;
            exec($cmd, $output, $return_code);
            if ($return_code === 0) {
                $in_file_section = false;
                $current = null;
                foreach ($output as $line) {
                    if (strpos($line, '----------') === 0) { $in_file_section = true; continue; }
                    if (!$in_file_section) continue;
                    if (strpos($line, 'Path = ') === 0) {
                        $current = str_replace('\\', '/', substr($line, 7));
                        $current = self::normalizeEntryName($current);
                    } elseif ($current !== null && strpos($line, 'Size = ') === 0) {
                        $sizes[$current] = (int)trim(substr($line, 7));
                        $current = null;
                    }
                }
            }
        } elseif ($this->type === 'rar') {
            if (empty(self::$unrar_path)) return $sizes;
            $is_windows = (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
            $stdin_block = $is_windows ? '< NUL' : '< /dev/null';
            // unrar lt: 상세 목록(크기 포함). 베어(lb)와 달리 Size/Name 라벨 출력
            // ✅ [한글/유니코드] -scu: UTF-8 출력
            $cmd = sprintf('%s lt -scu -p- -y %s 2>&1 %s',
                self::escapeArg(self::$unrar_path),
                self::escapeArg($this->file_path),
                $stdin_block);
            $output = [];
            $return_code = -1;
            exec($cmd, $output, $return_code);
            if ($return_code === 0) {
                $current = null;
                foreach ($output as $line) {
                    $t = trim($line);
                    if (strpos($t, 'Name: ') === 0) {
                        $current = str_replace('\\', '/', substr($t, 6));
                        // ✅ [한글 안전장치] UTF-8 아니면 CP949→UTF-8
                        $current = self::normalizeEntryName($current);
                    } elseif ($current !== null && strpos($t, 'Size: ') === 0) {
                        $sizes[$current] = (int)trim(substr($t, 6));
                        $current = null;
                    }
                }
            }
        }
        return $sizes;
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
     * 압축 내 파일명을 UTF-8로 정규화.
     * 이미 UTF-8이면 그대로, 아니면 CP949(EUC-KR)로 보고 UTF-8 변환 시도.
     * mbstring/iconv 확장이 없는 환경에서도 죽지 않도록 모두 가드.
     */
    private static function normalizeEntryName($name) {
        if ($name === '' || $name === null) return $name;
        
        // 0) UTF-16 감지·변환 (Windows UnRAR.exe의 -scu 출력은 UTF-16LE)
        //    PHP exec()가 UTF-16 줄바꿈(0a)에서 자르면서 줄마다 정렬이 어긋나는 문제 보정.
        //    - 줄1: D\0 y\0 n\0 ... (정상 LE)
        //    - 줄2~: \0 D\0 y\0 ... (앞에 외톨이 \0 → 정렬 1바이트 밀림)
        if (strpos($name, "\0") !== false) {
            $s = $name;
            // (a) 맨 앞 외톨이 \0 제거 (LE 정렬 밀림 보정)
            if (strlen($s) > 0 && $s[0] === "\0") {
                $s = substr($s, 1);
            }
            // (b) 끝의 CR/LF 잔재 제거 (LE: 0d00 / 0a00, 또는 홀로 남은 0d/0a)
            $s = preg_replace('/(\x0d\x00|\x0a\x00)+$/', '', $s);
            $s = rtrim($s, "\x0d\x0a");
            // (c) 길이 홀수면 끝 글자 정렬을 위해 \0 보충
            if (strlen($s) % 2 === 1) {
                $s .= "\0";
            }
            // (d) UTF-16LE → UTF-8 (Windows unrar는 LE)
            if (function_exists('iconv')) {
                $conv = @iconv('UTF-16LE', 'UTF-8//IGNORE', $s);
                if ($conv !== false && $conv !== '') {
                    return rtrim($conv, "\0");
                }
            }
            if (function_exists('mb_convert_encoding')) {
                $conv = @mb_convert_encoding($s, 'UTF-8', 'UTF-16LE');
                if ($conv !== false && $conv !== '') {
                    return rtrim($conv, "\0");
                }
            }
            // (e) 변환 불가: null 바이트 제거 폴백 (ASCII 파일명은 이걸로도 복구됨)
            return str_replace("\0", '', $name);
        }
        
        // 1) UTF-8 유효성 검사 (mbstring 우선, 없으면 정규식 //u 사용)
        $isUtf8 = false;
        if (function_exists('mb_check_encoding')) {
            $isUtf8 = mb_check_encoding($name, 'UTF-8');
        } else {
            // //u 플래그: 유효한 UTF-8이 아니면 preg_match가 false 반환
            $isUtf8 = (@preg_match('//u', $name) !== false);
        }
        if ($isUtf8) return $name;
        
        // 2) UTF-8이 아니면 CP949 → UTF-8 변환 (iconv 우선, 없으면 mb_convert_encoding)
        if (function_exists('iconv')) {
            $conv = @iconv('CP949', 'UTF-8//IGNORE', $name);
            if ($conv !== false && $conv !== '') return $conv;
        }
        if (function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($name, 'UTF-8', 'CP949');
            if ($conv !== false && $conv !== '') return $conv;
        }
        // 변환 불가 환경: 원본 그대로 (최소한 죽지는 않음)
        return $name;
    }
    
    /**
     * 디렉토리에서 첫 번째 (일반)파일을 재귀적으로 찾아 경로 반환.
     * 추출 후 이름 직접 매칭이 인코딩 불일치로 실패할 때의 폴백.
     * 특정 1개 파일만 추출한 temp 폴더이므로 떨어진 파일은 보통 하나뿐.
     */
    private function findFirstFileRecursive($dir) {
        if (!is_dir($dir)) return null;
        $entries = array_diff(scandir($dir), ['.', '..']);
        // 파일 우선
        foreach ($entries as $e) {
            $p = $dir . '/' . $e;
            if (is_file($p)) return $p;
        }
        // 하위 폴더 탐색
        foreach ($entries as $e) {
            $p = $dir . '/' . $e;
            if (is_dir($p)) {
                $found = $this->findFirstFileRecursive($p);
                if ($found !== null) return $found;
            }
        }
        return null;
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