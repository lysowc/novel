<?php

namespace app\model;

use support\Model;

class SystemConfig extends Model
{
    protected $table = 'system_config';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    private static ?array $cache = null;

    /**
     * 读取全部配置为 key => value
     */
    public static function allAsMap(): array
    {
        if (static::$cache === null) {
            static::$cache = [];
            foreach (static::all() as $row) {
                static::$cache[$row->key] = $row->value;
            }
        }
        return static::$cache;
    }

    public static function forgetCache(): void
    {
        static::$cache = null;
    }

    /**
     * 读单个配置
     */
    public static function get(string $key, $default = null)
    {
        $map = static::allAsMap();
        return array_key_exists($key, $map) && $map[$key] !== null && $map[$key] !== ''
            ? $map[$key]
            : $default;
    }

    /**
     * 写配置（批量）
     */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $row = static::where('key', $key)->first();
            if (!$row) {
                $row = new static();
                $row->key = $key;
            }
            $row->value = is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);
            $row->updated_at = now();
            $row->save();
        }
        static::forgetCache();
    }
}
