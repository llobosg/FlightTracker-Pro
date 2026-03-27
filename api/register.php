<?php
// api/register.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

// Función para generar código de 6 dígitos
function generateCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Función para enviar email con Brevo
function sendVerificationEmail($email, $code, $name) {
    $apiKey = getenv('BREVO_API_KEY'); // Tu API Key de Brevo
    $url = 'https://api.brevo.com/v3/smtp/email';

    $data = [
        "sender" => ["name" => "FlightTracker Pro", "email" => "no-reply@tu-dominio.com"],
        "to" => [["email" => $email, "name" => $name]],
        "subject" => "Tu código de verificación - FlightTracker",
        "htmlContent" => "<html><body><h1>Bienvenido a FlightTracker</h1><p>Hola $name,</p><p>Tu código de verificación es: <strong style='font-size: 24px; color: #007bff;'>$code</strong></p><p>Este código expira en 15 minutos.</p></body></html>"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'api-key: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 201; // 201 Created es éxito en Brevo
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $password = $data['password'];
    $name = filter_var($data['name'], FILTER_SANITIZE_STRING);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["message" => "Email inválido."]);
        exit();
    }

    try {
        // 1. Verificar si el usuario ya existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(["message" => "El usuario ya está registrado."]);
            exit();
        }

        // 2. Hash de contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // 3. Insertar usuario (is_verified = 0)
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, is_verified) VALUES (?, ?, ?, 0)");
        $stmt->execute([$email, $passwordHash, $name]);
        $userId = $pdo->lastInsertId();

        // 4. Generar y guardar código de verificación
        $code = generateCode();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $stmtCode = $pdo->prepare("INSERT INTO verification_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
        $stmtCode->execute([$userId, $code, $expiresAt]);

        // 5. Enviar Email con Brevo
        $emailSent = sendVerificationEmail($email, $code, $name);

        if ($emailSent) {
            http_response_code(201);
            echo json_encode([
                "message" => "Usuario registrado. Por favor revisa tu correo.",
                "user_id" => $userId
            ]);
        } else {
            // Rollback manual o manejo de error si falla el email
            http_response_code(500);
            echo json_encode(["message" => "Error al enviar el correo de verificación."]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error del servidor: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["message" => "Método no permitido."]);
}
?>