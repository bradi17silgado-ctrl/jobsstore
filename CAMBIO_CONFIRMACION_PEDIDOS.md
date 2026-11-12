# Confirmación de pedidos integrada

- `POST /pedido/crear` conserva el registro en MySQL, validación de inventario y descuento de stock. Ahora devuelve además `confirmacion_url`.
- Se crea `GET /pedido/confirmado?token=...` para mostrar el comprobante de forma privada durante 24 horas en la sesión que realizó el pedido.
- El navegador ya no usa `alert()` para un pedido exitoso: después de recibir la respuesta correcta, borra el carrito y navega al comprobante.
- Se muestra referencia, artículos, total, botón copiar, enlaces a seguimiento y tienda.
- El envío por WhatsApp permanece independiente; no se indica que exista un pago realizado.
- Los errores de validación todavía utilizan alertas del navegador; solamente se reemplazó la confirmación de éxito.
