<?php
/**
 * Detail view definitions for mdeal_Contacts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$viewdefs['mdeal_Contacts']['DetailView'] = array(
    'templateMeta' => array(
        'form' => array(
            'buttons' => array(
                'EDIT',
                'DUPLICATE',
                'DELETE',
                array(
                    'customCode' => '<input title="{$MOD.LBL_CALCULATE_INFLUENCE}" class="button" onclick="calculateInfluenceScore(\'{$fields.id.value}\');" type="button" name="calc_influence_button" value="{$MOD.LBL_CALCULATE_INFLUENCE}" id="calc_influence_button">',
                ),
                array(
                    'customCode' => '<input title="{$MOD.LBL_VIEW_ORGANIZATION_CHART}" class="button" onclick="viewOrgChart(\'{$fields.id.value}\');" type="button" name="org_chart_button" value="{$MOD.LBL_VIEW_ORGANIZATION_CHART}" id="org_chart_button">',
                ),
            ),
        ),
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30'),
        ),
        'javascript' => '<script type="text/javascript">
            function calculateInfluenceScore(contactId) {
                // AJAX call to calculate influence score
                var callback = {
                    success: function(data) {
                        alert("{$MOD.MSG_INFLUENCE_CALCULATED}");
                        window.location.reload();
                    },
                    failure: function(error) {
                        alert("Error calculating influence score");
                    }
                };
                var url = "index.php?module=mdeal_Contacts&action=CalculateInfluence&record=" + contactId;
                YAHOO.util.Connect.asyncRequest("GET", url, callback);
            }
            
            function viewOrgChart(contactId) {
                var url = "index.php?module=mdeal_Contacts&action=OrganizationChart&record=" + contactId;
                window.open(url, "_blank", "width=800,height=600,scrollbars=yes,resizable=yes");
            }
        </script>',
    ),
    'panels' => array(
        'LBL_PANEL_1' => array(
            array(
                array(
                    'name' => 'full_name',
                    'customCode' => '{$fields.salutation.value} {$fields.first_name.value} {$fields.last_name.value}',
                    'label' => 'LBL_NAME',
                ),
                array(
                    'name' => 'contact_type',
                    'label' => 'LBL_CONTACT_TYPE',
                ),
            ),
            array(
                array(
                    'name' => 'title',
                    'label' => 'LBL_TITLE',
                ),
                array(
                    'name' => 'department',
                    'label' => 'LBL_DEPARTMENT',
                ),
            ),
            array(
                array(
                    'name' => 'account_name',
                    'label' => 'LBL_ACCOUNT_NAME',
                ),
                array(
                    'name' => 'reports_to_name',
                    'label' => 'LBL_REPORTS_TO',
                ),
            ),
            array(
                array(
                    'name' => 'assigned_user_name',
                    'label' => 'LBL_ASSIGNED_TO',
                ),
                array(
                    'name' => 'lead_source',
                    'label' => 'LBL_LEAD_SOURCE',
                ),
            ),
        ),
        'LBL_PANEL_CONTACT_DETAILS' => array(
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
                    'name' => 'email_address2',
                    'label' => 'LBL_EMAIL_ADDRESS2',
                ),
            ),
            array(
                array(
                    'name' => 'phone_home',
                    'label' => 'LBL_HOME_PHONE',
                ),
                array(
                    'name' => 'phone_fax',
                    'label' => 'LBL_FAX_PHONE',
                ),
            ),
            array(
                array(
                    'name' => 'assistant',
                    'label' => 'LBL_ASSISTANT',
                ),
                array(
                    'name' => 'assistant_phone',
                    'label' => 'LBL_ASSISTANT_PHONE',
                ),
            ),
            array(
                array(
                    'name' => 'linkedin_url',
                    'label' => 'LBL_LINKEDIN_URL',
                ),
                array(
                    'name' => 'birthdate',
                    'label' => 'LBL_BIRTHDATE',
                ),
            ),
        ),
        'LBL_PANEL_COMMUNICATION' => array(
            array(
                array(
                    'name' => 'preferred_contact_method',
                    'label' => 'LBL_PREFERRED_CONTACT_METHOD',
                ),
                array(
                    'name' => 'best_time_to_contact',
                    'label' => 'LBL_BEST_TIME_TO_CONTACT',
                ),
            ),
            array(
                array(
                    'name' => 'timezone',
                    'label' => 'LBL_TIMEZONE',
                ),
                array(
                    'name' => 'do_not_call',
                    'label' => 'LBL_DO_NOT_CALL',
                ),
            ),
            array(
                array(
                    'name' => 'email_opt_out',
                    'label' => 'LBL_EMAIL_OPT_OUT',
                ),
                array(
                    'name' => 'invalid_email',
                    'label' => 'LBL_INVALID_EMAIL',
                ),
            ),
            array(
                array(
                    'name' => 'communication_style',
                    'label' => 'LBL_COMMUNICATION_STYLE',
                    'span' => 12,
                ),
            ),
        ),
        'LBL_PANEL_DECISION_MAKING' => array(
            array(
                array(
                    'name' => 'decision_role',
                    'label' => 'LBL_DECISION_ROLE',
                ),
                array(
                    'name' => 'influence_level',
                    'label' => 'LBL_INFLUENCE_LEVEL',
                ),
            ),
            array(
                array(
                    'name' => 'relationship_strength',
                    'label' => 'LBL_RELATIONSHIP_STRENGTH',
                ),
                array(
                    'name' => 'trust_level',
                    'label' => 'LBL_TRUST_LEVEL',
                    'customCode' => '{if $fields.trust_level.value}{$fields.trust_level.value}/10{/if}',
                ),
            ),
        ),
        'LBL_PANEL_RELATIONSHIP' => array(
            array(
                array(
                    'name' => 'last_interaction_date',
                    'label' => 'LBL_LAST_INTERACTION_DATE',
                ),
                array(
                    'name' => 'interaction_count',
                    'label' => 'LBL_INTERACTION_COUNT',
                ),
            ),
            array(
                array(
                    'name' => 'response_rate',
                    'label' => 'LBL_RESPONSE_RATE',
                    'customCode' => '{if $fields.response_rate.value}{$fields.response_rate.value}%{/if}',
                ),
                array(
                    'name' => 'days_since_interaction',
                    'label' => 'LBL_DAYS_SINCE_INTERACTION',
                    'customCode' => '{if $fields.last_interaction_date.value}{$DAYS_SINCE_INTERACTION} days{/if}',
                ),
            ),
        ),
        'LBL_PANEL_ADDRESS' => array(
            array(
                array(
                    'name' => 'primary_address_street',
                    'label' => 'LBL_PRIMARY_ADDRESS_STREET',
                    'type' => 'address',
                    'displayParams' => array(
                        'key' => 'primary'
                    ),
                ),
                array(
                    'name' => 'alt_address_street',
                    'label' => 'LBL_ALT_ADDRESS_STREET',
                    'type' => 'address',
                    'displayParams' => array(
                        'key' => 'alt'
                    ),
                ),
            ),
        ),
        'LBL_PANEL_OTHER' => array(
            array(
                array(
                    'name' => 'confidentiality_agreement',
                    'label' => 'LBL_CONFIDENTIALITY_AGREEMENT',
                ),
                array(
                    'name' => 'background_check_completed',
                    'label' => 'LBL_BACKGROUND_CHECK_COMPLETED',
                ),
            ),
            array(
                array(
                    'name' => 'background_check_date',
                    'label' => 'LBL_BACKGROUND_CHECK_DATE',
                ),
                array(
                    'name' => 'contact_subtype',
                    'label' => 'LBL_CONTACT_SUBTYPE',
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
                    'name' => 'notes_private',
                    'label' => 'LBL_NOTES_PRIVATE',
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