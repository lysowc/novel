-- AI 小说系统 V1 全部表结构

CREATE TABLE `admin` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `category` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `sort` INT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `novel` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `cover` VARCHAR(500) NOT NULL DEFAULT '',
  `description` TEXT NULL,
  `tags` VARCHAR(500) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `is_public` TINYINT NOT NULL DEFAULT 0,
  `word_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `chapter_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `outline` LONGTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `novel_setting` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `novel_id` INT UNSIGNED NOT NULL,
  `world_view` LONGTEXT NULL,
  `characters` LONGTEXT NULL,
  `factions` LONGTEXT NULL,
  `conflicts` LONGTEXT NULL,
  `main_plot` LONGTEXT NULL,
  `style` LONGTEXT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_novel` (`novel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chapter` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `novel_id` INT UNSIGNED NOT NULL,
  `chapter_no` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `summary` TEXT NULL,
  `content` LONGTEXT NULL,
  `word_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'published',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_novel_no` (`novel_id`, `chapter_no`),
  KEY `idx_novel` (`novel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `idea` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'unused',
  `novel_id` INT UNSIGNED NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `idea_chat` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idea_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(16) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_idea` (`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_provider` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `base_url` VARCHAR(500) NOT NULL,
  `api_key` VARCHAR(500) NOT NULL DEFAULT '',
  `status` TINYINT NOT NULL DEFAULT 1,
  `is_default` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_model` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `display_name` VARCHAR(128) NOT NULL DEFAULT '',
  `max_tokens` INT UNSIGNED NULL,
  `temperature` DECIMAL(4,2) NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `is_default` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_provider` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `prompt` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(64) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `content` LONGTEXT NOT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `novel_memory` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `novel_id` INT UNSIGNED NOT NULL,
  `content` LONGTEXT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_novel` (`novel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_task` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_type` VARCHAR(64) NOT NULL,
  `ref_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `ref_type` VARCHAR(32) NOT NULL DEFAULT 'novel',
  `params` JSON NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `error_message` VARCHAR(1000) NOT NULL DEFAULT '',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ref` (`ref_type`, `ref_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(64) NOT NULL DEFAULT '',
  `model` VARCHAR(128) NOT NULL DEFAULT '',
  `task_type` VARCHAR(64) NOT NULL DEFAULT '',
  `prompt_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
  `completion_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
  `duration` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'success',
  `error_message` VARCHAR(1000) NOT NULL DEFAULT '',
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(64) NOT NULL,
  `value` TEXT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
