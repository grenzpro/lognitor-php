# lognitor/lognitor

Official Lognitor SDK for PHP — log management, error tracking, and monitoring.

## Install

```bash
composer require lognitor/lognitor
```

## Quick Start

```php
use Lognitor\Lognitor;

Lognitor::init(['api_key' => 'lgn_...', 'service' => 'api', 'environment' => 'production']);
Lognitor::info('Server started', ['metadata' => ['port' => 8080]]);
```

## Logging

```php
Lognitor::debug('Cache hit', ['metadata' => ['key' => 'users:123']]);
Lognitor::info('User signed in', ['tags' => ['auth']]);
Lognitor::warn('Slow query', ['perf' => ['duration_ms' => 3200]]);
Lognitor::error('Payment failed', ['notify' => true]);
Lognitor::fatal('Database unreachable');
```

## Error Tracking

```php
try {
    processOrder($order);
} catch (\Throwable $e) {
    $eventId = Lognitor::captureException($e);
}
```

PHP errors and uncaught exceptions are captured automatically.

## PSR-3 Logger

```php
$logger = new \Lognitor\PsrLogger();
$logger->error('Something failed', ['orderId' => 'ord_123']);
```

## Laravel

```php
// config/lognitor.php
return [
    'api_key' => env('LOGNITOR_API_KEY'),
    'service' => env('APP_NAME', 'laravel-app'),
    'environment' => env('APP_ENV', 'production'),
    'capture_user' => true,
    'ignore_routes' => ['/health'],
];
```

ServiceProvider is auto-discovered. Add middleware to `app/Http/Kernel.php`:
```php
protected $middleware = [
    \Lognitor\Integrations\Laravel\LognitorMiddleware::class,
];
```

Test: `php artisan lognitor:test`

## Symfony

```yaml
# config/packages/lognitor.yaml
lognitor:
    api_key: '%env(LOGNITOR_API_KEY)%'
    service: 'symfony-app'
```

## WordPress

```php
// wp-config.php
define('LOGNITOR_API_KEY', 'lgn_...');
define('LOGNITOR_SERVICE', 'wordpress-site');
```

## Advanced

### Heartbeat

```php
$hb = Lognitor::heartbeat('cron-token');
$hb->wrap(function() { runNightlyJob(); });
```

### Timer

```php
$timer = Lognitor::startTimer();
heavyQuery();
$timer->end('Slow DB query');
```

### Child Loggers

```php
$paymentLogger = Lognitor::child(['service' => 'payments', 'tags' => ['billing']]);
$paymentLogger->error('Card declined');
```

### Test Transport

```php
use Lognitor\{Client, MemoryTransport};

$transport = new MemoryTransport();
$client = new Client(['api_key' => 'test', 'transport' => $transport]);
$client->info('Hello');
$client->flush();
assert(count($transport->logs) === 1);
```

## Compatibility

PHP 8.0+. Zero dependencies (curl + json extensions required).

## Development

### Prerequisites

- PHP 8.0+
- Composer
- curl and json extensions

### Setup (Local Path Repository)

In your test app's `composer.json`, add the SDK as a local path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../sdks/php"
    }
  ],
  "require": {
    "lognitor/lognitor": "@dev"
  }
}
```

Then run:

```bash
composer update lognitor/lognitor
```

Changes to the SDK source are picked up immediately (Composer symlinks the path).

### Smoke Test

```php
<?php
require_once 'vendor/autoload.php';

use Lognitor\Lognitor;

Lognitor::init([
    'api_key' => 'test_key',
    'service' => 'dev-test',
    'debug'   => true,
]);

Lognitor::info('smoke test', ['metadata' => ['env' => 'dev']]);
Lognitor::flush();
```

### Run Tests

```bash
composer test
# or
./vendor/bin/phpunit tests/
```

### Project Structure

```
src/
├── Lognitor.php          # Static facade (singleton)
├── Client.php            # Core client class
├── Types.php             # Config, LogLevel, interfaces
├── Transport.php         # cURL + memory transports
├── BatchBuffer.php       # Batch buffer + retry logic
├── Breadcrumbs.php       # Ring buffer for breadcrumbs
├── Enrichment.php        # Hostname, SDK metadata
├── Fingerprint.php       # Error fingerprinting
├── Truncate.php          # Smart payload truncation
├── Redact.php            # PII redaction
├── PsrLogger.php         # PSR-3 LoggerInterface adapter
├── Utils.php             # UUID, JSON, URL scrubbing
└── Integrations/
    ├── Laravel/
    │   ├── LognitorServiceProvider.php
    │   ├── LognitorMiddleware.php
    │   └── TestCommand.php
    ├── Symfony/
    │   └── LognitorBundle.php
    └── WordPress/
        └── lognitor.php
```
