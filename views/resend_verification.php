<?php
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errorKey   = $_GET['error']   ?? '';
$successKey = $_GET['success'] ?? '';

$errorMessages = [
    'invalid_email' => 'Vendosni një adresë email të vlefshme.',
    'rate_limited'  => 'Shumë tentativa. Provoni përsëri pas një ore.',
    'csrf_expired'  => 'Sesioni ka skaduar. Provoni përsëri.',
];
$successMessages = [
    'email_sent' => 'Nëse ky email ekziston dhe nuk është verifikuar ende, do të marrësh një link konfirmimi të ri.',
];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ridërgo Verifikimin — Tirana Solidare</title>
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
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="20" height="16" x="2" y="4" rx="2"/>
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
          </svg>
          Ridërgo Verifikimin
        </span>
        <h1 class="auth-title">Nuk morët email-in e konfirmimit?</h1>
        <p class="auth-subtitle">Vendos adresën tuaj dhe ne do të dërgojmë një link të ri verifikimi (skadon pas 24 orësh).</p>

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

      <form class="auth-form" method="POST" action="/TiranaSolidare/src/actions/resend_verification_action.php">
        <?= csrf_field() ?>
        <div class="auth-field">
          <label for="email">Email</label>
          <input class="auth-input" type="email" id="email" name="email" required placeholder="emri@shembull.com">
        </div>
        <button type="submit" class="btn_primary auth-submit">Ridërgo link-un</button>
        <p class="auth-meta"><a href="/TiranaSolidare/views/login.php">Kthehu te hyrja</a></p>
      </form>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js?v=20260401a"></script>
</body>
</html>
