# EraLMS Quick Start Guides

---

## 👨‍💼 Administrator Quick Start

**For: admin.lms@vabis.edu.vn, daotao.lms@vabis.edu.vn**

### First Day Tasks

1. **Login to Admin Panel**
   ```
   URL: https://lms.vabis.edu.vn/admin
   Username: admin.lms@vabis.edu.vn
   Password: admin123456
   ```

2. **Dashboard Overview**
   - System Health: CPU, Memory, Database
   - Active Users: Real-time count
   - Key Metrics: Courses, Students, Completions
   - Alerts: Any system issues

3. **Core Admin Functions**

   **User Management**
   - Settings → Users
   - Add new user, bulk import
   - Assign roles and permissions
   - Manage user status

   **Course Management**
   - Courses → List
   - Approve pending courses
   - Archive completed courses
   - Monitor course progress

   **System Configuration**
   - Settings → System
   - Email settings
   - Upload storage
   - Integration settings
   - Backup settings

### Common Administrative Tasks

**Daily Tasks**
- [ ] Check system health (CPU, Memory, Disk)
- [ ] Review error logs
- [ ] Monitor user activity
- [ ] Approve pending courses
- [ ] Check backup status

**Weekly Tasks**
- [ ] Generate performance reports
- [ ] Review user analytics
- [ ] Check for security updates
- [ ] Verify backup integrity
- [ ] Meeting with stakeholders

**Monthly Tasks**
- [ ] User activity analysis
- [ ] Course effectiveness report
- [ ] System optimization
- [ ] Security audit
- [ ] Capacity planning

### Emergency Contacts

- **System Down**: Devops Team (devops@vabis.edu.vn)
- **Data Issue**: Database Admin (dba@vabis.edu.vn)
- **Security Breach**: Security Team (security@vabis.edu.vn)

---

## 👨‍🏫 Teacher Quick Start

**For: gv.lms@vabis.edu.vn and all teachers**

### First Day: Getting Started

1. **Login**
   ```
   URL: https://lms.vabis.edu.vn
   Username: gv.lms@vabis.edu.vn
   Password: gv123456
   ```

2. **Dashboard Tour**
   - My Courses: Courses you teach
   - Recent Activity: Student activity
   - Notifications: Alerts & messages
   - Quick Actions: Common tasks

### Essential Teacher Functions

**Course Management**
```
Courses → My Courses
├── Course Content
│   ├── Add Lessons
│   ├── Add Videos
│   ├── Add Quizzes
│   └── Add Assignments
├── Gradebook
│   ├── View Grades
│   ├── Enter Grades
│   ├── Calculate Final Grades
│   └── Export for SIS
└── Analytics
    ├── Student Progress
    ├── Engagement Metrics
    └── Risk Alerts
```

**Daily Tasks**
- [ ] Check for pending assignments to grade
- [ ] Review student questions/posts
- [ ] Monitor at-risk students
- [ ] Answer student messages
- [ ] Post announcements if needed

### Create Your First Course

1. **Navigate to Courses**
   - Click "Courses" in main menu
   - Click "Create New Course"

2. **Fill Course Information**
   - Course Code: (assigned by system)
   - Title: e.g., "Lập Trình Web - Lớp A"
   - Description: Course overview
   - Credits: 3
   - Capacity: 50-100

3. **Add Course Content**
   - Add Sections/Lessons
   - Upload videos from PC
   - Create quizzes
   - Add assignments

4. **Set Up Grading**
   - Create gradebook
   - Define grade items (quizzes, assignments, exams)
   - Set weights (e.g., Quiz 20%, Assignment 30%, Exam 50%)

5. **Enroll Students**
   - Manual add students
   - Or students self-enroll if enabled

6. **Publish Course**
   - Submit for review
   - Get approval from department
   - Publish to make available

### Grading Workflow

**Quick Grading:**
```
Gradebook → Select Student
├── View submissions
├── Enter scores
├── Add feedback
└── Save
```

**Bulk Download/Upload:**
```
Gradebook → Export
├── Download CSV
├── Edit in Excel
├── Upload CSV
```

### Video Upload

1. **Upload from Videos menu**
   - Title: e.g., "Bài 1: Giới thiệu HTML"
   - File: Select MP4 from computer
   - Auto-process to HLS streaming
   - Ready for students within 15 minutes

2. **Add to Course**
   - Courses → Edit Course
   - Add video component
   - Set as required/optional

### Key Keyboard Shortcuts

```
G   →  Go to Gradebook
C   →  Go to Courses
N   →  Create new item
?   →  Help
```

---

## 👨‍🎓 Student Quick Start

**For: sv.lms@vabis.edu.vn and all students**

### First Login

1. **Access LMS**
   ```
   URL: https://lms.vabis.edu.vn
   Username: sv.lms@vabis.edu.vn (or your student email)
   Password: Your password
   ```

2. **Welcome Tour**
   - System walks through basic features
   - Takes about 5 minutes
   - Can skip if you prefer

### Your Dashboard

**What You'll See:**
- My Courses: All your enrolled courses
- Recent Activity: Latest announcements
- My Grades: Current scores
- Calendar: Upcoming deadlines
- Messages: From teachers/classmates

### Enrolling in Courses

**Self-Enrollment (if enabled by teacher):**
1. Click "Explore Courses"
2. Search or browse courses
3. Click course name
4. Click "Enroll" button
5. Course appears in "My Courses"

**Manually Added by Teacher:**
- Check your email for enrollment notification
- Course appears in "My Courses"

### Learning a Course

**Typical Course Structure:**
```
Course → Week 1 → Lesson 1 → Watch Video
                           → Read Notes
                           → Take Quiz
                           → Submit Assignment
           Week 2 → Lesson 2 → ...
           Final → Final Exam
                → Final Project
```

**Complete a Lesson:**
1. Open lesson from course
2. Watch videos (can resume where you left off)
3. Read learning materials
4. Take quiz (if required)
5. Submit assignment (if required)
6. Check completion status

### Important Features

**Video Playback**
- Play/Pause with Space key
- 'F' for fullscreen
- Quality selector (top right)
- Speed control (0.75x to 2x)
- Captions available in Vietnamese
- Can download transcript

**Quiz Taking**
- Read all questions carefully
- Can review before submitting
- Timer shows remaining time
- Cannot go back after submit
- See results and feedback immediately (if enabled)

**Assignment Submission**
- Prepare files (PDF, DOC, ZIP)
- Click "Submit Assignment"
- Upload files
- Add notes (optional)
- Click "Submit"
- Email confirmation sent
- Can resubmit until deadline (if allowed)

**Checking Your Grade**
```
My Grades → Select Course
├── View gradebook
├── See all your scores
├── View feedback from teacher
└── Download grade report (as PDF or CSV)
```

### Getting Help

**If You Have Questions:**
1. Check course announcements
2. Post in discussion forum
3. Ask teacher in course chat
4. Contact course TA (Teaching Assistant)
5. Email: support@vabis.edu.vn

**Technical Issues:**
- Browser: Use Chrome or Firefox (recommended)
- Video won't play: Try different quality, clear cache
- Can't upload file: Check file size < 100MB
- Still stuck: Email support with screenshot

### Mobile App (PWA)

**Install on Phone:**
1. Open https://lms.vabis.edu.vn in mobile browser
2. Tap "Share" (iOS) or "Menu" (Android)
3. Select "Add to Home Screen"
4. Open app anytime, works offline

**Features:**
- Access courses offline
- Download videos to watch later
- Sync when connection restored
- Notifications for due dates

### Important Deadlines

**Check Your Calendar:**
- Quiz due dates
- Assignment deadlines
- Exam schedule
- Final project deadline

**Set Reminders:**
- Add to phone calendar
- Enable notifications
- Plan ahead - don't wait until last minute!

### Tips for Success

✅ **Do:**
- Start courses early
- Watch all videos
- Participate in discussions
- Ask questions
- Submit assignments on time
- Review feedback from teacher

❌ **Don't:**
- Wait until last day to submit
- Skip lessons
- Copy other students' work
- Ignore feedback
- Miss deadlines

### My Profile

**Update Your Information:**
1. Click profile icon (top right)
2. Click "My Profile"
3. Update:
   - Avatar photo
   - Biography
   - Learning goals
   - Preferences
4. Save changes

### Keyboard Shortcuts (Mobile-friendly)

```
M   →  Messages
G   →  Grades
C   →  Courses
?   →  Help
```

---

## 👨‍👩‍👧 Parent Quick Start

**For: parent.lms@vabis.edu.vn**

### Parent Dashboard

**View Your Child's Progress:**
1. Login: parent.lms@vabis.edu.vn
2. Dashboard shows:
   - Child's enrolled courses
   - Current grades
   - Attendance record
   - Recent activity

### Monitor Child's Learning

**Course Progress:**
- Click on child's course
- See lessons completed
- View assignments submitted
- Check quiz scores

**Grades:**
- View current grade in each course
- See individual assignment grades
- View attendance percentage
- Get warnings if grade drops

**Attendance:**
- Monitor attendance records
- See absence pattern
- Get notified of frequent absences

### Communication

**Message Teacher:**
- Click "Message" button
- Send message to teacher
- Receive responses
- Stay informed about child's progress

**View Teacher Comments:**
- See teacher feedback on assignments
- Read grade feedback
- Understand areas for improvement

### Receive Notifications

**Enable Alerts:**
1. Settings → Notifications
2. Choose what to be notified about:
   - Grade updates
   - Attendance alerts
   - Deadline reminders
   - Teacher messages
3. Select notification method:
   - Email
   - SMS (if enabled)
   - In-app only

---

## 🆘 Support & Troubleshooting

### Common Issues

**Can't Login:**
- [ ] Check email address (case-sensitive)
- [ ] Reset password: "Forgot Password" link
- [ ] Check CAPS LOCK
- [ ] Try different browser
- [ ] Clear browser cache (Ctrl+Shift+Delete)
- Contact: support@vabis.edu.vn

**Video Won't Play:**
- [ ] Try different quality (lower bandwidth)
- [ ] Refresh page (Ctrl+R)
- [ ] Try different browser
- [ ] Check internet connection
- [ ] Contact: support@vabis.edu.vn

**Can't Upload Files:**
- [ ] Check file size < 100MB
- [ ] Allowed formats: PDF, DOC, DOCX, ZIP, JPG, PNG
- [ ] Check internet connection
- [ ] Try different browser
- [ ] Contact: support@vabis.edu.vn

**Grade Not Showing:**
- [ ] Wait 5-10 minutes for page refresh
- [ ] Clear browser cache
- [ ] Check if teacher has entered grades
- [ ] Contact: teacher or support@vabis.edu.vn

### Getting Help

| Issue | Contact | Response Time |
|-------|---------|---------------|
| System Down | support@vabis.edu.vn | 15 min (Critical) |
| Urgent Problem | support@vabis.edu.vn | 1 hour |
| General Question | support@vabis.edu.vn | 24 hours |
| Course Content | Course Teacher | 48 hours |
| Technical | devops@vabis.edu.vn | 2 hours |

### Support Contacts

```
Email: support@vabis.edu.vn
Phone: 024-3xxx-xxxx
Chat: Available in system 9 AM - 5 PM (Mon-Fri)
```

---

**Version**: 1.0  
**Last Updated**: 2024-12-21  
**Next Review**: 2025-01-21

