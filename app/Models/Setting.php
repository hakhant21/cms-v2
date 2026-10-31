<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value settings with a declared type. Every row is loaded once per
 * request and cast, so callers ask for a bool or an array instead of parsing
 * raw strings, and a typo in a key fails loudly instead of silently at render
 * time (null in, default out).
 */
class Setting extends Model
{
    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_BOOL = 'bool';

    public const TYPE_INT = 'int';

    public const TYPE_JSON = 'json';

    /** @var list<string> */
    protected $fillable = ['key', 'category', 'type', 'value'];

    /** @var array<string, mixed>|null */
    private static ?array $values = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    public static function forgetCache(): void
    {
        self::$values = null;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::values()[$key] ?? null;

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::values()[$key] ?? null;

        return $value === null ? $default : (bool) $value;
    }

    public static function integer(string $key, int $default = 0): int
    {
        $value = self::values()[$key] ?? null;

        return $value === null ? $default : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function json(string $key, array $default = []): array
    {
        $value = self::values()[$key] ?? null;

        return is_array($value) ? $value : $default;
    }

    /**
     * All settings of a category (or of every category) as key => typed value.
     *
     * @return array<string, mixed>
     */
    public static function forCategory(?string $category = null): array
    {
        if ($category === null) {
            return self::values();
        }

        return self::query()
            ->where('category', $category)
            ->get()
            ->mapWithKeys(fn (self $setting): array => [$setting->key => self::cast($setting->type, $setting->value)])
            ->all();
    }

    /** Write a setting, inferring its type from the value when possible. */
    public static function put(string $key, mixed $value, string $category = 'general', ?string $type = null): self
    {
        $type ??= match (true) {
            is_bool($value) => self::TYPE_BOOL,
            is_int($value) => self::TYPE_INT,
            is_array($value) => self::TYPE_JSON,
            default => self::TYPE_STRING,
        };

        $stored = match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE) ?: null,
            $value === null => null,
            default => (string) $value,
        };

        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'category' => $category, 'type' => $type],
        );

        self::forgetCache();

        return $setting;
    }

    /**
     * @return array<string, mixed>
     */
    private static function values(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }

        if (! Schema::hasTable('settings')) {
            return self::$values = [];
        }

        return self::$values = static::query()
            ->get()
            ->mapWithKeys(fn (self $setting): array => [$setting->key => self::cast($setting->type, $setting->value)])
            ->all();
    }

    private static function cast(?string $type, ?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            self::TYPE_BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_INT => (int) $value,
            self::TYPE_JSON => json_decode($value, true) ?: [],
            default => $value,
        };
    }
}
