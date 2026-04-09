<?php

$router->get('/login', function() use ($pdo) {
    isLoggedOut();
    $error = null;
    require __DIR__ . "/../../views/login.php";
});

// =============================================
// POST /auth (procesar login)
// =============================================
$router->post('/auth', function() use ($pdo) {

    $usuario  = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT u.id, u.usuario, u.password, r.nombre AS rol FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.usuario = ? ");
    $stmt->execute([$usuario]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = "El usuario no existe";
        require __DIR__ . "/../../views/login.php";
        return;
    }

    if (!password_verify($password, $user['password'])) {
        $error = "Contraseña incorrecta";
        require __DIR__ . "/../../views/login.php";
        return;
    }

    // Guardar en sesión
    $_SESSION['user'] = [
        "id"      => $user['id'],
        "usuario" => $user['usuario'],
        "rol"  => $user['rol']
    ];

    header("Location: /");
    exit;
});

// =========================
// GET /logout
// =========================
$router->get('/logout', function() {
    isLoggedIn();
    session_destroy();
    header("Location: /login");
    exit;
});