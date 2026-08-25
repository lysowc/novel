<?php

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 后台登录校验
 */
class Auth implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $session = $request->session();
        if (!$session || !$session->get('admin_id')) {
            return fail_401();
        }
        return $handler($request);
    }
}
