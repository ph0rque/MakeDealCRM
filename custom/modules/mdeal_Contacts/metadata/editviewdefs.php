<?php
/**
 * Edit view definitions for mdeal_Contacts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$viewdefs['mdeal_Contacts']['EditView'] = array(
    'templateMeta' => array(
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30'),
        ),
        'javascript' => '<script type="text/javascript">
            function validateTrustLevel() {
                var trustLevel = document.getElementById("trust_level");
                if (trustLevel && trustLevel.value) {
                    var value = parseInt(trustLevel.value);
                    if (value < 1 || value > 10) {
                        alert("{sugar_translate label=\'ERR_TRUST_LEVEL_RANGE\' module=\'mdeal_Contacts\'}");
                        return false;
                    }
                }
                return true;
            }
            
            function checkHierarchy() {
                var reportsTo = document.getElementById("reports_to_id");
                var contactId = document.getElementById("record");
                
                if (reportsTo && reportsTo.value && contactId && contactId.value) {
                    if (reportsTo.value === contactId.value) {
                        alert("A contact cannot report to themselves.");
                        reportsTo.value = "";
                        return false;
                    }
                }
                return true;
            }
            
            // Add validation on form submit
            addToValidate("EditView", "last_name", "varchar", true, "{sugar_translate label=\'LBL_LAST_NAME\' module=\'mdeal_Contacts\'}");
            addToValidate("EditView", "trust_level", "int", false, "{sugar_translate label=\'LBL_TRUST_LEVEL\' module=\'mdeal_Contacts\'}", validateTrustLevel);
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
                    'name' => 'salutation',
                    'label' => 'LBL_SALUTATION',
                ),
                array(
                    'name' => 'contact_type',
                    'label' => 'LBL_CONTACT_TYPE',
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
                    'name' => 'contact_subtype',
                    'label' => 'LBL_CONTACT_SUBTYPE',
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
                    'displayParams' => array(
                        'help' => 'LBL_HELP_TRUST_LEVEL'
                    ),
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
                        'key' => 'primary',
                        'rows' => 2,
                        'cols' => 30
                    ),
                ),
                array(
                    'name' => 'alt_address_street',
                    'label' => 'LBL_ALT_ADDRESS_STREET',
                    'type' => 'address',
                    'displayParams' => array(
                        'key' => 'alt',
                        'rows' => 2,
                        'cols' => 30
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
                    'name' => 'picture',
                    'label' => 'LBL_PICTURE',
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
        ),
    ),
);