# Colores con fotografías reales — JOBS Store

1. En el administrador, edita una gorra o reloj. Marca cada color disponible, define las existencias y selecciona **Foto del color** para subir su fotografía correspondiente (JPG, PNG o WEBP, hasta 3 MB).
2. Guarda el producto. Las imágenes quedan almacenadas en MySQL, en `jobs_color_imagenes`, con `producto_id`, `color`, `mime` y `contenido`.
3. En la página del producto y en el catálogo se muestran círculos de color. Al elegir uno, la fotografía cambia a la foto real subida para ese color; si no hay foto específica, permanece la principal, sin alterar artificialmente el color.
4. El carrito conserva el color elegido y descuenta el stock por color al registrar el pedido.

## Railway — si la cuenta no permite crear tablas automáticamente

```sql
CREATE TABLE IF NOT EXISTS jobs_color_imagenes (
 producto_id VARCHAR(32) NOT NULL,
 color VARCHAR(32) NOT NULL,
 mime VARCHAR(32) NOT NULL,
 contenido MEDIUMBLOB NOT NULL,
 PRIMARY KEY (producto_id,color)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Importante:** para visualizar realmente una gorra roja, negra o azul hay que cargar una imagen de la gorra en cada color. El cambio de color por CSS sobre una fotografía no es fiel al producto.
