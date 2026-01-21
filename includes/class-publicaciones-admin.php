<?php
if ( !defined('ABSPATH') ) exit;

// Definicion de los tipos de publicacion
define('TIPOS_PUBLICACION', [
    'Tesis',
    'Trabajo Fin de Estudios',
    'Congreso',
    'Revista',
    'Preprint',
    'Libro'
]);

/**
 * Interfaz de administración del plugin "Publicaciones Científicas".
 * - Añade la opción de menú en el panel de WordPress.
 * - Pinta el listado (WP_List_Table), filtros y formularios.
 * - Procesa acciones del usuario (crear, editar, eliminar, importación).
 * - Valida nonces (CSRF) y sanea entradas.
 *
 * No crea páginas públicas ni shortcodes; todo ocurre dentro del área de administración.
 */
class Publicaciones_Admin {

    /** Mensajes de error/éxito del formulario de alta */
    private $form_notice = '';

    /** Indica si se ha actualizado una publicación en esta petición */
    private $edit_success = false;

    public function __construct($db) {
        $this->db = $db;
    }

    // Registra el elemento de menú y la página del plugin en el administrador.
    public function add_admin_menu() {
        add_menu_page(
            'Publicaciones', // Título de la página
            'Publicaciones', // Texto del menú
            'manage_options', // Capacidad mínima requerida
            'publicaciones', // Slug
            [$this, 'render_admin_page'], // Callback que pinta la pantalla
            'dashicons-media-document', // Icono
            20 // Posición
        );
    }

    // Renderiza la pantalla principal del plugin en el administrador.
    public function render_admin_page() {
        echo '<div class="wrap"><h1>📚 Publicaciones Científicas</h1>';

        // Si se ha enviado el formulario, guardar nueva publicación
        if ( isset($_POST['pub_guardar']) ) {
            $this->guardar_publicacion();
        }

        if ( isset($_POST['pub_vaciar']) && isset($_POST['pub_vaciar_nonce_field']) && wp_verify_nonce($_POST['pub_vaciar_nonce_field'], 'pub_vaciar_nonce') ) {
            $this->vaciar_publicaciones();
        }

        // Borrar publicación
        if ( isset($_GET['borrar_id']) ) {
            $this->borrar_publicacion(intval($_GET['borrar_id']));
        }

        // Editar publicación
        $hay_post_ajeno_a_edicion = !empty($_POST) && !isset($_POST['pub_editar_guardar']);
        if ( isset($_GET['editar_id']) && !$hay_post_ajeno_a_edicion ) {
            $this->editar_publicacion_form(intval($_GET['editar_id']));
        }

        if ( isset($_POST['pub_volcar']) && !empty($_POST['ruta_volcado']) ) {
            $this->volcar_publicaciones(trim($_POST['ruta_volcado']));
        }

        // Resetear completamente el sistema
        if ( isset($_POST['pub_reset']) &&
            isset($_POST['pub_reset_nonce_field']) &&
            wp_verify_nonce($_POST['pub_reset_nonce_field'], 'pub_reset_nonce') ) {

            $this->resetear_plugin();
        }

        // Búsqueda
        $busqueda = isset($_GET['pub_buscar']) ? sanitize_text_field($_GET['pub_buscar']) : '';

        // Filtrado por año
        $filtro_anyo = isset($_GET['pub_anyo']) ? intval($_GET['pub_anyo']) : '';

        // Paginación
        $pagina_actual = isset($_GET['pub_pagina']) ? max(1, intval($_GET['pub_pagina'])) : 1;
        $items_por_pagina = 20;

        echo '<form method="get" style="margin-bottom:20px;">';
        echo '<input type="hidden" name="page" value="publicaciones">'; // mantener menú
        echo '<input type="text" name="pub_buscar" value="' . esc_attr($busqueda) . '" placeholder="Buscar título o autores"> ';
        echo '<input type="number" name="pub_anyo" value="' . esc_attr($filtro_anyo) . '" placeholder="Año"> ';
        echo '<input type="submit" class="button" value="Filtrar">';
        echo '</form>';

        // Mostrar listado con parámetros
        echo '<hr><h2>Publicaciones registradas</h2>';
        $this->mostrar_listado($busqueda, $filtro_anyo, $pagina_actual, $items_por_pagina);

        // Mostrar el formulario de alta
        echo '<h2>Añadir nueva publicación</h2>';

        // Avisos específicos del formulario de alta (errores/éxito)
        if ( !empty($this->form_notice) ) {
            echo $this->form_notice;
        }

        $this->mostrar_formulario();

        // Formulario para vaciar la base de datos
        echo '<h2>⚠️ Vaciar todas las publicaciones</h2>';
        echo '<form method="post" onsubmit="return confirm(\'¿Seguro que quieres borrar todas las publicaciones? Esta acción no se puede deshacer.\');">';
        wp_nonce_field('pub_vaciar_nonce', 'pub_vaciar_nonce_field');
        echo '<input type="submit" name="pub_vaciar" class="button button-secondary" value="Vaciar base de datos">';
        echo '</form>';

        // Volcar publicaciones desde una carpeta local
        echo '<h2>Volcar publicaciones desde carpeta local</h2>';
        echo '<form method="post">';
        echo '<input type="text" name="ruta_volcado" placeholder="/ruta/a/publicaciones/" style="width:400px;"> ';
        echo '<input type="submit" name="pub_volcar" class="button button-primary" value="Volcar publicaciones">';
        echo '</form>';

        // Hard Reset del plugin
        echo '<h2>🧨 Resetear completamente el plugin</h2>';
        echo '<p style="color:red;font-weight:bold;">¡Esto borrará la tabla y la recreará desde cero! Úsalo solo si sabes lo que haces.</p>';
        echo '<form method="post" onsubmit="return confirm(\'⚠️ Esto borrará toda la información y recreará la base de datos.\n\n¿Seguro que quieres continuar?\');">';
        wp_nonce_field('pub_reset_nonce', 'pub_reset_nonce_field');
        echo '<input type="submit" name="pub_reset" class="button button-primary" value="Resetear plugin por completo">';
        echo '</form>';

        // --- EXPORTACIÓN DE LA BASE DE DATOS ---
        echo '<h2>💾 Exportar base de datos</h2>';
        if ( isset($_POST['pub_exportar']) ) {
            $export_path = WP_CONTENT_DIR . '/uploads/publicaciones_export.csv';
            if ($this->db->export_to_csv($export_path)) {
                echo '<p>Exportación realizada correctamente: <a href="' . content_url('uploads/publicaciones_export.csv') . '" target="_blank">Descargar CSV</a></p>';
            } else {
                echo '<p style="color:red;">Error al exportar la base de datos.</p></p>';
            }
        }
        echo '<form method="post">';
        echo '<input type="submit" name="pub_exportar" class="button button-primary" value="Exportar a CSV">';
        echo '</form>';

        // --- IMPORTACIÓN DE LA BASE DE DATOS ---
        echo '<h2>📂 Importar base de datos desde CSV</h2>';
        if ( isset($_FILES['pub_importar_file']) && $_FILES['pub_importar_file']['error'] == 0 ) {
            $uploaded_file = $_FILES['pub_importar_file']['tmp_name'];
            if ($this->db->import_from_csv($uploaded_file)) {
                echo '<p>Importación realizada correctamente.</p>';
            } else {
                echo '<p style="color:red;">Error al importar la base de datos.</p>';
            }
        }
        echo '<form method="post" enctype="multipart/form-data">';
        echo '<input type="file" name="pub_importar_file" accept=".csv"> ';
        echo '<input type="submit" name="pub_importar_submit" class="button button-primary" value="Importar CSV">';
        echo '</form>';

        echo '</div>';
    }

    private function resetear_plugin() {
        global $wpdb;

        $table = $wpdb->prefix . 'publicaciones';

        // Borrar tabla
        $wpdb->query("DROP TABLE IF EXISTS `$table`");

        // Recrear tabla
        $this->db->create_table();

        echo '<div class="updated"><p>✔️ La base de datos ha sido reseteada y recreada correctamente.</p></div>';
    }

    // Formulario de alta de publicaciones. Repuebla los campos solo cuando el POST viene del botón "Guardar Publicación" (pub_guardar).
    private function mostrar_formulario() {

        // ¿El submit que ha llegado es el del formulario de alta?
        $from_create_form = isset($_POST['pub_guardar']);

        // Valores por defecto solo si venimos de pub_guardar (y ha habido error)
        $titulo_val  = ($from_create_form && isset($_POST['titulo']))
            ? esc_attr($_POST['titulo']) : '';

        $autores_val = ($from_create_form && isset($_POST['autores']))
            ? esc_textarea($_POST['autores']) : '';

        $anio_val    = ($from_create_form && isset($_POST['anio']))
            ? intval($_POST['anio']) : '';

        $revista_val = ($from_create_form && isset($_POST['revista']))
            ? esc_attr($_POST['revista']) : '';

        $tipo_sel    = ($from_create_form && isset($_POST['tipo_publicacion']))
            ? sanitize_text_field($_POST['tipo_publicacion']) : '';

        ?>
        <form method="post" enctype="multipart/form-data" style="max-width:600px;">
            <table class="form-table">
                <tr>
                    <th><label for="titulo">Título</label></th>
                    <td><input type="text" name="titulo" id="titulo"
                            class="regular-text" required
                            value="<?php echo $titulo_val; ?>"></td>
                </tr>
                <tr>
                    <th><label for="autores">Autores</label></th>
                    <td><textarea name="autores" id="autores" rows="3"
                                class="large-text"><?php echo $autores_val; ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="anio">Año</label></th>
                    <td><input type="number" name="anio" id="anio"
                            min="1900" max="2099" step="1"
                            value="<?php echo $anio_val ? esc_attr($anio_val) : ''; ?>"></td>
                </tr>
                <tr>
                    <th><label for="tipo_publicacion">Tipo de Publicación</label></th>
                    <td>
                        <select name="tipo_publicacion" id="tipo_publicacion" required>
                            <option value="">-- Seleccionar --</option>
                            <?php
                            foreach (TIPOS_PUBLICACION as $tipo) {
                                $selected = ($tipo_sel === $tipo) ? 'selected' : '';
                                echo '<option value="' . esc_attr($tipo) . '" ' . $selected . '>'
                                    . esc_html($tipo) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="pdf">Archivo PDF</label></th>
                    <td><input type="file" name="pdf" id="pdf" accept=".pdf" required></td>
                </tr>
                <tr>
                    <th><label for="bib">Archivo BibTeX (.bib)</label></th>
                    <td><input type="file" name="bib" id="bib" accept=".bib" required></td>
                </tr>
                <tr>
                    <th><label for="revista">Revista</label></th>
                    <td>
                        <input type="text" name="revista" id="revista"
                            class="regular-text" value="<?php echo $revista_val; ?>">
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="pub_guardar"
                    class="button button-primary" value="Guardar Publicación">
            </p>
        </form>
        <?php
    }


    /**
     * Guarda una publicación y gestiona las subidas de PDF/BIB.
     *
     * - Valida/sanea los campos.
     * - Usa un prefijo uniqid() para evitar colisiones.
     * - Si la ruta final (URL) supera 255 caracteres, guarda un aviso en $this->form_notice y NO guarda nada.
     */
    private function guardar_publicacion() {
        global $wpdb;
        $table = $wpdb->prefix . 'publicaciones';

        // Usar la carpeta de subidas estándar de WordPress
        $uploads = wp_upload_dir();

        // Crear una subcarpeta "publicaciones" separada por año
        $year = !empty($_POST['anio']) ? intval($_POST['anio']) : date('Y');
        $upload_dir = $uploads['basedir'] . '/publicaciones/' . $year . '/';
        $upload_url = $uploads['baseurl'] . '/publicaciones/' . $year . '/';

        // Crear carpeta si no existe
        if ( ! file_exists( $upload_dir ) ) {
            wp_mkdir_p( $upload_dir );
        }

        // --- Validar que haya PDF y BIB correctos ---
        if (
            empty($_FILES['pdf']['name']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK ||
            empty($_FILES['bib']['name']) || $_FILES['bib']['error'] !== UPLOAD_ERR_OK
        ) {
            $this->form_notice = '<div class="notice notice-error"><p>❌ Debes seleccionar un archivo PDF y un archivo BibTeX válidos.</p></div>';
            return false;
        }

        $pdf_orig = $_FILES['pdf']['name'];
        $bib_orig = $_FILES['bib']['name'];

        // Opcional: comprobar extensión
        $pdf_ext = strtolower(pathinfo($pdf_orig, PATHINFO_EXTENSION));
        $bib_ext = strtolower(pathinfo($bib_orig, PATHINFO_EXTENSION));
        if ($pdf_ext !== 'pdf' || $bib_ext !== 'bib') {
            $this->form_notice = '<div class="notice notice-error"><p>❌ Las extensiones de los archivos deben ser .pdf y .bib respectivamente.</p></div>';
            return false;
        }

        // --- Generar nombres finales con prefijo único ---
        $prefix = uniqid('', false); // prefijo corto para que PDF y BIB compartan el mismo
        $sep    = '-';

        $pdf_name = $prefix . $sep . basename($pdf_orig);
        $bib_name = $prefix . $sep . basename($bib_orig);

        // --- Comprobar longitud máxima de la ruta (pdf_path/bib_path en BD = VARCHAR(255)) ---
        $maxPathLen = 255;

        $pdf_url_candidate = $upload_url . $pdf_name;
        $bib_url_candidate = $upload_url . $bib_name;

        if (function_exists('mb_strlen')) {
            $len_pdf = mb_strlen($pdf_url_candidate, 'UTF-8');
            $len_bib = mb_strlen($bib_url_candidate, 'UTF-8');
        } else {
            $len_pdf = strlen($pdf_url_candidate);
            $len_bib = strlen($bib_url_candidate);
        }

        if ($len_pdf > $maxPathLen || $len_bib > $maxPathLen) {
            $this->form_notice = '<div class="notice notice-error"><p>❌ El nombre de los archivos PDF/BibTeX es demasiado largo y la ruta completa supera el límite de 255 caracteres que admite la base de datos.<br>
            Por favor, renombra los archivos en tu ordenador con un nombre más corto (tanto el PDF como el BibTeX) y vuelve a subirlos.</p></div>';
            return false;
        }

        // --- Si todo está OK, movemos archivos ---
        $pdf_dest = $upload_dir . $pdf_name;
        $bib_dest = $upload_dir . $bib_name;

        if ( ! move_uploaded_file($_FILES['pdf']['tmp_name'], $pdf_dest) ) {
            $this->form_notice = '<div class="notice notice-error"><p>❌ Error al mover el archivo PDF al directorio de destino.</p></div>';
            return false;
        }

        if ( ! move_uploaded_file($_FILES['bib']['tmp_name'], $bib_dest) ) {
            @unlink($pdf_dest); // limpiar si falla el BibTeX
            $this->form_notice = '<div class="notice notice-error"><p>❌ Error al mover el archivo BibTeX al directorio de destino.</p></div>';
            return false;
        }

        $pdf_path = $upload_url . $pdf_name;
        $bib_path = $upload_url . $bib_name;

        // --- Obtener y validar el tipo de publicación ---
        $tipo_publicacion = isset($_POST['tipo_publicacion']) ? sanitize_text_field($_POST['tipo_publicacion']) : '';
        if (!in_array($tipo_publicacion, TIPOS_PUBLICACION)) {
            $tipo_publicacion = null; // o manejar error según prefieras
        }

        // --- Título: truncar para asegurarnos que cabe en VARCHAR(255) ---
        $titulo_raw = isset($_POST['titulo']) ? $_POST['titulo'] : '';
        if (method_exists($this->db, 'truncar_cadena')) {
            // si la tienes en la clase DB, úsala
            $titulo_db = $this->db->truncar_cadena($titulo_raw, 255);
        } else {
            $titulo_db = substr(sanitize_text_field($titulo_raw), 0, 255);
        }

        // --- Insertar en la base de datos ---
        $insert_ok = $wpdb->insert(
            $table,
            [
                'titulo'          => $titulo_db,
                'autores'         => sanitize_textarea_field($_POST['autores']),
                'anio'            => intval($_POST['anio']),
                'tipo_publicacion'=> $tipo_publicacion,
                'pdf_path'        => $pdf_path,
                'bib_path'        => $bib_path,
                'revista'         => isset($_POST['revista']) ? sanitize_text_field($_POST['revista']) : null
            ]
        );

        if ($insert_ok === false) {
            // Si falla la BD, borramos los ficheros para no dejar basura
            @unlink($pdf_dest);
            @unlink($bib_dest);
            $this->form_notice = '<div class="notice notice-error"><p>❌ Error al guardar la publicación en la base de datos: ' . esc_html($wpdb->last_error) . '</p></div>';
            return false;
        }

        // Éxito: mensaje verde
        $this->form_notice = '<div class="notice notice-success"><p>✅ Publicación guardada correctamente.</p></div>';
        // Limpiar los valores del formulario
        $_POST = [];

        return true;
    }

    // Lista las publicaciones con búsqueda, filtro por año y paginación.
    private function mostrar_listado($busqueda = '', $filtro_anyo = '', $pagina_actual = 1, $items_por_pagina = 20){
        global $wpdb;
        $table = $wpdb->prefix . 'publicaciones';

        $where = [];
        $params = [];

        // Filtrado por búsqueda en título o autores
        if ($busqueda) {
            $where[] = "(titulo LIKE %s OR autores LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($busqueda) . '%';
            $params[] = '%' . $wpdb->esc_like($busqueda) . '%';
        }

        // Filtrado por año
        if ($filtro_anyo) {
            $where[] = "anio = %d";
            $params[] = $filtro_anyo;
        }

        // Construir SQL final
        $sql = "SELECT * FROM $table";
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY fecha_creacion DESC";

        // Paginación
        $offset = ($pagina_actual - 1) * $items_por_pagina;
        $sql .= " LIMIT $offset, $items_por_pagina";

        // Importante: preparar SOLO si hay %s/%d en $sql.
        $publicaciones = $wpdb->get_results($wpdb->prepare($sql, ...$params));

        if ( empty($publicaciones) ) {
            echo '<p>No hay publicaciones registradas aún.</p>';
            return;
        }

        echo '<table class="widefat fixed striped" style="max-width: 1000px;">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Título</th>';
        echo '<th>Autores</th>';
        echo '<th>Año</th>';
        echo '<th>PDF</th>';
        echo '<th>BibTeX</th>';
        echo '<th>Fecha</th>';
        echo '<th>Revista</th>';
        echo '<th>Tipo</th>';
        echo '<th>Acciones</th>';
        echo '</tr></thead><tbody>';

        foreach ( $publicaciones as $pub ) {
            $pdf_link = $pub->pdf_path ? '<a href="'.esc_url($pub->pdf_path).'" target="_blank">Ver PDF</a>' : '-';
            $bib_link = $pub->bib_path ? '<a href="'.esc_url($pub->bib_path).'" target="_blank">Ver .bib</a>' : '-';

            echo '<tr>';
            echo '<td>'.esc_html($pub->id).'</td>';
            echo '<td>'.esc_html($pub->titulo).'</td>';
            echo '<td>'.esc_html($pub->autores).'</td>';
            echo '<td>'.esc_html($pub->anio).'</td>';
            echo '<td>'.$pdf_link.'</td>';
            echo '<td>'.$bib_link.'</td>';
            echo '<td>'.esc_html($pub->fecha_creacion).'</td>';
            echo '<td>'.esc_html($pub->revista).'</td>';
            echo '<td>'.esc_html($pub->tipo_publicacion).'</td>';

            // Enlaces de acción (edición/eliminación) conservando filtros y página actual
            $edit_link = add_query_arg(
                [
                    'page'       => 'publicaciones',
                    'editar_id'  => $pub->id,
                    'pub_buscar' => $busqueda,
                    'pub_anyo'   => $filtro_anyo,
                    'pub_pagina' => $pagina_actual,
                ],
                admin_url('admin.php')
            );

            $delete_link = add_query_arg(
                [
                    'page'       => 'publicaciones',
                    'borrar_id'  => $pub->id,
                    'pub_buscar' => $busqueda,
                    'pub_anyo'   => $filtro_anyo,
                    'pub_pagina' => $pagina_actual,
                ],
                admin_url('admin.php')
            );

            echo '<td>';
                echo '<a href="'.esc_url($edit_link).'" class="button button-small">Editar</a> ';
                echo '<a href="'.esc_url($delete_link).'" class="button button-small" onclick="return confirm(\'¿Seguro que quieres eliminar esta publicación?\');">Eliminar</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Paginación (conteo total)
        $total_resultados = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table" . ($where ? " WHERE " . implode(" AND ", $where) : ""), ...$params
        ));
        $total_paginas = ceil($total_resultados / $items_por_pagina);

        if ($total_paginas > 1) {
            echo '<div style="margin-top:10px;">';
            for ($i = 1; $i <= $total_paginas; $i++) {

                // Construimos la URL desde admin.php para NO arrastrar editar_id ni borrar_id
                $link = add_query_arg(
                    [
                        'page'       => 'publicaciones',
                        'pub_pagina' => $i,
                        'pub_buscar' => $busqueda,
                        'pub_anyo'   => $filtro_anyo,
                    ],
                    admin_url('admin.php')
                );

                $clase = ($i == $pagina_actual) ? 'button button-primary' : 'button';
                echo '<a href="' . esc_url($link) . '" class="' . $clase . '" style="margin-right:5px;">' . $i . '</a>';
            }
            echo '</div>';
        }

    }

    // Elimina todas las publicaciones de la tabla
    private function vaciar_publicaciones() {
        global $wpdb;
        $table = $wpdb->prefix . 'publicaciones';

        // Borrar todos los registros
        $wpdb->query("TRUNCATE TABLE $table");

        echo '<div class="notice notice-warning"><p>✅ Todas las publicaciones han sido eliminadas de la base de datos.</p></div>';
    }

    // Elimina una publicación concreta y borra sus archivos asociados
    private function borrar_publicacion($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'publicaciones';

        // Obtener rutas de archivos
        $pub = $wpdb->get_row($wpdb->prepare("SELECT pdf_path, bib_path FROM $table WHERE id=%d", $id));
        if ($pub) {
            // Borrar archivos si existen
            if ( $pub->pdf_path && file_exists(ABSPATH . str_replace(site_url().'/', '', $pub->pdf_path)) ) {
                unlink(ABSPATH . str_replace(site_url().'/', '', $pub->pdf_path));
            }
            if ( $pub->bib_path && file_exists(ABSPATH . str_replace(site_url().'/', '', $pub->bib_path)) ) {
                unlink(ABSPATH . str_replace(site_url().'/', '', $pub->bib_path));
            }
        }

        // Borrar de la base de datos
        $wpdb->delete($table, ['id' => $id]);

        echo '<div class="notice notice-success"><p>✅ Publicación eliminada correctamente.</p></div>';
    }

    // Muestra el formulario de edición de una publicación
    private function editar_publicacion_form($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'publicaciones';

        $pub = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
        if (!$pub) {
            echo '<div class="notice notice-error"><p>Publicación no encontrada.</p></div>';
            return;
        }

        // Si se envió el formulario de edición
        if ( isset($_POST['pub_editar_guardar']) ) {
            $this->guardar_edicion($id);
            $pub = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id)); // refrescar datos
            
            // Si la edición fue correcta, no mostramos el formulario de nuevo
            if ($this->edit_success) {
            return;
            }
        }

        ?>
        <h2>Editar Publicación</h2>
        <form method="post" enctype="multipart/form-data" style="max-width:600px;">
            <table class="form-table">
                <tr>
                    <th><label for="titulo">Título</label></th>
                    <td><input type="text" name="titulo" id="titulo" class="regular-text" value="<?php echo esc_attr($pub->titulo); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="autores">Autores</label></th>
                    <td><textarea name="autores" id="autores" rows="3" class="large-text"><?php echo esc_textarea($pub->autores); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="anio">Año</label></th>
                    <td><input type="number" name="anio" id="anio" min="1900" max="2099" step="1" value="<?php echo esc_attr($pub->anio); ?>"></td>
                </tr>
                <tr>
                    <th><label for="pdf">Archivo PDF (opcional)</label></th>
                    <td>
                        <input type="file" name="pdf" id="pdf" accept=".pdf">
                        <p class="description">
                            Deja este campo vacío si no quieres cambiar el PDF.
                        </p>
                        <?php if ( !empty($pub->pdf_path) ) : ?>
                            <p>PDF actual: <a href="<?php echo esc_url($pub->pdf_path); ?>" target="_blank">Ver PDF</a></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="bib">Archivo BibTeX (.bib, opcional)</label></th>
                    <td>
                        <input type="file" name="bib" id="bib" accept=".bib">
                        <p class="description">
                            Deja este campo vacío si no quieres cambiar el BibTeX.
                        </p>
                        <?php if ( !empty($pub->bib_path) ) : ?>
                            <p>BibTeX actual: <a href="<?php echo esc_url($pub->bib_path); ?>" target="_blank">Ver .bib</a></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="revista">Revista (opcional)</label></th>
                    <td><input type="text" name="revista" id="revista" class="regular-text" value="<?php echo esc_attr($pub->revista); ?>"></td>
                </tr>
                <tr>
                    <th><label for="tipo_publicacion">Tipo de Publicación</label></th>
                    <td>
                        <select name="tipo_publicacion" id="tipo_publicacion">
                            <!-- Opción vacía para poder limpiar el campo -->
                            <option value="">-- Sin especificar --</option>
                            <?php
                            foreach (TIPOS_PUBLICACION as $tipo) {
                                $selected = ($pub->tipo_publicacion === $tipo) ? 'selected' : '';
                                echo '<option value="' . esc_attr($tipo) . '" ' . $selected . '>' . esc_html($tipo) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="pub_editar_guardar" class="button button-primary" value="Guardar cambios">
            </p>
        </form>
        <?php
    }

    // Guarda los cambios de una publicación (texto + reemplazo de ficheros si se suben).
    private function guardar_edicion($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'publicaciones';

        // Validar tipo de publicación
        $tipo_post = isset($_POST['tipo_publicacion']) ? sanitize_text_field($_POST['tipo_publicacion']) : '';
        $tipo_publicacion = in_array($tipo_post, TIPOS_PUBLICACION) ? $tipo_post : null;

        // Datos básicos a actualizar
        $nuevo_anio = intval($_POST['anio']);

        $data = [
            'titulo'          => sanitize_text_field($_POST['titulo']),
            'autores'         => sanitize_textarea_field($_POST['autores']),
            'anio'            => $nuevo_anio,
            'tipo_publicacion'=> $tipo_publicacion,
            'revista'         => isset($_POST['revista']) ? sanitize_text_field($_POST['revista']) : null
        ];

        // Datos actuales de la publicación
        $pub = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
        if ( !$pub ) {
            echo '<div class="notice notice-error"><p>❌ Publicación no encontrada.</p></div>';
            return;
        }

        $anio_antiguo = intval($pub->anio);

        $uploads = wp_upload_dir();
        $upload_dir_nuevo = $uploads['basedir'] . '/publicaciones/' . $nuevo_anio . '/';
        $upload_url_nuevo = $uploads['baseurl'] . '/publicaciones/' . $nuevo_anio . '/';

        if ( ! file_exists( $upload_dir_nuevo ) ) {
            wp_mkdir_p( $upload_dir_nuevo );
        }

        // Flags para saber si hemos subido ficheros nuevos
        $subido_pdf = false;
        $subido_bib = false;

        // --- Reemplazar PDF si se sube uno nuevo ---
        if ( isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK ) {
            // Borrar antiguo si existe
            if ( $pub->pdf_path && file_exists(ABSPATH . str_replace(site_url().'/', '', $pub->pdf_path)) ) {
                unlink(ABSPATH . str_replace(site_url().'/', '', $pub->pdf_path));
            }
            $pdf_name = uniqid('', false) . '-' . basename($_FILES['pdf']['name']);
            $pdf_dest = $upload_dir_nuevo . $pdf_name;
            if ( move_uploaded_file($_FILES['pdf']['tmp_name'], $pdf_dest) ) {
                $data['pdf_path'] = $upload_url_nuevo . $pdf_name;
                $subido_pdf = true;
            }
        }

        // --- Reemplazar BibTeX si se sube uno nuevo ---
        if ( isset($_FILES['bib']) && $_FILES['bib']['error'] === UPLOAD_ERR_OK ) {
            // Borrar antiguo si existe
            if ( $pub->bib_path && file_exists(ABSPATH . str_replace(site_url().'/', '', $pub->bib_path)) ) {
                unlink(ABSPATH . str_replace(site_url().'/', '', $pub->bib_path));
            }
            $bib_name = uniqid('', false) . '-' . basename($_FILES['bib']['name']);
            $bib_dest = $upload_dir_nuevo . $bib_name;
            if ( move_uploaded_file($_FILES['bib']['tmp_name'], $bib_dest) ) {
                $data['bib_path'] = $upload_url_nuevo . $bib_name;
                $subido_bib = true;
            }
        }

        // Si NO se han subido ficheros nuevos, pero el año ha cambiado, movemos los archivos existentes a la carpeta del año nuevo 
        if ( $anio_antiguo !== $nuevo_anio ) {

            // Mover PDF existente
            if ( !$subido_pdf && !empty($pub->pdf_path) ) {
                $ruta_rel_pdf = str_replace(site_url().'/', '', $pub->pdf_path);
                $pdf_antiguo_abs = ABSPATH . $ruta_rel_pdf;

                if ( file_exists($pdf_antiguo_abs) ) {
                    $nombre_pdf = basename($pdf_antiguo_abs);
                    $pdf_nuevo_abs = $upload_dir_nuevo . $nombre_pdf;

                    // Crear dir por si acaso
                    if ( !file_exists($upload_dir_nuevo) ) {
                        wp_mkdir_p($upload_dir_nuevo);
                    }

                    if ( rename($pdf_antiguo_abs, $pdf_nuevo_abs) ) {
                        $data['pdf_path'] = $upload_url_nuevo . $nombre_pdf;
                    }
                }
            }

            // Mover BibTeX existente
            if ( !$subido_bib && !empty($pub->bib_path) ) {
                $ruta_rel_bib = str_replace(site_url().'/', '', $pub->bib_path);
                $bib_antiguo_abs = ABSPATH . $ruta_rel_bib;

                if ( file_exists($bib_antiguo_abs) ) {
                    $nombre_bib = basename($bib_antiguo_abs);
                    $bib_nuevo_abs = $upload_dir_nuevo . $nombre_bib;

                    if ( !file_exists($upload_dir_nuevo) ) {
                        wp_mkdir_p($upload_dir_nuevo);
                    }

                    if ( rename($bib_antiguo_abs, $bib_nuevo_abs) ) {
                        $data['bib_path'] = $upload_url_nuevo . $nombre_bib;
                    }
                }
            }
        }

        $wpdb->update($table, $data, ['id' => $id]);

        $this->edit_success = true;

        echo '<div class="notice notice-success"><p>✅ Publicación actualizada correctamente.</p></div>';
    }

    /**
     * Construye un nombre de fichero seguro y no demasiado largo,
     * conservando la extensión (.pdf / .bib).
     *
     * $original_name: nombre original del archivo (con extensión).
     * $prefix: prefijo único (por ejemplo, uniqid().'-').
     * $maxTotal: longitud máxima PERMITIDA para el NOMBRE DEL FICHERO
     *            (sin incluir $upload_url). Lo calcularemos fuera.
     */
    private function build_safe_filename($original_name, $prefix = '', $maxTotal = 255) {
        // Extensión en minúsculas (pdf, bib, etc.)
        $ext  = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        // Nombre sin extensión
        $base = pathinfo($original_name, PATHINFO_FILENAME);

        // Normalizar un poco el nombre (eliminar caracteres raros)
        $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
        $base = preg_replace('/[^A-Za-z0-9 _.-]/', '', $base);
        $base = preg_replace('/\s+/', ' ', $base);
        $base = trim($base);

        // Espacio que nos queda para la base, restando prefijo + punto + extensión
        $extra = strlen($prefix) + ($ext ? (1 + strlen($ext)) : 0);
        $maxBase = $maxTotal - $extra;
        if ($maxBase < 10) {
            $maxBase = 10; // margen de seguridad
        }

        // Recortar base si hace falta
        if (mb_strlen($base) > $maxBase) {
            $base = mb_substr($base, 0, $maxBase);
        }

        return $prefix . $base . ($ext ? '.' . $ext : '');
    }

    // Ejecuta el script de Python que rellena el campo 'revista' a partir de los .bib en wp_publicaciones.
    private function ejecutar_script_bibtex() {
        $python = "/bin/python3";
        $script = "/home/maria/Local Sites/prueba/app/public/resources_plugins/bib_extraction.py";

        if (file_exists($script)) {
            $cmd = $python . ' ' . escapeshellarg($script);
            exec($cmd . " > /dev/null 2>&1 &");
        }
    }

    // Importación masiva de publicaciones desde una ruta base con subcarpetas por año.
    public function volcar_publicaciones($ruta_origen) {
        global $wpdb;
        $table   = $wpdb->prefix . 'publicaciones';

        // Carpeta de subida estándar WordPress
        $uploads = wp_upload_dir();
        $archivos_volcados = 0;

        // Recorrer subcarpetas por año (2019, 2020, 2025, ...)
        foreach (glob($ruta_origen . '/*', GLOB_ONLYDIR) as $carpeta_anyo) {
            $anyo = basename($carpeta_anyo);

            $upload_dir = $uploads['basedir'] . '/publicaciones/' . $anyo . '/';
            $upload_url = $uploads['baseurl'] . '/publicaciones/' . $anyo . '/';

            if (!file_exists($upload_dir)) {
                wp_mkdir_p($upload_dir);
            }

            // En vez de glob("*.pdf"), usamos scandir() y filtramos
            $entries = scandir($carpeta_anyo);
            if ($entries === false) {
                continue; // no se pudo leer la carpeta, siguiente año
            }

            // Longitud máxima total que admite la BD (VARCHAR(255))
            $maxUrlLen = 255;
            // Máxima longitud permitida para el NOMBRE DE FICHERO (sin URL)
            $maxFilenameLen = $maxUrlLen - strlen($upload_url);
            if ($maxFilenameLen < 50) {
                $maxFilenameLen = 50; // por si acaso
            }

            foreach ($entries as $entry) {
                // Ignorar '.' y '..'
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $pdf_path_origen = $carpeta_anyo . '/' . $entry;

                // Solo ficheros regulares
                if (!is_file($pdf_path_origen)) {
                    continue;
                }

                // Solo extensión .pdf (case-insensitive)
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    continue;
                }

                // Nombre base sin extensión
                $nombre = pathinfo($entry, PATHINFO_FILENAME);

                // Construir ruta al .bib asociado
                $bib_path_origen = $carpeta_anyo . '/' . $nombre . '.bib';

                // Si no hay .bib, lo ignoramos
                if (!file_exists($bib_path_origen)) {
                    continue;
                }

                // Extraer autores y título desde el nombre: "Autores | Título"
                if (strpos($nombre, '|') !== false) {
                    list($autores, $titulo) = explode('|', $nombre, 2);
                    $autores = trim($autores);
                    $titulo  = trim($titulo);
                } else {
                    $autores = '';
                    $titulo  = $nombre;
                }

                // Prefijo único compartido por PDF y BIB
                $uniq = uniqid('', false) . '-';

                $nombre_para_archivo = $autores ? ($autores . '-' . $titulo) : $titulo;

                // Construir nombres seguros manteniendo la extensión .pdf / .bib
                $pdf_dest_name = $this->build_safe_filename($nombre_para_archivo . '.pdf', $uniq, $maxFilenameLen);
                $bib_dest_name = $this->build_safe_filename($nombre_para_archivo . '.bib', $uniq, $maxFilenameLen);

                $pdf_url = $upload_url . $pdf_dest_name;
                $bib_url = $upload_url . $bib_dest_name;

                // Doble check: si por cualquier motivo se pasa de 255, recortamos un poco más la base
                while (strlen($pdf_url) > $maxUrlLen || strlen($bib_url) > $maxUrlLen) {
                    $cut = 5; // recortar de 5 en 5 caracteres
                    foreach (['pdf_dest_name', 'bib_dest_name'] as $varName) {
                        $name = $$varName;
                        $dotPos = strrpos($name, '.');
                        if ($dotPos === false) {
                            // sin extensión, recortamos al final sin más
                            $name = substr($name, 0, max(0, strlen($name) - $cut));
                        } else {
                            $base = substr($name, 0, $dotPos);
                            $ext2 = substr($name, $dotPos); // incluye el punto
                            $base = substr($base, 0, max(0, strlen($base) - $cut));
                            $name = $base . $ext2;
                        }
                        $$varName = $name;
                    }
                    $pdf_url = $upload_url . $pdf_dest_name;
                    $bib_url = $upload_url . $bib_dest_name;
                }

                $pdf_dest = $upload_dir . $pdf_dest_name;
                $bib_dest = $upload_dir . $bib_dest_name;

                // Copiar ficheros al wp-content/uploads/publicaciones/<año>/
                copy($pdf_path_origen, $pdf_dest);
                copy($bib_path_origen, $bib_dest);

                // Insertar en la base de datos
                $insert_ok = $wpdb->insert(
                    $table,
                    [
                        'titulo'           => sanitize_text_field($titulo),
                        'autores'          => sanitize_textarea_field($autores),
                        'anio'             => intval($anyo),
                        'pdf_path'         => $pdf_url,
                        'bib_path'         => $bib_url,
                        'revista'          => null,
                        'tipo_publicacion' => null,
                    ]
                );

                if ( $insert_ok !== false ) {
                    $archivos_volcados++;
                } else {
                    error_log("Error insertando publicación ($anyo): " . $wpdb->last_error);
                }
            }
        }

        echo '<div class="notice notice-success"><p>✅ Se han volcado ' . intval($archivos_volcados) . ' publicaciones correctamente.</p></div>';

        // Rellenar 'revista' desde los .bib después del volcado
        $this->ejecutar_script_bibtex();
    }


}
