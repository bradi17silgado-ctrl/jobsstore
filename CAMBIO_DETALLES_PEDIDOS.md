# Detalles de pedidos en administración

- En **Administración → Pedidos recibidos**, pulsa el nombre del cliente para abrir un diálogo con referencia, nombre, teléfono, ciudad, dirección, fecha, estado, productos y total.
- Los nuevos pedidos tienen campos separados de ciudad y dirección; se añadió una columna opcional `direccion` a `jobs_pedidos` automáticamente mediante `initdb()` en MySQL. La cuenta de Railway debe permitir `ALTER TABLE`.
- Los pedidos existentes conservan su campo `ciudad` (en algunos casos puede contener ciudad y dirección juntas). La dirección separada aparecerá como no registrada para pedidos anteriores; no se inventan datos.
- Si el servidor no tiene permiso para cambiar la tabla, añade manualmente: `ALTER TABLE jobs_pedidos ADD COLUMN direccion VARCHAR(255) NULL AFTER ciudad;`
- No se modifican las credenciales ni los registros de pedidos actuales.
