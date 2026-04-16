async function cargarEstadisticas() {
    try {
        const stats = await obtenerEstadisticas();
        const totalProductos = document.getElementById('totalProductos');
        const totalMovimientos = document.getElementById('totalMovimientos');
        const totalAlertas = document.getElementById('totalAlertas');
        const servidorActivoSpan = document.getElementById('servidorActivo');
        if (totalProductos) totalProductos.textContent = stats.totalProductos || 0;
        if (totalMovimientos) totalMovimientos.textContent = stats.totalMovimientos || 0;
        if (totalAlertas) totalAlertas.textContent = stats.totalAlertas || 0;
        if (servidorActivoSpan) servidorActivoSpan.textContent = getServidor();
    } catch (error) {
        console.error('Error cargando estadísticas:', error);
    }
}

async function cargarAlertas() {
    const alertasList = document.getElementById('alertasList');
    if (!alertasList) return;
    try {
        const resultado = await obtenerAlertasStockBajo();
        if (resultado.success && resultado.data.length > 0) {
            alertasList.innerHTML = '';
            resultado.data.forEach(alerta => {
                const li = document.createElement('li');
                li.className = 'list-group-item bg-dark text-warning';
                li.innerHTML = `<div class="d-flex justify-content-between align-items-center"><div><strong>${escapeHtml(alerta.producto)}</strong><br><small>${alerta.codigobarras || 'Sin código'}</small></div><div class="text-end"><span class="badge bg-danger">Stock: ${alerta.stocktotal}</span><span class="badge bg-secondary">Mínimo: ${alerta.stockminimo}</span><span class="badge bg-warning text-dark">Faltan: ${alerta.faltante}</span></div></div>`;
                alertasList.appendChild(li);
            });
        } else {
            alertasList.innerHTML = '<li class="list-group-item bg-dark text-success">✅ No hay productos con stock bajo</li>';
        }
    } catch (error) {
        alertasList.innerHTML = '<li class="list-group-item bg-dark text-danger">❌ Error cargando alertas</li>';
    }
}

async function cargarUltimosMovimientos() {
    const tbody = document.getElementById('ultimosMovimientos');
    if (!tbody) return;
    try {
        const resultado = await obtenerMovimientos(10);
        if (resultado.success && resultado.data.length > 0) {
            tbody.innerHTML = '';
            resultado.data.forEach(mov => {
                const row = document.createElement('tr');
                const tipoClass = mov.tipo === 'Entrada' ? 'text-success' : 'text-danger';
                let fechaHora = '-';
                if (mov.fecha) {
                    const fechaObj = new Date(mov.fecha);
                    if (!isNaN(fechaObj)) {
                        fechaHora = fechaObj.toLocaleString('es-MX', { timeZone: 'America/Cancun', hour12: false });
                    } else {
                        fechaHora = mov.fecha.substring(0, 16);
                    }
                }
                row.innerHTML = `<td><small>${fechaHora}</small></td><td class="${tipoClass}">${mov.tipo || '-'}</td><td>${mov.almacen || '-'}</td><td>${mov.usuario || '-'}</td><td>${mov.cantidad || '-'}</td>`;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted">No hay movimientos registrados</td></tr>';
        }
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-danger">Error cargando movimientos</td></tr>';
    }
}

async function verificarServidores() {
    const container = document.getElementById('servidoresStatus');
    if (!container) return;
    const servidores = ['A', 'B', 'local'];
    const nombres = { A: 'Supabase A', B: 'Supabase B', local: 'PostgreSQL Local' };
    container.innerHTML = '';
    for (const server of servidores) {
        const status = await testServerConnection(server);
        const color = status ? '#10b981' : '#ef4444';
        const badge = document.createElement('span');
        badge.className = 'badge';
        badge.style.backgroundColor = color;
        badge.style.padding = '8px 12px';
        badge.innerHTML = `${nombres[server]}: ${status ? '✅ Conectado' : '❌ Desconectado'}`;
        container.appendChild(badge);
    }
}

function configurarSelectorServidor() {
    const selector = document.getElementById('selectorServidor');
    if (!selector) return;
    selector.value = getServidor();
    selector.addEventListener('change', async function() {
        const nuevoServidor = this.value;
        setServidor(nuevoServidor);
        const servidorActivoSpan = document.getElementById('servidorActivo');
        if (servidorActivoSpan) servidorActivoSpan.textContent = nuevoServidor;
        await cargarEstadisticas();
        await cargarAlertas();
        await cargarUltimosMovimientos();
        const msgDiv = document.createElement('div');
        msgDiv.className = 'alert alert-info mt-3';
        msgDiv.innerHTML = `🔄 Cambiado a servidor ${nuevoServidor === 'A' ? 'Supabase A' : (nuevoServidor === 'B' ? 'Supabase B' : 'PostgreSQL Local')}`;
        document.querySelector('.row.mt-4 .card').prepend(msgDiv);
        setTimeout(() => msgDiv.remove(), 3000);
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function sincronizarManual() {
    const btn = document.getElementById('btnSincronizar');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sincronizando...';
    }
    try {
        const servidorActual = getServidor();
        const resultado = await ejecutarSincronizacion(servidorActual);
        if (resultado.exitosos > 0) {
            alert(`✅ Sincronización completada: ${resultado.exitosos} operaciones`);
            await cargarEstadisticas();
            await cargarAlertas();
            await cargarUltimosMovimientos();
            await verificarServidores();
        } else if (resultado.pendientes === 0) {
            alert('📭 No hay operaciones pendientes');
        } else {
            alert(`⚠️ ${resultado.errores} errores en sincronización`);
        }
    } catch (error) {
        alert('❌ Error al sincronizar');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-sync"></i> Sincronizar Ahora';
        }
    }
}

function iniciarDetector() {
    const servidorActual = getServidor();
    if (servidorActual === 'local') return;
    iniciarDetectorServidor(servidorActual, 15000, async () => {
        await cargarEstadisticas();
        await cargarAlertas();
        await cargarUltimosMovimientos();
        await verificarServidores();
    });
}

// Sincronizar datos al iniciar sesión
async function sincronizarDatosInicial() {
    const servidor = getServidor();
    await fetch(`${API_BASE_URL}/sync/ejecutar_sync.php?accion=sincronizar_datos&servidor=${servidor}`);
}

document.addEventListener('DOMContentLoaded', async () => {
    await cargarEstadisticas();
    await cargarAlertas();
    await cargarUltimosMovimientos();
    await verificarServidores();
    configurarSelectorServidor();
    iniciarDetector();

     //(sincronización automática cada 30 minutos)
    setInterval(async () => {
        const servidor = getServidor();
        if (servidor !== 'local') {
            await fetch(`${API_BASE_URL}/sync/ejecutar_sync.php?accion=sincronizar_datos&servidor=${servidor}`);
            console.log('Sincronización automática de datos completada');
        }
    }, 1800000); // 30 minutos

    const btnSync = document.getElementById('btnSincronizar');
    if (btnSync) btnSync.addEventListener('click', sincronizarManual);
});