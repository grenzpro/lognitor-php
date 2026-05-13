<?php
declare(strict_types=1);
namespace Lognitor;

interface TransportInterface
{
    /** @param array<string, string> $headers */
    public function send(string $url, mixed $payload, array $headers): TransportResponse;
}
