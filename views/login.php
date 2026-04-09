<?php
$error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : null;
unset($_SESSION['login_error']); // borrar después de mostrarlo
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Palermo Che</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.jpeg" type="image/jpeg">

    <style>
        body {
            background: #f8f9fa;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 20px;
            padding: 2rem;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0,0,0,.1);
        }

        .login-logo {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .form-control {
            padding: 0.75rem;
            font-size: 1.1rem;
        }

        .footer-text {
            text-align: center;
            color: #6c757d;
            margin-top: 1rem;
            font-size: .9rem;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="text-center mb-4">
            <img src="/images/logo.jpeg" alt="Logo Palermo Che" width="80px" srcset="">
            <div class="login-logo">Palermo Che</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="/auth" method="POST">
            <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input type="text" name="usuario" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" id="password" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                        <i class="bi bi-eye-fill"></i>
                    </button>
                </div>
            </div>

            <button class="btn btn-primary w-100 mt-3" type="submit">
                <i class="bi bi-box-arrow-in-right"></i> Ingresar
            </button>
        </form>

        <div class="footer-text">
            &copy; <?= date('Y') ?> SebCode · Todos los derechos reservados
        </div>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById("password");
            field.type = field.type === "password" ? "text" : "password";
        }
    </script>

</body>
</html>