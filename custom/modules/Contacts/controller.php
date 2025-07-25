<?php
/**
 * Custom controller for Contacts module
 * Handles AJAX requests for stakeholder-related functionality
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');

class ContactsController extends SugarController
{
    /**
     * Quick search for contacts (used in stakeholder integration)
     */
    public function action_quicksearch()
    {
        global $db;
        
        $query = $db->quote($_GET['query'] ?? '');
        $limit = intval($_GET['limit'] ?? 10);
        
        if (strlen($query) < 2) {
            $this->sendJsonResponse(['results' => []]);
            return;
        }
        
        $sql = "SELECT 
                    c.id,
                    CONCAT(c.first_name, ' ', c.last_name) as name,
                    c.title,
                    c.email1 as email,
                    a.name as account_name
                FROM contacts c
                LEFT JOIN accounts a ON c.account_id = a.id AND a.deleted = 0
                WHERE c.deleted = 0
                AND (
                    c.first_name LIKE '%{$query}%' OR
                    c.last_name LIKE '%{$query}%' OR
                    CONCAT(c.first_name, ' ', c.last_name) LIKE '%{$query}%' OR
                    c.email1 LIKE '%{$query}%' OR
                    a.name LIKE '%{$query}%'
                )
                ORDER BY c.last_name, c.first_name
                LIMIT {$limit}";
        
        $result = $db->query($sql);
        $contacts = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $contacts[] = [
                'id' => $row['id'],
                'name' => trim($row['name']),
                'title' => $row['title'],
                'email' => $row['email'],
                'account_name' => $row['account_name']
            ];
        }
        
        $this->sendJsonResponse(['results' => $contacts]);
    }

    /**
     * Send JSON response
     */
    private function sendJsonResponse($data)
    {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        sugar_cleanup(true);
    }
}