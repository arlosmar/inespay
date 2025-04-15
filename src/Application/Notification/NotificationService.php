<?php

namespace App\Application\Notification;

use App\Domain\Payment\Payment;
use App\Infrastructure\Http\HttpNotificationSender;
use App\Domain\Payment\PaymentRepositoryInterface;

class NotificationService
{
    public function __construct(
        private HttpNotificationSender $sender,
        private PaymentRepositoryInterface $repository
    ) {
    }

    public function notify(Payment $payment): void
    {
        $this->sender->send($payment);
    }
}
