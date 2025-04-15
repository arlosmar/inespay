<?php

// src/Domain/Payment/PaymentRepositoryInterface.php
namespace App\Domain\Payment;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): void;

    public function findByNotificationId(string $notificationId): ?Payment;
}