<?php
// admin_api.php - Handle teacher management, CSV import, and admin dashboard metrics

session_start();
require_once 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// Block access if not logged in or not admin
if (!isset($_SESSION['teacher_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin access only.']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list_teachers':
        handleListTeachers($pdo);
        break;
    case 'add_teacher':
        handleAddTeacher($pdo);
        break;
    case 'edit_teacher':
        handleEditTeacher($pdo);
        break;
    case 'delete_teacher':
        handleDeleteTeacher($pdo);
        break;
    case 'import_csv':
        handleImportCSV($pdo);
        break;
    case 'dashboard_stats':
        handleDashboardStats($pdo);
        break;
    case 'add_criteria':
        handleAddCriteria($pdo);
        break;
    case 'edit_criteria':
        handleEditCriteria($pdo);
        break;
    case 'delete_criteria':
        handleDeleteCriteria($pdo);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

// 1. List Teachers
function handleListTeachers($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, national_id, name, lastname, department, role FROM teachers ORDER BY role DESC, id ASC");
        $teachers = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $teachers]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error querying teachers: ' . $e->getMessage()]);
    }
}

// 2. Add Teacher Individually
function handleAddTeacher($pdo) {
    $national_id = isset($_POST['national_id']) ? trim($_POST['national_id']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'teacher';

    if (empty($national_id) || empty($password) || empty($name) || empty($lastname) || empty($department)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลครูให้ครบถ้วนทุกช่อง']);
        exit;
    }

    $national_id = str_replace(['-', ' '], '', $national_id);
    if (strlen($national_id) !== 13 || !is_numeric($national_id)) {
        echo json_encode(['status' => 'error', 'message' => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก']);
        exit;
    }

    try {
        // Check duplicate
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE national_id = ?");
        $stmt_check->execute([$national_id]);
        if ($stmt_check->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'เลขประจำตัวประชาชนนี้มีอยู่ในระบบแล้ว']);
            exit;
        }

        // Insert (No prefix)
        $hash_pass = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO teachers (national_id, password, name, lastname, department, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$national_id, $hash_pass, $name, $lastname, $department, $role]);

        echo json_encode(['status' => 'success', 'message' => 'เพิ่มข้อมูลครูนิเทศก์เรียบร้อยแล้ว']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
}

// 3. Edit Teacher Details
function handleEditTeacher($pdo) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $national_id = isset($_POST['national_id']) ? trim($_POST['national_id']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'teacher';

    if ($id <= 0 || empty($national_id) || empty($name) || empty($lastname) || empty($department)) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลแก้ไขไม่ครบถ้วน']);
        exit;
    }

    $national_id = str_replace(['-', ' '], '', $national_id);

    try {
        // Check duplicate on other users
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE national_id = ? AND id != ?");
        $stmt_check->execute([$national_id, $id]);
        if ($stmt_check->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'เลขบัตรประจำตัวประชาชนซ้ำกับผู้ใช้อื่น']);
            exit;
        }

        if (!empty($password)) {
            // Update with password (No prefix)
            $hash_pass = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE teachers SET national_id = ?, password = ?, name = ?, lastname = ?, department = ?, role = ? WHERE id = ?");
            $stmt->execute([$national_id, $hash_pass, $name, $lastname, $department, $role, $id]);
        } else {
            // Update without password change (No prefix)
            $stmt = $pdo->prepare("UPDATE teachers SET national_id = ?, name = ?, lastname = ?, department = ?, role = ? WHERE id = ?");
            $stmt->execute([$national_id, $name, $lastname, $department, $role, $id]);
        }

        echo json_encode(['status' => 'success', 'message' => 'ปรับปรุงข้อมูลครูสำเร็จ']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
}

// 4. Delete Teacher
function handleDeleteTeacher($pdo) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID ไม่ถูกต้อง']);
        exit;
    }

    if ($id === intval($_SESSION['teacher_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถลบบัญชีของตัวเองได้']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลครูนิเทศก์เรียบร้อยแล้ว']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()]);
    }
}

// 5. Import Teachers via CSV Upload
function handleImportCSV($pdo) {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณาเลือกไฟล์ CSV ที่ถูกต้อง']);
        exit;
    }

    $file_tmp = $_FILES['csv_file']['tmp_name'];
    
    // Check extension
    $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv' && $file_ext !== 'txt') {
        echo json_encode(['status' => 'error', 'message' => 'รองรับเฉพาะไฟล์นามสกุล .csv เท่านั้น']);
        exit;
    }

    $handle = fopen($file_tmp, "r");
    if ($handle === FALSE) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถอ่านไฟล์ได้']);
        exit;
    }

    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $row_count = 0;

    try {
        $pdo->beginTransaction();
        
        $stmt_check = $pdo->prepare("SELECT id FROM teachers WHERE national_id = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO teachers (national_id, password, name, lastname, department, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_update = $pdo->prepare("UPDATE teachers SET name = ?, lastname = ?, department = ?, role = ? WHERE id = ?");
        $stmt_update_pwd = $pdo->prepare("UPDATE teachers SET password = ?, name = ?, lastname = ?, department = ?, role = ? WHERE id = ?");

        // Loop rows
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_count++;
            
            if ($row_count === 1) {
                // Strip UTF-8 BOM if present
                if (substr($data[0], 0, 3) === "\xEF\xBB\xBF") {
                    $data[0] = substr($data[0], 3);
                }
            }
            
            // Skip CSV header (First row if matches characters)
            if ($row_count === 1 && (strpos($data[0], 'บัตร') !== false || strpos($data[0], 'ประจำตัว') !== false || strpos($data[0], 'id') !== false || strpos($data[0], 'ID') !== false)) {
                continue;
            }

            // We expect fields:
            // 0: National ID, 1: First Name, 2: Last Name, 3: Department, 4: Password
            if (count($data) < 4) {
                $skipped++;
                continue;
            }

            $nid = str_replace(['-', ' '], '', trim($data[0]));
            if ($nid !== 'admin' && (strlen($nid) !== 13 || !is_numeric($nid))) {
                $skipped++;
                continue;
            }

            $first = trim($data[1]);
            $last = trim($data[2]);
            $dept = trim($data[3]);
            $pwd = isset($data[4]) ? trim($data[4]) : '';
            $role_val = 'teacher';

            // Check if teacher exists
            $stmt_check->execute([$nid]);
            $existing_id = $stmt_check->fetchColumn();

            if ($existing_id) {
                // Update (No prefix)
                if (!empty($pwd)) {
                    $hash = password_hash($pwd, PASSWORD_DEFAULT);
                    $stmt_update_pwd->execute([$hash, $first, $last, $dept, $role_val, $existing_id]);
                } else {
                    $stmt_update->execute([$first, $last, $dept, $role_val, $existing_id]);
                }
                $updated++;
            } else {
                // Insert new (No prefix)
                $pwd_to_hash = !empty($pwd) ? $pwd : $nid; // Default password = national id
                $hash = password_hash($pwd_to_hash, PASSWORD_DEFAULT);
                $stmt_insert->execute([$nid, $hash, $first, $last, $dept, $role_val]);
                $imported++;
            }
        }

        fclose($handle);
        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => "นำเข้าข้อมูลสำเร็จ: เพิ่มใหม่ {$imported} บัญชี, ปรับปรุง {$updated} บัญชี, ข้าม {$skipped} แถวที่ไม่ถูกต้อง"
        ]);

    } catch (\Exception $e) {
        $pdo->rollBack();
        fclose($handle);
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดระหว่างนำเข้าไฟล์: ' . $e->getMessage()]);
    }
}

// 6. Fetch Admin Dashboard Statistics
function handleDashboardStats($pdo) {
    try {
        // Total Reports
        $total_reports = $pdo->query("SELECT COUNT(*) FROM supervisions")->fetchColumn();
        
        // Total Teachers
        $total_teachers = $pdo->query("SELECT COUNT(*) FROM teachers WHERE role = 'teacher'")->fetchColumn();

        // Total evaluated students
        $total_students = $pdo->query("SELECT COUNT(*) FROM supervision_students")->fetchColumn();

        // Reports by Department (Grouped by teacher department)
        $stmt_dept = $pdo->query("
            SELECT t.department, COUNT(s.id) as count
            FROM teachers t
            LEFT JOIN supervisions s ON s.teacher_id = t.id
            WHERE t.role = 'teacher'
            GROUP BY t.department
        ");
        $dept_stats = $stmt_dept->fetchAll();

        // Reports by Establishment (Grouped by company name)
        $stmt_company = $pdo->query("
            SELECT company_name, COUNT(*) as count 
            FROM supervisions 
            GROUP BY company_name 
            ORDER BY count DESC
            LIMIT 10
        ");
        $company_stats = $stmt_company->fetchAll();

        // Recent supervisions (Last 15 reports)
        $stmt_recent = $pdo->query("
            SELECT s.*, t.name as teacher_name, t.lastname as teacher_lastname, t.department
            FROM supervisions s
            JOIN teachers t ON s.teacher_id = t.id
            ORDER BY s.supervision_date DESC, s.id DESC
            LIMIT 15
        ");
        $recent_reports = $stmt_recent->fetchAll();

        // Get students for each of the recent reports
        foreach ($recent_reports as &$r) {
            $stmt_stud = $pdo->prepare("SELECT * FROM supervision_students WHERE supervision_id = ?");
            $stmt_stud->execute([$r['id']]);
            $r['students'] = $stmt_stud->fetchAll();
        }

        // Teacher Supervision Statuses
        $stmt_teacher_status = $pdo->query("
            SELECT t.id, t.national_id, t.name, t.lastname, t.department, COUNT(s.id) as supervision_count
            FROM teachers t
            LEFT JOIN supervisions s ON s.teacher_id = t.id
            WHERE t.role = 'teacher'
            GROUP BY t.id, t.national_id, t.name, t.lastname, t.department
            ORDER BY t.department ASC, t.name ASC
        ");
        $teacher_statuses = $stmt_teacher_status->fetchAll();

        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_reports' => $total_reports,
                'total_teachers' => $total_teachers,
                'total_students' => $total_students,
                'dept_stats' => $dept_stats,
                'company_stats' => $company_stats,
                'recent_reports' => $recent_reports,
                'teacher_statuses' => $teacher_statuses
            ]
        ]);

    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการดึงสถิติ: ' . $e->getMessage()]);
    }
}

// 7. Add Criteria
function handleAddCriteria($pdo) {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    if (empty($title)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกหัวข้อการประเมิน']);
        exit;
    }

    try {
        $stmt_max = $pdo->query("SELECT MAX(order_num) FROM criteria");
        $max_order = intval($stmt_max->fetchColumn());
        
        $stmt = $pdo->prepare("INSERT INTO criteria (title, order_num) VALUES (?, ?)");
        $stmt->execute([$title, $max_order + 1]);
        
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มหัวข้อการประเมินเรียบร้อยแล้ว']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
}

// 8. Edit Criteria
function handleEditCriteria($pdo) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';

    if ($id <= 0 || empty($title)) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลแก้ไขไม่ครบถ้วน']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE criteria SET title = ? WHERE id = ?");
        $stmt->execute([$title, $id]);
        echo json_encode(['status' => 'success', 'message' => 'แก้ไขหัวข้อการประเมินสำเร็จ']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
}

// 9. Delete Criteria
function handleDeleteCriteria($pdo) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID ไม่ถูกต้อง']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM criteria WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'ลบหัวข้อการประเมินสำเร็จ']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบหัวข้อการประเมิน: ' . $e->getMessage()]);
    }
}
?>
