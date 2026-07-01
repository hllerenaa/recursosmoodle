"""
Base comun para los clientes de recursos por tipo del plugin local_mod.

Cada tipo de recurso (url, archivo, foro, tarea, quiz, h5p) tiene su propio
cliente en este paquete, mas simple que el generico `moodle_modulos_ws.py`:
solo expone crear() / actualizar() / eliminar() con los campos propios de
ese tipo.

Ajusta el import de MoodleWebService a la ruta real de tu proyecto.
"""

import logging
from typing import Any, Dict, List, Optional

from requests import post

from ..moodle_webservice import MoodleWebService  # ajusta a tu proyecto

logger = logging.getLogger(__name__)


class MoodleRecursoWS(MoodleWebService):
    """Base con lo comun a todos los recursos: opts, errores y subida de archivos."""

    UPLOAD_ENDPOINT = "/webservice/upload.php"
    MODULENAME: str = ""  # cada subclase define su tipo: "url", "resource", "forum"...

    @staticmethod
    def _opts(options: Optional[Dict[str, Any]]) -> List[Dict[str, str]]:
        if not options:
            return []
        return [{"name": str(k), "value": str(v)} for k, v in options.items()]

    @staticmethod
    def es_error(resultado: Any) -> Optional[str]:
        """Devuelve el mensaje de error si la respuesta de Moodle es una excepcion."""
        if isinstance(resultado, dict) and "exception" in resultado:
            return resultado.get("message") or resultado.get("errorcode")
        return None

    def subir_archivo_draft(self, file_obj, filename: str, itemid: int = 0) -> Optional[int]:
        """Sube un archivo al draft area del usuario del token y devuelve el itemid."""
        url = f"{self.url_base}{self.UPLOAD_ENDPOINT}"
        data = {"token": self.token, "filearea": "draft", "itemid": itemid}
        files = {"file_1": (filename, file_obj)}
        try:
            resp = post(url, data=data, files=files, verify=False)
            resp.raise_for_status()
            result = resp.json()
            if isinstance(result, list) and result:
                return result[0].get("itemid")
            logger.error(f"Respuesta inesperada al subir archivo: {result}")
            return None
        except Exception as e:
            logger.error(f"Error subiendo archivo al draft area: {e}")
            return None

    def _crear(self, courseid: int, section: int, name: str, intro: str,
               visible: int, options: Dict[str, Any]) -> Any:
        return self.call(
            "local_mod_create_module",
            courseid=courseid,
            section=section,
            modulename=self.MODULENAME,
            name=name,
            intro=intro,
            visible=visible,
            options=self._opts(options),
        )

    def _actualizar(self, cmid: int, name: Optional[str], intro: Optional[str],
                     visible: Optional[int], options: Dict[str, Any]) -> Any:
        kwargs: Dict[str, Any] = {"cmid": cmid, "options": self._opts(options)}
        if name is not None:
            kwargs["name"] = name
        if intro is not None:
            kwargs["intro"] = intro
        if visible is not None:
            kwargs["visible"] = visible
        return self.call("local_mod_update_module", **kwargs)

    def eliminar(self, cmid: int) -> Any:
        """Elimina el recurso/actividad por cmid (comun a todos los tipos)."""
        return self.call("local_mod_delete_module", cmid=cmid)
