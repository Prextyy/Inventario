<?php
require_once 'config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfRequest();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Ingresa usuario y contraseña.';
    } else {
        if (login($username, $password)) {
            header('Location: dashboard.php');
            exit();
        }
        $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= 5) {
            sleep(1);
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b1220">
    <title>Iniciar sesión - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
</head>
<body class="login-page">
    <div class="login-shell">
        <section class="login-intro" aria-label="Presentacion del sistema">
            <div class="login-brand">
                <span class="brand-mark brand-mark-large" aria-hidden="true">
                    <svg viewBox="0 0 32 32" focusable="false">
                        <path d="M5 8.5 16 3l11 5.5v15L16 29 5 23.5z" class="brand-box"></path>
                        <path d="M5 8.5 16 14l11-5.5M16 14v15" class="brand-fold"></path>
                        <path d="M10 11v8m3-6.5V21m3-7v9m3-10.5V21m3-10v8" class="brand-bars"></path>
                    </svg>
                </span>
                <span>Inventario Fanasa</span>
            </div>
            <div class="login-intro-copy">
                <span class="eyebrow">Inventario central</span>
                <h1>El almacén, claro y bajo control.</h1>
                <p>Ubica productos, escanea movimientos y atiende solicitudes desde un solo lugar.</p>
            </div>
            <div class="login-status"><span></span> Acceso seguro para personal autorizado</div>
        </section>

        <main class="login-box">
            <div class="login-header">
                <span class="eyebrow">Bienvenido</span>
                <h2>Inicia sesión</h2>
                <p>Ingresa tus credenciales para continuar.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="POST" class="login-form" autocomplete="on">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" required autofocus autocomplete="username" placeholder="Tu usuario">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Tu contraseña">
                        <button type="button" class="password-toggle" aria-label="Mostrar contraseña">Mostrar</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-login">Entrar al sistema <span aria-hidden="true">→</span></button>
            </form>
            <p class="login-help">Si no puedes acceder, solicita apoyo al administrador.</p>
        </main>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.querySelector('.password-toggle');
        passwordToggle.addEventListener('click', function () {
            const visible = passwordInput.type === 'text';
            passwordInput.type = visible ? 'password' : 'text';
            this.textContent = visible ? 'Mostrar' : 'Ocultar';
            this.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        });
    </script>
</body>
</html>
