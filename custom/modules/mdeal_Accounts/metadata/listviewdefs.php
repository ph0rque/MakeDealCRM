<?php
/**
 * List view definition for mdeal_Accounts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$listViewDefs['mdeal_Accounts'] = array(
    'name' => array(
        'width' => '20%',
        'label' => 'LBL_LIST_ACCOUNT_NAME',
        'link' => true,
        'default' => true,
        'related_fields' => array('billing_address_country')
    ),
    'account_type' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_ACCOUNT_TYPE',
        'default' => true
    ),
    'industry' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_INDUSTRY',
        'default' => true
    ),
    'annual_revenue' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_ANNUAL_REVENUE',
        'default' => true,
        'align' => 'right',
        'customCode' => '{if $ANNUAL_REVENUE}${$ANNUAL_REVENUE|number_format:0}{/if}'
    ),
    'employee_count' => array(
        'width' => '8%',
        'label' => 'LBL_LIST_EMPLOYEE_COUNT',
        'default' => true,
        'align' => 'right'
    ),
    'phone_office' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_PHONE',
        'default' => true
    ),
    'website' => array(
        'width' => '15%',
        'label' => 'LBL_LIST_WEBSITE',
        'default' => false,
        'customCode' => '{if $WEBSITE}<a href="{$WEBSITE}" target="_blank">{$WEBSITE}</a>{/if}'
    ),
    'parent_name' => array(
        'width' => '12%',
        'label' => 'LBL_LIST_PARENT_NAME',
        'default' => false,
        'link' => true,
        'id' => 'PARENT_ID',
        'module' => 'mdeal_Accounts',
        'ACLTag' => 'ACCOUNT',
        'related_fields' => array('parent_id')
    ),
    'deal_count' => array(
        'width' => '6%',
        'label' => 'LBL_LIST_DEAL_COUNT',
        'default' => false,
        'align' => 'center'
    ),
    'total_deal_value' => array(
        'width' => '10%',
        'label' => 'LBL_TOTAL_DEAL_VALUE',
        'default' => false,
        'align' => 'right',
        'customCode' => '{if $TOTAL_DEAL_VALUE}${$TOTAL_DEAL_VALUE|number_format:0}{/if}'
    ),
    'rating' => array(
        'width' => '8%',
        'label' => 'LBL_LIST_RATING',
        'default' => false
    ),
    'account_status' => array(
        'width' => '8%',
        'label' => 'LBL_LIST_ACCOUNT_STATUS',
        'default' => false
    ),
    'hierarchy_level' => array(
        'width' => '6%',
        'label' => 'LBL_HIERARCHY_LEVEL',
        'default' => false,
        'align' => 'center',
        'customCode' => '{if $HIERARCHY_LEVEL > 0}Level {$HIERARCHY_LEVEL}{else}Root{/if}'
    ),
    'ebitda' => array(
        'width' => '10%',
        'label' => 'LBL_EBITDA',
        'default' => false,
        'align' => 'right',
        'customCode' => '{if $EBITDA}${$EBITDA|number_format:0}{/if}'
    ),
    'integration_status' => array(
        'width' => '10%',
        'label' => 'LBL_INTEGRATION_STATUS',
        'default' => false
    ),
    'acquisition_date' => array(
        'width' => '10%',
        'label' => 'LBL_ACQUISITION_DATE',
        'default' => false
    ),
    'current_valuation' => array(
        'width' => '10%',
        'label' => 'LBL_CURRENT_VALUATION',
        'default' => false,
        'align' => 'right',
        'customCode' => '{if $CURRENT_VALUATION}${$CURRENT_VALUATION|number_format:0}{/if}'
    ),
    'exit_strategy' => array(
        'width' => '10%',
        'label' => 'LBL_EXIT_STRATEGY',
        'default' => false
    ),
    'compliance_status' => array(
        'width' => '10%',
        'label' => 'LBL_COMPLIANCE_STATUS',
        'default' => false
    ),
    'risk_assessment' => array(
        'width' => '8%',
        'label' => 'LBL_RISK_ASSESSMENT',
        'default' => false
    ),
    'assigned_user_name' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_ASSIGNED_USER',
        'module' => 'Employees',
        'id' => 'ASSIGNED_USER_ID',
        'default' => true
    ),
    'date_entered' => array(
        'width' => '10%',
        'label' => 'LBL_DATE_ENTERED',
        'default' => false
    ),
    'date_modified' => array(
        'width' => '10%',
        'label' => 'LBL_DATE_MODIFIED',
        'default' => false
    )
);

// Add custom JavaScript for list view enhancements
$listViewDefs['mdeal_Accounts']['customCode'] = '
<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    // Add hover tooltips for financial data
    var revenueElements = document.querySelectorAll("[data-field=\'annual_revenue\']");
    revenueElements.forEach(function(element) {
        if (element.textContent.trim()) {
            element.title = "Annual Revenue: " + element.textContent;
        }
    });

    // Add hierarchy indicators
    var hierarchyElements = document.querySelectorAll("[data-field=\'hierarchy_level\']");
    hierarchyElements.forEach(function(element) {
        var level = parseInt(element.textContent.replace("Level ", ""));
        if (level > 0) {
            element.innerHTML = "└" + "─".repeat(level-1) + " Level " + level;
            element.style.paddingLeft = (level * 10) + "px";
        }
    });

    // Add status color coding
    var statusElements = document.querySelectorAll("[data-field=\'account_status\']");
    statusElements.forEach(function(element) {
        var status = element.textContent.trim().toLowerCase();
        switch(status) {
            case "active":
                element.style.color = "green";
                break;
            case "under_review":
            case "due_diligence":
                element.style.color = "orange";
                break;
            case "closed_won":
            case "acquired":
                element.style.color = "blue";
                break;
            case "closed_lost":
            case "dead":
                element.style.color = "red";
                break;
        }
    });

    // Add deal count indicators
    var dealElements = document.querySelectorAll("[data-field=\'deal_count\']");
    dealElements.forEach(function(element) {
        var count = parseInt(element.textContent);
        if (count > 0) {
            element.innerHTML = "<strong>" + count + "</strong>";
            element.style.color = count > 3 ? "green" : "blue";
        }
    });
});
</script>';