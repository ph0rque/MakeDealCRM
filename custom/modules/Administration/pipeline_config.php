<?php
/**
 * Pipeline Configuration - Admin Panel
 * 
 * Manages pipeline stages, WIP limits, and settings
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

global $current_user, $db, $mod_strings, $app_strings;

// Check admin access
if (!is_admin($current_user)) {
    sugar_die($app_strings['ERR_NOT_ADMIN']);
}

// Handle form submission
if (!empty($_POST['save'])) {
    // Save WIP limits
    foreach ($_POST['wip_limit'] as $stage => $limit) {
        $stage = $db->quote($stage);
        $limit = empty($limit) ? 'NULL' : intval($limit);
        
        $query = "UPDATE pipeline_stages SET wip_limit = $limit WHERE stage_key = '$stage'";
        $db->query($query);
    }
    
    // Save stage colors
    foreach ($_POST['stage_color'] as $stage => $color) {
        $stage = $db->quote($stage);
        $color = $db->quote($color);
        
        $query = "UPDATE pipeline_stages SET color_code = '$color' WHERE stage_key = '$stage'";
        $db->query($query);
    }
    
    // Save global settings
    $stale_warning_days = intval($_POST['stale_warning_days'] ?? 14);
    $stale_alert_days = intval($_POST['stale_alert_days'] ?? 30);
    
    // Store in config_override.php
    require_once('modules/Configurator/Configurator.php');
    $configurator = new Configurator();
    $configurator->config['pipeline_stale_warning_days'] = $stale_warning_days;
    $configurator->config['pipeline_stale_alert_days'] = $stale_alert_days;
    $configurator->saveConfig();
    
    SugarApplication::appendSuccessMessage('Pipeline configuration saved successfully.');
}

// Load current configuration
$stages = array();
$query = "SELECT * FROM pipeline_stages WHERE deleted = 0 ORDER BY stage_order";
$result = $db->query($query);

while ($row = $db->fetchByAssoc($result)) {
    $stages[] = $row;
}

// Display form
?>
<h2>Pipeline Configuration</h2>

<form method="POST" action="index.php?module=Administration&action=pipeline_config">
    <input type="hidden" name="save" value="1">
    
    <h3>Pipeline Stages</h3>
    <table class="list view" width="100%">
        <thead>
            <tr>
                <th>Stage</th>
                <th>Order</th>
                <th>WIP Limit</th>
                <th>Color</th>
                <th>Active</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stages as $stage): ?>
            <tr>
                <td><?php echo htmlspecialchars($stage['name']); ?></td>
                <td><?php echo $stage['stage_order']; ?></td>
                <td>
                    <input type="number" 
                           name="wip_limit[<?php echo $stage['stage_key']; ?>]" 
                           value="<?php echo $stage['wip_limit']; ?>"
                           min="0"
                           placeholder="No limit">
                </td>
                <td>
                    <input type="color" 
                           name="stage_color[<?php echo $stage['stage_key']; ?>]" 
                           value="<?php echo $stage['color_code']; ?>">
                </td>
                <td>
                    <input type="checkbox" 
                           name="stage_active[<?php echo $stage['stage_key']; ?>]" 
                           <?php echo $stage['is_active'] ? 'checked' : ''; ?>>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h3>Time-Based Indicators</h3>
    <table>
        <tr>
            <td>Warning threshold (days):</td>
            <td>
                <input type="number" 
                       name="stale_warning_days" 
                       value="<?php echo $sugar_config['pipeline_stale_warning_days'] ?? 14; ?>"
                       min="1">
            </td>
        </tr>
        <tr>
            <td>Alert threshold (days):</td>
            <td>
                <input type="number" 
                       name="stale_alert_days" 
                       value="<?php echo $sugar_config['pipeline_stale_alert_days'] ?? 30; ?>"
                       min="1">
            </td>
        </tr>
    </table>
    
    <div class="buttons">
        <input type="submit" class="button primary" value="Save Configuration">
        <input type="button" class="button" value="Cancel" onclick="window.location.href='index.php?module=Administration&action=index'">
    </div>
</form>

<style>
.list.view th {
    background-color: #f0f0f0;
    padding: 8px;
    text-align: left;
}
.list.view td {
    padding: 8px;
}
input[type="number"] {
    width: 80px;
}
.buttons {
    margin-top: 20px;
}
</style>