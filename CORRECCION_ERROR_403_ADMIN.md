# Corrección de 403 en botones administrativos

La causa era la diferencia entre `.htaccess` (raíz) y `app_admin/.htaccess`. El `.htaccess` principal protegía el prefijo `/admin/` y solo permitía un listado antiguo de rutas. Las páginas nuevas terminaban en el bloqueo `[F]` de Apache, antes de alcanzar `index.php`.

Se agregaron expresamente las rutas `/admin/productos`, `/admin/producto/nuevo`, `/admin/pedidos`, `/admin/resenas`, `/admin/ventas`, `/admin/mercancia/entrada`, `/admin/mercancia/salida` y `/admin/movimientos` al listado permitido de la raíz. Continúan bloqueados los accesos directos a archivos internos y directorios privados.

## Despliegue
1. Sustituir los archivos del proyecto, incluido el archivo oculto `.htaccess` de la carpeta raíz.
2. Confirmar en Git que `.htaccess` figura como modificado: `git status`.
3. Ejecutar `git add .htaccess CORRECCION_ERROR_403_ADMIN.md`, `git commit -m "Corregir rutas 403 del panel administrativo"` y `git push origin TU_RAMA`.
4. En Render, volver a desplegar la rama correspondiente. Iniciar sesión como administrador y probar los siete botones.

Si persiste 403, comprobar que Render realmente desplegó el último commit y que el Dockerfile copia el `.htaccess` nuevo.
