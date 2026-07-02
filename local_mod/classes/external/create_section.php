<?php
namespace local_mod\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_course;

/**
 * Crea (o reutiliza) una seccion en un curso, con 'position' como numero de
 * seccion absoluto -no como posicion relativa de insercion-. Opcionalmente
 * fija nombre, descripcion (summary) y visibilidad.
 *
 * Soporta migrar secciones en cualquier orden (ej. 1, 10, 2, 4, 3): si se
 * pide una position mas alla del final del curso, NO se rellenan las
 * secciones intermedias que falten -quedan sin crear hasta que se pidan
 * explicitamente-; si la position pedida ya existe (por haberse creado
 * antes, o por pedirse dos veces), se reutiliza esa misma seccion en vez
 * de desplazar (shift) las que le siguen.
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

        $position = $params['position'];

        // course_create_section() trata 'position' como una posicion de
        // INSERCION relativa, no como "el numero de seccion final": si piden
        // una position mas alla del final la recorta a lastsection+1 sin
        // avisar, y si position ya existe desplaza (shift) todas las
        // secciones siguientes (y sus modulos) una posicion arriba.
        //
        // Aqui 'position' se trata como el numero de seccion ABSOLUTO que se
        // quiere: si ya existe (porque se creo antes, o porque ya se habia
        // pedido esa misma seccion), se reutiliza tal cual -sin shift-; si
        // esta mas alla del final, se crea directamente con ese numero
        // pasando $skipcheck = true, que hace que course_create_section()
        // NO recorte la position ni rellene/desplace nada -deja el hueco
        // intermedio sin crear hasta que se pida explicitamente-.
        if ($position > 0) {
            $sectioninfo = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $position]);
            if (!$sectioninfo) {
                $sectioninfo = course_create_section($course, $position, true);
            }
        } else {
            // 0 = al final: comportamiento nativo de course_create_section().
            $sectioninfo = course_create_section($course, $position);
        }

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
