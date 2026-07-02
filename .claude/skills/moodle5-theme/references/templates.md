# Mustache template system

## Table of contents
1. [Mustache syntax in Moodle](#syntax)
2. [Moodle-specific helpers](#helpers)
3. [Blocks and template inheritance](#blocks)
4. [How to override templates](#overriding)
5. [Best practices](#best-practices)
6. [Rendering from JavaScript](#js-rendering)

## Syntax

Templates reside in `templates/` of each component and are identified by Frankenstyle name: `mod_lesson/timer` → `mod/lesson/templates/timer.mustache`.

```mustache
{{variable}}          {{! HTML-escaped }}
{{{variable}}}        {{! Unescaped — only for content already processed with format_text() }}
{{#variable}}...{{/variable}}   {{! Conditional / loop }}
{{^variable}}...{{/variable}}   {{! Inverted section (if variable is falsy/empty) }}
{{> core/loading}}              {{! Partial — include another template }}
```

## Helpers

Moodle extends Mustache with essential helpers:

```mustache
{{! Language strings — equivalent to get_string() }}
{{#str}} helloworld, mod_greeting {{/str}}
{{#str}} backto, core, {{name}} {{/str}}

{{! Pix icons }}
{{#pix}} t/edit, core, Edit this section {{/pix}}

{{! JavaScript deferred to footer }}
{{#js}}
require(['theme_boost/form-display-errors'], function(module) {
    module.enhance({{#quote}}{{element.id}}{{/quote}});
});
{{/js}}

{{! Date formatting with user's timezone }}
{{#userdate}} {{time}}, {{#str}} strftimedate, core_langconfig {{/str}} {{/userdate}}

{{! Truncate text preserving words }}
{{#shortentext}} 15, {{{description}}} {{/shortentext}}

{{! Unique IDs for JS hooks }}
<div id="{{uniqid}}-my-widget"></div>
```

The `{{#quote}}` helper is required when passing non-scalar values to the `{{#str}}` helper:

```mustache
{{#str}} counteditems, core, { "count": {{count}}, "items": {{#quote}} {{itemname}} {{/quote}} } {{/str}}
```

## Blocks

Moodle enables the Mustache BLOCKS pragma for template inheritance:

```mustache
{{! Parent template: tool_demo/section }}
<section>
    <h1>{{$heading}} Default heading {{/heading}}</h1>
    <div>{{$content}} Default content {{/content}}</div>
</section>

{{! Child template that extends the parent }}
{{< tool_demo/section}}
    {{$heading}} Latest news {{/heading}}
    {{$content}} Custom content {{/content}}
{{/ tool_demo/section}}
```

Used extensively in core (e.g., `core/notification_error` extends `core/notification_base`).

## Overriding

To override a template, create the file with the same structure inside the theme's `templates/` directory, using the component name as a subdirectory:

| Original template | Override path in the theme |
|---|---|
| `lib/templates/modal.mustache` | `theme/mytheme/templates/core/modal.mustache` |
| `blocks/myoverview/templates/view-summary.mustache` | `theme/mytheme/templates/block_myoverview/view-summary.mustache` |
| `theme/boost/templates/navbar.mustache` | `theme/mytheme/templates/theme_boost/navbar.mustache` |
| `mod/wiki/templates/ratingui.mustache` | `theme/mytheme/templates/mod_wiki/ratingui.mustache` |

Templates are aggressively cached. Enable Theme Designer Mode or use `$CFG->cachetemplates = false` during development.

### Required boilerplate

Every template must include a GPL license, `@template` annotation, variable descriptions, and a JSON example:

```mustache
{{!
    This file is part of Moodle - http://moodle.org/

    Moodle is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    @template  theme_mytheme/hero_section
    @context   {
        "title": "Welcome",
        "subtitle": "Learning platform",
        "hasimage": true,
        "imageurl": "https://example.com/hero.jpg"
    }
}}
<div class="theme-mytheme-hero-section" data-region="hero-section">
    <h1>{{title}}</h1>
    <p>{{subtitle}}</p>
    {{#hasimage}}
    <img src="{{imageurl}}" alt="" class="img-fluid">
    {{/hasimage}}
</div>
```

## Best practices

- **A single root node** per template, with a class matching the template name
- **Data-attributes** for JS hooks, not classes or IDs: `data-region="hero-section"`
- **Bootstrap classes** directly in templates
- **Never** reuse helper names (`str`, `js`, `pix`, `quote`) as variable names — they will silently fail
- PHP arrays without key `[0]` or with gaps are not iterable in Mustache → use `array_values()`
- To test for non-empty arrays, add a boolean flag like `hasusers` to the context
- Enable `$CFG->debugtemplateinfo = true` to see which template renders each section (HTML comments)

## JS rendering

```javascript
import Templates from 'core/templates';
import {exception as displayException} from 'core/notification';

const context = { title: 'Welcome', subtitle: 'To our platform' };
Templates.renderForPromise('theme_mytheme/hero_section', context)
    .then(({html, js}) => {
        Templates.appendNodeContents('.hero-container', html, js);
    })
    .catch((error) => displayException(error));
```
