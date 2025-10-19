<?php

namespace ParcelTrack\Helpers;

class Logger
{
    public const DEBUG = 'DEBUG';
    public const INFO  = 'INFO';
    public const ERROR = 'ERROR';

    private const LOG_TARGET = 'php://stdout';
    private string $logLevel;
    private array $logLevels = [
        self::DEBUG => 0,
        self::INFO  => 1,
        self::ERROR => 2,
    ];

    public function __construct(string $logLevel = self::INFO)
    {
        $this->logLevel = strtoupper($logLevel);
    }

    public function log(string $message, string $level = self::INFO): void
    {
        if (!isset($this->logLevels[$level]) || $this->logLevels[$level] < $this->logLevels[$this->logLevel]) {
            return;
        }

        $date       = date('Y-m-d H:i:s');
        $logMessage = "[{$date}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents(self::LOG_TARGET, $logMessage, FILE_APPEND);
    }
}
