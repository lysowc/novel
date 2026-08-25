<?php

namespace app\controller\admin;

use app\model\Admin;
use support\Request;

class AuthController
{
    public function login(Request $request)
    {
        $username = trim((string)$request->post('username', ''));
        $password = (string)$request->post('password', '');
        if ($username === '' || $password === '') {
            return fail('请输入账号和密码');
        }
        $admin = Admin::where('username', $username)->first();
        if (!$admin || !password_verify($password, $admin->password)) {
            return fail('账号或密码错误');
        }
        $request->session()->set('admin_id', $admin->id);
        $admin->last_login_at = now();
        $admin->save();
        return ok(['username' => $admin->username], '登录成功');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin_id');
        return ok(null, '已退出登录');
    }

    public function me(Request $request)
    {
        $admin = Admin::find($request->session()->get('admin_id'));
        return ok(['username' => $admin->username ?? '']);
    }

    public function password(Request $request)
    {
        $old = (string)$request->post('old_password', '');
        $new = (string)$request->post('new_password', '');
        if (strlen($new) < 6) {
            return fail('新密码至少 6 位');
        }
        $admin = Admin::find($request->session()->get('admin_id'));
        if (!$admin || !password_verify($old, $admin->password)) {
            return fail('原密码错误');
        }
        $admin->password = password_hash($new, PASSWORD_DEFAULT);
        $admin->save();
        return ok(null, '密码已修改');
    }
}
