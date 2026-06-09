<?php
declare(strict_types=1);

require __DIR__ . '/../includes/auth_boot.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

const SIMULATOR_KEY = 'copa2026';

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON invalido.'], 400);
    }

    return $data;
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
        json_response(['ok' => false, 'error' => 'Usuário inválido.'], 401);
}

try {
    ensure_simulator_schema($db);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare(
            'SELECT payload, updated_at
               FROM simuladores
              WHERE user_id = :user_id
                AND simulator_key = :simulator_key
              LIMIT 1'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':simulator_key' => SIMULATOR_KEY,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            json_response(['ok' => true, 'exists' => false, 'scores' => new stdClass()]);
        }

        $scores = json_decode((string)$row['payload'], true);
        json_response([
            'ok' => true,
            'exists' => true,
            'scores' => is_array($scores) ? $scores : new stdClass(),
            'updated_at' => $row['updated_at'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = read_json_body();
        $scores = $data['scores'] ?? [];

        if (!is_array($scores)) {
            json_response(['ok' => false, 'error' => 'Placar invalido.'], 400);
        }

        $payload = json_encode($scores, JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            json_response(['ok' => false, 'error' => 'Não foi possível salvar.'], 400);
        }

        $stmt = $db->prepare(
            'INSERT INTO simuladores (user_id, simulator_key, payload)
             VALUES (:user_id, :simulator_key, :payload)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':simulator_key' => SIMULATOR_KEY,
            ':payload' => $payload,
        ]);

        json_response(['ok' => true]);
    }

    json_response(['ok' => false, 'error' => 'Metodo nao permitido.'], 405);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Erro ao acessar o simulador.'], 500);
}
