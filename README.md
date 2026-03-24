# EFL Visitor App — API REST Backend

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

RESTful API backend for the **EFL Visitor Management System**. Powers Android tablets at physical entry stations to register visitors, manage check-in/check-out, and capture identification photos and documents.

> Designed for full portability — switching databases or cloud providers requires only changes to `.env`.

---

## Features

- **Visitor registration** with document info, photo, and ID document capture
- **Check-in / check-out** tracking per station
- **Multi-station support** — each tablet authenticates independently via API Key
- **Admin panel endpoints** — visit history, filters, stats, station management
- **Two-layer authentication** — API Key for tablets, Bearer Token for admins
- **Images served securely** — never publicly accessible, always behind auth
- **Fully versioned API** starting at `/api/v1/`
- **Database-agnostic** — MySQL, SQL Server, PostgreSQL with zero code changes

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| ORM | Eloquent |
| Authentication | Laravel Sanctum |
| Database | MySQL (dev) · SQL Server / PostgreSQL (prod-ready) |
| Image Storage | Laravel Storage (local → S3 / Azure Blob via config) |
| Web Server | Apache + mod_rewrite |
| PHP | 8.2+ |

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/Api/V1/   ← versioned controllers
│   ├── Middleware/            ← API Key auth, HTTPS, security headers
│   └── Requests/             ← input validation (prevents injection)
├── Models/                   ← Eloquent: Station, Visitor, Visit, VisitImage
├── Repositories/             ← all DB queries isolated here
├── Services/                 ← business logic (VisitorService, VisitService, ImageService)
└── Resources/                ← JSON response formatting

database/
├── migrations/               ← versioned schema
└── seeders/                  ← test data

routes/
└── api.php                   ← all API routes

storage/app/visitors/         ← images (local dev / cloud prod)
```

The architecture follows a **Repository + Service + Resource** pattern:

```
Request → Controller → Service → Repository → Eloquent → DB
                           ↓
                       Resource → JSON Response
```

---

## API Endpoints

**Base URL:** `https://visitors-api.yourdomain.com/api/v1/`

| Group | Method | Endpoint | Description |
|---|---|---|---|
| **Auth** | `POST` | `/auth/validate-station` | Validate station code, receive API Key |
| | `GET` | `/station/me` | Authenticated station info |
| **Visitors** | `GET` | `/visitors/search?q=` | Search visitors by name |
| | `POST` | `/visitors` | Create new visitor |
| | `PUT` | `/visitors/{id}` | Update visitor |
| | `GET` | `/visitors/{id}/latest-visit` | Last visit for a visitor |
| **Visits** | `POST` | `/visits` | Check-in (new visit) |
| | `PATCH` | `/visits/{id}/checkout` | Check-out |
| | `GET` | `/visits/{id}` | Visit details |
| | `GET` | `/visits/active` | Active visits at this station |
| **Images** | `POST` | `/visits/{id}/images` | Upload photo / document |
| | `GET` | `/visits/{id}/images/{type}` | Retrieve image by type |
| **Admin** | `POST` | `/admin/login` | Admin login |
| | `GET` | `/admin/visits` | Visit list with filters and pagination |
| | `GET` | `/admin/stats` | Stats by station and period |
| | `GET/POST` | `/admin/stations` | List or create stations |

All responses follow a consistent envelope:

```json
{ "success": true, "data": { ... }, "message": "Visit registered successfully" }
{ "success": false, "message": "Validation failed", "errors": { ... }, "code": "VALIDATION_ERROR" }
```

---

## Authentication

**Tablets** authenticate with a per-station `X-API-Key` header. Keys are generated when a station is registered and can be revoked individually without affecting other stations.

**Admins** authenticate via `POST /admin/login` and receive a Bearer Token (24h expiration) managed by Laravel Sanctum.

---

## Getting Started

### Requirements

- PHP 8.2+
- Composer
- MySQL 8.0+

### Installation

```bash
# 1. Install dependencies
composer install

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Configure your database in .env, then run migrations
php artisan migrate

# 4. (Optional) Seed with test data
php artisan db:seed
```

### Environment

All environment-specific config lives in `.env` — the code has no hardcoded credentials or connection strings.

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=visitors_dev
DB_USERNAME=your_user
DB_PASSWORD=your_password

APP_URL=https://visitors-api.yourdomain.com
FILESYSTEM_DISK=local
```

> Migrating to Azure or changing databases = update `.env` + import data. No code changes required.

---

## Security Highlights

- HTTPS enforced on every request — HTTP redirected automatically
- Rate limiting on all sensitive endpoints (5 attempts/hour on station auth, 10 on admin login)
- Security headers on every response (`HSTS`, `X-Frame-Options`, `CSP`, etc.)
- SQL Injection prevented by design — Eloquent uses prepared statements exclusively
- Images stored outside `public/`, served only through authenticated endpoints
- Logs never contain passwords or full tokens

---

## Full Technical Specification

See [API_EXECUTION_PLAN.md](API_EXECUTION_PLAN.md) for the complete technical plan including database schema, security policies, rate limits, and phased execution roadmap.

---

*EFL Visitor App Backend · Laravel 12 · March 2026*
