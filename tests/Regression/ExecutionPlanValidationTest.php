<?php

declare(strict_types=1);

namespace Tests\Regression;

use DbCms\Core\Capabilities\PlanValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ExecutionPlanValidationTest extends TestCase
{
    public function test_rejects_invented_capabilities(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PlanValidator)->validate(['steps' => [['id' => 'one', 'capability' => 'run_sql', 'arguments' => []]]], []);
    }

    public function test_model_cannot_supply_confirmation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PlanValidator)->validate(['steps' => [['id' => 'one', 'capability' => 'delete', 'arguments' => ['confirm' => true]]]], ['delete']);
    }

    public function test_rejects_forward_dependencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PlanValidator)->validate(['steps' => [['id' => 'one', 'capability' => 'read', 'arguments' => [], 'depends_on' => ['two']], ['id' => 'two', 'capability' => 'read', 'arguments' => []]]], ['read']);
    }

    public function test_valid_plan_retains_ordered_dependencies(): void
    {
        $plan = (new PlanValidator)->validate(['steps' => [['id' => 'one', 'capability' => 'read', 'arguments' => []], ['id' => 'two', 'capability' => 'draft', 'arguments' => ['content' => ['$ref' => 'one.content']], 'depends_on' => ['one']]]], ['read', 'draft']);
        self::assertCount(2, $plan->steps);
        self::assertSame(['one'], $plan->steps[1]->dependsOn);
    }
}
