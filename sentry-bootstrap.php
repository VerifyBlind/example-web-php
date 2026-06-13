<?php
// Sentry init — her API endpoint'inin en üstünde require edilir.
// 3 example portal (nextjs/dotnet/php) tek "verifyblind-examples" projesine raporlar;
// example_stack tag'i hangisi olduğunu ayırır. DSN yoksa Sentry hiç başlamaz.

require_once __DIR__ . '/vendor/autoload.php';

// .env'i yükle (api dosyalarındaki hafif parser ile aynı mantık) — DSN ortamdan okunur.
$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

$dsn = getenv('EXAMPLES_SENTRY_DSN') ?: ($_ENV['EXAMPLES_SENTRY_DSN'] ?? '');
if ($dsn !== '') {
    \Sentry\init([
        'dsn' => $dsn,
        'environment' => getenv('APP_ENV') ?: 'production',
        'traces_sample_rate' => 0.1,
        'send_default_pii' => false,
        'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
            $event->setTag('example_stack', 'php');
            return $event;
        },
    ]);
}
