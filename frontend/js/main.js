// ===== FORMULARIO =====
const form = document.getElementById("formMovimiento");

if (form) {
  form.addEventListener("submit", function (e) {
    e.preventDefault();

    const data = {
      sucursal: document.getElementById("sucursal").value,
      tipo: document.getElementById("tipo").value,
      producto: document.getElementById("producto").value,
      cantidad: document.getElementById("cantidad").value,
      lote: document.getElementById("lote").value,
      ubicacion: document.getElementById("ubicacion").value,
      observaciones: document.getElementById("obs").value
    };

    const mensaje = document.getElementById("mensaje");

    // VALIDACIÓN
    if (
      data.sucursal === "" ||
      data.tipo === "" ||
      data.producto === "" ||
      data.cantidad === "" ||
      data.cantidad <= 0
    ) {
      mensaje.innerHTML =
        "<div class='alert alert-danger'>❌ Completa todos los campos obligatorios</div>";
      return;
    }

    // GUARDAR EN LOCALSTORAGE
    let inventario = JSON.parse(localStorage.getItem("inventario")) || [];
    inventario.push(data);
    localStorage.setItem("inventario", JSON.stringify(inventario));

    // MENSAJE
    mensaje.innerHTML =
      "<div class='alert alert-success'>✅ Movimiento guardado correctamente</div>";

    // LIMPIAR FORM
    form.reset();
  });
}

// ===== TABLA CONSULTAS =====
const tabla = document.getElementById("tablaDatos");

if (tabla) {
  let inventario = JSON.parse(localStorage.getItem("inventario")) || [];

  tabla.innerHTML = "";

  inventario.forEach((item, index) => {
    let colorTipo =
      item.tipo === "Entrada"
        ? "<span class='badge bg-success'>Entrada</span>"
        : "<span class='badge bg-danger'>Salida</span>";

    tabla.innerHTML += `
      <tr>
        <td>${item.producto}</td>
        <td>${item.sucursal}</td>
        <td>${colorTipo}</td>
        <td>${item.cantidad}</td>
        <td>${item.lote || "-"}</td>
        <td>${item.ubicacion || "-"}</td>
        <td>
  <button class="btn btn-warning btn-sm me-2" onclick="editar(${index})">
    <i class="fa fa-edit"></i>
  </button>
  <button class="btn btn-danger btn-sm" onclick="eliminar(${index})">
    <i class="fa fa-trash"></i>
  </button>
</td>
      </tr>
    `;
  });
}
// ===== DASHBOARD DINÁMICO =====
const movimientosHTML = document.getElementById("movimientos");
const productosHTML = document.getElementById("productos");
const alertasHTML = document.getElementById("alertas");

if (movimientosHTML && productosHTML && alertasHTML) {

  let inventario = JSON.parse(localStorage.getItem("inventario")) || [];

  // Movimientos
  movimientosHTML.textContent = inventario.length;

  // Productos únicos
  let productosUnicos = [...new Set(inventario.map(item => item.producto))];
  productosHTML.textContent = productosUnicos.length;

  // Alertas (cantidad menor a 5)
  let alertas = inventario.filter(item => item.cantidad < 5);
  alertasHTML.textContent = alertas.length;
}
// ===== AUTOCOMPLETE PRODUCTOS =====

const productosLista = [
  "Capacitor 100μF",
  "Capacitor 10μF",
  "Resistor 1k Ohm",
  "Resistor 10k Ohm",
  "Microcontrolador PIC16F877A",
  "Conector USB-C",
  "Conector HDMI",
  "Transistor BC547",
  "LED Rojo 5mm"
];

const inputProducto = document.getElementById("producto");
const sugerencias = document.getElementById("sugerencias");

if (inputProducto) {

  inputProducto.addEventListener("input", function () {
    let valor = this.value.toLowerCase();
    sugerencias.innerHTML = "";

    if (valor === "") return;

    let filtrados = productosLista.filter(p =>
      p.toLowerCase().includes(valor)
    );

    filtrados.forEach(p => {
      let div = document.createElement("div");
      div.classList.add("autocomplete-item");
      div.textContent = p;

      div.onclick = () => {
        inputProducto.value = p;
        sugerencias.innerHTML = "";
      };

      sugerencias.appendChild(div);
    });
  });

  // cerrar si haces click fuera
  document.addEventListener("click", function (e) {
    if (!e.target.closest(".position-relative")) {
      sugerencias.innerHTML = "";
    }
  });
}

function eliminar(index) {
  let inventario = JSON.parse(localStorage.getItem("inventario")) || [];

  if (confirm("¿Eliminar registro?")) {
    inventario.splice(index, 1);
    localStorage.setItem("inventario", JSON.stringify(inventario));
    location.reload();
  }
}

function editar(index) {
  let inventario = JSON.parse(localStorage.getItem("inventario")) || [];
  let item = inventario[index];

  localStorage.setItem("editarItem", JSON.stringify(item));
  window.location.href = "formulario.html";
}