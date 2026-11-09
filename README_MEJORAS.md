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

## Variables de entorno en Render
En tu servicio de Render abre **Environment** y configura:

- `SECRET_KEY`: clave aleatoria larga y privada.
- `ADMIN_PASSWORD`: contraseña nueva y segura para `/admin/login`.
- `DB_HOST`: host público de MySQL/Railway.
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

Los productos existentes quedan con stock `0` hasta que registres las cantidades reales. Esto evita vender unidades que no se han confirmado.

## Despliegue
1. Revisa las variables de entorno anteriores en Render.
2. Sube los cambios al repositorio conectado a Render.
3. Espera el nuevo despliegue.
4. Prueba `/inicio`, `/catalogo`, una ficha de producto y `/admin/login`.
5. Comprueba agregar al carrito y el control de existencias.

No actives pagos reales de Wompi hasta verificar las credenciales y realizar pruebas en el entorno de pruebas.
