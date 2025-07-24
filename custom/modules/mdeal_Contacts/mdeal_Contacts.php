<?php
/**
 * MakeDeal CRM Contacts Module
 * 
 * This module manages all individuals involved in deals, including sellers, brokers,
 * advisors, attorneys, accountants, and other stakeholders. Supports complex
 * many-to-many relationships with Deals, Accounts, and hierarchical structures.
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class mdeal_Contacts extends Person
{
    public $new_schema = true;
    public $module_dir = 'mdeal_Contacts';
    public $object_name = 'mdeal_Contacts';
    public $table_name = 'mdeal_contacts';
    public $importable = true;
    public $disable_row_level_security = false;

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

    // Person fields (inherited)
    public $salutation;
    public $first_name;
    public $last_name;
    public $full_name;
    public $title;
    public $department;
    public $phone_work;
    public $phone_mobile;
    public $phone_home;
    public $phone_other;
    public $phone_fax;
    public $email_address;
    public $email_address2;
    public $assistant;
    public $assistant_phone;

    // Contact-specific fields
    public $contact_type;
    public $contact_subtype;
    public $account_id;
    public $account_name;
    public $reports_to_id;
    public $reports_to_name;
    public $lead_source;
    public $linkedin_url;

    // Address fields
    public $primary_address_street;
    public $primary_address_city;
    public $primary_address_state;
    public $primary_address_postalcode;
    public $primary_address_country;
    public $alt_address_street;
    public $alt_address_city;
    public $alt_address_state;
    public $alt_address_postalcode;
    public $alt_address_country;

    // Deal-specific fields
    public $preferred_contact_method;
    public $best_time_to_contact;
    public $timezone;
    public $communication_style;
    public $decision_role;
    public $influence_level;

    // Relationship tracking
    public $relationship_strength;
    public $last_interaction_date;
    public $interaction_count;
    public $response_rate;
    public $trust_level;

    // Additional fields
    public $do_not_call;
    public $email_opt_out;
    public $invalid_email;
    public $birthdate;
    public $picture;
    public $confidentiality_agreement;
    public $background_check_completed;
    public $background_check_date;
    public $notes_private;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns the summary text that should show up on a contact's summary listing.
     */
    public function get_summary_text()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Save override to handle relationship calculations and validations
     */
    public function save($check_notify = false)
    {
        // Calculate interaction metrics
        if (!empty($this->fetched_row)) {
            $this->updateInteractionMetrics();
        }

        // Set full name
        $this->full_name = trim($this->first_name . ' ' . $this->last_name);

        // Update last interaction date if contact info changed
        if (!empty($this->fetched_row) && $this->hasContactInfoChanged()) {
            $this->last_interaction_date = date('Y-m-d H:i:s');
        }

        // Validate hierarchy to prevent circular references
        if (!empty($this->reports_to_id) && !$this->validateHierarchy()) {
            throw new Exception('Circular reference detected in reporting hierarchy');
        }

        return parent::save($check_notify);
    }

    /**
     * Check if contact information has changed
     */
    protected function hasContactInfoChanged()
    {
        $contactFields = ['email_address', 'phone_work', 'phone_mobile', 'title', 'department'];
        
        foreach ($contactFields as $field) {
            if (isset($this->fetched_row[$field]) && 
                $this->$field != $this->fetched_row[$field]) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Update interaction metrics
     */
    protected function updateInteractionMetrics()
    {
        // Increment interaction count if this is an update
        if (isset($this->fetched_row['interaction_count'])) {
            $this->interaction_count = $this->fetched_row['interaction_count'] + 1;
        } else {
            $this->interaction_count = 1;
        }

        // Calculate response rate based on activities
        $this->calculateResponseRate();
    }

    /**
     * Calculate response rate based on email activities
     */
    protected function calculateResponseRate()
    {
        // This would typically query the emails table for sent vs responded
        // For now, we'll use a simplified calculation
        if (!empty($this->interaction_count) && $this->interaction_count > 5) {
            // Mock calculation - in real implementation, query email responses
            $this->response_rate = min(100, ($this->interaction_count * 15) + rand(10, 30));
        }
    }

    /**
     * Validate hierarchy to prevent circular references
     */
    protected function validateHierarchy()
    {
        if (empty($this->reports_to_id) || $this->reports_to_id == $this->id) {
            return true; // No hierarchy or self-reference (which we'll prevent)
        }

        // Check for circular reference by walking up the hierarchy
        $currentId = $this->reports_to_id;
        $visited = [$this->id];
        $maxDepth = 10; // Prevent infinite loops
        $depth = 0;

        while ($currentId && $depth < $maxDepth) {
            if (in_array($currentId, $visited)) {
                return false; // Circular reference detected
            }

            $visited[] = $currentId;

            // Get the next level up
            $contact = BeanFactory::getBean('mdeal_Contacts', $currentId);
            if (!$contact || empty($contact->reports_to_id)) {
                break;
            }

            $currentId = $contact->reports_to_id;
            $depth++;
        }

        return true;
    }

    /**
     * Get all contacts that report to this contact
     */
    public function getDirectReports()
    {
        $query = "SELECT id, first_name, last_name, title FROM {$this->table_name} 
                  WHERE reports_to_id = ? AND deleted = 0 
                  ORDER BY last_name, first_name";
        
        $result = $this->db->pQuery($query, [$this->id]);
        $reports = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $reports[] = $row;
        }
        
        return $reports;
    }

    /**
     * Get complete organization chart starting from this contact
     */
    public function getOrganizationChart($maxDepth = 5)
    {
        return $this->buildOrgChart($this->id, 0, $maxDepth);
    }

    /**
     * Recursively build organization chart
     */
    protected function buildOrgChart($contactId, $currentDepth, $maxDepth)
    {
        if ($currentDepth >= $maxDepth) {
            return null;
        }

        $contact = BeanFactory::getBean('mdeal_Contacts', $contactId);
        if (!$contact) {
            return null;
        }

        $node = [
            'id' => $contact->id,
            'name' => trim($contact->first_name . ' ' . $contact->last_name),
            'title' => $contact->title,
            'department' => $contact->department,
            'email' => $contact->email_address,
            'phone' => $contact->phone_work,
            'children' => []
        ];

        // Get direct reports
        $reports = $contact->getDirectReports();
        foreach ($reports as $report) {
            $child = $this->buildOrgChart($report['id'], $currentDepth + 1, $maxDepth);
            if ($child) {
                $node['children'][] = $child;
            }
        }

        return $node;
    }

    /**
     * Get all deals where this contact is involved
     */
    public function getRelatedDeals()
    {
        if (!$this->load_relationship('mdeal_contacts_deals')) {
            return [];
        }

        $deals = $this->mdeal_contacts_deals->getBeans();
        $dealData = [];

        foreach ($deals as $deal) {
            // Get the relationship data (role, primary contact flag)
            $relationship = $this->mdeal_contacts_deals->getRelationshipObject();
            $relData = $relationship->getRelationshipData($this->id, $deal->id);

            $dealData[] = [
                'deal' => $deal,
                'contact_role' => $relData['contact_role'] ?? '',
                'primary_contact' => $relData['primary_contact'] ?? false
            ];
        }

        return $dealData;
    }

    /**
     * Get all accounts where this contact is associated
     */
    public function getRelatedAccounts()
    {
        if (!$this->load_relationship('mdeal_contacts_accounts')) {
            return [];
        }

        $accounts = $this->mdeal_contacts_accounts->getBeans();
        $accountData = [];

        foreach ($accounts as $account) {
            // Get the relationship data
            $relationship = $this->mdeal_contacts_accounts->getRelationshipObject();
            $relData = $relationship->getRelationshipData($this->id, $account->id);

            $accountData[] = [
                'account' => $account,
                'title' => $relData['title'] ?? $this->title,
                'department' => $relData['department'] ?? $this->department,
                'is_primary' => $relData['is_primary'] ?? false
            ];
        }

        return $accountData;
    }

    /**
     * Add contact to a deal with specific role
     */
    public function addToDeal($dealId, $role = '', $isPrimary = false)
    {
        if (!$this->load_relationship('mdeal_contacts_deals')) {
            return false;
        }

        // Add the relationship
        $this->mdeal_contacts_deals->add($dealId, [
            'contact_role' => $role,
            'primary_contact' => $isPrimary
        ]);

        return true;
    }

    /**
     * Add contact to an account with specific role
     */
    public function addToAccount($accountId, $title = '', $department = '', $isPrimary = false)
    {
        if (!$this->load_relationship('mdeal_contacts_accounts')) {
            return false;
        }

        // Add the relationship
        $this->mdeal_contacts_accounts->add($accountId, [
            'title' => $title ?: $this->title,
            'department' => $department ?: $this->department,
            'is_primary' => $isPrimary
        ]);

        return true;
    }

    /**
     * Get contact's influence score based on various factors
     */
    public function calculateInfluenceScore()
    {
        $score = 0;

        // Decision role weight
        switch ($this->decision_role) {
            case 'decision_maker':
                $score += 40;
                break;
            case 'financial_approver':
                $score += 35;
                break;
            case 'influencer':
                $score += 25;
                break;
            case 'champion':
                $score += 20;
                break;
            case 'technical_evaluator':
                $score += 15;
                break;
            case 'gatekeeper':
                $score += 10;
                break;
            default:
                $score += 5;
        }

        // Influence level weight
        switch ($this->influence_level) {
            case 'high':
                $score += 30;
                break;
            case 'medium':
                $score += 20;
                break;
            case 'low':
                $score += 10;
                break;
        }

        // Relationship strength weight
        switch ($this->relationship_strength) {
            case 'strong':
                $score += 20;
                break;
            case 'good':
                $score += 15;
                break;
            case 'developing':
                $score += 10;
                break;
            case 'weak':
                $score += 5;
                break;
        }

        // Trust level weight (1-10 scale)
        if (!empty($this->trust_level)) {
            $score += $this->trust_level;
        }

        return min(100, $score);
    }

    /**
     * Get days since last interaction
     */
    public function getDaysSinceLastInteraction()
    {
        if (empty($this->last_interaction_date)) {
            return null;
        }

        $lastInteraction = new DateTime($this->last_interaction_date);
        $now = new DateTime();
        $interval = $lastInteraction->diff($now);
        
        return $interval->days;
    }

    /**
     * Check if contact needs follow-up based on interaction history
     */
    public function needsFollowUp($dayThreshold = 30)
    {
        $daysSince = $this->getDaysSinceLastInteraction();
        
        if ($daysSince === null) {
            return true; // Never contacted
        }

        return $daysSince > $dayThreshold;
    }

    /**
     * Get preferred contact information
     */
    public function getPreferredContactInfo()
    {
        switch ($this->preferred_contact_method) {
            case 'email':
                return $this->email_address;
            case 'phone_mobile':
                return $this->phone_mobile;
            case 'phone_work':
                return $this->phone_work;
            case 'text_message':
                return $this->phone_mobile; // SMS to mobile
            default:
                return $this->email_address ?: $this->phone_work ?: $this->phone_mobile;
        }
    }

    /**
     * Override to add custom logic for list queries
     */
    public function create_new_list_query($order_by, $where, $filter = array(), $params = array(), $show_deleted = 0, $join_type = '', $return_array = false, $parentbean = null, $singleSelect = false, $ifListForExport = false)
    {
        // Call parent method
        $ret_array = parent::create_new_list_query($order_by, $where, $filter, $params, $show_deleted, $join_type, true, $parentbean, $singleSelect, $ifListForExport);

        // Add custom select fields for calculated values
        $ret_array['select'] .= ", CONCAT({$this->table_name}.first_name, ' ', {$this->table_name}.last_name) as full_name_calc";
        $ret_array['select'] .= ", DATEDIFF(NOW(), {$this->table_name}.last_interaction_date) as days_since_interaction";

        // Add account name if account_id is present
        $ret_array['from'] .= " LEFT JOIN mdeal_accounts acc ON {$this->table_name}.account_id = acc.id";
        $ret_array['select'] .= ", acc.name as account_name";

        // Add reports-to name
        $ret_array['from'] .= " LEFT JOIN {$this->table_name} reports_to ON {$this->table_name}.reports_to_id = reports_to.id";
        $ret_array['select'] .= ", CONCAT(reports_to.first_name, ' ', reports_to.last_name) as reports_to_name";

        if ($return_array) {
            return $ret_array;
        }

        return $ret_array['select'] . $ret_array['from'] . $ret_array['where'] . $ret_array['order_by'];
    }
}