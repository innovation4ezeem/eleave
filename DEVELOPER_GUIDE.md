# E‑Leave / HR System — Developer & Maintenance Guide

This document is the maintenance handbook for the Laravel HR system (leave
management, penalties, performance, and staff profiles). It is written for a
developer who has **not** seen the codebase before and needs to understand,
run, extend, and safely deploy it.

> **Read the [Deployment & Operations](#12-deployment--operations) section before
> touching production.** This app runs on cPanel shared hosting with several
> non-obvious constraints (FTP-only deploys, the local `.env` points at the live
> database, OPcache dependency, migrations run without SSH). Getting these wrong
> is the fastest way to break the live site.

---

## 1. What the system is

An internal staff portal with these functional areas:

| Area | Summary |
|---|---|
| **E‑Leave** | Employees apply for leave; HOD/admin approve/reject; balances, carry‑forward, yearly rollover, historical backfill, printable reports. |
| **Change requests** | Employees request to amend or cancel an already‑decided leave; HOD/admin approve. |
| **Penalties & Warnings** | HOD/admin issue disciplinary warnings or cash penalties; cash collection tracked; employees notified. |
| **Performance** | (a) *Personal Competence* — anonymous peer voting in admin‑managed categories. (b) *Performance scores* — admin enters monthly values, an admin‑customizable formula computes a score. |
| **Profiles** | Each user has working‑experience and achievements sections, editable by self / admin / dept HOD. |

Three roles: **admin** (full access), **hod** (department‑scoped admin‑like
powers), **employee** (own data + peer voting).

---

## 2. Tech stack

- **PHP 8.3**, **Laravel ^13.8**
- **MySQL** (utf8mb4 — see the key‑length gotcha in [§6](#6-database)).
- **Blade** server‑rendered views + **Bootstrap 5** (CDN, no build step) +
  **flatpickr** (CDN) for date pickers. There is **no** Node/Vite build in the
  deployed app — assets are CDN `<link>`/`<script>` tags in
  `resources/views/layouts/app.blade.php`.
- Sessions & cache: **database** driver. Queue: **sync** (no worker).
- Auth: classic session cookies (Laravel starter‑kit style), no API/token layer.

> **Note:** the deployment directory on the server *also* contains an unrelated
> pre‑existing Next.js app (`server.js`, `next.config.mjs`, `package.json`, etc.)
> living alongside the Laravel files. **Do not touch those files.** The Laravel
> app is served from `public/` only; the Node files are inert for our purposes.

---

## 3. Local development setup

```bash
# 1. Install PHP deps
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate
# Edit .env — see the DB note below.

# 3. Run
php artisan serve      # http://127.0.0.1:8000
```

### ⚠️ The local `.env` points at the PRODUCTION database

`DB_HOST` in `.env` is the live MySQL server. This means:

- `php artisan migrate` **run locally applies to production.** There is no
  separate staging DB.
- `php artisan tinker` queries touch live data.

**Convention used throughout this project for safe verification:** wrap any
test that writes data in a transaction and roll it back, e.g.

```php
DB::beginTransaction();
try {
    // create test users/records, call the service, assert results...
} finally {
    DB::rollBack();   // nothing is persisted
}
```

Run these via `php artisan tinker --execute="require '/path/to/script.php';"`.
This is how every feature in this codebase was validated against real data
without mutating it. If you want a truly isolated local DB, point `.env` at a
local MySQL and run `php artisan migrate --seed`.

### Seed data

`database/seeders/` provides: `LeaveTypeSeeder` (the 11 leave types),
`DepartmentSeeder`, `AdminUserSeeder` (initial admin). `DatabaseSeeder` wires
them. The performance module's default inputs/components are seeded **inside
the migration** `2026_06_28_000001_create_performance_module_tables.php`, not a
seeder.

---

## 4. Roles & authorization

### Roles
`users.role` is an enum: `admin`, `hod`, `employee` (the `hod` value was added
by migration `2026_06_16_000001`). Helpers on `App\Models\User`:
`isAdmin()`, `isHod()`, `isAdminOrHod()`.

### Three authorization mechanisms (know which is used where)

1. **Route middleware** — `role:admin,hod` (alias defined in `bootstrap/app.php`,
   backed by `App\Http\Middleware\EnsureUserRole`). Gates whole route groups in
   `routes/web.php`. Used for the `/admin/*` area.

2. **Policies** (`app/Policies/`) — auto‑discovered. `LeaveApplicationPolicy`
   (view/cancel/decide/requestChange/decideChange) and `PenaltyPolicy`. These
   encode the recurring **"owner OR admin OR HOD‑of‑same‑department"** rule.

3. **Gate closure** — `manage-profile` in `AppServiceProvider::boot()`, used by
   the profile experience/achievements feature. (Historically the only
   `Gate::define`; a future cleanup could fold it into a policy — see
   [§14 Known issues](#14-known-issues--tech-debt).)

### The HOD department‑scoping pattern (used in ~6 controllers)

HOD‑scoped controllers repeat this guard:

```php
if (auth()->user()->isHod() && $user->department_id !== auth()->user()->department_id) {
    abort(403);
}
```

and scope index queries with `->where('department_id', auth()->user()->department_id)`.
`Admin\UserController`, `Admin\LeaveBalanceController`,
`Admin\HistoricalLeaveController`, `Admin\LeaveApplicationController`,
`Admin\PenaltyController`, `Employee\PerformanceController` all follow it.
**When adding a new admin feature, replicate this pattern** so HODs stay
confined to their department.

> Note the null‑department edge: `null !== null` is false, so a HOD with no
> department is not blocked from department‑less users by the raw guard. The
> `manage-profile` gate explicitly handles this (`department_id !== null`); the
> controller guards do not (known minor issue).

---

## 5. Request lifecycle & directory map

```
routes/web.php                 All routes. Grouped: guest / auth / role:admin,hod / role:admin.
app/Http/Controllers/
  Employee/                    Employee-facing (own data): LeaveApplication, LeaveBalance,
                               LeaveReport, Penalty, Competence, Performance.
  Admin/                       Admin+HOD area (dept-scoped for HOD).
  ProfileController            /profile page.
  WorkExperienceController     Profile sub-resource (shared by self + admin).
  AchievementController        "
app/Http/Requests/             FormRequest validation (Admin/, Employee/, and root for shared).
app/Models/                    Eloquent models (see §6).
app/Services/                  Business logic (see §7). Controllers stay thin; rules live here.
app/Policies/                  LeaveApplicationPolicy, PenaltyPolicy.
app/Console/Commands/          leave:rollover, leave:expire-carry-forward.
app/Support/LeaveYear.php      Leave-year date helper (see §8).
routes/console.php             The Laravel scheduler definitions.
resources/views/               Blade. partials/ holds navbar, flash-messages, and the
                               reusable profile sections.
database/migrations/           Schema history (see §6).
```

Controllers are intentionally thin; **domain rules belong in `app/Services`**
so they can be reused (e.g. the approval service is used by both the normal
approve flow and the historical backfill tool).

---

## 6. Database

### ⚠️ utf8mb4 index key‑length gotcha

The DB is utf8mb4. A `string()` column (255) under a `unique()` exceeds MySQL's
1000‑byte index limit (255×4 = 1020). **Unique/indexed string columns must be
`string('col', 191)`** (see `competence_categories.name`). If a
`create_*_table` migration fails with *"Specified key was too long; max key
length is 1000 bytes"*, this is why.

### Tables by module

**Core / auth**
- `users` — name, email, password, `role` enum(admin,hod,employee),
  `department_id`, `employee_no`, `designation`, `joined_at`, `is_active`,
  **soft deletes**.
- `departments`, `department_approvers` (legacy pivot; approver assignment UI was
  removed — HOD routing replaced it; the table still exists but is unused by the
  current flow).

**Leave**
- `leave_types` — the 11 types; `code`, `default_annual_quota`, `affects_balance`,
  `requires_document`, `is_active`, `sort_order`.
- `leave_type_user` — **pivot: which leave types each employee is entitled to.**
- `leave_entitlement_tiers` — years‑of‑service → days, per leave type
  (currently used for Annual).
- `leave_balances` — one row per (user, leave_type, leave_year).
  `remaining = entitlement_days + adjustment_days - used_days` (accessor
  `remaining_days`).
- `leave_balance_adjustments` — audit trail of every manual balance change
  (`changed_by` is **nullable** so the system 1‑March expiry can attribute to
  "system").
- `leave_applications` — the central table. Statuses: `pending`, `approved`,
  `rejected`, `cancelled`, `change_requested`. Change‑request columns:
  `prev_status`, `change_action` (amend|cancel), and `requested_*`
  (leave_type_id/start_date/end_date/start_half/end_half/total_days). Snapshots
  `department_id` and `leave_year`. Soft deletes.
- `leave_application_logs` — status‑change audit (`action`, from/to status, notes).
- `leave_year_locks` — which leave years are archived/read‑only.

**Penalties**
- `penalties` — user, department (snapshot), penalty_date, mistake,
  `penalty_type` (warning|cash), `penalty_level` (1–3), `penalty_charges`
  (cash only), document, reported_by, remarks, `collection_status`
  (pending|collected, cash only), collected_by/at, created_by. Soft deletes.

**Performance**
- `competence_categories`, `competence_votes` (immutable log: voter, votee,
  category, created_at only).
- `performance_inputs` (the admin‑defined input fields, keyed slugs used as
  formula variables), `performance_components` (name + `expression`),
  `performance_entries` (per user/year/month: `values` JSON, `scores` JSON
  **snapshot**, `total_score`).

**Profiles**
- `work_experiences` (title, start_date, end_date nullable=current, notes),
  `achievements` (title, achieved_on, description).

> Migrations are the source of truth for schema. To inspect live schema:
> `php artisan migrate:status` and `Schema::getColumnListing('table')` in tinker.

---

## 7. Services layer (`app/Services`)

| Service | Responsibility |
|---|---|
| `LeaveDayCalculator` | Counts working days (Mon–Fri) between two dates, applying half‑day (`am`/`pm`) deductions. Single point for day math. Holidays could be added here later. |
| `LeaveBalanceService` | `getOrCreateBalance()` (the **choke point** — provisions a balance row using the entitlement service), `deduct()`, `restore()`. |
| `LeaveEntitlementService` | `entitlementFor(user, type, year)` — returns the tier‑based days if the type has service tiers, else the flat `default_annual_quota`. `yearsOfService()` from `joined_at`. |
| `LeaveApplicationApprovalService` | Orchestrates approve/reject **and** the change‑request lifecycle (requestChange/approveChange/rejectChange). Handles balance deduct/restore, audit logs, and decision emails in one place. Shared by the normal flow and historical backfill. |
| `LeaveYearRolloverService` | `rolloverUser()`/`rolloverAll()` — computes carry‑forward and provisions the new year. `estimateCarryForward()` is reused by the balance screens to show a live next‑year estimate. |
| `PerformanceScoreService` | `compute(values)` — runs each active component's expression through the evaluator; returns per‑component scores + total + a `complete` flag. Missing inputs → that component is `null` (pending), excluded from the total (supports partial entries). |
| `ExpressionEvaluator` | A **safe** arithmetic parser (recursive descent, **no `eval()`**) for admin‑defined performance formulas. `evaluate()` and `validate()`. Supports `+ - * / ( )`, numbers, and variable identifiers; divide‑by‑zero → 0. |

---

## 8. Core domain concept: the Leave Year

`App\Support\LeaveYear` centralizes all leave‑year logic. **The leave year is
the calendar year (1 Jan – 31 Dec).**

- `forDate($date): int` → the calendar year of the date.
- `current(): int`, `startDate($y)` (1 Jan), `endDate($y)` (31 Dec).

> **History:** the leave year was originally 1 Feb – 31 Jan and was changed to
> the calendar year. Existing rows created under the old rule were **not**
> back‑filled — only newly derived `leave_year` values use the calendar rule.
> If you see old records whose `leave_year` looks off by one for January dates,
> that's why. Do not "fix" historical rows without a deliberate migration.

Every `leave_balances.leave_year` and `leave_applications.leave_year` is derived
via this helper. **Always use it — never compute the year inline.**

---

## 9. Feature deep‑dives (business rules)

### 9.1 Leave application & approval
- Employee applies (`Employee\LeaveApplicationController@store`). Supports
  **split half‑day** (AM one type + PM another → two 0.5‑day applications) via
  `split_leave`. Overlap prevented by `NoOverlappingLeave` rule +
  `LeaveApplication::scopeOverlapping` (which allows complementary AM/PM halves
  on the same day).
- Balance is **only deducted on approval**, via
  `LeaveApplicationApprovalService::approve()`, and only if
  `leave_type.affects_balance`. Reject/cancel never touch balances (pending
  never deducted).
- **Reward Leave restriction:** can only be applied when the employee's Annual
  Leave balance is fully exhausted (enforced in the store request).
- Submission emails go to the department's **HOD**; if none, fall back to all
  active admins.

### 9.2 Change / cancellation requests
- On an `approved`/`rejected` leave, employee submits either **cancel** or
  **amend** (new type/dates), with a reason → status `change_requested`,
  `requested_*` fields captured.
- HOD/admin approves: **amend applies the new values directly** (balance
  restored on old, deducted on new) and returns to `approved`; **cancel** →
  `cancelled` + balance restored. Reject → reverts to `prev_status`.
- All in `LeaveApplicationApprovalService`.

### 9.3 Per‑employee entitlement & service tiers
- `leave_type_user` decides which types appear in an employee's apply dropdown
  and which get provisioned each year. New users default to all active types.
  Admin manages this on the user edit page.
- `leave_entitlement_tiers` gives Annual Leave a years‑of‑service scale (e.g.
  0y→14, 5y→18). Resolved by `LeaveEntitlementService` at provisioning time.
  **Editing a tier does not rewrite existing balances** — it applies to newly
  provisioned rows (new users, next rollover).
- Balance display queries **must filter by entitled types** (a user keeps old
  balance rows after a type is un‑ticked). See the `whereIn(entitledTypeIds)`
  in the balance controllers.

### 9.4 Yearly rollover, carry‑forward, expiry (scheduled)
- **1 Jan 00:05** — `leave:rollover` (`routes/console.php`): for every active
  user, carry‑forward = `min(5, unusedAnnual + unusedReward)` from the prior
  year, written to the Carry Forward Leave balance; all entitled types are
  provisioned for the new year; the **previous year is auto‑locked**.
- **1 Mar 00:05** — `leave:expire-carry-forward`: zeroes any remaining Carry
  Forward Leave (usable Jan–Feb only) via an audited balance adjustment
  (`changed_by = null` = system). Used days are preserved.
- Balance screens show a **live carry‑forward estimate** for the immediate next
  year via `LeaveYearRolloverService::estimateCarryForward()`.

### 9.5 Year‑end lock / archive
- `leave_year_locks` + `LeaveYearLock::isLocked()` (request‑cached). A locked
  year is read‑only: employees can't cancel/change its leave, admins can't edit
  its balances, and new applications into it are rejected. The **Historical
  Leave tool is exempt** (admins can still backfill). Admin manages locks on the
  Leave Years page; rollover auto‑locks the prior year.

### 9.6 Penalties & warnings
- HOD (own dept) / admin issue Warning or Cash penalties.
  `Admin\PenaltyController`. Documents stored on the private `local` disk
  (`penalty-documents/{user_id}`), served via policy‑gated download route.
- **Cash** → emails all active admins to collect + tracks
  `collection_status` pending→collected (only **admin** marks collected).
- Employee is emailed on issue and sees their own records ("My Penalties",
  `Employee\PenaltyController`).

### 9.7 Performance — Personal Competence (voting)
- Any employee/HOD votes for any other (company‑wide; admins excluded), in
  admin‑managed categories, **unlimited**, no self‑vote. `competence_votes` is
  an immutable log.
- Recipients see **per‑category totals only** (never who voted). Admins see the
  full **Voting Log** and a **Competence Results** matrix (votes received per
  person × category, date‑range filterable). Controllers: `Employee\Competence`,
  `Admin\CompetenceCategory`, `Admin\CompetenceVoteLog`.

### 9.8 Performance — scored
- Admin defines **inputs** (formula variables) and **components** (name +
  arithmetic expression) on the Scoring Formula page
  (`Admin\PerformanceSettingController`). Seeded formula:
  `KPI=(achieved/objective)*50`, `Tasks=(completed/total_tasks)*25`,
  `Quality=((completed-error)/100)*25`.
- Admin enters **monthly** values per user (`Admin\PerformanceEntryController`);
  `PerformanceScoreService` computes the score. **Entries snapshot their scores**
  — editing the formula later does not rewrite historical entries.
- **Partial entries allowed:** blank inputs are stored as `null`; components
  referencing a missing input are marked pending (excluded from total). The
  entry shows a "Partial" badge until completed.
- Employees see own scores; HOD sees own + department; admin manages all.

### 9.9 Profiles — experience & achievements
- `work_experiences` + `achievements`, shown on `/profile` (self) and the admin
  user‑detail page. Editable by **self, admin, or the dept HOD (of an
  employee)** via the `manage-profile` gate. Inline add/edit uses Bootstrap
  collapse; a hidden `_form` field re‑opens the correct form on validation error.

---

## 10. Scheduled tasks (cron)

Defined in `routes/console.php`:

| When | Command | Purpose |
|---|---|---|
| 1 Jan 00:05 | `leave:rollover {year}` | New‑year balances + carry‑forward + lock prior year |
| 1 Mar 00:05 | `leave:expire-carry-forward {year}` | Expire unused carry‑forward |

**These only fire if the server cron is calling the scheduler.** The required
cPanel cron entry is:

```
* * * * *  /usr/local/bin/php /home/<acct>/hrsystem/artisan schedule:run >> /dev/null 2>&1
```

(Set the schedule fields in the cPanel UI to every‑minute; paste only the
command part.) To verify the cron is alive without waiting for a real run,
temporarily add an `everyMinute()` task that writes a cache timestamp, deploy,
poll it, then remove it (this is how it was verified previously).

To run a rollover/expiry manually (e.g. re‑run for a specific year):
`php artisan leave:rollover 2027` (locally = against prod DB, so intended).

---

## 11. Email / notifications

- Mailables in `app/Mail/` (leave submitted/decided, change‑requested, penalty
  issued, cash‑penalty‑to‑collect). Templates in `resources/views/emails/`.
- **All sends are wrapped in try/catch + `Log::error`** so a mail failure never
  blocks the action.
- Production uses **SMTP** (`MAIL_MAILER=smtp`, host `mail.hotelsystem.com.my`,
  port 465, `MAIL_SCHEME=smtps`). **Local dev uses `MAIL_MAILER=log`** — mails
  are written to `storage/logs/laravel.log`, not sent. This Laravel version uses
  `MAIL_SCHEME` (`smtps` for 465), **not** `MAIL_ENCRYPTION`.
- To change mail settings you edit the production `.env` — see the config‑cache
  caveat in [§12](#12-deployment--operations).

---

## 12. Deployment & operations

### Topology
- **Deploy target:** cPanel shared hosting, via **FTP** to
  `ftp://hotelsystem.com.my/hrsystem/`. Credentials are **not** in the repo
  (they live in the ops password store / the maintainer's notes). `.env` is
  git‑ignored.
- **Document root** is `hrsystem/public/`. App URL:
  `https://hrsystem.hotelsystem.com.my`.
- **Database** is a remote MySQL host (fast, same datacenter). The local `.env`
  targets it directly (see [§3](#3-local-development-setup)).

### Deploying code
There is **no CI/CD**. Deployment = FTP‑upload the changed files to the same
relative path under `hrsystem/`. Example (curl):

```bash
curl -s --user 'USER:PASS' -T "app/Http/Controllers/Foo.php" \
  "ftp://hotelsystem.com.my/hrsystem/app/Http/Controllers/Foo.php"
```

Then keep git in sync: commit and `git push origin master` (GitHub:
`innovation4ezeem/eleave`; `gh auth setup-git` wires credentials). **Deploy and
push are separate steps — do both** so the repo mirrors the server.

### Running migrations without SSH
The host has **no SSH**. Two options:

1. **Easiest:** run `php artisan migrate --force` **locally** — because the
   local `.env` points at the production DB, the migration applies to prod.
   (Upload the new migration file first for parity, though running it locally is
   what actually changes the schema.)
2. **Token‑gated self‑deleting script** (for when you must run artisan *on the
   server*, e.g. `config:clear`): upload a small PHP file to `public/` that
   checks a secret token, bootstraps the framework, runs the command, and
   `@unlink(__FILE__)`s itself. Trigger it once over HTTPS, confirm it 404s
   afterward. **Always** token‑gate and self‑delete these, and remove them from
   `public/` immediately.

### ⚠️ OPcache is required for acceptable performance
Without OPcache the app recompiles ~3000 files per request; on a contended
shared disk that produces **30–45s** page loads. **OPcache must be enabled** via
**cPanel → Select PHP Version → Extensions → tick `opcache`** (it is a PHP
*extension*, so `.user.ini`/`php.ini` cannot enable it if the extension isn't
loaded). With OPcache on, pages are sub‑second. If the site suddenly gets slow,
first confirm OPcache is still enabled (a minimal PHP probe: `opcache_get_status()`).

### ⚠️ Config cache caveat
Do **not** blindly `php artisan config:cache` on this host. If you do, remember
`.env` edits then require re‑caching (or delete `bootstrap/cache/config.php`).
A past attempt to cache config over the web coincided with a disk‑load spike and
looked like it made things worse. View caching is fine and beneficial. **Route
caching is discouraged** with the FTP workflow — a `Route::redirect`/closure and
the frequent route additions make stale route caches a footgun after deploys.

### Diagnosing "the site is slow/broken"
Ordered checklist (this exact sequence found the OPcache issue before):
1. Hit a **static file** (`/robots.txt`) and the **main domain** — if those are
   fast but Laravel is slow, it's PHP‑side, not network.
2. Time the **DB from the server** (a minimal PDO script) — usually 1–2ms; rules
   out the database.
3. Check **`opcache_get_status()`** — if the function doesn't exist, the
   extension is off (the usual culprit).
4. Only then consider app‑level issues.

`curl` from a script may be blocked by the host WAF unless you pass a browser
`User-Agent` — a 403 from `curl` doesn't mean the site is down.

---

## 13. Testing & verification approach

There is no automated test suite wired into deploy. The established practice:

- **Lint** every changed PHP file: `php -l path.php`.
- **Recompile views** to catch Blade errors: `php artisan view:clear && php artisan view:cache`.
- **Render views** with realistic data in tinker (share an empty
  `ViewErrorBag` first so `$errors`/`@error` don't error outside a request):
  `View::share('errors', new \Illuminate\Support\ViewErrorBag)` then
  `View::make(...)->render()`.
- **Business‑logic checks** in tinker inside a rolled‑back transaction (see
  [§3](#3-local-development-setup)). This is how rollover math, gate rules,
  score computation, etc. were all validated against real data safely.

`app/Services/LeaveDayCalculator` has (or should have) unit tests — services are
the right place to add PHPUnit coverage since they hold the rules.

---

## 14. Known issues & tech debt

From the last code review (non‑blocking, not yet fixed):

- **Null‑department HOD visibility:** the raw controller guard
  `$user->department_id !== auth()->department_id` treats `null !== null` as
  false, so a department‑less HOD isn't blocked from department‑less users. The
  `manage-profile` gate handles this correctly; the controller guards don't.
- **Authorization altitude:** `manage-profile` is a closure Gate while the rest
  of the app uses Policy classes; and the "owner/admin/HOD‑dept" rule is
  duplicated across `PenaltyPolicy`, `LeaveApplicationPolicy`, the gate, and the
  six inline controller guards. A shared `User::isHodOf()` helper + a policy
  would consolidate it.
- **Blade duplication:** `partials/work-experiences.blade.php` and
  `partials/achievements.blade.php` are near‑identical; the edit forms omit the
  inline `@error` markup the add forms have (a failed edit shows only the global
  error alert). The `admin/penalties/_form.blade.php` include pattern is the
  precedent to follow if refactoring.
- **`department_approvers`** table/relation is legacy and unused by the current
  HOD‑based routing.

---

## 15. Quick reference — "how do I…"

| Task | Where |
|---|---|
| Add a leave type | `leave_types` row (via seeder/migration or phpMyAdmin); set `code`, `default_annual_quota`, `affects_balance`, `requires_document`. |
| Change who can approve leave | It's HOD‑by‑department + admin. See `User::canApproveFor()` and `LeaveApplicationPolicy::decide`. |
| Adjust the performance formula | Admin UI → Performance → Scoring Formula (no code change; `ExpressionEvaluator` runs it). |
| Add a competence category | Admin UI → Performance → Competence Categories. |
| Re‑run a yearly rollover | `php artisan leave:rollover {year}` (locally = prod DB). |
| Give an employee more days one‑off | Admin → user Balances → edit `adjustment_days` (audited). |
| Change entitlement by seniority | Admin → Entitlement Tiers. |
| Add a new admin/HOD‑scoped page | Copy the HOD‑scoping pattern in §4; put rules in a service; add routes under the `role:admin,hod` group. |
| Send real email in dev | Set `MAIL_MAILER=smtp` in local `.env` (otherwise it logs). |

---

*Keep this document updated when you add a module or change a business rule —
it is the fastest on‑ramp for the next maintainer.*
