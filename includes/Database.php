<?php
// includes/Database.php

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Intentar leer constantes, si no existen, leer variables de entorno directas de Railway
        $host = defined('DB_HOST') ? DB_HOST : getenv('MYSQLHOST');
        $port = defined('DB_PORT') ? DB_PORT : getenv('MYSQLPORT');
        $database = defined('DB_NAME') ? DB_NAME : getenv('MYSQLDATABASE');
        $username = defined('DB_USER') ? DB_USER : getenv('MYSQLUSER');
        $password = defined('DB_PASS') ? DB_PASS : getenv('MYSQLPASSWORD');

        // Fallback por si acaso (local)
        if (!$host) $host = 'localhost';
        if (!$port) $port = '3306';
        if (!$database) $database = 'flight_tracker_db';
        if (!$username) $username = 'root';
        
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }
}