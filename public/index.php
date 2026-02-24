<?php
// ── Connect to DB and fetch data for landing page ──
require_once __DIR__ . '/../config/db.php';

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

// Fetch latest 8 events (upcoming first)
$stmtEv = $pdo->prepare(
    "SELECT e.*, k.emri AS kategoria_emri
     FROM Eventi e
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     ORDER BY e.data DESC
     LIMIT 8"
);
$stmtEv->execute();
$eventet = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

// Counts for hero stats
$totalVullnetare  = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE roli = 'Vullnetar'")->fetchColumn();
$totalEvente      = (int) $pdo->query("SELECT COUNT(*) FROM Eventi")->fetchColumn();
$totalNdihmuara   = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'Closed'")->fetchColumn();

// Helper: time-ago in Albanian
function koheParapake(string $datetime): string {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->y > 0)  return $diff->y . ' vit më parë';
    if ($diff->m > 0)  return $diff->m . ' muaj më parë';
    if ($diff->d > 0)  return $diff->d . ' ditë më parë';
    if ($diff->h > 0)  return $diff->h . ' orë më parë';
    if ($diff->i > 0)  return $diff->i . ' min më parë';
    return 'tani';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tirana Solidare</title>
  <link rel="stylesheet" href="assets/styles/main.css">
  <link rel="stylesheet" href="assets/styles/index.css">
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
  <h2>Si funksionon platforma?</h2>
  <p>Ne vetem 4 hapa te thjeshte, mund te behesh pjese e komunitetit solidar</p>

  <div id="si-funksionon-grid">

    <div class="card">
      <div class="card_icon">
        <!-- SVG 1 -->
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="lucide lucide-users-round">
          <path d="M18 21a8 8 0 0 0-16 0" />
          <circle cx="10" cy="8" r="5" />
          <path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3" />
        </svg>
        <span class="card_number">1</span>
      </div>

      <div class="card_text">
        <h3>Kërko ndihmë ose bëhu vullnetar</h3>
        <p>Krijo një llogari në platformën tonë.</p>
      </div>
    </div>

    <div class="card">
      <div class="card_icon">
        <!-- SVG 2 -->
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="lucide lucide-binoculars">
          <path d="M10 10h4" />
          <path d="M19 7V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v3" />
          <path d="M20 21a2 2 0 0 0 2-2v-3.851c0-1.39-2-2.962-2-4.829V8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2z" />
          <path d="M 22 16 L 2 16" />
          <path d="M4 21a2 2 0 0 1-2-2v-3.851c0-1.39 2-2.962 2-4.829V8a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v11a2 2 0 0 1-2 2z" />
          <path d="M9 7V4a1 1 0 0 0-1-1H6a1 1 0 0 0-1 1v3" />
        </svg>
        <span class="card_number">2</span>
      </div>

      <div class="card_text">
        <h3>Jep ose merr ndihmë</h3>
        <p>Posto një kërkesë për ndihmë ose ofro shërbimin tënd.</p>
      </div>
    </div>

    <div class="card">
      <div class="card_icon">
        <!-- SVG 3 -->
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="lucide lucide-hand-helping">
          <path d="M11 12h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 14" />
          <path d="m7 18 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9" />
          <path d="m2 13 6 6" />
        </svg>
        <span class="card_number">3</span>
      </div>

      <div class="card_text">
        <h3>Kontribuo në komunitet</h3>
        <p>Apliko për vullnetarizëm ose posto kërkesa për ndihmë.</p>
      </div>
    </div>

    <div class="card">
      <div class="card_icon">
        <!-- SVG 4 -->
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          class="lucide lucide-heart-handshake">
          <path d="M19.414 14.414C21 12.828 22 11.5 22 9.5a5.5 5.5 0 0 0-9.591-3.676.6.6 0 0 1-.818.001A5.5 5.5 0 0 0 2 9.5c0 2.3 1.5 4 3 5.5l5.535 5.362a2 2 0 0 0 2.879.052 2.12 2.12 0 0 0-.004-3 2.124 2.124 0 1 0 3-3 2.124 2.124 0 0 0 3.004 0 2 2 0 0 0 0-2.828l-1.881-1.882a2.41 2.41 0 0 0-3.409 0l-1.71 1.71a2 2 0 0 1-2.828 0 2 2 0 0 1 0-2.828l2.823-2.762" />
        </svg>
        <span class="card_number">4</span>
      </div>

      <div class="card_text">
        <h3>Ndrysho jetë</h3>
        <p>Shiko ndikimin tënd dhe ndërtoni një komunitet më të fortë.</p>
      </div>
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