<?php
/**
 * Creates ONE shared PDO connection to the MySQL database.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function connect(array $dbConfig): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['name']
        );

        self::$connection = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // SQL errors throw exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ]);
    }

    public static function get(): PDO
    {
        if (self::$connection === null) {
            throw new RuntimeException('Database is not connected.');
        }
        return self::$connection;
    }
}
