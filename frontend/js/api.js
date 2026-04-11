/**
 * api.js
 * Comunicación con el backend PHP
 */

const API_BASE_URL = 'http://localhost/sistema-inventario-distribuido/backend';

let servidorActivo = 'A';

function setServidor(sucursal) {
    if (['A', 'B', 'local'].includes(sucursal)) {
        servidorActivo = sucursal;
        return true;
    }
    return false;
}

function getServidor() {
    return servidorActivo;
}

async function consultar(accion, params = {}) {
    params.accion = accion;
    params.sucursal = servidorActivo;
    
    const url = new URL(`${API_BASE_URL}/consultar.php`);
    Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) {
            return { success: true, data: data.data, servidor: data.servidor };
        } else {
            return { success: false, error: data.error };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
}

async function registrarEntrada(datos) {
    const body = {
        tipo: 'entrada',
        idproducto: datos.idproducto,
        cantidad: datos.cantidad,
        lote: datos.lote || `LOTE-${Date.now()}`,
        fechaingreso: datos.fechaingreso || new Date().toISOString().split('T')[0],
        ubicacion: datos.ubicacion || 'General',
        observaciones: datos.observaciones || '',
        idalmacen: datos.idalmacen || 1,
        idusuario: datos.idusuario || 1
    };
    
    try {
        const response = await fetch(`${API_BASE_URL}/insertar.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Sucursal': servidorActivo },
            body: JSON.stringify(body)
        });
        const data = await response.json();
        if (data.exito === true) {
            return { success: true, id_movimiento: data.id_movimiento };
        } else {
            return { success: false, error: data.error || data.mensaje, enCola: data.cola === true };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
}

async function registrarSalida(datos) {
    const body = {
        tipo: 'salida',
        idproducto: datos.idproducto,
        cantidad: datos.cantidad,
        observaciones: datos.observaciones || '',
        idalmacen: datos.idalmacen || 1,
        idusuario: datos.idusuario || 1
    };
    
    try {
        const response = await fetch(`${API_BASE_URL}/insertar.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Sucursal': servidorActivo },
            body: JSON.stringify(body)
        });
        const data = await response.json();
        if (data.exito === true) {
            return { success: true, id_movimiento: data.id_movimiento };
        } else {
            return { success: false, error: data.error || data.mensaje, enCola: data.cola === true };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
}

async function obtenerProductos(idProducto = null) {
    const params = {};
    if (idProducto) params.id = idProducto;
    return await consultar('productos', params);
}

async function obtenerStock(idProducto) {
    return await consultar('stock', { id: idProducto });
}

async function obtenerAlertasStockBajo() {
    return await consultar('alertas');
}

async function obtenerMovimientos(limite = 50) {
    return await consultar('movimientos', { limite });
}

async function obtenerProductosReales() {
    const resultado = await obtenerProductos();
    if (resultado.success) {
        return resultado.data.map(p => ({
            id: p.idproducto,
            nombre: p.nombre,
            codigo: p.codigobarras,
            precio: p.precio,
            stockminimo: p.stockminimo
        }));
    }
    return [];
}

async function obtenerEstadisticas() {
    const productos = await obtenerProductos();
    const alertas = await obtenerAlertasStockBajo();
    const movimientos = await obtenerMovimientos(100);
    return {
        totalProductos: productos.success ? productos.data.length : 0,
        totalAlertas: alertas.success ? alertas.data.length : 0,
        totalMovimientos: movimientos.success ? movimientos.data.length : 0
    };
}

async function testServerConnection(server) {
    try {
        const url = `${API_BASE_URL}/consultar.php?accion=productos&sucursal=${server}&limite=1`;
        const response = await fetch(url, { method: 'GET', headers: { 'X-Sucursal': server } });
        const data = await response.json();
        return data.success === true;
    } catch (error) {
        return false;
    }
}

async function ejecutarSincronizacion(forceServer = null) {
    try {
        let url = `${API_BASE_URL}/sync/sync_manager.php`;
        if (forceServer) url += `?force=${forceServer}`;
        const response = await fetch(url, { method: 'GET', cache: 'no-cache' });
        return await response.json();
    } catch (error) {
        return { error: error.message, exitosos: 0, pendientes: 0 };
    }
}

let servidorEstabaCaido = false;
let intervaloDetector = null;

async function isServerAvailable(server) {
    try {
        const url = `${API_BASE_URL}/consultar.php?accion=productos&sucursal=${server}&limite=1`;
        const response = await fetch(url, { method: 'GET', headers: { 'X-Sucursal': server }, cache: 'no-cache' });
        const data = await response.json();
        return data.success === true;
    } catch (error) {
        return false;
    }
}

function iniciarDetectorServidor(servidorPrincipal = 'A', intervaloMs = 15000, onRecovery = null) {
    if (intervaloDetector) clearInterval(intervaloDetector);
    
    (async () => {
        const disponible = await isServerAvailable(servidorPrincipal);
        servidorEstabaCaido = !disponible;
    })();
    
    intervaloDetector = setInterval(async () => {
        const disponible = await isServerAvailable(servidorPrincipal);
        if (disponible && servidorEstabaCaido) {
            if (onRecovery) await onRecovery(servidorPrincipal);
            else await ejecutarSincronizacion(servidorPrincipal);
            servidorEstabaCaido = false;
        } else if (!disponible && !servidorEstabaCaido) {
            servidorEstabaCaido = true;
        }
    }, intervaloMs);
}

function detenerDetectorServidor() {
    if (intervaloDetector) {
        clearInterval(intervaloDetector);
        intervaloDetector = null;
    }
}