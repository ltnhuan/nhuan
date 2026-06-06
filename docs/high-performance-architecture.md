# EraLMS High Performance Architecture

## Production Runtime

- Cache: Redis, DB 1.
- Queue: Redis, DB 2.
- Queue dashboard: Laravel Horizon on Linux/container with `ext-pcntl` and `ext-posix`.
- Storage: S3-compatible object storage through `object_storage`.
- CDN: set `ERALMS_CDN_ENABLED=true`, `ERALMS_CDN_URL`, and `ASSET_URL`.
- Database: PostgreSQL read/write split via `DB_WRITE_HOST` and comma-separated `DB_READ_HOSTS`.

## Dedicated Queues

- `default`
- `video-processing`
- `quiz-grading`
- `assignment-processing`
- `analytics`
- `notification`
- `certificate`
- `sync-sis`
- `ai`

Run workers through Horizon after installing it in the production image:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

On Windows development hosts, Horizon install is blocked unless PHP has `pcntl` and `posix`. Keep local testing on `QUEUE_CONNECTION=sync` or `database`.

## Health Check

`GET /api/v1/health` reports:

- app
- db
- redis
- queue
- storage
- sis connection
- cdn config
- cache

## Load Seed

Enable the load profile only when needed:

```bash
ERALMS_SEED_PERFORMANCE_LOAD=true php artisan db:seed --class=PerformanceLoadSeeder
```

The seeder creates 5,000 learners, 200 courses, 1,000 lessons/components, and 100,000 learning events.

## Event Tables

The migration `2026_06_05_000001_add_high_performance_architecture.php` adds composite indexes, monitoring tables, an event partition registry, and PostgreSQL append-only guards for:

- `learning_progress_events`
- `video_watch_events`
- `exam_attempt_events`
- `audit_logs`
- `integration_events`
