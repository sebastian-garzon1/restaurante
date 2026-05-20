<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contabilidad</title>

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
            font-size: 18px !important;
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
        .ventasTabla{
            font-size: 12px !important;
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
                <a href="/ventas" class="btn btn-outline-light me-2" data-bs-toggle="tooltip" title="Ventas">
                    <i class="bi bi-receipt-cutoff"></i>
                </a>
                <?php endif; ?>
                <?php if (in_array($rol, [ROLE_ADMIN])): ?>
                <a href="/contabilidad/responsables" class="btn btn-outline-light me-2" data-bs-toggle="tooltip" title="Responsables Egresos">
                    <i class="bi bi-person-lines-fill"></i>
                </a>
                <?php endif; ?>
                <a href="/logout" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <div class="row g-2 align-items-center justify-content-center">
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                            <input type="date" id="fechaDesde" class="form-control form-control-sm" placeholder="Desde" value="<?= $_GET['desde'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                            <input type="date" id="fechaHasta" class="form-control form-control-sm" placeholder="Hasta" value="<?= $_GET['hasta'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex justify-content-end gap-2">
                        <button class="btn btn-primary" id="filtrarVentas"><i class="bi bi-funnel"></i> Filtrar</button>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h4 class="text-center">Ventas</h4>
                        <div class="table-container mb-3">
                            <table class="table small table-hover">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Valor</th>
                                        <th>Metodo</th>
                                        <th>Total Vendido</th>
                                    </tr>
                                </thead>

                                <tbody id="ventasTabla">

                                <?php foreach ($ventasData as $fecha => $filas): ?>
                                    <?php $num_filas = count($filas); ?>
                                    <?php foreach ($filas as $index => $detalle): ?>
                                        <tr>
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?php echo $num_filas; ?>" class="align-middle fw-bold">
                                                    <?php echo $fecha; ?>
                                                </td>
                                            <?php endif; ?>

                                            <td>$ <?php echo number_format($detalle['valor'], 2); ?></td>
                                            <td><?php echo $detalle['metodo'] === 'tarjeta' ? "Bold" : ucfirst($detalle['metodo']); ?></td>

                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?php echo $num_filas; ?>" class="align-middle fw-bold">
                                                    <strong>$ <?php echo number_format($totales_dia[$fecha], 2); ?></strong>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>

                                </tbody>

                                <tfoot class="sticky-bottom table-dark">
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td>Efectivo</td>
                                        <td><?= number_format($ventasTotal['efectivo'], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td>Transferencia</td>
                                        <td><?= number_format($ventasTotal['transferencia'], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td>Bold</td>
                                        <td><?= number_format($ventasTotal['tarjeta'], 0) ?></td>
                                    </tr>
                                    <tr class="fw-bold">
                                        <td></td>
                                        <td></td>
                                        <td>Total</td>
                                        <td><?= number_format($ventasTotal['total'], 0) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <h4 class="text-center">Comisiones</h4>
                        <div class="table-container mb-3">
                            <table class="table small table-hover">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Valor</th>
                                        <th>Metodo</th>
                                        <th>Total Vendido</th>
                                    </tr>
                                </thead>

                                <tbody id="ventasTabla">

                                <?php foreach ($propinasData as $fecha => $filas): ?>
                                    <?php $num_filas = count($filas); ?>
                                    <?php foreach ($filas as $index => $detalle): ?>
                                        <tr>
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?php echo $num_filas; ?>" class="align-middle fw-bold">
                                                    <?php echo $fecha; ?>
                                                </td>
                                            <?php endif; ?>

                                            <td>$ <?php echo number_format($detalle['servicio'], 2); ?></td>
                                            <td><?php echo $detalle['metodo'] === 'tarjeta' ? "Bold" : ucfirst($detalle['metodo']); ?></td>

                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?php echo $num_filas; ?>" class="align-middle fw-bold">
                                                    <strong>$ <?php echo number_format($propinas_totales_dia[$fecha], 2); ?></strong>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>

                                </tbody>

                                <tfoot class="sticky-bottom table-dark">
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td>Efectivo</td>
                                        <td><?= number_format($propinasTotal['efectivo'], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td>Transferencia</td>
                                        <td><?= number_format($propinasTotal['transferencia'], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td>Bold</td>
                                        <td><?= number_format($propinasTotal['tarjeta'], 0) ?></td>
                                    </tr>
                                    <tr class="fw-bold">
                                        <td></td>
                                        <td></td>
                                        <td>Total</td>
                                        <td><?= number_format($propinasTotal['total'], 0) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <h4 class="text-center">Bold</h4>
                        <div class="table-container mb-3">
                            <table class="table small table-hover">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Valor</th>
                                        <th>Metodo</th>
                                        <th>Total Vendido</th>
                                    </tr>
                                </thead>

                                <tbody id="ventasTabla">

                                <?php foreach ($boldData as $fecha => $filas): ?>
                                    <?php $num_filas = count($filas); ?>
                                    <?php foreach ($filas as $index => $detalle): ?>
                                        <tr>
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?php echo $num_filas; ?>" class="align-middle fw-bold">
                                                    <?php echo $fecha; ?>
                                                </td>
                                            <?php endif; ?>

                                            <td>$ <?php echo number_format($detalle['pago_tarjeta'], 2); ?></td>
                                            <td><?php echo $detalle['metodo'] === 'tarjeta' ? "Bold" : ucfirst($detalle['metodo']); ?></td>

                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?php echo $num_filas; ?>" class="align-middle fw-bold">
                                                    <strong>$ <?php echo number_format($bold_totales_dia[$fecha], 2); ?></strong>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>

                                </tbody>

                                <tfoot class="sticky-bottom table-dark">
                                    <tr class="fw-bold">
                                        <td></td>
                                        <td></td>
                                        <td>Total</td>
                                        <td><?= number_format($boldTotal, 0) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <h4 class="text-center">
                            Egresos 
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#egresoModal">
                                <i class="bi bi-plus-lg me-1"></i>
                            </button>
                            <button class="btn btn-sm btn-success" id="exportarEgresos">
                                <i class="bi bi-file-earmark-excel"></i>
                            </button>
                        </h4>
                        <div class="table-container mb-3">
                            <table class="table small table-hover">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Responsable</th>
                                        <th>Concepto</th>
                                        <th>Metodo</th>
                                        <th><i class="bi bi-image"></i></th>
                                        <th>Valor</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody id="ventasTabla">

                                <?php foreach ($egresos as $e): ?>
                                    <tr>
                                        <td><?= date("d-m-Y", strtotime($e['fecha'])) ?></td>
                                        <td><?= htmlspecialchars($e['responsable']) ?></td>
                                        <td><?= htmlspecialchars($e['concepto']) ?></td>
                                        <td><?= $e['metodo'] === 'tarjeta' ? "Bold" : htmlspecialchars($e['metodo']); ?>
                                            <?= $e['modificado'] === 1 ? "🤔" : ""; ?>
                                        </td>
                                        <td>
                                        <?php 
                                            if( $e['comprobante'] && $e['comprobante'] != "" ){
                                                echo '<button class="btn btn-sm btn-outline-primary me-1 btn-comprobante" data-comprobante='. $e['comprobante'] .'>
                                                    <i class="bi bi-image"></i>
                                                </button>
                                                ';
                                            }
                                        ?>
                                        </td>
                                        <td>$<?= number_format($e['valor'], 0) ?></td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-primary me-1 btn-editar"
                                                    data-tipo="egresos"
                                                    data-action="editar"
                                                    data-id="<?= $e['id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>

                                                <button class="btn btn-sm btn-outline-danger btn-eliminar"
                                                    data-tipo="egresos"
                                                    data-action="eliminar"
                                                    data-id="<?= $e['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                </tbody>

                                <tfoot class="sticky-bottom table-dark">
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td>Efectivo</td>
                                        <td><?= number_format($egresosTotal['efectivo'], 0) ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td>Transferencia</td>
                                        <td><?= number_format($egresosTotal['transferencia'], 0) ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td>Bold</td>
                                        <td><?= number_format($egresosTotal['tarjeta'], 0) ?></td>
                                        <td></td>
                                    </tr>
                                    <tr class="fw-bold">
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td>Total</td>
                                        <td><?= number_format($egresosTotal['total'], 0) ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <h4 class="text-center">
                            Traspasos
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#traspasoModal">
                                <i class="bi bi-plus-lg me-1"></i>
                            </button>
                        </h4>
                        <div class="table-container mb-3">
                            <table class="table small table-hover">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Origen</th>
                                        <th>Destino</th>
                                        <th>Valor</th>
                                        <td></td>
                                    </tr>
                                </thead>

                                <tbody id="ventasTabla">

                                <?php foreach ($traspasos as $t): ?>
                                    <tr>
                                        <td><?= date("d-m-Y", strtotime($t['fecha'])) ?></td>
                                        <td><?= $t['origen'] === 'tarjeta' ? "Bold" : htmlspecialchars($t['origen']); ?></td>
                                        <td><?= $t['destino'] === 'tarjeta' ? "Bold" : htmlspecialchars($t['destino']); ?></td>
                                        <td>$<?= number_format($t['valor'], 0) ?>
                                            <?= $t['modificado'] === 1 ? "🤔" : ""; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-primary me-1 btn-editar"
                                                    data-tipo="traspasos"
                                                    data-action="editar"
                                                    data-id="<?= $t['id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>

                                                <button class="btn btn-sm btn-outline-danger btn-eliminar"
                                                    data-tipo="traspasos"
                                                    data-action="eliminar"
                                                    data-id="<?= $t['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                </tbody>

                                <tfoot class="sticky-bottom table-dark fw-bold">
                                    <tr>
                                        <td></td>
                                        <td>Metodo</td>
                                        <td>Ingreso</td>
                                        <td>Egreso</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td>Efectivo</td>
                                        <td>$<?= number_format($traspasoTotal["efectivo"]["ingreso"], 0) ?></td>
                                        <td>$<?= number_format($traspasoTotal["efectivo"]["egreso"], 0) ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td>Transferencia</td>
                                        <td>$<?= number_format($traspasoTotal["transferencia"]["ingreso"], 0) ?></td>
                                        <td>$<?= number_format($traspasoTotal["transferencia"]["egreso"], 0) ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td>Bold</td>
                                        <td>$<?= number_format($traspasoTotal["tarjeta"]["ingreso"], 0) ?></td>
                                        <td>$<?= number_format($traspasoTotal["tarjeta"]["egreso"], 0) ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-4 bg-success-subtle">
                        <h4 class="text-center">Total</h4>
                        <div class="table-container mb-3">
                            <table class="table small table-hover">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th></th>
                                        <th>Efectivo</th>
                                        <th>Transferencia</th>
                                        <th>Bold</th>
                                    </tr>
                                </thead>

                                <tbody id="ventasTabla">
                                    <tr>
                                        <td>Ventas <i class="bi bi-plus"></i></td>
                                        <td>$<?= number_format($ventasTotal["efectivo"], 0) ?></td>
                                        <td>$<?= number_format($ventasTotal["transferencia"], 0) ?></td>
                                        <td>$<?= number_format($ventasTotal["tarjeta"], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Egresos <i class="bi bi-dash"></i></td>
                                        <td>$<?= number_format($egresosTotal["efectivo"], 0) ?></td>
                                        <td>$<?= number_format($egresosTotal["transferencia"], 0) ?></td>
                                        <td>$<?= number_format($egresosTotal["tarjeta"], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Traspasos <i class="bi bi-plus"></i></td>
                                        <td>$<?= number_format($traspasoTotal["efectivo"]["ingreso"], 0) ?></td>
                                        <td>$<?= number_format($traspasoTotal["transferencia"]["ingreso"], 0) ?></td>
                                        <td>$<?= number_format($traspasoTotal["tarjeta"]["ingreso"], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Traspasos <i class="bi bi-dash"></i></td>
                                        <td>$<?= number_format($traspasoTotal["efectivo"]["egreso"], 0) ?></td>
                                        <td>$<?= number_format($traspasoTotal["transferencia"]["egreso"], 0) ?></td>
                                        <td>$<?= number_format($traspasoTotal["tarjeta"]["egreso"], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Propinas <i class="bi bi-plus"></i></td>
                                        <td>$<?= number_format($propinasTotal["efectivo"], 0) ?></td>
                                        <td>$<?= number_format($propinasTotal["transferencia"], 0) ?></td>
                                        <td>$<?= number_format($propinasTotal["tarjeta"], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Bold <i class="bi bi-plus"></i></td>
                                        <td>$0</td>
                                        <td>$0</td>
                                        <td>$<?= number_format($boldTotal, 0) ?></td>
                                    </tr>
                                </tbody>

                                <tfoot class="sticky-bottom table-dark fw-bold">
                                    <tr>
                                        <td>Total</td>
                                        <td>$<?= number_format($granTotal["efectivo"], 0) ?></td>
                                        <td>$<?= number_format($granTotal["transferencia"], 0) ?></td>
                                        <td>$<?= number_format($granTotal["tarjeta"], 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td colspan="2">Total</td>
                                        <td>$<?= number_format($granTotal["total"], 0) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Mostrar Comprobantes -->
    <div class="modal fade" id="popupFac" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true"> </div>

    <!-- Modal Egresos -->
    <div class="modal fade" id="egresoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-fill me-2"></i>
                        <span id="modalTitleEgreso">Nuevo Egreso</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="formEgreso">
                        <input type="hidden" id="egresoId">

                        <div class="mb-3">
                            <label for="fechaEgreso" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="fechaEgreso" required>
                        </div>

                        <div class="mb-3">
                            <label for="responsableEgreso" class="form-label">Responsable</label>
                            <select id="responsableEgreso" class="form-select" required></select>
                        </div>

                        <div class="mb-3">
                            <label for="conceptoEgreso" class="form-label">Concepto</label>
                            <input type="text" class="form-control" id="conceptoEgreso" required>
                        </div>

                        <div class="mb-3">
                            <label for="metodoEgreso" class="form-label">Metodo</label>
                            <select id="metodoEgreso" class="form-select" required>
                                <option value="efectivo" selected>Efectivo</option>
                                <option value="transferencia">Tranferencia</option>
                                <option value="tarjeta">Bold</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="valorEgreso">Valor</label>
                            <input type="number" class="form-control" id="valorEgreso" min="0" required>
                        </div>

                        <input class="form-control form-control-sm" id="flComprobante" name="flComprobante" type="file">
                        
                        <div  id="pegarRecorte" class="border boder-light text-dark text-center">
                            Pega aquí la imagen (Ctrl + V)
                            <img id="previsualizarImagen" width="100%" class="d-none">
                        </div>

                        <input type="hidden" name="txtComprobante" id="txtComprobante">
                        
                        <div id="enlace_comprobante" class="my-2 text-center"></div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarEgreso">
                        <i class="bi bi-save me-1"></i>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Traspaso -->
    <div class="modal fade" id="traspasoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-fill me-2"></i>
                        <span id="modalTitleTraspaso">Nuevo Traspaso</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="formTraspaso">
                        <input type="hidden" id="traspasoId">

                        <div class="mb-3">
                            <label for="fechaTraspaso" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="fechaTraspaso" required>
                        </div>

                        <div class="mb-3">
                            <label for="origenTraspaso" class="form-label">Origen</label>
                            <select id="origenTraspaso" class="form-select" required>
                                <option value="efectivo" selected>Efectivo</option>
                                <option value="transferencia">Tranferencia</option>
                                <option value="tarjeta">Bold</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="destinoTraspaso" class="form-label">Destino</label>
                            <select id="destinoTraspaso" class="form-select" required>
                                <option value="efectivo" selected>Efectivo</option>
                                <option value="transferencia">Tranferencia</option>
                                <option value="tarjeta">Bold</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="valorTraspaso">Valor</label>
                            <input type="number" class="form-control" id="valorTraspaso" min="0" required>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarTraspaso">
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
    <script src="/js/contabilidad.js"></script>
</body>
</html>
