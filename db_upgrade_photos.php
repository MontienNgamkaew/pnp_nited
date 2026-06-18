<?php
// db_upgrade_photos.php - Upgrade photo columns to MEDIUMTEXT for Base64 storage

header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

try {
    echo "<h2>เริ่มต้นอัปเกรดตารางข้อมูลรูปภาพ...</h2>";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'supervisions'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("ไม่พบตาราง supervisions ในระบบ กรุณาติดตั้งฐานข้อมูลก่อน");
    }
    
    // Modify photo columns to MEDIUMTEXT
    $columns = ['photo_1', 'photo_2', 'photo_3', 'photo_4'];
    foreach ($columns as $col) {
        $sql = "ALTER TABLE supervisions MODIFY `$col` MEDIUMTEXT";
        $pdo->exec($sql);
        echo "<li>ปรับปรุงคอลัมน์ `$col` เป็น MEDIUMTEXT สำเร็จ</li>";
    }
    
    echo "<h3 style='color: green;'>เสร็จสมบูรณ์! ปรับแต่งโครงสร้างฐานข้อมูลรองรับ Base64 เรียบร้อยแล้ว</h3>";
    echo "<p><a href='./'>กลับไปยังหน้าหลัก</a></p>";
} catch (Exception $e) {
    echo "<h3 style='color: red;'>เกิดข้อผิดพลาดในการอัปเกรด: " . $e->getMessage() . "</h3>";
}
?>
