USE hotelsys_hrsystem;
-- =============================================================================
-- T-007  Subdivisions + penalty targeting: create subdivisions, subdivision_user, alter penalties
--        (phpMyAdmin)
-- =============================================================================
-- Equivalent of these five Laravel migrations, for production where there is no artisan:
--   database/migrations/2026_10_08_000001_create_subdivisions_table.php
--   database/migrations/2026_10_08_000002_create_subdivision_user_table.php
--   database/migrations/2026_10_08_000003_add_subdivision_id_to_penalties_table.php
--   database/migrations/2026_10_08_000004_alter_subdivisions_nullable_department.php
--   database/migrations/2026_10_08_000005_make_penalties_user_id_nullable.php
-- Run ONCE, after exporting the database. This script creates the FINAL structure directly
-- and records all five migrations.
--
--  * 000004 is NOT run as written. 000001 was edited after 000004 was written, so it already
--    creates department_id as nullable (ON DELETE SET NULL) with a unique key on name only.
--    000004 then drops `subdivisions_department_id_name_unique`, which never exists, and would
--    fail. The CREATE TABLE below is the end state of 000001 + 000004; 000004 is only recorded.
--  * CREATE TABLE has no "IF NOT EXISTS" on purpose: a second run fails and changes nothing.
--  * The INSERT into `migrations` records all five migrations as already run (same batch), so a
--    later `php artisan migrate` does not try to run them again (000004 would fail).
--  * Order: run THIS script first, then upload the code. The new penalty and subdivision code
--    reads penalties.subdivision_id, so it must not go live before this script.
--  * T-006 (timezone shift): release T-006 first, like T-004. The new tables then start out
--    in Malaysia time and need no shift.
--  * No transaction: CREATE TABLE and ALTER TABLE commit implicitly in MySQL anyway.
-- =============================================================================

-- ---- PRE-CHECK (run this SELECT on its own first) ----------------------------
-- Expect users = InnoDB, departments = InnoDB, penalties = MyISAM (penalties is one of the 11
-- MyISAM live tables listed in T-006_timezone_shift.sql). The two new tables are InnoDB and
-- reference users / departments, which must be InnoDB: if departments is MyISAM the first
-- CREATE TABLE fails with errno 150 and nothing is changed. If penalties shows InnoDB, stop and
-- tell the developer: section 3 is written for MyISAM.
SELECT `TABLE_NAME`, `ENGINE`
FROM information_schema.`TABLES`
WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN ('users', 'departments', 'penalties');

-- ---- 1. subdivisions (000001 + 000004 end state) ------------------------------
-- department_id NULL = a cross-department subdivision. Deleting a department turns its
-- subdivisions into cross-department ones instead of deleting them.
CREATE TABLE `subdivisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `department_id` bigint unsigned NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subdivisions_name_unique` (`name`),
  KEY `subdivisions_department_id_foreign` (`department_id`),
  CONSTRAINT `subdivisions_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 2. subdivision_user (000002) -------------------------------------------
-- Pivot: an employee can be tagged with several subdivisions. Rows go away with either side.
CREATE TABLE `subdivision_user` (
  `subdivision_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`subdivision_id`, `user_id`),
  KEY `subdivision_user_user_id_foreign` (`user_id`),
  CONSTRAINT `subdivision_user_subdivision_id_foreign` FOREIGN KEY (`subdivision_id`) REFERENCES `subdivisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subdivision_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 3. penalties (000003 + 000005) -----------------------------------------
-- One statement, so either all three changes apply or none do.
--  * subdivision_id: optional scope for department-target penalties. Existing rows get NULL.
--    penalties is MyISAM on live, which has no foreign keys (the Laravel migration's
--    constrained()->nullOnDelete() would be silently ignored), so only the index is created,
--    under the name Laravel's foreign key would have used. Deleting a subdivision therefore
--    does NOT clear penalties.subdivision_id in the database.
--  * user_id becomes NULLable: a department/subdivision-wide penalty has no single employee.
ALTER TABLE `penalties`
  ADD COLUMN `subdivision_id` bigint unsigned NULL AFTER `department_id`,
  ADD KEY `penalties_subdivision_id_foreign` (`subdivision_id`),
  MODIFY `user_id` bigint unsigned NULL;

-- Record all five migrations with the same batch number, skipping any that are already recorded.
SET @t007_batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`name`, @t007_batch
FROM (
    SELECT '2026_10_08_000001_create_subdivisions_table' AS `name`
    UNION ALL
    SELECT '2026_10_08_000002_create_subdivision_user_table'
    UNION ALL
    SELECT '2026_10_08_000003_add_subdivision_id_to_penalties_table'
    UNION ALL
    SELECT '2026_10_08_000004_alter_subdivisions_nullable_department'
    UNION ALL
    SELECT '2026_10_08_000005_make_penalties_user_id_nullable'
) AS n
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS m WHERE m.`migration` = n.`name`
);

-- ---- VERIFY ------------------------------------------------------------------
DESCRIBE `subdivisions`;
DESCRIBE `subdivision_user`;
DESCRIBE `penalties`;                                                            -- user_id Null = YES; subdivision_id after department_id
SELECT COUNT(*) AS rows_in_subdivisions FROM `subdivisions`;                     -- expect 0
SELECT COUNT(*) AS rows_in_subdivision_user FROM `subdivision_user`;             -- expect 0
SELECT COUNT(*) AS penalties_with_subdivision FROM `penalties`
WHERE `subdivision_id` IS NOT NULL;                                              -- expect 0
SELECT `CONSTRAINT_NAME`, `DELETE_RULE`
FROM information_schema.`REFERENTIAL_CONSTRAINTS`
WHERE `CONSTRAINT_SCHEMA` = DATABASE()
  AND `CONSTRAINT_NAME` IN ('subdivisions_department_id_foreign', 'subdivision_user_subdivision_id_foreign',
                            'subdivision_user_user_id_foreign');
                                                                                 -- expect 3 rows
SELECT * FROM `migrations` WHERE `migration` LIKE '2026_10_08_%';                -- expect 5 rows, same batch

-- ---- ROLLBACK ----------------------------------------------------------------
-- See T-007_subdivisions_penalties_rollback.sql (separate file; deletes all subdivisions and
-- their employee tags, and the subdivision scope on penalties).
