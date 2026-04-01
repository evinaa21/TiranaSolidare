<?php exit; // debug file — remove when done

require_once __DIR__ . '/config/db.php';
$rows = $pdo->query("SELECT id_kerkese_ndihme, tipi, statusi, latitude, longitude FROM Kerkesa_per_Ndihme LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['id_kerkese_ndihme'].'|'.$r['tipi'].'|'.$r['statusi'].'|lat:'.$r['latitude'].'|lng:'.$r['longitude']."\n";
}
foreach ($rows as $r) { echo "User {$r['marruesi_id']}: {$r['cnt']} unread\n"; }

echo "\n=== HELP REQUEST COUNTS ===\n";
$r = $pdo->query("SELECT statusi, tipi, COUNT(*) as cnt FROM Kerkesa_per_Ndihme GROUP BY statusi, tipi")->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $row) { echo $row['statusi'].'/'.$row['tipi'].': '.$row['cnt']."\n"; }

echo "\n=== NOTIFICATIONS UNREAD ===\n";
$rows2 = $pdo->query("SELECT id_perdoruesi, COUNT(*) as cnt FROM Njoftimi WHERE is_read=0 GROUP BY id_perdoruesi")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows2 as $r2) { echo "User {$r2['id_perdoruesi']}: {$r2['cnt']} unread notifs\n"; }
