# moodle5-theme

Create and customize Moodle 5.1+ themes based on Boost (Bootstrap 5.3).

## Install

```bash
npx skills add nicolasflores9/skills -s moodle5-theme
```

Or manually:

```bash
# Claude Code (project)
cp -r . .claude/skills/moodle5-theme/

# Claude Code (global)
cp -r . ~/.claude/skills/moodle5-theme/
```

## What It Does

This skill teaches your AI agent how to generate production-ready Moodle 5 theme code. It covers the full development lifecycle:

**Creating themes** — Generates complete Boost child theme scaffolding with all required files (`version.php`, `config.php`, `lib.php`, language strings).

**Modifying styles** — Understands Moodle's three-phase SCSS pipeline and generates correct pre-SCSS, preset, and extra-SCSS code. Maps admin settings to Bootstrap variables.

**Overriding templates** — Knows the exact Mustache override paths, Moodle-specific helpers (`{{#str}}`, `{{#pix}}`, `{{#js}}`), and the BLOCKS pragma for template inheritance.

**Admin settings** — Generates settings pages with color pickers, file uploads, textareas, and SCSS editors, including proper cache invalidation callbacks.

**Renderers** — Creates custom output components following the renderable + renderer + template pattern, and overrides core or plugin renderers.

**Bootstrap 5.3** — All generated code uses modern BS5 syntax. Includes a complete BS4 → BS5 migration reference for updating existing themes.

**Debugging** — Guides through cache issues, SCSS compilation errors, template debugging, and performance monitoring.

## Reference Files

The skill loads reference documentation on demand based on the task:

| File | Content |
|---|---|
| `references/structure.md` | Directory layout, required files, theme inheritance |
| `references/scss-pipeline.md` | Three-phase SCSS compilation, `lib.php` callbacks, Bootstrap variables |
| `references/templates.md` | Mustache syntax, helpers, override paths, blocks pragma |
| `references/settings.md` | `settings.php` structure, setting types, accessing values from templates |
| `references/renderers.md` | Output components, core renderer overrides, `named_templatable` |
| `references/bootstrap5-migration.md` | BS4 → BS5 class mapping, data attributes, SCSS mixins, JS changes |
| `references/debugging.md` | Cache system, Theme Designer Mode, CLI commands, common errors |

## Examples

```
"Create a Boost child theme called campus with custom branding colors"
"Add a setting to upload a logo and display it in the navbar"
"Override the footer template to add social media links"
"Migrate my theme templates from Bootstrap 4 to Bootstrap 5"
"My SCSS changes aren't showing up after editing"
```

## Compatibility

- Moodle 5.0+ (optimized for 5.1.3+)
- PHP 8.3+
- PostgreSQL 15+ / MySQL 8.4+

## License

[Apache 2.0](../../../LICENSE)
