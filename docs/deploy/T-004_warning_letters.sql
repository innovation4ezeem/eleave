USE eleave_rehearsal;
-- =============================================================================
-- T-004 / T-005  Warning Letters: create warning_letters + warning_letter_events (phpMyAdmin)
-- =============================================================================
-- Equivalent of these two Laravel migrations, for production where there is no artisan:
--   database/migrations/2026_10_09_000001_create_warning_letters_table.php
--   database/migrations/2026_10_09_000002_add_audit_to_warning_letters.php
-- Run ONCE, after exporting the database. This script creates the FINAL structure directly
-- (warning_letters already includes updated_by) and records both migrations.
--
--  * CREATE TABLE has no "IF NOT EXISTS" on purpose: a second run fails and changes nothing.
--  * The INSERT into `migrations` records both migrations as already run (same batch), so a
--    later `php artisan migrate` does not try to create the tables again.
--  * Order: run THIS script first, then upload the code (see T-004_README.md).
--  * T-006 (timezone shift): release T-006 first. If this goes live BEFORE T-006, add
--    warning_letters and warning_letter_events to the T-006 script; see T-004_README.md.
--  * No transaction: CREATE TABLE commits implicitly in MySQL anyway.
-- =============================================================================

-- ENGINE=InnoDB is explicit on purpose: 11 other live tables are MyISAM, and the foreign keys
-- below need InnoDB on both sides (users is InnoDB).
CREATE TABLE `warning_letters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `issued_date` date NOT NULL,
  `level` varchar(100) NOT NULL,
  `notes` text NULL,
  `file_path` varchar(500) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int unsigned NOT NULL,
  `uploaded_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warning_letters_user_id_issued_date_index` (`user_id`, `issued_date`),
  CONSTRAINT `warning_letters_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `warning_letters_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `warning_letters_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit trail: one row per issue / edit / file replacement / delete / download.
-- created_at is deliberately NULLable with no default: on MySQL 5.7 a NOT NULL timestamp would
-- silently get DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP. The app sets the value.
CREATE TABLE `warning_letter_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warning_letter_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` varchar(20) NOT NULL,
  `details` varchar(255) NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warning_letter_events_warning_letter_id_created_at_index` (`warning_letter_id`, `created_at`),
  CONSTRAINT `warning_letter_events_warning_letter_id_foreign` FOREIGN KEY (`warning_letter_id`) REFERENCES `warning_letters` (`id`),
  CONSTRAINT `warning_letter_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record both migrations with the same batch number, skipping any that are already recorded.
SET @t004_batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`name`, @t004_batch
FROM (
    SELECT '2026_10_09_000001_create_warning_letters_table' AS `name`
    UNION ALL
    SELECT '2026_10_09_000002_add_audit_to_warning_letters'
) AS n
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS m WHERE m.`migration` = n.`name`
);

-- ---- VERIFY ------------------------------------------------------------------
DESCRIBE `warning_letters`;
DESCRIBE `warning_letter_events`;
SELECT COUNT(*) AS rows_in_warning_letters FROM `warning_letters`;               -- expect 0
SELECT COUNT(*) AS rows_in_warning_letter_events FROM `warning_letter_events`;   -- expect 0
SELECT * FROM `migrations` WHERE `migration` LIKE '%warning_letter%';            -- expect 2 rows, same batch

-- ---- ROLLBACK ----------------------------------------------------------------
-- See T-004_warning_letters_rollback.sql (separate file; deletes all warning letter records
-- and their audit history).
