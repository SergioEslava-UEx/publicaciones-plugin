<?php
/**
 * Capa de datos del plugin "Publicaciones Científicas".
 * 
 * - Construye el nombre de la tabla con el prefijo de WordPress.
 * - Crea/actualiza el esquema con dbDelta() al activar el plugin.
 *
 * Nota: esta clase no se conecta a eventos del núcleo de WordPress; simplemente
 * expone funciones que el resto del plugin invoca cuando necesita operar con la BD.
 */
if ( !defined('ABSPATH') ) exit;

// Gestiona la tabla `{prefix}publicaciones` y provee utilidades relacionadas.
class Publicaciones_DB {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'publicaciones';
    }

    /**
     * Crea o actualiza el esquema de la tabla mediante dbDelta().
     *
     * Se invoca normalmente durante la activación del plugin. dbDelta permite
     * aplicar cambios de esquema de forma segura (añadir columnas, índices, etc.)
     * sin perder datos existentes, siempre que el SQL esté bien formado.
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Esquema de la tabla. dbDelta requiere:
        //  - PRIMARY KEY definida
        //  - palabras clave e índices en líneas separadas
        //  - tipos y longitudes consistentes
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            titulo varchar(255) NOT NULL,
            autores text,
            anio year,
            tipo_publicacion varchar(50) DEFAULT NULL,
            pdf_path varchar(255),
            bib_path varchar(255),
            revista varchar(255) DEFAULT NULL,
            fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
            ultima_modificacion datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        // Aplica el esquema. Si la tabla ya existe, ajusta lo necesario.
        dbDelta($sql);
    }

    /**
     * Exporta la tabla completa a un archivo CSV
     */
    public function export_to_csv($file_path) {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table_name}", ARRAY_A);

        if (empty($rows)) return false;

        $fp = fopen($file_path, 'w');
        if (!$fp) return false;

        // Escribir cabecera
        fputcsv($fp, array_keys($rows[0]));

        // Escribir filas
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);
        return true;
    }

    /**
     * Importa datos desde un archivo CSV
     */
    public function import_from_csv($file_path) {
        global $wpdb;

        if (!file_exists($file_path)) return false;

        $fp = fopen($file_path, 'r');
        if (!$fp) return false;

        $header = fgetcsv($fp);
        if (!$header) return false;

        while (($row = fgetcsv($fp)) !== false) {
            $data = array_combine($header, $row);
            // Insertar o actualizar registro
            $wpdb->replace($this->table_name, $data); // replace hace insert o update por PRIMARY KEY
        }

        fclose($fp);
        return true;
    }


}
