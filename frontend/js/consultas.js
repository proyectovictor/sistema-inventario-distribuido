const tablaBody = document.getElementById('tablaDatos');
const searchInput = document.getElementById('searchInput');
let productosData = [];

async function cargarProductosConStock() {
    if (!tablaBody) return;
    tablaBody.innerHTML = '<tr><td colspan="6" class="text-center">Cargando datos...</td></tr>';
    try {
        const productosResult = await obtenerProductos();
        if (!productosResult || !productosResult.success) {
            tablaBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Error: ${productosResult?.error || 'Desconocido'}</td></tr>`;
            return;
        }
        const productos = productosResult.data;
        productosData = productos;
        if (!productos || productos.length === 0) {
            tablaBody.innerHTML = '<tr><td colspan="6" class="text-center">No hay productos registrados</td></tr>';
            return;
        }
        const productosConStock = [];
        for (const p of productos) {
            const stockResult = await obtenerStock(p.idproducto);
            const stockActual = stockResult.success ? (stockResult.data?.stock || 0) : 0;
            productosConStock.push({
                id: p.idproducto, nombre: p.nombre, codigo: p.codigobarras || '-',
                precio: parseFloat(p.precio) || 0, stock: stockActual,
                stockminimo: parseInt(p.stockminimo) || 0, categoria: p.categoria_nombre || '-'
            });
        }
        renderTabla(productosConStock);
    } catch (error) {
        tablaBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Error de conexión: ${error.message}</td></tr>`;
    }
}

function renderTabla(productos) {
    if (!tablaBody) return;
    if (!productos || productos.length === 0) {
        tablaBody.innerHTML = '<tr><td colspan="6" class="text-center">No hay productos</td></tr>';
        return;
    }
    tablaBody.innerHTML = '';
    productos.forEach(p => {
        const stockClass = p.stock <= p.stockminimo ? 'text-danger fw-bold' : '';
        const precioFormateado = !isNaN(p.precio) ? p.precio.toFixed(2) : '0.00';
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${escapeHtml(p.nombre)}</strong><br><small class="text-muted">${escapeHtml(p.codigo)}</small></td>
            <td>${escapeHtml(p.categoria)}</td>
            <td class="${stockClass}">${p.stock}</td>
            <td>${p.stockminimo}</td>
            <td>$${precioFormateado}</td>
            <td>
                <button class="btn btn-sm btn-outline-info me-1" onclick="verMovimientos(${p.id})" title="Ver movimientos"><i class="fa fa-history"></i></button>
                <button class="btn btn-sm btn-outline-warning" onclick="editarProducto(${p.id})" title="Editar"><i class="fa fa-edit"></i></button>
            </td>
        `;
        tablaBody.appendChild(row);
    });
}

function filtrarProductos() {
    const searchTerm = searchInput?.value?.toLowerCase() || '';
    const filtrados = productosData.filter(p => (p.nombre || '').toLowerCase().includes(searchTerm) || (p.codigobarras || '').toLowerCase().includes(searchTerm));
    actualizarStockFiltrados(filtrados);
}

async function actualizarStockFiltrados(productos) {
    const productosConStock = [];
    for (const p of productos) {
        const stockResult = await obtenerStock(p.idproducto);
        const stockActual = stockResult.success ? (stockResult.data?.stock || 0) : 0;
        productosConStock.push({
            id: p.idproducto, nombre: p.nombre, codigo: p.codigobarras || '-',
            precio: parseFloat(p.precio) || 0, stock: stockActual,
            stockminimo: parseInt(p.stockminimo) || 0, categoria: p.categoria_nombre || '-'
        });
    }
    renderTabla(productosConStock);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function verMovimientos(idProducto) { window.location.href = `movimientos.html?id=${idProducto}`; }
function editarProducto(idProducto) { window.location.href = `editar_producto.html?id=${idProducto}`; }

function exportarExcel() {
    const rows = document.querySelectorAll('#tablaDatos tr');
    let csv = 'Producto,Código,Categoría,Stock,Stock Mínimo,Precio\n';
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            const nombre = cells[0]?.innerText.split('\n')[0] || '';
            const codigo = cells[0]?.querySelector('small')?.innerText || '';
            const categoria = cells[1]?.innerText || '';
            const stock = cells[2]?.innerText || '';
            const stockMin = cells[3]?.innerText || '';
            const precio = cells[4]?.innerText || '';
            csv += `"${nombre}","${codigo}","${categoria}",${stock},${stockMin},"${precio}"\n`;
        }
    });
    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.setAttribute('download', 'inventario.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded', () => {
    cargarProductosConStock();
    if (searchInput) searchInput.addEventListener('input', filtrarProductos);
    const exportBtn = document.getElementById('exportarBtn');
    if (exportBtn) exportBtn.addEventListener('click', exportarExcel);
});