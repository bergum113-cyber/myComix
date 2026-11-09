<?php
/**
 * MaxMind GeoLite2 MMDB Reader
 * 
 * GeoLite2-Country.mmdb 파일을 읽어서 IP → 국가코드 변환
 * 
 * 사용법:
 * $reader = new MaxMindReader('/path/to/GeoLite2-Country.mmdb');
 * $country = $reader->getCountry('8.8.8.8'); // 'US'
 * 
 * 다운로드: https://dev.maxmind.com/geoip/geolite2-free-geolocation-data
 * (무료 회원가입 필요)
 * 
 * @version 1.0
 */

class MaxMindReader {
    private $fileHandle;
    private $metadata;
    private $decoder;
    private $ipV4Start;
    
    const DATA_TYPE_EXTENDED = 0;
    const DATA_TYPE_POINTER = 1;
    const DATA_TYPE_UTF8_STRING = 2;
    const DATA_TYPE_DOUBLE = 3;
    const DATA_TYPE_BYTES = 4;
    const DATA_TYPE_UINT16 = 5;
    const DATA_TYPE_UINT32 = 6;
    const DATA_TYPE_MAP = 7;
    const DATA_TYPE_INT32 = 8;
    const DATA_TYPE_UINT64 = 9;
    const DATA_TYPE_UINT128 = 10;
    const DATA_TYPE_ARRAY = 11;
    const DATA_TYPE_CONTAINER = 12;
    const DATA_TYPE_END_MARKER = 13;
    const DATA_TYPE_BOOLEAN = 14;
    const DATA_TYPE_FLOAT = 15;
    
    /**
     * 생성자
     * @param string $database MMDB 파일 경로
     */
    public function __construct($database) {
        if (!file_exists($database)) {
            throw new Exception("GeoIP database not found: {$database}");
        }
        
        $this->fileHandle = @fopen($database, 'rb');
        if (!$this->fileHandle) {
            throw new Exception("Cannot open GeoIP database: {$database}");
        }
        
        $this->metadata = $this->readMetadata();
        
        if ($this->metadata['ip_version'] == 6) {
            $this->ipV4Start = $this->findIpV4Start();
        }
    }
    
    /**
     * 소멸자
     */
    public function __destruct() {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }
    
    /**
     * IP 주소로 국가 코드 가져오기
     * @param string $ip IP 주소
     * @return string|null 국가 코드 (예: 'KR', 'US') 또는 null
     */
    public function getCountry($ip) {
        try {
            $record = $this->get($ip);
            
            if ($record && isset($record['country']['iso_code'])) {
                return $record['country']['iso_code'];
            }
            
            if ($record && isset($record['registered_country']['iso_code'])) {
                return $record['registered_country']['iso_code'];
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * IP 주소로 전체 레코드 가져오기
     * @param string $ip IP 주소
     * @return array|null
     */
    public function get($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipBytes = $this->ipToBytes($ip, 4);
            $bitCount = 32;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBytes = $this->ipToBytes($ip, 6);
            $bitCount = 128;
        } else {
            return null;
        }
        
        $pointer = $this->findAddressInTree($ipBytes, $bitCount);
        
        if ($pointer === null || $pointer === 0) {
            return null;
        }
        
        return $this->resolveDataPointer($pointer);
    }
    
    /**
     * IP를 바이트 배열로 변환
     */
    private function ipToBytes($ip, $version) {
        if ($version == 4) {
            $packed = inet_pton($ip);
        } else {
            $packed = inet_pton($ip);
        }
        
        return array_values(unpack('C*', $packed));
    }
    
    /**
     * 트리에서 IP 주소 찾기
     */
    private function findAddressInTree($ipBytes, $bitCount) {
        $nodeCount = $this->metadata['node_count'];
        $recordSize = $this->metadata['record_size'];
        $nodeByteSize = $recordSize / 4;
        
        $node = 0;
        
        // IPv4 in IPv6 데이터베이스 처리
        if ($bitCount == 32 && $this->metadata['ip_version'] == 6) {
            $node = $this->ipV4Start;
        }
        
        for ($i = 0; $i < $bitCount; $i++) {
            if ($node >= $nodeCount) {
                break;
            }
            
            $byteIndex = (int)($i / 8);
            $bitIndex = 7 - ($i % 8);
            $bit = ($ipBytes[$byteIndex] >> $bitIndex) & 1;
            
            $record = $this->readNode($node, $bit);
            
            if ($record === $nodeCount) {
                // Empty
                return null;
            } elseif ($record > $nodeCount) {
                // Data pointer
                return $record;
            } else {
                // Another node
                $node = $record;
            }
        }
        
        return null;
    }
    
    /**
     * 노드 읽기
     */
    private function readNode($nodeNumber, $bit) {
        $recordSize = $this->metadata['record_size'];
        $nodeByteSize = $recordSize / 4;
        
        $offset = $nodeNumber * $nodeByteSize;
        
        fseek($this->fileHandle, $offset);
        $bytes = fread($this->fileHandle, $nodeByteSize);
        
        if ($recordSize == 24) {
            // 24-bit records
            if ($bit == 0) {
                return unpack('N', "\x00" . substr($bytes, 0, 3))[1];
            } else {
                return unpack('N', "\x00" . substr($bytes, 3, 3))[1];
            }
        } elseif ($recordSize == 28) {
            // 28-bit records
            $middle = ord($bytes[3]);
            if ($bit == 0) {
                $prefix = ($middle & 0xF0) >> 4;
                return ($prefix << 24) | unpack('N', "\x00" . substr($bytes, 0, 3))[1];
            } else {
                $prefix = $middle & 0x0F;
                return ($prefix << 24) | unpack('N', "\x00" . substr($bytes, 4, 3))[1];
            }
        } elseif ($recordSize == 32) {
            // 32-bit records
            if ($bit == 0) {
                return unpack('N', substr($bytes, 0, 4))[1];
            } else {
                return unpack('N', substr($bytes, 4, 4))[1];
            }
        }
        
        return null;
    }
    
    /**
     * 데이터 포인터 해석
     */
    private function resolveDataPointer($pointer) {
        $nodeCount = $this->metadata['node_count'];
        $recordSize = $this->metadata['record_size'];
        $searchTreeSize = ($recordSize / 4) * $nodeCount;
        
        $resolved = $pointer - $nodeCount + $searchTreeSize;
        
        return $this->decodeData($resolved);
    }
    
    /**
     * 데이터 디코딩
     */
    private function decodeData($offset) {
        fseek($this->fileHandle, $offset);
        
        return $this->decode()[0];
    }
    
    /**
     * 값 디코딩
     */
    private function decode() {
        $ctrlByte = ord(fread($this->fileHandle, 1));
        
        $type = $ctrlByte >> 5;
        
        if ($type == self::DATA_TYPE_EXTENDED) {
            $type = 7 + ord(fread($this->fileHandle, 1));
        }
        
        $size = $ctrlByte & 0x1f;
        
        // ★ (2026-11-02 10:07) 포인터는 크기 확장 바이트가 없음(5비트가 곧 포인터 크기·값) — MaxMind DB 규격
        if ($type == self::DATA_TYPE_POINTER) return $this->decodeByType($type, $size);
        
        if ($size >= 29) {
            $bytesToRead = $size - 28;
            $bytes = fread($this->fileHandle, $bytesToRead);
            
            if ($size == 29) {
                $size = 29 + ord($bytes);
            } elseif ($size == 30) {
                $size = 285 + unpack('n', $bytes)[1];
            } elseif ($size == 31) {
                $size = 65821 + unpack('N', "\x00" . $bytes)[1];
            }
        }
        
        return $this->decodeByType($type, $size);
    }
    
    /**
     * 타입별 디코딩
     */
    private function decodeByType($type, $size) {
        switch ($type) {
            case self::DATA_TYPE_POINTER:
                $pointer = $this->decodePointer($size);
                $pos = ftell($this->fileHandle);
                // ★ (2026-11-02 10:07) 포인터는 데이터 구역 시작 기준(검색 트리 + 구분 16바이트 뒤) — 전에는 파일 맨 앞 기준으로 읽어 엉뚱한 값
                $dataStart = (int)(($this->metadata['record_size'] / 4) * $this->metadata['node_count']) + 16;
                $result = $this->decodeData($dataStart + $pointer);
                fseek($this->fileHandle, $pos);
                return [$result, ftell($this->fileHandle)];
                
            case self::DATA_TYPE_UTF8_STRING:
                $value = $size > 0 ? fread($this->fileHandle, $size) : '';
                return [$value, ftell($this->fileHandle)];
                
            case self::DATA_TYPE_DOUBLE:
                $value = unpack('E', fread($this->fileHandle, 8))[1];
                return [$value, ftell($this->fileHandle)];
                
            case self::DATA_TYPE_BYTES:
                $value = $size > 0 ? fread($this->fileHandle, $size) : '';
                return [$value, ftell($this->fileHandle)];
                
            case self::DATA_TYPE_UINT16:
            case self::DATA_TYPE_UINT32:
            case self::DATA_TYPE_UINT64:
            case self::DATA_TYPE_UINT128:
                $value = $this->decodeUint($size);
                return [$value, ftell($this->fileHandle)];
                
            case self::DATA_TYPE_MAP:
                $map = [];
                for ($i = 0; $i < $size; $i++) {
                    list($key) = $this->decode();
                    list($val) = $this->decode();
                    $map[$key] = $val;
                }
                return [$map, ftell($this->fileHandle)];
                
            case self::DATA_TYPE_INT32:
                $value = $this->decodeInt32($size);
                return [$value, ftell($this->fileHandle)];
                
            case self::DATA_TYPE_ARRAY:
                $arr = [];
                for ($i = 0; $i < $size; $i++) {
                    list($val) = $this->decode();
                    $arr[] = $val;
                }
                return [$arr, ftell($this->fileHandle)];
                
            case self::DATA_TYPE_BOOLEAN:
                return [$size ? true : false, ftell($this->fileHandle)];
                
            case self::DATA_TYPE_FLOAT:
                $value = unpack('G', fread($this->fileHandle, 4))[1];
                return [$value, ftell($this->fileHandle)];
                
            default:
                return [null, ftell($this->fileHandle)];
        }
    }
    
    /**
     * 포인터 디코딩
     */
    private function decodePointer($size) {
        // ★ (2026-11-02 10:07) MaxMind DB 규격대로 — SS(크기 2비트)·VVV(값 3비트), SS=0:11비트 / 1:19비트+2048 / 2:27비트+526336 / 3:32비트(VVV 안 씀)
        //   전에는 계산식이 틀려(1바이트 포인터 0x02 → 8194, 규격 값 258) 나라 이름 대신 엉뚱한 자리를 읽었음
        $ss = ($size >> 3) & 0x3;
        $vvv = $size & 0x7;
        $b = fread($this->fileHandle, $ss + 1);
        $b = str_pad((string)$b, $ss + 1, "\x00");
        switch ($ss) {
            case 0: return ($vvv << 8) | ord($b[0]);
            case 1: return (($vvv << 16) | (ord($b[0]) << 8) | ord($b[1])) + 2048;
            case 2: return (($vvv << 24) | (ord($b[0]) << 16) | (ord($b[1]) << 8) | ord($b[2])) + 526336;
            default: return unpack('N', $b)[1];
        }
    }
    
    /**
     * Unsigned int 디코딩
     */
    private function decodeUint($size) {
        if ($size == 0) return 0;
        
        $bytes = fread($this->fileHandle, $size);
        $bytes = str_pad($bytes, 4, "\x00", STR_PAD_LEFT);
        
        if ($size <= 4) {
            return unpack('N', substr($bytes, -4))[1];
        }
        
        // 큰 숫자는 문자열로 처리
        $value = 0;
        for ($i = 0; $i < $size; $i++) {
            $value = $value * 256 + ord($bytes[$i]);
        }
        return $value;
    }
    
    /**
     * Signed int32 디코딩
     */
    private function decodeInt32($size) {
        if ($size == 0) return 0;
        
        $bytes = fread($this->fileHandle, $size);
        $bytes = str_pad($bytes, 4, "\x00", STR_PAD_LEFT);
        
        $value = unpack('N', $bytes)[1];
        
        // 음수 처리
        if ($value >= 0x80000000) {
            $value -= 0x100000000;
        }
        
        return $value;
    }
    
    /**
     * IPv4 시작 노드 찾기 (IPv6 DB에서)
     */
    private function findIpV4Start() {
        $node = 0;
        $nodeCount = $this->metadata['node_count'];
        
        // IPv4는 IPv6의 ::ffff:0:0/96 서브넷에 매핑됨
        // 96비트 0을 따라가면 IPv4 시작점에 도달
        for ($i = 0; $i < 96; $i++) {
            if ($node >= $nodeCount) {
                break;
            }
            $node = $this->readNode($node, 0);
        }
        
        return $node;
    }
    
    /**
     * 메타데이터 읽기
     */
    private function readMetadata() {
        // 파일 끝에서 메타데이터 마커 찾기
        $marker = "\xab\xcd\xefMaxMind.com";
        $markerLength = strlen($marker);
        
        // ★ (2026-11-02 10:07) 파일 크기에 맞춰 끝부분을 읽음(규격상 메타데이터는 끝 128KB 안) — 8KB 보다 작은 파일은 fseek 가 실패해 엉뚱한 곳을 읽었음
        $fileSize = (int)(fstat($this->fileHandle)['size'] ?? 0);
        $readLen = min($fileSize, 131072);
        fseek($this->fileHandle, $fileSize - $readLen);
        $searchArea = fread($this->fileHandle, $readLen);
        
        $pos = strrpos($searchArea, $marker);
        if ($pos === false) {
            throw new Exception("Invalid MaxMind database: metadata marker not found");
        }
        
        $metadataStart = $fileSize - $readLen + $pos + $markerLength;
        fseek($this->fileHandle, $metadataStart);
        
        list($metadata) = $this->decode();
        
        return [
            'node_count' => $metadata['node_count'] ?? 0,
            'record_size' => $metadata['record_size'] ?? 28,
            'ip_version' => $metadata['ip_version'] ?? 4,
            'database_type' => $metadata['database_type'] ?? '',
            'build_epoch' => $metadata['build_epoch'] ?? 0
        ];
    }
    
    /**
     * 메타데이터 가져오기
     */
    public function getMetadata() {
        return $this->metadata;
    }
    
    /**
     * 데이터베이스 타입 확인
     */
    public function getDatabaseType() {
        return $this->metadata['database_type'] ?? '';
    }
    
    /**
     * 빌드 날짜 가져오기
     */
    public function getBuildDate() {
        $epoch = $this->metadata['build_epoch'] ?? 0;
        return $epoch ? date('Y-m-d H:i:s', $epoch) : '';
    }
}
