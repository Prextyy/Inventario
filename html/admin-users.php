<?php
// Panel de Administración - Gestión de Usuarios
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$conn = getDBConnection();
$error = '';
$success = '';

// Crear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $role = trim($_POST['role'] ?? 'user');
    $area_id = trim($_POST['area_id'] ?? null);
    
    if (!in_array($role, ['admin', 'user'], true)) {
        $role = 'user';
    }

    if (empty($username) || empty($password)) {
        $error = 'Usuario y contraseña son requeridos';
    } elseif (mb_strlen($username) > 80 || mb_strlen($password) < 8) {
        $error = 'El usuario debe tener hasta 80 caracteres y la contraseña al menos 8.';
    } else {
        // Verificar si el usuario ya existe
        $sql = "SELECT id FROM users WHERE username = '" . mysqli_real_escape_string($conn, $username) . "'";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) > 0) {
            $error = 'El usuario ya existe';
        } else {
            // Crear usuario
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $area_val = empty($area_id) ? 'NULL' : "'" . mysqli_real_escape_string($conn, $area_id) . "'";
            $email_val = empty($email) ? "'usuario@ejemplo.com'" : "'" . mysqli_real_escape_string($conn, $email) . "'";
            
            $sql = "INSERT INTO users (username, email, password, role, area_id) VALUES (
                '" . mysqli_real_escape_string($conn, $username) . "',
                $email_val,
                '" . mysqli_real_escape_string($conn, $hashed_password) . "',
                '" . mysqli_real_escape_string($conn, $role) . "',
                $area_val
            )";
            
            if (mysqli_query($conn, $sql)) {
                $success = "✓ Usuario '$username' creado exitosamente como $role";
            } else {
                $error = 'Error al crear usuario: ' . mysqli_error($conn);
            }
        }
    }
}

// Cambiar rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_rol') {
    $user_id = trim($_POST['user_id'] ?? '');
    $new_role = trim($_POST['new_role'] ?? '');
    $user_id = (int)$user_id;
    
    if ($user_id > 0 && in_array($new_role, ['admin', 'user'], true) && $user_id !== (int)getCurrentUserId()) {
        $sql = "UPDATE users SET role = '" . mysqli_real_escape_string($conn, $new_role) . "' WHERE id = '" . mysqli_real_escape_string($conn, $user_id) . "'";
        
        if (mysqli_query($conn, $sql)) {
            $success = "✓ Rol actualizado exitosamente";
        } else {
            $error = 'Error al cambiar rol: ' . mysqli_error($conn);
        }
    }
}

        // Obtener usuarios
$sql = "SELECT u.*, a.name as area_name FROM users u LEFT JOIN areas a ON u.area_id = a.id ORDER BY u.created_at DESC";
$result = mysqli_query($conn, $sql);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Obtener áreas
$sql_areas = "SELECT id, name FROM areas ORDER BY name";
$areas_result = mysqli_query($conn, $sql_areas);
$areas = mysqli_fetch_all($areas_result, MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?php echo SITE_NAME; ?></title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
</head>
<body>
    <?php require_once 'includes/header.php'; ?>
    
    <div class="admin-panel">
        <div class="page-header">
            <h1>Gestion de Usuarios</h1>
            <p>Administra cuentas, roles y asignaciones por departamento.</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="msg error"><?php echo e($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="msg success"><?php echo e($success); ?></div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats">
            <div class="stat-card">
                <h3>Total de Usuarios</h3>
                <div class="value"><?php echo count($users); ?></div>
            </div>
            <div class="stat-card">
                <h3>Administradores</h3>
                <div class="value"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></div>
            </div>
            <div class="stat-card">
                <h3>Usuarios Regulares</h3>
                <div class="value"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'user')); ?></div>
            </div>
        </div>
        
        <!-- Crear Usuario -->
        <div class="form-section">
            <h3>Crear Nuevo Usuario</h3>
            <form method="POST">
                <input type="hidden" name="action" value="crear">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Usuario *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email (Opcional)</label>
                        <input type="email" id="email" name="email" placeholder="usuario@ejemplo.com">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Contraseña *</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Rol</label>
                        <select id="role" name="role">
                            <option value="user" selected>Usuario Regular</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="area_id">Departamento (Opcional)</label>
                        <select id="area_id" name="area_id">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['id']; ?>"><?php echo $area['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">+ Crear Usuario</button>
            </form>
        </div>
        
        <!-- Listado de Usuarios -->
        <div>
            <h3>Usuarios Actuales</h3>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Departamento</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>#<?php echo $user['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="user-self-badge"> (TU)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                            <td>
                                <span class="role-badge <?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['area_name'] ?? '-'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['role'] === 'user'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="cambiar_rol">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="new_role" value="admin">
                                        <button type="submit" class="btn btn-primary btn-sm">Hacer Admin</button>
                                    </form>
                                <?php elseif ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="cambiar_rol">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="new_role" value="user">
                                        <button type="submit" class="btn btn-secondary btn-sm">Hacer Usuario</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>
