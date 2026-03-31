[English](README.md) | [日本語](README.ja.md)

# 위키위키위키

> “좋은 것은 적어도 세 번 이상 반복할 필요가 있습니다.”

**위키위키위키(WikiWikiWiki)**는 텍스트 파일 기반 PHP 위키 엔진입니다. 데이터베이스 없이, 복잡한 설정 없이, 그냥 쓸 수 있는.

[위키위키위키 위키](https://wikiwikiwiki.wiki)

## 주요 기능

- 간편 설치
- 마크다운 지원
- 문서 연결(`[[문서 제목]]`), 문서 연동(`![[문서 제목]]`), 해시태그(`#태그`), 넘겨주기
- 문서 둘러보기 및 검색
- 편집 이력 관리
- 동시 편집 충돌 방지
- 문서 및 편집 이력 내보내기
- RSS, Atom, JSON Feed, 사이트맵, llms.txt, llms-full.txt, 읽기 전용 API
- 사용자 관리
- 편집 권한(비공개, 공개, 완전 공개) 설정
- 테마 추가
- 다국어(한국어, 영어, 일본어)
- 다크 모드
- ...

## 요구 사항

- PHP `8.2+`
- 필수 확장: `json`, `mbstring`, `session`, `hash`, `pcre`
- 권장 확장: `zip`(내보내기), `intl`(문자열 정규화), `gd`(OG 이미지 렌더링)

## 설치

### 로컬(프로젝트 루트에서)

```bash
git clone https://github.com/minguhong/WikiWikiWiki
cd WikiWikiWiki
php -S localhost:3333
```

1. `http://localhost:3333` 접속
2. 언어 선택
3. 기본 정보 입력, 편집 권한 선택, 관리자 계정 생성

### 웹 서버(아파치)

필요 모듈: `mod_rewrite`(필수), `mod_headers`(선택), `mod_expires`(선택)

1. 최신 릴리즈 파일 다운로드
2. 서버에 업로드(`.htaccess` 포함)
3. 언어 선택
4. 기본 정보 입력, 편집 권한 선택, 관리자 계정 생성

Nginx를 사용하는 경우 `nginx.example.conf` 참고

## 업데이트

1. 최신 릴리즈 파일 다운로드
2. 사용자 데이터(`config/`, `content/`, `history/`, `users/`, `themes/`)를 제외한 폴더 및 파일 교체(`.htaccess` 포함)

## 사용법

### 위키 문법

| 문법 | 설명 |
|---|---|
| `[[문서 제목]]` | 다른 문서로 연결(데스크톱 입력 환경에서 자동 완성) |
| `[[문서 제목\|표시할 텍스트]]` | 문서 별칭으로 연결 |
| `[[상위/하위]]` | `/`로 문서 계층 구분 |
| `![[문서 제목]]` | 다른 문서의 내용을 문서에 포함(트랜스클루전) |
| `#태그` | 모아 보기용 태그 지정 |
| `(redirect: 문서 제목)` | 다른 문서로 넘겨주기(문서 첫 줄에) |

### 임베드

| 문법 | 설명 | 옵션 | 예시 |
|---|---|---|---|
| `(image: URL)` | 이미지 | `width`, `height`, `caption` | `(image: URL width: 300px caption: 체리)` |
| `(video: URL)` | 동영상(YouTube, Vimeo 등) | `width`, `height` | `(video: URL width: 80%)` |
| `(iframe: URL)` | `iframe`(`https://`만) | `width`, `height` | `(iframe: URL height: 400px)` |
| `(map: 주소)` | 구글 지도 | `width`, `height` | `(map: 서울 강남구)` |
| `(codepen: URL)` | CodePen | `width`, `height` | `(codepen: URL)` |
| `(arena: URL)` | Are.na 채널 | `width`, `height` | `(arena: username/channel-name)` |
| `(wikipedia: 검색어)` | 위키백과 링크(기본: 위키 언어) | `lang` | `(wikipedia: Markdown lang: en)` |
| `(toc)` | 문서 제목 기반 목차 | | `(toc)` |
| `(recent: 숫자)` | 최근 편집 문서 목록 | | `(recent: 5)` |
| `(wanted: 숫자)` | 작성이 필요한 문서 목록 | | `(wanted: 10)` |
| `(random: 숫자)` | 무작위 문서 목록 | | `(random: 10)` |

`width`, `height` 값에는 `px`, `%`, `rem`, `em` 등을 사용할 수 있습니다.

### 피드 및 API

| 경로 | 설명 |
|---|---|
| `/rss.xml` | RSS 피드 |
| `/atom.xml` | Atom 피드 |
| `/feed.json` | JSON 피드 |
| `/sitemap.xml` | 사이트맵 |
| `/llms.txt` | LLM용 문서 목록 요약 |
| `/llms-full.txt` | LLM용 문서 전체 내용 |
| `/api/all` | 전체 문서 목록 JSON; 문서 항목: `title`, `modified_at`, `url`, `redirect_target` |
| `/api/wiki/{제목}` | `GET`: 문서 내용 JSON(`title`, `content`, `modified_at`, `url`, `redirect_target`) |

### 단축키

| 단축키 | 설명 |
|---|---|
| `/` | 검색 |
| `e` | 편집(문서 보기) |
| `n` | 새 문서 |
| `Cmd/Ctrl` + `I` | 강조 1(새 문서/편집) |
| `Cmd/Ctrl` + `B` | 강조 2(새 문서/편집) |
| `Cmd/Ctrl` + `K` | 위키 링크(새 문서/편집) |
| `Tab` | 들여쓰기(새 문서/편집) |
| `Shift` + `Tab` | 내어쓰기(새 문서/편집) |
| `Cmd/Ctrl` + `S` 또는 `Cmd/Ctrl` + `Enter` | 저장(새 문서/편집) |

### 테마

[테마 가이드](themes/starter/README.ko.md) 참고

## 라이선스

[MIT](LICENSE)
