<?php

/**
 * Lognitor PHP SDK — Laravel Service Provider
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor\Integrations\Laravel;

use Lognitor\Client;
use Lognitor\Lognitor;

/**
 * Extends Illuminate\Support\ServiceProvider which is always available at runtime
 * in Laravel apps. No composer dependency needed — Laravel provides the base class.
 */
class LognitorServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'lognitor');

        $this->app->singleton(Client::class, function ($app) {
            /** @var array<string, mixed> $config */
            $config = $app['config']['lognitor'] ?? [];
            return Lognitor::init($config);
        });

        $this->app->alias(Client::class, 'lognitor');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->getConfigPath() => $this->app->configPath('lognitor.php'),
            ], 'lognitor-config');

            $this->commands([TestCommand::class]);
        }

        // Auto-resolve to trigger init
        try {
            $this->app->make(Client::class);
        } catch (\Throwable $e) {
            // swallow
        }
    }

    private function getConfigPath(): string
    {
        return __DIR__ . '/../../../config/lognitor.php';
    }
}
