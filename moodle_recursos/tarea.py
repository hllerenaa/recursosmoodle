"""Cliente simple para tareas (mod_assign) via local_mod."""

from typing import Any, Dict, Optional

from .base import MoodleRecursoWS


class MoodleTareaWS(MoodleRecursoWS):
    """Crear / actualizar / eliminar tareas. Fechas en timestamp Unix."""

    MODULENAME = "assign"

    def crear(self, courseid: int, section: int, name: str, intro: str = "",
              duedate: int = 0, allowsubmissionsfromdate: int = 0,
              grade: int = 100, visible: int = 1,
              extra: Optional[Dict[str, Any]] = None) -> Any:
        """`extra` permite sobreescribir/agregar campos de assignsubmission_*."""
        options: Dict[str, Any] = {
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
            options.update(extra)
        return self._crear(courseid, section, name, intro, visible, options=options)

    def actualizar(self, cmid: int, name: Optional[str] = None, intro: Optional[str] = None,
                    duedate: Optional[int] = None, allowsubmissionsfromdate: Optional[int] = None,
                    grade: Optional[int] = None, visible: Optional[int] = None,
                    extra: Optional[Dict[str, Any]] = None) -> Any:
        options: Dict[str, Any] = {}
        if duedate is not None:
            options["duedate"] = duedate
        if allowsubmissionsfromdate is not None:
            options["allowsubmissionsfromdate"] = allowsubmissionsfromdate
        if grade is not None:
            options["grade"] = grade
        if extra:
            options.update(extra)
        return self._actualizar(cmid, name, intro, visible, options)

    # eliminar() heredado de MoodleRecursoWS
