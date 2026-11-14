<?php
/**
 * ★ (2026-11-13 10:16, 펜닐 요청 — fix141) FileStation 동영상 플레이어 서버 기능 이식 — FileManager.php 의 함수를 그대로 옮김(FileStation 5.9.0 109차)
 *   · 재생바 미리보기 장면: videoFrameFile(한 장) · videoFramesFile(묶음 — ffmpeg 1번) · videoKeyTimesFile(mp4 키프레임 시각) · 짧은 영상 정확 탐색(a=1)
 *   · 빠른 시작(원본 스트리밍): directStreamFile — mp4(H.264·HEVC)를 다시 인코딩하지 않고 키프레임 조각(HLS)으로, 파이프/파일 방식 자동 선택
 *   · 도우미: escapeShellPath · getWindowsShortPath(윈도우 한글 경로 → 8.3 짧은 경로) · Mp4KeyIndex(video_mp4idx.php — FileStation 파일 그대로)
 *   바꾼 곳(myComix 에 맞춤 — 동작은 같음): ffmpeg 위치 = 관리자 설정(mcv_ffmpeg) · 장면 캐시 = cache/_vframes · 작업 파일(조각 임시·자동 선택 기록) = cache/_vds
 *   ★ (2026-11-13 18:08, 펜닐 요청 — fix147) 장면 캐시는 FileStation 원본(data/thumbcache/seek)과 같은 방식 그대로 — 자리만 cache/_vframes(지워져도 다시 만듦)
 *     · 변환 세션 폴더(cache/_hls)는 원본 그대로 남김(fix148 — fix146 의 빈 _hls 지우기 되돌림) · 끝내기 때 윈도우에서 로그가 안 지워지던 것 재시도(fix148)
 *   경로 검증은 이 클래스가 하지 않음 — viewer.php 가 로그인·경로·폴더 권한을 확인한 실제 파일만 넘김(FileStation 의 '검증된 경로만' 규칙과 같음)
 *   진단 기록(dsDiag*)은 FileStation 탐색기 전용이라 꺼 둠(dsDiagAllowed = false — 코드는 그대로 둠)
 */
if (!class_exists('MCVfs')) {
class MCVfs {
    /** ★ (2026-11-13 10:27 fix141) HLS 변환 재생 세션 폴더 cache/_hls (FileStation data/hls_sessions) — 이름 앞 '_' 는 계정별 캐시 폴더와 구분 */
    private static function hlsRoot(): string {
        $d = __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '_hls';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }
    private string $mcUser = '';   // 세션 주인(계정) — meta.json 의 mc_user 와 비교
    private string $mcBase = '';   // 재생 목록·조각 주소 앞부분(viewer.php?filetype=video&file=…&vapi=hls)
    private ?array $_siteSettingsCache = null;
    /** FileStation site_settings.json 대신 myComix 관리자 설정(app_settings — config.php 가 읽어 둔 값) — 하드웨어 가속 방식·ffmpeg 경로·VA-API 장치 */
    private function loadSiteSettingsOnce(): array {
        if ($this->_siteSettingsCache !== null) return $this->_siteSettingsCache;
        $a = (isset($GLOBALS['app_settings']) && is_array($GLOBALS['app_settings'])) ? $GLOBALS['app_settings'] : [];
        return $this->_siteSettingsCache = [
            'hw_accel_mode' => (string)($a['hw_accel_mode'] ?? ''),
            'ffmpeg_path' => (string)($GLOBALS['ffmpeg_path'] ?? ($a['ffmpeg_path'] ?? '')),
            'vaapi_device' => (string)($a['vaapi_device'] ?? ''),
        ];
    }
    /**
     * ★ (2026-11-13 10:27, 펜닐 요청 — fix141) HLS 변환 재생 — FileStation 공유 플레이어 hlsShareStream 그대로(아래 함수들 — HW 인코더 감지·HW 실패 시 SW 재시작·
     *   세션 재사용·고아 정리·PID 확인 포함). hls_action=start|playlist|segment|stop, 시작 인자 audio·seek·quality(1080p…144p)·force_sw
     *   $user: 세션 주인(계정 — 다른 계정은 세션 번호를 알아도 못 씀) · $base: 이 영상의 viewer.php 주소 + &vapi=hls
     *   조각은 비밀번호 공유처럼 Cache-Control private(로그인한 사람의 것)
     */
    public function hlsStream(string $fullPath, string $user, string $base): void {
        $this->mcUser = $user;
        $this->mcBase = $base;
        $this->hlsShareStream($fullPath, true);
    }

    /**
     * ★ (2026-11-13 10:31, 펜닐 요청 — fix141) 관리자 '하드웨어 가속' [테스트] — 감지 캐시를 지우고 FileStation detectHwEncoder 를 다시 돌려 결과·과정 기록을 돌려줌
     *   (FileStation 설정 화면의 테스트와 같은 1프레임 인코딩 시험 — 저장된 설정 값으로)
     */
    public function hwRedetect(): array {
        $ff = mcv_ffmpeg();
        if ($ff === '') return ['success' => false, 'error' => 'ffmpeg'];
        $dir = self::workDir();
        @unlink(self::stateDir() . DIRECTORY_SEPARATOR . 'hw_encoder_cache.json');   // ★ fix143 감지 결과는 src/video/vstate
        $args = $this->detectHwEncoder($ff);
        $c = @json_decode((string)@file_get_contents(self::stateDir() . DIRECTORY_SEPARATOR . 'hw_encoder_cache.json'), true);
        return ['success' => true, 'encoder' => is_array($c) ? (string)($c['encoder'] ?? '') : $this->extractEncoderFromArgs($args),
                'log' => (string)@file_get_contents($dir . DIRECTORY_SEPARATOR . 'hw_detect.log')];
    }
    /** 지금 쓰는(감지해 둔) 인코더 이름 — 감지 전이면 '' (관리자 화면 표시용, 감지를 새로 돌리지 않음) */
    public static function hwCachedEncoder(): string {
        $c = @json_decode((string)@file_get_contents(self::stateDir() . DIRECTORY_SEPARATOR . 'hw_encoder_cache.json'), true);   // ★ fix143
        return is_array($c) ? (string)($c['encoder'] ?? '') : '';
    }

    /**
     * ★ (2026-11-13 14:41, 펜닐 요청 — fix143) 지우면 안 되는 기록은 src/video/vstate — 하드웨어 인코더 감지 결과(hw_encoder_cache.json — 없으면 첫 변환 재생 때 수 초~수십 초 다시 감지)·
     *   빠른 시작 파이프/파일 선택 기록(ds_auto.json — 없으면 처음부터 다시 잼). cache 는 지워져도 되는 것만(세션 조각·임시 조각·과정 기록)
     *   fix141 때 cache/_vds 에 두던 두 파일은 처음 쓸 때 이리 옮김(이름 그대로) · src/video 는 .htaccess 로 막힘(mcv_cache_dir 이 만듦)
     */
    private static function stateDir(): string {
        $root = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'video';
        $d = function_exists('mcv_cache_dir') ? (string)mcv_cache_dir('vstate') : '';
        if ($d === '') { $d = $root . DIRECTORY_SEPARATOR . 'vstate'; if (!is_dir($d)) @mkdir($d, 0755, true); }
        static $moved = false;
        if (!$moved) {
            $moved = true;
            foreach (['hw_encoder_cache.json', 'ds_auto.json'] as $n) {
                $old = self::workDir() . DIRECTORY_SEPARATOR . $n;
                if (is_file($old) && !is_file($d . DIRECTORY_SEPARATOR . $n)) @rename($old, $d . DIRECTORY_SEPARATOR . $n);
            }
        }
        return $d;
    }

    /** myComix 작업 폴더 cache/_vds — 빠른 시작 조각 임시 파일(ds_tmp)·과정 기록(hw_detect.log 등) — 지워져도 되는 것만(fix143). cache 는 .htaccess 로 막힘 */
    private static function workDir(): string {
        $d = __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '_vds';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }

    private bool $dsDiagAllowed = false;      // FileStation: 탐색기 경로만 true — myComix 는 늘 false(진단 기록 안 함)

    private function dsDiagWrite(string $event, array $data): void {
        try {
            $dir = self::workDir();   // ★ myComix: cache/_vds
            $file = $dir . '/hls_diag.log';
            if (is_file($file) && filesize($file) > 5242880) @file_put_contents($file, '');
            @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] [server] ' . $event . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {}
    }

    private function dsDiagEnv(): array {
        $h = function_exists('headers_list') ? implode(';', headers_list()) : '';
        return [
            'proto' => (string)($_SERVER['SERVER_PROTOCOL'] ?? ''), 'sapi' => PHP_SAPI,
            'ob' => ob_get_level(), 'obIni' => (string)ini_get('output_buffering'), 'zlib' => (string)ini_get('zlib.output_compression'),
            'implicit' => (string)ini_get('implicit_flush'), 'enc' => (stripos($h, 'Content-Encoding') !== false) ? 'yes' : 'no',
        ];
    }

    private function dsAutoLoad(bool $useApcu): array {
        $st = null;
        try {
            if ($useApcu) { $hit = false; $c = @apcu_fetch('fs_ds_auto|' . PHP_OS_FAMILY, $hit); if ($hit && is_array($c)) $st = $c; }
            else {
                $f = self::stateDir() . '/ds_auto.json';
                if (is_file($f)) { $c = json_decode((string)@file_get_contents($f), true); if (is_array($c) && ($c['os'] ?? '') === PHP_OS_FAMILY) $st = $c; }
            }
        } catch (\Throwable $e) {}
        if (!is_array($st)) $st = [];
        return [
            'pipe' => array_values(array_filter((array)($st['pipe'] ?? []), 'is_numeric')),
            'file' => array_values(array_filter((array)($st['file'] ?? []), 'is_numeric')),
            'mode' => in_array($st['mode'] ?? '', ['pipe', 'file'], true) ? $st['mode'] : '',
            'at' => (int)($st['at'] ?? 0), 'ttl' => max(60, min(86400, (int)($st['ttl'] ?? 86400))), 'why' => (string)($st['why'] ?? ''),
        ];
    }

    private function dsAutoSave(bool $useApcu, array $st): void {
        try {
            $st['os'] = PHP_OS_FAMILY;
            if ($useApcu) { @apcu_store('fs_ds_auto|' . PHP_OS_FAMILY, $st, 7 * 86400); return; }
            $f = self::stateDir() . '/ds_auto.json';
            @file_put_contents($f, json_encode($st), LOCK_EX);
        } catch (\Throwable $e) {}
    }

    private function dsAutoPick(array $st): bool {
        if ($st['mode'] !== '' && time() - $st['at'] < $st['ttl']) return $st['mode'] === 'file';
        $p = count($st['pipe']); $f = count($st['file']);
        if ($f < $p) return true;
        if ($p < $f) return false;
        return PHP_OS_FAMILY === 'Windows';
    }

    private function dsAutoRecord(bool $useApcu, string $mode, float $ms, int $bytes): ?array {
        if ($bytes < 262144 || $ms <= 0) return null;                  // 너무 작은 조각은 시작 비용이 커 비교가 흐려짐 — 빼고
        $st = $this->dsAutoLoad($useApcu);
        if ($st['mode'] !== '' && time() - $st['at'] < $st['ttl']) return null;   // 이미 정함 — 쓰기 없음
        $st[$mode][] = round($ms / ($bytes / 1048576), 1);
        $st[$mode] = array_slice($st[$mode], -5);
        $res = null;
        if (count($st['pipe']) >= 5 && count($st['file']) >= 5) {
            $med = function (array $a) { sort($a); return $a[intdiv(count($a), 2)]; };
            $mp = $med($st['pipe']); $mf = $med($st['file']);
            $st['mode'] = ($mf < $mp * 0.6) ? 'file' : 'pipe';   // 40% 넘게 빠를 때만 — 리눅스처럼 비슷하면(시험 환경 두 번 잼: 89/80·108/72) 흔들리지 않게 파이프
            $st['at'] = time(); $st['ttl'] = 86400; $st['why'] = 'pipe ' . $mp . ' / file ' . $mf . ' ms/MB';
            $res = ['mode' => $st['mode'], 'pipe' => $st['pipe'], 'file' => $st['file'], 'pipeMed' => $mp, 'fileMed' => $mf];
            $st['pipe'] = []; $st['file'] = [];
        }
        $this->dsAutoSave($useApcu, $st);
        return $res;
    }

    private function dsAutoForcePipe(bool $useApcu, string $why): void {
        $st = $this->dsAutoLoad($useApcu);
        $st['mode'] = 'pipe'; $st['at'] = time(); $st['ttl'] = 3600; $st['why'] = 'file_fail ' . $why; $st['pipe'] = []; $st['file'] = [];
        $this->dsAutoSave($useApcu, $st);
    }

    private function escapeShellPath(string $path): string {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: 큰따옴표로 감싸고, 내부의 큰따옴표만 이스케이프
            $path = str_replace('"', '""', $path);
            // 환경 변수 확장 방지: % → %% (큰따옴표 안에서 %VAR% 확장 차단)
            $path = str_replace('%', '%%', $path);
            return '"' . $path . '"';
        }
        return escapeshellarg($path);
    }

    private function getWindowsShortPath(string $path): ?string {
        if (PHP_OS_FAMILY !== 'Windows') return null;
        if (!file_exists($path)) return null;
        
        try {
            // COM 객체를 사용한 짧은 경로 가져오기
            if (class_exists('COM')) {
                $fso = new \COM('Scripting.FileSystemObject');
                if (is_dir($path)) {
                    $obj = $fso->GetFolder($path);
                } else {
                    $obj = $fso->GetFile($path);
                }
                $shortPath = $obj->ShortPath;
                if ($shortPath && $shortPath !== $path) {
                    return $shortPath;
                }
            }
        } catch (\Exception $e) {}
        
        // fallback: dir /X
        $parent = dirname($path);
        $basename = basename($path);
        $output = shell_exec('dir /X ' . $this->escapeShellPath($parent) . ' 2>NUL');
        if ($output && preg_match('/\s([A-Z0-9~]+\.\S+)\s+' . preg_quote($basename, '/') . '/i', $output, $m)) {
            return $parent . DIRECTORY_SEPARATOR . $m[1];
        }
        
        return null;
    }

    public function directStreamFile(string $fullPath, string $segBase): void {
        $dgIn = microtime(true);   // ★ (2026-10-12) 진단 — 이 함수 시작(앞은 PHP 시작·로그인·권한)
        require_once __DIR__ . '/video_mp4idx.php';
        $ds = $_GET['ds'] ?? 'playlist';
        $fail = function (int $code, string $err) { http_response_code($code); header('Content-Type: application/json'); header('Cache-Control: no-store'); echo json_encode(['error' => $err]); exit; };
        if (!is_file($fullPath)) $fail(404, 'File not found');
        if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'mp4') $fail(415, 'not_mp4');

        // 키프레임 목록 — 파일 크기·수정 시각이 같으면 메모리 캐시 사용
        $st = @stat($fullPath);
        // ★ (2026-09-30) '|v2' — 목차에 음성 트랙 목록(acodecs)이 생겨 예전 저장값을 쓰지 않게(조각 주소의 k 도 함께 바뀜)
        // ★ (2026-10-12) '|v3' — 조각을 약 4초 → 약 2초로 묶게 바뀌어 조각 번호가 달라졌다. 조각 주소의 k 가 함께 바뀌므로 바꾸기 전 목록을 가진 재생기는
        //   409(file_changed)를 받고 엉뚱한 구간을 받지 않는다(키프레임 목록 메모리 저장도 새 이름으로 다시 만듦).
        $key = 'fs_ds_' . md5($fullPath . '|' . ($st['size'] ?? 0) . '|' . ($st['mtime'] ?? 0) . '|v4');   // ★ (2026-11-11 18:36, 79차 VID-1) '|v4' — 목차에 회전 표시(rot)가 생겨 예전 저장값(APCu 1시간)을 쓰지 않게
        $tag = substr($key, 6, 12);
        $useApcu = function_exists('apcu_fetch') && (!function_exists('apcu_enabled') || @apcu_enabled());
        $idx = null; $dgIdxHit = false;
        if ($useApcu) { $hit = false; $c = @apcu_fetch($key, $hit); if ($hit && is_array($c)) { $idx = $c; $dgIdxHit = true; } }
        if ($idx === null) {
            $idx = Mp4KeyIndex::read($fullPath);
            if ($idx === null) $fail(415, 'unsupported_file');
            if ($useApcu) @apcu_store($key, $idx, 3600);
        }
        $dgIdx = microtime(true);   // ★ (2026-10-12) 진단 — 키프레임 목록 준비 끝
        // ★ (2026-09-30) H.264 는 TS 조각(종전), HEVC(hvc1/hev1)는 조각 mp4(fMP4 — hls.js·사파리는 HEVC 를 TS 로 받지 않음). 둘 다 복사(다시 인코딩 없음).
        $vc = $idx['vcodec'];
        $isHevc = in_array($vc, ['hvc1', 'hev1'], true);
        if (!$isHevc && !in_array($vc, ['avc1', 'avc3'], true)) $fail(415, 'unsupported_codec');
        if (!$isHevc && !empty($idx['rot'])) $fail(415, 'rotated');   // ★ (2026-11-11 18:36, 79차 VID-1) 회전 정보가 있는 H.264 mp4 는 빠른 시작(TS 조각 — 회전 정보가 사라짐)을 쓰지 않고 화면이 종전 일반 재생으로(HEVC 조각 mp4 는 회전이 유지돼 그대로)
        // ★ (2026-09-30) 음성 여러 개 — a=번호(0부터, ffmpeg 0:a:N 순서). 범위 밖이면 거절. 음성이 없으면 0.
        $acodecs = $idx['acodecs'] ?? ((($idx['acodec'] ?? '') !== '') ? [$idx['acodec']] : []);
        $a = (int)($_GET['a'] ?? 0);
        if ($acodecs) { if ($a < 0 || $a >= count($acodecs)) $fail(400, 'bad_audio'); } else { $a = 0; }
        // ★ (2026-10-12) 약 4초 → 약 2초씩(키프레임 경계 — 키프레임 간격이 2초보다 길면 그 간격대로). 받지 않은 위치로 이동할 때 조각 하나를 받아야
        //   화면이 나오는데, 전송이 대부분이라(펜닐 로그 21:38 — 서버 준비 8ms·ffmpeg 80ms·전송 1.4초/1.6MB) 조각이 작을수록 빨리 나온다(펜닐 승인).
        $segs = Mp4KeyIndex::plan($idx, 2.0);
        if (!$segs) $fail(415, 'unsupported_file');
        $sfx = '&k=' . $tag . '&a=' . $a;

        if ($ds === 'playlist') {
            $td = 1; foreach ($segs as $sg) $td = max($td, (int)ceil($sg[1] - $sg[0]));
            $out = "#EXTM3U\n#EXT-X-VERSION:" . ($isHevc ? 7 : 3) . "\n#EXT-X-PLAYLIST-TYPE:VOD\n#EXT-X-TARGETDURATION:{$td}\n#EXT-X-MEDIA-SEQUENCE:0\n";
            if ($isHevc) $out .= "#EXT-X-INDEPENDENT-SEGMENTS\n#EXT-X-MAP:URI=\"" . preg_replace('/([?&])ds=segment/', '$1ds=init', $segBase, 1) . $sfx . "\"\n";
            foreach ($segs as $i => $sg) $out .= sprintf("#EXTINF:%.6f,\n%s%s&i=%d\n", $sg[1] - $sg[0], $segBase, $sfx, $i);
            $out .= "#EXT-X-ENDLIST\n";
            header('Content-Type: application/vnd.apple.mpegurl');
            header('Cache-Control: no-store');
            header('Content-Length: ' . strlen($out));
            $dgPl0 = microtime(true);
            echo $out;
            @flush();
            // ★ (2026-10-13) 진단 — 조각 목록(m3u8) 크기·만들기·보내기 시간(조각 2초로 목록이 커져서 — 70분 영상 약 670KB)
            if ($this->dsDiagAllowed && ($_COOKIE['fs_dsdiag'] ?? '') === '1') {
                $dgT0p = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : $dgIn;
                $this->dsDiagWrite('ds_pl_srv', ['segs' => count($segs), 'kb' => (int)round(strlen($out) / 1024), 'pre' => (int)round(($dgIn - $dgT0p) * 1000),
                    'idx' => (int)round(($dgIdx - $dgIn) * 1000), 'idxHit' => $dgIdxHit, 'make' => (int)round(($dgPl0 - $dgIdx) * 1000), 'send' => (int)round((microtime(true) - $dgPl0) * 1000),
                    'env' => $this->dsDiagEnv()]);
            }
            exit;
        }
        if (!($ds === 'segment' || ($ds === 'init' && $isHevc))) $fail(400, 'bad_request');
        if (($_GET['k'] ?? '') !== $tag) $fail(409, 'file_changed');
        $i = ($ds === 'init') ? 0 : (int)($_GET['i'] ?? -1);        // 초기화 조각은 첫 조각을 만들어 그 목차 부분만 쓴다(조각마다 목차가 같음 — 검증)
        if (!isset($segs[$i])) $fail(404, 'no_segment');

        // ★ (2026-10-12) ffmpeg 위치·윈도우 짧은 경로를 메모리(APCu) 10분 저장 — 조각마다 'ffmpeg -version' 을 실제로 실행해 위치를 확인하고(윈도우는 프로세스
        //   띄우기가 비쌈) 짧은 경로용 COM 객체를 새로 만들었다(펜닐 로그 21:22 — 조각마다 요청 도착 → ffmpeg 시작까지 약 0.59초, 다른 API 는 약 0.03초).
        //   원본 스트리밍 조각(이 함수)에서만 — 다른 기능의 findFfmpeg 는 그대로. 저장값으로 ffmpeg 를 못 띄우거나 아무것도 안 나오면 저장값을 지우고
        //   종전처럼 오류 응답(재생기는 다음 요청에서 다시 확인한 값으로 받음). APCu 가 없으면 종전과 같음.
        $ffKey = 'fs_ds_ffmpeg|' . PHP_OS_FAMILY;
        $dgFf0 = microtime(true); $dgFfHit = false; $ffmpeg = null;
        if ($useApcu) { $hit = false; $c = @apcu_fetch($ffKey, $hit); if ($hit && is_string($c) && $c !== '') { $ffmpeg = $c; $dgFfHit = true; } }
        if ($ffmpeg === null) {
            $ffmpeg = (mcv_ffmpeg() ?: null);
            if ($ffmpeg && $useApcu) @apcu_store($ffKey, $ffmpeg, 600);
        }
        $dgFf1 = microtime(true);
        if (!$ffmpeg) $fail(500, 'ffmpeg not available');
        $ffmpegInput = $fullPath; $dgSpHit = false;
        if (PHP_OS_FAMILY === 'Windows') {
            $spKey = 'fs_ds_sp_' . md5($fullPath . '|' . ($st['size'] ?? 0) . '|' . ($st['mtime'] ?? 0));   // 파일이 바뀌면 다른 키
            $short = null;
            if ($useApcu) { $hit = false; $c = @apcu_fetch($spKey, $hit); if ($hit && is_string($c)) { $short = $c; $dgSpHit = true; } }   // '' = 짧은 경로 없음(원래 경로 사용)
            if ($short === null) {
                $short = (string)($this->getWindowsShortPath($fullPath) ?? '');
                if ($useApcu) @apcu_store($spKey, $short, 600);
            }
            if ($short !== '') $ffmpegInput = $short;
        }
        $dgSp = microtime(true);
        $inputPath = $this->escapeShellPath($ffmpegInput);
        [$ss, , $cut] = $segs[$i];
        // ★ (2026-09-30) 초기화 조각은 목차(ftyp+moov)만 쓰므로 첫 0.1초 분량만 만든다 — 전체 조각으로 만든 목차와 바이트 단위로 같음 확인
        //   (HEVC hvc1/hev1·음성 복사/변환·38MB 조각 파일; 출력 38MB → 0.58MB).
        if ($ds === 'init') $cut = min($cut, $ss + 0.1);
        $hasA = count($acodecs) > 0;
        if (!$hasA || $acodecs[$a] === 'mp4a') {
            $in = ' -ss ' . sprintf('%.6f', $ss) . ' -i ' . $inputPath;
            $maps = ' -map 0:v:0' . ($hasA ? ' -map 0:a:' . $a : '');
            $codecs = ' -c:v copy' . ($hasA ? ' -c:a copy' : '');
        } else {
            // ★ (2026-09-30) AAC 가 아닌 음성(AC-3 등)은 AAC 로 변환 — 이때 음성은 이 조각의 시작 dts(=앞 조각의 끊는 시각)부터 따로 읽는다.
            //   영상 시작(키프레임 표시 시각)부터 풀면 B프레임 영상에서 표시·해석 시각 차이만큼 조각마다 소리가 비었다(0.05~0.2초, 시험에서 확인).
            //   복사는 ffmpeg 가 약간 앞부터 가져와 겹치므로 해당 없음.
            $ass = $i > 0 ? $segs[$i - 1][2] : 0.0;
            $in = ' -ss ' . sprintf('%.6f', $ss) . ' -i ' . $inputPath . ' -ss ' . sprintf('%.6f', $ass) . ' -i ' . $inputPath;
            $maps = ' -map 0:v:0 -map 1:a:' . $a;
            $codecs = ' -c:v copy -c:a aac -b:a 192k -ac 2';
        }
        $fmt = $isHevc
            ? ' -tag:v hvc1 -f mp4 -movflags +frag_keyframe+empty_moov+default_base_moof+frag_discont'   // hvc1: 사파리 필수, frag_discont: 조각 시작 시각을 실제 값으로
            : ' -muxdelay 0 -muxpreload 0 -output_ts_offset 10 -f mpegts';
        $cmd = escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -nostdin' . $in
             . ' -to ' . sprintf('%.6f', $cut) . ' -copyts' . $maps . $codecs . ' -avoid_negative_ts disabled' . $fmt . ' pipe:1';

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']];
        @set_time_limit(0);
        $dgT0 = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);   // ★ (2026-10-12) 진단 — 요청 도착

        // ★ (2026-10-13) 진단용 비교 시험 — '파일 방식'(펜닐 승인). 10-13 로그: 조각 보내기 약 0.59초 중 ffmpeg 출력 기다림(rd)이 거의 전부, 내보내기(wr) 3~5ms,
        //   한 번에 약 6KB·최대 기다림 늘 15~16ms(윈도우 대기 단위) → ffmpeg→PHP 파이프가 느린 것으로 보임(리눅스 시험 환경에선 재현 안 됨 — 추정).
        //   진단이 켜졌고(쿠키 fs_dsdiag=1, 로그인한 탐색기 경로만) 쿠키 fs_dsmode=file(주소 &dsmode=file, app.js)일 때만: ffmpeg 가 같은 조각을 임시 파일로
        //   쓰고(파이프 안 거침) 끝나면 PHP 가 그 파일을 보내고 바로 지운다(조각 하나 약 1MB). 만드는 명령·조각 내용은 같고 출력 위치만 다름(H.264 TS 조각만).
        //   평소(쿠키 없음)는 종전 파이프 방식 그대로. 두 방식의 기록(ds_seg_srv — mode·ffms·send)을 같은 로그에서 비교해 정식 적용을 정한다. → 아래 정식 적용으로 바뀜.
        // ★ (2026-10-13) 정식 적용 — 윈도우 서버는 H.264 조각을 '파일 방식'으로(펜닐 승인). 펜닐 서버 로그(09:20, 파일 방식): ffmpeg 시작~끝 약 80ms·
        //   서버 전체 약 90ms·보내기 0~1ms(08:01 파이프 방식은 ffmpeg 출력 기다림만 520~690ms) → 윈도우 파이프가 느린 것으로 판단.
        //   리눅스 등은 파이프가 빨라(시험 환경 조각 6ms) 종전 파이프 그대로. HEVC(조각 mp4)는 종전 파이프 그대로(따로 확인 후).
        //   ★ (2026-10-13 오전 정정) 위 '6ms' 는 보내는 시간만 본 잘못된 비교 — 같은 기준(ffmpeg 시작 → 조각 다 받음)으로 리눅스 시험 환경은 파이프 약 89·
        //   파일 약 80 ms/MB 로 비슷. 운영체제로 고정하던 것을 이 서버에서 잰 값으로 자동 선택하게 바꿈(아래 dsAutoPick — 펜닐: 환경마다 로그를 못 모음).
        //   진단이 켜졌을 때(로그인한 탐색기 경로)만 주소 &dsmode=file / &dsmode=pipe 로 방식을 골라 비교 가능(쿠키 fs_dsmode — app.js).
        //   임시 폴더(data/ds_tmp)를 못 쓰거나 경로에 영문·숫자 밖의 글자(한글 등 — 셸 명령 인코딩)가 있으면 시스템 임시 폴더, 그것도 안 되면 파이프.
        //   파일 방식에서 ffmpeg 를 못 띄우거나 결과가 비면 같은 요청 안에서 파이프 방식으로 다시(아직 응답을 보내기 전이라 안전).
        $dsFileMode = false; $dsFileFail = null; $dsAuto = null; $dsAutoRes = null; $dsPick = '';
        // ★ (2026-10-14) HEVC(조각 mp4 — 조각·초기화 조각)도 같은 자동 선택에 포함(펜닐 승인). 종전엔 H.264 조각만 — HEVC 는 ffmpeg 출력을 파이프로 끝까지 받은 뒤
        //   보내는 구조라 윈도우에선 같은 이유로 느렸을 것(재보진 않음). 파이프/파일 출력 내용은 바이트 단위로 같음(실측 — ftyp·moov·moof·mdat·mfra 동일).
        if ($ds === 'segment' || ($ds === 'init' && $isHevc)) {
            $dsPick = ($this->dsDiagAllowed && ($_COOKIE['fs_dsdiag'] ?? '') === '1') ? (string)($_COOKIE['fs_dsmode'] ?? '') : '';
            // ★ (2026-10-13 오전) 운영체제로 고정하지 않고 이 서버에서 잰 값으로 자동 선택(dsAutoPick — 정하기 전엔 두 방식 번갈아, 같으면 윈도우 파일·나머지 파이프).
            //   진단 &dsmode=file/pipe 는 종전처럼 강제(잰 값으로는 쓰임).
            if ($dsPick !== 'file' && $dsPick !== 'pipe') { $dsPick = ''; $dsAuto = $this->dsAutoLoad($useApcu); }
            $dsFileMode = $dsPick === 'file' ? true : ($dsPick === 'pipe' ? false : $this->dsAutoPick($dsAuto));
            // 파일 방식이 이 서버에서 실패했으면 10분간 바로 파이프로(조각마다 실패 → 다시 하기로 ffmpeg 를 두 번 띄우지 않게). 진단 &dsmode=file 은 늘 시도.
            // ★ (2026-10-13 재검토) 저장은 '파일 방식 실패 + 파이프 성공'일 때만(아래 파이프 첫 출력 뒤) — 서버 환경 문제일 때만 10분 파이프.
            if ($dsFileMode && $dsPick !== 'file' && $useApcu) { $hit = false; @apcu_fetch('fs_ds_nofile|' . PHP_OS_FAMILY, $hit); if ($hit) { $dsFileMode = false; $dsFileFail = 'cached'; } }
        }
        $dsTmpDir = null;
        if ($dsFileMode) {
            foreach ([self::workDir() . DIRECTORY_SEPARATOR . 'ds_tmp', rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'fs_ds_tmp'] as $cand) {
                if (preg_match('/[^\x20-\x7E]/', $cand)) continue;                 // 한글 등 — 셸 명령에서 깨질 수 있어 건너뜀
                if (!is_dir($cand)) @mkdir($cand, 0755, true);
                if (is_dir($cand) && is_writable($cand)) { $dsTmpDir = $cand; break; }
            }
            if ($dsTmpDir === null) $dsFileFail = 'no_tmp_dir';
        }
        if ($dsTmpDir !== null) {
            // 남은 임시 파일 정리(10분 넘은 것 — 비정상 종료 대비, 가끔만)
            if (mt_rand(1, 20) === 1) { foreach ((array)@glob($dsTmpDir . DIRECTORY_SEPARATOR . 'seg_*') as $old) {   // ★ (2026-10-14) .ts·.mp4(HEVC) 모두
                if (is_file($old) && @filemtime($old) < time() - 600) @unlink($old); } }
            $dsTmp = $dsTmpDir . DIRECTORY_SEPARATOR . 'seg_' . bin2hex(random_bytes(8)) . ($isHevc ? '.mp4' : '.ts');
            register_shutdown_function(function () use ($dsTmp) { if (is_file($dsTmp)) @unlink($dsTmp); });
            $cmdFile = preg_replace('/ pipe:1$/', ' -y ' . $this->escapeShellPath($dsTmp), $cmd, 1);
            $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $dgSpawn = microtime(true);
            $proc = @proc_open($cmdFile, [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pp);
            $rc = null; $sz = 0;
            if (is_resource($proc)) {
                @fclose($pp[0]);
                $rc = @proc_close($proc);
                clearstatcache(true, $dsTmp);
                $sz = is_file($dsTmp) ? (int)@filesize($dsTmp) : 0;
            }
            $dgFfEnd = microtime(true);
            // ★ (2026-10-13) 실패하면(못 띄움·빈 결과) 오류 응답 대신 아래 파이프 방식으로 다시 — 파이프도 실패하면 그쪽이 종전처럼 저장값을 지우고 오류 응답.
            // ★ (2026-10-13 재검토) 결과 파일이 있으면 ffmpeg 종료 코드와 상관없이 보냄 — 파이프도 종료 코드를 보지 않고 나온 만큼 보내므로 같은 결과를
            //   다시 만들 필요가 없다(종전: 오류 코드면 버리고 파이프로 다시). 종료 코드는 진단 기록(rc)에만.
            if ($rc === null) $dsFileFail = 'start';
            elseif ($sz <= 0) $dsFileFail = 'rc' . (int)$rc . '_sz0';
            if ($dsFileFail !== null && is_file($dsTmp)) @unlink($dsTmp);
        }
        if ($dsTmpDir !== null && $dsFileFail === null && $isHevc) {
            // ★ (2026-10-14) HEVC 파일 방식 — 파이프 방식(아래)과 같은 처리(fmp4Split 로 시작 시각 바로잡기 → 초기화 조각 또는 영상 조각)를 메모리 덩어리 대신
            //   임시 파일에서 읽어서 한다(메모리에 조각 전체를 올리지 않으므로 파이프 방식의 memory_limit 크기 제한도 해당 없음). 보낸 뒤 바로 지움.
            $fh = @fopen($dsTmp, 'rb');
            if (!$fh) { @unlink($dsTmp); $fail(500, 'segment_failed'); }
            $total = $sz; $BLK = 1024000;
            $get = function (int $o, int $n) use ($fh, $total): string {   // 위치·길이로 읽기
                if ($o < 0 || $n <= 0 || $o >= $total) return '';
                $n = min($n, $total - $o);
                if (@fseek($fh, $o) !== 0) return '';
                $out = '';
                while ($n > 0) { $b = fread($fh, min($n, 1048576)); if ($b === false || $b === '') break; $out .= $b; $n -= strlen($b); }
                return $out;
            };
            $parts = Mp4KeyIndex::fmp4Split($get, $total, 10.0);
            if ($parts === null) { fclose($fh); @unlink($dsTmp); $fail(500, 'segment_failed'); }
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: video/mp4');
            header('Cache-Control: no-store');
            ignore_user_abort(true);
            $dgW0 = microtime(true); $sent = 0; $abort = false;
            if ($ds === 'init') {
                header('Content-Length: ' . strlen($parts['init']));
                echo $parts['init']; $sent = strlen($parts['init']);
            } else {
                header('Content-Length: ' . $parts['fragLen']);
                foreach ($parts['parts'] as $pt) {
                    if ($pt[0] === 's') { echo $pt[1]; @flush(); $sent += strlen($pt[1]); continue; }
                    for ($p = $pt[1], $end = $pt[1] + $pt[2]; $p < $end; $p += $BLK) {
                        $buf = $get($p, min($BLK, $end - $p)); echo $buf; @flush(); $sent += strlen($buf);
                        if (connection_aborted()) { $abort = true; break 2; }
                    }
                }
            }
            $dgW1 = microtime(true);
            fclose($fh);
            @unlink($dsTmp);
            $dsAutoRes = $this->dsAutoRecord($useApcu, 'file', ($dgFfEnd - $dgSpawn) * 1000, $sz);
            if ($this->dsDiagAllowed && ($_COOKIE['fs_dsdiag'] ?? '') === '1') {
                $this->dsDiagWrite('ds_seg_srv', [
                    'mode' => 'file', 'codec' => 'hevc', 'ds' => $ds, 'i' => $i, 'ss' => round($ss, 3), 'len' => round($cut - $ss, 3),
                    'boot' => (int)round(($dgSpawn - $dgT0) * 1000), 'ffms' => (int)round(($dgFfEnd - $dgSpawn) * 1000), 'rc' => $rc,
                    'send' => (int)round(($dgW1 - $dgW0) * 1000), 'kb' => (int)round($sent / 1024), 'rawKb' => (int)round($sz / 1024), 'abort' => $abort,
                    'total' => (int)round(($dgW1 - $dgT0) * 1000), 'pick' => $dsPick !== '' ? $dsPick : 'auto',
                ]);
                if ($dsAutoRes !== null) $this->dsDiagWrite('ds_auto', $dsAutoRes);
            }
            exit;
        }
        if ($dsTmpDir !== null && $dsFileFail === null) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: video/mp2t');
            header('Cache-Control: no-store');
            header('X-Accel-Buffering: no');
            header('Content-Length: ' . $sz);
            ignore_user_abort(true);
            $dgW0 = microtime(true);
            $fh = @fopen($dsTmp, 'rb'); $sent = 0; $abort = false;
            if ($fh) {
                while (!feof($fh)) { $buf = fread($fh, 262144); if ($buf === false || $buf === '') break; echo $buf; @flush(); $sent += strlen($buf); if (connection_aborted()) { $abort = true; break; } }
                fclose($fh);
            }
            $dgW1 = microtime(true);
            @unlink($dsTmp);
            $dsAutoRes = $this->dsAutoRecord($useApcu, 'file', ($dgFfEnd - $dgSpawn) * 1000, $sz);   // ★ (2026-10-13 오전) 자동 선택용 잰 값(재는 중일 때만 저장)
            // ★ (2026-10-13) 정식 적용으로 평소에도 이 길을 타므로 기록은 진단이 켜졌을 때만(종전 시험용은 늘 진단 중이었음)
            if ($this->dsDiagAllowed && ($_COOKIE['fs_dsdiag'] ?? '') === '1') $this->dsDiagWrite('ds_seg_srv', [
                'mode' => 'file', 'i' => $i, 'ss' => round($ss, 3), 'len' => round($cut - $ss, 3),
                'boot' => (int)round(($dgSpawn - $dgT0) * 1000), 'ffms' => (int)round(($dgFfEnd - $dgSpawn) * 1000), 'rc' => $rc,
                'send' => (int)round(($dgW1 - $dgW0) * 1000), 'kb' => (int)round($sent / 1024), 'abort' => $abort,
                'kbps' => ($dgW1 - $dgW0) > 0.001 ? (int)round($sent * 8 / 1024 / ($dgW1 - $dgW0)) : null,
                'total' => (int)round(($dgW1 - $dgT0) * 1000),
                'ffHit' => $dgFfHit, 'spHit' => $dgSpHit, 'pick' => $dsPick !== '' ? $dsPick : 'auto',
            ]);
            if ($dsAutoRes !== null && $this->dsDiagAllowed && ($_COOKIE['fs_dsdiag'] ?? '') === '1') $this->dsDiagWrite('ds_auto', $dsAutoRes);
            exit;
        }

        $dgSpawn = microtime(true);                                                                                  //   ffmpeg 시작 직전
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) { if ($useApcu) { @apcu_delete($ffKey); if (isset($spKey)) @apcu_delete($spKey); } $fail(500, 'ffmpeg_start_failed'); }   // ★ (2026-10-12) 저장값 지움
        fclose($pipes[0]);
        ignore_user_abort(true);
        register_shutdown_function(function () use ($process, &$pipes) {
            if (is_resource($pipes[1] ?? null)) @fclose($pipes[1]);
            if (is_resource($process)) { @proc_terminate($process, 9); @proc_close($process); }
        });

        if ($isHevc) {
            // 조각 mp4 는 시작 시각을 바로잡아야 하므로 다 받은 뒤 보낸다(복사라 빠름).
            // ★ (2026-09-30) 크기 제한은 PHP memory_limit 에 맞춘다(한도의 60% − 지금 사용량, 최소 32MB) — 예전 고정 512MB 는 memory_limit 512M 과
            //   같아 제한에 닿기 전에 치명 오류가 났다. 넘으면 오류 응답(재생기는 빠른 시작 안전장치로 넘어감). 보낼 땐 복사본 없이 나눠서(아래).
            $ml = trim((string)ini_get('memory_limit'));
            if ($ml === '' || $ml === '-1') { $mlB = 1024 * 1048576; }                  // 제한 없음 — 1GB 로 본다
            else {
                $mlB = (int)$ml; $u = strtolower(substr($ml, -1));
                if ($u === 'g') $mlB *= 1073741824; elseif ($u === 'm') $mlB *= 1048576; elseif ($u === 'k') $mlB *= 1024;
            }
            $cap = max(8 * 1048576, (int)($mlB * 0.6) - memory_get_usage(true));   // 한도 비교는 '확보한 양'(true) 기준
            // ★ (2026-09-30) 한 문자열로 이어 붙이지 않고 일정한 크기의 덩어리로 모은다 — 이어 붙이면 늘릴 때 순간 두 벌이 필요해 제한에 닿기 전에
            //   치명 오류가 났다(실측). 덩어리 크기가 일정하므로 위치 → 덩어리 번호를 바로 계산한다.
            //   크기는 1,024,000 바이트 — 딱 1MB(1,048,576)는 머리 정보까지 PHP 할당 묶음(2MB)에 하나만 들어가 실제로 2배를 차지했다
            //   (1MB×40 → 78MB 확보, 1,024,000×40 → 38MB 확보 실측).
            $BLK = 1024000; $blocks = []; $cur = ''; $total = 0;
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], $BLK);
                if ($chunk === false || $chunk === '') break;
                $total += strlen($chunk);
                if ($total > $cap) { $blocks = []; $cur = ''; @fclose($pipes[1]); @proc_terminate($process, 9); @proc_close($process); $fail(500, 'segment_too_large'); }
                $cur .= $chunk;
                while (strlen($cur) >= $BLK) { $blocks[] = substr($cur, 0, $BLK); $cur = (string)substr($cur, $BLK); }
            }
            if ($cur !== '') $blocks[] = $cur;
            $cur = '';
            @fclose($pipes[1]); @proc_close($process);
            $dgHvEnd = microtime(true);
            // ★ (2026-10-14) HEVC 파이프 — 자동 선택용 잰 값(ffmpeg 시작 → 출력 끝, 다 받은 뒤 보내므로 회선 영향 없음)·파일 방식 실패 + 파이프 성공이면 환경 문제로 저장
            //   (H.264 파이프와 같은 규칙). 결과가 없으면 아래 fmp4Split 이 종전처럼 오류 응답.
            if ($total > 0) {
                if ($dsFileFail === null) $dsAutoRes = $this->dsAutoRecord($useApcu, 'pipe', ($dgHvEnd - $dgSpawn) * 1000, $total);
                elseif ($dsFileFail !== 'cached') {
                    if ($useApcu) @apcu_store('fs_ds_nofile|' . PHP_OS_FAMILY, $dsFileFail, 600);
                    $this->dsAutoForcePipe($useApcu, $dsFileFail);
                }
            }
            if ($this->dsDiagAllowed && ($_COOKIE['fs_dsdiag'] ?? '') === '1') {
                $dgHvRow = ['mode' => 'pipe', 'codec' => 'hevc', 'ds' => $ds, 'i' => $i, 'ss' => round($ss, 3), 'len' => round($cut - $ss, 3),
                    'boot' => (int)round(($dgSpawn - $dgT0) * 1000), 'pipeMs' => (int)round(($dgHvEnd - $dgSpawn) * 1000), 'rawKb' => (int)round($total / 1024),   // rawKb: ffmpeg 출력 크기(보낸 양 아님)
                   
                    'pick' => $dsPick !== '' ? $dsPick : 'auto'];
                if ($dsFileFail !== null) $dgHvRow['fileFail'] = $dsFileFail;
                $this->dsDiagWrite('ds_seg_srv', $dgHvRow);
                if ($dsAutoRes !== null) $this->dsDiagWrite('ds_auto', $dsAutoRes);
            }
            $get = function (int $o, int $n) use (&$blocks, $BLK, $total): string {   // 위치·길이로 읽기(덩어리 경계에 걸쳐도 이어 붙임)
                if ($o < 0 || $n <= 0 || $o >= $total) return '';
                $n = min($n, $total - $o); $out = '';
                while ($n > 0) {
                    $bi = intdiv($o, $BLK); $bo = $o % $BLK; $take = min($n, $BLK - $bo);
                    $out .= substr($blocks[$bi], $bo, $take); $o += $take; $n -= $take;
                }
                return $out;
            };
            $parts = Mp4KeyIndex::fmp4Split($get, $total, 10.0);
            if ($parts === null) $fail(500, 'segment_failed');
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: video/mp4');
            header('Cache-Control: no-store');
            if ($ds === 'init') {
                header('Content-Length: ' . strlen($parts['init']));
                echo $parts['init'];
                exit;
            }
            header('Content-Length: ' . $parts['fragLen']);
            foreach ($parts['parts'] as $pt) {
                if ($pt[0] === 's') { echo $pt[1]; @flush(); continue; }
                for ($p = $pt[1], $end = $pt[1] + $pt[2]; $p < $end; $p += $BLK) {   // 영상 자료는 덩어리에서 1MB 씩 꺼내 보냄
                    echo $get($p, min($BLK, $end - $p)); @flush();
                    if (connection_aborted()) exit;
                }
            }
            exit;
        }

        $first = fread($pipes[1], 65536);
        $dgFirst = microtime(true);                                      // ★ (2026-10-12) 진단 — ffmpeg 첫 출력(64KB 까지)
        if ($first === false || $first === '') {                          // 아무것도 안 나오면 빈 영상 대신 오류 응답
            @fclose($pipes[1]); @proc_terminate($process, 9); @proc_close($process);
            if ($useApcu) { @apcu_delete($ffKey); if (isset($spKey)) @apcu_delete($spKey); }   // ★ (2026-10-12) 저장값이 원인일 수 있으니 지워 다음 요청은 다시 확인
            $fail(500, 'segment_failed');
        }
        // ★ (2026-10-13 재검토) 파일 방식은 실패했는데 파이프로는 나왔다 = 서버 환경 문제(임시 폴더 권한·셸 등) → 10분간 바로 파이프.
        //   종전엔 파일 방식이 실패하기만 하면 저장해서, 영상 파일 자체 문제(뒤가 잘린 mp4 등 — 파이프도 실패)로도 다른 정상 영상까지 10분간 파이프로 갔다(재검토 실측).
        if ($dsFileFail !== null && $dsFileFail !== 'cached' && $useApcu) @apcu_store('fs_ds_nofile|' . PHP_OS_FAMILY, $dsFileFail, 600);
        if ($dsFileFail !== null && $dsFileFail !== 'cached') $this->dsAutoForcePipe($useApcu, $dsFileFail);   // ★ (2026-10-13 오전) 자동 선택도 1시간 파이프로
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: video/mp2t');
        header('Cache-Control: no-store');
        header('X-Accel-Buffering: no');
        // ★ (2026-10-13) 진단 — 보내는 시간을 'ffmpeg 출력 기다림(rd)'과 '브라우저로 내보내기 기다림(wr — echo+flush, 보낼 곳이 막히면 여기서 멈춤)'으로 나눠 잰다
        //   (펜닐: 회선 1Gbps·일반 다운로드 18~25MB/s 인데 조각은 약 12Mbps). 읽기 횟수·최대 한 번 기다림도. 재는 것만 — 보내는 데이터·순서 그대로.
        $dgW0 = microtime(true); echo $first; @flush(); $dgWr = microtime(true) - $dgW0; $dgRd = 0.0; $dgN = 1; $dgRdMax = 0.0; $dgWrMax = $dgWr;
        $dgBytes = strlen($first); $dgAbort = false;
        while (!feof($pipes[1])) {
            $dgR0 = microtime(true);
            $chunk = fread($pipes[1], 65536);
            $dgRt = microtime(true) - $dgR0; $dgRd += $dgRt; if ($dgRt > $dgRdMax) $dgRdMax = $dgRt;
            if ($chunk === false || $chunk === '') break;
            $dgW0 = microtime(true);
            echo $chunk; @flush();
            $dgWt = microtime(true) - $dgW0; $dgWr += $dgWt; if ($dgWt > $dgWrMax) $dgWrMax = $dgWt; $dgN++;
            $dgBytes += strlen($chunk);
            if (connection_aborted()) { $dgAbort = true; break; }       // 탐색 등으로 재생기가 취소
        }
        @fclose($pipes[1]);
        @proc_terminate($process, 9);
        @proc_close($process);
        // ★ (2026-10-13 오전) 자동 선택용 잰 값 — ffmpeg 시작 → 첫 출력 + 출력 기다림 합(보내는 시간 wr 은 뺌), 끝까지 받은 H.264 조각만(재는 중일 때만 저장)
        // ★ (2026-10-13 재검토) 보내는 시간(wr)이 출력 기다림(rd)보다 긴 조각은 뺌 — 보는 쪽 회선이 느리면(또는 일부러 천천히 받으면) 보내는 동안 ffmpeg 가
        //   출력을 미리 채워 기다림이 거의 0 이 되어 파이프가 실제보다 빨라 보였다(실측: 보통 회선 rd 10~22ms·80~108ms/MB → 느린 회선 rd 2ms·wr 3.9초·64ms/MB).
        //   그러면 서버 속도와 상관없이 파이프로 정해질 수 있었다. 파일 방식 잰 값(ffmpeg 시작~끝)은 회선과 무관.
        if (!$isHevc && $ds === 'segment' && !$dgAbort && $dsFileFail === null && $dgWr <= $dgRd) $dsAutoRes = $this->dsAutoRecord($useApcu, 'pipe', ($dgFirst - $dgSpawn + $dgRd) * 1000, $dgBytes);
        // ★ (2026-10-12) 진단 기록 — 브라우저 진단이 켜졌을 때(쿠키 fs_dsdiag=1, app.js)만 조각마다 한 줄을 같은 진단 로그(data/hls_diag.log)에.
        //   펜닐: mp4(원본 스트리밍) 탐색이 조각당 약 2초 — 서버 처리인지 전송인지 가르려고. 기록만(보낸 데이터·순서 그대로), 실패해도 무시.
        //   boot: 요청 도착 → ffmpeg 시작 직전(PHP 시작·로그인·목차 캐시), first: ffmpeg 시작 → 첫 출력, send: 첫 출력 → 다 보냄(ffmpeg 읽기 + 전송,
        //   보낼 곳이 막히면 여기서 기다림), len: 조각 길이(초), kb: 보낸 양, abort: 재생기가 중간에 취소.
        //   ★ (2026-10-13) send 세부 — rd: ffmpeg 출력 기다림 합(rdMax 한 번 최대), wr: 내보내기(echo+flush) 기다림 합(wrMax), n: 64KB 읽기 횟수,
        //   kbps: 보낸 양 ÷ send. rd 가 크면 ffmpeg·디스크, wr 가 크면 PHP→Apache→네트워크 쪽. env: 서버 출력 환경(가끔 — 첫 조각·20번째마다).
        // ★ (2026-10-12) 탐색기 경로(로그인 필요 — directStream)에서만. 공유(ShareManager::downloadShare)도 이 함수를 쓰는데, 로그인 안 한 공유 방문자가
        //   쿠키를 직접 넣어 서버 파일 쓰기(진단 로그 — 5MB 넘으면 비움)를 일으킬 수 있었다(재검토에서 발견).
        if ($this->dsDiagAllowed && ($_COOKIE['fs_dsdiag'] ?? '') === '1') {
            $dgEnd = microtime(true);
            $dgSend = $dgEnd - $dgFirst;
            $dgRow = [
                'mode' => 'pipe', 'i' => $i, 'ss' => round($ss, 3), 'len' => round($cut - $ss, 3),
                'boot' => (int)round(($dgSpawn - $dgT0) * 1000), 'first' => (int)round(($dgFirst - $dgSpawn) * 1000),
                // ★ (2026-10-12) boot 세부(ms) — pre: PHP 시작·로그인·권한, idx: 키프레임 목록(idxHit 메모리), ff: ffmpeg 위치(ffHit 메모리), sp: 짧은 경로(spHit 메모리)
                'pre' => (int)round(($dgIn - $dgT0) * 1000), 'idx' => (int)round(($dgIdx - $dgIn) * 1000), 'idxHit' => $dgIdxHit,
                'ff' => (int)round(($dgFf1 - $dgFf0) * 1000), 'ffHit' => $dgFfHit, 'sp' => (int)round(($dgSp - $dgFf1) * 1000), 'spHit' => $dgSpHit,
                'send' => (int)round($dgSend * 1000), 'kb' => (int)round($dgBytes / 1024), 'abort' => $dgAbort,
                'rd' => (int)round($dgRd * 1000), 'rdMax' => (int)round($dgRdMax * 1000), 'wr' => (int)round($dgWr * 1000), 'wrMax' => (int)round($dgWrMax * 1000), 'n' => $dgN,
                'kbps' => $dgSend > 0.001 ? (int)round($dgBytes * 8 / 1024 / $dgSend) : null,
            ];
            if ($i === 0 || $i % 20 === 0) $dgRow['env'] = $this->dsDiagEnv();
            if ($dsFileFail !== null) $dgRow['fileFail'] = $dsFileFail;   // ★ (2026-10-13) 파일 방식이 안 돼 파이프로 보낸 경우 그 까닭
            $dgRow['pick'] = $dsPick !== '' ? $dsPick : 'auto';
            $this->dsDiagWrite('ds_seg_srv', $dgRow);
            if ($dsAutoRes !== null) $this->dsDiagWrite('ds_auto', $dsAutoRes);
        }
        exit;
    }

    public function videoFrameFile(string $fullPath, float $t, bool $accurate = false): void {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!in_array($ext, self::FRAME_VIDEO_EXTS, true) || !is_file($fullPath)) { header('X-Frame-Error: ext'); http_response_code(404); exit; }
        if (!is_finite($t) || $t < 0) $t = 0;
        $sec = (int)floor($t);   // ★ (2026-10-02) 1초 단위(간격은 화면이 영상 길이에 맞춰 정함 — 예전 10초 고정)
        if ($sec > 360000) { http_response_code(404); exit; }   // 100시간 넘는 값은 받지 않음

        // ★ (2026-10-02) 캐시 위치·장면 결정 규칙(키프레임 맞춤·짧은 영상 정확 탐색·캐시 이름)·작업 자리는 묶음 요청(videoFramesFile)과 같은 함수를 쓴다
        //   — 같은 시각은 어느 경로로 만들어도 같은 캐시 파일(frameCacheDir·frameTarget·frameSlot).
        $cacheDir = $this->frameCacheDir();
        if ($cacheDir === null) { header('X-Frame-Error: nocache'); http_response_code(404); exit; }
        $tg = $this->frameTarget($fullPath, $ext, $sec, $accurate, $cacheDir);
        $cachePath = $tg['cache'];
        if (is_file($cachePath)) $this->sendThumbnail($cachePath);   // sendThumbnail 은 보낸 뒤 exit(0바이트면 지우고 404)
        $failMarker = $tg['fail'];
        if (is_file($failMarker) && (time() - @filemtime($failMarker)) < 86400) { header('X-Frame-Error: failmark'); http_response_code(404); exit; }

        $ffmpeg = (mcv_ffmpeg() ?: null);
        if (!$ffmpeg) { header('X-Frame-Error: noffmpeg'); http_response_code(404); exit; }
        $slot = $this->frameSlot($cacheDir);   // 동시 작업 최대 2개(frameSlot 설명)
        if ($slot === null) { header('Retry-After: 1'); header('X-Frame-Error: busy'); http_response_code(503); exit; }
        $src = $fullPath;
        if (PHP_OS_FAMILY === 'Windows') { $short = $this->getWindowsShortPath($fullPath); if ($short) $src = $short; }   // 한글 경로
        $tmpPath = $cachePath . '.' . getmypid() . '.' . mt_rand(1000, 9999) . '.tmp.jpg';
        $cmd = escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -nostdin' . self::frameInputArgs($tg) . ' -i ' . $this->escapeShellPath($src)
             . ' -frames:v 1 -vf "scale=160:-2:flags=fast_bilinear" -q:v 5 ' . $this->escapeShellPath($tmpPath) . ' -y 2>&1';
        $out = [];
        @exec($cmd, $out, $ret);
        if ($ret !== 0 || !is_file($tmpPath) || (int)@filesize($tmpPath) < 200) {
            @unlink($tmpPath);
            @file_put_contents($failMarker, date('Y-m-d H:i:s') . ' ' . implode(' ', array_slice($out, -2)));
            header('X-Frame-Error: ffmpeg ret=' . (int)$ret . ' ' . self::frameErrMsg($out, [$fullPath, $src, $tmpPath]));   // ffmpeg 마지막 오류 줄(경로 지움·ASCII 만)
            http_response_code(404); exit;
        }
        if (!@rename($tmpPath, $cachePath)) {   // 같은 시각을 동시에 만든 다른 요청이 먼저 저장했으면(Windows 는 덮어쓰기 실패) 그쪽을 쓴다
            if (!is_file($cachePath)) @copy($tmpPath, $cachePath);
            @unlink($tmpPath);                  // 임시 파일은 어느 경우든 지운다(보낸 뒤 exit 하므로 먼저)
        }
        $this->sendThumbnail($cachePath);       // 그래도 없으면 sendThumbnail 이 404
    }

    /** ★ (2026-10-02) 재생바 미리보기가 받는 동영상 확장자(한 장·묶음 공용) */

    private const FRAME_VIDEO_EXTS = ['mp4', 'm4v', 'mkv', 'webm', 'mov', 'avi', 'wmv', 'flv', 'ts', 'm2ts', 'mts', 'mpg', 'mpeg', '3gp', 'ogv'];


    /** ★ (2026-10-02) 재생바 미리보기 장면 캐시 폴더(data/thumbcache/seek) — 없으면 만들고, 못 쓰면 null */
    private function frameCacheDir(): ?string {
        // ★ myComix: cache/_vframes(FileStation data/thumbcache/seek 자리 — 지워져도 다시 만듦, cache 는 .htaccess 로 막힘 · fix147 — 종전 src/video/vframes 는 mcv_frames_gc 가 정리)
        $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '_vframes';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        return (is_dir($cacheDir) && is_writable($cacheDir)) ? $cacheDir : null;
    }


    /**
     * ★ (2026-10-02) 재생바 미리보기 장면 결정 규칙(한 장·묶음 공용 — 같은 시각은 같은 캐시 파일). 반환 ['cache','fail','ss','acc'].
     *   키프레임 맞춤(펜닐 승인) — 장면은 '그 시각 바로 앞 키프레임'이라 키프레임 간격보다 촘촘한 요청은 같은 장면을 따로 만들고 따로 캐시했다.
     *   mp4 계열이고 APCu 가 있으면 빠른 시작과 **같은 키·같은 값**의 키프레임 목록(Mp4KeyIndex, 1시간)으로 바로 앞 키프레임 시각을 찾아 그 시각으로
     *   캐시(보이는 장면은 그대로). APCu 없음·mp4 아님·목록 못 읽음이면 1초 단위(요청마다 목차를 읽지 않게 — 목차는 수 MB~수십 MB).
     *   짧은 영상 정확 탐색(펜닐 제보: 8초 영상 — 키프레임이 맨 앞 하나뿐이라 어디를 가리켜도 첫 장면): 화면이 10분 이하 영상에 a=1 을 붙이면
     *   키프레임이 1초 이내일 때만 그 키프레임 장면, 아니면 정확 탐색(키프레임부터 그 시각까지 푼다), 캐시는 'a초' 로 따로.
     */
    private function frameTarget(string $fullPath, string $ext, int $sec, bool $accurate, string $cacheDir): array {
        $seekT = (float)$sec; $tag = (string)$sec; $snapped = false;
        if (in_array($ext, ['mp4', 'm4v', 'mov'], true) && function_exists('apcu_fetch') && (!function_exists('apcu_enabled') || @apcu_enabled())) {
            $st = @stat($fullPath);
            $ik = 'fs_ds_' . md5($fullPath . '|' . ($st['size'] ?? 0) . '|' . ($st['mtime'] ?? 0) . '|v2');   // directStreamFile 과 같은 키
            $hit = false; $idx = @apcu_fetch($ik, $hit);
            if (!$hit || !is_array($idx)) {
                require_once __DIR__ . '/video_mp4idx.php';
                $idx = Mp4KeyIndex::read($fullPath);
                if (is_array($idx)) @apcu_store($ik, $idx, 3600);   // directStreamFile 과 같은 값·같은 보관 시간
            }
            $kp = (is_array($idx) && !empty($idx['keyPts']) && is_array($idx['keyPts'])) ? $idx['keyPts'] : null;
            if ($kp) {
                $lo = 0; $hi = count($kp) - 1; $best = -1;
                while ($lo <= $hi) { $mid = ($lo + $hi) >> 1; if ($kp[$mid] <= $sec + 0.001) { $best = $mid; $lo = $mid + 1; } else { $hi = $mid - 1; } }
                if ($best < 0) $best = 0;
                $keyT = (float)$kp[$best];
                if (is_finite($keyT) && $keyT >= 0 && (!$accurate || ($sec - $keyT) <= 1.0)) { $seekT = $keyT; $tag = 'k' . sprintf('%.3f', $keyT); $snapped = true; }
            }
        }
        $acc = $accurate && !$snapped;
        if ($acc) $tag = 'a' . $sec;
        $cachePath = $cacheDir . DIRECTORY_SEPARATOR . md5($fullPath . '|' . @filemtime($fullPath) . '|' . @filesize($fullPath) . '|' . $tag . '|160k') . '.jpg';   // k: 키프레임 방식, $tag: 초·맞춘 키프레임 시각·a초
        // 키프레임 시각에 맞췄으면 그 키프레임이 확실히 잡히게 0.01초 뒤(-noaccurate_seek 는 그 시각 이하 키프레임으로 감 — 반올림으로 바로 앞 것이 잡히지 않게)
        return ['cache' => $cachePath, 'fail' => $cachePath . '.fail', 'ss' => $snapped ? sprintf('%.3f', $seekT + 0.01) : (string)$sec, 'acc' => $acc];
    }


    /**
     * ★ (2026-10-02) ffmpeg 입력 앞 옵션(한 장·묶음 공용). 키프레임만(-noaccurate_seek -skip_frame nokey): 그 시각 바로 앞 키프레임 화면 —
     *   정확 탐색은 키프레임부터 그 시각까지 풀어야 해 키프레임 간격 10초 1080p 실측 H.264 0.67초 · HEVC 10비트 2.34초 → 0.08초 · 0.14초(1코어).
     *   정확 탐색(acc)이면 키프레임 전용 옵션을 뺀다.
     */
    private static function frameInputArgs(array $tg): string {
        return ($tg['acc'] ? '' : ' -noaccurate_seek -skip_frame nokey') . ' -ss ' . $tg['ss'] . ' -analyzeduration 1000000 -probesize 1000000 -an -sn -dn';
    }


    /**
     * ★ (2026-10-02) 동시 작업 최대 2개(한 장·묶음 공용 — 묶음 하나 = 자리 하나). 마우스를 천천히 움직이거나 여러 탭·사용자가 동시에 요청해도
     *   ffmpeg 가 쌓이지 않게(트랜스코딩과 CPU 를 나눠 쓰므로). 자리가 없으면 null(호출한 쪽이 503). 잠금은 요청이 끝나면(exit) 풀린다.
     */
    private function frameSlot(string $cacheDir) {
        for ($i = 0; $i < 2; $i++) {
            $fh = @fopen($cacheDir . DIRECTORY_SEPARATOR . '.slot' . $i, 'c');
            if ($fh && @flock($fh, LOCK_EX | LOCK_NB)) return $fh;
            if ($fh) @fclose($fh);
        }
        return null;
    }


    /**
     * ★ (2026-10-09) 키프레임 시각 목록 — 공용(검증된 경로만: 탐색기 videoKeyTimes / 공유 downloadShare 'vk').
     *   mp4·m4v·mov 만 — 목차(moov)만 읽는 Mp4KeyIndex 결과를 장면 캐시(frameTarget)·원본 스트리밍(directStreamFile)과 **같은 APCu 키·같은 값**으로
     *   함께 쓴다(영상 데이터는 읽지 않음, ffmpeg 없음). 결과 {ok:true, d: 길이(초), keys: [키프레임 표시 시각(초, 소수 3자리)…]}.
     *   다른 형식·읽기 실패·APCu 없음과 무관하게 실패면 {ok:false} — 화면은 종전 간격(1·2·5·10초) 방식 그대로. 5만 개가 넘으면 보내지 않음.
     */
    public function videoKeyTimesFile(string $fullPath): void {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'm4v', 'mov'], true) || !is_file($fullPath)) { self::framesJson(['ok' => false, 'why' => 'ext']); }
        $useApcu = function_exists('apcu_fetch') && (!function_exists('apcu_enabled') || @apcu_enabled());
        $st = @stat($fullPath);
        $ik = 'fs_ds_' . md5($fullPath . '|' . ($st['size'] ?? 0) . '|' . ($st['mtime'] ?? 0) . '|v2');   // frameTarget·directStreamFile 과 같은 키
        $idx = null;
        if ($useApcu) { $hit = false; $c = @apcu_fetch($ik, $hit); if ($hit && is_array($c)) $idx = $c; }
        if ($idx === null) {
            require_once __DIR__ . '/video_mp4idx.php';
            $idx = Mp4KeyIndex::read($fullPath);
            if (is_array($idx) && $useApcu) @apcu_store($ik, $idx, 3600);   // 같은 보관 시간
        }
        $kp = (is_array($idx) && !empty($idx['keyPts']) && is_array($idx['keyPts'])) ? $idx['keyPts'] : null;
        if (!$kp || count($kp) > 50000) { self::framesJson(['ok' => false, 'why' => $kp ? 'many' : 'noindex']); }
        $keys = [];
        foreach ($kp as $k) { $k = (float)$k; if (is_finite($k) && $k >= 0) $keys[] = round($k, 3); }
        $d = (is_array($idx) && isset($idx['duration']) && is_finite((float)$idx['duration'])) ? round((float)$idx['duration'], 3) : 0;
        self::framesJson(['ok' => true, 'd' => $d, 'keys' => $keys]);
    }


    /**
     * ★ (2026-10-02) 재생바 미리보기 장면 묶음 — 공용(검증된 경로만: 탐색기 videoFrames / 공유 downloadShare 'vfs').
     *   ts: 쉼표로 나눈 초 목록(최대 20개, 0~100시간). 캐시에 없는 장면만 **ffmpeg 1번**(입력마다 그 시각으로 이동 · 출력마다 한 장)으로 만들어
     *   한 장 요청과 **같은 캐시 파일**에 저장(frameTarget). 결과 JSON {ok, fail, fallback, left, made, ms}:
     *     ok       — 캐시에 있음(이제 한 장 요청이 캐시에서 바로)  · fail — 실패 표시(24시간)
     *     fallback — ffmpeg 가 통째로 실패(입력 하나라도 못 열면 전체 실패) → 실패로 표시하지 않고 화면이 한 장씩 다시(장면별로 정확히 처리)
     *     left     — Windows 명령 줄 길이 제한(약 8,000자) 때문에 이번에 못 넣은 시각 → 화면이 다시 요청 · busy(503) — 작업 자리 없음
     *   실측(10분 1080p, 키프레임 10초, 20장): ffmpeg 20번 1.44초 → 1번 0.90초, 그림은 20장 모두 바이트까지 같음.
     */
    public function videoFramesFile(string $fullPath, string $ts, bool $accurate = false): void {
        $t0 = microtime(true);
        $res = ['ok' => [], 'fail' => [], 'fallback' => [], 'left' => [], 'made' => 0];
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!in_array($ext, self::FRAME_VIDEO_EXTS, true) || !is_file($fullPath)) { self::framesJson(['error' => 'ext'], 404); }
        $secs = [];
        foreach (explode(',', $ts) as $p) {
            $p = trim($p);
            if ($p === '' || !is_numeric($p)) continue;
            $f = (float)$p;
            if (!is_finite($f) || $f < 0 || $f > 360000) continue;
            $secs[(int)floor($f)] = true;
            if (count($secs) >= 20) break;
        }
        $secs = array_keys($secs);
        $cacheDir = $this->frameCacheDir();
        if ($cacheDir === null) { self::framesJson(['error' => 'nocache'], 404); }
        $groups = [];   // 같은 캐시 파일(키프레임 맞춤으로 합쳐진 시각들)은 한 번만 만든다
        foreach ($secs as $sec) {
            $tg = $this->frameTarget($fullPath, $ext, $sec, $accurate, $cacheDir);
            if (is_file($tg['cache']) && (int)@filesize($tg['cache']) > 0) { $res['ok'][] = $sec; continue; }
            if (is_file($tg['fail']) && (time() - @filemtime($tg['fail'])) < 86400) { $res['fail'][] = $sec; continue; }
            if (!isset($groups[$tg['cache']])) $groups[$tg['cache']] = ['tg' => $tg, 'secs' => []];
            $groups[$tg['cache']]['secs'][] = $sec;
        }
        if (!$groups) { $res['ms'] = (int)round((microtime(true) - $t0) * 1000); self::framesJson($res); }
        $ffmpeg = (mcv_ffmpeg() ?: null);
        if (!$ffmpeg) { foreach ($groups as $g) foreach ($g['secs'] as $s) $res['fail'][] = $s; $res['error'] = 'noffmpeg'; self::framesJson($res); }
        $slot = $this->frameSlot($cacheDir);
        if ($slot === null) {
            foreach ($groups as $g) foreach ($g['secs'] as $s) $res['left'][] = $s;
            $res['busy'] = true; header('Retry-After: 1'); self::framesJson($res, 503);
        }
        $src = $fullPath;
        if (PHP_OS_FAMILY === 'Windows') { $short = $this->getWindowsShortPath($fullPath); if ($short) $src = $short; }   // 한글 경로
        // ★ (2026-10-02) 메모리·스레드·시간 제한(재검토 실측, 펜닐 승인) — 입력 20개를 ffmpeg 하나에 넣으면 디코더가 입력마다 생겨
        //   HEVC 10비트 1080p 20장: 최대 메모리 1,210MB(한 장 110MB)·코어 8개 가정 2,027MB·스레드 201개 → 4K 면 수 GB(작업 자리 2개면 두 배).
        //   ①ffmpeg 한 번에 넣는 입력 수를 화소로 제한(1080p 4장분 — 1080p 이하 4·1440p 2·4K 1, 크기 모르면 2)하고 요청 안에서 차례로 실행
        //   ②입력마다 -threads 1(목록 썸네일과 같음 — 코어가 많은 서버에서 스레드 폭증 방지) ③약 10초가 지나면 나머지는 left
        //   (Windows PHP 의 max_execution_time 은 실제 경과 시간 — 도중에 끝나 임시 파일이 남지 않게). 시간 제한은 120초로 다시 잡는다(무제한이면 그대로).
        if ((int)ini_get('max_execution_time') > 0) @set_time_limit(120);
        $dims = $this->frameDims($fullPath, $src, $ffmpeg);
        $perProc = $dims ? max(1, min(4, (int)floor(4 * 1920 * 1080 / max(1, $dims[0] * $dims[1])))) : 2;
        $res['per'] = $perProc;
        $head = escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -nostdin';
        $srcArg = $this->escapeShellPath($src);
        $deadline = $t0 + 10.0;
        $list = array_values($groups);
        for ($gi = 0; $gi < count($list); ) {
            if ($gi > 0 && microtime(true) > $deadline) {   // 시간 상한 — 나머지는 다음 요청에서
                for (; $gi < count($list); $gi++) foreach ($list[$gi]['secs'] as $s) $res['left'][] = $s;
                break;
            }
            $inputs = ''; $outputs = ''; $used = []; $n = 0;
            while ($gi < count($list) && $n < $perProc) {
                $g = $list[$gi];
                $tmp = $cacheDir . DIRECTORY_SEPARATOR . 'b' . getmypid() . '_' . mt_rand(1000, 9999) . '_' . $n . '.tmp.jpg';
                $in = ' -threads 1' . self::frameInputArgs($g['tg']) . ' -i ' . $srcArg;
                $o = ' -map ' . $n . ':v:0 -frames:v 1 -vf "scale=160:-2:flags=fast_bilinear" -q:v 5 ' . $this->escapeShellPath($tmp);
                // Windows 명령 줄 한도(cmd.exe 8,191자) 아래로 — 넘으면 이번 실행엔 넣지 않고 다음 실행으로(한 실행의 첫 장은 항상 넣음)
                if ($n > 0 && strlen($head) + strlen($inputs) + strlen($in) + strlen($outputs) + strlen($o) + 16 > 7000) break;
                $inputs .= $in; $outputs .= $o; $used[] = ['g' => $g, 'tmp' => $tmp]; $n++; $gi++;
            }
            $out = [];
            @exec($head . $inputs . $outputs . ' -y 2>&1', $out, $ret);
            if ($ret !== 0) {   // 이 실행만 통째로 실패 — 실패 표시 없이 한 장씩(장면별로 정확히)
                foreach ($used as $u) { @unlink($u['tmp']); foreach ($u['g']['secs'] as $s) $res['fallback'][] = $s; }
                if (!isset($res['err'])) $res['err'] = 'ffmpeg ret=' . (int)$ret . ' ' . self::frameErrMsg($out, [$fullPath, $src, $cacheDir]);
                continue;
            }
            foreach ($used as $u) {
                $cp = $u['g']['tg']['cache'];
                if (is_file($u['tmp']) && (int)@filesize($u['tmp']) >= 200) {
                    if (!@rename($u['tmp'], $cp)) { if (!is_file($cp)) @copy($u['tmp'], $cp); @unlink($u['tmp']); }
                    foreach ($u['g']['secs'] as $s) $res['ok'][] = $s;
                    $res['made']++;
                } else {
                    @unlink($u['tmp']);
                    @file_put_contents($u['g']['tg']['fail'], date('Y-m-d H:i:s') . ' batch: no frame');   // 영상 끝을 넘은 시각 등 — 한 장과 같게 24시간
                    foreach ($u['g']['secs'] as $s) $res['fail'][] = $s;
                }
            }
        }
        $res['ms'] = (int)round((microtime(true) - $t0) * 1000);
        self::framesJson($res);
    }


    /**
     * ★ (2026-10-02) 영상 크기 [가로, 세로] — 묶음에서 ffmpeg 한 번에 넣을 입력 수를 정하려고(메모리). 영상 정보 확인(getMediaInfo)과 같은 'ffmpeg -i'
     *   출력에서 Video 줄의 가로x세로만 읽는다. APCu 가 있으면 1시간 보관(키: 경로|크기|수정시각). 못 읽으면 null(호출한 쪽이 2장으로).
     */
    private function frameDims(string $fullPath, string $src, string $ffmpeg): ?array {
        $useApcu = function_exists('apcu_fetch') && (!function_exists('apcu_enabled') || @apcu_enabled());
        $st = @stat($fullPath);
        $ck = 'fs_vfdim_' . md5($fullPath . '|' . ($st['size'] ?? 0) . '|' . ($st['mtime'] ?? 0));
        if ($useApcu) { $hit = false; $v = @apcu_fetch($ck, $hit); if ($hit && is_array($v) && count($v) === 2) return $v; }
        $out = [];
        @exec(escapeshellarg($ffmpeg) . ' -hide_banner -nostdin -i ' . $this->escapeShellPath($src) . ' 2>&1', $out);
        $dims = null;
        foreach ($out as $line) {
            if (stripos($line, 'Video:') !== false && preg_match('/Video:.*?\b(\d{2,5})x(\d{2,5})\b/', $line, $m)) { $dims = [(int)$m[1], (int)$m[2]]; break; }
        }
        if ($dims && ($dims[0] < 16 || $dims[1] < 16)) $dims = null;
        if ($useApcu && $dims) @apcu_store($ck, $dims, 3600);
        return $dims;
    }


    /** ★ (2026-10-02) 묶음 응답(JSON) 보내고 끝 */
    private static function framesJson(array $res, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($res);
        exit;
    }


    /**
     * ★ (2026-10-02) 진단 사유 헤더용 ffmpeg 오류 문장 — 서버 경로를 지운다(펜닐 승인). 공유 링크는 로그인 없이 볼 수 있는데, 예전 ffmpeg(4.x~5.x)는
     *   '파일경로: Invalid data found …' 처럼 마지막 줄에 경로를 찍어 응답 헤더로 서버 경로가 보일 수 있었다(이 환경 최신판은 경로 없음 — 재현 안 됨).
     *   ①넘겨받은 경로(원본·짧은 경로·임시 파일 — \ / 바꾼 꼴 포함) → <file> ②남은 Windows(C:\… C:/…)·유닉스(/a/b…) 경로 모양 → <path>
     *   ③그다음 ASCII 외 문자 ? (한글 경로는 ①②에서 먼저 지워짐), 120자. 오류 종류(Invalid data found 등)는 남아 진단엔 지장 없다.
     */
    private static function frameErrMsg(array $out, array $paths): string {
        $msg = (string)implode(' ', array_slice($out, -1));
        foreach ($paths as $pp) {
            $pp = (string)$pp;
            if ($pp === '') continue;
            $msg = str_replace([$pp, str_replace('\\', '/', $pp), str_replace('/', '\\', $pp)], '<file>', $msg);
        }
        $msg = preg_replace('~[A-Za-z]:[\\\\/][^\s:\'"]*~u', '<path>', $msg) ?? $msg;              // Windows 경로
        $msg = preg_replace('~(?<![\w<])/[^\s:\'"/]+/[^\s:\'"]*~u', '<path>', $msg) ?? $msg;       // 유닉스 경로(슬래시 2개 이상)
        $msg = preg_replace('/[^\x20-\x7E]/', '?', $msg) ?? '';                                     // 줄바꿈 등 제어 문자·ASCII 외 → ?
        return substr($msg, 0, 120);
    }


    /**
     * ★ (2026-11-02 10:28, 42차 점검) 썸네일·재생바 장면·음악 커버 응답의 Cache-Control — 화면 주소에 버전 값 `v=<수정시각>-<크기>`(원본 파일의 초 단위 mtime 과
     *   바이트 크기, 예: v=1730512345-10485760)가 있으면 $longPolicy(종전 정책 그대로), 없거나 옛 형식(v=mtime 만)이면 'private, max-age=0, must-revalidate'
     *   (캐시는 두되 매번 ETag 로 재검증 → 바뀌지 않았으면 304). 종전엔 주소에 버전이 없는데 하루~30일 캐시라, 같은 이름으로 다시 올리기·EXIF 지우기·
     *   편집 저장·덮어쓰기·표지 바꾸기 뒤에도 브라우저가 서버에 묻지 않고 옛 그림을 썼음. 주소에 v 를 붙이는 것은 화면(app.js·share.php) 몫
     */
    private static function versionedCacheControl(string $longPolicy): string {
        $v = $_GET['v'] ?? null;
        return (is_string($v) && preg_match('/^\d+-\d+$/', $v)) ? $longPolicy : 'private, max-age=0, must-revalidate';
    }


    private function sendThumbnail(string $cachePath): void {
        // 0바이트 파일 방어 (디스크 가득 참 등으로 쓰기 실패한 경우)
        $fsize = @filesize($cachePath);
        if ($fsize === false || $fsize === 0) {
            @unlink($cachePath);  // 손상된 캐시 삭제
            http_response_code(404);
            exit;
        }
        // 출력 버퍼링 완전 비활성화
        while (ob_get_level()) {
            ob_end_clean();
        }
        // ★ (2026-11-02 10:28, 42차 점검) ETag(캐시 파일 이름 = 원본 경로·수정시각(·크기) 키 + 캐시 파일 시각·크기)와 If-None-Match 304, 캐시 정책은 versionedCacheControl
        //   — 종전엔 검증자 없이 'public, max-age=86400' 이라 원본을 바꿔도 하루 동안 옛 썸네일·장면이 나왔음
        $tnEtag = '"' . substr(md5(basename($cachePath) . '|' . (int)@filemtime($cachePath) . '|' . $fsize), 0, 24) . '"';
        $tnCc = self::versionedCacheControl('public, max-age=86400');
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            foreach (explode(',', (string)$_SERVER['HTTP_IF_NONE_MATCH']) as $tnInm) {
                $tnInm = trim($tnInm);
                if (strncmp($tnInm, 'W/', 2) === 0) $tnInm = substr($tnInm, 2);
                if ($tnInm === $tnEtag) {
                    http_response_code(304);
                    header('ETag: ' . $tnEtag);
                    header('Cache-Control: ' . $tnCc);
                    exit;
                }
            }
        }
        // 기존 헤더 제거 후 이미지 헤더 설정
        header_remove('Content-Security-Policy');
        header('Content-Type: image/jpeg');
        header('Cache-Control: ' . $tnCc);
        header('ETag: ' . $tnEtag);
        header('Content-Length: ' . $fsize);
        readfile($cachePath);
        exit;
    }

    /**
     * 공유 스트리밍 HLS 모드
     * share.php에서 토큰 인증 후 호출됨 (로그인 불필요)
     * hls_action: start(세션생성), playlist(m3u8서빙), segment(ts서빙), stop(정리)
     */
    /**
     * ★ (2026-11-01 11:42, 39차 점검) 공유 HLS 세션이 이 공유의 것인지 — meta.json 의 share_path 가 공유 경로($fullPath, 파일 공유면 그 파일)와 같거나
     *   폴더 공유면 그 아래여야 함. 종전엔 세션 ID 만 맞으면 어떤 공유 토큰으로도 다른 공유·탐색기 세션의 조각을 받거나 stop 으로 지울 수 있었음
     */
    private function hlsShareSessionOwned(string $sessionDir, string $fullPath): bool {
        // 새 세션은 시작 응답(echo) 바로 뒤에 meta.json 을 쓰므로, 아주 빠른 playlist 요청이면 잠깐(최대 1초) 기다림
        for ($_w = 0; $_w < 10 && !is_file($sessionDir . '/meta.json'); $_w++) usleep(100000);
        $meta = null;
        for ($_r = 0; $_r < 5; $_r++) {   // ★ (2026-11-01 12:30, 39차 점검 재검토) meta.json 은 다시 쓰일 때(SW 폴백·재사용) 잠깐 잘린 내용일 수 있어 몇 번 다시 읽음 — 한 번만 읽으면 그 순간의 정상 요청이 404 였음
            $meta = @json_decode((string)@file_get_contents($sessionDir . '/meta.json'), true);
            if (is_array($meta)) break;
            usleep(50000);
        }
        if (!is_array($meta) || !is_string($meta['share_path'] ?? null) || $meta['share_path'] === '') return false;
        if ((string)($meta['mc_user'] ?? '') !== $this->mcUser) return false;   // ★ myComix: 같은 계정의 세션만(파일 경로 + 계정)
        $sp = realpath($meta['share_path']) ?: $meta['share_path'];
        $root = realpath($fullPath) ?: $fullPath;
        $win = (DIRECTORY_SEPARATOR === '\\');
        if ($win) { $sp = strtolower(str_replace('/', '\\', $sp)); $root = strtolower(str_replace('/', '\\', $root)); }
        if ($sp === $root) return true;
        $rootDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        return is_dir($fullPath) && strpos($sp, $rootDir) === 0;
    }

    public function hlsShareStream(string $fullPath, bool $privateCache = false): void {   // ★ (2026-11-04) 56차 $privateCache: 비밀번호 공유(조각 Cache-Control private)
        $action = $_GET['hls_action'] ?? 'start';
        $dataDir = self::workDir();   // ★ myComix: 기록 파일은 cache/_vds
        $hlsDir = self::hlsRoot();    // ★ myComix: 세션 폴더 cache/_hls (FileStation data/hls_sessions)
        
        // === playlist/segment 서빙 ===
        if ($action === 'playlist' || $action === 'segment') {
            // ★ (2026-11-01 11:42, 39차 점검) session 이 배열이면 preg_replace 가 배열을 돌려 'Array' 경로·TypeError 였음 → 문자열만
            $sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', is_string($_GET['session'] ?? null) ? $_GET['session'] : '');
            if (!$sessionId) { http_response_code(400); exit; }
            
            $sessionDir = $hlsDir . '/' . $sessionId;
            if (!is_dir($sessionDir)) { http_response_code(404); exit; }
            if (!$this->hlsShareSessionOwned($sessionDir, $fullPath)) { http_response_code(404); exit; }   // ★ (2026-11-01 11:42, 39차) 이 공유의 세션만
            
            if ($action === 'playlist') {
                $m3u8 = $sessionDir . '/stream.m3u8';
                
                // ★ 빠른 실패 감지 + m3u8 생성 대기 (최대 5초, api.php 경로와 동일)
                //   A) m3u8 생성됨 → 성공
                //   B) ffmpeg 프로세스 죽음 (pid.txt 기반) → 실패 확정
                //   C) ffmpeg.log에 치명적 실패 키워드 → 실패 확정
                $pidFile = $sessionDir . '/pid.txt';
                $ffLog = $sessionDir . '/ffmpeg.log';
                $fatalLogPatterns = [
                    '/Conversion failed!/i',
                    '/Could not open encoder before EOF/i',
                    '/Nothing was written into output file/i',
                    '/Invalid argument\b.*Terminating thread/i',
                ];
                $waitStart = microtime(true);
                $maxWait = 5.0;
                $ffmpegDied = false;
                $logFatalDetected = false;
                $waited = 0;
                $pollIter = 0;
                while (!file_exists($m3u8) && $waited < $maxWait) {
                    usleep(100000);
                    $waited = microtime(true) - $waitStart;
                    $pollIter++;
                    
                    // A) 프로세스 생존 체크 (300ms 주기)
                    if ($waited > 0.3 && file_exists($pidFile) && ($pollIter % 3 === 0)) {
                        $pid = (int)@file_get_contents($pidFile);
                        if ($pid > 0) {
                            if (PHP_OS_FAMILY === 'Windows') {
                                $check = @shell_exec('tasklist /FI "PID eq ' . $pid . '" /NH 2>NUL');
                                if (!$check || strpos($check, (string)$pid) === false) {
                                    $ffmpegDied = true;
                                    break;
                                }
                            } else {
                                if (!file_exists('/proc/' . $pid)) {
                                    $ffmpegDied = true;
                                    break;
                                }
                            }
                        }
                    }
                    
                    // B) 치명적 로그 키워드 감지 (300ms 주기)
                    if ($waited > 0.3 && file_exists($ffLog) && ($pollIter % 3 === 0)) {
                        $logTail = '';
                        $logSize = @filesize($ffLog);
                        if ($logSize > 0) {
                            $fh = @fopen($ffLog, 'r');
                            if ($fh) {
                                $readFrom = max(0, $logSize - 2048);
                                @fseek($fh, $readFrom, SEEK_SET);
                                $logTail = @fread($fh, 2048) ?: '';
                                @fclose($fh);
                            }
                        }
                        if ($logTail !== '') {
                            foreach ($fatalLogPatterns as $pat) {
                                if (preg_match($pat, $logTail)) {
                                    $logFatalDetected = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                if (!file_exists($m3u8)) {
                    // HW 에러 감지 시 libx264로 복구 시도
                    $hwErrorDetected = ($ffmpegDied || $logFatalDetected);
                    if (file_exists($ffLog)) {
                        $logText = file_get_contents($ffLog);
                        // HW 인코더 관련 에러 키워드 추가 체크
                        // ※ 전역 hw_encoder_cache.json은 절대 건드리지 않음
                        //   (공유 세션에서 특정 파일이 실패했다고 서버 전체를 SW로 바꾸면 안 됨)
                        $hwErrorPatterns = [
                            '/MFX session:\s+fail/i',
                            '/No capable devices found/i',
                            '/not supported by the QSV runtime/i',
                            '/\[h264_qsv[^\]]*\].*Error while opening/i',
                            '/\[hevc_qsv[^\]]*\].*Error while opening/i',
                            '/\[h264_nvenc[^\]]*\].*failed/i',
                            '/\[hevc_nvenc[^\]]*\].*failed/i',
                            '/\[h264_amf[^\]]*\].*failed/i',
                        ];
                        foreach ($hwErrorPatterns as $pattern) {
                            if (preg_match($pattern, $logText)) {
                                $hwErrorDetected = true;
                                break;
                            }
                        }
                    }
                    
                    // libx264로 자동 재시도
                    if ($hwErrorDetected) {
                        $metaFile = $sessionDir . '/meta.json';
                        $metaForSw = file_exists($metaFile) ? @json_decode(@file_get_contents($metaFile), true) : null;
                        $swCmd = is_array($metaForSw) ? ($metaForSw['sw_cmd'] ?? null) : null;
                        $swStderrLog = is_array($metaForSw) ? ($metaForSw['stderr_log'] ?? ($sessionDir . '/ffmpeg.log')) : ($sessionDir . '/ffmpeg.log');
                        
                        if ($swCmd) {
                            // ★ (2026-11-01 01:08, 35차 전체 점검) 탐색기 경로와 같음 — 로그의 HW 오류 문구가 남아 playlist 요청마다 libx264 를 또 띄울 수 있었음
                            //   → 재실행 직전 meta 에 'sw_fallback_started' 를 남기고, 있으면(락 잡은 뒤 다시 확인 포함) 다시 띄우지 않고 m3u8 만 기다림
                            $shareSwStarted = function () use ($metaFile): bool {
                                $mm = file_exists($metaFile) ? @json_decode(@file_get_contents($metaFile), true) : null;
                                return is_array($mm) && (!empty($mm['sw_fallback_started']) || !empty($mm['sw_fallback_applied']));
                            };
                            $shareSwWaitOnly = function () use ($m3u8): bool {
                                $wst = microtime(true);
                                while (!file_exists($m3u8) && (microtime(true) - $wst) < 8) { usleep(100000); }
                                return file_exists($m3u8);
                            };
                            if ($shareSwStarted()) {
                                if ($shareSwWaitOnly()) goto share_sw_done;
                                http_response_code(404); exit;
                            }
                            // 동시 요청 방지 락
                            $swLockFile = $sessionDir . '/sw_fallback.lock';
                            $lockFp = @fopen($swLockFile, 'c');
                            if ($lockFp && flock($lockFp, LOCK_EX | LOCK_NB)) {
                                // 락 획득 → 재실행 주도
                                if ($shareSwStarted()) {
                                    @flock($lockFp, LOCK_UN); @fclose($lockFp);
                                    if ($shareSwWaitOnly()) goto share_sw_done;
                                    http_response_code(404); exit;
                                }
                                $swStartMeta = @json_decode(@file_get_contents($metaFile), true) ?: [];
                                $swStartMeta['sw_fallback_started'] = time();
                                @file_put_contents($metaFile, json_encode($swStartMeta));
                            } else {
                                // 다른 요청이 이미 재실행 중 → m3u8 대기만
                                if ($lockFp) @fclose($lockFp);
                                $waitOtherStart = microtime(true);
                                while (!file_exists($m3u8) && (microtime(true) - $waitOtherStart) < 8) {
                                    usleep(100000);
                                }
                                if (!file_exists($m3u8)) {
                                    http_response_code(404); exit;
                                }
                                // 이어서 m3u8 서빙하도록 아래로 진행
                                goto share_sw_done;
                            }
                            
                            // 이전 실패 흔적 정리
                            foreach (glob($sessionDir . '/stream*.ts') as $oldSeg) { @unlink($oldSeg); }
                            @file_put_contents($swStderrLog, "\n=== HW encoder failed, retrying with libx264 ===\n", FILE_APPEND);
                            
                            // 기존 pid.txt 삭제 (죽은 HW PID 제거)
                            $pidFileForSwShare = $sessionDir . '/pid.txt';
                            @unlink($pidFileForSwShare);
                            
                            // libx264 백그라운드 재실행
                            if (PHP_OS_FAMILY === 'Windows') {
                                $bgCmd = 'start "" /B ' . $swCmd . ' 2>>' . escapeshellarg($swStderrLog);
                                @pclose(@popen($bgCmd, 'r'));
                            } else {
                                $bgCmd = $swCmd . ' >> ' . escapeshellarg($swStderrLog) . ' 2>&1 & echo $! > ' . escapeshellarg($pidFileForSwShare);
                                $wrapper = 'nohup sh -c ' . escapeshellarg($bgCmd) . ' > /dev/null 2>&1 &';
                                if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', @ini_get('disable_functions') ?: '')))) {
                                    @exec($wrapper);
                                } else {
                                    $ph = @popen($wrapper, 'r');
                                    if (is_resource($ph)) @pclose($ph);
                                }
                            }
                            
                            // m3u8 생성 대기 (최대 8초)
                            $retryWaitStart = microtime(true);
                            $retryWaited = 0;
                            while (!file_exists($m3u8) && $retryWaited < 8) {
                                usleep(100000);
                                $retryWaited = microtime(true) - $retryWaitStart;
                            }
                            
                            if (file_exists($m3u8)) {
                                // Windows: 새 libx264 PID 찾아서 pid.txt 갱신
                                if (PHP_OS_FAMILY === 'Windows') {
                                    $swFoundPidShare = null;
                                    $wmic = @shell_exec('wmic process where "name=\'ffmpeg.exe\'" get processid,commandline /format:csv 2>NUL');
                                    if ($wmic) {
                                        foreach (explode("\n", $wmic) as $line) {
                                            if (strpos($line, $sessionId) !== false && preg_match('/,(\d+)\s*$/', trim($line), $pm)) {
                                                $swFoundPidShare = $pm[1];
                                                break;
                                            }
                                        }
                                    }
                                    // ★ (2026-11-01 01:08, 35차 전체 점검) wmic 없는 윈도우에선 pid.txt 가 안 생겼음 → 탐색기 경로·시작 경로와 같은 PowerShell(Get-CimInstance) 대체
                                    if (!$swFoundPidShare) {
                                        $psSessionId = str_replace("'", "''", $sessionId);
                                        $psCmd = 'powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \\"Name = \'ffmpeg.exe\'\\" | Where-Object { $_.CommandLine -like \'*' . $psSessionId . '*\' } | Select-Object -First 1 -ExpandProperty ProcessId"';
                                        $psOut = @shell_exec($psCmd . ' 2>NUL');
                                        if ($psOut && preg_match('/^\d+$/', trim($psOut))) $swFoundPidShare = trim($psOut);
                                    }
                                    if ($swFoundPidShare) @file_put_contents($pidFileForSwShare, $swFoundPidShare);
                                }
                                
                                // meta.json의 current_encoder 갱신
                                $metaData = @json_decode(@file_get_contents($metaFile), true) ?: [];
                                $metaData['current_encoder'] = 'libx264';
                                $metaData['sw_fallback_applied'] = true;
                                @file_put_contents($metaFile, json_encode($metaData));
                                
                                // 락 해제
                                if (isset($lockFp) && $lockFp) {
                                    @flock($lockFp, LOCK_UN);
                                    @fclose($lockFp);
                                }
                                // 성공 — 아래 정상 흐름으로 진행
                            } else {
                                if (isset($lockFp) && $lockFp) {
                                    @flock($lockFp, LOCK_UN);
                                    @fclose($lockFp);
                                }
                                http_response_code(404); exit;
                            }
                        } else {
                            http_response_code(404); exit;
                        }
                    } else {
                        http_response_code(404); exit;
                    }
                }
                share_sw_done:
                
                @touch($sessionDir);
                // ★ (2026-11-01 07:25, 37차 기능 점검) 마지막 접근 시각 기록(탐색기 playlist·segment 와 같게) — 종전엔 touch 만 해서 고아 판정(hlsCleanupStale)이 .ts 수정 시각으로만 봐,
                //   변환이 끝난 뒤 10분이 지나면 시청 중인 공유 세션도 지워졌음
                @file_put_contents($sessionDir . '/last_access.txt', (string)time());
                
                $content = file_get_contents($m3u8);
                // 세그먼트 URL 치환 — share.php 경로로
                $token = $_GET['t'] ?? '';
                $password = $_GET['password'] ?? '';
                $baseUrl = $this->mcBase . '&hls_action=segment&session=' . $sessionId;   // ★ myComix: viewer.php 주소(로그인·폴더 권한 검사)
                if ((string)$password !== '') $baseUrl .= '&password=' . urlencode($password); // ★ (2026-11-01 11:46, 39차 점검) 공유 비밀번호가 '0' 이면 붙지 않던 것(아래 두 곳도 같게)
                $content = preg_replace('/^(stream\d+\.ts)$/m', $baseUrl . '&seg=$1', $content);   // ★ myComix: 'file' 은 영상 경로 이름이라 조각 이름은 'seg'
                
                header('Content-Type: application/vnd.apple.mpegurl');
                header('Cache-Control: no-cache');
                
                // 현재 인코더 헤더 (배지 업데이트용, 인젝션 방어)
                $currentMetaShare = @json_decode(@file_get_contents($sessionDir . '/meta.json'), true);
                if (is_array($currentMetaShare) && !empty($currentMetaShare['current_encoder'])) {
                    $encSafe = preg_replace('/[^A-Za-z0-9_]/', '', (string)$currentMetaShare['current_encoder']);
                    if ($encSafe !== '') {
                        header('Access-Control-Expose-Headers: X-Current-Encoder, X-Sw-Fallback-Applied');
                        header('X-Current-Encoder: ' . $encSafe);
                    }
                    if (!empty($currentMetaShare['sw_fallback_applied'])) {
                        header('X-Sw-Fallback-Applied: 1');
                    }
                }
                
                echo $content;
                exit;
            }
            
            if ($action === 'segment') {
                $file = basename(is_string($_GET['seg'] ?? null) ? $_GET['seg'] : '');   // ★ myComix: 조각 이름은 'seg'
                if (!$file || !preg_match('/^stream\d+\.ts$/', $file)) { http_response_code(400); exit; }
                $segPath = $sessionDir . '/' . $file;
                
                $waited = 0;
                while (!file_exists($segPath) && $waited < 10) {
                    usleep(300000);
                    $waited += 0.3;
                }
                if (!file_exists($segPath)) { http_response_code(404); exit; }
                
                @touch($sessionDir);
                // ★ (2026-11-01 07:25, 37차 기능 점검) 마지막 접근 시각 기록(탐색기 playlist·segment 와 같게) — 종전엔 touch 만 해서 고아 판정(hlsCleanupStale)이 .ts 수정 시각으로만 봐,
                //   변환이 끝난 뒤 10분이 지나면 시청 중인 공유 세션도 지워졌음
                @file_put_contents($sessionDir . '/last_access.txt', (string)time());
                
                header('Content-Type: video/mp2t');
                header('Content-Length: ' . filesize($segPath));
                header('Cache-Control: ' . ($privateCache ? 'private' : 'public') . ', max-age=3600');   // ★ (2026-11-04) 56차 비밀번호 공유는 private(같은 공유 일반 스트림 54차·share.php 와 같음), 비밀번호 없는 공유는 그대로 public
                readfile($segPath);
                exit;
            }
        }
        
        // === stop ===
        if ($action === 'stop') {
            // ★ (2026-11-01 11:42, 39차 점검) session 배열이면 'Array' 경로 → 문자열만. 그리고 이 공유의 세션일 때만 끄고 지움(위 hlsShareSessionOwned)
            $_stopSid = $_GET['session'] ?? $_POST['session'] ?? '';
            $sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', is_string($_stopSid) ? $_stopSid : '');
            $dataDir2 = self::workDir();
            if ($sessionId && is_dir($hlsDir . '/' . $sessionId) && !$this->hlsShareSessionOwned($hlsDir . '/' . $sessionId, $fullPath)) {
                $sessionId = '';
            }
            if ($sessionId) {
                $sessionDir = $hlsDir . '/' . $sessionId;
                if (is_dir($sessionDir)) {
                    $pidFile = $sessionDir . '/pid.txt';
                    $shareStopKilled = false;   // ★ (2026-11-01 01:51, 36차 전체 점검) pid.txt 로 끄지 못했는지 표시(아래 대체용)
                    if (file_exists($pidFile)) {
                        $pid = (int)file_get_contents($pidFile);
                        if ($pid > 0 && !self::hlsPidIsOurs($pid, $sessionId)) $pid = 0;   // ★ (2026-11-01 01:29, 35차 전체 점검) 다른 프로세스가 받은 PID 면 죽이지 않음(hlsCleanup 과 같게)
                        if ($pid > 0) {
                            if (PHP_OS_FAMILY === 'Windows') {
                                @exec("taskkill /PID $pid /F /T 2>NUL");
                                $shareStopKilled = true;
                            } else {
                                // ★ (2026-11-01 01:51, 36차 전체 점검) posix 확장이 없는 PHP 에선 posix_kill 호출이 치명 오류(500)라 정리까지 못 갔음 → 있으면 posix_kill, 없으면 kill -TERM
                                if (function_exists('posix_kill')) {
                                    $shareStopKilled = @posix_kill($pid, 15);
                                } else {
                                    @exec('kill -TERM ' . (int)$pid . ' 2>/dev/null');
                                    $shareStopKilled = true;
                                }
                            }
                        }
                    }
                    // ★ (2026-11-01 01:51, 36차 전체 점검) pid.txt 가 없거나(wmic 없는 윈도우 등) PID 를 못 쓰면 ffmpeg 가 남았음
                    //   → 탐색기 정리(hlsCleanup)를 그대로 써서 세션 ID 명령줄 검색으로 끄고 세션 폴더도 지움(아래 즉시 삭제와 같은 일)
                    if (!$shareStopKilled) {
                        $this->hlsCleanup($sessionId, $hlsDir);
                    }
                }
                // 세션 디렉토리 즉시 삭제
                // ★ (2026-11-13 21:26, 펜닐 제보 — fix148) 윈도우는 taskkill 직후 ffmpeg 가 잡고 있던 ffmpeg.log 가 아직 안 풀려 지우기가 실패 →
                //   로그 1개 때문에 세션 폴더가 남았음(30분 뒤 고아 정리 때까지). 첫 시도는 종전 그대로, 남으면 0.5초씩 6번 더(최대 3초 — hlsCleanup 의 재시도와 같은 방식)
                for ($_stopTry = 0; $_stopTry < 7 && is_dir($sessionDir); $_stopTry++) {
                    if ($_stopTry > 0) usleep(500000);
                    foreach (new \DirectoryIterator($sessionDir) as $f) {
                        if (!$f->isDot()) @unlink($f->getPathname());
                    }
                    @rmdir($sessionDir);
                }
            }
            $this->hlsCleanupStale();
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        
        // === start: 새 세션 생성 ===
        $ffmpeg = (mcv_ffmpeg() ?: null);   // ★ myComix: 관리자 설정 경로
        if (!$ffmpeg) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'ffmpeg not available']);
            exit;
        }
        
        if (!is_file($fullPath)) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            exit;
        }
        
        if (!is_dir($hlsDir)) @mkdir($hlsDir, 0755, true);
        
        // 기존 세션 재사용 (같은 파일)
        // ★ force_sw=1 요청 시 기존 HW 세션 (force_sw 없음) 은 reuse 안 함 (HW→SW fallback 의도)
        //   하지만 같은 force_sw=1 세션은 reuse — 서버 이중 실행 시 두 폴더 생성 방어 (메인과 동일)
        //   force_sw + audio + path + client_session 모두 일치 시 reuse
        $reqForceSwShare = isset($_GET['force_sw']) ? '1' : '0';
        // ★ 요청된 audio 인덱스 (reuse 비교용)
        $reqAudioIdxShare = isset($_GET['audio']) ? (int)$_GET['audio'] : null;
        // ★ client_session — PHP 세션 ID hash로 기기별 분리 (메인과 동일 패턴)
        //   같은 사용자 PC/모바일 동시 시청 시 폴더 공유 방지
        $reqClientSessionShare = session_id() ? substr(hash('sha256', session_id()), 0, 16) : '';
        // ★ quality 비교 — 다른 quality 요청은 별도 세션 (v5.8.1c)
        // ★ (2026-11-01 11:42, 39차 점검) quality[]=x 처럼 배열이면 trim TypeError 로 500 이었음 → 문자열일 때만(아래 새 세션 쪽도 같게)
        $reqQualityShare = (isset($_GET['quality']) && is_string($_GET['quality'])) ? trim($_GET['quality']) : 'original';
        // ★ (2026-11-01 07:25, 37차 기능 점검) 시작 시점(seek)도 비교 — 탐색기(v5.8.1c, abs 차 0.5초 이상이면 새 세션)와 같게. 종전엔 다른 시점 요청에 예전 세션을 돌려줘
        //   화면 보정값(_qualitySeekOffset)만큼 시간 표시·자막·이어 보기가 어긋났음. 값은 아래 새 세션 meta 의 seek 와 같은 식
        $reqSeekShare = isset($_GET['seek']) ? max(0, (float)$_GET['seek']) : 0;
        if (is_dir($hlsDir)) {
            foreach (new \DirectoryIterator($hlsDir) as $d) {
                if ($d->isDot() || !$d->isDir()) continue;
                $metaFile = $d->getPathname() . '/meta.json';
                if (!file_exists($metaFile)) continue;
                $meta = @json_decode(file_get_contents($metaFile), true);
                $existAudioIdxShare = isset($meta['audio']) ? (int)$meta['audio'] : null;
                // ★ (2026-08-24) 메인 경로와 동일 수정 — 요청 의도(force_sw)끼리 비교.
                //   current_encoder 기반 판정은 HW 감지 실패 시 항상 '1'이 되어
                //   force_sw 없는 요청과 어긋나 매번 새 세션을 만든다(폴더·ffmpeg 중복).
                //   필드가 없는 기존 meta.json 은 종전 방식으로 판정(하위호환).
                $existIsSwShare = isset($meta['force_sw'])
                    ? (string)$meta['force_sw']
                    : (!empty($meta['current_encoder']) && strpos($meta['current_encoder'], 'libx264') !== false ? '1' : '0');
                $existClientSessionShare = $meta['client_session'] ?? '';
                $existQualityShare = $meta['quality'] ?? 'original';
                if (($meta['share_path'] ?? '') === $fullPath
                    && (string)($meta['mc_user'] ?? '') === $this->mcUser
                    && $existAudioIdxShare === $reqAudioIdxShare
                    && $existIsSwShare === $reqForceSwShare
                    && $existClientSessionShare === $reqClientSessionShare
                    && $existQualityShare === $reqQualityShare
                    && abs((float)($meta['seek'] ?? 0) - $reqSeekShare) < 0.5) {
                    $existDir = $d->getPathname();
                    $existSessionId = $d->getFilename();
                    $existCreated = $meta['created'] ?? 0;
                    $isActive = false;
                    if (time() - $existCreated < 60) $isActive = true;
                    if (!$isActive) {
                        $existM3u8 = $existDir . '/stream.m3u8';
                        if (file_exists($existM3u8)) {
                            clearstatcache(true, $existM3u8);
                            $isActive = (time() - filemtime($existM3u8)) < 30;
                        }
                    }
                    if ($isActive) {
                        // reuse 시 meta.json 갱신 + touch (cleanup 보호)
                        // ★ sw_cmd, stderr_log, current_encoder, audio, client_session, quality, seek 기존 값 보존
                        // ★ (2026-11-01 11:43, 39차 점검) 루프 앞에서 읽은 $meta 로 통째로 다시 써서, 그 사이 playlist 쪽이 쓴 SW 폴백 표시
                        //   (sw_fallback_started/applied·current_encoder)를 덮을 수 있었음 → 쓰기 직전에 다시 읽은 최신 값을 기준으로 함(키 구성은 그대로)
                        $_reFresh = @json_decode((string)@file_get_contents($metaFile), true);
                        if (is_array($_reFresh)) $meta = $_reFresh;
                        @file_put_contents($metaFile, json_encode([
                            'created' => time(),
                            'share_path' => $fullPath,
                            'mc_user' => $this->mcUser,
                            'audio' => $existAudioIdxShare,
                            'client_session' => $existClientSessionShare,
                            'sw_cmd' => $meta['sw_cmd'] ?? null,
                            'stderr_log' => $meta['stderr_log'] ?? null,
                            'current_encoder' => $meta['current_encoder'] ?? null,
                            // ★ (2026-08-24) force_sw 보존 — 재사용 시 이 값이 빠지면
                            //   다음 재사용 판정이 다시 current_encoder 기반으로 떨어져 같은 버그가 재발한다.
                            'force_sw' => $existIsSwShare,
                            'sw_fallback_applied' => $meta['sw_fallback_applied'] ?? false,
                            'quality' => $existQualityShare,
                            'seek' => (float)($meta['seek'] ?? 0),
                            // ★ (2026-11-01 01:08, 35차 전체 점검) SW 폴백 시작 표시 보존(탐색기 재사용과 같음)
                            'sw_fallback_started' => $meta['sw_fallback_started'] ?? null,
                        ]));
                        @touch($existDir);
                        
                        $token = $_GET['t'] ?? '';
                        $password = $_GET['password'] ?? '';
                        $playlistUrl = $this->mcBase . '&hls_action=playlist&session=' . $existSessionId;
                        if ((string)$password !== '') $playlistUrl .= '&password=' . urlencode($password);
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'session' => $existSessionId, 'playlist' => $playlistUrl, 'reused' => true]);
                        exit;
                    }
                }
            }
        }
        
        $sessionId = bin2hex(random_bytes(8));
        $sessionDir = $hlsDir . '/' . $sessionId;
        @mkdir($sessionDir, 0755, true);
        
        // Windows 한글 경로 → 8.3 짧은 경로
        $ffmpegInput = $fullPath;
        if (PHP_OS_FAMILY === 'Windows') {
            $shortPath = $this->getWindowsShortPath($fullPath);
            if ($shortPath) $ffmpegInput = $shortPath;
        }
        $inputPath = $this->escapeShellPath($ffmpegInput);
        
        $audioMap = ' -map 0:v:0 -map "0:a:0?"';   // ★ (2026-10-15) 음성 없는 영상도 HLS 가 되게 — 탐색기 HLS 와 같은 이유·같은 방식
        if (isset($_GET['audio'])) {
            $audioIdx = (int)$_GET['audio'];
            $audioMap = ' -map 0:v:0 -map 0:a:' . max(0, $audioIdx);   // ★ myComix: 음성 순번(0부터 — 음성 트랙 안에서의 번호)
        }
        
        // ★ seek 파라미터 처리 (v5.8.1c — share에서도 quality 변경 시 사용자 시점부터 트랜스코딩)
        $seekSecShare = isset($_GET['seek']) ? max(0, (float)$_GET['seek']) : 0;
        $seekArgShare = $seekSecShare > 0 ? ' -ss ' . $seekSecShare : '';
        
        // SW 코덱 인자는 고정 (HW 실패 시 재시도용)
        $swCodecArgs = '-c:v libx264 -preset ultrafast -crf 23 -tune zerolatency -profile:v high -level 4.1 -pix_fmt yuv420p';
        $usingHwEncoder = false;
        if (isset($_GET['force_sw'])) {
            $videoCodecArgs = $swCodecArgs;
        } else {
            $videoCodecArgs = $this->detectHwEncoder($ffmpeg);
            $usingHwEncoder = (strpos($videoCodecArgs, 'libx264') === false);
        }
        $gopArgs = ' -g 48 -keyint_min 48';
        
        // ★ Quality 옵션 적용 (v5.8.1c)
        //   메인 HLS와 동일 패턴 — 양쪽 codec args 모두 적용
        $shareQuality = (isset($_GET['quality']) && is_string($_GET['quality'])) ? trim($_GET['quality']) : 'original';   // ★ (2026-11-01 11:42, 39차) 배열 입력 500 방지
        $shareQualityResult = $this->applyQualityToArgs($videoCodecArgs, $shareQuality);
        $videoCodecArgs = $shareQualityResult['codecArgs'];
        $shareQualityVf = $shareQualityResult['vfPrepend'];
        $swQualityResultShare = $this->applyQualityToArgs($swCodecArgs, $shareQuality);
        $swCodecArgs = $swQualityResultShare['codecArgs'];
        
        $outputM3u8 = $this->escapeShellPath($sessionDir . '/stream.m3u8');
        $segPattern = '"' . str_replace('/', DIRECTORY_SEPARATOR, $sessionDir . '/stream%d.ts') . '"';
        
        // ffmpeg 명령 빌더 (같은 옵션으로 HW/SW 둘 다 만들 수 있도록 함수화)
        $buildShareFfmpegCmd = function($codecArgs) use ($ffmpeg, $inputPath, $audioMap, $gopArgs, $segPattern, $outputM3u8, $shareQualityVf, $seekArgShare) {
            // ★ (2026-08-24) VA-API 지원 — 켜지 않았으면 빈 문자열/원본 반환이라 종전과 동일.
            $vaDev = $this->vaapiDeviceArg($codecArgs);
            $vfExpr = $this->encodeVf($shareQualityVf, $codecArgs);   // ★ (2026-11-02 14:34, 44차 점검) 홀수 크기 영상 — 늘 짝수(encodeVf 참고)
            $vfArg = $vfExpr !== '' ? ' -vf "' . $vfExpr . '"' : '';
            return escapeshellarg($ffmpeg)
                . $seekArgShare
                . $vaDev
                . ' -readrate 3'
                . ' -analyzeduration 2000000 -probesize 2000000'
                . ' -fflags +genpts+igndts+fastseek'
                . ' -i ' . $inputPath
                . $audioMap
                . ' -sn'
                . ' ' . $codecArgs
                . $gopArgs
                . ' -c:a aac -b:a 128k -ac 2'
                . $vfArg
                . ' -af aresample=async=1000:first_pts=0'
                . ' -f hls'
                . ' -hls_time 4'
                . ' -hls_list_size 0'
                . ' -hls_playlist_type event'
                . ' -hls_flags independent_segments'
                . ' -hls_segment_type mpegts'
                . ' -hls_segment_filename ' . $segPattern
                . ' ' . $outputM3u8;
        };
        $cmd = $buildShareFfmpegCmd($videoCodecArgs);
        // HW 인코더 사용 중이면 playlist 요청 경로에서 폴백 가능하도록 SW 명령도 준비
        $cmdSwBackup = $usingHwEncoder ? $buildShareFfmpegCmd($swCodecArgs) : null;
        
        $token = $_GET['t'] ?? '';
        $password = $_GET['password'] ?? '';
        $playlistUrl = $this->mcBase . '&hls_action=playlist&session=' . $sessionId;
        if ((string)$password !== '') $playlistUrl .= '&password=' . urlencode($password);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'session' => $sessionId,
            'playlist' => $playlistUrl,
        ]);
        
        // 메타데이터 저장 — sw_cmd, stderr_log, current_encoder, audio, client_session 포함 (api.php 경로와 동일)
        $stderrLog = $sessionDir . '/ffmpeg.log';
        $metaAudioIdxShare = isset($_GET['audio']) ? (int)$_GET['audio'] : null;
        // ★ client_session: 기기별 분리 (메인과 동일 패턴)
        $metaClientSessionShare = session_id() ? substr(hash('sha256', session_id()), 0, 16) : '';
        file_put_contents($sessionDir . '/meta.json', json_encode([
            'created' => time(),
            'share_path' => $fullPath,
            'mc_user' => $this->mcUser,   // ★ myComix: 세션 주인(계정)
            'audio' => $metaAudioIdxShare,
            'client_session' => $metaClientSessionShare,
            'sw_cmd' => $cmdSwBackup,
            'stderr_log' => $stderrLog,
            'current_encoder' => $this->extractEncoderFromArgs($videoCodecArgs),
            // ★ (2026-08-24) 메인 경로와 동일 — 요청 의도를 저장해 재사용 판정이
            //   current_encoder(실제 인코더)에 좌우되지 않게 한다.
            'force_sw' => (isset($_GET['force_sw']) ? '1' : '0'),
            'quality' => $shareQuality,
            'seek' => $seekSecShare,
        ]));
        
        $pidFile = $sessionDir . '/pid.txt';
        if (PHP_OS_FAMILY === 'Windows') {
            $bgCmd = 'start /B "" ' . $cmd . ' > NUL 2> "' . $sessionDir . '\\ffmpeg.log"';
            pclose(popen($bgCmd, 'r'));
        } else {
            $bgCmd = $cmd . ' > ' . escapeshellarg($stderrLog) . ' 2>&1 & echo $! > ' . escapeshellarg($pidFile);
            $wrapper = 'nohup sh -c ' . escapeshellarg($bgCmd) . ' > /dev/null 2>&1 &';
            if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', @ini_get('disable_functions') ?: '')))) {
                exec($wrapper);
            } else {
                $ph = @popen($wrapper, 'r');
                if (is_resource($ph)) @pclose($ph);
            }
        }
        
        // PID 저장 (약간 대기 후)
        usleep(1000000);
        if (PHP_OS_FAMILY === 'Windows') {
            $wmic = shell_exec('wmic process where "name=\'ffmpeg.exe\'" get processid,commandline /format:csv 2>NUL');
            if ($wmic) {
                foreach (explode("\n", $wmic) as $line) {
                    if (strpos($line, $sessionId) !== false && preg_match('/,(\d+)\s*$/', trim($line), $pm)) {
                        file_put_contents($pidFile, $pm[1]);
                        break;
                    }
                }
            }
            // ★ (2026-11-01 01:51, 36차 전체 점검) wmic 없는 윈도우(11 24H2+, Server 2025)에선 공유 세션에 pid.txt 가 안 생겼음
            //   → 탐색기 시작 경로와 같은 PowerShell(Get-CimInstance) 대체
            if (!file_exists($pidFile)) {
                $psSessionId = str_replace("'", "''", $sessionId);
                $psCmd = 'powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \\"Name = \'ffmpeg.exe\'\\" | Where-Object { $_.CommandLine -like \'*' . $psSessionId . '*\' } | Select-Object -First 1 -ExpandProperty ProcessId"';
                $psOut = @shell_exec($psCmd . ' 2>NUL');
                if ($psOut && preg_match('/^\d+$/', trim($psOut))) {
                    file_put_contents($pidFile, trim($psOut));
                }
            }
        } else if (!file_exists($pidFile)) {
            // ★ (2026-11-01 17:27, 40차 점검) pgrep -f 는 자기를 실행한 sh -c 의 PID 도 함께 내 여러 줄이 그대로 pid.txt 에 들어갔고(첫 줄이 죽은 sh 면 '실행 중' 판정이 틀림),
            //   ps|grep 대체도 같음 → share.php 38차처럼 /proc 명령줄 첫 인자가 ffmpeg 이고 hlsPidIsOurs 를 통과한 PID 하나만 기록. /proc 가 없으면 첫 번호 하나만
            $pidRaw = trim(@shell_exec('pgrep -f "' . $sessionId . '" 2>/dev/null') ?? '');
            if (!$pidRaw) $pidRaw = trim(@shell_exec('ps aux 2>/dev/null | grep ' . escapeshellarg($sessionId) . ' | grep -v grep | awk \'{print $2}\' 2>/dev/null') ?? '');
            $pid = '';
            $pidFirst = '';
            foreach (preg_split('/\s+/', $pidRaw) as $_pgPid) {
                if (!ctype_digit($_pgPid) || (int)$_pgPid === getmypid()) continue;
                if ($pidFirst === '') $pidFirst = $_pgPid;
                if (!is_dir('/proc')) break;
                $_pgCl = @file_get_contents('/proc/' . $_pgPid . '/cmdline');
                if ($_pgCl === false) continue;
                if (stripos(basename((string)strtok($_pgCl, "\0")), 'ffmpeg') !== false && self::hlsPidIsOurs((int)$_pgPid, $sessionId)) { $pid = $_pgPid; break; }
            }
            if ($pid === '' && !is_dir('/proc')) $pid = $pidFirst;
            if ($pid !== '') file_put_contents($pidFile, $pid);
        }
        
        exit;
    }

    /**
     * ★ (2026-11-01 01:29, 35차 전체 점검) pid.txt 의 PID 가 아직 이 HLS 세션의 ffmpeg 인지 — 끝난 ffmpeg 의 PID 를 OS 가 다른 프로세스에 다시 줬으면 죽이지 않게.
     *   윈도우: wmic 로 그 PID 의 명령줄에 세션 ID 가 있는지(없으면 PowerShell), 둘 다 못 쓰면 tasklist 로 이미지 이름 ffmpeg.exe 만 확인.
     *   리눅스: /proc/PID/cmdline 에 세션 ID. /proc 이 없는 OS 는 확인 수단이 없어 true(종전대로)
     */
    public static function hlsPidIsOurs(int $pid, string $sessionId): bool {
        if ($pid <= 0) return false;
        if ($sessionId === '') return true;
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = (string)@shell_exec('wmic process where "processid=' . $pid . '" get commandline /format:list 2>NUL');
            if (trim($cmd) === '') {
                $cmd = (string)@shell_exec('powershell -NoProfile -Command "(Get-CimInstance Win32_Process -Filter \\"ProcessId = ' . $pid . '\\").CommandLine" 2>NUL');
            }
            if (trim($cmd) !== '') return stripos($cmd, 'ffmpeg') !== false && strpos($cmd, $sessionId) !== false;
            $tl = (string)@shell_exec('tasklist /FI "PID eq ' . $pid . '" /FI "IMAGENAME eq ffmpeg.exe" /NH 2>NUL');
            return stripos($tl, 'ffmpeg') !== false && (bool)preg_match('/\b' . $pid . '\b/', $tl);
        }
        if (is_dir('/proc/self')) {
            $cl = @file_get_contents('/proc/' . $pid . '/cmdline');
            return $cl !== false && strpos($cl, $sessionId) !== false;
        }
        return true;
    }

    private function hlsCleanup(string $sessionId, string $hlsDir): void {
        $sessionDir = $hlsDir . '/' . $sessionId;
        if (!is_dir($sessionDir)) return;
        $dataDir = self::workDir();
        
        // [HLSDebug] cleanup 과정 기록 (파일 있을 때만)
        $_timingLogCleanup = $dataDir . '/hls_timing.log';
        $_debugEnabled = is_file($_timingLogCleanup);
        $_debugLog = function($msg) use ($_debugEnabled, $_timingLogCleanup, $sessionId) {
            if (!$_debugEnabled) return;
            @file_put_contents($_timingLogCleanup,
                date('H:i:s.') . sprintf('%03d', (int)((microtime(true) - floor(microtime(true))) * 1000)) .
                " [$sessionId] CLEANUP $msg\n",
                FILE_APPEND);
        };
        
        // 방법 1: PID 파일
        $_killSent = false;   // ★ (2026-11-12 17:31, 101차 TRA-1) 실제로 종료 명령을 보낸 때만 아래 0.5초를 기다리려는 표시 — 종전엔 죽일 것이 없어도 고아 하나마다 0.5초 멈춤(재현: 고아 10개 5.2초)
        $pidFile = $sessionDir . '/pid.txt';
        if (file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            $_debugLog("pid_file_found pid=$pid");
            // ★ (2026-11-01 01:05, 35차 전체 점검) 이미 끝난 ffmpeg 의 PID 를 OS 가 다른 프로세스에 다시 줬으면 그 프로세스(트리)를 죽였음
            //   → 죽이기 전에 아직 이 세션의 ffmpeg 인지 확인: 윈도우는 tasklist 로 이미지 이름 ffmpeg.exe(PID 는 원래 ffmpeg.exe 로 찾은 값),
            //   리눅스는 /proc/PID/cmdline 에 세션 ID 가 있는지. /proc 이 없는 OS 는 확인 수단이 없어 종전대로
            if ($pid > 0) {
                $pidIsOurs = self::hlsPidIsOurs($pid, $sessionId);   // ★ (2026-11-01 01:29, 35차 전체 점검) 공유 쪽 정지와 같이 쓰도록 함수로(윈도우는 명령줄의 세션 ID 까지 확인)
                if (!$pidIsOurs) {
                    $_debugLog("pid_not_ours pid=$pid — skip kill");
                    $pid = 0;
                }
            }
            if ($pid > 0) {
                $_killSent = true;   // ★ (2026-11-12 17:31, 101차 TRA-1) 종료 명령 보냄
                if (PHP_OS_FAMILY === 'Windows') {
                    $taskkillOut = shell_exec('taskkill /F /PID ' . $pid . ' /T 2>&1');
                    $_debugLog("taskkill_pid pid=$pid result=" . trim(str_replace(["\r","\n"], ' | ', (string)$taskkillOut)));
                } else {
                    if (function_exists('posix_kill')) {
                        $r = posix_kill($pid, 9);
                        $_debugLog("posix_kill pid=$pid result=" . ($r ? 'OK' : 'FAIL'));
                    } else {
                        exec('kill -9 ' . $pid . ' 2>/dev/null');
                        $_debugLog("kill_-9 pid=$pid");
                    }
                }
            }
        } else {
            $_debugLog("pid_file_missing");
        }
        
        // 방법 2: sessionId가 커맨드라인에 포함된 ffmpeg 프로세스 검색 후 kill
        if (PHP_OS_FAMILY === 'Windows') {
            $killCount = 0;
            $foundPids = [];
            
            // 방법 2-1: wmic 시도 (Windows 10/Server 2019 이하)
            $wmic = @shell_exec('wmic process where "name=\'ffmpeg.exe\'" get processid,commandline /format:csv 2>NUL');
            if ($wmic) {
                foreach (explode("\n", $wmic) as $line) {
                    if (strpos($line, $sessionId) !== false && preg_match('/,(\d+)\s*$/', trim($line), $m)) {
                        $foundPids[] = (int)$m[1];
                    }
                }
            }
            
            // 방법 2-2: PowerShell fallback (wmic deprecated 환경 — Windows 11 24H2+ / Server 2022/2025)
            if (empty($foundPids)) {
                $psSessionId = str_replace("'", "''", $sessionId);
                $psCmd = 'powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \\"Name = \'ffmpeg.exe\'\\" | Where-Object { $_.CommandLine -like \'*' . $psSessionId . '*\' } | Select-Object -ExpandProperty ProcessId"';
                $psOut = @shell_exec($psCmd . ' 2>NUL');
                if ($psOut) {
                    foreach (explode("\n", $psOut) as $line) {
                        $line = trim($line);
                        if (preg_match('/^\d+$/', $line)) {
                            $foundPids[] = (int)$line;
                        }
                    }
                }
                if (!empty($foundPids)) {
                    $_debugLog("ps_fallback_found pids=" . implode(',', $foundPids));
                }
            }
            
            foreach ($foundPids as $foundPid) {
                $_killSent = true;   // ★ (2026-11-12 17:31, 101차 TRA-1) 종료 명령 보냄(Windows 명령줄 검색)
                $out = @shell_exec('taskkill /F /PID ' . $foundPid . ' /T 2>&1');
                $killCount++;
                $_debugLog("kill pid=$foundPid result=" . trim(str_replace(["\r","\n"], ' | ', (string)$out)));
            }
            $_debugLog("total_killed=$killCount");
        } else {
            // ★ v5.8.1j 케이스 F 수정 (Linux/Unix 환경 권한 fallback):
            //   pid.txt 기반 posix_kill/kill 이 권한 부족 등으로 실패 시
            //   (예: nginx+PHP-FPM 워커가 다른 사용자, Synology DSM 시스템 사용자 분리)
            //   sessionId로 ffmpeg 프로세스 재검색 후 추가 kill 시도
            //   pgrep -af → grep으로 sessionId 매칭 (ffmpeg 명령행에 sessionId 포함)
            //   pgrep 없는 환경은 ps -ef로 fallback
            //   ★ 보안: sessionId 추가 sanitize (방어 심층 — basename($dir) 경로 진입 시 대응)
            $safeSid = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId);
            if ($safeSid !== '' && $safeSid === $sessionId) {
                $killCount2 = 0;
                $foundPids2 = [];
                
                // 방법 2-1: pgrep -af (대부분 Linux/Synology에서 사용 가능)
                if (function_exists('shell_exec')) {
                    $pgrepOut = @shell_exec('pgrep -af ffmpeg 2>/dev/null');
                    if ($pgrepOut) {
                        foreach (explode("\n", $pgrepOut) as $line) {
                            if (strpos($line, $safeSid) !== false && preg_match('/^(\d+)\s/', trim($line), $m)) {
                                $foundPids2[] = (int)$m[1];
                            }
                        }
                    }
                    
                    // 방법 2-2: pgrep 결과 없으면 ps -ef fallback (BusyBox 등 pgrep 미설치 환경)
                    if (empty($foundPids2)) {
                        $psOut2 = @shell_exec('ps -ef 2>/dev/null');
                        if ($psOut2) {
                            foreach (explode("\n", $psOut2) as $line) {
                                if (strpos($line, 'ffmpeg') !== false && strpos($line, $safeSid) !== false) {
                                    // ps -ef 출력: UID PID PPID C STIME TTY TIME CMD
                                    if (preg_match('/^\S+\s+(\d+)\s/', trim($line), $m)) {
                                        $foundPids2[] = (int)$m[1];
                                    }
                                }
                            }
                        }
                    }
                }
                
                // 발견된 PID 종료 (posix_kill → kill -9 fallback)
                foreach ($foundPids2 as $foundPid) {
                    $_killSent = true;   // ★ (2026-11-12 17:31, 101차 TRA-1) 종료 명령 보냄(Linux 명령줄 검색)
                    if (function_exists('posix_kill')) {
                        $r = @posix_kill($foundPid, 9);
                        $_debugLog("linux_fallback_posix_kill pid=$foundPid result=" . ($r ? 'OK' : 'FAIL'));
                    } elseif (function_exists('exec')) {
                        @exec('kill -9 ' . $foundPid . ' 2>/dev/null');
                        $_debugLog("linux_fallback_kill_-9 pid=$foundPid");
                    } elseif (function_exists('shell_exec')) {
                        @shell_exec('kill -9 ' . $foundPid . ' 2>/dev/null');
                        $_debugLog("linux_fallback_shell_kill pid=$foundPid");
                    }
                    $killCount2++;
                }
                if ($killCount2 > 0) {
                    $_debugLog("linux_total_killed=$killCount2");
                }
            }
        }
        
        // ffmpeg 종료 대기
        if ($_killSent) usleep(500000);   // ★ (2026-11-12 17:31, 101차 TRA-1) 끈 프로세스가 있을 때만 기다린 뒤 지움(Windows 열린 파일 보호는 그대로, 아래 삭제 재시도도 그대로) — 종전엔 늘 0.5초
        
        // 파일 삭제 (재시도) - hidden 파일 포함
        $_debugLog("file_delete_start");
        for ($retry = 0; $retry < 3; $retry++) {
            $entries = @scandir($sessionDir);
            if ($entries) {
                foreach ($entries as $e) {
                    if ($e === '.' || $e === '..') continue;
                    @unlink($sessionDir . DIRECTORY_SEPARATOR . $e);
                }
            }
            if (@rmdir($sessionDir)) {
                $_debugLog("file_delete_done retry=$retry");
                return;
            }
            usleep(500000);
        }
        $_debugLog("file_delete_failed (session dir still exists)");
    }

    public function hlsCleanupStale(): void {
        $dataDir = self::workDir();   // ★ myComix: 기록 파일은 cache/_vds
        $hlsDir = self::hlsRoot();    // ★ myComix: 세션 폴더 cache/_hls (FileStation data/hls_sessions)
        if (!is_dir($hlsDir)) return;
        
        $dirs = glob($hlsDir . '/*', GLOB_ONLYDIR);
        if (!$dirs) return;
        
        $now = time();
        $orphanTimeout = 600; // 10분 — 클라이언트 미접근 시 고아로 판정
                              // HLS 버퍼 + 긴 일시정지 대응 (5분도 부족할 수 있음)
        $maxAge = 1800; // 30분 — 절대 최대 세션 수명
        
        foreach ($dirs as $dir) {
          try {   // ★ (2026-11-13 00:11, 107차 개선 W3) 세션 하나를 보다 오류가 나도 그 세션만 건너뛰고 나머지 정리는 계속 — 종전엔 오류 하나로 뒤 세션 정리가 모두 멈추고 정리를 부른 요청(재생 stop 등)이 500(재현)
            $metaFile = $dir . '/meta.json';
            $sessionId = basename($dir);
            $lastAccessFile = $dir . '/last_access.txt';
            
            // ★ 1단계: 클라이언트 마지막 접근 시간 기준 고아 판정
            // playlist/segment 요청 시 last_access.txt가 갱신됨
            // 10분 이상 클라이언트가 요청 안 보내면 = 모달 닫힘/브라우저 종료 = 고아
            if (file_exists($lastAccessFile)) {
                $lastAccess = (int)@file_get_contents($lastAccessFile);
                if ($lastAccess <= 0) $lastAccess = (int)@filemtime($lastAccessFile);   // ★ (2026-11-12 19:59, 103차 개선 Y3) 빈 값·0 이면(재생 목록·조각 요청이 이 파일을 '비우고 쓰는' 그 순간) 파일 수정 시각으로 — 종전엔 0 으로 읽어 재생 중인 세션을 고아로 지웠음(재현: 쓰기·정리 요청을 함께 쏟으면 1초 안에 지워짐). 끝내 빈 채로 남은 파일은 수정 시각 기준 10분 뒤 종전처럼 정리
                $idleAge = $now - $lastAccess;
                
                if ($idleAge > $orphanTimeout) {
                    // 고아 세션 — ffmpeg 살아있어도 강제 종료 및 정리
                    $this->hlsCleanup($sessionId, $hlsDir);
                    continue;
                }
                
                // 최근 접근 (10분 이내) — 정상 재생 중
                continue;
            }
            
            // ★ 2단계: last_access.txt 없는 구버전 세션 처리
            // 30분 이상 된 세션만 대상 (안전 여유)
            // ★ v5.8.1j 케이스 E 수정 (다른 환경 권한 문제 대응):
            //   nginx+PHP-FPM/Synology DSM 등에서 last_access.txt 쓰기 권한 실패 시
            //   2단계가 30분 maxAge만 사용 → 정상 재생 중인 세션도 30분 후 끊김 발생
            //   해결: ts 세그먼트 파일 mtime을 idle 판정 보조 신호로 사용
            //   ffmpeg가 세그먼트 만들면 mtime 갱신 — 살아있으면 idle 짧음 / 죽었으면 idle 김
            //   ts 파일은 ffmpeg 권한으로 생성/갱신 → last_access.txt 권한 문제와 무관
            $isOld = false;
            
            // ★ 2-A: ts 세그먼트 mtime 기반 idle 판정 (last_access.txt 권한 fallback)
            $tsFiles = glob($dir . '/stream*.ts');
            if ($tsFiles) {
                // 가장 최근 ts 세그먼트의 mtime 찾기 (max mtime)
                $latestTsMtime = 0;
                foreach ($tsFiles as $ts) {
                    $m = @filemtime($ts);
                    if ($m && $m > $latestTsMtime) $latestTsMtime = $m;
                }
                if ($latestTsMtime > 0) {
                    $tsIdleAge = $now - $latestTsMtime;
                    if ($tsIdleAge > $orphanTimeout) {
                        // ts 파일이 10분 이상 갱신 안 됨 = ffmpeg 정지/죽음 = 고아
                        $this->hlsCleanup($sessionId, $hlsDir);
                        continue;
                    }
                    // ts 파일이 최근 갱신됨 = 재생 중 = 보호
                    continue;
                }
            }
            
            // ★ 2-B: ts 파일도 없는 경우 (초기화 실패 또는 오래된 손상) — 30분 절대 만료
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true);
                $age = $now - (is_array($meta) && is_numeric($meta['created'] ?? null) ? $meta['created'] : 0);   // ★ (2026-11-13 00:11, 107차 개선 W3) created 가 숫자가 아니면(글자·배열) 계산하지 않음 — 종전엔 TypeError(재현: 'abc')
                if (!is_array($meta) || !is_numeric($meta['created'] ?? null) || (int)$meta['created'] <= 0) $age = $now - (int)@filemtime($metaFile);   // ★ (2026-11-12 19:59, 103차 개선 Y3) 쓰는 중이라 비었거나 created 가 없으면 파일 수정 시각으로(위 last_access 와 같음) — 종전엔 0 으로 봐 막 시작한 세션도 30분 넘은 것으로 지웠음(정적 확인, 같은 쓰기 방식). 정상 meta 는 종전 그대로
                $isOld = ($age > $maxAge);
            } else {
                $age = $now - filemtime($dir);
                $isOld = ($age > $maxAge);
            }
            
            if (!$isOld) continue;
            
            // 30분 넘은 세션은 무조건 정리 (last_access.txt 없으면 구버전/손상)
            $this->hlsCleanup($sessionId, $hlsDir);
          } catch (\Throwable $__hcE) { continue; }   // ★ (2026-11-13 00:11, 107차 개선 W3) 위 try 설명 — 그 세션은 지우지 않고 남김(덜 지우는 쪽)
        }
    }

    /**
     * Quality 프리셋 (v5.8.1c — 펜닐님 요청 7단계, PC/모바일 공통)
     *   - 키: 'original' / '1080p' / '720p' / '480p' / '360p' / '240p' / '144p'
     *   - height: 다운스케일 목표 높이 (원본이 더 작으면 그대로 유지)
     *   - vbr/maxrate/bufsize: ffmpeg 비트레이트 제한 옵션
     *   - 'original': 옵션 없음 (재인코딩 없이 -c:v copy 가능 — caller가 결정)
     *   - HW/SW 인코더 모두 -b:v -maxrate -bufsize 옵션을 받음
     */
    private function getQualityPreset(string $quality): ?array {
        $presets = [
            '1080p' => ['height' => 1080, 'vbr' => '5M',   'maxrate' => '6M',   'bufsize' => '10M'],
            '720p'  => ['height' => 720,  'vbr' => '2500k','maxrate' => '3M',   'bufsize' => '5M'],
            '480p'  => ['height' => 480,  'vbr' => '1200k','maxrate' => '1500k','bufsize' => '2500k'],
            '360p'  => ['height' => 360,  'vbr' => '700k', 'maxrate' => '900k', 'bufsize' => '1500k'],
            '240p'  => ['height' => 240,  'vbr' => '400k', 'maxrate' => '500k', 'bufsize' => '800k'],
            '144p'  => ['height' => 144,  'vbr' => '200k', 'maxrate' => '250k', 'bufsize' => '400k'],
        ];
        return $presets[$quality] ?? null;
    }

    /**
     * Quality 옵션을 ffmpeg 인자로 변환 (v5.8.1c)
     *   - $codecArgs: 기존 비디오 코덱 인자 (e.g. '-c:v libx264 ...')
     *   - $quality: 'original' | '1080p' | '720p' | '480p' | '360p' | '240p' | '144p'
     *   - 반환: ['codecArgs' => 변환된 인자 문자열, 'vfPrepend' => scale 필터 (없으면 '')]
     *   - 'original' 또는 미지원 값이면 원본 그대로 반환
     *   - -c:v copy 인 경우엔 quality 적용 불가 → caller가 copy를 풀어서 SW로 변경 후 호출해야 함
     */
    private function applyQualityToArgs(string $codecArgs, string $quality): array {
        if ($quality === '' || $quality === 'original') {
            return ['codecArgs' => $codecArgs, 'vfPrepend' => ''];
        }
        $preset = $this->getQualityPreset($quality);
        if (!$preset) {
            return ['codecArgs' => $codecArgs, 'vfPrepend' => ''];
        }
        // -c:v copy인 경우는 호출자가 미리 풀어서 와야 함 (방어적: copy면 quality 무시)
        if (preg_match('/-c:v\s+copy/', $codecArgs)) {
            return ['codecArgs' => $codecArgs, 'vfPrepend' => ''];
        }
        // 기존 -b:v / -maxrate / -bufsize 옵션 제거 후 새로 추가 (중복 방지)
        $cleanArgs = preg_replace('/-b:v\s+\S+/', '', $codecArgs);
        $cleanArgs = preg_replace('/-maxrate\s+\S+/', '', $cleanArgs);
        $cleanArgs = preg_replace('/-bufsize\s+\S+/', '', $cleanArgs);
        // h264_nvenc는 -qp 옵션 제거 (vbr 모드와 충돌)
        $cleanArgs = preg_replace('/-qp\s+\d+/', '', $cleanArgs);
        // h264_qsv는 -global_quality 제거 (비트레이트 모드)
        $cleanArgs = preg_replace('/-global_quality\s+\d+/', '', $cleanArgs);
        // h264_amf는 -qp_i / -qp_p 제거
        $cleanArgs = preg_replace('/-qp_[ip]\s+\d+/', '', $cleanArgs);
        // libx264의 crf 제거 (비트레이트 모드)
        $cleanArgs = preg_replace('/-crf\s+\d+/', '', $cleanArgs);
        $cleanArgs = preg_replace('/\s+/', ' ', trim($cleanArgs));
        
        $brArgs = ' -b:v ' . $preset['vbr'] . ' -maxrate ' . $preset['maxrate'] . ' -bufsize ' . $preset['bufsize'];
        // scale 필터: 원본이 작으면 유지, 크면 다운스케일 (-2: 짝수 폭 자동 계산)
        // 'min(H\,ih)' — ffmpeg 표현식에서 콤마 이스케이프 필요
        // ★ (2026-11-02 14:34, 44차 점검) 원본 세로가 화질보다 작고 홀수면(예 241) 그대로 홀수라 libx264/yuv420p 가 실패 → 세로도 trunc(ih/2)*2 로 늘 짝수(짝수 원본은 값 그대로)
        $vfPrepend = "scale='-2:min(" . (int)$preset['height'] . "\\,trunc(ih/2)*2)'";
        return ['codecArgs' => $cleanArgs . $brArgs, 'vfPrepend' => $vfPrepend];
    }

    private function extractEncoderFromArgs(string $args): string {
        if (preg_match('/-c:v\s+(\S+)/', $args, $m)) {
            return $m[1];
        }
        return 'unknown';
    }

    /**
     * VA-API 렌더 노드 경로를 찾는다. 설정에 직접 지정한 값이 있으면 그것을 최우선으로 쓴다.
     * 지정이 없으면 /dev/dri/renderD* 중 **읽고 쓸 수 있는 첫 번째**를 고른다
     * (여러 GPU 가 있으면 renderD128 이 보통 내장 그래픽이라 정렬 순서상 먼저 잡힌다).
     */
    private function vaapiDevicePath(): string {
        static $cached = null;
        if ($cached !== null) return $cached;
        $cached = '';
        if (PHP_OS_FAMILY === 'Windows') return $cached;
        $settings = $this->loadSiteSettingsOnce();
        $manual = trim($settings['vaapi_device'] ?? '');
        if ($manual !== '' && @file_exists($manual)) { $cached = $manual; return $cached; }
        $nodes = @glob('/dev/dri/renderD*');
        if (is_array($nodes)) {
            sort($nodes);
            foreach ($nodes as $n) {
                if (@is_readable($n) && @is_writable($n)) { $cached = $n; break; }
            }
        }
        return $cached;
    }

    /**
     * 코덱 인자가 VA-API 인코더인지 판정한다. 명령 조립부는 이 값만 보고 분기하므로
     * 감지 결과(문자열)만으로 판단이 끝나 별도 상태를 들고 다닐 필요가 없다.
     */
    private function isVaapiArgs(string $codecArgs): bool {
        return strpos($codecArgs, 'h264_vaapi') !== false;
    }

    /**
     * VA-API 일 때 입력 앞에 붙일 장치 인자를 돌려준다(아니면 빈 문자열).
     */
    private function vaapiDeviceArg(string $codecArgs): string {
        if (!$this->isVaapiArgs($codecArgs)) return '';
        $dev = $this->vaapiDevicePath();
        return $dev === '' ? '' : ' -vaapi_device ' . escapeshellarg($dev);
    }

    /**
     * 기존 -vf 필터 표현식에 VA-API 업로드 필터를 **이어붙인다**.
     *
     * VA-API 인코더는 프레임이 GPU 메모리에 올라가 있어야 하므로 필터 체인 **맨 끝**에
     * `format=nv12,hwupload` 가 와야 한다. 기존 스케일 필터(iOS 1080p 제한, 사용자 화질 조절)가
     * 있으면 그 뒤에 콤마로 연결하고, 없으면 단독으로 쓴다.
     * VA-API 가 아니면 **입력을 그대로 돌려주므로 기존 동작에 영향이 없다.**
     *
     * @param string $vfExpr 기존 필터 표현식(-vf 없이 표현식만). 없으면 빈 문자열.
     * @param string $codecArgs 감지된 코덱 인자
     * @return string 최종 필터 표현식(-vf 없이 표현식만)
     */
    private function vaapiVf(string $vfExpr, string $codecArgs): string {
        if (!$this->isVaapiArgs($codecArgs)) return $vfExpr;
        $up = 'format=nv12,hwupload';
        return $vfExpr === '' ? $up : ($vfExpr . ',' . $up);
    }

    /**
     // ★ (2026-11-02 14:34, 44차 점검) '원본' 화질이면 크기 필터가 없어 가로·세로가 홀수인 영상(321x241 등)은 libx264+yuv420p·HW 인코더가
     *   'not divisible by 2' 로 실패(HLS 404·파이프 빈 응답) → 재인코딩일 때 필터가 비어 있으면 짝수 맞춤 scale 을 넣고 vaapiVf 를 거친다
     *   (VA-API 는 업로드 필터 앞에 오게 됨). copy 이면 필터를 붙일 수 없으니 종전 그대로. 짝수 원본은 크기가 바뀌지 않는다.
     */
    private function encodeVf(string $vfExpr, string $codecArgs): string {
        if ($vfExpr === '' && !preg_match('/-c:v\s+copy/', $codecArgs)) {
            $vfExpr = 'scale=trunc(iw/2)*2:trunc(ih/2)*2';
        }
        return $this->vaapiVf($vfExpr, $codecArgs);
    }

    /**
     * ★ (2026-11-01 11:36, 39차 점검) 감지 캐시가 시각만 보고 설정값을 비교하지 않아, 관리자가 하드웨어 가속 방식·ffmpeg 경로·VA-API 장치를
     *   바꿔 저장해도 최대 24시간 옛 인코더를 썼음 → 캐시에 이 세 설정값의 지문을 같이 저장하고 다르면 다시 감지
     *   (지문이 없는 옛 캐시는 한 번 다시 감지됨)
     */
    private function hwCacheFp(): string {
        $st = $this->loadSiteSettingsOnce();
        return md5(trim((string)($st['hw_accel_mode'] ?? '')) . '|' . trim((string)($st['ffmpeg_path'] ?? '')) . '|' . trim((string)($st['vaapi_device'] ?? '')));
    }

    private function detectHwEncoder(string $ffmpeg): string {
        // 캐시 파일로 매번 감지하지 않음
        $dataDir = self::workDir();   // ★ myComix: 감지 과정 기록(hw_detect.log)은 cache/_vds
        $cacheFile = self::stateDir() . DIRECTORY_SEPARATOR . 'hw_encoder_cache.json';   // ★ (2026-11-13 14:41 fix143) 감지 결과는 지우면 안 되는 쪽(src/video/vstate)
        
        // 캐시 확인 (24시간 유효)
        if (is_file($cacheFile)) {
            $cache = @json_decode(file_get_contents($cacheFile), true);
            if ($cache && isset($cache['args']) && (time() - ($cache['time'] ?? 0)) < 86400 && ($cache['fp'] ?? '') === $this->hwCacheFp()) {   // ★ (2026-11-01 11:36, 39차) 설정 지문 비교
                return $cache['args'];
            }
        }
        
        $devNull = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        
        // 사용 가능한 인코더 목록 조회
        $encoderList = shell_exec(escapeshellarg($ffmpeg) . ' -encoders 2>' . $devNull) ?? '';
        
        // 우선순위: NVENC > QSV > AMF > Software
        // -pix_fmt yuv420p: 10bit 입력을 8bit로 변환 (iOS 호환 필수)
        // -profile:v high -level 4.1: iOS/모바일 호환성 보장
        $hwEncoders = [
            'h264_nvenc'  => '-c:v h264_nvenc -preset p1 -tune ll -qp 23 -profile:v high -level 4.1 -pix_fmt yuv420p',
            'h264_qsv'    => '-c:v h264_qsv -preset veryfast -global_quality 23 -profile:v high -pix_fmt yuv420p',
            'h264_amf'    => '-c:v h264_amf -quality speed -qp_i 23 -qp_p 23 -profile:v high -pix_fmt yuv420p',
        ];

        // ★ (2026-08-24) VA-API 를 후보에 추가한다 — **리눅스에서 렌더 노드가 있을 때만**.
        //   [왜 필요한가] 구세대 인텔(Haswell 등)은 인텔의 **리눅스용 QSV 런타임이
        //   Broadwell(5세대) 이상만 지원**해 h264_qsv 가 실패한다. 반면 VA-API 는 구세대 GPU 도
        //   지원하므로 이 조합에서 유일한 하드웨어 경로다(윈도우는 DXVA/D3D11 이라 세대 제한 없음
        //   → vaapiDevicePath() 가 윈도우에서 빈 값을 돌려주므로 후보에 들어가지 않는다).
        //   우선순위는 **가장 뒤**: NVENC·QSV·AMF 가 되는 환경은 그쪽을 그대로 쓰고,
        //   전부 실패하는 구세대 리눅스에서만 VA-API 가 선택된다.
        //   pix_fmt 를 지정하지 않는 이유: 업로드 필터(format=nv12,hwupload)가 포맷을 정하므로
        //   여기서 또 지정하면 충돌한다.
        if ($this->vaapiDevicePath() !== '') {
            $hwEncoders['h264_vaapi'] = '-c:v h264_vaapi -qp 23 -profile:v high -level 41';
        }

        // ★ (2026-08-24) 설정의 '하드웨어 가속' 값으로 **후보를 좁힌다**.
        //   [비움 = 자동] 위 순서대로 전부 시도해 되는 것을 채택(종전 동작 + VA-API 추가).
        //   [특정 인코더 지정] 그 하나만 시도하고, 실패하면 SW(libx264)로 떨어진다.
        //     → 환경을 아는 관리자가 감지 절차를 줄이고 의도를 명시할 수 있다.
        //   허용값이 아니거나 해당 후보가 없으면(예: 윈도우에서 vaapi 지정) **자동으로 되돌린다** —
        //   잘못된 설정 하나로 하드웨어 가속이 통째로 막히는 일이 없도록 하기 위함이다.
        $hwMode = trim($this->loadSiteSettingsOnce()['hw_accel_mode'] ?? '');
        if ($hwMode !== '' && isset($hwEncoders[$hwMode])) {
            $hwEncoders = [$hwMode => $hwEncoders[$hwMode]];
        }
        
        $result = '-c:v libx264 -preset ultrafast -crf 23 -tune zerolatency -profile:v high -level 4.1 -pix_fmt yuv420p';
        $detectedName = 'libx264 (CPU)';

        // ★ (2026-08-24) HW 감지 과정을 기록한다 — data/hw_detect.log
        //   [왜 필요한가] 지금까지 감지는 **결과만** 캐시에 남기고 과정을 전혀 기록하지 않아,
        //   "왜 CPU로 떨어졌는지"를 서버에서 확인할 방법이 없었다(캐시를 지워도 같은 값만 다시 생김).
        //   원격 접속(RDP) 세션에서 명령을 직접 돌리면 물리 GPU 대신 Microsoft Basic Render Driver가
        //   잡혀 결과가 달라지므로, **웹서버 프로세스가 스스로 남긴 기록**이라야 신뢰할 수 있다.
        //   감지는 24시간에 한 번만 도므로 부하는 무시 가능하고, 파일은 매번 덮어써 커지지 않는다.
        $hwLogPath = $dataDir . DIRECTORY_SEPARATOR . 'hw_detect.log';
        $hwLog = [];
        $hwLog[] = '=== HW 인코더 감지 ' . date('Y-m-d H:i:s') . ' ===';
        $hwLog[] = 'ffmpeg: ' . $ffmpeg;
        $hwLog[] = 'php_sapi: ' . PHP_SAPI . ' | os: ' . PHP_OS_FAMILY
                 . ' | exec_available: ' . (function_exists('exec') ? 'YES' : 'NO');
        // 인코더 목록 조회가 성공했는지부터 남긴다(빈 문자열이면 ffmpeg 실행 자체가 실패한 것)
        $hwLog[] = 'encoder_list_len: ' . strlen($encoderList);
        foreach (array_keys($hwEncoders) as $_e) {
            $hwLog[] = '  build_has_' . $_e . ': ' . (strpos($encoderList, $_e) !== false ? 'YES' : 'NO');
        }

        foreach ($hwEncoders as $encoder => $args) {
            if (strpos($encoderList, $encoder) === false) {
                $hwLog[] = "[$encoder] SKIP — 이 ffmpeg 빌드에 인코더가 없음";
                continue;
            }
            
            // 실제 인코딩 테스트 (짧은 테스트로 동작 여부 확인)
            // ★ (2026-08-24) ffmpeg 버전 무관하게 동작하도록 테스트 방식 교체.
            //   [문제] 기존에는 '-f mp4 NUL'로 테스트했는데, **mp4 muxer는 seek 가능한 출력이 필요**하고
            //   NUL은 seek이 안 된다. 구버전 ffmpeg는 경고로 넘어갔지만 최신(8.x)은 이를 에러로 처리해
            //   출력에 'Error'가 섞이고, 아래 키워드 검사가 그것을 잡아 **HW가 멀쩡해도 전부 실패 판정**이
            //   나면서 항상 libx264(CPU)로 굳었다(펜닐 서버 ffmpeg 8.1.1, hw_encoder_cache.json이
            //   지워도 계속 'libx264 (CPU)'로 재생성됨).
            //   [수정] 출력 muxer를 **null**로 바꾼다 — seek이 필요 없어 모든 버전에서 동일하게 동작하고,
            //   인코더는 그대로 돌므로 HW 동작 여부 검증이라는 목적은 유지된다.
            //   -frames:v 1 로 1프레임만, -hide_banner 로 configuration 문자열이 키워드 검사에
            //   섞이는 오탐도 없앤다. -nostdin 은 서버 환경에서 입력 대기로 멈추는 것을 방지.
            // ★ (2026-08-24) VA-API 는 실제 사용과 **같은 형태**로 테스트해야 의미가 있다.
            //   장치 인자는 입력 앞에, 업로드 필터는 인코더 앞에 와야 하므로 명령을 따로 만든다.
            //   (일반 인코더는 종전 그대로 — 이 분기는 vaapi 후보일 때만 탄다.)
            if ($encoder === 'h264_vaapi') {
                $dev = $this->vaapiDevicePath();
                if ($dev === '') {
                    $hwLog[] = "[$encoder] SKIP — /dev/dri 렌더 노드를 찾지 못함(권한 포함)";
                    continue;
                }
                $testCmd = escapeshellarg($ffmpeg)
                    . ' -hide_banner -nostdin -vaapi_device ' . escapeshellarg($dev)
                    . ' -f lavfi -i nullsrc=s=64x64:d=0.1'
                    . ' -vf format=nv12,hwupload ' . $args
                    . ' -frames:v 1 -f null - 2>&1';
            } else {
                $testCmd = escapeshellarg($ffmpeg)
                    . ' -hide_banner -nostdin -f lavfi -i nullsrc=s=64x64:d=0.1 ' . $args
                    . ' -frames:v 1 -f null - 2>&1';
            }

            // ★ 판정 기준도 바꾼다: **종료 코드**가 가장 정확하다(0 = 성공).
            //   기존의 광범위한 키워드('error', 'Cannot' 등)는 무관한 줄에도 걸려 오탐이 잦았다.
            $testOutput = null;
            $hasError = true;
            if (function_exists('exec')) {
                $outLines = [];
                $rc = 1;
                @exec($testCmd, $outLines, $rc);
                $testOutput = implode("\n", $outLines);
                $hasError = ($rc !== 0);
            } else {
                // exec 이 막힌 환경 대비 — shell_exec + **인코더 고유 실패 문구만** 검사.
                //   일반적인 'error'/'Cannot' 같은 단어는 정상 로그에도 나오므로 쓰지 않는다.
                $testOutput = shell_exec($testCmd);
                if ($testOutput === null) {
                    $hasError = true;
                } else {
                    $hasError = false;
                    $errorPatterns = [
                        'Error while opening encoder',
                        'Unknown encoder',
                        'No capable devices found',
                        'not supported by the QSV runtime',
                        'MFX session',
                        'Cannot load nvcuda',
                        'Cannot open encoder',
                        'InitializeEncoder failed',
                        'Encoder not found',
                    ];
                    foreach ($errorPatterns as $pat) {
                        if (stripos($testOutput, $pat) !== false) { $hasError = true; break; }
                    }
                }
            }
            // ★ 각 후보의 판정 근거를 남긴다 — 성공/실패와 **실패 시 ffmpeg 원문 마지막 부분**.
            //   ffmpeg 메시지가 그대로 남아야 'MFX session: -9'(장치 못 잡음),
            //   'Failed to find d3d11va adapter'(어댑터 열거 실패) 같은 실제 원인이 보인다.
            if ($hasError) {
                $tail = trim((string)$testOutput);
                if (strlen($tail) > 1200) $tail = '...' . substr($tail, -1200);
                $hwLog[] = "[$encoder] FAIL";
                $hwLog[] = '  ffmpeg_output: ' . str_replace(["\r\n", "\n"], ' | ', $tail);
            } else {
                $hwLog[] = "[$encoder] OK — 사용 가능";
            }

            if (!$hasError) {
                $result = $args;
                $detectedName = $encoder;
                break;
            }
        }

        $hwLog[] = 'RESULT: ' . $detectedName;
        @file_put_contents($hwLogPath, implode("\n", $hwLog) . "\n");

        // 캐시 저장
        @file_put_contents($cacheFile, json_encode([
            'args' => $result,
            'encoder' => $detectedName,
            'time' => time(),
            'fp' => $this->hwCacheFp(),   // ★ (2026-11-01 11:36, 39차) 설정 지문
        ]));
        
        return $result;
    }

    /**
     * ★ (2026-11-13 10:33, 펜닐 요청 — fix141) 마지막 대체 — 조각 MP4 한 줄기(FileStation transcodeShareStream 그대로: HW 인코더·첫 출력 6초 안에 없으면 CPU 로 다시·
     *   아이폰 GOP 24·음성 없는 영상은 무음 트랙·&audio=·&seek=). 화면은 ManagedMediaSource/MediaSource 로 받음(FileStation _fallbackToMMS), 안 되면 video.src
     */
    /**
     * 공유 링크용 트랜스코딩 스트리밍 (ffmpeg → fragmented MP4)
     */
    public function transcodeShareStream(string $fullPath): void {
        $ffmpeg = (mcv_ffmpeg() ?: null);
        if (!$ffmpeg) {
            http_response_code(500);
            header('Content-Type: application/json');
            $disArr = array_map('trim', explode(',', @ini_get('disable_functions') ?: ''));
            $execBlocked = !function_exists('exec') || in_array('exec', $disArr);
            $hint = $execBlocked ? ' (exec function is disabled in PHP)' : ' (check ffmpeg path in admin settings)';
            header('X-Transcode-Error: ffmpeg not available' . $hint);
            echo json_encode(['error' => 'ffmpeg not available' . $hint]);
            exit;
        }
        
        // proc_open 사용 가능 여부 체크 (트랜스코딩 필수)
        $disArr2 = array_map('trim', explode(',', @ini_get('disable_functions') ?: ''));
        if (!function_exists('proc_open') || in_array('proc_open', $disArr2)) {
            http_response_code(500);
            header('Content-Type: application/json');
            header('X-Transcode-Error: proc_open disabled');
            echo json_encode(['error' => 'proc_open function is disabled in PHP. Required for video streaming.']);
            exit;
        }
        
        if (!is_file($fullPath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            header('X-Transcode-Error: file-not-found');
            echo json_encode(['error' => 'File not found', 'path' => basename($fullPath)]);
            exit;
        }
        
        set_time_limit(0);
        while (ob_get_level()) ob_end_clean();
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', 'Off');
        
        $inputPath = $this->escapeShellPath($fullPath);
        
        // Windows 한국어 파일명 → 짧은 경로로 변환
        if (PHP_OS_FAMILY === 'Windows') {
            $shortPath = $this->getWindowsShortPath($fullPath);
            if ($shortPath) $inputPath = $this->escapeShellPath($shortPath);
        }
        
        // 오디오/비디오 트랙 정보 조회
        if (isset($_GET['info'])) {
            $probeCmd = escapeshellarg($ffmpeg) . ' -i ' . $inputPath . ' 2>&1';
            $output = shell_exec($probeCmd);
            
            $audioTracks = [];
            // ★ (2026-09-25) mp4 의 음성 줄은 스트림 번호 뒤에 식별 번호가 붙는다 — 'Stream #0:1[0x2](eng): Audio: aac …'
            //   (mkv 는 'Stream #0:1(eng): Audio: …'). 종전 식은 이를 몰라 mp4 에서 음성을 0개로 읽어, 다국어 mp4(FileStation
            //   H.264 변환 결과 포함)에서 음성 고르기가 나오지 않았다(펜닐 제보, 실제 ffmpeg 7.0.2 출력으로 확인). '[...]' 를 선택으로
            //   허용한다 — 값을 잡지 않는 묶음이라 뒤의 언어·코덱·채널 번호는 그대로.
            if (preg_match_all('/Stream\s+#(\d+):(\d+)(?:\[[^\]]*\])?(?:\(([a-z]+)\))?\:\s*Audio:\s*(\w+)[^,]*,\s*(\d+)\s*Hz[^,]*,\s*([^,\r\n]+)/i', $output, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $title = '';
                    if (preg_match('/Stream\s+#' . preg_quote($m[1] . ':' . $m[2]) . '.*\n\s*Metadata:\s*\n\s*title\s*:\s*(.+)/i', $output, $tm)) {
                        $title = trim($tm[1]);
                    }
                    $audioTracks[] = [
                        'index' => (int)$m[2],
                        'language' => $m[3] ?? '',
                        'codec' => strtoupper($m[4]),
                        'channels' => trim($m[6]),
                        'title' => $title,
                    ];
                }
            }
            
            $duration = 0;
            if (preg_match('/Duration:\s*(\d+):(\d+):(\d+)\.(\d+)/', $output, $dm)) {
                $duration = (int)$dm[1] * 3600 + (int)$dm[2] * 60 + (int)$dm[3] + (int)$dm[4] / 100;
            }
            
            // 비디오 코덱 정보 추출
            $videoCodec = '';
            $videoResolution = '';
            if (preg_match('/Stream\s+#\d+:\d+.*Video:\s*(\w+)/i', $output, $vm)) {
                $videoCodec = strtolower($vm[1]);
            }
            // ★ (2026-11-02 14:38, 44차 점검) 출력 전체에서 처음 나온 WxH 를 읽어 파일 이름('clip_640x360.mp4')의 숫자를 해상도로 썼음 → Video: 가 있는 Stream 줄에서만
            if (preg_match('/Stream\s+#\d+:\d+.*Video:.*?\b(\d{2,5})x(\d{2,5})\b/', $output, $rm)) {
                $videoResolution = $rm[1] . 'x' . $rm[2];
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'audio_tracks' => $audioTracks,
                'encoder' => $this->getHwEncoderInfo(),
                'duration' => $duration,
                'video_codec' => $videoCodec,
                'video_resolution' => $videoResolution,
            ]);
            exit;
        }
        
        // 오디오 트랙 선택
        if (isset($_GET['audio'])) {
            $audioIdx = (int)$_GET['audio'];
            $audioMap = ' -map 0:v:0 -map 0:a:' . max(0, $audioIdx);   // ★ myComix: 음성 순번(0부터)
        } else {
            $audioMap = ' -map 0:v:0 -map 0:a:0';
            // ★ (2026-10-15) 음성 없는 영상 — 탐색기 MMS 와 같게 무음 음성 트랙(받는 쪽 형식이 'avc1 + mp4a' 고정). 이 경로엔 원본 조사가 없어
            //   음성을 고르지 않았을 때만 한 번 조사(ffmpeg -i, 탐색기 MMS 가 늘 하는 것과 같은 조사).
            $silentMap = $this->ffNoAudioSilentMap((string)(shell_exec(escapeshellarg($ffmpeg) . ' -i ' . $inputPath . ' 2>&1') ?? ''));
            if ($silentMap !== '') $audioMap = $silentMap;
        }
        
        // iOS 감지
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isIOS = (bool)preg_match('/iPhone|iPad|iPod/i', $ua);
        
        // HW 인코더 감지 + iOS 호환 프로필
        $videoCodecArgs = $this->detectHwEncoder($ffmpeg);
        $gopArgs = ' -g 48 -keyint_min 48';
        $fpsArg = '';
        
        if ($isIOS) {
            // iOS: 반드시 H.264 High Profile 4.1
            if (strpos($videoCodecArgs, 'libx264') === false) {
                // HW 인코더 사용
                $gopArgs = ' -g 24 -keyint_min 24';
            } else {
                $videoCodecArgs = '-c:v libx264 -preset veryfast -crf 23 -profile:v high -level 4.1 -pix_fmt yuv420p';
                $gopArgs = ' -g 24 -keyint_min 24';
            }
        }
        
        // HW 인코더 시 fps 제한
        if (strpos($videoCodecArgs, 'libx264') === false && strpos($videoCodecArgs, 'copy') === false) {
            $fpsArg = ' -r 30';
        }
        
        // stderr 로그
        $dataDir = self::workDir();   // ★ myComix: cache/_vds
        $stderrLog = $dataDir . '/ffmpeg_share_stderr.log';
        
        // ★ (2026-11-01 01:53, 36차 전체 점검) share.php 가 붙이는 &seek=절대초 를 읽지 않아 공유 MMS 대체 재생이 늘 0초부터 시작(화면 보정값만 앞서 감)
        //   → 탐색기 transcodeStream 과 같게 입력 앞 -ss 로 처리(같은 값 검증)
        $seekSecShareMms = isset($_GET['seek']) ? max(0, (float)$_GET['seek']) : 0;
        $seekArgShareMms = $seekSecShareMms > 0 ? ' -ss ' . $seekSecShareMms : '';
        $buildCmd = function($vCodec, $gop, $fps) use ($ffmpeg, $inputPath, $audioMap, $seekArgShareMms) {
            // ★ (2026-08-24) VA-API 지원 — 미사용 시 빈 문자열이라 종전과 동일.
            $vaDev = $this->vaapiDeviceArg($vCodec);
            $vaVfExpr = $this->encodeVf('', $vCodec);   // ★ (2026-11-02 14:34, 44차 점검) 홀수 크기 영상 — 재인코딩이면 짝수 맞춤(encodeVf 참고)
            $vaVf = $vaVfExpr !== '' ? ' -vf "' . $vaVfExpr . '"' : '';
            return escapeshellarg($ffmpeg)
                . $seekArgShareMms
                . $vaDev
                . ' -analyzeduration 2000000 -probesize 2000000'
                . ' -fflags +genpts+igndts+fastseek'
                . ' -i ' . $inputPath
                . $audioMap
                . ' -sn'
                . $vaVf
                . ' ' . $vCodec
                . $gop
                . $fps
                . ' -c:a aac -b:a 128k -ac 2'
                . ' -output_ts_offset 0'
                . ' -movflags frag_keyframe+empty_moov+default_base_moof'
                . ' -frag_duration 500000'
                . ' -f mp4 pipe:1';
        };
        
        $cmd = $buildCmd($videoCodecArgs, $gopArgs, $fpsArg);
        
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $stderrLog, 'w'],
        ];
        
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            http_response_code(500);
            echo 'Failed to start ffmpeg';
            exit;
        }
        
        fclose($pipes[0]);
        
        // 보안 헤더 제거 (스트리밍 응답에 불필요)
        header_remove('Content-Security-Policy');
        header_remove('X-Content-Type-Options');
        header('Content-Type: video/mp4');
        header('Cache-Control: no-cache, no-store');
        header('Accept-Ranges: none');
        header('X-Accel-Buffering: no');
        
        // 첫 데이터 읽기 (HW 인코더 실패 감지)
        $firstChunk = $this->_readFirstChunkWithTimeout($pipes[1], 6);
        
        if ($firstChunk === false || $firstChunk === '') {
            // HW 실패 → SW fallback
            fclose($pipes[1]);
            @proc_terminate($process, 9);   // ★ (2026-11-11 18:36, 79차 VID-4) 멈춘 HW 인코더를 먼저 끔(탐색기 쪽과 같음) — 종전엔 바로 proc_close 라 멈춘 ffmpeg 가 끝날 때까지 기다려 6초 제한이 듣지 않았음(흉내 재현 40초)
            proc_close($process);
            
            $swCodec = '-c:v libx264 -preset veryfast -crf 23 -profile:v high -level 4.1 -pix_fmt yuv420p';
            $cmd = $buildCmd($swCodec, ' -g 48 -keyint_min 48', '');
            $process = proc_open($cmd, $descriptors, $pipes);
            if (!is_resource($process)) {
                http_response_code(500);
                echo 'Failed to start ffmpeg (SW fallback)';
                exit;
            }
            fclose($pipes[0]);
            $firstChunk = $this->_readFirstChunkWithTimeout($pipes[1], 6);
        }
        
        if ($firstChunk !== false && $firstChunk !== '') {
            echo $firstChunk;
            if (ob_get_level()) ob_flush();
            flush();
        }
        
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 65536);
            if ($chunk !== false && $chunk !== '') {
                echo $chunk;
                if (ob_get_level()) ob_flush();
                flush();
            }
        }
        
        fclose($pipes[1]);
        proc_close($process);
        exit;
    }

    /**
     * ffmpeg stdout 파이프에서 첫 청크를 타임아웃과 함께 읽음.
     * HW 인코더가 초기화에서 hang(데이터도 안 주고 죽지도 않음)할 때 무한 대기를 방지.
     * - 데이터가 오면 즉시 반환 (정상 — 기존 blocking fread와 동일 결과)
     * - ffmpeg가 종료(EOF)되면 '' 반환 (HW 실패 → 호출측 폴백 트리거)
     * - $timeout초 내 아무 데이터도 없으면 '' 반환 (hang → 폴백 트리거, 무한 대기 차단)
     * 반환 후 파이프는 다시 blocking 모드로 복구 (이후 스트리밍 루프는 기존대로 동작).
     */
    private function _readFirstChunkWithTimeout($pipe, int $timeout = 6): string {
        if (!is_resource($pipe)) return '';
        stream_set_blocking($pipe, false);
        $waited = 0.0;
        while ($waited < $timeout) {
            $r = [$pipe]; $w = null; $e = null;
            $sel = @stream_select($r, $w, $e, 1, 0); // 1초 단위
            if ($sel === false) break; // select 오류 → 폴백
            if ($sel > 0) {
                $chunk = fread($pipe, 65536);
                if ($chunk !== false && $chunk !== '') {
                    stream_set_blocking($pipe, true);
                    return $chunk; // 정상: 데이터 반환
                }
                if (feof($pipe)) { // ffmpeg 종료(실패)
                    stream_set_blocking($pipe, true);
                    return '';
                }
            }
            $waited += 1.0;
        }
        stream_set_blocking($pipe, true);
        return ''; // 타임아웃(hang) → 폴백 트리거
    }

    /**
     * HLS 스트리밍 (Jellyfin 방식)
     * start: ffmpeg foreground 실행 → HLS 세그먼트를 디스크에 생성 (long-running 요청)
     * playlist: m3u8 서빙 (세그먼트 URL 치환)
     * segment: .ts 세그먼트 서빙
     * stop: ffmpeg 종료 + 파일 정리
     */
    /**
     * ★ (2026-10-15) 음성 없는 영상용 무음 음성 매핑 — ffmpeg -i 조사 결과에 영상은 있고 음성이 없으면 무음(anullsrc, 48kHz 스테레오)을 두 번째 입력으로
     * 넣고 영상과 함께 매핑(-shortest: 영상 길이에서 끝). 그 밖(음성 있음·조사 실패·영상 없음)은 '' — 호출부는 종전 매핑을 그대로 쓴다.
     * mp4 의 음성 줄은 'Stream #0:1[0x2](eng): Audio: …', mkv 는 'Stream #0:1(eng): Audio: …' — 번호 뒤 ':' 전까지 아무 글자.
     */
    private function ffNoAudioSilentMap(string $probeOut): string {
        if ($probeOut === '' || !preg_match('/Stream #\d+:\d+[^:\n]*: Video:/', $probeOut)) return '';
        if (preg_match('/Stream #\d+:\d+[^:\n]*: Audio:/', $probeOut)) return '';
        return ' -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=48000 -map 0:v:0 -map 1:a:0 -shortest';
    }

    // 현재 사용 중인 인코더 정보 반환
    private function getHwEncoderInfo(): string {
        $dataDir = self::workDir();
        $cacheFile = self::stateDir() . DIRECTORY_SEPARATOR . 'hw_encoder_cache.json';   // ★ fix143
        if (is_file($cacheFile)) {
            $cache = @json_decode(file_get_contents($cacheFile), true);
            if ($cache && isset($cache['encoder']) && (time() - ($cache['time'] ?? 0)) < 86400 && ($cache['fp'] ?? '') === $this->hwCacheFp()) {   // ★ (2026-11-01 11:36, 39차) 설정 지문 비교
                return $cache['encoder'];
            }
        }
        // 캐시 없으면 감지 실행
        $ffmpeg = (mcv_ffmpeg() ?: null);
        if ($ffmpeg) {
            $this->detectHwEncoder($ffmpeg);
            if (is_file($cacheFile)) {
                $cache = @json_decode(file_get_contents($cacheFile), true);
                return $cache['encoder'] ?? 'unknown';
            }
        }
        return 'unknown';
    }
}
}
