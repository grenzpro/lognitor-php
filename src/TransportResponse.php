<?php
declare(strict_types=1);
namespace Lognitor;

final class TransportResponse
{
    public int $status;
    /** @var array<string, string> */
    public array $headers;
    public mixed $body;

    /** @param array<string, string> $headers */
    public function __construct(int $status, array $headers, mixed $body = null)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }
}
