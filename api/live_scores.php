<?php
declare(strict_types=1);

require __DIR__ . '/../includes/auth_boot.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function live_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function live_data_path(): string
{
    return dirname(__DIR__) . '/data/world-cup-2026.json';
}

function live_cache_path(string $key): string
{
    $safeKey = preg_replace('/[^a-z0-9_-]+/i', '-', $key) ?: 'live';
    return dirname(__DIR__) . "/data/live-score-cache-{$safeKey}.json";
}

function live_matches(): array
{
    $data = json_decode((string)file_get_contents(live_data_path()), true);
    return is_array($data['matches'] ?? null) ? $data['matches'] : [];
}

function live_norm(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = $ascii;
    }

    $value = strtolower($value);
    $value = str_replace(['&', '.', "'", '`', '’', '-'], [' and ', ' ', ' ', ' ', ' ', ' '], $value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
    return trim(preg_replace('/\s+/', ' ', $value) ?: '');
}

function live_aliases(string $team): array
{
    $aliases = [
        'africa do sul' => ['south africa'],
        'alemanha' => ['germany'],
        'arabia saudita' => ['saudi arabia'],
        'argelia' => ['algeria'],
        'austria' => ['austria'],
        'belgica' => ['belgium'],
        'bosnia e herzegovina' => ['bosnia and herzegovina', 'bosnia herzegovina'],
        'brasil' => ['brazil'],
        'cabo verde' => ['cape verde'],
        'canada' => ['canada'],
        'colombia' => ['colombia'],
        'coreia do sul' => ['south korea', 'korea republic'],
        'costa do marfim' => ['ivory coast', 'cote d ivoire', 'cote divoire'],
        'croacia' => ['croatia'],
        'curacao' => ['curacao'],
        'egito' => ['egypt'],
        'equador' => ['ecuador'],
        'escocia' => ['scotland'],
        'espanha' => ['spain'],
        'estados unidos' => ['united states', 'usa', 'united states of america'],
        'franca' => ['france'],
        'gana' => ['ghana'],
        'haiti' => ['haiti'],
        'holanda' => ['netherlands', 'holland'],
        'inglaterra' => ['england'],
        'ira' => ['iran'],
        'iraque' => ['iraq'],
        'japao' => ['japan'],
        'jordania' => ['jordan'],
        'marrocos' => ['morocco'],
        'mexico' => ['mexico'],
        'noruega' => ['norway'],
        'nova zelandia' => ['new zealand'],
        'panama' => ['panama'],
        'paraguai' => ['paraguay'],
        'portugal' => ['portugal'],
        'qatar' => ['qatar'],
        'republica democratica do congo' => ['dr congo', 'congo dr', 'congo democratic republic'],
        'republica tcheca' => ['czech republic', 'czechia'],
        'senegal' => ['senegal'],
        'suica' => ['switzerland'],
        'suecia' => ['sweden'],
        'tunisia' => ['tunisia'],
        'turquia' => ['turkey', 'turkiye'],
        'uruguai' => ['uruguay'],
        'uzbequistao' => ['uzbekistan'],
    ];

    $key = live_norm($team);
    $values = [$key];
    foreach ($aliases[$key] ?? [] as $alias) {
        $values[] = live_norm($alias);
    }

    return array_values(array_unique(array_filter($values)));
}

function live_pair_matches(array $localMatch, string $apiHome, string $apiAway): ?array
{
    $home = live_norm($apiHome);
    $away = live_norm($apiAway);
    $teamA = live_aliases((string)$localMatch['team1']);
    $teamB = live_aliases((string)$localMatch['team2']);

    if (in_array($home, $teamA, true) && in_array($away, $teamB, true)) {
        return ['reversed' => false];
    }

    if (in_array($home, $teamB, true) && in_array($away, $teamA, true)) {
        return ['reversed' => true];
    }

    return null;
}

function live_match_time(array $match): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('d/m/Y H:i', $match['date'] . ' ' . $match['time'], app_timezone());
    return $date ?: new DateTimeImmutable('2999-01-01', app_timezone());
}

function live_api_request(string $query, string $cacheKey, bool $force = false, ?int $ttl = null): array
{
    $ttl = $ttl ?? API_FOOTBALL_CACHE_SECONDS;
    $cachePath = live_cache_path($cacheKey);
    $cacheAge = is_file($cachePath) ? (time() - filemtime($cachePath)) : PHP_INT_MAX;

    if (!$force && $cacheAge < $ttl) {
        $cached = json_decode((string)file_get_contents($cachePath), true);
        if (is_array($cached)) {
            return ['source' => "cache:{$cacheKey}", 'body' => $cached];
        }
    }

    if (trim((string)API_FOOTBALL_KEY) === '') {
        live_json(['ok' => false, 'error' => 'Chave da API-Football nao configurada.'], 400);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "x-apisports-key: " . API_FOOTBALL_KEY . "\r\n",
            'timeout' => 10,
        ],
    ]);

    $url = API_FOOTBALL_BASE_URL . $query;
    $raw = @file_get_contents($url, false, $context);
    $body = json_decode((string)$raw, true);
    if (!is_array($body)) {
        live_json(['ok' => false, 'error' => 'Nao consegui consultar a API-Football agora.'], 502);
    }

    @file_put_contents($cachePath, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return ['source' => "api-football:{$cacheKey}", 'body' => $body];
}

function live_fetch_fixtures(bool $force = false): array
{
    return live_api_request('/fixtures?live=all', 'live', $force);
}

function live_recovery_dates(array $matches): array
{
    $now = app_now();
    $dates = [];

    foreach ($matches as $match) {
        $start = live_match_time($match);
        if ($now >= $start->modify('+95 minutes') && $now <= $start->modify('+8 hours')) {
            $dates[$start->format('Y-m-d')] = true;
        }
    }

    return array_keys($dates);
}

function live_should_fetch_live(array $matches): bool
{
    $now = app_now();

    foreach ($matches as $match) {
        $start = live_match_time($match);
        if ($now >= $start->modify('-10 minutes') && $now <= $start->modify('+160 minutes')) {
            return true;
        }
    }

    return false;
}

function live_fetch_date_fixtures(array $dates, bool $force = false): array
{
    $responses = [];
    foreach ($dates as $date) {
        $responses[] = live_api_request('/fixtures?date=' . rawurlencode($date), "date-{$date}", $force, 600);
    }

    return $responses;
}

function live_upsert_result(PDO $db, int $matchId, int $a, int $b, string $status): void
{
    $stmt = $db->prepare(
        'INSERT INTO fantasy_results (match_id, result_a, result_b, source, status, synced_at)
         VALUES (:match_id, :result_a, :result_b, "api-football", :status, NOW())
         ON DUPLICATE KEY UPDATE
            result_a = IF(source = "manual" AND status = "manual", result_a, VALUES(result_a)),
            result_b = IF(source = "manual" AND status = "manual", result_b, VALUES(result_b)),
            source = IF(source = "manual" AND status = "manual", source, "api-football"),
            status = IF(source = "manual" AND status = "manual", status, VALUES(status)),
            synced_at = IF(source = "manual" AND status = "manual", synced_at, NOW()),
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':match_id' => $matchId,
        ':result_a' => $a,
        ':result_b' => $b,
        ':status' => $status,
    ]);
}

try {
    ensure_fantasy_schema($db);

    $force = ($_GET['force'] ?? '') === '1';
    $matches = live_matches();
    $responses = [];
    if ($force || live_should_fetch_live($matches)) {
        $responses[] = live_fetch_fixtures($force);
    }

    $recoveryDates = live_recovery_dates($matches);
    foreach (live_fetch_date_fixtures($recoveryDates, $force) as $dateResponse) {
        $responses[] = $dateResponse;
    }

    $updated = [];
    $sources = [];

    foreach ($responses as $fixtures) {
        $sources[] = $fixtures['source'];

        foreach (($fixtures['body']['response'] ?? []) as $fixture) {
            if (!is_array($fixture)) {
                continue;
            }

            $apiHome = (string)($fixture['teams']['home']['name'] ?? '');
            $apiAway = (string)($fixture['teams']['away']['name'] ?? '');
            $homeGoals = $fixture['goals']['home'] ?? null;
            $awayGoals = $fixture['goals']['away'] ?? null;
            $status = (string)($fixture['fixture']['status']['short'] ?? 'LIVE');

            if ($apiHome === '' || $apiAway === '' || $homeGoals === null || $awayGoals === null) {
                continue;
            }

            foreach ($matches as $match) {
                $pair = live_pair_matches($match, $apiHome, $apiAway);
                if (!$pair) {
                    continue;
                }

                $a = (int)($pair['reversed'] ? $awayGoals : $homeGoals);
                $b = (int)($pair['reversed'] ? $homeGoals : $awayGoals);
                $matchId = (int)$match['id'];
                live_upsert_result($db, $matchId, $a, $b, $status);
                $updated[$matchId] = [
                    'match_id' => $matchId,
                    'team1' => $match['team1'] ?? '',
                    'team2' => $match['team2'] ?? '',
                    'a' => $a,
                    'b' => $b,
                    'status' => $status,
                ];
                break;
            }
        }
    }

    live_json([
        'ok' => true,
        'source' => implode(',', array_unique($sources)),
        'cache_seconds' => API_FOOTBALL_CACHE_SECONDS,
        'updated' => count($updated),
        'recovery_dates' => $recoveryDates,
        'matches' => array_values($updated),
    ]);
} catch (Throwable $e) {
    error_log('Live score sync failed: ' . $e->getMessage());
    live_json(['ok' => false, 'error' => 'Erro ao sincronizar gols ao vivo.'], 500);
}
