<?php

// Clientes
$router->get('/api/clientes', function() use($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->query("SELECT * FROM clientes ORDER BY nombre");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $clientes = [];
    }

    echo json_encode($clientes);
});

$router->get('/api/clientes/buscar', function() use($pdo) {

    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $query = $_GET['q'] ?? '';

        $sql = "
            SELECT * FROM clientes
            WHERE nombre LIKE ? OR telefono LIKE ? OR correo LIKE ?
            ORDER BY nombre
            LIMIT 10
        ";

        $search = "%$query%";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$search, $search, $search]);

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al buscar clientes']);
    }
});

$router->get('/api/clientes/{id}', function($id) use($pdo) {

    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$id]);

        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            http_response_code(404);
            echo json_encode(['error' => 'Cliente no encontrado']);
            return;
        }

        echo json_encode($cliente);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener cliente']);
    }
});

$router->post('/api/clientes', function() use($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    // Convertir JSON del body
    try {
        $_POST = json_decode(file_get_contents('php://input'), true);
        $nombre    = $_POST['nombre']    ?? null;
        $correo    = $_POST['correo']    ?? null;
        $direccion = $_POST['direccion'] ?? null;
        $telefono  = $_POST['telefono']  ?? null;

        if (!$nombre) {
            http_response_code(400);
            echo json_encode(['error' => 'El nombre es requerido']);
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO clientes (nombre, correo, direccion, telefono)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$nombre, $correo, $direccion, $telefono]);

        echo json_encode([
            'id' => $pdo->lastInsertId(),
            'message' => 'Cliente creado exitosamente'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'message' => 'Error al crear cliente',
            'detalle' => $e->getMessage()
        ]);
    }
});

$router->put('/api/clientes/{id}', function($id) use($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $nombre    = $data['nombre']    ?? null;
        $correo    = $data['correo']    ?? null;
        $direccion = $data['direccion'] ?? null;
        $telefono  = $data['telefono']  ?? null;

        if (!$nombre) {
            http_response_code(400);
            echo json_encode(['error' => 'El nombre es requerido'.json_encode($data)]);
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE clientes
            SET nombre = ?, correo = ?, direccion = ?, telefono = ?
            WHERE id = ?
        ");

        $stmt->execute([$nombre, $correo, $direccion, $telefono, $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Cliente no encontrado']);
            return;
        }

        echo json_encode(['message' => 'Cliente actualizado exitosamente']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar cliente']);
    }
});

$router->delete('/api/clientes/{id}', function($id) use($pdo) {

    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Cliente no encontrado']);
            return;
        }

        echo json_encode(['message' => 'Cliente eliminado exitosamente']);

    } catch (PDOException $e) {

        if ($e->errorInfo[1] == 1451) {
            http_response_code(400);
            echo json_encode(['error' => 'No se puede eliminar el cliente porque tiene facturas asociadas']);
            return;
        }

        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar cliente']);
    }
});
