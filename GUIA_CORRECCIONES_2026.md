# JOBS Store PHP — revisión y correcciones

Se corrigieron el token CSRF de pedidos JSON, las rutas POST, la inicialización de tablas antes de registrar pedidos, el control de acceso al dashboard y la protección de `app_admin/` desde Apache.

## Acceso al administrador

`ADMIN_PASSWORD_HASH` debe contener un hash PHP válido, no una contraseña en texto plano ni un hash de Python. Para generar uno localmente, sin publicarlo:

```sh
php -r 'echo password_hash(readline("Clave nueva: "), PASSWORD_DEFAULT), PHP_EOL;'
```

Pega el resultado en Render > Environment > ADMIN_PASSWORD_HASH y reinicia el servicio. Accede por `/admin/login`. No publiques el hash ni el archivo `.env`.

## Despliegue

Usa el Dockerfile incluido con un servicio Docker en Render. Configura `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `ADMIN_PASSWORD_HASH` y `SECRET_KEY` (si se usa en otra integración). Conserva la carpeta `.git` del repositorio local, pero no la incluyas en despliegues públicos.

## Verificación pendiente en producción

El análisis de sintaxis PHP se realizó localmente. Para confirmar las operaciones completas es necesario probar Apache/Render, la conexión real a Railway, login, inventario, registro y seguimiento de pedidos, y moderación de reseñas.
