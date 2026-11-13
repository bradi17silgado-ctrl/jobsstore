# Anular ingresos de mercancía

En **Panel admin → Ingreso de mercancía** encontrarás la tabla **Eliminar / anular ingresos registrados**. Indica el motivo y confirma.

- Solo el administrador autenticado puede realizar anulaciones mediante un formulario protegido con CSRF.
- Se revierten las unidades del producto y color dentro de una transacción MySQL.
- Si el inventario actual no alcanza, no se anula. Por ejemplo, si entraron 10 y después se vendieron 8, no puedes anular las 10 mientras solo queden 2.
- No se borran filas del historial: la entrada se marca como anulada y se registra un movimiento adicional de auditoría.
- Los informes de ingresos/PDF excluyen los ingresos anulados de los totales; el historial general muestra las anulaciones.
- Se muestran los últimos 150 ingresos para evitar una carga excesiva.
- MySQL crea automáticamente `jobs_ingresos_anulados` si el usuario Railway tiene permisos. De lo contrario, ejecuta `SQL_RAILWAY_ANULAR_INGRESOS.sql`.

Comprueba la anulación en una copia de pruebas antes de usarla en producción.
