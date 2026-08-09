<?php
/**
 * Script de Verificación Pre-Actualización
 * Verifica que todo esté listo para la actualización del sistema
 */

require_once 'config.php';
require_once 'includes/auth.php';

requireAdmin();

$checks = [];
$all_good = true;

// 1. Verificar si las tablas nuevas existen
$conn = getDBConnection();

$tables_to_check = ['areas', 'product_requests', 'custom_product_requests'];
foreach ($tables_to_check as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = mysqli_num_rows($result) > 0;
    $checks[] = [
        'name' => "Tabla '$table' existe",
        'status' => $exists,
        'icon' => $exists ? '✓' : '✗',
        'action' => !$exists ? 'Ejecutar upgrade-db.php' : 'Ya existe'
    ];
    if (!$exists) $all_good = false;
}

// 2. Verificar columnas nuevas
$result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'image'");
$image_col_exists = mysqli_num_rows($result) > 0;
$checks[] = [
    'name' => "Columna 'image' en products",
    'status' => $image_col_exists,
    'icon' => $image_col_exists ? '✓' : '✗',
    'action' => !$image_col_exists ? 'Ejecutar upgrade-db.php' : 'Ya existe'
];
if (!$image_col_exists) $all_good = false;

$result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'area_id'");
$area_col_exists = mysqli_num_rows($result) > 0;
$checks[] = [
    'name' => "Columna 'area_id' en users",
    'status' => $area_col_exists,
    'icon' => $area_col_exists ? '✓' : '✗',
    'action' => !$area_col_exists ? 'Ejecutar upgrade-db.php' : 'Ya existe'
];
if (!$area_col_exists) $all_good = false;

// 3. Verificar directorio de subidas
$uploads_dir = __DIR__ . '/uploads/products/';
$uploads_exists = is_dir($uploads_dir);
$uploads_writable = $uploads_exists && is_writable($uploads_dir);

$checks[] = [
    'name' => "Directorio 'uploads/products/' existe",
    'status' => $uploads_exists,
    'icon' => $uploads_exists ? '✓' : '✗',
    'action' => !$uploads_exists ? 'Se creará automáticamente' : 'Ya existe'
];

$checks[] = [
    'name' => "Directorio 'uploads/products/' es escribible",
    'status' => $uploads_writable,
    'icon' => $uploads_writable ? '✓' : '✗',
    'action' => $uploads_writable ? 'OK' : 'Verificar permisos'
];

// 4. Verificar archivos nuevos
$new_files = [
    'manage-requests.php',
    'my-requests.php',
    'includes/requests.php',
    'upgrade-db.php'
];

foreach ($new_files as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $checks[] = [
        'name' => "Archivo '$file' existe",
        'status' => $exists,
        'icon' => $exists ? '✓' : '✗',
        'action' => $exists ? 'OK' : 'Necesita crear'
    ];
    if (!$exists) $all_good = false;
}

// 5. Verificar versión de PHP
$php_ok = version_compare(PHP_VERSION, '7.0.0', '>=');
$checks[] = [
    'name' => "PHP 7.0+ (tienes " . PHP_VERSION . ")",
    'status' => $php_ok,
    'icon' => $php_ok ? '✓' : '✗',
    'action' => $php_ok ? 'OK' : 'Actualizar PHP'
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Pre-Actualización</title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🔍 Verificación Pre-Actualización</h1>
            <p>Verifica que todo esté listo para instalar las nuevas funcionalidades</p>
        </div>
        
        <div class="overall-status <?php echo $all_good ? 'ready' : 'not-ready'; ?>">
            <h2><?php echo $all_good ? 'Todo listo' : 'Se requieren ajustes'; ?></h2>
            <p><?php echo $all_good ? 'El sistema está listo para usar las nuevas funcionalidades.' : 'Ejecuta upgrade-db.php y completa los pasos siguientes.'; ?></p>
        </div>
        
        <div>
            <h2>Checklist de Verificación</h2>
            
            <?php foreach ($checks as $check): ?>
                <div class="check-item <?php echo $check['status'] ? 'success' : 'error'; ?>">
                    <div class="check-icon"><?php echo $check['icon']; ?></div>
                    <div class="check-content">
                        <div class="check-name"><?php echo $check['name']; ?></div>
                        <div class="check-action"><?php echo $check['action']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!$all_good): ?>
            <div class="next-steps">
                <h3>📝 Próximos Pasos</h3>
                <ol>
                    <li><strong>Ejecutar upgrade-db.php</strong> como administrador:
                        <br><code class="inline-code-muted">https://tu-sitio.com/inventario/upgrade-db.php</code>
                    </li>
                    <li>Verifica que todos los cambios se apliquen correctamente</li>
                    <li>Vuelve a esta página para confirmar que todo esté OK</li>
                    <li>Una vez todo esté verde (✓), puedes empezar a usar las nuevas funcionalidades</li>
                </ol>
            </div>
        <?php else: ?>
            <div class="next-steps next-steps-success">
                <h3>Listo para comenzar</h3>
                <ul class="list-plain">
                    <li>📍 Ir a <strong>"Mis Solicitudes"</strong> desde el menú para crear solicitudes</li>
                    <li>📍 Ir a <strong>"Gestionar Solicitudes"</strong> (admin) para aprobar solicitudes</li>
                    <li>📍 Subir imágenes en <strong>"Productos" → "Editar"</strong></li>
                    <li>📍 Ver estadísticas en el <strong>Dashboard</strong></li>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="section-top text-center">
            <a href="dashboard.php" class="btn">← Volver al Dashboard</a>
            <?php if (!$all_good): ?>
                <a href="upgrade-db.php" class="btn btn-primary">Ejecutar Actualización →</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
