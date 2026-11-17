-- =====================================================================
-- E-Leave balance audit  (READ-ONLY: SELECT statements only)
-- Run in phpMyAdmin against the live DB. Nothing here modifies data.
--
-- Change @yr to the leave year you want to check (Jan-Dec year).
-- Each query returns ONLY problem rows; an empty result = clean.
--
-- Replacement Leave is excluded everywhere: it is earned and adjusted by
-- hand all the time, so a mismatch there is not a discrepancy.
-- =====================================================================

SET @yr := 2026;

-- ---------------------------------------------------------------------
-- Q1. used_days vs approved applications  (the "extra AL" check)
--     diff < 0  => balance shows LESS used than approved leave
--                  => employee has EXTRA leave they shouldn't have
--     diff > 0  => balance shows MORE used than approved leave
--                  => employee is short-changed (or a soft-deleted
--                     approved application still holds a deduction)
--     recreated_after_approvals = 1 means the balance row was created
--     AFTER leave had already been approved against it -- the
--     fingerprint of the admin "Recalculate" button (it deletes and
--     recreates balances with used_days = 0).
-- ---------------------------------------------------------------------
SELECT u.id                        AS user_id,
       u.employee_no,
       u.name,
       lt.code                     AS leave_type,
       b.leave_year,
       b.entitlement_days,
       b.adjustment_days,
       b.used_days                 AS used_days_in_balance,
       COALESCE(a.approved_days,0) AS approved_days_in_applications,
       b.used_days - COALESCE(a.approved_days,0)                    AS diff,
       b.entitlement_days + b.adjustment_days - b.used_days         AS remaining_shown,
       b.entitlement_days + b.adjustment_days - COALESCE(a.approved_days,0) AS remaining_correct,
       b.created_at                AS balance_created_at,
       a.first_decided_at,
       (a.first_decided_at IS NOT NULL
        AND b.created_at > a.first_decided_at + INTERVAL 10 MINUTE) AS recreated_after_approvals
FROM leave_balances b
JOIN users u        ON u.id  = b.user_id
JOIN leave_types lt ON lt.id = b.leave_type_id AND lt.affects_balance = 1
LEFT JOIN (
    SELECT user_id, leave_type_id, leave_year,
           SUM(total_days)  AS approved_days,
           -- historical rows have a back-dated decided_at, so skip them here
           MIN(CASE WHEN is_historical = 0 THEN decided_at END) AS first_decided_at
    FROM leave_applications
    WHERE deleted_at IS NULL
      AND (status = 'approved'
           OR (status = 'change_requested' AND prev_status = 'approved'))
    GROUP BY user_id, leave_type_id, leave_year
) a ON a.user_id = b.user_id AND a.leave_type_id = b.leave_type_id AND a.leave_year = b.leave_year
WHERE b.leave_year = @yr
  AND lt.code <> 'replacement'
  AND b.used_days <> COALESCE(a.approved_days,0)
ORDER BY diff, u.name, lt.sort_order;

-- ---------------------------------------------------------------------
-- Q2. Approved applications with NO balance row at all
--     (imported straight into leave_applications, never deducted)
-- ---------------------------------------------------------------------
SELECT la.id, u.employee_no, u.name, lt.code AS leave_type, la.leave_year,
       la.start_date, la.end_date, la.total_days, la.status, la.is_historical
FROM leave_applications la
JOIN users u        ON u.id  = la.user_id
JOIN leave_types lt ON lt.id = la.leave_type_id AND lt.affects_balance = 1
LEFT JOIN leave_balances b
       ON b.user_id = la.user_id AND b.leave_type_id = la.leave_type_id AND b.leave_year = la.leave_year
WHERE la.leave_year = @yr
  AND lt.code <> 'replacement'
  AND la.deleted_at IS NULL
  AND (la.status = 'approved' OR (la.status = 'change_requested' AND la.prev_status = 'approved'))
  AND b.id IS NULL
ORDER BY u.name, la.start_date;

-- ---------------------------------------------------------------------
-- Q3. Entitlement vs what the tier rules say it should be
--     (only leave types that HAVE tiers. Types without tiers -- Carry
--     Forward, Replacement, Reward -- are granted by hand or by the
--     rollover, so there is no rule to compare against; CF is Q4).
--     Mirrors LeaveEntitlementService: years of service measured at
--     1 Jan of the leave year; probation/intern always use the 0-year
--     tier; status-specific tier preferred over generic at same min_years.
--     manual_entitlement_edits > 0 => an admin changed it on purpose
--     (check the reason in leave_balance_adjustments). NOTE: Recalculate
--     wipes that history, so 0 does not prove nobody touched it.
-- ---------------------------------------------------------------------
SELECT s.user_id, s.employee_no, s.name, s.employee_status, s.joined_at,
       s.leave_type, s.leave_year, s.years_of_service,
       s.entitlement_days AS stored_entitlement,
       COALESCE((
           SELECT t.days
           FROM leave_entitlement_tiers t
           WHERE t.leave_type_id = s.leave_type_id
             AND (t.employee_status IS NULL OR t.employee_status = s.employee_status)
             AND t.min_years <= s.years_of_service
           ORDER BY t.min_years DESC, (t.employee_status IS NULL) ASC
           LIMIT 1
       ), s.default_annual_quota) AS expected_entitlement,
       (SELECT COUNT(*) FROM leave_balance_adjustments adj
         WHERE adj.leave_balance_id = s.balance_id
           AND adj.field_changed = 'entitlement_days') AS manual_entitlement_edits
FROM (
    SELECT b.id AS balance_id, b.leave_year, b.entitlement_days, b.leave_type_id,
           u.id AS user_id, u.employee_no, u.name, u.employee_status, u.joined_at,
           lt.code AS leave_type, lt.default_annual_quota,
           CASE
               WHEN u.employee_status IN ('probation','intern') THEN 0
               WHEN u.joined_at IS NULL THEN 0
               WHEN u.joined_at > MAKEDATE(b.leave_year, 1) THEN 0
               ELSE TIMESTAMPDIFF(YEAR, u.joined_at, MAKEDATE(b.leave_year, 1))
           END AS years_of_service
    FROM leave_balances b
    JOIN users u        ON u.id  = b.user_id AND u.deleted_at IS NULL
    JOIN leave_types lt ON lt.id = b.leave_type_id AND lt.affects_balance = 1
    WHERE b.leave_year = @yr
      AND EXISTS (SELECT 1 FROM leave_entitlement_tiers t0 WHERE t0.leave_type_id = lt.id)
) s
HAVING stored_entitlement <> expected_entitlement
ORDER BY s.name, s.leave_type;

-- ---------------------------------------------------------------------
-- Q4. Carry Forward for @yr vs unused Annual + Reward of (@yr - 1),
--     capped at 5.  A mismatch usually means leave from the previous
--     year was approved/cancelled/edited AFTER the 1 Jan rollover ran.
-- ---------------------------------------------------------------------
SELECT u.id AS user_id, u.employee_no, u.name,
       cf.entitlement_days AS cf_stored,
       LEAST(5,
             GREATEST(0, COALESCE(al.entitlement_days + al.adjustment_days - al.used_days, 0))
           + GREATEST(0, COALESCE(rw.entitlement_days + rw.adjustment_days - rw.used_days, 0))
       ) AS cf_expected_now,
       COALESCE(al.entitlement_days + al.adjustment_days - al.used_days, 0) AS prev_al_remaining,
       COALESCE(rw.entitlement_days + rw.adjustment_days - rw.used_days, 0) AS prev_reward_remaining
FROM leave_balances cf
JOIN leave_types tcf ON tcf.id = cf.leave_type_id AND tcf.code = 'carry_forward'
JOIN users u ON u.id = cf.user_id
LEFT JOIN leave_balances al
       ON al.user_id = cf.user_id AND al.leave_year = cf.leave_year - 1
      AND al.leave_type_id = (SELECT id FROM leave_types WHERE code = 'annual')
LEFT JOIN leave_balances rw
       ON rw.user_id = cf.user_id AND rw.leave_year = cf.leave_year - 1
      AND rw.leave_type_id = (SELECT id FROM leave_types WHERE code = 'reward')
WHERE cf.leave_year = @yr
HAVING cf_stored <> cf_expected_now
ORDER BY u.name;

-- ---------------------------------------------------------------------
-- Q5. Applications decided AFTER their leave year was locked/rolled over
--     (approval is not blocked by the year lock; these change the old
--     year's balance after Carry Forward was already calculated)
-- ---------------------------------------------------------------------
SELECT la.id, u.employee_no, u.name, lt.code AS leave_type, la.leave_year,
       la.start_date, la.total_days, la.status, la.decided_at, l.locked_at
FROM leave_applications la
JOIN leave_year_locks l ON l.leave_year = la.leave_year
JOIN users u            ON u.id  = la.user_id
JOIN leave_types lt     ON lt.id = la.leave_type_id
WHERE la.deleted_at IS NULL
  AND la.decided_at > l.locked_at
  AND la.leave_year IN (@yr, @yr - 1)
ORDER BY la.decided_at;

-- ---------------------------------------------------------------------
-- Q6. Overlapping approved applications for the same person
--     (duplicate rows from an import -- double-deducts if both counted)
-- ---------------------------------------------------------------------
SELECT u.employee_no, u.name,
       a.id AS app_a, a.start_date AS a_start, a.end_date AS a_end, ta.code AS a_type, a.total_days AS a_days, a.is_historical AS a_hist,
       b.id AS app_b, b.start_date AS b_start, b.end_date AS b_end, tb.code AS b_type, b.total_days AS b_days, b.is_historical AS b_hist
FROM leave_applications a
JOIN leave_applications b
  ON b.user_id = a.user_id AND b.id > a.id
 AND b.start_date <= a.end_date AND b.end_date >= a.start_date
JOIN users u        ON u.id  = a.user_id
JOIN leave_types ta ON ta.id = a.leave_type_id
JOIN leave_types tb ON tb.id = b.leave_type_id
WHERE a.leave_year = @yr
  AND a.deleted_at IS NULL AND b.deleted_at IS NULL
  AND a.status = 'approved' AND b.status = 'approved'
ORDER BY u.name, a.start_date;

-- ---------------------------------------------------------------------
-- Q7. Carry Forward still showing a balance after 1 March
--     (=> the 1 March expiry cron did not run for that year)
-- ---------------------------------------------------------------------
SELECT u.employee_no, u.name, cf.leave_year,
       cf.entitlement_days + cf.adjustment_days - cf.used_days AS cf_remaining
FROM leave_balances cf
JOIN leave_types t ON t.id = cf.leave_type_id AND t.code = 'carry_forward'
JOIN users u ON u.id = cf.user_id
WHERE cf.leave_year = @yr
  AND CURDATE() >= MAKEDATE(@yr, 60)  -- 1 Mar (29 Feb in leap years; close enough)
  AND cf.entitlement_days + cf.adjustment_days - cf.used_days > 0
ORDER BY u.name;
