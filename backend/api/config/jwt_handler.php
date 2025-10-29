<?php
require_once '../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHandler {
    protected $secret = 'YOUR_SUPER_SECRET_KEY'; // CHANGE THIS to a long, random string
    protected $issuer = 'http://localhost';
    protected $audience = 'http://localhost';
    protected $issuedAt;
    protected $expire;

    public function __construct() {
        $this->issuedAt = time();
        $this->expire = $this->issuedAt + 3600; // Token valid for 1 hour
    }

    public function encode($data) {
        $token = [
            "iss" => $this->issuer,
            "aud" => $this->audience,
            "iat" => $this->issuedAt,
            "exp" => $this->expire,
            "data" => $data,
        ];
        return JWT::encode($token, $this->secret, 'HS256');
    }

    public function decode($jwt) {
        try {
            $decoded = JWT::decode($jwt, new Key($this->secret, 'HS256'));
            return $decoded->data;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getUserId() {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            list($jwt) = sscanf($authHeader, 'Bearer %s');
            if ($jwt) {
                $data = $this->decode($jwt);
                return $data->user_id ?? null;
            }
        }
        return null;
    }
}
?>