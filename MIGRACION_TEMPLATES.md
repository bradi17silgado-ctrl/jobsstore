# Vistas PHP de JOBS Store

Las pantallas activas están en `views/*.php` y el panel en `admin/*.php`. La carpeta de referencia Flask y sus archivos `.html` se retiraron del paquete.

**Alcance:** Esta versión conserva las funciones PHP implementadas anteriormente; no convierte automáticamente todas las funciones originales de Flask. Pagos Wompi/PSE y flujos avanzados aún requieren implementación y pruebas.

Para probar: `php -S localhost:8000 router.php`. Configura MySQL y comprueba carrito, pedidos, inventario y administración antes de desplegar.
