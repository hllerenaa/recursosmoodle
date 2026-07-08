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
 * Elimina una seccion envolviendo course_delete_section().
 * No se puede eliminar la seccion 0. Con force=1 elimina aunque tenga actividades.
 */
class delete_section extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid'      => new external_value(PARAM_INT, 'ID del curso'),
            'sectionnumber' => new external_value(PARAM_INT, 'Numero de seccion a eliminar'),
            'force'         => new external_value(PARAM_INT, 'Forzar aunque tenga actividades (1/0)', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute($courseid, $sectionnumber, $force = 0) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'      => $courseid,
            'sectionnumber' => $sectionnumber,
            'force'         => $force,
        ]);

        $course  = get_course($params['courseid']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        $sectionrec = $DB->get_record('course_sections',
            ['course' => $course->id, 'section' => $params['sectionnumber']], '*', MUST_EXIST);

        $ok = course_delete_section($course, $sectionrec, (bool)$params['force']);

        return ['status' => (bool)$ok];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'true si se elimino'),
        ]);
    }
}
