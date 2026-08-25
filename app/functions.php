<?php
/**
 * AI 小说系统 - 通用辅助函数
 */

use Webman\Http\Response;

if (!function_exists('ok')) {
    /**
     * 成功响应
     */
    function ok($data = null, string $msg = 'ok'): Response
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }
}

if (!function_exists('fail')) {
    /**
     * 失败响应
     */
    function fail(string $msg = 'error', int $code = 1, $data = null): Response
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }
}

if (!function_exists('fail_401')) {
    /**
     * 未登录响应
     */
    function fail_401(string $msg = '未登录或登录已过期'): Response
    {
        return json(['code' => 401, 'msg' => $msg, 'data' => null]);
    }
}

if (!function_exists('word_count')) {
    /**
     * 统计中文字数（去除空白与换行）
     */
    function word_count(?string $text): int
    {
        if ($text === null || $text === '') {
            return 0;
        }
        $text = preg_replace('/\s+/u', '', $text);
        return $text === null ? 0 : mb_strlen($text);
    }
}

if (!function_exists('now')) {
    /**
     * 当前时间字符串
     */
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
