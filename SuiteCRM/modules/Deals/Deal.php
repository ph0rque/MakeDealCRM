<?php
/**
 * Deal Bean Class
 * 
 * This file represents the Deal module bean which extends from Basic
 * and implements the central object for all acquisition activities.
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('data/SugarBean.php');
require_once('include/utils.php');

class Deal extends SugarBean
{
    public $module_name = 'Deals';
    public $object_name = 'Deal';
    public $module_dir = 'Deals';
    public $table_name = 'opportunities';
    public $custom_fields = true;
    public $new_schema = true;
    public $importable = true;
    public $acl_category = 'Deals';
    
    // Basic fields
    public $id;
    public $name;
    public $date_entered;
    public $date_modified;
    public $modified_user_id;
    public $created_by;
    public $description;
    public $deleted;
    public $assigned_user_id;
    
    // Deal-specific fields
    public $status;
    public $source;
    public $deal_value;
    public $at_risk_status;
    public $focus_flag_c;
    
    // Financial fields
    public $asking_price_c;
    public $ttm_revenue_c;
    public $ttm_ebitda_c;
    public $sde_c;
    public $proposed_valuation_c;
    public $target_multiple_c;
    
    // Capital stack fields
    public $equity_c;
    public $senior_debt_c;
    public $seller_note_c;
    
    // Additional tracking
    public $date_in_current_stage;
    public $days_in_stage;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->disable_row_level_security = false;
        
        // Initialize custom fields
        if ($this->custom_fields) {
            $this->setupCustomFields($this->module_dir);
        }
    }
    
    /**
     * Override save to handle at-risk calculations and financial metrics
     */
    public function save($check_notify = false)
    {
        // Calculate days in current stage
        if (!empty($this->fetched_row['status']) && $this->fetched_row['status'] != $this->status) {
            // Status changed, reset the date
            $this->date_in_current_stage = date('Y-m-d H:i:s');
            $this->days_in_stage = 0;
        } elseif (!empty($this->date_in_current_stage)) {
            // Calculate days in stage
            $start = new DateTime($this->date_in_current_stage);
            $now = new DateTime();
            $diff = $start->diff($now);
            $this->days_in_stage = $diff->days;
            
            // Update at-risk status based on days in stage
            if ($this->days_in_stage >= 30) {
                $this->at_risk_status = 'Alert';
            } elseif ($this->days_in_stage >= 14) {
                $this->at_risk_status = 'Warning';
            } else {
                $this->at_risk_status = 'Normal';
            }
        } else {
            // First time save
            $this->date_in_current_stage = date('Y-m-d H:i:s');
            $this->days_in_stage = 0;
            $this->at_risk_status = 'Normal';
        }
        
        // Use FinancialCalculator for valuation calculations - TEMPORARILY DISABLED FOR TESTING
        // require_once('modules/Deals/services/FinancialCalculator.php');
        // $calculator = new FinancialCalculator();
        
        // Calculate proposed valuation if we have the data - TEMPORARILY DISABLED FOR TESTING
        // if (!empty($this->ttm_ebitda_c) && !empty($this->target_multiple_c)) {
        //     $this->proposed_valuation_c = $calculator->calculateProposedValuation(
        //         $this->ttm_ebitda_c,
        //         $this->target_multiple_c,
        //         'ebitda'
        //     );
        // }
        
        return parent::save($check_notify);
    }
    
    /**
     * Get list query for deals
     */
    public function create_list_query($order_by, $where, $show_deleted = 0)
    {
        $query = parent::create_list_query($order_by, $where, $show_deleted);
        return $query;
    }
    
    /**
     * Create new list query for deals (required for list view)
     */
    public function create_new_list_query($order_by, $where, $filter = array(), $params = array(), $show_deleted = 0, $join_type = '', $return_array = false, $parentbean = null, $singleSelect = false, $ifListForExport = false)
    {
        // Call parent method to get the base query
        $ret_value = parent::create_new_list_query($order_by, $where, $filter, $params, $show_deleted, $join_type, $return_array, $parentbean, $singleSelect, $ifListForExport);
        
        // Fix the table name in the query if it's using 'deals' instead of 'opportunities'
        if (is_string($ret_value)) {
            $ret_value = str_replace('deals.', 'opportunities.', $ret_value);
        } elseif (is_array($ret_value) && isset($ret_value['select'])) {
            $ret_value['select'] = str_replace('deals.', 'opportunities.', $ret_value['select']);
            if (isset($ret_value['from'])) {
                $ret_value['from'] = str_replace('deals.', 'opportunities.', $ret_value['from']);
            }
            if (isset($ret_value['where'])) {
                $ret_value['where'] = str_replace('deals.', 'opportunities.', $ret_value['where']);
            }
        }
        
        return $ret_value;
    }
    
    /**
     * Fill in additional details for list view
     */
    public function fill_in_additional_list_fields()
    {
        parent::fill_in_additional_list_fields();
        
        // Add any additional list view field processing here
        if (!empty($this->deal_value)) {
            global $locale;
            $this->deal_value = format_number($this->deal_value, 2, 2);
        }
    }
    
    /**
     * Get summary text for notifications
     */
    public function get_summary_text()
    {
        return $this->name;
    }
    
    /**
     * Get related contacts
     */
    public function get_contacts()
    {
        $this->load_relationship('contacts');
        return $this->contacts->getBeans();
    }
    
    /**
     * Get related documents
     */
    public function get_documents()
    {
        $this->load_relationship('documents');
        return $this->documents->getBeans();
    }
    
    /**
     * Create email from template for deal
     */
    public function create_email_from_template($template_id, $contact_ids = array())
    {
        global $current_user;
        
        require_once('modules/Emails/Email.php');
        require_once('modules/EmailTemplates/EmailTemplate.php');
        
        $template = BeanFactory::getBean('EmailTemplates', $template_id);
        if (empty($template->id)) {
            return false;
        }
        
        $email = new Email();
        $email->name = $template->subject;
        $email->description_html = $template->body_html;
        $email->description = $template->body;
        $email->from_addr = $current_user->email1;
        $email->from_name = $current_user->full_name;
        $email->parent_type = $this->module_name;
        $email->parent_id = $this->id;
        $email->assigned_user_id = $current_user->id;
        
        // Parse template for deal variables
        $email->name = $this->parse_template($email->name);
        $email->description_html = $this->parse_template($email->description_html);
        $email->description = $this->parse_template($email->description);
        
        return $email;
    }
    
    /**
     * Parse template variables
     */
    private function parse_template($text)
    {
        $text = str_replace('{DEAL_NAME}', $this->name, $text);
        $text = str_replace('{DEAL_STATUS}', $this->status, $text);
        $text = str_replace('{DEAL_VALUE}', format_number($this->deal_value), $text);
        $text = str_replace('{ASSIGNED_USER}', $this->assigned_user_name, $text);
        
        return $text;
    }
}