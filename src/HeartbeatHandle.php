<?php
declare(strict_types=1);
namespace Lognitor;

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
