<?php
declare(strict_types=1);

/**
 * Bootstrap de autenticaÃ§Ã£o
 * - SessÃ£o
 * - ConexÃ£o PDO
 * - Helpers (CSRF, usuÃ¡rio atual, lembrar-me)
 */

//////////////////////////////
// Config
//////////////////////////////
const DB_HOST = '127.0.0.1';
const DB_NAME = 'legroup_db';
const DB_USER = 'legroup';
const DB_PASS = 'Grupo20@*';   // <- sua senha do MySQL

// 12h em segundos
const REMEMBER_LIFETIME = 12 * 60 * 60;

// SeguranÃ§a (ajuste o domÃ­nio se tiver subdomÃ­nios)
const COOKIE_HTTPONLY = true;
const COOKIE_SAMESITE = 'Lax';

$localConfigPath = __DIR__ . '/local_config.php';
$localConfig = is_file($localConfigPath) ? (require $localConfigPath) : [];
if (!is_array($localConfig)) {
    $localConfig = [];
}

define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: ($localConfig['recaptcha_site_key'] ?? ''));
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: ($localConfig['recaptcha_secret_key'] ?? ''));
define('SMTP_HOST', getenv('SMTP_HOST') ?: ($localConfig['smtp_host'] ?? ''));
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: ($localConfig['smtp_port'] ?? 587)));
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: ($localConfig['smtp_encryption'] ?? 'tls'));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: ($localConfig['smtp_username'] ?? ''));
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: ($localConfig['smtp_password'] ?? ''));
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: ($localConfig['smtp_from_email'] ?? SMTP_USERNAME));
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: ($localConfig['smtp_from_name'] ?? 'Le Group'));
const PASSWORD_RESET_DEBUG_LINK = false;

function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

$cookieSecure = is_https_request();

//////////////////////////////
// SessÃ£o (segura)
//////////////////////////////
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', COOKIE_HTTPONLY ? '1' : '0');
ini_set('session.cookie_secure',   $cookieSecure ? '1' : '0');
ini_set('session.cookie_samesite', COOKIE_SAMESITE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

//////////////////////////////
// ConexÃ£o PDO
//////////////////////////////
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Erro de conexÃ£o com o banco.');
}

function ensure_auth_schema(PDO $db): void
{
    $statements = [
        "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS reset_token VARCHAR(128) NULL",
        "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL",
        "ALTER TABLE usuarios ADD INDEX IF NOT EXISTS reset_token_idx (reset_token)",
    ];

    foreach ($statements as $sql) {
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
            // Mantem a pagina viva em hospedagens que nao aceitem IF NOT EXISTS.
        }
    }
}

ensure_auth_schema($db);

//////////////////////////////
// CSRF helpers
//////////////////////////////
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

//////////////////////////////
// UsuÃ¡rio atual
//////////////////////////////
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function is_admin_user(?array $user = null): bool
{
    $user = $user ?? current_user();
    $email = strtolower((string)($user['email'] ?? ''));

    return ($user['role'] ?? '') === 'admin'
        || $email === 'junioredmilson211@gmail.com';
}

function require_admin(): void
{
    require_login();

    if (!is_admin_user()) {
        http_response_code(403);
        exit('Acesso restrito.');
    }
}

function recaptcha_enabled(): bool
{
    return RECAPTCHA_SITE_KEY !== '' && RECAPTCHA_SECRET_KEY !== '';
}

function recaptcha_script(): string
{
    return recaptcha_enabled()
        ? '<script src="https://www.google.com/recaptcha/api.js" async defer></script>'
        : '';
}

function recaptcha_widget(): string
{
    if (!recaptcha_enabled()) {
        return '';
    }

    return '<div class="recaptcha-wrap"><div class="g-recaptcha" data-sitekey="' .
        htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8') .
        '"></div></div>';
}

function recaptcha_check(): bool
{
    if (!recaptcha_enabled()) {
        return true;
    }

    $token = $_POST['g-recaptcha-response'] ?? '';
    if (!is_string($token) || $token === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 8,
        ],
    ]);

    $response = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    $data = json_decode((string)$response, true);

    return is_array($data) && ($data['success'] ?? false) === true;
}

function send_password_reset_email(string $email, string $link): bool
{
    $subject = 'Redefinir senha - Le Group';
    $message = "Recebemos um pedido para redefinir sua senha.\n\nAcesse este link por ate 60 minutos:\n{$link}\n\nSe voce nao pediu isso, ignore este e-mail.";

    if (SMTP_HOST !== '' && SMTP_USERNAME !== '' && SMTP_PASSWORD !== '') {
        return smtp_send_mail($email, $subject, $message);
    }

    $from = SMTP_FROM_EMAIL !== '' ? SMTP_FROM_EMAIL : 'no-reply@legroup.com.br';
    $headers = "From: Le Group <{$from}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, $subject, $message, $headers);
}

function smtp_send_mail(string $to, string $subject, string $message): bool
{
    $host = SMTP_HOST;
    $port = SMTP_PORT > 0 ? SMTP_PORT : 587;
    $target = SMTP_ENCRYPTION === 'ssl' ? "ssl://{$host}" : $host;
    $socket = @stream_socket_client("{$target}:{$port}", $errno, $errstr, 12, STREAM_CLIENT_CONNECT);

    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 12);

    $read = static function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $command = static function (string $line, array $expected) use ($socket, $read): bool {
        fwrite($socket, $line . "\r\n");
        $response = $read();
        $code = (int)substr($response, 0, 3);
        return in_array($code, $expected, true);
    };

    $response = $read();
    if ((int)substr($response, 0, 3) !== 220) {
        fclose($socket);
        return false;
    }

    $serverName = $_SERVER['SERVER_NAME'] ?? 'legroup.com.br';
    if (!$command("EHLO {$serverName}", [250])) {
        fclose($socket);
        return false;
    }

    if (SMTP_ENCRYPTION === 'tls') {
        if (!$command('STARTTLS', [220])) {
            fclose($socket);
            return false;
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }

        if (!$command("EHLO {$serverName}", [250])) {
            fclose($socket);
            return false;
        }
    }

    if (!$command('AUTH LOGIN', [334])
        || !$command(base64_encode(SMTP_USERNAME), [334])
        || !$command(base64_encode(SMTP_PASSWORD), [235])) {
        fclose($socket);
        return false;
    }

    $from = SMTP_FROM_EMAIL !== '' ? SMTP_FROM_EMAIL : SMTP_USERNAME;
    $fromName = SMTP_FROM_NAME !== '' ? SMTP_FROM_NAME : 'Le Group';

    if (!$command("MAIL FROM:<{$from}>", [250])
        || !$command("RCPT TO:<{$to}>", [250, 251])
        || !$command('DATA', [354])) {
        fclose($socket);
        return false;
    }

    $headers = [
        "From: {$fromName} <{$from}>",
        "Reply-To: {$from}",
        "To: <{$to}>",
        "Subject: {$subject}",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $body = str_replace(["\r\n", "\r"], "\n", $message);
    $body = preg_replace('/^\./m', '..', $body);
    fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body) . "\r\n.\r\n");

    $response = $read();
    $sent = (int)substr($response, 0, 3) === 250;
    $command('QUIT', [221]);
    fclose($socket);

    return $sent;
}

//////////////////////////////
// Auto login via cookie "remember"
//////////////////////////////
if (!current_user() && !empty($_COOKIE['remember'])) {
    $token = $_COOKIE['remember'];

    try {
        $stmt = $db->prepare(
            "SELECT id, nome, email, role, remember_expires
               FROM usuarios
              WHERE remember_token = :t
              LIMIT 1"
        );
        $stmt->execute([':t' => $token]);
        $u = $stmt->fetch();

        if ($u && !empty($u['remember_expires']) && (new DateTimeImmutable('now')) < new DateTimeImmutable($u['remember_expires'])) {
            // renova cookie (rolling expiration)
            $newToken  = bin2hex(random_bytes(32));
            $expiresAt = (new DateTimeImmutable('now'))->modify('+' . REMEMBER_LIFETIME . ' seconds')->format('Y-m-d H:i:s');

            $upd = $db->prepare(
                "UPDATE usuarios
                    SET remember_token = :t, remember_expires = :e, last_login = NOW()
                  WHERE id = :id"
            );
            $upd->execute([
                ':t'  => $newToken,
                ':e'  => $expiresAt,
                ':id' => $u['id'],
            ]);

            setcookie('remember', $newToken, [
                'expires'  => time() + REMEMBER_LIFETIME,
                'path'     => '/',
                'secure'   => $cookieSecure,
                'httponly' => COOKIE_HTTPONLY,
                'samesite' => COOKIE_SAMESITE,
            ]);

            $_SESSION['user'] = [
                'id'    => $u['id'],
                'nome'  => $u['nome'],
                'email' => $u['email'],
                'role'  => $u['role'],
            ];
        } else {
            // invÃ¡lido/expirado -> limpa
            setcookie('remember', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => $cookieSecure,
                'httponly' => COOKIE_HTTPONLY,
                'samesite' => COOKIE_SAMESITE,
            ]);
        }
    } catch (Throwable $e) {
        // Silencioso para nÃ£o quebrar a navegaÃ§Ã£o
    }
}

