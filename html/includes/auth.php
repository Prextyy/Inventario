<?php
/**
 * Autenticacion y protecciones compartidas.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfRequest(): void {
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!is_string($submitted) || !hash_equals(csrfToken(), $submitted)) {
        http_response_code(419);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'La sesion del formulario vencio. Recarga la pagina e intenta de nuevo.'
        ]);
        exit;
    }
}

function login($username, $password) {
    $conn = getDBConnection();
    $statement = mysqli_prepare($conn, 'SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 's', $username);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $user = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($statement);

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        unset($_SESSION['login_attempts']);
        return true;
    }

    return false;
}

function logout() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }

    session_destroy();
    header('Location: login.php');
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['logged_in'], $_SESSION['user_id']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    $maxIdleSeconds = 8 * 60 * 60;
    if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > $maxIdleSeconds) {
        logout();
    }
    $_SESSION['last_activity'] = time();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        verifyCsrfRequest();
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        exit('Acceso denegado. Se requieren privilegios de administrador.');
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? 'Invitado';
}
