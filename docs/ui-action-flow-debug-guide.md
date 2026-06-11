# UI Action Flow Debug Guide

## Standard Contract

Every actionable button must provide:

- `data-action-key` through `ActionButton`
- loading state
- disabled state while running
- confirm for dangerous actions
- success or error toast
- reload or local state update after success

Use:

- `resources/js/Composables/useLmsAction.ts`
- `resources/js/Components/Lms/ActionButton.vue`
- `resources/js/Components/Lms/ActionBar.vue`
- `resources/js/Components/Lms/FormDrawer.vue`
- `resources/js/Components/Lms/DataTable.vue`

## API Response Contract

Successful action:

```json
{
  "success": true,
  "message": "Thao tác thành công",
  "data": {},
  "meta": {}
}
```

Failed action:

```json
{
  "success": false,
  "message": "Không thể thực hiện thao tác",
  "errors": {},
  "code": "ACTION_FAILED"
}
```

Use `App\Support\ApiResponse` in controllers and exception handlers.

## Action Registry

The source of truth is `lms_action_registry`.

Scan in UI:

```text
/admin/lms/action-check
```

Scan in CLI:

```bash
php artisan lms:sync-actions
php artisan lms:scan-actions
php artisan lms:check-routes
php artisan lms:check-permissions
php artisan lms:smoke-actions
```

Grant admin:

```bash
php artisan lms:grant-admin-actions admin.lms@vabis.edu.vn
```

## Common Failures

- `Missing route`: registry route does not match a Laravel route.
- `Missing permission`: permission key is not in `permissions`.
- `Missing controller`: route points to a missing controller method.
- `Missing frontend handler`: shared ActionButton/useLmsAction components are missing.
- `danger_without_confirm`: action key looks destructive but `confirm_required=false`.

## Flow Smoke Checklist

- Course publish: `draft -> review -> approved -> published`.
- Exam attempt: no submit after `submitted`.
- Assignment grade: only submitted assignments can be graded.
- Gradebook: approval and lock actions require confirm.
- Attendance lock and SIS sync require confirm.

Run after route/controller changes:

```bash
php artisan optimize:clear
php artisan lms:sync-actions
php artisan lms:sync-permissions
php artisan lms:grant-admin-actions admin.lms@vabis.edu.vn
php artisan lms:smoke-actions
npm run build
```
