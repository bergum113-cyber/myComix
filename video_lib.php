<?php
/**
 * myComix 동영상 도우미 — FileStation 동영상 플레이어 기능 이식(자막·변환 재생·장면 미리보기·동영상 정보)
 * ★ (2026-10-31) viewer.php 에서만 불러 씀(함수만 있음 — 직접 열어도 아무것도 하지 않음)
 *
 *  · mcv_ffmpeg()/mcv_ffprobe()     관리자 설정의 FFmpeg·FFprobe 경로(설정 경로만 — 종전 규칙 그대로)
 *  · mcv_probe($file, $ffprobe_json) 길이·영상 코덱·해상도·음성 트랙(캐시) — FFprobe 결과가 있으면 그것, 없으면 ffmpeg -i 로
 *  · mcv_sidecar_subs($file)          같은 폴더의 자막 파일(srt·smi·sami·vtt·ass·ssa) — FileStation 과 같은 찾기·순위·인코딩 처리
 *  · mcv_zip_subs($zipfile, $entry)   압축 파일 속 동영상 옆 자막
 *  · mcv_slot_lock($kind, $max)       ffmpeg 동시 실행 자리(넘치면 기다리지 않고 '바쁨')
 *  · mcv_frame_send / mcv_transcode_send  장면 그림 / 실시간 변환 재생(조각 MP4)
 */

if (!function_exists('mcv_ffmpeg')) {

function mcv_ffmpeg() {
    $p = $GLOBALS['ffmpeg_path'] ?? '';
    return (is_string($p) && $p !== '' && @is_file($p)) ? $p : '';
}

function mcv_ffprobe() {
    $p = $GLOBALS['ffprobe_path'] ?? '';
    return (is_string($p) && $p !== '' && @is_file($p)) ? $p : '';
}

function mcv_is_windows() {
    return DIRECTORY_SEPARATOR === '\\' || stripos(PHP_OS, 'WIN') === 0;
}

function mcv_arg($s) {
    if (function_exists('escape_shell_arg_safe')) return escape_shell_arg_safe((string)$s);
    return mcv_is_windows() ? '"' . str_replace('"', '""', (string)$s) . '"' : escapeshellarg((string)$s);
}

function mcv_cache_dir($sub) {
    // ★ (2026-11-03 22:14) cache/ → src/video/ — cache 는 계정별 임시 압축 풀기 전용(펜닐). src/.htaccess 가 그림(jpg)은 열어 두므로 src/video/.htaccess 로 전부 막음(없으면 만듦)
    $root = __DIR__ . '/src/video';
    if (!is_dir($root)) @mkdir($root, 0755, true);
    if (is_dir($root) && !is_file($root . '/.htaccess')) {
        @file_put_contents($root . '/.htaccess', "<IfModule mod_authz_core.c>\n<FilesMatch \".*\">\nRequire all denied\n</FilesMatch>\n</IfModule>\n<IfModule !mod_authz_core.c>\n<FilesMatch \".*\">\nOrder deny,allow\nDeny from all\n</FilesMatch>\n</IfModule>\n");
    }
    $d = $root . '/' . $sub;
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return is_dir($d) ? $d : '';
}

/** 파일 하나를 가리키는 캐시 열쇠(경로·크기·수정 시각) — 파일이 바뀌면 새 열쇠 */
function mcv_file_key($file) {
    return md5($file . '|' . (int)@filesize($file) . '|' . (int)@filemtime($file));
}

/**
 * 영상 정보 — ['dur' => 초, 'vcodec' => 'h264', 'w' => , 'h' => , 'audio' => [['lang','codec','ch','title','def'], ...]]
 * @param array|null $ffprobe_json viewer.php 가 이미 돌린 ffprobe 결과(있으면 다시 돌리지 않음)
 */
function mcv_probe($file, $ffprobe_json = null) {
    static $memo = [];
    if (isset($memo[$file])) return $memo[$file];
    $key = mcv_file_key($file);
    $cdir = mcv_cache_dir('vprobe');
    $cf = $cdir ? $cdir . '/' . $key . '.json' : '';
    if ($cf && is_file($cf)) {
        $c = @json_decode((string)@file_get_contents($cf), true);
        // ★ (2026-11-04 00:33) ffmpeg 간이 정보로 저장된 것은 ffprobe 가 설정되면 다시 읽음(src 표시)
        if (is_array($c) && isset($c['dur']) && !(($c['src'] ?? '') === 'ffmpeg' && empty($c['pt']) && mcv_ffprobe() !== '')) return $memo[$file] = $c; // pt: ffprobe 를 이미 해 봤는데 못 읽은 파일(다시 돌리지 않음)
    }
    $r = ['dur' => 0.0, 'vcodec' => '', 'w' => 0, 'h' => 0, 'pix' => '', 'audio' => []];
    $ok = false;
    if (!is_array($ffprobe_json) && mcv_ffprobe() !== '') {
        $out = @shell_exec(mcv_arg(mcv_ffprobe()) . ' -v quiet -print_format json -show_format -show_streams ' . mcv_arg($file) . ' 2>&1');
        $ffprobe_json = $out ? @json_decode($out, true) : null;
    }
    if (is_array($ffprobe_json) && isset($ffprobe_json['streams'])) {
        $ok = true;
        $r['src'] = 'ffprobe';
        $r['dur'] = (float)($ffprobe_json['format']['duration'] ?? 0);
        foreach ($ffprobe_json['streams'] as $s) {
            $type = $s['codec_type'] ?? '';
            if ($type === 'video' && $r['vcodec'] === '' && empty($s['disposition']['attached_pic'])) {
                $r['vcodec'] = strtolower((string)($s['codec_name'] ?? ''));
                $r['w'] = (int)($s['width'] ?? 0);
                $r['h'] = (int)($s['height'] ?? 0);
                $r['pix'] = (string)($s['pix_fmt'] ?? '');
                if ($r['dur'] <= 0 && isset($s['duration'])) $r['dur'] = (float)$s['duration'];
            } elseif ($type === 'audio') {
                $r['audio'][] = [
                    'lang' => (string)($s['tags']['language'] ?? ''),
                    'codec' => strtoupper((string)($s['codec_name'] ?? '')),
                    'ch' => (int)($s['channels'] ?? 0),
                    'title' => (string)($s['tags']['title'] ?? ''),
                    'def' => !empty($s['disposition']['default']),
                ];
            }
        }
    } elseif (mcv_ffmpeg() !== '') {
        // FFprobe 가 없으면 ffmpeg -i 출력으로(FileStation 과 같은 방식)
        $out = (string)@shell_exec(mcv_arg(mcv_ffmpeg()) . ' -hide_banner -i ' . mcv_arg($file) . ' 2>&1');
        if ($out !== '') {
            $ok = true;
            $r['src'] = 'ffmpeg';
            if (mcv_ffprobe() !== '') $r['pt'] = 1; // ★ (2026-11-04 01:16) ffprobe 가 있는데도 못 읽어 ffmpeg 로 얻은 것 — 매번 둘 다 다시 돌지 않게
            if (preg_match('/Duration:\s*(\d+):(\d+):(\d+)\.(\d+)/', $out, $m)) $r['dur'] = $m[1] * 3600 + $m[2] * 60 + $m[3] + ('0.' . $m[4]);
            if (preg_match('/Stream\s+#\d+:\d+[^:]*:\s*Video:\s*(\w+)[^\n]*?,\s*(\w+)\(?[^\n]*?(\d{2,5})x(\d{2,5})/i', $out, $m)) {
                $r['vcodec'] = strtolower($m[1]); $r['pix'] = strtolower($m[2]); $r['w'] = (int)$m[3]; $r['h'] = (int)$m[4];
            }
            if (preg_match_all('/Stream\s+#\d+:\d+(?:\[[^\]]*\])?(?:\(([a-z]+)\))?:\s*Audio:\s*(\w+)[^\n]*/i', $out, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    $ch = preg_match('/\b(mono)\b/i', $m[0]) ? 1 : (preg_match('/\bstereo\b/i', $m[0]) ? 2 : (preg_match('/\b5\.1\b/', $m[0]) ? 6 : (preg_match('/\b7\.1\b/', $m[0]) ? 8 : 0)));
                    $r['audio'][] = ['lang' => (string)($m[1] ?? ''), 'codec' => strtoupper($m[2]), 'ch' => $ch, 'title' => '', 'def' => false];
                }
            }
        }
    }
    if ($ok && $cf) {
        @file_put_contents($cf, json_encode($r, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    return $memo[$file] = $r;
}

/** 자막 파일 바이트 → UTF-8(FileStation 과 같은 판정: UTF-16 BOM · UTF-8 BOM · 아니면 CP949/EUC-KR/SJIS) */
function mcv_sub_text($b) {
    if (!is_string($b) || $b === '') return '';
    if (strlen($b) >= 2 && $b[0] === "\xFF" && $b[1] === "\xFE") return (string)@mb_convert_encoding(substr($b, 2), 'UTF-8', 'UTF-16LE');
    if (strlen($b) >= 2 && $b[0] === "\xFE" && $b[1] === "\xFF") return (string)@mb_convert_encoding(substr($b, 2), 'UTF-8', 'UTF-16BE');
    if (strlen($b) >= 3 && substr($b, 0, 3) === "\xEF\xBB\xBF") return substr($b, 3);
    if (function_exists('mb_check_encoding') && !mb_check_encoding($b, 'UTF-8')) {
        // ★ (2026-11-09 11:38) 바꾼 결과가 아니라 원래 바이트로 판정 — 바꾼 결과는 늘 올바른 UTF-8('?'로 채움)이라 SJIS(일본어) 자막도 CP949로 읽혀 깨졌음
        //   CP949로 읽을 수 있으면(한국어) 예전과 같이 CP949, CP949로는 안 되고 SJIS로 되면 SJIS, 둘 다 아니면 예전과 같이 CP949
        if (!mb_check_encoding($b, 'CP949') && mb_check_encoding($b, 'SJIS')) {
            $c = @mb_convert_encoding($b, 'UTF-8', 'SJIS');
            if (is_string($c) && $c !== '') return $c;
        }
        $c = @mb_convert_encoding($b, 'UTF-8', 'CP949');
        if (is_string($c) && $c !== '' && mb_check_encoding($c, 'UTF-8')) return $c;
        $c = @mb_convert_encoding($b, 'UTF-8', 'SJIS');
        return is_string($c) ? $c : '';
    }
    return $b;
}

/** 자막 순위 — 이름이 똑같은 것 0, '영상이름.ko' 같은 것 1, 영상 이름이 자막 이름으로 시작 2, 그 밖 3(FileStation 과 같음) */
function mcv_sub_rank($subName, $videoBase) {
    $isSep = function ($c) { return $c === '.' || $c === '-' || $c === '_' || $c === ' ' || $c === '[' || $c === '('; };
    $b = strtolower(pathinfo($subName, PATHINFO_FILENAME));
    $vb = strtolower($videoBase);
    if ($b === $vb) return 0;
    if (strpos($b, $vb) === 0 && $isSep(substr($b, strlen($vb), 1))) return 1;
    if ($b !== '' && strpos($vb, $b) === 0 && $isSep(substr($vb, strlen($b), 1))) return 2;
    return 3;
}

/** 같은 폴더 자막 — [['name','ext','content','rank'], ...] (파일당 2MB·최대 12개·합계 8MB) */
function mcv_sidecar_subs($file) {
    $out = [];
    $dir = dirname($file);
    $vb = strtolower(pathinfo($file, PATHINFO_FILENAME));
    if ($vb === '') return $out;
    $names = @scandir($dir) ?: [];
    $total = 0;
    foreach ($names as $f) {
        if ($f === '.' || $f === '..' || $f[0] === '.') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, ['srt', 'smi', 'sami', 'vtt', 'ass', 'ssa'], true)) continue;
        $fb = strtolower(pathinfo($f, PATHINFO_FILENAME));
        if (!($fb === $vb || strpos($fb, $vb) === 0 || ($fb !== '' && strpos($vb, $fb) === 0))) continue;
        $full = $dir . DIRECTORY_SEPARATOR . $f;
        if (!@is_file($full) || @is_link($full)) continue;
        $sz = (int)@filesize($full);
        if ($sz <= 0 || $sz > 2097152 || $total + $sz > 8388608) continue;
        $c = mcv_sub_text((string)@file_get_contents($full));
        if ($c === '') continue;
        $total += $sz;
        $out[] = ['name' => $f, 'ext' => $ext, 'content' => $c, 'rank' => mcv_sub_rank($f, $vb)];
        if (count($out) >= 12) break;
    }
    usort($out, function ($a, $b) { return ($a['rank'] <=> $b['rank']) ?: strnatcasecmp($a['name'], $b['name']); });
    return $out;
}

/** 압축 파일 속 동영상(entry) 옆 자막 — 같은 폴더(압축 안)·같은 찾기 규칙 */
function mcv_zip_subs($zipfile, $entry) {
    $out = [];
    if (!class_exists('ZipArchive')) return $out;
    $z = new ZipArchive;
    if ($z->open($zipfile) !== TRUE) return $out;
    $entry = str_replace('\\', '/', (string)$entry);
    $vdir = (strpos($entry, '/') !== false) ? substr($entry, 0, strrpos($entry, '/') + 1) : '';
    $vb = strtolower(pathinfo($entry, PATHINFO_FILENAME));
    $total = 0;
    $names = function_exists('mc_zip_names') ? mc_zip_names($z) : [];
    for ($i = 0; $i < $z->numFiles && count($out) < 12; $i++) {
        $n = $names[$i] ?? $z->getNameIndex($i);
        if (!is_string($n) || $n === '' || substr($n, -1) === '/') continue;
        $d = (strpos($n, '/') !== false) ? substr($n, 0, strrpos($n, '/') + 1) : '';
        if ($d !== $vdir) continue;
        $f = substr($n, strlen($d));
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, ['srt', 'smi', 'sami', 'vtt', 'ass', 'ssa'], true)) continue;
        $fb = strtolower(pathinfo($f, PATHINFO_FILENAME));
        if (!($fb === $vb || strpos($fb, $vb) === 0 || ($fb !== '' && strpos($vb, $fb) === 0))) continue;
        $st = @$z->statIndex($i);
        $sz = is_array($st) ? (int)$st['size'] : 0;
        if ($sz <= 0 || $sz > 2097152 || $total + $sz > 8388608) continue;
        $raw = $z->getFromIndex($i);
        $c = mcv_sub_text(is_string($raw) ? $raw : '');
        if ($c === '') continue;
        $total += $sz;
        $out[] = ['name' => $f, 'ext' => $ext, 'content' => $c, 'rank' => mcv_sub_rank($f, $vb)];
    }
    $z->close();
    usort($out, function ($a, $b) { return ($a['rank'] <=> $b['rank']) ?: strnatcasecmp($a['name'], $b['name']); });
    return $out;
}

/**
 * ffmpeg 동시 실행 자리 — 비어 있는 자리를 잡으면 파일 손잡이(잡고 있는 동안 자리 차지), 다 차 있으면 null(기다리지 않음)
 */
function mcv_slot_lock($kind, $max) {
    $d = mcv_cache_dir('vlock');
    if ($d === '') return null;
    for ($i = 0; $i < $max; $i++) {
        $fp = @fopen($d . '/' . preg_replace('/[^a-z0-9_]/', '', $kind) . '_' . $i . '.lock', 'c');
        if (!$fp) continue;
        if (@flock($fp, LOCK_EX | LOCK_NB)) return $fp;
        @fclose($fp);
    }
    return null;
}

function mcv_slot_unlock($fp) {
    if ($fp) { @flock($fp, LOCK_UN); @fclose($fp); }
}

/** proc_open — 윈도우는 cmd 를 거치지 않고(bypass_shell) 바로, 리눅스는 exec 로 셸을 ffmpeg 로 바꿔(끊을 때 ffmpeg 까지 정확히 끝나게) */
function mcv_proc_open($cmd, $desc, &$pipes) {
    if (!function_exists('proc_open')) return false;
    if (mcv_is_windows()) return @proc_open($cmd, $desc, $pipes, null, null, ['bypass_shell' => true]);
    return @proc_open('exec ' . $cmd, $desc, $pipes);
}

/**
 * ★ (2026-10-31) 장면 캐시 정리 — 30일 넘게 새 장면이 안 생긴 영상 폴더를 지움(가끔 한 번, 한 번에 50개까지 · 쌓이기만 하던 것 방지)
 *   영상 하나 = src/video/vframes/<열쇠>/ 폴더(파일이 바뀌면 열쇠가 바뀌어 옛 폴더는 더 안 쓰임)
 */
function mcv_frames_gc() {
    $root = __DIR__ . '/src/video/vframes'; // ★ (2026-11-03 22:14) cache 밖으로
    if (!is_dir($root)) return;
    $limit = time() - 30 * 86400;
    $n = 0;
    foreach ((@scandir($root) ?: []) as $d) {
        if (!preg_match('/^[0-9a-f]{32}$/', $d)) continue;
        $p = $root . '/' . $d;
        if (!is_dir($p) || is_link($p) || (int)@filemtime($p) > $limit) continue;
        foreach ((@scandir($p) ?: []) as $f) { if ($f !== '.' && $f !== '..' && is_file($p . '/' . $f)) @unlink($p . '/' . $f); }
        @rmdir($p);
        if (++$n >= 50) break;
    }
}

/**
 * 재생바 미리보기 장면(작은 JPEG) — 캐시(src/video/vframes/<파일 열쇠>/<초>.jpg), 동시 2개까지(넘치면 503 → 플레이어가 잠시 뒤 다시)
 */
function mcv_frame_send($file, $t) {
    $ff = mcv_ffmpeg();
    if ($ff === '') { http_response_code(404); exit; }
    if (mt_rand(1, 300) === 1) mcv_frames_gc();
    $t = max(0, (int)floor((float)$t));
    $cdir = mcv_cache_dir('vframes/' . mcv_file_key($file));
    if ($cdir === '') { http_response_code(500); exit; }
    $cf = $cdir . '/' . $t . '.jpg';
    if (!is_file($cf) || filesize($cf) === 0) {
        $slot = mcv_slot_lock('frame', 2);
        if ($slot === null) { http_response_code(503); header('Retry-After: 1'); exit; }
        if (!is_file($cf)) {
            $tmp = $cf . '.' . bin2hex(random_bytes(4)) . '.tmp.jpg'; // ★ (2026-11-02 09:57) Windows Apache 는 스레드끼리 pid 가 같아 겹쳤음
            $cmd = mcv_arg($ff) . ' -hide_banner -loglevel error -nostdin -ss ' . $t . ' -i ' . mcv_arg($file)
                 . ' -map 0:v:0 -frames:v 1 -vf "scale=320:-2" -q:v 6 -f image2 -y ' . mcv_arg($tmp);
            @exec($cmd . (mcv_is_windows() ? ' 2>NUL' : ' 2>/dev/null'), $o, $rc);
            if (is_file($tmp) && filesize($tmp) > 0) { if (!@rename($tmp, $cf)) @unlink($tmp); }
            else @unlink($tmp);
        }
        mcv_slot_unlock($slot);
    }
    if (!is_file($cf)) { http_response_code(404); exit; }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($cf));
    header('Cache-Control: private, max-age=86400');
    readfile($cf);
    exit;
}

/**
 * 실시간 변환 재생 — 조각 MP4(H.264 + AAC 2ch)를 그대로 흘려보냄(FileStation transcodeShareStream 과 같은 방식)
 * @param float $ss 시작 위치(초 — 이 위치부터 변환, 브라우저 시각 0 = 실제 ss 초)
 * @param int   $a  음성 트랙 순번(0부터)
 * @param int   $q  화질(세로 픽셀 1080·720·480, 0 = 원본 크기)
 */
function mcv_transcode_send($file, $ss, $a, $q) {
    $ff = mcv_ffmpeg();
    if ($ff === '') { http_response_code(501); header('X-Transcode-Error: ffmpeg not set'); exit; }
    if (!function_exists('proc_open')) { http_response_code(501); header('X-Transcode-Error: proc_open disabled'); exit; }
    // 동시 변환 자리: 서버 전체 4개 — 넘치면 503(화면에 '바쁨' 안내). 영상을 옮기면 브라우저가 앞 요청을 끊어 자리가 곧 빔
    $slot = mcv_slot_lock('tc', 4);
    if ($slot === null) { http_response_code(503); header('Retry-After: 2'); header('X-Transcode-Error: busy'); exit; }
    $info = mcv_probe($file);
    $ss = max(0.0, min((float)$ss, max(0.0, (float)($info['dur'] ?? 0) - 1)));
    $na = count($info['audio'] ?? []);
    $a = ($na > 0) ? max(0, min((int)$a, $na - 1)) : -1;
    $vf = '';
    if (in_array((int)$q, [1080, 720, 480], true) && (int)($info['h'] ?? 0) > (int)$q) $vf = ' -vf "scale=-2:' . (int)$q . '"';
    $cmd = mcv_arg($ff) . ' -hide_banner -loglevel error -nostdin'
         . ($ss > 0 ? ' -ss ' . sprintf('%.3F', $ss) : '')
         . ' -i ' . mcv_arg($file)
         . ' -map 0:v:0' . ($a >= 0 ? ' -map 0:a:' . $a : '')
         . ' -sn -dn' . $vf
         . ' -c:v libx264 -preset veryfast -crf 23 -profile:v high -level 4.1 -pix_fmt yuv420p -g 48 -keyint_min 48 -sc_threshold 0'
         . ($a >= 0 ? ' -c:a aac -b:a 160k -ac 2' : ' -an')
         . ' -movflags frag_keyframe+empty_moov+default_base_moof -frag_duration 500000 -f mp4 pipe:1';
    $errLog = mcv_cache_dir('vlock');
    $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ($errLog !== '' ? ['file', $errLog . '/ffmpeg_tc_err.log', 'w'] : ['pipe', 'w'])];
    $pipes = [];
    $proc = mcv_proc_open($cmd, $desc, $pipes);
    if (!is_resource($proc)) { mcv_slot_unlock($slot); http_response_code(500); header('X-Transcode-Error: start failed'); exit; }
    @fclose($pipes[0]);
    if (isset($pipes[2]) && is_resource($pipes[2])) @fclose($pipes[2]);
    @set_time_limit(0);
    @ignore_user_abort(true);   // 브라우저가 끊으면 아래에서 알아채고 ffmpeg 를 끝냄
    while (ob_get_level()) ob_end_clean();
    if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
    @ini_set('zlib.output_compression', 'Off');
    header_remove('Content-Length');
    header('Content-Type: video/mp4');
    header('Cache-Control: no-cache, no-store');
    header('Accept-Ranges: none');
    header('X-Accel-Buffering: no');
    $sent = 0;
    while (!feof($pipes[1])) {
        $chunk = fread($pipes[1], 65536);
        if ($chunk === false) break;
        if ($chunk === '') { usleep(20000); if (connection_aborted()) break; continue; }
        echo $chunk;
        $sent += strlen($chunk);
        @flush();
        if (connection_aborted()) break;
    }
    @fclose($pipes[1]);
    $st = @proc_get_status($proc);
    if (is_array($st) && !empty($st['running'])) @proc_terminate($proc);
    @proc_close($proc);
    mcv_slot_unlock($slot);
    exit;
}

}
