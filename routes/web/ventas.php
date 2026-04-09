<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Usuarios
$router->get('/ventas', function() use($pdo) {
    requireRole([ROLE_ADMIN, ROLE_CAJERO]);
    $rol = currentRole();
    $query = "
        SELECT f.*, (COALESCE(total-descuento, 0)) AS total_final, c.nombre as cliente_nombre, u.nombre as vendedor_nombre
        FROM facturas f
        JOIN clientes c ON f.cliente_id = c.id
        JOIN usuarios u ON f.user_id = u.id
        WHERE 1=1
    ";

    $params = [];

    // Filtro por fechas
    if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
        $query .= " AND f.fecha >= ? AND f.fecha <= ?";
        $params[] = $_GET['desde'] . ' 00:00:00';
        $params[] = date('Y-m-d', strtotime($_GET['hasta'] . ' +1 day')) . ' 00:00:00';
    } else {
        $query .= " AND f.fecha >= ? AND f.fecha <= ?";
        $params[] = date('Y-m-d') . ' 00:00:00';
        $params[] = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
    }

    // Búsqueda
    if (!empty($_GET['q'])) {
        $query .= " AND (c.nombre LIKE ? OR f.id LIKE ? OR f.forma_pago LIKE ?)";
        $term = "%" . $_GET['q'] . "%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $query .= " ORDER BY f.fecha DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require __DIR__ . "/../../views/ventas.php";
});


$router->get('/ventas/export', function() use($pdo) {
    isLoggedIn();

    $query = "
        SELECT f.id, f.fecha, c.nombre as cliente, u.nombre as vendedor, f.forma_pago, f.total, f.pago_tarjeta, f.servicio, f.descuento
        FROM facturas f
        JOIN clientes c ON f.cliente_id = c.id
        JOIN usuarios u ON f.user_id = u.id
        WHERE 1=1
    ";

    $params = [];

    if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
        $query .= " AND DATE(f.fecha) BETWEEN ? AND ?";
        $params[] = $_GET['desde'];
        $params[] = $_GET['hasta'];
    }

    if (!empty($_GET['q'])) {
        $query .= " AND (c.nombre LIKE ? OR f.id LIKE ? OR f.forma_pago LIKE ?)";
        $term = "%" . $_GET['q'] . "%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $query .= " ORDER BY f.fecha DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================
    // Crear Excel
    // ============================
    $sheet = new Spreadsheet();
    $ws = $sheet->getActiveSheet();
    $ws->setTitle("Ventas");

    // Logo y configuración del negocio
    $stmtC = $pdo->query("SELECT * FROM configuracion_impresion LIMIT 1");
    $config = $stmtC->fetch(PDO::FETCH_ASSOC);

    $titulo = $config['nombre_negocio'] ?? 'Reporte de Ventas';
    $info = ($config['direccion'] ?? '') . " • Tel: " . ($config['telefono'] ?? '');
    $rango = "Rango: " . ($_GET['desde'] ?? '-') . " a " . ($_GET['hasta'] ?? '-');

    // TÍTULO
    $ws->mergeCells("B1:E1");
    $ws->setCellValue("B1", $titulo);
    $ws->getStyle("B1")->getFont()->setBold(true)->setSize(16);
    $ws->getStyle("B1")->getAlignment()->setHorizontal("center");

    // SUB INFO
    $ws->mergeCells("B2:E2");
    $ws->setCellValue("B2", $info);
    $ws->getStyle("B2")->getAlignment()->setHorizontal("center");

    // RANGO
    $ws->mergeCells("B3:E3");
    $ws->setCellValue("B3", $rango);
    $ws->getStyle("B3")->getAlignment()->setHorizontal("center");

    // LOGO
    if (!empty($config["logo_data"])) {
        $tmpLogo = __DIR__ . "/tmp_logo.png";
        file_put_contents($tmpLogo, $config["logo_data"]);

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath($tmpLogo);
        $drawing->setHeight(60);
        $drawing->setCoordinates('A1');
        $drawing->setWorksheet($ws);
    }

    // Fila vacía
    $ws->setCellValue("A5", "");

    // Encabezados
    $header = ["Factura #", "Fecha", "Cliente", "Forma de Pago", "SubTotal", "Tarjeta", "Propina", "Descuento", "Total"];
    $ws->fromArray($header, null, "A6");

    $ws->getStyle("A6:I6")->getFont()->setBold(true);
    $ws->getStyle("A6:I6")->getFill()->setFillType(Fill::FILL_SOLID)
                                        ->getStartColor()->setRGB("E9ECEF");

    // Datos
    $fila = 7;
    foreach ($rows as $r) {
        $ws->setCellValue("A{$fila}", $r["id"]);
        $ws->setCellValue("B{$fila}", $r["fecha"]);
        $ws->setCellValue("C{$fila}", $r["cliente"]);
        $ws->setCellValue("D{$fila}", ucfirst($r["forma_pago"]));
        $ws->setCellValue("E{$fila}", $r["total"]);
        $ws->setCellValue("F{$fila}", $r["pago_tarjeta"]);
        $ws->setCellValue("G{$fila}", $r["servicio"]);
        $ws->setCellValue("H{$fila}", $r["descuento"]);

        $total = $r["total"] + $r["pago_tarjeta"] + $r["servicio"] - $r["descuento"];

        $ws->setCellValue("I{$fila}", $total);
        $fila++;
    }

    // Descargar
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=ventas.xlsx");

    $writer = new Xlsx($sheet);
    $writer->save("php://output");
    exit;
});