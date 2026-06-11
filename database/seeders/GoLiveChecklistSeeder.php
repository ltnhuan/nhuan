<?php

namespace Database\Seeders;

use App\Models\GoLiveChecklistItem;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * GoLive Checklist Seeder
 * 
 * Populates comprehensive GoLive checklist with 50+ verification tasks
 * Organized into 8 categories with priorities and dependencies
 */
class GoLiveChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('code', 'VABIS')->first() ?? Tenant::first();
        
        if (!$tenant) {
            $this->command->error('No tenant found. Run CoreSeeder first.');
            return;
        }
        
        echo "\n🚀 Creating GoLive Checklist Items...\n";
        
        $categories = [
            'infrastructure' => $this->getInfrastructureChecks(),
            'configuration' => $this->getConfigurationChecks(),
            'security' => $this->getSecurityChecks(),
            'data_migration' => $this->getDataMigrationChecks(),
            'backup_recovery' => $this->getBackupRecoveryChecks(),
            'monitoring' => $this->getMonitoringChecks(),
            'integration' => $this->getIntegrationChecks(),
            'documentation' => $this->getDocumentationChecks(),
        ];
        
        $sequence = 1;
        
        foreach ($categories as $category => $items) {
            foreach ($items as $item) {
                GoLiveChecklistItem::create(array_merge($item, [
                    'tenant_id' => $tenant->id,
                    'category' => $category,
                    'sequence' => $sequence++,
                ]));
                
                echo "  ✓ {$item['title']}\n";
            }
        }
        
        echo "\n✅ GoLive Checklist created with " . ($sequence - 1) . " items\n\n";
    }

    protected function getInfrastructureChecks()
    {
        return [
            [
                'item_code' => 'INFRA-001',
                'title' => 'Production Server Setup',
                'description' => 'Verify production servers configured, tested, and ready',
                'verification_method' => 'SSH login, server checks, health endpoint',
                'estimated_hours' => 4,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'INFRA-002',
                'title' => 'Database Server Setup',
                'description' => 'Verify database server configured, optimized, and tested',
                'verification_method' => 'Database connection test, query performance check',
                'estimated_hours' => 3,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'INFRA-003',
                'title' => 'Redis Cache Server',
                'description' => 'Verify Redis cluster configured for caching and sessions',
                'verification_method' => 'redis-cli ping, connection test',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'INFRA-004',
                'title' => 'Message Queue Setup (RabbitMQ/Redis)',
                'description' => 'Verify message queue for background jobs configured',
                'verification_method' => 'Queue health check, test job dispatch',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['INFRA-003'],
            ],
            [
                'item_code' => 'INFRA-005',
                'title' => 'Storage Solution Setup',
                'description' => 'Verify file storage (S3/MinIO) configured with sufficient space',
                'verification_method' => 'Storage connection test, capacity check',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'INFRA-006',
                'title' => 'CDN Configuration',
                'description' => 'Verify CDN setup for video delivery and static assets',
                'verification_method' => 'CDN cache test, origin shield verification',
                'estimated_hours' => 3,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['INFRA-005'],
            ],
            [
                'item_code' => 'INFRA-007',
                'title' => 'Load Balancer Configuration',
                'description' => 'Verify load balancer configured with health checks',
                'verification_method' => 'Load balancing test, health check endpoint',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'INFRA-008',
                'title' => 'Network & Firewall Rules',
                'description' => 'Verify network access, firewall rules, VPN access',
                'verification_method' => 'Network connectivity tests, port verification',
                'estimated_hours' => 2,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
        ];
    }

    protected function getConfigurationChecks()
    {
        return [
            [
                'item_code' => 'CONFIG-001',
                'title' => 'Environment Variables',
                'description' => 'Verify all .env variables configured correctly',
                'verification_method' => 'Review .env file, verify all required vars set',
                'estimated_hours' => 1,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'CONFIG-002',
                'title' => 'Application Configuration',
                'description' => 'Verify app config (APP_URL, timezone, locale, etc)',
                'verification_method' => 'Review config/app.php',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['CONFIG-001'],
            ],
            [
                'item_code' => 'CONFIG-003',
                'title' => 'Database Configuration',
                'description' => 'Verify database config, connection pooling',
                'verification_method' => 'Test DB connection, verify pool settings',
                'estimated_hours' => 1,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => ['CONFIG-001'],
            ],
            [
                'item_code' => 'CONFIG-004',
                'title' => 'Cache & Session Config',
                'description' => 'Verify cache and session drivers configured',
                'verification_method' => 'Test cache reads/writes, session functionality',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['CONFIG-001', 'INFRA-003'],
            ],
            [
                'item_code' => 'CONFIG-005',
                'title' => 'Queue Configuration',
                'description' => 'Verify queue driver and worker configuration',
                'verification_method' => 'Test job dispatch and processing',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['CONFIG-001', 'INFRA-004'],
            ],
            [
                'item_code' => 'CONFIG-006',
                'title' => 'Logging Configuration',
                'description' => 'Verify logging configured for errors and performance',
                'verification_method' => 'Check log files, verify error logging',
                'estimated_hours' => 1,
                'priority' => 'medium',
                'risk_level' => 'medium',
                'dependencies' => ['CONFIG-001'],
            ],
            [
                'item_code' => 'CONFIG-007',
                'title' => 'Email Configuration',
                'description' => 'Verify email/SMTP configured for notifications',
                'verification_method' => 'Send test email, verify delivery',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['CONFIG-001'],
            ],
        ];
    }

    protected function getSecurityChecks()
    {
        return [
            [
                'item_code' => 'SEC-001',
                'title' => 'SSL/TLS Certificate',
                'description' => 'Verify SSL certificate installed and valid',
                'verification_method' => 'SSL test (ssl-labs.com), certificate validation',
                'estimated_hours' => 2,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'SEC-002',
                'title' => 'HTTPS Redirect',
                'description' => 'Verify all HTTP traffic redirected to HTTPS',
                'verification_method' => 'Test HTTP to HTTPS redirect',
                'estimated_hours' => 1,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => ['SEC-001'],
            ],
            [
                'item_code' => 'SEC-003',
                'title' => 'CORS Configuration',
                'description' => 'Verify CORS headers properly configured',
                'verification_method' => 'Test CORS requests, verify whitelist',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'SEC-004',
                'title' => 'CSRF Protection',
                'description' => 'Verify CSRF token generation and validation',
                'verification_method' => 'Test form submissions with/without token',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'SEC-005',
                'title' => 'SQL Injection Prevention',
                'description' => 'Verify parameterized queries used throughout',
                'verification_method' => 'Code review, penetration testing',
                'estimated_hours' => 2,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'SEC-006',
                'title' => 'Authentication Testing',
                'description' => 'Verify authentication mechanisms work correctly',
                'verification_method' => 'Test login, token generation, session validation',
                'estimated_hours' => 2,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'SEC-007',
                'title' => 'Authorization Testing',
                'description' => 'Verify role-based access control enforced',
                'verification_method' => 'Test unauthorized access attempts',
                'estimated_hours' => 2,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'SEC-008',
                'title' => 'Sensitive Data Encryption',
                'description' => 'Verify passwords, API keys, PII encrypted',
                'verification_method' => 'Check encryption in transit and at rest',
                'estimated_hours' => 2,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'SEC-009',
                'title' => 'API Rate Limiting',
                'description' => 'Verify API rate limiting and DDoS protection',
                'verification_method' => 'Load test API with high request rate',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'SEC-010',
                'title' => 'Security Headers',
                'description' => 'Verify security headers (CSP, X-Frame-Options, etc)',
                'verification_method' => 'Check response headers, security scan',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
        ];
    }

    protected function getDataMigrationChecks()
    {
        return [
            [
                'item_code' => 'DATA-001',
                'title' => 'Database Migrations',
                'description' => 'Verify all migrations run successfully',
                'verification_method' => 'Run migrations, verify database schema',
                'estimated_hours' => 2,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => ['CONFIG-003'],
            ],
            [
                'item_code' => 'DATA-002',
                'title' => 'Seed Data Validation',
                'description' => 'Verify seed data loaded and complete',
                'verification_method' => 'Check row counts, data integrity',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['DATA-001'],
            ],
            [
                'item_code' => 'DATA-003',
                'title' => 'Data Migration from Old System',
                'description' => 'Verify data migrated from legacy system',
                'verification_method' => 'Data reconciliation, count verification',
                'estimated_hours' => 4,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => ['DATA-001'],
            ],
            [
                'item_code' => 'DATA-004',
                'title' => 'Data Integrity Checks',
                'description' => 'Verify data integrity, foreign keys, constraints',
                'verification_method' => 'Database integrity scan, referential checks',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['DATA-003'],
            ],
            [
                'item_code' => 'DATA-005',
                'title' => 'Test Data Cleanup',
                'description' => 'Remove all test/seed data before production',
                'verification_method' => 'Review database, verify demo data removed',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['DATA-004'],
            ],
        ];
    }

    protected function getBackupRecoveryChecks()
    {
        return [
            [
                'item_code' => 'BACKUP-001',
                'title' => 'Backup Solution Setup',
                'description' => 'Verify backup system configured and tested',
                'verification_method' => 'Backup test, restore procedure validation',
                'estimated_hours' => 3,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'BACKUP-002',
                'title' => 'Automated Backup Scheduling',
                'description' => 'Verify automated daily/hourly backups configured',
                'verification_method' => 'Check backup logs, verify schedule',
                'estimated_hours' => 1,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => ['BACKUP-001'],
            ],
            [
                'item_code' => 'BACKUP-003',
                'title' => 'Disaster Recovery Plan',
                'description' => 'Verify DR plan documented and tested',
                'verification_method' => 'Review DR document, conduct DR drill',
                'estimated_hours' => 4,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => ['BACKUP-001'],
            ],
            [
                'item_code' => 'BACKUP-004',
                'title' => 'Backup Verification',
                'description' => 'Verify backups can be restored successfully',
                'verification_method' => 'Test restore from backup, verify data integrity',
                'estimated_hours' => 3,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => ['BACKUP-001'],
            ],
            [
                'item_code' => 'BACKUP-005',
                'title' => 'Geo-Redundant Storage',
                'description' => 'Verify backups replicated to remote location',
                'verification_method' => 'Check backup location, replication verification',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['BACKUP-001'],
            ],
        ];
    }

    protected function getMonitoringChecks()
    {
        return [
            [
                'item_code' => 'MON-001',
                'title' => 'Monitoring & Alerting System',
                'description' => 'Verify monitoring system deployed (Prometheus/Grafana)',
                'verification_method' => 'Access monitoring dashboard, verify metrics',
                'estimated_hours' => 3,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'MON-002',
                'title' => 'Log Aggregation (ELK Stack)',
                'description' => 'Verify log collection and search working',
                'verification_method' => 'Access Kibana, search for log entries',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'MON-003',
                'title' => 'Uptime Monitoring',
                'description' => 'Verify uptime monitoring configured',
                'verification_method' => 'Health check endpoint responds',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'MON-004',
                'title' => 'Performance Monitoring',
                'description' => 'Verify performance metrics being collected',
                'verification_method' => 'Check APM metrics, response times',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['MON-001'],
            ],
            [
                'item_code' => 'MON-005',
                'title' => 'Error Rate Monitoring',
                'description' => 'Verify error monitoring and alerting configured',
                'verification_method' => 'Trigger test error, verify alert received',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['MON-001'],
            ],
            [
                'item_code' => 'MON-006',
                'title' => 'On-Call Rotation Setup',
                'description' => 'Verify on-call team and escalation configured',
                'verification_method' => 'Review on-call schedule, verify notifications',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
        ];
    }

    protected function getIntegrationChecks()
    {
        return [
            [
                'item_code' => 'INT-001',
                'title' => 'SIS Integration Testing',
                'description' => 'Verify SIS connectivity and data sync',
                'verification_method' => 'Test API connection, verify data sync',
                'estimated_hours' => 3,
                'priority' => 'critical',
                'risk_level' => 'critical',
                'dependencies' => [],
            ],
            [
                'item_code' => 'INT-002',
                'title' => 'Email Service Integration',
                'description' => 'Verify email service (SendGrid/AWS SES) working',
                'verification_method' => 'Send test email, verify delivery',
                'estimated_hours' => 1,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => ['CONFIG-007'],
            ],
            [
                'item_code' => 'INT-003',
                'title' => 'Payment Gateway Integration',
                'description' => 'Verify payment gateway (Stripe/PayPal) configured',
                'verification_method' => 'Test payment transaction in sandbox',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'INT-004',
                'title' => 'Authentication Service (SSO/LDAP)',
                'description' => 'Verify SSO/LDAP integration working',
                'verification_method' => 'Test SSO login, verify user provisioning',
                'estimated_hours' => 2,
                'priority' => 'high',
                'risk_level' => 'high',
                'dependencies' => [],
            ],
            [
                'item_code' => 'INT-005',
                'title' => 'Third-Party API Integrations',
                'description' => 'Verify all third-party APIs tested and working',
                'verification_method' => 'Test API calls, verify response format',
                'estimated_hours' => 2,
                'priority' => 'medium',
                'risk_level' => 'medium',
                'dependencies' => [],
            ],
        ];
    }

    protected function getDocumentationChecks()
    {
        return [
            [
                'item_code' => 'DOC-001',
                'title' => 'Administrator Guide',
                'description' => 'Verify admin documentation complete and accurate',
                'verification_method' => 'Review documentation, verify all tasks covered',
                'estimated_hours' => 2,
                'priority' => 'medium',
                'risk_level' => 'medium',
                'dependencies' => [],
            ],
            [
                'item_code' => 'DOC-002',
                'title' => 'Teacher User Guide',
                'description' => 'Verify teacher guide for course management',
                'verification_method' => 'Review and test all documented tasks',
                'estimated_hours' => 2,
                'priority' => 'medium',
                'risk_level' => 'medium',
                'dependencies' => [],
            ],
            [
                'item_code' => 'DOC-003',
                'title' => 'Student Quick Start Guide',
                'description' => 'Verify student onboarding guide complete',
                'verification_method' => 'Review documentation',
                'estimated_hours' => 1,
                'priority' => 'medium',
                'risk_level' => 'medium',
                'dependencies' => [],
            ],
            [
                'item_code' => 'DOC-004',
                'title' => 'API Documentation',
                'description' => 'Verify API documentation generated and complete',
                'verification_method' => 'Review Swagger/OpenAPI docs',
                'estimated_hours' => 2,
                'priority' => 'medium',
                'risk_level' => 'medium',
                'dependencies' => [],
            ],
            [
                'item_code' => 'DOC-005',
                'title' => 'Troubleshooting Guide',
                'description' => 'Verify troubleshooting guide with common issues',
                'verification_method' => 'Review guide completeness',
                'estimated_hours' => 2,
                'priority' => 'medium',
                'risk_level' => 'medium',
                'dependencies' => [],
            ],
        ];
    }
}
