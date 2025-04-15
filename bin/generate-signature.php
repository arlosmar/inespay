<?php

//#!/usr/bin/env php

/*
cómo usarlo. fichero payment.json

{
  "amount": 100,
  "status": "completed",
  "creditor_account": "A",
  "debtor_account": "B",
  "notification_id": "123e4567-e89b-12d3-a456-426614174000"
}

✅ Signature JWT:
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...

ejecutar:
php bin/generate-signature.php --file=bin/payment.json

y luego para probar:

curl -X POST http://localhost:8000/notify \
  -H "Content-Type: application/json" \
  -H "Signature: <lo-generado-arriba>" \
  -d @bin/payment.json
*/

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;

$options = getopt('', ['file:', 'secret::']);
$inputFile = $options['file'] ?? null;
$secret = $options['secret'] ?? 'super-secret';

if (!$inputFile || !file_exists($inputFile)) {
    echo "Debes pasar un archivo JSON con --file=archivo.json\n";
    exit(1);
}

$json = file_get_contents($inputFile);
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON inválido en el archivo: {$inputFile}\n";
    exit(1);
}

$jwt = JWT::encode($data, $secret, 'HS256');

echo "Signature JWT:\n";
echo $jwt . "\n";
