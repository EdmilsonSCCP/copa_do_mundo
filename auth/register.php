<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth_boot.php';

if (current_user()) {
    header('Location: /index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha2 = $_POST['senha2'] ?? '';

    if (!csrf_check($token)) {
        $erro = 'Sessão expirada. Recarregue a página.';
    } elseif (!recaptcha_check()) {
        $erro = 'Confirme que voce nao e um robo.';
    } elseif ($nome === '' || mb_strlen($nome) < 3) {
        $erro = 'Informe um nome valido com pelo menos 3 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail valido.';
    } elseif (mb_strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($senha !== $senha2) {
        $erro = 'As senhas nao coincidem.';
    } else {
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);

        if ($stmt->fetch()) {
            $erro = 'Ja existe uma conta com este e-mail.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $ins = $db->prepare(
                'INSERT INTO usuarios (nome, email, senha, role, created_at)
                 VALUES (:n, :e, :s, :r, NOW())'
            );

            $ok = $ins->execute([
                ':n' => $nome,
                ':e' => $email,
                ':s' => $hash,
                ':r' => 'user',
            ]);

            if ($ok) {
                $_SESSION['flash_ok'] = 'Conta criada com sucesso! Faca login.';
                header('Location: /auth/login.php');
                exit;
            }

            $erro = 'Não foi possível criar a conta. Tente novamente.';
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
<title>Criar conta | Le Group</title>
<?= recaptcha_script() ?>
<?= analytics_head() ?>
<style>
    :root {
        --bg: #05080f;
        --card: #171112;
        --text: #f1f5f9;
        --muted: #b9aaa4;
        --input: #0f0b0c;
        --border: #3a262a;
        --primary: #ffffff;
        --primary-hover: #ffe9e3;
        --accent: #e63b3f;
        --ring: rgba(230,59,63,.18);
        --danger-bg: #2b1212;
        --danger: #fca5a5;
        --ok-bg: #10291b;
        --ok: #86efac;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        min-height: 100dvh;
        color: var(--text);
        font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, "Helvetica Neue", Arial, sans-serif;
        background:
            radial-gradient(900px 520px at 50% -20%, rgba(230,59,63,.22), transparent 62%),
            linear-gradient(180deg, #170c0e 0%, #050505 70%);
    }

    .auth-shell {
        min-height: 100dvh;
        display: grid;
        place-items: center;
        padding: 28px 16px;
    }

    .card {
        width: min(94vw, 760px);
        padding: 34px;
        border: 1px solid var(--border);
        border-radius: 16px;
        background: linear-gradient(180deg, rgba(23,17,18,.98), rgba(10,8,8,.98));
        box-shadow: 0 22px 70px rgba(0,0,0,.36), 0 0 0 1px var(--ring);
    }

    .brand {
        margin-bottom: 26px;
        text-align: center;
    }

    .logo {
        display: block;
        width: 116px;
        height: auto;
        margin: 0 auto 14px;
        object-fit: contain;
    }

    h1 {
        margin: 0;
        font-size: 32px;
        letter-spacing: 0;
    }

    .lead {
        max-width: 520px;
        margin: 8px auto 0;
        color: var(--muted);
        line-height: 1.5;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .input {
        width: 100%;
        padding: 15px 16px;
        border: 1px solid var(--border);
        border-radius: 10px;
        outline: none;
        background: var(--input);
        color: var(--text);
        font-size: 16px;
    }

    .input::placeholder { color: #7c8da5; }

    .input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 4px rgba(230,59,63,.12);
    }

    .btn {
        width: 100%;
        margin-top: 18px;
        padding: 14px 18px;
        border: 0;
        border-radius: 12px;
        background: var(--primary);
        color: #170c0e;
        cursor: pointer;
        font-size: 16px;
        font-weight: 800;
    }

    .btn:hover { background: var(--primary-hover); }

    .muted {
        margin-top: 16px;
        color: var(--muted);
        font-size: 14px;
        text-align: center;
    }

    .error,
    .ok {
        margin-bottom: 14px;
        padding: 11px 12px;
        border-radius: 10px;
        font-size: 14px;
    }

    .error { background: var(--danger-bg); color: var(--danger); }
    .ok { background: var(--ok-bg); color: var(--ok); }
    .recaptcha-wrap { margin-top: 16px; display: flex; justify-content: center; }

    a {
        color: #fff;
        font-weight: 800;
        text-decoration: none;
    }

    @media (max-width: 640px) {
        .card { padding: 26px 18px; }
        .form-grid { grid-template-columns: 1fr; }
        h1 { font-size: 28px; }
    }
</style>
</head>
<body>
<main class="auth-shell">
    <section class="card">
        <div class="brand">
            <img src="/assets/logo.png" alt="Logo" class="logo">
            <h1>Criar conta</h1>
            <p class="lead">Entre no Le Group para participar do bolão, acompanhar a Copa e ver o ranking com seus amigos.</p>
        </div>

        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_ok'])): ?>
            <div class="ok"><?= htmlspecialchars($_SESSION['flash_ok'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php unset($_SESSION['flash_ok']); ?>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">

            <div class="form-grid">
                <input class="input" type="text" name="nome" placeholder="Seu nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input class="input" type="email" name="email" placeholder="E-mail" autocomplete="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input class="input" type="password" name="senha" placeholder="Senha (min. 8 caracteres)" autocomplete="new-password" required>
                <input class="input" type="password" name="senha2" placeholder="Confirmar senha" autocomplete="new-password" required>
            </div>

            <?= recaptcha_widget() ?>
            <button class="btn" type="submit">Criar conta</button>
        </form>

        <p class="muted">Ja tem conta? <a href="/auth/login.php">Entrar</a></p>
    </section>
</main>
</body>
</html>
