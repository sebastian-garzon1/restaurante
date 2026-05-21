<?php
// Asegúrate de tener session_start() al inicio de tu archivo index/login principal si usas $_SESSION
$error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : null;
unset($_SESSION['login_error']); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a Palermo Che - Gastronomía Argentina en Bogotá</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,700;1,400&family=Playfair+Display:ital,wght@1,400;1,600&display=swap" rel="stylesheet">
    
    <link rel="icon" href="images/logo.jpeg" type="image/jpeg">

    <style>
        :root {
            --bg-card: rgba(18, 18, 18, 0.85);
            --border-card: rgba(255, 255, 255, 0.15);
            --btn-blue: #1d5287;
            --btn-blue-hover: #153d65;
            --text-gold: #dfb15b; /* Tono dorado/cálido para las estrellas y detalles */
        }

        body {
            /* RECO: Reemplaza 'ruta_de_tu_imagen.png' con el nombre/ruta real de tu fondo */
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.6)), url('images/fondo_login.png') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Montserrat', sans-serif;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2rem 1rem;
        }

        /* Contenedor Principal del Formulario */
        .login-container {
            max-width: 440px;
            width: 100%;
            margin: auto;
            position: relative; /* Clave para el posicionamiento del escudo */
            padding-top: 90px;  /* Dejamos un espacio arriba para que el escudo que sobresale no se corte */
        }

        /* Escudo Principal de Palermo Che */
        .brand-shield {
            max-width: 200px;         /* Ajustamos el tamaño para que sea proporcional */
            display: block;
            position: absolute;       /* Lo sacamos del flujo normal */
            top: 0;                   /* Lo alineamos al techo del contenedor */
            left: 50%;
            transform: translateX(-50%); /* Lo centramos horizontalmente de forma matemática */
            z-index: 10;              /* Lo forzamos a estar por encima de la tarjeta oscura */
        }

        /* La tarjeta del Formulario */
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 24px;
            padding: 5rem 2rem 2.5rem 2rem; /* Aumentamos el padding superior (de 2.5rem a 5rem) para que el texto de bienvenida no quede tapado por el escudo */
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            position: relative;
            z-index: 1;
        }

        /* Tipografías del Encabezado */
        .welcome-title {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 1.6rem;
            color: #ffffff;
            margin-bottom: 0.2rem;
        }

        .brand-title {
            font-weight: 700;
            letter-spacing: 2px;
            font-size: 1.8rem;
            color: #ffffff;
            margin-bottom: 0.1rem;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            letter-spacing: 1px;
            color: #b3b3b3;
            text-transform: uppercase;
            font-weight: 300;
        }

        /* Estilos de los inputs con Iconos */
        .input-group-custom {
            position: relative;
            margin-bottom: 1.2rem;
        }

        .input-group-custom i.input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            font-size: 1.1rem;
            z-index: 10;
        }

        .input-group-custom .form-control {
            background-color: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: #ffffff;
            padding: 0.75rem 1rem 0.75rem 2.8rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .input-group-custom .form-control:focus {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: none;
            color: #fff;
        }

        /* Botón de ver contraseña */
        .btn-toggle-pass {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #888;
            z-index: 10;
            padding: 0;
        }
        .btn-toggle-pass:hover { color: #fff; }

        /* Botón Iniciar Sesión */
        .btn-submit {
            background-color: var(--btn-blue);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #ffffff;
            transition: background 0.3s ease;
        }

        .btn-submit:hover {
            background-color: var(--btn-blue-hover);
        }

        /* Texto Inspiracional Inferior de la Tarjeta */
        .card-footer-phrase {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            color: #ffffff;
            font-size: 1.1rem;
        }
        .card-footer-subphrase {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #dfb15b;
            font-weight: 700;
        }

        /* Estrellas doradas */
        .stars-decoration {
            color: var(--text-gold);
            font-size: 0.75rem;
            letter-spacing: 3px;
        }

        /* Enlace de recuperación */
        .forgot-password-link {
            color: #b3b3b3;
            font-size: 0.85rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-password-link:hover { color: #fff; }

        /* Sección de Características Inferiores (Footer del sitio) */
        .features-footer {
            max-width: 1200px;
            width: 100%;
            margin: 3rem auto 0 auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1.5rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .feature-icon {
            font-size: 1.8rem;
            color: #ffffff;
            opacity: 0.9;
        }

        .feature-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }

        .feature-desc {
            font-size: 0.75rem;
            color: #b3b3b3;
            margin-bottom: 0;
        }

        .bottom-credits {
            font-size: 0.75rem;
            letter-spacing: 2px;
            color: #888;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <div class="login-container">
        
        <img src="images/logo_login.png" alt="Palermo Che Shield" class="brand-shield">

        <div class="login-card text-center">
            <div class="welcome-title">Bienvenido a</div>
            <div class="brand-title">PALERMO CHE</div>
            <div class="brand-subtitle">Gastronomía Argentina en Bogotá</div>
            
            <div class="stars-decoration my-2">★ ★ ★</div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 px-3 small my-3" style="background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #fff;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form action="/auth" method="POST" class="mt-4">
                
                <div class="input-group-custom">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" name="usuario" class="form-control" placeholder="Usuario" required autofocus>
                </div>

                <div class="input-group-custom">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" class="form-control" id="password" placeholder="Contraseña" required>
                    <button class="btn-toggle-pass" type="button" onclick="togglePassword()">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>

                <button class="btn btn-submit w-100 mt-2" type="submit">
                    Iniciar Sesión
                </button>

                </form>

            <div class="mt-4 pt-2">
                <div class="card-footer-subphrase">Fútbol y la Gastronomía</div>
                <div class="card-footer-phrase fs-5">nos unen</div>
            </div>
        </div>
    </div>

    <div class="features-footer">
        <div class="row g-4 justify-content-center text-start">
            
            <div class="col-6 col-md-3">
                <div class="feature-item">
                    <i class="bi bi-egg-fried feature-icon"></i> <div>
                        <div class="feature-title">Parrilla Argentina</div>
                        <p class="feature-desc">Tradición y sabor en cada plato</p>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="feature-item">
                    <i class="bi bi-dribbble feature-icon"></i>
                    <div>
                        <div class="feature-title">Pasión por el fútbol</div>
                        <p class="feature-desc">Viví el fútbol como en casa</p>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="feature-item">
                    <i class="bi bi-handshake feature-icon"></i>
                    <div>
                        <div class="feature-title">Alianzas que suman</div>
                        <p class="feature-desc">Proveedores que comparten nuestra pasión</p>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="feature-item">
                    <i class="bi bi-people feature-icon"></i>
                    <div>
                        <div class="feature-title">Experiencias que inspiran</div>
                        <p class="feature-desc">Cada visita es única</p>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-2">
                <div class="feature-item">
                    <i class="bi bi-instagram feature-icon"></i>
                    <div>
                        <a href="https://www.instagram.com/palermoche6/" target="_blank" rel="noopener noreferrer" style="color: white;">Contactanos</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 pt-2 bottom-credits">
            Desde Argentina para Bogotá 🇦🇷 | 🇨🇴
            <div class="mt-1 text-lowercase" style="font-size: 0.65rem; opacity: 0.5;">
                &copy; <?= date('Y') ?> SebCode · Todos los derechos reservados
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById("password");
            const icon = document.getElementById("toggleIcon");
            if (field.type === "password") {
                field.type = "text";
                icon.classList.remove("bi-eye");
                icon.classList.add("bi-eye-slash");
            } else {
                field.type = "password";
                icon.classList.remove("bi-eye-slash");
                icon.classList.add("bi-eye");
            }
        }
    </script>
</body>
</html>