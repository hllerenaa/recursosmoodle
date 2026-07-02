# SCSS compilation pipeline and presets

## Table of contents
1. [The three pipeline phases](#the-three-phases)
2. [lib.php implementation](#libphp-implementation)
3. [How to override Bootstrap variables](#override-variables)
4. [Presets and Bootswatch](#presets)
5. [Theme SCSS files](#theme-scss-files)

## The three phases

Moodle uses `scssphp/scssphp` (or the `sassc` binary if configured). Compilation follows a strict order:

```
┌────────────────────────────────────────────────────┐
│  1. prescsscallback (get_pre_scss)                  │
│     → SCSS variables without !default: $primary:    │
│       #e74c3c                                       │
│     → Raw SCSS from admin textarea                  │
│                                                      │
│  2. $THEME->scss (get_main_scss_content)             │
│     → Preset with variables (!default)              │
│     → @import "moodle" (Bootstrap + Moodle)          │
│     → CSS rules from the preset                     │
│                                                      │
│  3. extrascsscallback (get_extra_scss)               │
│     → Dynamic SCSS (background images, etc.)        │
│     → Raw SCSS from admin textarea                  │
├────────────────────────────────────────────────────┤
│  Concatenated → SCSS compiler → CSS on disk         │
└────────────────────────────────────────────────────┘
```

The order is critical: variables in phase 1 (without `!default`) override those from Bootstrap (with `!default`). Phase 3 has the highest priority due to CSS cascade.

## lib.php implementation

These are the three main functions every Boost child theme needs:

```php
<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Phase 2: Returns the main SCSS (preset + own pre/post).
 */
function theme_mytheme_get_main_scss_content($theme) {
    global $CFG;
    $scss = '';

    // Select preset (defaults to Boost's)
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();
    $context = context_system::instance();

    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    } else if ($filename && ($presetfile = $fs->get_file(
            $context->id, 'theme_mytheme', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Fallback to Boost's default preset
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    }

    // Wrap with own pre.scss and post.scss
    $pre = file_get_contents($CFG->dirroot . '/theme/mytheme/scss/pre.scss');
    $post = file_get_contents($CFG->dirroot . '/theme/mytheme/scss/post.scss');
    return $pre . "\n" . $scss . "\n" . $post;
}

/**
 * Phase 1: SCSS variables that override Bootstrap's !default values.
 * Injected BEFORE the preset.
 */
function theme_mytheme_get_pre_scss($theme) {
    $scss = '';

    // Setting → SCSS variable mapping
    $configurable = [
        'brandcolor'     => ['primary'],
        'secondarycolor' => ['secondary'],
    ];

    foreach ($configurable as $configkey => $targets) {
        $value = $theme->settings->{$configkey} ?? null;
        if (empty($value)) continue;
        foreach ($targets as $target) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }
    }

    // Raw SCSS from admin textarea (pre)
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * Phase 3: Additional SCSS after everything (highest priority due to cascade).
 */
function theme_mytheme_get_extra_scss($theme) {
    $content = '';

    // Raw SCSS from admin textarea (post)
    if (!empty($theme->settings->scss)) {
        $content .= $theme->settings->scss;
    }

    // Login background image from settings
    $loginbgurl = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (!empty($loginbgurl)) {
        $content .= "body.pagelayout-login #page {
            background-image: url('$loginbgurl');
            background-size: cover; }";
    }

    return $content;
}
```

## Override variables

The variable override mechanism works as follows:

1. **In pre-SCSS**: Define without `!default` → forces the value
   ```scss
   $primary: #e74c3c;  // Overrides Bootstrap's default
   ```

2. **In the preset**: Bootstrap defines with `!default` → only applies if not already defined
   ```scss
   $primary: #0d6efd !default;  // Ignored because it already exists from phase 1
   ```

**Flow when an admin changes the brand color**:
1. `#e74c3c` is saved in `mdl_config_plugins`
2. `theme_reset_all_caches` increments `themerev`
3. Next page load triggers recompilation
4. `get_pre_scss()` emits `$primary: #e74c3c;`
5. Bootstrap skips its `!default`
6. All components using `$primary` render in the new color

### Useful Bootstrap variables for themes

```scss
// Main colors
$primary: #0d6efd !default;
$secondary: #6c757d !default;
$success: #198754 !default;
$info: #0dcaf0 !default;
$warning: #ffc107 !default;
$danger: #dc3545 !default;
$light: #f8f9fa !default;
$dark: #212529 !default;

// Typography
$font-family-base: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif !default;
$font-size-base: 1rem !default;
$headings-font-weight: 500 !default;

// Spacing and borders
$border-radius: 0.375rem !default;
$border-radius-lg: 0.5rem !default;
$spacer: 1rem !default;

// Moodle activity colors (new in 5.x)
$activity-icon-assessment-bg: #17857f !default;
$activity-icon-collaboration-bg: #f7634d !default;
$activity-icon-communication-bg: #eb66a2 !default;
$activity-icon-content-bg: #399be2 !default;
$activity-icon-interactivecontent-bg: #a378b4 !default;
```

## Presets

A preset is a `.scss` file with three sections: variables → `@import "moodle"` → custom rules.

```scss
// Custom preset example: mytheme/scss/preset/campus.scss
$primary: #1a5276;
$secondary: #2e86c1;
$body-bg: #fafafa;
$navbar-light-bg: #1a5276;

@import "moodle";

// Rules after import
.navbar {
    box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
}
```

Presets can be uploaded from the admin interface using `admin_setting_configstoredfile`.

## Theme SCSS files

Organize the theme's SCSS in two main files:

- **`scss/pre.scss`**: Imports, mixins, and custom variables that must be available before the preset
- **`scss/post.scss`**: Custom CSS rules applied after the preset

Example `post.scss`:
```scss
// Header customization
.navbar {
    background-color: $primary;
    .nav-link {
        color: rgba(255, 255, 255, 0.85);
        &:hover { color: #fff; }
    }
}

// Footer customization
#page-footer {
    background-color: $dark;
    color: $light;
    padding: 2rem 0;
}

// Custom course card
.card.dashboard-card {
    border: none;
    border-radius: $border-radius-lg;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    transition: box-shadow 0.2s ease;
    &:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
}
```
