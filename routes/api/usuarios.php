<?php

// GET /api/usuarios  - Mostrar todos los usuarios
$router->get('/api/usuarios', function() use ($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->query("
            SELECT u.*, r.nombre AS rol_nombre
            FROM usuarios u
            LEFT JOIN roles r ON u.rol_id = r.id
            ORDER BY u.nombre
        ");

        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($usuarios);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener usuarios', 'detalle' => $e->getMessage()]);
    }
});

$router->get('/api/usuarios/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.nombre AS rol_nombre
            FROM usuarios u
            LEFT JOIN roles r ON u.rol_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) {
            http_response_code(408);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }

        echo json_encode($usuario);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener usuario']);
    }
});


$router->post('/api/usuarios', function() use ($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents("php://input"), true);

        $nombre   = $input['nombre']   ?? null;
        $username = $input['username'] ?? null;
        $password = $input['password'] ?? null;
        $correo   = $input['correo']   ?? null;
        $rol      = $input['rol']      ?? null;

        if (!$nombre || !$username || !$password || !$rol) {
            http_response_code(400);
            echo json_encode(['error' => 'Todos los campos son requeridos']);
            return;
        }

        // Hash seguro
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre, usuario, correo, password, rol_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $username, $correo, $hashed, $rol]);

        echo json_encode([
            'id' => $pdo->lastInsertId(),
            'message' => 'Usuario creado exitosamente'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Error al crear usuario',
            'detalle' => $e->getMessage()
        ]);
    }
});

$router->put('/api/usuarios/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents("php://input"), true);

        $nombre   = $input['nombre']   ?? null;
        $username = $input['username'] ?? null;
        $correo   = $input['correo']   ?? null;
        $password = $input['password'] ?? null;
        $rol      = $input['rol']      ?? null;

        if (!$nombre || !$username || !$rol) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos requeridos faltantes']);
            return;
        }

        $params = [$nombre, $username, $correo, $rol];

        $query = "
            UPDATE usuarios
            SET nombre = ?, usuario = ?, correo = ?, rol_id = ?
        ";

        if ($password) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $query .= ", password = ?";
            $params[] = $hashed;
        }

        $query .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }

        echo json_encode(['message' => 'Usuario actualizado exitosamente']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar usuario']);
    }
});

$router->delete('/api/usuarios/{id:\d+}', function($id) use ($pdo) {
    isLoggedIn();
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }

        echo json_encode(['message' => 'Usuario eliminado exitosamente']);

    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            http_response_code(400);
            echo json_encode(['error' => 'No se puede eliminar porque tiene registros asociados']);
            return;
        }

        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar usuario']);
    }
});
