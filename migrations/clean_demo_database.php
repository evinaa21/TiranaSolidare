<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.' . PHP_EOL);
}

require_once __DIR__ . '/../config/db.php';

$dryRun = !in_array('--confirm', $argv ?? [], true);

$userWhere = <<<SQL
LOWER(COALESCE(email, '')) LIKE 'e2e.%@test.local'
OR LOWER(COALESCE(email, '')) LIKE '%@deleted.invalid'
OR LOWER(COALESCE(email, '')) LIKE 'evina.tershalla@gm.com'
OR LOWER(COALESCE(email, '')) LIKE 'etershalla23@epoka%'
OR LOWER(COALESCE(organization_name, '')) IN ('test', 'organizat')
SQL;

$orgWhere = <<<SQL
LOWER(COALESCE(contact_email, '')) LIKE 'e2e.%@test.local'
OR LOWER(COALESCE(contact_email, '')) LIKE 'etershalla23@epoka%'
OR LOWER(COALESCE(organization_name, '')) IN ('test', 'organizat')
OR (applicant_user_id IS NOT NULL AND applicant_user_id NOT IN (SELECT id_perdoruesi FROM Perdoruesi))
SQL;

$userStmt = $pdo->query(
    'SELECT id_perdoruesi AS id, emri, email, roli, statusi_llogarise, organization_name
     FROM Perdoruesi
     WHERE ' . $userWhere . '
     ORDER BY id_perdoruesi'
);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$orgStmt = $pdo->query(
    'SELECT id, applicant_user_id, organization_name, contact_name, contact_email, status
     FROM organization_applications
     WHERE ' . $orgWhere . '
     ORDER BY id'
);
$orgApplications = $orgStmt->fetchAll(PDO::FETCH_ASSOC);

echo ($dryRun ? '[DRY RUN] ' : '') . 'Cleanup targets:' . PHP_EOL;
echo 'Users: ' . count($users) . PHP_EOL;
foreach ($users as $user) {
    echo sprintf(
        "  user ID=%d  email=%s  name=%s  role=%s  status=%s  org=%s\n",
        (int) $user['id'],
        (string) $user['email'],
        (string) $user['emri'],
        (string) $user['roli'],
        (string) $user['statusi_llogarise'],
        (string) ($user['organization_name'] ?? '')
    );
}

echo 'Organization applications: ' . count($orgApplications) . PHP_EOL;
foreach ($orgApplications as $application) {
    echo sprintf(
        "  org-app ID=%d  applicant=%s  org=%s  contact=%s  email=%s  status=%s\n",
        (int) $application['id'],
        $application['applicant_user_id'] === null ? 'null' : (string) $application['applicant_user_id'],
        (string) $application['organization_name'],
        (string) $application['contact_name'],
        (string) $application['contact_email'],
        (string) $application['status']
    );
}

if ($dryRun) {
    echo PHP_EOL . 'Run with --confirm to permanently remove these records.' . PHP_EOL;
    exit(0);
}

$deletedUsers = 0;
$deletedOrgApps = 0;

try {
    $pdo->beginTransaction();

    if ($orgApplications !== []) {
        $orgDelete = $pdo->prepare('DELETE FROM organization_applications WHERE id = ?');
        foreach ($orgApplications as $application) {
            $orgDelete->execute([(int) $application['id']]);
            $deletedOrgApps++;
        }
    }

    foreach ($users as $user) {
        $userId = (int) $user['id'];

        $pdo->prepare('DELETE FROM admin_log WHERE admin_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM Njoftimi WHERE id_perdoruesi = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM Aplikimi WHERE id_perdoruesi = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM Aplikimi_Kerkese WHERE id_perdoruesi = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM help_request_flags WHERE id_perdoruesi = ?')->execute([$userId]);
        $pdo->prepare(
            'DELETE FROM Aplikimi_Kerkese WHERE id_kerkese_ndihme IN (
                SELECT id_kerkese_ndihme FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?
            )'
        )->execute([$userId]);
        $pdo->prepare('DELETE FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?')->execute([$userId]);
        $pdo->prepare('UPDATE Eventi SET id_perdoruesi = NULL WHERE id_perdoruesi = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM Mesazhi WHERE derguesi_id = ? OR marruesi_id = ?')->execute([$userId, $userId]);
        $pdo->prepare('DELETE FROM push_subscriptions WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM Raporti WHERE id_perdoruesi = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? OR blocked_id = ?')->execute([$userId, $userId]);
        $pdo->prepare('DELETE FROM organization_applications WHERE applicant_user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM Perdoruesi WHERE id_perdoruesi = ?')->execute([$userId]);

        $deletedUsers++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Cleanup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'Cleanup complete.' . PHP_EOL;
echo 'Deleted users: ' . $deletedUsers . PHP_EOL;
echo 'Deleted organization applications: ' . $deletedOrgApps . PHP_EOL;