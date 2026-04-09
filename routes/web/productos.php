<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Productos
$router->get('/productos', function() use($pdo) {
    requireRole([ROLE_ADMIN, ROLE_MESERO, ROLE_COCINA]);
    $rol = currentRole();
    $stmt = $pdo->query("SELECT * FROM productos ORDER BY codigo DESC");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    require __DIR__ . "/../../views/productos.php";
});

$router->get('/productos/ventas', function() use($pdo) {
    requireRole([ROLE_ADMIN]);
    $rol = currentRole();
    $query = "
        SELECT p.nombre AS producto, SUM(d.cantidad) AS cantidad, SUM(d.subtotal) AS total
        FROM detalle_factura d 
        JOIN facturas f ON f.id = d.factura_id 
        LEFT JOIN productos p ON p.id = d.producto_id 
        WHERE 1=1
    ";

    $params = [];

    // Filtro por fechas
    if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
        $query .= "  AND f.fecha >= ? AND f.fecha <= ?";
        $params[] = $_GET['desde'] . ' 00:00:00';
        $params[] = date('Y-m-d', strtotime($_GET['hasta'] . ' +1 day')) . ' 00:00:00';
    }

    // Búsqueda
    if (!empty($_GET['q'])) {
        $query .= " AND (c.nombre LIKE ? OR f.id LIKE ? OR f.forma_pago LIKE ?)";
        $term = "%" . $_GET['q'] . "%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $query .= " GROUP BY p.nombre 
    ORDER BY cantidad DESC;";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require __DIR__ . "/../../views/productos_ventas.php";
});

$router->get('/productos/plantilla', function() {

    isLoggedIn();

    $spreadsheet = new Spreadsheet();

    // ==============
    // Hoja 1: Instrucciones
    // ==============
    $ws = $spreadsheet->getActiveSheet();
    $ws->setTitle('Instrucciones');

    $ws->setCellValue('A1', 'PLANTILLA DE PRODUCTOS');
    $ws->getStyle('A1')->getFont()->setBold(true)->setSize(16);

    $ws->setCellValue('A2', '1) No cambie los encabezados de la hoja "Productos".');
    $ws->setCellValue('A3', '2) Columnas obligatorias: codigo, nombre. Los precios pueden ser 0.');
    $ws->setCellValue('A4', '3) Use punto como decimal (ej: 1234.56).');
    $ws->setCellValue('A5', '4) El código debe ser único; si existe se actualizará.');

    $ws->getColumnDimension('A')->setWidth(80);

    // ==============
    // Hoja 2: Productos
    // ==============
    $sheetProd = $spreadsheet->createSheet();
    $sheetProd->setTitle('Productos');

    $headers = ['codigo','nombre','precio_unidad'];
    $sheetProd->fromArray($headers, NULL, 'A1');

    // Ejemplos
    $sheetProd->fromArray(
        [
            ['P001', 'Asado argentino', 65000],
            ['P002', 'CocaCola 400ml', 5000],
            ['P003', 'Bife de chorizo', 55000],
        ],
        NULL,
        'A2'
    );

    // Congelar encabezados
    $sheetProd->freezePane('A2');

    // Forzar descarga:
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="plantilla_productos.xlsx"');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
});

$router->post('/productos/importar', function() use($pdo) {

    isLoggedIn();
    header('Content-Type: application/json');

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Archivo requerido']);
        return;
    }

    $fileTmp = $_FILES['archivo']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($fileTmp);

        // Buscar hoja Productos
        $sheet = $spreadsheet->getSheetByName('Productos') 
                ?: $spreadsheet->getSheet(0);

        $rows = [];
        $highestRow = $sheet->getHighestRow();

        for ($i = 2; $i <= $highestRow; $i++) {

            $codigo = trim((string)$sheet->getCell("A$i")->getValue());
            $nombre = trim((string)$sheet->getCell("B$i")->getValue());
            $precio_unidad = (float)$sheet->getCell("C$i")->getValue();

            if (!$codigo || !$nombre) continue;

            $rows[] = [
                "codigo" => $codigo,
                "nombre" => $nombre,
                "precio_unidad" => $precio_unidad,
            ];
        }

        if (empty($rows)) {
            echo json_encode(['error' => 'No hay registros válidos']);
            return;
        }

        // Guardado con ON DUPLICATE KEY
        $pdo->beginTransaction();

        $sql = "
            INSERT INTO productos (codigo, nombre, precio_unidad)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre),
                precio_unidad = VALUES(precio_unidad)
        ";

        $stmt = $pdo->prepare($sql);

        foreach ($rows as $p) {
            $stmt->execute([
                $p['codigo'], 
                $p['nombre'],
                $p['precio_unidad'],
            ]);
        }

        $pdo->commit();

        echo json_encode(["inserted" => count($rows)]);
    } 
    catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Error al importar productos']);
    }
});