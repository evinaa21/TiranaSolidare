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
    $pendingApps  = (int) $pdo->query("SELECT COUNT(*) FROM Aplikimi WHERE statusi = 'pending'")->fetchColumn();
    $openRequests = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'open'")->fetchColumn();
    $approvalRate = $totalApps > 0 ? round($approvedApps / $totalApps * 100) : 0;

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

    $categoryStats = $pdo->query(
        "SELECT k.emri AS kategoria, COUNT(DISTINCT e.id_eventi) AS total_evente, COUNT(a.id_aplikimi) AS total_aplikime
         FROM Kategoria k
         LEFT JOIN Eventi e ON e.id_kategoria = k.id_kategoria AND e.is_archived = 0
         LEFT JOIN Aplikimi a ON a.id_eventi = e.id_eventi
         GROUP BY k.id_kategoria
         ORDER BY total_aplikime DESC
         LIMIT 6"
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
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Raport Aktiviteti — Tirana Solidare</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --pri:#00715D; --pri-dk:#004d40; --pri-lt:#e8faf6;
  --acc:#E17254; --tx:#0f172a; --txm:#64748b;
  --bdr:#e2e8f0; --bg:#f4f7fa; --card:#ffffff;
  --grn:#10b981; --ylw:#f59e0b; --red:#ef4444; --blu:#3b82f6;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
body{font-family:'Inter','Segoe UI',sans-serif;background:var(--bg);color:var(--tx);font-size:14px;line-height:1.6;}
.page{max-width:980px;margin:0 auto;padding-bottom:60px;}

/* ── Header ── */
.rh{background:linear-gradient(135deg,var(--pri) 0%,var(--pri-dk) 100%);color:#fff;padding:44px 52px;position:relative;overflow:hidden;}
.rh::before{content:'';position:absolute;top:-80px;right:-80px;width:340px;height:340px;background:rgba(255,255,255,0.06);border-radius:50%;}
.rh::after{content:'';position:absolute;bottom:-90px;right:140px;width:260px;height:260px;background:rgba(255,255,255,0.04);border-radius:50%;}
.rh__inner{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:24px;}
.rh__logo{display:flex;align-items:center;gap:16px;}
.rh__ico{width:56px;height:56px;background:rgba(255,255,255,0.18);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;}
.rh__brand{font-size:1.55rem;font-weight:800;letter-spacing:-.03em;}
.rh__sub{font-size:0.8rem;opacity:.7;margin-top:3px;}
.rh__right{text-align:right;}
.rh__type{font-size:0.78rem;font-weight:700;opacity:.8;text-transform:uppercase;letter-spacing:.1em;}
.rh__date{font-size:0.76rem;opacity:.6;margin-top:4px;}
.rh__btn{display:inline-flex;align-items:center;gap:8px;margin-top:18px;background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.35);color:#fff;padding:9px 22px;border-radius:9px;cursor:pointer;font-size:0.85rem;font-weight:600;font-family:inherit;transition:background .15s;text-decoration:none;}
.rh__btn:hover{background:rgba(255,255,255,0.28);}

/* ── Body ── */
.rb{padding:36px 52px;}

/* ── Section head ── */
.sh{display:flex;align-items:center;gap:12px;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--txm);margin-bottom:18px;margin-top:32px;}
.sh:first-child{margin-top:0;}
.sh::after{content:'';flex:1;height:1px;background:var(--bdr);}

/* ── KPI row ── */
.kpi-row{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:28px;}
.kpi{background:var(--card);border:1px solid var(--bdr);border-radius:16px;padding:20px 14px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
.kpi__ico{width:40px;height:40px;border-radius:10px;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;font-size:20px;}
.kpi__val{font-size:2rem;font-weight:800;line-height:1;font-variant-numeric:tabular-nums;}
.kpi__lbl{font-size:0.65rem;font-weight:700;color:var(--txm);text-transform:uppercase;letter-spacing:.05em;margin-top:6px;}
.kpi__sub{font-size:0.72rem;color:var(--txm);margin-top:4px;}

/* ── Approval bar ── */
.appr{background:var(--card);border:1px solid var(--bdr);border-radius:16px;padding:22px 28px;margin-bottom:28px;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
.appr__lbl{font-size:0.78rem;font-weight:600;color:var(--txm);margin-bottom:12px;}
.appr__track{height:12px;border-radius:999px;background:#f1f5f9;overflow:hidden;}
.appr__fill{height:100%;background:linear-gradient(90deg,var(--grn),#059669);border-radius:999px;}
.appr__legend{display:flex;gap:20px;margin-top:12px;flex-wrap:wrap;}
.appr__item{display:flex;align-items:center;gap:6px;font-size:0.78rem;color:var(--txm);}
.appr__dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}

/* ── Table ── */
.tw{background:var(--card);border:1px solid var(--bdr);border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:28px;}
.rt{width:100%;border-collapse:collapse;font-size:0.83rem;}
.rt thead{background:linear-gradient(135deg,#f8fafc,#f1f5f9);}
.rt th{padding:12px 18px;text-align:left;font-size:0.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--txm);border-bottom:1px solid var(--bdr);}
.rt td{padding:12px 18px;border-bottom:1px solid var(--bdr);vertical-align:middle;}
.rt tbody tr:last-child td{border-bottom:none;}
.rt tbody tr:hover{background:#f8f9fc;}
.rt tbody tr:nth-child(even){background:#fafbfd;}

/* ── Badges ── */
.bdg{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:0.69rem;font-weight:700;letter-spacing:.02em;white-space:nowrap;}
.bg-g{background:#dcfce7;color:#14532d;}
.bg-y{background:#fef3c7;color:#78350f;}
.bg-b{background:#dbeafe;color:#1e3a8a;}
.bg-r{background:#fee2e2;color:#7f1d1d;}
.bg-n{background:#f3f4f6;color:#4b5563;}

/* ── Category bars ── */
.cb{height:6px;background:#f1f5f9;border-radius:999px;}
.cb__fill{height:100%;background:linear-gradient(90deg,var(--pri),#26a898);border-radius:999px;}

/* ── Footer ── */
.rf{margin:0 52px;padding:20px 0 0;border-top:1px solid var(--bdr);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:0.72rem;color:var(--txm);}
.rf strong{color:var(--pri);}

/* ── Print ── */
@media print{
  body{background:#fff!important;}
  .no-print{display:none!important;}
  .rh{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  .page{max-width:100%;}
  .rb{padding:20px;}
  tr{page-break-inside:avoid;}
  @page{size:A4 portrait;margin:15mm;}
}
@media(max-width:720px){
  .rh,.rb{padding:24px;}
  .kpi-row{grid-template-columns:repeat(2,1fr);}
  .rf{margin:0 24px;}
}
</style>
</head>
<body>
<div class="page">

<header class="rh">
  <div class="rh__inner">
    <div class="rh__logo">
      <div class="rh__ico">🤝</div>
      <div>
        <div class="rh__brand">Tirana Solidare</div>
        <div class="rh__sub">Bashkia Tiranës &mdash; Platforma e Vullnetarizmit</div>
      </div>
    </div>
    <div class="rh__right">
      <div class="rh__type">Raport Aktiviteti</div>
      <div class="rh__date">Gjeneruar: <?= $esc($generatedAt) ?></div>
      <button class="rh__btn no-print" onclick="window.print()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
        Printo / Ruaj PDF
      </button>
    </div>
  </div>
</header>

<div class="rb">

  <p class="sh">Statistikat Kryesore</p>
  <div class="kpi-row">
    <div class="kpi"><div class="kpi__ico" style="background:#e8faf6;">👥</div><div class="kpi__val" style="color:var(--pri);"><?= $totalUsers ?></div><div class="kpi__lbl">Vullnetarë Aktivë</div></div>
    <div class="kpi"><div class="kpi__ico" style="background:#fef3c7;">📅</div><div class="kpi__val" style="color:#b45309;"><?= $totalEvents ?></div><div class="kpi__lbl">Evente Aktive</div></div>
    <div class="kpi"><div class="kpi__ico" style="background:#dbeafe;">📋</div><div class="kpi__val" style="color:var(--blu);"><?= $totalApps ?></div><div class="kpi__lbl">Aplikime Totale</div></div>
    <div class="kpi"><div class="kpi__ico" style="background:#dcfce7;">✅</div><div class="kpi__val" style="color:var(--grn);"><?= $approvedApps ?></div><div class="kpi__lbl">Pranuar</div><div class="kpi__sub"><?= $approvalRate ?>% normë</div></div>
    <div class="kpi"><div class="kpi__ico" style="background:#fce7f3;">💬</div><div class="kpi__val" style="color:#db2777;"><?= $openRequests ?></div><div class="kpi__lbl">Kërkesa Hapura</div></div>
  </div>

  <div class="appr">
    <div class="appr__lbl">Norma e Pranimit të Aplikimeve &mdash; <strong style="color:var(--grn)"><?= $approvalRate ?>%</strong> e aplikimeve janë pranuar</div>
    <div class="appr__track"><div class="appr__fill" style="width:<?= $approvalRate ?>%;"></div></div>
    <div class="appr__legend">
      <div class="appr__item"><div class="appr__dot" style="background:var(--grn)"></div> Pranuar: <strong><?= $approvedApps ?></strong></div>
      <div class="appr__item"><div class="appr__dot" style="background:var(--ylw)"></div> Në pritje: <strong><?= $pendingApps ?></strong></div>
      <div class="appr__item"><div class="appr__dot" style="background:var(--red)"></div> Refuzuar: <strong><?= $totalApps - $approvedApps - $pendingApps ?></strong></div>
    </div>
  </div>

  <?php if (!empty($categoryStats)): ?>
  <p class="sh">Aktiviteti sipas Kategorisë</p>
  <div class="tw">
    <table class="rt">
      <thead><tr><th>Kategoria</th><th style="text-align:center;">Evente</th><th>Aplikime</th></tr></thead>
      <tbody>
        <?php
        $maxApps = max(array_column($categoryStats, 'total_aplikime') ?: [1]);
        foreach ($categoryStats as $cat):
            $bw = $maxApps > 0 ? round($cat['total_aplikime'] / $maxApps * 100) : 0;
        ?>
        <tr>
          <td><strong><?= $esc($cat['kategoria']) ?></strong></td>
          <td style="text-align:center;font-variant-numeric:tabular-nums;"><?= (int)$cat['total_evente'] ?></td>
          <td style="min-width:180px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="cb" style="flex:1;"><div class="cb__fill" style="width:<?= $bw ?>%;"></div></div>
              <span style="font-weight:700;font-variant-numeric:tabular-nums;min-width:28px;text-align:right;"><?= (int)$cat['total_aplikime'] ?></span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <p class="sh">Evente të Fundit (10)</p>
  <div class="tw">
    <table class="rt">
      <thead><tr><th>Titulli</th><th>Kategoria</th><th>Data</th><th>Vendndodhja</th><th>Statusi</th><th style="text-align:center;">Aplikime</th></tr></thead>
      <tbody>
        <?php foreach ($recentEvents as $ev):
            $st = $ev['statusi'] ?? 'active';
            $sc = in_array($st,['active','open']) ? 'bg-g' : ($st==='completed'?'bg-b':($st==='cancelled'?'bg-r':($st==='pending'?'bg-y':'bg-n')));
            $sls = ['active'=>'Aktiv','completed'=>'Përfunduar','cancelled'=>'Anuluar','pending'=>'Në pritje','open'=>'Hapur'];
            $sl = $sls[$st] ?? ucfirst($st);
        ?>
        <tr>
          <td><strong><?= $esc($ev['titulli']) ?></strong></td>
          <td><?= $ev['kategoria'] ? '<span class="bdg bg-n">'.$esc($ev['kategoria']).'</span>' : '<span style="color:var(--txm)">—</span>' ?></td>
          <td style="white-space:nowrap;color:var(--txm);font-size:0.8rem;"><?= $esc(date('d.m.Y', strtotime($ev['data']))) ?></td>
          <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--txm);font-size:0.8rem;" title="<?= $esc($ev['vendndodhja']) ?>"><?= $esc($ev['vendndodhja']) ?></td>
          <td><span class="bdg <?= $sc ?>"><?= $esc($sl) ?></span></td>
          <td style="text-align:center;font-weight:700;font-variant-numeric:tabular-nums;"><?= (int)$ev['total_aplikime'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p class="sh">Top 5 Vullnetarë (Aplikime të Pranuara)</p>
  <div class="tw">
    <table class="rt">
      <thead><tr><th style="width:56px;text-align:center;">Rank</th><th>Emri</th><th>Roli</th><th style="text-align:right;">Aplikime</th></tr></thead>
      <tbody>
        <?php foreach ($topUsers as $i => $u): ?>
        <tr>
          <td style="text-align:center;font-size:1.1rem;"><?php if($i===0):?>🥇<?php elseif($i===1):?>🥈<?php elseif($i===2):?>🥉<?php else:?><span style="font-size:0.77rem;color:var(--txm);font-weight:600;">#<?=$i+1?></span><?php endif;?></td>
          <td><strong><?= $esc($u['emri']) ?></strong></td>
          <td><span class="bdg bg-g" style="font-size:0.68rem;"><?= $esc($u['roli']) ?></span></td>
          <td style="text-align:right;font-size:1.15rem;font-weight:800;color:var(--pri);font-variant-numeric:tabular-nums;"><?= (int)$u['app_count'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<footer class="rf">
  <span><strong>Tirana Solidare</strong> &mdash; Bashkia Tiranës</span>
  <span>Raport gjeneruar automatikisht &mdash; <?= $esc($generatedAt) ?></span>
</footer>

</div>
</body>
</html>
<?php
    exit;
}



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