# T-006 Release: switch the app to GMT+8 (Asia/Kuala_Lumpur)

Production stores datetimes in UTC (a leave submitted at 09:21 MYT shows 01:21).
Laravel does not convert on read/write, so changing the config alone leaves old rows
8 hours behind. This release changes the timezone setting AND shifts old rows +8h once.

cPanel shared hosting, no SSH, no artisan: the data change is a plain SQL script run in
phpMyAdmin (not a Laravel migration).

## What changes on the server

| What | How |
|---|---|
| `/hrsystem/config/app.php` | **Edit in cPanel File Manager.** Change only `'timezone' => 'UTC'` to `'timezone' => 'Asia/Kuala_Lumpur'`. Do not upload the repo copy: the live file may differ. |
| `/hrsystem/bootstrap/cache/config.php` | Delete it if it exists (otherwise the change is ignored). |
| Database `hotelsys_hrsystem` | Run `docs/deploy/T-006_timezone_shift.sql` once in phpMyAdmin. Do not upload the SQL files. |

No other code file changes. The repo also gains `tests/Feature/AppTimezoneTest.php`
(not needed on the server).

## Release order

1. **Export the database** (phpMyAdmin > Export > SQL). Zip `/hrsystem` (minus `vendor`
   and `storage`) as a file backup too. **Verify the export before going on:** the file size is
   in the same range as the previous export, and it contains the newest rows (for example
   leave id 2338 or higher). It is the only way back: 11 live tables are MyISAM and cannot be
   rolled back. Do the release in the evening, using the files from `docs/deploy/` only (first
   line `USE hotelsys_hrsystem;`), never a local test copy.
2. Open `T-006_timezone_shift.sql` in phpMyAdmin and run only the PREFLIGHT `SELECT`s.
   Check `NOW()` is `UTC_TIMESTAMP()` + 8 hours (MySQL runs on Malaysia time), and note
   the applied_at bucket counts (live when written: A=1474, B=281, C=1). A big change in
   B or C: stop and ask the developer.
3. **Edit `/hrsystem/config/app.php`** in File Manager (see above). Delete
   `bootstrap/cache/config.php` if present. If OPcache is on, wait for it to expire or ask
   the host to reset it.
4. **Get the cutoff.** Immediately run in phpMyAdmin:
   ```sql
   SELECT UTC_TIMESTAMP() + INTERVAL 4 HOUR;
   ```
   Use the result, as is, as `@CUTOFF@`.
5. In the SQL script replace `@CUTOFF@` (one place) with that value, e.g.
   `2026-10-20 07:14:07`, then run the whole script. Keep "ignore errors" unticked. Run it
   within a couple of minutes of step 3. The script rejects a cutoff that is not 1-7 hours
   ahead of the moment it runs. If it stops on the `INSERT INTO t006_timezone_shift_marker`
   line (bad cutoff or placeholder not replaced), nothing was changed: run
   `DROP TABLE t006_timezone_shift_marker;`, fix the value and run again. If it stops on the
   `CREATE TABLE t006_timezone_shift_marker` line, it already ran: do not run it again.
   **If it stops anywhere after that (during the UPDATEs): do NOT run `ROLLBACK;`, do NOT re-run
   it and do NOT run the rollback script.** 11 live tables are MyISAM (achievements,
   competence_categories, competence_votes, leave_entitlement_tiers, leave_type_user,
   leave_year_locks, penalties, performance_components, performance_entries, performance_inputs,
   work_experiences), which cannot be rolled back, so the data would be half shifted. Restore the
   database from the export taken in step 1 and tell the developer.
6. Run the checks below.
7. **cPanel > Cron Jobs:** confirm there is an entry that runs `php artisan schedule:run`
   every minute (the rollover is scheduled in `routes/console.php`). Rollover now fires at
   00:05 Malaysia time instead of 08:05. If there is no entry, tell the developer.

### Why the cutoff is "now + 4 hours"

Let G be the moment the new timezone went live (UTC clock). Every old row holds a UTC value
below G. Every new row is written in Malaysia time, so its value is at least G + 8 hours.
Any cutoff inside that gap classifies both correctly; "now + 4h" is the middle of it, so
being a few hours early or late is safe. The cutoff is applied **per column**
(`col = IF(col < cutoff, col + 8h, col)`), so a leave created before go-live and approved
after it only has `created_at` shifted; `decided_at` and `updated_at` are already Malaysia
time and are left alone. There is no row-level `WHERE` in any UPDATE.

### applied_at (leave_applications) is shifted by a rule

`applied_at` has `ON UPDATE CURRENT_TIMESTAMP` and MySQL runs on Malaysia time, so rows
updated after creation already hold Malaysia time while the other columns are UTC. The
script shifts `applied_at` +8h only when it is within 5s of `created_at` and `created_at`
is below the cutoff (bucket A). Bucket B (`applied_at = updated_at + 8h`) and bucket C
(anything else, e.g. id 2212) are left alone. No `ALTER TABLE`. It copies `applied_at` to
`_t006_applied_at_backup` first; the rollback restores it from that copy.

## Checks after the release

1. The two pending leaves from the morning of 8 Oct that showed 01:21 / 01:22 now read
   **09:21 / 09:22**.
2. applied_at consistency. Expected result: **exactly 1** (id 2212 only):
   ```sql
   SELECT COUNT(*) FROM leave_applications
   WHERE ABS(TIMESTAMPDIFF(SECOND, created_at, applied_at)) > 5
     AND ABS(TIMESTAMPDIFF(SECOND, updated_at, applied_at)) > 5;
   ```
   and `SELECT id, applied_at FROM leave_applications WHERE id = 2212;` must still show
   `2026-09-10 10:00:28`.
3. Submit a **new test leave**: it must show the correct local (Malaysia) time, not +8h
   again.
4. The script's `rows_in_future` SELECT shows 0 for every row.
5. The script's last two result sets: the list of every table and column it touched, and a
   coverage check of datetime/timestamp columns it did NOT touch. Expected: only
   `_stg_leave_applications` columns. Anything else is a missed column: tell the developer.
6. Once signed off, drop the helper tables:
   `DROP TABLE t006_timezone_shift_marker;` and `DROP TABLE _t006_applied_at_backup;`

## Scope

- **Shifted:** every datetime/timestamp column of the 22 Laravel/app tables in the script
  (the touched list at the end of the script prints them). `staff_achievements` is included;
  it exists on live but has no Laravel migration.
- **Not touched:** `_stg_leave_applications` (not a Laravel table; nothing in the code reads
  it). Pure DATE columns and integer unix timestamps (`sessions.last_activity`,
  `cache.expiration`, `jobs.*_at`).
- **`warning_letters` (T-004) is not in the script** because it does not exist yet. If T-004
  is released BEFORE T-006, add `warning_letters` (all its datetime columns) to the shift, the
  rollback and the touched list, using the same per-column rule.
- Historical leave records store a user-picked date in `decided_at` (00:00:00). After the
  shift it reads 08:00:00 on the same date; the date does not change. A user-picked
  `applied_at` is left as is (bucket C), which is why check 2 counts such rows too.
- Both SQL files start with `USE hotelsys_hrsystem;`.

## Rollback

1. Export the database again. Edit `/hrsystem/config/app.php` back to `'timezone' => 'UTC'`
   and delete `bootstrap/cache/config.php` if present.
2. Run `T-006_rollback.sql`. It reads the cutoff from the marker table and reverses every
   column below `cutoff + 4h` (about go-live + 8h), restores `applied_at` from
   `_t006_applied_at_backup`, then drops the marker and the backup.
3. Rows created after go-live keep Malaysia values and will read 8h ahead. Rows written
   between editing the config and taking the cutoff (normally a minute or two) may be
   reversed by mistake.

## Local real-copy test plan (Laragon)

Do this before touching the live site. Never point it at the live database.

1. **Export live:** phpMyAdmin on the live site > database `hotelsys_hrsystem` > Export >
   Custom > structure AND data, SQL format. Keep the file as `live_export.sql`.
2. **Import locally:** in Laragon start MySQL/MariaDB, open phpMyAdmin or HeidiSQL, create an
   empty database named `hotelsys_hrsystem`, and import `live_export.sql`.
3. **Engines:** run `SELECT VERSION(), @@global.time_zone, NOW(), UTC_TIMESTAMP();` on live
   and on local and note both. If the engine or version differs (MySQL vs MariaDB), keep that
   in mind; the script uses only standard functions. Laragon's server clock is probably not
   Malaysia time; if so, set `default-time-zone='+08:00'` under `[mysqld]` in Laragon's
   `my.ini` and restart, so `ON UPDATE CURRENT_TIMESTAMP` behaves like production.
4. **Export the local copy as the baseline:** `mysqldump --skip-extended-insert
   --no-create-info hotelsys_hrsystem > before.sql` (or Export > Custom > data only).
5. **Run the script with a test cutoff** on the local copy: do the PREFLIGHT, then
   `SELECT UTC_TIMESTAMP() + INTERVAL 4 HOUR;`, put the result in `@CUTOFF@` and run it.
   The cutoff must be 1-7 hours ahead of the moment you run it.
   Note: on a copy made from live, every existing row is "old" (below the cutoff), so all of
   it shifts, which is what you want to inspect.
6. **Run the checks** above (bucket counts, consistency query = 1, id 2212 unchanged, the
   touched list and coverage report, the 01:21 / 01:22 leaves now 09:21 / 09:22).
7. **Run the rollback** `T-006_rollback.sql`.
8. **Confirm the data matches the original:** dump again to `after.sql` with the same command
   as step 4 and compare (`fc before.sql after.sql` in cmd, or a diff tool). Expected: no
   differences. Then drop nothing else: the rollback already dropped the helper tables.
