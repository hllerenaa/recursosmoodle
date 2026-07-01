"""Cliente simple para actividades H5P (mod_h5pactivity) via local_mod."""

from typing import Any, Optional

from .base import MoodleRecursoWS


class MoodleH5pWS(MoodleRecursoWS):
    """Crear / actualizar / eliminar actividades H5P. Sube el .h5p al draft area."""

    MODULENAME = "h5pactivity"

    def crear(self, courseid: int, section: int, name: str, file_obj, filename: str,
              intro: str = "", visible: int = 1) -> Any:
        itemid = self.subir_archivo_draft(file_obj, filename)
        if itemid is None:
            return {"exception": "upload_failed", "message": "No se pudo subir el paquete H5P"}
        return self._crear(
            courseid, section, name, intro, visible,
            options={
                "packagefile": itemid,
                "displayoptions": 0,
                "enabletracking": 1,
                "grademethod": 1,
            },
        )

    def actualizar(self, cmid: int, name: Optional[str] = None, intro: Optional[str] = None,
                    file_obj=None, filename: Optional[str] = None,
                    visible: Optional[int] = None) -> Any:
        """Si pasas `file_obj` + `filename`, reemplaza el paquete H5P."""
        options = {}
        if file_obj is not None and filename:
            itemid = self.subir_archivo_draft(file_obj, filename)
            if itemid is None:
                return {"exception": "upload_failed", "message": "No se pudo subir el paquete H5P"}
            options["packagefile"] = itemid
        return self._actualizar(cmid, name, intro, visible, options)

    # eliminar() heredado de MoodleRecursoWS
