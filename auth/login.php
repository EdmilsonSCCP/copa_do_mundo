<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth_boot.php';

// Se jÃ¡ estiver logado, manda para a home
if (current_user()) {
    header('Location: /index.php');
    exit;
}

$erro = '';

// Processa POST (login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $senha    = $_POST['senha'] ?? '';
    $remember = isset($_POST['remember']);
    $token    = $_POST['csrf'] ?? '';

    if (!csrf_check($token)) {
        $erro = 'SessÃ£o expirada. Recarregue a pÃ¡gina.';
    } elseif ($email === '' || $senha === '') {
        $erro = 'Informe e-mail e senha.';
    } else {
        // Busca usuÃ¡rio
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        $now = new DateTimeImmutable('now');

        if ($user) {
            // bloqueio temporÃ¡rio
            if (!empty($user['locked_until']) && $now < new DateTimeImmutable($user['locked_until'])) {
                $erro = 'Conta temporariamente bloqueada. Tente novamente em alguns minutos.';
            } elseif (!password_verify($senha, $user['senha'])) {
                // Falha de senha
                $fails = (int)$user['failed_attempts'] + 1;
                $lockUntil = null;

                if ($fails >= 5) {
                    $lockUntil = $now->modify('+15 minutes')->format('Y-m-d H:i:s');
                    $fails = 0;
                }

                $upd = $db->prepare("UPDATE usuarios
                                       SET failed_attempts = :f, locked_until = :l
                                     WHERE id = :id");
                $upd->execute([':f' => $fails, ':l' => $lockUntil, ':id' => $user['id']]);

                $erro = 'E-mail ou senha invÃ¡lidos.';
            } else {
                // Senha OK
                $upd = $db->prepare("UPDATE usuarios
                                       SET failed_attempts = 0,
                                           locked_until = NULL,
                                           last_login = NOW()
                                     WHERE id = :id");
                $upd->execute([':id' => $user['id']]);

                $_SESSION['user'] = [
                    'id'    => $user['id'],
                    'nome'  => $user['nome'],
                    'email' => $user['email'],
                    'role'  => $user['role'],
                ];

                if ($remember) {
                    $rememberToken = bin2hex(random_bytes(32));
                    $expiresAt = (new DateTimeImmutable('now'))
                        ->modify('+' . REMEMBER_LIFETIME . ' seconds')
                        ->format('Y-m-d H:i:s');

                    $upd2 = $db->prepare("UPDATE usuarios
                                             SET remember_token = :t,
                                                 remember_expires = :e
                                           WHERE id = :id");
                    $upd2->execute([':t' => $rememberToken, ':e' => $expiresAt, ':id' => $user['id']]);

                    setcookie('remember', $rememberToken, [
                        'expires'  => time() + REMEMBER_LIFETIME,
                        'path'     => '/',
                        'secure'   => $cookieSecure,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

                header('Location: /index.php');
                exit;
            }
        } else {
            $erro = 'E-mail ou senha invÃ¡lidos.';
        }
    }
}

// Token CSRF
$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="dark">
<title>Entrar</title>
<style>
    :root{
        --bg:#000;          /* fundo tela */
        --card:#111;        /* caixa */
        --text:#fff;        /* texto padrÃ£o */
        --muted:#aaa;       /* texto secundÃ¡rio */
        --input-bg:#000;    /* fundo input */
        --input-bd:#333;    /* borda input */
        --ring:rgba(255,255,255,.08);
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{
        margin:0;
        font-family:system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Arial, "Noto Sans", sans-serif;
        background:var(--bg);
        color:var(--text);
        display:grid;
        place-items:center;
        padding:24px;
    }

    .card{
        width:min(92vw, 720px);
        background:var(--card);
        padding:48px 28px 36px;
        border-radius:18px;
        box-shadow:0 10px 35px var(--ring);
    }

    .logo{
        display:block;
        margin:0 auto 18px;
        width:132px;
        height:auto;
        object-fit:contain;
        filter:drop-shadow(0 2px 8px rgba(0,0,0,.5));
    }

    h1{
        text-align:center;
        margin:6px 0 26px;
        font-size:28px;
        color:var(--text);
    }

    .field{margin:12px 0}
    .input{
        width:100%;
        padding:14px 16px;
        border-radius:10px;
        border:1px solid var(--input-bd);
        background:var(--input-bg);
        color:var(--text);
        outline:none;
        font-size:16px;
        transition:border .2s, box-shadow .2s;
    }
    .input::placeholder{color:#777}
    .input:focus{
        border-color:#666;
        box-shadow:0 0 0 4px rgba(255,255,255,.06);
    }

    .row{
        display:flex;
        align-items:center;
        justify-content:space-between;
        margin-top:6px;
        gap:12px;
        flex-wrap:wrap;
    }

    label{font-size:14px;color:var(--muted);display:flex;align-items:center;gap:8px}
    a{color:#ddd;text-decoration:none}
    a:hover{color:#fff}

    .btn{
        width:100%;
        padding:14px 18px;
        margin-top:18px;
        border:1px solid #fff;
        border-radius:12px;
        font-size:16px;
        font-weight:700;
        background:#fff;       /* branco */
        color:#000;            /* texto preto */
        cursor:pointer;
        transition:.25s ease;
    }
    .btn:hover{
        background:#000;       /* inverte no hover */
        color:#fff;
        border-color:#fff;
    }

    .error{
        background:#2b1111;
        color:#ffb7b7;
        border:1px solid #611b1b;
        padding:12px 14px;
        border-radius:10px;
        margin-bottom:12px;
        font-size:14px;
    }

    .muted{text-align:center;color:var(--muted);font-size:14px;margin-top:16px}
    .muted a{color:#ddd}
</style>
</head>
<body>
<main class="card">
    <img src="/assets/logo.png" alt="LE GROUP" class="logo">
    <h1>Entrar</h1>

    <?php if ($erro): ?>
        <div class="error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">

        <div class="field">
            <input class="input" type="email" name="email" placeholder="UsuÃ¡rio (e-mail)" autocomplete="username" required autofocus>
        </div>

        <div class="field">
            <input class="input" type="password" name="senha" placeholder="Senha" autocomplete="current-password" required>
        </div>

        <div class="row">
            <label>
                <input type="checkbox" name="remember" value="1">
                Lembrar-me por 12 horas
            </label>
            <a href="/auth/forgot.php" class="muted">Esqueci minha senha</a>
        </div>

        <button class="btn" type="submit">Entrar</button>
    </form>

    <p class="muted">Ainda nÃ£o tem conta? <a href="/auth/register.php">Criar conta</a></p>
</main>
</body>
</html>


