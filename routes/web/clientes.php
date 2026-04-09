<?php

// Clientes
$router->get('/clientes', function() use($pdo) {
    requireRole([ROLE_ADMIN, ROLE_CAJERO, ROLE_MESERO]);
    $rol = currentRole();
    $stmt = $pdo->query("SELECT * FROM clientes ORDER BY nombre");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    require __DIR__ . "/../../views/clientes.php";
});