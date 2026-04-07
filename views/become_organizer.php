<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$isOrganizer = $isLoggedIn && ts_is_organizer_role_value($_SESSION['roli'] ?? '');
$isAdmin = $isLoggedIn && is_admin();
$userName = $_SESSION['emri'] ?? 'Përdorues';
$userEmail = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?= csrf_meta() ?>
  <title>Apliko si organizatë</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260401a">
  <style>
    .org-page{background:linear-gradient(180deg,#f7fbfa 0%,#ffffff 38%,#f8fafc 100%);min-height:100vh;}
    .org-shell{max-width:1120px;margin:0 auto;padding:48px 20px 72px;}
    .org-hero{display:grid;grid-template-columns:1.1fr .9fr;gap:28px;align-items:stretch;margin-bottom:28px;}
    .org-card,.org-panel{background:#fff;border:1px solid #e5ece9;border-radius:24px;box-shadow:0 16px 50px rgba(15,23,42,.06);}
    .org-card{padding:32px;position:relative;overflow:hidden;}
    .org-card h1{font-size:clamp(2rem,4vw,3.2rem);line-height:1.04;margin:14px 0 14px;color:#09352c;}
    .org-card p{color:#4a5d58;font-size:1rem;line-height:1.7;max-width:60ch;}
    .org-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;background:rgba(0,113,93,.1);color:#00715D;font-size:.82rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;}
    .org-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:24px;}
    .org-point{padding:16px;border-radius:18px;background:#f8fbfa;border:1px solid #e7f0ed;}
    .org-point strong{display:block;color:#0f2d27;margin-bottom:6px;}
    .org-point span{display:block;color:#5b6d68;font-size:.92rem;line-height:1.5;}
    .org-panel{padding:28px;}
    .org-panel h2{margin:0 0 8px;color:#0b2f28;font-size:1.35rem;}
    .org-panel p{margin:0 0 20px;color:#61736e;line-height:1.6;}
    .org-status{display:none;margin-bottom:18px;padding:16px 18px;border-radius:16px;border:1px solid #dbe7e3;background:#f8fbfa;color:#21443b;}
    .org-status strong{display:block;margin-bottom:4px;}
    .org-status--pending{display:block;background:#fff8e7;border-color:#f4d798;color:#7a5a10;}
    .org-status--approved{display:block;background:#ecfdf5;border-color:#b7efcf;color:#166534;}
    .org-status--rejected{display:block;background:#fff1f2;border-color:#fecdd3;color:#9f1239;}
    .org-form{display:grid;gap:14px;}
    .org-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .org-field label{display:block;font-size:.84rem;font-weight:700;color:#375049;margin-bottom:7px;}
    .org-field input,.org-field textarea{width:100%;border:1.5px solid #d9e5e1;border-radius:14px;padding:12px 14px;font-size:.95rem;outline:none;box-sizing:border-box;background:#fff;}
    .org-field textarea{min-height:150px;resize:vertical;line-height:1.6;}
    .org-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:6px;}
    .org-note{font-size:.85rem;color:#6b7d78;line-height:1.6;}
    .org-meta{display:grid;gap:12px;}
    .org-meta-card{padding:18px;border-radius:18px;background:linear-gradient(145deg,#f8fbfa,#ffffff);border:1px solid #e5eeeb;}
    .org-meta-card strong{display:block;margin-bottom:8px;color:#153831;}
    .org-meta-card p{margin:0;color:#637772;font-size:.92rem;line-height:1.65;}
    .org-cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px;}
    .org-hidden{display:none!important;}
    @media (max-width: 920px){.org-hero{grid-template-columns:1fr;}.org-grid,.org-row{grid-template-columns:1fr;}}
  </style>
</head>
<body class="org-page">
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main class="org-shell">
  <section class="org-hero">
    <div class="org-card">
      <span class="org-eyebrow">Për OJF dhe organizata lokale</span>
      <h1>Publikoni evente me llogari organizatori, jo me privilegje admin.</h1>
      <p>Kjo rrugë është ndërtuar për organizata që duan të krijojnë evente publike dhe të menaxhojnë aplikimet e tyre, pa marrë akses në përdoruesit, raportet ose cilësimet e platformës.</p>
      <div class="org-grid">
        <div class="org-point">
          <strong>Miratim i kontrolluar</strong>
          <span>Çdo kërkesë shqyrtohet para se llogaria të kthehet në organizator aktiv.</span>
        </div>
        <div class="org-point">
          <strong>Evente me moderim</strong>
          <span>Eventet e reja dërgohen fillimisht për shqyrtim dhe dalin publikisht pas miratimit.</span>
        </div>
        <div class="org-point">
          <strong>Akses i kufizuar</strong>
          <span>Organizatorët menaxhojnë vetëm eventet dhe aplikimet e veta, jo platformën e plotë.</span>
        </div>
      </div>

    </div>

    <aside class="org-panel">
      <h2>Çfarë merr pas miratimit</h2>
      <div class="org-meta">
        <div class="org-meta-card">
          <strong>Panel eventesh i dedikuar</strong>
          <p>Krijoni evente, përditësoni detajet, shihni aplikimet dhe menaxhoni pjesëmarrësit vetëm për iniciativat tuaja.</p>
        </div>
        <div class="org-meta-card">
          <strong>Profil publik organizate</strong>
          <p>Emri i organizatës shfaqet si organizues në evente, në vend të emrit personal të përdoruesit.</p>
        </div>
        <div class="org-meta-card">
          <strong>Njoftime për vendimet</strong>
          <p>Do të merrni njoftim sapo aplikimi të miratohet ose të kthehet me komente për rishikim.</p>
        </div>
      </div>
    </aside>
  </section>

  <section class="org-panel">
    <h2><?= $isOrganizer ? 'Statusi i llogarisë suaj organizatore' : ($isAdmin ? 'Akses i drejtpërdrejtë si administrator' : 'Dërgoni aplikimin tuaj') ?></h2>
    <p><?= $isOrganizer ? 'Llogaria juaj është tashmë organizator aktiv. Mund të vazhdoni drejtpërdrejt në panelin e eventeve.' : ($isAdmin ? 'Si administrator, keni akses të plotë në panelin e eventeve pa nevojë për aplikim si organizatë.' : 'Plotësoni të dhënat bazë të organizatës. Një super administrator do t\'i shqyrtojë dhe do t\'ju njoftojë për vendimin.') ?></p>

    <?php if (!$isLoggedIn): ?>
      <div class="org-status org-status--approved" style="display:block;margin-bottom:18px;">
        <strong>Nuk keni nevojë për llogari.</strong>
        Plotësoni formularin dhe Super Administratori do të krijojë llogarinë tuaj si organizator pas miratimit.
      </div>
      <form id="organization-application-form" class="org-form">
        <div class="org-row">
          <div class="org-field">
            <label for="organization_name">Emri i organizatës</label>
            <input id="organization_name" name="organization_name" type="text" maxlength="160" required>
          </div>
          <div class="org-field">
            <label for="contact_name">Personi i kontaktit</label>
            <input id="contact_name" name="contact_name" type="text" maxlength="120" required>
          </div>
        </div>

        <div class="org-row">
          <div class="org-field">
            <label for="contact_email">Email i kontaktit</label>
            <input id="contact_email" name="contact_email" type="email" maxlength="160" required>
          </div>
          <div class="org-field">
            <label for="contact_phone">Telefon (opsional)</label>
            <input id="contact_phone" name="contact_phone" type="text" maxlength="40">
          </div>
        </div>

        <div class="org-field">
          <label for="website">Website (opsional)</label>
          <input id="website" name="website" type="url" maxlength="255" placeholder="https://organizata.al">
        </div>

        <div class="org-field">
          <label for="description">Përshkrim i shkurtër</label>
          <textarea id="description" name="description" maxlength="2000" placeholder="Përshkruani misionin, fushën e punës dhe llojin e eventeve që dëshironi të organizoni." required></textarea>
        </div>

        <div class="org-actions">
          <button type="submit" class="btn_primary">Dërgo aplikimin</button>
          <a href="/TiranaSolidare/views/events.php" class="btn_secondary">Kthehu te eventet</a>
        </div>
        <div id="organization-form-status" class="org-note"></div>
      </form>
    <?php else: ?>
      <div id="org-status-card" class="org-status"></div>
      <?php if ($isAdmin): ?>
      <div class="org-status org-status--approved" style="display:block;">
        <strong>Jeni të kyçur si administrator.</strong>
        Administratorët kanë akses të drejtpërdrejtë në panelin e eventeve — ky formular aplikimi nuk vlen për llogarinë tuaj.
      </div>
      <div class="org-actions" style="margin-top:12px;">
        <a href="/TiranaSolidare/views/dashboard.php" class="btn_primary">Hap panelin e adminit</a>
      </div>
      <?php else: ?>
      <form id="organization-application-form" class="org-form<?= $isOrganizer ? ' org-hidden' : '' ?>">
        <div class="org-row">
          <div class="org-field">
            <label for="organization_name">Emri i organizatës</label>
            <input id="organization_name" name="organization_name" type="text" maxlength="160" required>
          </div>
          <div class="org-field">
            <label for="contact_name">Personi i kontaktit</label>
            <input id="contact_name" name="contact_name" type="text" maxlength="120" value="<?= htmlspecialchars($userName) ?>" required>
          </div>
        </div>

        <div class="org-row">
          <div class="org-field">
            <label for="contact_email">Email i kontaktit</label>
            <input id="contact_email" name="contact_email" type="email" maxlength="160" value="<?= htmlspecialchars($userEmail) ?>" required>
          </div>
          <div class="org-field">
            <label for="contact_phone">Telefon (opsional)</label>
            <input id="contact_phone" name="contact_phone" type="text" maxlength="40">
          </div>
        </div>

        <div class="org-field">
          <label for="website">Website (opsional)</label>
          <input id="website" name="website" type="url" maxlength="255" placeholder="https://organizata.al">
        </div>

        <div class="org-field">
          <label for="description">Përshkrim i shkurtër</label>
          <textarea id="description" name="description" maxlength="2000" placeholder="Përshkruani misionin, fushën e punës dhe llojin e eventeve që dëshironi të organizoni." required></textarea>
        </div>

        <div class="org-actions">
          <button type="submit" class="btn_primary">Dërgo aplikimin</button>
          <a href="<?= $isOrganizer ? '/TiranaSolidare/views/dashboard.php' : '/TiranaSolidare/views/events.php' ?>" class="btn_secondary"><?= $isOrganizer ? 'Hap panelin' : 'Kthehu te eventet' ?></a>
        </div>
        <div id="organization-form-status" class="org-note"></div>
      </form>

      <?php if ($isOrganizer): ?>
      <div class="org-actions">
        <a href="/TiranaSolidare/views/dashboard.php" class="btn_primary">Hap panelin e organizatorit</a>
      </div>
      <?php endif; ?>
      <?php endif; // end !$isAdmin ?>
    <?php endif; // end isLoggedIn ?>
  </section>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>

<script src="/TiranaSolidare/assets/js/main.js?v=<?= filemtime(__DIR__.'/../assets/js/main.js') ?>"></script>
<script>
const ORG_FORM = document.getElementById('organization-application-form');
const ORG_STATUS = document.getElementById('org-status-card');
const ORG_STATUS_TEXT = document.getElementById('organization-form-status');

function setOrgStatusCard(application, isOrganizer) {
  if (!ORG_STATUS) return;
  ORG_STATUS.className = 'org-status';

  if (isOrganizer) {
    ORG_STATUS.classList.add('org-status--approved');
    ORG_STATUS.innerHTML = '<strong>Llogaria juaj është aktive si organizator.</strong>Mund të krijoni dhe menaxhoni evente nga paneli.';
    return;
  }

  if (!application) {
    ORG_STATUS.innerHTML = '';
    return;
  }

  const status = (application.status || '').toLowerCase();
  if (status === 'pending') {
    ORG_STATUS.classList.add('org-status--pending');
    ORG_STATUS.innerHTML = '<strong>Aplikimi juaj është në pritje.</strong>Do të njoftoheni sapo të shqyrtohet.';
    if (ORG_FORM) ORG_FORM.classList.add('org-hidden');
    return;
  }

  if (status === 'approved') {
    ORG_STATUS.classList.add('org-status--approved');
    ORG_STATUS.innerHTML = '<strong>Aplikimi u miratua.</strong>Roli juaj do të jetë organizator pas rifreskimit të sesionit. Nëse jeni ende në këtë faqe, kyçuni përsëri.';
    if (ORG_FORM) ORG_FORM.classList.add('org-hidden');
    return;
  }

  if (status === 'rejected') {
    ORG_STATUS.classList.add('org-status--rejected');
    ORG_STATUS.innerHTML = '<strong>Aplikimi i fundit u refuzua.</strong>' + (application.review_notes ? ' Shënim: ' + application.review_notes : ' Mund ta përditësoni dhe ta dërgoni përsëri.');
    if (ORG_FORM) ORG_FORM.classList.remove('org-hidden');
  }
}

function fillOrganizationForm(application) {
  if (!application || !ORG_FORM) return;
  ['organization_name', 'contact_name', 'contact_email', 'contact_phone', 'website', 'description'].forEach((key) => {
    const input = document.getElementById(key);
    if (input && application[key]) {
      input.value = application[key];
    }
  });
}

async function loadOrganizationApplicationState() {
  const json = await apiCall('organizations.php?action=mine');
  if (!json.success) {
    if (ORG_STATUS_TEXT) ORG_STATUS_TEXT.textContent = json.message || 'Gabim gjatë ngarkimit të statusit.';
    return;
  }

  const application = json.data.application;
  const isOrganizer = !!json.data.is_organizer;
  fillOrganizationForm(application);
  setOrgStatusCard(application, isOrganizer);
}

if (ORG_FORM) {
  ORG_FORM.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (ORG_STATUS_TEXT) {
      ORG_STATUS_TEXT.style.color = '#64748b';
      ORG_STATUS_TEXT.textContent = 'Duke dërguar aplikimin…';
    }

    const formData = new FormData(ORG_FORM);
    const body = Object.fromEntries(formData.entries());
    const json = await apiCall('organizations.php?action=submit', 'POST', body);
    if (ORG_STATUS_TEXT) {
      ORG_STATUS_TEXT.style.color = json.success ? '#15803d' : '#b91c1c';
      ORG_STATUS_TEXT.textContent = json.message || json.data?.message || (json.success ? 'Aplikimi u dërgua.' : 'Dërgimi dështoi.');
    }
    if (json.success) {
      ORG_FORM.reset();
      <?php if ($isLoggedIn): ?>
      document.getElementById('contact_name').value = '<?= htmlspecialchars(addslashes($userName)) ?>';
      document.getElementById('contact_email').value = '<?= htmlspecialchars(addslashes($userEmail)) ?>';
      await loadOrganizationApplicationState();
      <?php else: ?>
      ORG_FORM.classList.add('org-hidden');
      <?php endif; ?>
    }
  });
}

<?php if ($isLoggedIn): ?>
loadOrganizationApplicationState();
<?php endif; ?>
</script>
</body>
</html>