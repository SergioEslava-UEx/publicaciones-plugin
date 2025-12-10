<?php


function robolab_publicaciones_shortcode( $atts ) {
    global $wpdb;

    // Parámetros del shortcode: ejemplo [publicaciones tipo="journal"]
    $atts = shortcode_atts( array(
        'tipo' => '',
        'year' => '',
        'search' => '',
    ), $atts );

    $table = $wpdb->prefix . 'publicaciones';

    // Construimos la query dinámicamente
    $where = array();
    $params = array();

    if ( ! empty( $atts['tipo'] ) ) {
        $where[] = "tipo = %s";
        $params[] = $atts['tipo'];
    }

    if ( ! empty( $atts['year'] ) ) {
        $where[] = "year = %d";
        $params[] = intval( $atts['year'] );
    }

    if ( ! empty( $atts['search'] ) ) {
        $where[] = "titulo LIKE %s";
        $params[] = '%' . $wpdb->esc_like( $atts['search'] ) . '%';
    }

    // Montamos la query final
    $sql = "SELECT * FROM $table";
    if ( ! empty( $where ) ) {
        $sql .= " WHERE " . implode( " AND ", $where );
    }

    // Ejecutar consulta
    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

    // Generar salida HTML
    if ( empty( $rows ) ) {
        return "<p>No se encontraron publicaciones.</p>";
    }

    $html = "<ul class='robolab-publicaciones'>";
    foreach ( $rows as $r ) {
        $html .= "<li><strong>{$r->titulo}</strong> – {$r->journal} ({$r->year})</li>";
    }
    $html .= "</ul>";

    return $html;
}
add_shortcode( 'publicaciones', 'robolab_publicaciones_shortcode' );
