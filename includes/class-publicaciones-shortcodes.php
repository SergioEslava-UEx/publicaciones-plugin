<?php

function robolab_publicaciones_shortcode() {
    global $wpdb;

    // Campos recibidos desde el formulario
    $fields = array(
        'pub_search'  => isset($_GET['pub_search'])  ? sanitize_text_field($_GET['pub_search'])  : '',
        'pub_anio'    => isset($_GET['pub_anio']) && $_GET['pub_anio'] !== '' ? intval($_GET['pub_anio']) : 0,
        'pub_revista' => isset($_GET['pub_revista']) ? sanitize_text_field($_GET['pub_revista']) : '',
    );

    $table = $wpdb->prefix . 'publicaciones';

    // Construcción dinámica del WHERE
    $where  = [];
    $params = [];

    if ( $fields['pub_search'] !== '' ) {
        $where[]  = "titulo LIKE %s";
        $params[] = '%' . $wpdb->esc_like($fields['pub_search']) . '%';
    }

    if ( $fields['pub_anio'] !== 0 ) {
        $where[]  = "anio = %d";
        $params[] = $fields['pub_anio'];
        echo '<p><strong>AAAAA</p></strong>';
    }

    if ( $fields['pub_revista'] !== '' ) {
        $where[]  = "revista LIKE %s";
        $params[] = '%' . $wpdb->esc_like($fields['pub_revista']) . '%';
    }

    // Generar SQL base
    $sql = "SELECT * FROM $table";
    if ( ! empty($where) ) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY anio DESC";

    // SQL final preparada (con valores “bindeados”)
    $prepared_sql = !empty($params)
        ? $wpdb->prepare($sql, $params)
        : $sql;

    // Ejecutar SQL
    $rows = $wpdb->get_results($prepared_sql);

    // ----------------------------------------------------------------------
    // 📌 FORMULARIO HTML
    // ----------------------------------------------------------------------

    ob_start();
    ?>

    <form method="GET" class="robolab-filtros">
        <div>
            <label>Buscar título:</label>
            <input type="text" name="pub_search" value="<?php echo esc_attr($fields['pub_search']); ?>">
        </div>

        <div>
            <label>Año:</label>
            <input type="number" name="pub_anio" value="<?php echo esc_attr($fields['pub_anio']); ?>">
        </div>

        <div>
            <label>Revista:</label>
            <input type="text" name="pub_revista" value="<?php echo esc_attr($fields['pub_revista']); ?>">
        </div>

        <button type="submit">Filtrar</button>
    </form>

    <hr>

    <?php

    // ----------------------------------------------------------------------
    // 📌 MOSTRAR SQL PARA DEPURACIÓN
    // ----------------------------------------------------------------------
    /*
    echo '<p><strong>SQL ejecutada:
    </strong> <code>' . esc_html($prepared_sql) . '</code></p>';
    */

    // ----------------------------------------------------------------------
    // 📌 RESULTADOS
    // ----------------------------------------------------------------------

    if ( empty($rows) ) {
        echo "<p>No se encontraron publicaciones.</p>";
        return ob_get_clean();
    }

    echo "<ul class='robolab-publicaciones'>";
    foreach ( $rows as $r ) {
        echo "<li><strong>{$r->titulo}</strong> – {$r->revista} ({$r->anio})</li>";
    }
    echo "</ul>";

    return ob_get_clean();
}

add_shortcode('publicaciones', 'robolab_publicaciones_shortcode');
