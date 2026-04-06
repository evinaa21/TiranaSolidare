<?php

require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
    $roli = $_SESSION['roli'] ?? '';
    if (in_array($roli, ['admin', 'super_admin'])) {
        header('Location: /TiranaSolidare/views/dashboard.php');
    } else {
        header('Location: /TiranaSolidare/views/volunteer_panel.php');
    }
    exit();
}

$errorKey   = $_GET['error']   ?? '';
$successKey = $_GET['success'] ?? '';
$redirect   = $_GET['redirect'] ?? '';

$errorMessages = [
  'empty_fields'       => 'Ju lutem plotësoni të gjitha fushat.',
  'invalid_email'      => 'Formati i email-it nuk është i saktë.',
  'invalid_name'       => 'Emri duhet të ketë të paktën 2 karaktere.',
  'invalid_birthdate'  => 'Vendosni një datë lindjeje të vlefshme.',
  'password_mismatch'  => 'Fjalëkalimet nuk përputhen.',
  'password_weak'      => 'Fjalëkalimi duhet të ketë të paktën 8 karaktere, shkronjë të madhe, të vogël, numër dhe simbol.',
  'email_taken'        => 'Ky email është regjistruar tashmë.',
  'guardian_details_required' => 'Për përdoruesit nën 16 vjeç kërkohen të dhënat e prindit ose kujdestarit.',
  'invalid_guardian_name' => 'Emri i prindit ose kujdestarit duhet të ketë të paktën 2 karaktere.',
  'invalid_guardian_email' => 'Email-i i prindit ose kujdestarit nuk është i saktë.',
  'guardian_email_same_as_user' => 'Email-i i prindit ose kujdestarit duhet të jetë i ndryshëm nga email-i juaj.',
  'invalid_guardian_relation' => 'Shënoni një lidhje të vlefshme me prindin ose kujdestarin.',
  'verification_email_failed' => 'Nuk u dërgua email-i i verifikimit. Kontrolloni konfigurimin e email-it dhe provoni përsëri.',
  'rate_limited'       => 'Shumë tentativa regjistrimi. Provoni përsëri më vonë.',
  'csrf_expired'       => 'Sesioni ka skaduar. Ju lutem provoni përsëri.',
  'no_consent'         => 'Duhet të pranoni Politikën e Privatësisë për të vazhduar.',
  'sql_error'          => 'Ndodhi një gabim gjatë regjistrimit. Provoni përsëri.',
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
  <title>Regjistrohu — Tirana Solidare</title>
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
          Regjistrohu
        </span>
        <h1 class="auth-title">Bëhu pjesë e komunitetit</h1>
        <p class="auth-subtitle">Krijo llogarinë tënde për të aplikuar në evente, për të postuar kërkesa ndihme dhe për të bashkëpunuar me vullnetarët e tjerë.</p>

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
        <div class="auth-sidecard">
          <strong>Pse të regjistrohesh?</strong>
          <ul>
            <li>Publikoni ose aplikoni në nisma dhe evente komunitare.</li>
            <li>Kontaktoni shpejt me njerëzit që kërkojnë ndihmë.</li>
            <li>Ndërtimi i profilit ndihmon besueshmërinë në komunitet.</li>
            <li>Nëse je nën 16 vjeç, mjafton një konfirmim i thjeshtë me email nga prindi ose kujdestari.</li>
          </ul>
        </div>
      </div>

      <form class="auth-form" action="/TiranaSolidare/src/actions/register_action.php" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <div class="auth-field">
          <label for="emri">Emri i plotë</label>
          <input class="auth-input" type="text" id="emri" name="emri" placeholder="Emri Mbiemri" required>
        </div>
        <div class="auth-field">
          <label for="email">Email</label>
          <input class="auth-input" type="email" id="email" name="email" placeholder="emri@shembull.com" required>
        </div>
        <div class="auth-field">
          <label for="birthdate">Data e lindjes</label>
          <input class="auth-input" type="date" id="birthdate" name="birthdate" required max="<?= (new DateTime('-1 year'))->format('Y-m-d') ?>">
          <small class="auth-field-help">Nëse je nën 16 vjeç, do të kërkojmë vetëm një konfirmim me email nga prindi ose kujdestari.</small>
        </div>
        <div class="auth-guardian-card" id="guardian-card" hidden>
          <strong>Konfirmim prindëror i thjeshtë</strong>
          <p>Ne do të dërgojmë një link të vetëm miratimi te prindi ose kujdestari. Llogaria jote do të aktivizohet sapo të konfirmohen email-i yt dhe pëlqimi i tyre.</p>
        </div>
        <div id="guardian-section" hidden>
          <div class="auth-field">
            <label for="guardian_name">Emri i prindit ose kujdestarit</label>
            <input class="auth-input" type="text" id="guardian_name" name="guardian_name" placeholder="Emri Mbiemri" data-guardian-field>
          </div>
          <div class="auth-field">
            <label for="guardian_email">Email-i i prindit ose kujdestarit</label>
            <input class="auth-input" type="email" id="guardian_email" name="guardian_email" placeholder="prindi@shembull.com" data-guardian-field>
          </div>
          <div class="auth-field">
            <label for="guardian_relation">Lidhja me ty</label>
            <input class="auth-input" type="text" id="guardian_relation" name="guardian_relation" placeholder="P.sh. nënë, baba, kujdestar ligjor" data-guardian-field>
          </div>
        </div>
        <div class="auth-field">
          <label for="password">Fjalëkalimi</label>
          <input class="auth-input" type="password" id="password" name="password" placeholder="********" required autocomplete="new-password">
          <ul class="pw-rules" id="pw-rules" aria-live="polite">
            <li id="pw-len"  class="pw-rule">Të paktën 8 karaktere</li>
            <li id="pw-up"   class="pw-rule">Të paktën 1 shkronjë e madhe (A–Z)</li>
            <li id="pw-lo"   class="pw-rule">Të paktën 1 shkronjë e vogël (a–z)</li>
            <li id="pw-num"  class="pw-rule">Të paktën 1 numër (0–9)</li>
            <li id="pw-sym"  class="pw-rule">Të paktën 1 simbol (p.sh. !@#$%)</li>
          </ul>
        </div>
        <div class="auth-field">
          <label for="confirm_password">Konfirmo fjalëkalimin</label>
          <input class="auth-input" type="password" id="confirm_password" name="confirm_password" placeholder="********" required autocomplete="new-password">
          <small class="pw-match-hint" id="pw-match-hint"></small>
        </div>
        <style>
          .pw-rules { list-style: none; margin: 0.45rem 0 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem; }
          .pw-rule  { font-size: 0.78rem; color: #aaa; transition: color .2s; padding-left: 1.1rem; position: relative; }
          .pw-rule.ok { color: #16a34a; }
          .pw-rule.ok::before { content: '\2713\00a0'; position: absolute; left: 0; }
          .pw-match-hint { font-size: 0.78rem; margin-top: 0.3rem; display: block; min-height: 1rem; }
          .auth-field-help { display: block; margin-top: 0.45rem; font-size: 0.8rem; color: #5a6a64; line-height: 1.5; }
          .auth-guardian-card { margin-bottom: 0.75rem; padding: 0.95rem 1rem; border-radius: 14px; border: 1px solid #d6ebe4; background: #f5faf8; color: #24423a; }
          .auth-guardian-card strong { display: block; margin-bottom: 0.35rem; }
          .auth-guardian-card p { margin: 0; font-size: 0.9rem; line-height: 1.55; }
        </style>
        <script>
          (function () {
            var pw   = document.getElementById('password');
            var cpw  = document.getElementById('confirm_password');
            var hint = document.getElementById('pw-match-hint');
            var birthdate = document.getElementById('birthdate');
            var guardianCard = document.getElementById('guardian-card');
            var guardianSection = document.getElementById('guardian-section');
            var guardianFields = Array.prototype.slice.call(document.querySelectorAll('[data-guardian-field]'));

            function check() {
              var v = pw.value;
              toggle('pw-len',  v.length >= 8);
              toggle('pw-up',   /[A-Z]/.test(v));
              toggle('pw-lo',   /[a-z]/.test(v));
              toggle('pw-num',  /[0-9]/.test(v));
              toggle('pw-sym',  /[^A-Za-z0-9]/.test(v));
            }
            function toggle(id, ok) {
              document.getElementById(id).classList.toggle('ok', ok);
            }

            function ageRequiresGuardian(value) {
              if (!value) return false;
              var birth = new Date(value + 'T00:00:00');
              if (Number.isNaN(birth.getTime())) return false;
              var today = new Date();
              var age = today.getFullYear() - birth.getFullYear();
              var monthDiff = today.getMonth() - birth.getMonth();
              if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age -= 1;
              }
              return age < <?= TS_GUARDIAN_CONSENT_MIN_AGE ?>;
            }

            function syncGuardianSection() {
              var showGuardian = ageRequiresGuardian(birthdate.value);
              guardianCard.hidden = !showGuardian;
              guardianSection.hidden = !showGuardian;
              guardianFields.forEach(function (field) {
                field.required = showGuardian;
              });
            }

            function matchCheck() {
              if (!cpw.value) { hint.textContent = ''; hint.style.color = ''; return; }
              if (pw.value === cpw.value) {
                hint.textContent = '\u2713 Fjalëkalimet përputhen';
                hint.style.color = '#16a34a';
              } else {
                hint.textContent = '\u2717 Fjalëkalimet nuk përputhen';
                hint.style.color = '#dc2626';
              }
            }
            pw.addEventListener('input', function() { check(); matchCheck(); });
            cpw.addEventListener('input', matchCheck);
            birthdate.addEventListener('input', syncGuardianSection);
            birthdate.addEventListener('change', syncGuardianSection);
            syncGuardianSection();
          })();
        </script>
        <div class="auth-field" style="margin-top:0.5rem">
          <label class="auth-checkbox-label">
            <input type="checkbox" name="privacy_consent" value="1" required>
            <span>Pranoj <a href="/TiranaSolidare/views/privacy.php" target="_blank" rel="noopener">Politikën e Privatësisë</a> dhe jap pëlqimin për përpunimin e të dhënave të mia personale.</span>
          </label>
        </div>
        <button type="submit" class="btn_primary auth-submit">Regjistrohu</button>
        <p class="auth-meta">Keni llogari? <a href="/TiranaSolidare/views/login.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>">Hyni këtu</a></p>
      </form>

    </div>
  </section>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js?v=20260401a"></script>
</body>
</html>




