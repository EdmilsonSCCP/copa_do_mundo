<?php
require __DIR__ . '/includes/auth_boot.php';
require_login();

$pageTitle = 'Central Copa do Mundo 2026';
$pageCss = ['/style/worldcup.css'];
$pageScripts = ['/scripts/worldcup.js'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> | Le Group</title>
  <link rel="preconnect" href="https://flagcdn.com">
  <link rel="stylesheet" href="/style/site.css">
  <link rel="stylesheet" href="/style/worldcup.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="wc-shell" id="top">
  <section class="wc-hero" aria-labelledby="wc-title">
    <div class="wc-hero-copy">
      <p class="eyebrow">Simulador interativo</p>
      <h1 id="wc-title">Copa do Mundo 2026</h1>
      <p class="hero-text">Classificação, jogos, probabilidades, melhores terceiros, mata-mata e projeções em uma central limpa para acompanhar o torneio.</p>
      <div class="hero-actions">
        <button class="btn primary" type="button" data-action="autofill">Preencher projeção</button>
        <button class="btn ghost" type="button" data-action="clear">Limpar placares</button>
      </div>
    </div>
    <div class="wc-hero-panel" aria-label="Resumo do torneio">
      <span>48 seleções</span>
      <strong>12 grupos</strong>
      <small>Top 2 + 8 melhores terceiros avançam</small>
    </div>
  </section>

  <section class="wc-controls" aria-label="Filtros do dashboard">
    <label>
      <span>Buscar seleção</span>
      <input id="wcSearch" type="search" placeholder="Brasil, França, Japão...">
    </label>
    <label>
      <span>Grupo</span>
      <select id="wcGroup">
        <option value="">Todos os grupos</option>
      </select>
    </label>
    <button class="btn theme" type="button" data-action="theme">Tema escuro</button>
  </section>

  <nav class="wc-tabs" aria-label="Seções da Copa">
    <button class="tab active" type="button" data-tab="standings">Classificação</button>
    <button class="tab" type="button" data-tab="schedule">Horários e probabilidades</button>
    <button class="tab" type="button" data-tab="matches">Simulador</button>
    <button class="tab" type="button" data-tab="fantasy">Bolao</button>
    <button class="tab" type="button" data-tab="knockout">Mata-mata</button>
    <button class="tab" type="button" data-tab="stats">Estatísticas</button>
  </nav>

  <section class="tab-panel" id="standings" data-panel="standings">
    <div class="section-head">
      <div>
        <p class="eyebrow">Fase de grupos</p>
        <h2>Classificação por grupo</h2>
      </div>
      <p>Critérios usados: pontos, saldo, gols pró e rating estimado.</p>
    </div>
    <div id="wcStandings" class="standings-grid"></div>
    <h2 class="subsection-title">Melhores terceiros</h2>
    <div id="wcThirds" class="table-card"></div>
  </section>

  <section class="tab-panel hidden" id="schedule" data-panel="schedule">
    <div class="section-head">
      <div>
        <p class="eyebrow">Agenda</p>
        <h2>Horários e probabilidades</h2>
      </div>
      <p>Horários em Brasília e favorito estimado por rating relativo.</p>
    </div>
    <div id="wcSchedule" class="schedule-groups"></div>
  </section>

  <section class="tab-panel hidden" id="matches" data-panel="matches">
    <div class="section-head">
      <div>
        <p class="eyebrow">Cenários</p>
        <h2>Simulador dos jogos</h2>
      </div>
    </div>
    <div id="wcMatches" class="matches-grid"></div>
  </section>

  <section class="tab-panel hidden" id="fantasy" data-panel="fantasy">
    <div class="section-head">
      <div>
        <p class="eyebrow">Mundo fantasia</p>
        <h2>Bolao dos amigos</h2>
      </div>
    </div>
    <div id="wcFantasy" class="fantasy-board"></div>
  </section>

  <section class="tab-panel hidden" id="knockout" data-panel="knockout">
    <div class="section-head">
      <div>
        <p class="eyebrow">Chaveamento</p>
        <h2>Mata-mata projetado</h2>
      </div>
      <p>As vagas de grupo e melhores terceiros são preenchidas conforme a tabela atual.</p>
    </div>
    <div id="wcKnockout" class="knockout-board"></div>
  </section>

  <section class="tab-panel hidden" id="stats" data-panel="stats">
    <div class="section-head">
      <div>
        <p class="eyebrow">Projeções</p>
        <h2>Estatísticas avançadas</h2>
      </div>
      <p>Probabilidades derivadas de rating, posição atual e caminho estimado.</p>
    </div>
    <div id="wcStats" class="stats-grid"></div>
  </section>
</main>

<?php include __DIR__ . '/footer.php'; ?>
<script src="/scripts/worldcup.js?v=<?= filemtime(__DIR__ . '/scripts/worldcup.js') ?>" defer></script>
</body>
</html>
