<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Payment\Payment;
use App\Domain\Payment\PaymentRepositoryInterface;
use PDO;

class SQLitePaymentRepository implements PaymentRepositoryInterface
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = new PDO("sqlite:$dbPath");
        $this->initialize();
    }

    private function initialize(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS payments (
                notification_id TEXT PRIMARY KEY,
                amount REAL,
                status TEXT,
                creditor_account TEXT,
                debtor_account TEXT
            )
        ");
    }

    public function save(Payment $payment): void
	{
	    try {
	        $stmt = $this->pdo->prepare(
	            'INSERT INTO payments (notification_id, amount, status, creditor_account, debtor_account)
	             VALUES (:id, :amount, :status, :creditor, :debtor)'
	        );
            
            $paymentArray = $payment->toArray();                

            $stmt->execute([
                'id' => $paymentArray['notification_id'],
                'amount' => $paymentArray['amount'],
                'status' => $paymentArray['status'],
                'creditor' => $paymentArray['creditor_account'],
                'debtor' => $paymentArray['debtor_account']
            ]);
            /*
	        $stmt->execute([
	            'id' => $payment->getNotificationId(),
	            'amount' => $payment->getAmount(),
	            'status' => $payment->getStatus(),
	            'creditor' => $payment->getCreditorAccount(),
	            'debtor' => $payment->getDebtorAccount(),
	        ]);
            */
	    } catch (\PDOException $e) {
	        if (str_contains($e->getMessage(), 'UNIQUE')) {
	            throw new \RuntimeException("Ya existe un pago con notification_id: " . $payment->getNotificationId());
	        }
	        throw $e;
	    }
	}

    public function findByNotificationId(string $notificationId): ?Payment
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE notification_id = :id");
        $stmt->execute(['id' => $notificationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        return new Payment(
            (float) $row['amount'],
            $row['status'],
            $row['creditor_account'],
            $row['debtor_account'],
            $row['notification_id']
        );
    }

    public function exists(string $notificationId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM payments WHERE notification_id = :id");
        $stmt->execute(['id' => $notificationId]);
        return $stmt->fetchColumn() > 0;
    }
}
