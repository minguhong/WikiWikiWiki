# AGENTS.md

This file is the single execution contract for LLMs/agents working on WikiWikiWiki.

## 0) Product Summary

WikiWikiWiki is a file-based wiki for personal or small-team use. It uses no database and stores primary content in plain text files (`content/*.txt`). The runtime entry point is a single `index.php` request flow. Reading can be anonymous, while write/admin actions are controlled by authentication and role checks.

## 1) Working Principles

Use these principles for every decision:

- Prefer simpler behavior over feature breadth.
- Keep operations obvious (install, backup, restore, update).
- Keep patterns consistent across files and flows.
- Optimize for recovery and clarity, not complexity.
- Ship small, reversible changes.

If a change needs long explanation, reduce scope.

## 2) Runtime Overview

Boot sequence:

1. `index.php`
2. `wikiwikiwiki/src/bootstrap.php`
3. `wikiwikiwiki/src/load.php`
4. `wikiwikiwiki/src/router.php`

Core constants (from `bootstrap.php`):

- `WIKI_ROUTE_PATH = 'wiki'`
- `PAGE_LIST_LIMIT = 20`
- `ALL_PAGES_LIMIT = 100`
- `FEED_LIMIT = 10`
- `CACHE_FILE_MAX_BYTES = 5 * 1024 * 1024` (5 MB)
- `PHP_REQUIRED_VERSION = '8.2.0'`

Theme name validation:

- Regex: `^[A-Za-z0-9_-]{1,64}$`

### 2.1 Runtime Config Keys (`config/config.php`)

Use this as the canonical key set for settings-related work:

| Key | Default/Fallback Behavior | Notes |
| --- | --- | --- |
| `WIKI_TITLE` | missing key falls back to `'WikiWikiWiki'` | Wiki name in header and metadata |
| `WIKI_DESCRIPTION` | missing key falls back to `'A wiki made with WikiWikiWiki'` | Used in meta/RSS description |
| `BASE_URL` | `''` -> auto-detect from request | Must be `http(s)` URL when set |
| `THEME` | `''` (no theme) | Must match available theme directory |
| `LANGUAGE` | missing key falls back to resolved i18n language from available catalogs (commonly `ko`/`en`); when no catalogs exist, falls back to `en` | Must exist in language catalog when configured |
| `TIMEZONE` | missing key falls back to `''` (server timezone); invalid value falls back to `UTC` | Must be valid PHP timezone when set |
| `HOME_PAGE` | missing key falls back to `'Welcome'` | Must pass title sanitization and filename limit |
| `EDIT_PERMISSION` | `'private'` | Allowed values: `'private'`, `'public'`, `'fully_public'` |

## 3) Repository Map (What Matters at Runtime)

- Entry: `index.php`
- Engine code: `wikiwikiwiki/src/`
- Engine templates: `wikiwikiwiki/templates/`
- Engine assets: `wikiwikiwiki/assets/`
- Engine i18n: `wikiwikiwiki/i18n/`
- Config: `config/config.php`
- Theme overrides: `themes/<theme>/{templates,assets,i18n}/`
- Page source of truth: `content/*.txt`
- History snapshots: `history/*.txt`
- User data: `users/*.json`
- Derived indexes/cache: `cache/*.json`

## 4) Non-Negotiable Invariants

### 4.1 Storage and locking

- Required writable dirs: `content`, `history`, `users`, `cache`.
- Global lock file path: `/.lock` (`wiki_lock_path()`).
- State-changing file operations must run inside `wiki_with_lock(...)`.
- File writes must be atomic via `file_put_atomic(...)`.
- If storage health fails at boot, app must fail fast with HTTP 503 (`fail_fast_on_storage_unavailable()`).

### 4.2 Auth and permissions

- Read is allowed to anonymous users.
- `EDIT_PERMISSION === 'private'` and `EDIT_PERMISSION === 'public'`: create/edit/delete require login.
- `EDIT_PERMISSION === 'fully_public'`: create/edit/delete are allowed without login.
- Admin-only operations must use `require_admin()`.
- History restore (`action=restore` on `/wiki/{title}/history/{timestamp}`) requires `require_admin()`.
- `delete_history=1` on page delete is honored only for admins; non-admin requests must ignore the flag and keep history files.
- Browser form POST actions must pass CSRF (`validate_post_request()`).
- `EDIT_PERMISSION === 'public'` or `EDIT_PERMISSION === 'fully_public'` enables registration (`/register`) and enables POST honeypot checks.
- `EDIT_PERMISSION === 'private'` disables registration and skips honeypot checks.
- Anonymous edits are allowed only by `EDIT_PERMISSION === 'fully_public'`; history author for anonymous edits remains empty and UI renders `history.unknown_author`.
- Do not store raw client IP in history filenames.
- Install-first rule is mandatory:
  - If `user_count() === 0`, redirect every path except `/install` to `/install`.

### 4.3 Data model

- `content/*.txt` is canonical data.
- `cache/*.json` is disposable derived state and must be rebuildable.
- Corrupt/stale cache must degrade to rebuild, not fatal.
- User record file: `users/<normalized-username>.json`
  - Required keys: `username`, `password_hash`, `role`, `created_at`
  - `role` values: `'admin'` or `'editor'`
  - `created_at`: Unix timestamp (seconds)

## 5) Page Write Contract

Page title constraints (sanitization + storage safety):

- Sanitizer strips: `\ < > : " # % | ? * [ ]` and null byte.
- `_` is normalized to a space (underscore and space titles are equivalent).
- Consecutive hyphens (`--` and longer) are removed (`/-{2,}/`).
- Internal whitespace is normalized to single spaces.
- Leading/trailing spaces, `/`, and `.` are trimmed.
- Internal `/` is preserved as hierarchy delimiter (for example `A/B/C` => parent `A/B`).
- Max logical length: `PAGE_TITLE_MAX_LENGTH = 80` chars (`mb_strlen`).
- Filename segment must fit 255 bytes (`page_title_fits_filename_limit()`).
- Empty title after sanitization is invalid.
- Reserved route suffix patterns are invalid:
  - `.../edit`, `.../history`, `.../backlinks`
  - `.../history/YYYYmmddHHMMSS` (`\d{14}`)
  - titles ending with `.md` or `.txt`

For create/update/delete/rename flows, preserve this behavior:

1. Validate input.
2. Validate permission/CSRF.
3. Acquire lock.
4. Atomic file write/move/delete.
5. Update history snapshot markers.
6. Update index bundle (`all/search/related`).
7. Reset derived caches (`page_reset_derived_caches()`).

Reference implementation: `wikiwikiwiki/src/page/crud.php`.

Important specifics:

- `page_save(...)` normalizes text, writes content, stores history backup, prunes history, updates all indexes, resets derived caches.
- If a page file exists but has no history snapshots (legacy state), `page_save(...)` and `page_rename(...)` must seed the first snapshot from current content before write/move.
- If that required history seeding fails, `page_save(...)` and `page_rename(...)` must fail closed (no write/move).
- `page_delete(...)` writes a history delete marker file in the form `<encoded-title>.<timestamp>._deleted.txt`, then removes indexes.
- `page_rename(...)` moves content/history and writes redirect stub at old title: `(redirect: NewTitle)`.
- If one or more history file moves fail during `page_rename(...)`, the rename still completes and logs `page.rename_history_move_failed`; history may be split between old/new title bases until operator recovery.
- `create_default_page()` creates `HOME_PAGE` only after first user exists.
- Edit POST conflict contract:
  - Browser edit form must send `original_revision` (hidden field).
  - Missing `original_revision` must fail closed (no save; redirect back to edit).
  - Conflict detection compares revision tokens (mtime + file size + content hash), not mtime-only fallback.
  - Rename-to-existing (non-redirect target) must fail closed by re-rendering `edit` with conflict panel.
  - On rename collision, keep submitted `new_title` and draft content visible so user can resolve and retry.
- New POST title-collision contract:
  - If `/new` receives a title that already exists, it must not save immediately.
  - Render `edit` directly with both existing server content and the submitted draft content.
  - Keep the edit hidden field `original_revision` populated so follow-up save uses normal conflict checks.

## 6) Route Contract (router.php is source of truth)

Static/special routes:

- `/`
- `/new`
- `/all`
- `/discover`
- `/random`
- `/search`
- `/login`
- `/logout`
- `/install`
- `/register`
- `/account`
- `/settings`
- `/robots.txt`
- `/sitemap.xml`
- `/rss.xml`
- `/atom.xml`
- `/feed.json`
- `/llms.txt`
- `/llms-full.txt`
- `/api/all`
- `/tags/{tag}`

Regex routes:

- `/api/wiki/{title}`
- `/wiki/{title}/edit`
- `/wiki/{title}/history/{timestamp}` (`\d{14}`)
- `/wiki/{title}/history`
- `/wiki/{title}/backlinks`
- `/wiki/{title}.md` or `.txt`
- `/wiki/{title}`

Route order matters. Keep specific patterns before broader wiki page matchers.

## 7) API Contract

`/api/all`:

- GET only. No query parameters.
- Response: `{ "pages": [...] }`.
- Per-page keys in `pages[]`: `title`, `modified_at`, `url`, `redirect_target`.

`/api/wiki/{title}`:

- GET only.
- Title decoding path: `rawurldecode` -> `url_to_page_title`.
- 400 for invalid title.
- 404 JSON for missing page.
- Success response keys: `title`, `content`, `modified_at`, `url`, `redirect_target`.

## 8) Parser/Render Syntax Contract

Markdown rendering pipeline:

- `ParsedownExtra` -> `SmartyPants`
- Raw HTML in content is escaped (`setMarkupEscaped(true)`).
- URL/link protocols are sanitized in safe mode (`setSafeMode(true)`), including rejection of dangerous schemes.

Supported wiki syntax:

- Wiki links: `[[Page]]`
- Aliased links: `[[Page|Label]]`
- Hierarchical titles: `Parent/Child` (parent/children are derived from `/` segments and shown on `/wiki/{title}/backlinks`)
- Transclusion: `![[Page]]`
- Hashtags: `#tag`
- Redirect line: `(redirect: Target)` on first line
- Footnotes: `[^1]` / `[^1]: text`
- Heading attributes: `## Heading {#id .class}`
- Reference links: `[label][ref]` + `[ref]: https://example.com`
- Bare URLs (`https://example.com`) are auto-linked.
- Task lists (`- [ ]` / `- [x]`) are rendered as disabled checkboxes in list items, and parent lists include `task-list` class.
- Markdown tables are rendered as `<table>` and wrapped in `<div class="table">...</div>`.
- Standalone Markdown images (`![](url)` on its own line) are normalized to `<figure><img ...></figure>`.
- For standalone Markdown image normalization, `loading="lazy"` is added when missing.
- Wiki syntax/custom tags are not parsed inside code contexts (fenced code, indented code, inline code spans).
- Wiki syntax/custom tags inside Markdown link/image labels are treated as plain text (no nested parsing).
- Block embed tags remain literal when used inside Markdown list/blockquote containers.

Transclusion contract:

- Max depth: `TRANSCLUSION_MAX_DEPTH = 2`.
- Cycle detection by title stack.
- Depth/cycle violations render invalid marker using `error.invalid.transclusion`.
- Missing included page renders invalid marker using `message.page.not_exists`.

Custom embed tags in parser:

- `(video: ...)`
- `(iframe: ...)` (`https://` only)
- `(image: ...)`
- `(map: ...)`
- `(arena: ...)`
- `(codepen: ...)`
- `(wikipedia: ...)`
- `(toc)`
- `(recent: N)`
- `(wanted: N)`
- `(random: N)`

Embed URL encoding contract:

- `render_embed_arena(...)` and `render_embed_codepen(...)` must normalize path segments using `rawurldecode(...)` then `rawurlencode(...)` before URL assembly.
- Already percent-encoded input must not be double-encoded (`%EA...`/`%20` must not become `%25EA...`/`%2520`).

External links opened in new tab must include `rel="noopener noreferrer"`.

Heading anchors and TOC contract:

- Document headings must receive stable `id` anchors in rendered HTML.
- Duplicate heading ids must be suffixed (`-2`, `-3`, ...).
- `(toc)` renders from source-document headings only (does not include transcluded headings).
- `(toc)` shows only the two shallowest distinct heading levels present in the document (relative levels 1–2); deeper levels are omitted.

Invalid wiki-link contract:

- `[[title|]]` (empty alias) and `[[|alias]]` (empty title) render as `<span class="invalid">` and are protected through the placeholder system so SmartyPants cannot corrupt attribute quotes.
- A wiki link is only considered valid when both sanitized title and display text are non-empty.

## 9) Index/Cache Contract

Derived cache files:

- `cache/all.json`
- `cache/search.json`
- `cache/related.json`

Rules:

- All 3 validate `version` and content metadata (`count`, `signature`).
- Version mismatch, stale signature, missing file, or oversized file must trigger rebuild.
- Max cache file size is constrained by `CACHE_FILE_MAX_BYTES`.

## 10) Theme Contract

Theme activation:

- `THEME` must pass regex and directory existence checks.
- Invalid/missing theme falls back to engine defaults.
- If `THEME` is configured but its directory is missing at runtime, boot may auto-clear `THEME` to `''` in `config/config.php` when writable (`clear_config_theme_if_missing()`).

Resolution order:

- Template: `themes/<theme>/templates/*` -> `wikiwikiwiki/templates/*`
- Asset: `themes/<theme>/assets/*` -> `wikiwikiwiki/assets/*`
- i18n: engine language file first, then theme file overrides duplicate keys.

Theme folder contract:

- `themes/<theme>/assets/` for CSS/JS/static files
- `themes/<theme>/templates/` for template overrides
- `themes/<theme>/i18n/` for translation overrides
- Any file with the same relative path/name overrides engine behavior for that path.

Theme file optionality:

- `assets/css/style.css`: present = completely replaces engine CSS; absent = engine CSS is used.
- `assets/js/script.js`: present = completely replaces engine JS; absent = engine JS is used.
- If theme JS replaces engine JS, all user-visible behaviors in section 12 must be re-implemented by the theme.

Template context for theme authors:

- Variables injected into templates:
  - `$wikiTitle`, `$wikiDescription`, `$pageTitle`, `$description`
  - `$language`, `$languageCode`, `$ogUrl`, `$basePath`
  - `$csrfToken`, `$flashes`, `$recentPages`
  - `base.php` only: `$section`, `$template` (`<body id>` source)
- Common helper functions available in templates:
  - `html()`, `t()`, `url()`, `page_url()`, `asset()`, `css()`, `js()`
  - `is_logged_in()`, `is_admin()`, `is_public()`, `is_fully_public()`, `is_private()`, `can_edit_pages()`, `current_user()`, `current_role()`
  - `csrf_token()`, `honeypot_field()`, `page_recent()`, `page_random()`

Template override behavior:

- Overriding `templates/base.php` replaces the global shell for all pages.
- Overriding other template files (for example `view.php`, `edit.php`, `search.php`, `settings.php`) replaces those specific pages only.
- `base.php` receives `$section` (page body HTML) and `$template` (current template name used as `<body id>`).
- If `base.php` is overridden, keep feed autodiscovery links in `<head>` for `/rss.xml`, `/atom.xml`, and `/feed.json`.
- If `templates/edit.php` is overridden, keep hidden fields `csrf_token`, `original_revision`, and `delete_history`; missing `original_revision` blocks saves by contract.
- For template attributes (`href`, `action`, `src`) built from `url(...)`, always output `html(url(...))` to keep HTML valid when query strings contain `&`.

CSS override scope (default template class contracts):

- Layout: `.document`, `.header`, `.main`, `.footer`, `.section`, `.section-header`, `.section-main`, `.section-footer`
- Typography: `.title`, `.content`
- Forms: `.form`, `.form.is-fill`, `.fields`, `.field`, `.field.is-fill`, `.field.is-sticky`, `.field-help`, `.label`, `.input`, `.textarea`
- Buttons: `.button`, `.button.is-primary`, `.button.is-danger`, `.button.is-link`, `.buttons`
- Tabs: `.tabs`, `.tab`, `.tab-content`
- Diff: `.diff`, `.diff-sign`, `.diff-line`, `.diff-add`, `.diff-remove`, `.diff-context`
- Utilities: `.hidden`, `.skip`, `.flash`
- Other: `.tag`, `.table`, `.feed`
- `.table` is a shared UI wrapper used both in rendered page content and app templates (for example settings/system/user tables).
- Print (`@media print`): hide `.skip`, `.flash`, `.header`, `.footer`, `.section-footer`, `.field.is-sticky`; keep `.section-header`, `.section-main`; set `.table { overflow: visible; }`

JS replacement minimum behavior:

- Preserve all keyboard/editor behaviors listed in section 12.
- Preserve settings/account tab switching (`.tabs`, `.tab[data-target]`, `.tab-content[data-id]`).
- Preserve submit confirmation behavior for elements using `data-confirm-message`.
- If no tab JS is provided, hidden tab panels remain hidden unless CSS explicitly overrides `[hidden]`.

Public exposure boundary:

- Public static access allowed only under `wikiwikiwiki/assets` and `themes/*/assets`.
- Do not expose `wikiwikiwiki/*` except `wikiwikiwiki/assets/*`.
- Do not expose `themes/*/*` except `themes/*/assets/*`.

## 11) Settings Contract

Settings tabs are fixed:

- `basic`
- `edit`
- `documents`
- `users`
- `system`

POST actions must be explicitly mapped in `settings_action_map_by_tab()`.
Unknown action/tab must fail closed with flash error and redirect.
- In `private` mode only, the users-tab add-user form is shown.
- In `public` or `fully_public` mode, the users-tab add-user form must be hidden and `create_user` must fail closed with `flash.settings.create_user_private_only`.
- System-tab expired-session cleanup action must be explicitly mapped as `cleanup_expired_sessions` and must remain admin-only via `require_admin()`.
- Expired-session cleanup targets only `cache/sessions/sess_*` files older than `session.gc_maxlifetime`; it must not purge non-expired session files.
- When `session_module_name() !== 'files'`, system-tab session cleanup UI must stay hidden and direct POST to `cleanup_expired_sessions` must fail closed.

System tab is for runtime health checks and includes:

- Wiki version
- PHP version requirement
- Required extensions
- Storage directory health
- Session save path health (configured/default/fallback)
- Session file count (`cache/sessions/sess_*`)
- Global lock health
- Config writable health
- Bcrypt availability

## 12) Frontend Behavior Contract (script.js)

Keep these user behaviors working:

- Global shortcuts:
  - `/` focus search
  - `e` edit current page (view page only)
  - `n` new page
- Editor shortcuts:
  - `Cmd/Ctrl+B` bold
  - `Cmd/Ctrl+I` italic
  - `Cmd/Ctrl+K` wiki link wrapper
  - `Tab` indent selection/line by 2 spaces
  - `Shift+Tab` outdent selection/line by 2 spaces
  - `Cmd/Ctrl+S` save
  - `Cmd/Ctrl+Enter` save
- Editor convenience:
  - On desktop input environments, typing `[[` in the editor shows page-title autocomplete from `/api/all`.
  - `Esc` closes only the autocomplete panel (does not cancel edit or navigate away).
  - Editor cancel/leave is explicit button-driven, not keyboard-driven.
- Editor textarea keeps a fixed minimum height and scrolls internally (`overflow-y: auto`, `resize: none`).
- Unsaved-change protection with `beforeunload` while editor is dirty.
- Tab UI (`.tabs`, `.tab`, `.tab-content`) with query-string sync.
- Context menu uses cloned `.header-nav .dropdown-content` and must respect viewport edges.

## 13) Naming and Style

- Functions: `snake_case`
- Local variables / template view keys: `camelCase`
- Constants: `UPPER_SNAKE_CASE`
- i18n keys: dotted namespaces with lowercase snake leaf names

When adding/changing an i18n key:

- Update both `wikiwikiwiki/i18n/ko.json` and `wikiwikiwiki/i18n/en.json` together.
- Update all `t('...')` call sites together.

## 14) Security Boundary for Web Server

Apache/Nginx configs must keep these constraints:

- Only `index.php` executable entry for app routing.
- On Apache, if a directory `index.html` exists (root or subdirectory install), it must be served before `index.php` and rewrite processing must terminate (no fall-through to catch-all `index.php` rewrite).
- Deny direct access to runtime/private dirs:
  - `users/`, `history/`, `content/`, `cache/`
- Deny direct access to `wikiwikiwiki/*` except `wikiwikiwiki/assets/*`.
- Deny direct access to `themes/*/*` except `themes/*/assets/*`.
- Allow static files only under engine/theme assets paths.

## 15) Intent -> File Map (Open These First)

If the requested change is about X, inspect Y first:

- Route/API behavior:
  - `wikiwikiwiki/src/router.php`
  - `wikiwikiwiki/src/handlers/api.php`
  - `wikiwikiwiki/src/handlers/feed.php`
  - `wikiwikiwiki/src/helpers/web.php`
- Auth/login/register/account:
  - `wikiwikiwiki/src/auth.php`
  - `wikiwikiwiki/src/handlers/auth.php`
  - `wikiwikiwiki/src/user.php`
  - `wikiwikiwiki/templates/login.php`
  - `wikiwikiwiki/templates/account.php`
- Page save/delete/rename/history/index:
  - `wikiwikiwiki/src/page.php`
  - `wikiwikiwiki/src/page/crud.php`
  - `wikiwikiwiki/src/page/discover.php`
  - `wikiwikiwiki/src/page/history.php`
  - `wikiwikiwiki/src/page/index.php`
  - `wikiwikiwiki/src/page/search.php`
  - `wikiwikiwiki/src/page/related.php`
  - `wikiwikiwiki/src/handlers/discover.php`
- Parser/render/syntax:
  - `wikiwikiwiki/src/render/parser.php`
  - `wikiwikiwiki/src/render/format.php`
  - `wikiwikiwiki/src/render/summary.php`
  - `wikiwikiwiki/src/render/embeds.php`
- Settings/system tab and config:
  - `wikiwikiwiki/src/handlers/settings.php`
  - `wikiwikiwiki/src/helpers/export.php`
  - `wikiwikiwiki/templates/settings.php`
  - `config/config.php`
- Theme/static/template overrides:
  - `wikiwikiwiki/src/helpers.php` (`asset()`, `template_file()`)
  - `wikiwikiwiki/templates/base.php`
  - `wikiwikiwiki/assets/css/style.css`
  - `wikiwikiwiki/assets/js/script.js`
  - `themes/<theme>/...`
- Text copy / localization:
  - `wikiwikiwiki/i18n/ko.json`
  - `wikiwikiwiki/i18n/en.json`
  - all `t('...')` call sites

## 16) Validation Commands (runtime snapshot)

- `php -l index.php`
- `php -l wikiwikiwiki/src/bootstrap.php`
- `php -l wikiwikiwiki/src/router.php`
- `php -l wikiwikiwiki/src/handlers/settings.php`
- `test -d content && test -d history && test -d users && test -d cache`
- Boot smoke:
  - `php -r '$_SERVER["REQUEST_URI"]="/"; $_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["SCRIPT_NAME"]="/index.php"; $_SERVER["HTTP_HOST"]="localhost"; require "index.php";'`

## 17) Change-Type Verification Matrix

Run these checks based on what changed:

| Change type | Required checks |
| --- | --- |
| Route/API (`router.php`, `handlers/api.php`, `handlers/feed.php`) | `php -l wikiwikiwiki/src/router.php` ; `php -l wikiwikiwiki/src/handlers/api.php` ; `php -l wikiwikiwiki/src/handlers/feed.php` ; boot smoke from section 16 ; feed smoke (`/rss.xml`, `/atom.xml`, `/feed.json`) |
| Auth/settings (`auth.php`, auth/settings handlers/templates) | `php -l wikiwikiwiki/src/auth.php` ; `php -l wikiwikiwiki/src/user.php` ; `php -l wikiwikiwiki/src/handlers/auth.php` ; `php -l wikiwikiwiki/src/handlers/settings.php` ; verify `public` and `fully_public` modes block `create_user` (UI hidden + server guard) ; verify system tab exposes session-path/session-file rows ; verify `cleanup_expired_sessions` removes only expired `cache/sessions/sess_*` files |
| Export/backup (`src/helpers/export.php`, settings documents tab) | `php -l wikiwikiwiki/src/helpers/export.php` ; `php -l wikiwikiwiki/src/handlers/settings.php` ; export smoke (ZIP download succeeds and includes expected directories such as `content/` and `history/`) |
| Parser/render (`src/render/*`) | `php -l wikiwikiwiki/src/render/parser.php` ; `php -l wikiwikiwiki/src/render/format.php` ; `php -l wikiwikiwiki/src/render/summary.php` ; `php -l wikiwikiwiki/src/render/embeds.php` ; verify ParsedownExtra-specific rendering (heading attributes, footnotes, markdown table wrapper, bare URL autolink) ; verify markdown reference labels (`[label][ref]`) do not parse wiki/tag/transclusion syntax inside labels |
| Page write/index (`src/page/*`) | `php -l wikiwikiwiki/src/page.php` ; `php -l wikiwikiwiki/src/page/crud.php` ; `php -l wikiwikiwiki/src/page/discover.php` ; `php -l wikiwikiwiki/src/page/index.php` ; `php -l wikiwikiwiki/src/page/search.php` ; `php -l wikiwikiwiki/src/page/related.php` |
| Theme/template/frontend (`templates/*`, `assets/css/style.css`, `assets/js/script.js`) | `php -l wikiwikiwiki/templates/base.php` ; `php -l wikiwikiwiki/templates/settings.php` ; boot smoke from section 16 ; `./wikiwikiwiki/vendor/bin/phpunit -c phpunit.xml tests/RenderTest.php tests/OperationalScenariosTest.php --colors=never` ; if print CSS changed, verify print keeps `.section-header` and hides `.section-footer` and `.field.is-sticky` |
| Web server/rewrite (`.htaccess`, `nginx.example.conf`, `index.php`) | verify root and subdirectory install behavior for `GET /` (or subdir root) prefers `index.html` when present ; verify `GET /index.html` is not rewritten to `index.php` ; verify wiki routes still resolve via `index.php` (`/wiki/{title}`, `/rss.xml`) |
| i18n only | JSON validity and key parity for `ko.json`/`en.json`/`ja.json` plus one representative page render |

Minimum floor for any code change: run `php -l index.php`, `php -l wikiwikiwiki/src/bootstrap.php`, and boot smoke from section 16.

## 18) Known Pitfalls (Avoid Regressions)

- Route order is behavior: specific patterns must stay above broader wiki patterns.
- Any browser POST mutation without `validate_post_request()` is a security regression.
- Auth entrypoints that handle credentials (for example login/register/install) must include `rate_limit_check_and_record()` to prevent brute-force exposure.
- Keep history-authority split strict: editor can delete page content, but history restore/purge must remain admin-only.
- File writes outside `wiki_with_lock(...)` + `file_put_atomic(...)` are invalid.
- Changing i18n keys in one language only will break copy consistency.
- `preg_replace` / `preg_replace_callback` can return `null`; always coalesce (`$result = preg_replace(...) ?? $original`).
- Parser changes must preserve transclusion depth/cycle safeguards.
- Theme overrides must not bypass fallback behavior when theme files are missing.
- Overriding `templates/base.php` increases upgrade maintenance: re-check engine `wikiwikiwiki/templates/base.php` changes when updating.
- Web server config must continue blocking internal/runtime directories from direct access.
- Do not publish a `wikiwikiwiki/`-only update instruction when release changes root web files (`.htaccess`, `index.php`, `nginx.example.conf`).
- On `page.rename_history_move_failed`, recover by moving remaining `history/<old-base>.*.txt` files to the new base (using `page_history_base(...)` naming), then refresh index bundle/caches.

## 19) Done Definition

A task is done only when all conditions below are true:

- Scope is minimal and directly tied to the request.
- Touched PHP files pass `php -l`.
- Required checks from section 17 were actually run (or explicitly reported as not run).
- `ko` and `en` i18n remain consistent for changed keys.
- No invariant in sections 4-10 is violated.
- User-facing behavior changes are described in plain terms.
- Residual risks or follow-up work are explicitly listed (or state none).

## 20) Agent Output Contract

When reporting completion, always include:

- Changed files (explicit paths).
- What changed in behavior (not only implementation detail).
- Validation commands executed and the result of each.
- Anything not validated and why.
- Remaining risks/assumptions (or explicit `none`).

Do not claim verification for checks that were not executed.

## 21) Explicit Non-Goals

- No database migration.
- No heavy frontend framework introduction.
- No bypass of lock, CSRF, or permission checks.
- No runtime pollution with test/dev leftovers.
- No large refactor without operational justification.

Keep the system understandable by one operator and one pass of reading.

Note: This AGENTS.md was not authored by [Min Guhong](https://minguhong.fyi), the creator of WikiWikiWiki.
