<?php
/**
 * Lead Conversion Engine for automated lead-to-deal conversion
 * Handles scoring, qualification, and automatic conversion logic
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/mdeal_Leads/mdeal_Leads.php');
require_once('custom/modules/mdeal_Deals/mdeal_Deals.php');
require_once('custom/modules/mdeal_Accounts/mdeal_Accounts.php');
require_once('custom/modules/mdeal_Contacts/mdeal_Contacts.php');

class LeadConversionEngine
{
    protected $db;
    protected $conversionRules;
    protected $scoringCriteria;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->loadConversionRules();
        $this->loadScoringCriteria();
    }
    
    /**
     * Load conversion rules and thresholds
     */
    protected function loadConversionRules()
    {
        $this->conversionRules = [
            'auto_conversion' => [
                'enabled' => true,
                'min_score' => 80,
                'required_fields' => [
                    'company_name',
                    'industry',
                    'annual_revenue',
                    'employee_count',
                    'primary_contact_name',
                    'primary_contact_email'
                ],
                'additional_criteria' => [
                    'annual_revenue >= 5000000', // $5M minimum
                    'employee_count >= 25',      // 25+ employees
                    'industry_fit_score >= 70',  // Good industry fit
                    'geographic_fit_score >= 60' // Acceptable geography
                ]
            ],
            'review_conversion' => [
                'enabled' => true,
                'min_score' => 60,
                'max_score' => 79,
                'create_review_task' => true,
                'assign_to_senior' => true
            ],
            'qualification_required' => [
                'enabled' => true,
                'min_score' => 40,
                'max_score' => 59,
                'create_qualification_tasks' => true,
                'follow_up_sequence' => true
            ],
            'disqualification' => [
                'enabled' => true,
                'max_score' => 39,
                'auto_disqualify' => false, // Manual review required
                'create_disqualification_task' => true
            ]
        ];
    }
    
    /**
     * Load scoring criteria for lead evaluation
     */
    protected function loadScoringCriteria()
    {
        $this->scoringCriteria = [
            'company_size' => [
                'weight' => 25,
                'criteria' => [
                    'annual_revenue' => [
                        'ranges' => [
                            [100000000, 100], // $100M+ = 100 points
                            [50000000, 85],   // $50M-$100M = 85 points
                            [25000000, 70],   // $25M-$50M = 70 points
                            [10000000, 55],   // $10M-$25M = 55 points
                            [5000000, 40],    // $5M-$10M = 40 points
                            [1000000, 25],    // $1M-$5M = 25 points
                            [0, 10]           // <$1M = 10 points
                        ]
                    ],
                    'employee_count' => [
                        'ranges' => [
                            [1000, 100],      // 1000+ employees = 100 points
                            [500, 85],        // 500-1000 = 85 points
                            [250, 70],        // 250-500 = 70 points
                            [100, 55],        // 100-250 = 55 points
                            [50, 40],         // 50-100 = 40 points
                            [25, 25],         // 25-50 = 25 points
                            [0, 10]           // <25 = 10 points
                        ]
                    ]
                ]
            ],
            'industry_fit' => [
                'weight' => 20,
                'preferred_industries' => [
                    'Technology' => 100,
                    'Software' => 100,
                    'Healthcare' => 90,
                    'Financial Services' => 85,
                    'Manufacturing' => 80,
                    'Business Services' => 75,
                    'Consumer Products' => 70,
                    'Energy' => 65,
                    'Real Estate' => 60,
                    'Retail' => 50,
                    'Other' => 30
                ]
            ],
            'geographic_fit' => [
                'weight' => 15,
                'preferred_regions' => [
                    'North America' => 100,
                    'Western Europe' => 90,
                    'Asia Pacific' => 80,
                    'Eastern Europe' => 70,
                    'Latin America' => 60,
                    'Middle East' => 50,
                    'Africa' => 40,
                    'Other' => 20
                ]
            ],
            'financial_health' => [
                'weight' => 20,
                'criteria' => [
                    'ebitda_margin' => [
                        'ranges' => [
                            [25, 100],        // 25%+ EBITDA margin = 100 points
                            [20, 85],         // 20-25% = 85 points
                            [15, 70],         // 15-20% = 70 points
                            [10, 55],         // 10-15% = 55 points
                            [5, 40],          // 5-10% = 40 points
                            [0, 25],          // 0-5% = 25 points
                            [-100, 10]       // Negative = 10 points
                        ]
                    ],
                    'growth_rate' => [
                        'ranges' => [
                            [50, 100],        // 50%+ growth = 100 points
                            [30, 85],         // 30-50% = 85 points
                            [20, 70],         // 20-30% = 70 points
                            [10, 55],         // 10-20% = 55 points
                            [5, 40],          // 5-10% = 40 points
                            [0, 25],          // 0-5% = 25 points
                            [-100, 10]       // Negative = 10 points
                        ]
                    ]
                ]
            ],
            'engagement_level' => [
                'weight' => 10,
                'criteria' => [
                    'interaction_count' => [
                        'ranges' => [
                            [10, 100],        // 10+ interactions = 100 points
                            [7, 85],          // 7-10 = 85 points
                            [5, 70],          // 5-7 = 70 points
                            [3, 55],          // 3-5 = 55 points
                            [1, 40],          // 1-3 = 40 points
                            [0, 10]           // No interactions = 10 points
                        ]
                    ],
                    'response_quality' => [
                        'values' => [
                            'high' => 100,
                            'medium' => 70,
                            'low' => 40,
                            'none' => 10
                        ]
                    ]
                ]
            ],
            'timing_readiness' => [
                'weight' => 10,
                'criteria' => [
                    'urgency_level' => [
                        'values' => [
                            'immediate' => 100,
                            'within_6_months' => 85,
                            'within_12_months' => 70,
                            'within_24_months' => 55,
                            'exploring' => 40,
                            'no_timeline' => 20
                        ]
                    ],
                    'budget_confirmed' => [
                        'values' => [
                            'confirmed' => 100,
                            'estimated' => 70,
                            'unknown' => 30
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Process leads for potential conversion
     */
    public function processLeadsForConversion($batchSize = 50)
    {
        $processedLeads = [];
        
        // Get qualified leads for processing
        $leads = $this->getQualifiedLeads($batchSize);
        
        foreach ($leads as $lead) {
            try {
                $result = $this->evaluateLeadForConversion($lead);
                $processedLeads[] = $result;
                
                // Execute conversion action based on score
                $this->executeConversionAction($lead, $result);
                
            } catch (Exception $e) {
                $GLOBALS['log']->error("Lead conversion processing failed for lead {$lead->id}: " . $e->getMessage());
            }
        }
        
        return $processedLeads;
    }
    
    /**
     * Get leads qualified for conversion evaluation
     */
    protected function getQualifiedLeads($limit = 50)
    {
        $query = "SELECT * FROM mdeal_leads 
                  WHERE deleted = 0 
                  AND status NOT IN ('converted', 'disqualified', 'dead')
                  AND (last_evaluation_date IS NULL OR last_evaluation_date < DATE_SUB(NOW(), INTERVAL 7 DAY))
                  ORDER BY lead_score DESC, date_modified DESC
                  LIMIT ?";
        
        $result = $this->db->pQuery($query, [$limit]);
        $leads = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $lead = BeanFactory::newBean('mdeal_Leads');
            $lead->populateFromRow($row);
            $leads[] = $lead;
        }
        
        return $leads;
    }
    
    /**
     * Evaluate lead for conversion readiness
     */
    public function evaluateLeadForConversion($lead)
    {
        $evaluation = [
            'lead_id' => $lead->id,
            'lead_name' => $lead->company_name,
            'current_score' => $lead->lead_score ?? 0,
            'calculated_score' => 0,
            'score_breakdown' => [],
            'conversion_recommendation' => '',
            'required_actions' => [],
            'missing_information' => [],
            'evaluation_date' => date('Y-m-d H:i:s')
        ];
        
        // Calculate comprehensive score
        $totalScore = 0;
        $totalWeight = 0;
        
        foreach ($this->scoringCriteria as $category => $config) {
            $categoryScore = $this->calculateCategoryScore($lead, $category, $config);
            $weightedScore = $categoryScore * ($config['weight'] / 100);
            
            $evaluation['score_breakdown'][$category] = [
                'score' => $categoryScore,
                'weight' => $config['weight'],
                'weighted_score' => $weightedScore
            ];
            
            $totalScore += $weightedScore;
            $totalWeight += $config['weight'];
        }
        
        $evaluation['calculated_score'] = round($totalScore, 2);
        
        // Determine conversion recommendation
        $evaluation['conversion_recommendation'] = $this->getConversionRecommendation($evaluation['calculated_score']);
        
        // Identify missing information
        $evaluation['missing_information'] = $this->identifyMissingInformation($lead);
        
        // Suggest required actions
        $evaluation['required_actions'] = $this->suggestRequiredActions($lead, $evaluation);
        
        // Update lead with evaluation results
        $this->updateLeadEvaluation($lead, $evaluation);
        
        return $evaluation;
    }
    
    /**
     * Calculate score for a specific category
     */
    protected function calculateCategoryScore($lead, $category, $config)
    {
        switch ($category) {
            case 'company_size':
                return $this->calculateCompanySizeScore($lead, $config);
                
            case 'industry_fit':
                return $this->calculateIndustryFitScore($lead, $config);
                
            case 'geographic_fit':
                return $this->calculateGeographicFitScore($lead, $config);
                
            case 'financial_health':
                return $this->calculateFinancialHealthScore($lead, $config);
                
            case 'engagement_level':
                return $this->calculateEngagementScore($lead, $config);
                
            case 'timing_readiness':
                return $this->calculateTimingReadinessScore($lead, $config);
                
            default:
                return 50; // Default medium score
        }
    }
    
    /**
     * Calculate company size score
     */
    protected function calculateCompanySizeScore($lead, $config)
    {
        $revenueScore = $this->getScoreFromRanges($lead->annual_revenue ?? 0, $config['criteria']['annual_revenue']['ranges']);
        $employeeScore = $this->getScoreFromRanges($lead->employee_count ?? 0, $config['criteria']['employee_count']['ranges']);
        
        return ($revenueScore + $employeeScore) / 2;
    }
    
    /**
     * Calculate industry fit score
     */
    protected function calculateIndustryFitScore($lead, $config)
    {
        $industry = $lead->industry ?? 'Other';
        return $config['preferred_industries'][$industry] ?? $config['preferred_industries']['Other'];
    }
    
    /**
     * Calculate geographic fit score
     */
    protected function calculateGeographicFitScore($lead, $config)
    {
        $region = $lead->geographic_region ?? 'Other';
        return $config['preferred_regions'][$region] ?? $config['preferred_regions']['Other'];
    }
    
    /**
     * Calculate financial health score
     */
    protected function calculateFinancialHealthScore($lead, $config)
    {
        $ebitdaMargin = 0;
        if (!empty($lead->annual_revenue) && !empty($lead->ebitda)) {
            $ebitdaMargin = ($lead->ebitda / $lead->annual_revenue) * 100;
        }
        
        $ebitdaScore = $this->getScoreFromRanges($ebitdaMargin, $config['criteria']['ebitda_margin']['ranges']);
        $growthScore = $this->getScoreFromRanges($lead->growth_rate ?? 0, $config['criteria']['growth_rate']['ranges']);
        
        return ($ebitdaScore + $growthScore) / 2;
    }
    
    /**
     * Calculate engagement score
     */
    protected function calculateEngagementScore($lead, $config)
    {
        $interactionCount = $this->getLeadInteractionCount($lead->id);
        $interactionScore = $this->getScoreFromRanges($interactionCount, $config['criteria']['interaction_count']['ranges']);
        
        $responseQuality = $lead->response_quality ?? 'none';
        $qualityScore = $config['criteria']['response_quality']['values'][$responseQuality] ?? 10;
        
        return ($interactionScore + $qualityScore) / 2;
    }
    
    /**
     * Calculate timing readiness score
     */
    protected function calculateTimingReadinessScore($lead, $config)
    {
        $urgency = $lead->urgency_level ?? 'no_timeline';
        $urgencyScore = $config['criteria']['urgency_level']['values'][$urgency] ?? 20;
        
        $budget = $lead->budget_status ?? 'unknown';
        $budgetScore = $config['criteria']['budget_confirmed']['values'][$budget] ?? 30;
        
        return ($urgencyScore + $budgetScore) / 2;
    }
    
    /**
     * Get score from ranges configuration
     */
    protected function getScoreFromRanges($value, $ranges)
    {
        foreach ($ranges as $range) {
            if ($value >= $range[0]) {
                return $range[1];
            }
        }
        
        return 0;
    }
    
    /**
     * Get lead interaction count
     */
    protected function getLeadInteractionCount($leadId)
    {
        $query = "SELECT COUNT(*) as count FROM (
            SELECT id FROM calls WHERE parent_type = 'mdeal_Leads' AND parent_id = ? AND deleted = 0
            UNION ALL
            SELECT id FROM meetings WHERE parent_type = 'mdeal_Leads' AND parent_id = ? AND deleted = 0
            UNION ALL
            SELECT id FROM emails WHERE parent_type = 'mdeal_Leads' AND parent_id = ? AND deleted = 0
            UNION ALL
            SELECT id FROM notes WHERE parent_type = 'mdeal_Leads' AND parent_id = ? AND deleted = 0
        ) interactions";
        
        $result = $this->db->pQuery($query, [$leadId, $leadId, $leadId, $leadId]);
        $row = $this->db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }
    
    /**
     * Get conversion recommendation based on score
     */
    protected function getConversionRecommendation($score)
    {
        if ($score >= 80) {
            return 'auto_conversion';
        } elseif ($score >= 60) {
            return 'review_conversion';
        } elseif ($score >= 40) {
            return 'qualification_required';
        } else {
            return 'disqualification';
        }
    }
    
    /**
     * Identify missing information for lead
     */
    protected function identifyMissingInformation($lead)
    {
        $requiredFields = $this->conversionRules['auto_conversion']['required_fields'];
        $missing = [];
        
        foreach ($requiredFields as $field) {
            if (empty($lead->$field)) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    /**
     * Suggest required actions based on evaluation
     */
    protected function suggestRequiredActions($lead, $evaluation)
    {
        $actions = [];
        $score = $evaluation['calculated_score'];
        
        if (!empty($evaluation['missing_information'])) {
            $actions[] = 'Gather missing information: ' . implode(', ', $evaluation['missing_information']);
        }
        
        if ($score < 60) {
            $actions[] = 'Improve lead qualification through additional discovery calls';
        }
        
        if (empty($lead->primary_contact_email)) {
            $actions[] = 'Obtain primary contact email address';
        }
        
        if (empty($lead->decision_maker_identified)) {
            $actions[] = 'Identify and engage with decision maker';
        }
        
        if ($evaluation['score_breakdown']['financial_health']['score'] < 60) {
            $actions[] = 'Validate financial information and health metrics';
        }
        
        if ($evaluation['score_breakdown']['engagement_level']['score'] < 50) {
            $actions[] = 'Increase engagement through regular touchpoints';
        }
        
        return $actions;
    }
    
    /**
     * Execute conversion action based on recommendation
     */
    protected function executeConversionAction($lead, $evaluation)
    {
        $recommendation = $evaluation['conversion_recommendation'];
        $rule = $this->conversionRules[$recommendation];
        
        switch ($recommendation) {
            case 'auto_conversion':
                if ($this->validateAutoConversionCriteria($lead, $evaluation)) {
                    $this->executeAutoConversion($lead, $evaluation);
                } else {
                    $this->createReviewTask($lead, $evaluation, 'Auto-conversion criteria not fully met');
                }
                break;
                
            case 'review_conversion':
                $this->createReviewTask($lead, $evaluation, 'Manual review required for conversion');
                break;
                
            case 'qualification_required':
                $this->createQualificationTasks($lead, $evaluation);
                break;
                
            case 'disqualification':
                $this->createDisqualificationTask($lead, $evaluation);
                break;
        }
    }
    
    /**
     * Validate auto-conversion criteria
     */
    protected function validateAutoConversionCriteria($lead, $evaluation)
    {
        $rule = $this->conversionRules['auto_conversion'];
        
        // Check minimum score
        if ($evaluation['calculated_score'] < $rule['min_score']) {
            return false;
        }
        
        // Check required fields
        if (!empty($evaluation['missing_information'])) {
            return false;
        }
        
        // Check additional criteria
        foreach ($rule['additional_criteria'] as $criterion) {
            if (!$this->evaluateCriterion($lead, $criterion)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Execute automatic lead conversion
     */
    protected function executeAutoConversion($lead, $evaluation)
    {
        try {
            // Begin transaction
            $this->db->query('START TRANSACTION');
            
            // Convert lead to deal
            $deal = $this->convertLeadToDeal($lead);
            
            // Create or link account
            $account = $this->createOrLinkAccount($lead, $deal);
            
            // Create or link contact
            $contact = $this->createOrLinkContact($lead, $account);
            
            // Update lead status
            $lead->status = 'converted';
            $lead->converted_deal_id = $deal->id;
            $lead->converted_account_id = $account->id;
            $lead->converted_contact_id = $contact->id;
            $lead->conversion_date = date('Y-m-d H:i:s');
            $lead->conversion_score = $evaluation['calculated_score'];
            $lead->save();
            
            // Create conversion log
            $this->logConversion($lead, $deal, $account, $contact, 'auto', $evaluation);
            
            // Send notifications
            $this->sendConversionNotifications($lead, $deal, 'auto');
            
            // Commit transaction
            $this->db->query('COMMIT');
            
            $GLOBALS['log']->info("Auto-converted lead {$lead->id} to deal {$deal->id} with score {$evaluation['calculated_score']}");
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->query('ROLLBACK');
            
            $GLOBALS['log']->error("Auto-conversion failed for lead {$lead->id}: " . $e->getMessage());
            
            // Create manual review task for failed auto-conversion
            $this->createReviewTask($lead, $evaluation, 'Auto-conversion failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Convert lead to deal
     */
    protected function convertLeadToDeal($lead)
    {
        $deal = BeanFactory::newBean('mdeal_Deals');
        
        // Map lead fields to deal fields
        $deal->name = $lead->company_name . ' - Acquisition Opportunity';
        $deal->company_name = $lead->company_name;
        $deal->industry = $lead->industry;
        $deal->annual_revenue = $lead->annual_revenue;
        $deal->ebitda = $lead->ebitda;
        $deal->employee_count = $lead->employee_count;
        $deal->deal_source = $lead->lead_source;
        $deal->deal_value = $lead->estimated_deal_value ?? $lead->annual_revenue * 3; // 3x revenue estimate
        $deal->stage = 'sourcing'; // Start in sourcing stage
        $deal->stage_entered_date = date('Y-m-d H:i:s');
        $deal->probability = 20; // Initial probability for sourcing stage
        $deal->assigned_user_id = $lead->assigned_user_id;
        $deal->description = "Converted from lead: {$lead->id}\n\n" . ($lead->description ?? '');
        
        // Set initial dates
        $deal->date_entered = date('Y-m-d H:i:s');
        $deal->close_date = date('Y-m-d', strtotime('+12 months')); // 12-month target
        
        $deal->save();
        
        return $deal;
    }
    
    /**
     * Create or link account
     */
    protected function createOrLinkAccount($lead, $deal)
    {
        // Check if account already exists
        $existingAccount = $this->findExistingAccount($lead->company_name);
        
        if ($existingAccount) {
            // Link to existing account
            $deal->account_id = $existingAccount->id;
            $deal->save();
            return $existingAccount;
        }
        
        // Create new account
        $account = BeanFactory::newBean('mdeal_Accounts');
        $account->name = $lead->company_name;
        $account->account_type = 'target';
        $account->industry = $lead->industry;
        $account->annual_revenue = $lead->annual_revenue;
        $account->ebitda = $lead->ebitda;
        $account->employee_count = $lead->employee_count;
        $account->website = $lead->website;
        $account->phone_office = $lead->phone_office;
        $account->email = $lead->email_address;
        $account->assigned_user_id = $lead->assigned_user_id;
        $account->description = "Created from lead conversion: {$lead->id}";
        
        // Copy address information
        $account->billing_address_street = $lead->primary_address_street;
        $account->billing_address_city = $lead->primary_address_city;
        $account->billing_address_state = $lead->primary_address_state;
        $account->billing_address_postalcode = $lead->primary_address_postalcode;
        $account->billing_address_country = $lead->primary_address_country;
        
        $account->save();
        
        // Link deal to account
        $deal->account_id = $account->id;
        $deal->save();
        
        return $account;
    }
    
    /**
     * Create or link contact
     */
    protected function createOrLinkContact($lead, $account)
    {
        // Check if contact already exists
        $existingContact = $this->findExistingContact($lead->primary_contact_email ?? $lead->email_address);
        
        if ($existingContact) {
            // Link to account if not already linked
            if ($existingContact->account_id !== $account->id) {
                $existingContact->account_id = $account->id;
                $existingContact->save();
            }
            return $existingContact;
        }
        
        // Create new contact
        $contact = BeanFactory::newBean('mdeal_Contacts');
        $contact->first_name = $lead->primary_contact_name ?? $lead->first_name ?? '';
        $contact->last_name = $lead->last_name ?? 'Unknown';
        $contact->title = $lead->title;
        $contact->account_id = $account->id;
        $contact->email1 = $lead->primary_contact_email ?? $lead->email_address;
        $contact->phone_work = $lead->phone_work ?? $lead->phone_office;
        $contact->phone_mobile = $lead->phone_mobile;
        $contact->contact_type = 'key_stakeholder';
        $contact->decision_role = 'evaluator'; // Default role
        $contact->assigned_user_id = $lead->assigned_user_id;
        $contact->description = "Created from lead conversion: {$lead->id}";
        
        $contact->save();
        
        return $contact;
    }
    
    /**
     * Find existing account by name
     */
    protected function findExistingAccount($companyName)
    {
        $query = "SELECT id FROM mdeal_accounts 
                  WHERE name = ? AND deleted = 0 
                  LIMIT 1";
        
        $result = $this->db->pQuery($query, [$companyName]);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            return BeanFactory::getBean('mdeal_Accounts', $row['id']);
        }
        
        return null;
    }
    
    /**
     * Find existing contact by email
     */
    protected function findExistingContact($email)
    {
        if (empty($email)) {
            return null;
        }
        
        $query = "SELECT c.id FROM mdeal_contacts c
                  LEFT JOIN email_addresses ea ON c.id = ea.bean_id
                  WHERE ea.email_address = ? AND c.deleted = 0 
                  LIMIT 1";
        
        $result = $this->db->pQuery($query, [$email]);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            return BeanFactory::getBean('mdeal_Contacts', $row['id']);
        }
        
        return null;
    }
    
    /**
     * Create review task for manual conversion
     */
    protected function createReviewTask($lead, $evaluation, $reason)
    {
        $task = BeanFactory::newBean('Tasks');
        $task->name = "Review Lead for Conversion: {$lead->company_name}";
        $task->description = "Lead Score: {$evaluation['calculated_score']}\n" .
                           "Reason: {$reason}\n" .
                           "Required Actions: " . implode(', ', $evaluation['required_actions']);
        $task->parent_type = 'mdeal_Leads';
        $task->parent_id = $lead->id;
        $task->assigned_user_id = $this->getSeniorUser($lead->assigned_user_id);
        $task->priority = 'High';
        $task->status = 'Not Started';
        
        // Set due date to 3 days from now
        $dueDate = new DateTime();
        $dueDate->add(new DateInterval('P3D'));
        $task->date_due = $dueDate->format('Y-m-d');
        
        $task->save();
    }
    
    /**
     * Create qualification tasks
     */
    protected function createQualificationTasks($lead, $evaluation)
    {
        foreach ($evaluation['required_actions'] as $action) {
            $task = BeanFactory::newBean('Tasks');
            $task->name = "Lead Qualification: {$action}";
            $task->description = "Lead: {$lead->company_name}\nScore: {$evaluation['calculated_score']}\nAction: {$action}";
            $task->parent_type = 'mdeal_Leads';
            $task->parent_id = $lead->id;
            $task->assigned_user_id = $lead->assigned_user_id;
            $task->priority = 'Medium';
            $task->status = 'Not Started';
            
            // Set due date to 7 days from now
            $dueDate = new DateTime();
            $dueDate->add(new DateInterval('P7D'));
            $task->date_due = $dueDate->format('Y-m-d');
            
            $task->save();
        }
    }
    
    /**
     * Create disqualification task
     */
    protected function createDisqualificationTask($lead, $evaluation)
    {
        $task = BeanFactory::newBean('Tasks');
        $task->name = "Review Lead for Disqualification: {$lead->company_name}";
        $task->description = "Low lead score: {$evaluation['calculated_score']}\n" .
                           "Consider disqualification or further qualification efforts.";
        $task->parent_type = 'mdeal_Leads';
        $task->parent_id = $lead->id;
        $task->assigned_user_id = $lead->assigned_user_id;
        $task->priority = 'Low';
        $task->status = 'Not Started';
        
        // Set due date to 14 days from now
        $dueDate = new DateTime();
        $dueDate->add(new DateInterval('P14D'));
        $task->date_due = $dueDate->format('Y-m-d');
        
        $task->save();
    }
    
    /**
     * Update lead with evaluation results
     */
    protected function updateLeadEvaluation($lead, $evaluation)
    {
        $lead->lead_score = $evaluation['calculated_score'];
        $lead->conversion_recommendation = $evaluation['conversion_recommendation'];
        $lead->last_evaluation_date = date('Y-m-d H:i:s');
        $lead->score_breakdown = json_encode($evaluation['score_breakdown']);
        $lead->save();
    }
    
    /**
     * Get senior user for escalation
     */
    protected function getSeniorUser($assignedUserId)
    {
        // This would implement logic to find a senior user
        // For now, return the same user
        return $assignedUserId;
    }
    
    /**
     * Log conversion for audit trail
     */
    protected function logConversion($lead, $deal, $account, $contact, $type, $evaluation)
    {
        $logId = create_guid();
        
        $query = "INSERT INTO mdeal_lead_conversions 
                  (id, lead_id, deal_id, account_id, contact_id, conversion_type, conversion_score, evaluation_data, created_date)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->pQuery($query, [
            $logId,
            $lead->id,
            $deal->id,
            $account->id,
            $contact->id,
            $type,
            $evaluation['calculated_score'],
            json_encode($evaluation)
        ]);
    }
    
    /**
     * Send conversion notifications
     */
    protected function sendConversionNotifications($lead, $deal, $type)
    {
        // This would implement email notifications
        $GLOBALS['log']->info("Lead {$lead->id} converted to deal {$deal->id} via {$type} conversion");
    }
    
    /**
     * Evaluate a single criterion
     */
    protected function evaluateCriterion($lead, $criterion)
    {
        // Parse criterion like "annual_revenue >= 5000000"
        if (preg_match('/(.+)\s*(>=|<=|>|<|==|!=)\s*(.+)/', $criterion, $matches)) {
            $field = trim($matches[1]);
            $operator = trim($matches[2]);
            $value = trim($matches[3]);
            
            $fieldValue = $lead->$field ?? 0;
            
            switch ($operator) {
                case '>=':
                    return $fieldValue >= floatval($value);
                case '<=':
                    return $fieldValue <= floatval($value);
                case '>':
                    return $fieldValue > floatval($value);
                case '<':
                    return $fieldValue < floatval($value);
                case '==':
                    return $fieldValue == $value;
                case '!=':
                    return $fieldValue != $value;
            }
        }
        
        return false;
    }
    
    /**
     * Get conversion statistics
     */
    public function getConversionStatistics($timeframe = '30 days')
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$timeframe}"));
        
        $query = "SELECT 
                    conversion_type,
                    COUNT(*) as conversion_count,
                    AVG(conversion_score) as avg_score,
                    MIN(conversion_score) as min_score,
                    MAX(conversion_score) as max_score
                  FROM mdeal_lead_conversions 
                  WHERE created_date >= ?
                  GROUP BY conversion_type";
        
        $result = $this->db->pQuery($query, [$cutoffDate]);
        $statistics = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $statistics[$row['conversion_type']] = [
                'count' => intval($row['conversion_count']),
                'avg_score' => round($row['avg_score'], 2),
                'min_score' => round($row['min_score'], 2),
                'max_score' => round($row['max_score'], 2)
            ];
        }
        
        return $statistics;
    }
}