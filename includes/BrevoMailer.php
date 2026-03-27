<?php
// includes/BrevoMailer.php
class BrevoMailer {
    private $apiKey;
    private $toEmail;
    private $toName;
    private $subject;
    private $htmlBody;

    public function __construct() {
        $this->apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : getenv('BREVO_API_KEY');
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

    public function send() {
        $data = [
            'sender' => ['name' => 'NegocioUP', 'email' => 'llobos@gltcomex.com'],
            'to' => [['email' => $this->toEmail, 'name' => $this->toName]],
            'subject' => $this->subject,
            'htmlContent' => $this->htmlBody
        ];

        $ch = curl_init();
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
            error_log("❌ Error Brevo: $response");
            throw new Exception('Error al enviar correo');
        }

        return true;
    }
}
?>