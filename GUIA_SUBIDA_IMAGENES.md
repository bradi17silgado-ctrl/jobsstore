# JOBS Store: carga de fotografías

El panel /admin permite seleccionar JPG, PNG o WEBP desde Descargas (máximo 3 MB, 5000 px por lado). Al guardar, la fotografía se almacena en la tabla `jobs_imagenes` de Railway MySQL como MEDIUMBLOB y se sirve mediante `/media/producto/{id}`. No depende del almacenamiento efímero de Render.

Requisitos: Render debe tener DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD configurados y el usuario MySQL debe poder crear la tabla `jobs_imagenes`. Se crea automáticamente con `initdb()`. Las imágenes existentes en static/img/productos siguen funcionando. Para cambiar una imagen existente, pulsa Editar y selecciona una nueva; para conservarla, no selecciones archivo.

El límite de PHP `upload_max_filesize` debe ser >= 3M y `post_max_size` >= 3M; el Dockerfile de esta entrega configura 4M y 5M.

El ZIP recibido todavía usaba `ADMIN_PASSWORD_HASH`. Esta entrega adapta el inicio de sesión a `ADMIN_PASSWORD`, como solicitaste anteriormente. Por seguridad, evita reutilizar la contraseña expuesta en el chat; se recomienda migrar a hashes en el futuro.
