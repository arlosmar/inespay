<?php

namespace App\Infrastructure\Http;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Infrastructure\Persistence\SQLitePaymentRepository;
use App\Domain\Payment\Payment;
use App\Infrastructure\Logger\FileLoggerFactory;
use PDO;

class NotificationReceiver
{
    private string $secret;
    private SQLitePaymentRepository $repository;
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(string $secret = 'super-secret')
    {
        $this->secret = $secret;

        // db y logger
        $dbPath = __DIR__ . '/../../../database.sqlite';
		$logPath = __DIR__ . '/../../../logs/notifications.log';
       
        $this->repository = new SQLitePaymentRepository($dbPath);
        
        $this->logger = FileLoggerFactory::create($logPath);
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_SERVER['REQUEST_URI'] !== '/notify') {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found']);
            return;
        }

        $signature = $_SERVER['HTTP_SIGNATURE'] ?? '';
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            $this->logger->warning('Invalid JSON received');
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        try {        	
            $decoded = JWT::decode($signature, new Key($this->secret, 'HS256'));
            $payload = (array) $decoded;

            if ($payload != $data) {
                http_response_code(401);
                $this->logger->warning('Signature mismatch', ['payload' => $payload, 'data' => $data]);
                echo json_encode(['error' => 'Signature mismatch']);
                return;
            }

            // Verificar si ya existe el notification_id
            /*
            $existing = $this->repository->findByNotificationId($data['notification_id']);

	        if ($existing) {
	            http_response_code(409);
                $this->logger->info("Duplicate notification_id: {$data['notification_id']}");
                echo json_encode(['error' => 'Duplicate notification_id']);
                return;
	        }
            */

            if ($this->repository->exists($data['notification_id'])) {            
                http_response_code(409);
                $this->logger->info("Duplicate notification_id: {$data['notification_id']}");
                echo json_encode(['error' => 'Duplicate notification_id']);
                return;
            }

            // Crear entidad y guardar
            $payment = new Payment(
                $data['amount'],
                $data['status'],
                $data['creditor_account'],
                $data['debtor_account'],
                $data['notification_id']
            );

            $this->repository->save($payment);
            $this->logger->info("Notification saved", ['id' => $payment->getNotificationId()]);

            http_response_code(200);
            echo json_encode(['status' => 'Notification received']);

        } catch (\Exception $e) {
            http_response_code(401);
            $this->logger->error('Invalid signature', ['exception' => $e->getMessage()]);
            echo json_encode(['error' => 'Invalid signature']);
        }
    }
}
