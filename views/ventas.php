<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas</title>

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
        .date-filter {
            max-width: 200px;
        }
        .table-container {
            max-height: calc(85vh - 150px);
            overflow-y: auto;
        }
        /* Estilo para las alertas personalizadas */
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideIn 0.3s ease-out;
        }
        .custom-alert.success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .custom-alert.error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .custom-alert.warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">
            Sistema de Facturación
        </a>
        <div class="d-flex">
            <a href="/" class="btn btn-outline-light me-2"><i class="bi bi-house-fill"></i></a>
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
    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                        <input type="date" id="fechaDesde" class="form-control" placeholder="Desde" value="<?= $_GET['desde'] ?? '' ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                        <input type="date" id="fechaHasta" class="form-control" placeholder="Hasta" value="<?= $_GET['hasta'] ?? '' ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="buscarVentas" class="form-control" placeholder="Buscar por cliente o # factura" value="<?= $_GET['q'] ?? '' ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex justify-content-end gap-2">
                    <button class="btn btn-primary" id="filtrarVentas"><i class="bi bi-funnel"></i> Filtrar</button>
                    <button class="btn btn-success" id="exportarVentas"><i class="bi bi-file-earmark-excel"></i> Exportar</button>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Factura #</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th>Forma Pago</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>

                    <tbody id="ventasTabla">

                    <?php foreach ($ventas as $v): ?>
                        <tr>
                            <td><?= $v['id'] ?></td>
                            <td><?= date("d/m/Y H:i", strtotime($v['fecha'])) ?></td>
                            <td><?= htmlspecialchars($v['cliente_nombre']) ?></td>
                            <td><?= htmlspecialchars($v['vendedor_nombre']) ?></td>
                            <td>
                                <?= ucfirst($v['forma_pago']) ?>
                                <?php if (!empty($v['servicio']) && $v['servicio'] > 0): ?>
                                    <i class="bi bi-star-fill text-warning ms-1"
                                    title="Incluye servicio"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-end" data-total="<?= $v['total_final'] ?>" data-servicio="<?= $v['servicio'] ?>" data-tarjeta="<?= $v['pago_tarjeta'] ?>">
                                $<?= number_format($v['total_final'], 0) ?>
                            </td>

                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-success btn-editar"
                                    data-factura-id="<?= $v['id'] ?>"
                                    data-bs-toggle="tooltip" 
                                    data-bs-title="Editar factura">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            
                                <button class="btn btn-sm btn-outline-info btn-detalles"
                                    data-factura-id="<?= $v['id'] ?>"
                                    data-bs-toggle="tooltip" 
                                    data-bs-title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <button class="btn btn-sm btn-outline-primary btn-reimprimir"
                                    data-factura-id="<?= $v['id'] ?>"
                                    data-bs-toggle="tooltip" 
                                    data-bs-title="Reimprimir factura">
                                    <i class="bi bi-printer"></i>
                                </button>

                                <button class="btn btn-sm btn-outline-danger btn-eliminar"
                                    data-factura-id="<?= $v['id'] ?>"
                                    data-bs-toggle="tooltip" 
                                    data-bs-title="Eliminar factura">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

            <div class="table-container">
                <table class="table table-hover">
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">Total Efectivo:</td>
                            <td colspan="2" class="text-end" id="totalEfectivo">$0.00</td>
                        </tr>

                        <tr>
                            <td colspan="3" class="text-end">Total Transferencia:</td>
                            <td colspan="2" class="text-end" id="totalTransferencia">$0.00</td>
                        </tr>

                        <tr>
                            <td colspan="3" class="text-end">Total Tarjeta:</td>
                            <td colspan="2" class="text-end" id="totalTarjeta">$0.00</td>
                        </tr>

                        <tr class="table-success" data-bs-toggle="collapse" data-bs-target="#servicioCollapse" style="cursor:pointer">
                            <td colspan="3" class="text-end">
                                Total Servicio:
                                <i class="bi bi-chevron-down ms-2"></i>
                            </td>
                            <td colspan="2" class="text-end" id="totalServicio">
                                $0.00
                            </td>
                        </tr>

                        <tr id="servicioCollapse" class="collapse table-info">
                            <td colspan="3" class="text-end">
                                Detalles Servicio:
                            </td>
                            <td colspan="2" class="text-end">
                                Efectivo: <span id="totalEfectivoServicio">0.00</span><br>
                                Transferencia: <span id="totalTransferenciaServicio">0.00</span><br>
                                Tarjeta: <span id="totalTarjetaServicio">0.00</span>
                            </td>
                        </tr>

                        <tr class="table-success">
                            <td colspan="3" class="text-end">Extra Valor Tarjeta:</td>
                            <td colspan="2" class="text-end" id="totalTarjetaPor">$0.00</td>
                        </tr>

                        <tr class="table-secondary">
                            <td colspan="3" class="text-end">Total General:</td>
                            <td colspan="2" class="text-end" id="totalGeneral">$0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>

</div>

<!-- Modal para mostrar facturas -->
    <div class="modal fade" id="facturaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <iframe id="facturaFrame" style="width: 100%; height: 600px; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="imprimirFactura()">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalles de factura -->
    <div class="modal fade" id="detallesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles de Factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Información del Cliente</h6>
                            <div id="detallesCliente"></div>
                        </div>
                        <div class="col-md-6">
                            <h6>Información de la Factura</h6>
                            <div id="detallesFactura"></div>
                        </div>
                    </div>
                    <h6>Productos</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-end">Cantidad</th>
                                    <th>Unidad</th>
                                    <th class="text-end">Precio Unit.</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="detallesProductos"></tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="4" class="text-end">Total:</td>
                                    <td class="text-end" id="detallesTotal"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Venta -->
    <div class="modal fade" id="ventaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-fill me-2"></i>
                        <span id="modalTitle">Editar Venta</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="formVenta">
                        <input type="hidden" id="ventaId">

                        <div class="mb-3">
                            <label class="form-label">Fecha</label>
                            <input type="datetime-local" class="form-control" id="fechaVenta" required>
                        </div>

                        <div class="mb-3">
                            <label for="usuario" class="form-label">Usuario</label>
                            <select id="usuario" class="form-select" required></select>
                        </div>

                        <div class="mb-3">
                            <label for="pago" class="form-label">Tipo de Pago</label>
                            <select id="pago" class="form-select" required>
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                            </select>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>

                    <button type="button" class="btn btn-primary" id="guardarVenta">
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
    <script src="/js/ventas.js"></script>
</body>
</html>
