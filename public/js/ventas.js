const facturaModal = new bootstrap.Modal(
    document.getElementById("facturaModal")
);
const detallesModal = new bootstrap.Modal(
    document.getElementById("detallesModal")
);

let ventaModal = new bootstrap.Modal(document.getElementById('ventaModal'));

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

function mostrarFactura(id) {
    document.getElementById("facturaFrame").src = `/facturas/${id}/imprimir`;
    document.querySelector(
        "#facturaModal .modal-title"
    ).textContent = `Factura #${id}`;
    facturaModal.show();
}

function mostrarDetalles(id) {
    $.ajax({
        url: `/api/facturas/${id}/detalles`,
        dataType: "json",
        success: function (data) {
            // Información del cliente
            $("#detallesCliente").html(`
                        <p><strong>Nombre:</strong> ${data.cliente.nombre}</p>
                        <p><strong>Dirección:</strong> ${data.cliente.direccion || "No especificada"
                }</p>
                        <p><strong>Teléfono:</strong> ${data.cliente.telefono || "No especificado"
                }</p>
                    `);

            // Información de la factura
            $("#detallesFactura").html(`
                <p><strong>Factura #:</strong> ${data.factura.id}</p>
                <p><strong>Fecha:</strong> ${new Date(data.factura.fecha).toLocaleString()}</p>
                <p><strong>Forma de Pago:</strong> ${data.factura.forma_pago.charAt(0).toUpperCase() + data.factura.forma_pago.slice(1)}</p>
                <p><strong>Descuento:</strong> ${data.factura.descuento}</p>
                <p><strong>Servicio:</strong> ${data.factura.servicio}</p>
                <p><strong>Pago con Tarjeta:</strong> ${data.factura.pago_tarjeta}</p>
            `);

            // Productos
            const tbody = $("#detallesProductos");
            tbody.empty();
            let totalGeneral = 0;

            data.productos.forEach((producto) => {
                const cantidad = producto.cantidad || 0;
                const precio = producto.precio || 0;
                const subtotal = producto.subtotal || 0;
                totalGeneral += subtotal;

                tbody.append(`
                    <tr>
                        <td>${producto.nombre}</td>
                        <td class="text-end">${cantidad.toFixed(2)}</td>
                        <td>${producto.unidad || "N/A"}</td>
                        <td class="text-end">$${precio.toFixed(2)}</td>
                        <td class="text-end">$${subtotal.toFixed(2)}</td>
                    </tr>
                `);
            });

            // totalGeneral += data.factura.pago_tarjeta + data.factura.servicio;

            $("#detallesTotal").text(`$${totalGeneral.toFixed(2)}`);
            detallesModal.show();
        },
        error: function () {
            mostrarAlerta("Error al cargar los detalles de la factura", "error");
        },
    });
}

function editarFactura(id) {
    $.get(`/api/ventas/${id}`, function(venta) {
        $('#ventaId').val(venta.id);
        $('#fechaVenta').val(venta.fecha);
        $('#pago').val(venta.forma_pago);

        cargarUsuarios(venta.usuario_id);
        cargarClientes(venta.cliente_id);

        $('#modalTitle').text('Editar Venta');
        ventaModal.show();
    });
}

function cargarClientes(seleccionado = null) {
    $.ajax({
        url: '/api/clientes',
        method: 'GET',
        success: function(clientes) {
            $('#cliente').empty();

            clientes.forEach(c => {
                const option = $('<option>', {
                    value: c.id,
                    text: c.nombre
                });

                if (seleccionado && seleccionado == c.id) {
                    option.prop('selected', true);
                }

                $('#cliente').append(option);
            });
        }
    });
}

function cargarUsuarios(seleccionado = null) {
    $.ajax({
        url: '/api/usuarios',
        method: 'GET',
        success: function(usuarios) {
            $('#usuario').empty();

            usuarios.forEach(c => {
                const option = $('<option>', {
                    value: c.id,
                    text: c.nombre
                });

                if (seleccionado && seleccionado == c.id) {
                    option.prop('selected', true);
                }

                $('#usuario').append(option);
            });
        }
    });
}

$('#guardarVenta').click(async function() {
    const venta = {
        fecha: $('#fechaVenta').val(),
        cliente_id: $('#cliente').val(),
        usuario_id: $('#usuario').val(),
        forma_pago: $('#pago').val()
    };

    if (!venta.fecha) {
        alert('La fecha es requerida');
        return;
    }

    if (!venta.cliente_id) {
        alert('El cliente es requerido');
        return;
    }

    if (!venta.usuario_id) {
        alert('El usuario es requerido');
        return;
    }

    if (!venta.forma_pago) {
        alert('El tipo de pago es requerido');
        return;
    }

    try {
        const resp = await fetch("/api/ventas/" + $('#ventaId').val(), {
            method: "PUT",
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: JSON.stringify(venta)
        });

        const data = await resp.json();

        if (data.success) {
            Swal.fire({
                icon: "success",
                title: "Venta Actualizada",
                timer: 1500,
                showConfirmButton: false,
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: data.error || "No se pudo guardar la venta",
            });
        }
    } catch (error) {
        alert(error.message);
    }
});

function eliminarFactura(id) {
    if (!confirm("¿Está seguro de eliminar esta factura?")) {
        return;
    }

    fetch(`/api/facturas/${id}`, {
        method: "DELETE",
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error("Error al eliminar la factura");
            }
            location.reload();
        })
        .catch((error) => alert(error.message));
}

function imprimirFactura() {
    const frame = document.getElementById("facturaFrame");
    frame.contentWindow.print();
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
    window.location.href = `/ventas?${params.toString()}`;
});

// Establecer valores iniciales desde la URL o defaults
(function initFiltrosDesdeURL() {
    const p = new URLSearchParams(window.location.search);
    let desde = p.get("desde");
    let hasta = p.get("hasta");
    const q = p.get("q") || "";

    const hoy = new Date().toISOString().split("T")[0];

    document.getElementById("fechaDesde").value = desde || hoy;
    document.getElementById("fechaHasta").value = hasta || hoy;
    if (q) document.getElementById("buscarVentas").value = q;
})();

// Inicializar tooltips
const tooltipTriggerList = document.querySelectorAll(
    '[data-bs-toggle="tooltip"]'
);
const tooltipList = [...tooltipTriggerList].map(
    (tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl)
);

// Función para calcular el total general
function calcularTotales() {
    const totales = document.querySelectorAll("tbody#ventasTabla td[data-total]");
    let total = 0, servicio = 0, tarjeta = 0, servicioEfectivo = 0, servicioTransferencia = 0, servicioTarjeta = 0, tef = 0, ttr = 0, tta = 0;
    document.querySelectorAll("#ventasTabla tr").forEach((tr) => {
        // Total general
        const totalEl = tr.querySelector("[data-total]");
        const forma = tr.children[4]?.innerText?.trim().toLowerCase();
        const val = Number(totalEl?.dataset.total || 0);
        total += val;

        // Servicio
        const totalSer = tr.querySelector("[data-servicio]");
        const valSer = Number(totalSer?.dataset.servicio || 0);
        servicio += valSer;

        // Valor de Tarjeta
        const totalTarjeta = tr.querySelector("[data-tarjeta]");
        const valTar = Number(totalTarjeta?.dataset.tarjeta || 0);
        tarjeta += valTar;

        if (forma === "efectivo") tef += val;
        else if (forma === "transferencia") ttr += val;
        else if (forma === "tarjeta") tta += val;

        // Calculo detallado de servicio por tipo de pago
        if(valSer > 0){
            if (forma === "efectivo") servicioEfectivo += valSer;
            else if (forma === "transferencia") servicioTransferencia += valSer;
            else if (forma === "tarjeta") servicioTarjeta += valSer;
        }
    });

    const money = (n) =>
    `$${Number(n).toLocaleString("es-CO", {
        maximumFractionDigits: 0
    })}`;

    document.getElementById("totalGeneral").textContent = money(total);
    document.getElementById("totalEfectivo").textContent = money(tef);
    document.getElementById("totalTransferencia").textContent = money(ttr);
    document.getElementById("totalTarjeta").textContent = money(tta);
    document.getElementById("totalTarjetaPor").textContent = money(tarjeta);
    document.getElementById("totalServicio").textContent = money(servicio);

    // Valores de detalle de servicio
    document.getElementById("totalEfectivoServicio").textContent = money(servicioEfectivo);
    document.getElementById("totalTransferenciaServicio").textContent = money(servicioTransferencia);
    document.getElementById("totalTarjetaServicio").textContent = money(servicioTarjeta);
}

// Calcular total al cargar la página
calcularTotales();

// Manejador para reimprimir facturas y ver detalles
document.querySelectorAll(".btn-reimprimir").forEach((button) => {
    button.addEventListener("click", function () {
        const facturaId = this.getAttribute("data-factura-id");
        mostrarFactura(facturaId);
    });
});

document.querySelectorAll(".btn-detalles").forEach((button) => {
    button.addEventListener("click", function () {
        const facturaId = this.getAttribute("data-factura-id");
        mostrarDetalles(facturaId);
    });
});

document.querySelectorAll(".btn-eliminar").forEach((button) => {
    button.addEventListener("click", function () {
        const facturaId = this.getAttribute("data-factura-id");
        eliminarFactura(facturaId);
    });
});

document.querySelectorAll(".btn-editar").forEach((button) => {
    button.addEventListener("click", function () {
        const facturaId = this.getAttribute("data-factura-id");
        editarFactura(facturaId);
    });
});
