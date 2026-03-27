<?php
// includes/Auth.php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/BrevoMailer.php';

class Auth {
    
    public static function register($name, $email, $password) {
        $db = Database::getInstance();
        
        // 1. Verificar si existe
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'El correo ya está registrado'];
        }

        // 2. Crear usuario
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (full_name, email, password_hash, is_verified) VALUES (?, ?, ?, 0)");
        $stmt->execute([$name, $email, $hash]);
        $userId = $db->lastInsertId();

        // 3. Generar código
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $stmt = $db->prepare("INSERT INTO verification_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $code, $expires]);

        // 4. Enviar Email
        try {
            $mailer = new BrevoMailer();
            $html = "<h1>Bienvenido a FlightTracker</h1><p>Hola $name,</p><p>Tu código es: <b style='font-size:20px;color:#3b82f6'>$code</b></p><p>Expira en 15 min.</p>";
            $mailer->setTo($email, $name)
                   ->setSubject('Tu código de verificación')
                   ->setHtmlBody($html)
                   ->send();
            
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            error_log($e->getMessage());
            // Rollback opcional: borrar usuario si falla email
            return ['success' => false, 'message' => 'Usuario creado pero falló el envío de email. Contacta soporte.'];
        }
    }

    public static function verify($email, $code) {
        $db = Database::getInstance();
        
        // Obtener usuario
        $stmt = $db->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) return ['success' => false, 'message' => 'Usuario no encontrado'];
        if ($user['is_verified']) return ['success' => false, 'message' => 'Usuario ya verificado'];

        // Verificar código
        $stmt = $db->prepare("SELECT id FROM verification_codes WHERE user_id = ? AND code = ? AND expires_at > NOW() AND is_used = 0");
        $stmt->execute([$user['id'], $code]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['success' => false, 'message' => 'Código inválido o expirado'];
        }

        // Activar usuario
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$user['id']]);
            $db->prepare("UPDATE verification_codes SET is_used = 1 WHERE id = ?")->execute([$record['id']]);
            $db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Error al verificar'];
        }
    }
    
    public static function login($email, $password) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Credenciales inválidas'];
        }
        
        if (!$user['is_verified']) {
            return ['success' => false, 'message' => 'Debes verificar tu correo primero'];
        }

        // Generar Token Simple (En prod usar JWT library)
        $token = bin2hex(random_bytes(32)); 
        // Guardar token en BD (tabla sessions) o usar JWT real. 
        // Para MVP, devolvemos el token y el frontend lo guarda.
        
        return [
            'success' => true, 
            'token' => $token, 
            'user' => [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'email' => $user['email']
            ]
        ];
    }
}