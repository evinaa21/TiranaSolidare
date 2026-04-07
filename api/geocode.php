<?php
/**
 * api/geocode.php
 * ---------------------------------------------------
 * Same-origin geocoding proxy for Nominatim.
 * Avoids browser-side CORS/rate-limit ambiguity and gives
 * the frontend a stable JSON contract.
 *
 * GET ?action=search&q=<query>
 * GET ?action=reverse&lat=<lat>&lon=<lon>
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

require_method('GET');
release_session();

$action = $_GET['action'] ?? 'search';

function ts_geocode_http_json(string $url): array
{
    $siteUrl = app_base_url();
    $headers = [
        'Accept: application/json',
        'Accept-Language: sq,en',
        'User-Agent: TiranaSolidare/1.0 (+' . $siteUrl . ')',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            throw new RuntimeException($error ?: ('HTTP ' . $status));
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers) . "\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Nuk u arrit shërbimi i hartës.');
        }

        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $match) && (int) $match[1] >= 400) {
            throw new RuntimeException('HTTP ' . $match[1]);
        }
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Përgjigje e pavlefshme nga shërbimi i hartës.');
    }

    return $json;
}

function ts_geocode_shorten_label(string $displayName): string
{
    $parts = array_values(array_filter(array_map('trim', explode(',', $displayName))));
    return implode(', ', array_slice($parts, 0, 4));
}

try {
    switch ($action) {
        case 'search':
            $query = trim((string) ($_GET['q'] ?? ''));
            $length = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);

            if ($length < 3) {
                json_success(['results' => []]);
            }

            if ($length > 180) {
                json_error('Kërkimi është shumë i gjatë.', 422);
            }

            $url = 'https://nominatim.openstreetmap.org/search?format=json'
                . '&q=' . rawurlencode($query . ', Tirana, Albania')
                . '&limit=6&countrycodes=al&addressdetails=1';

            $results = ts_geocode_http_json($url);
            $normalized = [];
            foreach ($results as $result) {
                if (!isset($result['lat'], $result['lon'], $result['display_name'])) {
                    continue;
                }
                $normalized[] = [
                    'lat' => (float) $result['lat'],
                    'lon' => (float) $result['lon'],
                    'display_name' => (string) $result['display_name'],
                    'short_name' => ts_geocode_shorten_label((string) $result['display_name']),
                ];
            }

            json_success(['results' => $normalized]);
            break;

        case 'reverse':
            if (!isset($_GET['lat'], $_GET['lon'])) {
                json_error('Koordinatat mungojnë.', 422);
            }

            $lat = (float) $_GET['lat'];
            $lon = (float) $_GET['lon'];

            if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
                json_error('Koordinata të pavlefshme.', 422);
            }

            $url = 'https://nominatim.openstreetmap.org/reverse?format=json'
                . '&lat=' . rawurlencode((string) $lat)
                . '&lon=' . rawurlencode((string) $lon)
                . '&zoom=18&addressdetails=1';

            $result = ts_geocode_http_json($url);
            $displayName = (string) ($result['display_name'] ?? '');
            json_success([
                'result' => [
                    'display_name' => $displayName,
                    'short_name' => ts_geocode_shorten_label($displayName),
                ],
            ]);
            break;

        default:
            json_error('Veprim i panjohur.', 400);
    }
} catch (Throwable $e) {
    error_log('geocode api: ' . $e->getMessage());
    json_error('Shërbimi i kërkimit të adresave nuk është i disponueshëm për momentin.', 502);
}