<?php
// upload_inspection_pdf.php
session_start();
require_once 'auth.php'; // إذا كان موجودًا
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'الطريقة غير مدعومة. استخدم POST فقط.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'upload_pdf') {
    // الكود الحالي للرفع (بدون تغيير)
    $inspection_id = $_POST['inspection_id'] ?? '';
    $file = $_FILES['pdf_file'] ?? null;

    if (empty($inspection_id)) {
        echo json_encode(['success' => false, 'message' => 'معرف التفتيش مطلوب.']);
        exit;
    }
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'خطأ في رفع الملف.']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime_type !== 'application/pdf') {
        echo json_encode(['success' => false, 'message' => 'الملف يجب أن يكون PDF فقط.']);
        exit;
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'حجم الملف يجب أن يكون أقل من 3 ميجا.']);
        exit;
    }

    $upload_dir = 'uploads/inspections/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = 'inspection_' . $inspection_id . '.pdf';
    $filepath = $upload_dir . $filename;

    // حذف الملف القديم إذا وجد (استبدال)
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relative_path = $upload_dir . $filename;
        echo json_encode([
            'success' => true,
            'path' => $relative_path,
            'message' => 'تم رفع الملف بنجاح.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في حفظ الملف.']);
    }

} elseif ($action === 'delete_pdf') {
    // عملية حذف الملف PDF المرفوع
    $inspection_id = $_POST['inspection_id'] ?? '';
    if (empty($inspection_id)) {
        echo json_encode(['success' => false, 'message' => 'معرف التفتيش مطلوب.']);
        exit;
    }

    $filename = 'inspection_' . $inspection_id . '.pdf';
    $filepath = 'uploads/inspections/' . $filename;

    if (file_exists($filepath)) {
        if (unlink($filepath)) {
            echo json_encode([
                'success' => true,
                'message' => 'تم حذف ملف PDF بنجاح.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'فشل حذف الملف (مشكلة صلاحيات).']);
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'لا يوجد ملف PDF لحذفه.'
        ]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'عملية غير مدعومة.']);
}
?>
