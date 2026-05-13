<?php

/**
 * Lognitor PHP SDK — Fingerprint
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

final class Fingerprint
{
    /** @param array<string, mixed>|null $error */
    public static function generate(?array $error, ?string $stack = null): string
    {
        $type = $error['type'] ?? 'Error';
        $msg = Utils::normalizeMessage($error['message'] ?? '');
        $frame = Utils::getTopStackFrame($stack ?? ($error['stack'] ?? ''));
        return Utils::sha256("{$type}|{$msg}|{$frame}");
    }
}
