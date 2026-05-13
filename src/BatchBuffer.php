<?php

/**
 * Lognitor PHP SDK — Batch Buffer
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

final class BatchBuffer
{
    /** @var list<array<string, mixed>> */
    private array $buffer = [];
    private Config $config;
    private TransportInterface $transport;
    private bool $paused = false;
    private bool $authFailed = false;
    /** @var callable */
    private $debugLog;
    /** @var callable|null */
    private $onSampleRates;
    /** @var callable|null */
    private $onAuthError;

    public function __construct(
        Config $config,
        TransportInterface $transport,
        callable $debugLog,
        ?callable $onSampleRates = null,
        ?callable $onAuthError = null,
    ) {
        $this->config = $config;
        $this->transport = $transport;
        $this->debugLog = $debugLog;
        $this->onSampleRates = $onSampleRates;
        $this->onAuthError = $onAuthError;
    }

    /** @param array<string, mixed> $log */
    public function add(array $log): void
    {
        if ($this->authFailed) {
            return;
        }
        if (count($this->buffer) >= $this->config->maxQueueSize) {
            array_shift($this->buffer);
            ($this->debugLog)('Buffer full, dropped oldest log');
        }
        $this->buffer[] = $log;

        if (count($this->buffer) >= $this->config->batchSize && !$this->paused) {
            $this->flush();
        }
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
        if (!empty($this->buffer)) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->paused || $this->authFailed || empty($this->buffer)) {
            return;
        }

        $batch = array_splice($this->buffer, 0, $this->config->batchSize);
        $payload = ['batch_id' => Utils::uuid(), 'logs' => $batch];
        $headers = ['X-API-Key' => $this->config->apiKey];
        $url = $this->config->apiUrl . '/ingest';

        ($this->debugLog)('Flushing ' . count($batch) . ' logs');

        try {
            $response = $this->sendWithRetry($url, $payload, $headers);
            $sr = $response->headers['x-lognitor-sample-rates'] ?? null;
            if ($sr && $this->onSampleRates) {
                try {
                    ($this->onSampleRates)(json_decode($sr, true));
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        } catch (\Throwable $e) {
            // All retries exhausted — drop the batch to prevent infinite retry loop.
            ($this->debugLog)("Batch of " . count($batch) . " logs dropped after " . ($this->config->maxRetries + 1) . " failed attempts");
        }
    }

    /** @param array<string, string> $headers */
    public function sendDirect(string $url, mixed $payload, array $headers): TransportResponse
    {
        return $this->sendWithRetry($url, $payload, $headers);
    }

    public function length(): int
    {
        return count($this->buffer);
    }

    public function updateApiKey(string $key): void
    {
        $this->config->apiKey = $key;
        $this->authFailed = false;
    }

    /** @param array<string, string> $headers */
    private function sendWithRetry(string $url, mixed $payload, array $headers): TransportResponse
    {
        $lastError = null;
        for ($attempt = 0; $attempt <= $this->config->maxRetries; $attempt++) {
            try {
                $response = $this->transport->send($url, $payload, $headers);

                if ($response->status >= 200 && $response->status < 300) {
                    return $response;
                }
                if ($response->status === 401) {
                    $this->authFailed = true;
                    if ($this->onAuthError) {
                        ($this->onAuthError)();
                    }
                    throw new \RuntimeException('Invalid API key');
                }
                if ($response->status === 429) {
                    $ra = $response->headers['retry-after'] ?? null;
                    $wait = $ra ? (float)$ra : Utils::jitteredBackoff($attempt);
                    usleep((int)($wait * 1_000_000));
                    continue;
                }
                if ($response->status >= 400 && $response->status < 500) {
                    ($this->debugLog)("Client error {$response->status}, dropping");
                    return $response;
                }
                if ($response->status >= 500 && $attempt < $this->config->maxRetries) {
                    usleep((int)(Utils::jitteredBackoff($attempt) * 1_000_000));
                    continue;
                }
                return $response;
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempt < $this->config->maxRetries) {
                    usleep((int)(Utils::jitteredBackoff($attempt) * 1_000_000));
                }
            }
        }
        throw $lastError ?? new \RuntimeException('Flush failed');
    }
}
