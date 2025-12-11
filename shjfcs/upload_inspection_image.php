<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once("db.php");

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'فشل الاتصال بقاعدة البيانات']);
    exit;
}

$inspection_id = isset($_POST['inspection_id']) ? intval($_POST['inspection_id']) : 0;
$code_id = isset($_POST['code_id']) ? intval($_POST['code_id']) : 0;
$action = $_POST['action'] ?? '';
$image = $_FILES['image'] ?? null;

if (!$inspection_id || !$code_id) {
    echo json_encode(['success' => false, 'message' => 'الحقول inspection_id و code_id مطلوبة.']);
    exit;
}

$uploadDir = "uploads/inspection_images/{$inspection_id}_{$code_id}/";

// حذف الصورة
if ($action === 'delete') {
    $stmt = $conn->prepare("SELECT inspection_photo_path FROM tbl_inspection_items WHERE inspection_id = ? AND code_id = ?");
    $stmt->bind_param("ii", $inspection_id, $code_id);
    $stmt->execute();
    $stmt->bind_result($photoPath);
    if ($stmt->fetch() && $photoPath && file_exists($photoPath)) {
        unlink($photoPath); // حذف الصورة من المجلد
    }
    $stmt->close();

    // حذف الرابط من الجدول
    $stmt = $conn->prepare("UPDATE tbl_inspection_items SET inspection_photo_path = NULL WHERE inspection_id = ? AND code_id = ?");
    $stmt->bind_param("ii", $inspection_id, $code_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'تم حذف الصورة بنجاح.']);
    exit;
}

// رفع صورة جديدة
if ($action === 'upload' && $image && $image['error'] === UPLOAD_ERR_OK) {
    // حذف الصورة القديمة
    $stmt = $conn->prepare("SELECT inspection_photo_path FROM tbl_inspection_items WHERE inspection_id = ? AND code_id = ?");
    $stmt->bind_param("ii", $inspection_id, $code_id);
    $stmt->execute();
    $stmt->bind_result($oldPath);
    if ($stmt->fetch() && $oldPath && file_exists($oldPath)) {
        unlink($oldPath); // حذف الصورة السابقة
    }
    $stmt->close();

    // حفظ الصورة الجديدة
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($image['name'], PATHINFO_EXTENSION);
    $filename = "photo_" . time() . "." . $extension;
    $targetFile = $uploadDir . $filename;

    if (!move_uploaded_file($image['tmp_name'], $targetFile)) {
        echo json_encode(['success' => false, 'message' => 'فشل نقل الصورة.']);
        exit;
    }

    // تحديث قاعدة البيانات
    $stmt = $conn->prepare("UPDATE tbl_inspection_items SET inspection_photo_path = ? WHERE inspection_id = ? AND code_id = ?");
    $stmt->bind_param("sii", $targetFile, $inspection_id, $code_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'تم رفع الصورة بنجاح.',
        'path' => $targetFile
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء رفع الصورة.']);