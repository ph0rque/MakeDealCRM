<?php
/**
 * Bulk Stakeholder Management View
 * 
 * Provides interface for managing stakeholders across multiple deals
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/views/view.detail.php');

class DealsViewStakeholder_bulk extends SugarView
{
    public function display()
    {
        global $current_user, $mod_strings;
        
        // Check permissions
        if (!$current_user->id) {
            sugar_die('Unauthorized access');
        }
        
        // Add CSS and JavaScript
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/stakeholder-badges.css">';
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/stakeholder-bulk.css">';
        echo '<script type="text/javascript" src="custom/modules/Deals/js/stakeholder-bulk-manager.js"></script>';
        
        // Get selected deals if provided
        $dealIds = isset($_REQUEST['deal_ids']) ? explode(',', $_REQUEST['deal_ids']) : [];
        
        // Display the interface
        $this->displayBulkInterface($dealIds);
    }
    
    private function displayBulkInterface($dealIds)
    {
        global $db;
        ?>
        <div class="stakeholder-bulk-container">
            <h2>Bulk Stakeholder Management</h2>
            
            <!-- Deal Selection -->
            <div class="section deal-selection">
                <h3>Select Deals</h3>
                <div class="deal-filter">
                    <input type="text" id="dealSearch" placeholder="Search deals..." class="form-control">
                    <button class="btn btn-primary" onclick="StakeholderBulkManager.searchDeals()">Search</button>
                    <button class="btn btn-default" onclick="StakeholderBulkManager.selectAllDeals()">Select All</button>
                    <button class="btn btn-default" onclick="StakeholderBulkManager.clearSelection()">Clear Selection</button>
                </div>
                
                <div id="dealList" class="deal-list">
                    <?php $this->displayDealList($dealIds); ?>
                </div>
                
                <div class="selected-count">
                    <span id="selectedCount">0</span> deals selected
                </div>
            </div>
            
            <!-- Stakeholder Actions -->
            <div class="section stakeholder-actions">
                <h3>Stakeholder Actions</h3>
                
                <div class="action-tabs">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#add-stakeholders" data-toggle="tab">Add Stakeholders</a></li>
                        <li><a href="#manage-roles" data-toggle="tab">Manage Roles</a></li>
                        <li><a href="#remove-stakeholders" data-toggle="tab">Remove Stakeholders</a></li>
                        <li><a href="#import-export" data-toggle="tab">Import/Export</a></li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- Add Stakeholders Tab -->
                        <div class="tab-pane active" id="add-stakeholders">
                            <div class="form-horizontal">
                                <div class="form-group">
                                    <label class="col-sm-2 control-label">Contact Search</label>
                                    <div class="col-sm-6">
                                        <input type="text" id="contactSearchBulk" class="form-control" placeholder="Search contacts to add...">
                                        <div id="contactSearchResults"></div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="col-sm-2 control-label">Role</label>
                                    <div class="col-sm-4">
                                        <select id="bulkRole" class="form-control">
                                            <option value="Decision Maker">Decision Maker</option>
                                            <option value="Executive Champion">Executive Champion</option>
                                            <option value="Technical Evaluator">Technical Evaluator</option>
                                            <option value="Business User">Business User</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="col-sm-2 control-label">Selected Contacts</label>
                                    <div class="col-sm-8">
                                        <div id="selectedContacts" class="selected-contacts"></div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="col-sm-offset-2 col-sm-10">
                                        <button class="btn btn-primary" onclick="StakeholderBulkManager.addStakeholdersToDeals()">
                                            Add to Selected Deals
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Manage Roles Tab -->
                        <div class="tab-pane" id="manage-roles">
                            <div class="role-management">
                                <p>Select stakeholders to update their roles across selected deals.</p>
                                <div id="existingStakeholders" class="existing-stakeholders">
                                    <!-- Populated dynamically -->
                                </div>
                                
                                <div class="form-group">
                                    <label>New Role:</label>
                                    <select id="newRole" class="form-control" style="width: 200px; display: inline-block;">
                                        <option value="">-- Select Role --</option>
                                        <option value="Decision Maker">Decision Maker</option>
                                        <option value="Executive Champion">Executive Champion</option>
                                        <option value="Technical Evaluator">Technical Evaluator</option>
                                        <option value="Business User">Business User</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <button class="btn btn-warning" onclick="StakeholderBulkManager.updateSelectedRoles()">
                                        Update Roles
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Remove Stakeholders Tab -->
                        <div class="tab-pane" id="remove-stakeholders">
                            <div class="remove-management">
                                <p>Select stakeholders to remove from selected deals.</p>
                                <div id="removableStakeholders" class="removable-stakeholders">
                                    <!-- Populated dynamically -->
                                </div>
                                
                                <button class="btn btn-danger" onclick="StakeholderBulkManager.removeSelectedStakeholders()">
                                    Remove Selected Stakeholders
                                </button>
                            </div>
                        </div>
                        
                        <!-- Import/Export Tab -->
                        <div class="tab-pane" id="import-export">
                            <div class="import-export-section">
                                <h4>Export Stakeholders</h4>
                                <p>Export stakeholder data for selected deals to CSV.</p>
                                <button class="btn btn-info" onclick="StakeholderBulkManager.exportStakeholders()">
                                    <i class="glyphicon glyphicon-download"></i> Export to CSV
                                </button>
                                
                                <hr>
                                
                                <h4>Import Stakeholders</h4>
                                <p>Import stakeholder assignments from CSV file.</p>
                                <input type="file" id="importFile" accept=".csv">
                                <button class="btn btn-success" onclick="StakeholderBulkManager.importStakeholders()">
                                    <i class="glyphicon glyphicon-upload"></i> Import from CSV
                                </button>
                                
                                <div class="import-template">
                                    <p><small>CSV Format: Deal Name, Contact Email, Role</small></p>
                                    <a href="#" onclick="StakeholderBulkManager.downloadTemplate()">Download Template</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Results/Status -->
            <div class="section results-section">
                <h3>Operation Results</h3>
                <div id="operationResults" class="operation-results"></div>
            </div>
        </div>
        
        <script>
            $(document).ready(function() {
                StakeholderBulkManager.init(<?php echo json_encode($dealIds); ?>);
            });
        </script>
        <?php
    }
    
    private function displayDealList($selectedIds = [])
    {
        global $db;
        
        // Get active deals
        $query = "SELECT 
                    d.id,
                    d.name,
                    d.amount,
                    c.pipeline_stage_c,
                    a.name as account_name,
                    COUNT(DISTINCT oc.contact_id) as stakeholder_count
                FROM opportunities d
                LEFT JOIN opportunities_cstm c ON d.id = c.id_c
                LEFT JOIN accounts a ON d.account_id = a.id
                LEFT JOIN opportunities_contacts oc ON d.id = oc.opportunity_id AND oc.deleted = 0
                WHERE d.deleted = 0
                AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                GROUP BY d.id
                ORDER BY d.name";
        
        $result = $db->query($query);
        
        echo '<table class="table table-striped deal-selection-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th><input type="checkbox" id="selectAllDeals"></th>';
        echo '<th>Deal Name</th>';
        echo '<th>Account</th>';
        echo '<th>Stage</th>';
        echo '<th>Amount</th>';
        echo '<th>Stakeholders</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        while ($row = $db->fetchByAssoc($result)) {
            $checked = in_array($row['id'], $selectedIds) ? 'checked' : '';
            echo '<tr>';
            echo '<td><input type="checkbox" class="deal-checkbox" value="' . $row['id'] . '" ' . $checked . '></td>';
            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['account_name'] ?: '-') . '</td>';
            echo '<td>' . htmlspecialchars($row['pipeline_stage_c'] ?: '-') . '</td>';
            echo '<td>$' . number_format($row['amount'] ?: 0) . '</td>';
            echo '<td><span class="badge">' . $row['stakeholder_count'] . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
}