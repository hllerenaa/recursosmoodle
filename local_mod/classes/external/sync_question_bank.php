<?php
namespace local_mod\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../locallib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use context_module;
use local_mod\local\imagenes_pregunta;

/**
 * Sincronizacion masiva idempotente del banco de preguntas de un quiz.
 *
 * Recrea desde cero la categoria propia del banco ({categoriaprefijo}{cmid},
 * por defecto banco_preguntas_{cmid}) en el contexto del modulo, sus preguntas
 * (via question_bank::get_qtype()->save_question()) y los slots del quiz
 * (fijos con maxmark=puntaje, o N aleatorios con maxmark=puntajealeatorio).
 * No admite quizzes con intentos.
 */
class sync_question_bank extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid'               => new external_value(PARAM_INT,   'course module id del quiz'),
            'aleatorio'          => new external_value(PARAM_INT,   'Modo sorteo 0/1', VALUE_DEFAULT, 0),
            'cantidadaleatorias' => new external_value(PARAM_INT,   'N preguntas a sortear (si aleatorio)', VALUE_DEFAULT, 0),
            'puntajealeatorio'   => new external_value(PARAM_FLOAT, 'maxmark por slot aleatorio', VALUE_DEFAULT, 1),
            'categoriaprefijo'   => new external_value(PARAM_ALPHANUMEXT, 'Prefijo del nombre de la categoria del banco ({prefijo}{cmid})', VALUE_DEFAULT, helper::CATEGORIA_PREFIJO),
            'preguntas'          => self::estructura_preguntas(VALUE_DEFAULT),
            'preguntasjson'      => new external_value(PARAM_RAW, 'Lista completa del banco como JSON; reemplaza a preguntas[] y evita el limite max_input_vars de PHP', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Estructura de la lista de preguntas, compartida por preguntas[] y preguntasjson.
     */
    private static function estructura_preguntas($requerido = VALUE_REQUIRED) {
        return new external_multiple_structure(
            new external_single_structure([
                'indice'              => new external_value(PARAM_INT,  'Correlativo isterpry para mapear ids de vuelta'),
                'qtype'               => new external_value(PARAM_ALPHA, 'multichoice/truefalse/shortanswer/numerical/essay'),
                'nombre'              => new external_value(PARAM_TEXT, 'Nombre de la pregunta', VALUE_DEFAULT, ''),
                'enunciado'           => new external_value(PARAM_RAW,  'Enunciado (HTML)'),
                'retroalimentacion'   => new external_value(PARAM_RAW,  'Retroalimentacion general (HTML)', VALUE_DEFAULT, ''),
                'puntaje'             => new external_value(PARAM_FLOAT, 'Puntaje / maxmark del slot fijo'),
                'multiple'            => new external_value(PARAM_INT,  'multichoice: varias respuestas 0/1', VALUE_DEFAULT, 0),
                'vfcorrecta'          => new external_value(PARAM_INT,  'truefalse: 1=Verdadero correcto, 0=Falso correcto', VALUE_DEFAULT, 1),
                'usecase'             => new external_value(PARAM_INT,  'shortanswer: sensible a mayusculas 0/1', VALUE_DEFAULT, 0),
                'essaytextorequerido' => new external_value(PARAM_INT,  'essay: texto en linea requerido 0/1', VALUE_DEFAULT, 1),
                'essayadjuntos'       => new external_value(PARAM_INT,  'essay: numero de adjuntos permitidos', VALUE_DEFAULT, 0),
                'respuestas'          => new external_multiple_structure(
                    new external_single_structure([
                        'detalle'    => new external_value(PARAM_RAW,   'Texto de la respuesta'),
                        'fraccion'   => new external_value(PARAM_FLOAT, 'Fraccion Moodle en % (-100..100), ya calculada por isterpry'),
                        'tolerancia' => new external_value(PARAM_FLOAT, 'Tolerancia (numerical)', VALUE_DEFAULT, 0),
                    ]),
                    'Respuestas de la pregunta', VALUE_DEFAULT, []
                ),
            ]),
            'Lista completa del banco', $requerido, []
        );
    }

    /**
     * Decodifica y valida preguntasjson con la misma estructura que preguntas[].
     */
    private static function decodificar_preguntas_json($json) {
        $preguntas = json_decode($json, true);
        if (!is_array($preguntas)) {
            throw new \invalid_parameter_exception('preguntasjson no es un JSON de lista valido: ' . json_last_error_msg());
        }
        $validado = self::validate_parameters(
            new external_function_parameters(['preguntas' => self::estructura_preguntas()]),
            ['preguntas' => $preguntas]
        );
        return $validado['preguntas'];
    }

    public static function execute($cmid, $aleatorio = 0, $cantidadaleatorias = 0,
                                   $puntajealeatorio = 1, $categoriaprefijo = helper::CATEGORIA_PREFIJO,
                                   $preguntas = [], $preguntasjson = '') {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'               => $cmid,
            'aleatorio'          => $aleatorio,
            'cantidadaleatorias' => $cantidadaleatorias,
            'puntajealeatorio'   => $puntajealeatorio,
            'categoriaprefijo'   => $categoriaprefijo,
            'preguntas'          => $preguntas,
            'preguntasjson'      => $preguntasjson,
        ]);
        if ($params['preguntasjson'] !== '') {
            $params['preguntas'] = self::decodificar_preguntas_json($params['preguntasjson']);
        }
        if (empty($params['preguntas'])) {
            throw new \invalid_parameter_exception('Debe enviarse preguntas[] o preguntasjson con al menos una pregunta');
        }

        $cm      = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $quiz    = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/question:add', $context);

        if (quiz_has_attempts($quiz->id)) {
            throw new \moodle_exception('bancoconintentos', 'local_mod', '', null,
                'El cuestionario ya tiene intentos; no se puede sincronizar el banco de preguntas.');
        }

        return helper::ejecutar_mutacion_curso($cm->course, 'sincronizar_banco:' . $cm->id,
            function() use ($quiz, $context, $params, $DB) {
                helper::wipe_question_bank($quiz, $context, $params['categoriaprefijo']);
                $categoria = helper::ensure_category($context, $params['categoriaprefijo']);

                $mapeo   = [];
                $creadas = [];
                foreach ($params['preguntas'] as $p) {
                    list($qid, $qbeid) = self::crear_pregunta($p, $categoria, $context);
                    $mapeo[] = [
                        'indice'              => (int) $p['indice'],
                        'questionid'          => $qid,
                        'questionbankentryid' => $qbeid,
                    ];
                    $creadas[] = ['questionid' => $qid, 'puntaje' => (float) $p['puntaje']];
                }

                if (!empty($params['aleatorio'])) {
                    $n = (int) $params['cantidadaleatorias'];
                    if ($n > 0) {
                        helper::add_random_slots($quiz, $categoria, $context, $n, (float) $params['puntajealeatorio']);
                    }
                } else {
                    foreach ($creadas as $c) {
                        helper::add_fixed_slot($quiz, $c['questionid'], $c['puntaje']);
                    }
                }

                quiz_update_sumgrades($quiz);
                $quiz = $DB->get_record('quiz', ['id' => $quiz->id], '*', MUST_EXIST);

                return [
                    'success'    => true,
                    'categoryid' => (int) $categoria->id,
                    'sumgrades'  => (float) ($quiz->sumgrades ?? 0),
                    'preguntas'  => $mapeo,
                ];
            }
        );
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL,  'true si se sincronizo'),
            'categoryid' => new external_value(PARAM_INT,   'id de la categoria {categoriaprefijo}{cmid}'),
            'sumgrades'  => new external_value(PARAM_FLOAT, 'Suma de calificaciones del quiz tras recalcular'),
            'preguntas'  => new external_multiple_structure(
                new external_single_structure([
                    'indice'              => new external_value(PARAM_INT, 'Correlativo isterpry'),
                    'questionid'          => new external_value(PARAM_INT, 'id de la pregunta creada'),
                    'questionbankentryid' => new external_value(PARAM_INT, 'id de la question bank entry'),
                ])
            ),
        ]);
    }

    /**
     * Crea una pregunta en la categoria y devuelve [questionid, questionbankentryid].
     */
    private static function crear_pregunta(array $p, $categoria, context_module $context) {
        global $DB, $USER;

        $qtype = $p['qtype'];
        if (!in_array($qtype, ['multichoice', 'truefalse', 'shortanswer', 'numerical', 'essay'], true)) {
            throw new \moodle_exception('bancoerrorqtype', 'local_mod', '', $qtype,
                'Tipo de pregunta no soportado: ' . $qtype);
        }

        $form = self::form_base($p, $categoria);
        self::form_por_tipo($form, $p);
        $imagenes = self::extraer_imagenes($form);

        $question = new \stdClass();
        $question->id         = 0;
        $question->category   = $categoria->id;
        $question->qtype      = $qtype;
        $question->createdby  = $USER->id;
        $question->modifiedby = $USER->id;
        $question->contextid  = $context->id;

        $nuevo = \question_bank::get_qtype($qtype)->save_question($question, $form);

        $respuestaids = $qtype === 'multichoice'
            ? array_keys($DB->get_records('question_answers', ['question' => $nuevo->id], 'id ASC', 'id'))
            : [];
        $imagenes->guardar((int) $categoria->contextid, (int) $nuevo->id, $respuestaids);

        $qbeid = $DB->get_field('question_versions', 'questionbankentryid',
            ['questionid' => $nuevo->id], MUST_EXIST);

        return [(int) $nuevo->id, (int) $qbeid];
    }

    /**
     * Pasa las imagenes base64 de los textos del form a @@PLUGINFILE@@ y devuelve
     * el extractor con los archivos pendientes de guardar tras save_question().
     */
    private static function extraer_imagenes(\stdClass $form) {
        $imagenes = new imagenes_pregunta();
        $form->questiontext['text']    = $imagenes->extraer($form->questiontext['text'], 'question', 'questiontext');
        $form->generalfeedback['text'] = $imagenes->extraer($form->generalfeedback['text'], 'question', 'generalfeedback');
        if ($form->qtype === 'multichoice') {
            // save_question_options() omite las respuestas vacias: el item es la posicion entre las guardadas.
            $posicion = 0;
            foreach ($form->answer as $clave => $respuesta) {
                if (trim($respuesta['text']) === '') {
                    continue;
                }
                $form->answer[$clave]['text'] = $imagenes->extraer($respuesta['text'], 'question', 'answer', $posicion);
                $posicion++;
            }
        } else if ($form->qtype === 'essay') {
            $form->graderinfo['text'] = $imagenes->extraer($form->graderinfo['text'], 'qtype_essay', 'graderinfo');
        }
        return $imagenes;
    }

    /**
     * Campos comunes del objeto form que espera save_question().
     */
    private static function form_base(array $p, $categoria) {
        $form = new \stdClass();
        $form->category        = $categoria->id;
        $form->name            = self::nombre_pregunta($p);
        $form->questiontext    = ['text' => (string) $p['enunciado'], 'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => (string) ($p['retroalimentacion'] ?? ''), 'format' => FORMAT_HTML];
        $form->defaultmark     = (float) $p['puntaje'];
        $form->penalty         = 0.3333333;
        $form->qtype           = $p['qtype'];
        if (class_exists('\core_question\local\bank\question_version_status')) {
            $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        }
        return $form;
    }

    /**
     * Nombre corto de la pregunta (max 250) derivado del nombre o del enunciado.
     */
    private static function nombre_pregunta(array $p) {
        $nombre = trim((string) ($p['nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = trim(strip_tags((string) $p['enunciado']));
        }
        $nombre = \core_text::substr($nombre, 0, 250);
        return $nombre !== '' ? $nombre : 'Pregunta';
    }

    /**
     * Convierte la fraccion de isterpry (porcentaje -100..100) a la fraccion
     * decimal (-1..1) que consume save_question().
     */
    private static function fraccion_decimal($porcentaje) {
        return round((float) $porcentaje / 100.0, 7);
    }

    /**
     * Rellena en $form los campos especificos de cada qtype.
     */
    private static function form_por_tipo(\stdClass $form, array $p) {
        $respuestas = isset($p['respuestas']) ? $p['respuestas'] : [];

        switch ($p['qtype']) {
            case 'multichoice':
                $form->single                   = empty($p['multiple']) ? 1 : 0;
                $form->shuffleanswers           = 1;
                $form->answernumbering          = 'abc';
                $form->correctfeedback          = ['text' => 'Respuesta correcta.', 'format' => FORMAT_HTML];
                $form->partiallycorrectfeedback = ['text' => 'Respuesta parcialmente correcta.', 'format' => FORMAT_HTML];
                $form->incorrectfeedback        = ['text' => 'Respuesta incorrecta.', 'format' => FORMAT_HTML];
                $form->shownumcorrect           = 1;
                $form->answer   = [];
                $form->fraction = [];
                $form->feedback = [];
                foreach ($respuestas as $r) {
                    $form->answer[]   = ['text' => (string) $r['detalle'], 'format' => FORMAT_HTML];
                    $form->fraction[] = self::fraccion_decimal($r['fraccion']);
                    $form->feedback[] = ['text' => '', 'format' => FORMAT_HTML];
                }
                break;

            case 'truefalse':
                $form->correctanswer = empty($p['vfcorrecta']) ? 0 : 1;
                $form->feedbacktrue  = ['text' => '', 'format' => FORMAT_HTML];
                $form->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML];
                $form->penalty       = 1;
                break;

            case 'shortanswer':
                $form->usecase  = empty($p['usecase']) ? 0 : 1;
                $form->answer   = [];
                $form->fraction = [];
                $form->feedback = [];
                foreach ($respuestas as $r) {
                    $form->answer[]   = (string) $r['detalle'];
                    $form->fraction[] = self::fraccion_decimal($r['fraccion']);
                    $form->feedback[] = ['text' => '', 'format' => FORMAT_HTML];
                }
                break;

            case 'numerical':
                $form->answer    = [];
                $form->fraction  = [];
                $form->tolerance = [];
                $form->feedback  = [];
                foreach ($respuestas as $r) {
                    $form->answer[]    = (string) $r['detalle'];
                    $form->fraction[]  = self::fraccion_decimal($r['fraccion']);
                    $form->tolerance[] = (float) ($r['tolerancia'] ?? 0);
                    $form->feedback[]  = ['text' => '', 'format' => FORMAT_HTML];
                }
                $form->unitgradingtype = 0;
                $form->unitpenalty     = 0.1;
                $form->showunits       = 3;
                $form->unitsleft       = 0;
                $form->unit            = [];
                $form->multiplier      = [];
                break;

            case 'essay':
                $requerido = !empty($p['essaytextorequerido']);
                $adjuntos  = (int) ($p['essayadjuntos'] ?? 0);
                if ($requerido) {
                    $form->responseformat   = 'editor';
                    $form->responserequired = 1;
                } else if ($adjuntos > 0) {
                    $form->responseformat   = 'noinline';
                    $form->responserequired = 0;
                } else {
                    $form->responseformat   = 'editor';
                    $form->responserequired = 0;
                }
                $form->responsefieldlines = 15;
                $form->attachments        = $adjuntos;
                $form->attachmentsrequired = 0;
                $form->graderinfo         = ['text' => (string) ($p['retroalimentacion'] ?? ''), 'format' => FORMAT_HTML];
                $form->responsetemplate   = ['text' => '', 'format' => FORMAT_HTML];
                $form->penalty            = 0;
                $form->maxbytes           = 0;
                $form->filetypeslist      = '';
                $form->minwordlimit       = 0;
                $form->maxwordlimit       = 0;
                $form->minwordenabled     = 0;
                $form->maxwordenabled     = 0;
                break;
        }
    }
}
