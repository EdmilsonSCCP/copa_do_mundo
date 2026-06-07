<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth_boot.php';
require_login();
require __DIR__ . '/includes/spotify_service.php';

$notice = '';
$noticeType = 'info';

if (isset($_GET['refresh'])) {
    try {
        spotify_refresh_ranking(__DIR__);
        header('Location: /spotify.php?updated=1');
        exit;
    } catch (Throwable $e) {
        $notice = 'Não consegui atualizar agora: ' . $e->getMessage();
        $noticeType = 'error';
    }
}

if (isset($_GET['updated'])) {
    $notice = 'Ranking atualizado com sucesso.';
    $noticeType = 'success';
}

$rankingPath = __DIR__ . '/top30_display.json';
$ranking = file_exists($rankingPath) ? json_decode((string)file_get_contents($rankingPath), true) : [];
if (!is_array($ranking)) {
    $ranking = [];
}

$lastUpdated = file_exists($rankingPath) ? date('d/m/Y H:i', filemtime($rankingPath)) : 'sem histórico';
$weekLabel = 'Semana de ' . date('d/m/Y', strtotime('next Saturday'));

function format_listeners($listeners): string
{
    $value = (int)str_replace(',', '', (string)$listeners);
    return number_format($value, 0, ',', '.');
}

function movement_label(array $artist): string
{
    if (!empty($artist['is_new'])) {
        return 'Nova entrada';
    }

    if (!isset($artist['lw']) || $artist['lw'] === null || $artist['lw'] === '') {
        return 'Sem posição anterior';
    }

    $rank = (int)$artist['rank'];
    $last = (int)$artist['lw'];

    if ($rank === $last) return 'Estável';
    if ($rank < $last) return 'Subiu ' . ($last - $rank);
    return 'Caiu ' . ($rank - $last);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ranking Global do Spotify | Le Group</title>
  <link rel="preconnect" href="https://cdn-images.dzcdn.net">
  <link rel="stylesheet" href="/style/site.css">
  <link rel="stylesheet" href="/style/spotify.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="chart-shell">
  <section class="chart-hero">
    <div>
      <p class="eyebrow">Spotify artists chart</p>
      <h1>Ranking Global do Spotify</h1>
      <p>Top 30 artistas por ouvintes mensais, com histórico de pico, semanas no ranking e movimento da última atualização.</p>
    </div>
    <aside class="week-card" aria-label="Semana do ranking">
      <span><?= htmlspecialchars($weekLabel, ENT_QUOTES, 'UTF-8') ?></span>
      <strong>Top 30</strong>
      <small>Última atualização: <?= htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8') ?></small>
    </aside>
  </section>

  <?php if ($notice): ?>
    <div class="notice notice-<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <section class="chart-toolbar" aria-label="Ações do ranking">
    <p>O botão abaixo baixa ranking, imagens e processa histórico em uma ação.</p>
    <a class="refresh-button" href="/spotify.php?refresh=1">Atualizar ranking agora</a>
  </section>

  <ol class="chart-list">
    <?php if ($ranking): ?>
      <?php foreach ($ranking as $artist): ?>
        <?php
          $rank = (int)($artist['rank'] ?? 0);
          $image = $artist['image'] ?: '/assets/logo_spotify.png';
          $name = (string)($artist['artist'] ?? 'Artista sem nome');
        ?>
        <li class="chart-item <?= $rank === 1 ? 'is-leader' : '' ?>">
          <div class="rank-box"><?= $rank ?></div>
          <img class="artist-art" src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
          <div class="artist-info">
            <h2 class="artist-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="listeners"><?= format_listeners($artist['listeners'] ?? 0) ?> ouvintes mensais</p>
            <div class="movement"><?= htmlspecialchars(movement_label($artist), ENT_QUOTES, 'UTF-8') ?></div>
            <?php if (!empty($artist['is_new'])): ?>
              <span class="new-badge">Novo no ranking</span>
            <?php endif; ?>
          </div>
          <div class="stats-block" aria-label="Estatísticas de <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
            <div class="stat"><span class="stat-label">LW</span><span class="stat-value"><?= htmlspecialchars((string)($artist['lw'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="stat"><span class="stat-label">Pico</span><span class="stat-value"><?= htmlspecialchars((string)($artist['peak'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
            <div class="stat"><span class="stat-label">Semanas</span><span class="stat-value"><?= htmlspecialchars((string)($artist['weeks'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
          </div>
        </li>
      <?php endforeach; ?>
    <?php else: ?>
      <li class="notice">Não foi possível carregar o ranking. Use “Atualizar ranking agora” para gerar o JSON.</li>
    <?php endif; ?>
  </ol>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
