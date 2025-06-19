## **MELT-B MVP: Backend - Core Data APIs & Ingestion (Laravel)**

This phase focuses on the fundamental capability of the system: taking the processed data from the Data Science (DS) team, storing it, and serving it securely and efficiently through various APIs.

### **1. Data Ingestion Processes**

**Goal:** Establish the mechanisms for securely receiving and storing the pre-generated thermal raster data and building attribute/geometry data from the Data Science team.

**Key Inputs from Data Science Team:**

*   **Thermal Raster Tiles:** Pre-processed image tiles (e.g., PNG, JPEG) containing thermal loss information. These are typically organized in a Z/X/Y tile structure.
*   **Building Data:** Structured data (e.g., CSV, GeoJSON, or even database dumps) containing building GIDs, geometries (Polygons), TLI, CO2 estimates, classification, addresses, ownership details, and other attributes.

**Implementation Steps (Laravel):**

*   **1.1. Object Storage Setup (for Raster Tiles):**
    *   **Requirement:** Access to an Object Storage solution (e.g., AWS S3, compatible with Laravel's Storage facade).
    *   **Task:** Configure Laravel's `config/filesystems.php` to connect to your Object Storage.
    *   **Task:** Define a clear folder structure within the bucket for different datasets (e.g., `thermal_rasters/{dataset_name}/{z}/{x}/{y}.png`).
    *   **Task:** Establish a secure method for the Data Science team to upload their pre-generated raster tiles to this specific Object Storage location. This might involve:
        *   Granting limited IAM credentials (if S3) to the DS team.
        *   Setting up an internal SFTP or a dedicated upload API endpoint (less common for large bulk data, but possible for smaller updates).
*   **1.2. Building Data Ingestion (PostgreSQL/PostGIS):**
    *   **Requirement:** The `buildings` table schema (with `geometry` column) and `datasets` table must be in place.
    *   **Task:** Develop a **Laravel Artisan Command** (e.g., `php artisan import:buildings {dataset_id} {file_path}`) or a secure internal API endpoint (if the DS team needs to trigger it programmatically).
    *   **Task:** This command/endpoint will:
        *   Receive the path to the CSV or GeoJSON file containing building data.
        *   **Read and Parse:** Efficiently read and parse the input file. For GeoJSON, you'll need a library to interpret the `FeatureCollection` and `geometry` objects. For CSV, parse columns to their respective types.
        *   **Data Validation:** Implement validation rules to ensure data quality and schema conformity (e.g., valid GID, TLI range, valid geometry strings).
        *   **Geometry Conversion:** Convert geometry strings (e.g., WKT or GeoJSON) into PostGIS-compatible formats. `clickbar/laravel-magellan` will be crucial here, as it provides parsers (e.g., `Magellan::geometryFromJson($geojson_string)`).
        *   **Batch Insertion/Upsertion:** Use Laravel's `insert()` or `upsert()` methods for efficient bulk insertion/updating of `buildings` records. This is vital for performance with large datasets.
        *   **Link to Dataset:** Ensure each imported building record is correctly associated with a `dataset_id`.
    *   **Task:** Implement logging for ingestion processes (success, failures, errors) for auditability.
*   **1.3. Metadata Updates:**
    *   **Task:** Ensure that after a successful ingestion of a new dataset (both rasters and building data), the `datasets` table is updated with accurate metadata (e.g., `storage_location`, `version`).

---

### **2. Map Tile Serving API (`GET /api/tiles/{dataset_id}/{z}/{x}/{y}.png`)**

**Goal:** Securely serve thermal raster tiles to the React frontend, applying user entitlements.

**Endpoint:** `GET /api/tiles/{dataset_id}/{z}/{x}/{y}.png` (or `.pbf` for vector tiles if applicable)

**Implementation Steps (Laravel Controller):**

1.  **Authentication & Initial Middleware:**
    *   Apply `auth:sanctum` middleware to this route to ensure the user is authenticated.
    *   Your existing entitlement middleware/service runs to retrieve the user's active entitlements, specifically focusing on `TILES` type entitlements.
2.  **Extract Request Parameters:**
    *   Parse `dataset_id`, `z` (zoom level), `x` (column), `y` (row) from the URL.
3.  **Tile Bounding Box Calculation:**
    *   **Task:** Calculate the exact geographic bounding box (min_lon, min_lat, max_lon, max_lat) of the requested tile `(z, x, y)`. This box will be represented as a PostGIS `POLYGON` geometry.
4.  **Authorization (Spatial Entitlement Check):**
    *   **Task:** Query the user's `TILES` entitlements from the database (or Redis cache).
    *   **Task:** For *each* active `TILES` entitlement, check if its `aoi_geom` **intersects** with the calculated `tile_bbox` using PostGIS spatial functions (e.g., `Magellan::whereIntersects('aoi_geom', $tileBbox)`).
    *   **Task:** If *no* intersecting `TILES` entitlement is found, return an HTTP 403 (Forbidden) response.
5.  **Tile Retrieval from Object Storage:**
    *   **Task:** If authorized, construct the full path to the tile image file in your Object Storage (e.g., `thermal_rasters/{dataset_name_from_db}/{z}/{x}/{y}.png`).
    *   **Task:** Use Laravel's `Storage` facade to retrieve the tile file from Object Storage.
    *   **Task:** If the tile is not found (e.g., outside the data coverage), return a transparent image or an appropriate HTTP 404 response.
6.  **Serving the Tile:**
    *   **Task:** Return the image content with the correct `Content-Type` header (e.g., `image/png`).
    *   **Task:** (Optimization) Implement HTTP caching headers (e.g., `Cache-Control`, `Expires`) for browser and CDN caching.

---

### **3. Filtered Buildings Data API (`GET /api/buildings`)**

**Goal:** Serve paginated and filterable building data to the React frontend, dynamically applying ABAC entitlements.

**Endpoint:** `GET /api/buildings`

**Implementation Steps (Laravel Controller):**

1.  **Authentication & Initial Middleware:**
    *   Apply `auth:sanctum` middleware.
    *   Your entitlement middleware/service retrieves the user's active `DS-ALL`, `DS-AOI`, and `DS-BLD` entitlements.
2.  **Query Builder Initialization:**
    *   Start with `Building::query()`.
3.  **Apply ABAC Filters (Crucial Logic):**
    *   **Task:** Call a custom **Query Scope** on your `Building` model (e.g., `scopeApplyUserEntitlements($query, $userEntitlements)`). This scope encapsulates the complex ABAC logic.
    *   **Logic within the scope:**
        *   If the user has `DS-ALL` entitlement for the requested dataset, no further spatial/GID filtering is needed for that dataset.
        *   Otherwise, build a complex `WHERE` clause using `OR` conditions:
            *   For each `DS-AOI` entitlement: `whereIntersects('geometry', $entitlement->aoi_geom)`.
            *   For each `DS-BLD` entitlement: `whereIn('gid', $entitlement->building_gids)`.
            *   Combine these with `orWhere` clauses. If there are multiple entitlements of different types, they should union the results.
        *   **Handle expired entitlements**: Ensure these are filtered out *before* applying the entitlement logic.
        *   **Consider a default "deny all"**: If no entitlements allow access to the requested data, the query should return an empty set or trigger an authorization error (e.g., throw `UnauthorizedException`).
4.  **Apply Request Parameters (Filters, Search, Pagination, Sorting):**
    *   **Task:** Implement logic to handle common API query parameters:
        *   `search`: Filter by address, cadastral reference, etc.
        *   `type`: Filter by `building_type_classification`.
        *   `tli_min`, `tli_max`: Filter by TLI range.
        *   `sort_by`, `sort_order`: Order results.
        *   `page`, `per_page`: Pagination.
    *   **Tech:** `Laravel Pagination`, `Query Scopes`
5.  **Return Data:**
    *   **Task:** Return the paginated collection of `Building` models, transformed into a clean JSON format using **Laravel API Resources**. This ensures consistent API responses and hides unnecessary internal fields.

---

### **4. Data Download API (`GET /api/downloads/{id}`)**

**Goal:** Allow authorized users to download full datasets in specified formats (CSV, GeoJSON, Excel).

**Endpoint:** `GET /api/downloads/{id}`

**Implementation Steps (Laravel Controller):**

1.  **Authentication & Initial Middleware:**
    *   Apply `auth:sanctum` middleware.
    *   Retrieve the authenticated user and their entitlements.
2.  **Entitlement & Format Check:**
    *   **Task:** Verify if the user has an active entitlement that allows downloading the specific `dataset_id` associated with `{id}` *and* if the requested `download_format` (e.g., from a query parameter `?format=csv`) is listed in the `entitlement.download_formats` JSONB array.
    *   **Task:** If unauthorized or format not allowed, return HTTP 403.
3.  **Retrieve Data for Download:**
    *   **Task:** Based on the `dataset_id` and the user's entitlements, construct the query to retrieve **all** relevant building data (not paginated). The same ABAC logic from `GET /api/buildings` will apply here.
4.  **Generate & Stream File:**
    *   **Task:** For **CSV/GeoJSON**:
        *   **Leverage PostgreSQL's `COPY` command:** This is the most efficient way to extract large amounts of data directly from PostgreSQL to a file or stream. You can execute `COPY (SELECT ... FROM buildings WHERE ...) TO STDOUT WITH (FORMAT CSV, HEADER TRUE)` directly and stream the output through Laravel.
        *   **Alternatively (less performant for huge data):** Loop through the query results and generate the CSV/GeoJSON content on the fly, streaming it as the response.
    *   **Task:** For **Excel**: You'll likely need a dedicated Laravel Excel package (e.g., `maatwebsite/excel`) which can export query results to XLSX format. This might involve generating a temporary file before streaming.
    *   **Task:** Set appropriate HTTP headers for file download: `Content-Type`, `Content-Disposition` (with filename).
    *   **Task:** (For very large files) Consider an asynchronous approach:
        *   User requests download.
        *   Backend queues a job (e.g., using Laravel Queues) to generate the file in the background.
        *   Frontend polls an endpoint for status or receives a notification when the file is ready for download.
        *   The file is stored in Object Storage for a limited time and served from there.
    *   **Tech:** `PostgreSQL` (raw `DB` facade for `COPY`), `Laravel Streaming Responses`, `maatwebsite/excel` (for Excel), `Laravel Queues` (for async).

