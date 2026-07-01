# Instalación — local_mod

Guía para instalar y dejar operativo el plugin `local_mod` en un sitio Moodle.

## Requisitos

- Moodle **4.1 o superior** (`$plugin->requires = 2022112800`).
- Acceso de administrador al sitio.

## 1. Instalar el plugin

**Opción A — interfaz web:**
*Administración del sitio → Plugins → Instalar plugins*, sube `local_mod.zip` y
confirma la instalación.

**Opción B — manual:**
Copia la carpeta del plugin dentro de `moodle_root/local/` **renombrada a
`mod`** (debe quedar `moodle_root/local/mod/`, no `moodle_root/local/local_mod/`)
y entra a *Administración del sitio → Notificaciones* para que Moodle detecte
y complete la instalación.

### Cómo generar el .zip correcto para la Opción A

Moodle identifica el plugin por el nombre de la carpeta dentro del zip, que
debe coincidir con el "frankenstyle" del componente **sin el prefijo del
tipo**: el componente es `local_mod` (tipo `local`, nombre `mod`), así que la
carpeta raíz dentro del zip debe llamarse **`mod`**, no `local_mod`. Si el
zip trae la carpeta como `local_mod/`, el instalador de plugins la rechaza o
la instala con el nombre equivocado.

En este repo el código vive en la carpeta `local_mod/`, así que hay que
renombrarla a `mod` al comprimir. No comprimas la carpeta `local_mod/` tal
cual.

**PowerShell (Windows):**

```powershell
Copy-Item -Recurse -Force local_mod mod
Compress-Archive -Path mod -DestinationPath local_mod.zip -Force
Remove-Item -Recurse -Force mod
```

**Bash / Git Bash:**

```bash
cp -r local_mod mod
zip -r local_mod.zip mod
rm -rf mod
```

Verifica el resultado antes de subirlo — la primera entrada del listado debe
ser `mod/`, no `local_mod/`:

```bash
unzip -l local_mod.zip | head
```

> El `local_mod.zip` que ya está en el repo fue generado comprimiendo
> `local_mod/` directamente (carpeta raíz `local_mod/` dentro del zip) — está
> mal para la Opción A y conviene regenerarlo con los comandos de arriba antes
> de subirlo por la interfaz. Para la Opción B (copiar archivos a mano) no
> afecta, porque ahí el nombre de carpeta se controla al copiar.

## 2. Habilitar Web Services

*Administración del sitio → Servidor → Servicios web*:

1. Activa **Habilitar servicios web**.
2. Activa el protocolo **REST** en *Administrar protocolos*.

## 3. Servicio y token

1. *Servicios externos*: verás **Mod Management** (lo crea el propio plugin).
   Confirma que esté habilitado.
2. *Usuarios autorizados*: agrega el usuario que va a hacer las llamadas al
   servicio.
3. *Gestionar tokens*: genera un token para ese usuario + el servicio
   **Mod Management**.

## 4. Permisos (capabilities)

En cada curso donde se vaya a usar el web service, el usuario del token
necesita:

| Capability | Para qué |
|---|---|
| `moodle/course:manageactivities` | Crear/editar/eliminar módulos |
| `mod/<tipo>:addinstance` | Crear cada tipo de módulo (`mod/assign:addinstance`, `mod/quiz:addinstance`, etc.) |
| `moodle/course:update` | Crear/editar/eliminar secciones |
| `moodle/course:sectionvisibility` | Solo si se van a **ocultar** secciones |

## 5. Subida de archivos (resource / h5p)

El servicio **Mod Management** ya se crea con `uploadfiles=1`, así que el
token puede subir archivos a `POST /webservice/upload.php` antes de crear un
`resource` o `h5pactivity` (ver detalle en [API.md](README_GENERACION_API.md#archivos-resource--h5p)).

## Verificación rápida

Con el token generado, prueba una función simple vía REST:

```
POST https://tu-moodle.tld/webservice/rest/server.php
  wstoken=TU_TOKEN
  wsfunction=core_course_get_courses_by_field
  moodlewsrestformat=json
```

Si devuelve JSON con cursos y no un `exception`, el servicio quedó bien
configurado. Para el uso del plugin en sí, sigue con [API.md](README_GENERACION_API.md).
