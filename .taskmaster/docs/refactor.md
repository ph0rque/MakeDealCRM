# Refactoring and Improvement Plan

This document outlines a plan for refactoring the MakeDealCRM application to improve its quality, maintainability, and test coverage.

## 1. E2E Test Suite Consolidation and Expansion

**Problem:** The current E2E tests are fragmented across multiple files and do not provide comprehensive coverage of the features defined in the PRD.

**Proposed Solution:**
*   **Consolidate:** Merge all Playwright test scripts into a single, unified test suite located at `tests/e2e/`.
*   **Expand:** Implement new E2E tests to cover the following features, as detailed in `.taskmaster/docs/e2e-test-prd.md`:
    *   Personal Due-Diligence Checklists
    *   Simplified Stakeholder Tracking
    *   At-a-Glance Financial & Valuation Hub
    *   Email-to-Deal creation and updates.
*   **Structure:** Organize the test suite to mirror the PRD, with a separate test file for each major feature.

## 2. Module Refactoring and Naming Conventions

**Problem:** The `Deals` module is a monolith with many responsibilities, and the naming convention of `mdeal_Deals` is confusing.

**Proposed Solution:**
*   **Delete `mdeal_Deals`:**
*   **Refactor `Deals` Module:** Break down the `Deals` module into smaller, more focused components.
    *   Extract email processing logic into a dedicated `EmailProcessorService` class.
    *   Create a `ChecklistService` to handle checklist-related operations.
    *   Isolate financial calculation logic into a `FinancialCalculator` class.

## 3. Test Data Management

**Problem:** The tests rely on inconsistent and manually created data, which makes them brittle.

**Proposed Solution:**
*   **Implement Fixtures:** Create a set of test data fixtures for deals, contacts, and other entities.
*   **Data Factory:** Develop a data factory to generate consistent and realistic test data on demand.
*   **Seed Script:** Create a seed script to populate the database with a known set of data for testing purposes.

## 4. AWS Deployment Wizard

**Problem:** The "One-Click AWS Deployment" feature is implemented as a set of scripts, but the user-friendly wizard mentioned in the PRD is missing.

**Proposed Solution:**
*   **Develop a CLI Wizard:** Create an interactive CLI wizard using a library like `inquirer.js` or Python's `click` to guide users through the deployment process.
*   **Integrate Scripts:** The wizard should orchestrate the execution of the existing deployment scripts (`deploy.sh`, `migrate-database.sh`, etc.).
*   **Cost Estimation:** Integrate the `cost-estimator.py` script into the wizard to provide users with upfront cost estimates.

## 5. Code Cleanup and Documentation

**Problem:** There are several unused or legacy test files in the root directory.

**Proposed Solution:**
*   **Remove Legacy Files:** Delete the following files from the root directory:
    *   `test_deal_navigation.js`
    *   `test_drag_drop_working.js`
    *   `test_dragdrop_fix.js`
*   **Improve Documentation:** Add inline documentation to the `Deals` module's logic hooks to explain their purpose and interactions.
