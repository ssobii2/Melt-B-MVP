## **MELT-B MVP Development Roadmap: Laravel Backend + React SPA Frontend**

**Core Principle:** Establish the secure and functional data layer first, then build the interactive user interface on top of it. Ensure robust testing and deployment practices are woven throughout.

**MVP Scope Update:** We have transitioned from TLI (Thermal Loss Index) to an **anomaly detection system**. The Data Science team now provides CSV files with detailed heat loss metrics and anomaly classifications. Our responsibility is to ingest, store, serve, visualize, and manage access to this anomaly data through a streamlined API-first architecture.

**Architecture Update:** Laravel now serves as a pure API backend for the React SPA, with separate Admin routes for the Blade-based AdminLTE dashboard. This eliminates routing conflicts and provides clear separation of concerns.

---

### **Phase 0: Foundation & Core Setup**

**Goal:** Complete the foundational database structure, assuming your basic Laravel, React, Docker environment is already running.

-   **0.1. Database: Initial Migrations & Spatial Setup (PostgreSQL + PostGIS)**
    -   **Task:** Create comprehensive database migrations for core entities:
        -   `users` table (for authentication, with a `role` column, `api_key`, `contact_info`).
        -   `datasets` table (to manage metadata about the pre-generated data sets, e.g., name, paths to data in object storage).
        -   `entitlements` table (to define access rules: `type`, `dataset_id`, spatial geometry for `aoi_geom` using PostGIS, array for `building_ids`, `expiry_date`).
        -   `user_entitlements` pivot table (linking users to entitlements).
        -   `buildings` table (critical for storing building-specific data like `gid`, `geometry` using PostGIS, `average_heatloss`, `reference_heatloss`, `heatloss_difference`, `abs_heatloss_difference`, `threshold`, `is_anomaly`, `confidence`, `co2_savings_estimate`, `classification`, `address`, `ownership_details`, etc.).
        -   `audit_logs` table (for tracking administrative actions).
    -   **Task:** Ensure your Laravel setup is configured to enable and utilize the PostGIS extension within PostgreSQL.
    -   **Task:** Implement spatial awareness for your Laravel Eloquent models to interact with PostGIS geometry types and spatial functions.
    -   **Task:** Run all pending database migrations.
    -   **Task:** Verify initial data seeding is successful in the new tables.
    -   **Task:** Create Eloquent models with proper relationships and spatial support.
    -   **Task:** Set up database seeders with sample data for testing.

---

### **Phase 1: Backend - Authentication & Authorization Core**

**Goal:** Implement secure user login, token management, and the granular, attribute-based access control (ABAC) system for data access.

-   **1.1. Backend: User Authentication & Token Management**
    -   **Task:** Implement API endpoints for user authentication (`POST /api/login`, `POST /api/register`, `POST /api/logout`).
    -   **Task:** Laravel Sanctum integration for session-based SPA authentication with API token support.
    -   **Task:** Implement server-side logic to validate incoming tokens for all protected API endpoints.
    -   **Task:** Password reset functionality (`POST /api/forgot-password`, `POST /api/reset-password`).
    -   **Task:** API token management (`POST /api/tokens/generate`, `DELETE /api/tokens/revoke`).
    -   **Task:** User profile endpoint (`GET /api/user`) for authenticated users.
    -   **Task:** Comprehensive audit logging for all authentication actions.
-   **1.2. Backend: AdminLTE Dashboard Integration**
    -   **Task:** AdminLTE installation and configuration for MELT-B branding.
    -   **Task:** Admin authentication with role-based access control middleware.
    -   **Task:** Admin dashboard with system statistics and recent activity.
    -   **Task:** Professional admin interface with anomaly detection data management menu structure.
    -   **Task:** Separate admin authentication flow (`/admin/login`) with session management.
-   **1.3. Backend: Attribute-Based Access Control (ABAC) Implementation**
    -   **Task:** UserEntitlementService with Redis caching for optimal performance.
    -   **Task:** CheckEntitlementsMiddleware for request-level entitlement filtering.
    -   **Task:** Building model query scopes with PostGIS spatial functions.
    -   **Task:** Admin User Management APIs with full CRUD operations.
    -   **Task:** Admin Entitlement Management APIs with spatial polygon support.
    -   **Task:** Admin Dataset Management APIs with full CRUD operations.
    -   **Task:** Admin Audit Log APIs for administrative tracking and compliance.
    -   **Task:** Building Data APIs with real-time entitlement filtering.
    -   **Task:** Comprehensive API route structure with proper authentication layers.
    -   **Task:** Simplified AdminLTE menu structure with core admin functionality only.
    -   **Task:** Complete User-Entitlement Assignment System with bidirectional management interface.

---

### **Phase 2: Backend - Core Data APIs & Anomaly Detection Workflow**

**Goal:** Set up robust API endpoints for serving spatial building data and establish the new anomaly detection data ingestion workflow.

-   **2.1. Backend: Anomaly Detection Data Ingestion**
    -   **Task:** Create `analysis_jobs` table to track external analysis job lifecycle (pending, running, completed, failed).
    -   **Task:** Implement `POST /api/admin/analysis-jobs` endpoint to initiate new analysis jobs with S3 input links.
    -   **Task:** Develop Artisan command `php artisan import:buildings-from-csv` for processing `merged_anomalies.csv` files.
    -   **Task:** Create secure webhook endpoint `POST /api/webhooks/analysis-complete` for receiving completion notifications.
    -   **Task:** Implement CSV parsing and upsert logic for anomaly detection data (average_heatloss, is_anomaly, confidence, etc.).
    -   **Task:** Coordinate with Data Science team on webhook integration and CSV format specifications.
-   **2.2. Backend: Simplified Routing Architecture**
    -   **Task:** Refactor `routes/web.php` to separate Admin (Blade) and SPA (React) routing.
    -   **Task:** Implement catch-all route for React SPA (`/{any?}`) after admin routes.
    -   **Task:** Remove obsolete tile serving endpoints (thermal raster tiles no longer needed).
    -   **Task:** Update authentication to use unified Laravel Sanctum for both admin and API access.
-   **2.3. Backend: Filtered Buildings Data API**
    -   **Task:** Update `GET /api/buildings` endpoint:
        -   Returns paginated and sortable building data with new anomaly detection fields.
        -   **Applies `DS-ALL`, `DS-AOI`, or `DS-BLD` entitlement filters** using PostGIS spatial queries.
        -   Supports filtering by anomaly status, heat loss ranges, and confidence levels.
        -   Includes efficient `/buildings/{gid}/find-page` endpoint for building navigation.
-   **2.4. Backend: Data Download API**
    -   **Task:** Implement `GET /api/downloads/{id}` endpoint.
    -   **Task:** This endpoint will retrieve the requested dataset and serve it.
    -   **Task:** **Implement high-performance streaming for large datasets** (CSV, GeoJSON, Excel) using PostgreSQL's `COPY` command.

---

### **Phase 3: Frontend - Anomaly Detection Dashboard & Map Interaction**

**Goal:** Create the interactive map dashboard for visualizing anomaly detection data and building heat loss metrics.

-   **3.1. Frontend: SPA Setup & Unified Authentication**
    -   **Task:** Configure React SPA routing with Laravel catch-all route integration.
    -   **Task:** Update authentication flow to use unified Laravel Sanctum session-based authentication.
    -   **Task:** Implement client-side logic for:
        -   Session-based authentication with CSRF protection.
        -   Protecting frontend routes based on authentication status.
        -   Handling authentication state across admin and user interfaces.
-   **3.2. Frontend: Anomaly Detection Map View**
    -   **Task:** Update `MapLibre GL` component to remove thermal raster tile layer.
    -   **Task:** Implement anomaly-based color-coding for building footprints (Red: anomaly, Blue: normal).
    -   **Task:** Update map legend from TLI scale to binary anomaly status indicator.
    -   **Task:** Fetch building footprint data with new anomaly detection fields from `/api/buildings`.
    -   **Task:** Implement building selection with auto-scroll functionality in Building Explorer.
-   **3.3. Frontend: Enhanced Context Panel & Building Details**
    -   **Task:** Update Building Explorer with anomaly filtering ("Anomalies Only", "Normal Only").
    -   **Task:** Implement sorting by heat loss difference to rank worst performers.
    -   **Task:** Update building details drawer with new metrics:
        -   Building Heat Loss (`average_heatloss`)
        -   Category Average (`reference_heatloss`)
        -   Deviation from Average (`heatloss_difference`)
        -   Anomaly status badge with confidence level
    -   **Task:** Fix building selection and auto-scroll functionality when clicking from map.

---

### **Phase 4: Advanced Features & Admin Dashboard**

**Goal:** Complete the full MVP feature set with anomaly detection insights, analysis job management, and enhanced administrative capabilities.

-   **4.1. Frontend: Advanced Anomaly Detection Insights**
    -   **Task:** Update dashboard KPIs to show "Total Anomalies" instead of "High TLI Buildings".
    -   **Task:** Implement anomaly confidence visualization and heat loss distribution charts.
    -   **Task:** Add building comparison features showing deviation from category averages.
    -   **Task:** Enhance building details with CO2 savings estimates and confidence metrics.
-   **4.2. Frontend: Analysis Job Management (Admin)**
    -   **Task:** Create Admin UI for "Analysis Jobs" section in React SPA.
    -   **Task:** Implement "Start New Analysis" form calling `POST /api/admin/analysis-jobs`.
    -   **Task:** Display analysis job status tracking (pending, running, completed, failed).
    -   **Task:** Show job history with input parameters and completion timestamps.
-   **4.3. Backend: Enhanced Admin Dashboard (Blade)**
    -   **Task:** Update AdminLTE dashboard to display anomaly detection statistics.
    -   **Task:** Add Building Details modal with new anomaly-related fields in admin buildings view.
    -   **Task:** Implement dataset metadata management with anomaly statistics.
    -   **Task:** Update `dataset:update-metadata` command for anomaly detection metrics.

---

### **Phase 5: Performance & Production Deployment**

**Goal:** Optimize the anomaly detection system for production use and deploy to staging/production environment.

-   **5.1. Backend: Performance & Scalability**
    -   **Task:** Implement caching strategies for building data and anomaly detection results.
    -   **Task:** Optimize database queries with proper indexing on anomaly detection fields.
    -   **Task:** Set up background job processing for CSV analysis and anomaly detection workflows.
    -   **Task:** Implement webhook handling for external analysis service integration.
-   **5.2. Frontend: Performance & User Experience**
    -   **Task:** Implement lazy loading for building data and map interactions.
    -   **Task:** Add comprehensive loading states and error handling for anomaly detection features.
    -   **Task:** Optimize React bundle size and implement code splitting for admin/user routes.
    -   **Task:** Fix building selection and auto-scroll functionality across all components.
-   **5.3. Production Deployment & Monitoring**
    -   **Task:** Deploy unified Laravel application with React SPA integration.
    -   **Task:** Configure session-based authentication with CSRF protection in production.
    -   **Task:** Implement monitoring for anomaly detection accuracy and system performance.
    -   **Task:** Set up automated backups for building data and analysis results.
