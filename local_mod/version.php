<?php
// Plugin local para gestionar (crear/actualizar/eliminar) actividades, recursos
// y secciones de un curso Moodle vía Web Services. Compatible Moodle 4.1 en adelante.
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_mod';
$plugin->version   = 2026070800;   // YYYYMMDDXX
$plugin->requires  = 2022112800;   // Moodle 4.1
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.1';
