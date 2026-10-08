USE hotelsys_hrsystem;
-- =============================================================================
-- T-004 / T-005 ROLLBACK: remove warning_letter_events and warning_letters (phpMyAdmin)
-- =============================================================================
--  !!  DESTROYS ALL WARNING LETTER RECORDS AND THEIR AUDIT HISTORY.        !!
--  !!  Export both tables first.                                           !!
--  !!  Uploaded files stay on disk in storage/app/private/warning-letters  !!
--      (including warning-letters/replaced/) - delete them by hand only if you are sure.
--
-- Reverse of T-004_warning_letters.sql. Restore the previous routes/web.php and
-- partials/navbar.blade.php (and remove the new warning-letter files) in the same release,
-- otherwise the menu and routes point at tables that no longer exist.
-- The events table must be dropped first: it has a foreign key to warning_letters.
-- =============================================================================

DROP TABLE `warning_letter_events`;
DROP TABLE `warning_letters`;

DELETE FROM `migrations`
WHERE `migration` IN (
    '2026_10_09_000001_create_warning_letters_table',
    '2026_10_09_000002_add_audit_to_warning_letters'
);

-- Verify: all three must return nothing.
SHOW TABLES LIKE 'warning_letter%';
SELECT * FROM `migrations` WHERE `migration` LIKE '%warning_letter%';
