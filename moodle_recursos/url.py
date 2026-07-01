"""Cliente simple para recursos tipo URL (mod_url) via local_mod."""

from typing import Any, Optional

from .base import MoodleRecursoWS


class MoodleUrlWS(MoodleRecursoWS):
    """Crear / actualizar / eliminar recursos tipo URL."""

    MODULENAME = "url"

    def crear(self, courseid: int, section: int, name: str, externalurl: str,
              intro: str = "", display: int = 0, visible: int = 1) -> Any:
        return self._crear(
            courseid, section, name, intro, visible,
            options={"externalurl": externalurl, "display": display},
        )

    def actualizar(self, cmid: int, name: Optional[str] = None,
                    externalurl: Optional[str] = None, intro: Optional[str] = None,
                    display: Optional[int] = None, visible: Optional[int] = None) -> Any:
        options = {}
        if externalurl is not None:
            options["externalurl"] = externalurl
        if display is not None:
            options["display"] = display
        return self._actualizar(cmid, name, intro, visible, options)

    # eliminar() heredado de MoodleRecursoWS
