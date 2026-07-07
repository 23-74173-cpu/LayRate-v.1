<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'label'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
    }

    public static function thresholds(): array
    {
        $rows = static::whereIn('key', ['temp_min','temp_max','hum_min','hum_max'])->pluck('value','key');
        return [
            'temp_min' => (float) ($rows['temp_min'] ?? 18),
            'temp_max' => (float) ($rows['temp_max'] ?? 30),
            'hum_min'  => (float) ($rows['hum_min']  ?? 40),
            'hum_max'  => (float) ($rows['hum_max']  ?? 70),
        ];
    }

    public static function eggWeights(): array
    {
        $rows = static::whereIn('key', [
            'egg_weight_small',
            'egg_weight_medium',
            'egg_weight_large',
            'egg_weight_jumbo',
            'egg_weight_fallback',
        ])->pluck('value', 'key');

        return [
            'small'     => (float) ($rows['egg_weight_small']     ?? 50),
            'medium'    => (float) ($rows['egg_weight_medium']    ?? 58),
            'large'     => (float) ($rows['egg_weight_large']     ?? 65),
            'jumbo'     => (float) ($rows['egg_weight_jumbo']     ?? 73),
            'fallback'  => (float) ($rows['egg_weight_fallback']  ?? 60),
        ];
    }
}
