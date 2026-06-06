<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth_boot.php';

if (current_user()) {
    header('Location: /index.php'); exit;
}

$erro = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf  = $_POST['csrf'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if (!csrf_check($csrf)) {
        $erro = 'Sessão expirada. Recarregue a página.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        // procura usuário
        $s = $db->prepare('SELECT id FROM usuarios WHERE email = :e LIMIT 1');
        $s->execute([':e' => $email]);
        $u = $s->fetch();

        // mensagem neutra para não expor existência de conta
        $ok = 'Se o e-mail existir, enviaremos instruções.';

        if ($u) {
            $token   = bin2hex(random_bytes(32));
            $expires = (new DateTimeImmutable('now'))->modify('+60 minutes')->format('Y-m-d H:i:s');

            $u2 = $db->prepare('UPDATE usuarios SET reset_token = :t, reset_expires = :e WHERE id = :id');
            $u2->execute([':t'=>$token, ':e'=>$expires, ':id'=>$u['id']]);

            // Aqui você enviaria e-mail. Como ainda não configuramos SMTP,
            // exibimos o link na página para teste.
            $_SESSION['reset_preview_link'] = "https://" . $_SERVER['HTTP_HOST'] . "/auth/reset.php?token={$token}";
        }
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$preview = $_SESSION['reset_preview_link'] ?? null;
unset($_SESSION['reset_preview_link']);
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Esqueci minha senha</title>
<style>
    :root{--bg:#0b0b0c;--card:#121214;--text:#f1f5f9;--muted:#9aa4b2;--input:#1c1d20;--border:#2a2b31;--primary:#000;--primary-hover:#111;--ring:rgba(255,255,255,.06);
          --danger-bg:#2b1212;--danger:#fca5a5;--ok-bg:#10291b;--ok:#86efac}
    *{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;
    min-height:100dvh;display:grid;place-items:center;background:radial-gradient(1000px 600px at 50% -10%,#1a1b21 0,#0b0b0c 70%);color:var(--text)}
    .card{width:min(92vw,720px);background:var(--card);padding:48px 28px 36px;border-radius:18px;box-shadow:0 12px 40px var(--ring);border:1px solid var(--border)}
    .logo{display:block;margin:0 auto 18px;width:140px;height:auto;object-fit:contain}
    h1{text-align:center;margin:6px 0 26px;font-size:28px}
    .field{margin:12px 0}.input{width:100%;padding:14px 16px;border-radius:10px;border:1px solid var(--border);background:var(--input);color:var(--text);outline:none;font-size:16px}
    .input::placeholder{color:#768194}.input:focus{border-color:#3b3c42;box-shadow:0 0 0 4px rgba(255,255,255,.03)}
    .btn{width:100%;padding:14px 18px;margin-top:18px;border:0;border-radius:12px;font-size:16px;font-weight:700;color:#fff;background:var(--primary);cursor:pointer}
    .btn:hover{background:var(--primary-hover)} .muted{color:var(--muted);font-size:14px;text-align:center;margin-top:16px}
    .error{background:var(--danger-bg);color:var(--danger);padding:10px 12px;border-radius:10px;margin-bottom:10px;font-size:14px}
    .ok{background:var(--ok-bg);color:var(--ok);padding:10px 12px;border-radius:10px;margin-bottom:10px;font-size:14px}
    a{color:#fff;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<main class="card">
    <img src="/assets/logo.png" class="logo" alt="Logo">
    <h1>Esqueci minha senha</h1>

    <?php if ($erro): ?><div class="error"><?= htmlspecialchars($erro,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
    <?php if ($ok):   ?><div class="ok"><?= htmlspecialchars($ok,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
    <?php if ($preview): ?><p class="ok" style="word-break:break-all;margin-top:10px">Link de teste: <a href="<?= htmlspecialchars($preview,ENT_QUOTES,'UTF-8') ?>">abrir reset</a></p><?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="field">
            <input class="input" type="email" name="email" placeholder="Seu e-mail" autocomplete="email" required>
        </div>
        <button class="btn" type="submit">Enviar instruções</button>
    </form>

    <p class="muted">Lembrou? <a href="/auth/login.php">Voltar ao login</a></p>
</main>
</body>
</html>
