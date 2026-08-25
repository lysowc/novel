<?php
/**
 * AI 小说系统 - 数据库配置（读取 .env）
 */
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => env('DB_HOST', '127.0.0.1'),
            'port'        => env('DB_PORT', '3306'),
            'database'    => env('DB_DATABASE', ''),
            'username'    => env('DB_USERNAME', ''),
            'password'    => env('DB_PASSWORD', ''),
            'charset'     => env('DB_CHARSET', 'utf8mb4'),
            'collation'   => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'      => env('DB_PREFIX', ''),
            'strict'      => env('DB_STRICT', true),
            'engine'      => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
            'pool' => [
                'max_connections' => (int)env('DB_POOL_MAX_CONNECTIONS', 5),
                'min_connections' => (int)env('DB_POOL_MIN_CONNECTIONS', 1),
                'wait_timeout' => (int)env('DB_POOL_WAIT_TIMEOUT', 3),
                'idle_timeout' => (int)env('DB_POOL_IDLE_TIMEOUT', 60),
                'heartbeat_interval' => (int)env('DB_POOL_HEARTBEAT_INTERVAL', 50),
            ],
        ],
    ],
];
