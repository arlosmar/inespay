<?php

// src/Infrastructure/Http/HttpNotificationSender.php
namespace App\Infrastructure\Http;

use GuzzleHttp\Client;
use App\Domain\Payment\Payment;
use App\Infrastructure\Jwt\JwtSigner;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\RequestException;

class HttpNotificationSender
{
    private Client $client;

    public function __construct(
        private string $endpoint,
        private JwtSigner $signer,
        private LoggerInterface $logger
    ) {
        $this->client = new Client();
    }

    // 3 attemps. the sleep between attemps is exponential
    public function send(Payment $payment): void
    {
        $data = $payment->toArray();
        $signature = $this->signer->sign($data);

        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                $response = $this->client->post($this->endpoint, [
                    'json' => $data,
                    'headers' => ['Signature' => $signature],
                    'timeout' => 5,
                ]);

                $this->logger->info("Notificación enviada exitosamente", $data);
                return;
            }
            catch (RequestException $e) {

                $error = $e->getMessage();

                $attempts++;

                $this->logger->warning("Error al enviar notificación. Intento $attempts", [
                    'exception' => $error,
                    'payment' => $data
                ]);

                if ($attempts >= $maxAttempts) {
                    throw new \RuntimeException("Falló el envío de la notificación tras $attempts intentos. Motivo: ".$error);
                }

                sleep(pow(2, $attempts));
            }
        }
    }
}
