// Función para mostrar alertas personalizadas
function mostrarAlerta(mensaje, tipo = "success") {
    const alertaDiv = document.createElement("div");
    alertaDiv.className = `custom-alert ${tipo}`;
    alertaDiv.innerHTML = `
                <div class="alert-content">
                    <i class="bi ${tipo === "success"
            ? "bi-check-circle"
            : tipo === "error"
                ? "bi-x-circle"
                : "bi-exclamation-triangle"
        } me-2"></i>
                    ${mensaje}
                </div>
                <button type="button" class="btn-close btn-close-white ms-3" onclick="this.parentElement.remove()"></button>
            `;
    document.body.appendChild(alertaDiv);
    setTimeout(() => alertaDiv.remove(), 5000);
}

document.getElementById("filtrarVentas").addEventListener("click", function () {
    const desde = document.getElementById("fechaDesde").value;
    const hasta = document.getElementById("fechaHasta").value;
    const q = document.getElementById("buscarVentas").value || "";
    if (!desde || !hasta) {
        mostrarAlerta("Por favor seleccione ambas fechas", "warning");
        return;
    }
    const params = new URLSearchParams({ desde, hasta, q });
    window.location.href = `/productos/ventas?${params.toString()}`;
});

// Establecer valores iniciales desde la URL o defaults
(function initFiltrosDesdeURL() {
    const p = new URLSearchParams(window.location.search);
    const desde = p.get("desde");
    const hasta = p.get("hasta");
    const q = p.get("q") || "";
    if (desde) document.getElementById("fechaDesde").value = desde;
    if (hasta) document.getElementById("fechaHasta").value = hasta;
    if (q) document.getElementById("buscarVentas").value = q;
    if (!desde || !hasta) {
        const hoy = new Date();
        const hace30Dias = new Date();
        hace30Dias.setDate(hace30Dias.getDate() - 30);
        document.getElementById("fechaDesde").value =
            document.getElementById("fechaDesde").value ||
            hace30Dias.toISOString().split("T")[0];
        document.getElementById("fechaHasta").value =
            document.getElementById("fechaHasta").value ||
            hoy.toISOString().split("T")[0];
    }
})();
