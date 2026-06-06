# EraLMS UAT Guide

**Complete User Acceptance Testing Procedures**

---

## Test Environment Setup

### Prerequisites
- [ ] Test environment deployed (staging.vabis.edu.vn)
- [ ] Database refreshed with seed data
- [ ] All demo accounts created
- [ ] Email notifications working
- [ ] File storage accessible
- [ ] Video CDN functioning

### Demo Accounts

| Role | Email | Password | Purpose |
|------|-------|----------|---------|
| Super Admin | superadmin@eralms.vn | admin123456 | System configuration |
| Admin (LMS) | admin.lms@vabis.edu.vn | admin123456 | Overall LMS management |
| Training Officer | daotao.lms@vabis.edu.vn | daotao123456 | Course creation & management |
| Department Head | khoa.lms@vabis.edu.vn | khoa123456 | Department oversight |
| Teacher | gv.lms@vabis.edu.vn | gv123456 | Course teaching |
| Student | sv.lms@vabis.edu.vn | sv123456 | Learning |
| Parent | parent.lms@vabis.edu.vn | parent123456 | Child monitoring |

---

## UAT-001: Create Course

**Objective**: Verify course creation with all required fields  
**Actor**: Training Officer (daotao.lms@vabis.edu.vn)  
**Duration**: 15 minutes  
**Status**: 🔄 In Progress

### Test Steps

1. **Login to LMS**
   - [ ] Navigate to https://staging.vabis.edu.vn
   - [ ] Enter credentials: daotao.lms@vabis.edu.vn / daotao123456
   - [ ] Click "Đăng nhập"
   - **Expected**: Dashboard loads

2. **Navigate to Courses**
   - [ ] Click "Courses" in main menu
   - [ ] Click "New Course" button
   - **Expected**: Course creation form appears

3. **Fill Course Information**
   - [ ] Enter Course Code: **CO2024001**
   - [ ] Enter Title: **Lập Trình Web Nâng Cao - K2024**
   - [ ] Enter Description: **Khóa học lập trình web với React, Node.js**
   - [ ] Select Type: **Online**
   - [ ] Enter Credits: **3**
   - **Expected**: All fields accept input

4. **Set Course Options**
   - [ ] Select Category: **IT**
   - [ ] Enable Self-Enrollment: **Yes**
   - [ ] Set Max Capacity: **100 students**
   - [ ] Set Start Date: **2024-12-20**
   - [ ] Set End Date: **2025-03-30**
   - **Expected**: Dropdown selections work

5. **Save Course**
   - [ ] Review all fields
   - [ ] Click "Save as Draft"
   - **Expected**: "Course saved successfully" message appears

6. **Verify Course Created**
   - [ ] Navigate to Courses list
   - [ ] Search for "CO2024001"
   - [ ] Click on course
   - [ ] Verify all fields match input
   - [ ] Check status = **Draft**
   - **Expected**: Course appears in list with correct data

### Test Data

| Field | Value | Notes |
|-------|-------|-------|
| Course Code | CO2024001-CO2024020 | Test 5 variations |
| Title | Various in Vietnamese | Include special characters |
| Credits | 2, 3, 4 | Test all valid ranges |
| Capacity | 50, 75, 100, 150 | Test boundary values |

### Validation Points

- [x] Course code is unique (test with duplicate)
- [x] Title is required (test empty)
- [x] Credits between 1-4 (test invalid values)
- [x] Status = 'draft' (verify in DB)
- [x] Audit log created (check system logs)
- [x] Email notification sent (check inbox)
- [x] Timestamp recorded in UTC

### Sign-Off

- **Tested By**: ________________  Date: ________
- **Status**: [ ] Pass  [ ] Fail  [ ] Partial
- **Notes**: ________________________________

---

## UAT-002: Course Approval Workflow

**Objective**: Verify multi-level course approval process  
**Actors**: Teacher → Department Head → Admin  
**Duration**: 30 minutes  
**Status**: 🔄 In Progress

### Workflow

```
Teacher (Draft)
    ↓
Submit for Review
    ↓
Dept Head (Under Review)
    ↓ [Approve or Request Changes]
    ↓
Academic Admin (Final Approval)
    ↓
Published
```

### Test Steps

#### Stage 1: Teacher Submit

1. **Login as Teacher**
   - [ ] Login: gv.lms@vabis.edu.vn
   - [ ] Navigate to Courses
   - [ ] Open CO2024001

2. **Submit for Review**
   - [ ] Click "Submit for Approval"
   - [ ] Enter review notes: "Khóa học sẵn sàng cho kỳ tuyển sinh"
   - [ ] Click "Submit"
   - **Expected**: Status changes to "Pending Department Review"

3. **Verify Notification**
   - [ ] Check inbox for department head
   - [ ] Email subject should be: "Khóa học cần xem xét: CO2024001"
   - [ ] Email includes course title and teacher name
   - **Expected**: Email delivered within 5 minutes

#### Stage 2: Department Head Review

4. **Login as Department Head**
   - [ ] Logout previous user
   - [ ] Login: khoa.lms@vabis.edu.vn
   - [ ] Navigate to "My Reviews"

5. **Review Course**
   - [ ] Open CO2024001 from review queue
   - [ ] Review course structure:
     - [ ] Objectives clear
     - [ ] Content organized
     - [ ] Learning outcomes defined
   - [ ] Add comment: "Cấu trúc tốt, phù hợp với chuẩn đơn vị"

6. **Approve or Request Changes**
   - [ ] Click "Approve" button
   - [ ] Status changes to "Under Academic Review"
   - **Expected**: Status update notification sent

#### Stage 3: Academic Admin Final Approval

7. **Login as Academic Admin**
   - [ ] Logout
   - [ ] Login: admin.lms@vabis.edu.vn
   - [ ] Navigate to Course Reviews

8. **Final Review and Publish**
   - [ ] Review all comments from previous approvers
   - [ ] Click "Publish Course"
   - **Expected**: Course status = "Published"

9. **Verify Course Published**
   - [ ] Students can now see course in catalog
   - [ ] Enrollment opens automatically
   - [ ] Course appears in search results
   - **Expected**: Course available for self-enrollment

### Validation Points

- [x] All status transitions correct
- [x] Only authorized users can approve
- [x] Comments visible to all reviewers
- [x] Audit trail complete with timestamps
- [x] Approval notifications sent
- [x] State machine prevents invalid transitions
- [x] Published course accessible to students

### Sign-Off

- **Tested By**: ________________  Date: ________
- **Approval Chain**:
  - [ ] Teacher submitted: __________
  - [ ] Dept Head approved: __________
  - [ ] Admin published: __________
- **Status**: [ ] Pass  [ ] Fail  [ ] Partial

---

## UAT-003 through UAT-020

*Detailed procedures for each scenario follow the same format above*

### Quick Reference

| UAT # | Scenario | Expected Time | Key Validations |
|-------|----------|---------------|-----------------|
| 003 | Learning Path | 20 min | Prerequisites enforced, unlocking works |
| 004 | Upload Video | 25 min | HLS streams generated, CDN cache updated |
| 005 | Watch Video | 20 min | Progress tracked, resume works, analytics recorded |
| 006 | Take Quiz | 30 min | Questions randomized, scoring correct, feedback shown |
| 007 | Submit Assignment | 20 min | Files uploaded, timestamp recorded, late detection |
| 008 | Grade Assignment | 25 min | Rubric applied, feedback sent, grade recorded |
| 009 | Calculate Grades | 15 min | Weighted calculation correct, letter grades assigned |
| 010 | Attendance | 30 min | Check-in QR works, timestamps accurate, SIS sync |
| 011 | Exam Eligibility | 15 min | Prerequisites checked, blocking reasons shown |
| 012 | Issue Certificate | 10 min | Auto-issued on completion, PDF generated, email sent |
| 013 | SIS Sync | 45 min | Data bidirectional, error handling, audit trail |
| 014 | Dashboard | 20 min | Load time < 1.5s, metrics calculated, drill-down works |
| 015 | AI Tutor | 15 min | Context-aware responses, flashcards generated |
| 016 | Portfolio | 15 min | Achievements shown, shareable link works |
| 017 | Backup/Clone | 20 min | Backup restores, clone independent, data clean |
| 018 | Security | 60 min | Injection attacks blocked, CSRF protected, auth verified |
| 019 | Mobile/PWA | 45 min | Offline access works, sync functional, responsive |
| 020 | Performance | 60 min | Targets met, no timeouts, queue non-blocking |

---

## UAT Execution Checklist

### Pre-Test (Day -1)
- [ ] Fresh database with seed data
- [ ] All demo accounts created
- [ ] Email notifications configured
- [ ] File storage accessible
- [ ] Video CDN operational
- [ ] Test data backup created

### During Test (Day 0-5)
- [ ] Daily standup at 9:00 AM
- [ ] Document any issues with priority
- [ ] Take screenshots of pass/fail states
- [ ] Record detailed steps if reproducing bugs
- [ ] Update blockers daily

### Post-Test (Day 6)
- [ ] All 20 scenarios completed
- [ ] Critical issues resolved
- [ ] Performance targets verified
- [ ] Sign-off from stakeholders
- [ ] Documentation updated

---

## Issue Reporting Template

```
Title: [UAT-NNN] Brief description

Environment: Staging
Date Reported: YYYY-MM-DD HH:MM
Reported By: Name

Steps to Reproduce:
1. 
2. 
3. 

Expected Result:

Actual Result:

Severity: [ ] Critical [ ] High [ ] Medium [ ] Low
Priority: [ ] P1 (Block) [ ] P2 (High) [ ] P3 (Nice) [ ] P4 (Future)

Attachments:
- Screenshot
- Video recording
- Database export

Environment Details:
- Browser: 
- Device: 
- OS Version:
```

---

## Sign-Off

### QA Manager
- Name: ____________________
- Date: ___________
- Signature: ___________________

### Product Manager  
- Name: ____________________
- Date: ___________
- Signature: ___________________

### Technical Lead
- Name: ____________________
- Date: ___________
- Signature: ___________________

---

**Test Execution Summary**

```
Total Test Cases: 20
Passed: ___
Failed: ___
Blocked: ___
Deferred: ___

Critical Issues: ___
High Issues: ___
Medium Issues: ___
Low Issues: ___

Overall Status: [ ] READY FOR GOLIVE [ ] ISSUES REMAIN
```

