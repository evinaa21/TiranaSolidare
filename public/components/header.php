<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('ts_resolve_profile_color')) {
  require_once dirname(__DIR__, 2) . '/includes/functions.php';
}

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $isLoggedIn ? ($_SESSION['emri'] ?? 'Përdorues') : '';
$isAdminUser = (isset($_SESSION['roli']) && in_array(ts_normalize_value($_SESSION['roli']), ['admin', 'super_admin'], true));

$firstChar = function_exists('mb_substr') ? mb_substr($userName ?: 'P', 0, 1) : substr($userName ?: 'P', 0, 1);
$userInitial = function_exists('mb_strtoupper') ? mb_strtoupper($firstChar) : strtoupper($firstChar);

$avatarSessionValue = $_SESSION['profile_picture'] ?? $_SESSION['avatar'] ?? $_SESSION['photo'] ?? $_SESSION['foto'] ?? $_SESSION['profile_image'] ?? '';
$avatarUrl = '';
if (is_string($avatarSessionValue) && $avatarSessionValue !== '') {
  if (preg_match('/^https?:\/\//i', $avatarSessionValue) || strpos($avatarSessionValue, '/TiranaSolidare/') === 0 || strpos($avatarSessionValue, '/') === 0) {
    $avatarUrl = $avatarSessionValue;
  } else {
    $avatarUrl = '/TiranaSolidare/public/assets/uploads/' . ltrim($avatarSessionValue, '/');
  }
}

$avatarHue = abs(crc32((string) $userName)) % 360;
$headerColorResolved = ts_resolve_profile_color($_SESSION['profile_color'] ?? 'emerald');
$headerColorTheme = $headerColorResolved['theme'];
?>
<link rel="manifest" href="/TiranaSolidare/public/manifest.json">
<meta name="theme-color" content="#00715D">
<header id="header" class="header">
  <a href="/TiranaSolidare/public/" class="header-logo">
    <img src="/TiranaSolidare/public/assets/images/logo.png" alt="Tirana Solidare">
    <span>Tirana<b>Solidare</b></span>
  </a>
  <nav class="header-nav">
    <button onclick="toggleMenu()">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
    <a href="/TiranaSolidare/public/" class="header-main-link">Kreu</a>
    <a href="/TiranaSolidare/public/#regjistrohu" class="header-main-link">Misioni</a>
    <a href="/TiranaSolidare/public/#si-funksionon" class="header-main-link">Si Funksionon</a>
    <a href="/TiranaSolidare/views/events.php" class="header-main-link">Evente</a>
    <a href="/TiranaSolidare/views/help_requests.php" class="header-main-link">Kërkesat</a>
    <a href="/TiranaSolidare/views/map.php" class="header-main-link">Harta</a>
    <span></span>
    <?php if ($isLoggedIn): ?>
        <div class="header-user-menu" id="header-user-menu">
          <button type="button" class="header-user-avatar" style="--avatar-accent: <?= htmlspecialchars($headerColorTheme['mid']) ?>;" aria-haspopup="true" aria-expanded="false" aria-controls="header-user-dropdown" onclick="toggleUserMenu(event)">
            <?php if ($avatarUrl !== ''): ?>
              <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($userName) ?>" onerror="this.style.display='none'; this.parentElement.classList.add('has-fallback'); this.parentElement.querySelector('.header-user-fallback').style.display='grid';">
            <?php endif; ?>
            <span class="header-user-fallback" style="--avatar-hue: <?= (int) $avatarHue ?>; --avatar-from: <?= htmlspecialchars($headerColorTheme['from']) ?>; --avatar-to: <?= htmlspecialchars($headerColorTheme['to']) ?>;<?= $avatarUrl !== '' ? 'display:none;' : '' ?>"><?= htmlspecialchars($userInitial) ?></span>
            <span id="notif-badge"></span>
            <span class="header-user-name"><?= htmlspecialchars($userName) ?></span>
            <span class="header-user-mobile-label">Llogaria ime</span>
            <span class="header-user-mobile-chevron" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </span>
          </button>

            <div class="header-user-dropdown" id="header-user-dropdown">
            <div class="header-user-dropdown__head">
              <strong><?= htmlspecialchars($userName) ?></strong>
              <small><?= $isAdminUser ? 'Administrator' : 'Paneli i vullnetarit' ?></small>
            </div>
            <?php if ($isAdminUser): ?>
            <a href="/TiranaSolidare/views/dashboard.php" class="header-dropdown-admin-link">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
              Paneli Admin
            </a>
            <?php else: ?>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=profile">Profili</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=applications">Aplikimet e mia</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=requests">Kërkesat e mia</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=score">Pikët e mia</a>
            <a href="/TiranaSolidare/views/leaderboard.php">Renditja</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=notifications">Njoftimet</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=settings">Cilësimet</a>
            <?php endif; ?>
            <form method="POST" action="/TiranaSolidare/src/actions/logout.php" style="display:inline;">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="header-user-signout">Dil</button>
            </form>
          </div>
        </div>
    <?php else: ?>
      <a href="/TiranaSolidare/views/register.php" class="btn_primary">
        Bëhu Vullnetar 
      </a>
      <a href="/TiranaSolidare/views/login.php" class="btn_secondary">
        Kyçu
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-in-icon lucide-log-in"><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></svg>
      </a>
    <?php endif; ?>
  </nav>

  <div class="header-btn">
    <button onclick="toggleMenu()">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu-icon lucide-menu"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/></svg>
    </button>
  </div>
</header>

<script>
if ('serviceWorker' in navigator) {
  // Root-level sw.js has scope /TiranaSolidare/ so it covers both /public/ and /views/
  navigator.serviceWorker.register('/TiranaSolidare/sw.js');
}
</script>
