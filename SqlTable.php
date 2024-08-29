<?php

class SqlTable {
    private string $name;
    private array $columns;
    private array $indexes;
    private ?string $comment;
    private static ?SqlConnection $connection = null;
    private array $joinedTables = [];

    public function __construct(string $name) {
        $this->name = $name;
        $this->columns = [];
        $this->indexes = [];
        $this->comment = null;
        $this->loadTableStructure();
    }

private function loadTableStructure(): void {
    $connection = self::getConnection();
    $connection->connect();

    try {
        // Fetch table comment
        $commentQuery = (new QueryBuilder('information_schema.tables'))
            ->select(['table_comment'])
            ->where('table_schema', '=', 'DATABASE()')
            ->where('table_name', '=', $this->name);


        $result = $commentQuery->execute($connection);
        if (!empty($result)) {
            $this->comment = $result[0]['table_comment'] ?: null;
        }

        // Fetch columns
        $columnQuery = (new QueryBuilder('information_schema.columns'))
            ->select(['COLUMN_NAME', 'DATA_TYPE', 'COLUMN_KEY', 'IS_NULLABLE', 'COLUMN_DEFAULT', 'EXTRA', 'COLUMN_COMMENT'])
            ->where('TABLE_SCHEMA', '=', 'DATABASE()')
            ->where('TABLE_NAME', '=', $this->name);


        $columnInfo = $columnQuery->execute($connection);
        foreach ($columnInfo as $row) {
            $column = new SqlColumn(
                $row['COLUMN_NAME'],
                $row['DATA_TYPE'],
                $row['COLUMN_KEY'] === 'PRI',
                $row['IS_NULLABLE'] === 'YES',
                $row['COLUMN_DEFAULT'],
                $row['COLUMN_KEY'] === 'UNI',
                strpos($row['EXTRA'], 'auto_increment') !== false,
                null,  // length will be parsed from DATA_TYPE if needed
                $row['COLUMN_COMMENT']
            );
            $this->addColumn($column);
        }

        // Fetch indexes
        $indexQuery = (new QueryBuilder('information_schema.statistics'))
            ->select(['INDEX_NAME', 'COLUMN_NAME', 'NON_UNIQUE'])
            ->where('TABLE_SCHEMA', '=', 'DATABASE()')
            ->where('TABLE_NAME', '=', $this->name);


        $indexInfo = $indexQuery->execute($connection);
        $indexes = [];
        foreach ($indexInfo as $row) {
            $indexName = $row['INDEX_NAME'];
            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'columns' => [],
                    'unique' => $row['NON_UNIQUE'] == 0
                ];
            }
            $indexes[$indexName]['columns'][] = $row['COLUMN_NAME'];
        }
        foreach ($indexes as $name => $index) {
            $this->addIndex($name, $index['columns'], $index['unique']);
        }
    } catch (Exception $e) {
        echo "Error in loadTableStructure: " . $e->getMessage() . "\n";
    } finally {
        $connection->disconnect();
    }
}

    public static function setConnection(SqlConnection $connection): void {
        self::$connection = $connection;
    }

    protected static function getConnection(): SqlConnection {
        if (self::$connection === null) {
            self::$connection = SqlConnection::getInstance();
        }
        return self::$connection;
    }

    // Getters
    public function getName(): string {
        return $this->name;
    }

    public function getColumns(): array {
        return $this->columns;
    }

    public function getIndexes(): array {
        return $this->indexes;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    // Setters
    public function setName(string $name): void {
        $this->name = $name;
    }

    public function setComment(?string $comment): void {
        $this->comment = $comment;
    }

    // Column management methods
    public function addColumn(SqlColumn $column): void {
        $this->columns[$column->getName()] = $column;
    }

    public function removeColumn(string $columnName): bool {
        if (isset($this->columns[$columnName])) {
            unset($this->columns[$columnName]);
            return true;
        }
        return false;
    }

    public function getColumn(string $columnName): ?SqlColumn {
        return $this->columns[$columnName] ?? null;
    }

    public function hasColumn(string $columnName): bool {
        return isset($this->columns[$columnName]);
    }

    // Index management methods
    public function addIndex(string $name, array $columnNames, bool $isUnique = false): void {
        $this->indexes[$name] = [
            'columns' => $columnNames,
            'unique' => $isUnique
        ];
    }

    public function removeIndex(string $indexName): bool {
        if (isset($this->indexes[$indexName])) {
            unset($this->indexes[$indexName]);
            return true;
        }
        return false;
    }

    public function getIndex(string $indexName): ?array {
        return $this->indexes[$indexName] ?? null;
    }

    public function hasIndex(string $indexName): bool {
        return isset($this->indexes[$indexName]);
    }

    // CRUD operations
    public function insert(array $data) {
        $connection = self::getConnection();
        $connection->connect();

        try {
            $columns = array_keys($data);
            $values = array_fill(0, count($data), '?');
            $sql = "INSERT INTO `{$this->name}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ")";

            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Failed to prepare statement: " . $connection->getConnection()->error);
            }

            $types = '';
            $bindParams = [];
            foreach ($data as $value) {
                $types .= $this->getBindParamType($value);
                $bindParams[] = $value;
            }

            $stmt->bind_param($types, ...$bindParams);
            $result = $stmt->execute();
            $stmt->close();

            return $result ? $connection->getConnection()->insert_id : false;
        } catch (Exception $e) {
            echo "Error in insert: " . $e->getMessage() . "\n";
            return false;
        } finally {
            $connection->disconnect();
        }
    }

    public function select(array $columns = [], array $conditions = [], int $limit = 20, int $offset = 0, array $orderBy = []) {
        $connection = self::getConnection();
        $connection->connect();

        try {
            $query = (new QueryBuilder($this->name))
                ->select($columns ?: ['*']);

            foreach ($conditions as $column => $value) {
                if (strtoupper(substr($column, 0, 3)) === 'OR ') {
                    $column = substr($column, 3);
                    $query = $query->orWhere($column, '=', $value);
                } else {
                    $query = $query->where($column, '=', $value);
                }
            }

            foreach ($orderBy as $column => $direction) {
                $query = $query->orderBy($column, $direction);
            }

            $query = $query->limit($limit)->offset($offset);

            return $query->execute($connection);
        } catch (Exception $e) {
            echo "Error in select: " . $e->getMessage() . "\n";
            return [];
        } finally {
            $connection->disconnect();
        }
    }

    public function update(array $data, array $conditions) {
        $connection = self::getConnection();
        $connection->connect();

        try {
            $query = new QueryBuilder($this->name);
            $setClause = [];
            $bindParams = [];
            $types = '';

            foreach ($data as $column => $value) {
                $setClause[] = "`$column` = ?";
                $bindParams[] = $value;
                $types .= $this->getBindParamType($value);
            }

            foreach ($conditions as $column => $value) {
                $query = $query->where($column, '=', $value);
                $bindParams[] = $value;
                $types .= $this->getBindParamType($value);
            }

            $sql = "UPDATE `{$this->name}` SET " . implode(', ', $setClause) . " " . substr($query->toSql(), strpos($query->toSql(), 'WHERE'));

            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Failed to prepare statement: " . $connection->getConnection()->error);
            }

            $stmt->bind_param($types, ...$bindParams);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            echo "Error in update: " . $e->getMessage() . "\n";
            return false;
        } finally {
            $connection->disconnect();
        }
    }

    public function delete(array $conditions) {
        $connection = self::getConnection();
        $connection->connect();

        try {
            $query = new QueryBuilder($this->name);
            $bindParams = [];
            $types = '';

            foreach ($conditions as $column => $value) {
                $query = $query->where($column, '=', $value);
                $bindParams[] = $value;
                $types .= $this->getBindParamType($value);
            }

            $sql = "DELETE FROM `{$this->name}` " . substr($query->toSql(), strpos($query->toSql(), 'WHERE'));

            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Failed to prepare statement: " . $connection->getConnection()->error);
            }

            $stmt->bind_param($types, ...$bindParams);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            echo "Error in delete: " . $e->getMessage() . "\n";
            return false;
        } finally {
            $connection->disconnect();
        }
    }

    // Helper method for binding parameters
    private function getBindParamType($value): string {
        if (is_int($value)) return 'i';
        if (is_float($value)) return 'd';
        if (is_string($value)) return 's';
        return 'b'; // BLOB and other types
    }

    // Additional utility methods
    public function getPrimaryKey(): ?SqlColumn {
        foreach ($this->columns as $column) {
            if ($column->isPrimary()) {
                return $column;
            }
        }
        return null;
    }

    public function getUniqueColumns(): array {
        return array_filter($this->columns, function($column) {
            return $column->isUnique();
        });
    }

    public function getAutoIncrementColumn(): ?SqlColumn {
        foreach ($this->columns as $column) {
            if ($column->isAutoIncrement()) {
                return $column;
            }
        }
        return null;
    }

    public function toArray(): array {
        return [
            'name' => $this->name,
            'columns' => array_map(function($column) {
                return [
                    'name' => $column->getName(),
                    'type' => $column->getType(),
                    'isPrimary' => $column->isPrimary(),
                    'isNullable' => $column->isNullable(),
                    'default' => $column->getDefault(),
                    'isUnique' => $column->isUnique(),
                    'isAutoIncrement' => $column->isAutoIncrement(),
                    'length' => $column->getLength(),
                    'comment' => $column->getComment(),
                ];
            }, $this->columns),
            'indexes' => $this->indexes,
            'comment' => $this->comment,
        ];
    }
}
?>