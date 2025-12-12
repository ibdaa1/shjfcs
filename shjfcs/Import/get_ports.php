<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db.php';

$conn->query("SET NAMES utf8mb4");
$conn->query("SET CHARACTER SET utf8mb4");

$sql = "SELECT ID, port_name FROM ports ORDER BY ID ASC";
$result = $conn->query($sql);

$ports = [];
while($row = $result->fetch_assoc()){
    $ports[] = [
        'id' => $row['ID'],
        'name' => $row['port_name']   // استخدم مفتاح 'name' لتسهيل العرض
    ];
}

echo json_encode([
    'success' => true,
    'data' => $ports
], JSON_UNESCAPED_UNICODE); // هذا يحافظ على العربية
