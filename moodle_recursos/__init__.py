"""
Clientes simples por tipo de recurso para el plugin local_mod.

Cada clase expone unicamente crear() / actualizar() / eliminar() para su
tipo, en vez de un cliente generico con un metodo por tipo (ver
`moodle_modulos_ws.py` en la raiz para la version todo-en-uno).

Uso:
    from moodle_recursos import MoodleUrlWS, MoodleTareaWS, MoodleSeccionWS

    ws = MoodleUrlWS(url_base="https://tu-moodle.tld", token="TOKEN", tipo_moodle=1)
    ws.crear(courseid=123, section=1, name="Guia", externalurl="https://...")
"""

from .base import MoodleRecursoWS
from .foro import MoodleForoWS
from .h5p import MoodleH5pWS
from .questionbank import MoodleBancoPreguntasWS
from .quiz import MoodleQuizWS
from .recurso_archivo import MoodleRecursoArchivoWS
from .seccion import MoodleSeccionWS
from .tarea import MoodleTareaWS
from .url import MoodleUrlWS

__all__ = [
    "MoodleRecursoWS",
    "MoodleUrlWS",
    "MoodleRecursoArchivoWS",
    "MoodleForoWS",
    "MoodleTareaWS",
    "MoodleQuizWS",
    "MoodleH5pWS",
    "MoodleSeccionWS",
    "MoodleBancoPreguntasWS",
]
