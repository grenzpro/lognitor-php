# lognitor/lognitor (PHP) — Developer Guide

## Installation

```bash
composer require lognitor/lognitor
```

---

## Quick Start

```php
use Lognitor\Lognitor;

Lognitor::init([
    'api_key' => 'your-api-key',
    'service' => 'my-php-app',
    'environment' => 'production',
    'version' => '1.2.0',
]);

Lognitor::info('Server started');
Lognitor::error('Database connection failed', [
    'error' => ['type' => 'ConnectionError', 'message' => 'ECONNREFUSED'],
    'metadata' => ['host' => 'db.internal', 'port' => 5432],
]);
```

---

## Configuration

```php
use Lognitor\Lognitor;

$client = Lognitor::init([
    // Required
    'api_key' => 'your-api-key',

    // Identity
    'service' => 'payment-service',
    'environment' => 'production',
    'version' => '2.1.0',

    // Batching
    'batch_size' => 25,
    'flush_interval' => 5.0,
    'max_queue_size' => 1000,

    // Retry
    'max_retries' => 3,

    // Filtering
    'min_level' => 'info',
    'enabled' => true,

    // Privacy
    'redact_patterns' => ['email', 'creditCard', 'ssn', 'bearer'],
    'scrub_url_params' => ['token', 'password', 'secret', 'authorization'],

    // Advanced
    'auto_truncate' => true,
    'max_breadcrumbs' => 100,
    'debug' => false,
    'before_send' => function (array $log): ?array {
        if (str_contains($log['message'], 'healthcheck')) return null;
        return $log;
    },
]);
```

### Configuration Options Reference

| Option             | Type             | Default                           | Description                                       |
| ------------------ | ---------------- | --------------------------------- | ------------------------------------------------- |
| `api_key`          | `string`         | —                                 | **Required.** Your project API key.               |
| `api_url`          | `string`         | `https://api.lognitor.com/api/v1` | API endpoint.                                     |
| `service`          | `string`         | `null`                            | Service/app name.                                 |
| `environment`      | `string`         | `null`                            | Environment label.                                |
| `version`          | `string`         | `null`                            | App version.                                      |
| `batch_size`       | `int`            | `25`                              | Logs per batch.                                   |
| `flush_interval`   | `float`          | `5.0`                             | Auto-flush interval in seconds.                   |
| `max_retries`      | `int`            | `3`                               | Retry count for failed requests.                  |
| `max_queue_size`   | `int`            | `1000`                            | Maximum logs held in memory.                      |
| `min_level`        | `string \| null` | `null`                            | Minimum log level.                                |
| `enabled`          | `bool`           | `true`                            | Master switch.                                    |
| `auto_truncate`    | `bool`           | `false`                           | Truncate instead of dropping oversized logs.      |
| `max_breadcrumbs`  | `int`            | `100`                             | Max breadcrumbs.                                  |
| `debug`            | `bool`           | `false`                           | Print SDK debug messages.                         |
| `redact_patterns`  | `string[]`       | `[]`                              | Built-in: `email`, `creditCard`, `ssn`, `bearer`. |
| `scrub_url_params` | `string[]`       | `[...]`                           | Query params to scrub from URLs.                  |
| `before_send`      | `callable`       | `null`                            | `fn(array): ?array`. Return `null` to drop.       |

> Check your plan's limits and ingestion quotas at [dashboard.lognitor.com](https://dashboard.lognitor.com).

---

## Log Levels

```php
use Lognitor\Lognitor;

Lognitor::debug('Cache miss', ['metadata' => ['key' => 'user:123']]);
Lognitor::info('Order created', ['metadata' => ['order_id' => 'ord_456']]);
Lognitor::warn('Rate limit approaching', ['metadata' => ['usage' => 850]]);
Lognitor::error('Payment failed', ['error' => new \RuntimeException('Card declined')]);
Lognitor::fatal('Database corrupted');

// Or use log() with explicit level
Lognitor::log('info', 'Custom level call');
```

---

## Per-Log Options

```php
Lognitor::info('User signed up', [
    'metadata' => ['plan' => 'pro', 'referrer' => 'google'],
    'tags' => ['signup', 'marketing'],
    'user' => ['id' => 'user_123', 'email' => 'alice@example.com'],
    'request' => [
        'method' => 'POST', 'url' => '/api/users',
        'status_code' => 201, 'duration_ms' => 45,
    ],
    'perf' => ['duration_ms' => 120, 'memory_mb' => 256],
    'trace' => ['trace_id' => 'abc123', 'span_id' => 'def456'],
    'deploy' => ['commit' => 'a1b2c3d', 'branch' => 'main'],
    'action' => 'user.signup',
    'request_id' => 'req_abc123',
    'session_id' => 'sess_xyz',
    'notify' => true,
    'notify_channels' => ['slack', 'email'],
]);
```

---

## User Context

```php
// Set user — attached to all subsequent logs
Lognitor::setUser([
    'id' => 'user_123',
    'email' => 'alice@example.com',
    'name' => 'Alice',
]);

Lognitor::info('Profile updated'); // Includes user_id

// Override for a single log
Lognitor::info('Admin action', ['user' => ['id' => 'admin_1']]);

// Clear user
Lognitor::clearUser();
```

---

## Global Context, Tags, and Session

```php
// Context merges on each call
Lognitor::setContext(['region' => 'us-east-1']);
Lognitor::setContext(['deploy_id' => 'deploy_456']);

// Tags replace on each call
Lognitor::setTags(['production', 'critical-path']);

// Session ID
Lognitor::setSession('sess_abc123');
```

---

## Error Capturing

```php
// Capture a Throwable
try {
    processPayment($order);
} catch (\Throwable $e) {
    Lognitor::captureException($e, [
        'metadata' => ['order_id' => $order->id],
        'tags' => ['payment', 'critical'],
        'request_id' => 'req_123',
    ]);
}

// PHP errors (E_WARNING, E_NOTICE, etc.) are captured automatically
// via set_error_handler when the SDK is initialized.

// Fatal errors are captured via register_shutdown_function.
```

---

## Breadcrumbs

```php
Lognitor::addBreadcrumb('http', 'api', 'GET /api/users 200', 'info', [
    'duration_ms' => 45,
]);

Lognitor::addBreadcrumb('db', 'query', 'SELECT * FROM orders', 'info', [
    'rows' => 1, 'duration_ms' => 12,
]);

// Attached to error/fatal logs automatically
Lognitor::error('Order processing failed', [
    'error' => new \RuntimeException('Insufficient stock'),
]);
```

---

## Timers

```php
$timer = Lognitor::startTimer();
$result = heavyComputation();
$timer->end('Computation finished', [
    'metadata' => ['input_size' => 1000],
    'perf' => ['db_queries' => 5],
]);
// Includes perf.duration_ms automatically
```

---

## Child Loggers

```php
$client = Lognitor::init(['api_key' => 'your-key', 'service' => 'main-app']);

$paymentLogger = $client->child([
    'service' => 'payment-module',
    'metadata' => ['module' => 'payment'],
    'tags' => ['payments'],
]);

$paymentLogger->info('Processing payment');

// Grandchild
$stripeLogger = $paymentLogger->child(['service' => 'stripe-adapter']);
$stripeLogger->info('Charge created');
```

---

## Heartbeat Monitoring

Create a monitor in the [dashboard](https://dashboard.lognitor.com) and use the token.

```php
$hb = Lognitor::heartbeat('your-monitor-token');

// Simple ping
$hb->ping();

// Wrap a callable
$result = $hb->wrap(function () {
    return syncInventory();
});
```

---

## User Feedback

```php
$logId = Lognitor::error('Checkout failed', [
    'error' => new \RuntimeException('Payment timeout'),
]);

Lognitor::submitFeedback([
    'event_id' => $logId,
    'comments' => 'Page froze when I clicked pay',
    'name' => 'Alice',
    'email' => 'alice@acme.com',
]);
```

---

## Release Tracking

```php
$release = Lognitor::registerRelease([
    'version' => '2.1.0',
    'commit_hash' => 'a1b2c3d4e5f6',
    'branch' => 'main',
    'deployed_by' => 'github-actions',
]);

echo $release['release_id'];
// All subsequent logs include release_id and deploy context
```

---

## Laravel Integration

### 1. Publish Config

The service provider auto-registers. Publish the config file:

```bash
php artisan vendor:publish --tag=lognitor-config
```

### 2. Set Environment Variables

```env
LOGNITOR_API_KEY=your-api-key
LOGNITOR_SERVICE=my-laravel-app
LOGNITOR_ENVIRONMENT=production
```

### 3. Add Middleware

```php
// app/Http/Kernel.php
protected $middleware = [
    \Lognitor\Integrations\Laravel\LognitorMiddleware::class,
    // ... other middleware
];
```

### 4. Config File

```php
// config/lognitor.php
return [
    'api_key' => env('LOGNITOR_API_KEY'),
    'service' => env('LOGNITOR_SERVICE', 'my-laravel-app'),
    'environment' => env('LOGNITOR_ENVIRONMENT', 'production'),
    'ignore_routes' => ['/health', '/ready'],
    'capture_user' => true,
];
```

### 5. Test the Connection

```bash
php artisan lognitor:test
```

**What the middleware captures:** The Laravel middleware sends structured request data (method, path, status, duration, route, IP, user agent) to the `/ingest/requests` endpoint. Unhandled exceptions are captured automatically.

---

## Symfony Integration

```php
// config/packages/lognitor.php
use Lognitor\Integrations\Symfony\LognitorBundle;

return static function ($container) {
    $container->loadFromExtension('lognitor', [
        'api_key' => '%env(LOGNITOR_API_KEY)%',
        'service' => 'my-symfony-app',
        'environment' => '%kernel.environment%',
    ]);
};
```

Register the bundle:

```php
// config/bundles.php
return [
    Lognitor\Integrations\Symfony\LognitorBundle::class => ['all' => true],
];
```

---

## WordPress Integration

```php
// wp-content/plugins/lognitor/lognitor.php is the plugin file.
// Activate it from the WordPress admin panel.
// Configure in wp-config.php:
define('LOGNITOR_API_KEY', 'your-api-key');
define('LOGNITOR_SERVICE', 'my-wordpress-site');
define('LOGNITOR_ENVIRONMENT', 'production');
```

---

## PSR-3 Logger

Use Lognitor as a PSR-3 compatible logger:

```php
use Lognitor\PsrLogger;

$logger = new PsrLogger(Lognitor::getInstance());

$logger->info('Order created', ['order_id' => 'ord_123']);
$logger->error('Payment failed', [
    'exception' => new \RuntimeException('Card declined'),
]);
$logger->warning('Cache miss', ['key' => 'user:456']);
```

---

## Flush and Shutdown

```php
Lognitor::flush();    // Send all buffered logs
Lognitor::shutdown(); // Flush and clean up

// The SDK registers a shutdown function that auto-flushes on script end.
```

---

## Pause and Resume

```php
Lognitor::pause();   // Stop sending (still buffers)
Lognitor::resume();  // Resume sending
```

---

## Reconfigure

```php
Lognitor::reconfigure([
    'min_level' => 'warn',
    'enabled' => false,
    'debug' => true,
]);
```
