<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $isLoggedIn ? ($_SESSION['emri'] ?? 'Përdorues') : '';
?>
<header id="header" class="header">
  <a href="/TiranaSolidare/public/" class="header-logo">
    <img src="/TiranaSolidare/public/assets/images/logo.png" alt="Tirana Solidare">
    <span>Tirana<b>Solidare</b></span>
  </a>
  <nav class="header-nav">
    <button onclick="toggleMenu()">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
    <a href="/TiranaSolidare/public/">Kreu</a>
    <a href="/TiranaSolidare/public/#si-funksionon">Si Funksionon</a>
    <a href="/TiranaSolidare/views/help_requests.php">Kërkesat</a>
    <a href="/TiranaSolidare/views/events.php">Evente</a>
    <a href="/TiranaSolidare/public/#regjistrohu">Misioni</a>
    <span></span>
    <?php if ($isLoggedIn): ?>
      <span id="notif-badge"></span>
      <span class="header-user"><?= htmlspecialchars($userName) ?></span>
      <a href="/TiranaSolidare/views/dashboard.php" class="btn_primary">Paneli</a>
      <a href="/TiranaSolidare/src/actions/logout.php" class="btn_secondary">Dil</a>
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

```