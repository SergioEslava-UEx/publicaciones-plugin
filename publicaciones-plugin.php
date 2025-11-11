<?php
/*
Plugin Name: Publicaciones Científicas
Plugin URI: http://localhost/wordpress
Description: Sistema para gestionar y consultar publicaciones científicas con archivos PDF y BibTeX.
Version: 0.1
Author: Tu Nombre
License: GPL2
*/

// Evita acceso directo
if ( !defined('ABSPATH') ) exit;

// Definir constantes del plugin
define( 'PUB_PLUGIN_PATH', plugin_dir_path(__FILE__) );
define( 'PUB_PLUGIN_URL', plugin_dir_url(__FILE__) );

// Autocarga de clases
require_once PUB_PLUGIN_PATH . 'includes/class-publicaciones-loader.php';

// Activación / desactivación
register_activation_hook( __FILE__, ['Publicaciones_Loader', 'activate'] );
register_deactivation_hook( __FILE__, ['Publicaciones_Loader', 'deactivate'] );

// Iniciar el plugin
function run_publicaciones_plugin() {
    $plugin = new Publicaciones_Loader();
    $plugin->run();
}
run_publicaciones_plugin();
