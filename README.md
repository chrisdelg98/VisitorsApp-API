# EFL Visitor App — API REST Backend

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql&logoColor=white)
![Sanctum](https://img.shields.io/badge/Sanctum-4.3-FF2D20?logo=laravel&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

RESTful API backend for the **EFL Visitor Management System**. Powers Android tablets at physical entry stations to register visitors, manage check-in/check-out, and capture identification photos and documents.

> Designed for full portability — switching databases or cloud providers requires only changes to `.env`.

---

## Features

- **Visitor registration** with document info, photo, and ID document capture
- **Check-in / check-out** tracking per station
- **Multi-station support** — each tablet authenticates independently via API Key
- **Admin panel endpoints** — visit history, filters, stats, station management
- **Admin user management** — create/deactivate admins, revoke sessions instantly (super_admin only)
- **Two-layer auth** — `X-API-Key` for tablets · Sanctum Bearer token for admins
- **Images served securely** — never publicly accessible, always behind auth
- **Audit log** — every sensitive admin action is logged to a daily rotating file
- **Fully versioned API** at `/api/v1/`
- **Database-agnostic** — MySQL, SQL Server, PostgreSQL with zero code changes

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| ORM | Eloquent |
| Authentication | Laravel Sanctum 4.3 |
| Database | MySQL 8.4 (dev) · SQL Server / PostgreSQL (prod-ready) |
| Image Storage | Laravel Storage (local → S3 / Azure Blob via config) |
| Web Server | IIS / Apache + mod_rewrite |
| PHP | 8.5+ |

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── Admin/            ← AuthController, VisitController, StationController
│   │   │                        StatsController, UserController
│   │   └── (tablet)          ← AuthController, VisitorController, VisitController
│   │                            ImageController, StationController
│   ├── Middleware/            ← API Key auth, HTTPS, security headers
│   ├── Requests/             ← input validation (FormRequests)
│   └── Resources/            ← JSON response shaping
├── Models/                   ← Station, Visitor, Visit, VisitImage, User
├── Services/                 ← ImageService (business logic)
└── Support/
    └── AuditLogger.php       ← structured audit log helper

database/
├── migrations/               ← versioned schema
└── seeders/                  ← default super_admin seed

routes/
└── api.php                   ← all routes

storage/
├── app/visitors/             ← images (local dev / cloud prod)
└── logs/audit-YYYY-MM-DD.log ← daily rotating audit log (90-day retention)

docs/
├── API.md                    ← full endpoint reference
└── ANDROID_INTEGRATION.md   ← step-by-step Android Studio guide
```

Architecture: `Request → Controller → Service → Eloquent → DB → Resource → JSON`

---

## API Endpoints

**Base URL:** `https://your-server/api/v1/`  
Full reference with request/response bodies → [`docs/API.md`](docs/API.md)

### Tablet endpoints (`X-API-Key`)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/auth/validate-station` | Exchange station code for API Key (setup only) |
| `GET` | `/station/me` | Authenticated station info |
| `GET` | `/visitors/search?q=` | Incremental search (min 2 chars, max 20 results) |
| `POST` | `/visitors` | Create visitor |
| `PUT` | `/visitors/{id}` | Update visitor |
| `GET` | `/visitors/{id}/latest-visit` | Last recorded visit |
| `POST` | `/visits` | Check-in |
| `GET` | `/visits/active` | Active visits at this station |
| `GET` | `/visits/{id}` | Visit detail |
| `PATCH` | `/visits/{id}/checkout` | Check-out |
| `POST` | `/visits/{id}/images` | Upload photo / document (multipart, max 5 MB) |
| `GET` | `/visits/{id}/images/{type}` | Stream image bytes |
| `GET` | `/ocr/templates` | Full published OCR template catalog (ETag → `304`) |
| `POST` | `/ocr/failed-documents` | Report an unreadable document for review |

### Admin endpoints (`Authorization: Bearer <token>`)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/admin/login` | Login — returns Bearer token |
| `POST` | `/admin/logout` | Revoke current token |
| `GET` | `/admin/me` | Current admin info |
| `GET` | `/admin/visits` | Visit list with filters + pagination |
| `GET` | `/admin/visits/{id}` | Visit detail |
| `PATCH` | `/admin/visits/{id}/status` | Change visit status |
| `GET` | `/admin/stats` | Aggregated metrics |
| `GET` | `/admin/stations` | List stations |
| `POST` | `/admin/stations` | Create station |
| `GET` | `/admin/users` | *(super_admin)* List admin users |
| `POST` | `/admin/users` | *(super_admin)* Create admin |
| `PATCH` | `/admin/users/{id}` | *(super_admin)* Edit / deactivate admin |
| `POST` | `/admin/users/{id}/revoke-tokens` | *(super_admin)* Force logout all sessions |

### Response envelope (uniform)

```json
{ "success": true, "data": { ... }, "message": "..." }
{ "success": false, "message": "...", "code": "VALIDATION_ERROR", "errors": { "campo": ["..."] } }
```

---

## Authentication

**Tablets** use a per-station `X-API-Key` header. The key is retrieved once via `POST /auth/validate-station` using the station code, then stored securely on the device. Each station's key can be regenerated without affecting others.

**Admins** log in via `POST /admin/login` and receive a Sanctum Bearer token. Only one active session per login (previous tokens are revoked on login). Token is invalidated immediately on logout or when a `super_admin` calls `revoke-tokens`.

**Roles:** `admin` (access to visits/stats/stations) · `super_admin` (everything + user management).

---

## Rate Limits

| Endpoint group | Limit |
|---|---|
| `POST /auth/validate-station` | 5 / hour / IP |
| `POST /admin/login` | 10 / hour / IP |
| Tablet JSON endpoints | 120 / min / station |
| Image uploads | 30 / min / station |
| `POST /ocr/failed-documents` | 20 / min / station |
| Admin endpoints | 200 / min / user |

Exceeding any limit returns `429 RATE_LIMIT_EXCEEDED` with a `Retry-After` header.

---

## Audit Log

All sensitive admin actions are written to `storage/logs/audit-YYYY-MM-DD.log` (daily rotation, 90-day retention configurable via `LOG_AUDIT_DAYS` env).

Logged events: `admin.login.success/failed/denied`, `admin.logout`, `admin.visit.status_changed`, `admin.station.created`, `admin.user.created/updated/tokens_revoked`, `tablet.visit.reentry/reentry_created/cross_station_lookup`, `tablet.ocr.catalog_synced`, `tablet.ocr.failed_document_reported`, `system.ocr.retention_purge`.

OCR entries never carry the reported content itself (`ocr_text` / `ocr_blocks` are PII) — only the row id and whether text/image were attached.

Each entry includes: `user_id`, `user_email`, `ip`, `user_agent`, and action-specific context fields.

---

## Getting Started

### Requirements

- PHP 8.5+
- Composer
- MySQL 8.4+

### Installation

```bash
# 1. Install dependencies
composer install

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Configure your database in .env, then run migrations + seed
php artisan migrate --seed
```

### Key environment variables

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=visitors_dev
DB_USERNAME=your_user
DB_PASSWORD=your_password

APP_URL=https://your-server
FILESYSTEM_DISK=local

LOG_AUDIT_DAYS=90

# OCR review-queue retention (personal data) — see config/ocr.php
OCR_FAILED_TEXT_RETENTION_DAYS=15
OCR_FAILED_RESOLVED_RETENTION_DAYS=30
OCR_FAILED_RETENTION_DAYS=90
```

The retention job runs daily at 03:15 via the scheduler, so the server must run
`php artisan schedule:run` every minute (cron / Task Scheduler).

The seeder creates a default `super_admin` — **change the password before going to production**.

> Migrating to Azure or swapping databases = update `.env` only. No code changes required.

---

## Security Highlights

- HTTPS enforced on every request — HTTP redirected automatically
- Rate limiting on all sensitive endpoints
- Security headers on every response (`HSTS`, `X-Frame-Options`, `CSP`, etc.)
- SQL Injection prevented by design — Eloquent uses prepared statements exclusively
- Images stored outside `public/`, served only through authenticated endpoints
- Audit log for all admin actions — logs never contain passwords or full tokens
- Deactivating an admin user instantly revokes all active Bearer tokens

---

## Documentation

| Document | Description |
|---|---|
| [`docs/API.md`](docs/API.md) | Full endpoint reference — auth, request bodies, responses, error codes |
| [`docs/ANDROID_INTEGRATION.md`](docs/ANDROID_INTEGRATION.md) | Step-by-step Android Studio integration guide (Retrofit, offline-first, sync queue) |
| [`API_EXECUTION_PLAN.md`](API_EXECUTION_PLAN.md) | Full technical plan — DB schema, security policy, phased roadmap |

---

*EFL Visitor App Backend · Laravel 13 · May 2026*
