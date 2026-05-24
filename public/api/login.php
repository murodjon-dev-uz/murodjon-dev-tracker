<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

if (Request::method() !== 'POST') {
    Response::error('Метод не поддерживается', 405);
}
Auth::requireCsrf();

$in = Request::json();
$login    = trim((string) ($in['login'] ?? ''));
$password = (string) ($in['password'] ?? '');

$validator = Validator::make(['login' => $login, 'password' => $password])
    ->required('login', 'Логин или e-mail')
    ->required('password', 'Пароль');

if ($validator->fails()) {
    Response::unprocessable($validator->errors());
}

$db = Database::conn();
$stmt = $db->prepare('SELECT id, username, email, password FROM users WHERE username = :login OR email = :login_alt LIMIT 1');
$stmt->execute([':login' => $login, ':login_alt' => $login]);
$user = $stmt->fetch();

if (!$user) {
    ActivityLog::record(null, 'failed_login', 'auth', null, ['login' => $login, 'reason' => 'invalid_user']);
    Response::error('Неверный логин или пароль', 401);
}

if (!Auth::verify($password, (string) $user['password'])) {
    ActivityLog::record(null, 'failed_login', 'auth', null, ['login' => $login, 'reason' => 'invalid_password']);
    Response::error('Неверный логин или пароль', 401);
}

// Transparently upgrade the stored hash if the cost factor ever changes.
if (Auth::needsRehash((string) $user['password'])) {
    $upd = $db->prepare('UPDATE users SET password = :p WHERE id = :id');
    $upd->execute([':p' => Auth::hash($password), ':id' => (int) $user['id']]);
}

Auth::login((int) $user['id'], (string) $user['username']);
ActivityLog::record((int) $user['id'], 'login', 'auth');

Response::ok([
    'user'      => ['id' => (int) $user['id'], 'username' => $user['username'], 'email' => $user['email']],
    'csrfToken' => Auth::csrfToken(),
]);
