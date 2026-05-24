<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

const TASK_STATUSES   = ['new', 'in_progress', 'completed'];
const TASK_PRIORITIES = ['low', 'medium', 'high'];

$uid = Auth::requireAuth();
$db  = Database::conn();

/** Fetch one task (with its category) scoped to the owner. */
function task_find(PDO $db, int $uid, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT t.id, t.title, t.description, t.status, t.priority, t.progress,
                t.deadline, t.category_id, c.name AS category_name, c.color AS category_color,
                t.created_at, t.updated_at
         FROM tasks t
         LEFT JOIN categories c ON c.id = t.category_id
         WHERE t.id = :id AND t.user_id = :uid'
    );
    $stmt->execute([':id' => $id, ':uid' => $uid]);
    $row = $stmt->fetch();
    return $row === false ? null : task_shape($row);
}

/** Normalise DB row types for JSON output. */
function task_shape(array $row): array
{
    return [
        'id'             => (int) $row['id'],
        'title'          => $row['title'],
        'description'    => $row['description'],
        'status'         => $row['status'],
        'priority'       => $row['priority'],
        'progress'       => (int) $row['progress'],
        'deadline'       => $row['deadline'],
        'category_id'    => $row['category_id'] !== null ? (int) $row['category_id'] : null,
        'category_name'  => $row['category_name'] ?? null,
        'category_color' => $row['category_color'] ?? null,
        'created_at'     => $row['created_at'],
        'updated_at'     => $row['updated_at'],
    ];
}

function category_belongs(PDO $db, int $uid, int $categoryId): bool
{
    $stmt = $db->prepare('SELECT 1 FROM categories WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $categoryId, ':uid' => $uid]);
    return (bool) $stmt->fetchColumn();
}

/** '' / null → null; valid datetime → 'Y-m-d H:i:s'; invalid → false. */
function normalize_deadline(mixed $value): string|false|null
{
    if ($value === null || $value === '') {
        return null;
    }
    $value = (string) $value;
    foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    return false;
}

switch (Request::method()) {

    // --------------------------------------------------------------- LIST / ONE
    case 'GET':
        $id = Request::intId();
        if ($id !== null) {
            $task = task_find($db, $uid, $id);
            if ($task === null) {
                Response::error('Задача не найдена', 404);
            }
            Response::ok($task);
        }

        $sql = 'SELECT t.id, t.title, t.description, t.status, t.priority, t.progress,
                       t.deadline, t.category_id, c.name AS category_name, c.color AS category_color,
                       t.created_at, t.updated_at
                FROM tasks t
                LEFT JOIN categories c ON c.id = t.category_id
                WHERE t.user_id = :uid';
        $params = [':uid' => $uid];

        $status = Request::query('status');
        if ($status !== null && in_array($status, TASK_STATUSES, true)) {
            $sql .= ' AND t.status = :status';
            $params[':status'] = $status;
        }

        $priority = Request::query('priority');
        if ($priority !== null && in_array($priority, TASK_PRIORITIES, true)) {
            $sql .= ' AND t.priority = :priority';
            $params[':priority'] = $priority;
        }

        $category = Request::query('category_id');
        if ($category !== null && ctype_digit($category)) {
            $sql .= ' AND t.category_id = :cat';
            $params[':cat'] = (int) $category;
        }

        $q = Request::query('q');
        if ($q !== null && trim($q) !== '') {
            $sql .= ' AND (t.title LIKE :q OR t.description LIKE :q)';
            $params[':q'] = '%' . trim($q) . '%';
        }

        $sql .= " ORDER BY FIELD(t.priority, 'high', 'medium', 'low'), t.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        Response::ok(array_map('task_shape', $stmt->fetchAll()));
        // no break needed — Response::ok exits

    // --------------------------------------------------------------- CREATE
    case 'POST':
        Auth::requireCsrf();
        $in = Request::json();

        $title       = trim((string) ($in['title'] ?? ''));
        $description = isset($in['description']) && $in['description'] !== null ? (string) $in['description'] : null;
        $status      = (string) ($in['status'] ?? 'new');
        $priority    = (string) ($in['priority'] ?? 'medium');
        $hasProgress = array_key_exists('progress', $in) && $in['progress'] !== null && $in['progress'] !== '';
        $progress    = $hasProgress ? $in['progress'] : 0;
        $deadline    = normalize_deadline($in['deadline'] ?? null);
        $categoryId  = isset($in['category_id']) && $in['category_id'] !== null && $in['category_id'] !== ''
            ? (int) $in['category_id'] : null;

        $validator = Validator::make([
            'title' => $title, 'description' => $description,
            'status' => $status, 'priority' => $priority, 'progress' => $progress,
        ])
            ->required('title', 'Название')->maxLen('title', 255, 'Название')
            ->maxLen('description', 5000, 'Описание')
            ->inEnum('status', TASK_STATUSES, 'Статус')
            ->inEnum('priority', TASK_PRIORITIES, 'Приоритет')
            ->intRange('progress', 0, 100, 'Прогресс');

        if ($validator->fails()) {
            Response::unprocessable($validator->errors());
        }
        if ($deadline === false) {
            Response::unprocessable(['deadline' => 'Дедлайн: неверный формат даты.']);
        }
        if ($categoryId !== null && !category_belongs($db, $uid, $categoryId)) {
            Response::unprocessable(['category_id' => 'Категория не найдена.']);
        }

        $progress = (int) $progress;
        // Completing a task with no explicit progress fills the bar.
        if ($status === 'completed' && !$hasProgress) {
            $progress = 100;
        }

        $stmt = $db->prepare(
            'INSERT INTO tasks (user_id, category_id, title, description, status, priority, progress, deadline)
             VALUES (:uid, :cat, :title, :descr, :status, :priority, :progress, :deadline)'
        );
        $stmt->execute([
            ':uid' => $uid, ':cat' => $categoryId, ':title' => $title, ':descr' => $description,
            ':status' => $status, ':priority' => $priority, ':progress' => $progress, ':deadline' => $deadline,
        ]);
        $id = (int) $db->lastInsertId();

        ActivityLog::record($uid, 'task_created', 'task', $id, ['title' => $title, 'priority' => $priority]);
        Response::created(task_find($db, $uid, $id));

    // --------------------------------------------------------------- UPDATE
    case 'PUT':
    case 'PATCH':
        Auth::requireCsrf();
        $id = Request::intId();
        if ($id === null) {
            Response::error('Не указан идентификатор задачи', 400);
        }
        $current = task_find($db, $uid, $id);
        if ($current === null) {
            Response::error('Задача не найдена', 404);
        }

        $in = Request::json();

        $title       = array_key_exists('title', $in) ? trim((string) $in['title']) : $current['title'];
        $description = array_key_exists('description', $in)
            ? ($in['description'] === null ? null : (string) $in['description'])
            : $current['description'];
        $status      = array_key_exists('status', $in) ? (string) $in['status'] : $current['status'];
        $priority    = array_key_exists('priority', $in) ? (string) $in['priority'] : $current['priority'];
        $hasProgress = array_key_exists('progress', $in) && $in['progress'] !== null && $in['progress'] !== '';
        $progress    = $hasProgress ? $in['progress'] : $current['progress'];

        $deadlineProvided = array_key_exists('deadline', $in);
        $deadline = $deadlineProvided ? normalize_deadline($in['deadline']) : $current['deadline'];

        $categoryProvided = array_key_exists('category_id', $in);
        $categoryId = $categoryProvided
            ? ($in['category_id'] === null || $in['category_id'] === '' ? null : (int) $in['category_id'])
            : $current['category_id'];

        $validator = Validator::make([
            'title' => $title, 'description' => $description,
            'status' => $status, 'priority' => $priority, 'progress' => $progress,
        ])
            ->required('title', 'Название')->maxLen('title', 255, 'Название')
            ->maxLen('description', 5000, 'Описание')
            ->inEnum('status', TASK_STATUSES, 'Статус')
            ->inEnum('priority', TASK_PRIORITIES, 'Приоритет')
            ->intRange('progress', 0, 100, 'Прогресс');

        if ($validator->fails()) {
            Response::unprocessable($validator->errors());
        }
        if ($deadline === false) {
            Response::unprocessable(['deadline' => 'Дедлайн: неверный формат даты.']);
        }
        if ($categoryId !== null && !category_belongs($db, $uid, $categoryId)) {
            Response::unprocessable(['category_id' => 'Категория не найдена.']);
        }

        $progress = (int) $progress;
        $statusChanged = $status !== $current['status'];
        // Keep progress consistent with status unless explicitly overridden.
        if ($statusChanged && !$hasProgress) {
            if ($status === 'completed') {
                $progress = 100;
            } elseif ($status === 'new') {
                $progress = 0;
            }
        }

        $stmt = $db->prepare(
            'UPDATE tasks
                SET title = :title, description = :descr, status = :status, priority = :priority,
                    progress = :progress, deadline = :deadline, category_id = :cat
              WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([
            ':title' => $title, ':descr' => $description, ':status' => $status, ':priority' => $priority,
            ':progress' => $progress, ':deadline' => $deadline, ':cat' => $categoryId,
            ':id' => $id, ':uid' => $uid,
        ]);

        if ($statusChanged) {
            ActivityLog::record($uid, 'task_status_changed', 'task', $id, [
                'from' => $current['status'], 'to' => $status,
            ]);
        } else {
            ActivityLog::record($uid, 'task_updated', 'task', $id, ['title' => $title]);
        }

        Response::ok(task_find($db, $uid, $id));

    // --------------------------------------------------------------- DELETE
    case 'DELETE':
        Auth::requireCsrf();
        $id = Request::intId();
        if ($id === null) {
            Response::error('Не указан идентификатор задачи', 400);
        }
        $current = task_find($db, $uid, $id);
        if ($current === null) {
            Response::error('Задача не найдена', 404);
        }

        $stmt = $db->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $uid]);

        ActivityLog::record($uid, 'task_deleted', 'task', $id, ['title' => $current['title']]);
        Response::noContent();

    default:
        Response::error('Метод не поддерживается', 405);
}
