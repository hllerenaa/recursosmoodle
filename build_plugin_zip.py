"""
Empaqueta el plugin local_mod en un .zip listo para instalar por la
interfaz de Moodle (Administracion del sitio > Plugins > Instalar plugins).

Moodle identifica el plugin por el nombre de carpeta dentro del zip, que debe
coincidir con el "frankenstyle" del componente SIN el prefijo del tipo: el
componente es local_mod (tipo "local", nombre "mod"), asi que la carpeta raiz
dentro del zip debe llamarse "mod", no "local_mod". Por eso este script
renombra la carpeta al comprimir en vez de zipear local_mod/ tal cual.

El nombre del .zip incluye la version de version.php (ej: local_mod-1.1.0.zip)
para no confundir builds de distintas versiones; usa --output para fijar un
nombre propio.

Uso:
    python build_plugin_zip.py
    python build_plugin_zip.py --source local_mod --output build/local_mod.zip
"""

import argparse
import re
import sys
import zipfile
from pathlib import Path

EXCLUDE_NAMES = {".git", "__pycache__", ".idea", ".DS_Store", "Thumbs.db"}
EXCLUDE_SUFFIXES = {".pyc", ".zip"}

ROOT = Path(__file__).resolve().parent


def leer_version(source: Path) -> str:
    """Extrae $plugin->release (o version si falta) de version.php sin ejecutar PHP."""
    contenido = (source / "version.php").read_text(encoding="utf-8")
    m = re.search(r"\$plugin->release\s*=\s*'([^']+)'", contenido)
    if m:
        return m.group(1)
    m = re.search(r"\$plugin->version\s*=\s*(\d+)", contenido)
    if m:
        return m.group(1)
    raise SystemExit("No se encontro $plugin->release ni $plugin->version en version.php")


def iter_archivos(source: Path):
    for path in sorted(source.rglob("*")):
        if not path.is_file():
            continue
        if EXCLUDE_NAMES & set(path.parts):
            continue
        if path.suffix in EXCLUDE_SUFFIXES:
            continue
        yield path


def empaquetar(source: Path, output: Path, carpeta_zip: str) -> int:
    if not source.is_dir():
        raise SystemExit(f"No existe la carpeta del plugin: {source}")
    if not (source / "version.php").is_file():
        raise SystemExit(f"{source} no parece un plugin de Moodle (falta version.php)")

    output.parent.mkdir(parents=True, exist_ok=True)

    total = 0
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as zf:
        for archivo in iter_archivos(source):
            arcname = Path(carpeta_zip) / archivo.relative_to(source)
            zf.write(archivo, arcname)
            total += 1
    return total


def verificar(output: Path, carpeta_zip: str) -> None:
    with zipfile.ZipFile(output) as zf:
        raiz = {name.split("/", 1)[0] for name in zf.namelist()}
    if raiz != {carpeta_zip}:
        raise SystemExit(
            f"El zip generado tiene carpeta(s) raiz {raiz}, se esperaba solo '{carpeta_zip}'"
        )


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", default="local_mod",
                        help="Carpeta del plugin en el repo (default: local_mod)")
    parser.add_argument("--output", default=None,
                        help="Ruta del .zip a generar (default: build/local_mod-<version>.zip, "
                             "leyendo la version de version.php)")
    parser.add_argument("--carpeta-zip", default="mod",
                        help="Nombre de carpeta raiz dentro del zip (default: mod, "
                             "el nombre que exige Moodle para el componente local_mod)")
    args = parser.parse_args()

    source = (ROOT / args.source).resolve()
    version = leer_version(source)
    output = (ROOT / args.output).resolve() if args.output else (ROOT / "build" / f"local_mod-{version}.zip")

    total = empaquetar(source, output, args.carpeta_zip)
    verificar(output, args.carpeta_zip)

    print(f"Version detectada: {version}")
    print(f"OK: {total} archivos empaquetados en {output}")
    print(f"Carpeta raiz dentro del zip: {args.carpeta_zip}/")
    print("Listo para subir en Administracion del sitio > Plugins > Instalar plugins.")


if __name__ == "__main__":
    sys.exit(main())
