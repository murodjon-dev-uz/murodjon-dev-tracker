<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

if (Request::method() !== 'GET') {
    Response::error('Метод не поддерживается', 405);
}

$uid = Auth::requireAuth();
$db  = Database::conn();

$stmt = $db->prepare(
    "SELECT
        COUNT(*)                                                                    AS total,
        SUM(status = 'new')                                                         AS new,
        SUM(status = 'in_progress')                                                 AS in_progress,
        SUM(status = 'completed')                                                   AS completed,
        SUM(deadline IS NOT NULL AND deadline < NOW() AND status <> 'completed')    AS overdue
     FROM tasks
     WHERE user_id = :uid"
);
$stmt->execute([':uid' => $uid]);
$row = $stmt->fetch() ?: [];

$stmt = $db->prepare('SELECT COUNT(*) FROM categories WHERE user_id = :uid');
$stmt->execute([':uid' => $uid]);
$categories = (int) $stmt->fetchColumn();

Response::ok([
    'total'       => (int) ($row['total'] ?? 0),
    'new'         => (int) ($row['new'] ?? 0),
    'in_progress' => (int) ($row['in_progress'] ?? 0),
    'completed'   => (int) ($row['completed'] ?? 0),
    'overdue'     => (int) ($row['overdue'] ?? 0),
    'categories'  => $categories,
]);
