<?php

if ( ! defined('ABSPATH') ) exit;

function robolab_publicaciones_shortcode() {
    global $wpdb;

    $table = $wpdb->prefix . 'publicaciones';

    // Cargamos todas las publicaciones para filtrar en cliente (JS)
    $rows = $wpdb->get_results("
        SELECT *
        FROM {$table}
        ORDER BY anio DESC, titulo ASC
    ");

    if ( ! $rows ) {
        return '<p>No se encontraron publicaciones.</p>';
    }

    // Sacamos años y tipos distintos
    $years = [];
    $tipos = [];

    foreach ( $rows as $r ) {
        if ( ! empty($r->anio) ) {
            $years[] = (int) $r->anio;
        }
        if ( ! empty($r->tipo_publicacion) ) {
            $tipos[] = trim($r->tipo_publicacion);
        }
    }

    $years = array_unique($years);
    rsort($years, SORT_NUMERIC);

    $tipos = array_unique($tipos);
    sort($tipos, SORT_NATURAL | SORT_FLAG_CASE);

    ob_start();
    ?>

    <div class="publicaciones-cientificas">

        <!-- Barra de filtros -->
        <div class="publicaciones-filtros">
            <!-- COLUMNA IZQUIERDA: año / texto / revista, EN COLUMNA -->
            <div class="filtros-principales">
                <div class="filtro-campo">
                    <label for="filtro-anio">Año</label>
                    <select id="filtro-anio">
                        <option value="todos">Todos</option>
                        <?php foreach ( $years as $year ): ?>
                            <option value="<?php echo esc_attr($year); ?>">
                                <?php echo esc_html($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-campo">
                    <label for="filtro-texto">Título / autor</label>
                    <input
                        type="text"
                        id="filtro-texto"
                        placeholder="Buscar por título o autor…"
                    >
                </div>

                <div class="filtro-campo">
                    <label for="filtro-fuente">Revista / congreso / libro</label>
                    <input
                        type="text"
                        id="filtro-fuente"
                        placeholder="Buscar por revista, congreso, libro…"
                    >
                </div>
            </div>

            <!-- COLUMNA DERECHA: tipos como checkboxes, EN COLUMNA -->
            <div class="filtro-tipo-wrapper">
                <div class="filtro-campo filtro-campo-tipo">
                    <span class="filtro-tipo-label">Tipo de publicación</span>
                    <div class="filtro-tipo-checkboxes">
                        <?php foreach ( $tipos as $tipo ):
                            $slug = strtolower(trim($tipo)); ?>
                            <label class="filtro-tipo-opcion">
                                <input
                                    type="checkbox"
                                    class="filtro-tipo-checkbox"
                                    value="<?php echo esc_attr($slug); ?>"
                                >
                                <span><?php echo esc_html($tipo); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de publicaciones agrupadas por año -->
        <div id="lista-publicaciones">
            <?php
            $current_year = null;

            foreach ( $rows as $r ):

                $year     = (int) $r->anio;
                $titulo   = isset($r->titulo)           ? $r->titulo           : '';
                $revista  = isset($r->revista)          ? $r->revista          : '';
                $autores  = isset($r->autores)          ? $r->autores          : '';
                $tipo_pub = isset($r->tipo_publicacion) ? $r->tipo_publicacion : '';

                if ( $year !== $current_year ) {
                    if ( $current_year !== null ) {
                        echo '</ul></div>';
                    }

                    $current_year = $year;
                    ?>
                    <div class="bloque-anio" data-anio="<?php echo esc_attr($year); ?>">
                        <h3><?php echo esc_html($year); ?></h3>
                        <ul>
                    <?php
                }

                ?>
                <li class="publicacion-item"
                    data-anio="<?php echo esc_attr($year); ?>"
                    data-fuente="<?php echo esc_attr( strtolower($revista) ); ?>"
                    data-tipo="<?php echo esc_attr( strtolower($tipo_pub) ); ?>">

                    <?php if ( ! empty($autores) ): ?>
                        <span class="autores-publicacion">
                            <?php echo esc_html($autores); ?>
                        </span> |
                    <?php endif; ?>

                    <strong class="titulo-publicacion">
                        <?php echo esc_html($titulo); ?>
                    </strong>

                    <?php if ( ! empty($revista) ): ?>
                        <span class="fuente-publicacion">
                            – <?php echo esc_html($revista); ?>
                        </span>
                    <?php endif; ?>

                    <?php
                    // Aquí puedes añadir enlaces a PDF / BibTeX si los tienes:
                    /*
                    if ( ! empty($r->pdf_url) ) {
                        echo " <a href='" . esc_url($r->pdf_url) . "' target='_blank' class='icono-pdf'>PDF</a>";
                    }
                    if ( ! empty($r->bibtex_url) ) {
                        echo " <a href='" . esc_url($r->bibtex_url) . "' target='_blank' class='icono-bibtex'>BibTeX</a>";
                    }
                    */
                    ?>
                </li>
                <?php

            endforeach;

            if ( $current_year !== null ) {
                echo '</ul></div>';
            }
            ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filtroAnio   = document.getElementById('filtro-anio');
        const filtroTexto  = document.getElementById('filtro-texto');
        const filtroFuente = document.getElementById('filtro-fuente');
        const checkTipos   = document.querySelectorAll('.filtro-tipo-checkbox');
        const bloques      = document.querySelectorAll('.bloque-anio');

        function filtrar() {
            const anioSeleccionado = (filtroAnio.value || 'todos').toLowerCase();
            const textoFiltro      = (filtroTexto.value || '').toLowerCase();
            const fuenteFiltro     = (filtroFuente.value || '').toLowerCase();

            const tiposSeleccionados = Array.from(checkTipos)
                .filter(cb => cb.checked)
                .map(cb => cb.value.toLowerCase());

            bloques.forEach(bloque => {
                const anioBloque = (bloque.dataset.anio || '').toLowerCase();
                let mostrarBloque = false;

                const items = bloque.querySelectorAll('li.publicacion-item');

                items.forEach(item => {
                    const textoItem  = item.textContent.toLowerCase();
                    const fuenteItem = (item.dataset.fuente || '').toLowerCase();
                    const tipoItem   = (item.dataset.tipo   || '').toLowerCase();

                    const coincideAnio   = (anioSeleccionado === 'todos' || anioBloque === anioSeleccionado);
                    const coincideTexto  = textoItem.includes(textoFiltro);
                    const coincideFuente = fuenteItem.includes(fuenteFiltro);
                    const coincideTipo   = (
                        tiposSeleccionados.length === 0 ||
                        tiposSeleccionados.includes(tipoItem)
                    );

                    const mostrarItem = coincideAnio && coincideTexto && coincideFuente && coincideTipo;

                    item.style.display = mostrarItem ? 'list-item' : 'none';
                    if (mostrarItem) mostrarBloque = true;
                });

                bloque.style.display = mostrarBloque ? 'block' : 'none';
            });
        }

        filtroAnio.addEventListener('change', filtrar);
        filtroTexto.addEventListener('input', filtrar);
        filtroFuente.addEventListener('input', filtrar);
        checkTipos.forEach(cb => cb.addEventListener('change', filtrar));
    });
    </script>

    <style>
    .publicaciones-cientificas {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        margin: 20px 0;
    }

    .publicaciones-filtros {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 1.5rem 5rem;
        padding: 1rem 1.25rem;
        border-radius: 8px;
        border: 1px solid #e2e2e2;
        background: #fafafa;
    }

    /* IZQUIERDA: filtros en columna */
    .filtros-principales {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        flex: 1 1 45%;
        max-width: 420px;
    }

    .filtro-campo {
        display: flex;
        flex-direction: column;
        width: 100%;
    }

    .filtro-campo label {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 4px;
        color: #444;
    }

    .filtro-campo select,
    .filtro-campo input {
        padding: 6px 8px;
        border-radius: 4px;
        border: 1px solid #d0d0d0;
        font-size: 0.9rem;
        width: 100%;
    }

    /* DERECHA: tipos en columna, uno debajo de otro */
    .filtro-tipo-wrapper {
        display: flex;
        justify-content: flex-end;
        align-items: flex-start;
        flex: 1 1 40%;
        min-width: 220px;
    }

    .filtro-campo-tipo {
        width: 100%;
    }

    .filtro-tipo-label {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 4px;
        color: #444;
        display: block;
    }

    .filtro-tipo-checkboxes {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .filtro-tipo-opcion {
        display: flex;
        align-items: center;
        gap: 0.1rem; /* menos espacio */
        font-size: 0.85rem;
        cursor: pointer;
    }

    .filtro-tipo-opcion input[type="checkbox"] {
        margin: 0;              
        transform: scale(0.95);  
    }

    #lista-publicaciones {
        margin-top: 24px;
    }

    .bloque-anio {
        margin-bottom: 32px;
    }

    .bloque-anio h3 {
        border-bottom: 2px solid #4CAF50;
        padding-bottom: 4px;
        margin-bottom: 10px;
        color: #333;
        font-size: 1.1rem;
    }

    .bloque-anio ul {
        list-style: none;
        padding-left: 0;
        margin: 0;
    }

    .publicacion-item {
        margin-bottom: 8px;
        padding: 8px 10px;
        border-radius: 6px;
        background: #f5f5f5;
        transition: background 0.15s ease, transform 0.15s ease;
    }

    .publicacion-item:hover {
        background: #ededed;
        transform: translateX(2px);
    }

    .autores-publicacion {
        font-size: 0.95rem;
        color: #555;
    }

    .titulo-publicacion {
        font-size: 0.95rem;
    }

    .fuente-publicacion {
        font-size: 0.9rem;
        color: #666;
    }

    .publicacion-item a img,
    .publicacion-item a.icono-pdf,
    .publicacion-item a.icono-bibtex {
        margin-left: 8px;
        vertical-align: middle;
        font-size: 0.85rem;
    }

    @media (max-width: 768px) {
        .publicaciones-filtros {
            flex-direction: column;
            align-items: stretch;
        }

        .filtros-principales,
        .filtro-tipo-wrapper {
            flex: 1 1 100%;
            max-width: 100%;
        }
    }

    /* Forzar estilo compacto de los checkboxes de tipo_publicacion */
    .publicaciones-cientificas .filtro-tipo-checkboxes {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .publicaciones-cientificas .filtro-tipo-opcion {
        display: inline-flex !important;
        align-items: center;
        justify-content: flex-start;
        gap: 0.15rem;              /* espacio mínimo entre checkbox y texto */
        padding: 0 !important;
        margin: 0 !important;
        text-align: left !important;
        width: auto !important;
    }

    .publicaciones-cientificas .filtro-tipo-opcion input[type="checkbox"] {
        margin: 0 4px 0 0 !important;  /* solo un pequeño margen a la derecha */
        transform: scale(0.95);
    }

    .publicaciones-cientificas .filtro-tipo-opcion span {
        margin: 0 !important;
    }

    /* Ensanchar el contenedor principal del shortcode */
    .publicaciones-cientificas {
        max-width: 900px !important;  
        margin-left: auto !important;
        margin-right: auto !important;
    }

    </style>

    <?php
    return ob_get_clean();
}

add_shortcode('publicaciones', 'robolab_publicaciones_shortcode');
