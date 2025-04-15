<?php

// src/Infrastructure/Jwt/JwtSigner.php
namespace App\Infrastructure\Jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtSigner
{
    public function __construct(private string $secret)
    {
    }

    public function sign(array $payload): string
    {
        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function verify(string $token): array
    {
        return (array) JWT::decode($token, new Key($this->secret, 'HS256'));
    }
}
