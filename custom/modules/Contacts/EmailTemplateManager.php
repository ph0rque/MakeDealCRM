<?php
/**
 * EmailTemplateManager
 * Manages email templates for stakeholder communications
 * Specialized for multi-party introductions and deal communications
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Contacts/ContactRoleManager.php');
require_once('custom/modules/Contacts/StakeholderRelationshipService.php');

class EmailTemplateManager
{
    // Template type constants
    const TEMPLATE_INTRODUCTION = 'introduction';
    const TEMPLATE_FOLLOW_UP = 'follow_up';
    const TEMPLATE_UPDATE = 'update';
    const TEMPLATE_MEETING_REQUEST = 'meeting_request';
    const TEMPLATE_DOCUMENT_REQUEST = 'document_request';
    const TEMPLATE_CLOSING_COORDINATION = 'closing_coordination';
    
    /**
     * Get all available template types
     * @return array Template types
     */
    public static function getTemplateTypes()
    {
        return array(
            self::TEMPLATE_INTRODUCTION => 'Multi-Party Introduction',
            self::TEMPLATE_FOLLOW_UP => 'Follow-Up',
            self::TEMPLATE_UPDATE => 'Deal Update',
            self::TEMPLATE_MEETING_REQUEST => 'Meeting Request',
            self::TEMPLATE_DOCUMENT_REQUEST => 'Document Request',
            self::TEMPLATE_CLOSING_COORDINATION => 'Closing Coordination'
        );
    }
    
    /**
     * Generate multi-party introduction email
     * @param string $dealId The deal ID
     * @param array $recipientIds Array of contact IDs to introduce
     * @param array $options Additional options
     * @return array Email data (subject, body, recipients)
     */
    public static function generateIntroductionEmail($dealId, $recipientIds, $options = array())
    {
        global $db, $current_user;
        
        // Get deal information
        $dealQuery = "SELECT name, description, amount, sales_stage 
                      FROM opportunities 
                      WHERE id = '{$db->quote($dealId)}' AND deleted = 0";
        $result = $db->query($dealQuery);
        $deal = $db->fetchByAssoc($result);
        
        if (!$deal) {
            return false;
        }
        
        // Get recipient information
        $recipients = array();
        $recipientNames = array();
        $recipientRoles = array();
        
        foreach ($recipientIds as $contactId) {
            $query = "SELECT c.id, c.first_name, c.last_name, c.email1, 
                             c.contact_role_c, c.title, c.account_name
                      FROM contacts c
                      WHERE c.id = '{$db->quote($contactId)}' AND c.deleted = 0";
            
            $result = $db->query($query);
            if ($contact = $db->fetchByAssoc($result)) {
                $recipients[] = $contact;
                $recipientNames[] = $contact['first_name'] . ' ' . $contact['last_name'];
                
                $role = ContactRoleManager::getRoleDisplayName($contact['contact_role_c']);
                if ($role && !in_array($role, $recipientRoles)) {
                    $recipientRoles[] = $role;
                }
            }
        }
        
        // Generate email content
        $subject = "Introduction: {$deal['name']} - Stakeholder Connection";
        
        $body = self::getIntroductionTemplate();
        
        // Replace placeholders
        $replacements = array(
            '{RECIPIENT_NAMES}' => implode(', ', $recipientNames),
            '{DEAL_NAME}' => $deal['name'],
            '{DEAL_DESCRIPTION}' => $deal['description'] ?: 'N/A',
            '{DEAL_STAGE}' => $deal['sales_stage'],
            '{STAKEHOLDER_ROLES}' => implode(', ', $recipientRoles),
            '{SENDER_NAME}' => $current_user->first_name . ' ' . $current_user->last_name,
            '{SENDER_TITLE}' => $current_user->title ?: 'Deal Coordinator'
        );
        
        foreach ($replacements as $placeholder => $value) {
            $body = str_replace($placeholder, $value, $body);
        }
        
        // Build recipient list with details
        $recipientList = "";
        foreach ($recipients as $recipient) {
            $recipientList .= "\n- " . $recipient['first_name'] . ' ' . $recipient['last_name'];
            if ($recipient['title']) {
                $recipientList .= " - " . $recipient['title'];
            }
            if ($recipient['account_name']) {
                $recipientList .= " at " . $recipient['account_name'];
            }
            $role = ContactRoleManager::getRoleDisplayName($recipient['contact_role_c']);
            if ($role) {
                $recipientList .= " (" . $role . ")";
            }
        }
        
        $body = str_replace('{RECIPIENT_LIST}', $recipientList, $body);
        
        return array(
            'subject' => $subject,
            'body' => $body,
            'body_html' => nl2br($body),
            'recipients' => $recipients,
            'cc_recipients' => array($current_user->email1),
            'template_type' => self::TEMPLATE_INTRODUCTION
        );
    }
    
    /**
     * Generate follow-up email template
     * @param string $dealId The deal ID
     * @param string $contactId The contact ID
     * @param array $options Additional options
     * @return array Email data
     */
    public static function generateFollowUpEmail($dealId, $contactId, $options = array())
    {
        global $db, $current_user;
        
        // Get contact and deal information
        $query = "SELECT c.first_name, c.last_name, c.contact_role_c,
                         o.name as deal_name, o.sales_stage,
                         cc.last_contact_date_c, cc.follow_up_notes_c
                  FROM contacts c
                  LEFT JOIN contacts_cstm cc ON c.id = cc.id_c
                  INNER JOIN deals_contacts dc ON dc.contact_id = c.id
                  INNER JOIN opportunities o ON o.id = dc.deal_id
                  WHERE c.id = '{$db->quote($contactId)}' 
                  AND o.id = '{$db->quote($dealId)}'
                  AND c.deleted = 0 AND o.deleted = 0";
        
        $result = $db->query($query);
        $data = $db->fetchByAssoc($result);
        
        if (!$data) {
            return false;
        }
        
        $subject = "Follow-up: {$data['deal_name']}";
        
        $body = self::getFollowUpTemplate();
        
        $replacements = array(
            '{CONTACT_NAME}' => $data['first_name'] . ' ' . $data['last_name'],
            '{DEAL_NAME}' => $data['deal_name'],
            '{DEAL_STAGE}' => $data['sales_stage'],
            '{LAST_CONTACT_DATE}' => $data['last_contact_date_c'] ?: 'N/A',
            '{FOLLOW_UP_NOTES}' => $data['follow_up_notes_c'] ?: '',
            '{SENDER_NAME}' => $current_user->first_name . ' ' . $current_user->last_name
        );
        
        foreach ($replacements as $placeholder => $value) {
            $body = str_replace($placeholder, $value, $body);
        }
        
        return array(
            'subject' => $subject,
            'body' => $body,
            'body_html' => nl2br($body),
            'template_type' => self::TEMPLATE_FOLLOW_UP
        );
    }
    
    /**
     * Generate deal update email for all stakeholders
     * @param string $dealId The deal ID
     * @param string $updateType Type of update
     * @param string $updateMessage Custom update message
     * @return array Email data
     */
    public static function generateDealUpdateEmail($dealId, $updateType, $updateMessage)
    {
        global $db, $current_user;
        
        // Get deal information
        $dealQuery = "SELECT name, sales_stage, amount, date_closed 
                      FROM opportunities 
                      WHERE id = '{$db->quote($dealId)}' AND deleted = 0";
        $result = $db->query($dealQuery);
        $deal = $db->fetchByAssoc($result);
        
        if (!$deal) {
            return false;
        }
        
        // Get all stakeholders
        $stakeholders = StakeholderRelationshipService::getDealStakeholders($dealId);
        
        $subject = "Deal Update: {$deal['name']} - {$updateType}";
        
        $body = self::getDealUpdateTemplate();
        
        $replacements = array(
            '{DEAL_NAME}' => $deal['name'],
            '{UPDATE_TYPE}' => $updateType,
            '{UPDATE_MESSAGE}' => $updateMessage,
            '{DEAL_STAGE}' => $deal['sales_stage'],
            '{EXPECTED_CLOSE}' => $deal['date_closed'] ?: 'TBD',
            '{SENDER_NAME}' => $current_user->first_name . ' ' . $current_user->last_name,
            '{TIMESTAMP}' => date('F j, Y g:i A')
        );
        
        foreach ($replacements as $placeholder => $value) {
            $body = str_replace($placeholder, $value, $body);
        }
        
        // Build stakeholder summary
        $stakeholderSummary = "\nCurrent Stakeholders:\n";
        $emailRecipients = array();
        
        foreach ($stakeholders as $stakeholder) {
            $stakeholderSummary .= "- " . $stakeholder['first_name'] . ' ' . $stakeholder['last_name'];
            if ($stakeholder['role_display_name']) {
                $stakeholderSummary .= " (" . $stakeholder['role_display_name'] . ")";
            }
            $stakeholderSummary .= "\n";
            
            if (!empty($stakeholder['email1'])) {
                $emailRecipients[] = $stakeholder['email1'];
            }
        }
        
        $body = str_replace('{STAKEHOLDER_SUMMARY}', $stakeholderSummary, $body);
        
        return array(
            'subject' => $subject,
            'body' => $body,
            'body_html' => nl2br($body),
            'recipients' => $emailRecipients,
            'stakeholders' => $stakeholders,
            'template_type' => self::TEMPLATE_UPDATE
        );
    }
    
    /**
     * Get introduction email template
     * @return string Template content
     */
    private static function getIntroductionTemplate()
    {
        return "Dear {RECIPIENT_NAMES},

I hope this email finds you well. I wanted to take a moment to introduce all stakeholders involved in the {DEAL_NAME} transaction.

Deal Overview:
- Transaction: {DEAL_NAME}
- Current Stage: {DEAL_STAGE}
- Description: {DEAL_DESCRIPTION}

Stakeholder Team:
{RECIPIENT_LIST}

The purpose of this introduction is to ensure all parties have each other's contact information and to establish clear communication channels as we move forward with this transaction.

I encourage everyone to use \"Reply All\" for deal-related communications to keep all stakeholders informed. For confidential matters, please reach out directly to the relevant party.

Next Steps:
1. Please acknowledge receipt of this email
2. Feel free to share any initial questions or concerns
3. We will schedule a stakeholder call to align on timeline and responsibilities

I look forward to working with all of you to ensure a successful transaction.

Best regards,
{SENDER_NAME}
{SENDER_TITLE}";
    }
    
    /**
     * Get follow-up email template
     * @return string Template content
     */
    private static function getFollowUpTemplate()
    {
        return "Dear {CONTACT_NAME},

I hope this message finds you well. I wanted to follow up on our {DEAL_NAME} transaction.

Current Status:
- Deal Stage: {DEAL_STAGE}
- Last Contact: {LAST_CONTACT_DATE}

{FOLLOW_UP_NOTES}

I wanted to check in to see if you have any questions or if there's anything you need from my end to keep things moving forward.

Please let me know your availability for a brief call this week to discuss next steps.

Best regards,
{SENDER_NAME}";
    }
    
    /**
     * Get deal update template
     * @return string Template content
     */
    private static function getDealUpdateTemplate()
    {
        return "Dear Team,

I wanted to provide an update on the {DEAL_NAME} transaction.

Update Type: {UPDATE_TYPE}
Date: {TIMESTAMP}

Update Details:
{UPDATE_MESSAGE}

Current Deal Status:
- Stage: {DEAL_STAGE}
- Expected Close: {EXPECTED_CLOSE}

{STAKEHOLDER_SUMMARY}

Please don't hesitate to reach out if you have any questions or concerns about this update.

Best regards,
{SENDER_NAME}";
    }
    
    /**
     * Save email template for reuse
     * @param string $name Template name
     * @param string $subject Email subject
     * @param string $body Email body
     * @param string $type Template type
     * @return string Template ID
     */
    public static function saveCustomTemplate($name, $subject, $body, $type)
    {
        global $db, $current_user;
        
        $id = create_guid();
        $now = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO email_templates 
                 (id, date_entered, date_modified, modified_user_id, created_by,
                  name, subject, body, body_html, type, deleted)
                 VALUES 
                 ('{$id}', '{$now}', '{$now}', '{$current_user->id}', '{$current_user->id}',
                  '{$db->quote($name)}', '{$db->quote($subject)}', '{$db->quote($body)}',
                  '{$db->quote(nl2br($body))}', '{$db->quote($type)}', 0)";
        
        $db->query($query);
        
        return $id;
    }
    
    /**
     * Get custom templates by type
     * @param string $type Template type
     * @return array Templates
     */
    public static function getCustomTemplatesByType($type)
    {
        global $db;
        
        $query = "SELECT id, name, subject, body 
                  FROM email_templates 
                  WHERE type = '{$db->quote($type)}' 
                  AND deleted = 0 
                  ORDER BY name";
        
        $result = $db->query($query);
        $templates = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $templates[] = $row;
        }
        
        return $templates;
    }
}