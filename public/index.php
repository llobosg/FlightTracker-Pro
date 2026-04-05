<?php
// public/index.php

// 1. Configuración de Headers (CORS)
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

// Función auxiliar para validar token desde DB
function validateToken($token) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT user_id FROM user_sessions WHERE token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['user_id'] : null;
    } catch (Exception $e) {
        return null;
    }
}

// 3. Enrutamiento
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Normalizar URI
if (strpos($uri, '/public') === 0) {
    $uri = substr($uri, 7);
}
if ($uri === '') $uri = '/';

// --- RUTAS DE LA API ---

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

// C. Login (Guarda Token en DB)
elseif ($uri === '/api/login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $result = Auth::login($input['email'] ?? '', $input['password'] ?? '');

    if ($result['success']) {
        $userId = $result['user']['id'];
        $token = bin2hex(random_bytes(32));
        
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("INSERT INTO user_sessions (user_id, token) VALUES (?, ?)");
            $stmt->execute([$userId, $token]);
            // Limpiar tokens viejos
            $db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND token != ?")->execute([$userId, $token]);
        } catch (Exception $e) {
            error_log("Error guardando sesión: " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'token' => $token, 'user' => $result['user']]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    exit();
}

// D. Guardar Vuelo (POST)
elseif ($uri === '/api/flights' && $method === 'POST') {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    $userId = validateToken($token);

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['reservation_code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit();
    }

    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO flights (user_id, airline_name, reservation_code, flight_number, origin_airport, destination_airport, departure_time, arrival_time, seat_number, gate_info, status_api) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
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
            $input['gate_info'] ?? null,
            null // status_api inicial
        ]);

        echo json_encode(['success' => true, 'message' => 'Vuelo guardado', 'id' => $db->lastInsertId()]);
    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error DB']);
    }
    exit();
}

// D2. ACTUALIZAR VUELO EXISTENTE (PUT)
elseif ($uri === '/api/flights' && $method === 'PUT') {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    $userId = validateToken($token);

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $flightId = $input['id'] ?? null;

    if (!$flightId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de vuelo faltante']);
        exit();
    }

    try {
        $db = Database::getInstance();
        
        // Verificar que el vuelo pertenece al usuario
        $checkStmt = $db->prepare("SELECT id FROM flights WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$flightId, $userId]);
        if (!$checkStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Vuelo no encontrado o no pertenece a usted']);
            exit();
        }

        // Actualizar
        $stmt = $db->prepare("
            UPDATE flights SET 
                airline_name = ?, 
                flight_number = ?, 
                origin_airport = ?, 
                destination_airport = ?, 
                departure_time = ?, 
                arrival_time = ?, 
                seat_number = ?, 
                gate_info = ?,
                reservation_code = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['airline_name'] ?? null,
            $input['flight_number'] ?? null,
            $input['origin_airport'] ?? null,
            $input['destination_airport'] ?? null,
            $input['departure_time'],
            $input['arrival_time'],
            $input['seat_number'] ?? null,
            $input['gate_info'] ?? null,
            $input['reservation_code'],
            $flightId
        ]);

        echo json_encode(['success' => true, 'message' => 'Vuelo actualizado correctamente']);
    } catch (PDOException $e) {
        error_log("DB Update Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
    }
    exit();
}

// E. Obtener Vuelos (GET)
elseif ($uri === '/api/flights' && $method === 'GET') {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    $userId = validateToken($token);

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit();
    }
    
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM flights WHERE user_id = ? ORDER BY departure_time ASC");
        $stmt->execute([$userId]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $flights]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener vuelos']);
    }
    exit();
}

// F. ACTUALIZAR ESTADO DE VUELO (AviationStack)
elseif ($uri === '/api/update-flight-status' && $method === 'POST') {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    $userId = validateToken($token);

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $flightNumber = $input['flight_number'] ?? '';
    $flightId = $input['flight_id'] ?? '';

    if (!$flightNumber || !$flightId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan datos']);
        exit();
    }

    $apiKey = getenv('AVIATION_STACK_KEY');
    if (!$apiKey) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'API Key no configurada']);
        exit();
    }

    $url = "https://api.aviationstack.com/v1/flights?access_key={$apiKey}&flight_iata={$flightNumber}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("AviationStack Error: $response");
        echo json_encode(['success' => false, 'message' => 'Error consultando aerolínea']);
        exit();
    }

    $data = json_decode($response, true);
    
    if (isset($data['data']) && count($data['data']) > 0) {
        $flightInfo = $data['data'][0]; 
        
        $status = $flightInfo['flight_status'] ?? 'unknown';
        $gate = $flightInfo['arrival']['gate'] ?? $flightInfo['departure']['gate'] ?? null;
        $delay = $flightInfo['arrival']['delay'] ?? $flightInfo['departure']['delay'] ?? 0;
        $actualDeparture = $flightInfo['departure']['actual'] ?? null;
        $estimatedArrival = $flightInfo['arrival']['estimated'] ?? null;
        $isFlying = isset($flightInfo['live']) && !$flightInfo['live']['is_ground'];
        $altitude = isset($flightInfo['live']) ? $flightInfo['live']['altitude'] : null;

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE flights SET gate_info = ?, status_api = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$gate, $status, $flightId, $userId]);

            echo json_encode([
                'success' => true, 
                'message' => 'Estado actualizado',
                'live_data' => [
                    'gate' => $gate,
                    'status' => $status,
                    'delay' => $delay,
                    'actual_departure' => $actualDeparture,
                    'estimated_arrival' => $estimatedArrival,
                    'is_flying' => $isFlying,
                    'altitude' => $altitude,
                    'aircraft_type' => $flightInfo['aircraft']['iata'] ?? null
                ]
            ]);
        } catch (PDOException $e) {
            error_log("DB Update Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error al guardar en BD']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Vuelo no encontrado en API']);
    }
    exit();
}

// G. GUARDAR PREFERENCIAS DE NOTIFICACIÓN
elseif ($uri === '/api/save-preferences' && $method === 'POST') {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    $userId = validateToken($token);

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO user_preferences (user_id, email_alerts, push_notifications, whatsapp_reminder, whatsapp_number) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                email_alerts = VALUES(email_alerts),
                push_notifications = VALUES(push_notifications),
                whatsapp_reminder = VALUES(whatsapp_reminder),
                whatsapp_number = VALUES(whatsapp_number)
        ");
        
        $stmt->execute([
            $userId,
            $input['email_alerts'] ?? 0,
            $input['push_notifications'] ?? 0,
            $input['whatsapp_reminder'] ?? 0,
            $input['whatsapp_number'] ?? null
        ]);

        echo json_encode(['success' => true, 'message' => 'Preferencias guardadas']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error DB']);
    }
    exit();
}

// H. CHECK DAILY NOTIFICATIONS (Para Cron-job.org)
elseif ($uri === '/api/check-daily-notifications' && $method === 'GET') {
    $secretKey = $_GET['key'] ?? '';
    if ($secretKey !== getenv('CRON_SECRET_KEY')) {
        http_response_code(403);
        echo "Acceso denegado";
        exit();
    }

    try {
        $db = Database::getInstance();
        $today = date('Y-m-d');
        
        $stmt = $db->prepare("
            SELECT f.*, u.email, u.name, up.email_alerts, up.whatsapp_reminder, up.whatsapp_number
            FROM flights f
            JOIN users u ON f.user_id = u.id
            LEFT JOIN user_preferences up ON f.user_id = up.user_id
            WHERE DATE(f.departure_time) = ? 
            AND f.status_api != 'landed'
            AND (up.email_alerts IS NULL OR up.email_alerts = 1)
        ");
        $stmt->execute([$today]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sentCount = 0;
        foreach ($flights as $flight) {
            $time = date('H:i', strtotime($flight['departure_time']));
            $subject = "✈️ Recordatorio: Tu vuelo {$flight['flight_number']} hoy a las {$time}";
            $body = "Hola {$flight['name']},\n\nTu vuelo {$flight['flight_number']} ({$flight['origin_airport']} → {$flight['destination_airport']}) sale hoy a las {$time}.\n\nPuerta: " . ($flight['gate_info'] ?? 'Por confirmar') . "\n\n¡Buen viaje!";
            
            error_log("EMAIL SIMULADO a {$flight['email']}: $subject");
            $sentCount++;
        }

        echo json_encode(['success' => true, 'processed' => count($flights), 'emails_sent' => $sentCount]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// I. OBTENER DATOS PARA PUSH NOTIFICATION AL ABRIR APP
elseif ($uri === '/api/get-today-alerts' && $method === 'GET') {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    $userId = validateToken($token);

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit();
    }

    try {
        $db = Database::getInstance();
        $today = date('Y-m-d');
        
        $stmt = $db->prepare("
            SELECT f.flight_number, f.origin_airport, f.destination_airport, f.departure_time, f.gate_info, f.status_api, up.push_notifications
            FROM flights f
            LEFT JOIN user_preferences up ON f.user_id = up.user_id
            WHERE f.user_id = ? AND DATE(f.departure_time) = ?
        ");
        $stmt->execute([$userId, $today]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $alerts = [];
        foreach ($flights as $f) {
            if ($f['push_notifications'] !== 0) {
                $time = date('H:i', strtotime($f['departure_time']));
                $msg = "Vuelo {$f['flight_number']} ($f[origin_airport] → $f[destination_airport]) hoy a las $time.";
                if ($f['gate_info']) $msg .= " Puerta: $f[gate_info]";
                
                $alerts[] = [
                    'title' => 'FlightTracker Pro',
                    'body' => $msg,
                    'icon' => '/assets/images/icons/icon-192x192.png'
                ];
            }
        }

        echo json_encode(['success' => true, 'alerts' => $alerts]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error']);
    }
    exit();
}

// J. BUSCAR VUELO POR NÚMERO (Para autocompletado frontend)
elseif ($uri === '/api/search-flight' && $method === 'GET') {
    $flightNum = $_GET['number'] ?? '';
    $apiKey = getenv('AVIATION_STACK_KEY');
    
    if (!$flightNum || !$apiKey) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
        exit();
    }

    // Consultar AviationStack
    $url = "https://api.aviationstack.com/v1/flights?access_key={$apiKey}&flight_iata={$flightNum}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'FlightTracker-Pro/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("AviationStack Error ($httpCode): $response");
        echo json_encode(['success' => false, 'message' => 'Error consultando API externa']);
        exit();
    }

    $data = json_decode($response, true);
    
    if (isset($data['data']) && count($data['data']) > 0) {
        $f = $data['data'][0];
        
        // Extraer y formatear datos útiles
        $result = [
            'success' => true,
            'flight' => [
                'airline' => $f['airline']['name'] ?? null,
                'flight_number' => $f['flight']['iata'] ?? $flightNum,
                'origin' => $f['departure']['iata'] ?? null,
                'destination' => $f['arrival']['iata'] ?? null,
                'scheduled_departure' => $f['departure']['scheduled'] ?? null,
                'scheduled_arrival' => $f['arrival']['scheduled'] ?? null,
                'terminal_departure' => $f['departure']['terminal'] ?? null,
                'terminal_arrival' => $f['arrival']['terminal'] ?? null,
            ]
        ];
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vuelo no encontrado en base de datos de aerolíneas']);
    }
    exit();
}

// --- SERVIDOR DE ESTÁTICOS ---
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