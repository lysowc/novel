-- 一致性审校报告（对照大纲检测漂移/矛盾）

CREATE TABLE `novel_consistency_report` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `novel_id` INT UNSIGNED NOT NULL,
  `chapter_no` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'warning',
  `report` JSON NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_novel` (`novel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
