<?php
declare(strict_types=1);

require __DIR__ . '/../includes/auth_boot.php';
require_login();

$user = current_user();
$userId = (int)($user['id'] ?? 0);
$erro = '';
$ok = '';

$stmt = $db->prepare('SELECT id, nome, email, role, created_at, last_login FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: /auth/logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_check($token)) {
        $erro = 'Sessao expirada. Recarregue a pagina.';
    } elseif ($action === 'profile') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($nome === '' || mb_strlen($nome) < 3) {
            $erro = 'Informe um nome valido.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail valido.';
        } else {
            $check = $db->prepare('SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1');
            $check->execute([':email' => $email, ':id' => $userId]);

            if ($check->fetch()) {
                $erro = 'Este e-mail ja esta sendo usado.';
            } else {
                $upd = $db->prepare('UPDATE usuarios SET nome = :nome, email = :email WHERE id = :id');
                $upd->execute([':nome' => $nome, ':email' => $email, ':id' => $userId]);

                $_SESSION['user']['nome'] = $nome;
                $_SESSION['user']['email'] = $email;
                $profile['nome'] = $nome;
                $profile['email'] = $email;
                $ok = 'Dados atualizados.';
            }
        }
    } elseif ($action === 'password') {
        $current = $_POST['senha_atual'] ?? '';
        $new = $_POST['senha'] ?? '';
        $new2 = $_POST['senha2'] ?? '';

        $pass = $db->prepare('SELECT senha FROM usuarios WHERE id = :id LIMIT 1');
        $pass->execute([':id' => $userId]);
        $row = $pass->fetch();

        if (!$row || !password_verify($current, (string)$row['senha'])) {
            $erro = 'Senha atual incorreta.';
        } elseif (mb_strlen($new) < 8) {
            $erro = 'A nova senha deve ter pelo menos 8 caracteres.';
        } elseif ($new !== $new2) {
            $erro = 'As senhas nao coincidem.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $db->prepare(
                'UPDATE usuarios
                    SET senha = :senha,
                        remember_token = NULL,
                        remember_expires = NULL,
                        failed_attempts = 0,
                        locked_until = NULL
                  WHERE id = :id'
            );
            $upd->execute([':senha' => $hash, ':id' => $userId]);
            $ok = 'Senha alterada.';
        }
    }
}

$stats = [
    'predictions' => 0,
    'simulator' => 0,
];

try {
    $stmt = $db->prepare('SELECT COUNT(*) FROM fantasy_predictions WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $stats['predictions'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM simuladores WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $stats['simulator'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    // Dados auxiliares podem nao existir ainda.
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Minha conta | Le Group</title>
  <?= analytics_head() ?>
  <link rel="stylesheet" href="/style/site.css?v=<?= filemtime(__DIR__ . '/../style/site.css') ?>">
  <link rel="stylesheet" href="/style/account.css?v=<?= filemtime(__DIR__ . '/../style/account.css') ?>">
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main class="account-shell">
  <section class="account-head">
    <p class="eyebrow">Minha conta</p>
    <h1>Dados do usuario</h1>
    <p>Gerencie seus dados de acesso e seguranca.</p>
  </section>

  <?php if ($ok): ?>
    <div class="notice ok"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($erro): ?>
    <div class="notice error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <section class="account-grid">
    <article class="account-card">
      <h2>Resumo</h2>
      <dl class="profile-list">
        <div><dt>Nome</dt><dd><?= htmlspecialchars((string)$profile['nome'], ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>E-mail</dt><dd><?= htmlspecialchars((string)$profile['email'], ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>Perfil</dt><dd><?= htmlspecialchars((string)$profile['role'], ENT_QUOTES, 'UTF-8') ?></dd></div>
        <div><dt>Palpites</dt><dd><?= $stats['predictions'] ?></dd></div>
        <div><dt>Simulador</dt><dd><?= $stats['simulator'] ? 'Salvo' : 'Sem dados' ?></dd></div>
        <div><dt>Ultimo login</dt><dd><?= htmlspecialchars((string)($profile['last_login'] ?? 'Nunca'), ENT_QUOTES, 'UTF-8') ?></dd></div>
      </dl>
    </article>

    <article class="account-card">
      <h2>Editar dados</h2>
      <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="profile">
        <label>Nome
          <input class="input" type="text" name="nome" value="<?= htmlspecialchars((string)$profile['nome'], ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label>E-mail
          <input class="input" type="email" name="email" value="<?= htmlspecialchars((string)$profile['email'], ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <button class="btn" type="submit">Salvar dados</button>
      </form>
    </article>

    <article class="account-card">
      <h2>Alterar senha</h2>
      <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="password">
        <label>Senha atual
          <input class="input" type="password" name="senha_atual" autocomplete="current-password" required>
        </label>
        <label>Nova senha
          <input class="input" type="password" name="senha" autocomplete="new-password" required>
        </label>
        <label>Confirmar nova senha
          <input class="input" type="password" name="senha2" autocomplete="new-password" required>
        </label>
        <button class="btn" type="submit">Alterar senha</button>
      </form>
    </article>
  </section>
</main>

<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>
