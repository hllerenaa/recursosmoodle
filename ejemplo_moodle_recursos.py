"""
Ejemplo de uso de moodle_recursos/: crea una seccion y, dentro de ella, una
actividad/recurso de cada tipo soportado por local_mod (url, archivo, foro,
tarea, quiz, h5p). Tambien muestra actualizar() y eliminar() de cada clase.

Ajusta URL_BASE, TOKEN y COURSE_ID a tu Moodle antes de correrlo.

Uso:
    python ejemplo_moodle_recursos.py
"""

import time

from moodle_recursos import (
    MoodleBancoPreguntasWS,
    MoodleForoWS,
    MoodleH5pWS,
    MoodleQuizWS,
    MoodleRecursoArchivoWS,
    MoodleSeccionWS,
    MoodleTareaWS,
    MoodleUrlWS,
)

URL_BASE = "https://evapreg.ister.edu.ec"
TOKEN = "TU_TOKEN_DEL_SERVICIO_MOD_MGMT"
COURSE_ID = 123


def mostrar(etiqueta, resultado):
    error = None
    if isinstance(resultado, dict) and "exception" in resultado:
        error = resultado.get("message") or resultado.get("errorcode")
    estado = f"ERROR: {error}" if error else "OK"
    print(f"[{estado}] {etiqueta}: {resultado}")
    return resultado


def main():
    kwargs = {"url_base": URL_BASE, "token": TOKEN, "tipo_moodle": 1}

    # ------------------------------------------------------------------ #
    #  Seccion (contenedor donde va todo lo demas)                       #
    # ------------------------------------------------------------------ #
    seccion_ws = MoodleSeccionWS(**kwargs)
    r = mostrar("crear seccion", seccion_ws.crear(
        COURSE_ID, name="Unidad de ejemplo", summary="<p>Creada por el script de ejemplo.</p>",
    ))
    sec = r.get("sectionnumber", 1) if isinstance(r, dict) else 1

    mostrar("actualizar seccion", seccion_ws.actualizar(
        COURSE_ID, sec, summary="<p>Descripcion actualizada.</p>",
    ))

    # ------------------------------------------------------------------ #
    #  URL                                                                #
    # ------------------------------------------------------------------ #
    url_ws = MoodleUrlWS(**kwargs)
    r = mostrar("crear url", url_ws.crear(
        COURSE_ID, sec, "Guia de laboratorio", "https://ejemplo.com/guia.pdf",
        intro="Material de apoyo",
    ))
    cmid_url = r.get("cmid") if isinstance(r, dict) else None
    if cmid_url:
        mostrar("actualizar url", url_ws.actualizar(cmid_url, name="Guia de laboratorio (v2)"))

    # ------------------------------------------------------------------ #
    #  Foro                                                               #
    # ------------------------------------------------------------------ #
    foro_ws = MoodleForoWS(**kwargs)
    r = mostrar("crear foro", foro_ws.crear(COURSE_ID, sec, "Foro de dudas", tipo="general"))
    cmid_foro = r.get("cmid") if isinstance(r, dict) else None
    if cmid_foro:
        mostrar("actualizar foro", foro_ws.actualizar(cmid_foro, name="Foro de dudas (actualizado)"))

    # ------------------------------------------------------------------ #
    #  Tarea (assign)                                                     #
    # ------------------------------------------------------------------ #
    tarea_ws = MoodleTareaWS(**kwargs)
    limite = int(time.time()) + 7 * 24 * 3600
    r = mostrar("crear tarea", tarea_ws.crear(
        COURSE_ID, sec, "Entrega Unidad 1", intro="Sube tu documento",
        duedate=limite, grade=20,
    ))
    cmid_tarea = r.get("cmid") if isinstance(r, dict) else None
    if cmid_tarea:
        mostrar("actualizar tarea", tarea_ws.actualizar(cmid_tarea, grade=25))

    # ------------------------------------------------------------------ #
    #  Quiz (contenedor, sin preguntas)                                   #
    # ------------------------------------------------------------------ #
    quiz_ws = MoodleQuizWS(**kwargs)
    r = mostrar("crear quiz", quiz_ws.crear(COURSE_ID, sec, "Evaluacion parcial", grade=20))
    cmid_quiz = r.get("cmid") if isinstance(r, dict) else None
    if cmid_quiz:
        mostrar("actualizar quiz", quiz_ws.actualizar(cmid_quiz, grade=30))

    # ------------------------------------------------------------------ #
    #  Banco de preguntas del quiz (sync + vaciar)                       #
    # ------------------------------------------------------------------ #
    banco_ws = MoodleBancoPreguntasWS(**kwargs)
    if cmid_quiz:
        preguntas = [
            {
                "indice": 1,
                "qtype": "multichoice",
                "nombre": "Capital de Ecuador",
                "enunciado": "<p>Cual es la capital de Ecuador?</p>",
                "retroalimentacion": "Quito es la capital.",
                "puntaje": 1,
                "multiple": 0,
                "respuestas": [
                    {"detalle": "Quito", "fraccion": 100},
                    {"detalle": "Guayaquil", "fraccion": 0},
                    {"detalle": "Cuenca", "fraccion": 0},
                ],
            },
            {
                "indice": 2,
                "qtype": "truefalse",
                "nombre": "Ecuador esta en Sudamerica",
                "enunciado": "<p>Ecuador esta ubicado en Sudamerica.</p>",
                "puntaje": 1,
                "vfcorrecta": 1,
            },
            {
                "indice": 3,
                "qtype": "essay",
                "nombre": "Reflexion Unidad 1",
                "enunciado": "<p>Describe en tus palabras lo aprendido en la Unidad 1.</p>",
                "retroalimentacion": "Se evalua manualmente.",
                "puntaje": 5,
                "essaytextorequerido": 1,
                "essayadjuntos": 0,
            },
        ]
        r = mostrar("sincronizar banco de preguntas", banco_ws.sync_question_bank(
            cmid_quiz, preguntas,
        ))
        # Reintentar con el mismo banco es seguro (idempotente): vacia y recrea.
        # r = mostrar("resincronizar banco de preguntas", banco_ws.sync_question_bank(
        #     cmid_quiz, preguntas,
        # ))
        mostrar("vaciar banco de preguntas", banco_ws.delete_question_bank(cmid_quiz))

    # ------------------------------------------------------------------ #
    #  Recurso archivo (requiere un archivo local de ejemplo)             #
    # ------------------------------------------------------------------ #
    archivo_ws = MoodleRecursoArchivoWS(**kwargs)
    # with open("silabo.pdf", "rb") as fh:
    #     r = mostrar("crear recurso archivo", archivo_ws.crear(
    #         COURSE_ID, sec, "Silabo", fh, "silabo.pdf",
    #     ))
    #     cmid_archivo = r.get("cmid") if isinstance(r, dict) else None
    #     if cmid_archivo:
    #         mostrar("eliminar recurso archivo", archivo_ws.eliminar(cmid_archivo))

    # ------------------------------------------------------------------ #
    #  H5P (requiere un paquete .h5p local de ejemplo)                    #
    # ------------------------------------------------------------------ #
    h5p_ws = MoodleH5pWS(**kwargs)
    # with open("actividad.h5p", "rb") as fh:
    #     r = mostrar("crear h5p", h5p_ws.crear(COURSE_ID, sec, "Interactivo U1", fh, "actividad.h5p"))
    #     cmid_h5p = r.get("cmid") if isinstance(r, dict) else None
    #     if cmid_h5p:
    #         mostrar("eliminar h5p", h5p_ws.eliminar(cmid_h5p))

    # ------------------------------------------------------------------ #
    #  Eliminar lo creado (comentado para no borrar de entrada)           #
    # ------------------------------------------------------------------ #
    # if cmid_url:
    #     mostrar("eliminar url", url_ws.eliminar(cmid_url))
    # if cmid_foro:
    #     mostrar("eliminar foro", foro_ws.eliminar(cmid_foro))
    # if cmid_tarea:
    #     mostrar("eliminar tarea", tarea_ws.eliminar(cmid_tarea))
    # if cmid_quiz:
    #     mostrar("eliminar quiz", quiz_ws.eliminar(cmid_quiz))
    # mostrar("eliminar seccion", seccion_ws.eliminar(COURSE_ID, sec, force=1))


if __name__ == "__main__":
    main()
