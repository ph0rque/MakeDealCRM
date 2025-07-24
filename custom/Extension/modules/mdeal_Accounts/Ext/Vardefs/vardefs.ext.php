<?php
/**
 * Variable definitions for mdeal_Accounts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$dictionary['mdeal_Accounts'] = array(
    'table' => 'mdeal_accounts',
    'audited' => true,
    'duplicate_merge' => true,
    'fields' => array(
        // Account classification
        'account_type' => array(
            'name' => 'account_type',
            'vname' => 'LBL_ACCOUNT_TYPE',
            'type' => 'enum',
            'options' => 'account_type_dom',
            'len' => 100,
            'comment' => 'Type of account in M&A context',
            'audited' => true,
            'required' => true,
        ),
        'industry' => array(
            'name' => 'industry',
            'vname' => 'LBL_INDUSTRY',
            'type' => 'enum',
            'options' => 'industry_dom',
            'len' => 100,
            'comment' => 'Industry classification',
            'audited' => true,
        ),
        'sub_industry' => array(
            'name' => 'sub_industry',
            'vname' => 'LBL_SUB_INDUSTRY',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Sub-industry classification',
        ),
        'naics_code' => array(
            'name' => 'naics_code',
            'vname' => 'LBL_NAICS_CODE',
            'type' => 'varchar',
            'len' => 10,
            'comment' => 'NAICS industry classification code',
        ),
        'sic_code' => array(
            'name' => 'sic_code',
            'vname' => 'LBL_SIC_CODE',
            'type' => 'varchar',
            'len' => 10,
            'comment' => 'SIC industry classification code',
        ),

        // Company information
        'ticker_symbol' => array(
            'name' => 'ticker_symbol',
            'vname' => 'LBL_TICKER_SYMBOL',
            'type' => 'varchar',
            'len' => 10,
            'comment' => 'Stock ticker symbol if publicly traded',
        ),
        'ownership_type' => array(
            'name' => 'ownership_type',
            'vname' => 'LBL_OWNERSHIP_TYPE',
            'type' => 'enum',
            'options' => 'ownership_type_dom',
            'len' => 50,
            'comment' => 'Type of ownership structure',
            'audited' => true,
        ),
        'year_established' => array(
            'name' => 'year_established',
            'vname' => 'LBL_YEAR_ESTABLISHED',
            'type' => 'int',
            'comment' => 'Year company was established',
        ),
        'dba_name' => array(
            'name' => 'dba_name',
            'vname' => 'LBL_DBA_NAME',
            'type' => 'varchar',
            'len' => 150,
            'comment' => 'Doing Business As name',
        ),
        'tax_id' => array(
            'name' => 'tax_id',
            'vname' => 'LBL_TAX_ID',
            'type' => 'varchar',
            'len' => 20,
            'comment' => 'Tax ID / EIN (encrypted)',
            'audited' => true,
        ),
        'duns_number' => array(
            'name' => 'duns_number',
            'vname' => 'LBL_DUNS_NUMBER',
            'type' => 'varchar',
            'len' => 20,
            'comment' => 'Dun & Bradstreet DUNS number',
        ),

        // Financial information
        'annual_revenue' => array(
            'name' => 'annual_revenue',
            'vname' => 'LBL_ANNUAL_REVENUE',
            'type' => 'decimal',
            'precision' => 26,
            'scale' => 6,
            'comment' => 'Latest annual revenue',
            'audited' => true,
        ),
        'revenue_currency_id' => array(
            'name' => 'revenue_currency_id',
            'vname' => 'LBL_REVENUE_CURRENCY',
            'type' => 'id',
            'comment' => 'Currency for revenue figures',
        ),
        'ebitda' => array(
            'name' => 'ebitda',
            'vname' => 'LBL_EBITDA',
            'type' => 'decimal',
            'precision' => 26,
            'scale' => 6,
            'comment' => 'Latest EBITDA',
            'audited' => true,
        ),
        'employee_count' => array(
            'name' => 'employee_count',
            'vname' => 'LBL_EMPLOYEE_COUNT',
            'type' => 'int',
            'comment' => 'Number of employees',
        ),
        'facility_count' => array(
            'name' => 'facility_count',
            'vname' => 'LBL_FACILITY_COUNT',
            'type' => 'int',
            'comment' => 'Number of facilities/locations',
        ),

        // Hierarchical structure
        'parent_id' => array(
            'name' => 'parent_id',
            'vname' => 'LBL_PARENT_ACCOUNT_ID',
            'type' => 'id',
            'comment' => 'Parent company ID',
        ),
        'parent_name' => array(
            'name' => 'parent_name',
            'rname' => 'name',
            'id_name' => 'parent_id',
            'vname' => 'LBL_PARENT_ACCOUNT',
            'type' => 'relate',
            'table' => 'mdeal_accounts',
            'isnull' => 'true',
            'module' => 'mdeal_Accounts',
            'dbType' => 'varchar',
            'link' => 'parent_account_link',
            'len' => 255,
            'source' => 'non-db',
            'comment' => 'Parent company name',
        ),
        'is_parent' => array(
            'name' => 'is_parent',
            'vname' => 'LBL_IS_PARENT',
            'type' => 'bool',
            'default' => '0',
            'comment' => 'Is this a parent company',
            'readonly' => true,
        ),
        'hierarchy_level' => array(
            'name' => 'hierarchy_level',
            'vname' => 'LBL_HIERARCHY_LEVEL',
            'type' => 'int',
            'default' => 0,
            'comment' => 'Level in company hierarchy',
            'readonly' => true,
        ),

        // Deal-related fields
        'rating' => array(
            'name' => 'rating',
            'vname' => 'LBL_RATING',
            'type' => 'enum',
            'options' => 'account_rating_dom',
            'len' => 20,
            'comment' => 'Account rating for deal potential',
            'audited' => true,
        ),
        'account_status' => array(
            'name' => 'account_status',
            'vname' => 'LBL_ACCOUNT_STATUS',
            'type' => 'enum',
            'options' => 'account_status_dom',
            'len' => 50,
            'default' => 'active',
            'comment' => 'Current account status',
            'audited' => true,
        ),
        'deal_count' => array(
            'name' => 'deal_count',
            'vname' => 'LBL_DEAL_COUNT',
            'type' => 'int',
            'default' => 0,
            'comment' => 'Number of related deals',
            'readonly' => true,
        ),
        'total_deal_value' => array(
            'name' => 'total_deal_value',
            'vname' => 'LBL_TOTAL_DEAL_VALUE',
            'type' => 'decimal',
            'precision' => 26,
            'scale' => 6,
            'comment' => 'Sum of all deal values',
            'readonly' => true,
        ),
        'last_deal_date' => array(
            'name' => 'last_deal_date',
            'vname' => 'LBL_LAST_DEAL_DATE',
            'type' => 'date',
            'comment' => 'Date of most recent deal',
            'readonly' => true,
        ),

        // Compliance & risk
        'credit_rating' => array(
            'name' => 'credit_rating',
            'vname' => 'LBL_CREDIT_RATING',
            'type' => 'varchar',
            'len' => 10,
            'comment' => 'Credit score/rating',
        ),
        'credit_limit' => array(
            'name' => 'credit_limit',
            'vname' => 'LBL_CREDIT_LIMIT',
            'type' => 'decimal',
            'precision' => 26,
            'scale' => 6,
            'comment' => 'Credit limit for transactions',
        ),
        'payment_terms' => array(
            'name' => 'payment_terms',
            'vname' => 'LBL_PAYMENT_TERMS',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Standard payment terms (Net 30, etc.)',
        ),
        'risk_assessment' => array(
            'name' => 'risk_assessment',
            'vname' => 'LBL_RISK_ASSESSMENT',
            'type' => 'enum',
            'options' => 'risk_assessment_dom',
            'len' => 20,
            'comment' => 'Risk level assessment',
            'audited' => true,
        ),
        'compliance_status' => array(
            'name' => 'compliance_status',
            'vname' => 'LBL_COMPLIANCE_STATUS',
            'type' => 'enum',
            'options' => 'compliance_status_dom',
            'len' => 50,
            'comment' => 'Compliance verification status',
            'audited' => true,
        ),
        'insurance_coverage' => array(
            'name' => 'insurance_coverage',
            'vname' => 'LBL_INSURANCE_COVERAGE',
            'type' => 'bool',
            'default' => '0',
            'comment' => 'Has adequate insurance coverage',
        ),
        'insurance_expiry' => array(
            'name' => 'insurance_expiry',
            'vname' => 'LBL_INSURANCE_EXPIRY',
            'type' => 'date',
            'comment' => 'Insurance policy expiration date',
        ),

        // Portfolio-specific fields
        'acquisition_date' => array(
            'name' => 'acquisition_date',
            'vname' => 'LBL_ACQUISITION_DATE',
            'type' => 'date',
            'comment' => 'Date when company was acquired',
            'audited' => true,
        ),
        'acquisition_price' => array(
            'name' => 'acquisition_price',
            'vname' => 'LBL_ACQUISITION_PRICE',
            'type' => 'decimal',
            'precision' => 26,
            'scale' => 6,
            'comment' => 'Purchase price when acquired',
            'audited' => true,
        ),
        'current_valuation' => array(
            'name' => 'current_valuation',
            'vname' => 'LBL_CURRENT_VALUATION',
            'type' => 'decimal',
            'precision' => 26,
            'scale' => 6,
            'comment' => 'Current estimated valuation',
            'audited' => true,
        ),
        'exit_strategy' => array(
            'name' => 'exit_strategy',
            'vname' => 'LBL_EXIT_STRATEGY',
            'type' => 'enum',
            'options' => 'exit_strategy_dom',
            'len' => 50,
            'comment' => 'Planned exit strategy',
            'audited' => true,
        ),
        'planned_exit_date' => array(
            'name' => 'planned_exit_date',
            'vname' => 'LBL_PLANNED_EXIT_DATE',
            'type' => 'date',
            'comment' => 'Target exit date',
        ),
        'integration_status' => array(
            'name' => 'integration_status',
            'vname' => 'LBL_INTEGRATION_STATUS',
            'type' => 'enum',
            'options' => 'integration_status_dom',
            'len' => 50,
            'comment' => 'Post-acquisition integration status',
            'audited' => true,
        ),

        // Address override for billing/shipping
        'same_as_billing' => array(
            'name' => 'same_as_billing',
            'vname' => 'LBL_SAME_AS_BILLING',
            'type' => 'bool',
            'default' => '1',
            'comment' => 'Shipping address same as billing',
        ),
    ),

    'relationships' => array(
        // Hierarchical relationship (self-referencing)
        'mdeal_accounts_parent' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Accounts',
            'rhs_table' => 'mdeal_accounts',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many'
        ),

        // Contacts relationships
        'mdeal_accounts_contacts' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Contacts',
            'rhs_table' => 'mdeal_contacts',
            'rhs_key' => 'account_id',
            'relationship_type' => 'one-to-many'
        ),

        // Many-to-many contacts for complex relationships
        'mdeal_contacts_accounts' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Contacts',
            'rhs_table' => 'mdeal_contacts',
            'rhs_key' => 'id',
            'relationship_type' => 'many-to-many',
            'join_table' => 'mdeal_contacts_accounts',
            'join_key_lhs' => 'account_id',
            'join_key_rhs' => 'contact_id',
            'relationship_fields' => array(
                'title' => array(
                    'name' => 'title',
                    'type' => 'varchar',
                    'len' => 100
                ),
                'department' => array(
                    'name' => 'department',
                    'type' => 'varchar',
                    'len' => 100
                ),
                'is_primary' => array(
                    'name' => 'is_primary',
                    'type' => 'bool',
                    'default' => '0'
                )
            )
        ),

        // Deals relationship
        'mdeal_accounts_deals' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Deals',
            'rhs_table' => 'mdeal_deals',
            'rhs_key' => 'account_id',
            'relationship_type' => 'one-to-many'
        ),

        // Leads relationship
        'mdeal_accounts_leads' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Leads',
            'rhs_table' => 'mdeal_leads',
            'rhs_key' => 'account_id',
            'relationship_type' => 'one-to-many'
        ),

        // Standard SuiteCRM relationships
        'mdeal_accounts_assigned_user' => array(
            'lhs_module' => 'Users',
            'lhs_table' => 'users',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Accounts',
            'rhs_table' => 'mdeal_accounts',
            'rhs_key' => 'assigned_user_id',
            'relationship_type' => 'one-to-many'
        ),
        'mdeal_accounts_modified_user' => array(
            'lhs_module' => 'Users',
            'lhs_table' => 'users',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Accounts',
            'rhs_table' => 'mdeal_accounts',
            'rhs_key' => 'modified_user_id',
            'relationship_type' => 'one-to-many'
        ),
        'mdeal_accounts_created_by' => array(
            'lhs_module' => 'Users',
            'lhs_table' => 'users',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Accounts',
            'rhs_table' => 'mdeal_accounts',
            'rhs_key' => 'created_by',
            'relationship_type' => 'one-to-many'
        ),

        // Activities relationships
        'mdeal_accounts_activities' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'Activities',
            'rhs_table' => 'activities',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Accounts'
        ),
        'mdeal_accounts_calls' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'Calls',
            'rhs_table' => 'calls',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Accounts'
        ),
        'mdeal_accounts_meetings' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'Meetings',
            'rhs_table' => 'meetings',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Accounts'
        ),
        'mdeal_accounts_tasks' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'Tasks',
            'rhs_table' => 'tasks',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Accounts'
        ),
        'mdeal_accounts_notes' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'Notes',
            'rhs_table' => 'notes',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Accounts'
        ),
        'mdeal_accounts_emails' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'Emails',
            'rhs_table' => 'emails',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Accounts'
        ),

        // Documents relationship
        'mdeal_accounts_documents' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'Documents',
            'rhs_table' => 'documents',
            'rhs_key' => 'id',
            'relationship_type' => 'many-to-many',
            'join_table' => 'mdeal_accounts_documents',
            'join_key_lhs' => 'account_id',
            'join_key_rhs' => 'document_id'
        ),

        // Contracts relationship
        'mdeal_accounts_contracts' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'Contracts',
            'rhs_table' => 'contracts',
            'rhs_key' => 'account_id',
            'relationship_type' => 'one-to-many'
        ),
    ),

    'indices' => array(
        array(
            'name' => 'idx_mdeal_accounts_name',
            'type' => 'index',
            'fields' => array('name', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_accounts_type',
            'type' => 'index',
            'fields' => array('account_type', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_accounts_industry',
            'type' => 'index',
            'fields' => array('industry', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_accounts_assigned',
            'type' => 'index',
            'fields' => array('assigned_user_id', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_accounts_parent',
            'type' => 'index',
            'fields' => array('parent_id', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_accounts_status',
            'type' => 'index',
            'fields' => array('account_status', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_accounts_website',
            'type' => 'index',
            'fields' => array('website')
        ),
        array(
            'name' => 'idx_mdeal_accounts_tax_id',
            'type' => 'index',
            'fields' => array('tax_id')
        ),
        array(
            'name' => 'idx_mdeal_accounts_revenue',
            'type' => 'index',
            'fields' => array('annual_revenue', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_accounts_rating',
            'type' => 'index',
            'fields' => array('rating', 'account_status')
        ),
    ),
);

VardefManager::createVardef('mdeal_Accounts', 'mdeal_Accounts', array('company', 'assignable', 'security_groups'));