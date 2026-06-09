<?php
declare(strict_types=1);

require __DIR__ . '/../includes/auth_boot.php';
require_admin();

$pageTitle = 'Admin';
$flashOk = '';
$flashError = '';

function admin_json_data(): array
{
    $path = __DIR__ . '/../data/world-cup-2026.json';
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : ['matches' => []];
}

function admin_matches(): array
{
    $data = admin_json_data();
    return is_array($data['matches'] ?? null) ? $data['matches'] : [];
}

function admin_match_map(array $matches): array
{
    $map = [];
    foreach ($matches as $match) {
        $map[(int)$match['id']] = $match;
    }
    return $map;
}

function admin_tables(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS fantasy_results (
            match_id INT PRIMARY KEY,
            result_a INT NOT NULL,
            result_b INT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

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
        "CREATE TABLE IF NOT EXISTS simuladores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            simulator_key VARCHAR(80) NOT NULL,
            payload LONGTEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_simulator (user_id, simulator_key),
            INDEX simulator_user_idx (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function admin_results(PDO $db): array
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

function admin_users(PDO $db): array
{
    $sql = "SELECT u.id, u.nome, u.email, u.role, u.created_at, u.last_login,
                   COUNT(DISTINCT fp.id) AS predictions,
                   COUNT(DISTINCT s.id) AS simulators
              FROM usuarios u
         LEFT JOIN fantasy_predictions fp ON fp.user_id = u.id
         LEFT JOIN simuladores s ON s.user_id = u.id
          GROUP BY u.id, u.nome, u.email, u.role, u.created_at, u.last_login
          ORDER BY u.created_at DESC, u.id DESC";

    return $db->query($sql)->fetchAll();
}

function admin_delete_user(PDO $db, int $userId): void
{
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('DELETE FROM fantasy_predictions WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);

        $stmt = $db->prepare('DELETE FROM simuladores WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);

        $stmt = $db->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmt->execute([':id' => $userId]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

admin_tables($db);
$matches = admin_matches();
$matchMap = admin_match_map($matches);
$currentUser = current_user();
$currentUserId = (int)($currentUser['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_check($token)) {
        $flashError = 'Sessao expirada. Recarregue a pagina.';
    } elseif ($action === 'save_result') {
        $matchId = (int)($_POST['match_id'] ?? 0);
        $a = filter_var($_POST['result_a'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $b = filter_var($_POST['result_b'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if (!isset($matchMap[$matchId]) || $a === false || $b === false) {
            $flashError = 'Placar invalido.';
        } else {
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
            $flashOk = 'Resultado oficial salvo.';
        }
    } elseif ($action === 'clear_result') {
        $matchId = (int)($_POST['match_id'] ?? 0);

        if (!isset($matchMap[$matchId])) {
            $flashError = 'Jogo invalido.';
        } else {
            $stmt = $db->prepare('DELETE FROM fantasy_results WHERE match_id = :match_id');
            $stmt->execute([':match_id' => $matchId]);
            $flashOk = 'Resultado oficial removido.';
        }
    } elseif ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0 || $userId === $currentUserId) {
            $flashError = 'Nao da para excluir esta conta.';
        } else {
            admin_delete_user($db, $userId);
            $flashOk = 'Usuario excluido.';
        }
    }
}

$results = admin_results($db);
$users = admin_users($db);
$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Le Group</title>
  <?= analytics_head() ?>
  <link rel="stylesheet" href="/style/site.css?v=<?= filemtime(__DIR__ . '/../style/site.css') ?>">
  <link rel="stylesheet" href="/style/admin.css?v=<?= filemtime(__DIR__ . '/../style/admin.css') ?>">
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main class="admin-shell">
  <section class="admin-head">
    <p class="eyebrow">Controle do dono</p>
    <h1>Admin Le Group</h1>
    <p>Lance os resultados oficiais e gerencie os cadastros do site.</p>
  </section>

  <?php if ($flashOk): ?>
    <div class="notice ok"><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="notice error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <section class="admin-section">
    <div class="section-title">
      <div>
        <p class="eyebrow">Copa do Mundo</p>
        <h2>Placares oficiais</h2>
      </div>
    </div>

    <div class="admin-match-grid">
      <?php foreach ($matches as $match): ?>
        <?php
          $id = (int)$match['id'];
          $result = $results[$id] ?? null;
        ?>
        <form class="admin-card result-card" method="post" action="">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="match_id" value="<?= $id ?>">

          <div class="card-meta">
            <span>Grupo <?= htmlspecialchars((string)$match['group'], ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= htmlspecialchars((string)$match['date'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)$match['time'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>

          <div class="teams">
            <strong><?= htmlspecialchars((string)$match['team1'], ENT_QUOTES, 'UTF-8') ?></strong>
            <strong><?= htmlspecialchars((string)$match['team2'], ENT_QUOTES, 'UTF-8') ?></strong>
          </div>

          <div class="score-line">
            <input type="number" min="0" inputmode="numeric" name="result_a" value="<?= htmlspecialchars((string)($result['a'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-label="Gols <?= htmlspecialchars((string)$match['team1'], ENT_QUOTES, 'UTF-8') ?>">
            <span>x</span>
            <input type="number" min="0" inputmode="numeric" name="result_b" value="<?= htmlspecialchars((string)($result['b'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-label="Gols <?= htmlspecialchars((string)$match['team2'], ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div class="result-actions">
            <?php if ($result): ?>
              <button class="danger-btn" type="submit" name="action" value="clear_result" formnovalidate>Limpar</button>
            <?php endif; ?>
            <button class="admin-btn" type="submit" name="action" value="save_result">Salvar</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="admin-section">
    <div class="section-title">
      <div>
        <p class="eyebrow">Cadastros</p>
        <h2>Usuarios</h2>
      </div>
      <span class="counter"><?= count($users) ?> contas</span>
    </div>

    <div class="admin-card table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Role</th>
            <th>Palpites</th>
            <th>Simulador</th>
            <th>Criado em</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $siteUser): ?>
            <?php $userId = (int)$siteUser['id']; ?>
            <tr>
              <td><strong><?= htmlspecialchars((string)$siteUser['nome'], ENT_QUOTES, 'UTF-8') ?></strong></td>
              <td><?= htmlspecialchars((string)$siteUser['email'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$siteUser['role'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int)$siteUser['predictions'] ?></td>
              <td><?= (int)$siteUser['simulators'] ?></td>
              <td><?= htmlspecialchars((string)($siteUser['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <?php if ($userId !== $currentUserId): ?>
                  <form method="post" action="" onsubmit="return confirm('Excluir este cadastro e os dados dele?');">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                    <button class="danger-btn" type="submit">Excluir</button>
                  </form>
                <?php else: ?>
                  <span class="self">Voce</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
