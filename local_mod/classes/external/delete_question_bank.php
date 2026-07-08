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
 * Vacia el banco de preguntas propio de un quiz.
 *
 * Elimina las preguntas de la categoria {categoriaprefijo}{cmid} (por defecto
 * banco_preguntas_{cmid}) via question_delete_question() y todos los slots del
 * quiz; la suma de calificaciones queda en 0. No admite quizzes con intentos.
 */
class delete_question_bank extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid'             => new external_value(PARAM_INT, 'course module id del quiz'),
            'categoriaprefijo' => new external_value(PARAM_ALPHANUMEXT, 'Prefijo del nombre de la categoria del banco ({prefijo}{cmid})', VALUE_DEFAULT, helper::CATEGORIA_PREFIJO),
        ]);
    }

    public static function execute($cmid, $categoriaprefijo = helper::CATEGORIA_PREFIJO) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'             => $cmid,
            'categoriaprefijo' => $categoriaprefijo,
        ]);

        $cm      = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $quiz    = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/question:managecategory', $context);
        require_capability('moodle/question:editall', $context);

        if (quiz_has_attempts($quiz->id)) {
            throw new \moodle_exception('bancoconintentos', 'local_mod', '', null,
                'El cuestionario ya tiene intentos; no se puede vaciar el banco de preguntas.');
        }

        $transaction = $DB->start_delegated_transaction();

        $resultado = helper::wipe_question_bank($quiz, $context, $params['categoriaprefijo']);
        quiz_update_sumgrades($quiz);

        $transaction->allow_commit();

        return [
            'success'    => true,
            'eliminadas' => (int) $resultado['eliminadas'],
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, 'true si se vacio'),
            'eliminadas' => new external_value(PARAM_INT,  'Numero de preguntas eliminadas'),
        ]);
    }
}
