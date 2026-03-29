[English](README.md)

# 테마 가이드

테마는 기본적으로 `wikiwikiwiki/`의 `assets/`, `templates/`, `i18n/`을 덮어쓰는 방식으로 작동합니다. 테마 폴더에 이름이 같은 파일이 있다면 테마 파일을 우선합니다. 테마 폴더명이 곧 테마명이 됩니다. 테마명에는 알파벳, 숫자, `-`, `_`만 사용할 수 있습니다.

---

## 시작하기

가장 좋은 시작은 `assets/css/style.css`만 수정하는 것입니다. **설정 → 기본 → 테마**에서 `starter`를 지정하세요.

---

## 1단계: CSS

```text
starter/
└── assets/
    └── css/
        └── style.css
```

`assets/css/style.css`가 있으면 위키위키위키의 기본 CSS가 **완전히 교체**됩니다. 위키위키위키의 기본 템플릿에 사용되는 클래스명은 다음과 같습니다.

**레이아웃**
- `.document`, `.header`, `.main`, `.footer`, `.section`, `.section-header`, `.section-main`, `.section-footer`

**타이포그래피**
- `.title`, `.content`

**링크**
- `.exists`, `.not-exists`, `.external`, `.wikipedia`, `.tag`, `.iframe-link`

**폼**
- `.form`, `.form.is-fill`, `.fields`, `.field`, `.field.is-fill`, `.field-help`, `.label`, `.input`, `.textarea`

**버튼**
- `.buttons`, `.button`, `.button.is-primary`, `.button.is-danger`, `.button.is-link`

**탭**(계정 및 설정 페이지)
- `.tabs`, `.tab`, `.tab-content`

**Diff**(편집 충돌·히스토리 비교)
- `.diff`, `.diff-sign`, `.diff-line`, `.diff-add`, `.diff-remove`, `.diff-context`

**유틸리티**
- `.hidden`, `.skip`, `.flash`

**기타**
- `.table`

페이지별 설계가 필요하다면 `base.php` `<body>`의 `id`를 활용할 수 있습니다. 현재 템플릿명(`view`, `edit`, `search`, `account`, `settings` 등)이 그대로 지정됩니다.

```css
/* 문서 보기 페이지만 본문 글자 크기를 크게 */
body#view .content { font-size: 1.5rem; }
```

## 2단계: 문구

버튼 텍스트나 안내 메시지를 바꾸고 싶다면 해당 언어 파일을 추가합니다.

```text
starter/
└── i18n/
    ├── ko.json
    └── en.json
```

키가 같으면 테마의 값이 위키위키위키의 기본값을 덮어씁니다.

```json
{
    "button.save": "게시",
    "button.edit": "수정"
}
```

## 3단계: 레이아웃

헤더, 메인, 푸터 등 레이아웃을 직접 설계하려면 `templates/base.php`를 추가합니다. `base.php`는 모든 페이지를 아우르는 기본 레이아웃 파일입니다. 처음에는 `wikiwikiwiki/templates/base.php`를 복사해 시작하는 것이 좋습니다.

```text
starter/
└── templates/
    └── base.php
```

`templates/`에 이름이 같은 파일을 추가하면 개별 페이지 템플릿까지 바꿀 수 있습니다. 특정 페이지만 구조를 바꾸고 싶을 때 유용합니다. 단, 레이아웃을 직접 설계하면 위키위키위키 업그레이드 시 `wikiwikiwiki/templates/base.php`의 변경 사항을 직접 반영해야 할 수 있습니다.

```text
starter/
└── templates/
    ├── base.php       # 기본 레이아웃
    ├── view.php       # 문서 보기
    ├── edit.php       # 편집
    ├── search.php     # 검색
    ├── discover.php   # 둘러보기
    ├── history.php    # 편집 이력
    ├── account.php    # 계정
    ├── settings.php   # 설정(관리자)
    ├── 404.php        # 문서 없음
    └── ...            # 그 외 템플릿과 동일한 파일
```

### 템플릿에서 사용할 수 있는 함수

**출력/번역**

| 함수 | 설명 |
|---|---|
| `html($str)` | 문자열을 HTML 안전하게 출력 |
| `t($key)` | 번역 문자열 반환, 키가 없으면 키 자체를 반환 |

**URL/에셋**

| 함수 | 설명 |
|---|---|
| `url($path)` | 위키위키위키 기준 경로 URL 생성, `/search` 같은 경로 또는 문서 제목 전달 |
| `page_url($title)` | 문서 전체 URL 반환 |
| `asset($file)` | 에셋 URL 반환, 테마 파일이 있으면 테마 파일 우선 |
| `css($file)` | CSS `<link>` 태그 문자열 생성 |
| `js($file)` | JS `<script>` 태그 문자열 생성 |

**인증/권한**

| 함수 | 설명 |
|---|---|
| `is_logged_in()` | 로그인 여부 |
| `is_admin()` | 관리자 여부 |
| `is_public()` | 편집 권한이 공개(public)인지 여부 |
| `current_user()` | 현재 사용자명, 비로그인 시 `null` |
| `current_role()` | 현재 사용자 역할, `'admin'`, `'editor'`, 또는 빈 문자열 |

**폼/보안**

| 함수 | 설명 |
|---|---|
| `csrf_token()` | 폼 보안 토큰 문자열 반환 |
| `honeypot_field()` | 스팸 방지용 hidden input HTML 반환 |

**데이터**

| 함수 | 설명 |
|---|---|
| `page_recent($limit)` | 최근 편집 문서 목록 반환, 기본 10개 |
| `page_random($limit)` | 무작위 문서 목록 반환, 기본 10개 |

### 템플릿에서 사용할 수 있는 변수

| 변수 | 설명 |
|---|---|
| `$wikiTitle` | 위키 제목 |
| `$wikiDescription` | 위키 설명 |
| `$pageTitle` | 현재 페이지 제목 |
| `$description` | 현재 페이지 설명, 없으면 빈 문자열 |
| `$language` | 언어 코드(예: `ko`) |
| `$languageCode` | 로케일 코드(예: `ko_KR`) |
| `$ogUrl` | 현재 페이지 전체 URL |
| `$basePath` | URL 기준 경로 |
| `$csrfToken` | 폼 보안 토큰 |
| `$flashes` | 알림 메시지 배열, 각 항목은 `type`과 `message` 키를 지님 |
| `$recentPages` | 최근 편집한 문서 배열, 기본 5개 |
| `$section` | 현재 페이지 본문 HTML, `base.php` 전용 변수 |
| `$template` | 현재 템플릿명, `<body id>` 속성에 사용, `base.php` 전용 변수 |

## 4단계: 자바스크립트

`assets/js/script.js`를 추가하면 위키위키위키의 기본 자바스크립트가 **완전히 교체**됩니다.

```text
starter/
└── assets/
    └── js/
        └── script.js
```

자바스크립트 파일을 직접 작성하면 다음 기능도 직접 구현해야 합니다.

### 탭 전환

계정 및 설정 페이지는 첫 번째 패널만 보이고, 나머지 패널은 `hidden` 상태로 시작합니다. 자바스크립트 없이 모두 표시하려면 `[hidden]` 처리까지 함께 재정의해야 합니다.

```css
/* 자바스크립트 없이 모든 탭 내용 표시 */
.tab-content[hidden] { display: block; }
```

```javascript
// 최소 구현 예시
document.querySelectorAll('.tabs').forEach(tabList => {
    const tabs = Array.from(tabList.querySelectorAll('.tab[data-target]'));
    const container = tabList.parentElement;
    if (!container) return;
    const panes = Array.from(container.querySelectorAll(':scope > .tab-content[data-id]'));

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.target || '';
            tabs.forEach(button => {
                button.setAttribute('aria-selected', button === tab ? 'true' : 'false');
            });
            panes.forEach(pane => {
                pane.hidden = (pane.dataset.id || '') !== target;
            });
        });
    });
});
```

### 문서 삭제 및 복원 확인 메시지

`[data-confirm-message]` 속성이 있는 버튼에 `confirm`이 없으면 문서 삭제와 복원이 확인 없이 즉시 실행됩니다.

```javascript
// 최소 구현 예시
document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    const message = submitter?.dataset.confirmMessage || form.dataset.confirmMessage || '';
    if (message && !confirm(message)) {
        event.preventDefault();
    }
});
```

---

## 변경 사항이 적용되지 않을 때

- 먼저 **설정 → 기본 → 테마**에서 현재 테마를 선택했는지 확인하세요.
- 파일 경로가 `themes/{테마명}/assets/...`, `themes/{테마명}/templates/...`, `themes/{테마명}/i18n/...` 형태인지 확인하세요.
- `style.css` 또는 `script.js` 파일이 실제로 존재하고 저장됐는지 확인하세요.
