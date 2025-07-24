<?php
/**
 * Detail view definitions for mdeal_Leads module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$viewdefs['mdeal_Leads']['DetailView'] = array(
    'templateMeta' => array(
        'form' => array(
            'buttons' => array(
                'EDIT',
                'DUPLICATE',
                'DELETE',
                array(
                    'customCode' => '{if $fields.status.value != "converted"}<input title="{$MOD.LBL_CONVERT_LEAD}" accesskey="C" class="button" onclick="this.form.action.value=\'ConvertLead\'; this.form.module.value=\'{$module}\'; this.form.record.value=\'{$fields.id.value}\';" type="submit" name="button" value="{$MOD.LBL_CONVERT_LEAD}" id="convert_lead_button">{/if}',
                ),
                array(
                    'customCode' => '{if $fields.converted_deal_id.value}<input title="{$MOD.LBL_VIEW_CONVERTED_DEAL}" class="button" onclick="location.href=\'index.php?module=mdeal_Deals&action=DetailView&record={$fields.converted_deal_id.value}\';" type="button" name="view_deal_button" value="{$MOD.LBL_VIEW_CONVERTED_DEAL}">{/if}',
                ),
            ),
        ),
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30'),
        ),
    ),
    'panels' => array(
        'LBL_PANEL_1' => array(
            array(
                array(
                    'name' => 'company_name',
                    'label' => 'LBL_COMPANY_NAME',
                ),
                array(
                    'name' => 'status',
                    'label' => 'LBL_STATUS',
                ),
            ),
            array(
                array(
                    'name' => 'first_name',
                    'customCode' => '{$fields.first_name.value} {$fields.last_name.value}',
                    'label' => 'LBL_NAME',
                ),
                array(
                    'name' => 'rating',
                    'label' => 'LBL_RATING',
                ),
            ),
            array(
                array(
                    'name' => 'title',
                    'label' => 'LBL_TITLE',
                ),
                array(
                    'name' => 'lead_source',
                    'label' => 'LBL_LEAD_SOURCE',
                ),
            ),
            array(
                array(
                    'name' => 'assigned_user_name',
                    'label' => 'LBL_ASSIGNED_TO',
                ),
                array(
                    'name' => 'converted_deal_id',
                    'label' => 'LBL_CONVERTED_DEAL',
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
        'LBL_PANEL_PIPELINE' => array(
            array(
                array(
                    'name' => 'pipeline_stage',
                    'label' => 'LBL_PIPELINE_STAGE',
                ),
                array(
                    'name' => 'qualification_score',
                    'label' => 'LBL_QUALIFICATION_SCORE',
                    'customCode' => '{if $fields.qualification_score.value}{$fields.qualification_score.value}%{/if}',
                ),
            ),
            array(
                array(
                    'name' => 'days_in_stage',
                    'label' => 'LBL_DAYS_IN_STAGE',
                    'customCode' => '{if $fields.days_in_stage.value}{$fields.days_in_stage.value} days{/if}',
                ),
                array(
                    'name' => 'date_entered_stage',
                    'label' => 'LBL_DATE_ENTERED_STAGE',
                ),
            ),
            array(
                array(
                    'name' => 'last_activity_date',
                    'label' => 'LBL_LAST_ACTIVITY_DATE',
                ),
                array(
                    'name' => 'next_follow_up_date',
                    'label' => 'LBL_NEXT_FOLLOW_UP_DATE',
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
                    'name' => 'description',
                    'label' => 'LBL_DESCRIPTION',
                    'span' => 12,
                ),
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