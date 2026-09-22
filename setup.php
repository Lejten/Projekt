<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$schema = file_get_contents(__DIR__ . '/database/database.sql');

try {
    $server = new PDO(
        "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4",
        $config['user'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $server->exec("CREATE DATABASE IF NOT EXISTS `{$config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",
        $config['user'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $schema);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '' && !str_starts_with($statement, 'CREATE DATABASE') && !str_starts_with($statement, 'USE ')) {
            $db->exec($statement);
        }
    }

    echo '<h1>Instalacja zakończona</h1><p>Baza rezerwuj została utworzona i uzupełniona danymi startowymi.</p><p><a href="index.php">Przejdź do serwisu</a></p>';
} catch (PDOException $exception) {
    http_response_code(500);
    echo '<h1>Nie udało się połączyć z bazą</h1><p>Sprawdź uruchomienie MySQL/MariaDB i dane w config.php lub zmiennych DB_*.</p>';
}
