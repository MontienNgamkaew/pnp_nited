<?php
// db_connect.php - Database connection parameters

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'u651170081_pnp_nited';
$user = getenv('DB_USER') ?: 'u651170081_pnp_nited';
$pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'a1d9GH10%';
$port = getenv('DB_PORT') ?: '3306';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
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
