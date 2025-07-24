<?php
/**
 * Export Controller for Due Diligence Reports
 * Handles web requests for PDF and Excel exports
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Deals/services/ExportService.php');

class DueDiligenceExportController
{
    /**
     * Export single deal to PDF
     */
    public function exportDealToPDF()
    {
        try {
            $this->validateRequest();
            
            $dealId = $_REQUEST['deal_id'] ?? null;
            $options = $this->parseExportOptions($_REQUEST);
            
            if (!$dealId) {
                throw new Exception('Deal ID is required');
            }
            
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || empty($deal->id)) {
                throw new Exception('Deal not found');
            }
            
            // Check permissions
            if (!$this->checkExportPermission($deal)) {
                throw new Exception('Access denied');
            }
            
            $exportService = new DueDiligenceExportService($deal);
            $result = $exportService->exportToPDF($options);
            
            if ($result['success']) {
                $this->downloadFile($result['filepath'], $result['filename']);
            } else {
                $this->returnError($result['error']);
            }
            
        } catch (Exception $e) {
            $this->returnError($e->getMessage());
        }
    }
    
    /**
     * Export single deal to Excel
     */
    public function exportDealToExcel()
    {
        try {
            $this->validateRequest();
            
            $dealId = $_REQUEST['deal_id'] ?? null;
            $options = $this->parseExportOptions($_REQUEST);
            
            if (!$dealId) {
                throw new Exception('Deal ID is required');
            }
            
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || empty($deal->id)) {
                throw new Exception('Deal not found');
            }
            
            // Check permissions
            if (!$this->checkExportPermission($deal)) {
                throw new Exception('Access denied');
            }
            
            $exportService = new DueDiligenceExportService($deal);
            $result = $exportService->exportToExcel($options);
            
            if ($result['success']) {
                $this->downloadFile($result['filepath'], $result['filename']);
            } else {
                $this->returnError($result['error']);
            }
            
        } catch (Exception $e) {
            $this->returnError($e->getMessage());
        }
    }
    
    /**
     * Batch export multiple deals
     */
    public function batchExport()
    {
        try {
            $this->validateRequest();
            
            $dealIds = $_REQUEST['deal_ids'] ?? [];
            $format = $_REQUEST['format'] ?? 'pdf';
            $options = $this->parseExportOptions($_REQUEST);
            
            if (empty($dealIds)) {
                throw new Exception('No deals selected for export');
            }
            
            if (is_string($dealIds)) {
                $dealIds = explode(',', $dealIds);
            }
            
            // Limit batch size for performance
            if (count($dealIds) > 50) {
                throw new Exception('Batch export limited to 50 deals maximum');
            }
            
            // Validate format
            if (!in_array($format, ['pdf', 'excel'])) {
                throw new Exception('Invalid export format');
            }
            
            $exportService = new DueDiligenceExportService();
            $result = $exportService->batchExport($dealIds, $format, $options);
            
            // Return JSON response for batch exports
            $this->returnJSON([
                'success' => true,
                'batch_id' => $result['batch_id'],
                'summary' => $result['summary'],
                'download_links' => array_map(function($r) {
                    return $r['success'] ? $r['download_url'] : null;
                }, $result['results'])
            ]);
            
        } catch (Exception $e) {
            $this->returnError($e->getMessage());
        }
    }
    
    /**
     * Validate request security and authentication
     */
    private function validateRequest()
    {
        global $current_user;
        
        if (empty($current_user) || empty($current_user->id)) {
            throw new Exception('Authentication required');
        }
        
        // Check if exports are enabled
        global $sugar_config;
        if (!empty($sugar_config['disable_export'])) {
            throw new Exception('Export functionality is disabled');
        }
    }
    
    /**
     * Check if user has permission to export a deal
     * @param Deal $deal
     * @return bool
     */
    private function checkExportPermission($deal)
    {
        return true; // Basic implementation - extend based on needs
    }
    
    /**
     * Parse export options from request
     * @param array $request
     * @return array
     */
    private function parseExportOptions($request)
    {
        $options = [];
        
        // PDF options
        $options['template'] = $request['template'] ?? 'standard';
        $options['include_progress'] = isset($request['include_progress']) ? 
            filter_var($request['include_progress'], FILTER_VALIDATE_BOOLEAN) : true;
        $options['include_file_requests'] = isset($request['include_file_requests']) ? 
            filter_var($request['include_file_requests'], FILTER_VALIDATE_BOOLEAN) : true;
        $options['include_notes'] = isset($request['include_notes']) ? 
            filter_var($request['include_notes'], FILTER_VALIDATE_BOOLEAN) : true;
        $options['branding'] = isset($request['branding']) ? 
            filter_var($request['branding'], FILTER_VALIDATE_BOOLEAN) : true;
        $options['watermark'] = $request['watermark'] ?? '';
        $options['orientation'] = $request['orientation'] ?? 'portrait';
        
        // Excel options
        $options['format'] = $request['excel_format'] ?? 'xlsx';
        $options['include_charts'] = isset($request['include_charts']) ? 
            filter_var($request['include_charts'], FILTER_VALIDATE_BOOLEAN) : true;
        $options['separate_sheets'] = isset($request['separate_sheets']) ? 
            filter_var($request['separate_sheets'], FILTER_VALIDATE_BOOLEAN) : true;
        $options['include_formulas'] = isset($request['include_formulas']) ? 
            filter_var($request['include_formulas'], FILTER_VALIDATE_BOOLEAN) : true;
        
        return $options;
    }
    
    /**
     * Download file to browser
     * @param string $filepath
     * @param string $filename
     */
    private function downloadFile($filepath, $filename)
    {
        if (!file_exists($filepath)) {
            throw new Exception('File not found');
        }
        
        $filesize = filesize($filepath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Set appropriate content type
        $contentTypes = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv'
        ];
        
        $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
        
        // Clear any existing output
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Set headers
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output file
        readfile($filepath);
        exit;
    }
    
    /**
     * Return JSON response
     * @param array $data
     */
    private function returnJSON($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Return error response
     * @param string $message
     */
    private function returnError($message)
    {
        global $log;
        $log->error("Export Error: " . $message);
        
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
}

// Handle the request based on action
if (isset($_REQUEST['action'])) {
    $controller = new DueDiligenceExportController();
    
    switch ($_REQUEST['action']) {
        case 'exportToPDF':
            $controller->exportDealToPDF();
            break;
        case 'exportToExcel':
            $controller->exportDealToExcel();
            break;
        case 'batchExport':
            $controller->batchExport();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}