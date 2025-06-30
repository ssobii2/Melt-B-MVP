Of course. This is a critical set of instructions to get right. Based on the new information and your current route files, here is a complete summary to give to your AI code assistant, followed by the high-level project summary you asked for.

---

## **Part 1: Detailed Guide for Cursor AI**

**Project Goal:** Refactor the MELT-B application to accommodate a significant change in the data processing workflow. The old TLI (Thermal Loss Index) derivation is now obsolete. We are moving to a new "anomaly detection" system where an external process provides a CSV file with detailed heat loss metrics. This requires changes to our database, APIs, and frontend UI.

Additionally, we need to resolve routing conflicts between the Blade-based AdminLTE dashboard and the React SPA. The solution is to make Laravel a pure API for the React SPA, while keeping the Admin routes separate.

---

### **Step 1: Simplify Backend Routing (`routes/web.php`)**

**Context:** The current `web.php` file tries to serve two different frontends (React SPA and a Blade Admin dashboard), which causes routing and authentication conflicts. We will simplify it to only serve the Admin dashboard and a single entry point for the React SPA.

**Action:**
1.  **Modify `routes/web.php`:**
    *   **Keep all existing Admin routes** (`Route::prefix('admin')->...`) exactly as they are. They will continue to handle the Blade/AdminLTE dashboard.
    *   **Add a single catch-all route at the end of the file.** This route will serve the React SPA's main entry point (`app.blade.php`) for any web request that doesn't match an admin route (like `/`, `/dashboard`, `/profile`).

    ```php
    // In routes/web.php

    // --- All your existing admin routes stay here ---
    Route::prefix('admin')->group(function () {
        // ...
    });

    // --- ADD THIS CATCH-ALL FOR THE REACT SPA ---
    // This must be placed AFTER the admin routes.
    Route::get('/{any?}', function () {
        return view('app'); // This should be the name of your main Blade file for the React SPA
    })->where('any', '.*')->name('react.spa');
    ```

### **Step 2: Update Database Schema**

**Context:** The `buildings` table needs to be updated to store the new data from the `merged_anomalies.csv`. The old TLI concept is gone. The `entitlements` and `datasets` tables also need minor changes because we are no longer serving thermal tiles. Please check the `merged_anomalies.csv` file found in the `storage/data/` folder to see what the new columns data looks like.

**Action:**
1.  **Create a new migration to modify the `buildings` table:**
    *   **Remove** the column: `thermal_loss_index_tli`.
    *   **Add** the following new columns:
        *   `average_heatloss` (NUMERIC/FLOAT)
        *   `reference_heatloss` (NUMERIC/FLOAT)
        *   `heatloss_difference` (NUMERIC/FLOAT)
        *   `abs_heatloss_difference` (NUMERIC/FLOAT)
        *   `threshold` (NUMERIC/FLOAT)
        *   `is_anomaly` (BOOLEAN)
        *   `confidence` (FLOAT)
2.  **Create a new migration to modify the `entitlements` table:**
    *   **Remove** logic related to the `'TILES'` entitlement type. Update any enums or validation to reflect this.
3.  **Create a new migration to modify the `datasets` table:**
    *   Update the `data_type` column to support a new type like `'building_anomalies'`.
4.  Run `php artisan migrate`.

### **Step 3: Update Backend Ingestion & APIs (`routes/api.php`)**

**Context:** We need to remove obsolete endpoints and create a new workflow for handling the asynchronous analysis job from the science team.

**Action:**
1.  **Remove Obsolete API Route:**
    *   In `routes/api.php`, **delete the entire route** for `GET /tiles/{dataset_id}/{z}/{x}/{y}.png`.
    *   Delete the `app/Http/Controllers/Api/TileController.php` file.
2.  **Update Core Data APIs:**
    *   Modify the `BuildingController` (`index` and `show` methods) and `DownloadController` to return the new data columns (`is_anomaly`, `average_heatloss`, etc.) instead of `tli`.
3.  **Implement New Analysis Job Workflow:**
    *   **Create a new `analysis_jobs` database table** with a migration. It should include columns for `id`, `status` (e.g., pending, running, completed, failed), `input_s3_links` (JSONB), `output_csv_url` (TEXT, nullable), `started_at`, `completed_at`.
    *   **Create a new `POST /api/admin/analysis-jobs` endpoint.** This endpoint will:
        *   Receive input links (e.g., S3 URLs to SatVu images).
        *   Create a new record in the `analysis_jobs` table with a `pending` status.
        *   **(For now, this endpoint will just create the database record. Later, it will call the science team's API).**
    *   **Create a new Artisan command `php artisan import:buildings-from-csv {job_id} {--file=}`.** This command will:
        *   Parse the provided CSV file (from `merged_anomalies.csv`).
        *   `Upsert` the data into the `buildings` table using `building_id` as the key.
        *   Update the corresponding record in the `analysis_jobs` table to `completed`.
    *   **Create a new webhook endpoint `POST /api/webhooks/analysis-complete`.** This secure endpoint will receive a notification from the science team's system, including the URL of the finished CSV. It will then dispatch a background job that runs the `import:buildings-from-csv` command.

### **Step 4: Update Frontend (React SPA)**

**Context:** The entire UI needs to be updated to reflect the new "anomaly detection" model instead of the old TLI scale.

**Action:**
1.  **Remove Tile Layer Logic:** In your `MapView` React component, remove all code related to fetching and displaying the thermal raster tile layer.
2.  **Update Map Legend:** Replace the TLI legend with a simpler binary legend for **Anomaly Status** (e.g., Red for `is_anomaly: true`, Blue for `is_anomaly: false`).
3.  **Update Map Building Styling:** Change the data-driven styling for the building footprints. The color should now be based on the `is_anomaly` boolean property.
4.  **Update Dashboard KPIs:** In your "Building Data Overview" component, replace "High TLI Buildings" with a new card for **"Total Anomalies"**, which counts buildings where `is_anomaly` is true.
5.  **Update Building Details Drawer:** When a user clicks a building, modify the details drawer to display the new, more meaningful metrics:
    *   "Building Heat Loss": `average_heatloss`
    *   "Category Average": `reference_heatloss`
    *   "Deviation from Average": `heatloss_difference`
    *   A prominent badge or status indicator for `is_anomaly`.
6.  **Update Filtering & Sorting:** Modify the UI controls in the context panel to allow users to filter by "Anomalies Only" and to sort the building list by `heatloss_difference` to rank the worst performers.
7.  **(For Admin Dashboard in React):** Add a new UI section for "Analysis Jobs" that lists jobs from the `analysis_jobs` table and includes a "Start New Analysis" button/form that calls the `POST /api/admin/analysis-jobs` endpoint.

---

## **Part 2: High-Level Project & Authentication Summary**

### **What the Project Does Now:**

*   **Your Frontend (React SPA):** Is responsible for the entire **user-facing dashboard**. This includes user login/registration, displaying an interactive map, visualizing building data (footprints colored by anomaly status), and providing detailed metrics and download capabilities for authorized users. It gets all its data by making calls to your Laravel API.
*   **Your Admin Frontend (Blade/AdminLTE):** Is a separate application for **system administration**. It manages users, datasets, and access control (entitlements). It is also responsible for triggering new data analysis jobs.
*   **Your Backend (Laravel API):** Acts as the central brain. Its jobs are to:
    1.  **Authenticate and Authorize** all requests from both frontends.
    2.  **Serve Data** to the React SPA (building data, user info, etc.) after applying strict entitlement filters.
    3.  **Serve Data and Handle Logic** for the AdminLTE dashboard (user lists, entitlement management, etc.).
    4.  **Manage the Data Analysis Workflow:** Trigger external jobs and ingest the results when they are ready.

### **The External REST API (That You Don't Have Yet):**

*   **What it does:** This is the science team's API. It accepts requests to start a long-running (up to 12 hours) data analysis process.
*   **Your Project's Job Related to It:**
    1.  **Trigger:** Your Laravel backend will make a `POST` request to this external API, providing it with links to the source images (e.g., SatVu TIFFs in S3).
    2.  **Receive:** Your backend needs to provide a **webhook URL** to their system. When their long process is finished, their system will call your webhook to notify you that the results (the `merged_anomalies.csv`) are ready and provide a URL to download them. Your project then takes over to ingest that final file.

### **How to Simplify Authentication:**

Your `web.php` and `api.php` show two different authentication systems: one for the Blade admin (`admin.token` middleware) and one for the API (`auth:sanctum`). This is the source of complexity.

**The Simplest Solution:**

**Use Laravel Sanctum for BOTH.**

1.  **React SPA:** Continue using Sanctum's SPA authentication (session cookies). Your `api.php` is already set up correctly for this with the `auth:sanctum` middleware.
2.  **AdminLTE Dashboard:** Change your `admin.token` middleware to also use `auth:sanctum`.
    *   In your `admin.login` controller method, after you successfully authenticate the admin (`Auth::attempt()`), you establish a session just like you would for a regular user.
    *   Then, in your `routes/web.php`, you protect your admin routes with Laravel's standard `auth` middleware, which uses the same session guard. You can add your `auth.admin` role-check middleware after it.

    ```php
    // In routes/web.php
    Route::middleware(['auth', 'auth.admin'])->prefix('admin')->group(function () {
        // Your protected admin Blade routes go here
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        // ...
    });
    ```

This way, you have **one single authentication system (Laravel's session guard, powered by Sanctum for API access)** for your entire application. A user logs in, gets a session cookie, and that same cookie authenticates them whether they are making an API call from React or navigating the Blade admin panel. It's much cleaner and easier to manage.

### **What This Command Is For**

This `dataset:update-metadata` command is an **essential administrative tool**. Its primary purpose is to manage the metadata associated with your datasets *after* they have been ingested. It's not for the regular user but is crucial for you, as the admin/developer, to:

1.  **Manually Correct Data:** Update a dataset's version or its storage location if it changes.
2.  **Generate Summary Statistics:** After importing a `merged_anomalies.csv` file, you can run this command with the `--calculate-stats` flag to get a complete statistical overview of that dataset (how many buildings, how many anomalies, the geographic area it covers, etc.). This is vital for data governance and quality control.

### **Does It Fit? Is It Necessary?**

**Yes, this command is extremely useful and should be kept and updated.** It's a perfect example of a necessary backend utility for managing your data pipeline.

### **What Needs to Be Updated (Crucial Refactoring)**

The command is built around the **old TLI data model**. It must be refactored to work with the new "anomaly detection" data from your `merged_anomalies.csv`.

Here are the specific instructions for you or Cursor AI to update the command.

---

### **Refactoring Guide for `UpdateDatasetMetadataCommand.php`**

**Goal:** Modify this command to calculate statistics based on the new anomaly detection metrics (`is_anomaly`, `average_heatloss`, `heatloss_difference`) instead of the obsolete `thermal_loss_index_tli`.

**Action: Modify the `calculateDatasetStatistics` method.**

1.  **Remove TLI-based Calculations:**
    *   In the first `DB::table('buildings')->selectRaw(...)` call, **delete** all calculations related to `thermal_loss_index_tli`:
        *   REMOVE: `AVG(thermal_loss_index_tli) as avg_tli`
        *   REMOVE: `MIN(thermal_loss_index_tli) as min_tli`
        *   REMOVE: `MAX(thermal_loss_index_tli) as max_tli`
    *   In the second `DB::table('buildings')->selectRaw(...)` call for TLI distribution, **delete** the entire query block.

2.  **Add Anomaly-based Calculations:**
    *   In the first `selectRaw` call, **add** new statistical calculations based on the new columns:
        *   ADD: `SUM(CASE WHEN is_anomaly = true THEN 1 ELSE 0 END) as total_anomalies`
        *   ADD: `AVG(average_heatloss) as avg_heatloss`
        *   ADD: `AVG(heatloss_difference) as avg_heatloss_difference`
        *   ADD: `MIN(heatloss_difference) as min_heatloss_difference`
        *   ADD: `MAX(heatloss_difference) as max_heatloss_difference`

3.  **Update the Returned Statistics Array:**
    *   Modify the `return` array at the end of the method to reflect these changes.

    ```php
    // --- Example of the updated return array ---
    return [
        'calculated_at' => now()->toISOString(),
        'total_buildings' => (int) $stats->total_buildings,
        
        // NEW: Anomaly Statistics
        'anomaly_statistics' => [
            'total_anomalies' => (int) $stats->total_anomalies,
            'anomaly_percentage' => round(((int) $stats->total_anomalies / (int) $stats->total_buildings) * 100, 2),
        ],

        // NEW: Heat Loss Statistics
        'heatloss_statistics' => [
            'average' => round((float) $stats->avg_heatloss, 2),
            'average_difference' => round((float) $stats->avg_heatloss_difference, 2),
            'min_difference' => round((float) $stats->min_heatloss_difference, 2),
            'max_difference' => round((float) $stats->max_heatloss_difference, 2),
        ],

        // KEEP: These are still valid
        'building_type_distribution' => $typeDistribution,
        'spatial_coverage' => $boundingBox,
        // ... any other relevant stats
    ];
    ```

4.  **Update the `displayStatistics` Method:**
    *   Modify the console output logic to print the new, refactored statistics (e.g., "Anomaly Statistics" instead of "TLI Statistics").

By making these changes, the `dataset:update-metadata` command will become a powerful and accurate tool for managing your new data pipeline. It should be run as a final step after each successful data ingestion.