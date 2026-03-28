<?php
require_once __DIR__ . '/config/db.php';
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . PHP_EOL;
echo "Raporti exists: " . (in_array('Raporti', $tables) ? 'YES' : 'NO') . PHP_EOL;
echo "Mesazhi exists: " . (in_array('Mesazhi', $tables) ? 'YES' : 'NO') . PHP_EOL;
