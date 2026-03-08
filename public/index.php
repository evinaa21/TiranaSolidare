<?php
// ── Connect to DB and fetch data for landing page ──
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Fetch latest 8 help requests (Open only)
$stmtReq = $pdo->prepare(
    "SELECT kn.*, p.emri AS krijuesi_emri
     FROM Kerkesa_per_Ndihme kn
     JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
     WHERE kn.statusi = 'Open'
     ORDER BY kn.krijuar_me DESC
     LIMIT 8"
);
$stmtReq->execute();
$kerkesat = $stmtReq->fetchAll(PDO::FETCH_ASSOC);

// Fetch latest 8 events (upcoming first, then recent past)
$stmtEv = $pdo->prepare(
    "SELECT e.*, k.emri AS kategoria_emri
     FROM Eventi e
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     ORDER BY CASE WHEN e.data >= NOW() THEN 0 ELSE 1 END, e.data ASC
     LIMIT 8"
);
$stmtEv->execute();
$eventet = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

// Counts for hero stats
$totalVullnetare  = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE roli = 'Vullnetar'")->fetchColumn();
$totalEvente      = (int) $pdo->query("SELECT COUNT(*) FROM Eventi")->fetchColumn();
$totalNdihmuara   = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'Closed'")->fetchColumn();

// Categories with event counts
$kategorite = $pdo->query(
    "SELECT k.id_kategoria, k.emri, COUNT(e.id_eventi) AS event_count
     FROM Kategoria k
     LEFT JOIN Eventi e ON e.id_kategoria = k.id_kategoria
     GROUP BY k.id_kategoria
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
  <link rel="stylesheet" href="assets/styles/main.css?v=20260308d">
  <link rel="stylesheet" href="assets/styles/index.css?v=20260308d">
</head>
<body>
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
    
  </section>

  <section id="si-funksionon">
    <div class="sf-wrapper">
      <div class="sf-header">
        <span class="sf-label">Si funksionon</span>
        <h2>Katër hapa drejt <span class="sf-accent">ndryshimit</span></h2>
        <p>Nga regjistrimi deri te ndikimi — procesi është i thjeshtë dhe i hapur për të gjithë qytetarët e Tiranës.</p>
      </div>

      <div class="sf-grid">
        <div class="sf-card sf-card--1">
          <div class="sf-card__top">
            <span class="sf-card__step">01</span>
            <div class="sf-card__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
            </div>
          </div>
          <h3>Krijo llogarinë</h3>
          <p>Regjistrohu në pak sekonda si vullnetar ose si qytetar që kërkon ndihmë. Procesi është i shpejtë dhe i sigurtë.</p>
        </div>

        <div class="sf-card sf-card--2">
          <div class="sf-card__top">
            <span class="sf-card__step">02</span>
            <div class="sf-card__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>
            </div>
          </div>
          <h3>Eksploro mundësitë</h3>
          <p>Shfleto kërkesat e hapura për ndihmë dhe eventet e ardhshme. Gjej ku mund të kontribuosh.</p>
        </div>

        <div class="sf-card sf-card--3">
          <div class="sf-card__top">
            <span class="sf-card__step">03</span>
            <div class="sf-card__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 12h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 14"/><path d="m7 18 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 13 6 6"/></svg>
            </div>
          </div>
          <h3>Merr pjesë aktivisht</h3>
          <p>Apliko për vullnetarizëm, ndihmo në evente, ose posto kërkesa për ndihmë në komunitet.</p>
        </div>

        <div class="sf-card sf-card--4">
          <div class="sf-card__top">
            <span class="sf-card__step">04</span>
            <div class="sf-card__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><path d="M12 5 9.04 7.96a2.17 2.17 0 0 0 0 3.08c.82.82 2.13.85 3 .07l2.07-1.9a2.82 2.82 0 0 1 3.79 0l2.96 2.66"/><path d="m18 15-2-2"/><path d="m15 18-2-2"/></svg>
            </div>
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
        <span class="kbtn-label">Eksploro Mundësitë</span>
        <h2>Zbulo kauzën tënde të <span class="kbtn-accent">radhës</span></h2>
        <p>Bashkohu me mijëra qytetarë që po bëjnë ndryshimin. Zgjidh fushën ku dëshiron të kontribuosh.</p>
      </div>

      <div class="kbtn-grid">
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
        ?>
        <a href="/TiranaSolidare/views/events.php?category=<?= $kat['id_kategoria'] ?>" class="kbtn-card kbtn-card-<?= $index ?>">
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

  <section id="kerkesat">
    <div id="kerkesat-title">
      <h2>Kërkesat e fundit</h2>
      <a href="/TiranaSolidare/views/help_requests.php" class="btn_secondary">Shiko te gjitha <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-move-right-icon lucide-move-right"><path d="M18 8L22 12L18 16"/><path d="M2 12H22"/></svg></a>
    </div>
    <?php include 'components/horizontalScroller/hs_start.php' ?>
      <?php if (empty($kerkesat)): ?>
        <p style="padding: 40px; color: #888;">Nuk ka kërkesa për momentin.</p>
      <?php else: ?>
        <?php foreach ($kerkesat as $k): ?>
          <div class="help_card">
              <?php if (!empty($k['imazhi'])): ?>
                <img src="<?= htmlspecialchars($k['imazhi']) ?>" alt="<?= htmlspecialchars($k['titulli']) ?>" class="help_card_img">
              <?php endif; ?>
              <span class="help_card_status <?= $k['tipi'] === 'Kërkesë' ? 'request' : 'offer' ?>">
                <?= $k['tipi'] === 'Kërkesë' ? 'Kërkoj ndihmë' : 'Ofroj ndihmë' ?>
              </span>
              <span class="help_card_title"><?= htmlspecialchars($k['titulli']) ?></span>
              
              <div class="help_card_info">
                <div class="help_card_info_location">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-icon"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <?= htmlspecialchars($k['krijuesi_emri']) ?>
                </div>
                <div class="help_card_info_time">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-icon lucide-clock"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                  <?= koheParapake($k['krijuar_me']) ?>
                </div>
              </div>
              <p class="help_card_description"><?= htmlspecialchars($k['pershkrimi'] ?? '') ?></p>
              <a href="/TiranaSolidare/views/help_requests.php?id=<?= $k['id_kerkese_ndihme'] ?>" class="help_card_btn btn_secondary">Shiko <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg></a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php include 'components/horizontalScroller/hs_end.php' ?>
  </section>

  <section id="eventet">
    <div id="eventet-title">
      <h2>Eventet e fundit</h2>
      <a href="/TiranaSolidare/views/events.php" class="btn_secondary">Shiko te gjitha <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-move-right-icon lucide-move-right"><path d="M18 8L22 12L18 16"/><path d="M2 12H22"/></svg></a>
    </div>
    <?php include 'components/horizontalScroller/hs_start.php' ?>
      <?php if (empty($eventet)): ?>
        <p style="padding: 40px; color: #888;">Nuk ka evente për momentin.</p>
      <?php else: ?>
        <?php foreach ($eventet as $ev): ?>
          <div class="help_card">
              <?php if (!empty($ev['banner'])): ?>
                <img src="<?= htmlspecialchars($ev['banner']) ?>" alt="<?= htmlspecialchars($ev['titulli']) ?>">
              <?php endif; ?>
              <span class="help_card_status request"><?= htmlspecialchars($ev['kategoria_emri'] ?? 'Event') ?></span>
              <span class="help_card_title"><?= htmlspecialchars($ev['titulli']) ?></span>
              
              <div class="help_card_info">
                <div class="help_card_info_location">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin-icon lucide-map-pin"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                  <?= htmlspecialchars($ev['vendndodhja'] ?? 'Tiranë') ?>
                </div>
                <div class="help_card_info_time">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar-icon"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                  <?= date('d M Y, H:i', strtotime($ev['data'])) ?>
                </div>
              </div>
              <p class="help_card_description"><?= htmlspecialchars(mb_substr($ev['pershkrimi'] ?? '', 0, 150)) ?></p>
              <a href="/TiranaSolidare/views/events.php?id=<?= $ev['id_eventi'] ?>" class="help_card_btn btn_secondary">Shiko <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg></a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php include 'components/horizontalScroller/hs_end.php' ?>
  </section>

  <section id="regjistrohu">
      <div id="regjistrohu-content">
        <h2>Çdo veprim i vogël krijon ndryshim të madh !</h2>
        <p>Regjistrohu në platformën tonë dhe fillo të ofrosh ndihmë për ata që kanë nevojë. Së bashku mund të bëjmë një ndryshim pozitiv në komunitetin tonë.</p>
        <a href="/TiranaSolidare/views/register.php" class="btn_primary">Regjistrohu tani <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg></a>
      </div>
      <div id="regjistrohu-image">
        <img src="assets/images/vullnetare-img.png">
      </div>
  </section>
  
</main>

<?php include 'components/footer.php' ?>

<script src="assets/scripts/main.js"></script>
</body>
</html>