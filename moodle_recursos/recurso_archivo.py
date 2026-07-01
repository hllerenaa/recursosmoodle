"""Cliente simple para recursos tipo archivo (mod_resource) via local_mod."""

from typing import Any, Optional

from .base import MoodleRecursoWS


class MoodleRecursoArchivoWS(MoodleRecursoWS):
    """Crear / actualizar / eliminar recursos tipo archivo. Sube el fichero al draft area."""

    MODULENAME = "resource"

    def crear(self, courseid: int, section: int, name: str, file_obj, filename: str,
              intro: str = "", display: int = 0, visible: int = 1) -> Any:
        itemid = self.subir_archivo_draft(file_obj, filename)
        if itemid is None:
            return {"exception": "upload_failed", "message": "No se pudo subir el archivo"}
        return self._crear(
            courseid, section, name, intro, visible,
            options={"files": itemid, "display": display},
        )

    def actualizar(self, cmid: int, name: Optional[str] = None, intro: Optional[str] = None,
                    file_obj=None, filename: Optional[str] = None,
                    display: Optional[int] = None, visible: Optional[int] = None) -> Any:
        """Si pasas `file_obj` + `filename`, reemplaza el archivo del recurso."""
        options = {}
        if file_obj is not None and filename:
            itemid = self.subir_archivo_draft(file_obj, filename)
            if itemid is None:
                return {"exception": "upload_failed", "message": "No se pudo subir el archivo"}
            options["files"] = itemid
        if display is not None:
            options["display"] = display
        return self._actualizar(cmid, name, intro, visible, options)

    # eliminar() heredado de MoodleRecursoWS
