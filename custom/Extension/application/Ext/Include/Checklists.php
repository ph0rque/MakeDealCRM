<?php
/**
 * Checklist System Registration for SuiteCRM
 * Registers the Checklist modules with the application
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Register the Checklist Templates module
$beanList['ChecklistTemplates'] = 'ChecklistTemplate';
$beanFiles['ChecklistTemplate'] = 'custom/modules/ChecklistTemplates/ChecklistTemplate.php';

// Register the Checklist Items module
$beanList['ChecklistItems'] = 'ChecklistItem';
$beanFiles['ChecklistItem'] = 'custom/modules/ChecklistItems/ChecklistItem.php';

// Register the Deal Checklists relationship module
$beanList['DealChecklists'] = 'DealChecklist';
$beanFiles['DealChecklist'] = 'custom/modules/DealChecklists/DealChecklist.php';

// Add to the module registry
$moduleList[] = 'ChecklistTemplates';
$moduleList[] = 'ChecklistItems';
$moduleList[] = 'DealChecklists';

// Add module tabs
$modInvisList[] = 'ChecklistItems';  // Hide ChecklistItems from main menu
$modInvisList[] = 'DealChecklists';   // Hide DealChecklists from main menu