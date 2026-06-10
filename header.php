<?php
require_once __DIR__ . '/includes/auth_boot.php';
$user = current_user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

function nav_active(string $path, string $currentPath): string
{
    return $path === $currentPath ? ' active' : '';
}
?>
<header class="site-header">
  <div class="site-header__inner">
    <a class="brand" href="<?= $user ? '/dashboard.php' : '/index.php' ?>" aria-label="Ir para o início">
      <img src="/assets/logo.png" alt="Le Group" class="brand__logo">
      <span>
        <strong>Le Group</strong>
        <small>Copa 2026 + Spotify Charts</small>
      </span>
    </a>

    <button class="menu-toggle" type="button" aria-label="Abrir menu" aria-expanded="false" data-menu-toggle>
      <span></span>
      <span></span>
      <span></span>
    </button>

    <nav class="main-nav" aria-label="Navegação principal" data-main-nav>
      <?php if ($user): ?>
        <a class="<?= 'nav-link' . nav_active('/dashboard.php', $currentPath) ?>" href="/dashboard.php">Início</a>
      <?php else: ?>
        <a class="<?= 'nav-link' . nav_active('/index.php', $currentPath) ?>" href="/index.php">Início</a>
      <?php endif; ?>
      <a class="<?= 'nav-link' . nav_active('/copa.php', $currentPath) ?>" href="/copa.php">Copa do Mundo</a>
      <a class="<?= 'nav-link' . nav_active('/spotify.php', $currentPath) ?>" href="/spotify.php">Spotify</a>
      <?php if ($user && is_admin_user($user)): ?>
        <a class="<?= 'nav-link' . nav_active('/admin/index.php', $currentPath) ?>" href="/admin/index.php">Admin</a>
      <?php endif; ?>
    </nav>

    <div class="user-area">
      <?php if ($user): ?>
        <span class="hello">Olá, <strong><?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?></strong></span>
        <a class="account-link" href="/account/index.php">Minha conta</a>
        <a class="account-link" href="/auth/logout.php">Sair</a>
      <?php else: ?>
        <a class="account-link" href="/auth/login.php">Entrar</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<script>
  (() => {
    const button = document.querySelector('[data-menu-toggle]');
    const nav = document.querySelector('[data-main-nav]');
    if (!button || !nav) return;

    button.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  })();
</script>
