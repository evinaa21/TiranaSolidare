<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('ts_resolve_profile_color')) {
  require_once dirname(__DIR__, 2) . '/includes/functions.php';
}

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $isLoggedIn ? ($_SESSION['emri'] ?? 'Përdorues') : '';
$isDashboardUser = (isset($_SESSION['roli']) && ts_is_dashboard_role_value($_SESSION['roli']));
$siteName = ts_get_site_setting('organization_name', 'Tirana Solidare');
$themePrimary = ts_get_site_setting('theme_primary', '#00715D');

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
<meta name="theme-color" content="<?= htmlspecialchars($themePrimary) ?>">
<?= ts_brand_theme_css() ?>
<header id="header" class="header">
  <a href="/TiranaSolidare/public/" class="header-logo">
    <img src="<?= htmlspecialchars(ts_get_site_logo_url()) ?>" alt="<?= htmlspecialchars($siteName) ?>">
    <span><?= htmlspecialchars($siteName) ?></span>
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
    <a href="/TiranaSolidare/views/become_organizer.php" class="header-main-link">Për Organizata</a>
    <span></span>
    <?php if ($isLoggedIn): ?>
      <?php if (!$isDashboardUser): ?>
        <div class="header-notif-wrap" id="header-notif-wrap">
          <button type="button" class="header-notif-bell" id="header-notif-btn"
                  aria-label="Njoftimet" title="Njoftimet"
                  onclick="toggleNotifDropdown(event)">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span id="notif-badge"></span>
          </button>
          <div class="header-notif-dropdown" id="header-notif-dropdown" hidden>
            <div class="header-notif-dropdown__head">
              <span>Njoftimet</span>
              <button type="button" onclick="headerMarkAllRead()">Shëno të gjitha</button>
            </div>
            <div id="header-notif-list">
              <div class="header-notif-empty">Duke ngarkuar…</div>
            </div>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=notifications" class="header-notif-viewall">Shiko të gjitha &rarr;</a>
          </div>
        </div>
        <?php endif; ?>
        <div class="header-user-menu" id="header-user-menu">
          <button type="button" class="header-user-avatar" style="--avatar-accent: <?= htmlspecialchars($headerColorTheme['mid']) ?>;" aria-haspopup="true" aria-expanded="false" aria-controls="header-user-dropdown" onclick="toggleUserMenu(event)">
            <?php if ($avatarUrl !== ''): ?>
              <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($userName) ?>" onerror="this.style.display='none'; this.parentElement.classList.add('has-fallback'); this.parentElement.querySelector('.header-user-fallback').style.display='grid';">
            <?php endif; ?>
            <span class="header-user-fallback" style="--avatar-hue: <?= (int) $avatarHue ?>; --avatar-from: <?= htmlspecialchars($headerColorTheme['from']) ?>; --avatar-to: <?= htmlspecialchars($headerColorTheme['to']) ?>;<?= $avatarUrl !== '' ? 'display:none;' : '' ?>"><?= htmlspecialchars($userInitial) ?></span>
            <span class="header-user-name"><?= htmlspecialchars($userName) ?></span>
            <span class="header-user-mobile-label">Llogaria ime</span>
            <span class="header-user-mobile-chevron" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </span>
          </button>

            <div class="header-user-dropdown" id="header-user-dropdown">
            <div class="header-user-dropdown__head">
              <strong><?= htmlspecialchars($userName) ?></strong>
              <small><?= $isDashboardUser ? 'Paneli i menaxhimit' : 'Paneli i vullnetarit' ?></small>
            </div>
            <?php if ($isDashboardUser): ?>
            <a href="/TiranaSolidare/views/dashboard.php" class="header-dropdown-admin-link">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
              Paneli
            </a>
            <?php else: ?>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=profile">Profili</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=applications">Aplikimet e mia</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=requests">Kërkesat e mia</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=score">Pikët e mia</a>
            <a href="/TiranaSolidare/views/leaderboard.php">Renditja</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=notifications">Njoftimet</a>
            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=settings">Cilësimet</a>
            <a href="/TiranaSolidare/views/become_organizer.php">Apliko si organizatë</a>
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

<style>
.header-notif-wrap{position:relative;display:inline-flex;}
.header-notif-bell{background:none;border:none;cursor:pointer;position:relative;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:999px;color:inherit;transition:background .2s;padding:0;}
.header-notif-bell:hover{background:rgba(0,0,0,0.07);}
.header-notif-dropdown{position:absolute;top:calc(100% + 10px);right:0;width:320px;background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,0.13);z-index:10000;overflow:hidden;border:1px solid rgba(0,0,0,0.07);}
.header-notif-dropdown__head{display:flex;justify-content:space-between;align-items:center;padding:13px 16px 11px;border-bottom:1px solid #f1f5f9;font-weight:600;font-size:0.88rem;}
.header-notif-dropdown__head button{background:none;border:none;cursor:pointer;font-size:0.75rem;color:#64748b;padding:4px 8px;border-radius:6px;transition:background .2s;}
.header-notif-dropdown__head button:hover{background:#f1f5f9;}
.header-notif-item{display:flex;gap:10px;padding:11px 16px;border-bottom:1px solid #f8fafc;font-size:0.82rem;text-decoration:none;color:inherit;transition:background .15s;}
.header-notif-item:hover{background:#f8fafc;}
.header-notif-item--unread{background:#f0fdf9;}
.header-notif-item__dot{width:7px;height:7px;border-radius:99px;background:#00715D;flex-shrink:0;margin-top:5px;}
.header-notif-item--read .header-notif-item__dot{background:#cbd5e1;}
.header-notif-item__text{flex:1;line-height:1.45;}
.header-notif-item__time{font-size:0.71rem;color:#94a3b8;margin-top:2px;}
.header-notif-empty{padding:24px;text-align:center;color:#94a3b8;font-size:0.84rem;}
.header-notif-viewall{display:block;text-align:center;padding:11px;font-size:0.82rem;font-weight:600;color:#00715D;text-decoration:none;border-top:1px solid #f1f5f9;transition:background .15s;}
.header-notif-viewall:hover{background:#f0fdf9;}
</style>
<script>
function toggleNotifDropdown(e){
  e.stopPropagation();
  var d=document.getElementById('header-notif-dropdown');
  if(!d)return;
  var opening=d.hidden;
  d.hidden=!opening;
  if(opening)headerLoadNotifications();
}
document.addEventListener('click',function(e){
  var wrap=document.getElementById('header-notif-wrap');
  if(wrap&&!wrap.contains(e.target)){var d=document.getElementById('header-notif-dropdown');if(d)d.hidden=true;}
});
function _notifTimeAgo(s){var diff=Math.floor((Date.now()-new Date(s))/1000);if(diff<60)return'tani';if(diff<3600)return Math.floor(diff/60)+'m';if(diff<86400)return Math.floor(diff/3600)+'o';return Math.floor(diff/86400)+'d';}
async function headerLoadNotifications(){
  var list=document.getElementById('header-notif-list');if(!list)return;
  list.innerHTML='<div class="header-notif-empty">Duke ngarkuar\u2026</div>';
  try{
    var res=await fetch('/TiranaSolidare/api/notifications.php?action=list&limit=6',{credentials:'same-origin'});
    var json=await res.json();
    if(!json.success||(json.data.notifications||[]).length===0){list.innerHTML='<div class="header-notif-empty">Nuk ka njoftime.</div>';return;}
    list.innerHTML=json.data.notifications.map(function(n){
      var link=n.linku?(n.linku):'#';
      var txt=(n.mesazhi||'').substring(0,90)+((n.mesazhi||'').length>90?'\u2026':'');
      return'<a href="'+link+'" class="header-notif-item '+(n.is_read?'header-notif-item--read':'header-notif-item--unread')+'" onclick="headerMarkNotifRead('+n.id_njoftimi+')">'
        +'<div class="header-notif-item__dot"></div>'
        +'<div class="header-notif-item__text">'+txt+'<div class="header-notif-item__time">'+_notifTimeAgo(n.krijuar_me)+'</div></div>'
        +'</a>';
    }).join('');
  }catch(e){list.innerHTML='<div class="header-notif-empty" style="color:#ef4444;">Gabim gjat\u00eb ngarkimit.</div>';}
}
async function headerMarkNotifRead(id){
  try{var csrf=document.querySelector('meta[name="csrf-token"]');await fetch('/TiranaSolidare/api/notifications.php?action=mark_read&id='+id,{method:'PUT',credentials:'same-origin',headers:{'X-CSRF-Token':csrf?csrf.content:''}});}catch(e){}
}
async function headerMarkAllRead(){
  try{
    var csrf=document.querySelector('meta[name="csrf-token"]');
    await fetch('/TiranaSolidare/api/notifications.php?action=mark_all_read',{method:'PUT',credentials:'same-origin',headers:{'X-CSRF-Token':csrf?csrf.content:''}});
    headerLoadNotifications();
    var b=document.getElementById('notif-badge');if(b)b.style.display='none';
  }catch(e){}
}
</script>

<script>
if ('serviceWorker' in navigator) {
  // Root-level sw.js has scope /TiranaSolidare/ so it covers both /public/ and /views/
  navigator.serviceWorker.register('/TiranaSolidare/sw.js');
}
</script>
