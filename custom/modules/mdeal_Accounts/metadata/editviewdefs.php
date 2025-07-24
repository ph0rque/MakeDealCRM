<?php
/**
 * Edit view definition for mdeal_Accounts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$viewdefs['mdeal_Accounts']['EditView'] = array(
    'templateMeta' => array(
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30')
        ),
        'includes' => array(
            array('file' => 'modules/mdeal_Accounts/AccountEditView.js')
        ),
        'form' => array(
            'headerTpl' => 'modules/mdeal_Accounts/tpls/EditViewHeader.tpl',
            'footerTpl' => 'modules/mdeal_Accounts/tpls/EditViewFooter.tpl',
            'buttons' => array(
                'SAVE',
                'CANCEL',
                array(
                    'customCode' => '<input type="button" class="button" value="{$MOD.LBL_SAVE_NEW_BUTTON_LABEL}" onclick="this.form.return_module.value=\'mdeal_Accounts\'; this.form.return_action.value=\'EditView\'; this.form.return_id.value=\'\'; this.form.action.value=\'Save\';">'
                ),
            )
        ),
        'javascript' => '
<script type="text/javascript">
// Account hierarchy validation
function validateHierarchy() {
    var currentId = document.getElementById("id").value;
    var parentId = document.getElementById("parent_id").value;
    
    if (parentId && parentId === currentId) {
        alert("{$MOD.LBL_CIRCULAR_HIERARCHY_ERROR}");
        return false;
    }
    return true;
}

// Auto-populate DBA name
function autoPopulateDBA() {
    var accountName = document.getElementById("name").value;
    var dbaField = document.getElementById("dba_name");
    
    if (accountName && !dbaField.value) {
        dbaField.value = accountName;
    }
}

// Calculate EBITDA margin
function calculateEBITDAMargin() {
    var revenue = parseFloat(document.getElementById("annual_revenue").value) || 0;
    var ebitda = parseFloat(document.getElementById("ebitda").value) || 0;
    
    if (revenue > 0 && ebitda > 0) {
        var margin = (ebitda / revenue * 100).toFixed(2);
        document.getElementById("ebitda_margin_display").innerHTML = margin + "%";
    }
}

// Copy billing address to shipping
function copyBillingToShipping() {
    if (document.getElementById("same_as_billing").checked) {
        document.getElementById("shipping_address_street").value = document.getElementById("billing_address_street").value;
        document.getElementById("shipping_address_city").value = document.getElementById("billing_address_city").value;
        document.getElementById("shipping_address_state").value = document.getElementById("billing_address_state").value;
        document.getElementById("shipping_address_postalcode").value = document.getElementById("billing_address_postalcode").value;
        document.getElementById("shipping_address_country").value = document.getElementById("billing_address_country").value;
    }
}

// Validate form before submission
function check_form(formname) {
    if (!validateHierarchy()) {
        return false;
    }
    
    if (typeof(validate) != "undefined") {
        if (!validate[formname]) {
            return false;
        }
    }
    
    return true;
}

// Add event listeners when page loads
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("name").addEventListener("blur", autoPopulateDBA);
    document.getElementById("annual_revenue").addEventListener("blur", calculateEBITDAMargin);
    document.getElementById("ebitda").addEventListener("blur", calculateEBITDAMargin);
    document.getElementById("same_as_billing").addEventListener("change", copyBillingToShipping);
});
</script>',
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
                    'label' => 'LBL_ACCOUNT_TYPE',
                    'displayParams' => array('required' => true)
                ),
            ),
            array(
                array(
                    'name' => 'parent_name',
                    'label' => 'LBL_PARENT_ACCOUNT'
                ),
                array(
                    'name' => 'account_status',
                    'label' => 'LBL_ACCOUNT_STATUS'
                ),
            ),
            array(
                array(
                    'name' => 'rating',
                    'label' => 'LBL_RATING'
                ),
                array(
                    'name' => 'assigned_user_name',
                    'label' => 'LBL_ASSIGNED_TO'
                ),
            ),
            array(
                array(
                    'name' => 'website',
                    'label' => 'LBL_WEBSITE'
                ),
                array(
                    'name' => 'dba_name',
                    'label' => 'LBL_DBA_NAME'
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
                    'name' => 'tax_id',
                    'label' => 'LBL_TAX_ID'
                ),
                '',
            ),
        ),

        'LBL_FINANCIAL_INFORMATION' => array(
            array(
                array(
                    'name' => 'annual_revenue',
                    'label' => 'LBL_ANNUAL_REVENUE'
                ),
                array(
                    'name' => 'ebitda',
                    'label' => 'LBL_EBITDA',
                    'customCode' => '{html_options name="ebitda" id="ebitda" options=$fields.ebitda.options selected=$fields.ebitda.value} <span id="ebitda_margin_display" style="margin-left:10px; font-weight:bold; color:green;"></span>'
                ),
            ),
            array(
                array(
                    'name' => 'credit_rating',
                    'label' => 'LBL_CREDIT_RATING'
                ),
                array(
                    'name' => 'credit_limit',
                    'label' => 'LBL_CREDIT_LIMIT'
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

        'LBL_COMPLIANCE_INFORMATION' => array(
            array(
                array(
                    'name' => 'risk_assessment',
                    'label' => 'LBL_RISK_ASSESSMENT'
                ),
                array(
                    'name' => 'compliance_status',
                    'label' => 'LBL_COMPLIANCE_STATUS'
                ),
            ),
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

        'LBL_PORTFOLIO_INFORMATION' => array(
            array(
                array(
                    'name' => 'acquisition_date',
                    'label' => 'LBL_ACQUISITION_DATE'
                ),
                array(
                    'name' => 'acquisition_price',
                    'label' => 'LBL_ACQUISITION_PRICE'
                ),
            ),
            array(
                array(
                    'name' => 'current_valuation',
                    'label' => 'LBL_CURRENT_VALUATION'
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
                    'displayParams' => array('key' => 'billing', 'rows' => 2, 'cols' => 30)
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
                    'name' => 'same_as_billing',
                    'label' => 'LBL_SAME_AS_BILLING'
                ),
                '',
            ),
            array(
                array(
                    'name' => 'shipping_address_street',
                    'label' => 'LBL_SHIPPING_ADDRESS_STREET',
                    'type' => 'address',
                    'displayParams' => array('key' => 'shipping', 'rows' => 2, 'cols' => 30)
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

        'LBL_DESCRIPTION_INFORMATION' => array(
            array(
                array(
                    'name' => 'description',
                    'label' => 'LBL_DESCRIPTION',
                    'displayParams' => array('rows' => 6, 'cols' => 80)
                ),
            ),
        ),
    )
);