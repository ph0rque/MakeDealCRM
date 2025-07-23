# Planned Features for MakeDealCRM

This document outlines the planned features for customizing the CRM to serve the specific needs of a solo dealmaker (e.g., a business buyer, micro-PE investor, or search fund principal). The core principle is to streamline the entire acquisition lifecycle for a single individual who handles all roles—from sourcing and analysis to closing and post-acquisition management.

---

### 1. The "Deal" as the Central Object

The legacy "Opportunities" and "Leads" modules will be **re-engineered and fully replaced** by a single **"Deals"** module. This becomes the undisputed hub for every data point tied to an acquisition target.

*   **Feature Highlights**
    *   Auto-capture from multiple sources: email forwarding, CSV import, web forms, and a Chrome extension.
    *   Duplicate-preventing lookup helpers warn when a company already exists.
    *   All related Contacts, Documents, Tasks, Financials, and Checklist items roll up under the Deal.

*   **User Story (Given/When/Then):**  
    *Given* I forward an email thread to `deals@mycrm`, *when* the CRM processes the message, *then* it should (a) create or update the corresponding Deal, (b) link the sender/recipients as Contacts with correct roles, and (c) attach all files—so I always work from a single, up-to-date record with zero manual sorting.

---

### 2. Unified Deal & Portfolio Pipeline

A single, visual pipeline will track a deal from initial identification through to post-close management—**including WIP limits and "at-risk" indicators** to keep a solo operator focused.

*   **Feature Highlights**
    *   Unified Kanban-style pipeline covering acquisition and post-acquisition stages (listed below).
    *   Time-in-stage counter; cards turn orange after 14 idle days and red after 30.
    *   Optional WIP limit or "Focus" flag per stage.
    *   Mobile gestures for drag-and-drop between stages.

*   **Suggested Stages:**
    1.  **Sourcing** – Initial identification of a potential target.
    2.  **Screening** – Quick review against investment thesis.
    3.  **Analysis & Outreach** – Initial contact made, preliminary data received.
    4.  **Due Diligence** – Deep-dive investigation (financial, legal, operational).
    5.  **Valuation & Structuring** – Price and terms determination.
    6.  **LOI / Negotiation** – Letter of Intent submitted and being negotiated.
    7.  **Financing** – Securing bank or investor funding.
    8.  **Closing** – Final legal process.
    9.  **Closed/Owned – 90-Day Plan** – Active integration and initial management.
    10. **Closed/Owned – Stable Operations** – Ongoing management and value creation.
    11. **Unavailable** – Deal no longer active (out-bid, too small/large, etc.).

*   **User Story (Given/When/Then):**  
    *Given* I open the pipeline on my phone, *when* a deal card has been idle for more than my SLA, *then* it should be highlighted so I can immediately re-prioritize my day and avoid stalled opportunities.

---

### 3. Personal Due-Diligence Checklists

To ensure rigor and prevent oversight when working alone, a templated checklist system is crucial.

*   **Feature Highlights**
    *   Create and save checklist templates (e.g., "Quick-Screen", "Full Financial DD", "Legal DD").
    *   Applying a template auto-generates tasks **with percent-complete indicator** displayed on the Deal card.
    *   Checklist items can optionally request files ("Upload last 3y tax returns"). Seller receives an automated email with the request.
    *   Export checklist (PDF/Excel) for lenders or investors.

*   **User Story:**  
    "As a solo buyer, I want to apply my standard Due-Diligence checklist with auto-scheduled reminders, file requests, and a visible completion bar, so I’m confident nothing slips through the cracks."

---

### 4. Simplified Stakeholder Tracking

Even when working alone, a deal involves multiple external parties. The CRM must make tracking them effortless.

*   **Feature Highlights**
    *   Streamlined Contacts section within each Deal with role tags (`Seller`, `Broker`, `Attorney`, `Accountant`, `Lender`).
    *   Quick "Introduce" action to send templated emails between selected stakeholders.
    *   Last-contact-date badge to surface stale relationships.

*   **User Story:**  
    "As the sole point of contact, I want to instantly access or contact key stakeholders—complete with last-touch indicators and pre-filled templates—so I get answers quickly without digging through emails."

---

### 5. At-a-Glance Financial & Valuation Hub

Key financial metrics must be easily accessible and comparable across deals.

*   **Feature Highlights**
    *   Dashboard widget displaying: `Asking Price`, `TTM Revenue`, `TTM EBITDA`, `SDE`, `My Proposed Valuation`, `Target Multiple`.
    *   **What-if calculator**—changing EBITDA or multiple updates valuation instantly.
    *   Optional "Capital Stack" section (Equity, Senior Debt, Seller Note) with debt-coverage checks.
    *   Comparables sidebar pulling median multiples from a private database for context.

*   **User Story:**  
    "As an investor working alone, I want to sort deals by key metrics and run quick what-if valuations—including capital-stack feasibility—so I can focus on the most promising opportunities without spreadsheets."

---

### 6. One-Click Deployment to AWS

To enable easy setup and hosting without technical expertise, provide a streamlined deployment process to AWS cloud services.

*   **Feature Highlights**
    *   One-click deployment presets: **Solo Tier** with monthly cost estimate, and an upgrade path to larger instances.
    *   Automated scripts for Docker container deployment, database setup, security hardening, and **scheduled encrypted backups to S3**.
    *   Step-through wizard guides AWS account linking with minimal input.

*   **User Story:**  
    "As a solo dealmaker with limited IT skills and budget, I want to deploy the CRM on AWS’s free/low-cost tier with scheduled backups and a clear upgrade path, so I start fast and scale later without headaches."
