# Build — empaquetar local_mod

Cómo generar el `.zip` instalable del plugin a partir del código fuente en
`local_mod/`. Para instalarlo en Moodle, ver [README_INSTALACION.md](README_INSTALACION.md).

## Cuándo ejecutarlo

**Cada vez que modifiques algo dentro de `local_mod/`** (clases en
`classes/external/`, `db/services.php`, `lang/*`, o subas el número de
`version.php`), corre el build antes de reinstalar/actualizar el plugin en
tu Moodle. El `.zip` no se genera solo ni se actualiza con git — es un paso
manual después de cada cambio.

Si no corriste el build después de tu último cambio, el `.zip` en `dist/`
queda desactualizado y vas a subir código viejo a Moodle sin darte cuenta.

## Cómo ejecutarlo

Desde la raíz del repo:

```bash
python build_plugin_zip.py
```

Parámetros opcionales (normalmente no hacen falta):

```bash
python build_plugin_zip.py --source local_mod --output dist/mi_nombre.zip --carpeta-zip mod
```

| Parámetro | Para qué | Default |
|---|---|---|
| `--source` | Carpeta del plugin a empaquetar | `local_mod` |
| `--output` | Ruta/nombre del `.zip` de salida | `dist/local_mod-<versión>.zip` (versión leída de `version.php`) |
| `--carpeta-zip` | Nombre de carpeta raíz dentro del zip | `mod` (obligatorio para que Moodle lo acepte, ver [README_INSTALACION.md](README_INSTALACION.md#cómo-generar-el-zip-correcto-para-la-opción-a)) |

## Dónde encontrar el resultado

En la carpeta **`dist/`** (raíz del repo), como `dist/local_mod-<versión>.zip`
— por ejemplo `dist/local_mod-1.1.0.zip`. El nombre incluye la versión de
`$plugin->release` en `version.php` para no confundir builds de distintos
cambios; si compilas dos veces sin subir la versión, el archivo se
sobreescribe con el mismo nombre.

`dist/` está en `.gitignore`: es una carpeta de artefactos generados, no se
commitea. Se recrea sola cada vez que corres el script (no hace falta
crearla a mano).

## Salida esperada

```
Version detectada: 1.1.0
OK: 11 archivos empaquetados en .../dist/local_mod-1.1.0.zip
Carpeta raiz dentro del zip: mod/
Listo para subir en Administracion del sitio > Plugins > Instalar plugins.
```

Si ves un error tipo `El zip generado tiene carpeta(s) raiz ...`, algo salió
mal armando el zip — no lo subas a Moodle, revisa `--carpeta-zip`.

## Checklist rápido tras modificar el plugin

1. Editaste algo en `local_mod/`.
2. Si el cambio es funcional (no solo un typo), sube `$plugin->version` en
   `local_mod/version.php` (formato `YYYYMMDDXX`) — así Moodle detecta que
   hay una actualización al reinstalar.
3. `python build_plugin_zip.py`
4. Sube el `.zip` de `dist/` por *Administración del sitio → Plugins →
   Instalar plugins*.
