<?php
$router->get('/api/ventas/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("
            SELECT f.*, 'cliente' AS cliente_nombre, u.nombre AS vendedor_nombre
            FROM facturas f
            LEFT JOIN usuarios u ON f.user_id = u.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);

        $venta = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$venta) {
            http_response_code(408);
            echo json_encode(['error' => 'Venta no encontrada']);
            return;
        }

        echo json_encode($venta);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener venta', 'detalle' => $e->getMessage()]);
    }
});

// ===============================================================
// PUT /api/ventas/{id}
// ===============================================================
$router->put('/api/ventas/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents("php://input"), true);
        $fecha = $input['fecha'] ?? null;
        $usuario_id = $input['usuario_id'] ?? null;
        $forma_pago = $input['forma_pago'] ?? null;

        $stmt = $pdo->prepare("
            SELECT f.*
            FROM facturas f
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);

        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venta) {
            http_response_code(408);
            echo json_encode(['error' => 'Venta no encontrada']);
            return;
        }

        $descuento = $venta['descuento'] ?? 0;
        $total_original = $venta['total'] ?? 0;

        $totalDes = $total_original - $descuento;

        $total_tarjeta = 0;
        if($forma_pago === 'tarjeta') {
            // Aplicar 5% de recargo si la forma de pago es tarjeta
            $total_tarjeta = round(($totalDes) * 0.05, 2);
        } else {
            $total_tarjeta = 0;
        }

        if (!$id || !$fecha || !$usuario_id || !$forma_pago || !$descuento) {
            http_response_code(400);
            echo json_encode([
                "error" => "ID de venta, fecha, cliente, usuario, descuento y forma de pago son requeridos"
            ]);
            return;
        }

        // Validación básica de fecha
        if (!strtotime($fecha)) {
            http_response_code(400);
            echo json_encode([
                "error" => "Formato de fecha inválido"
            ]);
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE facturas
            SET fecha = ?, user_id = ?, forma_pago = ?, pago_tarjeta = ?, descuento = ?
            WHERE id = ?
        ");

        $stmt->execute([$fecha, $usuario_id, $forma_pago, $total_tarjeta, $descuento, $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                "error" => "Factura no encontrada o sin cambios"
            ]);
            return;
        }

        echo json_encode([
            "success" => true,
            "id" => $id,
            "fecha" => $fecha,
            "usuario_id" => $usuario_id,
            "forma_pago" => $forma_pago,
            "descuento" => $descuento
        ]);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            "error" => "Error al actualizar la fecha de la factura"
        ]);
    }
});