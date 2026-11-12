# JOBS Store — versión PHP

Esta es una **reescritura PHP** del proyecto Flask, no una conversión 1:1 de todas sus rutas. Incluye catálogo, imágenes originales, carrito JS, WhatsApp, pedidos con inventario transaccional en MySQL, seguimiento por referencia y teléfono, panel de productos/pedidos y reseñas moderadas. No incluye cobro real PSE/Wompi, cupones, variantes ni las pantallas avanzadas originales. No declares la migración completa hasta probarla.

## Instalación
1. PHP 8.1+ con extensiones `pdo_mysql`, `json` y `session`. MySQL 8+ recomendado.
2. Copia `.env.example` a `.env` y configura `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `WHATSAPP_NUMBER`.
3. Genera un hash seguro de contraseña con `php -r "echo password_hash('TU_CLAVE_SEGURA', PASSWORD_DEFAULT), PHP_EOL;"` y colócalo en `ADMIN_PASSWORD_HASH`.
4. En desarrollo ejecuta `php -S localhost:8000 router.php` desde la raíz y visita `http://localhost:8000`.
5. En Apache usa el `.htaccess` incluido, habilitando `mod_rewrite`; en Nginx configura `try_files $uri $uri/ /index.php?$query_string`.
6. El sistema crea las tablas `jobs_productos`, `jobs_pedidos` y `jobs_resenas` en la base configurada. No modifica las tablas antiguas de Flask. Haz respaldo antes de migrar datos reales.
7. Para producción, usa HTTPS, limita acceso a `.env` y `/data/`, deshabilita listado de directorios y configura backups. El alojamiento debe soportar PHP; el servicio Python de Render actual no ejecutará este proyecto sin reconfiguración.

**Advertencias:** La tienda muestra productos de demostración sin DB, pero pedidos y administración requieren MySQL. El stock se descuenta al registrar el pedido, incluso antes de confirmar pago. La integración Wompi/PSE no está habilitada: no se simulan pagos ni se piden credenciales de pago al cliente. Los archivos originales Python no se incluyen en este ZIP; conserva tu copia de seguridad.


## Panel administrativo organizado
- `admin.php`: acceso directo al panel (`/admin.php` redirige a `/admin`).
- `admin/login.php`: formulario de acceso, solo cargado por el controlador.
- `admin/dashboard.php`: inventario, productos, pedidos y reseñas.
- `admin/actions.php`: operaciones administrativas, validación de sesión y token CSRF.
- `index.php`: entrada principal y enrutador del sitio.
- `admin/.htaccess`: bloquea el acceso directo a archivos internos con Apache.

**Seguridad:** Configura `ADMIN_PASSWORD_HASH` y las variables `DB_*` en el servidor. No publiques `.env`.

## Archivos recuperados del ZIP original
- `.vscode/`: configuración del editor.
- `static/`: imágenes, CSS y JavaScript originales, junto con los recursos PHP existentes.
- `README_*.md`: documentación del proyecto Flask para consulta.
- `referencia_flask/templates/`: copia de las plantillas HTML/Jinja originales, **solo referencia**, no son páginas PHP activas. Retira `referencia_flask/` antes de publicar.
- `.gitignore`: reglas de exclusión, actualizadas para PHP.

No se copiaron `.git/`, `Scripts/`, `app.py`, `requirements.txt`, `Procfile` ni `bodega.db`: son historial/entorno/servidor o datos de la versión anterior. **No borres tu copia original**, especialmente `bodega.db`, sin verificar si contiene datos necesarios. Las funciones de las plantillas originales que aún no se implementaron en PHP siguen pendientes.
