<?php

use PHPUnit\Framework\TestCase;
use App\Domain\Payment\Payment;
use App\Infrastructure\Http\HttpNotificationSender;
use App\Infrastructure\Jwt\JwtSigner;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class HttpNotificationSenderTest extends TestCase
{
    private Payment $payment;

    protected function setUp(): void
    {
        $this->payment = new Payment(100.0, 'completed', 'ACC123', 'ACC456');
    }

    // Verifica que se hace 1 solo intento y se llama a logger->info() en un envío exitoso.
    public function testSuccessfulNotificationLogsInfo()
    {
        $guzzleMock = $this->createMock(Client::class);
        $guzzleMock->expects($this->once())->method('post');

        $signer = new JwtSigner('secret');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   $this->equalTo('Notificación enviada exitosamente'),
                   $this->arrayHasKey('amount') // cualquier payload
               );

        $sender = new HttpNotificationSender('http://test.local', $signer, $logger);
        $this->setPrivateProperty($sender, 'client', $guzzleMock);

        $sender->send($this->payment);
    }

    // Fuerza que el post() falle siempre, y verifica que se intente 3 veces, logueando un warning cada vez.
    public function testRetriesOnFailureAndLogsWarnings()
    {
        $exception = new RequestException("Fallo", new Request('POST', 'test'));

        $guzzleMock = $this->getMockBuilder(Client::class)
            ->onlyMethods(['post'])
            ->getMock();

        $guzzleMock->expects($this->exactly(3))
                   ->method('post')
                   ->willThrowException($exception);

        $signer = new JwtSigner('secret');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))
               ->method('warning')
               ->with($this->stringContains('Error al enviar notificación'));

        $sender = new HttpNotificationSender('http://test.local', $signer, $logger);
        $this->setPrivateProperty($sender, 'client', $guzzleMock);

        $this->expectException(\RuntimeException::class);
        $sender->send($this->payment);
    }

    /**
     * Helper para inyectar el mock de Guzzle como propiedad privada.
     */
    private function setPrivateProperty($object, string $property, $value): void
    {
        $ref = new \ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
