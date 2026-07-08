# API — local_mod

Referencia de las funciones del Web Service, formatos de datos y del cliente
Python incluido. Para instalar el plugin primero, ver
[INSTALACION.md](README_INSTALACION.md).

## Versión del plugin

| Campo | Valor |
|---|---|
| Componente | `local_mod` |
| Versión (`$plugin->version`) | `2026070800` |
| Release | `1.2.1` |
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

### Banco de preguntas (quiz)

| Función WS | Uso | Parámetros clave | Capability |
|---|---|---|---|
| `local_mod_sync_question_bank` | Sincronizar (crear/reemplazar) | `cmid`, `preguntas[]`, `aleatorio?`, `cantidadaleatorias?`, `puntajealeatorio?`, `categoriaprefijo?` | `moodle/question:add` |
| `local_mod_delete_question_bank` | Vaciar | `cmid`, `categoriaprefijo?` | `moodle/question:managecategory` + `moodle/question:editall` |

`local_mod_sync_question_bank` es **idempotente**: en cada llamada primero
vacía el banco propio del quiz (categoría `{categoriaprefijo}{cmid}`, ver
abajo) y lo recrea completo desde `preguntas[]` — no hace merge/diff con lo
que ya existía. Por eso sirve tanto para la carga inicial como para cualquier
edición posterior del banco: isterpry siempre reenvía el banco completo
actualizado.

**Ambas funciones fallan** (`moodle_exception` `bancoconintentos`) si el quiz
ya tiene intentos registrados (`mdl_quiz_attempts`) — no se puede tocar el
banco de un quiz que alumnos ya rindieron.

#### Categoría propia del banco

Cada quiz tiene su propia categoría de preguntas dentro del contexto del
módulo, nombrada `{categoriaprefijo}{cmid}`. `categoriaprefijo` es opcional
(`PARAM_ALPHANUMEXT`) y por defecto es `banco_preguntas_` (constante
`helper::CATEGORIA_PREFIJO`) — normalmente no hace falta enviarlo; solo sirve
para aislar bancos si un mismo cmid necesitara más de una categoría lógica.
El cliente Python (`MoodleBancoPreguntasWS`) no lo expone: siempre usa el
default del plugin.

#### Parámetros de `local_mod_sync_question_bank`

| Parámetro | Tipo | Default | Descripción |
|---|---|---|---|
| `cmid` | `PARAM_INT` | — | course module id del quiz |
| `aleatorio` | `PARAM_INT` (0/1) | `0` | `1` = arma slots aleatorios en vez de fijos |
| `cantidadaleatorias` | `PARAM_INT` | `0` | N de preguntas a sortear por slot aleatorio (si `aleatorio=1`) |
| `puntajealeatorio` | `PARAM_FLOAT` | `1` | `maxmark` de cada slot aleatorio |
| `categoriaprefijo` | `PARAM_ALPHANUMEXT` | `banco_preguntas_` | Prefijo del nombre de categoría (`{prefijo}{cmid}`) |
| `preguntas[]` | lista | — | Ver tabla siguiente |

Cada elemento de `preguntas[]`:

| Campo | Tipo | Default | Descripción |
|---|---|---|---|
| `indice` | `PARAM_INT` | — | Correlativo propio de isterpry para mapear de vuelta el `questionid`/`questionbankentryid` creados |
| `qtype` | `PARAM_ALPHA` | — | `multichoice` / `truefalse` / `shortanswer` / `numerical` / `essay` |
| `nombre` | `PARAM_TEXT` | `''` | Nombre corto de la pregunta; si viene vacío se deriva del `enunciado` (recortado a 250 caracteres) |
| `enunciado` | `PARAM_RAW` | — | Enunciado en HTML |
| `retroalimentacion` | `PARAM_RAW` | `''` | Retroalimentación general (HTML); en `essay` también se usa como `graderinfo` |
| `puntaje` | `PARAM_FLOAT` | — | `maxmark` del slot fijo (ignorado si `aleatorio=1`) |
| `multiple` | `PARAM_INT` (0/1) | `0` | `multichoice`: permite varias respuestas correctas |
| `vfcorrecta` | `PARAM_INT` (0/1) | `1` | `truefalse`: `1`=Verdadero es la correcta, `0`=Falso |
| `usecase` | `PARAM_INT` (0/1) | `0` | `shortanswer`: sensible a mayúsculas/minúsculas |
| `essaytextorequerido` | `PARAM_INT` (0/1) | `1` | `essay`: exige respuesta de texto en línea |
| `essayadjuntos` | `PARAM_INT` | `0` | `essay`: número de adjuntos permitidos |
| `respuestas[]` | lista | `[]` | Ver tabla siguiente (no aplica a `essay`) |

Cada elemento de `respuestas[]` (dentro de cada pregunta):

| Campo | Tipo | Default | Descripción |
|---|---|---|---|
| `detalle` | `PARAM_RAW` | — | Texto de la respuesta |
| `fraccion` | `PARAM_FLOAT` | — | Fracción **en porcentaje** (`-100..100`), ya calculada por isterpry; el plugin la convierte internamente a la fracción decimal (`-1..1`) que espera Moodle |
| `tolerancia` | `PARAM_FLOAT` | `0` | Solo `numerical` |

Para `multichoice`, si `multiple=1` las respuestas correctas deben repartirse
de forma que sus `fraccion` sumen `100` entre sí (ej. dos correctas al 50%
cada una); si `multiple=0` (single, default) hay una sola respuesta con
`fraccion=100` y el resto en `0` (o negativas para penalizar).

#### Retorno de `local_mod_sync_question_bank`

| Campo | Tipo | Descripción |
|---|---|---|
| `success` | `bool` | `true` si sincronizó |
| `categoryid` | `int` | id de la categoría `{categoriaprefijo}{cmid}` |
| `sumgrades` | `float` | Suma de calificaciones del quiz tras recalcular (`quiz_update_sumgrades`) |
| `preguntas[]` | lista | Un item por pregunta enviada: `{indice, questionid, questionbankentryid}` — usa `indice` para mapear de vuelta a tu modelo |

#### Modo aleatorio

Con `aleatorio=1`, en vez de un slot fijo por pregunta se agregan
`cantidadaleatorias` slots que sortean preguntas de la categoría del banco en
tiempo de intento, cada uno con `maxmark=puntajealeatorio`. Las preguntas de
`preguntas[]` igual se crean en la categoría (son el pool del sorteo); su
campo `puntaje` se ignora en este modo — el puntaje real de cada slot lo da
`puntajealeatorio`.

#### Parámetros de `local_mod_delete_question_bank`

| Parámetro | Tipo | Default | Descripción |
|---|---|---|---|
| `cmid` | `PARAM_INT` | — | course module id del quiz |
| `categoriaprefijo` | `PARAM_ALPHANUMEXT` | `banco_preguntas_` | Debe coincidir con el usado al sincronizar |

Retorna `{success: bool, eliminadas: int}` (número de preguntas eliminadas).

## ADVERTENCIA OPERATIVA: debug display debe estar apagado

`sync_question_bank` y `delete_question_bank` envuelven APIs internas de
`mod_quiz` marcadas como deprecadas en Moodle reciente (`quiz_add_quiz_question`,
`quiz_add_random_questions`, `quiz_update_sumgrades`). Siguen siendo
funcionales en 4.1–4.5, pero **si el sitio corre con `debug` en `DEVELOPER` y
`displayerrors` activo**, cada llamada deprecada emite un `debugging()` que
Moodle inyecta como HTML plano **antes** del JSON de respuesta REST — en
4.4/4.5 esto corrompe el JSON y el cliente Python (o cualquier consumidor)
recibe una respuesta no parseable en vez del resultado esperado.

**El sitio Moodle debe correr con debug display apagado** (`$CFG->debugdisplay
= 0`, nivel de depuración `NONE` o `NORMAL` en Administración del sitio >
Depuración) para que estas dos funciones respondan JSON válido de forma
consistente.

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
| `sync_question_bank` | `execute_parameters()`, `execute()`, `execute_returns()` | `question_bank::get_qtype()->save_question()` + `quiz_add_quiz_question()` / `\mod_quiz\structure::add_random_questions()` |
| `delete_question_bank` | `execute_parameters()`, `execute()`, `execute_returns()` | `question_delete_question()` + `\mod_quiz\structure::remove_slot()` |
| `helper` | `options_structure()`, `apply_options()`, `set_common_defaults()`, `ensure_category()`, `wipe_question_bank()`, `add_fixed_slot()`, `add_random_slots()` | utilidades compartidas por las 8 clases de arriba |

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
| `MoodleBancoPreguntasWS` | `moodle_recursos/questionbank.py` | banco de preguntas de un quiz (no es modulo) | `sync_question_bank()`, `delete_question_bank()`, `es_error()` |

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

- Desde 1.2.1 usa los nombres con namespace `core_external\...` (los alias
  globales clásicos `external_api`, `external_function_parameters`… solo
  existen en 4.2+ si algo carga `lib/externallib.php`, que además desaparece
  en 4.6+; depender de ellos daba `Class "external_api" not found` según qué
  otros plugins tuviera el sitio). En Moodle 4.1, donde `core_external` aún
  no existe, `local_mod/locallib.php` crea los alias inversos — el plugin
  corre igual en 4.1 hasta 4.6+.
- La sección 0 no se puede eliminar (limitación de Moodle).
