<?php

function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $db   = getenv('DB_NAME') ?: 'zopacrm';
        $user = getenv('DB_USER') ?: 'jules';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Ensures native prepared statements
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            // DO NOT output the exception detail in production.
            // Log it securely instead.
            error_log("Database Connection Error: " . $e->getMessage());
            // Generic error message for user
            die('Database Connection Failed.');
        }
    }

    return $pdo;
}