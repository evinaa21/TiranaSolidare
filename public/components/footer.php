<?php
// Footer component
if (!function_exists('ts_get_site_logo_url')) {
  require_once dirname(__DIR__, 2) . '/includes/functions.php';
}
$logoUrl = ts_get_site_logo_url();
$hasCustomLogo = ts_has_custom_logo();
$dataAttr = $hasCustomLogo ? ' data-custom-logo="true"' : '';
?>

<footer id="footer" class="footer"<?php echo $dataAttr; ?><?php echo $hasCustomLogo ? ' style="--logo-url: url(\'' . htmlspecialchars($logoUrl) . '\')"' : ''; ?>>
  <div class="footer-main">
    <div class="footer-content">
      <div class="footer-logo">
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Tirana Solidare">
        <span>Tirana<b>Solidare</b></span>
      </div>
      <p>Ne besojmë se çdo akt i vogël mirësie ka fuqinë të ndryshojë jetën e dikujt. Platforma jonë është krijuar për të afruar njerëzit dhe për të ndërtuar një komunitet më të kujdesshëm dhe mbështetës.</p>
    </div>
    <div class="footer-links">
      <ul>
        <li>Navigim</li>
        <li><a href="/TiranaSolidare/public/">Kreu</a></li>
        <li><a href="/TiranaSolidare/views/events.php">Evente</a></li>
        <li><a href="/TiranaSolidare/views/help_requests.php">Kërkesat</a></li>
        <li><a href="/TiranaSolidare/views/map.php">Harta</a></li></ul>
      <ul>
        <li>Legal</li>
        <li><a href="/TiranaSolidare/views/privacy.php">Politika e Privatësisë</a></li>
        <li><a href="/TiranaSolidare/views/terms.php">Rregullat e Përdorimit</a></li>
        <li><a href="mailto:info@tiranasolidare.al">Kontakto</a></li>
      </ul>
      <ul>
        <li>Kontakt</li>
        <li><a href="mailto:info@tiranasolidare.al">info@tiranasolidare.al</a></li>
        <li><a href="tel:+355691234567">+355 69 123 4567</a></li>
        <li><a href="#">Bashkia Tiranë, Tiranë</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-copy">
    <div class="footer-partners">
      <div class="footer-partners__logos">
        <img src="/TiranaSolidare/public/assets/images/bashkia.png?v=<?= @filemtime(__DIR__ . '/../assets/images/bashkia.png') ?: time() ?>"
             alt="Bashkia Tiranë"
             class="footer-partner-logo footer-partner-logo--bashkia"
             onerror="this.style.display='none'">
        <span class="footer-partners__divider"></span>
        <img src="/TiranaSolidare/public/assets/images/webchallenge.png?v=<?= @filemtime(__DIR__ . '/../assets/images/webchallenge.png') ?: time() ?>"
             alt="Web Challenge"
             class="footer-partner-logo footer-partner-logo--webchallenge"
             onerror="this.style.display='none'">
      </div>
    </div>
    <span>Copyright &copy; <?= date('Y') ?> Tirana Solidare. Të drejtat e rezervuara</span>
  </div>
</footer>
