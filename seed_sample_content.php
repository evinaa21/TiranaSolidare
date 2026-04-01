<?php
require_once __DIR__ . '/config/db.php';

function quote_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function active_volunteer_ids(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id_perdoruesi FROM Perdoruesi WHERE roli = 'volunteer' AND statusi_llogarise = 'active' ORDER BY id_perdoruesi ASC");
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($ids) < 6) {
        throw new RuntimeException('At least 6 active volunteer users are required to seed request data.');
    }
    return $ids;
}

function insert_request(PDO $pdo, array $request): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO Kerkesa_per_Ndihme (id_perdoruesi, id_kategoria, tipi, titulli, pershkrimi, statusi, imazhi, vendndodhja, latitude, longitude)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $request['owner_id'],
        $request['tipi'],
        $request['titulli'],
        $request['pershkrimi'],
        $request['statusi'],
        $request['imazhi'],
        $request['vendndodhja'],
        $request['latitude'],
        $request['longitude'],
    ]);

    return (int) $pdo->lastInsertId();
}

function insert_request_application(PDO $pdo, int $requestId, int $userId, string $status): void
{
    $stmt = $pdo->prepare('INSERT INTO Aplikimi_Kerkese (id_kerkese_ndihme, id_perdoruesi, statusi) VALUES (?, ?, ?)');
    $stmt->execute([$requestId, $userId, $status]);
}

$volunteers = active_volunteer_ids($pdo);

$requests = [
    [
        'owner_id' => $volunteers[0],
        'tipi' => 'request',
        'titulli' => 'Ndihmë me pako ushqimore dhe produkte higjienike',
        'pershkrimi' => 'Jam prind i vetëm dhe aktualisht po mbaj vetëm të ardhurat e pjeshme të familjes. Për dy javët në vijim kemi nevojë për ndihmë me produkte bazë si vaj, makarona, qumësht, detergjent dhe artikuj higjienikë për fëmijët. Nëse dikush mund të ndihmojë me një pako të kombinuar ose me një pjesë të këtyre produkteve, do të ishte shumë e vlefshme për ne.',
        'statusi' => 'open',
        'imazhi' => 'https://images.unsplash.com/photo-1593113598332-cd288d649433?q=80&w=1200',
        'vendndodhja' => 'Astir, Tiranë',
        'latitude' => 41.3364000,
        'longitude' => 19.7828000,
    ],
    [
        'owner_id' => $volunteers[1],
        'tipi' => 'request',
        'titulli' => 'Kërkoj transport për kontrolle mjekësore javore',
        'pershkrimi' => 'Në familje kemi një të moshuar që duhet të paraqitet rregullisht për kontroll mjekësor dhe ekzaminime. Transporti publik është i vështirë për shkak të gjendjes fizike dhe nuk kemi mundësi ta përballojmë taksinë çdo javë. Kërkojmë ndihmë për transport vajtje-ardhje të paktën një herë në javë gjatë këtij muaji.',
        'statusi' => 'open',
        'imazhi' => 'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?q=80&w=1200',
        'vendndodhja' => 'Ali Demi, Tiranë',
        'latitude' => 41.3242000,
        'longitude' => 19.8405000,
    ],
    [
        'owner_id' => $volunteers[2],
        'tipi' => 'offer',
        'titulli' => 'Ofroj ndihmë për mësime bazë kompjuteri',
        'pershkrimi' => 'Jam i disponueshëm të ndihmoj të moshuarit ose të rinjtë që kanë nevojë për orientim bazë në përdorimin e kompjuterit dhe telefonit. Mund të shpjegoj përdorimin e email-it, dokumenteve, aplikacioneve të komunikimit dhe kërkimeve bazë në internet. Mund të organizoj takime të vogla në grup ose ndihmë individuale një herë në javë.',
        'statusi' => 'open',
        'imazhi' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=1200',
        'vendndodhja' => 'Qendra TEN, Tiranë',
        'latitude' => 41.3277000,
        'longitude' => 19.8186000,
    ],
    [
        'owner_id' => $volunteers[3],
        'tipi' => 'offer',
        'titulli' => 'Ofroj mjete dhe kohë për pastrim komunitar',
        'pershkrimi' => 'Kam në dispozicion disa mjete bazë për pastrim si doreza, thasë të mëdhenj, lopata dhe materiale të tjera të vogla pune. Përveç pajisjeve, mund të angazhohem personalisht për disa orë gjatë fundjavës në çdo nismë të lagjes. Nëse ka një grup që organizon aksion pastrimi, mund të bashkohem dhe të ndihmoj me logjistikën në terren.',
        'statusi' => 'open',
        'imazhi' => 'https://images.unsplash.com/photo-1500828131278-8de6878641b8?q=80&w=1200',
        'vendndodhja' => 'Yzberisht, Tiranë',
        'latitude' => 41.3394000,
        'longitude' => 19.7924000,
    ],
    [
        'owner_id' => $volunteers[4],
        'tipi' => 'request',
        'titulli' => 'Furnizime shkollore për tre fëmijë',
        'pershkrimi' => 'Familja kishte nevojë për çanta, fletore, lapsa, stilolapsa, blloqe vizatimi dhe mjete të tjera bazë për fillimin e periudhës mësimore. Gjendja ekonomike nuk e lejonte blerjen e plotë të materialeve dhe fëmijët rrezikonin të nisnin shkollën pa pajisjet e domosdoshme. Kërkesa u mbyll pasi një vullnetar siguroi furnizimet e nevojshme dhe dorëzimi u krye me sukses.',
        'statusi' => 'closed',
        'imazhi' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=1200',
        'vendndodhja' => 'Kamëz, Tiranë',
        'latitude' => 41.3811000,
        'longitude' => 19.7603000,
        'approved_user_id' => $volunteers[0],
    ],
    [
        'owner_id' => $volunteers[5],
        'tipi' => 'request',
        'titulli' => 'Ndihmë pas përmbytjes në banesë',
        'pershkrimi' => 'Pas reshjeve të forta, banesa pësoi dëmtime në katin përdhes dhe u desh pastrim i menjëhershëm i ambientit, largim i ujit dhe sistemim i sendeve më të domosdoshme. Familja nuk kishte fuqi punëtore dhe mjetet e nevojshme për ta përballuar situatën brenda pak ditësh. Kërkesa u konsiderua e përfunduar pasi një grup vullnetarësh ndihmoi me pastrimin, transportimin e materialeve dhe sistemimin fillestar të shtëpisë.',
        'statusi' => 'closed',
        'imazhi' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?q=80&w=1200',
        'vendndodhja' => 'Kombinat, Tiranë',
        'latitude' => 41.3372000,
        'longitude' => 19.7707000,
        'approved_user_id' => $volunteers[2],
    ],
    [
        'owner_id' => $volunteers[1],
        'tipi' => 'request',
        'titulli' => 'Sigurimi i barnave mujore për një të moshuar',
        'pershkrimi' => 'Një i moshuar me trajtim të vazhdueshëm kishte mbetur pa një pjesë të barnave mujore dhe familja nuk arrinte t’i siguronte brenda afatit. Kjo kërkesë përfshinte jo vetëm blerjen e barnave, por edhe koordinimin me farmacitë për të gjetur alternativat e duhura sipas recetës. Pas ndërhyrjes së një vullnetari dhe mbështetjes së një farmacie partnere, barnat u siguruan dhe kërkesa u mbyll plotësisht.',
        'statusi' => 'closed',
        'imazhi' => 'https://images.unsplash.com/photo-1584515933487-779824d29309?q=80&w=1200',
        'vendndodhja' => 'Qytet Studenti, Tiranë',
        'latitude' => 41.3186000,
        'longitude' => 19.8293000,
        'approved_user_id' => $volunteers[3],
    ],
    [
        'owner_id' => $volunteers[2],
        'tipi' => 'request',
        'titulli' => 'Laptop funksional për vazhdimin e studimeve',
        'pershkrimi' => 'Kërkesa u hap për një student që nuk kishte pajisje personale për ndjekjen e detyrave, kërkimeve dhe materialeve të fakultetit. Mungesa e laptopit po e pengonte ndjeshëm për të përfunduar projektet dhe për të dorëzuar punimet në kohë. Situata u zgjidh pasi një vullnetar dhuroi një laptop funksional, u bë dorëzimi dhe studenti konfirmoi se pajisja po përdoret tashmë për studimet e përditshme.',
        'statusi' => 'closed',
        'imazhi' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?q=80&w=1200',
        'vendndodhja' => 'Tregu Elektrik, Tiranë',
        'latitude' => 41.3215000,
        'longitude' => 19.8364000,
        'approved_user_id' => $volunteers[4],
    ],
];

$tablesToClear = ['Aplikimi', 'Eventi', 'Aplikimi_Kerkese', 'Kerkesa_per_Ndihme', 'Kategoria'];

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tablesToClear as $tableName) {
        $pdo->exec('TRUNCATE TABLE ' . quote_identifier($tableName));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
} catch (Throwable $e) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $ignored) {
    }
    fwrite(STDERR, 'Reset failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$pdo->beginTransaction();
try {
    $requestCount = 0;
    $completedCount = 0;
    foreach ($requests as $request) {
        $requestId = insert_request($pdo, $request);
        $requestCount++;

        if (($request['statusi'] ?? '') === 'closed' && isset($request['approved_user_id'])) {
            insert_request_application($pdo, $requestId, $request['approved_user_id'], 'approved');
            $completedCount++;
        }
    }

    $pdo->commit();

    echo 'Seed complete.' . PHP_EOL;
    echo 'Events: 0' . PHP_EOL;
    echo 'Categories: 0' . PHP_EOL;
    echo 'Requests/Offers: ' . $requestCount . PHP_EOL;
    echo 'Completed requests seeded: ' . $completedCount . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
