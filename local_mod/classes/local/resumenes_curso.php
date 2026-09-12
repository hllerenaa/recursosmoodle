<?php
namespace local_mod\local;

defined('MOODLE_INTERNAL') || die();

class resumenes_curso {

    public static function normalizar(int $cursoid): void {
        global $DB;

        $secciones = $DB->get_fieldset_select('course_sections', 'id',
            'course = ? AND ' . $DB->sql_like('summary', '?', false),
            [$cursoid, '%data:image/%;base64,%']);
        if (!$secciones) {
            return;
        }

        $contexto = \context_course::instance($cursoid);
        require_capability('moodle/course:update', $contexto);

        foreach ($secciones as $seccionid) {
            $seccion = $DB->get_record('course_sections', ['id' => $seccionid], '*', MUST_EXIST);
            $resumen = self::guardar_imagenes($seccion->summary, $contexto, $seccion->id);
            if ($resumen !== $seccion->summary) {
                course_update_section($cursoid, $seccion, (object) ['summary' => $resumen]);
                unset($seccion);
                \course_modinfo::clear_instance_cache($cursoid);
                gc_collect_cycles();
            }
        }
    }

    public static function guardar_imagenes(string $resumen, \context_course $contexto, int $seccionid): string {
        global $USER;

        if (stripos($resumen, 'data:image/') === false) {
            return $resumen;
        }

        $archivos = get_file_storage();
        $resultado = preg_replace_callback(
            '~data:image/(png|jpeg|jpg|gif|webp);base64,([a-zA-Z0-9+/=\r\n]+)~i',
            function($coincidencia) use ($archivos, $contexto, $seccionid, $USER) {
                $contenido = base64_decode($coincidencia[2], true);
                $imagen = $contenido === false ? false : @getimagesizefromstring($contenido);
                $extensiones = [
                    'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp',
                ];
                if (!$imagen || !isset($extensiones[$imagen['mime']])) {
                    throw new \invalid_parameter_exception('Imagen base64 invalida en el resumen de la seccion ' . $seccionid);
                }

                $nombre = 'imagen_' . sha1($contenido) . '.' . $extensiones[$imagen['mime']];
                if (!$archivos->get_file($contexto->id, 'course', 'section', $seccionid, '/', $nombre)) {
                    $archivos->create_file_from_string([
                        'contextid' => $contexto->id,
                        'component' => 'course',
                        'filearea' => 'section',
                        'itemid' => $seccionid,
                        'filepath' => '/',
                        'filename' => $nombre,
                        'mimetype' => $imagen['mime'],
                        'userid' => $USER->id,
                    ], $contenido);
                }
                return '@@PLUGINFILE@@/' . $nombre;
            },
            $resumen
        );
        if ($resultado === null) {
            throw new \invalid_parameter_exception('No se pudieron procesar las imagenes del resumen de la seccion ' . $seccionid);
        }
        return $resultado;
    }
}
