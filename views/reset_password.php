<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');

$errorKey = $_GET['error'] ?? '';
$successKey = $_GET['success'] ?? '';

$errorMessages = [
    'empty_fields' => 'Plotësoni të gjitha fushat.',
    'invalid_email' => 'Email-i është i pavlefshëm.',
    'invalid_token' => 'Tokeni i rivendosjes është i pavlefshëm ose i skaduar.',
    'password_mismatch' => 'Fjalëkalimet nuk përputhen.',
    'password_weak' => 'Fjalëkalimi duhet të jetë 8+ karaktere, me shkronja të mëdha, të vogla, numra dhe simbol.',
    'sql_error' => 'Ndodhi një gabim. Provoni përsëri.',
    'csrf_expired' => 'Sesioni ka skaduar. Provo përsëri.',
];

$successMessages = [
    'password_updated' => 'Fjalëkalimi u rivendos me sukses. Tani mund të kyçeni.',
];

if ($token === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errorKey = 'invalid_token';
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Krijo fjalëkalim të ri — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/auth.css">
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>
<main>
  <section class="auth-shell">
    <div class="auth-blob auth-blob--green"></div>
    <div class="auth-blob auth-blob--warm"></div>
    <div class="auth-card">
      <div class="auth-card__header">
        <span class="auth-pill">Rivendos Fjalëkalimin</span>
        <h1 class="auth-title">Vendos fjalëkalim të ri</h1>
        <p class="auth-subtitle">Fut fjalëkalimin e ri për llogarinë tënde.</p>
        <?php if ($errorKey && isset($errorMessages[$errorKey])): ?>
          <div class="auth-alert auth-alert--error"><span><?= htmlspecialchars($errorMessages[$errorKey]) ?></span></div>
        <?php endif; ?>
        <?php if ($successKey && isset($successMessages[$successKey])): ?>
          <div class="auth-alert auth-alert--success"><span><?= htmlspecialchars($successMessages[$successKey]) ?></span></div>
        <?php endif; ?>
      </div>
      <form class="auth-form" method="POST" action="/TiranaSolidare/src/actions/reset_password_action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <div class="auth-field">
          <label for="password">Fjalëkalimi i ri</label>
          <input class="auth-input" type="password" id="password" name="password" required>
        </div>
        <div class="auth-field">
          <label for="confirm_password">Konfirmo fjalëkalimin</label>
          <input class="auth-input" type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn_primary auth-submit">Rivendos fjalëkalimin</button>
        <p class="auth-meta"><a href="/TiranaSolidare/views/login.php">Kthehu te hyrja</a></p>
      </form>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>
