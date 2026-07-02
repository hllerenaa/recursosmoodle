"""
Cliente simple para secciones de curso via local_mod.

No es un "modulo" (no tiene cmid ni modulename), por eso no hereda de
MoodleRecursoWS y llama directo a local_mod_create_section /
local_mod_update_section / local_mod_delete_section.
"""

from typing import Any, Optional

from moodle_webservice import MoodleWebService  # ajusta a tu proyecto


class MoodleSeccionWS(MoodleWebService):
    """Crear / actualizar / eliminar secciones de un curso."""

    @staticmethod
    def es_error(resultado: Any) -> Optional[str]:
        if isinstance(resultado, dict) and "exception" in resultado:
            return resultado.get("message") or resultado.get("errorcode")
        return None

    def crear(self, courseid: int, position: int = 0, name: Optional[str] = None,
              summary: Optional[str] = None, visible: int = 1) -> Any:
        """`position=0` agrega la seccion al final; `position=N` crea/reutiliza
        la seccion N como numero absoluto (permite migrar fuera de orden, ej.
        1, 10, 2, 4, 3, sin desplazar secciones ya creadas). `summary` es la
        descripcion (HTML)."""
        kwargs = {"courseid": courseid, "position": position, "visible": visible}
        if name is not None:
            kwargs["name"] = name
        if summary is not None:
            kwargs["summary"] = summary
        return self.call("local_mod_create_section", **kwargs)

    def actualizar(self, courseid: int, sectionnumber: int, name: Optional[str] = None,
                    summary: Optional[str] = None, visible: Optional[int] = None) -> Any:
        kwargs = {"courseid": courseid, "sectionnumber": sectionnumber}
        if name is not None:
            kwargs["name"] = name
        if summary is not None:
            kwargs["summary"] = summary
        if visible is not None:
            kwargs["visible"] = visible
        return self.call("local_mod_update_section", **kwargs)

    def eliminar(self, courseid: int, sectionnumber: int, force: int = 0) -> Any:
        """`force=1` elimina la seccion aunque tenga actividades. No se puede borrar la 0."""
        return self.call(
            "local_mod_delete_section",
            courseid=courseid,
            sectionnumber=sectionnumber,
            force=force,
        )
