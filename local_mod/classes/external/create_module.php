<?php
namespace local_mod\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../locallib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use context_course;

/**
 * Crea una actividad/recurso en un curso envolviendo add_moduleinfo().
 * Funciona para cualquier tipo de modulo (resource, url, forum, quiz, assign, h5pactivity...).
 */
class create_module extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid'   => new external_value(PARAM_INT,       'ID del curso'),
            'section'    => new external_value(PARAM_INT,       'Numero de seccion (0..n), debe existir'),
            'modulename' => new external_value(PARAM_COMPONENT, 'resource, url, forum, quiz, assign, h5pactivity, ...'),
            'name'       => new external_value(PARAM_TEXT,      'Nombre de la actividad'),
            'intro'      => new external_value(PARAM_RAW,       'Descripcion/intro (HTML)', VALUE_DEFAULT, ''),
            'visible'    => new external_value(PARAM_INT,       'Visible (1/0)', VALUE_DEFAULT, 1),
            'options'    => helper::options_structure(),
        ]);
    }

    public static function execute($courseid, $section, $modulename, $name, $intro = '', $visible = 1, $options = []) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'   => $courseid,
            'section'    => $section,
            'modulename' => $modulename,
            'name'       => $name,
            'intro'      => $intro,
            'visible'    => $visible,
            'options'    => $options,
        ]);

        $course  = get_course($params['courseid']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('mod/' . $params['modulename'] . ':addinstance', $context);

        $moduleid = $DB->get_field('modules', 'id',
            ['name' => $params['modulename'], 'visible' => 1], MUST_EXIST);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename  = $params['modulename'];
        $moduleinfo->module      = $moduleid;
        $moduleinfo->course      = $course->id;
        $moduleinfo->section     = $params['section'];
        $moduleinfo->name        = $params['name'];
        $moduleinfo->intro       = $params['intro'];
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->visible     = $params['visible'];

        // Campos especificos del modulo + defaults comunes.
        $moduleinfo = helper::apply_options($moduleinfo, $params['options']);
        $moduleinfo = helper::set_common_defaults($moduleinfo);

        // add_moduleinfo() crea el modulo, el course_module, el contexto,
        // el grade_item (si aplica) y lo coloca en la seccion. Misma logica que modedit.php.
        try {
            $moduleinfo = helper::ejecutar_mutacion_curso(
                $course->id,
                'crear_modulo:' . $params['modulename'],
                function() use ($moduleinfo, $course) {
                    return add_moduleinfo($moduleinfo, $course);
                }
            );
        } catch (\Throwable $error) {
            $respuesta = [
                'cmid' => 0,
                'instance' => 0,
                'modulename' => $params['modulename'],
                'exception' => get_class($error),
                'errorcode' => $error->errorcode ?? 'errormutacioncurso',
                'message' => $error->getMessage(),
            ];
            if (debugging()) {
                $respuesta['debuginfo'] = $error->debuginfo ?? '';
            }
            return $respuesta;
        }

        return [
            'cmid'       => $moduleinfo->coursemodule,
            'instance'   => $moduleinfo->instance,
            'modulename' => $params['modulename'],
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'cmid'       => new external_value(PARAM_INT,       'course module id creado'),
            'instance'   => new external_value(PARAM_INT,       'id de la instancia del modulo'),
            'modulename' => new external_value(PARAM_COMPONENT, 'tipo de modulo'),
            'exception' => new external_value(PARAM_RAW, 'Clase de excepcion si fallo la creacion', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_RAW, 'Codigo de error Moodle', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_RAW, 'Mensaje de error', VALUE_OPTIONAL),
            'debuginfo' => new external_value(PARAM_RAW, 'Detalle disponible con depuracion activada', VALUE_OPTIONAL),
        ]);
    }
}
