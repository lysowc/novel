<?php

namespace app\service;

use app\model\AiLog;
use app\model\AiModel;
use app\model\AiProvider;
use app\model\SystemConfig;
use GuzzleHttp\Client;

/**
 * OpenAI 兼容 AI 客户端（流式 / 非流式）
 */
class AiClient
{
    /**
     * 取当前默认（启用且 is_default 优先）的 provider + model
     * @return array{provider: AiProvider, model: AiModel}|null
     */
    public static function defaultTarget(): ?array
    {
        $provider = AiProvider::where('status', 1)->orderByDesc('is_default')->orderBy('id')->first();
        $model = AiModel::where('status', 1)->orderByDesc('is_default')->orderBy('id')->first();
        if (!$provider || !$model) {
            return null;
        }
        return ['provider' => $provider, 'model' => $model];
    }

    /**
     * 组装 chat/completions 地址
     */
    public static function endpoint(AiProvider $provider): string
    {
        $base = rtrim(trim($provider->base_url), '/');
        if ($base === '') {
            throw new \RuntimeException('AI Provider base_url 为空');
        }
        if (str_ends_with($base, '/chat/completions')) {
            return $base;
        }
        if (str_ends_with($base, '/v1')) {
            return $base . '/chat/completions';
        }
        return $base . '/v1/chat/completions';
    }

    /**
     * 组装请求头
     */
    private static function headers(AiProvider $provider): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($provider->api_key !== '') {
            $headers['Authorization'] = 'Bearer ' . $provider->api_key;
        }
        return $headers;
    }

    /**
     * 记录 AI 调用日志
     */
    public static function log(string $taskType, AiProvider $provider, AiModel $model, array $usage, int $durationMs, string $status, string $error = ''): void
    {
        $log = new AiLog();
        $log->provider = $provider->name;
        $log->model = $model->name;
        $log->task_type = $taskType;
        $log->prompt_tokens = (int)($usage['prompt_tokens'] ?? 0);
        $log->completion_tokens = (int)($usage['completion_tokens'] ?? 0);
        $log->total_tokens = (int)($usage['total_tokens'] ?? 0);
        $log->duration = $durationMs;
        $log->status = $status;
        $log->error_message = mb_substr($error, 0, 900);
        $log->created_at = now();
        $log->save();
    }

    /**
     * 非流式对话
     * @param array $messages [['role'=>'system'|'user'|'assistant','content'=>'...']]
     * @param array $options task_type / temperature / max_tokens / timeout
     * @return array{text: string, usage: array, provider: string, model: string, duration: int}
     */
    public function chat(array $messages, array $options = []): array
    {
        $target = self::defaultTarget();
        if (!$target) {
            throw new \RuntimeException('没有可用的 AI Provider/Model，请先在 AI 配置中添加并启用');
        }
        /** @var AiProvider $provider */
        /** @var AiModel $model */
        ['provider' => $provider, 'model' => $model] = $target;

        $taskType = (string)($options['task_type'] ?? 'chat');
        $temperature = (float)($options['temperature'] ?? $model->temperature ?? SystemConfig::get('ai_temperature', 0.8));
        $timeout = (int)($options['timeout'] ?? SystemConfig::get('ai_http_timeout', 120));

        $payload = [
            'model' => $model->name,
            'messages' => $messages,
            'temperature' => $temperature,
            'stream' => false,
        ];
        if (!empty($options['max_tokens'])) {
            $payload['max_tokens'] = (int)$options['max_tokens'];
        }

        $client = new Client([
            'timeout' => $timeout,
            'connect_timeout' => 20,
            'http_errors' => false,
        ]);

        $start = microtime(true);
        try {
            $resp = $client->post(self::endpoint($provider), [
                'json' => $payload,
                'headers' => self::headers($provider),
            ]);
        } catch (\Throwable $e) {
            self::log($taskType, $provider, $model, [], (int)((microtime(true) - $start) * 1000), 'failed', $e->getMessage());
            throw new \RuntimeException('AI 请求失败: ' . $e->getMessage());
        }
        $duration = (int)((microtime(true) - $start) * 1000);
        $body = json_decode((string)$resp->getBody(), true);

        if ($resp->getStatusCode() !== 200 || !is_array($body) || !isset($body['choices'][0]['message']['content'])) {
            $err = is_array($body) ? ($body['error']['message'] ?? ('HTTP ' . $resp->getStatusCode())) : ('HTTP ' . $resp->getStatusCode());
            self::log($taskType, $provider, $model, [], $duration, 'failed', (string)$err);
            throw new \RuntimeException('AI 调用失败: ' . $err);
        }

        $text = (string)$body['choices'][0]['message']['content'];
        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];
        self::log($taskType, $provider, $model, $usage, $duration, 'success');
        return [
            'text' => $text,
            'usage' => $usage,
            'provider' => $provider->name,
            'model' => $model->name,
            'duration' => $duration,
        ];
    }

    /**
     * 流式对话（SSE）
     * @param callable|null $onDelta 每收到一段增量回调（参数为增量文本）
     * @param callable|null $onStage 阶段回调（参数为阶段名）
     * @return array 同 chat()
     */
    public function chatStream(array $messages, array $options = [], ?callable $onDelta = null, ?callable $onStage = null): array
    {
        $target = self::defaultTarget();
        if (!$target) {
            throw new \RuntimeException('没有可用的 AI Provider/Model，请先在 AI 配置中添加并启用');
        }
        ['provider' => $provider, 'model' => $model] = $target;

        $taskType = (string)($options['task_type'] ?? 'chat');
        $temperature = (float)($options['temperature'] ?? $model->temperature ?? SystemConfig::get('ai_temperature', 0.8));
        $timeout = (int)($options['timeout'] ?? SystemConfig::get('ai_http_timeout', 120));

        $payload = [
            'model' => $model->name,
            'messages' => $messages,
            'temperature' => $temperature,
            'stream' => true,
        ];
        if (!empty($options['max_tokens'])) {
            $payload['max_tokens'] = (int)$options['max_tokens'];
        }

        // 流式：不设总超时，只设读超时（长时间生成）
        $client = new Client([
            'timeout' => 0,
            'read_timeout' => 300,
            'connect_timeout' => 20,
            'http_errors' => false,
        ]);

        $start = microtime(true);
        $resp = $client->post(self::endpoint($provider), [
            'json' => $payload,
            'headers' => self::headers($provider),
            'stream' => true,
        ]);
        $duration = (int)((microtime(true) - $start) * 1000);

        if ($resp->getStatusCode() !== 200) {
            $err = 'HTTP ' . $resp->getStatusCode();
            try {
                $errBody = json_decode((string)$resp->getBody(), true);
                if (is_array($errBody)) {
                    $err = (string)($errBody['error']['message'] ?? $err);
                }
            } catch (\Throwable) {
            }
            self::log($taskType, $provider, $model, [], $duration, 'failed', $err);
            throw new \RuntimeException('AI 调用失败: ' . $err);
        }

        $onStage && $onStage('stream_start');

        $stream = $resp->getBody();
        $buffer = '';
        $text = '';
        $usage = [];
        $finished = false;
        $readError = null;

        try {
            while (!$stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk === '') {
                    continue;
                }
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);
                    if ($line === '') {
                        continue;
                    }
                    if (str_starts_with($line, 'data:')) {
                        $data = trim(substr($line, 5));
                        if ($data === '[DONE]') {
                            $finished = true;
                            break 2;
                        }
                        $event = json_decode($data, true);
                        if (!is_array($event)) {
                            continue;
                        }
                        if (isset($event['usage']) && is_array($event['usage'])) {
                            $usage = $event['usage'];
                        }
                        $delta = $event['choices'][0]['delta']['content']
                            ?? $event['choices'][0]['message']['content']
                            ?? $event['choices'][0]['text']
                            ?? '';
                        if (is_string($delta) && $delta !== '') {
                            $text .= $delta;
                            $onDelta && $onDelta($delta);
                        }
                    }
                }
            }
            if ($buffer !== '' && !$finished) {
                $line = trim($buffer);
                if (str_starts_with($line, 'data:')) {
                    $data = trim(substr($line, 5));
                    if ($data !== '[DONE]') {
                        $event = json_decode($data, true);
                        if (is_array($event) && isset($event['usage'])) {
                            $usage = $event['usage'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $readError = $e->getMessage();
        }

        $onStage && $onStage('stream_end');

        if ($text === '' && $readError !== null) {
            self::log($taskType, $provider, $model, $usage, $duration, 'failed', '流式读取中断: ' . $readError);
            throw new \RuntimeException('AI 流式输出中断: ' . $readError);
        }

        self::log($taskType, $provider, $model, $usage, $duration, 'success');
        return [
            'text' => $text,
            'usage' => $usage,
            'provider' => $provider->name,
            'model' => $model->name,
            'duration' => $duration,
        ];
    }
}
