<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Http\NotificationReceiver;

header('Content-Type: application/json');

$receiver = new NotificationReceiver();
$receiver->handle();
