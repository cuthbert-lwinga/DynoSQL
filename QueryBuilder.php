<?php

class QueryBuilder {
    private string $table;
    private array $select;
    private array $where;
    private array $orderBy;
    private ?int $limit;
    private int $offset;
    private array $joins;
    private array $bindings;
    private $lastWhere = null;
    
    public function __construct(string $table) {
        $this->table = $table;
        $this->select = ['*'];
        $this->where = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = 0;
        $this->joins = [];
        $this->bindings = [];
    }

    public function select(array $columns): self {
        $new = clone $this;
        $new->select = $columns;
        return $new;
    }

    public function where(string $column, $operator, $value = null): self {
        $new = clone $this;
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        if ($new->lastWhere !== null) {
            $new->where[] = 'AND';
        }

        $new->where[] = [$column, $operator, $value];
        if ($value !== 'DATABASE()') {
            $new->bindings[] = $value;
        }

        $new->lastWhere = [$column, $operator, $value];

        return $new;
    }

    public function orWhere(string $column, $operator, $value = null): self {
        $new = clone $this;
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $new->where[] = 'OR';
        $new->where[] = [$column, $operator, $value];
        if ($value !== 'DATABASE()') {
            $new->bindings[] = $value;
        }
        return $new;
    }

    public function whereNull(string $column): self {
        $new = clone $this;
        $new->where[] = [$column, 'IS', null];
        return $new;
    }

    public function whereNotNull(string $column): self {
        $new = clone $this;
        $new->where[] = [$column, 'IS NOT', null];
        return $new;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self {
        $new = clone $this;
        $new->orderBy[] = [$column, strtoupper($direction)];
        return $new;
    }

    public function limit(int $limit): self {
        $new = clone $this;
        $new->limit = $limit;
        return $new;
    }

    public function offset(int $offset): self {
        $new = clone $this;
        $new->offset = $offset;
        return $new;
    }

    public function join(string $table, string $first, string $operator, string $second): self {
        $new = clone $this;
        $new->joins[] = ['INNER JOIN', $table, $first, $operator, $second];
        return $new;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self {
        $new = clone $this;
        $new->joins[] = ['LEFT JOIN', $table, $first, $operator, $second];
        return $new;
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self {
        $new = clone $this;
        $new->joins[] = ['RIGHT JOIN', $table, $first, $operator, $second];
        return $new;
    }

    public function toSql(): string {
        $sql = "SELECT " . implode(', ', $this->select) . " FROM {$this->table}";

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= " {$join[0]} `{$join[1]}` ON {$join[2]} {$join[3]} {$join[4]}";
            }
        }

        if (!empty($this->where)) {
            $sql .= " WHERE ";
            $whereClauses = [];
            foreach ($this->where as $condition) {
                if (is_array($condition)) {
                    $column = $condition[0];
                    $operator = $condition[1];
                    $value = $condition[2];

                    if ($value === null) {
                        $whereClauses[] = "`$column` $operator NULL";
                    } elseif ($value === 'DATABASE()') {
                        $whereClauses[] = "`$column` $operator DATABASE()";
                    } else {
                        $whereClauses[] = "`$column` $operator ?";
                    }
                } else {
                    $whereClauses[] = $condition;
                }
            }
            $sql .= implode(' ', $whereClauses);
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', array_map(function($order) {
                return "`{$order[0]}` {$order[1]}";
            }, $this->orderBy));
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT ?";
        }

        if ($this->offset > 0) {
            $sql .= " OFFSET ?";
        }

        return $sql;
    }

    public function getRawSql(): string {
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        foreach ($bindings as $binding) {
            $value = is_string($binding) ? "'" . addslashes($binding) . "'" : $binding;
            $pos = strpos($sql, '?');
            if ($pos !== false) {
                $sql = substr_replace($sql, $value, $pos, 1);
            }
        }
        return $sql;
    }

    public function getBindings(): array {
        $bindings = [];
        foreach ($this->where as $condition) {
            if (is_array($condition) && $condition[2] !== null && $condition[2] !== 'DATABASE()') {
                $bindings[] = $condition[2];
            }
        }
        if ($this->limit !== null) {
            $bindings[] = $this->limit;
        }
        if ($this->offset > 0) {
            $bindings[] = $this->offset;
        }
        return $bindings;
    }

    public function execute(SqlConnection $connection) {
        $connection->connect();
        try {
            $stmt = $connection->prepare($this->toSql());
            if ($stmt === false) {
                throw new Exception("Failed to prepare statement: " . $connection->getConnection()->error);
            }

            $bindings = $this->getBindings();
            if (!empty($bindings)) {
                $types = '';
                foreach ($bindings as $value) {
                    $types .= $this->getBindParamType($value);
                }
                $stmt->bind_param($types, ...$bindings);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $data;
        } finally {
            $connection->disconnect();
        }
    }



    private function getBindParamType($value): string {
        if (is_int($value)) return 'i';
        if (is_float($value)) return 'd';
        if (is_string($value)) return 's';
        return 'b'; // BLOB and other types
    }

    // Debug method to print SQL and bindings
    public function debug(): void {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . print_r($this->getBindings(), true) . "\n";
    }
}
?>