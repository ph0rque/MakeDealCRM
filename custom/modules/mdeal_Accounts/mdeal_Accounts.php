<?php
/**
 * MakeDeal CRM Accounts Module
 * 
 * This module manages companies and organizations involved in the M&A process.
 * Includes target companies, portfolio companies, brokers, lenders, law firms,
 * and other business entities. Supports hierarchical relationships and complex
 * organizational structures with financial tracking.
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class mdeal_Accounts extends Company
{
    public $new_schema = true;
    public $module_dir = 'mdeal_Accounts';
    public $object_name = 'mdeal_Accounts';
    public $table_name = 'mdeal_accounts';
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

    // Company fields (inherited)
    public $website;
    public $phone_office;
    public $phone_alternate;
    public $phone_fax;
    public $email;
    
    // Account classification
    public $account_type;
    public $industry;
    public $sub_industry;
    public $naics_code;
    public $sic_code;
    
    // Company information
    public $ticker_symbol;
    public $ownership_type;
    public $year_established;
    public $dba_name;
    public $tax_id;
    public $duns_number;
    
    // Financial information
    public $annual_revenue;
    public $revenue_currency_id;
    public $ebitda;
    public $employee_count;
    public $facility_count;
    
    // Hierarchical structure
    public $parent_id;
    public $parent_name;
    public $is_parent;
    public $hierarchy_level;
    
    // Address information
    public $billing_address_street;
    public $billing_address_city;
    public $billing_address_state;
    public $billing_address_postalcode;
    public $billing_address_country;
    public $shipping_address_street;
    public $shipping_address_city;
    public $shipping_address_state;
    public $shipping_address_postalcode;
    public $shipping_address_country;
    public $same_as_billing;
    
    // Deal-related fields
    public $rating;
    public $account_status;
    public $deal_count;
    public $total_deal_value;
    public $last_deal_date;
    
    // Compliance & risk
    public $credit_rating;
    public $credit_limit;
    public $payment_terms;
    public $risk_assessment;
    public $compliance_status;
    public $insurance_coverage;
    public $insurance_expiry;
    
    // Portfolio-specific fields
    public $acquisition_date;
    public $acquisition_price;
    public $current_valuation;
    public $exit_strategy;
    public $planned_exit_date;
    public $integration_status;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns the summary text that should show up on an account's summary listing.
     */
    public function get_summary_text()
    {
        return $this->name;
    }

    /**
     * Save override to handle hierarchy calculations and validations
     */
    public function save($check_notify = false)
    {
        // Validate hierarchy to prevent circular references
        if (!empty($this->parent_id) && !$this->validateHierarchy()) {
            throw new Exception('Circular reference detected in account hierarchy');
        }

        // Calculate hierarchy level
        $this->calculateHierarchyLevel();

        // Update is_parent flag
        $this->updateParentFlag();

        // Calculate deal metrics
        $this->calculateDealMetrics();

        // Set financial ratios
        $this->calculateFinancialRatios();

        return parent::save($check_notify);
    }

    /**
     * Validate hierarchy to prevent circular references
     */
    protected function validateHierarchy()
    {
        if (empty($this->parent_id) || $this->parent_id == $this->id) {
            return true; // No hierarchy or self-reference (which we'll prevent)
        }

        // Check for circular reference by walking up the hierarchy
        $currentId = $this->parent_id;
        $visited = [$this->id];
        $maxDepth = 20; // Prevent infinite loops
        $depth = 0;

        while ($currentId && $depth < $maxDepth) {
            if (in_array($currentId, $visited)) {
                return false; // Circular reference detected
            }

            $visited[] = $currentId;

            // Get the next level up
            $account = BeanFactory::getBean('mdeal_Accounts', $currentId);
            if (!$account || empty($account->parent_id)) {
                break;
            }

            $currentId = $account->parent_id;
            $depth++;
        }

        return true;
    }

    /**
     * Calculate hierarchy level based on parent chain
     */
    protected function calculateHierarchyLevel()
    {
        if (empty($this->parent_id)) {
            $this->hierarchy_level = 0;
            return;
        }

        $level = 1;
        $currentId = $this->parent_id;
        $maxDepth = 20;

        while ($currentId && $level < $maxDepth) {
            $parent = BeanFactory::getBean('mdeal_Accounts', $currentId);
            if (!$parent || empty($parent->parent_id)) {
                break;
            }
            $currentId = $parent->parent_id;
            $level++;
        }

        $this->hierarchy_level = $level;
    }

    /**
     * Update is_parent flag based on children
     */
    protected function updateParentFlag()
    {
        if (empty($this->id)) {
            $this->is_parent = false;
            return;
        }

        $childCount = $this->getChildrenCount();
        $this->is_parent = $childCount > 0;
    }

    /**
     * Calculate deal-related metrics
     */
    protected function calculateDealMetrics()
    {
        if (empty($this->id)) {
            return;
        }

        // This would query deals related to this account
        $query = "SELECT COUNT(*) as deal_count, 
                         SUM(deal_value) as total_value,
                         MAX(date_modified) as last_deal
                  FROM mdeal_deals 
                  WHERE account_id = ? AND deleted = 0";
        
        try {
            $result = $this->db->pQuery($query, [$this->id]);
            $row = $this->db->fetchByAssoc($result);
            
            $this->deal_count = $row['deal_count'] ?? 0;
            $this->total_deal_value = $row['total_value'] ?? 0;
            $this->last_deal_date = $row['last_deal'] ?? null;
        } catch (Exception $e) {
            $GLOBALS['log']->error("Error calculating deal metrics: " . $e->getMessage());
        }
    }

    /**
     * Calculate financial ratios and metrics
     */
    protected function calculateFinancialRatios()
    {
        if (empty($this->annual_revenue) || empty($this->ebitda)) {
            return;
        }

        // Calculate EBITDA margin
        $ebitdaMargin = ($this->ebitda / $this->annual_revenue) * 100;
        
        // Store calculated ratios (would need additional fields in real implementation)
        $GLOBALS['log']->info("Account {$this->name} EBITDA margin: {$ebitdaMargin}%");
    }

    /**
     * Get all direct children of this account
     */
    public function getDirectChildren()
    {
        $query = "SELECT id, name, account_type, annual_revenue, employee_count 
                  FROM {$this->table_name} 
                  WHERE parent_id = ? AND deleted = 0 
                  ORDER BY name";
        
        $result = $this->db->pQuery($query, [$this->id]);
        $children = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $children[] = $row;
        }
        
        return $children;
    }

    /**
     * Get count of direct children
     */
    public function getChildrenCount()
    {
        $query = "SELECT COUNT(*) as count FROM {$this->table_name} 
                  WHERE parent_id = ? AND deleted = 0";
        
        $result = $this->db->pQuery($query, [$this->id]);
        $row = $this->db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }

    /**
     * Get complete organizational chart starting from this account
     */
    public function getOrganizationChart($maxDepth = 5)
    {
        return $this->buildOrgChart($this->id, 0, $maxDepth);
    }

    /**
     * Recursively build organization chart
     */
    protected function buildOrgChart($accountId, $currentDepth, $maxDepth)
    {
        if ($currentDepth >= $maxDepth) {
            return null;
        }

        $account = BeanFactory::getBean('mdeal_Accounts', $accountId);
        if (!$account) {
            return null;
        }

        $node = [
            'id' => $account->id,
            'name' => $account->name,
            'account_type' => $account->account_type,
            'industry' => $account->industry,
            'annual_revenue' => $account->annual_revenue,
            'employee_count' => $account->employee_count,
            'children' => []
        ];

        // Get direct children
        $children = $account->getDirectChildren();
        foreach ($children as $child) {
            $childNode = $this->buildOrgChart($child['id'], $currentDepth + 1, $maxDepth);
            if ($childNode) {
                $node['children'][] = $childNode;
            }
        }

        return $node;
    }

    /**
     * Get all accounts in the same hierarchy tree
     */
    public function getHierarchyTree()
    {
        // Find the root of this hierarchy
        $root = $this->findHierarchyRoot();
        
        // Build complete tree from root
        return $this->buildOrgChart($root->id, 0, 10);
    }

    /**
     * Find the root account of this hierarchy
     */
    protected function findHierarchyRoot()
    {
        $current = $this;
        $maxDepth = 20;
        $depth = 0;

        while ($current->parent_id && $depth < $maxDepth) {
            $parent = BeanFactory::getBean('mdeal_Accounts', $current->parent_id);
            if (!$parent) {
                break;
            }
            $current = $parent;
            $depth++;
        }

        return $current;
    }

    /**
     * Calculate portfolio metrics for portfolio companies
     */
    public function calculatePortfolioMetrics()
    {
        if ($this->account_type !== 'portfolio_company') {
            return null;
        }

        $metrics = [
            'acquisition_multiple' => null,
            'current_multiple' => null,
            'value_creation' => null,
            'irr' => null,
            'holding_period_days' => null
        ];

        if (!empty($this->acquisition_price) && !empty($this->annual_revenue)) {
            $metrics['acquisition_multiple'] = $this->acquisition_price / $this->annual_revenue;
        }

        if (!empty($this->current_valuation) && !empty($this->annual_revenue)) {
            $metrics['current_multiple'] = $this->current_valuation / $this->annual_revenue;
        }

        if (!empty($this->current_valuation) && !empty($this->acquisition_price)) {
            $metrics['value_creation'] = $this->current_valuation - $this->acquisition_price;
        }

        if (!empty($this->acquisition_date)) {
            $acquisitionDate = new DateTime($this->acquisition_date);
            $now = new DateTime();
            $metrics['holding_period_days'] = $acquisitionDate->diff($now)->days;
        }

        return $metrics;
    }

    /**
     * Get related deals with this account
     */
    public function getRelatedDeals()
    {
        if (!$this->load_relationship('mdeal_accounts_deals')) {
            return [];
        }

        $deals = $this->mdeal_accounts_deals->getBeans();
        $dealData = [];

        foreach ($deals as $deal) {
            $dealData[] = [
                'deal' => $deal,
                'relationship_type' => 'primary_account' // Could be enhanced with relationship metadata
            ];
        }

        return $dealData;
    }

    /**
     * Get all contacts associated with this account
     */
    public function getRelatedContacts()
    {
        // Get directly assigned contacts
        $directContacts = [];
        if ($this->load_relationship('mdeal_contacts_account')) {
            $directContacts = $this->mdeal_contacts_account->getBeans();
        }

        // Get contacts through many-to-many relationship
        $relatedContacts = [];
        if ($this->load_relationship('mdeal_contacts_accounts')) {
            $relatedContacts = $this->mdeal_contacts_accounts->getBeans();
        }

        return [
            'direct' => $directContacts,
            'related' => $relatedContacts
        ];
    }

    /**
     * Add account to deal relationship
     */
    public function addToDeal($dealId, $relationshipType = 'target')
    {
        if (!$this->load_relationship('mdeal_accounts_deals')) {
            return false;
        }

        $this->mdeal_accounts_deals->add($dealId, [
            'relationship_type' => $relationshipType
        ]);

        return true;
    }

    /**
     * Calculate account health score
     */
    public function calculateHealthScore()
    {
        $score = 0;
        $maxScore = 100;

        // Financial health (40 points)
        if (!empty($this->annual_revenue)) {
            $score += 20; // Has revenue data
            
            if ($this->annual_revenue > 10000000) { // > $10M
                $score += 10;
            } elseif ($this->annual_revenue > 1000000) { // > $1M
                $score += 5;
            }
            
            if (!empty($this->ebitda) && $this->ebitda > 0) {
                $ebitdaMargin = ($this->ebitda / $this->annual_revenue) * 100;
                if ($ebitdaMargin > 15) {
                    $score += 10;
                } elseif ($ebitdaMargin > 5) {
                    $score += 5;
                }
            }
        }

        // Contact coverage (20 points)
        $contactCount = $this->getContactCount();
        if ($contactCount > 5) {
            $score += 20;
        } elseif ($contactCount > 2) {
            $score += 15;
        } elseif ($contactCount > 0) {
            $score += 10;
        }

        // Deal activity (20 points)
        if (!empty($this->deal_count)) {
            if ($this->deal_count > 3) {
                $score += 20;
            } elseif ($this->deal_count > 1) {
                $score += 15;
            } else {
                $score += 10;
            }
        }

        // Data completeness (20 points)
        $requiredFields = ['industry', 'employee_count', 'website', 'phone_office'];
        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($this->$field)) {
                $completedFields++;
            }
        }
        $score += ($completedFields / count($requiredFields)) * 20;

        return min($maxScore, $score);
    }

    /**
     * Get contact count for this account
     */
    protected function getContactCount()
    {
        $query = "SELECT COUNT(*) as count FROM mdeal_contacts 
                  WHERE account_id = ? AND deleted = 0";
        
        $result = $this->db->pQuery($query, [$this->id]);
        $row = $this->db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }

    /**
     * Check if account needs attention
     */
    public function needsAttention($dayThreshold = 90)
    {
        // Check last deal activity
        if (!empty($this->last_deal_date)) {
            $lastDeal = new DateTime($this->last_deal_date);
            $now = new DateTime();
            $daysSince = $lastDeal->diff($now)->days;
            
            if ($daysSince > $dayThreshold) {
                return [
                    'needs_attention' => true,
                    'reason' => 'No recent deal activity',
                    'days_since_activity' => $daysSince
                ];
            }
        }

        // Check data completeness
        $healthScore = $this->calculateHealthScore();
        if ($healthScore < 50) {
            return [
                'needs_attention' => true,
                'reason' => 'Low data completeness',
                'health_score' => $healthScore
            ];
        }

        return ['needs_attention' => false];
    }

    /**
     * Override to add custom logic for list queries
     */
    public function create_new_list_query($order_by, $where, $filter = array(), $params = array(), $show_deleted = 0, $join_type = '', $return_array = false, $parentbean = null, $singleSelect = false, $ifListForExport = false)
    {
        // Call parent method
        $ret_array = parent::create_new_list_query($order_by, $where, $filter, $params, $show_deleted, $join_type, true, $parentbean, $singleSelect, $ifListForExport);

        // Add custom select fields for calculated values
        $ret_array['select'] .= ", (SELECT COUNT(*) FROM mdeal_contacts c WHERE c.account_id = {$this->table_name}.id AND c.deleted = 0) as contact_count";
        $ret_array['select'] .= ", (SELECT COUNT(*) FROM {$this->table_name} children WHERE children.parent_id = {$this->table_name}.id AND children.deleted = 0) as child_count";

        // Add parent name
        $ret_array['from'] .= " LEFT JOIN {$this->table_name} parent_account ON {$this->table_name}.parent_id = parent_account.id";
        $ret_array['select'] .= ", parent_account.name as parent_name";

        if ($return_array) {
            return $ret_array;
        }

        return $ret_array['select'] . $ret_array['from'] . $ret_array['where'] . $ret_array['order_by'];
    }
}