// Variables globales
let responsableModal;
let modoEdicion = false;

$(document).ready(function() {
    responsableModal = new bootstrap.Modal(document.getElementById('responsableModal'));

    // Manejar clics en los botones de acción
    $(document).on('click', '[data-action]', function() {
        const id = $(this).data('id');
        const action = $(this).data('action');

        if (action === 'editar') {
            editarResponsable(id);
        } else if (action === 'eliminar') {
            eliminarResponsable(id);
        }
    });

    // Limpiar modal al abrirlo para nuevo responsable
    $('#responsableModal').on('show.bs.modal', function(e) {
        if (!modoEdicion) {
            $('#formResponsable').trigger('reset');

            $('#responsableId').val('');
            $('#modalTitle').text('Nuevo Responsable');
        }
    });

    // Limpiar modo edición al cerrar el modal
    $('#responsableModal').on('hidden.bs.modal', function () {
        modoEdicion = false;
    });

    // Guardar responsable
    $('#guardarResponsable').click(async function() {
        const responsable = {
            nombre: $('#nombre').val(),
        };

        if (!responsable.nombre) {
            alert('El nombre es requerido');
            return;
        }

        const url = modoEdicion ? `/api/contabilidad/responsables/${$('#responsableId').val()}` : '/api/contabilidad/responsables';
        const method = modoEdicion ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: JSON.stringify(responsable)
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Error al guardar el responsable');
            }

            location.reload();
        } catch (error) {
            alert(error.message);
        }
    });
});

// Editar responsable
function editarResponsable(id) {
    $.get(`/api/contabilidad/responsables/${id}`, function(responsable) {
        modoEdicion = true;
        $('#responsableId').val(responsable.id);
        $('#nombre').val(responsable.nombre);

        $('#modalTitle').text('Editar responsable');
        responsableModal.show();
    });
}

// Eliminar responsable
function eliminarResponsable(id) {
    if (confirm('¿Está seguro de que desea eliminar este responsable?')) {
        $.ajax({
            url: `/api/contabilidad/responsables/${id}`,
            method: 'DELETE',
            success: function() {
                location.reload();
            },
            error: function(xhr) {
                const mensaje = xhr.responseJSON?.error || 'Error al eliminar el responsable';
                alert(mensaje);
            }
        });
    }
}