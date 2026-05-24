<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

$uid = Auth::requireAuth();
$db  = Database::conn();

function category_find(PDO $db, int $uid, int $id): ?array
{
    $stmt = $db->prepare('SELECT id, name, color, created_at FROM categories WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $uid]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** True if the user already has a category with this name (optionally excluding one id). */
function name_taken(PDO $db, int $uid, string $name, ?int $exceptId = null): bool
{
    $sql = 'SELECT 1 FROM categories WHERE user_id = :uid AND name = :name';
    $params = [':uid' => $uid, ':name' => $name];
    if ($exceptId !== null) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $exceptId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

switch (Request::method()) {

    // --------------------------------------------------------------- LIST
    case 'GET':
        $stmt = $db->prepare(
            'SELECT c.id, c.name, c.color, c.created_at, COUNT(t.id) AS task_count
             FROM categories c
             LEFT JOIN tasks t ON t.category_id = c.id
             WHERE c.user_id = :uid
             GROUP BY c.id, c.name, c.color, c.created_at
             ORDER BY c.name'
        );
        $stmt->execute([':uid' => $uid]);
        $rows = array_map(static fn (array $r): array => [
            'id'         => (int) $r['id'],
            'name'       => $r['name'],
            'color'      => $r['color'],
            'task_count' => (int) $r['task_count'],
            'created_at' => $r['created_at'],
        ], $stmt->fetchAll());
        Response::ok($rows);

    // --------------------------------------------------------------- CREATE
    case 'POST':
        Auth::requireCsrf();
        $in = Request::json();
        $name  = trim((string) ($in['name'] ?? ''));
        $color = trim((string) ($in['color'] ?? '#3498db'));
        if ($color === '') {
            $color = '#3498db';
        }

        $validator = Validator::make(['name' => $name, 'color' => $color])
            ->required('name', 'Название')->maxLen('name', 100, 'Название')
            ->hexColor('color', 'Цвет');
        if ($validator->fails()) {
            Response::unprocessable($validator->errors());
        }
        if (name_taken($db, $uid, $name)) {
            Response::error('Категория уже существует', 409, ['errors' => ['name' => 'Такая категория уже есть']]);
        }

        $stmt = $db->prepare('INSERT INTO categories (user_id, name, color) VALUES (:uid, :name, :color)');
        $stmt->execute([':uid' => $uid, ':name' => $name, ':color' => $color]);
        $id = (int) $db->lastInsertId();

        ActivityLog::record($uid, 'category_created', 'category', $id, ['name' => $name, 'color' => $color]);
        Response::created(['id' => $id, 'name' => $name, 'color' => $color, 'task_count' => 0]);

    // --------------------------------------------------------------- UPDATE
    case 'PUT':
    case 'PATCH':
        Auth::requireCsrf();
        $id = Request::intId();
        if ($id === null) {
            Response::error('Не указан идентификатор категории', 400);
        }
        $current = category_find($db, $uid, $id);
        if ($current === null) {
            Response::error('Категория не найдена', 404);
        }

        $in = Request::json();
        $name  = array_key_exists('name', $in) ? trim((string) $in['name']) : (string) $current['name'];
        $color = array_key_exists('color', $in) ? trim((string) $in['color']) : (string) $current['color'];
        if ($color === '') {
            $color = '#3498db';
        }

        $validator = Validator::make(['name' => $name, 'color' => $color])
            ->required('name', 'Название')->maxLen('name', 100, 'Название')
            ->hexColor('color', 'Цвет');
        if ($validator->fails()) {
            Response::unprocessable($validator->errors());
        }
        if (name_taken($db, $uid, $name, $id)) {
            Response::error('Категория уже существует', 409, ['errors' => ['name' => 'Такая категория уже есть']]);
        }

        $stmt = $db->prepare('UPDATE categories SET name = :name, color = :color WHERE id = :id AND user_id = :uid');
        $stmt->execute([':name' => $name, ':color' => $color, ':id' => $id, ':uid' => $uid]);

        ActivityLog::record($uid, 'category_updated', 'category', $id, ['name' => $name, 'color' => $color]);
        Response::ok(['id' => $id, 'name' => $name, 'color' => $color]);

    // --------------------------------------------------------------- DELETE
    case 'DELETE':
        Auth::requireCsrf();
        $id = Request::intId();
        if ($id === null) {
            Response::error('Не указан идентификатор категории', 400);
        }
        $current = category_find($db, $uid, $id);
        if ($current === null) {
            Response::error('Категория не найдена', 404);
        }

        // Tasks keep existing — their category_id is set to NULL by the FK.
        $stmt = $db->prepare('DELETE FROM categories WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $uid]);

        ActivityLog::record($uid, 'category_deleted', 'category', $id, ['name' => $current['name']]);
        Response::noContent();

    default:
        Response::error('Метод не поддерживается', 405);
}
