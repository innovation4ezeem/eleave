USE hotelsys_hrsystem;
-- =============================================================================
-- T-007 ROLLBACK: remove subdivisions, subdivision_user and penalties.subdivision_id (phpMyAdmin)
-- =============================================================================
--  !!  DESTROYS ALL SUBDIVISIONS, THEIR EMPLOYEE TAGS AND THE SUBDIVISION    !!
--  !!  SCOPE OF EVERY PENALTY. Export subdivisions, subdivision_user and     !!
--  !!  penalties first.                                                      !!
--
-- Reverse of T-007_subdivisions_penalties.sql. Restore the previous penalty, subdivision and
-- user-edit code (routes/web.php, navbar, controllers, views) in the same release, otherwise
-- the pages point at tables and columns that no longer exist.
--
--  * penalties.user_id is NOT made NOT NULL again here. Department/subdivision-wide penalties
--    issued after the release have user_id NULL and would block it (or, with the live
--    non-strict sql_mode, be silently turned into user_id = 0). A NULLable user_id is harmless
--    to the old code, which always sets it. Its migration row (000005) is kept to match.
--    If you really need it reverted, see the OPTIONAL section at the end.
--  * penalties is MyISAM on live, so T-007 created no foreign key on it; dropping the column
--    also drops its index. The pivot goes before subdivisions (it references subdivisions).
-- =============================================================================

-- Department/subdivision-wide penalties (no employee). Note this number; see OPTIONAL below.
SELECT COUNT(*) AS penalties_without_employee FROM `penalties` WHERE `user_id` IS NULL;

ALTER TABLE `penalties` DROP COLUMN `subdivision_id`;

DROP TABLE `subdivision_user`;
DROP TABLE `subdivisions`;

DELETE FROM `migrations`
WHERE `migration` IN (
    '2026_10_08_000001_create_subdivisions_table',
    '2026_10_08_000002_create_subdivision_user_table',
    '2026_10_08_000003_add_subdivision_id_to_penalties_table',
    '2026_10_08_000004_alter_subdivisions_nullable_department'
);

-- Verify: the first two must return nothing; the last must return only the 000005 row.
SHOW TABLES LIKE 'subdivision%';
SHOW COLUMNS FROM `penalties` LIKE 'subdivision_id';
SELECT * FROM `migrations` WHERE `migration` LIKE '2026_10_08_%';

-- ---- OPTIONAL: make penalties.user_id NOT NULL again --------------------------
-- Only if penalties_without_employee above was 0 (export and delete those rows first otherwise).
-- Run these two statements on their own, without the "-- " prefix:
-- ALTER TABLE `penalties` MODIFY `user_id` bigint unsigned NOT NULL;
-- DELETE FROM `migrations` WHERE `migration` = '2026_10_08_000005_make_penalties_user_id_nullable';
