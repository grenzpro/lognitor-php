<?php

/**
 * Lognitor PHP SDK — Laravel Artisan Test Command
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor\Integrations\Laravel;

use Lognitor\Lognitor;

class TestCommand extends \Illuminate\Console\Command
{
    protected $signature = 'lognitor:test';
    protected $description = 'Send a test log to verify Lognitor configuration';

    public function handle(): int
    {
        $this->line('Sending test log to Lognitor...');

        try {
            $eventId = Lognitor::info('Test log from artisan lognitor:test', [
                'metadata' => ['source' => 'artisan_command'],
            ]);
            Lognitor::flush();
            $this->info("Test log sent successfully (ID: {$eventId})");
            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to send test log: {$e->getMessage()}");
            return 1;
        }
    }
}
