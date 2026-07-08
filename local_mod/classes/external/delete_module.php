<?php
namespace local_mod\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../locallib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use context_module;

/**
 * Elimina una actividad/recurso envolviendo course_delete_module().
 * Es la misma baja que hace la interfaz: borra instancia, contexto, grade_items y ficheros.
 */
class delete_module extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id a eliminar'),
        ]);
    }

    public static function execute($cmid) {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm      = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        course_delete_module($cm->id);

        return ['status' => true];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'true si se elimino'),
        ]);
    }
}
