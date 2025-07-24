<?php
/**
 * Export Service for Due Diligence Checklists
 * Handles PDF and Excel export functionality for checklist reports
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('include/SugarPHPMailer.php');
require_once('include/export_utils.php');

class DueDiligenceExportService
{
    private $deal;
    private $checklistData;
    private $progressData;
    
    /**
     * Constructor
     * @param Deal $deal The deal object
     */
    public function __construct($deal = null)
    {
        $this->deal = $deal;
        if ($deal) {
            $this->loadChecklistData();
            $this->loadProgressData();
        }
    }
    
    /**
     * Load checklist data for the deal
     */
    private function loadChecklistData()
    {
        // This will integrate with the checklist system from task 2.3
        // For now, we'll prepare the structure
        $this->checklistData = [
            'deal_id' => $this->deal->id,
            'deal_name' => $this->deal->name,
            'checklists' => [],
            'file_requests' => [],
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'pending_tasks' => 0,
            'overdue_tasks' => 0
        ];
        
        // TODO: Implement actual checklist data loading once checklist system is ready
        // This would query the checklist tables created in task 2.3
    }
    
    /**
     * Load progress data for the deal
     */
    private function loadProgressData()
    {
        // This will integrate with the progress tracking from task 2.4
        $this->progressData = [
            'overall_progress' => 0,
            'progress_by_category' => [],
            'milestone_status' => [],
            'progress_history' => [],
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        // TODO: Implement actual progress data loading once progress system is ready
    }
    
    /**
     * Export checklist as PDF
     * @param array $options Export options
     * @return array Result with file path and download URL
     */
    public function exportToPDF($options = [])
    {
        try {
            $defaultOptions = [
                'template' => 'standard',
                'include_progress' => true,
                'include_file_requests' => true,
                'include_notes' => true,
                'branding' => true,
                'watermark' => '',
                'orientation' => 'portrait'
            ];
            
            $options = array_merge($defaultOptions, $options);
            
            // Get PDF engine from SuiteCRM
            require_once('lib/PDF/PDFWrapper.php');
            $pdfEngine = SuiteCRM\PDF\PDFWrapper::getPDFEngine();
            
            // Generate PDF content
            $htmlContent = $this->generatePDFContent($options);
            
            // Create PDF
            $filename = $this->generateFilename('pdf');
            $filepath = 'upload/exports/' . $filename;
            
            // Ensure export directory exists
            if (!file_exists('upload/exports')) {
                mkdir('upload/exports', 0755, true);
            }
            
            $pdfEngine->writeHTML($htmlContent);
            $pdfEngine->Output($filepath, 'F');
            
            // Store export metadata
            $this->logExport('pdf', $filepath, $options);
            
            return [
                'success' => true,
                'filepath' => $filepath,
                'filename' => $filename,
                'download_url' => $GLOBALS['sugar_config']['site_url'] . '/' . $filepath,
                'size' => filesize($filepath)
            ];
            
        } catch (Exception $e) {
            $GLOBALS['log']->error("PDF Export Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Export checklist as Excel
     * @param array $options Export options
     * @return array Result with file path and download URL
     */
    public function exportToExcel($options = [])
    {
        try {
            $defaultOptions = [
                'format' => 'xlsx',
                'include_progress' => true,
                'include_charts' => true,
                'separate_sheets' => true,
                'include_formulas' => true
            ];
            
            $options = array_merge($defaultOptions, $options);
            
            // Generate Excel content using CSV with advanced formatting
            $excelData = $this->generateExcelContent($options);
            
            $filename = $this->generateFilename($options['format']);
            $filepath = 'upload/exports/' . $filename;
            
            // Ensure export directory exists
            if (!file_exists('upload/exports')) {
                mkdir('upload/exports', 0755, true);
            }
            
            // For now, export as enhanced CSV that Excel can open
            // TODO: Implement true Excel generation with a library like PhpSpreadsheet
            $this->writeExcelFile($filepath, $excelData, $options);
            
            // Store export metadata
            $this->logExport('excel', $filepath, $options);
            
            return [
                'success' => true,
                'filepath' => $filepath,
                'filename' => $filename,
                'download_url' => $GLOBALS['sugar_config']['site_url'] . '/' . $filepath,
                'size' => filesize($filepath)
            ];
            
        } catch (Exception $e) {
            $GLOBALS['log']->error("Excel Export Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Batch export multiple deals
     * @param array $dealIds Array of deal IDs to export
     * @param string $format Export format (pdf|excel)
     * @param array $options Export options
     * @return array Results for each deal
     */
    public function batchExport($dealIds, $format = 'pdf', $options = [])
    {
        $results = [];
        $batchId = uniqid('batch_');
        
        foreach ($dealIds as $dealId) {
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || empty($deal->id)) {
                $results[$dealId] = [
                    'success' => false,
                    'error' => 'Deal not found'
                ];
                continue;
            }
            
            $exportService = new self($deal);
            
            if ($format === 'pdf') {
                $result = $exportService->exportToPDF($options);
            } else {
                $result = $exportService->exportToExcel($options);
            }
            
            $result['batch_id'] = $batchId;
            $results[$dealId] = $result;
        }
        
        // Create batch summary
        $this->createBatchSummary($batchId, $results, $format);
        
        return [
            'batch_id' => $batchId,
            'results' => $results,
            'summary' => $this->getBatchSummary($results)
        ];
    }
    
    /**
     * Generate PDF content HTML
     * @param array $options
     * @return string HTML content
     */
    private function generatePDFContent($options)
    {
        $template = $this->loadPDFTemplate($options['template']);
        
        // Replace template variables
        $replacements = [
            '{DEAL_NAME}' => htmlspecialchars($this->deal->name ?? ''),
            '{DEAL_ID}' => htmlspecialchars($this->deal->id ?? ''),
            '{EXPORT_DATE}' => date('F j, Y'),
            '{EXPORT_TIME}' => date('g:i A'),
            '{COMPANY_NAME}' => htmlspecialchars($GLOBALS['sugar_config']['company_name'] ?? 'Company'),
            '{TOTAL_TASKS}' => $this->checklistData['total_tasks'],
            '{COMPLETED_TASKS}' => $this->checklistData['completed_tasks'],
            '{PENDING_TASKS}' => $this->checklistData['pending_tasks'],
            '{OVERDUE_TASKS}' => $this->checklistData['overdue_tasks'],
            '{PROGRESS_PERCENTAGE}' => round($this->progressData['overall_progress'], 1),
            '{CHECKLIST_CONTENT}' => $this->generateChecklistHTML($options),
            '{PROGRESS_CHARTS}' => $options['include_progress'] ? $this->generateProgressHTML() : '',
            '{FILE_REQUESTS}' => $options['include_file_requests'] ? $this->generateFileRequestsHTML() : '',
            '{NOTES_SECTION}' => $options['include_notes'] ? $this->generateNotesHTML() : ''
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Generate Excel content data
     * @param array $options
     * @return array Excel data structure
     */
    private function generateExcelContent($options)
    {
        $data = [
            'summary' => $this->generateSummarySheet(),
            'checklist' => $this->generateChecklistSheet(),
            'progress' => $this->generateProgressSheet(),
            'file_requests' => $this->generateFileRequestsSheet()
        ];
        
        return $data;
    }
    
    /**
     * Generate filename for export
     * @param string $extension
     * @return string
     */
    private function generateFilename($extension)
    {
        $dealName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->deal->name ?? 'Deal');
        $timestamp = date('Y-m-d_H-i-s');
        return "due_diligence_{$dealName}_{$timestamp}.{$extension}";
    }
    
    /**
     * Load PDF template
     * @param string $templateName
     * @return string Template HTML
     */
    private function loadPDFTemplate($templateName)
    {
        $templatePath = "custom/modules/Deals/templates/pdf/{$templateName}.html";
        
        if (file_exists($templatePath)) {
            return file_get_contents($templatePath);
        }
        
        // Return default template if custom not found
        return $this->getDefaultPDFTemplate();
    }
    
    /**
     * Get default PDF template
     * @return string Default template HTML
     */
    private function getDefaultPDFTemplate()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Due Diligence Report - {DEAL_NAME}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .header { border-bottom: 2px solid #007cba; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #007cba; margin: 0; font-size: 24px; }
        .header .details { margin-top: 10px; color: #666; }
        .summary { background: #f8f9fa; padding: 20px; border-left: 4px solid #007cba; margin-bottom: 30px; }
        .summary h2 { margin-top: 0; color: #007cba; }
        .progress-bar { background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-fill { background: #28a745; height: 100%; transition: width 0.3s ease; }
        .section { margin-bottom: 30px; }
        .section h3 { color: #007cba; border-bottom: 1px solid #dee2e6; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: bold; color: #495057; }
        .status-completed { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-overdue { color: #dc3545; font-weight: bold; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Due Diligence Report</h1>
        <div class="details">
            <strong>Deal:</strong> {DEAL_NAME}<br>
            <strong>Report Generated:</strong> {EXPORT_DATE} at {EXPORT_TIME}<br>
            <strong>{COMPANY_NAME}</strong>
        </div>
    </div>
    
    <div class="summary">
        <h2>Executive Summary</h2>
        <p><strong>Overall Progress:</strong> {PROGRESS_PERCENTAGE}%</p>
        <div class="progress-bar">
            <div class="progress-fill" style="width: {PROGRESS_PERCENTAGE}%"></div>
        </div>
        <p>
            <strong>Total Tasks:</strong> {TOTAL_TASKS} | 
            <strong>Completed:</strong> <span class="status-completed">{COMPLETED_TASKS}</span> | 
            <strong>Pending:</strong> <span class="status-pending">{PENDING_TASKS}</span> | 
            <strong>Overdue:</strong> <span class="status-overdue">{OVERDUE_TASKS}</span>
        </p>
    </div>
    
    {CHECKLIST_CONTENT}
    {PROGRESS_CHARTS}
    {FILE_REQUESTS}
    {NOTES_SECTION}
    
    <div class="footer">
        Generated by {COMPANY_NAME} CRM System<br>
        Confidential and Proprietary Information
    </div>
</body>
</html>';
    }
    
    /**
     * Generate checklist HTML for PDF
     * @param array $options
     * @return string
     */
    private function generateChecklistHTML($options)
    {
        // TODO: Implement once checklist system is ready
        return '<div class="section">
            <h3>Due Diligence Checklist</h3>
            <p><em>Checklist data will be loaded from the checklist system (Task 2.3)</em></p>
        </div>';
    }
    
    /**
     * Generate progress HTML for PDF
     * @return string
     */
    private function generateProgressHTML()
    {
        // TODO: Implement once progress system is ready
        return '<div class="section">
            <h3>Progress Analysis</h3>
            <p><em>Progress data will be loaded from the progress tracking system (Task 2.4)</em></p>
        </div>';
    }
    
    /**
     * Generate file requests HTML for PDF
     * @return string
     */
    private function generateFileRequestsHTML()
    {
        // TODO: Implement once file request system is ready
        return '<div class="section">
            <h3>File Requests</h3>
            <p><em>File request data will be loaded from the file request system (Task 2.5)</em></p>
        </div>';
    }
    
    /**
     * Generate notes HTML for PDF
     * @return string
     */
    private function generateNotesHTML()
    {
        return '<div class="section">
            <h3>Notes</h3>
            <p>' . htmlspecialchars($this->deal->description ?? 'No notes available.') . '</p>
        </div>';
    }
    
    /**
     * Generate summary sheet for Excel
     * @return array
     */
    private function generateSummarySheet()
    {
        return [
            'headers' => ['Metric', 'Value'],
            'data' => [
                ['Deal Name', $this->deal->name ?? ''],
                ['Total Tasks', $this->checklistData['total_tasks']],
                ['Completed Tasks', $this->checklistData['completed_tasks']],
                ['Pending Tasks', $this->checklistData['pending_tasks']],
                ['Overdue Tasks', $this->checklistData['overdue_tasks']],
                ['Overall Progress', $this->progressData['overall_progress'] . '%'],
                ['Last Updated', $this->progressData['last_updated']]
            ]
        ];
    }
    
    /**
     * Generate checklist sheet for Excel
     * @return array
     */
    private function generateChecklistSheet()
    {
        // TODO: Implement once checklist system is ready
        return [
            'headers' => ['Task ID', 'Task Name', 'Category', 'Status', 'Due Date', 'Assigned To', 'Notes'],
            'data' => [
                ['Sample data will be replaced with actual checklist data from Task 2.3']
            ]
        ];
    }
    
    /**
     * Generate progress sheet for Excel
     * @return array
     */
    private function generateProgressSheet()
    {
        // TODO: Implement once progress system is ready
        return [
            'headers' => ['Date', 'Progress %', 'Tasks Completed', 'Notes'],
            'data' => [
                ['Sample data will be replaced with actual progress data from Task 2.4']
            ]
        ];
    }
    
    /**
     * Generate file requests sheet for Excel
     * @return array
     */
    private function generateFileRequestsSheet()
    {
        // TODO: Implement once file request system is ready
        return [
            'headers' => ['Request ID', 'File Name', 'Requested From', 'Status', 'Due Date', 'Received Date'],
            'data' => [
                ['Sample data will be replaced with actual file request data from Task 2.5']
            ]
        ];
    }
    
    /**
     * Write Excel file
     * @param string $filepath
     * @param array $data
     * @param array $options
     */
    private function writeExcelFile($filepath, $data, $options)
    {
        // For now, create an enhanced CSV that Excel can open properly
        // TODO: Implement proper Excel generation with PhpSpreadsheet library
        
        $output = '';
        
        if ($options['separate_sheets']) {
            // Create multiple CSV sections for different sheets
            foreach ($data as $sheetName => $sheetData) {
                $output .= "\"=== " . strtoupper($sheetName) . " ===\"\n";
                $output .= $this->arrayToCSV($sheetData);
                $output .= "\n\n";
            }
        } else {
            // Single sheet with all data
            $output = $this->arrayToCSV($data['summary']);
        }
        
        // Add BOM for proper Excel UTF-8 handling
        $output = "\xEF\xBB\xBF" . $output;
        
        file_put_contents($filepath, $output);
    }
    
    /**
     * Convert array to CSV format
     * @param array $data
     * @return string
     */
    private function arrayToCSV($data)
    {
        $output = '';
        
        // Add headers
        if (isset($data['headers'])) {
            $output .= '"' . implode('","', $data['headers']) . '"' . "\n";
        }
        
        // Add data rows
        if (isset($data['data'])) {
            foreach ($data['data'] as $row) {
                if (is_array($row)) {
                    $output .= '"' . implode('","', $row) . '"' . "\n";
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Log export activity
     * @param string $format
     * @param string $filepath
     * @param array $options
     */
    private function logExport($format, $filepath, $options)
    {
        global $current_user;
        
        $logData = [
            'deal_id' => $this->deal->id,
            'deal_name' => $this->deal->name,
            'export_format' => $format,
            'export_options' => json_encode($options),
            'filepath' => $filepath,
            'filesize' => filesize($filepath),
            'exported_by' => $current_user->id ?? 'system',
            'export_date' => date('Y-m-d H:i:s')
        ];
        
        $GLOBALS['log']->info("Due Diligence Export: " . json_encode($logData));
        
        // TODO: Store in export history table once database schema is ready
    }
    
    /**
     * Create batch summary
     * @param string $batchId
     * @param array $results
     * @param string $format
     */
    private function createBatchSummary($batchId, $results, $format)
    {
        $summary = [
            'batch_id' => $batchId,
            'format' => $format,
            'total_deals' => count($results),
            'successful_exports' => 0,
            'failed_exports' => 0,
            'total_size' => 0,
            'created_date' => date('Y-m-d H:i:s')
        ];
        
        foreach ($results as $result) {
            if ($result['success']) {
                $summary['successful_exports']++;
                $summary['total_size'] += $result['size'] ?? 0;
            } else {
                $summary['failed_exports']++;
            }
        }
        
        // Store batch summary
        file_put_contents(
            "upload/exports/batch_{$batchId}_summary.json",
            json_encode($summary, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Get batch summary statistics
     * @param array $results
     * @return array
     */
    private function getBatchSummary($results)
    {
        $successful = 0;
        $failed = 0;
        $totalSize = 0;
        
        foreach ($results as $result) {
            if ($result['success']) {
                $successful++;
                $totalSize += $result['size'] ?? 0;
            } else {
                $failed++;
            }
        }
        
        return [
            'total' => count($results),
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => count($results) > 0 ? round(($successful / count($results)) * 100, 1) : 0,
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2)
        ];
    }
}