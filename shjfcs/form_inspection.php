<?php
session_start(); // بدء الجلسة في أعلى الملف
$loggedInUserId = $_SESSION['user']['EmpID'] ?? null;
$loggedInUserName = $_SESSION['user']['EmpName'] ?? 'غير معروف';
// التحقق من تسجيل الدخول
if (!$loggedInUserId) {
    header('Location: login.php'); // إعادة توجيه إلى صفحة الدخول إذا لم يكن مسجل الدخول
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نموذج التفتيش الموحد</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
:root {
    --primary-color: #28a745;
    --secondary-bg: #e8f5e9;
    --card-bg: #ffffff;
    --text-color: #333;
    --border-color: #ddd;
    --focus-color: #1a7a3b;
    --shadow-light: rgba(0, 0, 0, 0.08);
    --danger-color: #dc3545;
    --table-bg: #f9f9f9;
}
body {
    font-family: 'Cairo', sans-serif;
    margin: 0;
    padding: 0;
    background-color: var(--secondary-bg);
    color: var(--text-color);
    direction: rtl;
    font-size: 0.85em;
}
.container {
    max-width: 100%;
    margin: 10px auto;
    padding: 10px;
    background-color: var(--card-bg);
    border-radius: 6px;
    box-shadow: 0 2px 6px var(--shadow-light);
}
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 8px;
    margin-bottom: 10px;
    border-bottom: 1px solid var(--primary-color);
}
.header img {
    height: 40px;
}
.header-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    color: #1a7a3b;
    font-weight: bold;
}
.header-text .main-title {
    font-size: 0.95em;
    margin-bottom: 1px;
}
.header-text .sub-title {
    font-size: 0.75em;
    color: #4CAF50;
}
h1 {
    text-align: center;
    color: var(--primary-color);
    margin-bottom: 15px;
    font-size: 1.5em;
}
.form-section {
    background-color: var(--card-bg);
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 6px;
    box-shadow: 0 1px 3px var(--shadow-light);
    border: 1px solid var(--border-color);
}
.form-section h3 {
    color: var(--primary-color);
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 5px;
    margin-bottom: 8px;
    font-size: 1.1em;
    text-align: right;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
}
.form-group {
    margin-bottom: 0;
}
label {
    margin-bottom: 2px;
    display: block;
    font-weight: bold;
    color: #555;
    font-size: 0.8em;
    text-align: right;
}
input, select, textarea {
    width: 100%;
    padding: 6px;
    margin-bottom: 0;
    border: 1px solid var(--border-color);
    border-radius: 3px;
    box-sizing: border-box;
    font-family: 'Cairo', sans-serif;
    font-size: 0.85em;
    direction: rtl;
    text-align: right;
}
input[type="date"] {
    direction: rtl;
    text-align: right;
}
#inspectionType {
    font-size: 0.75em;
}
input:focus, select:focus, textarea:focus {
    border-color: var(--focus-color);
    outline: none;
    box-shadow: 0 0 0 1px rgba(40, 167, 69, 0.2);
}
.readonly {
    background-color: #f5f5f5;
    cursor: not-allowed;
}
#message {
    margin-bottom: 10px;
    padding: 6px;
    border-radius: 4px;
    text-align: center;
    display: none;
}
.success {
    background-color: #e8f5e9;
    border: 1px solid var(--primary-color);
    color: var(--focus-color);
}
.error {
    background-color: #ffe6e6;
    border: 1px solid var(--danger-color);
    color: #c82333;
}
.search-area {
    display: flex;
    gap: 5px;
    margin-bottom: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.search-area input[type="text"],
.search-area input[type="search"],
.search-area select {
    flex: 1 1 auto;
    min-width: 120px;
    max-width: 180px;
    font-size: 0.8em;
    padding: 5px;
}
.search-area button {
    white-space: nowrap;
    padding: 5px 10px;
    font-size: 0.8em;
}
button {
    padding: 6px 12px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85em;
    display: flex;
    align-items: center;
    transition: all 0.2s;
}
button:hover {
    background-color: var(--focus-color);
    transform: translateY(-1px);
}
button i {
    margin-left: 5px;
}
.search-btn {
    background-color: #ffc107;
    color: #333;
}
.search-btn:hover {
    background-color: #e0a800;
}
.btn-secondary {
    background-color: #6c757d;
    color: white;
}
.btn-secondary:hover {
    background-color: #5a6268;
}
.btn-danger {
    background-color: var(--danger-color);
    color: white;
}
.btn-danger:hover {
    background-color: #c82333;
}
.item-row {
    border: 1px solid #eee;
    padding: 8px;
    margin-bottom: 8px;
    border-radius: 4px;
    background-color: #f9f9f9;
    direction: rtl;
    text-align: right;
}
.item-row p {
    margin: 0 0 5px 0;
}
.violation-details {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #ccc;
}
.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
    margin: 10px 0;
}
.result-item {
    padding: 6px;
    background-color: #f8f9fa;
    border-radius: 4px;
}
.result-item strong {
    display: block;
    margin-bottom: 3px;
    color: var(--primary-color);
}
.previous-violation-indicator {
    background-color: #ffe0b2;
    color: #e65100;
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 0.7em;
    font-weight: bold;
    margin-right: 5px;
    display: inline-block;
    float: left;
}
.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}
.item-header p {
    margin: 0;
    flex-grow: 1;
}
.violation-toggle-group {
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.button-group {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    margin-top: 10px;
    justify-content: flex-end;
}
.button-group button {
    margin-top: 0;
    flex-grow: 1;
    text-align: center;
}
.high-violation {
    background-color: #ffdddd;
    border-left: 2px solid var(--danger-color);
}
#registerEstablishmentBtn {
    display: block;
    width: 100%;
    text-align: center;
    margin-top: 10px;
    padding: 8px;
    font-size: 0.95em;
}
.hidden {
    display: none;
}
.switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 20px;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 20px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}
input:checked + .slider {
    background-color: var(--primary-color);
}
input:focus + .slider {
    box-shadow: 0 0 1px var(--primary-color);
}
input:checked + .slider:before {
    transform: translateX(18px);
}
.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 3px;
}
.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 3px;
    font-weight: normal;
    cursor: pointer;
}
.checkbox-group input[type="checkbox"] {
    width: auto;
    margin-bottom: 0;
}
.item-details-expanded {
    background-color: #f0fdf4;
    border-left: 2px solid var(--primary-color);
    padding: 6px;
    margin-top: 5px;
    border-radius: 3px;
    font-size: 0.8em;
}
.item-details-expanded p {
    margin: 3px 0;
}
.photo-preview-container {
    margin-top: 5px;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}
.photo-preview-container img {
    max-width: 80px;
    max-height: 80px;
    border: 1px solid #ddd;
    border-radius: 3px;
    object-fit: cover;
}
.photo-actions {
    display: flex;
    gap: 3px;
    margin-top: 3px;
}
.photo-actions button {
    padding: 4px 8px;
    font-size: 0.75em;
}
.editable-field {
    background-color: #fff !important;
    cursor: text !important;
}
.action-details {
    margin-top: 5px;
    padding: 6px;
    background-color: #f8f9fa;
    border-radius: 3px;
    border-left: 2px solid #6c757d;
}
#message, .container, .form-section, .search-area {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}
.actions-section {
    margin-top: 10px;
    border-top: 1px solid #ddd;
    padding-top: 10px;
}
.actions-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 5px;
    table-layout: fixed;
    background-color: var(--table-bg);
}
.actions-table th, .actions-table td {
    border: 1px solid #ddd;
    padding: 6px;
    text-align: right;
    background-color: var(--table-bg);
}
.actions-table th {
    background-color: var(--primary-color);
    color: white;
    position: sticky;
    top: 0;
    z-index: 10;
}
.actions-table tr {
    background-color: var(--table-bg);
}
#evaluationBtn {
    background-color: #17a2b8;
    color: white;
    margin-top: 5px;
    width: 100%;
}
#searchItemsInput {
    width: 100%;
    padding: 8px;
    margin-bottom: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    text-align: right;
}
/* ✅ PDF Preview Styles */
.pdf-preview-container {
    margin-top: 10px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 5px;
    border: 1px solid var(--border-color);
    text-align: right;
}
.pdf-preview-container embed {
    width: 100%;
    height: 400px;
    border: 1px solid #ddd;
    border-radius: 4px;
    display: none;
}
.pdf-preview-container.hidden {
    display: none;
}
.pdf-no-preview {
    text-align: center;
    color: #6c757d;
    font-style: italic;
}
.pdf-link {
    display: inline-block;
    margin-top: 10px;
    padding: 8px 12px;
    background-color: var(--primary-color);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 0.9em;
}
.pdf-link:hover {
    background-color: var(--focus-color);
}
#itemsContainer {
    direction: rtl;
    text-align: right;
}
/* ✅ قسم نتائج التفتيش - كامل الخط اتجاه اليمين */
#resultsSection {
    direction: rtl;
    text-align: right;
}
#resultsSection .form-grid,
#resultsSection .results-grid,
#resultsSection .form-group,
#resultsSection .result-item {
    direction: rtl;
    text-align: right;
}
#resultsSection input,
#resultsSection select,
#resultsSection textarea {
    direction: rtl;
    text-align: right;
}
/* ✅ قسم PDF في النتائج */
.results-pdf-section {
    margin-top: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 6px;
    border: 1px solid var(--border-color);
}
.pdf-upload-controls {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
}
.pdf-upload-controls input[type="file"] {
    flex: 1;
    min-width: 200px;
}
.pdf-upload-controls button {
    white-space: nowrap;
}
.pdf-preview-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}
.pdf-preview-actions .btn-primary {
    background-color: var(--primary-color);
}
.pdf-preview-actions .btn-secondary {
    background-color: #6c757d;
}
/* ✅ زر حفظ البنود في أسفل الصفحة */
.bottom-save-button {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1000;
    padding: 12px 24px;
    font-size: 1.1em;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}
.bottom-save-button:hover {
    background-color: var(--focus-color);
    transform: translateX(-50%) translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}
.bottom-save-button i {
    font-size: 1.2em;
}
</style>
</head>
<body>
<div class="container" style="direction: rtl;">
    <!-- Header -->
    <div class="header" style="display: flex; align-items: center; justify-content: space-between;">
        <div class="header-text" style="text-align: right;">
            <div class="main-title" style="font-weight: bold; font-size: 18px;">إدارة الرقابة والسلامة الصحية</div>
            <div class="sub-title" style="font-size: 16px;">قسم الرقابة الغذائية</div>
        </div>
        <div class="logo">
            <img src="shjmunlogo.png" alt="شعار البلدية" style="height: 60px;">
        </div>
    </div>
    <!-- Page Title -->
    <h1>التفتيش المبني علي الخطورة</h1>
  
    <!-- Message -->
    <div id="message"></div>
    <!-- 🔍 بحث السجلات السابقة برقم الرخصة -->
    <div class="search-license-box">
        <label for="fullLicenseSearch" style="display: block; margin-bottom: 5px;">بحث السجلات السابقة برقم الرخصة</label>
      
        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <input type="text" id="fullLicenseSearch" placeholder="أدخل رقم الرخصة الكامل" style="flex: 1; padding: 6px;">
          
            <button type="button" class="btn-primary" id="searchFullLicenseBtn">
                <i class="fas fa-search"></i> بحث
            </button>
          
            <button type="button" class="btn-secondary" id="previousFacilityBtn">
                <i class="fas fa-arrow-right"></i> السابق
            </button>
          
            <button type="button" class="btn-secondary" id="nextFacilityBtn">
                <i class="fas fa-arrow-left"></i> التالي
            </button>
        </div>
    </div>
    <!-- 🔍 إدخال رقم الرخصة للتسجيل -->
    <div class="form-section" id="searchSection">
        <h3>1. إدخال رقم الرخصة للتسجيل</h3>
      
        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
            <input type="text" id="licenseNo" placeholder="أدخل رقم الرخصة أو المعرف الفريد أو اسم المنشأة" style="flex: 1; padding: 6px;">
          
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search"></i> بحث
            </button>
        </div>
        <!-- زر تسجيل منشأة جديدة -->
        <button type="button" class="btn-primary hidden" id="registerEstablishmentBtn" style="margin-top: 10px;">
            <i class="fas fa-plus"></i> تسجيل منشأة جديدة
        </button>
        <!-- اختيار المنشأة -->
        <div id="facilitySelection" class="form-group hidden" style="margin-top: 10px;">
            <label for="facilitySelector">اختر المنشأة:</label>
            <select id="facilitySelector">
                <option value="">-- اختر منشأة --</option>
            </select>
        </div>
        <div id="facilityInfo">
            <div class="form-grid">
                <div class="form-group">
                    <label>اسم المنشأة</label>
                    <input type="text" id="facilityName" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>رقم الرخصة</label>
                    <input type="text" id="licenseNumberDisplay" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>المعرف الفريد</label>
                    <input type="text" id="uniqueId" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>المنطقة</label>
                    <input type="text" id="area" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>نوع النشاط</label>
                    <input type="text" id="activityType" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>الوحدة</label>
                    <input type="text" id="unit" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>SHFHSP</label>
                    <input type="text" id="shfhsp" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>فئة الخطر</label>
                    <input type="text" id="hazardClass" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>القطاع الفرعي</label>
                    <input type="text" id="sub_Sector" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>تاريخ آخر تفتيش</label>
                    <input type="date" id="lastInspectionDate" readonly class="readonly">
                </div>
                <div class="form-group">
                    <label>تاريخ آخر تقييم للمنشأة</label>
                    <input type="date" id="lastEvaluationDate" readonly class="readonly">
                    <button type="button" id="evaluationBtn" class="btn-primary hidden" style="margin-top: 5px;">
                        <i class="fas fa-star"></i> تقييم المنشأة
                    </button>
                </div>
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="text" id="establishmentEmail" readonly class="readonly">
                </div>
            </div>
            <div id="establishmentActionButtons" class="button-group hidden">
                <button type="button" class="btn-secondary" id="editEstablishmentBtn">
                    <i class="fas fa-edit"></i> تعديل المنشأة
                </button>
                <button type="button" class="btn-primary" id="evaluateEstablishmentBtn">
                    <i class="fas fa-star"></i> تقييم المنشأة
                </button>
            </div>
        </div>
        <div class="form-section hidden" id="inspectionSection">
            <h3>2. تفاصيل التفتيش</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>تاريخ التفتيش</label>
                    <input type="date" id="inspectionDate" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>نوع التفتيش</label>
                    <select id="inspectionType" name="inspectionType" class="form-control">
                        <option value="">-- اختر نوع التفتيش --</option>
                        <option value="دوري">دوري</option>
                        <option value="متابعة">متابعة</option>
                        <option value="حملة">حملة</option>
                        <option value="عينات">عينات</option>
                        <option value="شكوى">شكوى</option>
                    </select>
                </div>
                <div class="form-group hidden" id="campaignGroup">
    <label>اسم الحملة</label>
    <select id="campaignName" class="form-control">
        <option value="">-- اختر اسم الحملة --</option>
    </select>
</div>

                <div class="form-group">
                    <label>معرف المفتش</label>
                    <input type="number" id="inspectorId" value="<?php echo htmlspecialchars($loggedInUserId); ?>" readonly class="readonly">
                </div>
            </div>
            <div class="form-group">
                <label>ملاحظات عامة</label>
                <textarea id="notes"></textarea>
            </div>
            <button id="createInspectionBtn"><i class="fas fa-plus-circle"></i> إنشاء التفتيش</button>
        </div>
        <div class="form-section hidden" id="itemsSection">
            <h3>3. بنود التفتيش</h3>
            <div class="form-group">
                <label for="searchItemsInput">البحث في بنود التفتيش برقم البند (code_id)</label>
                <input type="text" id="searchItemsInput" placeholder="أدخل رقم البند للبحث...">
            </div>
            <div id="itemsContainer"></div>
            <!-- تم نقل أزرار البنود إلى أسفل الصفحة -->
        </div>
        <!-- قسم الإجراءات المتخذة -->
        <div class="form-section hidden" id="actionsSection">
            <h3>4. الإجراءات المتخذة</h3>
            <div id="actionsContainer">
                <table class="actions-table">
                    <thead>
                        <tr>
                            <th>نوع الإجراء</th>
                            <th>رقم الإجراء</th>
                            <th>المدة (يوم)</th>
                            <th>الحالة</th>
                            <th>الإجراء السابق</th>
                            <th>خيارات</th>
                        </tr>
                    </thead>
                    <tbody id="actionsList"></tbody>
                </table>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <button type="button" class="btn-primary" id="addActionBtn">
                    <i class="fas fa-plus"></i> إضافة إجراء
                </button>
            </div>
        </div>
        <div class="form-section hidden" id="resultsSection">
            <h3>5. نتائج التفتيش</h3>
            <div class="results-grid">
                <div class="result-item">
                    <strong>رقم التفتيش</strong>
                    <span id="resultInspectionId">-</span>
                </div>
                <div class="result-item">
                    <strong>تاريخ التفتيش</strong>
                    <span id="resultDate">-</span>
                </div>
                <div class="result-item">
                    <strong>نوع التفتيش</strong>
                    <span id="resultType">-</span>
                </div>
                <div class="result-item">
                    <strong>النقاط المخصومة</strong>
                    <span id="resultDeducted">0.00</span>
                </div>
                <div class="result-item">
                    <strong>الدرجة النهائية</strong>
                    <span id="resultScore">1000.00</span>
                </div>
                <div class="result-item">
                    <strong>النسبة المئوية</strong>
                    <span id="resultPercentage">100%</span>
                </div>
                <div class="result-item">
                    <strong>التقدير</strong>
                    <span id="resultGrade">-</span>
                </div>
                <div class="result-item">
                    <strong>لون البطاقة</strong>
                    <span id="resultCard">-</span>
                </div>
                <div class="result-item">
                    <strong>المخالفات الحرجة</strong>
                    <span id="resultCritical">0</span>
                </div>
                <div class="result-item">
                    <strong>المخالفات الهامة</strong>
                    <span id="resultMajor">0</span>
                </div>
                <div class="result-item">
                    <strong>المخالفات العامة</strong>
                    <span id="resultGeneral">0</span>
                </div>
                <div class="result-item">
                    <strong>المخالفات الإدارية</strong>
                    <span id="resultAdministrative">0</span>
                </div>
                <div class="result-item">
                    <strong>موعد التفتيش القادم</strong>
                    <span id="resultNextDate">-</span>
                </div>
                <div class="result-item">
                    <strong>إجمالي قيمة المخالفة</strong>
                    <input type="number" id="totalViolationValue" class="readonly" readonly step="0.01">
                </div>
                <div class="result-item">
                    <strong>حالة الاعتماد</strong>
                    <input type="text" id="approvalStatus" class="readonly" value="Pending" readonly>
                </div>
                <div class="result-item">
                    <strong>تم الاعتماد بواسطة</strong>
                    <input type="text" id="approvedBy" class="readonly" readonly>
                </div>
                <div class="result-item">
                    <strong>تاريخ الاعتماد</strong>
                    <input type="date" id="approvalDate" class="readonly" readonly>
                </div>
                <div class="result-item">
                    <strong>آخر تحديث بواسطة</strong>
                    <input type="text" id="updatedBy" class="readonly" readonly>
                </div>
            </div>
            <div class="form-group">
                <label>ملاحظات التفتيش</label>
                <textarea id="resultNotes" readonly class="readonly"></textarea>
            </div>
            <!-- ✅ قسم PDF في نتائج التفتيش -->
            <div class="results-pdf-section">
                <h4>ملف PDF للتفتيش</h4>
                <div class="pdf-upload-controls">
                    <input type="file" id="resultsInspectionPdfFile" accept=".pdf">
                    <button type="button" id="resultsUploadPdfBtn" class="btn-primary">
                        <i class="fas fa-upload"></i> تحميل PDF
                    </button>
                    <button type="button" id="resultsViewPdfBtn" class="btn-secondary hidden">
                        <i class="fas fa-eye"></i> عرض PDF
                    </button>
                    <button type="button" id="resultsDeletePdfBtn" class="btn-danger hidden">
                        <i class="fas fa-trash"></i> حذف PDF
                    </button>
                </div>
                <div id="resultsPdfPreview" class="pdf-preview-container hidden">
                    <label>معاينة ملف PDF:</label>
                    <embed id="resultsPdfEmbed" type="application/pdf" src="" style="display: none;">
                    <div id="resultsPdfNoPreview" class="pdf-no-preview">لا يوجد ملف PDF للمعاينة</div>
                    <div class="pdf-preview-actions">
                        <a id="resultsPdfLink" class="pdf-link hidden" href="#" target="_blank">
                            <i class="fas fa-external-link-alt"></i> فتح في نافذة جديدة
                        </a>
                        <button type="button" id="toggleResultsPdfPreview" class="btn-secondary">
                            <i class="fas fa-eye"></i> إظهار/إخفاء المعاينة
                        </button>
                    </div>
                </div>
            </div>
            <div class="button-group">
                <button type="button" class="btn-primary" id="approveInspectionBtn">
                    <i class="fas fa-check-circle"></i> اعتماد التفتيش
                </button>
                <button type="button" class="btn-secondary" id="editInspectionBtn">
                    <i class="fas fa-edit"></i> تعديل التفتيش
                </button>
                <button type="button" class="btn-secondary" id="newInspectionBtnResults">
                    <i class="fas fa-plus"></i> تفتيش جديد
                </button>
                <button type="button" class="btn-danger" id="deleteInspectionBtnResults">
                    <i class="fas fa-trash"></i> حذف النموذج
                </button>
                <button type="button" class="btn-primary" id="printReportBtn">
                    <i class="fas fa-print"></i> طباعة التقرير
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ✅ زر حفظ البنود في أسفل الصفحة -->
<button id="saveItemsBtn" class="bottom-save-button hidden">
    <i class="fas fa-save"></i> حفظ البنود
</button>

<div id="actionModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 400px; max-width: 90%; border-radius: 5px; direction: rtl;">
        <span id="closeModal" style="float: left; cursor: pointer; font-size: 20px;">×</span>
        <h3 id="modalTitle" style="text-align: center;">إضافة إجراء جديد</h3>
        <form id="actionForm">
            <input type="hidden" id="action_entry_id" value="">
            <input type="hidden" id="inspection_id" value="">
            <div class="form-group">
                <label for="action_name">نوع الإجراء</label>
                <select id="action_name" class="form-control" required>
                    <option value="">اختر نوع الإجراء</option>
                    <option value="مناسب">مناسب</option>
                    <option value="مخالفة">مخالفة</option>
                    <option value="انذار">انذار</option>
                    <option value="متابعة_انذار">متابعة_انذار</option>
                    <option value="مهلة_اضافية">مهلة_اضافية</option>
                    <option value="تحفظ">تحفظ</option>
                    <option value="مصادرة">مصادرة</option>
                    <option value="اغلاق مؤقت">اغلاق مؤقت</option>
                    <option value="متابعة">متابعة</option>
                    <option value="تصرف">تصرف</option>
                    <option value="اعادة_فتح">اعادة_فتح</option>
                    <option value="اتلاف">اتلاف</option>
                    <option value="مغلق">مغلق</option>
                    <option value="تقرير">تقرير</option
                </select>
            </div>
            <div class="form-group">
                <label for="action_number">رقم الإجراء</label>
                <input type="text" id="action_number" class="form-control">
            </div>
            <div class="form-group">
                <label for="action_duration_days">المدة (يوم)</label>
                <input type="number" id="action_duration_days" class="form-control" min="0">
            </div>
            <div class="form-group">
                <label for="action_status">حالة الإجراء</label>
                <select id="action_status" class="form-control">
                    <option value="active">نشط</option>
                    <option value="cancel">ملغى</option>
                    <option value="completed">مكتمل</option>
                </select>
            </div>
            <div class="form-group">
                <label for="previous_action_entry_id">الإجراء السابق (إن وجد)</label>
                <input type="number" id="previous_action_entry_id" class="form-control" min="0" placeholder="مثال: 1">
            </div>
            <div class="button-group" style="margin-top: 20px;">
                <button type="button" id="saveActionBtn" class="btn-primary">
                    <i class="fas fa-save"></i> حفظ
                </button>
                <button type="button" id="deleteActionBtn" class="btn-danger" style="display: none;">
                    <i class="fas fa-trash"></i> حذف
                </button>
                <button type="button" id="cancelActionBtn" class="btn-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('get_dropdowns.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.campaign_name) {
                const campaignSelect = document.getElementById('campaignName');
                data.data.campaign_name.forEach(name => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    campaignSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('خطأ في جلب أسماء الحملات:', error));
});
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const messageDiv = document.getElementById('message');
    const searchSection = document.getElementById('searchSection');
    const inspectionSection = document.getElementById('inspectionSection');
    const itemsSection = document.getElementById('itemsSection');
    const resultsSection = document.getElementById('resultsSection');
    const licenseNoInput = document.getElementById('licenseNo');
    const searchBtn = document.getElementById('searchBtn');
    const previousInspectionBtn = document.getElementById('previousFacilityBtn');
    const nextInspectionBtn = document.getElementById('nextFacilityBtn');
    const registerEstablishmentBtn = document.getElementById('registerEstablishmentBtn');
    const facilityInfoDiv = document.getElementById('facilityInfo');
    const facilityNameInput = document.getElementById('facilityName');
    const licenseNumberDisplay = document.getElementById('licenseNumberDisplay');
    const areaInput = document.getElementById('area');
    const activityTypeInput = document.getElementById('activityType');
    const uniqueIdInput = document.getElementById('uniqueId');
    const unitInput = document.getElementById('unit');
    const shfhspInput = document.getElementById('shfhsp');
    const hazardClassInput = document.getElementById('hazardClass');
    const sub_SectorInput = document.getElementById('sub_Sector');
    const lastInspectionDateInput = document.getElementById('lastInspectionDate');
    const lastEvaluationDateInput = document.getElementById('lastEvaluationDate');
    const evaluationBtn = document.getElementById('evaluationBtn');
    const establishmentEmailInput = document.getElementById('establishmentEmail');
    const establishmentActionButtons = document.getElementById('establishmentActionButtons');
    const editEstablishmentBtn = document.getElementById('editEstablishmentBtn');
    const evaluateEstablishmentBtn = document.getElementById('evaluateEstablishmentBtn');
    const inspectionDateInput = document.getElementById('inspectionDate');
    const inspectionTypeSelect = document.getElementById('inspectionType');
    const campaignGroup = document.getElementById('campaignGroup');
    const campaignNameInput = document.getElementById('campaignName');
    const inspectorIdInput = document.getElementById('inspectorId');
    const notesTextarea = document.getElementById('notes');
    const createInspectionBtn = document.getElementById('createInspectionBtn');
    const itemsContainer = document.getElementById('itemsContainer');
    const searchItemsInput = document.getElementById('searchItemsInput');
    const saveItemsBtn = document.getElementById('saveItemsBtn'); // ✅ زر حفظ البنود في الأسفل
    const newInspectionBtn = document.getElementById('newInspectionBtn');
    const deleteInspectionBtn = document.getElementById('deleteInspectionBtn');
    const resultInspectionId = document.getElementById('resultInspectionId');
    const resultDate = document.getElementById('resultDate');
    const resultType = document.getElementById('resultType');
    const resultDeducted = document.getElementById('resultDeducted');
    const resultScore = document.getElementById('resultScore');
    const resultPercentage = document.getElementById('resultPercentage');
    const resultGrade = document.getElementById('resultGrade');
    const resultCard = document.getElementById('resultCard');
    const resultCritical = document.getElementById('resultCritical');
    const resultMajor = document.getElementById('resultMajor');
    const resultGeneral = document.getElementById('resultGeneral');
    const resultAdministrative = document.getElementById('resultAdministrative');
    const resultNextDate = document.getElementById('resultNextDate');
    const resultNotes = document.getElementById('resultNotes');
    const totalViolationValueInput = document.getElementById('totalViolationValue');
    const approvalStatusInput = document.getElementById('approvalStatus');
    const approvedByInput = document.getElementById('approvedBy');
    const approvalDateInput = document.getElementById('approvalDate');
    const updatedByInput = document.getElementById('updatedBy');
    const approveInspectionBtn = document.getElementById('approveInspectionBtn');
    const editInspectionBtn = document.getElementById('editInspectionBtn');
    const newInspectionBtnResults = document.getElementById('newInspectionBtnResults');
    const deleteInspectionBtnResults = document.getElementById('deleteInspectionBtnResults');
    const printReportBtn = document.getElementById('printReportBtn');
    const actionsList = document.getElementById('actionsList');
    const addActionBtn = document.getElementById('addActionBtn');
    const actionModal = document.getElementById('actionModal');
    const modalTitle = document.getElementById('modalTitle');
    const actionEntryIdInput = document.getElementById('action_entry_id');
    const actionInspectionIdInput = document.getElementById('inspection_id');
    const actionNameSelect = document.getElementById('action_name');
    const actionNumberInput = document.getElementById('action_number');
    const actionDurationInput = document.getElementById('action_duration_days');
    const actionStatusSelect = document.getElementById('action_status');
    const previousActionInput = document.getElementById('previous_action_entry_id');
    const saveActionBtn = document.getElementById('saveActionBtn');
    const deleteActionBtn = document.getElementById('deleteActionBtn');
    const cancelActionBtn = document.getElementById('cancelActionBtn');
    const closeModal = document.getElementById('closeModal');
    const facilitySelectionDiv = document.getElementById('facilitySelection');
    const facilitySelector = document.getElementById('facilitySelector');
    // ✅ PDF في النتائج
    const resultsInspectionPdfFileInput = document.getElementById('resultsInspectionPdfFile');
    const resultsUploadPdfBtn = document.getElementById('resultsUploadPdfBtn');
    const resultsViewPdfBtn = document.getElementById('resultsViewPdfBtn');
    const resultsDeletePdfBtn = document.getElementById('resultsDeletePdfBtn');
    const resultsPdfPreview = document.getElementById('resultsPdfPreview');
    const resultsPdfEmbed = document.getElementById('resultsPdfEmbed');
    const resultsPdfNoPreview = document.getElementById('resultsPdfNoPreview');
    const resultsPdfLink = document.getElementById('resultsPdfLink');
    const toggleResultsPdfPreview = document.getElementById('toggleResultsPdfPreview');
    // Application Variables
    let currentInspectionId = null;
    let facilityUniqueId = null;
    let inspectionCodes = [];
    let allUserInspections = [];
    let currentInspectionIndex = -1;
    let isSpecificSearch = true;
    let searchResults = [];
    let currentResultIndex = 0;
    let allFoundFacilities = [];
    let currentFacilityIndex = 0;
    let inspectionRecords = [];
    let currentInspectionRecordIndex = 0;
    let currentPdfPath = '';
    const loggedInUserId = '<?php echo htmlspecialchars($loggedInUserId); ?>';
    const loggedInUserName = '<?php echo htmlspecialchars($loggedInUserName); ?>';
    
    // ✅ التحكم في ظهور زر حفظ البنود في الأسفل
    function toggleBottomSaveButton(show) {
        if (show) {
            saveItemsBtn.classList.remove('hidden');
        } else {
            saveItemsBtn.classList.add('hidden');
        }
    }

    // ✅ إخفاء زر الحفظ عند التمرير لأعلى وإظهاره عند التمرير لأسفل
    let lastScrollTop = 0;
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop) {
            // التمرير لأسفل - إظهار الزر
            saveItemsBtn.style.opacity = '1';
            saveItemsBtn.style.transform = 'translateX(-50%) translateY(0)';
        } else {
            // التمرير لأعلى - إخفاء الزر
            saveItemsBtn.style.opacity = '0';
            saveItemsBtn.style.transform = 'translateX(-50%) translateY(100px)';
        }
        lastScrollTop = scrollTop;
    }, { passive: true });

    // ✅ تحقق إضافي من معرف المفتش عند التحميل
    if (!loggedInUserId || loggedInUserId === 'null' || loggedInUserId === '') {
        console.error('معرف المفتش غير متاح من الجلسة');
        showMessage('خطأ: معرف المفتش غير متاح. يرجى تسجيل الدخول مرة أخرى.', false);
    } else {
        console.log('معرف المفتش المحمل من الجلسة:', loggedInUserId);
        inspectorIdInput.value = loggedInUserId;
    }

    facilitySelector.addEventListener('change', async function() {
        const selectedUniqueId = this.value;
        if (!selectedUniqueId) return;
        const selectedFacility = allFoundFacilities.find(f => f.unique_id === selectedUniqueId);
        if (selectedFacility) {
            await populateFacilityFields(selectedFacility);
            await loadInspectionsForFacility(selectedFacility.unique_id);
            facilitySelectionDiv.style.display = 'none';
            facilityInfoDiv.style.display = 'block';
            inspectionSection.style.display = 'block';
            itemsSection.style.display = 'none';
            resultsSection.style.display = 'none';
            establishmentActionButtons.style.display = 'flex';
            editEstablishmentBtn.dataset.uniqueId = selectedFacility.unique_id;
            evaluateEstablishmentBtn.dataset.uniqueId = selectedFacility.unique_id;
            showMessage('تم تحميل بيانات المنشأة المحددة', true);
        }
    });

    // Utility Functions
    function showMessage(text, isSuccess) {
        messageDiv.textContent = text;
        messageDiv.className = isSuccess ? 'success' : 'error';
        messageDiv.style.display = 'block';
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }

    function resetFormVisibility() {
        facilityInfoDiv.style.display = 'none';
        establishmentActionButtons.style.display = 'none';
        registerEstablishmentBtn.style.display = 'none';
        inspectionSection.style.display = 'none';
        itemsSection.style.display = 'none';
        resultsSection.style.display = 'none';
        facilityNameInput.value = '';
        licenseNumberDisplay.value = '';
        areaInput.value = '';
        activityTypeInput.value = '';
        uniqueIdInput.value = '';
        unitInput.value = '';
        shfhspInput.value = '';
        hazardClassInput.value = '';
        sub_SectorInput.value = '';
        lastInspectionDateInput.value = '';
        lastEvaluationDateInput.value = '';
        establishmentEmailInput.value = '';
        licenseNoInput.value = '';
        facilityUniqueId = null;
        allFoundFacilities = [];
        currentInspectionIndex = -1;
        inspectionDateInput.value = '<?php echo date('Y-m-d'); ?>';
        inspectionTypeSelect.value = '';
        campaignGroup.style.display = 'none';
        campaignNameInput.value = '';
        inspectorIdInput.value = loggedInUserId;
        notesTextarea.value = '';
        currentInspectionId = null;
        itemsContainer.innerHTML = '';
        searchItemsInput.value = '';
        resultInspectionId.textContent = '-';
        resultDate.textContent = '-';
        resultType.textContent = '-';
        resultDeducted.textContent = '0.00';
        resultScore.textContent = '1000.00';
        resultPercentage.textContent = '100%';
        resultGrade.textContent = '-';
        resultCard.textContent = '-';
        resultCritical.textContent = '0';
        resultMajor.textContent = '0';
        resultGeneral.textContent = '0';
        resultAdministrative.textContent = '0';
        resultNextDate.textContent = '-';
        resultNotes.value = '';
        totalViolationValueInput.value = '';
        approvalStatusInput.value = 'Pending';
        approvedByInput.value = '';
        approvalDateInput.value = '';
        updatedByInput.value = '';
        messageDiv.style.display = 'none';
        previousInspectionBtn.style.display = 'none';
        nextInspectionBtn.style.display = 'none';
        actionsList.innerHTML = '';
        inspectionRecords = [];
        currentInspectionRecordIndex = 0;
        currentPdfPath = '';
        resultsPdfPreview.classList.add('hidden');
        resultsPdfEmbed.style.display = 'none';
        resultsPdfNoPreview.style.display = 'block';
        resultsPdfLink.classList.add('hidden');
        resultsViewPdfBtn.classList.add('hidden');
        resultsInspectionPdfFileInput.value = '';
        
        // ✅ إخفاء زر حفظ البنود في الأسفل
        toggleBottomSaveButton(false);
    }

    // ✅ دالة لتحديث معاينة PDF المرفوع فقط مع رابط
    function updatePdfPreview(pdfPath) {
        if (pdfPath && pdfPath.trim() !== '') {
            let correctedPath = pdfPath;
            if (pdfPath.includes('uploads/inspections/uploads/inspections/')) {
                correctedPath = pdfPath.replace('uploads/inspections/uploads/inspections/', 'uploads/inspections/');
            }
          
            const fullPath = correctedPath.startsWith('http') ? correctedPath : correctedPath;
            resultsPdfEmbed.src = fullPath;
            resultsPdfLink.href = fullPath;
            resultsPdfLink.classList.remove('hidden');
            resultsPdfEmbed.style.display = 'block';
            resultsPdfNoPreview.style.display = 'none';
            resultsPdfPreview.classList.remove('hidden');
            resultsViewPdfBtn.classList.remove('hidden');
            resultsDeletePdfBtn.classList.remove('hidden');
          
            currentPdfPath = correctedPath;
        } else {
            resultsPdfEmbed.style.display = 'none';
            resultsPdfNoPreview.style.display = 'block';
            resultsPdfLink.classList.add('hidden');
            resultsPdfPreview.classList.add('hidden');
            resultsViewPdfBtn.classList.add('hidden');
            resultsDeletePdfBtn.classList.add('hidden');
        }
    }

    // ✅ معالجات PDF في النتائج
    toggleResultsPdfPreview.addEventListener('click', () => {
        const embed = document.getElementById('resultsPdfEmbed');
        const noPreview = document.getElementById('resultsPdfNoPreview');
        if (embed.style.display === 'none') {
            embed.style.display = 'block';
            noPreview.style.display = 'none';
        } else {
            embed.style.display = 'none';
            noPreview.style.display = 'block';
        }
    });

    resultsViewPdfBtn.addEventListener('click', () => {
        if (currentPdfPath) {
            window.open(currentPdfPath, '_blank');
        }
    });

    // ✅ PDF Upload Handler
    resultsUploadPdfBtn.addEventListener('click', async () => {
        const file = resultsInspectionPdfFileInput.files[0];
        if (!file) {
            showMessage('يرجى اختيار ملف PDF أولاً.', false);
            return;
        }
        if (file.size > 3 * 1024 * 1024) {
            showMessage('حجم الملف يجب أن يكون أقل من 3 ميجا.', false);
            return;
        }
        if (!currentInspectionId) {
            showMessage('الرجاء إنشاء التفتيش أولاً قبل تحميل الملف.', false);
            return;
        }
        const formData = new FormData();
        formData.append('action', 'upload_pdf');
        formData.append('inspection_id', currentInspectionId);
        formData.append('pdf_file', file);
        try {
            const response = await fetch('upload_inspection_pdf.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                let correctedPath = result.path;
                if (correctedPath.includes('uploads/inspections/uploads/inspections/')) {
                    correctedPath = correctedPath.replace('uploads/inspections/uploads/inspections/', 'uploads/inspections/');
                }
              
                currentPdfPath = correctedPath;
                updatePdfPreview(currentPdfPath);
                showMessage('تم تحميل الملف بنجاح!', true);
                resultsInspectionPdfFileInput.value = '';
            } else {
                showMessage(result.message || 'فشل تحميل الملف.', false);
            }
        } catch (error) {
            console.error('Error uploading PDF:', error);
            showMessage('حدث خطأ أثناء تحميل الملف.', false);
        }
    });

    // ✅ PDF Delete Handler
    resultsDeletePdfBtn.addEventListener('click', async () => {
        if (!currentInspectionId) {
            showMessage('لم يتم العثور على معرف التفتيش.', false);
            return;
        }
        
        if (!currentPdfPath) {
            showMessage('لا يوجد ملف PDF لحذفه.', false);
            return;
        }
        
        if (!confirm('هل أنت متأكد من حذف ملف PDF؟ لن تتمكن من استعادته.')) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_pdf');
            formData.append('inspection_id', currentInspectionId);
            formData.append('pdf_path', currentPdfPath);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                currentPdfPath = '';
                updatePdfPreview('');
                showMessage('تم حذف الملف بنجاح! يمكنك الآن رفع ملف جديد.', true);
            } else {
                showMessage(result.message || 'فشل حذف الملف.', false);
            }
        } catch (error) {
            console.error('Error deleting PDF:', error);
            showMessage('حدث خطأ أثناء حذف الملف.', false);
        }
    });

    async function searchAndLoadInspection(searchTerm, isSpecificSearch = true) {
        if (!searchTerm && isSpecificSearch) {
            showMessage('الرجاء إدخال رقم الرخصة أو المعرف الفريد أو اسم المنشأة.', false);
            resetFormVisibility();
            return;
        }
        try {
            const formData = new FormData();
            formData.append('action', 'search_establishments');
            formData.append('searchTerm', searchTerm || '');
            formData.append('isSpecificSearch', isSpecificSearch ? '1' : '0');
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success && data.data && data.data.length > 0) {
                allFoundFacilities = data.data;
                if (allFoundFacilities.length > 1) {
                    facilitySelector.innerHTML = '<option value="">-- اختر منشأة --</option>';
                    allFoundFacilities.forEach(facility => {
                        const option = document.createElement('option');
                        option.value = facility.unique_id;
                        option.textContent = `${facility.facility_name} (رخصة: ${facility.license_no} - المنطقة: ${facility.area} - المعرف: ${facility.unique_id})`;
                        facilitySelector.appendChild(option);
                    });
                    facilitySelectionDiv.style.display = 'block';
                    facilityInfoDiv.style.display = 'none';
                    inspectionSection.style.display = 'none';
                    itemsSection.style.display = 'none';
                    resultsSection.style.display = 'none';
                    establishmentActionButtons.style.display = 'none';
                    registerEstablishmentBtn.style.display = 'none';
                    showMessage('تم العثور على عدة منشآت، يرجى الاختيار', true);
                    facilityNameInput.value = '';
                    areaInput.value = '';
                    activityTypeInput.value = '';
                    uniqueIdInput.value = '';
                    facilityUniqueId = null;
                } else {
                    const facility = allFoundFacilities[0];
                    await populateFacilityFields(facility);
                    facilitySelectionDiv.style.display = 'none';
                    facilityInfoDiv.style.display = 'block';
                    await loadInspectionsForFacility(facility.unique_id);
                    inspectionSection.style.display = 'block';
                    itemsSection.style.display = 'none';
                    resultsSection.style.display = 'none';
                    establishmentActionButtons.style.display = 'flex';
                    editEstablishmentBtn.dataset.uniqueId = facility.unique_id;
                    evaluateEstablishmentBtn.dataset.uniqueId = facility.unique_id;
                    registerEstablishmentBtn.style.display = 'none';
                    showMessage('تم العثور على المنشأة بنجاح', true);
                }
            } else {
                showMessage('رقم الرخصة/المعرف الفريد غير موجود. هل ترغب بتسجيل منشأة جديدة؟', false);
                resetFormVisibility();
                registerEstablishmentBtn.style.display = 'block';
                searchSection.style.display = 'block';
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('حدث خطأ أثناء البحث', false);
            resetFormVisibility();
            registerEstablishmentBtn.style.display = 'block';
            searchSection.style.display = 'block';
        }
    }

    async function loadFacility(facility) {
        await populateFacilityFields(facility);
        facilitySelectionDiv.style.display = 'none';
        facilityInfoDiv.style.display = 'block';
        await loadInspectionsForFacility(facility.unique_id);
        inspectionSection.style.display = 'block';
        itemsSection.style.display = 'none';
        resultsSection.style.display = 'none';
        establishmentActionButtons.style.display = 'flex';
        editEstablishmentBtn.dataset.uniqueId = facility.unique_id;
        evaluateEstablishmentBtn.dataset.uniqueId = facility.unique_id;
        showMessage('تم تحميل المنشأة بنجاح', true);
    }

    // دالة البحث برقم الرخصة لجلب سجلات التفتيش
    document.getElementById('searchFullLicenseBtn').addEventListener('click', async () => {
        const licenseNo = document.getElementById('fullLicenseSearch').value.trim();
        if (!licenseNo) {
            showMessage('يرجى إدخال رقم الرخصة.', false);
            return;
        }
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: `action=search_inspections_by_license&license_no=${encodeURIComponent(licenseNo)}`
            });
            if (!response.ok) {
                throw new Error(`خطأ في الشبكة: ${response.status}`);
            }
            const data = await response.json();
            if (data.success && data.data && data.data.length > 0) {
                inspectionRecords = data.data;
                currentInspectionRecordIndex = 0;
                await displayInspectionRecord(inspectionRecords[currentInspectionRecordIndex]);
            } else {
                showMessage('لا توجد سجلات تفتيش لهذه الرخصة', false);
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('حدث خطأ أثناء جلب البيانات', false);
        }
    });

    async function displayInspectionRecord(record) {
        console.log('displayInspectionRecord called with:', record);
        try {
            if (!record || !record.facility_unique_id) {
                throw new Error('سجل التفتيش غير صالح أو لا يحتوي على معرف المنشأة');
            }
            const facilityResponse = await fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'get_facility_by_unique_id',
                    facility_unique_id: record.facility_unique_id
                })
            });
            if (!facilityResponse.ok) throw new Error(`خطأ في الشبكة: ${facilityResponse.status}`);
            const facilityData = await facilityResponse.json();
            if (!facilityData.success) throw new Error(facilityData.message || 'فشل جلب بيانات المنشأة');
            if (!facilityData.data) throw new Error('بيانات المنشأة فارغة');
            await populateFacilityFields(facilityData.data);
            if (!record.inspection_id) throw new Error('سجل التفتيش لا يحتوي على معرف التفتيش');
            currentInspectionId = record.inspection_id;
            await displayInspection(record.inspection_id);
            // إظهار الأقسام
            inspectionSection.style.display = 'block';
            itemsSection.style.display = 'block';
            resultsSection.style.display = 'block';
            // ✅ إظهار زر حفظ البنود في الأسفل
            toggleBottomSaveButton(true);
            // تحديث أزرار التنقل
            const prevBtn = document.getElementById('previousFacilityBtn');
            const nextBtn = document.getElementById('nextFacilityBtn');
            if (prevBtn && nextBtn) {
                prevBtn.style.display = 'inline-block';
                nextBtn.style.display = 'inline-block';
                updateNavigationButtons();
            }
        } catch (error) {
            console.error('Error displaying record:', error);
            showMessage(`حدث خطأ أثناء عرض بيانات السجل: ${error.message}`, false);
        }
    }

    // معالجات أحداث أزرار التنقل بين السجلات
    document.getElementById('previousFacilityBtn').addEventListener('click', async function() {
        if (currentInspectionRecordIndex > 0) {
            currentInspectionRecordIndex--;
            await displayInspectionRecord(inspectionRecords[currentInspectionRecordIndex]);
            updateNavigationButtons();
        }
    });

    document.getElementById('nextFacilityBtn').addEventListener('click', async function() {
        if (currentInspectionRecordIndex < inspectionRecords.length - 1) {
            currentInspectionRecordIndex++;
            await displayInspectionRecord(inspectionRecords[currentInspectionRecordIndex]);
            updateNavigationButtons();
        }
    });

    function updateNavigationButtons() {
        const prevBtn = document.getElementById('previousFacilityBtn');
        const nextBtn = document.getElementById('nextFacilityBtn');
        if (!prevBtn || !nextBtn) return;
        if (!inspectionRecords || inspectionRecords.length <= 1) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            return;
        }
        prevBtn.style.display = 'inline-block';
        nextBtn.style.display = 'inline-block';
        prevBtn.disabled = currentInspectionRecordIndex <= 0;
        nextBtn.disabled = currentInspectionRecordIndex >= inspectionRecords.length - 1;
    }

    async function populateFacilityFields(facility) {
        facilityNameInput.value = facility.facility_name || '';
        licenseNumberDisplay.value = facility.license_no || '';
        areaInput.value = facility.area || '';
        activityTypeInput.value = facility.activity_type || '';
        uniqueIdInput.value = facility.unique_id || '';
        facilityUniqueId = facility.unique_id;
        unitInput.value = facility.unit || '';
        shfhspInput.value = facility.shfhsp || '';
        hazardClassInput.value = facility.hazard_class || '';
        sub_SectorInput.value = facility.Sub_Sector || '';
        lastInspectionDateInput.value = facility.last_inspection_date || '';
        lastEvaluationDateInput.value = facility.last_evaluation_date || '';
        establishmentEmailInput.value = facility.email || '';
        facilityInfoDiv.style.display = 'block';
        inspectionSection.style.display = 'block';
        resultsSection.style.display = 'block';
        itemsSection.style.display = 'block';
        establishmentActionButtons.style.display = 'flex';
        // التحقق من تاريخ آخر تقييم وإظهار زر التقييم إذا غير موجود
        if (!facility.last_evaluation_date || facility.last_evaluation_date === '') {
            evaluationBtn.style.display = 'block';
        } else {
            evaluationBtn.style.display = 'none';
        }
        // ✅ إعادة تعيين معرف المفتش من الجلسة عند تحميل المنشأة
        inspectorIdInput.value = loggedInUserId;
    }

    async function loadInspectionsForFacility(uniqueId) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_facility_inspections');
            formData.append('facility_unique_id', uniqueId);
            formData.append('inspector_user_id', loggedInUserId);
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success && data.data && data.data.length > 0) {
                allUserInspections = data.data.sort((a, b) => new Date(b.inspection_date) - new Date(a.inspection_date));
                currentInspectionIndex = 0;
                displayInspection(allUserInspections[currentInspectionIndex].inspection_id);
                previousInspectionBtn.style.display = 'block';
                nextInspectionBtn.style.display = 'block';
            } else {
                allUserInspections = [];
                currentInspectionIndex = -1;
                showMessage('لا توجد تفتيشات سابقة لهذه المنشأة.', true);
                previousInspectionBtn.style.display = 'none';
                nextInspectionBtn.style.display = 'none';
            }
        } catch (error) {
            console.error('Error loading inspections for facility:', error);
            showMessage('حدث خطأ أثناء تحميل تفتيشات المنشأة.', false);
        }
    }

    async function displayInspection(inspectionId) {
        try {
            itemsContainer.innerHTML = '<p>جارٍ تحميل بيانات التفتيش...</p>';
            const formData = new FormData();
            formData.append('action', 'get_inspection_details');
            formData.append('inspection_id', inspectionId);
            formData.append('inspector_user_id', loggedInUserId);
            formData.append('load_items', '1');
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            console.log('Inspection data:', data);
            if (data.success && data.inspection) {
                const inspection = data.inspection;
                currentInspectionId = inspection.inspection_id;
                // تعبئة بيانات النموذج الأساسية
                inspectionDateInput.value = inspection.inspection_date || '';
                inspectionTypeSelect.value = inspection.inspection_type || '';
                campaignGroup.style.display = inspection.inspection_type === 'حملة' ? 'block' : 'none';
                campaignNameInput.value = inspection.inspection_type === 'حملة' ? (inspection.campaign_name || '') : '';
                inspectorIdInput.value = inspection.inspector_user_id || loggedInUserId;
                notesTextarea.value = inspection.notes || '';
                // ✅ تعبئة مسار PDF إذا كان موجوداً وتحديث المعاينة
                currentPdfPath = inspection.photo_file || '';
                updatePdfPreview(currentPdfPath);
                // تعبئة قسم النتائج بدقة
                resultInspectionId.textContent = inspection.inspection_id || '';
                resultDate.textContent = inspection.inspection_date || '';
                resultType.textContent = inspection.inspection_type || '';
                resultDeducted.textContent = parseFloat(inspection.total_deducted_points || 0).toFixed(2);
                resultScore.textContent = parseFloat(inspection.final_inspection_score || 0).toFixed(2);
                resultPercentage.textContent = (parseFloat(inspection.percentage_score || 0).toFixed(2)) + '%';
                resultGrade.textContent = inspection.letter_grade || '-';
                resultCard.textContent = inspection.color_card || '-';
                resultCritical.textContent = inspection.critical_violations || 0;
                resultMajor.textContent = inspection.major_violations || 0;
                resultGeneral.textContent = inspection.general_violations || 0;
                resultAdministrative.textContent = inspection.administrative_violations || 0;
                resultNextDate.textContent = inspection.next_inspection_date || 'غير محدد';
                resultNotes.value = inspection.notes || '';
                totalViolationValueInput.value = parseFloat(inspection.total_violation_value || 0).toFixed(2);
                approvalStatusInput.value = inspection.approval_status || 'Pending';
                approvedByInput.value = inspection.approved_by_username || '';
                approvalDateInput.value = inspection.approval_date || '';
                updatedByInput.value = inspection.updated_by_username || '';
                // إظهار الأقسام كاملة فور تحميل البيانات
                inspectionSection.style.display = 'block';
                itemsSection.style.display = 'block';
                resultsSection.style.display = 'block';
                // ✅ إظهار زر حفظ البنود في الأسفل
                toggleBottomSaveButton(true);
                // ✅ إظهار قسم PDF في وضع التعديل
                resultsPdfPreview.style.display = 'block';
                // تحميل البنود والإجراءات بشكل متوازي
                await Promise.all([
                    loadInspectionItems(true, inspectionId),
                    loadInspectionActions(inspectionId)
                ]);
                showMessage('تم تحميل بيانات التفتيش بنجاح', true);
                // تحديث أزرار التنقل بناءً على المؤشر الحالي في المصفوفة
                if (allUserInspections.length > 0) {
                    previousInspectionBtn.disabled = (currentInspectionIndex <= 0);
                    nextInspectionBtn.disabled = (currentInspectionIndex >= allUserInspections.length - 1);
                }
            } else {
                showMessage(data.message || 'فشل جلب بيانات التفتيش', false);
                resetFormVisibility();
            }
        } catch (error) {
            console.error('خطأ في عرض التفتيش:', error);
            showMessage('حدث خطأ أثناء تحميل بيانات التفتيش', false);
            itemsContainer.innerHTML = '<p style="color:red;">خطأ في تحميل البيانات</p>';
        }
    }

    async function loadInspectionActions(inspectionId) {
        try {
            actionsList.innerHTML = '<tr><td colspan="6" style="text-align: center;">جارٍ تحميل الإجراءات...</td></tr>';
            const response = await fetch(`inspection_actions_api.php?action=get_all&inspection_id=${inspectionId}`);
            const data = await response.json();
            console.log('Actions data:', data);
            if (data.success && data.actions && data.actions.length > 0) {
                actionsList.innerHTML = '';
                const sortedActions = data.actions.sort((a, b) =>
                    new Date(b.created_at) - new Date(a.created_at));
                sortedActions.forEach(action => {
                    const row = document.createElement('tr');
                    if (action.action_status === 'cancel') {
                        row.classList.add('canceled-action');
                    }
                    row.innerHTML = `
                        <td>${action.action_name}</td>
                        <td>${action.action_number || '-'}</td>
                        <td>${action.action_duration_days || '-'}</td>
                        <td>${getActionStatusText(action.action_status)}</td>
                        <td>${action.previous_action_entry_id || '-'}</td>
                        <td>
                            <button type="button" class="btn-secondary edit-action-btn"
                                    data-action-id="${action.action_entry_id}"
                                    style="padding: 3px 8px; font-size: 12px;">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    `;
                    actionsList.appendChild(row);
                });
                document.querySelectorAll('.edit-action-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const actionId = this.getAttribute('data-action-id');
                        openActionModal(actionId);
                    });
                });
            } else {
                actionsList.innerHTML = '<tr><td colspan="6" style="text-align: center;">لا توجد إجراءات مسجلة</td></tr>';
            }
        } catch (error) {
            console.error('خطأ في تحميل الإجراءات:', error);
            actionsList.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">حدث خطأ أثناء تحميل الإجراءات</td></tr>';
        }
    }

    function getActionStatusText(status) {
        switch(status) {
            case 'active': return 'نشط';
            case 'cancel': return 'ملغى';
            case 'completed': return 'مكتمل';
            default: return status;
        }
    }

    function openActionModal(actionId = null) {
        if (actionId) {
            modalTitle.textContent = 'تعديل الإجراء';
            deleteActionBtn.style.display = 'block';
            fetch(`inspection_actions_api.php?action=get&action_entry_id=${actionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        actionEntryIdInput.value = data.action.action_entry_id;
                        actionInspectionIdInput.value = data.action.inspection_id;
                        actionNameSelect.value = data.action.action_name;
                        actionNumberInput.value = data.action.action_number || '';
                        actionDurationInput.value = data.action.action_duration_days || '';
                        actionStatusSelect.value = data.action.action_status || 'active';
                        previousActionInput.value = data.action.previous_action_entry_id || '';
                    } else {
                        showMessage('فشل تحميل بيانات الإجراء', false);
                    }
                });
        } else {
            modalTitle.textContent = 'إضافة إجراء جديد';
            deleteActionBtn.style.display = 'none';
            actionEntryIdInput.value = '';
            actionInspectionIdInput.value = currentInspectionId;
            actionNameSelect.value = '';
            actionNumberInput.value = '';
            actionDurationInput.value = '';
            actionStatusSelect.value = 'active';
            previousActionInput.value = '';
        }
        actionModal.style.display = 'block';
    }

    function closeActionModal() {
        actionModal.style.display = 'none';
    }

    async function saveAction() {
        const formData = new FormData();
        formData.append('action', actionEntryIdInput.value ? 'update' : 'create');
        formData.append('action_entry_id', actionEntryIdInput.value);
        formData.append('inspection_id', actionInspectionIdInput.value);
        formData.append('action_name', actionNameSelect.value);
        formData.append('action_number', actionNumberInput.value);
        formData.append('action_duration_days', actionDurationInput.value);
        formData.append('action_status', actionStatusSelect.value);
        formData.append('previous_action_entry_id', previousActionInput.value);
        try {
            const response = await fetch('inspection_actions_api.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                showMessage('تم حفظ الإجراء بنجاح', true);
                closeActionModal();
                await loadInspectionActions(currentInspectionId);
            } else {
                showMessage(data.message || 'فشل حفظ الإجراء', false);
            }
        } catch (error) {
            console.error('Error saving action:', error);
            showMessage('حدث خطأ أثناء حفظ الإجراء', false);
        }
    }

    async function deleteAction() {
        const actionId = actionEntryIdInput.value;
        if (!actionId) return;
        if (confirm('هل أنت متأكد من حذف هذا الإجراء؟')) {
            try {
                const response = await fetch('inspection_actions_api.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'delete',
                        action_entry_id: actionId
                    })
                });
                const data = await response.json();
                if (data.success) {
                    showMessage('تم حذف الإجراء بنجاح', true);
                    closeActionModal();
                    await loadInspectionActions(currentInspectionId);
                } else {
                    showMessage(data.message || 'فشل حذف الإجراء', false);
                }
            } catch (error) {
                console.error('Error deleting action:', error);
                showMessage('حدث خطأ أثناء حذف الإجراء', false);
            }
        }
    }

    searchBtn.addEventListener('click', () => searchAndLoadInspection(licenseNoInput.value.trim()));
    previousInspectionBtn.addEventListener('click', () => {
        if (currentInspectionIndex < allUserInspections.length - 1) {
            currentInspectionIndex++;
            displayInspection(allUserInspections[currentInspectionIndex].inspection_id);
        }
    });
    nextInspectionBtn.addEventListener('click', () => {
        if (currentInspectionIndex > 0) {
            currentInspectionIndex--;
            displayInspection(allUserInspections[currentInspectionIndex].inspection_id);
        }
    });
    registerEstablishmentBtn.addEventListener('click', () => {
        const licenseNo = licenseNoInput.value.trim();
        window.location.href = `form_est.php?license_no=${encodeURIComponent(licenseNo)}`;
    });
    editEstablishmentBtn.addEventListener('click', () => {
        const uniqueId = uniqueIdInput.value;
        if (uniqueId) {
            window.location.href = `form_est.php?unique_id=${encodeURIComponent(uniqueId)}`;
        } else {
            showMessage('لا يمكن تعديل المنشأة، المعرف الفريد غير موجود.', false);
        }
    });
    evaluateEstablishmentBtn.addEventListener('click', () => {
        const uniqueId = uniqueIdInput.value;
        if (uniqueId) {
            window.location.href = `evaluation_form.php?unique_id=${encodeURIComponent(uniqueId)}`;
        } else {
            showMessage('لا يمكن تقييم المنشأة، المعرف الفريد غير موجود.', false);
        }
    });
    evaluationBtn.addEventListener('click', () => {
        const uniqueId = uniqueIdInput.value;
        if (uniqueId) {
            window.location.href = `/shjfcs/evaluation_form.php?unique_id=${encodeURIComponent(uniqueId)}`;
        } else {
            showMessage('لا يمكن تقييم المنشأة، المعرف الفريد غير موجود.', false);
        }
    });
    inspectionTypeSelect.addEventListener('change', function() {
        if (this.value === 'حملة') {
            campaignGroup.style.display = 'block';
        } else {
            campaignGroup.style.display = 'none';
            campaignNameInput.value = '';
        }
    });
    createInspectionBtn.addEventListener('click', async function() {
        if (!facilityUniqueId) {
            showMessage('الرجاء البحث عن منشأة أولاً وتحديدها', false);
            return;
        }
        if (!inspectionDateInput.value || !inspectionTypeSelect.value) {
            showMessage('الرجاء تعبئة جميع الحقول المطلوبة', false);
            return;
        }
        // ✅ تحقق إضافي من معرف المفتش
        const inspectorId = inspectorIdInput.value.trim();
        if (!inspectorId || inspectorId === '0' || inspectorId === '') {
            showMessage('خطأ: معرف المفتش غير صالح. تأكد من تسجيل الدخول.', false);
            console.error('معرف المفتش غير صالح:', inspectorId);
            return;
        }
        console.log('إرسال معرف المفتش:', inspectorId);
        try {
            const formData = new FormData();
            formData.append('action', 'create_inspection');
            formData.append('facility_unique_id', facilityUniqueId);
            formData.append('inspection_date', inspectionDateInput.value);
            formData.append('inspection_type', inspectionTypeSelect.value);
            formData.append('inspector_user_id', inspectorId);
            formData.append('photo_file', currentPdfPath);
            if (inspectionTypeSelect.value === 'حملة' && campaignNameInput.value) {
                formData.append('campaign_name', campaignNameInput.value);
            }
            if (notesTextarea.value) {
                formData.append('notes', notesTextarea.value);
            }
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            console.log('استجابة إنشاء التفتيش:', data);
            if (data.success) {
                currentInspectionId = data.inspection_id;
                resultInspectionId.textContent = currentInspectionId;
                resultDate.textContent = inspectionDateInput.value;
                resultType.textContent = inspectionTypeSelect.value;
                resultNotes.value = notesTextarea.value;
                inspectionSection.style.display = 'none';
                itemsSection.style.display = 'block';
                resultsSection.style.display = 'none';
                // ✅ إظهار زر حفظ البنود في الأسفل
                toggleBottomSaveButton(true);
                await loadInspectionItems(false, null);
                showMessage('تم إنشاء التفتيش بنجاح', true);
            } else {
                showMessage(data.message || 'فشل إنشاء التفتيش', false);
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('حدث خطأ أثناء إنشاء التفتيش', false);
        }
    });

    async function loadInspectionItems(isEditingExisting = false, inspectionIdToLoad = null) {
        itemsContainer.innerHTML = 'جارٍ تحميل بنود التفتيش...';
        try {
            const formData = new FormData();
            formData.append('action', 'get_inspection_codes');
            if (!isEditingExisting) {
                formData.append('load_all', '1');
            } else {
                formData.append('facility_unique_id', facilityUniqueId);
                if (inspectionIdToLoad) {
                    formData.append('inspection_id', inspectionIdToLoad);
                }
            }
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success && data.data && data.data.length > 0) {
                inspectionCodes = data.data;
                renderInspectionItems();
            } else {
                itemsContainer.innerHTML = '<p>لا توجد بنود تفتيش متاحة</p>';
                showMessage('لا توجد بنود تفتيش', false);
            }
        } catch (error) {
            console.error('Error:', error);
            itemsContainer.innerHTML = '<p style="color:red;">حدث خطأ أثناء تحميل البنود</p>';
            showMessage('حدث خطأ أثناء تحميل بنود التفتيش', false);
        }
    }

    async function renderInspectionItems() {
        const paginatedCodes = inspectionCodes;
        itemsContainer.innerHTML = '';
        for (const item of paginatedCodes) {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'item-row';
            let previousViolationIndicator = '';
            let isRepeatedViolation = false;
            let repeatedCount = 0;
            try {
                const prevViolationsForm = new FormData();
                prevViolationsForm.append('action', 'check_previous_violation');
                prevViolationsForm.append('facility_unique_id', facilityUniqueId);
                prevViolationsForm.append('code_id', item.code_id);
                const prevViolationsResponse = await fetch('api.php', {
                    method: 'POST',
                    body: prevViolationsForm
                });
                const prevViolationsData = await prevViolationsResponse.json();
                if (prevViolationsData.success) {
                    isRepeatedViolation = prevViolationsData.is_repeated_violation;
                    repeatedCount = prevViolationsData.repeated_count || 0;
                    if (isRepeatedViolation) {
                        previousViolationIndicator = '<span class="previous-violation-indicator">مخالفة سابقة</span>';
                    }
                }
            } catch (error) {
                console.error('Error checking previous violation:', error);
            }
            const existingItemData = item.inspection_item_data || {};
            const isViolationChecked = existingItemData.is_violation == 1;
            const preselectedAction = existingItemData.action_taken ?? '';
            const preselectedConditionLevel = existingItemData.condition_level ?? '';
            const preselectedDeductedPoints = existingItemData.deducted_points
                ? parseFloat(existingItemData.deducted_points).toFixed(2)
                : '0.00';
            const preselectedViolationValue = existingItemData.violation_value ?? '';
            const preselectedNotes = existingItemData.inspector_notes ?? '';
            const preselectedPhotoPath = existingItemData.inspection_photo_path ?? '';
            itemDiv.innerHTML = `
                <div class="item-header">
                    <p><strong>${item.code_id} - ${item.code_description}</strong> ${previousViolationIndicator}</p>
                    <div class="violation-toggle-group">
                        <label class="switch">
                            <input type="checkbox" id="isViolation_${item.code_id}" class="is-violation-checkbox" data-code-id="${item.code_id}" ${isViolationChecked ? 'checked' : ''}>
                            <span class="slider round"></span>
                        </label>
                        <span>عدم مطابقة؟</span>
                    </div>
                </div>
                <div class="item-details-expanded">
                    <p><strong>الفئة:</strong> ${item.code_category || 'N/A'}</p>
                    <p><strong>تصنيف البند:</strong> ${item.code_categorized || 'N/A'}</p>
                    <p><strong> الإجراء الافتراضي:</strong> ${item.default_action_type || 'N/A'}</p>
                    <p><strong>الإجراء التصحيحي:</strong> ${item.fixed_corrective_action || 'N/A'}</p>
                    <p><strong>قيمة المخالفة:</strong> ${item.violation_value_text || 'N/A'}</p>
                    <p><strong>المراجع:</strong> ${item.standard_reference || 'N/A'}</p>
                </div>
                <div class="violation-details ${isViolationChecked ? '' : 'hidden'}">
                    <div class="form-group">
                        <label>الإجراء المتخذ</label>
                        <select class="action-select" data-code-id="${item.code_id}" data-code-category="${item.code_category}" data-is-repeated-violation="${isRepeatedViolation ? '1' : '0'}" data-initial-repeated-count="${repeatedCount}">
                            <option value="">-- اختر الإجراء --</option>
                            <option value="لا يوجد إجراء" ${preselectedAction === 'لا يوجد إجراء' ? 'selected' : ''}>لا يوجد إجراء</option>
                            <option value="إجراء تصحيحي" ${preselectedAction === 'إجراء تصحيحي' ? 'selected' : ''}>إجراء تصحيحي</option>
                            <option value="انذار" ${preselectedAction === 'انذار' ? 'selected' : ''}>انذار</option>
                            <option value="مخالفة" ${preselectedAction === 'مخالفة' ? 'selected' : ''}>مخالفة</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>مستوى الحالة</label>
                        <input type="text" class="condition-level-display" data-code-id="${item.code_id}"
                               value="${preselectedConditionLevel}" readonly class="readonly">
                    </div>
                    <div class="form-group">
                        <label>النقاط المخصومة</label>
                        <input type="number" class="points-input" data-code-id="${item.code_id}"
                               value="${preselectedDeductedPoints}" step="0.01" readonly class="readonly">
                    </div>
                    <div class="form-group">
                        <label>عدد التكرارات السابقة</label>
                        <input type="text" class="repeated-count-display" data-code-id="${item.code_id}"
                               value="${repeatedCount}" readonly class="readonly">
                    </div>
                    <div class="form-group violation-value-group ${preselectedAction === 'مخالفة' ? '' : 'hidden'}">
                        <label>قيمة المخالفة</label>
                        <input type="number" class="violation-value-input" data-code-id="${item.code_id}"
                               placeholder="أدخل قيمة المخالفة" step="0.01" value="${preselectedViolationValue}">
                    </div>
                    <div class="form-group">
                        <label>صورة التفتيش</label>
                        <input type="file" accept="image/*" capture="environment" class="inspection-photo-input" data-code-id="${item.code_id}" style="display: none;">
                        <button type="button" class="capture-photo-btn" data-code-id="${item.code_id}" data-inspection-id="${currentInspectionId}">التقاط صورة</button>
                        <div class="photo-preview-container" data-code-id="${item.code_id}" data-image-path="${preselectedPhotoPath}">
                            ${preselectedPhotoPath ? `<img src="${preselectedPhotoPath}" alt="Inspection Photo">` : ''}
                        </div>
                    </div>
                    <div class="form-group">
                        <label>ملاحظات المفتش</label>
                        <textarea class="notes-input" data-code-id="${item.code_id}"
                                  placeholder="ملاحظات إضافية">${preselectedNotes}</textarea>
                    </div>
                </div>
            `;
            itemsContainer.appendChild(itemDiv);
            const isViolationCheckbox = itemDiv.querySelector('.is-violation-checkbox');
            const violationDetailsDiv = itemDiv.querySelector('.violation-details');
            const actionSelect = itemDiv.querySelector('.action-select');
            const conditionLevelDisplay = itemDiv.querySelector('.condition-level-display');
            const pointsInput = itemDiv.querySelector('.points-input');
            const violationValueGroup = itemDiv.querySelector('.violation-value-group');
            const violationValueInput = itemDiv.querySelector('.violation-value-input');
            const notesInput = itemDiv.querySelector('.notes-input');
            const capturePhotoBtn = itemDiv.querySelector('.capture-photo-btn');
            const inspectionPhotoInput = itemDiv.querySelector('.inspection-photo-input');
            const photoPreviewContainer = itemDiv.querySelector('.photo-preview-container');
            function toggleViolationFields(enable) {
                actionSelect.disabled = !enable;
                notesInput.disabled = !enable;
                capturePhotoBtn.disabled = !enable;
                violationValueInput.disabled = !enable;
                if (!enable) {
                    actionSelect.value = '';
                    conditionLevelDisplay.value = '';
                    pointsInput.value = '0.00';
                    pointsInput.dataset.conditionLevel = '';
                    violationValueGroup.style.display = 'none';
                    violationValueInput.value = '';
                    notesInput.value = '';
                    photoPreviewContainer.innerHTML = '';
                } else {
                    const currentAction = actionSelect.value;
                    if (currentAction === 'مخالفة') {
                        violationValueGroup.style.display = 'block';
                    } else {
                        violationValueGroup.style.display = 'none';
                    }
                }
            }
            isViolationCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    violationDetailsDiv.style.display = 'block';
                    toggleViolationFields(true);
                    actionSelect.dispatchEvent(new Event('change'));
                } else {
                    violationDetailsDiv.style.display = 'none';
                    toggleViolationFields(false);
                }
            });
            toggleViolationFields(isViolationChecked);
            actionSelect.dispatchEvent(new Event('change'));
            actionSelect.addEventListener('change', function() {
                const selectedAction = this.value;
                const codeCategory = this.dataset.codeCategory;
                const isRepeated = this.dataset.isRepeatedViolation === '1';
                let conditionLevel = '';
                violationValueGroup.style.display = 'none';
                violationValueInput.disabled = true;
                if (selectedAction) {
                    let points = 0;
                    if (selectedAction === 'إجراء تصحيحي') {
                        conditionLevel = 'Condition I';
                        points = 0;
                    } else if (selectedAction === 'انذار') {
                        conditionLevel = 'Condition II';
                        points = calculatePoints(codeCategory, conditionLevel);
                    } else if (selectedAction === 'مخالفة') {
                        if (isRepeated) {
                            conditionLevel = 'Condition V';
                        } else {
                            if (codeCategory === 'Critical') {
                                conditionLevel = 'Condition IV';
                            } else if (codeCategory === 'Major' || codeCategory === 'General') {
                                conditionLevel = 'Condition III';
                            } else {
                                conditionLevel = 'Condition III';
                            }
                        }
                        points = calculatePoints(codeCategory, conditionLevel);
                        violationValueGroup.style.display = 'block';
                        violationValueInput.disabled = false;
                    } else {
                        conditionLevel = 'N/A';
                        points = 0;
                    }
                    conditionLevelDisplay.value = conditionLevel;
                    pointsInput.value = points.toFixed(2);
                    pointsInput.dataset.conditionLevel = conditionLevel;
                } else {
                    conditionLevelDisplay.value = '';
                    pointsInput.value = '0.00';
                    pointsInput.dataset.conditionLevel = '';
                }
            });
            capturePhotoBtn.addEventListener('click', function () {
                inspectionPhotoInput.click();
            });
            inspectionPhotoInput.addEventListener('change', async function (event) {
                const file = event.target.files[0];
                if (!file) return;
                const codeId = this.dataset.codeId;
                if (!currentInspectionId) {
                    showMessage('الرجاء إنشاء التفتيش أولاً قبل التقاط الصور.', false);
                    return;
                }
                const fileName = `inspection_${currentInspectionId}_code_${codeId}_${Date.now()}.jpg`;
                const uploadUrl = 'upload_inspection_image.php';
                try {
                    const resizedImage = await resizeImage(file, 800, 600);
                    const formData = new FormData();
                    formData.append('image', resizedImage, fileName);
                    formData.append('inspection_id', currentInspectionId);
                    formData.append('code_id', codeId);
                    formData.append('action', 'upload');
                    const response = await fetch(uploadUrl, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showMessage('تم رفع الصورة بنجاح!', true);
                        photoPreviewContainer.innerHTML = `
                            <img src="${result.path}" alt="Inspection Photo">
                            <div class="photo-actions">
                                <button type="button" class="btn-secondary view-photo-btn" data-photo-path="${result.path}">عرض</button>
                                <button type="button" class="btn-danger delete-photo-btn" data-code-id="${codeId}">حذف</button>
                            </div>
                        `;
                        photoPreviewContainer.dataset.imagePath = result.path;
                        photoPreviewContainer.querySelector('.view-photo-btn').addEventListener('click', function () {
                            window.open(this.dataset.photoPath, '_blank');
                        });
                        photoPreviewContainer.querySelector('.delete-photo-btn').addEventListener('click', async function () {
                            const codeId = this.dataset.codeId;
                            const formData = new FormData();
                            formData.append('action', 'delete');
                            formData.append('inspection_id', currentInspectionId);
                            formData.append('code_id', codeId);
                            if (confirm('هل أنت متأكد أنك تريد حذف هذه الصورة؟')) {
                                const response = await fetch('upload_inspection_image.php', {
                                    method: 'POST',
                                    body: formData
                                });
                                const result = await response.json();
                                if (result.success) {
                                    showMessage('تم حذف الصورة بنجاح', true);
                                    photoPreviewContainer.innerHTML = '';
                                    photoPreviewContainer.dataset.imagePath = '';
                                } else {
                                    showMessage('فشل حذف الصورة: ' + (result.message || 'خطأ غير معروف'), false);
                                }
                            }
                        });
                    } else {
                        showMessage(`فشل رفع الصورة: ${result.message || 'خطأ غير معروف'}`, false);
                    }
                } catch (error) {
                    console.error('Error uploading image:', error);
                    showMessage('حدث خطأ أثناء رفع الصورة', false);
                }
            });
        }
    }

    function resizeImage(file, maxWidth, maxHeight) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;
                    if (width > height) {
                        if (width > maxWidth) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        }
                    } else {
                        if (height > maxHeight) {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                        if (width < maxWidth && height < maxHeight) {
                            width = img.width;
                            height = img.height;
                        }
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    canvas.toBlob((blob) => {
                        resolve(blob);
                    }, 'image/jpeg', 0.8);
                };
            };
        });
    }

    function calculatePoints(category, condition) {
        const rules = {
            'Critical': { 'Condition I': 0, 'Condition II': 175, 'Condition III': 250, 'Condition IV': 300, 'Condition V': 400 },
            'Major': { 'Condition I': 0, 'Condition II': 120, 'Condition III': 150, 'Condition IV': 200, 'Condition V': 250 },
            'General': { 'Condition I': 0, 'Condition II': 50, 'Condition III': 75, 'Condition IV': 100, 'Condition V': 150 },
            'Administrative': { 'Condition I': 0, 'Condition II': 0, 'Condition III': 0, 'Condition IV': 0, 'Condition V': 0 }
        };
        return rules[category]?.[condition] || 0;
    }

    // ✅ فلترة بنود التفتيش بناءً على البحث
    searchItemsInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const allItemRows = document.querySelectorAll('.item-row');
        let visibleCount = 0;
        allItemRows.forEach(row => {
            const codeIdElement = row.querySelector('.is-violation-checkbox');
            const codeId = codeIdElement ? codeIdElement.dataset.codeId : '';
            if (codeId.toLowerCase().includes(searchTerm)) {
                row.style.display = 'block';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        if (!searchTerm) {
            allItemRows.forEach(row => row.style.display = 'block');
        } else {
            showMessage(`تم العثور على ${visibleCount} نتيجة للبحث`, true);
        }
    });

    // ✅ زر حفظ البنود في أسفل الصفحة
saveItemsBtn.addEventListener('click', async function() {
    if (!currentInspectionId) {
        showMessage('لم يتم إنشاء التفتيش بعد', false);
        return;
    }
    const itemsToSave = [];
    const itemRows = document.querySelectorAll('.item-row');
    itemRows.forEach(row => {
        const codeId = parseInt(row.querySelector('.is-violation-checkbox').dataset.codeId, 10) || 0; // parseInt
        const isViolationCheckbox = row.querySelector('.is-violation-checkbox');
        const isViolation = !!isViolationCheckbox.checked;
        let actionTaken = 'لا يوجد إجراء';
        let conditionLevel = 'N/A';
        let deductedPoints = 0.00;
        let violationValue = null;
        let inspectorNotes = '';
        let inspectionPhotoPath = '';
        const actionSelect = row.querySelector('.action-select');
        if (actionSelect) {
            actionTaken = actionSelect.value || 'لا يوجد إجراء';
        }
        const pointsInput = row.querySelector('.points-input');
        if (pointsInput) {
            deductedPoints = parseFloat(pointsInput.value) || 0.00;
            const conditionLevelDisplay = row.querySelector('.condition-level-display');
            if (conditionLevelDisplay) {
                conditionLevel = conditionLevelDisplay.value || 'N/A';
            } else if (pointsInput.dataset.conditionLevel) {
                conditionLevel = pointsInput.dataset.conditionLevel;
            }
        }
        const violationValueInput = row.querySelector('.violation-value-input');
        if (violationValueInput) {
            violationValue = (actionTaken === 'مخالفة') ? (parseFloat(violationValueInput.value) || 0) : null;
        }
        const notesInput = row.querySelector('.notes-input');
        if (notesInput) {
            inspectorNotes = notesInput.value || '';
        }
        const photoPreviewContainer = row.querySelector('.photo-preview-container');
        if (photoPreviewContainer) {
            inspectionPhotoPath = (photoPreviewContainer.dataset.imagePath || '').trim();
            if (!inspectionPhotoPath) {
                const imgElement = photoPreviewContainer.querySelector('img');
                if (imgElement && imgElement.src && !imgElement.src.includes('placeholder.png') && imgElement.src.trim() !== '') {
                    try {
                        inspectionPhotoPath = new URL(imgElement.src).pathname;
                    } catch (e) {
                        inspectionPhotoPath = imgElement.src; // fallback
                    }
                }
            }
        }
        if (isViolation || inspectionPhotoPath.trim() !== '' || inspectorNotes.trim() !== '') {
            itemsToSave.push({
                code_id: codeId,
                is_violation: isViolation ? 1 : 0,
                action_taken: actionTaken,
                condition_level: conditionLevel,
                deducted_points: deductedPoints,
                violation_value: violationValue,
                inspector_notes: inspectorNotes,
                inspection_photo_path: inspectionPhotoPath // <-- use the name the server expects
            });
        }
    });

    const generalNotes = notesTextarea.value;
    const inspectionType = inspectionTypeSelect.value;
    const campaignName = campaignNameInput.value;
    const inspectorId = inspectorIdInput.value;

    // ====== NEW: get and normalize inspection_date (YYYY-MM-DD) ======
    let inspectionDate = '';
    if (typeof inspectionDateInput !== 'undefined' && inspectionDateInput) {
        // assume you have an <input id="inspectionDateInput" type="date"> or similar
        inspectionDate = inspectionDateInput.value || '';
        inspectionDate = inspectionDate.trim();
        if (inspectionDate) {
            // normalize different possible inputs to YYYY-MM-DD
            const ts = Date.parse(inspectionDate);
            if (!isNaN(ts)) {
                const d = new Date(ts);
                const yyyy = d.getFullYear();
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const dd = String(d.getDate()).padStart(2, '0');
                inspectionDate = `${yyyy}-${mm}-${dd}`;
            } else {
                // invalid -> clear to avoid server validation failure
                inspectionDate = '';
            }
        }
    }
    // =================================================================

    try {
        const formData = new FormData();
        formData.append('action', 'save_inspection_items');
        formData.append('inspection_id', currentInspectionId);
        formData.append('items_data', JSON.stringify(itemsToSave));
        formData.append('notes', generalNotes);
        formData.append('inspection_type', inspectionType);
        formData.append('campaign_name', campaignName);
        formData.append('inspector_user_id', inspectorId);
        formData.append('updated_by_user_id', loggedInUserId);
        formData.append('photo_file', currentPdfPath);

        // ====== NEW: append inspection_date only if present (server validates format) ======
        if (inspectionDate) {
            formData.append('inspection_date', inspectionDate);
        }
        // =================================================================

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            resultDeducted.textContent = parseFloat(data.results.total_deducted_points || 0).toFixed(2);
            resultScore.textContent = parseFloat(data.results.final_inspection_score || 0).toFixed(2);
            resultPercentage.textContent = parseFloat(data.results.percentage_score || 0).toFixed(2) + '%';
            resultGrade.textContent = data.results.letter_grade;
            resultCard.textContent = data.results.color_card;
            resultCritical.textContent = data.results.critical_violations || 0;
            resultMajor.textContent = data.results.major_violations || 0;
            resultGeneral.textContent = data.results.general_violations || 0;
            resultAdministrative.textContent = data.results.administrative_violations || 0;
            resultNextDate.textContent = data.results.next_inspection_date || 'غير محدد';
            totalViolationValueInput.value = parseFloat(data.results.total_violation_value || 0).toFixed(2);
            approvalStatusInput.value = data.results.approval_status || 'Pending';
            approvedByInput.value = data.results.approved_by_username || '';
            approvalDateInput.value = data.results.approval_date || '';
            updatedByInput.value = data.results.updated_by_username || loggedInUserName;
            resultNotes.value = generalNotes;
            updatePdfPreview(currentPdfPath);
            if (parseFloat(data.results.total_violation_value || 0) > 5000) {
                totalViolationValueInput.classList.add('high-violation');
            } else {
                totalViolationValueInput.classList.remove('high-violation');
            }
            showMessage('تم حفظ البنود وتحديث التفتيش بنجاح', true);
            approveInspectionBtn.disabled = false;
            editInspectionBtn.disabled = false;
        } else {
            showMessage(data.message || 'فشل حفظ البنود', false);
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('حدث خطأ أثناء حفظ البنود', false);
    }
});

    approveInspectionBtn.addEventListener('click', async function() {
        if (!currentInspectionId) {
            showMessage('لا يوجد تفتيش لاعتماده.', false);
            return;
        }
        if (confirm('هل أنت متأكد أنك تريد اعتماد هذا التفتيش؟')) {
            try {
                const formData = new FormData();
                formData.append('action', 'approve_inspection');
                formData.append('inspection_id', currentInspectionId);
                formData.append('approved_by_user_id', loggedInUserId);
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    approvalStatusInput.value = data.approval_status || 'Approved';
                    approvedByInput.value = data.approved_by_username || loggedInUserName;
                    approvalDateInput.value = data.approval_date || new Date().toISOString().slice(0,10);
                    updatedByInput.value = data.updated_by_username || loggedInUserName;
                    showMessage('تم اعتماد التفتيش بنجاح!', true);
                } else {
                    showMessage(data.message || 'فشل اعتماد التفتيش.', false);
                }
            } catch (error) {
                console.error('Error approving inspection:', error);
                showMessage('حدث خطأ أثناء اعتماد التفتيش.', false);
            }
        }
    });

    editInspectionBtn.addEventListener('click', function() {
        if (!currentInspectionId) {
            showMessage('لا يوجد تفتيش لتعديله.', false);
            return;
        }
        resultsSection.style.display = 'none';
        inspectionSection.style.display = 'block';
        itemsSection.style.display = 'block';
        showMessage('أصبح النموذج قابلاً للتعديل الآن.', true);
    });

    newInspectionBtnResults.addEventListener('click', resetFormVisibility);

    async function handleDeleteInspection() {
        if (!currentInspectionId) {
            showMessage('لا يوجد نموذج تفتيش لحذفه.', false);
            return;
        }
        if (confirm('هل أنت متأكد أنك تريد حذف نموذج التفتيش هذا؟ سيؤدي هذا إلى حذف جميع البيانات المتعلقة بهذا التفتيش.')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_inspection');
                formData.append('inspection_id', currentInspectionId);
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showMessage('تم حذف النموذج بنجاح.', true);
                    resetFormVisibility();
                } else {
                    showMessage(data.message || 'فشل حذف النموذج.', false);
                }
            } catch (error) {
                console.error('Error deleting inspection:', error);
                showMessage('حدث خطأ أثناء حذف النموذج.', false);
            }
        }
    }

    deleteInspectionBtnResults.addEventListener('click', handleDeleteInspection);

    printReportBtn.addEventListener('click', function() {
        if (!currentInspectionId) {
            showMessage('لا يوجد تفتيش لطباعة تقرير له.', false);
            return;
        }
        window.open(`generate_report.php?inspection_id=${currentInspectionId}`, '_blank');
    });

    // Action Management Event Listeners
    addActionBtn.addEventListener('click', function() {
        openActionModal();
    });
    saveActionBtn.addEventListener('click', saveAction);
    deleteActionBtn.addEventListener('click', deleteAction);
    cancelActionBtn.addEventListener('click', closeActionModal);
    closeModal.addEventListener('click', closeActionModal);

    // Initialize the form
    resetFormVisibility();
    
    const urlParams = new URLSearchParams(window.location.search);
    const initialUniqueId = urlParams.get('unique_id');
    const initialInspectionId = urlParams.get('inspection_id');
    if (initialInspectionId) {
        displayInspection(initialInspectionId);
    } else if (initialUniqueId) {
        licenseNoInput.value = initialUniqueId;
        searchAndLoadInspection(initialUniqueId);
    }
});
</script>
</body>
</html>