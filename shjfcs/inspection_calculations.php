<?php
// inspection_calculations.php

// دالة لتسجيل الأخطاء (لضمان وجودها إذا لم تكن موجودة من ملفات أخرى)
if (!function_exists('logError')) {
    function logError($message, $context = []) {
        error_log(date('Y-m-d H:i:s') . " - Application Error: " . $message . " " . json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, 'error.log');
    }
}
/**
 * Calculates inspection results including scores, grades, and next inspection date.
 *
 * @param int $inspectionId The ID of the inspection.
 * @param mysqli $conn Database connection object.
 * @param int|null $updatedByUserId Optional user ID who updated the inspection.
 * @return array|false An array of calculated results, or false on failure.
 */
function calculateInspectionResults($inspectionId, $conn, $updatedByUserId = null) {
    try {
        // 1. Get establishment's hazard class, Sub_Sector, facility_unique_id, inspection_date, and **inspection_type**
        // تم تحديث هذا الاستعلام لجلب inspection_type
        $hazardClass = null;
        $establishmentSubSector = null;
        $currentInspectionDate = null;
        $facilityUniqueId = null;
        $inspectionType = null; // متغير جديد لنوع التفتيش

        $stmt = $conn->prepare("SELECT e.hazard_class, e.Sub_Sector, e.unique_id, i.inspection_date, i.inspection_type FROM tbl_inspections i JOIN establishments e ON i.facility_unique_id = e.unique_id WHERE i.inspection_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for establishment and inspection details: " . $conn->error);
        }
        $stmt->bind_param("i", $inspectionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) {
            $hazardClass = $row['hazard_class'];
            $establishmentSubSector = $row['Sub_Sector'];
            $currentInspectionDate = $row['inspection_date'];
            $facilityUniqueId = $row['unique_id'];
            $inspectionType = $row['inspection_type']; // جلب نوع التفتيش
        } else {
            throw new Exception("Inspection or establishment details not found for inspection ID: " . $inspectionId);
        }
        $stmt->close();

        // حساب total_deducted_points و total_violation_value دائمًا لأي نوع تفتيش
        $totalDeductedPoints = 0.00;
        $totalViolationValue = 0.00;
        $stmt = $conn->prepare("SELECT SUM(deducted_points) AS total_deducted_points, SUM(violation_value) AS total_violation_value FROM tbl_inspection_items WHERE inspection_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for total deducted points: " . $conn->error);
        }
        $stmt->bind_param("i", $inspectionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $totalDeductedPoints = $row['total_deducted_points'] ?? 0.00;
        $totalViolationValue = $row['total_violation_value'] ?? 0.00;
        $stmt->close();

        logError("DEBUG_CALCULATIONS: Total deducted points and violation value calculated for all inspection types.", [
            'inspection_id' => $inspectionId,
            'total_deducted_points' => $totalDeductedPoints,
            'total_violation_value' => $totalViolationValue
        ]);

        // تهيئة القيم إلى NULL/فارغة. سيتم ملؤها فقط إذا كان inspectionType هو 'دوري'.
        $finalInspectionScore = null;
        $percentageScore = null;
        $letterGrade = null;
        $colorCard = null;
        $criticalViolations = null;
        $majorViolations = null;
        $generalViolations = null;
        $administrativeViolations = null;
        $nextInspectionDate = null;
        $scheduleResult = ['success' => false, 'message' => 'جدولة الزيارة تخطيت لأن نوع التفتيش ليس دوريًا.'];


        // الشرط: إجراء الحسابات الكاملة فقط إذا كان نوع التفتيش هو 'دوري'
        if ($inspectionType === 'دوري') {
            logError("DEBUG_CALCULATIONS: Inspection type is 'دوري'. Performing full calculations.", ['inspection_id' => $inspectionId, 'inspection_type' => $inspectionType]);

            $baseScore = 1000.00;
            $finalInspectionScore = max(0, $baseScore - $totalDeductedPoints);
            $percentageScore = ($baseScore > 0) ? ($finalInspectionScore / $baseScore) * 100 : 0;

            // Get violation counts by category
            $stmt = $conn->prepare("
    SELECT tic.code_category, COUNT(tii.code_id) AS violation_count
    FROM tbl_inspection_items tii
    JOIN tbl_inspection_code tic ON tii.code_id = tic.code_id
    WHERE tii.inspection_id = ?
      AND tii.is_violation = 1
      AND tii.action_taken = 'مخالفة'
    GROUP BY tic.code_category
");

            if (!$stmt) {
                throw new Exception("Failed to prepare statement for violation counts: " . $conn->error);
            }
            $stmt->bind_param("i", $inspectionId);
            $stmt->execute();
            $categoryCounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $criticalViolations = 0;
            $majorViolations = 0;
            $generalViolations = 0;
            $administrativeViolations = 0;

            foreach ($categoryCounts as $cat) {
                if ($cat['code_category'] === 'Critical') {
                    $criticalViolations = $cat['violation_count'];
                } elseif ($cat['code_category'] === 'Major') {
                    $majorViolations = $cat['violation_count'];
                } elseif ($cat['code_category'] === 'General') {
                    $generalViolations = $cat['violation_count'];
                } elseif ($cat['code_category'] === 'Administrative') {
                    $administrativeViolations = $cat['violation_count'];
                }
            }

            // تحديد الدرجة بالحرف
            if ($percentageScore >= 95) {
                $letterGrade = 'A+';
            } elseif ($percentageScore >= 90) {
                $letterGrade = 'A';
            } elseif ($percentageScore >= 75) {
                $letterGrade = 'B';
            } elseif ($percentageScore >= 60) {
                $letterGrade = 'C';
            } elseif ($percentageScore >= 45) {
                $letterGrade = 'D';
            } else {
                $letterGrade = 'E';
            }

            // تحديد البطاقة الملونة بناءً على عدد المخالفات
            // الافتراضي هو Green إذا لم يتم استيفاء شرط مخالفة معين
            $colorCard = 'Green';
            if ($criticalViolations > 4 || $majorViolations >= 8 || $generalViolations >= 12) {
                $colorCard = 'Red';
            } elseif ($majorViolations == 7 || $generalViolations >= 11) {
                $colorCard = 'Yellow';
            }
            // ملاحظة: إذا لم ينطبق أي مما سبق، فستظل البطاقة خضراء. لا يوجد افتراضي صريح 'White' هنا.

            // سجل الدرجة والبطاقة
            logError("DEBUG_CALCULATIONS: Grade and card calculated", [
                'inspection_id' => $inspectionId,
                'percentage_score' => $percentageScore,
                'letter_grade' => $letterGrade,
                'color_card' => $colorCard
            ]);

            // حساب تاريخ التفتيش التالي
            $baseDate = null;
            if ($currentInspectionDate) {
                try {
                    $baseDate = new DateTime($currentInspectionDate);
                } catch (Exception $e) {
                    logError("Invalid inspection date: " . $currentInspectionDate . " - " . $e->getMessage(), ['inspection_id' => $inspectionId]);
                    $baseDate = new DateTime();
                }
            } else {
                logError("Inspection date is null, using current date", ['inspection_id' => $inspectionId]);
                $baseDate = new DateTime();
            }

            // Get WorkMod, StartDate, and MaxVisitsPerDay from tbl_Sectors
            $workMod = null;
            $sectorStartDate = null;
            $maxVisitsPerDay = 5;
            if ($establishmentSubSector) {
                $stmt_sector = $conn->prepare("SELECT WorkMod, StartDate, MaxVisitsPerDay FROM tbl_Sectors WHERE SectorID = ?");
                if (!$stmt_sector) {
                    logError("Failed to prepare statement for WorkMod, StartDate, MaxVisitsPerDay: " . $conn->error, ['Sub_Sector' => $establishmentSubSector]);
                } else {
                    $stmt_sector->bind_param("i", $establishmentSubSector);
                    $stmt_sector->execute();
                    $result_sector = $stmt_sector->get_result();
                    $sectorRow = $result_sector->fetch_assoc();
                    if ($sectorRow) {
                        $workMod = $sectorRow['WorkMod'];
                        $sectorStartDate = $sectorRow['StartDate'];
                        $maxVisitsPerDay = $sectorRow['MaxVisitsPerDay'] ?? $maxVisitsPerDay;
                    }
                    $stmt_sector->close();
                }
            }

            // تعديل لدعم دورة العمل الكاملة مع الأنواع المحددة فقط: 4,3 / 4,2 / 6,4 / 5,2
            $allowedWorkMods = ['4,3', '4,2', '6,4', '5,2'];
            $workDaysPerCycle = 5; // قيمة افتراضية إذا لم يكن مدعومًا
            $restDaysPerCycle = 2; // قيمة افتراضية إذا لم يكن مدعومًا
            if ($workMod && in_array($workMod, $allowedWorkMods)) {
                $workModParts = explode(',', $workMod);
                if (count($workModParts) == 2) {
                    $workDaysPerCycle = (int)$workModParts[0];
                    $restDaysPerCycle = (int)$workModParts[1];
                }
                logError("DEBUG_CALCULATIONS: Supported WorkMod applied", [
                    'work_mod' => $workMod,
                    'work_days' => $workDaysPerCycle,
                    'rest_days' => $restDaysPerCycle
                ]);
            } else {
                logError("DEBUG_CALCULATIONS: Unsupported or missing WorkMod, using default (5,2)", [
                    'work_mod' => $workMod ?? 'null',
                    'sub_sector' => $establishmentSubSector
                ]);
            }
            $cycleLength = $workDaysPerCycle + $restDaysPerCycle;

            // تحديد frequencyDays بناءً على البطاقة الملونة وفئة المخاطر
            $frequencyDays = null;
            if ($colorCard === 'Red') {
                $frequencyDays = 3; // متابعة خلال 3 أيام (تقويمي)
            } elseif ($colorCard === 'Yellow') {
                $frequencyDays = 10; // متابعة خلال 10 أيام عمل
            } else { // Green
                switch ($hazardClass) {
                    case 'Very-high':
                        $frequencyDays = 30;
                        break;
                    case 'High':
                        switch ($letterGrade) {
                            case 'A+': $frequencyDays = 243; break;
                            case 'A':  $frequencyDays = 122; break;
                            case 'B':  $frequencyDays = 91; break;
                            case 'C':  $frequencyDays = 30; break;
                            case 'D':  $frequencyDays = 20; break;
                            case 'E':  $frequencyDays = 20; break;
                            default:   $frequencyDays = 60;
                        }
                        break;
                    case 'Medium':
                        switch ($letterGrade) {
                            case 'A+': $frequencyDays = 365; break;
                            case 'A':  $frequencyDays = 183; break;
                            case 'B':  $frequencyDays = 122; break;
                            case 'C':  $frequencyDays = 46; break;
                            case 'D':  $frequencyDays = 30; break;
                            case 'E':  $frequencyDays = 30; break;
                            default:   $frequencyDays = 120;
                        }
                        break;
                    case 'Low':
                        switch ($letterGrade) {
                            case 'A+': $frequencyDays = 365; break;
                            case 'A':  $frequencyDays = 365; break;
                            case 'B':  $frequencyDays = 365; break;
                            case 'C':  $frequencyDays = 122; break;
                            case 'D':  $frequencyDays = 61; break;
                            case 'E':  $frequencyDays = 61; break;
                            default:   $frequencyDays = 180;
                        }
                        break;
                    case 'Very-Low':
                        $frequencyDays = 360;
                        break;
                    default:
                        $frequencyDays = 90;
                        break;
                }
            }

            // سجل أيام التكرار
            logError("DEBUG_CALCULATIONS: Frequency days calculated", [
                'inspection_id' => $inspectionId,
                'hazard_class' => $hazardClass,
                'frequency_days' => $frequencyDays
            ]);

            if ($frequencyDays !== null) {
                // لـ Yellow فقط: استخدم الحلقة للبحث عن أيام عمل متاحة
                // لـ Red و Green: أضف أيام تقويمية مباشرة
                if ($colorCard === 'Yellow') {
                    $currentDate = clone $baseDate;
                    $effectiveWorkDaysFound = 0;
                    $safetyBreak = 0; // لمنع الحلقات اللانهائية

                    try {
                        $sectorStartDateTime = new DateTime($sectorStartDate);
                    } catch (Exception $e) {
                        logError("Invalid Sector StartDate: " . $sectorStartDate . " - " . $e->getMessage(), ['Sub_Sector' => $establishmentSubSector]);
                        $sectorStartDateTime = new DateTime('2000-01-01'); // قيمة احتياطية
                    }

                    while ($effectiveWorkDaysFound < $frequencyDays && $safetyBreak < 2000) { // كسر أمان
                        $currentDate->modify('+1 day');
                        $safetyBreak++;

                        // حساب اليوم في الدورة بالنسبة لتاريخ بدء القطاع
                        $intervalFromSectorStart = $sectorStartDateTime->diff($currentDate);
                        $totalDaysFromSectorStart = $intervalFromSectorStart->days;
                        if ($currentDate < $sectorStartDateTime) {
                            $totalDaysFromSectorStart = -($totalDaysFromSectorStart);
                        }
                        $dayInCycle = ($totalDaysFromSectorStart % $cycleLength + $cycleLength) % $cycleLength; // التأكد من نتيجة موجبة

                        $isWorkDayInCycle = ($dayInCycle < $workDaysPerCycle);

                        if ($isWorkDayInCycle) {
                            $visitsOnThisDayForSector = 0;
                            $stmt_count_visits = $conn->prepare("
                                SELECT COUNT(ti.inspection_id) AS visit_count
                                FROM tbl_inspections ti
                                JOIN establishments e ON ti.facility_unique_id = e.unique_id
                                WHERE ti.inspection_date = ? AND e.Sub_Sector = ?
                            ");
                            if ($stmt_count_visits) {
                                $dateFormatted = $currentDate->format('Y-m-d');
                                $stmt_count_visits->bind_param("si", $dateFormatted, $establishmentSubSector);
                                $stmt_count_visits->execute();
                                $result_count = $stmt_count_visits->get_result();
                                $row_count = $result_count->fetch_assoc();
                                $visitsOnThisDayForSector = $row_count['visit_count'] ?? 0;
                                $stmt_count_visits->close();
                            } else {
                                logError("Failed to prepare statement for counting visits: " . $conn->error, ['inspection_id' => $inspectionId]);
                            }

                            if ($visitsOnThisDayForSector < $maxVisitsPerDay) {
                                $effectiveWorkDaysFound++;
                            }
                        }
                    }

                    if ($effectiveWorkDaysFound >= $frequencyDays) {
                        $nextInspectionDate = $currentDate->format('Y-m-d');
                    } else {
                        logError("Could not find enough effective work days for inspection ID: " . $inspectionId . " after " . $safetyBreak . " attempts. Defaulting to direct addition.", ['inspection_id' => $inspectionId]);
                        // Fallback to simple addition if loop fails
                        $baseDate->modify("+$frequencyDays days");
                        $nextInspectionDate = $baseDate->format('Y-m-d');
                    }
                } else {
                    // لـ Red و Green: إضافة أيام تقويمية مباشرة
                    $baseDate->modify("+$frequencyDays days");
                    $nextInspectionDate = $baseDate->format('Y-m-d');
                    logError("DEBUG_CALCULATIONS: Direct calendar days addition for non-Yellow card", [
                        'inspection_id' => $inspectionId,
                        'color_card' => $colorCard,
                        'frequency_days' => $frequencyDays,
                        'next_date' => $nextInspectionDate
                    ]);
                }
            } else {
                // إذا كان frequencyDays قيمته null (لا يجب أن يحدث هذا مع المنطق الحالي لنوع 'دوري')
                $nextInspectionDate = null; // لا يوجد تاريخ لاحق محدد
                logError("DEBUG_CALCULATIONS: frequencyDays is null for 'دوري' type. Check logic.", ['inspection_id' => $inspectionId]);
            }

            // جدولة التفتيش التالي (فقط إذا كان 'دوري' وكانت الدالة موجودة)
            if (function_exists('scheduleNextInspection')) {
                logError("DEBUG_CALCULATIONS: Calling scheduleNextInspection for inspection_id: " . $inspectionId . " with date: " . ($nextInspectionDate ?? 'NULL'), ['inspection_id' => $inspectionId]);
                $scheduleResult = scheduleNextInspection($inspectionId, $facilityUniqueId, $nextInspectionDate, $conn);
            } else {
                logError("DEBUG_CALCULATIONS: scheduleNextInspection function not found. Skipping scheduling for 'دوري' type.", ['inspection_id' => $inspectionId]);
                $scheduleResult = ['success' => false, 'message' => 'دالة الجدولة غير متوفرة.'];
            }

        } else {
            // إذا كان نوع التفتيش ليس 'دوري'
            logError("DEBUG_CALCULATIONS: Inspection type is NOT 'دوري' (" . $inspectionType . "). Skipping points, cards, next inspection date calculation and scheduling.", ['inspection_id' => $inspectionId, 'inspection_type' => $inspectionType]);

            // تعيين جميع النتائج ذات الصلة إلى NULL لتفتيشات غير 'دوري' (باستثناء total_deducted_points و total_violation_value التي تم حسابها بالفعل)
            $finalInspectionScore = null;
            $percentageScore = null;
            $letterGrade = null;
            $colorCard = null;
            $criticalViolations = null;
            $majorViolations = null;
            $generalViolations = null;
            $administrativeViolations = null;
            $nextInspectionDate = null; // صراحةً NULL

            $scheduleResult = ['success' => false, 'message' => 'جدولة الزيارة تخطيت لأن نوع التفتيش ليس دوريًا.']; // رسالة مخصصة لهذه الحالة
        }


        // تحديث سجل التفتيش في tbl_inspections (دائماً قم بالتحديث بالقيم النهائية المحددة)
        // تم إزالة critical_violations, major_violations, general_violations, administrative_violations من استعلام UPDATE
        $updateStmtQuery = "UPDATE tbl_inspections SET total_deducted_points = ?, final_inspection_score = ?, percentage_score = ?, letter_grade = ?, color_card = ?, next_inspection_date = ?, total_violation_value = ?";
        $updateTypes = "ddssssd"; // تعديل أنواع الربط

        $updateParams = [
            &$totalDeductedPoints,
            &$finalInspectionScore,
            &$percentageScore,
            &$letterGrade,
            &$colorCard,
            &$nextInspectionDate,
            &$totalViolationValue
        ];

        if ($updatedByUserId !== null) {
            $updateStmtQuery .= ", updated_by_user_id = ?";
            $updateTypes .= "i";
            $updateParams[] = &$updatedByUserId;
        }

        $updateStmtQuery .= " WHERE inspection_id = ?";
        $updateTypes .= "i";
        $updateParams[] = &$inspectionId;

        $stmt = $conn->prepare($updateStmtQuery);
        if (!$stmt) {
            throw new Exception("Error preparing update inspection results statement: " . $conn->error);
        }

        // استخدام call_user_func_array لربط المعاملات ديناميكيًا
        call_user_func_array([$stmt, 'bind_param'], array_merge([$updateTypes], $updateParams));

        if (!$stmt->execute()) {
            throw new Exception("Error executing update inspection results statement: " . $stmt->error);
        }
        $stmt->close();

        // إرجاع جميع النتائج المحسوبة، حتى لو لم يتم تخزين بعضها في قاعدة البيانات
        $result = [
            'total_deducted_points'    => $totalDeductedPoints,
            'final_inspection_score'   => $finalInspectionScore,
            'percentage_score'         => $percentageScore,
            'letter_grade'             => $letterGrade,
            'color_card'               => $colorCard,
            'next_inspection_date'     => $nextInspectionDate,
            'critical_violations'      => $criticalViolations, // هذه ستكون NULL إذا لم يكن 'دوري'
            'major_violations'         => $majorViolations,
            'general_violations'       => $generalViolations,
            'administrative_violations'=> $administrativeViolations,
            'total_violation_value'    => $totalViolationValue,
            'schedule_result'          => $scheduleResult // تضمين نتيجة الجدولة
        ];

        return $result;
    } catch (Exception $e) {
        logError("Critical Error in calculateInspectionResults: " . $e->getMessage(), ['inspection_id' => $inspectionId]);
        return false;
    }
}