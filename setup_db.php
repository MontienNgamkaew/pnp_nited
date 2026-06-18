<?php
// setup_db.php - Re-initialize database without prefix column in teachers table

header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // 1. Connect to MySQL without selecting database
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS pnp_nited_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<h3>1. สร้าง/ตรวจสอบฐานข้อมูล pnp_nited_db เรียบร้อย...</h3>";

    // 3. Connect to Database
    $pdo->exec("USE pnp_nited_db");

    // Drop old tables to apply structural changes cleanly
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DROP TABLE IF EXISTS supervision_students;");
    $pdo->exec("DROP TABLE IF EXISTS supervision_scores;");
    $pdo->exec("DROP TABLE IF EXISTS supervisions;");
    $pdo->exec("DROP TABLE IF EXISTS criteria;");
    $pdo->exec("DROP TABLE IF EXISTS teachers;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "<h3>2. ล้างตารางเดิมเพื่ออัปเกรดโครงสร้างตาราง (ตัดคำนำหน้าออก)...</h3>";

    // 4. Create Table: teachers (Removed prefix column)
    $sql_teachers = "
    CREATE TABLE IF NOT EXISTS teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        national_id VARCHAR(20) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        lastname VARCHAR(100) NOT NULL,
        department VARCHAR(100) NOT NULL,
        role ENUM('teacher', 'admin') NOT NULL DEFAULT 'teacher',
        INDEX (national_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_teachers);
    echo "<h3>3. สร้างตาราง teachers (ไม่มีฟิลด์คำนำหน้าชื่อ) เรียบร้อย...</h3>";

    // 5. Create Table: criteria
    $sql_criteria = "
    CREATE TABLE IF NOT EXISTS criteria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        order_num INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_criteria);
    echo "<h3>4. สร้างตาราง criteria เรียบร้อย...</h3>";

    // 6. Create Table: supervisions
    $sql_supervisions = "
    CREATE TABLE IF NOT EXISTS supervisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        semester VARCHAR(10) NOT NULL,
        academic_year VARCHAR(4) NOT NULL,
        supervision_date DATE NOT NULL,
        company_name VARCHAR(200) NOT NULL,
        company_address TEXT NOT NULL,
        score_avg DECIMAL(3,2) NOT NULL,
        eval_result VARCHAR(50) NOT NULL,
        problems TEXT,
        corrections TEXT,
        suggestions TEXT,
        photo_1 VARCHAR(255),
        photo_2 VARCHAR(255),
        photo_3 VARCHAR(255),
        photo_4 VARCHAR(255),
        signature MEDIUMTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_supervisions);
    echo "<h3>5. สร้างตาราง supervisions เรียบร้อย...</h3>";

    // 7. Create Table: supervision_scores
    $sql_supervision_scores = "
    CREATE TABLE IF NOT EXISTS supervision_scores (
        supervision_id INT NOT NULL,
        criteria_id INT NOT NULL,
        score INT NOT NULL,
        PRIMARY KEY (supervision_id, criteria_id),
        FOREIGN KEY (supervision_id) REFERENCES supervisions(id) ON DELETE CASCADE,
        FOREIGN KEY (criteria_id) REFERENCES criteria(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_supervision_scores);
    echo "<h3>6. สร้างตาราง supervision_scores เรียบร้อย...</h3>";

    // 8. Create Table: supervision_students
    $sql_students = "
    CREATE TABLE IF NOT EXISTS supervision_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supervision_id INT NOT NULL,
        student_name VARCHAR(150) NOT NULL,
        level ENUM('ปวช.', 'ปวส.') NOT NULL,
        year INT NOT NULL,
        major VARCHAR(100) NOT NULL,
        FOREIGN KEY (supervision_id) REFERENCES supervisions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_students);
    echo "<h3>7. สร้างตาราง supervision_students เรียบร้อย...</h3>";

    // 9. Seed Default Users (Removed prefix)
    $teacher_pass = password_hash('password', PASSWORD_DEFAULT);
    $admin_pass = password_hash('pnp123', PASSWORD_DEFAULT);

    $stmt_ins = $pdo->prepare("INSERT INTO teachers (national_id, password, name, lastname, department, role) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Seed Admin
    $stmt_ins->execute(['admin', $admin_pass, 'ผู้ดูแล', 'ระบบ', 'ฝ่ายวิชาการ', 'admin']);
    
    // Seed Teachers
    $stmt_ins->execute(['1234567890123', $teacher_pass, 'สมชาย', 'รักดี', 'แผนกวิชาช่างยนต์', 'teacher']);
    $stmt_ins->execute(['1111111111111', $teacher_pass, 'วิภาดา', 'จิตดี', 'แผนกวิชาการบัญชี', 'teacher']);
    echo "<h3>8. นำเข้าบัญชีผู้ใช้งานทดสอบ (ไม่มีคำนำหน้าชื่อ) เรียบร้อย...</h3>";

    // 10. Seed Initial Criteria (10 items)
    $default_criteria = [
        "ตรงต่อเวลา และมาปฏิบัติงานอย่างสม่ำเสมอ",
        "การแต่งกายสุภาพเรียบร้อยและถูกระเบียบ",
        "ปฏิบัติงานตามคำสั่งและวางตนอยู่ในระเบียบวินัย",
        "ซื่อสัตย์ สุจริต อดทน และขยันขันแข็งในการทำงาน",
        "มีความตั้งใจและสนใจใฝ่รู้ในงานอยู่เสมอ",
        "ปฏิบัติงานถูกต้องตามลักษณะงาน",
        "สามารถปฏิบัติงานเสร็จเรียบร้อยภายในเวลาที่กำหนด",
        "รู้จักใช้เครื่องมือ อุปกรณ์ต่างๆ อย่างถูกต้อง และมีความระมัดระวัง",
        "มีน้ำใจ ให้ความร่วมมือ และทำงานร่วมกับผู้อื่นได้ดี",
        "มีความสุภาพ อ่อนน้อม รู้จักกาลเทศะ"
    ];

    $stmt_crit = $pdo->prepare("INSERT INTO criteria (title, order_num) VALUES (?, ?)");
    foreach ($default_criteria as $idx => $title) {
        $stmt_crit->execute([$title, $idx + 1]);
    }
    echo "<h3>9. เพิ่มหัวข้อการประเมินตั้งต้น 10 หัวข้อ เรียบร้อย...</h3>";

    // 11. Create uploads directory
    $upload_dir = __DIR__ . '/uploads';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    echo "<h2 style='color: green;'>ปรับปรุงโครงสร้างตารางข้อมูลและติดตั้งใหม่เสร็จสมบูรณ์!</h2>";
    echo "<p><a href='index.html'>คลิกที่นี่เพื่อย้อนกลับไปยังหน้าแรกของโปรเจค</a></p>";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>เกิดข้อผิดพลาดในการติดตั้งระบบฐานข้อมูล:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
