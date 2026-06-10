<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth_boot.php';

$user = current_user();
$worldCup = json_decode((string)file_get_contents(__DIR__ . '/data/world-cup-2026.json'), true);
$matches = is_array($worldCup['matches'] ?? null) ? $worldCup['matches'] : [];
$ranking = is_file(__DIR__ . '/top30_display.json')
    ? json_decode((string)file_get_contents(__DIR__ . '/top30_display.json'), true)
    : [];
if (!is_array($ranking)) {
    $ranking = [];
}

function home_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function home_match_time(array $match): DateTimeImmutable
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $date = DateTimeImmutable::createFromFormat('d/m/Y H:i', $match['date'] . ' ' . $match['time'], $tz);
    return $date ?: new DateTimeImmutable('2999-01-01', $tz);
}

$now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$nextMatches = array_values(array_filter($matches, static fn(array $match): bool => home_match_time($match) >= $now));
usort($nextMatches, static fn(array $a, array $b): int => home_match_time($a) <=> home_match_time($b));
$nextMatches = array_slice($nextMatches, 0, 3);
$spotifyTop = array_slice($ranking, 0, 3);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Le Group | Copa 2026 e Spotify</title>
  <?= analytics_head() ?>
  <link rel="stylesheet" href="/style/site.css?v=<?= filemtime(__DIR__ . '/style/site.css') ?>">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="home-shell">
  <section class="home-hero">
    <div class="home-copy">
      <p class="eyebrow">Le Group</p>
      <h1>Copa do Mundo, bolão dos amigos e Spotify em um só lugar.</h1>
      <p>Entre para simular os jogos, dar seus palpites, acompanhar o ranking da galera e ver o Top Spotify atualizado.</p>
      <div class="home-actions">
        <?php if ($user): ?>
          <a class="dash-btn primary" href="/dashboard.php">Abrir meu painel</a>
          <a class="dash-btn" href="/copa.php#fantasy">Ir para o bolão</a>
        <?php else: ?>
          <a class="dash-btn primary" href="/auth/register.php">Criar conta</a>
          <a class="dash-btn" href="/auth/login.php">Entrar</a>
        <?php endif; ?>
      </div>
    </div>
    <aside class="home-scorecard">
      <span>Temporada 2026</span>
      <strong>48 seleções</strong>
      <small>Simulador, mata-mata, bolão e ranking dos amigos.</small>
    </aside>
  </section>

  <section class="home-grid">
    <article class="home-card">
      <p class="eyebrow">Próximos jogos</p>
      <h2>Copa 2026</h2>
      <div class="home-list">
        <?php foreach ($nextMatches as $match): ?>
          <a href="<?= $user ? '/copa.php#matches' : '/auth/register.php' ?>">
            <span>Grupo <?= home_h($match['group']) ?> - <?= home_h($match['date']) ?> <?= home_h($match['time']) ?></span>
            <strong><?= home_h($match['team1']) ?> x <?= home_h($match['team2']) ?></strong>
          </a>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="home-card feature-card">
      <p class="eyebrow">Bolão</p>
      <h2>Palpites com pontuação</h2>
      <p>Acertou o placar exato, soma 3 pontos. Acertou o vencedor, soma 2. Tudo fica na sua conta e aparece no ranking dos amigos.</p>
      <a class="dash-btn primary" href="<?= $user ? '/copa.php#fantasy' : '/auth/register.php' ?>">Participar</a>
    </article>

    <article class="home-card">
      <p class="eyebrow">Spotify</p>
      <h2>Top 3 global</h2>
      <div class="home-spotify">
        <?php foreach ($spotifyTop as $artist): ?>
          <?php $image = $artist['image'] ?: '/assets/logo_spotify.png'; ?>
          <a href="<?= $user ? '/spotify.php' : '/auth/register.php' ?>">
            <img src="<?= home_h($image) ?>" alt="<?= home_h($artist['artist'] ?? 'Artista') ?>" loading="lazy">
            <span>#<?= (int)($artist['rank'] ?? 0) ?></span>
            <strong><?= home_h($artist['artist'] ?? 'Artista') ?></strong>
          </a>
        <?php endforeach; ?>
      </div>
    </article>
  </section>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
