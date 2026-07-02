# Theme settings management (settings.php)

## Table of contents
1. [Structure of settings.php](#structure)
2. [Available setting types](#types)
3. [Full example with tabs](#example)
4. [Accessing values from PHP and templates](#access)
5. [Serving uploaded files (pluginfile)](#pluginfile)

## Structure

Settings are defined in `settings.php` and stored in `mdl_config_plugins`. Boost provides `theme_boost_admin_settingspage_tabs` to organize them into tabs.

Critical rule: every setting that affects CSS/SCSS **must** include:
```php
$setting->set_updatedcallback('theme_reset_all_caches');
```
Without this, style changes will not be reflected until the next manual cache purge.

## Types

| Type | Usage | Example |
|---|---|---|
| `admin_setting_configtext` | Short free text | Footer text, external URL |
| `admin_setting_configtextarea` | Multiline text | Additional HTML code |
| `admin_setting_confightmleditor` | HTML editor | Rich text content |
| `admin_setting_configcolourpicker` | Color picker | Brand color |
| `admin_setting_configcheckbox` | On/off toggle | Show/hide section |
| `admin_setting_configselect` | Dropdown | Preset selection |
| `admin_setting_configstoredfile` | File upload | Logo, background image |
| `admin_setting_scsscode` | SCSS editor | Custom CSS |
| `admin_setting_heading` | Visual heading | Section separator |

## Example

```php
<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs(
        'themesettingmytheme',
        get_string('configtitle', 'theme_mytheme')
    );

    // === TAB: General settings ===
    $page = new admin_settingpage('theme_mytheme_general',
        get_string('generalsettings', 'theme_mytheme'));

    // Visual heading (no stored value)
    $page->add(new admin_setting_heading('theme_mytheme/brandingheading',
        get_string('branding', 'theme_mytheme'),
        get_string('branding_desc', 'theme_mytheme')));

    // Color picker
    $setting = new admin_setting_configcolourpicker('theme_mytheme/brandcolor',
        get_string('brandcolor', 'theme_mytheme'),
        get_string('brandcolor_desc', 'theme_mytheme'), '#0f6cbf');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Logo upload
    $setting = new admin_setting_configstoredfile('theme_mytheme/logo',
        get_string('logo', 'theme_mytheme'),
        get_string('logodesc', 'theme_mytheme'), 'logo', 0,
        ['accepted_types' => ['.png', '.jpg', '.svg', '.webp'], 'maxfiles' => 1]);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Checkbox toggle
    $setting = new admin_setting_configcheckbox('theme_mytheme/showfooter',
        get_string('showfooter', 'theme_mytheme'),
        get_string('showfooter_desc', 'theme_mytheme'), 1);
    $page->add($setting);

    // Dropdown selector
    $setting = new admin_setting_configselect('theme_mytheme/preset',
        get_string('preset', 'theme_mytheme'),
        get_string('preset_desc', 'theme_mytheme'), 'default.scss',
        ['default.scss' => 'Default', 'plain.scss' => 'Plain']);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Textarea for custom SCSS
    $setting = new admin_setting_scsscode('theme_mytheme/scss',
        get_string('rawscss', 'theme_mytheme'),
        get_string('rawscss_desc', 'theme_mytheme'), '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Free text
    $setting = new admin_setting_configtext('theme_mytheme/footnote',
        get_string('footnote', 'theme_mytheme'),
        get_string('footnotedesc', 'theme_mytheme'), '', PARAM_NOTAGS, 50);
    $page->add($setting);

    $settings->add($page);

    // === TAB: Images ===
    $page = new admin_settingpage('theme_mytheme_images',
        get_string('imagessettings', 'theme_mytheme'));

    $setting = new admin_setting_configstoredfile('theme_mytheme/loginbackgroundimage',
        get_string('loginbackgroundimage', 'theme_mytheme'),
        get_string('loginbackgroundimage_desc', 'theme_mytheme'),
        'loginbackgroundimage', 0,
        ['accepted_types' => ['.png', '.jpg', '.webp']]);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
```

## Access

### From PHP (lib.php and layouts)

```php
// Via get_config
$brandcolor = get_config('theme_mytheme', 'brandcolor');

// Via the $theme object (inside lib.php callbacks)
$value = $theme->settings->brandcolor;

// URL for uploaded files
$logourl = $theme->setting_file_url('logo', 'logo');
```

### From Mustache templates

Templates do not have direct access to settings. Values must be passed as context from the PHP layout file:

```php
// In layout/drawers.php
$templatecontext = [
    'showfooter' => get_config('theme_mytheme', 'showfooter'),
    'footnote'   => format_text(get_config('theme_mytheme', 'footnote'), FORMAT_HTML),
    'logourl'    => $OUTPUT->get_logo_url(),
    'output'     => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes([]),
];
echo $OUTPUT->render_from_template('theme_mytheme/drawers', $templatecontext);
```

## Pluginfile

For uploaded files with `configstoredfile` to work, `lib.php` must implement:

```php
function theme_mytheme_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $theme = theme_config::load('mytheme');
        if (!array_key_exists($filearea, [
            'logo' => 1,
            'loginbackgroundimage' => 1,
            'headerbackgroundimage' => 1,
        ])) {
            send_file_not_found();
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}
```
