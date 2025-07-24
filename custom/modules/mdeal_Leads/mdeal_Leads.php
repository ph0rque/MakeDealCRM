<?php
/**
 * MakeDeal CRM Leads Module
 * 
 * This module manages potential business acquisitions in their earliest stages,
 * integrating with the pipeline system for lead qualification and conversion to Deals.
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class mdeal_Leads extends Basic
{
    public $new_schema = true;
    public $module_dir = 'mdeal_Leads';
    public $object_name = 'mdeal_Leads';
    public $table_name = 'mdeal_leads';
    public $importable = true;

    public $id;
    public $name;
    public $date_entered;
    public $date_modified;
    public $modified_user_id;
    public $modified_by_name;
    public $created_by;
    public $created_by_name;
    public $description;
    public $deleted;
    public $created_by_link;
    public $modified_user_link;
    public $assigned_user_id;
    public $assigned_user_name;
    public $assigned_user_link;
    public $SecurityGroups;

    // Lead-specific fields
    public $first_name;
    public $last_name;
    public $title;
    public $phone_work;
    public $phone_mobile;
    public $email_address;
    public $website;
    
    // Company Information
    public $company_name;
    public $industry;
    public $annual_revenue;
    public $employee_count;
    public $years_in_business;
    
    // Lead Qualification
    public $lead_source;
    public $lead_source_description;
    public $status;
    public $status_description;
    public $rating;
    
    // Pipeline Integration
    public $pipeline_stage;
    public $days_in_stage;
    public $date_entered_stage;
    public $qualification_score;
    public $converted_deal_id;
    
    // Location
    public $primary_address_street;
    public $primary_address_city;
    public $primary_address_state;
    public $primary_address_postalcode;
    public $primary_address_country;
    
    // Additional Tracking
    public $do_not_call;
    public $email_opt_out;
    public $invalid_email;
    public $last_activity_date;
    public $next_follow_up_date;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns the summary text that should show up on a lead's summary listing.
     */
    public function get_summary_text()
    {
        return $this->company_name;
    }

    /**
     * Save override to handle pipeline stage transitions
     */
    public function save($check_notify = false)
    {
        // Track pipeline stage changes
        if (!empty($this->pipeline_stage) && 
            (!empty($this->fetched_row['pipeline_stage']) && 
             $this->pipeline_stage != $this->fetched_row['pipeline_stage'])) {
            $this->date_entered_stage = date('Y-m-d H:i:s');
            $this->days_in_stage = 0;
        }

        // Calculate qualification score if status changed
        if (!empty($this->status) && 
            (!empty($this->fetched_row['status']) && 
             $this->status != $this->fetched_row['status'])) {
            $this->calculateQualificationScore();
        }

        return parent::save($check_notify);
    }

    /**
     * Calculate lead qualification score based on various factors
     */
    protected function calculateQualificationScore()
    {
        $score = 0;

        // Industry fit (0-25 points)
        if (!empty($this->industry)) {
            $preferredIndustries = ['manufacturing', 'technology', 'healthcare', 'services'];
            if (in_array(strtolower($this->industry), $preferredIndustries)) {
                $score += 25;
            } else {
                $score += 10;
            }
        }

        // Revenue size (0-25 points)
        if (!empty($this->annual_revenue)) {
            if ($this->annual_revenue >= 10000000) { // $10M+
                $score += 25;
            } elseif ($this->annual_revenue >= 5000000) { // $5M+
                $score += 20;
            } elseif ($this->annual_revenue >= 1000000) { // $1M+
                $score += 15;
            } else {
                $score += 5;
            }
        }

        // Engagement level (0-25 points)
        if ($this->status == 'qualified') {
            $score += 25;
        } elseif ($this->status == 'contacted') {
            $score += 15;
        } elseif ($this->status == 'new') {
            $score += 5;
        }

        // Lead source quality (0-25 points)
        if (!empty($this->lead_source)) {
            $highQualitySources = ['referral', 'broker_network', 'direct_outreach'];
            if (in_array($this->lead_source, $highQualitySources)) {
                $score += 25;
            } else {
                $score += 10;
            }
        }

        $this->qualification_score = min(100, $score);
    }

    /**
     * Convert Lead to Deal
     */
    public function convertToDeal($userId = null)
    {
        if (empty($userId)) {
            global $current_user;
            $userId = $current_user->id;
        }

        // Check if already converted
        if (!empty($this->converted_deal_id)) {
            return false;
        }

        // Create new Deal
        require_once('custom/modules/mdeal_Deals/mdeal_Deals.php');
        $deal = new mdeal_Deals();
        
        // Copy relevant fields
        $deal->name = $this->company_name;
        $deal->description = $this->description;
        $deal->assigned_user_id = $this->assigned_user_id;
        $deal->industry = $this->industry;
        $deal->expected_revenue = $this->annual_revenue;
        
        // Set initial stage
        $deal->stage = 'sourcing';
        $deal->lead_source = $this->lead_source;
        
        // Save the deal
        $deal->save();

        // Create Contact record
        $this->createContactFromLead($deal->id);

        // Update Lead status
        $this->status = 'converted';
        $this->converted_deal_id = $deal->id;
        $this->save();

        // Copy activities to Deal
        $this->copyActivitiesToDeal($deal->id);

        return $deal->id;
    }

    /**
     * Create Contact from Lead information
     */
    protected function createContactFromLead($dealId)
    {
        // This will be implemented when Contacts module is created
        // For now, just log the intention
        $GLOBALS['log']->info("Would create contact for lead {$this->id} linked to deal {$dealId}");
    }

    /**
     * Copy all activities from Lead to Deal
     */
    protected function copyActivitiesToDeal($dealId)
    {
        // Copy calls, meetings, tasks, notes, emails
        $modules = ['Calls', 'Meetings', 'Tasks', 'Notes', 'Emails'];
        
        foreach ($modules as $module) {
            $this->copyRelatedRecords($module, $dealId, 'mdeal_Deals');
        }
    }

    /**
     * Generic method to copy related records
     */
    protected function copyRelatedRecords($module, $newParentId, $newParentType)
    {
        $relationship = strtolower($this->module_dir) . '_' . strtolower($module);
        
        if ($this->load_relationship($relationship)) {
            $relatedBeans = $this->$relationship->getBeans();
            
            foreach ($relatedBeans as $bean) {
                $newBean = clone $bean;
                $newBean->id = create_guid();
                $newBean->parent_type = $newParentType;
                $newBean->parent_id = $newParentId;
                $newBean->save();
            }
        }
    }

    /**
     * Check if lead is ready for conversion
     */
    public function isReadyForConversion()
    {
        // Lead must be in 'ready_to_convert' pipeline stage
        if ($this->pipeline_stage != 'ready_to_convert') {
            return false;
        }

        // Qualification score should be above threshold
        if ($this->qualification_score < 70) {
            return false;
        }

        // Required fields must be populated
        $requiredFields = ['company_name', 'industry', 'annual_revenue'];
        foreach ($requiredFields as $field) {
            if (empty($this->$field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get days since last activity
     */
    public function getDaysSinceLastActivity()
    {
        if (empty($this->last_activity_date)) {
            return null;
        }

        $lastActivity = new DateTime($this->last_activity_date);
        $now = new DateTime();
        $interval = $lastActivity->diff($now);
        
        return $interval->days;
    }

    /**
     * Override to add custom logic for list queries
     */
    public function create_new_list_query($order_by, $where, $filter = array(), $params = array(), $show_deleted = 0, $join_type = '', $return_array = false, $parentbean = null, $singleSelect = false, $ifListForExport = false)
    {
        // Call parent method
        $ret_array = parent::create_new_list_query($order_by, $where, $filter, $params, $show_deleted, $join_type, true, $parentbean, $singleSelect, $ifListForExport);

        // Add custom select fields for calculated values
        $ret_array['select'] .= ", DATEDIFF(NOW(), {$this->table_name}.date_entered_stage) as days_in_stage_calc";

        if ($return_array) {
            return $ret_array;
        }

        return $ret_array['select'] . $ret_array['from'] . $ret_array['where'] . $ret_array['order_by'];
    }
}