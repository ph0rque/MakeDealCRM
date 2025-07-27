<?php
/**
 * File Request Email Template Management
 * 
 * Manages email templates for different types of file requests:
 * - Due diligence document requests
 * - Financial document requests  
 * - Legal document requests
 * - General file requests
 * - Reminder emails
 * - Completion notifications
 * 
 * @category  Email
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'modules/EmailTemplates/EmailTemplate.php';

class FileRequestEmailTemplates
{
    private $templates = array();
    
    public function __construct()
    {
        $this->initializeTemplates();
    }
    
    /**
     * Initialize email templates
     */
    private function initializeTemplates()
    {
        $this->templates = array(
            'due_diligence' => array(
                'name' => 'File Request: Due Diligence Documents',
                'subject' => 'Due Diligence Document Request for {deal_name}',
                'body_html' => $this->getDueDiligenceTemplate(),
                'body_text' => $this->getDueDiligenceTemplateText(),
                'description' => 'Template for requesting due diligence documents from sellers'
            ),
            'financial' => array(
                'name' => 'File Request: Financial Documents',
                'subject' => 'Financial Document Request for {deal_name}',
                'body_html' => $this->getFinancialTemplate(),
                'body_text' => $this->getFinancialTemplateText(),
                'description' => 'Template for requesting financial documents and statements'
            ),
            'legal' => array(
                'name' => 'File Request: Legal Documents',
                'subject' => 'Legal Document Request for {deal_name}',
                'body_html' => $this->getLegalTemplate(),
                'body_text' => $this->getLegalTemplateText(),
                'description' => 'Template for requesting legal documents and contracts'
            ),
            'general' => array(
                'name' => 'File Request: General Documents',
                'subject' => 'Document Request for {deal_name}',
                'body_html' => $this->getGeneralTemplate(),
                'body_text' => $this->getGeneralTemplateText(),
                'description' => 'General template for file requests'
            ),
            'reminder' => array(
                'name' => 'File Request Reminder',
                'subject' => 'Reminder: Document Request for {deal_name}',
                'body_html' => $this->getReminderTemplate(),
                'body_text' => $this->getReminderTemplateText(),
                'description' => 'Template for reminder emails about pending file requests'
            ),
            'completion' => array(
                'name' => 'File Request Completed',
                'subject' => 'File Request Completed for {deal_name}',
                'body_html' => $this->getCompletionTemplate(),
                'body_text' => $this->getCompletionTemplateText(),
                'description' => 'Template for notifying completion of file requests'
            ),
            'partial_completion' => array(
                'name' => 'File Request Partial Completion',
                'subject' => 'Partial File Upload Received for {deal_name}',
                'body_html' => $this->getPartialCompletionTemplate(),
                'body_text' => $this->getPartialCompletionTemplateText(),
                'description' => 'Template for notifying partial completion of file requests'
            ),
            'overdue' => array(
                'name' => 'File Request Overdue',
                'subject' => 'Overdue: Document Request for {deal_name}',
                'body_html' => $this->getOverdueTemplate(),
                'body_text' => $this->getOverdueTemplateText(),
                'description' => 'Template for overdue file request notifications'
            )
        );
    }
    
    /**
     * Get template by type
     */
    public function getTemplate($type)
    {
        return $this->templates[$type] ?? $this->templates['general'];
    }
    
    /**
     * Get all available templates
     */
    public function getAllTemplates()
    {
        return $this->templates;
    }
    
    /**
     * Parse template with variables
     */
    public function parseTemplate($templateType, $variables)
    {
        $template = $this->getTemplate($templateType);
        
        $parsedTemplate = array(
            'subject' => $this->replaceVariables($template['subject'], $variables),
            'body_html' => $this->replaceVariables($template['body_html'], $variables),
            'body_text' => $this->replaceVariables($template['body_text'], $variables)
        );
        
        return $parsedTemplate;
    }
    
    /**
     * Replace variables in template content
     */
    private function replaceVariables($content, $variables)
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Due Diligence HTML Template
     */
    private function getDueDiligenceTemplate()
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Due Diligence Document Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .deal-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .file-list { background: #fff; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0; }
        .file-item { padding: 10px 15px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .required { color: #dc3545; font-weight: bold; }
        .upload-section { background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .upload-button { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Due Diligence Document Request</h1>
            <p>Dear {recipient_name},</p>
            <p>We are proceeding with the due diligence process for the potential acquisition of your business. To move forward efficiently, we need access to several key documents that will help us complete our evaluation.</p>
        </div>
        
        <div class="deal-info">
            <h3>Transaction Details</h3>
            <strong>Business:</strong> {deal_name}<br>
            <strong>Request Type:</strong> Due Diligence Documentation<br>
            <strong>Due Date:</strong> {due_date}<br>
            <strong>Priority:</strong> {priority}
        </div>
        
        <h3>Required Documents</h3>
        <div class="file-list">
            {file_list_html}
        </div>
        
        <div class="upload-section">
            <h3>Secure Document Upload</h3>
            <p>Please use the secure upload link below to submit your documents. All files are encrypted and stored securely.</p>
            <a href="{upload_url}" class="upload-button">Upload Documents Securely</a>
            <p><small>Upload Link: <a href="{upload_url}">{upload_url}</a></small></p>
        </div>
        
        <h3>Important Notes</h3>
        <ul>
            <li><strong>Confidentiality:</strong> All documents will be treated with strict confidentiality and used solely for due diligence purposes.</li>
            <li><strong>File Formats:</strong> Please provide documents in PDF format when possible. Word, Excel, and image files are also accepted.</li>
            <li><strong>File Naming:</strong> Please use descriptive filenames that clearly identify the document content.</li>
            <li><strong>Questions:</strong> If you have any questions about specific documents or need clarification, please contact us immediately.</li>
        </ul>
        
        {additional_notes}
        
        <div class="footer">
            <p>Thank you for your cooperation in this process. Your prompt attention to this request will help us maintain our timeline for completion.</p>
            <p>Best regards,<br>
            {user_name}<br>
            {user_title}<br>
            {company_name}</p>
            
            <p><em>This is an automated message. Please do not reply to this email address. For questions, contact {user_email}.</em></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Due Diligence Text Template
     */
    private function getDueDiligenceTemplateText()
    {
        return 'Due Diligence Document Request for {deal_name}

Dear {recipient_name},

We are proceeding with the due diligence process for the potential acquisition of your business. To move forward efficiently, we need access to several key documents that will help us complete our evaluation.

TRANSACTION DETAILS:
Business: {deal_name}
Request Type: Due Diligence Documentation
Due Date: {due_date}
Priority: {priority}

REQUIRED DOCUMENTS:
{file_list_text}

SECURE DOCUMENT UPLOAD:
Please use the secure upload link below to submit your documents. All files are encrypted and stored securely.

Upload Link: {upload_url}

IMPORTANT NOTES:
- Confidentiality: All documents will be treated with strict confidentiality and used solely for due diligence purposes.
- File Formats: Please provide documents in PDF format when possible. Word, Excel, and image files are also accepted.
- File Naming: Please use descriptive filenames that clearly identify the document content.
- Questions: If you have any questions about specific documents or need clarification, please contact us immediately.

{additional_notes}

Thank you for your cooperation in this process. Your prompt attention to this request will help us maintain our timeline for completion.

Best regards,
{user_name}
{user_title}
{company_name}

This is an automated message. Please do not reply to this email address. For questions, contact {user_email}.';
    }
    
    /**
     * Financial Documents HTML Template
     */
    private function getFinancialTemplate()
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Financial Document Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f0f8f0; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .deal-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .file-list { background: #fff; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0; }
        .file-item { padding: 10px 15px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .required { color: #dc3545; font-weight: bold; }
        .upload-section { background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .upload-button { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Financial Document Request</h1>
            <p>Dear {recipient_name},</p>
            <p>To complete our financial analysis of <strong>{deal_name}</strong>, we require access to your financial records and statements. These documents are crucial for our valuation process.</p>
        </div>
        
        <div class="deal-info">
            <h3>Request Details</h3>
            <strong>Business:</strong> {deal_name}<br>
            <strong>Request Type:</strong> Financial Documentation<br>
            <strong>Due Date:</strong> {due_date}<br>
            <strong>Priority:</strong> {priority}
        </div>
        
        <h3>Required Financial Documents</h3>
        <div class="file-list">
            {file_list_html}
        </div>
        
        <div class="upload-section">
            <h3>Secure Financial Document Upload</h3>
            <p>Upload your financial documents using our secure, encrypted platform.</p>
            <a href="{upload_url}" class="upload-button">Upload Financial Documents</a>
            <p><small>Upload Link: <a href="{upload_url}">{upload_url}</a></small></p>
        </div>
        
        <h3>Financial Document Guidelines</h3>
        <ul>
            <li><strong>Accuracy:</strong> Please ensure all financial information is accurate and up-to-date.</li>
            <li><strong>Completeness:</strong> Include all requested periods and categories of financial data.</li>
            <li><strong>Format:</strong> PDF or Excel formats are preferred for financial statements.</li>
            <li><strong>Confidentiality:</strong> All financial information will be kept strictly confidential and secure.</li>
        </ul>
        
        {additional_notes}
        
        <div class="footer">
            <p>Your financial documentation is essential for our evaluation process. Thank you for your cooperation.</p>
            <p>Best regards,<br>
            {user_name}<br>
            {user_title}<br>
            {company_name}</p>
            
            <p><em>This is an automated message. For questions about financial documents, contact {user_email}.</em></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Financial Documents Text Template
     */
    private function getFinancialTemplateText()
    {
        return 'Financial Document Request for {deal_name}

Dear {recipient_name},

To complete our financial analysis of {deal_name}, we require access to your financial records and statements. These documents are crucial for our valuation process.

REQUEST DETAILS:
Business: {deal_name}
Request Type: Financial Documentation
Due Date: {due_date}
Priority: {priority}

REQUIRED FINANCIAL DOCUMENTS:
{file_list_text}

SECURE FINANCIAL DOCUMENT UPLOAD:
Upload your financial documents using our secure, encrypted platform.
Upload Link: {upload_url}

FINANCIAL DOCUMENT GUIDELINES:
- Accuracy: Please ensure all financial information is accurate and up-to-date.
- Completeness: Include all requested periods and categories of financial data.
- Format: PDF or Excel formats are preferred for financial statements.
- Confidentiality: All financial information will be kept strictly confidential and secure.

{additional_notes}

Your financial documentation is essential for our evaluation process. Thank you for your cooperation.

Best regards,
{user_name}
{user_title}
{company_name}

This is an automated message. For questions about financial documents, contact {user_email}.';
    }
    
    /**
     * Legal Documents HTML Template
     */
    private function getLegalTemplate()
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Legal Document Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .deal-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .file-list { background: #fff; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0; }
        .file-item { padding: 10px 15px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .required { color: #dc3545; font-weight: bold; }
        .upload-section { background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .upload-button { display: inline-block; background: #ffc107; color: #212529; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666; }
        .legal-notice { background: #f8f9fa; border-left: 4px solid #6c757d; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Legal Document Request</h1>
            <p>Dear {recipient_name},</p>
            <p>As part of our legal review for the potential acquisition of <strong>{deal_name}</strong>, we require access to various legal documents and contracts. These documents are essential for our legal due diligence process.</p>
        </div>
        
        <div class="deal-info">
            <h3>Legal Review Details</h3>
            <strong>Business:</strong> {deal_name}<br>
            <strong>Request Type:</strong> Legal Documentation<br>
            <strong>Due Date:</strong> {due_date}<br>
            <strong>Priority:</strong> {priority}
        </div>
        
        <h3>Required Legal Documents</h3>
        <div class="file-list">
            {file_list_html}
        </div>
        
        <div class="upload-section">
            <h3>Secure Legal Document Upload</h3>
            <p>Please upload your legal documents through our secure portal.</p>
            <a href="{upload_url}" class="upload-button">Upload Legal Documents</a>
            <p><small>Upload Link: <a href="{upload_url}">{upload_url}</a></small></p>
        </div>
        
        <div class="legal-notice">
            <h3>Legal Document Guidelines</h3>
            <ul>
                <li><strong>Confidentiality:</strong> All legal documents will be reviewed under strict attorney-client privilege where applicable.</li>
                <li><strong>Completeness:</strong> Please provide complete copies of all requested agreements and contracts.</li>
                <li><strong>Currency:</strong> Ensure all documents represent the most current versions and amendments.</li>
                <li><strong>Organization:</strong> Please organize documents by type or category when possible.</li>
            </ul>
        </div>
        
        {additional_notes}
        
        <div class="footer">
            <p>The legal documentation you provide is critical to our transaction review. Thank you for your attention to this matter.</p>
            <p>Best regards,<br>
            {user_name}<br>
            {user_title}<br>
            {company_name}</p>
            
            <p><em>This is an automated message. For legal document questions, contact {user_email}.</em></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Legal Documents Text Template
     */
    private function getLegalTemplateText()
    {
        return 'Legal Document Request for {deal_name}

Dear {recipient_name},

As part of our legal review for the potential acquisition of {deal_name}, we require access to various legal documents and contracts. These documents are essential for our legal due diligence process.

LEGAL REVIEW DETAILS:
Business: {deal_name}
Request Type: Legal Documentation
Due Date: {due_date}
Priority: {priority}

REQUIRED LEGAL DOCUMENTS:
{file_list_text}

SECURE LEGAL DOCUMENT UPLOAD:
Please upload your legal documents through our secure portal.
Upload Link: {upload_url}

LEGAL DOCUMENT GUIDELINES:
- Confidentiality: All legal documents will be reviewed under strict attorney-client privilege where applicable.
- Completeness: Please provide complete copies of all requested agreements and contracts.
- Currency: Ensure all documents represent the most current versions and amendments.
- Organization: Please organize documents by type or category when possible.

{additional_notes}

The legal documentation you provide is critical to our transaction review. Thank you for your attention to this matter.

Best regards,
{user_name}
{user_title}
{company_name}

This is an automated message. For legal document questions, contact {user_email}.';
    }
    
    /**
     * General Template HTML
     */
    private function getGeneralTemplate()
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Document Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .deal-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .file-list { background: #fff; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0; }
        .file-item { padding: 10px 15px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .required { color: #dc3545; font-weight: bold; }
        .upload-section { background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .upload-button { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Document Request</h1>
            <p>Dear {recipient_name},</p>
            <p>We are requesting the following documents for <strong>{deal_name}</strong>. These documents are needed to proceed with our evaluation process.</p>
        </div>
        
        <div class="deal-info">
            <h3>Request Details</h3>
            <strong>Business:</strong> {deal_name}<br>
            <strong>Description:</strong> {description}<br>
            <strong>Due Date:</strong> {due_date}<br>
            <strong>Priority:</strong> {priority}
        </div>
        
        <h3>Requested Documents</h3>
        <div class="file-list">
            {file_list_html}
        </div>
        
        <div class="upload-section">
            <h3>Document Upload</h3>
            <p>Please use the secure link below to upload your documents.</p>
            <a href="{upload_url}" class="upload-button">Upload Documents</a>
            <p><small>Upload Link: <a href="{upload_url}">{upload_url}</a></small></p>
        </div>
        
        {additional_notes}
        
        <div class="footer">
            <p>Thank you for your assistance with this request.</p>
            <p>Best regards,<br>
            {user_name}<br>
            {user_title}<br>
            {company_name}</p>
            
            <p><em>This is an automated message. For questions, contact {user_email}.</em></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * General Template Text
     */
    private function getGeneralTemplateText()
    {
        return 'Document Request for {deal_name}

Dear {recipient_name},

We are requesting the following documents for {deal_name}. These documents are needed to proceed with our evaluation process.

REQUEST DETAILS:
Business: {deal_name}
Description: {description}
Due Date: {due_date}
Priority: {priority}

REQUESTED DOCUMENTS:
{file_list_text}

DOCUMENT UPLOAD:
Please use the secure link below to upload your documents.
Upload Link: {upload_url}

{additional_notes}

Thank you for your assistance with this request.

Best regards,
{user_name}
{user_title}
{company_name}

This is an automated message. For questions, contact {user_email}.';
    }
    
    /**
     * Reminder Template HTML
     */
    private function getReminderTemplate()
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Document Request Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107; }
        .deal-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .file-list { background: #fff; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0; }
        .file-item { padding: 10px 15px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .pending { color: #ffc107; font-weight: bold; }
        .upload-section { background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .upload-button { display: inline-block; background: #ffc107; color: #212529; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Document Request Reminder</h1>
            <p>Dear {recipient_name},</p>
            <p>This is a friendly reminder that we are still waiting for documents related to <strong>{deal_name}</strong>.</p>
        </div>
        
        <div class="deal-info">
            <h3>Original Request Details</h3>
            <strong>Business:</strong> {deal_name}<br>
            <strong>Original Due Date:</strong> {due_date}<br>
            <strong>Days Overdue:</strong> {days_overdue}<br>
            <strong>Priority:</strong> {priority}
        </div>
        
        <h3>Outstanding Documents</h3>
        <div class="file-list">
            {pending_files_html}
        </div>
        
        <div class="upload-section">
            <h3>Upload Outstanding Documents</h3>
            <p>Please use the same secure link to upload the remaining documents.</p>
            <a href="{upload_url}" class="upload-button">Upload Remaining Documents</a>
        </div>
        
        <p>If you are experiencing any difficulties with the upload process or have questions about the requested documents, please contact us immediately.</p>
        
        <div class="footer">
            <p>Thank you for your prompt attention to this matter.</p>
            <p>Best regards,<br>
            {user_name}<br>
            {user_title}<br>
            {company_name}</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Reminder Template Text
     */
    private function getReminderTemplateText()
    {
        return 'Document Request Reminder for {deal_name}

Dear {recipient_name},

This is a friendly reminder that we are still waiting for documents related to {deal_name}.

ORIGINAL REQUEST DETAILS:
Business: {deal_name}
Original Due Date: {due_date}
Days Overdue: {days_overdue}
Priority: {priority}

OUTSTANDING DOCUMENTS:
{pending_files_text}

UPLOAD OUTSTANDING DOCUMENTS:
Please use the same secure link to upload the remaining documents.
Upload Link: {upload_url}

If you are experiencing any difficulties with the upload process or have questions about the requested documents, please contact us immediately.

Thank you for your prompt attention to this matter.

Best regards,
{user_name}
{user_title}
{company_name}';
    }
    
    /**
     * Completion Template HTML
     */
    private function getCompletionTemplate()
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Document Request Completed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d4edda; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .deal-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .completion-summary { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Document Request Completed</h1>
            <p>Dear {recipient_name},</p>
            <p>Thank you! We have successfully received all requested documents for <strong>{deal_name}</strong>.</p>
        </div>
        
        <div class="deal-info">
            <h3>Request Summary</h3>
            <strong>Business:</strong> {deal_name}<br>
            <strong>Request Type:</strong> {request_type}<br>
            <strong>Completion Date:</strong> {completion_date}<br>
            <strong>Total Files Received:</strong> {total_files}
        </div>
        
        <div class="completion-summary">
            <h3>Next Steps</h3>
            <p>Our team will now review the submitted documents. We will contact you if we need any clarification or additional information.</p>
            <p>Expected review timeline: {review_timeline}</p>
        </div>
        
        <div class="footer">
            <p>Thank you for your cooperation and prompt response to our document request.</p>
            <p>Best regards,<br>
            {user_name}<br>
            {user_title}<br>
            {company_name}</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Completion Template Text
     */
    private function getCompletionTemplateText()
    {
        return 'Document Request Completed for {deal_name}

Dear {recipient_name},

Thank you! We have successfully received all requested documents for {deal_name}.

REQUEST SUMMARY:
Business: {deal_name}
Request Type: {request_type}
Completion Date: {completion_date}
Total Files Received: {total_files}

NEXT STEPS:
Our team will now review the submitted documents. We will contact you if we need any clarification or additional information.

Expected review timeline: {review_timeline}

Thank you for your cooperation and prompt response to our document request.

Best regards,
{user_name}
{user_title}
{company_name}';
    }
    
    /**
     * Partial Completion Template HTML
     */
    private function getPartialCompletionTemplate()
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Partial Document Upload Received</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #e2f3ff; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #17a2b8; }
        .deal-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .progress-summary { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .file-status { background: #fff; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0; }
        .file-item { padding: 10px 15px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .received { color: #28a745; font-weight: bold; }
        .pending { color: #ffc107; font-weight: bold; }
        .upload-section { background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .upload-button { display: inline-block; background: #17a2b8; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📄 Partial Document Upload Received</h1>
            <p>Dear {recipient_name},</p>
            <p>Thank you for uploading documents for <strong>{deal_name}</strong>. We have received some of the requested files.</p>
        </div>
        
        <div class="deal-info">
            <h3>Upload Progress</h3>
            <strong>Business:</strong> {deal_name}<br>
            <strong>Files Received:</strong> {received_files} of {total_files}<br>
            <strong>Progress:</strong> {completion_percentage}%<br>
            <strong>Last Upload:</strong> {last_upload_date}
        </div>
        
        <div class="progress-summary">
            <h3>Document Status</h3>
            <div class="file-status">
                {file_status_html}
            </div>
        </div>
        
        <div class="upload-section">
            <h3>Upload Remaining Documents</h3>
            <p>Please continue uploading the remaining required documents using the same secure link.</p>
            <a href="{upload_url}" class="upload-button">Continue Upload</a>
        </div>
        
        <div class="footer">
            <p>We appreciate your progress on this request. Please complete the remaining uploads at your earliest convenience.</p>
            <p>Best regards,<br>
            {user_name}<br>
            {user_title}<br>
            {company_name}</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Partial Completion Template Text
     */
    private function getPartialCompletionTemplateText()
    {
        return 'Partial Document Upload Received for {deal_name}

Dear {recipient_name},

Thank you for uploading documents for {deal_name}. We have received some of the requested files.

UPLOAD PROGRESS:
Business: {deal_name}
Files Received: {received_files} of {total_files}
Progress: {completion_percentage}%
Last Upload: {last_upload_date}

DOCUMENT STATUS:
{file_status_text}

UPLOAD REMAINING DOCUMENTS:
Please continue uploading the remaining required documents using the same secure link.
Upload Link: {upload_url}

We appreciate your progress on this request. Please complete the remaining uploads at your earliest convenience.

Best regards,
{user_name}
{user_title}
{company_name}';
    }
    
    /**
     * Overdue Template HTML
     */
    private function getOverdueTemplate()
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Overdue Document Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8d7da; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .deal-info { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .urgency-notice { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .file-list { background: #fff; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0; }
        .file-item { padding: 10px 15px; border-bottom: 1px solid #eee; }
        .file-item:last-child { border-bottom: none; }
        .overdue { color: #dc3545; font-weight: bold; }
        .upload-section { background: #fff5f5; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; border: 2px solid #dc3545; }
        .upload-button { display: inline-block; background: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ OVERDUE: Document Request</h1>
            <p>Dear {recipient_name},</p>
            <p>Our document request for <strong>{deal_name}</strong> is now <strong>{days_overdue} days overdue</strong>. Immediate attention is required.</p>
        </div>
        
        <div class="deal-info">
            <h3>Overdue Request Details</h3>
            <strong>Business:</strong> {deal_name}<br>
            <strong>Original Due Date:</strong> {due_date}<br>
            <strong>Days Overdue:</strong> <span class="overdue">{days_overdue} days</span><br>
            <strong>Priority:</strong> {priority}
        </div>
        
        <div class="urgency-notice">
            <h3>⚠️ URGENT ACTION REQUIRED</h3>
            <p>The delay in receiving these documents is impacting our ability to proceed with the evaluation of {deal_name}. Please prioritize the upload of the outstanding documents immediately.</p>
        </div>
        
        <h3>Outstanding Documents</h3>
        <div class="file-list">
            {pending_files_html}
        </div>
        
        <div class="upload-section">
            <h3>URGENT: Upload Documents Now</h3>
            <p>Please upload the outstanding documents immediately to avoid further delays.</p>
            <a href="{upload_url}" class="upload-button">UPLOAD NOW</a>
        </div>
        
        <p><strong>If you are unable to provide these documents by {new_deadline}, please contact us immediately to discuss alternative arrangements.</strong></p>
        
        <div class="footer">
            <p>Your immediate attention to this matter is critical to maintaining our transaction timeline.</p>
            <p>Best regards,<br>
            {user_name}<br>
            {user_title}<br>
            {company_name}</p>
            
            <p><strong>For urgent assistance, contact: {urgent_contact}</strong></p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Overdue Template Text
     */
    private function getOverdueTemplateText()
    {
        return 'OVERDUE: Document Request for {deal_name}

Dear {recipient_name},

Our document request for {deal_name} is now {days_overdue} days overdue. Immediate attention is required.

OVERDUE REQUEST DETAILS:
Business: {deal_name}
Original Due Date: {due_date}
Days Overdue: {days_overdue} days
Priority: {priority}

*** URGENT ACTION REQUIRED ***
The delay in receiving these documents is impacting our ability to proceed with the evaluation of {deal_name}. Please prioritize the upload of the outstanding documents immediately.

OUTSTANDING DOCUMENTS:
{pending_files_text}

URGENT: UPLOAD DOCUMENTS NOW
Please upload the outstanding documents immediately to avoid further delays.
Upload Link: {upload_url}

If you are unable to provide these documents by {new_deadline}, please contact us immediately to discuss alternative arrangements.

Your immediate attention to this matter is critical to maintaining our transaction timeline.

Best regards,
{user_name}
{user_title}
{company_name}

For urgent assistance, contact: {urgent_contact}';
    }
}