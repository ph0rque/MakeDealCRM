<?php
/**
 * Detail view definition for mdeal_Accounts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$viewdefs['mdeal_Accounts']['DetailView'] = array(
    'templateMeta' => array(
        'form' => array(
            'buttons' => array(
                'EDIT',
                'DUPLICATE',
                'DELETE',
                array(
                    'customCode' => '<input type="button" class="button" value="{$MOD.LBL_VIEW_HIERARCHY_BUTTON}" onclick="viewAccountHierarchy(\'{$fields.id.value}\');">',
                ),
                array(
                    'customCode' => '<input type="button" class="button" value="{$MOD.LBL_CALCULATE_HEALTH_BUTTON}" onclick="calculateAccountHealth(\'{$fields.id.value}\');">',
                ),
                array(
                    'customCode' => '<input type="button" class="button" value="{$MOD.LBL_PORTFOLIO_METRICS_BUTTON}" onclick="showPortfolioMetrics(\'{$fields.id.value}\');">',
                ),
            )
        ),
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30')
        ),
        'includes' => array(
            array('file' => 'custom/modules/mdeal_Accounts/tpls/DetailViewHeader.js')
        ),
    ),
    'panels' => array(
        'LBL_ACCOUNT_INFORMATION' => array(
            array(
                array(
                    'name' => 'name',
                    'label' => 'LBL_NAME',
                    'displayParams' => array('required' => true)
                ),
                array(
                    'name' => 'account_type',
                    'label' => 'LBL_ACCOUNT_TYPE'
                ),
            ),
            array(
                array(
                    'name' => 'parent_name',
                    'label' => 'LBL_PARENT_ACCOUNT'
                ),
                array(
                    'name' => 'hierarchy_level',
                    'label' => 'LBL_HIERARCHY_LEVEL',
                    'customCode' => '{if $fields.hierarchy_level.value > 0}Level {$fields.hierarchy_level.value}{else}Root Company{/if}'
                ),
            ),
            array(
                array(
                    'name' => 'account_status',
                    'label' => 'LBL_ACCOUNT_STATUS'
                ),
                array(
                    'name' => 'rating',
                    'label' => 'LBL_RATING'
                ),
            ),
            array(
                array(
                    'name' => 'assigned_user_name',
                    'label' => 'LBL_ASSIGNED_TO'
                ),
                array(
                    'name' => 'website',
                    'label' => 'LBL_WEBSITE',
                    'customCode' => '{if $fields.website.value}<a href="{$fields.website.value}" target="_blank">{$fields.website.value}</a>{/if}'
                ),
            ),
        ),

        'LBL_CONTACT_INFORMATION' => array(
            array(
                array(
                    'name' => 'phone_office',
                    'label' => 'LBL_PHONE_OFFICE'
                ),
                array(
                    'name' => 'phone_alternate',
                    'label' => 'LBL_PHONE_ALTERNATE'
                ),
            ),
            array(
                array(
                    'name' => 'phone_fax',
                    'label' => 'LBL_PHONE_FAX'
                ),
                array(
                    'name' => 'email',
                    'label' => 'LBL_EMAIL'
                ),
            ),
        ),

        'LBL_COMPANY_INFORMATION' => array(
            array(
                array(
                    'name' => 'industry',
                    'label' => 'LBL_INDUSTRY'
                ),
                array(
                    'name' => 'sub_industry',
                    'label' => 'LBL_SUB_INDUSTRY'
                ),
            ),
            array(
                array(
                    'name' => 'naics_code',
                    'label' => 'LBL_NAICS_CODE'
                ),
                array(
                    'name' => 'sic_code',
                    'label' => 'LBL_SIC_CODE'
                ),
            ),
            array(
                array(
                    'name' => 'employee_count',
                    'label' => 'LBL_EMPLOYEE_COUNT'
                ),
                array(
                    'name' => 'facility_count',
                    'label' => 'LBL_FACILITY_COUNT'
                ),
            ),
            array(
                array(
                    'name' => 'year_established',
                    'label' => 'LBL_YEAR_ESTABLISHED'
                ),
                array(
                    'name' => 'ownership_type',
                    'label' => 'LBL_OWNERSHIP_TYPE'
                ),
            ),
            array(
                array(
                    'name' => 'ticker_symbol',
                    'label' => 'LBL_TICKER_SYMBOL'
                ),
                array(
                    'name' => 'duns_number',
                    'label' => 'LBL_DUNS_NUMBER'
                ),
            ),
            array(
                array(
                    'name' => 'dba_name',
                    'label' => 'LBL_DBA_NAME'
                ),
                array(
                    'name' => 'tax_id',
                    'label' => 'LBL_TAX_ID'
                ),
            ),
        ),

        'LBL_FINANCIAL_INFORMATION' => array(
            array(
                array(
                    'name' => 'annual_revenue',
                    'label' => 'LBL_ANNUAL_REVENUE',
                    'customCode' => '{if $fields.annual_revenue.value}${$fields.annual_revenue.value|number_format:0}{/if}'
                ),
                array(
                    'name' => 'ebitda',
                    'label' => 'LBL_EBITDA',
                    'customCode' => '{if $fields.ebitda.value}${$fields.ebitda.value|number_format:0}{/if}'
                ),
            ),
            array(
                array(
                    'name' => 'credit_rating',
                    'label' => 'LBL_CREDIT_RATING'
                ),
                array(
                    'name' => 'credit_limit',
                    'label' => 'LBL_CREDIT_LIMIT',
                    'customCode' => '{if $fields.credit_limit.value}${$fields.credit_limit.value|number_format:0}{/if}'
                ),
            ),
            array(
                array(
                    'name' => 'payment_terms',
                    'label' => 'LBL_PAYMENT_TERMS'
                ),
                array(
                    'name' => 'revenue_currency_id',
                    'label' => 'LBL_REVENUE_CURRENCY'
                ),
            ),
        ),

        'LBL_DEAL_INFORMATION' => array(
            array(
                array(
                    'name' => 'deal_count',
                    'label' => 'LBL_DEAL_COUNT'
                ),
                array(
                    'name' => 'total_deal_value',
                    'label' => 'LBL_TOTAL_DEAL_VALUE',
                    'customCode' => '{if $fields.total_deal_value.value}${$fields.total_deal_value.value|number_format:0}{/if}'
                ),
            ),
            array(
                array(
                    'name' => 'last_deal_date',
                    'label' => 'LBL_LAST_DEAL_DATE'
                ),
                array(
                    'name' => 'risk_assessment',
                    'label' => 'LBL_RISK_ASSESSMENT'
                ),
            ),
            array(
                array(
                    'name' => 'compliance_status',
                    'label' => 'LBL_COMPLIANCE_STATUS'
                ),
                '',
            ),
        ),

        'LBL_PORTFOLIO_INFORMATION' => array(
            array(
                array(
                    'name' => 'acquisition_date',
                    'label' => 'LBL_ACQUISITION_DATE'
                ),
                array(
                    'name' => 'acquisition_price',
                    'label' => 'LBL_ACQUISITION_PRICE',
                    'customCode' => '{if $fields.acquisition_price.value}${$fields.acquisition_price.value|number_format:0}{/if}'
                ),
            ),
            array(
                array(
                    'name' => 'current_valuation',
                    'label' => 'LBL_CURRENT_VALUATION',
                    'customCode' => '{if $fields.current_valuation.value}${$fields.current_valuation.value|number_format:0}{/if}'
                ),
                array(
                    'name' => 'exit_strategy',
                    'label' => 'LBL_EXIT_STRATEGY'
                ),
            ),
            array(
                array(
                    'name' => 'planned_exit_date',
                    'label' => 'LBL_PLANNED_EXIT_DATE'
                ),
                array(
                    'name' => 'integration_status',
                    'label' => 'LBL_INTEGRATION_STATUS'
                ),
            ),
        ),

        'LBL_BILLING_ADDRESS' => array(
            array(
                array(
                    'name' => 'billing_address_street',
                    'label' => 'LBL_BILLING_ADDRESS_STREET',
                    'type' => 'address',
                    'displayParams' => array('key' => 'billing')
                ),
            ),
            array(
                array(
                    'name' => 'billing_address_city',
                    'label' => 'LBL_BILLING_ADDRESS_CITY'
                ),
                array(
                    'name' => 'billing_address_state',
                    'label' => 'LBL_BILLING_ADDRESS_STATE'
                ),
            ),
            array(
                array(
                    'name' => 'billing_address_postalcode',
                    'label' => 'LBL_BILLING_ADDRESS_POSTALCODE'
                ),
                array(
                    'name' => 'billing_address_country',
                    'label' => 'LBL_BILLING_ADDRESS_COUNTRY'
                ),
            ),
        ),

        'LBL_SHIPPING_ADDRESS' => array(
            array(
                array(
                    'name' => 'shipping_address_street',
                    'label' => 'LBL_SHIPPING_ADDRESS_STREET',
                    'type' => 'address',
                    'displayParams' => array('key' => 'shipping')
                ),
            ),
            array(
                array(
                    'name' => 'shipping_address_city',
                    'label' => 'LBL_SHIPPING_ADDRESS_CITY'
                ),
                array(
                    'name' => 'shipping_address_state',
                    'label' => 'LBL_SHIPPING_ADDRESS_STATE'
                ),
            ),
            array(
                array(
                    'name' => 'shipping_address_postalcode',
                    'label' => 'LBL_SHIPPING_ADDRESS_POSTALCODE'
                ),
                array(
                    'name' => 'shipping_address_country',
                    'label' => 'LBL_SHIPPING_ADDRESS_COUNTRY'
                ),
            ),
        ),

        'LBL_INSURANCE_INFORMATION' => array(
            array(
                array(
                    'name' => 'insurance_coverage',
                    'label' => 'LBL_INSURANCE_COVERAGE'
                ),
                array(
                    'name' => 'insurance_expiry',
                    'label' => 'LBL_INSURANCE_EXPIRY'
                ),
            ),
        ),

        'LBL_DESCRIPTION_INFORMATION' => array(
            array(
                array(
                    'name' => 'description',
                    'label' => 'LBL_DESCRIPTION',
                    'displayParams' => array('nl2br' => true)
                ),
            ),
        ),
    )
);