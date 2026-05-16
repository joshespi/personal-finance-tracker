<?php

// Force $_SERVER before Laravel boots — phpunit's <env> tags only touch $_ENV/putenv, and Laravel's Env::get() reads $_SERVER first.
$testEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_HOST' => '',
    'DB_PORT' => '',
    'DB_USERNAME' => '',
    'DB_PASSWORD' => '',
    'DB_URL' => '',
];

foreach ($testEnv as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("$key=$value");
}

// Cached config bakes in APP_ENV from the last artisan config:cache run (usually 'local').
// Delete it so env vars set above take effect when Laravel boots.
$configCache = __DIR__.'/../bootstrap/cache/config.php';
if (file_exists($configCache)) {
    unlink($configCache);
}

require __DIR__.'/../vendor/autoload.php';
