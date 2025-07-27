<?php
/**
 * API Registration File
 * 
 * This file registers the Pipeline API endpoints with SuiteCRM's REST framework
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

// Register the API classes
require_once 'custom/modules/Deals/api/PipelineApi.php';
require_once 'custom/modules/Deals/api/TemplateApi.php';
require_once 'custom/modules/Deals/api/FileRequestApi.php';
require_once 'custom/modules/Deals/api/StakeholderIntegrationApi.php';

// The APIs will be automatically discovered by SuiteCRM's API framework
// when placed in the custom/modules/Deals/api/ directory