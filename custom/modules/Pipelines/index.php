<?php
/**
 * Pipelines Module Entry Point
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

// Redirect to Kanban view by default
header('Location: index.php?module=Pipelines&action=kanbanview');