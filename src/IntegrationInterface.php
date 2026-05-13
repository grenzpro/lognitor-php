<?php
declare(strict_types=1);
namespace Lognitor;

interface IntegrationInterface
{
    public function getName(): string;
    public function setup(Client $client): void;
    public function teardown(): void;
}
