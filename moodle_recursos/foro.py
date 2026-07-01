"""Cliente simple para foros (mod_forum) via local_mod."""

from typing import Any, Optional

from .base import MoodleRecursoWS


class MoodleForoWS(MoodleRecursoWS):
    """Crear / actualizar / eliminar foros."""

    MODULENAME = "forum"

    def crear(self, courseid: int, section: int, name: str, intro: str = "",
              tipo: str = "general", visible: int = 1) -> Any:
        """tipo: general | news | eachuser | single | qanda | blog."""
        return self._crear(courseid, section, name, intro, visible, options={"type": tipo})

    def actualizar(self, cmid: int, name: Optional[str] = None, intro: Optional[str] = None,
                    tipo: Optional[str] = None, visible: Optional[int] = None) -> Any:
        options = {}
        if tipo is not None:
            options["type"] = tipo
        return self._actualizar(cmid, name, intro, visible, options)

    # eliminar() heredado de MoodleRecursoWS
