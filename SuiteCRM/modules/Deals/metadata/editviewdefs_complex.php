<?php
/**
 * Edit View Definitions for Deals Module
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

$module_name = 'Deals';
$viewdefs[$module_name]['EditView'] = array(
    'templateMeta' => array(
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30'),
        ),
        'includes' => array(
            array('file' => 'modules/Deals/Deal.js'),
        ),
        'form' => array(
            'enctype' => 'multipart/form-data',
            'headerTpl' => 'modules/Deals/tpls/EditViewHeader.tpl',
        ),
        'useTabs' => true,
        'tabDefs' => array(
            'LBL_DEAL_INFORMATION' => array(
                'newTab' => true,
                'panelDefault' => 'expanded',
            ),
            'LBL_FINANCIAL_INFORMATION' => array(
                'newTab' => true,
                'panelDefault' => 'expanded',
            ),
            'LBL_CAPITAL_STACK' => array(
                'newTab' => false,
                'panelDefault' => 'expanded',
            ),
            'LBL_PANEL_ASSIGNMENT' => array(
                'newTab' => false,
                'panelDefault' => 'expanded',
            ),
        ),
    ),
    'panels' => array(
        'LBL_DEAL_INFORMATION' => array(
            array(
                'name',
                array(
                    'name' => 'status',
                    'displayParams' => array(
                        'required' => true,
                    ),
                ),
            ),
            array(
                'source',
                'deal_value',
            ),
            array(
                array(
                    'name' => 'focus_c',
                    'label' => 'LBL_FOCUS',
                ),
                array(
                    'name' => 'at_risk_status',
                    'displayParams' => array(
                        'disabled' => true,
                    ),
                ),
            ),
            array(
                array(
                    'name' => 'description',
                    'displayParams' => array(
                        'rows' => 6,
                        'cols' => 80,
                    ),
                ),
            ),
        ),
        'LBL_FINANCIAL_INFORMATION' => array(
            array(
                'asking_price_c',
                'proposed_valuation_c',
            ),
            array(
                'ttm_revenue_c',
                'ttm_ebitda_c',
            ),
            array(
                'sde_c',
                array(
                    'name' => 'target_multiple_c',
                    'displayParams' => array(
                        'size' => 10,
                    ),
                ),
            ),
        ),
        'LBL_CAPITAL_STACK' => array(
            array(
                'equity_c',
                'senior_debt_c',
            ),
            array(
                'seller_note_c',
                '',
            ),
        ),
        'LBL_PANEL_ASSIGNMENT' => array(
            array(
                'assigned_user_name',
                '',
            ),
            array(
                array(
                    'name' => 'date_entered',
                    'customCode' => '{$fields.date_entered.value} {$APP.LBL_BY} {$fields.created_by_name.value}',
                ),
                array(
                    'name' => 'date_modified',
                    'customCode' => '{$fields.date_modified.value} {$APP.LBL_BY} {$fields.modified_by_name.value}',
                ),
            ),
        ),
    ),
);