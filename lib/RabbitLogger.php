<?php
/**
 * RabbitLogger — publish JSON log events to RabbitMQ.
 * Uses the [responseServer] section of testRabbitMQ_response.ini.
 */
require_once __DIR__ . '/../rabbitMQLib.inc';

class RabbitLogger
{
    private static $client = null;

    /** get (or create) a singleton rabbitMQClient */
    private static function client(): rabbitMQClient
    {
        if (self::$client === null) {
            $ini = __DIR__ . '/../testRabbitMQ_response.ini';
            self::$client = new rabbitMQClient($ini, 'responseServer');
        }
        return self::$client;
    }

    /** publish a structured log line */
    public static function log(string $level, string $message): void
    {
        $payload = [
            'ts'   => date('c'),
            'host' => gethostname(),
            'lvl'  => $level,
            'msg'  => $message,
        ];
        self::client()->publish($payload);
    }
}
