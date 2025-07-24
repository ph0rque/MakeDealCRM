<?php

namespace Tests\Unit\Modules\Pipeline;

use Tests\TestCase;
use Modules\Pipeline\PipelineDeal;
use DateTime;

/**
 * Unit tests for PipelineDeal class
 */
class PipelineDealTest extends TestCase
{
    /**
     * Test deal initialization
     */
    public function testDealInitialization(): void
    {
        $dealData = [
            'id' => 1,
            'name' => 'Big Corp Deal',
            'stage' => 'qualified',
            'amount' => 50000,
            'probability' => 60,
            'owner_id' => 1,
            'expected_close_date' => '2024-03-01',
            'created_at' => '2024-01-01 10:00:00',
            'stage_updated_at' => '2024-01-15 14:30:00',
            'time_in_stage' => 5
        ];

        $deal = new PipelineDeal($dealData);

        $this->assertEquals(1, $deal->getId());
        $this->assertEquals('Big Corp Deal', $deal->getName());
        $this->assertEquals('qualified', $deal->getStage());
        $this->assertEquals(50000, $deal->getAmount());
        $this->assertEquals(60, $deal->getProbability());
        $this->assertEquals(1, $deal->getOwnerId());
        $this->assertEquals('2024-03-01', $deal->getExpectedCloseDate());
        $this->assertEquals(5, $deal->getTimeInStage());
    }

    /**
     * Test time in stage calculation
     */
    public function testTimeInStageCalculation(): void
    {
        $deal = new PipelineDeal([
            'id' => 1,
            'name' => 'Test Deal',
            'stage' => 'proposal',
            'stage_updated_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ]);

        // Should be approximately 3 days
        $timeInStage = $deal->calculateTimeInStage();
        $this->assertGreaterThanOrEqual(3, $timeInStage);
        $this->assertLessThan(4, $timeInStage);
    }

    /**
     * Test stale deal detection
     */
    public function testStaleDealDetection(): void
    {
        // Fresh deal
        $freshDeal = new PipelineDeal([
            'id' => 1,
            'stage' => 'lead',
            'time_in_stage' => 3
        ]);
        $this->assertFalse($freshDeal->isStale());

        // Stale deal (7 days)
        $staleDeal = new PipelineDeal([
            'id' => 2,
            'stage' => 'qualified',
            'time_in_stage' => 7
        ]);
        $this->assertTrue($staleDeal->isStale());

        // Very stale deal
        $veryStaleDeal = new PipelineDeal([
            'id' => 3,
            'stage' => 'proposal',
            'time_in_stage' => 14
        ]);
        $this->assertTrue($veryStaleDeal->isStale());

        // Terminal stages should not be stale
        $wonDeal = new PipelineDeal([
            'id' => 4,
            'stage' => 'won',
            'time_in_stage' => 30
        ]);
        $this->assertFalse($wonDeal->isStale());

        $lostDeal = new PipelineDeal([
            'id' => 5,
            'stage' => 'lost',
            'time_in_stage' => 30
        ]);
        $this->assertFalse($lostDeal->isStale());
    }

    /**
     * Test expected value calculation
     */
    public function testExpectedValueCalculation(): void
    {
        $deal = new PipelineDeal([
            'amount' => 100000,
            'probability' => 0 // Start at 0%
        ]);

        // Test various probabilities
        $deal->setProbability(0);
        $this->assertEquals(0, $deal->getExpectedValue());

        $deal->setProbability(25);
        $this->assertEquals(25000, $deal->getExpectedValue());

        $deal->setProbability(50);
        $this->assertEquals(50000, $deal->getExpectedValue());

        $deal->setProbability(75);
        $this->assertEquals(75000, $deal->getExpectedValue());

        $deal->setProbability(100);
        $this->assertEquals(100000, $deal->getExpectedValue());
    }

    /**
     * Test stage transition validation
     */
    public function testStageTransitionValidation(): void
    {
        $deal = new PipelineDeal([
            'id' => 1,
            'stage' => 'lead'
        ]);

        // Valid transitions
        $this->assertTrue($deal->canMoveTo('contacted'));
        $this->assertTrue($deal->canMoveTo('lost'));

        // Invalid transitions
        $this->assertFalse($deal->canMoveTo('won')); // Can't skip to won
        $this->assertFalse($deal->canMoveTo('lead')); // Can't move to same stage

        // Terminal stage transitions
        $wonDeal = new PipelineDeal(['stage' => 'won']);
        $this->assertFalse($wonDeal->canMoveTo('lead')); // Can't move from won
        $this->assertFalse($wonDeal->canMoveTo('lost')); // Can't move from won

        $lostDeal = new PipelineDeal(['stage' => 'lost']);
        $this->assertFalse($lostDeal->canMoveTo('lead')); // Can't move from lost
        $this->assertFalse($lostDeal->canMoveTo('won')); // Can't move from lost
    }

    /**
     * Test stage history tracking
     */
    public function testStageHistoryTracking(): void
    {
        $deal = new PipelineDeal([
            'id' => 1,
            'stage' => 'lead',
            'stage_updated_at' => '2024-01-01 10:00:00',
            'time_in_stage' => 0
        ]);

        // Move to contacted
        $history1 = $deal->moveToStage('contacted', 1);
        
        $this->assertEquals('lead', $history1['from_stage']);
        $this->assertEquals('contacted', $history1['to_stage']);
        $this->assertEquals(1, $history1['changed_by']);
        $this->assertGreaterThanOrEqual(0, $history1['time_in_previous_stage']);
        $this->assertEquals('contacted', $deal->getStage());

        // Move to qualified
        sleep(1); // Ensure some time passes
        $history2 = $deal->moveToStage('qualified', 1);
        
        $this->assertEquals('contacted', $history2['from_stage']);
        $this->assertEquals('qualified', $history2['to_stage']);
        $this->assertGreaterThan(0, $history2['time_in_previous_stage']);
    }

    /**
     * Test deal priority calculation
     */
    public function testDealPriorityCalculation(): void
    {
        // High value, high probability, closing soon
        $highPriorityDeal = new PipelineDeal([
            'amount' => 100000,
            'probability' => 80,
            'expected_close_date' => date('Y-m-d', strtotime('+7 days')),
            'time_in_stage' => 2
        ]);
        $this->assertEquals('high', $highPriorityDeal->getPriority());

        // Low value, low probability, far out
        $lowPriorityDeal = new PipelineDeal([
            'amount' => 5000,
            'probability' => 20,
            'expected_close_date' => date('Y-m-d', strtotime('+90 days')),
            'time_in_stage' => 1
        ]);
        $this->assertEquals('low', $lowPriorityDeal->getPriority());

        // Stale deal should be high priority
        $staleDeal = new PipelineDeal([
            'amount' => 20000,
            'probability' => 50,
            'time_in_stage' => 10
        ]);
        $this->assertEquals('high', $staleDeal->getPriority());
    }

    /**
     * Test deal validation
     */
    public function testDealValidation(): void
    {
        $deal = new PipelineDeal([]);

        // Test required fields
        $errors = $deal->validate();
        $this->assertContains('Name is required', $errors);
        $this->assertContains('Stage is required', $errors);

        // Test with valid data
        $validDeal = new PipelineDeal([
            'name' => 'Valid Deal',
            'stage' => 'lead',
            'amount' => 10000
        ]);
        $errors = $validDeal->validate();
        $this->assertEmpty($errors);

        // Test invalid amount
        $invalidAmountDeal = new PipelineDeal([
            'name' => 'Invalid Amount Deal',
            'stage' => 'lead',
            'amount' => -1000
        ]);
        $errors = $invalidAmountDeal->validate();
        $this->assertContains('Amount must be positive', $errors);

        // Test invalid probability
        $invalidProbDeal = new PipelineDeal([
            'name' => 'Invalid Prob Deal',
            'stage' => 'lead',
            'probability' => 150
        ]);
        $errors = $invalidProbDeal->validate();
        $this->assertContains('Probability must be between 0 and 100', $errors);
    }

    /**
     * Test deal serialization
     */
    public function testDealSerialization(): void
    {
        $deal = new PipelineDeal([
            'id' => 1,
            'name' => 'Test Deal',
            'stage' => 'proposal',
            'amount' => 75000,
            'probability' => 65,
            'owner_id' => 2,
            'expected_close_date' => '2024-04-01',
            'time_in_stage' => 3,
            'is_stale' => false
        ]);

        $data = $deal->toArray();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('stage', $data);
        $this->assertArrayHasKey('amount', $data);
        $this->assertArrayHasKey('probability', $data);
        $this->assertArrayHasKey('expected_value', $data);
        $this->assertArrayHasKey('owner_id', $data);
        $this->assertArrayHasKey('expected_close_date', $data);
        $this->assertArrayHasKey('time_in_stage', $data);
        $this->assertArrayHasKey('is_stale', $data);
        $this->assertArrayHasKey('priority', $data);

        $this->assertEquals(48750, $data['expected_value']); // 75000 * 0.65
    }

    /**
     * Test deal cloning
     */
    public function testDealCloning(): void
    {
        $original = new PipelineDeal([
            'id' => 1,
            'name' => 'Original Deal',
            'stage' => 'qualified',
            'amount' => 50000
        ]);

        $clone = $original->clone();

        $this->assertNull($clone->getId()); // ID should be cleared
        $this->assertEquals('Original Deal (Copy)', $clone->getName());
        $this->assertEquals('lead', $clone->getStage()); // Reset to lead
        $this->assertEquals(50000, $clone->getAmount()); // Amount preserved
        $this->assertEquals(0, $clone->getTimeInStage()); // Reset time
    }

    /**
     * Test stage-specific probability defaults
     */
    public function testStageProbabilityDefaults(): void
    {
        $stages = [
            'lead' => 10,
            'contacted' => 20,
            'qualified' => 40,
            'proposal' => 60,
            'negotiation' => 80,
            'won' => 100,
            'lost' => 0
        ];

        foreach ($stages as $stage => $expectedProbability) {
            $deal = new PipelineDeal(['stage' => $stage]);
            $deal->applyStageDefaults();
            
            $this->assertEquals(
                $expectedProbability,
                $deal->getProbability(),
                "Stage {$stage} should have probability {$expectedProbability}"
            );
        }
    }
}