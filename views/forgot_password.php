<?php
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errorKey = $_GET['error'] ?? '';
$successKey = $_GET['success'] ?? '';

$errorMessages = [
    'empty_fields' => 'Ju lutem vendosni adresën tuaj të email-it.',
    'invalid_email' => 'Email-i duket i pavlefshëm.',
    'sql_error' => 'Ndodhi një gabim i brendshëm. Provoni përsëri.',
    'rate_limited' => 'Shumë tentativa. Provoni përsëri pas disa minutash.',
    'csrf_expired' => 'Sesioni ka skaduar. Provoni përsëri.',
];

$successMessages = [
    'email_sent' => 'Nëse ky email ekziston në sistem, u dërgua një link për rivendosjen e fjalëkalimit.',
];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rivendos Fjalëkalimin — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260401a">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css?v=20260401a">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/auth.css?v=20260401a">
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>
<main>
  <section class="auth-shell">
    <div class="auth-blob auth-blob--green"></div>
    <div class="auth-blob auth-blob--warm"></div>
    <div class="auth-card">
      <div class="auth-card__header">
        <span class="auth-pill">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
          Rivendos Fjalëkalimin
        </span>
        <h1 class="auth-title">Harrove fjalëkalimin?</h1>
        <p class="auth-subtitle">Vendos email-in tënd dhe ne do të të dërgojmë një link të sigurt për rivendosje.</p>

        <?php if ($errorKey && isset($errorMessages[$errorKey])): ?>
          <div class="auth-alert auth-alert--error">
            <span><?= htmlspecialchars($errorMessages[$errorKey]) ?></span>
          </div>
        <?php endif; ?>
        <?php if ($successKey && isset($successMessages[$successKey])): ?>
          <div class="auth-alert auth-alert--success">
            <span><?= htmlspecialchars($successMessages[$successKey]) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <form class="auth-form" method="POST" action="/TiranaSolidare/src/actions/forgot_password_action.php">
        <?= csrf_field() ?>
        <div class="auth-field">
          <label for="email">Email</label>
          <input class="auth-input" type="email" id="email" name="email" required placeholder="emri@shembull.com">
        </div>
        <button type="submit" class="btn_primary auth-submit">Dërgo link</button>
        <p class="auth-meta"><a href="/TiranaSolidare/views/login.php">Kthehu te hyrja</a></p>
      </form>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js?v=20260401a"></script>
</body>
</html>
