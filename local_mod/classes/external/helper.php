<?php
namespace local_mod\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../locallib.php');

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Utilidades compartidas por las external functions.
 */
class helper {

    /**
     * Campos internos del moduleinfo/course_modules que 'options' NUNCA debe
     * poder tocar: add_moduleinfo()/update_moduleinfo() los usan tal cual
     * (no los re-derivan del curso/cm ya validado), asi que dejarlos pasar
     * permite crear/actualizar un modulo de otro tipo o pisar la instancia
     * de otro curso saltandose el capability check ya hecho.
     */
    private const CAMPOS_PROTEGIDOS = [
        'id', 'course', 'coursemodule', 'module', 'modulename', 'instance', 'section',
    ];

    /**
     * Estructura del parametro 'options': lista de pares {name, value}
     * con los campos especificos de cada tipo de modulo.
     */
    public static function options_structure() {
        return new external_multiple_structure(
            new external_single_structure([
                'name'  => new external_value(PARAM_ALPHANUMEXT, 'Nombre del campo especifico del modulo'),
                'value' => new external_value(PARAM_RAW, 'Valor del campo'),
            ]),
            'Campos especificos del modulo (ej: url->externalurl, assign->duedate, quiz->grade)',
            VALUE_DEFAULT, []
        );
    }

    /**
     * Vuelca los pares {name,value} sobre el objeto moduleinfo, ignorando
     * los campos protegidos (ver CAMPOS_PROTEGIDOS).
     * Coercion ligera: enteros limpios -> int; el resto queda como string.
     */
    public static function apply_options(\stdClass $moduleinfo, array $options) {
        foreach ($options as $opt) {
            $name = $opt['name'];
            if (in_array($name, self::CAMPOS_PROTEGIDOS, true)) {
                continue;
            }
            $value = $opt['value'];
            if (is_numeric($value) && (string)(int)$value === (string)$value) {
                $value = (int)$value;
            }
            $moduleinfo->{$name} = $value;
        }
        return $moduleinfo;
    }

    /**
     * Defaults comunes que add_moduleinfo espera y que normalmente
     * provendrian del formulario de edicion de la actividad.
     */
    public static function set_common_defaults(\stdClass $moduleinfo) {
        $defaults = [
            'visible'                    => 1,
            'visibleoncoursepage'        => 1,
            'cmidnumber'                 => '',
            'groupmode'                  => 0,
            'groupingid'                 => 0,
            'availability'               => null,
            'completion'                 => 0,
            'completionview'             => 0,
            'completionexpected'         => 0,
            'completiongradeitemnumber'  => null,
            'showdescription'            => 0,
        ];
        foreach ($defaults as $k => $v) {
            if (!isset($moduleinfo->{$k})) {
                $moduleinfo->{$k} = $v;
            }
        }
        if (!isset($moduleinfo->introformat)) {
            $moduleinfo->introformat = FORMAT_HTML;
        }
        if (!isset($moduleinfo->intro)) {
            $moduleinfo->intro = '';
        }
        return $moduleinfo;
    }

    /**
     * Prefijo por defecto del nombre de la categoria propia del banco.
     * La categoria final se llama {prefijo}{cmid} y vive en el contexto del
     * propio modulo (quiz). El prefijo puede sobreescribirse por parametro
     * del webservice (categoriaprefijo).
     */
    const CATEGORIA_PREFIJO = 'banco_preguntas_';

    /**
     * Obtiene (o crea) la categoria propia del banco en el contexto del
     * modulo. Nombre: {prefijo}{cmid}. Idempotente.
     *
     * Se apoya en question_get_top_category() (estable 4.1-4.5) para garantizar
     * la categoria "top" del contexto y cuelga la nuestra debajo. La creacion es
     * una insercion de metadatos en {question_categories} (no hay API publica
     * estable entre 4.1 y 4.5 para crear una categoria hija plana; question_delete_question
     * / save_question si son estables y se usan para el contenido).
     */
    public static function ensure_category(\context_module $context, $prefijo = self::CATEGORIA_PREFIJO) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        $prefijo = trim((string) $prefijo);
        if ($prefijo === '') {
            $prefijo = self::CATEGORIA_PREFIJO;
        }
        $nombre = $prefijo . $context->instanceid;
        $existente = $DB->get_record('question_categories',
            ['contextid' => $context->id, 'name' => $nombre]);
        if ($existente) {
            return $existente;
        }

        $top = question_get_top_category($context->id, true);

        $cat = new \stdClass();
        $cat->parent      = $top->id;
        $cat->contextid   = $context->id;
        $cat->name        = $nombre;
        $cat->info        = '';
        $cat->infoformat  = FORMAT_HTML;
        $cat->stamp       = make_unique_id_code();
        $cat->sortorder   = 999;
        $cat->idnumber    = null;
        $cat->id = $DB->insert_record('question_categories', $cat);
        return $cat;
    }

    /**
     * Construye el objeto estructura del quiz (mod_quiz\structure).
     *
     * Compatibilidad:
     *  - 4.2 - 4.5: la clase de settings es \mod_quiz\quiz_settings (refactor MDL-71691).
     *  - 4.1:       la clase global es \quiz (mod/quiz/attemptlib.php).
     * En ambas ramas \mod_quiz\structure::create_for_quiz() existe y devuelve la
     * misma estructura manipulable de slots.
     */
    public static function quiz_structure($quiz) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        if (class_exists('\mod_quiz\quiz_settings')) {
            $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
        } else if (class_exists('\quiz')) {
            $quizobj = \quiz::create($quiz->id);
        } else {
            throw new \moodle_exception('bancoerrorquizsettings', 'local_mod', '', null,
                'No se encontro la clase de configuracion del quiz (quiz_settings/quiz).');
        }

        if (!class_exists('\mod_quiz\structure')) {
            throw new \moodle_exception('bancoerrorquizsettings', 'local_mod', '', null,
                'No se encontro \mod_quiz\structure en esta version de Moodle.');
        }
        return \mod_quiz\structure::create_for_quiz($quizobj);
    }

    /**
     * Elimina todos los slots del quiz usando la API de estructura de mod_quiz.
     * Se recrea la estructura fresca en cada iteracion y se borra siempre el slot
     * de numero mas alto para evitar depender de la renumeracion interna.
     */
    public static function remove_all_slots($quiz) {
        global $DB;
        while (true) {
            $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot DESC', 'id, slot', 0, 1);
            if (empty($slots)) {
                break;
            }
            $slot = reset($slots);
            $structure = self::quiz_structure($quiz);
            $structure->remove_slot($slot->slot);
        }
    }

    /**
     * Vacia el banco propio del quiz: quita todos los slots y elimina todas las
     * preguntas de la categoria {prefijo}{cmid} via question_delete_question()
     * (limpia answers, options, versions, bank entries y referencias). Idempotente.
     *
     * Devuelve ['categoryid' => int, 'eliminadas' => int].
     */
    public static function wipe_question_bank($quiz, \context_module $context, $prefijo = self::CATEGORIA_PREFIJO) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        self::remove_all_slots($quiz);

        $categoria = self::ensure_category($context, $prefijo);

        $sql = "SELECT DISTINCT q.id
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qbe.questioncategoryid = :catid";
        $ids = $DB->get_fieldset_sql($sql, ['catid' => $categoria->id]);

        $eliminadas = 0;
        foreach ($ids as $qid) {
            question_delete_question($qid);
            $eliminadas++;
        }

        return ['categoryid' => (int) $categoria->id, 'eliminadas' => $eliminadas];
    }

    /**
     * Agrega un slot fijo con una pregunta concreta y su maxmark.
     * quiz_add_quiz_question() ha permanecido en mod/quiz/locallib.php de 4.1 a 4.5
     * (marcada deprecada en versiones recientes pero funcional).
     */
    public static function add_fixed_slot($quiz, $questionid, $maxmark) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        if (!function_exists('quiz_add_quiz_question')) {
            throw new \moodle_exception('bancoerrorslotfijo', 'local_mod', '', null,
                'quiz_add_quiz_question() no existe en esta version de Moodle.');
        }
        quiz_add_quiz_question($questionid, $quiz, 0, $maxmark);
    }

    /**
     * Agrega N slots aleatorios que sortean de la categoria del banco.
     *
     * Compatibilidad (detectada en runtime):
     *  - 4.3 - 4.5: \mod_quiz\structure::add_random_questions($addonpage, $number, $filtercondition)
     *               (MDL-72321 movio el sorteo a condiciones de filtro).
     *  - 4.1 - 4.2: funcion libre quiz_add_random_questions($quiz, $addonpage, $categoryid,
     *               $number, $includesubcategories).
     * Si no existe ninguna via, se lanza moodle_exception.
     *
     * El maxmark de los slots aleatorios no lo fija ninguna de las dos APIs, asi que
     * tras agregarlos se recarga la estructura y se ajusta con
     * \mod_quiz\structure::update_slot_maxmark() (estable 4.1-4.5) para los slots
     * nuevos (id mayor al maximo previo).
     */
    public static function add_random_slots($quiz, $categoria, \context_module $context, $number, $maxmark) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $before = (int) $DB->get_field_sql(
            "SELECT COALESCE(MAX(id), 0) FROM {quiz_slots} WHERE quizid = ?", [$quiz->id]);

        if (method_exists('\mod_quiz\structure', 'add_random_questions')) {
            $structure = self::quiz_structure($quiz);
            $filtro = self::random_filtercondition($categoria);
            $structure->add_random_questions(0, $number, $filtro);
        } else if (function_exists('quiz_add_random_questions')) {
            quiz_add_random_questions($quiz, 0, $categoria->id, $number, false);
        } else {
            throw new \moodle_exception('bancoerrorslotaleatorio', 'local_mod', '', null,
                'No hay API disponible para agregar preguntas aleatorias en esta version.');
        }

        if ($maxmark !== null) {
            $structure = self::quiz_structure($quiz);
            foreach ($structure->get_slots() as $slot) {
                if ($slot->id > $before) {
                    $structure->update_slot_maxmark($slot, $maxmark);
                }
            }
        }
    }

    /**
     * Condicion de filtro para el sorteo aleatorio en 4.3+.
     * El esquema del filtro cambio entre 4.3, 4.4 y 4.5; esta forma cubre la
     * categoria unica sin subcategorias y debe validarse en un Moodle real.
     */
    private static function random_filtercondition($categoria) {
        return [
            'filter' => [
                'category' => [
                    'jointype' => 1,
                    'values' => [(int) $categoria->id],
                    'filteroptions' => ['includesubcategories' => 0],
                ],
            ],
        ];
    }
}
