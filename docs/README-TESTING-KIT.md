# EraLMS Enterprise Testing & GoLive Kit

**Complete suite for UAT, Performance Testing, and Production Deployment**

Version: 1.0.0  
Updated: June 2024  
Last Reviewed: 2024-12-21

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Seed Data](#seed-data)
4. [UAT Scripts](#uat-scripts)
5. [Performance Testing](#performance-testing)
6. [GoLive Checklist](#golive-checklist)
7. [API Documentation](#api-documentation)
8. [Support & Troubleshooting](#support--troubleshooting)

---

## Overview

This kit provides everything needed for comprehensive testing and production deployment of EraLMS Enterprise:

- **Seed Data Builder**: Generate 100K+ realistic test records
- **20 UAT Scenarios**: Complete business process workflows
- **Performance Benchmarks**: Response time and load testing
- **GoLive Checklist**: 50+ deployment verification tasks
- **Admin & User Guides**: Role-specific documentation
- **API Reference**: Complete endpoint documentation

### Target Data Scale

```
- 1 Tenant (VABIS LMS)
- 2 Campuses (Main + Branch)
- 19 Academic Units
- 90 Teachers
- 5,000 Students
- 200 Courses
- 1,000 Lessons
- 500 Videos
- 20,000 Quiz Attempts
- 10,000 Assignment Submissions
- 5,000 Attendance Records
- 100,000+ Learning Events
```

### Environments

```
Development   : localhost:8000  (local SQLite)
Staging       : staging.vabis.edu.vn (MySQL 8.0)
Production    : lms.vabis.edu.vn (MySQL 8.0 + Redis)
```

---

## Quick Start

### 1. Setup Development Environment

```bash
# Clone repository
git clone https://github.com/vabis/eralms.git
cd eralms

# Install dependencies
composer install
npm install

# Copy environment
cp .env.example .env

# Generate app key
php artisan key:generate

# Configure database
# Edit .env with MySQL connection details

# Run migrations
php artisan migrate

# Load seed data (15-20 minutes)
php artisan db:seed --class=EnterpriseDataSeeder

# Start Laravel server
php artisan serve

# Start Vite (for assets)
npm run dev

# Start queue worker (in new terminal)
php artisan queue:work
```

### 2. Access Application

**Demo Accounts:**
```
Admin       : admin.lms@vabis.edu.vn / admin123456
Training    : daotao.lms@vabis.edu.vn / daotao123456
Department  : khoa.lms@vabis.edu.vn / khoa123456
Teacher     : gv.lms@vabis.edu.vn / gv123456
Student     : sv.lms@vabis.edu.vn / sv123456
Parent      : parent.lms@vabis.edu.vn / parent123456
```

### 3. Run Tests

```bash
# Unit tests
php artisan test

# Feature tests
php artisan test tests/Feature --verbose

# Performance tests
php artisan test tests/Performance/PerformanceTestSuite.php

# UAT scenarios (manual - see UAT Scripts below)
```

---

## Seed Data

### What's Included

The **EnterpriseDataSeeder** creates a complete, realistic dataset:

#### Academic Structure
- 1 Tenant: VABIS LMS
- 2 Campuses: Hà Nội (main), TP.HCM (branch)
- 19 Academic Units (khoa/bộ môn):
  - Khoa Giáo Dục Trung Học (9+)
  - Khoa Kỹ Thuật Công Nghệ (Trung cấp)
  - Khoa Kinh Doanh & Quản Lý (Trung cấp)
  - Khoa Sức Khỏe (Trung cấp)
  - Khoa Công Nghệ Thông Tin (Cao đẳng)
  - Khoa Kỹ Thuật Xây Dựng (Cao đẳng)
  - Khoa Kinh Tế (Cao đẳng)
  - Trung Tâm Ngoại Ngữ
  - Trung Tâm Đào Tạo Doanh Nghiệp
  - 10 Bộ Môn cơ bản (Toán, Lý, Hóa, Sinh, Văn, Sử, Địa, Thể Dục, Âm Nhạc, Mỹ Thuật)

#### User Records
- **90 Teachers**: First names, emails (gv00001@vabis.edu.vn), phone numbers, departments
- **5,000 Students**: Realistic names, emails (sv00001@student.vabis.edu.vn), student IDs, enrollment dates

#### Course Structure
- **200 Courses**: Various types (theory, practical, online, hybrid)
- **1,000 Lessons**: Organized in course sections with descriptions
- **3,000+ Course Components**: Videos, text content, quizzes

#### Content & Media
- **500 Video Assets**: With HLS URLs, thumbnails, transcripts
- **1,000 Questions**: Multiple-choice, true/false, short answer, essay
- **5 Question Banks**: Organized by topic
- **100 Exams**: With 20-50 questions each

#### Assessments & Submissions
- **50 Assignments**: With rubric criteria
- **10,000 Enrollments**: Students → Courses
- **20,000 Quiz Attempts**: With scores and submissions
- **10,000 Assignment Submissions**: File uploads and grades
- **5,000 Attendance Records**: Check-ins and attendance tracking

#### Learning Analytics
- **20 Gradebooks**: With 5 grade items each
- **10,000 Learning Progress Records**: Per-student progress tracking
- **1,000 Certificates**: Issued to students
- **50 Surveys**: With 10 questions each
- **100 Forum Topics** + **1,000 Discussion Posts**

### Running the Seeder

```bash
# Full seed (recommended first time)
php artisan db:seed --class=EnterpriseDataSeeder

# Seed with fresh database
php artisan migrate:fresh --seed --seeder=EnterpriseDataSeeder

# Seed specific components (create separate seeders if needed)
php artisan db:seed --class=CourseStudioSeeder
php artisan db:seed --class=EnrollmentSeeder
```

### Verifying Data

```bash
# Check record counts
php artisan tinker

// In Tinker REPL:
>>> App\Models\Tenant::count()           // Should be 1
>>> App\Models\Campus::count()            // Should be 2
>>> App\Models\LmsUser::count()           // Should be ~5,090 (90 teachers + 5000 students)
>>> App\Models\Course::count()            // Should be 200
>>> App\Models\Enrollment::count()        // Should be ~10,000
>>> App\Models\ExamAttempt::count()       // Should be ~20,000
>>> App\Models\VideoAsset::count()        // Should be 500
>>> exit
```

### Cleaning Seed Data

**IMPORTANT: Before production, remove all seed/test data!**

```bash
# List test data created
php artisan tinker

>>> $admins = \App\Models\LmsUser::where('email', 'like', 'admin%@vabis.edu.vn')->count();
>>> $teachers = \App\Models\LmsUser::where('code', 'like', 'GV%')->count();
>>> $students = \App\Models\LmsUser::where('code', 'like', 'SV%')->count();
>>> dd(compact('admins', 'teachers', 'students'));

# Delete test data (use with caution!)
php artisan db:seed --class=CleanupTestDataSeeder
```

---

## UAT Scripts

### What's Included

20 comprehensive User Acceptance Test scenarios covering all major workflows:

| # | Scenario | Duration | Actor | 
|---|----------|----------|-------|
| 1 | Create Course | 15 min | Training Officer |
| 2 | Course Approval Workflow | 30 min | Multi-level |
| 3 | Create Learning Path | 20 min | Training Officer |
| 4 | Upload Video | 25 min | Teacher |
| 5 | Watch Video with Tracking | 20 min | Student |
| 6 | Take Quiz | 30 min | Student |
| 7 | Submit Assignment | 20 min | Student |
| 8 | Grade Assignment | 25 min | Teacher |
| 9 | Calculate Final Grades | 15 min | Teacher |
| 10 | Online Attendance | 30 min | Teacher |
| 11 | Exam Eligibility | 15 min | System |
| 12 | Issue Certificate | 10 min | System |
| 13 | SIS Synchronization | 45 min | Admin |
| 14 | Leadership Dashboard | 20 min | Admin |
| 15 | AI Tutor Q&A | 15 min | Student |
| 16 | Student Portfolio | 15 min | Student |
| 17 | Course Backup & Clone | 20 min | Teacher |
| 18 | Security Audit | 60 min | QA/DevOps |
| 19 | Mobile/PWA Testing | 45 min | QA |
| 20 | Performance Baseline | 60 min | DevOps |

### Running UAT Scripts

**Automated Tests:**
```bash
# View all scenarios
php artisan tinker
>>> include('tests/UAT/UATScenarios.php');
>>> $scenario = Tests\UAT\UATScenarios::scenario001_CreateCourse();
>>> dd($scenario);

# Run specific UAT tests (if implemented in test suite)
php artisan test --filter=UAT001
php artisan test --filter=UAT
```

**Manual UAT Steps:**

See [UAT-GUIDE.md](./docs/UAT-GUIDE.md) for step-by-step manual testing procedures for each scenario.

### UAT Acceptance Criteria

Each scenario must meet:
- ✅ All steps execute successfully
- ✅ Expected results match actual results
- ✅ Data persisted correctly
- ✅ Validation rules enforced
- ✅ Error messages clear and helpful
- ✅ Performance meets targets (< specified timeout)
- ✅ Audit logs recorded
- ✅ No database errors or constraint violations

---

## Performance Testing

### Performance Targets

```
Metric                              Target      Status
─────────────────────────────────────────────────────
Dashboard Load Time                 < 1,500ms   
Open Lesson/Chapter                 < 1,000ms   
Save Learning Progress              < 300ms     
Submit Quiz/Exam                    < 1,000ms   
Gradebook (500 students)            < 2,000ms   
Video Start Playback                < 2,000ms   
API Response Time (p95)             < 500ms     
API Response Time (p99)             < 1,000ms   
Concurrent Users                    1,000       
Database Query (avg)                < 100ms     
Cache Hit Ratio                     > 80%       
Memory Usage (baseline)             < 500MB     
Queue Processing (non-blocking)     < 100ms     
Full-Text Search                    < 500ms     
Report Generation (100 students)    < 5,000ms   
```

### Running Performance Tests

**Laravel Test Suite:**
```bash
# Run all performance tests
php artisan test tests/Performance/PerformanceTestSuite.php

# Run specific performance test
php artisan test tests/Performance/PerformanceTestSuite.php::test_dashboard_load_time

# Run with output
php artisan test tests/Performance/PerformanceTestSuite.php -v

# Generate performance report
php artisan test tests/Performance/PerformanceTestSuite.php --coverage
```

**Load Testing with k6:**

See [PERFORMANCE-TEST.md](./docs/PERFORMANCE-TEST.md) for k6 test scripts and load testing methodology.

```bash
# Install k6
brew install k6  # macOS
# or download from https://k6.io/open-source/

# Run load test
k6 run scripts/load-test.js

# Run with specific concurrency
k6 run -u 1000 -d 5m scripts/load-test.js
```

### Analyzing Results

```bash
# View performance report
cat storage/performance_reports/report_*.json | jq '.'

# Compare against targets
php artisan performance:report

# Generate HTML report
php artisan performance:report --format=html
```

---

## GoLive Checklist

### 50+ Deployment Verification Tasks

The GoLive checklist is organized into 8 categories:

#### 1. Infrastructure (8 items)
- [ ] Production Server Setup
- [ ] Database Server Setup
- [ ] Redis Cache Server
- [ ] Message Queue Setup
- [ ] Storage Solution Setup
- [ ] CDN Configuration
- [ ] Load Balancer Configuration
- [ ] Network & Firewall Rules

#### 2. Configuration (7 items)
- [ ] Environment Variables
- [ ] Application Configuration
- [ ] Database Configuration
- [ ] Cache & Session Config
- [ ] Queue Configuration
- [ ] Logging Configuration
- [ ] Email Configuration

#### 3. Security (10 items)
- [ ] SSL/TLS Certificate
- [ ] HTTPS Redirect
- [ ] CORS Configuration
- [ ] CSRF Protection
- [ ] SQL Injection Prevention
- [ ] Authentication Testing
- [ ] Authorization Testing
- [ ] Sensitive Data Encryption
- [ ] API Rate Limiting
- [ ] Security Headers

#### 4. Data Migration (5 items)
- [ ] Database Migrations
- [ ] Seed Data Validation
- [ ] Data Migration from Old System
- [ ] Data Integrity Checks
- [ ] Test Data Cleanup

#### 5. Backup & Recovery (5 items)
- [ ] Backup Solution Setup
- [ ] Automated Backup Scheduling
- [ ] Disaster Recovery Plan
- [ ] Backup Verification
- [ ] Geo-Redundant Storage

#### 6. Monitoring (6 items)
- [ ] Monitoring & Alerting System
- [ ] Log Aggregation (ELK Stack)
- [ ] Uptime Monitoring
- [ ] Performance Monitoring
- [ ] Error Rate Monitoring
- [ ] On-Call Rotation Setup

#### 7. Integration (5 items)
- [ ] SIS Integration Testing
- [ ] Email Service Integration
- [ ] Payment Gateway Integration
- [ ] Authentication Service (SSO/LDAP)
- [ ] Third-Party API Integrations

#### 8. Documentation (5 items)
- [ ] Administrator Guide
- [ ] Teacher User Guide
- [ ] Student Quick Start Guide
- [ ] API Documentation
- [ ] Troubleshooting Guide

### Using GoLive Checklist

**API Endpoints:**

```bash
# View all checklist items
curl -X GET http://lms.vabis.edu.vn/api/v1/golive-checklist \
  -H "Authorization: Bearer {token}"

# View by category
curl -X GET http://lms.vabis.edu.vn/api/v1/golive-checklist/by-category \
  -H "Authorization: Bearer {token}"

# Get summary
curl -X GET http://lms.vabis.edu.vn/api/v1/golive-checklist/summary \
  -H "Authorization: Bearer {token}"

# Get readiness report
curl -X GET http://lms.vabis.edu.vn/api/v1/golive-checklist/readiness-report \
  -H "Authorization: Bearer {token}"

# Mark item complete
curl -X PATCH http://lms.vabis.edu.vn/api/v1/golive-checklist/1/complete \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "notes": "Server setup verified, all health checks passed",
    "evidence_url": "https://monitoring.vabis.edu.vn/health"
  }'

# Export checklist
curl -X GET http://lms.vabis.edu.vn/api/v1/golive-checklist/export?format=csv \
  -H "Authorization: Bearer {token}" > checklist.csv
```

**GoLive Readiness Criteria:**

- ✅ All **CRITICAL** items completed
- ✅ All **HIGH** priority items completed  
- ✅ > 90% of all items completed
- ✅ Zero critical security issues
- ✅ All backup/recovery tests passed
- ✅ Performance within targets
- ✅ Monitoring systems active
- ✅ On-call team ready

See [GOLIVE-GUIDE.md](./docs/GOLIVE-GUIDE.md) for detailed GoLive execution steps.

---

## API Documentation

### Available Endpoints

#### Testing & Management APIs

```
GET  /api/v1/health                              Health check
GET  /api/v1/system/info                         System information
POST /api/v1/test-data/reset                     Reset to known state
POST /api/v1/performance/baseline                Generate performance report
GET  /api/v1/golive-checklist                    Checklist items
PATCH /api/v1/golive-checklist/{id}/complete    Mark item complete
```

#### Core APIs (in existing system)

```
GET  /api/v1/courses                             List courses
POST /api/v1/courses                             Create course
GET  /api/v1/enrollments                         List enrollments
POST /api/v1/learning-progress/save              Save progress
POST /api/v1/exam-attempts/{id}/submit           Submit exam
GET  /api/v1/gradebooks/{id}                     View gradebook
POST /api/v1/attendance/checkin                  Student check-in
```

Full API documentation: [API-REFERENCE.md](./docs/API-REFERENCE.md)

---

## Support & Troubleshooting

### Common Issues

**Issue: Seeding fails with "too many connections"**
```bash
# Solution: Increase MySQL max_connections
# In my.cnf: max_connections=500
# Or run fewer seeders in parallel
```

**Issue: UAT tests timeout**
```bash
# Solution: Increase test timeout
php artisan test --timeout=600

# Or optimize database queries
php artisan tinker
>>> DB::enableQueryLog(); 
>>> // Run test
>>> echo DB::getQueryLog();
```

**Issue: Performance test fails**
```bash
# Solution: Check system resources
# - CPU: should have spare capacity (< 70% usage)
# - Memory: should have 500MB+ available
# - Disk: should have 50GB+ free

free -h          # Check RAM (Linux)
df -h /          # Check disk space
top -n 1         # Check CPU usage
```

### Support Contacts

- **QA Lead**: qalead@vabis.edu.vn
- **DevOps Lead**: devops@vabis.edu.vn  
- **Product Manager**: pm@vabis.edu.vn

---

## Documentation Files

- [README-SEED.md](./docs/README-SEED.md) - Detailed seed data guide
- [README-UAT.md](./docs/README-UAT.md) - UAT procedures  
- [README-PERFORMANCE.md](./docs/README-PERFORMANCE.md) - Performance testing guide
- [README-GOLIVE.md](./docs/README-GOLIVE.md) - GoLive execution guide
- [ADMIN-GUIDE.md](./docs/ADMIN-GUIDE.md) - Administrator guide
- [TEACHER-GUIDE.md](./docs/TEACHER-GUIDE.md) - Teacher quick guide
- [STUDENT-GUIDE.md](./docs/STUDENT-GUIDE.md) - Student quick guide
- [API-REFERENCE.md](./docs/API-REFERENCE.md) - API endpoint reference

---

## Handover Documentation (VABIS/SIS/LMS)

### For VABIS Team
- System architecture overview
- Database schema documentation
- API specifications for SIS integration
- Troubleshooting procedures

### For SIS Team
- SIS adapter interface contracts
- Data mapping specifications
- Sync job scheduling
- Error handling procedures

### For LMS Teams (VABIS)
- System administration procedures
- User onboarding workflows
- Content management procedures
- Maintenance schedules

---

**Version History**
| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2024-06-20 | Initial release - Complete testing kit |

