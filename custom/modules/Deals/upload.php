<?php
/**
 * Secure File Upload Handler for File Requests
 * 
 * Handles file uploads for deal file requests with:
 * - Token-based authentication
 * - File type validation
 * - Virus scanning integration
 * - Secure file storage
 * - Progress tracking
 * 
 * @category  Upload
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

require_once 'config.php';
require_once 'include/entryPoint.php';
require_once 'include/upload_file.php';
require_once 'include/database/DBManagerFactory.php';

class FileRequestUploadHandler
{
    private $db;
    private $allowedMimeTypes = array(
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/zip'
    );
    
    private $maxFileSize = 25 * 1024 * 1024; // 25MB
    
    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
    }
    
    /**
     * Handle file upload request
     */
    public function handleUpload()
    {
        try {
            // Validate token
            $token = $_GET['token'] ?? $_POST['token'] ?? '';
            if (!$token) {
                return $this->jsonResponse(false, 'Upload token required');
            }
            
            $requestData = $this->validateToken($token);
            if (!$requestData) {
                return $this->jsonResponse(false, 'Invalid or expired upload token');
            }
            
            // Check if request is still active
            if (in_array($requestData['status'], array('completed', 'cancelled'))) {
                return $this->jsonResponse(false, 'File request is no longer active');
            }
            
            // Handle the upload
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                return $this->processUpload($requestData);
            } else {
                return $this->showUploadForm($requestData);
            }
            
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Upload error: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate upload token
     */
    private function validateToken($token)
    {
        $query = "SELECT fr.*, d.name as deal_name
                  FROM deal_file_requests fr
                  LEFT JOIN deals d ON fr.deal_id = d.id
                  WHERE fr.upload_token = ? AND fr.deleted = 0";
        
        $result = $this->db->pQuery($query, array($token));
        return $this->db->fetchByAssoc($result);
    }
    
    /**
     * Process file upload
     */
    private function processUpload($requestData)
    {
        if (!isset($_FILES['files'])) {
            return $this->jsonResponse(false, 'No files uploaded');
        }
        
        $fileType = $_POST['file_type'] ?? 'document';
        $files = $_FILES['files'];
        $uploadResults = array();
        
        // Handle multiple files
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                $fileData = array(
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                );
                
                $result = $this->processSingleFile($fileData, $requestData, $fileType, $i);
                $uploadResults[] = $result;
            }
        } else {
            $result = $this->processSingleFile($files, $requestData, $fileType, 0);
            $uploadResults[] = $result;
        }
        
        // Update request status
        $this->updateRequestProgress($requestData['id']);
        
        return $this->jsonResponse(true, 'Files processed', array('results' => $uploadResults));
    }
    
    /**
     * Process single file upload
     */
    private function processSingleFile($fileData, $requestData, $fileType, $index)
    {
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'filename' => $fileData['name'],
                'error' => $this->getUploadErrorMessage($fileData['error'])
            );
        }
        
        // Validate file
        $validation = $this->validateFile($fileData);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'filename' => $fileData['name'],
                'error' => $validation['error']
            );
        }
        
        try {
            // Create unique filename
            $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $safeFilename = $this->generateSafeFilename($fileData['name'], $extension);
            
            // Create upload directory if it doesn't exist
            $uploadDir = 'upload/file_requests/' . $requestData['id'] . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filePath = $uploadDir . $safeFilename;
            
            // Move uploaded file
            if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
                return array(
                    'success' => false,
                    'filename' => $fileData['name'],
                    'error' => 'Failed to save file'
                );
            }
            
            // Scan for viruses if scanner is available
            $scanResult = $this->scanForViruses($filePath);
            if (!$scanResult['clean']) {
                unlink($filePath);
                return array(
                    'success' => false,
                    'filename' => $fileData['name'],
                    'error' => 'File rejected by security scan'
                );
            }
            
            // Create document record
            $documentId = $this->createDocumentRecord($fileData, $filePath, $requestData);
            
            // Update file request item
            $this->updateFileRequestItem($requestData['id'], $fileType, $documentId);
            
            // Log upload activity
            $this->logFileUpload($requestData['id'], $documentId, $fileData['name']);
            
            return array(
                'success' => true,
                'filename' => $fileData['name'],
                'document_id' => $documentId,
                'file_path' => $filePath
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'filename' => $fileData['name'],
                'error' => 'Upload error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($fileData)
    {
        // Check file size
        if ($fileData['size'] > $this->maxFileSize) {
            return array(
                'valid' => false,
                'error' => 'File size exceeds maximum allowed (' . ($this->maxFileSize / 1024 / 1024) . 'MB)'
            );
        }
        
        // Check mime type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileData['tmp_name']);
        
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return array(
                'valid' => false,
                'error' => 'File type not allowed: ' . $mimeType
            );
        }
        
        // Additional security checks
        $filename = $fileData['name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Block dangerous extensions
        $dangerousExtensions = array('php', 'phtml', 'jsp', 'asp', 'exe', 'bat', 'cmd', 'scr');
        if (in_array($extension, $dangerousExtensions)) {
            return array(
                'valid' => false,
                'error' => 'File extension not allowed: ' . $extension
            );
        }
        
        return array('valid' => true);
    }
    
    /**
     * Generate safe filename
     */
    private function generateSafeFilename($originalName, $extension)
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $baseName);
        $safeName = substr($safeName, 0, 100); // Limit length
        
        return $safeName . '_' . time() . '.' . $extension;
    }
    
    /**
     * Scan file for viruses (placeholder - integrate with actual scanner)
     */
    private function scanForViruses($filePath)
    {
        // Placeholder for virus scanning integration
        // In production, this would integrate with ClamAV or similar
        
        // Basic checks
        $fileSize = filesize($filePath);
        
        // Check for suspicious file patterns
        $content = file_get_contents($filePath, false, null, 0, min($fileSize, 1024));
        
        // Look for common malware signatures
        $malwareSignatures = array(
            '<?php',
            '<script',
            'eval(',
            'base64_decode',
            'shell_exec'
        );
        
        foreach ($malwareSignatures as $signature) {
            if (stripos($content, $signature) !== false) {
                return array('clean' => false, 'threat' => 'Suspicious content detected');
            }
        }
        
        return array('clean' => true);
    }
    
    /**
     * Create document record in SuiteCRM
     */
    private function createDocumentRecord($fileData, $filePath, $requestData)
    {
        $document = BeanFactory::newBean('Documents');
        $document->name = $fileData['name'];
        $document->filename = basename($filePath);
        $document->file_mime_type = $fileData['type'];
        $document->description = 'Uploaded for file request: ' . $requestData['request_type'];
        $document->status = 'Active';
        
        // Link to the deal
        $document->related_doc_id = $requestData['deal_id'];
        $document->related_doc_name = $requestData['deal_name'];
        
        $document->save();
        
        return $document->id;
    }
    
    /**
     * Update file request item status
     */
    private function updateFileRequestItem($requestId, $fileType, $documentId)
    {
        $query = "UPDATE deal_file_request_items 
                  SET status = 'received', file_id = ?, date_received = NOW()
                  WHERE file_request_id = ? AND file_type = ? AND deleted = 0";
        
        $this->db->pQuery($query, array($documentId, $requestId, $fileType));
    }
    
    /**
     * Update overall request progress
     */
    private function updateRequestProgress($requestId)
    {
        // Check if all required files are received
        $query = "SELECT COUNT(*) as total_required,
                         SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_required
                  FROM deal_file_request_items 
                  WHERE file_request_id = ? AND is_required = 1 AND deleted = 0";
        
        $result = $this->db->pQuery($query, array($requestId));
        $counts = $this->db->fetchByAssoc($result);
        
        if ($counts['total_required'] > 0 && $counts['total_required'] == $counts['received_required']) {
            // All required files received
            $updateQuery = "UPDATE deal_file_requests 
                            SET status = 'completed', completion_date = NOW()
                            WHERE id = ?";
            $this->db->pQuery($updateQuery, array($requestId));
        } else {
            // Update to received status if not already
            $updateQuery = "UPDATE deal_file_requests 
                            SET status = 'received'
                            WHERE id = ? AND status = 'sent'";
            $this->db->pQuery($updateQuery, array($requestId));
        }
    }
    
    /**
     * Log file upload activity
     */
    private function logFileUpload($requestId, $documentId, $filename)
    {
        $logId = create_guid();
        $query = "INSERT INTO deal_file_request_history 
                  (id, file_request_id, old_status, new_status, notes, changed_by, date_changed)
                  VALUES (?, ?, 'pending', 'received', ?, 'system', NOW())";
        
        $this->db->pQuery($query, array(
            $logId,
            $requestId,
            "File uploaded: $filename (Document ID: $documentId)"
        ));
    }
    
    /**
     * Show upload form
     */
    private function showUploadForm($requestData)
    {
        // Get file request items
        $query = "SELECT * FROM deal_file_request_items 
                  WHERE file_request_id = ? AND deleted = 0
                  ORDER BY file_type";
        
        $result = $this->db->pQuery($query, array($requestData['id']));
        $fileItems = array();
        
        while ($item = $this->db->fetchByAssoc($result)) {
            $fileItems[] = $item;
        }
        
        header('Content-Type: text/html; charset=utf-8');
        
        echo $this->generateUploadFormHTML($requestData, $fileItems);
        exit;
    }
    
    /**
     * Generate upload form HTML
     */
    private function generateUploadFormHTML($requestData, $fileItems)
    {
        $dealName = htmlspecialchars($requestData['deal_name']);
        $recipientName = htmlspecialchars($requestData['recipient_name']);
        $description = htmlspecialchars($requestData['description']);
        $dueDate = date('M j, Y', strtotime($requestData['due_date']));
        
        $fileListHTML = '';
        foreach ($fileItems as $item) {
            $required = $item['is_required'] ? '<span class="required">*</span>' : '';
            $status = $item['status'] === 'received' ? '<span class="received">✓ Received</span>' : '<span class="pending">Pending</span>';
            
            $fileListHTML .= '
                <div class="file-item">
                    <div class="file-info">
                        <strong>' . htmlspecialchars($item['file_description'] ?: $item['file_type']) . '</strong> ' . $required . '
                        <div class="file-status">' . $status . '</div>
                    </div>
                </div>';
        }
        
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload - ' . $dealName . '</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; line-height: 1.6; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .deal-info { margin-bottom: 20px; }
        .file-item { background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 5px; }
        .file-status { font-size: 0.9em; margin-top: 5px; }
        .required { color: #dc3545; font-weight: bold; }
        .received { color: #28a745; font-weight: bold; }
        .pending { color: #ffc107; font-weight: bold; }
        .upload-form { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, input[type="file"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .progress { width: 100%; background: #f0f0f0; border-radius: 4px; margin-top: 10px; }
        .progress-bar { height: 20px; background: #007bff; border-radius: 4px; width: 0%; text-align: center; color: white; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="header">
        <h1>File Upload Request</h1>
        <div class="deal-info">
            <strong>Deal:</strong> ' . $dealName . '<br>
            <strong>Recipient:</strong> ' . $recipientName . '<br>
            <strong>Due Date:</strong> ' . $dueDate . '<br>
            <strong>Description:</strong> ' . $description . '
        </div>
    </div>
    
    <h2>Requested Files</h2>
    ' . $fileListHTML . '
    
    <div class="upload-form">
        <h3>Upload Files</h3>
        <form id="uploadForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="token" value="' . htmlspecialchars($requestData['upload_token']) . '">
            
            <div class="form-group">
                <label for="file_type">File Type:</label>
                <select name="file_type" id="file_type" required>
                    <option value="">Select file type...</option>';

        foreach ($fileItems as $item) {
            if ($item['status'] === 'pending') {
                $value = htmlspecialchars($item['file_type']);
                $label = htmlspecialchars($item['file_description'] ?: $item['file_type']);
                echo '<option value="' . $value . '">' . $label . '</option>';
            }
        }

        return $fileListHTML . '</select>
            </div>
            
            <div class="form-group">
                <label for="files">Select Files:</label>
                <input type="file" name="files[]" id="files" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.png,.gif,.zip">
                <small>Maximum file size: 25MB. Allowed types: PDF, Word, Excel, Text, Images, ZIP</small>
            </div>
            
            <button type="submit">Upload Files</button>
        </form>
        
        <div id="progress" class="progress" style="display: none;">
            <div class="progress-bar" id="progressBar">0%</div>
        </div>
        
        <div id="result"></div>
    </div>
    
    <script>
        document.getElementById("uploadForm").addEventListener("submit", function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const progressDiv = document.getElementById("progress");
            const progressBar = document.getElementById("progressBar");
            const resultDiv = document.getElementById("result");
            
            progressDiv.style.display = "block";
            resultDiv.innerHTML = "";
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener("progress", function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + "%";
                    progressBar.textContent = Math.round(percentComplete) + "%";
                }
            });
            
            xhr.addEventListener("load", function() {
                progressDiv.style.display = "none";
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        resultDiv.innerHTML = "<div class=\"alert alert-success\">Files uploaded successfully!</div>";
                        // Refresh the page after 2 seconds to show updated status
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        resultDiv.innerHTML = "<div class=\"alert alert-error\">Upload failed: " + response.message + "</div>";
                    }
                } catch (e) {
                    resultDiv.innerHTML = "<div class=\"alert alert-error\">Upload error occurred.</div>";
                }
            });
            
            xhr.addEventListener("error", function() {
                progressDiv.style.display = "none";
                resultDiv.innerHTML = "<div class=\"alert alert-error\">Upload failed. Please try again.</div>";
            });
            
            xhr.open("POST", window.location.href);
            xhr.send(formData);
        });
    </script>
</body>
</html>';
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File size exceeds maximum allowed';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Return JSON response
     */
    private function jsonResponse($success, $message, $data = array())
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(array(
            'success' => $success,
            'message' => $message
        ), $data));
        exit;
    }
}

// Handle the request
$handler = new FileRequestUploadHandler();
$handler->handleUpload();