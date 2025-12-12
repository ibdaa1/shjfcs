<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $dbFile = realpath(__DIR__ . '/../db.php');
    if (!$dbFile || !file_exists($dbFile)) throw new RuntimeException("Database configuration file '../db.php' not found.");
    require_once $dbFile;
    if (!isset($conn) || !($conn instanceof mysqli)) throw new RuntimeException("Database connection object $conn is not available. Check db.php.");
    if (session_status() === PHP_SESSION_NONE) session_start();

    // أثناء الاختبار نسمح بعدم وجود جلسة؛ في الإنتاج أعد تفعيل شرط المصادقة
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        $user = null;
    } else {
        $user = $_SESSION['user'];
    }

    $empid = $user['EmpID'] ?? null;
    $isAdmin = (int)($user['IsAdmin'] ?? 0);
    $canAdd = (int)($user['CanAdd'] ?? 0);
    $canEdit = (int)($user['CanEdit'] ?? 0);
    $canDelete = (int)($user['CanDelete'] ?? 0);
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    function json_input(){ $raw = file_get_contents('php://input'); $data = json_decode($raw, true); if ($data === null) return $_POST; return $data; }
    function validate_unique_belongs_to_license($conn, $unique_id, $license_no){ if (!$unique_id || !$license_no) return false; $stmt = $conn->prepare("SELECT ID FROM establishments WHERE unique_id = ? AND license_no = ? LIMIT 1"); $stmt->bind_param('ss', $unique_id, $license_no); $stmt->execute(); $res = $stmt->get_result(); $ok = (bool)$res->fetch_assoc(); $stmt->close(); return $ok; }

    switch($action){
        case 'list':
            $sql = "SELECT ie.ID, ie.unique_id, ie.registration_date, ie.system_registration_no, ie.container_count, ie.container_numbers, ie.actual_inspection_date, p.port_name AS port_name, u.EmpName AS inspector_name, a.action_name AS action_name, ie.notes, ie.created_at FROM import_export_inspections ie LEFT JOIN ports p ON p.id = ie.entry_port_id LEFT JOIN Users u ON u.EmpID = ie.inspector_emp_id LEFT JOIN action_types a ON a.id = ie.action_taken_id ORDER BY ie.ID DESC LIMIT 1000";
            $result = $conn->query($sql);
            $rows = [];
            while($r = $result->fetch_assoc()) $rows[] = $r;
            echo json_encode(['data' => $rows]);
            exit;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            if (!$id){ http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
            $stmt = $conn->prepare("SELECT * FROM import_export_inspections WHERE ID = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $inspection = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();

            if ($inspection){
                $pstmt = $conn->prepare("SELECT * FROM tblproducts WHERE inspection_id = ?");
                $pstmt->bind_param('i', $id);
                $pstmt->execute();
                $products = $pstmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $pstmt->close();
                $inspection['products'] = $products;
            }
            echo json_encode(['data' => $inspection]);
            exit;

        case 'create':
            // during testing we allow creation; in production check permissions:
            // if (!$isAdmin && !$canAdd) { http_response_code(403); echo json_encode(['error'=>'Permission denied']); exit; }
            $input = json_input();
            $license_no = trim($input['license_no'] ?? '');
            $unique_id = trim($input['unique_id'] ?? '');
            if ($license_no === ''){ http_response_code(400); echo json_encode(['error'=>'رقم الرخصة مطلوب']); exit; }
            if ($unique_id === ''){ http_response_code(400); echo json_encode(['error'=>'المعرف الفريد مطلوب']); exit; }
            if (!validate_unique_belongs_to_license($conn, $unique_id, $license_no)){ http_response_code(400); echo json_encode(['error'=>'المعرف الفريد لا ينتمي إلى رقم الرخصة المحدد']); exit; }

            $reg_date = isset($input['registration_date']) && $input['registration_date'] !== '' ? str_replace('T', ' ', $input['registration_date']) : null;
            $actual_date = isset($input['actual_inspection_date']) && $input['actual_inspection_date'] !== '' ? str_replace('T', ' ', $input['actual_inspection_date']) : null;

            $stmt = $conn->prepare("INSERT INTO import_export_inspections (unique_id, registration_date, entry_port_id, system_registration_no, container_count, container_numbers, actual_inspection_date, inspector_emp_id, action_taken_id, notes, created_by_emp_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $entry_port_id = $input['entry_port_id'] ?: null; $system_registration_no = $input['system_registration_no'] ?: null; $container_count = $input['container_count'] !== '' ? $input['container_count'] : null; $container_numbers = $input['container_numbers'] ?: null; $inspector_emp_id = $input['inspector_emp_id'] ?: null; $action_taken_id = $input['action_taken_id'] ?: null; $notes = $input['notes'] ?: null; $created_by_emp_id = $empid ?: null;

            $stmt->bind_param('ssississsis', $unique_id, $reg_date, $entry_port_id, $system_registration_no, $container_count, $container_numbers, $actual_date, $inspector_emp_id, $action_taken_id, $notes, $created_by_emp_id);
            $stmt->execute();
            $insertId = $stmt->insert_id;
            $stmt->close();

            $products = $input['products'] ?? [];
            if (!empty($products)){
                $pstmt = $conn->prepare("INSERT INTO tblproducts (inspection_id, product_name, brand_name, country_id, packaging_type, quantity, weight, production_date, expiry_date, category_id, serial, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                foreach($products as $p){
                    $inspection_id = $insertId;
                    $product_name = $p['product_name'] ?? '';
                    $brand_name = $p['brand_name'] ?? '';
                    $country_id = $p['country_id'] ?: null;
                    $packaging_type = $p['packaging_type'] ?? null;
                    $quantity = $p['quantity'] ?: 0;
                    $weight = $p['weight'] ?: 0;
                    $production_date = !empty($p['production_date']) ? $p['production_date'] : null;
                    $expiry_date = !empty($p['expiry_date']) ? $p['expiry_date'] : null;
                    $category_id = $p['category_id'] ?: null;
                    $serial = $p['serial'] ?? '';
                    $notes_p = $p['notes'] ?? '';
                    $pstmt->bind_param('ississddssss', $inspection_id, $product_name, $brand_name, $country_id, $packaging_type, $quantity, $weight, $production_date, $expiry_date, $category_id, $serial, $notes_p);
                    $pstmt->execute();
                }
                $pstmt->close();
            }
            echo json_encode(['success' => true, 'id' => $insertId]);
            exit;

        case 'update':
            // during testing we allow update; in production check permissions
            $input = json_input();
            $id = intval($input['ID'] ?? 0);
            if (!$id){ http_response_code(400); echo json_encode(['error'=>'ID required']); exit; }
            $license_no = trim($input['license_no'] ?? '');
            $unique_id = trim($input['unique_id'] ?? '');
            if ($license_no === ''){ http_response_code(400); echo json_encode(['error'=>'رقم الرخصة مطلوب']); exit; }
            if ($unique_id === ''){ http_response_code(400); echo json_encode(['error'=>'المعرف الفريد مطلوب']); exit; }
            if (!validate_unique_belongs_to_license($conn, $unique_id, $license_no)){ http_response_code(400); echo json_encode(['error'=>'المعرف الفريد لا ينتمي إلى رقم الرخصة المحدد']); exit; }

            $reg_date = isset($input['registration_date']) && $input['registration_date'] !== '' ? str_replace('T', ' ', $input['registration_date']) : null;
            $actual_date = isset($input['actual_inspection_date']) && $input['actual_inspection_date'] !== '' ? str_replace('T', ' ', $input['actual_inspection_date']) : null;

            $stmt = $conn->prepare("UPDATE import_export_inspections SET unique_id=?, registration_date=?, entry_port_id=?, system_registration_no=?, container_count=?, container_numbers=?, actual_inspection_date=?, inspector_emp_id=?, action_taken_id=?, notes=?, updated_by_emp_id=?, updated_at=NOW() WHERE ID=?");
            $entry_port_id = $input['entry_port_id'] ?: null; $system_registration_no = $input['system_registration_no'] ?: null; $container_count = $input['container_count'] !== '' ? $input['container_count'] : null; $container_numbers = $input['container_numbers'] ?: null; $inspector_emp_id = $input['inspector_emp_id'] ?: null; $action_taken_id = $input['action_taken_id'] ?: null; $notes = $input['notes'] ?: null; $updated_by_emp_id = $empid ?: null;

            $stmt->bind_param('ssississsisi', $unique_id, $reg_date, $entry_port_id, $system_registration_no, $container_count, $container_numbers, $actual_date, $inspector_emp_id, $action_taken_id, $notes, $updated_by_emp_id, $id);
            $stmt->execute();
            $stmt->close();

            $conn->query("DELETE FROM tblproducts WHERE inspection_id = " . intval($id));
            $products = $input['products'] ?? [];
            if (!empty($products)){
                $pstmt = $conn->prepare("INSERT INTO tblproducts (inspection_id, product_name, brand_name, country_id, packaging_type, quantity, weight, production_date, expiry_date, category_id, serial, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                foreach($products as $p){
                    $inspection_id = $id;
                    $product_name = $p['product_name'] ?? '';
                    $brand_name = $p['brand_name'] ?? '';
                    $country_id = $p['country_id'] ?: null;
                    $packaging_type = $p['packaging_type'] ?? null;
                    $quantity = $p['quantity'] ?: 0;
                    $weight = $p['weight'] ?: 0;
                    $production_date = !empty($p['production_date']) ? $p['production_date'] : null;
                    $expiry_date = !empty($p['expiry_date']) ? $p['expiry_date'] : null;
                    $category_id = $p['category_id'] ?: null;
                    $serial = $p['serial'] ?? '';
                    $notes_p = $p['notes'] ?? '';
                    $pstmt->bind_param('ississddssss', $inspection_id, $product_name, $brand_name, $country_id, $packaging_type, $quantity, $weight, $production_date, $expiry_date, $category_id, $serial, $notes_p);
                    $pstmt->execute();
                }
                $pstmt->close();
            }
            echo json_encode(['success' => true, 'id' => $id]);
            exit;

        case 'delete':
            // during testing we allow delete; in production check permissions
            $id = intval($_GET['id'] ?? 0);
            if (!$id){ http_response_code(400); echo json_encode(['error'=>'id required']); exit; }
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM tblproducts WHERE inspection_id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();

                $stmt2 = $conn->prepare("DELETE FROM import_export_inspections WHERE ID = ?");
                $stmt2->bind_param('i', $id);
                $stmt2->execute();
                $stmt2->close();

                $conn->commit();
                echo json_encode(['success' => true]);
            } catch (Throwable $ex) {
                $conn->rollback();
                throw $ex;
            }
            exit;

        default:
            http_response_code(400);
            echo json_encode(['error'=>'unknown or missing action']);
            exit;
    }
} catch (Throwable $ex) {
    error_log("Import.php error: " . $ex->getMessage() . " in " . $ex->getFile() . ":" . $ex->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}
?>
