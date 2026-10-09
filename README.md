# AI 小说工坊

基于 **Webman（PHP）+ Vue 3 + shadcn-vue** 的个人 AI 长篇小说创作与阅读系统。

核心链路：

```
AI 点子聊天 → 保存点子 → 创建小说 → AI 生成设定 → AI 生成章节大纲
→ 逐章生成正文（SSE 流式）→ 生成章节摘要 → 更新小说记忆
→ AI 续写 → 摘要 → 记忆 → …… 循环
```

核心原则：**正文是历史，摘要是索引，小说记忆是当前状态**。续写上下文分层组装（设定 + 记忆 + 摘要 + 最近 N 章正文），不把整本小说塞给 AI。

## 上下文与记忆架构

- **可召回记忆（检索式上下文）**：生成/续写每章前，以"本章大纲目标 + 用户指令 + 小说记忆"为查询，在全部历史章节摘要上做本地 BM25 检索（中文双字 bigram，零外部依赖），召回已被滚动窗口丢弃、但与当前剧情相关的早期章节注入上下文——长篇小说早期的伏笔得以自然回收。配置：`retrieval_enabled` / `retrieval_max_chapters`。
- **结构化记忆槽（v2）**：小说记忆从自由 JSON 升级为固定记忆槽：`current_state`（地点/时间/剧情进展）、`characters`（人物状态/关系/目标）、`foreshadowing`（伏笔：open/resolved + 埋设/回收章号）、`world_facts`（世界观增量）、`timeline`（关键事件，自动修剪最近 20 条）、`unresolved_events`、`important_items`、`style_notes`。旧格式记忆会在下次 AI 更新时自动迁移；后台「记忆」页提供结构化视图与 JSON 编辑两种模式。
- **一致性审校环**：新增 `consistency_check` 任务类型，AI 对照大纲、已写章节进度（近 30 章全量 + 更早等距抽样）与记忆，检测剧情偏离 / 前后矛盾 / 伏笔遗忘 / 人物失据 / 时间线冲突，产出分级报告（后台「审校」页）。支持手动触发，也可设置 `consistency_auto_interval` 每 N 章自动审校。
- 回归自测：`php webman verify:novel-memory`（检索召回 / 记忆规整 / 审校报告 19 项断言，自动造数并清理）。

## 技术栈

- 后端：PHP 8.5 + Webman 2 + MySQL 8 + Redis + Guzzle（OpenAI 兼容接口）
- 前端：Vue 3 + TypeScript + Vite + Tailwind CSS v4 + shadcn-vue + Pinia（目录 `web/`）
- AI：任意 OpenAI Compatible Provider（DeepSeek / OpenAI / Qwen / Gemini 等），流式 + 异步任务队列

## 环境要求

- PHP >= 8.4（本机推荐 Homebrew `php@8.5`，依赖 Symfony 8 / Laravel 13 要求 8.4+）
- MySQL 8、Redis
- Node 20+ / pnpm

## 快速开始

```bash
# 1. 安装依赖
composer install
cd web && pnpm install && cd ..

# 2. 配置 .env（复制 .env.example，填数据库/Redis）
cp .env.example .env

# 3. 初始化数据库（建表 + 默认数据）
php webman migrate        # 仅建表
php webman app:install    # 建表 + 管理员/分类/Prompt/系统配置
# 默认管理员：admin / admin123（可用 -u -p 自定义）

# 4. 启动后端（默认 http://127.0.0.1:8787）
php start.php start

# 5. 前端开发模式（http://localhost:5173，/api 代理到 8787，默认走真实后端）
cd web && pnpm dev
# 纯前端演示（不连后端）: VITE_USE_MOCK=1 pnpm dev

# 6. 前端生产构建（产物输出到 public/）
cd web && pnpm build
```

## 验收

```bash
bash test/acceptance.sh   # 全链路自动验收（登录/CRUD/鉴权/AI 任务/SSE 流式/摘要/记忆/阅读/日志）
```

## AI 配置

后台「AI 配置」添加 Provider（name + base_url + api_key）与 Model（如 `deepseek-chat`），设为默认即可。所有 AI 调用统一走 OpenAI 兼容接口。

本地无 API Key 时可使用 mock 服务器跑通全链路：

```bash
php -S 127.0.0.1:8899 test/mock_ai_server.php
# 后台添加 Provider: base_url = http://127.0.0.1:8899
```

## 目录结构

```
app/
  controller/api/      前台 API（首页/分类/详情/目录/阅读）
  controller/admin/    后台 API（需登录，含一致性审校）
  service/             AiClient / AiService / AiTaskService / ContextBuilder /
                       RetrievalService（相关章节检索）/ PromptService
  model/               14 张表的模型（+ 审校报告）
  process/AiWorker.php AI 任务消费进程（Redis 队列）
  command/             migrate / app:install / verify:novel-memory
config/route.php       全部路由 + SPA 兜底
database/migrations/   SQL 迁移
web/                   Vue 3 前端
test/mock_ai_server.php 本地 mock AI（联调用）
```

## 常用命令

```bash
php start.php start|stop|restart|status   # 服务管理
php webman migrate [fresh]                # 迁移（fresh 清库重建）
php webman app:install                    # 初始化数据
```
