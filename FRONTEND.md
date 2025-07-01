Of course. Phase 4 is all about adding depth and completing the user experience by building out the detailed data views and the final administrative interfaces.

Here is the detailed, step-by-step guide for what to do in Phase 4.

---

## **MELT-B MVP: Phase 4 Guide - Enhancements & Admin UI Completion**

**Goal for this Phase:** To transition from a core functional dashboard to a fully-featured MVP. This involves enriching the user's data exploration capabilities with detailed charts and download options, and ensuring the admin has all the tools needed to manage the platform and its data workflows.

**Prerequisites:**
*   Phase 3 is complete. The interactive map, basic context panel, and user authentication are fully functional.
*   All backend APIs for serving building data, handling auth, and managing access are working.

---

### **4.1. Frontend - Detailed Building Insights**

**Context:** The current details drawer only shows basic text information. This step is about adding rich, visual data representations to provide deeper insights when a user selects a building.

**Action:**
1.  **Select a Charting Library:** Choose and install a React charting library (e.g., `Recharts`, `Chart.js`, `Nivo`).
2.  **Enhance the Building Details Drawer Component:**
    *   **Implement a Bar Chart for Heat Loss Comparison:**
        *   Create a new chart component within the drawer.
        *   This chart should display two bars side-by-side:
            1.  The selected building's `average_heatloss`.
            2.  The `reference_heatloss` for its category.
        *   This provides an instant visual comparison, showing how the selected building performs against its peers.
    *   **Display Key Performance Indicators (KPIs) Clearly:**
        *   Show the `heatloss_difference` with a clear label like "Deviation from Average". Use color (e.g., red for positive/worse, green for negative/better) to add meaning.
        *   Display the `confidence` score for the building's classification.
        *   Display the potential `co2_savings_estimate` if available.
    *   **Add a Conditional "Download" Button:**
        *   The download button for the selected building's data should now be visible in the drawer.
        *   **Important:** This button should only be enabled if the user's entitlements (fetched from `/api/me/entitlements`) permit downloading data in a specific format (e.g., CSV).

---

### **4.2. Frontend - Download Centre & Profile Management**

**Context:** This step focuses on building out the user-facing pages that are linked from the main navigation, providing self-service capabilities.

**Action:**
1.  **Build the Download Centre Page (`/downloads`):**
    *   Create a new page component that fetches data from the `/api/me/entitlements` endpoint.
    *   Display a list of all datasets the user is entitled to access.
    *   For each dataset, provide buttons to download the data in the allowed formats (e.g., "Download as CSV", "Download as GeoJSON"). These buttons will call the `/api/downloads/{id}` endpoint.
    *   **Display the API URL Badge for Developers:** Show a pre-formatted, read-only text box containing the generic API URL for that dataset, along with a "Copy" button. This helps developers integrate with our system.
2.  **Implement Asynchronous Download Logic:**
    *   If a download is expected to be large, the API call should trigger a background job.
    *   The frontend should display a notification like "Your download is being prepared and will be available shortly." and update the UI when the file is ready (e.g., by polling a status endpoint or using websockets).
3.  **Build the User Profile Page (`/profile`):**
    *   Create a page component with forms for profile management.
    *   **Update Profile Information:** Form to update user's name and email, which calls a `PUT /api/user/profile-information` endpoint.
    *   **Change Password:** Form to update the user's password.
    *   **Manage API Tokens:**
        *   A section to list the user's active API tokens.
        *   A button to "Generate New Token" that calls `POST /api/user/generate-api-token`. The newly generated token should be displayed **once** for the user to copy.
        *   A "Revoke" button next to each token that calls `DELETE /api/user/api-tokens/{id}`.

---

### **4.3. Backend & Admin UI - Double-Checking & Finalizing**

**Context:** Although you have a full admin UI, this is the time to double-check that all administrative features required by the new workflow are implemented and robust.

**Action:**
1.  **Verify Admin User Management:**
    *   Ensure the Admin UI allows creating users with specific roles ('admin', 'municipality', etc.).
    *   Confirm that updating and deleting users works as expected.
2.  **Verify Admin Entitlement Management:**
    *   **Crucial:** The UI for creating/editing entitlements must be updated. The `'TILES'` option should be removed.
    *   The UI must provide a way to input `aoi_geom` (e.g., by pasting GeoJSON or using a map-drawing tool) and `building_gids` (e.g., a text area for comma-separated IDs).
    *   Confirm the interface for assigning entitlements to users is intuitive and functional.
3.  **Implement Admin Analysis Job Management:**
    *   **Backend:** Ensure the `POST /api/admin/analysis-jobs` and the `GET /api/admin/analysis-jobs` (to list jobs) endpoints are complete.
    *   **Frontend (AdminLTE Blade/React components):**
        *   Build the UI for the "Data Analysis Jobs" page.
        *   This UI should display a table of all analysis jobs with their status (`pending`, `completed`, `failed`), start/end times, and a link to the output file if completed.
        *   Implement the "Start New Analysis" form that submits the S3 image links to the `POST /api/admin/analysis-jobs` endpoint.
4.  **Finalize Audit Log Viewing:**
    *   Ensure the Admin UI can display and filter the audit logs effectively, providing clear insight into administrative actions.

---

### **4.4. DevOps & Testing for Full MVP**

**Context:** With all features now in place, the focus shifts to comprehensive testing to ensure the entire application is stable and reliable.

**Action:**
1.  **Expand End-to-End (E2E) Test Suite:**
    *   Write new E2E tests to cover all the features added in Phase 4:
        *   Verify that charts in the details drawer render with correct data.
        *   Test the download functionality for each format.
        *   Test the entire user profile update and API token management flow.
        *   Test the admin's ability to create a new analysis job.
        *   Simulate the webhook callback and verify that the job status updates and the data is ingested correctly.
2.  **Perform Cross-Browser and Responsiveness Testing:**
    *   Manually test the application on different browsers (Chrome, Firefox, Safari) and screen sizes (desktop, tablet) to catch any UI inconsistencies.
3.  **Run Final Integration Tests:**
    *   Conduct tests that span the entire workflow: an admin triggers a (simulated) job, the backend ingests the data, and a regular user can immediately see the updated anomaly data on their map dashboard.
4.  **Review CI/CD Pipeline:**
    *   Ensure all new tests are reliably running in the CI pipeline on every commit. Confirm that the pipeline can successfully build the production assets for both the Laravel backend and the React frontend.