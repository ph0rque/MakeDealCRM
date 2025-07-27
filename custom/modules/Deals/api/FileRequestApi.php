<?php
/**
 * File Request API for SuiteCRM Deals Module
 * 
 * Provides RESTful endpoints for file request operations including:
 * - Creating file requests with email automation
 * - Managing file uploads and storage
 * - Tracking request status and responses
 * - Email template generation for different request types
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/api/SugarApi.php';
require_once 'include/SugarQuery/SugarQuery.php';
require_once 'modules/ACL/ACLController.php';
require_once 'include/upload_file.php';
require_once 'custom/modules/Deals/services/EmailProcessorService.php';

class FileRequestApi extends SugarApi
{
    /**
     * Register API endpoints
     */
    public function registerApiRest()
    {
        return array(
            'createFileRequest' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'file-request', 'create'),
                'pathVars' => array('module', 'file-request', 'create'),
                'method' => 'createFileRequest',
                'shortHelp' => 'Create a file request with email automation',
                'longHelp' => 'Creates a file request, generates unique upload links, and sends automated emails',
            ),
            'getFileRequests' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'file-request', 'list'),
                'pathVars' => array('module', 'file-request', 'list'),
                'method' => 'getFileRequests',
                'shortHelp' => 'Get file requests for a deal',
                'longHelp' => 'Returns all file requests associated with a deal including status',
            ),
            'updateFileRequestStatus' => array(
                'reqType' => 'PUT',
                'path' => array('Deals', 'file-request', 'status'),
                'pathVars' => array('module', 'file-request', 'status'),
                'method' => 'updateFileRequestStatus',
                'shortHelp' => 'Update file request status',
                'longHelp' => 'Updates the status of a file request (pending, sent, received, completed)',
            ),
            'uploadRequestedFile' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'file-request', 'upload'),
                'pathVars' => array('module', 'file-request', 'upload'),
                'method' => 'uploadRequestedFile',
                'shortHelp' => 'Upload files for a file request',
                'longHelp' => 'Handles file uploads for specific file requests with validation',
            ),
            'sendFileRequestEmail' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'file-request', 'send-email'),
                'pathVars' => array('module', 'file-request', 'send-email'),
                'method' => 'sendFileRequestEmail',
                'shortHelp' => 'Send or resend file request email',
                'longHelp' => 'Sends automated email with file request details and upload links',
            ),
            'getFileRequestStatus' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'file-request', 'status', '?'),
                'pathVars' => array('module', 'file-request', 'status', 'request_id'),
                'method' => 'getFileRequestStatus',
                'shortHelp' => 'Get detailed status of a file request',
                'longHelp' => 'Returns detailed status and progress information for a specific file request',
            ),
        );
    }

    /**
     * Create a file request with email automation
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function createFileRequest($api, $args)
    {
        $this->requireArgs($args, array('module', 'deal_id', 'request_type', 'recipient_email'));
        
        // Check ACL
        if (!ACLController::checkAccess('Deals', 'edit', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to create file requests');
        }

        $dealId = $args['deal_id'];
        $requestType = $args['request_type'];
        $recipientEmail = $args['recipient_email'];
        $recipientName = $args['recipient_name'] ?? '';
        $description = $args['description'] ?? '';
        $files = $args['files'] ?? array(); // Array of requested file types
        $dueDate = $args['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $priority = $args['priority'] ?? 'medium';

        // Validate deal exists
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (empty($deal->id)) {
            throw new SugarApiExceptionNotFound('Deal not found');
        }

        // Check deal access
        if (!$deal->ACLAccess('view')) {
            throw new SugarApiExceptionNotAuthorized('No access to this Deal');
        }

        global $db, $current_user;

        // Create file request record
        $requestId = create_guid();
        $uploadToken = $this->generateUploadToken();
        
        $query = "INSERT INTO deal_file_requests 
                  (id, deal_id, request_type, recipient_email, recipient_name, description, 
                   requested_files, due_date, priority, status, upload_token, created_by, 
                   date_created, deleted) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), 0)";
        
        $db->pQuery($query, array(
            $requestId,
            $dealId,
            $requestType,
            $recipientEmail,
            $recipientName,
            $description,
            json_encode($files),
            $dueDate,
            $priority,
            $uploadToken,
            $current_user->id
        ));

        // Create file request entries for each requested file
        foreach ($files as $index => $fileSpec) {
            $fileRequestId = create_guid();
            $fileQuery = "INSERT INTO deal_file_request_items 
                          (id, file_request_id, file_type, file_description, is_required, status, deleted)
                          VALUES (?, ?, ?, ?, ?, 'pending', 0)";
            
            $db->pQuery($fileQuery, array(
                $fileRequestId,
                $requestId,
                $fileSpec['type'] ?? 'document',
                $fileSpec['description'] ?? '',
                $fileSpec['required'] ?? true
            ));
        }

        // Send email notification
        $emailResult = $this->sendFileRequestNotification($requestId, $deal);

        return array(
            'success' => true,
            'request_id' => $requestId,
            'upload_token' => $uploadToken,
            'upload_url' => $this->generateUploadUrl($uploadToken),
            'email_sent' => $emailResult['success'],
            'email_message' => $emailResult['message'] ?? '',
            'due_date' => $dueDate,
            'status' => 'pending'
        );
    }

    /**
     * Get file requests for a deal
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getFileRequests($api, $args)
    {
        $this->requireArgs($args, array('module'));
        
        // Check ACL
        if (!ACLController::checkAccess('Deals', 'list', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view file requests');
        }

        $dealId = $args['deal_id'] ?? null;
        $status = $args['status'] ?? null;
        $offset = (int)($args['offset'] ?? 0);
        $limit = (int)($args['limit'] ?? 20);

        global $db;

        // Build query conditions
        $conditions = array('fr.deleted = 0');
        $params = array();

        if ($dealId) {
            $conditions[] = 'fr.deal_id = ?';
            $params[] = $dealId;
        }

        if ($status) {
            $conditions[] = 'fr.status = ?';
            $params[] = $status;
        }

        $whereClause = implode(' AND ', $conditions);

        // Get file requests with deal information
        $query = "SELECT fr.*, 
                         d.name as deal_name,
                         u.user_name as created_by_name,
                         u.first_name as created_by_first_name,
                         u.last_name as created_by_last_name,
                         (SELECT COUNT(*) FROM deal_file_request_items fri 
                          WHERE fri.file_request_id = fr.id AND fri.deleted = 0) as total_files,
                         (SELECT COUNT(*) FROM deal_file_request_items fri 
                          WHERE fri.file_request_id = fr.id AND fri.status = 'received' AND fri.deleted = 0) as received_files
                  FROM deal_file_requests fr
                  LEFT JOIN deals d ON fr.deal_id = d.id AND d.deleted = 0
                  LEFT JOIN users u ON fr.created_by = u.id AND u.deleted = 0
                  WHERE $whereClause
                  ORDER BY fr.date_created DESC
                  LIMIT $limit OFFSET $offset";

        $result = $db->pQuery($query, $params);
        $requests = array();

        while ($row = $db->fetchByAssoc($result)) {
            $requests[] = $this->formatFileRequestData($row);
        }

        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM deal_file_requests fr WHERE $whereClause";
        $countResult = $db->pQuery($countQuery, $params);
        $countRow = $db->fetchByAssoc($countResult);

        return array(
            'success' => true,
            'records' => $requests,
            'total' => (int)$countRow['total'],
            'offset' => $offset,
            'limit' => $limit
        );
    }

    /**
     * Update file request status
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function updateFileRequestStatus($api, $args)
    {
        $this->requireArgs($args, array('module', 'request_id', 'status'));
        
        // Check ACL
        if (!ACLController::checkAccess('Deals', 'edit', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to update file requests');
        }

        $requestId = $args['request_id'];
        $newStatus = $args['status'];
        $notes = $args['notes'] ?? '';

        // Validate status
        $validStatuses = array('pending', 'sent', 'received', 'completed', 'cancelled');
        if (!in_array($newStatus, $validStatuses)) {
            throw new SugarApiExceptionInvalidParameter('Invalid status provided');
        }

        global $db, $current_user;

        // Check if request exists
        $checkQuery = "SELECT * FROM deal_file_requests WHERE id = ? AND deleted = 0";
        $checkResult = $db->pQuery($checkQuery, array($requestId));
        $requestData = $db->fetchByAssoc($checkResult);

        if (!$requestData) {
            throw new SugarApiExceptionNotFound('File request not found');
        }

        // Update status
        $updateQuery = "UPDATE deal_file_requests 
                        SET status = ?, status_updated_by = ?, status_updated_date = NOW()
                        WHERE id = ? AND deleted = 0";
        
        $db->pQuery($updateQuery, array($newStatus, $current_user->id, $requestId));

        // Log status change
        $this->logStatusChange($requestId, $requestData['status'], $newStatus, $notes);

        // Send notification email if needed
        if ($newStatus === 'completed') {
            $this->sendCompletionNotification($requestId);
        }

        return array(
            'success' => true,
            'request_id' => $requestId,
            'old_status' => $requestData['status'],
            'new_status' => $newStatus,
            'message' => 'File request status updated successfully'
        );
    }

    /**
     * Upload files for a file request
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function uploadRequestedFile($api, $args)
    {
        $this->requireArgs($args, array('module', 'upload_token'));
        
        $uploadToken = $args['upload_token'];
        $fileType = $args['file_type'] ?? 'document';

        global $db;

        // Validate upload token
        $tokenQuery = "SELECT * FROM deal_file_requests WHERE upload_token = ? AND deleted = 0";
        $tokenResult = $db->pQuery($tokenQuery, array($uploadToken));
        $requestData = $db->fetchByAssoc($tokenResult);

        if (!$requestData) {
            throw new SugarApiExceptionNotFound('Invalid upload token');
        }

        // Check if request is still active
        if (in_array($requestData['status'], array('completed', 'cancelled'))) {
            throw new SugarApiExceptionInvalidParameter('File request is no longer active');
        }

        // Handle file upload
        $uploadResult = $this->processFileUpload($requestData['id'], $fileType);

        if ($uploadResult['success']) {
            // Update file request item status
            $updateQuery = "UPDATE deal_file_request_items 
                            SET status = 'received', file_id = ?, date_received = NOW()
                            WHERE file_request_id = ? AND file_type = ?";
            
            $db->pQuery($updateQuery, array(
                $uploadResult['file_id'],
                $requestData['id'],
                $fileType
            ));

            // Check if all files are received
            $this->checkAndUpdateRequestCompletion($requestData['id']);
        }

        return array(
            'success' => $uploadResult['success'],
            'file_id' => $uploadResult['file_id'] ?? null,
            'message' => $uploadResult['message'],
            'request_status' => $this->getRequestStatus($requestData['id'])
        );
    }

    /**
     * Send or resend file request email
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function sendFileRequestEmail($api, $args)
    {
        $this->requireArgs($args, array('module', 'request_id'));
        
        // Check ACL
        if (!ACLController::checkAccess('Deals', 'edit', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to send file request emails');
        }

        $requestId = $args['request_id'];

        global $db;

        // Get request data
        $query = "SELECT fr.*, d.name as deal_name, d.description as deal_description
                  FROM deal_file_requests fr
                  LEFT JOIN deals d ON fr.deal_id = d.id
                  WHERE fr.id = ? AND fr.deleted = 0";
        
        $result = $db->pQuery($query, array($requestId));
        $requestData = $db->fetchByAssoc($result);

        if (!$requestData) {
            throw new SugarApiExceptionNotFound('File request not found');
        }

        // Load deal for additional context
        $deal = BeanFactory::getBean('Deals', $requestData['deal_id']);

        // Send email
        $emailResult = $this->sendFileRequestNotification($requestId, $deal);

        // Update sent status
        if ($emailResult['success']) {
            $updateQuery = "UPDATE deal_file_requests 
                            SET status = 'sent', last_email_sent = NOW()
                            WHERE id = ?";
            $db->pQuery($updateQuery, array($requestId));
        }

        return array(
            'success' => $emailResult['success'],
            'message' => $emailResult['message'],
            'email_sent_to' => $requestData['recipient_email'],
            'request_status' => $emailResult['success'] ? 'sent' : 'pending'
        );
    }

    /**
     * Get detailed status of a file request
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getFileRequestStatus($api, $args)
    {
        $this->requireArgs($args, array('module', 'request_id'));
        
        // Check ACL
        if (!ACLController::checkAccess('Deals', 'list', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view file request status');
        }

        $requestId = $args['request_id'];

        global $db;

        // Get request details
        $query = "SELECT fr.*, 
                         d.name as deal_name,
                         u.user_name as created_by_name
                  FROM deal_file_requests fr
                  LEFT JOIN deals d ON fr.deal_id = d.id
                  LEFT JOIN users u ON fr.created_by = u.id
                  WHERE fr.id = ? AND fr.deleted = 0";
        
        $result = $db->pQuery($query, array($requestId));
        $requestData = $db->fetchByAssoc($result);

        if (!$requestData) {
            throw new SugarApiExceptionNotFound('File request not found');
        }

        // Get file items
        $itemsQuery = "SELECT * FROM deal_file_request_items 
                       WHERE file_request_id = ? AND deleted = 0
                       ORDER BY file_type";
        
        $itemsResult = $db->pQuery($itemsQuery, array($requestId));
        $items = array();

        while ($item = $db->fetchByAssoc($itemsResult)) {
            $items[] = array(
                'id' => $item['id'],
                'file_type' => $item['file_type'],
                'file_description' => $item['file_description'],
                'is_required' => (bool)$item['is_required'],
                'status' => $item['status'],
                'file_id' => $item['file_id'],
                'date_received' => $item['date_received']
            );
        }

        // Get status history
        $historyQuery = "SELECT * FROM deal_file_request_history 
                         WHERE file_request_id = ? 
                         ORDER BY date_changed DESC";
        
        $historyResult = $db->pQuery($historyQuery, array($requestId));
        $history = array();

        while ($historyItem = $db->fetchByAssoc($historyResult)) {
            $history[] = array(
                'old_status' => $historyItem['old_status'],
                'new_status' => $historyItem['new_status'],
                'notes' => $historyItem['notes'],
                'changed_by' => $historyItem['changed_by'],
                'date_changed' => $historyItem['date_changed']
            );
        }

        return array(
            'success' => true,
            'request' => $this->formatFileRequestData($requestData),
            'file_items' => $items,
            'status_history' => $history,
            'upload_url' => $this->generateUploadUrl($requestData['upload_token'])
        );
    }

    /**
     * Generate secure upload token
     * 
     * @return string
     */
    private function generateUploadToken()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate upload URL for token
     * 
     * @param string $token
     * @return string
     */
    private function generateUploadUrl($token)
    {
        global $sugar_config;
        $baseUrl = $sugar_config['site_url'] ?? 'http://localhost';
        return $baseUrl . '/custom/modules/Deals/upload.php?token=' . $token;
    }

    /**
     * Send file request notification email
     * 
     * @param string $requestId
     * @param Deal $deal
     * @return array
     */
    private function sendFileRequestNotification($requestId, $deal)
    {
        global $db;

        // Get request data
        $query = "SELECT * FROM deal_file_requests WHERE id = ? AND deleted = 0";
        $result = $db->pQuery($query, array($requestId));
        $requestData = $db->fetchByAssoc($result);

        if (!$requestData) {
            return array('success' => false, 'message' => 'Request not found');
        }

        // Get file items
        $itemsQuery = "SELECT * FROM deal_file_request_items 
                       WHERE file_request_id = ? AND deleted = 0";
        $itemsResult = $db->pQuery($itemsQuery, array($requestId));
        $fileItems = array();

        while ($item = $db->fetchByAssoc($itemsResult)) {
            $fileItems[] = $item;
        }

        // Add file items to request data for template processing
        $requestData['file_items'] = $fileItems;

        // Send email using EmailProcessorService
        $emailProcessor = EmailProcessorService::getInstance();
        $templateType = $requestData['request_type'] ?? 'general';
        
        return $emailProcessor->sendFileRequestEmail($requestData, $deal, $templateType);
    }



    /**
     * Process file upload
     * 
     * @param string $requestId
     * @param string $fileType
     * @return array
     */
    private function processFileUpload($requestId, $fileType)
    {
        // Implement file upload logic using SuiteCRM's upload file system
        // This would integrate with the upload_file.php utilities
        
        try {
            // Placeholder for actual file upload implementation
            $uploadFile = new UploadFile('uploadfile');
            
            if (!$uploadFile->confirm_upload()) {
                return array('success' => false, 'message' => 'No file uploaded');
            }

            // Validate file type and size
            $allowedTypes = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'png');
            $fileExtension = strtolower(pathinfo($uploadFile->get_stored_file_name(), PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedTypes)) {
                return array('success' => false, 'message' => 'File type not allowed');
            }

            // Store file
            $uploadFile->final_move($uploadFile->get_stored_file_name());
            
            // Create document record
            $document = BeanFactory::newBean('Documents');
            $document->name = $uploadFile->get_stored_file_name();
            $document->filename = $uploadFile->get_stored_file_name();
            $document->file_mime_type = $uploadFile->mime_type;
            $document->save();

            return array(
                'success' => true, 
                'file_id' => $document->id,
                'message' => 'File uploaded successfully'
            );

        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Upload error: ' . $e->getMessage());
        }
    }

    /**
     * Check and update request completion status
     * 
     * @param string $requestId
     */
    private function checkAndUpdateRequestCompletion($requestId)
    {
        global $db;

        // Check if all required files are received
        $query = "SELECT COUNT(*) as total_required,
                         SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_required
                  FROM deal_file_request_items 
                  WHERE file_request_id = ? AND is_required = 1 AND deleted = 0";
        
        $result = $db->pQuery($query, array($requestId));
        $counts = $db->fetchByAssoc($result);

        if ($counts['total_required'] > 0 && $counts['total_required'] == $counts['received_required']) {
            // All required files received, mark as completed
            $updateQuery = "UPDATE deal_file_requests 
                            SET status = 'completed', completion_date = NOW()
                            WHERE id = ?";
            $db->pQuery($updateQuery, array($requestId));
        }
    }

    /**
     * Get current status of a request
     * 
     * @param string $requestId
     * @return string
     */
    private function getRequestStatus($requestId)
    {
        global $db;
        
        $query = "SELECT status FROM deal_file_requests WHERE id = ? AND deleted = 0";
        $result = $db->pQuery($query, array($requestId));
        $row = $db->fetchByAssoc($result);
        
        return $row['status'] ?? 'unknown';
    }

    /**
     * Log status change
     * 
     * @param string $requestId
     * @param string $oldStatus
     * @param string $newStatus
     * @param string $notes
     */
    private function logStatusChange($requestId, $oldStatus, $newStatus, $notes = '')
    {
        global $db, $current_user;
        
        $historyId = create_guid();
        $query = "INSERT INTO deal_file_request_history 
                  (id, file_request_id, old_status, new_status, notes, changed_by, date_changed)
                  VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $db->pQuery($query, array(
            $historyId,
            $requestId,
            $oldStatus,
            $newStatus,
            $notes,
            $current_user->id
        ));
    }


    /**
     * Send completion notification
     * 
     * @param string $requestId
     */
    private function sendCompletionNotification($requestId)
    {
        // Implementation for sending completion notification emails
        // This would notify the requestor that all files have been received
    }

    /**
     * Format file request data for API response
     * 
     * @param array $row
     * @return array
     */
    private function formatFileRequestData($row)
    {
        return array(
            'id' => $row['id'],
            'deal_id' => $row['deal_id'],
            'deal_name' => $row['deal_name'] ?? '',
            'request_type' => $row['request_type'],
            'recipient_email' => $row['recipient_email'],
            'recipient_name' => $row['recipient_name'],
            'description' => $row['description'],
            'requested_files' => json_decode($row['requested_files'] ?? '[]', true),
            'due_date' => $row['due_date'],
            'priority' => $row['priority'],
            'status' => $row['status'],
            'upload_token' => $row['upload_token'],
            'created_by' => $row['created_by'],
            'created_by_name' => trim(($row['created_by_first_name'] ?? '') . ' ' . ($row['created_by_last_name'] ?? '')),
            'date_created' => $row['date_created'],
            'total_files' => (int)($row['total_files'] ?? 0),
            'received_files' => (int)($row['received_files'] ?? 0),
            'completion_percentage' => $row['total_files'] > 0 ? 
                round(($row['received_files'] / $row['total_files']) * 100, 2) : 0
        );
    }
}