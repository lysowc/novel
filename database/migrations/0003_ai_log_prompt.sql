-- AI 日志增加 prompt 字段（记录实际使用的 system prompt 文本）

ALTER TABLE `ai_log` ADD COLUMN `prompt` LONGTEXT NULL AFTER `task_type`;
