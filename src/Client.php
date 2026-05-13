<?php

/**
 * Lognitor PHP SDK — Client
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

class Client
{
    private Config $config;
    private BatchBuffer $batchBuffer;
    private Breadcrumbs $breadcrumbs;
    /** @var array<string, mixed>|null */
    private ?array $user = null;
    /** @var array<string, mixed> */
    private array $globalContext = [];
    /** @var list<string> */
    private array $globalTags = [];
    private ?string $sessionId = null;
    private ?string $releaseId;
    /** @var array<string, string> */
    private array $deployCtx = [];
    private bool $initialized = false;
    /** @var list<array<string, mixed>> */
    private array $preInitBuffer = [];
    /** @var array<string, float>|null */
    private ?array $sampleRates = null;
    /** @var array<string, array{count: int, time: float}> */
    private array $dedupMap = [];
    /** @var list<IntegrationInterface> */
    private array $integrations = [];
    private bool $authWarned = false;
    private bool $isChild = false;
    private ?BatchBuffer $parentBuffer = null;
    /** @var array<string, mixed> */
    private array $childOverrides = [];

    /** @param array<string, mixed> $options */
    public function __construct(array $options = [])
    {
        $this->config = new Config($options);
        $this->breadcrumbs = new Breadcrumbs($this->config->maxBreadcrumbs);
        $this->releaseId = $this->config->releaseId;

        $transport = $this->config->transport ?? new CurlTransport();
        $this->batchBuffer = new BatchBuffer(
            $this->config,
            $transport,
            fn(string $msg) => $this->debugLog($msg),
            fn(array $rates) => $this->sampleRates = $rates,
            fn() => $this->onAuthError(),
        );

        if (!empty($options)) {
            $this->finishInit();
        }
    }

    private function finishInit(): void
    {
        if ($this->initialized) {
            return;
        }
        if (!$this->config->apiKey) {
            $this->consoleWarn('Lognitor: No API key provided. Logs will not be sent.');
            return;
        }

        $this->initialized = true;
        $keyPreview = substr($this->config->apiKey, 0, 8) . '***';
        $this->debugLog("Initialized service={$this->config->service}, apiKey={$keyPreview}");

        if (!$this->isChild) {
            register_shutdown_function([$this, 'shutdownFlush']);
            $this->installGlobalErrorHandlers();
        }

        foreach ($this->config->integrations as $integration) {
            $this->addIntegration($integration);
        }

        // Flush pre-init buffer
        if (!empty($this->preInitBuffer)) {
            $this->debugLog('Flushing ' . count($this->preInitBuffer) . ' pre-init logs');
            $buf = $this->preInitBuffer;
            $this->preInitBuffer = [];
            foreach ($buf as $entry) {
                $this->log($entry['level'], $entry['message'], $entry['options'] ?? []);
            }
        }
    }

    private function installGlobalErrorHandlers(): void
    {
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            try {
                $level = match (true) {
                    ($errno & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) !== 0 => 'fatal',
                    ($errno & (E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING)) !== 0 => 'warn',
                    ($errno & (E_NOTICE | E_USER_NOTICE | E_STRICT)) !== 0 => 'info',
                    ($errno & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0 => 'debug',
                    default => 'error',
                };
                $this->log($level, $errstr, [
                    'error' => [
                        'type' => 'PHPError',
                        'message' => $errstr,
                        'filename' => $errfile,
                        'line_number' => $errline,
                    ],
                ]);
            } catch (\Throwable $e) {
                // swallow
            }
            return false; // Let PHP handle it too
        });

        set_exception_handler(function (\Throwable $exception): void {
            try {
                $this->captureException($exception);
                $this->batchBuffer->flush();
            } catch (\Throwable $e) {
                // swallow
            }
        });
    }

    private function onAuthError(): void
    {
        if (!$this->authWarned) {
            $this->authWarned = true;
            $this->consoleWarn('Lognitor: Invalid API key. Logs will not be sent.');
        }
    }

    public function shutdownFlush(): void
    {
        try {
            // Capture fatal errors that set_error_handler can't catch
            $lastError = error_get_last();
            if ($lastError !== null && ($lastError['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE))) {
                $this->fatal($lastError['message'], [
                    'error' => [
                        'type' => 'PHPFatal',
                        'message' => $lastError['message'],
                        'filename' => $lastError['file'],
                        'line_number' => $lastError['line'],
                    ],
                ]);
            }

            // Send dedup summary logs for suppressed duplicates
            foreach ($this->dedupMap as $fp => $entry) {
                if ($entry['count'] > 0) {
                    $this->log('error', 'Deduplicated error summary', [
                        'fingerprint' => $fp,
                        'metadata' => ['dedupCount' => $entry['count']],
                    ]);
                }
            }
            $this->dedupMap = [];

            $stalls = 0;
            while ($this->batchBuffer->length() > 0) {
                $before = $this->batchBuffer->length();
                $this->batchBuffer->flush();
                if ($this->batchBuffer->length() >= $before) {
                    if (++$stalls > 3) break;
                } else {
                    $stalls = 0;
                }
            }
        } catch (\Throwable $e) {
            // swallow
        }
    }

    // --- Logging ---

    /** @param array<string, mixed> $options */
    public function log(string $level, string $message, array $options = []): string
    {
        try {
            return $this->logInternal($level, $message, $options);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @param array<string, mixed> $options */
    public function debug(string $message, array $options = []): string { return $this->log('debug', $message, $options); }
    /** @param array<string, mixed> $options */
    public function info(string $message, array $options = []): string { return $this->log('info', $message, $options); }
    /** @param array<string, mixed> $options */
    public function warn(string $message, array $options = []): string { return $this->log('warn', $message, $options); }
    /** @param array<string, mixed> $options */
    public function error(string $message, array $options = []): string { return $this->log('error', $message, $options); }
    /** @param array<string, mixed> $options */
    public function fatal(string $message, array $options = []): string { return $this->log('fatal', $message, $options); }

    /** @param array<string, mixed> $options */
    private function logInternal(string $level, string $message, array $options): string
    {
        if (!LogLevel::isValid($level)) {
            $this->consoleWarn("Lognitor: Invalid log level \"{$level}\"");
            return '';
        }
        if (!$this->config->enabled) {
            return '';
        }
        if (!$this->initialized) {
            $id = Utils::uuid();
            $this->preInitBuffer[] = ['level' => $level, 'message' => $message, 'options' => $options];
            return $id;
        }

        // Min level
        if ($this->config->minLevel !== null && isset(LogLevel::HIERARCHY[$this->config->minLevel])) {
            if (LogLevel::HIERARCHY[$level] < LogLevel::HIERARCHY[$this->config->minLevel]) {
                return '';
            }
        }

        // Sampling
        if ($this->sampleRates !== null) {
            $rate = $this->sampleRates[$level] ?? null;
            if ($rate !== null && $rate < 1 && mt_rand() / mt_getrandmax() > $rate) {
                return '';
            }
        }

        $logId = Utils::uuid();

        // Build error context
        $errorCtx = $options['error'] ?? null;
        if ($errorCtx instanceof \Throwable) {
            $errorCtx = $this->throwableToContext($errorCtx);
        }

        $payload = [
            'level' => $level,
            'message' => $message,
            'timestamp' => Utils::isoNow(),
            '_sdk' => Enrichment::getSdkMetadata(),
            'hostname' => Enrichment::getHostname(),
        ];

        // Auto-enrichment
        $svc = $this->childOverrides['service'] ?? $this->config->service;
        $env = $this->childOverrides['environment'] ?? $this->config->environment;
        $ver = $this->childOverrides['version'] ?? $this->config->version;
        if ($svc) $payload['service'] = $svc;
        if ($env) $payload['environment'] = $env;
        if ($ver) $payload['version'] = $ver;
        if ($this->releaseId) $payload['release_id'] = $this->releaseId;
        if (!empty($this->deployCtx)) $payload['deploy'] = $this->deployCtx;

        if ($this->user) {
            $payload['user'] = $this->user;
            $payload['user_id'] = $this->user['id'] ?? null;
        }
        if ($this->sessionId) $payload['session_id'] = $this->sessionId;
        if (!empty($this->globalContext)) $payload['metadata'] = $this->globalContext;
        if (!empty($this->globalTags)) $payload['tags'] = $this->globalTags;

        // Child overrides
        if (!empty($this->childOverrides['metadata'])) {
            $payload['metadata'] = array_merge($payload['metadata'] ?? [], $this->childOverrides['metadata']);
        }
        if (!empty($this->childOverrides['tags'])) {
            $payload['tags'] = $this->childOverrides['tags'];
        }

        // Per-log options
        foreach (['metadata', 'tags', 'user', 'request', 'perf', 'deploy', 'trace', 'action',
                   'fingerprint', 'session_id', 'release_id', 'request_id', 'breadcrumbs',
                   'notify', 'notify_channels', 'source', 'device'] as $key) {
            if (isset($options[$key])) {
                if ($key === 'metadata') {
                    $payload['metadata'] = array_merge($payload['metadata'] ?? [], $options[$key]);
                } elseif ($key === 'tags') {
                    $payload['tags'] = array_merge($payload['tags'] ?? [], $options[$key]);
                } elseif ($key === 'deploy') {
                    $payload['deploy'] = array_merge($payload['deploy'] ?? [], $options[$key]);
                } elseif ($key === 'trace') {
                    $payload['trace'] = array_merge($payload['trace'] ?? [], $options[$key]);
                } elseif ($key === 'user') {
                    $payload['user'] = $options[$key];
                    if (isset($options[$key]['id'])) {
                        $payload['user_id'] = $options[$key]['id'];
                    }
                } else {
                    $payload[$key] = $options[$key];
                }
            }
        }

        if ($errorCtx) $payload['error'] = $errorCtx;

        // Caller info
        if ($this->config->captureCallerInfo && !isset($payload['source'])) {
            $source = Utils::getCallerInfo(['lognitor/src/', 'Lognitor.php', 'Client.php']);
            if ($source) $payload['source'] = $source;
        }

        // Breadcrumbs for error/fatal
        if (in_array($level, ['error', 'fatal'], true)) {
            if (!isset($payload['breadcrumbs'])) {
                $payload['breadcrumbs'] = $this->breadcrumbs->getAll();
            }
            if (!isset($payload['fingerprint']) && isset($payload['error']) && is_array($payload['error'])) {
                $payload['fingerprint'] = Fingerprint::generate($payload['error'], $payload['error']['stack'] ?? null);
            }
        }

        // URL scrubbing
        if (isset($payload['request']['url'])) {
            $payload['request']['url'] = Utils::scrubUrl($payload['request']['url'], $this->config->scrubUrlParams);
        }
        if (isset($payload['request']['path'])) {
            $payload['request']['path'] = Utils::scrubUrl($payload['request']['path'], $this->config->scrubUrlParams);
        }
        if (!empty($payload['breadcrumbs']) && is_array($payload['breadcrumbs'])) {
            foreach ($payload['breadcrumbs'] as &$bc) {
                if (isset($bc['data']['url']) && is_string($bc['data']['url'])) {
                    $bc['data']['url'] = Utils::scrubUrl($bc['data']['url'], $this->config->scrubUrlParams);
                }
            }
            unset($bc);
        }

        // Redaction
        if (!empty($this->config->redactPatterns)) {
            $payload = Redact::redactPayload($payload, $this->config->redactPatterns);
        }

        // Per-log beforeSend
        if (isset($options['before_send']) && is_callable($options['before_send'])) {
            $result = ($options['before_send'])($payload);
            if ($result === null) return '';
            $payload = $result;
        }

        // Global beforeSend
        if ($this->config->beforeSend !== null) {
            $result = ($this->config->beforeSend)($payload);
            if ($result === null) return '';
            $payload = $result;
        }

        // Dedup
        $fp = $payload['fingerprint'] ?? null;
        if ($fp && in_array($level, ['error', 'fatal'], true)) {
            if (isset($this->dedupMap[$fp])) {
                $this->dedupMap[$fp]['count']++;
                return $logId;
            }
            $this->dedupMap[$fp] = ['count' => 0, 'time' => microtime(true)];
            // PHP doesn't have timers — dedup summary on shutdown
        }

        // Size check
        $size = Utils::safeJsonSize($payload);
        if ($size > 262144) {
            if ($this->config->autoTruncate) {
                $payload = Truncate::smartTruncate($payload);
            } else {
                $this->consoleError("Lognitor: Log too large ({$size} bytes), dropping");
                return '';
            }
        }

        $this->debugLog("[{$level}] {$message}");
        $buf = $this->parentBuffer ?? $this->batchBuffer;
        $buf->add($payload);
        return $logId;
    }

    /** @param array<string, mixed> $options */
    public function captureException(\Throwable $exception, array $options = []): string
    {
        try {
            $errorCtx = $this->throwableToContext($exception);
            // Source context
            if (isset($errorCtx['filename']) && isset($errorCtx['line_number'])) {
                $ctx = $this->readSourceContext($errorCtx['filename'], $errorCtx['line_number']);
                $errorCtx = array_merge($errorCtx, $ctx);
            }
            return $this->log('error', $exception->getMessage() ?: 'Unknown error', array_merge($options, ['error' => $errorCtx]));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @return array<string, mixed> */
    private function throwableToContext(\Throwable $e): array
    {
        return [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'stack' => $e->getTraceAsString(),
            'filename' => $e->getFile(),
            'line_number' => $e->getLine(),
        ];
    }

    /** @return array<string, mixed> */
    private function readSourceContext(string $filename, int $lineNumber, int $contextLines = 5): array
    {
        try {
            if (str_contains($filename, 'vendor/') || !is_file($filename)) {
                return [];
            }
            $file = new \SplFileObject($filename);
            $file->seek(0);
            $lines = [];
            while (!$file->eof()) {
                $lines[] = rtrim($file->current(), "\n\r");
                $file->next();
            }
            $idx = $lineNumber - 1;
            if ($idx < 0 || $idx >= count($lines)) return [];
            $start = max(0, $idx - $contextLines);
            $end = min(count($lines) - 1, $idx + $contextLines);
            return [
                'context_line' => $lines[$idx],
                'pre_context' => array_values(array_slice($lines, $start, $idx - $start)),
                'post_context' => array_values(array_slice($lines, $idx + 1, $end - $idx)),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // --- Context ---

    /** @param array<string, mixed> $user */
    public function setUser(array $user): void { $this->user = $user; }
    public function clearUser(): void { $this->user = null; }
    /** @param array<string, mixed> $ctx */
    public function setContext(array $ctx): void {
        try {
            $cloned = json_decode(json_encode($ctx), true) ?? $ctx;
            $this->globalContext = array_merge($this->globalContext, $cloned);
        } catch (\Throwable $e) {
            $this->globalContext = array_merge($this->globalContext, $ctx);
        }
    }
    /** @param list<string> $tags */
    public function setTags(array $tags): void { $this->globalTags = $tags; }
    public function setSession(string $sessionId): void { $this->sessionId = $sessionId; }

    /** @param array<string, mixed>|null $data */
    public function addBreadcrumb(
        string $type = 'default',
        string $category = 'custom',
        string $message = '',
        string $level = 'info',
        ?array $data = null,
    ): void {
        try { $this->breadcrumbs->add($type, $category, $message, $level, $data); } catch (\Throwable $e) {}
    }

    public function addIntegration(IntegrationInterface $integration): void
    {
        try {
            $this->integrations[] = $integration;
            $integration->setup($this);
            $this->debugLog("Integration \"{$integration->getName()}\" installed");
        } catch (\Throwable $e) {
            $this->debugLog("Integration setup failed: {$e->getMessage()}");
        }
    }

    public function flush(): void {
        try {
            $stalls = 0;
            while ($this->batchBuffer->length() > 0) {
                $before = $this->batchBuffer->length();
                $this->batchBuffer->flush();
                if ($this->batchBuffer->length() >= $before) {
                    if (++$stalls > 3) break;
                } else {
                    $stalls = 0;
                }
            }
        } catch (\Throwable $e) {}
    }

    public function shutdown(): void
    {
        try {
            foreach ($this->integrations as $i) { $i->teardown(); }

            // Flush dedup summaries
            foreach ($this->dedupMap as $fp => $entry) {
                if ($entry['count'] > 0) {
                    $this->log('error', 'Deduplicated error summary', [
                        'fingerprint' => $fp,
                        'metadata' => ['dedupCount' => $entry['count']],
                    ]);
                }
            }
            $this->dedupMap = [];

            $stalls = 0;
            while ($this->batchBuffer->length() > 0) {
                $before = $this->batchBuffer->length();
                $this->batchBuffer->flush();
                if ($this->batchBuffer->length() >= $before) {
                    if (++$stalls > 3) break;
                } else {
                    $stalls = 0;
                }
            }
        } catch (\Throwable $e) {}
    }

    public function pause(): void { $this->batchBuffer->pause(); }
    public function resume(): void { $this->batchBuffer->resume(); }

    /** @param array<string, mixed> $options */
    public function reconfigure(array $options): void
    {
        try {
            foreach ($options as $k => $v) {
                $prop = lcfirst(str_replace('_', '', ucwords($k, '_')));
                if (property_exists($this->config, $prop)) {
                    $this->config->$prop = $v;
                }
            }
            // Strip trailing slash
            $this->config->apiUrl = rtrim($this->config->apiUrl, '/');
            if (isset($options['api_key']) || isset($options['apiKey'])) {
                $this->authWarned = false;
                $this->batchBuffer->updateApiKey($this->config->apiKey);
            }
            if (isset($options['release_id']) || isset($options['releaseId'])) {
                $this->releaseId = $this->config->releaseId;
            }
        } catch (\Throwable $e) {}
    }

    // --- Heartbeat ---

    public function heartbeat(string $token): HeartbeatHandle
    {
        return new HeartbeatHandle($this, $token);
    }

    // --- Feedback ---

    /** @param array<string, mixed> $options */
    public function submitFeedback(array $options): void
    {
        try {
            $transport = $this->config->transport ?? new CurlTransport();
            $transport->send("{$this->config->apiUrl}/feedback", [
                'event_id' => $options['event_id'] ?? $options['eventId'] ?? '',
                'name' => $options['name'] ?? null,
                'email' => $options['email'] ?? null,
                'comments' => $options['comments'] ?? '',
                'url' => $options['url'] ?? null,
                'screenshot' => $options['screenshot'] ?? null,
            ], ['X-API-Key' => $this->config->apiKey]);
        } catch (\Throwable $e) {}
    }

    // --- Release ---

    /** @param array<string, mixed> $options @return array<string, mixed> */
    public function registerRelease(array $options): array
    {
        try {
            $transport = $this->config->transport ?? new CurlTransport();
            $response = $transport->send("{$this->config->apiUrl}/releases/register", [
                'version' => $options['version'] ?? '',
                'commit_hash' => $options['commit_hash'] ?? $options['commitHash'] ?? null,
                'branch' => $options['branch'] ?? null,
                'deployed_by' => $options['deployed_by'] ?? $options['deployedBy'] ?? null,
                'deploy_url' => $options['deploy_url'] ?? $options['deployUrl'] ?? null,
                'changelog' => $options['changelog'] ?? null,
                'services' => $options['services'] ?? null,
            ], ['X-API-Key' => $this->config->apiKey]);

            $body = is_array($response->body) ? $response->body : [];
            if (isset($body['release_id'])) {
                $this->releaseId = $body['release_id'];
                $this->deployCtx = [];
                if (isset($options['commit_hash']) || isset($options['commitHash'])) {
                    $this->deployCtx['commit'] = $options['commit_hash'] ?? $options['commitHash'];
                }
                if (isset($options['branch'])) {
                    $this->deployCtx['branch'] = $options['branch'];
                }
            }
            return $body;
        } catch (\Throwable $e) {
            $this->debugLog("registerRelease failed: {$e->getMessage()}");
            return [];
        }
    }

    // --- Child ---

    /** @param array<string, mixed> $options */
    public function child(array $options = []): self
    {
        $child = clone $this;
        $child->config = clone $this->config;
        $child->isChild = true;
        $child->parentBuffer = $this->batchBuffer;
        $child->childOverrides = array_intersect_key($options, array_flip(['service', 'environment', 'version', 'metadata', 'tags']));
        return $child;
    }

    // --- Timer ---

    public function startTimer(): TimerHandle
    {
        return new TimerHandle($this);
    }

    // --- Request logs ---

    /** @param list<array<string, mixed>> $entries */
    public function sendRequestLogs(array $entries): void
    {
        try {
            $this->batchBuffer->sendDirect(
                "{$this->config->apiUrl}/ingest/requests",
                ['requests' => $entries],
                ['X-API-Key' => $this->config->apiKey],
            );
        } catch (\Throwable $e) {}
    }

    // --- Internals ---

    public function getConfig(): Config { return $this->config; }

    /** @return list<array<string, mixed>> */
    public function drainPreInitBuffer(): array
    {
        $buf = $this->preInitBuffer;
        $this->preInitBuffer = [];
        return $buf;
    }

    /** @return array<string, mixed> */
    public function getState(): array
    {
        return [
            'user' => $this->user,
            'globalContext' => $this->globalContext,
            'globalTags' => $this->globalTags,
            'sessionId' => $this->sessionId,
            'breadcrumbs' => $this->breadcrumbs->getAll(),
        ];
    }

    private function debugLog(string $msg): void
    {
        if ($this->config->debug) {
            error_log("[Lognitor Debug] {$msg}");
        }
    }

    private function consoleWarn(string $msg): void { error_log($msg); }
    private function consoleError(string $msg): void { error_log($msg); }
}

final class HeartbeatHandle
{
    private Client $client;
    private string $token;

    public function __construct(Client $client, string $token)
    {
        $this->client = $client;
        $this->token = $token;
    }

    public function ping(): void
    {
        try {
            $config = $this->client->getConfig();
            $transport = $config->transport ?? new CurlTransport();
            $transport->send("{$config->apiUrl}/heartbeat/{$this->token}", null, []);
        } catch (\Throwable $e) {}
    }

    public function wrap(callable $fn): mixed
    {
        try {
            $result = $fn();
            $this->ping();
            return $result;
        } catch (\Throwable $e) {
            $this->client->captureException($e);
            throw $e;
        }
    }
}

final class TimerHandle
{
    private Client $client;
    private float $start;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->start = microtime(true);
    }

    /** @param array<string, mixed> $options */
    public function end(string $message, array $options = []): string
    {
        $durationMs = (microtime(true) - $this->start) * 1000;
        $perf = $options['perf'] ?? [];
        $perf['duration_ms'] = $durationMs;
        $options['perf'] = $perf;
        return $this->client->info($message, $options);
    }
}
