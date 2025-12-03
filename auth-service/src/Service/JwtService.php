<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $algorithm = 'HS256';
    private int $expirationTime = 3600; // 1 hour

    public function __construct(string $jwtSecret)
    {
        $this->secret = $jwtSecret;
    }

    public function generateToken(int $userId, string $email): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->expirationTime;

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'userId' => $userId,
            'email' => $email,
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }
}
