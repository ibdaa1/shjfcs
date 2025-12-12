<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once '../db.php';

try {
    $query = "SELECT ID, ARABIC_NAME FROM countries ORDER BY ARABIC_NAME ASC";
    $result = $conn->query($query);

    $countries = [];
    while ($row = $result->fetch_assoc()) {
        $countries[] = [
            'ID' => $row['ID'],
            'name' => $row['ARABIC_NAME']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $countries
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
