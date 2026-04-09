<?php

// ================================
// Middleware: verificar si está LOGUEADO
// ================================
function isLoggedIn() {
    if (!isset($_SESSION['user'])) {
        header("Location: /login");
        exit();
    }
}

// ================================
// Middleware: verificar si NO está logueado
// (para páginas como login o register)
// ================================
function isLoggedOut() {
    if (isset($_SESSION['user'])) {
        header("Location: /");
        exit();
    }
}

// ================================
// Middleware: verificar si es ADMIN
// id_rol === 1
// ================================
function requireRole(array $rolesPermitidos) {
    isLoggedIn();

    $rol = $_SESSION['user']['rol'];

    if (!in_array($rol, $rolesPermitidos)) {
        http_response_code(403);
        header("Location: /error");
        exit;
    }
}