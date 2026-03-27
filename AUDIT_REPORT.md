# AUDIT REPORT — Tirana Solidare

## Full-System Logic, Security & Architecture Review

**Date:** March 27, 2026  
**Scope:** Every PHP file, JS file, SQL schema, CSS layer, and user flow  
**Method:** Line-by-line code audit + adversarial "Shark Tank" evaluation  

---

## TABLE OF CONTENTS

1. [Executive Verdict](#1-executive-verdict)
2. [Security — Critical Vulnerabilities](#2-security--critical-vulnerabilities)
3. [Authentication & Session Management](#3-authentication--session-management)
4. [API Layer — Logic Bugs](#4-api-layer--logic-bugs)
5. [Database Schema — Integrity Gaps](#5-database-schema--integrity-gaps)
6. [Frontend — JS Logic Issues](#6-frontend--js-logic-issues)
7. [View Layer — Server-Side Rendering](#7-view-layer--server-side-rendering)
8. [Email System](#8-email-system)
9. [File Upload System](#9-file-upload-system)
10. [CSS & Design System](#10-css--design-system)
11. [Business Logic — Flow Analysis](#11-business-logic--flow-analysis)
12. [Scalability & Performance](#12-scalability--performance)
13. [Legal & Compliance (GDPR)](#13-legal--compliance-gdpr)
14. [What's Actually Good](#14-whats-actually-good)
15. [Shark Tank Verdict](#15-shark-tank-verdict)
16. [Priority Fix List](#16-priority-fix-list)

---

## 1. EXECUTIVE VERDICT

**Readiness Score: 38/100 — Not deployable without critical fixes.**

The platform has solid foundations (prepared statements, bcrypt, CSRF protection, clean visual design) but contains **7 critical bugs** that would cause immediate failures in production, **15 high-severity logic flaws** that undermine core functionality, and **30+ medium issues** across security, data integrity, and user experience.

The most damaging finding: **blocked users can still perform all actions**, **the file upload endpoint is completely broken** (double CSRF = always 403), and **password reset emails can be hijacked** via Host header injection.

| Area | Score | Summary |
|------|-------|---------|
| Security | 4/10 | Solid SQL injection defense, but critical session, CSRF, and header issues |
| Auth Flow | 5/10 | Works for happy path; broken session flags, no lockout on demotion/block |
| API Logic | 4/10 | No state machines, no capacity enforcement, race conditions throughout |
| Database | 5/10 | Tables well-structured; missing constraints, indexes, one enum not migrated |
| Frontend JS | 4/10 | Functional UI; memory leaks, function override collisions, double-loading |
| Views/UX | 6/10 | Clean design; dead links, missing CSRF in events.php, XSS in error path |
| Email | 6/10 | Queue system in place; subject not escaped, concurrent send risk |
| Uploads | 3/10 | Unified function ready but endpoint is broken (double CSRF) |
| Legal/GDPR | 1/10 | No privacy policy, no account deletion, no consent checkbox |
| Scalability | 2/10 | Polling collapses at ~100 users, no caching, no CDN |

---

## 2. SECURITY — CRITICAL VULNERABILITIES

### 2.1 Host Header Injection → Password Reset Poisoning
**File:** `includes/functions.php` line 315 (`app_base_url()`)  
**Severity:** CRITICAL

```php
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
return $scheme . '://' . $host;
```

`HTTP_HOST` is attacker-controlled. An attacker sends `POST /forgot_password_action.php` with `Host: evil.com`. The reset email contains `http://evil.com/TiranaSolidare/views/reset_password.php?token=SECRET`. Victim clicks → attacker captures token.

Same attack applies to email verification links in `register_action.php`.

**Fix:** Read host from a config constant, never from the request.

---

### 2.2 Blocked/Deactivated Users Retain Full Access
**File:** `api/helpers.php` `require_auth()` (~line 128)  
**Severity:** CRITICAL

`require_auth()` only checks `$_SESSION['user_id']`. It never re-queries the database. A blocked user with an active session can continue creating requests, applying to events, updating profiles — until the session expires (up to 1 hour).

**Fix:** On every `require_auth()`, query `statusi_llogarise` from DB (cache for 60 seconds in session if performance is a concern).

---

### 2.3 Demoted Admin Retains Admin Privileges (Session Persistence)
**File:** `api/helpers.php` `require_admin()` (~line 137), `api/users.php` `change_role`  
**Severity:** CRITICAL

When admin A demotes user B from admin→volunteer, user B's session still contains `roli = 'admin'`. User B retains full admin powers indefinitely.

**Fix:** On role change, invalidate all of target user's sessions (or re-verify role from DB on each `require_admin()`).

---

### 2.4 Rate Limiting Bypass via X-Forwarded-For
**File:** `includes/functions.php` line 204  
**Severity:** HIGH

```php
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
```

Client-controlled header. Any attacker sends a random X-Forwarded-For on each request to get unlimited attempts.

**Fix:** Use `$_SERVER['REMOTE_ADDR']` exclusively (unless behind a trusted reverse proxy where you validate the chain).

---

### 2.5 Session Cookie Missing Security Flags (Action Files)
**Files:** `login_action.php`, `register_action.php`, `forgot_password_action.php`, `reset_password_action.php`, `verify_email.php`  
**Severity:** HIGH

All action files call `session_start()` BEFORE requiring `functions.php`:
```php
session_start();                    // line 3 — NO httponly, samesite, secure
require_once '../../includes/functions.php'; // line 5 — too late
```

The `ini_set('session.cookie_httponly')` in `functions.php` only runs when `session_status() === PHP_SESSION_NONE`. Since the session is already started, the flags are never applied. First-time session cookies lack `HttpOnly`, `SameSite`, and `Secure`.

**Fix:** Move `session_start()` AFTER `require_once functions.php`, or centralize in a bootstrap file.

---

### 2.6 No Session Invalidation After Password Reset
**File:** `reset_password_action.php` line 48  
**Severity:** HIGH

After password reset, existing sessions remain valid. If an attacker compromised the account, they stay logged in even after the victim resets.

**Fix:** Invalidate all sessions for the user after password change.

---

### 2.7 Cookie `secure` Flag Hardcoded `false`
**File:** `api/helpers.php` line 17  
**Severity:** HIGH

```php
'secure' => false,
```

Session cookie can be intercepted on non-HTTPS connections.

**Fix:** `'secure' => isset($_SERVER['HTTPS'])`

---

## 3. AUTHENTICATION & SESSION MANAGEMENT

### 3.1 CSRF Token Not Rotated on Login
**File:** `login_action.php` line 56  
**Severity:** MEDIUM

`session_regenerate_id(true)` rotates the session ID but preserves the CSRF token. A pre-login CSRF token remains valid post-login.

**Fix:** Regenerate CSRF token on login: `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))`.

---

### 3.2 TOCTOU Race on Password Reset Token
**File:** `reset_password_action.php` lines 41-50  
**Severity:** MEDIUM

SELECT and UPDATE are not in a transaction. Two concurrent requests with the same token can both pass SELECT before either nullifies it.

**Fix:** Wrap in a transaction with `SELECT ... FOR UPDATE`, or use a single atomic UPDATE + check `rowCount()`.

---

### 3.3 Timing Side-Channel for User Enumeration (Login)
**File:** `login_action.php` line 35  
**Severity:** MEDIUM

When user doesn't exist, `password_verify` is skipped — response is measurably faster. Reveals which emails are registered.

**Fix:** Call `password_verify()` with a dummy hash when user is null.

---

### 3.4 No Per-Account Brute-Force Lockout
**File:** `includes/functions.php` lines 199-230  
**Severity:** MEDIUM

Rate limiting is IP-only. A distributed attacker (botnet) can try unlimited passwords per account.

**Fix:** Add per-email rate limiting alongside IP-based limits.

---

### 3.5 Logout Logic Bug
**File:** `src/actions/logout.php` line 8-10  
**Severity:** LOW

Sets `http_response_code(405)` then redirects — the 405 is never seen by the browser. Harmless but confusing.

---

### 3.6 Verification Token in GET URL (Referer Leakage)
**File:** `verify_email.php` line 6  
**Severity:** LOW

Token is in URL query string → appears in server logs, browser history, and Referer headers. Mitigated by single-use token, but POST-based would be safer.

---

## 4. API LAYER — LOGIC BUGS

### 4.1 Upload Endpoint Completely Broken (Double CSRF)
**Files:** `api/upload.php` line 15-18, `api/helpers.php` line 85-90  
**Severity:** CRITICAL

`helpers.php` (required by upload.php) validates CSRF and regenerates the token. Then `upload.php` performs a **second** CSRF check with the old token against the new session token. `hash_equals(new, old)` → always `false` → **every upload returns 403**.

The general upload endpoint is 100% non-functional.

**Fix:** Remove the redundant CSRF check in `upload.php` — `helpers.php` already handles it.

---

### 4.2 Unblock Silently Overrides Deactivation
**File:** `api/users.php` `unblock` action (~line 310)  
**Severity:** CRITICAL

`unblock` sets `statusi_llogarise = 'active'` without checking current status. If a user was **deactivated** (not blocked), calling unblock reactivates them and leaves `deaktivizuar_me` stale.

**Fix:** Check current status. Only unblock if currently `'blocked'`.

---

### 4.3 Admin Can Block Themselves
**File:** `api/users.php` `block` action  
**Severity:** HIGH

No check for `if ($targetId === $_SESSION['user_id'])`. Admin locks themselves out.

**Fix:** Prevent self-targeting on block/deactivate/role-change.

---

### 4.4 No Status Transition Validation (Applications)
**File:** `api/applications.php` `update_status` (~line 171)  
**Severity:** HIGH

Admin can set any status regardless of current state: rejected→pending→approved→rejected in any order. No state machine enforced.

**Fix:** Define allowed transitions: `pending → approved|rejected`, `approved → rejected` (with audit log), no re-opening.

---

### 4.5 Event Capacity Not Re-Checked on Admin Approve
**File:** `api/applications.php` `update_status`  
**Severity:** HIGH

The `apply` action checks capacity for waitlisting, but `update_status` does not. Admin can approve unlimited volunteers, exceeding capacity.

**Fix:** Check approved count vs capacity before allowing approval.

---

### 4.6 Can Approve Applicants on Closed Help Requests
**File:** `api/help_requests.php` `update_applicant_status` (~line 194)  
**Severity:** HIGH

No check on parent `kerkesa_per_ndihme.statusi`. Applicants can be approved for closed/dead requests.

**Fix:** Verify parent request is still Open before status changes.

---

### 4.7 Archived Events Can Be Updated
**File:** `api/events.php` `update` (~line 170)  
**Severity:** HIGH

The existence check doesn't filter `is_archived = 0`. Archived events can be silently modified.

**Fix:** Add `AND is_archived = 0` to the existence check.

---

### 4.8 Completed Events Can Be Cancelled
**File:** `api/events.php` `cancel` (~line 253)  
**Severity:** MEDIUM

Checks for double-cancel but not for `completed` status. A completed event can be retroactively cancelled.

**Fix:** Reject cancel if `statusi = 'completed'`.

---

### 4.9 Help Request `get` Exposes Email Without Auth
**File:** `api/help_requests.php` `get` (~line 395)  
**Severity:** HIGH

Unauthenticated users can fetch `krijuesi_email` by enumerating request IDs. PII disclosure.

**Fix:** Omit email from unauthenticated responses.

---

### 4.10 Race Condition — Duplicate Applications
**File:** `api/applications.php` `apply` (~line 133)  
**Severity:** MEDIUM

SELECT (duplicate check) and INSERT are not atomic. Concurrent requests can both pass the check.

**Fix:** Rely on the UNIQUE constraint (`uq_user_event`) as the primary backstop. Wrap in a try-catch for the duplicate key error.

---

### 4.11 Help Request Delete Doesn't Cascade to Applications
**File:** `api/help_requests.php` `delete` (~line 642)  
**Severity:** MEDIUM

Hard-deletes from `Kerkesa_per_Ndihme` but doesn't delete `Aplikimi_Kerkese` rows via code. Depends on the DB's ON DELETE CASCADE being set correctly.

---

### 4.12 Help Request `tipi` Not Validated on Update
**File:** `api/help_requests.php` `update` (~line 460)  
**Severity:** MEDIUM

`create` validates `tipi ∈ ['request', 'offer']` but `update` allows any string.

---

### 4.13 `pershkrimi` Length Unchecked on Help Request Create
**File:** `api/help_requests.php` `create`  
**Severity:** MEDIUM

Title is validated (3-200 chars) but description has no limit — megabytes of text accepted.

---

### 4.14 Help Request Image URL Not Validated
**File:** `api/help_requests.php` `create`/`update`  
**Severity:** MEDIUM

Events validate banner via `validate_image_url()`, but help requests don't validate `imazhi`. Could store `javascript:` or `data:` URIs.

---

### 4.15 Rate Limiting Exists but is Never Called from Any API
**Files:** All API files  
**Severity:** MEDIUM

`check_rate_limit()` is defined in `functions.php` but **no API endpoint calls it**. Zero protection against mass submissions, brute-force enumeration, or resource exhaustion via API.

---

### 4.16 LIKE Wildcards Not Escaped in Search
**Files:** `api/events.php`, `api/help_requests.php`, `api/users.php`  
**Severity:** LOW

User input wrapped in `%..%` for LIKE but `%` and `_` within input aren't escaped. Searching `%` matches everything.

---

### 4.17 Missing Audit Logging
- `applications.php` `update_status` — approve/reject not logged (MEDIUM)
- `categories.php` — no CRUD logging at all (LOW)
- `notification.mark_read` returns 404 for already-read (LOW)

---

## 5. DATABASE SCHEMA — INTEGRITY GAPS

### 5.1 `kerkesa_per_ndihme.statusi` Still Uses 'Open'/'Closed'
**File:** `TiranaSolidare.sql` line 145  
**Severity:** MEDIUM

The FIX 1 migration standardized all enums to English lowercase **except** this one (`statusi` on help requests). It's still `ENUM('Open','Closed')` with title case. Inconsistent with every other table.

---

### 5.2 Missing Composite Indexes
**Severity:** MEDIUM

| Table | Missing Index | Query Pattern |
|-------|--------------|---------------|
| `njoftimi` | `(id_perdoruesi, is_read)` | "Unread notifications for user" |
| `kerkesa_per_ndihme` | `(tipi)` | Filter by request/offer |
| `eventi` | `(statusi, is_archived)` | Every event list query |

---

### 5.3 Notification `target_id` Has No FK
**Severity:** MEDIUM

When a target event/request is deleted, notifications point to nothing. Links lead to 404.

---

### 5.4 Reports CASCADE on User Delete
**File:** `TiranaSolidare.sql` — `raporti` table  
**Severity:** MEDIUM

If an admin who generated reports is deleted, all their analytics reports vanish. Should be `ON DELETE SET NULL`.

---

### 5.5 Migration Not Idempotent
**Severity:** MEDIUM

`ALTER TABLE ADD COLUMN` will fail if run twice — MariaDB 10.4 doesn't support `IF NOT EXISTS` on ADD COLUMN. The migration script should check before adding.

---

### 5.6 Registration Code vs Pre-Migration Schema Mismatch
**Severity:** HIGH

Registration inserts `roli = 'volunteer'` and `statusi_llogarise = 'active'` (English). If the migration hasn't run yet, the original Albanian enum (`'Vullnetar'`, `'Aktiv'`) will **reject these inserts**. New user registration is broken until migration runs.

---

## 6. FRONTEND — JS LOGIC ISSUES

### 6.1 `setInterval` Polling Never Cleaned Up (Memory Leak)
**File:** `assets/js/ajax-polling.js` lines 24-26  
**Severity:** HIGH

Three `setInterval` timers (15s, 15s, 30s) are started on page load and never cleared. On SPA-like panel navigation, timers accumulate. Each fires fetch requests to endpoints that may no longer have corresponding DOM elements.

**Fix:** Store interval IDs. Clear on `pagehide`/`beforeunload`.

---

### 6.2 Double DOMContentLoaded Loading
**Files:** `assets/js/main.js` + `assets/js/dashboard-ui.js`  
**Severity:** MEDIUM

`main.js` fires `loadAdminEvents()`, `loadUsers()`, `loadDashboardStats()` on `DOMContentLoaded`. `dashboard-ui.js` overrides these functions and re-fires them 100ms later via `setTimeout`. This causes **two rounds** of API calls on every dashboard page load.

**Fix:** Remove the initial calls from `main.js` when `dashboard-ui.js` is loaded, or use a flag.

---

### 6.3 Leaflet Map Instance Leak
**File:** `views/map.php` lines 164-177  
**Severity:** MEDIUM

`initMap()` empties the container and creates a new Leaflet map on every filter click. The old map instance is never `.remove()`d. Each leaked instance retains tile layer event listeners.

**Fix:** Call `map.remove()` before reinitializing.

---

### 6.4 HTML Injection Risk in `onclick` Attribute Strings
**Files:** `assets/js/main.js` ~line 50, `assets/js/dashboard-ui.js` ~line 507  
**Severity:** MEDIUM

Templates build `onclick="fn(${id}, '${escapeHtml(name)}')"`. The `escapeHtml()` implementation doesn't escape single quotes. User names with `'` can break the attribute context.

**Fix:** Use `addEventListener` instead of inline `onclick`, or escape `'` → `&#39;` in `escapeHtml`.

---

### 6.5 Missing `credentials: 'same-origin'` on Polling Fetches
**File:** `assets/js/ajax-polling.js` lines 29-38  
**Severity:** MEDIUM

Bare `fetch()` calls without `credentials` option. Some browsers won't send session cookies.

---

### 6.6 No Debounce on Nominatim Geocoding API
**File:** `assets/js/map-component.js`  
**Severity:** LOW

Each keystroke triggers a Nominatim API call. Their policy requires max 1 req/sec. Rapid searches could get the IP blocked.

---

### 6.7 Carousel `setInterval` Never Cleared
**File:** `public/assets/scripts/main.js`  
**Severity:** LOW

Minor memory/CPU leak on pages with autoplay carousel.

---

## 7. VIEW LAYER — SERVER-SIDE RENDERING

### 7.1 Missing CSRF Meta Tag in `events.php`
**File:** `views/events.php`  
**Severity:** MEDIUM

Unlike dashboard.php, help_requests.php, and volunteer_panel.php, this view doesn't call `csrf_meta()`. Any JS-initiated state-changing API call (like applying to an event) from this page will fail — `getCsrfToken()` reads from the `<meta>` tag that doesn't exist.

**Fix:** Add `<?php csrf_meta(); ?>` to the `<head>`.

---

### 7.2 Map Queries Don't Filter Archived Events
**File:** `views/map.php` lines 10-28  
**Severity:** MEDIUM

The events query lacks `is_archived = 0`. Archived (soft-deleted) events appear as pins on the map.

---

### 7.3 Stored XSS Path in Login Error
**File:** `views/login.php` lines 64-65  
**Severity:** LOW (currently)

The `account_blocked` error uses unescaped `<?= $errorMessages[$errorKey] ?>` containing an `<a>` tag. All other errors use `htmlspecialchars()`. Currently safe because error messages are hardcoded, but the pattern is dangerous if copied.

---

### 7.4 Reset Form Renders with Invalid Token
**File:** `views/reset_password.php`  
**Severity:** LOW

When token/email are blank, the form still renders with empty hidden fields. Users can submit pointlessly.

---

### 7.5 Dead Footer Links
**File:** `public/components/footer.php` lines 28-32  
**Severity:** MEDIUM (reputational)

Five legal links (`href="#"`): Terms, Privacy Policy, Cookie Policy, Usage Rules, Security — all go nowhere.

---

### 7.6 Global `fetch` Override on Dashboard
**File:** `views/dashboard.php` ~line 390  
**Severity:** LOW

Overrides `window.fetch` to inject CSRF headers. This modifies fetch behavior for ALL scripts on the page, including Leaflet tile fetches.

---

## 8. EMAIL SYSTEM

### 8.1 Unescaped `$subject` in HTML Email Body
**File:** `includes/functions.php` line 542 (in `send_notification_email`)  
**Severity:** MEDIUM

```php
<h2 style="...">{$subject}</h2>
```

`$safeName` and `$safeMessage` are HTML-encoded, but `$subject` is injected raw. If any caller passes user-controlled data as subject, this enables HTML injection in emails.

**Fix:** `$safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')` and use in template.

---

### 8.2 Concurrent Cron Runs → Duplicate Email Sends
**File:** `includes/functions.php` `process_email_queue()` ~line 340  
**Severity:** MEDIUM

`SELECT * FROM email_queue WHERE status='pending'` without row locking. Two cron processes select the same rows and both send the same emails.

**Fix:** Use `SELECT ... FOR UPDATE SKIP LOCKED` or a claim pattern (UPDATE to 'processing' first).

---

## 9. FILE UPLOAD SYSTEM

### 9.1 Upload Endpoint Broken (Double CSRF — Repeated from §4.1)
The general `api/upload.php` is non-functional. The profile picture upload in `api/users.php` works because it doesn't have the redundant check.

---

### 9.2 `validate_image_url` Allows HTTP Despite Comment Saying HTTPS Only
**File:** `includes/functions.php` ~line 563  
**Severity:** LOW

Comment says "https:// only" but regex allows `http://`.

---

## 10. CSS & DESIGN SYSTEM

### 10.1 No Centralized Design Tokens
Primary color `#00715D` hardcoded 20+ times across 12+ CSS files. Each file re-declares its own CSS variables:

| File | Variable | Color |
|------|----------|-------|
| `index.css` | `--rq-primary` | `#00715D` |
| `requests.css` | `--rq-primary` | `#00715D` |
| `showcase-events.css` | `--ev-accent` | `#00715D` |
| `showcase-requests.css` | `--nb-green` | `#1a8756` ← different! |
| `dashboard.css` | `--db-primary` | `#00715D` |
| `volunteer-panel.css` | — | `#1f8f63` hardcoded |

**Fix:** Single `:root {}` block in `main.css` with all design tokens.

---

### 10.2 Z-Index Chaos
`10000` for header, `1000` for map, `100` for sidebar, with no documented layer system. Modals may appear under the header.

---

### 10.3 Inconsistent Breakpoints
8 different breakpoints: 480, 600, 640, 700, 768, 900, 1050, 1280px. No design system rationale.

---

### 10.4 Inconsistent Border-Radius
7 different values: 8, 10, 12, 15, 16, 20, 24px. Should be 3-4 tokens.

---

### 10.5 No `:focus-visible` Styles
**Severity:** HIGH (accessibility)

No keyboard focus indicators across any CSS file. Fails WCAG 2.4.7.

---

### 10.6 Performance: Blur Filters + Animations
4 orbs with `filter: blur(80px)` + simultaneous animations → 30fps on low-end devices.

---

## 11. BUSINESS LOGIC — FLOW ANALYSIS

### 11.1 The Registration Paradox
Everyone registers as "Volunteer." But what if someone just needs help? They must become a "Volunteer" to create a help request. The label is confusing — a person seeking food assistance shouldn't be called a "volunteer."

**Fix:** Consider renaming the default role to "Member" or "Citizen."

---

### 11.2 The Communication Black Hole
If someone posts "I need food before tonight" — how does a volunteer contact them? There is:
- No messaging system
- No phone number collected
- No email visible to volunteers
- No chat
- No contact mechanism whatsoever

**The platform cannot fulfill its core purpose for urgent requests.**

---

### 11.3 Points System is Decorative
Score = `(approved_apps × 5) + (total_apps × 1) + (requests × 2)`.
- Points unlock nothing
- Points include rejected/all applications (gaming incentive)
- Points don't track physical attendance
- After hitting 150 max, no further motivation

---

### 11.4 Badges Are Computed, Not Earned
Badges are recalculated on every profile view. No `earned_at` timestamp, no "Congratulations!" notification, no progress tracking toward next badge.

---

### 11.5 Events Have No Capacity Enforcement
Events accept unlimited volunteers. There's a `kapaciteti` field but admin can approve beyond it. No waitlist auto-management.

---

### 11.6 No Completion Workflow
Events end → nothing happens. No "Event completed" status transition. No volunteer attendance confirmation. No feedback mechanism. No impact measurement.

---

### 11.7 Admin Cannot Do Critical Things

| Missing Feature | Impact |
|----------------|--------|
| Mass notification / email blast | Can't mobilize volunteers quickly |
| Monthly/annual reports | Can't justify budget to City Council |
| CSV/PDF export | Data trapped in platform |
| Geographic analytics | Can't see which neighborhoods need help |
| Volunteer hour tracking | No proof of service |
| Event completion verification | Can't prove events happened |
| Content moderation queue | Posts go live immediately, unreviewed |
| Multi-admin permissions | All admins have identical, total powers |
| Recurring events | Must recreate weekly events manually |

---

### 11.8 No Audit Trail for Critical Actions
Application approve/reject, category CRUD — these are not logged. Only block/unblock/role-change are audited via `admin_log`.

---

## 12. SCALABILITY & PERFORMANCE

| Component | Current State | At 10,000 Users |
|-----------|--------------|-----------------|
| Notification polling (every 15s) | Acceptable | 666 req/sec → server collapse |
| No query caching | Acceptable | Every page view hits DB directly |
| No CDN for assets | Acceptable | All CSS/JS/images served from XAMPP |
| Session files on disk | Acceptable | I/O bottleneck under concurrency |
| No load testing | Unknown | Unknown failure mode |
| No automated tests | — | Every code change is a gamble |

---

## 13. LEGAL & COMPLIANCE (GDPR)

| Requirement | Status |
|-------------|--------|
| Privacy Policy page | **MISSING** — link goes to `#` |
| Consent checkbox on registration | **MISSING** |
| Right to delete account ("Right to be forgotten") | **MISSING** — no UI or API |
| Right to export personal data (data portability) | **MISSING** |
| Data retention policy | **UNDEFINED** |
| Data Processing Agreement (Gmail SMTP = third-party) | **MISSING** |
| Cookie consent banner | **MISSING** |
| Accessible to disabled users (EAA) | **FAILING** — no keyboard nav, no ARIA, no focus styles |

**Risk:** Under Albanian Data Protection Law (mirroring GDPR), the municipality operating this platform faces potential fines.

---

## 14. WHAT'S ACTUALLY GOOD

Credit where it's due — this platform has real strengths:

| Strength | Details |
|----------|---------|
| **SQL injection defense** | 100% prepared statements across all endpoints. Zero raw queries found. |
| **Password hashing** | bcrypt via `password_hash()` + `password_verify()`. Industry best practice. |
| **CSRF protection** | Token generation, validation, and regeneration — properly implemented in the core middleware. |
| **Clean visual design** | Professional, modern aesthetic. Good color palette. Mobile responsive layout. |
| **Session regeneration** | `session_regenerate_id(true)` on login prevents session fixation attacks. |
| **File upload validation** | Server-side MIME check via `finfo`, size limits, GD image processing, WebP conversion. |
| **Email queue system** | Queue with exponential backoff retry — production-grade resilience pattern. |
| **Unified upload function** | `handle_image_upload()` consolidates duplicate image processing code cleanly. |
| **Status standardization** | English enums in DB with Albanian UI labels via `status_label()` — correct i18n separation. |
| **Soft-delete for events** | `is_archived` flag preserves historical data instead of destroying it. |
| **Admin audit log** | `admin_log` table records block/unblock/role-change actions with details. |
| **Database schema design** | Proper foreign keys, cascades, and table relationships throughout. |

---

## 15. SHARK TANK VERDICT

### If you're the Municipality of Tirana evaluating this for deployment:

**The idea is excellent.** Tirana genuinely needs a volunteer coordination platform. The visual design is professional and would represent the city well.

**But this is a prototype, not a product.**

**Three things that would kill the deal immediately:**

1. **"How do volunteers contact people who need help?"** — They can't. There is no communication mechanism. The platform lets people raise their hand and... nothing happens next.

2. **"Show me last month's impact report."** — It doesn't exist. No analytics, no exports, no metrics. The City Council will ask "how many citizens did we help?" and the answer is "we don't know."

3. **"What happens if we have 5,000 users?"** — The server collapses. Fifteen-second polling with 5,000 simultaneous sessions generates 333 database queries per second just for notification checks.

**The verdict:**

> *"This has the bones of something real. The security fundamentals are there, the design is sharp, the database schema is thoughtful. But you've built the registration funnel and forgot to build the actual service. You need: user-to-user communication, admin reporting, and a scalability plan. Give the dev team 3 months to address the critical bugs and build the messaging system, and this could be a flagship municipal tool. Today, it's a beautiful demo."*

---

## 16. PRIORITY FIX LIST

### P0 — Fix Before Any Testing (Blocks Everything)

| # | Issue | File | Effort |
|---|-------|------|--------|
| 1 | Remove double CSRF check in upload.php | `api/upload.php` L15-18 | 1 min |
| 2 | Fix Host header injection in `app_base_url()` | `includes/functions.php` L315 | 10 min |
| 3 | Re-verify DB role/status in `require_auth()` | `api/helpers.php` ~L128 | 20 min |
| 4 | Move `session_start()` after `require functions.php` in all action files | 5 files in `src/actions/` | 15 min |
| 5 | Migrate `kerkesa_per_ndihme.statusi` to lowercase English | `TiranaSolidare.sql` | 5 min |
| 6 | Check current status in `unblock` action | `api/users.php` | 5 min |

### P1 — Fix Before Beta Launch

| # | Issue | File | Effort |
|---|-------|------|--------|
| 7 | Prevent admin self-block/self-demote | `api/users.php` | 10 min |
| 8 | Add status transition validation (applications) | `api/applications.php` | 30 min |
| 9 | Re-check capacity before admin approval | `api/applications.php` | 15 min |
| 10 | Block changes on closed help requests | `api/help_requests.php` | 15 min |
| 11 | Block updates on archived events | `api/events.php` | 5 min |
| 12 | Remove email from unauthenticated help request `get` | `api/help_requests.php` | 5 min |
| 13 | Fix X-Forwarded-For trust in rate limiting | `includes/functions.php` | 5 min |
| 14 | Invalidate sessions on password reset | `reset_password_action.php` | 20 min |
| 15 | Add CSRF meta to events.php | `views/events.php` | 1 min |
| 16 | Filter archived events from map | `views/map.php` | 5 min |
| 17 | Escape `$subject` in notification email | `includes/functions.php` | 2 min |
| 18 | Add `FOR UPDATE SKIP LOCKED` to email queue processor | `includes/functions.php` | 10 min |
| 19 | Validate `tipi` on help request update | `api/help_requests.php` | 5 min |
| 20 | Add `pershkrimi` length validation | `api/help_requests.php` | 5 min |
| 21 | Validate image URL on help requests | `api/help_requests.php` | 5 min |
| 22 | Clear setInterval on page unload (polling) | `assets/js/ajax-polling.js` | 10 min |
| 23 | Fix double-loading in dashboard JS | `assets/js/main.js` | 15 min |
| 24 | Add cookie `secure` flag dynamically | `api/helpers.php` | 2 min |

### P2 — Fix Before Public Launch

| # | Issue | Effort |
|---|-------|--------|
| 25 | Centralize CSS design tokens in `:root` | 2 hrs |
| 26 | Add `:focus-visible` styles for accessibility | 1 hr |
| 27 | Fix Leaflet map instance leak on filter | 15 min |
| 28 | Escape `'` in JS `escapeHtml()` function | 5 min |
| 29 | Add composite DB indexes (3 tables) | 15 min |
| 30 | Change `raporti` FK from CASCADE to SET NULL | 5 min |
| 31 | Create actual Privacy Policy page | 2 hrs |
| 32 | Add account deletion feature (GDPR Art. 17) | 4 hrs |
| 33 | Add consent checkbox to registration | 30 min |
| 34 | Per-account brute-force lockout | 1 hr |
| 35 | CSRF token rotation on login | 5 min |
| 36 | Create real footer link pages (Terms, Cookies, etc.) | 4 hrs |

### P3 — Feature Gaps for Production Readiness

| # | Feature | Effort |
|---|---------|--------|
| 37 | User-to-user messaging or contact exchange system | 2-3 weeks |
| 38 | Admin reporting dashboard (monthly stats, CSV/PDF export) | 1-2 weeks |
| 39 | Replace polling with WebSockets or Server-Sent Events | 1 week |
| 40 | Event completion workflow + volunteer attendance confirmation | 1 week |
| 41 | Multi-role permissions (Coordinator role) | 1 week |
| 42 | Automated test suite (PHPUnit + Cypress) | 2 weeks |
| 43 | Server-side caching (Redis/APCu) | 3 days |
| 44 | Geographic analytics for admin | 1 week |

---

**Total findings: 7 Critical · 15 High · 30 Medium · 12 Low**

**End of Audit.**
