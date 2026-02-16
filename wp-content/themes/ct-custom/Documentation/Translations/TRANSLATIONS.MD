# CT Custom — Multilanguage Translation System (Technical Reference)

> Internal reference for Claude Code and developers maintaining the translation system.

---

## System Overview

The CT Custom theme includes a custom-built multilanguage system that supports:

- **String translations** via JSON files with CLDR plural rules
- **Page duplication** per language with parent-child hierarchy preservation
- **Menu/widget cloning** per language with proper remapping
- **Admin UIs** for managing languages, editing translations, and filtering content
- **Frontend resolution** of `ct_translate()` patterns in templates, Customizer values, block editor content, and REST API responses
- **SEO hreflang** tags for translation groups

No external translation plugins are used. The system is self-contained within the theme.

---

## File Map

### PHP Backend — Core (`src/Multilang/`)

| File | Class | Purpose |
|------|-------|---------|
| `Translator.php` | `Translator` | Loads JSON translation files, resolves keys with CLDR plurals, placeholder replacement, HTML escaping. Supports locale-over-language merge. |
| `CldrPluralRules.php` | `CldrPluralRules` | Static resolver mapping language codes to CLDR plural families (germanic, french, east_slavic, west_slavic, polish, arabic, no_plural). Supports custom rule registration. |
| `LanguageManager.php` | `LanguageManager` | CRUD operations for `languages.json` registry. File-locking for concurrent writes. Manages enabled/default/locales per language. |
| `TranslationService.php` | `TranslationService` | Static utility resolving `ct_translate()` patterns in customizer and block content. Three-phase block resolution (collect, translate deduplicated, replace). |
| `MultilangService.php` | `MultilangService` | Singleton entry point consuming `MultilangHelpers` trait. Single access point for all multilang operations. |
| `MultilangHelpers.php` | `MultilangHelpers` (trait) | Core logic: `get_current_language()`, `get_translator()`, `translate()`. Caches only after `wp` action to prevent early poisoning. |
| `LanguagePageManager.php` | `LanguagePageManager` | Page duplication for new languages, translation group linking (UUID-based), menu/widget cloning, one-time migrations. |
| `HreflangService.php` | `HreflangService` | Outputs `<link rel="alternate" hreflang>` tags in `wp_head` for pages in translation groups. |

### PHP Backend — Supporting

| File | Class | Purpose |
|------|-------|---------|
| `src/Theme/ThemeSettings.php` | `ThemeSettings` | AJAX handlers for language management, translation editing, page management (Tabs A/B/C in admin). Export/import includes translations. |
| `src/Customizer/Controls/TranslationControl.php` | `TranslationControl` | Custom WP Customizer control (`ct_translation` type) with "Pick Key" button and searchable dropdown. |
| `src/Customizer/CustomizerSetup.php` | `CustomizerSetup` | Registers translation-aware controls for site description and footer copyright. Sets up selective refresh partials. |
| `src/RestApi/Endpoints/GetTranslations.php` | `GetTranslations` | `GET /wp-json/ct-custom/v1/translations?iso2=xx` — Returns full translation dictionary. |
| `src/RestApi/Endpoints/ResolveTranslation.php` | `ResolveTranslation` | `POST /wp-json/ct-custom/v1/resolve-translation` — Resolves `ct_translate()` patterns in text. Max 2000 chars. |
| `src/Admin/PageLanguageFilter.php` | `PageLanguageFilter` | Adds language dropdown filter to Pages list table with per-language page counts. |
| `src/Admin/MenuLanguageFilter.php` | `MenuLanguageFilter` | Language tabs on nav-menus.php. Filters location rows and strips language suffixes. |
| `src/Widgets/WidgetLanguageFilter.php` | `WidgetLanguageFilter` | Enqueues JS/CSS for widget admin, builds language data for JavaScript. |
| `src/Template/Language.php` | `Language` | Singleton for language switcher data, page translation lookups, menu location resolution. |

### JavaScript Frontend

| File | Purpose |
|------|---------|
| `inc/blocks/shared/editor-translator.js` | Client-side translation resolver for block editor live preview. Mirrors PHP `Translator` logic. Exposes `window.ctEditorTranslator.resolve()` and `findPatterns()`. |
| `assets/customizer/vite/src/controls/customizer-translation.js` | Customizer control handler. Searchable dropdown for translation keys, live preview with `ctEditorTranslator.resolve()`. |
| `assets/widgets/js/widget-translation-picker.js` | jQuery-based "Pick Key" button for widget text inputs. Searchable dropdown, preview element, event delegation for dynamic forms. |
| `assets/backend/vite/src/js/classes/Admin_Languages.js` | Admin class with three sections: Language CRUD, Translation Editor (singular/plural/export/import), Page Translations (filter/quick-edit). |

### Templates

| File | Purpose |
|------|---------|
| `template-admin/partials/languages.php` | Three-tab admin partial: Language Management, Translation Editor, Page Translations. CSS-only tabs with radio inputs. |

### Data Files

| File | Purpose |
|------|---------|
| `translations/languages.json` | Language registry array. Each entry: `id`, `iso2`, `iso3`, `native_name`, `flag`, `locales[]`, `enabled`, `is_default`. |
| `translations/{iso2}.json` | Translation dictionary per language. Keys are `UPPER_SNAKE_CASE`. Values are strings or plural objects. |

---

## Pattern Syntax

### Basic Pattern

```
ct_translate('KEY')
```

Resolves `KEY` from the current language's JSON file.

### With Placeholder Arguments

PHP arrow syntax:
```
ct_translate('ITEM_COUNT', ['count' => '5'], 'other')
```

JSON colon syntax:
```
ct_translate('ITEM_COUNT', {"count": "5"}, 'other')
```

### With Plural Form

Named form (string):
```
ct_translate('ITEM_COUNT', ['count' => '1'], 'one')
```

Numeric count (CLDR resolution):
```
ct_translate('ITEM_COUNT', ['count' => '5'], 5)
```

### Valid Form Names

`singular`, `zero`, `one`, `two`, `few`, `many`, `other`

### Key Format

Keys must match: `[A-Z][A-Z0-9_]*` (uppercase, starts with letter, underscores allowed).

---

## Data Structures

### `languages.json`

```json
[
    {
        "id": "lang_en",
        "iso2": "en",
        "iso3": "eng",
        "native_name": "English",
        "flag": "https://example.com/flag.svg",
        "locales": ["en_US", "en_GB"],
        "enabled": true,
        "is_default": true
    }
]
```

### Translation File (`{iso2}.json`)

Simple string:
```json
{
    "SITE_NAME": "CT Custom",
    "LOGIN": "Login"
}
```

Plural object:
```json
{
    "ITEM_COUNT": {
        "singular": "Count items",
        "one": "##count## item",
        "other": "##count## items"
    }
}
```

Placeholder syntax: `##placeholder_name##`

---

## CLDR Plural Rules

### Family Map

| Family | Languages | Categories |
|--------|-----------|------------|
| Germanic | en, de, nl, sv, nb, nn, da, no, it, es, pt, el, bg, he, hu, fi, et, ca, gl | 1=one, else=other |
| French | fr, hi, fa | 0-1=one, else=other |
| East Slavic | sr, ru, uk, be, hr, bs | mod10/mod100 rules for one/few/other |
| West Slavic | cs, sk | 1=one, 2-4=few, else=other |
| Polish | pl | 1=one, mod10 2-4 (not 12-14)=few, else=many |
| Arabic | ar | 0=zero, 1=one, 2=two, 3-10=few, 11-99=many, 100+=other |
| No-plural | ja, zh, ko, tr, vi, th, id, ms | Always other |

Unknown languages default to Germanic rules.

### Custom Rules

```php
CldrPluralRules::register_rule('xx', function(int $count): string {
    return ($count === 1) ? 'one' : 'other';
});
```

Maximum 50 custom rules. `reset_custom_rules()` clears them.

---

## Post Meta

| Meta Key | Purpose |
|----------|---------|
| `ct_language` | Two-letter language code assigned to the page |
| `ct_locale` | Optional locale code (e.g., `en_GB`) |
| `ct_translation_group` | UUID linking all translations of the same page |

---

## Resolution Flow

### PHP Resolution Pipeline

1. **Template/Customizer** calls `MultilangService::translate('KEY', $args, $count)`
2. `MultilangHelpers::get_translator()` creates `Translator($iso2, $locale)`
3. `Translator` loads `{iso2}.json`, optionally merges `{locale}.json`
4. `resolve_key()` handles plural form selection via `CldrPluralRules::resolve()`
5. `replace_placeholders()` substitutes `##name##` patterns
6. `escape_html()` applies HTML escaping (unless `_raw` variant used)

### Block Content Resolution

1. `TranslationService::resolve_block_content($content, $iso2)`
2. Phase 1: Regex collects all `ct_translate()` patterns
3. Phase 2: Deduplicated patterns resolved via `Translator::translate()`
4. Phase 3: Single `str_replace()` pass substitutes all patterns

### JavaScript Resolution

1. `window.ctEditorTranslator.resolve(text)` mirrors PHP logic
2. Parses patterns, resolves from injected dictionary (`ctEditorTranslatorData.translations`)
3. Applies CLDR plural rules (client-side implementation)
4. Used by: block editor live preview, Customizer control preview, widget picker preview

---

## Admin Interfaces

### Theme Settings — Languages Tab (Tab A)

- Add language form: iso2, iso3, native name, flag (media picker)
- Language table: edit, delete, enable/disable, set default
- Delete options: force delete pages, remove menus, remove widgets

### Theme Settings — Translations Tab (Tab B)

- Sidebar: searchable key list, add key button
- Editor: singular input, toggle to plural mode (shows all CLDR form fields)
- Export/import: JSON download/upload per language
- Debounced auto-save with visual feedback

### Theme Settings — Page Translations Tab (Tab C)

- Language filter dropdown
- Page list with quick-edit (title, slug, parent)
- Live update without page reload

### Customizer

- `TranslationControl` extends `WP_Customize_Control`
- Text/textarea input with "Pick Key" button
- Live preview resolution via `ctEditorTranslator.resolve()`

### Widget Admin

- "Pick Key" button on widget text inputs
- Searchable dropdown (max 200 visible keys)
- Yellow preview element showing resolved text

### Block Editor

- `editor-translator.js` loaded as shared block asset
- Live preview resolution in editor iframe

---

## REST API

### GET `/wp-json/ct-custom/v1/translations`

**Parameters:** `iso2` (required) — Two-letter language code

**Response:**
```json
{
    "success": true,
    "data": {
        "iso2": "en",
        "translations": { "SITE_NAME": "CT Custom", ... }
    }
}
```

### POST `/wp-json/ct-custom/v1/resolve-translation`

**Parameters:** `text` (required, max 2000 chars), `iso2` (optional)

**Response:**
```json
{
    "success": true,
    "data": {
        "resolved": "CT Custom"
    }
}
```

---

## Constants & Limits

| Constant | Value | Location |
|----------|-------|----------|
| `Translator::MAX_ARGS` | 50 | Max placeholder arguments |
| `Translator::MAX_PATTERN_MATCHES` | 200 | Max ct_translate() patterns per resolve |
| `Translator::MAX_PLACEHOLDER_ITER` | 100 | Max iterations for stripping unresolved placeholders |
| `CldrPluralRules::MAX_CUSTOM_RULES` | 50 | Max custom plural rules |
| `LanguageManager::MAX_LANGUAGES` | 50 | Max languages in registry |
| `LanguageManager::MAX_LOCALES_PER` | 20 | Max locales per language |
| `LanguagePageManager::MAX_PAGES_TO_DUPLICATE` | 500 | Max pages per duplication operation |
| `LanguagePageManager::MAX_MENU_ITEMS` | 200 | Max menu items per menu during duplication |
| `LanguagePageManager::MAX_WIDGET_TYPES` | 100 | Max widget types during cloning |
| `HreflangService::MAX_ALTERNATES` | 20 | Max hreflang links per page |
| `ResolveTranslation::MAX_LENGTH` | 2000 | Max text length for resolve endpoint |
