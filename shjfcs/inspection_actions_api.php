<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_all':
            $inspectionId = $_GET['inspection_id'] ?? 0;

            if (!$inspectionId) {
                throw new Exception('معرف التفتيش مطلوب');
            }

            $stmt = $conn->prepare("SELECT * FROM tbl_inspection_actions WHERE inspection_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $inspectionId);
            $stmt->execute();
            $result = $stmt->get_result();

            $actions = [];
            while ($row = $result->fetch_assoc()) {
                $actions[] = $row;
            }

            echo json_encode([
                'success' => true,
                'actions' => $actions
            ]);
            break;

        case 'get':
            $actionId = $_GET['action_entry_id'] ?? 0;

            if (!$actionId) {
                throw new Exception('معرف الإجراء مطلوب');
            }

            $stmt = $conn->prepare("SELECT * FROM tbl_inspection_actions WHERE action_entry_id = ?");
            $stmt->bind_param("i", $actionId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('الإجراء غير موجود');
            }

            $actionData = $result->fetch_assoc();

            echo json_encode([
                'success' => true,
                'action' => $actionData
            ]);
            break;

        case 'create':
            $inspectionId = $_POST['inspection_id'] ?? 0;
            $actionName = $_POST['action_name'] ?? '';
            $actionNumber = $_POST['action_number'] ?? '';
            $actionDuration = $_POST['action_duration_days'] ?? 0;
            $actionStatus = $_POST['action_status'] ?? 'active';
            $previousActionId = $_POST['previous_action_entry_id'] ?? null;

            if (!$inspectionId || !$actionName) {
                throw new Exception('بيانات الإجراء غير مكتملة');
            }

            $stmt = $conn->prepare("INSERT INTO tbl_inspection_actions 
                (inspection_id, action_name, action_number, action_duration_days, action_status, previous_action_entry_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("issisi", $inspectionId, $actionName, $actionNumber, $actionDuration, $actionStatus, $previousActionId);

            if ($stmt->execute()) {
                $newId = $conn->insert_id;

                echo json_encode([
                    'success' => true,
                    'action_entry_id' => $newId,
                    'message' => 'تم إضافة الإجراء بنجاح'
                ]);
            } else {
                throw new Exception('فشل في إضافة الإجراء: ' . $stmt->error);
            }
            break;

        case 'update':
            $actionId = $_POST['action_entry_id'] ?? 0;
            $actionName = $_POST['action_name'] ?? '';
            $actionNumber = $_POST['action_number'] ?? '';
            $actionDuration = $_POST['action_duration_days'] ?? 0;
            $actionStatus = $_POST['action_status'] ?? 'active';
            $previousActionId = $_POST['previous_action_entry_id'] ?? null;

            if (!$actionId || !$actionName) {
                throw new Exception('بيانات الإجراء غير مكتملة');
            }

            $stmt = $conn->prepare("UPDATE tbl_inspection_actions 
                SET action_name = ?, action_number = ?, action_duration_days = ?, action_status = ?, previous_action_entry_id = ?, updated_at = NOW() 
                WHERE action_entry_id = ?");
            $stmt->bind_param("ssisis", $actionName, $actionNumber, $actionDuration, $actionStatus, $previousActionId, $actionId);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'تم تحديث الإجراء بنجاح'
                ]);
            } else {
                throw new Exception('فشل في تحديث الإجراء: ' . $stmt->error);
            }
            break;

        case 'delete':
            $actionId = $_POST['action_entry_id'] ?? 0;

            if (!$actionId) {
                throw new Exception('معرف الإجراء مطلوب');
            }

            $stmt = $conn->prepare("DELETE FROM tbl_inspection_actions WHERE action_entry_id = ?");
            $stmt->bind_param("i", $actionId);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'تم حذف الإجراء بنجاح'
                ]);
            } else {
                throw new Exception('فشل في حذف الإجراء: ' . $stmt->error);
            }
            break;

        default:
            throw new Exception('إجراء غير معروف');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}