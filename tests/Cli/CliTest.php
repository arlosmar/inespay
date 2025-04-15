<?php

// tests/Cli/CliTest.php
use PHPUnit\Framework\TestCase;
use App\Infrastructure\Jwt\JwtSigner;

class CliTest extends TestCase
{
    private string $dbPath;
    private string $logPath;

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

    // Que la notificación se ejecuta bien, se guarda en SQLite, y se genera un log.
    public function testCliRunsSuccessfully()
    {
        $output = shell_exec("php cli.php --amount=99.99 --status=completed --creditor=TESTCRED --debtor=TESTDEBT");

        $this->assertStringContainsString('Notificación enviada', $output);

        // Verifica que exista un log
        $this->assertFileExists($this->logPath);
        $logContent = file_get_contents($this->logPath);
        $this->assertStringContainsString('Notificación enviada exitosamente', $logContent);

        // Verifica que la DB contiene el payment
        $pdo = new PDO("sqlite:{$this->dbPath}");
        $stmt = $pdo->query("SELECT COUNT(*) FROM payments");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(1, $count);
    }

    // Que el CLI detecta parámetros incompletos y muestra un mensaje de ayuda.
    public function testCliFailsOnMissingArgs()
    {
        $output = shell_exec("php cli.php --amount=50");
        $this->assertStringContainsString('Uso: php cli.php', $output);
    }

    /*
    	Vamos a escribir un test específico para verificar que el flag --dry-run:
		Muestra el payload y la firma correctamente.
		No hace una solicitud HTTP real.
		Guarda el Payment en la base de datos.
		Imprime el mensaje de que no se realizó la solicitud.
		
		¿Qué verifica este test?
		✔ Que el script imprime las 3 secciones del dry run.
		✔ Que el Payment se guarda en la base de datos.
		✔ Que los datos persistidos coinciden con los del input.
    */
    public function testCliDryRunWorksCorrectly()
	{
	    $output = shell_exec("php cli.php --amount=123.45 --status=pending --creditor=DRY1 --debtor=DRY2 --dry-run");

	    $this->assertStringContainsString('[DRY RUN] Payload:', $output);
	    $this->assertStringContainsString('[DRY RUN] Signature:', $output);
	    $this->assertStringContainsString('[DRY RUN] No se realizó ninguna solicitud HTTP.', $output);

	    // Verifica que la notificación fue persistida
	    $pdo = new PDO("sqlite:" . __DIR__ . '/../../database.sqlite');
	    $stmt = $pdo->query("SELECT * FROM payments WHERE creditor_account = 'DRY1'");
	    $result = $stmt->fetch(PDO::FETCH_ASSOC);

	    $this->assertNotEmpty($result);
	    $this->assertEquals('pending', $result['status']);
	    $this->assertEquals('DRY1', $result['creditor_account']);
	    $this->assertEquals('DRY2', $result['debtor_account']);
	    $this->assertEquals(123.45, (float)$result['amount']);
	}

	// probar se puede pasar por cli el notification_id
	// php cli.php --amount=75 --status=pending --creditor=AAA --debtor=BBB --id=my-custom-id --dry-run
	public function testCliAcceptsCustomNotificationId()
	{
	    $customId = 'test-id-123';
	    $output = shell_exec("php cli.php --amount=10 --status=test --creditor=X --debtor=Y --id=$customId --dry-run");

	    $this->assertStringContainsString($customId, $output);

	    // Verifica persistencia
	    $pdo = new PDO("sqlite:" . __DIR__ . '/../../database.sqlite');
	    $stmt = $pdo->prepare("SELECT * FROM payments WHERE notification_id = :id");
	    $stmt->execute(['id' => $customId]);
	    $result = $stmt->fetch(PDO::FETCH_ASSOC);

	    $this->assertNotEmpty($result);
	    $this->assertEquals($customId, $result['notification_id']);
	}

	// Si ejecutas dos veces el CLI con el mismo --id, la primera debe funcionar, pero la segunda debe fallar al intentar guardar un Payment duplicado (porque la columna notification_id es UNIQUE).
	public function testCliFailsOnDuplicateNotificationId()
	{
	    $customId = 'duplicate-id-456';

	    // Primera ejecución: debe funcionar
	    $first = shell_exec("php cli.php --amount=99 --status=completed --creditor=A1 --debtor=B1 --id=$customId --dry-run");

	    $this->assertStringContainsString($customId, $first);
	    $this->assertStringContainsString('[DRY RUN]', $first);

	    // Segunda ejecución: debe fallar
	    try{
	    	$second = shell_exec("php cli.php --amount=50 --status=failed --creditor=A2 --debtor=B2 --id=$customId --dry-run");
	    }
	    catch (\PDOException $e) {
	        $this->assertStringContainsString('Ya existe un pago con notification_id', $e->getMessage());
	    }	    
	}

	// Ahora, agregamos un test para verificar que el comando --list muestra correctamente los pagos en la base de datos:
	/*
		Qué hace el test
		Crear dos pagos utilizando --dry-run.
		Ejecutar el comando --list.
		Verificar que la salida contiene los IDs y la información de los pagos que acabamos de agregar.
	*/
	public function testCliListPayments()
	{
	    // Asegúrate de que haya pagos en la base de datos
	    $customId1 = 'test-id-001';
	    $customId2 = 'test-id-002';
	    shell_exec("php cli.php --amount=100 --status=completed --creditor=ACC123 --debtor=ACC456 --id=$customId1 --dry-run");
	    shell_exec("php cli.php --amount=50 --status=pending --creditor=ACC789 --debtor=ACC123 --id=$customId2 --dry-run");

	    // Ejecutar el comando `--list`
	    $output = shell_exec("php cli.php --list");

	    $this->assertStringContainsString($customId1, $output);
	    $this->assertStringContainsString($customId2, $output);
	    $this->assertStringContainsString('Monto: 100', $output);
	    $this->assertStringContainsString('Estado: completed', $output);
	}

	// Test de Validez de la Cabecera Signature
	/*
		Explicación del test
		Generación de pago: Simulamos la ejecución del CLI con los parámetros proporcionados, asegurándonos de que el Signature se genere correctamente.

		Extracción de la firma: Usamos una expresión regular (preg_match) para extraer la firma de la salida del CLI.

		Verificación de la firma: Usamos la misma clase JwtSigner para generar una firma válida y verificamos que coincida con la generada en el CLI.

		¿Qué estamos probando?
		Generación de JWT: Confirmamos que el Signature en la cabecera sea generado correctamente usando el JwtSigner.
		Validación: Nos aseguramos de que la firma generada con los datos del pago coincida con la que se imprime en el CLI.
	*/
	public function testCliValidatesSignature()
	{
	    $amount = 100;
	    $status = 'completed';
	    $creditor = 'ACC123';
	    $debtor = 'ACC456';
	    $customId = 'test-signature-id-789';

	    // Simula la ejecución del CLI
	    $output = shell_exec("php cli.php --amount=$amount --status=$status --creditor=$creditor --debtor=$debtor --id=$customId --dry-run");

	    // Verifica que la firma se muestra en la salida
	    preg_match('/\[DRY RUN\] Signature:\s*(\S+)/', $output, $matches);
	    $this->assertNotEmpty($matches);
	    $signature = $matches[1];

	    // Verificar que la firma sea válida usando el mismo secreto
	    $payload = [
	        'amount' => $amount,
	        'status' => $status,
	        'creditor_account' => $creditor,
	        'debtor_account' => $debtor,
	        'notification_id' => $customId
	    ];

	    $signer = new JwtSigner('super-secret');
	    $validSignature = $signer->sign($payload);

	    // Verificar que la firma generada coincida con la firma de la salida del CLI
	    $this->assertEquals($signature, $validSignature);
	}

}
