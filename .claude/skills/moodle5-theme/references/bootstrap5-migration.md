# Bootstrap 4 → Bootstrap 5.3 migration

## Table of contents
1. [CSS class equivalences](#css-classes)
2. [Data attributes](#data-attributes)
3. [SCSS/mixin changes](#scss)
4. [JavaScript without jQuery](#javascript)
5. [BS4 compatibility layer](#compatibility)

## CSS classes

| Bootstrap 4 | Bootstrap 5 |
|---|---|
| `.ml-3`, `.mr-3` | `.ms-3`, `.me-3` |
| `.pl-3`, `.pr-3` | `.ps-3`, `.pe-3` |
| `.text-left`, `.text-right` | `.text-start`, `.text-end` |
| `.float-left`, `.float-right` | `.float-start`, `.float-end` |
| `.sr-only` | `.visually-hidden` |
| `.font-weight-bold` | `.fw-bold` |
| `.badge-primary` | `.text-bg-primary` |
| `.close` | `.btn-close` |
| `.form-group` | `.mb-3` |
| `.custom-select` | `.form-select` |

## Data attributes

All Bootstrap component data-attributes now use the `bs` prefix:

| Bootstrap 4 | Bootstrap 5 |
|---|---|
| `data-toggle="modal"` | `data-bs-toggle="modal"` |
| `data-target="#id"` | `data-bs-target="#id"` |
| `data-dismiss="alert"` | `data-bs-dismiss="alert"` |
| `data-toggle="dropdown"` | `data-bs-toggle="dropdown"` |
| `data-toggle="collapse"` | `data-bs-toggle="collapse"` |
| `data-toggle="tooltip"` | `data-bs-toggle="tooltip"` |
| `data-slide="next"` | `data-bs-slide="next"` |
| `data-ride="carousel"` | `data-bs-ride="carousel"` |

## SCSS

| Bootstrap 4 | Bootstrap 5 |
|---|---|
| `theme-color-level($color, $level)` | `shift-color($color, $percentage)` |
| `@include hover-focus()` | `&:hover, &:focus` |
| `media-breakpoint-down(sm)` | `media-breakpoint-down(md)` (shifted by one breakpoint) |

## JavaScript

Bootstrap 5 no longer requires jQuery. Components are imported directly:

```javascript
// Before (BS4 + jQuery)
$('#my-dropdown').dropdown('toggle');

// Now (BS5 vanilla)
import Dropdown from 'theme_boost/bootstrap/dropdown';
const dd = new Dropdown('#my-dropdown');
dd.toggle();

// Other components
import Modal from 'theme_boost/bootstrap/modal';
import Tooltip from 'theme_boost/bootstrap/tooltip';
import Collapse from 'theme_boost/bootstrap/collapse';
```

## Compatibility

Moodle includes a BS4 compatibility layer that will be available until **Moodle 6.0**:

```javascript
import * as BS4compat from 'theme_boost/bs4-compat';
BS4compat.init(document.querySelector('[data-region="my-region"]'));
```

All new logic should be written with Bootstrap 5.3 syntax to avoid technical debt.

### Deprecation table

| Element | Available until |
|---|---|
| Bootstrap 4 CSS classes | Moodle 6.0 |
| Data-attributes without `bs` prefix | Moodle 6.0 |
| Course editing YUI modules | Moodle 6.0 |
| `$THEME->javascripts` / `$THEME->javascripts_footer` | Moodle 6.0 (use AMD) |
