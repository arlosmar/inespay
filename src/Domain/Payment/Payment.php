<?php

namespace App\Domain\Payment;

use Ramsey\Uuid\Uuid;

class Payment
{
    public function __construct(
        private float $amount,
        private string $status,
        private string $creditorAccount,
        private string $debtorAccount,
        private string $notificationId = ''
    ) {
        $this->notificationId = $notificationId ?: Uuid::uuid4()->toString();
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'status' => $this->status,
            'creditor_account' => $this->creditorAccount,
            'debtor_account' => $this->debtorAccount,
            'notification_id' => $this->notificationId,
        ];
    }

    public function getNotificationId()
    {
        return  $this->notificationId;
    }

    public function getAmount()
    {
        return  $this->amount;
    }

    public function getStatus()
    {
        return  $this->status;
    }

    public function getCreditorAccount()
    {
        return  $this->creditorAccount;
    }

    public function getDebtorAccount()
    {
        return  $this->debtorAccount;
    }
    
}
