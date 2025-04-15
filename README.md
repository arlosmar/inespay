# inespay

## Para arrancar el servidor de notificationes
**php -S localhost:8000 -t public**

## Para ejecutar por CLI:
### 1) Listar pagos realizados:
   **php cli.php --list**

### 2) Generar pago:
   **php cli.php --amount=100 --status=completed --creditor=ACC123 --debtor=ACC456 [--dry-run] [--id=XXX]**

- [--dry-run] sirve para que no haga requests HTTP reales. Evita hacer la solicitud HTTP, pero sigue generando y mostrando el payload, firmándolo y guardándolo en la base de datos

- [--id=XXX] sirve para guardar el pago con un ID concreto. Si ese ID está duplicado se lanza un error indicándolo.

## Para realizar pruebas:
**vendor/bin/phpunit tests**

### Para realizar una prueba concreta:
vendor/bin/phpunit tests/NotificationReceiverTest.php

## Para probar el sistema de notificaciones directamente por CLI:
### 1) Crear fichero json con los datos del pago:
Por ejemplo `bin/payment.json`:

**{
  "amount": 100,
  "status": "completed",
  "creditor_account": "A",
  "debtor_account": "B",
  "notification_id": "123e4567-e89b-12d3-a456-426614174000"
}**

### 2) Ejecutar:
**php bin/generate-signature.php --file=bin/payment.json**

Y obtenemos:

**Signature JWT:
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...**

### 3) Finalmente para probar:
**curl -X POST http://localhost:8000/notify \
  -H "Content-Type: application/json" \
  -H "Signature: `<VALOR-OBTENIDO-ARRIBA>`" \
  -d @bin/payment.json**

### 4) Obtenemos el resultado:
**{"status":"Notification received"}**