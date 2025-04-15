<?php

use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;

class NotificationReceiverTest extends TestCase
{
    private string $url = 'http://localhost:8000/notify';
    private string $secret = 'super-secret';

    // limpiar el fichero sqlite y los logs entre pruebas
    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/../../database.sqlite';
        $this->logPath = __DIR__ . '/../../logs/notifications.log';

        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }

        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    private function post(array $data, string $overrideToken = null): array
    {
        $json = json_encode($data);
        $token = $overrideToken ?? JWT::encode($data, $this->secret, 'HS256');

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Signature: ' . $token,
            ],
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $response];
    }

    public function testSuccessfulNotification()
    {
        $data = [
            'amount' => 200,
            'status' => 'approved',
            'creditor_account' => 'ACC1',
            'debtor_account' => 'ACC2',
            'notification_id' => uniqid('notif_', true)
        ];

        $result = $this->post($data);
        $this->assertEquals(200, $result['status']);
        $this->assertStringContainsString('Notification received', $result['body']);
    }

    public function testInvalidJson()
    {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{"invalid_json"', // mal formado
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Signature: dummy'
            ],
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(400, $status);
        $this->assertStringContainsString('Invalid JSON', $response);
    }

    public function testInvalidSignature()
    {
        $data = [
            'amount' => 100,
            'status' => 'denied',
            'creditor_account' => 'A',
            'debtor_account' => 'B',
            'notification_id' => uniqid('notif_', true)
        ];

        $invalidToken = JWT::encode(['tampered' => true], $this->secret, 'HS256');
        $result = $this->post($data, $invalidToken);

        $this->assertEquals(401, $result['status']);
        $this->assertStringContainsString('Signature mismatch', $result['body']);
    }

    public function testDuplicateNotificationId()
    {
        $notificationId = uniqid('notif_', true);
        $data = [
            'amount' => 150,
            'status' => 'ok',
            'creditor_account' => 'ACC1',
            'debtor_account' => 'ACC2',
            'notification_id' => $notificationId
        ];

        // Primer envío válido
        $first = $this->post($data);
        $this->assertEquals(200, $first['status']);

        // Segundo con mismo ID → debe fallar
        $second = $this->post($data);
        $this->assertEquals(409, $second['status']);
        $this->assertStringContainsString('Duplicate', $second['body']);
    }
}
