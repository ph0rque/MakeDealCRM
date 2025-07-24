<?php
/**
 * Edit view definitions for mdeal_Leads module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$viewdefs['mdeal_Leads']['EditView'] = array(
    'templateMeta' => array(
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30'),
        ),
        'javascript' => '<script type="text/javascript">
            function enableQualifiedFields() {
                var status = document.getElementById("status");
                var rating = document.getElementById("rating");
                var pipelineStage = document.getElementById("pipeline_stage");
                
                if (status.value == "qualified" || status.value == "converted") {
                    if (rating) rating.disabled = false;
                    if (pipelineStage) pipelineStage.disabled = false;
                } else {
                    if (rating) rating.disabled = true;
                    if (pipelineStage) pipelineStage.disabled = true;
                }
            }
            
            function validateConversion() {
                var status = document.getElementById("status");
                var pipelineStage = document.getElementById("pipeline_stage");
                var companyName = document.getElementById("company_name");
                var industry = document.getElementById("industry");
                var revenue = document.getElementById("annual_revenue");
                
                if (status.value == "converted") {
                    if (!companyName.value || !industry.value || !revenue.value) {
                        alert("Company Name, Industry, and Annual Revenue are required for conversion.");
                        return false;
                    }
                    if (pipelineStage.value != "ready_to_convert") {
                        alert("Lead must be in \\"Ready to Convert\\" stage before conversion.");
                        return false;
                    }
                }
                return true;
            }
            
            // Run on page load
            addToValidate("EditView", "company_name", "varchar", true, "{sugar_translate label=\'LBL_COMPANY_NAME\' module=\'mdeal_Leads\'}");
            addToValidate("EditView", "last_name", "varchar", true, "{sugar_translate label=\'LBL_LAST_NAME\' module=\'mdeal_Leads\'}");
            addToValidate("EditView", "status", "varchar", true, "{sugar_translate label=\'LBL_STATUS\' module=\'mdeal_Leads\'}");
        </script>',
        'form' => array(
            'validate' => 'true',
            'enctype' => 'multipart/form-data',
        ),
    ),
    'panels' => array(
        'LBL_PANEL_1' => array(
            array(
                array(
                    'name' => 'company_name',
                    'label' => 'LBL_COMPANY_NAME',
                    'required' => true,
                ),
                array(
                    'name' => 'status',
                    'label' => 'LBL_STATUS',
                    'required' => true,
                ),
            ),
            array(
                array(
                    'name' => 'first_name',
                    'label' => 'LBL_FIRST_NAME',
                ),
                array(
                    'name' => 'last_name',
                    'label' => 'LBL_LAST_NAME',
                    'required' => true,
                ),
            ),
            array(
                array(
                    'name' => 'title',
                    'label' => 'LBL_TITLE',
                ),
                array(
                    'name' => 'assigned_user_name',
                    'label' => 'LBL_ASSIGNED_TO',
                ),
            ),
        ),
        'LBL_PANEL_CONTACT' => array(
            array(
                array(
                    'name' => 'phone_work',
                    'label' => 'LBL_OFFICE_PHONE',
                ),
                array(
                    'name' => 'phone_mobile',
                    'label' => 'LBL_MOBILE_PHONE',
                ),
            ),
            array(
                array(
                    'name' => 'email_address',
                    'label' => 'LBL_EMAIL_ADDRESS',
                ),
                array(
                    'name' => 'website',
                    'label' => 'LBL_WEBSITE',
                ),
            ),
            array(
                array(
                    'name' => 'do_not_call',
                    'label' => 'LBL_DO_NOT_CALL',
                ),
                array(
                    'name' => 'email_opt_out',
                    'label' => 'LBL_EMAIL_OPT_OUT',
                ),
            ),
        ),
        'LBL_PANEL_COMPANY' => array(
            array(
                array(
                    'name' => 'industry',
                    'label' => 'LBL_INDUSTRY',
                ),
                array(
                    'name' => 'annual_revenue',
                    'label' => 'LBL_ANNUAL_REVENUE',
                ),
            ),
            array(
                array(
                    'name' => 'employee_count',
                    'label' => 'LBL_EMPLOYEE_COUNT',
                ),
                array(
                    'name' => 'years_in_business',
                    'label' => 'LBL_YEARS_IN_BUSINESS',
                ),
            ),
        ),
        'LBL_PANEL_QUALIFICATION' => array(
            array(
                array(
                    'name' => 'lead_source',
                    'label' => 'LBL_LEAD_SOURCE',
                ),
                array(
                    'name' => 'rating',
                    'label' => 'LBL_RATING',
                ),
            ),
            array(
                array(
                    'name' => 'pipeline_stage',
                    'label' => 'LBL_PIPELINE_STAGE',
                ),
                array(
                    'name' => 'qualification_score',
                    'label' => 'LBL_QUALIFICATION_SCORE',
                    'displayParams' => array('readonly' => true),
                ),
            ),
            array(
                array(
                    'name' => 'lead_source_description',
                    'label' => 'LBL_LEAD_SOURCE_DESCRIPTION',
                ),
                array(
                    'name' => 'status_description',
                    'label' => 'LBL_STATUS_DESCRIPTION',
                ),
            ),
            array(
                array(
                    'name' => 'next_follow_up_date',
                    'label' => 'LBL_NEXT_FOLLOW_UP_DATE',
                ),
                array(
                    'name' => 'converted_deal_id',
                    'label' => 'LBL_CONVERTED_DEAL',
                    'displayParams' => array('readonly' => true),
                ),
            ),
        ),
        'LBL_PANEL_ADDRESS' => array(
            array(
                array(
                    'name' => 'primary_address_street',
                    'label' => 'LBL_PRIMARY_ADDRESS_STREET',
                ),
                array(
                    'name' => 'primary_address_city',
                    'label' => 'LBL_PRIMARY_ADDRESS_CITY',
                ),
            ),
            array(
                array(
                    'name' => 'primary_address_state',
                    'label' => 'LBL_PRIMARY_ADDRESS_STATE',
                ),
                array(
                    'name' => 'primary_address_postalcode',
                    'label' => 'LBL_PRIMARY_ADDRESS_POSTALCODE',
                ),
            ),
            array(
                array(
                    'name' => 'primary_address_country',
                    'label' => 'LBL_PRIMARY_ADDRESS_COUNTRY',
                ),
            ),
        ),
        'LBL_PANEL_OTHER' => array(
            array(
                array(
                    'name' => 'description',
                    'label' => 'LBL_DESCRIPTION',
                    'span' => 12,
                ),
            ),
        ),
    ),
);