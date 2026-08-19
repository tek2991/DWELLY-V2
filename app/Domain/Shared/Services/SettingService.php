<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    /**
     * Get a setting by group.key notation (e.g. 'financials.default_margin_percentage')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        [$group, $settingKey] = static::parseKey($key);

        $cacheKey = "setting.{$group}.{$settingKey}";

        return Cache::rememberForever($cacheKey, function () use ($group, $settingKey, $default) {
            $setting = SystemSetting::where('group', $group)
                ->where('key', $settingKey)
                ->first();

            if (! $setting || $setting->value === null) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set/update a setting value
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null): SystemSetting
    {
        [$group, $settingKey] = static::parseKey($key);

        $stringValue = is_array($value) ? json_encode($value) : (string) $value;

        $setting = SystemSetting::updateOrCreate(
            ['group' => $group, 'key' => $settingKey],
            [
                'value' => $stringValue,
                'type' => $type,
                'description' => $description,
            ]
        );

        Cache::forget("setting.{$group}.{$settingKey}");

        return $setting;
    }

    /**
     * Get all financial defaults for quotations & billing
     */
    public static function getFinancialDefaults(): array
    {
        return [
            'default_margin_percentage' => (float) static::get('financials.default_margin_percentage', 10.00),
            'default_gst_percentage' => (float) static::get('financials.default_gst_percentage', 18.00),
            'default_quotation_validity_days' => (int) static::get('financials.default_quotation_validity_days', 14),
        ];
    }

    /**
     * Clear all cached settings
     */
    public static function clearCache(): void
    {
        $settings = SystemSetting::all(['group', 'key']);
        foreach ($settings as $setting) {
            Cache::forget("setting.{$setting->group}.{$setting->key}");
        }
    }

    /**
     * Helper to parse 'group.key' notation
     */
    protected static function parseKey(string $key): array
    {
        if (str_contains($key, '.')) {
            [$group, $settingKey] = explode('.', $key, 2);
            return [$group, $settingKey];
        }

        return ['general', $key];
    }

    /**
     * Cast raw database string value to native PHP type
     */
    protected static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'number', 'decimal', 'float' => (float) $value,
            'integer', 'int' => (int) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }
}
