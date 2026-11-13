# Fechas profesionales · JOBS Store

Se rediseñaron los controles de fechas en **Informe de ventas**, **Informes generales**, **Historial de movimientos** y **descarga de informes de entradas/salidas**, todos desde `app_admin/informes.php`.

- Etiquetas encima, icono, bordes suaves, enfoque visible y disposición adaptable.
- Se conservan los nombres `desde` y `hasta`, las rutas y la generación de PDF.
- Los valores `min` y `max` se establecen según el periodo seleccionado al renderizar; una búsqueda nueva continúa validándose en el servidor.
- CSS en `static/style.css` y parámetro de versión actualizado en `index.php` para evitar caché.

Para desplegar: copiar los archivos del ZIP (incluyendo el CSS), confirmar y subir al repositorio, verificar la rama configurada en Render, y probar fechas en escritorio y móvil.
