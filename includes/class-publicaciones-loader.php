<?php
if ( !defined('ABSPATH') ) exit;

require_once PUB_PLUGIN_PATH . 'includes/class-publicaciones-admin.php';
require_once PUB_PLUGIN_PATH . 'includes/class-publicaciones-db.php';

class Publicaciones_Loader {

    private $admin;
    private $db;

    public function __construct() {
        $this->db = new Publicaciones_DB();
        $this->admin = new Publicaciones_Admin();
    }

    public function run() {
        add_action('admin_menu', [$this->admin, 'add_admin_menu']);
    }

    public static function activate() {
        // Crear tabla en la base de datos (lo haremos en el siguiente paso)
        $db = new Publicaciones_DB();
        $db->create_table();
    }

    public static function deactivate() {
        // En principio, nada. Más adelante añadiremos limpieza si hace falta.
    }
}
