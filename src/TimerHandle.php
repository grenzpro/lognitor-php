<?php
declare(strict_types=1);
namespace Lognitor;

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
