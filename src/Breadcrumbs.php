<?php

/**
 * Lognitor PHP SDK — Breadcrumbs
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

final class Breadcrumbs
{
    /** @var list<array<string, mixed>> */
    private array $buffer;
    private int $maxSize;
    private int $pos = 0;
    private int $count = 0;

    public function __construct(int $maxSize = 100)
    {
        $this->maxSize = $maxSize;
        $this->buffer = array_fill(0, $maxSize, null);
    }

    /** @param array<string, mixed> $data */
    public function add(
        string $type = 'default',
        string $category = 'custom',
        string $message = '',
        string $level = 'info',
        ?array $data = null,
        ?string $timestamp = null,
    ): void {
        $crumb = [
            'type' => $type,
            'category' => $category,
            'message' => $message,
            'level' => $level,
            'timestamp' => $timestamp ?? Utils::isoNow(),
        ];
        if ($data !== null) {
            $crumb['data'] = $data;
        }
        $this->buffer[$this->pos % $this->maxSize] = $crumb;
        $this->pos++;
        if ($this->count < $this->maxSize) {
            $this->count++;
        }
    }

    /** @return list<array<string, mixed>> */
    public function getAll(): array
    {
        if ($this->count === 0) {
            return [];
        }
        if ($this->count < $this->maxSize) {
            return array_values(array_filter(array_slice($this->buffer, 0, $this->count)));
        }
        $start = $this->pos % $this->maxSize;
        $result = array_merge(
            array_slice($this->buffer, $start),
            array_slice($this->buffer, 0, $start)
        );
        return array_values(array_filter($result));
    }

    public function clear(): void
    {
        $this->pos = 0;
        $this->count = 0;
    }
}
