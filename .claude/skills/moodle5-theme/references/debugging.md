# Debugging and cache

## Table of contents
1. [Cache system](#cache)
2. [Theme Designer Mode](#designer-mode)
3. [CLI commands](#cli)
4. [Development configuration](#dev-config)
5. [SCSS debugging](#debug-scss)
6. [Template debugging](#debug-templates)
7. [Performance monitoring](#performance)
8. [Common errors and solutions](#errors)

## Cache

| Type | Location | Impact |
|---|---|---|
| Compiled CSS/SCSS | `<moodledata>/localcache/theme/<themerev>/<theme>/css/` | SCSS changes invisible without purge |
| Mustache templates | `<moodledata>/localcache/mustache/<revision>/<theme>/` | Overrides not applied |
| Language strings | MUC (Moodle Universal Cache) | New strings do not appear |
| JavaScript | `theme/javascript.php` minified | JS changes not reflected |
| Images/Icons | Browser cache + `theme/image.php` | New icons not visible |

Invalidation works through revision numbers in asset URLs. `theme_reset_all_caches()` increments `themerev`, forcing the browser to download new files.

## Designer Mode

Prevents caching of CSS, templates, and images. Recompiles SCSS on every page load:

```php
// In Moodle's config.php — NEVER in production
$CFG->themedesignermode = true;
```

The `sassc` binary reduces compilation time by >50%:
```bash
# Install on Debian/Ubuntu
apt install sassc
# Configure at: Administration → Experimental → Path to SassC
```

## CLI

```bash
# Purge everything
php admin/cli/purge_caches.php

# Theme cache only
php admin/cli/purge_caches.php --theme

# JavaScript only
php admin/cli/purge_caches.php --js

# Language strings only
php admin/cli/purge_caches.php --lang

# Combine
php admin/cli/purge_caches.php --theme --js --lang

# Compile CSS for a specific theme (without purging everything)
php admin/cli/build_theme_css.php --themes=mytheme --verbose
```

`build_theme_css.php` is especially useful: it compiles CSS and only increments the sub-revision of the specific theme.

## Dev config

```php
// Moodle's config.php — ONLY for development
@error_reporting(E_ALL | E_STRICT);
@ini_set('display_errors', '1');
$CFG->debug = (E_ALL | E_STRICT);          // DEBUG_DEVELOPER
$CFG->debugdisplay = 1;
$CFG->themedesignermode = true;             // Recompile SCSS on every load
$CFG->cachejs = false;                      // Do not cache JavaScript
$CFG->cachetemplates = false;               // Do not cache Mustache templates
$CFG->langstringcache = false;              // Do not cache language strings
$CFG->debugtemplateinfo = true;             // Template names in HTML comments
$CFG->debugstringids = 1;                   // Show string identifiers
$CFG->noemailever = true;                   // Do not send real emails
```

## Debug SCSS

SCSS compilation errors are not shown in the browser by default (MDL-62542). To diagnose:

1. Enable DEVELOPER debug in `config.php`
2. Purge caches (the error appears as PHP output)
3. Check logs: `tail -f /var/log/apache2/error.log`
4. Use CLI: `php admin/cli/build_theme_css.php --themes=mytheme --verbose`

Debug directives in SCSS:
```scss
$primary: #0073aa;
@debug "Current value of primary: #{$primary}";  // Writes to server log
@warn "Variable may change";                      // Warning in log
@error "Invalid value for primary";               // Stops compilation
```

## Debug templates

`$CFG->debugtemplateinfo = true` injects HTML comments:
```html
<!-- template(PHP): core/pix_icon_fontawesome -->
<i class="icon fa fa-window-close fa-fw" aria-hidden="true"></i>
<!-- /template(PHP): core/pix_icon_fontawesome -->
```

The Template Library (Administration → Development → Template Library) previews templates with `@template` annotations and example JSON contexts.

## Performance

```php
define('MDL_PERF', true);
define('MDL_PERFDB', true);
define('MDL_PERFTOFOOT', true);
```

Shows in the footer: load time, memory usage, DB queries, SCSS compilation times.

## Errors

| Problem | Cause | Solution |
|---|---|---|
| Changes not appearing | Cache not purged | `php admin/cli/purge_caches.php --theme` |
| CSS works in designer mode but not without it | Invalid SCSS that passes the compiler but breaks the minifier | Verify SCSS with verbose CLI |
| Template override has no effect | Incorrect path or cache | Verify path: `theme/<name>/templates/<component>/<template>.mustache`, use `$CFG->cachetemplates = false` |
| Language strings not updating | Language cache | `$CFG->langstringcache = false` |
| Child theme not inheriting styles | SCSS is not inherited automatically | Import explicitly: `theme_boost_get_main_scss_content($theme)` in `get_main_scss_content` |
| Page unstyled after editing SCSS | Silent compilation error | Use `build_theme_css.php --verbose` |
