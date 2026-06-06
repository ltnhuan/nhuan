# EraLMS GoLive Execution Guide

**Complete step-by-step production deployment guide**

---

## Pre-GoLive Phase (T-14 days to T-0)

### Week 1: Infrastructure & Configuration

#### Infrastructure Verification (Days -14 to -10)

**Checklist Items to Complete:**

1. **Production Server Setup** [INFRA-001]
   ```bash
   # SSH into production server
   ssh ubuntu@prod-server.vabis.edu.vn
   
   # Check system resources
   uname -a                    # OS and kernel
   nproc                       # CPU cores
   free -h                     # RAM
   df -h /                     # Disk space
   
   # Minimum requirements
   # - 8 CPU cores
   # - 32 GB RAM
   # - 500 GB SSD storage
   # - Ubuntu 22.04 LTS
   
   # Configure system limits
   sudo nano /etc/security/limits.conf
   # Add: * soft nofile 65536
   # Add: * hard nofile 65536
   ```

2. **Database Server Setup** [INFRA-002]
   ```bash
   # Test MySQL connection
   mysql -h prod-db.vabis.edu.vn -u app_user -p
   
   # Verify version
   SELECT VERSION();
   
   # Check configuration
   SHOW VARIABLES WHERE variable_name IN (
     'max_connections', 'innodb_buffer_pool_size',
     'sort_buffer_size', 'join_buffer_size'
   );
   
   # Required settings:
   # - MySQL 8.0+
   # - max_connections >= 300
   # - innodb_buffer_pool_size >= 20GB
   # - Replication configured (if HA)
   ```

3. **Redis Cache Setup** [INFRA-003]
   ```bash
   # Test Redis connection
   redis-cli -h prod-redis.vabis.edu.vn ping
   
   # Check memory usage
   redis-cli -h prod-redis.vabis.edu.vn INFO memory
   
   # Required:
   # - Redis 6.2+
   # - Memory >= 8GB
   # - Persistence enabled (AOF)
   # - Cluster mode (if HA)
   ```

4. **Message Queue Setup** [INFRA-004]
   ```bash
   # For RabbitMQ
   sudo rabbitmqctl status
   sudo rabbitmq-plugins enable rabbitmq_management
   # Access: http://prod-queue.vabis.edu.vn:15672
   ```

5. **CDN Configuration** [INFRA-006]
   ```
   - [ ] Origin URL configured
   - [ ] Cache TTL set (3600s for video)
   - [ ] Origin shield enabled
   - [ ] GeoIP routing configured
   - [ ] DDoS protection enabled
   ```

#### Configuration Verification (Days -9 to -7)

1. **Environment Variables** [CONFIG-001]
   ```bash
   # SSH into server
   ssh ubuntu@prod-app.vabis.edu.vn
   
   # Check .env
   cat /var/www/eralms/.env
   
   # Critical variables:
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://lms.vabis.edu.vn
   DB_HOST=prod-db.vabis.edu.vn
   CACHE_DRIVER=redis
   QUEUE_CONNECTION=redis
   SESSION_DRIVER=redis
   
   # Verify all set correctly
   php artisan tinker
   >>> config('app.url')
   >>> config('database.default')
   >>> exit
   ```

2. **SSL/TLS Certificate** [SEC-001]
   ```bash
   # Check certificate expiration
   echo | openssl s_client -servername lms.vabis.edu.vn \
     -connect lms.vabis.edu.vn:443 2>/dev/null | \
     openssl x509 -noout -dates
   
   # Expected: notAfter should be > 30 days
   
   # Check certificate chain
   curl -I https://lms.vabis.edu.vn/
   # Should show 200 OK with HTTPS
   ```

3. **HTTPS Redirect** [SEC-002]
   ```bash
   # Test HTTP to HTTPS redirect
   curl -I http://lms.vabis.edu.vn/
   # Should return 301 or 302 redirect to https://
   ```

4. **Database Configuration** [CONFIG-003]
   ```bash
   # Test DB connection from app server
   php artisan tinker
   >>> DB::connection()->getPdo()
   >>> DB::select('SELECT 1')
   >>> exit
   ```

### Week 2: Security & Data Migration

#### Security Verification (Days -6 to -4)

1. **Security Testing** [SEC-005 through SEC-010]
   ```bash
   # Run security tests
   php artisan test tests/Security/SecurityAuditTest.php
   
   # Expected: All tests pass
   
   # Check for SQL injection vulnerabilities
   php artisan tinker
   >>> // Try malicious input
   >>> $courses = Course::where('title', "'; DROP TABLE courses; --")->get();
   >>> // Should safely escape, not execute
   >>> exit
   
   # Verify API authentication
   curl -X GET http://lms.vabis.edu.vn/api/v1/courses
   # Should return 401 Unauthorized
   
   curl -X GET http://lms.vabis.edu.vn/api/v1/courses \
     -H "Authorization: Bearer invalid_token"
   # Should return 401 Unauthorized
   ```

2. **Data Migration from Legacy System** [DATA-003]
   ```bash
   # Verify data migrated completely
   php artisan tinker
   
   >>> App\Models\LmsUser::where('code', 'like', 'GV%')->count()
   # Should match expected teacher count
   
   >>> App\Models\Enrollment::count()
   # Should match expected enrollment count
   
   >>> App\Models\ExamAttempt::count()
   # Should match expected attempt count
   
   >>> exit
   ```

3. **Data Integrity Check** [DATA-004]
   ```bash
   # Verify foreign key integrity
   php artisan tinker
   
   >>> DB::select("
       SELECT COUNT(*) FROM enrollments 
       WHERE user_id NOT IN (SELECT id FROM lms_users)
     ");
   # Should return 0 (no orphaned records)
   
   >>> exit
   ```

4. **Test Data Cleanup** [DATA-005]
   ```bash
   # Remove seed/test data
   php artisan db:seed --class=CleanupTestDataSeeder
   
   # Verify cleanup
   php artisan tinker
   >>> App\Models\LmsUser::where('email', 'like', '%@example.com')->count()
   # Should return 0
   >>> exit
   ```

#### Backup Verification (Days -3 to -1)

1. **Backup Solution** [BACKUP-001]
   ```bash
   # Test backup creation
   php artisan backup:run
   
   # Check backup location
   ls -lh storage/backups/
   
   # Expected: Recent backup file exists
   ```

2. **Backup Restoration Test** [BACKUP-004]
   ```bash
   # Restore to test database
   # NOTE: Do NOT do this on production!
   # Test on staging environment:
   
   # Create backup of current staging DB
   mysql -h staging-db -u root -p eralms > /tmp/staging_backup.sql
   
   # Restore from production backup
   mysql -h staging-db -u root -p eralms < /tmp/prod_backup.sql
   
   # Verify data integrity
   php artisan tinker
   >>> App\Models\Course::count()
   # Should match production count
   >>> exit
   ```

3. **Disaster Recovery Plan** [BACKUP-003]
   ```
   - [ ] Documented and reviewed
   - [ ] RTO: 4 hours
   - [ ] RPO: 1 hour (hourly backups)
   - [ ] Tested within past 30 days
   - [ ] Team trained on procedures
   ```

### Day 0 (T-0): Final Checks

#### Pre-GoLive Verification (Morning of GoLive)

1. **Complete Infrastructure Checklist**
   ```bash
   # Health checks
   curl https://lms.vabis.edu.vn/api/v1/health
   
   # Expected response:
   {
     "status": "healthy",
     "database": "ok",
     "cache": "ok",
     "queue": "ok",
     "storage": "ok",
     "timestamp": "2024-12-21T09:00:00Z"
   }
   ```

2. **Database Final Sync**
   ```bash
   # Pull any last-minute data from legacy system
   php artisan integration:sync-enrollments --force
   
   # Verify record counts match expected
   php artisan tinker
   >>> dump([
       'users' => App\Models\LmsUser::count(),
       'enrollments' => App\Models\Enrollment::count(),
       'courses' => App\Models\Course::count(),
     ]);
   >>> exit
   ```

3. **Monitoring Systems Activation**
   ```
   - [ ] Prometheus scraping metrics
   - [ ] Grafana dashboards loading
   - [ ] ELK stack collecting logs
   - [ ] Alert thresholds configured
   - [ ] On-call team notified
   ```

4. **Stakeholder Sign-Off**
   ```
   - [ ] CTO approval
   - [ ] Product Manager approval
   - [ ] Operations Manager approval
   - [ ] QA Lead sign-off
   - [ ] All critical issues resolved
   ```

---

## GoLive Day (T-0): Deployment Window

### Timeline: 2024-12-21, 08:00-12:00 UTC+7

#### 08:00 - Deployment Start

1. **Notify Stakeholders**
   ```bash
   # Send email/Slack alert
   "GoLive deployment started at 08:00. 
    Expected completion: 10:30. 
    Do NOT perform any administrative tasks."
   ```

2. **Health Check Baseline**
   ```bash
   # Record current state
   curl https://staging.vabis.edu.vn/api/v1/health > /tmp/baseline.json
   echo "Baseline recorded at $(date)"
   ```

3. **Enable Maintenance Mode** (Optional, if no zero-downtime deployment)
   ```bash
   php artisan down --message="Hệ thống đang bảo trì. Vui lòng quay lại sau."
   ```

#### 08:15 - Database Preparation

1. **Final Migration Run**
   ```bash
   php artisan migrate --force
   
   # Verify migrations completed
   php artisan migrate:status
   ```

2. **Final Data Sync**
   ```bash
   php artisan integration:sync-all --force
   
   # Verify sync completed
   php artisan tinker
   >>> Artisan::call('integration:sync-status');
   >>> exit
   ```

#### 08:30 - Application Deployment

1. **Pull Latest Code**
   ```bash
   cd /var/www/eralms
   git pull origin main
   ```

2. **Install Dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```

3. **Cache Optimization**
   ```bash
   php artisan optimize
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Clear Old Caches**
   ```bash
   php artisan cache:clear
   php artisan queue:clear
   ```

#### 08:50 - Queue Worker Restart

1. **Stop Old Workers**
   ```bash
   # Kill old queue processes gracefully
   php artisan queue:restart
   
   # Verify stopped
   ps aux | grep "queue:work"
   ```

2. **Start New Workers**
   ```bash
   # Start with supervisor
   sudo systemctl start eralms-queue-worker
   
   # Or manually (for testing)
   php artisan queue:work --tries=3 --timeout=90 &
   ```

3. **Start Scheduler** (if not already running)
   ```bash
   # Verify cron job exists
   crontab -l | grep "schedule:run"
   
   # Should show: * * * * * php /var/www/eralms/artisan schedule:run >> /dev/null 2>&1
   ```

#### 09:00 - Smoke Tests

1. **Basic Functionality Tests**
   ```bash
   # Test login
   curl -X POST https://lms.vabis.edu.vn/api/v1/auth/login \
     -d '{"email":"admin.lms@vabis.edu.vn","password":"admin123456"}'
   
   # Expected: Token returned (200 OK)
   
   # Test course list
   curl -X GET https://lms.vabis.edu.vn/api/v1/courses \
     -H "Authorization: Bearer $TOKEN"
   
   # Expected: List of courses (200 OK)
   ```

2. **Run Smoke Test Suite**
   ```bash
   php artisan test tests/Smoke --parallel
   
   # Expected: All tests pass
   ```

3. **Check Error Logs**
   ```bash
   tail -100 /var/log/eralms/laravel.log
   
   # Should contain no ERROR or CRITICAL entries
   ```

#### 09:15 - Disable Maintenance Mode**

```bash
php artisan up
```

#### 09:30 - End-to-End Verification

1. **Manual QA Spot Checks**
   - [ ] Login as teacher - able to access courses
   - [ ] Login as student - able to view enrolled courses
   - [ ] View course - content loads properly
   - [ ] Submit assignment - file upload works
   - [ ] Take quiz - questions display correctly
   - [ ] Download gradebook - no errors

2. **Monitor System Metrics**
   ```
   - [ ] CPU usage: < 50%
   - [ ] Memory usage: < 75%
   - [ ] Database connections: < 50
   - [ ] Response times: within targets
   - [ ] Error rate: < 0.1%
   ```

3. **User Communication**
   - [ ] Send "GoLive Complete" notification
   - [ ] Publish system status update
   - [ ] Provide user support contact

---

## Post-GoLive (T+1 to T+7)

### Day 1 Post-GoLive: Close Monitoring

1. **24/7 Monitoring**
   - On-call engineer present
   - Check dashboards every 30 minutes
   - Monitor for anomalies

2. **User Issue Response**
   ```
   - Critical (System down): 15 min response
   - High (Major feature broken): 1 hour response
   - Medium (Minor issues): 4 hours response
   - Low (Nice to have): Next business day
   ```

3. **Daily Standup**
   - 09:00 AM: Issues from overnight
   - 12:00 PM: Mid-day status
   - 17:00 PM: End-of-day summary

### Days 2-7: Stabilization

1. **Hotfix Protocol** (if issues found)
   ```bash
   # Create hotfix branch
   git checkout -b hotfix/issue-description
   
   # Fix issue
   # Test locally
   
   # Merge and deploy
   git checkout main
   git merge hotfix/issue-description
   git push origin main
   
   # Deploy hotfix
   # ... (same deployment steps as above)
   ```

2. **Performance Tuning**
   - Monitor slow queries
   - Optimize database indexes
   - Adjust cache settings
   - Fine-tune worker processes

3. **Data Validation**
   ```bash
   # Compare data between systems
   # Verify enrollments match SIS
   # Verify grades sync correctly
   # Check no data corruption
   ```

### Week 2+: Handover

1. **Operations Documentation**
   - Runbook for common issues
   - Escalation procedures
   - Maintenance windows
   - Backup procedures

2. **Knowledge Transfer**
   - Train operations team
   - Session with SIS team
   - Documentation review
   - Support contact setup

3. **GoLive Sign-Off**
   ```
   - [ ] CTO sign-off
   - [ ] Ops manager sign-off
   - [ ] Product manager acceptance
   - [ ] All critical issues resolved
   - [ ] Baseline performance verified
   ```

---

## Rollback Plan

**If critical issues prevent GoLive:**

```bash
# Immediate rollback to staging
git checkout previous-release
composer install
php artisan migrate:rollback
php artisan cache:clear

# Or restore from backup
mysql eralms < /backups/prod_backup_pre_golive.sql

# Notify stakeholders
# Schedule post-mortem
# Identify root cause
# Plan for re-attempt
```

---

## Contacts During GoLive

| Role | Name | Phone | Email |
|------|------|-------|-------|
| On-Call Engineer | _______ | _______ | _______ |
| Database Admin | _______ | _______ | _______ |
| Infrastructure | _______ | _______ | _______ |
| Product Lead | _______ | _______ | _______ |
| CTO | _______ | _______ | _______ |

---

## GoLive Sign-Off

**Date of GoLive**: 2024-12-21  
**Status**: [ ] Successful  [ ] Rolled Back

### Approvals

- **Deployment Engineer**: _______________________ Date: _______
- **QA Lead**: _______________________ Date: _______
- **Operations Manager**: _______________________ Date: _______
- **Product Manager**: _______________________ Date: _______
- **CTO**: _______________________ Date: _______

### Issues Encountered

```
1. _______________________________________________
   Resolution: ___________________________________
   
2. _______________________________________________
   Resolution: ___________________________________
```

### Lessons Learned

```
1. What went well: ______________________________
2. What to improve: _____________________________
3. Next steps: _________________________________
```

---

## Post-GoLive Success Metrics (T+7)

```
Uptime Target: > 99.9%      Actual: ______%
Error Rate: < 0.1%           Actual: ______%
Avg Response Time: < 500ms   Actual: ______ms
User Adoption: > 80%         Actual: ______%
Support Tickets: < 50        Actual: ______
Critical Issues: 0           Actual: ______
```

**Status**: [ ] GoLive Successful [ ] GoLive Requires Follow-Up

