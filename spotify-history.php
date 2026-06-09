<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth_boot.php';
require_login();

$historyPath = __DIR__ . '/historico_artistas.json';
$history = is_file($historyPath) ? json_decode((string)file_get_contents($historyPath), true) : [];
if (!is_array($history)) {
    $history = [];
}

uasort($history, static fn(array $a, array $b): int =>
    (int)($a['current_rank'] ?? 999) <=> (int)($b['current_rank'] ?? 999)
);

$artists = array_slice($history, 0, 120, true);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Histórico Spotify | Le Group</title>
  <?= analytics_head() ?>
  <link rel="stylesheet" href="/style/site.css?v=<?= filemtime(__DIR__ . '/style/site.css') ?>">
  <link rel="stylesheet" href="/style/spotify.css?v=<?= filemtime(__DIR__ . '/style/spotify.css') ?>">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="chart-shell">
  <section class="chart-hero compact">
    <div class="hero-copy">
      <p class="eyebrow">Spotify artists chart</p>
      <h1>Histórico do ranking</h1>
      <p>Pico, semanas no Top 30 e posição atual de cada artista acompanhado pelo Le Group.</p>
    </div>
  </section>

  <section class="chart-table-card">
    <div class="table-head">
      <div>
        <p class="eyebrow">Arquivo histórico</p>
        <h2>Artistas monitorados</h2>
      </div>
      <a class="refresh-button muted" href="/spotify.php">Voltar ao ranking</a>
    </div>

    <?php if ($artists): ?>
      <ol class="chart-list history-list">
        <?php foreach ($artists as $name => $artist): ?>
          <li class="chart-item">
            <div class="rank-box"><?= htmlspecialchars((string)($artist['current_rank'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="artist-info">
              <h3 class="artist-name"><?= htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') ?></h3>
              <p class="listeners">Semana <?= htmlspecialchars((string)($artist['ultima_atualizacao_semana'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="stats-block">
              <div class="stat"><span class="stat-label">Atual</span><span class="stat-value"><?= htmlspecialchars((string)($artist['current_rank'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
              <div class="stat"><span class="stat-label">Pico</span><span class="stat-value"><?= htmlspecialchars((string)($artist['peak'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
              <div class="stat"><span class="stat-label">Semanas</span><span class="stat-value"><?= htmlspecialchars((string)($artist['weeks'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <div class="notice">Histórico ainda não foi gerado.</div>
    <?php endif; ?>
  </section>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
