# MELT-B MVP  
Thermal Analysis & Building Energy Efficiency Platform

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="280" alt="Laravel Logo" />
</p>

---

## Project Overview

MELT-B is an API-first platform that helps municipalities, researchers, and energy consultants discover **building heat-loss anomalies** and prioritise retrofits.

Core responsibilities:

- Ingest **pre-computed anomaly CSVs** delivered by the Data-Science team
- Store and serve spatial building data (PostGIS) with fine-grained **attribute-based access control (ABAC)**
- Provide a **React SPA** for interactive map visualisation & analytics
- Offer an **AdminLTE dashboard** for user, entitlement, and data-pipeline management

> The application itself does **not** perform thermal analysis—only manages and visualises the results.

---

## Technology Stack

• Laravel 11 (API-only)  
• React 18 + Vite  
• PostgreSQL 16 + PostGIS 3.4+  
• Laravel Sanctum (session & token auth)  
• AdminLTE 3 (Blade-based admin)  
• MapLibre GL for map rendering

---

## Repository Layout

```
app/             # Laravel application code
resources/       # Blade templates & SPA entrypoints
routes/          # API & web route definitions
public/          # Public assets & SPA build output
database/        # Migrations, factories, seeders
ROADMAP.md       # Development milestones
PROJECT_STATUS.md# Detailed progress tracker
DATABASE.md      # Full database schema documentation
```

---

## Quick Start (Local Development)

Prerequisites: **PHP ≥ 8.3**, **Composer**, **Node.js ≥ 20**, **PostgreSQL + PostGIS**.

```bash
# 1. Clone & install PHP deps
git clone https://github.com/ssobii2/Melt-B-MVP.git melt-b && cd melt-b
composer install

# 2. Environment & key
cp .env.example .env
php artisan key:generate

# 3. Configure your database credentials in .env, then:
php artisan migrate --seed

# 4. Install JS deps & start Vite (React SPA)
npm ci
npm run dev

# 5. Serve the API & admin dashboard
php artisan serve  # http://127.0.0.1:8000
```

After seeding, an admin account will be created. Check the database seeder files for the default credentials and **change them immediately** for security.

---

## Database Model (High-Level)

See `DATABASE.md` for full specification. Key tables:

| Table | Purpose |
|-------|---------|
| `users` | Auth & role management |
| `datasets` | Metadata for data bundles |
| `entitlements` & `user_entitlements` | ABAC rules (DS-ALL / DS-AOI / DS-BLD) |
| `buildings` | Geometry & anomaly metrics |
| `analysis_jobs` | External analysis tracking |
| `audit_logs` | Admin action history |

Spatial columns use PostGIS geometry types; models leverage the `laravel-eloquent-spatial` package.

---

## API Overview

All endpoints are versioned under `/api/*` and protected by Laravel Sanctum. The full OpenAPI schema lives in `api.json`.

| Group | Sample Endpoints |
|-------|-----------------|
| Auth | `POST /api/login`, `POST /api/register`, `POST /api/logout` |
| Buildings | `GET /api/buildings`, `GET /api/buildings/{gid}` |
| Downloads | `GET /api/downloads/{id}` |
| Analysis Jobs (admin) | `POST /api/admin/analysis-jobs` |
| Webhooks | `POST /api/webhooks/analysis-complete` |

---

## Feature Highlights

MELT-B ships with a complete, production-ready stack. The key capabilities are grouped below so you can grasp the platform at a glance.

### Backend (Laravel API)

- Spatial database powered by **PostgreSQL + PostGIS**
- Secure authentication with **Laravel Sanctum** (session & token)
- **Attribute-based access control (ABAC)** via entitlements (`DS-ALL`, `DS-AOI`, `DS-BLD`)
- RESTful endpoints for buildings, datasets, downloads, analysis jobs and webhooks
- **CSV ingestion & webhook pipeline** that updates building anomaly metrics in real time
- Comprehensive **audit logging** for administrative actions

### Front-End (React SPA)

- Interactive **MapLibre GL** map with anomaly-based building styling
- **Building Explorer** with search, filters, and detailed metrics drawer
- **Download Centre** for CSV/GeoJSON exports respecting user entitlements
- Responsive dashboard components & **KPI visualisations** (Chart.js)

### Admin Dashboard (AdminLTE)

- User, entitlement, dataset and analysis-job management
- Role-based middleware and separate `/admin` authentication flow
- Real-time system statistics & **audit log viewer**

### DevOps & Performance

- Artisan commands for import tasks and maintenance
- Caching strategies and background job queues for heavy workloads
- CI-ready test suite with PHPUnit & modern frontend testing tools

---

## Contributing

1. Fork, create a feature branch, and open a PR.  
2. Run **`phpunit`** & **frontend tests** – no failures.  
3. Follow PSR-12 coding style (`composer format`).  
4. Update docs for any new endpoints or components.

---

## License

MELT-B MVP is open-sourced software licensed under the **MIT License**.
