<?php
/**
 * myComix 압축 파일 핸들러
 * ZIP, RAR, 7Z 등 다양한 압축 형식 처리
 *
 * @version 1.3 - FileStation v5.8.12 방식 이식 (2026-10-31)
 * @date 2026-10-31
 *
 * ★ (2026-10-31) FileStation 압축 처리 이식 — 바뀐 점
 *   1) 도구 찾기: 관리자 설정 경로가 있으면 그것을 먼저 쓰고, 없거나 틀리면 흔한 설치 위치
 *      (윈도우 7-Zip/WinRAR, 리눅스·시놀로지 /usr/bin/7z·7za·7zz·7zzs, unrar 등)에서 자동으로 찾음
 *   2) 실행 환경: 리눅스·시놀로지는 UTF-8 로캘(C.UTF-8, 없으면 en_US.UTF-8 등)을 붙여 실행(아파치가 C 로캘이면 한글 이름이 ? 로 깨지던 문제),
 *      윈도우는 chcp 65001 + 결과를 임시 파일로 받음(파이프에서 글자가 ? 로 바뀌던 문제). 입력은 막아 암호 질문에서 멈추지 않음
 *   3) RAR 목록: 압축 헤더를 직접 읽음(RAR4·RAR5) — 도구·콘솔 인코딩과 무관. 못 읽으면 unrar → 7z 순으로
 *   4) 이름 인코딩: 압축 하나 단위로 CP949(한국어)·SJIS(일본어)·CP936(중국어)를 판정해 UTF-8 로 바꿈(옛 ZIP·RAR4)
 *   5) 추출: 도구가 아는 원래 이름 → 원본 바이트 → 바꾼 이름 순으로 시도(옛 p7zip 16.02·UnRAR 5.70 대응),
 *      리눅스는 압축 경로에 한글이 있으면 영문 임시 링크로 열어 줌
 *   6) 암호 압축: 암호 걸린 항목·헤더 암호를 알아내 헛된 전체 풀기를 하지 않음(isEncrypted/needsPassword)
 *   공개 함수(listFiles/extractFile/getImageFiles/getVideoFiles/getFileSizes/extractFirstImage 등)의 사용법은 그대로
 */

class ArchiveHandler {
    private $file_path;
    private $type;
    private $temp_dir;

    // ★ (2026-10-31) 목록 캐시(한 번 읽은 목록을 같은 객체에서 다시 쓰기 — 전에는 부를 때마다 도구를 다시 실행)
    //   각 항목: ['name' => UTF-8 이름, 'args' => [추출 때 도구에 넘길 이름 후보...], 'raw' => 원본 바이트 이름,
    //            'size' => 크기, 'dir' => 폴더 여부, 'enc' => 암호 여부, 'zi' => ZIP 인덱스]
    private $entries = null;
    private $entry_map = null;
    private $encrypted = false;        // 암호 걸린 항목이 있음
    private $header_encrypted = false; // 헤더까지 암호(목록 자체를 못 읽음)
    private $list_tool = '';           // 목록을 만든 방법(native/unrar/7z/zip) — 추출 때 이름 후보 순서에 씀

    // 외부 툴 경로 (config.php에서 로드)
    private static $unrar_path = '';
    private static $sevenzip_path = '';
    private static $bin_cache = [];

    /**
     * 플랫폼 독립적인 셸 인자 이스케이프
     * Windows에서는 따옴표로 감싸고, Linux/Mac에서는 escapeshellarg 사용
     *
     * @param string $arg 이스케이프할 인자
     * @return string 이스케이프된 인자
     */
    private static function escapeArg($arg) {
        $arg = (string)$arg;
        $is_windows = self::isWindows();
        if (!$is_windows) {
            // ★ (2026-10-31) 리눅스는 직접 감쌈 — escapeshellarg() 는 PHP 로캘이 UTF-8 이면 잘못된 UTF-8 바이트(옛 RAR 의 CP949 이름)를
            //   빼 버려 이름이 달라짐. 작은따옴표 감싸기는 바이트를 그대로 둠(결과는 C 로캘의 escapeshellarg 와 같음)
            return "'" . str_replace("'", "'\\''", $arg) . "'";
        }
        // 공용 함수가 있으면 사용, 없으면 자체 구현
        if (function_exists('escape_shell_arg_safe')) {
            return escape_shell_arg_safe($arg);
        }
        // Windows: 따옴표 이스케이프 후 전체를 따옴표로 감싸기
        $arg = str_replace('"', '""', $arg);
        return '"' . $arg . '"';
    }

    private static function isWindows() {
        return (DIRECTORY_SEPARATOR === '\\') || (stripos(PHP_OS, 'WIN') === 0);
    }

    /**
     * 외부 툴 경로 설정
     */
    public static function configure($unrar_path = '', $sevenzip_path = '') {
        self::$unrar_path = is_string($unrar_path) ? trim($unrar_path) : '';
        self::$sevenzip_path = is_string($sevenzip_path) ? trim($sevenzip_path) : '';
        self::$bin_cache = [];
    }

    /**
     * ★ (2026-10-31) 실제로 쓸 도구 경로 — 관리자 설정 경로가 있으면 그것, 없거나 없는 파일이면 흔한 설치 위치에서 찾음
     *   (FileStation 과 같은 후보 목록 + 시놀로지/Entware 위치). 못 찾으면 ''.
     * @param string $tool 'unrar' | '7z'
     */
    public static function getToolPath($tool) {
        if (isset(self::$bin_cache[$tool])) return self::$bin_cache[$tool];
        $configured = ($tool === 'unrar') ? self::$unrar_path : self::$sevenzip_path;
        $found = '';
        if ($configured !== '' && self::binUsable($configured)) {
            $found = $configured;
        } else {
            if ($tool === 'unrar') {
                $cands = self::isWindows()
                    ? ['C:\\Program Files\\WinRAR\\UnRAR.exe', 'C:\\Program Files (x86)\\WinRAR\\UnRAR.exe']
                    : ['/usr/bin/unrar', '/usr/local/bin/unrar', '/usr/bin/unrar-nonfree', '/opt/bin/unrar', '/usr/syno/bin/unrar', '/opt/homebrew/bin/unrar'];
            } else {
                $cands = self::isWindows()
                    ? ['C:\\Program Files\\7-Zip\\7z.exe', 'C:\\Program Files (x86)\\7-Zip\\7z.exe']
                    : ['/usr/bin/7z', '/usr/local/bin/7z', '/usr/bin/7za', '/usr/local/bin/7za', '/usr/bin/7zz', '/usr/local/bin/7zz',
                       '/usr/bin/7zzs', '/usr/local/bin/7zzs', '/usr/bin/7zip', '/bin/7zz', '/bin/7z', '/usr/syno/bin/7z', '/opt/bin/7z', '/opt/bin/7za',
                       '/opt/homebrew/bin/7zz', '/opt/homebrew/bin/7z'];
            }
            foreach ($cands as $c) {
                if (self::binUsable($c)) { $found = $c; break; }
            }
        }
        self::$bin_cache[$tool] = $found;
        return $found;
    }

    /** 설정 경로 없이 자동으로 찾은 도구인지(관리자 화면 표시용) */
    public static function isToolAutoDetected($tool) {
        $p = self::getToolPath($tool);
        $configured = ($tool === 'unrar') ? self::$unrar_path : self::$sevenzip_path;
        return $p !== '' && $p !== $configured;
    }

    /**
     * ★ (2026-11-03 15:22) 도구 버전(관리자 화면 표시용 — FileStation 압축 설정 화면과 같은 '✅ 7-Zip 23.01 설치됨')
     *   명령줄 도구 이름(7z·7za·7zz·7zzs·7zr·unrar·rar)일 때만 실행 — WinRAR.exe·7zG.exe 같은 창 프로그램을 연결했으면 띄우지 않음(멈춤 방지)
     *   결과는 src/tool_ver.json(★ 2026-11-03 22:14 cache 밖으로) 에 경로·수정 시각·크기와 함께 저장 → 도구를 바꾸지 않으면 다시 실행하지 않음
     * @return string 버전('' = 모름)
     */
    public static function toolVersion($tool) {
        $p = self::getToolPath($tool);
        if ($p === '') return '';
        $base = strtolower(basename(str_replace('\\', '/', $p)));
        if (!preg_match('/^(7z|7za|7zz|7zzs|7zr|7zip|unrar|unrar-nonfree|rar)(\.exe)?$/', $base)) return ''; // ★ (2026-11-03 20:58) 자동 찾기 후보 /usr/bin/7zip 도
        $key = $p . '|' . @filemtime($p) . '|' . @filesize($p);
        $cf = __DIR__ . '/src/tool_ver.json';
        $c = @json_decode((string)@file_get_contents($cf), true);
        if (!is_array($c)) $c = [];
        if (isset($c[$tool]['key'], $c[$tool]['ver']) && $c[$tool]['key'] === $key) return (string)$c[$tool]['ver'];
        $out = self::runTool($p, '', $rc);
        $ver = '';
        if ($tool === 'unrar') { if (preg_match('/\b(?:UNRAR|RAR)\s+(\d+\.\d+)/i', $out, $m)) $ver = $m[1]; }
        elseif (preg_match('/7-Zip[^\r\n]*?\s(\d+\.\d+)/', $out, $m)) $ver = $m[1];   // '7-Zip [64] 16.02'·'7-Zip 23.01 (x64)'·'7-Zip (z) 24.08'
        if ($ver === '') return ''; // ★ (2026-11-03 20:58) 못 알아냈으면 저장하지 않음(한 번 실패가 도구를 바꿀 때까지 남지 않게)
        $c[$tool] = ['key' => $key, 'ver' => $ver];
        if (is_dir(__DIR__ . '/src')) @file_put_contents($cf, json_encode($c), LOCK_EX);
        return $ver;
    }

    private static function binUsable($path) {
        if (!is_string($path) || $path === '') return false;
        if (!@is_file($path)) return false;
        return self::isWindows() ? true : (@is_executable($path) || @file_exists($path));
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
                // ★ (2026-10-31) unrar 가 없어도 7-Zip 이 있으면 RAR 을 엶(시놀로지 등은 7z 만 있는 경우가 많음)
                return self::getToolPath('unrar') !== '' || self::getToolPath('7z') !== '';

            case '7z':
            case 'cb7':
                return self::getToolPath('7z') !== '';

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

    // ============================================================
    // ★ (2026-10-31) 외부 도구 실행 — FileStation 과 같은 방식
    //   · 리눅스/시놀로지: LANG/LC_ALL 에 UTF-8 로캘을 붙여 실행(아파치가 C 로캘이면 7z/unrar 가 한글을 ? 로 출력)
    //   · 윈도우: chcp 65001 후 실행
    //   · 출력은 임시 파일로 받음(윈도우 파이프에서 유니코드가 ? 로 바뀌는 문제), 입력은 막음(암호 질문에서 멈춤 방지)
    //   · 옛 도구가 모르는 옵션이면(예: 9.20 의 -scc) 그 옵션을 빼고 한 번 더
    // ============================================================
    private static function runTool($bin, $args, &$rc = null, $fallback_args = null) {
        $rc = -1;
        $tmp = @tempnam(sys_get_temp_dir(), 'mcarc');
        if ($tmp === false) return '';
        $out = self::runToolOnce($bin, $args, $tmp, $rc);
        if ($fallback_args !== null && $fallback_args !== $args
            && ($rc === 7 || stripos($out, 'Unsupported switch') !== false || stripos($out, 'Unknown switch') !== false
                || stripos($out, 'Incorrect command line') !== false || stripos($out, 'unknown option') !== false)) {
            $out = self::runToolOnce($bin, $fallback_args, $tmp, $rc);
        }
        @unlink($tmp);
        return $out;
    }

    private static function runToolOnce($bin, $args, $tmp, &$rc) {
        $cmd = self::escapeArg($bin) . ' ' . $args;
        if (self::isWindows()) {
            $full = 'chcp 65001 >nul && ' . $cmd . ' > ' . self::escapeArg($tmp) . ' 2>&1 < NUL';
        } else {
            $loc = self::utf8Locale();
            $full = 'LANG=' . $loc . ' LC_ALL=' . $loc . ' ' . $cmd . ' > ' . self::escapeArg($tmp) . ' 2>&1 < /dev/null';
        }
        $o = [];
        $rc = -1;
        @exec($full, $o, $rc);
        $out = @file_get_contents($tmp);
        return is_string($out) ? $out : '';
    }

    /**
     * ★ (2026-10-31) 리눅스에서 쓸 UTF-8 로캘 이름 — C.UTF-8 이 없는 시스템(일부 시놀로지 DSM 등)은 en_US.UTF-8 등으로
     *   (없는 로캘을 주면 C 로 떨어져 7z/unrar 가 한글을 ? 로 냄). 'locale -a' 를 한 번만 보고, 못 보면 C.UTF-8
     */
    private static function utf8Locale() {
        static $loc = null;
        if ($loc !== null) return $loc;
        $loc = 'C.UTF-8';
        $list = [];
        @exec('locale -a 2>/dev/null', $list);
        if (!empty($list)) {
            $have = [];
            foreach ($list as $l) { $l = trim($l); if ($l !== '' && preg_match('/^[A-Za-z0-9_.@-]+$/', $l)) $have[strtolower(str_replace('-', '', $l))] = $l; }
            foreach (['c.utf8', 'en_us.utf8', 'ko_kr.utf8'] as $want) {
                if (isset($have[$want])) { $loc = $have[$want]; return $loc; }
            }
            foreach ($have as $k => $l) {
                if (substr($k, -5) === '.utf8') { $loc = $l; return $loc; }
            }
        }
        return $loc;
    }
    
    /** 7-Zip 빈 암호 인자 — 암호가 걸려 있어도 묻지 않고 바로 실패하게 */
    private static function emptyPwArg7z() {
        return self::isWindows() ? '-p""' : "-p''";
    }

    /**
     * ★ (2026-10-31) 리눅스·시놀로지에서 압축 파일 경로에 한글 등이 있으면 영문 이름의 임시 링크로 열기
     *   (옛 p7zip 16.02 / UnRAR 5.70 은 비ASCII 경로의 압축을 'Can not open' 으로 못 여는 경우가 있음 — FileStation 실측)
     *   링크라 파일을 복사하지 않음. 링크를 못 만들면 원래 경로 그대로. 윈도우는 해당 없음.
     * @return array [열 경로, 지울 링크 경로|null]
     */
    private function toolArchivePath() {
        $p = $this->file_path;
        if (self::isWindows() || !preg_match('/[^\x20-\x7E]/', $p) || !function_exists('symlink')) return [$p, null];
        $real = @realpath($p);
        if ($real === false) return [$p, null];
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        if (!preg_match('/^[a-z0-9]{1,5}$/', $ext)) $ext = 'bin';
        $link = rtrim(sys_get_temp_dir(), '/') . '/mcarcl_' . md5($real . uniqid('', true) . random_int(0, PHP_INT_MAX)) . '.' . $ext;
        if (preg_match('/[^\x20-\x7E]/', $link)) return [$p, null];
        if (@symlink($real, $link)) return [$link, $link];
        return [$p, null];
    }

    // ============================================================
    // 목록
    // ============================================================

    /**
     * 압축 파일 내 파일 목록 가져오기
     *
     * @param string $filter 정규식 필터 (예: '/\.(jpg|png)$/i')
     * @return array 파일 목록
     */
    public function listFiles($filter = null) {
        $files = [];
        foreach ($this->getEntries() as $e) {
            if ($e['dir']) continue;
            $name = $e['name'];
            if ($name === '' || substr($name, -1) === '/') continue;
            if ($filter && !preg_match($filter, $name)) continue;
            $files[] = $name;
        }
        return $files;
    }

    /** ★ (2026-10-31) 암호 걸린 항목이 있거나 헤더까지 암호인 압축인지 */
    public function isEncrypted() {
        $this->getEntries();
        return $this->encrypted || $this->header_encrypted;
    }

    /** ★ (2026-10-31) 헤더까지 암호라 암호 없이는 목록조차 못 읽는 압축인지 */
    public function needsPassword() {
        $this->getEntries();
        return $this->header_encrypted;
    }

    private function getEntries() {
        if ($this->entries !== null) return $this->entries;
        $this->entries = [];
        try {
            switch ($this->type) {
                case 'zip': $this->entries = $this->readZipEntries(); break;
                case 'rar': $this->entries = $this->readRarEntries(); break;
                case '7z':  $this->entries = $this->read7zEntries(); break;
            }
        } catch (\Throwable $e) {
            error_log('[ArchiveHandler] 목록 실패: ' . $e->getMessage());
            $this->entries = [];
        }
        $this->entry_map = [];
        foreach ($this->entries as $i => $e) {
            if (!isset($this->entry_map[$e['name']])) $this->entry_map[$e['name']] = $i;
        }
        return $this->entries;
    }

    /** 이름으로 항목 찾기 — 지금 이름이 없으면 예전 방식(CP949 고정 변환) 이름도 대조(예전에 만든 캐시 대비) */
    private function findEntry($entry_name) {
        $this->getEntries();
        $entry_name = str_replace('\\', '/', (string)$entry_name);
        if (isset($this->entry_map[$entry_name])) return $this->entries[$this->entry_map[$entry_name]];
        foreach ($this->entries as $e) {
            if ($e['raw'] === $entry_name) return $e;
            if (self::normalizeEntryName($e['raw']) === $entry_name) return $e;
            foreach ($e['args'] as $a) { if (str_replace('\\', '/', $a) === $entry_name) return $e; }
        }
        return null;
    }

    private static function makeEntry($name, $raw, array $args, $size, $dir, $enc, $zi = null) {
        $name = str_replace('\\', '/', $name);
        $uniq = [];
        foreach ($args as $a) { if (is_string($a) && $a !== '' && !in_array($a, $uniq, true)) $uniq[] = $a; }
        return ['name' => $dir ? rtrim($name, '/') : $name, 'raw' => $raw, 'args' => $uniq,
                'size' => (int)$size, 'dir' => (bool)$dir, 'enc' => (bool)$enc, 'zi' => $zi];
    }

    /**
     * ZIP 목록 — 원본 바이트 이름을 압축 단위로 판정해 UTF-8 로(옛 한국어·일본어·중국어 ZIP)
     */
    private function readZipEntries() {
        $out = [];
        $zip = new ZipArchive();
        if ($zip->open($this->file_path) !== TRUE) return $out;
        $names = mc_zip_names($zip);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $guess = $zip->getNameIndex($i);
            if (!is_string($guess)) continue;
            $name = $names[$i] ?? $guess;
            $st = @$zip->statIndex($i);
            $enc = is_array($st) && !empty($st['encryption_method']);
            if ($enc) $this->encrypted = true;
            $out[] = self::makeEntry($name, $guess, [$guess], is_array($st) ? ($st['size'] ?? 0) : 0, substr($guess, -1) === '/', $enc, $i);
        }
        $zip->close();
        $this->list_tool = 'zip';
        return $out;
    }

    /**
     * RAR 목록 — ①헤더 직접 읽기 ②unrar ③7-Zip 순
     */
    private function readRarEntries() {
        $win = self::isWindows();
        $nat = self::rarNativeList($this->file_path);
        if (is_array($nat) && !empty($nat['hdrenc'])) {
            // 헤더 암호 — 암호 없이는 이름조차 못 읽음
            $this->header_encrypted = true;
            $this->encrypted = true;
            $this->list_tool = 'native';
            return [];
        }
        if (is_array($nat) && !empty($nat['items'])) {
            $raws = [];
            foreach ($nat['items'] as $it) { if (count($raws) >= 500) break; $raws[] = $it['raw']; }
            $hint = self::detectEncoding($raws);
            $out = [];
            foreach ($nat['items'] as $it) {
                // 이름을 먼저 UTF-8 로 바꾼 뒤 '\' → '/' (SJIS·GBK 는 글자 둘째 바이트가 0x5C('\') 일 수 있어 바꾸기 전에 자르면 글자가 깨짐)
                $name = self::nameToUtf8($it['raw'], $hint);
                if (!self::isValidUtf8($name)) $name = self::normalizeEntryName($it['raw']);
                $name = str_replace('\\', '/', $name);
                // 도구(unrar/7z)가 보는 이름·디스크에 풀리는 경로는 바이트 단위로 '\' 를 '/' 로 본 것
                $tool_raw = str_replace('\\', '/', $it['raw']);
                // 추출 때 넘길 이름 후보: 윈도우는 UTF-8 이름(도구가 OEM 코드페이지로 이름을 바꿔 읽음), 리눅스는 원본 바이트 먼저
                $args = $win ? [$name, $tool_raw] : [$tool_raw, $name];
                if ($it['enc']) $this->encrypted = true;
                $out[] = self::makeEntry($name, $tool_raw, $args, $it['size'], $it['dir'], $it['enc']);
            }
            $this->list_tool = 'native';
            return $out;
        }

        // ② unrar
        $unrar = self::getToolPath('unrar');
        if ($unrar !== '') {
            $out = $this->readUnrarEntries($unrar);
            if (!empty($out)) { $this->list_tool = 'unrar'; return $out; }
            if ($this->header_encrypted) { $this->list_tool = 'unrar'; return []; }
        }
        // ③ 7-Zip
        if (self::getToolPath('7z') !== '') {
            $out = $this->read7zEntries();
            if (!empty($out)) return $out;
        }
        return [];
    }

    private function readUnrarEntries($unrar) {
        $out = [];
        list($apath, $link) = $this->toolArchivePath();
        // ★ (2026-11-02 08:51) '--' — 압축 파일·안의 이름이 '-' 로 시작하면 도구가 스위치로 읽었음(-op… 로 엉뚱한 곳에 풀기 등). 스위치는 모두 그 앞에
        $txt = self::runTool($unrar, 'lt -scu -p- -y -- ' . self::escapeArg($apath), $rc);
        if ($link) @unlink($link);
        // 윈도우 UnRAR -scu 는 UTF-16LE 로 냄 → 통째로 UTF-8 로
        if (strpos($txt, "\0") !== false) {
            $s = $txt;
            if (substr($s, 0, 2) === "\xFF\xFE") $s = substr($s, 2);
            if (strlen($s) % 2 === 1) $s .= "\0";
            $conv = function_exists('iconv') ? @iconv('UTF-16LE', 'UTF-8//IGNORE', $s) : false;
            if (($conv === false || $conv === '') && function_exists('mb_convert_encoding')) $conv = @mb_convert_encoding($s, 'UTF-8', 'UTF-16LE');
            $txt = ($conv !== false && $conv !== '') ? $conv : str_replace("\0", '', $txt);
        }
        if (stripos($txt, 'encrypted headers') !== false || stripos($txt, 'wrong password') !== false
            || stripos($txt, 'incorrect password') !== false || stripos($txt, 'Enter password') !== false) {
            if (stripos($txt, 'Name:') === false) { $this->header_encrypted = true; $this->encrypted = true; return []; }
        }
        $blocks = [];
        $cur = [];
        foreach (preg_split('/\r?\n/', $txt) as $ln) {
            $t = ltrim($ln); // 값 끝의 공백은 이름의 일부일 수 있어 왼쪽만
            if (trim($t) === '') { if ($cur) { $blocks[] = $cur; $cur = []; } continue; }
            $cp = strpos($t, ': ');
            if ($cp !== false) $cur[substr($t, 0, $cp)] = substr($t, $cp + 2);
        }
        if ($cur) $blocks[] = $cur;
        $raws = [];
        foreach ($blocks as $b) { if (isset($b['Name']) && count($raws) < 500) $raws[] = str_replace('\\', '/', $b['Name']); }
        $hint = self::detectEncoding($raws);
        foreach ($blocks as $b) {
            if (!isset($b['Name']) || $b['Name'] === '') continue;
            if (isset($b['Type']) && stripos($b['Type'], 'Service') !== false) continue; // 주석 등 내부 헤더
            $raw = $b['Name'];
            $isDir = isset($b['Type']) && stripos($b['Type'], 'Directory') !== false;
            $enc = (isset($b['Flags']) && stripos($b['Flags'], 'encrypt') !== false) || isset($b['CRC32 MAC']);
            if ($enc) $this->encrypted = true;
            $name = self::nameToUtf8(str_replace('\\', '/', $raw), $hint);
            if (!self::isValidUtf8($name)) $name = self::normalizeEntryName($raw);
            $out[] = self::makeEntry($name, $raw, [$raw, $name], (int)preg_replace('/[^0-9]/', '', $b['Size'] ?? '0'), $isDir, $enc);
        }
        return $out;
    }

    /**
     * 7-Zip 목록(-slt) — .7z 와, unrar 가 없을 때의 RAR
     */
    private function read7zEntries() {
        $out = [];
        $bin = self::getToolPath('7z');
        if ($bin === '') return $out;
        list($apath, $link) = $this->toolArchivePath();
        $pw = self::emptyPwArg7z();
        $txt = self::runTool($bin, 'l -slt -sccUTF-8 ' . $pw . ' -- ' . self::escapeArg($apath), $rc,
                                    'l -slt ' . $pw . ' -- ' . self::escapeArg($apath)); // ★ (2026-11-02 08:51) '--'
        if ($link) @unlink($link);

        $in_list = false;
        $blocks = [];
        $cur = [];
        foreach (preg_split('/\r?\n/', $txt) as $line) {
            $t = trim($line);
            if ($t === '----------') { $in_list = true; $cur = []; continue; }
            if (!$in_list) continue;
            if ($t === '') { if ($cur) { $blocks[] = $cur; $cur = []; } continue; }
            // 값은 자르지 않음(이름 앞뒤 공백 보존) — 7z -slt 의 키는 줄 맨 앞에서 시작
            $eq = strpos($line, ' = ');
            if ($eq !== false) { $cur[trim(substr($line, 0, $eq))] = substr($line, $eq + 3); }
            elseif (substr($t, -2) === ' =') { $cur[substr($t, 0, -2)] = ''; }
        }
        if ($cur) $blocks[] = $cur;

        if (empty($blocks)) {
            if (stripos($txt, 'Cannot open encrypted archive') !== false || stripos($txt, 'Can not open encrypted archive') !== false
                || stripos($txt, 'Wrong password') !== false || stripos($txt, 'Enter password') !== false) {
                $this->header_encrypted = true;
                $this->encrypted = true;
            }
            return $out;
        }
        $raws = [];
        foreach ($blocks as $b) { if (isset($b['Path']) && count($raws) < 500) $raws[] = str_replace('\\', '/', $b['Path']); }
        $hint = self::detectEncoding($raws);
        foreach ($blocks as $b) {
            if (!isset($b['Path']) || $b['Path'] === '') continue;
            $raw = $b['Path'];
            $isDir = (isset($b['Folder']) && $b['Folder'] === '+') || (isset($b['Attributes']) && strpos($b['Attributes'], 'D') === 0);
            $enc = isset($b['Encrypted']) && $b['Encrypted'] === '+';
            if ($enc) $this->encrypted = true;
            $name = self::nameToUtf8(str_replace('\\', '/', $raw), $hint);
            if (!self::isValidUtf8($name)) $name = self::normalizeEntryName($raw);
            $out[] = self::makeEntry($name, $raw, [$raw, $name], (int)($b['Size'] ?? 0), $isDir, $enc);
        }
        $this->list_tool = '7z';
        return $out;
    }

    // ============================================================
    // 추출
    // ============================================================

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
            // ★ (2026-10-31) 바꾼 이름(한글 등)으로도 찾기 — 못 찾으면 예전처럼 이름 그대로
            $data = mc_zip_from_name($zip, $entry_name);
            $zip->close();
            return $data;
        }

        return false;
    }

    private static function newTempDir($prefix, $seed) {
        $d = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . $prefix . md5($seed . uniqid('', true) . getmypid() . random_int(0, PHP_INT_MAX));
        return @mkdir($d, 0700, true) ? $d : false;
    }

    /**
     * unrar 풀 곳 인자 — 리눅스는 끝에 '/'(풀 곳으로 확실히 인식), 윈도우는 예전처럼 붙이지 않음
     *   (윈도우는 "C:\dir\" 의 끝 \" 가 따옴표 이스케이프로 읽혀 인자가 깨질 수 있음)
     */
    private static function unrarDestArg($dir) {
        return self::isWindows() ? self::escapeArg($dir) : self::escapeArg(rtrim($dir, '/') . '/');
    }
    
    /** 임시 폴더 안의 파일인지(압축 속 '..' 이름으로 밖을 읽지 않게) */
    private static function insideDir($file, $dir) {
        $rp = @realpath($file); $rt = @realpath($dir);
        if ($rp === false || $rt === false) return false;
        return strpos(str_replace('\\', '/', $rp), rtrim(str_replace('\\', '/', $rt), '/') . '/') === 0;
    }

    /** 한 항목을 풀어 둔 임시 폴더에서 결과 파일 찾기 — 기대 경로 → 하나뿐인 파일 */
    private function pickExtracted($temp_dir, $arg) {
        $arg = str_replace('\\', '/', $arg);
        foreach ([$temp_dir . '/' . $arg, $temp_dir . '/' . basename($arg)] as $cand) {
            if (@is_file($cand) && !@is_link($cand) && self::insideDir($cand, $temp_dir)) return $cand;
        }
        // 이름에 * ? 가 있으면 도구가 와일드카드로 보고 다른 파일을 풀 수 있음 → '하나뿐인 파일' 로 받지 않음
        if (strpbrk($arg, '*?') !== false) return null;
        $all = $this->collectFilesRecursive($temp_dir, $temp_dir, null);
        if (count($all) === 1) return $all[0];
        return null;
    }

    /**
     * RAR에서 파일 추출 (임시 디렉토리 사용)
     *   ★ (2026-10-31) 이름 후보(원본 바이트 / UTF-8)를 차례로 시도, unrar 가 없거나 실패하면 7-Zip 으로,
     *   그래도 안 되면 예전 전체 풀기(순번 대조)로. 암호 걸린 항목은 바로 실패(헛된 전체 풀기 방지)
     */
    private function extractRarFile($entry_name) {
        $e = $this->findEntry($entry_name);
        if ($e !== null && $e['enc']) return false;
        if ($this->header_encrypted) return false;
        // 목록은 읽었는데 그 이름이 없으면(예전 캐시의 깨진 이름 등) 도구에 넘기지 않음 — '?' 가 와일드카드로 다른 쪽을 꺼내지 않게
        if ($e === null && !empty($this->entries)) return false;
        $args = $e !== null ? $e['args'] : [$entry_name];

        $unrar = self::getToolPath('unrar');
        if ($unrar !== '') {
            $is_windows = self::isWindows();
            list($apath, $link) = $this->toolArchivePath();
            foreach ($args as $arg) {
                // ★ (2026-11-03 20:58) '@' 로 시작하는 이름은 unrar 가 '--' 뒤에서도 '목록 파일'로 읽음(실측 unrar 7.00) → unrar 는 건너뛰고 아래 7-Zip 으로(FileStation 34차와 같음)
                if (substr((string)$arg, 0, 1) === '@') continue;
                $temp_dir = self::newTempDir('mycomix_', $this->file_path . $arg);
                if ($temp_dir === false) break;
                $rar_arg = $is_windows ? str_replace('/', '\\', $arg) : $arg;
                // unrar x: 특정 파일 추출 (-p-: 암호 묻지 않음)
                self::runTool($unrar, 'x -scu -y -o+ -p- -- ' . self::escapeArg($apath) . ' ' . self::escapeArg($rar_arg) . ' '
                                      . self::unrarDestArg($temp_dir), $rc); // ★ (2026-11-02 08:51) '--'
                $found = $this->pickExtracted($temp_dir, $arg);
                $data = ($found !== null) ? @file_get_contents($found) : false;
                $this->deleteDirectory($temp_dir);
                if ($data !== false && $data !== '') { if ($link) @unlink($link); return $data; }
            }
            if ($link) @unlink($link);
        }

        if (self::getToolPath('7z') !== '') {
            $data = $this->extractWith7z($args);
            if ($data !== false) return $data;
        }

        // ✅ [한글 최종 폴백] 개별 추출이 이름 매칭 실패로 아무것도 못 꺼낸 경우:
        //    전체를 풀어 놓고 원본 이름/순번으로 찾는다. (목록에 있는 항목일 때만 — 없는 이름 요청으로 전체 풀기 반복 방지)
        if ($e === null) return false;
        return $this->extractRarByIndex($entry_name);
    }

    /** 7-Zip 으로 한 항목 풀기 — 이름 후보를 차례로 */
    private function extractWith7z(array $args) {
        $bin = self::getToolPath('7z');
        if ($bin === '') return false;
        list($apath, $link) = $this->toolArchivePath();
        $pw = self::emptyPwArg7z();
        $data = false;
        foreach ($args as $arg) {
            $temp_dir = self::newTempDir('mycomix7z_', $this->file_path . $arg);
            if ($temp_dir === false) break;
            // 7z e -o{출력폴더}: 경로 없이 파일명만으로 떨어짐. -o 는 붙여 써야 함
            // ★ (2026-11-02 08:51) 스위치(-o 포함)를 모두 앞에, '--' 뒤에 압축·이름 — 이름이 '-' 로 시작해도 스위치로 안 읽게
            $tail = ' ' . $pw . ' -o' . self::escapeArg($temp_dir) . ' -- ' . self::escapeArg($apath) . ' ' . self::escapeArg($arg);
            self::runTool($bin, 'e -y -sccUTF-8' . $tail, $rc, 'e -y' . $tail);
            $found = $this->pickExtracted($temp_dir, $arg);
            $d = ($found !== null) ? @file_get_contents($found) : false;
            $this->deleteDirectory($temp_dir);
            if ($d !== false && $d !== '') { $data = $d; break; }
        }
        if ($link) @unlink($link);
        return $data;
    }

    /**
     * [한글 최종 폴백] RAR 전체를 풀어 놓고 원본 이름 → 순번으로 파일 찾기.
     * 도구에 한글 파일명을 인자로 넘기는 것이 인코딩 문제로 실패할 때 사용.
     */
    private function extractRarByIndex($entry_name) {
        $unrar = self::getToolPath('unrar');
        $sz = self::getToolPath('7z');
        if ($unrar === '' && $sz === '') return false;

        $temp_dir = self::newTempDir('mycomixidx_', $this->file_path);
        if ($temp_dir === false) return false;

        try {
            // ✅ x: 경로 구조를 유지하며 전체 추출 (특정 파일명을 인자로 안 주므로 인코딩 무관)
            list($apath, $link) = $this->toolArchivePath();
            if ($unrar !== '') {
                self::runTool($unrar, 'x -scu -y -o+ -p- -- ' . self::escapeArg($apath) . ' ' . self::unrarDestArg($temp_dir), $rc); // ★ (2026-11-02 08:51) '--'
            } else {
                $pw = self::emptyPwArg7z();
                self::runTool($sz, 'x -y ' . $pw . ' -o' . self::escapeArg($temp_dir) . ' -- ' . self::escapeArg($apath), $rc);
            }
            if ($link) @unlink($link);

            // 추출된 파일들 수집 (이미지 확장자만, 하위폴더 포함 재귀 + 상대경로 보존)
            $img_pattern = function_exists('get_image_extensions_pattern')
                ? get_image_extensions_pattern()
                : '/\.(jpg|jpeg|png|gif|webp|bmp)$/i';
            $extracted = $this->collectFilesRecursive($temp_dir, $temp_dir, $img_pattern);
            if (empty($extracted)) {
                $this->deleteDirectory($temp_dir);
                return false;
            }

            $data = false;
            // ★ (2026-10-31) ① 디스크에 떨어진 이름이 목록의 원본 이름/바꾼 이름과 같은 것
            $e = $this->findEntry($entry_name);
            if ($e !== null) {
                foreach ($extracted as $full) {
                    $rel = ltrim(str_replace('\\', '/', substr($full, strlen($temp_dir))), '/');
                    if ($rel === str_replace('\\', '/', $e['raw']) || $rel === $e['name']) { $data = @file_get_contents($full); break; }
                }
            }

            if ($data === false) {
                // 추출 파일 자연 정렬 (getImageFiles와 동일: basename 기준)
                usort($extracted, function($a, $b) {
                    return strnatcasecmp(basename($a), basename($b));
                });

                // 목록에서 entry_name의 순번 찾기 (getImageFiles도 basename 정렬이라 순서 일치)
                $all_images = $this->getImageFiles();
                $target_index = array_search($entry_name, $all_images, true);

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
            if (is_link($p)) continue; // ★ (2026-10-30) 압축이 만든 링크는 따라가지 않음(임시 폴더 밖 파일 읽기 방지)
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
     * ★ (2026-10-31) 먼저 받은 이름 그대로 풀어 봄(예전과 같음 — 쪽마다 목록을 다시 읽지 않게).
     *   안 되면 목록을 읽어 도구가 낸 원래 이름 → 바꾼 이름 순으로 다시. 암호 항목은 거기서 멈춤
     */
    private function extract7zFile($entry_name) {
        $tried = null;
        if ($this->entries === null && strpbrk((string)$entry_name, '*?') === false) {
            $tried = (string)$entry_name;
            $data = $this->extractWith7z([$tried]);
            if ($data !== false) return $data;
        }
        $e = $this->findEntry($entry_name);
        if ($e !== null && $e['enc']) return false;
        if ($this->header_encrypted) return false;
        if ($e === null && !empty($this->entries)) return false; // 목록에 없는 이름(예전 캐시의 깨진 이름 등)
        $args = $e !== null ? $e['args'] : [(string)$entry_name];
        $args = array_values(array_filter($args, function ($a) use ($tried) { return $a !== $tried; }));
        return empty($args) ? false : $this->extractWith7z($args);
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
     * ★ (2026-10-31) 목록과 같은 자료에서 꺼냄(도구를 따로 다시 실행하지 않음 — 이름도 목록과 똑같이 맞음)
     */
    public function getFileSizes() {
        $sizes = [];
        if ($this->type !== '7z' && $this->type !== 'rar') return $sizes; // ZIP 은 예전처럼 호출부가 statIndex 로
        foreach ($this->getEntries() as $e) {
            if ($e['dir']) continue;
            $sizes[$e['name']] = $e['size'];
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

    // ============================================================
    // ★ (2026-10-31) 다국어 파일명 판정 — FileStation archiveCjkScore/archiveDetectEncoding/archiveNameToUtf8 이식
    //   · UTF-8 이름은 손대지 않음
    //   · 압축 하나 안의 이름은 인코딩이 하나 → 이름들을 모아 한 번 판정(확신이 없으면 이름별 판정)
    //   · 가나(일본어) > 한글 > 한자 순 가중치, 한글+한자가 섞이면 오판 신호로 감점
    // ============================================================
    private static function isValidUtf8($s) {
        if (!is_string($s)) return false;
        if (function_exists('mb_check_encoding')) return mb_check_encoding($s, 'UTF-8');
        return @preg_match('//u', $s) === 1;
    }

    private static function cjkScore($s) {
        $kana   = preg_match_all('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $s);
        $hangul = preg_match_all('/[\x{AC00}-\x{D7A3}]/u', $s);
        $han    = preg_match_all('/[\x{4E00}-\x{9FFF}]/u', $s);
        $score  = (int)$kana * 3 + (int)$hangul * 2 + (int)$han;
        if ($hangul > 0 && $han > 0) $score *= 0.3;
        return (float)$score;
    }

    /** 후보 인코딩으로 바꾼 값(실패·변환 손실이면 null) */
    private static function convFrom($raw, $enc) {
        $conv = @mb_convert_encoding($raw, 'UTF-8', $enc);
        if ($conv === false || $conv === '' || !mb_check_encoding($conv, 'UTF-8')) return null;
        if (strpos($conv, '?') !== false && strpos($raw, '?') === false) return null;
        return $conv;
    }

    /**
     * 압축 단위 인코딩 판정 — 확신이 설 때만 'CP949'|'SJIS'|'CP936', 아니면 null
     * @param array $rawNames 원본 바이트 이름들
     */
    public static function detectEncoding(array $rawNames) {
        if (!function_exists('mb_check_encoding') || !function_exists('mb_convert_encoding')) return null;
        $vote = ['CP949' => 0.0, 'SJIS' => 0.0, 'CP936' => 0.0];
        $count = 0;
        foreach ($rawNames as $raw) {
            if ($count >= 300) break;
            if (!is_string($raw) || $raw === '') continue;
            if (mb_check_encoding($raw, 'UTF-8')) continue;
            $count++;
            $sc = [];
            foreach (['CP949', 'SJIS', 'CP936'] as $enc) {
                $conv = self::convFrom($raw, $enc);
                $sc[$enc] = ($conv === null) ? 0.0 : self::cjkScore($conv);
            }
            arsort($sc);
            $keys = array_keys($sc);
            $margin = $sc[$keys[0]] - $sc[$keys[1]];
            if ($margin > 0) $vote[$keys[0]] += $margin;
        }
        if ($count < 2) return null;
        arsort($vote);
        $keys = array_keys($vote);
        if ($vote[$keys[0]] <= 0) return null;
        if ($vote[$keys[1]] > 0 && ($vote[$keys[0]] / $vote[$keys[1]] - 1) < 0.20) return null;
        return $keys[0];
    }

    /**
     * ★ (2026-10-31) 옛 ZIP 의 서양 이름(CP437: Müller·Café·naïve 등)처럼 보이는지 — CJK 로 잘못 바꾸지 않게
     *   비ASCII 바이트가 모두 CP437 라틴 글자 자리(0x80~0x9A, 0xA0~0xA5, ß=0xE1)이고, 서로 붙어 있지 않으며,
     *   각각 앞이나 뒤가 영문자일 때(악센트 글자가 영어 단어 안에 하나씩 박힌 모양). 한글 CP949(0xB0 이상)·
     *   일본어 가나(연속 바이트)·한자 이름은 여기에 걸리지 않음
     */
    public static function looksLatinLegacy($raw) {
        if (!is_string($raw) || $raw === '' || !preg_match('/[\x80-\xFF]/', $raw)) return false;
        $isL = function ($c) { return ($c >= 0x41 && $c <= 0x5A) || ($c >= 0x61 && $c <= 0x7A); };
        $len = strlen($raw);
        $i = 0;
        while ($i < $len) {
            if (ord($raw[$i]) < 0x80) { $i++; continue; }
            // 비ASCII 바이트 묶음(run) 단위로 봄
            $j = $i;
            while ($j < $len && ord($raw[$j]) >= 0x80) {
                $b = ord($raw[$j]);
                if (!(($b >= 0x80 && $b <= 0x9A) || ($b >= 0xA0 && $b <= 0xA5) || $b === 0xE1)) return false;
                $j++;
            }
            $run = $j - $i;
            $prevL = $i > 0 && $isL(ord($raw[$i - 1]));
            $nextL = $j < $len && $isL(ord($raw[$j]));
            if ($run > 2) return false;
            if ($run === 1 && !$prevL && !$nextL) return false;   // 악센트 글자 하나: 앞이나 뒤가 영문자
            if ($run === 2 && !($prevL && $nextL)) return false;  // 두 글자 붙음(Größe 의 öß): 앞뒤 모두 영문자
            $i = $j;
        }
        return true;
    }
    
    /** 정해진 인코딩으로만 바꾸기(실패하면 null) */
    public static function convertWith($raw, $enc) {
        if (!is_string($raw) || $raw === '' || !function_exists('mb_convert_encoding') || !in_array($enc, ['CP949', 'SJIS', 'CP936'], true)) return null;
        return self::convFrom($raw, $enc);
    }
    
    /**
     * 압축 속 이름 하나를 UTF-8 로 — 못 바꾸면 원본 그대로(잘못된 UTF-8 일 수 있음)
     */
    public static function nameToUtf8($raw, $hint = null) {
        if (!is_string($raw) || $raw === '' || !function_exists('mb_check_encoding') || !function_exists('mb_convert_encoding')) {
            return $raw;
        }
        // UTF-8 이름은 그대로 — FileStation 의 latin-1 되돌리기(tar 목록용)는 쓰지 않음:
        //   myComix 가 여는 7z/RAR/ZIP 은 그런 이름이 나오지 않고, 'Crème brûlée' 같은 정상 이름을 CJK 로 잘못 바꿀 위험만 있음
        if (mb_check_encoding($raw, 'UTF-8')) return $raw;
        if ($hint !== null && in_array($hint, ['CP949', 'SJIS', 'CP936'], true)) {
            $conv = self::convFrom($raw, $hint);
            if ($conv !== null) return $conv;
        }
        $best = null; $bestScore = 0.0;
        foreach (['CP949', 'SJIS', 'CP936'] as $enc) {
            $conv = self::convFrom($raw, $enc);
            if ($conv === null) continue;
            $score = self::cjkScore($conv);
            if ($score > $bestScore) { $bestScore = $score; $best = $conv; }
        }
        return ($best !== null) ? $best : $raw;
    }

    // ============================================================
    // ★ (2026-10-31) RAR 헤더 직접 읽기 (FileStation RarNative 이식 + 개선)
    //   · 파일 전체를 메모리에 올리지 않고 헤더만 읽고 데이터 부분은 건너뜀(FileStation 은 통째로 읽어 큰 RAR 에서 메모리 초과 위험)
    //   · 헤더 암호(RAR4 MHD_PASSWORD / RAR5 암호 헤더) 감지
    //   · 구조가 이상하면 null → 도구(unrar/7z) 목록으로 넘어감(어설픈 목록을 내지 않음)
    //   반환: ['fmt' => 'RAR4'|'RAR5', 'hdrenc' => bool, 'items' => [['raw','size','dir','enc'], ...]] 또는 null
    // ============================================================
    private static function rarNativeList($path) {
        $fh = @fopen($path, 'rb');
        if (!$fh) return null;
        try {
            $sig = @fread($fh, 8);
            if (!is_string($sig) || strlen($sig) < 7) return null;
            if ($sig === "Rar!\x1a\x07\x01\x00") return self::rar5Parse($fh);
            if (substr($sig, 0, 7) === "Rar!\x1a\x07\x00") { fseek($fh, 7); return self::rar4Parse($fh); }
            return null; // SFX·옛 1.x 등은 도구에 맡김
        } catch (\Throwable $e) {
            return null;
        } finally {
            @fclose($fh);
        }
    }

    private static function rar5ReadVint($s, &$p) {
        $val = 0; $shift = 0; $len = strlen($s);
        while ($p < $len) {
            $b = ord($s[$p]); $p++;
            if ($shift < 63) $val |= ($b & 0x7F) << $shift;
            if (!($b & 0x80)) return $val;
            $shift += 7;
            if ($shift > 70) return -1;
        }
        return -1;
    }

    private static function rar5Parse($fh) {
        $items = [];
        $guard = 0;
        $fsize = @filesize(stream_get_meta_data($fh)['uri'] ?? '');
        while (true) {
            if (++$guard > 300000) return null;
            $pre = fread($fh, 7); // CRC32(4) + 크기 vint(최대 3바이트)
            if ($pre === false || $pre === '') break;
            if (strlen($pre) < 5) break;
            $p = 4;
            $hsize = self::rar5ReadVint($pre, $p);
            if ($hsize <= 0 || $hsize > 2097152) return null;
            $bodyStart = ftell($fh) - strlen($pre) + $p;
            fseek($fh, $bodyStart);
            $body = fread($fh, $hsize);
            if ($body === false || strlen($body) < $hsize) return null;
            $q = 0;
            $htype = self::rar5ReadVint($body, $q);
            $hflags = self::rar5ReadVint($body, $q);
            if ($htype < 0 || $hflags < 0) return null;
            $extraSize = 0; $dsize = 0;
            if ($hflags & 0x01) $extraSize = self::rar5ReadVint($body, $q);
            if ($hflags & 0x02) $dsize = self::rar5ReadVint($body, $q);
            if ($extraSize < 0 || $dsize < 0 || $extraSize > $hsize) return null;
            if ($htype === 4) return ['fmt' => 'RAR5', 'hdrenc' => true, 'items' => []]; // 헤더 암호
            if ($htype === 5) break; // 끝
            if ($htype === 2) {
                $fflags = self::rar5ReadVint($body, $q);
                $unp = self::rar5ReadVint($body, $q);
                self::rar5ReadVint($body, $q); // attributes
                if ($fflags & 0x02) $q += 4;   // mtime
                if ($fflags & 0x04) $q += 4;   // data crc32
                self::rar5ReadVint($body, $q); // compression info
                self::rar5ReadVint($body, $q); // host os
                $nlen = self::rar5ReadVint($body, $q);
                if ($nlen <= 0 || $q + $nlen > strlen($body)) return null;
                $nm = substr($body, $q, $nlen);
                $enc = false;
                if ($extraSize > 0) $enc = self::rar5ExtraHasCrypt($body, $hsize - $extraSize, $extraSize);
                // 여러 권 압축의 '앞 권에서 이어지는' 조각은 같은 이름이 또 나오므로 뺌
                if (!($hflags & 0x08)) {
                    $items[] = ['raw' => $nm, 'size' => max(0, (int)$unp), 'dir' => (bool)($fflags & 0x01), 'enc' => $enc];
                }
            }
            $next = $bodyStart + $hsize + $dsize;
            if ($fsize !== false && $fsize > 0 && $next > $fsize) break; // 잘린 파일·여러 권의 첫 권
            if (fseek($fh, $next) !== 0) break;
        }
        return ['fmt' => 'RAR5', 'hdrenc' => false, 'items' => $items];
    }

    private static function rar5ExtraHasCrypt($s, $start, $size) {
        $end = $start + $size;
        if ($start < 0 || $end > strlen($s)) return false;
        $p = $start; $guard = 0;
        while ($p < $end) {
            if (++$guard > 1000) break;
            $recSize = self::rar5ReadVint($s, $p);
            if ($recSize <= 0) break;
            $recBodyStart = $p;
            $recType = self::rar5ReadVint($s, $p);
            if ($recType === 1) return true; // File encryption record
            $p = $recBodyStart + $recSize;
        }
        return false;
    }

    private static function rar4Parse($fh) {
        $items = [];
        $guard = 0;
        $fsize = @filesize(stream_get_meta_data($fh)['uri'] ?? '');
        while (true) {
            if (++$guard > 300000) return null;
            $pos = ftell($fh);
            $h = fread($fh, 7);
            if ($h === false || strlen($h) < 7) break;
            $u = unpack('vcrc/Ctype/vflags/vsize', $h);
            $type = $u['type']; $flags = $u['flags']; $size = $u['size'];
            if ($size < 7) return null;
            $rest = ($size > 7) ? fread($fh, $size - 7) : '';
            if ($rest === false || strlen($rest) < $size - 7) {
                if ($type === 0x7b) break;
                return null;
            }
            $add = 0;
            if ($type === 0x73) { // main
                if ($flags & 0x0080) return ['fmt' => 'RAR4', 'hdrenc' => true, 'items' => []]; // 헤더 암호
            } elseif ($type === 0x74) { // file
                if (strlen($rest) < 25) return null;
                $f = unpack('Vpack/Vunp/Chost/Vcrc/Vtime/Cver/Cmethod/vnsize/Vattr', substr($rest, 0, 25));
                $pack = $f['pack']; $unp = $f['unp'];
                $off = 25;
                if ($flags & 0x100) {
                    if (strlen($rest) < 33) return null;
                    $hi = unpack('Vhp/Vhu', substr($rest, 25, 8));
                    $pack += $hi['hp'] * 4294967296; $unp += $hi['hu'] * 4294967296;
                    $off = 33;
                }
                $nsize = $f['nsize'];
                if ($nsize <= 0 || $off + $nsize > strlen($rest)) return null;
                $raw = substr($rest, $off, $nsize);
                if ($flags & 0x200) { // 유니코드 이름
                    $zp = strpos($raw, "\x00");
                    $nm = ($zp !== false) ? self::rar4DecodeUnicode(substr($raw, 0, $zp), substr($raw, $zp + 1)) : $raw;
                } else {
                    $nm = $raw; // OEM(CP949/SJIS 등) — 호출측에서 판정
                }
                // 여러 권 압축의 '앞 권에서 이어지는' 조각(0x01)은 뺌
                if (!($flags & 0x01)) {
                    $items[] = ['raw' => $nm, 'size' => (int)$unp, 'dir' => (($flags & 0xE0) === 0xE0), 'enc' => (bool)($flags & 0x04)];
                }
                $add = $pack;
            } elseif ($type === 0x7b) { // end
                break;
            } elseif ($flags & 0x8000) {
                if (strlen($rest) < 4) return null;
                $add = unpack('V', substr($rest, 0, 4))[1];
            }
            $next = $pos + $size + $add;
            if ($fsize !== false && $fsize > 0 && $next > $fsize) break;
            if (fseek($fh, $next) !== 0) break;
        }
        return ['fmt' => 'RAR4', 'hdrenc' => false, 'items' => $items];
    }

    /** RAR4 유니코드 파일명 디코더 (unrar EncodeFileName::Decode 알고리즘) → UTF-8 */
    private static function rar4DecodeUnicode($oem, $enc) {
        $encLen = strlen($enc);
        if ($encLen === 0) return $oem;
        $high = ord($enc[0]); $encPos = 1;
        $flagBits = 0; $flags = 0; $decPos = 0;
        $oemLen = strlen($oem); $cps = [];
        $guard = 0;
        while ($encPos < $encLen) {
            if (++$guard > 100000) break;
            if ($flagBits === 0) {
                $flags = ord($enc[$encPos]); $encPos++; $flagBits = 8;
                if ($encPos > $encLen) break;
            }
            switch ($flags >> 6) {
                case 0:
                    if ($encPos >= $encLen) break 2;
                    $cps[$decPos++] = ord($enc[$encPos]); $encPos++;
                    break;
                case 1:
                    if ($encPos >= $encLen) break 2;
                    $cps[$decPos++] = ord($enc[$encPos]) + ($high << 8); $encPos++;
                    break;
                case 2:
                    if ($encPos + 1 >= $encLen) break 2;
                    $cps[$decPos++] = ord($enc[$encPos]) + (ord($enc[$encPos + 1]) << 8); $encPos += 2;
                    break;
                case 3:
                    if ($encPos >= $encLen) break 2;
                    $length = ord($enc[$encPos]); $encPos++;
                    if ($length & 0x80) {
                        if ($encPos >= $encLen) break 2;
                        $corr = ord($enc[$encPos]); $encPos++;
                        for ($l = ($length & 0x7f) + 2; $l > 0 && $decPos < 4096; $l--, $decPos++) {
                            $base = ($decPos < $oemLen) ? ord($oem[$decPos]) : 0;
                            $cps[$decPos] = (($base + $corr) & 0xff) + ($high << 8);
                        }
                    } else {
                        for ($l = $length + 2; $l > 0 && $decPos < 4096; $l--, $decPos++) {
                            $cps[$decPos] = ($decPos < $oemLen) ? ord($oem[$decPos]) : 0;
                        }
                    }
                    break;
            }
            $flags = ($flags << 2) & 0xFF; $flagBits -= 2;
        }
        // UTF-16 코드 단위 → UTF-8 (서로게이트 쌍도 합침)
        $out = '';
        $n = count($cps);
        for ($i = 0; $i < $n; $i++) {
            $cp = $cps[$i] ?? 0;
            if ($cp === 0) break;
            if ($cp >= 0xD800 && $cp <= 0xDBFF && isset($cps[$i + 1]) && $cps[$i + 1] >= 0xDC00 && $cps[$i + 1] <= 0xDFFF) {
                $cp = 0x10000 + (($cp - 0xD800) << 10) + ($cps[$i + 1] - 0xDC00);
                $i++;
            } elseif ($cp >= 0xD800 && $cp <= 0xDFFF) {
                $cp = 0xFFFD;
            }
            if ($cp < 0x80)        $out .= chr($cp);
            elseif ($cp < 0x800)   $out .= chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
            elseif ($cp < 0x10000) $out .= chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
            else                   $out .= chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        }
        return $out;
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
        // 파일 우선 — ★ (2026-10-30) 링크는 따라가지 않음
        foreach ($entries as $e) {
            $p = $dir . '/' . $e;
            if (!is_link($p) && is_file($p)) return $p;
        }
        // 하위 폴더 탐색
        foreach ($entries as $e) {
            $p = $dir . '/' . $e;
            if (!is_link($p) && is_dir($p)) {
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
            // ★ (2026-10-30) 링크·정션은 따라 들어가지 않고 링크만 지움(압축이 풀어 놓은 폴더 링크의 대상 내용을 지우지 않게)
            if (is_link($path)) { @unlink($path) || @rmdir($path); continue; }
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

// ============================================================
// ★ (2026-10-31) ZIP 다국어 이름 공용 함수 — ZipArchive 를 직접 쓰는 곳(index/viewer/thumb/bookmark/cache_util)에서 사용
//   [문제] ZipArchive::getNameIndex() 는 UTF-8 표시가 없는 옛 ZIP 이름을 CP437 로 보고 바꿔서
//          한글·일본어·중국어 이름이 '╟╤▒█' 처럼 깨지고, 그 깨진 이름으로 정렬돼 쪽 순서도 틀어짐
//   [처리] 원본 바이트(FL_ENC_RAW)를 압축 단위로 판정해 UTF-8 로. 확신이 없거나 못 바꾸면 예전 이름 그대로
//          (UTF-8 표시가 있는 ZIP 은 한 글자도 바뀌지 않음)
//   [꺼내기] 바뀐 이름은 ZipArchive 가 모르므로 mc_zip_locate() 로 순번을 찾아 꺼냄 —
//          예전 이름(이전에 만들어 둔 캐시 속 이름)으로 와도 그대로 찾음
// ============================================================
if (!function_exists('mc_zip_names')) {
    /** ZIP 의 모든 항목 이름(순번 => UTF-8 이름) */
    function mc_zip_names($zip) {
        static $memo = [];
        if (!($zip instanceof ZipArchive)) return [];
        $fn = (string)($zip->filename ?? '');
        $key = spl_object_id($zip) . '|' . $fn . '|' . $zip->numFiles . '|' . @filesize($fn) . '|' . @filemtime($fn);
        if (isset($memo[$key])) return $memo[$key];
        if (count($memo) > 64) $memo = [];
        $n = $zip->numFiles;
        $guess = [];
        $raw = [];
        for ($i = 0; $i < $n; $i++) {
            $g = $zip->getNameIndex($i);
            $guess[$i] = is_string($g) ? $g : '';
            $r = defined('ZipArchive::FL_ENC_RAW') ? @$zip->getNameIndex($i, ZipArchive::FL_ENC_RAW) : false;
            $raw[$i] = is_string($r) ? $r : $guess[$i];
        }
        $names = $guess;
        if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding')) {
            $legacy = [];
            $has_legacy = false;
            foreach ($raw as $i => $r) {
                if ($r === '' || mb_check_encoding($r, 'UTF-8')) continue;
                $has_legacy = true;
                // 서양 이름(CP437)처럼 보이는 것은 판정 표본에서 뺌 — Müller·Café 가 중국어·일본어 쪽 표를 만들지 않게
                if (!ArchiveHandler::looksLatinLegacy($r)) $legacy[] = $r;
            }
            if ($has_legacy) {
                $hint = ArchiveHandler::detectEncoding(array_slice($legacy, 0, 500));
                foreach ($raw as $i => $r) {
                    if ($r === '' || mb_check_encoding($r, 'UTF-8')) continue; // UTF-8 이름은 그대로
                    if (ArchiveHandler::looksLatinLegacy($r)) {
                        // 서양 이름처럼 보이면 예전(CP437) 이름 유지 — 압축 전체가 CJK 로 판정됐을 때만 그 인코딩으로
                        $c = ($hint !== null) ? ArchiveHandler::convertWith($r, $hint) : null;
                    } else {
                        $c = ArchiveHandler::nameToUtf8($r, $hint);
                    }
                    if (is_string($c) && $c !== '' && $c !== $r && mb_check_encoding($c, 'UTF-8')) $names[$i] = $c;
                }
            }
        }
        return $memo[$key] = $names;
    }

    /** 한 항목 이름(getNameIndex 대신) */
    function mc_zip_name($zip, $i) {
        $names = mc_zip_names($zip);
        if (isset($names[$i]) && $names[$i] !== '') return $names[$i];
        return $zip->getNameIndex($i);
    }

    /** 이름 → 순번(바꾼 이름·예전 이름 모두). 없으면 false */
    function mc_zip_locate($zip, $name) {
        if (!($zip instanceof ZipArchive) || !is_string($name) || $name === '') return false;
        $i = $zip->locateName($name);
        if ($i !== false) return $i;
        $names = mc_zip_names($zip);
        $j = array_search($name, $names, true);
        return ($j === false) ? false : (int)$j;
    }

    /** getFromName 대신 — 바꾼 이름으로도 꺼냄 */
    function mc_zip_from_name($zip, $name, $len = 0) {
        $i = mc_zip_locate($zip, $name);
        if ($i === false) return false;
        return $len > 0 ? $zip->getFromIndex($i, $len) : $zip->getFromIndex($i);
    }

    /** ZipArchive 가 아는 원래 이름(statName/getStream 등 이름만 받는 함수용) */
    function mc_zip_native_name($zip, $name) {
        $i = mc_zip_locate($zip, $name);
        if ($i === false) return $name;
        $g = $zip->getNameIndex($i);
        return is_string($g) ? $g : $name;
    }
}

// ✅ ArchiveHandler::configure()는 config.php에서 호출됨
// 이 파일이 단독으로 로드되는 경우는 없으므로 여기서 configure 불필요
