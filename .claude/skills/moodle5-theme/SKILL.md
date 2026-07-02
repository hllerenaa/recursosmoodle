---
name: moodle5-theme
description: |
  Create and customize Moodle 5.1+ themes based on Boost (Bootstrap 5.3). Use this skill whenever the user wants to: create a Boost child theme from scratch, modify SCSS styles of an existing theme, override Mustache templates, add or modify admin settings, customize renderers, override icons or fonts, migrate from Bootstrap 4 to 5, debug cache or SCSS compilation issues, create output components, or any task related to Moodle 5.x visual appearance. Also trigger when mentioning "theme", "Boost", "Moodle SCSS", "Moodle Mustache template", "theme settings", "customize Moodle", "change Moodle styles", or similar terms.
metadata:
  author: nicolasflores9
  version: 1.0.0
---

# Moodle 5 Theme Development

Skill for creating and modifying Moodle 5.1.3+ themes based on Boost (Bootstrap 5.3).

## Quick Context

Moodle 5.1+ uses Bootstrap 5.3, themes reside under `public/theme/`, and layout inheritance from the parent theme is automatic. The SCSS pipeline has three phases: pre-SCSS → main (preset) → extra-SCSS. Templates use Mustache with Moodle-specific helpers (`{{#str}}`, `{{#pix}}`, `{{#js}}`).

## Workflow

### 1. Understand What the User Needs

Before generating code, determine:

- **New or existing theme?** If new, generate the complete scaffolding. If existing, ask for the theme name (Frankenstyle component `theme_name`).
- **What do they want to change?** Styles/colors (→ SCSS), page structure (→ templates/layouts), admin options (→ settings), rendering behavior (→ renderers), icons/images (→ pix_core/pix_plugins).
- **Theme name**: The Frankenstyle identifier (`theme_mytheme`) that will be used across all files.

### 2. Consult the Appropriate Reference

Based on the type of change, read the corresponding reference file before generating code:

| Task | Reference file |
|---|---|
| Create new theme (scaffolding) | `references/structure.md` |
| Modify styles, colors, SCSS | `references/scss-pipeline.md` |
| Override Mustache templates | `references/templates.md` |
| Add/modify admin settings | `references/settings.md` |
| Customize renderers or output | `references/renderers.md` |
| Migrate from Bootstrap 4 to 5 | `references/bootstrap5-migration.md` |
| Cache issues or debugging | `references/debugging.md` |

Read **only** the files relevant to the current task. For cross-cutting tasks (e.g., adding a color setting that affects SCSS), read both files.

### 3. Generate Functional Code

When generating files, follow these rules:

**File structure**: All themes reside under `public/theme/<name>/`. Always generate the full path so the user knows exactly where to place each file.

**Required files** (for new themes):
- `version.php` — Plugin metadata
- `config.php` — Theme configuration (inheritance, layouts, SCSS callbacks)
- `lang/en/theme_<name>.php` — Minimum language strings
- `lib.php` — SCSS callbacks and helper functions

**Code conventions**:
- Every PHP file starts with `<?php` followed by `defined('MOODLE_INTERNAL') || die();` (except layout files that open with `<?php require_once...`)
- Use namespace `theme_<name>\output` for renderer classes
- Language strings always go in lang files, never hardcoded
- SCSS variables use `!default` in presets; without `!default` in pre-SCSS to force override
- Mustache templates include the GPL license boilerplate and `@template` annotation

**SCSS — Compilation order**:
The order is critical and all generated SCSS must respect the three phases:
1. `prescsscallback` — Variables that override Bootstrap `!default`
2. `$THEME->scss` — Main preset with `@import "moodle"`
3. `extrascsscallback` — Final rules with highest cascade priority

**Mustache templates — Overriding**:
To override a core or plugin template, the path in the theme must be:
`theme/<name>/templates/<component>/<template>.mustache`

Where `<component>` is the Frankenstyle name: `core`, `mod_quiz`, `block_myoverview`, etc.

**Settings — Cache invalidation**:
Every setting that affects CSS/SCSS **must** include:
```php
$setting->set_updatedcallback('theme_reset_all_caches');
```

### 4. Bootstrap 5.3 — Modern Code

All generated code must use Bootstrap 5.3 syntax:
- Logical classes: `.ms-*`, `.me-*`, `.ps-*`, `.pe-*`, `.text-start`, `.text-end`
- Data attributes with `bs` prefix: `data-bs-toggle`, `data-bs-target`
- No jQuery: use direct Bootstrap module imports
- `.visually-hidden` instead of `.sr-only`

Do not generate code with Bootstrap 4 syntax unless the user explicitly asks for backward compatibility.

### 5. Delivery

Generate complete, functional files with:
- Exact path where they should be placed in the Moodle installation
- Inline comments explaining non-obvious parts
- Post-installation instructions if applicable (purge cache, increment version)

When modifying SCSS or templates, remind the user to purge caches:
```bash
php admin/cli/purge_caches.php --theme
# Or more specifically:
php admin/cli/build_theme_css.php --themes=<name> --verbose
```

## Interaction Example

**User**: "I want to add a custom color field in my theme settings that changes the header color"

**Flow**:
1. Read `references/settings.md` and `references/scss-pipeline.md`
2. Generate the `admin_setting_configcolourpicker` setting in `settings.php`
3. Add the language string in `lang/en/theme_<name>.php`
4. Map the setting to an SCSS variable in `get_pre_scss()` in `lib.php`
5. Create SCSS rule in `scss/post.scss` using the variable for headers
6. Include `set_updatedcallback('theme_reset_all_caches')`
