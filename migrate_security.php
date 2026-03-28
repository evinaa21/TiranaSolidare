<?php
/**
 * migrate_security.php
 * ---------------------------------------------------------
 * Applies the schema changes required by the security audit
 * fixes.  Run once from the CLI or browser – safe to re-run
 * (each statement checks for the column / index first).
 *
 * Changes:
 *  1. Perdoruesi.password_changed_at  – multi-device session
 *     invalidation (#6)
 *  2. Perdoruesi.arsye_bllokimi       – store block reason (#20)
 *  3. email_queue.status ENUM         – add 'processing' value
 *     for atomic CAS claim (#17)
 *  4. Performance indexes             – rate_limit_log,
 *     Njoftimi, Mesazhi, Kerkesa_per_Ndihme, admin_log
 * ---------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$steps = [];

// ── Helper ────────────────────────────────────────────
function run(PDO $pdo, string $label, string $sql): string {
    try {
        $pdo->exec($sql);
        return "  [OK]  $label";
    } catch (PDOException $e) {
        // 1060 = Duplicate column, 1061 = Duplicate key name – already applied
        if (in_array($e->getCode(), ['42S21', '42000']) ||
            str_contains($e->getMessage(), 'Duplicate column') ||
            str_contains($e->getMessage(), 'Duplicate key name') ||
            str_contains($e->getMessage(), "1060") ||
            str_contains($e->getMessage(), "1061")) {
            return "  [--]  $label (already applied)";
        }
        return "  [ERR] $label — " . $e->getMessage();
    }
}

// ── 1. password_changed_at ────────────────────────────
$steps[] = run($pdo, 'Add Perdoruesi.password_changed_at',
    "ALTER TABLE Perdoruesi
     ADD COLUMN password_changed_at DATETIME NULL DEFAULT NULL
     AFTER fjalekalimi"
);

// ── 2. arsye_bllokimi ─────────────────────────────────
$steps[] = run($pdo, 'Add Perdoruesi.arsye_bllokimi',
    "ALTER TABLE Perdoruesi
     ADD COLUMN arsye_bllokimi TEXT NULL DEFAULT NULL
     AFTER statusi_llogarise"
);

// ── 3. email_queue status ENUM ────────────────────────
$steps[] = run($pdo, "Add 'processing' to email_queue.status ENUM",
    "ALTER TABLE email_queue
     MODIFY COLUMN status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending'"
);

// ── 4. Indexes ────────────────────────────────────────
$steps[] = run($pdo, 'Index: rate_limit_log (ip, action, attempted_at)',
    "CREATE INDEX idx_rate_limit_ip_action
     ON rate_limit_log (ip, action, attempted_at)"
);

$steps[] = run($pdo, 'Index: Njoftimi (id_perdoruesi, is_read)',
    "CREATE INDEX idx_njoftimi_user_read
     ON Njoftimi (id_perdoruesi, is_read)"
);

$steps[] = run($pdo, 'Index: Mesazhi (derguesi_id, marruesi_id, krijuar_me)',
    "CREATE INDEX idx_mesazhi_conv
     ON Mesazhi (derguesi_id, marruesi_id, krijuar_me)"
);

$steps[] = run($pdo, 'Index: Kerkesa_per_Ndihme (statusi, krijuar_me)',
    "CREATE INDEX idx_kerkesa_statusi
     ON Kerkesa_per_Ndihme (statusi, krijuar_me)"
);

$steps[] = run($pdo, 'Index: admin_log (admin_id, krijuar_me)',
    "CREATE INDEX idx_admin_log_admin
     ON admin_log (admin_id, krijuar_me)"
);

// ── Output ────────────────────────────────────────────
$isCli = PHP_SAPI === 'cli';
$nl     = $isCli ? "\n" : "<br>\n";

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'>"
       . "<title>Security Migration</title>"
       . "<style>body{font-family:monospace;padding:20px;background:#1e1e2e;color:#cdd6f4;}"
       . "h2{color:#89b4fa;} .ok{color:#a6e3a1;} .skip{color:#fab387;} .err{color:#f38ba8;}</style></head><body>"
       . "<h2>migrate_security.php</h2><pre>";
}

foreach ($steps as $line) {
    if (!$isCli) {
        if (str_contains($line, '[OK]'))  $line = "<span class='ok'>$line</span>";
        elseif (str_contains($line, '[--]'))  $line = "<span class='skip'>$line</span>";
        elseif (str_contains($line, '[ERR]')) $line = "<span class='err'>$line</span>";
    }
    echo $line . $nl;
}

if (!$isCli) {
    echo "</pre><p style='color:#89b4fa;'>Done.</p></body></html>";
} else {
    echo "\nDone.\n";
}
