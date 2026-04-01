# AUDIT REPORT — Tirana Solidare

**Date:** Current  
**Scope:** Full codebase — all PHP views, API endpoints, action files, includes, frontend JS, config  
**Method:** Line-by-line code review of every major file  
**Baseline:** Commit `2d34b7a` (Premium redesign, 174/174 tests passing)

---

## TABLE OF CONTENTS

1. [Executive Summary](#1-executive-summary)
2. [What's Working Well](#2-whats-working-well)
3. [Critical Bugs — Breaks Features Today](#3-critical-bugs--breaks-features-today)
4. [Medium Issues — Logic Gaps](#4-medium-issues--logic-gaps)
5. [Low / Informational Issues](#5-low--informational-issues)
6. [Per-File Breakdown](#6-per-file-breakdown)
7. [Priority Fix List](#7-priority-fix-list)

---

## 1. EXECUTIVE SUMMARY

The codebase is in good shape. Core security fundamentals are correctly implemented: SQL injection protection via prepared statements throughout, bcrypt password hashing, CSRF token validation on all mutating requests, session regeneration on login, `require_auth()` re-verifying user status from the database on every API call. Several previously-flagged issues (Host header injection, double CSRF in upload, X-Forwarded-For in rate limiting, session cookie flags) have been fixed.

There are **1 critical bug** that breaks a feature outright, **5 medium logic gaps** with real user-facing consequences, and **5 low/informational items** worth noting.

| Severity | Count |
|----------|-------|
| Critical (feature broken) | 1 |
| Medium (logic gap / security) | 5 |
| Low / Informational | 5 |

---

## 2. WHAT IS WORKING WELL

These areas have been correctly implemented and should not be changed:

| Item | Detail |
|------|--------|
| SQL injection | 100% prepared statements. Zero raw string interpolation in queries. |
| Password hashing | `password_hash(PASSWORD_DEFAULT)` + `password_verify()`. No MD5/SHA1. |
| CSRF protection | Token generated per session, validated and rotated in `api/helpers.php` for every POST/PUT/DELETE. CSRF meta tag present in all views that need it. |
| `require_auth()` | Re-queries `statusi_llogarise`, `roli`, and `password_changed_at` from DB on every API call. Blocked and deactivated users are correctly ejected. Demoted admins are caught via session `password_changed_at` comparison. |
| `app_base_url()` | Uses `APP_URL` env variable; never trusts `HTTP_HOST`. Fallback is always `localhost`. |
| Rate limiting | Uses `REMOTE_ADDR` only; X-Forwarded-For correctly ignored. |
| Session cookies | `check_login()` sets `httponly`, `samesite=Lax`, and `secure` dynamically based on HTTPS detection. |
| File upload | Server-side MIME validation via `finfo`, GD image re-processing, size limits, WebP conversion. |
| Event lifecycle | Archived events cannot be edited; completed/cancelled events cannot be cancelled again; capacity enforced on admin approval via `FOR UPDATE` transaction. |
| Messaging | Correctly rejects messages to blocked or deactivated users (`statusi_llogarise !== active`). |
| Admin audit log | Block, unblock, role changes, event archive all written to `admin_log`. |
| Email queue | Queue with retry and exponential backoff. Synchronous direct-send for auth flows. |
| Soft deletes | `is_archived` on events preserves history; `statusi_llogarise = deactivated` on users preserves data. |
| API event list | Correctly excludes `statusi = cancelled` AND `is_archived = 1`. |
| Leaderboard | Only includes `verified = 1`, `statusi_llogarise = active`, `roli = volunteer` users. |

---

## 3. CRITICAL BUGS — BREAKS FEATURES TODAY

### BUG-01 — Report Type "Analiza e Eventeve" Always Fails (422)

**Files:** `api/stats.php` — generate action / `views/dashboard.php` — report modal

**Detail:**
The `generate` action validates the report type against a hardcoded list of two values:

```php
$validReportTypes = ['Permbledhje Mujore', 'Vullnetare Aktive'];
```

The dashboard modal has three buttons including "Analiza e Eventeve" which always returns 422.

**Fix:** Add the third type to the valid list and implement its generator case.

---

## 4. MEDIUM ISSUES — LOGIC GAPS

### MED-01 — Cancelled Events Appear in the Public Event List

**File:** `views/events.php` — list query

**Detail:**
The list query only filters by `is_archived`:

```php
$where = ['e.is_archived = 0'];
```

Cancelled events show in the public listing. The API endpoint (`api/events.php`) already correctly excludes them with `e.statusi != 'cancelled'`. The view-layer query must match.

**Fix:** `$where = ['e.is_archived = 0', "e.statusi != 'cancelled'"];`

---

### MED-02 — Cancelled Events Appear as Map Pins

**File:** `views/map.php` — events query

**Detail:**
```sql
WHERE e.latitude IS NOT NULL AND e.longitude IS NOT NULL AND e.is_archived = 0
```
No cancelled filter. Cancelled events with coordinates appear as clickable pins.

**Fix:** Add `AND e.statusi != 'cancelled'` to the events query.

---

### MED-03 — Session Timeout Not Enforced on View Pages

**Files:** `views/dashboard.php`, `views/volunteer_panel.php`

**Detail:**
Both views call `session_start()` and manually check `$_SESSION['user_id']` but never call `enforce_session_timeout()` or `check_login()`. The 1-hour inactivity timeout is only enforced when API endpoints are called. A user idling on the dashboard is never auto-logged-out on page refresh.

**Fix:** Replace the manual auth block with `check_login()` (which calls `enforce_session_timeout()` internally and handles session_start with correct cookie params).

---

### MED-04 — Email Verification TOCTOU

**File:** `src/actions/verify_email.php`

**Detail:**
Separate SELECT then UPDATE. Two concurrent requests with the same token can both SELECT before either UPDATEs. The UPDATE has no `WHERE verified = 0` guard.

**Fix:** Replace SELECT+UPDATE with a single atomic UPDATE that includes `AND verified = 0 AND verification_token_expires > NOW()` and check `rowCount() === 0` to detect invalid/already-used tokens. Eliminates the race entirely.

---

### MED-05 — Duplicate Application Check Before Transaction

**File:** `api/applications.php` — apply action

**Detail:**
The duplicate check `SELECT ... WHERE id_perdoruesi = ? AND id_eventi = ?` runs before `beginTransaction()`. Two simultaneous apply requests from the same user can both pass the dupe check before either inserts. The capacity check inside the transaction protects against over-capacity but not duplicate applications.

**Fix:** Move the duplicate SELECT inside the transaction with `FOR UPDATE`, or rely on a `UNIQUE KEY (id_perdoruesi, id_eventi)` constraint and catch PDO error code `23000`.

---

## 5. LOW / INFORMATIONAL ISSUES

### LOW-01 — API Register Skips Privacy Consent Check

**File:** `api/auth.php` — register action

Form-based register checks `privacy_consent`. The JSON API register endpoint does not. A direct API call can create an account without consent.

**Fix:** Add `if (empty($body['privacy_consent'])) { json_error(..., 422); }` to the API register action.

---

### LOW-02 — Broadcast Notification Link Allows Path Traversal

**File:** `api/notifications.php` — broadcast action

`str_starts_with($linku, '/TiranaSolidare/')` does not block `/TiranaSolidare/../../../path`. Only super admins can broadcast, limiting impact, but the validation should be stricter.

**Fix:** Add `|| str_contains($linku, '..')` to the rejection condition.

---

### LOW-03 — `verified` Field Missing from User List and Get

**File:** `api/users.php` — list and get actions

Admins cannot see which users have unverified email in the user management table.

**Fix:** Add `verified` to the SELECT in both list and get queries.

---

### LOW-04 — Categories Delete Nullifies Events Before Existence Check

**File:** `api/categories.php` — delete action

The `UPDATE Eventi SET id_kategoria = NULL` runs before checking if the category exists. Currently harmless (both UPDATE and DELETE are no-ops for non-existent IDs), but the ordering is wrong by principle.

**Fix:** Add an existence SELECT before the UPDATE.

---

### LOW-05 — Admin Notification Bell Hidden on All Public Pages

**File:** `public/components/header.php`

The notification bell is hidden for any user with admin role: `if (!$isAdminUser)`. Admins browsing public pages (events, map, etc.) see no notification indicator.

**Recommendation:** Show the bell for all logged-in users, with the bell linking to the dashboard for admin users.

---

## 6. PER-FILE BREAKDOWN

| File | Status | Notes |
|------|--------|-------|
| `api/auth.php` | Good | Register misses privacy_consent (LOW-01). |
| `api/helpers.php` | Good | require_auth re-verifies from DB. |
| `api/applications.php` | Medium | Dupe check outside transaction (MED-05). update_status capacity check correct. |
| `api/events.php` | Good | List excludes cancelled+archived. Update blocks archived. Cancel blocks completed. |
| `api/help_requests.php` | Good | Auth on all writes. get does not expose email. contact_applicant rate-limited. |
| `api/stats.php` | CRITICAL | generate validates only 2 types; UI has 3 (BUG-01). |
| `api/notifications.php` | Low | Broadcast link no traversal check (LOW-02). |
| `api/messages.php` | Good | Rate-limited. Rejects non-active receivers. Push notifications on send. |
| `api/export.php` | Good | Auth-gated. HTML+CSV exports. |
| `api/upload.php` | Good | No double CSRF. Delegates to handle_image_upload(). |
| `api/categories.php` | Low | DELETE ordering (LOW-04). |
| `api/users.php` | Low | verified field missing from list/get (LOW-03). |
| `views/dashboard.php` | Medium | No enforce_session_timeout (MED-03). |
| `views/volunteer_panel.php` | Medium | No enforce_session_timeout (MED-03). |
| `views/events.php` | Medium | List shows cancelled events (MED-01). CSRF meta present. |
| `views/map.php` | Medium | Cancelled events on map (MED-02). Help requests filtered to open. |
| `views/help_requests.php` | Good | Auth-gated applicants. Migration compat via try/catch. |
| `views/leaderboard.php` | Good | Filters verified+active+volunteer. Scoring consistent. |
| `views/public_profile.php` | Good | Privacy check correct. Admin redirect handled. |
| `views/login.php` | Good | Errors escaped. Flash messages rendered. |
| `views/register.php` | Good | Error keys mapped. No server-side logic in view. |
| `src/actions/login_action.php` | Good | CSRF validated. Rate limited. Session regenerated. |
| `src/actions/register_action.php` | Good | Privacy consent checked. Transaction with email rollback. |
| `src/actions/verify_email.php` | Medium | Non-atomic SELECT+UPDATE (MED-04). |
| `src/actions/forgot_password_action.php` | Good | app_base_url() used. Only sends to verified non-blocked accounts. |
| `src/actions/reset_password_action.php` | Good | Token validated. Hash stored. Token nullified after use. |
| `includes/functions.php` | Good | app_base_url() env-based. REMOTE_ADDR only. check_login() sets secure cookies. |
| `config/db.php` | Good | PDO ERRMODE_EXCEPTION. UTF-8 charset. |
| `public/components/header.php` | Low | Bell hidden for admins on public pages (LOW-05). |

---

## 7. PRIORITY FIX LIST

### P0 — Fix Now (Critical / High-Impact, ~20 min total)

| # | File | Fix |
|---|------|-----|
| 1 | `api/stats.php` | Add `Analiza e Eventeve` to valid report types + implement generator case |
| 2 | `views/events.php` | Add `e.statusi != 'cancelled'` to list `$where` array |
| 3 | `views/map.php` | Add `AND e.statusi != 'cancelled'` to events SQL |

### P1 — Fix Soon (Medium, ~45 min total)

| # | File | Fix |
|---|------|-----|
| 4 | `views/dashboard.php` | Replace manual session check with `check_login()` |
| 5 | `views/volunteer_panel.php` | Replace manual session check with `check_login()` |
| 6 | `src/actions/verify_email.php` | Atomic UPDATE with `AND verified = 0`, check rowCount() |
| 7 | `api/applications.php` | Move dupe check inside transaction or catch UNIQUE violation |

### P2 — Fix Before Next Release (Low, ~30 min total)

| # | File | Fix |
|---|------|-----|
| 8 | `api/auth.php` | Add `privacy_consent` check to API register |
| 9 | `api/notifications.php` | Reject links containing `..` |
| 10 | `api/users.php` | Add `verified` to list and get queries |
| 11 | `api/categories.php` | Check existence before nullifying events |
| 12 | `public/components/header.php` | Show notification bell for admin users |

---

**Total confirmed issues: 1 Critical · 5 Medium · 5 Low**

**End of Audit.**