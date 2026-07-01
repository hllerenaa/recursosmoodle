"""
Cliente Python para el plugin local_mod (gestion de modulos vía Web Service).

Extiende tu clase MoodleWebService existente (moodle_webservice.py) y agrega
metodos para crear / actualizar / eliminar actividades y recursos:
    - resource (archivo)   - url          - forum
    - quiz                 - assign       - h5pactivity
    - cualquier otro modulo de forma generica (crear_modulo)

Reemplaza el enfoque anterior por query (INSERT directo en mdl_url / mdl_assign /
mdl_grade_items / mdl_context ...). Aqui Moodle crea el grade_item, el contexto,
la secuencia de la seccion y el modulo por si solo, y es forward-compatible.

Requisitos en Moodle:
    1. Instalar el plugin local_mod.
    2. Habilitar el servicio "ISTER Module Management" y generar un token.
    3. El usuario del token debe tener moodle/course:manageactivities y
       mod/<tipo>:addinstance en los cursos objetivo.
"""

import logging
from typing import Any, Dict, List, Optional

from requests import post

# Ajusta este import a la ruta real de tu proyecto:
from .moodle_webservice import MoodleWebService

logger = logging.getLogger(__name__)


class MoodleModulosWS(MoodleWebService):
    """Extiende MoodleWebService con operaciones sobre modulos de curso."""

    UPLOAD_ENDPOINT = "/webservice/upload.php"

    # ------------------------------------------------------------------ #
    #  Utilidades                                                        #
    # ------------------------------------------------------------------ #
    @staticmethod
    def _opts(options: Optional[Dict[str, Any]]) -> List[Dict[str, str]]:
        """Convierte {campo: valor} -> [{'name': campo, 'value': valor}, ...]."""
        if not options:
            return []
        return [{"name": str(k), "value": str(v)} for k, v in options.items()]

    @staticmethod
    def _es_error(resultado: Any) -> Optional[str]:
        """Devuelve el mensaje si la respuesta de Moodle es una excepcion."""
        if isinstance(resultado, dict) and "exception" in resultado:
            return resultado.get("message") or resultado.get("errorcode")
        return None

    def subir_archivo_draft(self, file_obj, filename: str, itemid: int = 0) -> Optional[int]:
        """
        Sube un archivo al draft area del usuario del token y devuelve el itemid.
        Necesario para resource (files) y h5pactivity (packagefile).

        Args:
            file_obj: objeto tipo file abierto en binario (o request.FILES[...])
            filename: nombre del archivo
            itemid:   0 para crear un draft nuevo
        Returns:
            itemid del draft area, o None si falla.
        """
        url = f"{self.url_base}{self.UPLOAD_ENDPOINT}"
        data = {"token": self.token, "filearea": "draft", "itemid": itemid}
        files = {"file_1": (filename, file_obj)}
        try:
            resp = post(url, data=data, files=files, verify=False)
            resp.raise_for_status()
            result = resp.json()
            if isinstance(result, list) and result:
                return result[0].get("itemid")
            logger.error(f"Respuesta inesperada al subir archivo: {result}")
            return None
        except Exception as e:
            logger.error(f"Error subiendo archivo al draft area: {e}")
            return None

    # ------------------------------------------------------------------ #
    #  CRUD generico                                                     #
    # ------------------------------------------------------------------ #
    def crear_modulo(self, courseid: int, section: int, modulename: str, name: str,
                     intro: str = "", visible: int = 1,
                     options: Optional[Dict[str, Any]] = None) -> Any:
        """Crea cualquier modulo. `options` = campos especificos del tipo."""
        return self.call(
            "local_mod_create_module",
            courseid=courseid,
            section=section,
            modulename=modulename,
            name=name,
            intro=intro,
            visible=visible,
            options=self._opts(options),
        )

    def actualizar_modulo(self, cmid: int, name: Optional[str] = None,
                          intro: Optional[str] = None, visible: Optional[int] = None,
                          options: Optional[Dict[str, Any]] = None) -> Any:
        """Actualiza un modulo por cmid. Solo cambia lo que envies."""
        kwargs: Dict[str, Any] = {"cmid": cmid, "options": self._opts(options)}
        if name is not None:
            kwargs["name"] = name
        if intro is not None:
            kwargs["intro"] = intro
        if visible is not None:
            kwargs["visible"] = visible
        return self.call("local_mod_update_module", **kwargs)

    def eliminar_modulo(self, cmid: int) -> Any:
        """Elimina un modulo por cmid."""
        return self.call("local_mod_delete_module", cmid=cmid)

    # ------------------------------------------------------------------ #
    #  Atajos por tipo de modulo                                         #
    # ------------------------------------------------------------------ #
    def crear_url(self, courseid: int, section: int, name: str, externalurl: str,
                  intro: str = "", display: int = 0, visible: int = 1) -> Any:
        """Recurso tipo URL (mod_url) — equivalente a tu INSERT INTO mdl_url."""
        return self.crear_modulo(
            courseid, section, "url", name, intro, visible,
            options={"externalurl": externalurl, "display": display},
        )

    def crear_recurso_archivo(self, courseid: int, section: int, name: str,
                              file_obj, filename: str, intro: str = "",
                              display: int = 0, visible: int = 1) -> Any:
        """Recurso tipo archivo (mod_resource). Sube el fichero al draft area primero."""
        itemid = self.subir_archivo_draft(file_obj, filename)
        if itemid is None:
            return {"exception": "upload_failed", "message": "No se pudo subir el archivo"}
        return self.crear_modulo(
            courseid, section, "resource", name, intro, visible,
            options={"files": itemid, "display": display},
        )

    def crear_foro(self, courseid: int, section: int, name: str,
                   intro: str = "", tipo: str = "general", visible: int = 1) -> Any:
        """Foro (mod_forum). tipo: general | news | eachuser | single | qanda | blog."""
        return self.crear_modulo(
            courseid, section, "forum", name, intro, visible,
            options={"type": tipo},
        )

    def crear_tarea(self, courseid: int, section: int, name: str, intro: str = "",
                    duedate: int = 0, allowsubmissionsfromdate: int = 0,
                    grade: int = 100, visible: int = 1,
                    extra: Optional[Dict[str, Any]] = None) -> Any:
        """Tarea (mod_assign). Fechas en timestamp Unix. `extra` para plugins de entrega."""
        opts: Dict[str, Any] = {
            "duedate": duedate,
            "allowsubmissionsfromdate": allowsubmissionsfromdate,
            "grade": grade,
            "submissiondrafts": 0,
            "requiresubmissionstatement": 0,
            "assignsubmission_onlinetext_enabled": 1,
            "assignsubmission_file_enabled": 1,
            "assignsubmission_file_maxfiles": 1,
            "assignsubmission_file_maxsizebytes": 0,
        }
        if extra:
            opts.update(extra)
        return self.crear_modulo(courseid, section, "assign", name, intro, visible, options=opts)

    def crear_quiz(self, courseid: int, section: int, name: str, intro: str = "",
                   grade: int = 10, timeopen: int = 0, timeclose: int = 0,
                   visible: int = 1, extra: Optional[Dict[str, Any]] = None) -> Any:
        """Test (mod_quiz). Las preguntas se agregan aparte, este crea el contenedor."""
        opts: Dict[str, Any] = {
            "grade": grade,
            "timeopen": timeopen,
            "timeclose": timeclose,
            "grademethod": 1,          # 1=calificacion mas alta
            "questionsperpage": 1,
            "preferredbehaviour": "deferredfeedback",
        }
        if extra:
            opts.update(extra)
        return self.crear_modulo(courseid, section, "quiz", name, intro, visible, options=opts)

    def crear_h5p(self, courseid: int, section: int, name: str,
                  file_obj, filename: str, intro: str = "", visible: int = 1) -> Any:
        """Actividad H5P (mod_h5pactivity). Sube el .h5p al draft area primero."""
        itemid = self.subir_archivo_draft(file_obj, filename)
        if itemid is None:
            return {"exception": "upload_failed", "message": "No se pudo subir el paquete H5P"}
        return self.crear_modulo(
            courseid, section, "h5pactivity", name, intro, visible,
            options={
                "packagefile": itemid,
                "displayoptions": 0,
                "enabletracking": 1,
                "grademethod": 1,
            },
        )

    # ================================================================== #
    #  SECCIONES: CRUD                                                   #
    # ================================================================== #
    def crear_seccion(self, courseid: int, position: int = 0,
                      name: Optional[str] = None, summary: Optional[str] = None,
                      visible: int = 1) -> Any:
        """
        Crea una seccion. `position=0` la agrega al final.
        `summary` es la descripcion de la seccion (HTML).
        """
        kwargs: Dict[str, Any] = {"courseid": courseid, "position": position, "visible": visible}
        if name is not None:
            kwargs["name"] = name
        if summary is not None:
            kwargs["summary"] = summary
        return self.call("local_mod_create_section", **kwargs)

    def actualizar_seccion(self, courseid: int, sectionnumber: int,
                           name: Optional[str] = None, summary: Optional[str] = None,
                           visible: Optional[int] = None) -> Any:
        """
        Actualiza nombre, descripcion (summary) y/o visibilidad de una seccion.
        Solo cambia lo que envies.
        """
        kwargs: Dict[str, Any] = {"courseid": courseid, "sectionnumber": sectionnumber}
        if name is not None:
            kwargs["name"] = name
        if summary is not None:
            kwargs["summary"] = summary
        if visible is not None:
            kwargs["visible"] = visible
        return self.call("local_mod_update_section", **kwargs)

    def eliminar_seccion(self, courseid: int, sectionnumber: int, force: int = 0) -> Any:
        """
        Elimina una seccion. `force=1` la elimina aunque contenga actividades.
        No se puede eliminar la seccion 0.
        """
        return self.call(
            "local_mod_delete_section",
            courseid=courseid,
            sectionnumber=sectionnumber,
            force=force,
        )


# ---------------------------------------------------------------------- #
#  Ejemplo de uso                                                        #
# ---------------------------------------------------------------------- #
if __name__ == "__main__":
    # NOTA: en produccion usa tu get_moodle_ws() / ConfiguracionMoodle.
    ws = MoodleModulosWS(
        url_base="https://evapreg.ister.edu.ec",
        token="TU_TOKEN_DEL_SERVICIO_MOD",
        tipo_moodle=1,
    )

    import time
    COURSE_ID = 123

    # 0) Seccion (con descripcion / summary)
    r = ws.crear_seccion(COURSE_ID, name="Unidad 1", summary="<p>Fundamentos.</p>")
    print("seccion:", r)
    SECCION = r.get("sectionnumber", 1) if isinstance(r, dict) else 1
    ws.actualizar_seccion(COURSE_ID, SECCION, summary="<p>Descripcion actualizada.</p>")
    # ws.eliminar_seccion(COURSE_ID, SECCION, force=1)

    # 1) URL
    print(ws.crear_url(COURSE_ID, SECCION, "Guia de laboratorio",
                       "https://ejemplo.com/guia.pdf", intro="Material de apoyo"))

    # 2) Foro
    print(ws.crear_foro(COURSE_ID, SECCION, "Foro de dudas", tipo="general"))

    # 3) Tarea con fecha limite
    limite = int(time.time()) + 7 * 24 * 3600
    print(ws.crear_tarea(COURSE_ID, SECCION, "Entrega Unidad 1",
                         intro="Sube tu documento", duedate=limite, grade=20))

    # 4) Test (contenedor de quiz)
    print(ws.crear_quiz(COURSE_ID, SECCION, "Evaluacion parcial", grade=20))

    # 5) H5P (requiere archivo .h5p)
    # with open("actividad.h5p", "rb") as fh:
    #     print(ws.crear_h5p(COURSE_ID, SECCION, "Interactivo U1", fh, "actividad.h5p"))

    # 6) Recurso archivo (mod_resource)
    # with open("silabo.pdf", "rb") as fh:
    #     print(ws.crear_recurso_archivo(COURSE_ID, SECCION, "Silabo", fh, "silabo.pdf"))

    # 7) Actualizar (por cmid devuelto en 'cmid')
    # print(ws.actualizar_modulo(cmid=456, name="Foro de dudas (actualizado)"))

    # 8) Eliminar
    # print(ws.eliminar_modulo(cmid=456))
