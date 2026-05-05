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

require __DIR__.'/../vendor/autoload.php';
