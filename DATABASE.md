## **MELT-B MVP: Database Schema Design (PostgreSQL + PostGIS)**

We'll use `PostgreSQL` with the `PostGIS` extension, and the `matanyadaev/laravel-eloquent-spatial` package will be instrumental for handling spatial data in Laravel.

### **Core Principles:**

*   **Granular Access Control (ABAC):** Data access is defined by `entitlements` linked to users.
*   **Spatial Data:** Heavy reliance on `PostGIS` for `AOI` and `building` geometries.
*   **Pre-generated Data:** The `buildings` table will store the processed outputs from the Data Science team.
*   **Auditability:** Basic logging for administrative actions.
*   **PII Compliance:** Careful handling of personally identifiable information.

---

### **Tables & Columns:**

#### **1. `users` Table**

Stores user authentication details and basic profile information.

| Column Name        | Data Type (PostgreSQL) | Constraints                                   | Description                                                     |
| :----------------- | :--------------------- | :-------------------------------------------- | :-------------------------------------------------------------- |
| `id`               | `BIGINT` (PK)          | `AUTO_INCREMENT`, `PRIMARY KEY`               | Unique identifier for the user.                                 |
| `name`             | `VARCHAR(255)`         | `NOT NULL`                                    | User's full name.                                               |
| `email`            | `VARCHAR(255)`         | `NOT NULL`, `UNIQUE`                          | User's email address (used for login).                          |
| `password`         | `VARCHAR(255)`         | `NOT NULL`                                    | Hashed password.                                                |
| `role`             | `VARCHAR(50)`          | `NOT NULL`, (e.g., 'admin', 'municipality', 'researcher', 'contractor', 'user') | User's role, used for gatekeeping features (Admin UI, analytics). |
| `api_key`          | `VARCHAR(255)`         | `NULLABLE`, `UNIQUE`                          | API token for programatic access (e.g., Service Bot).           |
| `contact_info`     | `JSONB`                | `NULLABLE`                                    | Additional business contact details (GDPR compliant PII only).  |
| `email_verified_at`| `TIMESTAMP`            | `NULLABLE`                                    | Timestamp when email was verified.                              |
| `remember_token`   | `VARCHAR(100)`         | `NULLABLE`                                    | For "remember me" functionality.                                |
| `created_at`       | `TIMESTAMP`            | `NOT NULL`                                    | Record creation timestamp.                                      |
| `updated_at`       | `TIMESTAMP`            | `NOT NULL`                                    | Last update timestamp.                                          |

#### **2. `datasets` Table**

Stores metadata about the different data bundles available (e.g., specific thermal raster releases).

| Column Name       | Data Type (PostgreSQL) | Constraints                           | Description                                                     |
| :---------------- | :--------------------- | :------------------------------------ | :-------------------------------------------------------------- |
| `id`              | `BIGINT` (PK)          | `AUTO_INCREMENT`, `PRIMARY KEY`       | Unique identifier for the dataset.                              |
| `name`            | `VARCHAR(255)`         | `NOT NULL`, `UNIQUE`                  | Human-readable name (e.g., "Thermal Raster v2024-Q4 Debrecen"). |
| `description`     | `TEXT`                 | `NULLABLE`                            | Detailed description of the dataset.                            |
| `data_type`       | `VARCHAR(50)`          | `NOT NULL`, (e.g., 'thermal-raster', 'building-data') | Type of data bundle.                                            |
| `storage_location`| `TEXT`                 | `NOT NULL`                            | Path or prefix in object storage (e.g., S3 bucket/prefix).      |
| `version`         | `VARCHAR(50)`          | `NULLABLE`                            | Version identifier for the dataset.                             |
| `created_at`      | `TIMESTAMP`            | `NOT NULL`                            |                                                                 |
| `updated_at`      | `TIMESTAMP`            | `NOT NULL`                            |                                                                 |

#### **3. `entitlements` Table**

Defines the granular access rules (ABAC-style).

| Column Name       | Data Type (PostgreSQL) | Constraints                               | Description                                                     |
| :---------------- | :--------------------- | :---------------------------------------- | :-------------------------------------------------------------- |
| `id`              | `BIGINT` (PK)          | `AUTO_INCREMENT`, `PRIMARY KEY`           | Unique identifier for the entitlement.                          |
| `type`            | `VARCHAR(50)`          | `NOT NULL`, (e.g., 'DS-ALL', 'DS-AOI', 'DS-BLD', 'TILES') | The type of entitlement.                                        |
| `dataset_id`      | `BIGINT`               | `NOT NULL`, `FK` to `datasets.id`         | Which dataset this entitlement applies to.                      |
| `aoi_geom`        | `GEOMETRY(POLYGON, 4326)`| `NULLABLE`, `SPATIAL_INDEX`               | PostGIS Polygon for Area of Interest (for 'DS-AOI', 'TILES').  |
| `building_gids`   | `JSONB`                | `NULLABLE`                                | JSON array of specific building GIDs (for 'DS-BLD').            |
| `download_formats`| `JSONB`                | `NULLABLE`, (e.g., `["csv", "geojson", "xlsx"]`) | Allowed download formats for this entitlement.                  |
| `expires_at`      | `TIMESTAMP`            | `NULLABLE`                                | Date/time when the entitlement expires.                         |
| `created_at`      | `TIMESTAMP`            | `NOT NULL`                                |                                                                 |
| `updated_at`      | `TIMESTAMP`            | `NOT NULL`                                |                                                                 |

#### **4. `user_entitlement` Table (Pivot Table)**

Links users to their specific entitlements (many-to-many relationship).

| Column Name    | Data Type (PostgreSQL) | Constraints                         | Description                               |
| :------------- | :--------------------- | :---------------------------------- | :---------------------------------------- |
| `user_id`      | `BIGINT`               | `NOT NULL`, `FK` to `users.id`      | Foreign key to the `users` table.         |
| `entitlement_id`| `BIGINT`               | `NOT NULL`, `FK` to `entitlements.id` | Foreign key to the `entitlements` table.  |
| `created_at`   | `TIMESTAMP`            | `NOT NULL`                          | Timestamp when the entitlement was granted. |
| `PRIMARY KEY`  |                        | `(user_id, entitlement_id)`         | Composite primary key for uniqueness.     |

#### **5. `buildings` Table**

Stores the pre-generated thermal analysis results and building characteristics.

| Column Name                | Data Type (PostgreSQL) | Constraints                               | Description                                                     |
| :------------------------- | :--------------------- | :---------------------------------------- | :-------------------------------------------------------------- |
| `gid`                      | `VARCHAR(255)` (PK)    | `PRIMARY KEY`                             | Global Identifier for the building (from source data).          |
| `geometry`                 | `GEOMETRY(POLYGON, 4326)`| `NOT NULL`, `SPATIAL_INDEX`               | PostGIS Polygon representing the building's footprint. (SRID 4326 for WGS84 Lat/Lon). |
| `thermal_loss_index_tli`   | `INTEGER`              | `NOT NULL`                                | Calculated Thermal Loss Index (0-100).                          |
| `building_type_classification` | `VARCHAR(100)`         | `NOT NULL`                                | e.g., 'residential', 'commercial', 'industrial'.                |
| `co2_savings_estimate`     | `NUMERIC(10,2)`        | `NULLABLE`                                | Estimated CO2 savings potential.                                |
| `address`                  | `TEXT`                 | `NULLABLE`                                | Building's street address.                                      |
| `owner_operator_details`   | `TEXT`                 | `NULLABLE`                                | Business contact details for owner/operator (GDPR compliant).   |
| `cadastral_reference`      | `VARCHAR(255)`         | `NULLABLE`                                | Cadastral reference ID.                                         |
| `dataset_id`               | `BIGINT`               | `NOT NULL`, `FK` to `datasets.id`         | Which dataset provided this building's thermal data.            |
| `last_analyzed_at`         | `TIMESTAMP`            | `NOT NULL`                                | Timestamp of the thermal analysis.                              |
| `before_renovation_tli`    | `INTEGER`              | `NULLABLE`                                | TLI before any renovation (for comparison).                     |
| `after_renovation_tli`     | `INTEGER`              | `NULLABLE`                                | TLI after renovation (for comparison).                          |
| `created_at`               | `TIMESTAMP`            | `NOT NULL`                                |                                                                 |
| `updated_at`               | `TIMESTAMP`            | `NOT NULL`                                |                                                                 |

#### **6. `audit_logs` Table**

To track administrative actions, as implied by the "Admin area" requirements.

| Column Name | Data Type (PostgreSQL) | Constraints                   | Description                                             |
| :---------- | :--------------------- | :---------------------------- | :------------------------------------------------------ |
| `id`        | `BIGINT` (PK)          | `AUTO_INCREMENT`, `PRIMARY KEY` | Unique identifier.                                      |
| `user_id`   | `BIGINT`               | `NOT NULL`, `FK` to `users.id` | User who performed the action.                          |
| `action`    | `VARCHAR(255)`         | `NOT NULL`                    | Description of the action (e.g., 'user_created', 'entitlement_updated'). |
| `target_type`| `VARCHAR(100)`         | `NULLABLE`                    | Type of entity affected (e.g., 'user', 'entitlement').  |
| `target_id` | `BIGINT`               | `NULLABLE`                    | ID of the affected entity.                              |
| `old_values`| `JSONB`                | `NULLABLE`                    | Snapshot of relevant data before the action.            |
| `new_values`| `JSONB`                | `NULLABLE`                    | Snapshot of relevant data after the action.             |
| `ip_address`| `VARCHAR(45)`          | `NULLABLE`                    | IP address of the user.                                 |
| `user_agent`| `TEXT`                 | `NULLABLE`                    | User agent string of the client.                        |
| `created_at`| `TIMESTAMP`            | `NOT NULL`                    | Timestamp of the action.                                |

