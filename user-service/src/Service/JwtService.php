<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $algorithm = 'HS256';

    public function __construct(string $jwtSecret)
    {
        $this->secret = $jwtSecret;
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

    public function getUserIdFromToken(string $token): ?int
    {
        $payload = $this->validateToken($token);
        return $payload['userId'] ?? null;
    }

    public function getEmailFromToken(string $token): ?string
    {
        $payload = $this->validateToken($token);
        return $payload['email'] ?? null;
    }
}
