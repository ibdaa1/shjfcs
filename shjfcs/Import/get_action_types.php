<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// الاتصال بقاعدة البيانات
require_once '../db.php';

try {
    $query = "SELECT id, action_name FROM action_types ORDER BY id ASC";
    $result = $conn->query($query);

    $data = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'id' => (int)$row['id'],
                'action_name' => $row['action_name']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch action types',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
