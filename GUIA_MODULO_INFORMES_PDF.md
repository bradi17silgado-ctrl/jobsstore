# Módulo de informes, mercancía y PDF – JOBS Store

## Funcionalidades
- En el panel `/admin` entra en **Informes de ventas y mercancía · PDF**.
- Registra **entradas/salidas** con producto, color, cantidad, proveedor, costo y observaciones. Se guarda un historial por producto y color.
- Los pedidos al registrarse descuentan stock, y registran una **salida por pedido**. La cancelación devuelve mercancía una sola vez. No se puede reabrir directamente un pedido cancelado.
- Los **ingresos de ventas** se cuentan solo para pedidos marcados **Entregado**; pendientes y cancelados no cuentan.
- Selecciona fechas y tipo de informe; haz clic en **Descargar PDF**.
- PDF disponible mediante `dompdf/dompdf`, instalado automáticamente por Docker.

## Railway
La tabla `jobs_movimientos` se crea automáticamente en MySQL con los permisos habituales de creación de tablas. También puedes ejecutar `SQL_RAILWAY_INFORMES.sql` manualmente. **No borres las tablas existentes**.

## Render / PHP
Mantén el servicio con **Dockerfile** del ZIP. El Dockerfile instala Composer, mbstring, gd y `dompdf/dompdf`. Si ejecutas localmente sin Docker, ejecuta `composer install` para habilitar PDF. No es necesario Python ni JavaScript adicional para el servidor.

## Notas importantes
- El reporte de movimientos registra eventos **a partir de la instalación**; no recupera movimientos previos de ventas o compras.
- Si se edita manualmente el stock en el formulario anterior de producto, se registra como **Ajuste** de inventario. Para entradas y salidas habituales, usa el nuevo formulario.
- Las ventas se agrupan por fecha de **creación** del pedido, pero únicamente se cuentan cuando su estado actual es **Entregado**. Esto no equivale a la fecha real de recaudo de un pago.
- El stock baja cuando se crea un pedido, aunque esté pendiente de confirmación; si cancelas, se devuelve automáticamente.
- Solo el administrador autenticado puede ver o descargar informes; se usa CSRF para los cambios de inventario.
- El PDF usa hasta 10.000 movimientos por periodo y el panel muestra los primeros 200. Selecciona un intervalo menor si necesitas exportaciones muy grandes.
