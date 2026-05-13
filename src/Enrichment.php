<?php

/**
 * Lognitor PHP SDK — Enrichment
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

final class Enrichment
{
    public const SDK_NAME = 'lognitor/lognitor';
    public const SDK_VERSION = '1.0.0';

    private static ?string $cachedHostname = null;

    public static function getHostname(): string
    {
        if (self::$cachedHostname === null) {
            self::$cachedHostname = gethostname() ?: 'unknown';
        }
        return self::$cachedHostname;
    }

    /** @return array{name: string, version: string} */
    public static function getSdkMetadata(): array
    {
        return ['name' => self::SDK_NAME, 'version' => self::SDK_VERSION];
    }
}
