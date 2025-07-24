<?php
/**
 * Detail view definition for Deals module
 */

$viewdefs['Deals'] = array(
    'DetailView' => array(
        'templateMeta' => array(
            'form' => array(
                'buttons' => array(
                    'EDIT',
                    'DELETE',
                    'DUPLICATE',
                    array(
                        'customCode' => '<input type="button" class="button" onClick="window.location=\'index.php?module=Deals&action=Pipeline\';" value="{$MOD.LBL_VIEW_PIPELINE}">',
                    ),
                ),
            ),
            'maxColumns' => '2',
            'widths' => array(
                array('label' => '10', 'field' => '30'),
                array('label' => '10', 'field' => '30'),
            ),
            'useTabs' => true,
            'tabDefs' => array(
                'LBL_DEAL_INFORMATION' => array(
                    'newTab' => true,
                    'panelDefault' => 'expanded',
                ),
                'LBL_PIPELINE_INFORMATION' => array(
                    'newTab' => true,
                    'panelDefault' => 'expanded',
                ),
                'LBL_PANEL_ASSIGNMENT' => array(
                    'newTab' => true,
                    'panelDefault' => 'expanded',
                ),
            ),
        ),
        'panels' => array(
            'lbl_deal_information' => array(
                array(
                    'name',
                    'amount',
                ),
                array(
                    'account_name',
                    'date_closed',
                ),
                array(
                    'sales_stage',
                    array(
                        'name' => 'probability',
                        'comment' => 'The probability of closure',
                        'label' => 'LBL_PROBABILITY',
                    ),
                ),
                array(
                    'lead_source',
                    'campaign_name',
                ),
                array(
                    'next_step',
                    '',
                ),
                array(
                    'description',
                ),
            ),
            'lbl_pipeline_information' => array(
                array(
                    array(
                        'name' => 'pipeline_stage_c',
                        'label' => 'LBL_PIPELINE_STAGE',
                    ),
                    array(
                        'name' => 'stage_entered_date_c',
                        'label' => 'LBL_STAGE_ENTERED_DATE',
                    ),
                ),
                array(
                    array(
                        'name' => 'expected_close_date_c',
                        'label' => 'LBL_EXPECTED_CLOSE_DATE',
                    ),
                    array(
                        'name' => 'deal_source_c',
                        'label' => 'LBL_DEAL_SOURCE',
                    ),
                ),
                array(
                    array(
                        'name' => 'pipeline_notes_c',
                        'label' => 'LBL_PIPELINE_NOTES',
                    ),
                ),
            ),
            'LBL_PANEL_ASSIGNMENT' => array(
                array(
                    'assigned_user_name',
                    array(
                        'name' => 'date_modified',
                        'label' => 'LBL_DATE_MODIFIED',
                        'customCode' => '{$fields.date_modified.value} {$APP.LBL_BY} {$fields.modified_by_name.value}',
                    ),
                ),
                array(
                    'team_name',
                    array(
                        'name' => 'date_entered',
                        'customCode' => '{$fields.date_entered.value} {$APP.LBL_BY} {$fields.created_by_name.value}',
                    ),
                ),
            ),
        ),
    ),
);