<?php
/**
 * seed_dev_data.php — one-time dev data fix/seed. Run from CLI.
 * Safe to re-run.
 */
declare(strict_types=1);
require __DIR__ . '/config/db.php';

$steps = [];

function exec_sql(PDO $pdo, string $label, string $sql, array $params = []): string {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return "  [OK]  $label ({$stmt->rowCount()} rows)";
    } catch (PDOException $e) {
        return "  [ERR] $label — " . $e->getMessage();
    }
}

// ── 1. Fix missing roli for non-admin users ──────────────────────
$steps[] = exec_sql($pdo,
    'Set roli=volunteer for users with empty role',
    "UPDATE Perdoruesi SET roli = 'volunteer' WHERE roli IS NULL OR roli = ''"
);

// ── 2. Fix missing statusi_llogarise ────────────────────────────
$steps[] = exec_sql($pdo,
    'Set statusi_llogarise=active for users with empty status',
    "UPDATE Perdoruesi SET statusi_llogarise = 'active' WHERE statusi_llogarise IS NULL OR statusi_llogarise = ''"
);

// ── 3. Make some test volunteers have public profiles ────────────
$steps[] = exec_sql($pdo,
    'Set profile_public=1 for first 5 volunteers',
    "UPDATE Perdoruesi SET profile_public = 1
     WHERE roli = 'volunteer'
     ORDER BY id_perdoruesi ASC LIMIT 5"
);

// ── 4. Fix tipi for help requests ───────────────────────────────
// Titles starting with "Ofroj" = offer, everything else = request
$steps[] = exec_sql($pdo,
    "Set tipi='offer' for Ofroj requests",
    "UPDATE Kerkesa_per_Ndihme SET tipi = 'offer'
     WHERE titulli LIKE 'Ofroj%' AND (tipi IS NULL OR tipi = '')"
);
$steps[] = exec_sql($pdo,
    "Set tipi='request' for remaining empty requests",
    "UPDATE Kerkesa_per_Ndihme SET tipi = 'request'
     WHERE tipi IS NULL OR tipi = ''"
);

// ── 5. Make sure admin user has correct role/status ─────────────
$steps[] = exec_sql($pdo,
    'Ensure admin has roli=admin and statusi=active',
    "UPDATE Perdoruesi SET roli = 'admin', statusi_llogarise = 'active'
     WHERE emri = 'admin' AND (roli = 'volunteer' OR statusi_llogarise != 'active')"
);

// ── 6. Seed test messages between first volunteer and admin ──────
// First check if messages already exist
$count = (int) $pdo->query('SELECT COUNT(*) FROM Mesazhi')->fetchColumn();
if ($count === 0) {
    $getAdmin = $pdo->query("SELECT id_perdoruesi FROM Perdoruesi WHERE roli = 'admin' LIMIT 1")->fetch();
    $getVols  = $pdo->query("SELECT id_perdoruesi FROM Perdoruesi WHERE roli = 'volunteer' ORDER BY id_perdoruesi LIMIT 3")->fetchAll();

    if ($getAdmin && count($getVols) >= 2) {
        $adminId = (int) $getAdmin['id_perdoruesi'];
        $vol1    = (int) $getVols[0]['id_perdoruesi'];
        $vol2    = (int) $getVols[1]['id_perdoruesi'];

        $ins = $pdo->prepare(
            "INSERT INTO Mesazhi (derguesi_id, marruesi_id, mesazhi, is_read, krijuar_me) VALUES (?, ?, ?, ?, ?)"
        );

        $msgs = [
            [$vol1,   $adminId, 'Mirëdita! Kur fillon eventin e radhës?', 1, date('Y-m-d H:i:s', strtotime('-2 days'))],
            [$adminId, $vol1,   'Pershendetje! Eventi fillon të shtunën.', 1, date('Y-m-d H:i:s', strtotime('-2 days +10 minutes'))],
            [$vol1,   $adminId, 'Faleminderit! Do të jem aty.', 1, date('Y-m-d H:i:s', strtotime('-2 days +20 minutes'))],
            [$vol2,   $adminId, 'A mund të regjistrohem si vullnetar?', 0, date('Y-m-d H:i:s', strtotime('-1 day'))],
            [$adminId, $vol2,   'Po, kliko "Apliko" në panelin e eventeve.', 0, date('Y-m-d H:i:s', strtotime('-1 day +5 minutes'))],
        ];

        foreach ($msgs as $m) {
            $ins->execute($m);
        }
        $steps[] = "  [OK]  Seeded " . count($msgs) . " test messages";
    } else {
        $steps[] = "  [--]  Skip messages seed (need >= 1 admin + 2 volunteers)";
    }
} else {
    $steps[] = "  [--]  Skip messages seed (already has $count messages)";
}

// ── Output ─────────────────────────────────────────────────────
foreach ($steps as $line) {
    echo $line . "\n";
}
echo "\nDone.\n";
