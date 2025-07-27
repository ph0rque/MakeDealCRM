<?php
/**
 * DealChecklist Bean Class
 * 
 * @package MakeDealCRM
 * @subpackage DealChecklists
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class DealChecklist extends Basic
{
    public $module_dir = 'DealChecklists';
    public $object_name = 'DealChecklist';
    public $table_name = 'deal_checklists';
    public $importable = false;
    public $disable_row_level_security = false;
    
    public $id;
    public $name;
    public $date_entered;
    public $date_modified;
    public $modified_user_id;
    public $created_by;
    public $deleted;
    public $assigned_user_id;
    
    // Custom fields
    public $deal_id;
    public $template_id;
    public $status;
    public $progress;
    public $date_started;
    public $date_completed;
    public $total_items;
    public $completed_items;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Get checklist statuses
     */
    public static function getStatuses()
    {
        return array(
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'cancelled' => 'Cancelled'
        );
    }
    
    /**
     * Calculate and update progress
     * This method is now called by ChecklistService internally
     */
    public function updateProgress()
    {
        global $db;
        
        // Get item counts
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN is_required = 1 THEN 1 END) as required,
                    COUNT(CASE WHEN is_required = 1 AND status = 'completed' THEN 1 END) as required_completed
                FROM checklist_items 
                WHERE checklist_id = '{$this->id}' 
                AND deleted = 0";
        
        $result = $db->query($sql);
        if ($row = $db->fetchByAssoc($result)) {
            $this->total_items = $row['total'];
            $this->completed_items = $row['completed'];
            
            // Calculate progress percentage
            if ($row['total'] > 0) {
                $this->progress = round(($row['completed'] / $row['total']) * 100);
            } else {
                $this->progress = 0;
            }
            
            // Update status
            if ($this->progress == 0 && $this->status != 'on_hold' && $this->status != 'cancelled') {
                $this->status = 'not_started';
            } elseif ($this->progress > 0 && $this->progress < 100) {
                $this->status = 'in_progress';
            } elseif ($this->progress == 100) {
                $this->status = 'completed';
                if (empty($this->date_completed)) {
                    $this->date_completed = date('Y-m-d H:i:s');
                }
            }
            
            $this->save();
        }
    }
    
    /**
     * Get checklist items
     */
    public function getItems($includeDeleted = false)
    {
        global $db;
        
        $deletedClause = $includeDeleted ? '' : 'AND deleted = 0';
        
        $sql = "SELECT * FROM checklist_items 
                WHERE checklist_id = '{$this->id}' 
                {$deletedClause}
                ORDER BY order_number ASC, date_entered ASC";
        
        $result = $db->query($sql);
        $items = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $item = BeanFactory::newBean('ChecklistItems');
            foreach ($row as $field => $value) {
                if (property_exists($item, $field)) {
                    $item->$field = $value;
                }
            }
            $items[] = $item;
        }
        
        return $items;
    }
    
    /**
     * Get overdue items
     */
    public function getOverdueItems()
    {
        global $db;
        
        $today = date('Y-m-d');
        
        $sql = "SELECT * FROM checklist_items 
                WHERE checklist_id = '{$this->id}' 
                AND deleted = 0
                AND status NOT IN ('completed', 'not_applicable')
                AND due_date < '{$today}'
                AND due_date IS NOT NULL
                ORDER BY due_date ASC";
        
        $result = $db->query($sql);
        $items = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $item = BeanFactory::newBean('ChecklistItems');
            foreach ($row as $field => $value) {
                if (property_exists($item, $field)) {
                    $item->$field = $value;
                }
            }
            $items[] = $item;
        }
        
        return $items;
    }
    
    /**
     * Export checklist to PDF
     */
    public function exportToPDF()
    {
        require_once('modules/AOS_PDF_Templates/PDF_Lib/mpdf.php');
        
        $pdf = new mPDF('en', 'A4', '', 'DejaVuSansCondensed');
        
        // Get deal information
        $deal = BeanFactory::getBean('Deals', $this->deal_id);
        
        // Build PDF content
        $html = '<h1>' . $this->name . '</h1>';
        $html .= '<p><strong>Deal:</strong> ' . $deal->name . '</p>';
        $html .= '<p><strong>Progress:</strong> ' . $this->progress . '%</p>';
        $html .= '<p><strong>Status:</strong> ' . $this->status . '</p>';
        $html .= '<hr>';
        
        // Get items
        $items = $this->getItems();
        
        $html .= '<h2>Checklist Items</h2>';
        $html .= '<table width="100%" border="1" cellpadding="5">';
        $html .= '<tr>
                    <th width="5%">#</th>
                    <th width="35%">Item</th>
                    <th width="25%">Description</th>
                    <th width="10%">Status</th>
                    <th width="10%">Due Date</th>
                    <th width="15%">Completed By</th>
                  </tr>';
        
        foreach ($items as $index => $item) {
            $statusIcon = $item->status == 'completed' ? '✓' : '○';
            $dueDate = !empty($item->due_date) ? date('m/d/Y', strtotime($item->due_date)) : '-';
            $completedBy = '-';
            
            if ($item->status == 'completed' && !empty($item->completed_by)) {
                $user = BeanFactory::getBean('Users', $item->completed_by);
                $completedBy = $user->full_name;
            }
            
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $statusIcon . ' ' . $item->title . 
                     ($item->is_required ? ' <strong>*</strong>' : '') . '</td>';
            $html .= '<td>' . $item->description . '</td>';
            $html .= '<td>' . $item->status . '</td>';
            $html .= '<td>' . $dueDate . '</td>';
            $html .= '<td>' . $completedBy . '</td>';
            $html .= '</tr>';
            
            if (!empty($item->notes)) {
                $html .= '<tr><td colspan="6"><em>Notes: ' . $item->notes . '</em></td></tr>';
            }
        }
        
        $html .= '</table>';
        $html .= '<p><em>* Required items</em></p>';
        
        $pdf->WriteHTML($html);
        
        // Output PDF
        $filename = 'Checklist_' . $deal->name . '_' . date('Y-m-d') . '.pdf';
        $pdf->Output($filename, 'D');
    }
    
    /**
     * Export checklist to Excel
     */
    public function exportToExcel()
    {
        require_once('include/export_utils.php');
        
        // Get deal information
        $deal = BeanFactory::getBean('Deals', $this->deal_id);
        
        // Build CSV content
        $content = "Checklist Export\n";
        $content .= "Deal: {$deal->name}\n";
        $content .= "Progress: {$this->progress}%\n";
        $content .= "Status: {$this->status}\n";
        $content .= "Export Date: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Headers
        $content .= "#,Item,Description,Type,Required,Status,Due Date,Completed Date,Completed By,Notes\n";
        
        // Get items
        $items = $this->getItems();
        
        foreach ($items as $index => $item) {
            $completedBy = '';
            if ($item->status == 'completed' && !empty($item->completed_by)) {
                $user = BeanFactory::getBean('Users', $item->completed_by);
                $completedBy = $user->full_name;
            }
            
            $row = array(
                $index + 1,
                $item->title,
                $item->description,
                $item->type,
                $item->is_required ? 'Yes' : 'No',
                $item->status,
                !empty($item->due_date) ? $item->due_date : '',
                !empty($item->completed_date) ? $item->completed_date : '',
                $completedBy,
                $item->notes
            );
            
            $content .= '"' . implode('","', $row) . '"' . "\n";
        }
        
        // Output CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="Checklist_' . $deal->name . '_' . date('Y-m-d') . '.csv"');
        echo $content;
        exit;
    }
}