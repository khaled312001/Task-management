<?php

// Vercel entry point for Laravel
// Setup writable directories in /tmp (only writable on Vercel)
$tmpStorage = '/tmp/storage';
foreach ([
    $tmpStorage,
    $tmpStorage . '/app',
    $tmpStorage . '/app/public',
    $tmpStorage . '/framework',
    $tmpStorage . '/framework/cache',
    $tmpStorage . '/framework/cache/data',
    $tmpStorage . '/framework/sessions',
    $tmpStorage . '/framework/views',
    $tmpStorage . '/logs',
] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

// Set Laravel cache paths to /tmp
putenv('VIEW_COMPILED_PATH=' . $tmpStorage . '/framework/views');
putenv('APP_SERVICES_CACHE=' . $tmpStorage . '/framework/services.php');
putenv('APP_PACKAGES_CACHE=' . $tmpStorage . '/framework/packages.php');
putenv('APP_CONFIG_CACHE=' . $tmpStorage . '/framework/config.php');
putenv('APP_ROUTES_CACHE=' . $tmpStorage . '/framework/routes.php');
putenv('APP_EVENTS_CACHE=' . $tmpStorage . '/framework/events.php');

$_ENV['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';
$_SERVER['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';

define('LARAVEL_START', microtime(true));

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

// Override storage path for Vercel's read-only filesystem
$app->useStoragePath($tmpStorage);

// Handle the request
$app->handleRequest(Illuminate\Http\Request::capture());
