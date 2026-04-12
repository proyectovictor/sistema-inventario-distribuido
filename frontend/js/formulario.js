const formMovimiento = document.getElementById('formMovimiento');
const sucursalSelect = document.getElementById('sucursal');
const almacenInput = document.getElementById('almacen');
const tipoSelect = document.getElementById('tipo');
const productoInput = document.getElementById('producto');
const cantidadInput = document.getElementById('cantidad');
const loteInput = document.getElementById('lote');
const ubicacionInput = document.getElementById('ubicacion');
const fechaIngresoDiv = document.getElementById('divFechaIngreso');
const fechaIngresoInput = document.getElementById('fechaingreso');
const obsTextarea = document.getElementById('obs');
const mensajeDiv = document.getElementById('mensaje');
const sugerenciasDiv = document.getElementById('sugerencias');

let productosCache = [];
let idAlmacenActual = null;

function actualizarAlmacenPorSucursal() {
    const sucursal = sucursalSelect?.value;
    if (sucursal === 'A') {
        almacenInput.value = '🏭 Almacén Central (Id: 1)';
        idAlmacenActual = 1;
    } else if (sucursal === 'B') {
        almacenInput.value = '🏭 Almacén Norte (Id: 2)';
        idAlmacenActual = 2;
    } else {
        almacenInput.value = '';
        idAlmacenActual = null;
    }
}

function toggleFechaIngreso() {
    const tipo = tipoSelect?.value?.toLowerCase();
    if (tipo === 'entrada') {
        fechaIngresoDiv.style.display = 'block';
        if (!fechaIngresoInput.value) fechaIngresoInput.value = new Date().toISOString().split('T')[0];
    } else {
        fechaIngresoDiv.style.display = 'none';
    }
}

async function cargarProductosReales() {
    try {
        const resultado = await obtenerProductosReales();
        if (resultado && resultado.length > 0) {
            productosCache = resultado;
        } else {
            productosCache = [
                { id: 1, nombre: 'Capacitor 100µF', codigo: 'ELEC-00125' },
                { id: 2, nombre: 'Resistor 1k Ohm', codigo: 'ELEC-00345' },
                { id: 3, nombre: 'Microcontrolador PIC16F877A', codigo: 'ELEC-00567' },
                { id: 4, nombre: 'Conector USB-C', codigo: 'ELEC-00789' }
            ];
        }
    } catch (error) {
        console.error('Error cargando productos:', error);
    }
}

function configurarAutocomplete() {
    if (!productoInput) return;
    productoInput.addEventListener('input', function() {
        const valor = this.value.toLowerCase().trim();
        sugerenciasDiv.innerHTML = '';
        if (valor === '') return;
        const filtrados = productosCache.filter(p => p.nombre.toLowerCase().includes(valor) || (p.codigo && p.codigo.toLowerCase().includes(valor)));
        filtrados.slice(0, 10).forEach(p => {
            const div = document.createElement('div');
            div.classList.add('autocomplete-item');
            div.innerHTML = `<strong>${p.nombre}</strong> ${p.codigo ? `<small>(${p.codigo})</small>` : ''}`;
            div.onclick = () => {
                productoInput.value = p.nombre;
                productoInput.dataset.id = p.id;
                sugerenciasDiv.innerHTML = '';
            };
            sugerenciasDiv.appendChild(div);
        });
    });
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.position-relative')) sugerenciasDiv.innerHTML = '';
    });
}

async function cargarEstadisticasFormulario() {
    const movimientosSpan = document.getElementById('movimientos');
    const productosSpan = document.getElementById('productos');
    const alertasSpan = document.getElementById('alertas');
    if (!movimientosSpan && !productosSpan && !alertasSpan) return;
    try {
        const stats = await obtenerEstadisticas();
        if (movimientosSpan) movimientosSpan.textContent = stats.totalMovimientos;
        if (productosSpan) productosSpan.textContent = stats.totalProductos;
        if (alertasSpan) alertasSpan.textContent = stats.totalAlertas;
    } catch (error) {
        if (movimientosSpan) movimientosSpan.textContent = '0';
        if (productosSpan) productosSpan.textContent = '0';
        if (alertasSpan) alertasSpan.textContent = '0';
    }
}

async function registrarMovimiento(event) {
    event.preventDefault();
    const usuario = getUsuario();
    const idUsuario = usuario ? usuario.id : 1;
    const sucursal = sucursalSelect?.value;
    const tipo = tipoSelect?.value?.toLowerCase();
    const productoNombre = productoInput?.value;
    const productoId = productoInput?.dataset?.id;
    const cantidad = parseInt(cantidadInput?.value);
    const lote = loteInput?.value;
    const ubicacion = ubicacionInput?.value;
    const fechaIngreso = fechaIngresoInput?.value;
    const observaciones = obsTextarea?.value;
    
    if (!sucursal) return mostrarMensaje('❌ Selecciona una sucursal (A o B)', 'danger');
    if (!idAlmacenActual) return mostrarMensaje('❌ Error: No se pudo determinar el almacén', 'danger');
    if (!tipo || !productoNombre || !cantidad || cantidad <= 0) return mostrarMensaje('❌ Completa todos los campos obligatorios', 'danger');
    
    let idProducto = productoId;
    if (!idProducto) {
        const encontrado = productosCache.find(p => p.nombre === productoNombre);
        if (encontrado) idProducto = encontrado.id;
        else return mostrarMensaje('❌ Producto no encontrado. Selecciona uno de la lista.', 'danger');
    }
    
    setServidor(sucursal);
    
    let resultado;
    if (tipo === 'entrada') {
        resultado = await registrarEntrada({
            idproducto: idProducto, cantidad, lote, fechaingreso: fechaIngreso || new Date().toISOString().split('T')[0],
            ubicacion, observaciones, idalmacen: idAlmacenActual, idusuario: idUsuario
        });
    } else {
        resultado = await registrarSalida({
            idproducto: idProducto, cantidad, observaciones, idalmacen: idAlmacenActual, idusuario: idUsuario
        });
    }
    
    if (resultado.success) {
        mostrarMensaje(`✅ ${tipo === 'entrada' ? 'Entrada' : 'Salida'} registrada. ID: ${resultado.id_movimiento}`, 'success');

        // Limpiar solo ciertos campos, no todo el formulario
        cantidadInput.value = '';
        loteInput.value = '';
        ubicacionInput.value = '';
        obsTextarea.value = '';
        if (tipo === 'entrada') fechaIngresoInput.value = new Date().toISOString().split('T')[0];

        // Limpiar producto
        productoInput.value = '';
        productoInput.dataset.id = '';

        // Actualizar estadísticas
        cargarEstadisticasFormulario();

        // Opcional: mantener el foco en el campo producto para siguiente registro
        productoInput.focus();
    } else {
        if (resultado.enCola) {
            mostrarMensaje(`⚠️ Servidor ${sucursal} no disponible. Operación guardada en cola. Se sincronizará automáticamente.`, 'warning');
        } else {
            mostrarMensaje(`❌ Error: ${resultado.error}`, 'danger');
        }
    }
}

function mostrarMensaje(texto, tipo) {
    if (!mensajeDiv) return;
    const clases = { success: 'alert-success', danger: 'alert-danger', warning: 'alert-warning' };
    mensajeDiv.innerHTML = `<div class="alert ${clases[tipo] || 'alert-info'}">${texto}</div>`;
    setTimeout(() => { if (mensajeDiv) mensajeDiv.innerHTML = ''; }, 5000);
}

if (sucursalSelect) sucursalSelect.addEventListener('change', actualizarAlmacenPorSucursal);
if (tipoSelect) tipoSelect.addEventListener('change', toggleFechaIngreso);

document.addEventListener('DOMContentLoaded', async () => {
    await cargarProductosReales();
    configurarAutocomplete();
    cargarEstadisticasFormulario();
    if (formMovimiento) formMovimiento.addEventListener('submit', registrarMovimiento);
});