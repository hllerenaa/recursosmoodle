# Renderers and output components

## Table of contents
1. [Renderable + renderer + template pattern](#pattern)
2. [Creating a custom output component](#custom-component)
3. [Overriding core renderers](#override-core)
4. [Overriding plugin renderers](#override-plugins)
5. [The named_templatable interface](#named-templatable)

## Pattern

The renderer system connects PHP data with Mustache templates:

1. **Renderable**: Data class that implements `renderable` + `templatable`
2. **Renderer**: Invokes `export_for_template()` and `render_from_template()`
3. **Mustache Template**: The view

## Custom component

```php
// classes/output/hero_section.php
namespace theme_mytheme\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

class hero_section implements renderable, templatable {
    private string $title;
    private string $subtitle;
    private ?string $imageurl;

    public function __construct(string $title, string $subtitle, ?string $imageurl = null) {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->imageurl = $imageurl;
    }

    /**
     * Returns only simple types: arrays, stdClass, bool, int, float, string.
     * Never complex objects.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->title = $this->title;
        $data->subtitle = $this->subtitle;
        $data->hasimage = !empty($this->imageurl);
        $data->imageurl = $this->imageurl;
        return $data;
    }
}
```

Usage from a layout:
```php
$hero = new \theme_mytheme\output\hero_section('Welcome', 'Learning platform', $imageurl);
echo $OUTPUT->render($hero);
```

## Override core

Requires `$THEME->rendererfactory = 'theme_overridden_renderer_factory'` in `config.php`.

```php
// classes/output/core_renderer.php
namespace theme_mytheme\output;

defined('MOODLE_INTERNAL') || die();

class core_renderer extends \core_renderer {

    public function heading($text, $level = 2, $classes = 'main', $id = null) {
        $content = \html_writer::start_tag('div', ['class' => 'custom-heading-wrapper']);
        $content .= parent::heading($text, $level, $classes, $id);
        $content .= \html_writer::end_tag('div');
        return $content;
    }
}
```

Important rule: inside renderers, always use `$this->output` and `$this->page` instead of the globals `$OUTPUT` or `$PAGE`.

## Override plugins

The class must extend the original renderer:

```php
// classes/output/mod_quiz_renderer.php
namespace theme_mytheme\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/renderer.php');

class mod_quiz_renderer extends \mod_quiz_renderer {
    // Override specific methods
}
```

## Named templatable

If a renderable implements `named_templatable` with `get_template_name()`, no explicit renderer method is needed — Moodle automatically routes to the correct template:

```php
class hero_section implements renderable, named_templatable {
    // ...

    public function get_template_name(renderer_base $renderer): string {
        return 'theme_mytheme/hero_section';
    }
}
```
