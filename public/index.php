<?php
// public/index.php

// 1. Configuración de Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Cargar Configuración y Clases
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';

// Iniciar Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Enrutamiento
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Normalizar URI
if (strpos($uri, '/public') === 0) {
    $uri = substr($uri, 7);
}
if ($uri === '') $uri = '/';

// --- DEBUG: Logear todas las peticiones POST para ver qué llega ---
if ($method === 'POST') {
    error_log("DEBUG POST Request URI: " . $uri);
}

// A. Registro
if ($uri === '/api/register' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $result = Auth::register($input['name'] ?? '', $input['email'] ?? '', $input['password'] ?? '');
    echo json_encode($result);
    exit();
}

// B. Verificación
elseif ($uri === '/api/verify' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $result = Auth::verify($input['email'] ?? '', $input['code'] ?? '');
    echo json_encode($result);
    exit();
}

// C. Login
elseif ($uri === '/api/login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $result = Auth::login($input['email'] ?? '', $input['password'] ?? '');

    if ($result['success']) {
        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['user_email'] = $result['user']['email'];
        
        echo json_encode([
            'success' => true,
            'token' => $result['token'], 
            'user' => $result['user']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    exit();
}

// D. GUARDAR NUEVO VUELO
elseif ($uri === '/api/flights' && $method === 'POST') {
    // LOG DE DEPURACIÓN CRÍTICO
    error_log("DEBUG: Entrando a /api/flights POST. Session ID: " . session_id());
    error_log("DEBUG: User_ID en sesión: " . ($_SESSION['user_id'] ?? 'NO SET'));

    if (!isset($_SESSION['user_id'])) {
        error_log("ERROR: Sesión no válida en /api/flights");
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado: Sesión expirada.']);
        exit();
    }
    $userId = $_SESSION['user_id'];

    $jsonInput = file_get_contents('php://input');
    $input = json_decode($jsonInput, true);

    if (!$input || !isset($input['reservation_code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
        exit();
    }

    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO flights (user_id, airline_name, reservation_code, flight_number, origin_airport, destination_airport, departure_time, arrival_time, seat_number, gate_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $userId,
            $input['airline_name'] ?? null,
            $input['reservation_code'],
            $input['flight_number'] ?? null,
            $input['origin_airport'] ?? null,
            $input['destination_airport'] ?? null,
            $input['departure_time'] ?? null,
            $input['arrival_time'] ?? null,
            $input['seat_number'] ?? null,
            $input['gate_info'] ?? null
        ]);

        echo json_encode(['success' => true, 'message' => 'Vuelo guardado', 'id' => $db->lastInsertId()]);

    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error DB.']);
    }
    exit();
}

// E. Obtener Vuelos
elseif ($uri === '/api/flights' && $method === 'GET') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit();
    }
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM flights WHERE user_id = ? ORDER BY departure_time ASC");
        $stmt->execute([$_SESSION['user_id']]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $flights]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener vuelos']);
    }
    exit();
}

// Servir Estáticos
if ($uri === '/' || $uri === '/index.php') {
    header("Content-Type: text/html; charset=UTF-8");
    $htmlFile = __DIR__ . '/assets/views/pwa.html';
    if (file_exists($htmlFile)) {
        echo file_get_contents($htmlFile);
    } else {
        http_response_code(500);
        echo "Error: HTML no encontrado.";
    }
    exit();
}

$filePath = __DIR__ . $uri;
if (strpos(realpath($filePath), realpath(__DIR__)) !== 0) {
    http_response_code(403);
    exit();
}

if (file_exists($filePath) && is_file($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'
    ];
    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
    header("Content-Type: $contentType");
    readfile($filePath);
    exit();
}

http_response_code(404);
echo json_encode(['error' => 'Recurso no encontrado', 'path' => $uri]);
?>