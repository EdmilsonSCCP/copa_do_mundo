<?php
declare(strict_types=1);

require __DIR__ . '/../includes/auth_boot.php';

ensure_all_schema($db);

echo "Banco preparado com sucesso.\n";
