<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once '../db.php';

try {
    $query = "SELECT CATEGORY_ID, CATEGORY_NAME_AR FROM product_listings ORDER BY CATEGORY_NAME_AR ASC";
    $result = $conn->query($query);

    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'id' => $row['CATEGORY_ID'],
            'name' => $row['CATEGORY_NAME_AR']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $categories
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
