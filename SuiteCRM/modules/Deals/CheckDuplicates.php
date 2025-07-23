<?php
/**
 * AJAX handler for duplicate checking
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class DealsCheckDuplicates
{
    public function process()
    {
        global $db, $current_user;
        
        $response = array(
            'duplicates' => array(),
            'success' => true
        );
        
        // Get check data from request
        $checkData = json_decode($_POST['check_data'], true);
        $currentRecordId = $_POST['record_id'] ?? '';
        
        if (empty($checkData)) {
            echo json_encode($response);
            return;
        }
        
        // Build duplicate check query
        $duplicates = $this->findDuplicates($checkData, $currentRecordId);
        
        // Score and sort duplicates
        $scoredDuplicates = $this->scoreDuplicates($duplicates, $checkData);
        
        // Format response
        foreach ($scoredDuplicates as $duplicate) {
            $response['duplicates'][] = array(
                'id' => $duplicate['id'],
                'name' => $duplicate['name'],
                'account_name' => $duplicate['account_name'],
                'amount' => $duplicate['amount'],
                'amount_formatted' => '$' . number_format($duplicate['amount'], 2),
                'sales_stage' => $duplicate['sales_stage'],
                'assigned_user_name' => $duplicate['assigned_user_name'],
                'score' => $duplicate['duplicate_score'],
                'date_entered' => $duplicate['date_entered']
            );
        }
        
        echo json_encode($response);
    }
    
    /**
     * Find potential duplicates based on criteria
     */
    protected function findDuplicates($checkData, $excludeId)
    {
        global $db;
        
        $query = "SELECT 
                    d.id,
                    d.name,
                    d.amount,
                    d.sales_stage,
                    d.assigned_user_id,
                    d.date_entered,
                    a.name as account_name,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name
                  FROM deals d
                  LEFT JOIN accounts a ON d.account_id = a.id
                  LEFT JOIN users u ON d.assigned_user_id = u.id
                  WHERE d.deleted = 0 ";
        
        $conditions = array();
        
        // Exclude current record
        if (!empty($excludeId)) {
            $conditions[] = "d.id != " . $db->quote($excludeId);
        }
        
        // Check by name (fuzzy match)
        if (!empty($checkData['name'])) {
            $name = $db->quote($checkData['name']);
            $conditions[] = "(d.name LIKE '%" . $db->quote($checkData['name']) . "%' 
                             OR SOUNDEX(d.name) = SOUNDEX(" . $name . "))";
        }
        
        // Check by account
        if (!empty($checkData['account_name'])) {
            $conditions[] = "a.name LIKE '%" . $db->quote($checkData['account_name']) . "%'";
        }
        
        // Check by amount (within 10% range)
        if (!empty($checkData['amount']) && is_numeric($checkData['amount'])) {
            $amount = floatval($checkData['amount']);
            $lowerBound = $amount * 0.9;
            $upperBound = $amount * 1.1;
            $conditions[] = "(d.amount BETWEEN $lowerBound AND $upperBound)";
        }
        
        // Check by email
        if (!empty($checkData['email1'])) {
            $email = $db->quote($checkData['email1']);
            $conditions[] = "d.id IN (
                SELECT bean_id 
                FROM email_addr_bean_rel eabr
                JOIN email_addresses ea ON eabr.email_address_id = ea.id
                WHERE eabr.bean_module = 'Deals' 
                AND ea.email_address = " . $email . "
                AND eabr.deleted = 0
            )";
        }
        
        if (!empty($conditions)) {
            $query .= " AND (" . implode(" OR ", $conditions) . ")";
        }
        
        $query .= " ORDER BY d.date_entered DESC LIMIT 10";
        
        $result = $db->query($query);
        $duplicates = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $duplicates[] = $row;
        }
        
        return $duplicates;
    }
    
    /**
     * Score duplicates based on matching criteria
     */
    protected function scoreDuplicates($duplicates, $checkData)
    {
        foreach ($duplicates as &$duplicate) {
            $score = 0;
            $maxScore = 0;
            
            // Name match (40 points)
            if (!empty($checkData['name'])) {
                $maxScore += 40;
                $similarity = 0;
                similar_text(strtolower($checkData['name']), strtolower($duplicate['name']), $similarity);
                $score += ($similarity / 100) * 40;
            }
            
            // Account match (30 points)
            if (!empty($checkData['account_name']) && !empty($duplicate['account_name'])) {
                $maxScore += 30;
                if (strtolower($checkData['account_name']) == strtolower($duplicate['account_name'])) {
                    $score += 30;
                } else {
                    similar_text(strtolower($checkData['account_name']), strtolower($duplicate['account_name']), $similarity);
                    $score += ($similarity / 100) * 30;
                }
            }
            
            // Amount match (20 points)
            if (!empty($checkData['amount']) && !empty($duplicate['amount'])) {
                $maxScore += 20;
                $difference = abs($checkData['amount'] - $duplicate['amount']);
                $percentDiff = $difference / $checkData['amount'];
                if ($percentDiff <= 0.1) { // Within 10%
                    $score += 20 * (1 - $percentDiff * 10);
                }
            }
            
            // Email match (10 points)
            if (!empty($checkData['email1'])) {
                $maxScore += 10;
                // Email matching would need additional query
                // For now, assume it's checked in the main query
                $score += 10;
            }
            
            // Calculate percentage score
            $duplicate['duplicate_score'] = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;
        }
        
        // Sort by score descending
        usort($duplicates, function($a, $b) {
            return $b['duplicate_score'] - $a['duplicate_score'];
        });
        
        // Only return high-confidence duplicates (score > 50%)
        return array_filter($duplicates, function($dup) {
            return $dup['duplicate_score'] > 50;
        });
    }
}

// Process the request
$handler = new DealsCheckDuplicates();
$handler->process();