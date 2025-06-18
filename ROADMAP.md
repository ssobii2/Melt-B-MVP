## **MELT-B MVP Development Roadmap: Laravel Backend + React SPA Frontend**

**Core Principle:** Establish the secure and functional data layer first, then build the interactive user interface on top of it. Ensure robust testing and deployment practices are woven throughout.

**MVP Scope Reminder:** We are consuming _pre-generated data artifacts_ from the Data Science team. Our responsibility is to ingest, store, serve, visualize, and manage access to this data, not to generate the core thermal analysis or AI outputs.

---

### **Phase 0: Foundation & Core Setup ✅ COMPLETED**

**Goal:** Complete the foundational database structure, assuming your basic Laravel, React, Docker environment is already running.

-   **0.1. Database: Initial Migrations & Spatial Setup (PostgreSQL + PostGIS)** ✅ **COMPLETED**
    -   ✅ **Task:** Create comprehensive database migrations for core entities:
        -   ✅ `users` table (for authentication, with a `role` column, `api_key`, `contact_info`).
        -   ✅ `datasets` table (to manage metadata about the pre-generated data sets, e.g., name, paths to data in object storage).
        -   ✅ `entitlements` table (to define access rules: `type`, `dataset_id`, spatial geometry for `aoi_geom` using PostGIS, array for `building_ids`, `expiry_date`).
        -   ✅ `user_entitlements` pivot table (linking users to entitlements).
        -   ✅ `buildings` table (critical for storing building-specific data like `gid`, `geometry` using PostGIS, `thermal_loss_index` (TLI), `co2_potential`, `classification`, `address`, `ownership_details`, etc.).
        -   ✅ `audit_logs` table (for tracking administrative actions).
    -   ✅ **Task:** Ensure your Laravel setup is configured to enable and utilize the PostGIS extension within PostgreSQL.
    -   ✅ **Task:** Implement spatial awareness for your Laravel Eloquent models to interact with PostGIS geometry types and spatial functions.
    -   ✅ **Task:** Run all pending database migrations.
    -   ✅ **Task:** Verify initial data seeding is successful in the new tables.
    -   ✅ **Task:** Create Eloquent models with proper relationships and spatial support.
    -   ✅ **Task:** Set up database seeders with sample data for testing.

---

### **Phase 1: Backend - Authentication & Authorization Core**

**Goal:** Implement secure user login, token management, and the granular, attribute-based access control (ABAC) system for data access.

-   **1.1. Backend: User Authentication & Token Management**
    -   **Task:** Implement an API endpoint (e.g., `POST /api/auth/token`) responsible for user login.
    -   **Task:** This endpoint will interact with your chosen **Authentication Service** to verify user credentials and obtain a security token (e.g., JWT).
    -   **Task:** Implement server-side logic to validate incoming tokens for all protected API endpoints.
    -   **Task:** Implement token refreshing mechanism.
-   **1.2. Backend: Granular Authorization Logic**
    -   **Task:** Develop server-side middleware or services to:
        -   Extract user identity and role from validated tokens.
        -   Retrieve user-specific entitlements from the database.
        -   Utilize a **Caching Mechanism** to store and quickly retrieve entitlement filters for improved performance.
    -   **Task:** Implement the core **ABAC (Attribute-Based Access Control) logic** that dynamically constructs data filters based on user entitlements:
        -   Generate appropriate SQL `WHERE` clauses for `DS-ALL`, `DS-AOI`, and `DS-BLD` entitlement types.
        -   Generate bounding box filters for `TILES` (map data).
        -   Handle complex scenarios like **overlapping entitlements** (union logic).
        -   Enforce access denial for **expired entitlements** or unauthorized requests (e.g., HTTP 403).
    -   **Task:** Implement an API endpoint (e.g., `GET /api/me/entitlements`) for authenticated users to view their active entitlements.
-   **1.3. Backend: Basic Admin APIs for Access Management**
    -   **Task:** Implement API endpoints (e.g., `POST /api/admin/users`, `PUT /api/admin/entitlements/{id}`) for administrative roles to manage users and assign entitlements. These endpoints must also be secured by authentication and role-based authorization.

---

### **Phase 2: Backend - Core Data APIs & Ingestion**

**Goal:** Set up robust API endpoints for serving spatial building data and map tiles, and establish the process for ingesting pre-generated data.

-   **2.1. Backend: Data Ingestion Processes**
    -   **Task:** Develop secure and efficient scripts or API endpoints (e.g., an internal tool or endpoint for Data Science team) for **ingesting the pre-generated building data** (e.g., CSV, GeoJSON files) into the `buildings` PostgreSQL table. This must handle parsing, data validation, and inserting/updating records, including the PostGIS geometry.
    -   **Task:** Establish processes for placing **pre-generated thermal raster tiles** (image files) into your chosen **Object Storage** (e.g., S3). Our backend will then serve these from there.
    -   **Task:** Coordinate with Data Science team to finalize the exact format and delivery mechanism of their data outputs.
-   **2.2. Backend: Map Tile Serving API**
    -   **Task:** Implement the `GET /api/tiles/{dataset}/{z}/{x}/{y}` endpoint.
    -   **Task:** This endpoint will:
        -   Apply the `TILES` entitlement filter based on the user's authorized AOI.
        -   Retrieve the specific tile data (image or vector) from **Object Storage** or directly from PostGIS (if using vector tiles).
        -   **Decision Point (for Senior Devs):** Decide whether to clip rasters on the fly (more CPU intensive for backend) or rely on pre-cut rasters per AOI from the data source (simpler for our backend). _Communicate this requirement to the Data Science team._
-   **2.3. Backend: Filtered Buildings Data API**
    -   **Task:** Implement `GET /api/buildings` endpoint:
        -   Returns a paginated and sortable list of building data.
        -   **Crucially, applies the `DS-ALL`, `DS-AOI`, or `DS-BLD` entitlement filters** using PostGIS spatial queries on the `buildings` table.
        -   Supports basic query parameters for search, filtering, and pagination.
-   **2.4. Backend: Data Download API**
    -   **Task:** Implement `GET /api/downloads/{id}` endpoint.
    -   **Task:** This endpoint will retrieve the requested dataset and serve it.
    -   **Task:** **Implement high-performance streaming for large datasets** (CSV, GeoJSON, Excel) using PostgreSQL's `COPY` command.

---

### **Phase 3: Frontend - Core Dashboard & Map Interaction**

**Goal:** Create the interactive map dashboard, allowing users to visualize thermal data and interact with building information.

-   **3.1. Frontend: SPA Setup & Authentication Flow**
    -   **Task:** Configure your React SPA for client-side routing (e.g., `/login`, `/dashboard`, `/admin`).
    -   **Task:** Develop user login and registration UI components that interact with your backend authentication API (`POST /api/auth/token`).
    -   **Task:** Implement client-side logic for:
        -   Securely storing and managing the received security token.
        -   Implementing the token refreshing mechanism.
        -   Protecting frontend routes based on authentication status.
-   **3.2. Frontend: Interactive Map View**
    -   **Task:** Integrate `MapLibre GL` component into the main dashboard view.
    -   **Task:** Implement dynamic loading and display of thermal raster tiles by calling the `/api/tiles` endpoint as the user navigates the map.
    -   **Task:** Fetch building footprint data from `/api/buildings` and overlay these on the map.
    -   **Task:** Implement TLI-based color-coding for building footprints to visually highlight heat loss.
-   **3.3. Frontend: Context Panel & Building Details**
    -   **Task:** Develop the collapsible side panel to display building lists.
    -   **Task:** Implement search functionality connected to the `/api/buildings` endpoint.
    -   **Task:** Create a filterable and sortable table of buildings.
    -   **Task:** Implement click interactions on map buildings or table rows to open a detailed information drawer.
    -   **Task:** Populate the details drawer with initial building information (TLI, classification).

---

### **Phase 4: Enhancements & Admin UI**

**Goal:** Complete the full MVP feature set, including detailed building insights, download center, and the administrative dashboard.

-   **4.1. Frontend: Enhanced Building Details & Insights**
    -   **Task:** Expand the building details drawer to display charts and other key performance indicators (KPIs) like CO2 savings estimates.
    -   **Task:** Implement display of pre- and post-renovation visualizations if the data for this feature is available from the Data Science team.
-   **4.2. Frontend: Download Centre & Profile Management**
    -   **Task:** Develop the full Download Centre UI (listing available datasets, displaying generic API URL info).
    -   **Task:** Implement the trigger for initiating data downloads from the `/api/downloads` endpoint.
    -   **Task:** Implement a UI for users to manage their profile (e.g., change password, update contact info, manage API keys by interacting with backend APIs).
-   **4.3. Frontend: Admin Dashboard UI**
    -   **Task:** Build dedicated UI components accessible only to admin roles for managing users (CRUD operations), roles, and entitlements.
    -   **Task:** Implement a view for displaying audit logs (if exposed by a backend API).

---

### **Phase 5: Performance, Security & Deployment Preparation**

**Goal:** Optimize the MVP for performance and security, and prepare it for final demo and potential pilot deployments.

-   **5.1. Performance Optimization:**
    -   **Task:** Backend: Optimize database queries and API response times.
    -   **Task:** Frontend: Optimize React rendering, ensure efficient bundle size, consider lazy loading for less critical components.
    -   **Task:** Data Layer: Verify efficient data retrieval from Object Storage and PostGIS. Revisit caching strategies for map tiles.
-   **5.2. Security Audit & Hardening:**
    -   **Task:** Conduct a security review across the entire application (input validation, rate limiting, secure header configurations, CORS policies, secure token handling).
    -   **Task:** Implement any necessary security enhancements based on findings.
-   **5.3. Documentation:**
    -   **Task:** Document all API endpoints (e.g., using a tool to generate API docs).
    -   **Task:** Document the `docker-compose` deployment process.
    -   **Task:** Create basic troubleshooting guides.
