<?php

use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;

class NotifyEndpointTest extends TestCase
{
    private string $url = 'http://localhost:8000/notify';
    private string $secret = 'super-secret';
    private string $dbPath = __DIR__ . '/../database.sqlite';
    private string $logPath = __DIR__ . '/../logs/notifications.log';

    // limpiar el fichero sqlite y los logs entre pruebas
    protected function setUp(): void
    {

        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }

        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function test_valid_notification_returns_200()
    {
        $data = $this->generatePayment();
        $response = $this->post($data, $this->sign($data));

        $this->assertEquals(200, $response['status']);
        $this->assertStringContainsString('Notification received', $response['body']);
    }

    public function test_invalid_signature_returns_401()
    {
        $data = $this->generatePayment();
        $fakeToken = $this->sign($data, 'wrong-secret');
        $response = $this->post($data, $fakeToken);

        $this->assertEquals(401, $response['status']);
        $this->assertStringContainsString('Invalid signature', $response['body']);
    }

    public function test_json_malformed_returns_400()
    {
        $curl = curl_init($this->url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => ['Signature: dummy', 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => '{"invalid_json}',
        ]);

        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $this->assertEquals(400, $status);
        $this->assertStringContainsString('Invalid JSON', $body);
    }

    public function test_signature_payload_mismatch_returns_401()
    {
        $real = $this->generatePayment();
        $fake = $this->generatePayment();
        $token = $this->sign($real); // firma válida, pero para otro cuerpo

        $response = $this->post($fake, $token);
        $this->assertEquals(401, $response['status']);
        $this->assertStringContainsString('Signature mismatch', $response['body']);
    }

    public function test_duplicate_notification_returns_409()
    {
        $data = $this->generatePayment();
        $sig = $this->sign($data);

        $this->post($data, $sig); // primero OK
        $response = $this->post($data, $sig); // segundo: duplicado

        $this->assertEquals(409, $response['status']);
        $this->assertStringContainsString('Duplicate notification_id', $response['body']);
    }

    private function sign(array $payload, string $secretOverride = null): string
    {
        return JWT::encode($payload, $secretOverride ?? $this->secret, 'HS256');
    }

    private function post(array $data, string $signature): array
    {
        $curl = curl_init($this->url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Signature: ' . $signature,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);

        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'status' => $status,
            'body' => $body,
        ];
    }

    private function generatePayment(): array
    {
        return [
            'amount' => 150,
            'status' => 'ok',
            'creditor_account' => 'ACC123',
            'debtor_account' => 'ACC999',
            'notification_id' => $this->uuid(),
        ];
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
