# API Operations Center

API Operations Center centralizes API registry, internal gateway logging, webhooks, event bus, data mapping, sync jobs, health snapshots, test console, data contracts, and audit logs for EraLMS/SIS integrations.

## Backend Surface

Tables are created by `2026_06_11_000001_create_api_operations_center_tables.php`:

- `api_systems`, `api_endpoints`, `api_credentials`
- `api_requests`, `api_events`
- `api_webhook_endpoints`, `api_webhook_deliveries`
- `api_data_mappings`, `api_entity_mappings`
- `api_sync_jobs`, `api_sync_job_items`
- `api_data_contracts`, `api_health_snapshots`
- `api_error_rules`, `api_audit_logs`

Main services live under `App\Services\ApiOperations`:

- `ApiRegistryService`
- `ApiGatewayService`
- `DataMappingService`
- `EntityMappingService`
- `EventBusService`
- `WebhookService`
- `SyncJobService`
- `ApiHealthService`
- `ApiConsoleService`
- `ApiAlertService`

Queue jobs live under `App\Jobs\ApiOperations` and use:

- `api-events`
- `api-webhooks`
- `api-sync`
- `api-retry`
- `api-health`
- `api-alerts`

## API Routes

All routes are under `/api/v1/api-ops` and use tenant resolution plus granular permissions.

- `GET|POST /systems`, `PUT /systems/{id}`, `POST /systems/{id}/test-connection`
- `GET|POST /endpoints`, `PUT /endpoints/{id}`, `POST /endpoints/{id}/test`
- `GET /requests`
- `GET /events`, `POST /events/{id}/retry`, `POST /events/{id}/ignore`
- `GET|POST /webhooks`, `POST /webhooks/{id}/test`, `POST /webhooks/{id}/retry-failed`
- `GET|POST /mappings`, `POST /mappings/validate`
- `GET /entity-mappings`, `POST /entity-mappings/resolve-conflict`
- `GET|POST /sync-jobs`, `POST /sync-jobs/{id}/run|retry|cancel`
- `GET /health`, `POST /health/check-now`
- `GET|POST /contracts`, `PUT /contracts/{id}`, `POST /contracts/{id}/activate`
- `POST /console/test-request`

Inbound webhooks use `POST /api/v1/api-ops/webhooks/inbound/{systemCode}` with `X-Api-Ops-Signature` HMAC and throttling.

## Security Rules

- Credentials and webhook secrets are encrypted when created through services.
- Gateway logs recursively mask `password`, `token`, `api_key`, `authorization`, `secret`, and endpoint `sensitive_fields`.
- Users without `api_ops.payload.view` can list logs but receive masked payload placeholders.
- Console calls to production systems require `api_ops.secret.manage`.
- Webhooks use HMAC SHA-256 signatures.
- Events are idempotent by `tenant_id + event_key + idempotency_key`.
- Config changes are recorded in `api_audit_logs`.

## Permissions

- `api_ops.view`
- `api_ops.system.manage`
- `api_ops.endpoint.manage`
- `api_ops.request.view`
- `api_ops.payload.view`
- `api_ops.secret.manage`
- `api_ops.webhook.manage`
- `api_ops.mapping.manage`
- `api_ops.sync.run`
- `api_ops.sync.retry`
- `api_ops.health.view`
- `api_ops.console.use`
- `api_ops.contract.manage`

Seeded roles include `integration_admin` for operations and `api_ops_viewer` for masked log viewing.

## Artisan Commands

```bash
php artisan api-ops:build-snapshots
php artisan api-ops:health-check
php artisan api-ops:retry-failed
php artisan api-ops:cleanup-logs
php artisan api-ops:system-check
```

## Standard Flows

SIS to LMS student sync:

```text
sis.student.updated
-> inbound webhook
-> HMAC verification
-> idempotency check
-> api_events
-> api-events queue
-> data mapping
-> entity mapping lookup
-> LMS update
-> audit log
-> event success
```

LMS to SIS grade push:

```text
grade.approved
-> lms.grade.approved event
-> mapping
-> gateway request
-> api_requests log
-> success mark synced
-> failure scheduled retry
```

## UI

The Vue UI is available at:

- `/admin/api-ops`
- `/admin/api-ops/registry`
- `/admin/api-ops/requests`
- `/admin/api-ops/events`
- `/admin/api-ops/webhooks`
- `/admin/api-ops/mappings`
- `/admin/api-ops/entity-mappings`
- `/admin/api-ops/sync-jobs`
- `/admin/api-ops/health`
- `/admin/api-ops/console`
- `/admin/api-ops/contracts`
