# JOBS Store — gestión de productos

Se agregaron **Editar**, **Ocultar/Publicar** y **Eliminar** al inventario.

- Eliminar requiere sesión administrativa, CSRF y confirmación del navegador. Elimina producto, imagen almacenada en Railway y reseñas asociadas en una transacción. No elimina pedidos históricos, que conservan sus detalles en JSON.
- Ocultar/Publicar cambia `visible` sin perder información.
- La tabla `jobs_config` impide que los productos de ejemplo se vuelvan a insertar automáticamente si el inventario queda vacío tras eliminar todos.
- Se necesita una conexión MySQL de Railway con permisos para crear `jobs_config` y modificar productos, imágenes y reseñas.
- Prueba primero en un entorno de ensayo: eliminar es irreversible.
