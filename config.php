<?php
// Detectar entorno
if (getenv('RAILWAY_ENVIRONMENT')) {
    $host = getenv('RAILWAY_MYSQL_HOST') ?: getenv('RAILWAY_DB_HOST');
    $dbname = getenv('RAILWAY_MYSQL_DATABASE') ?: getenv('RAILWAY_DB_NAME');
    $username = getenv('RAILWAY_MYSQL_USER') ?: getenv('RAILWAY_DB_USER');
    $password = getenv('RAILWAY_MYSQL_PASSWORD') ?: getenv('RAILWAY_DB_PASSWORD');
    $port = getenv('RAILWAY_MYSQL_PORT') ?: '3306';
} else {
    // Local
    $host = 'localhost';
    $dbname = 'stockapp_local';
    $username = 'root';
    $password = '';
    $port = '3306';
}

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
?>