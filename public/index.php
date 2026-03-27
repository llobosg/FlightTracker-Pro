<?php
// public/index.php

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cargar clases (asegúrate que las rutas sean así)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/BrevoMailer.php';
require_once __DIR__ . '/../includes/Auth.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Limpieza de ruta para evitar problemas de doble slash o ruta vacía
if ($uri === '/' || $uri === '/index.php' || $uri === '') {
    header("Content-Type: text/html; charset=utf-8");
    // Servir el archivo HTML directamente
    if (file_exists(__DIR__ . '/assets/views/pwa.html')) {
        echo file_get_contents(__DIR__ . '/assets/views/pwa.html');
        exit();
    } else {
        // Fallback si no existe el HTML (para debug)
        echo "<h1>FlightTracker API Online</h1><p>El frontend no se encontró en assets/views/pwa.html</p>";
        exit();
    }
}

// Rutas API
if (strpos($uri, '/api/') === 0) {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        if ($uri === '/api/register' && $method === 'POST') {
            $res = Auth::register($input['name'] ?? '', $input['email'] ?? '', $input['password'] ?? '');
            echo json_encode($res);
        } 
        elseif ($uri === '/api/verify' && $method === 'POST') {
            $res = Auth::verify($input['email'] ?? '', $input['code'] ?? '');
            echo json_encode($res);
        }
        elseif ($uri === '/api/login' && $method === 'POST') {
            $res = Auth::login($input['email'] ?? '', $input['password'] ?? '');
            echo json_encode($res);
        }
        else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint no encontrado']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Servir Frontend PWA (Si no es API)
if ($uri === '/' || $uri === '/index.php') {
    header("Content-Type: text/html");
    readfile(__DIR__ . '/assets/views/pwa.html'); // Asegúrate de crear este archivo con el HTML que te di antes
    exit();
}

// Servir estáticos (CSS, JS, Manifest, SW)
$filePath = __DIR__ . $uri;
if (file_exists($filePath) && is_file($filePath)) {
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'html' => 'text/html'
    ];
    if (isset($mimeTypes[$ext])) {
        header("Content-Type: " . $mimeTypes[$ext]);
        readfile($filePath);
    } else {
        http_response_code(403);
        echo "Forbidden";
    }
} else {
    http_response_code(404);
    echo "404 Not Found";
}