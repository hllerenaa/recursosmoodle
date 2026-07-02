# Directory structure and required files

## Table of contents
1. [Full structure of a Boost child theme](#full-structure)
2. [Required files](#required-files)
3. [Theme inheritance](#theme-inheritance)
4. [Scaffolding a new theme](#scaffolding)

## Full structure

In Moodle 5.1+, themes reside under `public/theme/` (the DocumentRoot points to `/public/`):

```
public/theme/mytheme/
├── amd/
│   └── src/                    # AMD JavaScript modules (ES6)
├── classes/
│   └── output/                 # Custom renderers
├── fonts/                      # Custom fonts
├── fonts_core/                 # Core font overrides
├── fonts_plugins/
│   └── plugintype/pluginname/
├── lang/
│   └── en/
│       └── theme_mytheme.php   # Language strings (REQUIRED)
├── layout/                     # PHP layout files
├── pix/
│   ├── favicon.ico
│   └── screenshot.png          # ~500x400px for the theme selector
├── pix_core/                   # Core icon overrides
├── pix_plugins/                # Plugin icon overrides
├── scss/                       # SCSS source files
├── style/                      # Compiled CSS (only if SCSS is not used)
├── templates/                  # Mustache template overrides
│   └── core/
│       └── templatename.mustache
├── config.php                  # REQUIRED
├── lib.php                     # REQUIRED
├── settings.php                # Optional: admin settings
└── version.php                 # REQUIRED
```

## Required files

### version.php

Declares plugin metadata. `$plugin->supported` limits compatible branches, `$plugin->dependencies` ensures the parent theme is present:

```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2025100600;        // Format YYYYMMDDXX
$plugin->requires  = 2025041600;        // Minimum Moodle version (5.0)
$plugin->supported = [501, 501];        // Supported branches
$plugin->component = 'theme_mytheme';   // Frankenstyle name
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
$plugin->dependencies = [
    'theme_boost' => 2025041600,
];
```

### config.php

The most important file. Defines inheritance, layouts, SCSS compilation, and behavior:

```php
<?php
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/lib.php');

$THEME->name = 'mytheme';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_scss = ['editor'];
$THEME->usefallback = true;

// SCSS callbacks (three pipeline phases)
$THEME->scss = function($theme) {
    return theme_mytheme_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_mytheme_get_pre_scss';
$THEME->extrascsscallback = 'theme_mytheme_get_extra_scss';
$THEME->precompiledcsscallback = 'theme_mytheme_get_precompiled_css';

// Theme behavior
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->enable_dock = false;
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;

// Layouts — in 5.1+ they inherit automatically from the parent
// Only redefine those you want to customize
$THEME->layouts = [
    'base'      => ['file' => 'drawers.php', 'regions' => []],
    'standard'  => ['file' => 'drawers.php', 'regions' => ['side-pre'],
                     'defaultregion' => 'side-pre'],
    'course'    => ['file' => 'drawers.php', 'regions' => ['side-pre'],
                     'defaultregion' => 'side-pre',
                     'options' => ['langmenu' => true]],
    'frontpage' => ['file' => 'drawers.php', 'regions' => ['side-pre'],
                     'defaultregion' => 'side-pre',
                     'options' => ['nonavbar' => true]],
    'login'     => ['file' => 'login.php', 'regions' => [],
                     'options' => ['langmenu' => true]],
    'popup'     => ['file' => 'columns1.php', 'regions' => [],
                     'options' => ['nofooter' => true, 'nonavbar' => true]],
    'maintenance' => ['file' => 'maintenance.php', 'regions' => []],
];
```

**Available Boost layouts**: `drawers.php` (main with side navigation), `columns1.php` (single column), `login.php`, `embedded.php`, `maintenance.php`, `secure.php`.

**New in 5.1 (MDL-79319)**: Layouts inherit automatically from the parent. You no longer need to redeclare them all.

### lang/en/theme_mytheme.php

Minimum required strings:

```php
<?php
$string['pluginname'] = 'My Theme';
$string['choosereadme'] = 'My Theme is a custom Boost child theme.';
$string['configtitle'] = 'My Theme';
```

## Theme inheritance

`$THEME->parents = ['boost']` enables inheritance at five levels:

1. **SCSS**: Injected before and after the parent's SCSS via callbacks
2. **Mustache Templates**: Looked up first in the child, then up the parent chain
3. **PHP Layouts**: Inherit automatically from the parent (5.1+)
4. **Icons**: Overridden by placing them in `pix_core/` and `pix_plugins/`
5. **Renderers**: `theme_overridden_renderer_factory` looks for classes in the theme first

SCSS is **not** inherited automatically — you must explicitly import the parent's SCSS via `theme_boost_get_main_scss_content($theme)` in the `get_main_scss_content` function.

## Scaffolding

When generating a new theme, create at minimum these files:

1. `version.php` — with `$plugin->component = 'theme_<name>'`
2. `config.php` — with Boost inheritance and SCSS callbacks
3. `lib.php` — with the three SCSS pipeline functions
4. `lang/en/theme_<name>.php` — with the three minimum strings
5. `scss/pre.scss` — empty file or with custom imports
6. `scss/post.scss` — empty file or with custom rules
