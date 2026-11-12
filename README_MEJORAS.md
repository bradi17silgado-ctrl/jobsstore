# JOBS Store — versión mejorada

## Cambios incluidos
- Ficha individual para cada producto (`/producto/<id>`).
- Inventario por producto desde el panel administrativo.
- Indicador de unidades disponibles y estado agotado en el catálogo.
- El carrito limita cantidades al inventario conocido.
- El inicio de pago PSE valida existencias en el servidor.
- Resumen de productos, visibles y agotados en el panel.
- Protección: se retiraron credenciales de base de datos incrustadas en `app.py`.
- Se retiró el acceso a `/compras` mediante clave en la URL; ahora exige sesión administrativa.
- Se excluyeron del paquete los archivos del entorno virtual.

## MUY IMPORTANTE: antes de desplegar
La versión original tenía una contraseña de Railway escrita directamente en `app.py` y ese archivo fue publicado en GitHub. **Cambia/rota la contraseña de la base de datos en Railway inmediatamente** y actualiza la variable `DB_PASSWORD` en Render. Cambiar solo el código no invalida la contraseña que ya quedó expuesta en el historial de Git.

## Correcciones de esta revisión
- **Caída de `/inicio` (error 500):** los productos de respaldo no traían `stock` y la plantilla fallaba cuando la base de datos no respondía. Ahora el respaldo incluye `stock` y las plantillas toleran su ausencia.
- **Base de datos:** `mysql.railway.internal` es un host privado de Railway y no se resuelve desde Render. Usa el host/puerto públicos (TCP Proxy). Si lo configuras mal, el log lo explica.
- La app ya no espera 10 s por petición cuando la BD está caída (reintenta cada 30 s) y la ficha de producto también tiene respaldo.
- Se crea automáticamente la tabla `compras` (antes `/pago/pse` y `/compras` fallaban si no existía).
- El inventario se descuenta al confirmarse el pago (webhook de Wompi, una sola vez) y se devuelve si se anula.
- `ProxyFix` en Render: la URL de retorno de Wompi usa `https` y el límite de intentos del login es por IP real.
- Se quitaron textos `hola` sueltos, un `\n` literal en el panel y la imagen `og:image` inexistente; se eliminó `menu.html` (no se usaba).
- Páginas 404/500 con el diseño de la tienda y cabeceras de seguridad básicas.
- Se agregó `python-dotenv` (solo para desarrollo local con `.env`).

## Variables de entorno en Render
En tu servicio de Render abre **Environment** y configura:

- `SECRET_KEY`: clave aleatoria larga y privada.
- `ADMIN_PASSWORD`: contraseña nueva y segura para `/admin/login`.
- `DB_HOST`: host **público** de MySQL/Railway (`xxxx.proxy.rlwy.net`), nunca `mysql.railway.internal`.
- `DB_PORT`: puerto público de MySQL/Railway.
- `DB_USER`: usuario de MySQL.
- `DB_PASSWORD`: contraseña nueva de MySQL.
- `DB_NAME`: nombre de la base de datos.
- `WHATSAPP_NUMBER`: número con indicativo, solo dígitos (ej. 57...).
- `SESSION_COOKIE_SECURE`: `1` en producción HTTPS.

Si ya tienes variables `WOMPI_*`, consérvalas. No publiques sus secretos.

## Activar el inventario
La aplicación agrega automáticamente la columna `stock` a la tabla `productos` si todavía no existe. La migración no borra los productos.

Después de desplegar:
1. Abre `/admin/login`.
2. Entra con `ADMIN_PASSWORD`.
3. Edita cada producto.
4. Asigna sus existencias reales y guarda.

Los productos que ya existían en tu base quedan con stock `0` hasta que registres las cantidades reales (evita vender lo no confirmado). Si la base es nueva, los productos iniciales se cargan con 10 unidades.

## Despliegue
1. Revisa las variables de entorno anteriores en Render.
2. Sube los cambios al repositorio conectado a Render.
3. Espera el nuevo despliegue.
4. Prueba `/inicio`, `/catalogo`, una ficha de producto y `/admin/login`.
5. Comprueba agregar al carrito y el control de existencias.

No actives pagos reales de Wompi hasta verificar las credenciales y realizar pruebas en el entorno de pruebas.
