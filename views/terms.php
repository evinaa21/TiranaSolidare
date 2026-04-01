<?php
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rregullat e Përdorimit — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260401a">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css?v=20260401a">
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
    <h1>Rregullat e Përdorimit</h1>
    <p class="updated">Përditësuar për herë të fundit: <?= date('d.m.Y') ?></p>

    <p>Duke krijuar një llogari ose duke përdorur platformën <strong>Tirana Solidare</strong>, ju pranoni këto Rregulla të Përdorimit. Nëse nuk jeni dakord me to, ju lutem mos i përdorni shërbimet tona.</p>

    <h2>1. Pranimi i kushteve</h2>
    <p>Tirana Solidare ("Platforma") është një nismë e bashkëpunimit komunitar ndërmjet vullnetarëve dhe Bashkisë së Tiranës. Përdorimi i platformës constitutes pranimin e këtyre rregullave, si dhe të <a href="/TiranaSolidare/views/privacy.php">Politikës sonë të Privatësisë</a>.</p>

    <h2>2. Krijimi i llogarisë</h2>
    <ul>
      <li>Duhet të jeni të paktën 16 vjeç për të hapur llogari.</li>
      <li>Çdo person mund të ketë vetëm <strong>një llogari aktive</strong>. Llogaritë e dyfishta mund të çaktivizohen.</li>
      <li>Jeni përgjegjës për saktësinë e informacionit të regjistrimit dhe konfidencialitetin e fjalëkalimit tuaj.</li>
      <li>Njoftoni menjëherë ekipin nëse dyshoni se llogaria juaj është komprometuar: <a href="mailto:info@tiranasolidare.al">info@tiranasolidare.al</a>.</li>
    </ul>

    <h2>3. Sjellja e pranueshme</h2>
    <p>Jeni dakord të mos:</p>
    <ul>
      <li>Postoni ose ndani përmbajtje fyese, diskriminuese, urrejtëse, ose të rreme.</li>
      <li>Ngacmoni, kërcënoni ose shfrytëzoni përdorues të tjerë.</li>
      <li>Shpërndani informacione personale të të tjerëve pa lejen e tyre.</li>
      <li>Tentoni të aksesoni llogaritë e të tjerëve ose sisteme jo të autorizuara.</li>
      <li>Dërgoni mesazhe reklamimi, spam ose komunikime të pakërkuara.</li>
      <li>Postoni lidhje me përmbajtje keqdashëse (malware, phishing).</li>
    </ul>

    <h2>4. Pjesëmarrja vullnetare</h2>
    <ul>
      <li>Aplikimet për evente dhe kërkesa ndihme janë vullnetare dhe pa pagesë.</li>
      <li>Pasi të pranohet aplikimi juaj, ju lutemi respektoni angazhimin e marrë. Tërheqja e vonë pa arsye mund të ndikojë negativisht në numrin tuaj të pikëve.</li>
      <li>Vullnetarët mbajnë përgjegjësi personale gjatë aktiviteteve. Platforma nuk është e mbuluar si kompani sigurimi.</li>
      <li>Është rreptësisht e ndaluar të ndryshoni ose falsifikoni statusin e aplikimeve ose eventeve.</li>
    </ul>

    <h2>5. Kërkesat për ndihmë</h2>
    <ul>
      <li>Kërkesat për ndihmë duhet të jenë të vërteta dhe në nevojë reale.</li>
      <li>Kërkesat abuzive, të rreme ose reklamuese do të fshihen dhe llogaria do të bllokihet.</li>
      <li>Postimi i vendndodhjes suaj është vullnetar, por mundëson shërbim më të shpejtë.</li>
    </ul>

    <h2>6. Politika e përmbajtjes</h2>
    <ul>
      <li>Fotot e profilit duhet të jenë të duhura dhe nuk duhet të përmbajnë logot e kompanive tregtare.</li>
      <li>Biografia dhe emri i profilit nuk duhet të përmbajnë informacione kontakti tregtare ose lidhje të jashtme.</li>
      <li>Mesazhet ndërmjet përdoruesve janë private, por mund të shqyrtohen nga administratorët nëse raportohen si shkelje.</li>
    </ul>

    <h2>7. Të drejtat e platformës</h2>
    <ul>
      <li>Platforma rezervon të drejtën të fshijë ose modifikojë çdo përmbajtje që shkel këto rregulla.</li>
      <li>Llogaritë mund të bllokohen ose fshihen pa njoftim paraprak në rast shkeljes së rëndë të rregullave.</li>
      <li>Platforma mund të ndryshojë, pezullojë ose ndërpresë shërbime pa detyrim paraprak.</li>
      <li>Çdo funksion ose shërbim i ri i shtuar platformës do të i nënshtrohet këtyre rregullave.</li>
    </ul>

    <h2>8. Kufizimi i përgjegjësisë</h2>
    <p>Platforma ofrohet "si është" dhe "si është e disponueshme". Tirana Solidare dhe Bashkia e Tiranës nuk garantojnë disponueshmëri të pandërprerë të shërbimit dhe nuk mbajnë përgjegjësi për:</p>
    <ul>
      <li>Humbje të të dhënave për shkak të dështimeve teknike.</li>
      <li>Veprime ose mosveprime të vullnetarëve gjatë aktiviteteve.</li>
      <li>Dëme indirekte që rrjedhin nga përdorimi i platformës.</li>
    </ul>

    <h2>9. Ligji i zbatueshëm</h2>
    <p>Këto rregulla rregullohen nga legjislacioni i Republikës së Shqipërisë. Çdo mosmarrëveshje do të zgjidhet nga gjykatat kompetente të Tiranës.</p>

    <h2>10. Ndryshimet e rregullave</h2>
    <p>Tirana Solidare rezervon të drejtën të modifikojë këto rregulla në çdo kohë. Çdo ndryshim do të postohet në këtë faqe me datën e hyrjes në fuqi. Përdorimi i vazhdueshëm i platformës pas ndryshimeve constitutes pranimin tuaj.</p>

    <h2>Kontakt</h2>
    <p>Për pyetje ose shqetësime lidhur me këto rregulla, na shkruani në: <a href="mailto:info@tiranasolidare.al">info@tiranasolidare.al</a></p>
  </div>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js?v=20260401a"></script>
</body>
</html>
