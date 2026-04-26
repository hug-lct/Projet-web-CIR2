<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';

    $baseHost = trim((string) ($config['db_host'] ?? 'localhost'));
    $basePort = (int) ($config['db_port'] ?? 0);
    $dbName = (string) ($config['db_name'] ?? '');
    $dbUser = (string) ($config['db_user'] ?? '');
    $dbPass = (string) ($config['db_pass'] ?? '');

    $hostPort = null;
    if (strpos($baseHost, ':') !== false) {
        $hostParts = explode(':', $baseHost);
        $maybePort = array_pop($hostParts);
        if ($maybePort !== null && ctype_digit($maybePort)) {
            $hostPort = (int) $maybePort;
            $baseHost = implode(':', $hostParts);
        }
    }

    if ($baseHost === '') {
        $baseHost = 'localhost';
    }

    $hosts = array_values(array_unique(array_filter([
        $baseHost,
        $baseHost === 'localhost' ? '127.0.0.1' : null,
        $baseHost === '127.0.0.1' ? 'localhost' : null,
    ])));

    $envPort = getenv('DB_PORT');
    $envPortsRaw = getenv('DB_PORTS');
    $envPorts = [];
    if ($envPortsRaw !== false && trim((string) $envPortsRaw) !== '') {
        foreach (explode(',', (string) $envPortsRaw) as $value) {
            $value = trim($value);
            if ($value !== '' && ctype_digit($value)) {
                $envPorts[] = (int) $value;
            }
        }
    }

    $ports = array_values(array_unique(array_filter([
        $hostPort,
        $basePort,
        $envPort !== false && ctype_digit((string) $envPort) ? (int) $envPort : null,
        ...$envPorts,
        3306,
        8889,
    ], static fn (?int $port): bool => $port !== null && $port > 0 && $port <= 65535)));

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $errors = [];

    foreach ($hosts as $host) {
        $dsnWithoutPort = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);
        try {
            $pdo = new PDO($dsnWithoutPort, $dbUser, $dbPass, $options);
            return $pdo;
        } catch (PDOException $exception) {
            $errors[] = sprintf('%s (sans port, %s)', $host, $exception->getMessage());
        }

        foreach ($ports as $port) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

            try {
                $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
                return $pdo;
            } catch (PDOException $exception) {
                $errors[] = sprintf('%s:%d (%s)', $host, $port, $exception->getMessage());
            }
        }
    }

    throw new RuntimeException(
        "Impossible de se connecter a MySQL. Verifie que MySQL est demarre, puis configure src/config.php ou les variables DB_HOST/DB_PORT/DB_PORTS. Tentatives: "
        . implode(' | ', $errors)
    );
}
