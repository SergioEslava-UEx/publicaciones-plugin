<?php
if ( !defined('ABSPATH') ) exit;
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

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Registra el elemento de menú y la página del plugin en el administrador.
     *
     * Se ejecuta durante la construcción del menú del panel de WordPress
     * para añadir "Publicaciones" en el lateral.
     */
    public function add_admin_menu() {
        add_menu_page(
            'Publicaciones', // Título de la página
            'Publicaciones', // Texto del menú
            'manage_options', // Capacidad mínima requerida
            'publicaciones', // Slug
            [$this, 'render_admin_page'], // Callback que pinta la pantalla
            'dashicons-media-document', //Icono
            20 //Posicion
        );
    }

    /**
     * Renderiza la pantalla principal del plugin en el administrador.
     *
     * Flujo general:
     *  - Si llega una acción por POST (crear/editar/eliminar/importar), primero se valida el nonce (CSRF).
     *  - Después se muestran filtros/búsqueda, listado paginado y el formulario de alta/edición.
     */
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
        if ( isset($_GET['editar_id']) ) {
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
        $this->mostrar_formulario();

        // Formulario para vaciar la base de datos
        echo '<h2>⚠️ Vaciar todas las publicaciones</h2>';
        echo '<form method="post" onsubmit="return confirm(\'¿Seguro que quieres borrar todas las publicaciones? Esta acción no se puede deshacer.\');">';
        wp_nonce_field('pub_vaciar_nonce', 'pub_vaciar_nonce_field');
        echo '<input type="submit" name="pub_vaciar" class="button button-secondary" value="Vaciar base de datos">';
        echo '</form>';

        echo '<h2>Volcar publicaciones desde carpeta local</h2>';
        echo '<form method="post">';
        echo '<input type="text" name="ruta_volcado" placeholder="/ruta/a/publicaciones/" style="width:400px;"> ';
        echo '<input type="submit" name="pub_volcar" class="button button-primary" value="Volcar publicaciones">';
        echo '</form>';

        echo '<h2>🧨 Resetear completamente el plugin</h2>';
        echo '<p style="color:red;font-weight:bold;">¡Esto borrará la tabla y la recreará desde cero! Úsalo solo si sabes lo que haces.</p>';
        echo '<form method="post" onsubmit="return confirm(\'⚠️ Esto borrará toda la información y recreará la base de datos.\n\n¿Seguro que quieres continuar?\');">';
        wp_nonce_field('pub_reset_nonce', 'pub_reset_nonce_field');
        echo '<input type="submit" name="pub_reset" class="button button-primary" value="Resetear plugin por completo">';
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


    // Muestra el formulario de alta/edición de publicaciones
    private function mostrar_formulario() {
        ?>
        <form method="post" enctype="multipart/form-data" style="max-width:600px;">
            <table class="form-table">
                <tr>
                    <th><label for="titulo">Título</label></th>
                    <td><input type="text" name="titulo" id="titulo" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="autores">Autores</label></th>
                    <td><textarea name="autores" id="autores" rows="3" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="anio">Año</label></th>
                    <td><input type="number" name="anio" id="anio" min="1900" max="2099" step="1"></td>
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
                        <input type="text" name="revista" id="revista" class="regular-text"
                            value="<?php echo isset($publicacion->revista) ? esc_attr($publicacion->revista) : ''; ?>">
                    </td>
                </tr>

            </table>
            <p class="submit">
                <input type="submit" name="pub_guardar" class="button button-primary" value="Guardar Publicación">
            </p>
        </form>
        <?php
    }

    /**
     * Guarda una publicación y gestiona las subidas de PDF/BIB.
     *
     * - Valida/sanea los campos.
     * - Si hay archivos, los mueve a /wp-content/uploads/publicaciones/{AÑO}/ y construye las URLs.
     * - Inserta (o actualiza) la fila en la base de datos.
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

        // Validar y mover archivos
        $pdf_path = '';
        $bib_path = '';

        if ( isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK ) {
            $pdf_name = time() . '-' . basename($_FILES['pdf']['name']);
            $pdf_dest = $upload_dir . $pdf_name;
            move_uploaded_file($_FILES['pdf']['tmp_name'], $pdf_dest);
            $pdf_path = $upload_url . $pdf_name;
        }

        if ( isset($_FILES['bib']) && $_FILES['bib']['error'] === UPLOAD_ERR_OK ) {
            $bib_name = time() . '-' . basename($_FILES['bib']['name']);
            $bib_dest = $upload_dir . $bib_name;
            move_uploaded_file($_FILES['bib']['tmp_name'], $bib_dest);
            $bib_path = $upload_url . $bib_name;
        }

        // Insertar en la base de datos
        $wpdb->insert(
            $table,
            [
                'titulo' => sanitize_text_field($_POST['titulo']),
                'autores' => sanitize_textarea_field($_POST['autores']),
                'anio' => intval($_POST['anio']),
                'pdf_path' => $pdf_path,
                'bib_path' => $bib_path,
                'revista' => isset($_POST['revista']) ? sanitize_text_field($_POST['revista']) : null
            ]
        );

        echo '<div class="notice notice-success"><p>✅ Publicación guardada correctamente.</p></div>';
    }

    /**
     * Lista las publicaciones con búsqueda, filtro por año y paginación.
     *
     * - Búsqueda: en título y autores (LIKE).
     * - Filtro: por año exacto.
     * - Orden: fecha_creacion DESC.
     * - Paginación: LIMIT + OFFSET.
     */
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

            // Enlaces de acción (edición/eliminación)
            $edit_link = admin_url('admin.php?page=publicaciones&editar_id=' . $pub->id);
            $delete_link = admin_url('admin.php?page=publicaciones&borrar_id=' . $pub->id);

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
                $link = add_query_arg(['pub_pagina' => $i, 'pub_buscar' => $busqueda, 'pub_anyo' => $filtro_anyo]);
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
                    <td><input type="file" name="pdf" id="pdf" accept=".pdf"></td>
                </tr>
                <tr>
                    <th><label for="bib">Archivo BibTeX (.bib, opcional)</label></th>
                    <td><input type="file" name="bib" id="bib" accept=".bib"></td>
                </tr>
                <tr>
                    <th><label for="revista">Revista (opcional)</label></th>
                    <td><input type="text" name="revista" id="revista" class="regular-text" value="<?php echo esc_attr($pub->revista); ?>"></td>
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
/*  */
        $data = [
            'titulo' => sanitize_text_field($_POST['titulo']),
            'autores' => sanitize_textarea_field($_POST['autores']),
            'anio' => intval($_POST['anio']),
            'tipo_publicacion' => sanitize_textarea_field($_POST['tipo_publicacion']),
            'revista' => isset($_POST['revista']) ? sanitize_text_field($_POST['revista']) : null
        ];

        $pub = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
        $uploads = wp_upload_dir();
        $year = !empty($_POST['anio']) ? intval($_POST['anio']) : date('Y');
        $upload_dir = $uploads['basedir'] . '/publicaciones/' . $year . '/';
        $upload_url = $uploads['baseurl'] . '/publicaciones/' . $year . '/';

        if ( ! file_exists( $upload_dir ) ) wp_mkdir_p( $upload_dir );

        // Reemplazar PDF si se sube
        if ( isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK ) {
            if ( $pub->pdf_path && file_exists(ABSPATH . str_replace(site_url().'/', '', $pub->pdf_path)) ) {
                unlink(ABSPATH . str_replace(site_url().'/', '', $pub->pdf_path));
            }
            $pdf_name = time() . '-' . basename($_FILES['pdf']['name']);
            move_uploaded_file($_FILES['pdf']['tmp_name'], $upload_dir . $pdf_name);
            $data['pdf_path'] = $upload_url . $pdf_name;
        }

        // Reemplazar BibTeX si se sube
        if ( isset($_FILES['bib']) && $_FILES['bib']['error'] === UPLOAD_ERR_OK ) {
            if ( $pub->bib_path && file_exists(ABSPATH . str_replace(site_url().'/', '', $pub->bib_path)) ) {
                unlink(ABSPATH . str_replace(site_url().'/', '', $pub->bib_path));
            }
            $bib_name = time() . '-' . basename($_FILES['bib']['name']);
            move_uploaded_file($_FILES['bib']['tmp_name'], $upload_dir . $bib_name);
            $data['bib_path'] = $upload_url . $bib_name;
        }

        $wpdb->update($table, $data, ['id' => $id]);

        echo '<div class="notice notice-success"><p>✅ Publicación actualizada correctamente.</p></div>';
    }

    // Importación masiva de publicaciones desde una ruta base con subcarpetas por año.
    public function volcar_publicaciones($ruta_origen) {
        global $wpdb;
        $table = $wpdb->prefix . 'publicaciones';

        // Carpeta de subida estándar WordPress
        $uploads = wp_upload_dir();
        
        $archivos_volcados = 0;

        // Recorrer carpetas por año
        foreach ( glob($ruta_origen . '/*', GLOB_ONLYDIR) as $carpeta_anyo ) {
            $anyo = basename($carpeta_anyo);
            $upload_dir = $uploads['basedir'] . '/publicaciones/' . $anyo . '/';
            $upload_url = $uploads['baseurl'] . '/publicaciones/' . $anyo . '/';

            if ( ! file_exists($upload_dir) ) wp_mkdir_p($upload_dir);

            // Buscar archivos PDF y BibTeX
            $pdfs = glob($carpeta_anyo . '/*.pdf');

            foreach ( $pdfs as $pdf_path_origen ) {
                $nombre = basename($pdf_path_origen, '.pdf');
                $bib_path_origen = $carpeta_anyo . '/' . $nombre . '.bib';

                if ( !file_exists($bib_path_origen) ) continue; // ignorar si no hay .bib

                // Extraer autores y título desde el nombre
                if ( strpos($nombre, '|') !== false ) {
                    list($autores, $titulo) = explode('|', $nombre, 2);
                    $autores = trim($autores);
                    $titulo = trim($titulo);
                } else {
                    $autores = '';
                    $titulo = $nombre;
                }

                // Generar nombres únicos para WordPress
                $pdf_dest_name = time() . '-' . basename($pdf_path_origen);
                $bib_dest_name = time() . '-' . basename($bib_path_origen);

                $pdf_dest = $upload_dir . $pdf_dest_name;
                $bib_dest = $upload_dir . $bib_dest_name;

                copy($pdf_path_origen, $pdf_dest);
                copy($bib_path_origen, $bib_dest);

                // Insertar en la base de datos
                $wpdb->insert(
                    $table,
                    [
                        'titulo' => sanitize_text_field($titulo),
                        'autores' => sanitize_textarea_field($autores),
                        'anio' => intval($anyo),
                        'pdf_path' => $upload_url . $pdf_dest_name,
                        'bib_path' => $upload_url . $bib_dest_name,
                        'revista'  => null
                    ]
                );

                $archivos_volcados++;
            }
        }

        echo '<div class="notice notice-success"><p>✅ Se han volcado ' . $archivos_volcados . ' publicaciones correctamente.</p></div>';
    }



}
