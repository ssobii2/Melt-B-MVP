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
• PostgreSQL 16 + PostGIS 3  
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
git clone <repo> melt-b && cd melt-b
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

Login with the seeded **admin** account (`admin@example.com` / `password`) and change credentials immediately.

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

## Roadmap

Below is a **condensed view of the six-phase roadmap**—enough to understand where the project stands without switching files.

| Phase | Status | Key Deliverables |
|-------|--------|------------------|
| 0. Foundation & Core Setup | ✅ Done | PostGIS schema & migrations (users, buildings, datasets, entitlements) • Spatial Eloquent models • Seed data |
| 1. Auth & ABAC | ✅ Done | Laravel Sanctum auth + password flows • AdminLTE dashboard • Role & entitlement middleware |
| 2. Data APIs & Ingestion | ✅ Done | CSV ingestion command & webhook pipeline • Analysis job tracking APIs • Filtered Buildings & Download APIs |
| 3. Front-End Dashboard | ✅ Done | React SPA with MapLibre anomaly map • Building explorer & details drawer • Unified auth flow |
| 4. Enhancements & Admin UI | ✅ Done | KPI charts & anomaly insights • Download centre & profile mgmt • Admin CRUD for analysis jobs & entitlements |
| 5. Performance, Security & Deployment | ⌛ In progress | Caching & query optimisation • Background jobs • Test automation & CI/CD • Production deployment |

> Last updated: **December 2025**

---

## Project Status (December 2025)

The system is **feature-complete for anomaly detection** and ready for production ingestion. Remaining tasks concentrate on performance tuning, test automation, and deployment hardening. See `PROJECT_STATUS.md` for the exhaustive changelog.

---

## Contributing

1. Fork, create a feature branch, and open a PR.  
2. Run **`phpunit`** & **frontend tests** – no failures.  
3. Follow PSR-12 coding style (`composer format`).  
4. Update docs for any new endpoints or components.

---

## License

MELT-B MVP is open-sourced software licensed under the **MIT License**.
