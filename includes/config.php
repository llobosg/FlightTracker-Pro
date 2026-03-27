<?php
// includes/config.php — FlightTracker Pro — Versión integrada y lista para producción

// Definir raíz del proyecto (para rutas absolutas)
defined('ROOT_PATH') or define('ROOT_PATH', __DIR__ . '/..');
define('APP_URL', $_ENV['APP_URL'] ?? getenv('APP_URL') ?? 'http://localhost');

// Configuración de cabeceras CORS (ajusta según tu dominio final)
header("Access-Control-Allow-Origin: https://flighttracker-pro.up.railway.app");

// Iniciar sesión de forma segura (solo si no está activa)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Configuración de errores (ocultar en producción)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ROOT_PATH . '/logs/php_errors.log');
error_reporting(E_ALL);

/**
 * Cargar variables de entorno desde múltiples fuentes
 */
function loadEnvVars() {
    // Cargar .env solo en entorno local o CLI
    if (php_sapi_name() === 'cli' || !getenv('RAILWAY_ENVIRONMENT')) {
        $envPath = __DIR__ . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }

    // Prioridad: $_ENV > getenv() > $_SERVER
    return [
        'MYSQLHOST'      => $_ENV['MYSQLHOST']      ?? getenv('MYSQLHOST')      ?? $_SERVER['MYSQLHOST']      ?? '127.0.0.1',
        'MYSQLUSER'      => $_ENV['MYSQLUSER']      ?? getenv('MYSQLUSER')      ?? $_SERVER['MYSQLUSER']      ?? 'root',
        'MYSQLPASSWORD'  => $_ENV['MYSQLPASSWORD']  ?? getenv('MYSQLPASSWORD')  ?? $_SERVER['MYSQLPASSWORD']  ?? '',
        'MYSQLDATABASE'  => $_ENV['MYSQLDATABASE']  ?? getenv('MYSQLDATABASE')  ?? $_SERVER['MYSQLDATABASE']  ?? 'gestionafv_local',
        'MYSQLPORT'      => $_ENV['MYSQLPORT']      ?? getenv('MYSQLPORT')      ?? $_SERVER['MYSQLPORT']      ?? '3306',
        'BREVO_API_KEY'  => $_ENV['BREVO_API_KEY']  ?? getenv('BREVO_API_KEY')  ?? $_SERVER['BREVO_API_KEY']  ?? ''
    ];
}

$env = loadEnvVars();

// Validación crítica: base de datos
if (empty($env['MYSQLHOST']) || empty($env['MYSQLUSER']) || empty($env['MYSQLDATABASE'])) {
    error_log("❌ Faltan variables esenciales para la base de datos");
    http_response_code(500);
    die("Error de configuración: base de datos no disponible\n");
}

// Definir constante global para Brevo (accesible en toda la app)
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', $env['BREVO_API_KEY']);
}

// Conexión a la base de datos
try {
    $pdo = new PDO(
        "mysql:host={$env['MYSQLHOST']};port={$env['MYSQLPORT']};dbname={$env['MYSQLDATABASE']};charset=utf8mb4",
        $env['MYSQLUSER'],
        $env['MYSQLPASSWORD'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log("❌ Error de conexión a DB: " . $e->getMessage());
    http_response_code(500);
    die("No se pudo conectar a la base de datos\n");
}
?>