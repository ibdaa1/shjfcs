<?php
header('Content-Type: application/json; charset=utf-8');
// Simple Import API for CRUD of import_export_inspections + tblproducts
// NOTE: update DB credentials below to match your environment.
$dbHost = '127.0.0.1';
$dbUser = 'db_user';
$dbPass = 'db_pass';
$dbName = 'db_name';
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $mysqli->connect_error]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : ($_POST['action'] ?? null);
if (!$action) {
    echo json_encode(['error' => 'action required']);
    exit;
}

function json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($data === null) {
        // try POST form
        return $_POST;
    }
    return $data;
}

switch ($action) {
    case 'list':
        // list inspections with joined names
        $sql = "SELECT ie.ID, ie.unique_id, ie.registration_date, ie.system_registration_no, ie.container_count, ie.container_numbers,
                       ie.actual_inspection_date, p.port_name AS port_name, u.EmpName AS inspector_name, a.action_name AS action_name, ie.notes, ie.created_at
                FROM import_export_inspections ie
                LEFT JOIN ports p ON p.id = ie.entry_port_id
                LEFT JOIN Users u ON u.EmpID = ie.inspector_emp_id
                LEFT JOIN action_types a ON a.id = ie.action_taken_id
                ORDER BY ie.ID DESC
                LIMIT 500";
        $res = $mysqli->query($sql);
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode(['data' => $rows]);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'id required']); break; }
        $stmt = $mysqli->prepare("SELECT * FROM import_export_inspections WHERE ID = ?");
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $inspection = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if ($inspection) {
            $pstmt = $mysqli->prepare("SELECT * FROM tblproducts WHERE inspection_id = ?");
            $pstmt->bind_param('i',$id);
            $pstmt->execute();
            $products = $pstmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $pstmt->close();
            $inspection['products'] = $products;
        }
        echo json_encode(['data' => $inspection]);
        break;

    case 'create':
        $input = json_input();
        // basic fields - sanitize/validate as needed
        $fields = ['unique_id','registration_date','entry_port_id','system_registration_no','container_count','container_numbers','actual_inspection_date','inspector_emp_id','action_taken_id','notes','created_by_emp_id'];
        $placeholders = [];
        $types = '';
        $values = [];
        foreach ($fields as $f) {
            $placeholders[$f] = $input[$f] ?? null;
            $values[] = $placeholders[$f];
        }
        $sql = "INSERT INTO import_export_inspections (unique_id, registration_date, entry_port_id, system_registration_no, container_count, container_numbers, actual_inspection_date, inspector_emp_id, action_taken_id, notes, created_by_emp_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            'ssississsis',
            $values[0], $values[1], $values[2], $values[3], $values[4],
            $values[5], $values[6], $values[7], $values[8], $values[9], $values[10]
        );
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['error' => $stmt->error]);
            break;
        }
        $insertId = $stmt->insert_id;
        $stmt->close();

        // products array
        $products = $input['products'] ?? [];
        if (!empty($products)) {
            $pstmt = $mysqli->prepare("INSERT INTO tblproducts (inspection_id, product_name, brand_name, country_id, packaging_type, quantity, weight, production_date, expiry_date, category_id, serial, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            foreach ($products as $p) {
                $inspection_id = $insertId;
                $product_name = $p['product_name'] ?? '';
                $brand_name = $p['brand_name'] ?? '';
                $country_id = $p['country_id'] ?? null;
                $packaging_type = $p['packaging_type'] ?? '';
                $quantity = $p['quantity'] ?? 0;
                $weight = $p['weight'] ?? 0;
                $production_date = $p['production_date'] ?: null;
                $expiry_date = $p['expiry_date'] ?: null;
                $category_id = $p['category_id'] ?? null;
                $serial = $p['serial'] ?? '';
                $notes = $p['notes'] ?? '';
                $pstmt->bind_param('ississddssss', $inspection_id, $product_name, $brand_name, $country_id, $packaging_type, $quantity, $weight, $production_date, $expiry_date, $category_id, $serial, $notes);
                $pstmt->execute();
            }
            $pstmt->close();
        }

        echo json_encode(['success' => true, 'id' => $insertId]);
        break;

    case 'update':
        $input = json_input();
        $id = intval($input['ID'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID required']); break; }
        $sql = "UPDATE import_export_inspections SET unique_id=?, registration_date=?, entry_port_id=?, system_registration_no=?, container_count=?, container_numbers=?, actual_inspection_date=?, inspector_emp_id=?, action_taken_id=?, notes=?, updated_by_emp_id=?, updated_at=NOW() WHERE ID=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ssississsisi',
            $input['unique_id'] ?? null,
            $input['registration_date'] ?? null,
            $input['entry_port_id'] ?? null,
            $input['system_registration_no'] ?? null,
            $input['container_count'] ?? 0,
            $input['container_numbers'] ?? null,
            $input['actual_inspection_date'] ?? null,
            $input['inspector_emp_id'] ?? null,
            $input['action_taken_id'] ?? null,
            $input['notes'] ?? null,
            $input['updated_by_emp_id'] ?? null,
            $id
        );
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['error' => $stmt->error]);
            break;
        }
        $stmt->close();

        // replace products: delete then insert
        $mysqli->query("DELETE FROM tblproducts WHERE inspection_id = " . intval($id));
        $products = $input['products'] ?? [];
        if (!empty($products)) {
            $pstmt = $mysqli->prepare("INSERT INTO tblproducts (inspection_id, product_name, brand_name, country_id, packaging_type, quantity, weight, production_date, expiry_date, category_id, serial, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            foreach ($products as $p) {
                $inspection_id = $id;
                $product_name = $p['product_name'] ?? '';
                $brand_name = $p['brand_name'] ?? '';
                $country_id = $p['country_id'] ?? null;
                $packaging_type = $p['packaging_type'] ?? '';
                $quantity = $p['quantity'] ?? 0;
                $weight = $p['weight'] ?? 0;
                $production_date = $p['production_date'] ?: null;
                $expiry_date = $p['expiry_date'] ?: null;
                $category_id = $p['category_id'] ?? null;
                $serial = $p['serial'] ?? '';
                $notes = $p['notes'] ?? '';
                $pstmt->bind_param('ississddssss', $inspection_id, $product_name, $brand_name, $country_id, $packaging_type, $quantity, $weight, $production_date, $expiry_date, $category_id, $serial, $notes);
                $pstmt->execute();
            }
            $pstmt->close();
        }

        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'delete':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'id required']); break; }
        $mysqli->query("DELETE FROM tblproducts WHERE inspection_id = $id");
        $mysqli->query("DELETE FROM import_export_inspections WHERE ID = $id");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'unknown action']);
}
$mysqli->close();
