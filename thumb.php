<?php
/**
 * thumb.php - 썸네일 전용 로더
 * 사용법: <img src="thumb.php?file=파일경로&bidx=0">
 * 
 * type=cover: 폴더의 [cover].jpg 직접 출력
 * type=zip (기본): ZIP 파일의 .json에서 thumbnail 읽기
 * type=imgfolder: 폴더.image_files.json에서 thumbnail 읽기
 * type=book: TXT/EPUB 파일의 같은 이름 이미지 파일 출력
 * 
 * @version 2.0 - 이미지+동영상 혼합 ZIP 지원 (thumbnail 우선)
 * @date 2026-01-12
 */

// ✅ bootstrap.php 사용으로 통일
// 로드 순서: config.php → security_helpers.php → function.php → init.php → cache_util.php
require_once __DIR__ . "/bootstrap.php";

// ============================================================
// ✅ Placeholder 이미지 출력 함수
// ============================================================

/**
 * 썸네일이 없을 때 1x1 투명 GIF 출력 (깨진 아이콘 방지)
 */
function output_placeholder_image() {
    // 1x1 투명 GIF
    $placeholder = base64_decode("R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7");
    header('Content-Type: image/gif');
    header('Content-Length: ' . strlen($placeholder));
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    echo $placeholder;
    exit;
}

/**
 * 동영상 ZIP 파일용 아이콘 이미지 출력
 * SVG 이미지 반환
 */
function output_video_icon_image() {
    // ✅ 동영상 아이콘 SVG (index.php와 동일)
    $svg = '<svg viewBox="0 0 56 56" xmlns="http://www.w3.org/2000/svg" width="200" height="200">
<path style="fill:#E9E9E0;" d="M36.985,0H7.963C7.155,0,6.5,0.655,6.5,1.926V55c0,0.345,0.655,1,1.463,1h40.074c0.808,0,1.463-0.655,1.463-1V12.978c0-0.696-0.093-0.92-0.257-1.085L37.607,0.257C37.442,0.093,37.218,0,36.985,0z"/>
<polygon style="fill:#D9D7CA;" points="37.5,0.151 37.5,12 49.349,12"/>
<path style="fill:#556080;" d="M48.037,56H7.963C7.155,56,6.5,55.345,6.5,54.537V39h43v15.537C49.5,55.345,48.845,56,48.037,56z"/>
<g><path style="fill:#FFFFFF;" d="M20.379,54l-2.062-7.5h-0.068L16.156,54h-2.198l-2.569-10h2.088l1.626,7.821h0.068L17.15,44h1.937 l1.979,7.771h0.057L22.789,44h2.021l-2.569,10H20.379z"/></g>
<circle style="fill:#8697CB;" cx="27.5" cy="18" r="8"/>
<polygon style="fill:#FFFFFF;" points="25.5,14 25.5,22 31.5,18"/>
</svg>';
    
    header('Content-Type: image/svg+xml');
    header('Content-Length: ' . strlen($svg));
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    echo $svg;
    exit;
}

/**
 * 동영상 ZIP 파일인지 감지
 * 
 * @param string $filepath 압축 파일 경로
 * @param string $json_file 이미지 캐시 JSON 파일 경로
 * @return array ['is_video' => bool, 'has_thumbnail' => bool]
 * 
 * 검사 순서:
 * 1. .json에 thumbnail 있으면 → 이미지 ZIP (동영상 혼합이어도)
 * 2. .json에 viewer=video 또는 is_video_archive → 동영상 ZIP
 * 3. .video_files.json 존재 → 동영상 ZIP
 * 4. .json 없을 때만 → ZIP 내용 직접 확인
 * 
 * @version 2.0 - thumbnail 우선 (이미지+동영상 혼합 지원)
 */
function detect_video_archive($filepath, $json_file) {
    $result = ['is_video' => false, 'has_thumbnail' => false];
    
    // ✅ 1. 이미지 캐시 JSON 먼저 확인 (thumbnail 있으면 이미지 ZIP 확정)
    // 이미지+동영상 혼합 ZIP도 thumbnail 있으면 이미지로 표시
    if (is_file($json_file)) {
        $check_data = @json_decode(file_get_contents($json_file), true);
        if ($check_data) {
            // 썸네일 존재하면 이미지 ZIP (동영상 혼합이어도)
            if (!empty($check_data['thumbnail'])) {
                $result['has_thumbnail'] = true;
                // ✅ thumbnail 있으면 여기서 바로 return (동영상 아님)
                return $result;
            }
            // viewer=video 또는 is_video_archive=true인 경우만 동영상 ZIP
            if (($check_data['viewer'] ?? '') === 'video' || !empty($check_data['is_video_archive'])) {
                $result['is_video'] = true;
                return $result;
            }
        }
    }
    
    // 2. .video_files.json 존재 확인 (동영상 전용 ZIP)
    // ✅ .json에 thumbnail 없을 때만 체크
    $video_json_file = $filepath . '.video_files.json';
    if (is_file($video_json_file)) {
        $result['is_video'] = true;
        return $result;
    }
    
    // 3. ZIP 내용 직접 확인 (.json이 없을 때만 실행)
    if (preg_match('/\.(zip|cbz)$/i', $filepath) && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filepath) === true) {
            $has_video = false;
            $has_image = false;
            $video_pattern = get_video_extensions_pattern();
            $image_pattern = get_image_extensions_pattern();
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (substr($name, -1) === '/') continue;
                
                if (preg_match($video_pattern, $name)) $has_video = true;
                if (preg_match($image_pattern, $name)) $has_image = true;
                if ($has_image) break;
            }
            $zip->close();
            
            // 동영상만 있고 이미지 없으면 동영상 ZIP
            if ($has_video && !$has_image) {
                $result['is_video'] = true;
            }
        }
    }
    
    return $result;
}

// ✅ init_bidx() 사용으로 다중 폴더 지원
// 이 함수가 $base_dir을 현재 bidx에 맞게 설정함
$bidx = init_bidx();

// ✅ 디버그 모드 (?debug=1) - 관리자 전용 (보안 강화)
$debug = isset($_GET['debug']) && $_GET['debug'] == '1' 
         && isset($_SESSION['user_group']) && $_SESSION['user_group'] === 'admin';

// 파일 경로 파라미터 확인
if (!isset($_GET['file']) || empty($_GET['file'])) {
    // ✅ 에러 응답 통일: simple_error_exit() 사용
    simple_error_exit(400, $debug ? 'Error: file parameter missing' : 'Bad Request');
}

// ✅ 이중 인코딩 대응을 위해 decode_file_param() 사용
// JavaScript에서 encodeURIComponent() 2회 호출 시 발생하는 이중 인코딩 처리
$file = decode_file_param($_GET['file']);
$type = $_GET['type'] ?? 'auto';

// ✅ 경로 검증 강화 (validate_file_path 사용)
$filepath = validate_file_path($file, $base_dir);
if ($filepath === false) {
    // ✅ 에러 응답 통일: simple_error_exit() 사용
    simple_error_exit(403, $debug ? 'Error: invalid path' : 'Forbidden');
}

if ($debug) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "GET file: " . htmlspecialchars($_GET['file'], ENT_QUOTES, 'UTF-8') . "\n";
    echo "Decoded file: " . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . "\n";
    echo "Full filepath: " . htmlspecialchars($filepath, ENT_QUOTES, 'UTF-8') . "\n";
    echo "base_dir: " . htmlspecialchars($base_dir, ENT_QUOTES, 'UTF-8') . "\n";
    echo "bidx: " . htmlspecialchars($bidx, ENT_QUOTES, 'UTF-8') . "\n";
    echo "is_dir: " . (is_dir($filepath) ? 'yes' : 'no') . "\n";
    echo "is_file: " . (is_file($filepath) ? 'yes' : 'no') . "\n";
    $json_file = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '.json', $filepath);
    echo "JSON file: " . htmlspecialchars($json_file, ENT_QUOTES, 'UTF-8') . "\n";
    echo "JSON exists: " . (is_file($json_file) ? 'yes' : 'no') . "\n";
    exit;
}

// auto 모드: 파일/폴더 유형에 따라 자동 결정
if ($type === 'auto') {
    if (is_dir($filepath)) {
        // 폴더인 경우 - [cover].jpg 우선, 없으면 .image_files.json
        if (is_file($filepath . '/[cover].jpg')) {
            $type = 'cover';
        } else {
            $type = 'imgfolder';
        }
    } elseif (preg_match('/\.(txt|epub)$/i', $filepath)) {
        // TXT/EPUB 파일 - 같은 이름의 이미지 파일 찾기
        $type = 'book';
    } else {
        $type = 'zip';
    }
}

// 타입별 처리
if ($type === 'cover') {
    // [cover].jpg 직접 출력
    $cover_file = $filepath . '/[cover].jpg';
    
    if (!is_file($cover_file)) {
        output_placeholder_image();  // ✅ 404 대신 placeholder
    }
    
    $thumbnail = @file_get_contents($cover_file);
    if ($thumbnail === false) {
        output_placeholder_image();  // ✅ 500 대신 placeholder
    }
    
} elseif ($type === 'book') {
    // TXT/EPUB 파일의 썸네일 - 같은 이름의 이미지 파일 찾기
    $base_name = preg_replace('/\.(txt|epub)$/i', '', $filepath);
    $thumb_file = '';
    
    // 가능한 썸네일 파일 검색
    $possible_thumbs = [
        $base_name . '.jpg',      // 소설.jpg
        $base_name . '.jpeg',
        $base_name . '.png',
        $base_name . '.webp',
        $filepath . '.jpg',       // 소설.txt.jpg
        $filepath . '.jpeg',
        $filepath . '.png',
        $filepath . '.webp',
    ];
    
    foreach ($possible_thumbs as $pt) {
        if (is_file($pt)) {
            $thumb_file = $pt;
            break;
        }
    }
    
    if (empty($thumb_file)) {
        output_placeholder_image();  // ✅ 404 대신 placeholder
    }
    
    $thumbnail = @file_get_contents($thumb_file);
    if ($thumbnail === false) {
        output_placeholder_image();  // ✅ 500 대신 placeholder
    }
    
} elseif ($type === 'imgfolder') {
    // imgfolder: 폴더경로.image_files.json
    $json_file = $filepath . '.image_files.json';
    
    $data = safe_json_read($json_file, true);  // ✅ 손상된 JSON 자동 복구
    if (!$data || !isset($data['thumbnail']) || empty($data['thumbnail'])) {
        output_placeholder_image();  // ✅ 썸네일 없으면 placeholder
    }
    
    $thumbnail = base64_decode($data['thumbnail']);
    if ($thumbnail === false) {
        output_placeholder_image();  // ✅ 디코딩 실패 시 placeholder
    }
    
} else {
    // ZIP/RAR/7Z 파일: 파일명.json (확장자 제거)
    // 예: 파일명.zip → 파일명.json, 파일명.rar → 파일명.json
    $json_file = preg_replace('/\.(zip|cbz|rar|cbr|7z|cb7)$/i', '.json', $filepath);
    
    // ✅ 동영상 ZIP 감지 (함수화로 효율성 개선)
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "=== ZIP 파일 처리 디버그 ===\n";
        echo "filepath: $filepath\n";
        echo "json_file: $json_file\n";
        echo "video_json_file: {$filepath}.video_files.json\n";
        echo "json_file exists: " . (is_file($json_file) ? 'YES' : 'NO') . "\n";
        echo "video_json_file exists: " . (is_file($filepath . '.video_files.json') ? 'YES' : 'NO') . "\n";
    }
    
    $video_check = detect_video_archive($filepath, $json_file);
    $is_video_archive = $video_check['is_video'];
    $has_thumbnail = $video_check['has_thumbnail'];
    
    if ($debug) {
        echo "\n=== 최종 결과 ===\n";
        echo "is_video_archive: " . ($is_video_archive ? 'TRUE' : 'FALSE') . "\n";
        echo "has_thumbnail: " . ($has_thumbnail ? 'TRUE' : 'FALSE') . "\n";
        exit;
    }
    
    // ✅ 동영상 ZIP이면 동영상 아이콘 반환
    if ($is_video_archive) {
        output_video_icon_image();
    }
    
    if (!is_file($json_file)) {
        output_placeholder_image();  // ✅ JSON 없으면 placeholder
    }
    
    $data = safe_json_read($json_file, true);  // ✅ 손상된 JSON 자동 복구
    
    if (!$data || !isset($data['thumbnail']) || empty($data['thumbnail'])) {
        output_placeholder_image();  // ✅ 썸네일 없으면 placeholder
    }
    
    $thumbnail = base64_decode($data['thumbnail']);
    if ($thumbnail === false) {
        output_placeholder_image();  // ✅ 디코딩 실패 시 placeholder
    }
}

// MIME 타입 감지
$mime = 'image/jpeg';
if (isset($thumbnail[0], $thumbnail[1])) {
    if ($thumbnail[0] === "\xFF" && $thumbnail[1] === "\xD8") {
        $mime = 'image/jpeg';
    } elseif (substr($thumbnail, 0, 8) === "\x89PNG\x0D\x0A\x1A\x0A") {
        $mime = 'image/png';
    } elseif (substr($thumbnail, 0, 6) === "GIF87a" || substr($thumbnail, 0, 6) === "GIF89a") {
        $mime = 'image/gif';
    } elseif (substr($thumbnail, 0, 4) === "RIFF" && substr($thumbnail, 8, 4) === "WEBP") {
        $mime = 'image/webp';
    }
}

// 캐시 헤더 설정 (1일)
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($thumbnail));
header('Cache-Control: public, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

echo $thumbnail;
exit;