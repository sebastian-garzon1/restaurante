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

    // Vista de restaurar contraseña
    public function restorePass(): void
    {
        require __DIR__ . '/../../views/restaurar_pass.php';
    }

    // Confirmar restablecer contraseña
    public function restorePassConfirm(): void
    {
        $usuario = trim($_POST['usuario'] ?? '');
        $correo  = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        // PASO 1: validar usuario + correo
        if ($usuario && $correo && !$password) {

            $user = $this->usuarioModel->findByUserAndEmail($usuario, $correo);

            if (!$user) {
                $_SESSION['login_error'] = 'Usuario y correo no coinciden';
                header('Location: /restore_password');
                exit;
            }

            // Guardamos usuario validado en sesión
            $_SESSION['reset_user'] = $user['id'];

            header('Location: /restore_password?step=2');
            exit;
        }

        // PASO 2: cambiar contraseña
        if ($password && isset($_SESSION['reset_user'])) {

            $this->usuarioModel->updatePassword(
                $_SESSION['reset_user'],
                $password
            );

            unset($_SESSION['reset_user']);

            $_SESSION['login_error'] = 'Contraseña actualizada correctamente';
            header('Location: /login');
            exit;
        }

        $_SESSION['login_error'] = 'Datos incompletos';
        header('Location: /restore_password');
    }

    public function login(): void
    {
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$usuario || !$password) {
            $_SESSION['login_error'] = 'Usuario y contraseña son requeridos';
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
