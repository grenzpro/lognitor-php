<?php
declare(strict_types=1);
namespace Lognitor;

final class MemoryTransport implements TransportInterface
{
    /** @var list<mixed> */
    public array $logs = [];

    /** @var list<array{url: string, payload: mixed, headers: array<string, string>}> */
    public array $requests = [];

    public function send(string $url, mixed $payload, array $headers): TransportResponse
    {
        $this->requests[] = ['url' => $url, 'payload' => $payload, 'headers' => $headers];
        if (is_array($payload) && isset($payload['logs'])) {
            foreach ($payload['logs'] as $log) {
                $this->logs[] = $log;
            }
        } else {
            $this->logs[] = $payload;
        }
        return new TransportResponse(200, [], ['accepted' => 1, 'message' => 'OK']);
    }

    public function clear(): void
    {
        $this->logs = [];
        $this->requests = [];
    }
}
