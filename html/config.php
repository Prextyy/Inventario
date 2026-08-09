<?php
// Configuración de Base de Datos
define('DB_HOST', 'rihxfw4fvymwibps8exb8gas');
define('DB_NAME', 'Prexty_inventarios');
define('DB_USER', 'root');
define('DB_PASS', 'Kq7zMvD3R9KlBQdv1Lcm5DsCBpZfDyMAwXQWzrdLtGRRYXYcfyVMWwQcB7ArijQ2');

// Configuración del Sitio
define('SITE_NAME', 'Sistema de Inventario');
define('SITE_URL', 'http://inventario.anihost.lat');

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Claves de seguridad - Se generarán automáticamente durante la instalación
define('SECURE_AUTH_KEY', 'x1740924kj1482098412');
define('SECURE_AUTH_SALT', 'x90192841k24j120984');

// Conexión a la base de datos
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if (!$conn) {
            die('Error de conexión: ' . mysqli_connect_error());
        }
        
        mysqli_set_charset($conn, 'utf8mb4');
    }
    
    return $conn;
}
