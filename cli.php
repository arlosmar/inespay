<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Domain\Payment\Payment;
use App\Infrastructure\Jwt\JwtSigner;
use App\Infrastructure\Http\HttpNotificationSender;
use App\Infrastructure\Logger\FileLoggerFactory;
use App\Infrastructure\Persistence\SQLitePaymentRepository;
use App\Application\Notification\NotificationService;

// listar los pagos realizados y que están guardados en la base de datos
function listPayments(string $dbPath): void
{
    $pdo = new PDO("sqlite:$dbPath");
    $stmt = $pdo->query("SELECT * FROM payments");

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($payments) === 0) {
        echo "No hay pagos registrados.\n";
    } else {
        echo "Pagos registrados:\n";
        foreach ($payments as $payment) {
            echo "ID: {$payment['notification_id']}, Monto: {$payment['amount']}, Estado: {$payment['status']}, Acreedor: {$payment['creditor_account']}, Deudor: {$payment['debtor_account']}\n";
        }
    }
}


// Configuración
$secret = 'super-secret';
$endpoint = 'http://localhost:8000/notify';
$dbPath = __DIR__ . '/database.sqlite';
$logPath = __DIR__ . '/logs/notifications.log';

// Inyecciones
$signer = new JwtSigner($secret);
$logger = FileLoggerFactory::create($logPath);
$repository = new SQLitePaymentRepository($dbPath);
$sender = new HttpNotificationSender($endpoint, $signer, $logger);

// Parsear CLI
$options = getopt('', [
    'amount:',
    'status:',
    'creditor:',
    'debtor:',
    'id::',        // opcional
    'dry-run',     // flag
    'list'         // listar payments
]);

// php cli.php --list
/*
Pagos registrados:
ID: unique-id-1, Monto: 100, Estado: completed, Acreedor: ACC123, Deudor: ACC456
ID: unique-id-2, Monto: 50, Estado: pending, Acreedor: ACC789, Deudor: ACC123
*/
if (isset($options['list'])) {
    // Listar pagos
    listPayments($dbPath);
    exit(0);
}

if (!isset($options['amount'], $options['status'], $options['creditor'], $options['debtor'])) {
    echo "\nUso: php cli.php\n\n";
    echo "1) Listar pagos realizados:\n";
    echo "   php cli.php --list\n\n";
    echo "2) Generar pago:\n";
    echo "   php cli.php --amount=100 --status=completed --creditor=ACC123 --debtor=ACC456 [--dry-run]\n\n";
    echo "   [--dry-run] sirve para que no haga requests HTTP reales. Evita hacer la solicitud HTTP, pero sigue generando y mostrando el payload, firmándolo y guardándolo en la base de datos\n\n";
    //php cli.php --amount=75 --status=pending --creditor=AAA --debtor=BBB --id=my-custom-id --dry-run
    exit(1);
}

// Crear Payment
$payment = new Payment(
    (float) $options['amount'],
    $options['status'],
    $options['creditor'],
    $options['debtor'],
    $options['id'] ?? ''
);

// Dry run: mostrar payload y firma sin enviar
// vamos a modificar cli.php para que acepte el flag --dry-run y evite hacer la solicitud HTTP, pero siga generando y mostrando el payload, firmándolo y guardándolo en la base de datos.
/*
php cli.php --amount=200 --status=completed --creditor=ACC100 --debtor=ACC200 --dry-run

salida esperada:
[DRY RUN] Payload:
{
    "amount": 200,
    "status": "completed",
    "creditor_account": "ACC100",
    "debtor_account": "ACC200",
    "notification_id": "..."
}
[DRY RUN] Signature:
eyJ0eXAiOiJKV1QiLCJh...
[DRY RUN] No se realizó ninguna solicitud HTTP.
*/
if (isset($options['dry-run'])) {
    $payload = $payment->toArray();
    $signature = $signer->sign($payload);
    $repository->save($payment);

    echo "[DRY RUN] Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    echo "[DRY RUN] Signature:\n$signature\n";
    echo "[DRY RUN] No se realizó ninguna solicitud HTTP.\n";
    exit(0);
}

// envío con el sistema de notificaciones
try {
    $service = new NotificationService($sender, $repository);
    $service->notify($payment);
    echo "Notificación enviada: " . $payment->toArray()['notification_id'] . "\n";
} catch (\Throwable $e) {
    $logger->error('Fallo en CLI', ['error' => $e->getMessage()]);
    echo "Error: " . $e->getMessage() . "\n";
}
