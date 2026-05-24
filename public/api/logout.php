<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

if (Request::method() !== 'POST') {
    Response::error('Метод не поддерживается', 405);
}
Auth::requireCsrf();

$uid = Auth::userId();
if ($uid !== null) {
    ActivityLog::record($uid, 'logout', 'auth');
}

Auth::logout();
Response::ok(['loggedOut' => true]);
