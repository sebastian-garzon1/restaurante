<?php

// POST /api/facturas - Crear factura
$router->post('/api/facturas', function() use($pdo) {
    isLoggedIn();

    $data = json_decode(file_get_contents("php://input"), true);

    $pago_tarjeta = 0;
    $pago_servicio = 0;
    $total = $data['total'] ?? 0;
    $descuento = $data['descuento'] ?? 0;
    $forma_pago = $data['forma_pago'] ?? '';
    $productos = $data['productos'] ?? [];
    $incluir_servicio = $data['incluir_servicio'] ?? false;
    $servicio = $data['servicio'] ?? 0;

    $totalDes = $total - $descuento;

    if($forma_pago === 'tarjeta'){
        $pago_tarjeta = ($totalDes) * 0.05; // 5% de recargo por pago con tarjeta
    }

    if( $incluir_servicio ){
        if( $servicio > 0 ){
            $pago_servicio = $servicio;
        } else {
            $pago_servicio = $totalDes * 0.10; // 10% de recargo por servicio
        }
    }

    if ( count($productos) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos incompletos']);
        return;
    }

    try {
        // Iniciar transacción
        $pdo->beginTransaction();

        $fecha = date('Y-m-d H:i:s');

        // Insertar factura
        $stmt = $pdo->prepare("
            INSERT INTO facturas (user_id, total, forma_pago, pago_tarjeta, servicio, descuento, fecha)
            VALUES (:user_id, :total, :forma_pago, :pago_tarjeta, :servicio, :descuento, :fecha)
        ");

        $stmt->execute([
            ':user_id' => $_SESSION['user']['id'],
            ':total' => $total,
            ':forma_pago' => $forma_pago,
            ':pago_tarjeta' => $pago_tarjeta,
            ':servicio' => $pago_servicio,
            ':descuento' => $descuento,
            ':fecha' => $fecha
        ]);

        $factura_id = $pdo->lastInsertId();

        // Insertar detalles
        $stmtDetalle = $pdo->prepare("
            INSERT INTO detalle_factura 
            (factura_id, producto_id, cantidad, precio_unitario, unidad_medida, subtotal)
            VALUES (:factura_id, :producto_id, :cantidad, :precio, :unidad, :subtotal)
        ");

        foreach ($productos as $p) {
            $stmtDetalle->execute([
                ':factura_id' => $factura_id,
                ':producto_id' => $p['producto_id'],
                ':cantidad' => $p['cantidad'],
                ':precio' => $p['precio'],
                ':unidad' => $p['unidad'],
                ':subtotal' => $p['subtotal']
            ]);
        }
        // Confirmar operación
        $pdo->commit();

        echo json_encode(['id' => $factura_id]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear factura', 'detalle' => $e->getMessage()]);
    }
});

// GET /api/facturas/{id}/detalles - API JSON
$router->get('/api/facturas/{id}/detalles', function($id) use($pdo) {
    isLoggedIn();
    try {
        // Factura
        $stmt = $pdo->prepare("
            SELECT f.*, 'Cliente' AS cliente_nombre, c.direccion, c.telefono
            FROM facturas f
            WHERE f.id = ?
        ");

        $stmt->execute([$id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            http_response_code(404);
            echo json_encode(['error' => 'Factura no encontrada']);
            return;
        }

        // Detalles
        $stmt = $pdo->prepare("
            SELECT d.cantidad, d.precio_unitario, d.unidad_medida, d.subtotal, p.nombre
            FROM detalle_factura d
            JOIN productos p ON d.producto_id = p.id
            WHERE d.factura_id = ?
        ");

        $stmt->execute([$id]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'factura' => [
                'id' => $factura['id'],
                'fecha' => $factura['fecha'],
                'servicio' => floatval($factura['servicio']),
                'pago_tarjeta' => floatval($factura['pago_tarjeta']),
                'descuento' => floatval($factura['descuento']),
                'total' => floatval($factura['total']),
                'forma_pago' => $factura['forma_pago'],
            ],
            'cliente' => [
                'nombre' => "Cliente",
                'direccion' => "Lugar",
                'telefono' => "No registra"
            ],
            'productos' => array_map(function($p) {
                return [
                    'nombre' => $p['nombre'],
                    'cantidad' => floatval($p['cantidad']),
                    'unidad' => $p['unidad_medida'],
                    'precio' => floatval($p['precio_unitario']),
                    'subtotal' => floatval($p['subtotal'])
                ];
            }, $productos)
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener detalles']);
    }
});

// =====================================
// DELETE /facturas/{id}
// =====================================
$router->delete('/api/facturas/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();

    header('Content-Type: application/json');

    try {
        // Iniciar transacción
        $pdo->beginTransaction();

        // Obtener productos de la factura
        $stmtDetalle = $pdo->prepare("
            SELECT producto_id, cantidad
            FROM detalle_factura
            WHERE factura_id = ?
        ");
        $stmtDetalle->execute([$id]);
        $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

        if (empty($detalles)) {
            throw new Exception('No se encontraron detalles para la factura');
        }

        // Eliminar detalles
        $stmt = $pdo->prepare("DELETE FROM detalle_factura WHERE factura_id = ?");
        $stmt->execute([$id]);

        // Eliminar factura
        $stmt = $pdo->prepare("DELETE FROM facturas WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Factura no encontrada');
        }

        // Confirmar
        $pdo->commit();

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

});