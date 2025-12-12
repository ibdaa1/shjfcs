<?php
// get_establishments.php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once '../db.php'; // ملف الاتصال بقاعدة البيانات

$conn->set_charset("utf8mb4"); // لضمان دعم العربية

$sql = "SELECT ID, unique_id, facility_name, brand_name, area, sub_area, description 
        FROM establishments 
        WHERE facility_status = 'نشط' 
        ORDER BY facility_name ASC";

$result = $conn->query($sql);

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'ID' => $row['ID'],
            'unique_id' => $row['unique_id'],
            'facility_name' => $row['facility_name'],
            'brand_name' => $row['brand_name'],
            'area' => $row['area'],
            'sub_area' => $row['sub_area'],
            'description' => $row['description']
        ];
    }
}

echo json_encode([
    'success' => true,
    'data' => $data
], JSON_UNESCAPED_UNICODE);

$conn->close();
