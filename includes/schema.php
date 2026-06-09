<?php
declare(strict_types=1);

function schema_try(PDO $db, string $sql): void
{
    try {
        $db->exec($sql);
    } catch (Throwable $e) {
        error_log('Schema update skipped: ' . $e->getMessage());
    }
}

function ensure_auth_schema(PDO $db): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            role VARCHAR(40) NOT NULL DEFAULT 'user',
            remember_token VARCHAR(128) NULL,
            remember_expires DATETIME NULL,
            reset_token VARCHAR(128) NULL,
            reset_expires DATETIME NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX reset_token_idx (reset_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS reset_token VARCHAR(128) NULL",
        "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL",
        "ALTER TABLE usuarios ADD INDEX IF NOT EXISTS reset_token_idx (reset_token)",
    ];

    foreach ($statements as $sql) {
        schema_try($db, $sql);
    }
}

function ensure_fantasy_schema(PDO $db): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS fantasy_results (
            match_id INT PRIMARY KEY,
            result_a INT NOT NULL,
            result_b INT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS fantasy_predictions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            match_id INT NOT NULL,
            pred_a INT NOT NULL,
            pred_b INT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_match_prediction (user_id, match_id),
            INDEX prediction_match_idx (match_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $sql) {
        schema_try($db, $sql);
    }
}

function ensure_simulator_schema(PDO $db): void
{
    schema_try(
        $db,
        "CREATE TABLE IF NOT EXISTS simuladores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            simulator_key VARCHAR(80) NOT NULL,
            payload LONGTEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_simulator (user_id, simulator_key),
            INDEX simulator_user_idx (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensure_all_schema(PDO $db): void
{
    ensure_auth_schema($db);
    ensure_fantasy_schema($db);
    ensure_simulator_schema($db);
}
