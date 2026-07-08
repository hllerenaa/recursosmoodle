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
 * Actualiza una seccion (nombre, descripcion/summary, visibilidad)
 * envolviendo course_update_section(). Solo cambia lo que se envia.
 */
class update_section extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid'      => new external_value(PARAM_INT,  'ID del curso'),
            'sectionnumber' => new external_value(PARAM_INT,  'Numero de seccion (0..n)'),
            'name'          => new external_value(PARAM_TEXT, 'Nuevo nombre', VALUE_DEFAULT, null),
            'summary'       => new external_value(PARAM_RAW,  'Nueva descripcion/resumen (HTML)', VALUE_DEFAULT, null),
            'visible'       => new external_value(PARAM_INT,  'Visible (1/0)', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute($courseid, $sectionnumber, $name = null, $summary = null, $visible = null) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'      => $courseid,
            'sectionnumber' => $sectionnumber,
            'name'          => $name,
            'summary'       => $summary,
            'visible'       => $visible,
        ]);

        $course  = get_course($params['courseid']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        $sectionrec = $DB->get_record('course_sections',
            ['course' => $course->id, 'section' => $params['sectionnumber']], '*', MUST_EXIST);

        $data = new \stdClass();
        if ($params['name'] !== null) {
            $data->name = $params['name'];
        }
        if ($params['summary'] !== null) {
            $data->summary = $params['summary'];
            $data->summaryformat = FORMAT_HTML;
        }
        if ($params['visible'] !== null) {
            if ((int)$params['visible'] === 0) {
                require_capability('moodle/course:sectionvisibility', $context);
            }
            $data->visible = $params['visible'];
        }

        course_update_section($course, $sectionrec, $data);

        return ['sectionid' => $sectionrec->id, 'status' => true];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'sectionid' => new external_value(PARAM_INT,  'id en course_sections'),
            'status'    => new external_value(PARAM_BOOL, 'true si se actualizo'),
        ]);
    }
}
