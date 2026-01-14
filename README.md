# Publicaciones Científicas (Plugin para WordPress)

Este repositorio contiene un plugin diseñado para gestionar publicaciones científicas desde el **panel de administración de WordPress**. Su propósito es proporcionar una solución estructurada para el registro, organización y mantenimiento de publicaciones con sus archivos asociados.


## Descripción general

El plugin habilita un nuevo elemento de menú en el área de administración que permite:

- Registrar publicaciones individuales con **título**, **autores**, **año**, **archivo PDF** y **archivo BibTeX**.
- Visualizar las publicaciones en un **listado** con búsqueda y filtrado por año, implementado mediante `WP_List_Table`.
- Editar o eliminar registros existentes.
- Realizar una **importación masiva** de publicaciones desde un directorio local organizado por años, automatizando la copia de ficheros y la creación de entradas en la base de datos.

Está orientado a entornos internos donde se requiere mantener un repositorio de publicaciones de manera sencilla y consistente.


## Estructura del plugin

publicaciones-plugin.php
includes/
├─ class-publicaciones-loader.php # Inicialización del plugin y gestión de hooks
├─ class-publicaciones-admin.php # Componentes de administración y flujo de trabajo
├─ class-publicaciones-db.php # Gestión de la tabla de base de datos y operaciones CRUD
└─ class-publicaciones-list-table.php # Implementación del listado mediante WP_List_Table

La arquitectura organiza el código en componentes independientes: carga inicial, administración, acceso a datos y representación del listado. Esto facilita la extensibilidad y el mantenimiento futuro.


## Modelo de datos

Al activarse, el plugin crea la tabla `{prefix}publicaciones`.

Los campos almacenados para cada publicación son:

- `titulo`
- `autores`
- `anio`
- `pdf_path`
- `bib_path`
- `fecha_creacion`
- `ultima_modificacion`

Los archivos asociados se almacenan en el directorio de uploads de WordPress, bajo la ruta:

wp-content/uploads/publicaciones/{AÑO}/


## Importación masiva

El plugin incorpora un método para importar publicaciones desde una estructura de carpetas con el formato:

    /origen/
    ├─ 2021/
    │ ├─ archivo1.pdf
    │ └─ archivo1.bib
    ├─ 2022/
    ├─ archivo2.pdf
    └─ archivo2.bib

Para cada año, los ficheros PDF y BibTeX se copian a `uploads/publicaciones/{AÑO}/` y se genera la entrada correspondiente en la base de datos. Esta funcionalidad es útil para cargas iniciales o migraciones desde herramientas previas.

## ¡¡Importante!!

El plugin también tiene la opción de añadir, modificar o eliminar una publicación de forma manual a través de formularios que se muestran en la interfaz. Es importante que esto se haga desde ahí, nunca modificando el contenido de los directorios.

## Observaciones

El plugin está enfocado al uso interno y **no expone funcionalidades en el frontal del sitio**. No obstante, la estructura del código facilita la incorporación futura de shortcodes, bloques o endpoints REST si fueran necesarios.
