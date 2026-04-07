<?php
declare(strict_types=1);

// CLI only — deny web access
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

// Resolve project root (one level up from migrations/)
define('SEED_PROJECT_ROOT', dirname(__DIR__));

require_once SEED_PROJECT_ROOT . '/config/db.php';

const SEED_PASSWORD = 'Demo123!';
const APP_PATH_PREFIX = '/TiranaSolidare';
const SEED_MEDIA_DIR = SEED_PROJECT_ROOT . '/public/assets/uploads/seed';
const SEED_MEDIA_URL = APP_PATH_PREFIX . '/public/assets/uploads/seed';
const SEED_AVATAR_DIR = SEED_PROJECT_ROOT . '/uploads/images/profiles/seed';
const SEED_AVATAR_URL = APP_PATH_PREFIX . '/uploads/images/profiles/seed';

function quote_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

    return $cache[$table] = (bool) $stmt->fetchColumn();
}

function table_columns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    if (!table_exists($pdo, $table)) {
        return $cache[$table] = [];
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM ' . quote_identifier($table));
    $columns = [];
    foreach ($stmt as $row) {
        $columns[] = (string) $row['Field'];
    }

    return $cache[$table] = $columns;
}

function has_column(PDO $pdo, string $table, string $column): bool
{
    return in_array($column, table_columns($pdo, $table), true);
}

function ensure_directory(string $dir): void
{
    if (is_dir($dir)) {
        return;
    }

    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create directory: ' . $dir);
    }
}

function delete_path(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        $items = scandir($path);
        if ($items === false) {
            throw new RuntimeException('Could not read directory: ' . $path);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            delete_path($path . DIRECTORY_SEPARATOR . $item);
        }

        if (!rmdir($path)) {
            throw new RuntimeException('Could not remove directory: ' . $path);
        }

        return;
    }

    if (file_exists($path) && !unlink($path)) {
        throw new RuntimeException('Could not remove file: ' . $path);
    }
}

function reset_seed_directory(string $dir): void
{
    if (is_dir($dir)) {
        $items = scandir($dir);
        if ($items === false) {
            throw new RuntimeException('Could not read seed directory: ' . $dir);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            delete_path($dir . DIRECTORY_SEPARATOR . $item);
        }
    }

    ensure_directory($dir);
}

function slugify(string $value): string
{
    $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : false;
    $normalized = is_string($ascii) ? $ascii : $value;
    $normalized = strtolower($normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : 'item';
}

function xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function image_extension_from_mime(?string $mime): string
{
    return match (strtolower((string) $mime)) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        default => 'jpg',
    };
}

function detect_image_mime(string $binary, ?string $contentType = null): ?string
{
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);
        if (is_string($mime) && str_starts_with(strtolower($mime), 'image/')) {
            return strtolower($mime);
        }
    }

    if ($contentType !== null && $contentType !== '') {
        $mime = strtolower(trim(strtok($contentType, ';')));
        if (str_starts_with($mime, 'image/')) {
            return $mime;
        }
    }

    return null;
}

function fetch_remote_image(string $url): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TiranaSolidare seed script',
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (is_string($body) && $body !== '' && $status >= 200 && $status < 300) {
            $mime = detect_image_mime($body, $contentType);
            if ($mime !== null) {
                return ['body' => $body, 'mime' => $mime];
            }
        }
    }

    if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) || ini_get('allow_url_fopen') === '1') {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 25,
                'header' => "User-Agent: TiranaSolidare seed script\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);

        if (is_string($body) && $body !== '') {
            $contentType = null;
            foreach ($http_response_header ?? [] as $header) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $contentType = trim(substr($header, 13));
                    break;
                }
            }

            $mime = detect_image_mime($body, $contentType);
            if ($mime !== null) {
                return ['body' => $body, 'mime' => $mime];
            }
        }
    }

    return null;
}

function build_fallback_svg(string $label, string $accent): string
{
    $parts = preg_split('/\s+/', trim($label)) ?: [];
    $lines = [];

    while ($parts !== []) {
        $lines[] = trim(implode(' ', array_splice($parts, 0, 3)));
    }

    if ($lines === []) {
        $lines[] = 'Tirana Solidare';
    }

    $text = '';
    $baseY = 290;
    foreach (array_slice($lines, 0, 3) as $index => $line) {
        $y = $baseY + ($index * 56);
        $text .= '<text x="80" y="' . $y . '" fill="#ffffff" font-family="Arial, sans-serif" font-size="42" font-weight="700">' . xml_escape($line) . '</text>';
    }

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" role="img" aria-label="{$label}">
  <defs>
    <linearGradient id="seedGradient" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$accent}" />
      <stop offset="100%" stop-color="#0f172a" />
    </linearGradient>
  </defs>
  <rect width="1200" height="800" fill="url(#seedGradient)" />
  <circle cx="1020" cy="160" r="180" fill="rgba(255,255,255,0.08)" />
  <circle cx="170" cy="690" r="230" fill="rgba(255,255,255,0.06)" />
  <rect x="72" y="90" rx="18" ry="18" width="240" height="44" fill="rgba(255,255,255,0.15)" />
  <text x="96" y="120" fill="#f8fafc" font-family="Arial, sans-serif" font-size="24" font-weight="700">Tirana Solidare</text>
  {$text}
</svg>
SVG;
}

function create_local_seed_image(
    string $baseDir,
    string $publicPrefix,
    string $relativeName,
    array $sourceUrls,
    string $label,
    string $accent,
    array &$stats
): string {
    $relativeName = str_replace('\\', '/', trim($relativeName, '/'));

    foreach ($sourceUrls as $sourceUrl) {
        $download = fetch_remote_image($sourceUrl);
        if ($download === null) {
            continue;
        }

        $extension = image_extension_from_mime($download['mime'] ?? null);
        $absolutePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeName . '.' . $extension);
        ensure_directory(dirname($absolutePath));

        if (file_put_contents($absolutePath, $download['body']) === false) {
            throw new RuntimeException('Could not write downloaded image: ' . $absolutePath);
        }

        $stats['downloaded']++;
        return rtrim($publicPrefix, '/') . '/' . $relativeName . '.' . $extension;
    }

    $absolutePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeName . '.svg');
    ensure_directory(dirname($absolutePath));
    if (file_put_contents($absolutePath, build_fallback_svg($label, $accent)) === false) {
        throw new RuntimeException('Could not write fallback SVG: ' . $absolutePath);
    }

    $stats['fallback']++;
    return rtrim($publicPrefix, '/') . '/' . $relativeName . '.svg';
}

function demo_password_hash(): string
{
    static $hash = null;

    if ($hash === null) {
        $hash = password_hash(SEED_PASSWORD, PASSWORD_DEFAULT);
    }

    return $hash;
}

function split_full_name(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    if ($parts === []) {
        return ['Demo', 'User'];
    }

    $firstName = array_shift($parts);
    $lastName = trim(implode(' ', $parts));

    return [$firstName !== '' ? $firstName : 'Demo', $lastName !== '' ? $lastName : 'User'];
}

function insert_row(PDO $pdo, string $table, array $data): int
{
    $columns = array_flip(table_columns($pdo, $table));
    $filtered = [];
    foreach ($data as $column => $value) {
        if (isset($columns[$column])) {
            $filtered[$column] = $value;
        }
    }

    if ($filtered === []) {
        throw new RuntimeException('No insertable columns found for table ' . $table . '.');
    }

    $columnNames = array_keys($filtered);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        quote_identifier($table),
        implode(', ', array_map('quote_identifier', $columnNames)),
        implode(', ', array_fill(0, count($columnNames), '?'))
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($filtered));

    return (int) $pdo->lastInsertId();
}

function upsert_demo_user(PDO $pdo, array $user): int
{
    [$firstName, $lastName] = split_full_name($user['emri']);

    $data = [
        'emri' => $user['emri'],
        'email' => $user['email'],
        'fjalekalimi' => demo_password_hash(),
        'roli' => $user['roli'],
        'statusi_llogarise' => 'active',
        'verified' => 1,
        'bio' => $user['bio'],
        'profile_picture' => $user['profile_picture'],
        'profile_public' => $user['profile_public'] ? 1 : 0,
        'profile_color' => $user['profile_color'],
        'email_notifications' => $user['email_notifications'] ? 1 : 0,
        'verification_token_hash' => null,
        'verification_token_expires' => null,
        'password_reset_token_hash' => null,
        'password_reset_token_expires' => null,
        'deaktivizuar_me' => null,
        'mbiemri' => $lastName,
        'profilePicture' => $user['profile_picture'],
    ];

    $stmt = $pdo->prepare('SELECT id_perdoruesi FROM Perdoruesi WHERE email = ? LIMIT 1');
    $stmt->execute([$user['email']]);
    $existingId = $stmt->fetchColumn();

    $available = array_flip(table_columns($pdo, 'Perdoruesi'));
    $filtered = [];
    foreach ($data as $column => $value) {
        if (isset($available[$column])) {
            $filtered[$column] = $value;
        }
    }

    if ($existingId !== false) {
        $assignments = [];
        foreach (array_keys($filtered) as $column) {
            $assignments[] = quote_identifier($column) . ' = ?';
        }

        $sql = 'UPDATE ' . quote_identifier('Perdoruesi') . ' SET ' . implode(', ', $assignments) . ' WHERE id_perdoruesi = ?';
        $update = $pdo->prepare($sql);
        $values = array_values($filtered);
        $values[] = (int) $existingId;
        $update->execute($values);

        return (int) $existingId;
    }

    return insert_row($pdo, 'Perdoruesi', $filtered);
}

function format_datetime(DateTimeImmutable $dateTime): string
{
    return $dateTime->format('Y-m-d H:i:s');
}

function reset_seed_tables(PDO $pdo, array $tables): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    try {
        foreach ($tables as $table) {
            if (table_exists($pdo, $table)) {
                $pdo->exec('TRUNCATE TABLE ' . quote_identifier($table));
            }
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

$now = new DateTimeImmutable('now');
$mediaStats = ['downloaded' => 0, 'fallback' => 0];

$demoUsers = [
    [
        'key' => 'admin',
        'emri' => 'Administrator Demo',
        'email' => 'demo.admin@tiranasolidare.local',
        'roli' => 'admin',
        'bio' => 'Koordinon eventet, kategoritë dhe raportet demo për platformën.',
        'profile_color' => 'emerald',
        'profile_public' => true,
        'email_notifications' => true,
        'accent' => '#0f766e',
        'avatar_sources' => ['https://randomuser.me/api/portraits/men/52.jpg'],
    ],
    [
        'key' => 'elira',
        'emri' => 'Elira Gjoni',
        'email' => 'demo.elira@tiranasolidare.local',
        'roli' => 'volunteer',
        'bio' => 'Vullnetare aktive në shpërndarje ushqimi dhe koordinim komunitar.',
        'profile_color' => 'rose',
        'profile_public' => true,
        'email_notifications' => true,
        'accent' => '#be185d',
        'avatar_sources' => ['https://randomuser.me/api/portraits/women/44.jpg'],
    ],
    [
        'key' => 'arber',
        'emri' => 'Arber Hoxha',
        'email' => 'demo.arber@tiranasolidare.local',
        'roli' => 'volunteer',
        'bio' => 'Ndihmon me logjistikë, transport dhe terren gjatë eventeve në qytet.',
        'profile_color' => 'ocean',
        'profile_public' => true,
        'email_notifications' => true,
        'accent' => '#1d4ed8',
        'avatar_sources' => ['https://randomuser.me/api/portraits/men/41.jpg'],
    ],
    [
        'key' => 'sara',
        'emri' => 'Sara Kola',
        'email' => 'demo.sara@tiranasolidare.local',
        'roli' => 'volunteer',
        'bio' => 'Angazhohet me fëmijët dhe me aktivitete edukative në lagje.',
        'profile_color' => 'pink',
        'profile_public' => true,
        'email_notifications' => true,
        'accent' => '#ec4899',
        'avatar_sources' => ['https://randomuser.me/api/portraits/women/65.jpg'],
    ],
    [
        'key' => 'leon',
        'emri' => 'Leon Tafa',
        'email' => 'demo.leon@tiranasolidare.local',
        'roli' => 'volunteer',
        'bio' => 'Merret me grumbullim donacionesh dhe organizim aktivitetesh sociale.',
        'profile_color' => 'amber',
        'profile_public' => true,
        'email_notifications' => true,
        'accent' => '#d97706',
        'avatar_sources' => ['https://randomuser.me/api/portraits/men/32.jpg'],
    ],
    [
        'key' => 'ina',
        'emri' => 'Ina Muca',
        'email' => 'demo.ina@tiranasolidare.local',
        'roli' => 'volunteer',
        'bio' => 'Koordinon vizita sociale dhe ndihmë për familje në nevojë.',
        'profile_color' => 'teal',
        'profile_public' => true,
        'email_notifications' => false,
        'accent' => '#0d9488',
        'avatar_sources' => ['https://randomuser.me/api/portraits/women/26.jpg'],
    ],
    [
        'key' => 'klodi',
        'emri' => 'Klodi Lila',
        'email' => 'demo.klodi@tiranasolidare.local',
        'roli' => 'volunteer',
        'bio' => 'Mbështet aksionet mjedisore dhe punët praktike në terren.',
        'profile_color' => 'indigo',
        'profile_public' => true,
        'email_notifications' => true,
        'accent' => '#4f46e5',
        'avatar_sources' => ['https://randomuser.me/api/portraits/men/64.jpg'],
    ],
    [
        'key' => 'ena',
        'emri' => 'Ena Shyti',
        'email' => 'demo.ena@tiranasolidare.local',
        'roli' => 'volunteer',
        'bio' => 'Punon me orientim arsimor dhe mbështetje digjitale për të rinjtë.',
        'profile_color' => 'cyan',
        'profile_public' => true,
        'email_notifications' => true,
        'accent' => '#0891b2',
        'avatar_sources' => ['https://randomuser.me/api/portraits/women/31.jpg'],
    ],
    [
        'key' => 'noel',
        'emri' => 'Noel Kasmi',
        'email' => 'demo.noel@tiranasolidare.local',
        'roli' => 'volunteer',
        'bio' => 'Mbulon nevoja urgjente, transport dhe ndihmë mjekësore bazë.',
        'profile_color' => 'lime',
        'profile_public' => true,
        'email_notifications' => true,
        'accent' => '#84cc16',
        'avatar_sources' => ['https://randomuser.me/api/portraits/men/72.jpg'],
    ],
];

$categories = [
    [
        'key' => 'mjedis',
        'emri' => 'Mjedis',
        'accent' => '#0f766e',
        'banner_sources' => ['https://images.unsplash.com/photo-1542601906990-b4d3fb773b09?q=80&w=1200'],
    ],
    [
        'key' => 'sociale',
        'emri' => 'Sociale',
        'accent' => '#d97706',
        'banner_sources' => ['https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?q=80&w=1200'],
    ],
    [
        'key' => 'edukimi',
        'emri' => 'Edukimi',
        'accent' => '#2563eb',
        'banner_sources' => ['https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=1200'],
    ],
    [
        'key' => 'shendetesi',
        'emri' => 'Shëndetësi',
        'accent' => '#dc2626',
        'banner_sources' => ['https://images.unsplash.com/photo-1584515933487-779824d29309?q=80&w=1200'],
    ],
    [
        'key' => 'emergjenca',
        'emri' => 'Emergjenca',
        'accent' => '#7c3aed',
        'banner_sources' => ['https://images.unsplash.com/photo-1547683905-f686c993aae5?q=80&w=1200'],
    ],
];

$events = [
    [
        'key' => 'web-challenge',
        'owner_key' => 'admin',
        'category_key' => 'edukimi',
        'titulli' => 'Përgatitja e skenës për konkursin Web Challenge',
        'pershkrimi' => 'Kërkojmë 12 vullnetarë për montimin e skenës, sistemimin e sallës, vendosjen e materialeve vizuale dhe orientimin e ekipeve pjesëmarrëse për konkursin Web Challenge në Tiranë. Aktiviteti zhvillohet në bashkëpunim me ekipin organizator dhe koordinatorët e bashkisë.',
        'data' => format_datetime($now->modify('+1 day')->setTime(9, 0)),
        'vendndodhja' => 'Piramida e Tiranës, Tiranë',
        'latitude' => 41.3270,
        'longitude' => 19.8213,
        'kapaciteti' => 12,
        'statusi' => 'active',
        'banner_sources' => ['https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80'],
    ],
    [
        'key' => 'lake-cleanup',
        'owner_key' => 'admin',
        'category_key' => 'mjedis',
        'titulli' => 'Pastrimi i Liqenit Artificial',
        'pershkrimi' => 'Bashkohemi për një aksion pastrimi rreth liqenit me ndarje në skuadra për mbetje, orientim dhe ndërgjegjësim. Dorezat dhe materialet do të jenë gati në pikën e takimit.',
        'data' => format_datetime($now->modify('+6 days')->setTime(9, 0)),
        'vendndodhja' => 'Liqeni Artificial, Tiranë',
        'latitude' => 41.3133,
        'longitude' => 19.8195,
        'kapaciteti' => 2,
        'statusi' => 'active',
        'banner_sources' => ['https://images.unsplash.com/photo-1618477462146-050d2767eac4?q=80&w=1200'],
    ],
    [
        'key' => 'food-distribution',
        'owner_key' => 'admin',
        'category_key' => 'sociale',
        'titulli' => 'Shpërndarja e Ushqimit në Laprakë',
        'pershkrimi' => 'Kjo dalje fokusohet te paketimi dhe dorëzimi i ndihmave ushqimore për familjet e identifikuara nga rrjeti lokal i mbështetjes. Kemi nevojë për ekip në magazinë dhe ekip në terren.',
        'data' => format_datetime($now->modify('+4 days')->setTime(10, 30)),
        'vendndodhja' => 'Laprakë, Tiranë',
        'latitude' => 41.3422,
        'longitude' => 19.7919,
        'kapaciteti' => 3,
        'statusi' => 'active',
        'banner_sources' => ['https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?q=80&w=1200'],
    ],
    [
        'key' => 'study-lab',
        'owner_key' => 'admin',
        'category_key' => 'edukimi',
        'titulli' => 'Laborator Studimi për Nxënësit',
        'pershkrimi' => 'Një seancë mbështetëse për nxënësit e ciklit 9-vjeçar me fokus te matematika, shkenca dhe organizimi i detyrave. Vullnetarët do të ndihmojnë me ushtrime dhe orientim individual.',
        'data' => format_datetime($now->modify('+9 days')->setTime(15, 0)),
        'vendndodhja' => 'Biblioteka Kombëtare, Tiranë',
        'latitude' => 41.3265,
        'longitude' => 19.8195,
        'kapaciteti' => 4,
        'statusi' => 'active',
        'banner_sources' => ['https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=1200'],
    ],
    [
        'key' => 'medical-check',
        'owner_key' => 'admin',
        'category_key' => 'shendetesi',
        'titulli' => 'Kontroll Mjekësor Falas për të Moshuarit',
        'pershkrimi' => 'Në bashkëpunim me partnerë lokalë po organizojmë orientim, pritje dhe asistencë për një ditë kontrollesh bazë falas për të moshuarit e zonës së Kombinatit.',
        'data' => format_datetime($now->modify('+11 days')->setTime(9, 30)),
        'vendndodhja' => 'Kombinat, Tiranë',
        'latitude' => 41.3372,
        'longitude' => 19.7707,
        'kapaciteti' => 2,
        'statusi' => 'active',
        'banner_sources' => ['https://images.unsplash.com/photo-1576091160550-2173dba999ef?q=80&w=1200'],
    ],
    [
        'key' => 'winter-drive',
        'owner_key' => 'admin',
        'category_key' => 'sociale',
        'titulli' => 'Mbledhje Veshjesh për Dimrin',
        'pershkrimi' => 'Aktivitet i mbyllur me sukses ku u mblodhën, u ndanë dhe u paketuan veshje dimri për familjet me fëmijë në nevojë. U desh mbështetje me klasifikim dhe sistemim logjistik.',
        'data' => format_datetime($now->modify('-6 days')->setTime(10, 0)),
        'vendndodhja' => 'Qendra Sociale, Tiranë',
        'latitude' => 41.3275,
        'longitude' => 19.8187,
        'kapaciteti' => 3,
        'statusi' => 'completed',
        'banner_sources' => ['https://images.unsplash.com/photo-1489710437720-ebb67ec84dd2?q=80&w=1200'],
    ],
    [
        'key' => 'flood-response',
        'owner_key' => 'admin',
        'category_key' => 'emergjenca',
        'titulli' => 'Ndihmë Logjistike pas Reshjeve në Kombinat',
        'pershkrimi' => 'Po përgatisim skuadra të vogla për sistemim të materialeve, shpërndarje pakosh emergjente dhe koordinim me familjet e prekura nga reshjet e fundit.',
        'data' => format_datetime($now->modify('+2 days')->setTime(8, 0)),
        'vendndodhja' => 'Kombinat, Tiranë',
        'latitude' => 41.3372,
        'longitude' => 19.7707,
        'kapaciteti' => 3,
        'statusi' => 'active',
        'banner_sources' => ['https://images.unsplash.com/photo-1504307651254-35680f356dfd?q=80&w=1200'],
    ],
    [
        'key' => 'coding-workshop',
        'owner_key' => 'admin',
        'category_key' => 'edukimi',
        'titulli' => 'Workshop Kodimi për të Rinjtë',
        'pershkrimi' => 'Një sesion hyrës në programim për të rinjtë 15-25 vjeç. Vullnetarët do të ndihmojnë me ushtrimet bazë, orientimin e pjesëmarrësve dhe ndjekjen e ritmit të klasës.',
        'data' => format_datetime($now->modify('+13 days')->setTime(16, 0)),
        'vendndodhja' => 'Innovation Hub, Tiranë',
        'latitude' => 41.3285,
        'longitude' => 19.8180,
        'kapaciteti' => 2,
        'statusi' => 'active',
        'banner_sources' => ['https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=1200'],
    ],
];

$eventApplications = [
    ['event_key' => 'lake-cleanup', 'user_key' => 'elira', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'lake-cleanup', 'user_key' => 'arber', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'lake-cleanup', 'user_key' => 'sara', 'statusi' => 'pending', 'ne_liste_pritje' => 1],
    ['event_key' => 'food-distribution', 'user_key' => 'leon', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'food-distribution', 'user_key' => 'ina', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'food-distribution', 'user_key' => 'klodi', 'statusi' => 'pending', 'ne_liste_pritje' => 0],
    ['event_key' => 'study-lab', 'user_key' => 'sara', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'study-lab', 'user_key' => 'ena', 'statusi' => 'pending', 'ne_liste_pritje' => 0],
    ['event_key' => 'medical-check', 'user_key' => 'noel', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'medical-check', 'user_key' => 'elira', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'winter-drive', 'user_key' => 'arber', 'statusi' => 'present', 'ne_liste_pritje' => 0],
    ['event_key' => 'winter-drive', 'user_key' => 'ina', 'statusi' => 'present', 'ne_liste_pritje' => 0],
    ['event_key' => 'winter-drive', 'user_key' => 'klodi', 'statusi' => 'absent', 'ne_liste_pritje' => 0],
    ['event_key' => 'flood-response', 'user_key' => 'sara', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'flood-response', 'user_key' => 'leon', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'flood-response', 'user_key' => 'noel', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'flood-response', 'user_key' => 'elira', 'statusi' => 'pending', 'ne_liste_pritje' => 1],
    ['event_key' => 'coding-workshop', 'user_key' => 'klodi', 'statusi' => 'approved', 'ne_liste_pritje' => 0],
    ['event_key' => 'coding-workshop', 'user_key' => 'ena', 'statusi' => 'pending', 'ne_liste_pritje' => 0],
];

$requests = [
    [
        'key' => 'food-parcel-astir',
        'owner_key' => 'elira',
        'category_key' => 'sociale',
        'tipi' => 'request',
        'titulli' => 'Pako ushqimore për një familje me tre fëmijë',
        'pershkrimi' => 'Familja ka nevojë për artikuj bazë ushqimorë dhe disa produkte higjienike për javën në vijim. Çdo mbështetje me paketim ose dorëzim do të ishte shumë e vlefshme.',
        'statusi' => 'open',
        'matching_mode' => 'limited',
        'capacity_total' => 3,
        'vendndodhja' => 'Astir, Tiranë',
        'latitude' => 41.3364,
        'longitude' => 19.7828,
        'completed_at' => null,
        'moderation_status' => 'approved',
        'image_sources' => ['https://images.unsplash.com/photo-1593113598332-cd288d649433?q=80&w=1200'],
        'accent' => '#d97706',
    ],
    [
        'key' => 'medical-transport',
        'owner_key' => 'arber',
        'category_key' => 'shendetesi',
        'tipi' => 'request',
        'titulli' => 'Transport javor për kontrolle mjekësore',
        'pershkrimi' => 'Një i moshuar ka nevojë për transport vajtje-ardhje një herë në javë për kontrolle të rregullta. Kërkojmë dikë të disponueshëm për koordinim dhe shoqërim.',
        'statusi' => 'open',
        'matching_mode' => 'single',
        'capacity_total' => 1,
        'vendndodhja' => 'Ali Demi, Tiranë',
        'latitude' => 41.3242,
        'longitude' => 19.8405,
        'completed_at' => null,
        'moderation_status' => 'approved',
        'image_sources' => ['https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?q=80&w=1200'],
        'accent' => '#dc2626',
    ],
    [
        'key' => 'laptop-for-university',
        'owner_key' => 'sara',
        'category_key' => 'edukimi',
        'tipi' => 'request',
        'titulli' => 'Laptop funksional për vazhdimin e studimeve',
        'pershkrimi' => 'Studenti ka nevojë për një laptop funksional për ndjekjen e projekteve dhe dorëzimin e materialeve universitare. Kërkesa ka hyrë në fazën e përputhjes finale.',
        'statusi' => 'filled',
        'matching_mode' => 'single',
        'capacity_total' => 1,
        'vendndodhja' => 'Tregu Elektrik, Tiranë',
        'latitude' => 41.3215,
        'longitude' => 19.8364,
        'completed_at' => null,
        'moderation_status' => 'approved',
        'image_sources' => ['https://images.unsplash.com/photo-1496181133206-80ce9b88a853?q=80&w=1200'],
        'accent' => '#2563eb',
    ],
    [
        'key' => 'math-tutoring-offer',
        'owner_key' => 'ina',
        'category_key' => 'edukimi',
        'tipi' => 'offer',
        'titulli' => 'Ofroj orë mbështetëse në matematikë',
        'pershkrimi' => 'Mund të ofroj dy pasdite në javë për mbështetje me ushtrime bazë në matematikë dhe organizim detyrash për nxënës të ciklit 9-vjeçar.',
        'statusi' => 'open',
        'matching_mode' => 'open',
        'capacity_total' => null,
        'vendndodhja' => 'Komuna e Parisit, Tiranë',
        'latitude' => 41.3204,
        'longitude' => 19.7998,
        'completed_at' => null,
        'moderation_status' => 'approved',
        'image_sources' => ['https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=1200'],
        'accent' => '#1d4ed8',
    ],
    [
        'key' => 'school-supplies',
        'owner_key' => 'klodi',
        'category_key' => 'sociale',
        'tipi' => 'request',
        'titulli' => 'Furnizime shkollore për tre nxënës',
        'pershkrimi' => 'Kërkesa u përmbush me sukses pas koordinimit me dy vullnetarë që siguruan çanta, fletore dhe materiale bazë për fillimin e muajit mësimor.',
        'statusi' => 'completed',
        'matching_mode' => 'limited',
        'capacity_total' => 2,
        'vendndodhja' => 'Kamëz, Tiranë',
        'latitude' => 41.3811,
        'longitude' => 19.7603,
        'completed_at' => format_datetime($now->modify('-9 days')->setTime(17, 15)),
        'moderation_status' => 'approved',
        'image_sources' => ['https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=1200'],
        'accent' => '#f59e0b',
    ],
    [
        'key' => 'flood-cleanup-home',
        'owner_key' => 'ena',
        'category_key' => 'emergjenca',
        'tipi' => 'request',
        'titulli' => 'Ndihmë për pastrim pas reshjeve në banesë',
        'pershkrimi' => 'Pas reshjeve të forta u mobilizua një grup i vogël vullnetarësh për pastrim, largim materialesh dhe sistemim fillestar të ambientit. Rasti u mbyll me sukses.',
        'statusi' => 'completed',
        'matching_mode' => 'limited',
        'capacity_total' => 3,
        'vendndodhja' => 'Kombinat, Tiranë',
        'latitude' => 41.3372,
        'longitude' => 19.7707,
        'completed_at' => format_datetime($now->modify('-4 days')->setTime(18, 0)),
        'moderation_status' => 'approved',
        'image_sources' => ['https://images.unsplash.com/photo-1504307651254-35680f356dfd?q=80&w=1200'],
        'accent' => '#7c3aed',
    ],
    [
        'key' => 'elderly-visits-offer',
        'owner_key' => 'noel',
        'category_key' => 'sociale',
        'tipi' => 'offer',
        'titulli' => 'Ofroj vizita javore për të moshuar që jetojnë vetëm',
        'pershkrimi' => 'Jam i gatshëm për vizita shoqëruese, blerje të vogla dhe ndihmë me orientim digjital për të moshuarit e lagjes. Aktualisht oferta ka kapacitet të mbushur.',
        'statusi' => 'filled',
        'matching_mode' => 'limited',
        'capacity_total' => 2,
        'vendndodhja' => 'Brryl, Tiranë',
        'latitude' => 41.3338,
        'longitude' => 19.8333,
        'completed_at' => null,
        'moderation_status' => 'approved',
        'image_sources' => ['https://images.unsplash.com/photo-1516302752625-fcc3c50ae61f?q=80&w=1200'],
        'accent' => '#f97316',
    ],
    [
        'key' => 'cleanup-tools-offer',
        'owner_key' => 'leon',
        'category_key' => 'mjedis',
        'tipi' => 'offer',
        'titulli' => 'Ofroj mjete për aksione pastrimi komunitar',
        'pershkrimi' => 'Kam në dispozicion doreza, thasë dhe disa mjete bazë terreni për aksione pastrimi ose sistemim të shpejtë në lagje.',
        'statusi' => 'open',
        'matching_mode' => 'open',
        'capacity_total' => null,
        'vendndodhja' => 'Yzberisht, Tiranë',
        'latitude' => 41.3394,
        'longitude' => 19.7924,
        'completed_at' => null,
        'moderation_status' => 'approved',
        'image_sources' => ['https://images.unsplash.com/photo-1500828131278-8de6878641b8?q=80&w=1200'],
        'accent' => '#0f766e',
    ],
];

$requestApplications = [
    ['request_key' => 'food-parcel-astir', 'user_key' => 'arber', 'statusi' => 'approved'],
    ['request_key' => 'food-parcel-astir', 'user_key' => 'sara', 'statusi' => 'pending'],
    ['request_key' => 'medical-transport', 'user_key' => 'noel', 'statusi' => 'pending'],
    ['request_key' => 'laptop-for-university', 'user_key' => 'klodi', 'statusi' => 'approved'],
    ['request_key' => 'laptop-for-university', 'user_key' => 'ena', 'statusi' => 'waitlisted'],
    ['request_key' => 'math-tutoring-offer', 'user_key' => 'elira', 'statusi' => 'pending'],
    ['request_key' => 'school-supplies', 'user_key' => 'leon', 'statusi' => 'completed'],
    ['request_key' => 'school-supplies', 'user_key' => 'ina', 'statusi' => 'completed'],
    ['request_key' => 'school-supplies', 'user_key' => 'klodi', 'statusi' => 'rejected'],
    ['request_key' => 'flood-cleanup-home', 'user_key' => 'arber', 'statusi' => 'completed'],
    ['request_key' => 'flood-cleanup-home', 'user_key' => 'sara', 'statusi' => 'completed'],
    ['request_key' => 'flood-cleanup-home', 'user_key' => 'elira', 'statusi' => 'rejected'],
    ['request_key' => 'elderly-visits-offer', 'user_key' => 'ina', 'statusi' => 'approved'],
    ['request_key' => 'elderly-visits-offer', 'user_key' => 'noel', 'statusi' => 'approved'],
    ['request_key' => 'elderly-visits-offer', 'user_key' => 'klodi', 'statusi' => 'waitlisted'],
];

reset_seed_directory(SEED_MEDIA_DIR);
reset_seed_directory(SEED_AVATAR_DIR);

foreach ($demoUsers as &$user) {
    $user['profile_picture'] = create_local_seed_image(
        SEED_AVATAR_DIR,
        SEED_AVATAR_URL,
        slugify($user['key']),
        $user['avatar_sources'],
        $user['emri'],
        $user['accent'],
        $mediaStats
    );
}
unset($user);

foreach ($categories as &$category) {
    $category['banner_path'] = create_local_seed_image(
        SEED_MEDIA_DIR,
        SEED_MEDIA_URL,
        'categories/' . slugify($category['key']),
        $category['banner_sources'],
        $category['emri'],
        $category['accent'],
        $mediaStats
    );
}
unset($category);

foreach ($events as &$event) {
    $event['banner'] = create_local_seed_image(
        SEED_MEDIA_DIR,
        SEED_MEDIA_URL,
        'events/' . slugify($event['key']),
        $event['banner_sources'],
        $event['titulli'],
        '#0f172a',
        $mediaStats
    );
}
unset($event);

foreach ($requests as &$request) {
    $request['imazhi'] = create_local_seed_image(
        SEED_MEDIA_DIR,
        SEED_MEDIA_URL,
        'requests/' . slugify($request['key']),
        $request['image_sources'],
        $request['titulli'],
        $request['accent'],
        $mediaStats
    );
}
unset($request);

$tablesToClear = ['Aplikimi', 'Eventi', 'Aplikimi_Kerkese', 'Kerkesa_per_Ndihme', 'Kategoria', 'Njoftimi', 'Raporti'];

try {
    reset_seed_tables($pdo, $tablesToClear);

    $pdo->beginTransaction();

    $userIds = [];
    foreach ($demoUsers as $user) {
        $userIds[$user['key']] = upsert_demo_user($pdo, $user);
    }

    $categoryIds = [];
    foreach ($categories as $category) {
        $categoryIds[$category['key']] = insert_row($pdo, 'Kategoria', [
            'emri' => $category['emri'],
            'banner_path' => $category['banner_path'],
        ]);
    }

    $eventIds = [];
    foreach ($events as $event) {
        $eventIds[$event['key']] = insert_row($pdo, 'Eventi', [
            'id_perdoruesi' => $userIds[$event['owner_key']],
            'id_kategoria' => $categoryIds[$event['category_key']] ?? null,
            'titulli' => $event['titulli'],
            'pershkrimi' => $event['pershkrimi'],
            'kapaciteti' => $event['kapaciteti'],
            'data' => $event['data'],
            'vendndodhja' => $event['vendndodhja'],
            'latitude' => $event['latitude'],
            'longitude' => $event['longitude'],
            'banner' => $event['banner'],
            'statusi' => $event['statusi'],
            'is_archived' => 0,
        ]);
    }

    foreach ($eventApplications as $application) {
        insert_row($pdo, 'Aplikimi', [
            'id_perdoruesi' => $userIds[$application['user_key']],
            'id_eventi' => $eventIds[$application['event_key']],
            'statusi' => $application['statusi'],
            'ne_liste_pritje' => $application['ne_liste_pritje'],
        ]);
    }

    $requestIds = [];
    foreach ($requests as $request) {
        $requestIds[$request['key']] = insert_row($pdo, 'Kerkesa_per_Ndihme', [
            'id_perdoruesi' => $userIds[$request['owner_key']],
            'id_kategoria' => $categoryIds[$request['category_key']] ?? null,
            'tipi' => $request['tipi'],
            'titulli' => $request['titulli'],
            'pershkrimi' => $request['pershkrimi'],
            'statusi' => $request['statusi'],
            'moderation_status' => $request['moderation_status'],
            'imazhi' => $request['imazhi'],
            'vendndodhja' => $request['vendndodhja'],
            'latitude' => $request['latitude'],
            'longitude' => $request['longitude'],
            'matching_mode' => $request['matching_mode'],
            'capacity_total' => $request['capacity_total'],
            'completed_at' => $request['completed_at'],
            'cancelled_at' => null,
            'closed_reason' => null,
        ]);
    }

    foreach ($requestApplications as $application) {
        insert_row($pdo, 'Aplikimi_Kerkese', [
            'id_kerkese_ndihme' => $requestIds[$application['request_key']],
            'id_perdoruesi' => $userIds[$application['user_key']],
            'statusi' => $application['statusi'],
        ]);
    }

    $reports = [
        [
            'id_perdoruesi' => $userIds['admin'],
            'tipi_raportit' => 'Mujor',
            'permbajtja' => 'Përmbledhje demo: ' . count($events) . ' evente, ' . count($eventApplications) . ' aplikime eventesh dhe ' . count($requests) . ' postime ndihme/oferte të ngarkuara me media lokale.',
        ],
        [
            'id_perdoruesi' => $userIds['admin'],
            'tipi_raportit' => 'Statistikë',
            'permbajtja' => 'Statistika demo: ' . count($demoUsers) . ' përdorues demo aktivë, ' . count($categories) . ' kategori me banner, ' . count($requestApplications) . ' aplikime për postime ndihme.',
        ],
    ];

    foreach ($reports as $report) {
        insert_row($pdo, 'Raporti', $report);
    }

    $notifications = [
        [
            'id_perdoruesi' => $userIds['sara'],
            'mesazhi' => 'Jeni në listën e pritjes për eventin "Pastrimi i Liqenit Artificial".',
            'is_read' => 0,
            'tipi' => 'aplikim_event',
            'target_type' => 'event',
            'target_id' => $eventIds['lake-cleanup'],
            'linku' => '/TiranaSolidare/views/events.php?id=' . $eventIds['lake-cleanup'],
        ],
        [
            'id_perdoruesi' => $userIds['arber'],
            'mesazhi' => 'Aplikimi juaj për postimin "Pako ushqimore për një familje me tre fëmijë" është miratuar.',
            'is_read' => 0,
            'tipi' => 'aplikim_kerkese',
            'target_type' => 'help_request',
            'target_id' => $requestIds['food-parcel-astir'],
            'linku' => '/TiranaSolidare/views/help_requests.php?id=' . $requestIds['food-parcel-astir'],
        ],
        [
            'id_perdoruesi' => $userIds['admin'],
            'mesazhi' => 'Ka aplikime të reja në eventet aktive të javës demo.',
            'is_read' => 0,
            'tipi' => 'admin_veprim',
            'target_type' => 'event',
            'target_id' => $eventIds['flood-response'],
            'linku' => '/TiranaSolidare/views/dashboard.php#events',
        ],
        [
            'id_perdoruesi' => $userIds['ena'],
            'mesazhi' => 'Laptopi për studime ka arritur kapacitetin aktual dhe ju jeni në listën e pritjes.',
            'is_read' => 0,
            'tipi' => 'aplikim_kerkese',
            'target_type' => 'help_request',
            'target_id' => $requestIds['laptop-for-university'],
            'linku' => '/TiranaSolidare/views/help_requests.php?id=' . $requestIds['laptop-for-university'],
        ],
        [
            'id_perdoruesi' => $userIds['klodi'],
            'mesazhi' => 'Keni një vend të rezervuar për workshop-in e kodimit javën e ardhshme.',
            'is_read' => 1,
            'tipi' => 'aplikim_event',
            'target_type' => 'event',
            'target_id' => $eventIds['coding-workshop'],
            'linku' => '/TiranaSolidare/views/events.php?id=' . $eventIds['coding-workshop'],
        ],
    ];

    foreach ($notifications as $notification) {
        insert_row($pdo, 'Njoftimi', $notification);
    }

    $pdo->commit();

    echo 'Seed complete.' . PHP_EOL;
    echo 'Users: ' . count($demoUsers) . PHP_EOL;
    echo 'Categories: ' . count($categories) . PHP_EOL;
    echo 'Events: ' . count($events) . PHP_EOL;
    echo 'Event applications: ' . count($eventApplications) . PHP_EOL;
    echo 'Requests/Offers: ' . count($requests) . PHP_EOL;
    echo 'Request applications: ' . count($requestApplications) . PHP_EOL;
    echo 'Notifications: ' . count($notifications) . PHP_EOL;
    echo 'Reports: ' . count($reports) . PHP_EOL;
    echo 'Media downloaded: ' . $mediaStats['downloaded'] . PHP_EOL;
    echo 'SVG fallbacks: ' . $mediaStats['fallback'] . PHP_EOL;
    echo PHP_EOL . 'Demo credentials:' . PHP_EOL;
    echo '- Shared password: ' . SEED_PASSWORD . PHP_EOL;
    echo '- Admin: demo.admin@tiranasolidare.local' . PHP_EOL;
    echo '- Volunteers: demo.elira@tiranasolidare.local, demo.arber@tiranasolidare.local, demo.sara@tiranasolidare.local, demo.leon@tiranasolidare.local, demo.ina@tiranasolidare.local, demo.klodi@tiranasolidare.local, demo.ena@tiranasolidare.local, demo.noel@tiranasolidare.local' . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
