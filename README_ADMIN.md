# TiranaSolidare — Super Admin Runbook

This document covers operational procedures for the `super_admin` role.
Keep it updated any time the super-admin account changes.

---

## 1. Roles overview

| Role | Can do |
|------|--------|
| `volunteer` | Apply to events, send messages, file help requests |
| `admin` | All of the above + create/edit events, approve applications, manage users |
| `super_admin` | All admin capabilities + promote/demote other admins, access audit log, cannot be deleted |

There is no "organisation" role — all events are admin-created.

---

## 2. Current super-admin account

> **Update this line whenever the super-admin email changes.**

| Field | Value |
|-------|-------|
| Email | *(fill in — do not commit credentials to public repos)* |
| Set via | DB command below |

---

## 3. Succession procedure

When handing over super-admin access:

1. Create (or identify) the new admin account in the application UI.
2. Connect to MySQL and run:

```sql
-- Promote new super admin
UPDATE Perdoruesi SET roli = 'super_admin' WHERE email = 'new-admin@example.com';

-- Demote previous super admin (if applicable)
UPDATE Perdoruesi SET roli = 'admin' WHERE email = 'old-admin@example.com';
```

3. Verify access by logging in as the new account.
4. Update section 2 of this file.

> **Warning:** Only one active `super_admin` is recommended. Having multiple means
> either can demote the other without warning.

> **Note:** The super-admin account cannot delete itself through the UI — this is
> an intentional safeguard to prevent accidental self-lockout.

---

## 4. Emergency access (locked out of UI)

If no `super_admin` account exists (e.g., after a data migration):

```sql
-- Find current admins
SELECT id_perdoruesi, emri, email, roli, verifikuar
FROM Perdoruesi
WHERE roli IN ('admin', 'super_admin')
ORDER BY krijuar_me;

-- Elevate a known admin
UPDATE Perdoruesi
SET roli = 'super_admin'
WHERE email = 'your-email@example.com';

-- Or create a verified volunteer and elevate them
UPDATE Perdoruesi
SET roli = 'super_admin', verifikuar = 1
WHERE email = 'your-email@example.com';
```

---

## 5. Email / SMTP configuration

Mail settings live in two places:

| Location | Purpose |
|----------|---------|
| `config/mail.php` | Reads env vars; constructs PHPMailer instance |
| `.env` (project root) | `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `SMTP_PORT`, `SMTP_FROM`, `SMTP_FROM_NAME` |

To change the sending address, update `SMTP_FROM` and `SMTP_FROM_NAME` in `.env`.
Never commit `.env` to version control.

Outgoing email is dispatched directly from `send_notification_email()` in
`includes/functions.php` — there is no background mail queue (the `email_queue`
table is written to, but `cron/process_emails.php` provides an optional fallback
processor).

---

## 6. Rate-limit table maintenance

Rate-limiting uses the `rate_limit_log` table.  Expired rows are pruned
automatically on ~1 % of requests (probabilistic cleanup in `check_rate_limit()`),
so no scheduled job is required.

To clear manually (e.g., to unblock a legitimate user locked out by the rate
limiter):

```sql
-- Remove all expired entries
DELETE FROM rate_limit_log WHERE attempted_at < NOW() - INTERVAL 1 HOUR;

-- Unblock a specific user for a specific action
DELETE FROM rate_limit_log WHERE rate_key LIKE 'withdraw_event_42_%';

-- Unblock a specific user for all actions
DELETE FROM rate_limit_log WHERE rate_key LIKE '%_42' OR rate_key LIKE '%_42_%';
```

---

## 7. Audit log

All admin actions (status changes, deletions, promotions) are recorded in the
`admin_log` table.

```sql
-- Most recent 50 admin actions
SELECT l.id, l.veprimi, l.detaje, p.emri AS admin_name, l.krijuar_me
FROM admin_log l
LEFT JOIN Perdoruesi p ON p.id_perdoruesi = l.id_admin
ORDER BY l.krijuar_me DESC
LIMIT 50;

-- Actions by a specific admin
SELECT * FROM admin_log
WHERE id_admin = <admin_id>
ORDER BY krijuar_me DESC;
```

---

## 8. Database backup (XAMPP)

```powershell
# Dump to timestamped file
& "C:\xampp\mysql\bin\mysqldump.exe" -u root TiranaSolidare `
  | Set-Content "TiranaSolidare_$(Get-Date -Format 'yyyyMMdd_HHmm').sql"
```

Restore:

```powershell
Get-Content backup.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root TiranaSolidare
```
