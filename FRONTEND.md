## **MELT-B MVP: Phase 3 Guide - Frontend Core Dashboard & Map Interaction**

**Goal for this Phase:** To develop the primary user-facing dashboard, focusing on creating the interactive map, setting up the core user interface, and integrating the essential authentication and data APIs developed in previous phases.

**Prerequisites:**
*   The Laravel backend is running and serving a single Blade view for the React application.
*   All required backend API endpoints from Phase 1 & 2 are complete and available (e.g., `/api/login`, `/api/logout`, `/api/me/entitlements`, `/api/buildings`, `/api/tiles`).

---

### **3.1. Frontend - SPA Setup & Authentication Flow**

1.  **Implement Client-Side Routing:**
    *   Set up the main routing structure for the SPA using your chosen routing library (e.g., `react-router-dom`).
    *   Define routes for `/login` (public), `/dashboard` (protected), `/profile` (protected), and `/admin` (protected, admin role only).
2.  **Create Protected Route Logic:**
    *   Develop a wrapper component (e.g., `<ProtectedRoute>`) that checks for a valid authentication token.
    *   If a user is not authenticated, this component should redirect them to the `/login` route.
3.  **Build Authentication UI Components:**
    *   Create the UI forms for Login, Registration, and Forgot Password.
4.  **Integrate Authentication APIs:**
    *   Connect the Login form to the `POST /api/login` endpoint. On success, securely store the received API token/session state.
    *   Connect the Registration form to the `POST /api/register` endpoint.
    *   Implement the logout functionality to call `POST /api/logout` and clear the stored token/session.
5.  **Implement Silent Token Refresh:**
    *   Set up a mechanism (e.g., an Axios interceptor or a timer) to silently refresh the authentication token before it expires (e.g., every 55 minutes), ensuring a seamless user session.

---

### **3.2. Frontend - Core Dashboard Layout & Structure**

1.  **Develop the Main Layout Component:**
    *   Create the primary shell for the authenticated user experience (e.g., `<DashboardLayout>`).
    *   This component will contain a main top navigation bar, a primary content area for the map, and the placeholder for the collapsible side panel.
2.  **Build the Top Navigation Bar:**
    *   Implement the top navigation bar component.
    *   Include a link to the "Downloads" centre.
    *   Include a user profile dropdown menu.
3.  **Implement the User Profile Menu:**
    *   When clicked, this menu should display options to navigate to the user's `/profile` page and a "Logout" button that triggers the logout API call.

---

### **3.3. Frontend - Interactive Map View Implementation**

1.  **Integrate MapLibre GL:**
    *   Create a dedicated React component (`<MapView>`) to initialize and render the MapLibre GL map. This component will be the centerpiece of the `/dashboard` route.
2.  **Implement Dynamic Tile Layer:**
    *   Add a raster tile source to the map.
    *   The URL for this source must dynamically fetch tiles from the `/api/tiles/{dataset_id}/{z}/{x}/{y}` endpoint.
    *   Ensure that every tile request includes the user's authentication token in the `Authorization: Bearer <token>` header.
    *   The tile layer should only become visible when the user zooms in to the specified level (≥1:10 000).
3.  **Implement Building Footprint Layer:**
    *   On map load or view change, fetch building footprint data from the `/api/buildings` endpoint (passing the current map viewport as a filter if applicable).
    *   Add the returned GeoJSON data as a new vector layer to the map.
4.  **Implement TLI-Based Styling:**
    *   Use MapLibre's data-driven styling features to color the building footprint layer.
    *   The fill color of each building polygon should be determined by its `thermal_loss_index_tli` property.
5.  **Implement Map Interactivity (Click Events):**
    *   Add a click event listener specifically to the building footprint layer.
    *   When a user clicks on a building, capture that building's feature data (ID, TLI, etc.).
    *   Use this data to update the application's state, which will trigger the opening and population of the context panel/details drawer.

---

### **3.4. Frontend - Context Panel & Building Interaction**

1.  **Develop the Collapsible Panel Component:**
    *   Create the right-hand side panel component.
    *   Its visibility and content should be controlled by the application's state (e.g., which building is currently selected).
2.  **Implement Search & Filter Component:**
    *   Add a search input field and filter dropdowns (e.g., for building type) to the panel.
    *   When a user types in the search bar or applies a filter, trigger a new API call to `/api/buildings` with the appropriate query parameters.
3.  **Implement the Building List Table:**
    *   Create a table component within the panel that displays the paginated results returned from the `/api/buildings` API.
    *   Make each row in the table clickable. A click should highlight the corresponding building on the map and populate the details drawer.
4.  **Implement the Building Details Drawer:**
    *   Create the component for the details drawer, which appears when a building is selected.
    *   Initially, populate this drawer with basic information from the selected building's data (e.g., Address, GID, TLI, Classification). Charts and other KPIs will be added in Phase 4.

---

### **3.5. DevOps & Testing for Frontend**

1.  **Set up Unit Testing:**
    *   Configure the testing environment (e.g., Jest, React Testing Library).
    *   Write initial unit tests for key UI components (e.g., Login form, Map component) to verify they render correctly.
2.  **Set up End-to-End (E2E) Testing:**
    *   Configure an E2E testing framework (e.g., Cypress or Playwright).
    *   Write initial E2E test scripts for the most critical user flows:
        *   User login.
        *   Dashboard page loading correctly.
        *   Map and initial building data appearing.
3.  **Integrate Testing into CI Pipeline:**
    *   Update the CI pipeline configuration to automatically run both unit and E2E tests for the frontend on every code commit to the repository.