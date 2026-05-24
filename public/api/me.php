<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

if (Request::method() !== 'GET') {
    Response::error('Метод не поддерживается', 405);
}

// Always hand the client a CSRF token so the SPA can make state-changing
// calls right after it loads, even before the user has authenticated.
$uid = Auth::userId();
if ($uid === null) {
    Response::ok(['authenticated' => false, 'csrfToken' => Auth::csrfToken()]);
}

$stmt = Database::conn()->prepare('SELECT id, username, email, created_at FROM users WHERE id = :id');
$stmt->execute([':id' => $uid]);
$user = $stmt->fetch();

if (!$user) {
    // Stale session pointing at a deleted user — clear it.
    Auth::logout();
    Response::ok(['authenticated' => false, 'csrfToken' => Auth::csrfToken()]);
}

Response::ok([
    'authenticated' => true,
    'user'          => [
        'id'         => (int) $user['id'],
        'username'   => $user['username'],
        'email'      => $user['email'],
        'created_at' => $user['created_at'],
    ],
    'csrfToken' => Auth::csrfToken(),
]);
