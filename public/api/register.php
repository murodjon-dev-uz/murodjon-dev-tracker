<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

if (Request::method() !== 'POST') {
    Response::error('Метод не поддерживается', 405);
}
Auth::requireCsrf();

$in = Request::json();
$username = trim((string) ($in['username'] ?? ''));
$email    = trim((string) ($in['email'] ?? ''));
$password = (string) ($in['password'] ?? '');

$validator = Validator::make(['username' => $username, 'email' => $email, 'password' => $password])
    ->required('username', 'Имя пользователя')->minLen('username', 3, 'Имя пользователя')->maxLen('username', 50, 'Имя пользователя')
    ->required('email', 'E-mail')->email('email', 'E-mail')->maxLen('email', 100, 'E-mail')
    ->required('password', 'Пароль')->minLen('password', 8, 'Пароль');

if ($validator->fails()) {
    Response::unprocessable($validator->errors());
}

$db = Database::conn();

$stmt = $db->prepare('SELECT email FROM users WHERE username = :u OR email = :e LIMIT 1');
$stmt->execute([':u' => $username, ':e' => $email]);
if ($existing = $stmt->fetch()) {
    $field = strcasecmp((string) $existing['email'], $email) === 0 ? 'email' : 'username';
    $label = $field === 'email' ? 'E-mail уже зарегистрирован' : 'Имя пользователя уже занято';
    Response::error('Пользователь уже существует', 409, ['errors' => [$field => $label]]);
}

$stmt = $db->prepare('INSERT INTO users (username, email, password) VALUES (:u, :e, :p)');
$stmt->execute([':u' => $username, ':e' => $email, ':p' => Auth::hash($password)]);
$id = (int) $db->lastInsertId();

ActivityLog::record($id, 'user_registered', 'user', $id, ['email' => $email]);
Auth::login($id, $username);

Response::created([
    'user'      => ['id' => $id, 'username' => $username, 'email' => $email],
    'csrfToken' => Auth::csrfToken(),
]);
