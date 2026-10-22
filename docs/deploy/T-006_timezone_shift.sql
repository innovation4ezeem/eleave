USE hotelsys_hrsystem;
-- =============================================================================
-- T-006  ONE-TIME DATA SHIFT: UTC -> GMT+8 (Asia/Kuala_Lumpur)
-- =============================================================================
--  !!  RUN EXACTLY ONCE.  Running it twice shifts every row by 16 hours.  !!
--  !!  EXPORT THE DATABASE FIRST (phpMyAdmin > Export).                   !!
--  !!  Run within a couple of minutes of editing config/app.php.          !!
--
-- Before running, replace the ONE placeholder  @CUTOFF@  below with the result of
--         SELECT UTC_TIMESTAMP() + INTERVAL 4 HOUR;
-- taken right AFTER editing 'timezone' in /hrsystem/config/app.php, written as
-- 'YYYY-MM-DD HH:MM:SS' (UTC, not Malaysia time).
--
-- Why "now + 4 hours": let G be the moment the new timezone went live (UTC clock).
--   * Every OLD row holds a UTC value below G.
--   * Every NEW row is written in Malaysia time, so its value is at least G + 8h.
--   Any cutoff inside the gap [G, G + 8h) therefore separates old from new, and
--   "now + 4h" is the middle of that gap, so it still works if the SELECT was taken
--   a few hours early or late. The script refuses a cutoff that is not between 1 and
--   7 hours ahead of the moment it runs.
--
-- The cutoff is applied PER COLUMN, never per row:
--         col = IF(col < cutoff, col + 8 HOUR, col)
-- A row created before go-live but approved after has a UTC created_at and a
-- Malaysia decided_at / updated_at; only created_at is shifted. No UPDATE below has
-- a row-level WHERE. The only rule that reads another column is applied_at (below).
--
-- leave_applications.applied_at is SPECIAL (live data, 1756 rows): it has
-- ON UPDATE CURRENT_TIMESTAMP and MySQL runs on Malaysia time, so rows updated after
-- creation hold a Malaysia-time applied_at while created_at / updated_at /
-- decided_at are UTC.
--     A  applied_at within 5s of created_at     -> UTC value        -> SHIFT +8h
--     B  applied_at = updated_at + 8h (+-5s)    -> already Malaysia -> LEAVE
--     C  anything else (e.g. id 2212)           -> LEAVE
-- applied_at is shifted only when it is within 5s of created_at AND created_at is
-- below the cutoff. It is the FIRST assignment in its UPDATE (SET is evaluated left
-- to right and the rule needs the original created_at) and is always assigned
-- explicitly so ON UPDATE cannot overwrite it. No ALTER TABLE is made. A snapshot
-- of applied_at is taken first; the rollback restores from it.
--
-- Scope (checked against the live table list):
--   * Includes staff_achievements, a live-only table with no Laravel migration.
--   * Excludes _stg_leave_applications: not a Laravel table, nothing in the code
--     reads it, its columns are left untouched.
--   * Excludes warning_letters (T-004), which does not exist yet. If T-004 is released
--     BEFORE T-006, add warning_letters (every datetime column) to the shift, the
--     rollback and the touched list, using the same per-column rule.
--   * Not shifted on purpose: pure DATE columns (start_date, end_date, joined_at,
--     penalty_date, achieved_on, requested_*_date ...) and integer unix timestamps
--     (sessions.last_activity, cache.expiration, jobs.*_at).
--
-- Safety design:
--   * The marker table is created WITHOUT "IF NOT EXISTS": a second run fails on it
--     before any UPDATE runs. Leave "ignore errors" UNticked in phpMyAdmin.
--   * One UPDATE per table, every datetime column assigned explicitly, so MySQL's
--     "ON UPDATE CURRENT_TIMESTAMP" can never overwrite a column with NOW().
--   * NO TRANSACTION: 11 live tables are MyISAM (achievements, competence_categories,
--     competence_votes, leave_entitlement_tiers, leave_type_user, leave_year_locks, penalties,
--     performance_components, performance_entries, performance_inputs, work_experiences).
--     MyISAM cannot roll back, so ROLLBACK; would NOT undo this script (MySQL 5.7 warns
--     1196 "non-transactional changed tables couldn't be rolled back").
--   * IF THE SCRIPT STOPS PARTWAY: do NOT re-run it (the marker table blocks that anyway),
--     do NOT run T-006_rollback.sql (it would reverse tables that were never shifted), and do
--     NOT try ROLLBACK;. Restore the database from the export taken before the run, then tell
--     the developer.
--
-- Known side effect: historical leave records store a user-picked DATE in decided_at
-- (00:00:00). After the shift it reads 08:00:00 on the SAME date. The date never changes.
-- =============================================================================

-- ---- PREFLIGHT (read-only; check the output before continuing) --------------
-- MySQL here runs on Malaysia time: expect NOW() = UTC_TIMESTAMP() + 8 hours.
SELECT @@global.time_zone AS global_tz, @@session.time_zone AS session_tz,
       NOW() AS server_now, UTC_TIMESTAMP() AS utc_now;

-- Every datetime/timestamp column in this database (compare with the report at the end).
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('datetime', 'timestamp')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- applied_at buckets BEFORE the shift. Live data at time of writing: A=1474, B=281,
-- C=1 (id 2212). Numbers grow with new rows; a big change in B or C means stop.
SELECT
    SUM(ABS(TIMESTAMPDIFF(SECOND, created_at, applied_at)) <= 5) AS bucket_a_utc,
    SUM(ABS(TIMESTAMPDIFF(SECOND, created_at, applied_at)) > 5
        AND ABS(TIMESTAMPDIFF(SECOND, DATE_ADD(updated_at, INTERVAL 8 HOUR), applied_at)) <= 5) AS bucket_b_malaysia,
    SUM(ABS(TIMESTAMPDIFF(SECOND, created_at, applied_at)) > 5
        AND ABS(TIMESTAMPDIFF(SECOND, DATE_ADD(updated_at, INTERVAL 8 HOUR), applied_at)) > 5) AS bucket_c_other,
    COUNT(*) AS total
FROM leave_applications;

-- ---- RUN-ONCE GUARD + CUTOFF CHECK -------------------------------------------
-- Fails (stopping the script) if already run, if @CUTOFF@ was not replaced, or if the
-- cutoff is not 1-7 hours ahead of now (cutoff_utc is NOT NULL, so a NULL is an error).
-- If it fails on the INSERT, the empty marker table already exists: run
--     DROP TABLE t006_timezone_shift_marker;
-- then fix the cutoff and retry. If it fails on the CREATE TABLE, the script has
-- already run. Do NOT run it again.
CREATE TABLE t006_timezone_shift_marker (
    id INT PRIMARY KEY,
    cutoff_utc DATETIME NOT NULL,
    ran_at DATETIME NOT NULL
) ENGINE=InnoDB;
INSERT INTO t006_timezone_shift_marker (id, cutoff_utc, ran_at)
VALUES (1,
        IF(STR_TO_DATE('@CUTOFF@', '%Y-%m-%d %H:%i:%s')
               BETWEEN DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR) AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 HOUR),
           STR_TO_DATE('@CUTOFF@', '%Y-%m-%d %H:%i:%s'),
           NULL),
        UTC_TIMESTAMP());

SET @cutoff := (SELECT cutoff_utc FROM t006_timezone_shift_marker WHERE id = 1);

-- ---- SNAPSHOT (the rollback restores applied_at from this, not from a rule) ---
-- If this fails with "already exists", drop _t006_applied_at_backup only if you are
-- sure the shift has NOT run (the marker above would have failed otherwise).
-- Defined explicitly (not CREATE TABLE ... AS SELECT) so the copy does not inherit the live
-- column's ON UPDATE CURRENT_TIMESTAMP. TIMESTAMP is kept so the instant is copied exactly.
CREATE TABLE _t006_applied_at_backup (
    id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB;
INSERT INTO _t006_applied_at_backup (id, applied_at)
    SELECT id, applied_at FROM leave_applications;

-- ---- SHIFT (each UPDATE commits on its own; see "NO TRANSACTION" above) -------

UPDATE users SET
    email_verified_at = IF(email_verified_at < @cutoff, DATE_ADD(email_verified_at, INTERVAL 8 HOUR), email_verified_at),
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at),
    deleted_at = IF(deleted_at < @cutoff, DATE_ADD(deleted_at, INTERVAL 8 HOUR), deleted_at);

UPDATE password_reset_tokens SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at);

UPDATE failed_jobs SET
    failed_at = IF(failed_at < @cutoff, DATE_ADD(failed_at, INTERVAL 8 HOUR), failed_at);

UPDATE departments SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at),
    deleted_at = IF(deleted_at < @cutoff, DATE_ADD(deleted_at, INTERVAL 8 HOUR), deleted_at);

UPDATE department_approvers SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE leave_types SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE leave_balances SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

-- ORDER MATTERS: applied_at first, so its rule sees the ORIGINAL (UTC) created_at.
-- This is the only place a column's rule reads another column. The other columns
-- are plain per-column rules, so a row created before go-live and approved after
-- (UTC created_at, Malaysia decided_at / updated_at) only has created_at shifted.
UPDATE leave_applications SET
    applied_at = IF(created_at < @cutoff AND ABS(TIMESTAMPDIFF(SECOND, created_at, applied_at)) <= 5,
                    DATE_ADD(applied_at, INTERVAL 8 HOUR), applied_at),
    decided_at = IF(decided_at < @cutoff, DATE_ADD(decided_at, INTERVAL 8 HOUR), decided_at),
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at),
    deleted_at = IF(deleted_at < @cutoff, DATE_ADD(deleted_at, INTERVAL 8 HOUR), deleted_at);

UPDATE leave_application_logs SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at);

UPDATE leave_balance_adjustments SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at);

UPDATE leave_type_user SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE leave_entitlement_tiers SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE leave_year_locks SET
    locked_at = IF(locked_at < @cutoff, DATE_ADD(locked_at, INTERVAL 8 HOUR), locked_at),
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE penalties SET
    collected_at = IF(collected_at < @cutoff, DATE_ADD(collected_at, INTERVAL 8 HOUR), collected_at),
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at),
    deleted_at = IF(deleted_at < @cutoff, DATE_ADD(deleted_at, INTERVAL 8 HOUR), deleted_at);

UPDATE work_experiences SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE achievements SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE staff_achievements SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE competence_categories SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE competence_votes SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at);

UPDATE performance_inputs SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE performance_components SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE performance_entries SET
    created_at = IF(created_at < @cutoff, DATE_ADD(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_ADD(updated_at, INTERVAL 8 HOUR), updated_at);

-- ---- VERIFY ------------------------------------------------------------------
-- 1. Marker row: confirm cutoff_utc is the value you intended.
SELECT * FROM t006_timezone_shift_marker;

-- 2. Pending leaves from the morning of 8 Oct (were 01:21 / 01:22) must now read 09:21 / 09:22.
SELECT id, status, applied_at, created_at
FROM leave_applications
WHERE status = 'pending'
ORDER BY applied_at DESC
LIMIT 10;

-- 3. Newest rows: anything submitted after go-live must NOT be shifted twice
--    (applied_at should be close to Malaysia "now").
SELECT id, applied_at, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 8 HOUR) AS malaysia_now
FROM leave_applications
ORDER BY id DESC
LIMIT 5;

-- 4. Nothing should sit in the future (a future value means a double shift).
SELECT 'leave_applications.applied_at' AS col, COUNT(*) AS rows_in_future
FROM leave_applications WHERE applied_at > DATE_ADD(UTC_TIMESTAMP(), INTERVAL 9 HOUR)
UNION ALL
SELECT 'leave_applications.decided_at', COUNT(*)
FROM leave_applications WHERE decided_at > DATE_ADD(UTC_TIMESTAMP(), INTERVAL 9 HOUR)
UNION ALL
SELECT 'leave_applications.updated_at', COUNT(*)
FROM leave_applications WHERE updated_at > DATE_ADD(UTC_TIMESTAMP(), INTERVAL 9 HOUR)
UNION ALL
SELECT 'leave_application_logs.created_at', COUNT(*)
FROM leave_application_logs WHERE created_at > DATE_ADD(UTC_TIMESTAMP(), INTERVAL 9 HOUR)
UNION ALL
SELECT 'penalties.created_at', COUNT(*)
FROM penalties WHERE created_at > DATE_ADD(UTC_TIMESTAMP(), INTERVAL 9 HOUR);

-- 5. applied_at consistency. Expected: exactly 1 (id 2212 only).
SELECT COUNT(*) AS inconsistent_rows
FROM leave_applications
WHERE ABS(TIMESTAMPDIFF(SECOND, created_at, applied_at)) > 5
  AND ABS(TIMESTAMPDIFF(SECOND, updated_at, applied_at)) > 5;

-- 6. id 2212 must be untouched: applied_at = 2026-09-10 10:00:28.
SELECT id, applied_at, created_at, updated_at FROM leave_applications WHERE id = 2212;

-- 7. Keep the marker and backup tables until the release is signed off (the marker
--    blocks an accidental second run). Then:
--    DROP TABLE t006_timezone_shift_marker;
--    DROP TABLE _t006_applied_at_backup;

-- ---- REPORT: everything this script touched ----------------------------------
CREATE TEMPORARY TABLE t006_touched (
    tbl VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    col VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
);
INSERT INTO t006_touched (tbl, col) VALUES
    ('users', 'email_verified_at'),
    ('users', 'created_at'),
    ('users', 'updated_at'),
    ('users', 'deleted_at'),
    ('password_reset_tokens', 'created_at'),
    ('failed_jobs', 'failed_at'),
    ('departments', 'created_at'),
    ('departments', 'updated_at'),
    ('departments', 'deleted_at'),
    ('department_approvers', 'created_at'),
    ('department_approvers', 'updated_at'),
    ('leave_types', 'created_at'),
    ('leave_types', 'updated_at'),
    ('leave_balances', 'created_at'),
    ('leave_balances', 'updated_at'),
    ('leave_applications', 'applied_at'),
    ('leave_applications', 'decided_at'),
    ('leave_applications', 'created_at'),
    ('leave_applications', 'updated_at'),
    ('leave_applications', 'deleted_at'),
    ('leave_application_logs', 'created_at'),
    ('leave_balance_adjustments', 'created_at'),
    ('leave_type_user', 'created_at'),
    ('leave_type_user', 'updated_at'),
    ('leave_entitlement_tiers', 'created_at'),
    ('leave_entitlement_tiers', 'updated_at'),
    ('leave_year_locks', 'locked_at'),
    ('leave_year_locks', 'created_at'),
    ('leave_year_locks', 'updated_at'),
    ('penalties', 'collected_at'),
    ('penalties', 'created_at'),
    ('penalties', 'updated_at'),
    ('penalties', 'deleted_at'),
    ('work_experiences', 'created_at'),
    ('work_experiences', 'updated_at'),
    ('achievements', 'created_at'),
    ('achievements', 'updated_at'),
    ('staff_achievements', 'created_at'),
    ('staff_achievements', 'updated_at'),
    ('competence_categories', 'created_at'),
    ('competence_categories', 'updated_at'),
    ('competence_votes', 'created_at'),
    ('performance_inputs', 'created_at'),
    ('performance_inputs', 'updated_at'),
    ('performance_components', 'created_at'),
    ('performance_components', 'updated_at'),
    ('performance_entries', 'created_at'),
    ('performance_entries', 'updated_at');

-- Every table and column this script touched (one row per table).
SELECT tbl AS table_name, GROUP_CONCAT(col ORDER BY col SEPARATOR ', ') AS columns_shifted
FROM t006_touched GROUP BY tbl ORDER BY tbl;

-- Coverage check: datetime/timestamp columns in this database that the script did NOT touch.
-- Expected: only _stg_leave_applications (not a Laravel table, deliberately excluded).
-- Anything else listed here is a column this script missed: tell the developer.
SELECT c.TABLE_NAME AS untouched_table, c.COLUMN_NAME AS untouched_column, c.DATA_TYPE
FROM information_schema.COLUMNS c
LEFT JOIN t006_touched t
       ON t.tbl = CONVERT(c.TABLE_NAME USING utf8mb4) COLLATE utf8mb4_general_ci
      AND t.col = CONVERT(c.COLUMN_NAME USING utf8mb4) COLLATE utf8mb4_general_ci
WHERE c.TABLE_SCHEMA = DATABASE()
  AND c.DATA_TYPE IN ('datetime', 'timestamp')
  AND t.tbl IS NULL
  AND c.TABLE_NAME NOT IN ('t006_timezone_shift_marker', '_t006_applied_at_backup')
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;
