# RAPORT KRITIK I DETAJUAR — Tirana Solidare

## Auditimi i plotë i koherencës, sigurisë, dizajnit dhe cilësisë së kodit

---

## 1. SIGURIA — Probleme kritike

### 1.1 Kredenciale të ekspozuara në kod burimor
**Skedarët:** `config/db.php`, `config/mail.php`

| Problemi | Ku ndodhet | Si duhet të jetë |
|----------|-----------|-------------------|
| Databaza me `root` pa fjalëkalim | `config/db.php` linja 5 | Përdor `.env` me `getenv()`, mos e komito `.env` në Git |
| Kredenciale SMTP në tekst të qartë | `config/mail.php` linja 9-10 | Variablat e mjedisit `getenv()` janë si fallback — rendi duhet të jetë i njëjtë: variablën e mjedisit së pari, JO vlera hardcoded si fallback |
| Asnjë skedar `.env` nuk ekziston | Mungon komplet | Krijo `.env` me `.env.example` template, shto `.env` në `.gitignore` |

**Ashpërsia:** KRITIKE — çdokush me akses në repo ka akses total në email dhe databazë.

### 1.2 Cookie pa flag `secure`
**Skedari:** `api/helpers.php` linja 17

```php
'secure' => false,  // Edhe në prodhim!
```

**Si duhet:** `'secure' => isset($_SERVER['HTTPS'])` — cookie duhet të transmetohen vetëm me HTTPS.

### 1.3 CORS pa varianta HTTPS
**Skedari:** `api/helpers.php` linja 35-43

Lejon vetëm `http://localhost` — nëse deplojohet me HTTPS, asnjë origin nuk pranohet. Duhet të shtojë edhe variantat `https://`.

### 1.4 Logout me GET (jo POST)
Logout bëhet me link GET me token në URL:

```php
<a href="/TiranaSolidare/src/actions/logout.php?token=...">
```

**Problemi:** Token-i mund të zbullohet në `Referer` headers.  
**Si duhet:** Logout duhet të bëhet me formë POST me CSRF token.

### 1.5 Rate limiting bazuar në sesion (jo IP)
**Skedari:** `includes/functions.php`

Nëse sulmuesi fshin cookies, rate limiting ristarton. Duhet bazuar në IP ose të përdorë cache server-side (Redis/Memcached).

### 1.6 Admini mund ta bllokojë veten
**Skedari:** `api/users.php` — `action=block`

Nuk ka kontroll `if ($targetId === $_SESSION['user_id'])`. Admini mund të mbyllet jashtë platformës.

---

## 2. SKEMA SQL — Jokoherenca

### 2.1 Gjuhë e përzier në kolonat e databazës

| Tabela | Kolonë shqip | Kolonë anglisht | Problemi |
|--------|-------------|------------------|---------|
| `kerkesa_per_ndihme` | `titulli`, `pershkrimi`, `vendndodhja`, `krijuar_me` | `statusi = 'Open'/'Closed'` | Statuset janë anglisht, gjithçka tjetër shqip |
| `njoftimi` | `mesazhi`, `krijuar_me` | `is_read` | Një kolonë anglisht në mes të shqipes |
| `perdoruesi` | `emri`, `fjalekalimi`, `roli` | `verified`, `profile_picture`, `profile_public`, `profile_color` | ~50% anglisht, 50% shqip |
| `perdoruesi` | `statusi_llogarise = 'Bllokuar'/'Çaktivizuar'` | `statusi_llogarise = 'Aktiv'` | OK, por `verified` pranë `statusi_llogarise` — gjuhë e ndryshme |

**Si duhet:** Zgjidhni NJË gjuhë për skemën e plotë (rekomandohet anglisht për kodin, shqip vetëm për ndërfaqen). **Ose** nëse zgjidhni shqip, `is_read` duhet të jetë `lexuar`, `profile_picture` duhet `foto_profili`, etj.

### 2.2 Statuset e përziera

| Tabelë | Vlerat e statusit | Gjuha |
|--------|-------------------|-------|
| `aplikimi` | `'Në pritje', 'Pranuar', 'Refuzuar'` | Shqip |
| `aplikimi_kerkese` | `'Në pritje', 'Pranuar', 'Refuzuar'` | Shqip |
| `kerkesa_per_ndihme` | `'Open', 'Closed'` | **ANGLISHT** |
| `perdoruesi` | `'Aktiv', 'Bllokuar', 'Çaktivizuar'` | Shqip |
| `perdoruesi.roli` | `'Admin', 'Vullnetar'` | Shqip/Anglisht përzier |

**Jokoherencë e fortë.** Aplikimi përdor statuse shqip por kërkesat përdorin anglisht. Duhet standardizuar.

### 2.3 Indekse që mungojnë

| Tabela | Indeks i munguar | Arsyeja |
|--------|------------------|---------|
| `aplikimi_kerkese` | `(id_perdoruesi, statusi)` | Kërkim i shpeshtë "aplikimet e mia" |
| `njoftimi` | `(id_perdoruesi, is_read, krijuar_me)` | Query "njoftime të palexuara" |
| `eventi` | `(id_perdoruesi)` | Query "eventet e mia" |
| `kerkesa_per_ndihme` | `(id_perdoruesi, statusi)` | Query "kërkesat e mia aktive" |

### 2.4 Kolona `njoftimi.is_read` me indeks të paefektshëm
Boolean column (0/1) ka selektivitete shumë të ulët. Indeksi `idx_is_read` pothuajse nuk përdoret nga optimizuesi. Duhet indeks i kombinuar `(id_perdoruesi, is_read)`.

---

## 3. FRONTEND — Jokoherenca në CSS

### 3.1 Ngjyrat e shpërndara — asnjë sistem unik

Ngjyra kryesore `#00715D` shkruhet **hardcoded 20+ herë** nëpër 12+ skedarë CSS. Çdo skedar e rideklaron:

| Skedari | Variablat e deklaruara | Problemi |
|---------|----------------------|---------|
| `main.css` | Nuk ka variabla qendrore | Ngjyra hardcoded |
| `index.css` | `--rq-primary: #00715D` | Ri-deklarim |
| `requests.css` | `--rq-primary: #00715D` | Dublikim |
| `showcase-events.css` | `--ev-accent: #00715D` | Emër i ndryshëm |
| `showcase-requests.css` | `--nb-green: #1a8756` | Ngjyrë e ndryshme fare! |
| `dashboard.css` | `--db-primary: #00715D` | Emër tjetër |
| `volunteer-panel.css` | `#1f8f63` hardcoded | As emër variabli nuk ka |

**Si duhet:** Një bllok `:root {}` i vetëm në `main.css` me të gjitha ngjyrat, pa ri-deklarime.

### 3.2 Z-index kaos — pa sistem

| Vlera | Ku përdoret | Konflikti |
|-------|------------|-----------|
| `10000` | `.header` | Mbi gjithçka — edhe mbi modalet? |
| `1000` | `.ts-map-search__results` | Konflikton me kontrollet Leaflet |
| `100` | `.hs_Btn`, `.db-sidebar` | Pritet mbi përmbajtje — por cili fiton? |
| `50` | `.db-topbar` | Nën sidebar — OK? |
| `0` | Hero blobs, grid overlay | I paspecifikuar |

**Si duhet:** Sistem i dokumentuar i shtresave:
- 1-10: Komponentë bazë
- 100-200: Sidebar, navigim
- 1000-2000: Modale, popups
- 9000+: Header fiks, overlay

### 3.3 Breakpoints jo-konsistente

Breakpoints e gjetur nëpër CSS: `480px`, `600px`, `640px`, `700px`, `768px`, `900px`, `1050px`, `1280px` — **8 vlera të ndryshme** pa logjikë. Nuk ka design tokens ose mixin.

**Si duhet:** Maksiumi 4 breakpoints standarde (p.sh. 480, 768, 1024, 1440) si variabla CSS ose SCSS.

### 3.4 Border-radius inkoherent

Gjenden: `8px`, `10px`, `12px`, `15px`, `16px`, `20px`, `24px` — **7 vlera** pa asnjë rregull.

**Si duhet:** 3-4 vlera: `--radius-sm: 8px`, `--radius-md: 12px`, `--radius-lg: 20px`, `--radius-full: 50%`.

---

## 4. FRONTEND — Jokoherenca në JavaScript

### 4.1 Mbivendosja e funksioneve (anti-pattern)
`dashboard-ui.js` **mbishkruan** funksione nga `main.js` pa dokumentim:

```js
// main.js definon loadDashboardStats()
// dashboard-ui.js e ri-definon loadDashboardStats()  ← mbishkruan heshtazi
```

**Problemi:** Sjellja varet nga rendi i ngarkimit të skripteve. Fragile.  
**Si duhet:** Sistem modulor (ES modules ose namespace explicit).

### 4.2 Memory leaks — interval pa pastrim
`ajax-polling.js` krijon `setInterval()` pa mekanizëm pastrimi. Nëse përdoruesi navigon mes faqeve (SPA-style), intervalet grumbullohen.

**Si duhet:** Ruaj ID-në e intervalit dhe pastroe me `clearInterval()` në `beforeunload` ose `pagehide`.

### 4.3 Funksione të varura mes skedarëve
`main.js` përdor `escapeHtml()`, `formatDate()`, `showToast()`, `renderPagination()` — por këto **nuk janë definuar** në të njëjtin skedar. Varen nga ngarkimi i skedarëve tjerë.

**Si duhet:** Bundler (Vite/Webpack) me import/export, ose file i dedikuar `utils.js` i ngarkuar i pari.

### 4.4 IntersectionObserver pa `unobserve`
`public/assets/scripts/main.js` krijon shumë Observer-a por nuk i pastron. Elementë që janë animuar tashmë vazhdojnë të monitorohen.

---

## 5. ARKITEKTURA E DREJTORIVE — Jokoherenca

### 5.1 Dy dosje `assets/` të ndara

| Dosja | Përmban | Kush e përdor |
|-------|---------|---------------|
| `/assets/css/`, `/assets/js/` | CSS/JS për admin dashboard | Faqet e admin-it |
| `/public/assets/styles/`, `/public/assets/scripts/` | CSS/JS për faqen publike | Faqet publike |

**Problemi:** Dy sisteme paralele pa logjikë. Cili është "i vërteti"? Stila e përbashkët (p.sh. butona, ngjyra) dublikohen mes dy dosjeve.

**Si duhet:** Një dosje e vetme `public/assets/` me nëndosje `shared/`, `admin/`, `public/`.

### 5.2 Referenca path inkoherente nëpër views

| Skedari | Shtegu i përdorur | Format |
|---------|-------------------|--------|
| `public/index.php` | `assets/styles/main.css` | Relativ (pa /) |
| `views/dashboard.php` | `/TiranaSolidare/public/assets/styles/main.css` | Absolut |
| `views/events.php` | `/TiranaSolidare/public/assets/styles/main.css` | Absolut |

Dy mënyra për të referuar të njëjtin skedar. Nëse aplikacioni zhvendoset, njëri prishet.

---

## 6. GJUHA SHQIPE — Gabime dhe jokoherenca

### 6.1 Gabime drejtshkrimore nëpër faqe

| Teksti gabim | Ku | Korrigjimi |
|-------------|-----|------------|
| "mund" | Shumë faqe | "mund" ✓ (varianti i pranuar, por "mund" pa "ë" është më pak formal) |
| "Kombëtare" | SQL seed data | "Kombëtare" ✓ (OK) |
| "bëjmë" | `public/index.php` | "bëjmë" ✓ |
| "Bashkia Tiranë, Tiranë" | `public/components/footer.php` | Përsëritje e panevojshme — duhet "Bashkia Tiranë" |

### 6.2 Terminologji inkoherente

| Koncepti | Varianti 1 | Varianti 2 | Ku |
|----------|-----------|-----------|-----|
| Eventë/Evente | "Evente të realizuara" | "Eventet e fundit" | index.php |
| Krijuar/Postuar | "krijuar_me" (DB) | "Postuar më" (UI mund) | Views |
| Ndihmë/Ndihmo | "Ndihmo dikë sot" | "Kërkoj ndihmë" | index.php vs requests |
| Vullnetar | "Vullnetarë aktivë" | "Bëhu Vullnetar" | Njëjës vs shumës OK, por "vullnetarizëm" kundër "volunteerism" |

---

## 7. SEO & PWA — Mungesa

### 7.1 Meta tags që mungojnë

| Tag | Status | Ndikimi |
|-----|--------|---------|
| `<meta name="description">` | **MUNGON** në të gjitha faqet | Google shfaq fragment të keq |
| Open Graph (`og:title`, `og:image`, `og:description`) | **MUNGON** | Share në Facebook/LinkedIn pa imazh |
| Twitter Card (`twitter:card`, `twitter:image`) | **MUNGON** | Share në Twitter pa preview |
| `<link rel="canonical">` | Vetëm në `public_profile.php` | Përmbajtje e dublikuar në Google |
| JSON-LD schema | **MUNGON** | Events nuk shfaqen si "Rich Results" |

### 7.2 Service Worker i mangët
**Skedari:** `public/sw.js`

| Problemi | Si duhet |
|----------|---------|
| Asnjë version cache — `tirana-solidare-v1` nuk ndryshon kurrë | Versionim automatik me hash |
| Faqet HTML gjithmonë nga rrjeti — offline nuk funksionon | Fallback offline page |
| Cache-t e vjetra nuk fshihen | `activate` event me cache cleanup |
| Vetëm 4 asete cache-ohen | Të paktën 15-20 asete kryesore |

### 7.3 Manifest.json i paplotë
**Skedari:** `public/manifest.json`

Mungon: `description`, `scope`, `categories`, `shortcuts`, `screenshots`, icon `purpose: "any maskable"`.

---

## 8. AKSESIBILITETI (A11y) — Dështime serioze

| Problemi | Ku | WCAG | Ashpërsia |
|----------|-----|------|-----------|
| Asnjë `:focus-visible` nëpër CSS | Të gjitha CSS | 2.4.7 | Kritike |
| Diferencimi vetëm me ngjyrë (badges gjelbër/kuq) | Cards, badges | 1.4.1 | E lartë |
| Modale JS pa `role="dialog"`, `aria-modal` | dashboard-ui.js | 4.1.2 | E lartë |
| Tab panels pa `role="tabpanel"`, `aria-labelledby` | dashboard.php | 4.1.2 | E lartë |
| Karuseli pa mbështetje tastiere | Events carousel | 2.1.1 | Mesatare |
| `aria-live="polite"` mungon në kontejnerë njoftimesh | Notifications | 4.1.3 | Mesatare |
| Tekst me `opacity: 0.8` — kontrast i pamjaftueshëm | Disa faqe | 1.4.3 | Mesatare |

---

## 9. PERFORMANCA — Probleme

| Problemi | Vendndodhja | Ndikimi |
|----------|------------|---------|
| 4 orbe me `filter: blur(80px)` + animacione simultane | index.css, requests.css | 30fps në pajisje të dobëta |
| CSS total ~5MB me vendor prefixes të dublikuara | Të gjitha CSS | 40% e mbytshme |
| Imazhe nga Unsplash pa lazy loading (`loading="lazy"`) | index.php, seed data | Ngarkimi fillestar i ngadaltë |
| CDN Leaflet pa SRI (Subresource Integrity) | dashboard.php, map.php | Rrezik sigurie |
| `setInterval` polling pa cleanup | ajax-polling.js | Rrjedhje memorje |
| Animacione pa `will-change` hints | main.css | Repaints/reflows |

---

## 10. TABELA PËRMBLEDHËSE — Prioritetet

| # | Kategori | Problemi kryesor | Prioriteti | Përpjekja |
|---|----------|-----------------|-----------|-----------|
| 1 | **Siguri** | Kredenciale në kod burimor | KRITIKE | E ulët |
| 2 | **Siguri** | Cookie `secure=false` | E LARTË | E ulët |
| 3 | **Siguri** | Admin mund ta bllokojë veten | E LARTË | E ulët |
| 4 | **Koherencë DB** | Gjuhë e përzier (shqip+anglisht) në kolona/statuse | E LARTË | Mesatare |
| 5 | **CSS** | Ngjyra të shpërndara pa sistem variablash | E LARTË | Mesatare |
| 6 | **CSS** | Z-index kaos, breakpoints inkoherente | MESATARE | Mesatare |
| 7 | **JS** | Memory leaks, mbivendosje funksionesh | MESATARE | E lartë |
| 8 | **Arkitekturë** | Dy dosje `assets/` paralele | MESATARE | E lartë |
| 9 | **A11y** | Focus styles, ARIA, kontrast | E LARTË | E lartë |
| 10 | **SEO** | Meta tags, Open Graph, JSON-LD mungojnë | MESATARE | E ulët |
| 11 | **PWA** | Service Worker i mangët | E ULËT | Mesatare |
| 12 | **Gjuhë** | Terminologji inkoherente, "Tiranë, Tiranë" | E ULËT | E ulët |

---

**Nota përfundimtare:** Platforma ka baza të mira — prepared statements për SQL, CSRF protection, rate limiting, password hashing, file upload validation. Por jokoherencat janë të shumta: gjuha e përzier në databazë, sistemi i ngjyrave i fragmentuar, arkitektura e dosjeve e dyfishtë, dhe mungesat e aksesibilitetit janë çështjet më urgjente për tu adresuar.

---
---

# PJESA II — VLERËSIMI INSTITUCIONAL (Shark Tank / Blerësi Bashkiak)

> *Ky seksion simulon vlerësimin që do bënte një panel komisionerësh nga Bashkia e Tiranës, një organizatë ndërkombëtare donatore, ose një investitor tip "Shark Tank" që po vendos nëse do ta adoptojë/blejë këtë platformë.*

---

## 11. PËRMBLEDHJA EKZEKUTIVE

**Çfarë është kjo platformë:** Tirana Solidare paraqitet si "Platforma Zyrtare e Vullnetarizmit — Bashkia Tiranë". Ofron një hapësirë ku qytetarët regjistrohen si vullnetarë, aplikojnë për evente, dhe postojnë kërkesa/oferta ndihme.

**Gjendja aktuale:** Beta / Zhvillim i hershëm (~60% e funksionaliteteve e përfunduar)

**Gatishmëria institucionale: 37 / 100** — Nuk është gati për deploim publik pa ristrukturim të rëndësishëm.

| Fusha | Nota /10 | Koment |
|-------|----------|--------|
| Autentifikim & Siguri | 6/10 | Ka password hashing, CSRF; mungon HTTPS, rate limiting i anashkalueshëm |
| Përvojë Përdoruesi (UX) | 6/10 | Dizajn i pastër, responsiv; mungon aksesibiliteti, asnjë mbështetje për të moshuarit |
| Menaxhimi i të Dhënave | 3/10 | Mbledh minimalisht; asnjë e dhënë demografike, gjurmim rezultatesh, audit trail |
| Shkallëzueshmëria | 2/10 | Polling-based notifications kollapson me >100 përdorues |
| Funksionalitete Bashkiake | 2/10 | Asnjë raportim, analitikë, ose mjete vendimmarrjeje |
| Përputhshmëria Ligjore | 1/10 | Shkelje GDPR, asnjë politikë privatësie, kredenciale të ekspozuara |
| Plotësia e Funksionaliteteve | 4/10 | Funksionalitetet bazë ekzistojnë por janë gjysmë të ndërtuara |
| Cilësia e Kodit | 4/10 | Funksionon por i pamirëmbajtshëm; gjuhë e përzier, pa teste, pa dokumentim |
| Përvoja e Vullnetarit | 7/10 | Mund të regjistrohet, shfletojë, aplikojë; profil i mirë; por pa cikël feedback-u |
| Gatishmëria Operacionale | 2/10 | Pa procedurë deploimi, pa backup, pa monitorim |

---

## 12. UDHËTIMI I PËRDORUESIT — Analiza e plotë

### 12.1 Vizitori i parë (Faqja kryesore)

**Çfarë sheh qytetari:**
- Seksion hero: "Bashkohu me komunitetin që ndryshon jetë" me animacione
- Tri statistika: vullnetarë aktivë, evente, qytetarë të ndihmuar
- CTA regjistrimi: "Bëhu Vullnetar"
- 4 hapat e procesit
- Karusel me kategori
- 8 kërkesat e fundit + 8 eventet e fundit

**Çfarë MUNGON që do pyeste një komision:**

| Pyetja e komisionit | Përgjigjja |
|---------------------|-----------|
| "Kush mund ta përdorë? Vetëm banorë të Tiranës?" | Nuk specifikohet askund. Çdokush mund të regjistrohet. |
| "Ku janë dokumentet ligjore?" | Mungojnë — linket në footer çojnë në `#` (asgjëkund) |
| "A mund ta shoh panelin e administratorit pa u regjistruar?" | Jo — asnjë demo ose screenshot |
| "Ka ndonjë vulë zyrtare të Bashkisë?" | Jo — asnjë sinjal besimi institucional |
| "A mbështet gjuhë të tjera?" | Jo — vetëm shqip. Tirana ka komunitet ndërkombëtar |
| "Si e matni ndikimin?" | Nuk ka asnjë mjet matjeje |

### 12.2 Regjistrimi

**Çfarë kërkohet:** Emri, Email, Fjalëkalim (8+ karaktere, shkronjë e madhe, e vogël, numër, simbol)

**Çfarë NUK kërkohet por duhet:**

| E dhëna | Pse duhet | Pasoja e mungesës |
|---------|-----------|-------------------|
| Numër telefoni | Kontakt urgjent, verifikim | Vullnetarët nuk mund të kontaktohen |
| Lagja/Njësia | Përputhje gjeografike | Nuk mund t'i dërgosh dikë pranë shtëpisë |
| Mosha/Grup-mosha | Shërbime për të moshuarit | Nuk dinë se kush ka nevojë për ndihmë të veçantë |
| Aftësi/Përvoja | Përputhja vullnetar-event | Dërgon kontabilist për pastrim lumi |
| Disponueshmëria | Planifikim eventesh | Admin nuk di kur janë vullnetarët e lirë |
| Pranim GDPR | Detyrim ligjor | Shkelje e ligjit për mbrojtjen e të dhënave |
| Foto identifikuese | Besimi i komunitetit | Qytetar nuk di kë po pret në derë |

**Problem kritik — Rolet:**

| Rol | Ekziston | Si krijohet |
|-----|---------|-------------|
| Vullnetar | Po | Regjistrim i zakonshëm |
| Admin | Po | **VETËM nga databaza manualisht** — pa UI |
| Qytetar (jo vullnetar) | **JO** | Çdokush që regjistrohet bëhet automatikisht "Vullnetar" |
| Organizatë / OJF | **JO** | Nuk ka mundësi |
| Punonjës Social | **JO** | Nuk ka mundësi |
| Super-Admin | **JO** | Të gjithë Admin-ët kanë të njëjtat të drejta |

> *"Po nëse një i moshuar do vetëm të kërkojë ndihmë, pse duhet të regjistrohet si 'Vullnetar'? Terminologjia është konfuze."*

### 12.3 Pas kyçjes — Çfarë merr vullnetari

**Volunteer Panel — Tab-et:**

| Tab | Çfarë bën | Çfarë mungon |
|-----|----------|-------------|
| Profili | Ngarkon foto, shkruan bio, zgjedh ngjyrë teme | Asnjë aftësi, disponueshmëri, ose lagje |
| Aplikimet e mia | Sheh listën e eventeve ku ka aplikuar | Asnjë kujtesë, as status "në progres" |
| Kërkesat e mia | Sheh postimet e veta | Asnjë feedback pas mbylljes |
| Pikët e mia | Sheh pikët (formula: aplikimet × 5 + kërkesat × 2) | Nuk ka lidhje reale me prezencën fizike |
| Njoftimet | Listë njoftimesh | Pa filtrim lexuar/palexuar, pa veprime |
| Cilësimet | Ndrysho emrin, emailin, fjalëkalimin | Pa fshirje llogarie (shkelje GDPR) |

### 12.4 Paneli i administratorit

**Çfarë MUND të bëjë admini:**
1. Krijon evente (titull, lokacion, datë, kategori, përshkrim, banner)
2. Sheh listën e eventeve
3. Sheh aplikantët për çdo event
4. Bllokon/zhbllokon përdorues
5. Ndryshon rolin e përdoruesit
6. Sheh kërkesat e ndihmës

**Çfarë NUK MUND të bëjë admini (por DUHET):**

| Funksionalitet i munguar | Pse është i rëndësishëm |
|--------------------------|------------------------|
| Dërgo mesazh masovik | "Na duhen 10 vullnetarë nesër në Vorë" — s'ka si e çon |
| Gjeneroni raport mujor/vjetor | Këshilli Bashkiak kërkon numra. S'ka si i nxjerr |
| Eksporto të dhëna në CSV/PDF | Pa eksport, të dhënat janë të bllokuara brenda platformës |
| Shiko analitikë gjeografike | "Cila lagje ka më shumë kërkesa?" — pamundur |
| Gjurmo orët e vullnetarizmit | Nuk ka koncept "orë shërbimi" |
| Verifikoni përfundimin e eventit | Admini nuk di nëse eventi ndodhi realisht |
| Moderoni postimet para publikimit | Postimet shfaqen menjëherë — pa filtër |
| Menaxho shumë Admin-ë | Çdo admin sheh gjithçka — pa ndarje përgjegjësish |
| Krijo evente të përsëritura | Çdo event krijohet manualisht, edhe nëse ndodh çdo javë |
| Konfiguro kategori të reja | Kategorite ndryshoren vetëm nëpërmjet API, jo UI |

---

## 13. MUNGESA KRITIKE PËR VENDIMMARRJE BASHKIAKE

### 13.1 Asnjë raportim analitik

Nëse Këshilli Bashkiak pyet: *"Sa qytetarë ndihmuam këtë muaj?"* — përgjigjja është: **"Nuk dimë."**

**Çfarë duhet të ofrojë paneli i administratorit:**

| Metrikë | Status | Pasoja |
|---------|--------|--------|
| Numri total i qytetarëve të ndihmuar (mujor/vjetor) | **MUNGON** | S'mund ta arsyetosh buxhetin |
| Orët totale të vullnetarizmit | **MUNGON** | S'di sa kohë investohet |
| Koha mesatare e përgjigjes ndaj kërkesave | **MUNGON** | S'di nëse sistemi funksionon shpejt |
| Lagjet me më shumë kërkesa | **MUNGON** | S'di ku të fokusosh burimet |
| Shkalla e mbajtjes së vullnetarëve | **MUNGON** | Nuk dinë nëse po humbasim vullnetarë |
| Numri i vullnetarëve që nuk u paraqitën | **MUNGON** | S'ka llogaridhënie |
| Kënaqësia e qytetarëve | **MUNGON** | S'di cilësinë e shërbimit |
| Krahasimi muaj-me-muaj | **MUNGON** | S'ka tendenca historike |

### 13.2 Asnjë eksport të dhënash

| Format | I mundshëm | Pasoja |
|--------|-----------|--------|
| CSV | **JO** | S'hapet në Excel |
| PDF | **JO** | S'paraqitet në mbledhje këshilli |
| API publike | **JO** | S'lidhet me sistemet e tjera bashkiake |
| JSON export | **JO** | S'integrohet me asnjë platformë tjetër |

### 13.3 Asnjë audit trail

| Pytja | Përgjigjja |
|-------|-----------|
| "Kush e fshiu eventin X?" | Nuk dihet |
| "Kur u bllokua ky përdorues?" | Nuk regjistrohet |
| "Sa herë ka ndryshuar admin-i statusin?" | Pamundur të verifikohet |
| "A ka pasur shkelje të dhënash?" | S'ka log të aksesit |

---

## 14. PËRPUTHSHMËRIA LIGJORE — DËSHTIM I PLOTË

### 14.1 GDPR (Rregyllorja Europiane për Mbrojtjen e të Dhënave)

| Kërkesë | Statusi | Rrreziku |
|---------|--------|---------|
| Checkbox pranimi në regjistrim | **MUNGON** | Bashkia mund të paditet |
| Politika e privatësisë (faqe reale) | **MUNGON** — linku çon në `#` | Çdo inspektim e gjen |
| E drejta për fshirje ("Right to be forgotten") | **MUNGON** — asnjë buton "Fshi llogarinë" | Shkelje Neni 17 GDPR |
| E drejta për eksport të dhënash | **MUNGON** | Shkelje Neni 20 GDPR |
| Njoftim për shkelje të dhënash | **PA PROCEDURË** | Dënim deri 4% e buxhetit vjetor |
| Periudha e ruajtjes së të dhënave | **E PADEFINUAR** | S'dihet sa kohë ruhen të dhënat |
| Kush është "Kontrolluesi i të Dhënave"? | **I PADEFINUAR** | Bashkia apo zhvilluesi? |
| DPA me palë të treta (Gmail SMTP) | **MUNGON** | Email-et dërgohen nga llogari personale Gmail |

### 14.2 Aftësia e kufizuar

| Grup | Problem | Pasoja |
|------|---------|--------|
| Të moshuar (65+) | Asnjë modalitet i thjeshtëzuar, font i madh, kontrast i lartë | 15%+ e popullsisë e përjashtuar |
| PAK (persona me aftësi të kufizuara) | Asnjë navigim me tastierë, asnjë lexues ekrani, pa ARIA | Diskriminim ligjor |
| Fëmijë (<13 vjeç) | Asnjë verifikim moshe, asnjë pranim prindëror | Shkelje COPPA/ligjeve për fëmijët |
| Jofolës të shqipes | Vetëm shqip, pa gjuhë alternative | Komunitet ndërkombëtar i përjashtuar |

### 14.3 Linket ligjore në footer

```
Kushtet e Përdorimit  →  href="#"  →  ASGJË
Politika e Privatësisë  →  href="#"  →  ASGJË
Politika e Cookie  →  href="#"  →  ASGJË
Rregullat e Përdorimit  →  href="#"  →  ASGJË
Siguria  →  href="#"  →  ASGJË
```

> *"Keni pesë linke ligjore dhe asnjëra nuk çon diku. Kjo nuk është platformë e gatshme — është maket."*

---

## 15. KOMUNIKIMI — BOSHLLËK THELBËSOR

### 15.1 Si komunikojnë vullnetarët me qytetarët?

| Kanali | Ekziston? |
|--------|----------|
| Mesazhe direkte brenda platformës | **JO** |
| Chat në kohë reale | **JO** |
| Email automatik pas aprovimit | **VETËM verifikim regjistrimi** |
| SMS kujtese para eventit | **JO** |
| Numër telefoni i dukshëm | **JO** (nuk mblidhet fare) |
| Sistemi i urgjencës "SOS" | **JO** |

> *"Nëse dikush poston 'Kam nevojë për ushqim para mbrëmjes' — si e kontakton vullnetari? Nuk ka asnjë mënyrë. Kjo e bën platformën të pavlerë për raste urgjente."*

### 15.2 Si komunikon admini me komunitetin?

| Veprimi | Ekziston? |
|---------|----------|
| Dërgon njoftim masovik | **JO** |
| Dërgon email-blast | **JO** |
| Poston njoftim publik | **JO** (vetëm evente/kërkesa) |
| Sistemi eskalimi "pa përgjigje në 24 orë" | **JO** |

---

## 16. SHKALLËZUESHMËRIA — ANALIZA E NGARKESËS

### 16.1 Çfarë ndodh me 10,000 përdorues?

| Komponent | Gjendja aktuale | Me 10,000 | Me 100,000 |
|-----------|----------------|-----------|------------|
| Polling njoftimesh | Çdo 3 sekonda | 3,333 req/sek → **Server kollapson** | Pamundur |
| Faqja kryesore | 4 SQL queries | I ngadaltë | Timeout |
| Ngarkim imazhesh | Pa kompresim, pa CDN | 50GB disk | 500GB |
| Sesione PHP | Në skedarë lokal | I/O bottleneck | Bllokim |
| Cache | **Asnjë** | Çdo kërkim godet DB | DB kollapson |

### 16.2 Pa teste automatike

| Lloji i testeve | Ekziston? |
|----------------|----------|
| Unit tests (PHPUnit) | **JO** |
| Integration tests | **JO** |
| End-to-end tests (Cypress, Selenium) | **JO** |
| Load/stress tests (k6, JMeter) | **JO** |
| Security scanning (OWASP ZAP) | **JO** |

> *"Nëse bëni një ndryshim në kod, si e dini që nuk keni prishur diçka tjetër? Përgjigjja: Nuk e dini."*

---

## 17. FUNKSIONALITETE GJYSMË TË NDËRTUARA

| Funksionaliteti | % e plotë | Çfarë mungon |
|-----------------|-----------|-------------|
| Sistemi i badge-ve | 30% | Algortimi i fitimit hardcoded, pa progres drejt badge-it tjetër, pa databazë badge-sh |
| Email njoftimesh | 10% | Vetëm verifikim regjistrimi; pa kujtesa eventesh, pa digest javor |
| Aplikimi për kërkesë ndihme | 60% | Pa status "Në progres", pa foto verifikuese, pa vlerësim pas përfundimit |
| Multi-Admin | 0% | Të gjithë adminët shohin gjithçka; pa leje, pa ndarje zonash |
| Analitika | 5% | Shimmer loading ekziston — por asnjë grafik ose numër real i thellë |
| Service Worker (offline) | 20% | Cache-on 4 skedarë; offline nuk funksionon; pa versionim |
| Harta interaktive | 70% | Pina shfaqen; pa distancë, pa heatmap, pa zona lagjesh |
| Sistemi i pikëve | 40% | Formula ekziston; por pikët nuk lidhen me prezencën fizike — mund të "mashtrosh" |

---

## 18. PYETJET QË DO BËNTE PANELI I BLERËSVE

| # | Pyetja | Përgjigjja e ndershme |
|---|--------|----------------------|
| 1 | "Sa kushton mirëmbajtja vjetore?" | E pamatur — pa hosting, pa SLA, pa procedurë backupi |
| 2 | "Sa qytetarë mund të mbështesë njëkohësisht?" | ~100 para se serveri të ngadaltësohet seriozisht |
| 3 | "Si e mbroni nga sulmet kibernetike?" | Prepared statements + CSRF = baza. Por kredenciale në kod, pa HTTPS, pa WAF |
| 4 | "Çfarë ndodh nëse zhvilluesi largohet?" | Asnjë dokumentim teknik. Kodi i përzier shqip-anglisht. Zhvilluesi i ri ka nevojë për javë |
| 5 | "Si provoni që platforma po funksionon?" | S'ka metrika. S'ka raporte. S'ka log-e |
| 6 | "A keni liçensë softueri?" | Nuk specifikohet |
| 7 | "A e keni testuar me përdorues realë?" | Nuk ka evidencë |
| 8 | "Si e verifikoni identitetin e vullnetarëve?" | Nuk e verifikojmë — regjistrimi i hapur pa kontroll |
| 9 | "Çfarë ndodh nëse vullnetari kryen veprim keqdashës?" | Admin mund ta bllokojë. Por s'ka log, s'ka raportim, s'ka njoftim automatik |
| 10 | "A ka ndonjë konkurrent?" | Po — Voluntarily, Be My Eyes, VolunteerMatch. Çfarë ka ndryshe ky? Fokusi lokal në Tiranë |

---

## 19. BLLOKUESIT KRITIKË — NDAL PARA ÇDO DEPLOIMI

| # | Bllokuesi | Ashpërsia |
|---|-----------|-----------|
| 1 | Kredenciale SMTP/DB në kod burimor | **FATALE** — një push në GitHub = akses total |
| 2 | Asnjë politikë privatësie funksionale | **LIGJORE** — Bashkia rrezikon gjobë |
| 3 | Asnjë raportim për vendimmarrës | **STRATEGJIKE** — pa numra, pa buxhet, pa dëshmi ndikimi |
| 4 | Shkallëzueshmëria kollapson me >100 përdorues | **OPERACIONALE** — platforma prishet nëse ka sukses |
| 5 | Linket ligjore boshe (`href="#"`) | **REPUTACIONALE** — duket si projekt i papërfunduar |
| 6 | Asnjë komunikim mes përdoruesve | **FUNKSIONALE** — platforma nuk e plotëson qëllimin bazë |
| 7 | Asnjë audit trail veprimesh administratori | **PËRPUTHSHMËRI** — s'mund të provohet se u ndoqën procedurat |
| 8 | Asnjë mekanizëm verifikimi identiteti | **SIGURIE** — çdokush mund të pretendojë se është vullnetar |

---

## 20. REKOMANDIMI PËRFUNDIMTAR

### Nëse jeni Bashkia e Tiranës duke shkuar me blerjen:

**MOS E DEPLOJONI NË GJENDJEN AKTUALE.**

**Pikat pozitive të forta:**
- Ideja është e shkëlqyer — Tirana ka nevojë reale për platformë solidariteti
- Dizajni vizual është profesional dhe tërheqës
- Baza teknike e sigurisë (prepared statements, CSRF, bcrypt) është solide
- Sistemi i eventeve dhe kërkesave funksionon
- Responsiviteti mobil është i mirë

**Por platforma ka nevojë për:**

1. **Fazë 1 (Urgjente):** Heqja e kredencialeve nga kodi, politika e privatësisë reale, HTTPS, fshirje llogarie
2. **Fazë 2 (Para lansimit):** Komunikim mes përdoruesve, raportim bazë, eksport CSV, sistem urgjence
3. **Fazë 3 (Pas 3 muajsh):** Analitikë e avancuar, multi-admin, WebSockets, testim ngarkese

**Vlerësimi si investitor:**
> *"Ky projekt ka themele të mira dhe vizion interesant. Por sot është prototip, jo produkt. Nëse ekipi zhvillues i kushton 2-3 muaj punë intensive problemeve të mësipërme, mund të bëhet një platformë e vërtetë bashkiake. Deri atëherë — është demo, jo deploim."*

---

# PJESA III — AUDITIMI I NIVELIT TË PËRSOSMËRISË: ÇDO VENDIM DIZAJNI NË PYETJE

> *Kjo seksion nuk trajton çfarë mungon — por pyet nëse çfarë EKZISTON është e saktë. Çdo veçori ekzistuese analizohet, pyetet dhe i propozohet një alternativë konkrete.*

---

## 21. SISTEMI I ROLEVE: ADMIN vs VULLNETAR

### Çfarë Ekziston Tani
Dy role: `Admin` dhe `Vullnetar`. Admini menaxhon gjithçka (evente, përdorues, kategori, raporte). Vullnetari aplikon për evente dhe krijon kërkesa/oferta.

### Pyetjet Kritike

**P1: A duhet Admini të ndryshojë rolin e çdokujt pa kufizime?**
Aktualisht, çdo Admin mund ta promovojë çdo Vullnetar në Admin me një klikim. Nuk ka:
- Log që regjistron kush e bëri ndryshimin
- Konfirmim me fjalëkalim para veprimit
- Aprovim nga admin tjetër (dual-authorization)
- Limit se sa admin mund të kenë

**Propozim konkret:** Ndryshimi i rolit duhet të kërkojë: (1) konfirmim me fjalëkalimin e adminit që vepron, (2) regjistrim në tabelë `admin_log` me `admin_id, target_user_id, veprim, koha`, (3) limit maksimal 3 admin në sistem (i konfigurueshëm).

**P2: Pse nuk ka rol "Koordinator" ose "Menaxher Eventesh"?**
Në realitet, një organizatë bashkiake ka persona me përgjegjësi specifike: dikush menaxhon evente, dikush përgjigjjet kërkesave, dikush miraton vullnetarë. Aktualisht Admin bën gjithçka.

**Propozim konkret:** Shtoni rol `Koordinator` me leje: krijon/menaxhon vetëm evente dhe aplikimet e tyre, por nuk ka akses tek menaxhimi i përdoruesve apo raportet. Kështu admin delegon pa dhënë kontroll total.

**P3: Pse Admini nuk mund të aplikojë për evente?**
Kodi kthen 403 nëse roli = Admin dhe tenton të aplikojë. Kjo do të thotë: nëse një administrator dëshiron gjithashtu të jetë vullnetar aktiv, nuk mundet. Nuk ka logjikë konceptuale pse admini nuk mund të vullnetarizojë.

**Propozim konkret:** Lejoni adminin të aplikojë për evente si vullnetar. Statistikat e tij regjistrohen njëlloj. Kjo motivon edhe stafin administrativ.

---

## 22. SISTEMI I PIKËVE (SCORE) — DIZAJNI MË KRITIK

### Çfarë Ekziston Tani
```
score = (acceptedApps × 5) + (totalApps × 1) + (totalRequests × 2)
scoreMax = 150
scorePercent = min(100, round((score / 150) × 100))
```
Shfaqet si progress bar në panelin e vullnetarit. **Nuk bën ASGJË tjetër.**

### Pyetjet Kritike

**P1: Pse pikët ekzistojnë nëse nuk zhbllokojnë asgjë?**
Aktualisht pikët janë vetëm një numër vizual. Nuk ka:
- Nivel/rangje që zhbllokojnë diçka
- Përparësi në aplikime
- Certifikata automatike
- Njohje publike

Kjo është motivim i zbrazët — pas 2 javësh përdorimi, vullnetari kupton që pikët janë thjesht dekorative.

**Propozim konkret — Sistemi me 5 Nivele:**

| Nivel | Emri | Pikë | Çfarë Zhbllokon |
|-------|------|------|-----------------|
| 1 | Fillestar | 0–19 | Akses bazë — mund të aplikojë për 3 evente njëkohësisht |
| 2 | Aktiv | 20–49 | Mund të aplikojë për 5 evente njëkohësisht + badge "Aktiv" në profil publik |
| 3 | I Besuar | 50–99 | Aplikimi i tij merr përparësi vizuale në listen e adminit (shfaqet lart) |
| 4 | Kontribues | 100–149 | Mund të gjenerojë certifikatë PDF vullnetarizmi + akses në seksion "Ekipi ynë" |
| 5 | Ambasador | 150+ | E-mail i personalizuar mirënjohje nga admini + logo badge në profil + mundësi të bëhet Koordinator |

**P2: Pse max = 150? Çfarë logjike ka kjo?**
Le ta llogarisim: Për të arritur 150 pikë duhen ~20 evente të pranuara (100 pikë) + 20 aplikime totale (20 pikë) + 15 kërkesa (30 pikë) = 150. Kjo kërkon ~6-12 muaj aktivitet intensiv. Problemi: pas arritjes 150, nuk ka motivim. Pikët duhet të jenë pa limit ku nivelet e larta kërkojnë gjithmonë më shumë.

**Propozim konkret:** Hiqni konceptin "max 150". Pikët rriten pafundësisht. Progress bar-i tregon progres drejt nivelit tjetër, jo drejt max-it. Kur arrini nivelin 5, progress bar-i tregon pikët totale si numër i thjeshtë.

**P3: Formula e pikëve — a janë peshat e sakta?**
- `totalApps × 1` — merr pikë edhe për aplikime të refuzuara. Kjo inkurajon aplikime masive pa cilësi.
- `totalRequests × 2` — krijon kërkesë ndihme = 2 pikë. Por ofertë ndihme = gjithashtu 2 pikë. A duhet oferta (që jep) të vlerësohet më shumë se kërkesa (që merr)?

**Propozim konkret — Formula e rishikuar:**

| Veprim | Pikë | Arsyeja |
|--------|------|---------|
| Aplikim për event (çdo statusi) | 0 | Të aplikosh nuk do të thotë të ndihmosh |
| Aplikim i pranuar për event | 8 | Konfirmim real i kontributit |
| Krijim ofertë ndihme | 3 | Proaktivitet — ofron ndihmë |
| Krijim kërkesë ndihme | 1 | Aktivitet në platformë, por nuk jep |
| Aplikim i pranuar për kërkesë ndihme | 5 | Ndihmë konkrete |
| Ditë anëtarësimi (çdo 30 ditë) | 1 | Loyalty bonus |

---

## 23. SISTEMI I BADGE-VE — ANALIZË E ÇDOMIT

### Çfarë Ekziston Tani
7 badge me kushte specifike:

| Badge | Kushti |
|-------|--------|
| Hapi i Parë | (apps + requests + help_apps) >= 1 |
| Startues Eventesh | accepted_events >= 1 |
| Ndihmues i Komunitetit | accepted_events >= 5 |
| Zëri i Lagjes | total_requests >= 3 |
| Mbështetës i Besuar | accepted_help_apps >= 3 |
| Anëtar Veteran | member_days >= 180 |
| All-Rounder | accepted_events >= 3 AND total_requests >= 2 AND accepted_help_apps >= 2 |

### Pyetjet Kritike

**P1: "Hapi i Parë" fitohet me vetëm 1 veprim — çfardo lloj veprimi. A ka vlerë?**
Nëse un krijoj 1 kërkesë ndihme "Kërkoj laptop", marr badge. Nuk kam ndihmuar askënd. Badge "Hapi i Parë" duhet të sinjalizojë fillimin e rrugëtimit si VULLNETAR, jo si KËRKUES.

**Propozim konkret:** "Hapi i Parë" duhet të kërkojë: 1 aplikim të pranuar PER event OSE 1 aplikim të pranuar PER kërkesë ndihme. Domethënë: duhet të kesh NDIHMUAR dikë realisht.

**P2: "Ndihmues i Komunitetit" kërkon 5 evente të pranuara. Me 8 evente totale në platformë, kjo do çka shumica e vullnetarëve nuk do e arrijnë kurrë.**
Nëse platforma ka 8 evente në muaj, dhe çdo vullnetar shkon në 1-2, duhen 3-5 muaj për 5 të pranuara. Kjo nuk është realiste për platformë të re.

**Propozim konkret:** Ulni pragun në 3 evente të pranuara. Shtoni badge të ri "Hero i Komunitetit" me prag 10, dhe "Legjenda" me prag 25. Kjo krijon shkallë progresive.

**P3: "All-Rounder" — kërkon aktivitet në TRE dimensione. A janë dimensionet e sakta?**
Kushti aktual: 3 evente + 2 requests + 2 help applications. Por nuk përfshin: kohëzgjatjen e anëtarësimit. Dikush mund ta arrijë në javën e parë nëse platforma ka shumë aktivitet.

**Propozim konkret:** Shtoni kusht kohor: `member_days >= 60`. All-Rounder duhet të sinjalizojë angazhim AFATGJATË, jo blitz-vullnetarizëm.

**P4: Badge-t nuk kanë data kur u fituan. Nuk mund të shihet progresioni.**
Aktualisht badge-t llogariten në kohë reale çdo herë që hapet profili. Nuk ka rekord historik.

**Propozim konkret:** Krijoni tabelë `user_badges (id, user_id, badge_key, earned_at)`. Kur vullnetari fiton badge për herë të parë: INSERT + njoftim. Kjo mundëson: "Urime! Sapo fituat badge-n 'Ndihmues i Komunitetit'!" si njoftim.

---

## 24. SISTEMI I EVENTEVE — PYETJE PËR ÇDO VENDIM

### Çfarë Ekziston Tani
Vetëm admini krijon evente. Eventet kanë: titull, datë, vendndodhje, koordinata, kategori, përshkrim, banner. Vullnetarët aplikojnë. Admini pranon/refuzon.

### Pyetjet Kritike

**P1: Pse vetëm admini mund të krijojë evente?**
Në një komunitet real, vullnetarë organizojnë vetë iniziativa (pastrim lagjeje, mbledhje veshjesh). Duke kufizuar krijimin tek admini, platforma bëhet e centralizuar — nuk stimulon iniciativën.

**Propozim konkret:** Vullnetarët me nivel ≥ 3 ("I Besuar") mund të propozojnë evente. Admini sheh "Eventet e propozuara" dhe i miraton ose refuzon para se të publikohen. Status workflow: `Draft → Propozuar → Pranuar/Refuzuar → Aktiv → Përfunduar`.

**P2: Nuk ka koncept "kapaciteti i eventit". Çdo event pranon vullnetarë pa limit.**
Eventet reale kanë limit: pastrimi i liqenit kërkon 20 persona, jo 200. Pa kapacitet:
- Admini duhet të refuzojë manualisht pasi mbushet
- Vullnetarët aplikojnë pa ditur nëse ka vend
- Nuk ka listë pritje

**Propozim konkret:** Shtoni fushë `kapaciteti INT NULL` në tabelën Eventi. Nëse `kapaciteti IS NOT NULL`: aplikimi automatikisht pranohet derisa mbushet, pastaj shkon në "Listë Pritje". UI tregon: "12/20 vende të mbushura". Kjo ul ngarkesën e adminit drastikisht.

**P3: Eventet e kaluara nuk kanë status "Përfunduar". Thjesht janë "në të kaluarën" bazuar në datë.**
Kjo do të thotë: nuk ka mekanizëm të shënosh "eventet u zhvillua me sukses" vs "u anulua". Nuk ka feedback loop.

**Propozim konkret:** Shtoni fushë `statusi ENUM('Aktiv', 'Përfunduar', 'Anuluar') DEFAULT 'Aktiv'`. Pas datës, admini (ose automatikisht pas 24 orësh) e kalon në "Përfunduar". Vetëm eventet "Përfunduar" i japin pikë vullnetarëve. Kjo ndikon bug-un ku vullnetari merr pikë për event që u anulua.

**P4: Fshirja e eventit fshin TË GJITHA aplikimet dhe njoftimet. Nuk ka "arkivim".**
Nëse admin fshin gabimisht, humbën 50 aplikime. Nuk ka rikuperim.

**Propozim konkret:** Në vend të DELETE, përdorni soft-delete: `is_archived = 1`. Eventet e arkivuara nuk shfaqen në listë, por ekzistojnë për statistika dhe histori. Fshirje e vërtetë vetëm për evente pa asnjë aplikim.

**P5: Nuk ka mundësi të duplikohet (klonohet) një event.**
Nëse bashkia organizon "Pastrim Liqeni" çdo muaj, admini duhet të krijojë çdo herë nga zero.

**Propozim konkret:** Buton "Klono Eventin" që kopjon titull, përshkrim, kategori, vendndodhje, koordinata — por lë bosh datën dhe banner-in. Kursen kohë administrimi.

---

## 25. KËRKESAT PËR NDIHMË & OFERTAT — DIZAJN I PYETSHËM

### Çfarë Ekziston Tani
Një tabelë e vetme `Kerkesa_per_Ndihme` me fushë `tipi = 'Kërkesë' | 'Ofertë'` + status `Open/Closed`. Vullnetarët krijojnë, vullnetarë të tjerë aplikojnë.

### Pyetjet Kritike

**P1: "Kërkesë" dhe "Ofertë" trajtohen identikisht në kod. A janë konceptualisht të njëjta?**
Një KËRKESË do të thotë "Kam nevojë, ndihmoni." Një OFERTË do të thotë "Kam diçka për të dhënë." Workflow-t janë të ndryshme:
- Kërkesë: dikush aplikon → pronari zgjedh → lidhet kontakti → ndihma jepet → mbyllet
- Ofertë: dikush interesohet → pronari konfirmon → artikulli/shërbimi transferohet → mbyllet

Por kodi i trajton njëlloj. Nuk ka konfirmim "ndihma u dha me sukses" ose "transferimi u krye".

**Propozim konkret:** Shtoni status të ri: `Open → Në Proces → Përfunduar → Mbyllur`. "Në Proces" aktivizohet kur pronari pranon dikë. "Përfunduar" kur pronari konfirmon që ndihma u dha realisht. Pikë jepen vetëm në "Përfunduar", jo në "Pranuar". Kjo parandalon: pranim fiktiv.

**P2: Nuk ka urgjencë/prioritet. "Ndihmë me ushqim" dukej njëlloj si "Ofroj kurse kompjuteri".**
Të dyja shfaqen në listë identike. Nuk ka mënyrë vizuale për të dalluar urgjencën.

**Propozim konkret:** Shtoni fushë `urgjenca ENUM('Normal', 'E lartë', 'Emergjencë') DEFAULT 'Normal'`. Kërkesat emergjente shfaqen me kuadër të kuq, listohen të parat, dhe gjenerojnë njoftim tek TË GJITHË vullnetarët e zonës.

**P3: Nuk ka zona/lagjje. Vendndodhja është tekst i lirë.**
"Tiranë" nuk mjafton. Dikush në Laprakë nuk ndihmon dot dikë në Kombinat pa transport. Nuk ka filtrim gjeografik.

**Propozim konkret:** Shtoni fushë `lagja ENUM('Laprakë', 'Kombinat', 'Blloku', 'Kinostudio', 'Yzberisht', ...) NULL` — lista e lagjjeve të Tiranës. Filtrimi sipas lagjes mundëson: "Shih kërkesat në lagjen time". Kjo bën platformën LOKALE, jo abstrakte.

**P4: Pronari i kërkesës mund të kontaktojë aplikuesin VETËM përmes email-it. Nuk ka chat ose mesazh në platformë.**
Email-i dërgohet nga sistemi me tekstin e pronarit. Por:
- Aplikuesi mund ta injorojë
- Nuk ka histori bisede
- Nëse email-i shkon në spam, asgjë

**Propozim konkret (minimal):** Shtoni tabelë `Mesazhi (id, id_dergues, id_marres, id_kerkese, teksti, krijuar_me, lexuar)`. Mundësoni komunikim bazë brenda platformës. Email mbetet si njoftim, por biseda ndodh në platformë.

**P5: Kur mbyllet një kërkesë, aplikuesit e refuzuar nuk njoftohen.**
Nëse 5 persona kanë aplikuar dhe pronari pranon 1, 4-t e tjerë mbesin pezull pafundësisht. Nuk marrin asnjë njoftim.

**Propozim konkret:** Kur statusi kalon në "Mbyllur" ose "Përfunduar", sistemi automatikisht njofton TË GJITHË aplikuesit e mbetur me statusi "Në pritje": "Kërkesa '{title}' u mbyll. Faleminderit për interesin."

---

## 26. SISTEMI I APLIKIMEVE — WORKFLOW I MANGËT

### Çfarë Ekziston Tani
Aplikimi ka 3 statuse: `Në pritje → Pranuar / Refuzuar`. Vullnetari mund të tërheqë vetëm aplikime "Në pritje".

### Pyetjet Kritike

**P1: Pse nuk ka status "Prezent" — konfirmim që vullnetari erdhi realisht?**
Aktualisht, "Pranuar" do të thotë "mund të vish". Por nuk ka mënyrë të dijmë nëse vullnetari erdhi realisht. Pikët jepen thjesht për pranim, jo për prezencë.

**Propozim konkret:** Shtoni statuse: `Në pritje → Pranuar → Prezent / Munguar`. Pas datës së eventit, admini shënon kush erdhi realisht. Pikët jepen vetëm për "Prezent". Kjo parandalon: pranim + mospranim faktike.

**P2: Admini mund ta kthejë statusin nga "Pranuar" mbrapsht në "Në pritje". A ka kuptim?**
Kodi lejon ndryshimin e statusit në çdo drejtim: Pranuar→Në pritje, Refuzuar→Pranuar, etj. Nuk ka logjikë workflow-i.

**Propozim konkret:** Tranzicione të lejuara:
- `Në pritje → Pranuar` ✓
- `Në pritje → Refuzuar` ✓
- `Pranuar → Prezent` ✓ (pas eventit)
- `Pranuar → Munguar` ✓ (pas eventit)
- Asnjë tranzicion tjetër i lejuar.

**P3: Vullnetari nuk mund të tërheqë aplikim të pranuar. Nëse ndryshon planin?**
Aktualisht, pas pranimit, vullnetari është "i bllokuar" — nuk ka mënyrë të anulojë prezencën. Kjo mund të çojë adminin të presë dikë që nuk vjen.

**Propozim konkret:** Lejoni tërheqjen e aplikimit të pranuar VETËM NËSE: data e eventit është > 48 orë larg. Brenda 48 orëve, tërheqja kërkon mesazh arsyeje tek admini.

---

## 27. SISTEMI I NJOFTIMEVE — I CEKËT

### Çfarë Ekziston Tani
Njoftimet janë rreshta teksti të pa-kategorizuar. Kanë: mesazh, i_lexuar, data. Shfaqen si listë. Mund të shënohen si të lexuara ose të fshihen.

### Pyetjet Kritike

**P1: Njoftimet nuk kanë tip/kategori. Nuk mund të filtrohen.**
"Aplikimi u pranua" dhe "Dikush aplikoi për kërkesën tuaj" duken njëlloj. Me 50+ njoftime, bëhet kaos.

**Propozim konkret:** Shtoni fushë `tipi ENUM('aplikim_event', 'aplikim_kerkese', 'status_ndryshim', 'sistem', 'admin_veprim')` dhe `link VARCHAR(500) NULL`. Çdo njoftim ka link direkt tek objekti përkatës. UI mundëson filtrim sipas tipit.

**P2: Njoftimet nuk kanë link. Përdoruesi nuk di ku të shkojë.**
"Aplikimi juaj për 'Pastrimi i Liqenit' u pranua!" — Ku shtyp për të parë detajet? Aktualisht: askund. Duhet të navigojë manualisht.

**Propozim konkret:** Çdo njoftim regjistrohet me `link = '/views/events.php?id=5'` ose `'/views/help_requests.php?id=3'`. Klikimi hap direkt objektin.

**P3: Njoftimet NGERËSE me volum — 30 për faqe, pa grup, pa agregim.**
Nëse admini ka 200 njoftime "X aplikoi për eventin Y" — lista bëhet e papërdorshme.

**Propozim konkret:** Agregoni njoftime të ngjashme: "5 persona aplikuan për 'Pastrimi i Liqenit'" në vend se 5 njoftime individuale. Implementohet me fushat `tip + objekt_id` dhe GROUP BY në frontend.

---

## 28. MENAXHIMI I PËRDORUESVE — PYETJE PËR ÇDO VEPRIM ADMIN

### Çfarë Ekziston Tani
Admini mund: listojë, shohë detaje, bllokojë, zhbllokojë, ndryshojë rol, deaktivizojë, riaktivizojë përdorues.

### Pyetjet Kritike

**P1: Bllokimi vs Çaktivizimi — a i kupton përdoruesi dallimin?**
- Bllokuar: llogaria ekziston por nuk lejohet hyrja. Vendim i adminit, mund të ketë arsye.
- Çaktivizuar: soft-delete. E ndryshme konceptualisht.

Por nga perspektiva e përdoruesit, efekti është IDENTICAL — nuk hyn dot. Nuk ka njoftim informativ kur tenton login pas bllokimit. Nëse bllokimi ka arsye, ku e sheh?

**Propozim konkret:** 
- Kur përdoruesi i bllokuar tenton login: faqe e dedikuar "Llogaria juaj është pezulluar" me arsyen specifike (nëse e ka) + buton kontakti.
- Kur përdoruesi i çaktivizuar tenton login: faqe "Llogaria juaj u çaktivizua" me mundësi riaktivizimi me email.
- Aktualisht ekziston `views/blocked.php` — por nuk shfaq arsyen specifike.

**P2: Admini mund të deaktivizojë dikë, por PËRDORUESI vetë nuk mund të fshijë llogarinë.**
GDPR kërkon "e drejtë fshirjeje". Aktualisht: çaktivizimi ruan TË GJITHA të dhënat. Përdoruesi nuk ka buton "Fshi llogarinë time" në cilësimet.

**Propozim konkret:** Shtoni veprim "Fshi llogarinë" në cilësimet e vullnetarit. Kjo kërkon: (1) konfirmim me fjalëkalim, (2) periudhë 14-ditore para fshirjes reale (mund ta anulojë), (3) anonimizim i të dhënave (emri→"Përdorues i fshirë", email→NULL, bio→NULL) në vend se fshirje e fortë, për të ruajtur integritetin e statistikave.

**P3: Nuk ka histori veprimesh administratori (audit trail).**
Kur admini bllokon dikë, ndryshon rol, apo fshin event — nuk regjistrohet. Kjo është problem i madh institucional: nuk mund të provohet kush bëri çfarë.

**Propozim konkret:** Tabelë `admin_log (id, admin_id, veprim, target_type, target_id, detaje_json, krijuar_me)`. Çdo veprim admini INSERT-ohet automatikisht. Paneli admin ka tab "Log Veprimesh" ku shihen kronologjikisht.

---

## 29. PROFILI I PËRDORUESIT — A ËSHTË I DIZAJNUAR MIRË?

### Çfarë Ekziston Tani
Profili ka: emër, bio (max 500 shkronja), foto, ngjyrë (12 opsione), toggle publik/privat. Profili publik shfaq badge-t.

### Pyetjet Kritike

**P1: 12 ngjyra profili — a kanë vlerë reale apo janë vetëm estetikë?**
Ngjyrat: emerald, ocean, sunset, rose, violet, slate, teal, amber, indigo, pink, lime, cyan. Shërbejnë vetëm si background gradient. Nuk kanë një funksion tjetër.

**Propozim i drejtë:** Mbajini — janë personalizim pozitiv me kosto zero. Por shtoni mundësinë e ngjyrës automatike bazuar në nivelin e pikëve: Niveli 1-2 = ngjyrat standard, Niveli 3+ = zhbllokon ngjyra "premium" (gold, platinum). Kjo lidh personalizimin me motivimin.

**P2: Bio max 500 shkronja — pa formatim. A mjafton?**
500 shkronja janë ~2-3 fjali. Për një vullnetar që do të prezantojë aftësitë, motivimin, dhe disponueshmërinë — mund të jetë pak. Por pa formatim (bold, lista), edhe 1000 shkronja do ishin muret teksti.

**Propozim konkret:** Rritni në 800 shkronja. Lejoni **bold** dhe *italic* me Markdown bazë (parse-ohet në shfaqje). Shtoni fushë e ndarë `aftesite VARCHAR(300)` ku vullnetari liston aftësi specigike (p.sh.: "Anglisht, Teknologji, Transport") — kjo mundëson filtrim: "Gjej vullnetarë me aftësi Transport".

**P3: Toggle publik/privat — çfarë fshihet kur është privat?**
Aktualisht: `profile_public = 0` nuk dokumentohet saktësisht çfarë bën. A fsheh emrin? Badge-t? A mund ta shohin të tjerët në listen e aplikuesve?

**Propozim konkret:** Specifikoni saktë: profili privat fsheh bio, badge-t, pikët, dhe statistikat. EMRI dhe roli mbeten vizible sepse janë të nevojshme për funksionimin (admini duhet ta shohë emrin e aplikuesit). Dokumentoni këtë në UI: "Kur profili është privat, emri juaj do jetë i dukshëm por statistikat dhe aktiviteti jo."

---

## 30. SISTEMI I KATEGORIVE — THJESHTËSIA QË KUFZON

### Çfarë Ekziston Tani
5 kategori: Mjedis, Sociale, Edukimi, Shëndetësi, Emergjenca. Vetëm admini i menaxhon. Përdoren VETËM për evente.

### Pyetjet Kritike

**P1: Pse kërkesat për ndihmë nuk kanë kategori?**
Eventet kanë kategori (Mjedis, Sociale, etj.) por kërkesat nuk kanë. "Ndihmë me ushqim" dhe "Ofroj transport" nuk kategorizohen. Kjo bën filtrimin e kërkesave shumë më pak efikas.

**Propozim konkret:** Përdorni TË NJËJTAT kategori edhe për kërkesat. Shtoni fushë `id_kategoria INT NULL` në tabelën `Kerkesa_per_Ndihme` me Foreign Key tek Kategoria. Filtrimi në listë bëhet i mundshëm.

**P2: Vetëm 5 kategori, pa hierarki, pa ikona. A mjaftojnë?**
Për fillim, 5 kategori janë ok. Por me rritje: "Sociale" mbulon ushqim, veshje, strehim, transport — shumë të ndryshme.

**Propozim konkret:** Shtoni fushë `ikona VARCHAR(50)` dhe `ngjyra VARCHAR(7)` në Kategoria. Çdo kategori ka ikonë (Font Awesome) dhe ngjyrë. Mos shtoni nën-kategori tani — kompleksiteti nuk ja vlen me < 50 evente.

---

## 31. RAPORTET — FUNKSION I GJYSËM-NDËRTUAR

### Çfarë Ekziston Tani
Admini gjeneron raporte me tip: "Përmbledhje Mujore" ose "Vullnetarë Aktivë". Raportet janë tekst i ruajtur në databazë. Nuk ka eksport, grafik, ose scheduling.

### Pyetjet Kritike

**P1: Raporti "Përmbledhje Mujore" gjeneron tekst statik si: "8 evente, 19 aplikime". A ka vlerë?**
Kjo informacion gjendet tashmë në panelin e statistikave. Raporti nuk shton ASGJË të ri.

**Propozim konkret:** Raporti mujor duhet të përfshijë: (1) krahasim me muajin e kaluar ("+3 evente, -2 aplikime"), (2) top 3 vullnetarët me pikë, (3) kërkesat pa përgjigje > 7 ditë, (4) evente ku asnjë nuk aplikoi. Kjo informacion nuk ekziston askund tjetër.

**P2: Nuk ka eksport (CSV, PDF). Raportet lexohen vetëm në platformë.**
Për vendimmarrës institucionalë, duhet raport i printushëm. Pa PDF ose CSV, admini duhet të copy-paste.

**Propozim konkret:** Shtoni buton "Shkarko PDF" që gjeneron raport me logo, data, statistika, dhe tabelë vullnetarësh aktivë. Përdorni librari PHP si TCPDF ose DomPDF. Edhe një buton "Shkarko CSV" për të dhënat tabelare.

---

## 32. IMAZHET DHE UPLOAD-I — DY SISTEME PA KOORDINIM

### Çfarë Ekziston Tani
- `api/upload.php` — Max 5MB, resize 700px, WebP quality 80, ruan në `/public/assets/uploads/`
- `api/users.php?upload_profile_picture` — Max 6MB, resize 640px, WebP quality 78, ruan në `/uploads/images/profiles/`

### Pyetjet Kritike

**P1: Pse ka DY endpoint-e upload me specifika të ndryshme?**
Max size: 5MB vs 6MB. Resize: 700px vs 640px. Quality: 80 vs 78. Path-e të ndryshme. Kjo është inkoherencë e qartë.

**Propozim konkret:** Unifikoni në NJË funksion upload me parametra:
```
upload_image($file, $type = 'general', $maxWidth = 700, $quality = 80)
- type='profile' → /uploads/images/profiles/, maxWidth=400, quality=85
- type='event' → /uploads/images/events/, maxWidth=800, quality=80  
- type='request' → /uploads/images/requests/, maxWidth=800, quality=80
```

**P2: Banner-i i eventit akceptohet si URL e jashtme (Unsplash, etj.). Kjo është problem sigurie.**
Nëse admini vendos URL Unsplash, imazhi ngarkohet direkt nga Unsplash. Kjo do të thotë:
- Nëse Unsplash e fshin, imazhi zhduket
- Tracking pixels mund të futen
- Mixed content nëse kaloni në HTTPS

**Propozim konkret:** Kur admini jep URL, sistemi e shkarkon, e ripërpunon, dhe e ruan lokalisht. URL-ja origjinale ruhet si referencë por nuk përdoret për shfaqje.

---

## 33. FSHIRJA E EVENTIT — KASKADAT E RREZIKSHME

### Çfarë Ekziston Tani
```sql
DELETE FROM Njoftimi WHERE ... (njoftimet e lidhura me aplikime)
DELETE FROM Aplikimi WHERE id_eventi = ?
DELETE FROM Eventi WHERE id_eventi = ?
```
Fshirja e eventit fshin TË GJITHA aplikimet DHE njoftimet e lidhura. Kjo nuk bëhet me transaksion.

### Pyetjet Kritike

**P1: Pse nuk përdoret transaksion (BEGIN/COMMIT)?**
Nëse fshirja e aplikimeve dështon pas fshirjes së njoftimeve, keni inkoherencë.

**Propozim konkret:** Mbështillni fshirjen në transaksion:
```php
$pdo->beginTransaction();
try {
    // delete notifications, applications, event
    $pdo->commit();
} catch (...) {
    $pdo->rollBack();
}
```

**P2: Kur fshihet eventi, vullnetarët nuk njoftohen.**
50 persona aplikuan, admini fshin eventin — askush nuk merr njoftim. Thjesht zhduken aplikimi nga lista e tyre.

**Propozim konkret:** Para fshirjes, dërgoni njoftim tek TË GJITHË aplikuesit: "Eventi '{title}' u anulua." Pastaj fshini njoftimet e vjetra por mos fshini njoftimet e reja.

---

## 34. RATE LIMITING — DIZAJN I GABUAR

### Çfarë Ekziston Tani
Rate limiting bazohet në session: `$_SESSION["rate_limit_{$key}"][]`. Default: 5 tentativa / 900 sekonda (15 min).

### Pyetjet Kritike

**P1: Rate limiting-u dështon nëse sulmuesi nuk ka session.**
Sulmuesi mund: thjesht mos dërgojë cookie session. Çdo request shkon pa session → pa limit. Sistemi nuk mbron asgjë kundër bruteforce reale.

**Propozim konkret:** Përdorni rate limiting bazuar në IP me tabelë databaze:
```sql
CREATE TABLE rate_limit (
    ip VARCHAR(45), action VARCHAR(50), 
    attempted_at TIMESTAMP DEFAULT NOW(),
    INDEX idx_ip_action (ip, action)
);
```
Kontrolloni: `SELECT COUNT(*) FROM rate_limit WHERE ip = ? AND action = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE`. Kjo funksionon edhe pa session.

---

## 35. CSRF — I SAKTË POR I PAMJAFTUESHËM

### Çfarë Ekziston Tani
Token CSRF gjenerohet me `bin2hex(random_bytes(32))`, ruhet në session, kontrollohet me `hash_equals()`. Enforcohet në POST/PUT/DELETE.

### Pyetjet Kritike

**P1: Token-i CSRF nuk refreshohet kurrë. I njëjti token përdoret për TË GJITHË jetën e session-it.**
Nëse token-i ekspozohet (XSS, referrer leak, shoulder surfing), sulmuesi e ka pafundësisht.

**Propozim konkret:** Gjeneroni token të ri pas ÇDO veprimi mutues (POST/PUT/DELETE) të suksesshëm. Ose të paktën: refresh çdo 30 minuta session-i.

---

## 36. EMAIL-ET — PIKA E VETME E DËSHTIMIT

### Çfarë Ekziston Tani
3 funksione email: verification, password_reset, notification. Të gjitha përdorin PHPMailer me SMTP. Nëse SMTP dështon, logrohet error dhe kthehet `false`.

### Pyetjet Kritike

**P1: Nëse email-i dështon, sistemi vazhdon sikur asgjë nuk ndodhi.**
Regjistrimi kalon edhe nëse email-i i verifikimit nuk dërgohet. Përdoruesi nuk e di. Nuk ka retry, nuk ka njoftim, nuk ka fallback visible.

**Propozim konkret:** Nëse email-i i verifikimit dështon: (1) shfaqni mesazh "Email-i nuk u dërgua. Provoni përsëri." me buton resend, (2) ruani në databazë `email_queue` me status `pending/failed/sent` + retry count, (3) cron job çdo 5 min tenton dërgimin e email-eve failed.

**P2: Tre funksione email kanë 90% kod identik (setup PHPMailer). Nuk ka DRY.**
`send_verification_email`, `send_password_reset_email`, dhe `send_notification_email` — ÇDO njëra ngarkoi autoload, kontrollon class, lexon config, krijon PHPMailer instancë me TË NJËJTAT cilësime.

**Propozim konkret:** Nxirrni funksion bazë:
```php
function create_mailer(): PHPMailer {
    // load config, create instance, set SMTP settings
    return $mail;
}
```
Pastaj çdo funksion specifik e thirr: `$mail = create_mailer(); $mail->Subject = ...; $mail->Body = ...;`

---

## 37. STATUSET — KAOS GJUHËSOR

### Çfarë Ekziston Tani

| Tabelë | Fushë | Vlerat |
|--------|-------|--------|
| Aplikimi | statusi | `'Në pritje', 'Pranuar', 'Refuzuar'` (Shqip) |
| Kerkesa_per_Ndihme | tipi | `'Kërkesë', 'Ofertë'` (Shqip) |
| Kerkesa_per_Ndihme | statusi | `'Open', 'Closed'` (ANGLISHT) |
| Perdoruesi | roli | `'Admin', 'Vullnetar'` (Shqip) |
| Perdoruesi | statusi_llogarise | `'Aktiv', 'Bllokuar', 'Çaktivizuar'` (Shqip) |

### Pyetja Kritike

**Pse statuset e Kerkesa_per_Ndihme janë ANGLISHT ndërsa TË GJITHA të tjerat janë SHQIP?**
Kjo është inkoherencë e pastër. Kodi bën krahasime me string-e: `$row['statusi'] === 'Open'` në disa vende, `$row['statusi'] === 'Në pritje'` në të tjera. Çdo bashkëpunëtor i ri do ngatërrohet.

**Propozim konkret:** Vendosni NJË gjuhë për vlerat e databazës. Rekomandimi: ANGLISHT për TË GJITHA vlerat e brendshme (database), SHQIP vetëm në UI. Krijoni mapping:
```php
const STATUS_LABELS = [
    'pending' => 'Në pritje',
    'approved' => 'Pranuar', 
    'rejected' => 'Refuzuar',
    'open' => 'E hapur',
    'closed' => 'E mbyllur'
];
```

---

## 38. DATABAZA — VENDIME STRUKTURORE NË PYETJE

### Pyetjet Kritike

**P1: Tabela `Raporti` — pse ruhet teksti i raportit si TEXT brenda databazës?**
Raporte janë tekst i gjeneruar nga query-t. Nëse të dhënat ndryshojnë, raporti vjetrohet. Por nuk mund ta rigjeneron sepse nuk ruan parametrat.

**Propozim konkret:** Ruani `parametra_json` (datat, filtrat) në raport. Kur admini hap raportin, ofrojini dy opsione: "Shih raportin origjinal" ose "Rigjenero me të dhëna aktuale".

**P2: Tabela `Njoftimi.mesazhi` — tekst i lirë pa struktur. Nuk mund të parsohet.**
"Aplikimi juaj për 'Pastrimi i Liqenit' u pranua!" — ky tekst nuk ka event_id, nuk ka action type, nuk ka link. Është string i papërdorshëm programatikisht.

**Propozim konkret:** Shtoni kolona:
```sql
ALTER TABLE Njoftimi ADD COLUMN tipi VARCHAR(30);
ALTER TABLE Njoftimi ADD COLUMN target_type VARCHAR(30);
ALTER TABLE Njoftimi ADD COLUMN target_id INT;
ALTER TABLE Njoftimi ADD COLUMN link VARCHAR(500);
```
Mesazhi mbetet për shfaqje, por metadata mundëson filtrim, grupim, dhe navigim.

**P3: Nuk ka `updated_at` në asnjë tabelë (përveç `deaktivizuar_me` në Perdoruesi).**
Asnjë tabelë nuk regjistron kur u modifikua për herë të fundit. Kjo bën debugimin e pamundur: "Kur u ndryshua statusi i këtij aplikimi?"

**Propozim konkret:** Shtoni `ndryshuar_me TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP` në: Aplikimi, Aplikimi_Kerkese, Kerkesa_per_Ndihme, Eventi, Perdoruesi.

---

## 39. SIGURIA E SESIONIT — MBROJTJE E PAMJAFTUESHME

### Çfarë Ekziston Tani
- Cookie: httponly=true, samesite=Lax, secure=false
- Session ID nuk regenerohet pas login
- Session nuk ka expiration explicit

### Pyetjet Kritike

**P1: Session ID nuk regenerohet pas login-it. Session fixation i mundshëm.**
Nëse sulmuesi e di session ID para login-it, pas login-it i njëjti session ID përban identitetin e përdoruesit.

**Propozim konkret:** Shtoni `session_regenerate_id(true)` pas login-it të suksesshëm në `login_action.php`. Kjo është 1 rresht kodi, por parandalon klasë të tërë sulmesh.

**P2: Session nuk ka kohëzgjatje maksimale. Nëse përdoruesi nuk bën logout, session-i jeton pafundësisht.**
Pa inactivity timeout, session i harruar në kompjuter publik mbetet aktiv pafundësisht.

**Propozim konkret:** Shtoni kontrolle:
```php
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_destroy(); // 1 orë inaktiviteti
    redirect('/views/login.php?expired=1');
}
$_SESSION['last_activity'] = time();
```

---

## 40. TABELA PËRMBLEDHËSE: ÇDO VENDIM EKZISTUES NË PYETJE

| # | Veçoria Ekzistuese | Vendimi Aktual | Vlerësim | Propozimi |
|---|-------------------|----------------|----------|-----------|
| 1 | Dy role: Admin/Vullnetar | Centralizim i plotë | ⚠️ Mangësi | Shto rol Koordinator |
| 2 | Admin ndryshon role | Pa log, pa konfirmim | ❌ Rrezik | Kërko fjalëkalim + audit log |
| 3 | Admin nuk aplikon për evente | Bllokuar me 403 | ❌ Pa logjikë | Lejo admin si vullnetar |
| 4 | Pikët: formula (5×acc + 1×tot + 2×req) | Pa funksion real | ❌ Dekorative | Sistemi me 5 nivele që zhbllokojnë |
| 5 | Score max = 150 | Arbitrar | ⚠️ Demotivues | Pa max, nivele progresive |
| 6 | Pikë për aplikime të refuzuara | 1 pikë/aplikim | ❌ Inkurajon spam | Pikë vetëm për pranim/prezencë |
| 7 | 7 badge me kushte fixe | Pa histori, pa njoftim | ⚠️ I mirë por i mangët | Tabelë user_badges + njoftim |
| 8 | "Hapi i Parë" me 1 veprim çfardo | Edhe kërkesë = badge | ❌ Pa vlerë | Kërko 1 ndihmë reale (pranim) |
| 9 | Vetëm admini krijon evente | Centralizim | ⚠️ Kufizues | Propozim eventesh nga vullnetarë |
| 10 | Evente pa kapacitet | Pa limit | ❌ Joreale | Fushë kapaciteti + listë pritje |
| 11 | Pa status "Përfunduar" për evente | Data = status | ⚠️ I thjeshtë | ENUM(Aktiv, Përfunduar, Anuluar) |
| 12 | Fshirje e fortë eventesh | DELETE kaskadë | ❌ Rrezik | Soft-delete (is_archived) |
| 13 | Kërkesë/Ofertë si identike | I njëjti workflow | ⚠️ I mangët | Statuse shtesë: Në Proces, Përfunduar |
| 14 | Pa urgjencë në kërkesa | Të gjitha = Normal | ❌ Pa prioritet | ENUM(Normal, E lartë, Emergjencë) |
| 15 | Pa lagje/zonë | Tekst i lirë | ❌ Pa filtrim lokal | Fushë lagje me lista |
| 16 | Pa mesazhe në platformë | Vetëm email | ❌ I papërshtatshëm | Tabelë Mesazhi + UI chat |
| 17 | Aplikuesit e refuzuar nuk njoftohen | Kur mbyllet kërkesa | ❌ UX e keqe | Njoftim automatik kur mbyllet |
| 18 | Pa status "Prezent" | Pikë për pranim | ❌ Pa verifikim | Shtoni Prezent/Munguar pas eventit |
| 19 | Statuse të kthyeshme | Pranuar→Në pritje | ❌ Pa workflow | Tranzicione të lejuara me rregulla |
| 20 | Njoftim pa tip/link | Tekst i lirë | ⚠️ Funksionon por kufizuar | Shtoni tipi + link + target_id |
| 21 | Rate limit me session | Bypassable | ❌ I prishur | Rate limit me IP + databazë |
| 22 | CSRF token i përjetshëm | Pa refresh | ⚠️ Rrezik mesatar | Refresh pas çdo veprimi mutues |
| 23 | Email dështon heshtazi | return false; | ❌ Pa feedback | Queue + retry + mesazh përdoruesi |
| 24 | 3 funksione email identike | Kod i përsëritur | ⚠️ DRY shkelje | Extract create_mailer() bazë |
| 25 | Statuse shqip+anglisht | 'Open' vs 'Në pritje' | ❌ Inkoherent | 1 gjuhë (EN) brenda + mapping AL |
| 26 | Raporti si tekst statik | Pa parametra, pa rigenerim | ⚠️ Kufizuar | Ruaj parametra, ofro rigjerim |
| 27 | Njoftimi pa strukturë | TEXT i lirë | ⚠️ Funksionon por | Shto tipi + target_type + target_id |
| 28 | Pa `updated_at` | Kur u ndryshua? | ❌ Pa debugim | Shto ON UPDATE CURRENT_TIMESTAMP |
| 29 | Session pa regenerim | Session fixation | ❌ Sigurie | session_regenerate_id(true) |
| 30 | Session pa timeout | Jeton pafundësisht | ❌ Sigurie | 1-orë inactivity timeout |
| 31 | 2 upload endpoint me specs ndryshme | 5MB/6MB, 700/640px | ⚠️ Inkoherent | 1 funksion me parametra |
| 32 | Banner si URL e jashtme | Unsplash direkt | ⚠️ Rrezik | Shkarko + ripërpuno lokalisht |
| 33 | Fshirje eventi pa njoftim | Vullnetarët nuk dinë | ❌ UX e keqe | Njofto para fshirjes |
| 34 | Pa fshirje llogarie nga përdoruesi | GDPR shkelje | ❌ Ligjore | Buton "Fshi llogarinë" me 14-ditë |
| 35 | Pa audit trail | Kush bëri çfarë? | ❌ Institucionale | Tabelë admin_log |

---

## 41. PRIORITETET E IMPLEMENTIMIT

### Faza Urgjente (Para çdo prezantimi)
1. **Session regeneration pas login** — 1 rresht kodi
2. **Session timeout 1-orë** — 5 rreshta kodi
3. **Audit log për veprime admin** — 1 tabelë + ~20 rreshta INSERT
4. **Njoftim kur fshihet event** — ~15 rreshta
5. **Statuset në 1 gjuhë** — migration + find-replace

### Faza e Dytë (Java 1-2)
6. Sistemi i pikëve me 5 nivele reale
7. Kapacitet eventesh + listë pritje
8. Status "Prezent/Munguar" pas eventit
9. Njoftim me tip + link
10. Rate limiting me IP

### Faza e Tretë (Java 3-4)
11. Rol Koordinator
12. Propozim eventesh nga vullnetarë
13. Kategori edhe për kërkesat
14. Mesazhim bazë në platformë
15. Eksport PDF/CSV raportesh

### Faza e Katërt (Muaji 2)
16. Fshirje llogarie (GDPR)
17. Soft-delete eventesh
18. Urgjencë + lagje në kërkesa
19. Email queue me retry
20. Badge me histori + njoftim

---

> **VLERËSIMI FINAL:** Sistemi ekzistues funksionon teknikisht, por pothuajse çdo vendim dizajni ka hapësirë për përmirësim konkret. Asnjë veçori nuk duhet FSHIRË — por 35 nga 35 vendime të analizuara duan modifikim. Prioriteti #1 janë çështjet e sigurisë (session, rate limit, audit) që kërkojnë < 100 rreshta kod total. Prioriteti #2 janë përmirësimet funksionale (pikët, njoftimet, kapaciteti) që e transformojnë prototiptin në produkt.
