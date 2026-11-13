# Colores y stock por variante — JOBS Store

## Qué se añadió
- Selección manual de 14 colores al crear o editar productos en `/admin`.
- Stock por color almacenado en MySQL (`jobs_colores`).
- Stock general sincronizado como suma de existencias de los colores.
- Selección de color obligatoria para añadir al carrito productos que tengan variantes.
- Pedido en línea y por WhatsApp incluyen el color escogido.
- El pedido en línea descuenta el stock general y el stock de ese color en una transacción.
- El panel de pedidos y el comprobante muestran el color.

## Configuración
1. Conserva las variables de conexión Railway ya existentes.
2. Despliega el proyecto PHP. Al recibir peticiones, `initdb()` crea automáticamente `jobs_colores` si la conexión de MySQL dispone de permisos `CREATE TABLE`.
3. Entra a `/admin`, crea un producto y marca al menos un color con su cantidad. Para artículos anteriores, edita el producto y asigna sus colores; hasta entonces seguirán funcionando con su stock anterior sin selector.
4. Prueba un pedido de ejemplo y verifica que el color aparezca en los detalles del pedido y que se descuenten unidades de la variante.

## Advertencias
- El sistema usa una fotografía general por producto, no imágenes distintas por color.
- Los colores de variantes se guardan como nombres normalizados (ej. `azul-marino`).
- En artículos que ya tienen variantes, se exige conservar al menos un color al editar. Puedes asignarle 0 unidades para marcarlo como agotado.
- No se modifican contraseñas, pagos, ni tablas de pedidos históricos.
