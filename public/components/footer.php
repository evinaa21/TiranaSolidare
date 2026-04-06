<?php
// Footer component
if (!function_exists('ts_get_site_logo_url')) {
  require_once dirname(__DIR__, 2) . '/includes/functions.php';
}
$logoUrl = ts_get_site_logo_url();
$hasCustomLogo = ts_has_custom_logo();
$dataAttr = $hasCustomLogo ? ' data-custom-logo="true"' : '';
$contactPage = ts_contact_page_path();
$supportEmail = ts_support_email();
$siteName = ts_get_site_setting('organization_name', 'Tirana Solidare');
$footerBlurb = ts_get_site_setting('footer_blurb');
$contactPhone = ts_get_site_setting('contact_phone', '+355 69 123 4567');
$contactAddress = ts_get_site_setting('contact_address', 'Bashkia Tiranë, Tiranë');
?>

<footer id="footer" class="footer"<?php echo $dataAttr; ?><?php echo $hasCustomLogo ? ' style="--logo-url: url(\'' . htmlspecialchars($logoUrl) . '\')"' : ''; ?>>
  <div class="footer-main">
    <div class="footer-content">
      <div class="footer-logo">
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?>">
        <span><?= htmlspecialchars($siteName) ?></span>
      </div>
      <p><?= htmlspecialchars($footerBlurb) ?></p>
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
        <li><a href="<?= htmlspecialchars($contactPage) ?>">Kontakto</a></li>
      </ul>
      <ul>
        <li>Kontakt</li>
        <li><a href="<?= htmlspecialchars($contactPage) ?>">Faqja e kontaktit</a></li>
        <li><a href="/TiranaSolidare/views/become_organizer.php">Apliko si organizatë</a></li>
        <li><span><?= htmlspecialchars($supportEmail) ?></span></li>
        <li><a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $contactPhone)) ?>"><?= htmlspecialchars($contactPhone) ?></a></li>
        <li><a href="#"><?= htmlspecialchars($contactAddress) ?></a></li>
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
    <span>Copyright &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. Të drejtat e rezervuara</span>
  </div>
</footer>
