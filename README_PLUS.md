# JOBS Store Plus — entrega de desarrollo

## Incluido
- Registro de pedidos pendientes con código y consulta mediante código + correo.
- Administración de estados de pedidos y estadísticas básicas (NO son pagos confirmados).
- Cupones porcentuales gestionados por administrador.
- Registro administrativo de variantes por color y talla (inventario informativo).
- Reseñas moderadas (no verificadas como compras).
- Página informativa de envíos, devoluciones y privacidad (textos orientativos que deben completarse con políticas reales).
- Navegación a nuevas páginas; carrito original y flujo PSE/WhatsApp existentes conservados.

## Pendiente antes de producción
- Validar con una base MySQL de prueba y realizar copia de seguridad de Railway.
- Completar políticas reales y tarifa de envíos; la tarifa de 0 COP Bogotá / 15.000 COP otras ciudades es solo una estimación de ejemplo.
- El registro de pedidos NO reserva ni descuenta stock, y las variantes aún no están integradas con la selección del carrito.
- No hay cuentas de clientes, favoritos ni pagos nuevos integrados en esta versión.
- La pasarela PSE existente no se modificó y debe probarse por separado.
- Añadir antispam/rate limiting y CSRF a formularios públicos antes de exposición amplia.
- Los datos de contacto y dirección requieren política de tratamiento de datos, controles de acceso y retención.
- El pedido no envía email automáticamente; mostrar código al cliente y consultar en Seguimiento.

## Despliegue
1. Copia de seguridad de MySQL y del proyecto.
2. Configura DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME, SECRET_KEY, ADMIN_PASSWORD según README original.
3. Instala `pip install -r requirements.txt` y ejecuta `python app.py`.
4. Las tablas nuevas se crean al abrir las páginas Plus; necesitan permisos CREATE en MySQL.
5. Visita `/admin/plus`, `/seguimiento`, `/pedido/nuevo`, `/informacion`.
6. No hagas `git push --force`; revisa primero el problema de ramas con historiales no relacionados.
