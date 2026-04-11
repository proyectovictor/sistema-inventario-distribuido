// auth.js - Manejo de autenticación y sesión

// Usar la misma variable global
window.API_BASE_URL = window.API_BASE_URL || 'http://localhost/sistema-inventario-distribuido/backend';

function getToken() {
    return localStorage.getItem('token');
}

function getUsuario() {
    const usuario = localStorage.getItem('usuario');
    return usuario ? JSON.parse(usuario) : null;
}

function getSucursalDefecto() {
    const usuario = getUsuario();
    if (!usuario) return 'A';
    if (usuario.sucursal_defecto === 'AMBAS') return 'A';
    return usuario.sucursal_defecto;
}

function puedeCambiarSucursal() {
    const usuario = getUsuario();
    return usuario && usuario.sucursal_defecto === 'AMBAS';
}

function isAuthenticated() {
    return getToken() !== null;
}

function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('usuario');
    localStorage.removeItem('sucursal_seleccionada');
    window.location.href = 'login.html';
}

async function verificarSesion() {
    const token = getToken();
    if (!token) {
        window.location.href = 'login.html';
        return false;
    }
    
    try {
        const response = await fetch(`${window.API_BASE_URL}/verificar_token.php`, {
            headers: { 'X-Auth-Token': token }
        });
        const data = await response.json();
        
        if (!data.valid) {
            logout();
            return false;
        }
        return true;
    } catch (error) {
        logout();
        return false;
    }
}

function protegerPagina() {
    if (!isAuthenticated()) {
        window.location.href = 'login.html';
        return false;
    }
    return true;
}