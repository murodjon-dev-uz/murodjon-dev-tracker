<?php

declare(strict_types=1);

/**
 * Thin wrapper over the incoming HTTP request.
 */
final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Decoded JSON body as an associative array (empty when absent/invalid). */
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? null;
        return $value === null ? $default : (string) $value;
    }

    /** Positive integer `id` from the query string, or null. */
    public static function intId(): ?int
    {
        $value = $_GET['id'] ?? null;
        if ($value === null || !ctype_digit((string) $value)) {
            return null;
        }
        return (int) $value;
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
