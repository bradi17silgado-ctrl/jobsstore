-- EJECUTAR EN RAILWAY SI DESEAS CREAR LA TABLA MANUALMENTE.
-- La tienda también la crea automáticamente mediante initdb() al acceder.
CREATE TABLE IF NOT EXISTS jobs_movimientos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 producto_id VARCHAR(32) NULL,
 producto_nombre VARCHAR(160) NOT NULL,
 color VARCHAR(32) NULL,
 tipo VARCHAR(20) NOT NULL,
 cantidad INT NOT NULL,
 stock_anterior INT NOT NULL,
 stock_nuevo INT NOT NULL,
 costo_unitario DECIMAL(12,2) NULL,
 proveedor VARCHAR(160) NULL,
 referencia VARCHAR(40) NULL,
 motivo VARCHAR(255) NULL,
 usuario VARCHAR(60) NOT NULL DEFAULT 'administrador',
 creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_mov_fecha (creado),
 INDEX idx_mov_producto (producto_id, creado),
 INDEX idx_mov_tipo (tipo, creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
