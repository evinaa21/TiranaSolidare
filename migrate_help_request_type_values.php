<?php
require_once __DIR__ . '/config/db.php';

function ts_help_request_type_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    if (!ts_help_request_type_column_exists($pdo, 'Kerkesa_per_Ndihme', 'tipi')) {
        throw new RuntimeException('Kolona tipi mungon në tabelën Kerkesa_per_Ndihme.');
    }

    $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme MODIFY COLUMN tipi VARCHAR(20) NULL DEFAULT NULL");

    $offerUpdated = $pdo->exec(
        "UPDATE Kerkesa_per_Ndihme
         SET tipi = 'offer'
         WHERE LOWER(TRIM(COALESCE(tipi, ''))) IN ('offer', 'oferte', 'ofertë')
            OR (
                TRIM(COALESCE(tipi, '')) = ''
                AND (
                    LOWER(COALESCE(titulli, '')) LIKE 'ofroj %'
                    OR LOWER(COALESCE(pershkrimi, '')) LIKE 'ofroj %'
                    OR LOWER(COALESCE(pershkrimi, '')) LIKE 'dua të ofroj%'
                    OR LOWER(COALESCE(pershkrimi, '')) LIKE 'dua te ofroj%'
                    OR LOWER(COALESCE(pershkrimi, '')) LIKE 'jam i disponueshëm të ofroj%'
                    OR LOWER(COALESCE(pershkrimi, '')) LIKE 'jam i disponueshem te ofroj%'
                    OR LOWER(COALESCE(pershkrimi, '')) LIKE 'jam e disponueshme të ofroj%'
                    OR LOWER(COALESCE(pershkrimi, '')) LIKE 'jam e disponueshme te ofroj%'
                )
            )"
    );

    $requestUpdated = $pdo->exec(
        "UPDATE Kerkesa_per_Ndihme
         SET tipi = 'request'
         WHERE LOWER(TRIM(COALESCE(tipi, ''))) IN ('request', 'kerkese', 'kërkesë')
            OR TRIM(COALESCE(tipi, '')) = ''"
    );

    $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme MODIFY COLUMN tipi ENUM('request','offer') NULL DEFAULT NULL");

    echo 'Help request type migration completed. ';
    echo 'Offers repaired: ' . (int) $offerUpdated . '. ';
    echo 'Requests repaired: ' . (int) $requestUpdated . '.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}