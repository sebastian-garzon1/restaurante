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
            max-height: calc(100vh - 150px);
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
        <a class="navbar-brand" href="/">Sistema de Facturación</a>

        <div class="d-flex">
            <a href="/" class="btn btn-outline-light me-2"><i class="bi bi-house-fill"></i></a>
            <?php if (in_array($rol, [ROLE_ADMIN, ROLE_MESERO, ROLE_COCINA])): ?>
            <a href="/productos" class="btn btn-outline-light me-2"><i class="bi bi-box"></i></a>
            <?php endif; ?>
            <?php if (in_array($rol, [ROLE_ADMIN])): ?>
            <a href="/ventas" class="btn btn-outline-light me-2" data-bs-toggle="tooltip" title="Historial de Ventas">
                <i class="bi bi-receipt"></i>
            </a>
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
                        <input type="text" id="buscarVentas" class="form-control" placeholder="Buscar" value="<?= $_GET['q'] ?? '' ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex justify-content-end gap-2">
                    <button class="btn btn-primary" id="filtrarVentas"><i class="bi bi-funnel"></i> Filtrar</button>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Total</th>
                        </tr>
                    </thead>

                    <tbody id="ventasTabla">

                    <?php foreach ($ventas as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['producto']) ?></td>
                            <td><?= number_format($v['cantidad'], 0) ?></td>
                            <td>$<?= number_format($v['total'], 0) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/js/ventas_productos.js"></script>

</body>
</html>
