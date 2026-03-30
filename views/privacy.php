<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Politika e Privatësisë — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css">
  <style>
    main { padding-top: 96px; }
    .privacy-container { max-width: 820px; margin: 0 auto; padding: 0 2rem 2rem; }
    .privacy-container h1 { font-size: 2rem; margin-bottom: 0.5rem; color: var(--clr-primary, #00715D); }
    .privacy-container h2 { font-size: 1.25rem; margin-top: 2rem; margin-bottom: 0.5rem; color: var(--clr-primary, #00715D); }
    .privacy-container p, .privacy-container li { line-height: 1.7; color: #444; }
    .privacy-container ul { padding-left: 1.5rem; margin-bottom: 1rem; }
    .privacy-container .updated { color: #888; font-size: 0.9rem; margin-bottom: 2rem; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>
  <div class="privacy-container">
    <h1>Politika e Privatësisë</h1>
    <p class="updated">Përditësuar për herë të fundit: <?= date('d.m.Y') ?></p>

    <p>Tirana Solidare ("Platforma") është një nismë e bashkëpunimit komunitar që respekton të drejtën tuaj për privatësinë e të dhënave personale, në përputhje me Ligjin Nr. 9887 "Për Mbrojtjen e të Dhënave Personale" të Republikës së Shqipërisë dhe Rregulloren e Përgjithshme të Mbrojtjes së të Dhënave (GDPR) të BE.</p>

    <h2>1. Kush jemi ne</h2>
    <p>Platforma operohet në kuadrin e një projekti komunitar në bashkëpunim me Bashkinë e Tiranës. Për pyetje mbi privatësinë, kontaktoni: <a href="mailto:info@tiranasolidare.al">info@tiranasolidare.al</a>.</p>

    <h2>2. Të dhënat që mbledhim</h2>
    <ul>
      <li><strong>Të dhëna identifikuese:</strong> emri i plotë, adresa e email-it — të nevojshme për krijimin e llogarisë.</li>
      <li><strong>Të dhëna profili:</strong> biografia, foto profili, ngjyra e zgjedhur — vullnetare.</li>
      <li><strong>Të dhëna aktiviteti:</strong> aplikimet për evente, kërkesat për ndihmë, mesazhet, njoftimet.</li>
      <li><strong>Të dhëna teknike:</strong> adresa IP (për mbrojtje nga shpërdorime), kohëvula e sesionit.</li>
    </ul>

    <h2>3. Baza ligjore &amp; qëllimi</h2>
    <ul>
      <li><strong>Pëlqimi:</strong> Regjistrimi kërkon pëlqimin tuaj të qartë përmes checkbox-it të privatësisë.</li>
      <li><strong>Performanca e shërbimit:</strong> Përpunimi i të dhënave për t'ju mundësuar pjesëmarrjen në evente dhe kërkesa ndihme.</li>
      <li><strong>Interes legjitim:</strong> Mbrojtja e platformës nga shpërdorime (rate limiting, logje).</li>
    </ul>

    <h2>4. Si i përdorim të dhënat</h2>
    <ul>
      <li>Për krijimin dhe menaxhimin e llogarisë suaj</li>
      <li>Për procesimin e aplikimeve në evente vullnetare</li>
      <li>Për mundësimin e komunikimit ndërmjet përdoruesve</li>
      <li>Për dërgimin e njoftimeve me email (mund të çaktivizohen)</li>
      <li>Për mbrojtjen e sigurisë së platformës</li>
    </ul>

    <h2>5. Ruajtja e të dhënave</h2>
    <p>Të dhënat tuaja ruhen për sa kohë llogaria juaj është aktive. Pas fshirjes së llogarisë, të dhënat fshihen brenda 30 ditëve nga sistemet tona, përveç logje-ve të sigurisë që ruhen deri në 1 vit.</p>

    <h2>6. Të drejtat tuaja</h2>
    <p>Bazuar në Ligjin Nr. 9887 dhe GDPR, keni të drejtë të:</p>
    <ul>
      <li><strong>Aksesoni</strong> të dhënat tuaja personale (nëpërmjet profilit tuaj)</li>
      <li><strong>Korrigjoni</strong> të dhënat e pasakta</li>
      <li><strong>Fshini llogarinë</strong> tuaj dhe të dhënat përkatëse (E Drejta për t'u Harruar)</li>
      <li><strong>Tërhiqni</strong> pëlqimin në çdo moment</li>
      <li><strong>Eksportoni</strong> të dhënat tuaja (kontaktoni <a href="mailto:info@tiranasolidare.al">info@tiranasolidare.al</a>)</li>
      <li><strong>Parashtroni ankim</strong> pranë Komisionerit për Mbrojtjen e të Dhënave Personale</li>
    </ul>
    <p>Për të fshirë llogarinë tuaj, hyni në panelin tuaj → Profili im → "Fshi llogarinë".</p>

    <h2>7. Sigurisë e të dhënave</h2>
    <ul>
      <li>Fjalëkalimet ruhen të koduara me bcrypt</li>
      <li>Sesionet mbrohen me HttpOnly, SameSite, Secure cookies</li>
      <li>Mbrojtje CSRF në të gjitha veprimet</li>
      <li>Rate limiting kundër sulmeve brute-force</li>
      <li>Email-et dërgohen nëpërmjet kanalit TLS të koduar</li>
    </ul>

    <h2>8. Ndarja e të dhënave</h2>
    <p>Nuk i ndajmë të dhënat tuaja personale me palë të treta tregtare. Të dhënat mund t'i ndajmë vetëm me:</p>
    <ul>
      <li>Bashkinë e Tiranës — si partner institucional i projektit</li>
      <li>Autoritetet ligjore — kur kërkohet me vendim gjyqësor</li>
    </ul>

    <h2>9. Cookies</h2>
    <p>Platforma përdor vetëm cookie-t e domosdoshme për sesionin dhe sigurinë (CSRF). Nuk përdorim cookie-t e reklamimit apo analitikës së palëve të treta.</p>

    <h2>10. Ndryshime në këtë politikë</h2>
    <p>Kjo politikë mund të përditësohet. Ndryshimet do të postohen në këtë faqe me datën përkatëse. Përdorimi i vazhdueshëm i platformës pas ndryshimeve përbën pranimin tuaj.</p>

    <h2>Kontakt</h2>
    <p>Për pyetje ose kërkesa lidhur me privatësinë, na shkruani në: <a href="mailto:info@tiranasolidare.al">info@tiranasolidare.al</a></p>
  </div>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>
