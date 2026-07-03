"""
Cliente simple para el banco de preguntas de un quiz via local_mod.

No es un "modulo" (opera por cmid de un quiz ya creado, no crea/actualiza/
elimina el propio quiz), por eso no hereda de MoodleRecursoWS y llama
directo a local_mod_sync_question_bank / local_mod_delete_question_bank.

No expone `categoriaprefijo`: el cliente siempre usa el default del plugin
(`banco_preguntas_{cmid}`, constante helper::CATEGORIA_PREFIJO en PHP).
"""

from typing import Any, Dict, List, Optional

from moodle_webservice import MoodleWebService  # ajusta a tu proyecto


class MoodleBancoPreguntasWS(MoodleWebService):
    """Sincroniza / vacia el banco de preguntas propio de un quiz (por cmid)."""

    @staticmethod
    def es_error(resultado: Any) -> Optional[str]:
        if isinstance(resultado, dict) and "exception" in resultado:
            return resultado.get("message") or resultado.get("errorcode")
        return None

    def sync_question_bank(self, cmid: int, preguntas: List[Dict[str, Any]],
                            aleatorio: int = 0, cantidadaleatorias: int = 0,
                            puntajealeatorio: float = 1) -> Any:
        """Recrea desde cero el banco propio del quiz (`cmid`) con `preguntas`
        y arma los slots: uno fijo por pregunta (maxmark=`puntaje` de cada
        una) si `aleatorio=0`, o `cantidadaleatorias` slots que sortean de la
        categoria (maxmark=`puntajealeatorio`) si `aleatorio=1`.

        Idempotente: vacia el banco anterior (misma categoria propia del
        quiz) antes de recrearlo, asi que sirve tanto para la carga inicial
        como para cualquier edicion posterior — siempre reenvia el banco
        completo actualizado. Falla (`bancoconintentos`) si el quiz ya tiene
        intentos registrados.

        Cada item de `preguntas` es un dict con: `indice` (correlativo propio
        para mapear de vuelta `questionid`/`questionbankentryid`), `qtype`
        (`multichoice`/`truefalse`/`shortanswer`/`numerical`/`essay`),
        `nombre`, `enunciado`, `retroalimentacion`, `puntaje`, y segun el
        tipo: `multiple`, `vfcorrecta`, `usecase`, `essaytextorequerido`,
        `essayadjuntos`, `respuestas` (lista de `{detalle, fraccion,
        tolerancia}` con `fraccion` en porcentaje -100..100). Ver
        README_GENERACION_API.md para el detalle completo.

        Devuelve `{success, categoryid, sumgrades, preguntas: [{indice,
        questionid, questionbankentryid}, ...]}`.
        """
        return self.call(
            "local_mod_sync_question_bank",
            cmid=cmid,
            aleatorio=aleatorio,
            cantidadaleatorias=cantidadaleatorias,
            puntajealeatorio=puntajealeatorio,
            preguntas=preguntas,
        )

    def delete_question_bank(self, cmid: int) -> Any:
        """Vacia el banco propio del quiz (`cmid`): elimina sus preguntas y
        todos los slots (la suma de calificaciones queda en 0). Falla
        (`bancoconintentos`) si el quiz ya tiene intentos registrados.

        Devuelve `{success, eliminadas}`.
        """
        return self.call("local_mod_delete_question_bank", cmid=cmid)
