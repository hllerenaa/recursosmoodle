# local_mod — Gestión de módulos y secciones Moodle por Web Service

Plugin `local` que expone crear / actualizar / eliminar **actividades, recursos y
secciones** de un curso vía Web Service. Reemplaza el enfoque anterior por `INSERT`
directo en la BD (`mdl_url`, `mdl_assign`, `mdl_grade_items`, `mdl_context`,
`mdl_course_sections`…).

Cubre: `resource` (archivo), `url`, `forum`, `quiz`, `assign`, `h5pactivity` y
cualquier otro módulo de forma genérica; más el CRUD de secciones (con descripción).

**Compatible Moodle 4.1 en adelante.** No toca tablas directamente: envuelve las
funciones internas de Moodle, las mismas que usa la interfaz.

## Documentación

- **[INSTALACION.md](README_INSTALACION.md)** — instalar el plugin, habilitar Web
  Services, generar el token y asignar permisos.
- **[API.md](README_GENERACION_API.md)** — funciones expuestas, formatos de datos, versión del
  plugin y uso del cliente Python.
