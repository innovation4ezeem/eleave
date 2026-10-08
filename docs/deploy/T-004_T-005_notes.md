# T-004 / T-005 notes: Warning Letters

Copied from the tracker tickets (parent T-001, phase "Warning Letter Update", due 16/10/2026).
These tickets REPLACE the earlier "filtered Penalty list" design: do not use the `Penalty` model or
`penalty_type = 'warning'`.

## T-004 Warning Letters tab
New table `warning_letters` (`user_id`, `issued_date`, `level` string, `notes` admin-only, private-disk
file with `original_filename` / `mime_type` / `file_size`, `uploaded_by`, timestamps, softDeletes).
`WarningLetterPolicy` enforces:
- Admin: view all, create, edit, delete.
- HOD: view-only for employees in the HOD's own department plus their own letters (null-department
  safe, filtered in the query).
- Employee: view-only for their own letters.

Files live on the private `local` disk under `warning-letters`, downloaded only through a
policy-checked route. Admin menu "Warning Letters"; HOD and employee get a read-only list (employees:
"My Warning Letters"). Production has no artisan: Laravel migration for local plus a phpMyAdmin SQL
script (`T-004_warning_letters.sql`).

## T-005 Warning Letter details
Delivered together with T-004: issued date, level, notes (admin-only, never shown to employees or
HODs), file download with the original filename, `uploaded_by`. The admin create/edit form uses a text
input for the level with a datalist of suggestions (Verbal, 1st Written, 2nd Written, Final, Other).

## Related
- Release the `warning_letters` table AFTER T-006 (timezone shift) has shipped.
- Disable the "Admin (For internal testing only)" account before Warning Letters goes live (tracker
  issue ISS-002).

## Audit log (added after T-004/T-005 delivery)
- `warning_letters.uploaded_by` stays the original issuer; `updated_by` (nullable) is the last admin who
  edited the letter or replaced its file.
- `warning_letter_events` logs issued / edited / file_replaced / deleted (admins) and downloaded (anyone).
  Details are short and never contain the notes. A replaced file is kept, renamed into
  `warning-letters/replaced/`, and its stored name is recorded in the event.
- History is shown to admins only, on the edit page. Access rules for letters are unchanged.
