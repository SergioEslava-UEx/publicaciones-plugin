<?php
/**
 * Carga y coordinación del plugin “Publicaciones Científicas”.
 *
 * Esta clase centraliza la inicialización del plugin:
 * - Crea las instancias de los componentes principales (administración y base de datos).
 * - Conecta métodos del plugin a eventos del ciclo de WordPress (puntos de integración),
 *   por ejemplo, al evento de construcción del menú de administración.
 * - Define lo que ocurre al activar/desactivar el plugin (p. ej. crear la tabla).
 */
if ( !defined('ABSPATH') ) exit;

require_once PUB_PLUGIN_PATH . 'includes/class-publicaciones-admin.php';
require_once PUB_PLUGIN_PATH . 'includes/class-publicaciones-db.php';

class Publicaciones_Loader {

    private $admin;
    private $db;
    // Construye el cargador e instancia los componentes principales
    public function __construct() {
        $this->db = new Publicaciones_DB();
        $this->admin = new Publicaciones_Admin();
    }

    public function run() {
        // Evento del ciclo de WP: construcción del menú del admin.
        add_action('admin_menu', [$this->admin, 'add_admin_menu']);
    }

    public static function activate() {
        // Crear/actualizar la tabla de base de datos para almacenar las publicaciones
        $db = new Publicaciones_DB();
        $db->create_table();
    }

    public static function deactivate() {
        // Actualmente no elimina datos ni modifica el esquema. Este método queda como 
        // punto central para añadir tareas de limpieza si en el futuro fueran necesarias
    }
}
