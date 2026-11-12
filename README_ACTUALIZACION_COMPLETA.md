# JOBS Store — actualización profesional

## Mejoras incluidas
- Rediseño premium previo: portada, navegación, colores, categorías y responsive.
- Catálogo mejorado: buscador, filtros por categoría, orden por precio y nombre, contador de resultados y estado vacío.
- Tarjetas: insignias de destacados y agotados, botones y estados de inventario.
- Ficha de producto: información de compra, fotografía principal y productos relacionados de la misma categoría.
- Accesibilidad: foco visible, etiquetas de búsqueda y anuncio del número de resultados.
- Se conservan las rutas, productos, MySQL de Railway, administración, carrito, WhatsApp y el pago PSE condicional existente.

## Publicación
1. Haz una copia de seguridad del proyecto y de la base de datos antes de reemplazar archivos.
2. Copia el contenido a tu repositorio GitHub (sin subir .env ni credenciales).
3. Configura las variables de entorno del archivo .env.example en Render, especialmente SECRET_KEY y las de MySQL.
4. Ejecuta `pip install -r requirements.txt`, `python -m compileall -q app.py` y prueba localmente los flujos.
5. Publica con `git add .`, `git commit -m "Mejoras profesionales JOBS Store"`, `git push origin main` si estás en main.

No se han probado pagos reales ni la conexión a tu base Railway. No hay modificaciones destructivas de esquema en esta actualización.
