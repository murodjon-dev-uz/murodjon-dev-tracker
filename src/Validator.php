<?php

declare(strict_types=1);

/**
 * Server-side validation. Client-side checks improve UX, but every rule is
 * re-enforced here because the client can never be trusted. Messages are in
 * Russian to match the product UI.
 */
final class Validator
{
    /** @var array<string,string> first error message per field */
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    private function value(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    private function filled(string $field): bool
    {
        return array_key_exists($field, $this->data)
            && $this->data[$field] !== null
            && $this->data[$field] !== '';
    }

    private function add(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function required(string $field, string $label): self
    {
        if (!$this->filled($field)) {
            $this->add($field, "{$label}: обязательное поле.");
        }
        return $this;
    }

    public function minLen(string $field, int $min, string $label): self
    {
        if ($this->filled($field) && mb_strlen((string) $this->value($field)) < $min) {
            $this->add($field, "{$label}: не менее {$min} символов.");
        }
        return $this;
    }

    public function maxLen(string $field, int $max, string $label): self
    {
        if ($this->filled($field) && mb_strlen((string) $this->value($field)) > $max) {
            $this->add($field, "{$label}: не более {$max} символов.");
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        if ($this->filled($field) && !filter_var((string) $this->value($field), FILTER_VALIDATE_EMAIL)) {
            $this->add($field, "{$label}: неверный формат e-mail.");
        }
        return $this;
    }

    /** @param array<int,string> $allowed */
    public function inEnum(string $field, array $allowed, string $label): self
    {
        if ($this->filled($field) && !in_array($this->value($field), $allowed, true)) {
            $this->add($field, "{$label}: недопустимое значение.");
        }
        return $this;
    }

    public function intRange(string $field, int $min, int $max, string $label): self
    {
        if ($this->filled($field)) {
            $raw = $this->value($field);
            if (!is_int($raw) && !(is_string($raw) && ctype_digit($raw))) {
                $this->add($field, "{$label}: должно быть числом.");
            } elseif ((int) $raw < $min || (int) $raw > $max) {
                $this->add($field, "{$label}: значение от {$min} до {$max}.");
            }
        }
        return $this;
    }

    public function hexColor(string $field, string $label): self
    {
        if ($this->filled($field) && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->value($field))) {
            $this->add($field, "{$label}: ожидается HEX-цвет (#RRGGBB).");
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
