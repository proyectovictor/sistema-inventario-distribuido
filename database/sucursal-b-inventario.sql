-- ============================================
-- SUCURSAL A - INVENTARIO
-- Estructura completa de base de datos
-- PostgreSQL / Supabase
-- ============================================

-- TABLAS PRINCIPALES
CREATE TABLE IF NOT EXISTS almacen (
    IdAlmacen SERIAL PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    Ubicacion VARCHAR(255),
    Responsable VARCHAR(100),
    Activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS categoria (
    IdCategoria SERIAL PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    Descripcion VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS usuario (
    IdUsuario SERIAL PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    UsuarioLogin VARCHAR(50) UNIQUE NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Rol VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS tipomovimiento (
    IdTipoMovimiento SERIAL PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS producto (
    IdProducto SERIAL PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    Descripcion VARCHAR(255),
    CodigoBarras VARCHAR(50),
    Precio DECIMAL(10,2) NOT NULL,
    StockMinimo INTEGER DEFAULT 0,
    IdCategoria INTEGER REFERENCES categoria(IdCategoria) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS movimiento (
    IdMovimiento SERIAL PRIMARY KEY,
    Fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    IdAlmacen INTEGER REFERENCES almacen(IdAlmacen),
    IdUsuario INTEGER REFERENCES usuario(IdUsuario),
    IdTipoMovimiento INTEGER REFERENCES tipomovimiento(IdTipoMovimiento),
    Observaciones TEXT
);

CREATE TABLE IF NOT EXISTS stock_almacen (
    IdStock SERIAL PRIMARY KEY,
    IdAlmacen INTEGER REFERENCES almacen(IdAlmacen),
    IdProducto INTEGER REFERENCES producto(IdProducto),
    Cantidad INTEGER NOT NULL DEFAULT 0,
    FechaIngreso DATE NOT NULL,
    FechaCaducidad DATE,
    Lote VARCHAR(50),
    UbicacionEstante VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS detallemovimiento (
    IdDetalle SERIAL PRIMARY KEY,
    IdMovimiento INTEGER REFERENCES movimiento(IdMovimiento) ON DELETE CASCADE,
    IdStock INTEGER REFERENCES stock_almacen(IdStock),
    Cantidad INTEGER NOT NULL,
    PrecioUnitario DECIMAL(10,2)
);

CREATE TABLE IF NOT EXISTS existencia (
    IdProducto INTEGER PRIMARY KEY REFERENCES producto(IdProducto) ON DELETE CASCADE,
    StockActual INTEGER NOT NULL DEFAULT 0
);

-- FUNCIÓN Y TRIGGER
CREATE OR REPLACE FUNCTION actualizar_existencia()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO existencia (IdProducto, StockActual)
    VALUES (
        NEW.IdProducto,
        (SELECT COALESCE(SUM(Cantidad), 0)
         FROM stock_almacen
         WHERE IdProducto = NEW.IdProducto)
    )
    ON CONFLICT (IdProducto)
    DO UPDATE SET StockActual = EXCLUDED.StockActual;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_stock_after_update ON stock_almacen;
CREATE TRIGGER trg_stock_after_update
AFTER UPDATE OR INSERT ON stock_almacen
FOR EACH ROW
EXECUTE FUNCTION actualizar_existencia();

-- VISTAS
CREATE OR REPLACE VIEW vista_alertas_stock_bajo AS
SELECT 
    p.IdProducto,
    p.Nombre AS Producto,
    p.CodigoBarras,
    COALESCE(SUM(s.Cantidad), 0) AS StockTotal,
    p.StockMinimo,
    p.StockMinimo - COALESCE(SUM(s.Cantidad), 0) AS Faltante,
    COUNT(DISTINCT s.IdAlmacen) AS AlmacenesAfectados
FROM producto p
LEFT JOIN stock_almacen s ON p.IdProducto = s.IdProducto
GROUP BY p.IdProducto, p.Nombre, p.CodigoBarras, p.StockMinimo
HAVING COALESCE(SUM(s.Cantidad), 0) < p.StockMinimo;

CREATE OR REPLACE VIEW vista_stock_detallado AS
SELECT 
    a.Nombre AS Almacen,
    p.Nombre AS Producto,
    p.CodigoBarras,
    s.Lote,
    s.FechaIngreso,
    s.FechaCaducidad,
    s.Cantidad,
    s.UbicacionEstante
FROM stock_almacen s
JOIN almacen a ON s.IdAlmacen = a.IdAlmacen
JOIN producto p ON s.IdProducto = p.IdProducto
WHERE s.Cantidad > 0
ORDER BY a.Nombre, p.Nombre, s.FechaIngreso;

CREATE OR REPLACE VIEW vista_stock_total AS
SELECT 
    p.IdProducto,
    p.Nombre AS Producto,
    p.CodigoBarras,
    COALESCE(SUM(s.Cantidad), 0) AS StockTotal,
    COALESCE(MIN(s.FechaIngreso)::TEXT, 'N/A') AS FechaLoteMasAntiguo,
    COUNT(DISTINCT s.Lote) AS CantidadLotes,
    COUNT(DISTINCT s.IdAlmacen) AS AlmacenesConStock
FROM producto p
LEFT JOIN stock_almacen s ON p.IdProducto = s.IdProducto
GROUP BY p.IdProducto, p.Nombre, p.CodigoBarras;

-- PROCEDIMIENTOS ALMACENADOS
CREATE OR REPLACE FUNCTION sp_registrar_entrada(
    p_IdAlmacen INTEGER,
    p_IdUsuario INTEGER,
    p_IdProducto INTEGER,
    p_Cantidad INTEGER,
    p_Lote VARCHAR(50),
    p_FechaIngreso DATE,
    p_UbicacionEstante VARCHAR(50),
    p_Observaciones TEXT
)
RETURNS INTEGER AS $$
DECLARE
    v_IdMovimiento INTEGER;
    v_IdStock INTEGER;
BEGIN
    INSERT INTO movimiento (IdAlmacen, IdUsuario, IdTipoMovimiento, Observaciones)
    VALUES (p_IdAlmacen, p_IdUsuario, 1, p_Observaciones)
    RETURNING IdMovimiento INTO v_IdMovimiento;
    
    INSERT INTO stock_almacen (IdAlmacen, IdProducto, Cantidad, FechaIngreso, Lote, UbicacionEstante)
    VALUES (p_IdAlmacen, p_IdProducto, p_Cantidad, p_FechaIngreso, p_Lote, p_UbicacionEstante)
    RETURNING IdStock INTO v_IdStock;
    
    INSERT INTO detallemovimiento (IdMovimiento, IdStock, Cantidad)
    VALUES (v_IdMovimiento, v_IdStock, p_Cantidad);
    
    RETURN v_IdMovimiento;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION sp_registrar_salida_fifo(
    p_IdAlmacen INTEGER,
    p_IdUsuario INTEGER,
    p_IdProducto INTEGER,
    p_Cantidad INTEGER,
    p_Observaciones TEXT
)
RETURNS INTEGER AS $$
DECLARE
    v_IdMovimiento INTEGER;
    v_CantidadRestante INTEGER;
    v_StockRecord RECORD;
    v_CantidadADescontar INTEGER;
BEGIN
    IF (SELECT COALESCE(SUM(Cantidad), 0) FROM stock_almacen 
        WHERE IdAlmacen = p_IdAlmacen AND IdProducto = p_IdProducto) < p_Cantidad THEN
        RAISE EXCEPTION 'Stock insuficiente';
    END IF;
    
    INSERT INTO movimiento (IdAlmacen, IdUsuario, IdTipoMovimiento, Observaciones)
    VALUES (p_IdAlmacen, p_IdUsuario, 2, p_Observaciones)
    RETURNING IdMovimiento INTO v_IdMovimiento;
    
    v_CantidadRestante := p_Cantidad;
    
    FOR v_StockRecord IN 
        SELECT IdStock, Cantidad 
        FROM stock_almacen 
        WHERE IdAlmacen = p_IdAlmacen AND IdProducto = p_IdProducto AND Cantidad > 0
        ORDER BY FechaIngreso ASC
    LOOP
        IF v_CantidadRestante <= 0 THEN EXIT; END IF;
        
        v_CantidadADescontar := LEAST(v_StockRecord.Cantidad, v_CantidadRestante);
        
        UPDATE stock_almacen 
        SET Cantidad = Cantidad - v_CantidadADescontar
        WHERE IdStock = v_StockRecord.IdStock;
        
        INSERT INTO detallemovimiento (IdMovimiento, IdStock, Cantidad)
        VALUES (v_IdMovimiento, v_StockRecord.IdStock, v_CantidadADescontar);
        
        v_CantidadRestante := v_CantidadRestante - v_CantidadADescontar;
    END LOOP;
    
    RETURN v_IdMovimiento;
END;
$$ LANGUAGE plpgsql;