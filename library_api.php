<?php
/**
 * myComix 라이브러리 편집 API — 관리자 전용, POST + CSRF 토큰 필수, JSON 응답
 * ★ (2026-10-29) 신규
 *
 * action:
 *   save_series  sid, name, status, description, authors, groups(★ 2026-11-02 그룹), genres, tags   (쉼표 구분)
 *   set_volume   vid, vol_no, vol_label
 *   move_volumes vids[] , series_name, bidx                      (선택한 권을 다른/새 시리즈로)
 *   bulk_assign  files[](encode_url), bidx, series_name?, authors?, groups?, genres?, tags?   (목록 화면 일괄 편집 — 추가 방식)
 *   scan_step    bidx, restart(0|1), mode(quick|full), token     (스캔 — 빠른 스캔/전체 다시, 이어서는 token)
 *   scan_stop    bidx, token                                      (스캔 중지)
 *   scan_start   bidx, mode(quick|full)                           (★ 2026-10-30 서버에서 끝까지 진행 — 응답을 먼저 보냄)
 *   scan_status  bidx                                             (진행 상황)
 *   scan_cancel  bidx                                             (중지 요청)
 *   auto_set     on(0|1), min(5|10|30|60|180|360|1440)            (자동 스캔 설정)
 *   suggest      bidx                                             (편집 창 자동완성 목록)
 *   find_volumes sid, q                                           (★ 2026-11-06 이 시리즈에 넣을 책 찾기)
 *   add_volumes / detach_volumes / auto_volumes  sid, vids[]      (★ 2026-11-06 가져오기 · 빼기 · 자동 판단으로 되돌리기)
 *   order_copies sid, vids[]                                       (★ fix64 같은 권 사본 순서 — 앞에서부터 1,2,3…, 맨 앞이 대표)
 */

// ★ (2026-10-30) 스캔 진행 상황 읽기(2초마다 자동)는 사람이 한 활동이 아님 → 자동 로그아웃용 활동 기록을 건드리지 않음(init.php)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'scan_status' && !defined('MC_NO_ACTIVITY_TOUCH')) define('MC_NO_ACTIVITY_TOUCH', true);
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/library.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function lib_api_out($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') lib_api_out(['success' => false, 'error' => 'POST only'], 405);
if (empty($_SESSION['user_id']) || !mc_lib_is_admin()) lib_api_out(['success' => false, 'error' => __('err_permission_denied')], 403);
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) lib_api_out(['success' => false, 'error' => __('api_csrf_error')], 403);
// ★ (2026-11-02 09:14) 글자 칸은 문자열만(배열이면 'Array' 로 저장될 수 있었음) — 목록 칸(vids·files)은 그대로
foreach ($_POST as $_pk => $_pv) {
    if (is_array($_pv) && !in_array($_pk, ['vids', 'files'], true)) $_POST[$_pk] = '';
}

$db = mc_lib_db();
if (!$db) lib_api_out(['success' => false, 'error' => __('lib_no_sqlite')], 500);

$action = (string)($_POST['action'] ?? '');
$allowed_status = ['', 'ongoing', 'completed', 'hiatus'];

try {
    switch ($action) {
        case 'save_series': {
            $sid = (int)($_POST['sid'] ?? 0);
            $st = $db->prepare('SELECT id, status, description FROM series WHERE id=?');
            $st->execute([$sid]);
            $found = $st->fetch();
            $st->closeCursor(); // ★ (2026-10-30) 열어 둔 읽기 커서가 아래 쓰기를 스캔 중 실패하게 하던 것 — 바로 닫음
            if (!$found) lib_api_out(['success' => false, 'error' => 'not found'], 404);
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, $allowed_status, true)) $status = '';
            $desc = mc_lib_nfc(str_replace("\r\n", "\n", (string)($_POST['description'] ?? '')));
            if (mb_strlen($desc, 'UTF-8') > 5000) $desc = mb_substr($desc, 0, 5000, 'UTF-8');
            mc_lib_begin_write($db); // ★ (2026-10-30) 쓰기 권한을 먼저 잡고 시작(스캔 중 편집이 실패하지 않게)
            // ★ (2026-10-30) 상태·설명의 '직접 입력' 표시(manual 4)는 이름 바꾸기(합치기 여부)를 본 뒤에 붙임
            //   — 합쳐질 때 이번에 실제로 바꾼 상태·설명만 대상 작품에 쓰고, 안 바꿨으면 대상 작품의 기존 값을 그대로 둠
            $info_changed = ($status !== (string)$found['status']) || ($desc !== (string)$found['description']);
            $db->prepare('UPDATE series SET status=?, description=?, updated_at=? WHERE id=?')->execute([$status, $desc, time(), $sid]);
            // ★ (2026-10-30) 작가·장르·태그도 바꾼 종류만 먼저 저장 — 이름만 바꿔 다른 작품과 합칠 때 대상 작품의 자동 작가 계산·ComicInfo 반영이 꺼지지 않게
            $cur_terms = mc_lib_series_terms($db, $sid);
            $norm = function (array $a) { $a = array_map(function ($x) { return mb_strtolower(trim((string)$x), 'UTF-8'); }, $a); $a = array_values(array_unique(array_filter($a, 'strlen'))); sort($a); return $a; };
            $unchanged = [];
            foreach (['authors' => 'author', 'groups' => 'group', 'genres' => 'genre', 'tags' => 'tag'] as $field => $kind) { // ★ (2026-11-02 01:53) 그룹
                if (!isset($_POST[$field])) continue;
                $new_list = mc_lib_split_terms($_POST[$field]);
                $old_list = array_map(function ($r) { return (string)$r['name']; }, $cur_terms[$kind] ?? []);
                if ($norm($new_list) === $norm($old_list)) { $unchanged[$kind] = $new_list; continue; }
                mc_lib_set_terms($db, $sid, $kind, $new_list, 'replace');
            }
            $final = $sid;
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name !== '') $final = mc_lib_rename_series($db, $sid, $name);
            if ($final === $sid) {
                $db->prepare('UPDATE series SET manual=(manual|4) WHERE id=?')->execute([$sid]); // 전과 같음
                foreach ($unchanged as $kind => $list) mc_lib_set_terms($db, $sid, $kind, $list, 'replace'); // 합치지 않으면 전처럼 모두 저장
            } elseif ($info_changed) {
                $db->prepare('UPDATE series SET status=?, description=?, manual=(manual|4), updated_at=? WHERE id=?')->execute([$status, $desc, time(), $final]);
            }
            $db->commit();
            lib_api_out(['success' => true, 'sid' => $final, 'merged' => $final !== $sid]);
        }
        case 'set_volume': {
            $vid = (int)($_POST['vid'] ?? 0);
            $vno = trim((string)($_POST['vol_no'] ?? ''));
            $vno = ($vno === '' || !is_numeric($vno)) ? null : max(0, min(99999, (float)$vno));
            $label = mc_lib_clean(mc_lib_nfc((string)($_POST['vol_label'] ?? '')));
            if (mb_strlen($label, 'UTF-8') > 40) $label = mb_substr($label, 0, 40, 'UTF-8');
            // ★ (2026-10-29) 같은 책 묶음 키(dkey)도 새 권 번호로
            $st = $db->prepare('SELECT subtitle FROM volumes WHERE id=?');
            $st->execute([$vid]);
            $subt = (string)$st->fetchColumn();
            $st->closeCursor(); // ★ (2026-10-30) 바로 닫음(위와 같은 이유)
            $db->prepare('UPDATE volumes SET vol_no=?, vol_label=?, manual=1, dkey=?, corder=0 WHERE id=?')->execute([$vno, $label, mc_lib_dup_key(['vol_no' => $vno, 'vol_label' => $label, 'subtitle' => $subt]), $vid]);
            lib_api_out(['success' => true]);
        }
        // ★ (2026-11-06 07:20) 손으로 묶기 — 편집 창 '도서 목록'에서 찾아 넣기·빼기·자동으로 되돌리기(펜닐 요청)
        case 'find_volumes': {
            $sid = (int)($_POST['sid'] ?? 0);
            $st = $db->prepare('SELECT bidx FROM series WHERE id=?'); $st->execute([$sid]); $b = $st->fetchColumn(); $st->closeCursor();
            if ($b === false) lib_api_out(['success' => false, 'error' => 'not found'], 404);
            $q = (string)($_POST['q'] ?? '');
            if (mb_strlen(trim($q), 'UTF-8') < 1) lib_api_out(['success' => true, 'items' => []]);
            if (mb_strlen($q, 'UTF-8') > 100) $q = mb_substr($q, 0, 100, 'UTF-8');
            lib_api_out(['success' => true, 'items' => mc_lib_find_volumes($db, (int)$b, $sid, $q, 80)]);
        }
        // ★ fix64 같은 권 사본 순서(편집 창 ↑↓) — 이 시리즈의 책만, 받은 차례대로 corder 1,2,3…
        case 'order_copies': {
            $sid = (int)($_POST['sid'] ?? 0);
            $vids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)($_POST['vids'] ?? []))))), 0, 500);
            if ($sid <= 0 || count($vids) < 2) lib_api_out(['success' => false, 'error' => 'invalid'], 400);
            mc_lib_begin_write($db);
            $up = $db->prepare('UPDATE volumes SET corder=? WHERE id=? AND series_id=?');
            $n = 0;
            foreach ($vids as $i => $vid) { $up->execute([$i + 1, $vid, $sid]); $n += $up->rowCount(); }
            $db->commit();
            lib_api_out(['success' => true, 'changed' => $n]);
        }
        case 'add_volumes':
        case 'detach_volumes':
        case 'auto_volumes': {
            $sid = (int)($_POST['sid'] ?? 0);
            $vids = array_slice(array_values(array_filter(array_map('intval', (array)($_POST['vids'] ?? [])))), 0, 2000);
            $st = $db->prepare('SELECT bidx FROM series WHERE id=?'); $st->execute([$sid]); $b = $st->fetchColumn(); $st->closeCursor();
            if ($b === false || !$vids) lib_api_out(['success' => false, 'error' => 'invalid'], 400);
            $folders = [];
            if ($action === 'detach_volumes') {
                if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); // 폴더를 다시 읽어 조금 걸릴 수 있음
                $n = mc_lib_detach_volumes($db, $vids, $sid); // 안에서 단계마다 트랜잭션(폴더 다시 읽기는 그 사이)
            } else {
                mc_lib_begin_write($db);
                if ($action === 'add_volumes') $n = mc_lib_assign_volumes($db, $vids, $sid);
                else { $folders = mc_lib_volumes_to_auto($db, $vids, $sid); $n = count($folders); }
                $db->commit();
            }
            // 자동으로 되돌린 책은 그 폴더를 바로 다시 읽어 자동 판단(다음 스캔을 기다리지 않게 · 폴더마다 트랜잭션)
            if ($folders) { if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); foreach (array_slice($folders, 0, 200) as [$fb, $fd]) mc_lib_index_folder($fb, $fd, null, true); }
            $st = $db->prepare('SELECT COUNT(*) FROM volumes WHERE series_id=?'); $st->execute([$sid]); $left = (int)$st->fetchColumn(); $st->closeCursor();
            lib_api_out(['success' => true, 'changed' => $n, 'left' => $left]);
        }
        case 'move_volumes': {
            $bidx = (int)($_POST['bidx'] ?? 0);
            $vids = array_values(array_filter(array_map('intval', (array)($_POST['vids'] ?? []))));
            $name = (string)($_POST['series_name'] ?? '');
            if (!$vids || trim($name) === '') lib_api_out(['success' => false, 'error' => 'invalid'], 400);
            mc_lib_begin_write($db); // ★ (2026-10-30) 쓰기 권한을 먼저 잡고 시작(스캔 중 편집이 실패하지 않게)
            $target = mc_lib_move_volumes($db, array_slice($vids, 0, 2000), $name, $bidx);
            $db->commit();
            lib_api_out(['success' => (bool)$target, 'sid' => $target]);
        }
        case 'bulk_assign': {
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); // ★ (2026-11-04 09:40) 오래 걸리는 작업 — 세션을 먼저 닫음(그사이 로그아웃이 되살아나지 않게, 세션 값은 그대로 읽힘)
            $bidx = (int)($_POST['bidx'] ?? 0);
            $files = array_slice((array)($_POST['files'] ?? []), 0, 2000);
            $paths = [];
            foreach ($files as $f) {
                if (!is_string($f)) continue; // ★ (2026-11-02 10:08) 겹친 배열이면 경고
                $p = mc_lib_norm_path(decode_file_param($f));
                if ($p === '' || !mc_lib_can_access($bidx, $p) || !mc_lib_inside_base($bidx, $p)) continue;
                if (!in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), mc_lib_exts(), true)) continue;
                $paths[$p] = true;
            }
            if (!$paths) lib_api_out(['success' => false, 'error' => __('lib_bulk_none')], 400);
            // 선택한 파일들이 있는 폴더를 먼저 인덱싱
            $folders = [];
            foreach (array_keys($paths) as $p) { $d = dirname($p); $folders[$d === '/' ? '' : $d] = true; }
            foreach (array_keys($folders) as $d) mc_lib_index_folder($bidx, $d);
            mc_lib_begin_write($db); // ★ (2026-10-30) 쓰기 권한을 먼저 잡고 시작(스캔 중 편집이 실패하지 않게)
            $sel = $db->prepare('SELECT id, series_id FROM volumes WHERE bidx=? AND path=?');
            $vids = []; $sids = [];
            foreach (array_keys($paths) as $p) {
                $sel->execute([$bidx, $p]);
                if ($r = $sel->fetch()) { $vids[] = (int)$r['id']; $sids[(int)$r['series_id']] = true; }
            }
            $name = trim((string)($_POST['series_name'] ?? ''));
            if ($name !== '' && $vids) {
                $target = mc_lib_move_volumes($db, $vids, $name, $bidx);
                $sids = $target ? [$target => true] : $sids;
            }
            $added = 0;
            foreach (array_keys($sids) as $sid) {
                if (!$sid) continue;
                foreach (['authors' => 'author', 'groups' => 'group', 'genres' => 'genre', 'tags' => 'tag'] as $field => $kind) { // ★ (2026-11-02 01:53) 그룹
                    $names = mc_lib_split_terms($_POST[$field] ?? '');
                    if ($names) { mc_lib_set_terms($db, $sid, $kind, $names, 'append'); $added++; }
                }
            }
            $db->commit();
            lib_api_out(['success' => true, 'volumes' => count($vids), 'series' => count($sids)]);
        }
        case 'scan_step': {
            // ★ (2026-10-29) restart=1 이면 새로 시작(mode=full 이면 전체 다시 스캔), 이어서는 받은 token 으로
            $bidx = (int)($_POST['bidx'] ?? 0);
            @set_time_limit(60);
            $r = mc_lib_scan_step($bidx, 8.0, !empty($_POST['restart']), ($_POST['mode'] ?? '') === 'full', (string)($_POST['token'] ?? ''));
            if (!empty($r['busy'])) lib_api_out(['success' => false, 'busy' => true, 'error' => __('lib_scan_busy')], 409);
            lib_api_out(['success' => true] + $r);
        }
        case 'suggest': {
            // ★ (2026-10-29) 편집 창 자동완성 — 처음 열 때만 요청(관리자·CSRF 확인은 위에서)
            $bidx = (int)($_POST['bidx'] ?? 0);
            $out = ['author' => [], 'group' => [], 'genre' => [], 'tag' => [], 'series' => []]; // ★ (2026-11-02 01:53) 그룹
            // ★ (2026-11-02 03:04) 종류마다 따로 3000개 — 모두 합쳐 이름순 6000개였을 때는 작가가 많으면 뒤쪽 이름의 그룹·태그가 빠졌음
            $sq = $db->prepare('SELECT name FROM terms WHERE kind=? ORDER BY name LIMIT 3000');
            foreach (['author', 'group', 'genre', 'tag'] as $_k) { $sq->execute([$_k]); $out[$_k] = $sq->fetchAll(PDO::FETCH_COLUMN); }
            $st = $db->prepare('SELECT name FROM series WHERE bidx=? ORDER BY name LIMIT 3000');
            $st->execute([$bidx]);
            $out['series'] = $st->fetchAll(PDO::FETCH_COLUMN);
            lib_api_out(['success' => true] + $out);
        }
        case 'scan_stop': {
            lib_api_out(['success' => mc_lib_scan_stop((int)($_POST['bidx'] ?? 0), (string)($_POST['token'] ?? ''))]);
        }
        // ★ (2026-10-30) 서버 쪽 스캔: 시작(응답 먼저 보내고 서버에서 끝까지) / 진행 상황 / 중지 요청 / 자동 스캔 설정
        case 'scan_start': {
            // ★ (2026-10-31) bidx=all → 모든 콘텐츠 폴더 차례로(작품 탐색 '전체 폴더(통합)' 화면)
            if (($_POST['bidx'] ?? '') === 'all') {
                $mode = ($_POST['mode'] ?? '') === 'full' ? 'full' : 'quick';
                $s = mc_lib_scan_all_status();
                if ($s && $s['live']) lib_api_out(['success' => true, 'running' => true] + $s);
                mc_lib_detach_json(['success' => true, 'started' => true]);
                @set_time_limit(0);
                mc_lib_scan_all_run($mode, (string)($_POST['req'] ?? ''));
                exit;
            }
            $bidx = (int)($_POST['bidx'] ?? 0);
            if (!mc_lib_base($bidx)) lib_api_out(['success' => false, 'error' => 'nobase'], 400);
            $mode = ($_POST['mode'] ?? '') === 'full' ? 'full' : 'quick';
            $s = mc_lib_scan_status($bidx);
            if ($s['live']) lib_api_out(['success' => true, 'running' => true] + $s); // 이미 진행 중(자동 포함) → 그 진행 상황을 보여줌
            mc_lib_detach_json(['success' => true, 'started' => true]);
            mc_lib_scan_run($bidx, $mode, 'manual', 3600, (string)($_POST['req'] ?? ''));
            exit;
        }
        case 'scan_status': {
            if (($_POST['bidx'] ?? '') === 'all') lib_api_out(['success' => true] + mc_lib_scan_all_status()); // ★ (2026-10-31)
            $bidx = (int)($_POST['bidx'] ?? 0);
            lib_api_out(['success' => true] + mc_lib_scan_status($bidx));
        }
        case 'scan_cancel': {
            if (($_POST['bidx'] ?? '') === 'all') lib_api_out(['success' => mc_lib_scan_all_request_stop()]); // ★ (2026-10-31)
            lib_api_out(['success' => mc_lib_scan_request_stop((int)($_POST['bidx'] ?? 0))]);
        }
        case 'auto_set': {
            $min = (int)($_POST['min'] ?? 10);
            if (!in_array($min, [5, 10, 30, 60, 180, 360, 1440], true)) $min = 10;
            mc_lib_meta_set($db, 'auto_scan', json_encode(['on' => !empty($_POST['on']), 'min' => $min]));
            lib_api_out(['success' => true, 'auto' => mc_lib_auto_cfg($db)]);
        }
        default:
            lib_api_out(['success' => false, 'error' => 'unknown action'], 400);
    }
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[library_api] ' . $e->getMessage());
    lib_api_out(['success' => false, 'error' => __('lib_save_failed')], 500);
}
