# JOBS Store — rediseño con skill `diseno-premium`

## Alcance

Se aplicaron los criterios de `.agents/skills/diseno-premium/SKILL.md` a la tienda PHP existente sin cambiar el modelo de datos, rutas de API ni formularios de acciones.

- Portada editorial con productos destacados y acceso a gorras / relojes.
- Catálogo filtrable por las categorías existentes; tarjetas de producto personalizadas.
- Página de producto con precio, stock, botón de compra y reseñas.
- Carrito lateral con campos identificados de nombre, teléfono, ciudad y dirección.
- Pantallas de seguimiento, ayuda y acceso administrativo renovadas.
- Pulido visual de inventario, pedidos y formularios del administrador.
- Tipografías Manrope e Inter y paleta azul marino / blanco / gris claro / dorado.
- Reglas responsive para móvil, tablet y escritorio; animaciones reducidas con preferencias de accesibilidad.

## Conservado

- PHP 8, MySQL (Railway), inventario, controles de stock, sesiones, CSRF, rutas, panel administrativo, pedidos, reseñas y WhatsApp.
- Scripts de carrito, administrador y confirmación de pedido sin cambios de lógica.
- Configuración de despliegue para Render con Docker/Apache.

## Pruebas realizadas

- `php -l` sin errores para todos los archivos `.php`.
- Comprobaciones HTTP de la página de inicio, catálogo, filtros, seguimiento, ayuda y acceso admin con servidor PHP local.
- **Pendiente de verificar en entorno conectado a MySQL:** guardar pedidos, inventario, sesiones admin y base de datos real; probar el flujo de WhatsApp y responsive en dispositivos reales.

## Publicar

1. Guarda una copia del proyecto actual.
2. Reemplaza el contenido con los archivos de esta entrega, sin reemplazar tu `.env` real.
3. En el directorio del proyecto: `git status`, `git add .`, `git commit -m "Aplicar diseno premium JOBS Store"` y `git push origin migracion-php` (si esa es la rama configurada en Render).
4. Comprueba el despliegue de Render y recarga el navegador con `Ctrl + F5`.

**Nota:** el rediseño es visual. No habilita pagos online ni garantiza el funcionamiento de servicios externos sin configuración de producción.
