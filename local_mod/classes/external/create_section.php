<?php
namespace local_mod\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_course;

/**
 * Crea una seccion en un curso envolviendo course_create_section().
 * Opcionalmente fija nombre, descripcion (summary) y visibilidad.
 */
class create_section extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT,  'ID del curso'),
            'position' => new external_value(PARAM_INT,  'Posicion donde insertar (0 = al final)', VALUE_DEFAULT, 0),
            'name'     => new external_value(PARAM_TEXT, 'Nombre de la seccion', VALUE_DEFAULT, null),
            'summary'  => new external_value(PARAM_RAW,  'Descripcion/resumen de la seccion (HTML)', VALUE_DEFAULT, null),
            'visible'  => new external_value(PARAM_INT,  'Visible (1/0)', VALUE_DEFAULT, 1),
        ]);
    }

    public static function execute($courseid, $position = 0, $name = null, $summary = null, $visible = 1) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'position' => $position,
            'name'     => $name,
            'summary'  => $summary,
            'visible'  => $visible,
        ]);

        $course  = get_course($params['courseid']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        // Crea la seccion y ajusta la secuencia del curso automaticamente.
        $sectioninfo = course_create_section($course, $params['position']);

        $data = new \stdClass();
        $needupdate = false;
        if ($params['name'] !== null) {
            $data->name = $params['name'];
            $needupdate = true;
        }
        if ($params['summary'] !== null) {
            $data->summary = $params['summary'];
            $data->summaryformat = FORMAT_HTML;
            $needupdate = true;
        }
        if ($params['visible'] !== null) {
            if ((int)$params['visible'] === 0) {
                require_capability('moodle/course:sectionvisibility', $context);
            }
            $data->visible = $params['visible'];
            $needupdate = true;
        }
        if ($needupdate) {
            $sectionrec = $DB->get_record('course_sections', ['id' => $sectioninfo->id], '*', MUST_EXIST);
            course_update_section($course, $sectionrec, $data);
        }

        return [
            'sectionid'     => $sectioninfo->id,
            'sectionnumber' => $sectioninfo->section,
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'sectionid'     => new external_value(PARAM_INT, 'id en course_sections'),
            'sectionnumber' => new external_value(PARAM_INT, 'numero de seccion'),
        ]);
    }
}
