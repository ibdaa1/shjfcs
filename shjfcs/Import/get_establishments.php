<?php
// get_establishments.php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
try {
    $dbFile = realpath(__DIR__ . '/../db.php');
    if (!$dbFile || !file_exists($dbFile)) throw new Exception('db.php not found');
    require_once $dbFile;
    if (!isset($conn) || !($conn instanceof mysqli)) throw new Exception('DB connection not available');

    $sql = "SELECT ID, license_no, unique_id, facility_name, brand_name, area, sub_area, phone_number, email FROM establishments ORDER BY license_no, unique_id";
    $res = $conn->query($sql);
    $rows = [];
    while($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['data' => $rows]);
    exit;
} catch (Throwable $ex) {
    error_log('get_establishments.php error: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'Internal server error']);
    exit;
}
?>
