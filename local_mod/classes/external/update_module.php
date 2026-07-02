<?php
namespace local_mod\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;

/**
 * Actualiza una actividad/recurso existente envolviendo update_moduleinfo().
 * Carga primero los datos actuales (get_moduleinfo_data) para no perder campos.
 */
class update_module extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT,  'course module id a actualizar'),
            'name'    => new external_value(PARAM_TEXT, 'Nuevo nombre', VALUE_DEFAULT, null),
            'intro'   => new external_value(PARAM_RAW,  'Nueva intro (HTML)', VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_INT,  'Visible (1/0)', VALUE_DEFAULT, null),
            'options' => helper::options_structure(),
        ]);
    }

    public static function execute($cmid, $name = null, $intro = null, $visible = null, $options = []) {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'    => $cmid,
            'name'    => $name,
            'intro'   => $intro,
            'visible' => $visible,
            'options' => $options,
        ]);

        $cm      = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $course  = get_course($cm->course);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Datos actuales del modulo (para no sobreescribir con vacios).
        list($cmrec, $ctx, $module, $data, $cw) = get_moduleinfo_data($cm, $course);

        if ($params['name'] !== null) {
            $data->name = $params['name'];
        }
        if ($params['intro'] !== null) {
            $data->intro = $params['intro'];
            $data->introformat = FORMAT_HTML;
        }
        if ($params['visible'] !== null) {
            $data->visible = $params['visible'];
        }

        $data = helper::apply_options($data, $params['options']);

        if (isset($data->gradepass) && $data->gradepass !== '' && $data->gradepass !== null) {
            $gradepass = unformat_float($data->gradepass, true);
            if ($gradepass !== false) {
                $data->gradepass = $gradepass;
            }
        }

        list($cmrec, $data) = update_moduleinfo($cmrec, $data, $course, null);

        return ['cmid' => $cm->id, 'status' => true];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'cmid'   => new external_value(PARAM_INT,  'course module id'),
            'status' => new external_value(PARAM_BOOL, 'true si se actualizo'),
        ]);
    }
}
