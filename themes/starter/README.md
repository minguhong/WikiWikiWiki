[한국어](README.ko.md)

# Theme Guide

Themes work by overriding `assets/`, `templates/`, and `i18n/` inside `wikiwikiwiki/`. If a theme folder contains a file with the same name, the theme file takes priority. The folder name becomes the theme name. Theme names may only contain letters, numbers, `-`, and `_`.

---

## Getting started

The quickest start is to edit only `assets/css/style.css`. Go to **Settings → Basic → Theme** and select `starter`.

---

## Step 1: CSS

```text
starter/
└── assets/
    └── css/
        └── style.css
```

If `assets/css/style.css` exists, WikiWikiWiki’s default CSS is **completely replaced**. The class names used in WikiWikiWiki’s default templates are listed below.

**Layout**
- `.document`, `.header`, `.main`, `.footer`, `.section`, `.section-header`, `.section-main`, `.section-footer`

**Typography**
- `.title`, `.content`

**Links**
- `.exists`, `.not-exists`, `.external`, `.wikipedia`, `.tag`, `.iframe-link`

**Forms**
- `.form`, `.form.is-fill`, `.fields`, `.field`, `.field.is-fill`, `.field-help`, `.label`, `.input`, `.textarea`

**Buttons**
- `.buttons`, `.button`, `.button.is-primary`, `.button.is-danger`, `.button.is-link`

**Tabs** (account and settings pages)
- `.tabs`, `.tab`, `.tab-content`

**Diff** (edit conflict and history comparison)
- `.diff`, `.diff-sign`, `.diff-line`, `.diff-add`, `.diff-remove`, `.diff-context`

**Utilities**
- `.hidden`, `.skip`, `.flash`

**Other**
- `.table`

For page-specific styles, use the `<body>` `id` in `base.php`. It is set to the current template name (`view`, `edit`, `search`, `account`, `settings`, etc.).

```css
/* Larger body text on the document view page only */
body#view .content { font-size: 1.5rem; }
```

## Step 2: Copy

To change button labels or messages, add i18n files.

```text
starter/
└── i18n/
    ├── ko.json
    └── en.json
```

If the same key exists, the theme value overrides the WikiWikiWiki default.

```json
{
    "button.save": "Publish",
    "button.edit": "Edit this"
}
```

## Step 3: Layout

To design the header, main, footer, and other layout yourself, add `templates/base.php`. `base.php` is the base layout file that wraps every page. It is best to start by copying `wikiwikiwiki/templates/base.php`.

```text
starter/
└── templates/
    └── base.php
```

You can also place a file with the same name under `templates/` to override individual page templates — useful when you only want to change the structure of a specific page. Note that if you design the layout yourself, you may need to manually apply changes from `wikiwikiwiki/templates/base.php` when upgrading WikiWikiWiki.

```text
starter/
└── templates/
    ├── base.php       # base layout
    ├── view.php       # document view
    ├── edit.php       # edit
    ├── search.php     # search
    ├── discover.php   # discover
    ├── history.php    # edit history
    ├── account.php    # account
    ├── settings.php   # settings (admin)
    ├── 404.php        # document not found
    └── ...            # any other file with the same name as an engine template
```

### Functions available in templates

**Output / Translation**

| Function | Description |
|---|---|
| `html($str)` | Output a string safely as HTML |
| `t($key)` | Return translated string, returns the key itself if not found |

**URL / Assets**

| Function | Description |
|---|---|
| `url($path)` | Build a URL relative to WikiWikiWiki, pass a path like `/search` or a document title |
| `page_url($title)` | Return the full URL of a document |
| `asset($file)` | Return an asset URL, theme file takes priority if available |
| `css($file)` | Generate a CSS `<link>` tag string |
| `js($file)` | Generate a JS `<script>` tag string |

**Auth / Permissions**

| Function | Description |
|---|---|
| `is_logged_in()` | Whether the user is logged in |
| `is_admin()` | Whether the user is an admin |
| `is_public()` | Whether edit permission is set to public |
| `current_user()` | Current username, `null` if not logged in |
| `current_role()` | Current user role: `'admin'`, `'editor'`, or empty string |

**Forms / Security**

| Function | Description |
|---|---|
| `csrf_token()` | Return the CSRF token string |
| `honeypot_field()` | Return a spam-prevention hidden input HTML |

**Data**

| Function | Description |
|---|---|
| `page_recent($limit)` | Return recently edited documents, default 10 |
| `page_random($limit)` | Return random documents, default 10 |

### Variables available in templates

| Variable | Description |
|---|---|
| `$wikiTitle` | Wiki title |
| `$wikiDescription` | Wiki description |
| `$pageTitle` | Current page title |
| `$description` | Current page description, empty string if none |
| `$language` | Language code (e.g. `en`) |
| `$languageCode` | Locale code (e.g. `en_US`) |
| `$ogUrl` | Full URL of the current page |
| `$basePath` | Base URL path |
| `$csrfToken` | Form security token |
| `$flashes` | Array of alert messages, each item has `type` and `message` keys |
| `$recentPages` | Array of recently edited documents, default 5 |
| `$section` | Current page body HTML, `base.php` only |
| `$template` | Current template name, used as `<body id>`, `base.php` only |

## Step 4: JavaScript

If `assets/js/script.js` exists, WikiWikiWiki’s default JavaScript is **completely replaced**.

```text
starter/
└── assets/
    └── js/
        └── script.js
```

If you write your own JavaScript file, you must also implement the following.

### Tab switching

On the account and settings pages, only the first panel is visible — the rest start as `hidden`. To show all panels without JavaScript, override the `[hidden]` style as well.

```css
/* Show all tab panels without JavaScript */
.tab-content[hidden] { display: block; }
```

```javascript
// Minimal implementation
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

### Confirm before deleting or restoring a document

If buttons with `[data-confirm-message]` have no `confirm`, document deletions and restores execute immediately without confirmation.

```javascript
// Minimal implementation
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

## Changes not taking effect

- Check that the current theme is selected under **Settings → Basic → Theme**.
- Verify the file paths are under `themes/{theme-name}/assets/...`, `themes/{theme-name}/templates/...`, or `themes/{theme-name}/i18n/...`.
- Make sure `style.css` or `script.js` actually exists and is saved.
