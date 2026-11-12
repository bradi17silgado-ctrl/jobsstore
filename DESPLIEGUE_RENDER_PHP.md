# JOBS Store PHP en Render (Docker)

1. Crea un repositorio o rama para PHP y sube el contenido de esta carpeta, incluyendo `Dockerfile`, `.htaccess` y `.dockerignore`. No subas `.env` ni contraseñas.
2. En Render: New > Web Service > conecta el repositorio > Language/Runtime: Docker (Dockerfile en la raíz). Usa un servicio **nuevo**, no reemplaces el servicio Flask hasta validar.
3. Configura Environment: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, ADMIN_PASSWORD_HASH, WHATSAPP_NUMBER, APP_ENV=production. Usa las credenciales **públicas/TCP** de Railway si Render no puede acceder a la red privada de Railway. No pegues secretos en Git.
4. Para generar el hash del administrador en una terminal con PHP: `php -r "echo password_hash('TU_CLAVE_SEGURA', PASSWORD_DEFAULT), PHP_EOL;"` y guarda el resultado en ADMIN_PASSWORD_HASH.
5. Deploy. Verifica `/`, `/catalogo`, `/admin/login`, imágenes, conexión MySQL y creación de pedidos de prueba. Si usas un dominio propio, cámbialo al nuevo servicio solo después de probar.

## Precauciones
- La tienda usa tablas nuevas con prefijo `jobs_`; confirma primero que el usuario MySQL tiene los permisos necesarios y respalda la base de datos.
- Wompi/PSE todavía no está integrado. No aceptes pagos reales sin configurar y probar la pasarela.
- El servicio antiguo `jobsstore.onrender.com` no se transforma automáticamente al crear otro servicio: el nuevo tendrá otra URL de Render.
- Este paquete no ha sido probado en un Render real ni contra tu Railway; debes validar la puesta en producción.
