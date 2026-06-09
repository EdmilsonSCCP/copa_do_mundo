<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth_boot.php';

if (current_user()) {
    header('Location: /index.php');
    exit;
}

$erro = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if (!csrf_check($token)) {
        $erro = 'Sessão expirada. Recarregue a página.';
    } elseif (!recaptcha_check()) {
        $erro = 'Confirme que voce nao e um robo.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail valido.';
    } else {
        $ok = 'Se este e-mail existir, enviaremos as instrucoes.';

        $stmt = $db->prepare('SELECT id, email FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $resetToken = bin2hex(random_bytes(32));
            $expires = (new DateTimeImmutable('now'))->modify('+60 minutes')->format('Y-m-d H:i:s');

            $upd = $db->prepare('UPDATE usuarios SET reset_token = :token, reset_expires = :expires WHERE id = :id');
            $upd->execute([
                ':token' => $resetToken,
                ':expires' => $expires,
                ':id' => $user['id'],
            ]);

            $scheme = is_https_request() ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'legroup.com.br';
            $link = "{$scheme}://{$host}/auth/reset.php?token={$resetToken}";

            if (!send_password_reset_email((string)$user['email'], $link) && PASSWORD_RESET_DEBUG_LINK) {
                $_SESSION['reset_preview_link'] = $link;
            }
        }
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$preview = $_SESSION['reset_preview_link'] ?? null;
unset($_SESSION['reset_preview_link']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recuperar senha | Le Group</title>
<?= recaptcha_script() ?>
<?= analytics_head() ?>
<link rel="stylesheet" href="/style/auth.css?v=<?= filemtime(__DIR__ . '/../style/auth.css') ?>">
</head>
<body>
<main class="auth-shell">
    <section class="card">
        <div class="brand">
            <img src="/assets/logo.png" alt="Logo" class="logo">
            <h1>Recuperar senha</h1>
            <p class="lead">Informe seu e-mail para receber um link de redefinicao.</p>
        </div>

        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($ok): ?>
            <div class="ok"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($preview): ?>
            <div class="ok debug-link">Link de teste: <a href="<?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?>">abrir reset</a></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input class="input" type="email" name="email" placeholder="Seu e-mail" autocomplete="email" required>
            <?= recaptcha_widget() ?>
            <button class="btn" type="submit">Enviar instrucoes</button>
        </form>

        <p class="muted">Lembrou? <a href="/auth/login.php">Voltar ao login</a></p>
    </section>
</main>
</body>
</html>
