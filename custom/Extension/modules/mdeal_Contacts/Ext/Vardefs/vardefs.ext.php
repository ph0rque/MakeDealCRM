<?php
/**
 * Variable definitions for mdeal_Contacts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$dictionary['mdeal_Contacts'] = array(
    'table' => 'mdeal_contacts',
    'audited' => true,
    'duplicate_merge' => true,
    'fields' => array(
        // Contact-specific fields
        'contact_type' => array(
            'name' => 'contact_type',
            'vname' => 'LBL_CONTACT_TYPE',
            'type' => 'enum',
            'options' => 'contact_type_dom',
            'len' => 100,
            'comment' => 'Type of contact in deal context',
            'audited' => true,
        ),
        'contact_subtype' => array(
            'name' => 'contact_subtype',
            'vname' => 'LBL_CONTACT_SUBTYPE',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Further classification of contact type',
        ),
        'account_id' => array(
            'name' => 'account_id',
            'vname' => 'LBL_ACCOUNT_ID',
            'type' => 'id',
            'comment' => 'Primary account/company ID',
        ),
        'account_name' => array(
            'name' => 'account_name',
            'rname' => 'name',
            'id_name' => 'account_id',
            'vname' => 'LBL_ACCOUNT_NAME',
            'type' => 'relate',
            'table' => 'mdeal_accounts',
            'isnull' => 'true',
            'module' => 'mdeal_Accounts',
            'dbType' => 'varchar',
            'link' => 'account_link',
            'len' => 255,
            'source' => 'non-db',
            'comment' => 'Primary account/company name',
        ),
        'reports_to_id' => array(
            'name' => 'reports_to_id',
            'vname' => 'LBL_REPORTS_TO_ID',
            'type' => 'id',
            'comment' => 'Manager/supervisor contact ID',
        ),
        'reports_to_name' => array(
            'name' => 'reports_to_name',
            'rname' => 'full_name',
            'id_name' => 'reports_to_id',
            'vname' => 'LBL_REPORTS_TO',
            'type' => 'relate',
            'table' => 'mdeal_contacts',
            'isnull' => 'true',
            'module' => 'mdeal_Contacts',
            'dbType' => 'varchar',
            'link' => 'reports_to_link',
            'len' => 255,
            'source' => 'non-db',
            'comment' => 'Manager/supervisor name',
        ),
        'lead_source' => array(
            'name' => 'lead_source',
            'vname' => 'LBL_LEAD_SOURCE',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'How contact was acquired',
        ),
        'linkedin_url' => array(
            'name' => 'linkedin_url',
            'vname' => 'LBL_LINKEDIN_URL',
            'type' => 'url',
            'dbType' => 'varchar',
            'len' => 255,
            'comment' => 'LinkedIn profile URL',
        ),

        // Deal-specific fields
        'preferred_contact_method' => array(
            'name' => 'preferred_contact_method',
            'vname' => 'LBL_PREFERRED_CONTACT_METHOD',
            'type' => 'enum',
            'options' => 'contact_method_dom',
            'len' => 50,
            'comment' => 'Preferred method of contact',
        ),
        'best_time_to_contact' => array(
            'name' => 'best_time_to_contact',
            'vname' => 'LBL_BEST_TIME_TO_CONTACT',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Best time to contact',
        ),
        'timezone' => array(
            'name' => 'timezone',
            'vname' => 'LBL_TIMEZONE',
            'type' => 'varchar',
            'len' => 50,
            'comment' => 'Contact timezone',
        ),
        'communication_style' => array(
            'name' => 'communication_style',
            'vname' => 'LBL_COMMUNICATION_STYLE',
            'type' => 'text',
            'comment' => 'Notes on communication preferences and style',
        ),
        'decision_role' => array(
            'name' => 'decision_role',
            'vname' => 'LBL_DECISION_ROLE',
            'type' => 'enum',
            'options' => 'decision_role_dom',
            'len' => 50,
            'comment' => 'Role in decision making process',
            'audited' => true,
        ),
        'influence_level' => array(
            'name' => 'influence_level',
            'vname' => 'LBL_INFLUENCE_LEVEL',
            'type' => 'enum',
            'options' => 'influence_level_dom',
            'len' => 20,
            'comment' => 'Level of influence in organization',
            'audited' => true,
        ),

        // Relationship tracking
        'relationship_strength' => array(
            'name' => 'relationship_strength',
            'vname' => 'LBL_RELATIONSHIP_STRENGTH',
            'type' => 'enum',
            'options' => 'relationship_strength_dom',
            'len' => 20,
            'comment' => 'Strength of business relationship',
            'audited' => true,
        ),
        'last_interaction_date' => array(
            'name' => 'last_interaction_date',
            'vname' => 'LBL_LAST_INTERACTION_DATE',
            'type' => 'datetime',
            'comment' => 'Date of last interaction',
            'readonly' => true,
        ),
        'interaction_count' => array(
            'name' => 'interaction_count',
            'vname' => 'LBL_INTERACTION_COUNT',
            'type' => 'int',
            'default' => 0,
            'comment' => 'Total number of interactions',
            'readonly' => true,
        ),
        'response_rate' => array(
            'name' => 'response_rate',
            'vname' => 'LBL_RESPONSE_RATE',
            'type' => 'decimal',
            'precision' => 5,
            'scale' => 2,
            'comment' => 'Email response rate percentage',
            'readonly' => true,
        ),
        'trust_level' => array(
            'name' => 'trust_level',
            'vname' => 'LBL_TRUST_LEVEL',
            'type' => 'int',
            'comment' => 'Trust level on 1-10 scale',
            'validation' => array(
                'type' => 'range',
                'min' => 1,
                'max' => 10
            ),
        ),

        // Additional fields
        'confidentiality_agreement' => array(
            'name' => 'confidentiality_agreement',
            'vname' => 'LBL_CONFIDENTIALITY_AGREEMENT',
            'type' => 'bool',
            'default' => '0',
            'comment' => 'Has signed confidentiality agreement',
        ),
        'background_check_completed' => array(
            'name' => 'background_check_completed',
            'vname' => 'LBL_BACKGROUND_CHECK_COMPLETED',
            'type' => 'bool',
            'default' => '0',
            'comment' => 'Background check completed',
        ),
        'background_check_date' => array(
            'name' => 'background_check_date',
            'vname' => 'LBL_BACKGROUND_CHECK_DATE',
            'type' => 'date',
            'comment' => 'Date background check was completed',
        ),
        'notes_private' => array(
            'name' => 'notes_private',
            'vname' => 'LBL_NOTES_PRIVATE',
            'type' => 'text',
            'comment' => 'Private internal notes',
        ),
    ),

    'relationships' => array(
        // Primary account relationship
        'mdeal_contacts_account' => array(
            'lhs_module' => 'mdeal_Accounts',
            'lhs_table' => 'mdeal_accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Contacts',
            'rhs_table' => 'mdeal_contacts',
            'rhs_key' => 'account_id',
            'relationship_type' => 'one-to-many'
        ),

        // Reports-to relationship
        'mdeal_contacts_reports_to' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Contacts',
            'rhs_table' => 'mdeal_contacts',
            'rhs_key' => 'reports_to_id',
            'relationship_type' => 'one-to-many'
        ),

        // Many-to-many with Deals
        'mdeal_contacts_deals' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Deals',
            'rhs_table' => 'mdeal_deals',
            'rhs_key' => 'id',
            'relationship_type' => 'many-to-many',
            'join_table' => 'mdeal_contacts_deals',
            'join_key_lhs' => 'contact_id',
            'join_key_rhs' => 'deal_id',
            'relationship_fields' => array(
                'contact_role' => array(
                    'name' => 'contact_role',
                    'type' => 'varchar',
                    'len' => 100
                ),
                'primary_contact' => array(
                    'name' => 'primary_contact',
                    'type' => 'bool',
                    'default' => '0'
                )
            )
        ),

        // Many-to-many with Accounts
        'mdeal_contacts_accounts' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Accounts',
            'rhs_table' => 'mdeal_accounts',
            'rhs_key' => 'id',
            'relationship_type' => 'many-to-many',
            'join_table' => 'mdeal_contacts_accounts',
            'join_key_lhs' => 'contact_id',
            'join_key_rhs' => 'account_id',
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

        // Standard SuiteCRM relationships
        'mdeal_contacts_assigned_user' => array(
            'lhs_module' => 'Users',
            'lhs_table' => 'users',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Contacts',
            'rhs_table' => 'mdeal_contacts',
            'rhs_key' => 'assigned_user_id',
            'relationship_type' => 'one-to-many'
        ),
        'mdeal_contacts_modified_user' => array(
            'lhs_module' => 'Users',
            'lhs_table' => 'users',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Contacts',
            'rhs_table' => 'mdeal_contacts',
            'rhs_key' => 'modified_user_id',
            'relationship_type' => 'one-to-many'
        ),
        'mdeal_contacts_created_by' => array(
            'lhs_module' => 'Users',
            'lhs_table' => 'users',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Contacts',
            'rhs_table' => 'mdeal_contacts',
            'rhs_key' => 'created_by',
            'relationship_type' => 'one-to-many'
        ),

        // Activities relationships
        'mdeal_contacts_activities' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'Activities',
            'rhs_table' => 'activities',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Contacts'
        ),
        'mdeal_contacts_calls' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'Calls',
            'rhs_table' => 'calls',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Contacts'
        ),
        'mdeal_contacts_meetings' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'Meetings',
            'rhs_table' => 'meetings',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Contacts'
        ),
        'mdeal_contacts_tasks' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'Tasks',
            'rhs_table' => 'tasks',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Contacts'
        ),
        'mdeal_contacts_notes' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'Notes',
            'rhs_table' => 'notes',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Contacts'
        ),
        'mdeal_contacts_emails' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'Emails',
            'rhs_table' => 'emails',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Contacts'
        ),

        // Documents relationship
        'mdeal_contacts_documents' => array(
            'lhs_module' => 'mdeal_Contacts',
            'lhs_table' => 'mdeal_contacts',
            'lhs_key' => 'id',
            'rhs_module' => 'Documents',
            'rhs_table' => 'documents',
            'rhs_key' => 'id',
            'relationship_type' => 'many-to-many',
            'join_table' => 'mdeal_contacts_documents',
            'join_key_lhs' => 'contact_id',
            'join_key_rhs' => 'document_id'
        ),
    ),

    'indices' => array(
        array(
            'name' => 'idx_mdeal_contacts_name',
            'type' => 'index',
            'fields' => array('last_name', 'first_name', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_contacts_assigned',
            'type' => 'index',
            'fields' => array('assigned_user_id', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_contacts_email',
            'type' => 'index',
            'fields' => array('email_address')
        ),
        array(
            'name' => 'idx_mdeal_contacts_account',
            'type' => 'index',
            'fields' => array('account_id', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_contacts_type',
            'type' => 'index',
            'fields' => array('contact_type', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_contacts_reports_to',
            'type' => 'index',
            'fields' => array('reports_to_id')
        ),
        array(
            'name' => 'idx_mdeal_contacts_phone',
            'type' => 'index',
            'fields' => array('phone_mobile', 'phone_work')
        ),
        array(
            'name' => 'idx_mdeal_contacts_decision_role',
            'type' => 'index',
            'fields' => array('decision_role', 'influence_level')
        ),
        array(
            'name' => 'idx_mdeal_contacts_interaction',
            'type' => 'index',
            'fields' => array('last_interaction_date', 'deleted')
        ),
    ),
);

VardefManager::createVardef('mdeal_Contacts', 'mdeal_Contacts', array('person', 'assignable', 'security_groups'));