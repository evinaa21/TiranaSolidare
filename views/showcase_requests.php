<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = ($isLoggedIn && ($_SESSION['roli'] ?? '') === 'Admin');

// ── Fetch real data ──
$statTotalKerkesa = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme")->fetchColumn();
$statOpen         = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'Open'")->fetchColumn();
$statClosed       = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'Closed'")->fetchColumn();
$statVullnetare   = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE roli = 'Vullnetar'")->fetchColumn();
$statOferta       = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE tipi = 'Ofertë'")->fetchColumn();
$statKerkesa      = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE tipi = 'Kërkesë'")->fetchColumn();

// Fetch all open requests with coordinates for the map
$allRequests = $pdo->query(
    "SELECT k.*, p.emri AS krijuesi_emri
     FROM Kerkesa_per_Ndihme k
     LEFT JOIN Perdoruesi p ON p.id_perdoruesi = k.id_perdoruesi
     WHERE k.statusi = 'Open'
     ORDER BY k.krijuar_me DESC
     LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);

// Separate by type
$requestsOnly = array_filter($allRequests, fn($r) => ($r['tipi'] ?? '') === 'Kërkesë');
$offersOnly   = array_filter($allRequests, fn($r) => ($r['tipi'] ?? '') === 'Ofertë');

// Build GeoJSON for the map
$geoFeatures = [];
foreach ($allRequests as $r) {
    if (!empty($r['latitude']) && !empty($r['longitude'])) {
        $geoFeatures[] = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float)$r['longitude'], (float)$r['latitude']],
            ],
            'properties' => [
                'id'    => (int)$r['id_kerkese_ndihme'],
                'title' => $r['titulli'] ?? '',
                'type'  => $r['tipi'] ?? 'Kërkesë',
                'status'=> $r['statusi'] ?? 'Open',
                'owner' => $r['krijuesi_emri'] ?? 'Anonim',
                'location' => $r['vendndodhja'] ?? '',
            ],
        ];
    }
}
$geoJSON = json_encode([
    'type' => 'FeatureCollection',
    'features' => $geoFeatures,
], JSON_UNESCAPED_UNICODE);

// Helper: time-ago in Albanian
function koheParapakeShowcase(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'tani';
    if ($diff < 3600) return (int)($diff/60) . ' min më parë';
    if ($diff < 86400) return (int)($diff/3600) . ' orë më parë';
    if ($diff < 604800) return (int)($diff/86400) . ' ditë më parë';
    return date('d/m/Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tabela e Lagjes — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260320">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/showcase-requests.css?v=20260320">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
</head>
<body class="page-showcase-requests">
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<!-- ═══════════════════════════════════════════════════════════
     HERO — Warm, Human, Compact
     ═══════════════════════════════════════════════════════════ -->
<section class="nb-hero">
  <div class="nb-hero__orb nb-hero__orb--1"></div>
  <div class="nb-hero__orb nb-hero__orb--2"></div>
  <div class="nb-hero__orb nb-hero__orb--3"></div>

  <div class="nb-hero__inner">
    <div class="nb-hero__content">
      <div class="nb-hero__badge">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
        Qytetarë për qytetarë
      </div>
      <h1>Tabela e <span>Lagjes</span></h1>
      <p class="nb-hero__subtitle">Shiko kush ka nevojë për ndihmë pranë teje dhe ofro dorën tënde. Çdo akt i vogël mirësie ka fuqinë të ndryshojë jetën e dikujt.</p>
    </div>

    <div class="nb-hero__stats">
      <div class="nb-hero-stat">
        <span class="nb-hero-stat__number nb-hero-stat__number--warm"><?= $statOpen ?></span>
        <span class="nb-hero-stat__label">Të hapura</span>
      </div>
      <div class="nb-hero-stat">
        <span class="nb-hero-stat__number nb-hero-stat__number--green"><?= $statClosed ?></span>
        <span class="nb-hero-stat__label">Plotësuara</span>
      </div>
      <div class="nb-hero-stat">
        <span class="nb-hero-stat__number"><?= $statVullnetare ?></span>
        <span class="nb-hero-stat__label">Vullnetarë</span>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     TABS — Kërkoj Ndihmë / Ofroj Ndihmë
     ═══════════════════════════════════════════════════════════ -->
<div class="nb-tabs-strip">
  <div class="nb-tabs-strip__inner">
    <button class="nb-tab nb-tab--request nb-tab--active" data-type="Kërkesë">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      Kërkoj Ndihmë
      <span class="nb-tab__count"><?= count($requestsOnly) ?></span>
    </button>
    <button class="nb-tab nb-tab--offer" data-type="Ofertë">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      Ofroj Ndihmë
      <span class="nb-tab__count"><?= count($offersOnly) ?></span>
    </button>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     SPLIT VIEW — Map + Scrollable List
     ═══════════════════════════════════════════════════════════ -->
<div class="nb-split">

  <!-- Map Panel -->
  <div class="nb-map-panel">
    <div class="nb-map-overlay">
      <div class="nb-map-search">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" placeholder="Kërko vendndodhje ose lagje..." id="nb-map-search-input">
      </div>
      <div class="nb-map-legend">
        <div class="nb-map-legend__item">
          <span class="nb-map-legend__dot nb-map-legend__dot--request"></span>
          Kërkon ndihmë
        </div>
        <div class="nb-map-legend__item">
          <span class="nb-map-legend__dot nb-map-legend__dot--offer"></span>
          Ofron ndihmë
        </div>
      </div>
    </div>
    <div id="nb-map"></div>
  </div>

  <!-- List Panel -->
  <div class="nb-list-panel">
    <div class="nb-list-header">
      <span class="nb-list-header__count" id="nb-list-count">
        <?= count($allRequests) ?> kërkesa <span>të hapura</span>
      </span>
      <button class="nb-list-header__sort">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 16 4 4 4-4"/><path d="M7 20V4"/><path d="m21 8-4-4-4 4"/><path d="M17 4v16"/></svg>
        Më të rejat
      </button>
    </div>

    <div class="nb-list-scroll" id="nb-list-scroll">
      <?php foreach ($allRequests as $i => $r): ?>
        <?php
          $isRequest = ($r['tipi'] ?? '') === 'Kërkesë';
          $typeClass = $isRequest ? 'request' : 'offer';
          $lat = $r['latitude'] ?? '';
          $lng = $r['longitude'] ?? '';
        ?>
        <a href="/TiranaSolidare/views/help_requests.php?id=<?= $r['id_kerkese_ndihme'] ?>"
           class="nb-req-card"
           data-type="<?= htmlspecialchars($r['tipi'] ?? '') ?>"
           data-id="<?= $r['id_kerkese_ndihme'] ?>"
           data-lat="<?= htmlspecialchars($lat) ?>"
           data-lng="<?= htmlspecialchars($lng) ?>"
           style="animation-delay: <?= $i * 0.04 ?>s">

          <div class="nb-req-card__indicator nb-req-card__indicator--<?= $typeClass ?>"></div>

          <div class="nb-req-card__body">
            <div class="nb-req-card__top">
              <span class="nb-req-card__type nb-req-card__type--<?= $typeClass ?>">
                <?= $isRequest ? 'Kërkoj ndihmë' : 'Ofroj ndihmë' ?>
              </span>
              <span class="nb-req-card__status">
                <span class="nb-req-card__status__dot"></span>
                E hapur
              </span>
            </div>
            <h3 class="nb-req-card__title"><?= htmlspecialchars($r['titulli']) ?></h3>
            <div class="nb-req-card__meta">
              <span class="nb-req-card__meta-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?= htmlspecialchars($r['krijuesi_emri'] ?? 'Anonim') ?>
              </span>
              <?php if (!empty($r['vendndodhja'])): ?>
                <span class="nb-req-card__meta-item">
                  <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                  <?= htmlspecialchars($r['vendndodhja']) ?>
                </span>
              <?php endif; ?>
              <span class="nb-req-card__meta-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= koheParapakeShowcase($r['krijuar_me']) ?>
              </span>
            </div>
          </div>

          <div class="nb-req-card__action">
            <span class="nb-req-card__help-btn nb-req-card__help-btn--<?= $isRequest ? 'warm' : 'green' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php if ($isRequest): ?><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><?php else: ?><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/><?php endif; ?></svg>
              <?= $isRequest ? 'Ndihmoj' : 'Shiko' ?>
            </span>
          </div>
        </a>

        <?php if ($i < count($allRequests) - 1): ?>
          <div class="nb-req-divider" data-type="<?= htmlspecialchars($r['tipi'] ?? '') ?>"></div>
        <?php endif; ?>
      <?php endforeach; ?>

      <?php if (empty($allRequests)): ?>
        <div style="text-align:center; padding: 60px 20px; color: var(--nb-text-muted);">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px; opacity:0.4"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
          <h3 style="margin-bottom:6px; font-size:1rem;">Nuk ka kërkesa të hapura</h3>
          <p style="font-size:0.88rem;">Bëhu i pari që poston një kërkesë ose ofron ndihmën tënde.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>


<!-- ═══════════════════════════════════════════════════════════
     MOMENTUM STATS — Social Proof
     ═══════════════════════════════════════════════════════════ -->
<section class="nb-momentum">
  <div class="nb-momentum__inner">
    <div class="nb-momentum-card">
      <div class="nb-momentum-card__icon nb-momentum-card__icon--warm">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      </div>
      <div class="nb-momentum-card__text">
        <span class="nb-momentum-card__number"><?= $statOpen ?></span>
        <span class="nb-momentum-card__label">Kërkesa të hapura</span>
      </div>
    </div>
    <div class="nb-momentum-card">
      <div class="nb-momentum-card__icon nb-momentum-card__icon--green">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
      </div>
      <div class="nb-momentum-card__text">
        <span class="nb-momentum-card__number"><?= $statClosed ?></span>
        <span class="nb-momentum-card__label">Të plotësuara këtë muaj</span>
      </div>
    </div>
    <div class="nb-momentum-card">
      <div class="nb-momentum-card__icon nb-momentum-card__icon--blue">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="nb-momentum-card__text">
        <span class="nb-momentum-card__number"><?= $statVullnetare ?></span>
        <span class="nb-momentum-card__label">Vullnetarë aktivë</span>
      </div>
    </div>
    <div class="nb-momentum-card">
      <div class="nb-momentum-card__icon nb-momentum-card__icon--purple">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      </div>
      <div class="nb-momentum-card__text">
        <span class="nb-momentum-card__number"><?= $statOferta ?></span>
        <span class="nb-momentum-card__label">Oferta ndihme</span>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     DUAL CTA — Post a Request / Offer Help
     ═══════════════════════════════════════════════════════════ -->
<section class="nb-cta">
  <div class="nb-cta__inner">
    <div class="nb-cta-card nb-cta-card--request">
      <div class="nb-cta-card__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      </div>
      <h3>Ke nevojë për ndihmë?</h3>
      <p>Posto një kërkesë dhe komunitetit i Tiranës do të dëgjojë. Është anonim, falas, dhe i shpejtë.</p>
      <?php if ($isLoggedIn): ?>
        <a href="/TiranaSolidare/views/volunteer_panel.php?tab=requests" class="nb-cta-card__btn">
          Posto kërkesën
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      <?php else: ?>
        <a href="/TiranaSolidare/views/register.php" class="nb-cta-card__btn">
          Regjistrohu për të postuar
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      <?php endif; ?>
    </div>

    <div class="nb-cta-card nb-cta-card--offer">
      <div class="nb-cta-card__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      </div>
      <h3>Dëshiron të ndihmosh?</h3>
      <p>Ofroi aftesitë ose kohën tënde. Dikush pranë teje mund ta ketë shumë nevojë.</p>
      <?php if ($isLoggedIn): ?>
        <a href="/TiranaSolidare/views/volunteer_panel.php?tab=requests" class="nb-cta-card__btn">
          Ofroj ndihmën time
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      <?php else: ?>
        <a href="/TiranaSolidare/views/register.php" class="nb-cta-card__btn">
          Regjistrohu për të ofruar
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

  // ── Initialize Map — Centered on Tirana ──
  const map = L.map('nb-map', {
    center: [41.3275, 19.8189],
    zoom: 13,
    zoomControl: false
  });

  L.control.zoom({ position: 'bottomright' }).addTo(map);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
    maxZoom: 19,
    subdomains: 'abcd'
  }).addTo(map);

  // ── Custom markers ──
  function createIcon(type) {
    const color = type === 'Kërkesë' ? '#c55a32' : '#1a8756';
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="40" viewBox="0 0 32 40">
      <path d="M16 0C7.2 0 0 7.2 0 16c0 12 16 24 16 24s16-12 16-24C32 7.2 24.8 0 16 0z" fill="${color}"/>
      <circle cx="16" cy="15" r="7" fill="white" opacity="0.9"/>
      <circle cx="16" cy="15" r="4" fill="${color}"/>
    </svg>`;
    return L.divIcon({
      html: svg,
      iconSize: [32, 40],
      iconAnchor: [16, 40],
      popupAnchor: [0, -36],
      className: 'nb-custom-marker'
    });
  }

  // Parse GeoJSON
  const geoData = <?= $geoJSON ?>;

  const requestCluster = L.markerClusterGroup({
    maxClusterRadius: 50,
    iconCreateFunction: function(cluster) {
      const count = cluster.getChildCount();
      return L.divIcon({
        html: '<div style="background:#c55a32;color:#fff;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-family:Bitter,serif;font-weight:700;font-size:0.85rem;box-shadow:0 2px 8px rgba(197,90,50,0.4);border:2px solid #fff;">' + count + '</div>',
        iconSize: [36, 36],
        className: ''
      });
    }
  });

  const offerCluster = L.markerClusterGroup({
    maxClusterRadius: 50,
    iconCreateFunction: function(cluster) {
      const count = cluster.getChildCount();
      return L.divIcon({
        html: '<div style="background:#1a8756;color:#fff;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-family:Bitter,serif;font-weight:700;font-size:0.85rem;box-shadow:0 2px 8px rgba(26,135,86,0.4);border:2px solid #fff;">' + count + '</div>',
        iconSize: [36, 36],
        className: ''
      });
    }
  });

  const markerMap = {};

  geoData.features.forEach(function(feature) {
    const coords = feature.geometry.coordinates;
    const props = feature.properties;
    const marker = L.marker([coords[1], coords[0]], {
      icon: createIcon(props.type)
    });

    const popupHTML = `
      <div style="font-family:Raleway,sans-serif;min-width:200px;">
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:${props.type === 'Kërkesë' ? '#c55a32' : '#1a8756'};margin-bottom:4px;">
          ${props.type === 'Kërkesë' ? 'Kërkoj ndihmë' : 'Ofroj ndihmë'}
        </div>
        <div style="font-family:Bitter,serif;font-size:1rem;font-weight:700;color:#1a2332;margin-bottom:6px;">
          ${props.title}
        </div>
        <div style="font-size:0.82rem;color:#6b7280;">
          ${props.owner} · ${props.location || 'Tiranë'}
        </div>
        <a href="/TiranaSolidare/views/help_requests.php?id=${props.id}" 
           style="display:inline-block;margin-top:10px;padding:8px 16px;border-radius:8px;font-size:0.8rem;font-weight:700;color:#fff;text-decoration:none;background:${props.type === 'Kërkesë' ? '#c55a32' : '#1a8756'};">
          Shiko detajet &rarr;
        </a>
      </div>`;
    marker.bindPopup(popupHTML, { maxWidth: 280 });

    markerMap[props.id] = marker;

    if (props.type === 'Kërkesë') {
      requestCluster.addLayer(marker);
    } else {
      offerCluster.addLayer(marker);
    }
  });

  map.addLayer(requestCluster);
  map.addLayer(offerCluster);


  // ── Tab Switching ──
  const tabs = document.querySelectorAll('.nb-tab');
  const cards = document.querySelectorAll('.nb-req-card');
  const dividers = document.querySelectorAll('.nb-req-divider');

  tabs.forEach(tab => {
    tab.addEventListener('click', function() {
      tabs.forEach(t => t.classList.remove('nb-tab--active'));
      this.classList.add('nb-tab--active');

      const type = this.dataset.type;
      let visibleCount = 0;

      cards.forEach(card => {
        const match = type === '' || card.dataset.type === type;
        card.style.display = match ? '' : 'none';
        if (match) visibleCount++;
      });

      dividers.forEach(d => {
        const match = type === '' || d.dataset.type === type;
        d.style.display = match ? '' : 'none';
      });

      document.getElementById('nb-list-count').innerHTML = 
        visibleCount + ' kërkesa <span>të hapura</span>';

      // Toggle map clusters
      if (type === 'Kërkesë') {
        map.addLayer(requestCluster);
        map.removeLayer(offerCluster);
      } else if (type === 'Ofertë') {
        map.removeLayer(requestCluster);
        map.addLayer(offerCluster);
      } else {
        map.addLayer(requestCluster);
        map.addLayer(offerCluster);
      }
    });
  });


  // ── Card-Map Interaction: Hover card → highlight on map ──
  cards.forEach(card => {
    card.addEventListener('mouseenter', function() {
      const id = parseInt(this.dataset.id);
      const lat = parseFloat(this.dataset.lat);
      const lng = parseFloat(this.dataset.lng);
      if (lat && lng && markerMap[id]) {
        markerMap[id].openPopup();
        map.setView([lat, lng], 15, { animate: true });
      }
    });
  });

});
</script>
</body>
</html>
