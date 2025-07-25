<?php
/**
 * Simple Edit View Definitions for Deals Module
 */

$viewdefs['Deals']['EditView'] = array(
    'templateMeta' => array(
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30')
        ),
    ),
    'panels' => array(
        'default' => array(
            array(
                'name',
                'account_name',
            ),
            array(
                'amount',
                'date_closed',
            ),
            array(
                'sales_stage',
                'probability',
            ),
            array(
                'lead_source',
                'campaign_name',
            ),
            array(
                'next_step',
            ),
            array(
                'description',
            ),
            array(
                'assigned_user_name',
                'team_name',
            ),
        ),
    ),
);
?>