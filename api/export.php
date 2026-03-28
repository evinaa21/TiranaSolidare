<?php
/**
 * api/export.php
 * ---------------------------------------------------
 * Data Export API for Admins (CSV format)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

require_method('GET');
$admin = require_admin();

$type = $_GET['type'] ?? '';

if (!in_array($type, ['users', 'events', 'applications'], true)) {
    json_error('Lloji i eksportit i pavlefshëm.', 400);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $type . '_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Prevent CSV formula injection (Excel/LibreOffice execute = + - @ \t \r prefixed cells)
function csv_safe(?string $v): string {
    if ($v === null) return '';
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

if ($type === 'users') {
    fputcsv($output, ['ID', 'Emri', 'Email', 'Roli', 'Statusi', 'Regjistruar Më']);
    $stmt = $pdo->query("SELECT id_perdoruesi, emri, email, roli, statusi_llogarise, krijuar_me FROM Perdoruesi ORDER BY krijuar_me DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['id_perdoruesi'], csv_safe($row['emri']), csv_safe($row['email']), $row['roli'], $row['statusi_llogarise'], $row['krijuar_me']]);
    }
}

if ($type === 'events') {
    fputcsv($output, ['ID', 'Titulli', 'Kategoria', 'Data', 'Vendndodhja', 'Statusi', 'Krijuar Më']);
    $stmt = $pdo->query(
        "SELECT e.id_eventi, e.titulli, k.emri AS kategoria, e.data, e.vendndodhja, e.statusi, e.krijuar_me
         FROM Eventi e
         LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
         WHERE e.is_archived = 0
         ORDER BY e.data DESC"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['id_eventi'], csv_safe($row['titulli']), csv_safe($row['kategoria'] ?? '—'), $row['data'], csv_safe($row['vendndodhja']), $row['statusi'] ?? 'active', $row['krijuar_me']]);
    }
}

if ($type === 'applications') {
    fputcsv($output, ['ID', 'Vullnetari', 'Email', 'Eventi', 'Statusi', 'Aplikuar Më']);
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
