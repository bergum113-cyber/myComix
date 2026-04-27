# myComix

> PHP 기반 개인 만화/미디어 서재 웹 애플리케이션 (대규모 확장 포크)

[![Version](https://img.shields.io/badge/version-v2.2-blue.svg)](#)
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

| 항목 | 원본 (v0.488) | 포크 (v2.2) | 증가 |
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

## 📝 변경 이력 (v2.2 주요 변경)

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
