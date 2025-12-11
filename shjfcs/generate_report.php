<?php
ob_start(); // بدء التخزين المؤقت للإخراج
session_start();
require_once 'auth.php';
require_once 'db.php';

// منع التخزين المؤقت
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// إيقاف عرض الأخطاء في الإنتاج
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 403 Forbidden');
    die('غير مصرح بالوصول. يرجى تسجيل الدخول.');
}

// فحص اتصال قاعدة البيانات
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

$inspectionId = filter_input(INPUT_GET, 'inspection_id', FILTER_VALIDATE_INT);
$establishmentDetails = null;
$inspectionDetails = null;
$inspectionItems = [];
$inspectionActions = [];

// استعلام رئيسي مع التحقق من وجود الحقول
$sql = "SELECT 
            i.*,
            e.facility_name, e.license_no, e.area, e.sub_area, e.activity_type, 
            COALESCE(e.hazard_class, 'غير محدد') AS hazard_class,
            e.unit, e.Sub_UNIT, e.Sector, e.Sub_Sector, e.shfhsp, 
            COALESCE(e.phone_number, 'غير محدد') AS phone_number,
            COALESCE(e.email, 'غير محدد') AS email,
            u.EmpName AS inspector_name,
            approved_by.EmpName AS approved_by_name
        FROM tbl_inspections i
        JOIN establishments e ON i.facility_unique_id = e.unique_id
        LEFT JOIN Users u ON i.inspector_user_id = u.EmpID
        LEFT JOIN Users approved_by ON i.approved_by_user_id = approved_by.EmpID";

$params = [];
$types = "";

if ($inspectionId && $inspectionId > 0) {
    $sql .= " WHERE i.inspection_id = ?";
    $params[] = $inspectionId;
    $types = "i";
} else {
    // استعلام مبسط لقائمة التفتيشات
    $sql = "SELECT 
                i.inspection_id, i.inspection_date, i.inspection_type, 
                i.final_inspection_score, i.letter_grade,
                e.facility_name, 
                COALESCE(u.EmpName, 'غير معروف') AS inspector_name
            FROM tbl_inspections i
            JOIN establishments e ON i.facility_unique_id = e.unique_id
            LEFT JOIN Users u ON i.inspector_user_id = u.EmpID
            ORDER BY i.inspection_date DESC LIMIT 50";
}

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('فشل في تحضير استعلام التفتيشات: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception('فشل في تنفيذ الاستعلام: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('فشل في الحصول على النتائج: ' . $conn->error);
    }

    $inspections = $result->fetch_all(MYSQLI_ASSOC);

    if ($inspectionId && !empty($inspections)) {
        $inspectionDetails = $inspections[0];
        
        // تعبئة تفاصيل المنشأة مع قيم افتراضية
        $establishmentDetails = [
            'facility_name' => $inspectionDetails['facility_name'] ?? 'غير محدد',
            'license_no' => $inspectionDetails['license_no'] ?? 'غير محدد',
            'area' => $inspectionDetails['area'] ?? 'غير محدد',
            'sub_area' => $inspectionDetails['sub_area'] ?? 'غير محدد',
            'activity_type' => $inspectionDetails['activity_type'] ?? 'غير محدد',
            'hazard_class' => $inspectionDetails['hazard_class'] ?? 'غير محدد',
            'unit' => $inspectionDetails['unit'] ?? 'غير محدد',
            'Sub_UNIT' => $inspectionDetails['Sub_UNIT'] ?? 'غير محدد',
            'Sector' => $inspectionDetails['Sector'] ?? 'غير محدد',
            'Sub_Sector' => $inspectionDetails['Sub_Sector'] ?? 'غير محدد',
            'shfhsp' => $inspectionDetails['shfhsp'] ?? 'غير محدد',
            'phone_number' => $inspectionDetails['phone_number'] ?? 'غير محدد',
            'email' => $inspectionDetails['email'] ?? 'غير محدد'
        ];

        // جلب بنود التفتيش مع معالجة الأخطاء
        $stmtItems = $conn->prepare("
            SELECT 
                ii.*, 
                ic.code_description, 
                COALESCE(ic.code_category, 'غير محدد') AS code_category, 
                COALESCE(ic.standard_reference, 'غير محدد') AS standard_reference
            FROM tbl_inspection_items ii
            JOIN tbl_inspection_code ic ON ii.code_id = ic.code_id
            WHERE ii.inspection_id = ?
            ORDER BY ic.code_category DESC, ii.code_id ASC
        ");
        
        if ($stmtItems) {
            $stmtItems->bind_param("i", $inspectionId);
            if (!$stmtItems->execute()) {
                error_log('Error executing inspection items query: ' . $stmtItems->error);
            } else {
                $inspectionItems = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // حساب المخالفات مع قيم افتراضية صفرية
                $violationCounts = [
                    'critical' => 0,
                    'major' => 0,
                    'general' => 0,
                    'administrative' => 0
                ];
                
                foreach ($inspectionItems as $item) {
                    if ($item['is_violation'] && isset($item['code_category'])) {
                        switch ($item['code_category']) {
                            case 'Critical': $violationCounts['critical']++; break;
                            case 'Major': $violationCounts['major']++; break;
                            case 'General': $violationCounts['general']++; break;
                            case 'Administrative': $violationCounts['administrative']++; break;
                        }
                    }
                }
                
                $inspectionDetails['critical_violations'] = $violationCounts['critical'];
                $inspectionDetails['major_violations'] = $violationCounts['major'];
                $inspectionDetails['general_violations'] = $violationCounts['general'];
                $inspectionDetails['administrative_violations'] = $violationCounts['administrative'];
            }
        }

        // جلب الإجراءات المتخذة مع معالجة الأخطاء
        $stmtActions = $conn->prepare("
            SELECT 
                *,
                COALESCE(action_name, 'غير محدد') AS action_name,
                COALESCE(action_status, 'غير محدد') AS action_status
            FROM tbl_inspection_actions
            WHERE inspection_id = ?
            ORDER BY created_at DESC
        ");
        
        if ($stmtActions) {
            $stmtActions->bind_param("i", $inspectionId);
            if (!$stmtActions->execute()) {
                error_log('Error executing actions query: ' . $stmtActions->error);
            } else {
                $inspectionActions = $stmtActions->get_result()->fetch_all(MYSQLI_ASSOC);
            }
        }
    }
} catch (Exception $e) {
    error_log('Error in report generation: ' . $e->getMessage());
    die('<div class="error">حدث خطأ في توليد التقرير. يرجى المحاولة لاحقاً.</div>');
}

// إغلاق الاتصال بقاعدة البيانات
$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<style>
    body {
        font-family: 'Cairo', sans-serif;
        margin: 10px;
        background-color: #fcfcfc;
        color: #333;
        direction: rtl;
        text-align: right;
        line-height: 1.5;
        font-size: 0.9em;
        box-sizing: border-box;
    }

    body::before, body::after {
        display: none !important;
    }

    .report-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 10px;
    }

    .report-header .logo {
        order: 3; /* تم التعديل: الشعار في أقصى اليسار (في RTL) */
        margin-left: 0;
        margin-right: 0; 
    }

    .report-header .logo img {
        max-width: 100px;
        height: auto;
    }

    .report-header .department-info {
        order: 1; /* تم التعديل: معلومات القسم في أقصى اليمين (في RTL) */
        flex: 1;
        text-align: right;
        padding-right: 15px;
    }

    .report-header .title {
        order: 2; /* العنوان في المنتصف */
        flex: 2;
        text-align: center;
        color: #1a7a3b;
        font-size: 1.5em;
        font-weight: bold;
        margin: 0 10px;
    }

    .section-title {
        background-color: #f0f8f3;
        color: #1a7a3b;
        padding: 8px 15px;
        font-size: 1.1em;
        margin: 20px auto 10px;
        border-radius: 4px;
        text-align: center; /* ليظهر في المنتصف */
        width: 100%;
        border-right: 4px solid #1a7a3b;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .info-table, .actions-table {
        width: 100%;
        margin: 0 auto 15px;
        border-collapse: collapse;
        background-color: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        font-size: 0.85em;
        table-layout: fixed;
    }

    .info-table th, .info-table td,
    .actions-table th, .actions-table td {
        padding: 8px 12px;
        border: 1px solid #eaeaea;
        text-align: right;
        vertical-align: top;
        word-wrap: break-word;
    }

    .info-table th, .actions-table th {
        background-color: #f5fbf7;
        color: #1a7a3b;
        font-weight: bold;
    }

    .info-table tr:hover,
    .actions-table tr:hover {
        background-color: #f9f9f9;
    }

    table {
        width: 100%;
        margin: 0 auto;
        border-collapse: collapse;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        background-color: #fff;
        font-size: 0.85em;
        table-layout: fixed;
    }

    table th, table td {
        padding: 8px 12px;
        border: 1px solid #eaeaea;
        text-align: right;
        vertical-align: middle;
    }

    table th {
        background-color: #f5fbf7;
        color: #1a7a3b;
        font-weight: bold;
    }

    .no-data {
        text-align: center;
        padding: 15px;
        color: #666;
        width: 100%;
        margin: 15px auto;
        background-color: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        font-size: 0.9em;
    }

    .violation-item {
        border: 1px solid #e0e0e0;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 4px;
        background-color: #fff;
        page-break-inside: avoid;
    }

    .violation-item.compliant {
        border-left: 4px solid #4CAF50;
    }

    .violation-item:not(.compliant) {
        border-left: 4px solid #c62828;
    }

    .violation-title {
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
        font-size: 1em;
    }

    .violation-item.compliant .violation-title {
        color: #2e7d32;
    }

    .violation-item:not(.compliant) .violation-title {
        color: #c62828;
    }

    .violation-details {
        margin-right: 15px;
        color: #555;
        line-height: 1.6;
        font-size: 0.85em;
    }

    .photo-container {
        margin-top: 8px;
        text-align: center;
        page-break-inside: avoid;
    }

    .photo-container img {
        max-width: 200px;
        max-height: 200px;
        border: 1px solid #ddd;
        margin: 5px;
        border-radius: 3px;
    }

    /* الطباعة: إعدادات خاصة */
    @media print {
        @page {
            size: A4;
            margin: 1cm;
            @top-center { content: none !important; }
            @bottom-center { content: none !important; }
            @top-left { content: none !important; }
            @bottom-left { content: none !important; }
            @top-right { content: none !important; }
            @bottom-right { content: none !important; }
        }
    
        body {
            margin: 0;
            padding: 0;
            font-size: 10pt;
            color: #000;
            background-color: #fff;
        }

        body::before, body::after {
            display: none !important;
        }
    
        .report-header {
            border-bottom: 2px solid #1a7a3b;
            padding-bottom: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .report-header .logo {
            order: 3; /* تم التعديل: الشعار في أقصى اليسار للطباعة */
            margin-left: 0;
            margin-right: 0;
        }
        
        .report-header .logo img {
            max-width: 80px;
        }
        
        .report-header .title {
            order: 2; /* العنوان في المنتصف للطباعة */
            font-size: 1.3em;
        }
        
        .report-header .department-info {
            order: 1; /* تم التعديل: معلومات القسم في أقصى اليمين للطباعة */
        }

        .section-title {
            background-color: #e8f4ec !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            border-right: 3px solid #1a7a3b !important;
            page-break-after: avoid;
            text-align: center; /* بالفعل في المنتصف للطباعة */
        }
        
        .info-table, .actions-table, table {
            width: 100% !important;
            table-layout: fixed !important;
            page-break-inside: auto;
        }
        
        .info-table th, .info-table td,
        .actions-table th, .actions-table td,
        table th, table td {
            padding: 6px 8px;
            border: 1px solid #ddd !important;
            font-size: 9pt;
        }
        
        .info-table th, .actions-table th, table th {
            background-color: #e8f4ec !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        /* تحديد عرض الأعمدة للجداول */
        .establishment-info-table th:nth-child(1),
        .establishment-info-table th:nth-child(3),
        .establishment-info-table td:nth-child(1),
        .establishment-info-table td:nth-child(3) {
            width: 20% !important;
        }
        
        .establishment-info-table th:nth-child(2),
        .establishment-info-table th:nth-child(4),
        .establishment-info-table td:nth-child(2),
        .establishment-info-table td:nth-child(4) {
            width: 30% !important;
        }
        
        .inspection-details-table th:nth-child(1),
        .inspection-details-table th:nth-child(3),
        .inspection-details-table td:nth-child(1),
        .inspection-details-table td:nth-child(3) {
            width: 15% !important;
        }
        
        .inspection-details-table th:nth-child(2),
        .inspection-details-table th:nth-child(4),
        .inspection-details-table td:nth-child(2),
        .inspection-details-table td:nth-child(4) {
            width: 35% !important;
        }
        
        .inspection-details-table td[colspan="3"] {
            width: 85% !important;
        }
        
        .violation-item {
            border: 1px solid #ddd !important;
            page-break-inside: avoid;
        }
        
        .photo-container img {
            max-width: 150px !important;
        }
        
        .report-actions {
            display: none;
        }
    }

    /* التصميم المتجاوب */
    @media (max-width: 768px) {
        .report-header {
            flex-direction: row; /* الحفاظ على الترتيب في صفوف */
            flex-wrap: wrap; /* السماح بالالتفاف إذا لم يتسع المحتوى */
            text-align: center;
        }
        
        .report-header .logo {
            order: 3; /* تم التعديل: الشعار في أقصى اليسار في هذا العرض */
            margin: 5px 0;
            flex: 0 0 auto;
            text-align: center;
        }
        
        .report-header .department-info {
            order: 1; /* تم التعديل: معلومات القسم في أقصى اليمين في هذا العرض */
            padding-right: 0;
            text-align: center;
            flex: 1;
        }
        
        .report-header .title {
            order: 2; /* العنوان في المنتصف في هذا العرض */
            font-size: 1.3em;
            margin: 5px 0;
            flex: 2;
        }
        
        /* ... باقي خصائص media (max-width: 768px) كما هي ... */
        body {
            margin: 5px;
            font-size: 0.85em;
        }
        
        .section-title {
            font-size: 1em;
            padding: 6px 10px;
        }
        
        .info-table, .actions-table, table {
            font-size: 0.8em;
        }
        
        .photo-container img {
            max-width: 150px;
        }
    }

    @media (max-width: 480px) {
        .report-header {
            flex-direction: column; /* جعل العناصر تتكدس عمودياً في الشاشات الصغيرة جداً */
        }
        
        .report-header .logo {
            order: 0; /* الشعار في الأعلى عند التكديس */
        }
        
        .report-header .title {
            font-size: 1.1em;
            order: 1; /* العنوان تحت الشعار عند التكديس */
            margin: 5px 0;
        }
        
        .report-header .department-info {
            order: 2; /* معلومات القسم تحت العنوان عند التكديس */
            margin: 5px 0;
        }
        
        .info-table, .actions-table, table {
            display: block;
            overflow-x: auto;
        }
        
        .violation-details {
            font-size: 0.8em;
        }
    }

    /* ألوان التصنيفات */
    .grade-Aplus, .grade-A { background-color: #f7fff7; color: #1a7a3b; font-weight: bold; }
    .grade-B { background-color: #f7faff; color: #0056b3; font-weight: bold; }
    .grade-C { background-color: #fffff7; color: #b38f00; font-weight: bold; }
    .grade-D { background-color: #fff7f7; color: #c62828; font-weight: bold; }
    .grade-E { background-color: #fcf4f5; color: #c62828; font-weight: bold; }
    
    /* ألوان البطاقات */
    .color-card-green { background-color: #f7fff7; color: #1a7a3b; font-weight: bold; }
    .color-card-yellow { background-color: #fffff7; color: #b38f00; font-weight: bold; }
    .color-card-red { background-color: #fff7f7; color: #c62828; font-weight: bold; }
    .color-card-orange { background-color: #fffaf7; color: #d65a00; font-weight: bold; }
    .color-card-none { background-color: #fafafa; color: #666; }
    
    /* ألوان حالة الإجراء */
    .action-status-active { color: #d32f2f; font-weight: bold; }
    .action-status-completed { color: #388e3c; font-weight: bold; }
    .action-status-cancel { color: #388e3c; font-weight: bold; }
    
    /* ألوان حالة الاعتماد */
    .approval-status-pending { background-color: #fff7f7; color: #c62828; font-weight: bold; }
    .approval-status-approved { background-color: #f7fff7; color: #1a7a3b; font-weight: bold; }

    /* أزرار الإجراءات */
    .report-actions {
        text-align: center;
        margin: 15px auto;
        width: 98%;
    }
    .report-actions button {
        background-color: #1a7a3b;
        color: white;
        padding: 8px 18px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.95em;
        margin: 4px;
        transition: background-color 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .report-actions button:hover {
        background-color: #155724;
    }
</style>
</head>
<body>
    <div class="report-header">
        <div class="department-info">
            <p>إدارة الرقابة والسلامة الصحية</p>
            <p>قسم الرقابة الغذائية</p>
        </div>
        <div class="title">
            تقرير تفتيش منشأة غذائية
        </div>
        <div class="logo">
            <img src="shjmunlogo.png?v=<?php echo time(); ?>" alt="شعار بلدية الشارقة" />
        </div>
    </div>

    <?php if ($inspectionId && $establishmentDetails): ?>

        <div class="report-actions">
            <button onclick="window.print()"><i class="fas fa-print"></i> طباعة التقرير</button>
        </div>

        <div class="section-title">معلومات المنشأة</div>
        <table class="info-table establishment-info-table">
            <tr>
                <th>اسم المنشأة</th>
                <td><?php echo htmlspecialchars($establishmentDetails['facility_name']); ?></td>
                <th>رقم الرخصة</th>
                <td><?php echo htmlspecialchars($establishmentDetails['license_no']); ?></td>
            </tr>
            <tr>
                <th>المنطقة</th>
                <td><?php echo htmlspecialchars($establishmentDetails['area'] . ' - ' . $establishmentDetails['sub_area']); ?></td>
                <th>نوع النشاط</th>
                <td><?php echo htmlspecialchars($establishmentDetails['activity_type']); ?></td>
            </tr>
            <tr>
                <th>التصنيف الخطورة</th>
                <td><?php echo htmlspecialchars($establishmentDetails['hazard_class']); ?></td>
                <th>القطاع</th>
                <td>
                    <?php
                        $sectorDisplay = '';
                        if (!empty($establishmentDetails['Sector'])) {
                            $sectorDisplay .= htmlspecialchars($establishmentDetails['Sector']);
                        }
                        if (!empty($establishmentDetails['Sub_Sector'])) {
                            if (!empty($sectorDisplay)) {
                                $sectorDisplay .= ' - ';
                            }
                            $sectorDisplay .= htmlspecialchars($establishmentDetails['Sub_Sector']);
                        }
                        echo $sectorDisplay;
                    ?>
                </td>
            </tr>
            <tr>
                <th>الوحدة</th>
                <td><?php echo htmlspecialchars($establishmentDetails['unit'] . ' - ' . $establishmentDetails['Sub_UNIT']); ?></td>
                <th>SHFHSP</th>
                <td><?php echo htmlspecialchars($establishmentDetails['shfhsp']); ?></td>
            </tr>
            <tr>
                <th>هاتف المنشأة</th>
                <td><?php echo htmlspecialchars($establishmentDetails['phone_number']); ?></td>
                <th>البريد الإلكتروني</th>
                <td><?php echo htmlspecialchars($establishmentDetails['email']); ?></td>
            </tr>
        </table>

        <div class="section-title">تفاصيل التفتيش</div>
        <table class="info-table inspection-details-table">
            <tr>
                <th>رقم التفتيش</th>
                <td><?php echo htmlspecialchars($inspectionDetails['inspection_id']); ?></td>
                <th>تاريخ التفتيش</th>
                <td><?php echo htmlspecialchars($inspectionDetails['inspection_date']); ?></td>
            </tr>
            <tr>
                <th>نوع التفتيش</th>
                <td colspan="3"><?php echo htmlspecialchars($inspectionDetails['inspection_type']); ?></td>
            </tr>
            <tr>
                <th>الدرجة النهائية</th>
                <td><?php echo htmlspecialchars($inspectionDetails['final_inspection_score']); ?></td>
                <th>التقييم</th>
                <?php
                    $letterGradeClass = '';
                    $letterGrade = htmlspecialchars($inspectionDetails['letter_grade']);
                    switch ($letterGrade) {
                        case 'A+':
                        case 'A': $letterGradeClass = 'grade-Aplus'; break;
                        case 'B': $letterGradeClass = 'grade-B'; break;
                        case 'C': $letterGradeClass = 'grade-C'; break;
                        case 'D': $letterGradeClass = 'grade-D'; break;
                        case 'E': $letterGradeClass = 'grade-E'; break;
                        default: $letterGradeClass = '';
                    }
                ?>
                <td class="<?php echo $letterGradeClass; ?>"><?php echo $letterGrade; ?></td>
            </tr>
            <tr>
                <th>النسبة المئوية</th>
                <td><?php echo htmlspecialchars($inspectionDetails['percentage_score']); ?>%</td>
                <th>لون البطاقة</th>
                <?php
                    $colorCardClass = '';
                    $colorCard = htmlspecialchars($inspectionDetails['color_card']);
                    switch ($colorCard) {
                        case 'Green': $colorCardClass = 'color-card-green'; break;
                        case 'Yellow': $colorCardClass = 'color-card-yellow'; break;
                        case 'Red': $colorCardClass = 'color-card-red'; break;
                        case 'Orange': $colorCardClass = 'color-card-orange'; break;
                        default: $colorCardClass = 'color-card-none';
                    }
                ?>
                <td class="<?php echo $colorCardClass; ?>"><?php echo $colorCard; ?></td>
            </tr>
            <tr>
                <th>المخالفات الحرجة</th>
                <td><?php echo htmlspecialchars($inspectionDetails['critical_violations']); ?></td>
                <th>المخالفات الهامة</th>
                <td><?php echo htmlspecialchars($inspectionDetails['major_violations']); ?></td>
            </tr>
            <tr>
                <th>المخالفات العامة</th>
                <td><?php echo htmlspecialchars($inspectionDetails['general_violations']); ?></td>
                <th>المخالفات الإدارية</th>
                <td><?php echo htmlspecialchars($inspectionDetails['administrative_violations']); ?></td>
            </tr>
            <tr>
                <th>إجمالي النقاط المخصومة</th>
                <td><?php echo htmlspecialchars($inspectionDetails['total_deducted_points']); ?></td>
                <th>إجمالي قيمة المخالفات</th>
                <td><?php echo htmlspecialchars($inspectionDetails['total_violation_value']); ?> درهم</td>
            </tr>
            <tr>
                <th>موعد التفتيش القادم</th>
                <td colspan="3"><?php echo htmlspecialchars($inspectionDetails['next_inspection_date']); ?></td>
            </tr>
            <tr>
                <th>ملاحظات التفتيش</th>
                <td colspan="3"><?php echo nl2br(htmlspecialchars($inspectionDetails['notes'] ?? 'لا توجد ملاحظات.')); ?></td>
            </tr>
            <tr>
                <th>اسم المفتش</th>
                <td colspan="3"><?php echo htmlspecialchars($inspectionDetails['inspector_name'] ?? 'غير معروف'); ?></td>
            </tr>
            <tr>
                <th>حالة الاعتماد</th>
                <?php
                    $approvalStatusClass = '';
                    $approvalStatus = htmlspecialchars($inspectionDetails['approval_status']);
                    switch ($approvalStatus) {
                        case 'Pending': $approvalStatusClass = 'approval-status-pending'; break;
                        case 'Approved': $approvalStatusClass = 'approval-status-approved'; break;
                        default: $approvalStatusClass = '';
                    }
                ?>
                <td class="<?php echo $approvalStatusClass; ?>"><?php echo $approvalStatus; ?></td>
                <th>معتمد بواسطة</th>
                <td><?php echo htmlspecialchars($inspectionDetails['approved_by_name'] ?? 'غير معتمد'); ?></td>
            </tr>
            <tr>
                <th>تاريخ الاعتماد</th>
                <td colspan="3"><?php echo htmlspecialchars($inspectionDetails['approval_date'] ?? 'غير معتمد'); ?></td>
            </tr>
        </table>

        <?php if (!empty($inspectionActions)): ?>
        <div class="section-title">الإجراءات المتخذة</div>
        <table class="actions-table">
            <thead>
                <tr>
                    <th>اسم الإجراء</th>
                    <th>رقم الإجراء</th>
                    <th>المدة (أيام)</th>
                    <th>الحالة</th>
                    <th>معرف الإجراء السابق</th>
                    <th>ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inspectionActions as $action): ?>
                <tr>
                    <td><?php echo htmlspecialchars($action['action_name']); ?></td>
                    <td><?php echo htmlspecialchars($action['action_number'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($action['action_duration_days'] ?? '-'); ?></td>
                    <td>
                        <?php
                            $statusClass = '';
                            switch ($action['action_status']) {
                                case 'active': $statusClass = 'action-status-active'; break;
                                case 'cancel': $statusClass = 'action-status-cancel'; break;
                                case 'completed': $statusClass = 'action-status-completed'; break;
                                default: $statusClass = '';
                            }
                        ?>
                        <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($action['action_status']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($action['previous_action_entry_id'] ?? '-'); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($action['action_notes'] ?? 'لا توجد ملاحظات.')); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php elseif ($inspectionId): ?>
            <div class="no-data">
                لا توجد إجراءات متخذة لهذا التفتيش المحدد.
            </div>
        <?php endif; ?>

        <?php if (!empty($inspectionItems)): ?>
        <div class="section-title">بنود التفتيش والمخالفات</div>
        <div style="width: 98%; margin: 0 auto;"> 
            <?php foreach ($inspectionItems as $item): ?>
            <div class="violation-item <?php echo $item['is_violation'] ? '' : 'compliant'; ?>">
                <div class="violation-title">
                    <?php echo htmlspecialchars($item['code_id']); ?> - <?php echo htmlspecialchars($item['code_description']); ?>
                    <span style="font-size:0.8em; color: #666;">(<?php echo htmlspecialchars($item['code_category']); ?>)</span>
                </div>
                <div class="violation-details">
                    <strong>الحالة:</strong>
                    <?php echo $item['is_violation'] ? '<span style="color:#d32f2f;font-weight:bold;">مخالفة</span>' : '<span style="color:#388e3c;font-weight:bold;">مطابق</span>'; ?><br>

                    <?php if ($item['is_violation']): ?>
                        <strong>الإجراء المتخذ:</strong> <?php echo htmlspecialchars($item['action_taken']); ?><br>
                        <strong>مستوى الحالة:</strong> <?php echo htmlspecialchars($item['condition_level']); ?><br>
                        <strong>النقاط المخصومة:</strong> <?php echo htmlspecialchars($item['deducted_points']); ?><br>
                        <?php if ($item['violation_value']): ?>
                            <strong>قيمة المخالفة:</strong> <?php echo htmlspecialchars($item['violation_value']); ?> درهم<br>
                        <?php endif; ?>
                    <?php endif; ?>
                    <strong>الملاحظات:</strong> <?php echo nl2br(htmlspecialchars($item['inspector_notes'] ?? 'لا توجد ملاحظات')); ?><br>
                </div>
                <?php
                if (!empty($item['inspection_photo_path']) && file_exists($item['inspection_photo_path'])) {
                    echo '<div class="photo-container"><img src="' . htmlspecialchars($item['inspection_photo_path']) . '" alt="صورة البند"></div>';
                }
                ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif ($inspectionId): ?>
            <div class="no-data">
                لا توجد بنود تفتيش أو مخالفات لهذا التفتيش المحدد.
            </div>
        <?php endif; ?>
        
    <?php else: ?>
    <div class="section-title">قائمة التفتيشات الأخيرة</div>
    <?php if (!empty($inspections)): ?>
    <table>
        <thead>
            <tr>
                <th>معرف التفتيش</th>
                <th>تاريخ التفتيش</th>
                <th>نوع التفتيش</th>
                <th>اسم المنشأة</th>
                <th>اسم المفتش</th>
                <th>الدرجة النهائية</th>
                <th>التقييم</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inspections as $insp): ?>
            <tr>
                <td><?php echo htmlspecialchars($insp['inspection_id']); ?></td>
                <td><?php echo htmlspecialchars($insp['inspection_date']); ?></td>
                <td><?php echo htmlspecialchars($insp['inspection_type']); ?></td>
                <td><?php echo htmlspecialchars($insp['facility_name']); ?></td>
                <td><?php echo htmlspecialchars($insp['inspector_name'] ?? 'غير معروف'); ?></td>
                <td><?php echo htmlspecialchars($insp['final_inspection_score']); ?></td>
                <td><?php echo htmlspecialchars($insp['letter_grade']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="no-data">
        لا توجد بيانات تفتيش لعرضها.
    </div>
    <?php endif; ?>
    <?php endif; ?>

</body>
</html>
<?php
ob_end_flush(); // إنهاء التخزين المؤقت للإخراج
?>