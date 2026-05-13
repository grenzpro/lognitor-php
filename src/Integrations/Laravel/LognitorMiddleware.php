<?php

/**
 * Lognitor PHP SDK — Laravel Middleware
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor\Integrations\Laravel;

use Lognitor\Lognitor;
use Lognitor\Utils;

class LognitorMiddleware
{
    /** @var list<string> */
    private array $ignoreRoutes = [];
    private bool $captureUser = false;
    private bool $configLoaded = false;

    /** @var list<array<string, mixed>> */
    private static array $requestLogBuffer = [];
    /** @var array<string, bool> */
    private static array $seenRequestIds = [];
    private static bool $timerRegistered = false;

    private function loadConfig(): void
    {
        if ($this->configLoaded) {
            return;
        }
        $this->configLoaded = true;
        if (function_exists('config')) {
            $this->ignoreRoutes = config('lognitor.ignore_routes', ['/health', '/ready']) ?? [];
            $this->captureUser = (bool)(config('lognitor.capture_user', false));
        }

        // Register shutdown flush once
        if (!self::$timerRegistered) {
            self::$timerRegistered = true;
            register_shutdown_function([self::class, 'flushRequestLogs']);
        }
    }

    public static function flushRequestLogs(): void
    {
        if (empty(self::$requestLogBuffer)) {
            return;
        }
        try {
            $entries = self::$requestLogBuffer;
            self::$requestLogBuffer = [];
            self::$seenRequestIds = [];
            Lognitor::sendRequestLogs($entries);
        } catch (\Throwable $e) {
            // swallow
        }
    }

    public function handle(mixed $request, \Closure $next): mixed
    {
        $this->loadConfig();

        $path = Utils::scrubUrl($request->path());
        foreach ($this->ignoreRoutes as $ignored) {
            if (str_starts_with($path, ltrim($ignored, '/'))) {
                return $next($request);
            }
        }

        $requestId = Utils::uuid();
        $startTime = microtime(true);
        $method = $request->method();

        if ($this->captureUser && $request->user()) {
            try {
                Lognitor::setUser([
                    'id' => (string)$request->user()->getAuthIdentifier(),
                    'email' => $request->user()->email ?? null,
                    'name' => $request->user()->name ?? null,
                ]);
            } catch (\Throwable $e) {
                // swallow
            }
        }

        Lognitor::addBreadcrumb('http', 'request', "{$method} /{$path}", 'info', [
            'method' => $method, 'path' => $path, 'request_id' => $requestId,
        ]);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            Lognitor::captureException($e, ['request_id' => $requestId]);
            throw $e;
        }

        $duration = (microtime(true) - $startTime) * 1000;
        $status = $response->getStatusCode();

        // Send to /ingest/requests
        if (!isset(self::$seenRequestIds[$requestId])) {
            self::$seenRequestIds[$requestId] = true;

            $config = Lognitor::getInstance()->getConfig();
            self::$requestLogBuffer[] = [
                'method' => $method,
                'path' => "/{$path}",
                'status_code' => $status,
                'duration_ms' => $duration,
                'request_id' => $requestId,
                'route' => $request->route()?->uri() ?? "/{$path}",
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'host' => $request->getHost(),
                'scheme' => $request->getScheme(),
                'runtime' => 'php',
                'runtime_version' => PHP_VERSION,
                'timestamp' => Utils::isoNow(),
                'environment' => $config->environment ?? null,
                'service' => $config->service ?? null,
                'version' => $config->version ?? null,
            ];

            if (count(self::$requestLogBuffer) >= 25) {
                self::flushRequestLogs();
            }
        }

        return $response;
    }
}
