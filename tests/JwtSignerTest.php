<?php

// ejemplo unitario de firma

// tests/JwtSignerTest.php
use PHPUnit\Framework\TestCase;
use App\Infrastructure\Jwt\JwtSigner;

class JwtSignerTest extends TestCase
{
    public function testSignAndVerify()
    {
        $secret = 'test-secret';
        $payload = ['foo' => 'bar'];
        $signer = new JwtSigner($secret);

        $token = $signer->sign($payload);
        $decoded = $signer->verify($token);

        $this->assertEquals($payload, $decoded);
    }
}
