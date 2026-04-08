<?php
// CLI only — deny web access
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
require_once __DIR__ . '/config/db.php';

$execute = in_array('--execute', $argv, true);
$protectedTables = ['perdoruesi'];

function quote_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

$targets = [];
foreach ($tables as $row) {
    $tableName = $row[0];
    if (in_array(strtolower($tableName), $protectedTables, true)) {
        continue;
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . quote_identifier($tableName))->fetchColumn();
    $targets[] = ['table' => $tableName, 'rows' => $count];
}

echo $execute ? "EXECUTE MODE\n" : "DRY RUN\n";
echo 'Protected tables: ' . implode(', ', $protectedTables) . PHP_EOL;
echo 'Target tables:' . PHP_EOL;
foreach ($targets as $target) {
    echo '- ' . $target['table'] . ' (' . $target['rows'] . ' rows)' . PHP_EOL;
}

if (!$execute) {
    echo PHP_EOL . "Run with --execute to clear these tables.\n";
    exit(0);
}

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($targets as $target) {
        $pdo->exec('TRUNCATE TABLE ' . quote_identifier($target['table']));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo PHP_EOL . 'Database cleanup completed successfully.' . PHP_EOL;
} catch (Throwable $e) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $ignored) {
    }

    fwrite(STDERR, 'Cleanup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}