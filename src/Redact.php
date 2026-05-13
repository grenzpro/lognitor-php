<?php

/**
 * Lognitor PHP SDK — Redaction
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

final class Redact
{
    /** @var array<string, string> */
    private const BUILTIN_PATTERNS = [
        'creditCard' => '/\b(?:\d[ -]*?){13,19}\b/',
        'email' => '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Z|a-z]{2,}\b/',
        'bearer' => '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/',
        'ssn' => '/\b\d{3}-\d{2}-\d{4}\b/',
    ];

    /** @param list<string> $patterns */
    public static function redactPayload(mixed $obj, array $patterns, string $path = ''): mixed
    {
        if (empty($patterns)) {
            return $obj;
        }
        $regexes = self::getRegexes($patterns);
        return self::deep($obj, $regexes, $path);
    }

    /** @return list<string> */
    private static function getRegexes(array $patterns): array
    {
        $result = [];
        foreach ($patterns as $p) {
            if (isset(self::BUILTIN_PATTERNS[$p])) {
                $result[] = self::BUILTIN_PATTERNS[$p];
            } elseif (is_string($p) && @preg_match($p, '') !== false) {
                $result[] = $p;
            }
        }
        return $result;
    }

    /** @param list<string> $regexes */
    private static function deep(mixed $obj, array $regexes, string $path): mixed
    {
        if (is_string($obj)) {
            if ($path === 'user.email') {
                return $obj;
            }
            foreach ($regexes as $rx) {
                $obj = preg_replace($rx, '[REDACTED]', $obj) ?? $obj;
            }
            return $obj;
        }
        if (is_array($obj)) {
            $result = [];
            $isList = $obj === [] || array_keys($obj) === range(0, count($obj) - 1);
            foreach ($obj as $k => $v) {
                $childPath = $isList ? "{$path}[{$k}]" : ($path ? "{$path}.{$k}" : (string)$k);
                $result[$k] = self::deep($v, $regexes, $childPath);
            }
            return $result;
        }
        return $obj;
    }
}
