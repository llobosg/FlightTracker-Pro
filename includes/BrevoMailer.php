<?php
// includes/BrevoMailer.php

class BrevoMailer {
    private $apiKey;
    private $toEmail;
    private $toName;
    private $subject;
    private $htmlBody;
    private $fromEmail;
    private $fromName;

    public function __construct() {
        $this->apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : getenv('BREVO_API_KEY');
        $this->fromEmail = defined('BREVO_FROM_MAIL') ? BREVO_FROM_MAIL : 'no-reply@flighttracker.app';
        $this->fromName = 'FlightTracker Pro';
        
        if (!$this->apiKey) {
            throw new Exception('BREVO_API_KEY no configurada');
        }
    }

    public function setTo($email, $name = '') {
        $this->toEmail = $email;
        $this->toName = $name ?: $email;
        return $this;
    }

    public function setHtmlBody($html) {
        $this->htmlBody = $html;
        return $this;
    }

    public function setSubject($subject) {
        $this->subject = $subject;
        return $this;
    }
    
    public function setSender($email, $name = 'FlightTracker Pro') {
        $this->fromEmail = $email;
        $this->fromName = $name;
        return $this;
    }

    public function send() {
        $data = [
            'sender' => ['name' => $this->fromName, 'email' => $this->fromEmail],
            'to' => [['email' => $this->toEmail, 'name' => $this->toName]],
            'subject' => $this->subject,
            'htmlContent' => $this->htmlBody
        ];

        $ch = curl_init();
        // Nota: Eliminé espacios extra en la URL que había en el original por seguridad
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.brevo.com/v3/smtp/email',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $this->apiKey,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            error_log("❌ Error Brevo HTTP $httpCode: $response");
            throw new Exception('Error al enviar correo: ' . $httpCode);
        }

        return true;
    }
}