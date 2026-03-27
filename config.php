<?php
// config.php

// Cargar variables de entorno si existen (útil para local con .env, Railway lo hace auto)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Definir constantes para compatibilidad con tu clase BrevoMailer y legacy code
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', getenv('BREVO_API_KEY'));
}
if (!defined('BREVO_FROM_MAIL')) {
    // Usar la variable de Railway o un fallback
    define('BREVO_FROM_MAIL', getenv('BREVO_FROM_MAIL') ?: 'no-reply@flighttracker.app');
}

// Configuración de Base de Datos (Nombres de variables de Railway)
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'flight_tracker_db');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');

// Configuración de la App
define('APP_ENV', getenv('APP_ENV') ?: 'local');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8000');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'cambia_esto_por_un_string_muy_largo_y_seguro_12345');

// Reporte de errores (Desactivar en producción si se desea, pero útil para debug inicial)
if (APP_ENV === 'local') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}