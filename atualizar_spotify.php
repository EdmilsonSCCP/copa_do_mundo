<?php
declare(strict_types=1);

require __DIR__ . '/includes/spotify_service.php';

if (PHP_SAPI !== 'cli') {
    require __DIR__ . '/includes/auth_boot.php';
    require_login();
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $ranking = spotify_refresh_ranking(__DIR__);
    echo 'Ranking atualizado com sucesso.' . PHP_EOL;
    echo 'Artistas processados: ' . count($ranking) . PHP_EOL;
    echo 'Arquivo final: top30_display.json' . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erro ao atualizar ranking: ' . $e->getMessage() . PHP_EOL;
}
