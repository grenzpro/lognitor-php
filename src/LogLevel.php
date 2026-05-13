<?php
declare(strict_types=1);
namespace Lognitor;

final class LogLevel
{
    public const DEBUG = 0;
    public const INFO = 1;
    public const WARN = 2;
    public const ERROR = 3;
    public const FATAL = 4;

    /** @var array<string, int> */
    public const HIERARCHY = [
        'debug' => self::DEBUG,
        'info' => self::INFO,
        'warn' => self::WARN,
        'error' => self::ERROR,
        'fatal' => self::FATAL,
    ];

    public static function isValid(string $level): bool
    {
        return isset(self::HIERARCHY[$level]);
    }
}
