<?php
/**
 * test_e2e_superadmin.php
 * Quick smoke test to verify super_admin role works end-to-end.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/status_labels.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $condition): void {
    global $pass, $fail;
    if ($condition) {
        echo "  ✓ $label\n";
        $pass++;
    } else {
        echo "  ✗ FAIL: $label\n";
        $fail++;
    }
}

echo "=== Super Admin E2E Smoke Test ===\n\n";

// ── 1. Database state ──
echo "1. Database state:\n";
$user2 = $pdo->query("SELECT roli, statusi_llogarise FROM Perdoruesi WHERE id_perdoruesi = 2")->fetch();
check('User #2 is super_admin', $user2['roli'] === 'super_admin');
check('User #2 is active', $user2['statusi_llogarise'] === 'active');

$user12 = $pdo->query("SELECT roli FROM Perdoruesi WHERE id_perdoruesi = 12")->fetch();
check('User #12 is admin', $user12['roli'] === 'admin');

$user10 = $pdo->query("SELECT statusi_llogarise FROM Perdoruesi WHERE id_perdoruesi = 10")->fetch();
check('User #10 is blocked', $user10['statusi_llogarise'] === 'blocked');

$volunteers = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE roli = 'volunteer'")->fetchColumn();
check('Volunteers count > 0', $volunteers > 0);

// ── 2. Enum values are English ──
echo "\n2. DB enum consistency:\n";
$roles = $pdo->query("SELECT DISTINCT roli FROM Perdoruesi")->fetchAll(PDO::FETCH_COLUMN);
check('All roles are English', empty(array_diff($roles, ['admin', 'volunteer', 'super_admin'])));

$appStatuses = $pdo->query("SELECT DISTINCT statusi FROM Aplikimi WHERE statusi != ''")->fetchAll(PDO::FETCH_COLUMN);
check('All app statuses English', empty(array_diff($appStatuses, ['pending', 'approved', 'rejected', 'present', 'absent'])));

$tipis = $pdo->query("SELECT DISTINCT tipi FROM Kerkesa_per_Ndihme")->fetchAll(PDO::FETCH_COLUMN);
check('Help request types English', empty(array_diff($tipis, ['request', 'offer'])));

$helpStatuses = $pdo->query("SELECT DISTINCT statusi FROM Kerkesa_per_Ndihme")->fetchAll(PDO::FETCH_COLUMN);
check('Help request statuses English', empty(array_diff($helpStatuses, ['open', 'closed'])));

$accStatuses = $pdo->query("SELECT DISTINCT statusi_llogarise FROM Perdoruesi")->fetchAll(PDO::FETCH_COLUMN);
check('Account statuses English', empty(array_diff($accStatuses, ['active', 'blocked', 'deactivated'])));

// ── 3. PHP function checks ──
echo "\n3. PHP function tests:\n";

// is_admin()
$_SESSION['roli'] = 'admin';
check('is_admin() true for admin', is_admin());

$_SESSION['roli'] = 'super_admin';
check('is_admin() true for super_admin', is_admin());

$_SESSION['roli'] = 'volunteer';
check('is_admin() false for volunteer', !is_admin());

// is_super_admin()
$_SESSION['roli'] = 'super_admin';
check('is_super_admin() true for super_admin', is_super_admin());

$_SESSION['roli'] = 'admin';
check('is_super_admin() false for admin', !is_super_admin());

// status_label()
check('status_label(super_admin) = Super Admin', status_label('super_admin') === 'Super Admin');
check('status_label(admin) = Admin', status_label('admin') === 'Admin');
check('status_label(volunteer) = Vullnetar', status_label('volunteer') === 'Vullnetar');

// ts_normalize_value()
check('normalize super_admin = super_admin', ts_normalize_value('super_admin') === 'super_admin');
check('normalize Admin = admin', ts_normalize_value('Admin') === 'admin');

// ── 4. Data integrity ──
echo "\n4. Data integrity:\n";
$offers = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE tipi = 'offer'")->fetchColumn();
$requests = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE tipi = 'request'")->fetchColumn();
check("Offers count = 4 (got $offers)", $offers === 4);
check("Requests count = 6 (got $requests)", $requests === 6);

$appCount = (int) $pdo->query("SELECT COUNT(*) FROM Aplikimi WHERE statusi != ''")->fetchColumn();
check("Applications with valid status = 19 (got $appCount)", $appCount === 19);

$emptyApps = (int) $pdo->query("SELECT COUNT(*) FROM Aplikimi WHERE statusi = ''")->fetchColumn();
check("No empty application statuses (got $emptyApps)", $emptyApps === 0);

// ── Summary ──
echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
