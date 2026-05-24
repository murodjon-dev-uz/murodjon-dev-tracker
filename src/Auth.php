<?php

declare(strict_types=1);

/**
 * Session-based authentication plus CSRF protection.
 *
 * Passwords use bcrypt with cost 12 (matching the $2y$12$ hashes already in
 * the database). State-changing requests must echo back the per-session CSRF
 * token in the X-CSRF-Token header.
 */
final class Auth
{
    private const BCRYPT_OPTIONS = ['cost' => 12];

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function check(): bool
    {
        return self::userId() !== null;
    }

    /** Returns the current user id or ends the request with 401. */
    public static function requireAuth(): int
    {
        $id = self::userId();
        if ($id === null) {
            Response::error('Требуется авторизация', 401);
        }
        return $id;
    }

    public static function login(int $id, string $username): void
    {
        // Prevent session fixation: issue a fresh session id on privilege change.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $username;
        self::ensureCsrfToken();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function ensureCsrfToken(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function requireCsrf(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $real = $_SESSION['csrf_token'] ?? '';
        if ($real === '' || !is_string($sent) || !hash_equals($real, $sent)) {
            Response::error('Недействительный CSRF-токен', 403);
        }
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, self::BCRYPT_OPTIONS);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, self::BCRYPT_OPTIONS);
    }
}
