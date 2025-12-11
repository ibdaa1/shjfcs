# Testing Checklist - Inspection Form Updates

## Pre-Testing Setup ⚙️

### 1. Database Setup
- [ ] Run SQL migration: `mysql -u username -p database_name < shjfcs/create_attachments_table.sql`
- [ ] Verify table created: `SHOW TABLES LIKE 'tbl_inspection_attachments';`
- [ ] Verify table structure: `DESCRIBE tbl_inspection_attachments;`

### 2. File Permissions
- [ ] Create directory: `mkdir -p shjfcs/uploads/inspections/attachments/`
- [ ] Set permissions: `chmod 755 shjfcs/uploads/inspections/attachments/`
- [ ] Verify writable: `touch shjfcs/uploads/inspections/attachments/test.txt && rm shjfcs/uploads/inspections/attachments/test.txt`

### 3. PHP Configuration
- [ ] Check PHP version: `php -v` (must be >= 7.4)
- [ ] Verify file upload settings in php.ini:
  - `upload_max_filesize = 10M`
  - `post_max_size = 20M`
  - `file_uploads = On`

---

## Feature 1: New Inspection Button ➕

### Test Case 1.1: Button Visibility
- [ ] Open form_inspection.php
- [ ] Search for existing license
- [ ] **Expected:** "New Inspection" button is hidden initially
- [ ] View existing inspection
- [ ] **Expected:** "New Inspection" button appears
- [ ] **Screenshot:** Take screenshot showing button

### Test Case 1.2: Button Functionality
- [ ] Click "New Inspection" button
- [ ] **Expected:** Inspection fields cleared (date, type, notes)
- [ ] **Expected:** Facility data remains (name, license, area)
- [ ] **Expected:** Message: "تم تفريغ الحقول..."
- [ ] Create new inspection
- [ ] Save successfully
- [ ] **Verify:** New inspection ID generated
- [ ] **Verify:** Old inspection still exists in database

### Test Case 1.3: Multiple New Inspections
- [ ] Search same license again
- [ ] View first inspection
- [ ] Click "New Inspection"
- [ ] Create second inspection
- [ ] **Verify:** Can create multiple inspections for same license
- [ ] **Verify:** Each has unique inspection_id

---

## Feature 2: Confirmation Modal 💬

### Test Case 2.1: Modal Display
- [ ] Fill inspection items
- [ ] Click "Save Items" button (bottom of page)
- [ ] **Expected:** Confirmation modal appears
- [ ] **Expected:** Modal shows: "هل أنت متأكد من حفظ بنود التفتيش؟"
- [ ] **Expected:** Two buttons: "نعم، احفظ" and "إلغاء"
- [ ] **Screenshot:** Take screenshot of modal

### Test Case 2.2: Cancel Functionality
- [ ] Open modal
- [ ] Click "إلغاء" button
- [ ] **Expected:** Modal closes
- [ ] **Expected:** No save occurs
- [ ] **Expected:** Message: "تم إلغاء عملية الحفظ"
- [ ] **Verify:** Database not updated

### Test Case 2.3: Confirm Functionality
- [ ] Open modal again
- [ ] Click "نعم، احفظ" button
- [ ] **Expected:** Modal closes
- [ ] **Expected:** Save proceeds normally
- [ ] **Expected:** Message: "تم حفظ البنود..."
- [ ] **Verify:** Database updated with changes

### Test Case 2.4: Modal Outside Click
- [ ] Open modal
- [ ] Click outside modal (on dark overlay)
- [ ] **Expected:** Modal remains open (no accidental close)

---

## Feature 3: Attachments Display 📎

### Test Case 3.1: Empty State
- [ ] Open inspection without attachments
- [ ] Scroll to "المرفقات الإضافية" section
- [ ] **Expected:** Message: "لا توجد مرفقات"
- [ ] **Screenshot:** Empty state

### Test Case 3.2: Attachments List Display
- [ ] Upload 2-3 attachments first
- [ ] Refresh or reload inspection
- [ ] **Expected:** All attachments listed
- [ ] **Expected:** Each shows: filename, view button, delete button
- [ ] **Expected:** PDF icon displayed
- [ ] **Screenshot:** List with attachments

### Test Case 3.3: View Button
- [ ] Click "عرض" button on attachment
- [ ] **Expected:** PDF opens in new browser tab
- [ ] **Expected:** PDF displays correctly
- [ ] **Verify:** Correct file opened

### Test Case 3.4: Multiple Attachments
- [ ] Upload 5 attachments
- [ ] **Verify:** All 5 displayed in list
- [ ] **Verify:** Order: newest first (uploaded_at DESC)

---

## Feature 4: Delete Attachment 🗑️

### Test Case 4.1: Delete Confirmation
- [ ] Click "حذف" button on attachment
- [ ] **Expected:** Browser confirm dialog: "هل أنت متأكد من حذف هذا المرفق؟"
- [ ] Click "Cancel"
- [ ] **Expected:** No deletion occurs

### Test Case 4.2: Successful Deletion
- [ ] Click "حذف" button
- [ ] Click "OK" in confirm dialog
- [ ] **Expected:** Attachment removed from UI immediately
- [ ] **Expected:** Message: "تم حذف المرفق بنجاح"
- [ ] **Expected:** No page reload
- [ ] Refresh page
- [ ] **Verify:** Attachment still gone
- [ ] **Verify:** File deleted from uploads folder
- [ ] **Verify:** Database record deleted

### Test Case 4.3: Delete All Attachments
- [ ] Upload 3 attachments
- [ ] Delete all 3 one by one
- [ ] **Expected:** After last deletion, shows "لا توجد مرفقات"
- [ ] **Expected:** Upload input still available

### Test Case 4.4: Permission Denied
- [ ] Login as different user (not inspector, not admin)
- [ ] Try to delete attachment from another user's inspection
- [ ] **Expected:** Error: "لا تملك صلاحية حذف هذا المرفق"
- [ ] **Verify:** Attachment not deleted

### Test Case 4.5: Admin Override
- [ ] Login as admin
- [ ] Delete attachment from any user's inspection
- [ ] **Expected:** Deletion succeeds
- [ ] **Verify:** Admin can delete any attachment

---

## Feature 5: Upload Attachment ⬆️

### Test Case 5.1: Single File Upload
- [ ] Click "Choose File"
- [ ] Select 1 PDF file (< 10MB)
- [ ] Click "رفع مرفق جديد"
- [ ] **Expected:** Upload success message
- [ ] **Expected:** File appears in list immediately
- [ ] **Verify:** File saved in uploads/inspections/attachments/
- [ ] **Verify:** Database record created

### Test Case 5.2: Multiple Files Upload
- [ ] Click "Choose File"
- [ ] Select 3 PDF files
- [ ] Click "رفع مرفق جديد"
- [ ] **Expected:** All 3 files uploaded
- [ ] **Expected:** All 3 appear in list
- [ ] **Verify:** All files in uploads folder
- [ ] **Verify:** 3 database records created

### Test Case 5.3: File Type Validation
- [ ] Try to upload .docx file
- [ ] **Expected:** Error: "يُسمح فقط بملفات PDF"
- [ ] **Verify:** File not uploaded
- [ ] Try to upload .jpg image
- [ ] **Expected:** Same error
- [ ] Try to upload .pdf.exe (spoofed)
- [ ] **Expected:** Upload blocked

### Test Case 5.4: File Size Validation
- [ ] Try to upload PDF > 10MB
- [ ] **Expected:** Error: "حجم الملف يجب أن يكون أقل من 10 ميجابايت"
- [ ] **Verify:** File not uploaded
- [ ] Try to upload PDF = 9.9MB
- [ ] **Expected:** Upload succeeds

### Test Case 5.5: No Inspection Error
- [ ] Clear currentInspectionId (in browser console: `currentInspectionId = null`)
- [ ] Try to upload
- [ ] **Expected:** Error: "الرجاء إنشاء التفتيش أولاً..."

### Test Case 5.6: Permission Denied
- [ ] Login as user without permission
- [ ] Try to upload to another user's inspection
- [ ] **Expected:** Error: "لا تملك صلاحية رفع مرفقات..."

---

## API Testing 🔌

### Test Case 6.1: get_attachments Endpoint
**Manual API Test:**
```bash
curl -X POST http://yoursite/api.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=get_attachments&inspection_id=123" \
  --cookie "PHPSESSID=your_session_id"
```
- [ ] **Expected:** JSON with attachments array
- [ ] **Verify:** Response structure matches documentation

### Test Case 6.2: delete_attachment Endpoint
**Manual API Test:**
```bash
curl -X POST http://yoursite/api.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=delete_attachment&attachment_id=1" \
  --cookie "PHPSESSID=your_session_id"
```
- [ ] **Expected:** `{"success": true, "message": "..."}`
- [ ] **Verify:** File deleted
- [ ] **Verify:** DB record deleted

### Test Case 6.3: upload_attachment Endpoint
**Manual API Test:**
```bash
curl -X POST http://yoursite/api.php \
  -F "action=upload_attachment" \
  -F "inspection_id=123" \
  -F "attachments[]=@test.pdf" \
  --cookie "PHPSESSID=your_session_id"
```
- [ ] **Expected:** `{"success": true, "uploaded_files": [...]}`
- [ ] **Verify:** File uploaded
- [ ] **Verify:** DB record created

---

## Security Testing 🔒

### Test Case 7.1: Authentication
- [ ] Logout
- [ ] Try DELETE request: `api.php?action=delete_attachment&attachment_id=1`
- [ ] **Expected:** Error: "يجب تسجيل الدخول..."
- [ ] Try UPLOAD without login
- [ ] **Expected:** Same error

### Test Case 7.2: Authorization
- [ ] Login as User A
- [ ] Get inspection created by User B
- [ ] Try to delete attachment
- [ ] **Expected:** Error: "لا تملك صلاحية..."
- [ ] Login as admin
- [ ] Try same deletion
- [ ] **Expected:** Success

### Test Case 7.3: SQL Injection
- [ ] Try: `action=get_attachments&inspection_id=1' OR '1'='1`
- [ ] **Expected:** No data leak, proper error handling
- [ ] Try: `action=delete_attachment&attachment_id=1; DROP TABLE tbl_inspection_attachments;--`
- [ ] **Expected:** No SQL execution, safe error

### Test Case 7.4: Path Traversal
- [ ] Try uploading file named: `../../../../etc/passwd`
- [ ] **Expected:** Sanitized filename, no directory traversal
- [ ] Verify file saved as: `attachment_XXX_timestamp_0.pdf`

### Test Case 7.5: XSS Prevention
- [ ] Upload file named: `<script>alert('xss')</script>.pdf`
- [ ] View in UI
- [ ] **Expected:** Filename escaped, no script execution

---

## Integration Testing 🔗

### Test Case 8.1: Create Inspection → Upload → Delete Flow
- [ ] Create new inspection
- [ ] Upload 2 attachments
- [ ] Delete 1 attachment
- [ ] Save inspection items
- [ ] **Verify:** All data consistent
- [ ] **Verify:** 1 attachment remains

### Test Case 8.2: Delete Inspection Cascade
- [ ] Create inspection with 3 attachments
- [ ] Note attachment IDs
- [ ] Delete entire inspection
- [ ] **Verify:** All attachment files deleted from disk
- [ ] **Verify:** All attachment records deleted from DB
- [ ] **Verify:** Cascade delete works

### Test Case 8.3: Edit Inspection → Add Attachments
- [ ] Load existing inspection
- [ ] Click "Edit" button
- [ ] Upload new attachment
- [ ] Save changes
- [ ] **Verify:** Attachment associated correctly

---

## Performance Testing ⚡

### Test Case 9.1: Large File Upload
- [ ] Upload 9.9MB PDF
- [ ] **Measure:** Upload time
- [ ] **Expected:** < 30 seconds on normal connection
- [ ] **Verify:** No timeout errors

### Test Case 9.2: Multiple Files Upload
- [ ] Upload 10 files at once
- [ ] **Measure:** Total upload time
- [ ] **Verify:** All uploaded successfully
- [ ] **Verify:** UI responsive during upload

### Test Case 9.3: Attachments List with Many Items
- [ ] Create inspection with 20 attachments
- [ ] Load inspection
- [ ] **Measure:** List render time
- [ ] **Expected:** < 2 seconds
- [ ] **Verify:** UI remains responsive

---

## Browser Compatibility 🌐

Test all features in:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Chrome (Android)
- [ ] Mobile Safari (iOS)

For each browser:
- [ ] Confirmation modal works
- [ ] File upload works
- [ ] Delete works
- [ ] View PDF opens correctly

---

## Accessibility Testing ♿

- [ ] Tab through form with keyboard
- [ ] **Expected:** All buttons reachable
- [ ] Press Enter on "Save" button
- [ ] **Expected:** Modal opens
- [ ] Navigate modal with Tab
- [ ] **Expected:** Focus trapped in modal
- [ ] Press Escape key
- [ ] **Expected:** Modal closes (if implemented)

---

## Error Recovery Testing 🚨

### Test Case 10.1: Network Failure During Upload
- [ ] Start file upload
- [ ] Disconnect network mid-upload
- [ ] **Expected:** Error message displayed
- [ ] Reconnect network
- [ ] Try upload again
- [ ] **Expected:** Upload succeeds

### Test Case 10.2: Disk Full
- [ ] Simulate disk full (if possible)
- [ ] Try upload
- [ ] **Expected:** Graceful error message
- [ ] **Verify:** Partial files cleaned up

### Test Case 10.3: Database Connection Lost
- [ ] Simulate DB disconnect
- [ ] Try any operation
- [ ] **Expected:** User-friendly error
- [ ] **Verify:** No PHP errors exposed

---

## Regression Testing 🔄

### Test Case 11.1: Existing Features Still Work
- [ ] Create inspection (without attachments)
- [ ] Add inspection items
- [ ] Save items
- [ ] **Verify:** Works as before
- [ ] Approve inspection
- [ ] **Verify:** Works as before
- [ ] Print report
- [ ] **Verify:** Works as before

### Test Case 11.2: Old Inspections Compatible
- [ ] Load inspection created before update
- [ ] **Verify:** Displays correctly
- [ ] **Verify:** No attachments section errors
- [ ] **Verify:** Can add attachments to old inspection

---

## Final Checklist ✅

### Documentation
- [ ] README.md updated
- [ ] VISUAL_CHANGES_SUMMARY.md created
- [ ] INSPECTION_UPDATES_README.md created
- [ ] All testing documented

### Code Quality
- [ ] No PHP syntax errors
- [ ] No JavaScript console errors
- [ ] No SQL errors in logs
- [ ] Code follows existing style

### Deployment
- [ ] SQL migration file ready
- [ ] Upload directory created
- [ ] Permissions set correctly
- [ ] Backup taken before deployment

### Sign-off
- [ ] Developer tested: ________________ Date: _______
- [ ] QA tested: ________________ Date: _______
- [ ] Client approved: ________________ Date: _______

---

## Bug Report Template 🐛

If you find issues, report them with:

```
**Issue Title:** [Short description]

**Severity:** Critical / High / Medium / Low

**Steps to Reproduce:**
1. 
2. 
3. 

**Expected Result:**


**Actual Result:**


**Screenshots/Error Messages:**


**Browser/Environment:**
- Browser: 
- PHP Version:
- Database:

**Additional Context:**

```

---

## Test Summary Report Template 📊

```
Test Date: ______________
Tester: ______________

Total Test Cases: ____
Passed: ____
Failed: ____
Blocked: ____
Pass Rate: ____%

Critical Issues Found: ____
High Priority Issues: ____
Medium Priority Issues: ____
Low Priority Issues: ____

Ready for Production: YES / NO

Notes:



Signature: ______________
```
