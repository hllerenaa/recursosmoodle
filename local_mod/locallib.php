<?php
// Compatibilidad de la API externa entre versiones de Moodle.
//
// Desde Moodle 4.2 las clases de la API externa viven en el namespace
// core_external y los alias globales legacy (external_api, external_value,
// etc.) solo existen si algo hace require de lib/externallib.php — archivo
// que ademas desaparece en Moodle 4.6+. Por eso el plugin usa siempre los
// nombres core_external\* y este shim los crea en Moodle 4.1, donde ese
// namespace todavia no existe.
defined('MOODLE_INTERNAL') || die();

if (!class_exists('core_external\external_api')) {
    global $CFG;
    require_once($CFG->libdir . '/externallib.php');
    class_alias('external_api', 'core_external\external_api');
    class_alias('external_function_parameters', 'core_external\external_function_parameters');
    class_alias('external_value', 'core_external\external_value');
    class_alias('external_single_structure', 'core_external\external_single_structure');
    class_alias('external_multiple_structure', 'core_external\external_multiple_structure');
}
