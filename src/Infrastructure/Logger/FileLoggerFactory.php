<?php

// src/Infrastructure/Logger/FileLoggerFactory.php
namespace App\Infrastructure\Logger;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class FileLoggerFactory
{
    public static function create(string $logPath): Logger
    {
        $logger = new Logger('notifications');
        $logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
        return $logger;
    }
}
