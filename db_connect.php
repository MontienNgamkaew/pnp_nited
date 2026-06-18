<?php
// db_connect.php - Database connection parameters

$host = 'localhost';
$db   = 'pnp_nited_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // If connection fails because DB doesn't exist, we allow it for setup
     if ($e->getCode() != 1049) {
          throw new \PDOException($e->getMessage(), (int)$e->getCode());
     }
}
?>
