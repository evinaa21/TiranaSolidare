<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flash = $_SESSION['contact_flash'] ?? null;
unset($_SESSION['contact_flash']);

$old = $_SESSION['contact_form_old'] ?? [];
unset($_SESSION['contact_form_old']);

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$prefillName = trim((string) ($old['name'] ?? ($_SESSION['emri'] ?? '')));
$prefillEmail = trim((string) ($old['email'] ?? ''));
$prefillSubject = trim((string) ($old['subject'] ?? ''));
$prefillMessage = trim((string) ($old['message'] ?? ''));

if ($prefillEmail === '' && $currentUserId !== null) {
    $emailStmt = $pdo->prepare('SELECT email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
    $emailStmt->execute([$currentUserId]);
    $prefillEmail = (string) ($emailStmt->fetchColumn() ?: '');
}

$supportEmail = ts_support_email();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kontakto Ekipin — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260401a">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css?v=20260401a">
  <style>
    main {
      padding: 104px 0 72px;
      background:
        radial-gradient(circle at top left, rgba(0, 113, 93, 0.14), transparent 34%),
        linear-gradient(180deg, #f5faf8 0%, #ffffff 100%);
    }

    .contact-shell {
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 24px;
    }

    .contact-hero {
      margin-bottom: 28px;
    }

    .contact-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 14px;
      padding: 7px 12px;
      border-radius: 999px;
      background: rgba(0, 113, 93, 0.1);
      color: #0d5d4d;
      font-size: 0.84rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .contact-hero h1 {
      max-width: 720px;
      margin: 0 0 12px;
      color: #0f172a;
      font-size: clamp(2rem, 4vw, 3.35rem);
      line-height: 1.06;
    }

    .contact-hero p {
      max-width: 720px;
      margin: 0;
      color: #475569;
      font-size: 1.02rem;
      line-height: 1.75;
    }

    .contact-grid {
      display: grid;
      grid-template-columns: minmax(300px, 0.95fr) minmax(0, 1.05fr);
      gap: 24px;
      align-items: start;
    }

    .contact-card {
      padding: 28px;
      border: 1px solid rgba(148, 163, 184, 0.18);
      border-radius: 26px;
      background: rgba(255, 255, 255, 0.96);
      box-shadow: 0 22px 50px rgba(15, 23, 42, 0.08);
    }

    .contact-card--accent {
      background:
        linear-gradient(160deg, rgba(0, 113, 93, 0.95), rgba(8, 89, 74, 0.92)),
        #0a5b4b;
      color: #f8fffc;
      border-color: transparent;
    }

    .contact-card h2 {
      margin: 0 0 12px;
      color: #0f172a;
      font-size: 1.4rem;
    }

    .contact-card p {
      margin: 0 0 16px;
      color: #526072;
      line-height: 1.7;
    }

    .contact-card--accent,
    .contact-card--accent h2,
    .contact-card--accent p,
    .contact-card--accent a,
    .contact-card--accent li,
    .contact-card--accent strong,
    .contact-card--accent span,
    .contact-card--accent div {
      color: #f8fffc;
    }

    .contact-points {
      margin: 22px 0 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 14px;
    }

    .contact-points li {
      display: grid;
      gap: 4px;
      padding: 14px 16px;
      border-radius: 18px;
      background: rgba(255, 255, 255, 0.08);
    }

    .contact-points span {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      opacity: 0.78;
    }

    .contact-note {
      margin-top: 22px;
      padding: 16px 18px;
      border-radius: 18px;
      background: rgba(255, 255, 255, 0.12);
      line-height: 1.7;
    }

    .contact-alert {
      margin-bottom: 18px;
      padding: 14px 16px;
      border-radius: 16px;
      font-size: 0.95rem;
      line-height: 1.6;
    }

    .contact-alert--success {
      background: #ecfdf5;
      border: 1px solid #bbf7d0;
      color: #166534;
    }

    .contact-alert--error {
      background: #fff7ed;
      border: 1px solid #fed7aa;
      color: #9a3412;
    }

    .contact-form {
      display: grid;
      gap: 16px;
    }

    .contact-form__row {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }

    .contact-field {
      display: grid;
      gap: 8px;
    }

    .contact-field label {
      color: #0f172a;
      font-size: 0.92rem;
      font-weight: 700;
    }

    .contact-field input,
    .contact-field textarea {
      width: 100%;
      padding: 13px 14px;
      border: 1.5px solid #d9e2ec;
      border-radius: 16px;
      background: #fff;
      color: #0f172a;
      font: inherit;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
      resize: vertical;
    }

    .contact-field input:focus,
    .contact-field textarea:focus {
      outline: none;
      border-color: #0b7d67;
      box-shadow: 0 0 0 4px rgba(0, 113, 93, 0.12);
    }

    .contact-meta {
      color: #64748b;
      font-size: 0.9rem;
      line-height: 1.7;
    }

    .contact-submit {
      justify-self: start;
      padding: 13px 22px;
      border: 0;
      border-radius: 999px;
      background: linear-gradient(135deg, #00715d 0%, #0b7d67 100%);
      color: #fff;
      font-size: 0.98rem;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 16px 30px rgba(0, 113, 93, 0.22);
    }

    @media (max-width: 900px) {
      .contact-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      main {
        padding-top: 92px;
      }

      .contact-shell {
        padding: 0 16px;
      }

      .contact-card {
        padding: 22px 18px;
        border-radius: 22px;
      }

      .contact-form__row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>
  <section class="contact-shell">
    <div class="contact-hero">
      <div class="contact-eyebrow">Kontakt Zyrtar</div>
      <h1>Pyetje, raportime ose çështje administrative kalojnë këtu.</h1>
      <p>Ky kanal përdoret për kontaktin me ekipin e Tirana Solidare dhe me Bashkinë, sidomos kur keni nevojë për ndihmë mbi llogarinë, raportime sigurie, bllokime ose çështje që nuk duhet të kalojnë përmes mesazheve direkte.</p>
    </div>

    <div class="contact-grid">
      <article class="contact-card contact-card--accent">
        <h2>Na shkruani drejtpërdrejt</h2>
        <p>Mesazhi juaj i dërgohet ekipit me email dhe mund të marrë përgjigje direkt në adresën që vendosni në formular.</p>

        <ul class="contact-points">
          <li>
            <span>Email</span>
            <a href="mailto:<?= htmlspecialchars($supportEmail) ?>"><?= htmlspecialchars($supportEmail) ?></a>
          </li>
          <li>
            <span>Telefon</span>
            <a href="tel:+355691234567">+355 69 123 4567</a>
          </li>
          <li>
            <span>Vendndodhja</span>
            <strong>Bashkia Tiranë, Tiranë</strong>
          </li>
        </ul>

        <div class="contact-note">
          Nëse jeni të kyçur, mesazhi do të përfshijë edhe ID-në e llogarisë suaj për ta bërë trajtimin më të shpejtë. Për raste urgjente sigurie, përmendni qartë çfarë ndryshoi dhe kur e vutë re.
        </div>
      </article>

      <section class="contact-card">
        <h2>Formulari i kontaktit</h2>
        <p>Përdorni këtë formular për pyetje të përgjithshme, apelime, raportime ose ndihmë teknike.</p>

        <?php if (is_array($flash) && isset($flash['message'], $flash['type'])): ?>
          <div class="contact-alert contact-alert--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars((string) $flash['message']) ?>
          </div>
        <?php endif; ?>

        <form class="contact-form" method="post" action="/TiranaSolidare/src/actions/contact_action.php">
          <?= csrf_field() ?>

          <div class="contact-form__row">
            <div class="contact-field">
              <label for="contact-name">Emri</label>
              <input id="contact-name" name="name" type="text" maxlength="120" required value="<?= htmlspecialchars($prefillName) ?>">
            </div>

            <div class="contact-field">
              <label for="contact-email">Email-i</label>
              <input id="contact-email" name="email" type="email" maxlength="190" required value="<?= htmlspecialchars($prefillEmail) ?>">
            </div>
          </div>

          <div class="contact-field">
            <label for="contact-subject">Subjekti</label>
            <input id="contact-subject" name="subject" type="text" maxlength="160" required value="<?= htmlspecialchars($prefillSubject) ?>">
          </div>

          <div class="contact-field">
            <label for="contact-message">Mesazhi</label>
            <textarea id="contact-message" name="message" rows="8" maxlength="4000" required><?= htmlspecialchars($prefillMessage) ?></textarea>
          </div>

          <p class="contact-meta">Ne ruajmë IP-në dhe kufizojmë dërgesat për të parandaluar abuzimin. Për kërkesa mbi llogari, përdorni të njëjtin email që keni në platformë kur është e mundur.</p>

          <button type="submit" class="contact-submit">Dërgo mesazhin</button>
        </form>
      </section>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
</body>
</html>