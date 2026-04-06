<?php
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errorKey = $_GET['error'] ?? '';
$successKey = $_GET['success'] ?? '';

$errorMessages = [
    'invalid_email' => 'Vendosni një adresë email të vlefshme.',
    'rate_limited' => 'Shumë tentativa. Provoni përsëri pas një ore.',
    'csrf_expired' => 'Sesioni ka skaduar. Provoni përsëri.',
];

$successMessages = [
    'email_sent' => 'Nëse kjo llogari ka nevojë ende për pëlqim prindëror, do të dërgojmë një link të ri te email-i i prindit ose kujdestarit.',
];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ridërgo Pëlqimin Prindëror — Tirana Solidare</title>
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
            <path d="M22 12.08V12a10 10 0 1 0-5.93 9.14"/>
            <path d="M22 4 12 14.01l-3-3"/>
          </svg>
          Ridërgo Pëlqimin Prindëror
        </span>
        <h1 class="auth-title">Nuk mori email prindi ose kujdestari?</h1>
        <p class="auth-subtitle">Vendos email-in e llogarisë dhe ne do të dërgojmë një link të ri miratimi te kontakti prindëror i ruajtur në profil.</p>

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

      <form class="auth-form" method="POST" action="/TiranaSolidare/src/actions/resend_guardian_consent_action.php">
        <?= csrf_field() ?>
        <div class="auth-field">
          <label for="email">Email-i i llogarisë</label>
          <input class="auth-input" type="email" id="email" name="email" required placeholder="emri@shembull.com">
        </div>
        <button type="submit" class="btn_primary auth-submit">Ridërgo linkun</button>
        <p class="auth-meta"><a href="/TiranaSolidare/views/login.php">Kthehu te hyrja</a></p>
        <p class="auth-meta"><a href="/TiranaSolidare/views/resend_verification.php">Ridërgo verifikimin e email-it</a></p>
      </form>
    </div>
  </section>
</main>
<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js?v=20260401a"></script>
</body>
</html>