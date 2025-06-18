## **MELT-B MVP: Authentication, Authorization & Admin APIs (Custom Implementation)**

### **1. User Authentication & Token Management (Laravel Sanctum - Custom)**

Since you're not using Breeze, you'll be setting up the authentication routes and controllers manually.

**Required Components:**

1.  **Laravel Sanctum Installation & Configuration:**
    -   Install the Sanctum package.
    -   Publish Sanctum's configuration and migrations.
    -   Run migrations to create the `personal_access_tokens` table (used for API tokens, not strictly for SPA but good to have).
    -   Configure `config/sanctum.php` for your SPA domains and stateful session domains.
    -   Ensure `EnsureFrontendRequestsAreStateful` middleware is uncommented in `app/Http/Kernel.php` (under `api` middleware group for SPAs).
2.  **Custom Authentication Controllers:**
    -   **Registration Controller (`AuthController@register`):**
        -   Handles `POST /api/register` requests.
        -   Validates user input (name, email, password, password confirmation).
        -   Creates a new `User` record in the `users` table with a default `role` (e.g., 'user').
        -   (Optional but Recommended) Implement email verification flow.
    -   **Login Controller (`AuthController@login`):**
        -   Handles `POST /api/login` requests.
        -   Validates credentials (email, password).
        -   Uses `Auth::attempt()` to authenticate the user.
        -   On success, issues a session cookie via Sanctum (`session()->regenerate()`) for the SPA.
        -   Returns a success response (e.g., user object, success message).
    -   **Logout Controller (`AuthController@logout`):**
        -   Handles `POST /api/logout` requests.
        -   Invalidates the user's session (`Auth::guard('web')->logout()`).
        -   Returns a success response.
    -   **Password Reset Controllers:**
        -   `POST /api/forgot-password`: Sends password reset link.
        -   `POST /api/reset-password`: Resets password using token.
    -   **Email Verification Controllers:**
        -   `POST /api/email/verification-notification`: Resends verification email.
        -   `GET /api/verify-email/{id}/{hash}`: Verifies email.
3.  **Authentication Routes:**
    -   Define all the above routes in `routes/api.php`. Ensure they are outside any `auth:sanctum` middleware initially, as they are used for unauthenticated users.
    -   Wrap authenticated routes in `auth:sanctum` middleware.
4.  **API Tokens for Service Bots (Optional but Recommended):**
    -   If you need to allow non-SPA clients (like the "Service Bot" for CRM sync) to access your API, Sanctum also provides API tokens.
    -   You'd implement an endpoint (e.g., `POST /api/tokens`) where a user can generate a token, or an admin can generate one for a service bot.
    -   The service bot would then send this token in the `Authorization: Bearer <token>` header for its requests.
5.  **React Frontend Integration:**
    -   Develop **Login, Registration, Password Reset, Email Verification UIs** in React.
    -   Make AJAX requests (e.g., using Axios) to the Laravel API endpoints.
    -   Sanctum's SPA authentication relies on Laravel's session cookies. When using Axios, ensure `axios.defaults.withCredentials = true;` is set, and Laravel's CORS configuration allows credentialed requests from your frontend domain.
    -   Handle success/error responses and client-side routing based on authentication status.

### **2. Attribute-Based Access Control (ABAC) Implementation**

This is the core of your data access security, determining _what data_ a user can see.

**Required Components:**

1.  **Database Schema (already defined):**
    -   `users` (with `role`)
    -   `datasets`
    -   `entitlements` (`type`, `dataset_id`, `aoi_geom` (PostGIS), `building_gids` (JSONB), `expires_at`, `download_formats`)
    -   `user_entitlement` (pivot table)
    -   `buildings` (`geometry` (PostGIS), TLI, classification, etc.)
2.  **Entitlement Retrieval Service/Logic:**
    -   **Task:** Develop a service (e.g., `app/Services/UserEntitlementService.php`) that, for an authenticated user, retrieves all their active entitlements from the database.
    -   **Task:** Implement a **Caching Mechanism (Redis)** to store these entitlements for a given user ID to avoid repeated database queries for every request. Tokens refresh every 55 mins, so cache validity needs to be considered.
3.  **Authorization Middleware/Query Scopes:**
    -   **Task:** Create a **Laravel Middleware** (e.g., `CheckEntitlementsMiddleware`) that runs on protected API routes (`/api/buildings`, `/api/tiles`, `/api/downloads`).
    -   **Task:** This middleware will:
        -   Get the current authenticated user.
        -   Fetch their entitlements (from cache or DB).
        -   Based on `entitlement.type`:
            -   **`DS-ALL`:** Apply no filters for this dataset.
            -   **`DS-AOI`:** Extract `aoi_geom` polygons.
            -   **`DS-BLD`:** Extract `building_gids` arrays.
            -   **`TILES`:** Extract `aoi_geom` polygons for tile bbox filtering.
        -   **CRITICAL:** Pass these generated filters down to the Controller/Query Builder.
    -   **Task:** In your `Building` Eloquent model (using `clickbar/laravel-magellan`), create **Query Scopes** (e.g., `scopeApplyEntitlementFilters($query, $entitlementFilters)`) that dynamically construct complex `WHERE` clauses using `PostGIS` functions:
        -   For `DS-AOI`: `whereIntersects('geometry', $aoiPolygon)` for each allowed AOI.
        -   For `DS-BLD`: `whereIn('gid', $buildingGids)` for each allowed building set.
        -   Handle **overlapping entitlements**: The scope should generate a combined `WHERE` clause that uses `OR` conditions for the `DS-AOI` and `DS-BLD` filters, effectively creating a `SQL UNION` for data rows. `clickbar/laravel-magellan`'s query builder extensions should simplify this.
        -   For `TILES`: The tile endpoint will receive the `(z, x, y)` coordinates, calculate the tile's bounding box, and then use `whereIntersects('aoi_geom', $tileBbox)` against the user's `TILES` entitlements to verify access.
    -   **Task:** Implement logic to handle **expired entitlements** (exclude them from active filters).
    -   **Task:** For download APIs, check `entitlement.download_formats` to ensure the requested format is allowed.

### **3. Admin APIs & AdminLTE Dashboard**

**Goal:** Provide a web-based administrative interface for managing users, entitlements, and viewing audit logs.

**Required Backend API Components:**

1.  **User Management APIs:**
    -   `GET /api/admin/users`: List all users (paginated, filterable).
    -   `POST /api/admin/users`: Create new user.
    -   `GET /api/admin/users/{id}`: Get user details.
    -   `PUT /api/admin/users/{id}`: Update user details (name, email, role, etc.).
    -   `DELETE /api/admin/users/{id}`: Delete user.
2.  **Entitlement Management APIs:**
    -   `GET /api/admin/entitlements`: List all entitlements.
    -   `POST /api/admin/entitlements`: Create a new entitlement.
    -   `GET /api/admin/entitlements/{id}`: Get entitlement details.
    -   `PUT /api/admin/entitlements/{id}`: Update entitlement details (type, dataset, AOI, building GIDs, expiry).
    -   `DELETE /api/admin/entitlements/{id}`: Delete entitlement.
    -   `POST /api/admin/users/{user_id}/entitlements/{entitlement_id}`: Assign an entitlement to a user.
    -   `DELETE /api/admin/users/{user_id}/entitlements/{entitlement_id}`: Remove an entitlement from a user.
3.  **Dataset Management APIs:**
    -   `GET /api/admin/datasets`: List all datasets.
    -   `POST /api/admin/datasets`: Create new dataset.
    -   `PUT /api/admin/datasets/{id}`: Update dataset details.
    -   `DELETE /api/admin/datasets/{id}`: Delete dataset.
4.  **Audit Log APIs:**
    -   `GET /api/admin/audit-logs`: List audit log entries (paginated, filterable by user, action type).
5.  **API Security for Admin Endpoints:**
    -   Apply middleware to all `/api/admin/*` routes that checks if the authenticated user has the `role: 'admin'`. Laravel's `Gate` or `Policy` system is ideal for this role-based authorization.

**AdminLTE Dashboard Integration:**

-   **Laravel Blade as Layout:** AdminLTE will primarily serve as the visual layout and framework for your admin dashboard. You'll likely have a dedicated Blade layout file for AdminLTE (e.g., `resources/views/admin/layout.blade.php`).
-   **React Components within AdminLTE:** Inside the content area of your AdminLTE Blade layout, you will mount your React SPA components specific to the admin area.
    -   For example, a `UserManagement.jsx` React component could render the table and forms for managing users, making API calls to `/api/admin/users`.
    -   This allows you to leverage AdminLTE's pre-built CSS, JavaScript components (like tables, forms, navigation), while still building the dynamic parts with React.
-   **Vite Compilation:** Ensure Vite correctly compiles your React components into the AdminLTE-based Blade views.
-   **Routing:** Your React SPA's client-side router will handle navigation _within_ the Admin Dashboard (e.g., from "Manage Users" to "Manage Entitlements" without a full page reload). The initial load for `/admin` will be handled by Laravel serving the AdminLTE Blade layout, and then React takes over.
