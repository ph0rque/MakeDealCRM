<?php
/**
 * Extension to set Pipeline as default view for Deals module
 * This ensures all standard actions redirect to pipeline view
 */

// Map all common list/index actions to pipeline view
$action_view_map['index'] = 'pipeline';
$action_view_map['listview'] = 'pipeline';
$action_view_map['ListView'] = 'pipeline';
$action_view_map['list'] = 'pipeline';