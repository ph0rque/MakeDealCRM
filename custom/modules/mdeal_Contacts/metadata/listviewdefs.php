<?php
/**
 * List view definitions for mdeal_Contacts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$listViewDefs['mdeal_Contacts'] = array(
    'FULL_NAME' => array(
        'width' => '15%',
        'label' => 'LBL_LIST_FULL_NAME',
        'link' => true,
        'default' => true,
        'customCode' => '{$FIRST_NAME} {$LAST_NAME}',
        'related_fields' => array('first_name', 'last_name'),
        'orderBy' => 'last_name',
    ),
    'ACCOUNT_NAME' => array(
        'width' => '15%',
        'label' => 'LBL_LIST_ACCOUNT_NAME',
        'id' => 'ACCOUNT_ID',
        'module' => 'mdeal_Accounts',
        'default' => true,
        'ACLTag' => 'ACCOUNT',
        'related_fields' => array('account_id'),
    ),
    'TITLE' => array(
        'width' => '12%',
        'label' => 'LBL_LIST_TITLE',
        'default' => true,
    ),
    'CONTACT_TYPE' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_CONTACT_TYPE',
        'default' => true,
    ),
    'EMAIL_ADDRESS' => array(
        'width' => '15%',
        'label' => 'LBL_LIST_EMAIL',
        'default' => true,
        'customCode' => '<a href="mailto:{$EMAIL_ADDRESS}">{$EMAIL_ADDRESS}</a>',
    ),
    'PHONE_WORK' => array(
        'width' => '10%',
        'label' => 'LBL_OFFICE_PHONE',
        'default' => false,
    ),
    'PHONE_MOBILE' => array(
        'width' => '10%',
        'label' => 'LBL_MOBILE_PHONE',
        'default' => false,
    ),
    'DECISION_ROLE' => array(
        'width' => '12%',
        'label' => 'LBL_LIST_DECISION_ROLE',
        'default' => true,
    ),
    'INFLUENCE_LEVEL' => array(
        'width' => '8%',
        'label' => 'LBL_LIST_INFLUENCE_LEVEL',
        'default' => false,
    ),
    'RELATIONSHIP_STRENGTH' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_RELATIONSHIP_STRENGTH',
        'default' => false,
    ),
    'REPORTS_TO_NAME' => array(
        'width' => '12%',
        'label' => 'LBL_REPORTS_TO',
        'id' => 'REPORTS_TO_ID',
        'module' => 'mdeal_Contacts',
        'default' => false,
        'related_fields' => array('reports_to_id'),
    ),
    'DEPARTMENT' => array(
        'width' => '10%',
        'label' => 'LBL_DEPARTMENT',
        'default' => false,
    ),
    'LINKEDIN_URL' => array(
        'width' => '12%',
        'label' => 'LBL_LINKEDIN_URL',
        'default' => false,
        'customCode' => '{if $LINKEDIN_URL}<a href="{$LINKEDIN_URL}" target="_blank">LinkedIn</a>{/if}',
    ),
    'LAST_INTERACTION_DATE' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_LAST_INTERACTION',
        'default' => false,
    ),
    'INTERACTION_COUNT' => array(
        'width' => '8%',
        'label' => 'LBL_INTERACTION_COUNT',
        'default' => false,
    ),
    'RESPONSE_RATE' => array(
        'width' => '8%',
        'label' => 'LBL_RESPONSE_RATE',
        'default' => false,
        'customCode' => '{if $RESPONSE_RATE}{$RESPONSE_RATE}%{/if}',
    ),
    'TRUST_LEVEL' => array(
        'width' => '8%',
        'label' => 'LBL_TRUST_LEVEL',
        'default' => false,
        'customCode' => '{if $TRUST_LEVEL}{$TRUST_LEVEL}/10{/if}',
    ),
    'PREFERRED_CONTACT_METHOD' => array(
        'width' => '10%',
        'label' => 'LBL_PREFERRED_CONTACT_METHOD',
        'default' => false,
    ),
    'CONFIDENTIALITY_AGREEMENT' => array(
        'width' => '8%',
        'label' => 'LBL_CONFIDENTIALITY_AGREEMENT',
        'default' => false,
        'customCode' => '{if $CONFIDENTIALITY_AGREEMENT}✓{/if}',
    ),
    'DO_NOT_CALL' => array(
        'width' => '8%',
        'label' => 'LBL_DO_NOT_CALL',
        'default' => false,
        'customCode' => '{if $DO_NOT_CALL}✓{/if}',
    ),
    'EMAIL_OPT_OUT' => array(
        'width' => '8%',
        'label' => 'LBL_EMAIL_OPT_OUT',
        'default' => false,
        'customCode' => '{if $EMAIL_OPT_OUT}✓{/if}',
    ),
    'PRIMARY_ADDRESS_CITY' => array(
        'width' => '10%',
        'label' => 'LBL_PRIMARY_ADDRESS_CITY',
        'default' => false,
    ),
    'PRIMARY_ADDRESS_STATE' => array(
        'width' => '8%',
        'label' => 'LBL_PRIMARY_ADDRESS_STATE',
        'default' => false,
    ),
    'ASSIGNED_USER_NAME' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_ASSIGNED_USER',
        'module' => 'Employees',
        'id' => 'ASSIGNED_USER_ID',
        'default' => true,
    ),
    'DATE_ENTERED' => array(
        'width' => '10%',
        'label' => 'LBL_DATE_ENTERED',
        'default' => false,
    ),
    'DATE_MODIFIED' => array(
        'width' => '10%',
        'label' => 'LBL_DATE_MODIFIED',
        'default' => false,
    ),
);