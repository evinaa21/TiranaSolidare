<?php
require __DIR__ . '/config/db.php';
echo "--- PERDORUESI ---\n";
foreach ($pdo->query('SELECT id_perdoruesi, emri, roli, statusi_llogarise, verified, profile_public FROM Perdoruesi LIMIT 8')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo implode(' | ', array_map('strval', $r)) . "\n";
}
echo "\n--- KERKESA_PER_NDIHME ---\n";
foreach ($pdo->query('SELECT id_kerkese_ndihme, titulli, tipi, statusi FROM Kerkesa_per_Ndihme LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo implode(' | ', array_map('strval', $r)) . "\n";
}
