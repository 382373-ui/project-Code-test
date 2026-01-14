<?php
require_once 'config.php';

function getDBConnection() {
    try {
        // Use the constants defined in config.php
        $host = DB_HOST;
        $dbname = DB_NAME;
        $user = DB_USER;
        $pass = DB_PASS;
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>
