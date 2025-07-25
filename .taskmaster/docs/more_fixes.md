# PRD: Codebase Cleanup and Feature Realignment

**Objective:** Address critical disconnects between the codebase, documentation, and feature requirements to improve maintainability, fix broken functionality, and clarify the project's true status.

## 1. Code Structure Consolidation

*   **Problem:** The project's code is split between the standard `SuiteCRM/modules/` and `custom/modules/` directories and a confusing, non-standard top-level `/modules/` directory. This top-level directory contains an empty `Pipelines` module and an orphaned `pipeline.php` file, creating significant confusion for developers.
*   **Recommendation:**
    *   Relocate all functional code from the top-level `/modules/` directory to its correct destination within the `SuiteCRM/` or `custom/` hierarchy.
    *   Remove the top-level `/modules/` directory entirely after its contents have been moved.
    *   Ensure all paths and references in the code are updated to reflect the new, consolidated structure.
*   **Benefit:** A clean, predictable, and maintainable code structure that aligns with SuiteCRM development conventions.

## 2. Fix Broken Pipeline UI

*   **Problem:** The Pipeline view (`custom/modules/mdeal_Deals/views/view.pipeline.php`) is critically broken. It attempts to load essential CSS and JavaScript files (`pipeline-kanban.css`, `PipelineKanbanView.js`) from a `custom/modules/Pipelines/views/` directory that does not exist.
*   **Recommendation:**
    *   Locate the missing `pipeline-kanban.css` and `PipelineKanbanView.js` files, which may be misplaced elsewhere in the project.
    *   If the files cannot be found, they must be recreated based on the logic in the `view.pipeline.php` file.
    *   Place the located or recreated files in the correct directory (`custom/modules/mdeal_Deals/views/` would be a logical choice) and update the `<link>` and `<script>` tags in `view.pipeline.php` to point to the correct location.
*   **Benefit:** A functional and visually correct Deal Pipeline, which is a core feature of the application.

## 3. Documentation and Feature Status Alignment

*   **Problem:** The main requirements document (`docs/business/features.md`) accurately describes the desired features but does not reflect their true implementation status. The "Unified Pipeline" is partially implemented but not marked as such, and there is no code evidence for "Due-Diligence Checklists," "Stakeholder Tracking," "Financial Hub," or "AWS Deployment."
*   **Recommendation:**
    *   Update the `features.md` document to accurately reflect the current state of each feature (e.g., "Completed," "In Progress," "Not Started").
    *   Add a "Known Issues" or "Technical Debt" section to the documentation to note the structural problems and the missing UI files.
*   **Benefit:** Clear, honest, and reliable documentation that gives stakeholders an accurate understanding of the project's progress and current challenges.
