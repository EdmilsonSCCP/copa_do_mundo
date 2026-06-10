<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth_boot.php';
require_login();
require __DIR__ . '/includes/spotify_service.php';

$notice = '';
$noticeType = 'info';
$isAdmin = is_admin_user();

if (isset($_GET['refresh'])) {
    if (!$isAdmin) {
        $notice = 'Atualizacao manual disponivel apenas para admin.';
        $noticeType = 'error';
    } else {
        try {
            spotify_refresh_ranking(__DIR__);
            header('Location: /spotify.php?updated=1');
            exit;
        } catch (Throwable $e) {
            $notice = 'Não consegui atualizar agora: ' . $e->getMessage();
            $noticeType = 'error';
        }
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

$lastUpdated = file_exists($rankingPath) ? date('d/m/Y H:i', filemtime($rankingPath)) : 'sem historico';
$weekLabel = 'Semana de ' . date('d/m/Y', strtotime('next Saturday'));
$leader = $ranking[0] ?? null;
$podium = array_slice($ranking, 0, 3);
$chartRows = array_slice($ranking, 3);

function format_listeners($listeners): string
{
    $value = (int)str_replace(',', '', (string)$listeners);
    return number_format($value, 0, ',', '.');
}

function compact_listeners($listeners): string
{
    $value = (int)str_replace(',', '', (string)$listeners);
    if ($value >= 1000000) {
        return number_format($value / 1000000, 1, ',', '.') . ' mi';
    }

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

    if ($rank === $last) return 'Estavel';
    if ($rank < $last) return 'Subiu ' . ($last - $rank);
    return 'Caiu ' . ($rank - $last);
}

function movement_class(array $artist): string
{
    if (!empty($artist['is_new'])) {
        return 'is-new';
    }

    if (!isset($artist['lw']) || $artist['lw'] === null || $artist['lw'] === '') {
        return 'is-neutral';
    }

    $rank = (int)$artist['rank'];
    $last = (int)$artist['lw'];

    if ($rank < $last) return 'is-up';
    if ($rank > $last) return 'is-down';
    return 'is-neutral';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ranking Global do Spotify | Le Group</title>
  <?= analytics_head() ?>
  <link rel="preconnect" href="https://cdn-images.dzcdn.net">
  <link rel="stylesheet" href="/style/site.css?v=<?= filemtime(__DIR__ . '/style/site.css') ?>">
  <link rel="stylesheet" href="/style/spotify.css?v=<?= filemtime(__DIR__ . '/style/spotify.css') ?>">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="chart-shell">
  <section class="chart-hero">
    <div class="hero-copy">
      <p class="eyebrow">Spotify artists chart</p>
      <h1>Ranking Global do Spotify</h1>
      <p>Top 30 artistas por ouvintes mensais, com histórico de pico, semanas no ranking e movimento da última atualização.</p>
    </div>

    <?php if ($leader): ?>
      <?php
        $leaderName = (string)($leader['artist'] ?? 'Artista sem nome');
        $leaderImage = $leader['image'] ?: '/assets/logo_spotify.png';
      ?>
      <aside class="leader-card" aria-label="Lider do ranking">
        <img src="<?= htmlspecialchars($leaderImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($leaderName, ENT_QUOTES, 'UTF-8') ?>" loading="eager">
        <div>
          <span>#1 desta semana</span>
          <strong><?= htmlspecialchars($leaderName, ENT_QUOTES, 'UTF-8') ?></strong>
          <small><?= compact_listeners($leader['listeners'] ?? 0) ?> ouvintes mensais</small>
        </div>
      </aside>
    <?php endif; ?>
  </section>

  <?php if ($notice): ?>
    <div class="notice notice-<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <section class="chart-summary" aria-label="Resumo do ranking">
    <article>
      <span>Periodo</span>
      <strong><?= htmlspecialchars($weekLabel, ENT_QUOTES, 'UTF-8') ?></strong>
    </article>
    <article>
      <span>Ranking</span>
      <strong>Top <?= count($ranking) ?: 30 ?></strong>
    </article>
    <article>
      <span>Última atualização</span>
      <strong><?= htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8') ?></strong>
    </article>
    <div class="summary-actions">
      <a class="refresh-button muted" href="/spotify-history.php">Ver histórico</a>
      <?php if ($isAdmin): ?>
        <a class="refresh-button" href="/spotify.php?refresh=1">Atualizar agora</a>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($ranking): ?>
    <section class="podium" aria-label="Top 3 artistas">
      <?php foreach ($podium as $artist): ?>
        <?php
          $rank = (int)($artist['rank'] ?? 0);
          $image = $artist['image'] ?: '/assets/logo_spotify.png';
          $name = (string)($artist['artist'] ?? 'Artista sem nome');
        ?>
        <article class="podium-card podium-<?= $rank ?>">
          <span class="rank-pill">#<?= $rank ?></span>
          <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
          <div>
            <h2><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= compact_listeners($artist['listeners'] ?? 0) ?> ouvintes mensais</p>
            <span class="movement <?= movement_class($artist) ?>"><?= htmlspecialchars(movement_label($artist), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="chart-table-card" aria-labelledby="chart-table-title">
      <div class="table-head">
        <div>
          <p class="eyebrow">Chart completo</p>
          <h2 id="chart-table-title">Posicoes 4 a 30</h2>
        </div>
        <span><?= count($chartRows) ?> artistas</span>
      </div>

      <ol class="chart-list" start="4">
        <?php foreach ($chartRows as $artist): ?>
          <?php
            $rank = (int)($artist['rank'] ?? 0);
            $image = $artist['image'] ?: '/assets/logo_spotify.png';
            $name = (string)($artist['artist'] ?? 'Artista sem nome');
          ?>
          <li class="chart-item">
            <div class="rank-box"><?= $rank ?></div>
            <img class="artist-art" src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
            <div class="artist-info">
              <h3 class="artist-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h3>
              <p class="listeners"><?= format_listeners($artist['listeners'] ?? 0) ?> ouvintes mensais</p>
            </div>
            <span class="movement <?= movement_class($artist) ?>"><?= htmlspecialchars(movement_label($artist), ENT_QUOTES, 'UTF-8') ?></span>
            <div class="stats-block" aria-label="Estatisticas de <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
              <div class="stat"><span class="stat-label">LW</span><span class="stat-value"><?= htmlspecialchars((string)($artist['lw'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
              <div class="stat"><span class="stat-label">Pico</span><span class="stat-value"><?= htmlspecialchars((string)($artist['peak'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
              <div class="stat"><span class="stat-label">Semanas</span><span class="stat-value"><?= htmlspecialchars((string)($artist['weeks'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php else: ?>
    <div class="notice">Não foi possível carregar o ranking. Use "Atualizar agora" para gerar o JSON.</div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
