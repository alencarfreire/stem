<?php

declare(strict_types=1);

namespace StemExample;

final class Database
{
    public static function connect(?string $dsn = null): \PDO
    {
        $dsn ??= 'sqlite:' . __DIR__ . '/var/app.sqlite';

        $dir = __DIR__ . '/var';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
        }

        $pdo = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $schema = file_get_contents(__DIR__ . '/schema.sql');
        if ($schema === false) {
            throw new \RuntimeException('Cannot read schema.sql');
        }

        $pdo->exec($schema);
        self::seed($pdo);

        return $pdo;
    }

    private static function seed(\PDO $pdo): void
    {
        $count = $pdo->query('SELECT COUNT(*) FROM users');
        if ($count === false || (int) $count->fetchColumn() > 0) {
            return;
        }

        $pdo->exec("INSERT INTO users (name) VALUES ('Ada'), ('Linus')");
    }
}
