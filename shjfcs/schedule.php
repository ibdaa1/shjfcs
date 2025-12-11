<?php
// schedule.php

// دالة لتسجيل الأخطاء (لضمان وجودها إذا لم تكن موجودة من ملفات أخرى)
if (!function_exists('logError')) {
    function logError($message, $context = []) {
        error_log(date('Y-m-d H:i:s') . " - Application Error: " . $message . " " . json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, 'error.log');
    }
}

/**
 * Schedules the next inspection in tbl_inspection_schedule.
 *
 * @param int $inspectionId The ID of the current inspection.
 * @param string $facilityUniqueId The unique ID of the facility.
 * @param string $nextInspectionDate The calculated next inspection date.
 * @param mysqli $conn Database connection object.
 * @return array Returns an array with success status and message.
 */
function scheduleNextInspection($inspectionId, $facilityUniqueId, $nextInspectionDate, $conn) {
    logError("DEBUG_SCHEDULE: Starting scheduleNextInspection for inspection_id: " . $inspectionId);
    try {
        // 1. جلب تفاصيل التفتيش الحالي (خاصة البطاقة اللونية وتاريخ التفتيش)
        $colorCard = 'White'; // قيمة افتراضية
        $currentInspectionDate = null;
        $stmt_insp_details = $conn->prepare("SELECT color_card, inspection_date FROM tbl_inspections WHERE inspection_id = ?");
        if (!$stmt_insp_details) {
            throw new Exception("Failed to prepare statement for inspection details: " . $conn->error);
        }
        $stmt_insp_details->bind_param("i", $inspectionId);
        $stmt_insp_details->execute();
        $result_insp_details = $stmt_insp_details->get_result();
        if ($row_insp_details = $result_insp_details->fetch_assoc()) {
            $colorCard = $row_insp_details['color_card'];
            $currentInspectionDate = $row_insp_details['inspection_date'];
            logError("DEBUG_SCHEDULE: Fetched color_card: '" . $colorCard . "' and current_inspection_date: '" . $currentInspectionDate . "'", ['inspection_id' => $inspectionId]);
        } else {
            logError("DEBUG_SCHEDULE: Could not fetch inspection details for ID: " . $inspectionId . ". Defaulting color_card to White.", ['inspection_id' => $inspectionId]);
        }
        $stmt_insp_details->close();

        // 2. تحديد حالة الجدولة بناءً على البطاقة اللونية
        $inspectionStatus = 'Scheduled'; // الحالة الافتراضية
        $message = '';
        $skipExistingScheduleCheck = false; // افتراضياً، لا تتجاوز التحقق

        // إذا كانت البطاقة حمراء أو صفراء، فستكون الحالة "Emergency" ويتم تجاوز التحقق من الجداول السابقة
        if ($colorCard === 'Red' || $colorCard === 'Yellow') {
            $inspectionStatus = 'Emergency';
            $message = 'تم جدولة زيارة طارئة (Emergency) لهذه المنشأة (بطاقة ' . ($colorCard === 'Red' ? 'حمراء' : 'صفراء') . ').';
            $skipExistingScheduleCheck = true;
            logError("DEBUG_SCHEDULE: Determined status: Emergency (Color Card: " . $colorCard . "). Skip existing schedule check: TRUE.", [
                'inspection_id' => $inspectionId,
                'facility_unique_id' => $facilityUniqueId,
                'next_inspection_date' => $nextInspectionDate,
                'color_card' => $colorCard
            ]);
        } else {
            // للبطاقات الأخرى (مثل الأخضر أو الأبيض)
            $inspectionStatus = 'Scheduled'; // ستبقى مجدولة
            logError("DEBUG_SCHEDULE: Determined status: Scheduled (Color Card: " . $colorCard . "). Skip existing schedule check: FALSE.", ['inspection_id' => $inspectionId]);
        }


        // قم بإجراء التحقق من وجود زيارات مجدولة/معلقة سابقة فقط إذا لم يكن skipExistingScheduleCheck صحيحاً
        if (!$skipExistingScheduleCheck) {
            logError("DEBUG_SCHEDULE: Not a Red or Yellow card. Performing existing schedule check for facility_unique_id: " . $facilityUniqueId, ['inspection_id' => $inspectionId]);
            $stmt_check_existing = $conn->prepare("
                SELECT COUNT(*) AS schedule_count
                FROM tbl_inspection_schedule
                WHERE facility_unique_id = ? AND inspection_status IN ('Scheduled', 'Pending')
            ");
            if (!$stmt_check_existing) {
                throw new Exception("Failed to prepare statement for checking existing schedules: " . $conn->error);
            }
            $stmt_check_existing->bind_param("s", $facilityUniqueId);
            $stmt_check_existing->execute();
            $result_check_existing = $stmt_check_existing->get_result();
            $row_check_existing = $result_check_existing->fetch_assoc();
            $scheduleCount = $row_check_existing['schedule_count'] ?? 0;
            $stmt_check_existing->close();
            logError("DEBUG_SCHEDULE: Existing scheduled/pending count for facility " . $facilityUniqueId . ": " . $scheduleCount, ['inspection_id' => $inspectionId]);


            if ($scheduleCount > 0) {
                // إذا كانت المنشأة لديها بالفعل زيارة مجدولة أو معلقة، لا تقم بالجدولة مرة أخرى.
                logError("DEBUG_SCHEDULE: Facility " . $facilityUniqueId . " already has a scheduled/pending visit. Returning early (no new schedule/update).", ['inspection_id' => $inspectionId]);
                return [
                    'success' => false,
                    'message' => 'يوجد زيارة مجدولة أو معلقة بخطة التفتيش لهذه المنشأة. لم يتم إنشاء جدول جديد.'
                ];
            }
            $message = 'تم إدراج موعد التفتيش القادم في جدول الجدولة بنجاح.'; // رسالة افتراضية للحالات غير الحمراء/الصفراء
        }


        // 3. تحديث أو إدراج سجل جديد في tbl_inspection_schedule
        // التحقق أولاً إذا كان هناك سجل موجود لنفس inspection_id لتجنب التكرار
        logError("DEBUG_SCHEDULE: Checking for existing schedule entry linked to current inspection_id: " . $inspectionId, ['inspection_id' => $inspectionId]);
        $stmt_check_schedule_entry = $conn->prepare("SELECT schedule_id, inspection_status FROM tbl_inspection_schedule WHERE inspection_id = ?"); // جلب inspection_status الحالي
        $existingScheduleId = null;
        $oldInspectionStatus = null; // لتخزين الحالة القديمة
        if ($stmt_check_schedule_entry) {
            $stmt_check_schedule_entry->bind_param("i", $inspectionId);
            $stmt_check_schedule_entry->execute();
            $result_check_schedule_entry = $stmt_check_schedule_entry->get_result();
            if ($row_schedule_entry = $result_check_schedule_entry->fetch_assoc()) {
                $existingScheduleId = $row_schedule_entry['schedule_id'];
                $oldInspectionStatus = $row_schedule_entry['inspection_status'];
                logError("DEBUG_SCHEDULE: Found existing schedule_id: " . $existingScheduleId . " for inspection_id: " . $inspectionId . ", Old Status: " . $oldInspectionStatus);
            } else {
                logError("DEBUG_SCHEDULE: No existing schedule_id found for inspection_id: " . $inspectionId . ". This is a new schedule.", ['inspection_id' => $inspectionId]);
            }
            $stmt_check_schedule_entry->close();
        }

        $inspectorUserId = null; // كما هو مطلوب: explicitly set to NULL
        $updatedByUserId = null; // كما هو مطلوب: explicitly set to NULL
        $execute_success = false;

        // *** منطق التعديل/الإضافة والحذف بناءً على الحالة الجديدة والقديمة ***
        if ($existingScheduleId) {
            // حالة: تحديث سجل موجود
            logError("DEBUG_SCHEDULE: An existing schedule record was found. Deciding whether to UPDATE or DELETE.", ['schedule_id' => $existingScheduleId, 'old_status' => $oldInspectionStatus, 'new_status_determined' => $inspectionStatus]);

            // Scenario 1: Transition from Urgent (Emergency) to a non-urgent status
            // If the old status was 'Emergency' AND the new status is NOT 'Emergency', then delete the old record.
            // This happens when a high-risk scenario is resolved, and the facility no longer needs urgent follow-up.
            $shouldDeleteOldRecord = false;
            // التحقق إذا كانت الحالة القديمة طارئة (أي Emergency) والحالة الجديدة ليست طارئة
            if ($oldInspectionStatus === 'Emergency' && $inspectionStatus !== 'Emergency') {
                 $shouldDeleteOldRecord = true;
                 logError("DEBUG_SCHEDULE: Old record was Emergency, new status is not. Deleting old record.", ['schedule_id' => $existingScheduleId, 'old_status' => $oldInspectionStatus, 'new_status' => $inspectionStatus]);
            }

            if ($shouldDeleteOldRecord) {
                logError("DEBUG_SCHEDULE: Attempting to DELETE old schedule record for inspection_id: " . $inspectionId . " (ID: " . $existingScheduleId . ") due to resolved urgency.", ['inspection_id' => $inspectionId]);
                $stmt_delete = $conn->prepare("DELETE FROM tbl_inspection_schedule WHERE schedule_id = ?");
                if (!$stmt_delete) {
                    throw new Exception("Failed to prepare delete schedule statement: " . $conn->error);
                }
                $stmt_delete->bind_param("i", $existingScheduleId);
                $execute_success = $stmt_delete->execute();
                $stmt_delete->close();
                if (!$execute_success) {
                    $message = 'فشل حذف موعد التفتيش السابق: ' . $conn->error;
                    logError("DEBUG_SCHEDULE: DELETE failed: " . $conn->error, ['schedule_id' => $existingScheduleId]);
                } else {
                    $message = 'تم حذف موعد التفتيش السابق بنجاح (تغيرت حالة البطاقة).';
                    logError("DEBUG_SCHEDULE: DELETE successful for schedule_id: " . $existingScheduleId, ['inspection_id' => $inspectionId]);
                    // بعد الحذف، إذا كانت الحالة الجديدة تتطلب جدولة (أي Emergency)، سنقوم بإدراجها كسجل جديد
                    if ($inspectionStatus === 'Emergency') {
                         logError("DEBUG_SCHEDULE: Old record deleted, now inserting new Emergency record as per new card status.", ['inspection_id' => $inspectionId]);
                         // إعادة تنفيذ جزء الإدراج
                         $stmt_insert = $conn->prepare("
                             INSERT INTO tbl_inspection_schedule
                                 (inspection_id, facility_unique_id, next_visit_date, inspection_status, inspector_user_id, created_at, updated_at, updated_by_user_id)
                             VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
                         ");
                         if (!$stmt_insert) {
                             throw new Exception("Failed to prepare insert schedule statement after delete: " . $conn->error);
                         }
                         $stmt_insert->bind_param("isssss", $inspectionId, $facilityUniqueId, $nextInspectionDate, $inspectionStatus, $inspectorUserId, $updatedByUserId);
                         $execute_success = $stmt_insert->execute();
                         $stmt_insert->close();
                         if (!$execute_success) {
                             $message = 'فشل إدراج موعد التفتيش الجديد بعد الحذف: ' . $conn->error;
                             logError("DEBUG_SCHEDULE: INSERT after DELETE failed: " . $conn->error, ['inspection_id' => $inspectionId]);
                         } else {
                             $message = 'تم حذف السجل السابق وإدراج سجل طارئ جديد بنجاح.';
                             logError("DEBUG_SCHEDULE: INSERT after DELETE successful for inspection_id: " . $inspectionId . ", new schedule_id: " . $conn->insert_id, ['inspection_id' => $inspectionId]);
                         }
                    } else {
                         // إذا تم الحذف ولم تكن الحالة الجديدة طارئة، فإننا ننتهي هنا
                         return ['success' => true, 'message' => $message];
                    }
                }
            } else {
                // إذا لم يتم الحذف، فقم بتحديث السجل الموجود
                logError("DEBUG_SCHEDULE: No deletion needed. Attempting to UPDATE schedule_id: " . $existingScheduleId . " with status: " . $inspectionStatus . " and date: " . $nextInspectionDate, ['inspection_id' => $inspectionId]);
                $stmt_update = $conn->prepare("
                    UPDATE tbl_inspection_schedule
                    SET facility_unique_id = ?, next_visit_date = ?, inspection_status = ?, inspector_user_id = ?, updated_at = NOW(), updated_by_user_id = ?
                    WHERE schedule_id = ?
                ");
                if (!$stmt_update) {
                    throw new Exception("Failed to prepare update schedule statement: " . $conn->error);
                }
                $stmt_update->bind_param("sssisi", $facilityUniqueId, $nextInspectionDate, $inspectionStatus, $inspectorUserId, $updatedByUserId, $existingScheduleId);
                $execute_success = $stmt_update->execute();
                $stmt_update->close();
                if (!$execute_success) {
                    $message = 'فشل تحديث موعد التفتيش: ' . $conn->error;
                    logError("DEBUG_SCHEDULE: UPDATE failed: " . $conn->error, ['inspection_id' => $inspectionId]);
                } else {
                    logError("DEBUG_SCHEDULE: UPDATE successful for schedule_id: " . $existingScheduleId, ['inspection_id' => $inspectionId]);
                }
            }
        } else {
            // حالة: إدراج سجل جديد (لم يتم العثور على سجل سابق مرتبط بنفس inspection_id)
            logError("DEBUG_SCHEDULE: No existing schedule record found for inspection_id. Attempting to INSERT new schedule record with status: " . $inspectionStatus . " and date: " . $nextInspectionDate, ['inspection_id' => $inspectionId]);
            $stmt_insert = $conn->prepare("
                INSERT INTO tbl_inspection_schedule
                    (inspection_id, facility_unique_id, next_visit_date, inspection_status, inspector_user_id, created_at, updated_at, updated_by_user_id)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
            ");
            if (!$stmt_insert) {
                throw new Exception("Failed to prepare insert schedule statement: " . $conn->error);
            }
            $stmt_insert->bind_param("isssss", $inspectionId, $facilityUniqueId, $nextInspectionDate, $inspectionStatus, $inspectorUserId, $updatedByUserId);
            $execute_success = $stmt_insert->execute();
            $stmt_insert->close();
            if (!$execute_success) {
                $message = 'فشل إدراج موعد التفتيش: ' . $conn->error;
                logError("DEBUG_SCHEDULE: INSERT failed: " . $conn->error, ['inspection_id' => $inspectionId]);
            } else {
                logError("DEBUG_SCHEDULE: INSERT successful for inspection_id: " . $inspectionId . ", new schedule_id: " . $conn->insert_id, ['inspection_id' => $inspectionId]);
            }
        }

        if ($execute_success) {
            return [
                'success' => true,
                'message' => $message
            ];
        } else {
            throw new Exception($message);
        }

    } catch (Exception $e) {
        // Ensure the statement is closed in case of an error
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
        logError("DEBUG_SCHEDULE: Critical Error in scheduleNextInspection: " . $e->getMessage(), [
            'inspection_id' => $inspectionId,
            'facility_unique_id' => $facilityUniqueId
        ]);
        return [
            'success' => false,
            'message' => 'خطأ أثناء جدولة التفتيش القادم: ' . $e->getMessage()
        ];
    }
}