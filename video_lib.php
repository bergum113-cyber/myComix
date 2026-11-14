<?php
/**
 * myComix 동영상 도우미 — FileStation 동영상 플레이어 기능 이식(자막·변환 재생·장면 미리보기·동영상 정보)
 * ★ (2026-10-31) viewer.php 에서만 불러 씀(함수만 있음 — 직접 열어도 아무것도 하지 않음)
 *
 *  · mcv_ffmpeg()/mcv_ffprobe()     관리자 설정의 FFmpeg·FFprobe 경로(설정 경로만 — 종전 규칙 그대로)
 *  · mcv_probe($file, $ffprobe_json) 길이·영상 코덱·해상도·음성 트랙(캐시) — FFprobe 결과가 있으면 그것, 없으면 ffmpeg -i 로
 *  · mcv_sidecar_subs($file)          같은 폴더의 자막 파일(srt·smi·sami·vtt·ass·ssa) — FileStation 과 같은 찾기·순위·인코딩 처리
 *  · mcv_zip_subs($zipfile, $entry)   압축 파일 속 동영상 옆 자막
 *  · mcv_frames_gc()                  장면 캐시 정리(cache/_vframes — fix146, 종전 src/video/vframes 도)
 *  ★ (2026-11-13 14:41 fix143) 장면 그림·변환 재생은 video_fs.php(FileStation 코드 그대로 — fix141)로 바뀌어 mcv_slot_lock·mcv_slot_unlock·mcv_proc_open·mcv_frame_send·mcv_transcode_send·mcv_tmp_dir(cache/_vlock)는 뺌(다른 곳에서 안 씀)
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
 * ★ (2026-10-31) 장면 캐시 정리 — 30일 넘게 새 장면이 안 생긴 영상 폴더를 지움(가끔 한 번, 한 번에 50개까지 · 쌓이기만 하던 것 방지)
 * ★ (2026-11-13 18:08, 펜닐 요청 — fix147) 장면 캐시는 FileStation 원본처럼 한 폴더(cache/_vframes/<md5>.jpg · .jpg.fail · .slot0·1) — 정리는 10일 넘은 장면·실패 표시(fix148 펜닐 지시 — 종전 30일)
 *   종전 자리 src/video/vframes(장면·실패 표시·작업 자리·예전 영상 폴더)는 날짜와 상관없이 지우고 비면 폴더도 — 이름이 맞는 것만 하나씩
 *   init.php 1시간 정리 때 + 장면 요청 300번에 한 번(종전처럼)
 */
function mcv_frames_gc() {
    $n = 0;
    // 지금 자리 cache/_vframes — 10일 넘은 장면·실패 표시(★ 2026-11-13 21:26 fix148 펜닐 지시 — 종전 30일), 1시간 넘은 묶음 임시 파일 · 폴더를 통째로 지워도 다음 장면 요청 때 다시 만듦(video_fs.php frameCacheDir)
    $root = __DIR__ . '/cache/_vframes';
    if (is_dir($root) && !is_link($root)) {
        $limit = time() - 10 * 86400;
        foreach ((@scandir($root) ?: []) as $f) {
            $p = $root . '/' . $f;
            if (preg_match('/^[0-9a-f]{32}\.jpg(\.fail)?$/', $f)) { if ((int)@filemtime($p) > $limit) continue; }
            elseif (preg_match('/^(b\d+_\d+_\d+|[0-9a-f]{32}\.jpg\.\d+\.\d+)\.tmp\.jpg$/', $f)) { if ((int)@filemtime($p) > time() - 3600) continue; }
            else continue;
            if (is_file($p) && !is_link($p)) @unlink($p);
            if (++$n >= 200) return;
        }
    }
    // 종전 자리 src/video/vframes — fix141~145 의 <md5>.jpg·.jpg.fail·.slot0·1·임시, 그 전의 영상 폴더(<32자>/)
    $old = __DIR__ . '/src/video/vframes'; // ★ (2026-11-03 22:14) cache 밖으로 → ★ fix146 다시 cache/_vframes 로(지워져도 되는 것)
    if (!is_dir($old) || is_link($old)) return;
    foreach ((@scandir($old) ?: []) as $f) {
        $p = $old . '/' . $f;
        if (preg_match('/^[0-9a-f]{32}$/', $f) && is_dir($p) && !is_link($p)) {
            foreach ((@scandir($p) ?: []) as $g) { if ($g !== '.' && $g !== '..' && is_file($p . '/' . $g) && !is_link($p . '/' . $g)) @unlink($p . '/' . $g); }
            @rmdir($p);
        } elseif (preg_match('/^([0-9a-f]{32}\.jpg(\.fail|\.\d+\.\d+\.tmp\.jpg)?|\.slot[01]|b\d+_\d+_\d+\.tmp\.jpg)$/', $f) && is_file($p) && !is_link($p)) {
            @unlink($p);
        } else continue;
        if (++$n >= 500) return;
    }
    @rmdir($old);
}



}
