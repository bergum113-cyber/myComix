# myComix

> PHP 기반 개인 만화/미디어 서재 웹 애플리케이션 (대규모 확장 포크)

[![Version](https://img.shields.io/badge/version-v2.4.2-blue.svg)](#)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Forked from](https://img.shields.io/badge/forked%20from-imueRoid%2FmyComix-orange.svg)](https://github.com/imueRoid/myComix)

---

## 📋 개요

**myComix**는 압축 파일(ZIP, CBZ, RAR, 7Z), 이미지 폴더, PDF, EPUB, TXT, 동영상 등 다양한 형식을 지원하는 개인 미디어 서버입니다. 자체 호스팅 환경에서 만화책, 전자책, 동영상, 문서 등을 통합 관리하고 웹 브라우저로 편리하게 열람할 수 있습니다.

> **이 저장소는 [imueRoid/myComix](https://github.com/imueRoid/myComix) (v0.488)에서 포크하여 대규모로 확장·재구성한 버전입니다.** 원본의 만화 뷰어 기능을 기반으로 보안, 성능, 멀티미디어 지원, 관리 기능 등을 전면 강화했습니다.

---

## 🔄 원본 대비 개선 사항

### 📐 규모 비교

| 항목 | 원본 (v0.488) | 포크 (v2.4) | 증가 |
|------|--------------|-------------|------|
| **PHP 파일 수** | 9개 | 25개 | **+178%** |
| **총 코드 라인 수** | ~5,000 라인 (추정) | **47,640 라인** | **약 9배** |
| **PHP 요구 버전** | 7.0+ | **8.0+** | 최신화 |
| **폴더 구조** | 평면 (루트만) | `lang/`, `css/`, `js/`, `src/`, `cache/` | 모듈화 |
| **다국어** | 한국어만 | 한국어 + 영어 (1,400+ 키) | 신규 |
| **DB/캐시** | 없음 | JSON 캐시 + APCu/OPcache | 신규 |
| **사용자 그룹** | 단순 권한 | 5단계 (admin + group1~4) | 확장 |

### 🆕 원본에 없던 신규 기능

#### 미디어 형식 확장
- ✨ **EPUB 뷰어** — 챕터 네비게이션, 폰트/테마, 진행률 저장 (`epub_viewer.php`)
- ✨ **TXT 뷰어** — 청크 로딩, 1단/2단 레이아웃, 진행률 저장 (`txt_viewer.php`)
- ✨ **HWP 뷰어** — 한글 문서 지원 (`hwp_viewer.php`)
- ✨ **오피스 문서 뷰어** — DOCX, XLSX, PPTX (`office_viewer.php`)
- ✨ **동영상 스트리밍** — MP4, WebM, MKV 등 10종 + HLS 변환
- ✨ **RAR/7Z 지원** — 원본은 ZIP만, 포크는 6종 압축 형식 (`archive_handler.php`)
- ✨ **WebP/BMP 이미지** 추가 지원

#### 보안 (원본에는 거의 없음)
- 🔐 **2FA (TOTP)** — Google Authenticator 호환
- 🔐 **CSRF 보호** — 토큰 기반 검증
- 🔐 **XSS 방어** — `sanitizeHtml()` DOMDocument 화이트리스트
- 🔐 **경로 검증** — Directory Traversal 차단 (`security_helpers.php`)
- 🔐 **명령 인젝션 방지** — `escapeArg()` 함수
- 🔐 **IP/국가 차단** — GeoIP 255개국, MaxMind DB 통합 (`MaxMindReader.php`)
- 🔐 **브루트포스 방지** — 5회 실패 시 잠금
- 🔐 **CSP 헤더** — Content Security Policy 통합
- 🔐 **보안 헤더** — X-XSS-Protection, X-Content-Type-Options, X-Frame-Options
- 🔐 **자동 로그아웃** — 비활성 시간 기반 (`auto-logout.js`)
- 🔐 **Privacy Shield** — 모바일 화면 가림 (`privacy_shield.php`)

#### 관리 기능
- 🛠️ **관리자 패널 전면 재설계** — 12,400+ 라인 (원본 대비 수십 배)
- 🛠️ **다중 콘텐츠 폴더** (`base_dirs`) — 원본은 단일 폴더만
- 🛠️ **5단계 권한 그룹** — 폴더별 접근 제어
- 🛠️ **회원가입 시스템** — 활성화/승인제 옵션
- 🛠️ **SMTP 이메일** — 비밀번호 찾기, 알림
- 🛠️ **활동 로그** — 최대 5,000건, 페이지네이션
- 🛠️ **중복 파일 찾기**
- 🛠️ **파일 업로드 / 폴더 생성 / 삭제** — 웹에서 직접 관리
- 🛠️ **팝업 공지/배너 시스템**
- 🛠️ **로그인 테마 27종** — 도서관 테마 포함

#### 성능 최적화 (I/O 속도 대폭 개선)

| 최적화 | 원본 | 포크 |
|--------|------|------|
| **JSON 캐시** | ❌ 매 요청마다 디렉토리 스캔 | ✅ 캐시 우선 + 수동 갱신 |
| **OPcache 통합** | ❌ | ✅ 관리 패널에서 제어 |
| **APCu 통합** | ❌ | ✅ 메모리 캐시 |
| **VIPS 지원** | ❌ GD만 사용 | ✅ 대용량 이미지 고속 처리 |
| **EPUB 지연 로딩** | N/A | ✅ 서버 캐시 + 진행률 유지 |
| **썸네일 캐시** | 기본 | ✅ 동영상 + 혼합 ZIP 지원 |
| **HLS 스트리밍** | ❌ | ✅ ffmpeg `-re` 플래그 실시간 |
| **로그아웃 시 캐시 정리** | ❌ | ✅ 자동 |
| **병목 I/O 최적화** | ❌ rclone에서 매우 느림 | ✅ 디렉토리 확인 최소화 |

> **체감 속도**: 대용량 라이브러리(수만 개 파일)에서 원본 대비 **약 98% 로드 시간 감소** (캐시 적중 시).

#### 뷰어 기능 강화
- 📖 **세로 분할 모드** (1\|2, 2\|1) — 원본은 가로 분할만
- 📖 **VIPS 고속 이미지 처리**
- 📖 **다크모드** 지원
- 📖 **모바일 최적화** — 반응형 UI, 터치 제스처, 핀치 줌
- 📖 **검색 기능** — 한글/영문 동시 검색
- 📖 **NEW 배지** — 최근 추가 콘텐츠 표시
- 📖 **자연어 정렬** — 1, 2, 10, 11 순
- 📖 **폴더별/전체 파일 카운트**

#### 인프라 / 확장성
- 🌐 **다국어 시스템** (`i18n.php`) — 한국어/영어, 1,400+ 번역 키
- 🌐 **모듈화 구조** — `bootstrap.php`, `init.php`, `security_helpers.php` 분리
- 🌐 **PHP 8.0 ~ 8.x 지원** — match, arrow function, null safe 등 최신 문법 활용
- 🌐 **Docker 지원 가능** (Synology 등 NAS 환경)

### 🐛 원본 이슈 수정

원본의 알려진 문제들이 포크에서는 모두 해결되었습니다:

| 원본 이슈 | 포크 해결 |
|-----------|----------|
| `dir`에 `%2F..` 입력 시 상위 디렉토리 노출 (v0.488 패치) | `validate_file_path()` + `resolve_path_from_basedirs()` 다중 검증 |
| 사파리에서 자동 북마크 미저장 | 다중 이벤트 + sessionStorage 폴백 |
| rclone 사용 시 심각한 속도 저하 | JSON 캐시 + `is_remote` 자동 감지 |
| 권한 부족 시 화면이 안 뜸 | 명확한 에러 메시지 + 진단 패널 |
| `php-zip`, `php-gd` 외 추가 지원 부재 | 다양한 확장 + 외부 도구 통합 |
| 사용자 그룹 관리 버그 | 5단계 권한 시스템으로 재설계 |

---

## 📊 지원 파일 형식

| 분류 | 확장자 |
|------|--------|
| **압축** | zip, cbz, rar, cbr, 7z, cb7 |
| **이미지** | jpg, jpeg, png, gif, webp, bmp |
| **동영상** | mp4, webm, mkv, avi, mov, m4v, wmv, flv, m2t, ts |
| **문서** | pdf, txt, epub, hwp, docx, xlsx, pptx |

---

## ✨ 주요 기능

### 🔧 관리자 기능

- 다중 콘텐츠 폴더(`base_dirs`) 지원
- 5단계 권한 그룹 (admin, group1~4)
- 폴더별 접근 권한 설정
- IP/국가 차단 (GeoIP 255개국, 9개 지역별 분류)
- 브루트포스 방지 (5회 실패 시 잠금)
- 로그인 테마 27종
- 브랜딩 커스터마이징 (로고, 사이트명, 타이틀)
- 팝업 공지/배너 시스템
- SMTP 이메일 설정
- 캐시 관리 (OPcache, APCu)
- 활동 로그 (최대 5,000건)
- 중복 파일 찾기
- 회원가입 설정 (활성화/승인제)
- 폴더 생성 / 파일 업로드 / 삭제
- 연관 파일 자동 정리

### 👤 사용자 기능

- 2FA (TOTP) — Google Authenticator 호환
- 자동 로그인 (30일 유지)
- 회원가입 (관리자 설정에 따라)
- 비밀번호 찾기 (SMTP)
- 프로필 관리
- 로그인 기록 조회
- 회원 탈퇴

### 📖 뷰어 기능

- **이미지 뷰어**: Toon/Book 모드, 좌→우/우→좌, 세로 분할 (1\|2, 2\|1), VIPS 고속 처리
- **동영상**: HTML5 + HLS 스트리밍
- **PDF**: PDF.js 기반
- **EPUB**: 챕터 네비, 폰트/테마, 진행률, 지연 로딩 + 서버 캐시
- **TXT**: 청크 로딩, 1단/2단 레이아웃, 진행률
- **HWP / Office**: 한글 / DOCX / XLSX / PPTX 뷰어

### 🔖 북마크

- 수동 북마크 + 자동 저장
- 즐겨찾기, 표지 설정 (`[cover].jpg`)
- EPUB/TXT 진행률 저장
- 같은 파일명.jpg 썸네일

### 🔐 보안

- CSRF, XSS, 경로 검증, 명령 인젝션 방지
- IP/국가 차단, 브루트포스 방지
- 활동 로그, 업로드 확장자 제한 (34종)
- 2단계 삭제 확인, 루트 폴더 삭제 방지
- CSP 헤더, 보안 헤더 통합

---

## ⚙️ 요구사항

| 항목 | 내용 |
|------|------|
| **PHP** | 8.0 이상 (8.1, 8.2, 8.3 권장) |
| **필수 확장** | `zip`, `gd`, `json`, `mbstring` |
| **권장 확장** | `intl`, `apcu`, `opcache` |
| **외부 도구** | `unrar`, `7z`, `ffmpeg`, `vips` |
| **웹 서버** | Apache 2.4+ (mod_rewrite, mod_headers) |

> ⚠️ **PHP 7.x는 지원하지 않습니다.** PHP 7.4는 2022년 11월에 공식 지원이 종료되었으며, 본 프로젝트는 `match` 표현식 등 PHP 8.0+ 문법을 사용합니다.

---

## 📁 파일 구조

```
mycomix/
├── index.php              # 메인 파일 탐색 (9,776 lines)
├── admin.php              # 관리자 패널 (12,435 lines)
├── login.php              # 인증 (27가지 테마, 4,359 lines)
├── viewer.php             # 이미지/동영상 뷰어 (5,502 lines)
├── epub_viewer.php        # EPUB 뷰어 [신규]
├── txt_viewer.php         # TXT 뷰어 [신규]
├── hwp_viewer.php         # HWP 뷰어 [신규]
├── office_viewer.php      # 오피스 문서 뷰어 [신규]
├── bookmark.php           # 북마크 관리
├── thumb.php              # 썸네일 생성
├── archive_handler.php    # 압축 파일 처리 [신규]
├── ip_block.php           # IP/국가 차단 [신규]
├── MaxMindReader.php      # GeoIP 처리 [신규]
├── privacy_shield.php     # 모바일 화면 가림 [신규]
├── security_helpers.php   # 보안 함수 통합 [신규]
├── cache_util.php         # 캐시 유틸리티 [신규]
├── i18n.php               # 다국어 처리 [신규]
├── admin_translations.php # 관리자 번역 [신규]
├── config.php             # 설정
├── bootstrap.php          # 공통 초기화 [신규]
├── init.php               # 세션/보안 헤더 [신규]
├── session_check.php      # 세션 검증 [신규]
├── function.php           # 공용 함수
├── warmup.php             # 캐시 워밍업 [신규]
├── blank.php              # 빈 페이지 [신규]
├── lang/                  # 언어 파일 (ko, en) [신규]
│   ├── ko.json/php
│   └── en.json/php
├── css/                   # 스타일시트
│   ├── bootstrap.min.css
│   ├── darkmode.css       [신규]
│   ├── lightgallery.min.css
│   └── swiper-bundle.min.css
├── js/                    # 자바스크립트
│   ├── jquery-3.5.1.min.js
│   ├── bootstrap.min.js
│   ├── pdf.min.js
│   ├── pdf.worker.min.js
│   ├── swiper-bundle.min.js
│   ├── crypto-js.min.js   [신규]
│   ├── i18n.js            [신규]
│   ├── darkmode.js        [신규]
│   └── auto-logout.js     [신규]
├── src/                   # 설정 데이터 (JSON)
└── cache/                 # 임시 캐시 (자동 삭제)
```

---

## 🚀 설치

1. 웹 서버 루트 또는 하위 디렉토리에 파일 업로드
2. `src/` 폴더에 쓰기 권한 부여 (`chmod 755 src/`)
3. `cache/` 폴더에 쓰기 권한 부여 (`chmod 755 cache/`)
4. `config.php`에서 `base_dirs` 경로 설정
5. 웹 브라우저로 `index.php` 접속 → 초기 관리자 계정 생성
6. 관리자 패널에서 권한, 보안, 브랜딩 설정

> 원본은 `$base_dir`(단수)였지만, 포크는 `$base_dirs`(복수)로 다중 폴더 지원합니다.

---

## ⚠️ 주의사항

- **HTTPS 강력 권장** (`.htaccess`에 자동 리다이렉트 포함)
- `src/` 폴더는 외부 직접 접근 차단됨
- 대용량 파일 처리 시 `php.ini` 조정 필요 (`memory_limit`, `upload_max_filesize`, `post_max_size`)
- iOS Safari에서는 일부 MSE 기능이 제한될 수 있음
- 압축 파일 미리보기에는 `unrar` / `7z` 외부 도구 필요

---

## 📝 변경 이력

### v2.4.2 (2026-06-12)

**버그 수정 — iOS 모바일 사생활 보호모드 탭 복귀 시 간헐적으로 홈으로 이동하던 문제**
- **로그인 유지 상태에서 탭 복귀 시 보던 화면 대신 홈(index.php)으로 가던 문제 수정** — 사생활 보호모드는 백그라운드 전환 시 `blank.php?return=<보던 화면 URL>`로 이동했다가, 복귀 시 `session_check.php`로 세션을 확인해 유효하면 `return` 화면으로 되돌아감. 그런데 iOS에서 탭을 빠르게 복귀할 때(또는 백그라운드 탭 폐기 후 재로딩 시) 세션 확인이 그 순간 일시적으로 `false`로 떨어지는 경쟁(race) 상황이 발생할 수 있었음. 이때 `blank.php`가 `login.php`로 보냈고, **로그인 유지(remember_token)** 쿠키가 있으면 `login.php`가 자동 로그인 후 무조건 `index.php`(홈)로 보내 보던 화면을 잃었음(일반 로그인은 토큰이 없어 로그인창에 머무르므로 이 증상이 없었고, 그래서 시간과 무관하게 무작위로 갈렸음). ① `blank.php`가 로그인으로 보낼 때 보던 화면 URL(`return`)을 함께 전달하고, ② `login.php`가 자동 로그인 성공(remember_token) 및 이미 로그인된 것으로 보이는 복귀 경로에서 홈 대신 그 `return` 화면으로 복귀하도록 수정. 외부 사이트로의 오픈 리다이렉트를 막기 위해 `return`은 **같은 호스트의 절대경로(`/`로 시작)+쿼리만** 허용하고(CRLF 제거, 백슬래시 포함·프로토콜 상대 경로·`@` 우회 차단), 없거나 외부면 기존대로 `index.php`로 폴백. 사용자가 직접 로그인 폼을 제출하는 능동 로그인 경로는 기존대로 홈 이동을 유지.

> 코드 변경은 `config.php`(버전)와 `blank.php`·`login.php`에 한정됩니다. v2.4.1의 변경 파일(`index.php`·`function.php`·`viewer.php`·`thumb.php`·`archive_handler.php`)은 그대로 유지되며, 그 외 파일은 v2.4 원본과 동일합니다. 실서버(iOS·실세션) 환경에서의 재현 검증은 배포 후 확인이 필요합니다.

### v2.4.1 (2026-06-07)

**버그 수정 ① 같은 이름 다른 확장자 파일 누락 (캐시·표시 2단계)**
- **압축별 캐시 키 분리 (rar/7z는 확장자 포함 경로 사용)** — 같은 이름에 확장자만 다른 압축(`만화.zip`, `만화.rar`, `만화.7z`)이 한 폴더에 있을 때, 기존에는 셋 다 확장자를 떼어낸 `만화.json` 하나를 공유하여 썸네일·페이지 수 캐시가 서로 덮어써졌음. 캐시 경로 생성을 `get_cache_json_path()` 헬퍼로 일원화하여 rar/7z는 확장자를 포함한 경로(`만화.rar.json`, `만화.7z.json`)를, zip은 기존 경로(`만화.json`)를 그대로 사용(하위 호환 유지). 캐시를 읽고 쓰는 지점(목록·썸네일·커버·뷰어·`thumb.php`) 13곳을 모두 헬퍼 경유로 통일.
- **목록 중복 제거가 확장자를 무시해 같은 이름 압축이 사라지던 문제 수정** — 위 캐시 수정 후에도 목록을 화면에 표시하기 직전의 중복 제거 단계가 파일명에서 확장자를 떼어낸 이름(`만화`)을 기준으로 비교하여 먼저 처리된 하나만 남기고 나머지를 목록에서 제외했음. 캐시 자체는 정상이므로 강제 재생성을 해도 증상이 사라지지 않던 원인. 중복 제거 기준을 확장자를 포함한 전체 파일명으로 변경하여 같은 이름의 서로 다른 압축이 각각 표시되도록 수정. 검색은 기존대로 확장자를 뗀 이름으로 매칭하므로 "만화"로 검색하면 셋 다 잡힘.

**버그 수정 ② RAR 한글·특수문자 파일명 내용 표시 불가**
- **RAR 압축 내부에 한글·특수문자 폴더명이나 한글 이미지 파일명, 알파벳 분류 폴더 등이 있으면 뷰어에서 열리지 않던 문제 수정** — ZIP·7Z는 압축 내부 파일명에 특수문자·한글이 있어도 정상적으로 읽었으나, RAR은 기존 `archive_handler.php`에서 외부 도구로 특정 파일을 추출할 때 파일명 인코딩이 일치하지 않아 추출 결과를 찾지 못하고 내용을 표시할 수 없었음. ① 추출 시 UTF-8 문자셋 옵션(`-scu`)을 적용해 목록·추출 파일명의 인코딩을 일치시키고, ② 파일명 정규화(UTF-16LE/CP949 등 보정)로 매칭을 안정화하며, ③ 이름 매칭이 실패해도 추출 폴더 재귀 검색 → 전체 추출 후 목록 순번 매칭으로 이어지는 폴백 단계를 추가(경로 구조를 유지해 하위폴더의 같은 이름 파일이 덮어써지는 충돌 방지), ④ Windows·Linux 간 명령 차이(표준오류 리다이렉션·경로 구분자·표준입력 차단)를 분기 처리하여 양쪽 환경 모두에서 동작.

> 위 항목은 v2.4 배포본 이후의 작업분을 v2.4.1로 묶은 것입니다. 같은 이름 문제는 "캐시 키 충돌"과 "목록 표시 중복 제거" 두 단계가 각각 원인이었고 둘 다 해결했습니다. 코드 변경은 `config.php`(버전)와 `index.php`·`function.php`·`viewer.php`·`thumb.php`·`archive_handler.php`에 한정되며, 그 외 파일은 v2.4 원본과 동일합니다.

### v2.4 (2026-06)

**RAR / 7Z 압축 만화 지원 (신규)**
- **RAR(.rar, .cbr) / 7Z(.7z, .cb7) 압축 파일 지원 추가** — 기존엔 ZIP(.zip, .cbz)만 만화로 인식했으나, RAR·7Z 압축도 ZIP과 동일하게 목록 표시·썸네일·페이지 뷰어·북마크까지 모두 지원. 외부 명령줄 도구(UnRAR, 7-Zip)를 통해 처리하며, 관리자 설정에서 도구 경로를 지정.
- **검증된 ZIP 처리 흐름 재사용** — 별도 코드를 새로 만들지 않고, 안정적으로 동작하던 ZIP 경로에 "압축 목록 읽기"와 "개별 이미지 추출" 두 동작만 압축 종류별로 분기. 썸네일 생성·캐시 저장·표시 등 이후 로직은 ZIP과 100% 동일하게 동작하여 안정성 확보.
- **압축 형식이 거치는 모든 경로 일괄 대응** — 메인 목록 표시, 관리자 캐시 재생성(썸네일·커버·이미지목록·동영상목록), 뷰어 페이지 추출, 캐시 사전 생성(warmup), 동영상 압축 판별, 북마크 커버 생성 등 ZIP을 처리하던 모든 지점에 RAR/7Z 대응 추가.
- **관리자 설정 안내 강화** — 압축 도구 설정 항목에 "GUI 프로그램(WinRAR.exe)이 아닌 명령줄 도구(UnRAR.exe)를 연결해야 한다"는 안내 추가. GUI 프로그램 연결 시 페이지 무한 로딩이 발생하는 문제를 사전 방지.

> ZIP 처리 코드는 원본 그대로 유지하고 RAR/7Z 분기만 추가했으므로, 기존 ZIP 만화의 동작에는 영향이 없습니다.

### v2.3 (2026-05)

**성능 최적화 (폴더 진입 속도 개선)**
- **동영상 ZIP 캐시 통과 수정** — 동영상 ZIP(`.video_files.json` 보유)이 기존엔 `.image_files.json` 부재로 캐시 통과에 실패해, 표시할 때마다 ZipArchive를 다시 열고 메타파일을 재기록했음. 이 재기록이 폴더 mtime을 갱신시켜 다음 진입 시 파일 목록 캐시가 무효화(`cache_old`)되고, 결과적으로 폴더 전체를 매번 재스캔하는 악순환이 발생. 동영상 ZIP은 `.video_files.json` 존재로 캐시를 판정하도록 보강하여 악순환 제거. (간헐적으로 상위 폴더 진입이 수 초까지 느려지던 현상 해결)
- **커버 썸네일 중복 stat 제거** — 폴더 대표 커버(`[cover].jpg`)는 폴더당 하나인데, 기존엔 표시되는 파일마다(수백~수천 회) 동일 파일을 `is_file`+`filesize`로 반복 확인했음. 폴더당 한 번만 확인하도록 변경하여 디스크 I/O 대폭 감소. (파일이 많은 폴더일수록 효과 큼)
- **NEW 딱지 시각 계산을 캐시값으로 대체** — NEW 딱지(최근 추가 표시) 판정에 파일마다 `filectime()`을 디스크에서 호출(수백 회)하던 것을, 이미 파일 목록 캐시에 저장된 `time`(filemtime) 값을 사용하도록 변경하여 디스크 접근 회피. 캐시에 값이 없는 예외 상황에서는 기존 `filectime()` 방식으로 폴백. (OS 파일 캐시가 식은 첫 접근 시 부담 감소)
- **폴더 개수 카운트 최적화** — 폴더 리스트를 그릴 때 각 폴더의 항목 수를 `scandir`(전체 배열 생성+정렬)로 세던 것을 `FilesystemIterator`(개수만 카운트)로 변경. 결과값은 동일하며, 폴더가 많은 목록일수록 디스크 I/O와 메모리 부담 감소.

> 위 최적화는 모두 동작 결과를 바꾸지 않고 디스크 접근(I/O) 횟수만 줄이는 변경입니다. 파일/폴더가 많은 환경에서 OS 파일 캐시가 식은 상태(오랜만의 첫 접근)일 때 체감 속도가 개선됩니다.

### v2.2 (2026-01)

**뷰어**
- 세로 분할 모드 추가 (`pageorder` 3, 4) — 1\|2, 2\|1
- VIPS 라이브러리 지원 — 대용량 이미지 고속 변환
- TXT 뷰어 페이지 레이아웃 모드 (1단/2단)
- EPUB 뷰어 지연 로딩 + 서버 캐시
- HWP 뷰어 v3.0
- 이미지+동영상 혼합 ZIP 썸네일 지원

**보안**
- `escapeArg()` 함수 추가 — 명령 인젝션 방지 강화
- 토큰 관련 함수 통합
- `safe_redirect()` 동적 경로 지원
- 보안 헤더 통일 (CSP / X-XSS / X-Frame)
- 다중 base_dir 환경 경로 검증 강화

**관리**
- 로그인 테마 0번 "도서관" 추가 (총 27종)
- 관리자 패널 폴더 생성 / 파일 업로드 / 삭제
- 연관 파일 자동 정리

**기타**
- IMAGE_EXT_PATTERN 상수 통합
- 헬퍼 함수 추가 (`is_image_file()`, `is_viewable_file()`)
- Privacy Shield 호환성 개선
- 탭 열기 시 캐시 플래그 자동 초기화

---

## 🤝 기여

이 프로젝트는 개인 사용을 목적으로 개발되었으나, 버그 리포트와 개선 제안은 언제나 환영합니다. Pull Request보다는 Issue 등록을 우선 권장합니다.

---

## 📜 라이선스

이 프로젝트는 [MIT License](LICENSE)로 배포됩니다.

- **Original work**: Copyright (c) 2020 [imueRoid](https://github.com/imueRoid/myComix) — 원본 myComix 저작권자
- **Fork maintenance**: Copyright (c) 2026 펜닐 — 본 포크 메인테이너

번들된 서드파티 라이브러리의 라이선스는 [LICENSE](LICENSE) 파일을 참조하세요.

---

## 🙏 감사의 말

이 프로젝트는 [imueRoid](https://github.com/imueRoid)님의 [myComix](https://github.com/imueRoid/myComix) 원본을 기반으로 합니다. 만화 뷰어의 핵심 아이디어와 기본 구조를 제공해 주신 원작자께 감사드립니다.

## 🔗 사용 라이브러리

- [jQuery](https://jquery.com/) — MIT
- [Bootstrap](https://getbootstrap.com/) — MIT
- [Swiper](https://swiperjs.com/) — MIT
- [PDF.js](https://mozilla.github.io/pdf.js/) — Apache 2.0
- [CryptoJS](https://github.com/brix/crypto-js) — MIT
- [LightGallery](https://www.lightgalleryjs.com/) — GPLv3 / Commercial
- [Popper.js](https://popper.js.org/) — MIT
