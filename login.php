<?php
// login.php - Handle login validation and session creation

session_start();
require_once 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Get input parameters
$national_id = isset($_POST['national_id']) ? trim($_POST['national_id']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($national_id) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกเลขบัตรประชาชนและรหัสผ่าน']);
    exit;
}

// Clean national_id (remove dashes/spaces if any)
$national_id = str_replace(['-', ' '], '', $national_id);

if ($national_id !== 'admin') {
    if (strlen($national_id) !== 13 || !is_numeric($national_id)) {
        echo json_encode(['status' => 'error', 'message' => 'รูปแบบเลขประจำตัวประชาชนไม่ถูกต้อง (ต้องเป็นตัวเลข 13 หลัก)']);
        exit;
    }
}

try {
    // Check user in database
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE national_id = ?");
    $stmt->execute([$national_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Create session
        $_SESSION['teacher_id'] = $user['id'];
        $_SESSION['national_id'] = $user['national_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['lastname'] = $user['lastname'];
        $_SESSION['fullname'] = $user['name'] . ' ' . $user['lastname'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['role'] = $user['role'];

        echo json_encode([
            'status' => 'success',
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'role' => $user['role'],
            'user' => [
                'fullname' => $_SESSION['fullname'],
                'department' => $user['department']
            ]
        ]);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'เลขประจำตัวประชาชนหรือรหัสผ่านไม่ถูกต้อง']);
        exit;
    }

} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage()]);
    exit;
}
?>
