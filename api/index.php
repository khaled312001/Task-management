<?php

// Vercel entry point for Laravel
// Vercel only allows writing to /tmp directory

// Setup writable directories in /tmp before Laravel boots
$tmpStorage = '/tmp/storage';
$tmpDirs = [
    $tmpStorage,
    $tmpStorage . '/app',
    $tmpStorage . '/app/public',
    $tmpStorage . '/framework',
    $tmpStorage . '/framework/cache',
    $tmpStorage . '/framework/cache/data',
    $tmpStorage . '/framework/sessions',
    $tmpStorage . '/framework/views',
    $tmpStorage . '/logs',
];
foreach ($tmpDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Override Laravel paths via env vars (read by config files)
putenv('VIEW_COMPILED_PATH=' . $tmpStorage . '/framework/views');
putenv('APP_SERVICES_CACHE=' . $tmpStorage . '/framework/services.php');
putenv('APP_PACKAGES_CACHE=' . $tmpStorage . '/framework/packages.php');
putenv('APP_CONFIG_CACHE=' . $tmpStorage . '/framework/config.php');
putenv('APP_ROUTES_CACHE=' . $tmpStorage . '/framework/routes.php');
putenv('APP_EVENTS_CACHE=' . $tmpStorage . '/framework/events.php');

$_ENV['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';
$_SERVER['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

// Override storage path to writable /tmp
$app->useStoragePath($tmpStorage);

$app->handleRequest(Illuminate\Http\Request::capture());
