<?php
require_once 'config.php';

function getDBConnection() {
    try {
        $host = getenv('PGHOST');
        $port = getenv('PGPORT');
        $dbname = getenv('PGDATABASE');
        $user = getenv('PGUSER');
        $pass = getenv('PGPASSWORD');

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Show the real error for debugging
        die("Connection failed: " . $e->getMessage());
    }
}
?>
