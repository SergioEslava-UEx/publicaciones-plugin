<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Publicaciones_List_Table extends WP_List_Table {

    private $data = [];

    public function __construct($data) {
        parent::__construct([
            'singular' => 'publicacion',
            'plural'   => 'publicaciones',
            'ajax'     => false
        ]);
        $this->data = $data;
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
            case 'titulo':
            case 'autores':
            case 'anio':
            case 'fecha_creacion':
                return $item->$column_name;
            case 'pdf_path':
                return $item->pdf_path ? '<a href="'.esc_url($item->pdf_path).'" target="_blank">Ver PDF</a>' : '-';
            case 'bib_path':
                return $item->bib_path ? '<a href="'.esc_url($item->bib_path).'" target="_blank">Ver .bib</a>' : '-';
            case 'acciones':
                $edit_link = admin_url('admin.php?page=publicaciones&editar_id=' . $item->id);
                $delete_link = admin_url('admin.php?page=publicaciones&borrar_id=' . $item->id);
                return '<a href="'.esc_url($edit_link).'" class="button button-small">Editar</a> ' .
                       '<a href="'.esc_url($delete_link).'" class="button button-small" onclick="return confirm(\'¿Seguro que quieres eliminar esta publicación?\');">Eliminar</a>';
            default:
                return print_r($item,true);
        }
    }

    public function get_columns() {
        return [
            'id' => 'ID',
            'titulo' => 'Título',
            'autores' => 'Autores',
            'anio' => 'Año',
            'pdf_path' => 'PDF',
            'bib_path' => 'BibTeX',
            'fecha_creacion' => 'Fecha',
            'acciones' => 'Acciones'
        ];
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = ['id'=>'id','titulo'=>'titulo','anio'=>'anio','fecha_creacion'=>'fecha_creacion'];
        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->items = $this->data;
    }

}
