// Variables globales
let egresoModal;
let traspasoModal;
let modoEdicion = false;

$(document).ready(function() {
    egresoModal = new bootstrap.Modal(document.getElementById('egresoModal'));
    traspasoModal = new bootstrap.Modal(document.getElementById('traspasoModal'));

    // Manejar clics en los botones de acción
    $(document).on('click', '[data-action]', function() {
        const id = $(this).data('id');
        const action = $(this).data('action');
        const tipo = $(this).data('tipo');

        if( tipo == "egresos" ){
            if (action === 'editar') {
                editarEgreso(id);
            } else if (action === 'eliminar') {
                eliminarEgreso(id);
            }
        } else if ( tipo == "traspasos" ){
            if (action === 'editar') {
                editarTraspaso(id);
            } else if (action === 'eliminar') {
                eliminarTraspaso(id);
            }
        }
    });

    $(document).on('click', '.btn-comprobante', function () {
        const comprobante = $(this).data('comprobante');

        const ext = comprobante.split('.').pop().toLowerCase();
        const src = `uploads/comprobantes/${comprobante}`;

        let contenido = '';

        if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
            // IMAGEN
            contenido = `
                <div class="d-flex justify-content-center align-items-center"
                    style="height:80vh;">
                    <img src="${src}"
                        style="
                            max-width:100%;
                            max-height:100%;
                            object-fit:contain;
                        "
                        alt="Factura">
                </div>
            `;
        } else if (ext === 'pdf') {
            // PDF
            contenido = `
                <iframe
                    src="${src}"
                    style="width:100%; height:80vh; border:0;">
                </iframe>
            `;
        } else {
            contenido = `<p class="text-center">Formato no soportado</p>`;
        }

        $('#popupFac').html(`
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content text-dark">
                    <div class="modal-header bg-dark text-light">
                        <h1 class="modal-title fs-5">
                            Factura Compra ${comprobante}
                        </h1>
                        <button type="button"
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-2">
                        ${contenido}
                    </div>

                    <div class="modal-footer">
                        <button type="button"
                                class="btn btn-secondary"
                                data-bs-dismiss="modal">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        `);

        $('#popupFac').modal('show');
    });

    // Limpiar modal al abrirlo para nuevo egreso
    $('#egresoModal').on('show.bs.modal', function(e) {
        if (!modoEdicion) {
            $('#formEgreso').trigger('reset');
            $('#egresoId').val('');
            cargarResponsables();
            $('#modalTitleEgreso').text('Nuevo Egreso');
        }
    });

    // Limpiar modo edición al cerrar el modal
    $('#egresoModal').on('hidden.bs.modal', function () {
        modoEdicion = false;
    });

    // Guardar egreso
    $('#guardarEgreso').click(async function() {
        const egreso = {
            responsable: $('#responsableEgreso').val(),
            concepto: $('#conceptoEgreso').val(),
            metodo: $('#metodoEgreso').val(),
            fecha: $('#fechaEgreso').val(),
            valor: $('#valorEgreso').val()
        };

        // =========================
        // VALIDACIÓN
        // =========================
        let valido = true;

        const campos = {
            responsableEgreso: 'El responsable es requerido',
            conceptoEgreso: 'El concepto es requerido',
            metodoEgreso: 'El método es requerido',
            fechaEgreso: 'La fecha es requerida',
            valorEgreso: 'El valor es requerido'
        };

        for (const id in campos) {
            if (!$(`#${id}`).val()) {
                $(`#${id}`).addClass('is-invalid');
                valido = false;
            } else {
                $(`#${id}`).removeClass('is-invalid');
            }
        }

        if (!valido) {
            alert('Completa todos los campos obligatorios');
            return;
        }

        // =========================
        // FORM DATA + IMAGEN
        // =========================
        const formData = new FormData();

        formData.append('fecha', egreso.fecha);
        formData.append('responsable', egreso.responsable);
        formData.append('concepto', egreso.concepto);
        formData.append('metodo', egreso.metodo);
        formData.append('valor', egreso.valor);

        formData.append('modo', modoEdicion ? 'editar' : 'crear');

        if (modoEdicion) {
            formData.append('id', $('#egresoId').val());
        }

        const fileInput = $('#flComprobante')[0];

        /*if (!modoEdicion && fileInput.files.length === 0) {
            alert('El comprobante es obligatorio');
            $('#flComprobante').addClass('is-invalid');
            return;
        } else {
            $('#flComprobante').removeClass('is-invalid');
        }*/

        if (fileInput && fileInput.files.length > 0) {
            formData.append('comprobante', fileInput.files[0]);
        }

        // =========================
        // ENVÍO
        // =========================
        try {
            const response = await fetch('/api/contabilidad/egresos', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Error al guardar el egreso');
            }
            
            // reset
            $('#formEgreso')[0].reset();
            $('.is-invalid').removeClass('is-invalid');
            modoEdicion = false;

            location.reload();

        } catch (error) {
            alert(error.message);
        }
    });

    // Limpiar modal al abrirlo para nuevo egreso
    $('#traspasoModal').on('show.bs.modal', function(e) {
        if (!modoEdicion) {
            $('#formTraspaso').trigger('reset');
            $('#traspasoId').val('');
            $('#modalTitleTraspaso').text('Nuevo Traspaso');
        }
    });

    // Limpiar modo edición al cerrar el modal
    $('#traspasoModal').on('hidden.bs.modal', function () {
        modoEdicion = false;
    });

    // Guardar traspaso
    $('#guardarTraspaso').click(async function() {
        const traspaso = {
            fecha: $('#fechaTraspaso').val(),
            origen: $('#origenTraspaso').val(),
            destino: $('#destinoTraspaso').val(),
            valor: $('#valorTraspaso').val(),
        };

        if (!traspaso.fecha) {
            alert('El fecha es requerido');
            return;
        }

        if (!traspaso.origen) {
            alert('El origen es requerido');
            return;
        }

        if (!traspaso.destino) {
            alert('El destino es requerido');
            return;
        }

        if (!traspaso.valor) {
            alert('El valor es requerido');
            return;
        }

        const url = modoEdicion ? `/api/contabilidad/traspasos/${$('#traspasoId').val()}` : '/api/contabilidad/traspasos';
        const method = modoEdicion ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: JSON.stringify(traspaso)
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Error al guardar el traspaso');
            }

            location.reload();
        } catch (error) {
            alert(error.message);
        }
    });
});

// Editar Egreso
function editarEgreso(id) {
    $.get(`/api/contabilidad/egresos/${id}`, function(egreso) {
        modoEdicion = true;
        $('#egresoId').val(egreso.id);
        $('#fechaEgreso').val(egreso.fecha);
        $('#responsableEgreso').val(egreso.responsable_id);
        $('#metodoEgreso').val(egreso.metodo);
        $('#conceptoEgreso').val(egreso.concepto);
        $('#valorEgreso').val(egreso.valor);
        $('#txtComprobante').val(egreso.comprobante);
        
        if( egreso.comprobante ){
            $('#enlace_comprobante').removeClass("d-none");
            $('#enlace_comprobante').html("<a href='/uploads/comprobantes/" + egreso.comprobante + "'  target='_blank' rel='noopener noreferrer'>" + egreso.comprobante + "</a>");
        }else{
            $('#enlace_comprobante').addClass("d-none");
        }

        $('#flComprobante').val("");
        cambiarColor();

        cargarResponsables(egreso.responsable_id);

        $('#modalTitleEgreso').text('Editar Egreso');
        egresoModal.show();
    });
}

// Eliminar usuario
function eliminarEgreso(id) {
    if (confirm('¿Está seguro de que desea eliminar este registro?')) {
        $.ajax({
            url: `/api/contabilidad/egresos/${id}`,
            method: 'DELETE',
            success: function() {
                location.reload();
            },
            error: function(xhr) {
                const mensaje = xhr.responseJSON?.error || 'Error al eliminar el egreso';
                alert(mensaje);
            }
        });
    }
}

// Editar traspaso
function editarTraspaso(id) {
    $.get(`/api/contabilidad/traspasos/${id}`, function(traspaso) {
        modoEdicion = true;
        $('#traspasoId').val(traspaso.id);
        $('#fechaTraspaso').val(traspaso.fecha);
        $('#origenTraspaso').val(traspaso.origen);
        $('#destinoTraspaso').val(traspaso.destino);
        $('#valorTraspaso').val(traspaso.valor);

        $('#modalTitleTraspaso').text('Editar traspaso');
        traspasoModal.show();
    });
}

// Eliminar traspaso
function eliminarTraspaso(id) {
    if (confirm('¿Está seguro de que desea eliminar este registro?')) {
        $.ajax({
            url: `/api/contabilidad/traspasos/${id}`,
            method: 'DELETE',
            success: function() {
                location.reload();
            },
            error: function(xhr) {
                const mensaje = xhr.responseJSON?.error || 'Error al eliminar el traspaso';
                alert(mensaje);
            }
        });
    }
}

function cargarResponsables(seleccionado = null) {
    $.ajax({
        url: '/api/contabilidad/responsables',
        method: 'GET',
        success: function(responsables) {
            $('#responsableEgreso').empty();

            responsables.forEach(c => {
                const option = $('<option>', {
                    value: c.id,
                    text: c.nombre
                });

                if (seleccionado && seleccionado == c.id) {
                    option.prop('selected', true);
                }

                if(seleccionado == null && c.id == 1){
                    option.prop('selected', true);
                }

                $('#responsableEgreso').append(option);
            });

            
        }
    });
}

// Establecer valores iniciales desde la URL o defaults
(function initFiltrosDesdeURL() {
    const p = new URLSearchParams(window.location.search);
    let desde = p.get("desde");
    let hasta = p.get("hasta");
    const q = p.get("q") || "";

    const hoy = new Date().toISOString().split("T")[0];

    document.getElementById("fechaDesde").value = desde || "2025-12-01";
    document.getElementById("fechaHasta").value = hasta || hoy;
    if (q) document.getElementById("buscarVentas").value = q;
})();

document.getElementById("filtrarVentas").addEventListener("click", function () {
    const desde = document.getElementById("fechaDesde").value;
    const hasta = document.getElementById("fechaHasta").value;
    if (!desde || !hasta) {
        mostrarAlerta("Por favor seleccione ambas fechas", "warning");
        return;
    }
    const params = new URLSearchParams({ desde, hasta });
    window.location.href = `/contabilidad?${params.toString()}`;
});

let imageShown = false; // Variable para controlar si la imagen ya fue mostrada

document.getElementById("pegarRecorte").addEventListener("paste", (event) => {
    event.preventDefault(); // Evita que la imagen se pegue dentro del div

    if (imageShown) return; // Evita mostrar más de una imagen

    const items = event.clipboardData.items;
    for (let item of items) {
        if (item.type.startsWith("image/")) {
            const file = item.getAsFile();
            
            // Asignar archivo al input file
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            document.getElementById("flComprobante").files = dataTransfer.files;

            // Mostrar la imagen
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById("previsualizarImagen");
                preview.src = e.target.result;
                preview.classList.remove("d-none")
                document.getElementById("flComprobante").style.backgroundColor = "#36eb34a1";
                // imageShown = true; // Marcar que ya se mostró una imagen
            };
            reader.readAsDataURL(file);
            break; // Salir del bucle después de la primera imagen
        }
    }
});

document.getElementById("flComprobante").addEventListener("change", function() {
    cambiarColor();
}, false)

function cambiarColor(){
    if (document.getElementById("flComprobante").files.length == 0 ) {
        document.getElementById("flComprobante").style.backgroundColor = "#ef2d2da1";
    } else if ( document.getElementById("flComprobante").files.length > 0 ){
        document.getElementById("flComprobante").style.backgroundColor = "#36eb34a1";
    }
}

$("#exportarEgresos").on("click", function () {
    const desde = $("#fechaDesde").val();
    const hasta = $("#fechaHasta").val();
    const q = $("#buscarVentas").val() || "";
    const params = new URLSearchParams();

    if (desde && hasta) {
        params.set("desde", desde);
        params.set("hasta", hasta);
    }

    window.open(`/contabilidad/export?${params.toString()}`, "_blank");
});