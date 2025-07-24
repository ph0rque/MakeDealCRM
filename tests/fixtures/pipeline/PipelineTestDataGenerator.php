<?php

namespace Tests\Fixtures\Pipeline;

/**
 * Pipeline Test Data Generator
 * 
 * Generates realistic test data for pipeline testing
 */
class PipelineTestDataGenerator
{
    private array $companyNames = [
        'Acme Corp', 'TechStart', 'Global Industries', 'StartupXYZ', 'Enterprise Solutions',
        'Digital Dynamics', 'Cloud Nine', 'DataFlow Inc', 'SecureNet', 'AI Innovations',
        'Green Energy Co', 'FinTech Plus', 'Health Systems', 'EduTech Pro', 'Retail Max'
    ];
    
    private array $firstNames = [
        'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emma', 'Robert', 'Lisa', 'James', 'Mary'
    ];
    
    private array $lastNames = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'
    ];
    
    private array $stages = [
        'lead' => ['min' => 5000, 'max' => 50000, 'probability' => 10],
        'contacted' => ['min' => 10000, 'max' => 75000, 'probability' => 20],
        'qualified' => ['min' => 20000, 'max' => 100000, 'probability' => 40],
        'proposal' => ['min' => 30000, 'max' => 150000, 'probability' => 60],
        'negotiation' => ['min' => 40000, 'max' => 200000, 'probability' => 80],
        'won' => ['min' => 50000, 'max' => 250000, 'probability' => 100],
        'lost' => ['min' => 0, 'max' => 0, 'probability' => 0],
    ];

    /**
     * Generate multiple deals
     */
    public function generateDeals(int $count, array $options = []): array
    {
        $deals = [];
        
        for ($i = 0; $i < $count; $i++) {
            $deals[] = $this->generateDeal($options);
        }
        
        return $deals;
    }

    /**
     * Generate a single deal
     */
    public function generateDeal(array $options = []): array
    {
        $stage = $options['stage'] ?? $this->randomStage();
        $stageConfig = $this->stages[$stage];
        
        $deal = [
            'id' => $options['id'] ?? null,
            'name' => $options['name'] ?? $this->generateDealName(),
            'stage' => $stage,
            'amount' => $options['amount'] ?? rand($stageConfig['min'], $stageConfig['max']),
            'probability' => $options['probability'] ?? $stageConfig['probability'],
            'owner_id' => $options['owner_id'] ?? rand(1, 10),
            'account_id' => $options['account_id'] ?? rand(1, 100),
            'contact_name' => $options['contact_name'] ?? $this->generateContactName(),
            'expected_close_date' => $options['expected_close_date'] ?? $this->generateCloseDate($stage),
            'created_at' => $options['created_at'] ?? $this->generateCreatedDate(),
            'stage_updated_at' => $options['stage_updated_at'] ?? $this->generateStageDate($stage),
            'time_in_stage' => $options['time_in_stage'] ?? $this->generateTimeInStage($stage),
            'notes' => $options['notes'] ?? $this->generateNotes(),
            'tags' => $options['tags'] ?? $this->generateTags(),
            'is_stale' => false
        ];
        
        // Calculate if stale
        $deal['is_stale'] = $deal['time_in_stage'] >= 7 && !in_array($stage, ['won', 'lost']);
        
        return $deal;
    }

    /**
     * Generate deals with specific distribution across stages
     */
    public function generatePipelineDistribution(array $distribution): array
    {
        $deals = [];
        
        foreach ($distribution as $stage => $count) {
            for ($i = 0; $i < $count; $i++) {
                $deals[] = $this->generateDeal(['stage' => $stage]);
            }
        }
        
        // Shuffle to mix stages
        shuffle($deals);
        
        // Assign IDs
        foreach ($deals as $index => &$deal) {
            $deal['id'] = $index + 1;
        }
        
        return $deals;
    }

    /**
     * Generate a realistic pipeline scenario
     */
    public function generateRealisticPipeline(): array
    {
        // Typical funnel distribution
        $distribution = [
            'lead' => 50,
            'contacted' => 35,
            'qualified' => 20,
            'proposal' => 12,
            'negotiation' => 8,
            'won' => 15,
            'lost' => 10
        ];
        
        $deals = $this->generatePipelineDistribution($distribution);
        
        // Add some stale deals
        foreach ($deals as &$deal) {
            if (rand(1, 10) <= 2 && !in_array($deal['stage'], ['won', 'lost'])) {
                $deal['time_in_stage'] = rand(8, 20);
                $deal['is_stale'] = true;
            }
        }
        
        return $deals;
    }

    /**
     * Generate stress test data
     */
    public function generateStressTestData(int $dealCount = 1000): array
    {
        $deals = [];
        $stageKeys = array_keys($this->stages);
        
        for ($i = 0; $i < $dealCount; $i++) {
            $stage = $stageKeys[array_rand($stageKeys)];
            $deals[] = $this->generateDeal([
                'id' => $i + 1,
                'stage' => $stage,
                'name' => "Stress Test Deal #{$i}"
            ]);
        }
        
        return $deals;
    }

    /**
     * Generate WIP limit test scenarios
     */
    public function generateWipLimitScenarios(): array
    {
        $scenarios = [];
        
        // Scenario 1: Stage at capacity
        $scenarios['at_capacity'] = [
            'stage' => 'qualified',
            'wip_limit' => 5,
            'deals' => $this->generateDeals(5, ['stage' => 'qualified'])
        ];
        
        // Scenario 2: Stage over capacity
        $scenarios['over_capacity'] = [
            'stage' => 'proposal',
            'wip_limit' => 3,
            'deals' => $this->generateDeals(5, ['stage' => 'proposal'])
        ];
        
        // Scenario 3: Stage under capacity
        $scenarios['under_capacity'] = [
            'stage' => 'negotiation',
            'wip_limit' => 10,
            'deals' => $this->generateDeals(3, ['stage' => 'negotiation'])
        ];
        
        return $scenarios;
    }

    /**
     * Generate stage transition test data
     */
    public function generateTransitionTestData(): array
    {
        $transitions = [];
        
        // Valid transitions
        $validTransitions = [
            'lead' => ['contacted', 'lost'],
            'contacted' => ['qualified', 'lost'],
            'qualified' => ['proposal', 'lost'],
            'proposal' => ['negotiation', 'lost'],
            'negotiation' => ['won', 'lost']
        ];
        
        foreach ($validTransitions as $from => $toStages) {
            foreach ($toStages as $to) {
                $transitions[] = [
                    'deal' => $this->generateDeal(['stage' => $from]),
                    'from_stage' => $from,
                    'to_stage' => $to,
                    'is_valid' => true
                ];
            }
        }
        
        // Invalid transitions
        $invalidTransitions = [
            ['from' => 'lead', 'to' => 'won'],
            ['from' => 'lead', 'to' => 'negotiation'],
            ['from' => 'won', 'to' => 'lead'],
            ['from' => 'lost', 'to' => 'qualified']
        ];
        
        foreach ($invalidTransitions as $transition) {
            $transitions[] = [
                'deal' => $this->generateDeal(['stage' => $transition['from']]),
                'from_stage' => $transition['from'],
                'to_stage' => $transition['to'],
                'is_valid' => false
            ];
        }
        
        return $transitions;
    }

    // Private helper methods
    
    private function randomStage(): string
    {
        $stages = array_keys($this->stages);
        // Weight towards earlier stages for realistic funnel
        $weights = [30, 25, 20, 15, 5, 3, 2];
        $rand = rand(1, 100);
        $cumulative = 0;
        
        foreach ($weights as $index => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $stages[$index];
            }
        }
        
        return $stages[0];
    }
    
    private function generateDealName(): string
    {
        $company = $this->companyNames[array_rand($this->companyNames)];
        $types = ['Expansion', 'New Business', 'Renewal', 'Upgrade', 'Partnership'];
        $type = $types[array_rand($types)];
        
        return "{$company} - {$type}";
    }
    
    private function generateContactName(): string
    {
        $first = $this->firstNames[array_rand($this->firstNames)];
        $last = $this->lastNames[array_rand($this->lastNames)];
        
        return "{$first} {$last}";
    }
    
    private function generateCloseDate(string $stage): string
    {
        $daysOut = [
            'lead' => [30, 90],
            'contacted' => [20, 60],
            'qualified' => [15, 45],
            'proposal' => [10, 30],
            'negotiation' => [5, 15],
            'won' => [0, 0],
            'lost' => [0, 0]
        ];
        
        $range = $daysOut[$stage];
        $days = rand($range[0], $range[1]);
        
        return date('Y-m-d', strtotime("+{$days} days"));
    }
    
    private function generateCreatedDate(): string
    {
        $daysAgo = rand(1, 90);
        return date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
    }
    
    private function generateStageDate(string $stage): string
    {
        $maxDays = in_array($stage, ['won', 'lost']) ? 30 : 14;
        $daysAgo = rand(0, $maxDays);
        
        return date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
    }
    
    private function generateTimeInStage(string $stage): int
    {
        if (in_array($stage, ['won', 'lost'])) {
            return rand(0, 30);
        }
        
        // 70% chance of being fresh (< 7 days)
        if (rand(1, 10) <= 7) {
            return rand(0, 6);
        }
        
        // 30% chance of being stale
        return rand(7, 20);
    }
    
    private function generateNotes(): string
    {
        $notes = [
            'Initial contact made via email',
            'Had productive call with decision maker',
            'Sent proposal, awaiting feedback',
            'Customer comparing with competitors',
            'Budget approved, finalizing terms',
            'Following up next week',
            'Scheduled demo for next Tuesday',
            'Requested additional information',
            'Very interested in our solution',
            'Needs board approval'
        ];
        
        return $notes[array_rand($notes)];
    }
    
    private function generateTags(): array
    {
        $allTags = ['enterprise', 'smb', 'hot', 'cold', 'renewal', 'upsell', 'new', 'priority', 'at-risk'];
        $numTags = rand(0, 3);
        
        if ($numTags === 0) {
            return [];
        }
        
        $tags = [];
        $tagKeys = array_rand($allTags, $numTags);
        
        if (is_array($tagKeys)) {
            foreach ($tagKeys as $key) {
                $tags[] = $allTags[$key];
            }
        } else {
            $tags[] = $allTags[$tagKeys];
        }
        
        return $tags;
    }
}