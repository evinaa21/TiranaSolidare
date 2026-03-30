<?php
/**
 * api/export.php
 * ---------------------------------------------------
 * Data Export API for Admins
 *   GET ?type=users           - CSV of all users
 *   GET ?type=events          - CSV of all active events
 *   GET ?type=applications    - CSV of all applications
 *   GET ?type=report_html     - Printable HTML activity report
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

require_method('GET');
$admin = require_admin();

$type = $_GET['type'] ?? '';

if (!in_array($type, ['users', 'events', 'applications', 'report_html'], true)) {
    json_error('Lloji i eksportit i pavlefshme.', 400);
}

// HTML PRINT REPORT
if ($type === 'report_html') {
    $totalUsers   = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE statusi_llogarise = 'active'")->fetchColumn();
    $totalEvents  = (int) $pdo->query("SELECT COUNT(*) FROM Eventi WHERE is_archived = 0")->fetchColumn();
    $totalApps    = (int) $pdo->query("SELECT COUNT(*) FROM Aplikimi")->fetchColumn();
    $approvedApps = (int) $pdo->query("SELECT COUNT(*) FROM Aplikimi WHERE statusi = 'approved'")->fetchColumn();
    $openRequests = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'open'")->fetchColumn();

    $recentEvents = $pdo->query(
        "SELECT e.titulli, k.emri AS kategoria, e.data, e.vendndodhja, e.statusi,
                COUNT(a.id_aplikimi) AS total_aplikime
         FROM Eventi e
         LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
         LEFT JOIN Aplikimi a ON a.id_eventi = e.id_eventi
         WHERE e.is_archived = 0
         GROUP BY e.id_eventi
         ORDER BY e.data DESC
         LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);

    $topUsers = $pdo->query(
        "SELECT p.emri, p.roli, COUNT(a.id_aplikimi) AS app_count
         FROM Perdoruesi p
         JOIN Aplikimi a ON a.id_perdoruesi = p.id_perdoruesi AND a.statusi = 'approved'
         GROUP BY p.id_perdoruesi
         ORDER BY app_count DESC
         LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

    $generatedAt = date('d.m.Y H:i');
    $esc = fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename=report_' . date('Y-m-d') . '.html');
    ?>
<!DOCTYPE html>
<html lang="sq">
<head>
<meta charset="UTF-8">
<title>Raport - Tirana Solidare</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; margin: 40px; color: #1e293b; font-size: 14px; }
  h1 { color: #00715D; margin-bottom: 4px; font-size: 1.6rem; }
  h2 { color: #00715D; font-size: 1.1rem; margin: 28px 0 10px; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; }
  .meta { color: #6b7280; font-size: 0.85rem; margin-bottom: 32px; }
  .stats-grid { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 28px; }
  .stat-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; min-width: 140px; flex: 1; text-align: center; }
  .stat-card .num { font-size: 2rem; font-weight: 700; color: #00715D; }
  .stat-card .lbl { font-size: 0.8rem; color: #6b7280; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  th { background: #f1f5f9; text-align: left; padding: 8px 10px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: .04em; color: #475569; }
  td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
  tr:last-child td { border-bottom: none; }
  .badge { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
  .badge-active { background: #dcfce7; color: #166534; }
  .badge-completed { background: #dbeafe; color: #1e40af; }
  .badge-cancelled { background: #fee2e2; color: #991b1b; }
  .ft { margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 0.78rem; color: #9ca3af; }
  @media print {
    body { margin: 20mm; }
    .no-print { display: none !important; }
    @page { size: A4; margin: 20mm; }
  }
</style>
</head>
<body>
<button class="no-print" onclick="window.print()" style="float:right;padding:8px 18px;background:#00715D;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">Printo / Ruaj PDF</button>
<h1>Tirana Solidare - Raport</h1>
<p class="meta">Gjeneruar: <?= $esc($generatedAt) ?></p>

<h2>Statistika te pergjithshme</h2>
<div class="stats-grid">
  <div class="stat-card"><div class="num"><?= $totalUsers ?></div><div class="lbl">Perdorues aktive</div></div>
  <div class="stat-card"><div class="num"><?= $totalEvents ?></div><div class="lbl">Evente aktive</div></div>
  <div class="stat-card"><div class="num"><?= $totalApps ?></div><div class="lbl">Aplikime totale</div></div>
  <div class="stat-card"><div class="num"><?= $approvedApps ?></div><div class="lbl">Aplikime te pranuara</div></div>
  <div class="stat-card"><div class="num"><?= $openRequests ?></div><div class="lbl">Kerkesa te hapura</div></div>
</div>

<h2>Evente te fundit (10)</h2>
<table>
  <thead><tr><th>Titulli</th><th>Kategoria</th><th>Data</th><th>Vendndodhja</th><th>Statusi</th><th>Aplikime</th></tr></thead>
  <tbody>
    <?php foreach ($recentEvents as $ev): ?>
    <tr>
      <td><?= $esc($ev['titulli']) ?></td>
      <td><?= $esc($ev['kategoria'] ?? '-') ?></td>
      <td><?= $esc($ev['data']) ?></td>
      <td><?= $esc($ev['vendndodhja']) ?></td>
      <td><span class="badge badge-<?= $esc($ev['statusi'] ?? 'active') ?>"><?= $esc($ev['statusi'] ?? 'active') ?></span></td>
      <td><?= (int) $ev['total_aplikime'] ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Top 5 vullnetare (aplikime te pranuara)</h2>
<table>
  <thead><tr><th>#</th><th>Emri</th><th>Roli</th><th>Aplikime te pranuara</th></tr></thead>
  <tbody>
    <?php foreach ($topUsers as $i => $u): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= $esc($u['emri']) ?></td>
      <td><?= $esc($u['roli']) ?></td>
      <td><?= (int) $u['app_count'] ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<p class="ft">Tirana Solidare &mdash; Raport i gjeneruar automatikisht &mdash; <?= $esc($generatedAt) ?></p>
</body>
</html>
<?php
    exit;
}

// CSV EXPORTS
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $type . '_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Prevent CSV formula injection (Excel/LibreOffice execute = + - @ \t \r prefixed cells)
function csv_safe(?string $v): string
{
    if ($v === null) return '';
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

if ($type === 'users') {
    fputcsv($output, ['ID', 'Emri', 'Email', 'Roli', 'Statusi', 'Regjistruar Me']);
    $stmt = $pdo->query("SELECT id_perdoruesi, emri, email, roli, statusi_llogarise, krijuar_me FROM Perdoruesi ORDER BY krijuar_me DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['id_perdoruesi'], csv_safe($row['emri']), csv_safe($row['email']), $row['roli'], $row['statusi_llogarise'], $row['krijuar_me']]);
    }
}

if ($type === 'events') {
    fputcsv($output, ['ID', 'Titulli', 'Kategoria', 'Data', 'Vendndodhja', 'Statusi', 'Krijuar Me']);
    $stmt = $pdo->query(
        "SELECT e.id_eventi, e.titulli, k.emri AS kategoria, e.data, e.vendndodhja, e.statusi, e.krijuar_me
         FROM Eventi e
         LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
         WHERE e.is_archived = 0
         ORDER BY e.data DESC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['id_eventi'], csv_safe($row['titulli']), csv_safe($row['kategoria'] ?? '-'), $row['data'], csv_safe($row['vendndodhja']), $row['statusi'] ?? 'active', $row['krijuar_me']]);
    }
}

if ($type === 'applications') {
    fputcsv($output, ['ID', 'Vullnetari', 'Email', 'Eventi', 'Statusi', 'Aplikuar Me']);
    $stmt = $pdo->query(
        "SELECT a.id_aplikimi, p.emri, p.email, e.titulli AS eventi, a.statusi, a.aplikuar_me
         FROM Aplikimi a
         JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
         JOIN Eventi e ON e.id_eventi = a.id_eventi
         ORDER BY a.aplikuar_me DESC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['id_aplikimi'], csv_safe($row['emri']), csv_safe($row['email']), csv_safe($row['eventi']), $row['statusi'], $row['aplikuar_me']]);
    }
}

fclose($output);
exit;