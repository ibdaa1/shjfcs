<?php
// upload_inspection_pdf.php
session_start();
require_once 'auth.php'; // تأكد من تضمين ملف التحقق من الصلاحيات إذا لزم الأمر

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // إذا كان هناك حاجة لـ CORS
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'الطريقة غير مدعومة. استخدم POST فقط.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'upload_pdf') {
    // رفع الملف الجديد أو استبدال الموجود
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

    // التحقق من نوع الملف
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime_type !== 'application/pdf') {
        echo json_encode(['success' => false, 'message' => 'الملف يجب أن يكون PDF فقط.']);
        exit;
    }

    // التحقق من حجم الملف (3 ميجا كحد أقصى)
    if ($file['size'] > 3 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'حجم الملف يجب أن يكون أقل من 3 ميجا.']);
        exit;
    }

    // إنشاء مجلد الرفع إذا لم يكن موجودًا
    $upload_dir = 'uploads/inspections/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'فشل في إنشاء مجلد الرفع.']);
            exit;
        }
    }

    // اسم الملف الثابت لكل تفتيش للسماح بالاستبدال التلقائي
    $filename = 'inspection_' . $inspection_id . '.pdf';
    $filepath = $upload_dir . $filename;

    // حذف الملف القديم إذا كان موجودًا (استبدال)
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    // نقل الملف الجديد
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // يمكن تحديث قاعدة البيانات هنا إذا لزم الأمر، لكن نفترض أن api.php يتعامل مع ذلك
        $relative_path = $upload_dir . $filename;
        echo json_encode([
            'success' => true, 
            'path' => $relative_path,
            'message' => 'تم رفع الملف بنجاح.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في حفظ الملف. تحقق من الصلاحيات.']);
    }

} elseif ($action === 'delete') {
    // حذف الملف عند حذف النموذج
    $inspection_id = $_POST['inspection_id'] ?? '';

    if (empty($inspection_id)) {
        echo json_encode(['success' => false, 'message' => 'معرف التفتيش مطلوب.']);
        exit;
    }

    $filename = 'inspection_' . $inspection_id . '.pdf';
    $filepath = 'uploads/inspections/' . $filename;

    if (file_exists($filepath)) {
        if (unlink($filepath)) {
            // يمكن تحديث قاعدة البيانات هنا لمسح المسار إذا لزم الأمر
            echo json_encode([
                'success' => true, 
                'message' => 'تم حذف الملف بنجاح.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'فشل في حذف الملف. تحقق من الصلاحيات.']);
        }
    } else {
        // لا يوجد ملف، فهو محذوف بالفعل
        echo json_encode([
            'success' => true, 
            'message' => 'لا يوجد ملف لحذفه.'
        ]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'عملية غير صالحة.']);
}
?>