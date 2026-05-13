<?php
declare(strict_types=1);
namespace Lognitor;

final class Config
{
    public string $apiKey;
    public string $apiUrl;
    public ?string $service;
    public ?string $environment;
    public ?string $version;
    public ?string $releaseId;
    public int $batchSize;
    public float $flushInterval;
    public int $maxRetries;
    public int $maxQueueSize;
    public bool $debug;
    public bool $enabled;
    public ?string $minLevel;
    public bool $autoTruncate;
    public bool $captureCallerInfo;
    public int $maxBreadcrumbs;
    /** @var callable|null */
    public $beforeSend;
    /** @var list<string> */
    public array $redactPatterns;
    /** @var list<string> */
    public array $scrubUrlParams;
    /** @var TransportInterface|null */
    public ?TransportInterface $transport;
    /** @var list<IntegrationInterface> */
    public array $integrations;

    /** @param array<string, mixed> $options */
    public function __construct(array $options)
    {
        $this->apiKey = (string)($options['api_key'] ?? $options['apiKey'] ?? '');
        $this->apiUrl = rtrim((string)($options['api_url'] ?? $options['apiUrl'] ?? 'https://api.lognitor.com/api/v1'), '/');
        $this->service = $options['service'] ?? null;
        $this->environment = $options['environment'] ?? null;
        $this->version = $options['version'] ?? null;
        $this->releaseId = $options['release_id'] ?? $options['releaseId'] ?? null;
        $this->batchSize = (int)($options['batch_size'] ?? $options['batchSize'] ?? 25);
        $this->flushInterval = (float)($options['flush_interval'] ?? $options['flushInterval'] ?? 5.0);
        $this->maxRetries = (int)($options['max_retries'] ?? $options['maxRetries'] ?? 3);
        $this->maxQueueSize = (int)($options['max_queue_size'] ?? $options['maxQueueSize'] ?? 1000);
        $this->debug = (bool)($options['debug'] ?? false);
        $this->enabled = (bool)($options['enabled'] ?? true);
        $this->minLevel = $options['min_level'] ?? $options['minLevel'] ?? null;
        $this->autoTruncate = (bool)($options['auto_truncate'] ?? $options['autoTruncate'] ?? false);
        $this->captureCallerInfo = (bool)($options['capture_caller_info'] ?? $options['captureCallerInfo'] ?? false);
        $this->maxBreadcrumbs = (int)($options['max_breadcrumbs'] ?? $options['maxBreadcrumbs'] ?? 100);
        $this->beforeSend = $options['before_send'] ?? $options['beforeSend'] ?? null;
        $this->redactPatterns = $options['redact_patterns'] ?? $options['redactPatterns'] ?? [];
        $this->scrubUrlParams = $options['scrub_url_params'] ?? $options['scrubUrlParams'] ?? [];
        $this->transport = $options['transport'] ?? null;
        $this->integrations = $options['integrations'] ?? [];
    }
}
