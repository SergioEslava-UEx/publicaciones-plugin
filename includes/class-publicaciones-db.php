
<?php
if ( !defined('ABSPATH') ) exit;

class Publicaciones_DB {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'publicaciones';
    }

    /**
     * Crear la tabla en la base de datos.
     * Se ejecuta al activar el plugin.
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            titulo varchar(255) NOT NULL,
            autores text,
            anio year,
            pdf_path varchar(255),
            bib_path varchar(255),
            fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
            ultima_modificacion datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
