# BS Custom — Translation System (Developer Guide)

> How to use the multilanguage system in the BS Custom theme.

---

## Quick Start

### 1. Add a Language

Navigate to **Appearance > Theme Settings > Languages** tab:

1. Fill in the "Add Language" form: ISO 639-1 code (e.g., `fr`), ISO 639-2 code (e.g., `fra`), native name (e.g., `Francais`)
2. Click the flag area to pick a flag image from the media library
3. Click **Add Language**
4. The language appears in the table. Toggle **Enabled** to activate it
5. Pages, menus, and widgets are automatically duplicated from the default language

### 2. Add Translation Keys

Switch to the **Translations** tab:

1. Click **Add Key** in the sidebar
2. Enter a key name in `UPPER_SNAKE_CASE` (e.g., `WELCOME_MSG`)
3. Type the translation value in the editor area
4. Switch between languages using the language dropdown
5. Changes auto-save with debounced saves

### 3. Use Translations in Content

In the Block Editor, type:
```
bs_translate('WELCOME_MSG')
```

The pattern is resolved to the translated text on the frontend.

---

## Translation Pattern Syntax

### Simple Key

```
bs_translate('KEY_NAME')
```

Looks up `KEY_NAME` in the current language's JSON file and returns the value.

### With Placeholders

Define a translation with placeholders:
```json
{
    "GREETING": "Hello, ##name##!"
}
```

Use with arguments (PHP arrow syntax):
```
bs_translate('GREETING', ['name' => 'Alice'])
```

Or JSON colon syntax:
```
bs_translate('GREETING', {"name": "Alice"})
```

Result: `Hello, Alice!`

### With Plural Forms

Define plural variants in the JSON file:
```json
{
    "ITEM_COUNT": {
        "singular": "Items",
        "one": "##count## item",
        "other": "##count## items"
    }
}
```

Use with a named form:
```
bs_translate('ITEM_COUNT', ['count' => '1'], 'one')
```
Result: `1 item`

Use with a numeric count (auto-resolves via CLDR rules):
```
bs_translate('ITEM_COUNT', ['count' => '5'], 5)
```
Result: `5 items`

Without a count, the `singular` value is used as fallback:
```
bs_translate('ITEM_COUNT')
```
Result: `Items`

---

## Using Translations

### In PHP Templates

```php
// HTML-escaped output (safe for templates)
echo bs_translate('SITE_NAME');

// Via the MultilangService singleton
$service = BSCustom\Multilang\MultilangService::instance();
echo $service->translate('SITE_NAME');

// With echo shorthand
$service->translate_echo('GREETING', ['name' => $user_name]);
```

### In the Block Editor

Type the pattern directly in any text block:
```
bs_translate('CONTACT_US')
```

The pattern is resolved when the page renders on the frontend.

### In the Customizer

Translation-aware controls show a "Pick Key" button. Click it to search and select a translation key. The value is stored as `bs_translate('KEY')` and resolved on the frontend.

### In Widgets

Widget text inputs with translation support show a "Pick Key" button. Select a key and the input value becomes `bs_translate('KEY')`. A yellow preview shows the resolved text.

### In REST API Calls

Fetch the full dictionary:
```
GET /wp-json/ct-custom/v1/translations?iso2=en
```

Resolve a pattern:
```
POST /wp-json/ct-custom/v1/resolve-translation
Body: { "text": "bs_translate('SITE_NAME')", "iso2": "en" }
```

---

## Plural Forms

### CLDR Plural Categories

The system supports six CLDR plural categories:

| Category | Description | Example Languages |
|----------|-------------|-------------------|
| `zero` | Zero items | Arabic |
| `one` | Singular | English (1), French (0-1) |
| `two` | Dual | Arabic (2) |
| `few` | Small plural | Serbian (2-4), Czech (2-4), Polish (2-4) |
| `many` | Large plural | Polish (5+), Arabic (11-99) |
| `other` | General plural | All languages |

### Language Families

**English/Germanic** (en, de, nl, sv, etc.): 1 = one, everything else = other

**French** (fr, hi, fa): 0 and 1 = one, everything else = other

**Serbian/East Slavic** (sr, ru, uk, hr, etc.):
- mod10=1, mod100!=11 = one
- mod10 in 2-4, mod100 not in 12-14 = few
- everything else = other

**Czech/West Slavic** (cs, sk): 1 = one, 2-4 = few, else = other

**Polish** (pl): 1 = one, mod10 2-4 (not 12-14) = few, else = many

**Arabic** (ar): 0=zero, 1=one, 2=two, 3-10=few, 11-99=many, 100+=other

**Japanese/Chinese/etc.** (ja, zh, ko, tr, vi, th, id, ms): Always other (no plural distinction)

### Defining Plural Translations

In the JSON file, use an object instead of a string:

```json
{
    "COMMENT_COUNT": {
        "singular": "Comments",
        "zero": "No comments",
        "one": "##count## comment",
        "two": "##count## comments",
        "few": "##count## comments",
        "many": "##count## comments",
        "other": "##count## comments"
    }
}
```

You only need to define the categories relevant to your language. Missing categories fall back to `other`, then `singular`, then the key name.

---

## Placeholders

### Syntax

Use `##name##` in translation values:

```json
{
    "WELCOME": "Welcome back, ##user##!",
    "ITEMS_IN_CART": {
        "one": "##count## item in ##user##'s cart",
        "other": "##count## items in ##user##'s cart"
    }
}
```

### Rules

- Placeholder names: letters, numbers, underscores (`[a-zA-Z0-9_]`)
- Unresolved placeholders are silently stripped from the output
- Maximum 50 placeholder arguments per call

---

## Adding a New Language

### Step-by-Step

1. Go to **Appearance > Theme Settings > Languages** tab
2. Fill the "Add Language" form:
   - **ISO 639-1** (2 letters): e.g., `de`
   - **ISO 639-2** (3 letters): e.g., `deu`
   - **Native name**: e.g., `Deutsch`
3. Click the flag placeholder to select a flag image
4. Click **Add Language**
5. The system automatically:
   - Creates a `translations/de.json` file (if not existing)
   - Duplicates all pages from the default language
   - Creates language-specific menus
   - Clones widget areas
6. Toggle **Enabled** to make the language active on the frontend

### Setting a Default Language

Click **Set Default** next to a language in the table. The default language:
- Is the fallback when no language is detected
- Provides the source pages for duplication
- Gets the `x-default` hreflang tag

### Managing Locales

Each language can have multiple locales (e.g., English: `en_US`, `en_GB`). Locales allow region-specific translation overrides via separate JSON files.

---

## Translation Files

### Location

```
wp-content/themes/ct-custom/translations/
    languages.json     # Language registry
    en.json            # English translations
    sr.json            # Serbian translations
    en_GB.json         # English (UK) locale override (optional)
```

### JSON Structure

**Simple strings:**
```json
{
    "KEY_NAME": "Translated value"
}
```

**Plural objects:**
```json
{
    "KEY_NAME": {
        "singular": "Default form",
        "one": "Singular form",
        "other": "Plural form"
    }
}
```

### Key Naming Convention

- All uppercase with underscores: `SITE_NAME`, `ITEM_COUNT`, `CONTACT_US`
- Must start with a letter: `[A-Z][A-Z0-9_]*`
- Descriptive and unique across the project

### Export/Import

In the Translations tab:
- **Export**: Downloads the current language's JSON file
- **Import**: Uploads a JSON file to replace/merge translations

---

## Admin Interfaces

### Theme Settings > Languages (Tab A)

Manage languages: add, edit (native name, iso3, flag), delete, enable/disable, set default. Deleting a language offers options to also remove its pages, menus, and widgets.

### Theme Settings > Translations (Tab B)

Edit translation keys and values per language. Search keys in the sidebar. Toggle between singular and plural modes. Add/delete keys. Export/import JSON files.

### Theme Settings > Page Translations (Tab C)

View and quick-edit pages per language. Filter by language dropdown. Edit title, slug, and parent inline.

### Customizer

Controls registered with `bs_translation` type show a translation key picker. Used for site description and footer copyright.

### Widget Admin (widgets.php)

Text inputs enhanced with "Pick Key" buttons. Works with both classic widget admin and Customizer widget panels.

### Block Editor

The `editor-translator.js` script enables live preview of `bs_translate()` patterns in the block editor. No special UI needed; just type the pattern.

---

## REST API

### Get Translation Dictionary

```
GET /wp-json/ct-custom/v1/translations?iso2=en
```

Returns the complete translation dictionary for a language. Public endpoint, no authentication required.

### Resolve Translation Pattern

```
POST /wp-json/ct-custom/v1/resolve-translation
Content-Type: application/json

{
    "text": "bs_translate('SITE_NAME')",
    "iso2": "en"
}
```

Resolves `bs_translate()` patterns in the text. The `iso2` parameter is optional (defaults to current language). Maximum text length: 2000 characters.

---

## Extending — Custom Plural Rules

Register a custom plural rule for a language not in the built-in family map:

```php
use BSCustom\Multilang\CldrPluralRules;

CldrPluralRules::register_rule('my_lang', function(int $count): string {
    if ($count === 0) return 'zero';
    if ($count === 1) return 'one';
    if ($count >= 2 && $count <= 10) return 'few';
    return 'other';
});
```

- Maximum 50 custom rules
- Custom rules take priority over built-in family rules
- Use `CldrPluralRules::reset_custom_rules()` to clear all custom rules (useful in tests)

---

## Post Meta Reference

| Meta Key | Type | Description |
|----------|------|-------------|
| `bs_language` | string | Two-letter ISO 639-1 language code (e.g., `en`, `sr`) |
| `bs_locale` | string | Optional locale code for region-specific content (e.g., `en_GB`) |
| `bs_translation_group` | string | UUID linking all translations of the same source page |

These meta keys are automatically set when pages are duplicated for a new language.
