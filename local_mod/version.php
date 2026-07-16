<?php
// Plugin local para gestionar (crear/actualizar/eliminar) actividades, recursos
// y secciones de un curso Moodle vía Web Services. Compatible Moodle 4.0 en adelante.
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_mod';
$plugin->version   = 2026071500;   // YYYYMMDDXX
$plugin->requires  = 2022041900;   // Moodle 4.0
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.2';
