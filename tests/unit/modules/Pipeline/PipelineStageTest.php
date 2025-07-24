<?php

namespace Tests\Unit\Modules\Pipeline;

use Tests\TestCase;
use Modules\Pipeline\PipelineStage;

/**
 * Unit tests for PipelineStage class
 */
class PipelineStageTest extends TestCase
{
    /**
     * Test stage initialization
     */
    public function testStageInitialization(): void
    {
        $stage = new PipelineStage([
            'id' => 1,
            'name' => 'lead',
            'display_name' => 'Lead',
            'color' => '#6B7280',
            'wip_limit' => 50,
            'order_index' => 1
        ]);

        $this->assertEquals(1, $stage->getId());
        $this->assertEquals('lead', $stage->getName());
        $this->assertEquals('Lead', $stage->getDisplayName());
        $this->assertEquals('#6B7280', $stage->getColor());
        $this->assertEquals(50, $stage->getWipLimit());
        $this->assertEquals(1, $stage->getOrderIndex());
    }

    /**
     * Test WIP limit validation
     */
    public function testWipLimitValidation(): void
    {
        $stage = new PipelineStage([
            'name' => 'lead',
            'wip_limit' => 5
        ]);

        // Test within limit
        $this->assertTrue($stage->canAcceptDeals(3));
        $this->assertTrue($stage->canAcceptDeals(5));

        // Test exceeding limit
        $this->assertFalse($stage->canAcceptDeals(6));
        $this->assertFalse($stage->canAcceptDeals(10));

        // Test with no limit (null)
        $stageNoLimit = new PipelineStage([
            'name' => 'won',
            'wip_limit' => null
        ]);
        $this->assertTrue($stageNoLimit->canAcceptDeals(1000));
    }

    /**
     * Test stage transition rules
     */
    public function testStageTransitionRules(): void
    {
        $leadStage = new PipelineStage(['name' => 'lead']);
        $contactedStage = new PipelineStage(['name' => 'contacted']);
        $wonStage = new PipelineStage(['name' => 'won']);
        $lostStage = new PipelineStage(['name' => 'lost']);

        // Valid transitions
        $this->assertTrue($leadStage->canTransitionTo($contactedStage));
        $this->assertTrue($leadStage->canTransitionTo($lostStage));

        // Invalid transitions (won/lost are terminal)
        $this->assertFalse($wonStage->canTransitionTo($leadStage));
        $this->assertFalse($lostStage->canTransitionTo($contactedStage));

        // Can always move to lost
        $this->assertTrue($contactedStage->canTransitionTo($lostStage));
    }

    /**
     * Test stage metrics calculation
     */
    public function testStageMetrics(): void
    {
        $stage = new PipelineStage([
            'name' => 'proposal',
            'wip_limit' => 20
        ]);

        $deals = [
            ['id' => 1, 'amount' => 10000, 'time_in_stage' => 3],
            ['id' => 2, 'amount' => 25000, 'time_in_stage' => 5],
            ['id' => 3, 'amount' => 15000, 'time_in_stage' => 2],
        ];

        $metrics = $stage->calculateMetrics($deals);

        $this->assertEquals(3, $metrics['deal_count']);
        $this->assertEquals(50000, $metrics['total_value']);
        $this->assertEquals(3.33, $metrics['avg_time_in_stage'], '', 0.01);
        $this->assertEquals(15, $metrics['wip_usage_percentage']); // 3/20 * 100
        $this->assertEquals(17, $metrics['remaining_capacity']); // 20 - 3
    }

    /**
     * Test stale deal detection
     */
    public function testStaleDealDetection(): void
    {
        $stage = new PipelineStage(['name' => 'qualified']);

        $deals = [
            ['id' => 1, 'time_in_stage' => 5], // Not stale
            ['id' => 2, 'time_in_stage' => 7], // Stale (threshold)
            ['id' => 3, 'time_in_stage' => 10], // Stale
            ['id' => 4, 'time_in_stage' => 3], // Not stale
        ];

        $staleDeals = $stage->getStaleDeals($deals, 7);

        $this->assertCount(2, $staleDeals);
        $this->assertEquals(2, $staleDeals[0]['id']);
        $this->assertEquals(3, $staleDeals[1]['id']);
    }

    /**
     * Test stage color validation
     */
    public function testColorValidation(): void
    {
        // Valid hex colors
        $this->assertTrue(PipelineStage::isValidColor('#FFFFFF'));
        $this->assertTrue(PipelineStage::isValidColor('#000000'));
        $this->assertTrue(PipelineStage::isValidColor('#6B7280'));

        // Invalid colors
        $this->assertFalse(PipelineStage::isValidColor('red'));
        $this->assertFalse(PipelineStage::isValidColor('#GGGGGG'));
        $this->assertFalse(PipelineStage::isValidColor('#FFF')); // Short form not accepted
        $this->assertFalse(PipelineStage::isValidColor('6B7280')); // Missing #
    }

    /**
     * Test stage ordering
     */
    public function testStageOrdering(): void
    {
        $stages = [
            new PipelineStage(['name' => 'qualified', 'order_index' => 3]),
            new PipelineStage(['name' => 'lead', 'order_index' => 1]),
            new PipelineStage(['name' => 'contacted', 'order_index' => 2]),
        ];

        usort($stages, [PipelineStage::class, 'compareByOrder']);

        $this->assertEquals('lead', $stages[0]->getName());
        $this->assertEquals('contacted', $stages[1]->getName());
        $this->assertEquals('qualified', $stages[2]->getName());
    }

    /**
     * Test stage capacity warnings
     */
    public function testCapacityWarnings(): void
    {
        $stage = new PipelineStage([
            'name' => 'negotiation',
            'wip_limit' => 10
        ]);

        // No warning below 80%
        $this->assertFalse($stage->shouldShowCapacityWarning(7));

        // Warning at 80% or above
        $this->assertTrue($stage->shouldShowCapacityWarning(8));
        $this->assertTrue($stage->shouldShowCapacityWarning(9));
        $this->assertTrue($stage->shouldShowCapacityWarning(10));
    }

    /**
     * Test terminal stage identification
     */
    public function testTerminalStages(): void
    {
        $wonStage = new PipelineStage(['name' => 'won']);
        $lostStage = new PipelineStage(['name' => 'lost']);
        $leadStage = new PipelineStage(['name' => 'lead']);

        $this->assertTrue($wonStage->isTerminal());
        $this->assertTrue($lostStage->isTerminal());
        $this->assertFalse($leadStage->isTerminal());
    }

    /**
     * Test stage data serialization
     */
    public function testStageSerialization(): void
    {
        $stage = new PipelineStage([
            'id' => 1,
            'name' => 'proposal',
            'display_name' => 'Proposal',
            'color' => '#FBBF24',
            'wip_limit' => 20,
            'order_index' => 4
        ]);

        $data = $stage->toArray();

        $expected = [
            'id' => 1,
            'name' => 'proposal',
            'display_name' => 'Proposal',
            'color' => '#FBBF24',
            'wip_limit' => 20,
            'order_index' => 4,
            'is_terminal' => false,
            'is_active' => true
        ];

        $this->assertEquals($expected, $data);
    }
}