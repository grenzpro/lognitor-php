<?php

/**
 * Lognitor PHP SDK — WordPress Plugin
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 *
 * Plugin Name: Lognitor
 * Description: Error tracking and log management for WordPress
 * Version: 1.0.0
 * Author: Lognitor Inc.
 */

declare(strict_types=1);

namespace Lognitor\Integrations\WordPress;

use Lognitor\Lognitor;

function lognitor_init(): void
{
    if (!defined('LOGNITOR_API_KEY') || !LOGNITOR_API_KEY) {
        return;
    }

    Lognitor::init([
        'api_key' => LOGNITOR_API_KEY,
        'service' => defined('LOGNITOR_SERVICE') ? LOGNITOR_SERVICE : 'wordpress',
        'environment' => defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production',
        'version' => defined('LOGNITOR_VERSION') ? LOGNITOR_VERSION : null,
    ]);

    // Client already installs set_error_handler and set_exception_handler
    // via installGlobalErrorHandlers(). No need to duplicate here.

    // Log WordPress wp_die events (fatal errors, permission denied, etc.)
    if (function_exists('add_filter')) {
        add_filter('wp_die_handler', function (callable $handler) {
            return function ($message, $title = '', $args = []) use ($handler) {
                try {
                    $msg = is_string($message) ? strip_tags(substr($message, 0, 500)) : 'wp_die called';
                    Lognitor::error('wp_die: ' . $msg, [
                        'metadata' => ['title' => $title],
                    ]);
                    Lognitor::flush();
                } catch (\Throwable $e) {
                    // swallow
                }
                // Call the original handler
                $handler($message, $title, $args);
            };
        });
    }
}

// Auto-init when loaded
lognitor_init();
