# JOBS Store — corrección de rutas y acceso administrativo

## Correcciones
- `/.htaccess`: las rutas públicas `/admin`, `/admin/login`, `/admin/salir`, `/admin/producto/guardar`, `/admin/pedido/estado` y `/admin/resena/aprobar` se envían a `index.php` antes de bloquear el acceso directo a `admin/`.
- `/admin/.htaccess`: se bloquea la ejecución directa de los archivos internos. No se debe visitar `/admin/login.php`: usar `/admin/login`.
- `index.php`: normaliza las barras finales de las rutas, por ejemplo `/admin/`.

## Variables necesarias en Render
La autenticación utiliza `ADMIN_PASSWORD_HASH` (hash de PHP generado con `password_hash`), **no** `ADMIN_PASSWORD`. Generar un hash con PHP 8.3, por ejemplo ejecutando localmente:

```sh
php -r 'echo password_hash(readline("Nueva contraseña: "), PASSWORD_DEFAULT), PHP_EOL;'
```

En Windows PowerShell, si tienes Docker instalado:

```powershell
$pass = Read-Host 'Nueva contraseña del administrador'
docker run --rm -e ADMIN_PLAIN="$pass" php:8.3-cli php -r 'echo password_hash(getenv("ADMIN_PLAIN"), PASSWORD_DEFAULT), PHP_EOL;'
Remove-Variable pass
```

Copiar el resultado completo a `ADMIN_PASSWORD_HASH` en Render, mantenerlo secreto y eliminar `ADMIN_PASSWORD` después de comprobar el acceso. Cambiar la contraseña que se mostró en capturas previas. También rotar la contraseña MySQL expuesta.

## Despliegue
1. Sustituir los archivos del proyecto en la carpeta local `JOBS-Store-Render` (conservar `.git`).
2. `git status` y comprobar que no se están incluyendo secretos.
3. `git add .htaccess admin/.htaccess admin/index.php index.php CORRECCIONES_ADMIN_RENDER.md`
4. `git commit -m "Corregir rutas y seguridad del administrador PHP"`
5. `git push origin migracion-php`
6. Render: confirmar rama `migracion-php` y ejecutar `Manual Deploy -> Deploy latest commit` si no se despliega automáticamente.
7. Probar `https://jobsstore-php.onrender.com/admin/login` y luego `https://jobsstore-php.onrender.com/admin`.

El Dockerfile debe habilitar `mod_rewrite` y `AllowOverride All`, como ya está configurado. Las pruebas de sintaxis PHP se realizaron, pero el comportamiento de Apache y la conexión a Railway deben comprobarse después del despliegue.
