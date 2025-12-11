<?php
// api.php
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Dubai');
// Start session at the very beginning of the script
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// تعيين رأس الاستجابة لضمان JSON
header('Content-Type: application/json; charset=UTF-8');
// رؤوس CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
// تضمين ملف اتصال قاعدة البيانات
require_once 'db.php';
require_once 'schedule.php';
require_once 'inspection_calculations.php';
// التحقق من اتصال قاعدة البيانات
if ($conn->connect_error) {
    logError("Database Connection Failed: " . $conn->connect_error);
    $response_data = [
        'success' => false,
        'message' => 'خطأ في الاتصال بقاعدة البيانات.',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    ob_end_flush();
    exit;
}
// ✅ إضافة بيانات المستخدم وصلاحياته من الجلسة
$user_id = $_SESSION['user']['empid'] ?? null;
$user_name = $_SESSION['user']['EmpName'] ?? '';
$user_role = $_SESSION['user']['role'] ?? ''; // مثال: admin, viewer, editor
$user_permissions = $_SESSION['user']['permissions'] ?? []; // مصفوفة مثل ['view', 'edit', 'delete']
// 🔒 دالة للتحقق من الصلاحيات
function hasPermission($permission) {
    global $user_role, $user_permissions;
    return $user_role === 'admin' || in_array($permission, $user_permissions);
}
// ✅ دالة تسجيل الأخطاء
if (!function_exists('logError')) {
    function logError($message, $context = []) {
        error_log(date('Y-m-d H:i:s') . " - Application Error: " . $message . " " . json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, 'error.log');
    }
}
// ✅ دالة مساعدة لحذف ملفات التفتيش
function deleteInspectionFiles($filePaths) {
    $deletedCount = 0;
    $errorCount = 0;
   
    foreach ($filePaths as $filePath) {
        if (!empty($filePath) && file_exists($filePath)) {
            if (unlink($filePath)) {
                $deletedCount++;
                logError("Successfully deleted inspection file: " . $filePath);
            } else {
                $errorCount++;
                logError("Failed to delete inspection file: " . $filePath);
            }
        }
    }
   
    return [
        'deleted' => $deletedCount,
        'errors' => $errorCount
    ];
}
// نقطة الدخول لطلبات AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response_data = ['success' => false, 'message' => 'إجراء غير صالح.', 'timestamp' => date('Y-m-d H:i:s')];
    // Get logged-in user EmpID from session
    $loggedInUserId = $_SESSION['user']['EmpID'] ?? null;
    if ($loggedInUserId === null && $action !== 'login') { // Allow login action without loggedInUserId
        logError("User ID not found in session for action: " . $action);
        $response_data = [
            'success' => false,
            'message' => 'خطأ: معرف المستخدم غير متاح. يرجى تسجيل الدخول مرة أخرى.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        ob_end_flush();
        exit;
    }
    switch ($action) {
        case 'search_establishments':
            $searchTerm = trim($_POST['searchTerm'] ?? '');
            $isSpecificSearch = isset($_POST['isSpecificSearch']) ? (int)$_POST['isSpecificSearch'] : 0;
            $establishments = [];
            // تحديد الحقول مع التأكد من Sub_Sector وshfhsp وجميع الحقول المطلوبة
            $selectFields = "ID, license_no, unique_id, facility_name, brand_name, area, sub_area, activity_type, hazard_class,
                             LicenseIssuing, ltype, sub_no, Building, detailed_activities, facility_status, unit, Sub_UNIT,
                             site_coordinates, Sector, Sub_Sector, shfhsp, lstart_date, lend_date, user, area_id, phone_number, email, front_image_url, entry_permit_no, created_at, updated_at";
            if (empty($searchTerm)) {
                logError("No search term provided.", ['action' => $action, 'isSpecificSearch' => $isSpecificSearch]);
                $response_data = [
                    'success' => false,
                    'message' => 'يرجى إدخال رقم الرخصة أو اسم المنشأة.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                break;
            }
            try {
                // تنظيف مصطلح البحث لمنع الحقن
                $searchTerm = $conn->real_escape_string($searchTerm);
                if ($isSpecificSearch === 1 || ctype_digit($searchTerm)) {
                    $stmt = $conn->prepare("SELECT $selectFields FROM establishments WHERE license_no = ? LIMIT 20");
                    if (!$stmt) {
                        throw new Exception("Failed to prepare statement for exact search: " . $conn->error);
                    }
                    $stmt->bind_param("s", $searchTerm);
                } else {
                    $likeSearchTerm = '%' . $searchTerm . '%';
                    $stmt = $conn->prepare("SELECT $selectFields FROM establishments WHERE license_no LIKE ? OR facility_name LIKE ? LIMIT 20");
                    if (!$stmt) {
                        throw new Exception("Failed to prepare statement for LIKE search: " . $conn->error);
                    }
                    $stmt->bind_param("ss", $likeSearchTerm, $likeSearchTerm);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    // تنظيف البيانات لضمان توافق JSON
                    foreach ($row as $key => $value) {
                        if (is_string($value)) {
                            $row[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                        }
                    }
                    $row['Sub_Sector'] = (int)($row['Sub_Sector'] ?? 0);
                    $row['shfhsp'] = $row['shfhsp'] ?? '';
                    $establishments[] = $row;
                }
                $stmt->close();
                foreach ($establishments as &$est) {
                    $est['last_inspection_date'] = null;
                    $est['last_inspection_notes'] = null; // Changed from taken_actions_previous to notes
                    $est['last_evaluation_date'] = null;
                    // Fetch last inspection date and notes (formerly taken_actions)
                    $stmt_last_insp = $conn->prepare("SELECT inspection_date, notes FROM tbl_inspections WHERE facility_unique_id = ? ORDER BY inspection_date DESC, inspection_id DESC LIMIT 1");
                    if ($stmt_last_insp) {
                        $stmt_last_insp->bind_param("s", $est['unique_id']);
                        $stmt_last_insp->execute();
                        $result_last_insp = $stmt_last_insp->get_result();
                        if ($row_last_insp = $result_last_insp->fetch_assoc()) {
                            $est['last_inspection_date'] = $row_last_insp['inspection_date'];
                            $est['last_inspection_notes'] = $row_last_insp['notes'] ?? 'لا توجد ملاحظات سابقة';
                        }
                        $stmt_last_insp->close();
                    } else {
                        logError("Error preparing last inspection query: " . $conn->error, ['unique_id' => $est['unique_id']]);
                    }
                    $stmt_last_eval = $conn->prepare("SELECT MAX(assessment_date) AS last_eval_date FROM tbl_evaluation_factors WHERE facility_unique_id = ?");
                    if ($stmt_last_eval) {
                        $stmt_last_eval->bind_param("s", $est['unique_id']);
                        $stmt_last_eval->execute();
                        $result_last_eval = $stmt_last_eval->get_result();
                        if ($row_last_eval = $result_last_eval->fetch_assoc()) {
                            $est['last_evaluation_date'] = $row_last_eval['last_eval_date'];
                        }
                        $stmt_last_eval->close();
                    } else {
                        logError("Error preparing last evaluation query: " . $conn->error, ['unique_id' => $est['unique_id']]);
                    }
                }
                unset($est); // Break the reference with the last element
                usort($establishments, function($a, $b) {
                    return strcmp($a['license_no'], $b['license_no']);
                });
                $response_data = [
                    'success' => true,
                    'data' => $establishments,
                    'message' => empty($establishments) ? 'لم يتم العثور على منشآت.' : 'تم جلب المنشآت بنجاح.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } catch (Exception $e) {
                logError("Error in search_establishments: " . $e->getMessage(), [
                    'searchTerm' => $searchTerm,
                    'isSpecificSearch' => $isSpecificSearch
                ]);
                $response_data = [
                    'success' => false,
                    'message' => 'خطأ أثناء البحث عن المنشآت: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
        case 'create_inspection':
            $facilityUniqueId = trim($_POST['facility_unique_id'] ?? '');
            $inspectionDate = $_POST['inspection_date'] ?? '';
            $inspectionType = $_POST['inspection_type'] ?? '';
            $campaignName = $_POST['campaign_name'] ?? null;
            // ✅ تعديل: استخدام القيمة المرسلة من JS إذا كانت موجودة، fallback إلى session
            $inspectorUserId = $_POST['inspector_user_id'] ?? $loggedInUserId;
            if (empty($inspectorUserId)) {
                logError("inspector_user_id is empty in create_inspection.", ['posted_id' => $_POST['inspector_user_id'] ?? 'null', 'session_id' => $loggedInUserId]);
                $response_data = [
                    'success' => false,
                    'message' => 'معرف المفتش غير صالح.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                break;
            }
            $notes = $_POST['notes'] ?? null;
            $violationRefNo = $_POST['violation_ref_no'] ?? null;
            $photoFile = $_POST['photo_file'] ?? null; // ✅ إضافة حقل photo_file
            // ✅ تصحيح مسار photo_file قبل الحفظ (مثل عملية الرفع في upload_inspection_pdf.php)
            if ($photoFile !== null && $photoFile !== '') {
                $photoFile = str_replace('uploads/inspections/uploads/inspections/', 'uploads/inspections/', $photoFile);
                logError("Corrected photo_file path during create_inspection: " . $photoFile, ['inspection_id' => 'new', 'inspector_user_id' => $inspectorUserId]);
            }
            if (empty($facilityUniqueId) || empty($inspectionDate) || empty($inspectionType)) {
                logError("Missing required fields for create_inspection.", [
                    'facility_unique_id' => $facilityUniqueId,
                    'inspection_date' => $inspectionDate,
                    'inspection_type' => $inspectionType,
                    'inspector_user_id' => $inspectorUserId
                ]);
                $response_data = [
                    'success' => false,
                    'message' => 'البيانات الأساسية للتفتيش غير مكتملة.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                break;
            }
            try {
                $stmt = $conn->prepare("INSERT INTO tbl_inspections (facility_unique_id, inspection_date, inspection_type, campaign_name, inspector_user_id, notes, violation_ref_no, approval_status, approved_by_user_id, approval_date, photo_file, created_at, updated_at, updated_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement for create_inspection: " . $conn->error);
                }
                $defaultApprovalStatus = 'Pending';
                $defaultApprovedByUserId = null;
                $defaultApprovalDate = null;
                $stmt->bind_param("ssssisssissi",
                    $facilityUniqueId,
                    $inspectionDate,
                    $inspectionType,
                    $campaignName,
                    $inspectorUserId, // ✅ الآن يستخدم القيمة المرسلة أو session
                    $notes,
                    $violationRefNo,
                    $defaultApprovalStatus,
                    $defaultApprovedByUserId, // null
                    $defaultApprovalDate, // null
                    $photoFile, // ✅ ربط photo_file المصحح
                    $loggedInUserId
                );
                if ($stmt->execute()) {
                    $newInspectionId = $conn->insert_id;
                    logError("Created new inspection with inspector_user_id: " . $inspectorUserId, ['inspection_id' => $newInspectionId]);
                    $response_data = [
                        'success' => true,
                        'inspection_id' => $newInspectionId,
                        'message' => 'تم إنشاء التفتيش بنجاح.',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                } else {
                    throw new Exception("Failed to execute create_inspection: " . $stmt->error);
                }
                $stmt->close();
            } catch (Exception $e) {
                logError("Error in create_inspection: " . $e->getMessage(), ['facility_unique_id' => $facilityUniqueId, 'inspector_user_id' => $inspectorUserId]);
                $response_data = [
                    'success' => false,
                    'message' => 'فشل إنشاء التفتيش: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
        case 'get_facility_inspections':
            $facilityUniqueId = $_POST['facility_unique_id'] ?? null;
            $inspectorUserId = $_POST['inspector_user_id'] ?? $loggedInUserId;
            $inspections = [];
            if (empty($facilityUniqueId)) {
                logError("No facility_unique_id provided for get_facility_inspections.", ['action' => $action]);
                $response_data = [
                    'success' => false,
                    'message' => 'يرجى إدخال المعرف الفريد للمنشأة.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                break;
            }
            try {
                $query = "SELECT inspection_id, inspection_date, facility_unique_id, notes, inspection_type, campaign_name FROM tbl_inspections WHERE facility_unique_id = ? AND inspector_user_id = ?";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement for get_facility_inspections: " . $conn->error);
                }
                $stmt->bind_param("si", $facilityUniqueId, $inspectorUserId);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    foreach ($row as $key => $value) {
                        if (is_string($value)) {
                            $row[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                        }
                    }
                    $inspections[] = $row;
                }
                $stmt->close();
                $response_data = [
                    'success' => true,
                    'data' => $inspections,
                    'message' => empty($inspections) ? 'لا توجد تفتيشات سابقة لهذه المنشأة.' : 'تم جلب تفتيشات المنشأة بنجاح.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } catch (Exception $e) {
                logError("Error in get_facility_inspections: " . $e->getMessage(), ['facility_unique_id' => $facilityUniqueId, 'inspector_user_id' => $inspectorUserId]);
                $response_data = [
                    'success' => false,
                    'message' => 'خطأ في جلب تفتيشات المنشأة: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
        case 'get_inspection_codes':
            $inspectionId = $_POST['inspection_id'] ?? null;
            $codes = [];
            try {
                // دائماً جلب جميع البنود بدون فلتر بناءً على activity_type
                $query = "SELECT code_id, code_description, code_category, code_categorized, standard_reference, fixed_corrective_action, default_action_type, violation_value_text
                          FROM tbl_inspection_code
                          ORDER BY code_category DESC, code_id ASC";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement for inspection codes: " . $conn->error);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    foreach ($row as $key => $value) {
                        if (is_string($value)) {
                            $row[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                        }
                    }
                    $codes[] = $row;
                }
                $stmt->close();
                if ($inspectionId) {
                    $stmt_existing_items = $conn->prepare("SELECT code_id, is_violation, action_taken, condition_level, deducted_points, inspector_notes, violation_value, inspection_photo_path FROM tbl_inspection_items WHERE inspection_id = ?");
                    if ($stmt_existing_items) {
                        $stmt_existing_items->bind_param("i", $inspectionId);
                        $stmt_existing_items->execute();
                        $result_existing_items = $stmt_existing_items->get_result();
                        $existingItemsData = [];
                        while ($row_item = $result_existing_items->fetch_assoc()) {
                            foreach ($row_item as $key => $value) {
                                if (is_string($value)) {
                                    $row_item[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                                }
                            }
                            $existingItemsData[$row_item['code_id']] = $row_item;
                        }
                        $stmt_existing_items->close();
                        foreach ($codes as $key => $code) {
                            if (isset($existingItemsData[$code['code_id']])) {
                                $codes[$key]['inspection_item_data'] = $existingItemsData[$code['code_id']];
                            }
                        }
                    } else {
                        logError("Failed to prepare statement for existing inspection items: " . $conn->error, ['inspection_id' => $inspectionId]);
                    }
                }
                $response_data = [
                    'success' => true,
                    'data' => $codes,
                    'message' => empty($codes) ? 'لم يتم العثور على بنود تفتيش.' : 'تم جلب بنود التفتيش بنجاح.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } catch (Exception $e) {
                logError("Error in get_inspection_codes: " . $e->getMessage());
                $response_data = [
                    'success' => false,
                    'message' => 'خطأ في جلب بنود التفتيش: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
        case 'check_previous_violation':
            $facilityUniqueId = $_POST['facility_unique_id'] ?? null;
            $codeId = $_POST['code_id'] ?? null;
            $currentInspectionId = $_POST['current_inspection_id'] ?? null;
            if (!$facilityUniqueId || !$codeId) {
                logError("Missing parameters for check_previous_violation.", ['facility_unique_id' => $facilityUniqueId, 'code_id' => $codeId]);
                $response_data = [
                    'success' => false,
                    'message' => 'بيانات غير كافية للتحقق من المخالفات السابقة.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                break;
            }
            try {
                $repeated_count = 0;
                $query_prev_inspections = "
                    SELECT i.inspection_id
                    FROM tbl_inspections i
                    WHERE i.facility_unique_id = ?
                ";
                $params_prev_inspections = [$facilityUniqueId];
                $types_prev_inspections = "s";
                if ($currentInspectionId) {
                    $query_prev_inspections .= " AND i.inspection_id != ?";
                    $params_prev_inspections[] = $currentInspectionId;
                    $types_prev_inspections .= "i";
                }
                $query_prev_inspections .= " ORDER BY i.inspection_date DESC, i.inspection_id DESC LIMIT 3";
                $stmt_prev_inspections = $conn->prepare($query_prev_inspections);
                if (!$stmt_prev_inspections) {
                    throw new Exception("Failed to prepare stmt_prev_inspections: " . $conn->error);
                }
                call_user_func_array([$stmt_prev_inspections, 'bind_param'], array_merge([$types_prev_inspections], $params_prev_inspections));
                $stmt_prev_inspections->execute();
                $result_prev_inspections = $stmt_prev_inspections->get_result();
                $previous_inspection_ids = [];
                while ($row_prev = $result_prev_inspections->fetch_assoc()) {
                    $previous_inspection_ids[] = $row_prev['inspection_id'];
                }
                $stmt_prev_inspections->close();
                if (!empty($previous_inspection_ids)) {
                    $placeholders = implode(',', array_fill(0, count($previous_inspection_ids), '?'));
                    $types = str_repeat('i', count($previous_inspection_ids));
                    // ✅ تعديل: التحقق فقط عند action_taken = 'مخالفة'
                    $stmt_violation_count = $conn->prepare("
                        SELECT COUNT(*) AS violation_count
                        FROM tbl_inspection_items
                        WHERE code_id = ? AND is_violation = 1 AND action_taken = 'مخالفة' AND inspection_id IN ($placeholders)
                    ");
                    if (!$stmt_violation_count) {
                        throw new Exception("Failed to prepare stmt_violation_count: " . $conn->error);
                    }
                    $params_violation_count = array_merge([$types . 'i'], [$codeId], $previous_inspection_ids);
                    $ref_params = [];
                    foreach ($params_violation_count as $key => $value) {
                        $ref_params[$key] = &$params_violation_count[$key];
                    }
                    call_user_func_array([$stmt_violation_count, 'bind_param'], $ref_params);
                    $stmt_violation_count->execute();
                    $result_violation_count = $stmt_violation_count->get_result();
                    $row_violation_count = $result_violation_count->fetch_assoc();
                    $repeated_count = $row_violation_count['violation_count'] ?? 0;
                    $stmt_violation_count->close();
                }
                $isRepeatedViolation = ($repeated_count > 0);
                $response_data = [
                    'success' => true,
                    'is_repeated_violation' => $isRepeatedViolation,
                    'repeated_count' => $repeated_count,
                    'message' => 'تم التحقق من المخالفات السابقة بنجاح.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } catch (Exception $e) {
                logError("Error in check_previous_violation: " . $e->getMessage(), ['facility_unique_id' => $facilityUniqueId, 'code_id' => $codeId]);
                $response_data = [
                    'success' => false,
                    'message' => 'خطأ في التحقق من المخالفات السابقة: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
case 'save_inspection_items':
    $inspection_id = $_POST['inspection_id'] ?? null;
    $inspection_items_data = json_decode($_POST['items_data'] ?? '[]', true);
    $generalNotes = $_POST['notes'] ?? null;
    $inspectionType = $_POST['inspection_type'] ?? null;
    $inspection_date = $_POST['inspection_date'] ?? null;
    $campaignName = $_POST['campaign_name'] ?? null;
    $inspectorId = $_POST['inspector_user_id'] ?? $loggedInUserId;
    $violationRefNo = $_POST['violation_ref_no'] ?? null;
    $photoFile = $_POST['photo_file'] ?? null;
    $updatedByUserId = $loggedInUserId;
   
    // ✅ Logging لتتبع inspection_date عند الوصول
    logError("save_inspection_items: Received data", [
        'inspection_id' => $inspection_id,
        'inspection_date_received' => $inspection_date ?? 'NULL',
        'inspection_type' => $inspectionType
    ]);
   
    // ✅ Fallback للتاريخ إذا كان فارغًا (دائمًا نحدثه)
    if (empty($inspection_date)) {
        $inspection_date = date('Y-m-d'); // التاريخ الحالي كبديل
        logError("Applied fallback date for inspection_date", ['inspection_id' => $inspection_id, 'fallback_date' => $inspection_date]);
    }
   
    // Validate inspection_date format (always now)
    $d = DateTime::createFromFormat('Y-m-d', $inspection_date);
    if (!($d && $d->format('Y-m-d') === $inspection_date)) {
        logError("Invalid inspection_date format provided.", ['inspection_id' => $inspection_id, 'inspection_date' => $inspection_date]);
        $response_data = [
            'success' => false,
            'message' => 'تنسيق تاريخ التفتيش غير صالح. يرجى استخدام YYYY-MM-DD.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        ob_end_flush();
        break;
    }
   
    // Normalize photo path if double nested
    if ($photoFile !== null && $photoFile !== '') {
        $originalPath = $photoFile;
        $photoFile = str_replace('uploads/inspections/uploads/inspections/', 'uploads/inspections/', $photoFile);
        if ($photoFile !== $originalPath) {
            logError("Corrected photo_file path during save_inspection_items: from $originalPath to $photoFile", ['inspection_id' => $inspection_id]);
        }
    }
   
    // Validate inspection_id
    if (!$inspection_id) {
        logError("Invalid inspection_id provided for saving items.", ['inspection_id' => $inspection_id]);
        $response_data = [
            'success' => false,
            'message' => 'معرف التفتيش غير كافٍ لحفظ بنود التفتيش.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        ob_end_flush();
        break;
    }
    if (!is_array($inspection_items_data)) {
        logError("Invalid items_data format. Expected array.", ['inspection_id' => $inspection_id, 'items_data_type' => gettype($inspection_items_data)]);
        $response_data = [
            'success' => false,
            'message' => 'تنسيق بيانات بنود التفتيش غير صالح.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        ob_end_flush();
        break;
    }
   
    try {
        // Get facility_unique_id for repeated_count checks
        $current_facility_unique_id = null;
        $stmt_facility = $conn->prepare("SELECT facility_unique_id FROM tbl_inspections WHERE inspection_id = ?");
        if (!$stmt_facility) throw new Exception("Failed to prepare statement for facility_unique_id: " . $conn->error);
        $stmt_facility->bind_param("i", $inspection_id);
        $stmt_facility->execute();
        $result_facility = $stmt_facility->get_result();
        $row_facility = $result_facility->fetch_assoc();
        if ($row_facility) $current_facility_unique_id = $row_facility['facility_unique_id'];
        $stmt_facility->close();
       
        $conn->begin_transaction();
        $errors = [];
        $successCount = 0;
       
        // Fetch existing items
        $existing_items_map = [];
        $stmt_get_existing = $conn->prepare("SELECT code_id, is_violation, action_taken, condition_level, deducted_points, inspector_notes, repeated_count, violation_value, inspection_photo_path FROM tbl_inspection_items WHERE inspection_id = ?");
        if (!$stmt_get_existing) throw new Exception("Failed to prepare statement for fetching existing items: " . $conn->error);
        $stmt_get_existing->bind_param("i", $inspection_id);
        $stmt_get_existing->execute();
        $result_existing = $stmt_get_existing->get_result();
        while ($row_existing = $result_existing->fetch_assoc()) {
            $existing_items_map[$row_existing['code_id']] = $row_existing;
        }
        $stmt_get_existing->close();
       
        $processed_code_ids_from_client = [];
        foreach ($inspection_items_data as $item) {
            $code_id = intval($item['code_id'] ?? 0);
            $action_taken = $item['action_taken'] ?? null;
            $condition_level = $item['condition_level'] ?? 'N/A';
            $deducted_points = isset($item['deducted_points']) ? floatval($item['deducted_points']) : 0.00;
            $inspector_notes = $item['inspector_notes'] ?? null;
            $violation_value = isset($item['violation_value']) ? floatval($item['violation_value']) : null;
            // corrected field name
            $inspection_photo_path = $item['inspection_photo_path'] ?? null;
            if ($inspection_photo_path !== null && $inspection_photo_path !== '') {
                $originalImgPath = $inspection_photo_path;
                $inspection_photo_path = str_replace('uploads/inspections/uploads/inspections/', 'uploads/inspections/', $inspection_photo_path);
                if ($inspection_photo_path !== $originalImgPath) {
                    logError("Corrected inspection_photo_path for item $code_id: from $originalImgPath to $inspection_photo_path", ['inspection_id' => $inspection_id]);
                }
            }
            $is_violation = (isset($item['is_violation']) && $item['is_violation'] == 1) ? 1 : 0;
            $repeated_count = 0;
            // Recalculate repeated_count based on last 3 inspections where action_taken = 'مخالفة'
            if ($is_violation === 1 && $current_facility_unique_id) {
                $stmt_prev_inspections = $conn->prepare("
                    SELECT i.inspection_id
                    FROM tbl_inspections i
                    WHERE i.facility_unique_id = ? AND i.inspection_id != ?
                    ORDER BY i.inspection_date DESC, i.inspection_id DESC
                    LIMIT 3
                ");
                if ($stmt_prev_inspections) {
                    $stmt_prev_inspections->bind_param("si", $current_facility_unique_id, $inspection_id);
                    $stmt_prev_inspections->execute();
                    $result_prev_inspections = $stmt_prev_inspections->get_result();
                    $previous_inspection_ids = [];
                    while ($row_prev = $result_prev_inspections->fetch_assoc()) {
                        $previous_inspection_ids[] = $row_prev['inspection_id'];
                    }
                    $stmt_prev_inspections->close();
                    if (!empty($previous_inspection_ids)) {
                        $placeholders = implode(',', array_fill(0, count($previous_inspection_ids), '?'));
                        // Prepare the query with code_id + IN (...)
                        $stmt_violation_count = $conn->prepare("
                            SELECT COUNT(*) AS violation_count
                            FROM tbl_inspection_items
                            WHERE code_id = ? AND is_violation = 1 AND action_taken = 'مخالفة' AND inspection_id IN ($placeholders)
                        ");
                        if ($stmt_violation_count) {
                            // Build types: one 'i' for code_id, then 'i' for each previous inspection id
                            $types_all = 'i' . str_repeat('i', count($previous_inspection_ids));
                            $params_violation_count = array_merge([$types_all], [$code_id], $previous_inspection_ids);
                            // bind dynamically
                            $ref_params = [];
                            foreach ($params_violation_count as $k => $v) {
                                $ref_params[$k] = &$params_violation_count[$k];
                            }
                            call_user_func_array([$stmt_violation_count, 'bind_param'], $ref_params);
                            $stmt_violation_count->execute();
                            $result_violation_count = $stmt_violation_count->get_result();
                            $row_violation_count = $result_violation_count->fetch_assoc();
                            $repeated_count = $row_violation_count['violation_count'] ?? 0;
                            $stmt_violation_count->close();
                        } else {
                            logError("Failed to prepare stmt_violation_count: " . $conn->error, ['code_id' => $code_id]);
                        }
                    }
                } else {
                    logError("Failed to prepare stmt_prev_inspections: " . $conn->error, ['facility_unique_id' => $current_facility_unique_id]);
                }
            }
            $processed_code_ids_from_client[] = $code_id;
            if (isset($existing_items_map[$code_id])) {
                // Update if changed
                $existing_item = $existing_items_map[$code_id];
                $has_changed = false;
                if (
                    $existing_item['is_violation'] != $is_violation ||
                    $existing_item['action_taken'] != $action_taken ||
                    $existing_item['condition_level'] != $condition_level ||
                    floatval($existing_item['deducted_points']) != floatval($deducted_points) ||
                    $existing_item['inspector_notes'] != $inspector_notes ||
                    floatval($existing_item['violation_value']) != floatval($violation_value) ||
                    $existing_item['inspection_photo_path'] != $inspection_photo_path ||
                    intval($existing_item['repeated_count']) != intval($repeated_count)
                ) {
                    $has_changed = true;
                }
                if ($has_changed) {
                    $stmt = $conn->prepare("
                        UPDATE tbl_inspection_items
                        SET is_violation = ?, action_taken = ?, condition_level = ?, deducted_points = ?, inspector_notes = ?, repeated_count = ?, violation_value = ?, inspection_photo_path = ?
                        WHERE inspection_id = ? AND code_id = ?
                    ");
                    if (!$stmt) {
                        $errors[] = "خطأ في إعداد استعلام تحديث البند رقم $code_id: " . $conn->error;
                        logError("Failed to prepare UPDATE statement for tbl_inspection_items: " . $conn->error, ['code_id' => $code_id]);
                        continue;
                    }
                    $stmt->bind_param(
                        "issdsidsii",
                        $is_violation,
                        $action_taken,
                        $condition_level,
                        $deducted_points,
                        $inspector_notes,
                        $repeated_count,
                        $violation_value,
                        $inspection_photo_path,
                        $inspection_id,
                        $code_id
                    );
                    if ($stmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "فشل تحديث البند رقم $code_id: " . $stmt->error;
                        logError("Failed to execute UPDATE statement for tbl_inspection_items: " . $stmt->error, ['code_id' => $code_id, 'inspection_id' => $inspection_id]);
                    }
                    $stmt->close();
                } else {
                    $successCount++;
                }
            } else {
                // Insert new item
                $stmt = $conn->prepare("
                    INSERT INTO tbl_inspection_items
                        (inspection_id, code_id, is_violation, action_taken, condition_level, deducted_points, inspector_notes, repeated_count, violation_value, inspection_photo_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if (!$stmt) {
                    $errors[] = "خطأ في إعداد استعلام إدخال البند رقم $code_id: " . $conn->error;
                    logError("Failed to prepare INSERT statement for tbl_inspection_items: " . $conn->error, ['code_id' => $code_id]);
                    continue;
                }
                $stmt->bind_param(
                    "iiissdsids",
                    $inspection_id,
                    $code_id,
                    $is_violation,
                    $action_taken,
                    $condition_level,
                    $deducted_points,
                    $inspector_notes,
                    $repeated_count,
                    $violation_value,
                    $inspection_photo_path
                );
                if ($stmt->execute()) {
                    $successCount++;
                } else {
                    $errors[] = "فشل إدخال البند رقم $code_id: " . $stmt->error;
                    logError("Failed to execute INSERT statement for tbl_inspection_items: " . $stmt->error, ['code_id' => $code_id, 'inspection_id' => $inspection_id]);
                }
                $stmt->close();
            }
        } // end foreach items
       
        // Delete items removed by client
        $code_ids_to_delete = array_diff(array_keys($existing_items_map), $processed_code_ids_from_client);
        if (!empty($code_ids_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($code_ids_to_delete), '?'));
            // Prepare statement: first param is inspection_id, then code_ids
            $stmt_delete = $conn->prepare("DELETE FROM tbl_inspection_items WHERE inspection_id = ? AND code_id IN ($placeholders)");
            if (!$stmt_delete) {
                throw new Exception("Failed to prepare delete statement for specific items: " . $conn->error);
            }
            // types: one i for inspection_id + i * count(code_ids_to_delete)
            $types_delete = str_repeat('i', count($code_ids_to_delete) + 1);
            $params_delete = array_merge([$types_delete], [$inspection_id], array_values($code_ids_to_delete));
            $ref_params_delete = [];
            foreach ($params_delete as $k => $v) {
                $ref_params_delete[$k] = &$params_delete[$k];
            }
            call_user_func_array([$stmt_delete, 'bind_param'], $ref_params_delete);
            if (!$stmt_delete->execute()) {
                $errors[] = "فشل حذف بعض البنود القديمة: " . $stmt_delete->error;
                logError("Failed to execute DELETE statement for specific items: " . $stmt_delete->error, ['inspection_id' => $inspection_id, 'code_ids_to_delete' => $code_ids_to_delete]);
            }
            $stmt_delete->close();
        }
       
        $message_suffix = "";
        if ($successCount === 0 && empty($inspection_items_data) && empty($errors)) {
            $message_suffix = " تم حفظ التفتيش بدون بنود.";
        } elseif (!empty($errors)) {
            $message_suffix = " حدثت أخطاء أثناء حفظ أو تحديث أو حذف بعض البنود.";
        }
       
        if (empty($errors)) {
            // Update photo_file if provided
            if ($photoFile !== null) {
                $stmt_update_pdf = $conn->prepare("UPDATE tbl_inspections SET photo_file = ? WHERE inspection_id = ?");
                if ($stmt_update_pdf) {
                    $stmt_update_pdf->bind_param("si", $photoFile, $inspection_id);
                    $stmt_update_pdf->execute();
                    $stmt_update_pdf->close();
                    logError("Updated photo_file in tbl_inspections: " . $photoFile, ['inspection_id' => $inspection_id]);
                }
            }
           
            // ✅ UPDATE دائم للتفاصيل الرئيسية، بما في ذلك inspection_date دائمًا
            $sql = "UPDATE tbl_inspections SET notes = ?, inspection_type = ?, inspection_date = ?, campaign_name = ?, inspector_user_id = ?, violation_ref_no = ?, updated_by_user_id = ?, updated_at = NOW() WHERE inspection_id = ?";
            $stmt_update_insp = $conn->prepare($sql);
            if ($stmt_update_insp) {
                $types = "ssssisii"; // notes(s), inspection_type(s), inspection_date(s), campaign_name(s), inspector_user_id(i), violation_ref_no(s), updated_by_user_id(i), inspection_id(i)
                $params = [$generalNotes, $inspectionType, $inspection_date, $campaignName, $inspectorId, $violationRefNo, $updatedByUserId, $inspection_id];
                $refs = [];
                $refs[] = &$types;
                foreach ($params as $k => $v) $refs[] = &$params[$k];
                call_user_func_array([$stmt_update_insp, 'bind_param'], $refs);
                if ($stmt_update_insp->execute()) {
                    logError("Successfully updated inspection details including date", [
                        'inspection_id' => $inspection_id,
                        'new_inspection_date' => $inspection_date
                    ]);
                } else {
                    $errors[] = "فشل تحديث تفاصيل التفتيش: " . $stmt_update_insp->error;
                    logError("Failed to execute update main inspection details statement: " . $stmt_update_insp->error, ['inspection_id' => $inspection_id]);
                }
                $stmt_update_insp->close();
            } else {
                logError("Error preparing update main inspection details statement: " . $conn->error, ['inspection_id' => $inspection_id]);
                $errors[] = "خطأ في تحضير استعلام تحديث تفاصيل التفتيش الرئيسية.";
            }
           
            if (empty($errors)) {
                $conn->commit();
                $results = calculateInspectionResults($inspection_id, $conn, $updatedByUserId);
                if ($results === false) {
                    logError("calculateInspectionResults failed after commit", ['inspection_id' => $inspection_id]);
                    // لا rollback؛ الـ commit تم، فقط أضف تحذير في الـ response
                }
                $response_data = [
                    'success' => true,
                    'saved' => $successCount,
                    'results' => $results ?: ['calculation_error' => true], // fallback إذا false
                    'message' => 'تم حفظ بنود التفتيش وحساب النتائج بنجاح.' . (isset($results['schedule_result']['message']) ? ' ' . $results['schedule_result']['message'] : '') . $message_suffix,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } else {
                $conn->rollback();
                $response_data = [
                    'success' => false,
                    'saved' => $successCount,
                    'errors' => $errors,
                    'message' => 'حدثت أخطاء أثناء حفظ بنود التفتيش.' . $message_suffix,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
        } else {
            $conn->rollback();
            $response_data = [
                'success' => false,
                'saved' => $successCount,
                'errors' => $errors,
                'message' => 'حدثت أخطاء أثناء حفظ بنود التفتيش.' . $message_suffix,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    } catch (Exception $e) {
        $conn->rollback();
        logError("Error in save_inspection_items: " . $e->getMessage(), ['inspection_id' => $inspection_id]);
        $response_data = [
            'success' => false,
            'message' => 'خطأ أثناء حفظ بنود التفتيش: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    ob_end_flush();
    break;
// ---------------------------------------------------------------------
case 'update_inspection_date':
    $inspection_id = $_POST['inspection_id'] ?? null;
    $new_inspection_date = $_POST['new_inspection_date'] ?? null;
    if (!$inspection_id || !$new_inspection_date) {
        logError("Missing parameters for update_inspection_date.", ['inspection_id' => $inspection_id, 'new_inspection_date' => $new_inspection_date]);
        $response_data = [
            'success' => false,
            'message' => 'معرف التفتيش أو التاريخ الجديد غير متوفر.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        ob_end_flush();
        break;
    }
    // Validate date format
    $d = DateTime::createFromFormat('Y-m-d', $new_inspection_date);
    if (!($d && $d->format('Y-m-d') === $new_inspection_date)) {
        logError("Invalid new_inspection_date format.", ['inspection_id' => $inspection_id, 'new_inspection_date' => $new_inspection_date]);
        $response_data = [
            'success' => false,
            'message' => 'تنسيق التاريخ الجديد غير صالح. استخدم YYYY-MM-DD.',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        ob_end_flush();
        break;
    }
    try {
        // check owner / permission
        $stmt_check = $conn->prepare("SELECT inspector_user_id FROM tbl_inspections WHERE inspection_id = ?");
        if (!$stmt_check) throw new Exception("Failed to prepare permission check: " . $conn->error);
        $stmt_check->bind_param("i", $inspection_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $inspection = $result_check->fetch_assoc();
        $stmt_check->close();
        if (!$inspection) throw new Exception("التفتيش غير موجود.");
        $isAdmin = $_SESSION['user']['IsAdmin'] ?? 0;
        if ($inspection['inspector_user_id'] != $loggedInUserId && $isAdmin != 1) {
            throw new Exception("لا تملك صلاحية تعديل هذا التفتيش.");
        }
        $stmt = $conn->prepare("UPDATE tbl_inspections SET inspection_date = ?, updated_at = NOW(), updated_by_user_id = ? WHERE inspection_id = ?");
        if (!$stmt) throw new Exception("Failed to prepare statement for update_inspection_date: " . $conn->error);
        $stmt->bind_param("sii", $new_inspection_date, $loggedInUserId, $inspection_id);
        if ($stmt->execute()) {
            // check affected rows? not necessary but informative
            $response_data = [
                'success' => true,
                'message' => 'تم تحديث تاريخ التفتيش بنجاح.',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            throw new Exception("Failed to execute update_inspection_date: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        logError("Error in update_inspection_date: " . $e->getMessage(), ['inspection_id' => $inspection_id, 'new_inspection_date' => $new_inspection_date]);
        $response_data = [
            'success' => false,
            'message' => 'فشل تحديث تاريخ التفتيش: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    ob_end_flush();
    break;
        case 'approve_inspection':
            $inspection_id = $_POST['inspection_id'] ?? null;
            $loggedInUserId = $_SESSION['user']['EmpID'] ?? 0;
            $isAdmin = $_SESSION['user']['IsAdmin'] ?? 0;
            if (!$inspection_id) {
                logError("Missing inspection_id for approve_inspection.", ['inspection_id' => $inspection_id]);
                echo json_encode([
                    'success' => false,
                    'message' => 'معرف التفتيش غير متوفر للموافقة.',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                exit;
            }
            if ($isAdmin != 1) {
                echo json_encode([
                    'success' => false,
                    'message' => 'لا تملك صلاحية اعتماد التفتيش. هذه العملية محصورة على الأدمن فقط.',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                exit;
            }
            try {
                $approvalStatus = 'Approved';
                $approvedByUserId = $loggedInUserId;
                $approvedByUsername = null;
                // جلب اسم المستخدم الذي قام بالموافقة (اختياري للعرض)
                $stmt_user = $conn->prepare("SELECT UserName FROM Users WHERE EmpID = ?");
                if ($stmt_user) {
                    $stmt_user->bind_param("i", $approvedByUserId);
                    $stmt_user->execute();
                    $result_user = $stmt_user->get_result();
                    if ($row_user = $result_user->fetch_assoc()) {
                        $approvedByUsername = $row_user['UserName'];
                    }
                    $stmt_user->close();
                }
                $stmt = $conn->prepare("UPDATE tbl_inspections SET approval_status = ?, approved_by_user_id = ?, approval_date = NOW(), updated_by_user_id = ?, updated_at = NOW() WHERE inspection_id = ?");
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement for approve_inspection: " . $conn->error);
                }
                $stmt->bind_param("siii", $approvalStatus, $approvedByUserId, $loggedInUserId, $inspection_id);
                if ($stmt->execute()) {
                    $response_data = [
                        'success' => true,
                        'approval_status' => $approvalStatus,
                        'approved_by_username' => $approvedByUsername,
                        'approval_date' => date('Y-m-d H:i:s'),
                        'message' => 'تم اعتماد التفتيش بنجاح.',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                } else {
                    throw new Exception("Failed to execute approve_inspection: " . $stmt->error);
                }
                $stmt->close();
            } catch (Exception $e) {
                logError("Error in approve_inspection: " . $e->getMessage(), ['inspection_id' => $inspection_id]);
                $response_data = [
                    'success' => false,
                    'message' => 'فشل اعتماد التفتيش: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            exit;
            break;
        case 'get_inspection_details':
            $inspection_id = $_POST['inspection_id'] ?? null;
            if (!$inspection_id) {
                logError("Missing inspection_id for get_inspection_details.", ['inspection_id' => $inspection_id]);
                $response_data = [
                    'success' => false,
                    'message' => 'معرف التفتيش غير متوفر لجلب التفاصيل.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                break;
            }
            try {
                // جلب تفاصيل التفتيش مع أسماء المستخدمين المرتبطين و photo_file
                $stmt_insp = $conn->prepare("
                    SELECT ti.*,
                           approved_by.UserName AS approved_by_username,
                           updated_by.UserName AS updated_by_username,
                           inspector.UserName AS inspector_username
                    FROM tbl_inspections ti
                    LEFT JOIN Users approved_by ON ti.approved_by_user_id = approved_by.EmpID
                    LEFT JOIN Users updated_by ON ti.updated_by_user_id = updated_by.EmpID
                    LEFT JOIN Users inspector ON ti.inspector_user_id = inspector.EmpID
                    WHERE ti.inspection_id = ?
                ");
                if (!$stmt_insp) {
                    throw new Exception("Error preparing statement: " . $conn->error);
                }
                $stmt_insp->bind_param("i", $inspection_id);
                $stmt_insp->execute();
                $result_insp = $stmt_insp->get_result();
                $inspectionDetails = $result_insp->fetch_assoc();
                $stmt_insp->close();
                if (!$inspectionDetails) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'لم يتم العثور على تفاصيل التفتيش.',
                        'timestamp' => date('Y-m-d H:i:s')
                    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                    ob_end_flush();
                    break;
                }
                // جلب معلومات المنشأة بناءً على المعرف الفريد
                $current_facility_unique_id = $inspectionDetails['facility_unique_id'] ?? null;
                $establishmentSubSector = null;
                $establishmentName = null;
                $establishmentLicenseNo = null;
                $establishmentEmail = null;
                if ($current_facility_unique_id) {
                    $stmt_est = $conn->prepare("SELECT Sub_Sector, facility_name, license_no, email FROM establishments WHERE unique_id = ?");
                    if ($stmt_est) {
                        $stmt_est->bind_param("s", $current_facility_unique_id);
                        $stmt_est->execute();
                        $result_est = $stmt_est->get_result();
                        $row_est = $result_est->fetch_assoc();
                        if ($row_est) {
                            $establishmentSubSector = $row_est['Sub_Sector'];
                            $establishmentName = $row_est['facility_name'];
                            $establishmentLicenseNo = $row_est['license_no'];
                            $establishmentEmail = $row_est['email'];
                        }
                        $stmt_est->close();
                    }
                }
                // جلب ملاحظات التفتيش السابق
                $previous_inspection_notes = null;
                if ($current_facility_unique_id) {
                    $stmt_prev_notes = $conn->prepare("
                        SELECT notes
                        FROM tbl_inspections
                        WHERE facility_unique_id = ? AND inspection_id != ?
                        ORDER BY inspection_date DESC, inspection_id DESC
                        LIMIT 1
                    ");
                    if ($stmt_prev_notes) {
                        $stmt_prev_notes->bind_param("si", $current_facility_unique_id, $inspection_id);
                        $stmt_prev_notes->execute();
                        $result_prev_notes = $stmt_prev_notes->get_result();
                        $row_prev_notes = $result_prev_notes->fetch_assoc();
                        if ($row_prev_notes) {
                            $previous_inspection_notes = $row_prev_notes['notes'];
                        }
                        $stmt_prev_notes->close();
                    }
                }
                // جلب بنود التفتيش مع بيانات إضافية وصور إن وجدت
                $inspectionItems = [];
                $stmt_items = $conn->prepare("
                    SELECT
                        tii.*,
                        tic.code_description,
                        tic.code_category,
                        tic.standard_reference
                    FROM tbl_inspection_items tii
                    LEFT JOIN tbl_inspection_code tic ON tii.code_id = tic.code_id
                    WHERE tii.inspection_id = ?
                    ORDER BY tic.code_category, tic.code_id
                ");
                if ($stmt_items) {
                    $stmt_items->bind_param("i", $inspection_id);
                    $stmt_items->execute();
                    $result_items = $stmt_items->get_result();
                    while ($row_item = $result_items->fetch_assoc()) {
                        // تنظيف الترميز
                        foreach ($row_item as $key => $value) {
                            if (is_string($value)) {
                                $row_item[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                            }
                        }
                        // إضافة رابط الصورة إذا موجودة
                        if (!empty($row_item['inspection_photo_path'])) {
                            // ✅ تصحيح المسار - إزالة التكرار
                            $correctedPath = $row_item['inspection_photo_path'];
                            if (strpos($correctedPath, 'uploads/inspections/uploads/inspections/') !== false) {
                                $correctedPath = str_replace('uploads/inspections/uploads/inspections/', 'uploads/inspections/', $correctedPath);
                            }
                            $row_item['photo_url'] = $correctedPath;
                        } else {
                            $row_item['photo_url'] = null;
                        }
                        $inspectionItems[] = $row_item;
                    }
                    $stmt_items->close();
                }
                // تنظيف الترميز في بيانات التفتيش
                foreach ($inspectionDetails as $key => $value) {
                    if (is_string($value)) {
                        $inspectionDetails[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    }
                }
                // ✅ تصحيح مسار photo_file إذا كان به تكرار
                if (!empty($inspectionDetails['photo_file'])) {
                    $correctedPhotoPath = $inspectionDetails['photo_file'];
                    if (strpos($correctedPhotoPath, 'uploads/inspections/uploads/inspections/') !== false) {
                        $correctedPhotoPath = str_replace('uploads/inspections/uploads/inspections/', 'uploads/inspections/', $correctedPhotoPath);
                        $inspectionDetails['photo_file'] = $correctedPhotoPath;
                    }
                }
                // ****** الخطوة الجديدة: حساب النتائج وإضافتها إلى الاستجابة ******
                $calculatedResults = calculateInspectionResults($inspection_id, $conn, $loggedInUserId);
                if ($calculatedResults === false) {
                    logError("Failed to calculate results for get_inspection_details.", ['inspection_id' => $inspection_id]);
                    // يمكن التعامل مع هذا الخطأ بشكل أكثر لطفاً أو إرجاع نتائج افتراضية
                    $calculatedResults = [];
                }
                $response_data = [
                    'success' => true,
                    'inspection' => $inspectionDetails,
                    'items' => $inspectionItems,
                    'previous_inspection_notes' => $previous_inspection_notes,
                    'establishment_name' => $establishmentName,
                    'establishment_license_no' => $establishmentLicenseNo,
                    'establishment_email' => $establishmentEmail, // أضف هذا الحقل إذا كنت تستخدمه في الواجهة الأمامية
                    'results' => $calculatedResults, // ****** تم إضافة النتائج المحسوبة هنا ******
                    'message' => 'تم جلب تفاصيل التفتيش بنجاح.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } catch (Exception $e) {
                logError("Error in get_inspection_details: " . $e->getMessage(), ['inspection_id' => $inspection_id]);
                $response_data = [
                    'success' => false,
                    'message' => 'خطأ في جلب تفاصيل التفتيش: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
        case 'delete_inspection':
            $inspection_id = $_POST['inspection_id'] ?? null;
            if (!$inspection_id) {
                logError("Missing inspection_id for delete_inspection.", ['inspection_id' => $inspection_id]);
                echo json_encode([
                    'success' => false,
                    'message' => 'معرف التفتيش غير متوفر للحذف.',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                exit;
            }
            // جلب بيانات المستخدم من الجلسة
            $loggedInUserId = $_SESSION['user']['EmpID'] ?? 0;
            // جلب صلاحية الحذف من قاعدة البيانات للمستخدم الحالي
            $stmt_perm = $conn->prepare("SELECT CanDelete FROM Users WHERE EmpID = ?");
            $stmt_perm->bind_param("i", $loggedInUserId);
            $stmt_perm->execute();
            $result_perm = $stmt_perm->get_result();
            $userPerm = $result_perm->fetch_assoc();
            $stmt_perm->close();
            $canDelete = $userPerm['CanDelete'] ?? 0;
            // تحقق من صلاحية الحذف فقط
            if ($canDelete != 1) {
                echo json_encode([
                    'success' => false,
                    'message' => 'لا تملك صلاحية حذف هذا التفتيش.',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                exit;
            }
            // التحقق من وجود التفتيش
            $stmt_check = $conn->prepare("SELECT inspection_id FROM tbl_inspections WHERE inspection_id = ?");
            $stmt_check->bind_param("i", $inspection_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $inspectionExists = $result_check->fetch_assoc();
            $stmt_check->close();
            if (!$inspectionExists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'لم يتم العثور على التفتيش المحدد.',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                exit;
            }
            try {
                $conn->begin_transaction();
                // 1. جمع جميع مسارات الملفات المرتبطة بالتفتيش قبل الحذف
                $filesToDelete = [];
                // أ- جلب photo_file من tbl_inspections
                $stmt_photo = $conn->prepare("SELECT photo_file FROM tbl_inspections WHERE inspection_id = ?");
                $stmt_photo->bind_param("i", $inspection_id);
                $stmt_photo->execute();
                $result_photo = $stmt_photo->get_result();
                if ($row_photo = $result_photo->fetch_assoc()) {
                    if (!empty($row_photo['photo_file'])) {
                        $filesToDelete[] = $row_photo['photo_file'];
                    }
                }
                $stmt_photo->close();
                // ب- جلب inspection_photo_path من tbl_inspection_items
                $stmt_items_photos = $conn->prepare("SELECT inspection_photo_path FROM tbl_inspection_items WHERE inspection_id = ?");
                $stmt_items_photos->bind_param("i", $inspection_id);
                $stmt_items_photos->execute();
                $result_items_photos = $stmt_items_photos->get_result();
                while ($row_item_photo = $result_items_photos->fetch_assoc()) {
                    if (!empty($row_item_photo['inspection_photo_path'])) {
                        $filesToDelete[] = $row_item_photo['inspection_photo_path'];
                    }
                }
                $stmt_items_photos->close();
                // 2. حذف السجلات من قاعدة البيانات
                $stmt_items = $conn->prepare("DELETE FROM tbl_inspection_items WHERE inspection_id = ?");
                $stmt_items->bind_param("i", $inspection_id);
                $stmt_items->execute();
                $stmt_items->close();
                $stmt_actions = $conn->prepare("DELETE FROM tbl_inspection_actions WHERE inspection_id = ?");
                $stmt_actions->bind_param("i", $inspection_id);
                $stmt_actions->execute();
                $stmt_actions->close();
                $stmt_insp = $conn->prepare("DELETE FROM tbl_inspections WHERE inspection_id = ?");
                $stmt_insp->bind_param("i", $inspection_id);
                $stmt_insp->execute();
                $stmt_insp->close();
                $conn->commit();
                // 3. بعد نجاح حذف السجلات من قاعدة البيانات، حذف الملفات الفعلية
                $fileDeletionResults = deleteInspectionFiles($filesToDelete);
                echo json_encode([
                    'success' => true,
                    'message' => 'تم حذف التفتيش وجميع بنوده وإجراءاته وملفاته بنجاح.',
                    'files_deleted' => $fileDeletionResults['deleted'],
                    'files_errors' => $fileDeletionResults['errors'],
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            } catch (Exception $e) {
                $conn->rollback();
                logError("Error in delete_inspection: " . $e->getMessage(), ['inspection_id' => $inspection_id]);
                echo json_encode([
                    'success' => false,
                    'message' => 'فشل حذف التفتيش: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            }
            ob_end_flush();
            exit;
            break;
        case 'get_all_user_inspections':
            $userId = $loggedInUserId;
            $facilityUniqueId = $_POST['facility_unique_id'] ?? null;
            $inspections = [];
            try {
                $query = "SELECT inspection_id, inspection_date, facility_unique_id, notes, inspection_type, campaign_name FROM tbl_inspections WHERE inspector_user_id = ?";
                $types = "i";
                $params = [$userId];
                if ($facilityUniqueId) {
                    $query .= " AND facility_unique_id = ?";
                    $types .= "s";
                    $params[] = $facilityUniqueId;
                }
                $query .= " ORDER BY inspection_date DESC, inspection_id DESC";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement for get_all_user_inspections: " . $conn->error);
                }
                // تمرير المعاملات بالمرجع
                $bind_names[] = $types;
                for ($i=0; $i < count($params); $i++) {
                    $bind_name = 'bind' . $i;
                    $$bind_name = $params[$i];
                    $bind_names[] = &$$bind_name;
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_names);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    foreach ($row as $key => $value) {
                        if (is_string($value)) {
                            $row[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                        }
                    }
                    $inspections[] = $row;
                }
                $stmt->close();
                $response_data = [
                    'success' => true,
                    'inspections' => $inspections,
                    'message' => 'تم جلب تفتيشات المستخدم بنجاح.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } catch (Exception $e) {
                logError("Error in get_all_user_inspections: " . $e->getMessage(), ['user_id' => $userId, 'facility_unique_id' => $facilityUniqueId]);
                $response_data = [
                    'success' => false,
                    'message' => 'خطأ في جلب تفتيشات المستخدم: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
        case 'search_inspections_by_license':
            $licenseNo = trim($_POST['license_no'] ?? '');
            if (empty($licenseNo)) {
                logError("No license number provided for search_inspections_by_license.", ['action' => $action]);
                $response_data = [
                    'success' => false,
                    'message' => 'يرجى إدخال رقم الرخصة.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                break;
            }
            try {
                // تحديد حالة المستخدم (تأكد أنك تعرفها في مكان ما)
                $isAdmin = $_SESSION['user']['IsAdmin'] ?? false;
                $loggedInUserId = $_SESSION['user']['EmpID'] ?? 0;
                // Step 1: Find facility_unique_id(s) from establishments using license_no
                $stmt = $conn->prepare("SELECT unique_id, facility_name FROM establishments WHERE license_no = ?");
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement for license search: " . $conn->error);
                }
                $stmt->bind_param("s", $licenseNo);
                $stmt->execute();
                $result = $stmt->get_result();
                $facilities = [];
                while ($row = $result->fetch_assoc()) {
                    $facilities[$row['unique_id']] = $row['facility_name'];
                }
                $stmt->close();
                if (empty($facilities)) {
                    $response_data = [
                        'success' => false,
                        'message' => 'لم يتم العثور على منشأة برقم الرخصة المحدد.',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                    ob_end_flush();
                    break;
                }
                $facilityUniqueIds = array_keys($facilities);
                $placeholders = implode(',', array_fill(0, count($facilityUniqueIds), '?'));
                $types = str_repeat('s', count($facilityUniqueIds));
                // Step 2: Build the query with optional user restriction
                $query = "
                    SELECT inspection_id, inspection_date, facility_unique_id, inspection_type, campaign_name, notes,
                           approval_status, approved_by_user_id, approval_date, inspector_user_id
                    FROM tbl_inspections
                    WHERE facility_unique_id IN ($placeholders)
                ";
                if (!$isAdmin) {
                    $query .= " AND inspector_user_id = ?";
                    $types .= "i";
                }
                $query .= " ORDER BY inspection_date DESC, inspection_id DESC";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement for inspections search: " . $conn->error);
                }
                // bind_param يحتاج مراجع للمتغيرات
                $bind_names = [];
                $bind_names[] = $types;
                // ربط كل معرف منشأة
                for ($i = 0; $i < count($facilityUniqueIds); $i++) {
                    $bind_name = 'bind' . $i;
                    $$bind_name = $facilityUniqueIds[$i];
                    $bind_names[] = &$$bind_name; // مرجع
                }
                // إذا المستخدم ليس أدمن نربط معرف المستخدم
                if (!$isAdmin) {
                    $bind_user = $loggedInUserId;
                    $bind_names[] = &$bind_user;
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_names);
                $stmt->execute();
                $result = $stmt->get_result();
                $inspections = [];
                while ($row = $result->fetch_assoc()) {
                    foreach ($row as $key => $value) {
                        if (is_string($value)) {
                            $row[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                        }
                    }
                    $row['facility_name'] = $facilities[$row['facility_unique_id']] ?? 'N/A';
                    $inspections[] = $row;
                }
                $stmt->close();
                $response_data = [
                    'success' => true,
                    'data' => $inspections,
                    'message' => empty($inspections) ? 'لم يتم العثور على تفتيشات لهذه المنشأة.' : 'تم جلب سجلات التفتيش بنجاح.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } catch (Exception $e) {
                logError("Error in search_inspections_by_license: " . $e->getMessage(), ['license_no' => $licenseNo]);
                $response_data = [
                    'success' => false,
                    'message' => 'خطأ أثناء البحث عن سجلات التفتيش: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
        case 'get_facility_by_unique_id':
            $facilityUniqueId = trim($_POST['facility_unique_id'] ?? '');
            if (empty($facilityUniqueId)) {
                logError("No facility_unique_id provided for get_facility_by_unique_id.", ['action' => $action]);
                $response_data = [
                    'success' => false,
                    'message' => 'يرجى إدخال المعرف الفريد للمنشأة.',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                ob_end_flush();
                break;
            }
            try {
                // Ensure all fields are selected here as well
                $selectFields = "ID, license_no, unique_id, facility_name, brand_name, area, sub_area, activity_type, hazard_class,
                                 LicenseIssuing, ltype, sub_no, Building, detailed_activities, facility_status, unit, Sub_UNIT,
                                 site_coordinates, Sector, Sub_Sector, shfhsp, lstart_date, lend_date, user, area_id, phone_number, email, front_image_url, entry_permit_no, created_at, updated_at";
                $stmt = $conn->prepare("
                    SELECT $selectFields
                    FROM establishments
                    WHERE unique_id = ?
                ");
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement for facility search: " . $conn->error);
                }
                $stmt->bind_param("s", $facilityUniqueId);
                $stmt->execute();
                $result = $stmt->get_result();
                $facility = $result->fetch_assoc();
                $stmt->close();
                if ($facility) {
                    foreach ($facility as $key => $value) {
                        if (is_string($value)) {
                            $facility[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                        }
                    }
                    $facility['Sub_Sector'] = (int)($facility['Sub_Sector'] ?? 0);
                    $facility['shfhsp'] = $facility['shfhsp'] ?? '';
                    $facility['last_inspection_date'] = null;
                    $facility['last_inspection_notes'] = null; // Changed from taken_actions_previous
                    $facility['last_evaluation_date'] = null;
                    $stmt_last_insp = $conn->prepare("SELECT inspection_date, notes FROM tbl_inspections WHERE facility_unique_id = ? ORDER BY inspection_date DESC, inspection_id DESC LIMIT 1");
                    if ($stmt_last_insp) {
                        $stmt_last_insp->bind_param("s", $facilityUniqueId);
                        $stmt_last_insp->execute();
                        $result_last_insp = $stmt_last_insp->get_result();
                        if ($row_last_insp = $result_last_insp->fetch_assoc()) {
                            $facility['last_inspection_date'] = $row_last_insp['inspection_date'];
                            $facility['last_inspection_notes'] = $row_last_insp['notes'] ?? 'لا توجد ملاحظات سابقة';
                        }
                        $stmt_last_insp->close();
                    } else {
                        logError("Error preparing last inspection query: " . $conn->error, ['unique_id' => $facilityUniqueId]);
                    }
                    $stmt_last_eval = $conn->prepare("SELECT MAX(assessment_date) AS last_eval_date FROM tbl_evaluation_factors WHERE facility_unique_id = ?");
                    if ($stmt_last_eval) {
                        $stmt_last_eval->bind_param("s", $facilityUniqueId);
                        $stmt_last_eval->execute();
                        $result_last_eval = $stmt_last_eval->get_result();
                        if ($row_last_eval = $result_last_eval->fetch_assoc()) {
                            $facility['last_evaluation_date'] = $row_last_eval['last_eval_date'];
                        }
                        $stmt_last_eval->close();
                    } else {
                        logError("Error preparing last evaluation query: " . $conn->error, ['unique_id' => $facilityUniqueId]);
                    }
                    $response_data = [
                        'success' => true,
                        'data' => $facility,
                        'message' => 'تم جلب بيانات المنشأة بنجاح.',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                } else {
                    $response_data = [
                        'success' => false,
                        'message' => 'لم يتم العثور على منشأة بالمعرف الفريد المحدد.',
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            } catch (Exception $e) {
                logError("Error in get_facility_by_unique_id: " . $e->getMessage(), ['facility_unique_id' => $facilityUniqueId]);
                $response_data = [
                    'success' => false,
                    'message' => 'خطأ أثناء جلب بيانات المنشأة: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
        default:
            logError("Invalid action received.", ['action' => $action]);
            $response_data = [
                'success' => false,
                'message' => 'إجراء غير صالح.',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            ob_end_flush();
            break;
    }
} else {
    logError("Invalid request method.", ['method' => $_SERVER['REQUEST_METHOD']]);
    $response_data = [
        'success' => false,
        'message' => 'طريقة طلب غير صالحة.',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    ob_end_flush();
}
// التقاط أي مخرجات غير متوقعة
$output_buffer_content = ob_get_clean();
if (!empty($output_buffer_content)) {
    logError("Unexpected output captured.", ['output' => $output_buffer_content]);
    $response_data['debug_output'] = $output_buffer_content;
    $response_data['message'] = 'حدث خطأ داخلي في الخادم. تم تضمين معلومات التصحيح في debug_output.';
    echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
}
ob_end_flush();
exit;
?>