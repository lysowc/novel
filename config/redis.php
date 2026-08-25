<?php
/**
 * AI 小说系统 - Redis 配置（读取 .env）
 */
return [
    'client' => 'predis',
    'default' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', ''),
        'port'     => (int)env('REDIS_PORT', 6379),
        'database' => (int)env('REDIS_DATABASE', 0),
        'pool' => [
            'max_connections' => (int)env('REDIS_POOL_MAX_CONNECTIONS', 5),
            'min_connections' => (int)env('REDIS_POOL_MIN_CONNECTIONS', 1),
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ],
    ],
];
