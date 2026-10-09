<?php

namespace app\model;

use support\Model;

class NovelMemory extends Model
{
    protected $table = 'novel_memory';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    /** 结构化记忆 schema 版本 */
    public const SCHEMA_V2 = 'v2';

    /** v2 记忆槽定义（渲染顺序与文案） */
    public const SLOTS = [
        'current_state' => '当前状态',
        'characters' => '人物状态',
        'foreshadowing' => '未回收伏笔',
        'world_facts' => '世界观增量',
        'timeline' => '关键时间线',
        'unresolved_events' => '未解决事件',
        'important_items' => '重要物品',
        'style_notes' => '文风备注',
    ];

    public function novel()
    {
        return $this->belongsTo(Novel::class, 'novel_id');
    }

    public static function of(Novel $novel): self
    {
        $memory = static::where('novel_id', $novel->id)->first();
        if (!$memory) {
            $memory = new static();
            $memory->novel_id = $novel->id;
            $memory->content = '';
        }
        return $memory;
    }

    /**
     * 记忆解析为数组（兼容三种历史格式）
     */
    public function toArray(): array
    {
        $data = json_decode((string)$this->content, true);
        if (!is_array($data)) {
            return [];
        }
        if (($data['schema'] ?? '') === self::SCHEMA_V2) {
            return $data;
        }
        // 旧格式 1：数组 [{"key":..,"value":..}]
        if (array_is_list($data)) {
            $legacy = [];
            foreach ($data as $item) {
                if (is_array($item) && isset($item['key'], $item['value'])) {
                    $legacy[(string)$item['key']] = (string)$item['value'];
                }
            }
            return ['schema' => 'legacy_pairs', 'items' => $legacy];
        }
        // 旧格式 2：自由对象（current_location / main_character / foreshadowing 等）
        return ['schema' => 'legacy_object', 'items' => $data];
    }

    /**
     * 是否为 v2 结构化记忆
     */
    public function isStructured(): bool
    {
        return ($this->toArray()['schema'] ?? '') === self::SCHEMA_V2;
    }

    /**
     * 把 v2 记忆渲染成 AI 上下文文本（结构化、信息密度高）
     */
    protected function renderStructured(array $data): string
    {
        $parts = [];
        foreach (self::SLOTS as $slot => $label) {
            $content = $this->renderSlot($slot, $data[$slot] ?? null);
            if ($content !== '') {
                $parts[] = "【{$label}】\n{$content}";
            }
        }
        return $parts !== [] ? implode("\n\n", $parts) : '（暂无结构化记忆）';
    }

    /**
     * 单个记忆槽渲染
     */
    protected function renderSlot(string $slot, $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }
        switch ($slot) {
            case 'current_state':
                if (!is_array($value)) {
                    return (string)$value;
                }
                $lines = [];
                if (isset($value['location']) && (string)$value['location'] !== '') {
                    $lines[] = '地点：' . $value['location'];
                }
                if (isset($value['time']) && (string)$value['time'] !== '') {
                    $lines[] = '时间：' . $value['time'];
                }
                if (isset($value['plot_progress']) && (string)$value['plot_progress'] !== '') {
                    $lines[] = '剧情进展：' . $value['plot_progress'];
                }
                return implode("\n", $lines);

            case 'characters':
                $lines = [];
                foreach ((array)$value as $c) {
                    if (!is_array($c)) {
                        continue;
                    }
                    $name = (string)($c['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $bits = [$name];
                    if (isset($c['status']) && (string)$c['status'] !== '') {
                        $bits[] = '状态：' . $c['status'];
                    }
                    if (isset($c['relationships']) && (string)$c['relationships'] !== '') {
                        $bits[] = '关系：' . $c['relationships'];
                    }
                    if (isset($c['goals']) && (string)$c['goals'] !== '') {
                        $bits[] = '目标：' . $c['goals'];
                    }
                    $lines[] = '- ' . implode('；', $bits);
                }
                return implode("\n", $lines);

            case 'foreshadowing':
                $lines = [];
                foreach ((array)$value as $f) {
                    if (!is_array($f)) {
                        continue;
                    }
                    $desc = (string)($f['description'] ?? '');
                    if ($desc === '') {
                        continue;
                    }
                    $status = (string)($f['status'] ?? 'open');
                    $planted = (int)($f['planted_chapter'] ?? 0);
                    $resolved = (int)($f['resolved_chapter'] ?? 0);
                    $suffix = $status === 'resolved'
                        ? "（已回收，第{$resolved}章）"
                        : ($planted > 0 ? "（约第{$planted}章埋下）" : '');
                    $lines[] = "- {$desc}{$suffix}";
                }
                return implode("\n", $lines);

            case 'world_facts':
            case 'unresolved_events':
                $lines = [];
                foreach ((array)$value as $fact) {
                    if (is_array($fact)) {
                        $fact = (string)($fact['description'] ?? $fact['name'] ?? '');
                    }
                    $fact = trim((string)$fact);
                    if ($fact !== '') {
                        $lines[] = "- {$fact}";
                    }
                }
                return implode("\n", $lines);

            case 'timeline':
                $lines = [];
                foreach ((array)$value as $event) {
                    if (!is_array($event)) {
                        continue;
                    }
                    $chapter = (int)($event['chapter'] ?? 0);
                    $desc = trim((string)($event['event'] ?? ''));
                    if ($desc === '') {
                        continue;
                    }
                    $lines[] = $chapter > 0 ? "- 第{$chapter}章：{$desc}" : "- {$desc}";
                }
                return implode("\n", $lines);

            case 'important_items':
                $lines = [];
                foreach ((array)$value as $item) {
                    if (is_array($item)) {
                        $name = (string)($item['name'] ?? '');
                        $status = (string)($item['status'] ?? '');
                        $lines[] = $name !== '' ? "- {$name}" . ($status !== '' ? "（{$status}）" : '') : '';
                    } else {
                        $lines[] = '- ' . (string)$item;
                    }
                }
                return implode("\n", $lines);

            default: // style_notes 等纯文本槽
                return is_array($value) ? implode("\n", array_map('strval', $value)) : (string)$value;
        }
    }

    /**
     * 渲染为 AI 上下文文本（v2 结构化 / 旧格式兼容）
     */
    public function toContextText(): string
    {
        $data = $this->toArray();
        if ($data === []) {
            return '（暂无小说记忆）';
        }
        if (($data['schema'] ?? '') === self::SCHEMA_V2) {
            return $this->renderStructured($data);
        }
        // 旧格式：尽量保真渲染
        if (($data['schema'] ?? '') === 'legacy_pairs') {
            $lines = [];
            foreach (($data['items'] ?? []) as $key => $value) {
                $lines[] = "- {$key}：{$value}";
            }
            return $lines !== [] ? implode("\n", $lines) : '（暂无小说记忆）';
        }
        $lines = [];
        foreach (($data['items'] ?? []) as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $lines[] = "- {$key}：" . (string)$value;
        }
        return $lines !== [] ? implode("\n", $lines) : '（暂无小说记忆）';
    }
}
