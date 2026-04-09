<?php

// Usuarios
$router->get('/usuarios', function() use($pdo) {
    requireRole([ROLE_ADMIN]);
    $rol = currentRole();

    $stmt = $pdo->query("SELECT u.*, r.nombre AS rol_nombre FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id ORDER BY u.nombre");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    require __DIR__ . "/../../views/usuarios.php";
});