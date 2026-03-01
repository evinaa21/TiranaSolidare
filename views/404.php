<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — Faqja nuk u gjet | Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css">
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>
  <section class="page-hero">
    <div class="page-hero__inner" style="text-align:center;">
      <h1 style="font-size:4rem;margin-bottom:0.5rem;">404</h1>
      <p style="font-size:1.25rem;margin-bottom:2rem;">Faqja që kërkuat nuk u gjet.</p>
      <a href="/TiranaSolidare/public/" class="btn_primary">Kthehu në Kreu</a>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>
