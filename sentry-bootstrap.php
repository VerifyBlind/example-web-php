<?php
// Sentry init — her API endpoint'inin en üstünde require edilir.
// 3 example portal (nextjs/dotnet/php) tek "verifyblind-examples" projesine raporlar;
// example_stack tag'i hangisi olduğunu ayırır. DSN yoksa Sentry hiç başlamaz.

require_once __DIR__ . '/vendor/autoload.php';

// Load .env once for every endpoint (api files require this bootstrap). Prefer a path
// OUTSIDE the docroot so the secrets file is never web-servable; fall back to the local
// project dir for `php -S` development. In production config comes from container env
// vars (.dockerignore strips **/.env, and apache.conf denies dotfiles as defense-in-depth).
$envCandidates = [
    dirname(__DIR__) . '/.env', // outside docroot (Docker: /var/www/.env)
    __DIR__ . '/.env',          // local dev fallback (NOT used in the image)
];
foreach ($envCandidates as $envPath) {
    if (!is_file($envPath)) continue;
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
    break; // first existing file wins
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
