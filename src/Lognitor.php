<?php

/**
 * Lognitor PHP SDK — Static Facade
 * Copyright (c) 2026 Lognitor Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Lognitor;

final class Lognitor
{
    private static ?Client $instance = null;

    /** @param array<string, mixed> $options */
    public static function init(array $options): Client
    {
        if (self::$instance !== null) {
            // If instance was created by pre-init calls (no apiKey), replace it
            if (!self::$instance->getConfig()->apiKey) {
                $old = self::$instance;
                $buffered = $old->drainPreInitBuffer();
                $state = $old->getState();

                self::$instance = new Client($options);

                // Copy state from old pre-init instance
                if (!empty($state['user'])) {
                    self::$instance->setUser($state['user']);
                }
                if (!empty($state['globalContext'])) {
                    self::$instance->setContext($state['globalContext']);
                }
                if (!empty($state['globalTags'])) {
                    self::$instance->setTags($state['globalTags']);
                }
                if (!empty($state['sessionId'])) {
                    self::$instance->setSession($state['sessionId']);
                }
                foreach ($state['breadcrumbs'] as $bc) {
                    self::$instance->addBreadcrumb(
                        $bc['type'] ?? 'default',
                        $bc['category'] ?? 'custom',
                        $bc['message'] ?? '',
                        $bc['level'] ?? 'info',
                        $bc['data'] ?? null,
                    );
                }

                // Replay buffered logs
                foreach ($buffered as $entry) {
                    self::$instance->log($entry['level'], $entry['message'], $entry['options'] ?? []);
                }

                return self::$instance;
            }
            trigger_error('Lognitor: init() called twice. Use reconfigure() to update config.', E_USER_WARNING);
            return self::$instance;
        }
        self::$instance = new Client($options);
        return self::$instance;
    }

    public static function getInstance(): Client
    {
        if (self::$instance === null) {
            self::$instance = new Client();
        }
        return self::$instance;
    }

    /** @param array<string, mixed> $options */
    public static function log(string $level, string $message, array $options = []): string
    {
        return self::getInstance()->log($level, $message, $options);
    }

    /** @param array<string, mixed> $options */
    public static function debug(string $message, array $options = []): string { return self::getInstance()->debug($message, $options); }
    /** @param array<string, mixed> $options */
    public static function info(string $message, array $options = []): string { return self::getInstance()->info($message, $options); }
    /** @param array<string, mixed> $options */
    public static function warn(string $message, array $options = []): string { return self::getInstance()->warn($message, $options); }
    /** @param array<string, mixed> $options */
    public static function error(string $message, array $options = []): string { return self::getInstance()->error($message, $options); }
    /** @param array<string, mixed> $options */
    public static function fatal(string $message, array $options = []): string { return self::getInstance()->fatal($message, $options); }

    /** @param array<string, mixed> $options */
    public static function captureException(\Throwable $e, array $options = []): string
    {
        return self::getInstance()->captureException($e, $options);
    }

    /** @param array<string, mixed> $user */
    public static function setUser(array $user): void { self::getInstance()->setUser($user); }
    public static function clearUser(): void { self::getInstance()->clearUser(); }
    /** @param array<string, mixed> $ctx */
    public static function setContext(array $ctx): void { self::getInstance()->setContext($ctx); }
    /** @param list<string> $tags */
    public static function setTags(array $tags): void { self::getInstance()->setTags($tags); }
    public static function setSession(string $sessionId): void { self::getInstance()->setSession($sessionId); }

    /** @param array<string, mixed>|null $data */
    public static function addBreadcrumb(
        string $type = 'default',
        string $category = 'custom',
        string $message = '',
        string $level = 'info',
        ?array $data = null,
    ): void {
        self::getInstance()->addBreadcrumb($type, $category, $message, $level, $data);
    }

    public static function flush(): void { self::getInstance()->flush(); }
    public static function shutdown(): void { self::getInstance()->shutdown(); }
    public static function pause(): void { self::getInstance()->pause(); }
    public static function resume(): void { self::getInstance()->resume(); }

    /** @param array<string, mixed> $options */
    public static function reconfigure(array $options): void { self::getInstance()->reconfigure($options); }

    public static function heartbeat(string $token): HeartbeatHandle { return self::getInstance()->heartbeat($token); }

    /** @param array<string, mixed> $options */
    public static function submitFeedback(array $options): void { self::getInstance()->submitFeedback($options); }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    public static function registerRelease(array $options): array { return self::getInstance()->registerRelease($options); }

    /** @param array<string, mixed> $options */
    public static function child(array $options = []): Client { return self::getInstance()->child($options); }

    public static function startTimer(): TimerHandle { return self::getInstance()->startTimer(); }

    public static function addIntegration(IntegrationInterface $integration): void { self::getInstance()->addIntegration($integration); }

    /** @param list<array<string, mixed>> $entries */
    public static function sendRequestLogs(array $entries): void { self::getInstance()->sendRequestLogs($entries); }
}
