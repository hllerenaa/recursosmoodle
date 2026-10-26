<?php
namespace local_mod\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Convierte las imagenes base64 (data URI) de los textos de una pregunta en
 * archivos del area que les corresponde, como las deja el editor de Moodle.
 *
 * Los textos se reescriben con @@PLUGINFILE@@ antes de save_question() y los
 * archivos se crean despues, cuando ya existen el id de la pregunta y los de
 * sus respuestas (itemid de cada area).
 */
class imagenes_pregunta {

    const PATRON = '~data:image/(png|jpeg|jpg|gif|webp);base64,([a-zA-Z0-9+/=\r\n]+)~i';

    const EXTENSIONES = [
        'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp',
    ];

    /** Clave de item para las areas cuyo itemid es el id de la pregunta. */
    const ITEM_PREGUNTA = 'pregunta';

    private $pendientes = [];

    /**
     * Reemplaza las imagenes base64 del texto por @@PLUGINFILE@@/{nombre} y las
     * deja pendientes de guardar en {componente}/{filearea}.
     *
     * @param string $item ITEM_PREGUNTA o el indice de la respuesta en el form
     */
    public function extraer(string $texto, string $componente, string $filearea, $item = self::ITEM_PREGUNTA): string {
        if (stripos($texto, 'data:image/') === false) {
            return $texto;
        }
        $resultado = preg_replace_callback(self::PATRON,
            function($coincidencia) use ($componente, $filearea, $item) {
                $contenido = base64_decode(preg_replace('~\s+~', '', $coincidencia[2]), true);
                $imagen = $contenido === false ? false : @getimagesizefromstring($contenido);
                if (!$imagen || !isset(self::EXTENSIONES[$imagen['mime']])) {
                    throw new \invalid_parameter_exception('Imagen base64 invalida en ' . $filearea);
                }
                $nombre = 'imagen_' . sha1($contenido) . '.' . self::EXTENSIONES[$imagen['mime']];
                $this->pendientes[] = [
                    'componente' => $componente,
                    'filearea'   => $filearea,
                    'item'       => $item,
                    'nombre'     => $nombre,
                    'mimetype'   => $imagen['mime'],
                    'contenido'  => $contenido,
                ];
                return '@@PLUGINFILE@@/' . $nombre;
            },
            $texto
        );
        if ($resultado === null) {
            throw new \invalid_parameter_exception('No se pudieron procesar las imagenes de ' . $filearea);
        }
        return $resultado;
    }

    /**
     * Crea los archivos pendientes en el contexto de la pregunta.
     *
     * @param array $respuestaids id de question_answers por indice de respuesta del form
     */
    public function guardar(int $contextid, int $questionid, array $respuestaids = []): void {
        global $USER;

        $archivos = get_file_storage();
        foreach ($this->pendientes as $p) {
            if ($p['item'] === self::ITEM_PREGUNTA) {
                $itemid = $questionid;
            } else if (isset($respuestaids[$p['item']])) {
                $itemid = $respuestaids[$p['item']];
            } else {
                throw new \moodle_exception('bancoerrorimagen', 'local_mod', '', $p['item']);
            }
            if ($archivos->file_exists($contextid, $p['componente'], $p['filearea'], $itemid, '/', $p['nombre'])) {
                continue;
            }
            $archivos->create_file_from_string([
                'contextid' => $contextid,
                'component' => $p['componente'],
                'filearea'  => $p['filearea'],
                'itemid'    => $itemid,
                'filepath'  => '/',
                'filename'  => $p['nombre'],
                'mimetype'  => $p['mimetype'],
                'userid'    => $USER->id,
            ], $p['contenido']);
        }
    }
}
