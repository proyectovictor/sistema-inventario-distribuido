-- ============================================
-- TABLA COLA_PENDIENTES
-- Para tolerancia a fallos entre servidores
-- ============================================

CREATE TABLE IF NOT EXISTS cola_pendientes (
    id SERIAL PRIMARY KEY,
    sucursal_origen VARCHAR(10),
    operacion VARCHAR(20),
    datos JSONB,
    fecha_intento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) DEFAULT 'pendiente'
);

-- Índice para búsquedas rápidas
CREATE INDEX IF NOT EXISTS idx_cola_estado ON cola_pendientes(estado);
CREATE INDEX IF NOT EXISTS idx_cola_fecha ON cola_pendientes(fecha_intento);