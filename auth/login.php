<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth_boot.php';

if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}

$erro = '';
$ok = $_SESSION['flash_ok'] ?? '';
unset($_SESSION['flash_ok']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $remember = isset($_POST['remember']);
    $token = $_POST['csrf'] ?? '';

    if (!csrf_check($token)) {
        $erro = 'Sessão expirada. Recarregue a página.';
    } elseif (!recaptcha_check()) {
        $erro = 'Confirme que voce nao e um robo.';
    } elseif ($email === '' || $senha === '') {
        $erro = 'Informe e-mail e senha.';
    } else {
        $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        $now = app_now();

        if ($user) {
            if (!empty($user['locked_until']) && $now < new DateTimeImmutable($user['locked_until'])) {
                $erro = 'Conta temporariamente bloqueada. Tente novamente em alguns minutos.';
            } elseif (!password_verify($senha, $user['senha'])) {
                $fails = (int)$user['failed_attempts'] + 1;
                $lockUntil = null;

                if ($fails >= 5) {
                    $lockUntil = $now->modify('+15 minutes')->format('Y-m-d H:i:s');
                    $fails = 0;
                }

                $upd = $db->prepare(
                    'UPDATE usuarios
                        SET failed_attempts = :f,
                            locked_until = :l
                      WHERE id = :id'
                );
                $upd->execute([':f' => $fails, ':l' => $lockUntil, ':id' => $user['id']]);

                $erro = 'E-mail ou senha invalidos.';
            } else {
                $upd = $db->prepare(
                    'UPDATE usuarios
                        SET failed_attempts = 0,
                            locked_until = NULL,
                            last_login = NOW()
                      WHERE id = :id'
                );
                $upd->execute([':id' => $user['id']]);

                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'nome' => $user['nome'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                ];

                if ($remember) {
                    $rememberToken = bin2hex(random_bytes(32));
                    $expiresAt = app_now()
                        ->modify('+' . REMEMBER_LIFETIME . ' seconds')
                        ->format('Y-m-d H:i:s');

                    $upd2 = $db->prepare(
                        'UPDATE usuarios
                            SET remember_token = :t,
                                remember_expires = :e
                          WHERE id = :id'
                    );
                    $upd2->execute([':t' => $rememberToken, ':e' => $expiresAt, ':id' => $user['id']]);

                    setcookie('remember', $rememberToken, [
                        'expires' => time() + REMEMBER_LIFETIME,
                        'path' => '/',
                        'secure' => $cookieSecure,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }

                header('Location: /dashboard.php');
                exit;
            }
        } else {
            $erro = 'E-mail ou senha invalidos.';
        }
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="dark">
<title>Entrar | Le Group</title>
<?= recaptcha_script() ?>
<?= analytics_head() ?>
<link rel="stylesheet" href="/style/auth.css?v=<?= filemtime(__DIR__ . '/../style/auth.css') ?>">
</head>
<body>
<main class="auth-shell login-shell">
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-brand">
            <img src="/assets/logo.png" alt="LE GROUP" class="logo">
            <p class="eyebrow">Central Le Group</p>
            <h1 id="login-title">Entre na sua conta</h1>
            <p class="lead">Acesse a Copa, o bolão dos amigos e o ranking Spotify em um só lugar.</p>
            <div class="login-highlights" aria-label="Recursos do site">
                <span>Copa 2026</span>
                <span>Bolao</span>
                <span>Spotify Charts</span>
            </div>
        </div>

        <div class="login-panel">
            <div class="form-heading">
                <span>Bem-vindo de volta</span>
                <strong>Login</strong>
            </div>

            <?php if ($erro): ?>
                <div class="error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($ok): ?>
                <div class="ok"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">

                <label class="field-label" for="email">E-mail</label>
                <input id="email" class="input" type="email" name="email" placeholder="seu@email.com" autocomplete="username" required autofocus>

                <label class="field-label" for="senha">Senha</label>
                <input id="senha" class="input" type="password" name="senha" placeholder="Digite sua senha" autocomplete="current-password" required>

                <div class="form-row">
                    <label class="check-label">
                        <input type="checkbox" name="remember" value="1">
                        <span>Lembrar por 12 horas</span>
                    </label>
                    <a href="/auth/forgot.php">Esqueci minha senha</a>
                </div>

                <?= recaptcha_widget() ?>
                <button class="btn" type="submit">Entrar</button>
            </form>

            <p class="muted">Ainda nao tem conta? <a href="/auth/register.php">Criar conta</a></p>
        </div>
    </section>
</main>
</body>
</html>
