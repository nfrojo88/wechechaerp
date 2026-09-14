<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Get a setting by key.
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        if ($setting->type === 'json') {
            return json_decode($setting->value, true) ?: $default;
        }
        if ($setting->type === 'boolean') {
            return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
        }
        if ($setting->type === 'integer') {
            return (int) $setting->value;
        }

        return $setting->value;
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, $value, string $type = 'string', ?string $group = null, ?string $description = null)
    {
        $val = ($type === 'json' && is_array($value)) ? json_encode($value) : (string)$value;

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value'       => $val,
                'type'        => $type,
                'group'       => $group,
                'description' => $description,
            ]
        );
    }
}
