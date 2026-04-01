<?php
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pse jam bllokuar? - Tirana Solidare</title>

  <!-- Main styles -->
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260401a">

  <!-- Blog styles -->
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/blog-post.css?v=20260401a">
</head>

<body>

<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>
  <div class="blog-container">

    <!-- Title -->
    <h1>Pse jam bllokuar?</h1>

    <!-- Subtitle / intro -->
    <p class="lead">
      Llogaria juaj tek Tirana Solidare është bllokuar. Këtu shpjegojmë arsyet dhe si mund të kërkoni zhbllokim.
    </p>

    <hr>

    <!-- Content -->
    <p>
      Platforma jonë mbështetet në një komunitet të sigurt dhe të respektueshëm. 
      Nëse përdoruesit shkelin rregullat, llogaritë mund të bllokohen për të mbrojtur komunitetin.
    </p>

    <h2>Arsye të zakonshme</h2>

    <ul>
      <li>Humbje respekti ndaj të tjerëve: përmbajtje fyese ose diskriminuese.</li>
      <li>Abuzim ose spam në evente dhe kërkesa.</li>
      <li>Përdorim i shumëfishtë i llogarive false ose manipulim i sistemit.</li>
      <li>Shkelje të politikave të komunitetit dhe kushteve të përdorimit.</li>
    </ul>

    <h2>Si të kërkoni zhbllokim</h2>

    <ol>
      <li>
        Dërgoni email në 
        <a href="mailto:info@tiranasolidare.al">info@tiranasolidare.al</a>.
      </li>
      <li>
        Shpjegoni shkurt pse mendoni se bllokimi është gabim ose si do të përmirësoni sjelljen.
      </li>
      <li>
        Përfshini emrin, email-in dhe nëse mundeni referencë të llogarisë/eventit.
      </li>
    </ol>

    <!-- Callout -->
    <div class="callout">
      <strong>Shënim:</strong> Ne shqyrtojmë çdo kërkesë me drejtësi. 
      Nëse demonstroni angazhim pozitiv në komunitet, mund të zhbllokoheni.
    </div>

    <hr>

    <!-- Actions -->
    <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top: 20px;">
      <a href="mailto:info@tiranasolidare.al" class="btn_secondary">
        Kontakto ekipin
      </a>
    </div>

  </div>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>

</body>
</html>