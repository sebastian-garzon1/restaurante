<?php
// Asegurar sesión y autenticación si es necesario
// session_start();
// if(!isset($_SESSION['user'])) { header("Location: login.php"); exit; }

// $mesas debe venir desde tu controlador PHP:
// Ejemplo: $mesas = obtenerMesasDesdeBD();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.jpeg" type="image/jpeg">
    <style>
        body{background-color:#f8f9fa}
        .navbar{background-color:#2c3e50;box-shadow:0 2px 4px rgba(0,0,0,.1)}
        .navbar-brand{color:#fff!important;font-weight:bold}
        .mesa-card{border:none;box-shadow:0 .5rem 1rem rgba(0,0,0,.05);transition:transform .1s}
        .mesa-card:hover{transform:translateY(-2px)}
        .estado-badge{position:absolute;top:.75rem;right:.75rem}
    </style>
 </head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/">Sistema de Facturación</a>
            <div class="d-flex">
                <a href="/" class="btn btn-outline-light me-2" data-bs-toggle="tooltip" title="Facturar"><i class="bi bi-receipt"></i></a>
                <?php if (in_array($rol, [ROLE_ADMIN, ROLE_CAJERO, ROLE_COCINA])): ?>
                <a href="/cocina" class="btn btn-outline-light me-2" data-bs-toggle="tooltip" title="Cocina"><i class="bi bi-egg-fried"></i></a>
                <?php endif; ?>
                <?php if (in_array($rol, [ROLE_ADMIN, ROLE_MESERO, ROLE_COCINA])): ?>
                <a href="/productos" class="btn btn-outline-light me-2"><i class="bi bi-box"></i></a>
                <?php endif; ?>
                <?php if (in_array($rol, [ROLE_ADMIN])): ?>
                <a href="/configuracion" class="btn btn-outline-light me-2"><i class="bi bi-gear"></i></a>
                <?php endif; ?>
                <a href="/logout" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="bi bi-grid me-2"></i>Mesas</h4>
            <button id="btnNuevaMesa" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva mesa</button>
        </div>

        <div class="row" id="gridMesas">
            <?php foreach ($mesas as $m): ?>
                <div class="col-6 col-md-4 col-lg-3 mb-3">
                    <div class="card mesa-card position-relative" data-mesa-id="<?= $m['id'] ?>">
                        <div class="card-body">
                            <?php
                                $estado = $m['estado'];
                                $color = $estado === 'libre' ? 'success' : ($estado === 'ocupada' ? 'warning' : 'secondary');
                            ?>
                            <span class="badge rounded-pill bg-<?= $color ?> estado-badge">
                                <?= $estado ?>
                            </span>

                            <h5 class="card-title mb-1">Mesa <?= $m['numero'] ?></h5>
                            <p class="text-muted small mb-3"><?= $m['descripcion'] ?? '' ?></p>

                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-secondary btn-sm btnLiberarMesa">
                                    <i class="bi bi-unlock me-1"></i> Liberar mesa
                                </button>
                                <button class="btn btn-outline-primary btn-sm btnAbrirPedido">
                                    <i class="bi bi-door-open me-1"></i> Abrir / Continuar pedido
                                </button>
                                <button class="btn btn-outline-secondary btn-sm btnVerPedido">
                                    <i class="bi bi-list-ul me-1"></i> Ver pedido
                                </button>
                                <?php if (in_array($rol, [ROLE_ADMIN])): ?>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary btn-sm btnEditarMesa flex-fill">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm btnEliminarMesa flex-fill">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Offcanvas Pedido -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="canvasPedido" style="width: 420px">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Pedido mesa <span id="pedidoMesa"></span></h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" id="btnMoverMesaHeader" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="Mover a otra mesa">
                    <i class="bi bi-arrow-left-right"></i>
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
        </div>

        <div class="offcanvas-body d-flex flex-column">
            <div class="mb-2">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="buscarProductoMesa" class="form-control" placeholder="Buscar producto...">
                </div>
                <div id="resultadosProductoMesa" class="list-group"></div>
            </div>

            <div class="d-flex justify-content-end mb-2">
                <button id="btnLiberarMesaHeader" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-unlock"></i> Liberar mesa
                </button>
            </div>

            <div class="table-responsive flex-grow-1">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Cant</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Subt</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tbodyItems"></tbody>
                </table>
            </div>

            <div class="mt-auto">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Total:</strong>
                    <span id="totalPedido" class="fw-bold">$0</span>
                </div>

                <div class="d-grid gap-2">
                    <button id="btnMoverMesa" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left-right me-1"></i>Mover a mesa
                    </button>
                    <button id="btnEnviarCocina" class="btn btn-warning">
                        <i class="bi bi-send me-1"></i>Enviar a cocina
                    </button>
                    <button id="btnFacturarPedido" class="btn btn-success">
                        <i class="bi bi-cash-coin me-1"></i>Facturar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Mesa -->
    <div class="modal fade" id="mesaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-fill me-2"></i>
                        <span id="modalTitle">Nueva Mesa</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formMesa">
                        <input type="hidden" id="mesaId">
                        <div class="mb-3">
                            <label class="form-label">Mesa</label>
                            <input type="text" class="form-control" id="nombre" placeholder="Nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripcion</label>
                            <input type="text" class="form-control" id="descripcion" placeholder="Descripcion" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarMesa">
                        <i class="bi bi-save me-1"></i>
                        Guardar
                    </button>
                    
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/js/mesas.js"></script>
</body>
</html>
