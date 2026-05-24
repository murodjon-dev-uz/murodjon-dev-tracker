<?php

declare(strict_types=1);

/**
 * JSON response helpers with meaningful HTTP status codes.
 * Every method ends the request, so endpoints read top-to-bottom.
 */
final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(mixed $data = [], int $status = 200): never
    {
        self::json(['success' => true, 'data' => $data], $status);
    }

    public static function created(mixed $data = []): never
    {
        self::json(['success' => true, 'data' => $data], 201);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    public static function error(string $message, int $status = 400, array $extra = []): never
    {
        self::json(['success' => false, 'error' => $message] + $extra, $status);
    }

    /** 422 with per-field validation messages. */
    public static function unprocessable(array $errors): never
    {
        self::json(['success' => false, 'error' => 'Проверьте правильность заполнения полей', 'errors' => $errors], 422);
    }
}
