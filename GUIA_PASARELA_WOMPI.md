# Pasarela Wompi en JOBS Store

## Qué incluye
- Botón «Pagar en línea con Wompi» después de registrar el pedido, solo si la configuración es válida.
- Importe consultado a MySQL y firma SHA256 creada en PHP.
- Webhook `POST /pago/wompi/evento` con comprobación SHA256 de firma, importe, referencia, moneda y ambiente.
- Tabla `jobs_pagos` creada automáticamente al inicializar la base de datos.
- Pedido marcado como **Pagado** exclusivamente con notificación válida `APPROVED`.
- Página `/pago/resultado` informativa; no confirma automáticamente pagos.

## Configuración
1. Registrar comercio y obtener llaves en https://comercios.wompi.co/ . Empezar en Sandbox.
2. En Render agregar las variables `APP_URL` (URL pública HTTPS sin slash final), `WOMPI_PUBLIC_KEY`, `WOMPI_INTEGRITY_SECRET`, `WOMPI_EVENTS_SECRET`.
3. En Wompi configurar URL de eventos HTTPS: `https://TU-DOMINIO/pago/wompi/evento` y evento `transaction.updated`.
4. Reiniciar servicio, registrar un pedido de prueba y abrir su comprobante. Comprobar redirección a Wompi y notificación del webhook.
5. Probar estados aprobado, rechazado y pendiente; verificar en Railway `SELECT * FROM jobs_pagos ORDER BY actualizado DESC;`.
6. Solo después usar llaves `pub_prod_*`, `prod_integrity_*` y secreto de eventos de producción. Configurar URL de eventos para ese ambiente.

## Consideraciones operativas
- Los pedidos se crean y descuentan inventario antes del pago: pagos abandonados **no reponen stock automáticamente**. Revisar pedidos pendientes y definir política de cancelación y reposición antes de vender en producción.
- Un pago aprobado solo cambia pedidos que sigan en estado «Pendiente»; no sobrescribe pedidos procesados o enviados. Consultar `jobs_pagos` como fuente del estado de pago.
- No almacenar datos de tarjetas en el sitio. No incrustar llaves privadas ni secretos en archivos públicos.
- En caso de reintentos tras rechazo, verificar en sandbox las reglas de reutilización de referencias de Wompi; un flujo de múltiples intentos con referencias distintas requiere ampliación.
- Nunca dar un pedido por pagado por parámetros de la URL de regreso.

Documentación oficial: https://docs.wompi.co/docs/colombia/widget-checkout-web/ y https://docs.wompi.co/docs/colombia/eventos/
