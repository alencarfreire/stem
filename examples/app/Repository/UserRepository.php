<?php

declare(strict_types=1);

namespace StemExample\Repository;

final class UserRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT id, name FROM users ORDER BY id');
        if ($statement === false) {
            return [];
        }

        /** @var list<array{id: int|string, name: string}> $rows */
        $rows = $statement->fetchAll();

        return array_map(self::map(...), $rows);
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        /** @var array{id: int|string, name: string}|false $row */
        $row = $statement->fetch();

        return $row === false ? null : self::map($row);
    }

    /**
     * @return array{id: int, name: string}
     */
    public function create(string $name): array
    {
        $statement = $this->pdo->prepare('INSERT INTO users (name) VALUES (:name)');
        $statement->execute(['name' => $name]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'name' => $name,
        ];
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @param array{id: int|string, name: string} $row
     * @return array{id: int, name: string}
     */
    private static function map(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
        ];
    }
}
