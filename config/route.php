<?php

use Webman\Route;
use support\Request;
use app\middleware\Auth;

use app\controller\admin\AuthController;
use app\controller\admin\DashboardController;
use app\controller\admin\CategoryController;
use app\controller\admin\NovelController;
use app\controller\admin\ChapterController;
use app\controller\admin\IdeaController;
use app\controller\admin\AiProviderController;
use app\controller\admin\AiModelController;
use app\controller\admin\PromptController;
use app\controller\admin\AiTaskController;
use app\controller\admin\AiLogController;
use app\controller\admin\ConfigController;
use app\controller\admin\ConsistencyController;
use app\controller\api\HomeController;
use app\controller\api\FrontNovelController;

// ============ 前台 API ============
Route::group('/api', function () {
    Route::get('/home', [HomeController::class, 'home']);
    Route::get('/categories', [HomeController::class, 'categories']);
    Route::get('/novels', [FrontNovelController::class, 'index']);
    Route::get('/novels/{id}', [FrontNovelController::class, 'show']);
    Route::get('/novels/{id}/chapters', [FrontNovelController::class, 'chapters']);
    Route::get('/novels/{id}/chapters/{no}', [FrontNovelController::class, 'read']);
});

// ============ 后台登录（无需鉴权） ============
Route::post('/api/admin/login', [AuthController::class, 'login']);

// ============ 后台 API（需登录） ============
Route::group('/api/admin', function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/password', [AuthController::class, 'password']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    Route::get('/novels', [NovelController::class, 'index']);
    Route::post('/novels', [NovelController::class, 'store']);
    Route::get('/novels/{id}', [NovelController::class, 'show']);
    Route::put('/novels/{id}', [NovelController::class, 'update']);
    Route::delete('/novels/{id}', [NovelController::class, 'destroy']);
    Route::get('/novels/{id}/chapters', [NovelController::class, 'chapters']);
    Route::post('/novels/{id}/chapters', [NovelController::class, 'chapterStore']);
    Route::get('/novels/{id}/setting', [NovelController::class, 'setting']);
    Route::put('/novels/{id}/setting', [NovelController::class, 'setting']);
    Route::get('/novels/{id}/memory', [NovelController::class, 'memory']);
    Route::put('/novels/{id}/memory', [NovelController::class, 'memory']);
    Route::get('/novels/{id}/outline', [NovelController::class, 'outline']);
    Route::put('/novels/{id}/outline', [NovelController::class, 'outline']);

    Route::get('/novels/{id}/consistency', [ConsistencyController::class, 'index']);
    Route::post('/novels/{id}/consistency', [ConsistencyController::class, 'run']);

    Route::put('/chapters/{id}', [ChapterController::class, 'update']);
    Route::delete('/chapters/{id}', [ChapterController::class, 'destroy']);

    Route::get('/ideas', [IdeaController::class, 'index']);
    Route::post('/ideas', [IdeaController::class, 'store']);
    Route::put('/ideas/{id}', [IdeaController::class, 'update']);
    Route::delete('/ideas/{id}', [IdeaController::class, 'destroy']);
    Route::get('/ideas/{id}/messages', [IdeaController::class, 'messages']);
    Route::post('/ideas/{id}/chat', [IdeaController::class, 'chat']);
    Route::post('/ideas/{id}/save', [IdeaController::class, 'save']);
    Route::post('/ideas/{id}/create-novel', [IdeaController::class, 'createNovel']);

    Route::get('/ai/providers', [AiProviderController::class, 'index']);
    Route::post('/ai/providers', [AiProviderController::class, 'store']);
    Route::put('/ai/providers/{id}', [AiProviderController::class, 'update']);
    Route::delete('/ai/providers/{id}', [AiProviderController::class, 'destroy']);
    Route::post('/ai/providers/{id}/default', [AiProviderController::class, 'setDefault']);

    Route::get('/ai/models', [AiModelController::class, 'index']);
    Route::post('/ai/models', [AiModelController::class, 'store']);
    Route::put('/ai/models/{id}', [AiModelController::class, 'update']);
    Route::delete('/ai/models/{id}', [AiModelController::class, 'destroy']);
    Route::post('/ai/models/{id}/default', [AiModelController::class, 'setDefault']);

    Route::get('/prompts', [PromptController::class, 'index']);
    Route::put('/prompts/{id}', [PromptController::class, 'update']);

    Route::get('/ai/tasks', [AiTaskController::class, 'index']);
    Route::post('/ai/tasks', [AiTaskController::class, 'store']);
    Route::get('/ai/tasks/{id}', [AiTaskController::class, 'show']);
    Route::post('/ai/tasks/{id}/retry', [AiTaskController::class, 'retry']);
    Route::get('/ai/tasks/{id}/stream', [AiTaskController::class, 'stream']);

    Route::get('/ai/logs', [AiLogController::class, 'index']);
    Route::delete('/ai/logs', [AiLogController::class, 'clear']);

    Route::get('/config', [ConfigController::class, 'index']);
    Route::put('/config', [ConfigController::class, 'update']);
})->middleware([Auth::class]);

// ============ SPA 兜底 ============
Route::fallback(function (Request $request) {
    $path = ltrim($request->path(), '/');
    if ($path === 'api' || str_starts_with($path, 'api/')) {
        return json(['code' => 404, 'msg' => '接口不存在', 'data' => null]);
    }
    $file = public_path() . '/index.html';
    if (!is_file($file)) {
        return response('<h1>前端尚未构建</h1><p>请运行 pnpm build（web 目录）</p>', 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
    return response((string)file_get_contents($file), 200, ['Content-Type' => 'text/html; charset=utf-8']);
});
