<?php

declare(strict_types=1);

/**
 * Append-only audit trail written to the activity_logs table.
 *
 * Logging must never break the request it is recording, so all failures are
 * swallowed (and sent to the PHP error log) rather than surfaced to the user.
 */
final class ActivityLog
{
    public static function record(
        ?int $userId,
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        try {
            $stmt = Database::conn()->prepare(
                'INSERT INTO activity_logs (user_id, action, entity, entity_id, details, ip_address)
                 VALUES (:user_id, :action, :entity, :entity_id, :details, :ip)'
            );
            $stmt->execute([
                ':user_id'   => $userId,
                ':action'    => $action,
                ':entity'    => $entity,
                ':entity_id' => $entityId,
                ':details'   => $details === null ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
                ':ip'        => Request::ip(),
            ]);
        } catch (Throwable $e) {
            error_log('[tracker] activity log failed: ' . $e->getMessage());
        }
    }
}
