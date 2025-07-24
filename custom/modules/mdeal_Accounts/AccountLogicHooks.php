<?php
/**
 * Logic hooks class for mdeal_Accounts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class AccountLogicHooks
{
    /**
     * Validate hierarchy relationships to prevent circular references
     */
    public function validateHierarchy($bean, $event, $arguments)
    {
        if (empty($bean->parent_id) || $bean->parent_id == $bean->id) {
            return; // No hierarchy or self-reference
        }

        // Check for circular reference
        if (!$this->isValidHierarchy($bean)) {
            throw new Exception('Circular reference detected in account hierarchy');
        }
    }

    /**
     * Calculate account health score
     */
    public function calculateHealthScore($bean, $event, $arguments)
    {
        $score = 0;
        $maxScore = 100;

        // Financial health (30 points)
        if (!empty($bean->annual_revenue)) {
            $score += 15; // Has revenue data
            
            if ($bean->annual_revenue > 50000000) { // > $50M
                $score += 10;
            } elseif ($bean->annual_revenue > 10000000) { // > $10M
                $score += 7;
            } elseif ($bean->annual_revenue > 1000000) { // > $1M
                $score += 5;
            }
            
            if (!empty($bean->ebitda) && $bean->ebitda > 0) {
                $ebitdaMargin = ($bean->ebitda / $bean->annual_revenue) * 100;
                if ($ebitdaMargin > 20) {
                    $score += 5;
                } elseif ($ebitdaMargin > 10) {
                    $score += 3;
                }
            }
        }

        // Employee base (20 points)
        if (!empty($bean->employee_count)) {
            if ($bean->employee_count > 1000) {
                $score += 20;
            } elseif ($bean->employee_count > 500) {
                $score += 15;
            } elseif ($bean->employee_count > 100) {
                $score += 10;
            } elseif ($bean->employee_count > 10) {
                $score += 5;
            }
        }

        // Industry risk adjustment (±10 points)
        $industryRisk = $this->getIndustryRiskScore($bean->industry);
        $score += $industryRisk;

        // Contact engagement (15 points)
        $contactCount = $this->getContactCount($bean->id);
        if ($contactCount > 10) {
            $score += 15;
        } elseif ($contactCount > 5) {
            $score += 10;
        } elseif ($contactCount > 2) {
            $score += 5;
        } elseif ($contactCount > 0) {
            $score += 3;
        }

        // Deal activity (15 points)
        if (!empty($bean->deal_count)) {
            if ($bean->deal_count > 5) {
                $score += 15;
            } elseif ($bean->deal_count > 2) {
                $score += 10;
            } else {
                $score += 5;
            }
        }

        // Recent activity bonus/penalty (±10 points)
        if (!empty($bean->last_deal_date)) {
            $daysSince = $this->getDaysSinceLastDeal($bean->last_deal_date);
            if ($daysSince <= 30) {
                $score += 10; // Very recent activity
            } elseif ($daysSince <= 90) {
                $score += 5; // Recent activity
            } elseif ($daysSince > 365) {
                $score -= 10; // Stale account
            }
        }

        $bean->health_score = min($maxScore, max(0, $score));
        
        // Create alert if health score is critical
        if ($bean->health_score < 30) {
            $this->createHealthAlert($bean);
        }
    }

    /**
     * Update deal-related metrics
     */
    public function updateDealMetrics($bean, $event, $arguments)
    {
        if (empty($bean->id)) {
            return;
        }

        // Calculate deal metrics
        $query = "SELECT COUNT(*) as total_deals, 
                         COUNT(CASE WHEN stage NOT IN ('Closed Won', 'Closed Lost') THEN 1 END) as active_deals,
                         SUM(CASE WHEN stage NOT IN ('Closed Won', 'Closed Lost') THEN deal_value ELSE 0 END) as active_value,
                         SUM(deal_value) as total_value,
                         AVG(deal_value) as avg_value,
                         MAX(date_modified) as last_deal
                  FROM mdeal_deals 
                  WHERE account_id = ? AND deleted = 0";
        
        try {
            $result = $this->db->pQuery($query, [$bean->id]);
            $row = $this->db->fetchByAssoc($result);
            
            $bean->deal_count = $row['active_deals'] ?? 0;
            $bean->total_deal_value = $row['active_value'] ?? 0;
            $bean->avg_deal_value = $row['avg_value'] ?? 0;
            $bean->last_deal_date = $row['last_deal'] ?? null;
            
            // Store historical metrics
            $this->storeHistoricalMetrics($bean, $row);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error("Error calculating deal metrics: " . $e->getMessage());
        }
    }

    /**
     * Update portfolio metrics for portfolio companies
     */
    public function updatePortfolioMetrics($bean, $event, $arguments)
    {
        if ($bean->account_type !== 'portfolio' || empty($bean->id)) {
            return;
        }

        $metrics = [];

        // Calculate acquisition multiple
        if (!empty($bean->acquisition_price) && !empty($bean->annual_revenue)) {
            $metrics['acquisition_multiple'] = $bean->acquisition_price / $bean->annual_revenue;
        }

        // Calculate current multiple
        if (!empty($bean->current_valuation) && !empty($bean->annual_revenue)) {
            $metrics['current_multiple'] = $bean->current_valuation / $bean->annual_revenue;
        }

        // Calculate value creation
        if (!empty($bean->current_valuation) && !empty($bean->acquisition_price)) {
            $metrics['value_creation'] = $bean->current_valuation - $bean->acquisition_price;
            $metrics['value_creation_percent'] = (($bean->current_valuation - $bean->acquisition_price) / $bean->acquisition_price) * 100;
        }

        // Update portfolio-level aggregations
        $this->updatePortfolioAggregates($bean, $metrics);
    }

    /**
     * Track compliance requirements
     */
    public function trackCompliance($bean, $event, $arguments)
    {
        // Check if compliance review is needed
        $needsReview = false;
        $reasons = [];

        // Industry-specific compliance
        if (in_array($bean->industry, ['Financial Services', 'Healthcare', 'Energy'])) {
            $needsReview = true;
            $reasons[] = 'High-regulation industry';
        }

        // Geographic compliance
        if (in_array($bean->billing_address_country, ['US', 'UK', 'EU'])) {
            $needsReview = true;
            $reasons[] = 'Geographic compliance requirements';
        }

        // Revenue threshold compliance
        if (!empty($bean->annual_revenue) && $bean->annual_revenue > 100000000) {
            $needsReview = true;
            $reasons[] = 'Large enterprise compliance';
        }

        if ($needsReview) {
            $this->createComplianceReviewTask($bean, $reasons);
        }

        // Update compliance score
        $bean->compliance_score = $this->calculateComplianceScore($bean);
    }

    /**
     * Calculate relationship score
     */
    public function calculateRelationshipScore($bean, $event, $arguments)
    {
        $score = 0;

        // Contact engagement (40 points)
        $contactMetrics = $this->getContactEngagementMetrics($bean->id);
        $score += min(40, $contactMetrics['engagement_score']);

        // Deal activity (30 points)
        if (!empty($bean->deal_count)) {
            $score += min(30, $bean->deal_count * 6);
        }

        // Opportunity pipeline (20 points)
        $opportunityValue = $this->getOpportunityPipelineValue($bean->id);
        if ($opportunityValue > 1000000) {
            $score += 20;
        } elseif ($opportunityValue > 500000) {
            $score += 15;
        } elseif ($opportunityValue > 100000) {
            $score += 10;
        }

        // Hierarchy connections (10 points)
        if (!empty($bean->parent_id) || $this->getChildrenCount($bean->id) > 0) {
            $score += 10;
        }

        $bean->relationship_score = min(100, $score);
    }

    /**
     * Send notifications for significant changes
     */
    public function sendChangeNotifications($bean, $event, $arguments)
    {
        if (empty($bean->fetched_row)) {
            return; // New record, no notifications needed
        }

        $significantChanges = [];

        // Track significant field changes
        $monitoredFields = [
            'account_status' => 'Account Status',
            'annual_revenue' => 'Annual Revenue',
            'rating' => 'Account Rating',
            'risk_assessment' => 'Risk Assessment',
            'compliance_status' => 'Compliance Status'
        ];

        foreach ($monitoredFields as $field => $label) {
            if (isset($bean->fetched_row[$field]) && 
                $bean->$field != $bean->fetched_row[$field]) {
                $significantChanges[$field] = [
                    'label' => $label,
                    'old_value' => $bean->fetched_row[$field],
                    'new_value' => $bean->$field
                ];
            }
        }

        if (!empty($significantChanges)) {
            $this->sendAccountChangeNotification($bean, $significantChanges);
        }
    }

    /**
     * Prevent deletion of accounts with active relationships
     */
    public function preventDeletionWithActiveRelationships($bean, $event, $arguments)
    {
        // Check for active deals
        $activeDealCount = $this->getRelatedRecordCount($bean->id, 'mdeal_deals', 'account_id', "stage NOT IN ('Closed Won', 'Closed Lost')");
        if ($activeDealCount > 0) {
            throw new Exception('Cannot delete account with active deals. Please close or reassign active deals first.');
        }

        // Check for subsidiary accounts
        $subsidiaryCount = $this->getRelatedRecordCount($bean->id, 'mdeal_accounts', 'parent_id');
        if ($subsidiaryCount > 0) {
            throw new Exception('Cannot delete account with subsidiary companies. Please reassign subsidiaries first.');
        }

        // Check for portfolio investments
        if ($bean->account_type === 'portfolio' && !empty($bean->acquisition_price)) {
            throw new Exception('Cannot delete portfolio company with investment data. Please mark as divested instead.');
        }
    }

    /**
     * Check if hierarchy is valid (no circular references)
     */
    protected function isValidHierarchy($bean)
    {
        $currentId = $bean->parent_id;
        $visited = [$bean->id];
        $maxDepth = 20;
        $depth = 0;

        while ($currentId && $depth < $maxDepth) {
            if (in_array($currentId, $visited)) {
                return false; // Circular reference
            }

            $visited[] = $currentId;

            // Get the next level up
            $account = BeanFactory::getBean('mdeal_Accounts', $currentId);
            if (!$account || empty($account->parent_id)) {
                break;
            }

            $currentId = $account->parent_id;
            $depth++;
        }

        return true;
    }

    /**
     * Get industry risk score
     */
    protected function getIndustryRiskScore($industry)
    {
        $riskScores = [
            'Technology' => 5,
            'Healthcare' => 3,
            'Financial Services' => -5,
            'Energy' => -3,
            'Real Estate' => -2,
            'Retail' => -3,
            'Manufacturing' => 2,
            'Software' => 8,
            'Telecommunications' => 0,
        ];

        return $riskScores[$industry] ?? 0;
    }

    /**
     * Get contact count for this account
     */
    protected function getContactCount($accountId)
    {
        if (empty($accountId)) {
            return 0;
        }

        $query = "SELECT COUNT(*) as count FROM mdeal_contacts 
                  WHERE account_id = ? AND deleted = 0";
        
        $result = $this->db->pQuery($query, [$accountId]);
        $row = $this->db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }

    /**
     * Get days since last deal
     */
    protected function getDaysSinceLastDeal($lastDealDate)
    {
        if (empty($lastDealDate)) {
            return null;
        }

        $lastDeal = new DateTime($lastDealDate);
        $now = new DateTime();
        return $lastDeal->diff($now)->days;
    }

    /**
     * Create health alert
     */
    protected function createHealthAlert($bean)
    {
        if (empty($bean->assigned_user_id)) {
            return;
        }

        $task = BeanFactory::newBean('Tasks');
        $task->name = "Account Health Alert: {$bean->name}";
        $task->description = "Account health score is critical ({$bean->health_score}%). Immediate attention required.";
        $task->parent_type = 'mdeal_Accounts';
        $task->parent_id = $bean->id;
        $task->assigned_user_id = $bean->assigned_user_id;
        $task->priority = 'High';
        $task->status = 'Not Started';
        
        // Set due date to today
        $task->date_due = date('Y-m-d');
        
        $task->save();
    }

    /**
     * Store historical metrics
     */
    protected function storeHistoricalMetrics($bean, $metrics)
    {
        // Store metrics in separate table for trend analysis
        $query = "INSERT INTO mdeal_accounts_metrics_history 
                  (account_id, date_recorded, deal_count, total_deal_value, avg_deal_value, health_score)
                  VALUES (?, NOW(), ?, ?, ?, ?)";
        
        $this->db->pQuery($query, [
            $bean->id,
            $metrics['active_deals'] ?? 0,
            $metrics['active_value'] ?? 0,
            $metrics['avg_value'] ?? 0,
            $bean->health_score ?? 0
        ]);
    }

    /**
     * Update portfolio aggregates
     */
    protected function updatePortfolioAggregates($bean, $metrics)
    {
        // Update portfolio-level metrics (would be in a portfolio summary table)
        $GLOBALS['log']->info("Portfolio metrics updated for {$bean->name}: " . json_encode($metrics));
    }

    /**
     * Create compliance review task
     */
    protected function createComplianceReviewTask($bean, $reasons)
    {
        $task = BeanFactory::newBean('Tasks');
        $task->name = "Compliance Review Required: {$bean->name}";
        $task->description = "Compliance review needed due to: " . implode(', ', $reasons);
        $task->parent_type = 'mdeal_Accounts';
        $task->parent_id = $bean->id;
        $task->assigned_user_id = $bean->assigned_user_id;
        $task->priority = 'Medium';
        $task->status = 'Not Started';
        
        // Set due date to 7 days from now
        $dueDate = new DateTime();
        $dueDate->add(new DateInterval('P7D'));
        $task->date_due = $dueDate->format('Y-m-d');
        
        $task->save();
    }

    /**
     * Calculate compliance score
     */
    protected function calculateComplianceScore($bean)
    {
        $score = 100; // Start with perfect score

        // Deduct points for missing compliance data
        if (empty($bean->compliance_status)) {
            $score -= 20;
        } elseif ($bean->compliance_status === 'non_compliant') {
            $score -= 50;
        }

        if (empty($bean->risk_assessment)) {
            $score -= 15;
        } elseif ($bean->risk_assessment === 'critical') {
            $score -= 30;
        }

        if (empty($bean->insurance_coverage) || !$bean->insurance_coverage) {
            $score -= 10;
        }

        if (!empty($bean->insurance_expiry)) {
            $expiryDate = new DateTime($bean->insurance_expiry);
            $now = new DateTime();
            $daysTillExpiry = $now->diff($expiryDate)->days;
            
            if ($daysTillExpiry < 30) {
                $score -= 20; // Insurance expiring soon
            } elseif ($daysTillExpiry < 90) {
                $score -= 10;
            }
        }

        return max(0, $score);
    }

    /**
     * Get contact engagement metrics
     */
    protected function getContactEngagementMetrics($accountId)
    {
        $query = "SELECT AVG(interaction_count) as avg_interactions,
                         AVG(response_rate) as avg_response_rate,
                         COUNT(*) as contact_count
                  FROM mdeal_contacts 
                  WHERE account_id = ? AND deleted = 0";
        
        $result = $this->db->pQuery($query, [$accountId]);
        $row = $this->db->fetchByAssoc($result);
        
        $engagementScore = 0;
        if ($row['contact_count'] > 0) {
            $engagementScore += min(20, $row['contact_count'] * 4); // Up to 20 points for contact count
            $engagementScore += min(20, ($row['avg_response_rate'] ?? 0) / 5); // Up to 20 points for response rate
        }
        
        return [
            'engagement_score' => $engagementScore,
            'contact_count' => $row['contact_count'] ?? 0,
            'avg_interactions' => $row['avg_interactions'] ?? 0,
            'avg_response_rate' => $row['avg_response_rate'] ?? 0
        ];
    }

    /**
     * Get opportunity pipeline value
     */
    protected function getOpportunityPipelineValue($accountId)
    {
        $query = "SELECT SUM(amount) as total_value FROM opportunities 
                  WHERE account_id = ? AND sales_stage NOT IN ('Closed Won', 'Closed Lost') AND deleted = 0";
        
        $result = $this->db->pQuery($query, [$accountId]);
        $row = $this->db->fetchByAssoc($result);
        
        return $row['total_value'] ?? 0;
    }

    /**
     * Get children count
     */
    protected function getChildrenCount($accountId)
    {
        return $this->getRelatedRecordCount($accountId, 'mdeal_accounts', 'parent_id');
    }

    /**
     * Send account change notification
     */
    protected function sendAccountChangeNotification($bean, $changes)
    {
        if (empty($bean->assigned_user_id)) {
            return;
        }

        $user = BeanFactory::getBean('Users', $bean->assigned_user_id);
        if (!$user || empty($user->email1)) {
            return;
        }

        require_once('include/SugarPHPMailer.php');
        
        $mail = new SugarPHPMailer();
        $mail->setMailerForSystem();
        
        $mail->AddAddress($user->email1, $user->full_name);
        $mail->Subject = "Account Updated: {$bean->name}";
        
        $changeList = '';
        foreach ($changes as $change) {
            $changeList .= "<li><strong>{$change['label']}:</strong> {$change['old_value']} → {$change['new_value']}</li>";
        }
        
        $body = "
        <p>Dear {$user->first_name},</p>
        
        <p>The account <strong>{$bean->name}</strong> has been updated with significant changes:</p>
        
        <ul>
        {$changeList}
        </ul>
        
        <p><a href=\"{$GLOBALS['sugar_config']['site_url']}/index.php?module=mdeal_Accounts&action=DetailView&record={$bean->id}\">View Account</a></p>
        
        <p>Best regards,<br/>MakeDeal CRM</p>
        ";
        
        $mail->Body = $body;
        $mail->isHTML(true);
        
        try {
            $mail->send();
        } catch (Exception $e) {
            $GLOBALS['log']->error("Failed to send account change notification: " . $e->getMessage());
        }
    }

    /**
     * Get count of related records
     */
    protected function getRelatedRecordCount($id, $table, $foreignKey, $additionalWhere = '')
    {
        global $db;
        
        $whereClause = "WHERE {$foreignKey} = ? AND deleted = 0";
        if (!empty($additionalWhere)) {
            $whereClause .= " AND {$additionalWhere}";
        }
        
        $query = "SELECT COUNT(*) as count FROM {$table} {$whereClause}";
        $result = $db->pQuery($query, [$id]);
        $row = $db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }
}