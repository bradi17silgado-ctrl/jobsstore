# Panel administrativo dividido en páginas

- `/admin`: resumen y accesos directos.
- `/admin/productos`: catálogo e inventario actual.
- `/admin/producto/nuevo`: agregar o editar, incluyendo colores e imágenes.
- `/admin/pedidos`: pedidos y datos de entrega.
- `/admin/ventas`: estadísticas, consulta por fechas y exportación a PDF.
- `/admin/mercancia/entrada`: registrar entradas de inventario.
- `/admin/mercancia/salida`: registrar salidas de inventario.
- `/admin/movimientos`: historial/auditoría y PDF.
- `/admin/resenas`: moderación de reseñas.

Conserva las variables DB_* de Railway y la instalación de Dompdf del ZIP anterior.
El historial muestra movimientos desde la activación del módulo, no reconstruye movimientos históricos.
