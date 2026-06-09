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
    $user = current_user();
    return ($user['role'] ?? '') === 'admin';
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

function fantasy_tables(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS fantasy_predictions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            match_id INT NOT NULL,
            pred_a INT NOT NULL,
            pred_b INT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_match_prediction (user_id, match_id),
            INDEX prediction_match_idx (match_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS fantasy_results (
            match_id INT PRIMARY KEY,
            result_a INT NOT NULL,
            result_b INT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
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
    $tz = new DateTimeZone('America/Sao_Paulo');
    $date = DateTimeImmutable::createFromFormat('d/m/Y H:i', $match['date'] . ' ' . $match['time'], $tz);
    return $date ?: new DateTimeImmutable('2999-01-01', $tz);
}

function fantasy_results(PDO $db): array
{
    $rows = $db->query('SELECT match_id, result_a, result_b FROM fantasy_results')->fetchAll();
    $results = [];
    foreach ($rows as $row) {
        $results[(int)$row['match_id']] = [
            'a' => (int)$row['result_a'],
            'b' => (int)$row['result_b'],
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
    fantasy_tables($db);

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
            'predictions' => fantasy_user_predictions($db, $userId),
            'results' => $results,
            'leaderboard' => fantasy_leaderboard($db, $results),
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

        $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
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
            'INSERT INTO fantasy_results (match_id, result_a, result_b)
             VALUES (:match_id, :result_a, :result_b)
             ON DUPLICATE KEY UPDATE result_a = VALUES(result_a), result_b = VALUES(result_b), updated_at = CURRENT_TIMESTAMP'
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
    fantasy_json(['ok' => false, 'error' => 'Erro ao carregar o bolao.'], 500);
}
