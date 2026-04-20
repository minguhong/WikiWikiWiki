[English](README.md) | [한국어](README.ko.md)

# WikiWikiWiki

> “良いものは、少なくとも三回は繰り返す価値がある。”

**WikiWikiWiki** は、テキストファイルベースの PHP ウィキエンジンです。データベースも複雑なセットアップも不要で、すぐに書き始められます。

[WikiWikiWiki Wiki](https://wikiwikiwiki.wiki)

## 主な機能

- 簡単セットアップ
- Markdown 対応
- Wiki リンク（`[[文書タイトル]]`）、トランスクルージョン（`![[文書タイトル]]`）、ハッシュタグ（`#タグ`）、リダイレクト
- 文書の探索と検索
- 編集履歴
- 編集競合の保護
- 文書と履歴のエクスポート
- RSS、Atom、JSON Feed、サイトマップ、llms.txt、llms-full.txt、読み取り専用 API
- ユーザー管理
- 編集権限（プライベート、公開、完全公開）
- カスタムテーマ
- 多言語（日本語、英語、韓国語）
- ダークモード
- ...

## 要件

- PHP `8.2+`
- 必須拡張: `json`, `mbstring`, `session`, `hash`, `pcre`
- 推奨拡張: `zip`（エクスポート）、`intl`（文字列正規化）、`gd`（OG画像レンダリング）

## インストール

### ローカル（プロジェクトルートで実行）

```bash
git clone https://github.com/minguhong/WikiWikiWiki
cd WikiWikiWiki
php -S localhost:3333
```

1. `http://localhost:3333` を開く
2. 言語を選択
3. 基本情報を入力し、編集権限を選び、管理者アカウントを作成

### Web サーバー（Apache）

必要モジュール: `mod_rewrite`（必須）、`mod_headers`（任意）、`mod_expires`（任意）

1. 最新リリースをダウンロード
2. サーバーへアップロード（`.htaccess` を含む）
3. 言語を選択
4. 基本情報を入力し、編集権限を選び、管理者アカウントを作成

Nginx の場合は `nginx.example.conf` を参照してください。

## 更新

1. 最新リリースをダウンロード
2. ユーザーデータ（`config/`, `content/`, `history/`, `users/`, `themes/`）以外のファイルとフォルダを置き換え（`.htaccess` を含む）

## 使い方

### Wiki 構文

| 構文 | 説明 |
|---|---|
| `[[ページタイトル]]` | 他ページへのリンク（デスクトップ入力環境ではオートコンプリート） |
| `[[ページタイトル\|表示テキスト]]` | エイリアス付きリンク |
| `[[親/子]]` | `/` でページ階層を表現 |
| `![[ページタイトル]]` | 他ページの内容を本文に埋め込み（トランスクルージョン） |
| `#タグ` | タグを追加 |
| `(redirect: ページタイトル)` | 別ページへリダイレクト（文書の1行目） |

### 埋め込み

| 構文 | 説明 | オプション | 例 |
|---|---|---|---|
| `(image: URL)` | 画像 | `width`, `height`, `caption` | `(image: URL width: 300px caption: 海辺)` |
| `(video: URL)` | 動画（YouTube、Vimeo など） | `width`, `height` | `(video: URL width: 80%)` |
| `(iframe: URL)` | `iframe`（`https://` のみ） | `width`, `height` | `(iframe: URL height: 400px)` |
| `(map: 住所)` | Google マップ | `width`, `height` | `(map: 東京駅)` |
| `(codepen: URL)` | CodePen | `width`, `height` | `(codepen: URL)` |
| `(arena: URL)` | Are.na チャンネル | `width`, `height` | `(arena: username/channel-name)` |
| `(wikipedia: サクランボ)` | Wikipedia リンク（既定: Wiki の言語） | `lang` | `(wikipedia: Markdown lang: ko)` |
| `(toc)` | 文書見出しベースの目次 | | `(toc)` |
| `(recent: N)` | 最近編集された文書一覧 | | `(recent: 5)` |
| `(wanted: N)` | 未作成文書一覧 | | `(wanted: 10)` |
| `(random: N)` | ランダム文書一覧 | | `(random: 10)` |

`width` / `height` には `px`, `%`, `rem`, `em` などを使用できます。

### フィードと API

| パス | 説明 |
|---|---|
| `/rss.xml` | RSS フィード |
| `/atom.xml` | Atom フィード |
| `/feed.json` | JSON フィード |
| `/sitemap.xml` | サイトマップ |
| `/llms.txt` | LLM 用文書インデックス要約 |
| `/llms-full.txt` | LLM 用全文書内容 |
| `/api/all` | 全文書一覧 JSON。各文書項目: `title`, `modified_at`, `url`, `redirect_target` |
| `/api/wiki/{title}` | `GET`: 文書内容 JSON（`title`, `content`, `modified_at`, `url`, `redirect_target`） |

### キーボードショートカット

| ショートカット | 説明 |
|---|---|
| `/` | 検索 |
| `e` | 編集（文書表示画面） |
| `n` | 新規ページ |
| `Cmd/Ctrl` + `I` | 斜体（新規/編集） |
| `Cmd/Ctrl` + `B` | 太字（新規/編集） |
| `Cmd/Ctrl` + `K` | Wiki リンク（新規/編集） |
| `Tab` | インデント（新規/編集） |
| `Shift` + `Tab` | アウトデント（新規/編集） |
| `Cmd/Ctrl` + `S` または `Cmd/Ctrl` + `Enter` | 保存（新規/編集） |

### テーマ

[テーマガイド](themes/starter/README.ja.md) を参照してください。

## ライセンス

[MIT](LICENSE)
