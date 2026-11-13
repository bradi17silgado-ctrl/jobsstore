# Comprobantes PDF por ingreso y anulación

En **Administración → Ingreso de mercancía**:

- Al registrar un ingreso, aparece el botón para descargar el comprobante PDF correspondiente.
- En la tabla de ingresos, cada registro incluye **PDF ingreso**.
- Al anular un ingreso, aparece **PDF anulación**, que muestra motivo, fecha, responsable y cantidades revertidas.
- Ambos documentos pueden descargarse de nuevo desde el listado, aun después de la anulación.

Ruta protegida: `/admin/mercancia/comprobante/pdf?id=NUMERO&clase=ingreso|anulacion`. Requiere sesión de administrador activa. Los PDF se generan con Dompdf instalado mediante Composer (Dockerfile incluido). No se crea ninguna tabla nueva y la descarga no modifica existencias.

**Importante:** Los comprobantes reflejan el historial guardado; la anulación real sigue condicionada a que haya stock suficiente. Prueba las descargas en Render con conexión a Railway.
