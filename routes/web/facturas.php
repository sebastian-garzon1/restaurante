<?php

// Facturas
$router->get('/facturas', function() {
    isLoggedIn();
    require __DIR__ . "/views/facturas.php";
});


// GET /facturas/{id}/imprimir - Vista HTML
$router->get('/facturas/{id}/imprimir', function($id) use($pdo) {
    isLoggedIn();

    $factura_id = $id;

    try {
        // Obtener configuración de impresión
        $stmt = $pdo->query("SELECT * FROM configuracion_impresion LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            http_response_code(400);
            echo json_encode(['error' => 'No hay configuración de impresión']);
            return;
        }

        // Convertir imágenes BLOB a base64
        if (!empty($config['logo_data'])) {
            $config['logo_src'] = "data:image/{$config['logo_tipo']};base64," . base64_encode($config['logo_data']);
        }
        if (!empty($config['qr_data'])) {
            $config['qr_src'] = "data:image/{$config['qr_tipo']};base64," . base64_encode($config['qr_data']);
        }

        // Obtener factura
        $stmt = $pdo->prepare("
            SELECT f.*, c.nombre AS cliente_nombre, c.direccion, c.telefono
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            WHERE f.id = ?
        ");
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            http_response_code(404);
            echo json_encode(['error' => 'Factura no encontrada']);
            return;
        }

        // Obtener detalles
        $stmt = $pdo->prepare("
            SELECT d.*, p.nombre AS producto_nombre
            FROM detalle_factura d
            JOIN productos p ON d.producto_id = p.id
            WHERE d.factura_id = ?
        ");
        $stmt->execute([$factura_id]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        include __DIR__ . '/../../views/factura.php';

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al cargar factura']);
    }
});