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

