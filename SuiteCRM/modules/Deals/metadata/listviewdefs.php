<?php
/**
 * List View Definitions for Deals Module
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

$module_name = 'Deals';
$listViewDefs[$module_name] = array(
    'NAME' => array(
        'width' => '20%',
        'label' => 'LBL_LIST_NAME',
        'link' => true,
        'orderBy' => 'name',
        'default' => true,
        'related_fields' => array('id'),
    ),
    'STATUS' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_STATUS',
        'default' => true,
        'customCode' => '<span class="deal-status-{$AT_RISK_STATUS}">{$STATUS}</span>',
    ),
    'SOURCE' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_SOURCE',
        'default' => true,
    ),
    'DEAL_VALUE' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_DEAL_VALUE',
        'currency_format' => true,
        'align' => 'right',
        'default' => true,
    ),
    'FOCUS_C' => array(
        'width' => '5%',
        'label' => 'LBL_LIST_FOCUS',
        'default' => true,
        'customCode' => '{if $FOCUS_C}<span class="glyphicon glyphicon-star" style="color: gold;"></span>{/if}',
    ),
    'AT_RISK_STATUS' => array(
        'width' => '8%',
        'label' => 'LBL_LIST_AT_RISK_STATUS',
        'default' => true,
        'customCode' => '<span class="risk-indicator risk-{$AT_RISK_STATUS}">{$AT_RISK_STATUS}</span>',
    ),
    'DAYS_IN_STAGE' => array(
        'width' => '8%',
        'label' => 'LBL_DAYS_IN_STAGE',
        'default' => true,
        'align' => 'center',
    ),
    'ASSIGNED_USER_NAME' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_ASSIGNED_USER',
        'module' => 'Users',
        'id' => 'ASSIGNED_USER_ID',
        'default' => true,
    ),
    'DATE_ENTERED' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_DATE_ENTERED',
        'default' => true,
    ),
    'DATE_MODIFIED' => array(
        'width' => '10%',
        'label' => 'LBL_DATE_MODIFIED',
        'default' => false,
    ),
    'ASKING_PRICE_C' => array(
        'width' => '10%',
        'label' => 'LBL_ASKING_PRICE',
        'currency_format' => true,
        'align' => 'right',
        'default' => false,
    ),
    'PROPOSED_VALUATION_C' => array(
        'width' => '10%',
        'label' => 'LBL_PROPOSED_VALUATION',
        'currency_format' => true,
        'align' => 'right',
        'default' => false,
    ),
    'TTM_REVENUE_C' => array(
        'width' => '10%',
        'label' => 'LBL_TTM_REVENUE',
        'currency_format' => true,
        'align' => 'right',
        'default' => false,
    ),
    'TTM_EBITDA_C' => array(
        'width' => '10%',
        'label' => 'LBL_TTM_EBITDA',
        'currency_format' => true,
        'align' => 'right',
        'default' => false,
    ),
    'TARGET_MULTIPLE_C' => array(
        'width' => '8%',
        'label' => 'LBL_TARGET_MULTIPLE',
        'default' => false,
        'customCode' => '{$TARGET_MULTIPLE_C}x',
    ),
    'CREATED_BY_NAME' => array(
        'width' => '10%',
        'label' => 'LBL_CREATED',
        'default' => false,
    ),
    'MODIFIED_BY_NAME' => array(
        'width' => '10%',
        'label' => 'LBL_MODIFIED',
        'default' => false,
    ),
);