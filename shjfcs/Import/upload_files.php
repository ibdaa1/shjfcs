<?php
// إعدادات عامة
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// بدء الجلسة إذا لم تبدأ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// مسار رفع الملفات
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// تحقق من استقبال الملف
if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'لم يتم اختيار أي ملف.']);
    exit;
}

$file = $_FILES['file'];

// الحد الأقصى للحجم بالبايت (2 ميجا)
$maxFileSize = 2 * 1024 * 1024;

// التحقق من حجم الملف
if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'حجم الملف يجب ألا يتجاوز 2 ميجابايت.']);
    exit;
}

// أنواع الملفات المسموح بها
$allowedMimeTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif'
];

// التحقق من نوع الملف
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedMimeTypes)) {
    echo json_encode(['success' => false, 'message' => 'الملف غير مسموح به. يجب أن يكون PDF أو صورة.']);
    exit;
}

// إنشاء اسم ملف فريد
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$newFileName = uniqid('upload_', true) . '.' . $ext;

// رفع الملف
if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
    echo json_encode([
        'success' => true,
        'message' => 'تم رفع الملف بنجاح.',
        'file_name' => $newFileName,
        'file_path' => 'uploads/' . $newFileName
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء رفع الملف.']);
}
?>
