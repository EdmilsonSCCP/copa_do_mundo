<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth_boot.php';

/**
 * SE VOCÊ JÁ ESTÁ LOGADO, VAI PARA A HOME
 */
if (current_user()) {
    header('Location: /index.php');
    exit;
}

$erro   = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token   = $_POST['csrf'] ?? '';
    $nome    = trim($_POST['nome']  ?? '');
    $email   = trim($_POST['email'] ?? '');
    $senha   = $_POST['senha']      ?? '';
    $senha2  = $_POST['senha2']     ?? '';

    // CSRF
    if (!csrf_check($token)) {
        $erro = 'Sessão expirada. Recarregue a página.';
    }
    // validações
    elseif ($nome === '' || mb_strlen($nome) < 3) {
        $erro = 'Informe um nome válido (mínimo 3 caracteres).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (mb_strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($senha !== $senha2) {
        $erro = 'As senhas não coincidem.';
    } else {
        // já existe?
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        if ($stmt->fetch()) {
            $erro = 'Já existe uma conta com este e-mail.';
        } else {
            // cria usuário (role padrão = user)
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
                // Se quiser exigir verificação de e-mail, aqui seria gerado um token
                // e enviado o link por e-mail. Por enquanto, apenas direcionamos
                // para o login com mensagem de sucesso.
                $_SESSION['flash_ok'] = 'Conta criada com sucesso! Faça login.';
                header('Location: /auth/login.php');
                exit;
            } else {
                $erro = 'Não foi possível criar a conta. Tente novamente.';
            }
        }
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Criar conta</title>
<style>
    :root{
        --bg:#0b0b0c;
        --card:#121214;
        --text:#f1f5f9;
        --muted:#9aa4b2;
        --input:#1c1d20;
        --border:#2a2b31;
        --primary:#000;        /* botão preto */
        --primary-hover:#111;
        --ring: rgba(255,255,255,.06);
        --danger-bg:#2b1212; --danger:#fca5a5;
        --ok-bg:#10291b; --ok:#86efac;
    }
    *{box-sizing:border-box}
    body{
        margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;
        min-height:100dvh; display:grid; place-items:center;
        background:radial-gradient(1000px 600px at 50% -10%, #1a1b21 0, #0b0b0c 70%);
        color:var(--text);
    }
    .card{
        width:min(92vw,720px);
        background:var(--card);
        padding:48px 28px 36px;
        border-radius:18px;
        box-shadow:0 12px 40px var(--ring);
        border:1px solid var(--border);
    }
    .logo{display:block;margin:0 auto 18px;width:140px;height:auto;object-fit:contain}
    h1{text-align:center;margin:6px 0 26px;font-size:28px}
    .field{margin:12px 0}
    .input{
        width:100%; padding:14px 16px; border-radius:10px;
        border:1px solid var(--border); background:var(--input);
        color:var(--text); outline:none; font-size:16px;
    }
    .input::placeholder{color:#768194}
    .input:focus{border-color:#3b3c42; box-shadow:0 0 0 4px rgba(255,255,255,.03)}
    .btn{
        width:100%; padding:14px 18px; margin-top:18px;
        border:0; border-radius:12px; font-size:16px; font-weight:700;
        color:#fff; background:var(--primary); cursor:pointer;
    }
    .btn:hover{background:var(--primary-hover)}
    .muted{color:var(--muted); font-size:14px; text-align:center; margin-top:16px}
    .error{background:var(--danger-bg); color:var(--danger); padding:10px 12px; border-radius:10px; margin-bottom:10px; font-size:14px}
    .ok{background:var(--ok-bg); color:var(--ok); padding:10px 12px; border-radius:10px; margin-bottom:10px; font-size:14px}
    a{color:#fff; font-weight:600; text-decoration:none}
</style>
</head>
<body>
<main class="card">
    <img src="/assets/logo.png" alt="Logo" class="logo">
    <h1>Criar conta</h1>

    <?php if ($erro): ?>
        <div class="error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_ok'])): ?>
        <div class="ok"><?= htmlspecialchars($_SESSION['flash_ok'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['flash_ok']); ?>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">

        <div class="field">
            <input class="input" type="text" name="nome" placeholder="Seu nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
            <input class="input" type="email" name="email" placeholder="E-mail" autocomplete="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
            <input class="input" type="password" name="senha" placeholder="Senha (mín. 8 caracteres)" autocomplete="new-password" required>
        </div>

        <div class="field">
            <input class="input" type="password" name="senha2" placeholder="Confirmar senha" autocomplete="new-password" required>
        </div>

        <button class="btn" type="submit">Criar conta</button>
    </form>

    <p class="muted">Já tem conta? <a href="/auth/login.php">Entrar</a></p>
</main>
</body>
</html>
