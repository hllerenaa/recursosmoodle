<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    // ----- Modulos / actividades / recursos -----
    'local_mod_create_module' => [
        'classname'    => 'local_mod\external\create_module',
        'methodname'   => 'execute',
        'description'  => 'Crea una actividad/recurso en un curso (resource, url, forum, quiz, assign, h5pactivity, etc.).',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mod_update_module' => [
        'classname'    => 'local_mod\external\update_module',
        'methodname'   => 'execute',
        'description'  => 'Actualiza una actividad/recurso existente (por cmid), preservando los campos no enviados.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mod_delete_module' => [
        'classname'    => 'local_mod\external\delete_module',
        'methodname'   => 'execute',
        'description'  => 'Elimina una actividad/recurso (por cmid) usando la baja logica de Moodle.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],

    // ----- Secciones -----
    'local_mod_create_section' => [
        'classname'    => 'local_mod\external\create_section',
        'methodname'   => 'execute',
        'description'  => 'Crea una seccion en un curso (con nombre, descripcion y visibilidad opcionales).',
        'type'         => 'write',
        'capabilities' => 'moodle/course:update',
        'ajax'         => false,
    ],
    'local_mod_update_section' => [
        'classname'    => 'local_mod\external\update_section',
        'methodname'   => 'execute',
        'description'  => 'Actualiza una seccion: nombre, descripcion (summary) y/o visibilidad.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:update',
        'ajax'         => false,
    ],
    'local_mod_delete_section' => [
        'classname'    => 'local_mod\external\delete_section',
        'methodname'   => 'execute',
        'description'  => 'Elimina una seccion de un curso (force para secciones con actividades).',
        'type'         => 'write',
        'capabilities' => 'moodle/course:update',
        'ajax'         => false,
    ],
];

$services = [
    'Mod Management' => [
        'functions' => [
            'local_mod_create_module',
            'local_mod_update_module',
            'local_mod_delete_module',
            'local_mod_create_section',
            'local_mod_update_section',
            'local_mod_delete_section',
            // Funciones core utiles para el mismo flujo:
            'core_course_get_contents',
            'core_course_get_courses_by_field',
        ],
        'restrictedusers' => 1,
        'enabled'         => 1,
        'shortname'       => 'mod_mgmt',
        'downloadfiles'   => 1,
        'uploadfiles'     => 1,   // habilita /webservice/upload.php para el token (resource/h5p)
    ],
];
