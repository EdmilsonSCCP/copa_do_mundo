<?php
declare(strict_types=1);

require __DIR__ . '/../includes/auth_boot.php';

$user = current_user();

if ($user && !empty($_COOKIE['remember'])) {
    try {
        $stmt = $db->prepare('UPDATE usuarios SET remember_token = NULL, remember_expires = NULL WHERE id = :id');
        $stmt->execute([':id' => (int)$user['id']]);
    } catch (Throwable $e) {
        // Logout nao deve falhar por causa de limpeza auxiliar.
    }
}

setcookie('remember', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => is_https_request(),
    'httponly' => COOKIE_HTTPONLY,
    'samesite' => COOKIE_SAMESITE,
]);

$_SESSION = [];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: /auth/login.php');
exit;
