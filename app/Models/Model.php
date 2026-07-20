<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Minimal active-record style base. Subclasses set $table and optionally
 * $fillable. All queries use PDO prepared statements via the Database helper.
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $softDeletes = false;

    public static function table(): string
    {
        return static::$table;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $sql = 'SELECT * FROM ' . static::$table . ' WHERE ' . static::$primaryKey . ' = ?';
        if (static::$softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';
        return Database::selectOne($sql, [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findBy(string $column, mixed $value): ?array
    {
        $sql = 'SELECT * FROM ' . static::$table . ' WHERE ' . $column . ' = ?';
        if (static::$softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';
        return Database::selectOne($sql, [$value]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(?string $orderBy = null): array
    {
        $sql = 'SELECT * FROM ' . static::$table;
        if (static::$softDeletes) {
            $sql .= ' WHERE deleted_at IS NULL';
        }
        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        return Database::select($sql);
    }

    public static function count(string $where = '', array $params = []): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . static::$table;
        $clauses = [];
        if (static::$softDeletes) {
            $clauses[] = 'deleted_at IS NULL';
        }
        if ($where !== '') {
            $clauses[] = $where;
        }
        if ($clauses !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        return (int) Database::scalar($sql, $params);
    }

    public static function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
        $sql = 'INSERT INTO ' . static::$table . ' (' . implode(', ', $columns) . ') '
            . 'VALUES (' . implode(', ', $placeholders) . ')';
        return Database::insert($sql, $data);
    }

    public static function update(int $id, array $data): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = $column . ' = :' . $column;
        }
        $data['__id'] = $id;
        $sql = 'UPDATE ' . static::$table . ' SET ' . implode(', ', $sets)
            . ' WHERE ' . static::$primaryKey . ' = :__id';
        return Database::affecting($sql, $data);
    }

    public static function delete(int $id): int
    {
        if (static::$softDeletes) {
            return static::update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
        }
        $sql = 'DELETE FROM ' . static::$table . ' WHERE ' . static::$primaryKey . ' = ?';
        return Database::affecting($sql, [$id]);
    }
}
