<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.jpeg" type="image/jpeg">

    <style>
        body { background-color: #f8f9fa; }
        .navbar { background-color: #2c3e50; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .navbar-brand { color: white !important; font-weight: bold; }
        .card { border: none; box-shadow: 0 .5rem 1rem rgba(0,0,0,.05); margin-bottom:1.5rem; }
        .card-header { background:#fff; border-bottom:2px solid #f8f9fa; font-weight:600; }
        .table th { border-top:none; }
        .action-buttons .btn { padding:.25rem .5rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">Palermo Che</a>
        <div class="d-flex">
            <a href="/" class="btn btn-outline-light me-2">
                <i class="bi bi-house-fill"></i>
            </a>
            <?php if (in_array($rol, [ROLE_ADMIN])): ?>
            <a href="/configuracion" class="btn btn-outline-light me-2">
                <i class="bi bi-gear"></i>
            </a>
            <?php endif; ?>
            <a href="/logout" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="bi bi-people-fill me-2"></i> Gestión de Usuarios</div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#usuarioModal">
                <i class="bi bi-plus-lg me-1"></i> Nuevo Usuario
            </button>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="usuariosTabla">

                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><i class="bi bi-person-square"></i></td>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['usuario']) ?></td>
                            <td><?= htmlspecialchars($u['correo'] ?? 'No especificado') ?></td>
                            <td><?= htmlspecialchars($u['rol_nombre'] ?? 'No especificado') ?></td>
                            <td><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></td>

                            <td class="text-center">
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-primary me-1 btn-editar"
                                        data-action="editar"
                                        data-usuario-id="<?= $u['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <button class="btn btn-sm btn-outline-danger btn-eliminar"
                                        data-action="eliminar"
                                        data-usuario-id="<?= $u['id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Usuario -->
<div class="modal fade" id="usuarioModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-fill me-2"></i>
                    <span id="modalTitle">Nuevo Usuario</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formUsuario">
                    <input type="hidden" id="usuarioId">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" placeholder="Nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rol" class="form-label">Rol</label>
                        <select id="rol" class="form-select" required></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" class="form-control" id="correo" placeholder="Correo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" placeholder="Username" required>
                    </div>
                    
                    <button type="button" class="btn btn-warning btn-sm d-none" id="btnCambiarPwd">
                        <i class="bi bi-key"></i>
                        Cambiar contraseña
                    </button>
                    
                    <div class="mb-3" id="passwordGroup">
                        <label class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" placeholder="Contraseña">
                    </div>

                    <div class="mb-3 d-none" id="confirmPasswordGroup">
                        <label class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" id="confirmPassword" placeholder="Confirmar contraseña">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="guardarUsuario">
                    <i class="bi bi-save me-1"></i>
                    Guardar
                </button>
                
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/usuarios.js"></script>

</body>
</html>
