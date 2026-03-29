[한국어](README.ko.md)

# WikiWikiWiki

> "Good things are worth repeating. At least three times."

**WikiWikiWiki** is a flat-file PHP wiki engine. No database, no complex setup, just write.

[WikiWikiWiki Wiki](https://wikiwikiwiki.wiki)

## Features

- Easy setup
- Markdown support
- Wiki links (`[[Document title]]`), transclusion (`![[Document title]]`), hashtags (`#tag`), redirects
- Discover and search
- Edit history
- Edit conflict protection
- Document and history export
- RSS, Atom, JSON Feed, sitemap, llms.txt, llms-full.txt, read-only API
- User management
- Edit permission (private / public / fully public)
- Custom themes
- Multilingual (English, Korean)
- Dark mode
- ...

## Requirements

- PHP `8.2+`
- Required extensions: `json`, `mbstring`, `session`, `hash`, `pcre`
- Recommended extensions: `zip` (export), `intl` (string normalization), `gd` (og-image rendering)

## Installation

### Local (from the project root)

```bash
git clone https://github.com/minguhong/WikiWikiWiki
cd WikiWikiWiki
php -S localhost:3333
```

1. Open `http://localhost:3333`
2. Select a language
3. Enter basic info, choose an edit permission, and create an admin account

### Web Server (Apache)

Requires: `mod_rewrite` (required), `mod_headers` (optional), `mod_expires` (optional)

1. Download the latest release
2. Upload to your server (includes `.htaccess`)
3. Select a language
4. Enter basic info, choose an edit permission, and create an admin account

For Nginx, see `nginx.example.conf`

## Update

1. Download the latest release
2. Replace all files and folders except user data (`config/`, `content/`, `history/`, `users/`, `themes/`), including `.htaccess`

## Usage

### Wiki syntax

| Syntax | Description |
|---|---|
| `[[Page title]]` | Link to another page (autocomplete on desktop input environments) |
| `[[Page title\|Display text]]` | Link with alias |
| `[[Parent/Child]]` | Use `/` to separate page hierarchy |
| `![[Page title]]` | Embed another page's content inline (Transclusion) |
| `#tag` | Add a tag |
| `(redirect: Page title)` | Redirect to another page (first line of document) |

### Embeds

| Syntax | Description | Options | Example |
|---|---|---|---|
| `(image: URL)` | Image | `width`, `height`, `caption` | `(image: URL width: 300px caption: Beach)` |
| `(video: URL)` | Video (YouTube, Vimeo, etc.) | `width`, `height` | `(video: URL width: 80%)` |
| `(iframe: URL)` | iframe (`https://` URL only) | `width`, `height` | `(iframe: URL height: 400px)` |
| `(map: Address)` | Google Maps | `width`, `height` | `(map: Central Park, New York)` |
| `(codepen: URL)` | CodePen | `width`, `height` | `(codepen: URL)` |
| `(arena: URL)` | Are.na channel | `width`, `height` | `(arena: username/channel-name)` |
| `(wikipedia: Search term)` | Wikipedia link (default: wiki language) | `lang` | `(wikipedia: Markdown lang: ja)` |
| `(toc)` | Table of contents from document headings | | `(toc)` |
| `(recent: N)` | List of recently edited pages | | `(recent: 5)` |
| `(wanted: N)` | List of wanted pages | | `(wanted: 10)` |
| `(random: N)` | List of random pages | | `(random: 10)` |

Values accept `px`, `%`, `rem`, `em`, etc.

### Feeds and API

| Path | Description |
|---|---|
| `/rss.xml` | RSS feed |
| `/atom.xml` | Atom feed |
| `/feed.json` | JSON feed |
| `/sitemap.xml` | Sitemap |
| `/llms.txt` | LLM document index summary |
| `/llms-full.txt` | LLM full document content |
| `/api/all` | Full page list JSON; page item: `title`, `modified_at`, `url`, `redirect_target` |
| `/api/wiki/{title}` | `GET`: document content JSON (`title`, `content`, `modified_at`, `url`, `redirect_target`) |

### Keyboard shortcuts

| Shortcut | Description |
|---|---|
| `/` | Search |
| `e` | Edit (on document view) |
| `n` | New page |
| `Cmd/Ctrl` + `I` | Italic (new page / edit) |
| `Cmd/Ctrl` + `B` | Bold (new page / edit) |
| `Cmd/Ctrl` + `K` | Wiki link (new page / edit) |
| `Tab` | Indent (new page / edit) |
| `Shift` + `Tab` | Outdent (new page / edit) |
| `Cmd/Ctrl` + `S` or `Cmd/Ctrl` + `Enter` | Save (new page / edit) |

### Theme

See the [Theme Guide](themes/starter/README.md).

## License

[MIT](LICENSE)
