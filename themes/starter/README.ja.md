[English](README.md) | [한국어](README.ko.md)

# テーマガイド

テーマは、`wikiwikiwiki/` 配下の `assets/`, `templates/`, `i18n/` を上書きする形で動作します。テーマフォルダに同名ファイルがある場合はテーマ側が優先されます。フォルダ名がそのままテーマ名になります。テーマ名に使える文字は英字、数字、`-`, `_` のみです。

---

## はじめに

最も簡単な始め方は `assets/css/style.css` だけを編集することです。**Settings → Basic → Theme** で `starter` を選択してください。

---

## ステップ1: CSS

```text
starter/
└── assets/
    └── css/
        └── style.css
```

`assets/css/style.css` が存在すると、WikiWikiWiki のデフォルト CSS は **完全に置き換え** られます。WikiWikiWiki のデフォルトテンプレートで使われるクラス名は次のとおりです。

**レイアウト**
- `.document`, `.header`, `.main`, `.footer`, `.section`, `.section-header`, `.section-main`, `.section-footer`

**タイポグラフィ**
- `.title`, `.content`

**リンク**
- `.exists`, `.not-exists`, `.external`, `.wikipedia`, `.tag`, `.iframe-link`

**フォーム**
- `.form`, `.form.is-fill`, `.fields`, `.field`, `.field.is-fill`, `.field-help`, `.label`, `.input`, `.textarea`

**ボタン**
- `.buttons`, `.button`, `.button.is-primary`, `.button.is-danger`, `.button.is-link`

**タブ**（アカウント/設定ページ）
- `.tabs`, `.tab`, `.tab-content`

**Diff**（編集競合・履歴比較）
- `.diff`, `.diff-sign`, `.diff-line`, `.diff-add`, `.diff-remove`, `.diff-context`

**ユーティリティ**
- `.hidden`, `.skip`, `.flash`

**その他**
- `.table`

ページ別スタイルを当てたい場合は `base.php` の `<body>` `id` を使います。現在のテンプレート名（`view`, `edit`, `search`, `account`, `settings` など）がそのまま設定されます。

```css
/* 文書表示ページだけ本文文字サイズを大きく */
body#view .content { font-size: 1.5rem; }
```

## ステップ2: 文言

ボタン文言やメッセージを変更したい場合は i18n ファイルを追加します。

```text
starter/
└── i18n/
    ├── ko.json
    └── en.json
```

同じキーが存在する場合、テーマ側の値が WikiWikiWiki のデフォルト値を上書きします。

```json
{
    "button.save": "公開",
    "button.edit": "この文書を編集"
}
```

## ステップ3: レイアウト

ヘッダー、メイン、フッターなどを独自設計したい場合は `templates/base.php` を追加します。`base.php` は全ページを包むベースレイアウトです。まずは `wikiwikiwiki/templates/base.php` をコピーして始めるのがおすすめです。

```text
starter/
└── templates/
    └── base.php
```

`templates/` 配下に同名ファイルを置くと、個別ページテンプレートも上書きできます。特定ページだけ構造を変えたいときに便利です。なお、レイアウトを独自設計した場合、WikiWikiWiki のアップグレード時に `wikiwikiwiki/templates/base.php` の変更を手動で取り込む必要がある場合があります。

```text
starter/
└── templates/
    ├── base.php       # ベースレイアウト
    ├── view.php       # 文書表示
    ├── edit.php       # 編集
    ├── search.php     # 検索
    ├── discover.php   # みつける
    ├── history.php    # 編集履歴
    ├── account.php    # アカウント
    ├── settings.php   # 設定（管理者）
    ├── 404.php        # 文書が見つからない
    └── ...            # エンジンテンプレートと同名の任意ファイル
```

### テンプレートで使える関数

**出力/翻訳**

| 関数 | 説明 |
|---|---|
| `html($str)` | 文字列を安全な HTML として出力 |
| `t($key)` | 翻訳文字列を返す。見つからない場合はキー自体を返す |

**URL/アセット**

| 関数 | 説明 |
|---|---|
| `url($path)` | WikiWikiWiki 基準の URL を生成。`/search` のようなパスまたは文書タイトルを渡す |
| `page_url($title)` | 文書の完全 URL を返す |
| `asset($file)` | アセット URL を返す。テーマ側があればテーマ優先 |
| `css($file)` | CSS `<link>` タグ文字列を生成 |
| `js($file)` | JS `<script>` タグ文字列を生成 |

**認証/権限**

| 関数 | 説明 |
|---|---|
| `is_logged_in()` | ログイン状態かどうか |
| `is_admin()` | 管理者かどうか |
| `is_public()` | 編集権限が public かどうか |
| `current_user()` | 現在のユーザー名。未ログインなら `null` |
| `current_role()` | 現在ユーザーの権限。`'admin'`, `'editor'`, または空文字 |

**フォーム/セキュリティ**

| 関数 | 説明 |
|---|---|
| `csrf_token()` | CSRF トークン文字列を返す |
| `honeypot_field()` | スパム対策用 hidden input HTML を返す |

**データ**

| 関数 | 説明 |
|---|---|
| `page_recent($limit)` | 最近編集された文書一覧を返す。既定 10 件 |
| `page_random($limit)` | ランダム文書一覧を返す。既定 10 件 |

### テンプレートで使える変数

| 変数 | 説明 |
|---|---|
| `$wikiTitle` | Wiki タイトル |
| `$wikiDescription` | Wiki 説明 |
| `$pageTitle` | 現在ページのタイトル |
| `$description` | 現在ページの説明。なければ空文字 |
| `$language` | 言語コード（例: `ja`） |
| `$languageCode` | ロケールコード（例: `ja_JP`） |
| `$ogUrl` | 現在ページの完全 URL |
| `$basePath` | URL のベースパス |
| `$csrfToken` | フォーム用セキュリティトークン |
| `$flashes` | 通知メッセージ配列。各要素は `type` と `message` を持つ |
| `$recentPages` | 最近編集された文書配列。既定 5 件 |
| `$section` | 現在ページ本文 HTML。`base.php` 専用 |
| `$template` | 現在テンプレート名。`<body id>` に使用。`base.php` 専用 |

## ステップ4: JavaScript

`assets/js/script.js` を追加すると、WikiWikiWiki のデフォルト JavaScript は **完全に置き換え** られます。

```text
starter/
└── assets/
    └── js/
        └── script.js
```

独自 JavaScript を書く場合は、次の動作も自前で実装する必要があります。

### タブ切り替え

アカウント/設定ページでは最初のパネルだけ表示され、残りは `hidden` で始まります。JavaScript なしで全パネルを表示したい場合は `[hidden]` スタイルも上書きしてください。

```css
/* JavaScript なしで全タブ内容を表示 */
.tab-content[hidden] { display: block; }
```

```javascript
// 最小実装例
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

### 文書削除・復元前の確認

`[data-confirm-message]` が付いたボタンで `confirm` を実装しないと、文書削除と復元が確認なしで即時実行されます。

```javascript
// 最小実装例
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

## 変更が反映されない場合

- **Settings → Basic → Theme** で現在テーマが選択されているか確認してください。
- ファイルパスが `themes/{theme-name}/assets/...`, `themes/{theme-name}/templates/...`, `themes/{theme-name}/i18n/...` になっているか確認してください。
- `style.css` または `script.js` が実際に存在し保存されているか確認してください。
