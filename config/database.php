<?php
/**
 * PDO database connection.
 *
 * Returns a single shared connection and configures PDO to throw
 * exceptions, fetch associative arrays, and use real prepared statements.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function database(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/**
 * Try to connect, returning null instead of throwing. Used by pages that
 * want to show a friendly "database not configured" screen.
 */
function database_or_null(): ?PDO
{
    try {
        return database();
    } catch (Throwable $error) {
        error_log('ITS database connection failed: ' . $error->getMessage());
        return null;
    }
}
