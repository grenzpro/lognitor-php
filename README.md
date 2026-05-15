# lognitor/lognitor

Official Lognitor SDK for PHP — log management, error tracking, and monitoring.

## Install

```bash
composer require lognitor/lognitor
```

## Quick Start

```php
use Lognitor\Lognitor;

Lognitor::init([
    'api_key' => 'your-api-key',
    'service' => 'my-php-app',
    'environment' => 'production',
]);

Lognitor::info('Server started');
```

## Log Levels

```php
Lognitor::debug('Cache miss', ['metadata' => ['key' => 'user:123']]);
Lognitor::info('Order created', ['metadata' => ['order_id' => 'ord_456']]);
Lognitor::warn('Rate limit approaching');
Lognitor::error('Payment failed', ['error' => new \RuntimeException('Card declined')]);
Lognitor::fatal('Database corrupted');
```

## Error Tracking

```php
try {
    processPayment($order);
} catch (\Throwable $e) {
    Lognitor::captureException($e, ['metadata' => ['order_id' => $order->id]]);
}
```

PHP errors and fatal errors are captured automatically.

## User Context

```php
Lognitor::setUser(['id' => 'user_123', 'email' => 'alice@example.com']);
Lognitor::clearUser();
```

## Laravel

```bash
# Config is auto-published via service provider
# Set in .env:
LOGNITOR_API_KEY=your-api-key
LOGNITOR_SERVICE=my-laravel-app
LOGNITOR_ENVIRONMENT=production
```

```php
// app/Http/Kernel.php
protected $middleware = [
    \Lognitor\Integrations\Laravel\LognitorMiddleware::class,
];
```

```bash
php artisan lognitor:test  # Verify connection
```

## Configuration

| Option | Default | Description |
|---|---|---|
| `api_key` | *required* | Project API key |
| `service` | `null` | Service name |
| `environment` | `null` | Environment |
| `batch_size` | `25` | Logs per batch |
| `flush_interval` | `5.0` | Auto-flush interval (seconds) |
| `max_retries` | `3` | Retry count |
| `min_level` | `null` | Minimum level to send |
| `redact_patterns` | `[]` | PII patterns to redact |
| `before_send` | `null` | Transform/filter callback |

## More

- Child loggers: `$client->child(['service' => 'payments'])`
- Timers: `$t = Lognitor::startTimer(); $t->end('Done');`
- Heartbeat: `Lognitor::heartbeat('token')->wrap(fn() => job())`
- Releases: `Lognitor::registerRelease(['version' => '2.0'])`
- PSR-3 logger: `new \Lognitor\PsrLogger()`
- Symfony: `LognitorBundle` available
- WordPress: Plugin included

Full documentation: [docs.lognitor.com](https://docs.lognitor.com)

## Compatibility

PHP 8.0+. Requires `ext-curl` and `ext-json`.
