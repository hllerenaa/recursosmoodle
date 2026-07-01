<?php
namespace local_mod\external;

defined('MOODLE_INTERNAL') || die();

use external_multiple_structure;
use external_single_structure;
use external_value;

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
}
