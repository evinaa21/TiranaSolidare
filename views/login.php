<?php

session_start();

$errorKey   = $_GET['error']   ?? '';
$successKey = $_GET['success'] ?? '';

$errorMessages = [
  'empty_fields'      => 'Ju lutem plotësoni të gjitha fushat.',
  'wrong_credentials' => 'Email ose fjalëkalim i pasaktë.',
];

$successMessages = [
  'registered' => 'Llogaria u krijua me sukses. Tani mund të kyçeni.',
];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kyçu — Tirana Solidare</title>
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
        <span class="auth-pill">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
          Kyçu
        </span>
        <h1 class="auth-title">Mirë se erdhe përsëri</h1>
        <p class="auth-subtitle">Hyr në platformë për të aplikuar për evente, për të kontaktuar kërkesat për ndihmë dhe për të menaxhuar profilin tënd.</p>

        <?php if ($errorKey && isset($errorMessages[$errorKey])): ?>
          <div class="auth-alert auth-alert--error">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            <span><?= htmlspecialchars($errorMessages[$errorKey]) ?></span>
          </div>
        <?php endif; ?>

        <?php if ($successKey && isset($successMessages[$successKey])): ?>
          <div class="auth-alert auth-alert--success">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
            <span><?= htmlspecialchars($successMessages[$successKey]) ?></span>
          </div>
        <?php endif; ?>
      </div>

      <form class="auth-form" action="/TiranaSolidare/src/actions/login_action.php" method="POST">
        <div class="auth-field">
          <label for="email">Email</label>
          <input class="auth-input" type="email" id="email" name="email" placeholder="emri@shembull.com" required>
        </div>
        <div class="auth-field">
          <label for="password">Fjalëkalimi</label>
          <input class="auth-input" type="password" id="password" name="password" placeholder="********" required>
        </div>
        <button type="submit" class="btn_primary auth-submit">Hyr</button>
        <p class="auth-meta">Nuk keni llogari? <a href="/TiranaSolidare/views/register.php">Krijo llogari</a></p>
      </form>

      <div class="auth-sidecard">
        <strong>Pse të kyçesh?</strong>
        <ul>
          <li>Aplikoni menjëherë për evente dhe njoftime.</li>
          <li>Kontaktoni postuesit e kërkesave për ndihmë.</li>
          <li>Ruani historikun dhe preferencat tuaja.</li>
        </ul>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>



