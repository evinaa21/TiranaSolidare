# TiranaSolidare — Competition TODO & Bug Tracker

## Priority Order (for maximum competition impact)

### 🔴 CRITICAL — Fix Bugs First
- [x] `deleteUser()` API action doesn't exist — fixed: now uses `deactivate` action instead
- [x] `koheParapake()` function defined 3 times — fixed: wrapped all 3 in `function_exists()` check
- [x] Volunteer dashboard calls admin-only `stats.php?action=overview` — fixed: volunteers redirected to new volunteer_panel.php
- [x] `loadCategoryDropdown()` reads `json.data.forEach()` — fixed: now reads `json.data.categories`
- [ ] No CSRF tokens anywhere — security vulnerability judges will spot
- [x] `src/actions/login_action.php` doesn't check blocked/deactivated accounts — fixed: now checks `statusi_llogarise`
- [x] Help request form sends `vendndodhja` field but table has no column — fixed: added column to schema + API
- [x] No password minimum length check in `src/actions/register_action.php` — fixed: added 6-char check

---

### 🟡 TIER 1 — "Wow the Judges" (High Impact, High Visibility)

#### 1. Interactive Tirana Heatmap (4-5 hours)
- [ ] Add Leaflet.js map on homepage or dedicated page
- [ ] Live pins for active help requests by neighborhood
- [ ] Color-coded: red = urgent needs, green = offers, blue = events
- [ ] Click a pin → see the request card
- [ ] Add `lagja` (neighborhood) + lat/lng fields to help requests and events

#### 2. Real-Time Matching System (4-5 hours)
- [ ] When someone posts a help request, auto-match with available volunteers
- [ ] Match by neighborhood, category experience, availability
- [ ] Send instant notification: "Arta ka nevojë për ndihmë me ushqim në Laprakë"
- [ ] Add `lagja` field to user profiles

#### 3. Impact Dashboard with Charts (3 hours)
- [ ] Add Chart.js to dashboard
- [ ] Monthly volunteering trend line chart
- [ ] Category breakdown pie chart
- [ ] Help requests resolved vs pending bar chart
- [ ] Personal volunteer "score" display
- [ ] Export as PDF report

#### 4. Volunteer Gamification & Profiles (4 hours)
- [ ] Public volunteer profile pages with avatar, bio, badges
- [ ] Badge system: "First Help", "10 Events", "Community Hero", "Emergency Responder"
- [ ] Points system: Apply = 5pts, Accepted = 20pts, Complete event = 50pts
- [ ] Monthly leaderboard

---

### 🟢 TIER 2 — "This Is Production-Ready" (Technical Excellence)

#### 5. PWA + Mobile Install (1 hour)
- [ ] Add `manifest.json` with app name, icons, theme color
- [ ] Add service worker for offline caching
- [ ] Installable on phones — demo by installing live during presentation

#### 6. SMS/Email Notifications (3 hours)
- [ ] PHPMailer integration with free SMTP
- [ ] Send email on: application status change, new match, event reminder
- [ ] Email templates in Albanian

#### 7. Image Upload with Compression (2 hours)
- [ ] Create `/uploads/` directory with proper permissions
- [ ] PHP GD library compression
- [ ] Upload for: event banners, help request photos, profile avatars
- [ ] Validate file type and size

#### 8. Multi-language SQ/EN (3 hours)
- [ ] Language toggle in header
- [ ] Translation array file (`lang/sq.php`, `lang/en.php`)
- [ ] Session-based language preference

---

### 🔵 TIER 3 — "Attention to Detail" (What Separates Winners)

#### 9. Accessibility (2 hours)
- [ ] ARIA labels on all interactive elements
- [ ] Keyboard navigation for dashboard
- [ ] High contrast mode toggle
- [ ] Screen reader friendly

#### 10. Dark Mode (1-2 hours)
- [ ] CSS custom properties for all colors
- [ ] Toggle switch in header
- [ ] Persist preference in localStorage

#### 11. Onboarding Flow (2 hours)
- [ ] 3-step wizard after registration
- [ ] Step 1: Complete profile (photo, neighborhood, skills)
- [ ] Step 2: Browse your first event
- [ ] Step 3: Apply or post a request

#### 12. Emergency "SOS" Mode (3 hours)
- [ ] Prominent red SOS button for urgent requests
- [ ] Bypasses normal flow — immediately notifies ALL active volunteers in area
- [ ] Special "Emergency" category with priority display
- [ ] Perfect for disaster response story in presentation

---

## Other Known Issues
- [ ] Duplicate seed data — Events 1-8 and 9-16 are identical in SQL
- [x] Footer links (Terms, Privacy, Cookies) have empty `href=""` — fixed: added `#` / `mailto:` / `tel:` hrefs
- [ ] Mixed design systems — Bootstrap on login/register, custom CSS on dashboard/public
- [ ] No event edit form — admin can only edit title via `prompt()` dialog
- [ ] Dashboard search/filter inputs missing on admin tables (API supports filters)
