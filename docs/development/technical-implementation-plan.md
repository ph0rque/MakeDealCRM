# Technical Implementation Plan

This plan outlines technical steps to implement features in `docs/planned-features.md`, using SuiteCRM architecture.

### Research & Preparation

* Research free SuiteCRM plugins (Store/vendors like OutRight, AppJetty) for Kanban views (section 2) and checklists (section 3).
* If suitable free plugins found, integrate them to extend core functionality; otherwise, proceed with custom implementations below.

### 1. The "Deal" as the Central Object

**Objective:** Replace "Opportunities" and "Leads" with a central "Deals" module for all acquisition activities.

**Technical Implementation Plan:**

1.  **Create "Deals" Module:**
    *   Use Module Builder (Admin -> Developer Tools) for upgrade-safe creation, extending `Basic` type.
    *   **Bean Class (`modules/Deals/Deal.php`):** Extends `Basic`, implements `ACL`.
    *   **Vardefs (`modules/Deals/vardefs.php`):** Fields include `name` (varchar, required), `status` (enum, e.g., 'Sourcing'), `source` (enum, e.g., 'Email'), `deal_value` (currency), plus financial fields from feature #5.
    *   **Relationships:** One-to-Many with `Contacts`, `Documents`, `Tasks`, `Notes`, `ChecklistItems`, defined in `vardefs.php`.

2.  **Data Capture:**
    *   **Email Forwarding:** Configure inbound email (Admin -> Inbound Email); add `after_email_import` logic hook to parse content, create/update `Deal`, add `Contacts`, relate attachments.
    *   **CSV Import:** Use built-in import with custom field mapping.
    *   **Web Forms & Chrome Extension:** Extend V8 REST API (per `03-api-architecture.md`) to receive data and create `Deal` records.

3.  **Duplicate Prevention:**
    *   Override Edit View controller (`custom/modules/Deals/views/view.edit.php`).
    *   Add JS AJAX lookup on `name` field to warn of duplicates.

4.  **Disable Legacy Modules:**
    *   Hide "Leads" and "Opportunities" in Admin -> Display Modules and Subpanels.

### 2. Unified Deal & Portfolio Pipeline

**Objective:** Add Kanban pipeline for "Deals" with at-risk indicators and WIP limits. (Check SuiteCRM Store for existing Kanban plugins.)

**Technical Implementation Plan:**

1.  **Custom Pipeline View:**
    *   Create `custom/modules/Deals/views/view.pipeline.php` to fetch/organize `Deals` by `status`; load Smarty template for Kanban.

2.  **Kanban UI:**
    *   Use HTML/CSS/JS with Dragula.js or SortableJS for drag-and-drop.
    *   Columns match `status`; cards show key info (name, value).

3.  **At-Risk Indicators:**
    *   Add daily scheduled job (Admin -> Schedulers) to check `date_modified` for time-in-stage.
    *   Update custom field `at_risk_status` (enum: 'Normal', 'Warning', 'Alert') for 14/30-day thresholds; style cards via CSS.

4.  **WIP Limits and Focus Flag:**
    *   Client-side JS counts cards per stage, warns on WIP exceedance.
    *   Add boolean field `focus_c` togglable from cards.

### 3. Personal Due-Diligence Checklists

**Objective:** Add templated checklists for due-diligence. (Check SuiteCRM Store for existing checklist modules.)

**Technical Implementation Plan:**

1.  **Custom Modules:**
    *   `ChecklistTemplates`: Fields `name`, `description`.
    *   `ChecklistTemplateItems`: Fields `name`, `template_id` (relate), `requires_file` (bool).
    *   `DealChecklists`: Fields `name`, `deal_id` (relate to `Deals`), `template_id` (relate), `percent_complete` (float).
    *   `DealChecklistItems`: Fields `name`, `checklist_id` (relate), `status` (enum: 'Pending', 'Complete'), `document_id` (relate to `Documents`).

2.  **Apply Template:**
    *   Add button on `Deal` detail view for AJAX call to custom API endpoint.
    *   Endpoint creates `DealChecklist` linked to `Deal`; copies `ChecklistTemplateItems` to `DealChecklistItems`.

3.  **File Requests:**
    *   Add `after_save` logic hook on `DealChecklistItems`.
    *   If `requires_file` true, trigger workflow email to primary `Contact` with unique, time-limited link to secure unauthenticated upload endpoint.

4.  **Percent Complete:**
    *   Add `after_save` logic hook on `DealChecklistItems` to recalculate/update `percent_complete` on parent `DealChecklist`.
    *   Display on `Deal` detail view and Kanban cards.

### 4. Simplified Stakeholder Tracking

**Objective:** Streamline external contact management for deals.

**Technical Implementation Plan:**

1.  **Customize Contacts:**
    *   Add custom `role` (multi-select enum) to `accounts_contacts` relationship (use custom table if needed).
    *   Modify `Contacts` subpanel under `Deals` to display `role`.

2.  **"Introduce" Action:**
    *   Add button to `Contacts` subpanel on `Deal` detail view.
    *   Opens popup to select contacts and template; AJAX call to endpoint sends email.

3.  **Last-Contact-Date Badge:**
    *   Add custom field `last_contact_date_c` on `Contacts`.
    *   Use scheduled job to update from latest `Emails`/`Calls`/`Meetings`.
    *   Display in subpanel with conditional styling.

### 5. At-a-Glance Financial & Valuation Hub

**Objective:** Centralize financial metrics and "what-if" calculator.

**Technical Implementation Plan:**

1.  **Financial Fields:**
    *   Add to `Deals` vardefs: `asking_price_c`, `ttm_revenue_c`, `ttm_ebitda_c`, `sde_c`, `proposed_valuation_c`, `target_multiple_c` (all currency except multiple as float).

2.  **Custom Dashlet:**
    *   Create dashlet for `Deals` displaying fields and JS calculator.
    *   Calculator updates `proposed_valuation_c` in real-time on EBITDA/multiple changes; "Save" persists to DB.

3.  **Capital Stack and Comparables:**
    *   **Stack:** Add fields `equity_c`, `senior_debt_c`, `seller_note_c`; dashlet checks debt-coverage.
    *   **Comparables:** Create `Comparables` module for manual entries; dashlet pulls medians.

### 6. One-Click Deployment to AWS

**Objective:** Automate deployment for easy AWS setup.

**Technical Implementation Plan:**

1.  **Validate Docker Setup:**
    * Test current Dockerfile and docker-compose.yml in staging/production-like environments.
    * Run `docker-compose up` to verify services (app, DB, Redis) start without errors.
    * Test key features (e.g., login, module access); check logs for issues.
    * Simulate scaling (e.g., multiple containers) and backups.

2.  **Automation Scripts:**
    * Use AWS CloudFormation or Terraform for infrastructure: EC2 (e.g., t3.micro for Solo Tier), RDS (database), ElastiCache (Redis), S3 (backups), IAM/security groups.

3.  **"One-Click" Experience:**
    * Add "Launch Stack" button in `README.md` linking to CloudFormation console with pre-loaded template.
    * Template parameters include admin username/password.

4.  **Scheduled Backups:**
    * Use Lambda function triggered by CloudWatch Event (scheduled).
    * Runs `mysqldump` on RDS, compresses, uploads to S3; syncs `upload/` directory to S3.
