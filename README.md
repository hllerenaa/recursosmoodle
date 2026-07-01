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
