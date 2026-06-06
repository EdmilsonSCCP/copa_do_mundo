<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth_boot.php';

// Se houver cookie remember, zera no banco também
if (!empty($_COOKIE['remember'])) {
    try {
        $stmt = $db->prepare("UPDATE usuarios SET remember_token = NULL, remember_expires = NULL WHERE remember_token = :t");
        $stmt->execute([':t' => $_COOKIE['remember']]);
    } catch (Throwable $e) {
        // silencioso
    }
    setcookie('remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => COOKIE_SECURE,
        'httponly' => COOKIE_HTTPONLY,
        'samesite' => COOKIE_SAMESITE,
    ]);
}

// Destroi a sessão
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Volta para login
header('Location: /auth/login.php');
exit;
