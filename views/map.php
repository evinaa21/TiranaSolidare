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

// Fetch all open help requests with coordinates
$stmtRequests = $pdo->query(
    "SELECT kn.id_kerkese_ndihme, kn.titulli, kn.vendndodhja, kn.latitude, kn.longitude,
            kn.tipi, kn.statusi, p.emri AS krijuesi_emri
     FROM Kerkesa_per_Ndihme kn
     JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
     WHERE kn.latitude IS NOT NULL AND kn.longitude IS NOT NULL
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
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="/TiranaSolidare/assets/css/map.css">
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<!-- ─── HERO ─── -->
<section class="map-page-hero">
  <h1>Harta e Komunitetit</h1>
  <p>Shiko të gjitha eventet dhe kërkesat për ndihmë në hartën e Tiranës.</p>
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
    Oferta
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
    Kërkesa për ndihmë
  </div>
  <div class="map-legend-item">
    <div class="map-legend-dot map-legend-dot--offer"></div>
    Oferta ndihmë
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
