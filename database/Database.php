<?php

declare(strict_types=1);

namespace mara\database;

final class Database
{
    private static ?Database $instance = null;

    private \mysqli $connection;

    private function __construct()
    {
        $this->connection = new \mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );

        if ($this->connection->connect_error) {
            throw new \RuntimeException(
                'Database connection failed: ' .
                $this->connection->connect_error
            );
        }

        $this->connection->set_charset('utf8mb4');
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): \mysqli
    {
        return $this->connection;
    }

    private function __clone(): void
    {
    }
}
?>