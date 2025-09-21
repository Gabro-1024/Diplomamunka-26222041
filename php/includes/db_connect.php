<?php
use Dotenv\Dotenv;

function db_connect() : PDO {
    $rootPath = dirname(__DIR__, 2);
    $autoloadPath = $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (class_exists(Dotenv::class)) {
        static $dotenvLoaded = false;
        if (!$dotenvLoaded) {
            $dotenv = Dotenv::createImmutable($rootPath);
            $dotenv->safeLoad();
            $dotenvLoaded = true;
        }
    }

    $dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $dbPort = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
    $dbName = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'ticketing_system';
    $dbUser = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
    $dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
    $dbCharset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $dbHost, $dbPort, $dbName, $dbCharset);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (Exception $e) {
        // Do not leak credentials; provide a generic error message
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}
