"""
Cliente REST minimo para Moodle Web Services (protocolo REST + JSON).

Es la clase base de la que heredan los clientes en moodle_recursos/. Si tu
proyecto ya tiene su propia clase MoodleWebService (con reintentos, logging,
pool de conexiones, etc.), borra este archivo y ajusta el import en
moodle_recursos/base.py y moodle_recursos/seccion.py a la ruta real.
"""

import logging
from typing import Any

import requests

logger = logging.getLogger(__name__)


class MoodleWebService:
    """Llama funciones de Moodle vía /webservice/rest/server.php."""

    REST_ENDPOINT = "/webservice/rest/server.php"

    def __init__(self, url_base: str, token: str, tipo_moodle: int = 1, verify_ssl: bool = True):
        self.url_base = url_base.rstrip("/")
        self.token = token
        self.tipo_moodle = tipo_moodle
        self.verify_ssl = verify_ssl

    def call(self, wsfunction: str, **params) -> Any:
        """Ejecuta wsfunction con params y devuelve el JSON ya decodificado."""
        url = f"{self.url_base}{self.REST_ENDPOINT}"
        data = {
            "wstoken": self.token,
            "wsfunction": wsfunction,
            "moodlewsrestformat": "json",
        }
        data.update(self._aplanar(params))

        resp = requests.post(url, data=data, verify=self.verify_ssl)
        resp.raise_for_status()
        return resp.json()

    @classmethod
    def _aplanar(cls, valor: Any, prefijo: str = "") -> dict:
        """
        Convierte dicts/listas anidadas al formato de arrays de PHP que
        espera el REST de Moodle: options[0][name]=x&options[0][value]=y
        """
        resultado = {}
        if isinstance(valor, dict):
            for clave, sub in valor.items():
                nueva_clave = f"{prefijo}[{clave}]" if prefijo else str(clave)
                resultado.update(cls._aplanar(sub, nueva_clave))
        elif isinstance(valor, (list, tuple)):
            for i, sub in enumerate(valor):
                nueva_clave = f"{prefijo}[{i}]"
                resultado.update(cls._aplanar(sub, nueva_clave))
        else:
            resultado[prefijo] = "" if valor is None else valor
        return resultado
