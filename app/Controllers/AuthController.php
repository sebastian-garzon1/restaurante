<?php

declare(strict_types=1);

class AuthController
{
    public function __construct(
        private UsuarioModel   $usuarioModel,
        private AuthMiddleware $auth
    ) {}

    public function showLogin(): void
    {
        $this->auth->isLoggedOut();
        $error = null;
        require __DIR__ . '/../../views/login.php';
    }

    public function login(): void
    {
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$usuario || !$password) {
            $error = 'Usuario y contraseña son requeridos';
            require __DIR__ . '/../../views/login.php';
            return;
        }

        $user = $this->usuarioModel->findByUsername($usuario);

        if (!$user) {
            $error = 'El usuario no existe';
            require __DIR__ . '/../../views/login.php';
            return;
        }

        if (!$user['activo']) {
            $error = 'Usuario inactivo. Contacta al administrador';
            require __DIR__ . '/../../views/login.php';
            return;
        }

        if (!password_verify($password, $user['password'])) {
            $error = 'Contraseña incorrecta';
            require __DIR__ . '/../../views/login.php';
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'      => $user['id'],
            'nombre'  => $user['nombre'],
            'usuario' => $user['usuario'],
            'rol'     => $user['rol'],
        ];
        $this->usuarioModel->updateLastLogin($user['id']);
        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        $this->auth->isLoggedIn();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: /login');
        exit;
    }
}
