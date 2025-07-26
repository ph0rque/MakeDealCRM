# Product Requirements Document: End-to-End (E2E) Testing

## 1. Overview

This document outlines the end-to-end (E2E) testing requirements for the core features of the MakeDealCRM platform. The goal is to validate complete user workflows from start to finish, ensuring that the integrated system functions as expected from a user's perspective.

These tests will be designed for automation using a framework like Playwright to simulate real user interactions in a production-like environment.

## 2. E2E Testing Scope

This PRD covers the following five key features:
1.  The "Deal" as Central Object
2.  Unified Deal & Portfolio Pipeline
3.  Personal Due-Diligence Checklists
4.  Simplified Stakeholder Tracking
5.  At-a-Glance Financial & Valuation Hub

---

### Feature 1: The "Deal" as Central Object

*   **Objective**: Verify that a user can create, manage, and view a Deal as the central hub of information, with all related entities (Contacts, Documents) correctly linked, saved, and displayed.
*   **User Story**: As a solo dealmaker, I want to create a new deal, associate contacts and documents with it, and see all this information consolidated in the deal's detail view, ensuring data integrity throughout the process.

#### Test Case 1.1: E2E Deal Creation and Data Association

*   **Scenario**: A user creates a new deal and attaches a new contact and a document to it. The test will verify that all associated data is correctly linked and displayed in the deal's detail view.
*   **Pre-conditions**:
    *   User is logged into the application.
    *   User has the necessary permissions to create and edit Deals, Contacts, and Documents.
*   **Test Steps**:
    1.  **Navigate** to the Deals module.
    2.  **Click** the "Create Deal" button.
    3.  **Fill** in all required fields for the new deal, including name, and financial data like `TTM Revenue` and `TTM EBITDA`.
    4.  **Save** the new deal.
    5.  **Verify** that the system navigates to the new deal's detail view.
    6.  From the **Contacts subpanel**, click "Create" to add a new contact.
    7.  **Fill** in the contact's details (e.g., Name: "John Seller", Role: "Seller") and save.
    8.  **Verify** the new contact "John Seller" appears correctly in the Contacts subpanel on the deal's detail view.
    9.  From the **Documents subpanel**, click "Create" to upload a document.
    10. **Upload** a file (e.g., `NDA.pdf`) and provide a document name.
    11. **Verify** the new document appears correctly in the Documents subpanel.
*   **Expected Result**: The new deal is created successfully, and the newly created contact and document are listed in their respective subpanels on the deal's detail view, confirming the relationships were saved correctly.

---

### Feature 2: Unified Deal & Portfolio Pipeline

*   **Objective**: Ensure the visual pipeline is fully functional, allowing a user to manage the deal lifecycle via drag-and-drop, with all changes persisting correctly and being reflected across the system.
*   **User Story**: As a busy investor, I want to see all my deals on a Kanban board, move a deal from "Screening" to "Analysis & Outreach," and be confident that the deal's stage is updated everywhere in the system.

#### Test Case 2.1: E2E Pipeline Stage Transition via Drag-and-Drop

*   **Scenario**: A user moves a deal from one pipeline stage to another using the drag-and-drop interface. The test verifies the change is reflected in the UI, the database, and the deal's audit log.
*   **Pre-conditions**:
    *   User is logged in.
    *   A deal named "E2E Test Deal" exists and is in the "Screening" stage.
*   **Test Steps**:
    1.  **Navigate** to the Deals module to open the pipeline view.
    2.  **Locate** the "E2E Test Deal" card in the "Screening" column.
    3.  **Drag** the deal card from the "Screening" column.
    4.  **Drop** the deal card into the "Analysis & Outreach" column.
    5.  **Verify** the UI updates immediately, showing the deal card in the "Analysis & Outreach" column.
    6.  **Refresh** the browser page.
    7.  **Verify** the "E2E Test Deal" card remains in the "Analysis & Outreach" column, confirming persistence.
    8.  **Click** on the deal card to navigate to its detail view.
    9.  **Verify** the "Stage" field on the detail view shows "Analysis & Outreach".
    10. **Navigate** to the deal's "View Change Log" and verify an entry exists for the stage change, including the old value, new value, and user who made the change.
*   **Expected Result**: The deal's stage is successfully updated via drag-and-drop, the change is persistent, and the modification is properly audited.

---

### Feature 3: Personal Due-Diligence Checklists

*   **Objective**: Validate that a user can create a checklist template, apply it to a deal, and see that the corresponding tasks are automatically generated and that progress is tracked correctly.
*   **User Story**: As a solo buyer, I want to apply my "Full Financial Due Diligence" checklist template to a new deal, see the required tasks automatically created, and track their completion status on the deal record.

#### Test Case 3.1: E2E Checklist Application and Task Generation

*   **Scenario**: A user creates a new checklist template, applies it to a deal, and verifies that the associated tasks are created and that progress tracking works.
*   **Pre-conditions**:
    *   User is logged in.
    *   A deal named "E2E Diligence Deal" exists.
*   **Test Steps**:
    1.  **Navigate** to the "Checklist Templates" module.
    2.  **Create** a new template named "E2E Financial Checklist" with at least two checklist items (e.g., "Review P&L", "Verify Bank Statements").
    3.  **Save** the template.
    4.  **Navigate** to the "E2E Diligence Deal" record.
    5.  From the detail view, use the **"Apply Checklist Template"** action and select "E2E Financial Checklist".
    6.  **Verify** a new checklist instance appears in the Checklists subpanel.
    7.  **Navigate** to the **Tasks subpanel** for the deal.
    8.  **Verify** that two new tasks, "Review P&L" and "Verify Bank Statements", have been automatically created and are linked to the deal.
    9.  **Click** on the "Review P&L" task and mark it as "Completed".
    10. **Return** to the deal's detail view.
    11. **Verify** the checklist progress indicator (e.g., a progress bar or text) has updated to show **50% completion**.
*   **Expected Result**: A checklist template can be created and applied to a deal, which correctly triggers the automatic creation of tasks. Task completion updates the overall progress of the checklist on the deal.

---

### Feature 4: Simplified Stakeholder Tracking

*   **Objective**: Ensure a user can efficiently manage stakeholders by assigning specific roles to contacts within the context of a deal and easily access this information.
*   **User Story**: As the sole point of contact, I want to add a new contact as the "Lender" for a deal and then quickly view their role and contact details directly from the deal's page.

#### Test Case 4.1: E2E Stakeholder Role Assignment and Verification

*   **Scenario**: A user assigns a specific role to a new contact associated with a deal. The test verifies the role is saved and displayed correctly.
*   **Pre-conditions**:
    *   User is logged in.
    *   A deal named "E2E Stakeholder Deal" exists.
*   **Test Steps**:
    1.  **Navigate** to the "E2E Stakeholder Deal" record.
    2.  From the **Contacts subpanel**, click "Create" to add a new contact.
    3.  **Fill** in the contact's name: "Jane Lender".
    4.  **Locate** the **"Contact Role"** field and select **"Lender"** from the dropdown.
    5.  **Save** the new contact.
    6.  **Verify** that "Jane Lender" appears in the Contacts subpanel on the deal's detail view.
    7.  **Verify** that the subpanel's "Role" column for Jane's record correctly displays "Lender".
    8.  **Click** on "Jane Lender" to navigate to her contact detail view.
    9.  **Verify** the "Contact Role" field on her detail page is correctly set to "Lender".
*   **Expected Result**: A contact can be created from a deal with a specific role, and that role is displayed correctly on both the deal's subpanel and the contact's detail view.

---

### Feature 5: At-a-Glance Financial & Valuation Hub

*   **Objective**: Validate that the financial hub correctly displays key metrics and that the "What-if" calculator allows for real-time valuation analysis that persists upon saving.
*   **User Story**: As an investor, I want to open the financial hub for a deal, see its TTM EBITDA and asking price, and then use the what-if calculator to see how a change in the multiple affects the proposed valuation.

#### Test Case 5.1: E2E Financial Hub and What-If Calculation

*   **Scenario**: A user modifies a deal's valuation multiple using the "What-if" calculator and verifies that the new valuation is calculated correctly and saved to the deal record.
*   **Pre-conditions**:
    *   User is logged in.
    *   A deal named "E2E Financial Deal" exists with the following data:
        *   TTM EBITDA: $1,000,000
        *   Multiple: 4
        *   Proposed Valuation: $4,000,000
*   **Test Steps**:
    1.  **Navigate** to the "E2E Financial Deal" record.
    2.  **Open** the **Financial Hub** (or dashboard widget).
    3.  **Verify** the widget correctly displays the initial "Proposed Valuation" of **$4,000,000**.
    4.  **Click** the button to open the **"What-if Calculator"**.
    5.  In the calculator, **change** the "Multiple" field from `4` to `5`.
    6.  **Verify** the "Proposed Valuation" field within the calculator instantly and automatically updates to **$5,000,000**.
    7.  **Click** the "Save" or "Apply" button in the calculator.
    8.  **Verify** the main financial widget on the hub now displays the updated "Proposed Valuation" of **$5,000,000**.
    9.  **Refresh** the entire page and navigate back to the deal's Financial Hub.
    10. **Verify** the "Proposed Valuation" remains **$5,000,000**, confirming the change was saved correctly.
*   **Expected Result**: The "What-if" calculator provides real-time feedback, and the changes made can be successfully saved, updating the deal's financial data persistently.
