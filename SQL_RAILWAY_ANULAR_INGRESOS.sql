-- Si no se permite CREATE TABLE desde PHP, ejecuta esto en Railway.
CREATE TABLE IF NOT EXISTS jobs_ingresos_anulados (
 movimiento_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 motivo VARCHAR(255) NOT NULL,
 usuario VARCHAR(60) NOT NULL DEFAULT 'administrador',
 creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_anul_creado (creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
