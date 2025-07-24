<?php
/**
 * Register Pipelines Module
 */

// Add Pipelines to module list
$moduleList[] = 'Pipelines';

// Add to beanList (even though it doesn't have a bean)
$beanList['Pipelines'] = 'Pipelines';
$beanFiles['Pipelines'] = 'custom/modules/Pipelines/Pipeline.php';

// Module name translations
$app_list_strings['moduleList']['Pipelines'] = 'Pipeline';
$app_list_strings['moduleListSingular']['Pipelines'] = 'Pipeline';