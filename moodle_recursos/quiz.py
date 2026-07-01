"""Cliente simple para tests (mod_quiz) via local_mod. Solo crea el contenedor;
las preguntas se agregan aparte (qbank)."""

from typing import Any, Dict, Optional

from .base import MoodleRecursoWS


class MoodleQuizWS(MoodleRecursoWS):
    """Crear / actualizar / eliminar tests (contenedor de quiz)."""

    MODULENAME = "quiz"

    def crear(self, courseid: int, section: int, name: str, intro: str = "",
              grade: int = 10, timeopen: int = 0, timeclose: int = 0,
              visible: int = 1, extra: Optional[Dict[str, Any]] = None) -> Any:
        options: Dict[str, Any] = {
            "grade": grade,
            "timeopen": timeopen,
            "timeclose": timeclose,
            "grademethod": 1,           # 1 = calificacion mas alta
            "questionsperpage": 1,
            "preferredbehaviour": "deferredfeedback",
        }
        if extra:
            options.update(extra)
        return self._crear(courseid, section, name, intro, visible, options=options)

    def actualizar(self, cmid: int, name: Optional[str] = None, intro: Optional[str] = None,
                    grade: Optional[int] = None, timeopen: Optional[int] = None,
                    timeclose: Optional[int] = None, visible: Optional[int] = None,
                    extra: Optional[Dict[str, Any]] = None) -> Any:
        options: Dict[str, Any] = {}
        if grade is not None:
            options["grade"] = grade
        if timeopen is not None:
            options["timeopen"] = timeopen
        if timeclose is not None:
            options["timeclose"] = timeclose
        if extra:
            options.update(extra)
        return self._actualizar(cmid, name, intro, visible, options)

    # eliminar() heredado de MoodleRecursoWS
