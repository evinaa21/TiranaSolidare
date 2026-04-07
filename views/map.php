<?php
// views/map.php — Events-only community map
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Events are fully public — no session or privacy checks needed

$mapEventFilterSql = "e.is_archived = 0 AND LOWER(e.statusi) = 'active' AND e.data >= NOW()";

// Fetch all public events that have coordinates
$stmtEvents = $pdo->query(
    "SELECT e.id_eventi, e.titulli, e.vendndodhja, e.latitude, e.longitude, e.data,
            k.emri AS kategoria_emri
     FROM Eventi e
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     WHERE e.latitude IS NOT NULL AND e.longitude IS NOT NULL
  AND " . $mapEventFilterSql . "
     ORDER BY e.data ASC"
);
$events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);

// Category names derived from events (no extra query)
$catNames = array_values(array_unique(array_filter(array_column($events, 'kategoria_emri'))));
sort($catNames);

// Build markers JSON (events only — help requests omitted for privacy)
$markers = [];
foreach ($events as $ev) {
    $markers[] = [
        'lat'      => (float) $ev['latitude'],
        'lng'      => (float) $ev['longitude'],
        'title'    => $ev['titulli'],
        'address'  => $ev['vendndodhja'] ?? '',
        'type'     => 'event',
        'category' => $ev['kategoria_emri'] ?? '',
        'url'      => ts_app_path('views/events.php') . '?id=' . $ev['id_eventi'],
        'extra'    => date('d M Y', strtotime($ev['data'])) . ($ev['kategoria_emri'] ? ' • ' . $ev['kategoria_emri'] : ''),
    ];
}
$totalCount = count($events);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?= csrf_meta() ?>
  <title>Harta e Eventeve — Tirana Solidare</title>
  <link rel="stylesheet" href="<?= ts_app_path('public/assets/styles/main.css') ?>?v=20260401a">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="<?= ts_app_path('assets/css/map.css') ?>?v=20260407">
</head>
<body class="page-map">
<?php include __DIR__ . '/../public/components/header.php'; ?>

<div class="map-functional-layout">

  <!-- ── Sidebar ── -->
  <aside class="map-sidepanel">

    <div class="map-sidepanel__header">
      <h1>Harta e Eventeve</h1>
      <p><?= $totalCount ?> evente aktive me vendndodhje në Tiranë</p>
    </div>

    <div class="map-sidepanel__filters">
      <div class="map-filter-group">
        <button class="map-clean-chip active" data-cat="" onclick="filterByCat(null, this)">
          Të gjitha <span class="map-clean-count"><?= $totalCount ?></span>
        </button>
        <?php foreach ($catNames as $cat):
          $catCount = count(array_filter($events, fn($e) => ($e['kategoria_emri'] ?? '') === $cat));
        ?>
        <button class="map-clean-chip" data-cat="<?= htmlspecialchars($cat) ?>" onclick="filterByCat(<?= json_encode($cat) ?>, this)">
          <?= htmlspecialchars($cat) ?> <span class="map-clean-count"><?= $catCount ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="map-sidepanel__list" id="map-sidebar-items">
      <?php foreach ($events as $i => $ev): ?>
      <a href="<?= ts_app_path('views/events.php') ?>?id=<?= (int) $ev['id_eventi'] ?>"
         class="map-item-card" data-cat="<?= htmlspecialchars($ev['kategoria_emri'] ?? '') ?>" data-idx="<?= $i ?>"
         onmouseenter="highlightMarker(<?= $i ?>)" onmouseleave="unhighlightMarker(<?= $i ?>)">
        <span class="map-item-card__icon map-item-card__icon--event"></span>
        <div class="map-item-card__content">
          <strong><?= htmlspecialchars($ev['titulli']) ?></strong>
          <span><?= date('d M Y', strtotime($ev['data'])) ?><?= $ev['kategoria_emri'] ? ' &bull; ' . htmlspecialchars($ev['kategoria_emri']) : '' ?></span>
          <?php if (!empty($ev['vendndodhja'])): ?>
          <span><?= htmlspecialchars($ev['vendndodhja']) ?></span>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (empty($events)): ?>
      <p class="map-item-empty">Nuk ka evente aktive me vendndodhje.</p>
      <?php endif; ?>
    </div>

  </aside>

  <!-- ── Map ── -->
  <div class="map-viewport">
    <div id="overview-map" class="ts-map-canvas-full"></div>
    <div class="map-legend">
      <div class="legend-item">
        <span class="legend-dot event"></span>
        Evente
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../public/components/footer.php'; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= ts_app_path('assets/js/map-component.js') ?>?v=20260401a"></script>
<script>
const allMarkers = <?= json_encode($markers, JSON_UNESCAPED_UNICODE) ?>;
let leafletMarkers = [];

function initMap(markers) {
  const container = document.getElementById('overview-map');
  container.innerHTML = '';
  const inner = document.createElement('div');
  inner.id = 'overview-map-inner';
  inner.className = 'ts-map-overview';
  inner.style.width = '100%';
  inner.style.height = '100%';
  container.appendChild(inner);
  const result = TSMap.overview('overview-map-inner', markers);
  leafletMarkers = [];
  if (result && result.group) {
    result.group.eachLayer(function (layer) { leafletMarkers.push(layer); });
  }
}

function highlightMarker(idx) {
  if (leafletMarkers[idx]) leafletMarkers[idx].openPopup();
}
function unhighlightMarker(idx) {
  if (leafletMarkers[idx]) leafletMarkers[idx].closePopup();
}

function filterByCat(cat, btn) {
  document.querySelectorAll('.map-clean-chip').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const filtered = cat ? allMarkers.filter(m => m.category === cat) : allMarkers;
  initMap(filtered);
  document.querySelectorAll('.map-item-card').forEach(function (card) {
    card.style.display = (!cat || card.dataset.cat === cat) ? '' : 'none';
  });
}

document.addEventListener('DOMContentLoaded', function () {
  initMap(allMarkers);
});
</script>
<script src="<?= ts_app_path('public/assets/scripts/main.js') ?>?v=20260401a"></script>
</body>
</html>
