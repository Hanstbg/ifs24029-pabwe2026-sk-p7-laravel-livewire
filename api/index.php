<?php

/*
|--------------------------------------------------------------------------
| Entry point Vercel (runtime vercel-php)
|--------------------------------------------------------------------------
|
| Di Vercel, filesystem bersifat read-only kecuali folder /tmp. File ini
| mengarahkan storage, cache bootstrap, dan compiled views ke /tmp, lalu
| meneruskan request ke public/index.php seperti biasa.
|
| Environment Variables yang diisi di dashboard Vercel tetap diutamakan.
|
*/

$tmp = '/tmp/laravel';

foreach ([
    'storage/app/public',
    'storage/app/private',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
] as $dir) {
    if (! is_dir("{$tmp}/{$dir}")) {
        @mkdir("{$tmp}/{$dir}", 0777, true);
    }
}

$defaults = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'FORCE_HTTPS' => 'true',
    'LOG_CHANNEL' => 'stderr',
    'SESSION_DRIVER' => 'cookie',
    'CACHE_STORE' => 'array',
    'LARAVEL_STORAGE_PATH' => "{$tmp}/storage",
    'VIEW_COMPILED_PATH' => "{$tmp}/storage/framework/views",
    'APP_CONFIG_CACHE' => "{$tmp}/bootstrap/cache/config.php",
    'APP_EVENTS_CACHE' => "{$tmp}/bootstrap/cache/events.php",
    'APP_PACKAGES_CACHE' => "{$tmp}/bootstrap/cache/packages.php",
    'APP_ROUTES_CACHE' => "{$tmp}/bootstrap/cache/routes.php",
    'APP_SERVICES_CACHE' => "{$tmp}/bootstrap/cache/services.php",
];

foreach ($defaults as $key => $value) {
    $current = getenv($key);

    if ($current === false || $current === '') {
        putenv("{$key}={$value}");
        $current = $value;
    }

    $_ENV[$key] = $current;
    $_SERVER[$key] = $current;
}

require __DIR__.'/../public/index.php';
