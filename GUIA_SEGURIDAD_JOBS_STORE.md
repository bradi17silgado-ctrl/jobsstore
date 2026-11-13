# Seguridad de JOBS Store — instrucciones para publicar

## Cambios incluidos
- Inicio de sesión con `ADMIN_PASSWORD_HASH` (bcrypt o Argon2) y compatibilidad temporal con `ADMIN_PASSWORD`.
- Intentos limitados por IP (5 accesos / 15 minutos; 12 pedidos / 15 minutos; 8 reseñas / 15 minutos).
- Sesión administrativa con cierre por inactividad a los 30 minutos, rotación al entrar y salir.
- Cookies HttpOnly, SameSite=Lax y Secure en producción.
- Cabeceras de seguridad: CSP compatible con scripts inline existentes, X-Frame-Options, HSTS en producción, Permissions-Policy.
- Restricciones de acceso a archivos internos, logs, SQL, respaldos y configuración.
- Límite de longitud para datos de contacto y límite de artículos por pedido.
- Continúan CSRF, PDO parametrizado y validación de imágenes.

## Pasos indispensables en Render
1. Configura `APP_ENV=production` **solamente si tu aplicación se sirve por HTTPS** (como en Render).
2. Genera un hash localmente con PHP (NO pegues la contraseña en comandos compartidos):
   `php -r 'echo password_hash(readline("Contraseña nueva: "), PASSWORD_DEFAULT), PHP_EOL;'`
   La contraseña introducida será visible en algunas terminales; puedes usar un script local privado con `readline` y eliminarlo después.
3. Guarda el resultado en variable de entorno `ADMIN_PASSWORD_HASH`. **Nunca** subas la contraseña ni el hash al repositorio.
4. Elimina `ADMIN_PASSWORD` del entorno tras comprobar que el hash funciona, y rota cualquier contraseña antigua expuesta.
5. Comprueba login, cierre de sesión, subida de fotos, carrito, seguimiento, WhatsApp y edición de productos después del despliegue.
6. Usa contraseñas únicas y MFA en GitHub, Railway y Render; aplica actualizaciones periódicas y copias de seguridad de Railway.

## Límites
- El limitador local almacena contadores temporales en el sistema de archivos; se reinicia con cada redeploy y **no** se comparte entre múltiples instancias. Para producción de alto tráfico, migra a Redis o un WAF con limitación global.
- La CSP conserva `unsafe-inline` porque la plantilla actual usa JavaScript y estilos inline. Para endurecerla, mueve estilos y scripts a archivos externos y usa nonces.
- No hay garantía de protección absoluta contra ataques. Haz auditoría de dependencias y pruebas de penetración autorizadas antes de manejar pagos.
- No habilitar pagos hasta contar con pasarela segura y notificaciones verificadas del proveedor.
