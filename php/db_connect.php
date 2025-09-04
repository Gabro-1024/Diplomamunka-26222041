<?php
function db_connect() {
    $dsn = 'mysql:host=localhost;dbname=ticketing_system;charset=utf8mb4';
    $user = 'root';
    $pass = '';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (Exception $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}
