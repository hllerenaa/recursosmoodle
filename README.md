# local_mod — Gestión de módulos y secciones Moodle por Web Service

Plugin `local` que expone crear / actualizar / eliminar **actividades, recursos y
secciones** de un curso vía Web Service. Reemplaza el enfoque anterior por `INSERT`
directo en la BD (`mdl_url`, `mdl_assign`, `mdl_grade_items`, `mdl_context`,
`mdl_course_sections`…).

Cubre: `resource` (archivo), `url`, `forum`, `quiz`, `assign`, `h5pactivity` y
cualquier otro módulo de forma genérica; más el CRUD de secciones (con descripción).

**Compatible Moodle 4.1 en adelante.** No toca tablas directamente: envuelve las
funciones internas de Moodle, las mismas que usa la interfaz.

## Funciones del Web Service

Módulos: `local_mod_create_module`, `local_mod_update_module`, `local_mod_delete_module`

Secciones: `local_mod_create_section`, `local_mod_update_section`, `local_mod_delete_section`

Detalle de parámetros y capabilities en [README_GENERACION_API.md](README_GENERACION_API.md#funciones-expuestas).

## Imágenes de secciones y recuperación de caché (1.2.6)

Antes de operar, se convierten las imágenes PNG/JPEG/GIF/WebP base64 de los
resúmenes existentes en archivos de Moodle (`course/section`, contexto del curso,
itemid de la sección). El HTML utiliza `@@PLUGINFILE@@` y conserva los demás
atributos y contenido. Los archivos se identifican por su contenido para que
repetir una migración no genere duplicados. Las actualizaciones se realizan con
`course_update_section`, conservando sus eventos y actualizaciones de caché.
La conversión requiere `moodle/course:update` cuando hay imágenes incrustadas.

La creación y actualización de secciones también convierten los resúmenes
entrantes. Esto evita volver a almacenar imágenes base64 en `coursemodinfo`.

En el curso 4212 se verificaron 20 imágenes PNG y 55.316.479 bytes de resúmenes.
Crear un quiz agotaba los 512 MB de PHP al deserializar caché; el rollback dejaba
un módulo inexistente cacheado. La versión 1.2.5 permitió capturar ese error fatal
y recuperar automáticamente el acceso al curso tras la operación fallida.

Con 1.2.6 los resúmenes del curso quedaron en 9.347 bytes, con las 20 imágenes
referenciadas como archivos. Se verificaron creación y actualización de un quiz
oculto, sincronización de 20 preguntas con puntaje total 20, descarga de una imagen
con hash correcto y reenvío idempotente de un resumen base64. Se eliminó el quiz de
prueba y el curso quedó accesible con sus 185 módulos originales.

La creación y actualización de módulos y la sincronización del banco de preguntas
invalidan la entrada `core/coursemodinfo` del curso antes de operar. Esto permite
reconstruirla desde la base incluso en cursos modificados previamente por SQL.

Estas operaciones usan una transacción propia. Si fallan, primero revierten las
transacciones pendientes y después eliminan la entrada de caché del curso con el
bloqueo MUC correspondiente y reinician sus cachés de memoria/navegación. En Moodle
4.5.2, incrementar solamente `course.cacherev` no basta tras un rollback: la caché
puede conservar una revisión superior con un módulo que ya no existe en la base.
Estas funciones externas deben invocarse sin una transacción envolvente ajena.

La excepción original de Moodle conserva su código. El detalle incluye operación,
curso y traza de archivos/funciones sin argumentos, disponible en `debuginfo` con
depuración activada, además del registro PHP. La recuperación de caché no corrige
por sí misma la excepción que haya hecho fallar la actividad: esa causa se debe
comprobar con la traza y una migración en la instalación de destino.

Si falla la operación de creación, la respuesta incluye `exception`, `errorcode`
y `message` con `cmid=0` e `instance=0`; `debuginfo` se incluye solamente con
depuración activada. El cliente debe comprobar `exception` antes de usar los IDs.

También se registra una recuperación en `core_shutdown_manager` para operaciones
que terminan sin devolver el control, por ejemplo un error fatal de PHP que no
pasa por `catch`. Se captura el último error PHP y el límite de memoria inicial;
el cliente debe conservar las respuestas JSON de error incluso con HTTP 500.

## Recursos que simula el cliente Python (`moodle_recursos/`)

Cada archivo simula lo mismo que harías a mano desde *Añadir una actividad o
recurso* en la interfaz de Moodle, pero vía Web Service:

| Archivo | Clase | Tipo Moodle | Qué simula |
|---|---|---|---|
| `url.py` | `MoodleUrlWS` | `url` | Agregar un recurso **URL** (enlace externo) |
| `recurso_archivo.py` | `MoodleRecursoArchivoWS` | `resource` | Agregar un recurso **Archivo** (sube el archivo al draft area primero) |
| `foro.py` | `MoodleForoWS` | `forum` | Agregar una actividad **Foro** |
| `tarea.py` | `MoodleTareaWS` | `assign` | Agregar una actividad **Tarea** |
| `quiz.py` | `MoodleQuizWS` | `quiz` | Agregar el contenedor de un **Cuestionario** (las preguntas se cargan aparte) |
| `h5p.py` | `MoodleH5pWS` | `h5pactivity` | Agregar un **Paquete interactivo de contenido H5P** (sube el `.h5p` al draft area primero) |
| `seccion.py` | `MoodleSeccionWS` | secciones (no es un módulo) | Crear/editar/eliminar una **sección** del curso (nombre + descripción) |
| `base.py` | `MoodleRecursoWS` | — | No simula nada por sí solo: código común (subida de archivos, armado de `options`, detección de errores) que heredan los 6 tipos de arriba |

## Documentación

- **[INSTALACION.md](README_INSTALACION.md)** — instalar el plugin, habilitar Web
  Services, generar el token y asignar permisos.
- **[API.md](README_GENERACION_API.md)** — funciones expuestas, formatos de datos, versión del
  plugin y uso del cliente Python.
- **[BUILD.md](README_BUILD.md)** — cómo y cuándo empaquetar el plugin
  (`build_plugin_zip.py`) y dónde queda el `.zip` resultante (`dist/`).
