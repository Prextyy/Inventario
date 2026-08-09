<?php
/**
 * Script de Respaldo Simple
 * 
 * Este script crea un respaldo de la base de datos
 * IMPORTANTE: Solo para uso de administradores
 */

require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Solo administradores pueden hacer backups
requireAdmin();

// El respaldo se genera en un archivo temporal fuera de la raiz publica.
$date = date('Y-m-d_H-i-s');
$backup_file = tempnam(sys_get_temp_dir(), 'inventario_backup_');

// Función para crear backup
function createDatabaseBackup($backup_file) {
    $conn = getDBConnection();
    
    // Obtener todas las tablas
    $tables = [];
    $result = mysqli_query($conn, "SHOW TABLES");
    
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    
    $output = "-- Respaldo de Base de Datos\n";
    $output .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Base de Datos: " . DB_NAME . "\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Exportar cada tabla
    foreach ($tables as $table) {
        // Estructura de la tabla
        $result = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
        $row = mysqli_fetch_row($result);
        
        $output .= "-- Estructura de tabla: $table\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $row[1] . ";\n\n";
        
        // Datos de la tabla
        $result = mysqli_query($conn, "SELECT * FROM `$table`");
        
        if (mysqli_num_rows($result) > 0) {
            $output .= "-- Datos de tabla: $table\n";
            
            while ($row = mysqli_fetch_assoc($result)) {
                $output .= "INSERT INTO `$table` VALUES(";
                
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $value) . "'";
                }
                
                $output .= implode(',', $values);
                $output .= ");\n";
            }
            
            $output .= "\n";
        }
    }
    
    $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Guardar archivo
    return file_put_contents($backup_file, $output);
}

// Procesar solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    if (createDatabaseBackup($backup_file)) {
        // Descargar el archivo
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="inventario_' . $date . '.sql"');
        header('Content-Length: ' . filesize($backup_file));
        readfile($backup_file);
        @unlink($backup_file);
        exit;
    } else {
        $error = "Error al crear el respaldo";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respaldo de Base de Datos - <?php echo SITE_NAME; ?></title>
    <meta name="theme-color" content="#0b1220">
    <link rel="stylesheet" href="assets/css/style.css?v=5.1">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div><span class="eyebrow">Proteccion de datos</span><h1>Respaldo de base de datos</h1><p>Descarga una copia completa y guardala en un lugar seguro.</p></div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="warning">
                <strong>⚠️ Importante:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>El respaldo incluye TODOS los datos del sistema</li>
                    <li>Guarda el archivo en un lugar seguro</li>
                    <li>Se recomienda hacer respaldos regularmente</li>
                    <li>El archivo puede ser grande dependiendo de tu inventario</li>
                </ul>
            </div>
            
            <form method="POST" style="margin-top: 30px;">
                <div class="export-card" style="max-width: 500px; margin: 0 auto;">
                    <div class="export-icon">💾</div>
                    <h3>Crear Respaldo Ahora</h3>
                    <p>Se creará un archivo SQL con todos los datos de la base de datos.</p>
                    <button type="submit" name="create_backup" class="btn btn-primary btn-lg">
                        Descargar Respaldo
                    </button>
                </div>
            </form>
            
            <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>ℹ️ Información sobre Respaldos</h3>
                <p style="margin-bottom: 10px;"><strong>Qué incluye el respaldo:</strong></p>
                <ul style="margin-left: 20px; color: #666;">
                    <li>Todos los productos y sus datos</li>
                    <li>Todas las categorías</li>
                    <li>Historial completo de movimientos</li>
                    <li>Usuarios del sistema</li>
                    <li>Configuraciones</li>
                </ul>
                
                <p style="margin-top: 20px; margin-bottom: 10px;"><strong>Cómo restaurar un respaldo:</strong></p>
                <ul style="margin-left: 20px; color: #666;">
                    <li>Accede a phpMyAdmin o tu gestor de MySQL</li>
                    <li>Selecciona la base de datos</li>
                    <li>Ve a la pestaña "Importar"</li>
                    <li>Selecciona el archivo .sql del respaldo</li>
                    <li>Haz clic en "Continuar"</li>
                </ul>
                
                <p style="margin-top: 20px; margin-bottom: 10px;"><strong>Recomendaciones:</strong></p>
                <ul style="margin-left: 20px; color: #666;">
                    <li>Haz respaldos diarios o semanales según tu uso</li>
                    <li>Guarda los respaldos en múltiples ubicaciones</li>
                    <li>Prueba restaurar un respaldo para asegurarte que funciona</li>
                    <li>Mantén al menos los últimos 3 respaldos</li>
                </ul>
            </div>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="dashboard.php" class="btn">← Volver al Dashboard</a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
