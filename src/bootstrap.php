<?php

declare(strict_types=1);

/**
 * Shared bootstrap included by every API endpoint.
 * Loads config + service layer, hardens the session, and guarantees that any
 * uncaught failure is returned as JSON rather than an HTML stack trace.
 */

$root = dirname(__DIR__);

require_once __DIR__ . '/env.php';
load_env($root . '/.env');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/Auth.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');

set_exception_handler(static function (Throwable $e): void {
    error_log('[tracker] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $detail = env('APP_ENV') === 'development' ? $e->getMessage() : 'Внутренняя ошибка сервера';
    echo json_encode(['success' => false, 'error' => $detail], JSON_UNESCAPED_UNICODE);
    exit;
});

// Hardened session cookie. `secure` turns on automatically under HTTPS.
$isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $isHttps,
]);
session_name('tracker_sid');
session_start();
Auth::ensureCsrfToken();

// Baseline security headers for every API response.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
