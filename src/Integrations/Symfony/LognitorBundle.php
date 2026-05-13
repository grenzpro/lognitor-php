<?php

/**
 * Lognitor PHP SDK — Symfony Bundle
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor\Integrations\Symfony;

use Lognitor\Client;
use Lognitor\Lognitor;

class LognitorBundle
{
    /** @param array<string, mixed> $config */
    public static function createClient(array $config): Client
    {
        return Lognitor::init($config);
    }

    /**
     * Symfony kernel.request listener for auto-logging.
     * Register in services.yaml:
     *   Lognitor\Integrations\Symfony\LognitorBundle:
     *     tags:
     *       - { name: kernel.event_listener, event: kernel.request, method: onKernelRequest }
     *       - { name: kernel.event_listener, event: kernel.response, method: onKernelResponse }
     *       - { name: kernel.event_listener, event: kernel.exception, method: onKernelException }
     */

    private float $startTime = 0;
    private string $requestId = '';

    public function onKernelRequest(mixed $event): void
    {
        try {
            $request = $event->getRequest();
            $this->startTime = microtime(true);
            $this->requestId = \Lognitor\Utils::uuid();

            Lognitor::addBreadcrumb(
                'http', 'request',
                "{$request->getMethod()} {$request->getPathInfo()}",
                'info',
            );
        } catch (\Throwable $e) {}
    }

    public function onKernelResponse(mixed $event): void
    {
        try {
            $request = $event->getRequest();
            $response = $event->getResponse();
            $duration = (microtime(true) - $this->startTime) * 1000;
            $status = $response->getStatusCode();
            $path = $request->getPathInfo();

            $level = $status >= 500 ? 'error' : ($status >= 400 ? 'warn' : 'info');
            $durationStr = sprintf('%.0f', $duration);
            Lognitor::log($level, "{$request->getMethod()} {$path} {$status} {$durationStr}ms", [
                'request' => [
                    'method' => $request->getMethod(), 'path' => $path,
                    'status_code' => $status, 'duration_ms' => $duration,
                ],
                'request_id' => $this->requestId,
            ]);
        } catch (\Throwable $e) {}
    }

    public function onKernelException(mixed $event): void
    {
        try {
            Lognitor::captureException($event->getThrowable(), ['request_id' => $this->requestId]);
        } catch (\Throwable $e) {}
    }
}
