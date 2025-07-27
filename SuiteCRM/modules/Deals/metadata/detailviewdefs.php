<?php
/**
 * Detail View Definitions for Deals Module
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

$module_name = 'Deals';
$viewdefs[$module_name]['DetailView'] = array(
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
            'buttons' => array(
                'EDIT',
                'DUPLICATE',
                'DELETE',
                'FIND_DUPLICATES',
                array(
                    'customCode' => '<input type="button" class="button" onClick="window.location=\'index.php?module=Deals&action=Pipeline&record={$fields.id.value}\';" value="{$MOD.LBL_PIPELINE_VIEW}">',
                ),
                array(
                    'customCode' => '<input type="button" class="button" id="apply_checklist_btn" onClick="showChecklistTemplatePopup(\'{$fields.id.value}\');" value="{$MOD.LBL_APPLY_CHECKLIST_TEMPLATE}">',
                ),
                array(
                    'customCode' => '<input type="button" class="button" onClick="window.location=\'index.php?module=ChecklistTemplates&action=index\';" value="{$MOD.LBL_MANAGE_TEMPLATES}">',
                ),
            ),
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
                    'customCode' => '<span class="deal-status-{$fields.at_risk_status.value}">{$fields.status.value}</span>',
                ),
            ),
            array(
                'source',
                array(
                    'name' => 'deal_value',
                    'label' => 'LBL_DEAL_VALUE',
                ),
            ),
            array(
                array(
                    'name' => 'focus_c',
                    'label' => 'LBL_FOCUS',
                ),
                array(
                    'name' => 'at_risk_status',
                    'customCode' => '<span class="risk-indicator risk-{$fields.at_risk_status.value}">{$fields.at_risk_status.value}</span>',
                ),
            ),
            array(
                array(
                    'name' => 'days_in_stage',
                    'label' => 'LBL_DAYS_IN_STAGE',
                    'customCode' => '{$fields.days_in_stage.value} days',
                ),
                array(
                    'name' => 'date_in_current_stage',
                    'label' => 'LBL_DATE_IN_CURRENT_STAGE',
                ),
            ),
            array(
                array(
                    'name' => 'description',
                    'nl2br' => true,
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
                    'customCode' => '{$fields.target_multiple_c.value}x',
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
                array(
                    'name' => 'total_capital',
                    'type' => 'readonly',
                    'label' => 'LBL_TOTAL_CAPITAL',
                    'customCode' => '<span id="total_capital_display">{$CURRENCY_SYMBOL}0.00</span>',
                ),
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
                    'label' => 'LBL_DATE_ENTERED',
                ),
                array(
                    'name' => 'date_modified',
                    'customCode' => '{$fields.date_modified.value} {$APP.LBL_BY} {$fields.modified_by_name.value}',
                    'label' => 'LBL_DATE_MODIFIED',
                ),
            ),
        ),
    ),
);