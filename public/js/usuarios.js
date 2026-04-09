// Variables globales
let usuarioModal;
let modoEdicion = false;

$(document).ready(function() {
    usuarioModal = new bootstrap.Modal(document.getElementById('usuarioModal'));

    // Búsqueda de usuarios
    $('#buscarUsuario').on('keyup', function() {
        let valor = $(this).val().toLowerCase();
        $("#usuariosTabla tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(valor) > -1);
        });
    });

    // Manejar clics en los botones de acción
    $(document).on('click', '[data-action]', function() {
        const id = $(this).data('usuario-id');
        const action = $(this).data('action');

        if (action === 'editar') {
            editarUsuario(id);
        } else if (action === 'eliminar') {
            eliminarUsuario(id);
        }
    });

    // Limpiar modal al abrirlo para nuevo usuario
    $('#usuarioModal').on('show.bs.modal', function(e) {
        if (!modoEdicion) {
            $('#formUsuario').trigger('reset');

            $('#passwordGroup').removeClass('d-none');
            $('#confirmPasswordGroup').addClass('d-none');
            $('#btnCambiarPwd').addClass('d-none');

            $('#usuarioId').val('');
            cargarCargos();
            $('#modalTitle').text('Nuevo Usuario');
        }
    });

    // Limpiar modo edición al cerrar el modal
    $('#usuarioModal').on('hidden.bs.modal', function () {
        modoEdicion = false;
    });

    // Guardar usuario
    $('#guardarUsuario').click(async function() {
        const usuario = {
            nombre: $('#nombre').val(),
            username: $('#username').val(),
            password: $('#password').val(),
            correo: $('#correo').val(),
            rol: $('#rol').val(),
        };

        if (!usuario.nombre) {
            alert('El nombre es requerido');
            return;
        }

        if (!usuario.rol) {
            alert('El rol es requerido');
            return;
        }

        if (!usuario.username) {
            alert('El username es requerido');
            return;
        }

        if (!usuario.correo) {
            alert('El correo es requerido');
            return;
        }

        const password = $('#password').val();
        const confirmPassword = $('#confirmPassword').val();

        // Si el usuario quiere cambiar contraseña
        if (!$('#confirmPasswordGroup').hasClass('d-none')) {
            if (!password) {
                alert("La contraseña no puede estar vacía.");
                return;
            }

            if (password !== confirmPassword) {
                alert("Las contraseñas no coinciden.");
                return;
            }

            usuario.password = password;
        }

        const url = modoEdicion ? `/api/usuarios/${$('#usuarioId').val()}` : '/api/usuarios';
        const method = modoEdicion ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: JSON.stringify(usuario)
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Error al guardar el usuario');
            }

            location.reload();
        } catch (error) {
            alert(error.message);
        }
    });
});

// Editar usuario
function editarUsuario(id) {
    $.get(`/api/usuarios/${id}`, function(usuario) {
        modoEdicion = true;
        $('#usuarioId').val(usuario.id);
        $('#nombre').val(usuario.nombre);
        $('#correo').val(usuario.correo);
        $('#username').val(usuario.usuario);

        cargarCargos(usuario.rol_id);

        $('#passwordGroup').addClass('d-none');
        $('#confirmPasswordGroup').addClass('d-none');
        $('#btnCambiarPwd').removeClass('d-none');
        $('#password').val('');
        $('#confirmPassword').val('');

        $('#modalTitle').text('Editar Usuario');
        usuarioModal.show();
    });
}

// Eliminar usuario
function eliminarUsuario(id) {
    if (confirm('¿Está seguro de que desea eliminar este usuario?')) {
        $.ajax({
            url: `/api/usuarios/${id}`,
            method: 'DELETE',
            success: function() {
                location.reload();
            },
            error: function(xhr) {
                const mensaje = xhr.responseJSON?.error || 'Error al eliminar el usuario';
                alert(mensaje);
            }
        });
    }
}

function cargarCargos(seleccionado = null) {
    $.ajax({
        url: '/api/roles',
        method: 'GET',
        success: function(roles) {
            $('#rol').empty();

            roles.forEach(c => {
                const option = $('<option>', {
                    value: c.id,
                    text: c.nombre
                });

                if (seleccionado && seleccionado == c.id) {
                    option.prop('selected', true);
                }

                $('#rol').append(option);
            });
        }
    });
}


$(document).on('click', '#btnCambiarPwd', function() {
    $('#passwordGroup').removeClass('d-none');
    $('#confirmPasswordGroup').removeClass('d-none');
});
