# API — local_mod

Referencia de las funciones del Web Service, formatos de datos y del cliente
Python incluido. Para instalar el plugin primero, ver
[INSTALACION.md](README_INSTALACION.md).

## Versión del plugin

| Campo | Valor |
|---|---|
| Componente | `local_mod` |
| Versión (`$plugin->version`) | `2026070102` |
| Release | `1.1.0` |
| Moodle requerido (`$plugin->requires`) | `2022112800` (Moodle 4.1) |
| Madurez (`$plugin->maturity`) | `MATURITY_STABLE` |

Definido en `local_mod/version.php`. El formato de versión es `YYYYMMDDXX`
(fecha + contador de build del día); súbelo cada vez que publiques cambios
para que Moodle detecte la actualización. **Compatible desde Moodle 4.1 en
adelante** (ver notas de compatibilidad al final).

## Diseño

No toca tablas directamente: envuelve las funciones internas de Moodle, las
mismas que usa la interfaz web:

- Módulos: `add_moduleinfo()`, `update_moduleinfo()`, `course_delete_module()`
- Secciones: `course_create_section()`, `course_update_section()`, `course_delete_section()`

Por eso Moodle crea/actualiza el `grade_item`, el contexto y la `sequence` de
la sección correctamente, dispara los eventos esperados, y el plugin
sobrevive a upgrades de versión de Moodle.

Cubre `resource` (archivo), `url`, `forum`, `quiz`, `assign`, `h5pactivity` y
cualquier otro módulo de forma genérica, más el CRUD de secciones (incluida
la descripción/`summary`).

## Servicio

Todas las funciones están agrupadas en el servicio externo **Mod Management**
(`mod_mgmt`), ver [INSTALACION.md](README_INSTALACION.md#3-servicio-y-token) para
generar el token.

## Funciones expuestas

### Módulos

| Función WS | Uso | Parámetros clave | Capability |
|---|---|---|---|
| `local_mod_create_module` | Crear | `courseid`, `section`, `modulename`, `name`, `intro`, `visible`, `options[]` | `moodle/course:manageactivities` + `mod/<tipo>:addinstance` |
| `local_mod_update_module` | Actualizar | `cmid`, `name?`, `intro?`, `visible?`, `options[]` | `moodle/course:manageactivities` |
| `local_mod_delete_module` | Eliminar | `cmid` | `moodle/course:manageactivities` |

### Secciones

| Función WS | Uso | Parámetros clave | Capability |
|---|---|---|---|
| `local_mod_create_section` | Crear | `courseid`, `position` (0=final, N=número de sección absoluto), `name?`, `summary?`, `visible?` | `moodle/course:update` |
| `local_mod_update_section` | Actualizar | `courseid`, `sectionnumber`, `name?`, `summary?`, `visible?` | `moodle/course:update` |
| `local_mod_delete_section` | Eliminar | `courseid`, `sectionnumber`, `force?` (1 = borra aunque tenga actividades) | `moodle/course:update` (+ `moodle/course:sectionvisibility` si oculta) |

`summary` es la **descripción de la sección** (HTML). `options` (módulos) es
una lista de pares `{name, value}` con los campos específicos del tipo de
módulo.

### `position` en `create_section`: número de sección absoluto, no posición de inserción

`position` **no** es "insertar aquí desplazando lo demás" (así funciona
`course_create_section()` de Moodle core si se le pasa el valor directo).
`local_mod_create_section` lo trata como **el número de sección final que
quieres**, así que se puede migrar fuera de orden sin romper nada:

- Si `position` es mayor que el máximo actual, se crean vacías las secciones
  intermedias que falten (ej. pedir `position=10` con curso hasta la 2 crea
  3‑9 vacías y la 10 con tu contenido).
- Si `position` ya existe (por ser uno de esos huecos, o por pedirse dos
  veces), se **reutiliza esa misma sección** — no se dispara ningún shift, así
  que las secciones ya creadas (con sus módulos) no cambian de número.

Esto permite migrar en cualquier orden, por ejemplo `1, 10, 2, 4, 3`, y cada
`position` termina siendo exactamente el `sectionnumber` que devuelve la
función.

### Campos específicos de módulo habituales

- **url**: `externalurl`, `display`
- **resource**: `files` (itemid del draft), `display`
- **forum**: `type` (general, news, qanda…)
- **assign**: `duedate`, `allowsubmissionsfromdate`, `grade`, `assignsubmission_*`
- **quiz**: `grade`, `timeopen`, `timeclose`, `grademethod`, `preferredbehaviour`
- **h5pactivity**: `packagefile` (itemid del draft), `enabletracking`, `grademethod`

### Clases y métodos PHP (`local_mod/classes/external/`)

| Clase | Métodos | Envuelve |
|---|---|---|
| `create_module` | `execute_parameters()`, `execute()`, `execute_returns()` | `add_moduleinfo()` |
| `update_module` | `execute_parameters()`, `execute()`, `execute_returns()` | `get_moduleinfo_data()` + `update_moduleinfo()` |
| `delete_module` | `execute_parameters()`, `execute()`, `execute_returns()` | `course_delete_module()` |
| `create_section` | `execute_parameters()`, `execute()`, `execute_returns()` | `course_create_section()` + `course_update_section()` |
| `update_section` | `execute_parameters()`, `execute()`, `execute_returns()` | `course_update_section()` |
| `delete_section` | `execute_parameters()`, `execute()`, `execute_returns()` | `course_delete_section()` |
| `helper` | `options_structure()`, `apply_options()`, `set_common_defaults()` | utilidades compartidas por las 6 clases de arriba |

## Archivos (resource / h5p)

Se sube primero al *draft area* vía `POST /webservice/upload.php` (el
servicio ya trae `uploadfiles=1`), se obtiene un `itemid`, y ese `itemid` va
en `options` (`files` para resource, `packagefile` para h5p). El cliente
Python lo hace solo dentro de `crear_recurso_archivo()` y `crear_h5p()`.

## Manejo de errores

Toda respuesta que sea un dict con clave `exception` indica error de Moodle
(revisa `message`). El helper estático `es_error(resp)` (en `MoodleRecursoWS`
y en `MoodleSeccionWS`) lo detecta.

## Cliente Python

Paquete `moodle_recursos/`: un cliente simple por tipo de recurso, cada uno
con solo `crear()` / `actualizar()` / `eliminar()`. Todos extienden
`MoodleWebService` (`moodle_webservice.py`, raíz del repo — un cliente REST
mínimo; si tu proyecto ya tiene el suyo, bórralo y ajusta el import en
`moodle_recursos/base.py` y `moodle_recursos/seccion.py`) salvo donde se
indica.

| Clase | Archivo | Tipo Moodle | Funciones |
|---|---|---|---|
| `MoodleRecursoWS` | `moodle_recursos/base.py` | (base común) | `_opts()`, `es_error()`, `subir_archivo_draft()`, `_crear()`, `_actualizar()`, `eliminar()` |
| `MoodleUrlWS` | `moodle_recursos/url.py` | `url` | `crear()`, `actualizar()`, `eliminar()` *(heredado)* |
| `MoodleRecursoArchivoWS` | `moodle_recursos/recurso_archivo.py` | `resource` | `crear()`, `actualizar()`, `eliminar()` *(heredado)* |
| `MoodleForoWS` | `moodle_recursos/foro.py` | `forum` | `crear()`, `actualizar()`, `eliminar()` *(heredado)* |
| `MoodleTareaWS` | `moodle_recursos/tarea.py` | `assign` | `crear()`, `actualizar()`, `eliminar()` *(heredado)* |
| `MoodleQuizWS` | `moodle_recursos/quiz.py` | `quiz` | `crear()`, `actualizar()`, `eliminar()` *(heredado)* |
| `MoodleH5pWS` | `moodle_recursos/h5p.py` | `h5pactivity` | `crear()`, `actualizar()`, `eliminar()` *(heredado)* |
| `MoodleSeccionWS` | `moodle_recursos/seccion.py` | secciones (no es modulo) | `crear()`, `actualizar()`, `eliminar()`, `es_error()` |

`crear()`/`actualizar()` de cada clase de módulo llaman a `local_mod_create_module` /
`local_mod_update_module` con el `modulename` fijo de esa clase; `eliminar()`
(heredado de `MoodleRecursoWS`) llama a `local_mod_delete_module`.
`MoodleSeccionWS` llama directo a `local_mod_create_section` /
`local_mod_update_section` / `local_mod_delete_section`.

```python
from moodle_recursos import MoodleUrlWS, MoodleSeccionWS

seccion_ws = MoodleSeccionWS(url_base="https://tu-moodle.tld", token="TOKEN", tipo_moodle=1)
r = seccion_ws.crear(courseid=123, name="Unidad 1", summary="<p>Fundamentos.</p>")
sec = r["sectionnumber"]

url_ws = MoodleUrlWS(url_base="https://tu-moodle.tld", token="TOKEN", tipo_moodle=1)
r = url_ws.crear(123, sec, "Guía", externalurl="https://...")
url_ws.actualizar(cmid=r["cmid"], name="Nuevo nombre")
url_ws.eliminar(cmid=r["cmid"])
```

Ejemplo completo con las 7 clases (sección + los 6 tipos de módulo, crear +
actualizar) en [`ejemplo_moodle_recursos.py`](ejemplo_moodle_recursos.py)
(raíz del repo) — ajusta `URL_BASE`, `TOKEN` y `COURSE_ID` y corre
`python ejemplo_moodle_recursos.py`.

## Notas de compatibilidad hacia adelante

- Usa los nombres de clase del External API clásicos (`external_api`,
  `external_function_parameters`…), que en 4.2+ quedaron como alias
  deprecados pero funcionales; el plugin corre igual en 4.1–4.5. Si algún día
  los eliminan, se migra a `\core_external\...` sin cambiar la lógica.
- La sección 0 no se puede eliminar (limitación de Moodle).
- Para `quiz`, este WS crea el contenedor; las preguntas se cargan aparte
  (`qbank`).
