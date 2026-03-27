<?php
// views/map.php — Interactive overview map showing all events & help requests
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);

// Fetch all events with coordinates
$stmtEvents = $pdo->query(
    "SELECT e.id_eventi, e.titulli, e.vendndodhja, e.latitude, e.longitude, e.data,
            k.emri AS kategoria_emri
     FROM Eventi e
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     WHERE e.latitude IS NOT NULL AND e.longitude IS NOT NULL
     ORDER BY e.data DESC"
);
$events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

// Fetch all open help requests with coordinates (exclude Pending)
$stmtRequests = $pdo->query(
    "SELECT kn.id_kerkese_ndihme, kn.titulli, kn.vendndodhja, kn.latitude, kn.longitude,
            kn.tipi, kn.statusi, p.emri AS krijuesi_emri
     FROM Kerkesa_per_Ndihme kn
     JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
     WHERE kn.latitude IS NOT NULL AND kn.longitude IS NOT NULL
       AND kn.statusi != 'Pending'
     ORDER BY kn.krijuar_me DESC"
);
$requests = $stmtRequests->fetchAll(PDO::FETCH_ASSOC);

// Build markers JSON
$markers = [];
foreach ($events as $ev) {
    $markers[] = [
        'lat'     => (float) $ev['latitude'],
        'lng'     => (float) $ev['longitude'],
        'title'   => $ev['titulli'],
        'address' => $ev['vendndodhja'] ?? '',
        'type'    => 'event',
        'url'     => '/TiranaSolidare/views/events.php?id=' . $ev['id_eventi'],
        'extra'   => date('d M Y', strtotime($ev['data'])) . ($ev['kategoria_emri'] ? ' • ' . $ev['kategoria_emri'] : ''),
    ];
}
foreach ($requests as $req) {
    $markers[] = [
        'lat'     => (float) $req['latitude'],
        'lng'     => (float) $req['longitude'],
        'title'   => $req['titulli'],
        'address' => $req['vendndodhja'] ?? '',
        'type'    => $req['tipi'] === 'Ofertë' ? 'offer' : 'request',
        'url'     => '/TiranaSolidare/views/help_requests.php?id=' . $req['id_kerkese_ndihme'],
        'extra'   => $req['tipi'] . ' • ' . $req['statusi'],
    ];
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Harta — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/requests.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="/TiranaSolidare/assets/css/map.css">
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<!-- ─── HERO ─── -->
<section class="rq-hero">
  <!-- Animated SVG blobs -->
  <svg class="rq-blob rq-blob--1" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(0,113,93,0.12)" d="M44.7,-76.4C58.8,-69.2,71.8,-58.7,79.6,-45.1C87.4,-31.5,90.1,-15.7,88.5,-0.9C86.9,13.9,81.1,27.8,72.6,39.6C64.1,51.4,52.9,61.2,40.1,68.4C27.3,75.6,13.7,80.3,-0.8,81.7C-15.3,83.1,-30.5,81.3,-43.4,74.2C-56.2,67.2,-66.7,55,-73.8,41.2C-80.8,27.3,-84.4,11.7,-83.5,-3.5C-82.6,-18.7,-77.2,-33.4,-68,-45.1C-58.8,-56.8,-45.9,-65.4,-32.3,-72.8C-18.7,-80.3,-9.3,-86.5,3.2,-91.9C15.7,-97.4,30.5,-83.6,44.7,-76.4Z" transform="translate(100 100)"/></svg>
  <svg class="rq-blob rq-blob--2" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(225,114,84,0.10)" d="M39.5,-51.2C52.9,-46.3,66.8,-37.9,71.4,-25.7C76.1,-13.5,71.5,2.6,66,17.3C60.6,31.9,54.3,45.1,44,54.7C33.6,64.3,19.3,70.2,3.4,73.7C-12.6,77.2,-30.3,78.4,-42.2,70.1C-54,61.7,-60,43.8,-65.3,27.3C-70.6,10.8,-75.2,-4.2,-72.3,-18.2C-69.5,-32.1,-59.2,-45,-46.1,-50C-33.1,-55,-16.5,-52.2,-1.4,-50.2C13.7,-48.3,26.1,-56.1,39.5,-51.2Z" transform="translate(100 100)"/></svg>
  <svg class="rq-blob rq-blob--3" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(0,113,93,0.07)" d="M47.7,-73.2C60.9,-67.5,70,-53.1,76.3,-38C82.6,-22.8,86.2,-6.9,83.4,7.6C80.6,22.2,71.5,35.4,60.7,47.1C49.9,58.8,37.5,69,23.3,74.3C9.1,79.6,-6.9,80,-21.4,75.4C-35.9,70.8,-48.9,61.3,-58.8,49.1C-68.7,36.9,-75.5,22,-77.2,6.3C-78.9,-9.4,-75.5,-25.9,-67,-38.7C-58.5,-51.5,-44.9,-60.5,-31,-66.3C-17.1,-72.1,-3,-74.7,8.8,-71.1C20.5,-67.5,34.5,-78.9,47.7,-73.2Z" transform="translate(100 100)"/></svg>
  <div class="rq-grid-overlay"></div>

  <div class="rq-hero__inner">
    <span class="rq-hero__label">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
      Zbulimi i Përafërt
    </span>
    <h1>Harta e Komunitetit</h1>
    <p class="rq-hero__subtitle">Shiko të gjitha eventet dhe kërkesat për ndihmë në hartën e Tiranës.</p>

    <!-- Trust stats -->
    <div class="rq-trust-bar">
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <div><strong><?= count($events) ?></strong><span>Evente</span></div>
      </div>
      <div class="rq-trust-divider"></div>
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
        <div><strong><?= count(array_filter($requests, fn($r) => $r['tipi'] === 'Kërkesë')) ?></strong><span>Kërkesa</span></div>
      </div>
      <div class="rq-trust-divider"></div>
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
        <div><strong><?= count(array_filter($requests, fn($r) => $r['tipi'] === 'Ofertë')) ?></strong><span>Kontribute</span></div>
      </div>
      <div class="rq-trust-divider"></div>
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
        <div><strong><?= count($markers) ?></strong><span>Në Hartë</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ─── FILTERS ─── -->
<div class="map-page-filters">
  <button class="map-filter-btn active" data-filter="all" onclick="filterMarkers('all', this)">
    Të gjitha
    <span class="filter-count"><?= count($markers) ?></span>
  </button>
  <button class="map-filter-btn" data-filter="event" onclick="filterMarkers('event', this)">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
    Evente
    <span class="filter-count"><?= count($events) ?></span>
  </button>
  <button class="map-filter-btn" data-filter="request" onclick="filterMarkers('request', this)">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
    Kërkesa
    <span class="filter-count"><?= count(array_filter($requests, fn($r) => $r['tipi'] === 'Kërkesë')) ?></span>
  </button>
  <button class="map-filter-btn" data-filter="offer" onclick="filterMarkers('offer', this)">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
    Kontribute
    <span class="filter-count"><?= count(array_filter($requests, fn($r) => $r['tipi'] === 'Ofertë')) ?></span>
  </button>
</div>

<!-- ─── LEGEND ─── -->
<div class="map-page-legend">
  <div class="map-legend-item">
    <div class="map-legend-dot map-legend-dot--event"></div>
    Evente vullnetariati
  </div>
  <div class="map-legend-item">
    <div class="map-legend-dot map-legend-dot--request"></div>
    Kërkoj ndihmë
  </div>
  <div class="map-legend-item">
    <div class="map-legend-dot map-legend-dot--offer"></div>
    Dua të ndihmoj
  </div>
</div>

<!-- ─── MAP ─── -->
<div class="map-page-content">
  <div id="overview-map" class="ts-map-overview"></div>
</div>

</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/TiranaSolidare/assets/js/map-component.js"></script>
<script>
const allMarkers = <?= json_encode($markers, JSON_UNESCAPED_UNICODE) ?>;
let currentMap = null;

function initMap(markers) {
  const container = document.getElementById('overview-map');
  container.innerHTML = '';
  // Recreate map div (Leaflet doesn't like reinit on same element)
  const mapDiv = document.createElement('div');
  mapDiv.id = 'overview-map-inner';
  mapDiv.style.width = '100%';
  mapDiv.style.height = '100%';
  container.appendChild(mapDiv);

  currentMap = TSMap.overview('overview-map-inner', markers);
}

function filterMarkers(type, btn) {
  // Update active button
  document.querySelectorAll('.map-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  // Filter markers
  let filtered;
  if (type === 'all') {
    filtered = allMarkers;
  } else {
    filtered = allMarkers.filter(m => m.type === type);
  }

  initMap(filtered);
}

document.addEventListener('DOMContentLoaded', function() {
  initMap(allMarkers);
});
</script>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>
