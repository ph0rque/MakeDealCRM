<?php
/**
 * ChecklistItem Bean Class
 * 
 * @package MakeDealCRM
 * @subpackage ChecklistItems
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class ChecklistItem extends Basic
{
    public $module_dir = 'ChecklistItems';
    public $object_name = 'ChecklistItem';
    public $table_name = 'checklist_items';
    public $importable = true;
    public $disable_row_level_security = false;
    
    public $id;
    public $name;
    public $date_entered;
    public $date_modified;
    public $modified_user_id;
    public $created_by;
    public $description;
    public $deleted;
    
    // Custom fields
    public $template_id;
    public $checklist_id;
    public $title;
    public $type;
    public $order_number;
    public $is_required;
    public $due_days;
    public $due_date;
    public $status;
    public $completed_date;
    public $completed_by;
    public $notes;
    public $task_id;
    public $file_request_id;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Get item types
     */
    public static function getItemTypes()
    {
        return array(
            'checkbox' => 'Checkbox',
            'text' => 'Text Input',
            'number' => 'Number',
            'date' => 'Date',
            'file' => 'File Upload',
            'select' => 'Dropdown',
            'textarea' => 'Text Area'
        );
    }
    
    /**
     * Get item statuses
     */
    public static function getStatuses()
    {
        return array(
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'not_applicable' => 'Not Applicable',
            'blocked' => 'Blocked'
        );
    }
    
    /**
     * Mark item as complete
     * @deprecated Use ChecklistService->updateChecklistItem() instead
     */
    public function markComplete($notes = '')
    {
        // This method is deprecated - use ChecklistService instead
        require_once('custom/modules/Deals/services/ChecklistService.php');
        $checklistService = new ChecklistService();
        
        $result = $checklistService->updateChecklistItem($this->id, 'completed', array(
            'notes' => $notes
        ));
        
        if (!$result['success']) {
            throw new Exception($result['message']);
        }
        
        // Refresh the bean with updated data
        $this->retrieve($this->id);
    }
    
    /**
     * Update checklist progress
     */
    private function updateChecklistProgress()
    {
        if (empty($this->checklist_id)) {
            return;
        }
        
        global $db;
        
        // Get total and completed items
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                FROM checklist_items 
                WHERE checklist_id = '{$this->checklist_id}' 
                AND deleted = 0";
        
        $result = $db->query($sql);
        if ($row = $db->fetchByAssoc($result)) {
            $progress = $row['total'] > 0 ? round(($row['completed'] / $row['total']) * 100) : 0;
            
            // Update checklist progress
            $sql = "UPDATE deal_checklists 
                    SET progress = {$progress},
                        date_modified = NOW()
                    WHERE id = '{$this->checklist_id}'";
            $db->query($sql);
            
            // Check if all items are complete
            if ($progress == 100) {
                $sql = "UPDATE deal_checklists 
                        SET status = 'completed',
                            date_completed = NOW()
                        WHERE id = '{$this->checklist_id}'";
                $db->query($sql);
            }
        }
    }
    
    /**
     * Check if item is overdue
     */
    public function isOverdue()
    {
        if ($this->status == 'completed' || empty($this->due_date)) {
            return false;
        }
        
        $dueDate = new DateTime($this->due_date);
        $today = new DateTime();
        
        return $today > $dueDate;
    }
    
    /**
     * Get days until due
     */
    public function getDaysUntilDue()
    {
        if (empty($this->due_date)) {
            return null;
        }
        
        $dueDate = new DateTime($this->due_date);
        $today = new DateTime();
        $interval = $today->diff($dueDate);
        
        return $interval->invert ? -$interval->days : $interval->days;
    }
    
    /**
     * Create file request for this item
     */
    public function createFileRequest($recipientEmail, $message = '')
    {
        global $sugar_config, $current_user;
        
        // Generate secure token
        $token = md5(uniqid($this->id, true));
        
        // Create file request record
        $fileRequest = new FileRequest();
        $fileRequest->checklist_item_id = $this->id;
        $fileRequest->recipient_email = $recipientEmail;
        $fileRequest->token = $token;
        $fileRequest->status = 'pending';
        $fileRequest->message = $message;
        $fileRequest->expires_date = date('Y-m-d', strtotime('+30 days'));
        $fileRequest->save();
        
        // Generate upload URL
        $uploadUrl = $sugar_config['site_url'] . '/index.php?entryPoint=uploadChecklistFile&token=' . $token;
        
        // Send email
        $this->sendFileRequestEmail($recipientEmail, $uploadUrl, $message);
        
        // Update item
        $this->file_request_id = $fileRequest->id;
        $this->save();
        
        return $fileRequest;
    }
    
    /**
     * Send file request email
     */
    private function sendFileRequestEmail($recipientEmail, $uploadUrl, $message)
    {
        require_once('include/SugarPHPMailer.php');
        
        global $sugar_config, $current_user;
        
        $mail = new SugarPHPMailer();
        $mail->setMailerForSystem();
        $mail->From = $sugar_config['notify_fromaddress'];
        $mail->FromName = $sugar_config['notify_fromname'];
        $mail->Subject = "Document Request: {$this->title}";
        
        // Build email body
        $body = "Hello,<br><br>";
        $body .= "You have been requested to upload a document for the following item:<br><br>";
        $body .= "<strong>{$this->title}</strong><br>";
        if (!empty($this->description)) {
            $body .= "{$this->description}<br>";
        }
        if (!empty($message)) {
            $body .= "<br>Additional message:<br>{$message}<br>";
        }
        $body .= "<br>Please click the link below to upload your document:<br>";
        $body .= "<a href='{$uploadUrl}'>Upload Document</a><br><br>";
        $body .= "This link will expire in 30 days.<br><br>";
        $body .= "Thank you,<br>{$current_user->full_name}";
        
        $mail->Body = $body;
        $mail->isHTML(true);
        $mail->AddAddress($recipientEmail);
        
        return $mail->Send();
    }
}