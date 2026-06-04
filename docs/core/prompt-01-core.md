# Prompt 01 — EraLMS Core Architecture

Prompt 01 builds the independent EraLMS foundation used by the following course, quiz, gradebook and SIS prompts.

## Tenant resolution

`TenantResolver` resolves tenant in this order:

1. Header `X-Tenant-Code`.
2. Exact request domain stored in `tenants.domain`.
3. Subdomain code fallback.
4. Default tenant configured by `ERALMS_DEFAULT_TENANT`, seeded as `VABIS`.

The resolved tenant is stored in `TenantContext`, request attributes and the container key `eralms.tenant`.

## RBAC model

- `roles` can be global (`tenant_id = null`) or tenant-specific.
- `permissions.key` is the canonical check key such as `core.user.update`.
- `role_permission` links roles to permissions.
- `user_role_scope` assigns a role to a user for tenant/campus/faculty/course/class scopes.
- `CorePermissionService` caches scoped permissions in Redis-compatible cache keys and invalidates menu cache when roles change.

## Audit and settings

`AuditLogService` records create/update/delete/lock/unlock/approve/publish style actions with before/after JSON, IP and user agent. Set `ERALMS_AUDIT_QUEUE=true` to dispatch `WriteAuditLog` after the response and avoid slowing the main request. `SettingService` caches tenant settings by `tenant/group/key`.

## Core APIs

- `GET /api/v1/core/me`
- `GET /api/v1/core/dashboard`
- `GET /api/v1/core/tenants`
- `GET /api/v1/core/organizations`
- `GET /api/v1/core/campuses`
- `GET /api/v1/core/academic-units`
- `GET /api/v1/core/users`
- `POST /api/v1/core/users`
- `PUT /api/v1/core/users/{user}`
- `POST /api/v1/core/users/{user}/lock`
- `POST /api/v1/core/users/{user}/unlock`
- `GET /api/v1/core/roles`
- `GET /api/v1/core/permissions`
- `POST /api/v1/core/users/{user}/roles`
- `PUT /api/v1/core/roles/{role}/permissions`
- `GET /api/v1/core/settings`
- `PUT /api/v1/core/settings`
- `PUT /api/v1/core/white-label`
- `GET /api/v1/core/audit-logs`

## Seed data

`CoreSeeder` is idempotent and creates:

- Tenant `VABIS LMS`.
- Campuses `Vũng Tàu` and `Online Campus`.
- 19 academic units/departments.
- Full RBAC roles and permissions.
- Demo users and role scopes.
- 90 teacher/staff accounts and 5000 student accounts.
- Compact UI and menu settings.

## UI shell

Vue pages provide a compact enterprise admin shell with sidebar, header, breadcrumb, search, tenant selector, campus selector, notification button and profile button. Prompt 01 admin screens are available for dashboard, tenant, campus, academic units, users, role/permission, white label settings and audit logs.
