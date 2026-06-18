<?php
// api_supervision.php - Handle CRUD for dynamic supervisions, dynamic students, and evaluation scores mapping

session_start();
require_once 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!isset($_SESSION['teacher_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please login first.']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        handleList($pdo);
        break;
    case 'get':
        handleGet($pdo);
        break;
    case 'add':
        handleAdd($pdo);
        break;
    case 'edit':
        handleEdit($pdo);
        break;
    case 'delete':
        handleDelete($pdo);
        break;
    case 'list_criteria':
        handleListCriteria($pdo);
        break;
    case 'get_session':
        handleGetSession();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

// 1. Get Logged-in Session Profile Details
function handleGetSession() {
    echo json_encode([
        'status' => 'success',
        'session' => [
            'teacher_id' => $_SESSION['teacher_id'],
            'fullname' => $_SESSION['fullname'],
            'department' => $_SESSION['department'],
            'role' => $_SESSION['role'],
            'national_id' => $_SESSION['national_id']
        ]
    ]);
}

// 2. Fetch Active Evaluation Criteria List
function handleListCriteria($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM criteria ORDER BY order_num ASC, id ASC");
        $criteria = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $criteria]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error querying criteria: ' . $e->getMessage()]);
    }
}

// 3. Fetch Supervision List
function handleList($pdo) {
    try {
        $teacher_id = $_SESSION['teacher_id'];
        $role = $_SESSION['role'];

        if ($role === 'admin') {
            $stmt = $pdo->prepare("
                SELECT s.*, t.name as teacher_name, t.lastname as teacher_lastname, t.department 
                FROM supervisions s
                JOIN teachers t ON s.teacher_id = t.id
                ORDER BY s.supervision_date DESC, s.id DESC
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT s.*, t.name as teacher_name, t.lastname as teacher_lastname, t.department 
                FROM supervisions s
                JOIN teachers t ON s.teacher_id = t.id
                WHERE s.teacher_id = ?
                ORDER BY s.supervision_date DESC, s.id DESC
            ");
            $stmt->execute([$teacher_id]);
        }
        $reports = $stmt->fetchAll();

        // Populate students & scores list for each report
        foreach ($reports as &$r) {
            // Students
            $stmt_stud = $pdo->prepare("SELECT * FROM supervision_students WHERE supervision_id = ?");
            $stmt_stud->execute([$r['id']]);
            $r['students'] = $stmt_stud->fetchAll();

            // Scores
            $stmt_scores = $pdo->prepare("
                SELECT ss.criteria_id, ss.score, c.title 
                FROM supervision_scores ss
                JOIN criteria c ON ss.criteria_id = c.id
                WHERE ss.supervision_id = ?
                ORDER BY c.order_num ASC, c.id ASC
            ");
            $stmt_scores->execute([$r['id']]);
            $r['scores'] = $stmt_scores->fetchAll();
        }

        echo json_encode(['status' => 'success', 'data' => $reports]);

    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error querying supervisions: ' . $e->getMessage()]);
    }
}

// 4. Fetch Specific Report Details
function handleGet($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }

    try {
        // Fetch supervision
        $stmt = $pdo->prepare("
            SELECT s.*, t.name as teacher_name, t.lastname as teacher_lastname, t.department, t.national_id
            FROM supervisions s
            JOIN teachers t ON s.teacher_id = t.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $report = $stmt->fetch();

        if (!$report) {
            echo json_encode(['status' => 'error', 'message' => 'Report not found']);
            exit;
        }

        // Fetch students
        $stmt_stud = $pdo->prepare("SELECT * FROM supervision_students WHERE supervision_id = ?");
        $stmt_stud->execute([$id]);
        $report['students'] = $stmt_stud->fetchAll();

        // Fetch scores
        $stmt_scores = $pdo->prepare("
            SELECT ss.criteria_id, ss.score, c.title 
            FROM supervision_scores ss
            JOIN criteria c ON ss.criteria_id = c.id
            WHERE ss.supervision_id = ?
            ORDER BY c.order_num ASC, c.id ASC
        ");
        $stmt_scores->execute([$id]);
        $report['scores'] = $stmt_scores->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $report]);

    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// 5. Save New Supervision Report
function handleAdd($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'POST method required']);
        exit;
    }

    // Capture text values
    $teacher_id = $_SESSION['teacher_id'];
    $semester = isset($_POST['semester']) ? trim($_POST['semester']) : '';
    $academic_year = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : '';
    $supervision_date = isset($_POST['supervision_date']) ? trim($_POST['supervision_date']) : '';
    $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
    $company_address = isset($_POST['company_address']) ? trim($_POST['company_address']) : '';

    // Capture dynamic scores mapping from POST
    // Expected post field format: scores[criteria_id] = score (1-4)
    $score_inputs = isset($_POST['scores']) && is_array($_POST['scores']) ? $_POST['scores'] : [];

    // Query active criteria to validate against
    $stmt_crit = $pdo->query("SELECT id FROM criteria");
    $criteria_ids = $stmt_crit->fetchAll(PDO::FETCH_COLUMN);

    if (count($criteria_ids) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบหัวข้อประเมินในฐานข้อมูล กรุณาติดต่อแอดมิน']);
        exit;
    }

    // Validate that all criteria are answered
    $score_sum = 0;
    foreach ($criteria_ids as $c_id) {
        if (!isset($score_inputs[$c_id])) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณาประเมินผลคะแนนให้ครบถ้วนทุกข้อการประเมิน']);
            exit;
        }
        $score_val = intval($score_inputs[$c_id]);
        if ($score_val < 1 || $score_val > 4) {
            echo json_encode(['status' => 'error', 'message' => 'คะแนนการประเมินต้องอยู่ระหว่าง 1 ถึง 4 คะแนน']);
            exit;
        }
        $score_sum += $score_val;
    }

    $score_avg = round($score_sum / count($criteria_ids), 2);

    // Determine rating outcome text based on 4-scale logic
    $eval_result = "ควรปรับปรุง";
    if ($score_avg >= 3.50) {
        $eval_result = "ดีมาก";
    } elseif ($score_avg >= 2.50) {
        $eval_result = "ดี";
    } elseif ($score_avg >= 1.50) {
        $eval_result = "พอใช้";
    }

    $problems = isset($_POST['problems']) ? trim($_POST['problems']) : '';
    $corrections = isset($_POST['corrections']) ? trim($_POST['corrections']) : '';
    $suggestions = isset($_POST['suggestions']) ? trim($_POST['suggestions']) : '';
    $signature_data = isset($_POST['signature']) ? $_POST['signature'] : '';

    // Handle students list
    $student_names = isset($_POST['student_names']) ? $_POST['student_names'] : [];
    $student_levels = isset($_POST['student_levels']) ? $_POST['student_levels'] : [];
    $student_years = isset($_POST['student_years']) ? $_POST['student_years'] : [];
    $student_majors = isset($_POST['student_majors']) ? $_POST['student_majors'] : [];

    if (empty($student_names) || count($student_names) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลผู้เรียนอย่างน้อย 1 คน']);
        exit;
    }

    // Handle Image uploads (4 slots)
    $uploaded_photos = [
        'photo_1' => null,
        'photo_2' => null,
        'photo_3' => null,
        'photo_4' => null
    ];

    $upload_dir = __DIR__ . '/uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $max_file_size = 3 * 1024 * 1024;
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

    for ($i = 1; $i <= 4; $i++) {
        $field_name = "photo_$i";
        if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
                $err_code = $_FILES[$field_name]['error'];
                $err_msg = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพที่ $i (รหัสข้อผิดพลาด: $err_code)";
                if ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
                    $err_msg = "ไฟล์รูปภาพที่ $i ขนาดใหญ่เกินความจุที่เซิร์ฟเวอร์รองรับ";
                }
                echo json_encode(['status' => 'error', 'message' => $err_msg]);
                exit;
            }
            $file_tmp = $_FILES[$field_name]['tmp_name'];
            $file_name = $_FILES[$field_name]['name'];
            $file_size = $_FILES[$field_name]['size'];
            
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_extensions)) {
                echo json_encode(['status' => 'error', 'message' => "ไฟล์รูปภาพที่ $i นามสกุลไม่ถูกต้อง (รองรับ JPG, JPEG, PNG, WEBP)"]);
                exit;
            }

            if ($file_size > $max_file_size) {
                echo json_encode(['status' => 'error', 'message' => "ไฟล์รูปภาพที่ $i ขนาดใหญ่เกินไป (จำกัดไม่เกิน 3MB)"]);
                exit;
            }

            $new_filename = uniqid('photo_' . $i . '_', true) . '.' . $file_ext;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $dest_path)) {
                $uploaded_photos[$field_name] = 'uploads/' . $new_filename;
            } else {
                echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาดในการบันทึกรูปภาพที่ $i"]);
                exit;
            }
        }
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert into supervisions table
        $sql_ins = "
            INSERT INTO supervisions (
                teacher_id, semester, academic_year, supervision_date, company_name, company_address,
                score_avg, eval_result, problems, corrections, suggestions,
                photo_1, photo_2, photo_3, photo_4, signature
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ";

        $stmt = $pdo->prepare($sql_ins);
        $stmt->execute([
            $teacher_id, $semester, $academic_year, $supervision_date, $company_name, $company_address,
            $score_avg, $eval_result, $problems, $corrections, $suggestions,
            $uploaded_photos['photo_1'], $uploaded_photos['photo_2'], $uploaded_photos['photo_3'], $uploaded_photos['photo_4'], $signature_data
        ]);

        $supervision_id = $pdo->lastInsertId();

        // 2. Insert into supervision_scores table
        $sql_score = "INSERT INTO supervision_scores (supervision_id, criteria_id, score) VALUES (?, ?, ?)";
        $stmt_score = $pdo->prepare($sql_score);
        foreach ($score_inputs as $criteria_id => $score_val) {
            $stmt_score->execute([$supervision_id, intval($criteria_id), intval($score_val)]);
        }

        // 3. Insert into supervision_students table
        $sql_stud = "
            INSERT INTO supervision_students (supervision_id, student_name, level, year, major)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt_stud = $pdo->prepare($sql_stud);

        for ($k = 0; $k < count($student_names); $k++) {
            $name_val = trim($student_names[$k]);
            $level_val = trim($student_levels[$k]);
            $year_val = intval($student_years[$k]);
            $major_val = trim($student_majors[$k]);

            if (!empty($name_val)) {
                $stmt_stud->execute([$supervision_id, $name_val, $level_val, $year_val, $major_val]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'บันทึกรายงานการนิเทศเสร็จสมบูรณ์']);

    } catch (\Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()]);
    }
}

// 6. Edit Supervision Report
function handleEdit($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'POST method required']);
        exit;
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }

    try {
        // Fetch existing report to verify ownership/existence
        $stmt = $pdo->prepare("SELECT * FROM supervisions WHERE id = ?");
        $stmt->execute([$id]);
        $report = $stmt->fetch();

        if (!$report) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลรายงานที่ต้องการแก้ไข']);
            exit;
        }

        $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
        $teacher_id = $_SESSION['teacher_id'];
        
        // Admin or owner only
        if ($role !== 'admin' && intval($report['teacher_id']) !== intval($teacher_id)) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์แก้ไขรายงานการนิเทศนี้']);
            exit;
        }

        // Capture text values
        $semester = isset($_POST['semester']) ? trim($_POST['semester']) : '';
        $academic_year = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : '';
        $supervision_date = isset($_POST['supervision_date']) ? trim($_POST['supervision_date']) : '';
        $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
        $company_address = isset($_POST['company_address']) ? trim($_POST['company_address']) : '';

        // Capture dynamic scores mapping from POST
        $score_inputs = isset($_POST['scores']) && is_array($_POST['scores']) ? $_POST['scores'] : [];

        // Query active criteria to validate against
        $stmt_crit = $pdo->query("SELECT id FROM criteria");
        $criteria_ids = $stmt_crit->fetchAll(PDO::FETCH_COLUMN);

        if (count($criteria_ids) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบหัวข้อประเมินในฐานข้อมูล กรุณาติดต่อแอดมิน']);
            exit;
        }

        // Validate that all criteria are answered
        $score_sum = 0;
        foreach ($criteria_ids as $c_id) {
            if (!isset($score_inputs[$c_id])) {
                echo json_encode(['status' => 'error', 'message' => 'กรุณาประเมินผลคะแนนให้ครบถ้วนทุกข้อการประเมิน']);
                exit;
            }
            $score_val = intval($score_inputs[$c_id]);
            if ($score_val < 1 || $score_val > 4) {
                echo json_encode(['status' => 'error', 'message' => 'คะแนนการประเมินต้องอยู่ระหว่าง 1 ถึง 4 คะแนน']);
                exit;
            }
            $score_sum += $score_val;
        }

        $score_avg = round($score_sum / count($criteria_ids), 2);

        // Determine rating outcome text based on 4-scale logic
        $eval_result = "ควรปรับปรุง";
        if ($score_avg >= 3.50) {
            $eval_result = "ดีมาก";
        } elseif ($score_avg >= 2.50) {
            $eval_result = "ดี";
        } elseif ($score_avg >= 1.50) {
            $eval_result = "พอใช้";
        }

        $problems = isset($_POST['problems']) ? trim($_POST['problems']) : '';
        $corrections = isset($_POST['corrections']) ? trim($_POST['corrections']) : '';
        $suggestions = isset($_POST['suggestions']) ? trim($_POST['suggestions']) : '';

        // Signature handling
        $signature_data = isset($_POST['signature']) ? $_POST['signature'] : '';
        if (empty($signature_data) || $signature_data === 'keep') {
            $signature_data = $report['signature'];
        }

        // Handle students list
        $student_names = isset($_POST['student_names']) ? $_POST['student_names'] : [];
        $student_levels = isset($_POST['student_levels']) ? $_POST['student_levels'] : [];
        $student_years = isset($_POST['student_years']) ? $_POST['student_years'] : [];
        $student_majors = isset($_POST['student_majors']) ? $_POST['student_majors'] : [];

        if (empty($student_names) || count($student_names) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลผู้เรียนอย่างน้อย 1 คน']);
            exit;
        }

        // Handle Image uploads (4 slots)
        $uploaded_photos = [
            'photo_1' => $report['photo_1'],
            'photo_2' => $report['photo_2'],
            'photo_3' => $report['photo_3'],
            'photo_4' => $report['photo_4']
        ];

        $upload_dir = __DIR__ . '/uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $max_file_size = 3 * 1024 * 1024;
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

        for ($i = 1; $i <= 4; $i++) {
            $field_name = "photo_$i";
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
                    $err_code = $_FILES[$field_name]['error'];
                    $err_msg = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพที่ $i (รหัสข้อผิดพลาด: $err_code)";
                    if ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
                        $err_msg = "ไฟล์รูปภาพที่ $i ขนาดใหญ่เกินความจุที่เซิร์ฟเวอร์รองรับ";
                    }
                    echo json_encode(['status' => 'error', 'message' => $err_msg]);
                    exit;
                }
                $file_tmp = $_FILES[$field_name]['tmp_name'];
                $file_name = $_FILES[$field_name]['name'];
                $file_size = $_FILES[$field_name]['size'];
                
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (!in_array($file_ext, $allowed_extensions)) {
                    echo json_encode(['status' => 'error', 'message' => "ไฟล์รูปภาพที่ $i นามสกุลไม่ถูกต้อง (รองรับ JPG, JPEG, PNG, WEBP)"]);
                    exit;
                }

                if ($file_size > $max_file_size) {
                    echo json_encode(['status' => 'error', 'message' => "ไฟล์รูปภาพที่ $i ขนาดใหญ่เกินไป (จำกัดไม่เกิน 3MB)"]);
                    exit;
                }

                $new_filename = uniqid('photo_' . $i . '_', true) . '.' . $file_ext;
                $dest_path = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $dest_path)) {
                    // Delete the old photo if it exists
                    $old_photo = $report[$field_name];
                    if (!empty($old_photo) && file_exists(__DIR__ . '/' . $old_photo)) {
                        @unlink(__DIR__ . '/' . $old_photo);
                    }
                    $uploaded_photos[$field_name] = 'uploads/' . $new_filename;
                } else {
                    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาดในการบันทึกรูปภาพที่ $i"]);
                    exit;
                }
            }
        }

        $pdo->beginTransaction();

        // 1. Update supervisions table
        $sql_upd = "
            UPDATE supervisions SET
                semester = ?, academic_year = ?, supervision_date = ?, company_name = ?, company_address = ?,
                score_avg = ?, eval_result = ?, problems = ?, corrections = ?, suggestions = ?,
                photo_1 = ?, photo_2 = ?, photo_3 = ?, photo_4 = ?, signature = ?
            WHERE id = ?
        ";

        $stmt_upd = $pdo->prepare($sql_upd);
        $stmt_upd->execute([
            $semester, $academic_year, $supervision_date, $company_name, $company_address,
            $score_avg, $eval_result, $problems, $corrections, $suggestions,
            $uploaded_photos['photo_1'], $uploaded_photos['photo_2'], $uploaded_photos['photo_3'], $uploaded_photos['photo_4'],
            $signature_data, $id
        ]);

        // 2. Delete old records from supervision_scores and supervision_students
        $stmt_del_scores = $pdo->prepare("DELETE FROM supervision_scores WHERE supervision_id = ?");
        $stmt_del_scores->execute([$id]);

        $stmt_del_stud = $pdo->prepare("DELETE FROM supervision_students WHERE supervision_id = ?");
        $stmt_del_stud->execute([$id]);

        // 3. Insert into supervision_scores table
        $sql_score = "INSERT INTO supervision_scores (supervision_id, criteria_id, score) VALUES (?, ?, ?)";
        $stmt_score = $pdo->prepare($sql_score);
        foreach ($score_inputs as $criteria_id => $score_val) {
            $stmt_score->execute([$id, intval($criteria_id), intval($score_val)]);
        }

        // 4. Insert into supervision_students table
        $sql_stud = "
            INSERT INTO supervision_students (supervision_id, student_name, level, year, major)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt_stud = $pdo->prepare($sql_stud);

        for ($k = 0; $k < count($student_names); $k++) {
            $name_val = trim($student_names[$k]);
            $level_val = trim($student_levels[$k]);
            $year_val = intval($student_years[$k]);
            $major_val = trim($student_majors[$k]);

            if (!empty($name_val)) {
                $stmt_stud->execute([$id, $name_val, $level_val, $year_val, $major_val]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'แก้ไขรายงานการนิเทศเสร็จสมบูรณ์']);

    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . $e->getMessage()]);
    }
}

// 7. Delete Supervision Report
function handleDelete($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }

    try {
        // Fetch report to check existence and owner
        $stmt = $pdo->prepare("SELECT * FROM supervisions WHERE id = ?");
        $stmt->execute([$id]);
        $report = $stmt->fetch();

        if (!$report) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลรายงาน']);
            exit;
        }

        $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
        $teacher_id = $_SESSION['teacher_id'];
        if ($role !== 'admin' && intval($report['teacher_id']) !== intval($teacher_id)) {
            echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ลบรายงานการนิเทศนี้']);
            exit;
        }

        // Delete photo files
        for ($i = 1; $i <= 4; $i++) {
            $photo_path = $report["photo_$i"];
            if (!empty($photo_path) && file_exists(__DIR__ . '/' . $photo_path)) {
                @unlink(__DIR__ . '/' . $photo_path);
            }
        }

        // Begin transaction to delete child tables first then parent
        $pdo->beginTransaction();

        $stmt_del_scores = $pdo->prepare("DELETE FROM supervision_scores WHERE supervision_id = ?");
        $stmt_del_scores->execute([$id]);

        $stmt_del_stud = $pdo->prepare("DELETE FROM supervision_students WHERE supervision_id = ?");
        $stmt_del_stud->execute([$id]);

        $stmt_del_report = $pdo->prepare("DELETE FROM supervisions WHERE id = ?");
        $stmt_del_report->execute([$id]);

        $pdo->commit();

        echo json_encode(['status' => 'success', 'message' => 'ลบรายงานการนิเทศสำเร็จแล้ว']);

    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()]);
    }
}
?>
