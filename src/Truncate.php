<?php

/**
 * Lognitor PHP SDK — Smart Truncation
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

final class Truncate
{
    private const DEFAULT_MAX_SIZE = 262144; // 256KB

    /** @param array<string, mixed> $log */
    public static function smartTruncate(array $log, int $maxSize = self::DEFAULT_MAX_SIZE): array
    {
        if (Utils::safeJsonSize($log) <= $maxSize) {
            return $log;
        }

        if (isset($log['error']['stack'])) {
            $lines = explode("\n", $log['error']['stack']);
            if (count($lines) > 16) {
                $top = array_slice($lines, 0, 11);
                $bottom = array_slice($lines, -3);
                $omitted = count($lines) - 13;
                $log['error']['stack'] = implode("\n", array_merge($top, ["  ... {$omitted} frames omitted ..."], $bottom));
            }
            if (Utils::safeJsonSize($log) <= $maxSize) {
                return $log;
            }
        }

        if (isset($log['breadcrumbs']) && count($log['breadcrumbs']) > 20) {
            $log['breadcrumbs'] = array_slice($log['breadcrumbs'], -20);
            if (Utils::safeJsonSize($log) <= $maxSize) {
                return $log;
            }
        }

        if (isset($log['metadata']) && is_array($log['metadata'])) {
            $log['metadata'] = self::truncateMeta($log['metadata'], 500);
            if (Utils::safeJsonSize($log) <= $maxSize) {
                return $log;
            }
        }

        if (isset($log['error']['pre_context']) && count($log['error']['pre_context']) > 3) {
            $log['error']['pre_context'] = array_slice($log['error']['pre_context'], -3);
        }
        if (isset($log['error']['post_context']) && count($log['error']['post_context']) > 3) {
            $log['error']['post_context'] = array_slice($log['error']['post_context'], 0, 3);
        }
        if (Utils::safeJsonSize($log) <= $maxSize) {
            return $log;
        }

        if (isset($log['message']) && strlen($log['message']) > 2000) {
            $log['message'] = substr($log['message'], 0, 2000) . ' [truncated]';
        }

        return $log;
    }

    /** @param array<string, mixed> $obj */
    private static function truncateMeta(array $obj, int $maxLen): array
    {
        $r = [];
        foreach ($obj as $k => $v) {
            if (is_string($v) && strlen($v) > $maxLen) {
                $r[$k] = substr($v, 0, $maxLen) . ' [truncated]';
            } elseif (is_array($v)) {
                $r[$k] = self::truncateMeta($v, $maxLen);
            } else {
                $r[$k] = $v;
            }
        }
        return $r;
    }
}
