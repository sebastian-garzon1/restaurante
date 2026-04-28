<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.jpeg" type="image/jpeg">
    <style>
        body{background-color:#f8f9fa}
        .navbar{background-color:#2c3e50;box-shadow:0 2px 4px rgba(0,0,0,.1)}
        .navbar-brand{color:#fff!important;font-weight:bold}
        .producto{font-size:1.2rem;font-weight:700}
        .cantidad-badge{font-size:1rem}
        .mesa-heading{background:#f1f3f5;border-radius:.5rem;padding:.4rem .75rem;margin:.25rem 0;font-weight:600}
        .card-cocina{border:none;box-shadow:0 0.5rem 1rem rgba(0,0,0,.05)}
        .item-row{border:1px solid #eef1f5;border-radius:.5rem;padding:.6rem .75rem}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/">Sistema de Facturación</a>
            <div class="d-flex">
                <?php if (in_array($rol, [ROLE_ADMIN, ROLE_CAJERO, ROLE_MESERO])): ?>
                <a href="/mesas" class="btn btn-outline-light me-2"><i class="bi bi-grid"></i></a>
                <?php endif; ?>
                <a href="/" class="btn btn-outline-light me-2"><i class="bi bi-receipt"></i></a>
                <a href="/logout" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h4 class="mb-3">
            <i class="bi bi-egg-fried me-2"></i>Cola de Cocina
        </h4>

        <div class="btn-group" role="group">
            <a href="/cocina" class="btn btn-outline-primary" data-tipo="">
                Todo <i class="bi bi-grid-fill"></i>
            </a>
            <a href="/cocina?tipo=comida" class="btn btn-outline-primary" data-tipo="comida">
                Alimentos <i class="bi bi-fork-knife"></i>
            </a>
            <a href="/cocina?tipo=bebida" class="btn btn-outline-primary" data-tipo="bebida">
                Bebidas <i class="bi bi-cup-straw"></i>
            </a>
        </div>
        
        <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="tabListos" role="tabpanel" aria-labelledby="tabListos-tab">
                <div class="card">
                    <div class="card-header">Pendientes</div>
                    <div class="card-body">
                        <div id="listaListos" class="row"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/cocina.js"></script>
</body>
</html>