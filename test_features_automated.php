<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/env.php';

echo "1. Checking 'flags' column in Kerkesa_per_Ndihme...\n";
try {
    $stmt = $pdo->query("DESCRIBE Kerkesa_per_Ndihme");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('flags', $columns)) {
        echo "[OK] 'flags' column exists.\n";
    } else {
        echo "[FAIL] 'flags' column is MISSING! Adding it now...\n";
        $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD COLUMN flags INT DEFAULT 0");
        echo "     -> Added 'flags' column.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

echo "\n2. Checking api/export.php...\n";
if (file_exists(__DIR__ . '/api/export.php')) {
    echo "[OK] api/export.php exists.\n";
} else {
    echo "[FAIL] api/export.php is MISSING!\n";
}

echo "\n3. Creating a dummy test user and request to test flagging...\n";
$pdo->exec("INSERT IGNORE INTO Perdoruesi (id_perdoruesi, emri, email, fjalekalimi, roli) VALUES (9999, 'Test User', 'testuser9999@test.com', 'test', 'volunteer')");
$pdo->exec("INSERT IGNORE INTO Kategoria (id_kategoria, emri) VALUES (99, 'Test Cat')");

$stmt = $pdo->query("SELECT id_kerkese_ndihme FROM Kerkesa_per_Ndihme WHERE titulli='Test Report Flag'");
$reqId = $stmt->fetchColumn();
if (!$reqId) {
    $pdo->exec("INSERT INTO Kerkesa_per_Ndihme (titulli, pershkrimi, tipi, statusi, id_perdoruesi, id_kategoria, flags) VALUES ('Test Report Flag', 'Desc', 'request', 'open', 9999, 99, 0)");
    $reqId = $pdo->lastInsertId();
}

echo "   -> Test Help Request ID: $reqId\n";

$oldFlags = $pdo->query("SELECT flags FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme=$reqId")->fetchColumn();
echo "   -> Current flags: $oldFlags\n";

$pdo->exec("UPDATE Kerkesa_per_Ndihme SET flags = COALESCE(flags, 0) + 1 WHERE id_kerkese_ndihme = $reqId");
$newFlags = $pdo->query("SELECT flags FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme=$reqId")->fetchColumn();
if ($newFlags == $oldFlags + 1) {
    echo "[OK] Flag increment works.\n";
} else {
    echo "[FAIL] Flag increment failed.\n";
}

$pdo->exec("UPDATE Kerkesa_per_Ndihme SET flags = 0 WHERE id_kerkese_ndihme = $reqId");
$clearedFlags = $pdo->query("SELECT flags FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme=$reqId")->fetchColumn();
if ($clearedFlags == 0) {
    echo "[OK] Flag clear works.\n";
} else {
    echo "[FAIL] Flag clear failed.\n";
}

// Cleanup
$pdo->exec("DELETE FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme=$reqId");

echo "\n4. Checking Aplikimi table for status updates...\n";
// Ensure 'statusi' enum supports 'present'/'absent' if needed, or if it's open text.
try {
    // some enum check or just simple test
    $pdo->exec("INSERT IGNORE INTO Eventi (id_eventi, titulli, data, vendndodhja, statusi, id_perdoruesi, id_kategoria) VALUES (9999, 'Test Event', '2026-05-01 10:00:00', 'Tirane', 'upcoming', 9999, 99)");
    $pdo->exec("INSERT IGNORE INTO Aplikimi (id_aplikimi, id_perdoruesi, id_eventi, statusi) VALUES (9999, 9999, 9999, 'approved')");
    
    $pdo->exec("UPDATE Aplikimi SET statusi = 'present' WHERE id_aplikimi = 9999");
    $appStatus = $pdo->query("SELECT statusi FROM Aplikimi WHERE id_aplikimi = 9999")->fetchColumn();
    
    if ($appStatus === 'present') {
         echo "[OK] 'present' enum status is valid.\n";
    } else {
         echo "[FAIL] status is not 'present', maybe enum issue? Got: $appStatus\n";
         echo "     -> Fixing Aplikimi statusi ENUM...\n";
         $pdo->exec("ALTER TABLE Aplikimi MODIFY statusi ENUM('pending', 'approved', 'rejected', 'present', 'absent') DEFAULT 'pending'");
         $pdo->exec("UPDATE Aplikimi SET statusi = 'present' WHERE id_aplikimi = 9999");
         $appStatus2 = $pdo->query("SELECT statusi FROM Aplikimi WHERE id_aplikimi = 9999")->fetchColumn();
         echo "     -> Retry got: $appStatus2\n";
    }
    
    // cleanup
    $pdo->exec("DELETE FROM Aplikimi WHERE id_aplikimi = 9999");
    $pdo->exec("DELETE FROM Eventi WHERE id_eventi = 9999");
    $pdo->exec("DELETE FROM Perdoruesi WHERE id_perdoruesi = 9999");
    $pdo->exec("DELETE FROM Kategoria WHERE id_kategoria = 99");
} catch (Exception $e) {
    echo "[FAIL] Aplikimi table issue: " . $e->getMessage() . "\n";
    // Check if it's a data truncation enum error
    if (strpos($e->getMessage(), 'Data truncated for column') !== false || strpos($e->getMessage(), 'Data truncated') !== false || strpos($e->getMessage(), 'statusi') !== false) {
        $pdo->exec("ALTER TABLE Aplikimi MODIFY statusi ENUM('pending', 'approved', 'rejected', 'present', 'absent') DEFAULT 'pending'");
        echo "     -> Auto-fixed Aplikimi statusi ENUM to include present/absent.\n";
    }
}
echo "\nTesting completed.\n";
