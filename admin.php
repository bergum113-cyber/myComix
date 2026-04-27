<?php
// ✅ 테마 0번 (도서관) 미리보기용 축소 배경 (약 29KB)
define('THEME_0_PREVIEW_BG', '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');

// ✅ init.php 사용으로 통일 (자동 로그아웃 등 공통 기능 적용)
require_once __DIR__ . "/bootstrap.php";

// ✅ admin.php는 전체 $base_dirs 기준으로 동작 (bidx 무관)
// 개별 bidx는 사용하지 않음

// ✅ 폴더 목록 AJAX 요청 처리 (특정 bidx의 폴더 탐색)
if (isset($_GET['ajax_folders']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    
    $path = $_GET['path'] ?? '';
    $ajax_bidx = isset($_GET['bidx']) ? (int)$_GET['bidx'] : 0;
    
    // bidx에 따른 base_dir 설정
    if ($ajax_bidx >= 0 && isset($base_dirs[$ajax_bidx])) {
        $ajax_base_dir = $base_dirs[$ajax_bidx];
    } else {
        $ajax_base_dir = $base_dirs[0];
    }
    
    // 빈 경로면 base_dir 사용
    if (empty($path) || $path === '/') {
        $target_dir = $ajax_base_dir;
    } else {
        // ✅ 경로 검증 통일 (validate_file_path 사용)
        $target_dir = validate_file_path($path, $ajax_base_dir);
        if ($target_dir === false) {
            echo json_encode(['folders' => [], 'error' => 'Access denied']);
            exit;
        }
    }
    
    // 디렉토리 존재 확인
    if (!is_dir($target_dir)) {
        echo json_encode(['folders' => [], 'error' => __('adm_dir_not_found')]);
        exit;
    }
    
    $folders = [];
    $items = @scandir($target_dir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '@eaDir') continue;
            if (strpos($item, '.') === 0) continue;
            $item_path = $target_dir . '/' . $item;
            if (is_dir($item_path)) {
                $folders[] = $item;
            }
        }
        natsort($folders);
        $folders = array_values($folders);
    }
    
    echo json_encode(['folders' => $folders, 'current_path' => $path, 'target_dir' => $target_dir]);
    exit;
}

// ✅ 실시간 서버 리소스 AJAX (3초마다 호출)
if (isset($_GET['ajax_resources']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $result = [
        'timestamp' => time(),
        'cpu_usage' => 0,
        'memory_percent' => 0,
        'memory_used' => 0,
        'memory_total' => 0,
        'disk_read' => 0,
        'disk_write' => 0,
        'net_rx' => 0,
        'net_tx' => 0,
        'interfaces' => []
    ];
    
    if ($isWindows) {
        // CPU 사용률
        $cpuLoad = @shell_exec('wmic cpu get loadpercentage /format:csv 2>nul');
        if ($cpuLoad) {
            $lines = array_filter(explode("\n", trim($cpuLoad)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 2) $result['cpu_usage'] = (int)$parts[1];
            }
        }
        
        // 메모리 사용률
        $memInfo = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /format:csv 2>nul');
        if ($memInfo) {
            $lines = array_filter(explode("\n", trim($memInfo)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 3) {
                    $freeKB = (int)$parts[1];
                    $totalKB = (int)$parts[2];
                    $result['memory_total'] = $totalKB * 1024;
                    $result['memory_used'] = ($totalKB - $freeKB) * 1024;
                    $result['memory_percent'] = $totalKB > 0 ? round((($totalKB - $freeKB) / $totalKB) * 100, 1) : 0;
                }
            }
        }
        
        // 디스크 I/O
        $diskIO = @shell_exec('wmic path Win32_PerfRawData_PerfDisk_PhysicalDisk where "Name=\'_Total\'" get DiskReadBytesPersec,DiskWriteBytesPersec /format:csv 2>nul');
        if ($diskIO) {
            $lines = array_filter(explode("\n", trim($diskIO)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 3) {
                    $result['disk_read'] = (int)$parts[1];
                    $result['disk_write'] = (int)$parts[2];
                }
            }
        }
        
        // 네트워크 트래픽
        $trafficInfo = @shell_exec('wmic path Win32_PerfRawData_Tcpip_NetworkInterface get Name,BytesReceivedPersec,BytesSentPersec /format:csv 2>nul');
        if ($trafficInfo) {
            $lines = array_filter(explode("\n", trim($trafficInfo)));
            if (count($lines) > 1) {
                array_shift($lines);
                foreach ($lines as $line) {
                    $parts = str_getcsv($line);
                    if (count($parts) >= 4 && !empty(trim($parts[3]))) {
                        $rx = (int)$parts[1];
                        $tx = (int)$parts[2];
                        $result['net_rx'] += $rx;
                        $result['net_tx'] += $tx;
                        $result['interfaces'][] = ['name' => trim($parts[3]), 'rx' => $rx, 'tx' => $tx];
                    }
                }
            }
        }
    } else {
        // Linux
        $load = @sys_getloadavg();
        $cpuCount = (int)@shell_exec('nproc 2>/dev/null') ?: 1;
        if ($load !== false) $result['cpu_usage'] = min(100, round(($load[0] / $cpuCount) * 100, 1));
        
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s*(\d+)/i', $meminfo, $total);
            preg_match('/MemAvailable:\s*(\d+)/i', $meminfo, $available);
            if (!empty($total[1])) {
                $totalKB = (int)$total[1];
                $availKB = (int)($available[1] ?? 0);
                $result['memory_total'] = $totalKB * 1024;
                $result['memory_used'] = ($totalKB - $availKB) * 1024;
                $result['memory_percent'] = round((($totalKB - $availKB) / $totalKB) * 100, 1);
            }
        }
        
        if (is_readable('/proc/diskstats')) {
            foreach (explode("\n", file_get_contents('/proc/diskstats')) as $line) {
                if (preg_match('/^\s*\d+\s+\d+\s+(sd[a-z]|nvme\d+n\d+)\s+\d+\s+\d+\s+(\d+)\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $m)) {
                    $result['disk_read'] += (int)$m[2] * 512;
                    $result['disk_write'] += (int)$m[3] * 512;
                }
            }
        }
        
        if (is_readable('/proc/net/dev')) {
            foreach (explode("\n", file_get_contents('/proc/net/dev')) as $line) {
                if (preg_match('/^\s*(\w+):\s*(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $m)) {
                    if ($m[1] !== 'lo') {
                        $result['net_rx'] += (int)$m[2];
                        $result['net_tx'] += (int)$m[3];
                        $result['interfaces'][] = ['name' => $m[1], 'rx' => (int)$m[2], 'tx' => (int)$m[3]];
                    }
                }
            }
        }
    }
    
    echo json_encode($result);
    exit;
}

// ✅ 시스템 탭 초기 정보 AJAX (탭 클릭 시 1회)
if (isset($_GET['ajax_init_system']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $result = [
        'cpu' => ['model' => 'Unknown', 'cores' => 0, 'threads' => 0],
        'memory' => ['total' => 0],
        'uptime' => '-',
        'network' => [],
        'webserver' => []
    ];
    
    if ($isWindows) {
        // CPU 정보
        $cpuInfo = @shell_exec('wmic cpu get name,numberofcores,numberoflogicalprocessors /format:csv 2>nul');
        if ($cpuInfo) {
            $lines = array_filter(explode("\n", trim($cpuInfo)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 4) {
                    $result['cpu'] = ['model' => trim($parts[1]), 'cores' => (int)$parts[2], 'threads' => (int)$parts[3]];
                }
            }
        }
        
        // 메모리 총량
        $memInfo = @shell_exec('wmic OS get TotalVisibleMemorySize /format:csv 2>nul');
        if ($memInfo) {
            $lines = array_filter(explode("\n", trim($memInfo)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 2) $result['memory']['total'] = (int)$parts[1] * 1024;
            }
        }
        
        // 업타임
        $uptimeInfo = @shell_exec('wmic os get lastbootuptime /format:csv 2>nul');
        if ($uptimeInfo) {
            $lines = array_filter(explode("\n", trim($uptimeInfo)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 2 && preg_match('/^(\d{14})/', $parts[1], $m)) {
                    $bootTime = strtotime(substr($m[1], 0, 8) . ' ' . substr($m[1], 8, 2) . ':' . substr($m[1], 10, 2) . ':' . substr($m[1], 12, 2));
                    $uptimeSecs = time() - $bootTime;
                    $days = floor($uptimeSecs / 86400);
                    $hours = floor(($uptimeSecs % 86400) / 3600);
                    $mins = floor(($uptimeSecs % 3600) / 60);
                    $result['uptime'] = "{$days}" . __("adm_day") . " {$hours}" . __("adm_hour") . " {$mins}" . __("adm_min");
                }
            }
        }
        
        // 네트워크 인터페이스
        $netInfo = @shell_exec('wmic nic where "NetEnabled=true" get name,speed /format:csv 2>nul');
        if ($netInfo) {
            $lines = array_filter(explode("\n", trim($netInfo)));
            if (count($lines) > 1) {
                array_shift($lines);
                foreach ($lines as $line) {
                    $parts = str_getcsv($line);
                    if (count($parts) >= 3 && !empty(trim($parts[1]))) {
                        $speed = (int)$parts[2];
                        $speedStr = $speed >= 1000000000 ? round($speed/1000000000, 1).' Gbps' : ($speed >= 1000000 ? round($speed/1000000).' Mbps' : $speed.' bps');
                        $result['network'][] = ['name' => trim($parts[1]), 'speed' => $speedStr];
                    }
                }
            }
        }
        
        // 웹서버 프로세스
        foreach (['httpd.exe' => 'Apache', 'nginx.exe' => 'Nginx', 'w3wp.exe' => 'IIS'] as $proc => $name) {
            $info = @shell_exec("wmic process where \"name='{$proc}'\" get processid,workingsetsize /format:csv 2>nul");
            if ($info) {
                $lines = array_filter(explode("\n", trim($info)));
                if (count($lines) > 1) {
                    array_shift($lines);
                    $count = 0; $totalMem = 0;
                    foreach ($lines as $line) {
                        $parts = str_getcsv($line);
                        if (count($parts) >= 3) { $count++; $totalMem += (int)$parts[2]; }
                    }
                    if ($count > 0) $result['webserver'][] = ['name' => $name . " ({$proc})", 'count' => $count, 'memory' => $totalMem];
                }
            }
        }
    } else {
        // Linux CPU
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $model);
            $cores = substr_count($cpuinfo, 'processor');
            $result['cpu'] = ['model' => trim($model[1] ?? 'Unknown'), 'cores' => $cores, 'threads' => $cores];
        }
        
        // Linux 메모리
        if (is_readable('/proc/meminfo')) {
            preg_match('/MemTotal:\s*(\d+)/i', file_get_contents('/proc/meminfo'), $total);
            $result['memory']['total'] = ((int)($total[1] ?? 0)) * 1024;
        }
        
        // Linux 업타임
        if (is_readable('/proc/uptime')) {
            $uptime = (float)file_get_contents('/proc/uptime');
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $mins = floor(($uptime % 3600) / 60);
            $result['uptime'] = "{$days}" . __("adm_day") . " {$hours}" . __("adm_hour") . " {$mins}" . __("adm_min");
        }
    }
    
    echo json_encode($result);
    exit;
}

// ✅ 설정 탭 도구 체크 AJAX (탭 클릭 시 1회)
if (isset($_GET['ajax_check_tools']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    
    $settingsFile = __DIR__ . '/src/app_settings.json';
    $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
    
    $ffprobe_path = $settings['ffprobe_path'] ?? '';
    $vips_path = $settings['vips_path'] ?? '';
    $unrar_path = $settings['unrar_path'] ?? '';
    $sevenzip_path = $settings['sevenzip_path'] ?? '';
    $ffmpeg_path = $settings['ffmpeg_path'] ?? '';
    
    $result = [
        'ffmpeg' => !empty($ffmpeg_path) && is_file($ffmpeg_path),
        'ffprobe' => !empty($ffprobe_path) && is_file($ffprobe_path),
        'vips' => !empty($vips_path) && is_file($vips_path),
        'unrar' => !empty($unrar_path) && is_file($unrar_path),
        'sevenzip' => !empty($sevenzip_path) && is_file($sevenzip_path)
    ];
    
    echo json_encode($result);
    exit;
}

// ✅ 서버 IP/네트워크 상태 AJAX
if (isset($_GET['ajax_server_info']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    
    $result = [
        'private_ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()),
        'public_ip' => null,
        'internet' => false,
        'latency' => null
    ];
    
    // 공인 IP - cURL 사용 (더 안정적)
    $ipServices = [
        'http://ip-api.com/line/?fields=query',  // HTTP 우선 (SSL 문제 회피)
        'http://checkip.amazonaws.com',
        'https://api.ipify.org',
        'https://icanhazip.com',
        'https://ipinfo.io/ip',
        'https://api.ip.sb/ip'
    ];
    
    // cURL 사용 가능하면 cURL로 시도
    if (function_exists('curl_init')) {
        foreach ($ipServices as $service) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $service,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_FOLLOWLOCATION => true
            ]);
            $publicIP = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($publicIP && $httpCode == 200) {
                $publicIP = trim($publicIP);
                if (filter_var($publicIP, FILTER_VALIDATE_IP)) {
                    $result['public_ip'] = $publicIP;
                    $result['internet'] = true;
                    break;
                }
            }
        }
    }
    
    // cURL 실패 시 file_get_contents로 시도
    if (!$result['public_ip']) {
        foreach ($ipServices as $service) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'ignore_errors' => true,
                    'header' => "User-Agent: Mozilla/5.0\r\n"
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            $publicIP = @file_get_contents($service, false, $ctx);
            if ($publicIP) {
                $publicIP = trim($publicIP);
                if (filter_var($publicIP, FILTER_VALIDATE_IP)) {
                    $result['public_ip'] = $publicIP;
                    $result['internet'] = true;
                    break;
                }
            }
        }
    }
    
    // 인터넷 연결 확인 (공인 IP 못 가져와도 연결은 될 수 있음)
    if (!$result['internet']) {
        // 소켓으로 연결 테스트 (더 안정적)
        $testHosts = [
            ['host' => 'www.google.com', 'port' => 80],
            ['host' => 'www.naver.com', 'port' => 80],
            ['host' => '8.8.8.8', 'port' => 53]  // Google DNS
        ];
        
        foreach ($testHosts as $test) {
            $start = microtime(true);
            $socket = @fsockopen($test['host'], $test['port'], $errno, $errstr, 2);
            if ($socket) {
                fclose($socket);
                $result['internet'] = true;
                $result['latency'] = round((microtime(true) - $start) * 1000);
                break;
            }
        }
    } else {
        // 지연시간 측정
        $start = microtime(true);
        $socket = @fsockopen('www.google.com', 80, $errno, $errstr, 2);
        if ($socket) {
            fclose($socket);
            $result['latency'] = round((microtime(true) - $start) * 1000);
        }
    }
    
    echo json_encode($result);
    exit;
}

// ✅ 보안 로그 AJAX 핸들러
if (isset($_GET['ajax_security_logs']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/ip_block.php';
    $ipBlocker = new IPBlocker(__DIR__ . '/src/ip_block_settings.json');
    
    $type = $_GET['type'] ?? 'block'; // block 또는 bruteforce
    $page = max(1, intval($_GET['page'] ?? 1));
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $limit = 20;
    
    if ($type === 'bruteforce') {
        $result = $ipBlocker->getBruteforceLogs($limit, $page, $dateFrom, $dateTo);
    } else {
        $result = $ipBlocker->getBlockLogs($limit, $page, $dateFrom, $dateTo);
    }
    
    echo json_encode($result);
    exit;
}

// ✅ 보안 로그 삭제 AJAX 핸들러
if (isset($_POST['ajax_delete_logs']) && isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    
    // CSRF 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        exit;
    }
    
    require_once __DIR__ . '/ip_block.php';
    $ipBlocker = new IPBlocker(__DIR__ . '/src/ip_block_settings.json');
    
    $type = $_POST['type'] ?? 'block';
    $action = $_POST['action'] ?? '';
    $indices = isset($_POST['indices']) ? json_decode($_POST['indices'], true) : [];
    $dateFrom = $_POST['date_from'] ?? null;
    $dateTo = $_POST['date_to'] ?? null;
    
    $success = false;
    $message = '';
    
    if ($action === 'selected' && !empty($indices)) {
        if ($type === 'bruteforce') {
            $success = $ipBlocker->deleteBruteforceLogsByIndex($indices);
        } else {
            $success = $ipBlocker->deleteBlockLogsByIndex($indices);
        }
        $message = $success ? __('adm_selected_logs_deleted') : __('adm_delete_failed');
    } elseif ($action === 'range' && $dateFrom) {
        if ($type === 'bruteforce') {
            $success = $ipBlocker->deleteBruteforceLogsByDateRange($dateFrom, $dateTo);
        } else {
            $success = $ipBlocker->deleteBlockLogsByDateRange($dateFrom, $dateTo);
        }
        $message = $success ? __('adm_period_logs_deleted') : __('adm_delete_failed');
    } elseif ($action === 'all') {
        if ($type === 'bruteforce') {
            $success = $ipBlocker->clearBruteforceData();
        } else {
            $success = $ipBlocker->clearBlockLogs();
        }
        $message = $success ? __('adm_all_logs_deleted') : __('adm_delete_failed');
    } else {
        $message = __('adm_invalid_request');
    }
    
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// ✅ 활동 로그 기록 함수
function log_activity($action, $detail = '', $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? 'system';
    }
    
    $log_file = __DIR__ . '/src/activity_log.json';
    if (!is_dir(dirname($log_file))) {
        @mkdir(dirname($log_file), 0755, true);
    }
    
    $fp = fopen($log_file, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            $logs = json_decode($content, true) ?? [];
            
            // 최대 5000개 유지
            if (count($logs) >= 5000) {
                $logs = array_slice($logs, -4000);
            }
            
            // 한국 시간 사용
            $kst = new DateTime('now', new DateTimeZone('Asia/Seoul'));
            
            $logs[] = [
                'datetime' => $kst->format('Y-m-d H:i:s'),
                'user_id' => preg_replace('/[^a-zA-Z0-9_]/', '', $user_id),
                'action' => $action,
                'detail' => mb_substr($detail, 0, 500),
                'ip' => filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown'
            ];
            
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

// ✅ CSRF 검증 실패 처리 함수
function csrf_fail_redirect($hash = '') {
    $_SESSION['admin_message'] = "⚠️ " . __("adm_csrf_expired");
    header("Location: " . $_SERVER['PHP_SELF'] . ($hash ? "#$hash" : ""));
    exit;
}

// ============================================================
// ✅ 중복파일 찾기 AJAX 처리 (배치 처리 방식)
// ============================================================

// 중복파일 검색 진행률 조회
if (isset($_GET['action']) && $_GET['action'] === 'duplicate_progress') {
    header('Content-Type: application/json');
    $progress_file = __DIR__ . '/src/.duplicate_progress.json';
    if (file_exists($progress_file)) {
        echo file_get_contents($progress_file);
    } else {
        echo json_encode(['progress' => 0, 'status' => __('adm_status_waiting')]);
    }
    exit;
}

// Step 1: 파일 목록 수집 (배치 처리)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'duplicate_collect') {
    header('Content-Type: application/json');
    set_time_limit(300);
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => __('api_csrf_invalid')]);
        exit;
    }
    
    $scope = $_POST['scope'] ?? 'all';
    $types = $_POST['types'] ?? ['archive'];
    $search_mode = $_POST['search_mode'] ?? 'fast';
    $filename_filter = trim($_POST['filename_filter'] ?? '');
    $same_filename = isset($_POST['same_filename']) && $_POST['same_filename'] === '1';
    
    // 검색할 디렉토리 결정
    $search_dirs = [];
    if ($scope === 'all') {
        $search_dirs = $base_dirs;
    } else {
        $idx = (int)$scope;
        if (isset($base_dirs[$idx])) {
            $search_dirs = [$base_dirs[$idx]];
        }
    }
    
    if (empty($search_dirs)) {
        echo json_encode(['error' => __('adm_no_search_folders')]);
        exit;
    }
    
    // 제외할 시스템 파일 패턴
    $system_file_patterns = [
        '/^\./',                     // 점으로 시작하는 파일 (.folder_cache.json 등)
        '/^Thumbs\.db$/i',           // Windows 썸네일
        '/^desktop\.ini$/i',         // Windows 설정
        '/^\.DS_Store$/i',           // Mac 설정
    ];
    
    // 파일명 필터 패턴 처리
    $filter_patterns = [];
    if (!empty($filename_filter)) {
        $filter_parts = array_map('trim', explode(',', $filename_filter));
        foreach ($filter_parts as $part) {
            if (empty($part)) continue;
            // 와일드카드를 정규표현식으로 변환
            $pattern = '/^' . str_replace(
                ['\*', '\?'],
                ['.*', '.'],
                preg_quote($part, '/')
            ) . '$/i';
            $filter_patterns[] = $pattern;
        }
    }
    
    // 파일 확장자 패턴
    $patterns = [];
    if (in_array('archive', $types)) {
        $patterns = array_merge($patterns, ['zip', 'cbz', 'rar', 'cbr', '7z', 'cb7']);
    }
    if (in_array('video', $types)) {
        $patterns = array_merge($patterns, ['mp4', 'mkv', 'avi', 'wmv', 'mov', 'webm', 'flv']);
    }
    if (in_array('image', $types)) {
        $patterns = array_merge($patterns, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
    }
    if (in_array('json', $types)) {
        $patterns = array_merge($patterns, ['json']);
    }
    
    if (empty($patterns)) {
        echo json_encode(['error' => __('adm_select_file_type')]);
        exit;
    }
    
    // 진행률 파일 초기화
    $progress_file = __DIR__ . '/src/.duplicate_progress.json';
    $data_file = __DIR__ . '/src/.duplicate_data.json';
    file_put_contents($progress_file, json_encode(['progress' => 0, 'status' => __('adm_status_collecting')]));
    
    // 파일 수집
    $files = [];
    $total_dirs = count($search_dirs);
    $dir_idx = 0;
    
    foreach ($search_dirs as $search_dir) {
        if (!is_dir($search_dir)) continue;
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($search_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            $iterator->setMaxDepth(20); // 깊이 제한
            
            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;
                
                $filename = $file->getFilename();
                
                // 시스템 파일 제외
                $is_system = false;
                foreach ($system_file_patterns as $pattern) {
                    if (preg_match($pattern, $filename)) {
                        $is_system = true;
                        break;
                    }
                }
                if ($is_system) continue;
                
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, $patterns)) continue;
                
                // 파일명 필터 적용
                if (!empty($filter_patterns)) {
                    $match = false;
                    foreach ($filter_patterns as $pattern) {
                        if (preg_match($pattern, $filename)) {
                            $match = true;
                            break;
                        }
                    }
                    if (!$match) continue;
                }
                
                try {
                    $size = $file->getSize();
                    $files[] = [
                        'path' => $file->getPathname(),
                        'name' => $filename,
                        'size' => $size,
                        'mtime' => date('Y-m-d H:i', $file->getMTime())
                    ];
                } catch (Exception $e) {
                    continue;
                }
                
                // 메모리 관리: 10000개마다 진행률 업데이트
                if (count($files) % 10000 === 0) {
                    file_put_contents($progress_file, json_encode([
                        'progress' => 5,
                        'status' => __('adm_status_collecting') . ' (' . number_format(count($files)) . ')'
                    ]));
                }
            }
        } catch (Exception $e) {
            continue;
        }
        
        $dir_idx++;
        $pct = (int)(($dir_idx / $total_dirs) * 10);
        file_put_contents($progress_file, json_encode([
            'progress' => $pct,
            'status' => __('adm_status_collecting') . ' (' . number_format(count($files)) . ')'
        ]));
    }
    
    // 크기별 그룹화 - 같은 크기 파일만 후보로
    $size_groups = [];
    foreach ($files as $idx => $file) {
        $size_groups[$file['size']][] = $idx;
    }
    
    // 같은 크기 파일이 2개 이상인 것만 후보로
    $candidates = [];
    foreach ($size_groups as $size => $indices) {
        if (count($indices) >= 2) {
            foreach ($indices as $idx) {
                $candidates[] = $files[$idx];
            }
        }
    }
    
    // 임시 파일에 저장 (search_mode, same_filename 포함)
    file_put_contents($data_file, json_encode([
        'total_files' => count($files),
        'candidates' => $candidates,
        'processed' => 0,
        'hash_map' => [],
        'search_mode' => $search_mode,
        'same_filename' => $same_filename
    ]));
    
    file_put_contents($progress_file, json_encode([
        'progress' => 15,
        'status' => __('adm_status_collected') . ': ' . number_format(count($files)) . ', ' . __('adm_candidates') . ': ' . number_format(count($candidates)) . ''
    ]));
    
    echo json_encode([
        'success' => true,
        'total_files' => count($files),
        'candidates' => count($candidates)
    ]);
    exit;
}

// Step 2: 해시 계산 (배치 처리)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'duplicate_process') {
    header('Content-Type: application/json');
    set_time_limit(120);
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => __('api_csrf_invalid')]);
        exit;
    }
    
    $data_file = __DIR__ . '/src/.duplicate_data.json';
    $progress_file = __DIR__ . '/src/.duplicate_progress.json';
    
    if (!file_exists($data_file)) {
        echo json_encode(['error' => __('adm_no_search_data')]);
        exit;
    }
    
    $data = json_decode(file_get_contents($data_file), true);
    $candidates = $data['candidates'] ?? [];
    $processed = $data['processed'] ?? 0;
    $hash_map = $data['hash_map'] ?? [];
    $search_mode = $data['search_mode'] ?? 'fast';
    $same_filename = $data['same_filename'] ?? false;
    
    // 정밀 모드는 느리므로 배치 크기 줄임
    $batch_size = ($search_mode === 'precise') ? 100 : 500;
    $total = count($candidates);
    
    if ($processed >= $total) {
        // 처리 완료 - 중복 그룹 생성
        $duplicates = [];
        foreach ($hash_map as $key => $files) {
            if (count($files) >= 2) {
                if ($search_mode === 'precise') {
                    // 정밀 모드: 용량|파일명|해시
                    $parts = explode('|', $key, 3);
                    $size = (int)$parts[0];
                    $filename = $parts[1] ?? '';
                    $hash = $parts[2] ?? '';
                    $duplicates[] = [
                        'hash' => $hash,
                        'filename' => $filename,
                        'size' => $size,
                        'size_formatted' => format_size($size),
                        'files' => $files
                    ];
                } else {
                    // 빠른 모드: 부분 해시가 같은 파일들을 전체 해시로 2차 검증
                    $parts = explode('|', $key, 2);
                    $size = (int)$parts[0];
                    $partial_hash = $parts[1] ?? '';
                    
                    // 전체 해시로 다시 그룹화
                    $full_hash_groups = [];
                    foreach ($files as $file) {
                        $full_hash = @md5_file($file['path']);
                        if ($full_hash) {
                            if (!isset($full_hash_groups[$full_hash])) {
                                $full_hash_groups[$full_hash] = [];
                            }
                            $full_hash_groups[$full_hash][] = $file;
                        }
                    }
                    
                    // 전체 해시도 같은 것만 중복으로 처리
                    foreach ($full_hash_groups as $full_hash => $verified_files) {
                        if (count($verified_files) >= 2) {
                            // same_filename 옵션: 파일명도 같아야 중복
                            if ($same_filename) {
                                // 파일명별로 다시 그룹화
                                $name_groups = [];
                                foreach ($verified_files as $vf) {
                                    $fname = $vf['name'] ?? basename($vf['path']);
                                    if (!isset($name_groups[$fname])) {
                                        $name_groups[$fname] = [];
                                    }
                                    $name_groups[$fname][] = $vf;
                                }
                                foreach ($name_groups as $fname => $name_files) {
                                    if (count($name_files) >= 2) {
                                        $duplicates[] = [
                                            'hash' => $full_hash,
                                            'filename' => $fname,
                                            'size' => $size,
                                            'size_formatted' => format_size($size),
                                            'files' => $name_files
                                        ];
                                    }
                                }
                            } else {
                                $duplicates[] = [
                                    'hash' => $full_hash,
                                    'filename' => '',
                                    'size' => $size,
                                    'size_formatted' => format_size($size),
                                    'files' => $verified_files
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        // 크기 기준 내림차순 정렬
        usort($duplicates, fn($a, $b) => $b['size'] - $a['size']);
        
        $duplicate_files = array_sum(array_map(fn($g) => count($g['files']) - 1, $duplicates));
        $saveable_size = array_sum(array_map(fn($g) => $g['size'] * (count($g['files']) - 1), $duplicates));
        
        // 임시 파일 삭제
        @unlink($data_file);
        @unlink($progress_file);
        
        echo json_encode([
            'done' => true,
            'total_files' => $data['total_files'],
            'duplicate_groups' => count($duplicates),
            'duplicate_files' => $duplicate_files,
            'saveable_size' => format_size($saveable_size),
            'groups' => $duplicates,
            'search_mode' => $search_mode
        ]);
        exit;
    }
    
    // 배치 처리
    $end = min($processed + $batch_size, $total);
    
    for ($i = $processed; $i < $end; $i++) {
        $file = $candidates[$i];
        
        try {
            if ($search_mode === 'precise') {
                // 정밀 모드: 전체 MD5 + 파일명
                $hash = @md5_file($file['path']);
                if ($hash) {
                    $filename = $file['name'] ?? basename($file['path']);
                    $key = $file['size'] . '|' . $filename . '|' . $hash;
                    if (!isset($hash_map[$key])) {
                        $hash_map[$key] = [];
                    }
                    $hash_map[$key][] = $file;
                }
            } else {
                // 빠른 모드: 부분 MD5만
                $hash = md5_file_partial($file['path']);
                if ($hash) {
                    $key = $file['size'] . '|' . $hash;
                    if (!isset($hash_map[$key])) {
                        $hash_map[$key] = [];
                    }
                    $hash_map[$key][] = $file;
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    // 진행률 업데이트
    $pct = 15 + (int)(($end / $total) * 85);
    $mode_label = ($search_mode === 'precise') ? __('adm_scan_precise') : __('adm_scan_quick');
    file_put_contents($progress_file, json_encode([
        'progress' => $pct,
        'status' => $mode_label . ' ... (' . number_format($end) . '/' . number_format($total) . ')'
    ]));
    
    // 데이터 저장
    $data['processed'] = $end;
    $data['hash_map'] = $hash_map;
    file_put_contents($data_file, json_encode($data));
    
    echo json_encode([
        'done' => false,
        'processed' => $end,
        'total' => $total,
        'progress' => $pct
    ]);
    exit;
}

// 검색 취소/정리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'duplicate_cancel') {
    header('Content-Type: application/json');
    @unlink(__DIR__ . '/src/.duplicate_data.json');
    @unlink(__DIR__ . '/src/.duplicate_progress.json');
    echo json_encode(['success' => true]);
    exit;
}

// 중복파일 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_duplicates') {
    header('Content-Type: application/json');
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => __('api_csrf_invalid')]);
        exit;
    }
    
    $files_to_delete = $_POST['files'] ?? [];
    $deleted = 0;
    $affected_dirs = [];  // 캐시 무효화할 폴더들
    
    foreach ($files_to_delete as $file) {
        // 경로 검증
        $valid = false;
        foreach ($base_dirs as $bd) {
            $real_base = realpath($bd);
            $real_file = realpath($file);
            if ($real_file && $real_base && strpos($real_file, $real_base) === 0) {
                $valid = true;
                break;
            }
        }
        
        if ($valid && is_file($file)) {
            $dir = dirname($file);
            if (@unlink($file)) {
                $deleted++;
                $affected_dirs[$dir] = true;  // 영향받은 폴더 기록
                log_user_activity('중복파일삭제', basename($file));
            }
        }
    }
    
    // 영향받은 폴더의 캐시 무효화
    foreach (array_keys($affected_dirs) as $dir) {
        $cache_file = $dir . '/.folder_cache.json';
        if (is_file($cache_file)) {
            @unlink($cache_file);
        }
    }
    
    // zip_total 캐시도 무효화 (다음 접근 시 재계산)
    foreach (array_keys($base_dirs) as $idx) {
        $total_file = __DIR__ . '/src/zip_total_' . $idx . '.json';
        if (is_file($total_file)) {
            @unlink($total_file);
        }
    }
    
    echo json_encode(['success' => true, 'deleted' => $deleted]);
    exit;
}

/**
 * 파일의 부분 MD5 해시 계산 (빠른 비교용)
 * 파일 앞 1MB + 뒤 1MB 해시
 */
function md5_file_partial($filepath) {
    if (!is_file($filepath) || !is_readable($filepath)) return null;
    
    $size = filesize($filepath);
    $chunk_size = 1024 * 1024; // 1MB
    
    $fp = @fopen($filepath, 'rb');
    if (!$fp) return null;
    
    $hash_data = '';
    
    // 앞부분
    $hash_data .= fread($fp, min($chunk_size, $size));
    
    // 뒷부분 (파일이 충분히 크면)
    if ($size > $chunk_size * 2) {
        fseek($fp, -$chunk_size, SEEK_END);
        $hash_data .= fread($fp, $chunk_size);
    }
    
    fclose($fp);
    
    return md5($hash_data);
}

/**
 * 파일 크기 포맷
 */
function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// ✅ config_change POST 처리 (JSON 설정 파일 기반)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'config_change') {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('config');
    }
    $settings_file = __DIR__ . '/src/app_settings.json';
    
    // 기존 설정 로드
    $settings = [];
    if (file_exists($settings_file)) {
        $settings = json_decode(file_get_contents($settings_file), true) ?? [];
    }
    
    // 다중 폴더 처리
    $base_dirs_input = $_POST['base_dirs'] ?? [];
    $base_dirs = [];
    foreach ($base_dirs_input as $dir) {
        $dir = trim($dir);
        $dir = str_replace(['..', '<', '>', '"', "'"], '', $dir);
        if (!empty($dir)) {
            $base_dirs[] = $dir;
        }
    }
    $settings['base_dirs'] = $base_dirs;
    
    // 뷰어 설정
    $settings['maxview_folder'] = (string)max(1, (int)($_POST['maxview_folder'] ?? 30));
    $settings['maxview_file'] = (string)max(1, (int)($_POST['maxview_file'] ?? 99999));
    $settings['maxview_folder_mobile'] = (string)max(1, (int)($_POST['maxview_folder_mobile'] ?? 30));
    $settings['maxview_file_mobile'] = (string)max(1, (int)($_POST['maxview_file_mobile'] ?? 30));
    $settings['pages_per_group'] = (string)max(1, (int)($_POST['pages_per_group'] ?? 10));
    $settings['pages_per_group_mobile'] = (string)max(1, (int)($_POST['pages_per_group_mobile'] ?? 5));
    $settings['max_autosave'] = (string)max(1, (int)($_POST['max_autosave'] ?? 10));
    $settings['max_bookmark'] = (string)max(1, (int)($_POST['max_bookmark'] ?? 10));
    $settings['max_favorites'] = (string)max(1, (int)($_POST['max_favorites'] ?? 50));
    $settings['new_badge_hours'] = (string)max(0, (int)($_POST['new_badge_hours'] ?? 24));
    $settings['use_cover'] = ($_POST['use_cover'] ?? 'n') === 'y' ? 'y' : 'n';
    $settings['use_listcover'] = ($_POST['use_listcover'] ?? 'n') === 'y' ? 'y' : 'n';
    
    // 외부 도구 경로
    $sanitize_path = function($path) {
        return str_replace(['<', '>', '"', "'", ';', '&', '|'], '', trim($path));
    };
    $settings['ffmpeg_path'] = $sanitize_path($_POST['ffmpeg_path'] ?? '');
    $settings['ffprobe_path'] = $sanitize_path($_POST['ffprobe_path'] ?? '');
    $settings['vips_path'] = $sanitize_path($_POST['vips_path'] ?? '');
    $settings['unrar_path'] = $sanitize_path($_POST['unrar_path'] ?? '');
    $settings['sevenzip_path'] = $sanitize_path($_POST['sevenzip_path'] ?? '');
    
    // 폴더 표시 설정
    $settings['imgfolder_threshold'] = max(1, (int)($_POST['imgfolder_threshold'] ?? 5));
    $settings['video_folder_as_dir'] = isset($_POST['video_folder_as_dir']);
    
    // 다크모드 설정
    $settings['darkmode'] = [
        'enabled' => isset($_POST['darkmode_enabled']),
        'default' => in_array($_POST['darkmode_default'] ?? 'light', ['light', 'dark']) 
                     ? $_POST['darkmode_default'] : 'light'
    ];
    
    // 자동 로그아웃 설정
    $auto_logout_enabled = isset($_POST['auto_logout_enabled']);
    $allowed_pages = ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'admin.php', 'admin_translations.php', 'bookmark.php', 'blank.php'];
    $selected_pages = [];
    if (isset($_POST['auto_logout_pages']) && is_array($_POST['auto_logout_pages'])) {
        foreach ($_POST['auto_logout_pages'] as $page) {
            if (in_array($page, $allowed_pages)) {
                $selected_pages[] = $page;
            }
        }
    }
    
    // ✅ 비활성화 시 기존 페이지 목록 유지 (disabled 속성으로 인해 전송되지 않는 문제 해결)
    if (!$auto_logout_enabled && empty($selected_pages)) {
        $selected_pages = $settings['auto_logout']['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
    }
    // 페이지 선택 안 했으면 기본값 사용
    if (empty($selected_pages)) {
        $selected_pages = ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
    }
    
    $settings['auto_logout'] = [
        'enabled' => $auto_logout_enabled,
        'timeout' => max(60, min(7200, (int)($_POST['auto_logout_timeout'] ?? 600))), // 1분~2시간
        'pages' => $selected_pages
    ];
    
    // ✅ 모든 기기에서 로그아웃 설정
    $settings['logout_all_devices'] = [
        'enabled' => isset($_POST['logout_all_devices_enabled'])
    ];
    
    // TXT 뷰어 설정
    $settings['txt_viewer'] = [
        'enabled' => isset($_POST['txt_viewer_enabled']),
        'chunk_size' => max(10240, (int)($_POST['txt_chunk_size'] ?? 102400)),
        'default_font_size' => max(10, min(40, (int)($_POST['txt_font_size'] ?? 18))),
        'default_line_height' => max(1.0, min(3.0, (float)($_POST['txt_line_height'] ?? 1.8))),
        'font_name' => trim($_POST['txt_font_name'] ?? ''),
        'font_url' => filter_var(trim($_POST['txt_font_url'] ?? ''), FILTER_SANITIZE_URL),
        'font_local' => trim(str_replace(['..', '<', '>', '"'], '', $_POST['txt_font_local'] ?? ''))
    ];
    
    // EPUB 뷰어 설정
    $settings['epub_viewer'] = [
        'enabled' => isset($_POST['epub_viewer_enabled']),
        'default_font_size' => max(50, min(200, (int)($_POST['epub_font_size'] ?? 100))),
        'default_theme' => in_array($_POST['epub_theme'] ?? 'light', ['light', 'sepia', 'dark'])
                          ? $_POST['epub_theme'] : 'light',
        'font_name' => trim($_POST['epub_font_name'] ?? ''),
        'font_url' => filter_var(trim($_POST['epub_font_url'] ?? ''), FILTER_SANITIZE_URL),
        'font_local' => trim(str_replace(['..', '<', '>', '"'], '', $_POST['epub_font_local'] ?? ''))
    ];
    
    // Privacy shield settings
    $privacy_shield_enabled = isset($_POST['privacy_shield_enabled']);
    $privacy_allowed_pages = ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php'];
    $privacy_selected_pages = [];
    if (isset($_POST['privacy_shield_pages']) && is_array($_POST['privacy_shield_pages'])) {
        foreach ($_POST['privacy_shield_pages'] as $page) {
            if (in_array($page, $privacy_allowed_pages)) {
                $privacy_selected_pages[] = $page;
            }
        }
    }
    
    // ✅ 비활성화 시 기존 페이지 목록 유지 (disabled 속성으로 인해 전송되지 않는 문제 해결)
    if (!$privacy_shield_enabled && empty($privacy_selected_pages)) {
        $privacy_selected_pages = $settings['privacy_shield']['pages'] ?? ['index.php', 'viewer.php'];
    }
    // 페이지 선택 안 했으면 기본값 사용
    if (empty($privacy_selected_pages)) {
        $privacy_selected_pages = ['index.php', 'viewer.php'];
    }
    
    $settings['privacy_shield'] = [
        'enabled' => $privacy_shield_enabled,
        'pages' => $privacy_selected_pages,
        'debug' => isset($_POST['privacy_shield_debug'])
    ];
    
    // List font settings
    $settings['list_font'] = [
        'font_name' => trim($_POST['list_font_name'] ?? ''),
        'font_url' => filter_var(trim($_POST['list_font_url'] ?? ''), FILTER_SANITIZE_URL),
        'font_local' => trim(str_replace(['..', '<', '>', '"'], '', $_POST['list_font_local'] ?? '')),
        'font_size' => max(12, min(40, (int)($_POST['list_font_size'] ?? 22)))
    ];
    
    // ✅ 회원 설정
    $settings['registration'] = [
        'enabled' => isset($_POST['registration_enabled']),
        'require_approval' => isset($_POST['registration_require_approval']),
        'find_id_enabled' => isset($_POST['find_id_enabled']),
        'find_password_enabled' => isset($_POST['find_password_enabled'])
    ];
    
    // src 폴더 생성
    if (!is_dir(__DIR__ . '/src')) {
        @mkdir(__DIR__ . '/src', 0755, true);
    }
    
    // JSON 파일로 저장 (파일 잠금 적용)
    $fp = fopen($settings_file, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
    
    // OPcache 무효화
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate(__DIR__ . '/config.php', true);
    }
    
    clearstatcache(true);
    
    $_SESSION['admin_message'] = __('admin_settings_saved');
    header("Location: " . $_SERVER['PHP_SELF'] . "#config");
    exit;
}

// ✅ 캐시 초기화 처리
if (isset($_SESSION['user_group']) && trim($_SESSION['user_group']) === "admin") {
    // OPcache 초기화
    if (isset($_GET['clear_opcache']) && function_exists('opcache_reset')) {
        opcache_reset();
        $_SESSION['admin_message'] = __("adm_opcache_cleared");
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "#system");
        exit;
    }
    // APCu 캐시 초기화
    if (isset($_GET['clear_apcu']) && function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
        $_SESSION['admin_message'] = __("adm_apcu_cleared");
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "#system");
        exit;
    }
}

if(isset($_SESSION['user_group']) && trim($_SESSION['user_group']) === "admin"){

$permissions_file = "./src/folder_permissions.json";
$theme_file = "./src/login_theme.json";
$activity_log_file = "./src/activity_log.json";

// ✅ load_permissions(), save_permissions()는 function.php에서 통합 제공

// 로그인 테마 로드
function load_theme($file) {
    if (!file_exists($file)) return ['theme' => 0, 'backgrounds' => [], 'filters' => []];
    $data = load_json_with_lock($file);
    if (!is_array($data)) $data = ['theme' => 0];
    if (!isset($data['backgrounds'])) $data['backgrounds'] = [];
    if (!isset($data['filters'])) $data['filters'] = [];
    return $data;
}

// 로그인 테마 저장
function save_theme($file, $theme, $backgrounds = null, $filters = null) {
    $data = load_theme($file);
    $data['theme'] = $theme;
    if ($backgrounds !== null) $data['backgrounds'] = $backgrounds;
    if ($filters !== null) $data['filters'] = $filters;
    return save_json_with_lock($file, $data);
}

// ✅ 브랜딩 설정은 function.php의 load_branding() 사용

// ✅ 브랜딩 설정 저장 (파일 잠금 적용)
function save_branding($data, $file = './src/branding.json') {
    return save_json_with_lock($file, $data);
}

// 폴더 권한 동기화 함수
function sync_folder_permissions($base_dir, $permissions_file) {
    $permissions = load_permissions($permissions_file);
    $actual_folders = [];
    $changed = false;
    
    if (is_dir($base_dir)) {
        foreach (new DirectoryIterator($base_dir) as $f) {
            if ($f->isDot() || $f->getFilename() == "@eaDir" || !$f->isDir()) continue;
            $actual_folders[] = $f->getFilename();
        }
    }
    
    foreach ($actual_folders as $folder) {
        if (!isset($permissions[$folder])) {
            $permissions[$folder] = [
                'admin' => 1,
                'group1' => 1,
                'group2' => 1,
                'group3' => 1,
                'group4' => 1
            ];
            $changed = true;
        }
    }
    
    foreach (array_keys($permissions) as $folder) {
        if (!in_array($folder, $actual_folders)) {
            unset($permissions[$folder]);
            $changed = true;
        }
    }
    
    if ($changed) {
        save_permissions($permissions_file, $permissions);
    }
    
    return $permissions;
}

// ✅ 전체 base_dirs에 대해 폴더 권한 동기화
foreach ($base_dirs as $bd) {
    sync_folder_permissions($bd, $permissions_file);
}

// ✅ 통계 함수 - 전체 base_dirs 기준
function get_system_stats_fast($permissions_file, $base_dirs) {
    $stats = ['total_folders' => 0, 'zip_total' => 0, 'user_count' => 0, 'translation_count' => 0];
    
    // 전체 폴더 수 & 작품 수: 모든 bidx의 zip_total_X.json 합산
    for ($i = 0; $i < count($base_dirs); $i++) {
        $tf = __DIR__ . '/src/zip_total_' . $i . '.json';
        if (file_exists($tf)) {
            $data = json_decode(file_get_contents($tf), true);
            $stats['zip_total'] += (int)($data['zip_total'] ?? 0);
            $stats['total_folders'] += (int)($data['folder_total'] ?? 0);
        }
    }
    // 기존 단일 파일도 확인 (하위 호환)
    $tf_legacy = __DIR__ . '/src/zip_total.json';
    if (file_exists($tf_legacy) && $stats['zip_total'] == 0) {
        $data = json_decode(file_get_contents($tf_legacy), true);
        $stats['zip_total'] = (int)($data['zip_total'] ?? 0);
        $stats['total_folders'] = (int)($data['folder_total'] ?? 0);
    }
    
    // ✅ users.json 사용 (순수 JSON)
    if (users_file_exists()) {
        $ua = load_users();
        $stats['user_count'] = count($ua);
    }
    
    $trf = "./src/search_translations.json";
    if (file_exists($trf)) {
        $stats['translation_count'] = count(json_decode(file_get_contents($trf), true) ?? []);
    }
    
    return $stats;
}

$message = '';
// 세션에서 메시지 가져오기 (PRG 패턴)
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}

$theme_data = load_theme($theme_file);
$current_theme = $theme_data['theme'] ?? 1;
$theme_backgrounds = $theme_data['backgrounds'] ?? [];
$theme_filters = $theme_data['filters'] ?? [];
$branding = load_branding();

// 기본 배경 URL
$default_backgrounds = [
    2 => 'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=1200',
    4 => 'https://images.unsplash.com/photo-1462331940025-496dfbfc7564?w=1200',
    6 => 'https://images.unsplash.com/photo-1478760329108-5c3ed9d495a0?w=1200'
];

// 테마 변경 처리
if (isset($_POST['mode']) && $_POST['mode'] === "theme_change") {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('theme');
    }
    $valid_themes = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25];
    $new_theme = (int)($_POST['login_theme'] ?? 0);
    if (in_array($new_theme, $valid_themes)) {
        save_theme($theme_file, $new_theme, $theme_backgrounds, $theme_filters);
        $_SESSION['admin_message'] = __("adm_theme_changed");
        header("Location: " . $_SERVER['PHP_SELF'] . "#theme");
        exit;
    }

// 배경 필터 변경 처리
} elseif (isset($_POST['mode']) && $_POST['mode'] === "background_filter_change") {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('theme');
    }
    $bg_theme = (int)($_POST['bg_theme'] ?? 0);
    $bg_filter = $_POST['bg_filter'] ?? 'none';
    $valid_filters = ['none', 'blur', 'grayscale', 'sepia', 'brightness', 'contrast', 'saturate', 'invert', 'hue-rotate', 'blur-grayscale', 'blur-sepia', 'brightness-contrast', 'vintage', 'cool', 'warm'];
    
    if ($bg_theme >= 0 && $bg_theme <= 25 && in_array($bg_filter, $valid_filters)) {
        if ($bg_filter === 'none') {
            unset($theme_filters[(string)$bg_theme]);
        } else {
            $theme_filters[(string)$bg_theme] = $bg_filter;
        }
        save_theme($theme_file, $current_theme, $theme_backgrounds, $theme_filters);
        $_SESSION['admin_message'] = __("adm_theme_filter_changed", $bg_theme);
        header("Location: " . $_SERVER['PHP_SELF'] . "#theme");
        exit;
    }

// 배경 URL 변경 처리
} elseif (isset($_POST['mode']) && $_POST['mode'] === "background_url_change") {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('theme');
    }
    $bg_theme = (int)($_POST['bg_theme'] ?? 0);
    $bg_url = trim($_POST['bg_url'] ?? '');
    
    if ($bg_theme >= 0 && $bg_theme <= 25 && !empty($bg_url)) {
        $theme_backgrounds[(string)$bg_theme] = $bg_url;
        save_theme($theme_file, $current_theme, $theme_backgrounds, $theme_filters);
        $_SESSION['admin_message'] = __("adm_theme_bg_url_changed", $bg_theme);
        header("Location: " . $_SERVER['PHP_SELF'] . "#theme");
        exit;
    } elseif ($bg_theme >= 0 && empty($bg_url)) {
        $message = __("adm_enter_url");
    }

// 배경 파일 업로드 처리
} elseif (isset($_POST['mode']) && $_POST['mode'] === "background_file_change") {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('theme');
    }
    $bg_theme = (int)($_POST['bg_theme'] ?? 0);
    if ($bg_theme >= 0 && $bg_theme <= 25 && isset($_FILES['bg_file']) && $_FILES['bg_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = './src/backgrounds/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        // ✅ MIME 타입 검증 추가 (보안 강화)
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['bg_file']['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_mimes)) {
            $message = __("adm_invalid_image_file");
        } else {
            // MIME → 확장자 매핑
            $mime_to_ext = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];
            $ext = $mime_to_ext[$mime] ?? 'jpg';
            
            $filename = 'theme_' . $bg_theme . '_bg.' . $ext;
            $filepath = $upload_dir . $filename;
            
            // 기존 파일 삭제
            foreach (glob($upload_dir . 'theme_' . $bg_theme . '_bg.*') as $old) {
                @unlink($old);
            }
            
            if (move_uploaded_file($_FILES['bg_file']['tmp_name'], $filepath)) {
                $theme_backgrounds[(string)$bg_theme] = $filepath;
                save_theme($theme_file, $current_theme, $theme_backgrounds, $theme_filters);
                $_SESSION['admin_message'] = __("adm_theme_bg_file_changed", $bg_theme);
                header("Location: " . $_SERVER['PHP_SELF'] . "#theme");
                exit;
            } else {
                $message = __("adm_upload_failed");
            }
        }
    } else {
        $message = __("adm_select_file");
    }

// 배경 초기화 처리
} elseif (isset($_POST['mode']) && $_POST['mode'] === "background_reset") {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('theme');
    }
    $bg_theme = (int)($_POST['bg_theme'] ?? 0);
    if ($bg_theme >= 0 && $bg_theme <= 25) {
        // 업로드된 파일 삭제
        foreach (glob('./src/backgrounds/theme_' . $bg_theme . '_bg.*') as $old) {
            @unlink($old);
        }
        unset($theme_backgrounds[(string)$bg_theme]);
        save_theme($theme_file, $current_theme, $theme_backgrounds, $theme_filters);
        $_SESSION['admin_message'] = __("adm_theme_bg_reset", $bg_theme);
        header("Location: " . $_SERVER['PHP_SELF'] . "#theme");
        exit;
    }

} elseif (isset($_POST['mode']) && $_POST['mode'] === "mode_change") {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('folder');
    }
    // 폴더 권한 저장 시 해당 bidx의 base_dir 사용
    $target_bidx = isset($_GET['bidx']) ? (int)$_GET['bidx'] : 0;
    if ($target_bidx < 0 || !isset($base_dirs[$target_bidx])) $target_bidx = 0;
    $target_base_dir = $base_dirs[$target_bidx];
    
    $permissions = load_permissions($permissions_file);
    foreach (new DirectoryIterator($target_base_dir) as $fi) {
        if ($fi->isDot() || $fi == "@eaDir" || !$fi->isDir()) continue;
        $fn = $fi->getFilename();
        $en = encode_url($fn);
        $permissions[$fn] = [
            'admin' => 1,
            'group1' => (int)($_POST[$en."_group1"] ?? 0) === 1 ? 1 : 0,
            'group2' => (int)($_POST[$en."_group2"] ?? 0) === 1 ? 1 : 0,
            'group3' => (int)($_POST[$en."_group3"] ?? 0) === 1 ? 1 : 0,
            'group4' => (int)($_POST[$en."_group4"] ?? 0) === 1 ? 1 : 0
        ];
        $old = $target_base_dir."/".$fn.".json";
        if (file_exists($old)) unlink($old);
    }
    save_permissions($permissions_file, $permissions);
    $_SESSION['admin_message'] = __("adm_folder_perm_updated");
    header("Location: " . $_SERVER['PHP_SELF'] . "#folder");
    exit;

} elseif (($_POST['mode'] ?? '') === "group_change") {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('group');
    }
    // ✅ users.json 사용 (순수 JSON)
    $ua = load_users();
    $original_ua = $ua; // 원본 복사 (삭제 전 정보 보관용)
    $deleted_users = []; // 삭제된 사용자 목록
    
    // 로그인 기록, 활동 로그 미리 로드
    $all_login_logs = [];
    $login_log_file = __DIR__ . '/src/login_log.json';
    if (file_exists($login_log_file)) {
        $all_login_logs = json_decode(file_get_contents($login_log_file), true) ?? [];
    }
    $all_activity_logs = [];
    $activity_log_file = __DIR__ . '/src/activity_log.json';
    if (file_exists($activity_log_file)) {
        $all_activity_logs = json_decode(file_get_contents($activity_log_file), true) ?? [];
    }
    
    foreach (array_keys($ua) as $uid) {
        $sid = preg_replace('/[^a-zA-Z0-9_]/', '', $uid);
        $gv = $_POST[$sid."_group"] ?? '';
        if ($gv === "delete") {
            // ✅ 관리자는 삭제 불가
            if (($ua[$uid]['group'] ?? '') === 'admin') {
                continue; // 관리자 삭제 시도 무시
            }
            $deleted_users[] = $uid; // 삭제 목록에 추가
            unset($ua[$uid]);
            
            // ✅ 해당 사용자의 모든 관련 파일 삭제
            $user_files = [
                "./src/".$sid."_bookmark.json",
                "./src/".$sid."_autosave.json",
                "./src/".$sid."_favorites.json",
                "./src/".$sid."_recent.json",
                "./src/".$sid."_epub_progress.json",
                "./src/".$sid."_txt_progress.json",
            ];
            foreach ($user_files as $file) {
                @unlink($file);
            }
            
            // ✅ 자동 로그인 토큰 삭제 (모든 기기)
            if (function_exists('delete_user_remember_tokens')) {
                delete_user_remember_tokens($uid);
            }
            
            // ✅ 2FA/TOTP 설정 삭제
            if (function_exists('delete_user_totp')) {
                delete_user_totp($uid);
            }
        } elseif (in_array($gv, ['admin','group1','group2','group3','group4'])) {
            $ua[$uid]['group'] = $gv;
        }
    }
    
    // ✅ 삭제된 사용자들 정보 백업
    if (!empty($deleted_users)) {
        $deleted_file = __DIR__ . '/src/deleted_users.json';
        $deleted_data = [];
        if (file_exists($deleted_file)) {
            $deleted_data = json_decode(file_get_contents($deleted_file), true) ?? [];
        }
        
        foreach ($deleted_users as $del_uid) {
            $user_info = $original_ua[$del_uid] ?? [];
            unset($user_info['pass']); // 비밀번호는 제외
            
            // 해당 사용자의 로그인 기록
            $user_login_logs = array_values(array_filter($all_login_logs, function($log) use ($del_uid) {
                return ($log['user_id'] ?? '') === $del_uid;
            }));
            
            // 해당 사용자의 활동 로그
            $user_activity_logs = array_values(array_filter($all_activity_logs, function($log) use ($del_uid) {
                return ($log['user_id'] ?? '') === $del_uid;
            }));
            
            $deleted_data[] = [
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => 'admin',
                'user_id' => $del_uid,
                'user_info' => $user_info,
                'login_logs' => $user_login_logs,
                'activity_logs' => $user_activity_logs
            ];
        }
        
        file_put_contents($deleted_file, json_encode($deleted_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    
    save_users($ua);
    
    // ✅ 삭제된 사용자들의 로그인 기록 삭제
    if (!empty($deleted_users)) {
        $log_file = __DIR__ . '/src/login_log.json';
        if (file_exists($log_file)) {
            $fp = fopen($log_file, 'c+');
            if ($fp && flock($fp, LOCK_EX)) {
                $content = stream_get_contents($fp);
                $logs = json_decode($content, true) ?? [];
                
                // 삭제된 사용자들의 로그 제거
                $logs = array_filter($logs, function($log) use ($deleted_users) {
                    return !in_array($log['user_id'] ?? '', $deleted_users);
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
        
        // ✅ 삭제된 사용자들의 활동 로그 삭제
        $activity_file = __DIR__ . '/src/activity_log.json';
        if (file_exists($activity_file)) {
            $fp = fopen($activity_file, 'c+');
            if ($fp && flock($fp, LOCK_EX)) {
                $content = stream_get_contents($fp);
                $logs = json_decode($content, true) ?? [];
                
                $logs = array_filter($logs, function($log) use ($deleted_users) {
                    return !in_array($log['user_id'] ?? '', $deleted_users);
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
    }
    
    $_SESSION['admin_message'] = __("adm_user_group_updated");
    header("Location: " . $_SERVER['PHP_SELF'] . "#group");
    exit;

} elseif (($_POST['mode'] ?? '') === "status_change") {
    // ✅ 사용자 상태 변경 (승인/정지)
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('group');
    }
    
    $ua = load_users();
    $target_user = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['target_user'] ?? '');
    $new_status = $_POST['new_status'] ?? '';
    
    // 관리자는 정지 불가
    if (!empty($target_user) && isset($ua[$target_user]) && ($ua[$target_user]['group'] ?? '') === 'admin' && $new_status === 'suspended') {
        $_SESSION['admin_message'] = __("adm_admin_no_suspend");
        header("Location: " . $_SERVER['PHP_SELF'] . "#group");
        exit;
    }
    
    if (!empty($target_user) && isset($ua[$target_user]) && in_array($new_status, ['active', 'pending', 'suspended'])) {
        $ua[$target_user]['status'] = $new_status;
        
        if ($new_status === 'active') {
            if (empty($ua[$target_user]['approved_at'])) {
                $ua[$target_user]['approved_at'] = date('Y-m-d H:i:s');
            }
            // 정지 해제 시 정지 정보 삭제
            unset($ua[$target_user]['suspended_reason']);
            unset($ua[$target_user]['suspended_from']);
            unset($ua[$target_user]['suspended_until']);
        }
        
        if ($new_status === 'suspended') {
            // 정지 사유 및 기간 저장
            $ua[$target_user]['suspended_reason'] = mb_substr(trim($_POST['suspend_reason'] ?? ''), 0, 200);
            $ua[$target_user]['suspended_from'] = $_POST['suspend_from'] ?? date('Y-m-d');
            $ua[$target_user]['suspended_until'] = $_POST['suspend_until'] ?? ''; // 빈 값이면 무기한
        }
        
        save_users($ua);
        
        $status_names = ['active' => __('adm_status_active'), 'pending' => __('adm_status_pending'), 'suspended' => __('adm_status_suspended')];
        $_SESSION['admin_message'] = __("adm_user_status_changed", h($target_user), $status_names[$new_status] ?? $new_status);
    } else {
        $_SESSION['admin_message'] = __("adm_status_change_failed");
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#group");
    exit;

} elseif (($_POST['mode'] ?? '') === "change_password") {
    // ✅ 비밀번호 변경 (관리자 기능)
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('group');
    }
    
    $ua = load_users();
    $target_user = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['target_user'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    
    if (!empty($target_user) && isset($ua[$target_user])) {
        // 비밀번호 유효성 검사
        if (strlen($new_password) < 8) {
            $_SESSION['admin_message'] = "❌ " . __("adm_pw_min_8");
            header("Location: " . $_SERVER['PHP_SELF'] . "#group");
            exit;
        }
        
        // 비밀번호 저장
        $ua[$target_user]['pass'] = password_hash(hash("sha256", $new_password), PASSWORD_DEFAULT);
        $ua[$target_user]['password_changed_at'] = date('Y-m-d H:i:s');
        unset($ua[$target_user]['must_change_password']); // 변경 필요 플래그 제거
        save_users($ua);
        
        $_SESSION['admin_message'] = "✅ " . __("adm_pw_changed", $target_user);
    } else {
        $_SESSION['admin_message'] = "❌ " . __("adm_pw_change_failed");
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#group");
    exit;

} elseif (($_POST['mode'] ?? '') === "change_email") {
    // ✅ 이메일 변경 (관리자 기능)
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('group');
    }
    
    $ua = load_users();
    $target_user = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['target_user'] ?? '');
    $new_email = trim($_POST['new_email'] ?? '');
    
    if (!empty($target_user) && isset($ua[$target_user])) {
        // 이메일 형식 검증 (빈 값은 허용 - 삭제 용도)
        if (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['admin_message'] = "❌ " . __("adm_invalid_email");
            header("Location: " . $_SERVER['PHP_SELF'] . "#group");
            exit;
        }
        
        // 이메일 중복 체크 (빈 값이 아닐 때만)
        if (!empty($new_email)) {
            foreach ($ua as $uid => $udata) {
                if ($uid !== $target_user && isset($udata['email']) && strtolower($udata['email']) === strtolower($new_email)) {
                    $_SESSION['admin_message'] = "❌ " . __("adm_email_in_use", $uid);
                    header("Location: " . $_SERVER['PHP_SELF'] . "#group");
                    exit;
                }
            }
        }
        
        $old_email = $ua[$target_user]['email'] ?? '';
        $ua[$target_user]['email'] = $new_email;
        save_users($ua);
        
        if (empty($new_email)) {
            $_SESSION['admin_message'] = "✅ " . __("adm_email_removed", $target_user);
        } else {
            $_SESSION['admin_message'] = "✅ " . __("adm_email_changed", $target_user, $new_email);
        }
    } else {
        $_SESSION['admin_message'] = "❌ " . __("adm_email_change_failed");
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#group");
    exit;

} elseif (($_POST['mode'] ?? '') === "branding_change") {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('branding');
    }
    // ✅ 브랜딩 설정 변경 처리
    $logo_type = ($_POST['logo_type'] ?? 'text') === 'image' ? 'image' : 'text';
    $logo_text = trim($_POST['logo_text'] ?? 'myComix');
    $subtitle = trim($_POST['subtitle'] ?? __('adm_default_subtitle'));
    $login_button = trim($_POST['login_button'] ?? __('login_submit'));
    $copyright = trim($_POST['copyright'] ?? 'myComix © 2026');
    $page_title = trim($_POST['page_title'] ?? 'myComix');
    $admin_title = trim($_POST['admin_title'] ?? 'myComix - Admin');
    
    $logo_text = strip_tags($logo_text);
    $subtitle = strip_tags($subtitle);
    $login_button = strip_tags($login_button);
    $copyright = strip_tags($copyright);
    $page_title = strip_tags($page_title);
    $admin_title = strip_tags($admin_title);
    
    $logo_image = $branding['logo_image'] ?? '';
    if (!empty($_FILES['logo_image']['name']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['logo_image']['tmp_name']);
        finfo_close($finfo);
        
        if (in_array($mime, $allowed_types)) {
            $ext = pathinfo($_FILES['logo_image']['name'], PATHINFO_EXTENSION);
            $ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'])) $ext = 'png';
            
            $upload_dir = './src/';
            $new_filename = 'logo_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (!empty($branding['logo_image']) && file_exists($branding['logo_image'])) {
                @unlink($branding['logo_image']);
            }
            
            if (move_uploaded_file($_FILES['logo_image']['tmp_name'], $upload_path)) {
                $logo_image = $upload_path;
            }
        }
    }
    
    if (isset($_POST['delete_logo_image']) && $_POST['delete_logo_image'] === '1') {
        if (!empty($branding['logo_image']) && file_exists($branding['logo_image'])) {
            @unlink($branding['logo_image']);
        }
        $logo_image = '';
        $logo_type = 'text';
    }
    
    $new_branding = [
        'logo_type' => $logo_type,
        'logo_text' => $logo_text,
        'logo_image' => $logo_image,
        'subtitle' => $subtitle,
        'login_button' => $login_button,
        'copyright' => $copyright,
        'page_title' => $page_title,
        'admin_title' => $admin_title
    ];
    
    save_branding($new_branding);
    $_SESSION['admin_message'] = __("adm_branding_saved");
    header("Location: " . $_SERVER['PHP_SELF'] . "#branding");
    exit;

} elseif (($_POST['mode'] ?? '') === "smtp_change") {
    // ✅ SMTP 설정 변경 처리
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('smtp');
    }
    
    $smtp_settings = [
        'enabled' => isset($_POST['smtp_enabled']),
        'host' => trim($_POST['smtp_host'] ?? ''),
        'port' => (int)($_POST['smtp_port'] ?? 587),
        'encryption' => in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none']) ? $_POST['smtp_encryption'] : 'tls',
        'username' => trim($_POST['smtp_username'] ?? ''),
        'password' => $_POST['smtp_password'] ?? '',
        'from_email' => trim($_POST['smtp_from_email'] ?? ''),
        'from_name' => trim($_POST['smtp_from_name'] ?? 'myComix')
    ];
    
    // 비밀번호가 비어있고 기존 비밀번호가 있으면 유지
    if (empty($smtp_settings['password'])) {
        $old_settings = get_app_settings('smtp', []);
        $smtp_settings['password'] = $old_settings['password'] ?? '';
    }
    
    $save_result = set_app_settings('smtp', $smtp_settings);
    if ($save_result) {
        $_SESSION['admin_message'] = "✅ " . __("adm_smtp_saved");
    } else {
        $_SESSION['admin_message'] = "❌ " . __("adm_smtp_save_failed");
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "#smtp");
    exit;

} elseif (($_POST['mode'] ?? '') === "smtp_test") {
    // ✅ SMTP 테스트 이메일 발송
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('smtp');
    }
    
    $test_email = trim($_POST['test_email'] ?? '');
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['admin_message'] = "❌ " . __("adm_enter_valid_email");
        header("Location: " . $_SERVER['PHP_SELF'] . "#smtp");
        exit;
    }
    
    $smtp = get_app_settings('smtp', []);
    if (empty($smtp['host']) || empty($smtp['username'])) {
        $_SESSION['admin_message'] = "❌ " . __("adm_smtp_save_first");
        header("Location: " . $_SERVER['PHP_SELF'] . "#smtp");
        exit;
    }
    
    // 비밀번호 확인
    if (empty($smtp['password'])) {
        $_SESSION['admin_message'] = "❌ " . __("adm_smtp_no_password");
        header("Location: " . $_SERVER['PHP_SELF'] . "#smtp");
        exit;
    }
    
    // 이메일 발송 시도
    $result = send_smtp_email(
        $test_email,
        __('adm_smtp_test_subject'),
        '<h2>' . __('adm_smtp_test_heading') . '</h2><p>' . __('adm_smtp_test_body') . '</p><p>' . __('adm_smtp_test_time') . ': ' . date('Y-m-d H:i:s') . '</p>',
        $smtp
    );
    
    if ($result === true) {
        $_SESSION['admin_message'] = "✅ " . __("adm_smtp_test_sent", $test_email);
    } else {
        $_SESSION['admin_message'] = "❌ " . __("adm_smtp_send_failed") . ": " . h($result);
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "#smtp");
    exit;

}

// ✅ 팝업 저장 처리 (다중 팝업)
if (isset($_POST['mode']) && $_POST['mode'] === 'save_popup') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('notice');
    }
    
    $popup_idx = (int)($_POST['popup_idx'] ?? -1);
    $popups = get_app_settings('popups', []);
    
    // 기존 팝업 수정 시 이미지 파일명 유지
    $image_file = '';
    if ($popup_idx >= 0 && isset($popups[$popup_idx])) {
        $image_file = $popups[$popup_idx]['image_file'] ?? '';
    }
    
    // 이미지 삭제 체크
    if (isset($_POST['popup_delete_image']) && !empty($image_file)) {
        $old_image_path = __DIR__ . '/src/' . $image_file;
        if (file_exists($old_image_path)) {
            @unlink($old_image_path);
        }
        $image_file = '';
    }
    
    // 이미지 업로드 처리
    if (!empty($_FILES['popup_image_file']['name']) && $_FILES['popup_image_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['popup_image_file']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            // 기존 이미지 삭제
            if (!empty($image_file)) {
                $old_image_path = __DIR__ . '/src/' . $image_file;
                if (file_exists($old_image_path)) {
                    @unlink($old_image_path);
                }
            }
            
            // 확장자 추출
            $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $ext = $ext_map[$file_type] ?? 'jpg';
            $new_filename = 'popup_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_path = __DIR__ . '/src/' . $new_filename;
            
            if (move_uploaded_file($_FILES['popup_image_file']['tmp_name'], $upload_path)) {
                $image_file = $new_filename;
            }
        }
    }
    
    // 팝업 데이터 구성
    $popup_data = [
        'enabled' => isset($_POST['popup_enabled']),
        'order' => max(1, min(10, (int)($_POST['popup_order'] ?? 1))),
        'title' => trim($_POST['popup_title'] ?? ''),
        'content' => $_POST['popup_content'] ?? '',
        'start_date' => $_POST['popup_start_date'] ?? '',
        'end_date' => $_POST['popup_end_date'] ?? '',
        'width' => ($_POST['popup_width'] ?? '') !== '' ? (int)$_POST['popup_width'] : '',
        'height' => ($_POST['popup_height'] ?? '') !== '' ? (int)$_POST['popup_height'] : '',
        'bg_color' => $_POST['popup_bg_color'] ?? '#ffffff',
        'image_url' => trim($_POST['popup_image_url'] ?? ''),
        'image_file' => $image_file,
        'show_mode' => $_POST['popup_show_mode'] ?? 'both'
    ];
    
    if ($popup_idx >= 0 && isset($popups[$popup_idx])) {
        // 기존 팝업 수정
        $popups[$popup_idx] = $popup_data;
    } else {
        // 새 팝업 추가 (최대 10개)
        if (count($popups) < 10) {
            $popups[] = $popup_data;
        }
    }
    
    set_app_settings('popups', $popups);
    $_SESSION['admin_message'] = "✅ " . __("adm_popup_saved");
    header("Location: " . $_SERVER['PHP_SELF'] . "#notice");
    exit;
}

// ✅ 팝업 삭제 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'delete_popup') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('notice');
    }
    
    $popup_idx = (int)($_POST['popup_idx'] ?? -1);
    $popups = get_app_settings('popups', []);
    
    if ($popup_idx >= 0 && isset($popups[$popup_idx])) {
        // 이미지 파일 삭제
        if (!empty($popups[$popup_idx]['image_file'])) {
            $image_path = __DIR__ . '/src/' . $popups[$popup_idx]['image_file'];
            if (file_exists($image_path)) {
                @unlink($image_path);
            }
        }
        
        array_splice($popups, $popup_idx, 1);
        set_app_settings('popups', $popups);
        $_SESSION['admin_message'] = "✅ " . __("adm_popup_deleted");
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#notice");
    exit;
}

// ✅ 팝업 설정 저장 처리 (배치 방식, 기본 크기, 간격)
if (isset($_POST['mode']) && $_POST['mode'] === 'save_popup_settings') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('notice');
    }
    
    // 배치 방식
    $layout = $_POST['popup_layout'] ?? 'horizontal';
    if (!in_array($layout, ['horizontal', 'vertical', 'grid'])) {
        $layout = 'horizontal';
    }
    set_app_settings('popup_layout', $layout);
    
    // 기본 크기
    $default_width = max(200, min(800, (int)($_POST['popup_default_width'] ?? 350)));
    $default_height = max(100, min(600, (int)($_POST['popup_default_height'] ?? 250)));
    set_app_settings('popup_default_width', $default_width);
    set_app_settings('popup_default_height', $default_height);
    
    // 간격
    $gap = max(0, min(50, (int)($_POST['popup_gap'] ?? 20)));
    set_app_settings('popup_gap', $gap);
    
    $_SESSION['admin_message'] = "✅ " . __("adm_popup_settings_saved");
    header("Location: " . $_SERVER['PHP_SELF'] . "#notice");
    exit;
}

// ✅ 배너 설정 저장 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'save_banner') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('notice');
    }
    
    $banner_settings = [
        'enabled' => isset($_POST['banner_enabled']),
        'content' => trim($_POST['banner_content'] ?? ''),
        'bg_color' => $_POST['banner_bg_color'] ?? '#fff3cd',
        'text_color' => $_POST['banner_text_color'] ?? '#856404',
        'link' => trim($_POST['banner_link'] ?? ''),
        'start_date' => $_POST['banner_start_date'] ?? '',
        'end_date' => $_POST['banner_end_date'] ?? ''
    ];
    set_app_settings('banner', $banner_settings);
    
    $_SESSION['admin_message'] = "✅ " . __("adm_banner_saved");
    header("Location: " . $_SERVER['PHP_SELF'] . "#notice");
    exit;
}

// ✅ 언어 설정 저장 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'save_language') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('language');
    }
    
    $new_lang = $_POST['site_language'] ?? 'ko';
    $available = get_available_langs();
    if (!array_key_exists($new_lang, $available)) {
        $new_lang = 'ko';
    }
    
    set_app_settings('language', $new_lang);
    $_SESSION['lang'] = $new_lang; // 즉시 반영
    init_language(); // 언어 재초기화
    $_SESSION['admin_message'] = __('language_saved');
    header("Location: " . $_SERVER['PHP_SELF'] . "#language");
    exit;
}

// ✅ 약관 설정 저장 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'save_terms') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('terms');
    }
    
    $terms_settings = [
        'enabled' => isset($_POST['terms_enabled']),
        'content' => $_POST['terms_content'] ?? ''
    ];
    
    set_app_settings('terms', $terms_settings);
    $_SESSION['admin_message'] = "✅ " . __("adm_terms_saved");
    header("Location: " . $_SERVER['PHP_SELF'] . "#terms");
    exit;
}

// ✅ 삭제된 사용자 영구삭제 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'purge_deleted_user') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('deleted_users');
    }
    
    $purge_index = (int)($_POST['purge_index'] ?? -1);
    $deleted_file = __DIR__ . '/src/deleted_users.json';
    
    if (file_exists($deleted_file)) {
        $deleted_data = json_decode(file_get_contents($deleted_file), true) ?? [];
        $deleted_data = array_reverse($deleted_data); // 최신순으로 정렬된 상태
        
        if (isset($deleted_data[$purge_index])) {
            array_splice($deleted_data, $purge_index, 1);
            $deleted_data = array_reverse($deleted_data); // 다시 원래 순서로
            file_put_contents($deleted_file, json_encode($deleted_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $_SESSION['admin_message'] = "✅ " . __("adm_deleted_user_purged");
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#deleted_users");
    exit;
}

// ✅ 삭제된 사용자 전체 영구삭제 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'purge_all_deleted_users') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('deleted_users');
    }
    
    $deleted_file = __DIR__ . '/src/deleted_users.json';
    if (file_exists($deleted_file)) {
        file_put_contents($deleted_file, '[]');
    }
    
    $_SESSION['admin_message'] = "✅ " . __("adm_all_deleted_users_purged");
    header("Location: " . $_SERVER['PHP_SELF'] . "#deleted_users");
    exit;
}

// config_change는 상단에서 처리됨

// ✅ IP/국가 차단 설정 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'security_settings') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('security');
    }
    
    require_once __DIR__ . '/ip_block.php';
    $blocker = new IPBlocker(__DIR__ . '/src/ip_block_settings.json');
    
    $settings = [
        'enabled' => isset($_POST['ip_block_enabled']),
        'mode' => isset($_POST['ip_block_mode']) ? (array)$_POST['ip_block_mode'] : [],
        'blocked_countries' => array_filter(array_map('trim', explode(',', $_POST['blocked_countries'] ?? ''))),
        'allowed_countries' => array_filter(array_map('trim', explode(',', $_POST['allowed_countries'] ?? ''))),
        'blocked_ips' => array_filter(array_map('trim', preg_split('/[\r\n,]+/', $_POST['blocked_ips'] ?? ''))),
        'allowed_ips' => array_filter(array_map('trim', preg_split('/[\r\n,]+/', $_POST['allowed_ips'] ?? ''))),
        'whitelist_ips' => array_filter(array_map('trim', preg_split('/[\r\n,]+/', $_POST['whitelist_ips'] ?? ''))),
        'block_message' => $_POST['block_message'] ?? __('adm_default_block_msg'),
        'log_enabled' => isset($_POST['log_enabled']),
        'cache_hours' => max(1, min(168, intval($_POST['cache_hours'] ?? 24))),
        // 브루트포스 설정
        'bruteforce_enabled' => isset($_POST['bruteforce_enabled']),
        'bruteforce_max_attempts' => max(1, min(20, intval($_POST['bruteforce_max_attempts'] ?? 5))),
        'bruteforce_lockout_time' => max(0, min(86400, intval($_POST['bruteforce_lockout_time'] ?? 900))),
        'bruteforce_attempt_window' => max(60, min(3600, intval($_POST['bruteforce_attempt_window'] ?? 300))),
        // ✅ 프록시 헤더 신뢰 설정 (IP 스푸핑 방지)
        'trust_proxy_headers' => isset($_POST['trust_proxy_headers']),
        // ✅ GeoIP 설정
        'geoip_source' => in_array($_POST['geoip_source'] ?? 'api', ['api', 'mmdb', 'dat', 'csv']) ? $_POST['geoip_source'] : 'api',
        'geoip_mmdb_path' => trim($_POST['geoip_mmdb_path'] ?? ''),
        'geoip_dat_path' => trim($_POST['geoip_dat_path'] ?? ''),
        'geoip_csv_path' => trim($_POST['geoip_csv_path'] ?? ''),
        'block_unknown' => isset($_POST['block_unknown'])
    ];
    
    $blocker->saveSettings($settings);
    $_SESSION['admin_message'] = "✅ " . __("adm_security_saved");
    header("Location: " . $_SERVER['PHP_SELF'] . "#security");
    exit;
}

// ✅ 차단 로그 삭제 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'clear_block_logs') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('security');
    }
    
    require_once __DIR__ . '/ip_block.php';
    $blocker = new IPBlocker(__DIR__ . '/src/ip_block_settings.json');
    $blocker->clearBlockLogs();
    $_SESSION['admin_message'] = "✅ " . __("adm_block_log_cleared");
    header("Location: " . $_SERVER['PHP_SELF'] . "#security");
    exit;
}

// ✅ 브루트포스 로그 삭제 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'clear_bruteforce_logs') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('security');
    }
    
    require_once __DIR__ . '/ip_block.php';
    $blocker = new IPBlocker(__DIR__ . '/src/ip_block_settings.json');
    $blocker->clearBruteforceData();
    $_SESSION['admin_message'] = "✅ " . __("adm_bruteforce_log_cleared");
    header("Location: " . $_SERVER['PHP_SELF'] . "#security");
    exit;
}

// ✅ IP 잠금 해제 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'unlock_ip') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('security');
    }
    
    require_once __DIR__ . '/ip_block.php';
    $blocker = new IPBlocker(__DIR__ . '/src/ip_block_settings.json');
    $ip = $_POST['unlock_ip'] ?? '';
    if ($ip && $blocker->unlockIP($ip)) {
        $_SESSION['admin_message'] = "✅ " . __("adm_ip_unlocked", $ip);
    } else {
        $_SESSION['admin_message'] = "⚠️ " . __("adm_unlock_failed");
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "#security");
    exit;
}

// ✅ IP 캐시 삭제 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'clear_ip_cache') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('security');
    }
    
    require_once __DIR__ . '/ip_block.php';
    $blocker = new IPBlocker(__DIR__ . '/src/ip_block_settings.json');
    $blocker->clearIPCache();
    $_SESSION['admin_message'] = "✅ " . __("adm_ip_cache_cleared");
    header("Location: " . $_SERVER['PHP_SELF'] . "#security");
    exit;
}

// ✅ 로그인 로그 삭제 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'delete_login_log') {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('logs');
    }
    $log_file = __DIR__ . '/src/login_log.json';
    $delete_type = $_POST['delete_type'] ?? '';
    
    if ($delete_type === 'all') {
        // 전체 삭제
        if (file_exists($log_file)) {
            $fp = fopen($log_file, 'c+');
            if ($fp) {
                if (flock($fp, LOCK_EX)) {
                    ftruncate($fp, 0);
                    fwrite($fp, '[]');
                    fflush($fp);
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
            }
        }
        $_SESSION['admin_message'] = __("adm_all_login_logs_deleted");
    } elseif ($delete_type === 'selected' && !empty($_POST['selected_logs'])) {
        // 선택 삭제
        $selected = array_map('intval', $_POST['selected_logs']);
        if (file_exists($log_file)) {
            $fp = fopen($log_file, 'c+');
            if ($fp) {
                if (flock($fp, LOCK_EX)) {
                    $content = stream_get_contents($fp);
                    $logs = json_decode($content, true) ?? [];
                    $logs = array_values(array_filter($logs, function($k) use ($selected) {
                        return !in_array($k, $selected);
                    }, ARRAY_FILTER_USE_KEY));
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fflush($fp);
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
            }
        }
        $_SESSION['admin_message'] = __("adm_n_logs_deleted", count($selected));
    } elseif ($delete_type === 'old' && !empty($_POST['days_old'])) {
        // 오래된 로그 삭제
        $days = max(1, (int)$_POST['days_old']);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        if (file_exists($log_file)) {
            $fp = fopen($log_file, 'c+');
            if ($fp) {
                if (flock($fp, LOCK_EX)) {
                    $content = stream_get_contents($fp);
                    $logs = json_decode($content, true) ?? [];
                    $original_count = count($logs);
                    $logs = array_values(array_filter($logs, function($log) use ($cutoff) {
                        return ($log['datetime'] ?? '') >= $cutoff;
                    }));
                    $deleted_count = $original_count - count($logs);
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    $_SESSION['admin_message'] = __("adm_old_logs_deleted", $days, $deleted_count);
                }
                fclose($fp);
            }
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=logs");
    exit;
}

// ✅ 활동 로그 삭제 처리
if (isset($_POST['mode']) && $_POST['mode'] === 'delete_activity_log') {
    // CSRF 토큰 검증
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        csrf_fail_redirect('logs');
    }
    $log_file = __DIR__ . '/src/activity_log.json';
    $delete_type = $_POST['delete_type'] ?? '';
    
    if ($delete_type === 'all') {
        // 전체 삭제
        if (file_exists($log_file)) {
            $fp = fopen($log_file, 'c+');
            if ($fp) {
                if (flock($fp, LOCK_EX)) {
                    ftruncate($fp, 0);
                    fwrite($fp, '[]');
                    fflush($fp);
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
            }
        }
        $_SESSION['admin_message'] = __("adm_all_activity_logs_deleted");
    } elseif ($delete_type === 'old' && !empty($_POST['days_old'])) {
        // 오래된 로그 삭제
        $days = max(1, (int)$_POST['days_old']);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        if (file_exists($log_file)) {
            $fp = fopen($log_file, 'c+');
            if ($fp) {
                if (flock($fp, LOCK_EX)) {
                    $content = stream_get_contents($fp);
                    $logs = json_decode($content, true) ?? [];
                    $original_count = count($logs);
                    $logs = array_values(array_filter($logs, function($log) use ($cutoff) {
                        return ($log['datetime'] ?? '') >= $cutoff;
                    }));
                    $deleted_count = $original_count - count($logs);
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    $_SESSION['admin_message'] = __("adm_old_activity_logs_deleted", $days, $deleted_count);
                }
                fclose($fp);
            }
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#logs");
    exit;
}

$stats = get_system_stats_fast($permissions_file, $base_dirs);

// 테마 정보 (26개)
$themes = [
    0 => ['name' => __('adm_lstyle_library'), 'icon' => '📚', 'desc' => __('adm_lstyledesc_library'), 'color' => '#8B4513'],
    1 => ['name' => __('adm_lstyle_matrix'), 'icon' => '🟢', 'desc' => __('adm_lstyledesc_matrix'), 'color' => '#00ff00'],
    2 => ['name' => __('adm_lstyle_glass'), 'icon' => '🏔️', 'desc' => __('adm_lstyledesc_glass'), 'color' => '#667eea'],
    3 => ['name' => __('adm_lstyle_cyberpunk'), 'icon' => '🌃', 'desc' => __('adm_lstyledesc_cyberpunk'), 'color' => '#ff0066'],
    4 => ['name' => __('adm_lstyle_galaxy'), 'icon' => '🌌', 'desc' => __('adm_lstyledesc_galaxy'), 'color' => '#8a2be2'],
    5 => ['name' => __('adm_theme_sakura'), 'icon' => '🌸', 'desc' => __('adm_lstyledesc_sakura'), 'color' => '#ff69b4'],
    6 => ['name' => __('adm_lstyle_gothic'), 'icon' => '🧛', 'desc' => __('adm_lstyledesc_gothic'), 'color' => '#8b0000'],
    7 => ['name' => __('adm_lstyle_minimal'), 'icon' => '⚪', 'desc' => __('adm_lstyledesc_minimal'), 'color' => '#667eea'],
    8 => ['name' => __('adm_theme_retro_arcade'), 'icon' => '🕹️', 'desc' => __('adm_lstyledesc_retro'), 'color' => '#00ff00'],
    9 => ['name' => __('adm_lstyle_ocean'), 'icon' => '🌊', 'desc' => __('adm_lstyledesc_ocean'), 'color' => '#0077be'],
    10 => ['name' => __('adm_lstyle_gradient'), 'icon' => '🎨', 'desc' => __('adm_lstyledesc_gradient'), 'color' => '#e73c7e'],
    11 => ['name' => __('adm_lstyle_jarvis'), 'icon' => '🤖', 'desc' => __('adm_lstyledesc_jarvis'), 'color' => '#00d4ff'],
    12 => ['name' => __('adm_lstyle_aurora'), 'icon' => '🌌', 'desc' => __('adm_lstyledesc_aurora'), 'color' => '#00ff88'],
    13 => ['name' => __('adm_lstyle_neoncity'), 'icon' => '🌆', 'desc' => __('adm_lstyledesc_neoncity'), 'color' => '#ff00ff'],
    14 => ['name' => __('adm_lstyle_fire'), 'icon' => '🔥', 'desc' => __('adm_lstyledesc_fire'), 'color' => '#ff4500'],
    15 => ['name' => __('adm_lstyle_aquarium'), 'icon' => '🐠', 'desc' => __('adm_lstyledesc_aquarium'), 'color' => '#00ced1'],
    16 => ['name' => __('adm_lstyle_snow'), 'icon' => '❄️', 'desc' => __('adm_lstyledesc_snow'), 'color' => '#87ceeb'],
    17 => ['name' => __('adm_lstyle_gold'), 'icon' => '👑', 'desc' => __('adm_lstyledesc_gold'), 'color' => '#ffd700'],
    18 => ['name' => __('adm_lstyle_hologram'), 'icon' => '🌈', 'desc' => __('adm_lstyledesc_hologram'), 'color' => '#ff69b4'],
    19 => ['name' => __('adm_lstyle_terminal'), 'icon' => '💻', 'desc' => __('adm_lstyledesc_terminal'), 'color' => '#00ff00'],
    20 => ['name' => __('adm_lstyle_starwars'), 'icon' => '⭐', 'desc' => __('adm_lstyledesc_starwars'), 'color' => '#ffe81f'],
    21 => ['name' => __('adm_lstyle_constellation'), 'icon' => '✨', 'desc' => __('adm_lstyledesc_constellation'), 'color' => '#6495ed'],
    22 => ['name' => __('adm_lstyle_milkyway'), 'icon' => '🌌', 'desc' => __('adm_lstyledesc_milkyway'), 'color' => '#9370db'],
    23 => ['name' => __('adm_lstyle_nebula'), 'icon' => '💜', 'desc' => __('adm_lstyledesc_nebula'), 'color' => '#00ffff'],
    24 => ['name' => __('adm_lstyle_meteor'), 'icon' => '☄️', 'desc' => __('adm_lstyledesc_meteor'), 'color' => '#ffd700'],
    25 => ['name' => __('adm_lstyle_deepspace'), 'icon' => '🚀', 'desc' => __('adm_lstyledesc_deepspace'), 'color' => '#0096ff'],
];
?>
<!DOCTYPE html>
<html lang="<?php echo get_html_lang(); ?>">
<head>
<script>
// 브라우저 자동 스크롤 복원 비활성화
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}
// 페이지 로드 시 즉시 스크롤 위치 복원
(function() {
    var saved = sessionStorage.getItem('adminScrollPos');
    if (saved !== null) {
        // 해시 스크롤 방지를 위해 즉시 실행
        window.addEventListener('scroll', function restore() {
            window.scrollTo(0, parseInt(saved));
            window.removeEventListener('scroll', restore);
        }, {once: true});
        // DOM 로드 후에도 한번 더 복원
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.scrollTo(0, parseInt(saved));
                sessionStorage.removeItem('adminScrollPos');
            }, 10);
        });
    }
})();
</script>
<title><?php echo h($branding['admin_title'] ?? 'myComix - Admin'); ?></title>
<meta charset="UTF-8">
<!-- Core layout + page transitions -->
<style>
html{opacity:0;transition:opacity .15s ease-in}
html.ready{opacity:1}
html.leaving{opacity:0;transition:opacity .1s ease-out}
*{box-sizing:border-box}
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
</style>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="./css/bootstrap.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gugi&family=Nanum+Gothic:wght@400;700&family=VT323&family=Orbitron:wght@400;700&family=Creepster&family=Press+Start+2P&family=Roboto+Mono:wght@400;700&family=Share+Tech+Mono&family=Cinzel:wght@400;700&family=Playfair+Display:wght@400;700&family=Jua&display=swap">
<link rel="shortcut icon" href="./favicon.ico">
<script src="./js/jquery-3.5.1.min.js"></script>
<script src="./js/popper.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
body{font-family:'Nanum Gothic',sans-serif;font-size:smaller;background:#f8f9fa}
a:link,a:visited,a:active,a:hover{text-decoration:none}
.stat-card{border-radius:15px;padding:20px;color:#fff;margin-bottom:15px;box-shadow:0 4px 15px rgba(0,0,0,.1);transition:transform .2s}
.stat-card:hover{transform:translateY(-3px)}
.stat-card .stat-number{font-size:2.2em;font-weight:700;margin:0}
.stat-card .stat-label{font-size:.95em;opacity:.9;margin:5px 0 0}
.bg-gradient-purple{background:linear-gradient(135deg,#667eea,#764ba2)}
.bg-gradient-pink{background:linear-gradient(135deg,#f093fb,#f5576c)}
.bg-gradient-blue{background:linear-gradient(135deg,#4facfe,#00f2fe)}
.bg-gradient-orange{background:linear-gradient(135deg,#fa709a,#fee140)}
.status-badge{display:inline-block;padding:4px 10px;border-radius:15px;font-size:.85em;font-weight:600}
.status-ok{background:#d4edda;color:#155724}
.status-warning{background:#fff3cd;color:#856404}
.status-error{background:#f8d7da;color:#721c24}
.info-card{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:15px}
.info-card .card-header{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:10px 10px 0 0;padding:12px 15px;font-weight:600}
.quick-link{display:block;padding:15px;border-radius:10px;text-align:center;color:#fff;margin-bottom:10px;transition:all .2s}
.quick-link:hover{transform:scale(1.02);color:#fff;box-shadow:0 5px 20px rgba(0,0,0,.2)}
.alert-floating{position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;animation:slideIn .3s}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
.badge-item{font-size:0.85em;padding:4px 9px}
.config-table td:first-child{width:80px;max-width:80px;font-size:0.85em;word-break:keep-all;padding-right:5px;padding-top:6px;padding-bottom:6px}
@media(max-width:576px){.config-table td:first-child{width:65px;max-width:65px;font-size:0.75em}}
.config-table input[type="text"],.config-table input[type="number"]{width:100%}
.config-table td{padding-top:6px;padding-bottom:6px}
/* 섹션 제목 및 일반 행 여백 축소 */
table.config-table{border-collapse:collapse;border-spacing:0}
@media(max-width:576px){
  table.config-table td.text-right{width:70px !important}
}
.theme-card{border:2px solid #dee2e6;border-radius:12px;padding:12px 8px;margin-bottom:8px;cursor:pointer;transition:all .2s;text-align:center;min-height:95px}
.theme-card:hover{border-color:#667eea;background:#f8f9ff;transform:translateY(-2px)}
.theme-card.active{border-color:#667eea;background:#e8ecff;box-shadow:0 0 0 3px rgba(102,126,234,.2)}
.theme-card .theme-icon{font-size:1.5em;margin-bottom:3px}
.theme-card .theme-name{font-weight:bold;color:#333;font-size:0.8em}
.theme-card .theme-desc{font-size:0.65em;color:#888}
.theme-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(95px,1fr));gap:8px}
.theme-card .theme-desc{font-size:0.8em;color:#666}
/* 탭 네비게이션 가로 스크롤 */
.nav-tabs-wrapper{overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;margin-bottom:15px}
.nav-tabs-wrapper::-webkit-scrollbar{height:4px}
.nav-tabs-wrapper::-webkit-scrollbar-track{background:#f1f1f1;border-radius:2px}
.nav-tabs-wrapper::-webkit-scrollbar-thumb{background:#888;border-radius:2px}
.nav-tabs-wrapper::-webkit-scrollbar-thumb:hover{background:#555}
.nav-tabs-wrapper .nav-tabs{display:flex;flex-wrap:nowrap;border-bottom:1px solid #dee2e6;min-width:max-content}
.nav-tabs-wrapper .nav-item{flex-shrink:0}
.nav-tabs-wrapper .nav-link{white-space:nowrap;padding:8px 15px}
/* Bootstrap 탭 안전장치 */
.tab-content > .tab-pane:not(.active){display:none !important;height:0 !important;overflow:hidden !important;visibility:hidden !important;opacity:0 !important;position:absolute !important;pointer-events:none !important}
.tab-content > .tab-pane.active{display:block !important;position:relative !important;opacity:1 !important;visibility:visible !important;height:auto !important}
</style>
<script>document.documentElement.classList.add('ready');</script>

<script>
var i18n_adm = <?php
$_i18n_adm_data = [
    'select_cache_type' => __('adm_js_select_cache_type'),
    'enter_folder_path' => __('adm_js_enter_folder_path'),
    'confirm_delete_popup' => __('adm_js_confirm_delete_popup'),
    'confirm_delete_all_logs' => __('adm_js_confirm_delete_all_logs'),
    'confirm_delete_selected_logs' => __('adm_js_confirm_delete_selected_logs'),
    'confirm_delete_period_logs' => __('adm_js_confirm_delete_period_logs'),
    'confirm_delete_all_activity' => __('adm_js_confirm_delete_all_activity'),
    'confirm_purge_user' => __('adm_js_confirm_purge_user'),
    'confirm_purge_all_users' => __('adm_js_confirm_purge_all_users'),
    'confirm_clear_block_log' => __('adm_js_confirm_clear_block_log'),
    'confirm_clear_bruteforce_log' => __('adm_js_confirm_clear_bruteforce_log'),
    'confirm_clear_ip_cache' => __('adm_js_confirm_clear_ip_cache'),
    'confirm_status_change' => __('adm_js_confirm_status_change'),
    'select_logs' => __('adm_js_select_logs'),
    'confirm_delete_n_logs' => __('adm_js_confirm_delete_n_logs'),
    'prompt_days_delete' => __('adm_js_prompt_days_delete'),
    'confirm_delete_old' => __('adm_js_confirm_delete_old'),
    'processing' => __('js_processing'),
    'error_occurred' => __('js_error_occurred'),
];
$_i18n_adm_data = array_map(function($v) {
    return is_string($v) ? str_replace('\n', "\n", $v) : $v;
}, $_i18n_adm_data);
echo json_encode($_i18n_adm_data, JSON_UNESCAPED_UNICODE);
?>;
</script>
</head>
<body class="text-center">
<?php if($message): ?>
<div class="alert alert-success alert-dismissible fade show alert-floating" role="alert">✅ <?php echo h($message); ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
<script>setTimeout(function(){$('.alert-floating').fadeOut();},3000);</script>
<?php endif; ?>

<br>
<?php if(($branding['logo_type'] ?? 'text') === 'image' && !empty($branding['logo_image']) && file_exists($branding['logo_image'])): ?>
<div><a href="admin.php" style="display:inline-block;"><img src="<?php echo h($branding['logo_image']); ?>" alt="<?php echo h($branding['logo_text']); ?>" style="max-height:2.5em; max-width:200px;"></a><span style="font-size:10px; color:#999; margin-left:4px; position:relative; top:-20px;"><?php echo MYCOMIX_VERSION; ?></span></div>
<?php else: ?>
<div style="font-family:'Gugi';font-size:2.5em;"><a href="admin.php" style="color:#000; text-decoration:none;"><?php echo h($branding['logo_text'] ?? 'myComix'); ?></a><span style="font-size:10px; color:#999; margin-left:4px; position:relative; top:-20px;"><?php echo MYCOMIX_VERSION; ?></span></div>
<?php endif; ?>
<small class="text-muted"><?php echo __h('admin_return_admin'); ?></small>
<?php render_lang_badge('md'); ?>
<br><br>

<div class="nav-tabs-wrapper">
<ul class="nav nav-tabs flex-nowrap">
<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#dashboard"><?php echo __('admin_tab_dashboard'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#config"><?php echo __('admin_tab_config'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cache"><?php echo __('admin_tab_cache'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#theme"><?php echo __('admin_tab_theme'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#branding"><?php echo __('admin_tab_branding'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#notice"><?php echo __('admin_tab_notice'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#group"><?php echo __('admin_tab_users'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#folder"><?php echo __('admin_tab_folder'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#logs" onclick="resetLogPage()"><?php echo __('admin_tab_logs'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#deleted_users"><?php echo __('admin_tab_deleted_users'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#terms"><?php echo __('admin_tab_terms'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#security"><?php echo __('admin_tab_security'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#smtp"><?php echo __('admin_tab_smtp'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#duplicates"><?php echo __('admin_tab_duplicates'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#system"><?php echo __('admin_tab_system'); ?></a></li>
<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#language"><?php echo __('admin_tab_language'); ?></a></li>
</ul>
</div>

<div class="tab-content">

<!-- Dashboard -->
<div class="tab-pane fade show active" id="dashboard">
<div class="container-fluid">

<?php
// ✅ 승인 대기 사용자 알림 배너
$pending_users_list = [];
$all_users_check = load_users();
foreach ($all_users_check as $uid => $udata) {
    if (($udata['status'] ?? 'active') === 'pending') {
        $pending_users_list[] = ['id' => $uid, 'created_at' => $udata['created_at'] ?? ''];
    }
}
if (count($pending_users_list) > 0): ?>
<div class="alert alert-warning alert-dismissible fade show mb-3" role="alert" style="border-left:4px solid #ff9800;animation:pulse 2s infinite;">
    <strong><?php echo __("adm_pending_alert", count($pending_users_list)); ?></strong>
    <ul class="mb-2 mt-2" style="padding-left:20px;">
    <?php foreach(array_slice($pending_users_list, 0, 5) as $pu): ?>
        <li><strong><?php echo h($pu['id']); ?></strong> <?php echo $pu['created_at'] ? '(' . h($pu['created_at']) . ' ' . __('adm_joined') . ')' : ''; ?></li>
    <?php endforeach; ?>
    <?php if(count($pending_users_list) > 5): ?>
        <li><?php echo __("adm_and_more", count($pending_users_list) - 5); ?></li>
    <?php endif; ?>
    </ul>
    <a href="#group" class="btn btn-warning btn-sm" onclick="document.querySelector('a[href=\'#group\']').click();"><?php echo __("adm_approve_users"); ?></a>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="position:absolute;top:10px;right:15px;">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<style>
@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.85}}
</style>
<?php endif; ?>

<div class="row">
<div class="col-6 col-md-3"><div class="stat-card bg-gradient-purple"><p class="stat-number"><?php echo number_format($stats['total_folders']); ?></p><p class="stat-label"><?php echo __("adm_stat_total_folders"); ?></p></div></div>
<div class="col-6 col-md-3"><div class="stat-card bg-gradient-pink"><p class="stat-number"><?php echo number_format($stats['zip_total']); ?></p><p class="stat-label"><?php echo __("adm_stat_total_works"); ?></p></div></div>
<div class="col-6 col-md-3"><div class="stat-card bg-gradient-blue"><p class="stat-number"><?php echo number_format($stats['user_count']); ?></p><p class="stat-label"><?php echo __("adm_stat_total_users"); ?></p></div></div>
<div class="col-6 col-md-3"><div class="stat-card bg-gradient-orange"><p class="stat-number"><?php echo number_format($stats['translation_count']); ?></p><p class="stat-label"><?php echo __("adm_stat_translations"); ?></p></div></div>
</div>

<div class="row mt-3">
<div class="col-6 col-md-3"><a href="index.php?bidx=0" class="quick-link bg-primary">🏠<br><?php echo __h('admin_return_index'); ?></a></div>
<div class="col-6 col-md-3"><a href="login.php?mode=adduser" class="quick-link bg-success"><?php echo __("adm_quick_add_user"); ?></a></div>
<div class="col-6 col-md-3"><a href="admin_translations.php" class="quick-link bg-info"><?php echo __("adm_quick_translations"); ?></a></div>
<div class="col-6 col-md-3"><a href="login.php?mode=logout" class="quick-link bg-danger">🚪<br><?php echo __h('admin_logout'); ?></a></div>
</div>

<!-- 📊 Access Statistics -->
<?php
// 로그인 기록에서 접속 통계 계산 (봇 제외, 로그인 사용자만)
$login_log_file = __DIR__ . '/src/login_log.json';
$daily_stats = [];
$hourly_stats = array_fill(0, 24, 0); // 시간대별 (0~23시)
$monthly_stats = [];
$yearly_stats = [];
$today = date('Y-m-d');
$this_month = date('Y-m');
$this_year = date('Y');

// 봇 판별 함수
function is_bot_user_agent($ua) {
    if (empty($ua)) return false;
    $bot_patterns = [
        'bot', 'crawler', 'spider', 'slurp', 'googlebot', 'bingbot', 
        'yandex', 'baidu', 'duckduckbot', 'facebookexternalhit', 
        'twitterbot', 'linkedinbot', 'pinterest', 'semrush', 'ahrefs',
        'mj12bot', 'dotbot', 'petalbot', 'bytespider', 'gptbot'
    ];
    $ua_lower = strtolower($ua);
    foreach ($bot_patterns as $pattern) {
        if (strpos($ua_lower, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

if (file_exists($login_log_file)) {
    $all_logs = json_decode(file_get_contents($login_log_file), true) ?? [];
    
    // 로그인 사용자만 카운트 (봇 제외)
    foreach ($all_logs as $log) {
        $log_time = $log['datetime'] ?? ($log['time'] ?? '');
        if (empty($log_time)) continue;
        
        $user_agent = $log['user_agent'] ?? '';
        if (is_bot_user_agent($user_agent)) continue;
        
        $user_id = $log['user_id'] ?? 'unknown';
        
        // 일별 통계
        $log_date = substr($log_time, 0, 10);
        if (!isset($daily_stats[$log_date])) {
            $daily_stats[$log_date] = ['total' => 0, 'unique' => []];
        }
        $daily_stats[$log_date]['total']++;
        if (!in_array($user_id, $daily_stats[$log_date]['unique'])) {
            $daily_stats[$log_date]['unique'][] = $user_id;
        }
        
        // 시간대별 통계 (오늘만)
        if ($log_date === $today && strlen($log_time) >= 13) {
            $hour = (int)substr($log_time, 11, 2);
            if ($hour >= 0 && $hour < 24) {
                $hourly_stats[$hour]++;
            }
        }
        
        // 월별 통계
        $log_month = substr($log_time, 0, 7);
        if (!isset($monthly_stats[$log_month])) {
            $monthly_stats[$log_month] = ['total' => 0, 'unique' => []];
        }
        $monthly_stats[$log_month]['total']++;
        if (!in_array($user_id, $monthly_stats[$log_month]['unique'])) {
            $monthly_stats[$log_month]['unique'][] = $user_id;
        }
        
        // 년도별 통계
        $log_year = substr($log_time, 0, 4);
        if (!isset($yearly_stats[$log_year])) {
            $yearly_stats[$log_year] = ['total' => 0, 'unique' => []];
        }
        $yearly_stats[$log_year]['total']++;
        if (!in_array($user_id, $yearly_stats[$log_year]['unique'])) {
            $yearly_stats[$log_year]['unique'][] = $user_id;
        }
    }
    
    krsort($daily_stats);
    krsort($monthly_stats);
    krsort($yearly_stats);
}

// 오늘/어제/이번주/이번달 요약
$today_total = $daily_stats[$today]['total'] ?? 0;
$today_unique = count($daily_stats[$today]['unique'] ?? []);
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_total = $daily_stats[$yesterday]['total'] ?? 0;
$yesterday_unique = count($daily_stats[$yesterday]['unique'] ?? []);

$week_total = 0;
$week_unique = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("-$i days"));
    if (isset($daily_stats[$d])) {
        $week_total += $daily_stats[$d]['total'];
        $week_unique = array_merge($week_unique, $daily_stats[$d]['unique']);
    }
}
$week_unique = count(array_unique($week_unique));

$month_total = 0;
$month_unique = [];
for ($i = 0; $i < 30; $i++) {
    $d = date('Y-m-d', strtotime("-$i days"));
    if (isset($daily_stats[$d])) {
        $month_total += $daily_stats[$d]['total'];
        $month_unique = array_merge($month_unique, $daily_stats[$d]['unique']);
    }
}
$month_unique = count(array_unique($month_unique));
?>
<div class="info-card mt-4">
<div class="card-header"><?php echo __("adm_card_stats"); ?></div>
<div class="card-body p-3">

<!-- Summary cards -->
<div class="row mb-3">
<div class="col-6 col-md-3">
    <div class="border rounded p-2 text-center bg-light">
        <div class="font-weight-bold text-primary" style="font-size:1.5rem;"><?php echo number_format($today_total); ?></div>
        <small class="text-muted"><?php echo __("adm_stat_today_access", $today_unique); ?></small>
    </div>
</div>
<div class="col-6 col-md-3">
    <div class="border rounded p-2 text-center bg-light">
        <div class="font-weight-bold text-secondary" style="font-size:1.5rem;"><?php echo number_format($yesterday_total); ?></div>
        <small class="text-muted"><?php echo __("adm_stat_yesterday_access", $yesterday_unique); ?></small>
    </div>
</div>
<div class="col-6 col-md-3">
    <div class="border rounded p-2 text-center bg-light">
        <div class="font-weight-bold text-info" style="font-size:1.5rem;"><?php echo number_format($week_total); ?></div>
        <small class="text-muted"><?php echo __("adm_stat_week_access", $week_unique); ?></small>
    </div>
</div>
<div class="col-6 col-md-3">
    <div class="border rounded p-2 text-center bg-light">
        <div class="font-weight-bold text-success" style="font-size:1.5rem;"><?php echo number_format($month_total); ?></div>
        <small class="text-muted"><?php echo __("adm_stat_month_access", $month_unique); ?></small>
    </div>
</div>
</div>

<!-- Tab navigation -->
<ul class="nav nav-tabs nav-fill mb-3" id="statsTab" role="tablist" style="font-size:13px;">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#stats-hourly"><?php echo __("adm_stats_hourly"); ?></a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#stats-daily"><?php echo __("adm_stats_daily"); ?></a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#stats-monthly"><?php echo __("adm_stats_monthly"); ?></a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#stats-yearly"><?php echo __("adm_stats_yearly"); ?></a></li>
</ul>

<div class="tab-content">
<!-- Hourly -->
<div class="tab-pane fade show active" id="stats-hourly">
<div class="table-responsive" style="max-height:250px;overflow-y:auto;">
<table class="table table-sm table-bordered mb-0" style="font-size:12px;">
<thead class="thead-light" style="position:sticky;top:0;">
<tr>
    <?php for ($h = 0; $h < 12; $h++): ?>
    <th class="text-center" style="width:8.33%;"><?php echo $h; ?><?php echo __('adm_hour_suffix'); ?></th>
    <?php endfor; ?>
</tr>
</thead>
<tbody>
<tr>
    <?php for ($h = 0; $h < 12; $h++): ?>
    <td class="text-center <?php echo $hourly_stats[$h] > 0 ? 'table-info' : ''; ?>"><?php echo $hourly_stats[$h]; ?></td>
    <?php endfor; ?>
</tr>
</tbody>
<thead class="thead-light">
<tr>
    <?php for ($h = 12; $h < 24; $h++): ?>
    <th class="text-center" style="width:8.33%;"><?php echo $h; ?><?php echo __('adm_hour_suffix'); ?></th>
    <?php endfor; ?>
</tr>
</thead>
<tbody>
<tr>
    <?php for ($h = 12; $h < 24; $h++): ?>
    <td class="text-center <?php echo $hourly_stats[$h] > 0 ? 'table-info' : ''; ?>"><?php echo $hourly_stats[$h]; ?></td>
    <?php endfor; ?>
</tr>
</tbody>
</table>
</div>
<small class="text-muted mt-2 d-block"><?php echo __("adm_stats_hourly_note", $today); ?></small>
</div>

<!-- Daily -->
<div class="tab-pane fade" id="stats-daily">
<div class="table-responsive" style="max-height:250px;overflow-y:auto;">
<table class="table table-sm table-bordered table-hover mb-0" style="font-size:12px;">
<thead class="thead-light" style="position:sticky;top:0;">
<tr>
    <th style="width:130px;"><?php echo __("adm_th_date"); ?></th>
    <th class="text-right"><?php echo __("adm_th_login_count"); ?></th>
    <th class="text-right"><?php echo __("adm_th_user_count"); ?></th>
</tr>
</thead>
<tbody>
<?php 
$display_count = 0;
foreach ($daily_stats as $date => $stat): 
    if ($display_count >= 30) break;
    $display_count++;
    $unique_count = count($stat['unique']);
    $is_today = ($date === $today);
    $is_yesterday = ($date === $yesterday);
?>
<tr<?php echo $is_today ? ' class="table-primary"' : ($is_yesterday ? ' class="table-secondary"' : ''); ?>>
    <td><?php echo h($date); ?><?php if ($is_today): ?> <span class="badge badge-primary"><?php echo __('adm_badge_today'); ?></span><?php endif; ?><?php if ($is_yesterday): ?> <span class="badge badge-secondary"><?php echo __('adm_badge_yesterday'); ?></span><?php endif; ?></td>
    <td class="text-right font-weight-bold"><?php echo number_format($stat['total']); ?></td>
    <td class="text-right"><?php echo $unique_count; ?><?php echo __('adm_unit_people'); ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($daily_stats)): ?>
<tr><td colspan="3" class="text-center text-muted py-3"><?php echo __("adm_no_access_records"); ?></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<small class="text-muted mt-2 d-block"><?php echo __("adm_stats_daily_note"); ?></small>
</div>

<!-- Monthly -->
<div class="tab-pane fade" id="stats-monthly">
<div class="table-responsive" style="max-height:250px;overflow-y:auto;">
<table class="table table-sm table-bordered table-hover mb-0" style="font-size:12px;">
<thead class="thead-light" style="position:sticky;top:0;">
<tr>
    <th style="width:130px;"><?php echo __("adm_th_month"); ?></th>
    <th class="text-right"><?php echo __("adm_th_login_count"); ?></th>
    <th class="text-right"><?php echo __("adm_th_user_count"); ?></th>
</tr>
</thead>
<tbody>
<?php 
$display_count = 0;
foreach ($monthly_stats as $month => $stat): 
    if ($display_count >= 12) break;
    $display_count++;
    $unique_count = count($stat['unique']);
    $is_this_month = ($month === $this_month);
?>
<tr<?php echo $is_this_month ? ' class="table-primary"' : ''; ?>>
    <td><?php echo h($month); ?><?php if ($is_this_month): ?> <span class="badge badge-primary"><?php echo __('adm_badge_this_month'); ?></span><?php endif; ?></td>
    <td class="text-right font-weight-bold"><?php echo number_format($stat['total']); ?></td>
    <td class="text-right"><?php echo $unique_count; ?><?php echo __('adm_unit_people'); ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($monthly_stats)): ?>
<tr><td colspan="3" class="text-center text-muted py-3"><?php echo __("adm_no_access_records"); ?></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<small class="text-muted mt-2 d-block"><?php echo __("adm_stats_monthly_note"); ?></small>
</div>

<!-- Yearly -->
<div class="tab-pane fade" id="stats-yearly">
<div class="table-responsive" style="max-height:250px;overflow-y:auto;">
<table class="table table-sm table-bordered table-hover mb-0" style="font-size:12px;">
<thead class="thead-light" style="position:sticky;top:0;">
<tr>
    <th style="width:130px;"><?php echo __("adm_th_year"); ?></th>
    <th class="text-right"><?php echo __("adm_th_login_count"); ?></th>
    <th class="text-right"><?php echo __("adm_th_user_count"); ?></th>
</tr>
</thead>
<tbody>
<?php 
foreach ($yearly_stats as $year => $stat): 
    $unique_count = count($stat['unique']);
    $is_this_year = ($year === $this_year);
?>
<tr<?php echo $is_this_year ? ' class="table-primary"' : ''; ?>>
    <td><?php echo h($year); ?><?php if ($is_this_year): ?> <span class="badge badge-primary"><?php echo __('adm_badge_this_year'); ?></span><?php endif; ?></td>
    <td class="text-right font-weight-bold"><?php echo number_format($stat['total']); ?></td>
    <td class="text-right"><?php echo $unique_count; ?><?php echo __('adm_unit_people'); ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($yearly_stats)): ?>
<tr><td colspan="3" class="text-center text-muted py-3"><?php echo __("adm_no_access_records"); ?></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>

<small class="text-muted mt-2 d-block"><?php echo __("adm_stats_yearly_note"); ?></small>
</div>
</div>

<!-- 📋 README.md -->
<div class="info-card mt-4">
<div class="card-header">📋 README.md</div>
<div class="card-body p-3 text-left" style="max-height:250px;overflow-y:auto">
<?php
if(file_exists("README.md")){
    $rm = file_get_contents("README.md");
    $sp = strpos($rm, "<h2 id=\"-1\">Updates</h2>");
    $allowed_tags = '<h1><h2><h3><h4><p><ul><ol><li><a><strong><em><code><pre>';
    echo $sp !== false ? strip_tags(substr($rm, $sp), $allowed_tags) : "...";
}else echo "<p class='text-muted'>README.md not found.</p>";
?>
</div></div>

</div></div>


<!-- Config -->
<div class="tab-pane fade" id="config">
<div class="card m-2 p-0">
<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
<?php echo csrf_field(); ?>
<div class="card-header bg-success text-white"><?php echo __('admin_settings_title'); ?></div>
<?php
// app_settings.json에서 직접 설정 로드
$_settings_file = __DIR__ . '/src/app_settings.json';
if (file_exists($_settings_file)) {
    $_loaded_settings = json_decode(file_get_contents($_settings_file), true) ?? [];
    if (isset($_loaded_settings['auto_logout'])) $auto_logout_settings = $_loaded_settings['auto_logout'];
    if (isset($_loaded_settings['darkmode'])) $darkmode_settings = $_loaded_settings['darkmode'];
    if (isset($_loaded_settings['txt_viewer'])) $txt_viewer_settings = $_loaded_settings['txt_viewer'];
    if (isset($_loaded_settings['epub_viewer'])) $epub_viewer_settings = $_loaded_settings['epub_viewer'];
    if (isset($_loaded_settings['list_font'])) $list_font_settings = $_loaded_settings['list_font'];
    if (isset($_loaded_settings['privacy_shield'])) $privacy_shield_settings = $_loaded_settings['privacy_shield'];
    // 개별 설정값 로드
    if (isset($_loaded_settings['maxview_folder'])) $maxview_folder = $_loaded_settings['maxview_folder'];
    if (isset($_loaded_settings['maxview_file'])) $maxview_file = $_loaded_settings['maxview_file'];
    if (isset($_loaded_settings['maxview_folder_mobile'])) $maxview_folder_mobile = $_loaded_settings['maxview_folder_mobile'];
    if (isset($_loaded_settings['maxview_file_mobile'])) $maxview_file_mobile = $_loaded_settings['maxview_file_mobile'];
    if (isset($_loaded_settings['new_badge_hours'])) $new_badge_hours = $_loaded_settings['new_badge_hours'];
    if (isset($_loaded_settings['max_bookmark'])) $max_bookmark = $_loaded_settings['max_bookmark'];
    if (isset($_loaded_settings['max_autosave'])) $max_autosave = $_loaded_settings['max_autosave'];
    if (isset($_loaded_settings['max_favorites'])) $max_favorites = $_loaded_settings['max_favorites'];
    if (isset($_loaded_settings['pages_per_group'])) $pages_per_group = $_loaded_settings['pages_per_group'];
    if (isset($_loaded_settings['pages_per_group_mobile'])) $pages_per_group_mobile = $_loaded_settings['pages_per_group_mobile'];
}
if(!isset($max_bookmark))$max_bookmark=10;if(!isset($max_autosave))$max_autosave=3;
if(!isset($max_favorites))$max_favorites=50;if(!isset($new_badge_hours))$new_badge_hours=24;
if(!isset($maxview_folder))$maxview_folder=50;if(!isset($maxview_file))$maxview_file=100;if(!isset($maxview_folder_mobile))$maxview_folder_mobile=30;if(!isset($maxview_file_mobile))$maxview_file_mobile=30;
if(!isset($pages_per_group))$pages_per_group=5;if(!isset($pages_per_group_mobile))$pages_per_group_mobile=3;
if(!isset($use_cover))$use_cover='n';if(!isset($use_listcover))$use_listcover='n';
if(!isset($ffmpeg_path))$ffmpeg_path='';if(!isset($ffprobe_path))$ffprobe_path='';
if(!isset($vips_path))$vips_path='';
if(!isset($unrar_path))$unrar_path='';if(!isset($sevenzip_path))$sevenzip_path='';
if(!isset($imgfolder_threshold))$imgfolder_threshold=5;if(!isset($video_folder_as_dir))$video_folder_as_dir=true;
if(!isset($darkmode_settings))$darkmode_settings=['enabled'=>true,'default'=>'light'];
if(!isset($auto_logout_settings))$auto_logout_settings=['enabled'=>true,'timeout'=>600];
if(!isset($logout_all_devices_settings))$logout_all_devices_settings=['enabled'=>false];
if(!isset($txt_viewer_settings))$txt_viewer_settings=['enabled'=>true,'chunk_size'=>102400,'default_font_size'=>18,'default_line_height'=>1.8];
if(!isset($epub_viewer_settings))$epub_viewer_settings=['enabled'=>true,'default_font_size'=>100,'default_theme'=>'light'];
if(!isset($privacy_shield_settings))$privacy_shield_settings=['enabled'=>true,'pages'=>['index.php','viewer.php'],'debug'=>false];
if(!isset($list_font_settings))$list_font_settings=['font_name'=>'','font_url'=>'','font_local'=>'','font_size'=>22];
if(!isset($base_dirs))$base_dirs=[$base_dir];
// 외부 프로그램 상태 확인 (파일 존재만 체크 - 빠름)
$ffmpeg_ok = !empty($ffprobe_path) && is_file($ffprobe_path);
$vips_ok = !empty($vips_path) && is_file($vips_path);
$unrar_ok = !empty($unrar_path) && is_file($unrar_path);
$sevenzip_ok = !empty($sevenzip_path) && is_file($sevenzip_path);
?>
<ul class="list-group list-group-flush">
<li class="list-group-item">
<table class="config-table" width="100%">

<!-- Multi-folder settings -->
<tr class="border-bottom"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_content_folders"); ?></strong> <small class="text-muted"><?php echo __("adm_help_multi_folders"); ?></small></td></tr>
<tr class="border-bottom"><td class="text-right align-top" style="width:80px;max-width:80px;">base_dirs</td><td class="">
<div id="base-dirs-container">
<?php 
$dirs = !empty($base_dirs) ? $base_dirs : [''];
foreach ($dirs as $idx => $dir): ?>
<div class="input-group mb-2 base-dir-row">
    <div class="input-group-prepend">
        <span class="input-group-text"><?php echo $idx === 0 ? __('adm_default') : $idx; ?></span>
    </div>
    <input type="text" name="base_dirs[]" class="form-control" value="<?php echo h($dir); ?>" placeholder="<?php echo __('adm_ph_example_path'); ?>">
    <?php if ($idx > 0): ?>
    <div class="input-group-append">
        <button type="button" class="btn btn-outline-danger btn-remove-dir" onclick="this.closest('.base-dir-row').remove()">✕</button>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<button type="button" class="btn btn-sm btn-outline-primary" onclick="addBaseDirRow()"><?php echo __("adm_btn_add_folder"); ?></button>
<small class="text-muted d-block mt-1"><?php echo __("adm_first_folder_default"); ?></small>
</td></tr>

<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_folder_list"); ?></td><td class=""><input type="number" name="maxview_folder" class="form-control" value="<?php echo h($maxview_folder); ?>" min="1" required><small class="text-muted">Folders per page in folder-only directories</small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_file_list"); ?></td><td class=""><input type="number" name="maxview_file" class="form-control" value="<?php echo h($maxview_file); ?>" min="1" required><small class="text-muted">Items per page in directories with files (99999999 = infinite scroll)</small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_folder_list_mobile"); ?></td><td class=""><input type="number" name="maxview_folder_mobile" class="form-control" value="<?php echo h($maxview_folder_mobile); ?>" min="1" required><small class="text-muted">Folders per page on mobile (folder-only)</small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_file_list_mobile"); ?></td><td class=""><input type="number" name="maxview_file_mobile" class="form-control" value="<?php echo h($maxview_file_mobile); ?>" min="1" required><small class="text-muted">Items per page on mobile (with files) (smaller value recommended)</small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_page_nav_pc"); ?></td><td class=""><input type="number" name="pages_per_group" class="form-control" value="<?php echo h($pages_per_group); ?>" min="1" required></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_page_nav_mobile"); ?></td><td class=""><input type="number" name="pages_per_group_mobile" class="form-control" value="<?php echo h($pages_per_group_mobile); ?>" min="1" required></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_max_autosave"); ?></td><td class=""><input type="number" name="max_autosave" class="form-control" value="<?php echo h($max_autosave); ?>" min="1" required></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_max_bookmark"); ?></td><td class=""><input type="number" name="max_bookmark" class="form-control" value="<?php echo h($max_bookmark); ?>" min="1" required></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_max_favorites"); ?></td><td class=""><input type="number" name="max_favorites" class="form-control" value="<?php echo h($max_favorites); ?>" min="1" required><small class="text-muted">Max bookmarks for folders/files</small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_new_badge_hours"); ?></td><td class=""><input type="number" name="new_badge_hours" class="form-control" value="<?php echo h($new_badge_hours); ?>" min="0" max="8760" required><small class="text-muted">Files added within N hours get <span class="badge badge-danger">N</span> badge (0 = disabled, default: 24h)</small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_folder_cover"); ?></td><td class="">
<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="use_cover" value="y" <?php if($use_cover=="y")echo"checked";?>><label class="form-check-label"><?php echo __("adm_label_enable"); ?></label></div>
<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="use_cover" value="n" <?php if($use_cover=="n")echo"checked";?>><label class="form-check-label"><?php echo __("adm_label_disable"); ?></label></div>
</td></tr>
<tr><td class="text-right"><?php echo __("adm_cfg_list_cover"); ?></td><td class="">
<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="use_listcover" value="y" <?php if($use_listcover=="y")echo"checked";?>><label class="form-check-label"><?php echo __("adm_label_enable"); ?></label></div>
<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="use_listcover" value="n" <?php if($use_listcover=="n")echo"checked";?>><label class="form-check-label"><?php echo __("adm_label_disable"); ?></label></div>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_ffmpeg"); ?></strong> <small class="text-muted"><?php echo __("adm_help_ffmpeg"); ?></small></td></tr>
<?php
$ffmpeg_ok_check = !empty($ffmpeg_path) && file_exists($ffmpeg_path);
$ffprobe_ok_check = !empty($ffprobe_path) && file_exists($ffprobe_path);
?>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_ffmpeg_path"); ?></td><td class="">
<input type="text" name="ffmpeg_path" class="form-control" value="<?php echo h($ffmpeg_path); ?>" placeholder="<?php echo __('adm_ph_ffmpeg'); ?>">
<small class="text-muted">
    <?php if($ffmpeg_ok_check): ?>
        <span class="text-success">✅ <?php echo __("adm_tool_available"); ?></span> - <?php echo __("adm_ffmpeg_available_desc"); ?>
    <?php elseif(!empty($ffmpeg_path)): ?>
        <span class="text-danger">❌ <?php echo __("adm_tool_check_path"); ?></span>
    <?php else: ?>
        <?php echo __("adm_ffmpeg_not_set"); ?>
    <?php endif; ?>
</small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_ffprobe_path"); ?></td><td class="">
<input type="text" name="ffprobe_path" class="form-control" value="<?php echo h($ffprobe_path); ?>" placeholder="<?php echo __('adm_ph_ffprobe'); ?>">
<small class="text-muted">
    <?php if($ffprobe_ok_check): ?>
        <span class="text-success">✅ <?php echo __("adm_tool_available"); ?></span> - <?php echo __("adm_ffprobe_detail"); ?>
    <?php elseif(!empty($ffprobe_path)): ?>
        <span class="text-danger">❌ <?php echo __("adm_tool_check_path"); ?></span>
    <?php else: ?>
        <?php echo __("adm_ffprobe_not_set"); ?>
    <?php endif; ?>
</small>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_vips"); ?></strong> <small class="text-muted"><?php echo __("adm_help_vips"); ?></small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_vips_path"); ?></td><td class="">
<input type="text" name="vips_path" class="form-control" value="<?php echo h($vips_path); ?>" placeholder="<?php echo __('adm_ph_vips'); ?>">
<small class="text-muted"><?php if($vips_ok): ?><span class="text-success"><?php echo __("adm_vips_available"); ?></span><?php elseif(!empty($vips_path)): ?><span class="text-danger">❌ <?php echo __("adm_tool_check_path"); ?></span><?php else: ?>Falls back to GD library if not set<?php endif; ?></small>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_archive_tools"); ?></strong> <small class="text-muted"><?php echo __("adm_help_archive"); ?></small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_unrar_path"); ?></td><td class="">
<input type="text" name="unrar_path" class="form-control" value="<?php echo h($unrar_path); ?>" placeholder="<?php echo __('adm_ph_unrar'); ?>">
<small class="text-muted"><?php if($unrar_ok): ?><span class="text-success"><?php echo __("adm_unrar_enabled"); ?></span><?php elseif(!empty($unrar_path)): ?><span class="text-danger">❌ <?php echo __("adm_tool_check_path"); ?></span><?php else: ?>RAR/CBR unsupported if not set<?php endif; ?></small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_7zip_path"); ?></td><td class="">
<input type="text" name="sevenzip_path" class="form-control" value="<?php echo h($sevenzip_path); ?>" placeholder="<?php echo __('adm_ph_7zip'); ?>">
<small class="text-muted"><?php if($sevenzip_ok): ?><span class="text-success"><?php echo __("adm_7zip_enabled"); ?></span><?php elseif(!empty($sevenzip_path)): ?><span class="text-danger">❌ <?php echo __("adm_tool_check_path"); ?></span><?php else: ?>7Z/CB7 unsupported if not set<?php endif; ?></small>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_folder_display"); ?></strong></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_img_threshold"); ?></td><td class="">
<input type="number" name="imgfolder_threshold" class="form-control" value="<?php echo h($imgfolder_threshold ?? 5); ?>" min="1" max="100" style="width:100px;display:inline-block;">
<small class="text-muted"><?php echo __("adm_help_img_threshold"); ?></small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_video_folder"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="video_folder_as_dir" name="video_folder_as_dir" <?php echo ($video_folder_as_dir ?? true) ? 'checked' : ''; ?>>
    <label class="custom-control-label" for="video_folder_as_dir"><?php echo __("adm_label_video_folder_icon"); ?></label>
</div>
<small class="text-muted"><?php echo __("adm_help_video_folder"); ?></small>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_darkmode"); ?></strong></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_darkmode"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="darkmode_enabled" name="darkmode_enabled" <?php echo ($darkmode_settings['enabled'] ?? true) ? 'checked' : ''; ?>>
    <label class="custom-control-label" for="darkmode_enabled"><?php echo __("adm_label_darkmode_toggle"); ?></label>
</div>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_default_theme"); ?></td><td class="">
<select name="darkmode_default" class="form-control" style="width:150px;">
    <option value="light" <?php if(($darkmode_settings['default']??'light')==='light')echo'selected';?>><?php echo __('adm_opt_light'); ?></option>
    <option value="dark" <?php if(($darkmode_settings['default']??'')==='dark')echo'selected';?>><?php echo __('adm_opt_dark'); ?></option>
</select>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_auto_logout"); ?></strong></td></tr>
<?php
// PHP 세션 타임아웃 값 미리 가져오기
$_php_session_timeout = (int)ini_get('session.gc_maxlifetime');
$_php_timeout_minutes = floor($_php_session_timeout / 60);
$_php_timeout_seconds = $_php_session_timeout % 60;
$_php_timeout_str = $_php_timeout_minutes > 0 ? "{$_php_timeout_minutes}" . __("adm_unit_min") : "";
$_php_timeout_str .= $_php_timeout_seconds > 0 ? " {$_php_timeout_seconds}" . __("adm_unit_sec") : "";
$_php_timeout_str = trim($_php_timeout_str) ?: "{$_php_session_timeout}" . __("adm_unit_sec");
?>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_auto_logout"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="auto_logout_enabled" name="auto_logout_enabled" <?php echo ($auto_logout_settings['enabled'] ?? true) ? 'checked' : ''; ?> onchange="toggleAutoLogoutTimeout()">
    <label class="custom-control-label" for="auto_logout_enabled"><?php echo __("adm_label_auto_logout"); ?></label>
</div>
<small class="text-muted">
    • <strong><?php echo __("adm_help_checked_pages"); ?></strong><br>
    • <strong><?php echo __("adm_help_unchecked_pages", $_php_timeout_str); ?></strong><br>
    • <strong><?php echo __("adm_help_disabled_pages", $_php_timeout_str); ?></strong><br>
    <strong><?php echo __("adm_help_remember_me"); ?></strong>
</small>
</td></tr>
<tr class="border-bottom" id="auto_logout_timeout_row"><td class="text-right"><?php echo __("adm_cfg_timeout"); ?></td><td class="">
<div style="display:flex; align-items:center; gap:10px;">
    <input type="number" id="auto_logout_minutes" class="form-control" value="<?php echo floor(($auto_logout_settings['timeout'] ?? 600) / 60); ?>" min="1" max="120" style="width:80px;" onchange="updateAutoLogoutTimeout()"> <?php echo __("adm_unit_min"); ?>
    <input type="number" id="auto_logout_seconds" class="form-control" value="<?php echo ($auto_logout_settings['timeout'] ?? 600) % 60; ?>" min="0" max="59" style="width:80px;" onchange="updateAutoLogoutTimeout()"> <?php echo __("adm_unit_sec"); ?>
    <input type="hidden" name="auto_logout_timeout" id="auto_logout_timeout" value="<?php echo $auto_logout_settings['timeout'] ?? 600; ?>">
    <span class="text-muted" id="auto_logout_total">(<?php echo __("adm_total"); ?> <?php echo $auto_logout_settings['timeout'] ?? 600; ?><?php echo __("adm_unit_sec"); ?>)</span>
</div>
<small class="text-muted"><?php echo __("adm_help_timeout_range"); ?></small>
</td></tr>
<tr class="border-bottom" id="auto_logout_pages_row"><td class="text-right align-top"><?php echo __("adm_cfg_apply_pages"); ?></td><td class="">
<?php
$auto_logout_pages = $auto_logout_settings['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
$page_labels = [
    'index.php' => __('adm_page_index'),
    'viewer.php' => __('adm_page_viewer'),
    'epub_viewer.php' => __('adm_page_epub'),
    'txt_viewer.php' => __('adm_page_txt'),
    'admin.php' => __('adm_cfg_page_admin'),
    'admin_translations.php' => __('adm_cfg_page_translations'),
    'bookmark.php' => __('adm_cfg_page_bookmark'),
    'blank.php' => __('adm_cfg_page_blank')
];
?>
<div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:2px; text-align:left;">
<?php foreach ($page_labels as $page => $label): ?>
    <div class="custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" 
               id="auto_logout_page_<?php echo str_replace('.', '_', $page); ?>" 
               name="auto_logout_pages[]" 
               value="<?php echo $page; ?>"
               <?php echo in_array($page, $auto_logout_pages) ? 'checked' : ''; ?>>
        <label class="custom-control-label" for="auto_logout_page_<?php echo str_replace('.', '_', $page); ?>"><?php echo $label; ?></label>
    </div>
<?php endforeach; ?>
</div>
<small class="text-muted">
    • <?php echo __("adm_help_checked_short"); ?><br>
    • <?php echo __("adm_help_unchecked_short"); ?> <strong><?php echo $_php_timeout_str; ?></strong> (session.gc_maxlifetime=<?php echo $_php_session_timeout; ?>s) <?php echo __("adm_after_logout"); ?>
</small>
</td></tr>

<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_logout_all_devices"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="logout_all_devices_enabled" name="logout_all_devices_enabled" <?php echo ($logout_all_devices_settings['enabled'] ?? false) ? 'checked' : ''; ?>>
    <label class="custom-control-label" for="logout_all_devices_enabled"><?php echo __("adm_label_show_logout_all"); ?></label>
</div>
<small class="text-muted">
    <?php echo __("adm_cfg_multi_logout_desc1"); ?><br>
    <?php echo __("adm_cfg_multi_logout_desc2"); ?>
</small>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_privacy"); ?></strong> <small class="text-muted"><?php echo __("adm_privacy_desc"); ?></small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_privacy_enable"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="privacy_shield_enabled" name="privacy_shield_enabled" <?php echo ($privacy_shield_settings['enabled'] ?? true) ? 'checked' : ''; ?> onchange="togglePrivacyShieldPages()">
    <label class="custom-control-label" for="privacy_shield_enabled"><?php echo __("adm_label_privacy_shield"); ?></label>
</div>
<small class="text-muted"><?php echo __("adm_cfg_privacy_help"); ?></small>
</td></tr>
<tr class="border-bottom" id="privacy_shield_debug_row"><td class="text-right"><?php echo __("adm_cfg_debug_mode"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="privacy_shield_debug" name="privacy_shield_debug" <?php echo ($privacy_shield_settings['debug'] ?? false) ? 'checked' : ''; ?>>
    <label class="custom-control-label" for="privacy_shield_debug"><?php echo __("adm_label_debug_panel"); ?></label>
</div>
<small class="text-muted"><?php echo __("adm_cfg_debug_help"); ?></small>
</td></tr>
<tr class="border-bottom" id="privacy_shield_pages_row"><td class="text-right align-top"><?php echo __("adm_cfg_apply_pages"); ?></td><td class="">
<?php
$privacy_shield_pages = $privacy_shield_settings['pages'] ?? ['index.php', 'viewer.php'];
$privacy_page_labels = [
    'index.php' => __('adm_page_index'),
    'viewer.php' => __('adm_page_viewer'),
    'epub_viewer.php' => __('adm_page_epub'),
    'txt_viewer.php' => __('adm_page_txt')
];
?>
<div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:2px; text-align:left;">
<?php foreach ($privacy_page_labels as $page => $label): ?>
    <div class="custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" 
               id="privacy_shield_page_<?php echo str_replace('.', '_', $page); ?>" 
               name="privacy_shield_pages[]" 
               value="<?php echo $page; ?>"
               <?php echo in_array($page, $privacy_shield_pages) ? 'checked' : ''; ?>>
        <label class="custom-control-label" for="privacy_shield_page_<?php echo str_replace('.', '_', $page); ?>"><?php echo $label; ?></label>
    </div>
<?php endforeach; ?>
</div>
<small class="text-muted"><?php echo __("adm_cfg_privacy_pages_help"); ?></small>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_txt_viewer"); ?></strong> <small class="text-muted"><?php echo __("adm_txt_desc"); ?></small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_txt_viewer"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="txt_viewer_enabled" name="txt_viewer_enabled" <?php echo ($txt_viewer_settings['enabled'] ?? true) ? 'checked' : ''; ?>>
    <label class="custom-control-label" for="txt_viewer_enabled"><?php echo __("adm_label_txt_viewer"); ?></label>
</div>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_chunk_size"); ?></td><td class="">
<input type="number" name="txt_chunk_size" class="form-control" value="<?php echo h($txt_viewer_settings['chunk_size'] ?? 102400); ?>" min="10240" style="width:150px;display:inline-block;"> bytes
<small class="text-muted d-block"><?php echo __("adm_txt_chunk_help"); ?></small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_font_size"); ?></td><td class="">
<input type="number" name="txt_font_size" class="form-control" value="<?php echo h($txt_viewer_settings['default_font_size'] ?? 18); ?>" min="10" max="40" style="width:100px;display:inline-block;"> px
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_line_height"); ?></td><td class="">
<input type="number" name="txt_line_height" class="form-control" value="<?php echo h($txt_viewer_settings['default_line_height'] ?? 1.8); ?>" min="1.0" max="3.0" step="0.1" style="width:100px;display:inline-block;">
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_custom_font"); ?></td><td class="">
<div class="mb-2">
    <label class="small text-muted"><?php echo __("adm_cfg_font_name"); ?></label>
    <input type="text" name="txt_font_name" class="form-control form-control-sm" value="<?php echo h($txt_viewer_settings['font_name'] ?? ''); ?>" placeholder="e.g. Nanum Myeongjo, KoPub Batang">
</div>
<div class="mb-2">
    <label class="small text-muted"><?php echo __("adm_cfg_font_url"); ?></label>
    <input type="text" name="txt_font_url" class="form-control form-control-sm" value="<?php echo h($txt_viewer_settings['font_url'] ?? ''); ?>" placeholder="e.g. https://fonts.googleapis.com/css2?family=...">
</div>
<div class="mb-1">
    <label class="small text-muted"><?php echo __("adm_label_font_path"); ?></label>
    <input type="text" name="txt_font_local" class="form-control form-control-sm" value="<?php echo h($txt_viewer_settings['font_local'] ?? ''); ?>" placeholder="e.g. ./fonts/custom.woff2">
</div>
<small class="text-muted"><?php echo __("adm_cfg_font_help"); ?></small>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_epub_viewer"); ?></strong></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_epub_viewer"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="epub_viewer_enabled" name="epub_viewer_enabled" <?php echo ($epub_viewer_settings['enabled'] ?? true) ? 'checked' : ''; ?>>
    <label class="custom-control-label" for="epub_viewer_enabled"><?php echo __("adm_label_epub_viewer"); ?></label>
</div>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_font_size"); ?></td><td class="">
<input type="number" name="epub_font_size" class="form-control" value="<?php echo h($epub_viewer_settings['default_font_size'] ?? 100); ?>" min="50" max="200" style="width:100px;display:inline-block;"> %
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_default_theme"); ?></td><td class="">
<select name="epub_theme" class="form-control" style="width:150px;">
    <option value="light" <?php if(($epub_viewer_settings['default_theme']??'light')==='light')echo'selected';?>><?php echo __('adm_opt_light'); ?></option>
    <option value="sepia" <?php if(($epub_viewer_settings['default_theme']??'')==='sepia')echo'selected';?>><?php echo __("adm_filter_sepia"); ?></option>
    <option value="dark" <?php if(($epub_viewer_settings['default_theme']??'')==='dark')echo'selected';?>><?php echo __('adm_opt_dark'); ?></option>
</select>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_custom_font"); ?></td><td class="">
<div class="mb-2">
    <label class="small text-muted"><?php echo __("adm_cfg_font_name"); ?></label>
    <input type="text" name="epub_font_name" class="form-control form-control-sm" value="<?php echo h($epub_viewer_settings['font_name'] ?? ''); ?>" placeholder="e.g. Nanum Myeongjo, KoPub Batang">
</div>
<div class="mb-2">
    <label class="small text-muted"><?php echo __("adm_cfg_font_url"); ?></label>
    <input type="text" name="epub_font_url" class="form-control form-control-sm" value="<?php echo h($epub_viewer_settings['font_url'] ?? ''); ?>" placeholder="e.g. https://fonts.googleapis.com/css2?family=...">
</div>
<div class="mb-1">
    <label class="small text-muted"><?php echo __("adm_label_font_path"); ?></label>
    <input type="text" name="epub_font_local" class="form-control form-control-sm" value="<?php echo h($epub_viewer_settings['font_local'] ?? ''); ?>" placeholder="e.g. ./fonts/custom.woff2">
</div>
<small class="text-muted"><?php echo __("adm_cfg_font_help"); ?></small>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_list_font"); ?></strong> <small class="text-muted"><?php echo __("adm_list_font_desc"); ?></small></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_font_size"); ?></td><td class="">
<input type="number" name="list_font_size" class="form-control" value="<?php echo h($list_font_settings['font_size'] ?? 22); ?>" min="12" max="40" style="width:100px;display:inline-block;"> px
<small class="text-muted d-block"><?php echo __("adm_cfg_font_size_help"); ?></small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_custom_font"); ?></td><td class="">
<div class="mb-2">
    <label class="small text-muted"><?php echo __("adm_cfg_font_name"); ?></label>
    <input type="text" name="list_font_name" class="form-control form-control-sm" value="<?php echo h($list_font_settings['font_name'] ?? ''); ?>" placeholder="e.g. Nanum Gothic, Dongle">
</div>
<div class="mb-2">
    <label class="small text-muted"><?php echo __("adm_cfg_font_url"); ?></label>
    <input type="text" name="list_font_url" class="form-control form-control-sm" value="<?php echo h($list_font_settings['font_url'] ?? ''); ?>" placeholder="e.g. https://fonts.googleapis.com/css2?family=Nanum+Gothic">
</div>
<div class="mb-1">
    <label class="small text-muted"><?php echo __("adm_label_font_path"); ?></label>
    <input type="text" name="list_font_local" class="form-control form-control-sm" value="<?php echo h($list_font_settings['font_local'] ?? ''); ?>" placeholder="e.g. ./fonts/custom.woff2">
</div>
<small class="text-muted"><?php echo __("adm_cfg_font_default"); ?></small>
</td></tr>

<tr class="border-top"><td colspan="2" class="bg-light"><strong><?php echo __("adm_section_member"); ?></strong></td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_allow_register"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="registration_enabled" name="registration_enabled" <?php 
        $reg_settings = $_loaded_settings['registration'] ?? ['enabled' => false, 'require_approval' => true];
        echo ($reg_settings['enabled'] ?? false) ? 'checked' : ''; 
    ?> onchange="toggleRegistrationOptions()">
    <label class="custom-control-label" for="registration_enabled"><?php echo __("adm_label_allow_register"); ?></label>
</div>
<small class="text-muted"><?php echo __("adm_cfg_register_help"); ?></small>
</td></tr>
<tr class="border-bottom" id="registration_approval_row"><td class="text-right"><?php echo __("adm_cfg_require_approval"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="registration_require_approval" name="registration_require_approval" <?php 
        echo ($reg_settings['require_approval'] ?? true) ? 'checked' : ''; 
    ?>>
    <label class="custom-control-label" for="registration_require_approval"><?php echo __("adm_cfg_require_approval"); ?></label>
</div>
<small class="text-muted"><?php echo __("adm_cfg_approval_help"); ?></small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_find_id"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="find_id_enabled" name="find_id_enabled" <?php 
        echo ($reg_settings['find_id_enabled'] ?? true) ? 'checked' : ''; 
    ?>>
    <label class="custom-control-label" for="find_id_enabled"><?php echo __("adm_label_allow_find_id"); ?></label>
</div>
<small class="text-muted"><?php echo __("adm_cfg_find_id_help"); ?></small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_find_pw"); ?></td><td class="">
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="find_password_enabled" name="find_password_enabled" <?php 
        echo ($reg_settings['find_password_enabled'] ?? true) ? 'checked' : ''; 
    ?>>
    <label class="custom-control-label" for="find_password_enabled"><?php echo __("adm_label_allow_find_pw"); ?></label>
</div>
<small class="text-muted"><?php echo __("adm_cfg_find_pw_help"); ?></small>
</td></tr>

<script>
function toggleRegistrationOptions() {
    const enabled = document.getElementById('registration_enabled').checked;
    document.getElementById('registration_approval_row').style.opacity = enabled ? '1' : '0.5';
}
document.addEventListener('DOMContentLoaded', toggleRegistrationOptions);
</script>

</table>
</li>
<li class="list-group-item p-0"><input type="hidden" name="mode" value="config_change"><button class="btn btn-success btn-block btn-sm" type="submit"><?php echo __("adm_btn_change_settings"); ?></button></li>
</ul>

<script>
function addBaseDirRow() {
    const container = document.getElementById('base-dirs-container');
    const count = container.querySelectorAll('.base-dir-row').length;
    const div = document.createElement('div');
    div.className = 'input-group mb-2 base-dir-row';
    div.innerHTML = `
        <div class="input-group-prepend">
            <span class="input-group-text">${count}</span>
        </div>
        <input type="text" name="base_dirs[]" class="form-control" placeholder="e.g. f:/manga">
        <div class="input-group-append">
            <button type="button" class="btn btn-outline-danger btn-remove-dir" onclick="this.closest('.base-dir-row').remove()">✕</button>
        </div>
    `;
    container.appendChild(div);
}
</script>

</form></div></div>


<!-- Cache management -->
<div class="tab-pane fade" id="cache">
<div class="container-fluid">

<!-- 1. Thumbnail cache (filename.json) -->
<div class="info-card mb-3">
<div class="card-header text-white" style="font-size:18px;font-weight:600;background:#3498db;"><?php echo __("adm_card_thumb_cache"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_thumb_desc"); ?></p>
<div class="btn-group w-100 mb-2">
<button class="btn" onclick="runThumbnailCache(false)" id="btn-thumb-quick" style="border:1px solid #3498db;color:#3498db;background:white;">
<small><?php echo __("adm_cache_quick_btn"); ?></small>
</button>
<button class="btn" onclick="runThumbnailCache(true)" id="btn-thumb-force" style="background:#3498db;color:white;">
<small><?php echo __("adm_cache_regen_btn"); ?></small>
</button>
</div>
<div id="thumb-progress" style="display:none;">
<div class="progress mb-2" style="height:25px;">
<div id="thumb-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#3498db;">0%</div>
</div>
<div id="thumb-status" class="small text-muted"></div>
<div id="thumb-current" class="small text-info" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;"></div>
</div>
<div id="thumb-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- 2. Cover image ([cover].jpg) -->
<div class="info-card mb-3">
<div class="card-header text-white" style="font-size:18px;font-weight:600;background:#27ae60;"><?php echo __("adm_card_cover_cache"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_cover_desc"); ?></p>
<div class="btn-group w-100 mb-2">
<button class="btn" onclick="runCoverCache(false)" id="btn-cover-quick" style="border:1px solid #27ae60;color:#27ae60;background:white;">
<small><?php echo __("adm_cache_quick_btn"); ?></small>
</button>
<button class="btn" onclick="runCoverCache(true)" id="btn-cover-force" style="background:#27ae60;color:white;">
<small><?php echo __("adm_cache_regen_btn"); ?></small>
</button>
</div>
<div id="cover-progress" style="display:none;">
<div class="progress mb-2" style="height:25px;">
<div id="cover-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#27ae60;">0%</div>
</div>
<div id="cover-status" class="small text-muted"></div>
<div id="cover-current" class="small text-info" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;"></div>
</div>
<div id="cover-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- 3. Selective batch run -->
<div class="info-card mb-3">
<div class="card-header text-white" style="font-size:18px;font-weight:600;background:#8e44ad;"><?php echo __("adm_card_selective_run"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_selective_desc"); ?></p>

<div class="row mb-3">
<div class="col-6 col-md-4">
<div class="form-check"><input class="form-check-input" type="checkbox" id="chk-thumb" checked><label class="form-check-label" for="chk-thumb"><?php echo __("adm_cache_chk_thumb"); ?></label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="chk-cover" checked><label class="form-check-label" for="chk-cover"><?php echo __("adm_cache_chk_cover"); ?></label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="chk-filelist"><label class="form-check-label" for="chk-filelist"><?php echo __("adm_cache_chk_filelist_full"); ?></label></div>
</div>
<div class="col-6 col-md-4">
<div class="form-check"><input class="form-check-input" type="checkbox" id="chk-search"><label class="form-check-label" for="chk-search"><?php echo __("adm_cache_chk_search_full"); ?></label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="chk-ziptotal"><label class="form-check-label" for="chk-ziptotal"><?php echo __("adm_cache_chk_stats"); ?></label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="chk-folder"><label class="form-check-label" for="chk-folder"><?php echo __("adm_cache_chk_folder_full"); ?></label></div>
</div>
<div class="col-6 col-md-4">
<div class="form-check"><input class="form-check-input" type="checkbox" id="chk-imgfiles"><label class="form-check-label" for="chk-imgfiles"><?php echo __("adm_cache_chk_zip_img"); ?></label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="chk-vidfiles"><label class="form-check-label" for="chk-vidfiles"><?php echo __("adm_cache_chk_zip_vid"); ?></label></div>
</div>
</div>

<div class="btn-group w-100 mb-2">
<button class="btn" onclick="runSelectedCache(false)" id="btn-selected-quick" style="border:1px solid #8e44ad;color:#8e44ad;background:white;">
<small><?php echo __("adm_cache_quick_btn"); ?></small>
</button>
<button class="btn" onclick="runSelectedCache(true)" id="btn-selected-force" style="background:#8e44ad;color:white;">
<small><?php echo __("adm_cache_regen_btn"); ?></small>
</button>
</div>

<div id="selected-progress" style="display:none;">
<div class="mb-2">
<span id="selected-phase" class="badge badge-info">1/3</span>
<span id="selected-task" class="badge badge-secondary ml-1"><?php echo __('adm_cache_label_thumb'); ?></span>
<span id="selected-status" class="small text-muted ml-2"></span>
</div>
<div class="progress mb-2" style="height:25px;">
<div id="selected-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#8e44ad;">0%</div>
</div>
<div id="selected-current" class="small text-info" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;"></div>
</div>
<div id="selected-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- 4. Folder-specific run -->
<div class="info-card mb-3">
<div class="card-header text-white" style="font-size:18px;font-weight:600;background:#16a085;"><?php echo __("adm_card_folder_run"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_folder_select_desc"); ?></p>

<div class="input-group mb-2">
<div class="input-group-prepend"><span class="input-group-text">📁</span></div>
<input type="text" class="form-control" id="target-folder" placeholder="<?php echo __('adm_ph_folder_path'); ?>" value="/" readonly style="background:#f8f9fa;">
<input type="hidden" id="target-bidx" value="-1">
<div class="input-group-append">
<button class="btn btn-outline-secondary" type="button" onclick="showFolderBrowser()"><?php echo __("adm_btn_browse"); ?></button>
</div>
</div>
<div class="mb-3 small text-muted">
<span id="target-folder-display"><?php echo __("adm_cache_all_root"); ?></span>
</div>

<div class="row mb-3">
<div class="col-6 col-md-4">
<div class="form-check"><input class="form-check-input" type="checkbox" id="folder-chk-thumb" checked><label class="form-check-label" for="folder-chk-thumb"><?php echo __("adm_cache_chk_thumb"); ?></label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="folder-chk-cover" checked><label class="form-check-label" for="folder-chk-cover"><?php echo __("adm_cache_chk_cover"); ?></label></div>
</div>
<div class="col-6 col-md-4">
<div class="form-check"><input class="form-check-input" type="checkbox" id="folder-chk-filelist"><label class="form-check-label" for="folder-chk-filelist"><?php echo __("adm_cache_chk_filelist_full"); ?></label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="folder-chk-folder"><label class="form-check-label" for="folder-chk-folder"><?php echo __("adm_cache_chk_folder_full"); ?></label></div>
</div>
<div class="col-6 col-md-4">
<div class="form-check"><input class="form-check-input" type="checkbox" id="folder-chk-imgfiles"><label class="form-check-label" for="folder-chk-imgfiles"><?php echo __("adm_cache_chk_zip_img"); ?></label></div>
<div class="form-check"><input class="form-check-input" type="checkbox" id="folder-chk-vidfiles"><label class="form-check-label" for="folder-chk-vidfiles"><?php echo __("adm_cache_chk_zip_vid"); ?></label></div>
</div>
</div>

<div class="form-check mb-3">
<input class="form-check-input" type="checkbox" id="folder-recursive" checked>
<label class="form-check-label" for="folder-recursive"><?php echo __("adm_cache_include_sub"); ?></label>
</div>

<div class="btn-group w-100 mb-2">
<button class="btn" onclick="runFolderTargetCache(false)" id="btn-folder-target-quick" style="border:1px solid #16a085;color:#16a085;background:white;">
<small><?php echo __("adm_cache_quick_btn"); ?></small>
</button>
<button class="btn" onclick="runFolderTargetCache(true)" id="btn-folder-target-force" style="background:#16a085;color:white;">
<small><?php echo __("adm_cache_regen_btn"); ?></small>
</button>
</div>

<div id="folder-target-progress" style="display:none;">
<div class="mb-2">
<span id="folder-target-phase" class="badge badge-info">1/3</span>
<span id="folder-target-task" class="badge badge-secondary ml-1"><?php echo __('adm_cache_label_thumb'); ?></span>
<span id="folder-target-status" class="small text-muted ml-2"></span>
</div>
<div class="progress mb-2" style="height:25px;">
<div id="folder-target-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#16a085;">0%</div>
</div>
<div id="folder-target-current" class="small text-info" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;"></div>
</div>
<div id="folder-target-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- Folder browser modal -->
<div class="modal fade" id="folderBrowserModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><?php echo __("adm_heading_folder_select"); ?></h5>
<button type="button" class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body" style="max-height:400px;overflow-y:auto;">
<div id="folder-browser-content"><?php echo __("adm_loading"); ?></div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __("common_cancel"); ?></button>
</div>
</div>
</div>
</div>

<hr class="my-4">

<!-- 5. Search index -->
<div class="info-card mb-3">
<div class="card-header text-white" style="font-size:18px;font-weight:600;background:#2980b9;">🔍 <?php echo __("adm_card_search_index"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_search_desc"); ?></p>
<div class="btn-group w-100 mb-2">
<button class="btn" onclick="runSearchIndex()" id="btn-search-index" style="background:#2980b9;color:white;">
<small><?php echo __("adm_cache_rescan_btn"); ?></small>
</button>
</div>
<div id="search-progress" style="display:none;">
<div class="progress mb-2" style="height:25px;">
<div id="search-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#2980b9;">0%</div>
</div>
<div id="search-status" class="small text-muted"></div>
<div id="search-current" class="small text-info" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;"></div>
</div>
<div id="search-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- 5. Statistics (zip_total.json) -->
<div class="info-card mb-3">
<div class="card-header text-white" style="font-size:18px;font-weight:600;background:#9b59b6;">📊 <?php echo __("adm_card_zip_stats"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_stats_desc"); ?></p>
<div class="btn-group w-100 mb-2">
<button class="btn" onclick="runZipTotal()" id="btn-zip-total" style="background:#9b59b6;color:white;">
<small><?php echo __("adm_cache_recount_btn"); ?></small>
</button>
</div>
<div id="ziptotal-progress" style="display:none;">
<div class="progress mb-2" style="height:25px;">
<div id="ziptotal-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#9b59b6;">0%</div>
</div>
<div id="ziptotal-status" class="small text-muted"></div>
</div>
<div id="ziptotal-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- 6. File list cache -->
<div class="info-card mb-3">
<div class="card-header text-white" style="font-size:18px;font-weight:600;background:#e67e22;">📋 <?php echo __("adm_card_filelist_cache"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_filelist_desc"); ?></p>
<div class="btn-group w-100 mb-2">
<button class="btn" onclick="runFilelistCache(false)" id="btn-filelist-quick" style="border:1px solid #e67e22;color:#e67e22;background:white;">
<small><?php echo __("adm_cache_quick_btn"); ?></small>
</button>
<button class="btn" onclick="runFilelistCache(true)" id="btn-filelist-force" style="background:#e67e22;color:white;">
<small><?php echo __("adm_cache_regen_btn"); ?></small>
</button>
</div>
<div id="filelist-progress" style="display:none;">
<div class="progress mb-2" style="height:25px;">
<div id="filelist-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#e67e22;">0%</div>
</div>
<div id="filelist-status" class="small text-muted"></div>
<div id="filelist-current" class="small text-info" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;"></div>
</div>
<div id="filelist-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- 7. Folder cache -->
<div class="info-card mb-3">
<div class="card-header text-white" style="font-size:18px;font-weight:600;background:#6c757d;">📁 <?php echo __("adm_card_folder_cache"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_folderinfo_desc"); ?></p>
<div class="btn-group w-100 mb-2">
<button class="btn" onclick="runFolderCache(false)" id="btn-folder-quick" style="border:1px solid #6c757d;color:#6c757d;background:white;">
<small><?php echo __("adm_cache_quick_btn"); ?></small>
</button>
<button class="btn" onclick="runFolderCache(true)" id="btn-folder-force" style="background:#6c757d;color:white;">
<small><?php echo __("adm_cache_regen_btn"); ?></small>
</button>
</div>
<div id="folder-progress" style="display:none;">
<div class="progress mb-2" style="height:25px;">
<div id="folder-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#6c757d;">0%</div>
</div>
<div id="folder-status" class="small text-muted"></div>
<div id="folder-current" class="small text-info" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;"></div>
</div>
<div id="folder-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- 7. ZIP image list cache -->
<div class="info-card mb-3">
<div class="card-header" style="font-size:18px;font-weight:600;background:#6f42c1;color:white;">🗂️ <?php echo __("adm_card_zip_images"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_zipimg_desc"); ?></p>
<div class="btn-group w-100 mb-2">
<button class="btn btn-outline-purple" onclick="runImageFilesCache(false)" id="btn-imgfiles-quick" style="border-color:#6f42c1;color:#6f42c1;">
<small><?php echo __("adm_cache_quick_btn"); ?></small>
</button>
<button class="btn" onclick="runImageFilesCache(true)" id="btn-imgfiles-force" style="background:#6f42c1;color:white;">
<small><?php echo __("adm_cache_regen_btn"); ?></small>
</button>
</div>
<div id="imgfiles-progress" style="display:none;">
<div class="progress mb-2" style="height:25px;">
<div id="imgfiles-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#6f42c1;">0%</div>
</div>
<div id="imgfiles-status" class="small text-muted"></div>
<div id="imgfiles-current" class="small text-info" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;"></div>
</div>
<div id="imgfiles-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- 8. ZIP video list cache -->
<div class="info-card mb-3">
<div class="card-header" style="font-size:18px;font-weight:600;background:#e83e8c;color:white;">🎬 <?php echo __("adm_card_zip_videos"); ?></div>
<div class="card-body">
<p class="mb-2" style="color:#333;font-weight:500;font-size:14px;"><?php echo __("adm_cache_zipvid_desc"); ?></p>
<div class="btn-group w-100 mb-2">
<button class="btn" onclick="runVideoFilesCache(false)" id="btn-vidfiles-quick" style="border:1px solid #e83e8c;color:#e83e8c;background:white;">
<small><?php echo __("adm_cache_quick_btn"); ?></small>
</button>
<button class="btn" onclick="runVideoFilesCache(true)" id="btn-vidfiles-force" style="background:#e83e8c;color:white;">
<small><?php echo __("adm_cache_regen_btn"); ?></small>
</button>
</div>
<div id="vidfiles-progress" style="display:none;">
<div class="progress mb-2" style="height:25px;">
<div id="vidfiles-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;background:#e83e8c;">0%</div>
</div>
<div id="vidfiles-status" class="small text-muted"></div>
<div id="vidfiles-current" class="small text-info" style="max-height:60px;overflow-y:auto;font-family:monospace;font-size:11px;"></div>
</div>
<div id="vidfiles-result" class="mt-2" style="display:none;"></div>
</div>
</div>

<!-- Cache descriptions -->
<div class="info-card mt-3">
<div class="card-header"><?php echo __("adm_card_cache_desc"); ?></div>
<div class="card-body p-0">
<table class="table table-sm table-striped mb-0">
<thead><tr><th><?php echo __("adm_th_filename"); ?></th><th><?php echo __("adm_th_description"); ?></th><th><?php echo __("adm_th_create_btn"); ?></th></tr></thead>
<tbody>
<tr style="background:#cce5ff;"><td><code>filename.json</code></td><td><?php echo __("adm_cachedesc_thumb"); ?></td><td>🖼️ Thumbnail Cache</td></tr>
<tr style="background:#d4edda;"><td><code>[cover].jpg</code></td><td><?php echo __("adm_cachedesc_cover"); ?></td><td>🎨 Cover Image</td></tr>
<tr style="background:#bee5eb;"><td><code>search_index_{bidx}.json</code></td><td><?php echo __("adm_cachedesc_search"); ?></td><td>🔍 Search Index</td></tr>
<tr style="background:#c3f5e8;"><td><code>zip_total.json</code></td><td><?php echo __("adm_cachedesc_stats"); ?></td><td><?php echo __("adm_cache_chk_stats"); ?></td></tr>
<tr style="background:#fdebd0;"><td><code>.filelist_cache.json</code></td><td><?php echo __("adm_cachedesc_filelist"); ?></td><td>📋 File List</td></tr>
<tr style="background:#d6d8db;"><td><code>.folder_cache.json</code></td><td><?php echo __("adm_cachedesc_folder"); ?></td><td>📁 Folder Cache</td></tr>
<tr style="background:#e2d5f1;"><td><code>filename.zip.image_files.json</code></td><td><?php echo __("adm_cachedesc_zip_img"); ?></td><td>🗂️ ZIP Images</td></tr>
<tr style="background:#f8d7e3;"><td><code>filename.zip.video_files.json</code></td><td><?php echo __("adm_cachedesc_zip_vid"); ?></td><td>🎬 ZIP Videos</td></tr>
</tbody>
</table>
</div>
</div>

<!-- Mode descriptions -->
<div class="info-card mt-3">
<div class="card-header"><?php echo __("adm_card_mode_desc"); ?></div>
<div class="card-body">
<div class="row">
<div class="col-md-6">
<div class="p-3 border rounded mb-2">
<strong><?php echo __("adm_cache_quick_generate"); ?></strong>
<p class="small text-muted mb-0"><?php echo __("adm_cache_quick_help"); ?></p>
</div>
</div>
<div class="col-md-6">
<div class="p-3 border rounded mb-2">
<strong><?php echo __("adm_cache_mode_regen_label"); ?></strong>
<p class="small text-muted mb-0"><?php echo __("adm_cache_regen_help"); ?></p>
</div>
</div>
</div>
</div>
</div>
</div>

<script>
// ✅ 전체 base_dirs 처리를 위한 배열
const allBidxs = <?php echo json_encode(array_keys($base_dirs)); ?>;
const baseDirsCount = <?php echo count($base_dirs); ?>;
const baseDirNames = <?php echo json_encode(array_map('basename', $base_dirs)); ?>;

// 시간 포맷 함수 (초 -> 시:분:초)
function formatElapsedTime(seconds) {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = (seconds % 60).toFixed(1);
    
    if (hrs > 0) {
        return hrs + 'h ' + mins + 'm ' + secs + 's';
    } else if (mins > 0) {
        return mins + 'm ' + secs + 's';
    } else {
        return secs + 's';
    }
}

// 썸네일 캐시 생성 (파일명.json) - 전체 base_dirs 순차 처리
function runThumbnailCache(force) {
    const progressDiv = document.getElementById('thumb-progress');
    const progressBar = document.getElementById('thumb-progress-bar');
    const statusDiv = document.getElementById('thumb-status');
    const currentDiv = document.getElementById('thumb-current');
    const resultDiv = document.getElementById('thumb-result');
    
    document.getElementById('btn-thumb-quick').disabled = true;
    document.getElementById('btn-thumb-force').disabled = true;
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    statusDiv.textContent = '<?php echo __("adm_loading"); ?>';
    currentDiv.innerHTML = '';
    
    const modeText = force ? 'Regenerate' : 'Quick';
    const startTime = Date.now();
    
    let currentBidxIndex = 0;
    let totalCreated = 0, totalSkipped = 0, totalFiles = 0;
    
    function processNextBidx() {
        if (currentBidxIndex >= allBidxs.length) {
            // 모든 bidx 완료
            const elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressBar.className = 'progress-bar bg-success';
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-success small';
            resultDiv.innerHTML = '✅ Done! (' + elapsed + ') - Created: <strong>' + totalCreated + '</strong>, Skip: ' + totalSkipped + ', Total: ' + totalFiles;
            document.getElementById('btn-thumb-quick').disabled = false;
            document.getElementById('btn-thumb-force').disabled = false;
            return;
        }
        
        const bidx = allBidxs[currentBidxIndex];
        const folderName = baseDirNames[currentBidxIndex];
        statusDiv.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' - ' + modeText + '...';
        
        const url = 'index.php?make_thumbnail_cache=1&bidx=' + bidx + (force ? '&force=1' : '') + '&stream=1';
        const eventSource = new EventSource(url);
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.type === 'progress') {
                const percent = Math.round((data.current / data.total) * 100);
                // ✅ 전체 폴더 대비 현재 진행률 계산
                const overallPercent = Math.round(((currentBidxIndex + (data.current / data.total)) / allBidxs.length) * 100);
                progressBar.style.width = overallPercent + '%';
                progressBar.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' ' + percent + '% (' + data.current + '/' + data.total + ')';
                statusDiv.textContent = overallPercent + '% - ' + folderName + ' (Created: ' + data.created + ', Skip: ' + data.skipped + ')';
                currentDiv.innerHTML = '📄 ' + data.file;
            } else if (data.type === 'complete') {
                eventSource.close();
                totalCreated += data.created;
                totalSkipped += data.skipped;
                totalFiles += data.total;
                currentBidxIndex++;
                processNextBidx();
            }
        };
        
        eventSource.onerror = function(e) {
            eventSource.close();
            currentBidxIndex++;
            processNextBidx(); // 에러 발생해도 다음 bidx 진행
        };
    }
    
    processNextBidx();
}

// 커버 이미지 생성 ([cover].jpg) - 전체 base_dirs 순차 처리
function runCoverCache(force) {
    const progressDiv = document.getElementById('cover-progress');
    const progressBar = document.getElementById('cover-progress-bar');
    const statusDiv = document.getElementById('cover-status');
    const currentDiv = document.getElementById('cover-current');
    const resultDiv = document.getElementById('cover-result');
    
    document.getElementById('btn-cover-quick').disabled = true;
    document.getElementById('btn-cover-force').disabled = true;
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    statusDiv.textContent = '<?php echo __("adm_loading"); ?>';
    currentDiv.innerHTML = '';
    
    const modeText = force ? 'Regenerate' : 'Quick';
    const startTime = Date.now();
    
    let currentBidxIndex = 0;
    let totalCreated = 0, totalSkipped = 0, totalFolders = 0;
    
    function processNextBidx() {
        if (currentBidxIndex >= allBidxs.length) {
            const elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressBar.className = 'progress-bar bg-success';
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-success small';
            resultDiv.innerHTML = '✅ Done! (' + elapsed + ') - Created: <strong>' + totalCreated + '</strong>, Skip: ' + totalSkipped + ', Total: ' + totalFolders;
            document.getElementById('btn-cover-quick').disabled = false;
            document.getElementById('btn-cover-force').disabled = false;
            return;
        }
        
        const bidx = allBidxs[currentBidxIndex];
        const folderName = baseDirNames[currentBidxIndex];
        statusDiv.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' - ' + modeText + '...';
        
        const url = 'index.php?make_cover_cache=1&bidx=' + bidx + (force ? '&force=1' : '') + '&stream=1';
        const eventSource = new EventSource(url);
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.type === 'progress') {
                const percent = Math.round((data.current / data.total) * 100);
                // ✅ 전체 폴더 대비 현재 진행률 계산
                const overallPercent = Math.round(((currentBidxIndex + (data.current / data.total)) / allBidxs.length) * 100);
                progressBar.style.width = overallPercent + '%';
                progressBar.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' ' + percent + '% (' + data.current + '/' + data.total + ')';
                statusDiv.textContent = overallPercent + '% - ' + folderName + ' (Created: ' + data.created + ', Skip: ' + data.skipped + ')';
                currentDiv.innerHTML = '📁 ' + data.folder;
            } else if (data.type === 'complete') {
                eventSource.close();
                totalCreated += data.created;
                totalSkipped += data.skipped;
                totalFolders += data.total;
                currentBidxIndex++;
                processNextBidx();
            }
        };
        
        eventSource.onerror = function(e) {
            eventSource.close();
            currentBidxIndex++;
            processNextBidx();
        };
    }
    
    processNextBidx();
}

// 선택형 통합 실행
function runSelectedCache(force) {
    const tasks = [];
    const taskNames = [];
    const taskIcons = [];
    
    if (document.getElementById('chk-thumb').checked) {
        tasks.push({url: 'make_thumbnail_cache', key: 'file'});
        taskNames.push('Thumbnail');
        taskIcons.push('🖼️');
    }
    if (document.getElementById('chk-cover').checked) {
        tasks.push({url: 'make_cover_cache', key: 'folder'});
        taskNames.push('Cover');
        taskIcons.push('🎨');
    }
    if (document.getElementById('chk-search').checked) {
        tasks.push({url: 'make_search_index', key: 'path', isSearch: true});
        taskNames.push('Search Index');
        taskIcons.push('🔍');
    }
    if (document.getElementById('chk-ziptotal').checked) {
        tasks.push({url: 'make_zip_total', key: 'path', isZipTotal: true});
        taskNames.push('Stats');
        taskIcons.push('📊');
    }
    if (document.getElementById('chk-filelist').checked) {
        tasks.push({url: 'make_filelist_cache', key: 'folder'});
        taskNames.push('File List');
        taskIcons.push('📋');
    }
    if (document.getElementById('chk-folder').checked) {
        tasks.push({url: 'make_folder_cache', key: 'folder'});
        taskNames.push('Folder');
        taskIcons.push('📁');
    }
    if (document.getElementById('chk-imgfiles').checked) {
        tasks.push({url: 'make_image_files_cache', key: 'file'});
        taskNames.push('ZIP Images');
        taskIcons.push('🗂️');
    }
    if (document.getElementById('chk-vidfiles').checked) {
        tasks.push({url: 'make_video_files_cache', key: 'file'});
        taskNames.push('ZIP Videos');
        taskIcons.push('🎬');
    }
    
    if (tasks.length === 0) {
        alert(i18n_adm.select_cache_type);
        return;
    }
    
    runTasksSequentially(tasks, taskNames, taskIcons, force, 'selected');
}

// 폴더 지정 실행
function runFolderTargetCache(force) {
    const folder = document.getElementById('target-folder').value.trim();
    const bidx = parseInt(document.getElementById('target-bidx').value) || -1;
    
    if (!folder) {
        alert(i18n_adm.enter_folder_path);
        return;
    }
    
    const recursive = document.getElementById('folder-recursive').checked;
    const tasks = [];
    const taskNames = [];
    const taskIcons = [];
    
    if (document.getElementById('folder-chk-thumb').checked) {
        tasks.push({url: 'make_thumbnail_cache', key: 'file'});
        taskNames.push('Thumbnail');
        taskIcons.push('🖼️');
    }
    if (document.getElementById('folder-chk-cover').checked) {
        tasks.push({url: 'make_cover_cache', key: 'folder'});
        taskNames.push('Cover');
        taskIcons.push('🎨');
    }
    if (document.getElementById('folder-chk-filelist').checked) {
        tasks.push({url: 'make_filelist_cache', key: 'folder'});
        taskNames.push('File List');
        taskIcons.push('📋');
    }
    if (document.getElementById('folder-chk-folder').checked) {
        tasks.push({url: 'make_folder_cache', key: 'folder'});
        taskNames.push('Folder');
        taskIcons.push('📁');
    }
    if (document.getElementById('folder-chk-imgfiles').checked) {
        tasks.push({url: 'make_image_files_cache', key: 'file'});
        taskNames.push('ZIP Images');
        taskIcons.push('🗂️');
    }
    if (document.getElementById('folder-chk-vidfiles').checked) {
        tasks.push({url: 'make_video_files_cache', key: 'file'});
        taskNames.push('ZIP Videos');
        taskIcons.push('🎬');
    }
    
    if (tasks.length === 0) {
        alert(i18n_adm.select_cache_type);
        return;
    }
    
    // bidx를 전달 (기본값 -1 = 전체)
    runTasksSequentially(tasks, taskNames, taskIcons, force, 'folder-target', folder, recursive, bidx);
}

// 순차 실행 공통 함수 - 전체 base_dirs 순차 처리 또는 특정 bidx만 처리
function runTasksSequentially(tasks, taskNames, taskIcons, force, prefix, folder = '', recursive = true, targetBidx = -1) {
    const progressDiv = document.getElementById(prefix + '-progress');
    const progressBar = document.getElementById(prefix + '-progress-bar');
    const phaseDiv = document.getElementById(prefix + '-phase');
    const taskDiv = document.getElementById(prefix + '-task');
    const statusDiv = document.getElementById(prefix + '-status');
    const currentDiv = document.getElementById(prefix + '-current');
    const resultDiv = document.getElementById(prefix + '-result');
    
    // 버튼 비활성화
    document.getElementById('btn-' + prefix + '-quick').disabled = true;
    document.getElementById('btn-' + prefix + '-force').disabled = true;
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    
    const startTime = Date.now();
    const results = [];  // 각 task별 결과 누적
    let currentTask = 0;
    let currentBidxIndex = 0;
    
    // targetBidx가 -1이면 전체, 아니면 해당 bidx만
    const bidxList = (targetBidx === -1) ? allBidxs : [targetBidx];
    
    // 각 task별 누적 결과
    let taskTotals = tasks.map(() => ({created: 0, skipped: 0, total: 0, files: 0, zip_total: 0}));
    
    function runNextBidx() {
        if (currentBidxIndex >= bidxList.length) {
            // 현재 task의 모든 bidx 완료 -> 결과 저장 후 다음 task
            const task = tasks[currentTask];
            const totals = taskTotals[currentTask];
            if (task.isSearch) {
                results.push({isSearch: true, files: totals.files});
            } else if (task.isZipTotal) {
                results.push({isZipTotal: true, zip_total: totals.zip_total});
            } else {
                results.push({created: totals.created, skipped: totals.skipped, total: totals.total});
            }
            
            currentTask++;
            currentBidxIndex = 0;
            runNextTask();
            return;
        }
        
        const task = tasks[currentTask];
        const bidx = bidxList[currentBidxIndex];
        const folderName = baseDirNames[bidx] || ('Folder ' + bidx);
        
        phaseDiv.textContent = (currentTask + 1) + '/' + tasks.length;
        taskDiv.textContent = taskNames[currentTask] + (bidxList.length > 1 ? ' [' + folderName + ']' : '');
        statusDiv.textContent = (bidxList.length > 1 ? '[' + (currentBidxIndex + 1) + '/' + bidxList.length + '] ' + folderName + ' ...' : '<?php echo __("adm_processing"); ?>');
        
        let url = 'index.php?' + task.url + '=1&bidx=' + bidx + (force ? '&force=1' : '') + '&stream=1';
        if (folder) {
            url += '&folder=' + encodeURIComponent(folder) + '&recursive=' + (recursive ? '1' : '0');
        }
        
        const eventSource = new EventSource(url);
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.type === 'progress') {
                const percent = Math.round((data.current / data.total) * 100);
                // ✅ 전체 진행률 계산 (작업 * 폴더 기준)
                const totalSteps = tasks.length * bidxList.length;
                const currentStep = currentTask * bidxList.length + currentBidxIndex + (data.current / data.total);
                const overallPercent = Math.round((currentStep / totalSteps) * 100);
                progressBar.style.width = overallPercent + '%';
                
                // ✅ 폴더가 여러개면 번호 표시
                const folderInfo = bidxList.length > 1 ? '[' + (currentBidxIndex + 1) + '/' + bidxList.length + '] ' + folderName + ' ' : '';
                progressBar.textContent = folderInfo + percent + '% (' + data.current + '/' + data.total + ')';
                
                if (task.isSearch) {
                    statusDiv.textContent = overallPercent + '% - ' + folderName + ' scanning... files: ' + data.files;
                } else if (task.isZipTotal) {
                    statusDiv.textContent = overallPercent + '% - ' + folderName + ' scanning... ZIP: ' + data.zips;
                } else {
                    statusDiv.textContent = overallPercent + '% - ' + folderName + ' (Created: ' + data.created + ', Skip: ' + data.skipped + ')';
                }
                currentDiv.innerHTML = taskIcons[currentTask] + ' ' + (data[task.key] || '');
            } else if (data.type === 'complete') {
                eventSource.close();
                
                // 누적
                const totals = taskTotals[currentTask];
                if (task.isSearch) {
                    totals.files += data.files || 0;
                } else if (task.isZipTotal) {
                    totals.zip_total += data.zip_total || 0;
                } else {
                    totals.created += data.created || 0;
                    totals.skipped += data.skipped || 0;
                    totals.total += data.total || 0;
                }
                
                currentBidxIndex++;
                runNextBidx();
            }
        };
        
        eventSource.onerror = function(e) {
            eventSource.close();
            currentBidxIndex++;
            runNextBidx();
        };
    }
    
    function runNextTask() {
        if (currentTask >= tasks.length) {
            // 모든 작업 완료
            const elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-success small';
            
            let html = '✅ Complete! (' + elapsed + ')<br>';
            results.forEach((r, i) => {
                html += '<strong>' + taskIcons[i] + ' ' + taskNames[i] + ':</strong> ';
                if (r.isSearch) {
                    html += 'Files: ' + r.files + '<br>';
                } else if (r.isZipTotal) {
                    html += 'ZIP: ' + r.zip_total + '<br>';
                } else {
                    html += 'Created: ' + r.created + ', Skip: ' + r.skipped + '<br>';
                }
            });
            resultDiv.innerHTML = html;
            
            document.getElementById('btn-' + prefix + '-quick').disabled = false;
            document.getElementById('btn-' + prefix + '-force').disabled = false;
            return;
        }
        
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        currentDiv.innerHTML = '';
        
        runNextBidx();
    }
    
    runNextTask();
}

// 폴더 브라우저 표시
function showFolderBrowser() {
    $('#folderBrowserModal').modal('show');
    showBaseDirsList(); // 먼저 base_dirs 목록 표시
}

// base_dirs 목록 표시 (첫 화면)
function showBaseDirsList() {
    const content = document.getElementById('folder-browser-content');
    let html = '<div class="p-2 bg-light border-bottom small"><strong><?php echo __('adm_cache_folder_select'); ?></strong> - <?php echo __('adm_cache_select_root'); ?></div>';
    
    // 전체 선택 옵션
    html += '<div class="folder-item p-2 border-bottom d-flex justify-content-between align-items-center" style="background:#e8f4f8;">';
    html += '<span>🌐 <strong><?php echo __('adm_cache_all_folders'); ?></strong></span>';
    html += '<button class="btn btn-sm btn-success" onclick="selectFolder(\'/\', -1)"><?php echo __("adm_btn_select_all"); ?></button>';
    html += '</div>';
    
    // 각 base_dir 표시
    baseDirNames.forEach((name, idx) => {
        html += '<div class="folder-item p-2 border-bottom d-flex justify-content-between align-items-center">';
        html += '<span style="cursor:pointer;" onclick="loadFolderList(\'\', ' + idx + ')">📁 <strong>' + name + '</strong></span>';
        html += '<div>';
        html += '<button class="btn btn-sm btn-outline-primary mr-1" onclick="loadFolderList(\'\', ' + idx + ')"><?php echo __("adm_btn_open"); ?></button>';
        html += '<button class="btn btn-sm btn-primary" onclick="selectFolder(\'/\', ' + idx + ')"><?php echo __("adm_btn_select"); ?></button>';
        html += '</div>';
        html += '</div>';
    });
    
    content.innerHTML = html;
}

// 폴더 목록 로드 (admin.php 자체에서 처리)
function loadFolderList(path, bidx) {
    currentBrowseBidx = bidx; // 현재 탐색 중인 bidx 저장
    const content = document.getElementById('folder-browser-content');
    content.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';
    
    const url = 'admin.php?ajax_folders=1&bidx=' + bidx + '&path=' + encodeURIComponent(path);
    
    fetch(url)
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            let html = '';
            
            // 현재 경로 표시
            const folderName = baseDirNames[bidx] || 'Unknown';
            html += '<div class="p-2 bg-light border-bottom small">';
            html += '<strong>[' + folderName + ']</strong> ' + (path || '/');
            html += '</div>';
            
            // 상위 폴더 또는 base_dirs 목록으로 돌아가기
            if (path) {
                const parent = path.split('/').slice(0, -1).join('/');
                html += '<div class="folder-item p-2 border-bottom" style="cursor:pointer;background:#f8f9fa;" onclick="loadFolderList(\'' + parent.replace(/'/g, "\\'") + '\', ' + bidx + ')">';
                html += '📁 <strong>..</strong> (Parent)';
                html += '</div>';
            } else {
                html += '<div class="folder-item p-2 border-bottom" style="cursor:pointer;background:#f8f9fa;" onclick="showBaseDirsList()">';
                html += '🏠 <strong>← Root</strong>';
                html += '</div>';
            }
            
            if (data.error) {
                html += '<div class="p-3 text-danger">Error: ' + data.error + '</div>';
            } else if (data.folders && data.folders.length > 0) {
                data.folders.forEach(folder => {
                    const fullPath = path ? path + '/' + folder : '/' + folder;
                    html += '<div class="folder-item p-2 border-bottom d-flex justify-content-between align-items-center">';
                    html += '<span style="cursor:pointer;" onclick="loadFolderList(\'' + fullPath.replace(/'/g, "\\'") + '\', ' + bidx + ')">📁 ' + folder + '</span>';
                    html += '<button class="btn btn-sm btn-primary" onclick="selectFolder(\'' + fullPath.replace(/'/g, "\\'") + '\', ' + bidx + ')"><?php echo __("adm_btn_select"); ?></button>';
                    html += '</div>';
                });
            } else {
                html += '<div class="p-3 text-muted">No subfolders.</div>';
            }
            
            // 현재 폴더 선택 버튼
            html += '<div class="p-2 border-top"><button class="btn btn-sm btn-outline-primary w-100" onclick="selectFolder(\'' + (path || '/').replace(/'/g, "\\'") + '\', ' + bidx + ')"><?php echo __("adm_btn_select_folder"); ?></button></div>';
            
            content.innerHTML = html;
        })
        .catch(err => {
            console.error('Fetch error:', err);
            content.innerHTML = '<div class="text-danger p-3">Failed to load folders.<br><small>' + err.message + '</small></div>';
        });
}

// 폴더 선택 (bidx도 함께 저장)
let currentBrowseBidx = 0;
function selectFolder(path, bidx) {
    document.getElementById('target-folder').value = path;
    document.getElementById('target-bidx').value = bidx; // bidx도 저장
    
    // 표시용 텍스트
    let displayText = path;
    if (bidx === -1) {
        displayText = 'All Folders (/)';
    } else if (baseDirNames[bidx]) {
        displayText = '[' + baseDirNames[bidx] + '] ' + path;
    }
    document.getElementById('target-folder-display').textContent = displayText;
    
    $('#folderBrowserModal').modal('hide');
}

// 검색 인덱스 생성 - 전체 base_dirs 순차 처리
function runSearchIndex() {
    const progressDiv = document.getElementById('search-progress');
    const progressBar = document.getElementById('search-progress-bar');
    const statusDiv = document.getElementById('search-status');
    const currentDiv = document.getElementById('search-current');
    const resultDiv = document.getElementById('search-result');
    
    document.getElementById('btn-search-index').disabled = true;
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    statusDiv.textContent = 'Generating search index...';
    currentDiv.innerHTML = '';
    
    const startTime = Date.now();
    let currentBidxIndex = 0;
    let totalFiles = 0;
    
    function processNextBidx() {
        if (currentBidxIndex >= allBidxs.length) {
            const elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-success small';
            resultDiv.innerHTML = '✅ Done! (' + elapsed + ') - Files: <strong>' + totalFiles + '</strong>';
            document.getElementById('btn-search-index').disabled = false;
            return;
        }
        
        const bidx = allBidxs[currentBidxIndex];
        const folderName = baseDirNames[currentBidxIndex];
        statusDiv.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' generating index...';
        
        const url = 'index.php?make_search_index=1&bidx=' + bidx + '&stream=1';
        const eventSource = new EventSource(url);
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.type === 'progress') {
                const percent = Math.round((data.current / data.total) * 100);
                // ✅ 전체 폴더 대비 진행률
                const overallPercent = Math.round(((currentBidxIndex + (data.current / data.total)) / allBidxs.length) * 100);
                progressBar.style.width = overallPercent + '%';
                progressBar.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' ' + percent + '% (' + data.current + '/' + data.total + ')';
                statusDiv.textContent = overallPercent + '% - ' + folderName + ' scanning (files: ' + data.files + ')';
                currentDiv.innerHTML = '🔍 ' + data.path;
            } else if (data.type === 'complete') {
                eventSource.close();
                totalFiles += data.files || 0;
                currentBidxIndex++;
                processNextBidx();
            }
        };
        
        eventSource.onerror = function(e) {
            eventSource.close();
            currentBidxIndex++;
            processNextBidx();
        };
    }
    
    processNextBidx();
}

// 통계 생성 - 전체 base_dirs 순차 처리
function runZipTotal() {
    const progressDiv = document.getElementById('ziptotal-progress');
    const progressBar = document.getElementById('ziptotal-progress-bar');
    const statusDiv = document.getElementById('ziptotal-status');
    const resultDiv = document.getElementById('ziptotal-result');
    
    document.getElementById('btn-zip-total').disabled = true;
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    statusDiv.textContent = 'Counting ZIPs...';
    
    const startTime = Date.now();
    let currentBidxIndex = 0;
    let totalZips = 0;
    let totalFolders = 0;
    
    function processNextBidx() {
        if (currentBidxIndex >= allBidxs.length) {
            const elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-success small';
            resultDiv.innerHTML = '✅ Done! (' + elapsed + ') - Folders: <strong>' + totalFolders + '</strong>, ZIP: <strong>' + totalZips + '</strong>';
            document.getElementById('btn-zip-total').disabled = false;
            return;
        }
        
        const bidx = allBidxs[currentBidxIndex];
        const folderName = baseDirNames[currentBidxIndex];
        statusDiv.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' counting ZIPs...';
        
        const url = 'index.php?make_zip_total=1&bidx=' + bidx + '&stream=1';
        const eventSource = new EventSource(url);
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.type === 'progress') {
                const percent = Math.round((data.current / data.total) * 100);
                // ✅ 전체 폴더 대비 진행률
                const overallPercent = Math.round(((currentBidxIndex + (data.current / data.total)) / allBidxs.length) * 100);
                progressBar.style.width = overallPercent + '%';
                progressBar.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' ' + percent + '% (' + data.current + '/' + data.total + ')';
                statusDiv.textContent = overallPercent + '% - ' + folderName + ' counting (folders: ' + (data.folders || 0) + ', ZIP: ' + data.zips + ')';
            } else if (data.type === 'complete') {
                eventSource.close();
                totalZips += data.zip_total || 0;
                totalFolders += data.folder_total || 0;
                currentBidxIndex++;
                processNextBidx();
            }
        };
        
        eventSource.onerror = function(e) {
            eventSource.close();
            currentBidxIndex++;
            processNextBidx();
        };
    }
    
    processNextBidx();
}

// 파일 목록 캐시 생성 - 전체 base_dirs 순차 처리
function runFilelistCache(force) {
    const progressDiv = document.getElementById('filelist-progress');
    const progressBar = document.getElementById('filelist-progress-bar');
    const statusDiv = document.getElementById('filelist-status');
    const currentDiv = document.getElementById('filelist-current');
    const resultDiv = document.getElementById('filelist-result');
    
    document.getElementById('btn-filelist-quick').disabled = true;
    document.getElementById('btn-filelist-force').disabled = true;
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    statusDiv.textContent = '<?php echo __("adm_loading"); ?>';
    currentDiv.innerHTML = '';
    
    const modeText = force ? 'Regenerate' : 'Quick';
    const startTime = Date.now();
    
    let currentBidxIndex = 0;
    let totalCreated = 0, totalSkipped = 0, totalFolders = 0;
    
    function processNextBidx() {
        if (currentBidxIndex >= allBidxs.length) {
            const elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-success small';
            resultDiv.innerHTML = '✅ Done! (' + elapsed + ') - Created: <strong>' + totalCreated + '</strong>, Skip: ' + totalSkipped + ', Total: ' + totalFolders;
            document.getElementById('btn-filelist-quick').disabled = false;
            document.getElementById('btn-filelist-force').disabled = false;
            return;
        }
        
        const bidx = allBidxs[currentBidxIndex];
        const folderName = baseDirNames[currentBidxIndex];
        statusDiv.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' - ' + modeText;
        
        const url = 'index.php?make_filelist_cache=1&bidx=' + bidx + (force ? '&force=1' : '') + '&stream=1';
        const eventSource = new EventSource(url);
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.type === 'progress') {
                const percent = Math.round((data.current / data.total) * 100);
                // ✅ 전체 폴더 대비 진행률
                const overallPercent = Math.round(((currentBidxIndex + (data.current / data.total)) / allBidxs.length) * 100);
                progressBar.style.width = overallPercent + '%';
                progressBar.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' ' + percent + '% (' + data.current + '/' + data.total + ')';
                statusDiv.textContent = overallPercent + '% - ' + folderName + ' (Created: ' + data.created + ', Skip: ' + data.skipped + ')';
                currentDiv.innerHTML = '📋 ' + data.folder;
            } else if (data.type === 'complete') {
                eventSource.close();
                totalCreated += data.created || 0;
                totalSkipped += data.skipped || 0;
                totalFolders += data.total || 0;
                currentBidxIndex++;
                processNextBidx();
            }
        };
        
        eventSource.onerror = function(e) {
            eventSource.close();
            currentBidxIndex++;
            processNextBidx();
        };
    }
    
    processNextBidx();
}

// 폴더 캐시 생성 - 전체 base_dirs 순차 처리
function runFolderCache(force) {
    const progressDiv = document.getElementById('folder-progress');
    const progressBar = document.getElementById('folder-progress-bar');
    const statusDiv = document.getElementById('folder-status');
    const currentDiv = document.getElementById('folder-current');
    const resultDiv = document.getElementById('folder-result');
    
    document.getElementById('btn-folder-quick').disabled = true;
    document.getElementById('btn-folder-force').disabled = true;
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    statusDiv.textContent = '<?php echo __("adm_loading"); ?>';
    currentDiv.innerHTML = '';
    
    const modeText = force ? 'Regenerate' : 'Quick';
    const startTime = Date.now();
    
    let currentBidxIndex = 0;
    let totalCreated = 0, totalSkipped = 0, totalFolders = 0;
    
    function processNextBidx() {
        if (currentBidxIndex >= allBidxs.length) {
            const elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-success small';
            resultDiv.innerHTML = '✅ Done! (' + elapsed + ') - Created: <strong>' + totalCreated + '</strong>, Skip: ' + totalSkipped + ', Total: ' + totalFolders;
            document.getElementById('btn-folder-quick').disabled = false;
            document.getElementById('btn-folder-force').disabled = false;
            return;
        }
        
        const bidx = allBidxs[currentBidxIndex];
        const folderName = baseDirNames[currentBidxIndex];
        statusDiv.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' - ' + modeText;
        
        const url = 'index.php?make_folder_cache=1&bidx=' + bidx + (force ? '&force=1' : '') + '&stream=1';
        const eventSource = new EventSource(url);
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.type === 'progress') {
                const percent = Math.round((data.current / data.total) * 100);
                // ✅ 전체 폴더 대비 진행률
                const overallPercent = Math.round(((currentBidxIndex + (data.current / data.total)) / allBidxs.length) * 100);
                progressBar.style.width = overallPercent + '%';
                progressBar.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' ' + percent + '% (' + data.current + '/' + data.total + ')';
                statusDiv.textContent = overallPercent + '% - ' + folderName + ' (Created: ' + data.created + ', Skip: ' + data.skipped + ')';
                currentDiv.innerHTML = '📁 ' + data.folder;
            } else if (data.type === 'complete') {
                eventSource.close();
                totalCreated += data.created || 0;
                totalSkipped += data.skipped || 0;
                totalFolders += data.total || 0;
                currentBidxIndex++;
                processNextBidx();
            }
        };
        
        eventSource.onerror = function(e) {
            eventSource.close();
            currentBidxIndex++;
            processNextBidx();
        };
    }
    
    processNextBidx();
}

// ZIP 이미지 목록 캐시 생성 - 전체 base_dirs 순차 처리
function runImageFilesCache(force) {
    const progressDiv = document.getElementById('imgfiles-progress');
    const progressBar = document.getElementById('imgfiles-progress-bar');
    const statusDiv = document.getElementById('imgfiles-status');
    const currentDiv = document.getElementById('imgfiles-current');
    const resultDiv = document.getElementById('imgfiles-result');
    
    document.getElementById('btn-imgfiles-quick').disabled = true;
    document.getElementById('btn-imgfiles-force').disabled = true;
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    statusDiv.textContent = '<?php echo __("adm_loading"); ?>';
    currentDiv.innerHTML = '';
    
    const modeText = force ? 'Regenerate' : 'Quick';
    const startTime = Date.now();
    
    let currentBidxIndex = 0;
    let totalCreated = 0, totalSkipped = 0, totalFiles = 0;
    
    function processNextBidx() {
        if (currentBidxIndex >= allBidxs.length) {
            const elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-success small';
            resultDiv.innerHTML = '✅ Done! (' + elapsed + ') - Created: <strong>' + totalCreated + '</strong>, Skip: ' + totalSkipped + ', Total: ' + totalFiles;
            document.getElementById('btn-imgfiles-quick').disabled = false;
            document.getElementById('btn-imgfiles-force').disabled = false;
            return;
        }
        
        const bidx = allBidxs[currentBidxIndex];
        const folderName = baseDirNames[currentBidxIndex];
        statusDiv.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' - ' + modeText;
        
        const url = 'index.php?make_image_files_cache=1&bidx=' + bidx + (force ? '&force=1' : '') + '&stream=1';
        const eventSource = new EventSource(url);
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.type === 'progress') {
                const percent = Math.round((data.current / data.total) * 100);
                // ✅ 전체 폴더 대비 진행률
                const overallPercent = Math.round(((currentBidxIndex + (data.current / data.total)) / allBidxs.length) * 100);
                progressBar.style.width = overallPercent + '%';
                progressBar.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' ' + percent + '% (' + data.current + '/' + data.total + ')';
                statusDiv.textContent = overallPercent + '% - ' + folderName + ' (Created: ' + data.created + ', Skip: ' + data.skipped + ')';
                currentDiv.innerHTML = '🗂️ ' + data.file;
            } else if (data.type === 'complete') {
                eventSource.close();
                totalCreated += data.created || 0;
                totalSkipped += data.skipped || 0;
                totalFiles += data.total || 0;
                currentBidxIndex++;
                processNextBidx();
            }
        };
        
        eventSource.onerror = function(e) {
            eventSource.close();
            currentBidxIndex++;
            processNextBidx();
        };
    }
    
    processNextBidx();
}

// ZIP 동영상 목록 캐시 생성 - 전체 base_dirs 순차 처리
function runVideoFilesCache(force) {
    const progressDiv = document.getElementById('vidfiles-progress');
    const progressBar = document.getElementById('vidfiles-progress-bar');
    const statusDiv = document.getElementById('vidfiles-status');
    const currentDiv = document.getElementById('vidfiles-current');
    const resultDiv = document.getElementById('vidfiles-result');
    
    document.getElementById('btn-vidfiles-quick').disabled = true;
    document.getElementById('btn-vidfiles-force').disabled = true;
    
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    statusDiv.textContent = '<?php echo __("adm_loading"); ?>';
    currentDiv.innerHTML = '';
    
    const modeText = force ? 'Regenerate' : 'Quick';
    const startTime = Date.now();
    
    let currentBidxIndex = 0;
    let totalCreated = 0, totalSkipped = 0, totalFiles = 0;
    
    function processNextBidx() {
        if (currentBidxIndex >= allBidxs.length) {
            const elapsed = formatElapsedTime((Date.now() - startTime) / 1000);
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-success small';
            resultDiv.innerHTML = '✅ Done! (' + elapsed + ') - Created: <strong>' + totalCreated + '</strong>, Skip: ' + totalSkipped + ', Total: ' + totalFiles;
            document.getElementById('btn-vidfiles-quick').disabled = false;
            document.getElementById('btn-vidfiles-force').disabled = false;
            return;
        }
        
        const bidx = allBidxs[currentBidxIndex];
        const folderName = baseDirNames[currentBidxIndex];
        statusDiv.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' - ' + modeText;
        
        const url = 'index.php?make_video_files_cache=1&bidx=' + bidx + (force ? '&force=1' : '') + '&stream=1';
        const eventSource = new EventSource(url);
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            
            if (data.type === 'progress') {
                const percent = Math.round((data.current / data.total) * 100);
                // ✅ 전체 폴더 대비 진행률
                const overallPercent = Math.round(((currentBidxIndex + (data.current / data.total)) / allBidxs.length) * 100);
                progressBar.style.width = overallPercent + '%';
                progressBar.textContent = '[' + (currentBidxIndex + 1) + '/' + allBidxs.length + '] ' + folderName + ' ' + percent + '% (' + data.current + '/' + data.total + ')';
                statusDiv.textContent = overallPercent + '% - ' + folderName + ' (Created: ' + data.created + ', Skip: ' + data.skipped + ')';
                currentDiv.innerHTML = '🎬 ' + data.file;
            } else if (data.type === 'complete') {
                eventSource.close();
                totalCreated += data.created || 0;
                totalSkipped += data.skipped || 0;
                totalFiles += data.total || 0;
                currentBidxIndex++;
                processNextBidx();
            }
        };
        
        eventSource.onerror = function(e) {
            eventSource.close();
            currentBidxIndex++;
            processNextBidx();
        };
    }
    
    processNextBidx();
}
</script>

</div><!-- container-fluid -->


<!-- Theme -->
<div class="tab-pane fade" id="theme">
<div class="card m-2 p-0">
<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="theme_change">

<div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
<span><?php echo __("adm_theme_select_title"); ?></span>
<span class="badge bg-light text-success"><?php echo __("adm_theme_current"); ?> <?php echo $current_theme; ?>. <?php echo h($themes[$current_theme]['name'] ?? __('adm_default')); ?></span>
</div>
<div class="card-body">
<p class="text-muted mb-3"><?php echo __("adm_theme_select_desc"); ?></p>

<div class="row">
<div class="col-md-7">
<div class="theme-grid">
<?php foreach($themes as $tid => $tinfo): ?>
<div class="theme-card <?php if($current_theme == $tid) echo 'active'; ?>" style="border-left:4px solid <?php echo $tinfo['color']; ?>" onclick="selectTheme(event, <?php echo $tid; ?>);" tabindex="-1">
<input type="radio" name="login_theme" value="<?php echo $tid; ?>" id="theme_radio_<?php echo $tid; ?>" <?php if($current_theme == $tid) echo 'checked'; ?> style="display:none;">
<div class="theme-icon"><?php echo $tinfo['icon']; ?></div>
<div class="theme-name"><?php echo h($tinfo['name']); ?><?php if($current_theme == $tid): ?> <span class="badge bg-success" style="font-size:0.6em;"><?php echo __('adm_theme_applied'); ?></span><?php endif; ?></div>
<div class="theme-desc"><?php echo h($tinfo['desc']); ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- Theme apply button -->
<div class="mt-3">
<button type="submit" class="btn btn-success"><?php echo __("adm_btn_apply_theme"); ?></button>
</div>
</form>

<!-- Background image custom settings -->
<div class="mt-4 p-3 bg-light rounded">
<h6 class="text-center"><?php echo __("adm_heading_bg_custom"); ?></h6>
<p class="text-muted small mb-3 text-center"><?php echo __("adm_theme_bg_desc"); ?></p>

<!-- Change by URL -->
<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post" class="mb-4">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="background_url_change">
<label class="form-label small mb-2 text-muted"><?php echo __("adm_theme_bg_url_label"); ?></label>
<div class="d-flex flex-column flex-md-row gap-2">
<select name="bg_theme" id="bg_theme_url" class="form-select form-select-sm" style="flex: 0 0 auto; width: 100%; max-width: 150px;" onchange="updateBgInput(this.value)">
<?php foreach($themes as $tid => $tinfo): ?>
<option value="<?php echo $tid; ?>" <?php if($current_theme == $tid) echo 'selected'; ?>><?php echo $tid; ?>. <?php echo h($tinfo['name']); ?></option>
<?php endforeach; ?>
</select>
<input type="url" name="bg_url" id="bg_url_input" class="form-control form-control-sm flex-grow-1" placeholder="https://images.unsplash.com/..." style="min-width: 0;">
<button type="submit" class="btn btn-sm btn-primary" style="flex: 0 0 auto; white-space: nowrap;"><?php echo __("adm_btn_apply_url"); ?></button>
</div>
</form>

<!-- Change by file -->
<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" class="mb-4">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="background_file_change">
<label class="form-label small mb-2 text-muted"><?php echo __("adm_theme_bg_file_label"); ?></label>
<div class="d-flex flex-column flex-md-row gap-2">
<select name="bg_theme" id="bg_theme_file" class="form-select form-select-sm" style="flex: 0 0 auto; width: 100%; max-width: 150px;">
<?php foreach($themes as $tid => $tinfo): ?>
<option value="<?php echo $tid; ?>" <?php if($current_theme == $tid) echo 'selected'; ?>><?php echo $tid; ?>. <?php echo h($tinfo['name']); ?></option>
<?php endforeach; ?>
</select>
<input type="file" name="bg_file" class="form-control form-control-sm flex-grow-1" accept="image/*" style="min-width: 0;">
<button type="submit" class="btn btn-sm btn-success" style="flex: 0 0 auto; white-space: nowrap;"><?php echo __("adm_btn_apply_file"); ?></button>
</div>
</form>

<!-- Background filter settings -->
<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post" class="mb-4">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="background_filter_change">
<label class="form-label small mb-2 text-muted"><?php echo __("adm_theme_bg_filter_label"); ?></label>
<div class="d-flex flex-column flex-md-row gap-2">
<select name="bg_theme" id="bg_theme_filter" class="form-select form-select-sm" style="flex: 0 0 auto; width: 100%; max-width: 150px;" onchange="updateFilterSelect(this.value)">
<?php foreach($themes as $tid => $tinfo): ?>
<option value="<?php echo $tid; ?>" <?php if($current_theme == $tid) echo 'selected'; ?>><?php echo $tid; ?>. <?php echo h($tinfo['name']); ?></option>
<?php endforeach; ?>
</select>
<select name="bg_filter" id="bg_filter_select" class="form-select form-select-sm" style="flex: 1; min-width: 0;">
<option value="none"><?php echo __("adm_filter_none"); ?></option>
<optgroup label="<?php echo __('adm_theme_basic_filters'); ?>">
<option value="blur"><?php echo __("adm_filter_blur"); ?></option>
<option value="grayscale"><?php echo __("adm_filter_grayscale"); ?></option>
<option value="sepia"><?php echo __("adm_filter_sepia"); ?></option>
<option value="brightness"><?php echo __("adm_filter_brightness"); ?></option>
<option value="contrast"><?php echo __("adm_filter_contrast"); ?></option>
<option value="saturate"><?php echo __("adm_filter_saturate"); ?></option>
<option value="invert"><?php echo __("adm_filter_invert"); ?></option>
<option value="hue-rotate"><?php echo __("adm_filter_hue"); ?></option>
</optgroup>
<optgroup label="<?php echo __('adm_theme_combo_filters'); ?>">
<option value="blur-grayscale"><?php echo __("adm_filter_blur_grayscale"); ?></option>
<option value="blur-sepia"><?php echo __("adm_filter_blur_sepia"); ?></option>
<option value="brightness-contrast"><?php echo __("adm_filter_bright_contrast"); ?></option>
<option value="vintage"><?php echo __("adm_filter_vintage"); ?></option>
<option value="cool"><?php echo __("adm_filter_cool"); ?></option>
<option value="warm"><?php echo __("adm_filter_warm"); ?></option>
</optgroup>
</select>
<button type="submit" class="btn btn-sm btn-info" style="flex: 0 0 auto; white-space: nowrap;"><?php echo __("adm_btn_apply_filter"); ?></button>
</div>
</form>

<!-- Reset -->
<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post" class="mb-3">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="background_reset">
<label class="form-label small mb-2 text-muted"><?php echo __("adm_theme_reset_default"); ?></label>
<div class="d-flex flex-column flex-md-row gap-2">
<select name="bg_theme" id="bg_theme_reset" class="form-select form-select-sm" style="flex: 0 0 auto; width: 100%; max-width: 150px;">
<?php foreach($themes as $tid => $tinfo): ?>
<option value="<?php echo $tid; ?>" <?php if($current_theme == $tid) echo 'selected'; ?>><?php echo $tid; ?>. <?php echo h($tinfo['name']); ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="btn btn-sm btn-outline-secondary" style="flex: 0 0 auto; white-space: nowrap;"><?php echo __("adm_btn_reset_default"); ?></button>
</div>
</form>

<!-- Current settings status -->
<div class="mt-3 p-2 border rounded bg-white">
<small class="text-muted d-block mb-1"><strong><?php echo __("adm_theme_current_bg"); ?></strong></small>
<div class="row small">
<?php 
$filter_names = [
    'none' => __('adm_filter_none'), 'blur' => __('adm_filter_blur'), 'grayscale' => __('adm_filter_grayscale'), 'sepia' => __('adm_filter_sepia'),
    'brightness' => __('adm_filter_brightness'), 'contrast' => __('adm_filter_contrast'), 'saturate' => __('adm_filter_saturate'), 'invert' => __('adm_filter_invert'),
    'hue-rotate' => __('adm_filter_hue'), 'blur-grayscale' => __('adm_filter_blur_grayscale'), 'blur-sepia' => __('adm_filter_blur_sepia'),
    'brightness-contrast' => __('adm_filter_bright_contrast'), 'vintage' => __('adm_filter_vintage'), 'cool' => __('adm_filter_cool'), 'warm' => __('adm_filter_warm')
];
for($i = 0; $i <= 25; $i++): 
$has_bg = !empty($theme_backgrounds[(string)$i]);
$has_filter = !empty($theme_filters[(string)$i]);
?>
<div class="col-6 col-md-4 col-lg-3 mb-1">
<span class="badge <?php echo $has_bg ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $i; ?></span>
<?php echo $themes[$i]['name']; ?>: 
<?php 
if($has_bg) {
    if(strpos($theme_backgrounds[(string)$i], 'src/backgrounds/') !== false) echo '📁';
    else echo '🔗';
} else {
    echo __('adm_default');
}
if($has_filter) {
    echo ' <span class="badge bg-info text-dark" style="font-size:10px;">' . ($filter_names[$theme_filters[(string)$i]] ?? $theme_filters[(string)$i]) . '</span>';
}
?>
</div>
<?php endfor; ?>
</div>
</div>
</div>

</div>
<div class="col-md-5">
<label><strong><?php echo __("adm_th_preview"); ?></strong></label>
<div id="theme-preview-container" class="border rounded p-2" style="height:420px;position:relative;overflow:hidden;">
<?php 
// 배경 URL 결정
// 테마별 기본 배경
$preview_defaults = [
    2 => 'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=600',
    4 => 'https://images.unsplash.com/photo-1462331940025-496dfbfc7564?w=600',
    6 => 'https://images.unsplash.com/photo-1478760329108-5c3ed9d495a0?w=600'
];
$bg2 = $theme_backgrounds['2'] ?? $preview_defaults[2];
$bg4 = $theme_backgrounds['4'] ?? $preview_defaults[4];
$bg6 = $theme_backgrounds['6'] ?? $preview_defaults[6];
?>
<?php foreach($themes as $tid => $tinfo): 
$preview_custom_bg = $theme_backgrounds[(string)$tid] ?? '';
?>
<div class="theme-preview-box" id="preview-<?php echo $tid; ?>" style="display:<?php echo ($current_theme == $tid) ? 'block' : 'none'; ?>;">
<style>
#preview-<?php echo $tid; ?> .preview-body{font-family:'Nanum Gothic',sans-serif;height:400px;display:flex;align-items:center;justify-content:center;border-radius:8px;overflow:hidden;position:relative}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;width:100%;max-width:260px;transform:scale(0.9);position:relative;z-index:10}
#preview-<?php echo $tid; ?> .preview-header{padding:20px;text-align:center}
#preview-<?php echo $tid; ?> .preview-header h2{font-family:'Black Han Sans',sans-serif;font-size:1.4em;margin:0}
#preview-<?php echo $tid; ?> .preview-header .subtitle{font-size:0.75em;margin-top:5px}
#preview-<?php echo $tid; ?> .preview-content{padding:20px}
#preview-<?php echo $tid; ?> .preview-input-group{margin-bottom:12px}
#preview-<?php echo $tid; ?> .preview-input-group label{display:block;font-size:0.75em;margin-bottom:5px;font-weight:500;text-align:left}
#preview-<?php echo $tid; ?> .preview-input-wrapper{position:relative}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{width:100%;padding:10px 12px 10px 36px !important;margin:0;font-size:0.8em;box-sizing:border-box}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;fill:#888;pointer-events:none}
#preview-<?php echo $tid; ?> .preview-content button{width:100%;padding:12px;font-weight:600;cursor:default;font-size:0.9em}
#preview-<?php echo $tid; ?> .preview-footer{padding:10px;text-align:center;font-size:0.7em}
<?php if($preview_custom_bg): ?>
/* 커스텀 배경 적용 */
#preview-<?php echo $tid; ?> .preview-body{background:url('<?php echo h($preview_custom_bg); ?>') center/cover !important}
<?php endif; ?>
<?php if($tid == 0): /* Library */ ?>
<?php if(!$preview_custom_bg && defined('THEME_0_PREVIEW_BG')): ?>
#preview-<?php echo $tid; ?> .preview-body{background:url('data:image/jpeg;base64,<?php echo THEME_0_PREVIEW_BG; ?>') center/cover}
<?php else: ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(180deg,#2c1810 0%,#1a0f0a 100%)}
<?php endif; ?>
#preview-<?php echo $tid; ?> .preview-card{background:rgba(245,240,230,0.97);border:none;border-radius:12px;box-shadow:0 25px 60px rgba(0,0,0,0.5),0 0 0 1px rgba(139,69,19,0.3),inset 0 1px 0 rgba(255,255,255,0.5);position:relative}
#preview-<?php echo $tid; ?> .preview-card::before{content:'';position:absolute;top:8px;left:8px;right:8px;bottom:8px;border:1px solid rgba(139,69,19,0.15);border-radius:6px;pointer-events:none}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;padding:25px 20px 15px;text-align:center}
#preview-<?php echo $tid; ?> .preview-logo-icon{width:55px;height:55px;margin:0 auto 12px;background:linear-gradient(135deg,#8B4513 0%,#654321 100%);border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 15px rgba(139,69,19,0.4)}
#preview-<?php echo $tid; ?> .preview-header h2{color:#3d2817;font-weight:700;font-size:1.4em;margin:0}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#8B7355;font-size:0.75em;margin-top:5px}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:15px 20px 20px}
#preview-<?php echo $tid; ?> .preview-input-group{margin-bottom:12px}
#preview-<?php echo $tid; ?> .preview-input-group label{display:block;font-size:0.75em;color:#5d4e37;margin-bottom:5px;font-weight:500;text-align:left}
#preview-<?php echo $tid; ?> .preview-input-wrapper{position:relative}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{width:100%;padding:10px 12px 10px 36px;border:2px solid #d4c9b8;border-radius:8px;font-size:0.8em;background:#fff;color:#3d2817}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;fill:#a89880}
#preview-<?php echo $tid; ?> .preview-content input::placeholder{color:#bbb0a0}
#preview-<?php echo $tid; ?> .preview-checkbox{font-size:0.7em;color:#5d4e37;margin:10px 0 15px;display:flex;align-items:center;gap:5px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(135deg,#8B4513 0%,#654321 100%);color:#fff;border:none;border-radius:8px;box-shadow:0 4px 15px rgba(139,69,19,0.3);font-weight:600;width:100%;padding:12px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:12px;text-align:center;color:#a89880;font-size:0.65em}
<?php elseif($tid == 1): /* Matrix */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:#000}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(0,10,0,0.95);border:2px solid #00ff00;border-radius:0;box-shadow:0 0 30px rgba(0,255,0,0.3),inset 0 0 20px rgba(0,255,0,0.1)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(0,255,0,0.5);padding:15px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#00ff00;font-family:'VT323',monospace;text-shadow:0 0 15px #00ff00;letter-spacing:2px;font-size:1.3em}
#preview-<?php echo $tid; ?> .preview-header h2::before{content:'> ';color:#00ff00}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#00aa00;font-family:'VT323',monospace;font-size:0.85em}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#00aa00;font-family:'VT323',monospace}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(0,20,0,0.8);border:1px solid #00ff00;color:#00ff00;font-family:'VT323',monospace;border-radius:0;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#00ff00}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(0,20,0,0.8);border:1px solid #00ff00;color:#00ff00;font-family:'VT323',monospace;border-radius:0}
#preview-<?php echo $tid; ?> .preview-content button{background:#00ff00;color:#000;border:none;font-family:'VT323',monospace;border-radius:0;text-transform:uppercase;letter-spacing:2px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#00aa00;font-family:'VT323',monospace}
<?php elseif($tid == 2): /* Glassmorphism */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:url('<?php echo h($bg2); ?>') center/cover}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);border-radius:20px;backdrop-filter:blur(10px)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;padding:0;margin-bottom:20px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#fff;text-shadow:0 2px 10px rgba(0,0,0,0.3)}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(255,255,255,0.8)}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(255,255,255,0.8)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:#fff;border-radius:10px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(255,255,255,0.5)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:#fff;border-radius:10px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:10px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(255,255,255,0.7)}
<?php elseif($tid == 3): /* Cyberpunk */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:#0a0014;position:relative;overflow:hidden}
#preview-<?php echo $tid; ?> .preview-body::before{content:'';position:absolute;bottom:0;left:0;right:0;height:100%;background:linear-gradient(90deg,transparent 49%,rgba(255,0,102,0.3) 49%,rgba(255,0,102,0.3) 51%,transparent 51%),linear-gradient(0deg,rgba(255,0,102,0.3) 1px,transparent 1px);background-size:30px 30px;transform:perspective(300px) rotateX(60deg);transform-origin:bottom;animation:prevGridMove 2s linear infinite;pointer-events:none}
@keyframes prevGridMove{0%{background-position:0 0}100%{background-position:30px 30px}}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(10,0,20,0.95);border:2px solid #ff0066;clip-path:polygon(0 8px,8px 0,calc(100% - 8px) 0,100% 8px,100% calc(100% - 8px),calc(100% - 8px) 100%,8px 100%,0 calc(100% - 8px));box-shadow:0 0 30px rgba(255,0,102,0.4),inset 0 0 30px rgba(0,255,255,0.1);position:relative;z-index:5}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(255,0,102,0.5);padding:20px 15px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#ff0066;font-family:'Orbitron',sans-serif;text-shadow:0 0 10px #ff0066,2px 2px 0 #00ffff;letter-spacing:2px;font-size:1.1em}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#00ffff;font-family:'Orbitron',sans-serif;letter-spacing:2px;text-transform:uppercase;font-size:0.6em}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#00ffff;font-family:'Orbitron',sans-serif}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(0,0,0,0.5);border:1px solid #ff0066;color:#00ffff;font-family:'Orbitron',sans-serif;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#00ffff}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(0,0,0,0.5);border:1px solid #ff0066;color:#00ffff;font-family:'Orbitron',sans-serif;clip-path:polygon(0 4px,4px 0,calc(100% - 4px) 0,100% 4px,100% calc(100% - 4px),calc(100% - 4px) 100%,4px 100%,0 calc(100% - 4px))}
#preview-<?php echo $tid; ?> .preview-content button{background:#ff0066;color:#fff;border:none;font-family:'Orbitron',sans-serif;clip-path:polygon(0 4px,4px 0,calc(100% - 4px) 0,100% 4px,100% calc(100% - 4px),calc(100% - 4px) 100%,4px 100%,0 calc(100% - 4px))}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#00ffff;font-family:'Orbitron',sans-serif;font-size:0.6em}
<?php elseif($tid == 4): /* Galaxy */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:url('<?php echo h($bg4); ?>') center/cover;position:relative}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(10,10,30,0.85);border:2px solid;border-image:linear-gradient(135deg,#667eea,#764ba2,#f093fb,#667eea) 1;border-radius:0;backdrop-filter:blur(10px);position:relative}
#preview-<?php echo $tid; ?> .preview-card::before{content:'✦';position:absolute;top:-12px;left:50%;transform:translateX(-50%);font-size:1.2em;color:#f093fb;text-shadow:0 0 15px #f093fb}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;padding:20px 15px}
#preview-<?php echo $tid; ?> .preview-header h2{background:linear-gradient(90deg,#667eea,#764ba2,#f093fb);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-family:'Orbitron',sans-serif;font-size:1.2em}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(255,255,255,0.8);font-family:'Orbitron',sans-serif;font-size:0.65em}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(255,255,255,0.8);font-family:'Orbitron',sans-serif}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(255,255,255,0.1);border:1px solid rgba(138,43,226,0.5);color:#fff;border-radius:5px;font-family:'Orbitron',sans-serif;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(255,255,255,0.6)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(255,255,255,0.1);border:1px solid rgba(138,43,226,0.5);color:#fff;border-radius:5px;font-family:'Orbitron',sans-serif}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(135deg,#667eea,#764ba2,#f093fb);color:#fff;border:none;border-radius:5px;font-family:'Orbitron',sans-serif}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(255,255,255,0.6);font-family:'Orbitron',sans-serif;font-size:0.6em}
<?php elseif($tid == 5): /* Sakura */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(180deg,#ffecd2,#fcb69f)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(255,255,255,0.95);border:none;border-radius:25px;box-shadow:0 15px 40px rgba(255,105,180,0.3),0 0 0 3px #ff69b4;overflow:hidden}
#preview-<?php echo $tid; ?> .preview-header{background:linear-gradient(135deg,#ff69b4,#ff1493);border-radius:0;padding:20px 15px 25px;position:relative;clip-path:ellipse(120% 100% at 50% 0%)}
#preview-<?php echo $tid; ?> .preview-header::before{content:'🌸';position:absolute;top:8px;left:12px;font-size:1em}
#preview-<?php echo $tid; ?> .preview-header::after{content:'🌸';position:absolute;top:8px;right:12px;font-size:1em}
#preview-<?php echo $tid; ?> .preview-header h2{color:#fff;font-family:'Jua',sans-serif;font-size:1.4em;text-shadow:0 2px 8px rgba(0,0,0,0.2)}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(255,255,255,0.95);font-family:'Jua',sans-serif}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:25px 20px 15px}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#ff69b4;font-family:'Jua',sans-serif}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:#fff;border:2px solid #ffb6c1;color:#333;border-radius:20px;font-family:'Jua',sans-serif;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#ff69b4}
#preview-<?php echo $tid; ?> .preview-content input{background:#fff;border:2px solid #ffb6c1;color:#333;border-radius:20px;font-family:'Jua',sans-serif}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(135deg,#ff69b4,#ff1493);color:#fff;border:none;border-radius:20px;font-family:'Jua',sans-serif}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#ff69b4;font-family:'Jua',sans-serif}
<?php elseif($tid == 6): /* Dark Gothic */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:url('<?php echo h($bg6); ?>') center/cover;position:relative}
#preview-<?php echo $tid; ?> .preview-body::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(180deg,rgba(0,0,0,0.7),rgba(20,0,0,0.9))}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:linear-gradient(180deg,rgba(20,0,0,0.97),rgba(5,0,0,0.97));border:3px double #8b0000;position:relative}
#preview-<?php echo $tid; ?> .preview-card::before{content:'⚜';position:absolute;top:-12px;left:50%;transform:translateX(-50%);font-size:1.5em;color:#8b0000;text-shadow:0 0 15px #8b0000}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:2px solid #8b0000;padding:25px 15px 15px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#8b0000;text-shadow:0 0 20px #8b0000;font-family:'Creepster',cursive;font-size:1.5em;letter-spacing:2px}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#666;font-family:'Cinzel',serif;font-style:italic;letter-spacing:1px}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#8b0000;font-family:'Cinzel',serif}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(0,0,0,0.5);border:1px solid #8b0000;color:#ccc;font-family:'Cinzel',serif;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#8b0000}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(0,0,0,0.5);border:1px solid #8b0000;color:#ccc;font-family:'Cinzel',serif}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(180deg,#8b0000 0%,#5c0000 50%,#8b0000 100%);color:#fff;border:1px solid #cc0000;font-family:'Cinzel',serif}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#8b0000;font-family:'Cinzel',serif}
<?php elseif($tid == 7): /* Minimal White */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(135deg,#f5f7fa,#c3cfe2)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:#fff;border:none;border-radius:20px;box-shadow:0 20px 40px rgba(0,0,0,0.1)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;padding:0;margin-bottom:20px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#333;font-weight:300}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#999}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#666}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:#f5f7fa;border:2px solid #e0e0e0;color:#333;border-radius:10px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#999}
#preview-<?php echo $tid; ?> .preview-content input{background:#f5f7fa;border:2px solid #e0e0e0;color:#333;border-radius:10px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:10px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#999}
<?php elseif($tid == 8): /* Retro Arcade */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:url('https://images.unsplash.com/photo-1511882150382-421056c89033?w=600&q=80') center/cover}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:#000;border:3px dashed #00ff00;box-shadow:0 0 20px rgba(0,255,0,0.3),inset 0 0 30px rgba(0,255,0,0.05);position:relative}
#preview-<?php echo $tid; ?> .preview-card::after{content:'🕹️ INSERT COIN';position:absolute;bottom:5px;left:50%;transform:translateX(-50%);color:#00ff00;font-family:'Press Start 2P',monospace;font-size:0.45em;animation:coinBlink 1s step-end infinite}
@keyframes coinBlink{0%,100%{opacity:1}50%{opacity:0}}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:2px dashed #00ff00;padding:15px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#00ff00;font-family:'Press Start 2P',monospace;text-shadow:0 0 10px #00ff00,2px 2px 0 #008800;font-size:0.75em;letter-spacing:1px}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#00aa00;font-family:'Press Start 2P',monospace;font-size:0.4em;margin-top:8px}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:15px}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#00aa00;font-family:'Press Start 2P',monospace}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:#001100;border:2px solid #00ff00;color:#00ff00;font-family:'Press Start 2P',monospace;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#00ff00}
#preview-<?php echo $tid; ?> .preview-content input{background:#001100;border:2px solid #00ff00;color:#00ff00;font-family:'Press Start 2P',monospace;font-size:0.55em}
#preview-<?php echo $tid; ?> .preview-content button{background:#00ff00;color:#000;border:none;font-family:'Press Start 2P',monospace;font-size:0.55em}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#006600;font-family:'Press Start 2P',monospace;font-size:0.4em}
<?php elseif($tid == 9): /* Ocean Wave */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(180deg,#0077be 0%,#00416a 100%)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(255,255,255,0.25);border:1px solid rgba(255,255,255,0.4);border-radius:25px;backdrop-filter:blur(15px);overflow:hidden}
#preview-<?php echo $tid; ?> .preview-header{background:rgba(255,255,255,0.1);padding:20px 15px;position:relative}
#preview-<?php echo $tid; ?> .preview-header::before{content:'🐚';position:absolute;top:12px;left:12px;font-size:0.9em}
#preview-<?php echo $tid; ?> .preview-header::after{content:'🐟';position:absolute;top:12px;right:12px;font-size:0.9em}
#preview-<?php echo $tid; ?> .preview-header h2{color:#fff;font-family:'Jua',sans-serif;text-shadow:0 2px 10px rgba(0,0,0,0.3)}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(255,255,255,0.9)}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:20px 15px}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(255,255,255,0.9)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(255,255,255,0.3);border:1px solid rgba(255,255,255,0.5);color:#fff;border-radius:15px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(255,255,255,0.7)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(255,255,255,0.3);border:1px solid rgba(255,255,255,0.5);color:#fff;border-radius:15px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(135deg,#fff,#e0f7fa);color:#0077be;border:none;border-radius:15px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(255,255,255,0.8)}
<?php elseif($tid == 10): /* Gradient Motion */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(-45deg,#ee7752,#e73c7e,#23a6d5,#23d5ab);background-size:400% 400%;animation:gradientBG 5s ease infinite}
@keyframes gradientBG{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(255,255,255,0.95);border:none;border-radius:30px}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;padding:0;margin-bottom:20px}
#preview-<?php echo $tid; ?> .preview-header h2{background:linear-gradient(-45deg,#ee7752,#e73c7e,#23a6d5);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#666}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#666}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:#f5f5f5;border:2px solid #e0e0e0;color:#333;border-radius:15px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#999}
#preview-<?php echo $tid; ?> .preview-content input{background:#f5f5f5;border:2px solid #e0e0e0;color:#333;border-radius:15px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(-45deg,#ee7752,#e73c7e,#23a6d5);color:#fff;border:none;border-radius:15px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#999}

<?php elseif($tid == 11): /* Jarvis */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:#0a1628}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(0,20,40,0.95);border:2px solid #00d4ff;border-radius:5px;box-shadow:0 0 30px rgba(0,212,255,0.3);overflow:hidden;position:relative}
#preview-<?php echo $tid; ?> .preview-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#00d4ff,transparent);animation:jarvisScan 2s linear infinite}
@keyframes jarvisScan{0%{transform:translateY(0)}100%{transform:translateY(300px)}}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(0,212,255,0.4);padding:20px 15px}
#preview-<?php echo $tid; ?> .preview-header::before{content:'◉';color:#00d4ff;font-size:0.7em;margin-right:8px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#00d4ff;font-family:'Orbitron',sans-serif;text-shadow:0 0 15px #00d4ff;letter-spacing:2px;font-size:1em;display:inline}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#0099cc;font-family:'Share Tech Mono',monospace;font-size:0.7em;margin-top:5px}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#00d4ff;font-family:'Share Tech Mono',monospace}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(0,20,40,0.8);border:1px solid #00d4ff;color:#00d4ff;font-family:'Share Tech Mono',monospace;border-radius:3px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#00d4ff}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(0,20,40,0.8);border:1px solid #00d4ff;color:#00d4ff;font-family:'Share Tech Mono',monospace;border-radius:3px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(180deg,#00d4ff,#0099cc);color:#001428;border:none;font-family:'Orbitron',sans-serif;border-radius:3px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#0099cc;font-family:'Share Tech Mono',monospace;font-size:0.7em}

<?php elseif($tid == 12): /* Aurora */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:url('https://images.unsplash.com/photo-1483347756197-71ef80e95f73?w=600&q=80') center/cover}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(10,10,32,0.9);border:1px solid rgba(0,255,136,0.4);border-radius:20px;backdrop-filter:blur(10px);overflow:hidden;position:relative}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;padding:20px 15px;position:relative}
#preview-<?php echo $tid; ?> .preview-header::before{content:'🌌';position:absolute;top:12px;left:12px;font-size:1em}
#preview-<?php echo $tid; ?> .preview-header h2{background:linear-gradient(90deg,#00ff88,#00d4ff,#ff00ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-family:'Orbitron',sans-serif;font-size:1.1em}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(0,255,136,0.8);font-family:'Share Tech Mono',monospace}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(0,255,136,0.8)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(0,255,136,0.08);border:1px solid rgba(0,255,136,0.4);color:#fff;border-radius:10px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(0,255,136,0.6)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(0,255,136,0.08);border:1px solid rgba(0,255,136,0.4);color:#fff;border-radius:10px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#00ff88,#00d4ff,#ff00ff);color:#000;border:none;border-radius:10px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(0,255,136,0.7)}

<?php elseif($tid == 13): /* Neon City */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(180deg,#1a0033,#330066)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(26,0,51,0.95);border:3px solid;border-image:linear-gradient(135deg,#ff00ff,#00ffff,#ff00ff) 1;box-shadow:0 0 20px rgba(255,0,255,0.4)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:2px solid #00ffff;padding:20px 15px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#ff00ff;text-shadow:0 0 10px #ff00ff;font-family:'Orbitron',sans-serif;font-size:1.1em;letter-spacing:2px}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#00ffff;text-shadow:0 0 5px #00ffff;font-family:'Share Tech Mono',monospace}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#ff00ff;font-family:'Share Tech Mono',monospace}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(0,0,0,0.5);border:2px solid #ff00ff;color:#fff;padding-left:36px;font-family:'Share Tech Mono',monospace}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#00ffff}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(0,0,0,0.5);border:2px solid #ff00ff;color:#fff;border-radius:0;font-family:'Share Tech Mono',monospace}
#preview-<?php echo $tid; ?> .preview-content button{background:transparent;border:2px solid #00ffff;color:#00ffff;border-radius:0;font-family:'Orbitron',sans-serif}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#ff00ff;font-family:'Share Tech Mono',monospace}

<?php elseif($tid == 14): /* Fire */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:#1a0000}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:linear-gradient(180deg,rgba(40,0,0,0.95),rgba(20,0,0,0.95));border:2px solid #ff4500;border-radius:10px;box-shadow:0 0 30px rgba(255,69,0,0.5)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(255,69,0,0.5)}
#preview-<?php echo $tid; ?> .preview-header h2{color:#ff4500;text-shadow:0 0 10px #ff4500}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#ff6600}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#ff6600}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(255,69,0,0.1);border:1px solid #ff4500;color:#fff;border-radius:5px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#ff6600}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(255,69,0,0.1);border:1px solid #ff4500;color:#fff;border-radius:5px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#ff4500,#ff0000);color:#fff;border:none;border-radius:5px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#ff6600}

<?php elseif($tid == 15): /* Aquarium */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(180deg,#006994,#003366)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(0,50,80,0.8);border:1px solid rgba(0,206,209,0.5);border-radius:20px;backdrop-filter:blur(10px)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;padding:0;margin-bottom:20px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#00ced1;text-shadow:0 0 10px rgba(0,206,209,0.5)}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(0,206,209,0.7)}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(0,206,209,0.8)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(0,206,209,0.1);border:1px solid rgba(0,206,209,0.5);color:#fff;border-radius:15px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(0,206,209,0.7)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(0,206,209,0.1);border:1px solid rgba(0,206,209,0.5);color:#fff;border-radius:15px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#00ced1,#20b2aa);color:#fff;border:none;border-radius:15px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(0,206,209,0.7)}

<?php elseif($tid == 16): /* Snow */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(180deg,#87ceeb,#e0f0ff)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(255,255,255,0.9);border:1px solid rgba(135,206,235,0.5);border-radius:20px}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;padding:0;margin-bottom:20px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#4a90a4}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#6ab0c0}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#4a90a4}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(135,206,235,0.2);border:1px solid rgba(135,206,235,0.5);color:#333;border-radius:15px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#6ab0c0}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(135,206,235,0.2);border:1px solid rgba(135,206,235,0.5);color:#333;border-radius:15px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#87ceeb,#6ab0c0);color:#fff;border:none;border-radius:15px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#6ab0c0}

<?php elseif($tid == 17): /* Gold Luxury */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(135deg,#1a1a2e 0%,#0d0d1a 100%)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:linear-gradient(135deg,rgba(26,26,46,0.97),rgba(13,13,26,0.97));border:2px solid;border-image:linear-gradient(135deg,#ffd700,#b8860b,#ffd700) 1;box-shadow:0 0 30px rgba(255,215,0,0.2);position:relative}
#preview-<?php echo $tid; ?> .preview-card::before{content:'♔';position:absolute;top:-15px;left:50%;transform:translateX(-50%);font-size:1.5em;color:#ffd700;text-shadow:0 0 15px rgba(255,215,0,0.8)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(255,215,0,0.3);padding:25px 15px 15px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#ffd700;text-shadow:0 0 20px rgba(255,215,0,0.5);font-family:'Playfair Display',serif;letter-spacing:2px}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#b8860b;font-family:'Playfair Display',serif;font-style:italic}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#b8860b;font-family:'Playfair Display',serif}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(255,215,0,0.08);border:1px solid #b8860b;color:#ffd700;padding-left:36px;font-family:'Playfair Display',serif}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#ffd700}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(255,215,0,0.08);border:1px solid #b8860b;color:#ffd700;border-radius:0;font-family:'Playfair Display',serif}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(135deg,#ffd700,#b8860b);color:#0d0d1a;border:none;border-radius:0;font-family:'Playfair Display',serif}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#b8860b;font-family:'Playfair Display',serif;font-style:italic}

<?php elseif($tid == 18): /* Hologram */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:#111}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:linear-gradient(135deg,rgba(255,105,180,0.1),rgba(0,255,255,0.1));border:2px solid #ff69b4;border-radius:20px;backdrop-filter:blur(10px)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;padding:0;margin-bottom:20px}
#preview-<?php echo $tid; ?> .preview-header h2{background:linear-gradient(90deg,#ff69b4,#00ffff,#ffff00);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#aaa}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(255,255,255,0.8)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(255,255,255,0.1);border:1px solid rgba(255,105,180,0.5);color:#fff;border-radius:10px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(255,105,180,0.7)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(255,255,255,0.1);border:1px solid rgba(255,105,180,0.5);color:#fff;border-radius:10px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#ff69b4,#00ffff);color:#111;border:none;border-radius:10px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(255,105,180,0.7)}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#888}

<?php elseif($tid == 19): /* Terminal */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:url('https://images.unsplash.com/photo-1629654297299-c8506221ca97?w=600&q=80') center/cover}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(12,12,12,0.98);border:1px solid #333;border-radius:5px;box-shadow:0 10px 30px rgba(0,0,0,0.8);overflow:hidden;position:relative}
#preview-<?php echo $tid; ?> .preview-card::before{content:'';position:absolute;top:0;left:0;right:0;height:25px;background:linear-gradient(90deg,#ff5f56 10px 10px,transparent 10px),linear-gradient(90deg,#ffbd2e 10px 10px,transparent 10px),linear-gradient(90deg,#27ca40 10px 10px,transparent 10px);background-position:10px center,28px center,46px center;background-size:10px 10px;background-repeat:no-repeat;border-bottom:1px solid #333}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid #333;padding:35px 15px 12px}
#preview-<?php echo $tid; ?> .preview-header h2{color:#00ff00;font-family:'Roboto Mono',monospace;font-size:0.85em}
#preview-<?php echo $tid; ?> .preview-header h2::before{content:'user@mycomix:~$ ';color:#00aaff}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#666;font-family:'Roboto Mono',monospace;font-size:0.65em}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;font-family:'Roboto Mono',monospace;padding:15px}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#00ff00;font-family:'Roboto Mono',monospace}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:#1a1a1a;border:none;border-left:2px solid #00ff00;color:#00ff00;font-family:'Roboto Mono',monospace;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#00ff00}
#preview-<?php echo $tid; ?> .preview-content input{background:#1a1a1a;border:none;border-left:2px solid #00ff00;color:#00ff00;font-family:'Roboto Mono',monospace;border-radius:0;padding:8px 10px}
#preview-<?php echo $tid; ?> .preview-content button{background:transparent;border:1px solid #00ff00;color:#00ff00;font-family:'Roboto Mono',monospace;border-radius:0}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#444;font-family:'Roboto Mono',monospace;border-top:1px solid #333;font-size:0.65em}

<?php elseif($tid == 20): /* Star Wars */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:#000}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(0,0,0,0.8);border:2px solid #ffe81f;border-radius:10px;box-shadow:0 0 30px rgba(255,232,31,0.3)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(255,232,31,0.3)}
#preview-<?php echo $tid; ?> .preview-header h2{color:#ffe81f;text-shadow:0 0 10px rgba(255,232,31,0.5);letter-spacing:2px}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:#999}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:#ffe81f}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(255,232,31,0.1);border:1px solid #ffe81f;color:#ffe81f;border-radius:5px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:#ffe81f}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(255,232,31,0.1);border:1px solid #ffe81f;color:#ffe81f;border-radius:5px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#ffe81f,#ffd700);color:#000;border:none;border-radius:5px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:#ffe81f}

<?php elseif($tid == 21): /* Constellation */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(180deg, #0a0a2e 0%, #1a1a4e 100%)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(10,10,46,0.9);border:1px solid rgba(100,149,237,0.3);border-radius:15px;backdrop-filter:blur(10px)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(100,149,237,0.2)}
#preview-<?php echo $tid; ?> .preview-header h2{color:#6495ed;text-shadow:0 0 10px rgba(100,149,237,0.5)}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(100,149,237,0.7)}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(100,149,237,0.8)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(100,149,237,0.1);border:1px solid rgba(100,149,237,0.3);color:#fff;border-radius:8px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(100,149,237,0.7)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(100,149,237,0.1);border:1px solid rgba(100,149,237,0.3);color:#fff;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#6495ed,#9370db);color:#fff;border:none;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(100,149,237,0.6)}

<?php elseif($tid == 22): /* Milky Way */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:radial-gradient(ellipse at center, #1a0a3e 0%, #0a0a1e 100%)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(26,10,62,0.85);border:1px solid rgba(138,43,226,0.4);border-radius:15px;backdrop-filter:blur(15px)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(138,43,226,0.3)}
#preview-<?php echo $tid; ?> .preview-header h2{background:linear-gradient(90deg,#9370db,#ff69b4);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(147,112,219,0.8)}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(147,112,219,0.8)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(138,43,226,0.15);border:1px solid rgba(138,43,226,0.3);color:#fff;border-radius:8px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(147,112,219,0.7)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(138,43,226,0.15);border:1px solid rgba(138,43,226,0.3);color:#fff;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#8a2be2,#ff69b4);color:#fff;border:none;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(138,43,226,0.6)}

<?php elseif($tid == 23): /* Nebula */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(135deg, #1a0a2e 0%, #2e1a4a 50%, #0a1a2e 100%)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(30,10,50,0.8);border:1px solid rgba(0,255,255,0.3);border-radius:15px;backdrop-filter:blur(10px)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(0,255,255,0.2)}
#preview-<?php echo $tid; ?> .preview-header h2{color:#00ffff;text-shadow:0 0 10px rgba(0,255,255,0.5)}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(0,255,255,0.6)}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(0,255,255,0.7)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(0,255,255,0.1);border:1px solid rgba(0,255,255,0.3);color:#fff;border-radius:8px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(0,255,255,0.7)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(0,255,255,0.1);border:1px solid rgba(0,255,255,0.3);color:#fff;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#00ffff,#ff00ff);color:#000;border:none;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(0,255,255,0.5)}

<?php elseif($tid == 24): /* Meteor Shower */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:linear-gradient(180deg, #000020 0%, #000040 100%)}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(0,0,40,0.9);border:1px solid rgba(255,215,0,0.4);border-radius:15px;backdrop-filter:blur(10px)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(255,215,0,0.3)}
#preview-<?php echo $tid; ?> .preview-header h2{color:#ffd700;text-shadow:0 0 10px rgba(255,215,0,0.5)}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(255,215,0,0.7)}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(255,215,0,0.8)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(255,215,0,0.1);border:1px solid rgba(255,215,0,0.3);color:#fff;border-radius:8px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(255,215,0,0.7)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(255,215,0,0.1);border:1px solid rgba(255,215,0,0.3);color:#fff;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-content button{background:linear-gradient(90deg,#ffd700,#ffa500);color:#000;border:none;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(255,215,0,0.5)}

<?php elseif($tid == 25): /* Deep Space */ ?>
#preview-<?php echo $tid; ?> .preview-body{background:#000}
#preview-<?php echo $tid; ?> .preview-card{padding:30px;background:rgba(0,20,40,0.95);border:1px solid rgba(0,150,255,0.5);border-radius:15px;box-shadow:0 0 30px rgba(0,150,255,0.2)}
#preview-<?php echo $tid; ?> .preview-header{background:transparent;border-bottom:1px solid rgba(0,150,255,0.3)}
#preview-<?php echo $tid; ?> .preview-header h2{color:#0096ff;text-shadow:0 0 10px rgba(0,150,255,0.5);letter-spacing:2px}
#preview-<?php echo $tid; ?> .preview-header .subtitle{color:rgba(0,150,255,0.7)}
#preview-<?php echo $tid; ?> .preview-content{background:transparent;padding:0}
#preview-<?php echo $tid; ?> .preview-input-group label{color:rgba(0,150,255,0.8)}
#preview-<?php echo $tid; ?> .preview-input-wrapper input{background:rgba(0,150,255,0.1);border:1px solid rgba(0,150,255,0.3);color:#fff;border-radius:8px;padding-left:36px}
#preview-<?php echo $tid; ?> .preview-input-wrapper svg{fill:rgba(0,150,255,0.7)}
#preview-<?php echo $tid; ?> .preview-content input{background:rgba(0,150,255,0.1);border:1px solid rgba(0,150,255,0.3);color:#fff;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-content button{background:#0096ff;color:#fff;border:none;border-radius:8px}
#preview-<?php echo $tid; ?> .preview-footer{background:transparent;padding:0;margin-top:15px;text-align:center;color:rgba(0,150,255,0.5)}

<?php endif; ?>
</style>
<?php if($tid == 0): /* Library theme preview HTML */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div class="preview-logo-icon">
<svg viewBox="0 0 24 24" width="32" height="32" fill="#f5f0e6">
<path d="M19 2L14 6.5V17.5L19 13V2M6.5 5C4.55 5 2.45 5.4 1 6.5V21.16C1 21.41 1.25 21.66 1.5 21.66C1.6 21.66 1.65 21.59 1.75 21.59C3.1 20.94 5.05 20.5 6.5 20.5C8.45 20.5 10.55 20.9 12 22C13.35 21.15 15.8 20.5 17.5 20.5C19.15 20.5 20.85 20.81 22.25 21.56C22.35 21.61 22.4 21.59 22.5 21.59C22.75 21.59 23 21.34 23 21.09V6.5C22.4 6.05 21.75 5.75 21 5.5V19C19.9 18.65 18.7 18.5 17.5 18.5C15.8 18.5 13.35 19.15 12 20V6.5C10.55 5.4 8.45 5 6.5 5Z"/>
</svg>
</div>
<h2><?php echo h($branding['logo_text'] ?? 'myComix'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_th_userid"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_ph_enter_id'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_label_password"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_ph_enter_password'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo h($branding['login_button'] ?? __('adm_file_login')); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'myComix © 2026'); ?></div>
</div>
</div>
<?php elseif($tid == 1): /* Matrix */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:50px;height:50px;margin:0 auto 10px;border:2px solid #00ff00;display:flex;align-items:center;justify-content:center;font-family:'VT323',monospace;font-size:1.6em;color:#00ff00;text-shadow:0 0 10px #00ff00;">&gt;_</div>
<h2><?php echo h($branding['logo_text'] ?? 'SYSTEM ACCESS'); ?></h2>
<div class="subtitle">// authentication required</div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>&gt; USER_ID:</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="enter_username" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>&gt; PASSWORD:</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="••••••••" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#00aa00;margin:8px 0 12px;display:flex;align-items:center;gap:5px;font-family:'VT323',monospace;"><span>☐</span> [x] keep_session</div>
<button type="button" disabled>[ EXECUTE LOGIN ]</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'SYSTEM v2.026'); ?></div>
</div>
</div>

<?php elseif($tid == 2): /* Glassmorphism */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 12px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.3);">
<svg viewBox="0 0 24 24" style="width:28px;height:28px;fill:rgba(255,255,255,0.9);"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
</div>
<h2><?php echo h($branding['logo_text'] ?? 'myComix'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_th_userid"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_ph_enter_id'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_label_password"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_ph_enter_password'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:rgba(255,255,255,0.7);margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo h($branding['login_button'] ?? __('adm_file_login')); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 3): /* Cyberpunk */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;border:2px solid #ff0066;background:rgba(255,0,102,0.1);clip-path:polygon(25% 0%,75% 0%,100% 50%,75% 100%,25% 100%,0% 50%);display:flex;align-items:center;justify-content:center;font-size:1.5em;">⚡</div>
<h2><?php echo h($branding['logo_text'] ?? 'CYBER//LINK'); ?></h2>
<div class="subtitle">NEURAL_CONNECT v2.0</div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>IDENTITY</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="USER.ID" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>CIPHER</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="••••••••" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.6em;color:#00ffff;margin:8px 0 12px;display:flex;align-items:center;gap:5px;font-family:'Orbitron',sans-serif;"><span>☐</span> PERSIST_SESSION</div>
<button type="button" disabled>◢ JACK IN ◣</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'NETRUN © 2077'); ?></div>
</div>
</div>

<?php elseif($tid == 4): /* Galaxy */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:60px;height:60px;margin:0 auto 10px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 25px rgba(138,43,226,0.5);font-size:1.8em;">🪐</div>
<h2><?php echo h($branding['logo_text'] ?? 'COSMOS'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? 'Gateway to the Stars'); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>PILOT ID</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_space_id'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>ACCESS CODE</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_19'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#e0b0ff;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled>🚀 LAUNCH</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'Starbase © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 5): /* Sakura */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:60px;height:60px;margin:0 auto 10px;background:linear-gradient(135deg,#ff69b4,#ff1493);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 15px rgba(255,105,180,0.4);font-size:1.8em;">🌸</div>
<h2><?php echo h($branding['logo_text'] ?? 'Sakura'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_1'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_2'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#ff69b4;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_enter4"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '🌷 myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 6): /* Dark Gothic */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;border:2px solid #8b0000;display:flex;align-items:center;justify-content:center;font-size:1.6em;color:#8b0000;text-shadow:0 0 15px #8b0000;">⚰️</div>
<h2><?php echo h($branding['logo_text'] ?? 'DARKNESS'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? 'Enter the Shadows'); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>† SOUL NAME †</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_soul'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>† BLOOD SEAL †</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_blood'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.6em;color:#999;margin:8px 0 12px;display:flex;align-items:center;gap:5px;font-family:'Cinzel',serif;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled>† ENTER †</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '— Eternal Darkness —'); ?></div>
</div>
</div>

<?php elseif($tid == 7): /* Minimal White */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:50px;height:50px;margin:0 auto 12px;border:2px solid #e0e0e0;border-radius:50%;display:flex;align-items:center;justify-content:center;">
<svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:#667eea;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
</div>
<h2><?php echo h($branding['logo_text'] ?? 'myComix'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_th_userid"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_ph_enter_id'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_label_password"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_ph_enter_password'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#888;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo h($branding['login_button'] ?? __('adm_file_login')); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 8): /* Retro Arcade */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:50px;height:50px;margin:0 auto 8px;border:3px dashed #00ff00;display:flex;align-items:center;justify-content:center;background:#001100;font-size:1.5em;">👾</div>
<h2><?php echo h($branding['logo_text'] ?? 'ARCADE'); ?></h2>
<div class="subtitle">PRESS START</div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>PLAYER:</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="ENTER NAME" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>SECRET:</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="********" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.5em;color:#00ff00;margin:8px 0 12px;display:flex;align-items:center;gap:5px;font-family:'Press Start 2P',monospace;"><span>☐</span> SAVE GAME</div>
<button type="button" disabled>▶ START</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '© 1P READY'); ?></div>
</div>
</div>

<?php elseif($tid == 9): /* Ocean */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;background:linear-gradient(180deg,#0077be,#00416a);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 15px rgba(0,119,190,0.4);font-size:1.6em;">🐬</div>
<h2><?php echo h($branding['logo_text'] ?? 'OCEAN'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_8'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_7'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:rgba(255,255,255,0.8);margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_sail"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '🐟 myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 10): /* Gradient Motion */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 12px;background:linear-gradient(135deg,#667eea,#764ba2,#f093fb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5em;">✨</div>
<h2><?php echo h($branding['logo_text'] ?? 'myComix'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_th_userid"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_ph_enter_id'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_label_password"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_ph_enter_password'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#764ba2;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo h($branding['login_button'] ?? __('adm_file_login')); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 11): /* Jarvis AI */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:60px;height:60px;margin:0 auto 10px;border:2px solid #00d4ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5em;">🤖</div>
<h2><?php echo h($branding['logo_text'] ?? 'A.I. SYSTEM'); ?></h2>
<div class="subtitle">VOICE INTERFACE READY</div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>USER IDENTIFICATION</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="Operator ID" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>VOICE PRINT</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="Authorization Code" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.6em;color:#00d4ff;margin:8px 0 12px;display:flex;align-items:center;gap:5px;font-family:'Share Tech Mono',monospace;"><span>☐</span> REMEMBER OPERATOR</div>
<button type="button" disabled>◉ INITIALIZE</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'JARVIS v3.0'); ?></div>
</div>
</div>

<?php elseif($tid == 12): /* Aurora */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;background:linear-gradient(135deg,#00ff88,#00d4ff);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 25px rgba(0,255,136,0.4);font-size:1.6em;">🌌</div>
<h2><?php echo h($branding['logo_text'] ?? 'AURORA'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_15'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_16'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#00ff88;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_enter3"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '✧ myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 13): /* Neon Sign */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;border:2px solid #ff00ff;border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 0 15px #ff00ff;font-size:1.5em;">💡</div>
<h2><?php echo h($branding['logo_text'] ?? 'NEON'); ?></h2>
<div class="subtitle">LIGHTS ON</div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>◈ USER</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="Enter name" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>◈ PASS</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="••••••••" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.6em;color:#ff00ff;margin:8px 0 12px;display:flex;align-items:center;gap:5px;text-shadow:0 0 5px #ff00ff;"><span>☐</span> STAY LIT</div>
<button type="button" disabled>⚡ POWER ON</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '◇ NEON © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 14): /* Fire */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;background:linear-gradient(180deg,#ff4500,#ff6600,#ff8c00);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 25px rgba(255,69,0,0.6);font-size:1.6em;">🔥</div>
<h2><?php echo h($branding['logo_text'] ?? 'INFERNO'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_10'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_11'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#ff8c00;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_start"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '🔥 myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 15): /* Aquarium */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;background:linear-gradient(180deg,#00ced1,#008b8b);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 15px rgba(0,206,209,0.4);font-size:1.6em;">🐠</div>
<h2><?php echo h($branding['logo_text'] ?? 'AQUA'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_20'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_9'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#00ced1;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_start"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '🐠 myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 16): /* Winter */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;background:linear-gradient(180deg,#e0f7fa,#b2ebf2);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 15px rgba(135,206,235,0.4);font-size:1.6em;">⛄</div>
<h2><?php echo h($branding['logo_text'] ?? 'WINTER'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_21'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_22'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#4fc3f7;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_enter2"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '❄️ myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 17): /* Gold Luxury */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:60px;height:60px;margin:0 auto 10px;background:linear-gradient(135deg,#ffd700,#b8860b);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 25px rgba(255,215,0,0.5);font-size:1.8em;">👑</div>
<h2><?php echo h($branding['logo_text'] ?? 'ROYAL'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? 'VIP ACCESS'); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>✦ Royal Name</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_6'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>✦ Royal Key</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_17'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#ffd700;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled>♔ ENTER</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '✦ LUXURY © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 18): /* Hologram */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;background:linear-gradient(135deg,#ff0080,#ff8c00,#40e0d0,#8a2be2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.6em;">🦄</div>
<h2><?php echo h($branding['logo_text'] ?? 'HOLO'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_12'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_13'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#ff69b4;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_enter4"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '🌈 myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 19): /* Terminal */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header" style="padding-top:30px;">
<h2 style="font-size:1em;"><?php echo h($branding['logo_text'] ?? 'root@mycomix'); ?></h2>
<div class="subtitle">~ $ ssh login</div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>login:</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="username" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>password:</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="••••••••" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.6em;color:#00ff00;margin:8px 0 12px;display:flex;align-items:center;gap:5px;font-family:'Roboto Mono',monospace;"><span>☐</span> # save session</div>
<button type="button" disabled>> connect</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'GNU/Linux 6.0'); ?></div>
</div>
</div>

<?php elseif($tid == 20): /* Star Wars */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:60px;height:60px;margin:0 auto 10px;border:2px solid #ffe81f;display:flex;align-items:center;justify-content:center;font-size:1.6em;filter:drop-shadow(0 0 8px #ffe81f);">⚔️</div>
<h2><?php echo h($branding['logo_text'] ?? 'GALACTIC'); ?></h2>
<div class="subtitle">A LONG TIME AGO...</div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>JEDI NAME</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="Your name, Jedi" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>FORCE CODE</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="Use the Force" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.6em;color:#ffe81f;margin:8px 0 12px;display:flex;align-items:center;gap:5px;font-family:'Share Tech Mono',monospace;"><span>☐</span> REMEMBER ME</div>
<button type="button" disabled>☆ ENTER GALAXY</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '☆ EPISODE X ☆'); ?></div>
</div>
</div>

<?php elseif($tid == 21): /* Constellation */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;border:2px solid rgba(100,149,237,0.5);border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(100,149,237,0.1);font-size:1.6em;">✨</div>
<h2><?php echo h($branding['logo_text'] ?? 'STELLAR'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_3'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_4'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#6495ed;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_connect"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '✧ myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 22): /* Milky Way */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:60px;height:60px;margin:0 auto 10px;background:linear-gradient(135deg,#9370db,#ff69b4);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 25px rgba(138,43,226,0.5);font-size:1.6em;">🌀</div>
<h2><?php echo h($branding['logo_text'] ?? 'MILKYWAY'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_galaxy'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_dimension'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#9370db;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_start"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '🌀 myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 23): /* Nebula */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;background:linear-gradient(135deg,#00ffff,#ff00ff);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 25px rgba(0,255,255,0.4);font-size:1.6em;">🌫️</div>
<h2><?php echo h($branding['logo_text'] ?? 'NEBULA'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_cosmic'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_5'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#00ffff;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_enter1"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '🌫️ myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 24): /* Meteor Shower */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:55px;height:55px;margin:0 auto 10px;background:linear-gradient(135deg,#ffd700,#ff8c00);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 20px rgba(255,215,0,0.5);font-size:1.6em;">☄️</div>
<h2><?php echo h($branding['logo_text'] ?? 'METEOR'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_login_label_id"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_18'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_login_label_pw"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_14'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#ffd700;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo __("adm_login_btn_start"); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '☄️ myComix © 2026'); ?></div>
</div>
</div>

<?php elseif($tid == 25): /* Deep Space */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<div style="width:60px;height:60px;margin:0 auto 10px;border:2px solid rgba(0,150,255,0.5);border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(0,150,255,0.1);font-size:1.6em;">🛸</div>
<h2><?php echo h($branding['logo_text'] ?? 'DEEP SPACE'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label>PILOT ID</label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_login_ph_astronaut'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label>ACCESS CODE</label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_login_ph_access_code'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.6em;color:#0096ff;margin:8px 0 12px;display:flex;align-items:center;gap:5px;font-family:'Share Tech Mono',monospace;"><span>☐</span> SESSION PERSIST</div>
<button type="button" disabled>🌑 LAUNCH</button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? '🛸 SPACE STATION © 2026'); ?></div>
</div>
</div>

<?php else: /* Default theme */ ?>
<div class="preview-body">
<div class="preview-card">
<div class="preview-header">
<h2><?php echo h($branding['logo_text'] ?? 'myComix'); ?></h2>
<div class="subtitle"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></div>
</div>
<div class="preview-content">
<div class="preview-input-group">
<label><?php echo __("adm_th_userid"); ?></label>
<div class="preview-input-wrapper">
<input type="text" placeholder="<?php echo __('adm_ph_enter_id'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
</div>
</div>
<div class="preview-input-group">
<label><?php echo __("adm_label_password"); ?></label>
<div class="preview-input-wrapper">
<input type="password" placeholder="<?php echo __('adm_ph_enter_password'); ?>" disabled>
<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
</div>
</div>
<div class="preview-checkbox" style="font-size:0.65em;color:#666;margin:8px 0 12px;display:flex;align-items:center;gap:5px;"><span>☐</span> <?php echo __("adm_remember_me"); ?></div>
<button type="button" disabled><?php echo h($branding['login_button'] ?? __('adm_file_login')); ?></button>
</div>
<div class="preview-footer"><?php echo h($branding['copyright'] ?? 'myComix © 2026'); ?></div>
</div>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>

</div><!-- col-md-5 -->
</div><!-- row -->
</div><!-- card-body -->

<script>
// 현재 테마별 배경 설정
var currentBackgrounds = {
<?php foreach($theme_backgrounds as $tid => $bg): ?>
    <?php echo $tid; ?>: '<?php echo h($bg); ?>',
<?php endforeach; ?>
};

// 현재 테마별 필터 설정
var currentFilters = {
<?php foreach($theme_filters as $tid => $filter): ?>
    <?php echo $tid; ?>: '<?php echo h($filter); ?>',
<?php endforeach; ?>
};

function updateBgInput(themeId) {
    document.getElementById('bg_url_input').value = currentBackgrounds[themeId] || '';
}

function updateFilterSelect(themeId) {
    var filterSelect = document.getElementById('bg_filter_select');
    if (filterSelect) {
        filterSelect.value = currentFilters[themeId] || 'none';
    }
}

// 페이지 로드시 초기값 설정
document.addEventListener('DOMContentLoaded', function() {
    var filterThemeSelect = document.getElementById('bg_theme_filter');
    if (filterThemeSelect) {
        updateFilterSelect(filterThemeSelect.value);
    }
});

function selectTheme(e, tid) {
    e.preventDefault();
    e.stopPropagation();
    
    // 현재 스크롤 위치 저장
    var scrollPos = window.scrollY;
    
    // radio 체크
    var radio = document.getElementById('theme_radio_' + tid);
    if (radio) radio.checked = true;
    
    // active 클래스 변경
    document.querySelectorAll('.theme-card').forEach(function(c) {
        c.classList.remove('active');
    });
    e.currentTarget.classList.add('active');
    
    // 미리보기 변경
    changeThemePreview(tid);
    
    // 스크롤 위치 복원
    setTimeout(function() {
        window.scrollTo(0, scrollPos);
    }, 300);
}

function changeThemePreview(tid) {
    // 현재 스크롤 위치 저장
    var scrollPos = window.scrollY;
    
    // 이전 효과 컨테이너 제거
    document.querySelectorAll('.preview-effect-container').forEach(el => el.remove());
    
    document.querySelectorAll('.theme-preview-box').forEach(function(el) {
        el.style.display = 'none';
    });
    document.getElementById('preview-' + tid).style.display = 'block';
    
    // 배경 커스텀 셀렉트 3개도 같이 변경
    ['bg_theme_url', 'bg_theme_file', 'bg_theme_reset'].forEach(function(id) {
        var sel = document.getElementById(id);
        if (sel) sel.value = tid;
    });
    
    // URL 입력창에 현재 배경값 표시
    updateBgInput(tid);
    
    // 스크롤 위치 즉시 복원
    window.scrollTo(0, scrollPos);
    
    // JavaScript 효과 적용
    setTimeout(function() {
        applyPreviewEffect(tid);
        window.scrollTo(0, scrollPos);
    }, 100);
    
    // 추가 복원 (비동기 효과 대응)
    setTimeout(function() {
        window.scrollTo(0, scrollPos);
    }, 200);
}

function applyPreviewEffect(tid) {
    // 스크롤 위치 저장
    var scrollPos = window.scrollY;
    
    var previewBody = document.querySelector('#preview-' + tid + ' .preview-body');
    if (!previewBody) return;
    
    // 기존 효과 제거
    var oldEffect = previewBody.querySelector('.preview-effect-container');
    if (oldEffect) oldEffect.remove();
    
    var container = document.createElement('div');
    container.className = 'preview-effect-container';
    container.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;pointer-events:none;overflow:hidden;z-index:1;';
    
    if (tid == 1) {
        // 매트릭스 효과
        var canvas = document.createElement('canvas');
        canvas.style.cssText = 'width:100%;height:100%;';
        container.appendChild(canvas);
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        
        var ctx = canvas.getContext('2d');
        canvas.width = previewBody.offsetWidth;
        canvas.height = previewBody.offsetHeight;
        
        var chars = 'アァカサタナハマヤャラワガザダバパABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        var fontSize = 10;
        var columns = Math.floor(canvas.width / fontSize);
        var drops = Array(columns).fill(1);
        
        function drawMatrix() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#0f0';
            ctx.font = fontSize + 'px monospace';
            for (var i = 0; i < drops.length; i++) {
                var text = chars.charAt(Math.floor(Math.random() * chars.length));
                ctx.fillStyle = Math.random() > 0.95 ? '#fff' : '#0f0';
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0;
                drops[i]++;
            }
        }
        previewBody.matrixInterval = setInterval(drawMatrix, 50);
        
    } else if (tid == 4) {
        // 갤럭시 별 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        for (var i = 0; i < 30; i++) {
            var star = document.createElement('div');
            star.style.cssText = 'position:absolute;width:' + (Math.random()*2+1) + 'px;height:' + (Math.random()*2+1) + 'px;background:#fff;border-radius:50%;left:' + (Math.random()*100) + '%;top:' + (Math.random()*100) + '%;animation:twinkle ' + (Math.random()*2+1) + 's infinite;';
            container.appendChild(star);
        }
        
    } else if (tid == 5) {
        // 사쿠라 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        var petals = ['🌸', '🏵️', '💮'];
        function createPetal() {
            var petal = document.createElement('div');
            petal.textContent = petals[Math.floor(Math.random() * petals.length)];
            petal.style.cssText = 'position:absolute;left:' + (Math.random()*100) + '%;top:-20px;font-size:' + (Math.random()*8+10) + 'px;animation:sakuraFall ' + (Math.random()*3+3) + 's linear forwards;';
            container.appendChild(petal);
            setTimeout(function() { petal.remove(); }, 6000);
        }
        for (var i = 0; i < 5; i++) setTimeout(createPetal, i * 500);
        previewBody.sakuraInterval = setInterval(createPetal, 800);
        
    } else if (tid == 6) {
        // 고딕 효과 (피+박쥐)
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        function createBlood() {
            var drip = document.createElement('div');
            drip.style.cssText = 'position:absolute;left:' + (Math.random()*100) + '%;top:0;width:2px;height:0;background:linear-gradient(to bottom,#8b0000,transparent);animation:bloodDrip 2s linear forwards;';
            container.appendChild(drip);
            setTimeout(function() { drip.remove(); }, 2000);
        }
        function createBat() {
            var bat = document.createElement('div');
            bat.textContent = '🦇';
            bat.style.cssText = 'position:absolute;left:-20px;top:' + (Math.random()*30+10) + '%;font-size:14px;animation:batFly 4s linear forwards;opacity:0.6;';
            container.appendChild(bat);
            setTimeout(function() { bat.remove(); }, 4000);
        }
        previewBody.bloodInterval = setInterval(createBlood, 600);
        setTimeout(createBat, 500);
        previewBody.batInterval = setInterval(createBat, 3000);
        
    } else if (tid == 9) {
        // 오션 버블 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        function createBubble() {
            var bubble = document.createElement('div');
            var size = Math.random() * 10 + 5;
            bubble.style.cssText = 'position:absolute;left:' + (Math.random()*100) + '%;bottom:-20px;width:' + size + 'px;height:' + size + 'px;background:rgba(255,255,255,0.3);border-radius:50%;animation:bubbleRise ' + (Math.random()*3+2) + 's linear forwards;';
            container.appendChild(bubble);
            setTimeout(function() { bubble.remove(); }, 5000);
        }
        for (var i = 0; i < 5; i++) setTimeout(createBubble, i * 300);
        previewBody.bubbleInterval = setInterval(createBubble, 500);
        
    } else if (tid == 11) {
        // 자비스 HUD 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        for (var i = 0; i < 2; i++) {
            var ring = document.createElement('div');
            var size = 150 + i * 80;
            ring.style.cssText = 'position:absolute;width:' + size + 'px;height:' + size + 'px;border:1px solid rgba(0,212,255,' + (0.3-i*0.1) + ');border-radius:50%;top:50%;left:50%;transform:translate(-50%,-50%);animation:jarvisRotate ' + (8+i*4) + 's linear infinite;';
            container.appendChild(ring);
        }
        
    } else if (tid == 12) {
        // 오로라 효과 (캔버스)
        previewBody.style.position = 'relative';
        var auroraCanvas = document.createElement('canvas');
        auroraCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;';
        auroraCanvas.width = previewBody.offsetWidth;
        auroraCanvas.height = previewBody.offsetHeight;
        previewBody.insertBefore(auroraCanvas, previewBody.firstChild);
        
        var actx = auroraCanvas.getContext('2d');
        var atime = 0;
        var astars = [];
        for (var i = 0; i < 30; i++) {
            astars.push({x: Math.random() * auroraCanvas.width, y: Math.random() * auroraCanvas.height * 0.6, size: Math.random() * 1.5 + 0.5, twinkle: Math.random() * Math.PI * 2});
        }
        
        function drawAuroraPreview() {
            actx.clearRect(0, 0, auroraCanvas.width, auroraCanvas.height);
            
            // 별
            astars.forEach(function(star) {
                star.twinkle += 0.03;
                actx.beginPath();
                actx.arc(star.x, star.y, star.size * (0.6 + Math.sin(star.twinkle) * 0.4), 0, Math.PI * 2);
                actx.fillStyle = 'rgba(255,255,255,' + (0.4 + Math.sin(star.twinkle) * 0.4) + ')';
                actx.fill();
            });
            
            // 오로라 레이어
            var layers = [
                {c1:'0,255,127', c2:'0,255,200', off:0, h:0.5},
                {c1:'0,200,255', c2:'100,255,200', off:1, h:0.4},
                {c1:'150,100,255', c2:'0,255,150', off:2, h:0.45}
            ];
            
            layers.forEach(function(layer) {
                actx.beginPath();
                for (var x = 0; x <= auroraCanvas.width; x += 3) {
                    var w1 = Math.sin(x * 0.02 + atime * 0.8 + layer.off) * 20;
                    var w2 = Math.sin(x * 0.04 + atime * 1.2 + layer.off * 0.5) * 15;
                    var y = auroraCanvas.height * layer.h + w1 + w2;
                    if (x === 0) actx.moveTo(x, y);
                    else actx.lineTo(x, y);
                }
                actx.lineTo(auroraCanvas.width, auroraCanvas.height);
                actx.lineTo(0, auroraCanvas.height);
                actx.closePath();
                
                var grad = actx.createLinearGradient(0, auroraCanvas.height * 0.1, 0, auroraCanvas.height);
                grad.addColorStop(0, 'rgba(' + layer.c1 + ',0.35)');
                grad.addColorStop(0.4, 'rgba(' + layer.c2 + ',0.15)');
                grad.addColorStop(1, 'transparent');
                actx.fillStyle = grad;
                actx.fill();
            });
            
            // 수직 광선
            for (var j = 0; j < 8; j++) {
                var rx = (j / 8) * auroraCanvas.width + Math.sin(atime + j) * 10;
                var rh = auroraCanvas.height * (0.35 + Math.sin(atime * 0.7 + j * 0.5) * 0.15);
                var ra = 0.06 + Math.sin(atime + j * 0.3) * 0.03;
                var rayGrad = actx.createLinearGradient(rx, 0, rx, rh);
                rayGrad.addColorStop(0, 'rgba(0,255,180,' + ra + ')');
                rayGrad.addColorStop(1, 'transparent');
                actx.fillStyle = rayGrad;
                actx.fillRect(rx - 5, 0, 10, rh);
            }
            
            atime += 0.015;
            previewBody.auroraAnim = requestAnimationFrame(drawAuroraPreview);
        }
        drawAuroraPreview();
        
    } else if (tid == 14) {
        // 파이어 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        function createFlame() {
            var flame = document.createElement('div');
            flame.style.cssText = 'position:absolute;left:' + (Math.random()*100) + '%;bottom:-20px;width:8px;height:20px;background:linear-gradient(to top,#ff4500,#ff0000,transparent);border-radius:50%;animation:flameRise 1.5s ease-out forwards;filter:blur(1px);';
            container.appendChild(flame);
            setTimeout(function() { flame.remove(); }, 1500);
        }
        previewBody.flameInterval = setInterval(createFlame, 150);
        
    } else if (tid == 15) {
        // 아쿠아리움 물고기 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        var fishes = ['🐠', '🐟', '🐡'];
        function createFish() {
            var fish = document.createElement('div');
            fish.textContent = fishes[Math.floor(Math.random() * fishes.length)];
            fish.style.cssText = 'position:absolute;left:-30px;top:' + (Math.random()*70+15) + '%;font-size:16px;animation:fishSwimPreview 8s linear forwards;';
            container.appendChild(fish);
            setTimeout(function() { fish.remove(); }, 8000);
        }
        createFish();
        previewBody.fishInterval = setInterval(createFish, 4000);
        
    } else if (tid == 16) {
        // 스노우 눈 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        function createSnow() {
            var snow = document.createElement('div');
            snow.textContent = '❄';
            snow.style.cssText = 'position:absolute;left:' + (Math.random()*100) + '%;top:-10px;font-size:' + (Math.random()*8+6) + 'px;color:rgba(255,255,255,0.8);animation:snowFallPreview ' + (Math.random()*3+3) + 's linear forwards;';
            container.appendChild(snow);
            setTimeout(function() { snow.remove(); }, 6000);
        }
        for (var i = 0; i < 10; i++) setTimeout(createSnow, i * 200);
        previewBody.snowInterval = setInterval(createSnow, 300);
        
    } else if (tid == 20) {
        // 스타워즈 별 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        for (var i = 0; i < 30; i++) {
            var star = document.createElement('div');
            star.style.cssText = 'position:absolute;width:2px;height:2px;background:#ffe81f;border-radius:50%;left:' + (Math.random()*100) + '%;top:' + (Math.random()*100) + '%;animation:starTwinkle ' + (Math.random()*2+1) + 's infinite;';
            container.appendChild(star);
        }
    } else if (tid == 21) {
        // 별자리 연결 효과 (캔버스)
        previewBody.style.position = 'relative';
        var consCanvas = document.createElement('canvas');
        consCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;';
        consCanvas.width = previewBody.offsetWidth;
        consCanvas.height = previewBody.offsetHeight;
        previewBody.insertBefore(consCanvas, previewBody.firstChild);
        
        var cctx = consCanvas.getContext('2d');
        var cstars = [];
        for (var i = 0; i < 40; i++) {
            cstars.push({x: Math.random() * consCanvas.width, y: Math.random() * consCanvas.height, size: Math.random() * 2 + 1, speed: Math.random() * 0.3 + 0.1, twinkle: Math.random() * Math.PI * 2});
        }
        
        function drawConstellation() {
            cctx.fillStyle = 'rgba(10,10,46,0.1)';
            cctx.fillRect(0, 0, consCanvas.width, consCanvas.height);
            cstars.forEach(function(star, i) {
                star.twinkle += 0.02;
                cctx.beginPath();
                cctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                cctx.fillStyle = 'rgba(100,149,237,' + (0.5 + Math.sin(star.twinkle) * 0.5) + ')';
                cctx.fill();
                cstars.forEach(function(star2, j) {
                    if (i < j) {
                        var dist = Math.hypot(star.x - star2.x, star.y - star2.y);
                        if (dist < 60) {
                            cctx.beginPath();
                            cctx.moveTo(star.x, star.y);
                            cctx.lineTo(star2.x, star2.y);
                            cctx.strokeStyle = 'rgba(100,149,237,' + (0.3 - dist/200) + ')';
                            cctx.lineWidth = 0.5;
                            cctx.stroke();
                        }
                    }
                });
                star.y += star.speed;
                if (star.y > consCanvas.height + 10) { star.y = -10; star.x = Math.random() * consCanvas.width; }
            });
            previewBody.consAnim = requestAnimationFrame(drawConstellation);
        }
        drawConstellation();
        
    } else if (tid == 22) {
        // 은하수 흐름 효과 (캔버스)
        previewBody.style.position = 'relative';
        var mwCanvas = document.createElement('canvas');
        mwCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;';
        mwCanvas.width = previewBody.offsetWidth;
        mwCanvas.height = previewBody.offsetHeight;
        previewBody.insertBefore(mwCanvas, previewBody.firstChild);
        
        var mctx = mwCanvas.getContext('2d');
        var mparticles = [];
        for (var i = 0; i < 100; i++) {
            var t = Math.random();
            var baseX = t * mwCanvas.width * 1.5 - mwCanvas.width * 0.25;
            var baseY = t * mwCanvas.height;
            var spread = (Math.random() - 0.5) * 80;
            mparticles.push({baseX: baseX, baseY: baseY, spread: spread, x: baseX + spread * 0.7, y: baseY + spread * 0.3, size: Math.random() * 2 + 0.5, offset: Math.random() * Math.PI * 2, color: 'hsl(' + (Math.random() * 60 + 260) + ', 70%, ' + (Math.random() * 30 + 50) + '%)'});
        }
        var mtime = 0;
        function drawMilkyway() {
            mctx.fillStyle = 'rgba(26,10,62,0.05)';
            mctx.fillRect(0, 0, mwCanvas.width, mwCanvas.height);
            mparticles.forEach(function(p) {
                p.x = p.baseX + Math.sin(mtime + p.offset) * 15 + p.spread * 0.7;
                p.y = p.baseY + Math.cos(mtime + p.offset) * 8 + p.spread * 0.3;
                mctx.beginPath();
                mctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                mctx.fillStyle = p.color;
                mctx.fill();
            });
            mtime += 0.01;
            previewBody.mwAnim = requestAnimationFrame(drawMilkyway);
        }
        drawMilkyway();
        
    } else if (tid == 23) {
        // 네뷸라 효과 (캔버스)
        previewBody.style.position = 'relative';
        var nebCanvas = document.createElement('canvas');
        nebCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;';
        nebCanvas.width = previewBody.offsetWidth;
        nebCanvas.height = previewBody.offsetHeight;
        previewBody.insertBefore(nebCanvas, previewBody.firstChild);
        
        var nctx = nebCanvas.getContext('2d');
        var nclouds = [];
        for (var i = 0; i < 10; i++) {
            nclouds.push({x: Math.random() * nebCanvas.width, y: Math.random() * nebCanvas.height, size: Math.random() * 60 + 40, color: 'hsla(' + (Math.random() * 60 + 180) + ', 80%, 50%, 0.08)', speedX: (Math.random() - 0.5) * 0.2, speedY: (Math.random() - 0.5) * 0.15});
        }
        var nstars = [];
        for (var i = 0; i < 30; i++) {
            nstars.push({x: Math.random() * nebCanvas.width, y: Math.random() * nebCanvas.height, size: Math.random() * 1.5, twinkle: Math.random() * Math.PI * 2});
        }
        var ntime = 0;
        function drawNebula() {
            nctx.fillStyle = 'rgba(30,10,50,0.03)';
            nctx.fillRect(0, 0, nebCanvas.width, nebCanvas.height);
            nclouds.forEach(function(cloud) {
                var gradient = nctx.createRadialGradient(cloud.x, cloud.y, 0, cloud.x, cloud.y, cloud.size);
                gradient.addColorStop(0, cloud.color);
                gradient.addColorStop(1, 'transparent');
                nctx.beginPath();
                nctx.arc(cloud.x, cloud.y, cloud.size, 0, Math.PI * 2);
                nctx.fillStyle = gradient;
                nctx.fill();
                cloud.x += cloud.speedX;
                cloud.y += cloud.speedY;
                if (cloud.x < -cloud.size) cloud.x = nebCanvas.width + cloud.size;
                if (cloud.x > nebCanvas.width + cloud.size) cloud.x = -cloud.size;
                if (cloud.y < -cloud.size) cloud.y = nebCanvas.height + cloud.size;
                if (cloud.y > nebCanvas.height + cloud.size) cloud.y = -cloud.size;
            });
            nstars.forEach(function(star) {
                star.twinkle += 0.05;
                nctx.beginPath();
                nctx.arc(star.x, star.y, star.size * (0.5 + Math.sin(star.twinkle) * 0.5), 0, Math.PI * 2);
                nctx.fillStyle = 'rgba(0,255,255,' + (0.5 + Math.sin(star.twinkle) * 0.5) + ')';
                nctx.fill();
            });
            ntime += 0.015;
            previewBody.nebAnim = requestAnimationFrame(drawNebula);
        }
        drawNebula();
        
    } else if (tid == 24) {
        // 유성우 효과 (캔버스)
        previewBody.style.position = 'relative';
        var metCanvas = document.createElement('canvas');
        metCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;';
        metCanvas.width = previewBody.offsetWidth;
        metCanvas.height = previewBody.offsetHeight;
        previewBody.insertBefore(metCanvas, previewBody.firstChild);
        
        var metctx = metCanvas.getContext('2d');
        var metstars = [];
        for (var i = 0; i < 50; i++) {
            metstars.push({x: Math.random() * metCanvas.width, y: Math.random() * metCanvas.height, size: Math.random() * 1.5, twinkle: Math.random() * Math.PI * 2});
        }
        var meteors = [];
        function createMeteorPreview() {
            meteors.push({x: Math.random() * metCanvas.width * 1.2, y: -10, length: Math.random() * 50 + 30, speed: Math.random() * 6 + 4, angle: Math.PI / 4});
        }
        function drawMeteorShower() {
            metctx.fillStyle = 'rgba(0,0,32,0.15)';
            metctx.fillRect(0, 0, metCanvas.width, metCanvas.height);
            metstars.forEach(function(star) {
                star.twinkle += 0.03;
                metctx.beginPath();
                metctx.arc(star.x, star.y, star.size * (0.5 + Math.sin(star.twinkle) * 0.5), 0, Math.PI * 2);
                metctx.fillStyle = '#fff';
                metctx.fill();
            });
            meteors.forEach(function(meteor, i) {
                var gradient = metctx.createLinearGradient(meteor.x, meteor.y, meteor.x - Math.cos(meteor.angle) * meteor.length, meteor.y - Math.sin(meteor.angle) * meteor.length);
                gradient.addColorStop(0, 'rgba(255,215,0,1)');
                gradient.addColorStop(1, 'rgba(255,215,0,0)');
                metctx.beginPath();
                metctx.moveTo(meteor.x, meteor.y);
                metctx.lineTo(meteor.x - Math.cos(meteor.angle) * meteor.length, meteor.y - Math.sin(meteor.angle) * meteor.length);
                metctx.strokeStyle = gradient;
                metctx.lineWidth = 2;
                metctx.stroke();
                meteor.x += Math.cos(meteor.angle) * meteor.speed;
                meteor.y += Math.sin(meteor.angle) * meteor.speed;
                if (meteor.y > metCanvas.height + 50) meteors.splice(i, 1);
            });
            if (Math.random() < 0.03) createMeteorPreview();
            previewBody.metAnim = requestAnimationFrame(drawMeteorShower);
        }
        drawMeteorShower();
        
    } else if (tid == 25) {
        // 딥 스페이스 효과 (캔버스)
        previewBody.style.position = 'relative';
        var dsCanvas = document.createElement('canvas');
        dsCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;';
        dsCanvas.width = previewBody.offsetWidth;
        dsCanvas.height = previewBody.offsetHeight;
        previewBody.insertBefore(dsCanvas, previewBody.firstChild);
        
        var dsctx = dsCanvas.getContext('2d');
        var dsstars = [];
        var dscx = dsCanvas.width / 2;
        var dscy = dsCanvas.height / 2;
        for (var i = 0; i < 100; i++) {
            dsstars.push({x: Math.random() * dsCanvas.width, y: Math.random() * dsCanvas.height, z: Math.random() * dsCanvas.width, size: Math.random() * 2});
        }
        function drawDeepSpace() {
            dsctx.fillStyle = 'rgba(0,0,0,0.1)';
            dsctx.fillRect(0, 0, dsCanvas.width, dsCanvas.height);
            dsstars.forEach(function(star) {
                star.z -= 2;
                if (star.z <= 0) { star.z = dsCanvas.width; star.x = Math.random() * dsCanvas.width; star.y = Math.random() * dsCanvas.height; }
                var k = 100 / star.z;
                var px = (star.x - dscx) * k + dscx;
                var py = (star.y - dscy) * k + dscy;
                var size = (1 - star.z / dsCanvas.width) * 3;
                var alpha = 1 - star.z / dsCanvas.width;
                dsctx.beginPath();
                dsctx.arc(px, py, size, 0, Math.PI * 2);
                dsctx.fillStyle = 'rgba(0,150,255,' + alpha + ')';
                dsctx.fill();
            });
            previewBody.dsAnim = requestAnimationFrame(drawDeepSpace);
        }
        drawDeepSpace();
        
    } else if (tid == 2) {
        // 글래스모피즘 - 유리 반사 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        var shine = document.createElement('div');
        shine.style.cssText = 'position:absolute;top:0;left:-100%;width:50%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.1),transparent);animation:glassShine 4s infinite;';
        container.appendChild(shine);
    } else if (tid == 3) {
        // 사이버펑크 - 글리치 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        var glitch = document.createElement('div');
        glitch.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(transparent 50%, rgba(255,0,255,0.03) 50%);background-size:100% 4px;animation:scanline 0.5s linear infinite;';
        container.appendChild(glitch);
    } else if (tid == 7) {
        // 미니멀 화이트 - 부드러운 그림자 움직임
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        var shadow = document.createElement('div');
        shadow.style.cssText = 'position:absolute;top:50%;left:50%;width:80%;height:80%;transform:translate(-50%,-50%);background:radial-gradient(ellipse,rgba(0,0,0,0.03),transparent);animation:minimalPulse 4s ease-in-out infinite;';
        container.appendChild(shadow);
    } else if (tid == 8) {
        // 레트로 아케이드 - 픽셀 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        for (var i = 0; i < 8; i++) {
            var pixel = document.createElement('div');
            pixel.style.cssText = 'position:absolute;width:4px;height:4px;background:' + ['#ff0','#0ff','#f0f','#0f0'][i%4] + ';left:' + (Math.random()*100) + '%;top:' + (Math.random()*100) + '%;animation:pixelBlink ' + (Math.random()*1+0.5) + 's step-end infinite;';
            container.appendChild(pixel);
        }
    } else if (tid == 10) {
        // 그라데이션 모션 - 색상 흐름
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        container.style.background = 'linear-gradient(45deg, rgba(102,126,234,0.3), rgba(118,75,162,0.3), rgba(102,126,234,0.3))';
        container.style.backgroundSize = '200% 200%';
        container.style.animation = 'gradientFlow 5s ease infinite';
    } else if (tid == 13) {
        // 네온 시티 - 네온 깜빡임
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        for (var i = 0; i < 3; i++) {
            var neon = document.createElement('div');
            neon.style.cssText = 'position:absolute;height:1px;background:#ff00ff;box-shadow:0 0 5px #ff00ff;left:0;right:0;top:' + (20+i*30) + '%;animation:neonFlash ' + (1.5+i*0.5) + 's ease-in-out infinite;opacity:0.3;';
            container.appendChild(neon);
        }
    } else if (tid == 17) {
        // 골드 럭셔리 - 반짝이 효과
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        function createSparkle() {
            var sparkle = document.createElement('div');
            sparkle.textContent = '✦';
            sparkle.style.cssText = 'position:absolute;left:' + (Math.random()*100) + '%;top:' + (Math.random()*100) + '%;font-size:8px;color:#ffd700;animation:goldSparkle 1.5s ease-out forwards;';
            container.appendChild(sparkle);
            setTimeout(function() { sparkle.remove(); }, 1500);
        }
        previewBody.sparkleInterval = setInterval(createSparkle, 400);
    } else if (tid == 18) {
        // 홀로그램 - 무지개 흐름
        previewBody.style.position = 'relative';
        previewBody.insertBefore(container, previewBody.firstChild);
        var holo = document.createElement('div');
        holo.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(45deg,rgba(255,0,0,0.1),rgba(255,165,0,0.1),rgba(255,255,0,0.1),rgba(0,255,0,0.1),rgba(0,255,255,0.1),rgba(138,43,226,0.1));background-size:300% 300%;animation:holoShift 3s ease infinite;';
        container.appendChild(holo);
    } else if (tid == 19) {
        // 터미널 - CSS로 커서 처리 (JavaScript 효과 없음)
    }
    
    // 스크롤 위치 복원
    window.scrollTo(0, scrollPos);
}

// 애니메이션 키프레임 추가
var style = document.createElement('style');
style.textContent = `
@keyframes twinkle{0%,100%{opacity:0.3;transform:scale(1)}50%{opacity:1;transform:scale(1.2)}}
@keyframes sakuraFall{0%{transform:translateY(0) rotate(0deg);opacity:0.8}100%{transform:translateY(400px) rotate(360deg);opacity:0.2}}
@keyframes bloodDrip{0%{height:0;opacity:1}100%{height:100px;opacity:0}}
@keyframes batFly{0%{transform:translateX(0)}100%{transform:translateX(400px)}}
@keyframes bubbleRise{0%{transform:translateY(0);opacity:0.5}100%{transform:translateY(-400px);opacity:0}}
@keyframes jarvisRotate{from{transform:translate(-50%,-50%) rotate(0deg)}to{transform:translate(-50%,-50%) rotate(360deg)}}
@keyframes auroraWave{0%,100%{transform:translateY(0);opacity:0.3}50%{transform:translateY(-20px);opacity:0.6}}
@keyframes flameRise{0%{transform:translateY(0);opacity:1}100%{transform:translateY(-150px);opacity:0}}
@keyframes fishSwimPreview{0%{transform:translateX(0)}100%{transform:translateX(400px)}}
@keyframes snowFallPreview{0%{transform:translateY(0);opacity:0.8}100%{transform:translateY(400px);opacity:0.2}}
@keyframes starTwinkle{0%,100%{opacity:0.3}50%{opacity:1}}
@keyframes glassShine{0%{left:-100%}100%{left:200%}}
@keyframes scanline{0%{opacity:0.5}50%{opacity:0.3}100%{opacity:0.5}}
@keyframes minimalPulse{0%,100%{transform:translate(-50%,-50%) scale(1)}50%{transform:translate(-50%,-50%) scale(1.05)}}
@keyframes pixelBlink{0%,50%{opacity:1}51%,100%{opacity:0.3}}
@keyframes gradientFlow{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes neonFlash{0%,100%{opacity:0.3}50%{opacity:0.8}}
@keyframes goldSparkle{0%{transform:scale(0);opacity:1}100%{transform:scale(2);opacity:0}}
@keyframes holoShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes terminalCursor{0%,50%{opacity:1}51%,100%{opacity:0}}
`;
document.head.appendChild(style);

// 페이지 로드시 현재 테마 효과 적용
document.addEventListener('DOMContentLoaded', function() {
    var currentTheme = <?php echo $current_theme; ?>;
    setTimeout(function() {
        applyPreviewEffect(currentTheme);
    }, 500);
});
</script>

</div><!-- card -->
</div><!-- theme tab -->


<!-- Branding -->
<div class="tab-pane fade" id="branding">
<div class="card m-2 p-0">
<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<div class="card-header bg-info text-white"><?php echo __("adm_card_branding"); ?></div>
<ul class="list-group list-group-flush">
<li class="list-group-item">
<p class="text-muted mb-2"><?php echo __("adm_branding_desc"); ?></p>
<table class="config-table" width="100%">
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_logo_type"); ?></td><td class="">
<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="logo_type" id="logo_type_text" value="text" <?php if(($branding['logo_type'] ?? 'text') === 'text') echo 'checked'; ?> onchange="toggleLogoType()"><label class="form-check-label" for="logo_type_text">Text Logo</label></div>
<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="logo_type" id="logo_type_image" value="image" <?php if(($branding['logo_type'] ?? 'text') === 'image') echo 'checked'; ?> onchange="toggleLogoType()"><label class="form-check-label" for="logo_type_image">Image Logo</label></div>
</td></tr>
<tr class="border-bottom" id="text_logo_row" style="<?php echo ($branding['logo_type'] ?? 'text') === 'image' ? 'display:none;' : ''; ?>"><td class="text-right"><?php echo __("adm_cfg_logo_title"); ?></td><td class="">
<input type="text" class="form-control" id="logo_text" name="logo_text" value="<?php echo h($branding['logo_text'] ?? 'myComix'); ?>" maxlength="30">
<small class="text-muted"><?php echo __("adm_branding_logo_help"); ?></small>
</td></tr>
<tr class="border-bottom" id="image_logo_row" style="<?php echo ($branding['logo_type'] ?? 'text') === 'text' ? 'display:none;' : ''; ?>"><td class="text-right"><?php echo __("adm_cfg_logo_image"); ?></td><td class="">
<?php if(!empty($branding['logo_image']) && file_exists($branding['logo_image'])): ?>
<div class="mb-2 p-2 bg-light rounded d-inline-block">
<img src="<?php echo h($branding['logo_image']); ?>" alt="Logo" style="max-height:60px; max-width:200px;">
<div class="form-check mt-2">
<input class="form-check-input" type="checkbox" name="delete_logo_image" value="1" id="delete_logo_image">
<label class="form-check-label text-danger" for="delete_logo_image"><?php echo __("adm_branding_delete_img"); ?></label>
</div>
</div><br>
<?php endif; ?>
<input type="file" class="form-control-file" name="logo_image" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
<small class="text-muted"><?php echo __("adm_branding_img_help"); ?></small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_subtitle"); ?></td><td class="">
<input type="text" class="form-control" id="subtitle" name="subtitle" value="<?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?>" maxlength="50">
<small class="text-muted"><?php echo __("adm_branding_subtitle_help"); ?></small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_login_button"); ?></td><td class="">
<input type="text" class="form-control" id="login_button" name="login_button" value="<?php echo h($branding['login_button'] ?? __('login_submit')); ?>" maxlength="20">
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_copyright"); ?></td><td class="">
<input type="text" class="form-control" id="copyright" name="copyright" value="<?php echo h($branding['copyright'] ?? 'myComix © 2026'); ?>" maxlength="50">
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_branding_page_title"); ?></td><td class="">
<input type="text" class="form-control" id="page_title" name="page_title" value="<?php echo h($branding['page_title'] ?? 'myComix'); ?>" maxlength="50">
<small class="text-muted"><?php echo __("adm_branding_tab_desc"); ?></small>
</td></tr>
<tr class="border-bottom"><td class="text-right"><?php echo __("adm_cfg_admin_title"); ?></td><td class="">
<input type="text" class="form-control" id="admin_title" name="admin_title" value="<?php echo h($branding['admin_title'] ?? 'myComix - Admin'); ?>" maxlength="50">
<small class="text-muted"><?php echo __("adm_branding_admin_tab_desc"); ?></small>
</td></tr>
<tr><td class="text-right"><?php echo __("adm_cfg_preview"); ?></td><td class="">
<div class="p-3 bg-light rounded border">
<?php if(($branding['logo_type'] ?? 'text') === 'image' && !empty($branding['logo_image']) && file_exists($branding['logo_image'])): ?>
<img src="<?php echo h($branding['logo_image']); ?>" alt="Logo" style="max-height:40px;">
<?php else: ?>
<span style="font-family:'Gugi',sans-serif; font-size:1.5em;"><?php echo h($branding['logo_text'] ?? 'myComix'); ?></span>
<?php endif; ?>
<br><small class="text-muted"><?php echo h($branding['subtitle'] ?? __('adm_default_subtitle')); ?></small>
<br><button type="button" class="btn btn-primary btn-sm mt-2" disabled><?php echo h($branding['login_button'] ?? __('adm_file_login')); ?></button>
<br><small class="text-muted mt-2 d-block"><?php echo h($branding['copyright'] ?? 'myComix © 2026'); ?></small>
</div>
</td></tr>
</table>
</li>
<li class="list-group-item p-0"><input type="hidden" name="mode" value="branding_change"><button class="btn btn-info btn-block btn-sm" type="submit"><?php echo __("adm_btn_save_branding"); ?></button></li>
</ul>
</form>
</div>
</div>

<script>
function toggleLogoType() {
    var isImage = document.getElementById('logo_type_image').checked;
    document.getElementById('text_logo_row').style.display = isImage ? 'none' : '';
    document.getElementById('image_logo_row').style.display = isImage ? '' : 'none';
}

// ✅ 자동 로그아웃 설정 함수 (disabled 대신 CSS로 시각적 비활성화 - 값은 항상 전송됨)
function toggleAutoLogoutTimeout() {
    var enabled = document.getElementById('auto_logout_enabled').checked;
    
    // 타임아웃 시간 행 - 시각적 비활성화만 (값은 전송됨)
    var timeoutRow = document.getElementById('auto_logout_timeout_row');
    timeoutRow.style.opacity = enabled ? '1' : '0.5';
    timeoutRow.style.pointerEvents = enabled ? '' : 'none';
    
    // 적용 페이지 행 - 시각적 비활성화만 (값은 전송됨)
    var pagesRow = document.getElementById('auto_logout_pages_row');
    if (pagesRow) {
        pagesRow.style.opacity = enabled ? '1' : '0.5';
        pagesRow.style.pointerEvents = enabled ? '' : 'none';
    }
}

function updateAutoLogoutTimeout() {
    var minutes = parseInt(document.getElementById('auto_logout_minutes').value) || 0;
    var seconds = parseInt(document.getElementById('auto_logout_seconds').value) || 0;
    var total = (minutes * 60) + seconds;
    
    // 최소 60초, 최대 7200초
    if (total < 60) total = 60;
    if (total > 7200) total = 7200;
    
    document.getElementById('auto_logout_timeout').value = total;
    document.getElementById('auto_logout_total').textContent = '(' + total + '<?php echo __("adm_unit_sec"); ?>)';
}

// ✅ 프라이버시 보호 설정 함수 (disabled 대신 CSS로 시각적 비활성화 - 값은 항상 전송됨)
function togglePrivacyShieldPages() {
    var enabled = document.getElementById('privacy_shield_enabled').checked;
    var pagesRow = document.getElementById('privacy_shield_pages_row');
    var debugRow = document.getElementById('privacy_shield_debug_row');
    if (pagesRow) {
        pagesRow.style.opacity = enabled ? '1' : '0.5';
        pagesRow.style.pointerEvents = enabled ? '' : 'none';
    }
    if (debugRow) {
        debugRow.style.opacity = enabled ? '1' : '0.5';
        debugRow.style.pointerEvents = enabled ? '' : 'none';
    }
}

// 페이지 로드 시 초기 상태 설정
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('auto_logout_enabled')) {
        toggleAutoLogoutTimeout();
    }
    if (document.getElementById('privacy_shield_enabled')) {
        togglePrivacyShieldPages();
    }
});
</script>


<!-- Notice (popup + banner) -->
<div class="tab-pane fade" id="notice">
<div class="card m-2">
<div class="card-header" style="background:#fd7e14;color:#fff;"><?php echo __("adm_card_notice_settings"); ?></div>
<div class="card-body">

<?php
// 다중 팝업 설정 로드
$popups = get_app_settings('popups', []);
$banner_settings = get_app_settings('banner', [
    'enabled' => false,
    'content' => '',
    'bg_color' => '#fff3cd',
    'text_color' => '#856404',
    'link' => ''
]);
?>

<!-- Popup management -->
<div class="card mb-4">
<div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
    <div>
        <strong><?php echo __("adm_notice_popup_mgmt"); ?></strong>
        <small class="text-muted ml-2"><?php echo __("adm_notice_popup_desc"); ?></small>
    </div>
    <button type="button" class="btn btn-sm btn-primary" onclick="openPopupModal(-1)" <?php echo count($popups) >= 10 ? 'disabled' : ''; ?>>
        ➕ <?php echo __('adm_btn_add_popup'); ?>
    </button>
</div>
<div class="card-body p-0">
    <?php if (empty($popups)): ?>
    <div class="text-center py-4 text-muted">
        <p><?php echo __("adm_notice_no_popups"); ?></p>
        <button type="button" class="btn btn-primary btn-sm" onclick="openPopupModal(-1)"><?php echo __("adm_btn_add_first_popup"); ?></button>
    </div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table table-sm table-hover mb-0" style="font-size:12px;">
    <thead class="thead-light">
    <tr>
        <th width="40"><?php echo __("adm_th_order"); ?></th>
        <th width="50"><?php echo __("adm_th_status"); ?></th>
        <th width="100"><?php echo __("adm_th_title"); ?></th>
        <th><?php echo __("adm_th_content"); ?></th>
        <th width="70"><?php echo __("adm_th_display_type"); ?></th>
        <th width="60"><?php echo __("adm_th_header_color"); ?></th>
        <th width="80"><?php echo __("adm_th_size"); ?></th>
        <th width="90"><?php echo __("adm_th_period"); ?></th>
        <th width="50"><?php echo __("adm_th_image"); ?></th>
        <th width="70"><?php echo __("adm_th_action"); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php 
    // 순서대로 정렬
    usort($popups, function($a, $b) {
        return ($a['order'] ?? 0) - ($b['order'] ?? 0);
    });
    foreach ($popups as $idx => $popup): 
        $has_image = !empty($popup['image_file']) || !empty($popup['image_url']);
        $show_mode = $popup['show_mode'] ?? 'both';
        $mode_labels = ['both' => __('adm_popup_mode_all'), 'image' => __('adm_popup_mode_image'), 'text' => __('adm_popup_mode_text')];
        $bg_color = $popup['bg_color'] ?? '#ffffff';
    ?>
    <tr>
        <td class="text-center"><span class="badge badge-secondary"><?php echo ($popup['order'] ?? $idx + 1); ?></span></td>
        <td>
            <?php if ($popup['enabled'] ?? false): ?>
            <span class="badge badge-success"><?php echo __('adm_status_active_badge'); ?></span>
            <?php else: ?>
            <span class="badge badge-secondary"><?php echo __('adm_status_inactive_badge'); ?></span>
            <?php endif; ?>
        </td>
        <td><strong><?php echo h($popup['title'] ?: __('adm_no_title')); ?></strong></td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <small class="text-muted"><?php echo h(mb_substr($popup['content'] ?? '', 0, 30)); ?><?php echo mb_strlen($popup['content'] ?? '') > 30 ? '...' : ''; ?></small>
        </td>
        <td><span class="badge badge-<?php echo $show_mode === 'both' ? 'primary' : ($show_mode === 'image' ? 'info' : 'warning'); ?>"><?php echo $mode_labels[$show_mode] ?? __('adm_popup_mode_all'); ?></span></td>
        <td><span style="display:inline-block;width:24px;height:24px;background:<?php echo h($bg_color); ?>;border:1px solid #ccc;border-radius:3px;vertical-align:middle;"></span></td>
        <td style="font-size:11px;">
            <?php 
            $w = $popup['width'] ?? '';
            $h = $popup['height'] ?? '';
            echo ($w ?: 'auto') . '×' . ($h ?: 'auto');
            ?>
        </td>
        <td style="font-size:10px;">
            <?php 
            $sd = $popup['start_date'] ?? '';
            $ed = $popup['end_date'] ?? '';
            if ($sd || $ed) {
                echo ($sd ?: __('adm_date_start')) . '<br>~' . ($ed ?: __('adm_date_end'));
            } else {
                echo '<span class="text-muted">' . __('adm_always') . '</span>';
            }
            ?>
        </td>
        <td>
            <?php if ($has_image): ?>
            <span class="badge badge-info"><?php echo __('adm_yes'); ?></span>
            <?php else: ?>
            <span class="badge badge-light"><?php echo __("adm_none"); ?></span>
            <?php endif; ?>
        </td>
        <td>
            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openPopupModal(<?php echo $idx; ?>)" title="<?php echo __('adm_btn_edit'); ?>">✏️</button>
            <form method="post" style="display:inline;" onsubmit="return confirm(i18n_adm.confirm_delete_popup);">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="mode" value="delete_popup">
                <input type="hidden" name="popup_idx" value="<?php echo $idx; ?>">
                <button type="submit" class="btn btn-xs btn-outline-danger" title="<?php echo __('adm_btn_delete_title'); ?>">🗑️</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<!-- Layout settings -->
<div class="card-footer bg-light">
<form method="post" class="mb-0">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="mode" value="save_popup_settings">
    <?php 
    $popup_layout = get_app_settings('popup_layout', 'horizontal');
    $popup_default_width = get_app_settings('popup_default_width', 350);
    $popup_default_height = get_app_settings('popup_default_height', 250);
    $popup_gap = get_app_settings('popup_gap', 20);
    ?>
    
    <!-- Layout mode -->
    <div class="d-flex align-items-center flex-wrap mb-2" style="gap:10px;">
        <strong><?php echo __("adm_popup_layout"); ?></strong>
        <div class="btn-group btn-group-toggle" data-toggle="buttons">
            <label class="btn btn-sm btn-outline-secondary <?php echo $popup_layout === 'horizontal' ? 'active' : ''; ?>">
                <input type="radio" name="popup_layout" value="horizontal" <?php echo $popup_layout === 'horizontal' ? 'checked' : ''; ?>> <?php echo __("adm_popup_horizontal"); ?>
            </label>
            <label class="btn btn-sm btn-outline-secondary <?php echo $popup_layout === 'vertical' ? 'active' : ''; ?>">
                <input type="radio" name="popup_layout" value="vertical" <?php echo $popup_layout === 'vertical' ? 'checked' : ''; ?>> <?php echo __("adm_popup_vertical"); ?>
            </label>
            <label class="btn btn-sm btn-outline-secondary <?php echo $popup_layout === 'grid' ? 'active' : ''; ?>">
                <input type="radio" name="popup_layout" value="grid" <?php echo $popup_layout === 'grid' ? 'checked' : ''; ?>> <?php echo __('adm_popup_grid'); ?>
            </label>
        </div>
        <span class="text-muted" style="font-size:11px;">
            (H:[1][2][3]→ V:[1]↓[2]↓ Grid:[1][2]↓[3][4])
        </span>
    </div>
    
    <!-- Default size & spacing -->
    <div class="d-flex align-items-center flex-wrap" style="gap:15px;">
        <div class="d-flex align-items-center" style="gap:5px;">
            <strong><?php echo __("adm_popup_default_size"); ?></strong>
            <input type="number" name="popup_default_width" value="<?php echo (int)$popup_default_width; ?>" class="form-control form-control-sm" style="width:70px;" min="200" max="800">
            <span>x</span>
            <input type="number" name="popup_default_height" value="<?php echo (int)$popup_default_height; ?>" class="form-control form-control-sm" style="width:70px;" min="100" max="600">
            <span>px</span>
        </div>
        <div class="d-flex align-items-center" style="gap:5px;">
            <strong><?php echo __("adm_popup_spacing"); ?></strong>
            <input type="number" name="popup_gap" value="<?php echo (int)$popup_gap; ?>" class="form-control form-control-sm" style="width:60px;" min="0" max="50">
            <span>px</span>
        </div>
        <button type="submit" class="btn btn-sm btn-primary"><?php echo __("adm_btn_save"); ?></button>
    </div>
    <small class="text-muted d-block mt-2"><?php echo __("adm_popup_size_note"); ?></small>
</form>
</div>
</div>

<!-- Banner settings -->
<div class="card mb-4">
<div class="card-header bg-light py-2">
    <strong><?php echo __("adm_banner_title"); ?></strong>
    <small class="text-muted ml-2"><?php echo __("adm_banner_desc"); ?></small>
</div>
<div class="card-body">
<form method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="save_banner">

<table class="table table-borderless mb-3" style="width:100%;">
<tr>
    <td colspan="2" style="padding:8px 0;">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="banner_enabled" name="banner_enabled" 
                   <?php echo ($banner_settings['enabled'] ?? false) ? 'checked' : ''; ?>>
            <label class="custom-control-label" for="banner_enabled"><strong><?php echo __("adm_section_banner_enable"); ?></strong></label>
        </div>
    </td>
</tr>
<tr>
    <th style="width:120px;vertical-align:middle;padding:8px 0;"><?php echo __("adm_banner_content_label"); ?></th>
    <td style="padding:8px 0;">
        <input type="text" class="form-control" name="banner_content" style="width:100%;" value="<?php echo h($banner_settings['content'] ?? ''); ?>" placeholder="e.g. 📢 Server maintenance: 1/25 02:00~04:00">
    </td>
</tr>
<tr>
    <th style="width:120px;vertical-align:middle;padding:8px 0;"><?php echo __("adm_banner_link_label"); ?></th>
    <td style="padding:8px 0;">
        <input type="text" class="form-control" name="banner_link" style="width:100%;" value="<?php echo h($banner_settings['link'] ?? ''); ?>" placeholder="e.g. https://example.com/notice">
        <small class="text-muted"><?php echo __("adm_banner_link_help"); ?></small>
    </td>
</tr>
<tr>
    <th style="width:120px;vertical-align:middle;padding:8px 0;"><?php echo __("adm_modal_start_date"); ?></th>
    <td style="padding:8px 0;">
        <input type="date" class="form-control" name="banner_start_date" style="width:100%;max-width:200px;" value="<?php echo h($banner_settings['start_date'] ?? ''); ?>">
        <small class="text-muted"><?php echo __("adm_banner_start_help"); ?></small>
    </td>
</tr>
<tr>
    <th style="width:120px;vertical-align:middle;padding:8px 0;"><?php echo __("adm_modal_end_date"); ?></th>
    <td style="padding:8px 0;">
        <input type="date" class="form-control" name="banner_end_date" style="width:100%;max-width:200px;" value="<?php echo h($banner_settings['end_date'] ?? ''); ?>">
        <small class="text-muted"><?php echo __("adm_modal_indefinite_note"); ?></small>
    </td>
</tr>
<tr>
    <th style="width:120px;vertical-align:middle;padding:8px 0;"><?php echo __("adm_banner_bg_label"); ?></th>
    <td style="padding:8px 0;">
        <input type="color" name="banner_bg_color" value="<?php echo h($banner_settings['bg_color'] ?? '#fff3cd'); ?>" style="width:80px;height:36px;padding:2px;border:1px solid #ccc;border-radius:4px;cursor:pointer;">
    </td>
</tr>
<tr>
    <th style="width:120px;vertical-align:middle;padding:8px 0;"><?php echo __("adm_banner_text_label"); ?></th>
    <td style="padding:8px 0;">
        <input type="color" name="banner_text_color" value="<?php echo h($banner_settings['text_color'] ?? '#856404'); ?>" style="width:80px;height:36px;padding:2px;border:1px solid #ccc;border-radius:4px;cursor:pointer;">
    </td>
</tr>
<tr>
    <th style="width:120px;vertical-align:top;padding:8px 0;"><?php echo __("adm_th_preview"); ?></th>
    <td style="padding:8px 0;">
        <div id="bannerPreview" style="padding:12px 15px;border-radius:4px;text-align:left;background:<?php echo h($banner_settings['bg_color'] ?? '#fff3cd'); ?>;color:<?php echo h($banner_settings['text_color'] ?? '#856404'); ?>;font-size:14px;">
            <?php echo h($banner_settings['content'] ?: '<?php echo __("adm_banner_preview_text"); ?>'); ?>
        </div>
    </td>
</tr>
</table>

<button type="submit" class="btn btn-primary btn-block"><?php echo __("adm_btn_save_banner"); ?></button>
</form>
</div>
</div>

</div>
</div>
</div>

<!-- Popup edit modal -->
<div class="modal fade" id="popupEditModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<form method="post" enctype="multipart/form-data" id="popupEditForm">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="save_popup">
<input type="hidden" name="popup_idx" id="popupEditIdx" value="-1">

<div class="modal-header bg-primary text-white py-2">
    <h5 class="modal-title" id="popupModalTitle"><?php echo __("adm_heading_popup_add"); ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body" style="max-height:70vh;overflow-y:auto;padding:15px 20px;">
    
<table class="table table-borderless mb-0" style="width:100%;">
<!-- Basic settings -->
<tr>
    <th style="width:110px;vertical-align:middle;padding:8px 0;"><?php echo __("adm_popup_enabled"); ?></th>
    <td style="padding:8px 0;">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="edit_popup_enabled" name="popup_enabled" checked>
            <label class="custom-control-label" for="edit_popup_enabled"><?php echo __("adm_popup_show"); ?></label>
        </div>
    </td>
</tr>
<tr>
    <th style="vertical-align:middle;padding:8px 0;"><?php echo __("adm_popup_order"); ?></th>
    <td style="padding:8px 0;">
        <input type="number" class="form-control" name="popup_order" id="edit_popup_order" min="1" max="10" value="1" style="width:100%;max-width:100px;">
        <small class="text-muted"><?php echo __("adm_popup_order_help"); ?></small>
    </td>
</tr>
<tr>
    <th style="vertical-align:middle;padding:8px 0;"><?php echo __("adm_popup_title_field"); ?></th>
    <td style="padding:8px 0;">
        <input type="text" class="form-control" name="popup_title" id="edit_popup_title" style="width:100%;" placeholder="e.g. Server maintenance">
    </td>
</tr>
<tr>
    <th style="vertical-align:middle;padding:8px 0;"><?php echo __("adm_popup_period"); ?></th>
    <td style="padding:8px 0;">
        <div class="d-flex align-items-center flex-wrap" style="gap:10px;">
            <input type="date" class="form-control" name="popup_start_date" id="edit_popup_start_date" style="width:auto;max-width:160px;">
            <span>~</span>
            <input type="date" class="form-control" name="popup_end_date" id="edit_popup_end_date" style="width:auto;max-width:160px;">
        </div>
        <small class="text-muted">💡 <?php echo __('adm_popup_if_empty'); ?> <strong><?php echo __('adm_always'); ?></strong> <?php echo __('adm_popup_always_note'); ?></small>
    </td>
</tr>

<!-- Size settings -->
<tr><td colspan="2" style="padding:15px 0 5px 0;"><hr style="margin:0;"><h6 class="text-primary mt-2 mb-0"><strong><?php echo __("adm_popup_size_settings"); ?></strong></h6></td></tr>
<tr>
    <th style="vertical-align:middle;padding:8px 0;"><?php echo __("adm_th_width"); ?></th>
    <td style="padding:8px 0;">
        <input type="number" class="form-control" name="popup_width" id="edit_popup_width" min="200" max="800" style="width:100%;max-width:150px;" placeholder="e.g. 400">
    </td>
</tr>
<tr>
    <th style="vertical-align:middle;padding:8px 0;"><?php echo __("adm_th_height"); ?></th>
    <td style="padding:8px 0;">
        <input type="number" class="form-control" name="popup_height" id="edit_popup_height" min="100" max="800" style="width:100%;max-width:150px;" placeholder="e.g. 300">
        <small class="text-muted d-block"><?php echo __("adm_popup_size_help"); ?></small>
    </td>
</tr>

<!-- Image settings -->
<tr><td colspan="2" style="padding:15px 0 5px 0;"><hr style="margin:0;"><h6 class="text-primary mt-2 mb-0"><strong><?php echo __("adm_popup_img_settings"); ?></strong></h6></td></tr>
<tr>
    <th style="vertical-align:middle;padding:8px 0;"><?php echo __("adm_popup_img_url"); ?></th>
    <td style="padding:8px 0;">
        <input type="text" class="form-control" name="popup_image_url" id="edit_popup_image_url" style="width:100%;" placeholder="https://example.com/image.jpg">
    </td>
</tr>
<tr>
    <th style="vertical-align:top;padding:8px 0;"><?php echo __("adm_popup_img_upload"); ?></th>
    <td style="padding:8px 0;">
        <div class="custom-file" style="max-width:400px;">
            <input type="file" class="custom-file-input" id="edit_popup_image_file" name="popup_image_file" accept="image/*">
            <label class="custom-file-label" for="edit_popup_image_file" id="edit_popup_image_file_label"><?php echo __("adm_popup_file_select"); ?></label>
        </div>
        <small class="text-muted d-block mt-1"><?php echo __("adm_popup_upload_priority"); ?></small>
    </td>
</tr>
<tr id="currentImageSection" style="display:none;">
    <th style="vertical-align:top;padding:8px 0;"><?php echo __("adm_popup_current_img"); ?></th>
    <td style="padding:8px 0;">
        <img id="currentImagePreview" src="" style="max-width:200px;max-height:150px;border:1px solid #ddd;border-radius:4px;display:block;margin-bottom:8px;">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="edit_popup_delete_image" name="popup_delete_image">
            <label class="custom-control-label text-danger" for="edit_popup_delete_image"><?php echo __("adm_popup_delete_img"); ?></label>
        </div>
    </td>
</tr>

<!-- Text settings -->
<tr><td colspan="2" style="padding:15px 0 5px 0;"><hr style="margin:0;"><h6 class="text-primary mt-2 mb-0"><strong><?php echo __("adm_popup_text_settings"); ?></strong></h6></td></tr>
<tr>
    <th style="vertical-align:top;padding:8px 0;"><?php echo __("adm_popup_content_field"); ?></th>
    <td style="padding:8px 0;">
        <textarea class="form-control" name="popup_content" id="edit_popup_content" rows="4" style="width:100%;" placeholder="<?php echo __('adm_ph_popup_content'); ?>"></textarea>
    </td>
</tr>

<!-- Display settings -->
<tr><td colspan="2" style="padding:15px 0 5px 0;"><hr style="margin:0;"><h6 class="text-primary mt-2 mb-0"><strong><?php echo __("adm_section_display_settings"); ?></strong></h6></td></tr>
<tr>
    <th style="vertical-align:middle;padding:8px 0;"><?php echo __("adm_popup_display_mode"); ?></th>
    <td style="padding:8px 0;">
        <select class="form-control" name="popup_show_mode" id="edit_popup_show_mode" style="width:100%;max-width:250px;">
            <option value="both"><?php echo __("adm_popup_image_text"); ?></option>
            <option value="image"><?php echo __("adm_popup_image_only"); ?></option>
            <option value="text"><?php echo __("adm_popup_text_only"); ?></option>
        </select>
    </td>
</tr>
<tr>
    <th style="vertical-align:middle;padding:8px 0;"><?php echo __("adm_popup_header_color"); ?></th>
    <td style="padding:8px 0;">
        <input type="color" name="popup_bg_color" id="edit_popup_bg_color" value="#ffffff" style="width:80px;height:36px;padding:2px;border:1px solid #ccc;border-radius:4px;cursor:pointer;">
    </td>
</tr>
</table>

</div>
<div class="modal-footer py-2">
    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __("common_cancel"); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo __("adm_btn_save"); ?></button>
</div>
</form>
</div>
</div>
</div>

<script>
var popupsData = <?php echo json_encode($popups, JSON_UNESCAPED_UNICODE); ?>;

// 배너 미리보기
document.querySelector('input[name="banner_content"]').addEventListener('input', updateBannerPreview);
document.querySelector('input[name="banner_bg_color"]').addEventListener('input', updateBannerPreview);
document.querySelector('input[name="banner_text_color"]').addEventListener('input', updateBannerPreview);

function updateBannerPreview() {
    var content = document.querySelector('input[name="banner_content"]').value || '<?php echo __("adm_banner_preview_text"); ?>';
    var bgColor = document.querySelector('input[name="banner_bg_color"]').value;
    var textColor = document.querySelector('input[name="banner_text_color"]').value;
    var preview = document.getElementById('bannerPreview');
    preview.textContent = content;
    preview.style.background = bgColor;
    preview.style.color = textColor;
}

// 팝업 모달 열기
function openPopupModal(idx) {
    var isNew = (idx === -1);
    document.getElementById('popupModalTitle').textContent = isNew ? '<?php echo __("adm_popup_modal_add"); ?>' : '<?php echo __("adm_popup_modal_edit"); ?>';
    document.getElementById('popupEditIdx').value = idx;
    
    // 폼 초기화
    document.getElementById('edit_popup_enabled').checked = true;
    document.getElementById('edit_popup_order').value = popupsData.length + 1;
    document.getElementById('edit_popup_title').value = '';
    document.getElementById('edit_popup_start_date').value = '';
    document.getElementById('edit_popup_end_date').value = '';
    document.getElementById('edit_popup_width').value = '';
    document.getElementById('edit_popup_height').value = '';
    document.getElementById('edit_popup_image_url').value = '';
    document.getElementById('edit_popup_image_file').value = '';
    document.getElementById('edit_popup_image_file_label').textContent = '<?php echo __("adm_popup_file_select"); ?>';
    document.getElementById('edit_popup_content').value = '';
    document.getElementById('edit_popup_show_mode').value = 'both';
    document.getElementById('edit_popup_bg_color').value = '#ffffff';
    document.getElementById('edit_popup_delete_image').checked = false;
    document.getElementById('currentImageSection').style.display = 'none';
    
    if (!isNew && popupsData[idx]) {
        var p = popupsData[idx];
        document.getElementById('edit_popup_enabled').checked = p.enabled || false;
        document.getElementById('edit_popup_order').value = p.order || (idx + 1);
        document.getElementById('edit_popup_title').value = p.title || '';
        document.getElementById('edit_popup_start_date').value = p.start_date || '';
        document.getElementById('edit_popup_end_date').value = p.end_date || '';
        document.getElementById('edit_popup_width').value = p.width || '';
        document.getElementById('edit_popup_height').value = p.height || '';
        document.getElementById('edit_popup_image_url').value = p.image_url || '';
        document.getElementById('edit_popup_content').value = p.content || '';
        document.getElementById('edit_popup_show_mode').value = p.show_mode || 'both';
        document.getElementById('edit_popup_bg_color').value = p.bg_color || '#ffffff';
        
        // 현재 이미지 표시 (tr이므로 table-row)
        if (p.image_file) {
            document.getElementById('currentImageSection').style.display = 'table-row';
            document.getElementById('currentImagePreview').src = 'src/' + p.image_file + '?t=' + Date.now();
        }
    }
    
    $('#popupEditModal').modal('show');
}

// 파일 선택 시 라벨 업데이트
document.getElementById('edit_popup_image_file').addEventListener('change', function() {
    var label = document.getElementById('edit_popup_image_file_label');
    if (this.files && this.files[0]) {
        label.textContent = this.files[0].name;
    } else {
        label.textContent = '<?php echo __("adm_popup_file_select"); ?>';
    }
});
</script>

<!-- Users -->
<div class="tab-pane fade" id="group">
<div class="card m-2 p-0">
<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
<?php echo csrf_field(); ?>
<div class="card-header bg-success text-white"><?php echo __("adm_card_user_mgmt"); ?></div>
<ul class="list-group list-group-flush">
<?php
// ✅ users.json 사용 (순수 JSON)
$ua = load_users();

// 대기 중인 사용자 수 카운트
$pending_count = 0;
foreach($ua as $uid => $ud) {
    $status = $ud['status'] ?? 'active';
    if ($status === 'pending') $pending_count++;
}
if ($pending_count > 0): ?>
<li class="list-group-item bg-warning text-dark">
    <strong><?php echo __("adm_group_pending", $pending_count); ?></strong>
</li>
<?php endif;

foreach($ua as $uid=>$ud):
    $su=h($uid);
    $gr=$ud['group']??'group2';
    $user_email = $ud['email'] ?? '';
    $status = $ud['status'] ?? 'active'; // 기존 사용자는 active
    $created_at = $ud['created_at'] ?? '';
    $approved_at = $ud['approved_at'] ?? '';
    $is_admin = ($gr === 'admin');
    
    // 정지 정보
    $suspended_reason = $ud['suspended_reason'] ?? '';
    $suspended_from = $ud['suspended_from'] ?? '';
    $suspended_until = $ud['suspended_until'] ?? '';
    
    // 상태별 배지 색상
    $status_badge = '';
    $status_label = '';
    $status_title = '';
    switch($status) {
        case 'pending':
            $status_badge = 'badge-warning';
            $status_label = __('adm_status_pending');
            $status_title = $created_at ? __('adm_joined') . ': '.$created_at : '';
            break;
        case 'suspended':
            $status_badge = 'badge-danger';
            $status_label = __('adm_status_suspended');
            $status_title = '';
            if ($suspended_from) $status_title .= __('adm_suspended_from') . ': ' . $suspended_from;
            if ($suspended_until) $status_title .= ' / ' . __('adm_suspended_until') . ': ' . $suspended_until;
            else $status_title .= ' (' . __('adm_indefinite') . ')';
            if ($suspended_reason) $status_title .= ' / ' . __('adm_th_reason') . ': ' . $suspended_reason;
            break;
        default:
            $status_badge = 'badge-success';
            $status_label = __('adm_status_active');
            $status_title = '';
            if ($created_at) $status_title .= __('adm_joined') . ': '.$created_at;
            if ($approved_at) $status_title .= ($status_title ? ' / ' : '') . __('adm_approved') . ': '.$approved_at;
    }
?>
<li class="list-group-item">
    <div class="d-flex flex-wrap align-items-center gap-2" style="gap:8px;">
        <span class="badge badge-dark badge-item"><?php echo $su;?></span>
        <span class="badge <?php echo $status_badge; ?>" title="<?php echo h($status_title); ?>" style="cursor:help;"><?php echo $status_label; ?></span>
        
        <!-- Registration date display -->
        <?php if ($created_at): ?>
        <span class="text-muted" style="font-size:11px;" title="<?php echo __('adm_th_joined'); ?>">📅 <?php echo h(substr($created_at, 0, 10)); ?></span>
        <?php endif; ?>
        
        <!-- Email display and edit -->
        <span class="text-muted" style="font-size:12px;" title="<?php echo h($user_email ?: __('adm_no_email')); ?>">
            <?php if ($user_email): ?>
            📧 <?php echo h(mb_strlen($user_email) > 20 ? mb_substr($user_email, 0, 20) . '...' : $user_email); ?>
            <?php else: ?>
            <span class="text-warning"><?php echo __("adm_no_email_badge"); ?></span>
            <?php endif; ?>
        </span>
        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="openEmailModal('<?php echo $su; ?>', '<?php echo h($user_email); ?>')" title="<?php echo __('adm_heading_email_edit'); ?>" style="font-size:11px;">✏️</button>
        
        <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="<?php echo $su;?>_group" value="admin" <?php if($gr=="admin")echo"checked";?>><label class="form-check-label">admin</label></div>
        <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="<?php echo $su;?>_group" value="group1" <?php if($gr=="group1")echo"checked";?>><label class="form-check-label">group1</label></div>
        <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="<?php echo $su;?>_group" value="group2" <?php if($gr=="group2")echo"checked";?>><label class="form-check-label">group2</label></div>
        <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="<?php echo $su;?>_group" value="group3" <?php if($gr=="group3")echo"checked";?>><label class="form-check-label">group3</label></div>
        <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="<?php echo $su;?>_group" value="group4" <?php if($gr=="group4")echo"checked";?>><label class="form-check-label">group4</label></div>
        <?php if(!$is_admin): ?>
        <div class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="<?php echo $su;?>_group" value="delete"><label class="form-check-label text-danger"><?php echo __("adm_deletion"); ?></label></div>
        <?php endif; ?>
        
        <?php if($status === 'pending'): ?>
        <button type="button" class="btn btn-sm btn-success ml-auto" onclick="changeUserStatus('<?php echo $su; ?>', 'active')" title="<?php echo __('adm_btn_approve'); ?>"><?php echo __('adm_btn_approve_short'); ?></button>
        <?php elseif($status === 'active' && !$is_admin): ?>
        <button type="button" class="btn btn-sm btn-outline-warning ml-auto" onclick="changeUserStatus('<?php echo $su; ?>', 'pending')" title="<?php echo __('adm_btn_set_pending'); ?>"><?php echo __('adm_btn_pending_short'); ?></button>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="openSuspendModal('<?php echo $su; ?>')" title="<?php echo __('adm_btn_suspend'); ?>"><?php echo __('adm_btn_suspend_short'); ?></button>
        <?php elseif($status === 'suspended'): ?>
        <button type="button" class="btn btn-sm btn-outline-success ml-auto" onclick="changeUserStatus('<?php echo $su; ?>', 'active')" title="<?php echo __('adm_btn_activate'); ?>"><?php echo __('adm_btn_activate_short'); ?></button>
        <button type="button" class="btn btn-sm btn-outline-warning" onclick="changeUserStatus('<?php echo $su; ?>', 'pending')" title="<?php echo __('adm_btn_set_pending'); ?>"><?php echo __('adm_btn_pending_short'); ?></button>
        <?php endif; ?>
        <?php if(!$is_admin): ?>
        <button type="button" class="btn btn-sm btn-outline-info" onclick="openPasswordModal('<?php echo $su; ?>')" title="<?php echo __('adm_heading_pw_change'); ?>">🔑</button>
        <?php endif; ?>
    </div>
</li>
<?php endforeach;?>
<li class="list-group-item p-0"><input type="hidden" name="mode" value="group_change"><button class="btn btn-success btn-block btn-sm" type="submit"><?php echo __("adm_btn_edit_usergroup"); ?></button></li>
</ul>
</form>

<!-- Hidden form for status change -->
<form id="statusChangeForm" action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post" style="display:none;">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="status_change">
<input type="hidden" name="target_user" id="status_target_user" value="">
<input type="hidden" name="new_status" id="status_new_status" value="">
<input type="hidden" name="suspend_reason" id="status_suspend_reason" value="">
<input type="hidden" name="suspend_from" id="status_suspend_from" value="">
<input type="hidden" name="suspend_until" id="status_suspend_until" value="">
</form>

<!-- Hidden form for password change -->
<form id="changePasswordForm" action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post" style="display:none;">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="change_password">
<input type="hidden" name="target_user" id="password_target_user" value="">
<input type="hidden" name="new_password" id="password_new_password" value="">
</form>

<!-- Password change modal -->
<div id="passwordModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:center;">
<div style="background:#fff;border-radius:10px;padding:25px;max-width:400px;width:90%;box-shadow:0 10px 30px rgba(0,0,0,0.3);">
    <h5 style="margin:0 0 20px;color:#007bff;"><?php echo __("adm_heading_pw_change"); ?></h5>
    <p style="margin:0 0 15px;"><strong id="passwordModalUser"></strong> <?php echo __("adm_modal_pw_desc"); ?></p>
    
    <div style="margin-bottom:15px;">
        <label style="display:block;margin-bottom:5px;font-weight:500;"><?php echo __("adm_modal_new_pw"); ?></label>
        <input type="password" id="modalNewPassword" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:5px;font-size:14px;" placeholder="<?php echo __('adm_ph_min_8'); ?>" minlength="8">
    </div>
    
    <div style="margin-bottom:20px;">
        <label style="display:block;margin-bottom:5px;font-weight:500;"><?php echo __("adm_label_password_confirm"); ?></label>
        <input type="password" id="modalConfirmPassword" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:5px;font-size:14px;" placeholder="<?php echo __('adm_ph_reenter_pw'); ?>">
    </div>
    
    <div id="passwordModalError" style="display:none;background:#f8d7da;color:#721c24;padding:10px;border-radius:5px;margin-bottom:15px;font-size:13px;"></div>
    
    <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closePasswordModal()" style="padding:8px 20px;border:1px solid #ddd;background:#fff;border-radius:5px;cursor:pointer;"><?php echo __("common_cancel"); ?></button>
        <button type="button" onclick="confirmPasswordChange()" style="padding:8px 20px;border:none;background:#007bff;color:#fff;border-radius:5px;cursor:pointer;"><?php echo __("adm_btn_change"); ?></button>
    </div>
</div>
</div>

<!-- Hidden form for email edit -->
<form id="emailChangeForm" action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post" style="display:none;">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="change_email">
<input type="hidden" name="target_user" id="email_target_user" value="">
<input type="hidden" name="new_email" id="email_new_email" value="">
</form>

<!-- Email edit modal -->
<div id="emailModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:center;">
<div style="background:#fff;border-radius:10px;padding:25px;max-width:400px;width:90%;box-shadow:0 10px 30px rgba(0,0,0,0.3);">
    <h5 style="margin:0 0 20px;color:#17a2b8;"><?php echo __("adm_heading_email_edit"); ?></h5>
    <p style="margin:0 0 15px;"><strong id="emailModalUser"></strong> <?php echo __("adm_modal_email_desc"); ?></p>
    
    <div style="margin-bottom:20px;">
        <label style="display:block;margin-bottom:5px;font-weight:500;"><?php echo __("adm_modal_email_addr"); ?></label>
        <input type="email" id="modalNewEmail" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:5px;font-size:14px;" placeholder="example@email.com">
    </div>
    
    <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closeEmailModal()" style="padding:8px 20px;border:1px solid #ddd;background:#fff;border-radius:5px;cursor:pointer;"><?php echo __("common_cancel"); ?></button>
        <button type="button" onclick="confirmEmailChange()" style="padding:8px 20px;border:none;background:#17a2b8;color:#fff;border-radius:5px;cursor:pointer;"><?php echo __("adm_btn_save_short"); ?></button>
    </div>
</div>
</div>

<!-- Suspend modal -->
<div id="suspendModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:center;">
<div style="background:#fff;border-radius:10px;padding:25px;max-width:400px;width:90%;box-shadow:0 10px 30px rgba(0,0,0,0.3);">
    <h5 style="margin:0 0 20px;color:#dc3545;"><?php echo __("adm_heading_user_suspend"); ?></h5>
    <p style="margin:0 0 15px;"><strong id="suspendModalUser"></strong> <?php echo __("adm_modal_suspend_desc"); ?></p>
    
    <div style="margin-bottom:15px;">
        <label style="display:block;margin-bottom:5px;font-weight:500;"><?php echo __("adm_modal_suspend_reason"); ?></label>
        <input type="text" id="modalSuspendReason" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:5px;" placeholder="<?php echo __('adm_ph_suspend_reason'); ?>" maxlength="200">
    </div>
    
    <div style="display:flex;gap:10px;margin-bottom:15px;">
        <div style="flex:1;">
            <label style="display:block;margin-bottom:5px;font-weight:500;"><?php echo __("adm_modal_start_date"); ?></label>
            <input type="date" id="modalSuspendFrom" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:5px;">
        </div>
        <div style="flex:1;">
            <label style="display:block;margin-bottom:5px;font-weight:500;"><?php echo __('adm_modal_end_date'); ?> (<?php echo __('adm_sys_optional'); ?>)</label>
            <input type="date" id="modalSuspendUntil" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:5px;">
        </div>
    </div>
    <p style="font-size:12px;color:#666;margin:0 0 20px;"><?php echo __("adm_modal_indefinite_note"); ?></p>
    
    <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closeSuspendModal()" style="padding:8px 20px;border:1px solid #ddd;background:#fff;border-radius:5px;cursor:pointer;"><?php echo __("common_cancel"); ?></button>
        <button type="button" onclick="confirmSuspend()" style="padding:8px 20px;border:none;background:#dc3545;color:#fff;border-radius:5px;cursor:pointer;"><?php echo __("adm_btn_suspend"); ?></button>
    </div>
</div>
</div>

<script>
var currentSuspendUser = '';

function openSuspendModal(username) {
    currentSuspendUser = username;
    document.getElementById('suspendModalUser').textContent = username;
    document.getElementById('modalSuspendReason').value = '';
    document.getElementById('modalSuspendFrom').value = new Date().toISOString().split('T')[0];
    document.getElementById('modalSuspendUntil').value = '';
    document.getElementById('suspendModal').style.display = 'flex';
}

function closeSuspendModal() {
    document.getElementById('suspendModal').style.display = 'none';
    currentSuspendUser = '';
}

function confirmSuspend() {
    if (!currentSuspendUser) return;
    
    document.getElementById('status_target_user').value = currentSuspendUser;
    document.getElementById('status_new_status').value = 'suspended';
    document.getElementById('status_suspend_reason').value = document.getElementById('modalSuspendReason').value;
    document.getElementById('status_suspend_from').value = document.getElementById('modalSuspendFrom').value;
    document.getElementById('status_suspend_until').value = document.getElementById('modalSuspendUntil').value;
    document.getElementById('statusChangeForm').submit();
}

function changeUserStatus(username, newStatus) {
    var statusLabels = {'active': '<?php echo __("adm_status_active"); ?>', 'suspended': '<?php echo __('adm_status_suspended'); ?>', 'pending': '<?php echo __('adm_status_pending'); ?>'};
    if (confirm(username + ' ' + i18n_adm.confirm_status_change + ' ' + (statusLabels[newStatus] || newStatus) + '?')) {
        document.getElementById('status_target_user').value = username;
        document.getElementById('status_new_status').value = newStatus;
        document.getElementById('status_suspend_reason').value = '';
        document.getElementById('status_suspend_from').value = '';
        document.getElementById('status_suspend_until').value = '';
        document.getElementById('statusChangeForm').submit();
    }
}

// 비밀번호 변경 모달
var currentPasswordUser = '';

function openPasswordModal(username) {
    currentPasswordUser = username;
    document.getElementById('passwordModalUser').textContent = username;
    document.getElementById('modalNewPassword').value = '';
    document.getElementById('modalConfirmPassword').value = '';
    document.getElementById('passwordModalError').style.display = 'none';
    document.getElementById('passwordModal').style.display = 'flex';
    document.getElementById('modalNewPassword').focus();
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    currentPasswordUser = '';
}

function confirmPasswordChange() {
    var newPass = document.getElementById('modalNewPassword').value;
    var confirmPass = document.getElementById('modalConfirmPassword').value;
    var errorEl = document.getElementById('passwordModalError');
    
    // 유효성 검사
    if (newPass.length < 8) {
        errorEl.textContent = '<?php echo __("adm_err_pw_length"); ?>';
        errorEl.style.display = 'block';
        return;
    }
    
    if (newPass !== confirmPass) {
        errorEl.textContent = '<?php echo __("adm_err_pw_mismatch"); ?>';
        errorEl.style.display = 'block';
        return;
    }
    
    document.getElementById('password_target_user').value = currentPasswordUser;
    document.getElementById('password_new_password').value = newPass;
    document.getElementById('changePasswordForm').submit();
}

// 이메일 수정 모달
var currentEmailUser = '';

function openEmailModal(username, currentEmail) {
    currentEmailUser = username;
    document.getElementById('emailModalUser').textContent = username;
    document.getElementById('modalNewEmail').value = currentEmail || '';
    document.getElementById('emailModal').style.display = 'flex';
    document.getElementById('modalNewEmail').focus();
}

function closeEmailModal() {
    document.getElementById('emailModal').style.display = 'none';
    currentEmailUser = '';
}

function confirmEmailChange() {
    var newEmail = document.getElementById('modalNewEmail').value.trim();
    
    // 이메일 형식 검사 (빈 값은 허용 - 삭제 용도)
    if (newEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
        alert('<?php echo __("adm_err_email_format"); ?>');
        return;
    }
    
    document.getElementById('email_target_user').value = currentEmailUser;
    document.getElementById('email_new_email').value = newEmail;
    document.getElementById('emailChangeForm').submit();
}
</script>
</div></div>


<!-- Folders -->
<div class="tab-pane fade" id="folder">
<?php 
// 모든 base_dirs에 대해 폴더 권한 관리
foreach ($base_dirs as $dir_idx => $dir_path):
    $dir_name = basename($dir_path);
?>
<div class="card m-2 p-0">
<form action="<?php echo h($_SERVER['PHP_SELF']) . '?bidx=' . $dir_idx; ?>" method="post" id="folderForm_<?php echo $dir_idx; ?>">
<?php echo csrf_field(); ?>
<div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap">
    <span><?php echo __("adm_folder_perm_title"); ?> - <?php echo h($dir_name); ?></span>
    <div class="btn-group btn-group-sm flex-wrap">
        <button type="button" class="btn btn-light btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'group1', true)" title="group1 <?php echo __('adm_btn_select_all'); ?>">G1✓</button>
        <button type="button" class="btn btn-outline-light btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'group1', false)" title="group1 <?php echo __('adm_btn_deselect_all'); ?>">G1✗</button>
        <button type="button" class="btn btn-light btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'group2', true)" title="group2 <?php echo __('adm_btn_select_all'); ?>">G2✓</button>
        <button type="button" class="btn btn-outline-light btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'group2', false)" title="group2 <?php echo __('adm_btn_deselect_all'); ?>">G2✗</button>
        <button type="button" class="btn btn-light btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'group3', true)" title="group3 <?php echo __('adm_btn_select_all'); ?>">G3✓</button>
        <button type="button" class="btn btn-outline-light btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'group3', false)" title="group3 <?php echo __('adm_btn_deselect_all'); ?>">G3✗</button>
        <button type="button" class="btn btn-light btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'group4', true)" title="group4 <?php echo __('adm_btn_select_all'); ?>">G4✓</button>
        <button type="button" class="btn btn-outline-light btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'group4', false)" title="group4 <?php echo __('adm_btn_deselect_all'); ?>">G4✗</button>
        <button type="button" class="btn btn-warning btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'all', true)" title="<?php echo __('adm_btn_select_all'); ?>"><?php echo __("adm_btn_all_check"); ?></button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAllFolder(<?php echo $dir_idx; ?>, 'all', false)" title="<?php echo __('adm_btn_deselect_all'); ?>"><?php echo __("adm_btn_all_uncheck"); ?></button>
    </div>
</div>
<ul class="list-group list-group-flush">
<?php
$ap=load_permissions($permissions_file);$dl=[];
if (is_dir($dir_path)) {
    foreach(new DirectoryIterator($dir_path) as $fi){
        if($fi->isDot()||$fi->getFilename()=="@eaDir"||!$fi->isDir())continue;
        $fn=$fi->getFilename();$pm=$ap[$fn]??[];
        $dl[]=['n'=>$fn,'g1'=>$pm['group1']??1,'g2'=>$pm['group2']??1,'g3'=>$pm['group3']??1,'g4'=>$pm['group4']??1];
    }
}
usort($dl,function($a,$b){return strnatcasecmp($a['n'],$b['n']);});
if (count($dl) > 0):
foreach($dl as $d):$sn=h($d['n']);$en=encode_url($d['n']);?>
<li class="list-group-item">
<span class="badge badge-dark badge-item mr-2"><?php echo $sn;?></span>
<div class="form-check form-check-inline"><input class="form-check-input folder-chk-<?php echo $dir_idx; ?>-group1" type="checkbox" name="<?php echo h($en);?>_group1" value="1" <?php if($d['g1']==1)echo"checked";?>><label class="form-check-label">group1</label></div>
<div class="form-check form-check-inline"><input class="form-check-input folder-chk-<?php echo $dir_idx; ?>-group2" type="checkbox" name="<?php echo h($en);?>_group2" value="1" <?php if($d['g2']==1)echo"checked";?>><label class="form-check-label">group2</label></div>
<div class="form-check form-check-inline"><input class="form-check-input folder-chk-<?php echo $dir_idx; ?>-group3" type="checkbox" name="<?php echo h($en);?>_group3" value="1" <?php if($d['g3']==1)echo"checked";?>><label class="form-check-label">group3</label></div>
<div class="form-check form-check-inline"><input class="form-check-input folder-chk-<?php echo $dir_idx; ?>-group4" type="checkbox" name="<?php echo h($en);?>_group4" value="1" <?php if($d['g4']==1)echo"checked";?>><label class="form-check-label">group4</label></div>
</li>
<?php endforeach;
else: ?>
<li class="list-group-item text-muted"><?php echo __("adm_no_folders"); ?></li>
<?php endif; ?>
<li class="list-group-item p-0">
<input type="hidden" name="mode" value="mode_change">
<input type="hidden" name="target_bidx" value="<?php echo $dir_idx; ?>">
<button class="btn btn-success btn-block btn-sm" type="submit"><?php echo __("adm_btn_edit_folder_perm"); ?></button>
</li>
</ul>
</form></div>
<?php endforeach; ?>

<script>
function toggleAllFolder(dirIdx, type, checked) {
    if (type === 'all') {
        ['group1', 'group2', 'group3', 'group4'].forEach(function(t) {
            document.querySelectorAll('.folder-chk-' + dirIdx + '-' + t).forEach(function(chk) {
                chk.checked = checked;
            });
        });
    } else {
        document.querySelectorAll('.folder-chk-' + dirIdx + '-' + type).forEach(function(chk) {
            chk.checked = checked;
        });
    }
}
</script>
</div>

<!-- Logs -->
<div class="tab-pane fade" id="logs">
<div class="container-fluid">

<!-- Login log management -->
<div class="info-card" id="login-log">
<div class="card-header" style="background:#34495e;color:#fff;"><?php echo __("adm_card_login_log"); ?></div>
<div class="card-body p-3">
<?php
$log_file = __DIR__ . '/src/login_log.json';
$login_logs = [];
if (file_exists($log_file)) {
    $login_logs = json_decode(file_get_contents($log_file), true) ?? [];
}
$total_logs = count($login_logs);
// 최신순 정렬 (역순)
$login_logs = array_reverse($login_logs, true);
// 페이지네이션
$logs_per_page = 20;
$current_page = max(1, (int)($_GET['log_page'] ?? 1));
$total_pages = max(1, ceil($total_logs / $logs_per_page));
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $logs_per_page;
$paged_logs = array_slice($login_logs, $offset, $logs_per_page, true);
?>

<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <strong><?php echo __("adm_total"); ?> <?php echo number_format($total_logs); ?></strong> <?php echo __('adm_login_records'); ?>
    </div>
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteOldLogs()"><?php echo __("adm_btn_delete_old_logs"); ?></button>
        <button type="button" class="btn btn-sm btn-danger" onclick="deleteAllLogs()"><?php echo __("adm_btn_delete_all"); ?></button>
    </div>
</div>

<?php if ($total_logs > 0): ?>
<form method="post" id="logDeleteForm" action="<?php echo h($_SERVER['PHP_SELF']); ?>">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="delete_login_log">
<input type="hidden" name="delete_type" id="delete_type" value="">
<input type="hidden" name="days_old" id="days_old" value="">

<div class="table-responsive">
<style>
/* 모바일에서 IP, 사용자, 일시 열 터치/선택 방지 */
@media (max-width: 767px) {
    .login-log-table td.no-touch {
        user-select: none;
        -webkit-user-select: none;
        pointer-events: none;
    }
}
</style>
<table class="table table-sm table-hover login-log-table" style="font-size:13px; min-width:650px;">
<thead class="thead-light">
<tr>
    <th width="30"><input type="checkbox" id="selectAllLogs" onclick="toggleAllLogs(this)"></th>
    <th style="white-space:nowrap;"><?php echo __("adm_th_datetime"); ?></th>
    <th style="white-space:nowrap;"><?php echo __("adm_th_user"); ?></th>
    <th style="white-space:nowrap;"><?php echo __("adm_th_ip"); ?></th>
    <th style="white-space:nowrap;"><?php echo __("adm_th_country"); ?></th>
    <th class="d-none d-md-table-cell">User Agent</th>
    <th class="d-table-cell d-md-none" style="width:40px;"><?php echo __("adm_th_device"); ?></th>
</tr>
</thead>
<tbody>
<?php 
$display_index = $offset;
foreach ($paged_logs as $orig_idx => $log): 
    $real_idx = $total_logs - 1 - $display_index; // 역순 인덱스 계산
    $ua = $log['user_agent'] ?? '';
    $is_mobile = preg_match('/iPhone|iPad|iPod|Android|Mobile/i', $ua);
    $device_icon = $is_mobile ? '📱' : '💻';
    $ua_escaped = htmlspecialchars($ua, ENT_QUOTES, 'UTF-8');
    $country_code = $log['country'] ?? '';
    $country_name = isset(IPBlocker::$countries[$country_code]) ? IPBlocker::$countries[$country_code] : $country_code;
?>
<tr>
    <td><input type="checkbox" name="selected_logs[]" value="<?php echo $real_idx; ?>" class="log-checkbox"></td>
    <td class="no-touch" style="white-space:nowrap;font-size:12px;"><?php echo h($log['datetime'] ?? '-'); ?></td>
    <td class="no-touch"><strong><?php echo h($log['user_id'] ?? '-'); ?></strong></td>
    <td class="no-touch" style="white-space:nowrap;"><code style="font-size:11px;"><?php echo h($log['ip'] ?? '-'); ?></code></td>
    <td class="no-touch" style="white-space:nowrap;"><span class="badge badge-secondary"><?php echo h($country_code); ?></span><?php if($country_name && $country_name !== $country_code): ?><small class="text-muted ml-1"><?php echo h($country_name); ?></small><?php endif; ?></td>
    <td class="d-none d-md-table-cell"><small class="text-muted" style="word-break:break-all;"><?php echo h($ua); ?></small></td>
    <td class="d-table-cell d-md-none text-center"><span style="cursor:pointer;" onclick="alert('<?php echo addslashes($ua_escaped); ?>')"><?php echo $device_icon; ?></span></td>
</tr>
<?php 
$display_index++;
endforeach; 
?>
</tbody>
</table>
</div>

<div class="d-flex justify-content-between align-items-center mt-2 flex-wrap">
    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSelectedLogs()"><?php echo __("adm_btn_delete_selected"); ?></button>
    
    <?php if ($total_pages > 1): ?>
    <nav class="mx-auto my-2">
        <ul class="pagination pagination-sm mb-0 justify-content-center">
            <?php if ($current_page > 1): ?>
            <li class="page-item"><a class="page-link" href="?log_page=1&tab=logs">«</a></li>
            <li class="page-item"><a class="page-link" href="?log_page=<?php echo $current_page - 1; ?>&tab=logs">‹</a></li>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);
            for ($p = $start_page; $p <= $end_page; $p++):
            ?>
            <li class="page-item <?php echo $p == $current_page ? 'active' : ''; ?>">
                <a class="page-link" href="?log_page=<?php echo $p; ?>&tab=logs"><?php echo $p; ?></a>
            </li>
            <?php endfor; ?>
            
            <?php if ($current_page < $total_pages): ?>
            <li class="page-item"><a class="page-link" href="?log_page=<?php echo $current_page + 1; ?>&tab=logs">›</a></li>
            <li class="page-item"><a class="page-link" href="?log_page=<?php echo $total_pages; ?>&tab=logs">»</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <div></div>
    <?php endif; ?>
</div>
</form>

<script>
function toggleAllLogs(checkbox) {
    document.querySelectorAll('.log-checkbox').forEach(cb => cb.checked = checkbox.checked);
}

function deleteSelectedLogs() {
    const selected = document.querySelectorAll('.log-checkbox:checked');
    if (selected.length === 0) {
        alert(i18n_adm.select_logs || '');
        return;
    }
    if (confirm(selected.length + ' ' + (i18n_adm.confirm_delete_n_logs || ''))) {
        document.getElementById('delete_type').value = 'selected';
        document.getElementById('logDeleteForm').submit();
    }
}

function deleteAllLogs() {
    if (confirm(i18n_adm.confirm_delete_all_logs)) {
        document.getElementById('delete_type').value = 'all';
        document.getElementById('logDeleteForm').submit();
    }
}

function deleteOldLogs() {
    const days = prompt(i18n_adm.prompt_days_delete || 'Days?', '30');
    if (days && !isNaN(days) && parseInt(days) > 0) {
        if (confirm(days + ' ' + (i18n_adm.confirm_delete_old || ''))) {
            document.getElementById('delete_type').value = 'old';
            document.getElementById('days_old').value = days;
            document.getElementById('logDeleteForm').submit();
        }
    }
}
</script>

<?php else: ?>
<div class="text-center text-muted py-4">
    <p><?php echo __("adm_no_login_records"); ?></p>
</div>
<?php endif; ?>

</div>
</div>

<!-- Activity log management -->
<div class="info-card mt-3" id="activity-log">
<div class="card-header" style="background:#27ae60;color:#fff;"><?php echo __("adm_card_activity_log"); ?></div>
<div class="card-body p-3">
<?php
$activity_log_path = __DIR__ . '/src/activity_log.json';
$activity_logs = [];
if (file_exists($activity_log_path)) {
    $activity_logs = json_decode(file_get_contents($activity_log_path), true) ?? [];
}

// 필터 파라미터
$filter_action = $_GET['filter_action'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';
$filter_from = $_GET['filter_from'] ?? '';
$filter_to = $_GET['filter_to'] ?? '';

// 활동 유형별 아이콘/색상 (필터 옵션으로도 사용)
$action_styles = [
    __('adm_file_login') => ['icon' => '🔑', 'color' => '#28a745', 'label' => __('adm_act_login')],
    '로그아웃' => ['icon' => '🚪', 'color' => '#6c757d', 'label' => __('adm_act_logout')],
    '회원가입' => ['icon' => '📝', 'color' => '#20c997', 'label' => __('adm_act_register')],
    '폴더 접근' => ['icon' => '📂', 'color' => '#17a2b8', 'label' => __('adm_act_folder')],
    '검색' => ['icon' => '🔍', 'color' => '#6610f2', 'label' => __('adm_act_search')],
    '열람' => ['icon' => '📖', 'color' => '#28a745', 'label' => __('adm_act_view')],
    '다운로드' => ['icon' => '⬇️', 'color' => '#007bff', 'label' => __('adm_act_download')],
    '해킹시도' => ['icon' => '🚨', 'color' => '#dc3545', 'label' => __('adm_act_hack')],
];

// 사용자 목록은 로그에서 추출
$unique_users = [];
foreach ($activity_logs as $log) {
    $user = $log['user_id'] ?? '';
    if (!empty($user) && !in_array($user, $unique_users)) {
        $unique_users[] = $user;
    }
}
sort($unique_users);

// 필터 적용
$filtered_logs = $activity_logs;
if (!empty($filter_action)) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($filter_action) {
        return ($log['action'] ?? '') === $filter_action;
    });
}
if (!empty($filter_user)) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($filter_user) {
        return ($log['user_id'] ?? '') === $filter_user;
    });
}
// 기간 필터 (날짜 부분만 비교 - YYYY-MM-DD)
if (!empty($filter_from)) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($filter_from) {
        $log_date = substr($log['datetime'] ?? '', 0, 10);
        return $log_date >= $filter_from;
    });
}
if (!empty($filter_to)) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($filter_to) {
        $log_date = substr($log['datetime'] ?? '', 0, 10);
        return $log_date <= $filter_to;
    });
}

$total_activity_logs = count($activity_logs);
$filtered_count = count($filtered_logs);

// 최신순 정렬 (역순)
$filtered_logs = array_reverse($filtered_logs, true);

// 페이지네이션
$activity_per_page = 30;
$activity_page = max(1, (int)($_GET['activity_page'] ?? 1));
$activity_total_pages = max(1, ceil($filtered_count / $activity_per_page));
$activity_page = min($activity_page, $activity_total_pages);
$activity_offset = ($activity_page - 1) * $activity_per_page;
$paged_activity = array_slice($filtered_logs, $activity_offset, $activity_per_page, true);

// 필터 쿼리스트링 생성
$filter_query = '';
if (!empty($filter_action)) $filter_query .= '&filter_action=' . urlencode($filter_action);
if (!empty($filter_user)) $filter_query .= '&filter_user=' . urlencode($filter_user);
if (!empty($filter_from)) $filter_query .= '&filter_from=' . urlencode($filter_from);
if (!empty($filter_to)) $filter_query .= '&filter_to=' . urlencode($filter_to);

// 필터 활성화 여부
$has_filter = !empty($filter_action) || !empty($filter_user) || !empty($filter_from) || !empty($filter_to);
?>

<div class="mb-3">
    <!-- Row 1: Basic info + delete buttons -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:10px;">
        <div>
            <strong><?php echo __("adm_total"); ?> <?php echo number_format($total_activity_logs); ?></strong>
            <?php if ($filtered_count !== $total_activity_logs): ?>
            <span class="text-muted">(<?php echo __('adm_filtered'); ?>: <?php echo number_format($filtered_count); ?>)</span>
            <?php endif; ?>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteOldActivityLogs()"><?php echo __("adm_btn_delete_old_logs"); ?></button>
            <button type="button" class="btn btn-sm btn-danger" onclick="deleteAllActivityLogs()"><?php echo __("adm_btn_delete_all"); ?></button>
        </div>
    </div>
    
    <!-- Row 2: Filters -->
    <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
        <select id="filterAction" class="form-control form-control-sm" style="width:auto;min-width:120px;" onchange="applyActivityFilter()">
            <option value=""><?php echo __("adm_opt_all_activities"); ?></option>
            <?php foreach ($action_styles as $act => $style): ?>
            <option value="<?php echo h($act); ?>" <?php echo $filter_action === $act ? 'selected' : ''; ?> style="color:<?php echo $style['color']; ?>"><?php echo $style['icon']; ?> <?php echo h($style['label']); ?></option>
            <?php endforeach; ?>
        </select>
        
        <select id="filterUser" class="form-control form-control-sm" style="width:auto;min-width:100px;" onchange="applyActivityFilter()">
            <option value=""><?php echo __("adm_opt_all_users"); ?></option>
            <?php foreach ($unique_users as $usr): ?>
            <option value="<?php echo h($usr); ?>" <?php echo $filter_user === $usr ? 'selected' : ''; ?>><?php echo h($usr); ?></option>
            <?php endforeach; ?>
        </select>
        
        <div class="d-flex align-items-center" style="gap:4px;">
            <input type="date" id="filterFrom" class="form-control form-control-sm" style="width:auto;" value="<?php echo h($filter_from); ?>" onchange="applyActivityFilter()" title="Start">
            <span class="text-muted">~</span>
            <input type="date" id="filterTo" class="form-control form-control-sm" style="width:auto;" value="<?php echo h($filter_to); ?>" onchange="applyActivityFilter()" title="End">
        </div>
        
        <!-- Quick date range buttons -->
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('today')" title="Today"><?php echo __("adm_btn_today"); ?></button>
            <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('week')" title="7 days"><?php echo __("adm_btn_7days"); ?></button>
            <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('month')" title="30 days"><?php echo __("adm_btn_30days"); ?></button>
        </div>
        
        <?php if ($has_filter): ?>
        <a href="?#logs" class="btn btn-sm btn-outline-secondary"><?php echo __("adm_btn_reset"); ?></a>
        <?php endif; ?>
    </div>
</div>

<script>
function applyActivityFilter() {
    var action = document.getElementById('filterAction').value;
    var user = document.getElementById('filterUser').value;
    var from = document.getElementById('filterFrom').value;
    var to = document.getElementById('filterTo').value;
    var url = '?';
    if (action) url += 'filter_action=' + encodeURIComponent(action) + '&';
    if (user) url += 'filter_user=' + encodeURIComponent(user) + '&';
    if (from) url += 'filter_from=' + encodeURIComponent(from) + '&';
    if (to) url += 'filter_to=' + encodeURIComponent(to) + '&';
    url += '#logs';
    window.location.href = url;
}

function setDateRange(range) {
    var today = new Date();
    var from = new Date();
    
    if (range === 'today') {
        // 오늘
    } else if (range === 'week') {
        from.setDate(today.getDate() - 6);
    } else if (range === 'month') {
        from.setDate(today.getDate() - 29);
    }
    
    // 로컬 시간 기준으로 YYYY-MM-DD 형식 생성
    function formatDate(d) {
        var year = d.getFullYear();
        var month = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    document.getElementById('filterFrom').value = formatDate(from);
    document.getElementById('filterTo').value = formatDate(today);
    applyActivityFilter();
}
</script>

<?php if ($total_activity_logs > 0): ?>
<form method="post" id="activityLogDeleteForm" action="<?php echo h($_SERVER['PHP_SELF']); ?>">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="delete_activity_log">
<input type="hidden" name="delete_type" id="activity_delete_type" value="">
<input type="hidden" name="days_old" id="activity_days_old" value="">
</form>

<div class="table-responsive">
<table class="table table-sm table-hover" style="font-size:13px; min-width:600px;">
<thead class="thead-light">
<tr>
    <th style="white-space:nowrap;"><?php echo __("adm_th_datetime"); ?></th>
    <th style="white-space:nowrap;"><?php echo __("adm_th_user"); ?></th>
    <th style="white-space:nowrap;"><?php echo __('adm_th_activity'); ?></th>
    <th style="white-space:nowrap;"><?php echo __("adm_th_detail"); ?></th>
    <th style="white-space:nowrap;"><?php echo __("adm_th_ip"); ?></th>
</tr>
</thead>
<tbody>
<?php foreach ($paged_activity as $alog): 
    $action = $alog['action'] ?? '';
    $style = $action_styles[$action] ?? ['icon' => '📌', 'color' => '#6c757d', 'label' => $action];
    $log_user = $alog['user_id'] ?? '';
?>
<tr>
    <td class="text-nowrap"><?php echo h($alog['datetime'] ?? ''); ?></td>
    <td><a href="?filter_user=<?php echo urlencode($log_user); ?>#logs" class="badge badge-secondary" style="text-decoration:none;"><?php echo h($log_user); ?></a></td>
    <td class="text-nowrap">
        <a href="?filter_action=<?php echo urlencode($action); ?>#logs" style="text-decoration:none;color:<?php echo $style['color']; ?>">
            <?php echo $style['icon']; ?> <?php echo h($style['label'] ?? $action); ?>
        </a>
    </td>
    <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo h($alog['detail'] ?? ''); ?>">
        <?php echo h($alog['detail'] ?? '-'); ?>
    </td>
    <td class="text-nowrap text-muted"><?php echo h($alog['ip'] ?? ''); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php if ($activity_total_pages > 1): ?>
<nav aria-label="Activity log pagination">
<ul class="pagination pagination-sm justify-content-center mb-0">
    <?php if ($activity_page > 1): ?>
    <li class="page-item"><a class="page-link" href="?activity_page=1<?php echo $filter_query; ?>#logs">«</a></li>
    <li class="page-item"><a class="page-link" href="?activity_page=<?php echo $activity_page - 1; ?><?php echo $filter_query; ?>#logs">‹</a></li>
    <?php endif; ?>
    
    <?php
    $start_page = max(1, $activity_page - 2);
    $end_page = min($activity_total_pages, $activity_page + 2);
    for ($p = $start_page; $p <= $end_page; $p++): ?>
    <li class="page-item <?php echo $p == $activity_page ? 'active' : ''; ?>">
        <a class="page-link" href="?activity_page=<?php echo $p; ?><?php echo $filter_query; ?>#logs"><?php echo $p; ?></a>
    </li>
    <?php endfor; ?>
    
    <?php if ($activity_page < $activity_total_pages): ?>
    <li class="page-item"><a class="page-link" href="?activity_page=<?php echo $activity_page + 1; ?><?php echo $filter_query; ?>#logs">›</a></li>
    <li class="page-item"><a class="page-link" href="?activity_page=<?php echo $activity_total_pages; ?><?php echo $filter_query; ?>#logs">»</a></li>
    <?php endif; ?>
</ul>
</nav>
<?php endif; ?>

<?php else: ?>
<div class="text-center text-muted py-4">
    <?php if ($has_filter): ?>
    <p><?php echo __("adm_no_filtered_activity"); ?></p>
    <a href="?#logs" class="btn btn-sm btn-outline-secondary"><?php echo __("adm_btn_reset_filter"); ?></a>
    <?php else: ?>
    <p><?php echo __("adm_no_activity_records"); ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

</div>
</div>

<script>
function deleteOldActivityLogs() {
    var days = prompt(i18n_adm.prompt_days_delete || 'Days?', '30');
    if (days && !isNaN(days) && parseInt(days) > 0) {
        document.getElementById('activity_delete_type').value = 'old';
        document.getElementById('activity_days_old').value = parseInt(days);
        document.getElementById('activityLogDeleteForm').submit();
    }
}

function deleteAllActivityLogs() {
    if (confirm(i18n_adm.confirm_delete_all_logs)) {
        document.getElementById('activity_delete_type').value = 'all';
        document.getElementById('activityLogDeleteForm').submit();
    }
}
</script>

</div>
</div>

<!-- Deleted users tab -->
<div class="tab-pane fade" id="deleted_users">
<div class="container-fluid">
<div class="card m-2">
<div class="card-header" style="background:#6c757d;color:#fff;"><?php echo __("adm_card_deleted_users"); ?></div>
<div class="card-body">

<?php
$deleted_users_file = __DIR__ . '/src/deleted_users.json';
$deleted_users_data = [];
if (file_exists($deleted_users_file)) {
    $deleted_users_data = json_decode(file_get_contents($deleted_users_file), true) ?? [];
}
$deleted_users_data = array_reverse($deleted_users_data); // 최신순
?>

<?php if (empty($deleted_users_data)): ?>
<div class="text-center py-4 text-muted">
    <p><?php echo __("adm_no_deleted_users"); ?></p>
</div>
<?php else: ?>

<div class="mb-3">
    <strong><?php echo __("adm_total"); ?> <?php echo count($deleted_users_data); ?></strong> <?php echo __('adm_deleted_users_label'); ?>
    <button type="button" class="btn btn-sm btn-outline-danger float-right" onclick="if(confirm(i18n_adm.confirm_purge_all_users || '')) document.getElementById('deleteAllDeletedUsersForm').submit();">
        🗑️ <?php echo __('adm_btn_purge_all'); ?>
    </button>
    <form id="deleteAllDeletedUsersForm" method="post" style="display:none;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="mode" value="purge_all_deleted_users">
    </form>
</div>

<div class="table-responsive">
<table class="table table-sm table-hover" style="font-size:13px;">
<thead class="thead-light">
<tr>
    <th><?php echo __("adm_th_userid"); ?></th>
    <th><?php echo __("adm_th_email"); ?></th>
    <th><?php echo __("adm_th_group"); ?></th>
    <th><?php echo __("adm_th_category"); ?></th>
    <th><?php echo __("adm_th_datetime"); ?></th>
    <th><?php echo __("adm_th_joined"); ?></th>
    <th width="120"><?php echo __("adm_th_action"); ?></th>
</tr>
</thead>
<tbody>
<?php foreach ($deleted_users_data as $idx => $du): 
    $del_type = ($du['deleted_by'] ?? '') === 'self' ? __('adm_withdrawal') : __('adm_deletion');
    $del_badge = ($du['deleted_by'] ?? '') === 'self' ? 'badge-warning' : 'badge-danger';
    $user_info = $du['user_info'] ?? [];
?>
<tr>
    <td><strong><?php echo h($du['user_id'] ?? ''); ?></strong></td>
    <td><?php echo h($user_info['email'] ?? '-'); ?></td>
    <td><span class="badge badge-secondary"><?php echo h($user_info['group'] ?? '-'); ?></span></td>
    <td><span class="badge <?php echo $del_badge; ?>"><?php echo $del_type; ?></span></td>
    <td style="white-space:nowrap;font-size:12px;"><?php echo h($du['deleted_at'] ?? '-'); ?></td>
    <td style="white-space:nowrap;font-size:12px;"><?php echo h(substr($user_info['created_at'] ?? '-', 0, 10)); ?></td>
    <td>
        <button type="button" class="btn btn-xs btn-info" onclick="viewDeletedUser(<?php echo $idx; ?>)" title="<?php echo __('adm_btn_view_detail'); ?>">👁️</button>
        <form method="post" style="display:inline;" onsubmit="return confirm(i18n_adm.confirm_purge_user);">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="mode" value="purge_deleted_user">
            <input type="hidden" name="purge_index" value="<?php echo $idx; ?>">
            <button type="submit" class="btn btn-xs btn-danger" title="<?php echo __('adm_btn_purge'); ?>">🗑️</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- Deleted user detail modal -->
<div class="modal fade" id="deletedUserModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">
    <div class="modal-header bg-secondary text-white py-2">
        <h5 class="modal-title"><?php echo __("adm_heading_deleted_user_detail"); ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
    </div>
    <div class="modal-body" id="deletedUserModalBody">
    </div>
    <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><?php echo __("common_close"); ?></button>
    </div>
</div>
</div>
</div>

<script>
var deletedUsersData = <?php echo json_encode($deleted_users_data, JSON_UNESCAPED_UNICODE); ?>;
var currentDeletedUserIdx = null;
var loginLogPage = 1;
var activityLogPage = 1;
var logsPerPage = 10;
var loginLogDateFrom = '';
var loginLogDateTo = '';
var activityLogDateFrom = '';
var activityLogDateTo = '';

function viewDeletedUser(idx) {
    var du = deletedUsersData[idx];
    if (!du) return;
    
    currentDeletedUserIdx = idx;
    loginLogPage = 1;
    activityLogPage = 1;
    loginLogDateFrom = '';
    loginLogDateTo = '';
    activityLogDateFrom = '';
    activityLogDateTo = '';
    
    var html = '<div class="row">';
    html += '<div class="col-md-6">';
    html += '<h6><?php echo __("adm_heading_user_info"); ?></h6>';
    html += '<table class="table table-sm table-bordered" style="font-size:13px;">';
    html += '<tr><th width="100"><?php echo __("adm_th_userid"); ?></th><td><strong>' + (du.user_id || '-') + '</strong></td></tr>';
    html += '<tr><th><?php echo __("adm_th_email"); ?></th><td>' + (du.user_info?.email || '-') + '</td></tr>';
    html += '<tr><th><?php echo __("adm_th_group"); ?></th><td>' + (du.user_info?.group || '-') + '</td></tr>';
    html += '<tr><th><?php echo __("adm_th_joined"); ?></th><td>' + (du.user_info?.created_at || '-') + '</td></tr>';
    html += '<tr><th><?php echo __("adm_th_category"); ?></th><td>' + (du.deleted_by === 'self' ? '<span class="badge badge-warning"><?php echo __('adm_withdrawal'); ?></span>' : '<span class="badge badge-danger"><?php echo __("adm_deletion"); ?></span>') + '</td></tr>';
    html += '<tr><th>' + (du.deleted_by === 'self' ? '<?php echo __('adm_th_withdrawal_date'); ?>' : '<?php echo __('adm_th_deletion_date'); ?>') + '</th><td>' + (du.deleted_at || '-') + '</td></tr>';
    html += '</table>';
    html += '</div>';
    
    html += '<div class="col-md-6">';
    html += '<h6><?php echo __("adm_cache_chk_stats"); ?></h6>';
    html += '<table class="table table-sm table-bordered" style="font-size:13px;">';
    html += '<tr><th><?php echo __("adm_th_login_history"); ?></th><td>' + (du.login_logs?.length || 0) + '</td></tr>';
    html += '<tr><th><?php echo __('adm_activity_records'); ?></th><td>' + (du.activity_logs?.length || 0) + '</td></tr>';
    html += '</table>';
    html += '</div>';
    html += '</div>';
    
    // 로그인 기록 컨테이너
    html += '<hr><div id="loginLogSection"></div>';
    
    // 활동 로그 컨테이너
    html += '<hr><div id="activityLogSection"></div>';
    
    document.getElementById('deletedUserModalBody').innerHTML = html;
    
    renderLoginLogs();
    renderActivityLogs();
    
    $('#deletedUserModal').modal('show');
}

function filterLogsByDate(logs, dateFrom, dateTo) {
    if (!dateFrom && !dateTo) return logs;
    
    return logs.filter(function(log) {
        var logDate = (log.datetime || '').substring(0, 10); // YYYY-MM-DD
        if (dateFrom && logDate < dateFrom) return false;
        if (dateTo && logDate > dateTo) return false;
        return true;
    });
}

function renderLoginLogs() {
    var du = deletedUsersData[currentDeletedUserIdx];
    var html = '<h6><?php echo __("adm_heading_login_history"); ?></h6>';
    
    // 날짜 검색 필터
    html += '<div class="row mb-2" style="font-size:12px;">';
    html += '<div class="col-auto"><input type="date" class="form-control form-control-sm" id="loginLogDateFrom" value="' + loginLogDateFrom + '" onchange="applyLoginLogDateFilter()" style="font-size:12px;"></div>';
    html += '<div class="col-auto px-0 d-flex align-items-center">~</div>';
    html += '<div class="col-auto"><input type="date" class="form-control form-control-sm" id="loginLogDateTo" value="' + loginLogDateTo + '" onchange="applyLoginLogDateFilter()" style="font-size:12px;"></div>';
    html += '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetLoginLogDateFilter()" style="font-size:11px;"><?php echo __("adm_btn_reset"); ?></button></div>';
    html += '</div>';
    
    if (!du || !du.login_logs || du.login_logs.length === 0) {
        html += '<p class="text-muted">No login records.</p>';
        document.getElementById('loginLogSection').innerHTML = html;
        return;
    }
    
    var allLogs = du.login_logs.slice().reverse(); // 최신순
    var filteredLogs = filterLogsByDate(allLogs, loginLogDateFrom, loginLogDateTo);
    var totalLogs = filteredLogs.length;
    var totalPages = Math.ceil(totalLogs / logsPerPage);
    
    if (loginLogPage > totalPages) loginLogPage = Math.max(1, totalPages);
    
    var start = (loginLogPage - 1) * logsPerPage;
    var end = Math.min(start + logsPerPage, totalLogs);
    var pageLogs = filteredLogs.slice(start, end);
    
    html += '<div class="text-muted mb-1" style="font-size:11px;">Total: ' + totalLogs + '' + (loginLogDateFrom || loginLogDateTo ? ' (filtered)' : '') + '</div>';
    
    if (totalLogs === 0) {
        html += '<p class="text-muted">No login records for this period.</p>';
        document.getElementById('loginLogSection').innerHTML = html;
        return;
    }
    
    html += '<div class="table-responsive"><table class="table table-sm" style="font-size:12px;">';
    html += '<thead class="thead-light"><tr><th><?php echo __("adm_th_datetime"); ?></th><th><?php echo __("adm_th_ip"); ?></th><th><?php echo __("adm_th_country"); ?></th></tr></thead><tbody>';
    for (var i = 0; i < pageLogs.length; i++) {
        html += '<tr><td style="white-space:nowrap;">' + (pageLogs[i].datetime || '-') + '</td><td><code>' + (pageLogs[i].ip || '-') + '</code></td><td>' + (pageLogs[i].country || '-') + '</td></tr>';
    }
    html += '</tbody></table></div>';
    
    // 페이지네이션
    if (totalPages > 1) {
        html += '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
        html += '<li class="page-item ' + (loginLogPage === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" onclick="changeLoginLogPage(' + (loginLogPage - 1) + ');return false;">«</a></li>';
        
        var startPage = Math.max(1, loginLogPage - 2);
        var endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
        
        for (var p = startPage; p <= endPage; p++) {
            html += '<li class="page-item ' + (p === loginLogPage ? 'active' : '') + '"><a class="page-link" href="#" onclick="changeLoginLogPage(' + p + ');return false;">' + p + '</a></li>';
        }
        
        html += '<li class="page-item ' + (loginLogPage === totalPages ? 'disabled' : '') + '"><a class="page-link" href="#" onclick="changeLoginLogPage(' + (loginLogPage + 1) + ');return false;">»</a></li>';
        html += '</ul></nav>';
        html += '<div class="text-center text-muted" style="font-size:11px;">' + loginLogPage + ' / ' + totalPages + '</div>';
    }
    
    document.getElementById('loginLogSection').innerHTML = html;
}

function renderActivityLogs() {
    var du = deletedUsersData[currentDeletedUserIdx];
    var html = '<h6><?php echo __("adm_card_activity_log"); ?></h6>';
    
    // 날짜 검색 필터
    html += '<div class="row mb-2" style="font-size:12px;">';
    html += '<div class="col-auto"><input type="date" class="form-control form-control-sm" id="activityLogDateFrom" value="' + activityLogDateFrom + '" onchange="applyActivityLogDateFilter()" style="font-size:12px;"></div>';
    html += '<div class="col-auto px-0 d-flex align-items-center">~</div>';
    html += '<div class="col-auto"><input type="date" class="form-control form-control-sm" id="activityLogDateTo" value="' + activityLogDateTo + '" onchange="applyActivityLogDateFilter()" style="font-size:12px;"></div>';
    html += '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetActivityLogDateFilter()" style="font-size:11px;"><?php echo __("adm_btn_reset"); ?></button></div>';
    html += '</div>';
    
    if (!du || !du.activity_logs || du.activity_logs.length === 0) {
        html += '<p class="text-muted">No activity logs.</p>';
        document.getElementById('activityLogSection').innerHTML = html;
        return;
    }
    
    var allLogs = du.activity_logs.slice().reverse(); // 최신순
    var filteredLogs = filterLogsByDate(allLogs, activityLogDateFrom, activityLogDateTo);
    var totalLogs = filteredLogs.length;
    var totalPages = Math.ceil(totalLogs / logsPerPage);
    
    if (activityLogPage > totalPages) activityLogPage = Math.max(1, totalPages);
    
    var start = (activityLogPage - 1) * logsPerPage;
    var end = Math.min(start + logsPerPage, totalLogs);
    var pageLogs = filteredLogs.slice(start, end);
    
    html += '<div class="text-muted mb-1" style="font-size:11px;">Total: ' + totalLogs + '' + (activityLogDateFrom || activityLogDateTo ? ' (filtered)' : '') + '</div>';
    
    if (totalLogs === 0) {
        html += '<p class="text-muted">No activity records for this period.</p>';
        document.getElementById('activityLogSection').innerHTML = html;
        return;
    }
    
    html += '<div class="table-responsive"><table class="table table-sm" style="font-size:12px;">';
    html += '<thead class="thead-light"><tr><th><?php echo __("adm_th_datetime"); ?></th><th><?php echo __('adm_th_activity'); ?></th><th><?php echo __("adm_th_detail"); ?></th></tr></thead><tbody>';
    for (var i = 0; i < pageLogs.length; i++) {
        html += '<tr><td style="white-space:nowrap;">' + (pageLogs[i].datetime || '-') + '</td><td>' + (pageLogs[i].action || '-') + '</td><td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + (pageLogs[i].detail || '') + '">' + (pageLogs[i].detail || '-') + '</td></tr>';
    }
    html += '</tbody></table></div>';
    
    // 페이지네이션
    if (totalPages > 1) {
        html += '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
        html += '<li class="page-item ' + (activityLogPage === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" onclick="changeActivityLogPage(' + (activityLogPage - 1) + ');return false;">«</a></li>';
        
        var startPage = Math.max(1, activityLogPage - 2);
        var endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
        
        for (var p = startPage; p <= endPage; p++) {
            html += '<li class="page-item ' + (p === activityLogPage ? 'active' : '') + '"><a class="page-link" href="#" onclick="changeActivityLogPage(' + p + ');return false;">' + p + '</a></li>';
        }
        
        html += '<li class="page-item ' + (activityLogPage === totalPages ? 'disabled' : '') + '"><a class="page-link" href="#" onclick="changeActivityLogPage(' + (activityLogPage + 1) + ');return false;">»</a></li>';
        html += '</ul></nav>';
        html += '<div class="text-center text-muted" style="font-size:11px;">' + activityLogPage + ' / ' + totalPages + '</div>';
    }
    
    document.getElementById('activityLogSection').innerHTML = html;
}

function applyLoginLogDateFilter() {
    loginLogDateFrom = document.getElementById('loginLogDateFrom').value;
    loginLogDateTo = document.getElementById('loginLogDateTo').value;
    loginLogPage = 1;
    renderLoginLogs();
}

function resetLoginLogDateFilter() {
    loginLogDateFrom = '';
    loginLogDateTo = '';
    loginLogPage = 1;
    renderLoginLogs();
}

function applyActivityLogDateFilter() {
    activityLogDateFrom = document.getElementById('activityLogDateFrom').value;
    activityLogDateTo = document.getElementById('activityLogDateTo').value;
    activityLogPage = 1;
    renderActivityLogs();
}

function resetActivityLogDateFilter() {
    activityLogDateFrom = '';
    activityLogDateTo = '';
    activityLogPage = 1;
    renderActivityLogs();
}

function changeLoginLogPage(page) {
    var du = deletedUsersData[currentDeletedUserIdx];
    if (!du || !du.login_logs) return;
    var filteredLogs = filterLogsByDate(du.login_logs, loginLogDateFrom, loginLogDateTo);
    var totalPages = Math.ceil(filteredLogs.length / logsPerPage);
    if (page < 1 || page > totalPages) return;
    loginLogPage = page;
    renderLoginLogs();
}

function changeActivityLogPage(page) {
    var du = deletedUsersData[currentDeletedUserIdx];
    if (!du || !du.activity_logs) return;
    var filteredLogs = filterLogsByDate(du.activity_logs, activityLogDateFrom, activityLogDateTo);
    var totalPages = Math.ceil(filteredLogs.length / logsPerPage);
    if (page < 1 || page > totalPages) return;
    activityLogPage = page;
    renderActivityLogs();
}
</script>

<?php endif; ?>

</div>
</div>
</div>
</div>

<!-- Terms of service settings tab -->
<div class="tab-pane fade" id="terms">
<div class="card m-2">
<div class="card-header" style="background:#17a2b8;color:#fff;"><?php echo __("adm_card_terms_settings"); ?></div>
<div class="card-body">

<?php
$terms_settings = get_app_settings('terms', [
    'enabled' => false,
    'content' => ''
]);
?>

<form method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="save_terms">

<div class="form-group">
    <div class="custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" id="terms_enabled" name="terms_enabled" 
               <?php echo ($terms_settings['enabled'] ?? false) ? 'checked' : ''; ?>>
        <label class="custom-control-label" for="terms_enabled">
            <strong><?php echo __("adm_label_terms_required"); ?></strong>
        </label>
    </div>
    <small class="text-muted"><?php echo __("adm_terms_required_desc"); ?></small>
</div>

<div class="form-group">
    <label><strong><?php echo __("adm_terms_content_label"); ?></strong></label>
    <textarea class="form-control" name="terms_content" rows="15" style="width:100%;" placeholder="<?php echo __('adm_ph_terms_content'); ?>"><?php echo h($terms_settings['content'] ?? ''); ?></textarea>
    <small class="text-muted"><?php echo __("adm_terms_html_help"); ?></small>
</div>

<button type="submit" class="btn btn-primary btn-block"><?php echo __("adm_btn_save_terms"); ?></button>

</form>

<hr>

<h6><?php echo __("adm_heading_preview"); ?></h6>
<div class="border p-3 bg-light" style="max-height:300px;overflow-y:auto;font-size:13px;width:100%;">
    <?php 
    $preview = $terms_settings['content'] ?? '';
    if (empty($preview)) {
        echo '<span class="text-muted">' . __("adm_no_terms_content") . '</span>';
    } else {
        echo nl2br(h($preview));
    }
    ?>
</div>

</div>
</div>
</div>

<div class="tab-pane fade" id="security">
<div class="container-fluid">

<?php
// IP 차단 설정 로드
require_once __DIR__ . '/ip_block.php';
$ipBlocker = new IPBlocker(__DIR__ . '/src/ip_block_settings.json');
$ipBlockSettings = $ipBlocker->getSettings();
$blockLogs = $ipBlocker->getBlockLogs(20, 1); // 페이지네이션 지원
$bruteforceLogs = $ipBlocker->getBruteforceLogs(20, 1); // 페이지네이션 지원
$lockedIPs = $ipBlocker->getLockedIPs();
$currentIP = $ipBlocker->getClientIP();
$currentCountry = $ipBlocker->getCountryByIP($currentIP);
?>

<div class="row">
<!-- Settings area -->
<div class="col-lg-8">

<div class="card mb-3">
<div class="card-header bg-danger text-white"><?php echo __("adm_card_security_settings"); ?></div>
<div class="card-body">
<form method="POST">
<input type="hidden" name="mode" value="security_settings">
<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

<!-- Current connection info -->
<div class="alert alert-info mb-3">
    <strong><?php echo __("adm_sec_current_info"); ?></strong> 
    IP: <code><?php echo h($currentIP); ?></code> | 
    <?php echo __("adm_th_country"); ?>: <code><?php echo h($currentCountry); ?></code>
    <?php if (isset(IPBlocker::$countries[$currentCountry])): ?>
        (<?php echo h(IPBlocker::$countries[$currentCountry]); ?>)
    <?php endif; ?>
</div>

<!-- Enable -->
<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="ip_block_enabled" name="ip_block_enabled" 
               <?php echo ($ipBlockSettings['enabled'] ?? false) ? 'checked' : ''; ?>>
        <label class="custom-control-label" for="ip_block_enabled">
            <strong><?php echo __("adm_sec_block_enable"); ?></strong>
        </label>
    </div>
    <small class="text-danger" style="font-weight: bold;"><?php echo __("adm_sec_must_select_mode"); ?></small>
</div>

<!-- Block mode (multi-select) -->
<fieldset id="security_settings_fieldset">
<div class="form-group">
    <label><strong><?php echo __("adm_sec_block_mode_label"); ?></strong> <small class="text-danger" style="font-weight: bold;"><?php echo __("adm_sec_required"); ?></small></label>
    <?php 
    $modes = $ipBlockSettings['mode'] ?? [];
    if (!is_array($modes)) $modes = $modes ? [$modes] : [];
    ?>
    <div class="row">
        <div class="col-md-6">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input security-mode-check" id="mode_block_countries" name="ip_block_mode[]" value="block_countries"
                       <?php echo in_array('block_countries', $modes) ? 'checked' : ''; ?>>
                <label class="custom-control-label" for="mode_block_countries"><?php echo __("adm_label_block_countries"); ?></label>
            </div>
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input security-mode-check" id="mode_allow_countries" name="ip_block_mode[]" value="allow_countries"
                       <?php echo in_array('allow_countries', $modes) ? 'checked' : ''; ?>>
                <label class="custom-control-label" for="mode_allow_countries"><?php echo __("adm_label_allow_countries"); ?></label>
            </div>
        </div>
        <div class="col-md-6">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input security-mode-check" id="mode_block_ips" name="ip_block_mode[]" value="block_ips"
                       <?php echo in_array('block_ips', $modes) ? 'checked' : ''; ?>>
                <label class="custom-control-label" for="mode_block_ips"><?php echo __("adm_label_block_ips"); ?></label>
            </div>
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input security-mode-check" id="mode_allow_ips" name="ip_block_mode[]" value="allow_ips"
                       <?php echo in_array('allow_ips', $modes) ? 'checked' : ''; ?>>
                <label class="custom-control-label" for="mode_allow_ips"><?php echo __("adm_label_allow_ips"); ?></label>
            </div>
        </div>
    </div>
    <small class="text-info"><?php echo __("adm_sec_combined_note"); ?></small>
</div>

<hr>

<!-- GeoIP data source settings -->
<div class="form-group">
    <label><strong><?php echo __("adm_sec_geoip_source"); ?></strong></label>
    <?php $geoipSource = $ipBlockSettings['geoip_source'] ?? 'api'; ?>
    <div class="row">
        <div class="col-md-3">
            <div class="custom-control custom-radio">
                <input type="radio" class="custom-control-input" id="geoip_api" name="geoip_source" value="api"
                       <?php echo $geoipSource === 'api' ? 'checked' : ''; ?>>
                <label class="custom-control-label" for="geoip_api">
                    🌐 External API
                </label>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-control custom-radio">
                <input type="radio" class="custom-control-input" id="geoip_mmdb" name="geoip_source" value="mmdb"
                       <?php echo $geoipSource === 'mmdb' ? 'checked' : ''; ?>>
                <label class="custom-control-label" for="geoip_mmdb">
                    📦 MMDB (Recommended)
                </label>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-control custom-radio">
                <input type="radio" class="custom-control-input" id="geoip_dat" name="geoip_source" value="dat"
                       <?php echo $geoipSource === 'dat' ? 'checked' : ''; ?>>
                <label class="custom-control-label" for="geoip_dat">
                    📁 DAT (Legacy)
                </label>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-control custom-radio">
                <input type="radio" class="custom-control-input" id="geoip_csv" name="geoip_source" value="csv"
                       <?php echo $geoipSource === 'csv' ? 'checked' : ''; ?>>
                <label class="custom-control-label" for="geoip_csv">
                    📄 CSV File
                </label>
            </div>
        </div>
    </div>
    
    <!-- MMDB file path -->
    <div class="mt-3" id="geoip_mmdb_group" style="<?php echo $geoipSource !== 'mmdb' ? 'display:none;' : ''; ?>">
        <label><?php echo __("adm_sec_mmdb_path"); ?></label>
        <input type="text" class="form-control" name="geoip_mmdb_path" style="width:100%;"
               value="<?php echo h($ipBlockSettings['geoip_mmdb_path'] ?? ''); ?>"
               placeholder="<?php echo __('adm_ph_geoip_mmdb'); ?>">
        <small class="text-info">
            📥 <a href="https://dev.maxmind.com/geoip/geolite2-free-geolocation-data" target="_blank">MaxMind GeoLite2</a>에서 무료 다운로드 (회원가입 필요)
        </small>
    </div>
    
    <!-- DAT file path -->
    <div class="mt-3" id="geoip_dat_group" style="<?php echo $geoipSource !== 'dat' ? 'display:none;' : ''; ?>">
        <label><?php echo __("adm_sec_dat_path"); ?></label>
        <input type="text" class="form-control" name="geoip_dat_path" style="width:100%;"
               value="<?php echo h($ipBlockSettings['geoip_dat_path'] ?? ''); ?>"
               placeholder="<?php echo __('adm_ph_geoip_dat'); ?>">
        <small class="text-muted"><?php echo __("adm_sec_dat_desc"); ?></small>
    </div>
    
    <!-- CSV file path -->
    <div class="mt-3" id="geoip_csv_group" style="<?php echo $geoipSource !== 'csv' ? 'display:none;' : ''; ?>">
        <label><?php echo __("adm_sec_csv_path"); ?></label>
        <input type="text" class="form-control" name="geoip_csv_path" style="width:100%;" 
               value="<?php echo h($ipBlockSettings['geoip_csv_path'] ?? ''); ?>"
               placeholder="<?php echo __('adm_ph_geoip_csv'); ?>">
        <small class="text-muted"><?php echo __("adm_sec_csv_desc"); ?></small>
    </div>
    
    <!-- Block UNKNOWN IPs -->
    <div class="custom-control custom-checkbox mt-2">
        <input type="checkbox" class="custom-control-input" id="block_unknown" name="block_unknown" 
               <?php echo ($ipBlockSettings['block_unknown'] ?? false) ? 'checked' : ''; ?>>
        <label class="custom-control-label" for="block_unknown">
            🚫 Block UNKNOWN Country IPs
        </label>
    </div>
    <small class="text-warning"><?php echo __("adm_sec_unknown_warning"); ?></small>
</div>

<hr>

<!-- Country settings -->
<div class="row">
    <div class="col-md-6" id="blocked_countries_group">
        <div class="form-group">
            <label><strong><?php echo __("adm_sec_blocked_countries"); ?></strong> <small class="text-muted"><?php echo __("adm_sec_country_format"); ?></small></label>
            <input type="text" class="form-control" name="blocked_countries" 
                   value="<?php echo h(implode(',', $ipBlockSettings['blocked_countries'] ?? [])); ?>"
                   placeholder="CN,RU,KP">
            <small class="text-muted"><?php echo __("adm_sec_country_examples"); ?></small>
        </div>
    </div>
    <div class="col-md-6" id="allowed_countries_group">
        <div class="form-group">
            <label><strong><?php echo __("adm_sec_allowed_countries"); ?></strong> <small class="text-muted"><?php echo __("adm_sec_country_format"); ?></small></label>
            <input type="text" class="form-control" name="allowed_countries" 
                   value="<?php echo h(implode(',', $ipBlockSettings['allowed_countries'] ?? [])); ?>"
                   placeholder="KR,US,JP">
            <small class="text-muted"><?php echo __("adm_sec_whitelist_examples"); ?></small>
        </div>
    </div>
</div>

<!-- IP settings -->
<div class="row">
    <div class="col-md-6" id="blocked_ips_group">
        <div class="form-group">
            <label><strong><?php echo __("adm_sec_blocked_ips"); ?></strong> <small class="text-muted"><?php echo __("adm_sec_format_note"); ?></small></label>
            <textarea class="form-control" name="blocked_ips" rows="4" 
                      placeholder="1.2.3.4&#10;5.6.7.0/24"><?php echo h(implode("\n", $ipBlockSettings['blocked_ips'] ?? [])); ?></textarea>
        </div>
    </div>
    <div class="col-md-6" id="allowed_ips_group">
        <div class="form-group">
            <label><strong><?php echo __("adm_sec_allowed_ip"); ?></strong> <small class="text-muted"><?php echo __("adm_sec_format_note"); ?></small></label>
            <textarea class="form-control" name="allowed_ips" rows="4" 
                      placeholder="192.168.1.0/24&#10;10.0.0.0/8"><?php echo h(implode("\n", $ipBlockSettings['allowed_ips'] ?? [])); ?></textarea>
        </div>
    </div>
</div>

<!-- Whitelist (admin IP) -->
<div class="form-group" id="whitelist_ips_group">
    <label><strong><?php echo __("adm_sec_admin_ip"); ?></strong></label>
    <div class="alert alert-danger py-2 mb-2" style="background-color: #dc3545; border-color: #dc3545;">
        <strong style="color: #fff; font-size: 1.1em;"><?php echo __("adm_sec_admin_ip_warning"); ?></strong><br>
        <span style="color: #fff;"><?php echo __("adm_sec_admin_ip_desc"); ?></span><br>
        <span style="color: #ffeb3b; font-weight: bold;"><?php echo __("adm_sec_current_ip"); ?>: <code style="background: rgba(255,255,255,0.2); color: #fff;"><?php echo h($currentIP); ?></code> <?php echo __("adm_sec_add_this_ip"); ?></span>
    </div>
    <textarea class="form-control" name="whitelist_ips" rows="2" 
              placeholder="127.0.0.1&#10;192.168.1.100"><?php echo h(implode("\n", $ipBlockSettings['whitelist_ips'] ?? [])); ?></textarea>
    <small class="text-muted"><?php echo __("adm_sec_cidr_help"); ?></small>
</div>

<hr>

<!-- Other settings -->
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label><strong><?php echo __("adm_sec_block_msg"); ?></strong></label>
            <input type="text" class="form-control" name="block_message" 
                   value="<?php echo h($ipBlockSettings['block_message'] ?? __('adm_default_block_msg')); ?>">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label><strong><?php echo __("adm_sec_ip_cache_time"); ?></strong></label>
            <div class="input-group">
                <input type="number" class="form-control" name="cache_hours" min="1" max="168"
                       value="<?php echo intval($ipBlockSettings['cache_hours'] ?? 24); ?>">
                <div class="input-group-append"><span class="input-group-text"><?php echo __("adm_unit_hours"); ?></span></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label><strong><?php echo __("adm_sec_block_log"); ?></strong></label>
            <div class="custom-control custom-switch mt-2">
                <input type="checkbox" class="custom-control-input" id="log_enabled" name="log_enabled" 
                       <?php echo ($ipBlockSettings['log_enabled'] ?? true) ? 'checked' : ''; ?>>
                <label class="custom-control-label" for="log_enabled"><?php echo __("adm_label_logging"); ?></label>
            </div>
        </div>
    </div>
</div>

<hr>

<!-- Brute force protection settings -->
<div class="mt-3">
    <h6><strong><?php echo __("adm_sec_bruteforce"); ?></strong></h6>
    <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" id="bruteforce_enabled" name="bruteforce_enabled" 
               <?php echo ($ipBlockSettings['bruteforce_enabled'] ?? true) ? 'checked' : ''; ?>>
        <label class="custom-control-label" for="bruteforce_enabled">
            <strong><?php echo __("adm_sec_bf_enable"); ?></strong>
        </label>
    </div>
    <small class="text-muted d-block mb-2"><?php echo __("adm_sec_bf_desc"); ?></small>
    
    <div class="row" id="bruteforce_settings">
        <div class="col-md-4">
            <div class="form-group">
                <label><?php echo __("adm_sec_max_tries"); ?></label>
                <div class="input-group">
                    <input type="number" class="form-control" name="bruteforce_max_attempts" min="1" max="20"
                           value="<?php echo intval($ipBlockSettings['bruteforce_max_attempts'] ?? 5); ?>">
                    <div class="input-group-append"><span class="input-group-text"><?php echo __("adm_unit_times"); ?></span></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label><?php echo __("adm_sec_attempt_window"); ?></label>
                <div class="input-group">
                    <input type="number" class="form-control" name="bruteforce_attempt_window" min="60" max="3600"
                           value="<?php echo intval($ipBlockSettings['bruteforce_attempt_window'] ?? 300); ?>">
                    <div class="input-group-append"><span class="input-group-text"><?php echo __("adm_unit_sec"); ?></span></div>
                </div>
                <small class="text-muted"><?php echo __("adm_sec_window_desc"); ?></small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label><?php echo __("adm_sec_lockout_time"); ?></label>
                <div class="input-group">
                    <select class="form-control" name="bruteforce_lockout_time" id="bruteforce_lockout_time_select">
                        <?php 
                        $currentLockout = intval($ipBlockSettings['bruteforce_lockout_time'] ?? 900);
                        $presets = [
                            60 => __('adm_lock_1min'),
                            300 => __('adm_lock_5min'),
                            600 => __('adm_lock_10min'),
                            900 => __('adm_lock_15min'),
                            1800 => __('adm_lock_30min'),
                            3600 => __('adm_lock_1hour'),
                            7200 => __('adm_lock_2hour'),
                            21600 => __('adm_lock_6hour'),
                            43200 => __('adm_lock_12hour'),
                            86400 => __('adm_lock_24hour'),
                            0 => __('adm_lock_unlimited')
                        ];
                        foreach ($presets as $val => $label):
                            $selected = ($currentLockout == $val || ($val == 0 && $currentLockout <= 0)) ? 'selected' : '';
                        ?>
                        <option value="<?php echo $val; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <small class="text-muted"><?php echo __("adm_sec_unlimited_note"); ?></small>
            </div>
        </div>
    </div>
</div>

<hr>

<!-- IP detection (proxy header trust) -->
<div class="mt-3">
    <h6><strong><?php echo __("adm_sec_ip_detection"); ?></strong></h6>
    <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" id="trust_proxy_headers" name="trust_proxy_headers" 
               <?php echo ($ipBlockSettings['trust_proxy_headers'] ?? true) ? 'checked' : ''; ?>>
        <label class="custom-control-label" for="trust_proxy_headers">
            <strong><?php echo __("adm_sec_trust_proxy"); ?></strong>
        </label>
    </div>
    <small class="text-muted d-block mb-2">
        <strong>On (recommended):</strong> When using Cloudflare, Nginx reverse proxy, load balancer<br>
        <strong>Off:</strong> Direct connection, prevents IP spoofing (REMOTE_ADDR only)
    </small>
    <div class="alert alert-info py-2 small">
        <strong>⚠️ Warning:</strong> If enabled without a proxy, 
        attackers can spoof IPs via X-Forwarded-For header.
    </div>
</div>

</fieldset>
<button type="submit" class="btn btn-danger btn-block"><?php echo __("adm_btn_save_security"); ?></button>
</form>
</div>
</div>

<!-- Country code reference (clickable) -->
<div class="card mb-3">
<div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center" data-toggle="collapse" data-target="#countryCodesCollapse" style="cursor:pointer;">
    <span><?php echo __("adm_sec_country_ref"); ?> <small><?php echo __("adm_sec_click_toggle"); ?></small></span>
    <span class="badge badge-light"><?php echo __("adm_sec_toggle"); ?></span>
</div>
<div class="collapse" id="countryCodesCollapse">
<div class="card-body p-3">
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap">
        <small class="text-muted"><?php echo __("adm_sec_click_country_help"); ?></small>
        <div class="btn-group btn-group-sm mt-1">
            <button type="button" class="btn btn-outline-secondary" onclick="toggleAllCountryButtons(false)"><?php echo __("adm_btn_deselect_all_items"); ?></button>
        </div>
    </div>
    
    <?php 
    // 전체 255개 국가를 지역별로 표시 (ISO 3166-1 alpha-2 코드순 정렬)
    foreach (IPBlocker::getTranslatedRegions() as $region => $codes): 
        $sortedCodes = $codes;
        sort($sortedCodes); // 알파벳 순 정렬
    ?>
    <div class="mb-3 p-2" style="background: #f8f9fa; border-radius: 4px;">
        <div class="d-flex align-items-center mb-2" style="border-left: 3px solid #007bff; padding-left: 10px;">
            <strong style="color: #333;"><?php echo h($region); ?></strong>
            <small class="text-muted ml-2">(<?php echo count($codes); ?>)</small>
        </div>
        <div class="d-flex flex-wrap" style="gap: 6px; padding-left: 13px;">
            <?php foreach ($sortedCodes as $code): 
                $name = IPBlocker::$countries[$code] ?? $code;
            ?>
            <button type="button" 
                    class="btn btn-sm country-select-btn" 
                    data-code="<?php echo h($code); ?>"
                    style="border: 1px solid #dee2e6; background: #fff; padding: 4px 10px; font-size: 0.85em; border-radius: 4px;">
                <strong style="color: #007bff;"><?php echo h($code); ?></strong>
                <span style="color: #6c757d;"><?php echo h($name); ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>
</div>

</div>

<!-- Log/management area -->
<div class="col-lg-4">

<!-- Cache management -->
<div class="card mb-3">
<div class="card-header bg-warning"><?php echo __("adm_card_cache_mgmt"); ?></div>
<div class="card-body">
    <form method="POST" class="mb-0" onsubmit="return confirm('<?php echo __("adm_sec_confirm_ip_cache"); ?>');">
        <input type="hidden" name="mode" value="clear_ip_cache">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <button type="submit" class="btn btn-outline-warning btn-block btn-sm"><?php echo __("adm_btn_clear_ip_cache"); ?></button>
    </form>
    <small class="text-muted d-block mt-2"><?php echo __("adm_sec_log_delete_help"); ?></small>
</div>
</div>

<!-- Locked IP list -->
<?php if (!empty($lockedIPs)): ?>
<div class="card mb-3">
<div class="card-header bg-danger text-white"><?php echo __("adm_sec_locked_ips"); ?> <span class="badge badge-light"><?php echo count($lockedIPs); ?></span></div>
<div class="card-body p-0">
    <table class="table table-sm mb-0" style="font-size: 0.85em;">
    <thead class="thead-light">
        <tr><th><?php echo __("adm_th_ip"); ?></th><th><?php echo __("adm_th_remaining"); ?></th><th><?php echo __("adm_btn_unlock"); ?></th></tr>
    </thead>
    <tbody>
    <?php foreach ($lockedIPs as $locked): ?>
        <tr>
            <td><code><?php echo h($locked['ip']); ?></code></td>
            <td><?php echo $locked['remaining'] == -1 ? '<span class="badge badge-dark">♾️ Unlimited</span>' : ceil($locked['remaining'] / 60) . 'm'; ?></td>
            <td>
                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo __("adm_sec_confirm_unlock"); ?>');">
                    <input type="hidden" name="mode" value="unlock_ip">
                    <input type="hidden" name="unlock_ip" value="<?php echo h($locked['ip']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success py-0"><?php echo __("adm_btn_release"); ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
</div>
</div>
<?php endif; ?>

<!-- Block logs -->
<div class="card mb-3">
<div class="card-header bg-dark" style="color: #fff;"><?php echo __("adm_sec_block_log_card"); ?> <span class="badge badge-light" id="block_log_count"><?php echo $blockLogs['total'] ?? 0; ?></span></div>
<div class="card-body p-2">
    <!-- Filter -->
    <div class="mb-2 d-flex flex-wrap align-items-center" style="gap: 8px; font-size: 0.85em;">
        <div class="input-group input-group-sm" style="width: auto;">
            <div class="input-group-prepend"><span class="input-group-text">Start</span></div>
            <input type="date" class="form-control" id="block_log_from" style="width: 130px;">
        </div>
        <div class="input-group input-group-sm" style="width: auto;">
            <div class="input-group-prepend"><span class="input-group-text">End</span></div>
            <input type="date" class="form-control" id="block_log_to" style="width: 130px;">
        </div>
        <button type="button" class="btn btn-sm btn-primary" onclick="loadBlockLogs(1)"><?php echo __("adm_btn_search"); ?></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetBlockLogFilter()"><?php echo __("adm_btn_reset"); ?></button>
    </div>
    <!-- Delete buttons -->
    <div class="mb-2 d-flex flex-wrap" style="gap: 6px;">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSelectedBlockLogs()"><?php echo __("adm_btn_delete_selected"); ?></button>
        <button type="button" class="btn btn-sm btn-outline-warning" onclick="deleteBlockLogsByRange()"><?php echo __("adm_btn_delete_period"); ?></button>
        <button type="button" class="btn btn-sm btn-danger" onclick="deleteAllBlockLogs()"><?php echo __("adm_btn_delete_all"); ?></button>
    </div>
    <!-- Log table -->
    <div id="block_log_container" style="max-height: 300px; overflow-y: auto;">
        <table class="table table-sm table-striped mb-0" style="font-size: 0.8em;">
        <thead class="thead-dark">
            <tr>
                <th style="width:30px;"><input type="checkbox" id="block_log_check_all" onchange="toggleAllBlockLogChecks(this)"></th>
                <th><?php echo __("adm_th_time"); ?></th><th><?php echo __("adm_th_ip"); ?></th><th><?php echo __("adm_th_country"); ?></th><th><?php echo __("adm_th_reason"); ?></th>
            </tr>
        </thead>
        <tbody id="block_log_tbody">
        <?php if (empty($blockLogs['logs'])): ?>
            <tr><td colspan="5" class="text-center text-muted py-3"><?php echo __("adm_sec_no_block_logs"); ?></td></tr>
        <?php else: ?>
            <?php foreach ($blockLogs['logs'] as $idx => $log): ?>
            <tr>
                <td><input type="checkbox" class="block-log-check" value="<?php echo $idx; ?>"></td>
                <td><?php echo h(substr($log['time'] ?? '', 5)); ?></td>
                <td><code><?php echo h($log['ip'] ?? ''); ?></code></td>
                <td><?php echo h($log['country'] ?? ''); ?></td>
                <td><small><?php echo h(explode(':', $log['reason'] ?? '')[0]); ?></small></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div class="mt-2 d-flex justify-content-between align-items-center" style="font-size: 0.85em;">
        <small class="text-muted">Page <span id="block_log_page">1</span> / <span id="block_log_pages"><?php echo $blockLogs['pages'] ?? 1; ?></span></small>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" onclick="loadBlockLogs('prev')" id="block_log_prev"><?php echo __("adm_btn_prev"); ?></button>
            <button type="button" class="btn btn-outline-secondary" onclick="loadBlockLogs('next')" id="block_log_next"><?php echo __("adm_btn_next"); ?></button>
        </div>
    </div>
</div>
</div>

<!-- Brute force logs -->
<div class="card mb-3">
<div class="card-header bg-secondary text-white"><?php echo __("adm_sec_bf_log_card"); ?> <span class="badge badge-light" id="bf_log_count"><?php echo $bruteforceLogs['total'] ?? 0; ?></span></div>
<div class="card-body p-2">
    <!-- Filter -->
    <div class="mb-2 d-flex flex-wrap align-items-center" style="gap: 8px; font-size: 0.85em;">
        <div class="input-group input-group-sm" style="width: auto;">
            <div class="input-group-prepend"><span class="input-group-text">Start</span></div>
            <input type="date" class="form-control" id="bf_log_from" style="width: 130px;">
        </div>
        <div class="input-group input-group-sm" style="width: auto;">
            <div class="input-group-prepend"><span class="input-group-text">End</span></div>
            <input type="date" class="form-control" id="bf_log_to" style="width: 130px;">
        </div>
        <button type="button" class="btn btn-sm btn-primary" onclick="loadBfLogs(1)"><?php echo __("adm_btn_search"); ?></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetBfLogFilter()"><?php echo __("adm_btn_reset"); ?></button>
    </div>
    <!-- Delete buttons -->
    <div class="mb-2 d-flex flex-wrap" style="gap: 6px;">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSelectedBfLogs()"><?php echo __("adm_btn_delete_selected"); ?></button>
        <button type="button" class="btn btn-sm btn-outline-warning" onclick="deleteBfLogsByRange()"><?php echo __("adm_btn_delete_period"); ?></button>
        <button type="button" class="btn btn-sm btn-danger" onclick="deleteAllBfLogs()"><?php echo __("adm_btn_delete_all"); ?></button>
    </div>
    <!-- Log table -->
    <div id="bf_log_container" style="max-height: 250px; overflow-y: auto;">
        <table class="table table-sm table-striped mb-0" style="font-size: 0.8em;">
        <thead class="thead-light">
            <tr>
                <th style="width:30px;"><input type="checkbox" id="bf_log_check_all" onchange="toggleAllBfLogChecks(this)"></th>
                <th><?php echo __("adm_th_time"); ?></th><th><?php echo __("adm_th_ip"); ?></th><th><?php echo __("adm_th_attempt_id"); ?></th><th><?php echo __("adm_th_count"); ?></th>
            </tr>
        </thead>
        <tbody id="bf_log_tbody">
        <?php if (empty($bruteforceLogs['logs'])): ?>
            <tr><td colspan="5" class="text-center text-muted py-3"><?php echo __("adm_sec_no_bf_logs"); ?></td></tr>
        <?php else: ?>
            <?php foreach ($bruteforceLogs['logs'] as $idx => $log): ?>
            <tr>
                <td><input type="checkbox" class="bf-log-check" value="<?php echo $idx; ?>"></td>
                <td><?php echo h(substr($log['time'] ?? '', 5, 11)); ?></td>
                <td><code><?php echo h($log['ip'] ?? ''); ?></code></td>
                <td><small><?php echo h($log['username'] ?? '-'); ?></small></td>
                <td><span class="badge badge-danger"><?php echo intval($log['attempts'] ?? 0); ?></span></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div class="mt-2 d-flex justify-content-between align-items-center" style="font-size: 0.85em;">
        <small class="text-muted">Page <span id="bf_log_page">1</span> / <span id="bf_log_pages"><?php echo $bruteforceLogs['pages'] ?? 1; ?></span></small>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" onclick="loadBfLogs('prev')" id="bf_log_prev"><?php echo __("adm_btn_prev"); ?></button>
            <button type="button" class="btn btn-outline-secondary" onclick="loadBfLogs('next')" id="bf_log_next"><?php echo __("adm_btn_next"); ?></button>
        </div>
    </div>
</div>
</div>

<!-- Usage guide -->
<div class="card">
<div class="card-header bg-info text-white"><?php echo __("adm_card_usage"); ?></div>
<div class="card-body" style="font-size: 0.85em;">
<p><strong><?php echo __("adm_sec_block_apply"); ?></strong></p>
<pre class="bg-light p-2 mb-2"><code>require_once 'ip_block.php';
check_ip_block();</code></pre>

<p><strong><?php echo __("adm_sec_bruteforce_apply"); ?></strong></p>
<p class="mb-1"><?php echo __("adm_sec_help_login_fail"); ?></p>
<pre class="bg-light p-2 mb-2"><code>$blocker = new IPBlocker();
// 실패 시
$blocker->recordLoginFailure($ip, $username);
// 성공 시  
$blocker->clearLoginAttempts($ip);</code></pre>

<p class="mt-2"><strong><?php echo __("adm_sec_block_mode"); ?></strong></p>
<ul class="mb-0 pl-3">
    <li><strong><?php echo __("adm_sec_help_block_country"); ?></strong></li>
    <li><strong><?php echo __("adm_sec_help_allow_country"); ?></strong></li>
    <li><strong><?php echo __("adm_sec_help_block_ip"); ?></strong></li>
    <li><strong><?php echo __("adm_sec_help_allow_ip"); ?></strong></li>
</ul>

<p class="mt-2 text-danger"><strong><?php echo __("adm_warning"); ?></strong></p>
<p class="mb-0"><?php echo __("adm_sec_whitelist_warning"); ?></p>
</div>
</div>

</div>
</div>

<!-- Block feature toggle JS -->
<script>
(function() {
    const enabledSwitch = document.getElementById('ip_block_enabled');
    const settingsFieldset = document.getElementById('security_settings_fieldset');
    const bruteforceSwitch = document.getElementById('bruteforce_enabled');
    const bruteforceSettings = document.getElementById('bruteforce_settings');
    
    // 체크박스와 연동할 필드 그룹 매핑
    const modeFieldMapping = {
        'mode_block_countries': 'blocked_countries_group',
        'mode_allow_countries': 'allowed_countries_group',
        'mode_block_ips': 'blocked_ips_group',
        'mode_allow_ips': 'allowed_ips_group'
    };
    
    // 필드 그룹 활성화/비활성화
    function setFieldGroupState(groupId, enabled) {
        const group = document.getElementById(groupId);
        if (!group) return;
        
        const inputs = group.querySelectorAll('input, textarea, select');
        inputs.forEach(function(input) {
            input.disabled = !enabled;
        });
        
        group.style.opacity = enabled ? '1' : '0.4';
    }
    
    // 차단 모드 체크박스 상태에 따라 필드 활성화/비활성화
    function updateModeFields() {
        const mainEnabled = enabledSwitch.checked;
        
        Object.keys(modeFieldMapping).forEach(function(checkboxId) {
            const checkbox = document.getElementById(checkboxId);
            const groupId = modeFieldMapping[checkboxId];
            
            if (checkbox) {
                // 메인 스위치가 꺼져있으면 모두 비활성화
                // 메인 스위치가 켜져있으면 개별 체크박스 상태에 따라 결정
                const fieldEnabled = mainEnabled && checkbox.checked;
                setFieldGroupState(groupId, fieldEnabled);
            }
        });
    }
    
    // 전체 설정 영역 활성화/비활성화 (체크박스 자체 제외)
    function toggleSecuritySettings() {
        if (!settingsFieldset) return;
        
        const isEnabled = enabledSwitch.checked;
        
        // 체크박스들은 항상 활성화 (선택 가능하게)
        const modeCheckboxes = settingsFieldset.querySelectorAll('.security-mode-check');
        modeCheckboxes.forEach(function(cb) {
            cb.disabled = !isEnabled;
        });
        
        // 화이트리스트, 차단 메시지 등 기본 설정 필드
        const otherInputs = settingsFieldset.querySelectorAll('#whitelist_ips_group input, #whitelist_ips_group textarea, [name="block_message"], [name="cache_hours"], #log_enabled');
        otherInputs.forEach(function(input) {
            input.disabled = !isEnabled;
        });
        
        // 화이트리스트 그룹 시각적 상태
        const whitelistGroup = document.getElementById('whitelist_ips_group');
        if (whitelistGroup) {
            whitelistGroup.style.opacity = isEnabled ? '1' : '0.4';
        }
        
        // 모드별 필드 상태 업데이트
        updateModeFields();
        
        // 전체 fieldset 시각적 피드백
        settingsFieldset.style.opacity = isEnabled ? '1' : '0.5';
    }
    
    // 이벤트 리스너 등록
    if (enabledSwitch && settingsFieldset) {
        // 메인 활성화 스위치
        enabledSwitch.addEventListener('change', toggleSecuritySettings);
        
        // 각 모드 체크박스
        Object.keys(modeFieldMapping).forEach(function(checkboxId) {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                checkbox.addEventListener('change', updateModeFields);
            }
        });
        
        // 초기 상태 적용
        toggleSecuritySettings();
    }
    
    // 브루트포스 설정 토글
    function toggleBruteforceSettings() {
        if (!bruteforceSettings || !bruteforceSwitch) return;
        const isEnabled = bruteforceSwitch.checked;
        const inputs = bruteforceSettings.querySelectorAll('input');
        inputs.forEach(function(input) {
            input.disabled = !isEnabled;
        });
        bruteforceSettings.style.opacity = isEnabled ? '1' : '0.4';
    }
    
    if (bruteforceSwitch && bruteforceSettings) {
        bruteforceSwitch.addEventListener('change', toggleBruteforceSettings);
        toggleBruteforceSettings();
    }
    
    // GeoIP 소스 선택 토글
    const geoipRadios = document.querySelectorAll('input[name="geoip_source"]');
    const geoipMmdbGroup = document.getElementById('geoip_mmdb_group');
    const geoipDatGroup = document.getElementById('geoip_dat_group');
    const geoipCsvGroup = document.getElementById('geoip_csv_group');
    
    function toggleGeoipFields() {
        const selectedSource = document.querySelector('input[name="geoip_source"]:checked');
        if (!selectedSource) return;
        
        if (geoipMmdbGroup) {
            geoipMmdbGroup.style.display = selectedSource.value === 'mmdb' ? 'block' : 'none';
        }
        if (geoipDatGroup) {
            geoipDatGroup.style.display = selectedSource.value === 'dat' ? 'block' : 'none';
        }
        if (geoipCsvGroup) {
            geoipCsvGroup.style.display = selectedSource.value === 'csv' ? 'block' : 'none';
        }
    }
    
    geoipRadios.forEach(function(radio) {
        radio.addEventListener('change', toggleGeoipFields);
    });
    toggleGeoipFields();
})();

// ✅ 국가 코드 버튼 클릭 기능
(function() {
    const blockedInput = document.querySelector('input[name="blocked_countries"]');
    const allowedInput = document.querySelector('input[name="allowed_countries"]');
    const modeBlockCountries = document.getElementById('mode_block_countries');
    const modeAllowCountries = document.getElementById('mode_allow_countries');
    
    function getTargetInput() {
        // 허용 모드가 체크되어 있으면 허용 입력창, 아니면 차단 입력창
        if (modeAllowCountries && modeAllowCountries.checked) {
            return allowedInput;
        }
        return blockedInput;
    }
    
    function getCountryList(input) {
        if (!input || !input.value.trim()) return [];
        return input.value.toUpperCase().split(',').map(s => s.trim()).filter(s => s);
    }
    
    function setCountryList(input, list) {
        if (!input) return;
        input.value = list.join(',');
    }
    
    function updateButtonStates() {
        const blockedList = getCountryList(blockedInput);
        const allowedList = getCountryList(allowedInput);
        
        document.querySelectorAll('.country-select-btn').forEach(btn => {
            const code = btn.dataset.code;
            const isBlocked = blockedList.includes(code);
            const isAllowed = allowedList.includes(code);
            
            // 기본 스타일로 리셋
            btn.style.background = '#fff';
            btn.style.borderColor = '#dee2e6';
            const strong = btn.querySelector('strong');
            const span = btn.querySelector('span');
            
            if (isBlocked) {
                // 차단 목록 - 빨간색
                btn.style.background = '#dc3545';
                btn.style.borderColor = '#dc3545';
                if (strong) strong.style.color = '#fff';
                if (span) span.style.color = '#fff';
            } else if (isAllowed) {
                // 허용 목록 - 파란색
                btn.style.background = '#007bff';
                btn.style.borderColor = '#007bff';
                if (strong) strong.style.color = '#fff';
                if (span) span.style.color = '#fff';
            } else {
                // 선택 안됨 - 기본
                if (strong) strong.style.color = '#007bff';
                if (span) span.style.color = '#6c757d';
            }
        });
    }
    
    document.querySelectorAll('.country-select-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const code = this.dataset.code;
            const targetInput = getTargetInput();
            const list = getCountryList(targetInput);
            
            const idx = list.indexOf(code);
            if (idx > -1) {
                list.splice(idx, 1);
            } else {
                list.push(code);
            }
            
            setCountryList(targetInput, list);
            updateButtonStates();
        });
    });
    
    // 입력창 변경 시 버튼 상태 업데이트
    if (blockedInput) blockedInput.addEventListener('input', updateButtonStates);
    if (allowedInput) allowedInput.addEventListener('input', updateButtonStates);
    
    // 초기 상태 적용
    updateButtonStates();
    
    // 모드 변경 시에도 업데이트
    if (modeBlockCountries) modeBlockCountries.addEventListener('change', updateButtonStates);
    if (modeAllowCountries) modeAllowCountries.addEventListener('change', updateButtonStates);
})();

function toggleAllCountryButtons(select) {
    // 모두 선택 해제
    document.querySelector('input[name="blocked_countries"]').value = '';
    document.querySelector('input[name="allowed_countries"]').value = '';
    document.querySelectorAll('.country-select-btn').forEach(btn => {
        btn.style.background = '#fff';
        btn.style.borderColor = '#dee2e6';
        const strong = btn.querySelector('strong');
        const span = btn.querySelector('span');
        if (strong) strong.style.color = '#007bff';
        if (span) span.style.color = '#6c757d';
    });
}

// ✅ 차단 로그 페이지네이션 및 삭제
let blockLogPage = 1;
let blockLogPages = <?php echo $blockLogs['pages'] ?? 1; ?>;

function loadBlockLogs(page) {
    if (page === 'prev') page = Math.max(1, blockLogPage - 1);
    else if (page === 'next') page = Math.min(blockLogPages, blockLogPage + 1);
    else page = parseInt(page) || 1;
    
    const dateFrom = document.getElementById('block_log_from').value;
    const dateTo = document.getElementById('block_log_to').value;
    
    fetch(`admin.php?ajax_security_logs=1&type=block&page=${page}&date_from=${dateFrom}&date_to=${dateTo}`)
        .then(r => r.json())
        .then(data => {
            blockLogPage = page;
            blockLogPages = data.pages || 1;
            
            document.getElementById('block_log_page').textContent = page;
            document.getElementById('block_log_pages').textContent = blockLogPages;
            document.getElementById('block_log_count').textContent = data.total || 0;
            
            const tbody = document.getElementById('block_log_tbody');
            if (!data.logs || data.logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><?php echo __("adm_sec_no_block_logs"); ?></td></tr>';
            } else {
                tbody.innerHTML = data.logs.map((log, idx) => `
                    <tr>
                        <td><input type="checkbox" class="block-log-check" value="${idx}"></td>
                        <td>${(log.time || '').substring(5)}</td>
                        <td><code>${log.ip || ''}</code></td>
                        <td>${log.country || ''}</td>
                        <td><small>${(log.reason || '').split(':')[0]}</small></td>
                    </tr>
                `).join('');
            }
        });
}

function resetBlockLogFilter() {
    document.getElementById('block_log_from').value = '';
    document.getElementById('block_log_to').value = '';
    loadBlockLogs(1);
}

function toggleAllBlockLogChecks(master) {
    document.querySelectorAll('.block-log-check').forEach(cb => cb.checked = master.checked);
}

function deleteSelectedBlockLogs() {
    const indices = Array.from(document.querySelectorAll('.block-log-check:checked')).map(cb => parseInt(cb.value));
    if (indices.length === 0) return alert('<?php echo __("adm_sec_no_selection"); ?>');
    if (!confirm(`${indices.length} <?php echo __('adm_sec_confirm_delete_n'); ?>`)) return;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_delete_logs=1&type=block&action=selected&indices=${JSON.stringify(indices)}&csrf_token=<?php echo generate_csrf_token(); ?>`
    }).then(r => r.json()).then(data => {
        alert(data.message);
        loadBlockLogs(blockLogPage);
    });
}

function deleteBlockLogsByRange() {
    const dateFrom = document.getElementById('block_log_from').value;
    const dateTo = document.getElementById('block_log_to').value;
    if (!dateFrom) return alert('<?php echo __("adm_sec_select_start_date"); ?>');
    if (!confirm(i18n_adm.confirm_delete_period_logs)) return;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_delete_logs=1&type=block&action=range&date_from=${dateFrom}&date_to=${dateTo}&csrf_token=<?php echo generate_csrf_token(); ?>`
    }).then(r => r.json()).then(data => {
        alert(data.message);
        loadBlockLogs(1);
    });
}

function deleteAllBlockLogs() {
    if (!confirm('<?php echo __("adm_sec_confirm_delete_all_block"); ?>')) return;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_delete_logs=1&type=block&action=all&csrf_token=<?php echo generate_csrf_token(); ?>`
    }).then(r => r.json()).then(data => {
        alert(data.message);
        loadBlockLogs(1);
    });
}

// ✅ 브루트포스 로그 페이지네이션 및 삭제
let bfLogPage = 1;
let bfLogPages = <?php echo $bruteforceLogs['pages'] ?? 1; ?>;

function loadBfLogs(page) {
    if (page === 'prev') page = Math.max(1, bfLogPage - 1);
    else if (page === 'next') page = Math.min(bfLogPages, bfLogPage + 1);
    else page = parseInt(page) || 1;
    
    const dateFrom = document.getElementById('bf_log_from').value;
    const dateTo = document.getElementById('bf_log_to').value;
    
    fetch(`admin.php?ajax_security_logs=1&type=bruteforce&page=${page}&date_from=${dateFrom}&date_to=${dateTo}`)
        .then(r => r.json())
        .then(data => {
            bfLogPage = page;
            bfLogPages = data.pages || 1;
            
            document.getElementById('bf_log_page').textContent = page;
            document.getElementById('bf_log_pages').textContent = bfLogPages;
            document.getElementById('bf_log_count').textContent = data.total || 0;
            
            const tbody = document.getElementById('bf_log_tbody');
            if (!data.logs || data.logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><?php echo __("adm_sec_no_bf_logs"); ?></td></tr>';
            } else {
                tbody.innerHTML = data.logs.map((log, idx) => `
                    <tr>
                        <td><input type="checkbox" class="bf-log-check" value="${idx}"></td>
                        <td>${(log.time || '').substring(5, 16)}</td>
                        <td><code>${log.ip || ''}</code></td>
                        <td><small>${log.username || '-'}</small></td>
                        <td><span class="badge badge-danger">${log.attempts || 0}</span></td>
                    </tr>
                `).join('');
            }
        });
}

function resetBfLogFilter() {
    document.getElementById('bf_log_from').value = '';
    document.getElementById('bf_log_to').value = '';
    loadBfLogs(1);
}

function toggleAllBfLogChecks(master) {
    document.querySelectorAll('.bf-log-check').forEach(cb => cb.checked = master.checked);
}

function deleteSelectedBfLogs() {
    const indices = Array.from(document.querySelectorAll('.bf-log-check:checked')).map(cb => parseInt(cb.value));
    if (indices.length === 0) return alert('<?php echo __("adm_sec_no_selection"); ?>');
    if (!confirm(`${indices.length} <?php echo __('adm_sec_confirm_delete_n'); ?>`)) return;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_delete_logs=1&type=bruteforce&action=selected&indices=${JSON.stringify(indices)}&csrf_token=<?php echo generate_csrf_token(); ?>`
    }).then(r => r.json()).then(data => {
        alert(data.message);
        loadBfLogs(bfLogPage);
    });
}

function deleteBfLogsByRange() {
    const dateFrom = document.getElementById('bf_log_from').value;
    const dateTo = document.getElementById('bf_log_to').value;
    if (!dateFrom) return alert('<?php echo __("adm_sec_select_start_date"); ?>');
    if (!confirm(i18n_adm.confirm_delete_period_logs)) return;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_delete_logs=1&type=bruteforce&action=range&date_from=${dateFrom}&date_to=${dateTo}&csrf_token=<?php echo generate_csrf_token(); ?>`
    }).then(r => r.json()).then(data => {
        alert(data.message);
        loadBfLogs(1);
    });
}

function deleteAllBfLogs() {
    if (!confirm('<?php echo __("adm_sec_confirm_delete_all_bf"); ?>')) return;
    
    fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_delete_logs=1&type=bruteforce&action=all&csrf_token=<?php echo generate_csrf_token(); ?>`
    }).then(r => r.json()).then(data => {
        alert(data.message);
        loadBfLogs(1);
    });
}
</script>

</div>
</div>

<!-- SMTP settings -->
<div class="tab-pane fade" id="smtp">
<div class="container-fluid">

<?php
$smtp = get_app_settings('smtp', []);
$smtp_enabled = $smtp['enabled'] ?? false;
$smtp_host = $smtp['host'] ?? '';
$smtp_port = $smtp['port'] ?? 587;
$smtp_encryption = $smtp['encryption'] ?? 'tls';
$smtp_username = $smtp['username'] ?? '';
$smtp_from_email = $smtp['from_email'] ?? '';
$smtp_from_name = $smtp['from_name'] ?? 'myComix';
?>

<div class="card mb-3">
<div class="card-header bg-primary text-white"><?php echo __("adm_card_smtp_settings"); ?></div>
<div class="card-body">
    <div class="alert alert-info py-2 mb-3" style="font-size:13px;">
        <strong><?php echo __("adm_smtp_purpose_title"); ?></strong> | <?php echo __("adm_smtp_purpose_desc"); ?><br>
        <?php echo __("adm_smtp_providers"); ?>
    </div>
    
    <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="mode" value="smtp_change">
    
    <div class="form-group">
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="smtp_enabled" name="smtp_enabled" <?php echo $smtp_enabled ? 'checked' : ''; ?>>
            <label class="custom-control-label" for="smtp_enabled"><strong><?php echo __("adm_smtp_enable"); ?></strong></label>
        </div>
        <small class="text-muted"><?php echo __("adm_smtp_disabled_note"); ?></small>
    </div>
    
    <hr>
    
    <div class="row">
        <div class="col-md-8">
            <div class="form-group">
                <label><strong><?php echo __("adm_smtp_host"); ?></strong></label>
                <input type="text" class="form-control" name="smtp_host" value="<?php echo h($smtp_host); ?>" placeholder="<?php echo __('adm_ph_smtp_host'); ?>">
                <small class="text-muted">
                    Gmail: smtp.gmail.com | Naver: smtp.naver.com | Daum: smtp.daum.net
                </small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label><strong><?php echo __("adm_th_port"); ?></strong></label>
                <input type="number" class="form-control" name="smtp_port" value="<?php echo (int)$smtp_port; ?>" placeholder="587">
                <small class="text-muted">TLS: 587 | SSL: 465</small>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label><strong><?php echo __("adm_smtp_encryption_label"); ?></strong></label>
        <div class="btn-group btn-group-toggle d-block" data-toggle="buttons">
            <label class="btn btn-outline-secondary <?php echo $smtp_encryption === 'tls' ? 'active' : ''; ?>">
                <input type="radio" name="smtp_encryption" value="tls" <?php echo $smtp_encryption === 'tls' ? 'checked' : ''; ?>> TLS (<?php echo __("adm_recommended"); ?>)
            </label>
            <label class="btn btn-outline-secondary <?php echo $smtp_encryption === 'ssl' ? 'active' : ''; ?>">
                <input type="radio" name="smtp_encryption" value="ssl" <?php echo $smtp_encryption === 'ssl' ? 'checked' : ''; ?>> SSL
            </label>
            <label class="btn btn-outline-secondary <?php echo $smtp_encryption === 'none' ? 'active' : ''; ?>">
                <input type="radio" name="smtp_encryption" value="none" <?php echo $smtp_encryption === 'none' ? 'checked' : ''; ?>> <?php echo __('adm_none'); ?>
            </label>
        </div>
    </div>
    
    <hr>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><strong><?php echo __("adm_smtp_username"); ?></strong></label>
                <input type="text" class="form-control" name="smtp_username" value="<?php echo h($smtp_username); ?>" placeholder="<?php echo __('adm_ph_smtp_user'); ?>">
                <small class="text-muted"><?php echo __("adm_smtp_email_note"); ?></small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label><strong><?php echo __("adm_smtp_password"); ?></strong> 
                    <?php if (!empty($smtp['password'])): ?>
                    <span class="badge badge-success"><?php echo __("adm_smtp_saved"); ?></span>
                    <?php else: ?>
                    <span class="badge badge-warning"><?php echo __("adm_smtp_not_set"); ?></span>
                    <?php endif; ?>
                </label>
                <input type="password" class="form-control" name="smtp_password" autocomplete="new-password" placeholder="<?php echo !empty($smtp['password']) ? __('adm_ph_smtp_pw_change') : __('adm_ph_smtp_pw_enter'); ?>">
                <small class="text-muted"><?php echo __("adm_smtp_gmail_note"); ?></small>
            </div>
        </div>
    </div>
    
    <hr>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><strong><?php echo __("adm_smtp_sender"); ?></strong></label>
                <input type="email" class="form-control" name="smtp_from_email" value="<?php echo h($smtp_from_email); ?>" placeholder="e.g. noreply@example.com">
                <small class="text-muted"><?php echo __("adm_smtp_sender_note"); ?></small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label><strong><?php echo __("adm_smtp_sender_name"); ?></strong></label>
                <input type="text" class="form-control" name="smtp_from_name" value="<?php echo h($smtp_from_name); ?>" placeholder="myComix">
            </div>
        </div>
    </div>
    
    <button type="submit" class="btn btn-primary btn-block"><?php echo __("adm_btn_save_smtp"); ?></button>
    </form>
</div>
</div>

<!-- Test email send -->
<div class="card mb-3">
<div class="card-header bg-success text-white"><?php echo __("adm_card_smtp_test"); ?></div>
<div class="card-body">
    <form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="mode" value="smtp_test">
    
    <div class="form-group">
        <label><strong><?php echo __("adm_smtp_test_email"); ?></strong></label>
        <div class="input-group">
            <input type="email" class="form-control" name="test_email" placeholder="test@example.com" required>
            <div class="input-group-append">
                <button type="submit" class="btn btn-success"><?php echo __("adm_btn_test_smtp"); ?></button>
            </div>
        </div>
        <small class="text-muted"><?php echo __("adm_smtp_save_first_note"); ?></small>
    </div>
    </form>
</div>
</div>

<!-- SMTP setup guide -->
<div class="card mb-3">
<div class="card-header bg-secondary text-white"><?php echo __("adm_card_smtp_guide"); ?></div>
<div class="card-body p-0">
<table class="table table-sm table-bordered mb-0" style="font-size:13px;">
<thead class="thead-light">
<tr><th><?php echo __("adm_th_service"); ?></th><th><?php echo __("adm_smtp_host"); ?></th><th><?php echo __("adm_th_port"); ?></th><th><?php echo __("adm_th_encryption"); ?></th><th><?php echo __("adm_th_note"); ?></th></tr>
</thead>
<tbody>
<tr><td><strong>Gmail</strong></td><td>smtp.gmail.com</td><td>587</td><td>TLS</td><td><?php echo __("adm_smtp_guide_gmail"); ?></td></tr>
<tr><td><strong>Naver</strong></td><td>smtp.naver.com</td><td>587</td><td>TLS</td><td><?php echo __("adm_smtp_guide_naver"); ?></td></tr>
<tr><td><strong>Daum/Kakao</strong></td><td>smtp.daum.net</td><td>465</td><td>SSL</td><td><?php echo __("adm_smtp_guide_daum"); ?></td></tr>
<tr><td><strong>Outlook</strong></td><td>smtp.office365.com</td><td>587</td><td>TLS</td><td><?php echo __("adm_smtp_guide_outlook"); ?></td></tr>
</tbody>
</table>
</div>
</div>

<div class="alert alert-warning" style="font-size:13px;">
    <strong><?php echo __("adm_smtp_gmail_warning"); ?></strong><br>
    1. In your Google account, <strong><?php echo __("adm_user_2fa"); ?></strong> <?php echo __('adm_smtp_step1'); ?><br>
    2. <a href="https://myaccount.google.com/apppasswords" target="_blank"><?php echo __("adm_smtp_app_pw"); ?></a>를 생성하여 SMTP 비밀번호로 사용하세요.<br>
    3. Regular passwords are blocked by security policy.
</div>

</div>
</div>

<!-- Find duplicates -->
<div class="tab-pane fade" id="duplicates">
<div class="container-fluid">

<div class="card mb-3">
<div class="card-header bg-info text-white">
    🔍 <?php echo __('adm_dup_title'); ?>
</div>
<div class="card-body">
    <div class="alert alert-warning py-2 mb-3" style="font-size:13px;">
        <strong><?php echo __("adm_dup_guide"); ?></strong> | 
        <?php echo __("adm_dup_refresh_note"); ?>
    </div>
    
    <form id="duplicateSearchForm">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <div class="row">
            <!-- Scan mode -->
            <div class="col-md-4 mb-3">
                <div class="border rounded p-3 h-100">
                    <label class="font-weight-bold mb-2"><?php echo __("adm_dup_scan_mode"); ?></label>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="mode_fast" name="search_mode" value="fast" class="custom-control-input" checked>
                        <label class="custom-control-label" for="mode_fast"><?php echo __("adm_label_quick_scan"); ?></label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="mode_precise" name="search_mode" value="precise" class="custom-control-input">
                        <label class="custom-control-label" for="mode_precise"><?php echo __("adm_dup_precise_scan"); ?></label>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <?php echo __("adm_dup_quick_desc"); ?><br>
                        <?php echo __("adm_dup_precise_desc"); ?>
                    </small>
                    <small class="text-danger d-block mt-1">
                        <strong><?php echo __("adm_dup_quick_warning"); ?></strong>
                    </small>
                </div>
            </div>
            
            <!-- File types -->
            <div class="col-md-4 mb-3">
                <div class="border rounded p-3 h-100">
                    <label class="font-weight-bold mb-2"><?php echo __("adm_dup_file_type"); ?></label>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" id="type_archive" name="types[]" value="archive" class="custom-control-input" checked>
                        <label class="custom-control-label" for="type_archive"><?php echo __("adm_dup_type_archive"); ?></label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" id="type_video" name="types[]" value="video" class="custom-control-input">
                        <label class="custom-control-label" for="type_video"><?php echo __("adm_dup_type_video"); ?></label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" id="type_image" name="types[]" value="image" class="custom-control-input">
                        <label class="custom-control-label" for="type_image"><?php echo __("adm_dup_type_image"); ?></label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" id="type_json" name="types[]" value="json" class="custom-control-input">
                        <label class="custom-control-label" for="type_json">📋 JSON</label>
                    </div>
                </div>
            </div>
            
            <!-- Search scope -->
            <div class="col-md-4 mb-3">
                <div class="border rounded p-3 h-100">
                    <label class="font-weight-bold mb-2"><?php echo __("adm_dup_search_scope"); ?></label>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="scope_all" name="scope" value="all" class="custom-control-input" checked>
                        <label class="custom-control-label" for="scope_all"><?php echo __("adm_dup_all_folders"); ?></label>
                    </div>
                    <?php foreach ($base_dirs as $idx => $bdir): ?>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="scope_<?php echo $idx; ?>" name="scope" value="<?php echo $idx; ?>" class="custom-control-input">
                        <label class="custom-control-label" for="scope_<?php echo $idx; ?>">📁 <?php echo h(basename($bdir)); ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <button type="button" id="startDuplicateSearch" class="btn btn-primary btn-lg">
                🔍 <?php echo __('adm_dup_start_search'); ?>
            </button>
            <button type="button" id="cancelDuplicateSearch" class="btn btn-secondary btn-lg" style="display:none;">
                ❌ <?php echo __('adm_dup_cancel'); ?>
            </button>
        </div>
    </form>
    
    <!-- Progress -->
    <div id="duplicateProgress" class="mt-3" style="display:none;">
        <div class="progress" style="height: 25px;">
            <div id="duplicateProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
        </div>
        <small id="duplicateProgressText" class="text-muted"><?php echo __('adm_loading'); ?></small>
    </div>
    
    <!-- Results -->
    <div id="duplicateResults" class="mt-3" style="display:none;">
        <hr>
        <h5><?php echo __("adm_heading_search_results"); ?></h5>
        <div id="duplicateStats" class="alert alert-info"></div>
        
        <!-- Result filter -->
        <div class="mb-3">
            <input type="text" id="resultFilter" class="form-control form-control-sm" placeholder="<?php echo __('adm_dup_filter_results'); ?>" style="max-width:300px;">
        </div>
        
        <div id="duplicateList"></div>
        
        <div id="duplicateActions" class="mt-3" style="display:none;">
            <button type="button" id="selectAllDuplicates" class="btn btn-sm btn-outline-secondary"><?php echo __("adm_btn_select_all"); ?></button>
            <button type="button" id="deselectAllDuplicates" class="btn btn-sm btn-outline-secondary"><?php echo __("adm_btn_deselect_all"); ?></button>
            <button type="button" id="selectAllExceptFirst" class="btn btn-sm btn-outline-danger"><?php echo __("adm_btn_select_except_first"); ?></button>
            <button type="button" id="deleteSelectedDuplicates" class="btn btn-sm btn-danger"><?php echo __("adm_btn_delete_selected_files"); ?></button>
        </div>
    </div>
</div>
</div>

<script>
(function() {
    let isSearching = false;
    let isCancelled = false;
    
    document.getElementById('startDuplicateSearch').addEventListener('click', function() {
        if (isSearching) return;
        startSearch();
    });
    
    document.getElementById('cancelDuplicateSearch').addEventListener('click', function() {
        isCancelled = true;
        isSearching = false;
        
        // 서버에 취소 요청
        const formData = new FormData();
        formData.append('action', 'duplicate_cancel');
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        fetch('admin.php', { method: 'POST', body: formData });
        
        document.getElementById('startDuplicateSearch').style.display = '';
        document.getElementById('cancelDuplicateSearch').style.display = 'none';
        document.getElementById('duplicateProgressText').textContent = '<?php echo __("adm_cancelled"); ?>';
    });
    
    document.getElementById('selectAllDuplicates').addEventListener('click', function() {
        // 모든 파일 선택
        if (!duplicateData || !duplicateData.groups) return;
        
        checkedFiles.clear();
        duplicateData.groups.forEach(group => {
            group.files.forEach(file => {
                checkedFiles.add(file.path);
            });
        });
        
        // 현재 페이지 체크박스 업데이트
        document.querySelectorAll('#duplicateList .dup-checkbox').forEach(cb => {
            cb.checked = true;
        });
        
        updateSelectedCount();
    });
    
    document.getElementById('deselectAllDuplicates').addEventListener('click', function() {
        document.querySelectorAll('#duplicateList input[type="checkbox"]').forEach(cb => cb.checked = false);
        checkedFiles.clear();
        updateSelectedCount();
    });
    
    document.getElementById('selectAllExceptFirst').addEventListener('click', function() {
        // 모든 그룹에서 첫번째 제외하고 선택
        if (!duplicateData || !duplicateData.groups) return;
        
        checkedFiles.clear();
        duplicateData.groups.forEach((group, idx) => {
            group.files.forEach((file, fIdx) => {
                if (fIdx !== 0) {  // 첫번째가 아닌 파일만
                    checkedFiles.add(file.path);
                }
            });
        });
        
        // 현재 페이지 체크박스 업데이트
        document.querySelectorAll('#duplicateList .dup-checkbox').forEach(cb => {
            cb.checked = checkedFiles.has(cb.value);
        });
        
        updateSelectedCount();
    });
    
    document.getElementById('deleteSelectedDuplicates').addEventListener('click', function() {
        // checkedFiles Set에서 선택된 파일 가져오기 (페이지네이션 대응)
        const selected = Array.from(checkedFiles);
        
        if (selected.length === 0) {
            alert('<?php echo __("adm_dup_select_files"); ?>');
            return;
        }
        
        if (!confirm(selected.length + ' <?php echo __("adm_dup_confirm_delete"); ?>')) {
            return;
        }
        
        deleteFiles(selected);
    });
    
    async function startSearch() {
        isSearching = true;
        isCancelled = false;
        
        document.getElementById('startDuplicateSearch').style.display = 'none';
        document.getElementById('cancelDuplicateSearch').style.display = '';
        document.getElementById('duplicateProgress').style.display = '';
        document.getElementById('duplicateResults').style.display = 'none';
        updateProgress(0, '<?php echo __("adm_status_collecting"); ?>');
        
        try {
            // Step 1: 파일 목록 수집
            const collectData = new FormData(document.getElementById('duplicateSearchForm'));
            collectData.append('action', 'duplicate_collect');
            
            const collectResp = await fetch('admin.php', {
                method: 'POST',
                body: collectData
            });
            const collectResult = await collectResp.json();
            
            if (collectResult.error) {
                throw new Error(collectResult.error);
            }
            
            if (isCancelled) return;
            
            updateProgress(15, `Files: ${collectResult.total_files.toLocaleString()}, Candidates: ${collectResult.candidates.toLocaleString()}`);
            
            // Step 2: 배치별 해시 계산
            let done = false;
            while (!done && !isCancelled) {
                const processData = new FormData();
                processData.append('action', 'duplicate_process');
                processData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                
                const processResp = await fetch('admin.php', {
                    method: 'POST',
                    body: processData
                });
                const processResult = await processResp.json();
                
                if (processResult.error) {
                    throw new Error(processResult.error);
                }
                
                if (processResult.done) {
                    done = true;
                    displayResults(processResult);
                } else {
                    updateProgress(
                        processResult.progress,
                        `Hashing... (${processResult.processed.toLocaleString()}/${processResult.total.toLocaleString()})`
                    );
                }
            }
            
        } catch (err) {
            alert('Error: ' + err.message);
        } finally {
            isSearching = false;
            document.getElementById('startDuplicateSearch').style.display = '';
            document.getElementById('cancelDuplicateSearch').style.display = 'none';
        }
    }
    
    function updateProgress(pct, status) {
        document.getElementById('duplicateProgressBar').style.width = pct + '%';
        document.getElementById('duplicateProgressBar').textContent = pct + '%';
        document.getElementById('duplicateProgressText').textContent = status;
    }
    
    // 중복 검색 결과 저장 (페이지네이션용)
    let duplicateData = null;
    let duplicatePage = 1;
    const duplicatePerPage = 20;
    let checkedFiles = new Set(); // 선택된 파일 추적
    
    function displayResults(data) {
        document.getElementById('duplicateProgress').style.display = 'none';
        document.getElementById('duplicateResults').style.display = '';
        
        const stats = document.getElementById('duplicateStats');
        const list = document.getElementById('duplicateList');
        const actions = document.getElementById('duplicateActions');
        
        const modeLabel = data.search_mode === 'precise' ? '🎯 Precise' : '⚡ Quick';
        const modeDesc = data.search_mode === 'precise' 
            ? 'Same size + filename + MD5' 
            : 'Same size + MD5 (any filename)';
        const modeWarning = data.search_mode === 'precise'
            ? ''
            : '<br><strong class="text-danger">⚠️ Files with different names but same content are shown as duplicates!</strong>';
        
        stats.innerHTML = `
            <strong><?php echo __("adm_dup_search_done"); ?></strong> <span class="badge badge-secondary">${modeLabel}</span><br>
            <small class="text-muted">Condition: ${modeDesc}</small>${modeWarning}<br>
            Total: ${data.total_files.toLocaleString()}/ 
            Groups: ${data.duplicate_groups.toLocaleString()}/ 
            Duplicates: ${data.duplicate_files.toLocaleString()}/ 
            Saveable: ${data.saveable_size}
        `;
        
        if (data.duplicate_groups === 0) {
            list.innerHTML = '<div class="alert alert-success">No duplicates found! 👍</div>';
            actions.style.display = 'none';
            return;
        }
        
        // 결과 저장 및 초기화
        duplicateData = data;
        duplicatePage = 1;
        checkedFiles.clear();
        renderDuplicatePage();
        actions.style.display = '';
    }
    
    function renderDuplicatePage() {
        if (!duplicateData) return;
        
        const list = document.getElementById('duplicateList');
        const totalGroups = duplicateData.groups.length;
        const totalPages = Math.ceil(totalGroups / duplicatePerPage);
        const startIdx = (duplicatePage - 1) * duplicatePerPage;
        const endIdx = Math.min(startIdx + duplicatePerPage, totalGroups);
        
        let html = '';
        
        // 선택된 파일 수 표시 및 페이지네이션 (상단)
        html += `<div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted">Selected: <strong id="selectedCount">${checkedFiles.size}</strong></span>
            <span class="text-muted">Page ${duplicatePage} / ${totalPages} (Group ${startIdx + 1}-${endIdx} / ${totalGroups})</span>
        </div>`;
        
        if (totalPages > 1) {
            html += renderDuplicatePagination(totalPages);
        }
        
        // 현재 페이지 그룹들
        for (let idx = startIdx; idx < endIdx; idx++) {
            const group = duplicateData.groups[idx];
            const modeInfo = duplicateData.search_mode === 'precise'
                ? `📏 ${group.size_formatted} | 📝 ${group.filename || '(filename)'} | 🔑 ${group.hash.substring(0, 8)}...`
                : `📏 ${group.size_formatted} | 🔑 ${group.hash.substring(0, 8)}...`;
            
            html += `<div class="card mb-2">
                <div class="card-header py-1 bg-light d-flex justify-content-between align-items-center">
                    <span>
                        <strong>Group ${idx + 1}</strong> <small class="text-muted">(${group.files.length} <?php echo __('adm_sys_unit_files'); ?>)</small>
                        <br><small class="text-info">${modeInfo}</small>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-danger select-all-except-first" data-group="${idx}">
                        Select All Except First
                    </button>
                </div>
                <div class="card-body py-2">`;
            
            group.files.forEach((file, fIdx) => {
                const isFirst = fIdx === 0;
                const isChecked = checkedFiles.has(file.path);
                html += `<div class="form-check">
                    <input type="checkbox" class="form-check-input dup-checkbox dup-group-${idx}" id="dup_${idx}_${fIdx}" 
                           value="${escapeHtml(file.path)}" data-first="${isFirst}" ${isChecked ? 'checked' : ''}>
                    <label class="form-check-label ${isFirst ? 'text-primary font-weight-bold' : ''}" for="dup_${idx}_${fIdx}">
                        ${isFirst ? '🏠 [Original] ' : '📋 '}${escapeHtml(file.path)}
                        <small class="text-muted">(${file.mtime})</small>
                    </label>
                </div>`;
            });
            
            html += `</div></div>`;
        }
        
        // 페이지네이션 (하단)
        if (totalPages > 1) {
            html += renderDuplicatePagination(totalPages);
        }
        
        list.innerHTML = html;
        
        // 체크박스 변경 이벤트 (상태 추적)
        document.querySelectorAll('.dup-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                if (this.checked) {
                    checkedFiles.add(this.value);
                } else {
                    checkedFiles.delete(this.value);
                }
                updateSelectedCount();
            });
        });
        
        // "Select All Except First" 버튼 이벤트
        document.querySelectorAll('.select-all-except-first').forEach(btn => {
            btn.addEventListener('click', function() {
                const groupIdx = this.dataset.group;
                document.querySelectorAll(`.dup-group-${groupIdx}`).forEach(cb => {
                    const shouldCheck = cb.dataset.first !== 'true';
                    cb.checked = shouldCheck;
                    if (shouldCheck) {
                        checkedFiles.add(cb.value);
                    } else {
                        checkedFiles.delete(cb.value);
                    }
                });
                updateSelectedCount();
            });
        });
        
        // 페이지네이션 클릭 이벤트
        document.querySelectorAll('.dup-page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.dataset.page);
                if (page && page !== duplicatePage) {
                    duplicatePage = page;
                    renderDuplicatePage();
                    // 스크롤 위로
                    document.getElementById('duplicateList').scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    }
    
    function updateSelectedCount() {
        const countEl = document.getElementById('selectedCount');
        if (countEl) countEl.textContent = checkedFiles.size;
    }
    
    function renderDuplicatePagination(totalPages) {
        let html = '<nav class="my-2"><ul class="pagination pagination-sm justify-content-center mb-0">';
        
        // 이전 버튼
        html += `<li class="page-item ${duplicatePage <= 1 ? 'disabled' : ''}">
            <a class="page-link dup-page-link" href="#" data-page="${duplicatePage - 1}">&laquo;</a>
        </li>`;
        
        // 페이지 번호
        let startPage = Math.max(1, duplicatePage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }
        
        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link dup-page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === duplicatePage ? 'active' : ''}">
                <a class="page-link dup-page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link dup-page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }
        
        // 다음 버튼
        html += `<li class="page-item ${duplicatePage >= totalPages ? 'disabled' : ''}">
            <a class="page-link dup-page-link" href="#" data-page="${duplicatePage + 1}">&raquo;</a>
        </li>`;
        
        html += '</ul></nav>';
        return html;
    }
    
    function deleteFiles(files) {
        const formData = new FormData();
        formData.append('action', 'delete_duplicates');
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        files.forEach(f => formData.append('files[]', f));
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            
            alert('✅ Deleted: ' + data.deleted + ' <?php echo __('adm_sys_unit_files'); ?>\n\n💡 If counts don\'t update:\nCache tab → Regen stats / Delete folder cache\n→ Refresh main page');
            
            // checkedFiles에서 삭제된 파일 제거
            files.forEach(f => checkedFiles.delete(f));
            
            // duplicateData에서 삭제된 파일 제거
            if (duplicateData && duplicateData.groups) {
                const deletedSet = new Set(files);
                duplicateData.groups.forEach(group => {
                    group.files = group.files.filter(f => !deletedSet.has(f.path));
                });
                // 빈 그룹 제거
                duplicateData.groups = duplicateData.groups.filter(g => g.files.length >= 2);
                duplicateData.duplicate_groups = duplicateData.groups.length;
            }
            
            // 현재 페이지 다시 렌더링
            renderDuplicatePage();
            updateSelectedCount();
        })
        .catch(err => {
            alert('Delete error: ' + err.message);
        });
    }
    
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // 결과 필터링
    document.getElementById('resultFilter').addEventListener('input', function() {
        const filter = this.value.toLowerCase().trim();
        document.querySelectorAll('#duplicateList .card').forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = (filter === '' || text.includes(filter)) ? '' : 'none';
        });
    });
})();
</script>
</div></div>

<!-- System -->
<div class="tab-pane fade" id="system">
<div class="container-fluid">

<?php
// ========== 서버 리소스 모니터링 함수들 ==========

/**
 * Windows에서 서버 리소스 정보 수집
 */
function getServerResources() {
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    $resources = [
        'cpu' => [
            'model' => 'Unknown',
            'cores' => 0,
            'threads' => 0,
            'usage' => 0,
            'available' => false
        ],
        'memory' => [
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'percent' => 0,
            'available' => false
        ],
        'network' => [
            'interfaces' => [],
            'available' => false
        ],
        'traffic' => [
            'total_rx' => 0,
            'total_tx' => 0,
            'interfaces' => [],
            'available' => false
        ],
        'webserver' => [
            'processes' => [],
            'available' => false
        ],
        'uptime' => [
            'value' => '',
            'available' => false
        ],
        'is_windows' => $isWindows
    ];
    
    if ($isWindows) {
        // Windows: CPU 정보
        $cpuInfo = @shell_exec('wmic cpu get name,numberofcores,numberoflogicalprocessors /format:csv 2>nul');
        if ($cpuInfo) {
            $lines = array_filter(explode("\n", trim($cpuInfo)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 4) {
                    $resources['cpu']['model'] = trim($parts[1]);
                    $resources['cpu']['cores'] = (int)$parts[2];
                    $resources['cpu']['threads'] = (int)$parts[3];
                    $resources['cpu']['available'] = true;
                }
            }
        }
        
        // Windows: CPU 사용률
        $cpuLoad = @shell_exec('wmic cpu get loadpercentage /format:csv 2>nul');
        if ($cpuLoad) {
            $lines = array_filter(explode("\n", trim($cpuLoad)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 2) {
                    $resources['cpu']['usage'] = (int)$parts[1];
                }
            }
        }
        
        // Windows: 메모리 정보
        $memInfo = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /format:csv 2>nul');
        if ($memInfo) {
            $lines = array_filter(explode("\n", trim($memInfo)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 3) {
                    $freeKB = (int)$parts[1];
                    $totalKB = (int)$parts[2];
                    $resources['memory']['total'] = $totalKB * 1024;
                    $resources['memory']['free'] = $freeKB * 1024;
                    $resources['memory']['used'] = ($totalKB - $freeKB) * 1024;
                    $resources['memory']['percent'] = $totalKB > 0 ? round((($totalKB - $freeKB) / $totalKB) * 100, 1) : 0;
                    $resources['memory']['available'] = true;
                }
            }
        }
        
        // Windows: 네트워크 인터페이스 (네트워크 연결 이름 사용)
        // netsh 사용 (제어판 > 네트워크 연결에서 보이는 이름)
        $netInfo = @shell_exec('netsh interface show interface 2>nul');
        if ($netInfo) {
            $lines = explode("\n", $netInfo);
            foreach ($lines as $line) {
                // "사용    연결됨    전용    이더넷" 또는 "Enabled  Connected  Dedicated  Ethernet" 형식
                $line = trim($line);
                if (empty($line) || strpos($line, '---') !== false) continue;
                
                // 연결됨/Connected 상태인 것만
                if (preg_match('/Connected/i', $line)) {
                    // 마지막 열이 인터페이스 이름
                    $parts = preg_split('/\s{2,}/', $line);
                    if (count($parts) >= 4) {
                        $ifaceName = trim(end($parts));
                        if (!empty($ifaceName) && $ifaceName !== 'Interface Name' && $ifaceName !== 'Interface Name') {
                            $resources['network']['interfaces'][] = [
                                'name' => $ifaceName,
                                'speed' => '',
                                'active' => true
                            ];
                        }
                    }
                }
            }
            $resources['network']['available'] = !empty($resources['network']['interfaces']);
        }
        
        // 속도 정보 추가 (PowerShell로 가져오기)
        if (!empty($resources['network']['interfaces'])) {
            $speedInfo = @shell_exec('powershell -NoProfile -Command "Get-NetAdapter | Select-Object Name,LinkSpeed | ConvertTo-Csv -NoTypeInformation" 2>nul');
            if ($speedInfo) {
                $speedMap = [];
                $lines = array_filter(explode("\n", trim($speedInfo)));
                array_shift($lines); // 헤더 제거
                foreach ($lines as $line) {
                    $parts = str_getcsv($line);
                    if (count($parts) >= 2) {
                        $speedMap[trim($parts[0])] = trim($parts[1]);
                    }
                }
                // 속도 매핑
                foreach ($resources['network']['interfaces'] as &$iface) {
                    if (isset($speedMap[$iface['name']])) {
                        $iface['speed'] = $speedMap[$iface['name']];
                    }
                }
                unset($iface);
            }
        }
        
        // netsh 실패 시 PowerShell fallback
        if (empty($resources['network']['interfaces'])) {
            $netInfo = @shell_exec('powershell -NoProfile -Command "Get-NetAdapter | Where-Object Status -eq \'Up\' | Select-Object Name,LinkSpeed | ConvertTo-Csv -NoTypeInformation" 2>nul');
            if ($netInfo) {
                $lines = array_filter(explode("\n", trim($netInfo)));
                if (count($lines) > 1) {
                    array_shift($lines);
                    foreach ($lines as $line) {
                        $parts = str_getcsv($line);
                        if (count($parts) >= 2 && !empty(trim($parts[0]))) {
                            $resources['network']['interfaces'][] = [
                                'name' => trim($parts[0]),
                                'speed' => trim($parts[1]) ?: 'Unknown',
                                'active' => true
                            ];
                        }
                    }
                    $resources['network']['available'] = !empty($resources['network']['interfaces']);
                }
            }
        }
        
        // Windows: 네트워크 트래픽 (송수신 바이트)
        $trafficInfo = @shell_exec('wmic path Win32_PerfRawData_Tcpip_NetworkInterface get Name,BytesReceivedPersec,BytesSentPersec,BytesTotalPersec /format:csv 2>nul');
        if ($trafficInfo) {
            $lines = array_filter(explode("\n", trim($trafficInfo)));
            if (count($lines) > 1) {
                array_shift($lines); // 헤더 제거
                $totalRx = 0;
                $totalTx = 0;
                foreach ($lines as $line) {
                    $parts = str_getcsv($line);
                    // CSV: Node, BytesReceivedPersec, BytesSentPersec, BytesTotalPersec, Name
                    if (count($parts) >= 5 && !empty(trim($parts[4]))) {
                        $rx = (int)$parts[1];
                        $tx = (int)$parts[2];
                        $totalRx += $rx;
                        $totalTx += $tx;
                        $resources['traffic']['interfaces'][] = [
                            'name' => trim($parts[4]),
                            'rx' => $rx,
                            'tx' => $tx
                        ];
                    }
                }
                $resources['traffic']['total_rx'] = $totalRx;
                $resources['traffic']['total_tx'] = $totalTx;
                $resources['traffic']['available'] = true;
            }
        }
        
        // Windows: 웹서버 프로세스 (Apache httpd.exe, IIS w3wp.exe)
        $webProcesses = [];
        
        // Apache (httpd.exe)
        $apacheInfo = @shell_exec('wmic process where "name=\'httpd.exe\'" get processid,workingsetsize /format:csv 2>nul');
        if ($apacheInfo) {
            $lines = array_filter(explode("\n", trim($apacheInfo)));
            if (count($lines) > 1) {
                array_shift($lines);
                $totalMem = 0;
                $count = 0;
                foreach ($lines as $line) {
                    $parts = str_getcsv($line);
                    if (count($parts) >= 3) {
                        $totalMem += (int)$parts[2];
                        $count++;
                    }
                }
                if ($count > 0) {
                    $webProcesses[] = [
                        'name' => 'Apache (httpd.exe)',
                        'count' => $count,
                        'memory' => $totalMem,
                        'icon' => '🌐'
                    ];
                }
            }
        }
        
        // IIS (w3wp.exe)
        $iisInfo = @shell_exec('wmic process where "name=\'w3wp.exe\'" get processid,workingsetsize /format:csv 2>nul');
        if ($iisInfo) {
            $lines = array_filter(explode("\n", trim($iisInfo)));
            if (count($lines) > 1) {
                array_shift($lines);
                $totalMem = 0;
                $count = 0;
                foreach ($lines as $line) {
                    $parts = str_getcsv($line);
                    if (count($parts) >= 3) {
                        $totalMem += (int)$parts[2];
                        $count++;
                    }
                }
                if ($count > 0) {
                    $webProcesses[] = [
                        'name' => 'IIS (w3wp.exe)',
                        'count' => $count,
                        'memory' => $totalMem,
                        'icon' => '🔷'
                    ];
                }
            }
        }
        
        // Nginx (nginx.exe)
        $nginxInfo = @shell_exec('wmic process where "name=\'nginx.exe\'" get processid,workingsetsize /format:csv 2>nul');
        if ($nginxInfo) {
            $lines = array_filter(explode("\n", trim($nginxInfo)));
            if (count($lines) > 1) {
                array_shift($lines);
                $totalMem = 0;
                $count = 0;
                foreach ($lines as $line) {
                    $parts = str_getcsv($line);
                    if (count($parts) >= 3) {
                        $totalMem += (int)$parts[2];
                        $count++;
                    }
                }
                if ($count > 0) {
                    $webProcesses[] = [
                        'name' => 'Nginx (nginx.exe)',
                        'count' => $count,
                        'memory' => $totalMem,
                        'icon' => '🟢'
                    ];
                }
            }
        }
        
        // PHP (php.exe, php-cgi.exe)
        $phpInfo = @shell_exec('wmic process where "name like \'php%\'" get name,processid,workingsetsize /format:csv 2>nul');
        if ($phpInfo) {
            $lines = array_filter(explode("\n", trim($phpInfo)));
            if (count($lines) > 1) {
                array_shift($lines);
                $totalMem = 0;
                $count = 0;
                foreach ($lines as $line) {
                    $parts = str_getcsv($line);
                    if (count($parts) >= 4) {
                        $totalMem += (int)$parts[3];
                        $count++;
                    }
                }
                if ($count > 0) {
                    $webProcesses[] = [
                        'name' => 'PHP Processes',
                        'count' => $count,
                        'memory' => $totalMem,
                        'icon' => '🐘'
                    ];
                }
            }
        }
        
        $resources['webserver']['processes'] = $webProcesses;
        $resources['webserver']['available'] = !empty($webProcesses);
        
        // Windows: 업타임
        $uptimeInfo = @shell_exec('wmic os get lastbootuptime /format:csv 2>nul');
        if ($uptimeInfo) {
            $lines = array_filter(explode("\n", trim($uptimeInfo)));
            if (count($lines) > 1) {
                $parts = str_getcsv(end($lines));
                if (count($parts) >= 2) {
                    $bootTime = $parts[1];
                    // WMI 날짜 형식: 20240101120000.000000+540
                    if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $bootTime, $m)) {
                        $bootTimestamp = mktime($m[4], $m[5], $m[6], $m[2], $m[3], $m[1]);
                        $uptimeSecs = time() - $bootTimestamp;
                        $resources['uptime']['value'] = formatUptime($uptimeSecs);
                        $resources['uptime']['available'] = true;
                    }
                }
            }
        }
        
    } else {
        // Linux/Unix 시스템
        
        // CPU 정보
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if (preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $m)) {
                $resources['cpu']['model'] = trim($m[1]);
            }
            $resources['cpu']['threads'] = substr_count($cpuinfo, 'processor');
            preg_match_all('/core id\s*:\s*(\d+)/i', $cpuinfo, $cores);
            $resources['cpu']['cores'] = count(array_unique($cores[1] ?? [])) ?: $resources['cpu']['threads'];
            $resources['cpu']['available'] = true;
        }
        
        // CPU 사용률 (간단한 방법)
        $load = @sys_getloadavg();
        if ($load !== false && $resources['cpu']['threads'] > 0) {
            $resources['cpu']['usage'] = min(100, round(($load[0] / $resources['cpu']['threads']) * 100, 1));
        }
        
        // 메모리 정보
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s*(\d+)/i', $meminfo, $total);
            preg_match('/MemAvailable:\s*(\d+)/i', $meminfo, $available);
            if (!empty($total[1])) {
                $totalKB = (int)$total[1];
                $availKB = (int)($available[1] ?? 0);
                $resources['memory']['total'] = $totalKB * 1024;
                $resources['memory']['free'] = $availKB * 1024;
                $resources['memory']['used'] = ($totalKB - $availKB) * 1024;
                $resources['memory']['percent'] = $totalKB > 0 ? round((($totalKB - $availKB) / $totalKB) * 100, 1) : 0;
                $resources['memory']['available'] = true;
            }
        }
        
        // 네트워크 인터페이스 및 트래픽
        if (is_readable('/proc/net/dev')) {
            $netdev = file_get_contents('/proc/net/dev');
            $lines = explode("\n", $netdev);
            $totalRx = 0;
            $totalTx = 0;
            foreach ($lines as $line) {
                // 형식: iface: rx_bytes rx_packets ... tx_bytes tx_packets ...
                if (preg_match('/^\s*(\w+):\s*(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $m)) {
                    $iface = $m[1];
                    $rx = (int)$m[2];
                    $tx = (int)$m[3];
                    
                    if ($iface !== 'lo') {
                        $speed = @file_get_contents("/sys/class/net/$iface/speed");
                        $speedStr = ($speed && (int)$speed > 0) ? ((int)$speed . ' Mbps') : 'Unknown';
                        $resources['network']['interfaces'][] = [
                            'name' => $iface,
                            'speed' => $speedStr
                        ];
                        
                        // 트래픽 정보
                        $resources['traffic']['interfaces'][] = [
                            'name' => $iface,
                            'rx' => $rx,
                            'tx' => $tx
                        ];
                        $totalRx += $rx;
                        $totalTx += $tx;
                    }
                }
            }
            $resources['network']['available'] = !empty($resources['network']['interfaces']);
            $resources['traffic']['total_rx'] = $totalRx;
            $resources['traffic']['total_tx'] = $totalTx;
            $resources['traffic']['available'] = $totalRx > 0 || $totalTx > 0;
        }
        
        // 웹서버 프로세스
        $webProcesses = [];
        $processChecks = [
            ['cmd' => "pgrep -c apache2 2>/dev/null || pgrep -c httpd 2>/dev/null || echo 0", 'name' => 'Apache', 'icon' => '🌐'],
            ['cmd' => "pgrep -c nginx 2>/dev/null || echo 0", 'name' => 'Nginx', 'icon' => '🟢'],
            ['cmd' => "pgrep -c php-fpm 2>/dev/null || echo 0", 'name' => 'PHP-FPM', 'icon' => '🐘'],
        ];
        
        foreach ($processChecks as $check) {
            $count = (int)@shell_exec($check['cmd']);
            if ($count > 0) {
                $webProcesses[] = [
                    'name' => $check['name'],
                    'count' => $count,
                    'memory' => 0, // Linux에서는 별도로 계산 필요
                    'icon' => $check['icon']
                ];
            }
        }
        $resources['webserver']['processes'] = $webProcesses;
        $resources['webserver']['available'] = !empty($webProcesses);
        
        // 업타임
        if (is_readable('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptimeSecs = (int)explode(' ', $uptime)[0];
            $resources['uptime']['value'] = formatUptime($uptimeSecs);
            $resources['uptime']['available'] = true;
        }
    }
    
    return $resources;
}

/**
 * 비트/초를 읽기 쉬운 단위로 변환
 */
function formatBitsPerSecond($bits) {
    if ($bits >= 1000000000) {
        return round($bits / 1000000000, 1) . ' Gbps';
    } elseif ($bits >= 1000000) {
        return round($bits / 1000000, 0) . ' Mbps';
    } elseif ($bits >= 1000) {
        return round($bits / 1000, 0) . ' Kbps';
    }
    return $bits . ' bps';
}

/**
 * 업타임을 읽기 쉬운 형식으로 변환
 */
function formatUptime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($days > 0) $parts[] = $days . __('adm_day');
    if ($hours > 0) $parts[] = $hours . __('adm_hour');
    if ($minutes > 0) $parts[] = $minutes . __('adm_min');
    
    return implode(' ', $parts) ?: __('adm_less_than_1min');
}

// 서버 리소스 정보 - 기본값 (시스템 탭 클릭 시 AJAX로 로드)
$serverResources = [
    'is_windows' => strtoupper(substr(PHP_OS, 0, 3)) === 'WIN',
    'cpu' => ['available' => true, 'model' => __('adm_loading'), 'cores' => '-', 'threads' => '-', 'usage' => 0],
    'memory' => ['available' => true, 'total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0],
    'network' => ['available' => true, 'interfaces' => []],
    'traffic' => ['available' => true, 'total_rx' => 0, 'total_tx' => 0],
    'webserver' => ['available' => true, 'processes' => []],
    'uptime' => ['available' => true, 'value' => __('adm_loading')]
];

// ========== 시스템 정보 수집 함수들 ==========

// 바이트를 읽기 쉬운 단위로 변환
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

// PHP 설정값을 바이트로 변환
function returnBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// 디스크 사용량 계산
function getDiskUsage($path) {
    if (!is_dir($path)) return null;
    $total = @disk_total_space($path);
    $free = @disk_free_space($path);
    if ($total === false || $free === false) return null;
    $used = $total - $free;
    $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
    return [
        'total' => $total,
        'used' => $used,
        'free' => $free,
        'percent' => $percent
    ];
}

// 폴더 크기 계산 (재귀)
function getFolderSize($path) {
    $size = 0;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    } catch (Exception $e) {
        return false;
    }
    return $size;
}

// 폴더 내 파일/폴더 수 카운트
function countItems($path) {
    $folders = 0;
    $files = 0;
    try {
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || $item->getFilename() === '@eaDir') continue;
            if ($item->isDir()) $folders++;
            else $files++;
        }
    } catch (Exception $e) {
        return ['folders' => 0, 'files' => 0];
    }
    return ['folders' => $folders, 'files' => $files];
}

// OPcache 상태 확인
function getOpcacheStatus() {
    if (!function_exists('opcache_get_status')) {
        return ['enabled' => false];
    }
    $status = @opcache_get_status(false);
    if (!$status) return ['enabled' => false];
    
    $config = @opcache_get_configuration();
    $memory = $status['memory_usage'] ?? [];
    $stats = $status['opcache_statistics'] ?? [];
    
    $used = ($memory['used_memory'] ?? 0) + ($memory['wasted_memory'] ?? 0);
    $total = $used + ($memory['free_memory'] ?? 0);
    $hitRate = isset($stats['hits'], $stats['misses']) && ($stats['hits'] + $stats['misses']) > 0
        ? round($stats['hits'] / ($stats['hits'] + $stats['misses']) * 100, 1)
        : 0;
    
    return [
        'enabled' => $status['opcache_enabled'] ?? false,
        'memory_total' => $total,
        'memory_used' => $used,
        'memory_free' => $memory['free_memory'] ?? 0,
        'hit_rate' => $hitRate,
        'cached_scripts' => $stats['num_cached_scripts'] ?? 0,
        'hits' => $stats['hits'] ?? 0,
        'misses' => $stats['misses'] ?? 0
    ];
}

// APCu 상태 확인
function getApcuStatus() {
    if (!function_exists('apcu_cache_info')) {
        return ['enabled' => false];
    }
    $info = @apcu_cache_info(true);
    $sma = @apcu_sma_info(true);
    if (!$info) return ['enabled' => false];
    
    $hitRate = isset($info['num_hits'], $info['num_misses']) && ($info['num_hits'] + $info['num_misses']) > 0
        ? round($info['num_hits'] / ($info['num_hits'] + $info['num_misses']) * 100, 1)
        : 0;
    
    return [
        'enabled' => true,
        'memory_total' => $sma['seg_size'] ?? 0,
        'memory_used' => ($sma['seg_size'] ?? 0) - ($sma['avail_mem'] ?? 0),
        'memory_free' => $sma['avail_mem'] ?? 0,
        'hit_rate' => $hitRate,
        'entries' => $info['num_entries'] ?? 0
    ];
}

// 서버 정보 수집
$serverInfo = [
    'date' => date('Y-m-d H:i:s P'),
    'timezone' => date_default_timezone_get(),
    'php_version' => PHP_VERSION,
    'php_sapi' => php_sapi_name(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'os' => PHP_OS . ' ' . (PHP_INT_SIZE * 8) . '-bit',
    'hostname' => gethostname() ?: 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? __DIR__,
    'ssl' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? true : false,
    'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
    'base_url' => ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/'
];

$diskUsage = getDiskUsage(__DIR__);

// 모든 base_dirs에 대한 정보 수집
$baseDirsInfo = [];
foreach ($base_dirs as $idx => $dir) {
    $baseDirsInfo[$idx] = [
        'path' => $dir,
        'usage' => getDiskUsage($dir),
        'items' => is_dir($dir) ? countItems($dir) : ['folders' => 0, 'files' => 0],
        'exists' => is_dir($dir),
        'readable' => is_readable($dir),
        'writable' => is_writable($dir)
    ];
}

// 하위 호환성 유지
$baseDirUsage = !empty($baseDirsInfo[0]) ? $baseDirsInfo[0]['usage'] : null;
$baseDirItems = !empty($baseDirsInfo[0]) ? $baseDirsInfo[0]['items'] : ['folders' => 0, 'files' => 0];

$opcache = getOpcacheStatus();
$apcu = getApcuStatus();

// 로드된 확장 모듈
$loadedExtensions = get_loaded_extensions();
sort($loadedExtensions);

// FFmpeg/FFprobe 체크 (파일 존재만 확인 - 빠름)
$ffmpegOk = !empty($ffmpeg_path) && is_file($ffmpeg_path);
$ffprobeOk = !empty($ffprobe_path) && is_file($ffprobe_path);
$vipsOk = !empty($vips_path) && is_file($vips_path);

// 웹서버 타입 감지
function detectWebServer() {
    $software = $_SERVER['SERVER_SOFTWARE'] ?? '';
    $type = 'Unknown';
    $version = '';
    
    if (stripos($software, 'Apache') !== false) {
        $type = 'Apache';
        if (preg_match('/Apache\/([0-9.]+)/i', $software, $m)) $version = $m[1];
    } elseif (stripos($software, 'nginx') !== false) {
        $type = 'Nginx';
        if (preg_match('/nginx\/([0-9.]+)/i', $software, $m)) $version = $m[1];
    } elseif (stripos($software, 'IIS') !== false || stripos($software, 'Microsoft') !== false) {
        $type = 'IIS';
        if (preg_match('/IIS\/([0-9.]+)/i', $software, $m)) $version = $m[1];
    } elseif (stripos($software, 'LiteSpeed') !== false) {
        $type = 'LiteSpeed';
        if (preg_match('/LiteSpeed\/([0-9.]+)/i', $software, $m)) $version = $m[1];
    } elseif (stripos($software, 'caddy') !== false) {
        $type = 'Caddy';
    }
    
    return ['type' => $type, 'version' => $version, 'full' => $software];
}

// Apache 모듈 체크 (Apache인 경우만)
function getApacheModules() {
    if (function_exists('apache_get_modules')) {
        return apache_get_modules();
    }
    return [];
}

// 필수/권장 PHP 확장 체크
function checkRequiredExtensions() {
    $extensions = [
        // 필수
        ['name' => 'gd', 'required' => true, 'desc' => __('adm_ext_gd'), 'alt' => 'imagick'],
        ['name' => 'imagick', 'required' => false, 'desc' => __('adm_ext_imagick'), 'alt' => 'gd'],
        ['name' => 'zip', 'required' => true, 'desc' => __('adm_ext_zip')],
        ['name' => 'intl', 'required' => true, 'desc' => __('adm_ext_intl')],
        ['name' => 'mbstring', 'required' => true, 'desc' => __('adm_ext_mbstring')],
        ['name' => 'json', 'required' => true, 'desc' => __('adm_ext_json2')],
        ['name' => 'fileinfo', 'required' => true, 'desc' => __('adm_ext_fileinfo2')],
        // 권장
        ['name' => 'curl', 'required' => false, 'desc' => __('adm_ext_curl2')],
        ['name' => 'openssl', 'required' => false, 'desc' => __('adm_ext_openssl2')],
        ['name' => 'exif', 'required' => false, 'desc' => __('adm_ext_exif2')],
        ['name' => 'zlib', 'required' => false, 'desc' => __('adm_ext_zlib')],
        // 캐시
        ['name' => 'redis', 'required' => false, 'desc' => __('adm_ext_redis')],
        ['name' => 'apcu', 'required' => false, 'desc' => __('adm_ext_apcu2')],
        ['name' => 'Zend OPcache', 'required' => false, 'desc' => __('adm_ext_opcache2'), 'check' => 'opcache'],
    ];
    
    $result = [];
    foreach ($extensions as $ext) {
        $checkName = $ext['check'] ?? $ext['name'];
        $loaded = extension_loaded($checkName);
        $result[] = [
            'name' => $ext['name'],
            'loaded' => $loaded,
            'required' => $ext['required'],
            'desc' => $ext['desc'],
            'alt' => $ext['alt'] ?? null
        ];
    }
    return $result;
}

// 필수 PHP 함수 체크
function checkRequiredFunctions() {
    $functions = [
        ['name' => 'json_encode', 'desc' => __('adm_fn_json_encode')],
        ['name' => 'json_decode', 'desc' => __('adm_fn_json_decode')],
        ['name' => 'file_get_contents', 'desc' => __('adm_fn_file_read')],
        ['name' => 'file_put_contents', 'desc' => __('adm_fn_file_write')],
        ['name' => 'scandir', 'desc' => __('adm_fn_scandir')],
        ['name' => 'exec', 'desc' => __('adm_fn_exec')],
        ['name' => 'shell_exec', 'desc' => __('adm_fn_shell_exec')],
    ];
    
    $result = [];
    foreach ($functions as $func) {
        $disabled = explode(',', ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
        $available = function_exists($func['name']) && !in_array($func['name'], $disabled);
        $result[] = [
            'name' => $func['name'],
            'available' => $available,
            'desc' => $func['desc']
        ];
    }
    return $result;
}

// 필수 PHP 클래스 체크
function checkRequiredClasses() {
    $classes = [
        ['name' => 'ZipArchive', 'desc' => __('adm_cls_ziparchive')],
        ['name' => 'Normalizer', 'desc' => __('adm_cls_normalizer')],
        ['name' => 'DirectoryIterator', 'desc' => __('adm_cls_diriterator')],
        ['name' => 'RecursiveDirectoryIterator', 'desc' => __('adm_cls_recdiriterator')],
        ['name' => 'DateTime', 'desc' => __('adm_cls_datetime')],
        ['name' => 'finfo', 'desc' => __('adm_cls_finfo')],
    ];
    
    $result = [];
    foreach ($classes as $cls) {
        $result[] = [
            'name' => $cls['name'],
            'available' => class_exists($cls['name']),
            'desc' => $cls['desc']
        ];
    }
    return $result;
}

$webServer = detectWebServer();
$apacheModules = getApacheModules();
$requiredExts = checkRequiredExtensions();
$requiredFuncs = checkRequiredFunctions();
$requiredClasses = checkRequiredClasses();

// 필수 항목 중 누락된 것 체크
$missingRequired = array_filter($requiredExts, function($e) { 
    return $e['required'] && !$e['loaded'] && empty($e['alt']); 
});
$missingRequiredWithAlt = array_filter($requiredExts, function($e) use ($requiredExts) { 
    if (!$e['required'] || $e['loaded']) return false;
    if (empty($e['alt'])) return false;
    // 대체 확장이 있는지 확인
    foreach ($requiredExts as $other) {
        if ($other['name'] === $e['alt'] && $other['loaded']) return false;
    }
    return true;
});
$hasAllRequired = empty($missingRequired) && empty($missingRequiredWithAlt);
?>

<!-- Server resource monitor -->
<div class="info-card">
<div class="card-header bg-primary text-white"><?php echo __("adm_card_server_monitor"); ?></div>
<div class="card-body">
<div class="row">

<!-- CPU info -->
<div class="col-md-6 mb-3">
<div class="border rounded p-3 h-100">
    <h6 class="mb-2">⚡ CPU</h6>
    <div class="mb-2">
        <small class="text-muted d-block"><?php echo __("adm_sys_model"); ?></small>
        <strong style="font-size: 0.85em;" id="cpu-model"><?php echo h($serverResources['cpu']['model']); ?></strong>
    </div>
    <div class="row mb-2">
        <div class="col-6">
            <small class="text-muted d-block"><?php echo __("adm_sys_cores"); ?></small>
            <strong id="cpu-cores"><?php echo $serverResources['cpu']['cores']; ?></strong>
        </div>
        <div class="col-6">
            <small class="text-muted d-block"><?php echo __("adm_sys_threads"); ?></small>
            <strong id="cpu-threads"><?php echo $serverResources['cpu']['threads']; ?></strong>
        </div>
    </div>
    <div>
        <small class="text-muted d-block"><?php echo __("adm_sys_usage_rate"); ?></small>
        <div class="progress" style="height: 20px;">
            <div class="progress-bar bg-success" id="cpu-bar" style="width: 0%">0%</div>
        </div>
    </div>
</div>
</div>

<!-- Memory info -->
<div class="col-md-6 mb-3">
<div class="border rounded p-3 h-100">
    <h6 class="mb-2"><?php echo __("adm_sys_memory_ram"); ?></h6>
    <div class="row mb-2">
        <div class="col-4">
            <small class="text-muted d-block"><?php echo __("adm_sys_total_label"); ?></small>
            <strong id="mem-total"><?php echo formatBytes($serverResources['memory']['total']); ?></strong>
        </div>
        <div class="col-4">
            <small class="text-muted d-block"><?php echo __("adm_sys_used_space"); ?></small>
            <strong class="text-danger" id="mem-used"><?php echo formatBytes($serverResources['memory']['used']); ?></strong>
        </div>
        <div class="col-4">
            <small class="text-muted d-block"><?php echo __("adm_sys_free_label"); ?></small>
            <strong class="text-success" id="mem-free"><?php echo formatBytes($serverResources['memory']['free']); ?></strong>
        </div>
    </div>
    <div>
        <small class="text-muted d-block"><?php echo __("adm_sys_usage_rate"); ?></small>
        <div class="progress" style="height: 20px;">
            <div class="progress-bar bg-success" id="mem-bar" style="width: 0%">0%</div>
        </div>
    </div>
</div>
</div>

<!-- Web server processes -->
<div class="col-md-6 mb-3">
<div class="border rounded p-3 h-100">
    <h6 class="mb-2"><?php echo __("adm_sys_webserver"); ?></h6>
    <div id="webserver-list" style="max-height: 150px; overflow-y: auto;">
        <span class="text-muted">Loading...</span>
    </div>
</div>
</div>

<!-- Network interfaces -->
<div class="col-md-6 mb-3">
<div class="border rounded p-3 h-100">
    <h6 class="mb-2"><?php echo __("adm_sys_net_interfaces"); ?> <small class="text-muted" id="active-iface-count"></small></h6>
    <div id="iface-list" style="max-height: 150px; overflow-y: auto; font-size: 0.85em;">
        <span class="text-muted">Loading...</span>
    </div>
</div>
</div>

<!-- Network traffic -->
<div class="col-md-6 mb-3">
<div class="border rounded p-3 h-100">
    <h6 class="mb-2"><?php echo __("adm_sys_net_traffic"); ?></h6>
    <div class="row mb-2">
        <div class="col-6">
            <small class="text-muted d-block">⬇️ RX (Total)</small>
            <strong class="text-primary" id="traffic-rx-total">0 B</strong>
        </div>
        <div class="col-6">
            <small class="text-muted d-block">⬆️ TX (Total)</small>
            <strong class="text-success" id="traffic-tx-total">0 B</strong>
        </div>
    </div>
    <hr class="my-2">
    <div class="row">
        <div class="col-6">
            <small class="text-muted d-block">⬇️ RX Speed</small>
            <strong class="text-primary" id="traffic-rx-speed"><?php echo __("adm_sys_measuring"); ?></strong>
        </div>
        <div class="col-6">
            <small class="text-muted d-block">⬆️ TX Speed</small>
            <strong class="text-success" id="traffic-tx-speed"><?php echo __("adm_sys_measuring"); ?></strong>
        </div>
    </div>
</div>
</div>

<!-- Real-time monitor (Chart.js) -->
<div class="col-12 mb-3">
<div class="border rounded p-2 bg-dark" style="min-height: 180px; color: #fff;">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span style="color:#fff;"><?php echo __("adm_sys_realtime_monitor"); ?> <small id="monitor-datetime" style="color:#ccc;"><?php echo date('Y-m-d H:i:s'); ?></small></span>
        <div>
            <select class="form-control form-control-sm d-inline" id="refreshInterval" style="width: 65px; color:#000; background:#fff;">
                <option value="3">3s</option>
                <option value="5">5s</option>
                <option value="10">10s</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-light ml-1" id="toggleRealtime" onclick="toggleRealtimeMonitor()">
                <span id="realtimeStatus">⏸️</span>
            </button>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3 col-6 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <small style="color:#fff;">⚡ CPU</small>
                <span class="badge badge-primary badge-sm" id="cpu-current">0%</span>
            </div>
            <div style="height: 70px;"><canvas id="cpuChart"></canvas></div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <small style="color:#fff;">🧠 Memory</small>
                <span class="badge badge-info badge-sm" id="mem-current">0%</span>
            </div>
            <div style="height: 70px;"><canvas id="memChart"></canvas></div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <small style="color:#fff;">💾 Disk I/O</small>
                <span class="badge badge-warning badge-sm" id="disk-current">0 B/s</span>
            </div>
            <div style="height: 70px;"><canvas id="diskChart"></canvas></div>
            <div class="text-center" style="font-size: 0.7em; color: #aaa; margin-top: 8px;">📖R: <span id="disk-read">0</span> · 📝W: <span id="disk-write">0</span></div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <small style="color:#fff;">📊 Network</small>
                <span class="badge badge-success badge-sm" id="net-current">0 B/s</span>
            </div>
            <div style="height: 70px;"><canvas id="netChart"></canvas></div>
            <div class="text-center" style="font-size: 0.7em; color: #aaa; margin-top: 8px;">⬇️ <span id="net-rx">0</span> · ⬆️ <span id="net-tx">0</span></div>
        </div>
    </div>
</div>
</div>

<!-- Network/server info -->
<div class="col-12 mb-3">
<div class="border rounded p-2 bg-light">
    <div class="d-flex justify-content-center align-items-center flex-wrap small">
        <span class="mr-3">🌐 <strong>Internet:</strong> <span id="net-status" class="badge badge-secondary"><?php echo __("adm_sys_checking"); ?></span> <span id="net-latency" class="text-muted"></span></span>
        <span class="mr-3">🏠 <strong>Private IP:</strong> <code id="private-ip">...</code></span>
        <span class="mr-3">🌍 <strong><?php echo __("adm_sys_public_ip"); ?></strong> <code id="public-ip">...</code></span>
        <span class="mr-3" id="uptime-display">⏱️ <span id="server-uptime">Loading...</span></span>
        <span>💻 <?php echo h($serverResources['is_windows'] ? 'Windows' : 'Linux'); ?></span>
    </div>
</div>
</div>

</div>
</div>
</div>

<!-- ⭐ Required Service Check -->
<div class="info-card">
<div class="card-header <?php echo $hasAllRequired ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
    ⭐ Required Service Check 
    <?php echo $hasAllRequired ? '- ' . __('adm_sys_all_ok') : '- ' . __('adm_sys_missing_items'); ?>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-sm table-striped mb-0">
<thead class="thead-light">
    <tr><th style="min-width:100px;"><?php echo __("adm_sys_ext_module"); ?></th><th style="min-width:80px;"><?php echo __("adm_th_status"); ?></th><th><?php echo __("adm_th_description"); ?></th></tr>
</thead>
<tbody>
<?php foreach ($requiredExts as $ext): ?>
<tr class="<?php echo $ext['required'] && !$ext['loaded'] ? 'table-danger' : ''; ?>">
    <td>
        <strong><?php echo h($ext['name']); ?></strong><br>
        <?php if ($ext['required']): ?>
            <span class="badge badge-danger badge-sm"><?php echo __("adm_sys_required"); ?></span>
        <?php else: ?>
            <span class="badge badge-secondary badge-sm"><?php echo __("adm_sys_optional"); ?></span>
        <?php endif; ?>
    </td>
    <td style="white-space:nowrap;">
        <?php if ($ext['loaded']): ?>
            <span class="text-success">✅</span>
        <?php else: ?>
            <?php if ($ext['alt'] && extension_loaded($ext['alt'])): ?>
                <span class="text-warning"><?php echo __("adm_sys_alt_badge"); ?></span>
            <?php elseif ($ext['required']): ?>
                <span class="text-danger">❌</span>
            <?php else: ?>
                <span class="text-warning">⚠️</span>
            <?php endif; ?>
        <?php endif; ?>
    </td>
    <td><small><?php echo h($ext['desc']); ?></small></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

<!-- Required PHP classes/functions -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_sys_classes_functions"); ?></div>
<div class="card-body p-3">
<div class="row">
    <div class="col-md-6">
        <h6 class="mb-2"><strong><?php echo __("adm_sys_classes"); ?></strong></h6>
        <?php foreach ($requiredClasses as $cls): ?>
        <div class="mb-1">
            <?php echo $cls['available'] ? '<span class="status-badge status-ok">✅</span>' : '<span class="status-badge status-error">❌</span>'; ?>
            <code><?php echo h($cls['name']); ?></code>
            <small class="text-muted">- <?php echo h($cls['desc']); ?></small>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="col-md-6">
        <h6 class="mb-2"><strong><?php echo __("adm_sys_functions"); ?></strong></h6>
        <?php foreach ($requiredFuncs as $func): ?>
        <div class="mb-1">
            <?php echo $func['available'] ? '<span class="status-badge status-ok">✅</span>' : '<span class="status-badge status-error">❌</span>'; ?>
            <code><?php echo h($func['name']); ?>()</code>
            <small class="text-muted">- <?php echo h($func['desc']); ?></small>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</div>
</div>

<!-- Server overview -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_server_overview"); ?></div>
<div class="card-body p-0">
<table class="table table-sm table-striped mb-0">
<tr><td width="35%"><strong><?php echo __("adm_sys_current_time"); ?></strong></td><td><?php echo h($serverInfo['date']); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_timezone"); ?></strong></td><td><?php echo h($serverInfo['timezone']); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_server_os"); ?></strong></td><td><?php echo h($serverInfo['os']); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_hostname"); ?></strong></td><td><?php echo h($serverInfo['hostname']); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_webserver_type"); ?></strong></td><td>
    <span class="badge badge-<?php 
        echo $webServer['type'] === 'Apache' ? 'danger' : 
            ($webServer['type'] === 'Nginx' ? 'success' : 
            ($webServer['type'] === 'IIS' ? 'primary' : 'secondary')); 
    ?>">
        <?php echo h($webServer['type']); ?>
    </span>
    <?php if ($webServer['version']): ?>
        <span class="text-muted"><?php echo h($webServer['version']); ?></span>
    <?php endif; ?>
</td></tr>
<tr><td><strong><?php echo __("adm_sys_webserver_full"); ?></strong></td><td><small><?php echo h($webServer['full']); ?></small></td></tr>
<?php if (!empty($apacheModules)): ?>
<tr><td><strong><?php echo __("adm_sys_apache_modules"); ?></strong></td><td>
    <small>
    <?php 
    $importantMods = ['mod_rewrite', 'mod_headers', 'mod_ssl', 'mod_deflate', 'mod_expires'];
    foreach ($importantMods as $mod): 
        $hasIt = in_array($mod, $apacheModules);
    ?>
        <span class="badge <?php echo $hasIt ? 'badge-success' : 'badge-secondary'; ?> mr-1"><?php echo h($mod); ?></span>
    <?php endforeach; ?>
    <br><span class="text-muted"><?php echo count($apacheModules); ?> <?php echo __('adm_sys_modules_loaded'); ?></span>
    </small>
</td></tr>
<?php endif; ?>
<tr><td><strong>PHP SAPI</strong></td><td>
    <?php echo h($serverInfo['php_sapi']); ?>
    <small class="text-muted">
    (<?php 
        $sapi = $serverInfo['php_sapi'];
        if (strpos($sapi, 'apache') !== false) echo __('adm_sys_apache_modules');
        elseif (strpos($sapi, 'fpm') !== false) echo 'PHP-FPM';
        elseif (strpos($sapi, 'cgi') !== false) echo 'CGI/FastCGI';
        elseif (strpos($sapi, 'cli') !== false) echo 'CLI';
        else echo __('adm_act_other');
    ?>)
    </small>
</td></tr>
<tr><td><strong>Base URL</strong></td><td style="word-break:break-all"><?php echo h($serverInfo['base_url']); ?></td></tr>
<tr><td><strong>SSL/HTTPS</strong></td><td><?php echo $serverInfo['ssl'] ? '<span class="status-badge status-ok">✅ Enabled</span>' : '<span class="status-badge status-warning">⚠️ Disabled</span>'; ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_protocol"); ?></strong></td><td><?php echo h($serverInfo['protocol']); ?></td></tr>
</table>
</div>
</div>

<!-- PHP info -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_php_info"); ?></div>
<div class="card-body p-0">
<table class="table table-sm table-striped mb-0">
<tr><td width="35%"><strong><?php echo __("adm_sys_php_version"); ?></strong></td><td><span class="badge badge-info"><?php echo h(PHP_VERSION); ?></span></td></tr>
<tr><td><strong><?php echo __("adm_sys_memory_limit"); ?></strong></td><td><?php echo h(ini_get('memory_limit')); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_current_mem"); ?></strong></td><td><?php echo formatBytes(memory_get_usage(true)); ?> / <?php echo formatBytes(memory_get_peak_usage(true)); ?> (peak)</td></tr>
<tr><td><strong><?php echo __("adm_sys_max_upload2"); ?></strong></td><td><?php echo h(ini_get('upload_max_filesize')); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_max_post2"); ?></strong></td><td><?php echo h(ini_get('post_max_size')); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_max_exec2"); ?></strong></td><td><?php echo h(ini_get('max_execution_time')); ?>s</td></tr>
<tr><td><strong><?php echo __("adm_sys_max_input"); ?></strong></td><td><?php echo h(ini_get('max_input_time')); ?>s</td></tr>
<tr><td><strong><?php echo __("adm_sys_max_files"); ?></strong></td><td><?php echo h(ini_get('max_file_uploads')); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_error_display"); ?></strong></td><td><?php echo ini_get('display_errors') ? '<span class="text-warning">On</span>' : '<span class="text-success">Off</span>'; ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_error_logging"); ?></strong></td><td><?php echo ini_get('log_errors') ? '<span class="text-success">On</span>' : '<span class="text-warning">Off</span>'; ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_error_log_path"); ?></strong></td><td style="word-break:break-all"><small><?php echo h(ini_get('error_log') ?: __('adm_sys_default')); ?></small></td></tr>
</table>
</div>
</div>

<!-- Session info -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_session_info"); ?></div>
<div class="card-body p-0">
<table class="table table-sm table-striped mb-0">
<tr><td width="35%"><strong><?php echo __("adm_sys_current_user"); ?></strong></td><td><span class="badge badge-dark"><?php echo h($_SESSION['user_id'] ?? 'Unknown'); ?></span></td></tr>
<tr><td><strong><?php echo __("adm_sys_user_group"); ?></strong></td><td><span class="badge badge-primary"><?php echo h($_SESSION['user_group'] ?? 'Unknown'); ?></span></td></tr>
<tr><td><strong><?php echo __("adm_sys_session_handler2"); ?></strong></td><td><?php echo h(ini_get('session.save_handler')); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_session_path2"); ?></strong></td><td style="word-break:break-all"><small><?php echo h(ini_get('session.save_path') ?: __('adm_sys_default')); ?></small></td></tr>
<tr><td><strong><?php echo __("adm_sys_session_gc"); ?></strong></td><td><?php echo h(ini_get('session.gc_maxlifetime')); ?>s (<?php echo round(ini_get('session.gc_maxlifetime') / 60); ?>m)</td></tr>
<tr><td><strong><?php echo __("adm_sys_session_cookie_life"); ?></strong></td><td><?php echo h(ini_get('session.cookie_lifetime')); ?>s</td></tr>
<tr><td><strong><?php echo __("adm_sys_session_cookie_secure"); ?></strong></td><td><?php echo ini_get('session.cookie_secure') ? '<span class="status-badge status-ok">✅</span>' : '<span class="status-badge status-warning">⚠️</span>'; ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_session_cookie_http"); ?></strong></td><td><?php echo ini_get('session.cookie_httponly') ? '<span class="status-badge status-ok">✅</span>' : '<span class="status-badge status-warning">⚠️</span>'; ?></td></tr>
</table>
</div>
</div>

<!-- OPcache status -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_opcache_status"); ?></div>
<div class="card-body p-0">
<?php if ($opcache['enabled']): ?>
<table class="table table-sm table-striped mb-0">
<tr><td width="35%"><strong><?php echo __("adm_th_status"); ?></strong></td><td><span class="status-badge status-ok">✅ Enabled</span></td></tr>
<tr><td><strong><?php echo __("adm_sys_mem_usage"); ?></strong></td><td>
    <?php echo formatBytes($opcache['memory_used']); ?> / <?php echo formatBytes($opcache['memory_total']); ?>
    <div class="progress mt-1" style="height: 8px;">
        <?php $opcPercent = $opcache['memory_total'] > 0 ? round($opcache['memory_used'] / $opcache['memory_total'] * 100) : 0; ?>
        <div class="progress-bar <?php echo $opcPercent > 90 ? 'bg-danger' : ($opcPercent > 70 ? 'bg-warning' : 'bg-success'); ?>" style="width: <?php echo $opcPercent; ?>%"></div>
    </div>
</td></tr>
<tr><td><strong><?php echo __("adm_sys_cache_hit_rate"); ?></strong></td><td>
    <span class="badge <?php echo $opcache['hit_rate'] > 90 ? 'badge-success' : ($opcache['hit_rate'] > 70 ? 'badge-warning' : 'badge-danger'); ?>">
        <?php echo $opcache['hit_rate']; ?>%
    </span>
    (<?php echo number_format($opcache['hits']); ?> hits / <?php echo number_format($opcache['misses']); ?> misses)
</td></tr>
<tr><td><strong><?php echo __("adm_sys_cached_scripts"); ?></strong></td><td><?php echo number_format($opcache['cached_scripts']); ?></td></tr>
</table>
<?php else: ?>
<div class="p-3 text-center text-muted">
    <span class="status-badge status-warning"><?php echo __("adm_sys_opcache_disabled"); ?></span>
    <p class="mb-0 mt-2"><small><?php echo __("adm_sys_opcache_recommend"); ?></small></p>
</div>
<?php endif; ?>
</div>
</div>

<!-- APCu status -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_apcu_status"); ?></div>
<div class="card-body p-0">
<?php if ($apcu['enabled']): ?>
<table class="table table-sm table-striped mb-0">
<tr><td width="35%"><strong><?php echo __("adm_th_status"); ?></strong></td><td><span class="status-badge status-ok">✅ Enabled</span></td></tr>
<tr><td><strong><?php echo __("adm_sys_mem_usage"); ?></strong></td><td>
    <?php echo formatBytes($apcu['memory_used']); ?> / <?php echo formatBytes($apcu['memory_total']); ?>
    <div class="progress mt-1" style="height: 8px;">
        <?php $apcuPercent = $apcu['memory_total'] > 0 ? round($apcu['memory_used'] / $apcu['memory_total'] * 100) : 0; ?>
        <div class="progress-bar <?php echo $apcuPercent > 90 ? 'bg-danger' : ($apcuPercent > 70 ? 'bg-warning' : 'bg-success'); ?>" style="width: <?php echo $apcuPercent; ?>%"></div>
    </div>
</td></tr>
<tr><td><strong><?php echo __("adm_sys_cache_hit_rate"); ?></strong></td><td>
    <span class="badge <?php echo $apcu['hit_rate'] > 90 ? 'badge-success' : ($apcu['hit_rate'] > 70 ? 'badge-warning' : 'badge-danger'); ?>">
        <?php echo $apcu['hit_rate']; ?>%
    </span>
</td></tr>
<tr><td><strong><?php echo __("adm_sys_stored_items"); ?></strong></td><td><?php echo number_format($apcu['entries']); ?></td></tr>
</table>
<?php else: ?>
<div class="p-3 text-center text-muted">
    <span class="status-badge status-warning"><?php echo __("adm_sys_apcu_disabled"); ?></span>
</div>
<?php endif; ?>
</div>
</div>

<!-- Disk info -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_disk_info"); ?></div>
<div class="card-body p-0">
<table class="table table-sm table-striped mb-0">
<?php if ($diskUsage): ?>
<tr><td width="35%"><strong><?php echo __("adm_sys_app_disk"); ?></strong></td><td>
    <?php echo __("adm_sys_disk_used"); ?>: <?php echo formatBytes($diskUsage['used']); ?> / <?php echo formatBytes($diskUsage['total']); ?> (Free: <?php echo formatBytes($diskUsage['free']); ?>)
    <div class="progress mt-1" style="height: 8px;">
        <div class="progress-bar <?php echo $diskUsage['percent'] > 90 ? 'bg-danger' : ($diskUsage['percent'] > 70 ? 'bg-warning' : 'bg-success'); ?>" style="width: <?php echo $diskUsage['percent']; ?>%"></div>
    </div>
    <small class="text-muted"><?php echo $diskUsage['percent']; ?>% used</small>
</td></tr>
<?php endif; ?>
<?php 
// 모든 base_dirs의 디스크 정보 표시 (중복 드라이브 제거)
$shownDrives = [$diskUsage ? $diskUsage['total'] : null]; // 앱 디스크와 같은 드라이브 제외용
foreach ($baseDirsInfo as $idx => $info): 
    if (!$info['usage']) continue;
    // 이미 표시된 드라이브는 스킵 (같은 total 용량으로 판단)
    if (in_array($info['usage']['total'], $shownDrives)) continue;
    $shownDrives[] = $info['usage']['total'];
?>
<tr><td><strong><?php echo __("adm_sys_content_disk"); ?> #<?php echo $idx; ?></strong></td><td>
    <?php echo __("adm_sys_disk_used"); ?>: <?php echo formatBytes($info['usage']['used']); ?> / <?php echo formatBytes($info['usage']['total']); ?> (Free: <?php echo formatBytes($info['usage']['free']); ?>)
    <div class="progress mt-1" style="height: 8px;">
        <div class="progress-bar <?php echo $info['usage']['percent'] > 90 ? 'bg-danger' : ($info['usage']['percent'] > 70 ? 'bg-warning' : 'bg-success'); ?>" style="width: <?php echo $info['usage']['percent']; ?>%"></div>
    </div>
    <small class="text-muted"><?php echo $info['usage']['percent']; ?>% used · <?php echo h($info['path']); ?></small>
</td></tr>
<?php endforeach; ?>
<tr><td><strong><?php echo __("adm_sys_content_total"); ?></strong></td><td>
    <?php 
    $totalFolders = 0;
    $totalFiles = 0;
    foreach ($baseDirsInfo as $info) {
        $totalFolders += $info['items']['folders'];
        $totalFiles += $info['items']['files'];
    }
    ?>
    📁 <?php echo number_format($totalFolders); ?> <?php echo __('adm_sys_unit_folders'); ?>, 
    📄 <?php echo number_format($totalFiles); ?> <?php echo __('adm_sys_unit_files'); ?>
    <small class="text-muted">(<?php echo count($base_dirs); ?> <?php echo __('adm_sys_unit_dirs'); ?>)</small>
</td></tr>
</table>
</div>
</div>

<!-- Directory info -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_dir_info"); ?></div>
<div class="card-body p-0">
<table class="table table-sm table-striped mb-0">
<tr class="bg-light"><td colspan="2"><strong><?php echo __("adm_sys_content_folders"); ?> (<?php echo count($base_dirs); ?>)</strong></td></tr>
<?php foreach ($baseDirsInfo as $idx => $info): ?>
<tr>
    <td width="35%"><strong>base_dirs[<?php echo $idx; ?>]</strong></td>
    <td style="word-break:break-all">
        <?php echo h($info['path']); ?>
        <div class="mt-1">
            <?php if ($info['exists']): ?>
                <span class="badge badge-success"><?php echo __("adm_sys_exists"); ?></span>
                <?php echo $info['readable'] ? '<span class="badge badge-info">📖 Read</span>' : '<span class="badge badge-danger">❌ Unreadable</span>'; ?>
                <?php echo $info['writable'] ? '<span class="badge badge-info">✏️ Write</span>' : ''; ?>
                <span class="badge badge-secondary">📁 <?php echo number_format($info['items']['folders']); ?> folders</span>
                <span class="badge badge-secondary">📄 <?php echo number_format($info['items']['files']); ?> <?php echo __('adm_sys_unit_files'); ?></span>
            <?php else: ?>
                <span class="badge badge-danger"><?php echo __("adm_sys_not_exists"); ?></span>
            <?php endif; ?>
        </div>
        <?php if ($info['usage']): ?>
        <div class="progress mt-1" style="height: 6px;">
            <div class="progress-bar <?php echo $info['usage']['percent'] > 90 ? 'bg-danger' : ($info['usage']['percent'] > 70 ? 'bg-warning' : 'bg-success'); ?>" style="width: <?php echo $info['usage']['percent']; ?>%"></div>
        </div>
        <small class="text-muted">Disk: <?php echo formatBytes($info['usage']['used']); ?> / <?php echo formatBytes($info['usage']['total']); ?> (<?php echo $info['usage']['percent']; ?>%)</small>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<tr class="bg-light"><td colspan="2"><strong><?php echo __("adm_sys_system_paths"); ?></strong></td></tr>
<tr><td><strong><?php echo __("adm_sys_current_dir"); ?></strong></td><td style="word-break:break-all"><?php echo h(__DIR__); ?></td></tr>
<tr><td><strong>Document Root</strong></td><td style="word-break:break-all"><?php echo h($serverInfo['document_root']); ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_write_perm_app"); ?></strong></td><td><?php echo is_writable(__DIR__) ? '<span class="status-badge status-ok">✅ OK</span>' : '<span class="status-badge status-error">❌ No</span>'; ?></td></tr>
<tr><td><strong><?php echo __("adm_sys_write_perm_src"); ?></strong></td><td><?php echo is_writable(__DIR__ . '/src') ? '<span class="status-badge status-ok">✅ OK</span>' : '<span class="status-badge status-error">❌ No</span>'; ?></td></tr>
</table>
</div>
</div>

<!-- External tools status -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_tools_status"); ?></div>
<div class="card-body p-0">
<table class="table table-sm table-striped mb-0">
<tr><td width="35%"><strong>FFmpeg</strong></td><td>
    <?php if (!empty($ffmpeg_path)): ?>
        <?php echo $ffmpegOk ? '<span class="status-badge status-ok">✅ OK</span>' : '<span class="status-badge status-error">❌ Not Found</span>'; ?>
        <br><small class="text-muted"><?php echo h($ffmpeg_path); ?></small>
    <?php else: ?>
        <span class="status-badge status-warning">⚠️ N/A</span>
    <?php endif; ?>
</td></tr>
<tr><td><strong>FFprobe</strong></td><td>
    <?php if (!empty($ffprobe_path)): ?>
        <?php echo $ffprobeOk ? '<span class="status-badge status-ok">✅ OK</span>' : '<span class="status-badge status-error">❌ Not Found</span>'; ?>
        <br><small class="text-muted"><?php echo h($ffprobe_path); ?></small>
    <?php else: ?>
        <span class="status-badge status-warning">⚠️ N/A</span>
    <?php endif; ?>
</td></tr>
<tr><td><strong>VIPS</strong></td><td>
    <?php if (!empty($vips_path)): ?>
        <?php echo $vipsOk ? '<span class="status-badge status-ok">✅ OK</span>' : '<span class="status-badge status-error">❌ Not Found</span>'; ?>
        <br><small class="text-muted"><?php echo h($vips_path); ?></small>
    <?php else: ?>
        <span class="status-badge status-warning">⚠️ N/A</span>
    <?php endif; ?>
</td></tr>
</table>
</div>
</div>

<!-- File status -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_core_files"); ?></div>
<div class="card-body p-0">
<table class="table table-sm table-striped mb-0">
<?php 
$coreFiles = [
    'config.php' => __('adm_file_config'),
    'function.php' => __('adm_file_function'),
    'security_helpers.php' => __('adm_file_security'),
    'init.php' => __('adm_file_init'),
    'index.php' => __('adm_file_main'),
    'viewer.php' => __('adm_file_viewer'),
    'login.php' => __('adm_file_login'),
    './src/search_translations.json' => __('adm_file_translations'),
    './src/users.json' => __('adm_file_users'),
    './src/folder_permissions.json' => __('adm_file_folder_perm'),
    './src/login_theme.json' => __('adm_file_login_theme'),
    './src/branding.json' => __('adm_file_branding'),
];

// base_dirs 개수에 따라 검색 인덱스 파일 추가
for ($i = 0; $i < count($base_dirs); $i++) {
    $coreFiles["./src/search_index_{$i}.json"] = __("adm_file_search_idx") . " #{$i}";
}

foreach ($coreFiles as $f => $d): 
    $exists = file_exists($f);
    $writable = $exists ? is_writable($f) : false;
    $size = $exists ? filesize($f) : 0;
    $mtime = $exists ? filemtime($f) : 0;
?>
<tr>
    <td width="35%"><strong><?php echo h($d); ?></strong><br><small class="text-muted"><?php echo h($f); ?></small></td>
    <td>
        <?php echo $exists ? '<span class="status-badge status-ok">✅</span>' : '<span class="status-badge status-warning">⚠️</span>'; ?>
        <?php if ($exists): ?>
            <small class="text-muted ml-2"><?php echo formatBytes($size); ?></small>
            <small class="text-muted ml-2"><?php echo date('Y-m-d H:i', $mtime); ?></small>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>

<!-- Security checklist -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_security_checklist"); ?></div>
<div class="card-body p-3">
<div class="row">
    <div class="col-md-6 mb-2">
        <span class="status-badge status-ok"><?php echo __("adm_sec_xss_defense"); ?></span>
        <small class="text-muted d-block">htmlspecialchars()</small>
    </div>
    <div class="col-md-6 mb-2">
        <span class="status-badge status-ok"><?php echo __("adm_sec_admin_auth"); ?></span>
        <small class="text-muted d-block">Session-based auth</small>
    </div>
    <div class="col-md-6 mb-2">
        <span class="status-badge status-ok"><?php echo __("adm_sec_input_validation"); ?></span>
        <small class="text-muted d-block">sanitize_input()</small>
    </div>
    <div class="col-md-6 mb-2">
        <span class="status-badge status-ok"><?php echo __("adm_sec_path_traversal"); ?></span>
        <small class="text-muted d-block">realpath()</small>
    </div>
    <div class="col-md-6 mb-2">
        <?php $secHeaders = file_exists('security_helpers.php'); ?>
        <?php echo $secHeaders ? '<span class="status-badge status-ok">✅ Security</span>' : '<span class="status-badge status-warning">⚠️ Security</span>'; ?>
        <small class="text-muted d-block">security_helpers.php</small>
    </div>
    <div class="col-md-6 mb-2">
        <?php echo $serverInfo['ssl'] ? '<span class="status-badge status-ok">✅ HTTPS</span>' : '<span class="status-badge status-warning">⚠️ HTTPS</span>'; ?>
        <small class="text-muted d-block">SSL</small>
    </div>
    <div class="col-md-6 mb-2">
        <?php $displayErrors = ini_get('display_errors'); ?>
        <?php echo !$displayErrors ? '<span class="status-badge status-ok">✅ Hidden</span>' : '<span class="status-badge status-warning">⚠️ Shown</span>'; ?>
        <small class="text-muted d-block">display_errors</small>
    </div>
    <div class="col-md-6 mb-2">
        <?php $cookieSecure = ini_get('session.cookie_httponly'); ?>
        <?php echo $cookieSecure ? '<span class="status-badge status-ok">✅ HttpOnly</span>' : '<span class="status-badge status-warning">⚠️ HttpOnly</span>'; ?>
        <small class="text-muted d-block">session.cookie_httponly</small>
    </div>
</div>
</div>
</div>

<!-- PHP extensions -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_sys_php_extensions"); ?> (<?php echo count($loadedExtensions); ?>)</div>
<div class="card-body">
<div style="max-height: 200px; overflow-y: auto;">
<?php 
$importantExts = ['curl', 'gd', 'imagick', 'json', 'mbstring', 'openssl', 'pdo', 'redis', 'zip', 'zlib', 'opcache', 'apcu', 'intl', 'fileinfo'];
foreach ($loadedExtensions as $ext): 
    $isImportant = in_array(strtolower($ext), $importantExts);
?>
<span class="badge <?php echo $isImportant ? 'badge-primary' : 'badge-secondary'; ?> mr-1 mb-1"><?php echo h($ext); ?></span>
<?php endforeach; ?>
</div>
</div>
</div>

<!-- Cache management buttons -->
<div class="info-card">
<div class="card-header"><?php echo __("adm_card_cache_control"); ?></div>
<div class="card-body">
<div class="btn-group-vertical w-100">
    <?php if (function_exists('opcache_reset')): ?>
    <button type="button" class="btn btn-outline-warning btn-sm mb-2" onclick="if(confirm('<?php echo __("adm_sys_confirm_opcache"); ?>')){location.href='?clear_opcache=1';}">
        ⚡ Reset OPcache
    </button>
    <?php endif; ?>
    <?php if (function_exists('apcu_clear_cache')): ?>
    <button type="button" class="btn btn-outline-warning btn-sm mb-2" onclick="if(confirm('<?php echo __("adm_sys_confirm_apcu"); ?>')){location.href='?clear_apcu=1';}">
        🗄️ Reset APCu
    </button>
    <?php endif; ?>
    <button type="button" class="btn btn-outline-info btn-sm" onclick="location.reload();">
        🔃 Refresh
    </button>
</div>
</div>
</div>

</div></div>

<!-- ============================================================ -->
<!-- Language settings tab -->
<!-- ============================================================ -->
<div class="tab-pane fade" id="language">
<div class="card m-2 p-0">
<div class="card-header bg-info text-white"><?php echo __('language_title'); ?></div>
<div class="card-body">

<form action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="mode" value="save_language">

<div class="mb-4">
    <p class="text-muted"><?php echo __('language_description'); ?></p>
    <p class="text-muted small"><?php echo __('language_applies_to'); ?></p>
</div>

<div class="form-group mb-4">
    <label class="font-weight-bold mb-2"><?php echo __('language_select'); ?></label>
    <div class="d-flex flex-wrap" style="gap:15px;">
    <?php 
    $current_lang = get_current_lang();
    $available_langs = get_available_langs();
    foreach ($available_langs as $code => $name): 
        $checked = ($code === $current_lang) ? 'checked' : '';
        $active_class = ($code === $current_lang) ? 'border-primary bg-light' : '';
    ?>
        <label class="d-flex align-items-center p-3 border rounded <?php echo $active_class; ?>" style="cursor:pointer;min-width:200px;gap:12px;">
            <input type="radio" name="site_language" value="<?php echo $code; ?>" <?php echo $checked; ?> style="width:18px;height:18px;">
            <div>
                <div style="font-size:1.2em;">
                    <?php echo $code === 'ko' ? '🇰🇷' : '🇺🇸'; ?>
                    <strong><?php echo h($name); ?></strong>
                </div>
                <small class="text-muted"><?php echo $code === 'ko' ? 'Korean' : 'English'; ?></small>
            </div>
        </label>
    <?php endforeach; ?>
    </div>
</div>

<div class="p-3 bg-light rounded mb-3">
    <small class="text-muted">
        <strong><?php echo __('language_current'); ?>:</strong> 
        <?php echo $code === 'ko' ? '🇰🇷' : ''; ?><?php echo $code === 'en' ? '🇺🇸' : ''; ?>
        <?php echo h($available_langs[$current_lang] ?? 'Unknown'); ?>
        (<?php echo $current_lang; ?>)
    </small>
</div>

<button type="submit" class="btn btn-info">
    🌐 <?php echo __h('language_save'); ?>
</button>
</form>

</div>
</div>
</div>

</div><!-- tab-content -->

<!-- Tab activation script -->
<script>
// 로그 탭 클릭 시 1페이지로 초기화
function resetLogPage() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = parseInt(urlParams.get('log_page')) || 1;
    
    // 현재 2페이지 이상이면 1페이지로 새로고침
    if (currentPage > 1) {
        window.location.href = '?tab=logs';
        return false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // URL 파라미터에서 tab 값 확인
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    // 해시 또는 파라미터로 탭 결정
    const hash = window.location.hash.replace('#', '');
    const targetTab = hash || tabParam || 'dashboard';
    
    // 항상 올바른 탭 활성화 (Bootstrap tab API)
    var tabLink = document.querySelector('.nav-tabs-wrapper a[data-toggle="tab"][href="#' + targetTab + '"]');
    if (tabLink && typeof $ !== 'undefined') {
        $(tabLink).tab('show');
    }
    
    // URL에서 해시 제거 (스크롤 점프 방지)
    if (window.location.hash) {
        history.replaceState(null, '', window.location.pathname + window.location.search);
    }
    
    // 현재 테마의 배경 URL 입력창에 표시
    var currentTheme = document.querySelector('select#bg_theme_url');
    if (currentTheme && typeof updateBgInput === 'function') {
        updateBgInput(currentTheme.value);
    }
    
    // 모든 form submit 시 스크롤 위치 저장
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            sessionStorage.setItem('adminScrollPos', window.scrollY.toString());
        });
    });
});
</script>

<!-- Smooth page transition effect -->
<script>
(function(){
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href]');
        if (!link) return;
        var href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank') return;
        if (href.includes('mode=logout')) return;
        e.preventDefault();
        document.documentElement.classList.add('leaving');
        setTimeout(function() { location.href = href; }, 100);
    });
    document.addEventListener('submit', function() {
        document.documentElement.classList.add('leaving');
    });
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            document.documentElement.classList.remove('leaving');
            document.documentElement.classList.add('ready');
        }
    });
})();
</script>

<!-- Auto-logout timer -->
<?php 
$remaining = isset($_SESSION['last_action']) ? max(0, $timeout - (time() - $_SESSION['last_action'])) : $timeout;
// ✅ 자동 로그아웃 활성화 + 현재 페이지가 적용 대상인 경우에만 JS 로드
$_current_page = basename($_SERVER['SCRIPT_FILENAME']);
$_auto_logout_pages = $auto_logout_settings['pages'] ?? ['index.php', 'viewer.php', 'epub_viewer.php', 'txt_viewer.php', 'bookmark.php'];
$_is_auto_logout_target = in_array($_current_page, $_auto_logout_pages);

// ✅ "로그인 유지"로 로그인한 경우 자동 로그아웃 무시
$_is_remember_me = isset($_SESSION['remember_me']) && $_SESSION['remember_me'] === true;

if (($auto_logout_settings['enabled'] ?? true) && $_is_auto_logout_target && !$_is_remember_me): 
?>
<script>
window.SESSION_TIMEOUT = <?php echo $timeout; ?>;
window.SESSION_REMAINING = <?php echo $remaining; ?>;
</script>
<script src="./js/auto-logout.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<!-- Real-time resource monitoring JS -->
<script>
(function() {
    // localStorage에서 상태 복원
    let realtimeEnabled = localStorage.getItem('monitorEnabled') !== 'false';
    let refreshMs = parseInt(localStorage.getItem('monitorRefresh') || '3') * 1000;
    let realtimeInterval = null;
    let lastData = null;
    let lastTimestamp = null;
    let systemInitialized = false;
    
    // 그래프 데이터 (최근 30개)
    const MAX_POINTS = 30;
    const chartData = { labels: [], cpu: [], mem: [], diskR: [], diskW: [], netRx: [], netTx: [] };
    let cpuChart, memChart, diskChart, netChart;
    
    function formatSpeed(bytes) {
        if (bytes < 0) bytes = 0;
        if (bytes < 1024) return bytes.toFixed(0) + ' B/s';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB/s';
        return (bytes / 1024 / 1024).toFixed(1) + ' MB/s';
    }
    
    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        if (bytes < 1024 * 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        return (bytes / 1024 / 1024 / 1024).toFixed(2) + ' GB';
    }
    
    function getBadgeClass(v) {
        if (v > 80) return 'badge-danger';
        if (v > 60) return 'badge-warning';
        return 'badge-success';
    }
    
    // Chart.js 초기화
    function initCharts() {
        if (cpuChart) return; // 이미 초기화됨
        
        const baseOpts = {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 200 },
            scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
            plugins: { legend: { display: false } },
            elements: { point: { radius: 0 }, line: { tension: 0.3, borderWidth: 1.5 } }
        };
        
        const cpuCtx = document.getElementById('cpuChart')?.getContext('2d');
        if (cpuCtx) {
            cpuChart = new Chart(cpuCtx, {
                type: 'line',
                data: { labels: chartData.labels, datasets: [{ data: chartData.cpu, borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,0.2)', fill: true }] },
                options: { ...baseOpts, scales: { ...baseOpts.scales, y: { display: false, beginAtZero: true, max: 100 } } }
            });
        }
        
        const memCtx = document.getElementById('memChart')?.getContext('2d');
        if (memCtx) {
            memChart = new Chart(memCtx, {
                type: 'line',
                data: { labels: chartData.labels, datasets: [{ data: chartData.mem, borderColor: '#17a2b8', backgroundColor: 'rgba(23,162,184,0.2)', fill: true }] },
                options: { ...baseOpts, scales: { ...baseOpts.scales, y: { display: false, beginAtZero: true, max: 100 } } }
            });
        }
        
        const diskCtx = document.getElementById('diskChart')?.getContext('2d');
        if (diskCtx) {
            diskChart = new Chart(diskCtx, {
                type: 'line',
                data: { labels: chartData.labels, datasets: [
                    { data: chartData.diskR, borderColor: '#007bff', fill: false },
                    { data: chartData.diskW, borderColor: '#28a745', fill: false }
                ]},
                options: baseOpts
            });
        }
        
        const netCtx = document.getElementById('netChart')?.getContext('2d');
        if (netCtx) {
            netChart = new Chart(netCtx, {
                type: 'line',
                data: { labels: chartData.labels, datasets: [
                    { data: chartData.netRx, borderColor: '#007bff', fill: false },
                    { data: chartData.netTx, borderColor: '#28a745', fill: false }
                ]},
                options: baseOpts
            });
        }
    }
    
    // 시스템 탭 초기 정보 로드 (1회)
    function loadSystemInfo() {
        if (systemInitialized) return;
        systemInitialized = true;
        
        // 서버 정보 (IP, 인터넷 상태)
        fetch('?ajax_server_info=1').then(r => r.json()).then(data => {
            const el = id => document.getElementById(id);
            if (el('private-ip')) el('private-ip').textContent = data.private_ip || 'N/A';
            if (el('public-ip')) el('public-ip').textContent = data.public_ip || 'N/A';
            if (el('net-status')) {
                el('net-status').textContent = data.internet ? 'Connected' : 'Disconnected';
                el('net-status').className = 'badge ' + (data.internet ? 'badge-success' : 'badge-danger');
            }
            if (el('net-latency') && data.latency) el('net-latency').textContent = '(' + data.latency + 'ms)';
        }).catch(() => {});
        
        // 시스템 초기 정보 (CPU 모델, 메모리 용량, 업타임, 네트워크 인터페이스, 웹서버)
        fetch('?ajax_init_system=1').then(r => r.json()).then(data => {
            const el = id => document.getElementById(id);
            
            // CPU 정보
            if (el('cpu-model')) el('cpu-model').textContent = data.cpu.model;
            if (el('cpu-cores')) el('cpu-cores').textContent = data.cpu.cores + '';
            if (el('cpu-threads')) el('cpu-threads').textContent = data.cpu.threads + '';
            
            // 메모리 총량
            if (el('mem-total')) el('mem-total').textContent = formatBytes(data.memory.total);
            
            // 업타임
            if (el('server-uptime')) el('server-uptime').textContent = data.uptime;
            
            // 네트워크 인터페이스는 실시간 데이터에서 처리
            // (ajax_init_system과 ajax_resources의 인터페이스 이름이 다를 수 있음)
            
            // 웹서버 프로세스
            if (data.webserver && data.webserver.length > 0) {
                const wsList = document.getElementById('webserver-list');
                if (wsList) {
                    let html = '<table class="table table-sm table-borderless mb-0">';
                    data.webserver.forEach(ws => {
                        html += '<tr><td>🟢 <strong>' + ws.name + '</strong></td>';
                        html += '<td>' + ws.count + ' processes</td>';
                        html += '<td class="text-right">' + formatBytes(ws.memory) + '</td></tr>';
                    });
                    html += '</table>';
                    wsList.innerHTML = html;
                }
            } else {
                const wsList = document.getElementById('webserver-list');
                if (wsList) wsList.innerHTML = '<span class="text-muted">No running web server found</span>';
            }
        }).catch(() => {});
    }
    
    // 실시간 데이터 가져오기
    function fetchRealtimeData() {
        if (!realtimeEnabled) return;
        
        fetch('?ajax_resources=1').then(r => r.json()).then(data => {
            const now = new Date();
            chartData.labels.push(now.toLocaleTimeString('ko-KR', {hour:'2-digit',minute:'2-digit',second:'2-digit'}));
            if (chartData.labels.length > MAX_POINTS) chartData.labels.shift();
            
            chartData.cpu.push(data.cpu_usage);
            chartData.mem.push(data.memory_percent);
            if (chartData.cpu.length > MAX_POINTS) chartData.cpu.shift();
            if (chartData.mem.length > MAX_POINTS) chartData.mem.shift();
            
            // 속도 계산
            let diskRSpeed = 0, diskWSpeed = 0, netRxSpeed = 0, netTxSpeed = 0;
            if (lastData && lastTimestamp) {
                const dt = data.timestamp - lastTimestamp;
                if (dt > 0) {
                    diskRSpeed = Math.max(0, (data.disk_read - lastData.disk_read) / dt);
                    diskWSpeed = Math.max(0, (data.disk_write - lastData.disk_write) / dt);
                    netRxSpeed = Math.max(0, (data.net_rx - lastData.net_rx) / dt);
                    netTxSpeed = Math.max(0, (data.net_tx - lastData.net_tx) / dt);
                }
            }
            lastData = data;
            lastTimestamp = data.timestamp;
            
            chartData.diskR.push(diskRSpeed);
            chartData.diskW.push(diskWSpeed);
            chartData.netRx.push(netRxSpeed);
            chartData.netTx.push(netTxSpeed);
            if (chartData.diskR.length > MAX_POINTS) { chartData.diskR.shift(); chartData.diskW.shift(); }
            if (chartData.netRx.length > MAX_POINTS) { chartData.netRx.shift(); chartData.netTx.shift(); }
            
            // UI 업데이트
            const el = id => document.getElementById(id);
            if (el('cpu-current')) { el('cpu-current').textContent = data.cpu_usage + '%'; el('cpu-current').className = 'badge badge-sm ' + getBadgeClass(data.cpu_usage); }
            if (el('mem-current')) { el('mem-current').textContent = data.memory_percent + '%'; el('mem-current').className = 'badge badge-sm ' + getBadgeClass(data.memory_percent); }
            if (el('disk-current')) el('disk-current').textContent = formatSpeed(diskRSpeed + diskWSpeed);
            if (el('net-current')) el('net-current').textContent = formatSpeed(netRxSpeed + netTxSpeed);
            if (el('monitor-datetime')) el('monitor-datetime').textContent = now.toLocaleDateString('ko-KR') + ' ' + now.toLocaleTimeString('ko-KR');
            if (el('disk-read')) el('disk-read').textContent = formatSpeed(diskRSpeed);
            if (el('disk-write')) el('disk-write').textContent = formatSpeed(diskWSpeed);
            if (el('net-rx')) el('net-rx').textContent = formatSpeed(netRxSpeed);
            if (el('net-tx')) el('net-tx').textContent = formatSpeed(netTxSpeed);
            
            // CPU/메모리 프로그레스바 업데이트
            const cpuBar = el('cpu-bar');
            if (cpuBar) {
                cpuBar.style.width = data.cpu_usage + '%';
                cpuBar.textContent = data.cpu_usage + '%';
                cpuBar.className = 'progress-bar bg-' + (data.cpu_usage > 80 ? 'danger' : (data.cpu_usage > 50 ? 'warning' : 'success'));
            }
            const memBar = el('mem-bar');
            if (memBar) {
                memBar.style.width = data.memory_percent + '%';
                memBar.textContent = data.memory_percent + '%';
                memBar.className = 'progress-bar bg-' + (data.memory_percent > 85 ? 'danger' : (data.memory_percent > 70 ? 'warning' : 'success'));
            }
            
            // 트래픽 카드 업데이트
            if (el('traffic-rx-speed')) el('traffic-rx-speed').textContent = formatSpeed(netRxSpeed);
            if (el('traffic-tx-speed')) el('traffic-tx-speed').textContent = formatSpeed(netTxSpeed);
            if (el('traffic-rx-total')) el('traffic-rx-total').textContent = formatBytes(data.net_rx);
            if (el('traffic-tx-total')) el('traffic-tx-total').textContent = formatBytes(data.net_tx);
            
            // 메모리 상세
            if (el('mem-used')) el('mem-used').textContent = formatBytes(data.memory_used);
            if (el('mem-free')) el('mem-free').textContent = formatBytes(data.memory_total - data.memory_used);
            if (el('mem-total')) el('mem-total').textContent = formatBytes(data.memory_total);
            
            // 네트워크 인터페이스 활성 표시 (실시간 데이터 기반)
            if (data.interfaces && data.interfaces.length > 0) {
                let activeCount = 0;
                const ifaceList = document.getElementById('iface-list');
                
                // 어댑터 이름에서 링크 속도 추출
                function getLinkSpeed(name) {
                    if (/2\.5G|2,5G/i.test(name)) return '2.5 Gbps';
                    if (/10G/i.test(name)) return '10 Gbps';
                    if (/1000|Gigabit/i.test(name)) return '1 Gbps';
                    if (/100M/i.test(name)) return '100 Mbps';
                    return '';
                }
                
                // 인터페이스 목록 직접 업데이트
                let html = '';
                data.interfaces.forEach((iface, idx) => {
                    let isActive = false;
                    let speed = '';
                    
                    // 트래픽이 있으면 활성 (rx 또는 tx가 0보다 크면)
                    if (iface.rx > 0 || iface.tx > 0) {
                        isActive = true;
                    }
                    
                    // 속도 계산 (이전 데이터와 비교)
                    if (lastData && lastData.interfaces && lastData.interfaces[idx]) {
                        const rxDiff = iface.rx - (lastData.interfaces[idx].rx || 0);
                        const txDiff = iface.tx - (lastData.interfaces[idx].tx || 0);
                        if (rxDiff > 0 || txDiff > 0) {
                            const totalSpeed = (rxDiff + txDiff) / (refreshMs / 1000);
                            speed = formatSpeed(totalSpeed);
                        }
                    }
                    if (isActive) activeCount++;
                    
                    const icon = isActive ? '🟢' : '⚪';
                    const linkSpeed = getLinkSpeed(iface.name);
                    
                    html += '<div style="display:flex;align-items:center;justify-content:center;gap:2px;padding:1px 0;">';
                    html += '<span style="line-height:1;">' + icon + '</span>';
                    html += '<strong style="font-size:0.95em;">' + iface.name + '</strong>';
                    if (linkSpeed) html += '<span class="badge badge-info ml-1">' + linkSpeed + '</span>';
                    if (speed) html += '<span class="badge badge-success ml-1">' + speed + '</span>';
                    html += '</div>';
                });
                if (ifaceList) ifaceList.innerHTML = html;
                
                const countEl = el('active-iface-count');
                if (countEl) countEl.textContent = '(' + activeCount + '/' + data.interfaces.length + ' active)';
            }
            
            // 차트 업데이트
            cpuChart?.update('none');
            memChart?.update('none');
            diskChart?.update('none');
            netChart?.update('none');
        }).catch(e => console.log('Monitor error:', e));
    }
    
    window.toggleRealtimeMonitor = function() {
        realtimeEnabled = !realtimeEnabled;
        localStorage.setItem('monitorEnabled', realtimeEnabled);
        
        const statusEl = document.getElementById('realtimeStatus');
        if (realtimeEnabled) {
            statusEl.textContent = '⏸️';
            startRealtimeMonitor();
        } else {
            statusEl.textContent = '▶️';
            stopRealtimeMonitor();
        }
    };
    
    function startRealtimeMonitor() {
        if (realtimeInterval) clearInterval(realtimeInterval);
        initCharts();
        loadSystemInfo();
        fetchRealtimeData();
        realtimeInterval = setInterval(fetchRealtimeData, refreshMs);
    }
    
    function stopRealtimeMonitor() {
        if (realtimeInterval) { clearInterval(realtimeInterval); realtimeInterval = null; }
    }
    
    function updateButtonState() {
        const statusEl = document.getElementById('realtimeStatus');
        if (statusEl) statusEl.textContent = realtimeEnabled ? '⏸️' : '▶️';
        const refreshEl = document.getElementById('refreshInterval');
        if (refreshEl) refreshEl.value = refreshMs / 1000;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        updateButtonState();
        
        // 갱신 간격 변경
        document.getElementById('refreshInterval')?.addEventListener('change', function() {
            refreshMs = parseInt(this.value) * 1000;
            localStorage.setItem('monitorRefresh', this.value);
            if (realtimeEnabled && realtimeInterval) startRealtimeMonitor();
        });
        
        // 탭 전환 감지
        const systemTab = document.querySelector('a[href="#system"]');
        if (systemTab) {
            $(systemTab).on('shown.bs.tab', function() {
                initCharts();
                loadSystemInfo();
                if (realtimeEnabled) startRealtimeMonitor();
            });
            $(systemTab).on('hidden.bs.tab', function() { stopRealtimeMonitor(); });
        }
        
        // 현재 시스템 탭이면 시작
        if (window.location.hash === '#system') {
            initCharts();
            loadSystemInfo();
            if (realtimeEnabled) startRealtimeMonitor();
        }
    });
    
    window.addEventListener('beforeunload', function() { stopRealtimeMonitor(); });
})();
</script>

<!-- Session keepalive script (CSRF token refresh) -->
<script>
(function(){
    var KEEPALIVE_INTERVAL = 5 * 60 * 1000; // 5분마다
    var keepaliveTimer = null;
    
    function keepSessionAlive() {
        fetch('init.php?check_session=1&extend=1', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function(r){ return r.json(); }).then(function(data){
            if (data.status === 'logged_out' || data.status === 'expired') {
                clearInterval(keepaliveTimer);
                alert('<?php echo __("adm_session_expired"); ?>');
                location.href = 'login.php';
            }
        }).catch(function(){});
    }
    
    // 5분마다 세션 갱신
    keepaliveTimer = setInterval(keepSessionAlive, KEEPALIVE_INTERVAL);
    
    // 페이지 떠날 때 정리
    window.addEventListener('beforeunload', function() {
        if (keepaliveTimer) clearInterval(keepaliveTimer);
    });
})();
</script>

</body></html>
<?php
} else {
    echo "<div class='container mt-5'><div class='alert alert-danger'>" . __("adm_not_admin_redirect") . "</div></div>";
    echo "<meta http-equiv=\"refresh\" content=\"3; url=index.php?bidx=0\">";
}
?>