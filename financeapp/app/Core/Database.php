<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use Exception;

/**
 * Database Connection and Query Builder
 */
class Database
{
    private ?PDO $connection = null;
    private Config $config;
    private string $table = '';
    private array $wheres = [];
    private array $bindings = [];
    private array $selects = ['*'];
    private array $joins = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get PDO connection instance
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $this->config->get('database.host'),
                $this->config->get('database.port'),
                $this->config->get('database.database'),
                $this->config->get('database.charset')
            );

            $this->connection = new PDO(
                $dsn,
                $this->config->get('database.username'),
                $this->config->get('database.password'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );

            // Set collation
            $this->connection->exec(
                "SET NAMES " . $this->config->get('database.charset') .
                " COLLATE " . $this->config->get('database.collation')
            );

        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Set table for query
     */
    public function table(string $table): self
    {
        $this->table = $table;
        $this->reset();
        return $this;
    }

    /**
     * Select columns
     */
    public function select(array|string $columns): self
    {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Add WHERE clause
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add OR WHERE clause
     */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'OR',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add JOIN clause
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = "INNER JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    /**
     * Add LEFT JOIN clause
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = "LEFT JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }

    /**
     * Set LIMIT
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set OFFSET
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Get all results
     */
    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        $stmt = $this->execute($sql, $this->bindings);
        return $stmt->fetchAll();
    }

    /**
     * Get first result
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Find by ID
     */
    public function find(int|string $id, string $column = 'id'): ?array
    {
        return $this->where($column, $id)->first();
    }

    /**
     * Get count
     */
    public function count(): int
    {
        $originalSelects = $this->selects;
        $this->selects = ['COUNT(*) as count'];

        $result = $this->first();

        $this->selects = $originalSelects;

        return (int)($result['count'] ?? 0);
    }

    /**
     * Insert record
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->execute($sql, $values);

        return (int)$this->getConnection()->lastInsertId();
    }

    /**
     * Update records
     */
    public function update(array $data): int
    {
        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $bindings = array_merge($bindings, $this->bindings);

        $sql = sprintf(
            "UPDATE %s SET %s%s",
            $this->table,
            implode(', ', $sets),
            $this->buildWhereClause()
        );

        $stmt = $this->execute($sql, $bindings);

        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(): int
    {
        $sql = sprintf(
            "DELETE FROM %s%s",
            $this->table,
            $this->buildWhereClause()
        );

        $stmt = $this->execute($sql, $this->bindings);

        return $stmt->rowCount();
    }

    /**
     * Execute raw query
     */
    public function query(string $sql, array $bindings = []): PDOStatement
    {
        return $this->execute($sql, $bindings);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }

    /**
     * Build SELECT query
     */
    private function buildSelectQuery(): string
    {
        $sql = sprintf(
            "SELECT %s FROM %s",
            implode(', ', $this->selects),
            $this->table
        );

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->buildWhereClause();

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * Build WHERE clause
     */
    private function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $clauses = [];

        foreach ($this->wheres as $where) {
            $clause = "{$where['column']} {$where['operator']} ?";

            if (empty($clauses)) {
                $clauses[] = $clause;
            } else {
                $clauses[] = "{$where['type']} {$clause}";
            }
        }

        return ' WHERE ' . implode(' ', $clauses);
    }

    /**
     * Execute prepared statement
     */
    private function execute(string $sql, array $bindings = []): PDOStatement
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($bindings);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }

    /**
     * Reset query builder state
     */
    private function reset(): void
    {
        $this->wheres = [];
        $this->bindings = [];
        $this->selects = ['*'];
        $this->joins = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;
    }
}
