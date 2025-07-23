### **Guide for Implementing the Interactive AOI Map Editor**

**Objective:** To replace the manual coordinate input form with a user-friendly, interactive map editor in the Admin Dashboard for creating and editing `DS-AOI` entitlements.

**User Experience Goal:** The admin should be able to visually draw, view, and modify an Area of Interest (AOI) directly on a map, and see how it relates to other existing AOIs.

---

### **Step-by-Step Functional Guide:**

#### **1. Initial State & Map Display**

1.  **Display the Interactive Map:** When the admin opens the "Create/Edit Entitlement" page, the form should display a fully interactive map.
2.  **Provide Drawing Tools:** The interface must include simple tools for the admin to create a shape:
    *   A "Draw Rectangle" button.
    *   A "Draw Polygon" button (for custom shapes).
    *   An "Edit Shape" button.
    *   A "Delete Shape" button.

#### **2. Creating a New AOI**

1.  **Initiate Drawing:** The admin clicks a drawing tool (e.g., "Draw Rectangle").
2.  **Draw on Map:** The admin clicks and drags on the map to define the new AOI's boundaries.
3.  **Capture Geometry:** As the admin finishes drawing, the system must automatically capture the geographic coordinates of the newly created shape. This captured data will be submitted with the form when saved.

#### **3. Editing an Existing AOI**

1.  **Load the Existing Shape:** When an admin edits an entitlement that already has an AOI, the map must automatically load and display the saved shape.
2.  **Enable Editing:** The admin can click the "Edit Shape" tool.
3.  **Modify the Shape:** The admin must be able to drag the corners (vertices) of the polygon to resize and reshape it.
4.  **Update Geometry:** As the shape is modified, the system must automatically update the captured GeoJSON data in the background, ready to be saved.

#### **4. Handling Existing and Overlapping AOIs (Crucial Feature)**

1.  **Display Other AOIs:** To prevent accidental overlaps and provide context, the map must **display all other existing AOIs** from the database as read-only, semi-transparent layers.
2.  **Visual Distinction:**
    *   The **current AOI being edited** should have a distinct style (e.g., solid, bright-colored border).
    *   **All other AOIs** should have a different, less prominent style (e.g., dashed, greyed-out border).
3.  **Support for Overlapping:** The system **should allow AOIs to overlap** if required by the business logic. The purpose of displaying them is to give the admin the information they need to make an informed decision—either to avoid an overlap or create one intentionally.

---

### **Backend vs. Frontend Work**

*   **Frontend Work (95% of the effort):**
    *   Integrating the map library and the drawing plugin.
    *   Handling the UI for the drawing tools.
    *   Implementing the logic to display the current AOI and all other AOIs with different styles.
    *   Capturing, storing (in state), and submitting the final GeoJSON data from the map.

*   **Backend Work (5% of the effort):**
    *   **Create one new API endpoint:** `GET /api/admin/entitlements/all-aois`. This endpoint will return a GeoJSON FeatureCollection of all existing AOI geometries from the `entitlements` table. The frontend will call this endpoint to get the data for the read-only layers.
    *   **Modify the existing "Save Entitlement" endpoint:** Ensure it can correctly receive the GeoJSON string from the form, validate it, and convert it to a PostGIS geometry before saving.