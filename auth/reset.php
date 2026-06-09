<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth_boot.php';

if (current_user()) { header('Location:/index.php'); exit; }

$erro=''; $ok='';
$token = $_GET['token'] ?? '';

if ($token === '') { $erro = 'Token inválido.'; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $csrf   = $_POST['csrf'] ?? '';
    $token  = $_POST['token'] ?? '';
    $senha  = $_POST['senha'] ?? '';
    $senha2 = $_POST['senha2'] ?? '';

    if (!csrf_check($csrf)) {
        $erro = 'Sessão expirada. Recarregue a página.';
    } elseif ($token === '') {
        $erro = 'Token ausente.';
    } elseif (mb_strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($senha !== $senha2) {
        $erro = 'As senhas não coincidem.';
    } else {
        // valida token/expiração
        $q = $db->prepare('SELECT id, reset_expires FROM usuarios WHERE reset_token = :t LIMIT 1');
        $q->execute([':t'=>$token]);
        $u = $q->fetch();

        if (!$u) {
            $erro = 'Token inválido ou já utilizado.';
        } elseif (new DateTimeImmutable('now') > new DateTimeImmutable($u['reset_expires'])) {
            $erro = 'Token expirado. Solicite novamente.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $up = $db->prepare('UPDATE usuarios
                                   SET senha = :s,
                                       reset_token = NULL,
                                       reset_expires = NULL,
                                       failed_attempts = 0,
                                       locked_until = NULL
                                 WHERE id = :id');
            $up->execute([':s'=>$hash, ':id'=>$u['id']]);

            $_SESSION['flash_ok'] = 'Senha redefinida com sucesso. Faça login.';
            header('Location:/auth/login.php'); exit;
        }
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Redefinir senha</title>
<?= analytics_head() ?>
<style>
    :root{--bg:#0b0b0c;--card:#121214;--text:#f1f5f9;--muted:#9aa4b2;--input:#1c1d20;--border:#2a2b31;--primary:#000;--primary-hover:#111;--ring:rgba(255,255,255,.06);
          --danger-bg:#2b1212;--danger:#fca5a5}
    *{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;
    min-height:100dvh;display:grid;place-items:center;background:radial-gradient(1000px 600px at 50% -10%,#1a1b21 0,#0b0b0c 70%);color:var(--text)}
    .card{width:min(92vw,720px);background:var(--card);padding:48px 28px 36px;border-radius:18px;box-shadow:0 12px 40px var(--ring);border:1px solid var(--border)}
    .logo{display:block;margin:0 auto 18px;width:140px;height:auto;object-fit:contain}
    h1{text-align:center;margin:6px 0 26px;font-size:28px}
    .field{margin:12px 0}.input{width:100%;padding:14px 16px;border-radius:10px;border:1px solid var(--border);background:var(--input);color:var(--text);outline:none;font-size:16px}
    .input::placeholder{color:#768194}.input:focus{border-color:#3b3c42;box-shadow:0 0 0 4px rgba(255,255,255,.03)}
    .btn{width:100%;padding:14px 18px;margin-top:18px;border:0;border-radius:12px;font-size:16px;font-weight:700;color:#fff;background:var(--primary);cursor:pointer}
    .btn:hover{background:var(--primary-hover)} .error{background:var(--danger-bg);color:var(--danger);padding:10px 12px;border-radius:10px;margin-bottom:10px;font-size:14px}
    a{color:#fff;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<main class="card">
    <img src="/assets/logo.png" class="logo" alt="Logo">
    <h1>Redefinir senha</h1>

    <?php if ($erro): ?><div class="error"><?= htmlspecialchars($erro,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token,ENT_QUOTES,'UTF-8') ?>">

        <div class="field">
            <input class="input" type="password" name="senha" placeholder="Nova senha (mín. 8 caracteres)" autocomplete="new-password" required>
        </div>
        <div class="field">
            <input class="input" type="password" name="senha2" placeholder="Confirmar nova senha" autocomplete="new-password" required>
        </div>

        <button class="btn" type="submit">Salvar nova senha</button>
    </form>

    <p class="muted" style="text-align:center;margin-top:14px"><a href="/auth/login.php">Voltar ao login</a></p>
</main>
</body>
</html>
