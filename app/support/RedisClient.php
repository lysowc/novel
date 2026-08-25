<?php

namespace app\support;

use Predis\Client;

/**
 * Redis 客户端封装（Predis 纯 PHP，供队列/流使用，绕过连接池与协程 Context）
 */
class RedisClient
{
    /**
     * 新建一个 Redis 连接
     */
    public static function connect(): Client
    {
        $config = [
            'scheme' => 'tcp',
            'host' => (string)env('REDIS_HOST', '127.0.0.1'),
            'port' => (int)env('REDIS_PORT', 6379),
            'database' => (int)env('REDIS_DATABASE', 0),
            'timeout' => 2.0,
            'read_write_timeout' => 60.0,
        ];
        $pass = (string)env('REDIS_PASSWORD', '');
        if ($pass !== '') {
            $config['password'] = $pass;
        }
        return new Client($config);
    }
}
