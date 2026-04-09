<?php

$router->get('/api/roles', function() use ($pdo) {
    isLoggedIn(); // opcional, si deseas protegerlo
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->query("SELECT id, nombre FROM roles ORDER BY nombre");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($roles ?: []);

    } catch (Exception $e) {
        error_log("Error al cargar roles: " . $e->getMessage());

        http_response_code(500);
        echo json_encode([
            'error' => 'Error al cargar roles',
            'detalle' => $e->getMessage()
        ]);
    }
});
