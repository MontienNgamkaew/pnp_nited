<?php
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query("SELECT id, teacher_id, company_name, photo_1, photo_2, photo_3, photo_4, signature FROM supervisions ORDER BY id DESC LIMIT 10");
    $rows = $stmt->fetchAll();
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
