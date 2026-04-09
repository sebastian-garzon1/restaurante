<?php

// Productos
$router->get('/api/productos', function() use ($pdo) {
    isLoggedIn();

    try {
        $stmt = $pdo->query("SELECT * FROM productos ORDER BY nombre");
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $productos = [];
    }

    echo json_encode($productos);
});


// ====================================
// GET /productos/buscar - Buscar AJAX
// ====================================
$router->get('/api/productos/buscar', function() use ($pdo) {
    isLoggedIn();

    header('Content-Type: application/json');

    try {
        $query = $_GET['q'] ?? '';

        $sql = "
            SELECT * FROM productos
            WHERE nombre LIKE ? OR codigo LIKE ?
            ORDER BY nombre
            LIMIT 10
        ";

        $term = "%$query%";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$term, $term]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al buscar productos']);
    }
});


// ============================================
// GET /productos/{id} - Obtener un producto
// ============================================
$router->get('/api/productos/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();

    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            http_response_code(404);
            echo json_encode(['error' => 'Producto no encontrado']);
            return;
        }

        echo json_encode($producto);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al obtener producto']);
    }
});

// ====================================
// GET /productos/buscar - Buscar AJAX
// ====================================
$router->get('/api/productos/insumos/buscar/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();

    header('Content-Type: application/json');

    try {
        $query = $_GET['q'] ?? '';

        $sql = "
            SELECT i.*
            FROM insumos i
            LEFT JOIN producto_insumo pi
                ON pi.insumo_id = i.id
                AND pi.producto_id = ?
            WHERE i.nombre LIKE ?
            AND pi.id IS NULL
            ORDER BY i.nombre
            LIMIT 10;
        ";

        $term = "%$query%";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $term]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al buscar el insumo']);
    }
});

// ====================================
// POST /productos - Crear producto
// ====================================
$router->post('/api/productos', function() use ($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $_POST = json_decode(file_get_contents('php://input'), true);
        $codigo        = $_POST['codigo']        ?? null;
        $nombre        = $_POST['nombre']        ?? null;
        $precio_unidad = $_POST['precio_unidad'] ?? 0;
        $cocina = $_POST['cocina'] ?? false;

        if (!$codigo || !$nombre) {
            http_response_code(400);
            echo json_encode(['error' => 'El código y nombre son requeridos'.json_encode($_POST)]);
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO productos (codigo, nombre, precio_unidad, cocina)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$codigo, $nombre, $precio_unidad, $cocina]);

        echo json_encode([
            'id' => $pdo->lastInsertId(),
            'message' => 'Producto creado exitosamente'
        ]);
    } catch (PDOException $e) {

        if ($e->errorInfo[1] == 1062) { // Duplicate entry
            http_response_code(400);
            echo json_encode(['error' => 'Ya existe un producto con ese código']);
            return;
        }

        http_response_code(500);
        echo json_encode(['error' => 'Error al crear producto']);
    }
});


// =====================================
// PUT /productos/{id} - Actualizar
// =====================================
$router->put('/api/productos/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();

    header('Content-Type: application/json');

    // Obtener JSON del body:
    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $codigo        = $data['codigo']        ?? null;
        $nombre        = $data['nombre']        ?? null;
        $precio_unidad = $data['precio_unidad'] ?? 0;
        $cocina = $data['cocina'] ?? false;

        if (!$codigo || !$nombre) {
            http_response_code(400);
            echo json_encode(['error' => 'El código y nombre son requeridos']);
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE productos
            SET codigo = ?, nombre = ?, precio_unidad = ? , cocina = ?
            WHERE id = ?
        ");

        $stmt->execute([$codigo, $nombre, $precio_unidad, $cocina, $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Producto no encontrado']);
            return;
        }

        echo json_encode(['message' => 'Producto actualizado exitosamente']);
    } catch (PDOException $e) {

        if ($e->errorInfo[1] == 1062) {
            http_response_code(400);
            echo json_encode(['error' => 'Ya existe un producto con ese código']);
            return;
        }

        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar producto']);
    }
});


// =====================================
// DELETE /productos/{id}
// =====================================
$router->delete('/api/productos/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();

    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Producto no encontrado']);
            return;
        }

        echo json_encode(['message' => 'Producto eliminado exitosamente']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar producto']);
    }
});