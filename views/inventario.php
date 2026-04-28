<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.jpeg" type="image/jpeg">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #2c3e50;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #f8f9fa;
            font-weight: 600;
        }
        .search-box {
            max-width: 300px;
        }
        .table th {
            border-top: none;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/">Sistema de Facturación</a>
            <div class="d-flex">
                <a href="/" class="btn btn-outline-light me-2">
                    <i class="bi bi-house-fill"></i>
                </a>
                <?php if (in_array($rol, [ROLE_ADMIN, ROLE_MESERO, ROLE_COCINA])): ?>
                <a href="/productos" class="btn btn-outline-light me-2" data-bs-toggle="tooltip" title="Gestionar Productos">
                    <i class="bi bi-box"></i>
                </a>
                <?php endif; ?>
                <?php if (in_array($rol, [ROLE_ADMIN])): ?>
                <a href="/configuracion" class="btn btn-outline-light me-2">
                    <i class="bi bi-gear"></i>
                </a>
                <?php endif ?>
                <a href="/logout" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-egg-fried me-2"></i>
                    Gestión de Inventario
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoInsumoModal">
                    <i class="bi bi-plus-lg me-1"></i>
                    Nuevo Insumo
                </button>
            </div>

            <div class="card-body">
                <div class="row mb-4">
                    <div class="col">
                        <div class="input-group search-box ms-auto">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control" id="buscarInsumo" placeholder="Buscar insumo...">
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Stock</th>
                                <th>Stock Minimo</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>

                        <tbody id="insumosTabla">
                            <?php foreach ($insumos as $insumo): 
                                $fondo_estado = ($insumo["stock"] < $insumo['stock_minimo']) ? "table-danger" : "table-success";
                            ?>
                                <tr class="<?= $fondo_estado ?>">
                                    <td><i class="bi bi-person-square"></i></td>
                                    <td><?= htmlspecialchars($insumo['nombre']) ?></td>
                                    <td><?= $insumo['descripcion'] ? htmlspecialchars($insumo['descripcion']) : 'No especificada' ?></td>
                                    <td><?= number_format($insumo['stock'], 0) ?></td>
                                    <td><?= number_format($insumo['stock_minimo'], 0) ?></td>
                                    <td class="text-center"><?= (($insumo['activo'] ?? 0) == 1) ? '✅' : '⛔' ?></td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary me-1"
                                                    data-id="<?= $insumo['id'] ?>"
                                                    data-action="editar"
                                                    title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <button class="btn btn-sm btn-outline-danger"
                                                    data-id="<?= $insumo['id'] ?>"
                                                    data-action="eliminar"
                                                    title="Eliminar">
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

    <!-- Modal Insumo -->
    <div class="modal fade" id="nuevoInsumoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-egg-fried me-2"></i>
                        <span id="modalTitle">Nuevo Insumo</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="formInsumo">
                        <input type="hidden" id="insumoId">

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="nombre" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" cols="30"></textarea>
                        </div>

                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label class="form-label">Stock</label>
                                <input type="number" class="form-control" id="stock" value="0" required>
                            </div>

                            <div class="mb-3 col-md-6">
                                <label class="form-label">Stock Mínimo</label>
                                <input type="number" class="form-control" id="stock_minimo" value="0" required>
                            </div>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="chkEstado" checked="">
                            <label class="form-check-label" for="chkEstado">
                                Estado
                            </label>
                        </div>                        
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>

                    <button type="button" class="btn btn-primary" id="guardarInsumo">
                        <i class="bi bi-save me-1"></i>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/inventario.js"></script>

</body>
</html>
