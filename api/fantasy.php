<?php
declare(strict_types=1);

require __DIR__ . '/../includes/auth_boot.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function fantasy_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fantasy_is_admin(): bool
{
    return is_admin_user();
}

function fantasy_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        fantasy_json(['ok' => false, 'error' => 'JSON invalido.'], 400);
    }

    return $data;
}

function fantasy_matches(string $root): array
{
    $path = $root . '/data/world-cup-2026.json';
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data['matches'] ?? null) ? $data['matches'] : [];
}

function fantasy_match_map(array $matches): array
{
    $map = [];
    foreach ($matches as $match) {
        $map[(int)$match['id']] = $match;
    }
    return $map;
}

function fantasy_match_time(array $match): DateTimeImmutable
{
    $tz = app_timezone();
    $date = DateTimeImmutable::createFromFormat('d/m/Y H:i', $match['date'] . ' ' . $match['time'], $tz);
    return $date ?: new DateTimeImmutable('2999-01-01', $tz);
}

function fantasy_results(PDO $db): array
{
    $rows = $db->query('SELECT match_id, result_a, result_b, source, status, synced_at FROM fantasy_results')->fetchAll();
    $results = [];
    foreach ($rows as $row) {
        $results[(int)$row['match_id']] = [
            'a' => (int)$row['result_a'],
            'b' => (int)$row['result_b'],
            'source' => (string)($row['source'] ?? 'manual'),
            'status' => $row['status'] ?? null,
            'synced_at' => $row['synced_at'] ?? null,
        ];
    }
    return $results;
}

function fantasy_user_predictions(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT match_id, pred_a, pred_b FROM fantasy_predictions WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    $predictions = [];
    foreach ($stmt->fetchAll() as $row) {
        $predictions[(int)$row['match_id']] = [
            'a' => (int)$row['pred_a'],
            'b' => (int)$row['pred_b'],
        ];
    }
    return $predictions;
}

function fantasy_points(array $prediction, ?array $result): array
{
    if (!$result) {
        return ['points' => 0, 'type' => 'pending'];
    }

    if ($prediction['a'] === $result['a'] && $prediction['b'] === $result['b']) {
        return ['points' => 3, 'type' => 'exact'];
    }

    $predDiff = $prediction['a'] <=> $prediction['b'];
    $realDiff = $result['a'] <=> $result['b'];

    if ($predDiff === $realDiff) {
        return ['points' => 2, 'type' => 'outcome'];
    }

    return ['points' => 0, 'type' => 'miss'];
}

function fantasy_leaderboard(PDO $db, array $results): array
{
    $users = $db->query('SELECT id, nome FROM usuarios ORDER BY nome')->fetchAll();
    $stmt = $db->query('SELECT user_id, match_id, pred_a, pred_b FROM fantasy_predictions');
    $scores = [];

    foreach ($users as $user) {
        $scores[(int)$user['id']] = [
            'name' => $user['nome'],
            'points' => 0,
            'exact' => 0,
            'outcome' => 0,
            'predictions' => 0,
        ];
    }

    foreach ($stmt->fetchAll() as $row) {
        $userId = (int)$row['user_id'];
        if (!isset($scores[$userId])) {
            continue;
        }

        $prediction = ['a' => (int)$row['pred_a'], 'b' => (int)$row['pred_b']];
        $scored = fantasy_points($prediction, $results[(int)$row['match_id']] ?? null);
        $scores[$userId]['points'] += $scored['points'];
        $scores[$userId]['predictions'] += 1;
        if ($scored['type'] === 'exact') $scores[$userId]['exact'] += 1;
        if ($scored['type'] === 'outcome') $scores[$userId]['outcome'] += 1;
    }

    usort($scores, static fn(array $a, array $b): int =>
        $b['points'] <=> $a['points']
        ?: $b['exact'] <=> $a['exact']
        ?: $b['outcome'] <=> $a['outcome']
        ?: strcasecmp($a['name'], $b['name'])
    );

    foreach ($scores as $index => &$row) {
        $row['position'] = $index + 1;
    }

    return $scores;
}

function fantasy_history(PDO $db, array $matches, array $results): array
{
    if (!$results) {
        return [];
    }

    $matchMap = fantasy_match_map($matches);
    $stmt = $db->query(
        'SELECT fp.user_id, fp.match_id, fp.pred_a, fp.pred_b, fp.updated_at, u.nome
           FROM fantasy_predictions fp
           JOIN usuarios u ON u.id = fp.user_id
          ORDER BY fp.updated_at DESC'
    );
    $history = [];

    foreach ($stmt->fetchAll() as $row) {
        $matchId = (int)$row['match_id'];
        if (!isset($results[$matchId], $matchMap[$matchId])) {
            continue;
        }

        $prediction = ['a' => (int)$row['pred_a'], 'b' => (int)$row['pred_b']];
        $scored = fantasy_points($prediction, $results[$matchId]);
        $match = $matchMap[$matchId];
        $history[] = [
            'user' => $row['nome'],
            'match_id' => $matchId,
            'date' => $match['date'] ?? '',
            'group' => $match['group'] ?? '',
            'team1' => $match['team1'] ?? '',
            'team2' => $match['team2'] ?? '',
            'prediction' => $prediction,
            'result' => $results[$matchId],
            'points' => $scored['points'],
            'type' => $scored['type'],
        ];
    }

    return array_slice($history, 0, 80);
}

function fantasy_next_matches(array $matches, array $results): array
{
    $unresolved = array_values(array_filter($matches, static fn(array $match): bool =>
        !isset($results[(int)$match['id']])
    ));

    if (!$unresolved) {
        return [];
    }

    usort($unresolved, static fn(array $a, array $b): int =>
        fantasy_match_time($a) <=> fantasy_match_time($b)
    );

    $nextDate = $unresolved[0]['date'];
    return array_values(array_filter($unresolved, static fn(array $match): bool =>
        $match['date'] === $nextDate
    ));
}

try {
    ensure_fantasy_schema($db);

    $user = current_user();
    $userId = (int)($user['id'] ?? 0);
    $matches = fantasy_matches(dirname(__DIR__));
    $matchMap = fantasy_match_map($matches);
    $results = fantasy_results($db);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        fantasy_json([
            'ok' => true,
            'is_admin' => fantasy_is_admin(),
            'next_matches' => fantasy_next_matches($matches, $results),
            'matches' => $matches,
            'predictions' => fantasy_user_predictions($db, $userId),
            'results' => $results,
            'leaderboard' => fantasy_leaderboard($db, $results),
            'history' => fantasy_history($db, $matches, $results),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fantasy_json(['ok' => false, 'error' => 'Metodo nao permitido.'], 405);
    }

    $body = fantasy_body();
    $action = (string)($body['action'] ?? '');
    $matchId = (int)($body['match_id'] ?? 0);

    if (!isset($matchMap[$matchId])) {
        fantasy_json(['ok' => false, 'error' => 'Jogo invalido.'], 400);
    }

    $a = filter_var($body['a'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $b = filter_var($body['b'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($a === false || $b === false) {
        fantasy_json(['ok' => false, 'error' => 'Placar invalido.'], 400);
    }

    if ($action === 'prediction') {
        if (isset($results[$matchId])) {
            fantasy_json(['ok' => false, 'error' => 'Este jogo ja tem resultado oficial.'], 400);
        }

        $now = app_now();
        if (fantasy_match_time($matchMap[$matchId]) <= $now) {
            fantasy_json(['ok' => false, 'error' => 'Palpites encerrados para este jogo.'], 400);
        }

        $stmt = $db->prepare(
            'INSERT INTO fantasy_predictions (user_id, match_id, pred_a, pred_b)
             VALUES (:user_id, :match_id, :pred_a, :pred_b)
             ON DUPLICATE KEY UPDATE pred_a = VALUES(pred_a), pred_b = VALUES(pred_b), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':match_id' => $matchId,
            ':pred_a' => $a,
            ':pred_b' => $b,
        ]);

        fantasy_json(['ok' => true]);
    }

    if ($action === 'result') {
        if (!fantasy_is_admin()) {
            fantasy_json(['ok' => false, 'error' => 'Apenas admin pode lancar resultado.'], 403);
        }

        $stmt = $db->prepare(
            'INSERT INTO fantasy_results (match_id, result_a, result_b, source, status, synced_at)
             VALUES (:match_id, :result_a, :result_b, "manual", "manual", NOW())
             ON DUPLICATE KEY UPDATE result_a = VALUES(result_a), result_b = VALUES(result_b), source = "manual", status = "manual", synced_at = NOW(), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':match_id' => $matchId,
            ':result_a' => $a,
            ':result_b' => $b,
        ]);

        fantasy_json(['ok' => true]);
    }

    fantasy_json(['ok' => false, 'error' => 'Acao invalida.'], 400);
} catch (Throwable $e) {
    fantasy_json(['ok' => false, 'error' => 'Erro ao carregar o bolão.'], 500);
}
