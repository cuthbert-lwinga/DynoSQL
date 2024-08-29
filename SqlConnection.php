<?php

class SqlConnection {
    private static ?SqlConnection $instance = null;
    private ?mysqli $connection = null;
    private string $host;
    private string $username;
    private string $password;
    private string $database;

    private function __construct(string $host, string $username, string $password, string $database) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
    }

    public static function getInstance(): SqlConnection {
        if (self::$instance === null) {
            self::$instance = new self(DatabaseConfig::host, DatabaseConfig::username, DatabaseConfig::password, DatabaseConfig::name);
        }
        return self::$instance;
    }

    public function connect(): bool {
        if ($this->connection === null) {
            $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            $this->connection->set_charset("utf8mb4");
        }
        return true;
    }

    public function disconnect(): void {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    public function getConnection(): ?mysqli {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    public function query(string $sql): mysqli_result|bool {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection->query($sql);
    }

    public function prepare(string $sql): mysqli_stmt|false {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection->prepare($sql);
    }

    public function getLastInsertId(): int {
        return $this->connection->insert_id;
    }

    public function beginTransaction(): bool {
        return $this->connection->begin_transaction();
    }

    public function commit(): bool {
        return $this->connection->commit();
    }

    public function rollback(): bool {
        return $this->connection->rollback();
    }

    public function isConnectionActive(): bool {
        if ($this->connection === null) {
            return false;
        }

        // Check if the connection is still active using mysqli_ping()
        return $this->connection->ping();
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

?>