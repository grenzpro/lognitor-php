<?php

/**
 * Lognitor PHP SDK — PSR-3 Compatible Logger
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

/**
 * Bundled PSR-3 LoggerInterface to avoid requiring psr/log as a dependency.
 */
interface LoggerInterface
{
    public function emergency(string|\Stringable $message, array $context = []): void;
    public function alert(string|\Stringable $message, array $context = []): void;
    public function critical(string|\Stringable $message, array $context = []): void;
    public function error(string|\Stringable $message, array $context = []): void;
    public function warning(string|\Stringable $message, array $context = []): void;
    public function notice(string|\Stringable $message, array $context = []): void;
    public function info(string|\Stringable $message, array $context = []): void;
    public function debug(string|\Stringable $message, array $context = []): void;
    public function log(mixed $level, string|\Stringable $message, array $context = []): void;
}

final class PsrLogger implements LoggerInterface
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? Lognitor::getInstance();
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->client->fatal((string)$message, $this->buildOptions($context));
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->client->fatal((string)$message, $this->buildOptions($context));
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->client->fatal((string)$message, $this->buildOptions($context));
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->client->error((string)$message, $this->buildOptions($context));
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->client->warn((string)$message, $this->buildOptions($context));
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->client->info((string)$message, $this->buildOptions($context));
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->client->info((string)$message, $this->buildOptions($context));
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->client->debug((string)$message, $this->buildOptions($context));
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $mapped = match ((string)$level) {
            'emergency', 'alert', 'critical' => 'fatal',
            'error' => 'error',
            'warning' => 'warn',
            'notice', 'info' => 'info',
            'debug' => 'debug',
            default => 'info',
        };
        $this->client->log($mapped, (string)$message, $this->buildOptions($context));
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function buildOptions(array $context): array
    {
        $options = [];
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $options['error'] = $context['exception'];
            unset($context['exception']);
        }
        if (!empty($context)) {
            $options['metadata'] = $context;
        }
        return $options;
    }
}
