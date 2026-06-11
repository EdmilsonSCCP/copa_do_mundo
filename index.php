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
    $tz = app_timezone();
    $date = DateTimeImmutable::createFromFormat('d/m/Y H:i', $match['date'] . ' ' . $match['time'], $tz);
    return $date ?: new DateTimeImmutable('2999-01-01', $tz);
}

$now = app_now();
$nextMatches = array_values(array_filter($matches, static fn(array $match): bool => home_match_time($match) >= $now));
usort($nextMatches, static fn(array $a, array $b): int => home_match_time($a) <=> home_match_time($b));
$nextMatches = array_slice($nextMatches, 0, 3);
$heroMatch = $nextMatches[0] ?? null;
$spotifyTop = array_slice($ranking, 0, 3);
$primaryHref = $user ? '/dashboard.php' : '/auth/register.php';
$secondaryHref = $user ? '/copa.php#fantasy' : '/auth/login.php';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Le Group | Copa 2026, bolão e Spotify</title>
  <?= analytics_head() ?>
  <link rel="stylesheet" href="/style/site.css?v=<?= filemtime(__DIR__ . '/style/site.css') ?>">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="home-shell">
  <section class="home-hero" aria-labelledby="home-title">
    <div class="home-copy">
      <p class="eyebrow">Le Group</p>
      <h1 id="home-title">A central dos amigos para viver a Copa e acompanhar música.</h1>
      <p>Simule a Copa do Mundo 2026, participe do bolão com ranking automático e acompanhe o Top Spotify em uma experiência privada, simples e organizada.</p>
      <div class="home-actions">
        <a class="dash-btn primary" href="<?= $primaryHref ?>"><?= $user ? 'Abrir meu painel' : 'Criar minha conta' ?></a>
        <a class="dash-btn" href="<?= $secondaryHref ?>"><?= $user ? 'Ir para o bolão' : 'Já tenho conta' ?></a>
      </div>
      <div class="home-trust">
        <span>Ranking por conta</span>
        <span>Palpites salvos</span>
        <span>Spotify diário</span>
      </div>
    </div>

    <aside class="home-product" aria-label="Prévia do Le Group">
      <div class="home-product-head">
        <span>Ao vivo no Le Group</span>
        <strong>Copa 2026</strong>
      </div>

      <?php if ($heroMatch): ?>
        <div class="home-match-preview">
          <small>Próximo jogo</small>
          <strong><?= home_h($heroMatch['team1']) ?> x <?= home_h($heroMatch['team2']) ?></strong>
          <span>Grupo <?= home_h($heroMatch['group']) ?> - <?= home_h($heroMatch['date']) ?> às <?= home_h($heroMatch['time']) ?></span>
        </div>
      <?php endif; ?>

      <div class="home-score-preview">
        <span>Placar exato</span>
        <strong>3 pts</strong>
        <span>Vencedor certo</span>
        <strong>2 pts</strong>
      </div>

      <a class="home-product-link" href="<?= $user ? '/copa.php#fantasy' : '/auth/register.php' ?>">Entrar no bolão</a>
    </aside>
  </section>

  <section class="home-section">
    <div class="home-section-head">
      <p class="eyebrow">O que tem dentro</p>
      <h2>Um site feito para acompanhar tudo sem planilha, grupo bagunçado ou atualização manual.</h2>
    </div>

    <div class="home-feature-grid">
      <article class="home-feature-card">
        <span>01</span>
        <h3>Simulador da Copa</h3>
        <p>Preencha os placares, veja a classificação mudar e acompanhe o caminho até o mata-mata.</p>
      </article>
      <article class="home-feature-card">
        <span>02</span>
        <h3>Bolão dos amigos</h3>
        <p>Cada usuário dá seus palpites, o sistema pontua automaticamente e o ranking fica visível para todos.</p>
      </article>
      <article class="home-feature-card">
        <span>03</span>
        <h3>Spotify Chart</h3>
        <p>Top 30 global com imagens, movimento, pico histórico e atualização diária no servidor.</p>
      </article>
    </div>
  </section>

  <section class="home-data-grid">
    <article class="home-card">
      <div class="home-card-head">
        <p class="eyebrow">Agenda</p>
        <h2>Próximos jogos</h2>
      </div>
      <div class="home-list">
        <?php foreach ($nextMatches as $match): ?>
          <a href="<?= $user ? '/copa.php#matches' : '/auth/register.php' ?>">
            <span>Grupo <?= home_h($match['group']) ?> - <?= home_h($match['date']) ?> <?= home_h($match['time']) ?></span>
            <strong><?= home_h($match['team1']) ?> x <?= home_h($match['team2']) ?></strong>
          </a>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="home-card home-card-cta">
      <div>
        <p class="eyebrow">Bolão</p>
        <h2>Seu palpite vale ranking.</h2>
        <p>O site organiza os palpites por usuário e mostra quem está liderando depois dos resultados oficiais.</p>
      </div>
      <a class="dash-btn primary" href="<?= $user ? '/copa.php#fantasy' : '/auth/register.php' ?>">Participar agora</a>
    </article>

    <article class="home-card">
      <div class="home-card-head">
        <p class="eyebrow">Spotify</p>
        <h2>Top 3 global</h2>
      </div>
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

  <section class="home-final-cta">
    <div>
      <p class="eyebrow">Acesso privado</p>
      <h2>Crie sua conta e entre no jogo.</h2>
      <p>Depois do login, cada pessoa tem painel, palpites, simulador salvo e participação no ranking.</p>
    </div>
    <a class="dash-btn primary" href="<?= $primaryHref ?>"><?= $user ? 'Abrir painel' : 'Começar agora' ?></a>
  </section>
</main>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
