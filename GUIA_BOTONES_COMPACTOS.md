# Botones compactos del administrador

Se redujo el tamaño de los botones de acciones del panel administrador.

- PDF ingreso y PDF anulación se muestran juntos, horizontalmente si existe espacio.
- Descargar PDF, Consultar, Guardar, Registrar y Volver al panel comparten un diseño compacto y profesional.
- Se mantienen las URLs, validaciones y funciones existentes.
- Los cambios están concentrados en `static/style.css` y el contenedor de comprobantes de `app_admin/informes.php`.
- Se cambió la versión de CSS en `index.php` para evitar caché.

Desplegar en Render y validar el diseño en escritorio y móvil.
