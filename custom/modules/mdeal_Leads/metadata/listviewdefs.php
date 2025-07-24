<?php
/**
 * List view definitions for mdeal_Leads module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$listViewDefs['mdeal_Leads'] = array(
    'COMPANY_NAME' => array(
        'width' => '20%',
        'label' => 'LBL_LIST_COMPANY_NAME',
        'link' => true,
        'default' => true,
        'related_fields' => array('first_name', 'last_name'),
    ),
    'NAME' => array(
        'width' => '15%', 
        'label' => 'LBL_LIST_CONTACT_NAME',
        'link' => false,
        'default' => true,
        'customCode' => '{$FIRST_NAME} {$LAST_NAME}',
        'related_fields' => array('first_name', 'last_name'),
    ),
    'STATUS' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_STATUS',
        'default' => true,
    ),
    'RATING' => array(
        'width' => '8%',
        'label' => 'LBL_LIST_RATING',
        'default' => true,
    ),
    'PIPELINE_STAGE' => array(
        'width' => '12%',
        'label' => 'LBL_LIST_PIPELINE_STAGE',
        'default' => true,
    ),
    'LEAD_SOURCE' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_LEAD_SOURCE',
        'default' => true,
    ),
    'QUALIFICATION_SCORE' => array(
        'width' => '8%',
        'label' => 'LBL_LIST_QUALIFICATION_SCORE',
        'default' => false,
        'customCode' => '{if $QUALIFICATION_SCORE}{$QUALIFICATION_SCORE}%{/if}',
    ),
    'PHONE_WORK' => array(
        'width' => '10%',
        'label' => 'LBL_OFFICE_PHONE',
        'default' => false,
    ),
    'EMAIL_ADDRESS' => array(
        'width' => '15%',
        'label' => 'LBL_EMAIL_ADDRESS',
        'default' => false,
        'customCode' => '<a href="mailto:{$EMAIL_ADDRESS}">{$EMAIL_ADDRESS}</a>',
    ),
    'WEBSITE' => array(
        'width' => '12%',
        'label' => 'LBL_WEBSITE',
        'default' => false,
        'customCode' => '{if $WEBSITE}<a href="{$WEBSITE}" target="_blank">{$WEBSITE}</a>{/if}',
    ),
    'INDUSTRY' => array(
        'width' => '10%',
        'label' => 'LBL_INDUSTRY',
        'default' => false,
    ),
    'ANNUAL_REVENUE' => array(
        'width' => '12%',
        'label' => 'LBL_ANNUAL_REVENUE',
        'default' => false,
        'customCode' => '{if $ANNUAL_REVENUE}{sugar_currency_format var=$ANNUAL_REVENUE}{/if}',
    ),
    'EMPLOYEE_COUNT' => array(
        'width' => '8%',
        'label' => 'LBL_EMPLOYEE_COUNT',
        'default' => false,
    ),
    'LAST_ACTIVITY_DATE' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_LAST_ACTIVITY',
        'default' => false,
    ),
    'NEXT_FOLLOW_UP_DATE' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_NEXT_FOLLOW_UP',
        'default' => false,
    ),
    'DAYS_IN_STAGE' => array(
        'width' => '8%',
        'label' => 'LBL_DAYS_IN_STAGE',
        'default' => false,
        'customCode' => '{if $DAYS_IN_STAGE}{$DAYS_IN_STAGE}d{/if}',
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