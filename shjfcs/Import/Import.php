<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

session_start();
if(!isset($_SESSION['user'])){
    echo json_encode(['success'=>false,'message'=>'غير مسموح']);
    exit;
}

$conn->query("SET time_zone = '+04:00'");

try {
    $conn->begin_transaction();

    // حفظ الفحص
    $stmt = $conn->prepare("INSERT INTO import_export_inspections
    (system_registration_no, shipment_numbers, container_count, container_numbers, registration_date, entry_port_id, actual_inspection_date, action_taken, notes, created_by_emp_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ssissssssi",
        $_POST['system_registration_no'],
        $_POST['shipment_numbers'],
        $_POST['container_count'],
        $_POST['container_numbers'],
        $_POST['registration_date'],
        $_POST['entry_port_id'],
        $_POST['actual_inspection_date'],
        $_POST['action_taken'],
        $_POST['notes'],
        $_SESSION['user']['EmpID']
    );
    $stmt->execute();
    $inspection_id = $stmt->insert_id;
    $stmt->close();

    // حفظ المنتجات
    $product_count = count($_POST['product_name']);
    $stmt = $conn->prepare("INSERT INTO tblproducts
    (inspection_id, product_name, brand_name, category_id, country_code, packaging_type, quantity, weight, production_date, expiry_date, serial, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    for($i=0; $i<$product_count; $i++){
        $stmt->bind_param("ississddssss",
            $inspection_id,
            $_POST['product_name'][$i],
            $_POST['brand_name'][$i],
            $_POST['category_id'][$i],
            $_POST['country_code'][$i],
            $_POST['packaging_type'][$i],
            $_POST['quantity'][$i],
            $_POST['weight'][$i],
            $_POST['production_date'][$i],
            $_POST['expiry_date'][$i],
            $_POST['serial'][$i],
            $_POST['notes'][$i]
        );
        $stmt->execute();
    }
    $stmt->close();

    $conn->commit();
    echo json_encode(['success'=>true]);
} catch(Exception $e){
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
