<?php
// ── Connect to DB and fetch data for landing page ──
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Fetch latest 8 help requests (Open only)
$stmtReq = $pdo->prepare(
    "SELECT kn.*, p.emri AS krijuesi_emri, kat.emri AS kategoria_emri
     FROM Kerkesa_per_Ndihme kn
     JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
     LEFT JOIN Kategoria kat ON kat.id_kategoria = kn.id_kategoria
     WHERE kn.statusi IN ('open','Open')
     ORDER BY kn.krijuar_me DESC
     LIMIT 8"
);
$stmtReq->execute();
$kerkesat = ts_normalize_rows($stmtReq->fetchAll(PDO::FETCH_ASSOC));

// Fetch latest 8 events (upcoming first, then recent past)
$stmtEv = $pdo->prepare(
    "SELECT e.*, k.emri AS kategoria_emri
     FROM Eventi e
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     WHERE e.is_archived = 0
     ORDER BY CASE WHEN e.data >= NOW() THEN 0 ELSE 1 END, e.data ASC
     LIMIT 8"
);
$stmtEv->execute();
$eventet = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

// Counts for hero stats
$totalVullnetare  = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE roli = 'volunteer'")->fetchColumn();
$totalEvente      = (int) $pdo->query("SELECT COUNT(*) FROM Eventi WHERE is_archived = 0")->fetchColumn();
$totalNdihmuara   = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi IN ('closed','Closed')")->fetchColumn();

// Categories with event counts
$kategorite = $pdo->query(
  "SELECT k.id_kategoria, k.emri, k.banner_path, COUNT(e.id_eventi) AS event_count
     FROM Kategoria k
     LEFT JOIN Eventi e ON e.id_kategoria = k.id_kategoria AND e.is_archived = 0
    GROUP BY k.id_kategoria, k.emri, k.banner_path
     ORDER BY event_count DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#00715D">
  <link rel="manifest" href="/TiranaSolidare/public/manifest.json">
  <title>Tirana Solidare</title>
  <link rel="stylesheet" href="assets/styles/main.css?v=20260401a">
  <link rel="stylesheet" href="assets/styles/requests.css?v=202603213">
  <link rel="stylesheet" href="assets/styles/index.css?v=20260401a">
</head>
<body class="page-home">
<?php include 'components/header.php' ?>

<main>

  <section id="main">
    <!-- Decorative SVG blob top-right -->
    <svg class="hero-blob-tr" viewBox="0 0 600 700" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
      <path d="M600,0 L600,550 Q540,620 430,580 Q280,530 220,400 Q160,270 280,190 Q370,130 420,60 Q450,10 600,0 Z" fill="#00715D" opacity="0.06"/>
      <path d="M600,0 L600,450 Q550,520 460,490 Q340,450 300,350 Q260,250 350,180 Q420,120 460,50 Q480,10 600,0 Z" fill="#00715D" opacity="0.04"/>
      <path d="M600,0 L600,320 Q570,380 500,360 Q400,330 380,260 Q360,190 420,140 Q470,100 500,40 Q520,0 600,0 Z" fill="#00715D" opacity="0.03"/>
    </svg>

    <div id="main-content">
      <span class="hero-badge">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19.414 14.414C21 12.828 22 11.5 22 9.5a5.5 5.5 0 0 0-9.591-3.676.6.6 0 0 1-.818.001A5.5 5.5 0 0 0 2 9.5c0 2.3 1.5 4 3 5.5l5.535 5.362a2 2 0 0 0 2.879.052z"/></svg>
        Platforma Zyrtare e Vullnetarizmit — Bashkia Tiranë
      </span>
      <h1>Bashkohu me komunitetin<br> që ndryshon jetë</h1>
      <p class="hero-subtitle">Së bashku mund të bëjmë më shumë. Ndihmo dikë sot dhe bëhu ndryshimi që dëshiron të shohësh.</p>
    
      <div id="main-stats">
        <span>
          <b data-count="<?= $totalVullnetare ?>">0</b>
          <i>Vullnetarë aktivë</i>
        </span>
        <span>
          <b data-count="<?= $totalEvente ?>">0</b>
          <i>Evente të realizuara</i>
        </span>
        <span>
          <b data-count="<?= $totalNdihmuara ?>">0</b>
          <i>Qytetarë të ndihmuar</i>
        </span>
      </div>
    </div>
    <div id="main-help">
      <div class="hero-img-wrapper">
        <img src="assets/images/hero_img_nobg.png" class="hero-img" alt="Tirana Solidare">
      </div>
    </div>

    <div class="hero-scroll-hint">
      <span>Zbulo</span>
      <div class="scroll-line"></div>
    </div>
    
  </section>

  <section id="regjistrohu">
      <!-- Decorative SVG blobs -->
      <svg class="reg-blob reg-blob--1" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(68, 139, 119, 0.07)" d="M44.7,-76.4C58.8,-69.2,71.8,-58.7,79.6,-45.1C87.4,-31.5,90.1,-15.7,88.5,-0.9C86.9,13.9,81.1,27.8,72.6,39.6C64.1,51.4,52.9,61.2,40.1,68.4C27.3,75.6,13.7,80.3,-0.8,81.7C-15.3,83.1,-30.5,81.3,-43.4,74.2C-56.2,67.2,-66.7,55,-73.8,41.2C-80.8,27.3,-84.4,11.7,-83.5,-3.5C-82.6,-18.7,-77.2,-33.4,-68,-45.1C-58.8,-56.8,-45.9,-65.4,-32.3,-72.8C-18.7,-80.3,-9.3,-86.5,3.2,-91.9C15.7,-97.4,30.5,-83.6,44.7,-76.4Z" transform="translate(100 100)"/></svg>
      <svg class="reg-blob reg-blob--2" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(0,113,93,0.05)" d="M39.5,-51.2C52.9,-46.3,66.8,-37.9,71.4,-25.7C76.1,-13.5,71.5,2.6,66,17.3C60.6,31.9,54.3,45.1,44,54.7C33.6,64.3,19.3,70.2,3.4,73.7C-12.6,77.2,-30.3,78.4,-42.2,70.1C-54,61.7,-60,43.8,-65.3,27.3C-70.6,10.8,-75.2,-4.2,-72.3,-18.2C-69.5,-32.1,-59.2,-45,-46.1,-50C-33.1,-55,-16.5,-52.2,-1.4,-50.2C13.7,-48.3,26.1,-56.1,39.5,-51.2Z" transform="translate(100 100)"/></svg>

      <div id="regjistrohu-content">
        <span class="regjistrohu-label reveal reveal-up">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
          Bëhu pjesë e komunitetit
        </span>
        <h2 class="reveal reveal-up reveal-d1">Çdo veprim i vogël krijon <br>ndryshim të <span class="regjistrohu-accent">madh!</span></h2>
        <p class="reveal reveal-up reveal-d2">Regjistrohu në platformën tonë dhe fillo të ofrosh ndihmë për ata që kanë nevojë. Së bashku mund të bëjmë një ndryshim pozitiv në komunitetin tonë.</p>
        <a href="/TiranaSolidare/views/register.php" class="btn_primary reveal reveal-up reveal-d3">Bëhu Vullnetar <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
      </div>
      <div id="regjistrohu-image" class="reveal reveal-right reveal-d2">
        <div class="regjistrohu-img-stack">
          <img src="assets/images/community-volunteers.png" alt="Vullnetarë" class="regjistrohu-img-front">
          <div class="regjistrohu-img-back"></div>
        </div>
      </div>
  </section>

  <section id="si-funksionon">
    <div class="sf-wrapper">
      <div class="sf-header">
        <span class="sf-label reveal reveal-up">Si funksionon</span>
        <h2 class="reveal reveal-up reveal-d1">Katër hapa drejt <span class="sf-accent">ndryshimit</span></h2>
        <p class="reveal reveal-up reveal-d2">Nga regjistrimi deri te ndikimi — procesi është i thjeshtë dhe i hapur për të gjithë qytetarët e Tiranës.</p>
      </div>

      <div class="sf-grid reveal-stagger">
        <div class="sf-card sf-card--1 reveal reveal-up">
          <div class="sf-card__top">
            <div class="sf-card__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
            </div>
            <span class="sf-card__step">01</span>
          </div>
          <h3>Krijo llogarinë</h3>
          <p>Regjistrohu në pak sekonda si vullnetar ose si qytetar që kërkon ndihmë. Procesi është i shpejtë dhe i sigurtë.</p>
        </div>

        <div class="sf-card sf-card--2 reveal reveal-up">
          <div class="sf-card__top">
            <div class="sf-card__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>
            </div>
            <span class="sf-card__step">02</span>
          </div>
          <h3>Eksploro mundësitë</h3>
          <p>Shfleto kërkesat e hapura për ndihmë dhe eventet e ardhshme. Gjej ku mund të kontribuosh.</p>
        </div>

        <div class="sf-card sf-card--3 reveal reveal-up">
          <div class="sf-card__top">
            <div class="sf-card__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 12h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 14"/><path d="m7 18 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 13 6 6"/></svg>
            </div>
            <span class="sf-card__step">03</span>
          </div>
          <h3>Merr pjesë aktivisht</h3>
          <p>Apliko për vullnetarizëm, ndihmo në evente, ose posto kërkesa për ndihmë në komunitet.</p>
        </div>

        <div class="sf-card sf-card--4 reveal reveal-up">
          <div class="sf-card__top">
            <div class="sf-card__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><path d="M12 5 9.04 7.96a2.17 2.17 0 0 0 0 3.08c.82.82 2.13.85 3 .07l2.07-1.9a2.82 2.82 0 0 1 3.79 0l2.96 2.66"/><path d="m18 15-2-2"/><path d="m15 18-2-2"/></svg>
            </div>
            <span class="sf-card__step">04</span>
          </div>
          <h3>Ndërto komunitetin</h3>
          <p>Shiko ndikimin tënd real dhe ndërto një Tiranë më solidare për të gjithë qytetarët.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="kategorite">
    <div class="kbtn-wrapper">
      <div class="kbtn-header">
        <span class="kbtn-label reveal reveal-up">Eksploro Mundësitë</span>
        <h2 class="reveal reveal-up reveal-d1">Zbulo kauzën tënde të <span class="kbtn-accent">radhës</span></h2>
        <p class="reveal reveal-up reveal-d2">Bashkohu me mijëra qytetarë që po bëjnë ndryshimin. Zgjidh fushën ku dëshiron të kontribuosh.</p>
      </div>

      <div class="kbtn-grid reveal-stagger">
        <?php
        // Map category names to high-quality images and content
        $katMeta = [
          'Mjedis' => [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z"/></svg>',
            'img' => 'https://images.unsplash.com/photo-1500828131278-8de6878641b8?q=80&auto=format&fit=crop&w=800',
            'desc' => 'Ndihmo në pastrimin dhe mbjelljen e pemëve.'
          ],
          'Sociale' => [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20a6 6 0 0 0-12 0"/><circle cx="12" cy="10" r="4"/><circle cx="12" cy="12" r="10"/></svg>',
            'img' => 'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?q=80&auto=format&fit=crop&w=800',
            'desc' => 'Dërgo mbështetje tek familjet në nevojë.'
          ],
          'Edukimi' => [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
            'img' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&auto=format&fit=crop&w=800',
            'desc' => 'Angazhohu në trajnime dhe ndihmo të rinjtë.'
          ],
          'Shëndetësi' => [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
            'img' => 'https://images.unsplash.com/photo-1584515933487-779824d29309?q=80&auto=format&fit=crop&w=800',
            'desc' => 'Kujdesu për komunitetin përmes fushatave.'
          ],
          'Emergjenca' => [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
            'img' => 'https://images.unsplash.com/photo-1547683905-f686c993aae5?q=80&auto=format&fit=crop&w=800',
            'desc' => 'Bëhu vullnetar i vijës së parë.'
          ]
        ];
        ?>
        <?php foreach ($kategorite as $index => $kat):
          $emri = $kat['emri'];
          $meta = $katMeta[$emri] ?? [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>',
            'img' => 'https://images.unsplash.com/photo-1593113598332-cd288d649433?q=80&w=800',
            'desc' => 'Zbuloni më shumë rreth kësaj kategorie.'
          ];
          if (!empty($kat['banner_path'])) {
            $meta['img'] = $kat['banner_path'];
          }
        ?>
        <a href="/TiranaSolidare/views/events.php?category=<?= $kat['id_kategoria'] ?>" class="kbtn-card kbtn-card-<?= $index ?> reveal reveal-scale">
          <div class="kbtn-img-wrap">
            <img src="<?= htmlspecialchars($meta['img']) ?>" alt="<?= htmlspecialchars($emri) ?>">
          </div>
          
          <div class="kbtn-top-badge">
            <span class="pulse-dot"></span>
            <?= (int)$kat['event_count'] ?> Evente
          </div>

          <div class="kbtn-dock">
            <div class="kbtn-dock-icon">
              <?= $meta['icon'] ?>
            </div>
            <div class="kbtn-dock-text">
              <h3><?= htmlspecialchars($emri) ?></h3>
              <p><?= htmlspecialchars($meta['desc']) ?></p>
            </div>
            <div class="kbtn-dock-arrow">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════
       EVENTS — 3D Cinematic Card Carousel
       ═══════════════════════════════════════════ -->
  <section id="eventet">
    <div class="evs-wrapper">
      <div class="evs-header">
        <div>
          <span class="evs-label reveal reveal-up">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            Eventet e fundit
          </span>
          <h2 class="reveal reveal-up reveal-d1">Mos humb <span class="evs-accent">mundësinë</span></h2>
        </div>
        <a href="/TiranaSolidare/views/events.php" class="btn_secondary">Shiko të gjitha <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
      </div>

      <?php if (empty($eventet)): ?>
        <p style="padding: 40px; color: rgba(255,255,255,0.5);">Nuk ka evente për momentin.</p>
      <?php else: ?>
      <div class="evs-stage" id="evs-stage">
        <div class="evs-cards">
          <?php foreach ($eventet as $idx => $ev): ?>
          <div class="evs-card" data-idx="<?= $idx ?>">
            <a href="/TiranaSolidare/views/events.php?id=<?= $ev['id_eventi'] ?>" class="evs-card__link">
              <div class="evs-card__img">
                <img src="<?= !empty($ev['banner']) ? htmlspecialchars($ev['banner']) : '/TiranaSolidare/public/assets/images/default-event.svg' ?>" alt="<?= htmlspecialchars($ev['titulli']) ?>" onerror="this.src='/TiranaSolidare/public/assets/images/default-event.svg'">
                <div class="evs-card__gradient"></div>
              </div>
              <div class="evs-card__body">
                <div class="evs-card__badges">
                  <span class="evs-badge"><?= htmlspecialchars($ev['kategoria_emri'] ?? 'Event') ?></span>
                  <?php if (strtotime($ev['data']) >= time()): ?>
                    <span class="evs-badge evs-badge--live"><span class="evs-pulse"></span> I ardhshëm</span>
                  <?php endif; ?>
                </div>
                <h3 class="evs-card__title"><?= htmlspecialchars($ev['titulli']) ?></h3>
                <p class="evs-card__desc"><?= htmlspecialchars(mb_substr($ev['pershkrimi'] ?? '', 0, 130)) ?>...</p>
                <div class="evs-card__meta">
                  <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= htmlspecialchars($ev['vendndodhja'] ?? 'Tiranë') ?>
                  </span>
                  <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                    <?= date('d M Y', strtotime($ev['data'])) ?>
                  </span>
                </div>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <button class="evs-nav evs-nav--prev" aria-label="Para">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <button class="evs-nav evs-nav--next" aria-label="Pas">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>
      <div class="evs-indicators" id="evs-indicators">
        <?php foreach ($eventet as $idx => $ev): ?>
          <button class="evs-dot-btn <?= $idx === 0 ? 'evs-dot-btn--active' : '' ?>" data-idx="<?= $idx ?>" aria-label="Event <?= $idx + 1 ?>">
            <span class="evs-dot-fill"></span>
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════
       REQUESTS — Community Voices Editorial
       ═══════════════════════════════════════════ -->
  <section id="kerkesat">
    <!-- Decorative floating orbs -->
    <div class="cv-orb cv-orb--1" aria-hidden="true"></div>
    <div class="cv-orb cv-orb--2" aria-hidden="true"></div>
    <div class="cv-orb cv-orb--3" aria-hidden="true"></div>

    <div class="cv-wrapper">
      <div class="cv-header">
        <div class="cv-header__left">
          <span class="cv-label reveal reveal-up">
            <span class="cv-label__pulse"></span>
            Zërat e komunitetit
          </span>
          <h2 class="reveal reveal-up reveal-d1">Dikush ka nevojë <br>për <span class="cv-accent">ty sot</span></h2>
          <p class="cv-subtitle reveal reveal-up reveal-d2">Lexo kërkesat dhe ofertat e komunitetit. Çdo ndihmë e vogël krijon ndryshim.</p>
        </div>
        <div class="cv-header__right">
          <div class="cv-stats">
            <div class="cv-stat">
              <span class="cv-stat__num"><?= count($kerkesat) ?></span>
              <span class="cv-stat__text">Kërkesa aktive</span>
            </div>
            <div class="cv-stat-divider"></div>
            <div class="cv-stat">
              <span class="cv-stat__num"><?= count(array_filter($kerkesat, fn($k) => $k['tipi'] === 'offer')) ?></span>
              <span class="cv-stat__text">Oferta ndihme</span>
            </div>
          </div>
          <a href="/TiranaSolidare/views/help_requests.php" class="cv-cta-btn">
            Shiko të gjitha
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </a>
        </div>
      </div>

      <?php if (empty($kerkesat)): ?>
        <p style="padding: 40px; color: #888;">Nuk ka kërkesa për momentin.</p>
      <?php else: ?>

      <!-- Featured Spotlight -->
      <?php $feat = $kerkesat[0]; ?>
      <div class="cv-spotlight cv-spotlight--<?= $feat['tipi'] === 'offer' ? 'offer' : 'request' ?> reveal reveal-up">
        <div class="cv-spotlight__deco" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="0.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.07">
            <?php if ($feat['tipi'] === 'request'): ?>
              <path d="M19.414 14.414C21 12.828 22 11.5 22 9.5a5.5 5.5 0 0 0-9.591-3.676.6.6 0 0 1-.818.001A5.5 5.5 0 0 0 2 9.5c0 2.3 1.5 4 3 5.5l5.535 5.362a2 2 0 0 0 2.879.052z"/>
            <?php else: ?>
              <path d="M11 12h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 14"/><path d="m7 18 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 13 6 6"/>
            <?php endif; ?>
          </svg>
        </div>
        <div class="cv-spotlight__content">
          <div class="cv-spotlight__badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <?php if ($feat['tipi'] === 'request'): ?>
                <path d="M19.414 14.414C21 12.828 22 11.5 22 9.5a5.5 5.5 0 0 0-9.591-3.676.6.6 0 0 1-.818.001A5.5 5.5 0 0 0 2 9.5c0 2.3 1.5 4 3 5.5l5.535 5.362a2 2 0 0 0 2.879.052z"/>
              <?php else: ?>
                <path d="M11 12h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 14"/><path d="m7 18 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 13 6 6"/>
              <?php endif; ?>
            </svg>
            <?= $feat['tipi'] === 'request' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?>
          </div>
          <?php if (!empty($feat['kategoria_emri'])): ?>
          <div class="cv-spotlight__category"><?= htmlspecialchars($feat['kategoria_emri']) ?></div>
          <?php endif; ?>
          <h3 class="cv-spotlight__title"><?= htmlspecialchars($feat['titulli']) ?></h3>
          <p class="cv-spotlight__desc"><?= htmlspecialchars(mb_substr($feat['pershkrimi'] ?? '', 0, 220)) ?>...</p>
          <div class="cv-spotlight__meta">
            <span class="cv-spotlight__author">
              <span class="cv-spotlight__avatar"><?= mb_strtoupper(mb_substr($feat['krijuesi_emri'] ?? 'A', 0, 1)) ?></span>
              <?= htmlspecialchars($feat['krijuesi_emri'] ?? 'Anonim') ?>
            </span>
            <span class="cv-spotlight__time">
              <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <?= koheParapake($feat['krijuar_me']) ?>
            </span>
          </div>
        </div>
        <a href="/TiranaSolidare/views/help_requests.php?id=<?= $feat['id_kerkese_ndihme'] ?>" class="cv-spotlight__link" aria-label="Shiko kërkesën">
          <span>Lexo më shumë</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      </div>

      <!-- Horizontal Gallery -->
      <?php if (count($kerkesat) > 1): ?>
      <div class="cv-gallery-wrap">
        <div class="cv-gallery" id="cv-gallery">
          <?php foreach (array_slice($kerkesat, 1) as $ki => $k): ?>
          <a href="/TiranaSolidare/views/help_requests.php?id=<?= $k['id_kerkese_ndihme'] ?>" class="cv-card cv-card--<?= $k['tipi'] === 'offer' ? 'offer' : 'request' ?>" style="--card-delay: <?= $ki * 0.1 ?>s">
            <div class="cv-card__top">
              <div class="cv-card__type">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <?php if ($k['tipi'] === 'request'): ?>
                    <path d="M19.414 14.414C21 12.828 22 11.5 22 9.5a5.5 5.5 0 0 0-9.591-3.676.6.6 0 0 1-.818.001A5.5 5.5 0 0 0 2 9.5c0 2.3 1.5 4 3 5.5l5.535 5.362a2 2 0 0 0 2.879.052z"/>
                  <?php else: ?>
                    <path d="M11 12h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 14"/><path d="m7 18 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 13 6 6"/>
                  <?php endif; ?>
                </svg>
                <?= $k['tipi'] === 'request' ? 'Kërkoj ndihmë' : 'Ofroj ndihmë' ?>
              </div>
              <span class="cv-card__time"><?= koheParapake($k['krijuar_me']) ?></span>
            </div>
            <?php if (!empty($k['kategoria_emri'])): ?>
            <div class="cv-card__category"><?= htmlspecialchars($k['kategoria_emri']) ?></div>
            <?php endif; ?>
            <h3 class="cv-card__title"><?= htmlspecialchars($k['titulli']) ?></h3>
            <p class="cv-card__desc"><?= htmlspecialchars(mb_substr($k['pershkrimi'] ?? '', 0, 95)) ?>...</p>
            <div class="cv-card__bottom">
              <span class="cv-card__author">
                <span class="cv-card__avatar"><?= mb_strtoupper(mb_substr($k['krijuesi_emri'] ?? 'A', 0, 1)) ?></span>
                <?= htmlspecialchars($k['krijuesi_emri'] ?? 'Anonim') ?>
              </span>
              <span class="cv-card__arrow">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
              </span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <!-- Gallery scroll buttons -->
        <button class="cv-scroll-btn cv-scroll-btn--prev" aria-label="Para">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <button class="cv-scroll-btn cv-scroll-btn--next" aria-label="Pas">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div>
  </section>

</main>

<?php include 'components/footer.php' ?>

<script src="assets/scripts/main.js?v=20260321e"></script>
</body>
</html>
