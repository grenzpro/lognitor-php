<?php

/**
 * Lognitor PHP SDK — Utilities
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

final class Utils
{
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function isoNow(): string
    {
        return gmdate('Y-m-d\TH:i:s.') . sprintf('%03d', (int)(microtime(true) * 1000) % 1000) . 'Z';
    }

    public static function sha256(string $input): string
    {
        return hash('sha256', $input);
    }

    public static function normalizeMessage(string $message): string
    {
        $result = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '<UUID>', $message) ?? $message;
        $result = preg_replace('/\b0x[0-9a-fA-F]+\b/', '<HEX>', $result) ?? $result;
        $result = preg_replace('/\bhttps?:\/\/\S+/', '<URL>', $result) ?? $result;
        $result = preg_replace('/\b\d+\b/', '<N>', $result) ?? $result;
        return $result;
    }

    public static function getTopStackFrame(string $stack): string
    {
        $lines = explode("\n", $stack);
        foreach (array_slice($lines, 1) as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, 'at ')) {
                return $trimmed;
            }
        }
        return '';
    }

    private const SCRUB_PARAMS = [
        'token', 'password', 'secret', 'key', 'authorization', 'session',
        'credit_card', 'api_key', 'apiKey', 'access_token', 'refresh_token',
    ];

    /** @param list<string> $extraParams */
    public static function scrubUrl(string $url, array $extraParams = []): string
    {
        $params = array_merge(self::SCRUB_PARAMS, $extraParams);
        $parsed = parse_url($url);
        if (!isset($parsed['query'])) {
            return $url;
        }
        parse_str($parsed['query'], $qs);
        $changed = false;
        foreach ($qs as $key => $value) {
            if (in_array($key, $params, true) || in_array(strtolower($key), $params, true)) {
                $qs[$key] = '[FILTERED]';
                $changed = true;
            }
        }
        if (!$changed) {
            return $url;
        }
        $parsed['query'] = http_build_query($qs);
        return self::unparseUrl($parsed);
    }

    /** @param array<string, mixed> $parsed */
    private static function unparseUrl(array $parsed): string
    {
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        return $scheme . $host . $port . $path . $query . $fragment;
    }

    public static function jitteredBackoff(int $attempt): float
    {
        $base = pow(2, $attempt) * 1.0;
        $jitter = $base * 0.25 * (mt_rand() / mt_getrandmax() * 2 - 1);
        return max(0.1, $base + $jitter);
    }

    public static function safeJsonEncode(mixed $data): string
    {
        $result = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $result !== false ? $result : '{}';
    }

    public static function safeJsonSize(mixed $data): int
    {
        return strlen(self::safeJsonEncode($data));
    }

    /** @param list<string> $skipPatterns */
    public static function getCallerInfo(array $skipPatterns = []): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach (array_slice($trace, 2) as $frame) {
            $file = $frame['file'] ?? '';
            $skip = false;
            foreach ($skipPatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            if ($file) {
                return $file . ':' . ($frame['line'] ?? 0);
            }
        }
        return null;
    }
}
