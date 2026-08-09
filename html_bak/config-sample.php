<?php
/**
 * Archivo de Configuración de Ejemplo
 * 
 * Este archivo será creado automáticamente durante la instalación.
 * No edites este archivo manualmente.
 */

// Configuración de Base de Datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'nombre_base_datos');
define('DB_USER', 'usuario_mysql');
define('DB_PASS', 'contraseña_mysql');

// Configuración del Sitio
define('SITE_NAME', 'Sistema de Inventario');
define('SITE_URL', 'http://localhost/inventario');

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Claves de seguridad - Se generarán automáticamente durante la instalación
define('SECURE_AUTH_KEY', 'tu-clave-segura-aqui');
define('SECURE_AUTH_SALT', 'tu-salt-seguro-aqui');

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
