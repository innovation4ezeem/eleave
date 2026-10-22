USE hotelsys_hrsystem;
-- =============================================================================
-- T-006 ROLLBACK: GMT+8 -> UTC  (reverse of T-006_timezone_shift.sql)
-- =============================================================================
--  !!  Run ONLY if T-006_timezone_shift.sql was run, and ONLY ONCE.        !!
--  !!  Export the database first. Edit /hrsystem/config/app.php back to    !!
--      'timezone' => 'UTC' right before running this.
--
-- Cutoff logic (differs from the forward script on purpose):
--   Forward cutoff = (time of the SELECT) + 4h, and the SELECT is taken just after
--   go-live G, so cutoff is about G + 4h. After the shift every old row sits below
--   G + 8h and every row written after go-live (Malaysia time) sits at or above
--   G + 8h. The reverse threshold is therefore cutoff + 4h (about G + 8h), read from
--   the marker table. Same per-column rule: col = IF(col < threshold, col - 8h, col).
--   Caveat 1: rows written between editing the config and taking the cutoff
--   (normally a minute or two) can fall just below the threshold and be reversed.
--   Caveat 2: rows created after go-live keep Malaysia-time values after rollback;
--   they will read 8h ahead under UTC. Fix by hand if any exist and matter.
--
--   leave_applications.applied_at is NOT reversed by a rule: it is restored from the
--   snapshot _t006_applied_at_backup taken by the forward script (rows created after
--   the snapshot keep their current applied_at). Any applied_at change made after
--   go-live on a row that existed at snapshot time is overwritten by the snapshot.
--
--   Not included, same as the forward script: _stg_leave_applications, warning_letters.
-- =============================================================================

SET @cutoff := (SELECT DATE_ADD(cutoff_utc, INTERVAL 4 HOUR) FROM t006_timezone_shift_marker WHERE id = 1);
SELECT @cutoff AS reverse_below;   -- must not be NULL; if NULL the shift never ran: STOP.
SELECT COUNT(*) AS snapshot_rows FROM _t006_applied_at_backup;   -- errors if the snapshot is missing: STOP.

-- No transaction: 11 live tables are MyISAM and cannot roll back (see T-006_timezone_shift.sql).
-- If this script stops partway, do NOT re-run it: restore the database from the export instead.

UPDATE users SET
    email_verified_at = IF(email_verified_at < @cutoff, DATE_SUB(email_verified_at, INTERVAL 8 HOUR), email_verified_at),
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at),
    deleted_at = IF(deleted_at < @cutoff, DATE_SUB(deleted_at, INTERVAL 8 HOUR), deleted_at);

UPDATE password_reset_tokens SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at);

UPDATE failed_jobs SET
    failed_at = IF(failed_at < @cutoff, DATE_SUB(failed_at, INTERVAL 8 HOUR), failed_at);

UPDATE departments SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at),
    deleted_at = IF(deleted_at < @cutoff, DATE_SUB(deleted_at, INTERVAL 8 HOUR), deleted_at);

UPDATE department_approvers SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE leave_types SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE leave_balances SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

-- applied_at is restored from the snapshot, not by a rule. Rows created after the
-- snapshot (not in the backup) keep their current applied_at.
UPDATE leave_applications la
LEFT JOIN _t006_applied_at_backup b ON b.id = la.id
SET
    la.applied_at = IF(b.id IS NOT NULL, b.applied_at, la.applied_at),
    la.decided_at = IF(la.decided_at < @cutoff, DATE_SUB(la.decided_at, INTERVAL 8 HOUR), la.decided_at),
    la.created_at = IF(la.created_at < @cutoff, DATE_SUB(la.created_at, INTERVAL 8 HOUR), la.created_at),
    la.updated_at = IF(la.updated_at < @cutoff, DATE_SUB(la.updated_at, INTERVAL 8 HOUR), la.updated_at),
    la.deleted_at = IF(la.deleted_at < @cutoff, DATE_SUB(la.deleted_at, INTERVAL 8 HOUR), la.deleted_at);

UPDATE leave_application_logs SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at);

UPDATE leave_balance_adjustments SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at);

UPDATE leave_type_user SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE leave_entitlement_tiers SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE leave_year_locks SET
    locked_at = IF(locked_at < @cutoff, DATE_SUB(locked_at, INTERVAL 8 HOUR), locked_at),
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE penalties SET
    collected_at = IF(collected_at < @cutoff, DATE_SUB(collected_at, INTERVAL 8 HOUR), collected_at),
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at),
    deleted_at = IF(deleted_at < @cutoff, DATE_SUB(deleted_at, INTERVAL 8 HOUR), deleted_at);

UPDATE work_experiences SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE achievements SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE staff_achievements SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE competence_categories SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE competence_votes SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at);

UPDATE performance_inputs SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE performance_components SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

UPDATE performance_entries SET
    created_at = IF(created_at < @cutoff, DATE_SUB(created_at, INTERVAL 8 HOUR), created_at),
    updated_at = IF(updated_at < @cutoff, DATE_SUB(updated_at, INTERVAL 8 HOUR), updated_at);

-- Allow the forward script to be run again later if needed:
DROP TABLE t006_timezone_shift_marker;
DROP TABLE _t006_applied_at_backup;

-- Verify: the pending leaves from the morning of 8 Oct are back to 01:21 / 01:22.
SELECT id, status, applied_at FROM leave_applications WHERE status = 'pending' ORDER BY applied_at DESC LIMIT 10;

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
