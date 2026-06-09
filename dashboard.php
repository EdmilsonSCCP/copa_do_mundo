<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth_boot.php';
require_login();

$user = current_user();
$userId = (int)($user['id'] ?? 0);

function dashboard_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dashboard_match_time(array $match): DateTimeImmutable
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $date = DateTimeImmutable::createFromFormat('d/m/Y H:i', $match['date'] . ' ' . $match['time'], $tz);
    return $date ?: new DateTimeImmutable('2999-01-01', $tz);
}

$worldCup = json_decode((string)file_get_contents(__DIR__ . '/data/world-cup-2026.json'), true);
$matches = is_array($worldCup['matches'] ?? null) ? $worldCup['matches'] : [];
$officialResults = [];
$userPredictions = [];
$leaderboard = [];
$stats = [
    'predictions' => 0,
    'pending_predictions' => 0,
    'official_results' => 0,
    'simulator_saved' => false,
];

try {
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

    foreach ($db->query('SELECT match_id, result_a, result_b FROM fantasy_results')->fetchAll() as $row) {
        $officialResults[(int)$row['match_id']] = [(int)$row['result_a'], (int)$row['result_b']];
    }

    $stmt = $db->prepare('SELECT match_id, pred_a, pred_b FROM fantasy_predictions WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    foreach ($stmt->fetchAll() as $row) {
        $userPredictions[(int)$row['match_id']] = [(int)$row['pred_a'], (int)$row['pred_b']];
    }

    $stats['predictions'] = count($userPredictions);
    $stats['official_results'] = count($officialResults);

    $stmt = $db->prepare('SELECT COUNT(*) FROM simuladores WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $stats['simulator_saved'] = (int)$stmt->fetchColumn() > 0;

    $users = $db->query('SELECT id, nome FROM usuarios ORDER BY nome')->fetchAll();
    $predictions = $db->query('SELECT user_id, match_id, pred_a, pred_b FROM fantasy_predictions')->fetchAll();
    $scoreMap = [];

    foreach ($users as $siteUser) {
        $scoreMap[(int)$siteUser['id']] = [
            'name' => $siteUser['nome'],
            'points' => 0,
            'exact' => 0,
        ];
    }

    foreach ($predictions as $prediction) {
        $uid = (int)$prediction['user_id'];
        $matchId = (int)$prediction['match_id'];
        if (!isset($scoreMap[$uid], $officialResults[$matchId])) {
            continue;
        }

        [$ra, $rb] = $officialResults[$matchId];
        $pa = (int)$prediction['pred_a'];
        $pb = (int)$prediction['pred_b'];
        if ($pa === $ra && $pb === $rb) {
            $scoreMap[$uid]['points'] += 3;
            $scoreMap[$uid]['exact']++;
        } elseif (($pa <=> $pb) === ($ra <=> $rb)) {
            $scoreMap[$uid]['points'] += 2;
        }
    }

    $leaderboard = array_values($scoreMap);
    usort($leaderboard, static fn(array $a, array $b): int =>
        $b['points'] <=> $a['points']
        ?: $b['exact'] <=> $a['exact']
        ?: strcasecmp($a['name'], $b['name'])
    );
    $leaderboard = array_slice($leaderboard, 0, 5);
} catch (Throwable) {
    $leaderboard = [];
}

$now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$futureMatches = array_values(array_filter($matches, static fn(array $match): bool => dashboard_match_time($match) >= $now));
usort($futureMatches, static fn(array $a, array $b): int => dashboard_match_time($a) <=> dashboard_match_time($b));
$nextMatches = array_slice($futureMatches, 0, 4);

foreach ($nextMatches as $match) {
    if (!isset($userPredictions[(int)$match['id']])) {
        $stats['pending_predictions']++;
    }
}

$spotify = is_file(__DIR__ . '/top30_display.json')
    ? json_decode((string)file_get_contents(__DIR__ . '/top30_display.json'), true)
    : [];
$spotifyTop = is_array($spotify) ? array_slice($spotify, 0, 3) : [];
$firstName = explode(' ', trim((string)($user['nome'] ?? '')))[0] ?: 'jogador';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inicio | Le Group</title>
  <?= analytics_head() ?>
  <link rel="stylesheet" href="/style/site.css?v=<?= filemtime(__DIR__ . '/style/site.css') ?>">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="dashboard-shell">
  <section class="dashboard-hero">
    <div>
      <p class="eyebrow">Painel Le Group</p>
      <h1>Bem-vindo, <?= dashboard_h($firstName) ?></h1>
      <p>Acompanhe seus palpites, os proximos jogos da Copa e o Top Spotify sem precisar procurar por tudo no menu.</p>
    </div>
    <div class="dashboard-actions">
      <a class="dash-btn primary" href="/index.php#fantasy">Ir para o bolao</a>
      <a class="dash-btn" href="/index.php#matches">Abrir simulador</a>
      <a class="dash-btn" href="/spotify.php">Ver Spotify</a>
    </div>
  </section>

  <section class="dashboard-kpis" aria-label="Resumo da sua conta">
    <article><span>Meus palpites</span><strong><?= $stats['predictions'] ?></strong></article>
    <article><span>Pendentes</span><strong><?= $stats['pending_predictions'] ?></strong></article>
    <article><span>Placares oficiais</span><strong><?= $stats['official_results'] ?></strong></article>
    <article><span>Simulador</span><strong><?= $stats['simulator_saved'] ? 'Salvo' : 'Novo' ?></strong></article>
  </section>

  <section class="dashboard-grid">
    <article class="dash-card">
      <div class="card-title">
        <p class="eyebrow">Agenda</p>
        <h2>Proximos jogos</h2>
      </div>
      <div class="next-list">
        <?php foreach ($nextMatches as $match): ?>
          <?php $prediction = $userPredictions[(int)$match['id']] ?? null; ?>
          <a href="/index.php#fantasy" class="next-match">
            <span>Grupo <?= dashboard_h($match['group']) ?> - <?= dashboard_h($match['date']) ?> <?= dashboard_h($match['time']) ?></span>
            <strong><?= dashboard_h($match['team1']) ?> x <?= dashboard_h($match['team2']) ?></strong>
            <small><?= $prediction ? 'Palpite feito: ' . $prediction[0] . ' x ' . $prediction[1] : 'Palpite pendente' ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="dash-card">
      <div class="card-title">
        <p class="eyebrow">Bolao</p>
        <h2>Ranking dos amigos</h2>
      </div>
      <ol class="dash-ranking">
        <?php foreach ($leaderboard as $index => $row): ?>
          <li><span>#<?= $index + 1 ?></span><strong><?= dashboard_h($row['name']) ?></strong><em><?= (int)$row['points'] ?> pts</em></li>
        <?php endforeach; ?>
      </ol>
    </article>

    <article class="dash-card">
      <div class="card-title">
        <p class="eyebrow">Spotify</p>
        <h2>Top 3 global</h2>
      </div>
      <div class="spotify-mini-list">
        <?php foreach ($spotifyTop as $artist): ?>
          <?php $image = $artist['image'] ?: '/assets/logo_spotify.png'; ?>
          <a href="/spotify.php">
            <img src="<?= dashboard_h($image) ?>" alt="<?= dashboard_h($artist['artist'] ?? 'Artista') ?>" loading="lazy">
            <span>#<?= (int)($artist['rank'] ?? 0) ?></span>
            <strong><?= dashboard_h($artist['artist'] ?? 'Artista') ?></strong>
          </a>
        <?php endforeach; ?>
      </div>
    </article>
  </section>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
